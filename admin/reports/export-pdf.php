<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : 'summary';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get data for report
if ($type == 'summary') {
    // Overall statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $total_students = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE is_active = 1");
    $total_departments = $stmt->fetch()['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM clearance_items");
    $total_items = $stmt->fetch()['count'];
    
    // Clearance statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(sc.id) as total_clearances,
            SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            ROUND((SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) / NULLIF(COUNT(sc.id), 0)) * 100, 1) as approval_rate
        FROM student_clearance sc
        WHERE DATE(sc.created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$date_from, $date_to]);
    $clearance_stats = $stmt->fetch();
    
    // Department performance
    $stmt = $pdo->prepare("
        SELECT 
            d.department_name,
            d.department_code,
            COUNT(DISTINCT sc.id) as total_clearances,
            SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending,
            ROUND((SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT sc.id), 0)) * 100, 1) as completion_rate
        FROM departments d
        LEFT JOIN clearance_items ci ON d.id = ci.department_id
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id
        WHERE d.is_active = 1
        GROUP BY d.id
        ORDER BY completion_rate DESC
    ");
    $stmt->execute();
    $departments = $stmt->fetchAll();
}

// Set headers for PDF download
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="clearance_report_' . date('Y-m-d') . '.pdf"');

// HTML content for PDF
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clearance Report - <?php echo date('Y-m-d'); ?></title>
    <style>
        @page {
            size: A4;
            margin: 2cm;
        }
        
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #800020;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #800020;
            font-size: 24px;
            margin: 0;
        }
        
        .header p {
            color: #666;
            font-size: 12px;
            margin: 5px 0 0;
        }
        
        .report-info {
            margin-bottom: 20px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        
        .report-info table {
            width: 100%;
        }
        
        .report-info td {
            padding: 5px;
        }
        
        .section-title {
            color: #800020;
            font-size: 16px;
            font-weight: bold;
            margin: 20px 0 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
        }
        
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            flex: 1;
            min-width: 120px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #eee;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #800020;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th {
            background: #800020;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 11px;
        }
        
        td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .progress-bar-container {
            width: 150px;
            background: #eee;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-bar {
            height: 8px;
            background: #800020;
            border-radius: 10px;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #999;
        }
        
        .text-right {
            text-align: right;
        }
        
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .badge-approved { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-rejected { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Graduation Clearance System</h1>
        <p>Clearance Report - <?php echo date('F d, Y'); ?></p>
    </div>
    
    <div class="report-info">
        <table>
            <tr>
                <td><strong>Report Type:</strong> <?php echo ucfirst($type); ?> Report</td>
                <td class="text-right"><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></td>
            </tr>
            <tr>
                <td><strong>Date Range:</strong> <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></td>
                <td class="text-right"><strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?></td>
            </tr>
        </table>
    </div>
    
    <?php if ($type == 'summary'): ?>
    <!-- Summary Report -->
    <div class="section-title">System Overview</div>
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($total_students); ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $total_departments; ?></div>
            <div class="stat-label">Departments</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $total_items; ?></div>
            <div class="stat-label">Clearance Items</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo number_format($clearance_stats['total_clearances'] ?? 0); ?></div>
            <div class="stat-label">Total Clearances</div>
        </div>
    </div>
    
    <div class="section-title">Clearance Statistics</div>
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-number" style="color: #28a745;"><?php echo $clearance_stats['approved'] ?? 0; ?></div>
            <div class="stat-label">Approved</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: #ffc107;"><?php echo $clearance_stats['pending'] ?? 0; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-box">
            <div class="stat-number" style="color: #dc3545;"><?php echo $clearance_stats['rejected'] ?? 0; ?></div>
            <div class="stat-label">Rejected</div>
        </div>
        <div class="stat-box">
            <div class="stat-number"><?php echo $clearance_stats['approval_rate'] ?? 0; ?>%</div>
            <div class="stat-label">Approval Rate</div>
        </div>
    </div>
    
    <div class="section-title">Department Performance</div>
    <table>
        <thead>
            <tr>
                <th>Department</th>
                <th>Code</th>
                <th>Clearances</th>
                <th>Approved</th>
                <th>Pending</th>
                <th>Completion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($departments as $dept): ?>
            <tr>
                <td><?php echo htmlspecialchars($dept['department_name']); ?></td>
                <td><?php echo $dept['department_code']; ?></td>
                <td><?php echo $dept['total_clearances']; ?></td>
                <td><span style="color: #28a45;"><?php echo $dept['approved']; ?></span></td>
                <td><span style="color: #ffc107;"><?php echo $dept['pending']; ?></span></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="progress-bar-container"><div class="progress-bar" style="width: <?php echo $dept['completion_rate']; ?>%"></div></div>
                        <span><?php echo $dept['completion_rate']; ?>%</span>
                    </div>
                 </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php else: ?>
    <!-- Detailed Report Placeholder -->
    <div class="section-title">Detailed Clearance Report</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Student</th>
                <th>Department</th>
                <th>Item</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $pdo->prepare("
                SELECT sc.*, u.full_name, u.student_id, d.department_name, ci.item_name
                FROM student_clearance sc
                JOIN users u ON sc.student_id = u.id
                JOIN clearance_items ci ON sc.clearance_item_id = ci.id
                JOIN departments d ON ci.department_id = d.id
                WHERE DATE(sc.created_at) BETWEEN ? AND ?
                ORDER BY sc.created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$date_from, $date_to]);
            $details = $stmt->fetchAll();
            ?>
            <?php foreach ($details as $detail): ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($detail['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($detail['full_name']); ?><br><small><?php echo $detail['student_id']; ?></small></td>
                <td><?php echo htmlspecialchars($detail['department_name']); ?></td>
                <td><?php echo htmlspecialchars($detail['item_name']); ?></td>
                <td>
                    <?php if ($detail['status'] == 'approved'): ?>
                        <span class="badge badge-approved">Approved</span>
                    <?php elseif ($detail['status'] == 'rejected'): ?>
                        <span class="badge badge-rejected">Rejected</span>
                    <?php else: ?>
                        <span class="badge badge-pending">Pending</span>
                    <?php endif; ?>
                 </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <div class="footer">
        <p>This report is system-generated. For any discrepancies, please contact the administrator.</p>
        <p>&copy; <?php echo date('Y'); ?> Graduation Clearance System. All rights reserved.</p>
    </div>
    
    <script>
        window.print();
    </script>
</body>
</html>