<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/eligibility.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
if (!validCsrf()) { flash('Your form expired. Please try again.'); redirectTo('../contact.php#get-in-touch'); }
try {
    $name = inputText($_POST, 'name'); $email = inputText($_POST, 'email'); $subject = inputText($_POST, 'subject'); $message = inputText($_POST, 'message');
    $_SESSION['contact_old'] = compact('name', 'email', 'subject', 'message');
    if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || $subject === '' || mb_strlen($subject) > 150 || $message === '' || mb_strlen($message) > 5000) throw new InvalidArgumentException('Enter your name, a valid email, a subject (up to 150 characters), and a message (up to 5,000 characters).');
    if (($_SESSION['last_contact_at'] ?? 0) > time() - 30) throw new InvalidArgumentException('Please wait a moment before sending another message.');
    throttle(getDBConnection(),'contact-source',$_SERVER['REMOTE_ADDR']??'local',10,3600,30);
    throttle(getDBConnection(),'contact-email',$email,5,3600);
    throttle(getDBConnection(),'contact-session',session_id(),5,3600);
    if(inputText($_POST,'website')!=='')throw new InvalidArgumentException('Unable to accept this message.');
    $stmt = getDBConnection()->prepare('INSERT INTO contact_messages (sender_name, email, subject, message_body, received_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $email, $subject, $message, date('Y-m-d H:i:s')]);
    $_SESSION['contact_inquiries'][(int)getDBConnection()->lastInsertId()] = time();
    $_SESSION['contact_inquiries'] = array_slice($_SESSION['contact_inquiries'], -5, null, true);
    auditEvent(getDBConnection(),null,'Inquiry received','contact_messages',(int)getDBConnection()->lastInsertId());
    $_SESSION['last_contact_at'] = time(); unset($_SESSION['contact_old']);
    flash('Thank you for contacting HemoPulse. We have received your message and our team will reply shortly during operating hours.', 'success');
} catch (InvalidArgumentException $e) { flash($e->getMessage()); }
catch (Throwable $e) { error_log($e->getMessage()); flash('Your message could not be saved. Please try again later.'); }
redirectTo('../contact.php#get-in-touch');
