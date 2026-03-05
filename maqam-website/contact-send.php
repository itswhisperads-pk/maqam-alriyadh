<?php
/**
 * Contact Form Handler
 * Handles form submission, validation, rate limiting, and SMTP email sending.
 */

// Configuration - PLACEHOLDERS TO BE FILLED
define('RECAPTCHA_SECRET_KEY', 'YOUR_SECRET_KEY');
define('SMTP_HOST', 'mail.maqamalriyadh.com');
define('SMTP_USERNAME', 'info@maqamalriyadh.com');
define('SMTP_PASSWORD', 'YOUR_EMAIL_PASSWORD');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// Rate Limiting Configuration
define('RATE_LIMIT_MAX', 5); // Max submissions
define('RATE_LIMIT_TIME', 600); // Time window in seconds (10 mins)

// Set response header to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// 1. Rate Limiting (IP-based)
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_file = sys_get_temp_dir() . '/contact_rate_limit.json';

$rate_data = [];
if (file_exists($rate_limit_file)) {
    $rate_data = json_decode(file_get_contents($rate_limit_file), true) ?? [];
}

// Clean up old entries
$current_time = time();
foreach ($rate_data as $logged_ip => $attempts) {
    // If last attempt was older than the window, remove it
    // Actually, we need to track timestamp of attempts. 
    // Simplified: Store array of timestamps for each IP.
    $valid_timestamps = array_filter($attempts, function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < RATE_LIMIT_TIME;
    });
    
    if (empty($valid_timestamps)) {
        unset($rate_data[$logged_ip]);
    } else {
        $rate_data[$logged_ip] = array_values($valid_timestamps);
    }
}

// Check current IP
if (!isset($rate_data[$ip])) {
    $rate_data[$ip] = [];
}

if (count($rate_data[$ip]) >= RATE_LIMIT_MAX) {
    echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
    exit;
}

// Record this attempt (will save after successful validation, or maybe count attempts regardless of validity to prevent spamming checks)
// We'll count it now to prevent brute forcing the form
$rate_data[$ip][] = $current_time;
file_put_contents($rate_limit_file, json_encode($rate_data));


// 2. Honeypot Check
if (!empty($_POST['website'])) {
    // Silent fail for bots
    echo json_encode(['success' => false, 'message' => 'Spam detected.']);
    exit;
}

// 3. Input Sanitization & Validation
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING) ?: 'New Contact Inquiry';
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

if (!$name || !$email || !$message) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// 4. reCAPTCHA Verification
if (empty($recaptcha_response)) {
    echo json_encode(['success' => false, 'message' => 'Please complete the CAPTCHA.']);
    exit;
}

$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
$verify_data = [
    'secret' => RECAPTCHA_SECRET_KEY,
    'response' => $recaptcha_response,
    'remoteip' => $ip
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($verify_data)
    ]
];
$context  = stream_context_create($options);
$verify_result = file_get_contents($verify_url, false, $context);
$captcha_success = json_decode($verify_result);

if (!$captcha_success->success) {
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA verification failed. Please try again.']);
    exit;
}


// 5. PHPMailer Setup & Sending
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = (SMTP_SECURE === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;

    // Recipients
    $mail->setFrom(SMTP_USERNAME, 'Maqam Al Riyadh Website');
    $mail->addAddress(SMTP_USERNAME);     // Send to info@maqamalriyadh.com
    $mail->addReplyTo($email, $name);     // Reply to the user

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Website Contact Form — ' . $subject;
    
    // HTML Body
    $mail->Body    = "
        <h2>New Contact Message</h2>
        <p><strong>Name:</strong> {$name}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Subject:</strong> {$subject}</p>
        <p><strong>Message:</strong><br>" . nl2br($message) . "</p>
        <hr>
        <p><small>Sent from Maqam Al Riyadh Website Contact Form</small></p>
    ";
    
    // Plain Text Body
    $mail->AltBody = "New Contact Message\n\nName: {$name}\nEmail: {$email}\nSubject: {$subject}\nMessage:\n{$message}\n\nSent from Maqam Al Riyadh Website Contact Form";

    $mail->send();
    echo json_encode(['success' => true, 'message' => 'Thank you! Your message has been sent.']);

} catch (Exception $e) {
    // Log actual error for admin, but show generic error to user
    // error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
    echo json_encode(['success' => false, 'message' => 'Message could not be sent. Please try again later.']);
}
