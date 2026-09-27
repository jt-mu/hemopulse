<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/booking.php';
$json = defined('BOOKING_API');
function bookingReply(string $message, int $code, bool $json, ?string $token = null): void {
    if ($json) jsonResponse(['status' => $code === 200 ? 'success' : 'error', 'message' => $message, 'token' => $token], $code);
    flash($message, $code === 200 ? 'success' : 'error');
    redirectTo($code === 200 ? '../dashboard.php?view=donations' : '../dashboard.php?view=eligibility&tab=registration');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
if (!validCsrf()) bookingReply('Your form expired. Refresh the page and try again.', 403, $json);
try {
    $user = currentUser();
    if (!$user) {
        if ($json) bookingReply('Please log in to book.', 401, true);
        flash('Please log in to book a campaign.'); redirectTo('../index.php#login');
    }
    if ($user['role_name'] !== 'Donor') bookingReply('Booking is available to donor accounts.', 403, $json);
    if (($_POST['action'] ?? '') !== 'book_slot') throw new InvalidArgumentException('Invalid booking action.');
    $campaign = filter_var($_POST['campaign_id'] ?? null, FILTER_VALIDATE_INT);
    $time = is_string($_POST['scheduled_time_slot'] ?? null) ? $_POST['scheduled_time_slot'] : '';
    $_SESSION['selected_campaign'] = (int)$campaign;
    $_SESSION['registration_old'] = ['contact_number' => inputText($_POST, 'contact_number'), 'email' => inputText($_POST, 'email'), 'scheduled_time_slot' => $time];
    $token = bookAppointment(getDBConnection(), (int)$user['user_id'], (int)$campaign, $time, $_POST);
    unset($_SESSION['selected_campaign'], $_SESSION['registration_old']);
    bookingReply('Your registration has been successfully submitted. Please wait for confirmation regarding your donation schedule.', 200, $json, $token);
} catch (InvalidArgumentException $e) { bookingReply($e->getMessage(), 422, $json); }
catch (Throwable $e) { error_log($e->getMessage()); bookingReply('Booking is unavailable. Please try again later.', 503, $json); }
