<?php
// Explicit demo setup: no consent, screening or clinical records are fabricated.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
$db = getDBConnection();
try {
    $db->beginTransaction();
    $campaign = $db->query("SELECT * FROM campaigns WHERE title='Community Donation Drive (DEMO)' AND campaign_date>=CURRENT_DATE AND campaign_status='Published' ORDER BY campaign_id LIMIT 1 FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
    if (!$campaign) throw new RuntimeException('Run seed_campaigns.php first; a future demo campaign is required.');
    $donors = $db->query("SELECT user_id FROM users JOIN roles USING(role_id) WHERE role_name='Donor' AND account_status='Active'")->fetchAll(PDO::FETCH_COLUMN);
    $added = 0;
    foreach ($donors as $donor) {
        if ($added >= $campaign['available_slots']) break;
        $existing = $db->prepare('SELECT appointment_id FROM appointments WHERE donor_id=? AND campaign_id=?');
        $existing->execute([$donor,$campaign['campaign_id']]);
        if ($existing->fetchColumn()) continue;
        $db->prepare("INSERT INTO appointments(donor_id,campaign_id,scheduled_time_slot,appointment_status,qr_pass_token) VALUES (?,?,'09:00:00','Pending',?)")->execute([$donor,$campaign['campaign_id'],bin2hex(random_bytes(24))]);
        $added++;
    }
    $db->prepare('UPDATE campaigns SET available_slots=available_slots-? WHERE campaign_id=?')->execute([$added,$campaign['campaign_id']]);
    $db->commit();
    echo "Added $added clearly labeled demo registrations. Existing bookings preserved. No clinical records created.\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR,$e->getMessage()."\n"); exit(1);
}
