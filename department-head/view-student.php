<?php
// ============================================================
// DEBUGGING - Enable error reporting
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

session_start();

// Check if config files exist before requiring
$config_path = '../config/config.php';
if (!file_exists($config_path)) {
    die("Config file not found at: " . $config_path);
}
require_once $config_path;

$chat_path = '../config/chat_functions.php';
if (!file_exists($chat_path)) {
    die("Chat functions file not found at: " . $chat_path);
}
require_once $chat_path;

$email_path = '../includes/send_email.php';
if (!file_exists($email_path)) {
    die("Email file not found at: " . $email_path);
}
require_once $email_path;

$templates_path = '../includes/email_templates.php';
if (!file_exists($templates_path)) {
    die("Email templates file not found at: " . $templates_path);
}
require_once $templates_path;

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

// Check if department is selected
if (!isset($_SESSION['selected_department_id'])) {
    redirect('select-department.php');
}

$department_id = $_SESSION['selected_department_id'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

if (!$student_id) {
    redirect('students.php');
}

try {
    // Get student information with profile picture
    $stmt = $pdo->prepare("
        SELECT id, full_name, student_id, email, phone, profile_pic
        FROM users 
        WHERE id = ? AND role = 'student'
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        redirect('students.php');
    }

    // Get department name
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $dept = $stmt->fetch();
    $department_name = $dept['department_name'] ?? 'Department';

    // ============================================================
    // PROCESS APPROVAL / REJECTION - FIXED
    // ============================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $remarks = sanitizeInput($_POST['remarks'] ?? '');
        $clearance_item_id = isset($_POST['clearance_item_id']) ? (int)$_POST['clearance_item_id'] : 0;
        $is_reapproval = isset($_POST['is_reapproval']) ? true : false;
        
        if ($action == 'approve') {
            try {
                $pdo->beginTransaction();
                
                // Check if record exists
                $stmt = $pdo->prepare("
                    SELECT id, status FROM student_clearance 
                    WHERE clearance_item_id = ? AND student_id = ? AND department_id = ?
                ");
                $stmt->execute([$clearance_item_id, $student_id, $department_id]);
                $existing = $stmt->fetch();
                
                // Track previous status for logging
                $previous_status = $existing ? $existing['status'] : 'none';
                
                if ($existing) {
                    // UPDATE existing record - CRITICAL: Set status to 'approved'
                    $stmt = $pdo->prepare("
                        UPDATE student_clearance 
                        SET status = 'approved', 
                            reviewed_by = ?, 
                            reviewed_at = NOW(), 
                            remarks = ? 
                        WHERE clearance_item_id = ? AND student_id = ? AND department_id = ?
                    ");
                    $result = $stmt->execute([
                        $_SESSION['user_id'], 
                        $remarks ?: ($previous_status == 'rejected' ? 'Re-approved by department head' : 'Approved by department head'), 
                        $clearance_item_id, 
                        $student_id,
                        $department_id
                    ]);
                } else {
                    // INSERT new record with status 'approved'
                    $stmt = $pdo->prepare("
                        INSERT INTO student_clearance 
                        (student_id, clearance_item_id, department_id, status, reviewed_by, reviewed_at, remarks, created_at) 
                        VALUES (?, ?, ?, 'approved', ?, NOW(), ?, NOW())
                    ");
                    $result = $stmt->execute([
                        $student_id, 
                        $clearance_item_id, 
                        $department_id, 
                        $_SESSION['user_id'], 
                        $remarks ?: 'Approved by department head'
                    ]);
                }
                
                if ($result) {
                    // Get item details for email
                    $stmt = $pdo->prepare("SELECT item_name FROM clearance_items WHERE id = ?");
                    $stmt->execute([$clearance_item_id]);
                    $item = $stmt->fetch();
                    
                    // Determine if this is a re-approval (was previously rejected)
                    $is_reapproval = ($previous_status == 'rejected');
                    
                    // Send appropriate email
                    if ($item) {
                        if ($is_reapproval) {
                            $email_body = "Dear " . $student['full_name'] . ",\n\n";
                            $email_body .= "Great news! Your clearance for '{$item['item_name']}' has been re-approved by {$department_name} after review.\n\n";
                            $email_body .= "The issues that were previously identified have been resolved, and your clearance has been approved.\n\n";
                            $email_body .= "Remarks: " . ($remarks ?: "Re-approved after correction") . "\n\n";
                            $email_body .= "Thank you for completing the necessary corrections.\n\n";
                            $email_body .= "Regards,\n" . $department_name . " Department";
                            
                            sendEmail($student['email'], 'Clearance Re-Approved - ' . $department_name, $email_body);
                        } else {
                            $email_body = emailClearanceApproved(
                                $student['full_name'],
                                $department_name,
                                $item['item_name'],
                                $remarks ?: 'Approved by department head'
                            );
                            sendEmail($student['email'], 'Clearance Approved - ' . $department_name, $email_body);
                        }
                    }
                    
                    // Create notification for student
                    $notification_title = $is_reapproval ? 'Clearance Re-Approved' : 'Clearance Approved';
                    $notification_msg = $is_reapproval 
                        ? "Your clearance for '{$item['item_name']}' has been re-approved by {$department_name} after review."
                        : "Your clearance for '{$item['item_name']}' has been approved by {$department_name}.";
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
                        VALUES (?, ?, ?, 'success', 'student/clearance-status.php', NOW())
                    ");
                    $stmt->execute([$student_id, $notification_title, $notification_msg]);
                    
                    // Log the action
                    $log_action = $is_reapproval ? 'Re-Approve Clearance Item' : 'Approve Clearance Item';
                    $log_details = ($is_reapproval ? "Re-approved (was Rejected) item ID: " : "Approved item ID: ") . "$clearance_item_id for student ID: $student_id";
                    logActivity($pdo, $_SESSION['user_id'], $log_action, $log_details);
                    
                    $pdo->commit();
                    $success = $is_reapproval 
                        ? "Clearance item re-approved successfully! Student has been notified."
                        : "Clearance item approved successfully! Student has been notified.";
                    
                } else {
                    $pdo->rollBack();
                    $error = "Failed to approve clearance.";
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
                error_log("Approval error: " . $e->getMessage());
            }
            
        } elseif ($action == 'reject') {
            if (empty($remarks)) {
                $error = "Please provide a reason for rejection.";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Check if record exists
                    $stmt = $pdo->prepare("
                        SELECT id, status FROM student_clearance 
                        WHERE clearance_item_id = ? AND student_id = ? AND department_id = ?
                    ");
                    $stmt->execute([$clearance_item_id, $student_id, $department_id]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        // Update existing record
                        $stmt = $pdo->prepare("
                            UPDATE student_clearance 
                            SET status = 'rejected', 
                                reviewed_by = ?, 
                                reviewed_at = NOW(), 
                                remarks = ? 
                            WHERE clearance_item_id = ? AND student_id = ? AND department_id = ?
                        ");
                        $result = $stmt->execute([
                            $_SESSION['user_id'], 
                            $remarks, 
                            $clearance_item_id, 
                            $student_id,
                            $department_id
                        ]);
                    } else {
                        // Insert new record
                        $stmt = $pdo->prepare("
                            INSERT INTO student_clearance 
                            (student_id, clearance_item_id, department_id, status, reviewed_by, reviewed_at, remarks, created_at) 
                            VALUES (?, ?, ?, 'rejected', ?, NOW(), ?, NOW())
                        ");
                        $result = $stmt->execute([
                            $student_id, 
                            $clearance_item_id, 
                            $department_id, 
                            $_SESSION['user_id'], 
                            $remarks
                        ]);
                    }
                    
                    if ($result) {
                        // Get item details for email
                        $stmt = $pdo->prepare("SELECT item_name FROM clearance_items WHERE id = ?");
                        $stmt->execute([$clearance_item_id]);
                        $item = $stmt->fetch();
                        
                        if ($item) {
                            // Send rejection email to student
                            $email_body = emailClearanceRejected(
                                $student['full_name'],
                                $department_name,
                                $item['item_name'],
                                $remarks
                            );
                            sendEmail($student['email'], 'Clearance Rejected - ' . $department_name, $email_body);
                        }
                        
                        // Create notification for student
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, title, message, type, link, created_at) 
                            VALUES (?, 'Clearance Rejected', ?, 'danger', 'student/clearance-status.php', NOW())
                        ");
                        $notification_msg = "Your clearance for '{$item['item_name']}' has been rejected by {$department_name}. Reason: " . substr($remarks, 0, 100);
                        $stmt->execute([$student_id, $notification_msg]);
                        
                        // Create or update conversation for follow-up
                        $stmt = $pdo->prepare("
                            SELECT id FROM conversations 
                            WHERE student_id = ? AND department_id = ? 
                            ORDER BY updated_at DESC LIMIT 1
                        ");
                        $stmt->execute([$student_id, $department_id]);
                        $conversation = $stmt->fetch();
                        
                        if ($conversation) {
                            sendMessage($pdo, $conversation['id'], $_SESSION['user_id'], $student_id, 
                                "Your clearance for '{$item['item_name']}' has been rejected. Reason: " . $remarks);
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO conversations (student_id, department_id, subject, created_at) 
                                VALUES (?, ?, ?, NOW())
                            ");
                            $stmt->execute([$student_id, $department_id, "Clearance Rejection - {$item['item_name']}"]);
                            $convo_id = $pdo->lastInsertId();
                            sendMessage($pdo, $convo_id, $_SESSION['user_id'], $student_id, 
                                "Your clearance for '{$item['item_name']}' has been rejected. Reason: " . $remarks);
                        }
                        
                        $pdo->commit();
                        $success = "Clearance item rejected successfully! Student has been notified.";
                        logActivity($pdo, $_SESSION['user_id'], 'Reject Clearance Item', 
                            "Rejected item ID: $clearance_item_id for student ID: $student_id. Reason: $remarks");
                        
                    } else {
                        $pdo->rollBack();
                        $error = "Failed to reject clearance.";
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = "Error: " . $e->getMessage();
                    error_log("Rejection error: " . $e->getMessage());
                }
            }
        }
    }

    // ============================================================
    // GET CLEARANCE ITEMS WITH STUDENT STATUS
    // ============================================================
    $stmt = $pdo->prepare("
        SELECT 
            ci.id,
            ci.item_name,
            ci.description,
            ci.requires_document,
            ci.is_mandatory,
            sc.id as clearance_id,
            sc.status,
            sc.remarks as review_remarks,
            sc.document_path,
            sc.reviewed_at,
            sc.created_at as submitted_at,
            d.department_name
        FROM clearance_items ci
        JOIN departments d ON ci.department_id = d.id
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
        WHERE ci.department_id = ?
        ORDER BY ci.is_mandatory DESC, ci.sort_order ASC, ci.item_name ASC
    ");
    $stmt->execute([$student_id, $department_id]);
    $clearance_items = $stmt->fetchAll();

    // Calculate overall progress
    $total_items = count($clearance_items);
    $approved_items = count(array_filter($clearance_items, function($item) { 
        return $item['status'] == 'approved'; 
    }));
    $pending_items = count(array_filter($clearance_items, function($item) { 
        return $item['status'] == 'pending' || $item['status'] == null; 
    }));
    $rejected_items = count(array_filter($clearance_items, function($item) { 
        return $item['status'] == 'rejected'; 
    }));
    $progress_percent = $total_items > 0 ? round(($approved_items / $total_items) * 100, 1) : 0;

    // Get unread messages count
    $unread_messages = getUnreadMessageCount($pdo, $_SESSION['user_id']);

    // Check if student has a profile picture
    $student_pic = $student['profile_pic'] ?? 'default-avatar.png';
    $student_pic_exists = !empty($student_pic) && $student_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $student_pic);
    $student_initials = strtoupper(substr($student['full_name'], 0, 1));

    $page_title = 'Review Student - ' . htmlspecialchars($student['full_name']);

} catch (Exception $e) {
    error_log("Error in view-student.php: " . $e->getMessage());
    die("Error loading page: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo htmlspecialchars($page_title); ?> - Department Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            padding: 8px 18px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
            transition: all 0.2s;
        }
        
        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateX(-3px);
        }
        
        .student-info-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        
        .student-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 2rem;
            flex-shrink: 0;
        }
        
        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .student-avatar .avatar-initials {
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        .student-info-header {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .progress-stats {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .stat-badge {
            text-align: center;
            padding: 10px 20px;
            background: var(--gray-100);
            border-radius: 12px;
            flex: 1;
            min-width: 100px;
        }
        
        .stat-badge .number {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .stat-badge .label {
            font-size: 0.7rem;
            color: var(--gray-600);
        }
        
        .clearance-item {
            background: white;
            border-radius: 16px;
            margin-bottom: 20px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .clearance-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .item-header {
            padding: 18px 25px;
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .item-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .item-icon {
            width: 45px;
            height: 45px;
            background: var(--primary-soft);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .item-title h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .item-title small {
            font-size: 0.7rem;
            color: var(--gray-600);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-approved { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .item-body {
            padding: 20px 25px;
        }
        
        .document-link {
            background: var(--gray-100);
            padding: 12px 15px;
            border-radius: 10px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            color: var(--info);
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .document-link:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }
        
        .remarks-box {
            background: var(--gray-100);
            padding: 12px 15px;
            border-radius: 10px;
            margin-top: 15px;
            font-size: 0.85rem;
        }
        
        .remarks-box i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--gray-200);
        }
        
        .btn-approve {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239,68,68,0.3);
        }
        
        .btn-reapprove {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-reapprove:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128,0,32,0.3);
        }
        
        .btn-reapprove i {
            margin-right: 6px;
        }
        
        .modal-content {
            border-radius: 16px;
            border: none;
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: 16px 16px 0 0;
            padding: 16px 20px;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        textarea.form-control {
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            padding: 12px 15px;
        }
        
        textarea.form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-soft);
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
        
        .badge-reapproved {
            background: var(--primary);
            color: white;
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
            .item-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .action-buttons {
                width: 100%;
            }
            .btn-approve, .btn-reject, .btn-reapprove {
                flex: 1;
                text-align: center;
            }
            .progress-stats {
                flex-direction: column;
            }
            .student-info-header {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @media (max-width: 576px) {
            .student-info-card {
                padding: 18px;
            }
            .item-header, .item-body {
                padding: 15px 18px;
            }
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
        <div class="container-fluid">
            <a href="students.php?filter=<?php echo $_GET['filter'] ?? 'all'; ?>" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Students
            </a>
            
            <?php if ($success): ?>
                <div class="alert-custom alert-success">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert-custom alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Student Information Card -->
            <div class="student-info-card">
                <div class="student-info-header">
                    <div class="student-avatar">
                        <?php if ($student_pic_exists): ?>
                            <img src="../assets/uploads/Students-profile/<?php echo htmlspecialchars($student_pic); ?>" alt="<?php echo htmlspecialchars($student['full_name']); ?>">
                        <?php else: ?>
                            <span class="avatar-initials"><?php echo $student_initials; ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h4 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                        <p class="text-muted mb-1">
                            <i class="fas fa-id-card me-1"></i> <?php echo htmlspecialchars($student['student_id']); ?>
                        </p>
                        <p class="text-muted mb-1">
                            <i class="fas fa-envelope me-1"></i> <?php echo htmlspecialchars($student['email']); ?>
                        </p>
                        <?php if ($student['phone']): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($student['phone']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="progress-stats">
                    <div class="stat-badge">
                        <div class="number" style="color: var(--success);"><?php echo $approved_items; ?></div>
                        <div class="label">Approved</div>
                    </div>
                    <div class="stat-badge">
                        <div class="number" style="color: var(--warning);"><?php echo $pending_items; ?></div>
                        <div class="label">Pending</div>
                    </div>
                    <div class="stat-badge">
                        <div class="number" style="color: var(--danger);"><?php echo $rejected_items; ?></div>
                        <div class="label">Rejected</div>
                    </div>
                    <div class="stat-badge">
                        <div class="number"><?php echo $progress_percent; ?>%</div>
                        <div class="label">Complete</div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar" style="width: <?php echo $progress_percent; ?>%; background: linear-gradient(90deg, var(--primary), var(--primary-light));"></div>
                    </div>
                    <div class="text-center mt-2">
                        <small class="text-muted">Overall Clearance Progress: <?php echo $progress_percent; ?>%</small>
                    </div>
                </div>
            </div>
            
            <!-- Clearance Items -->
            <h5 class="mb-3"><i class="fas fa-list-check me-2" style="color: var(--primary);"></i> Clearance Requirements</h5>
            
            <?php foreach ($clearance_items as $item): ?>
                <div class="clearance-item">
                    <div class="item-header">
                        <div class="item-title">
                            <div class="item-icon">
                                <?php if ($item['requires_document']): ?>
                                    <i class="fas fa-file-alt"></i>
                                <?php else: ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h5>
                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                    <?php if ($item['is_mandatory']): ?>
                                        <span class="badge bg-danger ms-2" style="font-size: 0.6rem;">Mandatory</span>
                                    <?php endif; ?>
                                </h5>
                                <small><?php echo htmlspecialchars($item['description']); ?></small>
                            </div>
                        </div>
                        <div>
                            <?php if ($item['status'] == 'approved'): ?>
                                <span class="status-badge status-approved">
                                    <i class="fas fa-check-circle"></i> Approved
                                </span>
                            <?php elseif ($item['status'] == 'rejected'): ?>
                                <span class="status-badge status-rejected">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> Pending Review
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="item-body">
                        <?php if ($item['requires_document'] && $item['document_path']): ?>
                            <div class="mb-3">
                                <a href="../assets/uploads/documents/<?php echo htmlspecialchars($item['document_path']); ?>" class="document-link" target="_blank">
                                    <i class="fas fa-file-pdf"></i> View Submitted Document
                                </a>
                            </div>
                        <?php elseif ($item['requires_document'] && !$item['document_path']): ?>
                            <div class="mb-3 text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i> No document uploaded yet
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($item['review_remarks'] && $item['status'] != 'pending'): ?>
                            <div class="remarks-box">
                                <i class="fas fa-comment-dots"></i>
                                <strong>Remarks:</strong> <?php echo htmlspecialchars($item['review_remarks']); ?>
                                <?php if ($item['reviewed_at']): ?>
                                    <br><small class="text-muted">Reviewed on <?php echo date('M d, Y h:i A', strtotime($item['reviewed_at'])); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- ACTION BUTTONS -->
                        <?php if ($item['status'] == 'pending' || $item['status'] == null): ?>
                            <div class="action-buttons">
                                <button class="btn-approve" onclick="showApproveModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', false)">
                                    <i class="fas fa-check me-1"></i> Approve
                                </button>
                                <button class="btn-reject" onclick="showRejectModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>')">
                                    <i class="fas fa-times me-1"></i> Reject
                                </button>
                            </div>
                            
                        <?php elseif ($item['status'] == 'rejected'): ?>
                            <div class="action-buttons">
                                <button class="btn-reapprove" onclick="showApproveModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>', true)">
                                    <i class="fas fa-undo-alt me-1"></i> Re-Approve Student
                                </button>
                                <span class="text-muted ms-2" style="font-size: 0.8rem; align-self: center;">
                                    <i class="fas fa-info-circle me-1"></i> Student has corrected issues and can be re-approved
                                </span>
                            </div>
                            
                        <?php elseif ($item['status'] == 'approved'): ?>
                            <div class="mt-2">
                                <span class="badge bg-success">Approved</span>
                                <span class="text-muted ms-2">Clearance completed for this item.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($clearance_items)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-3x mb-3" style="color: var(--gray-300);"></i>
                    <p class="text-muted">No clearance items found for this department</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i> <span id="approveModalTitle">Approve Clearance</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="clearance_item_id" id="approve_item_id">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="is_reapproval" id="is_reapproval" value="0">
                        
                        <div class="mb-3">
                            <label class="form-label">Item</label>
                            <p class="form-control-static" id="approve_item_name"></p>
                        </div>
                        
                        <div id="reapproval_notice" class="alert alert-warning py-2" style="font-size: 0.8rem; display: none;">
                            <i class="fas fa-exclamation-triangle me-1"></i> 
                            <strong>Re-Approval Notice:</strong> This student was previously rejected. Review the corrected documents before approving.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Remarks <span class="text-muted">(Optional)</span></label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Add any comments or notes about this approval..."></textarea>
                        </div>
                        
                        <div class="alert alert-info py-2" style="font-size: 0.75rem;">
                            <i class="fas fa-info-circle me-1"></i> 
                            <span id="approval_action_text">This action will mark the clearance item as approved and notify the student.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" id="approveConfirmBtn" style="background: var(--success); color: white;">
                            <i class="fas fa-check me-1"></i> <span id="approveBtnText">Confirm Approval</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i> Reject Clearance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="clearance_item_id" id="reject_item_id">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label class="form-label">Item</label>
                            <p class="form-control-static" id="reject_item_name"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                            <textarea name="remarks" class="form-control" rows="4" required placeholder="Please provide a detailed reason for rejection..."></textarea>
                            <small class="text-muted">This reason will be visible to the student and included in the email notification.</small>
                        </div>
                        <div class="alert alert-danger py-2" style="font-size: 0.75rem;">
                            <i class="fas fa-exclamation-triangle me-1"></i> This action cannot be undone. The student will be notified and will need to resubmit.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" style="background: var(--danger); color: white;">Confirm Rejection</button>
                    </div>
                </form>
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
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        function showApproveModal(itemId, itemName, isReapproval) {
            document.getElementById('approve_item_id').value = itemId;
            document.getElementById('approve_item_name').innerHTML = '<strong>' + itemName + '</strong>';
            document.getElementById('is_reapproval').value = isReapproval ? '1' : '0';
            
            if (isReapproval) {
                document.getElementById('approveModalTitle').textContent = 'Re-Approve Clearance';
                document.getElementById('reapproval_notice').style.display = 'block';
                document.getElementById('approval_action_text').textContent = 'This action will re-approve this clearance item. The student will be notified that their clearance has been re-approved.';
                document.getElementById('approveBtnText').textContent = 'Confirm Re-Approval';
                document.getElementById('approveConfirmBtn').style.background = 'linear-gradient(135deg, #800020, #5a0016)';
            } else {
                document.getElementById('approveModalTitle').textContent = 'Approve Clearance';
                document.getElementById('reapproval_notice').style.display = 'none';
                document.getElementById('approval_action_text').textContent = 'This action will mark the clearance item as approved and notify the student.';
                document.getElementById('approveBtnText').textContent = 'Confirm Approval';
                document.getElementById('approveConfirmBtn').style.background = '#10b981';
            }
            
            $('#approveModal').modal('show');
        }
        
        function showRejectModal(itemId, itemName) {
            document.getElementById('reject_item_id').value = itemId;
            document.getElementById('reject_item_name').innerHTML = '<strong>' + itemName + '</strong>';
            $('#rejectModal').modal('show');
        }
    </script>
</body>
</html>