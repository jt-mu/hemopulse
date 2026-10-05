<?php
require_once __DIR__.'/../includes/bootstrap.php';
try {
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')jsonResponse(['status'=>'error','message'=>'Use POST.'],405);
 if(!validCsrf())jsonResponse(['status'=>'error','message'=>'Your form expired. Refresh and try again.'],403);
 $email=is_string($_POST['email']??null)?trim($_POST['email']):'';
 $password=is_string($_POST['password']??null)?$_POST['password']:'';
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100||$password==='')throw new InvalidArgumentException('Enter your email and password.');
 $user=authenticate(getDBConnection(),$email,$password);
 jsonResponse(['status'=>'success','message'=>'Login successful.','redirect'=>in_array($user['role_name'],['Admin','Staff'],true)?'workspace.php':'dashboard.php']);
}catch(InvalidArgumentException $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],422);}
catch(Throwable $e){error_log($e->getMessage());jsonResponse(['status'=>'error','message'=>'Account service is unavailable. Please try again.'],503);}
