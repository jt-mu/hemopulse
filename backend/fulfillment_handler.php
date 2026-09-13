<?php
// backend/fulfillment_handler.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';

authorizeRoles(['Staff', 'Admin']);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'fulfill_request') {
    $requestId   = (int)($_POST['request_id'] ?? 0);
    $inventoryId = (int)($_POST['inventory_id'] ?? 0);
    $units       = (int)($_POST['units_allocated'] ?? 0);
    $staffId     = (int)($_SESSION['user_id'] ?? 0);

    if ($requestId <= 0 || $inventoryId <= 0 || $units <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid fulfillment parameters.']);
        return;
    }

    $pdo = getDBConnection();

    try {
        $pdo->beginTransaction();

        // 1. CONCURRENCY FIX: Lock and re-verify the request row
        // Prevents two staff members racing to fulfill the exact same request simultaneously
        $stmtReqLock = $pdo->prepare("SELECT request_status FROM blood_requests WHERE request_id = :id FOR UPDATE");
        $stmtReqLock->execute([':id' => $requestId]);
        $reqRow = $stmtReqLock->fetch();

        if (!$reqRow) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Request does not exist.']);
            return;
        }

        if ($reqRow['request_status'] !== 'Pending' && $reqRow['request_status'] !== 'Approved') {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Request is already fulfilled, cancelled, or rejected.']);
            return;
        }

        // 2. Lock and verify inventory availability
        $invStmt = $pdo->prepare("SELECT units_available FROM blood_inventory WHERE inventory_id = :id FOR UPDATE");
        $invStmt->execute([':id' => $inventoryId]);
        $inv = $invStmt->fetch();

        if (!$inv || (int)$inv['units_available'] < $units) {
            $pdo->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Insufficient stock for this blood unit.']);
            return;
        }

        // 3. Insert fulfillment record
        $stmtFulfill = $pdo->prepare("
            INSERT INTO request_fulfillments (request_id, inventory_id, fulfilled_by_staff_id, units_allocated)
            VALUES (:req_id, :inv_id, :staff_id, :units)
        ");
        $stmtFulfill->execute([
            ':req_id'   => $requestId,
            ':inv_id'   => $inventoryId,
            ':staff_id' => $staffId,
            ':units'    => $units
        ]);

        // 4. Deduct inventory
        $stmtUpdateInv = $pdo->prepare("UPDATE blood_inventory SET units_available = units_available - :units WHERE inventory_id = :id");
        $stmtUpdateInv->execute([':units' => $units, ':id' => $inventoryId]);

        // 5. Append ledger transaction
        $stmtTx = $pdo->prepare("
            INSERT INTO inventory_transactions (
                inventory_id, executed_by_staff_id, transaction_type, units_transacted, request_id, transaction_notes
            ) VALUES (
                :inv_id, :staff_id, 'Deduction', :units, :req_id, 'Blood request allocation'
            )
        ");
        $stmtTx->execute([
            ':inv_id'   => $inventoryId,
            ':staff_id' => $staffId,
            ':units'    => $units,
            ':req_id'   => $requestId
        ]);

        // 6. Update request status
        $stmtReq = $pdo->prepare("UPDATE blood_requests SET request_status = 'Fulfilled' WHERE request_id = :id");
        $stmtReq->execute([':id' => $requestId]);

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => 'Request successfully fulfilled.']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Fulfillment error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Could not complete request fulfillment: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing action.']);
}