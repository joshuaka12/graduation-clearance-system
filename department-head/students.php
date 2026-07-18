<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

// Check if department is selected
if (!isset($_SESSION['selected_department_id'])) {
    redirect('select-department.php');
}

$department_id = $_SESSION['selected_department_id'];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Get department info
$stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
$stmt->execute([$department_id]);
$dept = $stmt->fetch();
$department_name = $dept['department_name'] ?? 'Department';

// First, get all students with their clearance status
$sql = "
    SELECT 
        u.id, 
        u.full_name, 
        u.student_id, 
        u.email, 
        u.phone,
        u.profile_pic,
        u.created_at as registered_date,
        COUNT(DISTINCT ci.id) as total_items,
        COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) as completed_count,
        COUNT(DISTINCT CASE WHEN sc.status = 'pending' THEN ci.id END) as pending_count,
        COUNT(DISTINCT CASE WHEN sc.status = 'rejected' THEN ci.id END) as rejected_count,
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) = COUNT(DISTINCT ci.id) THEN 'approved'
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'rejected' THEN ci.id END) > 0 THEN 'rejected'
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'pending' THEN ci.id END) > 0 THEN 'pending'
            ELSE 'pending'
        END as status
    FROM users u
    INNER JOIN clearance_items ci ON ci.department_id = ?
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = u.id
    WHERE u.role = 'student' AND u.is_active = 1
    GROUP BY u.id, u.full_name, u.student_id, u.email, u.phone, u.profile_pic, u.created_at
    HAVING total_items > 0
";

$params = [$department_id];

// Apply filter - this filters the results AFTER grouping
$sql_filter = "";
if ($filter == 'pending') {
    $sql_filter = " AND status = 'pending'";
} elseif ($filter == 'approved') {
    $sql_filter = " AND status = 'approved'";
} elseif ($filter == 'rejected') {
    $sql_filter = " AND status = 'rejected'";
}

// We need to use a subquery or CTE for proper filtering with HAVING
$final_sql = "
    SELECT * FROM (
        $sql
    ) as student_data
    WHERE 1=1
    $sql_filter
    ORDER BY full_name ASC
";

$stmt = $pdo->prepare($final_sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get all students for counts (without filter)
$count_sql = "
    SELECT 
        u.id,
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) = COUNT(DISTINCT ci.id) THEN 'approved'
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'rejected' THEN ci.id END) > 0 THEN 'rejected'
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'pending' THEN ci.id END) > 0 THEN 'pending'
            ELSE 'pending'
        END as status
    FROM users u
    INNER JOIN clearance_items ci ON ci.department_id = ?
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = u.id
    WHERE u.role = 'student' AND u.is_active = 1
    GROUP BY u.id
    HAVING COUNT(DISTINCT ci.id) > 0
";

$stmt = $pdo->prepare($count_sql);
$stmt->execute([$department_id]);
$all_students_data = $stmt->fetchAll();

// Calculate accurate counts
$total_students = count($all_students_data);
$pending_count = count(array_filter($all_students_data, function($s) { return $s['status'] == 'pending'; }));
$approved_count = count(array_filter($all_students_data, function($s) { return $s['status'] == 'approved'; }));
$rejected_count = count(array_filter($all_students_data, function($s) { return $s['status'] == 'rejected'; }));

// Get unread messages count
$unread_messages = getUnreadMessageCount($pdo, $_SESSION['user_id']);

