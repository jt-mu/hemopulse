<?php
function campaignRegistrationStatus(array $campaign, ?string $now = null): string {
    $now = $now ?? date('Y-m-d H:i:s');
    $eventEnd = ($campaign['campaign_date'] ?? '') . ' ' . ($campaign['end_time'] ?? '');
    $deadline = $campaign['registration_closes_at'] ?? $eventEnd;
    if (!in_array($campaign['campaign_status'], ['Active', 'Published'], true) || $deadline <= $now || $eventEnd <= $now) return 'CLOSED';
    return (int)$campaign['available_slots'] <= 0 ? 'FULL' : 'OPEN';
}
