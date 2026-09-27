<?php
// includes/registration_form.php - Campaign Registration Form with Complete Validations
if (!$registrationAllowed || $registrationError): ?>
  <p class="app-notice">Complete the eligibility checker before registering.</p>
<?php else: ?>

  <!-- 1. CAMPAIGN SELECTOR FORM (GET) -->
  <h3 class="dash-title">Campaign Registration Form</h3>
  <form action="dashboard.php" method="get" class="campaign-select-form" style="margin-bottom: 1.5rem;">
    <input type="hidden" name="view" value="eligibility">
    <input type="hidden" name="tab" value="registration">
    
    <div class="dash-field" style="margin-bottom: 0.75rem;">
      <label for="choose-campaign">Selected Campaign <span class="req-star">*</span></label>
      <select id="choose-campaign" name="campaign_id" class="input-grey" required onchange="this.form.submit()">
        <option value="">Choose a campaign</option>
        <?php foreach ($campaigns as $campaign): $status = campaignRegistrationStatus($campaign); ?>
          <option value="<?= (int)$campaign['campaign_id'] ?>" <?= $selectedCampaign && $campaign['campaign_id'] === $selectedCampaign['campaign_id'] ? 'selected' : '' ?>>
            <?= h($campaign['title']) ?> — <?= h($campaign['campaign_date']) ?> — <?= $status ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <?php if (!$campaigns): ?>
    <p>No campaigns are available yet.</p>
  <?php elseif ($selectedCampaign): $selectedStatus = campaignRegistrationStatus($selectedCampaign); ?>
    
    <?php if ($selectedStatus !== 'OPEN'): ?>
      <p class="app-notice error" style="margin-bottom: 1.5rem;">
        This campaign is <?= $selectedStatus === 'FULL' ? 'full' : 'closed for registration' ?>. Please choose another campaign.
      </p>
    <?php else: ?>

      <!-- 2. APPOINTMENT BOOKING FORM (POSTS TO appointment_handler.php) -->
      <form id="campaignRegForm" class="campaign-reg-form" action="backend/appointment_handler.php" method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="book_slot">
        <input type="hidden" name="campaign_id" value="<?= (int)$selectedCampaign['campaign_id'] ?>">

        <!-- SECTION 1: PERSONAL INFORMATION -->
        <h4 class="form-section-title">Personal Information</h4>

        <div class="dash-row">
          <div class="dash-field">
            <label for="regFullName">Full Name <span class="req-star">*</span></label>
            <input 
              type="text" 
              id="regFullName" 
              name="full_name" 
              class="input-grey readonly-field" 
              value="<?= h(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>" 
              readonly 
              required
            >
            <span id="err_full_name" class="field-error-hint hide"></span>
          </div>

          <div class="dash-field">
            <label for="regDob">Date of Birth <span class="req-star">*</span></label>
            <input 
              type="date" 
              id="regDob" 
              name="date_of_birth" 
              class="input-grey readonly-field" 
              value="<?= h($screeningAnswers['date_of_birth'] ?? $user['date_of_birth'] ?? '1981-06-10') ?>" 
              readonly 
              required
            >
            <span id="err_date_of_birth" class="field-error-hint hide"></span>
          </div>
        </div>

        <div class="dash-row">
          <div class="dash-field">
            <label for="regContact">Contact Number <span class="req-star">*</span></label>
            <input 
              type="tel" 
              id="regContact" 
              name="contact_number" 
              class="input-grey" 
              placeholder="09171234567" 
              maxlength="11" 
              inputmode="numeric" 
              autocomplete="tel" 
              oninput="this.value = this.value.replace(/[^0-9]/g, '')"
              value="<?= h($registrationOld['contact_number'] ?? $user['contact_number'] ?? '') ?>" 
              required
            >
            <span id="err_contact_number" class="field-error-hint hide"></span>
          </div>

          <div class="dash-field">
            <label for="regEmail">Email Address <span class="req-star">*</span></label>
            <input 
              type="email" 
              id="regEmail" 
              name="email" 
              class="input-grey" 
              value="<?= h($registrationOld['email'] ?? $user['email'] ?? '') ?>" 
              required
            >
            <span id="err_email" class="field-error-hint hide"></span>
          </div>
        </div>

        <!-- SECTION 2: DONATION INFORMATION -->
        <h4 class="form-section-title" style="margin-top: 1.5rem;">Donation Information</h4>

        <div class="dash-field mb-md">
          <label for="regCampaignTitle">Selected Campaign</label>
          <input 
            type="text" 
            id="regCampaignTitle" 
            class="input-grey readonly-field" 
            value="<?= h($selectedCampaign['title']) ?>" 
            readonly
          >
        </div>

        <div class="dash-field mb-md">
          <label for="regLocation">Donation Location</label>
          <input 
            type="text" 
            id="regLocation" 
            class="input-grey readonly-field" 
            value="<?= h($selectedCampaign['location_venue']) ?>" 
            readonly
          >
        </div>

        <div class="dash-row">
          <div class="dash-field">
            <label for="regDonationDate">Donation Date <span class="req-star">*</span></label>
            <input 
              type="date" 
              id="regDonationDate" 
              name="donation_date" 
              class="input-grey readonly-field" 
              value="<?= h($selectedCampaign['campaign_date']) ?>" 
              readonly 
              required
            >
            <span id="err_donation_date" class="field-error-hint hide"></span>
          </div>

          <div class="dash-field">
            <label for="regPreferredTime">Preferred Donation Time <span class="req-star">*</span></label>
            <select id="regPreferredTime" name="scheduled_time_slot" class="input-grey" required>
              <option value="" disabled selected>Select a time slot (08:00 AM – 04:00 PM)</option>
              <option value="08:00:00">08:00 AM</option>
              <option value="08:30:00">08:30 AM</option>
              <option value="09:00:00">09:00 AM</option>
              <option value="09:30:00">09:30 AM</option>
              <option value="10:00:00">10:00 AM</option>
              <option value="10:30:00">10:30 AM</option>
              <option value="11:00:00">11:00 AM</option>
              <option value="11:30:00">11:30 AM</option>
              <option value="12:00:00">12:00 PM</option>
              <option value="12:30:00">12:30 PM</option>
              <option value="13:00:00">01:00 PM</option>
              <option value="13:30:00">01:30 PM</option>
              <option value="14:00:00">02:00 PM</option>
              <option value="14:30:00">02:30 PM</option>
              <option value="15:00:00">03:00 PM</option>
              <option value="15:30:00">03:30 PM</option>
              <option value="16:00:00">04:00 PM</option>
            </select>
            <span id="err_preferred_time" class="field-error-hint hide"></span>
          </div>
        </div>

        <p class="form-hint-note">
          Donation hours: 08:00–16:00. Your preferred time is subject to confirmation.
        </p>

        <!-- SECTION 3: CONSENT & SUBMIT -->
        <div class="terms-consent-wrap">
          <input type="checkbox" id="regTermsCheck" name="requirements_agreed" value="1" required>
          <label for="regTermsCheck">
            I agree to the blood donation requirements and understand that donation staff will assess my final eligibility. <span class="req-star">*</span>
          </label>
        </div>

        <button type="submit" id="btnSubmitCampaignReg" class="btn-dash-yellow btn-submit-lg" disabled>
          Submit Registration
        </button>
      </form>

    <?php endif; ?>
  <?php endif; ?>
<?php endif; ?>