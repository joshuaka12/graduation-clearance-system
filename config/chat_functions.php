<?php
// config/chat_functions.php

require_once __DIR__ . '/../includes/send_email.php';
require_once __DIR__ . '/../includes/email_templates.php';

function startConversation($pdo, $student_id, $department_id, $subject, $clearance_item_id = null) {
    $stmt = $pdo->prepare("
        INSERT INTO conversations (student_id, department_id, clearance_item_id, subject, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    return $stmt->execute([$student_id, $department_id, $clearance_item_id, $subject]);
}

function sendMessage($pdo, $conversation_id, $sender_id, $receiver_id, $message) {
    // Insert message
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, receiver_id, message, created_at) 
        VALUES (?, ?, ?, ?, NOW())
    ");
    $result = $stmt->execute([$conversation_id, $sender_id, $receiver_id, $message]);
    
    if ($result) {
        // Update conversation
        $stmt = $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);
        
        // Get sender and receiver info
        $stmt = $pdo->prepare("SELECT full_name, role, email FROM users WHERE id = ?");
        $stmt->execute([$sender_id]);
        $sender = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT full_name, role, email FROM users WHERE id = ?");
        $stmt->execute([$receiver_id]);
        $receiver = $stmt->fetch();
        
        // Get conversation details
        $stmt = $pdo->prepare("
            SELECT c.*, d.department_name 
            FROM conversations c 
            JOIN departments d ON c.department_id = d.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$conversation_id]);
        $conversation = $stmt->fetch();
        
        // Determine notification link
        $role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
        if ($role == 'student') {
            $notification_link = "student/message.php?id=" . $conversation_id;
        } elseif ($role == 'department_head') {
            $notification_link = "department-head/message.php?id=" . $conversation_id;
        } else {
            $notification_link = "message.php?id=" . $conversation_id;
        }
        
        // Create in-system notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, created_at) 
            VALUES (?, ?, ?, 'info', ?, NOW())
        ");
        $notification_title = "New Message";
        $notification_message = "You have a new message regarding: " . $conversation['department_name'];
        $stmt->execute([$receiver_id, $notification_title, $notification_message, $notification_link]);
        
        // Send email notification - Changed from queueEmail to sendEmail
        $message_preview = substr($message, 0, 100) . (strlen($message) > 100 ? '...' : '');
        
        if ($receiver['role'] == 'student') {
            $email_body = emailNewMessage($receiver['full_name'], $conversation['department_name'], $message_preview);
            sendEmail($receiver['email'], 'New Message from ' . $conversation['department_name'], $email_body);
        } elseif ($receiver['role'] == 'department_head') {
            $stmt = $pdo->prepare("SELECT student_id FROM users WHERE id = ?");
            $stmt->execute([$sender_id]);
            $student_info = $stmt->fetch();
            
            $email_body = emailNewStudentMessage($receiver['full_name'], $sender['full_name'], $student_info['student_id'] ?? 'N/A', $message_preview);
            sendEmail($receiver['email'], 'New Message from Student - ' . $conversation['department_name'], $email_body);
        }
    }
    
    return $result;
}

function getConversationsForStudent($pdo, $student_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, d.department_name, d.department_code,
               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count,
               (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM conversations c
        JOIN departments d ON c.department_id = d.id
        WHERE c.student_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$student_id, $student_id]);
    return $stmt->fetchAll();
}

function getConversationsForDepartmentHead($pdo, $department_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name, u.student_id, u.email,
               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.receiver_id = ? AND m.is_read = 0) as unread_count,
               (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM conversations c
        JOIN users u ON c.student_id = u.id
        WHERE c.department_id = ?
        ORDER BY c.updated_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $department_id]);
    return $stmt->fetchAll();
}

function getMessages($pdo, $conversation_id, $user_id) {
    // Mark messages as read
    $stmt = $pdo->prepare("
        UPDATE messages SET is_read = 1, read_at = NOW() 
        WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0
    ");
    $stmt->execute([$conversation_id, $user_id]);
    
    // Get all messages
    $stmt = $pdo->prepare("
        SELECT m.*, u_sender.full_name as sender_name, u_sender.role as sender_role,
               u_receiver.full_name as receiver_name
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.id
        JOIN users u_receiver ON m.receiver_id = u_receiver.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversation_id]);
    return $stmt->fetchAll();
}

function getUnreadMessageCount($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM messages 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}

function getConversationById($pdo, $conversation_id) {
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ?");
    $stmt->execute([$conversation_id]);
    return $stmt->fetch();
}

function getUnreadNotifications($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? AND is_read = 0 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function markNotificationAsRead($pdo, $notification_id, $user_id) {
    $stmt = $pdo->prepare("
        UPDATE notifications SET is_read = 1, read_at = NOW() 
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$notification_id, $user_id]);
}

function markAllNotificationsAsRead($pdo, $user_id) {
    $stmt = $pdo->prepare("
        UPDATE notifications SET is_read = 1, read_at = NOW() 
        WHERE user_id = ? AND is_read = 0
    ");
    return $stmt->execute([$user_id]);
}

// Email notification functions for clearance processing - Changed from queueEmail to sendEmail
function sendClearanceApprovalEmail($pdo, $student_id, $department_id, $item_id, $remarks = null) {
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $dept = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT item_name FROM clearance_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    $email_body = emailClearanceApproved($student['full_name'], $dept['department_name'], $item['item_name'], $remarks);
    return sendEmail($student['email'], 'Clearance Approved - ' . $dept['department_name'], $email_body);
}

function sendClearanceRejectionEmail($pdo, $student_id, $department_id, $item_id, $reason) {
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $dept = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT item_name FROM clearance_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    $email_body = emailClearanceRejected($student['full_name'], $dept['department_name'], $item['item_name'], $reason);
    return sendEmail($student['email'], 'Clearance Rejected - ' . $dept['department_name'], $email_body);
}

function sendFinalClearanceEmail($pdo, $student_id) {
    $stmt = $pdo->prepare("SELECT full_name, email, student_id FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $certificate_number = 'GCS-' . date('Y') . '-' . str_pad($student_id, 5, '0', STR_PAD_LEFT);
    
    $email_body = emailFinalClearanceCompleted($student['full_name'], $student['student_id'], $certificate_number);
    return sendEmail($student['email'], '🎉 Congratulations! You Are Fully Cleared!', $email_body);
}

// Clearance reminder email function
function sendClearanceReminderEmail($pdo, $student_id, $deadline_date, $remaining_items) {
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    
    $email_body = emailReminder($student['full_name'], $deadline_date, $remaining_items);
    return sendEmail($student['email'], 'Clearance Deadline Reminder', $email_body);
}
?>