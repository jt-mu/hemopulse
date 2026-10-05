<?php
require_once __DIR__.'/status_notices.php';
function donationSuccessMessage(string $outcome): string {
    return $outcome==='Completed' ? "Donation successfully recorded. The donor's donation history and next eligibility date have been updated." : 'Deferral recorded. The donor can be assessed again on the review date.';
}
function listUsers(PDO $pdo): array {
    $where=[]; $params=[]; $q=field($_GET,'q',100,false);
    if ($q !== '') { $where[]='(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.public_reference LIKE ?)'; $params=array_fill(0,4,'%'.$q.'%'); }
    if (inputText($_GET,'status') !== '') { $where[]='u.account_status=?'; $params[]=choice($_GET,'status',['Active','Suspended','Deactivated','Pending']); }
    return paginated($pdo,'u.user_id,u.public_reference,u.email,u.first_name,u.last_name,u.contact_number,u.account_status,u.status_reason,(SELECT delivery_status FROM account_status_notices n WHERE n.user_id=u.user_id ORDER BY notice_id DESC LIMIT 1) AS notice_delivery,r.role_name,u.created_at','users u JOIN roles r ON r.role_id=u.role_id',$where,$params,['newest'=>'u.user_id DESC','name'=>'u.last_name ASC,u.user_id ASC'],'newest');
}
function saveUser(PDO $pdo, array $actor, array $data, ?int $id): int {
    $first=field($data,'first_name',50); $last=field($data,'last_name',50); $email=field($data,'email',100);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new ApiError('Enter a valid email.');
    $role=choice($data,'role_name',['Admin','Staff','Donor']); $status=choice($data,'account_status',['Active','Suspended','Deactivated','Pending']);
    $contact=field($data,'contact_number',20,false); $password=is_string($data['password']??null)?$data['password']:'';
    if($contact!==''&&!validContact($contact))throw new ApiError('Use an 11-digit mobile number starting with 09.');
    if(!$id||$password!=='') { validatePassword($password); if(($data['confirm_password']??'')!==$password)throw new ApiError('Passwords must match.'); }
    if ($id === (int)$actor['user_id'] && ($role !== 'Admin' || $status !== 'Active')) throw new ApiError('You cannot remove your own administrator access.',409);
    $reason=field($data,'status_reason',1000,false);$notice=null;
    $saved=transaction($pdo,function() use($pdo,$actor,$id,$first,$last,$email,$role,$status,$contact,$password,$reason,&$notice) {
        $roles=$pdo->query('SELECT role_id,role_name FROM roles ORDER BY role_id FOR UPDATE')->fetchAll(PDO::FETCH_KEY_PAIR);
        $roleId=array_search($role,$roles,true); if (!$roleId) throw new ApiError('Role is unavailable.');
        if ($id) {
            $s=$pdo->prepare('SELECT * FROM users WHERE user_id=? FOR UPDATE'); $s->execute([$id]); $old=$s->fetch(); if (!$old) throw new ApiError('User not found.',404);
            if ($roles[$old['role_id']] === 'Admin' && $old['account_status'] === 'Active' && ($role !== 'Admin' || $status !== 'Active')) {
                $count=$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id=r.role_id WHERE r.role_name='Admin' AND u.account_status='Active'")->fetchColumn();
                if ($count <= 1) throw new ApiError('At least one active administrator must remain.',409);
            }
            $sql='UPDATE users SET first_name=?,last_name=?,email=?,role_id=?,account_status=?,contact_number=?'; $values=[$first,$last,$email,$roleId,$status,$contact?:null];
            if ($password !== '') { $sql.=',password_hash=?'; $values[]=password_hash($password,PASSWORD_DEFAULT); }
            $values[]=$id; $pdo->prepare($sql.' WHERE user_id=?')->execute($values);
        } else {
            $pdo->prepare('INSERT INTO users (first_name,last_name,email,role_id,account_status,contact_number,password_hash,public_reference) VALUES (?,?,?,?,?,?,?,?)')->execute([$first,$last,$email,$roleId,$status,$contact?:null,password_hash($password,PASSWORD_DEFAULT),publicReference('USR')]); $id=(int)$pdo->lastInsertId();
        }
        if(($old['account_status']??'Active')!==$status) {
            if(strlen($reason)<3)throw new ApiError('Provide a reason for changing the account status.');
            $pdo->prepare('UPDATE users SET status_reason=? WHERE user_id=?')->execute([$reason,$id]);
            $notice=queueStatusNotice($pdo,$id,(int)$actor['user_id'],$old['account_status']??null,$status,$reason,$old['email']??$email);
        }
        auditAction($pdo,$actor['user_id'],isset($old)?'Account updated':'Account created','users',$id,['old_status'=>$old['account_status']??null,'new_status'=>$status,'old_role'=>$old['role_id']??null,'new_role'=>$roleId,'reason'=>$reason]); return $id;
    });
    if($notice)deliverStatusNotice($pdo,$notice);return $saved;
}
function deleteUser(PDO $pdo,array $actor,int $id,array $data=[]): void {
    if($id===(int)$actor['user_id'])throw new ApiError('You cannot remove your own administrator access.',409);
    $reason=field($data,'status_reason',1000);if(strlen($reason)<3)throw new ApiError('Provide a brief reason for deactivation.');
    $notice=transaction($pdo,function()use($pdo,$actor,$id,$reason){
        $pdo->query('SELECT role_id FROM roles ORDER BY role_id FOR UPDATE')->fetchAll();
        $s=$pdo->prepare('SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE user_id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetch();
        if(!$old)throw new ApiError('User not found.',404);
        if($old['account_status']==='Deactivated')throw new ApiError('This account is already deactivated.',409);
        if($old['role_name']==='Admin'&&$old['account_status']==='Active') {
            $count=$pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='Admin' AND u.account_status='Active'")->fetchColumn();
            if($count<=1)throw new ApiError('At least one active administrator must remain.',409);
        }
        $pdo->prepare("UPDATE users SET account_status='Deactivated',status_reason=? WHERE user_id=?")->execute([$reason,$id]);
        $notice=queueStatusNotice($pdo,$id,(int)$actor['user_id'],$old['account_status'],'Deactivated',$reason,$old['email']);
        auditAction($pdo,$actor['user_id'],'Account deactivated','users',$id,['from'=>$old['account_status'],'to'=>'Deactivated','reason'=>$reason]);return $notice;
    });deliverStatusNotice($pdo,$notice);
}
function categoryMutation(PDO $pdo,array $actor,string $method,?int $id,array $data): int {
    return transaction($pdo,function() use($pdo,$actor,$method,$id,$data) {
        if ($id) { $s=$pdo->prepare('SELECT category_id FROM campaign_categories WHERE category_id=? FOR UPDATE'); $s->execute([$id]); if (!$s->fetch()) throw new ApiError('Category not found.',404); }
        if ($method === 'DELETE') {
            $s=$pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE category_id=?'); $s->execute([$id]); if ($s->fetchColumn()) throw new ApiError('This category is used by a campaign.',409);
            $pdo->prepare('DELETE FROM campaign_categories WHERE category_id=?')->execute([$id]);
        } else {
            $name=field($data,'name',80); $description=field($data,'description',255,false);
            if ($id) $pdo->prepare('UPDATE campaign_categories SET name=?,description=? WHERE category_id=?')->execute([$name,$description,$id]);
            else { $pdo->prepare('INSERT INTO campaign_categories (name,description) VALUES (?,?)')->execute([$name,$description]); $id=(int)$pdo->lastInsertId(); }
        }
        auditAction($pdo,$actor['user_id'],$method.' category','campaign_categories',$id); return $id;
    });
}
function listAppointments(PDO $pdo,array $user): array {
    $where=[]; $params=[]; $staff=in_array($user['role_name'],['Admin','Staff'],true);
    if (!$staff) { $where[]='a.donor_id=?'; $params[]=$user['user_id']; }
    $q=field($_GET,'q',100,false);
    if ($q !== '') { $where[]="(c.title LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ? OR a.public_reference=?)"; array_push($params,'%'.$q.'%','%'.$q.'%',$q); }
    if (inputText($_GET,'status') === 'Overdue') { $where[]="a.appointment_status IN ('Pending','Confirmed','Checked-In') AND TIMESTAMP(c.campaign_date,c.end_time)<=NOW()"; }
    elseif (inputText($_GET,'status') !== '') { $where[]='a.appointment_status=?'; $params[]=choice($_GET,'status',['Pending','Confirmed','Checked-In','Completed','Deferred','Cancelled','No-Show']); }
    dateFilters('c.campaign_date',$where,$params);
    $select="a.public_reference,a.appointment_id,a.donor_id,a.campaign_id,a.scheduled_time_slot,a.appointment_status,a.cancellation_reason,a.booked_at,c.title,c.campaign_date,c.location_venue,CONCAT(u.first_name,' ',u.last_name) AS donor_name";
    if ($staff) $select.=',u.email';
    return paginated($pdo,$select,'appointments a JOIN campaigns c ON c.campaign_id=a.campaign_id JOIN users u ON u.user_id=a.donor_id',$where,$params,['newest'=>'a.appointment_id DESC','date'=>'c.campaign_date ASC,a.scheduled_time_slot ASC,a.appointment_id ASC','name'=>'u.last_name ASC,a.appointment_id ASC'],'newest');
}
/** Lock donor, campaign, appointment in the same order used by registration. */
function lockAppointment(PDO $pdo,int $id): array {
    $s=$pdo->prepare('SELECT donor_id,campaign_id FROM appointments WHERE appointment_id=?'); $s->execute([$id]); $ref=$s->fetch(); if (!$ref) throw new ApiError('Registration not found.',404);
    $s=$pdo->prepare('SELECT user_id FROM users WHERE user_id=? FOR UPDATE'); $s->execute([$ref['donor_id']]);
    $s=$pdo->prepare('SELECT * FROM campaigns WHERE campaign_id=? FOR UPDATE'); $s->execute([$ref['campaign_id']]); $campaign=$s->fetch();
    $s=$pdo->prepare('SELECT * FROM appointments WHERE appointment_id=? FOR UPDATE'); $s->execute([$id]); $appointment=$s->fetch();
    return [$appointment,$campaign];
}
function updateAppointment(PDO $pdo,array $user,int $id,array $data,bool $cancel=false): void {
    transaction($pdo,function() use($pdo,$user,$id,$data,$cancel) {
        [$appointment,$campaign]=lockAppointment($pdo,$id);
        $staff=in_array($user['role_name'],['Admin','Staff'],true);
        if (!$staff && (int)$appointment['donor_id'] !== (int)$user['user_id']) throw new ApiError('Registration not found.',404);
        $old=$appointment['appointment_status']; $status=$cancel?'Cancelled':field($data,'appointment_status',20);
        $transitions=['Pending'=>['Confirmed','Cancelled'],'Confirmed'=>['Checked-In','Cancelled','No-Show']];
        if (!$staff && !$cancel) throw new ApiError('Only staff can confirm or process registrations.',403);
        if (!in_array($status,$transitions[$old]??[],true)) throw new ApiError('This status change is not allowed. Refresh the record.',409);
        if ($status==='Confirmed' && $campaign['campaign_date'].' '.$campaign['end_time']<=date('Y-m-d H:i:s')) throw new ApiError('This event has ended. Cancel the unresolved registration with a reason.',409);
        if (!$staff && $campaign['campaign_date'].' '.$campaign['start_time'] <= date('Y-m-d H:i:s')) throw new ApiError('Please contact staff to cancel an event that has already started.',409);
        if ($status==='Checked-In' && $campaign['campaign_date']!==date('Y-m-d')) throw new ApiError('Check-in is available only on the event date. Contact an administrator for historical corrections.',409);
        if ($status==='No-Show' && $campaign['campaign_date'].' '.$appointment['scheduled_time_slot']>date('Y-m-d H:i:s')) throw new ApiError('No-show can be recorded only after the scheduled appointment time.',409);
        $reason = null;
        if ($status === 'Cancelled') {
            $reason = field($data, 'cancellation_reason', 1000);
            if (strlen($reason) < 3) throw new ApiError('Provide a brief reason for cancelling this registration.', 422);
        }
        $pdo->prepare('UPDATE appointments SET appointment_status=?, cancellation_reason=? WHERE appointment_id=?')->execute([$status,$reason,$id]);
        if ($status === 'Cancelled') $pdo->prepare('UPDATE campaigns SET available_slots=LEAST(total_slots,available_slots+1) WHERE campaign_id=?')->execute([$campaign['campaign_id']]);
        $pdo->prepare("INSERT INTO notifications(user_id,notification_type,subject,message_body) VALUES(?,'Appointment',?,?)")->execute([$appointment['donor_id'],'Registration '.$status,$campaign['title'].' · '.$campaign['campaign_date'].' '.$appointment['scheduled_time_slot'].': '.$status.($reason?' — '.$reason:'')]);
        auditAction($pdo,$user['user_id'],'Registration '.$status,'appointments',$id);
    });
}
function recordDonation(PDO $pdo,array $user,array $data): int {
    $id=integer($data,'appointment_id'); $outcome=choice($data,'clinical_outcome',['Completed','Deferred']);
    $blood=$outcome==='Completed'?choice($data,'blood_type_collected',['A+','A-','B+','B-','AB+','AB-','O+','O-']):null;
    $volume=$outcome==='Completed'?integer($data,'volume_ml',1,1000):0;
    $reason=field($data,'deferral_reason',1000,$outcome==='Deferred');
    $notes=field($data,'medical_notes',2000,false);
    $expiry=inputText($data,'expiration_date'); $eligible=nextDonationDate(date('Y-m-d'));
    if ($outcome==='Completed' && (!validDate($expiry) || $expiry<=date('Y-m-d') || !validDate($eligible) || $eligible<=date('Y-m-d'))) throw new ApiError('Staff must enter a future inventory expiry date.');
    return transaction($pdo,function() use($pdo,$user,$id,$outcome,$blood,$volume,$reason,$notes,$expiry,$eligible,$data) {
        [$appointment,$campaign]=lockAppointment($pdo,$id);
        if ($appointment['appointment_status']!=='Checked-In') throw new ApiError('Only checked-in registrations can receive a donation outcome.',409);
        if ($outcome==='Completed') {
            $check=latestEligibility($pdo,(int)$appointment['donor_id']);
            if(!$check||$check['outcome']!=='Eligible')throw new ApiError('The latest screening requires staff review before a completed donation can be recorded.',409);
            $next=donorNextDate($pdo,(int)$appointment['donor_id']);
            if ($next && $next>date('Y-m-d')) throw new ApiError('The recorded donation interval or dated deferral has not ended. Record a deferred outcome instead.',409);
        }
        $s=$pdo->prepare('SELECT donation_id FROM donation_records WHERE appointment_id=?'); $s->execute([$id]); if ($s->fetch()) throw new ApiError('This donation outcome has already been recorded.',409);
        $pdo->prepare('INSERT INTO donation_records (appointment_id,verified_by_staff_id,donation_date,blood_type_collected,volume_ml,clinical_outcome,deferral_reason,medical_notes) VALUES (?,?,?,?,?,?,?,?)')->execute([$id,$user['user_id'],date('Y-m-d'),$blood,$volume,$outcome,$reason?:null,$notes?:null]); $donationId=(int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE appointments SET appointment_status=? WHERE appointment_id=?')->execute([$outcome,$id]);
        $pdo->prepare("INSERT INTO notifications(user_id,notification_type,subject,message_body) VALUES(?,'Eligibility','Donation outcome recorded',?)")->execute([$appointment['donor_id'],$outcome==='Completed'?'Your donation history has been updated. Next eligible donation: '.$eligible.'.':'Donation deferred: '.$reason]);
        if ($outcome==='Completed') {
            $pdo->prepare("INSERT INTO blood_inventory (blood_type,units_available,volume_ml_per_unit,collection_date,expiration_date,inventory_status,public_reference) VALUES (?,1,?,?,?,'Pending',?)")->execute([$blood,$volume,date('Y-m-d'),$expiry,publicReference('UNT')]); $inventoryId=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventory_transactions (inventory_id,executed_by_staff_id,transaction_type,units_transacted,donation_id,transaction_notes,public_reference) VALUES (?,?,'Addition',1,?,'Donation recorded; pending laboratory review',?)")->execute([$inventoryId,$user['user_id'],$donationId,publicReference('TXN')]);
            $s=$pdo->prepare('SELECT cr.date_of_birth,e.answers_json FROM campaign_registrations cr JOIN eligibility_checks e ON e.eligibility_id=cr.eligibility_id WHERE cr.appointment_id=?'); $s->execute([$id]); $registration=$s->fetch();
            if ($registration) {
                $answers=json_decode($registration['answers_json'],true);
                $pdo->prepare('INSERT INTO donor_profiles (user_id,blood_type,date_of_birth,weight_kg,last_donation_date,estimated_eligible_date) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE blood_type=VALUES(blood_type),last_donation_date=VALUES(last_donation_date),estimated_eligible_date=VALUES(estimated_eligible_date)')->execute([$appointment['donor_id'],$blood,$registration['date_of_birth'],$answers['weight_kg'],date('Y-m-d'),$eligible]);
            } else {
                $pdo->prepare('INSERT INTO donor_profiles(user_id,blood_type,last_donation_date,estimated_eligible_date) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE blood_type=VALUES(blood_type),last_donation_date=VALUES(last_donation_date),estimated_eligible_date=VALUES(estimated_eligible_date)')->execute([$appointment['donor_id'],$blood,date('Y-m-d'),$eligible]);
            }
        }
        if($outcome==='Deferred') {
            $until=inputText($data,'deferred_until');
            if(!validDate($until)||$until<=date('Y-m-d')) throw new ApiError('Enter a future deferral review date.');
            $pdo->prepare('INSERT INTO donor_profiles(user_id,estimated_eligible_date) VALUES(?,?) ON DUPLICATE KEY UPDATE estimated_eligible_date=GREATEST(COALESCE(estimated_eligible_date,VALUES(estimated_eligible_date)),VALUES(estimated_eligible_date))')->execute([$appointment['donor_id'],$until]);
            $check=latestEligibility($pdo,(int)$appointment['donor_id']);
            if($check) $pdo->prepare("UPDATE eligibility_checks SET outcome='Temporarily Deferred',deferred_until=?,review_notes=?,reviewed_by=?,reviewed_at=NOW() WHERE eligibility_id=?")->execute([$until,$reason,$user['user_id'],$check['eligibility_id']]);
        }
        auditAction($pdo,$user['user_id'],'Donation '.$outcome,'donation_records',$donationId); return $donationId;
    });
}
