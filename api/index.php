<?php
require_once __DIR__ . '/../includes/api_common.php';
require_once __DIR__ . '/../includes/api_campaigns.php';
require_once __DIR__ . '/../includes/api_records.php';
require_once __DIR__ . '/../includes/api_reports.php';
require_once __DIR__ . '/../includes/booking.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';
$path=trim($_SERVER['PATH_INFO']??($_GET['route']??''),'/');
try {
    if (!preg_match('~^([a-z-]+)(?:/([1-9][0-9]*))?$~D',$path,$match)) throw new ApiError('API endpoint not found.',404);
    $resource=$match[1]; $id=isset($match[2])?(int)$match[2]:null; $pdo=getDBConnection();
    if ($method==='GET' && $resource==='session' && !$id) jsonResponse(['status'=>'success','data'=>['user'=>currentUser(),'csrf'=>csrfToken()]]);
    if ($method==='GET' && $resource==='campaigns' && !$id) jsonResponse(listCampaigns($pdo,($_GET['scope']??'')==='management'));
    if ($method==='GET' && $resource==='categories' && !$id) jsonResponse(['status'=>'success','data'=>$pdo->query('SELECT * FROM campaign_categories ORDER BY name')->fetchAll()]);
    $user=apiUser();
    if (!in_array($method,['GET','POST','PUT','DELETE'],true)) { header('Allow: GET, POST, PUT, DELETE'); throw new ApiError('Method not allowed.',405); }
    if ($method!=='GET') apiCsrf();
    $data=in_array($method,['POST','PUT'],true) || ($method==='DELETE' && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) ? apiBody() : [];
    switch($resource) {
        case 'campaigns':
            apiUser(['Admin']);
            if (($method==='POST'&&!$id)||($method==='PUT'&&$id)) { $saved=saveCampaign($pdo,$user,$data,$id); jsonResponse(['status'=>'success','message'=>'Campaign saved.','data'=>['campaign_id'=>$saved]],$id?200:201); }
            if ($method==='DELETE'&&$id) { deleteCampaign($pdo,$user,$id); jsonResponse(['status'=>'success','message'=>'Campaign deleted.']); }
            break;
        case 'categories':
            apiUser(['Admin']);
            if (($method==='POST'&&!$id)||($id&&in_array($method,['PUT','DELETE'],true))) { $saved=categoryMutation($pdo,$user,$method,$id,$data); jsonResponse(['status'=>'success','message'=>$method==='DELETE'?'Category deleted.':'Category saved.','data'=>['category_id'=>$saved]],$method==='POST'?201:200); } break;
        case 'users':
            apiUser(['Admin']);
            if ($method==='GET'&&!$id) jsonResponse(listUsers($pdo));
            if (($method==='POST'&&!$id)||($method==='PUT'&&$id)) { $saved=saveUser($pdo,$user,$data,$id); jsonResponse(['status'=>'success','message'=>'User saved.','data'=>['user_id'=>$saved]],$id?200:201); }
            if ($method==='DELETE'&&$id) { deleteUser($pdo,$user,$id); jsonResponse(['status'=>'success','message'=>'User deactivated; their historical records are retained.']); } break;
        case 'appointments':
            if ($method==='GET'&&!$id) jsonResponse(listAppointments($pdo,$user));
            if ($method==='POST'&&!$id) {
                apiUser(['Donor']); $token=bookAppointment($pdo,$user['user_id'],integer($data,'campaign_id'),field($data,'scheduled_time_slot',8),$data);
                jsonResponse(['status'=>'success','message'=>'Registration submitted for confirmation.','data'=>['token'=>$token]],201);
            }
            if ($id&&in_array($method,['PUT','DELETE'],true)) { updateAppointment($pdo,$user,$id,$data,$method==='DELETE'); jsonResponse(['status'=>'success','message'=>$method==='DELETE'?'Registration cancelled.':'Registration status updated.']); } break;
        case 'donations':
            apiUser(['Admin','Staff']);
            if ($method==='POST'&&!$id) { $saved=recordDonation($pdo,$user,$data); jsonResponse(['status'=>'success','message'=>'Donation outcome saved. Completed donations are reserved pending laboratory review.','data'=>['donation_id'=>$saved]],201); }
            break;
        case 'inventory':
            apiUser(['Admin','Staff']);
            if ($method==='GET'&&!$id) {
                $where=[];$params=[];$q=field($_GET,'q',100,false);
                if($q!==''){$where[]='blood_type LIKE ?';$params[]='%'.$q.'%';}
                if(inputText($_GET,'status')!==''){$where[]='inventory_status=?';$params[]=choice($_GET,'status',['Available','Reserved','Expired','Disposed']);}
                dateFilters('collection_date',$where,$params);
                jsonResponse(paginated($pdo,'inventory_id,blood_type,units_available,volume_ml_per_unit,collection_date,expiration_date,inventory_status','blood_inventory',$where,$params,['newest'=>'inventory_id DESC','date'=>'expiration_date ASC,inventory_id ASC'],'newest'));
            } break;
        case 'transactions':
            apiUser(['Admin','Staff']);
            if($method==='GET'&&!$id){$where=[];$params=[];dateFilters('t.transaction_timestamp',$where,$params);jsonResponse(paginated($pdo,'t.transaction_id,t.transaction_type,t.units_transacted,t.transaction_timestamp,t.transaction_notes,i.blood_type','inventory_transactions t LEFT JOIN blood_inventory i ON i.inventory_id=t.inventory_id',$where,$params,['newest'=>'t.transaction_id DESC','date'=>'t.transaction_timestamp ASC,t.transaction_id ASC'],'newest'));}break;
        case 'messages':
            apiUser(['Admin','Staff']);
            if($method==='GET'&&!$id){$where=[];$params=[];$q=field($_GET,'q',100,false);if($q!==''){$where[]='(subject LIKE ? OR sender_name LIKE ?)';array_push($params,'%'.$q.'%','%'.$q.'%');}if(inputText($_GET,'status')!==''){$where[]='message_status=?';$params[]=choice($_GET,'status',['New','Replied']);}dateFilters('received_at',$where,$params);jsonResponse(paginated($pdo,'*','contact_messages',$where,$params,['newest'=>'message_id DESC','date'=>'received_at ASC,message_id ASC'],'newest'));}
            if($method==='PUT'&&$id){$status=choice($data,'message_status',['New','Replied']);transaction($pdo,function()use($pdo,$user,$id,$status){$s=$pdo->prepare('SELECT message_id FROM contact_messages WHERE message_id=? FOR UPDATE');$s->execute([$id]);if(!$s->fetch())throw new ApiError('Message not found.',404);$pdo->prepare('UPDATE contact_messages SET message_status=? WHERE message_id=?')->execute([$status,$id]);auditAction($pdo,$user['user_id'],'Message '.$status,'contact_messages',$id);});jsonResponse(['status'=>'success','message'=>'Message status saved.']);}break;
        case 'reports':
            if($method==='GET'&&!$id){$report=reportData($pdo,$user);if(($_GET['format']??'')==='csv')exportReport($report);jsonResponse(['status'=>'success','data'=>$report]);}break;
    }
    throw new ApiError('Method or endpoint not supported.',405);
} catch(ApiError $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],$e->httpStatus);}
catch(InvalidArgumentException $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],422);}
catch(PDOException $e){error_log($e->getMessage());jsonResponse(['status'=>'error','message'=>$e->getCode()==='23000'?'This change conflicts with an existing or referenced record.':'The database service is temporarily unavailable.'], $e->getCode()==='23000'?409:503);}
catch(Throwable $e){error_log($e->getMessage());jsonResponse(['status'=>'error','message'=>'The service is temporarily unavailable. Please try again.'],503);}
