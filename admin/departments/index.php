<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Handle status toggle
if (isset($_GET['toggle']) && isset($_GET['status'])) {
    $dept_id = $_GET['toggle'];
    $status = $_GET['status'] == '1' ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE departments SET is_active = ? WHERE id = ?");
    $stmt->execute([$status, $dept_id]);
    
    logActivity($pdo, $_SESSION['user_id'], 'Toggle Department Status', "Toggled status for department ID: $dept_id");
    header("Location: index.php");
    exit();
}

// Handle delete - NOW DELETES EVERYTHING
if (isset($_GET['delete'])) {
    $dept_id = (int)$_GET['delete'];
    
    // Get department name for logging
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $stmt->execute([$dept_id]);
    $dept = $stmt->fetch();
    $dept_name = $dept ? $dept['department_name'] : 'Unknown';
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // 1. Delete student clearance records for this department
        $stmt = $pdo->prepare("
            DELETE sc FROM student_clearance sc
            JOIN clearance_items ci ON sc.clearance_item_id = ci.id
            WHERE ci.department_id = ?
        ");
        $stmt->execute([$dept_id]);
        $clearance_deleted = $stmt->rowCount();
        
        // 2. Delete clearance items for this department
        $stmt = $pdo->prepare("DELETE FROM clearance_items WHERE department_id = ?");
        $stmt->execute([$dept_id]);
        $items_deleted = $stmt->rowCount();
        
        // 3. Remove department_id from users (department heads) assigned to this department
        $stmt = $pdo->prepare("UPDATE users SET department_id = NULL, selected_department_id = NULL WHERE department_id = ?");
        $stmt->execute([$dept_id]);
        $users_updated = $stmt->rowCount();
        
        // 4. Delete the department
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$dept_id]);
        
        // Commit transaction
        $pdo->commit();
        
        logActivity($pdo, $_SESSION['user_id'], 'Delete Department', 
            "Deleted department: $dept_name (ID: $dept_id). Deleted $items_deleted items, $clearance_deleted clearances, updated $users_updated users."
        );
        
        $_SESSION['success'] = "Department '$dept_name' and all associated data (clearance items, student clearances, and assigned users) have been permanently deleted.";
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to delete department: " . $e->getMessage();
    }
    
    header("Location: index.php");
    exit();
}

