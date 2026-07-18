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
$department_name = $_SESSION['selected_department_name'];

// Get date filters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// ============================================================
// MAIN QUERY - Get ALL students with their clearance status
// ============================================================
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
        SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        MAX(sc.created_at) as applied_date,
        MAX(CASE WHEN sc.status = 'approved' THEN sc.reviewed_at END) as approval_date,
        MAX(CASE WHEN sc.status = 'rejected' THEN sc.reviewed_at END) as rejected_date,
        CASE 
            WHEN SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
            WHEN SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) = COUNT(DISTINCT ci.id) AND COUNT(DISTINCT ci.id) > 0 THEN 'approved'
            WHEN SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) > 0 THEN 'pending'
            WHEN SUM(CASE WHEN sc.status IS NULL THEN 1 ELSE 0 END) > 0 THEN 'pending'
            ELSE 'pending'
        END as status,
        GROUP_CONCAT(DISTINCT CASE WHEN sc.status = 'rejected' THEN sc.remarks END SEPARATOR ' | ') as rejection_reason,
        MAX(CASE WHEN sc.status = 'rejected' THEN sc.remarks END) as remarks
    FROM users u
    INNER JOIN clearance_items ci ON ci.department_id = ?
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = u.id
    WHERE u.role = 'student' AND u.is_active = 1
    GROUP BY u.id, u.full_name, u.student_id, u.email, u.phone, u.profile_pic, u.created_at
    HAVING total_items > 0
";

$params = [$department_id];
$sql .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_students = $stmt->fetchAll();

// ============================================================
// FILTER STUDENTS BY STATUS IN PHP
// ============================================================
$pending_students_all = array_filter($all_students, function($s) { 
    return $s['status'] == 'pending'; 
});

$approved_students_all = array_filter($all_students, function($s) { 
    return $s['status'] == 'approved'; 
});

$rejected_students_all = array_filter($all_students, function($s) { 
    return $s['status'] == 'rejected'; 
});

// Apply filters
$pending_students = $pending_students_all;
$approved_students = $approved_students_all;
$rejected_students = $rejected_students_all;

// For the main display
if ($status_filter == 'pending') {
    $students_to_display = $pending_students;
} elseif ($status_filter == 'approved') {
    $students_to_display = $approved_students;
} elseif ($status_filter == 'rejected') {
    $students_to_display = $rejected_students;
} else {
    $students_to_display = $all_students;
}

// Counts for stats cards
$total_count = count($all_students);
$pending_count = count($pending_students_all);
$approved_count = count($approved_students_all);
$rejected_count = count($rejected_students_all);

$unread_messages = getUnreadMessageCount($pdo, $_SESSION['user_id']);

$page_title = 'Reports - ' . htmlspecialchars($department_name);

