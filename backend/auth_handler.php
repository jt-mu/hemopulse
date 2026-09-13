<?php
// backend/auth_handler.php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        header('Location: ../login.php?error=empty_fields');
        exit();
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.role_id, u.password_hash, u.first_name, u.last_name, u.account_status, r.role_name
        FROM users u
        INNER JOIN roles r ON u.role_id = r.role_id
        WHERE u.email = :email
        LIMIT 1
    ");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if ($user['account_status'] !== 'Active') {
            header('Location: ../login.php?error=account_suspended');
            exit();
        }

        // Prevent Session Fixation
        session_regenerate_id(true);

        $_SESSION['user_id']    = (int)$user['user_id'];
        $_SESSION['role_id']    = (int)$user['role_id'];
        $_SESSION['role_name']  = $user['role_name'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'];

        switch ($user['role_name']) {
            case 'Admin':
                header('Location: ../admin/dashboard.php');
                break;
            case 'Staff':
                header('Location: ../staff/dashboard.php');
                break;
            case 'Donor':
                header('Location: ../donor/dashboard.php');
                break;
            case 'Recipient':
                header('Location: ../recipient/dashboard.php');
                break;
            default:
                header('Location: ../index.php');
        }
        exit();
    } else {
        header('Location: ../login.php?error=invalid_credentials');
        exit();
    }
}