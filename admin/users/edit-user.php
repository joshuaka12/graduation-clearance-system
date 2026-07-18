<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('index.php');
}

// Get departments for department head assignment
$stmt = $pdo->query("SELECT id, department_name, department_code FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_user'])) {
        $full_name = sanitizeInput($_POST['full_name']);
        $email = sanitizeInput($_POST['email']);
        $phone = sanitizeInput($_POST['phone']);
        $role = sanitizeInput($_POST['role']);
        $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = "Email already in use by another account.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, department_id = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$full_name, $email, $phone, $role, $department_id, $user_id])) {
                $success = "User updated successfully!";
                logActivity($pdo, $_SESSION['user_id'], 'Edit User', "Updated user ID: $user_id");
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $error = "Failed to update user.";
            }
        }
    }
    
    // Handle password reset
    if (isset($_POST['reset_password'])) {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $user_id])) {
                $success = "Password reset successfully!";
                logActivity($pdo, $_SESSION['user_id'], 'Reset Password', "Reset password for user ID: $user_id");
            } else {
                $error = "Failed to reset password.";
            }
        }
    }
}

$page_title = 'Edit User';
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
        
        /* Sidebar - EXACT MATCH to Admin Dashboard */
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
        
        .sidebar.closed {
            transform: translateX(-100%);
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
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 500;
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
            padding: 10px 24px;
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
            margin-bottom: 25px;
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
        
        .alert-custom {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
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
        
        .info-text {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin-top: 5px;
        }
        
        .table {
            font-size: 0.85rem;
        }
        
        .table td {
            padding: 10px 0;
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
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <!-- Sidebar - EXACT MATCH to Admin Dashboard -->
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
            <a href="index.php" class="menu-item active">
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
                <h2><i class="fas fa-user-edit me-2"></i> Edit User</h2>
                <p>Update user information and manage account</p>
            </div>
            <a href="index.php" class="btn-outline-custom">
                <i class="fas fa-arrow-left me-2"></i> Back to Users
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
        
        <div class="row">
            <div class="col-md-6">
                <div class="card-modern">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-info-circle"></i> User Information</h5>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-tag"></i> Role</label>
                                <select name="role" id="role" class="form-select">
                                    <option value="student" <?php echo $user['role'] == 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="department_head" <?php echo $user['role'] == 'department_head' ? 'selected' : ''; ?>>Department Head</option>
                                </select>
                            </div>
                            
                            <!-- Department Field - Shows for Department Head -->
                            <div class="mb-3" id="departmentField" style="display: <?php echo $user['role'] == 'department_head' ? 'block' : 'none'; ?>;">
                                <label class="form-label"><i class="fas fa-building"></i> Assigned Department</label>
                                <select name="department_id" class="form-select">
                                    <option value="">-- Select Department --</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['id']; ?>" <?php echo ($user['department_id'] == $dept['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo $dept['department_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="info-text">Department Heads can only manage their assigned department</div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" name="update_user" class="btn-primary-custom">
                                    <i class="fas fa-save me-2"></i> Update User
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card-modern">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-key"></i> Reset Password</h5>
                    </div>
                    <div class="card-body-custom">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock"></i> New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="new_password" id="new_password" class="form-control" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                </div>
                                <div class="info-text">Minimum 8 characters</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-check-circle"></i> Confirm Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                    <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye-slash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="reset_password" class="btn-primary-custom">
                                    <i class="fas fa-sync-alt me-2"></i> Reset Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Account Information Card -->
                <div class="card-modern">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-chart-line"></i> Account Information</h5>
                    </div>
                    <div class="card-body-custom">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Account ID:</strong> </div>
                                <td><?php echo $user['id']; ?> </div>
                             </tr>
                            <tr>
                                <td><strong>Student ID:</strong> </div>
                                <td><?php echo $user['student_id'] ?: 'N/A'; ?> </div>
                             </tr>
                            <tr>
                                <td><strong>Created:</strong> </div>
                                <td><?php echo date('F d, Y', strtotime($user['created_at'])); ?> </div>
                             </tr>
                            <tr>
                                <td><strong>Last Updated:</strong> </div>
                                <td><?php echo date('F d, Y', strtotime($user['updated_at'])); ?> </div>
                             </tr>
                            <tr>
                                <td><strong>Account Status:</strong> </div>
                                <td>
                                    <?php if ($user['is_active'] == 1): ?>
                                        <span class="badge" style="background: rgba(16,185,129,0.1); color: #10b981;">Active</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: rgba(239,68,68,0.1); color: #ef4444;">Inactive</span>
                                    <?php endif; ?>
                                 </div>
                             </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
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
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        // Toggle password visibility
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
        
        // Show/hide department field based on role selection
        $('#role').on('change', function() {
            const selectedRole = $(this).val();
            if (selectedRole === 'department_head') {
                $('#departmentField').show();
                $('select[name="department_id"]').prop('required', true);
            } else {
                $('#departmentField').hide();
                $('select[name="department_id"]').prop('required', false);
                $('select[name="department_id"]').val('');
            }
        });
        
        // Password validation on reset
        document.querySelector('form[action=""]').addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
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