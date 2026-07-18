<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$department_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if (!$department_id) {
    redirect('index.php');
}

// Get department data
$stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
$stmt->execute([$department_id]);
$department = $stmt->fetch();

if (!$department) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_name = sanitizeInput($_POST['department_name']);
    $department_code = strtoupper(sanitizeInput($_POST['department_code']));
    $description = sanitizeInput($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($department_name) || empty($department_code)) {
        $error = "Department name and code are required.";
    } else {
        // Check if department code exists for other departments
        $stmt = $pdo->prepare("SELECT id FROM departments WHERE department_code = ? AND id != ?");
        $stmt->execute([$department_code, $department_id]);
        if ($stmt->fetch()) {
            $error = "Department code already exists. Please use a unique code.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE departments 
                    SET department_name = ?, department_code = ?, description = ?, 
                        is_active = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $department_name, $department_code, $description,
                    $is_active, $department_id
                ]);
                
                logActivity($pdo, $_SESSION['user_id'], 'Edit Department', "Edited department: $department_name ($department_code)");
                $success = "Department updated successfully!";
                
                // Refresh department data
                $stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ?");
                $stmt->execute([$department_id]);
                $department = $stmt->fetch();
                
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Edit Department';
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
        
        .card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            max-width: 800px;
            margin: 0 auto;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        
        .card-header h5 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-size: 0.85rem;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 10px 15px;
            width: 100%;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
            outline: none;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 10px 24px;
            border-radius: 50px;
            background: white;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: var(--primary);
            color: white;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
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
            
            .page-header h2 {
                font-size: 1.4rem;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .btn-back, .btn-save {
                padding: 8px 20px;
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
            <a href="index.php" class="menu-item active">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
            <div class="menu-divider"></div>
            <a href="../users/index.php" class="menu-item">
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
    
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h2><i class="fas fa-edit me-2"></i> Edit Department</h2>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left me-2"></i> Back to Departments</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-building me-2" style="color: var(--primary);"></i> Department Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department Name <span class="text-danger">*</span></label>
                            <input type="text" name="department_name" class="form-control" value="<?php echo htmlspecialchars($department['department_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department Code <span class="text-danger">*</span></label>
                            <input type="text" name="department_code" class="form-control" value="<?php echo $department['department_code']; ?>" required maxlength="20">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($department['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Settings</label>
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="isActive" <?php echo $department['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isActive">Active Department</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <!-- Empty column for spacing -->
                        </div>
                    </div>
                    
                    <div class="text-end mt-4">
                        <a href="index.php" class="btn-back me-2">Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i> Update Department</button>
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
    </script>
</body>
</html>