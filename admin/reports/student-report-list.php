<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;

// Get departments for filter
$stmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

// Get all active department IDs
$dept_ids = array_column($departments, 'id');
$total_active_depts = count($dept_ids);

// ============================================================
// UPDATED: Status logic - Only Approved, Pending, Rejected
// ============================================================
$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.student_id,
        u.email,
        u.phone,
        u.created_at as registered_date,
        u.last_login,
        u.is_active,
        COUNT(DISTINCT ci.id) as total_requirements,
        COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) as completed_requirements,
        COUNT(DISTINCT CASE WHEN sc.status = 'pending' THEN ci.id END) as pending_requirements,
        COUNT(DISTINCT CASE WHEN sc.status = 'rejected' THEN ci.id END) as rejected_requirements,
        SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        ROUND((COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) / NULLIF(COUNT(DISTINCT ci.id), 0)) * 100, 1) as completion_percentage,
        CASE 
            WHEN SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'Rejected'
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) = COUNT(DISTINCT ci.id) AND COUNT(DISTINCT ci.id) > 0 THEN 'Approved'
            ELSE 'Pending'
        END as clearance_status,
        MAX(sc.updated_at) as last_activity
    FROM users u
    CROSS JOIN clearance_items ci
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = u.id
    WHERE u.role = 'student'
    GROUP BY u.id
";

$params = [];

if (!empty($search)) {
    $sql .= " HAVING u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($status_filter != 'all') {
    $sql .= " HAVING clearance_status = ?";
    $params[] = $status_filter;
}

if ($department_filter > 0) {
    $sql .= " AND EXISTS (SELECT 1 FROM clearance_items ci2 WHERE ci2.department_id = ?)";
    $params[] = $department_filter;
}

$sql .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get statistics
$total_students = count($students);
$approved_students = array_filter($students, function($s) { return $s['clearance_status'] == 'Approved'; });
$rejected_students = array_filter($students, function($s) { return $s['clearance_status'] == 'Rejected'; });
$pending_students = array_filter($students, function($s) { return $s['clearance_status'] == 'Pending'; });

// Handle export
if (isset($_GET['export']) && $_GET['export'] == 'students') {
    $format = isset($_GET['format']) ? $_GET['format'] : 'print';
    $export_search = isset($_GET['export_search']) ? sanitizeInput($_GET['export_search']) : '';
    $export_status = isset($_GET['export_status']) ? $_GET['export_status'] : 'all';
    $export_department = isset($_GET['export_department']) ? (int)$_GET['export_department'] : 0;
    
    // Build export query with updated status logic
    $export_sql = "
        SELECT 
            u.full_name,
            u.student_id,
            u.email,
            u.phone,
            u.created_at as registered_date,
            COUNT(DISTINCT ci.id) as total_requirements,
            COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) as completed_requirements,
            ROUND((COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) / NULLIF(COUNT(DISTINCT ci.id), 0)) * 100, 1) as completion_percentage,
            CASE 
                WHEN SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'Rejected'
                WHEN COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) = COUNT(DISTINCT ci.id) AND COUNT(DISTINCT ci.id) > 0 THEN 'Approved'
                ELSE 'Pending'
            END as clearance_status,
            MAX(sc.updated_at) as last_activity
        FROM users u
        CROSS JOIN clearance_items ci
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = u.id
        WHERE u.role = 'student'
        GROUP BY u.id
    ";
    
    $export_params = [];
    
    if (!empty($export_search)) {
        $export_sql .= " HAVING u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?";
        $search_term = "%$export_search%";
        $export_params[] = $search_term;
        $export_params[] = $search_term;
        $export_params[] = $search_term;
    }
    
    if ($export_status != 'all') {
        $export_sql .= " HAVING clearance_status = ?";
        $export_params[] = $export_status;
    }
    
    if ($export_department > 0) {
        $export_sql .= " AND EXISTS (SELECT 1 FROM clearance_items ci2 WHERE ci2.department_id = ?)";
        $export_params[] = $export_department;
    }
    
    $export_sql .= " ORDER BY u.full_name ASC";
    
    $stmt = $pdo->prepare($export_sql);
    $stmt->execute($export_params);
    $export_students = $stmt->fetchAll();
    
    // Get department name for export
    $dept_name = 'All Departments';
    if ($export_department > 0) {
        $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
        $stmt->execute([$export_department]);
        $dept = $stmt->fetch();
        if ($dept) {
            $dept_name = $dept['department_name'];
        }
    }
    
    if ($format == 'excel') {
        exportExcel($export_students, $dept_name, $export_search, $export_status);
        exit();
    } elseif ($format == 'pdf') {
        exportPDF($export_students, $dept_name, $export_search, $export_status);
        exit();
    } else {
        printReport($export_students, $dept_name, $export_search, $export_status);
        exit();
    }
}

