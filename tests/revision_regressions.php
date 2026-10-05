<?php
if(PHP_SAPI!=='cli'||!isset($pdo,$port,$checks)){http_response_code(404);exit;}
// Status notices: reason, atomic persistence, delivery tracking and retry behavior.
$demo=array_replace($record,['first_name'=>'Revision','last_name'=>'Donor','email'=>'revision@example.test']);
$r=request('admin','api/index.php/users','POST',$demo,true,$admin);check($r['code']===201,'Revision fixture donor created');$revisionUser=(int)$r['json']['data']['user_id'];
$restricted=array_replace($demo,['account_status'=>'Suspended','password'=>'']);
check(request('admin','api/index.php/users/'.$revisionUser,'PUT',$restricted,true,$admin)['code']===422,'Status change requires a reason');
check($pdo->query('SELECT account_status FROM users WHERE user_id='.$revisionUser)->fetchColumn()==='Active','Missing status reason rolls back account update');
$restricted['status_reason']='Demo account under administrative review';
check(request('admin','api/index.php/users/'.$revisionUser,'PUT',$restricted,true,$admin)['code']===200,'Status change with a reason succeeds');
$s=$pdo->query('SELECT * FROM account_status_notices WHERE user_id='.$revisionUser)->fetch();
check($s['new_status']==='Suspended'&&$s['delivery_status']==='Preview'&&$s['reason']===$restricted['status_reason'],'Account notice records accurate preview status and reason');
check(request('admin','api/index.php/users/'.$revisionUser,'PUT',$restricted,true,$admin)['code']===200&&(int)$pdo->query('SELECT COUNT(*) FROM account_status_notices WHERE user_id='.$revisionUser)->fetchColumn()===1,'Retrying unchanged status does not resend account notice');
check(login('revision','revision@example.test')['code']===422,'Suspended account cannot sign in');
$restore=array_replace($demo,['password'=>'','status_reason'=>'Review complete; account restored']);
check(request('admin','api/index.php/users/'.$revisionUser,'PUT',$restore,true,$admin)['code']===200,'Administrator restores account with a reason');
check(login('revision','revision@example.test')['code']===200,'Restored account can sign in');$revisionCsrf=token('revision');

// Denial retry policy is separate from a clinical deferral.
$denied=array_replace($answers,['date_of_birth'=>'2015-01-01','csrf'=>$revisionCsrf]);
check(request('revision','backend/eligibility_handler.php','POST',$denied)['code']===303,'Not Eligible screening is saved');
$screen=latestEligibility($pdo,$revisionUser);$retry=afterMonths(date('Y-m-d'),1);
check($screen['outcome']==='Not Eligible'&&$screen['retry_after']===$retry,'Denial stores one calendar-month screening retry date');
$before=(int)$pdo->query('SELECT COUNT(*) FROM eligibility_checks WHERE user_id='.$revisionUser)->fetchColumn();
request('revision','backend/eligibility_handler.php','POST',$answers+['csrf'=>$revisionCsrf]);
check((int)$pdo->query('SELECT COUNT(*) FROM eligibility_checks WHERE user_id='.$revisionUser)->fetchColumn()===$before,'Denial retry lock enforced on server');
$page=request('revision','dashboard.php?view=eligibility&tab=registration')['body'];
check(str_contains($page,$retry)&&!str_contains($page,'Please complete an eligible pre-screening today before continuing'),'Denied donor sees return date without unfinished-checker notice');
check(request('staff','api/index.php/screenings/'.$screen['eligibility_id'],'PUT',['outcome'=>'Reviewed','review_notes'=>'Please correct your birth date and submit a new assessment.'],true,$staff)['code']===200,'Staff can release administrative retry pause for reassessment');
request('revision','backend/eligibility_handler.php','POST',$answers+['csrf'=>$revisionCsrf]);
check(latestEligibility($pdo,$revisionUser)['outcome']==='Eligible','Staff-requested reassessment permits a fresh screening');

// Contact continuation uses only a secret expiring link, with CSRF and throttling.
$threadToken=null;
foreach(glob($temporary.'/mail/*.eml')?:[] as $file){$mail=file_get_contents($file);if(str_contains($mail,'To: contact@example.test')&&preg_match('/contact_thread.php\?token=([a-f0-9]{64})/',$mail,$match))$threadToken=$match[1];}
check(is_string($threadToken),'Staff reply contains private conversation link');
check(request('conversation','contact_thread.php?token='.str_repeat('a',64))['code']===404,'Conversation rejects unknown bearer token');
$conversation=request('conversation','contact_thread.php?token='.$threadToken);
check($conversation['code']===200&&str_contains($conversation['body'],'Thank you.')&&!str_contains($conversation['body'],'<script>alert(1)</script>'),'Conversation shows escaped original and team messages');
check(request('conversation','contact_thread.php','POST',['token'=>$threadToken,'csrf'=>'bad','message'=>'Follow-up question'])['code']===403,'Conversation reply requires CSRF');
$conversationCsrf=token('conversation');
check(request('conversation','contact_thread.php','POST',['token'=>$threadToken,'csrf'=>$conversationCsrf,'message'=>'Could you provide more information?'])['code']===303,'Visitor submits a conversation follow-up');
check((int)$pdo->query('SELECT COUNT(*) FROM contact_followups WHERE message_id='.$mid)->fetchColumn()===1&&$pdo->query('SELECT message_status FROM contact_messages WHERE message_id='.$mid)->fetchColumn()==='New','Follow-up persists and reopens staff inbox record');
request('conversation','contact_thread.php','POST',['token'=>$threadToken,'csrf'=>$conversationCsrf,'message'=>'Immediate repeat']);
check((int)$pdo->query('SELECT COUNT(*) FROM contact_followups WHERE message_id='.$mid)->fetchColumn()===1,'Conversation cooldown prevents repeated submission');
$inbox=request('staff','api/index.php/message-replies/'.$mid)['json'];
check(count($inbox['followups'])===1&&str_contains($inbox['followups'][0]['message_body'],'more information'),'Staff endpoint exposes visitor follow-ups');
check(request('revision','api/index.php/message-replies/'.$mid)['code']===403,'Donor cannot browse contact inbox by numeric ID');
$pdo->prepare('UPDATE contact_messages SET thread_expires_at=? WHERE message_id=?')->execute([$past.' 00:00:00',$mid]);
check(request('conversation','contact_thread.php?token='.$threadToken)['code']===404,'Conversation link expires');

