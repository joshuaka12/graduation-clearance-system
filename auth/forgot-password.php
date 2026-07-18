<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$page_title = 'Forgot Password';

// Load required files
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/constants.php';

// Check if email_config.php exists
if (file_exists('../config/email_config.php')) {
    require_once '../config/email_config.php';
} else {
    // Fallback constants if email_config.php is missing
    if (!defined('SMTP_HOST')) define('SMTP_HOST', 'smtp.gmail.com');
    if (!defined('SMTP_PORT')) define('SMTP_PORT', 587);
    if (!defined('SMTP_USER')) define('SMTP_USER', 'jokaramuzi@gmail.com');
    if (!defined('SMTP_PASS')) define('SMTP_PASS', 'uwzthjuzgvfdlkci');
    if (!defined('SMTP_FROM_EMAIL')) define('SMTP_FROM_EMAIL', 'jokaramuzi@gmail.com');
    if (!defined('SMTP_FROM_NAME')) define('SMTP_FROM_NAME', 'Graduation Clearance System');
}

// Check if PHPMailer exists
$phpmailer_path = '../vendor/autoload.php';
$use_phpmailer = false;

if (file_exists($phpmailer_path)) {
    try {
        require_once $phpmailer_path;
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $use_phpmailer = true;
        }
    } catch (Exception $e) {
        error_log("Error loading PHPMailer: " . $e->getMessage());
        $use_phpmailer = false;
    }
}

