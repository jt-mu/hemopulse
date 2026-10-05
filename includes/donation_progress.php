<?php
// Display recorded workflow steps; these are not clinical certification.
require_once __DIR__.'/progress_state.php';
$progress=donationProgressState($appointments,$donations,$registrationAllowed,$selection,$nextEligibleDate,$screening);
$progressAppointment=$progress['appointment'];$submitted=$progress['submitted'];$attention=$progress['attention'];
$completedDonations=$progress['history'];$waiting=$progress['waiting'];$progressTitle=$progress['title'];$progressText=$progress['text'];
?>
<section class="donation-progress" aria-label="Donation registration progress">
  <?php if($nextEligibleDate): ?><p><strong>Next eligible donation:</strong> <?= h($nextEligibleDate) ?></p><?php endif; ?>
  <?php if($completedDonations): ?><p>Previous journey: donation completed and saved in <a href="dashboard.php?view=donations">My Donations</a>. <?= !$waiting ? 'The steps below describe your new registration cycle.' : '' ?></p><?php endif; ?>
  <?php if($progressAppointment): ?><p>Registration: <?= h($progressAppointment['appointment_status']) ?> · Appointment: <?= h($progressAppointment['campaign_date'].' '.$progressAppointment['scheduled_time_slot']) ?></p><?php endif; ?>
  <?php if($waiting&&$progress['history']): ?><h3>Last completed journey</h3><?php endif; ?>
  <ol class="progress-steps">
    <?php foreach (array_map(null,$progress['steps'],['Eligibility Check','Select Campaign','Registration','Appointment','Donation','Donation History']) as $index => [$complete,$label]): ?>
    <li class="<?= $complete ? 'step-complete' : '' ?>"><span class="step-dot" aria-hidden="true"><?= $complete ? '✓' : $index + 1 ?></span><span><?= h($label) ?></span><span class="visually-hidden">: <?= $complete ? 'complete' : 'not complete' ?></span></li>
    <?php endforeach; ?>
  </ol>
  <div class="progress-message <?= $attention ? 'attention' : '' ?>" role="status">
    <h3><?= h($progressTitle) ?></h3><p><?= h($progressText) ?></p>
    <?php if (!$submitted): ?><a class="btn-dash-yellow" href="<?= $registrationAllowed||$waiting ? 'locations.php' : 'dashboard.php?view=eligibility' ?>"><?= $registrationAllowed||$waiting ? 'Find a campaign' : 'Continue to pre-screening' ?></a><?php endif; ?>
  </div>
</section>