$page_title = 'Students - ' . htmlspecialchars($department_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?> - Department Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128,0,32,0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-300: #dce1e8;
            --gray-600: #6c7293;
            --gray-800: #2d3047;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
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
            box-shadow: 10px 0 30px rgba(128,0,32,0.2);
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
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
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
            font-size: 0.85rem;
        }
        
        /* Stats Summary Cards */
        .stats-summary {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 15px;
            min-width: 140px;
            flex: 1;
        }
        
        .stat-card .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }
        
        .stat-card .stat-icon.total { background: var(--primary-soft); color: var(--primary); }
        .stat-card .stat-icon.pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .stat-card .stat-icon.approved { background: rgba(16,185,129,0.1); color: var(--success); }
        .stat-card .stat-icon.rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .stat-card .stat-info h4 {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            color: var(--gray-800);
        }
        
        .stat-card .stat-info span {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
            cursor: pointer;
        }
        
        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }
        
        .filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(128,0,32,0.2);
        }
        
        /* Card */
        .card-modern {
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background: var(--gray-100);
            padding: 15px 18px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--gray-800);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table td {
            padding: 15px 18px;
            font-size: 0.85rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
        }
        
        /* Student Avatar in Table */
        .student-avatar-sm {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 700;
            font-size: 0.8rem;
            flex-shrink: 0;
        }
        
        .student-avatar-sm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-approved { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .progress-small {
            width: 100px;
            height: 5px;
            background: var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-small-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .btn-view {
            background: var(--info);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-view:hover {
            transform: translateY(-1px);
            color: white;
            background: #2c6bcf;
        }
        
        .btn-chat {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-chat:hover {
            transform: translateY(-1px);
            color: white;
            background: var(--primary-dark);
        }
        
        /* DataTables Custom Styling */
        .dataTables_wrapper {
            padding: 15px 20px;
        }
        
        .dataTables_length {
            margin-bottom: 15px;
        }
        
        .dataTables_length label {
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        
        .dataTables_length select {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 4px 8px;
            margin: 0 5px;
        }
        
        .dataTables_filter {
            margin-bottom: 15px;
        }
        
        .dataTables_filter label {
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        
        .dataTables_filter input {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 6px 12px;
            margin-left: 8px;
        }
        
        .dataTables_filter input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px var(--primary-soft);
        }
        
        .dataTables_info {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-top: 15px;
        }
        
        .dataTables_paginate {
            margin-top: 15px;
        }
        
        .paginate_button {
            padding: 6px 12px;
            margin: 0 3px;
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-700);
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .paginate_button:hover {
            background: var(--primary-soft);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .paginate_button.current {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .paginate_button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header h2 {
                font-size: 1.4rem;
            }
            
            .stats-summary {
                flex-direction: column;
            }
            
            .stat-card {
                min-width: 100%;
            }
            
            .filter-buttons {
                width: 100%;
                justify-content: flex-start;
            }
            
            .filter-btn {
                padding: 6px 14px;
                font-size: 0.75rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-view, .btn-chat {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
            
            .student-info {
                flex-wrap: wrap;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .table th, .table td {
                padding: 10px 12px;
                font-size: 0.8rem;
            }
            
            .progress-small {
                width: 70px;
            }
            
            .student-avatar-sm {
                width: 28px;
                height: 28px;
                font-size: 0.7rem;
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
            <p>Department Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="clearance-items/index.php" class="menu-item"><i class="fas fa-list-check"></i> Clearance Items</a>
            <a href="students.php" class="menu-item active"><i class="fas fa-users"></i> Students</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="message.php" class="menu-item">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="notification-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <div class="menu-divider"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
            <a href="change-password.php" class="menu-item"><i class="fas fa-key"></i> Change Password</a>
            <a href="../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <div>
                <h2><i class="fas fa-users me-2"></i> Students</h2>
                <p><?php echo htmlspecialchars($department_name); ?> - Student Clearance Status</p>
            </div>
            <div class="filter-buttons">
                <a href="?filter=all" class="filter-btn <?php echo $filter == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-users me-1"></i> All (<?php echo $total_students; ?>)
                </a>
                <a href="?filter=pending" class="filter-btn <?php echo $filter == 'pending' ? 'active' : ''; ?>">
                    <i class="fas fa-clock me-1"></i> Pending (<?php echo $pending_count; ?>)
                </a>
                <a href="?filter=approved" class="filter-btn <?php echo $filter == 'approved' ? 'active' : ''; ?>">
                    <i class="fas fa-check-circle me-1"></i> Approved (<?php echo $approved_count; ?>)
                </a>
                <a href="?filter=rejected" class="filter-btn <?php echo $filter == 'rejected' ? 'active' : ''; ?>">
                    <i class="fas fa-times-circle me-1"></i> Rejected (<?php echo $rejected_count; ?>)
                </a>
            </div>
        </div>
        
        <!-- Stats Summary Cards -->
        <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-icon total"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <h4><?php echo $total_students; ?></h4>
                    <span>Total Students</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending"><i class="fas fa-clock"></i></div>
                <div class="stat-info">
                    <h4><?php echo $pending_count; ?></h4>
                    <span>Pending</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon approved"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h4><?php echo $approved_count; ?></h4>
                    <span>Approved</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon rejected"><i class="fas fa-times-circle"></i></div>
                <div class="stat-info">
                    <h4><?php echo $rejected_count; ?></h4>
                    <span>Rejected</span>
                </div>
            </div>
        </div>
        
        <div class="card-modern">
            <div class="table-responsive">
                <table id="studentsTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $student): 
                                $progress_percent = $student['total_items'] > 0 ? round(($student['completed_count'] / $student['total_items']) * 100, 1) : 0;
                                
                                // Check if student has a profile picture
                                $student_pic = $student['profile_pic'] ?? 'default-avatar.png';
                                $student_pic_exists = !empty($student_pic) && $student_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $student_pic);
                                $student_initials = strtoupper(substr($student['full_name'], 0, 1));
                                
                                // Determine status text and icon
                                $status_text = ucfirst($student['status']);
                                $status_icon = '';
                                if ($student['status'] == 'approved') {
                                    $status_icon = '<i class="fas fa-check-circle me-1"></i>';
                                } elseif ($student['status'] == 'rejected') {
                                    $status_icon = '<i class="fas fa-times-circle me-1"></i>';
                                } else {
                                    $status_icon = '<i class="fas fa-clock me-1"></i>';
                                }
                            ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar-sm">
                                            <?php if ($student_pic_exists): ?>
                                                <img src="../assets/uploads/Students-profile/<?php echo $student_pic; ?>" alt="<?php echo htmlspecialchars($student['full_name']); ?>">
                                            <?php else: ?>
                                                <?php echo $student_initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                            <?php if ($student['phone']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($student['phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress-small">
                                            <div class="progress-small-bar" style="width: <?php echo $progress_percent; ?>%"></div>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $student['completed_count']; ?>/<?php echo $student['total_items']; ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $student['status']; ?>">
                                        <?php echo $status_icon; ?> <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view-student.php?id=<?php echo $student['id']; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="message.php?student_id=<?php echo $student['id']; ?>" class="btn-chat">
                                            <i class="fas fa-comments"></i> Chat
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-users fa-3x mb-3" style="color: var(--gray-300);"></i>
                                    <p class="mb-2 text-muted">No students found for this filter</p>
                                    <small class="text-muted">Try changing the filter or check back later</small>
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
            $('#studentsTable').DataTable({
                pageLength: 15,
                order: [[0, 'asc']],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ students",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "<i class='fas fa-chevron-right'></i>",
                        previous: "<i class='fas fa-chevron-left'></i>"
                    }
                },
                drawCallback: function() {
                    // Style pagination buttons
                    $('.paginate_button').addClass('btn btn-sm');
                    $('.paginate_button.current').removeClass('btn-outline-secondary').addClass('btn-primary');
                }
            });
        });
    </script>
</body>
</html>