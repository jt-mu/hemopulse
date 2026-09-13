<?php
// backend/donation_handler.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

authorizeRoles(['Staff', 'Admin']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'record_donation') {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    $staffId       = (int)($_SESSION['user_id'] ?? 0);
    $bloodType     = trim($_POST['blood_type_collected'] ?? '');
    $volumeMl      = (int)($_POST['volume_ml'] ?? 0);
    $outcome       = trim($_POST['clinical_outcome'] ?? ''); // 'Completed' or 'Deferred'
    $deferralDesc  = trim($_POST['deferral_reason'] ?? '');
    $medicalNotes  = trim($_POST['medical_notes'] ?? '');

    if ($appointmentId <= 0 || empty($bloodType) || !in_array($outcome, ['Completed', 'Deferred'], true)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid outcome parameters submitted.']);
        exit();
    }

    $pdo = getDBConnection();

    try {
        $pdo->beginTransaction();

        // 1. Insert Donation Record
        $stmtRecord = $pdo->prepare("
            INSERT INTO donation_records (
                appointment_id, verified_by_staff_id, donation_date,
                blood_type_collected, volume_ml, clinical_outcome, deferral_reason, medical_notes
            ) VALUES (
                :app_id, :staff_id, CURDATE(), :blood_type, :volume, :outcome, :deferral, :notes
            )
        ");
        $stmtRecord->execute([
            ':app_id'     => $appointmentId,
            ':staff_id'   => $staffId,
            ':blood_type' => $bloodType,
            ':volume'     => ($outcome === 'Completed' ? $volumeMl : 0),
            ':outcome'    => $outcome,
            ':deferral'   => ($outcome === 'Deferred' ? $deferralDesc : null),
            ':notes'      => $medicalNotes
        ]);
        $donationId = $pdo->lastInsertId();

        // 2. Update Appointment Status
        $stmtApp = $pdo->prepare("UPDATE appointments SET appointment_status = :status WHERE appointment_id = :id");
        $stmtApp->execute([':status' => $outcome, ':id' => $appointmentId]);

        // 3. Update Donor Profile Eligibility (90 days interval)
        if ($outcome === 'Completed') {
            $stmtEligibility = $pdo->prepare("
                UPDATE donor_profiles 
                SET last_donation_date = CURDATE(),
                    estimated_eligible_date = DATE_ADD(CURDATE(), INTERVAL 90 DAY)
                WHERE user_id = (SELECT donor_id FROM appointments WHERE appointment_id = :app_id)
            ");
            $stmtEligibility->execute([':app_id' => $appointmentId]);

            // 4. Update or Insert Blood Inventory
            $invStmt = $pdo->prepare("
                SELECT inventory_id FROM blood_inventory 
                WHERE blood_type = :btype AND collection_date = CURDATE() AND inventory_status = 'Available' 
                LIMIT 1
            ");
            $invStmt->execute([':btype' => $bloodType]);
            $invItem = $invStmt->fetch();

            if ($invItem) {
                $inventoryId = $invItem['inventory_id'];
                $updateInv = $pdo->prepare("UPDATE blood_inventory SET units_available = units_available + 1 WHERE inventory_id = :id");
                $updateInv->execute([':id' => $inventoryId]);
            } else {
                $insertInv = $pdo->prepare("
                    INSERT INTO blood_inventory (blood_type, units_available, volume_ml_per_unit, collection_date, expiration_date, inventory_status)
                    VALUES (:btype, 1, :volume, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 35 DAY), 'Available')
                ");
                $insertInv->execute([
                    ':btype'  => $bloodType,
                    ':volume' => $volumeMl
                ]);
                $inventoryId = $pdo->lastInsertId();
            }

            // 5. Log Inventory Transaction as 'Addition'
            $stmtTx = $pdo->prepare("
                INSERT INTO inventory_transactions (
                    inventory_id, executed_by_staff_id, transaction_type, units_transacted, donation_id, transaction_notes
                ) VALUES (
                    :inv_id, :staff_id, 'Addition', 1, :donation_id, 'Donation intake recorded'
                )
            ");
            $stmtTx->execute([
                ':inv_id'      => $inventoryId,
                ':staff_id'    => $staffId,
                ':donation_id' => $donationId
            ]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Donation intake recorded successfully.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Donation intake failure: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Transaction failed while saving records: ' . $e->getMessage()]);
    }
    exit();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing action.']);
    exit();
}