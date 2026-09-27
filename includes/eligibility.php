<?php
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
    $denials = []; $reviews = [];
    if ($age < 16 || $age > 65) $denials[] = 'Your age falls outside the website pre-screening range.';
    elseif ($age < 18) $reviews[] = 'Parental or guardian consent requires staff review.';
    if ($weight < 45) $denials[] = 'Your weight falls below the website pre-screening range.';
    elseif ($weight < 50) $reviews[] = 'Your weight requires an on-site assessment.';
    if ($answers['healthy'] === 'no') $denials[] = 'Please speak with donation staff about your current health.';
    if ($answers['recent_illness'] === 'yes') $reviews[] = 'Recent illness requires staff review.';
    if ($answers['medication'] === 'yes') $reviews[] = 'Medication requires staff review.';
    if ($answers['recent_travel'] === 'yes') $reviews[] = 'Recent international travel requires staff review.';
    foreach (['donated_before' => ['last_donation_date', 21], 'recent_tattoo' => ['tattoo_date', 1095]] as $question => [$dateKey, $days]) {
        if ($answers[$question] !== 'yes') continue;
        $date = pastDate(inputText($input, $dateKey), str_replace('_', ' ', $dateKey));
        $answers[$dateKey] = $date->format('Y-m-d');
        if ($date->diff(new DateTimeImmutable('today'))->days < $days) $denials[] = $question === 'donated_before' ? 'Your last donation was within the required three-week interval.' : 'Your tattoo or piercing was within the required three-year interval.';
    }
    return ['outcome' => $denials ? 'Not Eligible' : ($reviews ? 'Needs Review' : 'Eligible'), 'answers' => $answers, 'reasons' => array_merge($denials, $reviews)];
}
function latestEligibility(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT * FROM eligibility_checks WHERE user_id = ? ORDER BY eligibility_id DESC LIMIT 1');
    $stmt->execute([$userId]); return $stmt->fetch() ?: null;
}
function canRegister(?array $check): bool {
    return $check && $check['outcome'] === 'Eligible' && substr($check['completed_at'], 0, 10) === date('Y-m-d');
}
