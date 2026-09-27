<?php
// Compatibility endpoint: same authentication, CSRF and booking rules as the website.
define('BOOKING_API', true);
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true);
    $_POST = is_array($input) ? $input : [];
    $_POST['scheduled_time_slot'] = $_POST['scheduled_time_slot'] ?? $_POST['time'] ?? '';
    $_POST['action'] = 'book_slot';
}
require __DIR__ . '/../appointment_handler.php';
