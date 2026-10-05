<?php
function eligibilityRules(): array { static $rules; return $rules ??= require __DIR__.'/../config/eligibility.php'; }
function afterMonths(string $date, int $months): string {
    $d=new DateTimeImmutable($date); $target=$d->modify('first day of this month')->modify('+'.$months.' months');
    return $target->setDate((int)$target->format('Y'),(int)$target->format('m'),min((int)$d->format('d'),(int)$target->format('t')))->format('Y-m-d');
}
function nextDonationDate(string $date): string { return afterMonths($date,eligibilityRules()['donation_months']); }
function screeningLocked(?array $check): bool { return $check && max($check['deferred_until']??'', $check['retry_after']??'') > date('Y-m-d'); }
function donorNextDate(PDO $pdo,int $id): ?string {
    $s=$pdo->prepare("SELECT MAX(d.donation_date) FROM donation_records d JOIN appointments a ON a.appointment_id=d.appointment_id WHERE a.donor_id=? AND d.clinical_outcome='Completed'");$s->execute([$id]);$last=$s->fetchColumn();
    $s=$pdo->prepare('SELECT estimated_eligible_date FROM donor_profiles WHERE user_id=?');$s->execute([$id]);$stored=$s->fetchColumn();
    $check=latestEligibility($pdo,$id);
    return max($last?nextDonationDate($last):'', $stored?:'', $check['deferred_until']??'') ?: null;
}
function inputText(array $input, string $key): string { return trim(is_string($input[$key] ?? null) ? $input[$key] : ''); }
function pastDate(string $value, string $label): DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value || $date > new DateTimeImmutable('today')) throw new InvalidArgumentException('Enter a valid ' . $label . ' that is not in the future.');
    return $date;
}
/** Preserve the existing website's preliminary screening rules; this is not clinical approval. */
function evaluateEligibility(array $input): array {
    $dob = pastDate(inputText($input, 'date_of_birth'), 'date of birth');
    $age = $dob->diff(new DateTimeImmutable('today'))->y;
    $weight = filter_var($input['weight_kg'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($age > 120 || !$weight || $weight <= 0 || $weight > 500) throw new InvalidArgumentException('Check your date of birth and weight.');
    $answers = ['date_of_birth' => $dob->format('Y-m-d'), 'weight_kg' => $weight];
    foreach (['healthy', 'recent_illness', 'medication', 'donated_before', 'recent_tattoo', 'recent_travel'] as $key) {
        $value = inputText($input, $key);
        if (!in_array($value, ['yes', 'no'], true)) throw new InvalidArgumentException('Please answer every eligibility question.');
        $answers[$key] = $value;
    }
    $denials = []; $reviews = []; $deferredUntil=null; $rules=eligibilityRules();
    if ($age < $rules['minimum_age'] || $age > $rules['maximum_age']) $denials[] = 'Your age falls outside the website pre-screening range.';
    elseif ($age < 18) $reviews[] = 'Parental or guardian consent requires staff review.';
    if ($weight < $rules['minimum_weight']) $reviews[] = 'Your weight requires staff assessment before donation.';
    elseif ($weight < $rules['review_weight']) $reviews[] = 'Your weight requires an on-site assessment.';
    if ($answers['healthy'] === 'no') $reviews[] = 'Please speak with donation staff about your current health.';
    if ($answers['recent_illness'] === 'yes') $reviews[] = 'Recent illness requires staff review.';
    if ($answers['medication'] === 'yes') $reviews[] = 'Medication requires staff review.';
    if ($answers['recent_travel'] === 'yes') $reviews[] = 'Recent international travel requires staff review.';
    foreach (['donated_before' => ['last_donation_date', $rules['donation_months']], 'recent_tattoo' => ['tattoo_date', $rules['tattoo_months']]] as $question => [$dateKey, $months]) {
        if ($answers[$question] !== 'yes') continue;
        $date = pastDate(inputText($input, $dateKey), str_replace('_', ' ', $dateKey));
        $answers[$dateKey] = $date->format('Y-m-d');
        $until=afterMonths($date->format('Y-m-d'),$months);
        if($until>date('Y-m-d')) { $deferredUntil=max($deferredUntil??'', $until); $reviews[]='A '.$months.'-month interval is required after '.str_replace('_',' ',$dateKey).'. Recheck on '.$until.'.'; }
    }
    return ['outcome' => $denials ? 'Not Eligible' : ($deferredUntil ? 'Temporarily Deferred' : ($reviews ? 'Needs Review' : 'Eligible')), 'deferred_until'=>$deferredUntil, 'retry_after'=>$denials?afterMonths(date('Y-m-d'),1):null, 'answers' => $answers, 'reasons' => array_merge($denials, $reviews)];
}
function latestEligibility(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM eligibility_checks WHERE user_id = ? ORDER BY eligibility_id DESC LIMIT 1');
    $stmt->execute([$userId]); return $stmt->fetch() ?: null;
}
function canRegister(?array $check): bool {
    return $check && !screeningLocked($check) && $check['outcome'] === 'Eligible' && substr($check['reviewed_at'] ?? $check['completed_at'], 0, 10) === date('Y-m-d');
}
