<?php
// ============================================================
// DEBUGGING - Enable error reporting to identify the HTTP 500
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_log("=== statistics.php loaded ===");

session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$page_title = 'Statistics Dashboard';

try {
    // ============================================================
    // 1. GET SYSTEM SETTINGS
    // ============================================================
    $settings = [];
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // ============================================================
    // 2. TOTAL STUDENTS
    // ============================================================
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1");
    $total_result = $stmt->fetch();
    $total_students = $total_result ? (int)$total_result['count'] : 0;

    // Get all active departments
    $stmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY clearance_order ASC");
    $active_departments = $stmt->fetchAll();
    $total_departments = count($active_departments);
    $department_ids = array_column($active_departments, 'id');

    // ============================================================
    // 3. STATUS DISTRIBUTION (Overall - across all clearance records)
    // ============================================================
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM student_clearance
    ");
    $status_distribution = $stmt->fetch();
    if (!$status_distribution) {
        $status_distribution = ['approved' => 0, 'pending' => 0, 'rejected' => 0];
    }

    // ============================================================
    // 4. DEPARTMENT PERFORMANCE
    // ============================================================
    $stmt = $pdo->query("
        SELECT 
            d.id,
            d.department_name,
            COUNT(DISTINCT sc.student_id) as applications,
            SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            ROUND(AVG(CASE WHEN sc.status = 'approved' THEN DATEDIFF(sc.reviewed_at, sc.created_at) END), 1) as avg_days
        FROM departments d
        LEFT JOIN clearance_items ci ON d.id = ci.department_id
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id
        WHERE d.is_active = 1
        GROUP BY d.id
    ");
    $department_performance_raw = $stmt->fetchAll();

    $department_performance = [];
    foreach ($department_performance_raw as $dept) {
        $applications = $total_students;
        $approved = (int)$dept['approved'];
        $rejected = (int)$dept['rejected'];
        $pending = $applications - ($approved + $rejected);
        if ($pending < 0) $pending = 0;
        $approval_rate = $applications > 0 ? round(($approved / $applications) * 100, 1) : 0;
        
        $department_performance[] = [
            'id' => $dept['id'],
            'department_name' => $dept['department_name'],
            'applications' => $applications,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'avg_days' => $dept['avg_days'] ? number_format((float)$dept['avg_days'], 1) : '—',
            'approval_rate' => $approval_rate
        ];
    }

    usort($department_performance, function($a, $b) {
        return $b['approval_rate'] - $a['approval_rate'];
    });

    // ============================================================
    // 5. DEPARTMENTS REQUIRING ATTENTION
    // ============================================================
    $stmt = $pdo->query("
        SELECT 
            d.id,
            d.department_name,
            SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            MIN(CASE WHEN sc.status = 'pending' THEN sc.created_at END) as oldest_pending
        FROM departments d
        LEFT JOIN clearance_items ci ON d.id = ci.department_id
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id
        WHERE d.is_active = 1
        GROUP BY d.id
    ");
    $all_departments_attention = $stmt->fetchAll();

    $attention_departments = [];
    foreach ($all_departments_attention as $dept) {
        $approved = (int)$dept['approved'];
        $rejected = (int)$dept['rejected'];
        $pending = $total_students - ($approved + $rejected);
        if ($pending < 0) $pending = 0;
        
        $status = 'normal';
        $status_label = 'Normal';
        $status_class = 'status-normal';
        $status_icon = '🟢';
        
        if ($rejected > 0) {
            $status = 'critical';
            $status_label = 'Critical';
            $status_class = 'status-critical';
            $status_icon = '🔴';
        } elseif ($pending > 0) {
            $status = 'moderate';
            $status_label = 'Attention Needed';
            $status_class = 'status-moderate';
            $status_icon = '🟡';
        }
        
        $attention_departments[] = [
            'department_name' => $dept['department_name'],
            'applications' => $total_students,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'status' => $status,
            'status_label' => $status_label,
            'status_class' => $status_class,
            'status_icon' => $status_icon
        ];
    }

    usort($attention_departments, function($a, $b) {
        $order = ['critical' => 0, 'moderate' => 1, 'normal' => 2];
        return $order[$a['status']] - $order[$b['status']];
    });

    // ============================================================
    // 6. SYSTEM OVERVIEW
    // ============================================================
    $stmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
            SUM(CASE WHEN role = 'department_head' THEN 1 ELSE 0 END) as dept_heads,
            SUM(CASE WHEN role = 'registrar' THEN 1 ELSE 0 END) as registrars,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students
        FROM users
        WHERE is_active = 1
    ");
    $system_users = $stmt->fetch();
    if (!$system_users) {
        $system_users = ['admins' => 0, 'dept_heads' => 0, 'registrars' => 0, 'students' => 0];
    }

    $stmt = $pdo->query("SELECT MAX(last_login) as last_login FROM users");
    $last_login_result = $stmt->fetch();
    $last_login = $last_login_result ? $last_login_result['last_login'] : null;

    $page_title = 'Statistics Dashboard';

} catch (PDOException $e) {
    error_log("Database error in statistics.php: " . $e->getMessage());
    die("Database error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("General error in statistics.php: " . $e->getMessage());
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --gold: #c9a84c;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
            min-height: 100vh;
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
        .mobile-toggle:hover { background: var(--primary-dark); }
        
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
        
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }
        
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
        
        .sidebar-header h4 { font-size: 1.2rem; font-weight: 600; margin: 10px 0 5px; }
        .sidebar-header p { font-size: 0.75rem; opacity: 0.7; margin: 0; }
        
        .sidebar-menu { padding: 0 15px 20px; }
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
        .menu-item i { width: 22px; font-size: 1rem; }
        .menu-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 20px 0; }
        
        .main-content {
            margin-left: 280px;
            padding: 30px 40px;
            transition: all 0.3s;
            min-height: 100vh;
        }
        
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
        .sidebar-overlay.active { display: block; }
        
        .page-header {
            margin-bottom: 30px;
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
        .page-header h2 i { color: var(--primary); font-size: 1.8rem; }
        .page-header p { color: var(--gray-600); margin: 6px 0 0 0; font-size: 0.95rem; }
        
        .card-modern {
            background: white;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            margin-bottom: 30px;
        }
        .card-header-custom {
            padding: 18px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .card-header-custom h5 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
            color: var(--gray-800);
        }
        .card-header-custom h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        .card-body { padding: 20px 24px; }
        
        .dept-table {
            width: 100%;
            border-collapse: collapse;
        }
        .dept-table th {
            background: var(--gray-50);
            padding: 10px 14px;
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--gray-700);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        .dept-table td {
            padding: 10px 14px;
            font-size: 0.8rem;
            border-bottom: 1px solid var(--gray-200);
        }
        .dept-table tr:hover td {
            background: var(--gray-50);
        }
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 0.75rem;
            background: var(--gray-200);
            color: var(--gray-700);
        }
        .rank-1 { background: var(--gold); color: white; }
        .rank-2 { background: var(--gray-400); color: white; }
        .rank-3 { background: #cd7f32; color: white; }
        
        .approval-rate-high { color: var(--success); font-weight: 700; }
        .approval-rate-medium { color: var(--warning); font-weight: 700; }
        .approval-rate-low { color: var(--danger); font-weight: 700; }
        
        .status-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 6px;
        }
        .status-normal { background: var(--success); }
        .status-moderate { background: var(--warning); }
        .status-critical { background: var(--danger); }
        
        .status-text-critical { color: var(--danger); font-weight: 600; }
        .status-text-moderate { color: var(--warning); font-weight: 600; }
        .status-text-normal { color: var(--success); font-weight: 600; }
        
        .chart-container {
            position: relative;
            height: 280px;
        }
        .chart-container-sm {
            height: 220px;
        }
        
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .system-info-item {
            padding: 12px 16px;
            background: var(--gray-50);
            border-radius: 10px;
        }
        .system-info-item .label {
            font-size: 0.7rem;
            color: var(--gray-600);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .system-info-item .value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-top: 2px;
        }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media (max-width: 768px) {
            .mobile-toggle { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 18px; }
            .chart-container { height: 220px; }
            .dept-table { display: block; overflow-x: auto; }
            .system-info-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 15px 14px; }
            .system-info-grid { grid-template-columns: 1fr; }
            .card-body { padding: 15px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeInUp 0.4s ease forwards; }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
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
            <a href="index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="statistics.php" class="menu-item active"><i class="fas fa-chart-pie"></i> Statistics</a>
            <a href="../settings/index.php" class="menu-item"><i class="fas fa-cog"></i> Settings</a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <div class="page-header animate-in">
            <h2><i class="fas fa-chart-pie"></i> Statistics Dashboard</h2>
            <p>Executive overview of the graduation clearance process</p>
        </div>
        
        <div class="row g-4">
            <div class="col-lg-12">
                <div class="card-modern animate-in" style="animation-delay: 0.5s;">
                    <div class="card-header-custom">
                        <h5><i class="fas fa-chart-pie"></i> Clearance Status Distribution</h5>
                        <span class="text-muted small">Total: <?php echo array_sum((array)$status_distribution); ?> records</span>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="max-width: 500px; margin: 0 auto;">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card-modern animate-in" style="animation-delay: 0.6s;">
            <div class="card-header-custom">
                <h5><i class="fas fa-trophy"></i> Department Performance Analytics</h5>
                <span class="text-muted small"><?php echo count($department_performance); ?> departments | <?php echo $total_students; ?> total students</span>
            </div>
            <div class="card-body" style="overflow-x: auto;">
                <table class="dept-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Department</th>
                            <th style="text-align:center;">Applications</th>
                            <th style="text-align:center;">Approved</th>
                            <th style="text-align:center;">Pending</th>
                            <th style="text-align:center;">Rejected</th>
                            <th style="text-align:center;">Avg. Days</th>
                            <th style="text-align:center;">Approval Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $rank = 1; foreach ($department_performance as $dept): 
                            $rate_class = $dept['approval_rate'] >= 80 ? 'approval-rate-high' : ($dept['approval_rate'] >= 50 ? 'approval-rate-medium' : 'approval-rate-low');
                            $rank_class = $rank == 1 ? 'rank-1' : ($rank == 2 ? 'rank-2' : ($rank == 3 ? 'rank-3' : ''));
                        ?>
                        <tr>
                            <td><span class="rank-badge <?php echo $rank_class; ?>"><?php echo $rank++; ?></span></td>
                            <td><strong><?php echo htmlspecialchars($dept['department_name'] ?? 'Unknown'); ?></strong></td>
                            <td style="text-align:center; font-weight: 700; color: var(--primary);"><?php echo number_format($dept['applications']); ?></td>
                            <td style="text-align:center; color: var(--success);"><?php echo $dept['approved']; ?></td>
                            <td style="text-align:center; color: var(--warning);"><?php echo $dept['pending']; ?></td>
                            <td style="text-align:center; color: var(--danger);"><?php echo $dept['rejected']; ?></td>
                            <td style="text-align:center;"><?php echo $dept['avg_days']; ?></td>
                            <td style="text-align:center;">
                                <span class="<?php echo $rate_class; ?>">
                                    <?php echo number_format($dept['approval_rate'], 1); ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-modern animate-in" style="animation-delay: 0.65s;">
            <div class="card-header-custom">
                <h5><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> Departments Requiring Attention</h5>
                <span class="text-muted small"><?php echo count($attention_departments); ?> departments</span>
            </div>
            <div class="card-body" style="overflow-x: auto;">
                <table class="dept-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th style="text-align:center;">Applications</th>
                            <th style="text-align:center;">Approved</th>
                            <th style="text-align:center;">Pending</th>
                            <th style="text-align:center;">Rejected</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attention_departments as $dept): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($dept['department_name'] ?? 'Unknown'); ?></strong></td>
                            <td style="text-align:center; font-weight: 700; color: var(--primary);"><?php echo number_format($dept['applications']); ?></td>
                            <td style="text-align:center; color: var(--success);"><?php echo $dept['approved']; ?></td>
                            <td style="text-align:center; color: var(--warning);"><?php echo $dept['pending']; ?></td>
                            <td style="text-align:center; color: var(--danger);"><?php echo $dept['rejected']; ?></td>
                            <td>
                                <span class="status-dot <?php echo $dept['status_class']; ?>"></span>
                                <span class="status-text-<?php echo $dept['status']; ?>">
                                    <?php echo $dept['status_icon']; ?> <?php echo $dept['status_label']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card-modern animate-in" style="animation-delay: 0.75s;">
            <div class="card-header-custom">
                <h5><i class="fas fa-info-circle"></i> System Overview</h5>
            </div>
            <div class="card-body">
                <div class="system-info-grid">
                    <div class="system-info-item">
                        <div class="label">Graduation Clearance Status</div>
                        <div class="value">
                            <span class="status-dot" style="background: var(--success);"></span>
                            Active
                        </div>
                    </div>
                    <div class="system-info-item">
                        <div class="label">Academic Year</div>
                        <div class="value"><?php echo htmlspecialchars($settings['academic_year'] ?? 'Not Set'); ?></div>
                    </div>
                    <div class="system-info-item">
                        <div class="label">Graduation Year</div>
                        <div class="value"><?php echo htmlspecialchars($settings['graduation_year'] ?? 'Not Set'); ?></div>
                    </div>
                    <div class="system-info-item">
                        <div class="label">Total Users</div>
                        <div class="value"><?php echo number_format($total_students + $system_users['admins'] + $system_users['dept_heads'] + $system_users['registrars']); ?></div>
                    </div>
                    <div class="system-info-item">
                        <div class="label">Administrators</div>
                        <div class="value"><?php echo $system_users['admins']; ?></div>
                    </div>
                    <div class="system-info-item">
                        <div class="label">Department Heads</div>
                        <div class="value"><?php echo $system_users['dept_heads']; ?></div>
                    </div>
                    <div class="system-info-item">
                        <div class="label">Students</div>
                        <div class="value"><?php echo number_format($system_users['students']); ?></div>
                    </div>
                    <div class="system-info-item">
                        <div class="label">Last System Login</div>
                        <div class="value"><?php echo $last_login ? date('M d, Y h:i A', strtotime($last_login)) : 'Never'; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) closeSidebar();
        });
        
        const primary = '#800020';
        const success = '#10b981';
        const warning = '#f59e0b';
        const danger = '#ef4444';
        
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [<?php echo $status_distribution['approved'] ?? 0; ?>, <?php echo $status_distribution['pending'] ?? 0; ?>, <?php echo $status_distribution['rejected'] ?? 0; ?>],
                    backgroundColor: [success, warning, danger],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { size: 12 },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let total = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    </script>
</body>
</html>