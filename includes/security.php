<?php
require_once __DIR__.'/audit.php';
/** Shared account validation and database-backed throttling. */
function validPassword(string $password): bool {
    return strlen($password) >= 12 && strlen($password) <= 72
        && preg_match('/[A-Za-z]/', $password) && preg_match('/[^A-Za-z]/', $password)
        && !preg_match('/(.)\1{5}/u', $password)
        && !in_array(strtolower($password), ['password1234', 'password123!', '123456789012'], true);
}
function validatePassword(string $password): void {
    if (!validPassword($password)) throw new InvalidArgumentException('Use 12–72 bytes, including a letter and a number or symbol; avoid common or repeated passwords.');
}
function validContact(string $contact): bool { return (bool)preg_match('/^09[0-9]{9}$/D', $contact); }
function publicReference(string $prefix): string { return $prefix . '-' . strtoupper(bin2hex(random_bytes(12))); }
/** Atomic counters survive cookie resets and concurrent requests. */
function throttle(PDO $pdo, string $scope, string $identity, int $limit, int $seconds, int $cooldown = 0): void {
    $key = hash('sha256', $scope . '|' . strtolower($identity));
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT IGNORE INTO request_limits (limit_key,window_started,attempts,last_attempt) VALUES (?,0,0,0)')->execute([$key]);
        $s=$pdo->prepare('SELECT * FROM request_limits WHERE limit_key=? FOR UPDATE'); $s->execute([$key]); $row=$s->fetch(); $now=time();
        $count=$row['window_started'] <= $now-$seconds ? 0 : (int)$row['attempts'];
        if ($count >= $limit || ($cooldown && $row['last_attempt'] > $now-$cooldown)) throw new InvalidArgumentException('Too many requests. Please wait before trying again.');
        $pdo->prepare('UPDATE request_limits SET window_started=?,attempts=?,last_attempt=? WHERE limit_key=?')->execute([$count ? $row['window_started'] : $now,$count+1,$now,$key]);
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
}
function establishSession(array $user): void {
    $campaign=$_SESSION['selected_campaign']??null;
    session_regenerate_id(true);
    $_SESSION=['user_id'=>(int)$user['user_id'],'role_id'=>(int)$user['role_id'],'role_name'=>$user['role_name'],'first_name'=>$user['first_name'],'last_name'=>$user['last_name'],'selected_campaign'=>$campaign,'last_activity'=>time()];
}
function authenticate(PDO $pdo, string $email, string $password): array {
    throttle($pdo,'login-source',$_SERVER['REMOTE_ADDR']??'local',50,900);
    throttle($pdo,'login-account',$email,5,900);
    $s=$pdo->prepare('SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE email=?'); $s->execute([$email]); $user=$s->fetch();
    $hash=$user['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if (!password_verify($password,$hash) || !$user || $user['account_status']!=='Active') throw new InvalidArgumentException('Invalid email or password, or account unavailable.');
    $pdo->prepare('DELETE FROM request_limits WHERE limit_key=?')->execute([hash('sha256','login-account|'.strtolower($email))]);
    auditEvent($pdo,(int)$user['user_id'],'Account signed in','users',(int)$user['user_id']);
    establishSession($user); return $user;
}
