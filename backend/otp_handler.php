<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/eligibility.php';
require_once __DIR__.'/../includes/mailer.php';
try {
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')jsonResponse(['status'=>'error','message'=>'Use POST.'],405);
 if(!validCsrf())jsonResponse(['status'=>'error','message'=>'Your form expired. Refresh and try again.'],403);
 $pdo=getDBConnection();$action=inputText($_POST,'action');
 if($action==='send_otp'){
  $email=strtolower(inputText($_POST,'email'));$first=inputText($_POST,'first_name');$last=inputText($_POST,'last_name');$middle=inputText($_POST,'middle_name');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100||!$first||!$last||mb_strlen($first)>50||mb_strlen($last)>50||mb_strlen($middle)>50)throw new InvalidArgumentException('Enter a valid email and names of up to 50 characters.');
  if(($_POST['terms']??'')!=='1')throw new InvalidArgumentException('Accept the terms before registering.');
  $password=is_string($_POST['password']??null)?$_POST['password']:'';validatePassword($password);
  throttle($pdo,'otp-source',$_SERVER['REMOTE_ADDR']??'local',15,3600);
  throttle($pdo,'otp-email',$email,5,3600,60);
  $s=$pdo->prepare('SELECT user_id FROM users WHERE email=?');$s->execute([$email]);
  if($s->fetchColumn())throw new InvalidArgumentException('Registration could not continue. Try logging in or contact support.');
  $code=(string)random_int(100000,999999);unset($_SESSION['pending_registration']);
  $delivery=sendEmail($email,'HemoPulse verification code','<p>Your verification code is <strong>'.h($code).'</strong>. It expires in 10 minutes.</p>');
  $_SESSION['pending_registration']=['email'=>$email,'first_name'=>$first,'last_name'=>$last,'middle_name'=>$middle,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'otp_hash'=>password_hash($code,PASSWORD_DEFAULT),'otp_expires'=>time()+600,'attempts'=>0];
  jsonResponse(['status'=>'success','message'=>$delivery==='Preview'?'A verification email preview was saved on the local server. No email was sent.':'Check your email for your verification code.']);
 }
 if($action!=='verify_otp')throw new InvalidArgumentException('Invalid account action.');
 $pending=$_SESSION['pending_registration']??null;
 if(!$pending||time()>=$pending['otp_expires']||$pending['attempts']>=5){unset($_SESSION['pending_registration']);throw new InvalidArgumentException('Code expired or retry limit reached. Request a new code.');}
 throttle($pdo,'otp-verify-source',$_SERVER['REMOTE_ADDR']??'local',30,900);
 throttle($pdo,'otp-verify-email',$pending['email'],10,900);
 $_SESSION['pending_registration']['attempts']++;$code=inputText($_POST,'otp');
 if(!preg_match('/^[0-9]{6}$/D',$code)||!password_verify($code,$pending['otp_hash']))throw new InvalidArgumentException('Invalid verification code.');
 $pdo->beginTransaction();$role=$pdo->query("SELECT role_id FROM roles WHERE role_name='Donor'")->fetchColumn();
 if(!$role)throw new RuntimeException('Donor role missing.');
 $pdo->prepare('INSERT INTO users(role_id,email,password_hash,first_name,last_name,middle_name,public_reference) VALUES(?,?,?,?,?,?,?)')->execute([$role,$pending['email'],$pending['password_hash'],$pending['first_name'],$pending['last_name'],$pending['middle_name']?:null,publicReference('USR')]);
 $id=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO audit_logs(user_id,action_performed,affected_table,target_record_id) VALUES(?,'Account created','users',?)")->execute([$id,$id]);$pdo->commit();
 establishSession($pending+['user_id'=>$id,'role_id'=>$role,'role_name'=>'Donor']);
 jsonResponse(['status'=>'success','message'=>'Registration complete.','redirect'=>'dashboard.php']);
}catch(InvalidArgumentException $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],422);}
catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log($e->getMessage());jsonResponse(['status'=>'error','message'=>'Registration could not be completed. Please try again or contact support.'],503);}
