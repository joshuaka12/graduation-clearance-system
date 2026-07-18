<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/constants.php';

// Check if user is logged in and needs to change password
if (!isset($_SESSION['user_id']) || !isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] != 1) {
    redirect('login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < MIN_PASSWORD_LENGTH) {
        $error = "Password must be at least " . MIN_PASSWORD_LENGTH . " characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if new password is different from old password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (password_verify($new_password, $user['password'])) {
            $error = "New password cannot be the same as your current password.";
        } else {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password and reset flags
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password = ?, 
                    must_change_password = 0, 
                    is_temporary_password = 0, 
                    password_changed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                // Update session
                $_SESSION['must_change_password'] = 0;
                $_SESSION['password_changed'] = true;
                
                // Log activity
                logActivity($pdo, $_SESSION['user_id'], 'Password Changed', 'User changed password on first login');
                
                $success = "Password changed successfully! Redirecting to dashboard...";
                
                // Redirect based on role
                if ($_SESSION['role'] == 'department_head') {
                    header("refresh:2;url=../department-head/dashboard.php");
                } elseif ($_SESSION['role'] == 'admin') {
                    header("refresh:2;url=../admin/dashboard.php");
                } else {
                    header("refresh:2;url=../student/dashboard.php");
                }
            } else {
                $error = "Failed to update password. Please try again.";
            }
        }
    }
}

$page_title = 'Change Password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e8ecef;
            --gray-600: #6c7683;
            --gray-800: #2d3047;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
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
        
        .change-card {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            animation: fadeInUp 0.6s ease;
        }
        
        .change-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .change-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .change-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .change-header p {
            font-size: 0.85rem;
            opacity: 0.9;
            margin: 0;
        }
        
        .change-body {
            padding: 30px;
        }
        
        .alert-custom {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-label i {
            color: var(--primary);
        }
        
        .input-group {
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
            outline: none;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--gray-600);
            cursor: pointer;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        .requirements {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 12px;
            margin-top: 15px;
            font-size: 0.7rem;
        }
        
        .requirements p {
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .requirement {
            color: var(--gray-600);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .requirement.valid {
            color: var(--success);
        }
        
        .btn-change {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .btn-change:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128, 0, 32, 0.3);
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
            .change-header { padding: 25px; }
            .change-body { padding: 25px; }
        }
    </style>
</head>
<body>
    <div class="container-center">
        <div class="change-card">
            <div class="change-header">
                <i class="fas fa-key"></i>
                <h2>Change Your Password</h2>
                <p>Please create a new password to continue</p>
            </div>
            
            <div class="change-body">
                <?php if ($error): ?>
                    <div class="alert-custom alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert-custom alert-success">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-lock"></i> New Password</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="new_password" class="form-control" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-check-circle"></i> Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
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
                    
                    <button type="submit" class="btn-change">
                        <i class="fas fa-save me-2"></i> Change Password & Continue
                    </button>
                </form>
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
        
        // Password validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const reqLength = document.getElementById('req-length');
        const reqMatch = document.getElementById('req-match');
        
        function validatePassword() {
            if (newPassword.value.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
            } else {
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
            
            if (confirmPassword.value !== '' && newPassword.value === confirmPassword.value) {
                reqMatch.classList.add('valid');
                reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            } else {
                reqMatch.classList.remove('valid');
                reqMatch.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
            }
        }
        
        newPassword.addEventListener('keyup', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
        
        document.querySelector('form').addEventListener('submit', function(e) {
            if (newPassword.value.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>