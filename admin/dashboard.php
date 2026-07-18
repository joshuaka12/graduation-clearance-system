<?php
session_start();

// ============================================================
// SIMPLE AUTHENTICATION CHECK - NO REDIRECT LOOPS
// ============================================================
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

// Now include config files
require_once '../config/config.php';

// Get statistics - CORRECT COUNTS
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1");
$total_students = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
$total_departments = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'department_head' AND is_active = 1");
$total_dept_heads = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1");
$total_admins = $stmt->fetch()['count'];

// Get pending clearances count
$stmt = $pdo->query("SELECT COUNT(*) as count FROM student_clearance WHERE status = 'pending'");
$pending_clearances = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM student_clearance WHERE status = 'approved'");
$completed_clearances = $stmt->fetch()['count'];

// Get recent users with profile pictures
$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll();

// Get department performance
$stmt = $pdo->query("
    SELECT 
        d.id,
        d.department_name,
        d.department_code,
        COUNT(DISTINCT sc.id) as total_clearances,
        SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        ROUND((SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT sc.id), 0)) * 100, 1) as completion_rate
    FROM departments d
    LEFT JOIN clearance_items ci ON d.id = ci.department_id
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id
    WHERE d.is_active = 1
    GROUP BY d.id
    ORDER BY completion_rate DESC
    LIMIT 5
");
$department_stats = $stmt->fetchAll();

// Get system settings for branding
$university_name = getUniversityName($pdo);
$academic_year = getAcademicYear($pdo);
$graduation_year = getGraduationYear($pdo);
$logo_url = getUniversityLogoUrl($pdo);

