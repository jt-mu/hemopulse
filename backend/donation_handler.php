<?php
require_once __DIR__ . '/../includes/api_common.php';
require_once __DIR__ . '/../includes/api_records.php';
try {
    $user=apiUser(['Admin','Staff']);
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') throw new ApiError('Use POST for donation intake.',405);
    if (!validCsrf()) throw new ApiError('Your form expired.',403);
    $id=recordDonation(getDBConnection(),$user,$_POST);
    jsonResponse(['status'=>'success','message'=>'Donation outcome saved.','donation_id'=>$id],201);
} catch(ApiError $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],$e->httpStatus);}
catch(Throwable $e){error_log($e->getMessage());jsonResponse(['status'=>'error','message'=>'Donation intake could not be saved.'],503);}
