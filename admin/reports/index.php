<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$total_students = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
$total_departments = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM clearance_items");
$total_items = $stmt->fetch()['count'];

// Clearance statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(sc.id) as total_clearances,
        SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        ROUND((SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) / NULLIF(COUNT(sc.id), 0)) * 100, 1) as approval_rate
    FROM student_clearance sc
");
$clearance_stats = $stmt->fetch();

// Graduation ready count
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT u.id) as count
    FROM users u
    WHERE u.role = 'student'
    AND NOT EXISTS (
        SELECT 1 FROM clearance_items ci
        WHERE NOT EXISTS (
            SELECT 1 FROM student_clearance sc 
            WHERE sc.clearance_item_id = ci.id 
            AND sc.student_id = u.id 
            AND sc.status = 'approved'
        )
    )
");
$graduation_ready = $stmt->fetch()['count'];

$approval_rate = $clearance_stats['total_clearances'] > 0 ? round(($clearance_stats['approved'] / $clearance_stats['total_clearances']) * 100, 1) : 0;

$page_title = 'Reports Dashboard';
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
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
            transition: all 0.3s;
        }
        
        /* Overlay for mobile */
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
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }
        
        .page-header h1 i {
            color: var(--primary);
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 5px 0 0;
            font-size: 0.85rem;
        }
        
        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .section-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-700);
            margin: 0;
        }
        
        .section-header h3 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        /* Report Cards Grid - 3 columns */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.04);
            padding: 25px;
            text-decoration: none;
            transition: all 0.3s;
            display: block;
        }
        
        .report-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: 0 10px 25px var(--primary-glow);
        }
        
        .report-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-soft);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.3rem;
            margin-bottom: 15px;
        }
        
        .report-card h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0 0 5px 0;
        }
        
        .report-card p {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin: 0;
            line-height: 1.4;
        }
        
        .report-meta {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .report-meta span {
            color: var(--gray-600);
        }
        
        .report-meta strong {
            color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .reports-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
        }
        
        @media (max-width: 992px) {
            .reports-grid { gap: 15px; }
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
            
            .page-header h1 {
                font-size: 1.4rem;
            }
            
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
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
            <a href="../users/index.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <div class="menu-divider"></div>
            <a href="index.php" class="menu-item active">
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
            <h1><i class="fas fa-chart-bar me-2"></i> Reports Dashboard</h1>
            <p>Comprehensive analytics and reporting tools</p>
        </div>
        
        <!-- Reports Section - Analytics & Reports -->
        <div class="section-header">
            <h3><i class="fas fa-chart-line"></i> 📊 Analytics & Reports</h3>
        </div>
        <div class="reports-grid">
            <a href="statistics.php" class="report-card">
                <div class="report-icon"><i class="fas fa-chart-pie"></i></div>
                <h4>Statistics Dashboard</h4>
                <p>Visual charts and trend analysis</p>
                <div class="report-meta"><span>Charts:</span> <strong>5+</strong></div>
            </a>
        </div>
        
        <!-- Student Reports -->
        <div class="section-header">
            <h3><i class="fas fa-users"></i> 👨‍🎓 Student Reports</h3>
        </div>
        <div class="reports-grid">
            <a href="student-report-list.php" class="report-card">
                <div class="report-icon"><i class="fas fa-user-graduate"></i></div>
                <h4>Student Report</h4>
                <p>Individual student clearance progress</p>
                <div class="report-meta"><span>Students:</span> <strong><?php echo number_format($total_students); ?></strong></div>
            </a>
            <a href="graduation-ready.php" class="report-card">
                <div class="report-icon"><i class="fas fa-graduation-cap"></i></div>
                <h4>Graduation Ready</h4>
                <p>Students who completed all requirements</p>
                <div class="report-meta"><span>Eligible:</span> <strong><?php echo $graduation_ready; ?></strong></div>
            </a>
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
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>