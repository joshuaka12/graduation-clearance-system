<?php
session_start();
require_once '../config/config.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

// Check if department is selected
if (!isset($_SESSION['selected_department_id'])) {
    redirect('select-department.php');
}

$department_id = $_SESSION['selected_department_id'];
$department_name = $_SESSION['selected_department_name'];

// Get parameters
$report_type = isset($_GET['type']) ? $_GET['type'] : 'all';
$format = isset($_GET['format']) ? $_GET['format'] : 'print';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query based on report type
$sql = "
    SELECT DISTINCT 
        u.id,
        u.full_name,
        u.student_id,
        u.email,
        u.phone,
        MAX(sc.created_at) as applied_date,
        MAX(CASE WHEN sc.status = 'approved' THEN sc.reviewed_at END) as approval_date,
        CASE 
            WHEN SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) = COUNT(ci.id) THEN 'approved'
            WHEN SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
            ELSE 'pending'
        END as status,
        GROUP_CONCAT(CASE WHEN sc.status = 'rejected' THEN sc.remarks END SEPARATOR ' | ') as rejection_reason,
        MAX(sc.remarks) as remarks,
        COUNT(ci.id) as total_items,
        SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as completed_items
    FROM clearance_items ci
    CROSS JOIN users u
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = u.id
    WHERE ci.department_id = ? AND u.role = 'student'
    GROUP BY u.id
    HAVING applied_date IS NOT NULL
";

$params = [$department_id];

// Apply filters based on report type
if ($report_type == 'pending') {
    $sql .= " AND status = 'pending'";
} elseif ($report_type == 'rejected') {
    $sql .= " AND status = 'rejected'";
}

// Apply date filters
if ($date_from && $date_to) {
    $sql .= " AND applied_date BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to . ' 23:59:59';
}

