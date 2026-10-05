<?php
/** Disposable database + real HTTP endpoints. Never changes the installed database. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
date_default_timezone_set('Asia/Manila');
$root=dirname(__DIR__);$name='hemopulse_test_'.bin2hex(random_bytes(6));
putenv('HEMOPULSE_DB_NAME='.$name);putenv('HEMOPULSE_MAIL_TRANSPORT=preview');putenv('MAIL_MODE=preview');
$temporary=sys_get_temp_dir().'/'.$name;mkdir($temporary,0700,true);
mkdir($temporary.'/sessions',0700,true);
putenv('HEMOPULSE_MAIL_PREVIEW_DIR='.$temporary.'/mail');
require $root.'/config/database.php';
$server=new PDO('mysql:host='.DB_HOST.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$process=null;$checks=0;
function check(bool $ok,string $message): void { global $checks; if(!$ok)throw new RuntimeException('FAIL: '.$message);$checks++;echo "PASS: $message\n"; }
function request(string $client,string $path,string $method='GET',?array $data=null,bool $json=false,?string $csrf=null): array {
 global $port,$temporary;
 $c=curl_init('http://127.0.0.1:'.$port.'/'.$path);$headers=[];
 if($json)$headers[]='Content-Type: application/json';if($csrf!==null)$headers[]='X-CSRF-Token: '.$csrf;
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_COOKIEFILE=>$temporary.'/'.$client.'.cookies',CURLOPT_COOKIEJAR=>$temporary.'/'.$client.'.cookies',CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>10]);
 if($data!==null)curl_setopt($c,CURLOPT_POSTFIELDS,$json?json_encode($data,JSON_FORCE_OBJECT):http_build_query($data));
 $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);if($body===false)throw new RuntimeException(curl_error($c));curl_close($c);
 return ['code'=>$status,'body'=>$body,'json'=>json_decode($body,true)];
}
function token(string $client): string { $r=request($client,'api/index.php/session');return $r['json']['data']['csrf']; }
function login(string $client,string $email,string $password='Safe-Demo!2026'): array {return request($client,'backend/login_handler.php','POST',['csrf'=>token($client),'email'=>$email,'password'=>$password]);}
try {
 $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");$pdo=getDBConnection();$pdo->exec(file_get_contents($root.'/deployment/schema.sql'));
 require $root.'/database/migrate.php';applyPortalMigration($pdo);applyPortalMigration($pdo);
 require $root.'/includes/eligibility.php';require $root.'/includes/campaigns.php';
 check(validPassword('Safe-Demo!2026')&&!validPassword('password1234')&&!validPassword('aaaaaaaaaaaa')&&!validPassword(('A1!'.str_repeat('é',35))),'Password policy and byte limit');
 check(validContact('09171234567')&&!validContact('+639171234567')&&!validContact('1234567'),'One intentional Philippine contact format');
 check(nextDonationDate('2026-01-31')==='2026-04-30','Calendar-month interval clamps month end');
 $future=date('Y-m-d',strtotime('+2 days'));$past=date('Y-m-d',strtotime('-2 days'));
 $campaign=['campaign_date'=>$future,'start_time'=>'08:00:00','end_time'=>'14:00:00','campaign_status'=>'Published','available_slots'=>3];
 check(count(campaignSlots($campaign))===12&&!in_array('14:00:00',campaignSlots($campaign),true),'Dynamic slots exclude closing time');
 check(campaignRegistrationStatus($campaign)==='OPEN'&&campaignRegistrationStatus(array_replace($campaign,['available_slots'=>0]))==='FULL'&&campaignRegistrationStatus(array_replace($campaign,['campaign_date'=>$past]))==='CLOSED','Campaign open/full/expired states');
 $answers=['date_of_birth'=>'1995-01-01','weight_kg'=>'65','healthy'=>'yes','recent_illness'=>'no','medication'=>'no','donated_before'=>'no','recent_tattoo'=>'no','recent_travel'=>'no'];
 check(evaluateEligibility($answers)['outcome']==='Eligible','Eligible screening');
 check(evaluateEligibility(array_replace($answers,['medication'=>'yes']))['outcome']==='Needs Review','Needs Review screening');
 $deferred=evaluateEligibility(array_replace($answers,['donated_before'=>'yes','last_donation_date'=>date('Y-m-d')]));check($deferred['outcome']==='Temporarily Deferred'&&$deferred['deferred_until']===nextDonationDate(date('Y-m-d')),'Temporary deferral has a future review date');
 check(!screeningLocked(['deferred_until'=>$past]),'Expired deferral unlocks screening');
 $ids=[];foreach(['Admin','Staff','Donor'] as $role){$r=$pdo->query("SELECT role_id FROM roles WHERE role_name='$role'")->fetchColumn();$pdo->prepare('INSERT INTO users(role_id,email,password_hash,first_name,last_name,public_reference) VALUES(?,?,?,?,?,?)')->execute([$r,strtolower($role).'@example.test',password_hash('Safe-Demo!2026',PASSWORD_DEFAULT),$role,'Test',publicReference('USR')]);$ids[$role]=(int)$pdo->lastInsertId();}
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$port=(int)substr(strrchr(stream_socket_get_name($socket,false),':'),1);fclose($socket);
 putenv('HEMOPULSE_APP_URL=http://127.0.0.1:'.$port);
 $process=proc_open([PHP_BINARY,'-d','session.save_path="'.str_replace('\\','/',$temporary).'/sessions"','-S','127.0.0.1:'.$port,'-t',$root,$root.'/router.php'],[0=>['pipe','r'],1=>['file',$temporary.'/server.log','a'],2=>['file',$temporary.'/server.log','a']],$pipes,$root);
 for($i=0;$i<30;$i++){usleep(100000);$s=@fsockopen('127.0.0.1',$port);if($s){fclose($s);break;}}
 check(request('anon','api/index.php/users')['code']===401,'Logged-out protected API denied');
 check(in_array(request('anon','dashboard.php')['code'],[302,303],true),'Logged-out dashboard redirects');
 check(request('anon','backend/login_handler.php','POST',['email'=>'admin@example.test','password'=>'Safe-Demo!2026'])['code']===403,'Login requires CSRF');
 foreach(['Admin','Staff','Donor'] as $role)check(login(strtolower($role),strtolower($role).'@example.test')['code']===200,$role.' login');
 check(request('donor','workspace.php')['code']===403,'Donor cannot open staff workspace');
 check(request('donor','api/index.php/users')['code']===403,'Donor cannot access admin users');
 check(request('staff','api/index.php/users')['code']===403,'Staff cannot access admin users');
 check(request('donor','api/index.php/inventory')['code']===403,'Donor cannot read inventory');
 check(login('bad',"' OR 1=1 --",'bad')['code']===422,'Login injection rejected');
 for($i=0;$i<5;$i++)check(login('bad','nobody@example.test','wrong')['code']===422,'Incorrect login '.$i);
 check(str_contains(login('newcookie','nobody@example.test','wrong')['body'],'Too many requests'),'Login limit persists across new sessions');
 $admin=token('admin');$staff=token('staff');$donor=token('donor');
 $record=['first_name'=>'<script>alert(1)</script>','last_name'=>'Test','email'=>'created@example.test','role_name'=>'Donor','account_status'=>'Active','password'=>'Safe-Demo!2026','confirm_password'=>'Safe-Demo!2026','contact_number'=>'09171234567'];
 check(request('admin','api/index.php/users','POST',array_replace($record,['password'=>'short']),true,$admin)['code']===422,'Weak admin-created password rejected');
 check(request('admin','api/index.php/users','POST',array_replace($record,['contact_number'=>'abc']),true,$admin)['code']===422,'Invalid contact rejected');
 $r=request('admin','api/index.php/users','POST',$record,true,$admin);check($r['code']===201,'Admin creates account');$created=$r['json']['data']['user_id'];
 check(login('created','created@example.test')['code']===200,'Created account logs in');
 $html=request('created','dashboard.php')['body'];check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;')&&!str_contains($html,'<script>alert(1)</script>'),'Profile output escapes stored XSS');
 $pdo->prepare('UPDATE users SET contact_number=? WHERE user_id=?')->execute(['legacy phone',$created]);
 check(request('admin','api/index.php/users/'.$created,'DELETE',['status_reason'=>'Demo account no longer needed'],true,$admin)['code']===200,'Deactivation accepts JSON object and succeeds');
 check(request('created','api/index.php/appointments')['code']===401,'Deactivation invalidates existing session on next request');
 check(login('created','created@example.test')['code']===422,'Inactive account login rejected');
 check(request('admin','api/index.php/users/'.$ids['Admin'],'DELETE',[],true,$admin)['code']===409,'Cannot deactivate own administrator');
 $screen=request('donor','backend/eligibility_handler.php','POST',$answers+['csrf'=>$donor]);check($screen['code']===303,'Screening form saves');
 $check=latestEligibility($pdo,$ids['Donor']);check($check&&$check['outcome']==='Eligible','Screening persisted');
 $pdo->prepare("UPDATE eligibility_checks SET outcome='Needs Review' WHERE eligibility_id=?")->execute([$check['eligibility_id']]);
 check(request('staff','api/index.php/screenings/'.$check['eligibility_id'],'PUT',['outcome'=>'Eligible','review_notes'=>'Assessed by staff.'],true,$staff)['code']===200,'Staff approves Needs Review');
 check($pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn()==1,'Review creates persistent donor notification');
 check(request('staff','api/index.php/screenings/'.$check['eligibility_id'],'PUT',['outcome'=>'Temporarily Deferred','review_notes'=>'Return for assessment.','deferred_until'=>$future],true,$staff)['code']===200,'Staff records dated deferral');
 $count=(int)$pdo->query('SELECT COUNT(*) FROM eligibility_checks')->fetchColumn();
 request('donor','backend/eligibility_handler.php','POST',$answers+['csrf'=>$donor]);
 check((int)$pdo->query('SELECT COUNT(*) FROM eligibility_checks')->fetchColumn()===$count,'Active deferral prevents new screening');
 $pdo->prepare('UPDATE eligibility_checks SET deferred_until=? WHERE eligibility_id=?')->execute([$past,$check['eligibility_id']]);
 request('donor','backend/eligibility_handler.php','POST',$answers+['csrf'=>$donor]);
 check((int)$pdo->query('SELECT COUNT(*) FROM eligibility_checks')->fetchColumn()===$count+1,'Expired deferral permits new screening without changing history');
 check(request('staff','api/index.php/screenings/'.$check['eligibility_id'],'PUT',['outcome'=>'Eligible','review_notes'=>'Stale review'],true,$staff)['code']===409,'Stale screening review rejected');
 $campaignData=['title'=>'Test campaign','description'=>'<img src=x onerror=alert(1)>','location_venue'=>'Test venue','campaign_date'=>$future,'start_time'=>'08:00','end_time'=>'14:00','campaign_status'=>'Published','total_slots'=>2];
 check(request('staff','api/index.php/campaigns','POST',$campaignData,true,$staff)['code']===403,'Staff cannot create campaigns');
 check(request('admin','api/index.php/campaigns','POST',array_replace($campaignData,['campaign_date'=>'2026-02-30']),true,$admin)['code']===422,'Invalid campaign date rejected');
 $r=request('admin','api/index.php/campaigns','POST',$campaignData,true,$admin);check($r['code']===201,'Campaign created');$cid=$r['json']['data']['campaign_id'];
 $pdo->prepare("UPDATE campaigns SET campaign_date=?,campaign_status='Closed',registration_closes_at=? WHERE campaign_id=?")->execute([$past,$past.' 13:00:00',$cid]);
 $reschedule=array_replace($campaignData,['campaign_status'=>'Closed','registration_closes_at'=>$past.'T13:00']);
 check(request('admin','api/index.php/campaigns/'.$cid,'PUT',$reschedule,true,$admin)['code']===200,'Past closed campaign reschedules');
 $c=$pdo->query('SELECT * FROM campaigns WHERE campaign_id='.$cid)->fetch();check(campaignRegistrationStatus($c)==='OPEN','Rescheduled campaign reopens and stale deadline clears');
 check(request('donor','backend/campaign_selection.php','POST',['csrf'=>'invalid','campaign_id'=>$cid])['code']===403,'Saved selection requires CSRF');
 check(request('donor','backend/campaign_selection.php','POST',['csrf'=>$donor,'campaign_id'=>$cid])['code']===303,'Eligible donor persists campaign selection');
 check((int)$pdo->query('SELECT campaign_id FROM donor_campaign_selection WHERE user_id='.$ids['Donor'])->fetchColumn()===$cid,'Campaign selection stored against screening');
 $booking=['campaign_id'=>$cid,'scheduled_time_slot'=>'14:00','date_of_birth'=>'1995-01-01','contact_number'=>'09171234567','email'=>'donor@example.test','requirements_agreed'=>'1'];
 check(request('donor','api/index.php/appointments','POST',$booking,true,$donor)['code']===422,'Closing boundary rejected by backend');
 $booking['scheduled_time_slot']='08:30';$r=request('donor','api/index.php/appointments','POST',$booking,true,$donor);check($r['code']===201,'Donor books valid generated slot');
 check(request('donor','api/index.php/appointments','POST',$booking,true,$donor)['code']===422,'Duplicate active booking rejected');
 $move=array_replace($campaignData,['campaign_date'=>date('Y-m-d',strtotime('+3 days'))]);
 check(request('admin','api/index.php/campaigns/'.$cid,'PUT',$move,true,$admin)['code']===200,'Reschedule preserves fitting pending appointment');
 $aid=(int)$pdo->query('SELECT appointment_id FROM appointments')->fetchColumn();
 check(request('staff','api/index.php/appointments/'.$aid,'PUT',['appointment_status'=>'Confirmed'],true,$staff)['code']===200,'Staff confirms booking');
 check(request('staff','api/index.php/appointments/'.$aid,'PUT',['appointment_status'=>'Checked-In'],true,$staff)['code']===409,'Future appointment check-in rejected');
 $pdo->prepare('UPDATE campaigns SET campaign_date=? WHERE campaign_id=?')->execute([date('Y-m-d'),$cid]);
 check(request('staff','api/index.php/appointments/'.$aid,'PUT',['appointment_status'=>'Checked-In'],true,$staff)['code']===200,'Staff checks in on event date');
 $donation=['appointment_id'=>$aid,'clinical_outcome'=>'Completed','blood_type_collected'=>'O+','volume_ml'=>450,'expiration_date'=>date('Y-m-d',strtotime('+35 days'))];
 check(request('staff','api/index.php/donations','POST',$donation,true,$staff)['code']===201,'Donation completed atomically');
 check(request('staff','api/index.php/donations','POST',$donation,true,$staff)['code']===409,'Completed registration cannot be reused');
 check($pdo->query('SELECT inventory_status FROM blood_inventory')->fetchColumn()==='Pending','Donation creates Pending inventory');
 check(donorNextDate($pdo,$ids['Donor'])===nextDonationDate(date('Y-m-d')),'Next eligible date automatically stored');
 check(request('donor','dashboard.php')['code']===200,'Donor account remains accessible after donation');
 check(request('donor','api/index.php/appointments','POST',$booking,true,$donor)['code']===422,'Post-donation booking rejected');
 $unit=(int)$pdo->query('SELECT inventory_id FROM blood_inventory')->fetchColumn();
 check(request('staff','api/index.php/inventory/'.$unit,'PUT',['inventory_status'=>'Used','notes'=>'No testing yet'],true,$staff)['code']===409,'Untested unit cannot be used');
 check(request('staff','api/index.php/inventory/'.$unit,'PUT',['inventory_status'=>'Available','notes'=>'Required tests complete.'],true,$staff)['code']===200,'Staff releases tested unit');
 $pdo->prepare('UPDATE blood_inventory SET expiration_date=? WHERE inventory_id=?')->execute([$past,$unit]);
 $r=request('staff','api/index.php/inventory');check($r['json']['data'][0]['inventory_status']==='Expired','Expired inventory detected automatically');
 check(request('staff','api/index.php/inventory/'.$unit,'PUT',['inventory_status'=>'Available','notes'=>'Invalid release'],true,$staff)['code']===409,'Expired unit cannot be made available');
 $expiryCount=(int)$pdo->query("SELECT COUNT(*) FROM inventory_transactions WHERE transaction_type='Expiration'")->fetchColumn();request('staff','api/index.php/inventory');
 check((int)$pdo->query("SELECT COUNT(*) FROM inventory_transactions WHERE transaction_type='Expiration'")->fetchColumn()===$expiryCount,'Automatic expiration is idempotent');
 check(request('staff','api/index.php/transactions')['code']===200,'Operational transactions readable by staff');
 check(request('staff','api/index.php/reports')['code']===200,'Dashboard metrics and stock report');
 $contact=['csrf'=>token('contact'),'name'=>'Contact Test','email'=>'contact@example.test','subject'=>'Question','message'=>'<script>alert(1)</script>'];
 check(request('contact','backend/contact_handler.php','POST',$contact)['code']===303,'Contact inquiry accepted');
 $mid=(int)$pdo->query('SELECT message_id FROM contact_messages')->fetchColumn();
 check(request('staff','api/index.php/messages/'.$mid,'PUT',['message_status'=>'Read'],true,$staff)['code']===200,'Contact marked read');
 $r=request('staff','api/index.php/messages/'.$mid,'PUT',['message_status'=>'Replied','reply_body'=>'Thank you.'],true,$staff);check($r['code']===200&&str_contains($r['body'],'no email was sent'),'Reply preview accurately reported');
 check($pdo->query('SELECT COUNT(*) FROM contact_replies')->fetchColumn()==1,'Reply history persisted');
 check(request('staff','api/index.php/messages/'.$mid,'PUT',['message_status'=>'Closed'],true,$staff)['code']===200,'Contact closed');
 $otp=['csrf'=>token('otp'),'action'=>'send_otp','email'=>'otp@example.test','password'=>'Safe-Demo!2026','first_name'=>'OTP','last_name'=>'Test','terms'=>'1'];
 check(request('otp','backend/otp_handler.php','POST',array_replace($otp,['terms'=>'']))['code']===422,'Registration terms enforced on backend');
 $r=request('otp','backend/otp_handler.php','POST',$otp);check($r['code']===200&&!str_contains($r['body'],'demo_otp'),'OTP request returns no code');
 $code=null;foreach(glob($temporary.'/mail/*.eml') as $file){$mail=file_get_contents($file);if(str_contains($mail,'To: otp@example.test')&&preg_match('/<strong>([0-9]{6})<\/strong>/',$mail,$m))$code=$m[1];}
 check($code!==null&&!str_contains($r['body'],$code),'OTP delivered only through mail transport');
 check(request('otp','backend/otp_handler.php','POST',$otp)['code']===422,'OTP resend cooldown');
 check(request('otp','backend/otp_handler.php','POST',['csrf'=>$otp['csrf'],'action'=>'verify_otp','otp'=>'000000'])['code']===422,'Invalid OTP rejected');
 check(request('otp','backend/otp_handler.php','POST',['csrf'=>$otp['csrf'],'action'=>'verify_otp','otp'=>$code])['code']===200,'OTP verifies and creates account');
 check(request('otp','backend/otp_handler.php','POST',['csrf'=>token('otp'),'action'=>'verify_otp','otp'=>$code])['code']===422,'OTP cannot be reused');
 foreach(['expired','limited'] as $client){
    $otp['csrf']=token($client);$otp['email']=$client.'@example.test';
    check(request($client,'backend/otp_handler.php','POST',$otp)['code']===200,'OTP issued for '.$client.' test');
    if($client==='expired'){
        foreach(glob($temporary.'/sessions/sess_*') as $file){$session=file_get_contents($file);if(str_contains($session,'expired@example.test'))file_put_contents($file,preg_replace('/s:11:"otp_expires";i:[0-9]+;/','s:11:"otp_expires";i:1;',$session));}
        $r=request($client,'backend/otp_handler.php','POST',['csrf'=>$otp['csrf'],'action'=>'verify_otp','otp'=>'123456']);
        check($r['code']===422&&str_contains($r['body'],'expired'),'Expired OTP rejected');
    }else{
        for($i=0;$i<5;$i++)request($client,'backend/otp_handler.php','POST',['csrf'=>$otp['csrf'],'action'=>'verify_otp','otp'=>'000000']);
        $r=request($client,'backend/otp_handler.php','POST',['csrf'=>$otp['csrf'],'action'=>'verify_otp','otp'=>'000000']);
        check($r['code']===422&&str_contains($r['body'],'retry limit'),'OTP verification attempt limit');
    }
 }
 $newsletter=['csrf'=>token('newsletter'),'newsletter_email'=>'news@example.test'];
 check(request('newsletter','backend/newsletter_handler.php','POST',$newsletter)['code']===303,'Newsletter request accepted');
 check($pdo->query('SELECT subscription_status FROM newsletter_subscriptions')->fetchColumn()==='Pending','Unverified subscriber remains pending');
 foreach(glob($temporary.'/mail/*.eml') as $file){$mail=file_get_contents($file);if(str_contains($mail,'To: news@example.test')){preg_match('/newsletter_confirm.php\?token=([a-f0-9]{64})/',$mail,$m);$confirm=$m[1];preg_match('/newsletter_unsubscribe.php\?token=([a-f0-9]{64})/',$mail,$m);$unsubscribe=$m[1];}}
 check(request('newsletter','newsletter_confirm.php','POST',['csrf'=>$newsletter['csrf'],'token'=>$confirm])['code']===200,'Newsletter confirms');
 check($pdo->query('SELECT subscription_status FROM newsletter_subscriptions')->fetchColumn()==='Subscribed','Confirmed subscription persisted');
 request('newsletter','newsletter_unsubscribe.php','POST',['csrf'=>$newsletter['csrf'],'token'=>$unsubscribe]);
 check($pdo->query('SELECT subscription_status FROM newsletter_subscriptions')->fetchColumn()==='Unsubscribed','Secure unsubscribe updates existing record');
 check(request('staff','api/index.php/newsletter')['code']===403,'Newsletter management is admin only');
 check(request('admin','api/index.php/newsletter')['code']===200,'Admin sees newsletter statuses');
 check(request('donor','backend/auth_handler.php','POST',['csrf'=>$donor,'action'=>'logout'])['code']===303,'Logout completes');
 check(request('donor','api/index.php/appointments')['code']===401,'Logout destroys authenticated session');
 check(request('admin','api/index.php/users?q='.rawurlencode("' OR 1=1 --"))['json']['meta']['total']===0,'Search injection treated as literal input');
 check(request('admin','api/index.php/users','POST',array_replace($record,['email'=>'invalid-role@example.test','role_name'=>'Owner']),true,$admin)['code']===422,'Invalid role rejected');
 check(request('admin','api/index.php/users','POST',$record,true,'invalid')['code']===403,'Protected mutation rejects invalid CSRF');
 check(request('admin','api/index.php/users','POST',array_replace($record,['first_name'=>'']),true,$admin)['code']===422,'Empty required name rejected');
 check(request('admin','api/index.php/users','POST',array_replace($record,['email'=>'invalid']),true,$admin)['code']===422,'Invalid email rejected');
 check(request('anon','database/revision.sql')['code']===404,'Development router protects private SQL');
 check(request('anon','tests/integration.php')['code']===404,'Development router protects test scripts');
 require_once $root.'/includes/progress_state.php';
 check(donationProgressState([],[],false,['campaign_id'=>1],null)['steps']===[false,false,false,false,false,false],'Campaign selection without eligibility never completes progress');
 check(donationProgressState([],[],true,['campaign_id'=>1],null)['steps']===[true,true,false,false,false,false],'Saved eligible selection advances only campaign step');
 $history=[['clinical_outcome'=>'Completed']];
 check(donationProgressState([],$history,false,null,$future)['steps'][4]===true,'Completed donation retained during waiting period');
 $progress=donationProgressState([],$history,true,null,$past);
 check($progress['history']&&!$progress['steps'][4]&&$progress['steps'][0],'New cycle resets steps while keeping historical completion');
 $ref=$pdo->query('SELECT public_reference FROM appointments LIMIT 1')->fetchColumn();
 check((bool)preg_match('/^REG-[A-F0-9]{24}$/D',$ref),'Registration uses random public reference');
 check(request('staff','api/index.php/appointments?q='.urlencode($ref))['json']['meta']['total']===1,'Staff searches registration public reference');
 check(request('staff','backend/profile_handler.php','POST',['csrf'=>$staff,'action'=>'contact','contact_number'=>'09170000000','role_name'=>'Admin'])['code']===303,'Staff updates own safe contact field');
 check($pdo->query('SELECT r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE user_id='.$ids['Staff'])->fetchColumn()==='Staff','Profile action cannot elevate role');
 check(str_contains(request('anon','newsletter_confirm.php')['body'],'site-footer')&&str_contains(request('anon','newsletter_unsubscribe.php')['body'],'site-footer'),'Both newsletter pages reuse standard footer');
 $insert=$pdo->prepare("INSERT INTO inventory_transactions(inventory_id,executed_by_staff_id,transaction_type,units_transacted,transaction_notes,public_reference) VALUES(?,?,'Adjustment',0,?,?)");
 for($i=0;$i<13;$i++)$insert->execute([$unit,$ids['Staff'],'pagination-proof '.str_repeat('Long note ',30),publicReference('TXN')]);
 $p1=request('staff','api/index.php/transactions?q=pagination-proof&limit=10&page=1')['json'];
 $p2=request('staff','api/index.php/transactions?q=pagination-proof&limit=10&page=2')['json'];
 check($p1['meta']['total']===13&&$p1['meta']['pages']===2&&count($p2['data'])===3,'Filtered transaction pagination returns correct count and last page');
 check(!array_intersect(array_column($p1['data'],'transaction_id'),array_column($p2['data'],'transaction_id')),'Transaction pages contain distinct records');
 check(request('staff','api/index.php/transactions?q=pagination-proof&page=99')['json']['meta']['page']===2,'Pagination clamps beyond last page');
 check(request('staff','api/index.php/campaigns?venue=MissingVenue&status=OPEN')['json']['meta']['total']===0,'Combined location filters apply together');
 require_once $root.'/includes/mailer.php';putenv('MAIL_MODE=smtp');putenv('SMTP_USERNAME=');putenv('SMTP_PASSWORD=');
 try{sendEmail('test@example.test','Configuration test','Test');check(false,'SMTP missing configuration rejected');}
 catch(RuntimeException $e){check($e->getMessage()==='Email delivery is temporarily unavailable. Please try again later.','SMTP configuration failure is generic and safe');}
 putenv('MAIL_MODE=preview');
 check(login('freshdonor','donor@example.test')['code']===200,'Fresh donor session logs in');
 $fresh=token('freshdonor');
 $waitingPage=request('freshdonor','dashboard.php?view=eligibility')['body'];
 check(str_contains($waitingPage,'aria-label="Donation registration progress"')&&str_contains($waitingPage,'Previous journey: donation completed'),'Waiting donor can see completed journey progress');
 check(!str_contains(request('freshdonor','dashboard.php?view=profile')['body'],'aria-label="Donation registration progress"'),'Profile does not repeat workflow progress');
 $sample=request('admin','api/index.php/campaigns','POST',array_replace($campaignData,['title'=>'Selection restore test']),true,$admin)['json']['data']['campaign_id'];
 $latest=latestEligibility($pdo,$ids['Donor']);
 $pdo->prepare('UPDATE donor_campaign_selection SET campaign_id=?,eligibility_id=? WHERE user_id=?')->execute([$sample,$latest['eligibility_id'],$ids['Donor']]);
 check(login('restored','donor@example.test')['code']===200,'Independent session for saved selection');
 check(str_contains(request('restored','dashboard.php?view=eligibility')['body'],'Selected campaign: Selection restore test'),'Saved campaign is restored without prior session selection');
 check(request('admin','api/index.php/campaigns/'.$sample,'DELETE',[],true,$admin)['code']===200,'Unbooked campaign with saved selection deletes successfully');
 check((int)$pdo->query('SELECT COUNT(*) FROM donor_campaign_selection WHERE campaign_id='.$sample)->fetchColumn()===0,'Deleted campaign clears only unsubmitted selection');
 check(request('admin','api/index.php/campaigns/'.$cid,'DELETE',[],true,$admin)['code']===409,'Campaign registration history remains protected');
 $insertNotice=$pdo->prepare("INSERT INTO notifications(user_id,notification_type,subject,message_body) VALUES(?,'Appointment',?,'Notification regression test')");
 for($i=1;$i<=25;$i++)$insertNotice->execute([$ids['Donor'],'Notice test '.$i]);
 $ownNotice=(int)$pdo->lastInsertId();
 $insertNotice->execute([$ids['Staff'],'Staff private notice']);$otherNotice=(int)$pdo->lastInsertId();
 $first=request('freshdonor','dashboard.php?view=notifications')['body'];
 $second=request('freshdonor','dashboard.php?view=notifications&page=2')['body'];
 check(str_contains($first,'Notice test 25')&&!str_contains($second,'Notice test 25')&&str_contains($second,'Notice test 1</span>'),'Notifications paginate distinct records including older entries');
 check(str_contains($first,'notification-card is-unread')&&str_contains($first,'Mark as read')&&!str_contains($first,'Staff private notice'),'Notifications show unread controls only for own records');
 check(request('freshdonor','backend/notification_handler.php','POST',['csrf'=>'invalid','notification_id'=>$ownNotice])['code']===403,'Mark-read form requires CSRF');
 request('freshdonor','backend/notification_handler.php','POST',['csrf'=>$fresh,'notification_id'=>$otherNotice]);
 check((int)$pdo->query('SELECT is_read FROM notifications WHERE notification_id='.$otherNotice)->fetchColumn()===0,'Mark-read cannot modify another user notification');
 request('freshdonor','backend/notification_handler.php','POST',['csrf'=>$fresh,'notification_id'=>$ownNotice,'page'=>2]);
 check((int)$pdo->query('SELECT is_read FROM notifications WHERE notification_id='.$ownNotice)->fetchColumn()===1,'Mark-read persists own notification state');
 $pdo->exec('RENAME TABLE donor_profiles TO donor_profiles_failure_test');
 try{$failure=request('freshdonor','dashboard.php?view=eligibility');check($failure['code']===200&&str_contains($failure['body'],'Registration is temporarily unavailable.')&&!str_contains($failure['body'],'SQLSTATE'),'Eligibility query failure produces friendly dashboard fallback');}
 finally{$pdo->exec('RENAME TABLE donor_profiles_failure_test TO donor_profiles');}
 $pdo->exec('RENAME TABLE notifications TO notifications_failure_test');
 try{$failure=request('freshdonor','dashboard.php?view=notifications');check($failure['code']===200&&str_contains($failure['body'],'Notifications are temporarily unavailable.'),'Notification query failure produces friendly fallback');}
 finally{$pdo->exec('RENAME TABLE notifications_failure_test TO notifications');}
 echo "\n$checks checks passed.\n";
 require __DIR__.'/logic_audit_probes.php';
 require __DIR__.'/revision_regressions.php';
 echo "Total including audit regressions: $checks checks passed.\n";
 if(in_array('--browser', $argv,true)){
    echo 'Browser fixture: http://127.0.0.1:'.$port.'/index.php'."\n";
    echo 'Temporary accounts: admin@example.test / staff@example.test; password Safe-Demo!2026. Fixture expires in 10 minutes.'."\n";
    echo 'Stop file: '.$temporary.'/stop'."\n";
    for($i=0;$i<600&&!is_file($temporary.'/stop');$i++)sleep(1);
 }
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage()."\n");if(is_file($temporary.'/server.log'))fwrite(STDERR,substr(file_get_contents($temporary.'/server.log'),-4000));$failed=true;}
finally {
 if(is_resource($process)){proc_terminate($process);proc_close($process);}
 if(preg_match('/^hemopulse_test_[a-f0-9]{12}$/D',$name))$server->exec("DROP DATABASE IF EXISTS `$name`");
 // Remove only this suite's randomly named temporary files.
 foreach(glob($temporary.'/mail/*.eml')?:[] as $file)unlink($file);if(is_dir($temporary.'/mail'))rmdir($temporary.'/mail');
 foreach(glob($temporary.'/sessions/*')?:[] as $file)unlink($file);rmdir($temporary.'/sessions');
 foreach(glob($temporary.'/*')?:[] as $file)if(is_file($file))unlink($file);rmdir($temporary);
}
exit(isset($failed)?1:0);
