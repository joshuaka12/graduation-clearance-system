<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';
require_once '../includes/send_email.php';
require_once '../includes/email_templates.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] == 1) {
    redirect('../auth/force-change-password.php');
}

$user_id = $_SESSION['user_id'];

// Get the department assigned by admin from the users table
$stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data || !$user_data['department_id']) {
    $_SESSION['error'] = "No department has been assigned to you. Please contact the administrator.";
    redirect('../auth/login.php');
}

$department_id = $user_data['department_id'];

// Get department name
$stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ? AND is_active = 1");
$stmt->execute([$department_id]);
$dept = $stmt->fetch();

if (!$dept) {
    $_SESSION['error'] = "Your assigned department is not active. Please contact the administrator.";
    redirect('../auth/login.php');
}

$department_name = $dept['department_name'];

// Set session variables for department
$_SESSION['selected_department_id'] = $department_id;
$_SESSION['selected_department_name'] = $department_name;

// Get system settings for branding
$university_name = getUniversityName($pdo);
$academic_year = getAcademicYear($pdo);
$graduation_year = getGraduationYear($pdo);

// ============================================================
// PROCESS APPROVAL / REJECTION / RE-APPROVAL FROM AJAX
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => '', 'data' => []];
    
    try {
        $action = $_POST['ajax_action'];
        $student_id = (int)$_POST['student_id'];
        $remarks = sanitizeInput($_POST['remarks'] ?? '');
        
        // Verify student exists
        $stmt = $pdo->prepare("SELECT id, full_name, email, student_id FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            throw new Exception("Student not found.");
        }
        
        if ($action == 'approve' || $action == 'reject') {
            $pdo->beginTransaction();
            
            // Get all clearance items for this department
            $stmt = $pdo->prepare("
                SELECT ci.id as item_id, sc.id as clearance_id, sc.status as current_status
                FROM clearance_items ci
                LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
                WHERE ci.department_id = ?
            ");
            $stmt->execute([$student_id, $department_id]);
            $items = $stmt->fetchAll();
            
            $updated_count = 0;
            $rejected_count = 0;
            $approved_count = 0;
            $pending_count = 0;
            
            foreach ($items as $item) {
                $current_status = $item['current_status'] ?? null;
                
                if ($action == 'approve') {
                    // Allow approval even if previously rejected (re-approval)
                    if ($current_status == 'rejected' || $current_status == 'pending' || $current_status === null) {
                        if ($item['clearance_id']) {
                            // Update existing record
                            $stmt = $pdo->prepare("
                                UPDATE student_clearance 
                                SET status = 'approved', 
                                    reviewed_by = ?, 
                                    reviewed_at = NOW(), 
                                    remarks = ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([$user_id, $remarks ?: 'Approved by department head', $item['clearance_id']]);
                        } else {
                            // Insert new record
                            $stmt = $pdo->prepare("
                                INSERT INTO student_clearance 
                                (student_id, clearance_item_id, department_id, status, reviewed_by, reviewed_at, remarks, created_at) 
                                VALUES (?, ?, ?, 'approved', ?, NOW(), ?, NOW())
                            ");
                            $stmt->execute([$student_id, $item['item_id'], $department_id, $user_id, $remarks ?: 'Approved by department head']);
                        }
                        $updated_count++;
                        $approved_count++;
                    } elseif ($current_status == 'approved') {
                        $approved_count++;
                    }
                } elseif ($action == 'reject') {
                    if (empty($remarks)) {
                        throw new Exception("Please provide a reason for rejection.");
                    }
                    
                    if ($current_status == 'pending' || $current_status === null) {
                        if ($item['clearance_id']) {
                            // Update existing record
                            $stmt = $pdo->prepare("
                                UPDATE student_clearance 
                                SET status = 'rejected', 
                                    reviewed_by = ?, 
                                    reviewed_at = NOW(), 
                                    remarks = ? 
                                WHERE id = ?
                            ");
                            $stmt->execute([$user_id, $remarks, $item['clearance_id']]);
                        } else {
                            // Insert new record
                            $stmt = $pdo->prepare("
                                INSERT INTO student_clearance 
                                (student_id, clearance_item_id, department_id, status, reviewed_by, reviewed_at, remarks, created_at) 
                                VALUES (?, ?, ?, 'rejected', ?, NOW(), ?, NOW())
                            ");
                            $stmt->execute([$student_id, $item['item_id'], $department_id, $user_id, $remarks]);
                        }
                        $updated_count++;
                        $rejected_count++;
                    } elseif ($current_status == 'rejected') {
                        $rejected_count++;
                    }
                }
            }
            
            if ($updated_count > 0 || ($action == 'approve' && $approved_count > 0) || ($action == 'reject' && $rejected_count > 0)) {
                // Send email notification
                if ($action == 'approve') {
                    $is_reapproval = false;
                    // Check if this was a re-approval (was previously rejected)
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as count FROM student_clearance sc
                        JOIN clearance_items ci ON sc.clearance_item_id = ci.id
                        WHERE sc.student_id = ? AND ci.department_id = ? AND sc.status = 'approved' 
                        AND sc.remarks LIKE '%Re-approved%'
                    ");
                    $stmt->execute([$student_id, $department_id]);
                    $reapproval_check = $stmt->fetch();
                    $is_reapproval = $reapproval_check['count'] > 0;
                    
                    $email_body = "Dear " . $student['full_name'] . ",\n\n";
                    if ($is_reapproval) {
                        $email_body .= "Your clearance has been **RE-APPROVED** by {$department_name} after review.\n\n";
                        $email_body .= "The issues that were previously identified have been resolved.\n\n";
                    } else {
                        $email_body .= "Your clearance has been approved by {$department_name}.\n\n";
                    }
                    $email_body .= "Remarks: " . ($remarks ?: "Approved by department head") . "\n\n";
                    $email_body .= "Thank you for completing the clearance process.\n\n";
                    $email_body .= "Regards,\n" . $department_name . " Department";
                    
                    sendEmail($student['email'], 'Clearance Approved - ' . $department_name, $email_body);
                    
                    // Create notification
                    $notification_title = $is_reapproval ? 'Clearance Re-Approved' : 'Clearance Approved';
                    $notification_msg = $is_reapproval 
                        ? "Your clearance has been re-approved by {$department_name} after review."
                        : "Your clearance has been approved by {$department_name}.";
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
                        VALUES (?, ?, ?, 'success', 'student/clearance-status.php', NOW())
                    ");
                    $stmt->execute([$student_id, $notification_title, $notification_msg]);
                    
                    logActivity($pdo, $user_id, $is_reapproval ? 'Re-Approve Clearance' : 'Approve Clearance', 
                        ($is_reapproval ? "Re-approved (was Rejected) student ID: " : "Approved student ID: ") . "$student_id in department ID: $department_id");
                    
                } else {
                    $email_body = "Dear " . $student['full_name'] . ",\n\n";
                    $email_body .= "Your clearance has been rejected by {$department_name}.\n\n";
                    $email_body .= "Reason: " . $remarks . "\n\n";
                    $email_body .= "Please address the issues mentioned and resubmit your clearance.\n\n";
                    $email_body .= "Regards,\n" . $department_name . " Department";
                    
                    sendEmail($student['email'], 'Clearance Rejected - ' . $department_name, $email_body);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
                        VALUES (?, 'Clearance Rejected', ?, 'danger', 'student/clearance-status.php', NOW())
                    ");
                    $stmt->execute([$student_id, "Your clearance has been rejected by {$department_name}. Reason: " . substr($remarks, 0, 100)]);
                    
                    logActivity($pdo, $user_id, 'Reject Clearance', 
                        "Rejected student ID: $student_id in department ID: $department_id. Reason: $remarks");
                }
                
                // ============================================================
                // CHECK IF STUDENT IS FULLY CLEARED FOR THIS DEPARTMENT
                // ============================================================
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT ci.id) as total_items,
                        SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count
                    FROM clearance_items ci
                    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
                    WHERE ci.department_id = ?
                ");
                $stmt->execute([$student_id, $department_id]);
                $progress = $stmt->fetch();
                
                $department_fully_cleared = ($progress['total_items'] > 0 && $progress['approved_count'] == $progress['total_items']);
                
                // ============================================================
                // CHECK IF STUDENT IS FULLY CLEARED FOR ALL DEPARTMENTS
                // ============================================================
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT d.id) as total_depts,
                        COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN d.id END) as cleared_depts
                    FROM departments d
                    JOIN clearance_items ci ON d.id = ci.department_id
                    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
                    WHERE d.is_active = 1
                ");
                $stmt->execute([$student_id]);
                $overall = $stmt->fetch();
                
                $fully_cleared_all = ($overall['total_depts'] > 0 && $overall['cleared_depts'] == $overall['total_depts']);
                
                // If fully cleared, generate certificate
                if ($fully_cleared_all) {
                    $certificate_number = 'GCS-' . date('Y') . '-' . str_pad($student_id, 5, '0', STR_PAD_LEFT);
                    $verification_code = strtoupper(bin2hex(random_bytes(8)));
                    
                    $stmt = $pdo->prepare("SELECT id FROM clearance_certificates WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                    if (!$stmt->fetch()) {
                        $stmt = $pdo->prepare("
                            INSERT INTO clearance_certificates 
                            (student_id, certificate_number, verification_code, issued_date, created_at) 
                            VALUES (?, ?, ?, NOW(), NOW())
                        ");
                        $stmt->execute([$student_id, $certificate_number, $verification_code]);
                        
                        $final_email = "Dear " . $student['full_name'] . ",\n\n";
                        $final_email .= "🎉 Congratulations! You are fully cleared for graduation!\n\n";
                        $final_email .= "Certificate Number: " . $certificate_number . "\n";
                        $final_email .= "Verification Code: " . $verification_code . "\n\n";
                        $final_email .= "Please download your certificate from the student portal.\n\n";
                        $final_email .= "Regards,\nClearance System";
                        
                        sendEmail($student['email'], '🎉 Congratulations! You Are Fully Cleared!', $final_email);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, title, message, type, link, created_at) 
                            VALUES (?, 'Graduation Clearance Complete', 
                            'Congratulations! You have successfully completed all graduation clearance requirements.', 
                            'success', 'student/download-certificate.php', NOW())
                        ");
                        $stmt->execute([$student_id]);
                    }
                }
                
                $pdo->commit();
                
                // ============================================================
                // GET UPDATED STATS AND STUDENT DATA
                // ============================================================
                $stats = getDepartmentStats($pdo, $department_id);
                $total_students = getTotalStudents($pdo, $department_id);
                
                // Get updated student progress
                $student_progress = getStudentProgress($pdo, $student_id, $department_id);
                
                $response['success'] = true;
                $response['message'] = $action == 'approve' ? "Student approved successfully!" : "Student rejected successfully!";
                $response['data'] = [
                    'stats' => $stats,
                    'total_students' => $total_students,
                    'student_id' => $student_id,
                    'status' => $action == 'approve' ? 'approved' : 'rejected',
                    'action' => $action,
                    'progress' => $student_progress,
                    'fully_cleared' => $fully_cleared_all
                ];
                
            } else {
                $pdo->rollBack();
                throw new Exception($action == 'approve' ? "No items to approve." : "No items to reject.");
            }
        } else {
            throw new Exception("Invalid action.");
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================
function getDepartmentStats($pdo, $department_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT sc.student_id) as count
        FROM student_clearance sc
        JOIN clearance_items ci ON sc.clearance_item_id = ci.id
        WHERE ci.department_id = ? AND sc.status = 'approved'
    ");
    $stmt->execute([$department_id]);
    $approved = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT sc.student_id) as count
        FROM student_clearance sc
        JOIN clearance_items ci ON sc.clearance_item_id = ci.id
        WHERE ci.department_id = ? AND sc.status = 'rejected'
    ");
    $stmt->execute([$department_id]);
    $rejected = (int)$stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM clearance_items WHERE department_id = ?");
    $stmt->execute([$department_id]);
    $items = (int)$stmt->fetch()['count'];
    
    return [
        'approved' => $approved,
        'rejected' => $rejected,
        'items' => $items
    ];
}

function getTotalStudents($pdo, $department_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT u.id) as count
        FROM users u
        WHERE u.role = 'student' AND EXISTS (
            SELECT 1 FROM student_clearance sc 
            JOIN clearance_items ci ON sc.clearance_item_id = ci.id 
            WHERE ci.department_id = ? AND sc.student_id = u.id
        )
    ");
    $stmt->execute([$department_id]);
    return (int)$stmt->fetch()['count'];
}

function getStudentProgress($pdo, $student_id, $department_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT ci.id) as total_items,
            SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count
        FROM clearance_items ci
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
        WHERE ci.department_id = ?
    ");
    $stmt->execute([$student_id, $department_id]);
    $result = $stmt->fetch();
    
    return [
        'total' => (int)$result['total_items'],
        'completed' => (int)$result['approved_count'],
        'percent' => $result['total_items'] > 0 ? round(($result['approved_count'] / $result['total_items']) * 100, 1) : 0
    ];
}

