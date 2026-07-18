<?php
session_start();
require_once '../config/config.php';
require_once '../config/email_config.php';
require_once '../config/chat_functions.php';
require_once '../includes/email_templates.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reason = isset($_GET['reason']) ? sanitizeInput($_GET['reason']) : '';

if ($id == 0 || empty($action)) {
    redirect('dashboard.php');
}

// Get clearance details
$stmt = $pdo->prepare("
    SELECT sc.*, ci.department_id, ci.item_name, ci.id as clearance_item_id,
           u.email, u.full_name, u.student_id
    FROM student_clearance sc
    JOIN clearance_items ci ON sc.clearance_item_id = ci.id
    JOIN users u ON sc.student_id = u.id
    WHERE sc.id = ?
");
$stmt->execute([$id]);
$clearance = $stmt->fetch();

if (!$clearance || $clearance['department_id'] != $_SESSION['selected_department_id']) {
    redirect('dashboard.php');
}

if ($action == 'approve') {
    // Update clearance status
    $stmt = $pdo->prepare("
        UPDATE student_clearance 
        SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), remarks = 'Approved by department head'
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $id]);
    
    logActivity($pdo, $_SESSION['user_id'], 'Approve Clearance', "Approved clearance for: {$clearance['full_name']} - {$clearance['item_name']}");
    
    // Send approval email to student
    $email_body = emailClearanceApproved(
        $clearance['full_name'],
        $_SESSION['selected_department_name'],
        $clearance['item_name'],
        'Approved by department head'
    );
    sendEmail($clearance['email'], 'Clearance Approved - ' . $_SESSION['selected_department_name'], $email_body);
    
    // Create notification for student
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
        VALUES (?, 'Clearance Approved', ?, 'success', ?, NOW())
    ");
    $notification_message = "Your clearance for {$clearance['item_name']} has been approved by {$_SESSION['selected_department_name']}.";
    $stmt->execute([$clearance['student_id'], $notification_message, "student/clearance-status.php"]);
    
    // Check if student is fully cleared for graduation
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ci.id) as total_items,
               SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count
        FROM clearance_items ci
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
        WHERE ci.is_mandatory = 1
    ");
    $stmt->execute([$clearance['student_id']]);
    $progress = $stmt->fetch();
    
    if ($progress['total_items'] > 0 && $progress['approved_count'] == $progress['total_items']) {
        // Student is fully cleared
        $certificate_number = 'GCS-' . date('Y') . '-' . str_pad($clearance['student_id'], 5, '0', STR_PAD_LEFT);
        
        // Insert certificate record
        $stmt = $pdo->prepare("
            INSERT INTO clearance_certificates (student_id, certificate_number, issued_date, created_at) 
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$clearance['student_id'], $certificate_number]);
        
        // Send final clearance email
        $final_email_body = emailFinalClearanceCompleted($clearance['full_name'], $clearance['student_id'], $certificate_number);
        sendEmail($clearance['email'], '🎉 Congratulations! You Are Fully Cleared!', $final_email_body);
        
        // Create notification for student
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, created_at) 
            VALUES (?, 'Graduation Clearance Complete', 'Congratulations! You have successfully completed all graduation clearance requirements.', 'success', ?, NOW())
        ");
        $stmt->execute([$clearance['student_id'], "student/download-certificate.php"]);
    }
    
    $_SESSION['success'] = "Clearance approved successfully! The student has been notified.";
    
} elseif ($action == 'reject') {
    if (empty($reason)) {
        $_SESSION['error'] = "Please provide a reason for rejection.";
        redirect('view-student.php?id=' . $clearance['student_id']);
        exit();
    }
    
    // Update clearance status
    $stmt = $pdo->prepare("
        UPDATE student_clearance 
        SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), remarks = ?
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $reason, $id]);
    
    logActivity($pdo, $_SESSION['user_id'], 'Reject Clearance', "Rejected clearance for: {$clearance['full_name']} - {$clearance['item_name']}. Reason: $reason");
    
    // Send rejection email to student
    $email_body = emailClearanceRejected(
        $clearance['full_name'],
        $_SESSION['selected_department_name'],
        $clearance['item_name'],
        $reason
    );
    sendEmail($clearance['email'], 'Clearance Rejected - ' . $_SESSION['selected_department_name'], $email_body);
    
    // Create notification for student
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
        VALUES (?, 'Clearance Rejected', ?, 'danger', ?, NOW())
    ");
    $notification_message = "Your clearance for {$clearance['item_name']} has been rejected by {$_SESSION['selected_department_name']}. Reason: " . substr($reason, 0, 100);
    $stmt->execute([$clearance['student_id'], $notification_message, "student/clearance-status.php"]);
    
    // Check if there's an existing conversation, if not, create one for follow-up
    $stmt = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE student_id = ? AND department_id = ? 
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmt->execute([$clearance['student_id'], $clearance['department_id']]);
    $conversation = $stmt->fetch();
    
    if ($conversation) {
        // Send message in existing conversation
        sendMessage($pdo, $conversation['id'], $_SESSION['user_id'], $clearance['student_id'], 
            "Your clearance for '{$clearance['item_name']}' has been rejected. Reason: " . $reason . " Please address the issue and resubmit.");
    } else {
        // Create new conversation
        $stmt = $pdo->prepare("
            INSERT INTO conversations (student_id, department_id, subject, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$clearance['student_id'], $clearance['department_id'], "Clearance Rejection - {$clearance['item_name']}"]);
        $convo_id = $pdo->lastInsertId();
        
        sendMessage($pdo, $convo_id, $_SESSION['user_id'], $clearance['student_id'], 
            "Your clearance for '{$clearance['item_name']}' has been rejected. Reason: " . $reason . " Please address the issue and resubmit.");
    }
    
    $_SESSION['success'] = "Clearance rejected successfully. The student has been notified.";
}

redirect('dashboard.php');
?>