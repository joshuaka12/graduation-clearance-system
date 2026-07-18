<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// If admin tries to access, redirect to admin dashboard
if (isAdmin()) {
    redirect('../admin/dashboard.php');
}

$error = '';
$success = '';

// Get unread messages for notification badge
$unread_messages = 0;
if (file_exists('../config/chat_functions.php')) {
    require_once '../config/chat_functions.php';
    $unread_messages = getUnreadMessageCount($pdo, $_SESSION['user_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } else {
        // Get current user's password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            $error = "Current password is incorrect.";
        } else {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                // Log activity
                logActivity($pdo, $_SESSION['user_id'], 'Password Changed', 'User changed their password');
                
                $success = "Password changed successfully! Redirecting...";
                
                // Redirect after 2 seconds
                header("refresh:2;url=dashboard.php");
            } else {
                $error = "Failed to change password. Please try again.";
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?> - Student Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --gray-100: #f4f6f9;
            --gray-200: #e8ecef;
            --gray-600: #6c7683;
            --gray-700: #4a5360;
            --gray-800: #2d3440;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
        }
        
        /* Navbar - SAME AS DASHBOARD */
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 12px 0;
            box-shadow: 0 4px 25px rgba(128, 0, 32, 0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            color: white !important;
            letter-spacing: -0.5px;
        }
        
        .navbar-brand i { margin-right: 10px; }
        
        .navbar-toggler {
            border: 2px solid rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white !important;
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white !important;
        }
        
        /* Profile Dropdown in Navbar */
        .profile-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px 5px 8px;
            border-radius: 50px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            color: white !important;
            text-decoration: none;
            cursor: pointer;
        }
        
        .profile-dropdown-toggle:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.3);
        }
        
        .profile-dropdown-toggle .avatar-sm {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: white;
            flex-shrink: 0;
        }
        
        .profile-dropdown-toggle .profile-name {
            color: white;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .profile-dropdown-toggle .profile-arrow {
            color: rgba(255,255,255,0.7);
            font-size: 0.7rem;
            transition: transform 0.3s;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 2px var(--primary);
        }
        
        .dropdown-menu {
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border: none;
            margin-top: 12px;
            animation: fadeInDown 0.3s ease;
            min-width: 200px;
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-item {
            padding: 10px 20px;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }
        
        .dropdown-item.text-danger:hover {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }
        
        /* Main Content */
        .main-content {
            padding: 30px;
            min-height: calc(100vh - 70px);
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 5px 0 0;
        }
        
        /* Password Card */
        .password-card {
            background: white;
            border-radius: 24px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .card-header-custom {
            padding: 25px 30px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        
        .card-header-custom h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .card-header-custom h4 i {
            color: var(--primary);
            margin-right: 10px;
        }
        
        .card-body-custom {
            padding: 30px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-label i {
            color: var(--primary);
            width: 20px;
        }
        
        .input-group-custom {
            position: relative;
        }
        
        .form-control {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 12px 15px;
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
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray-600);
            background: transparent;
            border: none;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        /* Password Requirements */
        .requirements {
            background: var(--gray-100);
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .requirements-title {
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--gray-700);
        }
        
        .requirement {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .requirement i {
            font-size: 0.7rem;
            width: 16px;
        }
        
        .requirement.valid {
            color: var(--success);
        }
        
        .requirement.invalid {
            color: var(--gray-500);
        }
        
        /* Buttons */
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
            color: white;
        }
        
        .btn-cancel {
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-cancel:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Alert */
        .alert-custom {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--success);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .card-header-custom { padding: 20px; }
            .card-body-custom { padding: 20px; }
            
            .navbar-collapse {
                background: white;
                border-radius: 16px;
                margin-top: 15px;
                padding: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            }
            
            .navbar-nav .nav-link {
                color: var(--gray-800) !important;
                padding: 10px 15px !important;
            }
            
            .navbar-nav .nav-link:hover {
                background: var(--primary-soft);
                color: var(--primary) !important;
            }
            
            .profile-dropdown-toggle {
                background: transparent;
                border: none;
                padding: 5px 0;
                width: 100%;
                justify-content: flex-start;
            }
            
            .profile-dropdown-toggle .avatar-sm {
                background: var(--primary-soft);
                color: var(--primary);
            }
            
            .profile-dropdown-toggle .profile-name {
                color: var(--gray-800);
            }
            
            .profile-dropdown-toggle .profile-arrow {
                color: var(--gray-600);
            }
            
            .dropdown-menu {
                box-shadow: none;
                border: 1px solid var(--gray-200);
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar - SAME AS DASHBOARD -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap"></i> Clearance System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clearance-status.php">
                            <i class="fas fa-list-check me-1"></i> Clearance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="help-support.php">
                            <i class="fas fa-headset me-1"></i> Help & Support
                        </a>
                    </li>
                    <li class="nav-item position-relative">
                        <a class="nav-link" href="message.php">
                            <i class="fas fa-comments me-1"></i> Messages
                            <?php if ($unread_messages > 0): ?>
                                <span class="notification-badge"><?php echo $unread_messages; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Profile Dropdown - Shows Account Name -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle profile-dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-sm">
                                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                            </span>
                            <span class="profile-name"><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                            <i class="fas fa-chevron-down profile-arrow"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item active" href="change-password.php"><i class="fas fa-key me-2"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="main-content">
        <div class="container">
            <div class="page-header">
                <h2><i class="fas fa-key me-2" style="color: var(--primary);"></i> Change Password</h2>
                <p>Update your password to keep your account secure</p>
            </div>
            
            <div class="password-card">
                <div class="card-header-custom">
                    <h4><i class="fas fa-lock"></i> Password Settings</h4>
                </div>
                <div class="card-body-custom">
                    <?php if ($error): ?>
                        <div class="alert-custom alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?php echo $error; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert-custom alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?php echo $success; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="passwordForm">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Current Password
                            </label>
                            <div class="input-group-custom">
                                <input type="password" class="form-control" name="current_password" id="current_password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-key"></i> New Password
                            </label>
                            <div class="input-group-custom">
                                <input type="password" class="form-control" name="new_password" id="new_password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-check-circle"></i> Confirm New Password
                            </label>
                            <div class="input-group-custom">
                                <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Password Requirements -->
                        <div class="requirements">
                            <div class="requirements-title">
                                <i class="fas fa-shield-alt me-1"></i> Password Requirements:
                            </div>
                            <div class="requirement" id="req-length">
                                <i class="fas fa-circle"></i> At least 8 characters
                            </div>
                            <div class="requirement" id="req-match">
                                <i class="fas fa-circle"></i> Passwords match
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <a href="dashboard.php" class="btn-cancel">
                                    <i class="fas fa-times me-2"></i> Cancel
                                </a>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save me-2"></i> Change Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
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
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const reqLength = document.getElementById('req-length');
        const reqMatch = document.getElementById('req-match');
        
        function validatePassword() {
            const password = newPassword.value;
            const confirm = confirmPassword.value;
            
            // Check length
            if (password.length >= 8) {
                reqLength.classList.add('valid');
                reqLength.classList.remove('invalid');
                reqLength.innerHTML = '<i class="fas fa-check-circle"></i> At least 8 characters';
            } else {
                reqLength.classList.add('invalid');
                reqLength.classList.remove('valid');
                reqLength.innerHTML = '<i class="fas fa-circle"></i> At least 8 characters';
            }
            
            // Check match
            if (confirm !== '' && password === confirm) {
                reqMatch.classList.add('valid');
                reqMatch.classList.remove('invalid');
                reqMatch.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            } else {
                reqMatch.classList.add('invalid');
                reqMatch.classList.remove('valid');
                reqMatch.innerHTML = '<i class="fas fa-circle"></i> Passwords match';
            }
        }
        
        newPassword.addEventListener('keyup', validatePassword);
        confirmPassword.addEventListener('keyup', validatePassword);
        
        // Form validation before submit
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const password = newPassword.value;
            const confirm = confirmPassword.value;
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            if (password !== confirm) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
        });
    </script>
</body>
</html>