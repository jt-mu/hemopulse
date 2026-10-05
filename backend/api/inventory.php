<?php
require_once __DIR__ . '/../../includes/api_common.php';
try {
    apiUser(['Admin','Staff']);
    if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') throw new ApiError('Use GET to view inventory.',405);
    require_once __DIR__.'/../../includes/api_workflows.php';
    expireInventory(getDBConnection());
    jsonResponse(['status'=>'success','data'=>getDBConnection()->query('SELECT inventory_id,blood_type,units_available,expiration_date,inventory_status FROM blood_inventory ORDER BY expiration_date')->fetchAll()]);
} catch(ApiError $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],$e->httpStatus);}
catch(Throwable $e){error_log($e->getMessage());jsonResponse(['status'=>'error','message'=>'Inventory is temporarily unavailable.'],503);}
