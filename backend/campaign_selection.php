<?php
require_once __DIR__.'/../includes/bootstrap.php';
require_once __DIR__.'/../includes/eligibility.php';
require_once __DIR__.'/../includes/campaigns.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);exit;}
try {
    $user=currentUser();
    if(!$user || $user['role_name']!=='Donor'){http_response_code(403);exit;}
    if(!validCsrf()){http_response_code(403);exit;}
    $pdo=getDBConnection();$screen=latestEligibility($pdo,(int)$user['user_id']);
    $next=donorNextDate($pdo,(int)$user['user_id']);
    if(!canRegister($screen)||($next&&$next>date('Y-m-d')))throw new InvalidArgumentException('Complete a current eligible screening before selecting a campaign for registration.');
    $id=filter_var($_POST['campaign_id']??null,FILTER_VALIDATE_INT);
    $s=$pdo->prepare('SELECT * FROM campaigns WHERE campaign_id=?');$s->execute([$id]);$campaign=$s->fetch();
    if(!$campaign || campaignRegistrationStatus($campaign)!=='OPEN')throw new InvalidArgumentException('Choose an open campaign.');
    $pdo->prepare('INSERT INTO donor_campaign_selection(user_id,eligibility_id,campaign_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE eligibility_id=VALUES(eligibility_id),campaign_id=VALUES(campaign_id)')->execute([$user['user_id'],$screen['eligibility_id'],$id]);
    auditEvent($pdo,(int)$user['user_id'],'Campaign selected','campaigns',(int)$id);
    $_SESSION['selected_campaign']=$id;
} catch(InvalidArgumentException $e){flash($e->getMessage());}
catch(Throwable $e){error_log($e->getMessage());flash('Campaign selection is temporarily unavailable.');}
redirectTo('../dashboard.php?view=eligibility&tab=registration');
