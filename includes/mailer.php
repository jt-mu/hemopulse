<?php
/** Returns Preview or Accepted; Accepted means the configured mail server accepted the message. */
function sendConfirmationEmail(string $email, string $token): string {
    $config = require __DIR__ . '/../config/mail.php';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($config['from'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid mail configuration.');
    $url = $config['app_url'];
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || strpbrk($url, "\r\n") !== false || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)) throw new RuntimeException('Invalid application URL.');
    $link = htmlspecialchars($url . '/newsletter_confirm.php?token=' . rawurlencode($token), ENT_QUOTES, 'UTF-8');
    $subject = 'Confirm your HemoPulse newsletter subscription';
    $body = '<!doctype html><html><body><h1>HemoPulse updates</h1><p>Thank you for subscribing to HemoPulse updates.<br>Click the confirmation button below to receive blood donation announcements, campaigns, and promotions.</p><p><a href="' . $link . '" style="background:#192a4d;color:#fff;padding:12px 20px;display:inline-block">Confirm subscription</a></p><p>This link expires in 48 hours. If you did not request updates, you can ignore this email.</p></body></html>';
    $headers = ['From: HemoPulse <' . $config['from'] . '>', 'MIME-Version: 1.0', 'Content-Type: text/html; charset=UTF-8'];
    if ($config['transport'] === 'preview') {
        $directory = $config['preview_dir'];
        if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Cannot create email preview directory.');
        $directory = realpath($directory);
        $webRoot = realpath(dirname(__DIR__));
        $normalize = static fn($path) => strtolower(str_replace('\\', '/', $path));
        if ($normalize($directory) === $normalize($webRoot) || str_starts_with($normalize($directory) . '/', $normalize($webRoot) . '/')) throw new RuntimeException('Mail previews must be outside the website directory.');
        $file = $directory . DIRECTORY_SEPARATOR . 'confirmation-' . bin2hex(random_bytes(12)) . '.eml';
        if (file_put_contents($file, 'To: ' . $email . "\r\nSubject: " . $subject . "\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body, LOCK_EX) === false) throw new RuntimeException('Cannot save email preview.');
        return 'Preview';
    }
    if ($config['transport'] !== 'mail' || $config['from'] === 'no-reply@hemopulse.local') throw new RuntimeException('Configure the newsletter sender and transport.');
    if (!@mail($email, $subject, $body, implode("\r\n", $headers))) throw new RuntimeException('The email transport rejected the confirmation message.');
    return 'Accepted';
}
function confirmedNewsletterRecipients(PDO $pdo): array {
    return $pdo->query("SELECT email FROM newsletter_subscriptions WHERE subscription_status = 'Subscribed' AND confirmed_at IS NOT NULL ORDER BY subscription_id")->fetchAll(PDO::FETCH_COLUMN);
}
