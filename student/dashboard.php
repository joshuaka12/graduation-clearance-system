<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// If admin tries to access student dashboard, redirect to admin dashboard
if (isAdmin()) {
    redirect('../admin/dashboard.php');
}

$student_id = $_SESSION['user_id'];

// Get system settings for branding
$university_name = getUniversityName($pdo);
$academic_year = getAcademicYear($pdo);
$graduation_year = getGraduationYear($pdo);

// Get student clearance progress
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT ci.id) as total_items,
        SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        ROUND((SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) / NULLIF(COUNT(DISTINCT ci.id), 0)) * 100, 1) as completion_rate
    FROM clearance_items ci
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
");
$stmt->execute([$student_id]);
$progress = $stmt->fetch();

// Get department-wise clearance status
$stmt = $pdo->prepare("
    SELECT 
        d.id,
        d.department_name,
        d.department_code,
        COUNT(ci.id) as total_items,
        SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        CASE 
            WHEN SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) = COUNT(ci.id) THEN 'completed'
            WHEN SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
            ELSE 'pending'
        END as status
    FROM departments d
    JOIN clearance_items ci ON d.id = ci.department_id
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
    WHERE d.is_active = 1
    GROUP BY d.id
    ORDER BY d.clearance_order ASC
");
$stmt->execute([$student_id]);
$departments = $stmt->fetchAll();

// Get chat unread count
$unread_messages = getUnreadMessageCount($pdo, $student_id);

