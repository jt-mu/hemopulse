<?php require __DIR__ . '/includes/dashboard_data.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HemoPulse - Account Dashboard</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap-grid.min.css"><link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/dashboard.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/app.css">
<link rel="stylesheet" href="css/design.css?v=4"></head>
<body class="dash-page-body">

  <div class="dash-wrapper">
    
    <aside class="dash-sidebar">
      <div class="dash-logo">
        <a href="index.php">
          <img src="images/logo.png" alt="HemoPulse" onerror="this.src='images/logo.jpg'">
        </a>
      </div>

      <nav class="dash-nav">
        <a href="dashboard.php?view=profile" class="nav-pill <?= $view === 'profile' ? 'active' : '' ?>" <?= $view === 'profile' ? 'aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>
          My Account
        </a>
        <a href="dashboard.php?view=eligibility" class="nav-pill <?= $view === 'eligibility' ? 'active' : '' ?>" <?= $view === 'eligibility' ? 'aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          Eligibility
        </a>
        <a href="dashboard.php?view=donations" class="nav-pill <?= $view === 'donations' ? 'active' : '' ?>" <?= $view === 'donations' ? 'aria-current="page"' : '' ?>>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/></svg>
          My Donations
        </a>
      </nav>

      <form method="post" action="backend/auth_handler.php"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="logout"><button type="submit" class="nav-signout">Sign out</button></form>
    </aside>

   
    <main class="dash-content"><?php showFlash(); ?>

      <!-- TAB 1: PROFILE SETTINGS -->
      <section id="view-profile" class="view-panel <?= $view !== 'profile' ? 'hide' : '' ?>">
        <div class="avatar-center">
          <div class="avatar-circle">
<?php if (!empty($user['profile_image'])): ?><img class="profile-avatar" src="profile_photo.php" alt="Your profile picture"><?php else: ?>
            <svg viewBox="0 0 24 24" fill="#192A4D"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg><?php endif; ?>
          </div>
        </div>

        <?php include __DIR__ . '/includes/account_overview.php'; ?>
      </section>

      
      <!-- TAB 2: ELIGIBILITY SCREENER -->
      <section id="view-eligibility" class="view-panel <?= $view !== 'eligibility' ? 'hide' : '' ?>"><?php include __DIR__ . '/includes/eligibility_panel.php'; ?></section>
<section id="view-donations" class="view-panel <?= $view !== 'donations' ? 'hide' : '' ?>"><?php include __DIR__ . '/includes/donation_history.php'; ?></section>

    </main>
  </div>


<script src="js/donor-dashboard.js"></script><script src="js/eligibility.js"></script>

<!-- Prevent viewing cached dashboard via browser back button after logout -->
  <script>
    window.addEventListener('pageshow', function (event) {
      const isBackForward = event.persisted || 
        (window.performance && window.performance.getEntriesByType && 
         window.performance.getEntriesByType("navigation")[0]?.type === "back_forward");

      if (isBackForward) {
        window.location.replace("index.php");
      }
    });
  </script>
</body>
</html>

</body>
</html>
