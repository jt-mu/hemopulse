<?php
// Explicit sample campaigns requested for the local website. Safe to rerun.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../includes/campaigns.php';
date_default_timezone_set('Asia/Manila');
$pdo=getDBConnection();$locked=false;
try {
    $locked=(bool)$pdo->query("SELECT GET_LOCK('hemopulse_demo_campaigns',10)")->fetchColumn();
    if(!$locked)throw new RuntimeException('Another campaign setup is running. Try again.');
    $pdo->beginTransaction();$added=0;
    $samples=[
        ['Barangay Blood Donation Day','Community','Maligaya Barangay Activity Hall',7,40],
        ['Student Volunteer Blood Drive','Campus','Bayanihan College Multipurpose Hall',14,50],
        ['Corporate Volunteer Donation Day','Workplace','Pagkakaisa Business Center',21,35],
        ['Weekend Community Blood Drive','Community','Pag-asa Community Center',28,45],
    ];
    foreach($samples as [$title,$category,$venue,$days,$capacity]) {
        $date=(new DateTimeImmutable('today'))->modify('+'.$days.' days')->format('Y-m-d');
        $s=$pdo->prepare('SELECT campaign_id FROM campaigns WHERE title=? AND campaign_date=?');$s->execute([$title,$date]);
        if($s->fetchColumn())continue;
        $pdo->prepare('INSERT INTO campaign_categories(name,description) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)')->execute([$category,'Donation campaign category']);
        $s=$pdo->prepare('SELECT category_id FROM campaign_categories WHERE name=?');$s->execute([$category]);$categoryId=$s->fetchColumn();
        $pdo->prepare("INSERT INTO campaigns(title,description,location_venue,campaign_date,start_time,end_time,total_slots,available_slots,campaign_status,category_id,registration_closes_at) VALUES(?,?,?,?,'08:00:00','16:00:00',?,?,'Published',?,?)")->execute([$title,'Fictional sample event for the HemoPulse school project. Scheduled whole-blood donation appointments; final assessment by donation staff. Not an actual invitation to attend.',$venue,$date,$capacity,$capacity,$categoryId,$date.' 15:00:00']);
        $added++;
    }
    $pdo->commit();echo "Added $added campaigns.\n";
    foreach($samples as [$title]) {
        $s=$pdo->prepare('SELECT * FROM campaigns WHERE title=? ORDER BY campaign_id DESC LIMIT 1');$s->execute([$title]);$campaign=$s->fetch();
        if(!$campaign||campaignRegistrationStatus($campaign)!=='OPEN'||!campaignSlots($campaign))throw new RuntimeException('Campaign availability verification failed.');
        echo $campaign['title'].' | '.$campaign['campaign_date'].' | '.$campaign['location_venue'].' | OPEN | '.$campaign['available_slots']." slots\n";
    }
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);
} finally {
    if($locked)$pdo->query("SELECT RELEASE_LOCK('hemopulse_demo_campaigns')");
}