// Display success/error messages
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Get department info
$stmt = $pdo->prepare("SELECT * FROM departments WHERE id = ? AND is_active = 1");
$stmt->execute([$department_id]);
$department = $stmt->fetch();

// Get statistics
$stats = getDepartmentStats($pdo, $department_id);
$approved = $stats['approved'];
$rejected = $stats['rejected'];
$items = $stats['items'];

// Get chat unread count
$unread_messages = getUnreadMessageCount($pdo, $user_id);

// Get total students count
$total_students = getTotalStudents($pdo, $department_id);

// Get students with profile pictures - SORTED BY CREATED AT DESC (newest first)
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        u.id, 
        u.full_name, 
        u.student_id, 
        u.email, 
        u.phone,
        u.profile_pic,
        u.created_at as registration_date,
        (SELECT COUNT(*) FROM clearance_items ci WHERE ci.department_id = ?) as total_items,
        (SELECT COUNT(*) FROM student_clearance sc 
         JOIN clearance_items ci ON sc.clearance_item_id = ci.id 
         WHERE ci.department_id = ? AND sc.student_id = u.id AND sc.status = 'approved') as completed_items,
        (SELECT status FROM student_clearance sc 
         JOIN clearance_items ci ON sc.clearance_item_id = ci.id 
         WHERE ci.department_id = ? AND sc.student_id = u.id ORDER BY sc.created_at DESC LIMIT 1) as last_status,
        (SELECT MAX(sc.created_at) FROM student_clearance sc 
         JOIN clearance_items ci ON sc.clearance_item_id = ci.id 
         WHERE ci.department_id = ? AND sc.student_id = u.id) as applied_date
    FROM users u
    WHERE u.role = 'student'
    AND EXISTS (
        SELECT 1 FROM student_clearance sc 
        JOIN clearance_items ci ON sc.clearance_item_id = ci.id 
        WHERE ci.department_id = ? AND sc.student_id = u.id
    )
    ORDER BY u.created_at DESC, u.id DESC
