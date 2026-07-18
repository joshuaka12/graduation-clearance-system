<?php
$page_title = 'Login';
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/constants.php';

// DEBUG: Log the session state
error_log("Login page accessed. Session: " . print_r($_SESSION, true));

// If already logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    error_log("User already logged in. Role: " . ($_SESSION['role'] ?? 'unknown'));
    
    if (isStudent()) {
        error_log("Redirecting to student dashboard");
        redirect('../student/dashboard.php');
        exit();
    } elseif (isAdmin()) {
        error_log("Redirecting to admin dashboard");
        redirect('../admin/dashboard.php');
        exit();
    } elseif (isDepartmentHead()) {
        error_log("Redirecting department head");
        // Check if department head has selected a department
        $stmt = $pdo->prepare("SELECT selected_department_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user && $user['selected_department_id']) {
            $_SESSION['selected_department_id'] = $user['selected_department_id'];
            error_log("Department head has selected department: " . $user['selected_department_id']);
            redirect('../department-head/dashboard.php');
            exit();
        } else {
            error_log("Department head needs to select department");
            redirect('../department-head/select-department.php');
            exit();
        }
    } else {
        // Fallback - if role is unknown, redirect to student dashboard
        error_log("Unknown role, redirecting to student dashboard");
        redirect('../student/dashboard.php');
        exit();
    }
}

$error = '';
$success = '';

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    error_log("Login attempt for: " . $username);
    
    if (empty($username) || empty($password)) {
        $error = "Please enter your email/student ID and password.";
        error_log("Login failed: Empty username or password");
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (student_id = ? OR email = ?) AND is_active = 1");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                error_log("Login successful for user: " . $user['email'] . " (Role: " . $user['role'] . ")");
                
                // Set basic session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['student_id'] = $user['student_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['login_time'] = time();
                
                error_log("Session set: " . print_r($_SESSION, true));
                
                // Check if user must change password (temporary password)
                if ($user['must_change_password'] == 1 || $user['is_temporary_password'] == 1) {
                    $_SESSION['must_change_password'] = 1;
                    logActivity($pdo, $user['id'], 'Login', 'User logged in with temporary password - redirecting to change password');
                    error_log("User must change password, redirecting to force-change-password.php");
                    redirect('force-change-password.php');
                    exit();
                }
                
                // Update last login
                updateLastLogin($pdo, $user['id']);
                
                // Log activity
                logActivity($pdo, $user['id'], 'Login', 'User logged in successfully');
                
                // Remember me (7 days)
                if ($remember) {
                    $token = generateToken();
                    setcookie('remember_token', $token, time() + (86400 * 7), "/");
                    $stmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $stmt->execute([$token, $user['id']]);
                }
                
                // Redirect based on role - ADD DELAY AND DEBUG
                error_log("Redirecting based on role: " . $user['role']);
                
                if ($user['role'] === 'admin') {
                    error_log("Redirecting to admin dashboard");
                    redirect('../admin/dashboard.php');
                    exit();
                } elseif ($user['role'] === 'department_head') {
                    error_log("Processing department head redirect");
                    // Check if department head has selected a department
                    if ($user['selected_department_id']) {
                        $_SESSION['selected_department_id'] = $user['selected_department_id'];
                        
                        // Get department name
                        $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
                        $stmt->execute([$user['selected_department_id']]);
                        $dept = $stmt->fetch();
                        $_SESSION['selected_department_name'] = $dept['department_name'] ?? 'Department';
                        
                        error_log("Department head redirecting to dashboard");
                        redirect('../department-head/dashboard.php');
                        exit();
                    } else {
                        error_log("Department head needs to select department first");
                        redirect('../department-head/select-department.php');
                        exit();
                    }
                } elseif ($user['role'] === 'student') {
                    error_log("Redirecting to student dashboard");
                    redirect('../student/dashboard.php');
                    exit();
                } elseif ($user['role'] === 'registrar') {
                    error_log("Redirecting to registrar dashboard");
                    redirect('../registrar/dashboard.php');
                    exit();
                } else {
                    error_log("Unknown role, redirecting to index");
                    redirect('../index.php');
                    exit();
                }
            } else {
                $error = "Invalid credentials. Please try again.";
                error_log("Login failed: Invalid credentials for " . $username);
                logActivity($pdo, 0, 'Failed Login', "Failed login attempt for: $username");
            }
        } catch (PDOException $e) {
            $error = "Login failed. Please try again.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #800020 0%, #4a0012 50%, #2d000a 100%);
            min-height: 100vh;
        }
        
        .bg-shape {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }
        
        .bg-shape::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.05) 0%, transparent 70%);
            animation: rotate 40s linear infinite;
        }
        
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            max-width: 480px;
            width: 100%;
            background: rgba(255, 255, 255, 0.98);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
        }
        
        .back-button-container {
            padding: 15px 20px 0 20px;
        }
        
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: none;
            color: #800020;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .back-btn:hover {
            transform: translateX(-3px);
            color: #5a0016;
        }
        
        .login-header {
            padding: 20px 35px 25px;
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
        
        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 800;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #6c7683;
            font-size: 0.9rem;
        }
        
        .login-body {
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
        
        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #9aa4b2;
            background: transparent;
            border: none;
            font-size: 1rem;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: #800020;
        }
        
        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        
        .custom-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #4a5360;
        }
        
        .custom-checkbox input {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #800020;
        }
        
        .forgot-link {
            color: #800020;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .forgot-link:hover {
            text-decoration: underline;
        }
        
        .btn-login {
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
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128, 0, 32, 0.3);
        }
        
        .divider {
            text-align: center;
            position: relative;
            margin-bottom: 24px;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e8ecef;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #9aa4b2;
            font-size: 0.8rem;
        }
        
        .register-link {
            text-align: center;
        }
        
        .register-link p {
            color: #6c7683;
            font-size: 0.9rem;
        }
        
        .register-link a {
            color: #800020;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
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
        
        .login-card {
            animation: fadeInUp 0.6s ease;
        }
        
        @media (max-width: 520px) {
            .login-header {
                padding: 20px 25px 20px;
            }
            .login-body {
                padding: 25px 25px 35px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-shape"></div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="back-button-container">
                <a href="../index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
            
            <div class="login-header">
                <div class="logo-circle">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h1>Welcome Back</h1>
                <p>Sign in to continue to your dashboard</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo $error; ?></span>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <div class="input-icon-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="text" class="form-control" name="username" placeholder="Email " required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="input-icon-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-control" name="password" id="password" placeholder="Password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="options-row">
             
                        <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="fas fa-arrow-right-to-bracket"></i>
                        Sign In
                    </button>
                    
                    <div class="divider">
                        <span>New to Clearance System?</span>
                    </div>
                    
                    <div class="register-link">
                        <p>Don't have an account? <a href="register.php">Create an account</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }
    </script>
</body>
</html>