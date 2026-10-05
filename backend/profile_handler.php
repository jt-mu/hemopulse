<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/profile_upload.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
$saved = null; $return='../dashboard.php?view=profile';
try {
    $user = currentUser();
    if (!$user) { http_response_code(401); exit; }
    $return=in_array($user['role_name'],['Admin','Staff'],true)?'../workspace.php?view=account':'../dashboard.php?view=profile';
    if (!validCsrf()) { http_response_code(403); exit('Your form expired. Refresh and try again.'); }
    if(($_POST['action']??'')==='contact') {
        $contact=is_string($_POST['contact_number']??null)?trim($_POST['contact_number']):'';
        if(!validContact($contact))throw new InvalidArgumentException('Use an 11-digit mobile number starting with 09.');
        getDBConnection()->prepare('UPDATE users SET contact_number=? WHERE user_id=?')->execute([$contact,$user['user_id']]);
        auditEvent(getDBConnection(),(int)$user['user_id'],'Contact number updated','users',(int)$user['user_id']);
        flash('Contact number updated.','success');redirectTo($return);
    }
    $file = $_FILES['profile_picture'] ?? [];
    [$mime,$extension] = validateProfileUpload($file);
    if (!is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Upload a picture using the profile form.');
    $directory = profileStorage();
    if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Upload directory unavailable.');
    $name = bin2hex(random_bytes(24)).'.'.$extension;
    $saved = $directory.'/'.$name;
    if (!move_uploaded_file($file['tmp_name'],$saved)) throw new RuntimeException('Cannot save uploaded picture.');
    getDBConnection()->prepare('UPDATE users SET profile_image=? WHERE user_id=?')->execute([$name,$user['user_id']]);
    auditEvent(getDBConnection(),(int)$user['user_id'],'Profile picture updated','users',(int)$user['user_id']);
    $saved = null;
    // Remove only a previous generated image, never an arbitrary database path.
    if (preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$user['profile_image'] ?? '')) {
        $old = $directory.'/'.$user['profile_image']; if (is_file($old)) @unlink($old);
    }
    flash('Profile picture updated.','success');
} catch (InvalidArgumentException $e) { flash($e->getMessage()); }
catch (Throwable $e) { error_log($e->getMessage()); flash('Could not save your picture. Please try again.'); }
finally { if ($saved && is_file($saved)) unlink($saved); }
redirectTo($return);
