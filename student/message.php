<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect('../auth/login.php');
}

$student_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Get user profile picture for navbar
$stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();
$profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
$profile_pic_exists = !empty($profile_pic) && $profile_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic);

// Function to get department head ID
function getDepartmentHeadId($pdo, $department_id) {
    $stmt = $pdo->prepare("
        SELECT u.id 
        FROM users u
        JOIN assigned_departments ad ON u.id = ad.user_id
        WHERE ad.department_id = ? AND u.role = 'department_head' AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$department_id]);
    $result = $stmt->fetch();
    
    if ($result) return $result['id'];
    
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE department_id = ? AND role = 'department_head' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$department_id]);
    $result = $stmt->fetch();
    
    if ($result) return $result['id'];
    
    $stmt = $pdo->prepare("
        SELECT id FROM users 
        WHERE role = 'department_head' AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result ? $result['id'] : null;
}

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = sanitizeInput($_POST['message']);
    $conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : $conversation_id;
    
    if ($conversation_id > 0 && !empty($message)) {
        $conversation = getConversationById($pdo, $conversation_id);
        
        if ($conversation && $conversation['student_id'] == $student_id) {
            $dept_head_id = getDepartmentHeadId($pdo, $conversation['department_id']);
            
            if ($dept_head_id) {
                if (sendMessage($pdo, $conversation_id, $student_id, $dept_head_id, $message)) {
                    header("Location: message.php?id=" . $conversation_id);
                    exit();
                } else {
                    $error = "Failed to send message.";
                }
            } else {
                $error = "No department head available.";
            }
        } else {
            $error = "Conversation not found.";
        }
    } else {
        $error = "Message cannot be empty.";
    }
}

// Start new conversation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_chat'])) {
    $department_id = (int)$_POST['department_id'];
    $subject = sanitizeInput($_POST['subject']);
    
    $dept_head_id = getDepartmentHeadId($pdo, $department_id);
    
    if (!$dept_head_id) {
        $error = "No department head available for this department.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE student_id = ? AND department_id = ?");
        $stmt->execute([$student_id, $department_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            header("Location: message.php?id=" . $existing['id']);
            exit();
        } else {
            $stmt = $pdo->prepare("INSERT INTO conversations (student_id, department_id, subject, created_at) VALUES (?, ?, ?, NOW())");
            
            if ($stmt->execute([$student_id, $department_id, $subject])) {
                $convo_id = $pdo->lastInsertId();
                $initial_message = "Hello, I need assistance with: " . $subject;
                sendMessage($pdo, $convo_id, $student_id, $dept_head_id, $initial_message);
                header("Location: message.php?id=" . $convo_id);
                exit();
            } else {
                $error = "Failed to start conversation.";
            }
        }
    }
}

// Get conversations
$conversations = getConversationsForStudent($pdo, $student_id);
$current_conversation = null;
$messages = [];

if ($conversation_id > 0) {
    $current_conversation = getConversationById($pdo, $conversation_id);
    if ($current_conversation && $current_conversation['student_id'] == $student_id) {
        $messages = getMessages($pdo, $conversation_id, $student_id);
        // Mark messages as read
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0");
        $stmt->execute([$conversation_id, $student_id]);
    }
}

// Get departments for new chat
$stmt = $pdo->prepare("SELECT DISTINCT d.id, d.department_name, d.department_code FROM departments d WHERE d.is_active = 1 ORDER BY d.department_name");
$stmt->execute();
$departments = $stmt->fetchAll();

$unread_count = getUnreadMessageCount($pdo, $student_id);

