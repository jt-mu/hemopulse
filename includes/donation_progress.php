<?php
// Display recorded workflow steps; these are not clinical certification.
$progressAppointment = null;
foreach ($appointments as $candidate) {
    if (in_array($candidate['appointment_status'], ['Pending','Confirmed','Checked-In'], true)
        && $candidate['campaign_date'] >= date('Y-m-d')
        && (!$progressAppointment || $candidate['appointment_id'] > $progressAppointment['appointment_id'])) $progressAppointment = $candidate;
}
$submitted = $progressAppointment !== null;
$confirmed = $submitted && in_array($progressAppointment['appointment_status'], ['Confirmed','Checked-In'], true);
$screened = $registrationAllowed || ($submitted && !str_contains($progressAppointment['title'], '(DEMO)'));
$attention = !$submitted && $screening && !$registrationAllowed;
$progressTitle = $confirmed ? 'Your appointment is confirmed.' : ($submitted ? 'Status: Awaiting staff confirmation.' : ($registrationAllowed ? 'Pre-screening complete. Choose a campaign.' : 'Complete your pre-screening.'));
$progressText = $confirmed ? 'Review your campaign date and time below. Final eligibility is assessed by donation staff at the venue.' : ($submitted ? 'Your registration has been received. Please wait for staff to confirm your appointment.' : ($registrationAllowed ? 'You can continue to campaign registration today. This preliminary result does not establish clinical eligibility.' : 'Complete a current eligibility questionnaire before registering for a campaign.'));
if ($attention) {
    $progressTitle = $screening['outcome'] === 'Eligible' ? 'A fresh pre-screening is required.' : 'Your pre-screening needs attention.';
    $progressText = $screening['outcome'] === 'Eligible' ? 'Your previous result is no longer current for a new registration. Please complete today’s questionnaire.' : 'Review your screening result and contact donation staff for guidance before registering.';
}
?>
<section class="donation-progress" aria-label="Donation registration progress">
  <ol class="progress-steps">
    <?php foreach ([[$screened,'Pre-screening'],[$submitted,'Registration submitted'],[$confirmed,'Appointment confirmed']] as $index => [$complete,$label]): ?>
    <li class="<?= $complete ? 'step-complete' : '' ?>"><span class="step-dot" aria-hidden="true"><?= $complete ? '✓' : $index + 1 ?></span><span><?= h($label) ?></span><span class="visually-hidden">: <?= $complete ? 'complete' : 'not complete' ?></span></li>
    <?php endforeach; ?>
  </ol>
  <div class="progress-message <?= $attention ? 'attention' : '' ?>" role="status">
    <h3><?= h($progressTitle) ?></h3><p><?= h($progressText) ?></p>
    <?php if (!$submitted): ?><a class="btn-dash-yellow" href="<?= $registrationAllowed ? 'locations.php' : 'dashboard.php?view=eligibility' ?>"><?= $registrationAllowed ? 'Find a campaign' : 'Continue to pre-screening' ?></a><?php endif; ?>
  </div>
</section>
