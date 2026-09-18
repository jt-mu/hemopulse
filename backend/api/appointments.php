<?php

header("Content-Type: application/json");

require_once "../../config/database.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    $pdo = getDBConnection();

    $sql = "
        INSERT INTO appointments (
            donor_id,
            campaign_id,
            scheduled_time_slot,
            appointment_status
        ) VALUES (
            ?,
            ?,
            ?,
            'Pending'
        )
    ";

    $stmt = $pdo->prepare($sql);

    $result = $stmt->execute([
        $data["donor_id"],
        $data["campaign_id"],
        $data["time"]
    ]);

    echo json_encode([
        "success" => $result
    ]);
}