// Check for messages
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get all departments
$stmt = $pdo->query("
    SELECT d.*
    FROM departments d
    ORDER BY d.department_name ASC
");
$departments = $stmt->fetchAll();

$page_title = 'Manage Departments';
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
            --gray-50: #fafbfc;
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-300: #dce1e8;
            --gray-500: #9aa4b2;
            --gray-600: #6c7293;
            --gray-700: #4a5360;
            --gray-800: #2d3047;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--gray-50) 0%, var(--gray-100) 100%);
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
            transform: scale(1.05);
        }
        
        /* Sidebar */
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
            box-shadow: 10px 0 30px rgba(0,0,0,0.2);
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
            transition: transform 0.3s;
        }
        
        .logo-icon:hover {
            transform: rotate(5deg) scale(1.05);
        }
        
        .sidebar-header h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 10px 0 5px;
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
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
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
        
        /* Alerts */
        .alert-custom {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        /* Page Header */
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
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            box-shadow: 0 2px 5px rgba(128,0,32,0.2);
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128,0,32,0.3);
            color: white;
        }
        
        /* Department Grid */
        .departments-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .department-card {
            background: white;
            border-radius: 20px;
            padding: 25px 25px 20px;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        
        .department-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary), var(--primary-light));
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .department-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px var(--primary-glow);
            border-color: transparent;
        }
        
        .department-card:hover::before {
            opacity: 1;
        }
        
        .department-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        
        .department-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s;
        }
        
        .department-card:hover .department-icon {
            transform: scale(1.05);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.12);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.12);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .department-title {
            margin-bottom: 8px;
        }
        
        .department-title h5 {
            font-size: 1.05rem;
            font-weight: 700;
            margin: 0 0 3px 0;
            color: var(--gray-800);
        }
        
        .department-code {
            font-size: 0.65rem;
            background: var(--gray-100);
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-block;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .department-description {
            color: var(--gray-600);
            font-size: 0.85rem;
            line-height: 1.6;
            margin: 10px 0 16px 0;
            min-height: 40px;
        }
        
        .card-footer-info {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid var(--gray-200);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
        }
        
        .action-btn.view { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .action-btn.edit { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .action-btn.toggle { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .action-btn.delete { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .action-btn.view:hover { background: var(--info); color: white; }
        .action-btn.edit:hover { background: var(--warning); color: white; }
        .action-btn.toggle:hover { background: var(--success); color: white; }
        .action-btn.delete:hover { background: var(--danger); color: white; }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 30px;
            border: 1px solid var(--gray-200);
        }
        
        .empty-state i {
            font-size: 5rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--gray-700);
        }
        
        .empty-state p {
            color: var(--gray-500);
            margin-bottom: 25px;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .departments-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
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
            
            .departments-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .department-card {
                padding: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .department-icon {
                width: 48px;
                height: 48px;
                font-size: 1.2rem;
            }
            
            .action-btn {
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
            }
            
            .btn-primary-custom {
                padding: 8px 18px;
                font-size: 0.85rem;
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
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Administrator Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="index.php" class="menu-item active"><i class="fas fa-building"></i> Departments</a>
            <div class="menu-divider"></div>
            <a href="../users/index.php" class="menu-item"><i class="fas fa-users"></i> Users</a>
            <div class="menu-divider"></div>
            <a href="../reports/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="../settings/index.php" class="menu-item"><i class="fas fa-cog"></i> Settings</a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert-custom alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2><i class="fas fa-building me-2"></i> Departments</h2>
                <p>Manage all clearance departments</p>
            </div>
            <a href="add.php" class="btn-primary-custom">
                <i class="fas fa-plus me-2"></i> Add New Department
            </a>
        </div>
        
        <!-- Departments Grid -->
        <div class="departments-grid">
            <?php if (empty($departments)): ?>
                <div class="empty-state">
                    <i class="fas fa-building"></i>
                    <h4>No Departments Yet</h4>
                    <p>Get started by creating your first department</p>
                    <a href="add.php" class="btn-primary-custom">
                        <i class="fas fa-plus me-2"></i> Create Department
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($departments as $dept): ?>
                    <div class="department-card animate-in">
                        <div class="department-header">
                            <div class="department-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <span class="status-badge <?php echo $dept['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <?php if ($dept['is_active']): ?>
                                    <i class="fas fa-circle fa-2xs"></i> Active
                                <?php else: ?>
                                    <i class="fas fa-circle fa-2xs"></i> Inactive
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="department-title">
                            <h5><?php echo htmlspecialchars($dept['department_name']); ?></h5>
                            <span class="department-code"><?php echo $dept['department_code']; ?></span>
                        </div>
                        
                        <div class="department-description">
                            <?php 
                            $desc = htmlspecialchars($dept['description'] ?? '');
                            echo !empty($desc) ? $desc : '<span style="color: var(--gray-500); font-style: italic;">No description</span>';
                            ?>
                        </div>
                        
                        <div class="card-footer-info">
                            <div class="action-buttons">
                                <a href="view.php?id=<?php echo $dept['id']; ?>" class="action-btn view" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $dept['id']; ?>" class="action-btn edit" title="Edit Department">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="?toggle=<?php echo $dept['id']; ?>&status=<?php echo $dept['is_active'] ? 0 : 1; ?>" 
                                   class="action-btn toggle" title="<?php echo $dept['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                   onclick="return confirm('Are you sure you want to toggle the status of this department?')">
                                    <i class="fas fa-power-off"></i>
                                </a>
                                <a href="?delete=<?php echo $dept['id']; ?>" 
                                   class="action-btn delete" title="Permanently Delete Department"
                                   onclick="return confirm('⚠️ WARNING: This will permanently delete this department and ALL its clearance items, student clearances, and reassign department heads. This action CANNOT be undone! Are you sure you want to proceed?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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