// ============================================================
// HANDLE PRINT/PDF EXPORT
// ============================================================
if (isset($_GET['action']) && isset($_GET['report_type'])) {
    $action = $_GET['action'];
    $report_type = $_GET['report_type'];
    
    if ($report_type == 'all') {
        $report_data = $students_to_display;
        $report_title = 'All Students Report';
    } elseif ($report_type == 'pending') {
        $report_data = $pending_students;
        $report_title = 'Pending Clearances Report';
    } elseif ($report_type == 'rejected') {
        $report_data = $rejected_students;
        $report_title = 'Rejected Students Report';
    } else {
        die('Invalid report type');
    }
    
    // Build the HTML for the report
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title><?php echo $report_title; ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 3px solid #800020; padding-bottom: 10px; margin-bottom: 20px; }
            .header h1 { color: #800020; margin: 0; font-size: 22px; }
            .header p { color: #666; margin: 5px 0; font-size: 14px; }
            .back-button { 
                display: inline-block; 
                margin-bottom: 15px; 
                padding: 8px 20px; 
                background: #6c7293; 
                color: white; 
                text-decoration: none; 
                border-radius: 6px; 
                font-size: 14px;
            }
            .back-button:hover { background: #5a5f7a; color: white; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th { background: #800020; color: white; padding: 10px; text-align: left; font-size: 12px; }
            td { padding: 8px 10px; border-bottom: 1px solid #ddd; font-size: 12px; }
            tr:nth-child(even) { background: #f9f9f9; }
            .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 11px; font-weight: bold; }
            .status-approved { background: #d4edda; color: #155724; }
            .status-pending { background: #fff3cd; color: #856404; }
            .status-rejected { background: #f8d7da; color: #721c24; }
            .footer { text-align: center; margin-top: 30px; font-size: 11px; color: #999; border-top: 1px solid #ddd; padding-top: 10px; }
            .badge { background: #e9ecef; padding: 3px 8px; border-radius: 4px; font-size: 11px; }
            .text-muted { color: #6c757d; }
            .print-actions {
                text-align: center;
                margin-bottom: 20px;
                padding: 15px;
                background: #f8f9fc;
                border-radius: 8px;
                border: 1px solid #e4e7ef;
            }
            .print-actions .btn {
                display: inline-block;
                padding: 10px 25px;
                margin: 0 8px;
                border-radius: 6px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                border: none;
            }
            .btn-print { background: #6c7293; color: white; }
            .btn-print:hover { background: #5a5f7a; }
            .btn-back { background: #800020; color: white; }
            .btn-back:hover { background: #5a0016; }
            @media print {
                .no-print { display: none !important; }
                body { margin: 10px; }
                th { background: #800020 !important; color: white !important; }
                .print-actions { display: none !important; }
                .back-button { display: none !important; }
            }
        </style>
    </head>
    <body>
        <!-- Back Button - Only shown when not printing -->
        <div class="no-print">
            <a href="reports.php?date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
        </div>
        
        <div class="header">
            <h1><?php echo $report_title; ?></h1>
            <p><?php echo htmlspecialchars($department_name); ?></p>
            <p>Date Range: <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?> | Total: <?php echo count($report_data); ?> records</p>
        </div>
        
        <!-- Print Actions -->
        <div class="print-actions no-print">
            <button onclick="window.print()" class="btn btn-print">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="reports.php?date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Email</th>
                    <th>Applied Date</th>
                    <th>Progress</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($report_data)): ?>
                    <?php $i = 1; foreach ($report_data as $student): 
                        $progress_percent = $student['total_items'] > 0 ? round(($student['approved_count'] / $student['total_items']) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td><?php echo $student['applied_date'] ? date('M d, Y', strtotime($student['applied_date'])) : '—'; ?></td>
                            <td><?php echo $student['approved_count']; ?>/<?php echo $student['total_items']; ?> (<?php echo $progress_percent; ?>%)</td>
                            <td>
                                <span class="status-badge status-<?php echo $student['status']; ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                            No records found
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <div class="footer">
            <p>Generated on <?php echo date('F d, Y h:i A'); ?> | Graduation Clearance System</p>
        </div>
        
        <?php if ($action == 'print'): ?>
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    $html_content = ob_get_clean();
    
    if ($action == 'print') {
        echo $html_content;
        exit;
    } elseif ($action == 'download_pdf') {
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $report_title)) . '_' . date('Y-m-d') . '.html"');
        echo $html_content;
        exit;
    }
}
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128,0,32,0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
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
        
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
            transition: all 0.3s;
        }
        
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
            transition: all 0.3s;
            border: 1px solid rgba(0,0,0,0.04);
            text-decoration: none;
            color: inherit;
            display: block;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(128,0,32,0.1);
            text-decoration: none;
            color: inherit;
        }
        
        .stat-card.active-filter {
            border-color: var(--primary);
            background: var(--primary-soft);
            border-width: 2px;
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
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.75rem;
            margin-top: 5px;
        }
        
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-soft);
        }
        
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.04);
            min-height: 320px;
        }
        
        .chart-container canvas {
            max-height: 280px;
        }
        
        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-title i {
            color: var(--primary);
        }
        
        .chart-subtitle {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-left: auto;
            font-weight: 400;
        }
        
        .report-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
        }
        
        .report-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: white;
        }
        
        .report-header h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .report-header h5 i {
            font-size: 1.1rem;
        }
        
        .report-header .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge-count {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            background: var(--gray-100);
            color: var(--gray-600);
        }
        
        .btn-report-action {
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        
        .btn-report-action:hover {
            transform: translateY(-2px);
            text-decoration: none;
            color: white;
        }
        
        .btn-report-action.print {
            background: #6c7293;
            color: white;
        }
        .btn-report-action.print:hover { background: #5a5f7a; }
        
        .btn-report-action.pdf {
            background: #dc3545;
            color: white;
        }
        .btn-report-action.pdf:hover { background: #c82333; }
        
        .btn-report-action i {
            font-size: 0.85rem;
        }
        
        .btn-clear-filter {
            background: var(--gray-600);
            color: white;
            border: none;
            padding: 5px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
        
        .btn-clear-filter:hover {
            background: var(--gray-700);
            color: white;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            min-width: 700px;
        }
        
        .table th {
            background: var(--gray-100);
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.8rem;
            white-space: nowrap;
        }
        
        .table td {
            padding: 12px 15px;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-approved { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .btn-view {
            background: var(--info);
            color: white;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-view:hover {
            background: #2c6bcf;
            color: white;
        }
        
        .progress-small {
            width: 80px;
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
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 15px;
            display: block;
        }
        
        .empty-state h6 {
            color: var(--gray-600);
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .empty-state p {
            color: var(--gray-500);
            font-size: 0.85rem;
            margin: 0;
        }
        
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 20px;
            text-align: center;
            padding-top: 10px;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 8px 16px;
            margin: 0 4px;
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            background: white;
            color: var(--gray-700);
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 40px;
            text-align: center;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: var(--primary-soft);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128,0,32,0.12);
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-color: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(128,0,32,0.2);
            font-weight: 600;
        }
        
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        
        .dataTables_wrapper .dataTables_info {
            margin-top: 15px;
            font-size: 0.8rem;
            color: var(--gray-600);
            text-align: center;
        }
        
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 15px;
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 4px 8px;
            margin: 0 5px;
            background: white;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
        }
        
        .dataTables_wrapper .dataTables_filter label {
            font-size: 0.8rem;
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
        
        .dataTables_wrapper .table-hover tbody tr:hover {
            background: var(--primary-soft) !important;
        }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .mobile-toggle { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            
            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .report-header .header-actions {
                width: 100%;
                justify-content: flex-start;
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
        }
        
        @media (max-width: 480px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
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
            <p>Department Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="clearance-items/index.php" class="menu-item"><i class="fas fa-list-check"></i> Clearance Items</a>
            <a href="students.php" class="menu-item"><i class="fas fa-users"></i> Students</a>
            <a href="reports.php" class="menu-item active"><i class="fas fa-chart-bar"></i> Reports</a>
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
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <a href="?status=all&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="stat-card <?php echo $status_filter == 'all' ? 'active-filter' : ''; ?>">
                <div class="stat-icon" style="background: rgba(128,0,32,0.1); color: var(--primary);"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $total_count; ?></div>
                <div class="stat-label">Total Students</div>
            </a>
            <a href="?status=pending&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="stat-card <?php echo $status_filter == 'pending' ? 'active-filter' : ''; ?>">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending</div>
            </a>
            <a href="?status=approved&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="stat-card <?php echo $status_filter == 'approved' ? 'active-filter' : ''; ?>">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo $approved_count; ?></div>
                <div class="stat-label">Approved</div>
            </a>
            <a href="?status=rejected&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="stat-card <?php echo $status_filter == 'rejected' ? 'active-filter' : ''; ?>">
                <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: var(--danger);"><i class="fas fa-times-circle"></i></div>
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-label">Rejected</div>
            </a>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn w-100" style="background: var(--primary); color: white; border: none; padding: 10px; border-radius: 10px;">
                        <i class="fas fa-filter me-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Chart -->
        <div class="row g-4">
            <div class="col-md-6 mx-auto">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-chart-pie"></i> Clearance Status Distribution
                        <span class="chart-subtitle">Overall distribution</span>
                    </div>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- ALL STUDENTS REPORT -->
        <!-- ============================================================ -->
        <div class="report-card">
            <div class="report-header">
                <h5>
                    <i class="fas fa-users" style="color: var(--primary);"></i> 
                    All Students Report
                </h5>
                <div class="header-actions">
                    <span class="badge-count">
                        <i class="fas fa-users me-1"></i> <?php echo count($students_to_display); ?> records
                    </span>
                    <a href="?action=print&report_type=all&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>" target="_blank" class="btn-report-action print">
                        <i class="fas fa-print"></i> Print
                    </a>
                    <a href="?action=download_pdf&report_type=all&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&status=<?php echo $status_filter; ?>" class="btn-report-action pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table id="studentsTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Applied Date</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students_to_display)): ?>
                            <?php foreach ($students_to_display as $student): 
                                $progress_percent = $student['total_items'] > 0 ? round(($student['approved_count'] / $student['total_items']) * 100, 1) : 0;
                                $status_icon = $student['status'] == 'approved' ? 'fa-check-circle' : ($student['status'] == 'rejected' ? 'fa-times-circle' : 'fa-clock');
                            ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                        <?php if ($student['phone']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($student['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td><?php echo $student['applied_date'] ? date('M d, Y', strtotime($student['applied_date'])) : '—'; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress-small">
                                                <div class="progress-small-bar" style="width: <?php echo $progress_percent; ?>%;"></div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo $student['approved_count']; ?>/<?php echo $student['total_items']; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $student['status']; ?>">
                                            <i class="fas <?php echo $status_icon; ?> me-1"></i>
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view-student.php?id=<?php echo $student['id']; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <h6>No Records Found</h6>
                                        <p>No students found for this department.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- PENDING CLEARANCES & REJECTED STUDENTS - SIDE BY SIDE -->
        <!-- ============================================================ -->
        <div class="row g-4">
            <!-- Pending Clearances -->
            <div class="col-md-6">
                <div class="report-card">
                    <div class="report-header">
                        <h5>
                            <i class="fas fa-clock" style="color: var(--warning);"></i> 
                            Pending Clearances
                        </h5>
                        <div class="header-actions">
                            <span class="badge-count">
                                <i class="fas fa-clock me-1"></i> <?php echo count($pending_students); ?>
                            </span>
                            <a href="?action=print&report_type=pending&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" target="_blank" class="btn-report-action print">
                                <i class="fas fa-print"></i> Print
                            </a>
                            <a href="?action=download_pdf&report_type=pending&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn-report-action pdf">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Student ID</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($pending_students)): ?>
                                    <?php foreach ($pending_students as $student): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="status-badge status-pending">
                                                    <i class="fas fa-clock me-1"></i> Pending
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
                                                    <?php echo $student['pending_count']; ?>/<?php echo $student['total_items']; ?> pending
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view-student.php?id=<?php echo $student['id']; ?>" class="btn-view">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">
                                            <i class="fas fa-check-circle" style="color: var(--success);"></i> No pending clearances
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Rejected Students -->
            <div class="col-md-6">
                <div class="report-card">
                    <div class="report-header">
                        <h5>
                            <i class="fas fa-times-circle" style="color: var(--danger);"></i> 
                            Rejected Students
                        </h5>
                        <div class="header-actions">
                            <span class="badge-count">
                                <i class="fas fa-times-circle me-1"></i> <?php echo count($rejected_students); ?>
                            </span>
                            <a href="?action=print&report_type=rejected&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" target="_blank" class="btn-report-action print">
                                <i class="fas fa-print"></i> Print
                            </a>
                            <a href="?action=download_pdf&report_type=rejected&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn-report-action pdf">
                                <i class="fas fa-file-pdf"></i> PDF
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Student ID</th>
                                    <th>Reason</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($rejected_students)): ?>
                                    <?php foreach ($rejected_students as $student): 
                                        $reject_reason = $student['rejection_reason'] ?? $student['remarks'] ?? 'No reason provided';
                                    ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['student_id'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span style="font-size: 0.75rem; color: var(--gray-600);">
                                                    <?php echo substr(htmlspecialchars($reject_reason), 0, 60); ?>
                                                    <?php if (strlen($reject_reason) > 60): ?>...<?php endif; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $student['rejected_date'] ? date('M d, Y', strtotime($student['rejected_date'])) : '—'; ?></td>
                                            <td>
                                                <a href="view-student.php?id=<?php echo $student['id']; ?>" class="btn-view">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">
                                            <i class="fas fa-check-circle" style="color: var(--success);"></i> No rejected students
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
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
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        $(document).ready(function() {
            $('#studentsTable').DataTable({
                pageLength: 10,
                order: [[3, 'desc']],
                language: {
                    search: "<i class='fas fa-search'></i> Search:",
                    searchPlaceholder: "Search records...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ records",
                    infoEmpty: "No records available",
                    infoFiltered: "(filtered from _MAX_ total records)",
                    paginate: {
                        first: "<i class='fas fa-chevron-double-left'></i>",
                        last: "<i class='fas fa-chevron-double-right'></i>",
                        next: "<i class='fas fa-chevron-right'></i>",
                        previous: "<i class='fas fa-chevron-left'></i>"
                    }
                },
                drawCallback: function() {
                    $('.paginate_button').addClass('btn-page');
                }
            });
            
            $('.dataTables_filter input').attr('placeholder', 'Search records...');
        });
        
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [<?php echo $approved_count; ?>, <?php echo $pending_count; ?>, <?php echo $rejected_count; ?>],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: {
                            font: { size: 13 },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    }
                },
                cutout: '65%'
            }
        });
    </script>
</body>
</html>