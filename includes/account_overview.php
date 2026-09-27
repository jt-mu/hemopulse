<form class="profile-upload" action="backend/profile_handler.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
<label for="profile-picture">Change profile picture</label><input type="file" id="profile-picture" name="profile_picture" accept="image/jpeg,image/png,image/webp" required aria-describedby="picture-help">
<p id="picture-help">JPEG, PNG or WebP. Maximum 2 MB and 4096 &times; 4096 pixels.</p><button class="btn-dash-yellow" type="submit">Upload picture</button>
</form>
<h3 class="dash-title">Account Overview</h3>
<p class="dash-subtitle">Welcome, <?= h($user['first_name']) ?>. Your registered account details are shown below.</p>
<div class="dash-row">
  <div class="dash-field"><label for="account-id">Account Reference ID</label><input id="account-id" class="input-grey" readonly value="<?= (int)$user['user_id'] ?>"></div>
  <div class="dash-field"><label for="account-email">Registered Email</label><input id="account-email" class="input-grey" readonly value="<?= h($user['email']) ?>"></div>
</div>
<div class="dash-row">
  <div class="dash-field"><label for="first-name">First Name</label><input id="first-name" class="input-grey" readonly value="<?= h($user['first_name']) ?>"></div>
  <div class="dash-field"><label for="last-name">Last Name</label><input id="last-name" class="input-grey" readonly value="<?= h($user['last_name']) ?>"></div>
</div>
<div class="dash-row"><div class="dash-field"><label for="middle-name">Middle name</label><input id="middle-name" class="input-grey" readonly value="<?= h($user['middle_name'] ?? '') ?>"></div></div>
<p>Account type: <?= h($user['role_name']) ?></p>
<div class="guardrail-banner">Account registration does not establish clinical eligibility. Screening is completed by donation staff.</div>
<p>For changes to your account details, <a href="contact.php">contact our team</a>.</p>
<?php if ($user['role_name'] === 'Donor'): ?><div class="donation-actions"><a class="btn-dash-yellow" href="dashboard.php?view=eligibility">Check eligibility</a><a class="btn-dash-yellow" href="locations.php">Find a campaign</a></div><?php endif; ?>
