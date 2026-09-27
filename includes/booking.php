<?php
require_once __DIR__ . '/campaigns.php';
require_once __DIR__ . '/eligibility.php';
/** Shared transaction for the form and JSON endpoint; donor identity comes from the session. */
function bookAppointment(PDO $pdo, int $donorId, int $campaignId, string $time, array $details = []): string {
    if ($campaignId < 1 || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::00)?$/D', $time)) throw new InvalidArgumentException('Select a campaign and a valid appointment time.');
    if (strlen($time) === 5) $time .= ':00';
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT u.user_id, u.first_name, u.last_name FROM users u JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ? AND u.account_status = 'Active' AND r.role_name = 'Donor' FOR UPDATE");
        $stmt->execute([$donorId]);
        $donor = $stmt->fetch();
        if (!$donor) throw new InvalidArgumentException('An active donor account is required.');
        $screening = latestEligibility($pdo, $donorId);
        if (!canRegister($screening)) throw new InvalidArgumentException('Complete an eligible pre-screening today before registering.');
        $screeningAnswers = json_decode($screening['answers_json'], true);
        $dob = pastDate(inputText($details, 'date_of_birth'), 'date of birth')->format('Y-m-d');
        if ($dob !== $screeningAnswers['date_of_birth']) throw new InvalidArgumentException('Your birth date must match your eligibility form. Update the checker if it is incorrect.');
        $contact = inputText($details, 'contact_number');
        $email = inputText($details, 'email');
        if (!preg_match('/^[+0-9 ()-]{7,30}$/D', $contact) || strlen(preg_replace('/\D/', '', $contact)) < 7) throw new InvalidArgumentException('Enter a valid contact number.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) throw new InvalidArgumentException('Enter a valid email address.');
        if (($details['requirements_agreed'] ?? '') !== '1') throw new InvalidArgumentException('Please agree to the blood donation requirements.');
        $stmt = $pdo->prepare('SELECT * FROM campaigns WHERE campaign_id = ? FOR UPDATE');
        $stmt->execute([$campaignId]); $campaign = $stmt->fetch();
        if (!$campaign || campaignRegistrationStatus($campaign) === 'CLOSED') throw new InvalidArgumentException('Registration for this campaign is closed.');
        if (campaignRegistrationStatus($campaign) === 'FULL') throw new InvalidArgumentException('Registration for this campaign is full.');
        if ($time < $campaign['start_time'] || $time >= $campaign['end_time']) throw new InvalidArgumentException('Choose a time during the campaign opening hours.');
        if ($campaign['campaign_date'] . ' ' . $time <= date('Y-m-d H:i:s')) throw new InvalidArgumentException('Choose a future appointment time.');
        $stmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE donor_id = ? AND appointment_status IN ('Pending','Confirmed','Checked-In')");
        $stmt->execute([$donorId]);
        if ($stmt->fetch()) throw new InvalidArgumentException('You already have an active registration. Only one blood donation registration may be active at a time.');
        $stmt = $pdo->prepare('SELECT estimated_eligible_date FROM donor_profiles WHERE user_id = ?');
        $stmt->execute([$donorId]); $eligible = $stmt->fetchColumn();
        if ($eligible && $eligible > $campaign['campaign_date']) throw new InvalidArgumentException('This campaign is before your recorded next eligible donation date.');
        $token = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO appointments (donor_id, campaign_id, scheduled_time_slot, appointment_status, qr_pass_token) VALUES (?, ?, ?, 'Pending', ?)");
        $stmt->execute([$donorId, $campaignId, $time, $token]);
        $appointmentId = (int)$pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO campaign_registrations (appointment_id, eligibility_id, full_name, date_of_birth, contact_number, email, requirements_agreed_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$appointmentId, $screening['eligibility_id'], trim($donor['first_name'] . ' ' . $donor['last_name']), $dob, $contact, $email, date('Y-m-d H:i:s')]);
        $stmt = $pdo->prepare('UPDATE campaigns SET available_slots = available_slots - 1 WHERE campaign_id = ?');
        $stmt->execute([$campaignId]); $pdo->commit(); return $token;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
