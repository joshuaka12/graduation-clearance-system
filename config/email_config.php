<?php
// config/email_config.php - ONLY CONSTANTS (No functions)

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'jokaramuzi@gmail.com');
define('SMTP_PASS', 'uwzthjuzgvfdlkci');
define('SMTP_FROM_EMAIL', 'jokaramuzi@gmail.com');
define('SMTP_FROM_NAME', 'Graduation Clearance System');

// Email Templates Header
define('EMAIL_TEMPLATE_HEADER', '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Graduation Clearance System</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #800020 0%, #5a0016 100%); color: white; padding: 25px; text-align: center; border-radius: 12px 12px 0 0; }
        .content { background: #ffffff; padding: 30px; border: 1px solid #e4e7ef; border-top: none; border-radius: 0 0 12px 12px; }
        .button { display: inline-block; background: #800020; color: white; padding: 12px 28px; text-decoration: none; border-radius: 50px; margin: 20px 0; }
        .button:hover { background: #5a0016; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #6c7293; border-top: 1px solid #e4e7ef; }
        .status-approved { color: #10b981; font-weight: bold; }
        .status-rejected { color: #ef4444; font-weight: bold; }
        .status-pending { color: #f59e0b; font-weight: bold; }
        .expiry-note { background: #fef3c7; padding: 10px 15px; border-radius: 8px; color: #92400e; font-size: 0.85rem; margin: 10px 0; }
        .highlight { color: #800020; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>🎓 Graduation Clearance System</h2>
        </div>
        <div class="content">
');

define('EMAIL_TEMPLATE_FOOTER', '
        </div>
        <div class="footer">
            <p>© ' . date('Y') . ' Graduation Clearance System. All rights reserved.</p>
            <p>This is an automated message, please do not reply.</p>
        </div>
    </div>
</body>
</html>
');
?>