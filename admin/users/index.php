<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$error = '';
$success = '';

// Handle status toggle
if (isset($_GET['toggle']) && isset($_GET['status'])) {
    $user_id = (int)$_GET['toggle'];
    $status = $_GET['status'] == '1' ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    if ($stmt->execute([$status, $user_id])) {
        $success = "User status updated successfully!";
        logActivity($pdo, $_SESSION['user_id'], 'Toggle User Status', "Toggled status for user ID: $user_id");
        header("refresh:1;url=index.php");
    } else {
        $error = "Failed to update status.";
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            $success = "User deleted successfully!";
            logActivity($pdo, $_SESSION['user_id'], 'Delete User', "Deleted user ID: $user_id");
            header("refresh:1;url=index.php");
        } else {
            $error = "Failed to delete user.";
        }
    }
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($role_filter !== 'all') {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== 'all') {
    $query .= " AND is_active = ?";
    $params[] = $status_filter == 'active' ? 1 : 0;
}

if ($search) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR student_id LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get role counts for stats
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$role_counts = [];
while ($row = $stmt->fetch()) {
    $role_counts[$row['role']] = $row['count'];
}

// Helper function to get user profile picture
function getUserProfilePic($user) {
    $profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
    
    if (empty($profile_pic) || $profile_pic == 'default-avatar.png') {
        return 'default-avatar.png';
    }
    
    $student_path = '../../assets/uploads/Students-profile/' . $profile_pic;
    if (file_exists($student_path)) {
        return $profile_pic;
    }
    
    $profile_path = '../../assets/uploads/profiles/' . $profile_pic;
    if (file_exists($profile_path)) {
        return $profile_pic;
    }
    
    return 'default-avatar.png';
}

function getProfilePicFolder($user) {
    $profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
    
    if (empty($profile_pic) || $profile_pic == 'default-avatar.png') {
        return 'Students-profile';
    }
    
    $student_path = '../../assets/uploads/Students-profile/' . $profile_pic;
    if (file_exists($student_path)) {
        return 'Students-profile';
    }
    
    $profile_path = '../../assets/uploads/profiles/' . $profile_pic;
    if (file_exists($profile_path)) {
        return 'profiles';
    }
    
    return 'Students-profile';
}

