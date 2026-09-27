<?php $contactOld = $_SESSION['contact_old'] ?? []; unset($_SESSION['contact_old']); ?>
<div class="get-in-touch" id="get-in-touch">
  <div class="app-toast" aria-live="polite"><?php showFlash(); ?></div>
  <div class="contact-form-wrap">
    <form class="left" action="backend/contact_handler.php" method="post">
      <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
      <p class="section-title">GET IN TOUCH</p>
      <p>Have questions or need assistance? Fill out the form below and our team will get back to you.</p>
      <div class="form-grid">
        <div><label for="contact-name">Name</label><input id="contact-name" type="text" name="name" maxlength="100" autocomplete="name" value="<?= h($contactOld['name'] ?? '') ?>" required></div>
        <div><label for="contact-email">Email Address</label><input id="contact-email" type="email" name="email" maxlength="254" autocomplete="email" value="<?= h($contactOld['email'] ?? '') ?>" required></div>
      </div>
      <label for="contact-subject">Subject</label><input id="contact-subject" type="text" name="subject" maxlength="150" value="<?= h($contactOld['subject'] ?? '') ?>" required>
      <label for="contact-message">Message</label><textarea id="contact-message" name="message" maxlength="5000" required><?= h($contactOld['message'] ?? '') ?></textarea>
      <button type="submit" class="btn-yellow-submit">SEND MESSAGE</button>
    </form>
    <div class="right" id="newsletter">
      <form class="newsletter-box" action="backend/newsletter_handler.php" method="post">
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <h4>OUR NEWSLETTERS</h4>
        <p>Receive donation announcements, campaigns, and promotions after confirming your email.</p>
        <label for="newsletter-email">Email Address</label><input id="newsletter-email" type="email" name="newsletter_email" maxlength="254" autocomplete="email" required>
        <button type="submit" class="btn-navy">SUBSCRIBE</button>
      </form>
    </div>
  </div>
</div>
