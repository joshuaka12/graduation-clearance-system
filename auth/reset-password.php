<?php
$page_title = 'Reset Password';
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/constants.php';

$error = '';
$success = '';
$token = isset($_GET['token']) ? sanitizeInput($_GET['token']) : '';

// Verify token
if (empty($token)) {
    redirect('forgot-password.php');
}

// Check if token is valid
$stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() AND is_active = 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = "Invalid or expired reset link. Please request a new password reset.";
} else {
    $user_id = $user['id'];
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL, updated_at = NOW() WHERE id = ?");
        
        if ($stmt->execute([$hashed_password, $user_id])) {
            logActivity($pdo, $user_id, 'Password Reset', 'User reset their password');
            $success = "Password reset successfully! You can now login with your new password.";
            
            // Redirect to login after 3 seconds
            header("refresh:3;url=login.php");
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
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
        
        .user-info {
            background: #f8f9fc;
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .user-info i {
            color: #800020;
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        
        .user-info strong {
            color: #2d3440;
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
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9aa4b2;
            background: transparent;
            border: none;
        }
        
        .password-toggle:hover {
            color: #800020;
        }
        
        .requirements {
            background: #f8f9fc;
            border-radius: 12px;
            padding: 12px;
            margin-top: 15px;
            font-size: 0.7rem;
        }
        
        .requirements p {
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5360;
        }
        
        .requirement {
            color: #9aa4b2;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .requirement.valid {
            color: #10b981;
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
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128, 0, 32, 0.3);
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
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #800020;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            transform: translateX(-3px);
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
                    <i class="fas fa-lock"></i>
                </div>
                <h1>Create New Password</h1>
                <p>Enter your new password below</p>
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
                <?php else: ?>
                    <?php if (isset($user)): ?>
                        <div class="user-info">
                            <i class="fas fa-user-circle"></i>
                            <div>Resetting password for: <strong><?php echo htmlspecialchars($user['email']); ?></strong></div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" class="form-control" name="password" id="password" placeholder="New Password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="input-icon-wrapper">
                                <i class="fas fa-check-circle input-icon"></i>
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirm New Password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="requirements">
                            <p>Password Requirements:</p>
                            <div class="requirement" id="req-length">
                                <i class="fas fa-circle"></i> At least 8 characters
                            </div>
                            <div class="requirement" id="req-match">
                                <i class="fas fa-circle"></i> Passwords match
                            </div>
                        </div>
                        
                        <button type="submit" name="reset_password" class="btn-submit">
                            <i class="fas fa-save"></i>
                            Reset Password
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <a href="login.php" class="btn-back">
                        <i class="fas fa-arrow-left me-2"></i> Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
        
        // Real-time password validation
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        const reqLength = document.getElementById('req-length');
        const reqMatch = document.getElementById('req-match');
        
        function validatePassword() {
            if (password.value.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
            } else {
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
            
            if (confirm.value !== '' && password.value === confirm.value) {
                reqMatch.classList.add('valid');
                reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            } else {
                reqMatch.classList.remove('valid');
                reqMatch.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
            }
        }
        
        password.addEventListener('keyup', validatePassword);
        confirm.addEventListener('keyup', validatePassword);
        
        // Form validation
        document.querySelector('form')?.addEventListener('submit', function(e) {
            if (password.value.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            if (password.value !== confirm.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>