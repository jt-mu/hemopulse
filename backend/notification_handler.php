<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/notifications.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
try {
    $user=currentUser();if(!$user){http_response_code(401);exit;}
    if(!validCsrf()){http_response_code(403);exit;}
    $id=filter_var($_POST['notification_id']??null,FILTER_VALIDATE_INT);
    if(!$id || $id<1){http_response_code(422);exit;}
    markNotificationRead(getDBConnection(),(int)$user['user_id'],(int)$id);
}catch(Throwable $e){error_log($e->getMessage());flash('Could not update the notification. Please try again.');}
$page=max(1,min(10000,(int)($_POST['page']??1)));
redirectTo('../dashboard.php?view=notifications&page='.$page);
