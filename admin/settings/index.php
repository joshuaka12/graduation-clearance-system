<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$page_title = 'Settings';
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
            --primary-glow: rgba(128, 0, 32, 0.12);
            --gray-50: #fafbfc;
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-300: #dce1e8;
            --gray-400: #b8bfcc;
            --gray-600: #6c7293;
            --gray-700: #4a5360;
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
            min-height: 100vh;
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
            box-shadow: 0 10px 20px rgba(128, 0, 32, 0.4);
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
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .menu-item:hover, .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateX(5px);
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
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px 40px;
            transition: all 0.3s;
            min-height: 100vh;
        }
        
        /* Overlay */
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
        
        /* Page Header */
        .page-header {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .page-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header h2 i {
            color: var(--primary);
            font-size: 1.8rem;
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 6px 0 0 0;
            font-size: 0.95rem;
        }
        
        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            max-width: 900px;
        }
        
        .settings-card {
            background: white;
            border-radius: 18px;
            padding: 30px 28px 28px;
            transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--gray-200);
            text-decoration: none;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            min-height: 200px;
        }
        
        .settings-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            transform: scaleX(0);
            transition: transform 0.4s ease;
            transform-origin: left;
        }
        
        .settings-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(128, 0, 32, 0.12);
            border-color: transparent;
        }
        
        .settings-card:hover::before {
            transform: scaleX(1);
        }
        
        .settings-card:hover .card-icon-wrapper {
            background: var(--primary);
            color: white;
            transform: scale(1.05) rotate(-4deg);
        }
        
        .settings-card:hover .btn-manage {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .settings-card:hover .btn-manage i {
            transform: translateX(4px);
        }
        
        .card-icon-wrapper {
            width: 64px;
            height: 64px;
            background: var(--primary-soft);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 18px;
            color: var(--primary);
            font-size: 1.8rem;
            transition: all 0.35s ease;
            flex-shrink: 0;
        }
        
        .settings-card h3 {
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: var(--gray-800);
            letter-spacing: -0.3px;
        }
        
        .settings-card p {
            color: var(--gray-600);
            font-size: 0.85rem;
            line-height: 1.6;
            margin: 0 0 20px 0;
            flex: 1;
        }
        
        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            padding-top: 16px;
            border-top: 1px solid var(--gray-100);
        }
        
        .btn-manage {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 22px;
            background: transparent;
            color: var(--gray-700);
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
        }
        
        .btn-manage i {
            font-size: 0.7rem;
            transition: transform 0.3s ease;
        }
        
        .card-badge {
            font-size: 0.6rem;
            padding: 3px 12px;
            border-radius: 20px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-essential {
            background: rgba(239, 68, 68, 0.08);
            color: var(--danger);
        }
        
        .badge-optional {
            background: rgba(16, 185, 129, 0.08);
            color: var(--success);
        }
        
        /* Icon Colors */
        .icon-general { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .icon-clearance { background: rgba(128, 0, 32, 0.1); color: var(--primary); }
        
        .settings-card:hover .icon-general { background: #3b82f6; color: white; }
        .settings-card:hover .icon-clearance { background: var(--primary); color: white; }
        
        /* Animation */
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
        
        .animate-in {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        .settings-card:nth-child(1) { animation-delay: 0.05s; }
        .settings-card:nth-child(2) { animation-delay: 0.1s; }
        
        /* Responsive */
        @media (max-width: 992px) {
            .settings-grid {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                max-width: 100%;
            }
            
            .main-content {
                padding: 25px 30px;
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
                padding: 20px 18px;
            }
            
            .page-header {
                margin-bottom: 28px;
                padding-bottom: 16px;
            }
            
            .page-header h2 {
                font-size: 1.6rem;
            }
            
            .page-header p {
                font-size: 0.85rem;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
                gap: 18px;
                max-width: 100%;
            }
            
            .settings-card {
                padding: 22px 20px 20px;
                min-height: auto;
            }
            
            .card-icon-wrapper {
                width: 54px;
                height: 54px;
                font-size: 1.4rem;
            }
            
            .settings-card h3 {
                font-size: 1.05rem;
            }
            
            .settings-card p {
                font-size: 0.8rem;
                margin-bottom: 16px;
            }
            
            .btn-manage {
                padding: 6px 18px;
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px 14px;
            }
            
            .page-header h2 {
                font-size: 1.3rem;
            }
            
            .page-header h2 i {
                font-size: 1.3rem;
            }
            
            .page-header p {
                font-size: 0.8rem;
            }
            
            .settings-card {
                padding: 18px 16px 16px;
                border-radius: 14px;
            }
            
            .card-icon-wrapper {
                width: 48px;
                height: 48px;
                font-size: 1.2rem;
                margin-bottom: 14px;
                border-radius: 14px;
            }
            
            .settings-card h3 {
                font-size: 0.95rem;
            }
            
            .settings-card p {
                font-size: 0.75rem;
                line-height: 1.5;
                margin-bottom: 14px;
            }
            
            .card-footer {
                padding-top: 12px;
            }
            
            .btn-manage {
                padding: 5px 14px;
                font-size: 0.7rem;
                border-radius: 8px;
            }
            
            .card-badge {
                font-size: 0.5rem;
                padding: 2px 10px;
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
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Administrator Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="../departments/index.php" class="menu-item"><i class="fas fa-building"></i> Departments</a>
            <div class="menu-divider"></div>
            <a href="../users/index.php" class="menu-item"><i class="fas fa-users"></i> Users</a>
            <div class="menu-divider"></div>
            <a href="../reports/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="index.php" class="menu-item active"><i class="fas fa-cog"></i> Settings</a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header animate-in">
            <h2>
                <i class="fas fa-cog"></i> Settings
            </h2>
            <p>Manage and configure the Graduation Clearance System.</p>
        </div>
        
        <!-- Settings Grid -->
        <div class="settings-grid">
            <!-- 1. General Settings -->
            <a href="general-settings.php" class="settings-card animate-in">
                <div class="card-icon-wrapper icon-general">
                    <i class="fas fa-university"></i>
                </div>
                <h3>General Settings</h3>
                <p>Manage the university's basic information, including the university name, logo, academic year, and graduation year.</p>
                <div class="card-footer">
                    <span class="btn-manage">
                        Manage <i class="fas fa-arrow-right"></i>
                    </span>
                    <span class="card-badge badge-optional">Optional</span>
                </div>
            </a>
            
            <!-- 2. Clearance Settings -->
            <a href="clearance-settings.php" class="settings-card animate-in">
                <div class="card-icon-wrapper icon-clearance">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <h3>Clearance Settings</h3>
                <p>Configure the graduation clearance process, including opening or closing clearance and setting the clearance period.</p>
                <div class="card-footer">
                    <span class="btn-manage">
                        Manage <i class="fas fa-arrow-right"></i>
                    </span>
                    <span class="card-badge badge-essential">Essential</span>
                </div>
            </a>
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