// Inventory summaries distinguish processing from available stock and include volume.
$pdo->prepare("INSERT INTO blood_inventory(blood_type,units_available,volume_ml_per_unit,collection_date,expiration_date,inventory_status,public_reference) VALUES('A+',2,350,?,?,'Pending',?)")->execute([date('Y-m-d'),$future,publicReference('UNT')]);$stockId=(int)$pdo->lastInsertId();
$report=request('staff','api/index.php/reports')['json']['data'];$aPlus=array_values(array_filter($report['stock'],fn($r)=>$r['blood_type']==='A+'))[0];
check($aPlus['pending']>=2&&$aPlus['units']===0,'Overview displays pending stock separately from available stock');
check(request('staff','api/index.php/inventory/'.$stockId,'PUT',['inventory_status'=>'Available','notes'=>'Fictional test fixture; processing complete'],true,$staff)['code']===200,'Processed fixture unit released');
$report=request('staff','api/index.php/reports')['json']['data'];$aPlus=array_values(array_filter($report['stock'],fn($r)=>$r['blood_type']==='A+'))[0];
check($aPlus['units']===2&&$aPlus['available_volume_ml']===700,'Overview calculates available volume from stored measurements');
check(request('staff','api/index.php/inventory?q=A%2B')['json']['data'][0]['volume_ml_per_unit']==350,'Inventory API exposes measured volume');

// Audit log is protected, filterable and paginated, including formerly missing events.
check(request('revision','api/index.php/audit-logs')['code']===403,'Audit log denies donor access');
$one=request('staff','api/index.php/audit-logs?limit=2&page=1')['json'];$two=request('staff','api/index.php/audit-logs?limit=2&page=2')['json'];
check($one['meta']['pages']>1&&$one['data'][0]['log_id']!==$two['data'][0]['log_id'],'Audit log paginates distinct entries');
$filter=request('staff','api/index.php/audit-logs?action='.rawurlencode('Account signed in').'&actor=Revision')['json'];
check($filter['meta']['total']>=1&&$filter['data'][0]['action_performed']==='Account signed in','Audit action and actor filters combine');
check($pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action_performed='Screening submitted'")->fetchColumn()>0,'Screening submissions recorded in audit log');

// Shared layout and supplied assets are wired to rendered pages.
$profile=request('revision','dashboard.php?view=profile')['body'];$staffPage=request('staff','workspace.php?view=account')['body'];
check(str_contains($profile,'inline-contact')&&str_contains($profile,'contact our team'),'Donor account includes contact field and protected-detail guidance');
check(str_contains($staffPage,'inline-contact')&&!str_contains($staffPage,'For changes to your account details'),'Staff account shares layout without donor-only guidance');
check(str_contains(request('revision','dashboard.php?view=notifications')['body'],'M18 8a6'),'Notifications sidebar includes icon');
check(str_contains(request('contact','helpdesk.php')['body'],'id="safety"'),'Safety and Standards link has a real destination');
check(str_contains(request('contact','newsletter_unsubscribe.php?token='.$unsubscribe)['body'],'class="btn-navy"'),'Unsubscribe button uses shared design');
check(str_contains(request('contact','locations.php')['body'],'DONATION LOCATIONS'),'Locations heading uses uppercase text');

// Preserve a future pending fixture for browser cancellation checks.
$previewCampaign=request('admin','api/index.php/campaigns','POST',array_replace($campaignData,['title'=>'Cancellation preview fixture','campaign_date'=>date('Y-m-d',strtotime('+5 days'))]),true,$admin);
check($previewCampaign['code']===201,'Future cancellation fixture campaign created');
$previewBooking=array_replace($booking,['campaign_id'=>$previewCampaign['json']['data']['campaign_id'],'email'=>'revision@example.test','scheduled_time_slot'=>'08:30']);
check(request('revision','api/index.php/appointments','POST',$previewBooking,true,$revisionCsrf)['code']===201,'Staff-released screening permits a future booking');

check(request('contact','contact_thread.php?inquiry='.$mid)['code']===200,'Submitting browser can open inquiry without email delivery');
check(request('outsider','contact_thread.php?inquiry='.$mid)['code']===404,'Other browser cannot open inquiry by numeric ID');
check(str_contains(request('contact','contact.php')['body'],'Open inquiry #'.$mid),'Contact page provides submitting browser conversation link');
