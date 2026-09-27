<?php if (!$registrationAllowed || $registrationError): ?>
<p>Complete the eligibility checker before registering.</p>
<?php else: ?>
<h3 class="dash-title">Campaign Registration Form</h3>
<form action="dashboard.php" method="get" class="campaign-select-form">
  <input type="hidden" name="view" value="eligibility"><input type="hidden" name="tab" value="registration">
  <label for="choose-campaign">Selected Campaign</label>
  <select id="choose-campaign" name="campaign_id" required>
    <option value="">Choose a campaign</option>
    <?php foreach ($campaigns as $campaign): $status = campaignRegistrationStatus($campaign); ?>
      <option value="<?= (int)$campaign['campaign_id'] ?>" <?= $selectedCampaign && $campaign['campaign_id'] === $selectedCampaign['campaign_id'] ? 'selected' : '' ?>><?= h($campaign['title']) ?> — <?= h($campaign['campaign_date']) ?> — <?= $status ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn-dash-yellow" type="submit">Choose campaign</button>
</form>
<?php if (!$campaigns): ?><p>No campaigns are available yet.</p>
<?php elseif ($selectedCampaign): $selectedStatus = campaignRegistrationStatus($selectedCampaign); ?>
<p class="campaign-status status-<?= strtolower($selectedStatus) ?>"><?= $selectedStatus ?></p>
<?php if ($selectedStatus !== 'OPEN'): ?><p class="app-notice">This campaign is <?= $selectedStatus === 'FULL' ? 'full' : 'closed for registration' ?>. Please choose another campaign.</p>
<?php else: ?>
<form action="backend/appointment_handler.php" method="post" class="registration-form">
  <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="action" value="book_slot">
  <input type="hidden" name="campaign_id" value="<?= (int)$selectedCampaign['campaign_id'] ?>">
  <h4>Personal Information</h4>
  <div class="dash-row">
    <div class="dash-field"><label for="registration-name">Full Name</label><input class="input-grey" id="registration-name" name="full_name" value="<?= h($user['first_name'] . ' ' . $user['last_name']) ?>" readonly></div>
    <div class="dash-field"><label for="registration-dob">Date of Birth</label><input class="input-grey" id="registration-dob" name="date_of_birth" type="date" value="<?= h($screeningAnswers['date_of_birth']) ?>" readonly required></div>
  </div>
  <div class="dash-row">
    <div class="dash-field"><label for="registration-contact">Contact Number</label><input class="input-grey" id="registration-contact" name="contact_number" type="tel" autocomplete="tel" maxlength="30" value="<?= h($registrationOld['contact_number'] ?? $user['contact_number']) ?>" required></div>
    <div class="dash-field"><label for="registration-email">Email Address</label><input class="input-grey" id="registration-email" name="email" type="email" autocomplete="email" maxlength="100" value="<?= h($registrationOld['email'] ?? $user['email']) ?>" required></div>
  </div>
  <h4>Donation Information</h4>
  <div class="dash-field"><label for="registration-campaign">Selected Campaign</label><input class="input-grey" id="registration-campaign" value="<?= h($selectedCampaign['title']) ?>" readonly></div>
  <div class="dash-field"><label for="registration-location">Donation Location</label><input class="input-grey" id="registration-location" value="<?= h($selectedCampaign['location_venue']) ?>" readonly></div>
  <div class="dash-row">
    <div class="dash-field"><label for="registration-date">Donation Date</label><input class="input-grey" id="registration-date" type="date" value="<?= h($selectedCampaign['campaign_date']) ?>" readonly></div>
    <div class="dash-field"><label for="registration-time">Preferred Donation Time</label><input class="input-grey" id="registration-time" type="time" name="scheduled_time_slot" min="<?= h(substr($selectedCampaign['start_time'], 0, 5)) ?>" max="<?= h(date('H:i', strtotime($selectedCampaign['end_time']) - 60)) ?>" value="<?= h($registrationOld['scheduled_time_slot'] ?? '') ?>" step="60" required></div>
  </div>
  <p>Donation hours: <?= h(substr($selectedCampaign['start_time'], 0, 5)) ?>–<?= h(substr($selectedCampaign['end_time'], 0, 5)) ?>. Your preferred time is subject to confirmation.</p>
  <label class="registration-agreement"><input type="checkbox" name="requirements_agreed" value="1" required> I agree to the blood donation requirements and understand that donation staff will assess my final eligibility.</label>
  <button class="btn-dash-yellow mt-lg" type="submit">Submit Registration</button>
</form>
<?php endif; endif; endif; ?>
