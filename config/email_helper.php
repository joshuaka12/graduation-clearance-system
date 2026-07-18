<?php
// includes/email_helper.php

function sendClearanceApprovalEmail($student_id, $department_id, $item_id, $remarks = null) {
    global $pdo;
    
    // Get student details
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    // Get item and department details
    $stmt = $pdo->prepare("
        SELECT ci.item_name, d.department_name 
        FROM clearance_items ci 
        JOIN departments d ON ci.department_id = d.id 
        WHERE ci.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    require_once 'email_templates.php';
    $email_body = emailClearanceApproved($student['full_name'], $item['department_name'], $item['item_name'], $remarks);
    
    return sendEmail($student['email'], 'Clearance Approved - ' . $item['department_name'], $email_body);
}

function sendClearanceRejectionEmail($student_id, $department_id, $item_id, $reason) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT ci.item_name, d.department_name 
        FROM clearance_items ci 
        JOIN departments d ON ci.department_id = d.id 
        WHERE ci.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    require_once 'email_templates.php';
    $email_body = emailClearanceRejected($student['full_name'], $item['department_name'], $item['item_name'], $reason);
    
    return sendEmail($student['email'], 'Clearance Rejected - ' . $item['department_name'], $email_body);
}

function sendNewClearanceRequestEmail($dept_head_id, $student_id, $item_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$dept_head_id]);
    $dept_head = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT full_name, student_id FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT ci.item_name, d.department_name, d.id as dept_id 
        FROM clearance_items ci 
        JOIN departments d ON ci.department_id = d.id 
        WHERE ci.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    require_once 'email_templates.php';
    $email_body = emailNewClearanceRequest($dept_head['full_name'], $student['full_name'], $student['student_id'], $item['department_name'], $item['item_name']);
    
    return sendEmail($dept_head['email'], 'New Clearance Request - ' . $item['department_name'], $email_body);
}
?>