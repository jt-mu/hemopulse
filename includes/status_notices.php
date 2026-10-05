<?php
require_once __DIR__.'/mailer.php';
/** Queue in the account transaction; deliver after commit without holding locks. */
function queueStatusNotice(PDO $pdo,int $userId,int $actor,?string $old,string $status,string $reason,string $email): int {
    $pdo->prepare('INSERT INTO account_status_notices(user_id,changed_by,old_status,new_status,reason,recipient_email) VALUES(?,?,?,?,?,?)')->execute([$userId,$actor,$old,$status,$reason,$email]);
    return (int)$pdo->lastInsertId();
}
function deliverStatusNotice(PDO $pdo,int $id): void {
    $claim=$pdo->prepare("UPDATE account_status_notices SET delivery_status='Sending' WHERE notice_id=? AND delivery_status='Pending'");$claim->execute([$id]);
    if(!$claim->rowCount())return;
    $s=$pdo->prepare('SELECT * FROM account_status_notices WHERE notice_id=?');$s->execute([$id]);$notice=$s->fetch();
    $body='<h1>HemoPulse account update</h1><p>Your account status is now <strong>'.h($notice['new_status']).'</strong>.</p><p>Reason: '.h($notice['reason']).'</p><p>'.($notice['new_status']==='Active'?'You can sign in again.':'You cannot sign in while this status applies. Contact the team through the website to request a review.').'</p>';
    try {$delivery=sendEmail($notice['recipient_email'],'HemoPulse account status update',$body);}
    catch(Throwable $e){$delivery='Unknown';error_log('Account status notification delivery could not be confirmed.');}
    $pdo->prepare('UPDATE account_status_notices SET delivery_status=? WHERE notice_id=?')->execute([$delivery,$id]);
}
