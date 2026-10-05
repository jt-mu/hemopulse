<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
if (!empty($_SESSION['user_id']) && ($_SESSION['last_activity'] ?? time()) < time() - 1800) {
    $_SESSION = []; session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function statusBadge(string $status): string { return '<span class="hp-status" data-state="'.h(strtolower($status)).'">'.h($status).'</span>'; }
function csrfToken(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function validCsrf(): bool { return is_string($_POST['csrf'] ?? null) && hash_equals(csrfToken(), $_POST['csrf']); }
function flash(string $message, string $type = 'error'): void { $_SESSION['flash'] = compact('message', 'type'); }
function showFlash(): void {
    if (isset($_SESSION['flash'])) {
        $notice = $_SESSION['flash']; unset($_SESSION['flash']);
        echo '<p class="app-notice ' . h($notice['type']) . '" role="status">' . h($notice['message']) . '</p>';
    }
}
function redirectTo(string $path): void { header('Location: ' . $path, true, 303); exit; }
function currentUser(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = getDBConnection()->prepare('SELECT u.user_id, u.public_reference, u.email, u.first_name, u.middle_name, u.last_name, u.profile_image, u.contact_number, u.account_status, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?');
    $stmt->execute([$_SESSION['user_id']]); $user = $stmt->fetch();
    if (!$user || $user['account_status'] !== 'Active') {
        unset($_SESSION['user_id'], $_SESSION['role_name']); return null;
    }
    return $user;
}
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
