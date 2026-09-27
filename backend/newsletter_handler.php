<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/eligibility.php';
require_once __DIR__ . '/../includes/mailer.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
if (!validCsrf()) { flash('Your form expired. Please try again.'); redirectTo('../contact.php#get-in-touch'); }
try {
    $email = inputText($_POST, 'newsletter_email');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) throw new InvalidArgumentException('Enter a valid email address for the newsletter.');
    $pdo = getDBConnection(); $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM newsletter_subscriptions WHERE email = ? FOR UPDATE'); $stmt->execute([$email]); $existing = $stmt->fetch();
    if ($existing && $existing['subscription_status'] === 'Subscribed') {
        $pdo->commit(); flash('Your newsletter subscription is already confirmed.', 'success'); redirectTo('../contact.php#get-in-touch');
    }
    if ($existing && strtotime($existing['requested_at']) > time() - 60) throw new InvalidArgumentException('Please wait one minute before requesting another confirmation email.');
    $token = bin2hex(random_bytes(32)); $hash = hash('sha256', $token);
    $stmt = $pdo->prepare("INSERT INTO newsletter_subscriptions (email, token_hash, token_expires_at, requested_at) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), token_expires_at = VALUES(token_expires_at), requested_at = VALUES(requested_at), delivery_status = 'Pending'");
    $stmt->execute([$email, $hash, date('Y-m-d H:i:s', time() + 172800), date('Y-m-d H:i:s')]);
    $pdo->commit();
    try { $delivery = sendConfirmationEmail($email, $token); }
    catch (Throwable $e) {
        $stmt = $pdo->prepare("UPDATE newsletter_subscriptions SET delivery_status = 'Failed' WHERE token_hash = ?"); $stmt->execute([$hash]);
        throw $e;
    }
    $stmt = $pdo->prepare('UPDATE newsletter_subscriptions SET delivery_status = ? WHERE token_hash = ?'); $stmt->execute([$delivery, $hash]);
    flash($delivery === 'Preview' ? 'Subscription request saved. A confirmation email preview was created for local testing; no email was sent. You are not subscribed until you confirm.' : 'Please check your email and confirm your subscription before receiving updates.', 'success');
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack(); flash($e->getMessage());
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Newsletter confirmation failed: ' . $e->getMessage());
    flash('We could not send your confirmation email. You have not been subscribed. Please try again later.');
}
redirectTo('../contact.php#get-in-touch');
