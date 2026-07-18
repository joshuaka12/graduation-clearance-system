<?php
// includes/send_email.php - PRODUCTION VERSION (Debug OFF)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

// Load config
require_once __DIR__ . '/../config/email_config.php';

// Load email templates
require_once __DIR__ . '/email_templates.php';

function sendEmail($to, $subject, $message) {
    $mail = new PHPMailer(true);
    
    try {
        // Production settings - Debug OFF
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = str_replace(' ', '', SMTP_PASS);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 30;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = EMAIL_TEMPLATE_HEADER . $message . EMAIL_TEMPLATE_FOOTER;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email failed to send: " . $mail->ErrorInfo);
        return false;
    }
}

function queueEmail($pdo, $to, $subject, $message, $type = 'immediate') {
    return sendEmail($to, $subject, $message);
}
?>