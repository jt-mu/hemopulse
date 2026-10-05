<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/security.php';
$email=getenv('HEMOPULSE_ADMIN_EMAIL')?:'';$password=getenv('HEMOPULSE_ADMIN_PASSWORD')?:'';
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100||!validPassword($password)){fwrite(STDERR,"Set HEMOPULSE_ADMIN_EMAIL and HEMOPULSE_ADMIN_PASSWORD (12–72 bytes) in this terminal, then run again. No account was created.\n");exit(1);}
$pdo=getDBConnection();$pdo->beginTransaction();
try{
    $role=$pdo->query("SELECT role_id FROM roles WHERE role_name='Admin' FOR UPDATE")->fetchColumn();
    if(!$role)throw new RuntimeException('Import the role data first.');
    $s=$pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id=? AND account_status='Active'");$s->execute([$role]);
    if($s->fetchColumn())throw new RuntimeException('An active administrator already exists. Use that account to manage users.');
    $pdo->prepare("INSERT INTO users (role_id,email,password_hash,first_name,last_name,account_status,public_reference) VALUES (?,?,?,'System','Administrator','Active',?)")->execute([$role,$email,password_hash($password,PASSWORD_DEFAULT),publicReference('USR')]);
    $pdo->commit();echo "Administrator created. Log in through the website to create staff accounts.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e instanceof PDOException?'Account could not be created; check for an existing email.':$e->getMessage());exit(1);}
