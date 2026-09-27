<?php
function listUsers(PDO $pdo): array {
    $where=[]; $params=[]; $q=field($_GET,'q',100,false);
    if ($q !== '') { $where[]='(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)'; $params=array_fill(0,3,'%'.$q.'%'); }
    if (inputText($_GET,'status') !== '') { $where[]='u.account_status=?'; $params[]=choice($_GET,'status',['Active','Suspended','Deactivated','Pending']); }
    return paginated($pdo,'u.user_id,u.email,u.first_name,u.last_name,u.contact_number,u.account_status,r.role_name,u.created_at','users u JOIN roles r ON r.role_id=u.role_id',$where,$params,['newest'=>'u.user_id DESC','name'=>'u.last_name ASC,u.user_id ASC'],'newest');
}
function saveUser(PDO $pdo, array $actor, array $data, ?int $id): int {
    $first=field($data,'first_name',50); $last=field($data,'last_name',50); $email=field($data,'email',100);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new ApiError('Enter a valid email.');
    $role=choice($data,'role_name',['Admin','Staff','Donor']); $status=choice($data,'account_status',['Active','Suspended','Deactivated','Pending']);
    $contact=field($data,'contact_number',20,false); $password=is_string($data['password']??null)?$data['password']:'';
    if ((!$id || $password !== '') && (strlen($password)<12 || strlen($password)>72)) throw new ApiError('Use a password between 12 and 72 bytes.');
    if ($id === (int)$actor['user_id'] && ($role !== 'Admin' || $status !== 'Active')) throw new ApiError('You cannot remove your own administrator access.',409);
    return transaction($pdo,function() use($pdo,$actor,$id,$first,$last,$email,$role,$status,$contact,$password) {
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
            $pdo->prepare('INSERT INTO users (first_name,last_name,email,role_id,account_status,contact_number,password_hash) VALUES (?,?,?,?,?,?,?)')->execute([$first,$last,$email,$roleId,$status,$contact?:null,password_hash($password,PASSWORD_DEFAULT)]); $id=(int)$pdo->lastInsertId();
        }
        auditAction($pdo,$actor['user_id'],'Save user','users',$id); return $id;
    });
}
function deleteUser(PDO $pdo,array $actor,int $id): void {
    $s=$pdo->prepare('SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE user_id=?'); $s->execute([$id]); $user=$s->fetch();
    if (!$user) throw new ApiError('User not found.',404);
    $user['account_status']='Deactivated'; unset($user['password_hash']); saveUser($pdo,$actor,$user,$id);
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
    if ($q !== '') { $where[]="(c.title LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ? OR CAST(a.appointment_id AS CHAR)=?)"; array_push($params,'%'.$q.'%','%'.$q.'%',$q); }
    if (inputText($_GET,'status') !== '') { $where[]='a.appointment_status=?'; $params[]=choice($_GET,'status',['Pending','Confirmed','Checked-In','Completed','Deferred','Cancelled','No-Show']); }
    dateFilters('c.campaign_date',$where,$params);
    $select="a.appointment_id,a.donor_id,a.campaign_id,a.scheduled_time_slot,a.appointment_status,a.cancellation_reason,a.booked_at,c.title,c.campaign_date,c.location_venue,CONCAT(u.first_name,' ',u.last_name) AS donor_name";
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
        if (!$staff && $campaign['campaign_date'].' '.$campaign['start_time'] <= date('Y-m-d H:i:s')) throw new ApiError('Please contact staff to cancel an event that has already started.',409);
        if (in_array($status,['Checked-In','No-Show'],true) && $campaign['campaign_date'] > date('Y-m-d')) throw new ApiError('Attendance can only be recorded on or after the event date.',409);
        $reason = null;
        if ($status === 'Cancelled') {
            $reason = field($data, 'cancellation_reason', 1000);
            if (strlen($reason) < 3) throw new ApiError('Provide a brief reason for cancelling this registration.', 422);
        }
        $pdo->prepare('UPDATE appointments SET appointment_status=?, cancellation_reason=? WHERE appointment_id=?')->execute([$status,$reason,$id]);
        if ($status === 'Cancelled') $pdo->prepare('UPDATE campaigns SET available_slots=LEAST(total_slots,available_slots+1) WHERE campaign_id=?')->execute([$campaign['campaign_id']]);
        auditAction($pdo,$user['user_id'],'Registration '.$status,'appointments',$id);
    });
}
function recordDonation(PDO $pdo,array $user,array $data): int {
    $id=integer($data,'appointment_id'); $outcome=choice($data,'clinical_outcome',['Completed','Deferred']);
    $blood=choice($data,'blood_type_collected',['A+','A-','B+','B-','AB+','AB-','O+','O-']);
    $volume=$outcome==='Completed'?integer($data,'volume_ml',1,1000):0;
    $reason=field($data,'deferral_reason',1000,$outcome==='Deferred');
    $notes=field($data,'medical_notes',2000,false);
    $expiry=inputText($data,'expiration_date'); $eligible=inputText($data,'estimated_eligible_date');
    if ($outcome==='Completed' && (!validDate($expiry) || $expiry<=date('Y-m-d') || !validDate($eligible) || $eligible<=date('Y-m-d'))) throw new ApiError('Staff must enter future inventory expiry and next eligible dates.');
    return transaction($pdo,function() use($pdo,$user,$id,$outcome,$blood,$volume,$reason,$notes,$expiry,$eligible) {
        [$appointment,$campaign]=lockAppointment($pdo,$id);
        if ($appointment['appointment_status']!=='Checked-In') throw new ApiError('Only checked-in registrations can receive a donation outcome.',409);
        $s=$pdo->prepare('SELECT donation_id FROM donation_records WHERE appointment_id=?'); $s->execute([$id]); if ($s->fetch()) throw new ApiError('This donation outcome has already been recorded.',409);
        $pdo->prepare('INSERT INTO donation_records (appointment_id,verified_by_staff_id,donation_date,blood_type_collected,volume_ml,clinical_outcome,deferral_reason,medical_notes) VALUES (?,?,?,?,?,?,?,?)')->execute([$id,$user['user_id'],date('Y-m-d'),$blood,$volume,$outcome,$reason?:null,$notes?:null]); $donationId=(int)$pdo->lastInsertId();
        $pdo->prepare('UPDATE appointments SET appointment_status=? WHERE appointment_id=?')->execute([$outcome,$id]);
        if ($outcome==='Completed') {
            $pdo->prepare("INSERT INTO blood_inventory (blood_type,units_available,volume_ml_per_unit,collection_date,expiration_date,inventory_status) VALUES (?,1,?,?,?,'Reserved')")->execute([$blood,$volume,date('Y-m-d'),$expiry]); $inventoryId=(int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO inventory_transactions (inventory_id,executed_by_staff_id,transaction_type,units_transacted,donation_id,transaction_notes) VALUES (?,?,'Addition',1,?,'Donation intake; reserved pending laboratory review')")->execute([$inventoryId,$user['user_id'],$donationId]);
            $s=$pdo->prepare('SELECT cr.date_of_birth,e.answers_json FROM campaign_registrations cr JOIN eligibility_checks e ON e.eligibility_id=cr.eligibility_id WHERE cr.appointment_id=?'); $s->execute([$id]); $registration=$s->fetch();
            if ($registration) {
                $answers=json_decode($registration['answers_json'],true);
                $pdo->prepare('INSERT INTO donor_profiles (user_id,blood_type,date_of_birth,weight_kg,last_donation_date,estimated_eligible_date) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE blood_type=VALUES(blood_type),last_donation_date=VALUES(last_donation_date),estimated_eligible_date=VALUES(estimated_eligible_date)')->execute([$appointment['donor_id'],$blood,$registration['date_of_birth'],$answers['weight_kg'],date('Y-m-d'),$eligible]);
            } else {
                $pdo->prepare('UPDATE donor_profiles SET last_donation_date=?,estimated_eligible_date=? WHERE user_id=?')->execute([date('Y-m-d'),$eligible,$appointment['donor_id']]);
            }
        }
        auditAction($pdo,$user['user_id'],'Donation '.$outcome,'donation_records',$donationId); return $donationId;
    });
}
