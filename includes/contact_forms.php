<?php if(!empty($_SESSION['contact_inquiries'])): ?><p class="record-card">Your recent inquiries in this browser: <?php foreach($_SESSION['contact_inquiries'] as $inquiryId=>$submittedAt): if($submittedAt<time()-30*86400)continue; ?><a href="contact_thread.php?inquiry=<?= (int)$inquiryId ?>">Open inquiry #<?= (int)$inquiryId ?></a> <?php endforeach; ?></p><?php endif; ?>
<?php $contactOld = $_SESSION['contact_old'] ?? []; unset($_SESSION['contact_old']); ?>
<div class="get-in-touch" id="get-in-touch">
  <div class="app-toast" aria-live="polite"><?php showFlash(); ?></div>
  <div class="contact-form-wrap">
    <form class="left" action="backend/contact_handler.php" method="post">
      <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
      <div hidden aria-hidden="true"><label>Leave empty<input name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="contact-fields">
      <p class="section-title">GET IN TOUCH</p>
      <div class="form-grid">
        <div><label for="contact-name">Name</label><input id="contact-name" type="text" name="name" maxlength="100" autocomplete="name" value="<?= h($contactOld['name'] ?? '') ?>" required></div>
        <div><label for="contact-email">Email Address</label><input id="contact-email" type="email" name="email" maxlength="254" autocomplete="email" value="<?= h($contactOld['email'] ?? '') ?>" required></div>
      </div>
      <label for="contact-subject">Subject</label><input id="contact-subject" type="text" name="subject" maxlength="150" value="<?= h($contactOld['subject'] ?? '') ?>" required>
      <label for="contact-message">Message</label><textarea id="contact-message" name="message" maxlength="5000" required><?= h($contactOld['message'] ?? '') ?></textarea>
      </div>
      <button type="submit" class="btn-yellow-submit">SEND MESSAGE</button>
    </form>
    <div class="right" id="newsletter">
      <form class="newsletter-box" action="backend/newsletter_handler.php" method="post">
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <h4>OUR NEWSLETTERS</h4>
        <p>Stay updated on how every drop is saving lives.</p>
        <input id="newsletter-email" type="email" name="newsletter_email" aria-label="Email Address" placeholder="Email" maxlength="254" autocomplete="email" required>
        <button type="submit" class="btn-navy">SUBMIT</button>
      </form>
    </div>
  </div>
</div>
