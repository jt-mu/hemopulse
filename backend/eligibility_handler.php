<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/eligibility.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

if (!validCsrf()) {
    flash('Your form expired. Please try again.');
    redirectTo('../dashboard.php?view=eligibility');
}

try {
    $user = currentUser();
    if (!$user) {
        redirectTo('../index.php#login');
    }

    if ($user['role_name'] !== 'Donor') {
        http_response_code(403);
        exit('Only donors can complete this form.');
    }

    $pdo = getDBConnection();

    // 2. Evaluate questionnaire responses
    $result = evaluateEligibility($_POST);

    $pdo->beginTransaction();

    // Coordinate with registration so an older positive check cannot race a new negative check.
    $lock = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? FOR UPDATE');
    $lock->execute([$user['user_id']]);

    if(screeningLocked(latestEligibility($pdo,(int)$user['user_id']))) throw new InvalidArgumentException('Your deferral is still active. Please contact staff or return on the review date.');
    $next=donorNextDate($pdo,(int)$user['user_id']);
    if($next && $next>date('Y-m-d')) { $result['outcome']='Temporarily Deferred'; $result['deferred_until']=max($next,$result['deferred_until']??''); $result['reasons'][]='Next donation date: '.$next; }
    $stmt = $pdo->prepare('
        INSERT INTO eligibility_checks (user_id, outcome, answers_json, reasons_json, completed_at, deferred_until, retry_after)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $user['user_id'],
        $result['outcome'],
        json_encode($result['answers']),
        json_encode($result['reasons']),
        date('Y-m-d H:i:s'), $result['deferred_until'], $result['retry_after']
    ]);

    auditEvent($pdo,(int)$user['user_id'],'Screening submitted','eligibility_checks',(int)$pdo->lastInsertId(),['outcome'=>$result['outcome']]);
    $pdo->commit();

    unset($_SESSION['eligibility_old']);

    flash(
        $result['outcome'] === 'Eligible'
            ? 'Pre-screening completed. You may continue to campaign registration. Final eligibility is assessed by donation staff.'
            : 'Your answers have been saved. Please review the result below.',
        $result['outcome'] === 'Eligible' ? 'success' : 'error'
    );

    redirectTo('../dashboard.php?view=eligibility&tab=' . ($result['outcome'] === 'Eligible' ? 'registration' : 'checker'));

} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['eligibility_old'] = [];
    foreach (['date_of_birth', 'weight_kg', 'healthy', 'recent_illness', 'medication', 'donated_before', 'recent_tattoo', 'recent_travel', 'last_donation_date', 'tattoo_date'] as $key) {
        $_SESSION['eligibility_old'][$key] = inputText($_POST, $key);
    }
    flash($e->getMessage(), 'error');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($e->getMessage());
    flash('Eligibility could not be saved. Please try again later.', 'error');
}

redirectTo('../dashboard.php?view=eligibility');
