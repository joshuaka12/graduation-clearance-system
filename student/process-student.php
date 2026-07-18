<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reason = isset($_GET['reason']) ? sanitizeInput($_GET['reason']) : '';
$department_id = $_SESSION['selected_department_id'] ?? 0;

if ($student_id == 0 || empty($action)) {
    redirect('dashboard.php');
}

if ($action == 'approve') {
    // Approve all pending clearances for this student in this department
    $stmt = $pdo->prepare("
        UPDATE student_clearance sc
        JOIN clearance_items ci ON sc.clearance_item_id = ci.id
        SET sc.status = 'approved', sc.reviewed_by = ?, sc.reviewed_at = NOW(), sc.remarks = 'Approved by department head'
        WHERE sc.student_id = ? AND ci.department_id = ? AND sc.status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $student_id, $department_id]);
    
    logActivity($pdo, $_SESSION['user_id'], 'Approve Student', "Approved all clearances for student ID: $student_id");
    $_SESSION['success'] = "Student clearances approved successfully!";
    
} elseif ($action == 'reject') {
    if (empty($reason)) {
        $_SESSION['error'] = "Please provide a reason for rejection.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE student_clearance sc
            JOIN clearance_items ci ON sc.clearance_item_id = ci.id
            SET sc.status = 'rejected', sc.reviewed_by = ?, sc.reviewed_at = NOW(), sc.remarks = ?
            WHERE sc.student_id = ? AND ci.department_id = ? AND sc.status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id'], $reason, $student_id, $department_id]);
        
        logActivity($pdo, $_SESSION['user_id'], 'Reject Student', "Rejected clearances for student ID: $student_id. Reason: $reason");
        $_SESSION['success'] = "Student clearances rejected.";
    }
}

redirect('dashboard.php');
?>