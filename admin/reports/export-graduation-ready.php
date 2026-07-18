<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Get filter parameters
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'excel';

// Get departments for filter
$stmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

// Query to get students who have completed ALL clearances
$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.student_id,
        u.email,
        u.phone,
        u.created_at as registered_date,
        COUNT(DISTINCT ci.id) as total_requirements,
        COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) as completed_requirements,
        SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        MAX(sc.updated_at) as last_activity,
        ROUND((COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) / NULLIF(COUNT(DISTINCT ci.id), 0)) * 100, 1) as completion_percentage,
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) = COUNT(DISTINCT ci.id) THEN 'ready'
            WHEN SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
            ELSE 'in_progress'
        END as graduation_status
    FROM users u
    CROSS JOIN clearance_items ci
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = u.id
    WHERE u.role = 'student'
    GROUP BY u.id
    HAVING graduation_status = 'ready'
";

$params = [];

if ($department_filter > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM clearance_items ci2 WHERE ci2.department_id = ?)";
    $params[] = $department_filter;
}

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get department name for header
$dept_name = 'All Departments';
if ($department_filter > 0) {
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $stmt->execute([$department_filter]);
    $dept = $stmt->fetch();
    if ($dept) {
        $dept_name = $dept['department_name'];
    }
}

// Get total students count
$total_ready = count($students);

// Get all students count for completion rate
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$total_students = $stmt->fetch()['count'];

$completion_rate = $total_students > 0 ? round(($total_ready / $total_students) * 100, 1) : 0;

// Handle Excel Export
if ($format == 'excel' || !isset($format)) {
    exportExcel($students, $dept_name, $search, $total_ready, $total_students, $completion_rate);
    exit();
}

// ============================================
// EXCEL EXPORT FUNCTION
// ============================================
function exportExcel($students, $department_name, $search, $total_ready, $total_students, $completion_rate) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Graduation_Ready_Students_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Graduation Ready</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        .header-title { font-size: 20px; font-weight: bold; color: #800020; text-align: center; }
        .header-sub { text-align: center; font-size: 14px; font-weight: bold; }
        .header-dept { text-align: center; font-size: 16px; font-weight: bold; color: #800020; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th { background: #800020; color: white; padding: 8px; text-align: left; border: 1px solid #000; font-weight: bold; }
        td { padding: 8px; border: 1px solid #999; }
        .status-ready { color: #10b981; font-weight: bold; }
        .status-in-progress { color: #f59e0b; font-weight: bold; }
        .status-rejected { color: #ef4444; font-weight: bold; }
        .footer-text { text-align: center; font-style: italic; font-size: 11px; color: #999; margin-top: 20px; }
        .summary-text { font-weight: bold; font-size: 12px; margin-top: 10px; }
        .certificate-badge { color: #10b981; font-weight: bold; }
    </style>';
    echo '</head><body>';
    
    // Header
    echo '<table>';
    echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 20px; font-weight: bold; color: #800020;">Graduation Clearance System</td></tr>';
    echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 16px; font-weight: bold;">Graduation Ready Students Report</td></tr>';
    echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 14px; font-weight: bold; color: #800020;">Department: ' . htmlspecialchars($department_name) . '</td></tr>';
    if (!empty($search)) {
        echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 12px;">Search: ' . htmlspecialchars($search) . '</td></tr>';
    }
    echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 12px; padding-bottom: 15px;">Generated: ' . date('F d, Y h:i A') . '</td></tr>';
    echo '</table>';
    
    if (empty($students)) {
        echo '<p style="text-align: center; padding: 50px; font-size: 16px; color: #999;">No students have completed all clearance requirements yet</p>';
    } else {
        // Summary
        echo '<table>';
        echo '<tr><td colspan="8" style="border: none; font-size: 12px;">';
        echo '<strong>Summary:</strong> ';
        echo 'Total Ready: ' . $total_ready . ' | ';
        echo 'Total Students: ' . number_format($total_students) . ' | ';
        echo 'Completion Rate: ' . $completion_rate . '%';
        echo '</td></tr>';
        echo '</table><br>';
        
        // Data Table
        echo '<table>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>Student Name</th>';
        echo '<th>Student ID</th>';
        echo '<th>Email</th>';
        echo '<th>Phone</th>';
        echo '<th>Registered Date</th>';
        echo '<th>Requirements</th>';
        echo '<th>Completion Rate</th>';
        echo '<th>Status</th>';
        echo '</tr>';
        
        $counter = 1;
        foreach ($students as $student) {
            echo '<tr>';
            echo '<td>' . $counter++ . '</td>';
            echo '<td>' . htmlspecialchars($student['full_name']) . '</td>';
            echo '<td>' . $student['student_id'] . '</td>';
            echo '<td>' . $student['email'] . '</td>';
            echo '<td>' . ($student['phone'] ?? 'N/A') . '</td>';
            echo '<td>' . date('M d, Y', strtotime($student['registered_date'])) . '</td>';
            echo '<td>' . $student['completed_requirements'] . '/' . $student['total_requirements'] . '</td>';
            echo '<td>' . $student['completion_percentage'] . '%</td>';
            echo '<td class="status-ready"><span style="background: rgba(16,185,129,0.1); padding: 2px 8px; border-radius: 10px;">✅ Ready for Graduation</span></td>';
            echo '</tr>';
        }
        echo '</table>';
        
        // Footer
        echo '<br><table>';
        echo '<tr><td colspan="8" style="border: none; text-align: center; font-style: italic; font-size: 11px; color: #999;">';
        echo '© ' . date('Y') . ' Graduation Clearance System';
        echo '<br>Generated on ' . date('F d, Y h:i A');
        echo '</td></tr>';
        echo '</table>';
    }
    
    echo '</body></html>';
    exit();
}
?>