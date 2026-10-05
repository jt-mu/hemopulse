<?php
/** Both notification surfaces use the same ownership and pagination rules. */
function notificationPage(PDO $pdo,int $userId,int $page=1,int $limit=20): array {
    $limit=max(1,min(50,$limit));
    $s=$pdo->prepare('SELECT COUNT(*) AS total,COALESCE(SUM(is_read=0),0) AS unread FROM notifications WHERE user_id=?');
    $s->execute([$userId]);$totals=$s->fetch();$pages=max(1,(int)ceil($totals['total']/$limit));$page=max(1,min($page,$pages));
    $s=$pdo->prepare('SELECT notification_id,subject,message_body,is_read,sent_at FROM notifications WHERE user_id=? ORDER BY notification_id DESC LIMIT '.$limit.' OFFSET '.(($page-1)*$limit));
    $s->execute([$userId]);
    return ['status'=>'success','data'=>$s->fetchAll(),'meta'=>['total'=>(int)$totals['total'],'unread'=>(int)$totals['unread'],'page'=>$page,'pages'=>$pages,'limit'=>$limit]];
}
function markNotificationRead(PDO $pdo,int $userId,int $id): bool {
    $s=$pdo->prepare('SELECT notification_id FROM notifications WHERE notification_id=? AND user_id=?');$s->execute([$id,$userId]);
    if(!$s->fetchColumn())return false;
    $pdo->prepare('UPDATE notifications SET is_read=1 WHERE notification_id=? AND user_id=?')->execute([$id,$userId]);return true;
}
