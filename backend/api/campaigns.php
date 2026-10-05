<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/campaigns.php';
try {
    $rows = getDBConnection()->query("SELECT * FROM campaigns WHERE campaign_status IN ('Active','Published','Closed') ORDER BY campaign_date DESC, start_time")->fetchAll();
    $data = [];
    foreach ($rows as $row) {
        $item = array_intersect_key($row, array_flip(['campaign_id', 'title', 'description', 'location_venue', 'campaign_date', 'start_time', 'end_time']));
        $item['registration_status'] = campaignRegistrationStatus($row);
        $data[] = $item;
    }
    jsonResponse(['status' => 'success', 'data' => $data]);
} catch (Throwable $e) { error_log($e->getMessage()); jsonResponse(['status' => 'error', 'message' => 'Campaigns are temporarily unavailable.'], 503); }