// Get recent conversations
$stmt = $pdo->prepare("
    SELECT c.*, d.department_name,
           (SELECT message FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
    FROM conversations c
    JOIN departments d ON c.department_id = d.id
    WHERE c.student_id = ?
    ORDER BY c.updated_at DESC
    LIMIT 3
");
$stmt->execute([$student_id]);
$recent_conversations = $stmt->fetchAll();

// Calculate certificate eligibility
$is_eligible_for_certificate = ($progress['approved_count'] == $progress['total_items'] && $progress['total_items'] > 0);

// Get user profile picture
$stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();
$profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
$profile_pic_exists = !empty($profile_pic) && $profile_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic);

$page_title = 'Student Dashboard';
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
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --primary-glow: rgba(128, 0, 32, 0.15);
            --gray-50: #fafbfc;
            --gray-100: #f8f9fc;
            --gray-200: #e8ecef;
            --gray-300: #dee2e8;
            --gray-600: #6c7683;
            --gray-700: #4a5360;
            --gray-800: #2d3440;
            --gray-900: #1a1e24;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gold: #c9a84c;
            --gold-light: #f0e6c8;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--gray-100) 0%, #ffffff 100%);
            overflow-x: hidden;
        }
        
        /* Navbar - UNCHANGED */
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
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: rgba(255,255,255,0.2);
            font-weight: 600;
            font-size: 0.9rem;
            color: white;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .dropdown-menu {
            border-radius: 16px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            border: none;
            margin-top: 12px;
            animation: fadeInDown 0.3s ease;
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
        
        /* Main Content */
        .main-content {
            padding: 30px;
            min-height: calc(100vh - 70px);
        }
        
        /* Welcome Card - ENHANCED with elegant System Info */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 24px;
            padding: 35px 40px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(128, 0, 32, 0.2);
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255,255,255,0.08), transparent);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .welcome-card::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 250px;
            height: 250px;
            background: radial-gradient(circle, rgba(255,255,255,0.05), transparent);
            border-radius: 50%;
            pointer-events: none;
        }
        
        .welcome-card .welcome-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            z-index: 1;
        }
        
        .welcome-card .welcome-text h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .welcome-card .welcome-text p {
            opacity: 0.9;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        
        /* System Info - Elegant Chips */
        .system-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .system-chips .chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.12);
            backdrop-filter: blur(10px);
            padding: 6px 14px 6px 10px;
            border-radius: 50px;
            font-size: 0.75rem;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
        }
        
        .system-chips .chip:hover {
            background: rgba(255,255,255,0.2);
            transform: translateY(-2px);
        }
        
        .system-chips .chip i {
            font-size: 0.7rem;
            opacity: 0.8;
        }
        
        .system-chips .chip strong {
            font-weight: 600;
        }
        
        .welcome-card .student-badge {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            white-space: nowrap;
        }
        
        /* Certificate Button */
        .btn-certificate-download {
            background: linear-gradient(135deg, #c9a84c, #a8883c);
            color: white;
            border: none;
            padding: 14px 35px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(201,168,76,0.3);
        }
        
        .btn-certificate-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(201,168,76,0.4);
            color: white;
        }
        
        .btn-certificate-download i {
            font-size: 1.2rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 22px;
            transition: all 0.3s;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px var(--primary-glow);
            border-color: transparent;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            background: var(--primary-soft);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 15px;
        }
        
        .stat-info h3 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0;
            color: var(--gray-800);
            line-height: 1.2;
        }
        
        .stat-info p {
            margin: 8px 0 0;
            color: var(--gray-600);
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Progress Card */
        .progress-card {
            background: white;
            border-radius: 24px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid var(--gray-200);
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-weight: 600;
            color: var(--gray-700);
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .progress {
            height: 10px;
            border-radius: 20px;
            background: var(--gray-200);
        }
        
        .progress-bar {
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 20px;
            transition: width 0.5s ease;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 12px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
            border: none;
            box-shadow: 0 2px 5px rgba(128,0,32,0.2);
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128,0,32,0.3);
            color: white;
        }
        
        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            border: 1px solid var(--gray-200);
            margin-bottom: 25px;
            transition: all 0.3s;
        }
        
        .section-card:hover {
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        }
        
        .card-header-custom {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            background: white;
        }
        
        .card-header-custom h5 {
            font-size: 1rem;
            font-weight: 700;
            margin: 0;
            color: var(--gray-800);
        }
        
        .card-header-custom h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .btn-view-all {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 5px 12px;
            border-radius: 20px;
            transition: all 0.2s;
        }
        
        .btn-view-all:hover {
            background: var(--primary-soft);
            text-decoration: none;
        }
        
        /* Department Items */
        .department-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 25px;
            border-bottom: 1px solid var(--gray-200);
            transition: all 0.3s;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .department-item:last-child {
            border-bottom: none;
        }
        
        .department-item:hover {
            background: var(--gray-50);
        }
        
        .department-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 200px;
        }
        
        .dept-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-soft);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-completed { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .dept-progress {
            width: 200px;
        }
        
        .dept-progress .progress {
            height: 6px;
            margin-bottom: 5px;
        }
        
        /* Chat Items */
        .chat-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 25px;
            border-bottom: 1px solid var(--gray-200);
            text-decoration: none;
            transition: all 0.3s;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .chat-item:last-child {
            border-bottom: none;
        }
        
        .chat-item:hover {
            background: var(--gray-50);
        }
        
        .chat-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
            min-width: 200px;
        }
        
        .chat-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-soft);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .chat-details {
            flex: 1;
        }
        
        .chat-details strong {
            display: block;
            color: var(--gray-800);
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        
        .chat-last-message {
            font-size: 0.75rem;
            color: var(--gray-600);
            max-width: 280px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .chat-meta {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .chat-time {
            font-size: 0.7rem;
            color: var(--gray-500);
        }
        
        .unread-indicator {
            background: var(--primary);
            color: white;
            border-radius: 20px;
            padding: 2px 10px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        /* Help Card */
        .help-card {
            background: linear-gradient(135deg, #ffffff 0%, #faf5f5 100%);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid var(--gray-200);
            margin-bottom: 25px;
            text-align: center;
            padding: 40px 30px 35px;
            transition: all 0.3s;
            position: relative;
        }
        
        .help-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light), var(--primary));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(128, 0, 32, 0.1);
            border-color: var(--primary-soft);
        }
        
        .help-icon-wrapper {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-soft), rgba(128, 0, 32, 0.15));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            transition: all 0.3s;
        }
        
        .help-card:hover .help-icon-wrapper {
            transform: scale(1.05);
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }
        
        .help-card:hover .help-icon-wrapper i {
            color: white;
        }
        
        .help-icon-wrapper i {
            font-size: 2rem;
            color: var(--primary);
            transition: all 0.3s;
        }
        
        .help-card h5 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--gray-800);
        }
        
        .help-card p {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin-bottom: 20px;
            line-height: 1.5;
            max-width: 350px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .help-card .btn-support {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(128, 0, 32, 0.2);
        }
        
        .help-card .btn-support:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(128, 0, 32, 0.3);
            color: white;
        }
        
        .help-card .btn-support i {
            font-size: 0.9rem;
        }
        
        .help-card .support-hours {
            margin-top: 15px;
            font-size: 0.7rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .help-card .support-hours i {
            color: var(--primary);
            font-size: 0.8rem;
        }
        
        /* Certificate Alert */
        .certificate-eligible-banner {
            background: linear-gradient(135deg, #c9a84c 0%, #f0e6c8 100%);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 30px;
            border: 2px solid #d4b86a;
            box-shadow: 0 5px 20px rgba(201,168,76,0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .certificate-eligible-banner .cert-icon {
            font-size: 2.5rem;
            color: var(--primary);
        }
        
        .certificate-eligible-banner .cert-text h4 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .certificate-eligible-banner .cert-text p {
            color: var(--gray-700);
            margin: 0;
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .stats-grid {
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .welcome-card {
                padding: 25px;
            }
            
            .welcome-card .welcome-content {
                flex-direction: column;
            }
            
            .welcome-card .welcome-text h2 {
                font-size: 1.4rem;
            }
            
            .system-chips {
                gap: 8px;
            }
            
            .system-chips .chip {
                font-size: 0.7rem;
                padding: 4px 10px 4px 8px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-info h3 {
                font-size: 1.5rem;
            }
            
            .stat-icon {
                width: 45px;
                height: 45px;
                font-size: 1.2rem;
                margin-bottom: 12px;
            }
            
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
            
            .department-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .department-info {
                width: 100%;
            }
            
            .dept-progress {
                width: 100%;
            }
            
            .chat-info {
                width: 100%;
            }
            
            .chat-last-message {
                max-width: 100%;
            }
            
            .chat-meta {
                width: 100%;
                justify-content: space-between;
            }
            
            .help-card {
                padding: 30px 20px;
            }
            
            .help-icon-wrapper {
                width: 60px;
                height: 60px;
            }
            
            .help-icon-wrapper i {
                font-size: 1.6rem;
            }
            
            .certificate-eligible-banner {
                flex-direction: column;
                text-align: center;
                padding: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-card {
                padding: 20px;
            }
            
            .welcome-card .welcome-text h2 {
                font-size: 1.2rem;
            }
            
            .system-chips .chip {
                font-size: 0.65rem;
                padding: 3px 8px 3px 6px;
            }
            
            .card-header-custom {
                padding: 15px 18px;
            }
            
            .department-item {
                padding: 12px 18px;
            }
            
            .chat-item {
                padding: 12px 18px;
            }
            
            .stat-card {
                display: flex;
                align-items: center;
                gap: 15px;
            }
            
            .stat-icon {
                margin-bottom: 0;
            }
            
            .help-card {
                padding: 25px 15px;
            }
            
            .help-card .btn-support {
                padding: 10px 24px;
                font-size: 0.8rem;
            }
            
            .btn-certificate-download {
                padding: 12px 25px;
                font-size: 0.85rem;
                width: 100%;
                justify-content: center;
            }
            
            .certificate-eligible-banner .cert-icon {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation - UNCHANGED -->
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
                        <a class="nav-link active" href="dashboard.php">
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
                        <a class="nav-link" href="message.php">
                            <i class="fas fa-comments me-1"></i> Messages
                            <?php if ($unread_messages > 0): ?>
                                <span class="notification-badge"><?php echo $unread_messages; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- User Dropdown with Profile Picture -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button">
                            <div class="user-avatar d-inline-flex align-items-center justify-content-center me-1">
                                <?php if ($profile_pic_exists): ?>
                                    <img src="../assets/uploads/Students-profile/<?php echo $profile_pic; ?>" alt="<?php echo htmlspecialchars($_SESSION['user_name']); ?>">
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
    
    <div class="main-content">
        <div class="container">
            <!-- Welcome Card - ENHANCED with elegant System Info Chips -->
            <div class="welcome-card">
                <div class="welcome-content">
                    <div class="welcome-text">
                        <h2>👋 Welcome back, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>!</h2>
                        <p>Track your graduation clearance progress in real-time. Complete all requirements to get your clearance certificate.</p>
                        
                        <!-- System Information - Elegant Chips -->
                        <div class="system-chips">
                            <span class="chip">
                                <i class="fas fa-university"></i>
                                <?php echo htmlspecialchars($university_name); ?>
                            </span>
                            <?php if ($academic_year): ?>
                                <span class="chip">
                                    <i class="fas fa-calendar-alt"></i>
                                    <strong>AY:</strong> <?php echo htmlspecialchars($academic_year); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($graduation_year): ?>
                                <span class="chip">
                                    <i class="fas fa-graduation-cap"></i>
                                    <strong>Grad:</strong> <?php echo htmlspecialchars($graduation_year); ?>
                                </span>
                            <?php endif; ?>
                            <span class="chip">
                                <i class="fas fa-id-card"></i>
                                <strong>ID:</strong> <?php echo htmlspecialchars($_SESSION['student_id']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="student-badge">
                        <i class="fas fa-user-circle"></i>
                        <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Certificate Eligible Banner -->
            <?php if ($is_eligible_for_certificate): ?>
            <div class="certificate-eligible-banner">
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="cert-icon">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <div class="cert-text">
                        <h4>🎉 Congratulations! You're cleared for graduation!</h4>
                        <p>All clearance requirements have been completed. Download your graduation clearance certificate now.</p>
                    </div>
                </div>
                <div>
                    <a href="download-certificate.php" class="btn-certificate-download">
                        <i class="fas fa-download"></i> Download Certificate
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary-soft); color: var(--primary);">
                        <i class="fas fa-list-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($progress['total_items'] ?? 0); ?></h3>
                        <p>Total Requirements</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($progress['approved_count'] ?? 0); ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($progress['pending_count'] ?? 0); ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: var(--danger);">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($progress['rejected_count'] ?? 0); ?></h3>
                        <p>Rejected</p>
                    </div>
                </div>
            </div>
            
            <!-- Overall Progress -->
            <div class="progress-card">
                <div class="progress-label">
                    <span><i class="fas fa-chart-line me-2" style="color: var(--primary);"></i> Overall Clearance Progress</span>
                    <span class="fw-bold" style="color: var(--primary);"><?php echo $progress['completion_rate'] ?? 0; ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $progress['completion_rate'] ?? 0; ?>%"></div>
                </div>
                <div class="text-center mt-4">
                    <a href="clearance-status.php" class="btn-primary-custom">
                        View Detailed Status <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Departments Status -->
                <div class="col-lg-7">
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-building"></i> Department Clearance Status</h5>
                            <a href="clearance-status.php" class="btn-view-all">View All <i class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                        <?php if (empty($departments)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3" style="opacity: 0.3;"></i>
                                <p>No departments found</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($departments, 0, 4) as $dept): ?>
                            <div class="department-item">
                                <div class="department-info">
                                    <div class="dept-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $dept['department_code']; ?></small>
                                    </div>
                                </div>
                                <div class="dept-progress">
                                    <div class="progress">
                                        <div class="progress-bar" style="width: <?php echo $dept['total_items'] > 0 ? round(($dept['approved_count'] / $dept['total_items']) * 100) : 0; ?>%"></div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted"><?php echo $dept['approved_count']; ?>/<?php echo $dept['total_items']; ?> completed</small>
                                        <span class="status-badge status-<?php echo $dept['status']; ?>">
                                            <?php if ($dept['status'] == 'completed'): ?>
                                                <i class="fas fa-check-circle"></i> Completed
                                            <?php elseif ($dept['status'] == 'rejected'): ?>
                                                <i class="fas fa-times-circle"></i> Rejected
                                            <?php else: ?>
                                                <i class="fas fa-clock"></i> Pending
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Recent Messages -->
                    <div class="section-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-comments"></i> Recent Messages</h5>
                            <a href="message.php" class="btn-view-all">View All <i class="fas fa-arrow-right ms-1"></i></a>
                        </div>
                        <?php if (empty($recent_conversations)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-comments fa-3x mb-3" style="opacity: 0.3;"></i>
                                <p class="mb-2">No conversations yet</p>
                                <a href="message.php" class="btn btn-sm btn-primary-custom">Start a Conversation</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_conversations as $conv): ?>
                            <a href="message.php?id=<?php echo $conv['id']; ?>" class="chat-item">
                                <div class="chat-info">
                                    <div class="chat-icon">
                                        <i class="fas fa-comment-dots"></i>
                                    </div>
                                    <div class="chat-details">
                                        <strong><?php echo htmlspecialchars($conv['department_name']); ?></strong>
                                        <div class="chat-last-message">
                                            <?php echo htmlspecialchars(substr($conv['last_message'] ?? 'No messages yet', 0, 60)); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="chat-meta">
                                    <span class="chat-time"><?php echo date('M d', strtotime($conv['updated_at'])); ?></span>
                                    <?php 
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE conversation_id = ? AND receiver_id = ? AND is_read = 0");
                                    $stmt->execute([$conv['id'], $student_id]);
                                    $unread = $stmt->fetch()['count'];
                                    ?>
                                    <?php if ($unread > 0): ?>
                                        <span class="unread-indicator"><?php echo $unread; ?> new</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-5">
                    <!-- Help & Support Card -->
                    <div class="help-card">
                        <div class="help-icon-wrapper">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h5>Need Assistance?</h5>
                        <p>Having trouble with your clearance process? Our support team is here to help you 24/7.</p>
                        <a href="help-support.php" class="btn-support">
                            <i class="fas fa-envelope"></i> Contact Support
                        </a>
                        <div class="support-hours">
                            <i class="fas fa-clock"></i>
                            <span>Available 24/7</span>
                            <span class="mx-1">•</span>
                            <i class="fas fa-reply-all"></i>
                            <span>Response within 24hrs</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>
</body>
</html>