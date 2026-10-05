<?php
/** One state model: current registration cycle plus separately retained donation history. */
function donationProgressState(array $appointments,array $donations,bool $eligible,?array $selection,?string $nextDate,?array $screening=null): array {
    $active=null;
    foreach($appointments as $appointment) {
        if(in_array($appointment['appointment_status'],['Pending','Confirmed','Checked-In'],true)
            && $appointment['campaign_date']>=date('Y-m-d')
            && (!$active || $appointment['appointment_id']>$active['appointment_id'])) $active=$appointment;
    }
    $history=(bool)array_filter($donations,static fn($d)=>$d['clinical_outcome']==='Completed');
    $waiting=$nextDate && $nextDate>date('Y-m-d');
    $screened=$eligible || ($active && !empty($active['eligibility_id']));
    $state=['appointment'=>$active,'history'=>$history,'waiting'=>$waiting,'steps'=>$waiting&&$history?array_fill(0,6,true):[
        (bool)$screened,(bool)($screened && ($active || $selection)),(bool)$active,
        (bool)($active && in_array($active['appointment_status'],['Confirmed','Checked-In'],true)),
        (bool)($waiting && $history),(bool)($waiting && $history)
    ]];
    $progressAppointment=$state['appointment'];
    $submitted = $progressAppointment !== null;
    $confirmed = $submitted && in_array($progressAppointment['appointment_status'], ['Confirmed','Checked-In'], true);
    $attention = !$submitted && $screening && !$eligible;
    $title = $confirmed ? 'Your appointment is confirmed.' : ($submitted ? 'Status: Awaiting staff confirmation.' : ($eligible ? 'Pre-screening complete. Choose a campaign.' : 'Complete your pre-screening.'));
    $text = $confirmed ? 'Review your campaign date and time below. Final eligibility is assessed by donation staff at the venue.' : ($submitted ? 'Your registration has been received. Please wait for staff to confirm your appointment.' : ($eligible ? 'You can continue to campaign registration today. This preliminary result does not establish clinical eligibility.' : 'Complete a current eligibility questionnaire before registering for a campaign.'));
    if ($attention) {
        $title = $screening['outcome'] === 'Eligible' ? 'A fresh pre-screening is required.' : 'Your pre-screening needs attention.';
        $text = $screening['outcome'] === 'Eligible' ? 'Your previous result is no longer current for a new registration. Please complete today’s questionnaire.' : 'Review your screening result and contact donation staff for guidance before registering.';
    }
    if($waiting){$title='Your next eligible donation date is '.$nextDate.'.';$text='You can view your history and browse campaigns while waiting. Complete a fresh screening when the interval ends.';}
    return $state+['submitted'=>$submitted,'confirmed'=>$confirmed,'attention'=>(bool)$attention,'title'=>$title,'text'=>$text];
}

