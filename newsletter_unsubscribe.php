<?php
require_once __DIR__.'/includes/bootstrap.php';
header('Referrer-Policy: no-referrer');
$token=is_string($_POST['token']??$_GET['token']??null)?($_POST['token']??$_GET['token']):'';
$message='This unsubscribe link is invalid.';$valid=false;
try {
    if(preg_match('/^[a-f0-9]{64}$/D',$token)) {
        $pdo=getDBConnection();$s=$pdo->prepare('SELECT subscription_id FROM newsletter_subscriptions WHERE unsubscribe_hash=?');$s->execute([hash('sha256',$token)]);$valid=(bool)$s->fetchColumn();
        if($valid) $message='Stop receiving HemoPulse newsletter updates?';
        if($valid&&($_SERVER['REQUEST_METHOD']??'')==='POST') {
            if(!validCsrf())$message='Your form expired. Refresh and try again.';
            else { $pdo->prepare("UPDATE newsletter_subscriptions SET subscription_status='Unsubscribed',token_hash=NULL,token_expires_at=NULL WHERE unsubscribe_hash=?")->execute([hash('sha256',$token)]);$message='You have been unsubscribed.';$valid=false; }
        }
    }
}catch(Throwable $e){error_log($e->getMessage());$message='Unsubscribe is temporarily unavailable. Please try again.';$valid=false;}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Unsubscribe — HemoPulse</title><link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>"><link rel="stylesheet" href="css/app.css?v=<?= filemtime(__DIR__ . '/css/app.css') ?>"><link rel="stylesheet" href="css/design.css?v=<?= filemtime(__DIR__.'/css/design.css') ?>"></head><body><main class="confirmation-page"><h1>HemoPulse Newsletter</h1><p><?= h($message) ?></p>
<?php if($valid): ?><form method="post"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="token" value="<?= h($token) ?>"><button type="submit" class="btn-navy">Unsubscribe</button></form><?php endif; ?><p><a href="index.php">Home</a></p></main><?php include __DIR__.'/includes/footer.php'; ?></body></html>
