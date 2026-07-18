<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

// ============================================================
// DEBUGGING - Enable error reporting
// ============================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Check if user is logged in
if (!isLoggedIn() || isAdmin()) {
    redirect('../auth/login.php');
}

$student_id = $_SESSION['user_id'];
$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

if ($department_id == 0) {
    redirect('clearance-status.php');
}

// ============================================================
// SECURITY CHECK - PREVENT ACCESS TO LOCKED DEPARTMENTS
// ============================================================
// Get all active departments in workflow order
$stmt = $pdo->prepare("
    SELECT id, department_name 
    FROM departments 
    WHERE is_active = 1 
    ORDER BY clearance_order ASC
");
$stmt->execute();
$all_departments = $stmt->fetchAll();
$workflow_order = array_column($all_departments, 'id');

// Check if the requested department exists in the workflow
if (!in_array($department_id, $workflow_order)) {
    $_SESSION['error'] = "Department not found.";
    redirect('clearance-status.php');
}

// Get the index of the requested department in the workflow
$dept_index = array_search($department_id, $workflow_order);

// Get the status of all departments for this student
$statuses = [];
foreach ($workflow_order as $dept_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT ci.id) as total_items,
            SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending_count
        FROM clearance_items ci
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
        WHERE ci.department_id = ?
    ");
    $stmt->execute([$student_id, $dept_id]);
    $data = $stmt->fetch();
    
    $total = (int)$data['total_items'];
    $approved = (int)$data['approved_count'];
    $rejected = (int)$data['rejected_count'];
    $pending = (int)$data['pending_count'];
    
    if ($total == 0) {
        $status = 'no_items';
    } elseif ($rejected > 0) {
        $status = 'rejected';
    } elseif ($approved == $total) {
        $status = 'completed';
    } elseif ($pending > 0 || $total > 0) {
        $status = 'pending';
    } else {
        $status = 'pending';
    }
    
    $statuses[$dept_id] = $status;
}

// Determine if the requested department is locked
$requested_status = $statuses[$department_id] ?? 'pending';

// Check if all previous departments are completed
$all_prev_completed = true;
for ($i = 0; $i < $dept_index; $i++) {
    $prev_dept_id = $workflow_order[$i];
    if ($statuses[$prev_dept_id] != 'completed') {
        $all_prev_completed = false;
        break;
    }
}

// LOCKED if: NOT completed AND NOT rejected AND NOT the first department AND previous departments not all completed
$is_locked = false;

if ($requested_status == 'completed' || $requested_status == 'rejected') {
    // Completed or rejected departments are always accessible
    $is_locked = false;
} elseif ($dept_index == 0) {
    // First department is always accessible
    $is_locked = false;
} elseif (!$all_prev_completed) {
    // Previous departments not all completed - LOCKED
    $is_locked = true;
}

// If locked, redirect with message
if ($is_locked) {
    $_SESSION['error'] = "This department is currently locked. Complete the previous department(s) in the clearance workflow before accessing this section.";
    redirect('clearance-status.php');
}

// Get system settings for branding
$university_name = getUniversityName($pdo);
$academic_year = getAcademicYear($pdo);
$graduation_year = getGraduationYear($pdo);

