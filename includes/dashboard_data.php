<?php
// Anti-Cache Headers: Prevents browser from caching authenticated screens
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session Guard: If logged out, redirect immediately
if (empty($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/campaigns.php';
require_once __DIR__ . '/eligibility.php';
$requestedCampaign = filter_var($_GET['campaign_id'] ?? null, FILTER_VALIDATE_INT);
if ($requestedCampaign && $requestedCampaign > 0) $_SESSION['selected_campaign'] = $requestedCampaign;
elseif (array_key_exists('campaign_id', $_GET)) unset($_SESSION['selected_campaign']);
$appointments = []; $donations = []; $dashboardError = null;
try { $user = currentUser(); }
catch (Throwable $e) { error_log($e->getMessage()); http_response_code(503); exit('Account service is temporarily unavailable. Please try again later.'); }
if (!$user) { flash('Please log in to continue.'); redirectTo('index.php#login'); }
if (in_array($user['role_name'], ['Admin', 'Staff'], true)) redirectTo('workspace.php');
$campaigns = []; $screening = null; $registrationError = null;
$selectedCampaign = null;
$view = in_array($_GET['view'] ?? '', ['profile', 'eligibility', 'donations'], true) ? $_GET['view'] : (!empty($_SESSION['selected_campaign']) ? 'eligibility' : 'profile');
try {
    $pdo = getDBConnection();
    $screening = latestEligibility($pdo, (int)$user['user_id']);
    $campaigns = $pdo->query("SELECT * FROM campaigns WHERE campaign_status IN ('Active','Published','Closed') ORDER BY campaign_date DESC, start_time")->fetchAll();
    foreach ($campaigns as $campaign) if ((int)$campaign['campaign_id'] === (int)($_SESSION['selected_campaign'] ?? 0)) $selectedCampaign = $campaign;
} catch (Throwable $e) { error_log($e->getMessage()); $registrationError = 'Registration is temporarily unavailable. Please try again later.'; }
$registrationAllowed = canRegister($screening);
$eligibilityTab = $registrationAllowed && (($_GET['tab'] ?? '') === 'registration' || ($selectedCampaign && !isset($_GET['tab']))) ? 'registration' : 'checker';
$screeningAnswers = $screening ? json_decode($screening['answers_json'], true) : [];
$eligibilityOld = $_SESSION['eligibility_old'] ?? $screeningAnswers;
unset($_SESSION['eligibility_old']);
$registrationOld = $_SESSION['registration_old'] ?? [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare('SELECT a.*, c.title, c.campaign_date, c.location_venue FROM appointments a JOIN campaigns c ON c.campaign_id = a.campaign_id WHERE a.donor_id = ? ORDER BY c.campaign_date DESC, a.scheduled_time_slot');
    $stmt->execute([$user['user_id']]); $appointments = $stmt->fetchAll();
    $stmt = $pdo->prepare('SELECT d.donation_date, d.clinical_outcome, d.volume_ml, c.title FROM donation_records d JOIN appointments a ON a.appointment_id = d.appointment_id JOIN campaigns c ON c.campaign_id = a.campaign_id WHERE a.donor_id = ? ORDER BY d.donation_date DESC');
    $stmt->execute([$user['user_id']]); $donations = $stmt->fetchAll();
} catch (Throwable $e) { error_log($e->getMessage()); $dashboardError = 'Your appointment and donation records are temporarily unavailable.'; }
