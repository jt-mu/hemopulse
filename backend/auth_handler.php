<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$action = $_POST['action'] ?? '';
$destination = '../index.php#' . ($action === 'register' ? 'register' : 'login');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
if (!validCsrf()) { flash('Your form expired. Please try again.'); redirectTo($destination); }
if ($action === 'logout') {
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    session_destroy(); redirectTo('../index.php');
}
try {
    if (!in_array($action, ['login', 'register'], true)) throw new InvalidArgumentException('Invalid account action.');
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || $password === '') throw new InvalidArgumentException('Enter a valid email address and password.');
    $pdo = getDBConnection();
    if ($action === 'register') {
        $first = trim(is_string($_POST['first_name'] ?? null) ? $_POST['first_name'] : '');
        $last = trim(is_string($_POST['last_name'] ?? null) ? $_POST['last_name'] : '');
        $middle = trim(is_string($_POST['middle_name'] ?? null) ? $_POST['middle_name'] : '');
        if (mb_strlen($middle) > 50) throw new InvalidArgumentException('Middle name must be at most 50 characters.');
        if ($first === '' || $last === '' || mb_strlen($first) > 50 || mb_strlen($last) > 50) throw new InvalidArgumentException('Enter your first and last name, up to 50 characters each.');
        if (strlen($password) < 12 || strlen($password) > 72) throw new InvalidArgumentException('Use a password between 12 and 72 bytes long.');
        if (($_POST['terms'] ?? '') !== '1') throw new InvalidArgumentException('Please accept the terms to register.');
        $role = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'Donor'")->fetchColumn();
        if (!$role) throw new RuntimeException('Donor role is missing.');
        $stmt = $pdo->prepare('INSERT INTO users (role_id, email, password_hash, first_name, middle_name, last_name) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$role, $email, password_hash($password, PASSWORD_DEFAULT), $first, $middle ?: null, $last]);
        flash('Account created. You can now log in.', 'success'); redirectTo('../index.php#login');
    }
    $identity = hash('sha256', strtolower($email) . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    $attempts = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE identity_hash = ? AND attempted_at > ?');
    $attempts->execute([$identity, date('Y-m-d H:i:s', time() - 900)]);
    if ($attempts->fetchColumn() >= 5) throw new InvalidArgumentException('Too many login attempts. Please try again in 15 minutes.');
    $pdo->prepare('INSERT INTO login_attempts (identity_hash, attempted_at) VALUES (?, ?)')->execute([$identity, date('Y-m-d H:i:s')]);
    $stmt = $pdo->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE email = ?');
    $stmt->execute([$email]); $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password_hash']) || $user['account_status'] !== 'Active') throw new InvalidArgumentException('The email or password is incorrect, or the account is inactive.');
    $pdo->prepare('DELETE FROM login_attempts WHERE identity_hash = ?')->execute([$identity]);
    $selectedCampaign = $_SESSION['selected_campaign'] ?? null;
    session_regenerate_id(true);
    $_SESSION = ['user_id' => (int)$user['user_id'], 'role_id' => (int)$user['role_id'], 'role_name' => $user['role_name'], 'first_name' => $user['first_name'], 'last_name' => $user['last_name'], 'selected_campaign' => $selectedCampaign];
    redirectTo(in_array($user['role_name'], ['Admin', 'Staff'], true) ? '../workspace.php' : '../dashboard.php');
} catch (InvalidArgumentException $e) { flash($e->getMessage()); }
catch (PDOException $e) {
    error_log($e->getMessage());
    flash($e->getCode() === '23000' ? 'That email is already registered. Please log in.' : 'Account service is unavailable. Please try again.');
} catch (Throwable $e) { error_log($e->getMessage()); flash('Account service is unavailable. Please try again.'); }
redirectTo($destination);
