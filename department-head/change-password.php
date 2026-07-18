<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

$error = $success = '';

// Get unread messages count for sidebar notification
$unread_messages = getUnreadMessageCount($pdo, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if (empty($current) || empty($new) || empty($confirm)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($new !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!password_verify($current, $user['password'])) {
            $error = "Current password is incorrect.";
        } elseif (password_verify($new, $user['password'])) {
            $error = "New password cannot be the same as current.";
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$hashed, $_SESSION['user_id']])) {
                if (function_exists('logActivity')) {
                    logActivity($pdo, $_SESSION['user_id'], 'Password Changed', 'Department head changed password');
                }
                $success = "Password changed successfully! Redirecting...";
                header("refresh:2;url=dashboard.php");
            } else {
                $error = "Failed to change password.";
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
    <title><?php echo $page_title; ?> - Department Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128,0,32,0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-600: #6c7293;
            --gray-700: #4a5360;
            --gray-800: #2d3047;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
        }
        
        /* Mobile Menu Toggle */
        .mobile-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(128,0,32,0.3);
            transition: all 0.3s;
        }
        
        .mobile-toggle:hover {
            background: var(--primary-dark);
        }
        
        /* Sidebar - EXACT COPY from Department Head Profile Page */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--primary-dark) 0%, #3a000e 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 10px 0 30px rgba(128,0,32,0.2);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        
        .sidebar-header h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 10px 0 5px;
            color: white;
        }
        
        .sidebar-header p {
            font-size: 0.75rem;
            opacity: 0.7;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 0 15px 20px;
        }
        
        .menu-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 500;
            position: relative;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
        }
        
        .menu-item i {
            width: 22px;
            font-size: 1rem;
        }
        
        .menu-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 20px 0;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
            transition: all 0.3s;
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
        
        .page-header h2 i {
            color: var(--primary);
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 5px 0 0;
            font-size: 0.85rem;
        }
        
        /* Back Button */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-600);
            padding: 8px 18px;
            border-radius: 10px;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Password Card */
        .password-card {
            background: white;
            border-radius: 20px;
            max-width: 550px;
            margin: 0 auto;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-700);
            display: block;
            font-size: 0.85rem;
        }
        
        .form-label i {
            color: var(--primary);
            margin-right: 6px;
        }
        
        .input-group {
            position: relative;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-soft);
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
            font-size: 1rem;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        /* Button */
        .btn-change {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
            font-size: 0.95rem;
            cursor: pointer;
        }
        
        .btn-change:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
        }
        
        /* Alerts */
        .alert {
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .alert-danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.2);
        }
        
        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.2);
        }
        
        /* Info Text */
        .text-muted {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin-top: 5px;
            display: block;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: flex;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .page-header h2 {
                font-size: 1.4rem;
            }
            
            .password-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <!-- Sidebar - EXACT COPY from Department Head Profile Page -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Department Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="clearance-items/index.php" class="menu-item"><i class="fas fa-list-check"></i> Clearance Items</a>
            <a href="students.php" class="menu-item"><i class="fas fa-users"></i> Students</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="message.php" class="menu-item">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="notification-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <div class="menu-divider"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
            <a href="change-password.php" class="menu-item active"><i class="fas fa-key"></i> Change Password</a>
            <a href="../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <a href="profile.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Profile</a>
        
        <div class="page-header">
            <h2><i class="fas fa-key me-2"></i> Change Password</h2>
            <p>Update your password to keep your account secure</p>
        </div>
        
        <div class="password-card">
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> <?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-lock"></i> Current Password</label>
                    <div class="input-group">
                        <input type="password" name="current_password" id="current" class="form-control" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('current')"><i class="fas fa-eye-slash"></i></button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-key"></i> New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" id="new" class="form-control" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('new')"><i class="fas fa-eye-slash"></i></button>
                    </div>
                    <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Minimum 8 characters</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-check-circle"></i> Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm" class="form-control" required>
                        <button type="button" class="password-toggle" onclick="togglePassword('confirm')"><i class="fas fa-eye-slash"></i></button>
                    </div>
                </div>
                
                <button type="submit" class="btn-change"><i class="fas fa-save me-2"></i> Change Password</button>
            </form>
        </div>
    </div>
    
    <script>
        // Sidebar Toggle for Mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        // Password visibility toggle
        function togglePassword(field) {
            const input = document.getElementById(field);
            const button = input.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new').value;
            const confirmPass = document.getElementById('confirm').value;
            
            if (newPass.length < 8) { 
                e.preventDefault(); 
                alert('Password must be at least 8 characters!'); 
            } else if (newPass !== confirmPass) { 
                e.preventDefault(); 
                alert('Passwords do not match!'); 
            }
        });
    </script>
</body>
</html>