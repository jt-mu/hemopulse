<?php
// Explicit, repeatable demonstration data. Never creates users or clinical records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Asia/Manila');
$pdo = getDBConnection();
$locked = false;
try {
    $locked = (bool)$pdo->query("SELECT GET_LOCK('hemopulse_demo_campaigns', 10)")->fetchColumn();
    if (!$locked) throw new RuntimeException('Another campaign setup is running. Try again.');
    $pdo->beginTransaction();
    $samples = [
        ['Community Blood Donation Drive', 'Community', 'Mabuhay Community Hall', 7, 30],
        ['University Blood Donation Day', 'Campus', 'San Isidro University Activity Center', 14, 40],
        ['Workplace Blood Donation Campaign', 'Workplace', 'Bayanihan Business Center', 21, 25],
    ];
    $added = 0;
    foreach ($samples as [$title, $category, $venue, $days, $capacity]) {
        $exists = $pdo->prepare('SELECT campaign_id FROM campaigns WHERE title = ?');
        $exists->execute([$title]);
        if ($exists->fetchColumn()) continue;
        $pdo->prepare('INSERT INTO campaign_categories (name,description) VALUES (?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)')->execute([$category, 'Donation campaign category']);
        $find = $pdo->prepare('SELECT category_id FROM campaign_categories WHERE name=?');
        $find->execute([$category]);
        $date = (new DateTimeImmutable('today'))->modify("+$days days")->format('Y-m-d');
        $pdo->prepare("INSERT INTO campaigns (title,description,location_venue,campaign_date,start_time,end_time,total_slots,available_slots,campaign_status,category_id,registration_closes_at) VALUES (?,?,?,?,'08:00:00','16:00:00',?,?,'Published',?,?)")->execute([
            $title, 'Join a community whole-blood donation session with scheduled appointments. Fictional prototype event; not an actual invitation to attend.',
            $venue, $date, $capacity, $capacity, $find->fetchColumn(), $date . ' 15:00:00'
        ]);
        $added++;
    }
    $pdo->commit();
    echo "Added $added demonstration campaigns. Existing campaigns and registrations were preserved.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Campaign setup failed: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($locked) $pdo->query("SELECT RELEASE_LOCK('hemopulse_demo_campaigns')");
}
