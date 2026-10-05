<div class="eligibility-tabs" aria-label="Eligibility navigation">
  <a class="<?= $eligibilityTab === 'checker' ? 'active' : '' ?>" href="dashboard.php?view=eligibility&tab=checker" <?= $eligibilityTab === 'checker' ? 'aria-current="page"' : '' ?>>Blood Donation Eligibility Checker</a>
  <a class="<?= $eligibilityTab === 'registration' ? 'active' : '' ?>" href="dashboard.php?view=eligibility&tab=registration" <?= $eligibilityTab === 'registration' ? 'aria-current="page"' : '' ?>>Campaign Registration Form</a>
</div>

<?php if ($registrationError): ?>
  <p class="app-notice error" role="alert"><?= h($registrationError) ?></p>
<?php endif; ?>

<?php if ($user['role_name'] !== 'Donor'): ?>
  <p>Eligibility checking and campaign registration are available to donor accounts.</p>
<?php elseif ($eligibilityTab === 'registration'): ?>
  <?php include __DIR__ . '/registration_form.php'; ?>
<?php else: ?>
  
  <?php 
    // A dated deferral pauses new submissions; historical answers remain intact.
    $recheckDate=max($nextEligibleDate??'', $screening['retry_after']??'');
    $isIneligible = $recheckDate>date('Y-m-d');
    $isLocked = $isIneligible;
  ?>

  <p class="form-hint-note">Whole-blood interval: <?= eligibilityRules()['donation_months'] ?> months. Tattoo/piercing interval: <?= eligibilityRules()['tattoo_months'] ?> months. Final assessment is made by donation staff.</p>
  <p class="dash-subhead">Complete this pre-screening before registering. It does not establish medical eligibility; donation staff make the final assessment at the venue. Recheck on each day you register and whenever your health changes.</p>

  <?php if (!empty($_SESSION['selected_campaign'])): ?>
    <p class="guardrail-banner"><?= $selectedCampaign ? 'Selected campaign: ' . h($selectedCampaign['title']) : 'Your selected campaign is unavailable. You can choose another after completing the checker.' ?></p>
  <?php endif; ?>

  <?php if (($_GET['tab'] ?? '') === 'registration' && !$screening): ?>
    <p class="app-notice">Please complete an eligible pre-screening today before continuing to the registration form.</p>
  <?php endif; ?>

  <?php if ($screening): ?>
    <div class="record-card screening-result <?= $screening['outcome'] === 'Eligible' ? 'eligible' : ($screening['outcome'] === 'Not Eligible' ? 'ineligible' : 'review') ?>" role="status">
      <h3>Pre-screening result: <?= statusBadge($screening['outcome']) ?></h3>
      <p>Completed: <?= h($screening['completed_at']) ?></p>
      <?php if($screening['reviewed_at']): ?><p>Reviewed: <?= h($screening['reviewed_at']) ?> — <?= h($screening['review_notes']) ?></p><?php endif; ?>
      <?php if($screening['reviewed_at']): ?><details><summary>Original assessment (before staff review)</summary><?php endif; ?>
      <?php foreach (json_decode($screening['reasons_json'] ?? '[]', true) ?: [] as $reason): ?>
        <p><?= h($reason) ?></p>
      <?php endforeach; ?>
      <?php if($screening['reviewed_at']): ?></details><?php endif; ?>
      <?php if ($registrationAllowed): ?>
        <a class="btn-dash-yellow" href="dashboard.php?view=eligibility&tab=registration">Continue to campaign registration</a>
      <?php elseif ($isIneligible): ?>
        <p><strong>Waiting period:</strong> You can submit a fresh screening on <?= h($recheckDate) ?>. Contact staff if you need a review.</p>
      <?php else: ?>
        <p><?= $screening['outcome'] === 'Needs Review' ? 'Your screening is awaiting staff review. You do not need to submit it again.' : ($screening['outcome']==='Not Eligible' ? 'Your screening was not approved. Contact the team to request a review.' : 'Your previous result needs a fresh assessment for today.') ?></p>
      <?php endif; ?>
    </div>
  <?php endif; ?>


  <form action="backend/eligibility_handler.php" method="post" class="eligibility-flow">
    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">

    <p class="form-legend-note" style="margin-bottom: 1.25rem; font-size: 0.8rem; color: #4A5B79; font-weight: 500;">
      All fields marked with <span class="req-star" aria-hidden="true">*</span> are required for clinical evaluation.
    </p>

    <!-- 1. DATE OF BIRTH -->
    <div class="question-block">
      <label class="q-label" for="eligibility-dob">
        Date of Birth <span class="req-star" aria-hidden="true">*</span>
        <span class="sr-only">(required)</span>
      </label>
      <input 
        class="input-grey input-half <?= $isLocked ? 'readonly-field' : '' ?>" 
        id="eligibility-dob" 
        name="date_of_birth" 
        type="date" 
        max="<?= date('Y-m-d') ?>" 
        value="<?= h(inputText($eligibilityOld, 'date_of_birth')) ?>" 
        <?= $isLocked ? 'readonly disabled' : 'required' ?>
        aria-required="true"
      >
      <span id="err_eligibility_dob" class="field-error-hint hide"></span>
    </div>

    <!-- 2. CURRENT WEIGHT -->
    <div class="question-block">
      <label class="q-label" for="eligibility-weight">
        Current weight (kg) <span class="req-star" aria-hidden="true">*</span>
        <span class="sr-only">(required)</span>
      </label>
      <input 
        class="input-grey input-half <?= $isLocked ? 'readonly-field' : '' ?>" 
        id="eligibility-weight" 
        name="weight_kg" 
        type="number" 
        min="1" 
        max="500" 
        step="0.01" 
        placeholder="e.g. 50" 
        value="<?= h($eligibilityOld['weight_kg'] ?? '') ?>" 
        <?= $isLocked ? 'readonly disabled' : 'required' ?>
        aria-required="true"
      >
      <span id="err_eligibility_weight" class="field-error-hint hide"></span>
    </div>

    <!-- 3. CLINICAL HEALTH QUESTIONNAIRE -->
    <?php 
    $screeningQuestions = [
      'healthy'        => 'Are you currently feeling healthy?',
      'recent_illness' => 'Have you experienced fever, infection, or illness recently?',
      'medication'     => 'Are you currently taking any medication?',
      'donated_before' => 'Have you donated blood before?',
      'recent_tattoo'  => 'Have you recently received tattoos or piercings?',
      'recent_travel'  => 'Have you recently traveled outside the country?'
    ];
    ?>

    <?php foreach ($screeningQuestions as $key => $question): ?>
      <fieldset class="question-block" aria-required="true" style="border: none; padding: 0; margin-bottom: 1.25rem;">
        <legend class="q-label">
          <?= h($question) ?> <span class="req-star" aria-hidden="true">*</span>
          <span class="sr-only">(required)</span>
        </legend>
        
        <div class="radio-options">
          <?php foreach (['yes' => 'Yes', 'no' => 'No'] as $value => $label): ?>
            <label style="<?= $isLocked ? 'cursor: not-allowed; opacity: 0.7;' : '' ?>">
              <input 
                type="radio" 
                name="<?= h($key) ?>" 
                value="<?= $value ?>" 
                <?= ($eligibilityOld[$key] ?? '') === $value ? 'checked' : '' ?> 
                <?= $isLocked ? 'disabled' : 'required' ?>
              > 
              <?= $label ?>
            </label>
          <?php endforeach; ?>
        </div>

        <?php if ($key === 'donated_before' || $key === 'recent_tattoo'): 
          $dateKey = $key === 'donated_before' ? 'last_donation_date' : 'tattoo_date'; 
        ?>
          <div class="conditional-date" data-conditional-question="<?= h($key) ?>" style="margin-top: 0.6rem;">
            <label for="<?= $dateKey ?>" class="q-label" style="font-size: 0.78rem;">If yes, enter the date:</label>
            <input 
              class="input-grey input-half <?= $isLocked ? 'readonly-field' : '' ?>" 
              id="<?= $dateKey ?>" 
              name="<?= $dateKey ?>" 
              type="date" 
              max="<?= date('Y-m-d') ?>" 
              value="<?= h(inputText($eligibilityOld, $dateKey)) ?>"
              <?= $isLocked ? 'readonly disabled' : '' ?>
            >
          </div>
        <?php endif; ?>
      </fieldset>
    <?php endforeach; ?>

    <?php if (!$isLocked): ?>
      <button type="submit" class="btn-dash-yellow mt-lg">Check Eligibility</button>
    <?php else: ?>
      <button type="button" class="btn-dash-yellow mt-lg" disabled style="cursor: not-allowed; opacity: 0.5;">
        Eligibility Locked
      </button>
    <?php endif; ?>
  </form>
<?php endif; ?>
