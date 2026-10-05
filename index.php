<?php require __DIR__ . '/includes/page_init.php'; require_once __DIR__.'/includes/eligibility.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HemoPulse - Blood Donation</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap-grid.min.css"><link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/app.css?v=<?= filemtime(__DIR__ . '/css/app.css') ?>">
<link rel="stylesheet" href="css/design.css?v=<?= filemtime(__DIR__ . '/css/design.css') ?>"></head>
<body>
<?php include __DIR__ . '/includes/notices.php'; ?>

<?php include __DIR__ . '/includes/auth_modal.php'; ?>
  <!-- HERO BANNER -->
  <div class="hero-banner">
    <header class="frame-nav">
      <div class="nav-links">
        <a href="index.php" class="active">Home</a>
        <a href="locations.php">Locations</a>
      </div>
      <div class="logo-badge">
        <a href="index.php"><img src="images/logo.png" alt="HemoPulse logo" onerror="this.src='images/logo.jpg'"></a>
      </div>
      <div class="nav-links">
        <a href="contact.php">Contact Us</a>
        <?php include __DIR__ . '/includes/account_menu.php'; ?>
      </div>
    </header>

    <div class="hero-inner">
      <div class="hero-left">
        <h1 class="hero-title"><span class="title-underline">Where</span><br><span class="title-underline">compassion</span><br><span class="title-underline">meets the vein.</span></h1>
        <p class="hero-subtitle">Give someone a fighting chance today.</p>
        <a href="<?= $user ? 'dashboard.php' : '#login' ?>" <?php if (!$user): ?>onclick="openAuthModal('login'); return false;"<?php endif; ?> class="btn-yellow">Donate</a>
      </div>
      <div class="hero-image-wrap">
        <img src="images/hero-group.png" alt="Where compassion meets the vein">
      </div>
    </div>
  </div>

  <!-- MAIN CONTAINER -->
  <div class="container">

    <!-- DONATION TYPES -->
    <section id="donation-types">
      <h2 class="section-heading">DONATION TYPES</h2>
      <div class="donation-types-grid">
        <div class="type-card">
          <img class="type-card-img" src="images/donation-1.jpg" alt="Whole Blood">
          <h3>Whole Blood</h3>
          <p>The standard, most flexible donation takes about 45 to 60 minutes total, with the blood draw lasting just 8 to 10 minutes. A single pint collects red cells, platelets, and plasma to support trauma victims, surgeries, and severe anemia patients. It is open to all blood types—especially O-negative—and you can donate every <?= eligibilityRules()['donation_months'] ?> months, subject to staff assessment.</p>
        </div>

        <div class="type-card">
          <img class="type-card-img" src="images/donation-2.jpg" alt="Platelets">
          <h3>Platelets</h3>
          <p>Using an automated machine, this process draws blood, collects vital clotting cells, and returns your red cells and plasma. The visit lasts about two hours—ideal for streaming a movie or relaxing—and directly aids cancer patients undergoing chemotherapy and major surgery recipients. Particularly needed from positive blood types (A+, B+, AB+, O+), platelet donation schedules must be confirmed with the collection center. HemoPulse campaign booking uses the whole-blood interval.</p>
        </div>

        <div class="type-card">
          <img class="type-card-img" src="images/donation-3.jpg" alt="Power Red">
          <h3>Power Red</h3>
          <p>This targeted donation collects a concentrated, double dose of red blood cells while returning your platelets and plasma with hydrating fluids. Taking roughly 60 to 75 minutes, it is most effective for O, A-negative, and B-negative donors to treat newborn transfusions and emergency trauma. Ask the collection center about availability and the required interval. HemoPulse campaign booking uses the whole-blood interval.</p>
        </div>
      </div>
    </section>

    <!-- ABOUT HEMOPULSE -->
    <section class="about-box">
      <h2>About HemoPulse</h2>
      <img src="images/about-img.jpg" alt="About HemoPulse">
      <div>
        <p>HemoPulse bridges the gap between willing donors and community blood drives. Our platform simplifies the entire giving journey: register in minutes, discover verified donation campaigns near you, and effortlessly monitor your personal donation history and eligibility milestones. By keeping track of live blood supply needs in your area, you always know when your specific blood type can make the greatest difference.</p>
      </div>
    </section>

    <!-- FAQS ACCORDION -->
    <section class="home-faq">
      <h2 class="section-heading text-center">FAQs</h2>
      <div class="faq-list">
<?php $rules=eligibilityRules(); foreach([
'Donation Eligibility'=>[
 'Who can donate?'=>'The preliminary age range is '.$rules['minimum_age'].'–'.$rules['maximum_age'].' years with a minimum weight of '.$rules['minimum_weight'].' kg. Minors and some other donors need additional staff assessment.',
 'How often can I donate whole blood?'=>'The interval is '.$rules['donation_months'].' months. Tattoos and piercings require a '.$rules['tattoo_months'].'-month interval and staff assessment.'],
'Campaigns & Registration'=>['Where can I find a donation drive?'=>'Browse Locations to find campaigns, hours and booking status. Sign in and complete screening before registering.'],
'Donation Process'=>['What happens after a donation?'=>'Staff record the outcome in your donation history and calculate your next eligible date. Follow the donation team’s aftercare instructions.'],
'Safety & Quality'=>['When is donated blood available?'=>'Collected units remain Pending until authorized staff record required testing and processing.']
] as $category=>$questions): ?><h3><?= h($category) ?></h3>
<?php foreach($questions as $question=>$answer): ?><details class="faq-bar"><summary><span><?= h($question) ?></span><svg class="chevron" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></summary><div class="faq-content"><?= h($answer) ?></div></details><?php endforeach; endforeach; ?>
<p>Need help using your account? Visit <a href="helpdesk.php">HemoPulse System FAQs</a>.</p>
      </div>
    </section>

  </div>

  <!-- SCRIPTS -->
  <script src="js/form-guard.js?v=<?= filemtime(__DIR__ . '/js/form-guard.js') ?>"></script>
<script src="js/validation.js?v=<?= filemtime(__DIR__ . '/js/validation.js') ?>"></script>
<script src="js/modal.js?v=<?= filemtime(__DIR__ . '/js/modal.js') ?>"></script>
  <script src="js/main.js?v=<?= filemtime(__DIR__ . '/js/main.js') ?>"></script>
  
<script src="js/navigation.js?v=<?= filemtime(__DIR__ . '/js/navigation.js') ?>"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="js/notices.js?v=<?= filemtime(__DIR__ . '/js/notices.js') ?>"></script>
<script src="js/faq.js?v=<?= filemtime(__DIR__ . '/js/faq.js') ?>"></script>
</body>
</html>
