<?php
require_once __DIR__.'/../includes/notifications.php';
require_once __DIR__ . '/../includes/api_common.php';
require_once __DIR__ . '/../includes/api_campaigns.php';
require_once __DIR__ . '/../includes/api_records.php';
require_once __DIR__ . '/../includes/api_reports.php';
require_once __DIR__ . '/../includes/booking.php';
require_once __DIR__ . '/../includes/api_workflows.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';
$route=$_SERVER['PATH_INFO']??($_GET['route']??'');
$path=is_string($route)?trim($route,'/'):'';
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
        case 'audit-logs':
            apiUser(['Admin','Staff']);
            if($method==='GET'&&!$id){$where=[];$params=[];$q=field($_GET,'q',100,false);if($q!==''){$where[]="(l.action_performed LIKE ? OR l.affected_table LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";$params=array_fill(0,3,'%'.$q.'%');}dateFilters('l.log_timestamp',$where,$params);if(inputText($_GET,'actor')!==''){$where[]="CONCAT(u.first_name,' ',u.last_name) LIKE ?";$params[]='%'.field($_GET,'actor',100).'%';}if(inputText($_GET,'action')!==''){$where[]='l.action_performed=?';$params[]=field($_GET,'action',255);}jsonResponse(paginated($pdo,"l.log_id,l.action_performed,l.affected_table,l.target_record_id,l.details_json,l.log_timestamp,CONCAT(u.first_name,' ',u.last_name) AS actor",'audit_logs l LEFT JOIN users u ON u.user_id=l.user_id',$where,$params,['newest'=>'l.log_id DESC','date'=>'l.log_timestamp ASC,l.log_id ASC'],'newest'));}break;
        case 'message-replies':
            apiUser(['Admin','Staff']);
            if($method==='GET'&&$id){$follow=$pdo->prepare('SELECT message_body,received_at FROM contact_followups WHERE message_id=? ORDER BY followup_id');$follow->execute([$id]);$followups=$follow->fetchAll();$s=$pdo->prepare("SELECT r.reply_body,r.delivery_status,r.replied_at,CONCAT(u.first_name,' ',u.last_name) AS author FROM contact_replies r JOIN users u ON u.user_id=r.replied_by WHERE r.message_id=? ORDER BY r.reply_id");$s->execute([$id]);jsonResponse(['status'=>'success','data'=>$s->fetchAll(),'followups'=>$followups]);}break;
        case 'screenings':
            apiUser(['Admin','Staff']);
            if($method==='GET'&&!$id){
                $where=[];$params=[];$q=field($_GET,'q',100,false);
                if($q!==''){$where[]="(u.public_reference LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";array_push($params,'%'.$q.'%','%'.$q.'%');}
                if(inputText($_GET,'status')!==''){$where[]='e.outcome=?';$params[]=choice($_GET,'status',['Eligible','Needs Review','Temporarily Deferred','Not Eligible','Reviewed']);}
                dateFilters('e.completed_at',$where,$params);
                jsonResponse(paginated($pdo,"e.*,u.public_reference,CONCAT(u.first_name,' ',u.last_name) AS donor_name,CONCAT(r.first_name,' ',r.last_name) AS reviewer",'eligibility_checks e JOIN users u ON u.user_id=e.user_id LEFT JOIN users r ON r.user_id=e.reviewed_by',$where,$params,['newest'=>'e.eligibility_id DESC','date'=>'e.completed_at ASC,e.eligibility_id ASC'],'newest'));
            }
            if($method==='PUT'&&$id){reviewScreening($pdo,$user,$id,$data);jsonResponse(['status'=>'success','message'=>'Screening reviewed. The donor has been notified.']);}break;
        case 'newsletter':
            apiUser(['Admin']);
            if($method==='GET'&&!$id){$where=[];$params=[];$q=field($_GET,'q',100,false);if($q!==''){$where[]='email LIKE ?';$params[]='%'.$q.'%';}if(inputText($_GET,'status')!==''){$where[]='subscription_status=?';$params[]=choice($_GET,'status',['Pending','Subscribed','Unsubscribed']);}dateFilters('requested_at',$where,$params);jsonResponse(paginated($pdo,'email,subscription_status,delivery_status,requested_at,confirmed_at','newsletter_subscriptions',$where,$params,['newest'=>'subscription_id DESC','date'=>'requested_at ASC,subscription_id ASC'],'newest'));}break;
        case 'notifications':
            if($method==='GET'&&!$id)jsonResponse(notificationPage($pdo,(int)$user['user_id'],(int)($_GET['page']??1),(int)($_GET['limit']??20)));
            if($method==='PUT'&&$id){if(!markNotificationRead($pdo,(int)$user['user_id'],$id))throw new ApiError('Notification not found.',404);jsonResponse(['status'=>'success','message'=>'Notification marked read.']);}break;
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
            if ($method==='DELETE'&&$id) { deleteUser($pdo,$user,$id,$data); jsonResponse(['status'=>'success','message'=>'Account successfully deactivated.']); } break;
        case 'appointments':
            if ($method==='GET'&&!$id) jsonResponse(listAppointments($pdo,$user));
            if ($method==='POST'&&!$id) {
                apiUser(['Donor']); $token=bookAppointment($pdo,$user['user_id'],integer($data,'campaign_id'),field($data,'scheduled_time_slot',8),$data);
                jsonResponse(['status'=>'success','message'=>'Registration submitted for confirmation.','data'=>['token'=>$token]],201);
            }
            if ($id&&in_array($method,['PUT','DELETE'],true)) { updateAppointment($pdo,$user,$id,$data,$method==='DELETE'); jsonResponse(['status'=>'success','message'=>$method==='DELETE'?'Registration cancelled.':'Registration status updated.']); } break;
        case 'donations':
            apiUser(['Admin','Staff']);
            if ($method==='POST'&&!$id) { $saved=recordDonation($pdo,$user,$data); jsonResponse(['status'=>'success','message'=>donationSuccessMessage($data['clinical_outcome']),'data'=>['donation_id'=>$saved]],201); }
            break;
        case 'inventory':
            apiUser(['Admin','Staff']);
            expireInventory($pdo);
            if($method==='PUT'&&$id){updateInventory($pdo,$user,$id,$data);jsonResponse(['status'=>'success','message'=>'Inventory status updated.']);}
            if ($method==='GET'&&!$id) {
                $where=[];$params=[];$q=field($_GET,'q',100,false);
                if($q!==''){$where[]='blood_type LIKE ?';$params[]='%'.$q.'%';}
                if(inputText($_GET,'status')!==''){$where[]='inventory_status=?';$params[]=choice($_GET,'status',['Pending','Available','Reserved','Used','Expired','Discarded','Disposed']);}
                dateFilters('collection_date',$where,$params);
                jsonResponse(paginated($pdo,'inventory_id,public_reference,blood_type,units_available,volume_ml_per_unit,collection_date,expiration_date,inventory_status,(SELECT u.public_reference FROM inventory_transactions t JOIN donation_records d ON d.donation_id=t.donation_id JOIN appointments a ON a.appointment_id=d.appointment_id JOIN users u ON u.user_id=a.donor_id WHERE t.inventory_id=blood_inventory.inventory_id LIMIT 1) AS donor_reference,(SELECT c.title FROM inventory_transactions t JOIN donation_records d ON d.donation_id=t.donation_id JOIN appointments a ON a.appointment_id=d.appointment_id JOIN campaigns c ON c.campaign_id=a.campaign_id WHERE t.inventory_id=blood_inventory.inventory_id LIMIT 1) AS source,(SELECT CONCAT(u.first_name,\' \',u.last_name) FROM inventory_transactions t LEFT JOIN users u ON u.user_id=t.executed_by_staff_id WHERE t.inventory_id=blood_inventory.inventory_id ORDER BY t.transaction_id DESC LIMIT 1) AS processed_by','blood_inventory',$where,$params,['newest'=>'inventory_id DESC','date'=>'expiration_date ASC,inventory_id ASC'],'newest'));
            } break;
        case 'transactions':
            apiUser(['Admin','Staff']);
            if($method==='GET'&&!$id){$where=[];$params=[];$q=field($_GET,'q',100,false);if($q!==''){$where[]='(t.public_reference LIKE ? OR t.transaction_notes LIKE ? OR c.title LIKE ?)';array_push($params,'%'.$q.'%','%'.$q.'%','%'.$q.'%');}dateFilters('t.transaction_timestamp',$where,$params);jsonResponse(paginated($pdo,'t.transaction_id,t.public_reference,t.transaction_type,t.units_transacted,t.transaction_timestamp,t.transaction_notes,i.blood_type,i.inventory_status,CONCAT(s.first_name,\' \',s.last_name) AS actor,u.public_reference AS donor_reference,c.title AS campaign','inventory_transactions t LEFT JOIN blood_inventory i ON i.inventory_id=t.inventory_id LEFT JOIN users s ON s.user_id=t.executed_by_staff_id LEFT JOIN donation_records d ON d.donation_id=COALESCE(t.donation_id,(SELECT origin.donation_id FROM inventory_transactions origin WHERE origin.inventory_id=t.inventory_id AND origin.donation_id IS NOT NULL ORDER BY origin.transaction_id LIMIT 1)) LEFT JOIN appointments a ON a.appointment_id=d.appointment_id LEFT JOIN users u ON u.user_id=a.donor_id LEFT JOIN campaigns c ON c.campaign_id=a.campaign_id',$where,$params,['newest'=>'t.transaction_id DESC','date'=>'t.transaction_timestamp ASC,t.transaction_id ASC'],'newest'));}break;
        case 'messages':
            apiUser(['Admin','Staff']);
            if($method==='GET'&&!$id){$where=[];$params=[];$q=field($_GET,'q',100,false);if($q!==''){$where[]='(subject LIKE ? OR sender_name LIKE ?)';array_push($params,'%'.$q.'%','%'.$q.'%');}if(inputText($_GET,'status')!==''){$where[]='message_status=?';$params[]=choice($_GET,'status',['New','Read','Replied','Closed']);}dateFilters('received_at',$where,$params);jsonResponse(paginated($pdo,'*','contact_messages',$where,$params,['newest'=>'message_id DESC','date'=>'received_at ASC,message_id ASC'],'newest'));}
            if($method==='PUT'&&$id){$message=updateContact($pdo,$user,$id,$data);jsonResponse(['status'=>'success','message'=>$message]);}break;
        case 'reports':
            if($method==='GET'&&!$id){$report=reportData($pdo,$user);if(($_GET['format']??'')==='csv')exportReport($report);jsonResponse(['status'=>'success','data'=>$report]);}break;
    }
    throw new ApiError('Method or endpoint not supported.',405);
} catch(ApiError $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],$e->httpStatus);}
catch(InvalidArgumentException $e){jsonResponse(['status'=>'error','message'=>$e->getMessage()],422);}
catch(PDOException $e){error_log($e->getMessage());jsonResponse(['status'=>'error','message'=>$e->getCode()==='23000'?'This change conflicts with an existing or referenced record.':'The database service is temporarily unavailable.'], $e->getCode()==='23000'?409:503);}
catch(Throwable $e){error_log($e->getMessage());jsonResponse(['status'=>'error','message'=>'The service is temporarily unavailable. Please try again.'],503);}
