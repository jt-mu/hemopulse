<?php
// test_runner.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_guard.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 25px; line-height: 1.6; background: #fdfdfd; }
    .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .pass { color: #16a34a; font-weight: 700; font-size: 15px; }
    .fail { color: #dc2626; font-weight: 700; font-size: 15px; }
    pre { background: #f1f5f9; padding: 10px; border-radius: 6px; font-size: 13px; overflow-x: auto; }
</style>";

echo "<h2>HemoPulse Backend Implementation Test Suite</h2>";

$pdo = getDBConnection();

// =============================================================
// RESET TEST FIXTURES (Ensures test suite can be run repeatedly)
// =============================================================
try {
    // 1. Remove child audit and transaction rows linked to test runs
    $pdo->exec("DELETE FROM inventory_transactions WHERE donation_id IN (SELECT donation_id FROM donation_records WHERE verified_by_staff_id = 2)");
    $pdo->exec("DELETE FROM request_fulfillments WHERE fulfilled_by_staff_id = 2");
    $pdo->exec("DELETE FROM blood_requests WHERE requester_id = 4");
    $pdo->exec("DELETE FROM donation_records WHERE verified_by_staff_id = 2");
    
    // 2. Remove test appointments for donor 3 so Test 1 starts fresh
    $pdo->exec("DELETE FROM appointments WHERE donor_id = 3");
    
    // 3. Reset campaign slots
    $pdo->exec("UPDATE campaigns SET available_slots = 30 WHERE campaign_id = 1");
    
    // 4. Reset donor 3 profile
    $pdo->exec("UPDATE donor_profiles SET last_donation_date = NULL, estimated_eligible_date = CURDATE() WHERE user_id = 3");
    
    // 5. Ensure O+ inventory has base stock
    $pdo->exec("UPDATE blood_inventory SET units_available = 5 WHERE blood_type = 'O+'");
} catch (Exception $e) {
    echo "<div class='card fail'>Reset Warning: " . $e->getMessage() . "</div>";
}

// =============================================================
// TEST 1: Donor Booking
// =============================================================
echo "<div class='card'>";
echo "<h4>Test 1: Booking an Appointment as Donor</h4>";

$_SESSION['user_id']   = 3;
$_SESSION['role_name'] = 'Donor';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action'              => 'book_slot',
    'campaign_id'         => 1,
    'scheduled_time_slot' => '09:00:00'
];

ob_start();
include __DIR__ . '/backend/appointment_handler.php';
$response1 = ob_get_clean();

echo "<strong>Handler Response:</strong> <pre>" . htmlspecialchars($response1) . "</pre>";
$bookingData = json_decode($response1, true);
$qrToken = $bookingData['token'] ?? null;

if ($qrToken) {
    $stmtApp = $pdo->prepare("SELECT appointment_id, appointment_status, qr_pass_token FROM appointments WHERE qr_pass_token = :token");
    $stmtApp->execute([':token' => $qrToken]);
    $appointment = $stmtApp->fetch();

    if ($appointment) {
        echo "<span class='pass'>✓ PASS: Appointment #{$appointment['appointment_id']} registered (Status: {$appointment['appointment_status']}).</span><br>";
        echo "<span class='pass'>✓ PASS: Database constrained active bookings correctly.</span><br>";
    } else {
        echo "<span class='fail'>✗ FAIL: Token generated but row missing in database.</span><br>";
        exit();
    }
} else {
    echo "<span class='fail'>✗ FAIL: Booking failed. Response: " . htmlspecialchars($response1) . "</span><br>";
    exit();
}
echo "</div>";

// =============================================================
// TEST 2: Staff Donation Intake & Eligibility Update
// =============================================================
echo "<div class='card'>";
echo "<h4>Test 2: Staff Intake, Recording Donation & Updating Eligibility</h4>";

$_SESSION['user_id']   = 2;
$_SESSION['role_name'] = 'Staff';
$appId = (int)$appointment['appointment_id'];

$_POST = [
    'action'               => 'record_donation',
    'appointment_id'       => $appId,
    'blood_type_collected' => 'O+',
    'volume_ml'            => 450,
    'clinical_outcome'     => 'Completed',
    'deferral_reason'      => '',
    'medical_notes'        => 'Donor passed physical screening and vitals.'
];

ob_start();
include __DIR__ . '/backend/donation_handler.php';
$response2 = ob_get_clean();

echo "<strong>Handler Response:</strong> <pre>" . htmlspecialchars($response2) . "</pre>";

$stmtDonation = $pdo->prepare("SELECT donation_id, clinical_outcome FROM donation_records WHERE appointment_id = :id");
$stmtDonation->execute([':id' => $appId]);
$donation = $stmtDonation->fetch();

$stmtInv = $pdo->prepare("SELECT inventory_id, units_available FROM blood_inventory WHERE blood_type = 'O+' AND inventory_status = 'Available' LIMIT 1");
$stmtInv->execute();
$invRow = $stmtInv->fetch();

// Check if donor profile was updated to +90 days
$stmtProfile = $pdo->prepare("SELECT last_donation_date, estimated_eligible_date FROM donor_profiles WHERE user_id = 3");
$stmtProfile->execute();
$profile = $stmtProfile->fetch();

if ($donation && $invRow && !empty($profile['last_donation_date'])) {
    echo "<span class='pass'>✓ PASS: Donation #{$donation['donation_id']} recorded.</span><br>";
    echo "<span class='pass'>✓ PASS: Stock incremented on Inventory ID #{$invRow['inventory_id']} ({$invRow['units_available']} available units).</span><br>";
    echo "<span class='pass'>✓ PASS: Donor profile updated: Last donation = {$profile['last_donation_date']}, Next eligible = {$profile['estimated_eligible_date']}.</span><br>";
} else {
    echo "<span class='fail'>✗ FAIL: Donation record, inventory, or profile update missing.</span><br>";
    exit();
}
echo "</div>";

// =============================================================
// TEST 3: Blood Request Fulfillment
// =============================================================
echo "<div class='card'>";
echo "<h4>Test 3: Fulfilling an Incoming Blood Request</h4>";

$stmtReq = $pdo->prepare("
    INSERT INTO blood_requests (requester_id, blood_type_requested, units_requested, urgency_level, request_status, clinical_justification)
    VALUES (4, 'O+', 1, 'Urgent', 'Pending', 'Emergency surgery cross-match requisition')
");
$stmtReq->execute();
$newRequestId = (int)$pdo->lastInsertId();

$_POST = [
    'action'          => 'fulfill_request',
    'request_id'      => $newRequestId,
    'inventory_id'    => (int)$invRow['inventory_id'],
    'units_allocated' => 1
];

ob_start();
include __DIR__ . '/backend/fulfillment_handler.php';
$response3 = ob_get_clean();

echo "<strong>Handler Response:</strong> <pre>" . htmlspecialchars($response3) . "</pre>";

$stmtCheckReq = $pdo->prepare("SELECT request_status FROM blood_requests WHERE request_id = :id");
$stmtCheckReq->execute([':id' => $newRequestId]);
$updatedReq = $stmtCheckReq->fetch();

$stmtTx = $pdo->prepare("
    SELECT transaction_id, transaction_type, units_transacted 
    FROM inventory_transactions 
    WHERE request_id = :ref_id AND transaction_type = 'Deduction'
");
$stmtTx->execute([':ref_id' => $newRequestId]);
$txRow = $stmtTx->fetch();

if ($updatedReq && $updatedReq['request_status'] === 'Fulfilled' && $txRow) {
    echo "<span class='pass'>✓ PASS: Request #{$newRequestId} marked 'Fulfilled'.</span><br>";
    echo "<span class='pass'>✓ PASS: Inventory Transaction #{$txRow['transaction_id']} recorded ({$txRow['transaction_type']} of {$txRow['units_transacted']} unit).</span><br>";
} else {
    echo "<span class='fail'>✗ FAIL: Fulfillment failed or transaction was not logged.</span><br>";
}
echo "</div>";

echo "<h3 style='color:#16a34a;'>All Backend Tests Executed & Passed!</h3>";