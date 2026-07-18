<?php
session_start();
require_once '../../config/config.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$error = '';
$success = '';
$form_data = [];

// Get departments for assignment
$stmt = $pdo->query("SELECT id, department_name, department_code FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $send_email = isset($_POST['send_email']) ? 1 : 0;
    
    // Validation
    if (empty($full_name) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (empty($department_id)) {
        $error = "Please select a department for this department head.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = "Email already registered. Please use a different email.";
        } else {
            // Check if department already has a head
            $stmt = $pdo->prepare("SELECT id FROM users WHERE department_id = ? AND role = 'department_head' AND is_active = 1");
            $stmt->execute([$department_id]);
            if ($stmt->fetch()) {
                $error = "This department already has an active department head. Please deactivate the current head first.";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO users (full_name, email, phone, password, role, department_id, is_active, created_at) 
                        VALUES (?, ?, ?, ?, 'department_head', ?, 1, NOW())
                    ");
                    
                    if ($stmt->execute([$full_name, $email, $phone, $hashed_password, $department_id])) {
                        $user_id = $pdo->lastInsertId();
                        
                        // Log activity
                        logActivity($pdo, $_SESSION['user_id'], 'Add Department Head', "Added department head: $email for department ID: $department_id");
                        
                        // Send welcome email if checked
                        if ($send_email) {
                            $subject = "Welcome to " . SITE_NAME . " - Department Head Access";
                            
                            // Get department name
                            $dept_name = '';
                            foreach ($departments as $dept) {
                                if ($dept['id'] == $department_id) {
                                    $dept_name = $dept['department_name'];
                                    break;
                                }
                            }
                            
                            $message = "
                            <html>
                            <head><style>
                                body { font-family: Arial, sans-serif; }
                                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                .header { background: #800020; color: white; padding: 15px; text-align: center; border-radius: 8px 8px 0 0; }
                                .content { padding: 20px; border: 1px solid #ddd; border-radius: 0 0 8px 8px; }
                                .credentials { background: #f4f6f9; padding: 15px; border-radius: 8px; margin: 15px 0; }
                            </style></head>
                            <body>
                                <div class='container'>
                                    <div class='header'>
                                        <h2>Welcome to " . SITE_NAME . "</h2>
                                    </div>
                                    <div class='content'>
                                        <p>Dear {$full_name},</p>
                                        <p>You have been appointed as the <strong>Department Head of {$dept_name}</strong>.</p>
                                        <div class='credentials'>
                                            <p><strong>Login Details:</strong></p>
                                            <p>Email: {$email}</p>
                                            <p>Password: {$password}</p>
                                            <p>Role: Department Head</p>
                                            <p>Department: {$dept_name}</p>
                                        </div>
                                        <p><strong>Login URL:</strong> <a href='http://{$_SERVER['HTTP_HOST']}/graduation-clearance-system/auth/login.php'>Click here to login</a></p>
                                        <p>Please change your password after your first login.</p>
                                        <p>Best regards,<br>Administration Team</p>
                                    </div>
                                </div>
                            </body>
                            </html>
                            ";
                            
                            $headers = "MIME-Version: 1.0\r\n";
                            $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                            $headers .= "From: " . SITE_NAME . " <noreply@" . $_SERVER['HTTP_HOST'] . ">\r\n";
                            
                            mail($email, $subject, $message, $headers);
                        }
                        
                        $success = "Department head created successfully!";
                        $form_data = [];
                        $_POST = array();
                        
                        header("refresh:2;url=department-heads.php");
                    } else {
                        $error = "Failed to create department head. Please try again.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
    
    $form_data = ['full_name' => $full_name, 'email' => $email, 'phone' => $phone];
}

$page_title = 'Add Department Head';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e8ecef;
            --gray-600: #6c7683;
            --gray-700: #4a5360;
            --gray-800: #2d3440;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
        }
        
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
        }
        
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
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
            color: white;
        }
        
        .btn-outline-custom {
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 500;
            background: white;
            text-decoration: none;
        }
        
        .btn-outline-custom:hover {
            background: var(--primary);
            color: white;
        }
        
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .card-header-custom {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        
        .card-header-custom h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .card-header-custom h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .card-body-custom {
            padding: 25px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-700);
        }
        
        .form-label i {
            color: var(--primary);
            margin-right: 6px;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
            outline: none;
        }
        
        .password-wrapper {
            position: relative;
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
        
        .help-text {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin-top: 5px;
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
        
        .info-box {
            background: var(--primary-soft);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-user-plus me-2" style="color: var(--primary);"></i> Add Department Head</h2>
                <p>Assign a department head to manage department clearances</p>
            </div>
            <a href="department-heads.php" class="btn-outline-custom">
                <i class="fas fa-arrow-left me-2"></i> Back to Department Heads
            </a>
        </div>
        
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
        
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-user-tie"></i> Department Head Information</h5>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label"><i class="fas fa-user"></i> Full Name *</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>" required placeholder="e.g., Prof. John Smith">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-envelope"></i> Email Address *</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required placeholder="hod@department.edu">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-phone"></i> Phone Number *</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required placeholder="+1234567890">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-building"></i> Assign Department *</label>
                        <select name="department_id" class="form-select" required>
                            <option value="">Select Department...</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo $dept['department_code']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Each department can only have one active department head</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-lock"></i> Password *</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                            <div class="help-text">Minimum 8 characters</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password *</label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="send_email" class="form-check-input" id="sendEmail" checked>
                            <label class="form-check-label" for="sendEmail">
                                <i class="fas fa-envelope"></i> Send welcome email with login credentials
                            </label>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Department Head Permissions:</strong>
                        <ul class="mt-2 mb-0">
                            <li>View and manage their assigned department only</li>
                            <li>Approve or reject student clearance requests</li>
                            <li>View student documents submitted to their department</li>
                            <li>Generate reports for their department</li>
                            <li>Cannot access other departments or system settings</li>
                        </ul>
                    </div>
                    
                    <div class="text-end mt-4">
                        <a href="department-heads.php" class="btn-outline-custom me-2">Cancel</a>
                        <button type="submit" class="btn-primary-custom">
                            <i class="fas fa-save me-2"></i> Create Department Head
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
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
        
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>