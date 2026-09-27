<?php
function listCampaigns(PDO $pdo, bool $management = false): array {
    $where = []; $params = [];
    if ($management) apiUser(['Admin', 'Staff']);
    else $where[] = "c.campaign_status IN ('Active','Published','Closed')";
    $statusExpression = "CASE WHEN c.campaign_status NOT IN ('Active','Published') OR TIMESTAMP(c.campaign_date,c.end_time) <= ? OR COALESCE(c.registration_closes_at,TIMESTAMP(c.campaign_date,c.end_time)) <= ? THEN 'CLOSED' WHEN c.available_slots <= 0 THEN 'FULL' ELSE 'OPEN' END";
    $query = field($_GET, 'q', 100, false);
    if ($query !== '') { $where[] = '(c.title LIKE ? OR c.location_venue LIKE ?)'; $params[] = '%' . $query . '%'; $params[] = '%' . $query . '%'; }
    if (inputText($_GET, 'venue') !== '') { $where[] = 'c.location_venue = ?'; $params[] = field($_GET, 'venue', 150); }
    $status = inputText($_GET, 'status');
    if ($status !== '') {
        if ($management && in_array($status, ['Draft', 'Published', 'Active', 'Closed'], true)) { $where[] = 'c.campaign_status = ?'; $params[] = $status; }
        else { if (!in_array($status, ['OPEN', 'FULL', 'CLOSED'], true)) throw new ApiError('Invalid campaign status.', 400); $where[] = "($statusExpression) = ?"; array_push($params, date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $status); }
    }
    if (!empty($_GET['category_id'])) { $where[] = 'c.category_id = ?'; $params[] = integer($_GET, 'category_id'); }
    dateFilters('c.campaign_date', $where, $params);
    $select = 'c.campaign_id, c.title, c.description, c.location_venue, c.campaign_date, c.start_time, c.end_time, c.campaign_status, c.registration_closes_at, c.category_id, k.name AS category_name';
    // Capacity is only included for the authenticated management interface.
    $result = paginated($pdo, $select . ', c.available_slots' . ($management ? ', c.total_slots' : ''), 'campaigns c LEFT JOIN campaign_categories k ON k.category_id = c.category_id', $where, $params, ['date' => 'c.campaign_date ASC,c.campaign_id ASC', 'newest' => 'c.campaign_date DESC,c.campaign_id DESC', 'name' => 'c.title ASC,c.campaign_id ASC'], 'date');
    foreach ($result['data'] as &$row) { $row['registration_status'] = campaignRegistrationStatus($row); if (!$management) unset($row['available_slots']); }
    return $result;
}
function saveCampaign(PDO $pdo, array $user, array $data, ?int $id): int {
    $title = field($data, 'title', 100); $venue = field($data, 'location_venue', 150); $description = field($data, 'description', 3000, false);
    $date = field($data, 'campaign_date', 10); $start = field($data, 'start_time', 8); $end = field($data, 'end_time', 8);
    foreach ([$start, $end] as $time) if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?$/D', $time)) throw new ApiError('Enter valid opening and closing times.');
    $start = substr($start, 0, 5) . ':00'; $end = substr($end, 0, 5) . ':00';
    if (!validDate($date) || $end <= $start) throw new ApiError('Check the event date and opening hours.');
    $status = choice($data, 'campaign_status', ['Draft', 'Published', 'Active', 'Closed']);
    $capacity = integer($data, 'total_slots', 1, 10000);
    $category = !empty($data['category_id']) ? integer($data, 'category_id') : null;
    $deadline = str_replace('T', ' ', field($data, 'registration_closes_at', 19, false));
    if ($deadline !== '') {
        if (strlen($deadline) === 16) $deadline .= ':00';
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $deadline);
        if (!$parsed || $parsed->format('Y-m-d H:i:s') !== $deadline || $deadline > "$date $end") throw new ApiError('Registration deadline must be a valid time no later than the event end.');
    }
    if (!$id && in_array($status, ['Active', 'Published'], true) && "$date $end" <= date('Y-m-d H:i:s')) throw new ApiError('New open campaigns must be in the future.');
    return transaction($pdo, function() use ($pdo, $user, $id, $title, $venue, $description, $date, $start, $end, $status, $capacity, $category, $deadline) {
        if ($category) { $s = $pdo->prepare('SELECT category_id FROM campaign_categories WHERE category_id = ?'); $s->execute([$category]); if (!$s->fetch()) throw new ApiError('Choose an existing category.'); }
        $consumed = 0;
        if ($id) {
            $s = $pdo->prepare('SELECT * FROM campaigns WHERE campaign_id = ? FOR UPDATE'); $s->execute([$id]); $old = $s->fetch();
            if (!$old) throw new ApiError('Campaign not found.', 404);
            $consumed = max(0, (int)$old['total_slots'] - (int)$old['available_slots']);
            $s = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE campaign_id = ? AND appointment_status <> 'Cancelled'"); $s->execute([$id]);
            if ($s->fetchColumn() && ($date !== $old['campaign_date'] || $start !== $old['start_time'] || $end !== $old['end_time'] || $venue !== $old['location_venue'])) throw new ApiError('This campaign has registrations. Close it and coordinate with donors before changing its date, hours, or venue.', 409);
            if ($capacity < $consumed) throw new ApiError('Capacity cannot be lower than registrations already counted.', 409);
        }
        $values = [$title, $venue, $description, $date, $start, $end, $status, $capacity, $capacity - $consumed, $category, $deadline ?: null];
        if ($id) { $s = $pdo->prepare('UPDATE campaigns SET title=?, location_venue=?, description=?, campaign_date=?, start_time=?, end_time=?, campaign_status=?, total_slots=?, available_slots=?, category_id=?, registration_closes_at=? WHERE campaign_id=?'); $values[] = $id; }
        else { $s = $pdo->prepare('INSERT INTO campaigns (title,location_venue,description,campaign_date,start_time,end_time,campaign_status,total_slots,available_slots,category_id,registration_closes_at,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'); $values[] = $user['user_id']; }
        $s->execute($values); $saved = $id ?: (int)$pdo->lastInsertId(); auditAction($pdo, $user['user_id'], $id ? 'Update campaign' : 'Create campaign', 'campaigns', $saved); return $saved;
    });
}
function deleteCampaign(PDO $pdo, array $user, int $id): void {
    transaction($pdo, function() use ($pdo, $user, $id) {
        $s = $pdo->prepare('SELECT campaign_id FROM campaigns WHERE campaign_id=? FOR UPDATE'); $s->execute([$id]); if (!$s->fetch()) throw new ApiError('Campaign not found.',404);
        $s = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE campaign_id=?'); $s->execute([$id]); if ($s->fetchColumn()) throw new ApiError('Campaigns with registration history cannot be deleted. Set the campaign to Closed instead.',409);
        $pdo->prepare('DELETE FROM campaigns WHERE campaign_id=?')->execute([$id]); auditAction($pdo,$user['user_id'],'Delete campaign','campaigns',$id);
    });
}
