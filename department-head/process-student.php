<?php
// department-head/process-student.php

session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';
require_once '../includes/send_email.php';
require_once '../includes/email_templates.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

// Check if department is selected
if (!isset($_SESSION['selected_department_id'])) {
    redirect('select-department.php');
}

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reason = isset($_GET['reason']) ? sanitizeInput($_GET['reason']) : '';

if ($student_id == 0 || empty($action)) {
    redirect('dashboard.php');
}

$department_id = $_SESSION['selected_department_id'];
$department_name = $_SESSION['selected_department_name'];

// Get student info
$stmt = $pdo->prepare("SELECT full_name, email, student_id FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    redirect('students.php');
}

if ($action == 'approve') {
    // Update all pending clearances for this student in this department to approved
    $stmt = $pdo->prepare("
        UPDATE student_clearance sc
        JOIN clearance_items ci ON sc.clearance_item_id = ci.id
        SET sc.status = 'approved', 
            sc.reviewed_by = ?, 
            sc.reviewed_at = NOW(), 
            sc.remarks = 'Approved by department head'
        WHERE ci.department_id = ? AND sc.student_id = ? AND sc.status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $department_id, $student_id]);
    
    // Send email notification
    $email_body = "
        <h3>✅ Clearance Approved</h3>
        <p>Dear <strong>{$student['full_name']}</strong>,</p>
        <p>Your clearance for the <strong>{$department_name}</strong> has been <span class='status-approved'>APPROVED</span>.</p>
        <p>You can now proceed to the next department or download your certificate if all departments are cleared.</p>
        <a href='https://graduationclearancesystem.gt.tc/student/clearance-status.php' class='button'>View Status</a>
    ";
    sendEmail($student['email'], "Clearance Approved - $department_name", $email_body);
    
    // Create notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
        VALUES (?, 'Clearance Approved', ?, 'success', ?, NOW())
    ");
    $stmt->execute([$student_id, "Your clearance for $department_name has been approved!", "student/clearance-status.php"]);
    
    $_SESSION['success'] = "Student cleared successfully!";
    
} elseif ($action == 'reject') {
    if (empty($reason)) {
        $_SESSION['error'] = "Please provide a reason for rejection.";
        redirect("view-student.php?id=$student_id");
        exit();
    }
    
    // Update all pending clearances for this student in this department to rejected
    $stmt = $pdo->prepare("
        UPDATE student_clearance sc
        JOIN clearance_items ci ON sc.clearance_item_id = ci.id
        SET sc.status = 'rejected', 
            sc.reviewed_by = ?, 
            sc.reviewed_at = NOW(), 
            sc.remarks = ?
        WHERE ci.department_id = ? AND sc.student_id = ? AND sc.status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $reason, $department_id, $student_id]);
    
    // Send email notification
    $email_body = "
        <h3>❌ Clearance Rejected</h3>
        <p>Dear <strong>{$student['full_name']}</strong>,</p>
        <p>Your clearance for the <strong>{$department_name}</strong> has been <span class='status-rejected'>REJECTED</span>.</p>
        <div style='background: #fef2f2; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #ef4444;'>
            <strong>Reason for Rejection:</strong><br>
            {$reason}
        </div>
        <p>Please address the issue and resubmit your clearance request.</p>
        <a href='https://graduationclearancesystem.gt.tc/student/clearance-status.php' class='button'>View Details</a>
    ";
    sendEmail($student['email'], "Clearance Rejected - $department_name", $email_body);
    
    // Create notification
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type, link, created_at) 
        VALUES (?, 'Clearance Rejected', ?, 'danger', ?, NOW())
    ");
    $stmt->execute([$student_id, "Your clearance for $department_name was rejected. Reason: " . substr($reason, 0, 100), "student/clearance-status.php"]);
    
    // Create chat conversation for follow-up
    $stmt = $pdo->prepare("
        SELECT id FROM conversations 
        WHERE student_id = ? AND department_id = ? 
        ORDER BY updated_at DESC LIMIT 1
    ");
    $stmt->execute([$student_id, $department_id]);
    $conversation = $stmt->fetch();
    
    if ($conversation) {
        sendMessage($pdo, $conversation['id'], $_SESSION['user_id'], $student_id, 
            "Your clearance for $department_name has been rejected. Reason: " . $reason);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO conversations (student_id, department_id, subject, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$student_id, $department_id, "Clearance Rejection - $department_name"]);
        $convo_id = $pdo->lastInsertId();
        sendMessage($pdo, $convo_id, $_SESSION['user_id'], $student_id, 
            "Your clearance for $department_name has been rejected. Reason: " . $reason);
    }
    
    $_SESSION['success'] = "Student rejected successfully!";
}

redirect('dashboard.php');
?>