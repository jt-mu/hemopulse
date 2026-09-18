<?php

header("Content-Type: application/json");

require_once "../../config/database.php";

$pdo = getDBConnection();

$query = "
    SELECT
        blood_type,
        units_available,
        expiration_date
    FROM blood_inventory
";

$stmt = $pdo->prepare($query);
$stmt->execute();

echo json_encode(
    $stmt->fetchAll(PDO::FETCH_ASSOC)
);