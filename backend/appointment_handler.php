<?php
// backend/appointment_handler.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

// Only donors can execute booking actions
authorizeRoles(['Donor']);

// Check if action is specified (accepts either real POST requests or simulated test calls)
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'book_slot') {
    $donorId    = (int)($_SESSION['user_id'] ?? 0);
    $campaignId = (int)($_POST['campaign_id'] ?? 0);
    $timeSlot   = trim($_POST['scheduled_time_slot'] ?? '');

    if ($donorId <= 0 || $campaignId <= 0 || empty($timeSlot)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid booking data provided.']);
        exit();
    }

    $pdo = getDBConnection();

    try {
        $pdo->beginTransaction();

        // Lock the campaign row to prevent race conditions on available slots
        $stmtCheck = $pdo->prepare("SELECT available_slots, campaign_status FROM campaigns WHERE campaign_id = :id FOR UPDATE");
        $stmtCheck->execute([':id' => $campaignId]);
        $campaign = $stmtCheck->fetch();

        if (!$campaign) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Campaign not found.']);
            exit();
        }

        if ($campaign['campaign_status'] !== 'Active') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Campaign is not currently active.']);
            exit();
        }

        if ((int)$campaign['available_slots'] <= 0) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'No slots available for this campaign.']);
            exit();
        }

        // Generate a cryptographically secure token for the QR Donor Pass
        $qrPassToken = bin2hex(random_bytes(16));

        // Create the appointment record
        $stmtInsert = $pdo->prepare("
            INSERT INTO appointments (donor_id, campaign_id, scheduled_time_slot, appointment_status, qr_pass_token)
            VALUES (:donor_id, :campaign_id, :time_slot, 'Confirmed', :token)
        ");
        $stmtInsert->execute([
            ':donor_id'    => $donorId,
            ':campaign_id' => $campaignId,
            ':time_slot'   => $timeSlot,
            ':token'       => $qrPassToken
        ]);

        // Decrement open slot inventory
        $stmtUpdate = $pdo->prepare("UPDATE campaigns SET available_slots = available_slots - 1 WHERE campaign_id = :id");
        $stmtUpdate->execute([':id' => $campaignId]);

        $pdo->commit();
        echo json_encode([
            'status'  => 'success',
            'message' => 'Appointment successfully booked.',
            'token'   => $qrPassToken
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Booking Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing action.']);
}