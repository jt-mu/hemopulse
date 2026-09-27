<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/eligibility.php';
require_once __DIR__ . '/campaigns.php';
class ApiError extends RuntimeException {
    public int $httpStatus;
    public function __construct(string $message, int $status = 422) { parent::__construct($message); $this->httpStatus = $status; }
}
function apiUser(array $roles = []): array {
    $user = currentUser();
    if (!$user) throw new ApiError('Please log in to continue.', 401);
    if ($roles && !in_array($user['role_name'], $roles, true)) throw new ApiError('You do not have permission for this action.', 403);
    return $user;
}
function apiBody(): array {
    if (!str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) throw new ApiError('Send an application/json request.', 415);
    $raw = file_get_contents('php://input');
    if (strlen($raw) > 32768) throw new ApiError('Request is too large.', 413);
    $data = json_decode($raw, true);
    if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) throw new ApiError('Invalid JSON object.', 400);
    return $data;
}
function apiCsrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals(csrfToken(), $token)) throw new ApiError('Your session form expired. Refresh and try again.', 403);
}
function field(array $data, string $key, int $max, bool $required = true): string {
    $value = inputText($data, $key);
    if (($required && $value === '') || mb_strlen($value) > $max) throw new ApiError('Check the ' . str_replace('_', ' ', $key) . ' field.');
    return $value;
}
function choice(array $data, string $key, array $allowed): string {
    $value = inputText($data, $key);
    if (!in_array($value, $allowed, true)) throw new ApiError('Invalid ' . str_replace('_', ' ', $key) . '.');
    return $value;
}
function integer(array $data, string $key, int $min = 1, int $max = 100000): int {
    $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_INT);
    if ($value === false || $value === null || $value < $min || $value > $max) throw new ApiError('Invalid ' . str_replace('_', ' ', $key) . '.');
    return $value;
}
function validDate(string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value;
}
function dateFilters(string $column, array &$where, array &$params): void {
    foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
        $value = inputText($_GET, $key);
        if ($value !== '') {
            if (!validDate($value)) throw new ApiError('Use a valid date filter.', 400);
            $where[] = "DATE($column) $operator ?"; $params[] = $value;
        }
    }
    if (!empty($_GET['from']) && !empty($_GET['to']) && $_GET['from'] > $_GET['to']) throw new ApiError('Start date must precede end date.', 400);
}
function paginated(PDO $pdo, string $select, string $from, array $where, array $params, array $sorts, string $default): array {
    $page = max(1, min(10000, (int)($_GET['page'] ?? 1)));
    $size = max(1, min(50, (int)($_GET['limit'] ?? 10)));
    $sort = $sorts[inputText($_GET, 'sort')] ?? $sorts[$default];
    $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $from . $clause); $stmt->execute($params); $total = (int)$stmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $size)); $page = min($page, $pages);
    $stmt = $pdo->prepare("SELECT $select FROM $from$clause ORDER BY $sort LIMIT $size OFFSET " . (($page - 1) * $size)); $stmt->execute($params);
    return ['status' => 'success', 'data' => $stmt->fetchAll(), 'meta' => ['page' => $page, 'pages' => $pages, 'total' => $total, 'limit' => $size]];
}
function auditAction(PDO $pdo, int $userId, string $action, string $table, int $id): void {
    $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action_performed, affected_table, target_record_id, client_ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $action, $table, $id, substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
}
function transaction(PDO $pdo, callable $work) {
    $pdo->beginTransaction();
    try { $result = $work(); $pdo->commit(); return $result; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
