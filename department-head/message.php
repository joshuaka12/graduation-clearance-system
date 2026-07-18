<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$conversation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Get selected department
if (!isset($_SESSION['selected_department_id'])) {
    redirect('select-department.php');
}
$department_id = $_SESSION['selected_department_id'];

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = sanitizeInput($_POST['message']);
    $conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : $conversation_id;
    
    if ($conversation_id > 0 && !empty($message)) {
        $conversation = getConversationById($pdo, $conversation_id);
        
        if ($conversation && $conversation['department_id'] == $department_id) {
            if (sendMessage($pdo, $conversation_id, $user_id, $conversation['student_id'], $message)) {
                header("Location: message.php?id=" . $conversation_id);
                exit();
            } else {
                $error = "Failed to send message.";
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
    $student_id = (int)$_POST['student_id'];
    $subject = sanitizeInput($_POST['subject']);
    
    // Verify student exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' AND is_active = 1");
    $stmt->execute([$student_id]);
    if (!$stmt->fetch()) {
        $error = "Invalid student selected.";
    } else {
        // Check if conversation already exists
        $stmt = $pdo->prepare("SELECT id FROM conversations WHERE student_id = ? AND department_id = ?");
        $stmt->execute([$student_id, $department_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            header("Location: message.php?id=" . $existing['id']);
            exit();
        } else {
            $stmt = $pdo->prepare("INSERT INTO conversations (student_id, department_id, subject, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            
            if ($stmt->execute([$student_id, $department_id, $subject])) {
                $convo_id = $pdo->lastInsertId();
                $initial_message = "Hello, I'm reaching out regarding: " . $subject;
                sendMessage($pdo, $convo_id, $user_id, $student_id, $initial_message);
                header("Location: message.php?id=" . $convo_id);
                exit();
            } else {
                $error = "Failed to start conversation.";
            }
        }
    }
}

// Get conversations for this department with student profile pics
$conversations = getConversationsForDepartmentHead($pdo, $department_id);

// Add profile picture to each conversation
foreach ($conversations as &$conv) {
    $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
    $stmt->execute([$conv['student_id']]);
    $student = $stmt->fetch();
    $conv['student_profile_pic'] = $student['profile_pic'] ?? 'default-avatar.png';
}

$current_conversation = null;
$messages = [];

if ($conversation_id > 0) {
    $current_conversation = getConversationById($pdo, $conversation_id);
    if ($current_conversation && $current_conversation['department_id'] == $department_id) {
        $messages = getMessages($pdo, $conversation_id, $user_id);
    }
}

// Get students for new chat with profile pictures
$stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.full_name, u.student_id, u.email, u.profile_pic
    FROM users u
    JOIN student_clearance sc ON u.id = sc.student_id
    JOIN clearance_items ci ON sc.clearance_item_id = ci.id
    WHERE ci.department_id = ? AND u.role = 'student' AND u.is_active = 1
    ORDER BY u.full_name ASC
");
$stmt->execute([$department_id]);
$students = $stmt->fetchAll();

// Get department info
$stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
$stmt->execute([$department_id]);
$dept = $stmt->fetch();
$department_name = $dept['department_name'] ?? 'Department';

$unread_count = getUnreadMessageCount($pdo, $user_id);

$page_title = 'Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?> - Department Panel</title>
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
            --gray-500: #9aa4b2;
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
            height: 100vh;
        }
        
        /* Mobile Menu Toggle */
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
        
        /* Sidebar - Same as clearance-items/index.php */
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
        
        /* Sidebar Overlay */
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
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            height: calc(100vh - 0px);
        }
        
        /* Chat Container */
        .chat-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            height: 100%;
            overflow: hidden;
        }
        
        /* Sidebar - Conversations List */
        .conversations-sidebar {
            width: 340px;
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            background: white;
        }
        
        .sidebar-header-custom {
            padding: 18px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: white;
        }
        
        .sidebar-header-custom h5 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .sidebar-header-custom h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .btn-new {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-new:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        /* Search Box */
        .search-box {
            padding: 12px 15px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .search-box input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.8rem;
            outline: none;
            transition: all 0.2s;
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-soft);
        }
        
        .conversations-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: 15px 20px;
            border-bottom: 1px solid var(--gray-200);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
        }
        
        .conversation-item:hover {
            background: var(--gray-100);
        }
        
        .conversation-item.active {
            background: var(--primary-soft);
            border-left: 3px solid var(--primary);
        }
        
        /* Conversation Avatar with Profile Picture */
        .conversation-avatar {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .conversation-avatar .avatar-initials {
            font-weight: 700;
            font-size: 1rem;
        }
        
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        
        .conversation-title {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 4px;
            color: var(--gray-800);
        }
        
        .conversation-preview {
            font-size: 0.7rem;
            color: var(--gray-600);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .conversation-time {
            font-size: 0.65rem;
            color: var(--gray-500);
            margin-top: 4px;
        }
        
        .unread-badge {
            background: var(--primary);
            color: white;
            border-radius: 16px;
            padding: 2px 8px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--gray-50);
        }
        
        .chat-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        
        .chat-header h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .chat-header p {
            font-size: 0.7rem;
            margin: 4px 0 0;
            color: var(--gray-600);
        }
        
        .refresh-btn {
            background: transparent;
            border: 1px solid var(--gray-200);
            color: var(--gray-600);
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 0.7rem;
            transition: all 0.2s;
        }
        
        .refresh-btn:hover {
            background: var(--primary-soft);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Messages Area */
        .messages-area {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .message {
            display: flex;
        }
        
        .message.sent {
            justify-content: flex-end;
        }
        
        .message.received {
            justify-content: flex-start;
        }
        
        .message-bubble {
            max-width: 65%;
            padding: 10px 14px;
            border-radius: 18px;
            font-size: 0.85rem;
            line-height: 1.4;
        }
        
        .message.sent .message-bubble {
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.received .message-bubble {
            background: white;
            color: var(--gray-800);
            border: 1px solid var(--gray-200);
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        
        .message-meta {
            font-size: 0.6rem;
            margin-top: 4px;
            opacity: 0.7;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .message.sent .message-meta {
            justify-content: flex-end;
        }
        
        /* Input Area */
        .message-input-area {
            padding: 16px 24px;
            border-top: 1px solid var(--gray-200);
            background: white;
        }
        
        .message-input {
            display: flex;
            gap: 12px;
        }
        
        .message-input input {
            flex: 1;
            border: 1px solid var(--gray-200);
            border-radius: 30px;
            padding: 12px 18px;
            font-size: 0.85rem;
            outline: none;
            transition: all 0.2s;
        }
        
        .message-input input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-soft);
        }
        
        .message-input button {
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 0 24px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .message-input button:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        /* Empty State */
        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            text-align: center;
            padding: 40px;
        }
        
        .empty-chat i {
            font-size: 3rem;
            color: var(--gray-200);
            margin-bottom: 16px;
        }
        
        .empty-chat h5 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-800);
        }
        
        .empty-chat p {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-bottom: 20px;
        }
        
        /* Modal */
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
        
        .modal-body {
            padding: 20px;
        }
        
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--gray-700);
        }
        
        .form-select, .form-control {
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            padding: 10px 14px;
            font-size: 0.85rem;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-soft);
        }
        
        .alert-info {
            background: var(--primary-soft);
            border: none;
            color: var(--primary);
        }
        
        /* Responsive */
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
                padding: 10px;
            }
            
            .conversations-sidebar {
                width: 280px;
            }
            
            .message-bubble {
                max-width: 85%;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 8px;
            }
            
            .conversations-sidebar {
                width: 250px;
            }
            
            .sidebar-header-custom {
                padding: 12px 15px;
            }
            
            .conversation-item {
                padding: 10px 15px;
            }
            
            .chat-header {
                padding: 12px 18px;
            }
            
            .messages-area {
                padding: 15px;
            }
            
            .message-input-area {
                padding: 12px 18px;
            }
            
            .conversation-avatar {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
        }
        
        ::-webkit-scrollbar {
            width: 5px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-200);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--gray-500);
            border-radius: 10px;
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
    
    <!-- Sidebar - Same as clearance-items/index.php (no profile pic) -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Department Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="clearance-items/index.php" class="menu-item"><i class="fas fa-list-check"></i> Clearance Items</a>
            <a href="students.php" class="menu-item"><i class="fas fa-users"></i> Students</a>
            <a href="reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="message.php" class="menu-item active">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <div class="menu-divider"></div>
            <a href="profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
            <a href="change-password.php" class="menu-item"><i class="fas fa-key"></i> Change Password</a>
            <a href="../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <div class="chat-container">
            <!-- Conversations Sidebar -->
            <div class="conversations-sidebar">
                <div class="sidebar-header-custom">
                    <h5><i class="fas fa-comments me-2" style="color: var(--primary);"></i> Conversations</h5>
                    <button class="btn-new" data-bs-toggle="modal" data-bs-target="#newChatModal">
                        <i class="fas fa-plus me-1"></i> New
                    </button>
                </div>
                <div class="search-box">
                    <input type="text" id="searchConversations" placeholder="🔍 Search students...">
                </div>
                <div class="conversations-list" id="conversationsList">
                    <?php if (empty($conversations)): ?>
                        <div class="text-center text-muted py-5">
                            <i class="fas fa-inbox fa-2x mb-2" style="opacity: 0.3;"></i>
                            <p class="small mb-0">No conversations yet</p>
                            <button class="btn btn-sm btn-new mt-2" data-bs-toggle="modal" data-bs-target="#newChatModal">Start a conversation</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): 
                            $student_pic = $conv['student_profile_pic'] ?? 'default-avatar.png';
                            $student_pic_exists = !empty($student_pic) && $student_pic != 'default-avatar.png';
                            $student_initials = strtoupper(substr($conv['full_name'], 0, 1));
                            
                            $stmt = $pdo->prepare("SELECT student_id FROM users WHERE id = ?");
                            $stmt->execute([$conv['student_id']]);
                            $student_id_data = $stmt->fetch();
                        ?>
                        <a href="message.php?id=<?php echo $conv['id']; ?>" class="conversation-item <?php echo ($conversation_id == $conv['id']) ? 'active' : ''; ?>">
                            <div class="d-flex gap-3">
                                <div class="conversation-avatar">
                                    <?php if ($student_pic_exists): ?>
                                        <img src="../assets/uploads/Students-profile/<?php echo $student_pic; ?>" alt="<?php echo htmlspecialchars($conv['full_name']); ?>">
                                    <?php else: ?>
                                        <span class="avatar-initials"><?php echo $student_initials; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conversation-info">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="conversation-title">
                                            <?php echo htmlspecialchars($conv['full_name']); ?>
                                            <small class="text-muted ms-1" style="font-weight: 400; font-size: 0.65rem;">
                                                (<?php echo $student_id_data['student_id'] ?? 'N/A'; ?>)
                                            </small>
                                        </div>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-preview">
                                        <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages', 0, 50)); ?>
                                    </div>
                                    <div class="conversation-time">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo $conv['last_message_time'] ? date('M d, H:i', strtotime($conv['last_message_time'])) : 'No messages'; ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Chat Area -->
            <div class="chat-area">
                <?php if ($current_conversation): ?>
                    <?php 
                        $stmt = $pdo->prepare("SELECT full_name, student_id, profile_pic FROM users WHERE id = ?");
                        $stmt->execute([$current_conversation['student_id']]);
                        $student_info = $stmt->fetch();
                        
                        $chat_student_pic = $student_info['profile_pic'] ?? 'default-avatar.png';
                        $chat_student_pic_exists = !empty($chat_student_pic) && $chat_student_pic != 'default-avatar.png';
                        $chat_student_initials = strtoupper(substr($student_info['full_name'] ?? 'S', 0, 1));
                    ?>
                    <div class="chat-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <div class="conversation-avatar" style="width: 40px; height: 40px; border-radius: 50%;">
                                    <?php if ($chat_student_pic_exists): ?>
                                        <img src="../assets/uploads/Students-profile/<?php echo $chat_student_pic; ?>" alt="<?php echo htmlspecialchars($student_info['full_name'] ?? 'Student'); ?>">
                                    <?php else: ?>
                                        <span class="avatar-initials"><?php echo $chat_student_initials; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h5>
                                        <?php echo htmlspecialchars($student_info['full_name'] ?? 'Student'); ?>
                                        <small class="text-muted ms-1" style="font-weight: 400; font-size: 0.75rem;">
                                            (<?php echo htmlspecialchars($student_info['student_id'] ?? 'N/A'); ?>)
                                        </small>
                                    </h5>
                                    <p>
                                        <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($department_name); ?>
                                    </p>
                                </div>
                            </div>
                            <button class="refresh-btn" onclick="location.reload();" title="Refresh messages">
                                <i class="fas fa-sync-alt me-1"></i> Refresh
                            </button>
                        </div>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger m-3 py-2" style="font-size: 0.8rem;"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="messages-area" id="messagesArea">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comment-dots fa-2x mb-2" style="opacity: 0.3;"></i>
                                <p class="small">No messages yet. Start the conversation!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                            <div class="message <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                <div class="message-bubble">
                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    <div class="message-meta">
                                        <span><?php echo date('h:i A', strtotime($msg['created_at'])); ?></span>
                                        <?php if ($msg['sender_id'] == $user_id && $msg['is_read']): ?>
                                            <i class="fas fa-check-double"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div id="scrollBottom"></div>
                    </div>
                    
                    <div class="message-input-area">
                        <form method="POST" class="message-input">
                            <input type="hidden" name="conversation_id" value="<?php echo $conversation_id; ?>">
                            <input type="text" name="message" id="messageInput" placeholder="Type your message..." required autocomplete="off">
                            <button type="submit" name="send_message">Send <i class="fas fa-paper-plane ms-1"></i></button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="empty-chat">
                        <i class="fas fa-comments"></i>
                        <h5>No conversation selected</h5>
                        <p>Select a conversation from the sidebar or start a new one</p>
                        <button class="btn btn-new" data-bs-toggle="modal" data-bs-target="#newChatModal">Start New Conversation</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- New Chat Modal -->
    <div class="modal fade" id="newChatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i> New Conversation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Student</label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Choose a student...</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['full_name']); ?> (<?php echo $student['student_id']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Only students who have applied for clearance in your department are shown.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" required placeholder="e.g., Clearance Update, Missing Document, Approval Notice">
                        </div>
                        <div class="alert alert-info py-2" style="font-size: 0.75rem;">
                            <i class="fas fa-info-circle me-1"></i> Your message will be sent to the selected student.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="start_chat" class="btn" style="background: var(--primary); color: white;">Start Conversation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar Toggle for Mobile
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
        
        // Scroll to bottom of messages
        function scrollToBottom() {
            const messagesArea = document.getElementById('messagesArea');
            if (messagesArea) {
                messagesArea.scrollTop = messagesArea.scrollHeight;
            }
        }
        scrollToBottom();
        
        // Search conversations
        document.getElementById('searchConversations').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const conversations = document.querySelectorAll('.conversation-item');
            
            conversations.forEach(conv => {
                const text = conv.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    conv.style.display = 'block';
                } else {
                    conv.style.display = 'none';
                }
            });
        });
        
        // Focus on message input when chat loads
        document.getElementById('messageInput')?.focus();
    </script>
</body>
</html>