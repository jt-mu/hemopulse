<?php
require_once __DIR__.'/includes/contact_threads.php';
require_once __DIR__.'/includes/eligibility.php';
header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');header('X-Robots-Tag: noindex, nofollow');
$token=inputText($_POST,'token')?:inputText($_GET,'token');$inquiry=(int)($_POST['inquiry']??$_GET['inquiry']??0);$thread=null;$entries=[];$error=null;
$sessionAccess=$inquiry>0&&($_SESSION['contact_inquiries'][$inquiry]??0)>time()-30*86400;
try {
    $pdo=getDBConnection();
    if($sessionAccess){$own=$pdo->prepare('SELECT * FROM contact_messages WHERE message_id=?');$own->execute([$inquiry]);$thread=$own->fetch()?:null;}
    else $thread=contactThread($pdo,$token);
    if(!$thread){http_response_code(404);$error='This conversation link is invalid or expired. Submit a new inquiry through Contact Us.';}
    elseif(($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        if(!validCsrf()){http_response_code(403);throw new InvalidArgumentException('Your form expired. Refresh and try again.');}
        $message=inputText($_POST,'message');
        if(!$message||mb_strlen($message)>5000||inputText($_POST,'website')!==''){http_response_code(422);throw new InvalidArgumentException('Enter a message of 1–5,000 characters.');}
        throttle($pdo,'thread-source',$_SERVER['REMOTE_ADDR']??'local',10,3600,30);
        throttle($pdo,'thread-message',(string)$thread['message_id'],5,3600,30);
        $pdo->beginTransaction();
        $lock=$pdo->prepare('SELECT thread_hash,thread_expires_at FROM contact_messages WHERE message_id=? FOR UPDATE');$lock->execute([$thread['message_id']]);$current=$lock->fetch();
        if(!$current||(!$sessionAccess&&(!hash_equals((string)$current['thread_hash'],hash('sha256',$token))||$current['thread_expires_at']<=date('Y-m-d H:i:s')))){http_response_code(404);throw new InvalidArgumentException('This conversation link is invalid or expired. Use the newest link from the team.');}
        $pdo->prepare('INSERT INTO contact_followups(message_id,message_body) VALUES(?,?)')->execute([$thread['message_id'],$message]);
        auditEvent($pdo,null,'Inquiry follow-up received','contact_messages',(int)$thread['message_id']);
        $pdo->prepare("UPDATE contact_messages SET message_status='New' WHERE message_id=?")->execute([$thread['message_id']]);$pdo->commit();
        flash('Your reply has been received.','success');redirectTo('contact_thread.php?'.($sessionAccess?'inquiry='.$inquiry:'token='.rawurlencode($token)));
    }
    if($thread){
        $s=$pdo->prepare("SELECT reply_body AS body,replied_at AS posted_at,'Team' AS author,delivery_status AS delivery FROM contact_replies WHERE message_id=? UNION ALL SELECT message_body,received_at,'You','Received' FROM contact_followups WHERE message_id=? ORDER BY posted_at");$s->execute([$thread['message_id'],$thread['message_id']]);$entries=$s->fetchAll();
    }
}catch(InvalidArgumentException $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();}
catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());$error='Conversation is temporarily unavailable. Please try again later.';}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Your inquiry — HemoPulse</title><link rel="stylesheet" href="css/style.css"><link rel="stylesheet" href="css/app.css"><link rel="stylesheet" href="css/design.css"></head><body><main class="conversation-page"><a href="contact.php">Contact Us</a><h1>Your HemoPulse inquiry</h1><p>Keep this conversation private. Browser access lasts for this session, up to 30 days. Email links expire after 30 days; use the newest link from the team.</p><?php showFlash(); ?>
<?php if($error): ?><p class="app-notice error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if($thread): ?><h2><?= h($thread['subject']) ?></h2><article class="record-card"><strong>Your original message</strong><p><?= nl2br(h($thread['message_body'])) ?></p></article>
<?php foreach($entries as $entry): ?><article class="record-card"><strong><?= h($entry['author'].' · '.$entry['posted_at']) ?></strong><p><?= nl2br(h($entry['body'])) ?></p><?php if($entry['delivery']==='Preview'): ?><small>Email preview; reply is available here.</small><?php endif; ?></article><?php endforeach; ?>
<form method="post" class="conversation-form"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="inquiry" value="<?= $sessionAccess?$inquiry:0 ?>"><input type="hidden" name="token" value="<?= h($token) ?>"><div hidden><label>Leave empty<input name="website" tabindex="-1" autocomplete="off"></label></div><label for="followup">Reply to the team</label><p id="reply-help">1–5,000 characters. Up to five replies per hour; wait at least 30 seconds between replies.</p><textarea id="followup" name="message" required maxlength="5000" rows="5" aria-describedby="reply-help"><?= h(inputText($_POST,'message')) ?></textarea><button class="btn-navy" type="submit">Send reply</button></form><?php endif; ?></main><?php include __DIR__.'/includes/footer.php'; ?><script src="js/notices.js"></script></body></html>
