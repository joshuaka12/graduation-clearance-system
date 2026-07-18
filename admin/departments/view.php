<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$department_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$department_id) {
    redirect('index.php');
}

// Get department details with head information from users table
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        u.full_name as head_name,
        u.email as head_email,
        u.profile_pic as head_profile_pic
    FROM departments d
    LEFT JOIN users u ON d.id = u.department_id AND u.role = 'department_head' AND u.is_active = 1
    WHERE d.id = ?
");
$stmt->execute([$department_id]);
$department = $stmt->fetch();

if (!$department) {
    $_SESSION['error'] = "Department not found.";
    redirect('index.php');
}

$page_title = 'View Department - ' . htmlspecialchars($department['department_name']);
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
            --gray-500: #9aa4b2;
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
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 8px;
            background: white;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .btn-back:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-edit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
            color: white;
        }
        
        /* Department Card */
        .dept-card {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .dept-card-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .dept-card-header h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }
        
        .dept-card-header h4 i {
            margin-right: 8px;
        }
        
        .dept-card-header .status-badge {
            padding: 4px 16px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .dept-card-body {
            padding: 25px 30px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
        }
        
        .info-item {
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item .label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        
        .info-item .value {
            font-size: 0.95rem;
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .info-item .value .text-muted {
            color: var(--gray-500);
            font-weight: 400;
        }
        
        .info-item.full-width {
            grid-column: 1 / -1;
        }
        
        /* Status Badge */
        .status-active {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-inactive {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            padding: 3px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
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
                padding: 20px 15px;
            }
            
            .page-header h2 {
                font-size: 1.4rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .dept-card-body {
                padding: 20px;
            }
            
            .dept-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .dept-card-body {
                padding: 15px;
            }
            
            .info-item .value {
                font-size: 0.85rem;
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
            <h2><i class="fas fa-building me-2"></i> Department Details</h2>
            <div class="d-flex gap-2">
                <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back</a>
                <a href="edit.php?id=<?php echo $department['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
            </div>
        </div>
        
        <div class="dept-card">
            <div class="dept-card-header">
                <h4><i class="fas fa-info-circle"></i> Department Information</h4>
                <span class="status-badge">
                    <?php echo $department['is_active'] ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="dept-card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label"><i class="fas fa-tag me-1"></i> Department Name</div>
                        <div class="value"><?php echo htmlspecialchars($department['department_name']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="label"><i class="fas fa-code me-1"></i> Department Code</div>
                        <div class="value"><?php echo htmlspecialchars($department['department_code']); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="label"><i class="fas fa-user-tie me-1"></i> Head of Department</div>
                        <div class="value">
                            <?php if ($department['head_name']): ?>
                                <?php echo htmlspecialchars($department['head_name']); ?>
                                <?php if (!empty($department['head_email'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($department['head_email']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">Not Assigned</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="label"><i class="fas fa-circle me-1"></i> Status</div>
                        <div class="value">
                            <span class="<?php echo $department['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                <i class="fas fa-<?php echo $department['is_active'] ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                <?php echo $department['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="label"><i class="fas fa-calendar-plus me-1"></i> Created Date</div>
                        <div class="value"><?php echo date('F d, Y h:i A', strtotime($department['created_at'])); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="label"><i class="fas fa-clock me-1"></i> Last Updated</div>
                        <div class="value"><?php echo date('F d, Y h:i A', strtotime($department['updated_at'])); ?></div>
                    </div>
                    
                    <?php if ($department['description']): ?>
                    <div class="info-item full-width">
                        <div class="label"><i class="fas fa-align-left me-1"></i> Description</div>
                        <div class="value"><?php echo nl2br(htmlspecialchars($department['description'])); ?></div>
                    </div>
                    <?php endif; ?>
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
    </script>
</body>
</html>