// Apply status filter for 'all' report
if ($report_type == 'all' && $status_filter != 'all') {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get department name
$stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
$stmt->execute([$department_id]);
$dept = $stmt->fetch();
$department_name = $dept['department_name'] ?? 'Unknown Department';

// Handle different formats
if ($format == 'excel') {
    exportExcel($students, $report_type, $department_name, $date_from, $date_to);
} elseif ($format == 'pdf') {
    exportPDF($students, $report_type, $department_name, $date_from, $date_to);
} else {
    // Default: Print view
    printReport($students, $report_type, $department_name, $date_from, $date_to);
}

// ============================================
// PRINT FUNCTION
// ============================================
function printReport($students, $report_type, $department_name, $date_from, $date_to) {
    $title = getReportTitle($report_type);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $title; ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #800020; margin-bottom: 20px; }
            .header h1 { color: #800020; margin: 0; font-size: 24px; }
            .header p { color: #666; margin: 5px 0; }
            .header .dept { color: #800020; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #800020; color: white; padding: 10px; text-align: left; font-size: 12px; }
            td { padding: 8px 10px; border-bottom: 1px solid #ddd; font-size: 12px; }
            tr:hover { background: #f5f5f5; }
            .status-approved { color: #10b981; font-weight: bold; }
            .status-pending { color: #f59e0b; font-weight: bold; }
            .status-rejected { color: #ef4444; font-weight: bold; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 11px; color: #999; }
            .no-data { text-align: center; padding: 50px; color: #999; }
            .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
            .badge-approved { background: rgba(16,185,129,0.1); color: #10b981; }
            .badge-pending { background: rgba(245,158,11,0.1); color: #f59e0b; }
            .badge-rejected { background: rgba(239,68,68,0.1); color: #ef4444; }
            .summary { margin-top: 15px; display: flex; gap: 30px; flex-wrap: wrap; }
            .summary-item { background: #f8f9fc; padding: 8px 15px; border-radius: 8px; font-size: 12px; }
            .summary-item strong { color: #800020; }
            @media print {
                .no-print { display: none; }
                body { margin: 10px; }
                .header { border-bottom: 2px solid #800020; }
                th { background: #800020 !important; color: white !important; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Graduation Clearance System</h1>
            <p><span class="dept"><?php echo htmlspecialchars($department_name); ?></span></p>
            <p><?php echo $title; ?></p>
            <p>Date Range: <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
            <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
        </div>
        
        <?php if (empty($students)): ?>
            <div class="no-data">
                <h3>No Records Found</h3>
                <p>No data available for the selected criteria.</p>
            </div>
        <?php else: ?>
            <div class="summary">
                <div class="summary-item"><strong>Total Records:</strong> <?php echo count($students); ?></div>
                <?php 
                $approved = array_filter($students, function($s) { return $s['status'] == 'approved'; });
                $pending = array_filter($students, function($s) { return $s['status'] == 'pending'; });
                $rejected = array_filter($students, function($s) { return $s['status'] == 'rejected'; });
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
                        <th>Applied Date</th>
                        <th>Status</th>
                        <th>Approval Date</th>
                        <?php if ($report_type == 'rejected'): ?>
                            <th>Rejection Reason</th>
                        <?php endif; ?>
                        <?php if ($report_type == 'pending'): ?>
                            <th>Days Pending</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; foreach ($students as $student): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                        <td><?php echo $student['student_id']; ?></td>
                        <td><?php echo $student['email']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($student['applied_date'])); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $student['status']; ?>">
                                <?php echo ucfirst($student['status']); ?>
                            </span>
                        </td>
                        <td><?php echo $student['approval_date'] ? date('M d, Y', strtotime($student['approval_date'])) : '—'; ?></td>
                        <?php if ($report_type == 'rejected'): ?>
                            <td><?php echo htmlspecialchars($student['rejection_reason'] ?? 'N/A'); ?></td>
                        <?php endif; ?>
                        <?php if ($report_type == 'pending'): ?>
                            <td>
                                <?php 
                                $days = round((time() - strtotime($student['applied_date'])) / (60 * 60 * 24));
                                echo $days . ' days';
                                ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="footer">
            <p>© <?php echo date('Y'); ?> Graduation Clearance System - <?php echo htmlspecialchars($department_name); ?></p>
            <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
        </div>
        
        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="window.print()" style="background: #800020; color: white; border: none; padding: 10px 30px; border-radius: 8px; cursor: pointer; font-size: 14px;">
                <i class="fas fa-print"></i> Print
            </button>
            <button onclick="window.close()" style="background: #6c7293; color: white; border: none; padding: 10px 30px; border-radius: 8px; cursor: pointer; font-size: 14px; margin-left: 10px;">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        
        <script>
            <?php if ($format == 'print'): ?>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
            <?php endif; ?>
        </script>
    </body>
    </html>
    <?php
}

// ============================================
// EXCEL EXPORT FUNCTION
// ============================================
function exportExcel($students, $report_type, $department_name, $date_from, $date_to) {
    $title = getReportTitle($report_type);
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="UTF-8">';
    echo '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>' . $title . '</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
    echo '<style>
        table { border-collapse: collapse; width: 100%; }
        th { background: #800020; color: white; padding: 8px; text-align: left; border: 1px solid #000; }
        td { padding: 8px; border: 1px solid #999; }
        .status-approved { color: #10b981; font-weight: bold; }
        .status-pending { color: #f59e0b; font-weight: bold; }
        .status-rejected { color: #ef4444; font-weight: bold; }
    </style>';
    echo '</head><body>';
    
    echo '<table>';
    echo '<tr><td colspan="10" style="text-align: center; border: none; font-size: 20px; font-weight: bold; color: #800020;">Graduation Clearance System</td></tr>';
    echo '<tr><td colspan="10" style="text-align: center; border: none; font-size: 16px; font-weight: bold;">' . htmlspecialchars($department_name) . '</td></tr>';
    echo '<tr><td colspan="10" style="text-align: center; border: none; font-size: 14px;">' . $title . '</td></tr>';
    echo '<tr><td colspan="10" style="text-align: center; border: none; font-size: 12px;">Date Range: ' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)) . '</td></tr>';
    echo '<tr><td colspan="10" style="text-align: center; border: none; font-size: 12px; padding-bottom: 15px;">Generated: ' . date('F d, Y h:i A') . '</td></tr>';
    echo '</table>';
    
    if (empty($students)) {
        echo '<p style="text-align: center; padding: 50px; font-size: 16px; color: #999;">No records found</p>';
    } else {
        $approved = array_filter($students, function($s) { return $s['status'] == 'approved'; });
        $pending = array_filter($students, function($s) { return $s['status'] == 'pending'; });
        $rejected = array_filter($students, function($s) { return $s['status'] == 'rejected'; });
        
        echo '<table>';
        echo '<tr><td colspan="10" style="border: none;"><strong>Summary:</strong> Total: ' . count($students) . ' | Approved: ' . count($approved) . ' | Pending: ' . count($pending) . ' | Rejected: ' . count($rejected) . '</td></tr>';
        echo '</table><br>';
        
        echo '<table>';
        echo '<tr>';
        echo '<th>#</th><th>Student Name</th><th>Student ID</th><th>Email</th><th>Phone</th><th>Applied Date</th><th>Status</th><th>Approval Date</th>';
        if ($report_type == 'rejected') echo '<th>Rejection Reason</th>';
        if ($report_type == 'pending') echo '<th>Days Pending</th>';
        if ($report_type == 'all') echo '<th>Total Items</th><th>Completed</th><th>Remarks</th>';
        echo '</tr>';
        
        $counter = 1;
        foreach ($students as $student) {
            echo '<tr>';
            echo '<td>' . $counter++ . '</td>';
            echo '<td>' . htmlspecialchars($student['full_name']) . '</td>';
            echo '<td>' . $student['student_id'] . '</td>';
            echo '<td>' . $student['email'] . '</td>';
            echo '<td>' . ($student['phone'] ?? 'N/A') . '</td>';
            echo '<td>' . date('M d, Y', strtotime($student['applied_date'])) . '</td>';
            echo '<td class="status-' . $student['status'] . '">' . ucfirst($student['status']) . '</td>';
            echo '<td>' . ($student['approval_date'] ? date('M d, Y', strtotime($student['approval_date'])) : '—') . '</td>';
            if ($report_type == 'rejected') echo '<td>' . htmlspecialchars($student['rejection_reason'] ?? 'N/A') . '</td>';
            if ($report_type == 'pending') {
                $days = round((time() - strtotime($student['applied_date'])) / (60 * 60 * 24));
                echo '<td>' . $days . ' days</td>';
            }
            if ($report_type == 'all') {
                echo '<td>' . ($student['total_items'] ?? 'N/A') . '</td>';
                echo '<td>' . ($student['completed_items'] ?? 'N/A') . '</td>';
                echo '<td>' . htmlspecialchars($student['remarks'] ?? '—') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
        
        echo '<br><table>';
        echo '<tr><td colspan="10" style="border: none; text-align: center; font-style: italic; font-size: 11px; color: #999;">';
        echo '© ' . date('Y') . ' Graduation Clearance System - ' . htmlspecialchars($department_name);
        echo '<br>Generated on ' . date('F d, Y h:i A');
        echo '</td></tr>';
        echo '</table>';
    }
    
    echo '</body></html>';
    exit();
}

// ============================================
// PDF EXPORT FUNCTION - AUTOMATIC DOWNLOAD
// ============================================
function exportPDF($students, $report_type, $department_name, $date_from, $date_to) {
    $title = getReportTitle($report_type);
    $filename = strtolower(str_replace(' ', '_', $title)) . '_' . date('Y-m-d');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo $title; ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: white; }
            .certificate-wrapper { max-width: 100%; margin: 0 auto; }
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
            .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 600; }
            .badge-approved { background: rgba(16,185,129,0.15); color: #10b981; }
            .badge-pending { background: rgba(245,158,11,0.15); color: #f59e0b; }
            .badge-rejected { background: rgba(239,68,68,0.15); color: #ef4444; }
            .summary { display: flex; gap: 20px; flex-wrap: wrap; margin: 10px 0; }
            .summary-item { background: #f8f9fc; padding: 5px 12px; border-radius: 6px; font-size: 11px; border: 1px solid #e8ecef; }
            .summary-item strong { color: #800020; }
            .footer { text-align: center; margin-top: 20px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 10px; color: #999; }
            .no-data { text-align: center; padding: 40px; color: #999; }
            .report-title { font-size: 16px; font-weight: bold; text-align: center; margin: 10px 0; color: #333; }
            .print-btn { 
                background: #800020; color: white; border: none; padding: 10px 25px; border-radius: 8px; 
                cursor: pointer; font-size: 14px; margin: 10px;
            }
            .print-btn:hover { background: #5a0016; }
            .download-btn {
                background: #10b981; color: white; border: none; padding: 10px 25px; border-radius: 8px;
                cursor: pointer; font-size: 14px; margin: 10px;
            }
            .download-btn:hover { background: #059669; }
            .controls { text-align: center; padding: 15px; background: #f8f9fc; border-radius: 8px; margin-bottom: 15px; }
            @media print {
                .no-print { display: none !important; }
                body { padding: 0.5in; }
                .header { border-bottom: 3px solid #800020; }
                th { background: #800020 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .badge { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .summary-item { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            }
        </style>
    </head>
    <body>
        <div id="report-content" class="certificate-wrapper">
            <!-- Header -->
            <div class="header">
                <h1>GRADUATION CLEARANCE SYSTEM</h1>
                <p class="dept"><?php echo htmlspecialchars($department_name); ?></p>
                <p><?php echo $title; ?></p>
                <p>Date Range: <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></p>
                <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
            </div>
            
            <?php if (empty($students)): ?>
                <div class="no-data">
                    <h3>No Records Found</h3>
                    <p>No data available for the selected criteria.</p>
                </div>
            <?php else: ?>
                <!-- Summary -->
                <div class="summary">
                    <div class="summary-item"><strong>Total Records:</strong> <?php echo count($students); ?></div>
                    <?php 
                    $approved = array_filter($students, function($s) { return $s['status'] == 'approved'; });
                    $pending = array_filter($students, function($s) { return $s['status'] == 'pending'; });
                    $rejected = array_filter($students, function($s) { return $s['status'] == 'rejected'; });
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
                            <th>Applied Date</th>
                            <th>Status</th>
                            <th>Approval Date</th>
                            <?php if ($report_type == 'rejected'): ?>
                                <th>Rejection Reason</th>
                            <?php endif; ?>
                            <?php if ($report_type == 'pending'): ?>
                                <th>Days Pending</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($student['full_name']); ?></strong></td>
                            <td><?php echo $student['student_id']; ?></td>
                            <td><?php echo $student['email']; ?></td>
                            <td><?php echo date('M d, Y', strtotime($student['applied_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $student['status']; ?>">
                                    <?php echo ucfirst($student['status']); ?>
                                </span>
                            </td>
                            <td><?php echo $student['approval_date'] ? date('M d, Y', strtotime($student['approval_date'])) : '—'; ?></td>
                            <?php if ($report_type == 'rejected'): ?>
                                <td><?php echo htmlspecialchars($student['rejection_reason'] ?? 'N/A'); ?></td>
                            <?php endif; ?>
                            <?php if ($report_type == 'pending'): ?>
                                <td>
                                    <?php 
                                    $days = round((time() - strtotime($student['applied_date'])) / (60 * 60 * 24));
                                    echo $days . ' days';
                                    ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="footer">
                <p>© <?php echo date('Y'); ?> Graduation Clearance System - <?php echo htmlspecialchars($department_name); ?></p>
                <p>Generated on <?php echo date('F d, Y h:i A'); ?></p>
            </div>
        </div>
        
        <!-- Controls -->
        <div class="controls no-print">
            <button class="download-btn" onclick="downloadPDF()">
                <i class="fas fa-download"></i> Download PDF
            </button>
            <button class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
            <button class="print-btn" onclick="window.close()" style="background: #6c7293;">
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
                }, 1000);
            }
        </script>
    </body>
    </html>
    <?php
    exit();
}

// ============================================
// HELPER FUNCTION
// ============================================
function getReportTitle($report_type) {
    switch ($report_type) {
        case 'all': return 'Student Clearance Report';
        case 'pending': return 'Pending Clearances Report';
        case 'rejected': return 'Rejected Students Report';
        default: return 'Clearance Report';
    }
}
?>