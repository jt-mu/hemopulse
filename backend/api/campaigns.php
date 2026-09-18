<?php

header("Content-Type: application/json");

require_once "../../config/database.php";

try {
    $pdo = getDBConnection();

    $sql = "
        SELECT 
            campaign_id,
            title,
            description,
            location_venue,
            campaign_date,
            start_time,
            end_time,
            available_slots,
            campaign_status
        FROM campaigns
        WHERE campaign_status = 'Active'
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();

    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "data" => $campaigns
    ]);
} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}