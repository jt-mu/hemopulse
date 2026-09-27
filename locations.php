<?php require __DIR__ . '/includes/page_init.php'; 
require_once __DIR__ . '/includes/campaigns.php';
$campaigns = []; $campaignError = null;
try { $campaigns = getDBConnection()->query("SELECT * FROM campaigns WHERE campaign_status IN ('Active','Published','Closed') ORDER BY campaign_date DESC, start_time")->fetchAll(); }
catch (Throwable $e) { error_log($e->getMessage()); $campaignError = 'Campaigns are temporarily unavailable. Please try again later.'; }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donation Locations - HemoPulse</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap-grid.min.css"><link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/locations.css">
  <link rel="stylesheet" href="css/app.css">
<link rel="stylesheet" href="css/design.css?v=4"></head>

<body class="locations-canvas-body">
<?php include __DIR__ . '/includes/notices.php'; ?>

    <!-- NAVBAR -->
    <header class="top-nav-strip">
        <nav class="frame-nav">
        <div class="nav-links">
            <a href="index.php">Home</a>
            <a href="locations.php" class="active">Locations</a>
        </div>

        <div class="logo-badge">
            <a href="index.php">
                <img src="images/logo.png" alt="HemoPulse">
            </a>
        </div>

        <div class="nav-links">
            <a href="contact.php">Contact Us</a>

            <?php include __DIR__ . '/includes/account_menu.php'; ?>
        </div>
    </header>

    <main class="locations-main">

        <!-- HEADER -->
        <div class="loc-head-wrap">
            <h1 class="loc-title">Donation Locations</h1>
            <p class="loc-sub">Find blood donation drives near you and be part of a healthier tomorrow.</p>
        </div>

        <!-- SEARCH FILTER -->
        <?php
        $allCampaigns = $campaigns;
        $featuredCampaigns = array_values(array_filter($campaigns, static fn($event) => in_array($event['campaign_status'], ['Active','Published'], true) && $event['campaign_date'] >= date('Y-m-d')));
        usort($featuredCampaigns, static fn($a,$b) => strcmp($a['campaign_date'], $b['campaign_date']));
        if ($featuredCampaigns): ?>
        <section class="drives-block featured-drives" aria-label="Upcoming donation drives">
          <h2 class="block-title">Upcoming Drives</h2>
          <div class="grid-2x2"><?php $campaigns = array_slice($featuredCampaigns, 0, 2); include __DIR__ . '/includes/campaign_cards.php'; $campaigns = $allCampaigns; ?></div>
        </section>
        <?php endif; ?>
        <section class="search-filter-section">
            <h2 class="block-title">Find Donation Events</h2>

            <div class="search-container">
                <input type="search" id="eventSearch" aria-label="Search campaigns" placeholder="Search event or location..." oninput="filterEvents()">

                <select id="locationFilter" aria-label="Filter by venue" onchange="filterEvents()"><option value="all">All Locations</option><?php foreach (array_unique(array_column($campaigns, 'location_venue')) as $venue): ?><option value="<?= h($venue) ?>"><?= h($venue) ?></option><?php endforeach; ?></select>

                <select id="statusFilter" aria-label="Filter by booking status" onchange="filterEvents()">
                    <option value="all">All Status</option>
                    <option value="OPEN">OPEN</option>
<option value="FULL">FULL</option>
                    <option value="CLOSED">CLOSED</option>
                </select>
                <label>From event date<input id="campaign-from" type="date" onchange="filterEvents()"></label><label>To event date<input id="campaign-to" type="date" onchange="filterEvents()"></label><label>Sort<select id="campaign-sort" onchange="filterEvents()"><option value="date">Event date</option><option value="newest">Latest events</option><option value="name">Name A–Z</option></select></label>
            </div>
        </section>

        <!-- UPCOMING EVENTS -->
        <section class="drives-block">
            <h2 class="block-title upcoming-title-bar">Donation Campaigns</h2>

            <div class="drives-row grid-2x2" id="eventCards"><?php include __DIR__ . '/includes/campaign_cards.php'; ?></div>
<p id="noEvents" role="status" <?= $campaigns ? 'hidden' : '' ?>><?= h($campaignError ?? 'No campaigns are available yet.') ?></p>
<div class="donor-pagination"><button type="button" id="campaign-prev">Previous</button><span id="campaign-page" aria-live="polite"></span><button type="button" id="campaign-next">Next</button></div>
        </section>

        <!-- EVENT TABLE -->
        <section class="schedule-overview-wrapper">
            <div class="schedule-white-box">
                <div class="schedule-pill-heading">EVENT SCHEDULE OVERVIEW</div>

                <div class="table-scroll">
                    <table class="figma-table">
                        <thead>
                            <tr>
                                <th>Event Title</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Venue</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="eventTable"><?php include __DIR__ . '/includes/campaign_rows.php'; ?></tbody>
                    </table>
                </div>
            </div>
        </section>

    </main>

    <?php include __DIR__ . '/includes/auth_modal.php'; ?>
<script src="js/modal.js"></script>
    <script src="js/search.js"></script>

<script src="js/navigation.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>

</html>
