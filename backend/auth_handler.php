<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$action = $_POST['action'] ?? '';
$destination = '../index.php#' . ($action === 'register' ? 'register' : 'login');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
if (!validCsrf()) { flash('Your form expired. Please try again.'); redirectTo($destination); }
if ($action === 'logout') {
    $actor=currentUser();if($actor)auditEvent(getDBConnection(),(int)$actor['user_id'],'Account signed out','users',(int)$actor['user_id']);
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
    if ($action === 'register') { flash('Use the registration form to verify your email.'); redirectTo('../index.php#register'); }
    $user=authenticate($pdo,$email,$password);
    redirectTo(in_array($user['role_name'], ['Admin', 'Staff'], true) ? '../workspace.php' : '../dashboard.php');
} catch (InvalidArgumentException $e) { flash($e->getMessage()); }
catch (PDOException $e) {
    error_log($e->getMessage());
    flash($e->getCode() === '23000' ? 'That email is already registered. Please log in.' : 'Account service is unavailable. Please try again.');
} catch (Throwable $e) { error_log($e->getMessage()); flash('Account service is unavailable. Please try again.'); }
redirectTo($destination);