$error = '';
$success = '';
$debug_info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $reset_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Save token to database
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $stmt->execute([$reset_token, $token_expiry, $user['id']]);
            
            // Build reset link
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
            $base_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
            $reset_link = $base_url . "/reset-password.php?token=" . $reset_token;
            
            // Email subject and body
            $subject = "Password Reset Request - " . SITE_NAME;
            
            // HTML Email Body
            $html_message = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #800020; color: white; padding: 25px 20px; text-align: center; border-radius: 12px 12px 0 0; }
                    .header h2 { margin: 0; font-weight: 700; }
                    .body { background: #f8f9fc; padding: 30px; border-radius: 0 0 12px 12px; }
                    .body p { line-height: 1.6; }
                    .btn { display: inline-block; background: #800020; color: white !important; padding: 12px 30px; text-decoration: none; border-radius: 50px; font-weight: 600; margin: 15px 0; }
                    .btn:hover { background: #5a0016; }
                    .footer { text-align: center; color: #999; font-size: 0.75rem; margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; }
                    .highlight { color: #800020; font-weight: 600; }
                    .expiry-note { background: #fff3cd; padding: 10px 15px; border-radius: 8px; color: #856404; font-size: 0.85rem; margin: 10px 0; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h2>🔐 Password Reset Request</h2>
                    </div>
                    <div class="body">
                        <p>Hello <strong>' . htmlspecialchars($user['full_name']) . '</strong>,</p>
                        <p>We received a request to reset your password for your <span class="highlight">' . SITE_NAME . '</span> account.</p>
                        <p>Click the button below to create a new password:</p>
                        <div style="text-align: center;">
                            <a href="' . $reset_link . '" class="btn">Reset Password</a>
                        </div>
                        <div class="expiry-note">
                            ⏰ This link will expire in <strong>1 hour</strong>.
                        </div>
                        <p style="font-size: 0.85rem; color: #666;">If you didn\'t request this password reset, please ignore this email. Your account remains secure.</p>
                        <hr>
                        <p style="font-size: 0.75rem; color: #999;">For security, this link can only be used once.</p>
                    </div>
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.</p>
                        <p>This is an automated message, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
            ';
            
            // Plain text version
            $plain_message = "
Password Reset Request - " . SITE_NAME . "

Hello " . $user['full_name'] . ",

We received a request to reset your password for your " . SITE_NAME . " account.

Click the link below to reset your password:
" . $reset_link . "

This link will expire in 1 hour.

If you didn't request this, please ignore this email.

---
" . SITE_NAME . " - " . date('Y') . "
            ";
            
            // Try to send email
            $email_sent = false;
            $send_error = '';
            
            if ($use_phpmailer) {
                try {
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    
                    // Server settings - DEBUG OFF for production
                    $mail->isSMTP();
                    $mail->Host       = SMTP_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = SMTP_USER;
                    $mail->Password   = SMTP_PASS;
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = SMTP_PORT;
                    $mail->CharSet    = 'UTF-8';
                    $mail->SMTPDebug  = 0; // Disable debug output
                    
                    // Recipients
                    $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                    $mail->addAddress($email, $user['full_name']);
                    $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $html_message;
                    $mail->AltBody = $plain_message;
                    
                    $mail->send();
                    $email_sent = true;
                    $debug_info = "Email sent successfully via PHPMailer";
                    
                } catch (Exception $e) {
                    $send_error = $e->getMessage();
                    error_log("PHPMailer failed: " . $e->getMessage());
                    $debug_info = "PHPMailer error: " . $e->getMessage();
                    $email_sent = false;
                }
            }
            
            // Fallback to mail() function if PHPMailer fails
            if (!$email_sent) {
                try {
                    $headers = "MIME-Version: 1.0\r\n";
                    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                    $headers .= "From: " . SMTP_FROM_EMAIL . "\r\n";
                    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
                    
                    $email_sent = mail($email, $subject, $html_message, $headers);
                    
                    if ($email_sent) {
                        $debug_info = "Email sent via mail() function";
                    } else {
                        $debug_info = "mail() function failed";
                    }
                } catch (Exception $e) {
                    $debug_info = "mail() error: " . $e->getMessage();
                    $email_sent = false;
                }
            }
            
            if ($email_sent) {
                $success = "A password reset link has been sent to your email address. Please check your inbox.";
                if (function_exists('logActivity')) {
                    logActivity($pdo, $user['id'], 'Password Reset Request', 'Password reset email sent to: ' . $email);
                }
            } else {
                $error = "We're having trouble sending emails right now. Please try again later or contact support.";
                // Log the error for debugging
                error_log("Email sending failed for $email: " . $send_error);
            }
        } else {
            // Don't reveal that email doesn't exist for security
            $success = "If an account exists with this email, you will receive a password reset link.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #800020 0%, #4a0012 50%, #2d000a 100%);
            min-height: 100vh;
        }
        
        .container-center {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .reset-card {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            animation: fadeInUp 0.6s ease;
        }
        
        .reset-header {
            padding: 40px 35px 25px;
            text-align: center;
            background: linear-gradient(135deg, #fff 0%, #fef9f9 100%);
        }
        
        .logo-circle {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #800020, #5a0016);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(128, 0, 32, 0.2);
        }
        
        .logo-circle i {
            font-size: 2rem;
            color: white;
        }
        
        .reset-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        
        .reset-header p {
            color: #6c7683;
            font-size: 0.9rem;
        }
        
        .reset-body {
            padding: 30px 35px 40px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .input-icon-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #800020;
            font-size: 1rem;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px 14px 45px;
            border: 1.5px solid #e8ecef;
            border-radius: 16px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fafafc;
        }
        
        .form-control:focus {
            border-color: #800020;
            background: white;
            box-shadow: 0 0 0 3px rgba(128, 0, 32, 0.1);
            outline: none;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #800020, #5a0016);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128, 0, 32, 0.3);
        }
        
        .back-link {
            text-align: center;
        }
        
        .back-link a {
            color: #800020;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 16px;
            margin-bottom: 24px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .info-text {
            background: #f8f9fc;
            border-radius: 16px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.8rem;
            color: #6c7683;
            text-align: center;
        }
        
        .debug-info {
            background: #fff3cd;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 15px;
            font-size: 0.75rem;
            color: #856404;
            word-break: break-all;
            display: <?php echo !empty($debug_info) ? 'block' : 'none'; ?>;
        }
        
        .email-hint {
            font-size: 0.7rem;
            color: #9aa4b2;
            margin-top: 5px;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 520px) {
            .reset-header { padding: 30px 25px 20px; }
            .reset-body { padding: 25px 25px 35px; }
        }
    </style>
</head>
<body>
    <div class="container-center">
        <div class="reset-card">
            <div class="reset-header">
                <div class="logo-circle">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Forgot Password?</h1>
                <p>Enter your email to reset your password</p>
            </div>
            
            <div class="reset-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo $success; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <div class="input-icon-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                        </div>
                        <div class="email-hint">Enter the email you used to register</div>
                    </div>
                    
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane"></i>
                        Send Reset Link
                    </button>
                    
                    <div class="back-link">
                        <a href="login.php">
                            <i class="fas fa-arrow-left me-2"></i> Back to Login
                        </a>
                    </div>
                    
                    <div class="info-text">
                        <i class="fas fa-info-circle me-2"></i>
                        Enter your registered email address and we'll send you a link to reset your password.
                    </div>
                    
                    <?php if (!empty($debug_info)): ?>
                        <div class="debug-info">
                            <strong>🔍 Debug Info:</strong> <?php echo $debug_info; ?>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>