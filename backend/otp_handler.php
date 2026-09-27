<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');

// Load database connection
require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? '';

// 1. GENERATE AND DISPATCH OTP
if ($action === 'send_otp') {
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email) {
        echo json_encode(['status' => 'error', 'message' => 'A valid email address is required.']);
        exit();
    }

    if (empty($firstName) || empty($lastName)) {
        echo json_encode(['status' => 'error', 'message' => 'First and last name are required.']);
        exit();
    }

    if (strlen($password) < 12) {
        echo json_encode(['status' => 'error', 'message' => 'Password must be at least 12 characters.']);
        exit();
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'An account with this email already exists.']);
            exit();
        }
    } catch (Throwable $e) {
        error_log('Database check error: ' . $e->getMessage());
    }

    // Generate random 6-digit OTP code
    $otpCode = (string) random_int(100000, 999999);
    $expiresAt = time() + (10 * 60);

    $_SESSION['pending_registration'] = [
        'email' => $email,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'middle_name' => $middleName,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'otp_code' => $otpCode,
        'otp_expires' => $expiresAt,
        'attempts' => 0
    ];

    $mailSent = false;
    $mailerFile = __DIR__ . '/../includes/mailer.php';

    if (file_exists($mailerFile)) {
        try {
            require_once $mailerFile;
            if (function_exists('sendMail')) {
                $subject = "HemoPulse - Your 6-Digit Verification Code: $otpCode";
                $body = "Hello $firstName,\n\nYour 6-digit verification code is:\n\n$otpCode\n\nExpires in 10 minutes.\n\nRegards,\nHemoPulse Team";
                $mailSent = (bool) sendMail($email, $subject, $body);
            }
        } catch (Throwable $e) {
            error_log('Mailer error: ' . $e->getMessage());
        }
    }

    if (!$mailSent) {
        $headers = "From: noreply@hemopulse.local\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($email, "HemoPulse Verification Code: $otpCode", "Your 6-digit code is: $otpCode", $headers);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Verification code dispatched to your email.',
        'demo_otp' => $otpCode
    ]);
    exit();
}

// 2. VERIFY OTP & INSERT INTO hemopulse_db.users
if ($action === 'verify_otp') {
    $enteredCode = trim($_POST['otp'] ?? '');
    $pending = $_SESSION['pending_registration'] ?? null;

    if (!$pending) {
        echo json_encode(['status' => 'error', 'message' => 'Registration session expired. Please register again.']);
        exit();
    }

    if (time() > $pending['otp_expires']) {
        unset($_SESSION['pending_registration']);
        echo json_encode(['status' => 'error', 'message' => 'Verification code expired. Please request a new code.']);
        exit();
    }

    if ($pending['attempts'] >= 5) {
        unset($_SESSION['pending_registration']);
        echo json_encode(['status' => 'error', 'message' => 'Too many failed attempts. Please register again.']);
        exit();
    }

    if ($enteredCode !== $pending['otp_code']) {
        $_SESSION['pending_registration']['attempts']++;
        echo json_encode(['status' => 'error', 'message' => 'Invalid 6-digit code. Please try again.']);
        exit();
    }

    try {
        $pdo = getDBConnection();

        // Determine Donor role_id from roles table
        $roleId = 3;
        try {
            $roleStmt = $pdo->prepare("SELECT role_id FROM roles WHERE LOWER(role_name) LIKE '%donor%' LIMIT 1");
            $roleStmt->execute();
            $row = $roleStmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['role_id'])) {
                $roleId = (int)$row['role_id'];
            }
        } catch (Throwable $t) {
            $roleId = 3;
        }

        // Insert into hemopulse_db.users
        $sql = "INSERT INTO users (role_id, email, password_hash, first_name, last_name, middle_name, account_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())";
        
        $insertStmt = $pdo->prepare($sql);
        $insertStmt->execute([
            $roleId,
            $pending['email'],
            $pending['password_hash'],
            $pending['first_name'],
            $pending['last_name'],
            $pending['middle_name'] ?: null
        ]);

        $newUserId = (int) $pdo->lastInsertId();

        // Also create an empty donor profile if the table exists
        try {
            $pdo->prepare("INSERT IGNORE INTO donor_profiles (user_id, created_at) VALUES (?, NOW())")
                ->execute([$newUserId]);
        } catch (Throwable $t) {
            // Non-critical
        }

        // Establish session variables
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['role_id'] = $roleId;
        $_SESSION['email'] = $pending['email'];
        $_SESSION['first_name'] = $pending['first_name'];
        $_SESSION['last_name'] = $pending['last_name'];
        $_SESSION['role'] = 'Donor';

        unset($_SESSION['pending_registration']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Registration complete.',
            'redirect' => 'dashboard.php?view=donations'
        ]);
        exit();

    } catch (PDOException $e) {
        error_log('Database insert failure: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error while saving account: ' . $e->getMessage()
        ]);
        exit();
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
exit();