$page_title = 'User Management';
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
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
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
            padding: 25px 30px;
            transition: all 0.3s;
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
            display: none;
        }
        
        .sidebar-overlay.active {
            display: block;
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
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
            color: white;
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
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px var(--primary-glow);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 12px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.75rem;
            margin-top: 5px;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .form-select, .form-control {
            border-radius: 10px;
            border-color: var(--gray-200);
            font-size: 0.85rem;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        
        /* Table */
        .card-modern {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: var(--gray-100);
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--gray-800);
        }
        
        .table td {
            padding: 12px 15px;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }
        
        .status-inactive {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }
        
        .role-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-soft);
            color: var(--primary);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .action-btn.edit { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        .action-btn.toggle { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .action-btn.delete { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        
        .action-btn:hover {
            transform: scale(1.05);
        }
        
        /* User Avatar with Profile Picture */
        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 600;
            border: 2px solid var(--gray-200);
            transition: all 0.3s;
        }
        
        .user-avatar:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar .avatar-initials {
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-details strong {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .user-details small {
            font-size: 0.7rem;
            color: var(--gray-600);
        }
        
        .alert-custom {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.2);
        }
        
        .alert-danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.2);
        }
        
        /* ============================================================
           PROFESSIONAL DATATABLES PAGINATION STYLES
           ============================================================ */
        .dataTables_wrapper {
            padding: 15px 20px;
        }
        
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: var(--gray-600);
        }
        
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 6px 10px;
            margin: 0 5px;
            background: white;
            font-size: 0.85rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .dataTables_wrapper .dataTables_length select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
            float: right;
        }
        
        .dataTables_wrapper .dataTables_filter label {
            font-size: 0.85rem;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            padding: 8px 14px;
            margin-left: 8px;
            width: 220px;
            font-size: 0.85rem;
            transition: all 0.3s;
            background: white;
        }
        
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        
        .dataTables_wrapper .dataTables_filter input::placeholder {
            color: var(--gray-400);
        }
        
        .dataTables_wrapper .dataTables_info {
            margin-top: 20px;
            font-size: 0.85rem;
            color: var(--gray-600);
            padding-top: 10px;
            float: left;
        }
        
        /* Professional Pagination Container */
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 20px;
            text-align: right;
            padding-top: 10px;
            float: right;
        }
        
        /* Individual Pagination Buttons */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 8px 16px;
            margin: 0 3px;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-700);
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
            display: inline-block;
            user-select: none;
            text-decoration: none;
        }
        
        /* Hover state */
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-soft);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128, 0, 32, 0.15);
            text-decoration: none;
        }
        
        /* Active/Current page */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-color: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(128, 0, 32, 0.25);
            font-weight: 600;
            cursor: default;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: linear-gradient(135deg, var(--primary-dark), #3a000e);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(128, 0, 32, 0.35);
        }
        
        /* Disabled buttons (First, Previous when at start) */
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            background: white;
            border-color: var(--gray-200);
            color: var(--gray-700);
            transform: none;
            box-shadow: none;
        }
        
        /* Table hover effect */
        .dataTables_wrapper .table-hover tbody tr:hover {
            background: var(--primary-soft) !important;
        }
        
        /* Table row striping */
        .dataTables_wrapper .table-striped tbody tr:nth-of-type(odd) {
            background: var(--gray-50);
        }
        
        /* Responsive pagination */
        @media (max-width: 768px) {
            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 6px 12px;
                margin: 0 2px;
                font-size: 0.75rem;
                min-width: 32px;
            }
            
            .dataTables_wrapper .dataTables_filter input {
                width: 100%;
                margin-left: 0;
                margin-top: 8px;
            }
            
            .dataTables_wrapper .dataTables_filter label {
                display: block;
                width: 100%;
            }
            
            .dataTables_wrapper .dataTables_length label {
                display: block;
                width: 100%;
            }
            
            .dataTables_wrapper .dataTables_info {
                float: none;
                text-align: center;
            }
            
            .dataTables_wrapper .dataTables_paginate {
                float: none;
                text-align: center;
                margin-top: 15px;
            }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
        }
        
        @media (max-width: 992px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start !important;
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
            
            .page-header h1 {
                font-size: 1.4rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-number {
                font-size: 1.4rem;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .filter-bar .row > div {
                margin-bottom: 10px;
            }
            
            .dataTables_wrapper {
                overflow-x: auto;
            }
            
            table.dataTable {
                min-width: 600px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .btn-primary-custom {
                padding: 8px 16px;
                font-size: 0.85rem;
            }
            
            .action-buttons {
                gap: 5px;
            }
            
            .action-btn {
                width: 28px;
                height: 28px;
            }
            
            .filter-bar {
                padding: 15px;
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
        
        /* Avatar color classes for different roles */
        .avatar-admin {
            background: linear-gradient(135deg, #800020, #5a0016);
            color: white;
        }
        
        .avatar-student {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }
        
        .avatar-dept-head {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }
        
        .avatar-default {
            background: linear-gradient(135deg, #6c7293, #4a5360);
            color: white;
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
            <a href="index.php" class="menu-item active"><i class="fas fa-users"></i> Users</a>
            <div class="menu-divider"></div>
            <a href="../reports/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="../settings/index.php" class="menu-item"><i class="fas fa-cog"></i> Settings</a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <div>
                <h1><i class="fas fa-users me-2"></i> User Management</h1>
                <p>Manage all system users and their permissions</p>
            </div>
            <a href="add-user.php" class="btn-primary-custom">
                <i class="fas fa-user-plus me-2"></i> Add New User
            </a>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(128,0,32,0.1); color: var(--primary);"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo count($users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: var(--info);"><i class="fas fa-user-graduate"></i></div>
                <div class="stat-number"><?php echo $role_counts['student'] ?? 0; ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="fas fa-user-cog"></i></div>
                <div class="stat-number"><?php echo ($role_counts['admin'] ?? 0) + ($role_counts['super_admin'] ?? 0); ?></div>
                <div class="stat-label">Admins</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);"><i class="fas fa-user-tie"></i></div>
                <div class="stat-number"><?php echo $role_counts['department_head'] ?? 0; ?></div>
                <div class="stat-label">Dept Heads</div>
            </div>
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
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3 col-sm-6">
                    <select name="role" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $role_filter == 'all' ? 'selected' : ''; ?>>📋 All Roles</option>
                        <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>🎓 Students</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>👑 Admins</option>
                        <option value="department_head" <?php echo $role_filter == 'department_head' ? 'selected' : ''; ?>>👔 Department Heads</option>
                    </select>
                </div>
                <div class="col-md-3 col-sm-6">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>🔘 All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>✅ Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>❌ Inactive</option>
                    </select>
                </div>
                <div class="col-md-4 col-sm-8">
                    <input type="text" name="search" class="form-control" placeholder="🔍 Search by name, email or ID" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn-primary-custom w-100">Search</button>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="card-modern">
            <div class="table-responsive">
                <table id="usersTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Student ID</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($users as $user): 
                            $profile_pic = getUserProfilePic($user);
                            $profile_folder = getProfilePicFolder($user);
                            $has_pic = $profile_pic != 'default-avatar.png';
                            
                            $avatar_class = 'avatar-default';
                            if ($user['role'] == 'admin' || $user['role'] == 'super_admin') {
                                $avatar_class = 'avatar-admin';
                            } elseif ($user['role'] == 'student') {
                                $avatar_class = 'avatar-student';
                            } elseif ($user['role'] == 'department_head') {
                                $avatar_class = 'avatar-dept-head';
                            }
                            
                            $name_parts = explode(' ', $user['full_name']);
                            $initials = '';
                            if (count($name_parts) >= 2) {
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1));
                            } else {
                                $initials = strtoupper(substr($user['full_name'], 0, 2));
                            }
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar <?php echo $avatar_class; ?>">
                                        <?php if ($has_pic): ?>
                                            <img src="../../assets/uploads/<?php echo $profile_folder; ?>/<?php echo $profile_pic; ?>" 
                                                 alt="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                 onerror="this.onerror=null; this.parentElement.innerHTML='<span class=\'avatar-initials\'>' + '<?php echo $initials; ?>' + '</span>';">
                                        <?php else: ?>
                                            <span class="avatar-initials"><?php echo $initials; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="user-details">
                                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                        <?php if ($user['phone']): ?>
                                            <small><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['phone']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><span class="role-badge"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span></td>
                            <td><?php echo htmlspecialchars($user['student_id'] ?: '—'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="action-btn edit" title="Edit User">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?toggle=<?php echo $user['id']; ?>&status=<?php echo $user['is_active'] ? 0 : 1; ?>" 
                                       class="action-btn toggle" title="Toggle Status"
                                       onclick="return confirm('Toggle user status for <?php echo addslashes($user['full_name']); ?>?')">
                                        <i class="fas fa-power-off"></i>
                                    </a>
                                    <a href="?delete=<?php echo $user['id']; ?>" 
                                       class="action-btn delete" title="Delete User"
                                       onclick="return confirm('Delete <?php echo addslashes($user['full_name']); ?>? This action cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-users fa-3x mb-3 d-block" style="color: var(--gray-300);"></i>
                                    <p class="text-muted mb-0">No users found</p>
                                    <a href="add-user.php" class="btn-primary-custom mt-3 d-inline-flex">Add New User</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
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
        
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 15,
                order: [[0, 'asc']],
                responsive: true,
                language: {
                    search: "<i class='fas fa-search'></i> Search:",
                    searchPlaceholder: "Search users...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ users",
                    infoEmpty: "No users found",
                    infoFiltered: "(filtered from _MAX_ total users)",
                    paginate: {
                        first: "<i class='fas fa-chevron-double-left'></i>",
                        last: "<i class='fas fa-chevron-double-right'></i>",
                        next: "<i class='fas fa-chevron-right'></i>",
                        previous: "<i class='fas fa-chevron-left'></i>"
                    }
                },
                drawCallback: function() {
                    // Add custom class to pagination buttons for styling
                    $('.paginate_button').addClass('btn-page');
                }
            });
            
            // Add placeholder to search input
            $('.dataTables_filter input').attr('placeholder', 'Search users...');
        });
    </script>
</body>
</html>