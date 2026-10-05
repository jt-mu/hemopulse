<?php
require_once __DIR__.'/mailer.php';
function reviewScreening(PDO $pdo,array $user,int $id,array $data): void {
    $outcome=choice($data,'outcome',['Eligible','Temporarily Deferred','Reviewed','Not Eligible']);
    $notes=field($data,'review_notes',2000);$until=inputText($data,'deferred_until');
    if($outcome==='Temporarily Deferred'&&(!validDate($until)||$until<=date('Y-m-d')))throw new ApiError('Enter a future review date.');
    transaction($pdo,function()use($pdo,$user,$id,$outcome,$notes,$until){
        $s=$pdo->prepare('SELECT user_id FROM eligibility_checks WHERE eligibility_id=?');$s->execute([$id]);$donor=$s->fetchColumn();if(!$donor)throw new ApiError('Screening not found.',404);
        $s=$pdo->prepare('SELECT user_id FROM users WHERE user_id=? FOR UPDATE');$s->execute([$donor]);
        $check=latestEligibility($pdo,(int)$donor);
        if((int)$check['eligibility_id']!==$id)throw new ApiError('A newer screening exists. Review the latest result.',409);
        if($outcome==='Eligible') {
            $next=donorNextDate($pdo,(int)$donor);
            if(($next&&$next>date('Y-m-d'))||($check['deferred_until']??'')>date('Y-m-d'))throw new ApiError('The recorded donation interval or deferral has not ended.',409);
        }
        $pdo->prepare('UPDATE eligibility_checks SET outcome=?,review_notes=?,deferred_until=?,retry_after=?,reviewed_by=?,reviewed_at=NOW() WHERE eligibility_id=?')->execute([$outcome,$notes,$outcome==='Temporarily Deferred'?$until:null,$outcome==='Not Eligible'?afterMonths(date('Y-m-d'),1):null,$user['user_id'],$id]);
        auditAction($pdo,$user['user_id'],'Eligibility decision changed','eligibility_checks',$id,['from'=>$check['outcome'],'to'=>$outcome,'deferred_until'=>$until?:null]);
        $pdo->prepare("INSERT INTO notifications(user_id,notification_type,subject,message_body) VALUES(?,'Eligibility','Screening reviewed',?)")->execute([$donor,'Your screening status is '.$outcome.'. '.$notes]);
    });
}
function expireInventory(PDO $pdo): void {
    transaction($pdo,function()use($pdo){
        $rows=$pdo->query("SELECT inventory_id,units_available FROM blood_inventory WHERE inventory_status IN ('Pending','Available','Reserved') AND expiration_date<=CURDATE() FOR UPDATE")->fetchAll();
        foreach($rows as $row) {
            $pdo->prepare("UPDATE blood_inventory SET inventory_status='Expired' WHERE inventory_id=?")->execute([$row['inventory_id']]);
            $pdo->prepare("INSERT INTO inventory_transactions(inventory_id,transaction_type,units_transacted,transaction_notes,public_reference) VALUES(?,'Expiration',?,'Automatically expired at the start of the expiry date',?)")->execute([$row['inventory_id'],$row['units_available'],publicReference('TXN')]);
        }
    });
}
function updateInventory(PDO $pdo,array $user,int $id,array $data): void {
    $status=choice($data,'inventory_status',['Available','Reserved','Used','Discarded']);$notes=field($data,'notes',1000);
    expireInventory($pdo);
    transaction($pdo,function()use($pdo,$user,$id,$status,$notes){
        $s=$pdo->prepare('SELECT * FROM blood_inventory WHERE inventory_id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetch();
        if(!$old)throw new ApiError('Unit not found.',404);
        $allowed=['Pending'=>['Available','Discarded'],'Available'=>['Reserved','Used','Discarded'],'Reserved'=>['Available','Used','Discarded'],'Expired'=>['Discarded']];
        if(!in_array($status,$allowed[$old['inventory_status']]??[],true))throw new ApiError('This inventory transition is not allowed.',409);
        if(in_array($status,['Available','Reserved','Used'],true)&&$old['expiration_date']<=date('Y-m-d'))throw new ApiError('Expired units cannot be released.',409);
        $pdo->prepare('UPDATE blood_inventory SET inventory_status=? WHERE inventory_id=?')->execute([$status,$id]);
        $type=$status==='Used'?'Deduction':($status==='Discarded'?'Disposal':'Adjustment');
        $pdo->prepare('INSERT INTO inventory_transactions(inventory_id,executed_by_staff_id,transaction_type,units_transacted,transaction_notes,public_reference) VALUES(?,?,?,?,?,?)')->execute([$id,$user['user_id'],$type,$old['units_available'],$old['inventory_status'].' → '.$status.': '.$notes,publicReference('TXN')]);
        auditAction($pdo,$user['user_id'],'Inventory '.$status,'blood_inventory',$id,['from'=>$old['inventory_status'],'to'=>$status]);
    });
}
/** Persist the attempt before SMTP; retries never resend an uncertain delivery. */
function updateContact(PDO $pdo,array $user,int $id,array $data): string {
    $status=choice($data,'message_status',['Read','Replied','Closed']);$reply=field($data,'reply_body',5000,false);
    $key=hash('sha256',$id.':'.$user['user_id'].':'.(inputText($data,'request_key')?:$reply));
    $attempt=transaction($pdo,function()use($pdo,$user,$id,$status,$reply,$key){
        $s=$pdo->prepare('SELECT * FROM contact_messages WHERE message_id=? FOR UPDATE');$s->execute([$id]);$message=$s->fetch();if(!$message)throw new ApiError('Message not found.',404);
        if($status!=='Replied') {
            $pdo->prepare('UPDATE contact_messages SET message_status=? WHERE message_id=?')->execute([$status,$id]);
            auditAction($pdo,$user['user_id'],'Contact '.$status,'contact_messages',$id);
            return ['notice'=>'Message status saved.'];
        }
        if(!$reply)throw new ApiError('Write a reply before sending.');
        $s=$pdo->prepare('SELECT reply_id,reply_body,delivery_status FROM contact_replies WHERE request_key=?');$s->execute([$key]);$existing=$s->fetch();
        if($existing) {
            if($existing['reply_body']!==$reply)throw new ApiError('This reply attempt already exists. Reopen the editor to compose a new reply.',409);
            return ['notice'=>'Existing reply attempt: '.$existing['delivery_status'].'. No duplicate email was sent.'];
        }
        $pdo->prepare("INSERT INTO contact_replies(message_id,replied_by,reply_body,delivery_status,replied_at,request_key) VALUES(?,?,?,'Sending',NOW(),?)")->execute([$id,$user['user_id'],$reply,$key]);
        auditAction($pdo,$user['user_id'],'Contact reply prepared','contact_messages',$id);
        $replyId=(int)$pdo->lastInsertId();$token=bin2hex(random_bytes(32));
        $pdo->prepare('UPDATE contact_messages SET thread_hash=?,thread_expires_at=DATE_ADD(NOW(),INTERVAL 30 DAY) WHERE message_id=?')->execute([hash('sha256',$token),$id]);
        return ['reply_id'=>$replyId,'email'=>$message['email'],'thread_token'=>$token];
    });
    if(isset($attempt['notice']))return $attempt['notice'];
    $mailConfig=require __DIR__.'/../config/mail.php';
    $link=$mailConfig['app_url'].'/contact_thread.php?token='.rawurlencode($attempt['thread_token']);
    try {$delivery=sendEmail($attempt['email'],'HemoPulse inquiry response',nl2br(h($reply)).'<p><a href="'.h($link).'">View this inquiry and reply to the team</a></p>');}
    catch(Throwable $error) {
        $pdo->prepare("UPDATE contact_replies SET delivery_status='Unknown' WHERE reply_id=?")->execute([$attempt['reply_id']]);
        throw new ApiError('Delivery could not be confirmed. The reply attempt is saved; inspect mail logs before composing another reply.',503);
    }
    transaction($pdo,function()use($pdo,$user,$id,$attempt,$delivery){
        $pdo->prepare('UPDATE contact_replies SET delivery_status=? WHERE reply_id=?')->execute([$delivery,$attempt['reply_id']]);
        $pdo->prepare('UPDATE contact_messages SET message_status=? WHERE message_id=?')->execute([$delivery==='Preview'?'Read':'Replied',$id]);
        auditAction($pdo,$user['user_id'],'Contact reply '.$delivery,'contact_messages',$id);
    });
    return $delivery==='Preview'?'Reply preview saved on the server; no email was sent. Configure mail delivery to send a reply.':'Reply accepted by the email server.';
}
