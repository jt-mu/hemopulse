<?php
require_once __DIR__ . '/includes/bootstrap.php';
header('Referrer-Policy: no-referrer');
$token = is_string($_POST['token'] ?? $_GET['token'] ?? null) ? ($_POST['token'] ?? $_GET['token']) : '';
$valid = false; $confirmed = false; $message = 'This confirmation link is invalid or has expired. Please request another from the Contact page.';
try {
    if (preg_match('/^[a-f0-9]{64}$/D', $token)) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT subscription_id FROM newsletter_subscriptions WHERE token_hash = ? AND subscription_status = 'Pending' AND token_expires_at > ?");
        $stmt->execute([hash('sha256', $token), date('Y-m-d H:i:s')]); $valid = (bool)$stmt->fetchColumn();
        if ($valid && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!validCsrf()) $message = 'Your form expired. Refresh this page to confirm.';
            else {
                $stmt = $pdo->prepare("UPDATE newsletter_subscriptions SET subscription_status = 'Subscribed', confirmed_at = ?, token_hash = NULL, token_expires_at = NULL WHERE token_hash = ? AND subscription_status = 'Pending' AND token_expires_at > ?");
                $now = date('Y-m-d H:i:s'); $stmt->execute([$now, hash('sha256', $token), $now]);
                $confirmed = $stmt->rowCount() === 1; $valid = false;
                if ($confirmed) $message = 'Your subscription is confirmed. You can now receive HemoPulse updates.';
            }
        } elseif ($valid) $message = 'Confirm that you would like to receive HemoPulse blood donation announcements, campaigns, and promotions.';
    }
} catch (Throwable $e) { error_log($e->getMessage()); $message = 'Confirmation is temporarily unavailable. Please try this link again later.'; $valid = false; }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Confirm subscription — HemoPulse</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/app.css"><link rel="stylesheet" href="css/design.css?v=3"></head><body>
<main class="confirmation-page"><h1>HemoPulse Newsletter</h1><p role="status"><?= h($message) ?></p>
<?php if ($valid): ?><form method="post"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="token" value="<?= h($token) ?>"><button class="btn-yellow-submit" type="submit">Confirm Subscription</button></form><?php endif; ?>
<p><a href="contact.php#newsletter">Back to Contact Us</a></p></main></body></html>