// ============================================================
// 1. GET DEPARTMENT DETAILS
// ============================================================
$stmt = $pdo->prepare("
    SELECT id, department_name, department_code, description 
    FROM departments 
    WHERE id = ? AND is_active = 1
");
$stmt->execute([$department_id]);
$department = $stmt->fetch();

if (!$department) {
    redirect('clearance-status.php');
}

// ============================================================
// 2. GET CLEARANCE ITEMS FOR THIS DEPARTMENT
// ============================================================
$stmt = $pdo->prepare("
    SELECT id, item_name, description, requires_document, is_mandatory, sort_order
    FROM clearance_items 
    WHERE department_id = ? 
    ORDER BY sort_order ASC, id ASC
");
$stmt->execute([$department_id]);
$clearance_items = $stmt->fetchAll();

// ============================================================
// 3. GET STUDENT'S CLEARANCE STATUS FOR THESE ITEMS
// ============================================================
$item_statuses = [];
$total_items = count($clearance_items);
$approved_count = 0;
$pending_count = 0;
$rejected_count = 0;
$not_started_count = 0;

foreach ($clearance_items as $item) {
    $stmt = $pdo->prepare("
        SELECT status, remarks, document_path, reviewed_at, reviewed_by
        FROM student_clearance 
        WHERE student_id = ? AND clearance_item_id = ?
    ");
    $stmt->execute([$student_id, $item['id']]);
    $status = $stmt->fetch();
    
    if ($status) {
        $item_statuses[$item['id']] = $status;
        if ($status['status'] == 'approved') $approved_count++;
        elseif ($status['status'] == 'pending') $pending_count++;
        elseif ($status['status'] == 'rejected') $rejected_count++;
    } else {
        $item_statuses[$item['id']] = ['status' => 'not_started', 'remarks' => null, 'document_path' => null, 'reviewed_at' => null];
        $not_started_count++;
    }
}

$completion_rate = $total_items > 0 ? round(($approved_count / $total_items) * 100, 1) : 0;

// Determine department status
if ($total_items == 0) {
    $department_status = 'no_items';
} elseif ($rejected_count > 0) {
    $department_status = 'rejected';
} elseif ($approved_count == $total_items && $total_items > 0) {
    $department_status = 'completed';
} elseif ($pending_count > 0 || $not_started_count > 0) {
    $department_status = 'pending';
} else {
    $department_status = 'pending';
}

// ============================================================
// 4. GET WORKFLOW POSITION
// ============================================================
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'clearance_workflow'");
$workflow_setting = $stmt->fetch();
$workflow_order = [];

if ($workflow_setting && !empty($workflow_setting['setting_value'])) {
    $workflow_order = explode(',', $workflow_setting['setting_value']);
} else {
    $stmt = $pdo->query("SELECT id FROM departments WHERE is_active = 1 ORDER BY clearance_order ASC");
    while ($row = $stmt->fetch()) {
        $workflow_order[] = $row['id'];
    }
}

$position = array_search($department_id, $workflow_order);
$step_number = $position !== false ? $position + 1 : '—';
$total_steps = count($workflow_order);

// ============================================================
// 5. GET USER PROFILE PICTURE
// ============================================================
$stmt = $pdo->prepare("SELECT profile_pic, full_name FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();
$profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
$profile_pic_exists = !empty($profile_pic) && $profile_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic);

$unread_messages = getUnreadMessageCount($pdo, $student_id);

$page_title = 'Clearance Details - ' . htmlspecialchars($department['department_name']);
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
            --primary-soft: rgba(128, 0, 32, 0.08);
            --gray-100: #f4f6f9;
            --gray-200: #e8ecef;
            --gray-400: #b0b8c4;
            --gray-500: #8a94a6;
            --gray-600: #6c7683;
            --gray-700: #4a5360;
            --gray-800: #2d3440;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gold: #c9a84c;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
            font-size: 15px;
            line-height: 1.6;
            color: var(--gray-800);
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 12px 0;
            box-shadow: 0 2px 20px rgba(128, 0, 32, 0.15);
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
            transition: all 0.3s;
        }
        
        .navbar-toggler:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: white;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
            outline: none;
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
        
        .main-content {
            padding: 24px 30px;
            min-height: calc(100vh - 70px);
        }
        
        .system-info-banner {
            background: white;
            border-radius: 14px;
            padding: 12px 22px;
            margin-bottom: 18px;
            border: 1px solid rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.02);
        }
        
        .system-info-banner .info-text .uni-name {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
            line-height: 1.3;
        }
        
        .system-info-banner .info-text .details {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 1px;
        }
        
        .system-info-banner .info-text .details .detail-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            color: var(--gray-600);
        }
        
        .system-info-banner .info-text .details .detail-item i {
            color: var(--primary);
            font-size: 0.7rem;
        }
        
        .system-info-banner .info-text .details .detail-item strong {
            color: var(--gray-700);
            font-weight: 600;
        }
        
        .system-info-banner .user-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            background: var(--gray-100);
            padding: 4px 12px 4px 8px;
            border-radius: 30px;
        }
        
        .system-info-banner .user-badge .avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: var(--primary-soft);
            color: var(--primary);
            font-weight: 600;
            font-size: 0.6rem;
        }
        
        .system-info-banner .user-badge .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .system-info-banner .user-badge span {
            font-size: 0.75rem;
            color: var(--gray-700);
            font-weight: 500;
        }
        
        .btn-back {
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-700);
            padding: 7px 16px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 18px;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateX(-3px);
        }
        
        .page-header {
            margin-bottom: 24px;
        }
        
        .page-header h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
            letter-spacing: -0.5px;
        }
        
        .page-header h2 i {
            color: var(--primary);
            font-size: 1.4rem;
            margin-right: 8px;
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 2px 0 0 0;
            font-size: 0.9rem;
        }
        
        .department-header-card {
            background: white;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 24px;
            border: 1px solid rgba(0,0,0,0.04);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .department-header-card .dept-info h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }
        
        .department-header-card .dept-info p {
            color: var(--gray-600);
            font-size: 0.85rem;
            margin: 4px 0 0;
        }
        
        .department-header-card .step-badge {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid rgba(0,0,0,0.04);
            text-align: center;
        }
        
        .stat-card .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            line-height: 1.2;
        }
        
        .stat-card .stat-label {
            color: var(--gray-600);
            font-size: 0.7rem;
            font-weight: 500;
            margin-top: 2px;
        }
        
        .progress-section {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .progress-section .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .progress-section .progress {
            height: 6px;
            border-radius: 8px;
            background: var(--gray-200);
        }
        
        .progress-section .progress-bar {
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 8px;
        }
        
        .items-card {
            background: white;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .items-card .card-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        
        .items-card .card-header h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .items-card .card-header h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .clearance-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 24px;
            border-bottom: 1px solid var(--gray-100);
            transition: all 0.3s;
        }
        
        .clearance-item-row:last-child {
            border-bottom: none;
        }
        
        .clearance-item-row:hover {
            background: var(--gray-50);
        }
        
        .clearance-item-row .item-info {
            flex: 1;
        }
        
        .clearance-item-row .item-info .item-name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-800);
        }
        
        .clearance-item-row .item-info .item-description {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-top: 2px;
        }
        
        .clearance-item-row .item-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .clearance-item-row .item-meta .requires-doc {
            font-size: 0.6rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: 2px 10px;
            border-radius: 10px;
        }
        
        .clearance-item-row .item-status {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 50px;
        }
        
        .clearance-item-row .item-status.approved {
            background: rgba(16,185,129,0.1);
            color: var(--success);
        }
        
        .clearance-item-row .item-status.pending {
            background: rgba(245,158,11,0.1);
            color: var(--warning);
        }
        
        .clearance-item-row .item-status.rejected {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
        }
        
        .clearance-item-row .item-status.not-started {
            background: rgba(108,118,131,0.08);
            color: var(--gray-500);
        }
        
        .btn-upload {
            background: var(--primary);
            color: white;
            border: none;
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-upload:hover {
            background: var(--primary-dark);
            color: white;
        }
        
        .btn-upload:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .btn-upload i {
            margin-right: 4px;
        }
        
        /* ============================================================
           VIEW DOCUMENT BUTTON - Always visible when document exists
           ============================================================ */
        .btn-view-doc {
            background: var(--success);
            color: white;
            border: none;
            padding: 4px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-view-doc:hover {
            background: #059669;
            color: white;
        }
        
        .btn-view-doc i {
            margin-right: 4px;
        }
        
        /* ============================================================
           DELETE BUTTON - HIDDEN FOR APPROVED ITEMS
           ============================================================ */
        .btn-delete {
            background: var(--danger);
            color: white;
            border: none;
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 0.65rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            display: none;
        }
        
        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }
        
        .btn-delete i {
            margin-right: 4px;
        }
        
        /* Show delete button only when NOT approved */
        .clearance-item-row:not(.status-approved) .btn-delete {
            display: inline-flex;
        }
        
        /* Hide delete button for approved items */
        .clearance-item-row.status-approved .btn-delete {
            display: none !important;
        }
        
        .rejection-reason {
            font-size: 0.75rem;
            color: var(--danger);
            background: rgba(239,68,68,0.05);
            padding: 6px 12px;
            border-radius: 8px;
            margin-top: 4px;
        }
        
        .approved-date {
            font-size: 0.7rem;
            color: var(--gray-500);
            margin-top: 2px;
        }
        
        .lock-icon {
            color: var(--gray-400);
            font-size: 0.8rem;
        }
        
        .document-info {
            font-size: 0.65rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: 2px 10px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 16px; }
            
            .system-info-banner {
                flex-direction: column;
                align-items: flex-start;
                padding: 12px 16px;
            }
            .system-info-banner .info-text .details {
                flex-direction: column;
                gap: 2px;
            }
            
            .navbar-collapse {
                background: linear-gradient(135deg, var(--primary), var(--primary-dark));
                padding: 15px;
                border-radius: 12px;
                margin-top: 10px;
            }
            .navbar-nav .nav-link {
                padding: 10px 15px;
                margin: 2px 0;
            }
            
            .department-header-card {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            
            .stat-card .stat-number {
                font-size: 1.2rem;
            }
            
            .clearance-item-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            
            .clearance-item-row .item-meta {
                width: 100%;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
            
            .btn-upload, .btn-view-doc, .btn-delete {
                width: auto;
                text-align: center;
                justify-content: center;
                padding: 4px 12px;
            }
            
            .page-header h2 { font-size: 1.3rem; }
        }
        
        @media (max-width: 576px) {
            .main-content { padding: 12px; }
            .stats-grid { grid-template-columns: 1fr; }
            .page-header h2 { font-size: 1.15rem; }
            .page-header h2 i { font-size: 1.1rem; }
            .department-header-card { padding: 16px 18px; }
            .department-header-card .dept-info h3 { font-size: 1.1rem; }
            .clearance-item-row { padding: 12px 16px; }
            .items-card .card-header { padding: 12px 16px; }
            
            .clearance-item-row .item-meta {
                gap: 8px;
            }
            
            .btn-upload, .btn-view-doc, .btn-delete {
                font-size: 0.65rem;
                padding: 4px 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
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
                        <a class="nav-link active" href="clearance-status.php">
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
            <!-- System Info Banner -->
            <div class="system-info-banner">
                <div class="info-text">
                    <span class="uni-name"><?php echo htmlspecialchars($university_name); ?></span>
                    <div class="details">
                        <?php if ($academic_year): ?>
                            <span class="detail-item">
                                <i class="fas fa-calendar-alt"></i>
                                <strong>Academic Year:</strong> <?php echo htmlspecialchars($academic_year); ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($graduation_year): ?>
                            <span class="detail-item">
                                <i class="fas fa-graduation-cap"></i>
                                <strong>Graduation:</strong> <?php echo htmlspecialchars($graduation_year); ?>
                            </span>
                        <?php endif; ?>
                        <span class="detail-item">
                            <i class="fas fa-id-card"></i>
                            <strong>ID:</strong> <?php echo htmlspecialchars($_SESSION['student_id']); ?>
                        </span>
                    </div>
                </div>
                <div class="user-badge">
                    <div class="avatar">
                        <?php if ($profile_pic_exists): ?>
                            <img src="../assets/uploads/Students-profile/<?php echo $profile_pic; ?>" alt="Profile">
                        <?php else: ?>
                            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                </div>
            </div>
            
            <!-- Back Button -->
            <a href="clearance-status.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Clearance Status
            </a>
            
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-clipboard-list"></i> Clearance Details</h2>
                <p>Review and manage your clearance requirements for this department</p>
            </div>
            
            <!-- Department Header -->
            <div class="department-header-card">
                <div class="dept-info">
                    <h3><?php echo htmlspecialchars($department['department_name']); ?></h3>
                    <p>
                        <i class="fas fa-code me-1"></i> <?php echo htmlspecialchars($department['department_code']); ?>
                        <?php if ($department['description']): ?>
                            <span class="mx-2">•</span> <?php echo htmlspecialchars($department['description']); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="step-badge">
                    <i class="fas fa-flag me-1"></i> Step <?php echo $step_number; ?> of <?php echo $total_steps; ?>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_items; ?></div>
                    <div class="stat-label">📋 Total Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--success);"><?php echo $approved_count; ?></div>
                    <div class="stat-label">✅ Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--warning);"><?php echo $pending_count + $not_started_count; ?></div>
                    <div class="stat-label">⏳ Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--danger);"><?php echo $rejected_count; ?></div>
                    <div class="stat-label">❌ Rejected</div>
                </div>
            </div>
            
            <!-- Progress -->
            <div class="progress-section">
                <div class="progress-label">
                    <span>Department Progress</span>
                    <span class="fw-bold"><?php echo $completion_rate; ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $completion_rate; ?>%"></div>
                </div>
            </div>
            
            <!-- Clearance Items -->
            <div class="items-card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> Clearance Requirements</h5>
                </div>
                <?php if (empty($clearance_items)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x mb-3" style="color: var(--gray-300);"></i>
                        <p class="text-muted">No clearance requirements have been configured for this department.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($clearance_items as $item): 
                        $status = $item_statuses[$item['id']] ?? ['status' => 'not_started'];
                        $status_text = ucfirst($status['status']);
                        if ($status['status'] == 'not_started') $status_text = 'Not Started';
                        $status_class = $status['status'];
                        
                        // Determine if upload is allowed
                        $can_upload = ($status['status'] == 'pending' || $status['status'] == 'not_started' || $status['status'] == 'rejected');
                        
                        // Determine if delete is allowed (NOT approved)
                        $can_delete = ($status['status'] != 'approved');
                        
                        // Check if document exists
                        $has_document = !empty($status['document_path']);
                        
                        // Row class for styling
                        $row_class = ($status['status'] == 'approved') ? 'status-approved' : '';
                    ?>
                    <div class="clearance-item-row <?php echo $row_class; ?>">
                        <div class="item-info">
                            <div class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                            <?php if ($item['description']): ?>
                                <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                            <?php endif; ?>
                            <?php if ($status['status'] == 'rejected' && $status['remarks']): ?>
                                <div class="rejection-reason">
                                    <i class="fas fa-exclamation-circle me-1"></i> 
                                    Rejected: <?php echo htmlspecialchars($status['remarks']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($status['status'] == 'approved' && $status['reviewed_at']): ?>
                                <div class="approved-date">
                                    <i class="fas fa-check-circle me-1" style="color: var(--success);"></i>
                                    Approved on <?php echo date('M d, Y h:i A', strtotime($status['reviewed_at'])); ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($has_document): ?>
                                <div class="document-info">
                                    <i class="fas fa-file"></i> 
                                    Document: <?php echo basename($status['document_path']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="item-meta">
                            <?php if ($item['requires_document']): ?>
                                <span class="requires-doc"><i class="fas fa-file-upload me-1"></i> Document Required</span>
                            <?php endif; ?>
                            <span class="item-status <?php echo $status_class; ?>">
                                <?php if ($status['status'] == 'approved'): ?>
                                    <i class="fas fa-check-circle"></i> <?php echo $status_text; ?>
                                <?php elseif ($status['status'] == 'rejected'): ?>
                                    <i class="fas fa-times-circle"></i> <?php echo $status_text; ?>
                                <?php elseif ($status['status'] == 'pending'): ?>
                                    <i class="fas fa-clock"></i> <?php echo $status_text; ?>
                                <?php else: ?>
                                    <i class="fas fa-circle"></i> <?php echo $status_text; ?>
                                <?php endif; ?>
                            </span>
                            
                            <?php if ($has_document): ?>
                                <!-- View Document - Always visible when document exists, regardless of status -->
                                <a href="../assets/uploads/<?php echo $status['document_path']; ?>" class="btn-view-doc" target="_blank">
                                    <i class="fas fa-eye"></i> View Document
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($can_upload && $item['requires_document']): ?>
                                <a href="upload-document.php?item_id=<?php echo $item['id']; ?>&dept_id=<?php echo $department_id; ?>" class="btn-upload">
                                    <i class="fas fa-upload"></i> 
                                    <?php echo $has_document ? 'Replace' : 'Upload'; ?>
                                </a>
                            <?php endif; ?>
                            
                            <!-- Delete Button - Only visible when NOT approved AND document exists -->
                            <?php if ($can_delete && $has_document): ?>
                                <a href="delete-document.php?item_id=<?php echo $item['id']; ?>&dept_id=<?php echo $department_id; ?>" 
                                   class="btn-delete" 
                                   onclick="return confirm('Are you sure you want to delete this document? This action cannot be undone.');">
                                    <i class="fas fa-trash-alt"></i> Delete
                                </a>
                            <?php endif; ?>
                            
                            <!-- Locked indicator for approved items -->
                            <?php if ($status['status'] == 'approved'): ?>
                                <span class="lock-icon" title="Item is approved and locked">
                                    <i class="fas fa-lock"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>