<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

// Check if user is logged in
if (!isLoggedIn() || isAdmin()) {
    redirect('../auth/login.php');
}

$student_id = $_SESSION['user_id'];

// Get user profile picture
$stmt = $pdo->prepare("SELECT profile_pic, full_name FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();
$profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
$profile_pic_exists = !empty($profile_pic) && $profile_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic);

// Get unread messages count
$unread_messages = getUnreadMessageCount($pdo, $student_id);

// Get system settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$university_name = $settings['university_name'] ?? 'Graduation Clearance System';
$academic_year = $settings['academic_year'] ?? date('Y');
$graduation_year = $settings['graduation_year'] ?? date('Y');

$page_title = 'Help & Support';
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
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128,0,32,0.08);
            --primary-glow: rgba(128,0,32,0.12);
            --gray-50: #f8f9fc;
            --gray-100: #f4f6f9;
            --gray-200: #e8ecef;
            --gray-300: #dee2e8;
            --gray-500: #9aa4b2;
            --gray-600: #6c7683;
            --gray-700: #4a5360;
            --gray-800: #2d3440;
            --gray-900: #1a1e24;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --danger: #ef4444;
            --gold: #c9a84c;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-50);
            overflow-x: hidden;
        }
        
        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 12px 0;
            box-shadow: 0 4px 25px rgba(128, 0, 32, 0.15);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            color: white !important;
            letter-spacing: -0.5px;
        }
        
        .navbar-brand i { margin-right: 10px; }
        
        .navbar-toggler {
            border: 2px solid rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 12px;
            border-radius: 8px;
        }
        
        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }
        
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            position: relative;
            transition: all 0.3s;
        }
        
        .navbar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white !important;
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link.active {
            background: rgba(255,255,255,0.15);
            color: white !important;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 2px var(--primary);
        }
        
        /* Profile Dropdown */
        .profile-dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 12px 5px 8px;
            border-radius: 50px;
            transition: all 0.3s;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            color: white !important;
            text-decoration: none;
            cursor: pointer;
        }
        
        .profile-dropdown-toggle:hover {
            background: rgba(255,255,255,0.2);
            border-color: rgba(255,255,255,0.3);
        }
        
        .profile-dropdown-toggle .avatar-sm {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.8rem;
            color: white;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .profile-dropdown-toggle .avatar-sm img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-dropdown-toggle .profile-name {
            color: white;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .profile-dropdown-toggle .profile-arrow {
            color: rgba(255,255,255,0.7);
            font-size: 0.7rem;
            transition: transform 0.3s;
        }
        
        .dropdown-menu {
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border: none;
            margin-top: 12px;
            animation: fadeInDown 0.3s ease;
            min-width: 200px;
        }
        
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-item {
            padding: 10px 20px;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background: var(--primary-soft);
            color: var(--primary);
        }
        
        .dropdown-item.text-danger:hover {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }
        
        .main-content { 
            padding: 30px; 
            min-height: calc(100vh - 70px); 
        }
        
        .page-header { 
            margin-bottom: 35px; 
            text-align: center;
        }
        
        .page-header h2 { 
            font-size: 2rem; 
            font-weight: 800; 
            color: var(--gray-900); 
            margin: 0; 
        }
        
        .page-header h2 i { 
            color: var(--primary); 
            margin-right: 12px; 
        }
        
        .page-header p { 
            color: var(--gray-600); 
            margin: 8px 0 0; 
            font-size: 1rem;
            max-width: 650px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
        }
        
        /* Quick Links */
        .quick-links {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }
        
        .quick-link {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 50px;
            padding: 8px 20px;
            text-decoration: none;
            color: var(--gray-700);
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-link:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px var(--primary-glow);
        }
        
        .quick-link i {
            color: var(--primary);
        }
        
        /* Cards */
        .card-modern {
            background: white;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            padding: 28px 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            transition: all 0.3s;
        }
        
        .card-modern:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }
        
        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 22px;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-title i { 
            color: var(--primary); 
            font-size: 1.2rem;
        }
        
        .card-title .badge-count {
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--gray-500);
            margin-left: auto;
        }
        
        /* FAQ Items - Professional Accordion */
        .faq-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .faq-item {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            background: white;
        }
        
        .faq-item:hover {
            border-color: var(--primary-soft);
        }
        
        .faq-item.active {
            border-color: var(--primary);
            box-shadow: 0 4px 15px var(--primary-glow);
        }
        
        .faq-question {
            padding: 16px 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: white;
            border: none;
            width: 100%;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-800);
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
            gap: 12px;
        }
        
        .faq-question:hover {
            background: var(--gray-50);
        }
        
        .faq-question .faq-icon {
            color: var(--primary);
            font-size: 0.8rem;
            transition: transform 0.3s;
            flex-shrink: 0;
            margin-top: 3px;
        }
        
        .faq-item.active .faq-icon {
            transform: rotate(180deg);
        }
        
        .faq-question .q-number {
            color: var(--primary);
            font-weight: 700;
            margin-right: 4px;
        }
        
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.3s ease;
            padding: 0 20px;
        }
        
        .faq-item.active .faq-answer {
            max-height: 800px;
            padding: 0 20px 20px 20px;
        }
        
        .faq-answer .content {
            font-size: 0.85rem;
            color: var(--gray-600);
            line-height: 1.8;
        }
        
        .faq-answer .content ol,
        .faq-answer .content ul {
            padding-left: 20px;
            margin-top: 6px;
            margin-bottom: 6px;
        }
        
        .faq-answer .content ol li,
        .faq-answer .content ul li {
            margin-bottom: 4px;
        }
        
        .faq-answer .content strong {
            color: var(--gray-800);
        }
        
        .faq-answer .content .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 30px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-badge.locked { background: rgba(108,118,131,0.1); color: var(--gray-600); }
        .status-badge.pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-badge.approved { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-badge.rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .faq-answer .content .workflow-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 20px;
            margin: 8px 0;
            padding-left: 0;
            list-style: none;
        }
        
        .faq-answer .content .workflow-grid li {
            padding: 4px 0;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.8rem;
        }
        
        .faq-answer .content .workflow-grid li strong {
            color: var(--primary);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            
            .navbar-collapse {
                background: white;
                border-radius: 16px;
                margin-top: 15px;
                padding: 20px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            }
            
            .navbar-nav .nav-link {
                color: var(--gray-800) !important;
                padding: 10px 15px !important;
            }
            
            .navbar-nav .nav-link:hover {
                background: var(--primary-soft);
                color: var(--primary) !important;
            }
            
            .profile-dropdown-toggle {
                background: transparent;
                border: none;
                padding: 5px 0;
                width: 100%;
                justify-content: flex-start;
            }
            
            .profile-dropdown-toggle .avatar-sm {
                background: var(--primary-soft);
                color: var(--primary);
            }
            
            .profile-dropdown-toggle .profile-name {
                color: var(--gray-800);
            }
            
            .profile-dropdown-toggle .profile-arrow {
                color: var(--gray-600);
            }
            
            .dropdown-menu {
                box-shadow: none;
                border: 1px solid var(--gray-200);
                margin-top: 5px;
            }
            
            .page-header h2 { font-size: 1.6rem; }
            .page-header p { font-size: 0.9rem; }
            
            .card-modern { padding: 20px; }
            
            .quick-links {
                gap: 8px;
            }
            
            .quick-link {
                font-size: 0.7rem;
                padding: 6px 14px;
            }
            
            .faq-question { font-size: 0.82rem; padding: 14px 16px; }
            .faq-item.active .faq-answer { padding: 0 16px 16px 16px; }
            
            .faq-answer .content .workflow-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 576px) {
            .main-content { padding: 15px; }
            .card-modern { padding: 16px; }
            .page-header h2 { font-size: 1.3rem; }
            
            .quick-links {
                flex-direction: column;
                align-items: stretch;
            }
            
            .quick-link {
                justify-content: center;
            }
            
            .faq-question { font-size: 0.78rem; padding: 12px 14px; }
            .faq-answer .content { font-size: 0.8rem; }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-in {
            animation: fadeInUp 0.4s ease forwards;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
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
                        <a class="nav-link active" href="help-support.php">
                            <i class="fas fa-headset me-1"></i> Help & Support
                        </a>
                    </li>
                    <li class="nav-item position-relative">
                        <a class="nav-link" href="message.php">
                            <i class="fas fa-comments me-1"></i> Messages
                            <?php if ($unread_messages > 0): ?>
                                <span class="notification-badge"><?php echo $unread_messages; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Profile Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle profile-dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-sm">
                                <?php if ($profile_pic_exists): ?>
                                    <img src="../assets/uploads/Students-profile/<?php echo $profile_pic; ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </span>
                            <span class="profile-name"><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                            <i class="fas fa-chevron-down profile-arrow"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
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
    
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header animate-in">
                <h2><i class="fas fa-headset"></i> Help & Support</h2>
                <p>Find answers to the most common questions about the Graduation Clearance System. If you cannot find the information you need, please contact the relevant Department Head through the <strong>Messages</strong> feature.</p>
            </div>
            
            <!-- Quick Links -->
            <div class="quick-links animate-in" style="animation-delay: 0.05s;">
                <a href="#faq1" class="quick-link"><i class="fas fa-play-circle"></i> Getting Started</a>
                <a href="#faq2" class="quick-link"><i class="fas fa-info-circle"></i> Status Meanings</a>
                <a href="#faq5" class="quick-link"><i class="fas fa-comments"></i> Communication</a>
                <a href="#faq9" class="quick-link"><i class="fas fa-certificate"></i> Certificate</a>
                <a href="#faq11" class="quick-link"><i class="fas fa-user-edit"></i> Profile & Password</a>
            </div>
            
            <!-- FAQ Section -->
            <div class="card-modern animate-in" style="animation-delay: 0.1s;">
                <div class="card-title">
                    <i class="fas fa-question-circle"></i> Frequently Asked Questions
                    <span class="badge-count">14 questions</span>
                </div>
                
                <div class="faq-container">
                    <!-- FAQ 1 -->
                    <div class="faq-item active" id="faq1">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">1.</span> How do I begin my graduation clearance?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>Your graduation clearance process begins automatically after you log into the system. You must complete clearance by following the official university clearance workflow. Departments become available one at a time, and you cannot skip or rearrange the sequence.</p>
                                
                                <p><strong>Official Clearance Order:</strong></p>
                                <ul class="workflow-grid">
                                    <li><strong>1.</strong> Computer Laboratory (COMP_LAB)</li>
                                    <li><strong>2.</strong> Student Guild Council (SGC)</li>
                                    <li><strong>3.</strong> Hostel Management (HOSTEL)</li>
                                    <li><strong>4.</strong> Dean of Students Office (DOS)</li>
                                    <li><strong>5.</strong> University Library (LIBRARY)</li>
                                    <li><strong>6.</strong> Facilities Management (FACILITY)</li>
                                    <li><strong>7.</strong> Finance Department (FINANCE)</li>
                                    <li><strong>8.</strong> Directorate of Quality Assurance (QA)</li>
                                    <li><strong>9.</strong> Academic Department / Head of Department (ACADEMIC_HOD)</li>
                                    <li><strong>10.</strong> Academic Registry (REGISTRY)</li>
                                </ul>
                                <p><strong>Note:</strong> Each department will unlock automatically after the previous department has approved your clearance. You cannot skip departments.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 2 -->
                    <div class="faq-item" id="faq2">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">2.</span> What do the clearance statuses mean?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>Each department displays one of the following statuses during your clearance process.</p>
                                
                                <p><strong><span class="status-badge locked">🔒 Locked</span></strong><br>
                                The department is not yet available because you have not completed the previous department in the clearance workflow. <strong>You cannot open or access a locked department.</strong></p>
                                
                                <p><strong><span class="status-badge pending">🟡 Pending</span></strong><br>
                                Your clearance request has been submitted and is waiting for the Department Head to review it. No further action is required unless the department requests additional information or documents.</p>
                                
                                <p><strong><span class="status-badge approved">🟢 Approved</span></strong><br>
                                The Department Head has successfully approved your clearance. Once approved: the department is marked as completed, the next department in the workflow is unlocked automatically, and your clearance progress is updated.</p>
                                
                                <p><strong><span class="status-badge rejected">🔴 Rejected</span></strong><br>
                                The Department Head has identified an issue with your clearance. If rejected: open the department to read the rejection comments, correct the issue or upload the requested documents, resubmit your clearance request, or communicate directly with the Department Head through the <strong>Messages</strong> page for clarification. The clearance process cannot continue until the rejection has been resolved.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 3 -->
                    <div class="faq-item" id="faq3">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">3.</span> Do I need to upload documents for every department?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>No. Some departments require supporting documents, while others only verify your information internally.</p>
                                <p>Before taking any action, open the department to view its clearance requirements. Upload documents only if the department requests them.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 4 -->
                    <div class="faq-item" id="faq4">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">4.</span> What should I do if my clearance is rejected?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>If a department rejects your clearance:</p>
                                <ol>
                                    <li>Open the rejected department.</li>
                                    <li>Read the rejection comments carefully.</li>
                                    <li>Correct the identified issue.</li>
                                    <li>Upload any required documents if requested.</li>
                                    <li>Resubmit your clearance request.</li>
                                    <li>If you need further clarification, use the <strong>Messages</strong> feature to contact the Department Head directly.</li>
                                </ol>
                                <p>The workflow will continue only after the department approves your clearance.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 5 -->
                    <div class="faq-item" id="faq5">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">5.</span> Can I communicate with the Department Head?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>Yes. Every student can communicate directly with the relevant Department Head through the built-in <strong>Messages</strong> feature.</p>
                                <p>You can use Messages to:</p>
                                <ul>
                                    <li>Request clarification about a rejection.</li>
                                    <li>Ask questions about required documents.</li>
                                    <li>Follow up on pending clearance requests.</li>
                                    <li>Receive responses regarding your application.</li>
                                </ul>
                                <p>Please ensure that all communication remains respectful and professional.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 6 -->
                    <div class="faq-item" id="faq6">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">6.</span> How long does it take for a Department Head to respond?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>Department Heads are expected to review applications and respond within <strong>24 working hours</strong>.</p>
                                <p>Response times may vary during weekends, public holidays, or busy graduation periods.</p>
                                <p>If you have not received a response after 24 working hours, you may send a polite follow-up message through the <strong>Messages</strong> feature.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 7 -->
                    <div class="faq-item" id="faq7">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">7.</span> Can I skip a department?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p><strong>No.</strong> The Graduation Clearance System follows a university-approved clearance workflow.</p>
                                <p>You must complete every department in the correct order before the next department becomes available. Skipping departments is not permitted.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 8 -->
                    <div class="faq-item" id="faq8">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">8.</span> How can I track my clearance progress?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>Open the <strong>Clearance Status</strong> page to monitor your progress.</p>
                                <p>You can view:</p>
                                <ul>
                                    <li>Current active department</li>
                                    <li>Approved departments</li>
                                    <li>Pending departments</li>
                                    <li>Rejected departments</li>
                                    <li>Locked departments</li>
                                    <li>Overall clearance progress</li>
                                </ul>
                                <p>The page updates automatically whenever a Department Head approves or rejects your clearance.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 9 -->
                    <div class="faq-item" id="faq9">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">9.</span> When will I receive my Graduation Clearance Certificate?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>Your Graduation Clearance Certificate becomes available only after:</p>
                                <ul>
                                    <li>Every department has approved your clearance.</li>
                                    <li>The Academic Registry has completed the final approval.</li>
                                </ul>
                                <p>Only fully cleared students are eligible to receive the official Graduation Clearance Certificate.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 10 -->
                    <div class="faq-item" id="faq10">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">10.</span> How can I view, download, or print my certificate?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>After you have been fully cleared:</p>
                                <ol>
                                    <li>Log in to your student account.</li>
                                    <li>Open your <strong>Dashboard</strong>.</li>
                                    <li>Navigate to the <strong>Graduation Clearance Certificate</strong> section.</li>
                                    <li>View your certificate.</li>
                                    <li>Download it as a PDF.</li>
                                    <li>Print it for official use if required.</li>
                                </ol>
                                <p>If the certificate is unavailable, it means your clearance process has not yet been completed.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 11 -->
                    <div class="faq-item" id="faq11">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">11.</span> How do I update my profile information?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>To update your personal information:</p>
                                <ol>
                                    <li>Log in to your account.</li>
                                    <li>Open <strong>Profile Settings</strong>.</li>
                                    <li>Update your details such as: Profile Photo, Email Address, Phone Number.</li>
                                    <li>Save your changes.</li>
                                </ol>
                                <p>Some academic information, including your Student ID, Programme, and Registration Number, can only be updated by the system administrator.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 12 -->
                    <div class="faq-item" id="faq12">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">12.</span> How do I change my password?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>To change your password:</p>
                                <ol>
                                    <li>Log in to your account.</li>
                                    <li>Open <strong>Profile Settings</strong>.</li>
                                    <li>Select <strong>Change Password</strong>.</li>
                                    <li>Enter your current password.</li>
                                    <li>Enter your new password.</li>
                                    <li>Confirm the new password.</li>
                                    <li>Save your changes.</li>
                                </ol>
                                <p>For your security, use a strong password and do not share it with anyone.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 13 -->
                    <div class="faq-item" id="faq13">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">13.</span> What should I do if I forget my password?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>Click <strong>Forgot Password</strong> on the login page and follow the password recovery instructions.</p>
                                <p>If you are unable to reset your password successfully, contact the system administrator or the university support team for assistance.</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ 14 -->
                    <div class="faq-item" id="faq14">
                        <button class="faq-question" onclick="toggleFAQ(this)">
                            <span><span class="q-number">14.</span> Who should I contact if I experience technical problems?</span>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </button>
                        <div class="faq-answer">
                            <div class="content">
                                <p>If you experience any technical issues such as:</p>
                                <ul>
                                    <li>Login problems</li>
                                    <li>Missing departments</li>
                                    <li>Upload failures</li>
                                    <li>System errors</li>
                                    <li>Certificate issues</li>
                                    <li>Clearance status not updating</li>
                                    <li>Messaging problems</li>
                                </ul>
                                <p>Submit a request through the <strong>Help & Support</strong> page or contact the system administrator for technical assistance.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // FAQ Accordion Toggle
        function toggleFAQ(button) {
            const item = button.closest('.faq-item');
            const isActive = item.classList.contains('active');
            
            // Close all other FAQs
            document.querySelectorAll('.faq-item').forEach(function(el) {
                el.classList.remove('active');
            });
            
            // Toggle this one
            if (!isActive) {
                item.classList.add('active');
            }
        }
        
        // Auto-open first FAQ on load
        document.addEventListener('DOMContentLoaded', function() {
            // First FAQ is already open by default (has 'active' class)
        });
        
        // Handle hash links for quick links
        document.querySelectorAll('.quick-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const target = document.getElementById(targetId);
                if (target) {
                    // Close all FAQs
                    document.querySelectorAll('.faq-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                    // Open the target FAQ
                    target.classList.add('active');
                    // Scroll to it
                    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
    </script>
</body>
</html>