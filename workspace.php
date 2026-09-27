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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
    <title>HemoPulse — <?= $admin ? 'Administrator' : 'Staff' ?> Workspace</title>
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="css/workspace.css">
    <link rel="stylesheet" href="css/design.css?v=4">
</head>
<body data-role="<?= h($user['role_name']) ?>" data-name="<?= h($user['first_name'] . ' ' . $user['last_name']) ?>" data-email="<?= h($user['email']) ?>">
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
                    'campaigns' => 'Campaigns',
                    'appointments' => 'Registrations',
                    'inventory' => 'Blood Inventory',
                    'transactions' => 'Transactions',
                    'messages' => 'Contact Inbox',
                ];
                foreach ($navItems as $key => $label) :
                ?>
                    <a class="nav-link" href="workspace.php?view=<?= $key ?>" data-view="<?= $key ?>"><?= $label ?></a>
                <?php endforeach; ?>

                <?php if ($admin) : ?>
                    <a class="nav-link" href="workspace.php?view=users" data-view="users">Users</a>
                    <a class="nav-link" href="workspace.php?view=categories" data-view="categories">Categories</a>
                <?php endif; ?>
            </nav>

            <form class="workspace-signout" method="post" action="backend/auth_handler.php">
                <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="action" value="logout">
                <button class="nav-signout" type="submit">Sign Out</button>
            </form>

            <p class="small px-3 text-secondary">
                Clinical outcomes are recorded by authorized staff. Collected units remain reserved pending laboratory review.
            </p>
        </aside>

        <main class="workspace-content" id="main">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <p class="text-uppercase small text-secondary mb-1">Blood donation management</p>
                    <h1 id="view-title" class="h3">Overview</h1>
                </div>
                <button id="create-record" class="btn btn-warning" hidden>New record</button>
            </div>

            <div id="portal-notice" role="status" aria-live="polite"></div>

            <section id="summary" class="mb-4" aria-label="Registration summary"></section>

            <form id="filters" class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="query">Search</label>
                    <input id="query" name="q" class="form-control" maxlength="100" placeholder="Keyword">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">All statuses</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="from">From date</label>
                    <input id="from" name="from" type="date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="to">To date</label>
                    <input id="to" name="to" type="date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="sort">Sort</label>
                    <select id="sort" name="sort" class="form-select">
                        <option value="newest">Newest first</option>
                        <option value="date">Date ascending</option>
                        <option value="name">Name A–Z</option>
                    </select>
                </div>
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
                    Report date filters use donation event dates. Recent activity shows the latest actions across the system.
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
                    <h2 class="h5">Recent activities</h2>
                    <div id="activity"></div>
                </article>
            </section>

            <noscript>
                <p class="alert alert-warning">Enable JavaScript to manage records. This workspace consumes the project's REST API.</p>
            </noscript>
        </main>
    </div>

    <dialog id="editor" class="portal-dialog">
        <form id="record-form">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 id="editor-title" class="h4 mb-0">Edit record</h2>
                <button type="button" class="btn-close" id="close-editor" aria-label="Close editor"></button>
            </div>
            <div id="form-error" role="alert"></div>
            <div id="editor-fields" class="row g-3"></div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="button" id="cancel-editor" class="btn btn-outline-secondary">Cancel</button>
                <button class="btn btn-primary" type="submit" id="save-record">Save</button>
            </div>
        </form>
    </dialog>

    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/workspace.js"></script>
</body>
</html>