");
$stmt->execute([$department_id, $department_id, $department_id, $department_id, $department_id]);
$students = $stmt->fetchAll();

$page_title = 'Department Dashboard - ' . htmlspecialchars($department_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?> - <?php echo htmlspecialchars($university_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           ALL STYLES KEPT EXACTLY THE SAME
           ============================================================ */
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
            background: transparent;
            color: var(--primary);
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.5rem;
        }
        
        .mobile-toggle:hover {
            background: rgba(128, 0, 32, 0.1);
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #5a0016 0%, #3a000e 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.2);
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
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        
        .sidebar-header h4 { font-size: 1.2rem; font-weight: 600; margin: 10px 0 5px; color: white; }
        .sidebar-header p { font-size: 0.75rem; opacity: 0.7; margin: 0; color: white; }
        
        .sidebar-menu { padding: 0 15px 20px; }
        
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
        
        .menu-item:hover { background: rgba(255,255,255,0.15); color: white; transform: translateX(5px); }
        .menu-item.active { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; box-shadow: 0 5px 15px rgba(128,0,32,0.3); }
        .menu-item i { width: 22px; font-size: 1rem; }
        .menu-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 20px 0; }
        
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
        .sidebar-overlay.active { display: block; }
        
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
            transition: all 0.3s;
        }
        
        .system-info-banner {
            background: white;
            border-radius: 16px;
            padding: 15px 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        
        .system-info-banner .info-left { display: flex; align-items: center; }
        .system-info-banner .info-text { display: flex; flex-direction: column; }
        .system-info-banner .info-text .uni-name { font-size: 1rem; font-weight: 700; color: var(--gray-800); margin: 0; line-height: 1.2; }
        .system-info-banner .info-text .details { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 2px; }
        .system-info-banner .info-text .details .detail-item { display: flex; align-items: center; gap: 5px; font-size: 0.75rem; color: var(--gray-600); }
        .system-info-banner .info-text .details .detail-item i { color: var(--primary); font-size: 0.75rem; }
        .system-info-banner .info-text .details .detail-item strong { color: var(--gray-800); font-weight: 600; }
        .system-info-banner .user-badge { display: flex; align-items: center; gap: 8px; background: var(--gray-100); padding: 5px 14px 5px 10px; border-radius: 30px; }
        .system-info-banner .user-badge i { color: var(--primary); font-size: 0.85rem; }
        .system-info-banner .user-badge span { font-size: 0.75rem; color: var(--gray-700); font-weight: 500; }
        
        /* ============================================================
           DEPARTMENT INFO CARD - REMOVED
           ============================================================ */
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.3s;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(128,0,32,0.1); }
        .stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 12px; }
        .stat-number { font-size: 1.8rem; font-weight: 700; color: var(--gray-800); line-height: 1.2; }
        .stat-label { color: var(--gray-600); font-size: 0.75rem; margin-top: 5px; }
        
        .card-modern {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
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
        }
        .card-header-custom h5 { font-size: 1rem; font-weight: 600; margin: 0; }
        
        .student-table th {
            background: var(--gray-100);
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            user-select: none;
            transition: all 0.2s;
        }
        .student-table th:hover { background: var(--gray-200); }
        .student-table th i { margin-left: 4px; font-size: 0.7rem; }
        
        .student-table td {
            padding: 12px 15px;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-approved { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        .status-fully-cleared { background: rgba(16,185,129,0.15); color: #059669; }
        
        .student-avatar-sm {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }
        .student-avatar-sm img { width: 100%; height: 100%; object-fit: cover; }
        
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
        
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
        
        .btn-approve {
            background: var(--success);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-approve:hover { transform: translateY(-2px); box-shadow: 0 2px 8px rgba(16,185,129,0.3); }
        
        .btn-reject {
            background: var(--danger);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 600;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-reject:hover { transform: translateY(-2px); box-shadow: 0 2px 8px rgba(239,68,68,0.3); }
        
        .btn-view {
            background: var(--info);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-view:hover { transform: translateY(-2px); background: #2c6bcf; color: white; }
        
        .btn-chat {
            background: var(--primary);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-chat:hover { transform: translateY(-2px); background: var(--primary-dark); color: white; }
        
        .btn-approve:disabled, .btn-reject:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .filter-select {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.8rem;
            background: white;
        }
        .filter-select:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px var(--primary-soft); }
        
        .confirmation-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        .confirmation-overlay.active { display: flex; }
        
        .confirmation-dialog {
            background: white;
            border-radius: 20px;
            padding: 35px 40px;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: dialogSlideIn 0.3s ease;
        }
        
        @keyframes dialogSlideIn {
            from { transform: scale(0.9) translateY(20px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        
        .confirmation-dialog .dialog-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 2rem;
        }
        .confirmation-dialog .dialog-icon.warning { background: rgba(239,68,68,0.1); color: var(--danger); }
        .confirmation-dialog .dialog-icon.success { background: rgba(16,185,129,0.1); color: var(--success); }
        
        .confirmation-dialog h4 { text-align: center; font-weight: 700; color: var(--gray-800); margin-bottom: 8px; }
        .confirmation-dialog p { text-align: center; color: var(--gray-600); font-size: 0.9rem; margin-bottom: 20px; }
        .confirmation-dialog .dialog-actions { display: flex; gap: 12px; justify-content: center; }
        .confirmation-dialog .dialog-actions .btn-cancel { background: var(--gray-200); border: none; padding: 10px 24px; border-radius: 12px; font-weight: 600; font-size: 0.85rem; color: var(--gray-700); transition: all 0.2s; }
        .confirmation-dialog .dialog-actions .btn-cancel:hover { background: var(--gray-300); }
        .confirmation-dialog .dialog-actions .btn-confirm { background: var(--success); border: none; padding: 10px 24px; border-radius: 12px; font-weight: 600; font-size: 0.85rem; color: white; transition: all 0.2s; }
        .confirmation-dialog .dialog-actions .btn-confirm:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .confirmation-dialog .dialog-actions .btn-confirm.danger { background: var(--danger); }
        .confirmation-dialog .dialog-actions .btn-confirm.danger:hover { box-shadow: 0 4px 12px rgba(239,68,68,0.3); }
        
        .confirmation-dialog .reject-reason-input { margin: 16px 0; }
        .confirmation-dialog .reject-reason-input textarea { width: 100%; border: 1px solid var(--gray-200); border-radius: 12px; padding: 12px; font-size: 0.85rem; resize: vertical; min-height: 80px; }
        .confirmation-dialog .reject-reason-input textarea:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 2px var(--primary-soft); }
        .confirmation-dialog .reject-reason-input .error-text { color: var(--danger); font-size: 0.75rem; display: none; margin-top: 4px; }
        
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10000;
        }
        
        .toast {
            background: white;
            border-radius: 16px;
            padding: 16px 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 300px;
            animation: toastSlideIn 0.4s ease;
            border-left: 4px solid var(--success);
        }
        .toast.error { border-left-color: var(--danger); }
        
        @keyframes toastSlideIn {
            from { transform: translateX(100px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        .toast i { font-size: 1.4rem; }
        .toast .toast-content { flex: 1; }
        .toast .toast-content .toast-title { font-weight: 600; font-size: 0.9rem; color: var(--gray-800); }
        .toast .toast-content .toast-message { font-size: 0.8rem; color: var(--gray-600); }
        .toast .toast-close { background: none; border: none; color: var(--gray-400); font-size: 1.2rem; cursor: pointer; }
        .toast .toast-close:hover { color: var(--gray-600); }
        
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 992px) { .stats-grid { gap: 15px; } }
        
        @media (max-width: 768px) {
            .mobile-toggle { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 15px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .stat-card { padding: 15px; }
            .stat-number { font-size: 1.4rem; }
            .stat-icon { width: 40px; height: 40px; font-size: 1rem; margin-bottom: 10px; }
            .system-info-banner { flex-direction: column; align-items: flex-start; padding: 15px 20px; }
            .system-info-banner .info-text .details { flex-direction: column; gap: 3px; }
            .card-header-custom { flex-direction: column; align-items: flex-start; }
            .card-header-custom .filters-wrapper { width: 100%; display: flex; flex-direction: column; gap: 8px; }
            .card-header-custom .filters-wrapper select,
            .card-header-custom .filters-wrapper input { width: 100% !important; }
            .action-buttons { flex-wrap: wrap; }
            .action-buttons button,
            .action-buttons a { flex: 1; min-width: 60px; text-align: center; font-size: 0.65rem; padding: 4px 8px; }
            .table-responsive { overflow-x: auto; }
            .student-table { min-width: 700px; }
        }
        
        @media (max-width: 576px) {
            .main-content { padding: 12px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-card { padding: 12px; display: flex; align-items: center; gap: 12px; }
            .stat-icon { margin-bottom: 0; width: 36px; height: 36px; font-size: 0.9rem; }
            .stat-number { font-size: 1.2rem; }
            .stat-label { font-size: 0.65rem; }
            .confirmation-dialog { padding: 25px 20px; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeInUp 0.4s ease forwards; }
        
        .sortable-header {
            cursor: pointer;
            user-select: none;
            transition: all 0.2s;
        }
        .sortable-header:hover { background: var(--gray-200); }
        .sortable-header i { margin-left: 4px; font-size: 0.7rem; }
        
        .highlight-row {
            animation: highlightFlash 1s ease;
        }
        @keyframes highlightFlash {
            0% { background: rgba(16,185,129,0.2); }
            100% { background: transparent; }
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
            <a href="dashboard.php" class="menu-item active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="clearance-items/index.php" class="menu-item"><i class="fas fa-list-check"></i> Clearance Items</a>
            <a href="students.php" class="menu-item"><i class="fas fa-users"></i> Students</a>
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
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Confirmation Dialog -->
    <div class="confirmation-overlay" id="confirmationOverlay">
        <div class="confirmation-dialog">
            <div class="dialog-icon" id="dialogIcon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h4 id="dialogTitle">Confirm Action</h4>
            <p id="dialogMessage">Are you sure you want to proceed?</p>
            <div class="reject-reason-input" id="rejectReasonContainer" style="display: none;">
                <label style="font-weight: 600; font-size: 0.85rem; color: var(--gray-700);">Reason for Rejection <span class="text-danger">*</span></label>
                <textarea id="rejectReason" placeholder="Please provide a detailed reason for rejection..."></textarea>
                <div class="error-text" id="rejectReasonError">Please provide a reason for rejection.</div>
            </div>
            <div class="dialog-actions">
                <button class="btn-cancel" onclick="closeConfirmation()">Cancel</button>
                <button class="btn-confirm" id="confirmBtn" onclick="confirmAction()">Confirm</button>
            </div>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <!-- System Info Banner -->
        <div class="system-info-banner animate-in" style="animation-delay: 0.05s;">
            <div class="info-left">
                <div class="info-text">
                    <span class="uni-name"><?php echo htmlspecialchars($university_name); ?></span>
                    <div class="details">
                        <?php if ($academic_year): ?>
                            <span class="detail-item">
                                <i class="fas fa-calendar-alt"></i>
                                <strong>Academic Year:</strong> <?php echo htmlspecialchars($academic_year); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($graduation_year): ?>
                            <span class="detail-item">
                                <i class="fas fa-graduation-cap"></i>
                                <strong>Graduation:</strong> <?php echo htmlspecialchars($graduation_year); ?>
                            </span>
                        <?php endif; ?>
                        <span class="detail-item">
                            <i class="fas fa-building"></i>
                            <strong>Department:</strong> <?php echo htmlspecialchars($department_name); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="user-badge">
                <i class="fas fa-user-circle"></i>
                <span><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Department Head'); ?></span>
            </div>
        </div>
        
        <!-- Department Info Card - REMOVED -->
        
        <!-- Statistics Cards - 3 cards (Total Students removed) -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card animate-in" style="animation-delay: 0.15s;">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-number" id="statApproved"><?php echo $approved; ?></div>
                    <div class="stat-label">Approved Clearances</div>
                </div>
            </div>
            <div class="stat-card animate-in" style="animation-delay: 0.2s;">
                <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: var(--danger);"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="stat-number" id="statRejected"><?php echo $rejected; ?></div>
                    <div class="stat-label">Rejected Clearances</div>
                </div>
            </div>
            <div class="stat-card animate-in" style="animation-delay: 0.25s;">
                <div class="stat-icon" style="background: rgba(128,0,32,0.1); color: var(--primary);"><i class="fas fa-list-check"></i></div>
                <div>
                    <div class="stat-number"><?php echo $items; ?></div>
                    <div class="stat-label">Clearance Items</div>
                </div>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-users"></i> Students - <?php echo htmlspecialchars($department_name); ?></h5>
                <div class="filters-wrapper d-flex gap-3 flex-wrap align-items-center">
                    <select id="statusFilter" class="filter-select" style="width: 150px;">
                        <option value="all">All Status</option>
                        <option value="pending">🟡 Pending</option>
                        <option value="approved">🟢 Approved</option>
                        <option value="rejected">🔴 Rejected</option>
                    </select>
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="🔍 Search by name, ID, or email..." style="width: 250px;">
                </div>
            </div>
            <div class="table-responsive" id="studentsTableContainer">
                <table id="studentsTable" class="table table-hover mb-0 student-table">
                    <thead>
                        <tr>
                            <th class="sortable-header" data-sort="name">Student <i class="fas fa-sort"></i></th>
                            <th class="sortable-header" data-sort="id">Student ID <i class="fas fa-sort"></i></th>
                            <th class="sortable-header" data-sort="progress">Progress <i class="fas fa-sort"></i></th>
                            <th class="sortable-header" data-sort="status">Status <i class="fas fa-sort"></i></th>
                            <th class="sortable-header" data-sort="date">Applied Date <i class="fas fa-sort"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <?php foreach ($students as $student): 
                            $progress_percent = $student['total_items'] > 0 ? round(($student['completed_items'] / $student['total_items']) * 100, 1) : 0;
                            $status = $student['last_status'] ?? 'pending';
                            
                            // Determine status label and class
                            if ($status == 'approved') {
                                $status_label = 'Approved';
                                $status_class = 'status-approved';
                                $status_icon = '<i class="fas fa-check-circle"></i>';
                            } elseif ($status == 'rejected') {
                                $status_label = 'Rejected';
                                $status_class = 'status-rejected';
                                $status_icon = '<i class="fas fa-times-circle"></i>';
                            } elseif ($progress_percent == 100 && $student['completed_items'] == $student['total_items']) {
                                $status_label = 'Fully Cleared';
                                $status_class = 'status-fully-cleared';
                                $status_icon = '<i class="fas fa-star"></i>';
                            } else {
                                $status_label = 'Pending';
                                $status_class = 'status-pending';
                                $status_icon = '<i class="fas fa-clock"></i>';
                            }
                            
                            // Profile picture
                            $pic = $student['profile_pic'] ?? 'default-avatar.png';
                            $pic_exists = !empty($pic) && $pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $pic);
                            $initials = strtoupper(substr($student['full_name'], 0, 1));
                            
                            // Registration date for sorting
                            $reg_date = $student['registration_date'] ?? date('Y-m-d H:i:s');
                            
                            // Progress display
                            $display_progress = $student['completed_items'] . '/' . $student['total_items'];
                        ?>
                            <tr data-student-id="<?php echo $student['id']; ?>" 
                                data-status="<?php echo $status; ?>"
                                data-name="<?php echo strtolower($student['full_name']); ?>"
                                data-id="<?php echo strtolower($student['student_id']); ?>"
                                data-email="<?php echo strtolower($student['email']); ?>"
                                data-progress="<?php echo $progress_percent; ?>"
                                data-date="<?php echo $student['applied_date'] ?? $reg_date; ?>"
                                data-reg-date="<?php echo $reg_date; ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="student-avatar-sm">
                                            <?php if ($pic_exists): ?>
                                                <img src="../assets/uploads/Students-profile/<?php echo $pic; ?>" alt="<?php echo htmlspecialchars($student['full_name']); ?>">
                                            <?php else: ?>
                                                <?php echo $initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?php echo htmlspecialchars($student['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress-small">
                                            <div class="progress-small-bar" style="width: <?php echo $progress_percent; ?>%"></div>
                                        </div>
                                        <small><?php echo $display_progress; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_icon; ?> <?php echo $status_label; ?>
                                    </span>
                                </td>
                                <td><?php echo $student['applied_date'] ? date('M d, Y', strtotime($student['applied_date'])) : 'N/A'; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($status == 'pending' || $status == 'rejected'): ?>
                                            <button class="btn-approve" onclick="openConfirmation('approve', <?php echo $student['id']; ?>, '<?php echo addslashes($student['full_name']); ?>')">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($status == 'pending'): ?>
                                            <button class="btn-reject" onclick="openConfirmation('reject', <?php echo $student['id']; ?>, '<?php echo addslashes($student['full_name']); ?>')">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="view-student.php?id=<?php echo $student['id']; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="message.php?student_id=<?php echo $student['id']; ?>" class="btn-chat">
                                            <i class="fas fa-comment"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-inbox fa-3x mb-3" style="opacity: 0.3;"></i>
                                    <p>No students have applied for clearance in this department yet</p>
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
    
    <script>
        // ============================================================
        // SIDEBAR FUNCTIONS
        // ============================================================
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
            if (window.innerWidth > 768) { closeSidebar(); }
        });
        
        // ============================================================
        // CONFIRMATION DIALOG
        // ============================================================
        let pendingAction = null;
        let pendingStudentId = null;
        let pendingStudentName = '';
        
        function openConfirmation(action, studentId, studentName) {
            pendingAction = action;
            pendingStudentId = studentId;
            pendingStudentName = studentName;
            
            const overlay = document.getElementById('confirmationOverlay');
            const icon = document.getElementById('dialogIcon');
            const title = document.getElementById('dialogTitle');
            const message = document.getElementById('dialogMessage');
            const confirmBtn = document.getElementById('confirmBtn');
            const rejectReasonContainer = document.getElementById('rejectReasonContainer');
            const rejectReason = document.getElementById('rejectReason');
            const rejectReasonError = document.getElementById('rejectReasonError');
            
            // Reset
            rejectReason.value = '';
            rejectReasonContainer.style.display = 'none';
            rejectReasonError.style.display = 'none';
            confirmBtn.className = 'btn-confirm';
            
            if (action === 'approve') {
                icon.className = 'dialog-icon success';
                icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                title.textContent = 'Approve Student';
                message.textContent = `Are you sure you want to approve ${studentName}'s clearance? This action will update their status to Approved.`;
                confirmBtn.textContent = 'Yes, Approve';
                confirmBtn.className = 'btn-confirm';
            } else if (action === 'reject') {
                icon.className = 'dialog-icon warning';
                icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                title.textContent = 'Reject Student';
                message.textContent = `Are you sure you want to reject ${studentName}'s clearance? Please provide a reason below.`;
                confirmBtn.textContent = 'Yes, Reject';
                confirmBtn.className = 'btn-confirm danger';
                rejectReasonContainer.style.display = 'block';
            }
            
            overlay.classList.add('active');
        }
        
        function closeConfirmation() {
            document.getElementById('confirmationOverlay').classList.remove('active');
            pendingAction = null;
            pendingStudentId = null;
            pendingStudentName = '';
        }
        
        function confirmAction() {
            if (pendingAction === 'reject') {
                const reason = document.getElementById('rejectReason').value.trim();
                const errorEl = document.getElementById('rejectReasonError');
                if (!reason) {
                    errorEl.style.display = 'block';
                    return;
                }
                errorEl.style.display = 'none';
                processStudent(pendingStudentId, 'reject', reason);
            } else if (pendingAction === 'approve') {
                processStudent(pendingStudentId, 'approve', '');
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { closeConfirmation(); }
        });
        
        // ============================================================
        // PROCESS STUDENT (AJAX)
        // ============================================================
        function processStudent(studentId, action, remarks) {
            const overlay = document.getElementById('confirmationOverlay');
            const btn = document.getElementById('confirmBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    ajax_action: action,
                    student_id: studentId,
                    remarks: remarks
                },
                dataType: 'json',
                success: function(response) {
                    btn.disabled = false;
                    btn.innerHTML = action === 'approve' ? 'Yes, Approve' : 'Yes, Reject';
                    closeConfirmation();
                    
                    if (response.success) {
                        showToast('success', 'Success', response.message);
                        updateStudentRow(studentId, response.data);
                        updateStats(response.data.stats);
                    } else {
                        showToast('error', 'Error', response.message);
                    }
                },
                error: function(xhr) {
                    btn.disabled = false;
                    btn.innerHTML = action === 'approve' ? 'Yes, Approve' : 'Yes, Reject';
                    closeConfirmation();
                    showToast('error', 'Error', 'An unexpected error occurred. Please try again.');
                    console.error('AJAX Error:', xhr.responseText);
                }
            });
        }
        
        // ============================================================
        // UPDATE STUDENT ROW
        // ============================================================
        function updateStudentRow(studentId, data) {
            const row = $(`tr[data-student-id="${studentId}"]`);
            if (!row.length) return;
            
            const status = data.status;
            const progress = data.progress || { total: 0, completed: 0, percent: 0 };
            
            let statusLabel = '';
            let statusClass = '';
            let statusIcon = '';
            
            if (status === 'approved') {
                statusLabel = 'Approved';
                statusClass = 'status-approved';
                statusIcon = '<i class="fas fa-check-circle"></i>';
            } else if (status === 'rejected') {
                statusLabel = 'Rejected';
                statusClass = 'status-rejected';
                statusIcon = '<i class="fas fa-times-circle"></i>';
            } else if (progress.percent == 100 && progress.total > 0) {
                statusLabel = 'Fully Cleared';
                statusClass = 'status-fully-cleared';
                statusIcon = '<i class="fas fa-star"></i>';
            } else {
                statusLabel = 'Pending';
                statusClass = 'status-pending';
                statusIcon = '<i class="fas fa-clock"></i>';
            }
            
            // Update progress
            const progressDisplay = progress.completed + '/' + progress.total;
            row.find('td:eq(2) .progress-small-bar').css('width', progress.percent + '%');
            row.find('td:eq(2) small').text(progressDisplay);
            
            // Update status
            row.find('td:eq(3)').html(`<span class="status-badge ${statusClass}">${statusIcon} ${statusLabel}</span>`);
            row.attr('data-status', status);
            row.attr('data-progress', progress.percent);
            
            // Update actions
            const actionsCell = row.find('td:eq(5)');
            const studentName = row.find('td:eq(0) strong').text();
            const studentIdAttr = row.data('student-id');
            
            let actionsHtml = '<div class="action-buttons">';
            
            if (status === 'pending' || status === 'rejected') {
                actionsHtml += `<button class="btn-approve" onclick="openConfirmation('approve', ${studentIdAttr}, '${studentName.replace(/'/g, "\\'")}')">
                                    <i class="fas fa-check"></i>
                                </button>`;
            }
            if (status === 'pending') {
                actionsHtml += `<button class="btn-reject" onclick="openConfirmation('reject', ${studentIdAttr}, '${studentName.replace(/'/g, "\\'")}')">
                                    <i class="fas fa-times"></i>
                                </button>`;
            }
            actionsHtml += `<a href="view-student.php?id=${studentIdAttr}" class="btn-view"><i class="fas fa-eye"></i></a>
                            <a href="message.php?student_id=${studentIdAttr}" class="btn-chat"><i class="fas fa-comment"></i></a>
                        </div>`;
            
            actionsCell.html(actionsHtml);
            
            // Highlight the row
            row.addClass('highlight-row');
            setTimeout(() => { row.removeClass('highlight-row'); }, 1500);
        }
        
        // ============================================================
        // UPDATE STATS
        // ============================================================
        function updateStats(stats) {
            if (stats) {
                if (stats.approved !== undefined) {
                    $('#statApproved').text(stats.approved);
                }
                if (stats.rejected !== undefined) {
                    $('#statRejected').text(stats.rejected);
                }
            }
        }
        
        // ============================================================
        // TOAST NOTIFICATIONS
        // ============================================================
        function showToast(type, title, message) {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type === 'error' ? 'error' : ''}`;
            
            const icon = type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle';
            const iconColor = type === 'error' ? 'var(--danger)' : 'var(--success)';
            
            toast.innerHTML = `
                <i class="fas ${icon}" style="color: ${iconColor};"></i>
                <div class="toast-content">
                    <div class="toast-title">${title}</div>
                    <div class="toast-message">${message}</div>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) { toast.remove(); }
            }, 5000);
        }
        
        // ============================================================
        // SEARCH AND FILTER
        // ============================================================
        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#studentsTableBody tr[data-student-id]');
            
            rows.forEach(row => {
                const name = (row.dataset.name || '').toLowerCase();
                const studentId = (row.dataset.id || '').toLowerCase();
                const email = (row.dataset.email || '').toLowerCase();
                const status = row.dataset.status || '';
                
                // Partial matching for search
                const matchesSearch = searchTerm === '' || 
                                     name.includes(searchTerm) || 
                                     studentId.includes(searchTerm) || 
                                     email.includes(searchTerm);
                
                const matchesStatus = statusFilter === 'all' || status === statusFilter;
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }
        
        // ============================================================
        // SORTING
        // ============================================================
        let currentSort = { column: 'date', direction: 'desc' };
        
        function sortTable(column, direction) {
            const tbody = document.getElementById('studentsTableBody');
            const rows = Array.from(tbody.querySelectorAll('tr[data-student-id]'));
            
            if (rows.length === 0) return;
            
            rows.sort((a, b) => {
                let aVal, bVal;
                
                switch(column) {
                    case 'name':
                        aVal = (a.dataset.name || '').toLowerCase();
                        bVal = (b.dataset.name || '').toLowerCase();
                        break;
                    case 'id':
                        aVal = (a.dataset.id || '').toLowerCase();
                        bVal = (b.dataset.id || '').toLowerCase();
                        break;
                    case 'progress':
                        aVal = parseFloat(a.dataset.progress || 0);
                        bVal = parseFloat(b.dataset.progress || 0);
                        break;
                    case 'status':
                        const statusOrder = { 'fully cleared': 0, 'approved': 1, 'pending': 2, 'rejected': 3 };
                        aVal = statusOrder[(a.dataset.status || 'pending').toLowerCase()] || 2;
                        bVal = statusOrder[(b.dataset.status || 'pending').toLowerCase()] || 2;
                        break;
                    case 'date':
                    default:
                        aVal = a.dataset.date || a.dataset.regDate || '';
                        bVal = b.dataset.date || b.dataset.regDate || '';
                        break;
                }
                
                if (typeof aVal === 'string') {
                    const compare = aVal.localeCompare(bVal);
                    return direction === 'asc' ? compare : -compare;
                } else {
                    return direction === 'asc' ? aVal - bVal : bVal - aVal;
                }
            });
            
            rows.forEach(row => tbody.appendChild(row));
            
            // Update sort indicators
            document.querySelectorAll('.sortable-header i').forEach(icon => {
                icon.className = 'fas fa-sort';
            });
            
            const header = document.querySelector(`.sortable-header[data-sort="${column}"] i`);
            if (header) {
                header.className = direction === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
            }
        }
        
        // ============================================================
        // SORT HEADER CLICK HANDLER
        // ============================================================
        document.querySelectorAll('.sortable-header').forEach(header => {
            header.addEventListener('click', function() {
                const column = this.dataset.sort;
                
                if (currentSort.column === column) {
                    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
                } else {
                    currentSort.column = column;
                    currentSort.direction = 'asc';
                }
                
                sortTable(column, currentSort.direction);
            });
        });
        
        // ============================================================
        // EVENT LISTENERS
        // ============================================================
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);
        
        // ============================================================
        // INITIALIZE - Sort by newest first (registration date DESC)
        // ============================================================
        document.addEventListener('DOMContentLoaded', function() {
            currentSort.column = 'date';
            currentSort.direction = 'desc';
            sortTable('date', 'desc');
        });
    </script>
</body>
</html>