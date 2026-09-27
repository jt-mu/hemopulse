<h2 class="dash-headline">Eligibility &amp; Campaign Registration</h2>
<div class="eligibility-tabs" aria-label="Eligibility navigation">
  <a class="<?= $eligibilityTab === 'checker' ? 'active' : '' ?>" href="dashboard.php?view=eligibility&tab=checker" <?= $eligibilityTab === 'checker' ? 'aria-current="page"' : '' ?>>Blood Donation Eligibility Checker</a>
  <a class="<?= $eligibilityTab === 'registration' ? 'active' : '' ?>" href="dashboard.php?view=eligibility&tab=registration" <?= $eligibilityTab === 'registration' ? 'aria-current="page"' : '' ?>>Campaign Registration Form</a>
</div>
<?php if ($registrationError): ?><p class="app-notice error" role="alert"><?= h($registrationError) ?></p><?php endif; ?>
<?php if ($user['role_name'] !== 'Donor'): ?>
<p>Eligibility checking and campaign registration are available to donor accounts.</p>
<?php elseif ($eligibilityTab === 'registration'): ?>
<?php include __DIR__ . '/registration_form.php'; ?>
<?php else: ?>
<p class="dash-subhead">Complete this pre-screening before registering. It does not establish medical eligibility; donation staff make the final assessment at the venue. Recheck on each day you register and whenever your health changes.</p>
<?php if (!empty($_SESSION['selected_campaign'])): ?><p class="guardrail-banner"><?= $selectedCampaign ? 'Selected campaign: ' . h($selectedCampaign['title']) : 'Your selected campaign is unavailable. You can choose another after completing the checker.' ?></p><?php endif; ?>
<?php if (($_GET['tab'] ?? '') === 'registration' && !$registrationAllowed): ?><p class="app-notice">Please complete an eligible pre-screening today before continuing to the registration form.</p><?php endif; ?>
<?php if ($screening): ?>
<div class="record-card screening-result <?= $screening['outcome'] === 'Eligible' ? 'eligible' : ($screening['outcome'] === 'Not Eligible' ? 'ineligible' : 'review') ?>" role="status">
  <h3>Pre-screening result: <?= h($screening['outcome']) ?></h3>
  <p>Completed: <?= h($screening['completed_at']) ?></p>
  <?php foreach (json_decode($screening['reasons_json'], true) as $reason): ?><p><?= h($reason) ?></p><?php endforeach; ?>
  <?php if ($registrationAllowed): ?><a class="btn-dash-yellow" href="dashboard.php?view=eligibility&tab=registration">Continue to campaign registration</a>
  <?php elseif ($screening['outcome'] !== 'Eligible'): ?><p>Please contact donation staff for guidance. You may correct your answers below.</p><?php else: ?><p>Please complete a fresh checker for today.</p><?php endif; ?>
</div>
<?php endif; ?>
<form action="backend/eligibility_handler.php" method="post" class="eligibility-flow">
  <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
  <div class="question-block"><label class="q-label" for="eligibility-dob">Date of Birth</label><input class="input-grey input-half" id="eligibility-dob" name="date_of_birth" type="date" max="<?= date('Y-m-d') ?>" value="<?= h(inputText($eligibilityOld, 'date_of_birth')) ?>" required></div>
  <div class="question-block"><label class="q-label" for="eligibility-weight">Current weight (kg)</label><input class="input-grey input-half" id="eligibility-weight" name="weight_kg" type="number" min="1" max="500" step="0.01" value="<?= h($eligibilityOld['weight_kg'] ?? '') ?>" required></div>
  <?php foreach (['healthy' => 'Are you currently feeling healthy?', 'recent_illness' => 'Have you experienced fever, infection, or illness recently?', 'medication' => 'Are you currently taking any medication?', 'donated_before' => 'Have you donated blood before?', 'recent_tattoo' => 'Have you recently received tattoos or piercings?', 'recent_travel' => 'Have you recently traveled outside the country?'] as $key => $question): ?>
  <fieldset class="question-block"><legend class="q-label"><?= h($question) ?></legend><div class="radio-options">
    <?php foreach (['yes' => 'Yes', 'no' => 'No'] as $value => $label): ?><label><input type="radio" name="<?= h($key) ?>" value="<?= $value ?>" <?= ($eligibilityOld[$key] ?? '') === $value ? 'checked' : '' ?> required> <?= $label ?></label><?php endforeach; ?>
  </div>
  <?php if ($key === 'donated_before' || $key === 'recent_tattoo'): $dateKey = $key === 'donated_before' ? 'last_donation_date' : 'tattoo_date'; ?>
    <div class="conditional-date" data-conditional-question="<?= h($key) ?>"><label for="<?= $dateKey ?>">If yes, enter the date:</label><input class="input-grey input-half" id="<?= $dateKey ?>" name="<?= $dateKey ?>" type="date" max="<?= date('Y-m-d') ?>" value="<?= h(inputText($eligibilityOld, $dateKey)) ?>"></div>
  <?php endif; ?>
  </fieldset>
  <?php endforeach; ?>
  <button type="submit" class="btn-dash-yellow mt-lg">Check Eligibility</button>
</form>
<?php endif; ?>
