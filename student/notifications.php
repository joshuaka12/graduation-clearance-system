<?php
// Chat System Functions - Simplified (No Notifications Table)

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
        // Update conversation last message time
        $stmt = $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$conversation_id]);
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

function closeConversation($pdo, $conversation_id) {
    $stmt = $pdo->prepare("UPDATE conversations SET status = 'closed' WHERE id = ?");
    return $stmt->execute([$conversation_id]);
}
?>