$page_title = 'Student Report';

// ============================================
// PRINT FUNCTION
// ============================================
function printReport($students, $department_name, $search, $status) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Student Report - Graduation Clearance System</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #800020; margin-bottom: 20px; }
            .header h1 { color: #800020; margin: 0; font-size: 22px; }
            .header p { color: #666; margin: 5px 0; font-size: 12px; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th { background: #800020; color: white; padding: 8px 10px; text-align: left; font-size: 11px; }
            td { padding: 6px 10px; border-bottom: 1px solid #ddd; font-size: 11px; }
            .status-approved { color: #10b981; font-weight: bold; }
            .status-pending { color: #f59e0b; font-weight: bold; }
            .status-rejected { color: #ef4444; font-weight: bold; }
            .footer { text-align: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid #ddd; font-size: 10px; color: #999; }
            .summary { margin: 10px 0; display: flex; gap: 20px; flex-wrap: wrap; }
            .summary-item { background: #f8f9fc; padding: 5px 12px; border-radius: 5px; font-size: 11px; }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; }
            .badge-approved { background: rgba(16,185,129,0.15); color: #10b981; }
            .badge-pending { background: rgba(245,158,11,0.15); color: #f59e0b; }
            .badge-rejected { background: rgba(239,68,68,0.15); color: #ef4444; }
            @media print {
                body { margin: 10px; }
                th { background: #800020 !important; color: white !important; }
                .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>GRADUATION CLEARANCE SYSTEM</h1>
            <p><strong>Student Report</strong></p>
            <p>Department: <?php echo htmlspecialchars($department_name); ?></p>
            <?php if ($search): ?>
                <p>Search: <?php echo htmlspecialchars($search); ?></p>
            <?php endif; ?>
            <?php if ($status != 'all'): ?>
                <p>Status: <?php echo htmlspecialchars($status); ?></p>
            <?php endif; ?>
            <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
        </div>
        
        <?php if (empty($students)): ?>
            <div style="text-align: center; padding: 50px; color: #999;">
                <h3>No Records Found</h3>
                <p>No students found for the selected criteria.</p>
            </div>
        <?php else: ?>
            <div class="summary">
                <div class="summary-item"><strong>Total Students:</strong> <?php echo count($students); ?></div>
                <?php 
                $approved = array_filter($students, function($s) { return $s['clearance_status'] == 'Approved'; });
                $rejected = array_filter($students, function($s) { return $s['clearance_status'] == 'Rejected'; });
                $pending = array_filter($students, function($s) { return $s['clearance_status'] == 'Pending'; });
                ?>
                <div class="summary-item"><strong>Approved:</strong> <?php echo count($approved); ?></div>
                <div class="summary-item"><strong>Pending:</strong> <?php echo count($pending); ?></div>
                <div class="summary-item"><strong>Rejected:</strong> <?php echo count($rejected); ?></div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Student ID</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                        <td><?php echo $student['student_id']; ?></td>
                        <td><?php echo $student['email']; ?></td>
                        <td><?php echo $student['phone'] ?? '—'; ?></td>
                        <td><?php echo $student['completion_percentage']; ?>% (<?php echo $student['completed_requirements']; ?>/<?php echo $student['total_requirements']; ?>)</td>
                        <td><span class="badge badge-<?php echo strtolower($student['clearance_status']); ?>"><?php echo $student['clearance_status']; ?></span></td>
                        <td><?php echo $student['last_activity'] ? date('M d, Y', strtotime($student['last_activity'])) : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="footer">
            <p>© <?php echo date('Y'); ?> Graduation Clearance System - Student Report</p>
            <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
}

// ============================================
// EXCEL FUNCTION
// ============================================
function exportExcel($students, $department_name, $search, $status) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Student_Report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Student Report</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>
        table { border-collapse: collapse; width: 100%; }
        th { background: #800020; color: white; padding: 8px; text-align: left; border: 1px solid #000; }
        td { padding: 8px; border: 1px solid #999; }
        .status-approved { color: #10b981; font-weight: bold; }
        .status-pending { color: #f59e0b; font-weight: bold; }
        .status-rejected { color: #ef4444; font-weight: bold; }
    </style>';
    echo '</head><body>';
    
    // Header
    echo '<table>';
    echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 18px; font-weight: bold; color: #800020;">Graduation Clearance System</td></tr>';
    echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 14px; font-weight: bold;">Student Report</td></tr>';
    echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 12px;">Department: ' . htmlspecialchars($department_name) . '</td></tr>';
    echo '<tr><td colspan="8" style="text-align: center; border: none; font-size: 12px; padding-bottom: 15px;">Generated: ' . date('F d, Y h:i A') . '</td></tr>';
    echo '</table>';
    
    if (empty($students)) {
        echo '<p style="text-align: center; padding: 50px; font-size: 16px; color: #999;">No records found</p>';
    } else {
        // Summary
        $approved = array_filter($students, function($s) { return $s['clearance_status'] == 'Approved'; });
        $rejected = array_filter($students, function($s) { return $s['clearance_status'] == 'Rejected'; });
        $pending = array_filter($students, function($s) { return $s['clearance_status'] == 'Pending'; });
        
        echo '<table>';
        echo '<tr><td colspan="8" style="border: none;"><strong>Summary:</strong> Total: ' . count($students) . ' | Approved: ' . count($approved) . ' | Pending: ' . count($pending) . ' | Rejected: ' . count($rejected) . '</td></tr>';
        echo '</table><br>';
        
        // Data Table
        echo '<table>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>Student Name</th>';
        echo '<th>Student ID</th>';
        echo '<th>Email</th>';
        echo '<th>Phone</th>';
        echo '<th>Progress</th>';
        echo '<th>Status</th>';
        echo '<th>Last Activity</th>';
        echo '</tr>';
        
        $counter = 1;
        foreach ($students as $student) {
            $status_class = 'status-' . strtolower($student['clearance_status']);
            echo '<tr>';
            echo '<td>' . $counter++ . '</td>';
            echo '<td>' . htmlspecialchars($student['full_name']) . '</td>';
            echo '<td>' . $student['student_id'] . '</td>';
            echo '<td>' . $student['email'] . '</td>';
            echo '<td>' . ($student['phone'] ?? 'N/A') . '</td>';
            echo '<td>' . $student['completion_percentage'] . '% (' . $student['completed_requirements'] . '/' . $student['total_requirements'] . ')</td>';
            echo '<td class="' . $status_class . '">' . $student['clearance_status'] . '</td>';
            echo '<td>' . ($student['last_activity'] ? date('M d, Y', strtotime($student['last_activity'])) : '—') . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<br><table>';
        echo '<tr><td colspan="8" style="border: none; text-align: center; font-style: italic; font-size: 11px; color: #999;">';
        echo '© ' . date('Y') . ' Graduation Clearance System';
        echo '<br>Generated on ' . date('F d, Y h:i A');
        echo '</td></tr>';
        echo '</table>';
    }
    
    echo '</body></html>';
}

// ============================================
// PDF FUNCTION
// ============================================
function exportPDF($students, $department_name, $search, $status) {
    $filename = 'Student_Report_' . date('Y-m-d');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Student Report - Graduation Clearance System</title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: white; }
            .report-wrapper { max-width: 100%; margin: 0 auto; }
            .header { text-align: center; padding-bottom: 15px; border-bottom: 3px solid #800020; margin-bottom: 15px; }
            .header h1 { color: #800020; margin: 0; font-size: 20px; letter-spacing: 1px; }
            .header p { color: #555; margin: 3px 0; font-size: 12px; }
            .header .dept { color: #800020; font-weight: bold; font-size: 14px; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 11px; }
            th { background: #800020; color: white; padding: 7px 10px; text-align: left; }
            td { padding: 6px 10px; border-bottom: 1px solid #e0e0e0; }
            .status-approved { color: #10b981; font-weight: bold; }
            .status-pending { color: #f59e0b; font-weight: bold; }
            .status-rejected { color: #ef4444; font-weight: bold; }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; }
            .badge-approved { background: rgba(16,185,129,0.15); color: #10b981; }
            .badge-pending { background: rgba(245,158,11,0.15); color: #f59e0b; }
            .badge-rejected { background: rgba(239,68,68,0.15); color: #ef4444; }
            .summary { margin: 10px 0; display: flex; gap: 20px; flex-wrap: wrap; }
            .summary-item { background: #f8f9fc; padding: 5px 12px; border-radius: 5px; font-size: 11px; border: 1px solid #e8ecef; }
            .summary-item strong { color: #800020; }
            .footer { text-align: center; margin-top: 20px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 10px; color: #999; }
            .no-data { text-align: center; padding: 40px; color: #999; }
            @media print {
                body { padding: 0.5in; }
                th { background: #800020 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style>
    </head>
    <body>
        <div id="report-content" class="report-wrapper">
            <!-- Header -->
            <div class="header">
                <h1>GRADUATION CLEARANCE SYSTEM</h1>
                <p class="dept"><?php echo htmlspecialchars($department_name); ?></p>
                <p><strong>Student Report</strong></p>
                <?php if ($search): ?>
                    <p>Search: <?php echo htmlspecialchars($search); ?></p>
                <?php endif; ?>
                <?php if ($status != 'all'): ?>
                    <p>Status: <?php echo htmlspecialchars($status); ?></p>
                <?php endif; ?>
                <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
            </div>
            
            <?php if (empty($students)): ?>
                <div class="no-data">
                    <h3>No Records Found</h3>
                    <p>No students found for the selected criteria.</p>
                </div>
            <?php else: ?>
                <!-- Summary -->
                <div class="summary">
                    <div class="summary-item"><strong>Total Students:</strong> <?php echo count($students); ?></div>
                    <?php 
                    $approved = array_filter($students, function($s) { return $s['clearance_status'] == 'Approved'; });
                    $rejected = array_filter($students, function($s) { return $s['clearance_status'] == 'Rejected'; });
                    $pending = array_filter($students, function($s) { return $s['clearance_status'] == 'Pending'; });
                    ?>
                    <div class="summary-item"><strong>Approved:</strong> <?php echo count($approved); ?></div>
                    <div class="summary-item"><strong>Pending:</strong> <?php echo count($pending); ?></div>
                    <div class="summary-item"><strong>Rejected:</strong> <?php echo count($rejected); ?></div>
                </div>
                
                <!-- Table -->
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                            <td><?php echo $student['student_id']; ?></td>
                            <td><?php echo $student['email']; ?></td>
                            <td><?php echo $student['phone'] ?? '—'; ?></td>
                            <td><?php echo $student['completion_percentage']; ?>% (<?php echo $student['completed_requirements']; ?>/<?php echo $student['total_requirements']; ?>)</td>
                            <td><span class="badge badge-<?php echo strtolower($student['clearance_status']); ?>"><?php echo $student['clearance_status']; ?></span></td>
                            <td><?php echo $student['last_activity'] ? date('M d, Y', strtotime($student['last_activity'])) : '—'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="footer">
                <p>© <?php echo date('Y'); ?> Graduation Clearance System - Student Report</p>
                <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 15px; padding: 10px; background: #f8f9fc; border-radius: 8px;">
            <button onclick="downloadPDF()" style="background: #800020; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-size: 14px;">
                <i class="fas fa-download"></i> Download PDF
            </button>
            <button onclick="window.close()" style="background: #6c7293; color: white; border: none; padding: 10px 25px; border-radius: 8px; cursor: pointer; font-size: 14px; margin-left: 10px;">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        
        <script>
            function downloadPDF() {
                var element = document.getElementById('report-content');
                
                var opt = {
                    margin: 0.3,
                    filename: '<?php echo $filename; ?>.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { 
                        scale: 2, 
                        letterRendering: true,
                        useCORS: true,
                        logging: false
                    },
                    jsPDF: { 
                        unit: 'in', 
                        format: 'a4', 
                        orientation: 'portrait' 
                    },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                
                html2pdf().set(opt).from(element).save();
            }
            
            // Auto-download when page loads
            window.onload = function() {
                setTimeout(function() {
                    downloadPDF();
                }, 800);
            }
        </script>
    </body>
    </html>
    <?php
}
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
            font-size: 0.85rem;
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 50px;
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
        
        .btn-export {
            padding: 8px 16px;
            border-radius: 10px;
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
        
        .btn-export:hover {
            transform: translateY(-2px);
        }
        
        .btn-export.print {
            background: #6c7293;
            color: white;
        }
        .btn-export.print:hover { background: #5a5f7a; }
        
        .btn-export.pdf {
            background: #dc3545;
            color: white;
        }
        .btn-export.pdf:hover { background: #c82333; }
        
        .btn-export.excel {
            background: #28a745;
            color: white;
        }
        .btn-export.excel:hover { background: #1e7e34; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 22px;
            transition: all 0.3s;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px var(--primary-glow);
            border-color: transparent;
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
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--gray-800);
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.8rem;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        
        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--gray-700);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            padding: 8px 12px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
            outline: none;
        }
        
        .btn-filter {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
        }
        
        .btn-reset {
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-reset:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .card-header-custom {
            padding: 18px 25px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: white;
        }
        
        .card-header-custom h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .card-header-custom h5 i {
            color: var(--primary);
            margin-right: 8px;
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
        
        .progress-small {
            width: 100px;
            height: 6px;
            background: var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-small-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 10px;
            transition: width 0.5s ease;
        }
        
        .btn-view {
            background: var(--info);
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .btn-view:hover {
            background: #2c6bcf;
            color: white;
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
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 15px; }
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
                margin-bottom: 10px;
            }
            
            .filter-bar .row > div {
                margin-bottom: 10px;
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
            
            .card-header-custom {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-export {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .stat-icon {
                margin-bottom: 0;
            }
            
            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 5px 10px;
                font-size: 0.65rem;
                min-width: 28px;
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
            <a href="index.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="student-report-list.php" class="menu-item active">
                <i class="fas fa-user-graduate"></i>
                <span>Student Report</span>
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
            <div>
                <h2><i class="fas fa-user-graduate me-2"></i> Student Report</h2>
                <p>Comprehensive student clearance progress report</p>
            </div>
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Reports
            </a>
        </div>
        
        <!-- Statistics Cards - Updated to 3 cards -->
        <div class="stats-grid">
            <div class="stat-card animate-in" style="animation-delay: 0.1s;">
                <div class="stat-icon" style="background: rgba(128,0,32,0.1); color: var(--primary);">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="stat-number"><?php echo number_format($total_students); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="stat-card animate-in" style="animation-delay: 0.2s;">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-number"><?php echo number_format(count($approved_students)); ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="stat-card animate-in" style="animation-delay: 0.3s;">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-number"><?php echo number_format(count($pending_students)); ?></div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, ID or Email" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-filter me-2"></i> Apply Filters
                        </button>
                        <a href="student-report-list.php" class="btn-reset">
                            <i class="fas fa-times me-2"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Students Table -->
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-list"></i> Student Records</h5>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'students', 'format' => 'print', 'export_search' => $search, 'export_status' => $status_filter, 'export_department' => $department_filter])); ?>" target="_blank" class="btn-export print">
                        <i class="fas fa-print"></i> Print
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'students', 'format' => 'pdf', 'export_search' => $search, 'export_status' => $status_filter, 'export_department' => $department_filter])); ?>" target="_blank" class="btn-export pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'students', 'format' => 'excel', 'export_search' => $search, 'export_status' => $status_filter, 'export_department' => $department_filter])); ?>" class="btn-export excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table id="studentsTable" class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Progress</th>
                            <th>Status</th>
                            <th>Last Activity</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3" style="opacity: 0.3;"></i>
                                    <p>No students found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; foreach ($students as $student): 
                                $completion = $student['completion_percentage'] ?? 0;
                                $status_class = 'status-' . strtolower($student['clearance_status']);
                            ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                        <br><small class="text-muted">Registered: <?php echo date('M d, Y', strtotime($student['registered_date'])); ?></small>
                                    </td>
                                    <td><?php echo $student['student_id']; ?></td>
                                    <td><?php echo $student['email']; ?></td>
                                    <td><?php echo $student['phone'] ?: '—'; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress-small">
                                                <div class="progress-small-bar" style="width: <?php echo $completion; ?>%"></div>
                                            </div>
                                            <span class="fw-bold" style="font-size: 0.75rem;">
                                                <?php echo $completion; ?>%
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo $student['completed_requirements']; ?>/<?php echo $student['total_requirements']; ?> completed
                                        </small>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php if ($student['clearance_status'] == 'Approved'): ?>
                                                <i class="fas fa-check-circle"></i>
                                            <?php elseif ($student['clearance_status'] == 'Pending'): ?>
                                                <i class="fas fa-clock"></i>
                                            <?php elseif ($student['clearance_status'] == 'Rejected'): ?>
                                                <i class="fas fa-times-circle"></i>
                                            <?php endif; ?>
                                            <?php echo $student['clearance_status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($student['last_activity']): ?>
                                            <span class="small text-muted">
                                                <?php echo date('M d, Y', strtotime($student['last_activity'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../users/view.php?id=<?php echo $student['id']; ?>" class="btn-view" title="View Student">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        // Animate progress bars
        setTimeout(function() {
            document.querySelectorAll('.progress-small-bar').forEach(function(bar) {
                var width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(function() {
                    bar.style.width = width;
                }, 100);
            });
        }, 100);
        
        // DataTable with Professional Pagination
        $(document).ready(function() {
            $('#studentsTable').DataTable({
                pageLength: 10,
                order: [[0, 'asc']],
                language: {
                    search: "<i class='fas fa-search'></i> Search:",
                    searchPlaceholder: "Search records...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ students",
                    infoEmpty: "No students available",
                    infoFiltered: "(filtered from _MAX_ total students)",
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
    </script>
</body>
</html>