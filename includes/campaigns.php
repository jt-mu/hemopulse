<?php
require_once __DIR__.'/eligibility.php';
function campaignSlots(array $campaign, ?string $now=null): array {
    $now=$now??date('Y-m-d H:i:s'); $slots=[];
    $time=new DateTimeImmutable($campaign['campaign_date'].' '.$campaign['start_time']);
    $end=new DateTimeImmutable($campaign['campaign_date'].' '.$campaign['end_time']);
    for(;$time<$end;$time=$time->modify('+'.eligibilityRules()['slot_minutes'].' minutes')) if($time->format('Y-m-d H:i:s')>$now)$slots[]=$time->format('H:i:s');
    return $slots;
}
function campaignRegistrationStatus(array $campaign, ?string $now = null): string {
    $now = $now ?? date('Y-m-d H:i:s');
    $eventEnd = ($campaign['campaign_date'] ?? '') . ' ' . ($campaign['end_time'] ?? '');
    $deadline = $campaign['registration_closes_at'] ?? $eventEnd;
    if (!in_array($campaign['campaign_status'], ['Active', 'Published'], true) || $deadline <= $now || campaignSlots($campaign,$now)===[]) return 'CLOSED';
    return (int)$campaign['available_slots'] <= 0 ? 'FULL' : 'OPEN';
}
/** Same final-slot boundary as campaignSlots(), including non-aligned closing times. */
function campaignStatusSql(): string {
    $seconds=(int)eligibilityRules()['slot_minutes']*60;
    return "CASE WHEN c.campaign_status NOT IN ('Active','Published') OR TIMESTAMPADD(SECOND,FLOOR((TIMESTAMPDIFF(SECOND,TIMESTAMP(c.campaign_date,c.start_time),TIMESTAMP(c.campaign_date,c.end_time))-1)/$seconds)*$seconds,TIMESTAMP(c.campaign_date,c.start_time)) <= ? OR COALESCE(c.registration_closes_at,TIMESTAMP(c.campaign_date,c.end_time)) <= ? THEN 'CLOSED' WHEN c.available_slots <= 0 THEN 'FULL' ELSE 'OPEN' END";
}
