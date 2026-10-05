<?php
require_once __DIR__ . '/includes/bootstrap.php';

try {
    $user = currentUser();
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(503);
    exit('Account service is temporarily unavailable.');
}

if (!$user) {
    redirectTo('index.php#login');
}

if (!in_array($user['role_name'], ['Admin', 'Staff'], true)) {
    http_response_code(403);
    exit('This workspace is for authorized staff.');
}

$admin = $user['role_name'] === 'Admin';
$workspaceIcons = [
 'account'=>'<circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>',
 'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
 'audit-logs'=>'<path d="M9 5H5v16h14V5h-4"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M8 11h8M8 15h8"/>',
 'campaigns'=>'<path d="m3 11 18-7v16L3 13zM7 14l2 7h4l-2-8"/>',
 'appointments'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18m-13 5 2 2 4-4"/>',
 'screenings'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5"/>',
 'inventory'=>'<path d="M12 3C9 7 5 11 5 15a7 7 0 0 0 14 0c0-4-4-8-7-12zM9 15h6M12 12v6"/>',
 'transactions'=>'<path d="M3 7h18m-4-4 4 4-4 4M21 17H3m4-4-4 4 4 4"/>',
 'messages'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
 'newsletter'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h4M7 16h10M15 12h2"/>',
 'users'=>'<circle cx="9" cy="7" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 4a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5"/>',
 'categories'=>'<path d="M3 3h7l11 11-7 7L3 10z"/><circle cx="7" cy="7" r="1"/>',
 'logout'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
];
function workspaceIcon(string $name): string {
 global $workspaceIcons;
 return '<svg class="workspace-nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$workspaceIcons[$name].'</svg>';
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
    <title>HemoPulse — <?= $admin ? 'Administrator' : 'Staff' ?> Workspace</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/workspace.css?v=<?= filemtime(__DIR__ . '/css/workspace.css') ?>">
    <link rel="stylesheet" href="css/design.css?v=<?= filemtime(__DIR__ . '/css/design.css') ?>">
</head>
<body class="workspace-page" data-role="<?= h($user['role_name']) ?>" data-name="<?= h($user['first_name'] . ' ' . $user['last_name']) ?>" data-email="<?= h($user['email']) ?>">
    <a class="visually-hidden-focusable" href="#main">Skip to main content</a>

    <div class="workspace-shell">
        <aside class="portal-sidebar">
            <div class="dash-logo">
                <a href="index.php"><img src="images/logo.png" alt="HemoPulse"></a>
            </div>

            <p class="workspace-identity">
                <?= $admin ? 'Administrator' : 'Staff' ?><br>
                <?= h($user['first_name'] . ' ' . $user['last_name']) ?>
            </p>

            <nav aria-label="Workspace navigation" class="nav flex-lg-column flex-row gap-1 py-3">
                <?php
                $navItems = [
                    'account' => 'My Account',
                    'dashboard' => 'Overview',
                    'audit-logs' => 'Audit Log',
                    'campaigns' => 'Campaigns',
                    'appointments' => 'Registrations',
                    'screenings' => 'Eligibility Reviews',
                    'inventory' => 'Blood Inventory',
                    'transactions' => 'Transactions',
                    'messages' => 'Contact Inbox',
                ];
                foreach ($navItems as $key => $label) :
                ?>
                    <a class="nav-link" href="workspace.php?view=<?= $key ?>" data-view="<?= $key ?>"><?= workspaceIcon($key) ?><span><?= $label ?></span></a>
                <?php endforeach; ?>

                <?php if ($admin) : ?>
                    <a class="nav-link" href="workspace.php?view=newsletter" data-view="newsletter"><?= workspaceIcon('newsletter') ?><span>Newsletter</span></a>
                    <a class="nav-link" href="workspace.php?view=users" data-view="users"><?= workspaceIcon('users') ?><span>Users</span></a>
                    <a class="nav-link" href="workspace.php?view=categories" data-view="categories"><?= workspaceIcon('categories') ?><span>Categories</span></a>
                <?php endif; ?>
            </nav>

            <form class="workspace-signout" method="post" action="backend/auth_handler.php">
                <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="nav-signout" type="submit"><?= workspaceIcon('logout') ?><span>Sign out</span></button>
            </form>

        </aside>

        <main class="workspace-content" id="main">
            <header class="workspace-page-header">
                <div>
                    <h1 id="view-title">Overview</h1>
                </div>
                <button id="create-record" class="btn btn-warning" hidden>New record</button>
            </header>

            <?php showFlash(); ?>
            <div id="portal-notice" role="status" aria-live="polite"></div>

            <section id="summary" class="mb-4" aria-label="Registration summary"></section>

            <form id="filters" class="row g-2 mb-3">
                <div class="col-md-4 filter-field">
                    <label class="form-label" for="query">Search</label>
                    <input id="query" name="q" class="form-control" maxlength="100" placeholder="Keyword">
                </div>
                <div class="col-md-2 filter-field">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                    </select>
                </div>
                <div class="col-md-2 filter-field">
                    <label class="form-label" for="from">From date</label>
                    <input id="from" name="from" type="date" class="form-control">
                </div>
                <div class="col-md-2 filter-field">
                    <label class="form-label" for="to">To date</label>
                    <input id="to" name="to" type="date" class="form-control">
                </div>
                <div class="col-md-2 filter-field">
                    <label class="form-label" for="sort">Sort</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="newest">Newest first</option>
                        <option value="date">Date ascending</option>
                        <option value="name">Name A–Z</option>
                    </select>
                </div>
                <div id="audit-extra" class="col-12 audit-filters" hidden><label>Actor name<input id="audit-actor" name="actor" type="text" maxlength="100" class="form-control" placeholder="e.g. Staff Test"></label><label>Exact action<input id="audit-action" name="action" class="form-control" maxlength="255" placeholder="e.g. Registration Confirmed"></label></div>
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Apply filters</button>
                    <button class="btn btn-outline-secondary" type="reset">Reset</button>
                </div>
            </form>

            <div id="records" aria-live="polite"></div>

            <nav class="d-flex align-items-center gap-3 mt-3" id="pagination" aria-label="Results pages">
                <button class="btn btn-outline-primary" id="previous">Previous</button>
                <span id="page-info"></span>
                <button class="btn btn-outline-primary" id="next">Next</button>
            </nav>

            <section id="report" hidden>
                <div class="d-flex gap-2 mb-3">
                    <a id="export-report" class="btn btn-primary" href="api/index.php/reports?format=csv">Download CSV</a>
                    <button id="print-report" class="btn btn-outline-primary">Print / Save PDF</button>
                </div>
                <p class="small text-secondary">
                    Report date filters use donation event dates. Open Audit Log for searchable, paginated actions across the system.
                </p>
                <div class="row g-3">
                    <div class="col-xl-7">
                        <article class="card p-3">
                            <h2 class="h5">Monthly registrations</h2>
                            <canvas id="monthly-chart" height="230" role="img" aria-label="Monthly registration counts; data table follows"></canvas>
                            <div id="monthly-data"></div>
                        </article>
                    </div>
                    <div class="col-xl-5">
                        <article class="card p-3">
                            <h2 class="h5">Most requested campaigns</h2>
                            <div id="popular"></div>
                        </article>
                    </div>
                </div>
                <article class="card p-3 mt-3">
                    <h2 class="h5">Audit log preview</h2>
                    <div id="activity"></div><a href="workspace.php?view=audit-logs">View complete Audit Log</a>
                </article>
            </section>

            <noscript>
                <p class="alert alert-warning">Enable JavaScript to manage records. This workspace consumes the project's REST API.</p>
            </noscript>
        </main>
    </div>

    <template id="staff-account-template"><article class="workspace-account">
    <?php if(!empty($user['profile_image'])): ?><img class="staff-avatar" src="profile_photo.php" alt="Your profile picture"><?php endif; ?>
    <?php include __DIR__.'/includes/account_overview.php'; ?></article></template>
    <dialog id="editor" class="portal-dialog">
        <form id="record-form">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 id="editor-title" class="h4 mb-0">Edit record</h2>
                <button type="button" class="btn-close" id="close-editor" aria-label="Close editor"></button>
            </div>
            <div id="form-error" role="alert"></div>
            <div id="editor-fields" class="row g-3"></div>
            <div class="editor-actions">
                <button type="button" id="cancel-editor" class="btn btn-outline-secondary">Cancel</button>
                <button class="btn btn-primary" type="submit" id="save-record">Save</button>
            </div>
        </form>
    </dialog>

    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/validation.js?v=<?= filemtime(__DIR__ . '/js/validation.js') ?>"></script>
<script src="js/notices.js?v=<?= filemtime(__DIR__ . '/js/notices.js') ?>"></script>
<script src="js/cancellation.js"></script>
<script src="js/workspace.js?v=<?= filemtime(__DIR__ . '/js/workspace.js') ?>"></script>
</body>
</html>