$page_title = 'Admin Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?> - <?php echo htmlspecialchars($university_name); ?></title>
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
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
        
        /* Sidebar - Original Design */
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
            color: white;
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
            width: 22px;
            font-size: 1rem;
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
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(0, 0, 0, 0.04);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px var(--primary-glow);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 15px;
        }
        
        .stat-info h3 {
            font-size: 1.8rem;
            font-weight: 800;
            margin: 0;
            color: var(--gray-800);
        }
        
        .stat-info p {
            margin: 5px 0 0;
            color: var(--gray-600);
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .quick-action-card {
            background: white;
            border-radius: 18px;
            padding: 15px 18px;
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.04);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px var(--primary-glow);
        }
        
        .quick-action-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .quick-action-content h4 {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0 0 2px;
            color: var(--gray-800);
        }
        
        .quick-action-content p {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin: 0;
        }
        
        /* Cards */
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .card-header-custom {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .card-header-custom h5 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .card-header-custom h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            min-width: 500px;
        }
        
        .user-avatar-small {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 600;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .role-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .role-admin { background: rgba(128, 0, 32, 0.1); color: var(--primary); }
        .role-student { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .role-dept { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .role-registrar { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        
        /* Department Items */
        .department-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid var(--gray-200);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .department-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .department-badge {
            width: 40px;
            height: 40px;
            background: var(--primary-soft);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1rem;
        }
        
        .progress-bar-custom {
            width: 120px;
            height: 5px;
            background: var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .btn-link-custom {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header-custom {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .department-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .progress-bar-custom {
                width: 100%;
            }
            
            .user-avatar-small {
                width: 32px;
                height: 32px;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .stat-info h3 {
                font-size: 1.4rem;
            }
            
            .user-avatar-small {
                width: 28px;
                height: 28px;
                font-size: 0.6rem;
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
    
    <!-- Sidebar - Original Design -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h4><?php echo htmlspecialchars($university_name); ?></h4>
            <p>Administrator Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="departments/index.php" class="menu-item">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
            <div class="menu-divider"></div>
            <a href="users/index.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <div class="menu-divider"></div>
            <a href="reports/index.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="settings/index.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div class="menu-divider"></div>
            <a href="../auth/logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h2><i class="fas fa-tachometer-alt me-2"></i> Dashboard</h2>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card animate-in" style="animation-delay: 0.1s;">
                <div class="stat-icon" style="background: var(--primary-soft); color: var(--primary);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($total_students); ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            
            <div class="stat-card animate-in" style="animation-delay: 0.2s;">
                <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_departments; ?></h3>
                    <p>Total Departments</p>
                </div>
            </div>
            
            <div class="stat-card animate-in" style="animation-delay: 0.3s;">
                <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_dept_heads; ?></h3>
                    <p>Total Dept Heads</p>
                </div>
            </div>
            
            <div class="stat-card animate-in" style="animation-delay: 0.4s;">
                <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $total_admins; ?></h3>
                    <p>Total Admins</p>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <a href="users/index.php" class="quick-action-card">
                <div class="quick-action-icon" style="background: rgba(128,0,32,0.1); color: var(--primary);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="quick-action-content">
                    <h4>All Users</h4>
                    <p><?php echo $total_students + $total_admins + $total_dept_heads; ?> total accounts</p>
                </div>
            </a>
            <a href="users/add-user.php" class="quick-action-card">
                <div class="quick-action-icon" style="background: rgba(59,130,246,0.1); color: var(--info);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="quick-action-content">
                    <h4>Add New User</h4>
                    <p>Create student or staff account</p>
                </div>
            </a>
            <a href="users/department-heads.php" class="quick-action-card">
                <div class="quick-action-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="quick-action-content">
                    <h4>Department Heads</h4>
                    <p><?php echo $total_dept_heads; ?> active heads</p>
                </div>
            </a>
        </div>
        
        <!-- Recent Users Table with Profile Pictures -->
        <div class="row g-4">
            <div class="col-12">
                <div class="card-modern">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-user-clock"></i> Recently Joined Users</h5>
                        <a href="users/index.php" class="btn-link-custom">View All <i class="fas fa-arrow-right ms-1"></i></a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background: var(--gray-100);">
                                <tr><th>User</th><th>Role</th><th>Joined</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): 
                                    $role_class = '';
                                    if ($user['role'] == 'admin') $role_class = 'role-admin';
                                    elseif ($user['role'] == 'student') $role_class = 'role-student';
                                    elseif ($user['role'] == 'department_head') $role_class = 'role-dept';
                                    else $role_class = 'role-registrar';
                                    
                                    // Check if profile picture exists
                                    $profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
                                    $pic_exists = !empty($profile_pic) && $profile_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic);
                                    $initials = strtoupper(substr($user['full_name'], 0, 1));
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="user-avatar-small">
                                                <?php if ($pic_exists): ?>
                                                    <img src="../assets/uploads/Students-profile/<?php echo $profile_pic; ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                <?php else: ?>
                                                    <?php echo $initials; ?>
                                                <?php endif; ?>
                                            </div>
                                            <div><strong><?php echo htmlspecialchars($user['full_name']); ?></strong></div>
                                        </div>
                                     </td>
                                    <td><span class="role-badge <?php echo $role_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td><?php echo $user['is_active'] ? '<span style="color:var(--success);">● Active</span>' : '<span style="color:var(--danger);">● Inactive</span>'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Only Top Departments - Chart Removed -->
        <div class="row g-4 mt-1">
            <div class="col-lg-12">
                <div class="card-modern">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-trophy"></i> Top Departments</h5>
                        <a href="departments/index.php" class="btn-link-custom">View All</a>
                    </div>
                    <?php foreach ($department_stats as $dept): ?>
                    <div class="department-item">
                        <div class="department-info">
                            <div class="department-badge"><i class="fas fa-building"></i></div>
                            <div>
                                <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                <br><small><?php echo $dept['department_code']; ?></small>
                            </div>
                        </div>
                        <div>
                            <div class="progress-bar-custom mb-1">
                                <div class="progress-fill" style="width: <?php echo $dept['completion_rate'] ?? 0; ?>%"></div>
                            </div>
                            <small><?php echo $dept['completion_rate'] ?? 0; ?>% Complete</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
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
        
        // Animate progress bars
        setTimeout(() => {
            document.querySelectorAll('.progress-fill').forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => { bar.style.width = width; }, 100);
            });
        }, 100);
    </script>
</body>
</html>