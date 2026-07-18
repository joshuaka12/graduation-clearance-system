#!/usr/bin/php
<?php
// cron/send-emails.php

$root_path = dirname(__DIR__);
require_once $root_path . '/config/config.php';
require_once $root_path . '/config/email_config.php';
require_once $root_path . '/includes/email_templates.php';

// Create email queue table if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `email_queue` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `recipient_email` varchar(100) NOT NULL,
            `subject` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `type` enum('immediate','scheduled') DEFAULT 'immediate',
            `status` enum('pending','sent','failed') DEFAULT 'pending',
            `error_message` text DEFAULT NULL,
            `attempts` int(11) DEFAULT 0,
            `sent_at` timestamp NULL DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table might already exist
}

// Get pending emails
$stmt = $pdo->prepare("
    SELECT * FROM email_queue 
    WHERE status = 'pending' 
    AND attempts < 3
    ORDER BY created_at ASC 
    LIMIT 50
");
$stmt->execute();
$emails = $stmt->fetchAll();

if (empty($emails)) {
    exit(0);
}

foreach ($emails as $email) {
    // Increment attempt count
    $stmt = $pdo->prepare("UPDATE email_queue SET attempts = attempts + 1 WHERE id = ?");
    $stmt->execute([$email['id']]);
    
    // Try to send email
    $sent = sendEmail($email['recipient_email'], $email['subject'], $email['message']);
    
    if ($sent) {
        $stmt = $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $stmt->execute([$email['id']]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE email_queue 
            SET status = 'failed', error_message = 'Mail function returned false'
            WHERE id = ? AND attempts >= 3
        ");
        $stmt->execute([$email['id']]);
    }
}
?>