<?php
require_once __DIR__.'/includes/bootstrap.php';
require_once __DIR__.'/includes/profile_upload.php';
try {
    $user = currentUser();
    if (!$user) { http_response_code(401); exit; }
    $name = $user['profile_image'] ?? '';
    if (!preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$name) || !is_file(profileStorage().'/'.$name)) { http_response_code(404); exit; }
    $type = ['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][pathinfo($name,PATHINFO_EXTENSION)];
    header('Content-Type: '.$type); header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="profile.'.pathinfo($name,PATHINFO_EXTENSION).'"');
    readfile(profileStorage().'/'.$name);
} catch(Throwable $e) { error_log($e->getMessage()); http_response_code(503); }
