<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/profile_upload.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
$saved = null;
try {
    $user = currentUser();
    if (!$user) { http_response_code(401); exit; }
    if (!validCsrf()) { http_response_code(403); exit('Your form expired. Refresh and try again.'); }
    $file = $_FILES['profile_picture'] ?? [];
    [$mime,$extension] = validateProfileUpload($file);
    if (!is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Upload a picture using the profile form.');
    $directory = profileStorage();
    if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Upload directory unavailable.');
    $name = bin2hex(random_bytes(24)).'.'.$extension;
    $saved = $directory.'/'.$name;
    if (!move_uploaded_file($file['tmp_name'],$saved)) throw new RuntimeException('Cannot save uploaded picture.');
    getDBConnection()->prepare('UPDATE users SET profile_image=? WHERE user_id=?')->execute([$name,$user['user_id']]);
    $saved = null;
    // Remove only a previous generated image, never an arbitrary database path.
    if (preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$user['profile_image'] ?? '')) {
        $old = $directory.'/'.$user['profile_image']; if (is_file($old)) @unlink($old);
    }
    flash('Profile picture updated.','success');
} catch (InvalidArgumentException $e) { flash($e->getMessage()); }
catch (Throwable $e) { error_log($e->getMessage()); flash('Could not save your picture. Please try again.'); }
finally { if ($saved && is_file($saved)) unlink($saved); }
redirectTo('../dashboard.php?view=profile');
