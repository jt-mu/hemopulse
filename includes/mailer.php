<?php
/** Returns Preview or Accepted; Accepted means the configured mail server accepted the message. */
function sendConfirmationEmail(string $email, string $token, string $unsubscribe): string {
    $config = require __DIR__ . '/../config/mail.php';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !filter_var($config['from'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid mail configuration.');
    $url = $config['app_url'];
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) || strpbrk($url, "\r\n") !== false || parse_url($url, PHP_URL_QUERY) || parse_url($url, PHP_URL_FRAGMENT)) throw new RuntimeException('Invalid application URL.');
    $link = htmlspecialchars($url . '/newsletter_confirm.php?token=' . rawurlencode($token), ENT_QUOTES, 'UTF-8');
    $subject = 'Confirm your HemoPulse newsletter subscription';
    $body = '<!doctype html><html><body><h1>HemoPulse updates</h1><p>Thank you for subscribing to HemoPulse updates.<br>Click the confirmation button below to receive blood donation announcements, campaigns, and promotions.</p><p><a href="' . $link . '" style="background:#192a4d;color:#fff;padding:12px 20px;display:inline-block">Confirm subscription</a></p><p>This link expires in 48 hours. If you did not request updates, you can ignore this email.</p></body></html>';
    $body=str_replace('</body>','<p><a href="'.htmlspecialchars($url.'/newsletter_unsubscribe.php?token='.rawurlencode($unsubscribe),ENT_QUOTES,'UTF-8').'">Unsubscribe</a></p></body>',$body);
    return sendEmail($email,$subject,$body);
}
function sendEmail(string $email,string $subject,string $body): string {
    $config=require __DIR__.'/../config/mail.php';
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!filter_var($config['from'],FILTER_VALIDATE_EMAIL)||strpbrk($subject,"\r\n")!==false)throw new RuntimeException('Invalid mail parameters.');
    $headers=['From: HemoPulse <'.$config['from'].'>','MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8'];
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
    if ($config['transport'] === 'smtp') {
        require_once __DIR__.'/../vendor/phpmailer/src/Exception.php';
        require_once __DIR__.'/../vendor/phpmailer/src/PHPMailer.php';
        require_once __DIR__.'/../vendor/phpmailer/src/SMTP.php';
        if (!$config['smtp_username'] || !$config['smtp_password'] || !filter_var($config['reply_to'],FILTER_VALIDATE_EMAIL)
            || $config['from']==='no-reply@hemopulse.local' || !in_array($config['smtp_security'],['tls','ssl'],true)
            || !preg_match('/^[a-zA-Z0-9.-]+$/D',$config['smtp_host']) || $config['smtp_port']<1 || $config['smtp_port']>65535) {
            error_log('HemoPulse SMTP: missing or invalid server configuration.');
            throw new RuntimeException('Email delivery is temporarily unavailable. Please try again later.');
        }
        try {
            $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP(); $mail->SMTPDebug=0; $mail->Timeout=15;
            $mail->Host=$config['smtp_host']; $mail->Port=$config['smtp_port'];
            $mail->SMTPAuth=true; $mail->Username=$config['smtp_username']; $mail->Password=$config['smtp_password'];
            $mail->SMTPSecure=$config['smtp_security'];
            $mail->CharSet='UTF-8'; $mail->setFrom($config['from'],'HemoPulse');
            $mail->addReplyTo($config['reply_to'],'HemoPulse team'); $mail->addAddress($email);
            $mail->isHTML(true); $mail->Subject=$subject; $mail->Body=$body;
            $mail->AltBody=html_entity_decode(strip_tags(str_replace(['</p>','<br>'],"\n",$body)),ENT_QUOTES,'UTF-8');
            $mail->send(); return 'Accepted';
        } catch (Throwable $e) {
            // Do not log server responses, message bodies, recipients, or credentials.
            error_log('HemoPulse SMTP delivery failed ('.get_class($e).'). Check server configuration and connectivity.');
            throw new RuntimeException('Email delivery is temporarily unavailable. Please try again later.');
        }
    }
    if ($config['transport'] !== 'mail' || $config['from'] === 'no-reply@hemopulse.local') throw new RuntimeException('Configure the newsletter sender and transport.');
    if (!@mail($email, $subject, $body, implode("\r\n", $headers))) throw new RuntimeException('The email transport rejected the confirmation message.');
    return 'Accepted';
}
function confirmedNewsletterRecipients(PDO $pdo): array {
    return $pdo->query("SELECT email FROM newsletter_subscriptions WHERE subscription_status = 'Subscribed' AND confirmed_at IS NOT NULL ORDER BY subscription_id")->fetchAll(PDO::FETCH_COLUMN);
}