$page_title = 'Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?> - Student Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================================
           CSS VARIABLES - KEEP SYSTEM COLORS
           ============================================================ */
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --primary-medium: rgba(128, 0, 32, 0.12);
            --primary-hover: #6e001b;
            --gray-50: #fafbfc;
            --gray-100: #f4f6f9;
            --gray-200: #e8ecef;
            --gray-300: #dce1e8;
            --gray-400: #b0b8c4;
            --gray-500: #8a94a6;
            --gray-600: #6c7683;
            --gray-700: #4a5360;
            --gray-800: #2d3440;
            --gray-900: #1a1e24;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 20px rgba(0,0,0,0.06);
            --shadow-lg: 0 8px 40px rgba(0,0,0,0.08);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
            height: 100vh;
            color: var(--gray-800);
            font-size: 15px;
            line-height: 1.6;
        }
        
        /* ============================================================
           NAVBAR - KEEP EXISTING STYLES
           ============================================================ */
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 10px 0;
            box-shadow: 0 2px 20px rgba(128, 0, 32, 0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.2rem;
            color: white !important;
            letter-spacing: -0.3px;
        }
        .navbar-brand i { margin-right: 10px; }
        
        .navbar-toggler {
            border: 2px solid rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 10px;
            border-radius: 8px;
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            position: relative;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white !important;
        }
        
        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white !important;
        }
        
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .user-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: rgba(255,255,255,0.2);
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .dropdown-menu {
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            border: none;
            margin-top: 8px;
            padding: 6px 0;
        }
        
        .dropdown-item {
            padding: 8px 20px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .dropdown-item:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }
        
        .dropdown-item.text-danger:hover {
            background: rgba(239,68,68,0.08);
            color: var(--danger);
        }
        
        /* ============================================================
           MAIN CONTENT
           ============================================================ */
        .main-content {
            padding: 20px;
            height: calc(100vh - 62px);
        }
        
        .chat-wrapper {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            display: flex;
            height: 100%;
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }
        
        /* ============================================================
           LEFT PANEL - CONVERSATIONS
           ============================================================ */
        .conversations-panel {
            width: 360px;
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            background: white;
            flex-shrink: 0;
        }
        
        .panel-header {
            padding: 18px 20px 14px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .panel-header h5 {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0;
            color: var(--gray-800);
        }
        
        .panel-header h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .btn-new-chat {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-new-chat:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            color: white;
        }
        
        /* Search Box */
        .search-box {
            padding: 10px 16px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 14px 8px 36px;
            border: 1.5px solid var(--gray-200);
            border-radius: 30px;
            font-size: 0.8rem;
            outline: none;
            transition: var(--transition);
            background: var(--gray-50);
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236c7683' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='M21 21l-4.35-4.35'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 12px center;
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
            background-color: white;
        }
        
        .search-box input::placeholder {
            color: var(--gray-400);
        }
        
        /* Conversations List */
        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 4px 0;
        }
        
        .conversations-list::-webkit-scrollbar {
            width: 4px;
        }
        .conversations-list::-webkit-scrollbar-track {
            background: transparent;
        }
        .conversations-list::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 10px;
        }
        
        .conversation-item {
            padding: 12px 16px;
            margin: 2px 8px;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: block;
            position: relative;
        }
        
        .conversation-item:hover {
            background: var(--gray-100);
            text-decoration: none;
        }
        
        .conversation-item.active {
            background: var(--primary-soft);
            box-shadow: inset 3px 0 0 var(--primary);
        }
        
        .conversation-item .avatar-wrapper {
            width: 44px;
            height: 44px;
            border-radius: var(--radius-md);
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.1rem;
            flex-shrink: 0;
            position: relative;
        }
        
        .conversation-item .avatar-wrapper .status-dot {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid white;
            background: var(--gray-300);
        }
        
        .conversation-item .avatar-wrapper .status-dot.online {
            background: var(--success);
        }
        
        .conversation-item .conv-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-item .conv-name {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-800);
            margin-bottom: 2px;
        }
        
        .conversation-item .conv-meta {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .conversation-item .conv-preview {
            font-size: 0.72rem;
            color: var(--gray-500);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            flex: 1;
        }
        
        .conversation-item .conv-time {
            font-size: 0.6rem;
            color: var(--gray-400);
            flex-shrink: 0;
        }
        
        .conversation-item .unread-badge {
            background: var(--primary);
            color: white;
            border-radius: 30px;
            padding: 1px 8px;
            font-size: 0.6rem;
            font-weight: 700;
            min-width: 18px;
            text-align: center;
            flex-shrink: 0;
        }
        
        .conv-reg-number {
            font-size: 0.6rem;
            color: var(--gray-500);
            font-weight: 500;
        }
        
        .empty-conversations {
            padding: 40px 20px;
            text-align: center;
        }
        
        .empty-conversations i {
            font-size: 2.5rem;
            color: var(--gray-200);
            margin-bottom: 12px;
        }
        
        .empty-conversations h6 {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .empty-conversations p {
            font-size: 0.8rem;
            color: var(--gray-500);
            margin-bottom: 16px;
        }
        
        /* ============================================================
           RIGHT PANEL - CHAT WINDOW
           ============================================================ */
        .chat-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--gray-50);
            min-width: 0;
        }
        
        /* Chat Header */
        .chat-header {
            padding: 14px 24px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
            flex-shrink: 0;
        }
        
        .chat-header .header-info {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        
        .chat-header .header-avatar {
            width: 42px;
            height: 42px;
            border-radius: var(--radius-md);
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .chat-header .header-details {
            min-width: 0;
            flex: 1;
        }
        
        .chat-header .header-details h6 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .chat-header .header-details .sub-details {
            font-size: 0.7rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 1px;
        }
        
        .chat-header .header-details .sub-details .status-badge {
            padding: 1px 10px;
            border-radius: 30px;
            font-size: 0.6rem;
            font-weight: 600;
        }
        
        .status-badge.pending {
            background: rgba(245,158,11,0.12);
            color: var(--warning);
        }
        
        .status-badge.approved {
            background: rgba(16,185,129,0.12);
            color: var(--success);
        }
        
        .status-badge.rejected {
            background: rgba(239,68,68,0.12);
            color: var(--danger);
        }
        
        .chat-header .header-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .btn-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            border: none;
            background: transparent;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            font-size: 0.85rem;
        }
        
        .btn-icon:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        /* Mobile Back Button */
        .btn-back-mobile {
            display: none;
            background: transparent;
            border: none;
            color: var(--gray-700);
            font-size: 1.1rem;
            padding: 4px 8px;
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }
        
        .btn-back-mobile:hover {
            background: var(--gray-100);
        }
        
        /* ============================================================
           MESSAGES AREA
           ============================================================ */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .messages-area::-webkit-scrollbar {
            width: 4px;
        }
        .messages-area::-webkit-scrollbar-track {
            background: transparent;
        }
        .messages-area::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 10px;
        }
        
        /* Date Separator */
        .date-separator {
            text-align: center;
            padding: 12px 0 8px;
            font-size: 0.7rem;
            color: var(--gray-500);
            font-weight: 500;
            position: relative;
        }
        
        .date-separator::before {
            content: '';
            position: absolute;
            left: 20%;
            right: 20%;
            top: 50%;
            height: 1px;
            background: var(--gray-200);
        }
        
        .date-separator span {
            background: var(--gray-50);
            padding: 0 12px;
            position: relative;
            z-index: 1;
        }
        
        /* Message Bubbles */
        .message {
            display: flex;
            max-width: 75%;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .message.sent {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .message.received {
            align-self: flex-start;
        }
        
        .message .bubble {
            padding: 10px 16px;
            border-radius: 18px;
            font-size: 0.85rem;
            line-height: 1.5;
            word-wrap: break-word;
            max-width: 100%;
            position: relative;
        }
        
        .message.sent .bubble {
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .bubble {
            background: white;
            color: var(--gray-800);
            border: 1px solid var(--gray-200);
            border-bottom-left-radius: 4px;
            box-shadow: var(--shadow-sm);
        }
        
        .message .bubble .msg-time {
            font-size: 0.6rem;
            opacity: 0.7;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            margin-left: 8px;
        }
        
        .message.sent .bubble .msg-time {
            justify-content: flex-end;
            opacity: 0.8;
        }
        
        .message .bubble .msg-time .read-status {
            font-size: 0.55rem;
        }
        
        .message .msg-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: var(--primary-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            color: var(--primary);
            flex-shrink: 0;
            margin: 0 8px;
            align-self: flex-end;
        }
        
        .message .msg-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Empty Chat State */
        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 40px;
            color: var(--gray-500);
        }
        
        .empty-chat i {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 16px;
        }
        
        .empty-chat h5 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
        }
        
        .empty-chat p {
            font-size: 0.85rem;
            margin-bottom: 16px;
            max-width: 320px;
        }
        
        /* ============================================================
           MESSAGE INPUT AREA
           ============================================================ */
        .message-input-area {
            padding: 14px 24px 18px;
            border-top: 1px solid var(--gray-200);
            background: white;
            flex-shrink: 0;
        }
        
        .message-input-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--gray-50);
            border: 1.5px solid var(--gray-200);
            border-radius: 30px;
            padding: 4px;
            transition: var(--transition);
        }
        
        .message-input-wrapper:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
            background: white;
        }
        
        .message-input-wrapper input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 10px 16px;
            font-size: 0.85rem;
            outline: none;
            color: var(--gray-800);
            min-width: 0;
        }
        
        .message-input-wrapper input::placeholder {
            color: var(--gray-400);
        }
        
        .message-input-wrapper .input-actions {
            display: flex;
            align-items: center;
            gap: 4px;
            padding-right: 4px;
        }
        
        .message-input-wrapper .input-actions .btn-attach {
            background: transparent;
            border: none;
            color: var(--gray-400);
            padding: 6px 8px;
            border-radius: 50%;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .message-input-wrapper .input-actions .btn-attach:hover {
            background: var(--gray-200);
            color: var(--gray-600);
        }
        
        .message-input-wrapper .btn-send {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .message-input-wrapper .btn-send:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(128, 0, 32, 0.25);
        }
        
        .message-input-wrapper .btn-send:active {
            transform: scale(0.97);
        }
        
        /* ============================================================
           MODAL
           ============================================================ */
        .modal-content {
            border-radius: var(--radius-lg);
            border: none;
            box-shadow: var(--shadow-lg);
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            padding: 16px 20px;
            border: none;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .modal-header h5 {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-body .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 4px;
        }
        
        .modal-body .form-select,
        .modal-body .form-control {
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--gray-200);
            padding: 10px 14px;
            font-size: 0.85rem;
            transition: var(--transition);
        }
        
        .modal-body .form-select:focus,
        .modal-body .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        
        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 16px 20px;
        }
        
        .modal-footer .btn-secondary {
            background: var(--gray-200);
            border: none;
            color: var(--gray-700);
            padding: 8px 20px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .modal-footer .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        .modal-footer .btn-primary {
            background: var(--primary);
            border: none;
            color: white;
            padding: 8px 24px;
            border-radius: var(--radius-sm);
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .modal-footer .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .alert-info-custom {
            background: var(--primary-soft);
            border: none;
            color: var(--primary);
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            padding: 10px 14px;
        }
        
        /* ============================================================
           ALERT MESSAGES
           ============================================================ */
        .alert-custom {
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            padding: 10px 16px;
            margin: 8px 24px 0;
        }
        
        .alert-custom.alert-danger {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.15);
            color: var(--danger);
        }
        
        /* ============================================================
           RESPONSIVE DESIGN
           ============================================================ */
        @media (max-width: 992px) {
            .conversations-panel {
                width: 300px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 10px;
                height: calc(100vh - 58px);
            }
            
            .chat-wrapper {
                border-radius: var(--radius-md);
            }
            
            /* Mobile: Show only conversations list initially */
            .conversations-panel {
                width: 100%;
                border-right: none;
            }
            
            .conversations-panel.hidden-mobile {
                display: none;
            }
            
            /* Chat panel takes full width on mobile */
            .chat-panel {
                width: 100%;
            }
            
            .chat-panel.hidden-mobile {
                display: none;
            }
            
            .btn-back-mobile {
                display: flex;
            }
            
            .chat-header {
                padding: 10px 16px;
            }
            
            .chat-header .header-avatar {
                width: 34px;
                height: 34px;
                font-size: 0.8rem;
            }
            
            .chat-header .header-details h6 {
                font-size: 0.85rem;
            }
            
            .chat-header .header-details .sub-details {
                font-size: 0.6rem;
                gap: 6px;
            }
            
            .messages-area {
                padding: 14px 16px;
            }
            
            .message {
                max-width: 85%;
            }
            
            .message .bubble {
                font-size: 0.82rem;
                padding: 8px 14px;
            }
            
            .message-input-area {
                padding: 10px 16px 14px;
            }
            
            .message-input-wrapper input {
                font-size: 0.82rem;
                padding: 8px 12px;
            }
            
            .message-input-wrapper .btn-send {
                padding: 6px 14px;
                font-size: 0.75rem;
            }
            
            .message-input-wrapper .btn-send span {
                display: none;
            }
            
            .conversation-item {
                padding: 10px 14px;
                margin: 2px 6px;
            }
            
            .conversation-item .avatar-wrapper {
                width: 38px;
                height: 38px;
                font-size: 0.9rem;
            }
            
            .conversation-item .conv-name {
                font-size: 0.82rem;
            }
            
            .conversation-item .conv-preview {
                font-size: 0.68rem;
            }
            
            .panel-header {
                padding: 14px 16px 10px;
            }
            
            .panel-header h5 {
                font-size: 0.88rem;
            }
            
            .search-box {
                padding: 8px 12px;
            }
            
            .search-box input {
                font-size: 0.78rem;
                padding: 6px 12px 6px 32px;
                background-size: 12px;
                background-position: 10px center;
            }
            
            .date-separator {
                font-size: 0.65rem;
                padding: 8px 0 6px;
            }
            
            .date-separator::before {
                left: 10%;
                right: 10%;
            }
            
            .empty-chat i {
                font-size: 2.2rem;
            }
            
            .empty-chat h5 {
                font-size: 0.9rem;
            }
            
            .empty-chat p {
                font-size: 0.8rem;
            }
            
            .modal-body {
                padding: 18px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 6px;
                height: calc(100vh - 54px);
            }
            
            .chat-wrapper {
                border-radius: var(--radius-sm);
            }
            
            .chat-header {
                padding: 8px 12px;
            }
            
            .chat-header .header-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.7rem;
            }
            
            .chat-header .header-details h6 {
                font-size: 0.8rem;
            }
            
            .chat-header .header-details .sub-details {
                font-size: 0.55rem;
                gap: 4px;
            }
            
            .messages-area {
                padding: 10px 12px;
            }
            
            .message {
                max-width: 90%;
            }
            
            .message .bubble {
                font-size: 0.8rem;
                padding: 8px 12px;
                border-radius: 14px;
            }
            
            .message.sent .bubble {
                border-bottom-right-radius: 3px;
            }
            
            .message.received .bubble {
                border-bottom-left-radius: 3px;
            }
            
            .message .msg-avatar {
                width: 22px;
                height: 22px;
                font-size: 0.55rem;
                margin: 0 4px;
            }
            
            .message-input-area {
                padding: 8px 12px 12px;
            }
            
            .message-input-wrapper {
                border-radius: 24px;
                padding: 2px;
            }
            
            .message-input-wrapper input {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            
            .message-input-wrapper .btn-send {
                padding: 5px 12px;
                font-size: 0.7rem;
                gap: 3px;
            }
            
            .message-input-wrapper .btn-send i {
                font-size: 0.7rem;
            }
            
            .conversation-item {
                padding: 8px 12px;
                margin: 1px 4px;
            }
            
            .conversation-item .avatar-wrapper {
                width: 34px;
                height: 34px;
                font-size: 0.8rem;
            }
            
            .conversation-item .conv-name {
                font-size: 0.78rem;
            }
            
            .conversation-item .conv-preview {
                font-size: 0.65rem;
            }
            
            .conversation-item .conv-time {
                font-size: 0.55rem;
            }
            
            .conversation-item .unread-badge {
                font-size: 0.55rem;
                padding: 1px 6px;
                min-width: 16px;
            }
            
            .panel-header {
                padding: 10px 12px 8px;
            }
            
            .panel-header h5 {
                font-size: 0.82rem;
            }
            
            .btn-new-chat {
                padding: 4px 10px;
                font-size: 0.68rem;
            }
            
            .btn-new-chat span {
                display: none;
            }
            
            .search-box {
                padding: 6px 10px;
            }
            
            .search-box input {
                font-size: 0.75rem;
                padding: 5px 10px 5px 28px;
                background-size: 10px;
                background-position: 8px center;
            }
        }
        
        /* ============================================================
           UTILITY CLASSES
           ============================================================ */
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .gap-8 { gap: 8px; }
        .gap-12 { gap: 12px; }
        
        .fw-500 { font-weight: 500; }
        .fw-600 { font-weight: 600; }
        .fw-700 { font-weight: 700; }
        
        .text-muted-light { color: var(--gray-400); }
    </style>
</head>
<body>
    <!-- ============================================================
    NAVBAR - KEPT EXACTLY AS ORIGINAL
    ============================================================ -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap"></i> Clearance System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="clearance-status.php">
                            <i class="fas fa-list-check me-1"></i> Clearance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="help-support.php">
                            <i class="fas fa-headset me-1"></i> Help & Support
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="message.php">
                            <i class="fas fa-comments me-1"></i> Messages
                            <?php if ($unread_count > 0): ?>
                                <span class="notification-badge"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button">
                            <div class="user-avatar d-inline-flex align-items-center justify-content-center me-1">
                                <?php if ($profile_pic_exists): ?>
                                    <img src="../assets/uploads/Students-profile/<?php echo $profile_pic; ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="change-password.php"><i class="fas fa-key me-2"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- ============================================================
    MAIN CONTENT
    ============================================================ -->
    <div class="main-content">
        <div class="chat-wrapper">
            <!-- ============================================================
            LEFT PANEL - CONVERSATIONS
            ============================================================ -->
            <div class="conversations-panel" id="conversationsPanel">
                <div class="panel-header">
                    <h5><i class="fas fa-comments"></i> Conversations</h5>
                    <button class="btn-new-chat" data-bs-toggle="modal" data-bs-target="#newChatModal">
                        <i class="fas fa-plus"></i> <span>New</span>
                    </button>
                </div>
                
                <div class="search-box">
                    <input type="text" id="searchConversations" placeholder="Search departments...">
                </div>
                
                <div class="conversations-list" id="conversationsList">
                    <?php if (empty($conversations)): ?>
                        <div class="empty-conversations">
                            <i class="fas fa-inbox"></i>
                            <h6>No conversations yet</h6>
                            <p>Start a new conversation with a department</p>
                            <button class="btn-new-chat" data-bs-toggle="modal" data-bs-target="#newChatModal">
                                <i class="fas fa-plus"></i> New Conversation
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): 
                            // Get department name
                            $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
                            $stmt->execute([$conv['department_id']]);
                            $dept_name = $stmt->fetchColumn() ?: 'Department';
                            
                            // Get student info for avatar
                            $stmt = $pdo->prepare("SELECT full_name, student_id, profile_pic FROM users WHERE id = ?");
                            $stmt->execute([$conv['student_id']]);
                            $student_info = $stmt->fetch();
                            
                            $has_unread = $conv['unread_count'] > 0;
                        ?>
                        <a href="message.php?id=<?php echo $conv['id']; ?>" class="conversation-item <?php echo ($conversation_id == $conv['id']) ? 'active' : ''; ?>" data-search="<?php echo strtolower($dept_name); ?>">
                            <div class="d-flex gap-12 align-items-center">
                                <div class="avatar-wrapper">
                                    <i class="fas fa-building"></i>
                                    <span class="status-dot <?php echo $has_unread ? 'online' : ''; ?>"></span>
                                </div>
                                <div class="conv-info">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="conv-name"><?php echo htmlspecialchars($dept_name); ?></div>
                                        <?php if ($has_unread): ?>
                                            <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conv-meta">
                                        <span class="conv-preview">
                                            <?php 
                                                $last_msg = $conv['last_message'] ?? 'No messages';
                                                echo htmlspecialchars(strlen($last_msg) > 45 ? substr($last_msg, 0, 45) . '...' : $last_msg);
                                            ?>
                                        </span>
                                        <span class="conv-time">
                                            <?php echo $conv['last_message_time'] ? date('h:i A', strtotime($conv['last_message_time'])) : ''; ?>
                                        </span>
                                    </div>
                                    <?php if ($student_info): ?>
                                        <div class="conv-reg-number">
                                            <?php echo htmlspecialchars($student_info['student_id'] ?? ''); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- ============================================================
            RIGHT PANEL - CHAT WINDOW
            ============================================================ -->
            <div class="chat-panel <?php echo $conversation_id > 0 ? '' : 'hidden-mobile'; ?>" id="chatPanel">
                <?php if ($current_conversation): 
                    // Get department name
                    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
                    $stmt->execute([$current_conversation['department_id']]);
                    $dept_name = $stmt->fetchColumn() ?: 'Department';
                    
                    // Get student clearance status for this department
                    $stmt = $pdo->prepare("
                        SELECT sc.status 
                        FROM student_clearance sc
                        JOIN clearance_items ci ON sc.clearance_item_id = ci.id
                        WHERE sc.student_id = ? AND ci.department_id = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$student_id, $current_conversation['department_id']]);
                    $clearance_status = $stmt->fetchColumn() ?: 'pending';
                    
                    $status_class = $clearance_status;
                    $status_label = ucfirst($clearance_status);
                ?>
                    <!-- Chat Header -->
                    <div class="chat-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="header-info">
                                <button class="btn-back-mobile" id="backToConversations" aria-label="Back to conversations">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <div class="header-avatar">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="header-details">
                                    <h6><?php echo htmlspecialchars($dept_name); ?></h6>
                                    <div class="sub-details">
                                        <span>
                                            <i class="far fa-comment-dots me-1"></i> 
                                            <?php echo htmlspecialchars($current_conversation['subject'] ?? 'General Inquiry'); ?>
                                        </span>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="header-actions">
                                <button class="btn-icon" onclick="location.reload();" title="Refresh messages">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <div class="alert-custom alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Messages Area -->
                    <div class="messages-area" id="messagesArea">
                        <?php if (empty($messages)): ?>
                            <div class="empty-chat">
                                <i class="fas fa-comment-dots"></i>
                                <h5>No messages yet</h5>
                                <p>Start the conversation by sending a message</p>
                            </div>
                        <?php else: ?>
                            <?php 
                            $last_date = '';
                            foreach ($messages as $msg): 
                                $msg_date = date('Y-m-d', strtotime($msg['created_at']));
                                $display_date = '';
                                
                                if ($msg_date != $last_date) {
                                    $last_date = $msg_date;
                                    if (date('Y-m-d') == $msg_date) {
                                        $display_date = 'Today';
                                    } elseif (date('Y-m-d', strtotime('-1 day')) == $msg_date) {
                                        $display_date = 'Yesterday';
                                    } else {
                                        $display_date = date('F d, Y', strtotime($msg['created_at']));
                                    }
                                }
                            ?>
                                <?php if ($display_date): ?>
                                    <div class="date-separator">
                                        <span><?php echo $display_date; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="message <?php echo $msg['sender_id'] == $student_id ? 'sent' : 'received'; ?>">
                                    <div class="bubble">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        <span class="msg-time">
                                            <?php echo date('h:i A', strtotime($msg['created_at'])); ?>
                                            <?php if ($msg['sender_id'] == $student_id && $msg['is_read']): ?>
                                                <span class="read-status"><i class="fas fa-check-double"></i></span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div id="scrollBottom"></div>
                    </div>
                    
                    <!-- Message Input Area -->
                    <div class="message-input-area">
                        <form method="POST" class="message-input-wrapper">
                            <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                            <input type="text" name="message" id="messageInput" placeholder="Type your message..." required autocomplete="off">
                            <div class="input-actions">
                                <button type="button" class="btn-attach" title="Attach file (coming soon)">
                                    <i class="fas fa-paperclip"></i>
                                </button>
                                <button type="submit" name="send_message" class="btn-send">
                                    <i class="fas fa-paper-plane"></i> <span>Send</span>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                <?php else: ?>
                    <!-- No Conversation Selected -->
                    <div class="empty-chat">
                        <i class="fas fa-comments"></i>
                        <h5>No conversation selected</h5>
                        <p>Select a conversation from the sidebar or start a new one</p>
                        <button class="btn-new-chat" data-bs-toggle="modal" data-bs-target="#newChatModal">
                            <i class="fas fa-plus"></i> Start New Conversation
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
    NEW CHAT MODAL - KEPT FUNCTIONALITY UNCHANGED
    ============================================================ -->
    <div class="modal fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5><i class="fas fa-plus-circle me-2"></i> New Conversation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Department</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Choose a department...</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo $dept['department_code']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Select the department you need assistance from.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" required placeholder="e.g., Clearance Status, Document Submission, Fee Inquiry">
                        </div>
                        <div class="alert-info-custom">
                            <i class="fas fa-info-circle me-1"></i> Your message will be sent to the Department Head for review.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="start_chat" class="btn-primary">Start Conversation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
    SCRIPTS
    ============================================================ -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ============================================================
        // SCROLL TO BOTTOM OF MESSAGES
        // ============================================================
        function scrollToBottom() {
            const messagesArea = document.getElementById('messagesArea');
            if (messagesArea) {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        }
        scrollToBottom();
        
        // ============================================================
        // FOCUS ON MESSAGE INPUT
        // ============================================================
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.focus();
        }
        
        // ============================================================
        // SEARCH CONVERSATIONS
        // ============================================================
        const searchInput = document.getElementById('searchConversations');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase().trim();
                const items = document.querySelectorAll('.conversation-item');
                
                items.forEach(item => {
                    const searchData = item.getAttribute('data-search') || '';
                    if (searchData.includes(searchTerm)) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        
        // ============================================================
        // MOBILE RESPONSIVE - TOGGLE PANELS
        // ============================================================
        const conversationsPanel = document.getElementById('conversationsPanel');
        const chatPanel = document.getElementById('chatPanel');
        const backBtn = document.getElementById('backToConversations');
        
        // Check if we're on mobile
        function isMobile() {
            return window.innerWidth <= 768;
        }
        
        // Handle panel visibility on mobile
        function handleMobileView() {
            if (isMobile()) {
                // If we have a conversation selected, show chat panel
                if (chatPanel && !chatPanel.classList.contains('hidden-mobile')) {
                    conversationsPanel.classList.add('hidden-mobile');
                    chatPanel.classList.remove('hidden-mobile');
                } else if (conversationsPanel) {
                    // Show conversations list by default on mobile
                    conversationsPanel.classList.remove('hidden-mobile');
                    if (chatPanel) {
                        chatPanel.classList.add('hidden-mobile');
                    }
                }
            } else {
                // Desktop - show both panels
                if (conversationsPanel) {
                    conversationsPanel.classList.remove('hidden-mobile');
                }
                if (chatPanel) {
                    chatPanel.classList.remove('hidden-mobile');
                }
            }
        }
        
        // Initialize mobile view
        handleMobileView();
        
        // Handle back button on mobile
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                if (isMobile()) {
                    conversationsPanel.classList.remove('hidden-mobile');
                    chatPanel.classList.add('hidden-mobile');
                }
            });
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            handleMobileView();
        });
        
        // When a conversation is clicked on mobile, show the chat panel
        document.querySelectorAll('.conversation-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (isMobile()) {
                    // Let the link navigate, then show chat panel
                    // We'll use a small delay to let the navigation happen
                    setTimeout(() => {
                        if (chatPanel) {
                            chatPanel.classList.remove('hidden-mobile');
                            conversationsPanel.classList.add('hidden-mobile');
                        }
                    }, 100);
                }
            });
        });
        
        // ============================================================
        // PREVENT FORM RESUBMISSION ON PAGE REFRESH
        // ============================================================
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // ============================================================
        // SEND MESSAGE ON ENTER KEY
        // ============================================================
        if (messageInput) {
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.form.submit();
                }
            });
        }
    </script>
</body>
</html>