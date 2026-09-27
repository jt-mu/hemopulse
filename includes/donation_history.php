<h2 class="dash-headline">My Donations</h2>
<?php if (!$dashboardError && !$registrationError) include __DIR__ . '/donation_progress.php'; ?>
<?php if ($dashboardError): ?><p class="app-notice error" role="alert"><?= h($dashboardError) ?></p>
<?php else: ?>
<details class="registration-management" open><summary>Registration references</summary>
<?php if (!$appointments): ?><p>No appointments yet. <a href="locations.php">Find a campaign</a>.</p><?php endif; ?>
<?php foreach ($appointments as $appointment): ?>
<article class="record-card">
  <h4><?= h($appointment['title']) ?></h4>
  <?php if (str_contains($appointment['title'], '(DEMO)')): ?><p><strong>Demonstration only.</strong> This fictional booking is for project testing, not a real donation appointment.</p><?php endif; ?>
  <p><?= h($appointment['campaign_date']) ?> at <?= h(substr($appointment['scheduled_time_slot'], 0, 5)) ?> · <?= h($appointment['location_venue']) ?></p>
  <p>Status: <?= h($appointment['appointment_status']) ?><?= $appointment['appointment_status'] === 'Pending' ? ' — awaiting schedule confirmation' : '' ?></p>
  <?php if ($appointment['appointment_status'] === 'Cancelled' && !empty($appointment['cancellation_reason'])): ?><p>Cancellation reason: <?= h($appointment['cancellation_reason']) ?></p><?php endif; ?>
  <p>Booking reference: <strong><?= (int)$appointment['appointment_id'] ?></strong></p>
  <p>Check-in token: <code><?= h($appointment['qr_pass_token']) ?></code></p>
</article>
<?php endforeach; ?>
</details>
<section class="donation-ledger"><h3>Donation Log &amp; History</h3>
<?php if (!$donations): ?><p>No donation records yet.</p><?php endif; ?>
<?php if ($donations): ?><div class="table-scroll"><table><thead><tr><th scope="col">Collection date</th><th scope="col">Volume (ml)</th><th scope="col">Campaign</th><th scope="col">Outcome</th></tr></thead><tbody>
<?php foreach ($donations as $donation): ?><tr><td><?= h($donation['donation_date']) ?></td><td><?= (int)$donation['volume_ml'] ?></td><td><?= h($donation['title']) ?></td><td><?= h($donation['clinical_outcome']) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<details class="registration-management"><summary>Manage registrations · search, filter and cancel</summary><?php include __DIR__ . '/donor_api_panel.php'; ?></details>
<?php endif; ?>