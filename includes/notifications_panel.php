<?php
require_once __DIR__.'/notifications.php';
$notificationPage=max(1,min(10000,(int)($_GET['page']??1)));
$notificationRows=[];$notificationError=null;$notificationPages=1;$notificationTotal=0;$unread=0;
try {
    $db=getDBConnection();
    $result=notificationPage($db,(int)$user['user_id'],$notificationPage);
    $notificationRows=$result['data'];$notificationTotal=$result['meta']['total'];$unread=$result['meta']['unread'];
    $notificationPages=$result['meta']['pages'];$notificationPage=$result['meta']['page'];
}catch(Throwable $e){error_log($e->getMessage());$notificationError='Notifications are temporarily unavailable. Please try again later.';}
?>
<section class="notification-panel" aria-label="Notifications">
<?php if($notificationError): ?><p role="alert"><?= h($notificationError) ?></p>
<?php else: ?>
<div class="notification-toolbar"><div><h2>Your updates</h2><p>Updates about your screening, appointments and donations.</p></div><span class="notification-count" aria-live="polite"><strong data-unread-count><?= $unread ?></strong> unread <span>· <?= $notificationTotal ?> total</span></span></div>
<p id="notification-feedback" class="hide" aria-live="polite"></p>
<?php if(!$notificationRows): ?><div class="notification-empty"><span aria-hidden="true">✓</span><h3>You're all caught up</h3><p>Your donation journey updates will appear here.</p></div><?php endif; ?>
<div class="notification-list">
<?php foreach($notificationRows as $notice): ?>
<?php $noticeDate=new DateTimeImmutable($notice['sent_at']); ?>
<details class="notification-card <?= !$notice['is_read']?'is-unread':'' ?>"><summary><span class="notification-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg></span><span class="notification-summary"><span class="notification-subject"><?= h($notice['subject']) ?></span><time datetime="<?= h($noticeDate->format('Y-m-d\TH:i:sP')) ?>"><?= h($noticeDate->format('M j, Y · g:i A')) ?></time></span><span class="notification-badge"><?= !$notice['is_read']?'Unread':'Read' ?></span><svg class="notification-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary><div class="notification-body"><p><?= h($notice['message_body']) ?></p>
<?php if(!$notice['is_read']): ?><form class="notification-read-form" method="post" action="backend/notification_handler.php">
<input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="notification_id" value="<?= (int)$notice['notification_id'] ?>"><input type="hidden" name="page" value="<?= $notificationPage ?>"><button type="submit" class="btn-dash-yellow">Mark as read</button></form><?php endif; ?></div></details>
<?php endforeach; ?>
</div>
<nav class="donor-pagination" aria-label="Notification pages">
<?php if($notificationPage>1): ?><a href="dashboard.php?view=notifications&amp;page=<?= $notificationPage-1 ?>">Previous</a><?php else: ?><button disabled>Previous</button><?php endif; ?>
<span>Page <?= $notificationPage ?> of <?= $notificationPages ?></span>
<?php if($notificationPage<$notificationPages): ?><a href="dashboard.php?view=notifications&amp;page=<?= $notificationPage+1 ?>">Next</a><?php else: ?><button disabled>Next</button><?php endif; ?>
</nav><?php endif; ?></section>
