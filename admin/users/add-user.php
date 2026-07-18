<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$error = '';
$success = '';

// Get departments that do NOT have a department head assigned
$stmt = $pdo->query("
    SELECT d.id, d.department_name, d.department_code 
    FROM departments d
    WHERE d.is_active = 1 
    AND NOT EXISTS (
        SELECT 1 FROM users u 
        WHERE u.department_id = d.id 
        AND u.role = 'department_head' 
        AND u.is_active = 1
    )
    ORDER BY d.department_name ASC
");
$available_departments = $stmt->fetchAll();

// Get all departments with head status for display
$stmt = $pdo->query("
    SELECT d.id, d.department_name, d.department_code,
           CASE 
               WHEN EXISTS (
                   SELECT 1 FROM users u 
                   WHERE u.department_id = d.id 
                   AND u.role = 'department_head' 
                   AND u.is_active = 1
               ) THEN 1 
               ELSE 0 
           END as has_head
    FROM departments d
    WHERE d.is_active = 1
    ORDER BY d.department_name ASC
");
$all_departments = $stmt->fetchAll();
$taken_departments = array_filter($all_departments, function($d) { return $d['has_head'] == 1; });

// User roles
$roles = [
    'admin' => 'Administrator',
    'department_head' => 'Department Head',
    'student' => 'Student'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $role = sanitizeInput($_POST['role']);
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name) || empty($email) || empty($phone) || empty($role) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($role === 'department_head' && $department_id == 0) {
        $error = "Please select a department for Department Head.";
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
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            try {
                // Determine department_id value
                $dept_value = ($role === 'department_head') ? $department_id : null;
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (full_name, email, phone, password, role, department_id, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                
                $result = $stmt->execute([$full_name, $email, $phone, $hashed_password, $role, $dept_value]);
                
                if ($result) {
                    $user_id = $pdo->lastInsertId();
                    
                    // If department head, update selected_department_id
                    if ($role === 'department_head' && $dept_value) {
                        $update_stmt = $pdo->prepare("UPDATE users SET selected_department_id = ? WHERE id = ?");
                        $update_stmt->execute([$dept_value, $user_id]);
                    }
                    
                    logActivity($pdo, $_SESSION['user_id'], 'Add User', "Added new user: $email as $role");
                    $_SESSION['success'] = "User created successfully!";
                    header("Location: index.php");
                    exit();
                } else {
                    $error = "Failed to create user. Please try again.";
                }
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
                error_log("Add User Error: " . $e->getMessage());
            }
        }
    }
}

$page_title = 'Add New User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
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
            --primary-glow: rgba(128, 0, 32, 0.15);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-600: #6c7293;
            --gray-800: #2d3047;
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
            box-shadow: 10px 0 30px rgba(128, 0, 32, 0.2);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
            box-shadow: 0 10px 20px rgba(128, 0, 32, 0.4);
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
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
        }
        
        .menu-item i {
            width: 24px;
            font-size: 1.1rem;
        }
        
        .menu-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 20px 0;
        }
        
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
        
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
            transition: all 0.3s;
        }
        
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
            color: white;
        }
        
        .btn-outline-custom {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 500;
            background: white;
            text-decoration: none;
            transition: all 0.3s;
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
            font-size: 0.85rem;
        }
        
        .form-label i {
            color: var(--primary);
            margin-right: 6px;
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger);
        }
        
        .form-control, .form-select {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 10px 15px;
            transition: all 0.3s;
            font-size: 0.85rem;
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
        
        .info-box {
            background: var(--primary-soft);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.85rem;
        }
        
        .info-box ul {
            padding-left: 20px;
            margin-top: 5px;
        }
        
        .info-box ul li {
            margin-bottom: 3px;
        }
        
        .alert-custom {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
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
        
        .dept-available {
            color: var(--success);
            font-size: 0.7rem;
        }
        
        .taken-departments-list {
            background: var(--gray-100);
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
        }
        
        .taken-departments-list .badge {
            margin: 2px 4px;
        }
        
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
                padding: 20px 15px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header h2 {
                font-size: 1.4rem;
            }
            
            .card-header-custom, .card-body-custom {
                padding: 20px;
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-in {
            animation: fadeInUp 0.4s ease forwards;
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
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h4>Clearance System</h4>
            <p>Administrator Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../departments/index.php" class="menu-item">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
            <div class="menu-divider"></div>
            <a href="index.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <div class="menu-divider"></div>
            <a href="../reports/index.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="../settings/index.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <div>
                <h2><i class="fas fa-user-plus me-2"></i> Add New User</h2>
                <p>Create a new user account</p>
            </div>
            <a href="index.php" class="btn-outline-custom">
                <i class="fas fa-arrow-left"></i> Back to Users
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
        
        <div class="card-modern animate-in">
            <div class="card-header-custom">
                <h5><i class="fas fa-user-circle"></i> User Information</h5>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="" id="addUserForm">
                    <div class="mb-3">
                        <label class="form-label required-field"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="Enter full name">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-field"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" class="form-control" required placeholder="user@example.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-field"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone" class="form-control" required placeholder="+1234567890">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required-field"><i class="fas fa-tag"></i> Role</label>
                        <select name="role" id="role" class="form-select" required>
                            <option value="">Select Role...</option>
                            <?php foreach ($roles as $key => $role_name): ?>
                                <option value="<?php echo $key; ?>"><?php echo $role_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Department Dropdown - Shows after role selection -->
                    <div class="mb-3" id="departmentField" style="display: none;">
                        <label class="form-label required-field"><i class="fas fa-building"></i> Department</label>
                        <select name="department_id" id="department_id" class="form-select">
                            <option value="">-- Select Department --</option>
                            <?php foreach ($available_departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo $dept['department_code']; ?>)
                                    <span class="dept-available">✓ Available</span>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Only departments without a Department Head are shown</div>
                        
                        <?php if (!empty($taken_departments)): ?>
                            <div class="taken-departments-list">
                                <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Departments already with Head:</small>
                                <?php foreach ($taken_departments as $dept): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($dept['department_name']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-field"><i class="fas fa-lock"></i> Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="password" id="password" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                            <div class="help-text">Minimum 8 characters</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-field"><i class="fas fa-check-circle"></i> Confirm Password</label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong>
                        <ul>
                            <li>Department Heads will only have access to their assigned department</li>
                            <li>Students can track their clearance progress</li>
                            <li>Admins have full system access</li>
                            <li><span class="text-success">✓</span> Only departments without a Head are available for Department Head role</li>
                        </ul>
                    </div>
                    
                    <div class="text-end mt-4">
                        <a href="index.php" class="btn-outline-custom me-2">Cancel</a>
                        <button type="submit" class="btn-primary-custom" id="submitBtn">
                            <i class="fas fa-save me-2"></i> Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar Toggle for Mobile
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
        
        function closeSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        // Toggle password visibility
        function togglePassword(fieldId) {
            var field = document.getElementById(fieldId);
            var icon = field.nextElementSibling.querySelector('i');
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
        
        // Show/hide department field based on role selection
        document.getElementById('role').addEventListener('change', function() {
            var selectedRole = this.value;
            var deptField = document.getElementById('departmentField');
            var deptSelect = document.getElementById('department_id');
            
            if (selectedRole === 'department_head') {
                deptField.style.display = 'block';
                deptSelect.setAttribute('required', 'required');
            } else {
                deptField.style.display = 'none';
                deptSelect.removeAttribute('required');
                deptSelect.value = '';
            }
        });
        
        // Form validation before submit
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            var role = document.getElementById('role').value;
            var dept_id = document.getElementById('department_id').value;
            var password = document.getElementById('password').value;
            var confirm = document.getElementById('confirm_password').value;
            
            // Check if department is selected for Department Head
            if (role === 'department_head' && (dept_id === '' || dept_id === '0')) {
                e.preventDefault();
                alert('Please select a department for Department Head.');
                return false;
            }
            
            // Check password match
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            // Check password length
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });
    </script>
</body>
</html>