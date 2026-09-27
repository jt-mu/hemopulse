<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || empty($password)) {
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit();
}

try {
    $pdo = getDBConnection();

    // Query user record by email
    $stmt = $pdo->prepare('SELECT user_id, role_id, email, password_hash, first_name, last_name, account_status FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
        exit();
    }

    // Verify account status
    if (isset($user['account_status']) && strtolower($user['account_status']) !== 'active') {
        echo json_encode(['status' => 'error', 'message' => 'Your account is currently inactive or suspended.']);
        exit();
    }

    // Verify password against stored bcrypt hash
    if (!password_verify($password, $user['password_hash'])) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
        exit();
    }

    // Determine role name from roles table
    $roleName = 'Donor';
    if (!empty($user['role_id'])) {
        $roleStmt = $pdo->prepare('SELECT role_name FROM roles WHERE role_id = ? LIMIT 1');
        $roleStmt->execute([$user['role_id']]);
        $roleRow = $roleStmt->fetch();
        if ($roleRow && !empty($roleRow['role_name'])) {
            $roleName = $roleRow['role_name'];
        }
    }

    // Establish authenticated PHP session
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['role_id'] = (int) ($user['role_id'] ?? 3);
    $_SESSION['email'] = $user['email'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name'] = $user['last_name'];
    $_SESSION['role'] = $roleName;

    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful.',
        'redirect' => 'dashboard.php?view=donations'
    ]);
    exit();

} catch (Throwable $e) {
    error_log('Login handler error: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'A server error occurred during login.']);
    exit();
}