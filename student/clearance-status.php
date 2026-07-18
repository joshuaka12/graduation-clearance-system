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
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// If admin tries to access, redirect to admin dashboard
if (isAdmin()) {
    redirect('../admin/dashboard.php');
}

$student_id = $_SESSION['user_id'];

// Get system settings for branding
$university_name = getUniversityName($pdo);
$academic_year = getAcademicYear($pdo);
$graduation_year = getGraduationYear($pdo);

// ============================================================
// 1. GET ALL ACTIVE DEPARTMENTS
// ============================================================
$stmt = $pdo->prepare("
    SELECT id, department_name, department_code, description, clearance_order 
    FROM departments 
    WHERE is_active = 1 
    ORDER BY clearance_order ASC
");
$stmt->execute();
$all_departments = $stmt->fetchAll();

// ============================================================
// 2. GET CLEARANCE WORKFLOW ORDER FROM SETTINGS
// ============================================================
$workflow_order = [];
$stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'clearance_workflow'");
$workflow_setting = $stmt->fetch();

if ($workflow_setting && !empty($workflow_setting['setting_value'])) {
    $workflow_order = explode(',', $workflow_setting['setting_value']);
    $active_dept_ids = array_column($all_departments, 'id');
    $workflow_order = array_intersect($workflow_order, $active_dept_ids);
}

if (empty($workflow_order)) {
    $workflow_order = array_column($all_departments, 'id');
}

// ============================================================
// 3. GET CLEARANCE STATUS FOR EACH DEPARTMENT
// ============================================================
$departments = [];
$completed_departments = 0;
$has_rejection = false;

foreach ($workflow_order as $index => $dept_id) {
    $dept_info = null;
    foreach ($all_departments as $d) {
        if ($d['id'] == $dept_id) {
            $dept_info = $d;
            break;
        }
    }
    
    if (!$dept_info) continue;
    
    // ============================================================
    // GET STATUS DIRECTLY FROM student_clearance
    // ============================================================
    $stmt = $pdo->prepare("
        SELECT 
            sc.status,
            sc.remarks,
            sc.reviewed_at,
            COUNT(DISTINCT ci.id) as total_items,
            SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count
        FROM clearance_items ci
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
        WHERE ci.department_id = ?
    ");
    $stmt->execute([$student_id, $dept_id]);
    $result = $stmt->fetch();
    
    // Get the actual status from database
    $actual_status = $result['status'] ?? 'pending';
    $remarks = $result['remarks'] ?? null;
    $reviewed_at = $result['reviewed_at'] ?? null;
    $total_items = (int)($result['total_items'] ?? 0);
    $approved_count = (int)($result['approved_count'] ?? 0);
    
    // Determine display status - use the actual status from database
    $display_status = $actual_status;
    $is_approved = ($actual_status == 'approved');
    $is_rejected = ($actual_status == 'rejected');
    $is_pending = ($actual_status == 'pending');
    
    if ($is_approved) {
        $completed_departments++;
    }
    if ($is_rejected) {
        $has_rejection = true;
    }
    
    // Get clearance items with their individual statuses
    $stmt = $pdo->prepare("
        SELECT 
            ci.id,
            ci.item_name,
            ci.description,
            ci.requires_document,
            sc.status as item_status,
            sc.remarks as item_remarks,
            sc.reviewed_at as item_reviewed_at
        FROM clearance_items ci
        LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
        WHERE ci.department_id = ?
        ORDER BY ci.sort_order ASC, ci.id ASC
    ");
    $stmt->execute([$student_id, $dept_id]);
    $clearance_items = $stmt->fetchAll();
    
    // Determine if department should be locked or active
    $is_locked = false;
    $is_active = false;
    
    if ($is_approved || $is_rejected) {
        // Approved or rejected departments are NEVER locked
        $is_locked = false;
        $is_active = false;
    } else {
        // Check if all previous departments are approved
        $all_prev_approved = true;
        for ($i = 0; $i < $index; $i++) {
            // Get status of previous department
            $stmt_prev = $pdo->prepare("
                SELECT sc.status
                FROM clearance_items ci
                LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
                WHERE ci.department_id = ?
                LIMIT 1
            ");
            $stmt_prev->execute([$student_id, $workflow_order[$i]]);
            $prev_status = $stmt_prev->fetchColumn();
            
            if ($prev_status != 'approved') {
                $all_prev_approved = false;
                break;
            }
        }
        
        if ($all_prev_approved) {
            // This is the next pending department - make it active
            $is_active = true;
            $is_locked = false;
        } else {
            // Some previous department is not approved - lock this one
            $is_locked = true;
            $is_active = false;
        }
    }
    
    $departments[] = [
        'id' => $dept_info['id'],
        'department_name' => $dept_info['department_name'],
        'department_code' => $dept_info['department_code'],
        'description' => $dept_info['description'] ?? '',
        'clearance_order' => $dept_info['clearance_order'] ?? 0,
        'index' => $index,
        'actual_status' => $actual_status,
        'display_status' => $display_status,
        'is_approved' => $is_approved,
        'is_rejected' => $is_rejected,
        'is_pending' => $is_pending,
        'is_locked' => $is_locked,
        'is_active' => $is_active,
        'remarks' => $remarks,
        'reviewed_at' => $reviewed_at,
        'total_items' => $total_items,
        'approved_count' => $approved_count,
        'clearance_items' => $clearance_items
    ];
}

// ============================================================
// 4. CALCULATE OVERALL PROGRESS
// ============================================================
$total_departments = count($departments);
$completion_rate = $total_departments > 0 ? round(($completed_departments / $total_departments) * 100, 1) : 0;
$is_fully_cleared = ($completed_departments == $total_departments && $total_departments > 0);

// ============================================================
// 5. GET CERTIFICATE
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM clearance_certificates WHERE student_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$student_id]);
$certificate = $stmt->fetch();

// ============================================================
// 6. GET USER PROFILE PICTURE
// ============================================================
$stmt = $pdo->prepare("SELECT profile_pic, full_name FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();
$profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
$profile_pic_exists = !empty($profile_pic) && $profile_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic);

$unread_messages = getUnreadMessageCount($pdo, $student_id);

$page_title = 'Clearance Status';
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
            --gray-900: #1a1e24;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gold: #c9a84c;
            --gold-dark: #a8883c;
            --gold-light: #f0e6c8;
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
        
        .workflow-progress {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .workflow-steps {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            padding: 0 8px;
        }
        
        .workflow-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 30px;
            right: 30px;
            height: 2px;
            background: var(--gray-200);
            z-index: 0;
        }
        
        .workflow-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
            text-align: center;
            min-width: 50px;
        }
        
        .workflow-step .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.7rem;
            border: 2.5px solid var(--gray-200);
            background: white;
            transition: all 0.3s;
            margin-bottom: 6px;
            cursor: default;
        }
        
        .workflow-step .step-circle.completed {
            border-color: var(--success);
            background: var(--success);
            color: white;
        }
        
        .workflow-step .step-circle.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
            box-shadow: 0 0 0 5px var(--primary-soft);
            animation: pulse 2s infinite;
        }
        
        .workflow-step .step-circle.rejected {
            border-color: var(--danger);
            background: var(--danger);
            color: white;
        }
        
        .workflow-step .step-circle.locked {
            border-color: var(--gray-200);
            background: var(--gray-100);
            color: var(--gray-500);
        }
        
        .workflow-step .step-circle.no-items {
            border-color: var(--gray-300);
            background: var(--gray-50);
            color: var(--gray-400);
            border-style: dashed;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(128, 0, 32, 0.3); }
            70% { box-shadow: 0 0 0 8px rgba(128, 0, 32, 0); }
            100% { box-shadow: 0 0 0 0 rgba(128, 0, 32, 0); }
        }
        
        .workflow-step .step-label {
            font-size: 0.55rem;
            font-weight: 500;
            color: var(--gray-600);
            max-width: 70px;
            word-wrap: break-word;
            line-height: 1.2;
        }
        
        .workflow-step .step-label.completed {
            color: var(--success);
        }
        .workflow-step .step-label.active {
            color: var(--primary);
        }
        .workflow-step .step-label.rejected {
            color: var(--danger);
        }
        .workflow-step .step-label.locked {
            color: var(--gray-400);
        }
        .workflow-step .step-label.no-items {
            color: var(--gray-500);
        }
        
        .workflow-step .step-status-icon {
            font-size: 0.5rem;
            margin-top: 3px;
        }
        
        .workflow-step .step-status-icon.completed { color: var(--success); }
        .workflow-step .step-status-icon.active { color: var(--primary); }
        .workflow-step .step-status-icon.rejected { color: var(--danger); }
        .workflow-step .step-status-icon.locked { color: var(--gray-400); }
        .workflow-step .step-status-icon.no-items { color: var(--gray-500); }
        
        .stats-summary {
            background: white;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 24px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .summary-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            line-height: 1.2;
        }
        
        .summary-label {
            color: var(--gray-600);
            font-size: 0.75rem;
            font-weight: 500;
            margin-top: 2px;
        }
        
        .certificate-eligible-banner {
            background: linear-gradient(135deg, #c9a84c 0%, #f0e6c8 100%);
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 24px;
            border: 2px solid #d4b86a;
            box-shadow: 0 4px 16px rgba(201,168,76,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .certificate-eligible-banner .cert-icon-wrapper {
            width: 48px;
            height: 48px;
            background: rgba(128,0,32,0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .certificate-eligible-banner .cert-icon-wrapper i {
            font-size: 1.4rem;
            color: var(--primary);
        }
        
        .certificate-eligible-banner .cert-text h4 {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 3px;
            font-size: 1.05rem;
        }
        
        .certificate-eligible-banner .cert-text p {
            color: var(--gray-700);
            margin: 0;
            font-size: 0.85rem;
        }
        
        .btn-certificate-download {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 3px 12px rgba(128,0,32,0.25);
        }
        
        .btn-certificate-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128,0,32,0.35);
            color: white;
        }
        
        .btn-certificate-print {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 10px 28px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-certificate-print:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .certificate-info {
            background: white;
            border-radius: 14px;
            padding: 14px 20px;
            margin-bottom: 14px;
            border: 1px solid var(--gold-light);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .certificate-info .label {
            font-size: 0.7rem;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .certificate-info .value {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--gray-800);
            font-family: monospace;
            letter-spacing: 0.5px;
        }
        
        .certificate-info .badge-verified {
            background: rgba(16,185,129,0.08);
            color: var(--success);
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .progress-alert {
            border-radius: 14px;
            padding: 16px 22px;
            margin-bottom: 24px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .progress-alert.warning {
            background: var(--warning);
        }
        
        .progress-alert.danger {
            background: var(--danger);
        }
        
        .progress-alert i {
            font-size: 1.4rem;
        }
        
        .progress-alert h5 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
        }
        
        .progress-alert p {
            margin: 2px 0 0;
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .btn-progress {
            background: white;
            color: var(--warning);
            border: none;
            padding: 8px 22px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-progress:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            color: var(--warning);
        }
        
        .dept-card {
            background: white;
            border-radius: 16px;
            margin-bottom: 16px;
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.3s;
            overflow: hidden;
            display: block;
            color: inherit;
        }
        
        .dept-card.locked {
            opacity: 0.7;
            border-color: var(--gray-200);
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .dept-card.locked .dept-card-header {
            background: var(--gray-50);
        }
        
        .dept-card.locked .view-details-hint {
            display: none;
        }
        
        .dept-card:not(.locked) {
            cursor: pointer;
            text-decoration: none;
        }
        
        .dept-card:not(.locked):hover {
            box-shadow: 0 8px 30px rgba(128, 0, 32, 0.12);
            transform: translateY(-3px);
            border-color: var(--primary-soft);
            text-decoration: none;
            color: inherit;
        }
        
        .dept-card.active:not(.locked) {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-soft);
        }
        
        .dept-card.active:not(.locked):hover {
            box-shadow: 0 8px 30px rgba(128, 0, 32, 0.2), 0 0 0 2px var(--primary-soft);
        }
        
        .dept-card.rejected:not(.locked) {
            border-color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .dept-card.completed:not(.locked) {
            border-color: var(--success);
            border-left: 4px solid var(--success);
        }
        
        .dept-card.no-items:not(.locked) {
            border-color: var(--gray-300);
            border-style: dashed;
        }
        
        .dept-card-header {
            padding: 16px 22px;
            background: white;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .dept-info {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
        }
        
        .dept-icon {
            width: 42px;
            height: 42px;
            background: var(--primary-soft);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .dept-title h4 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .dept-title p {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin: 1px 0 0;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }
        
        .status-completed { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        .status-locked { background: rgba(108,118,131,0.08); color: var(--gray-600); }
        .status-no-items { background: rgba(108,118,131,0.05); color: var(--gray-500); }
        
        .dept-card-body {
            padding: 16px 22px;
        }
        
        .dept-card-body .text-muted {
            font-size: 0.8rem;
        }
        
        .progress {
            height: 6px;
            border-radius: 8px;
            background: var(--gray-200);
        }
        
        .progress-bar {
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 8px;
        }
        
        .stats-row {
            display: flex;
            gap: 16px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--gray-200);
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
            min-width: 60px;
        }
        
        .stat-value {
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        
        .stat-label {
            font-size: 0.6rem;
            color: var(--gray-600);
            font-weight: 500;
        }
        
        .clearance-items-list {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--gray-200);
        }
        
        .clearance-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.85rem;
        }
        
        .clearance-item:last-child {
            border-bottom: none;
        }
        
        .clearance-item .item-name {
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .clearance-item .item-status {
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .clearance-item .item-status.approved { color: var(--success); }
        .clearance-item .item-status.pending { color: var(--warning); }
        .clearance-item .item-status.rejected { color: var(--danger); }
        .clearance-item .item-status.not-started { color: var(--gray-500); }
        
        .clearance-item .requires-doc {
            font-size: 0.6rem;
            color: var(--gray-500);
            background: var(--gray-100);
            padding: 1px 8px;
            border-radius: 10px;
        }
        
        .clearance-item .item-remarks {
            font-size: 0.7rem;
            color: var(--gray-600);
            display: block;
            margin-top: 2px;
            padding-left: 4px;
            border-left: 2px solid var(--gray-200);
            padding-left: 8px;
        }
        
        .clearance-item .item-remarks.rejected-remark {
            border-left-color: var(--danger);
            color: var(--danger);
        }
        
        .clearance-item .item-remarks.approved-remark {
            border-left-color: var(--success);
            color: var(--success);
        }
        
        .no-items-message {
            color: var(--gray-500);
            font-size: 0.8rem;
            padding: 10px 0;
            text-align: center;
        }
        
        .view-details-hint {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            color: var(--primary);
            font-weight: 500;
            margin-top: 8px;
            transition: all 0.3s;
        }
        
        .dept-card:not(.locked):hover .view-details-hint {
            transform: translateX(3px);
        }
        
        .overall-progress {
            margin-bottom: 24px;
        }
        
        .overall-progress .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .overall-progress .progress {
            height: 6px;
        }
        
        .item-review-date {
            font-size: 0.6rem;
            color: var(--gray-500);
        }
        
        .locked-message {
            color: var(--gray-500);
            font-size: 0.8rem;
            padding: 8px 0;
            border-top: 1px dashed var(--gray-200);
            margin-top: 8px;
        }
        
        @media (max-width: 992px) {
            .workflow-steps {
                overflow-x: auto;
                padding-bottom: 8px;
                gap: 12px;
            }
            .workflow-steps::before {
                display: none;
            }
            .workflow-step {
                min-width: 60px;
                flex: 0 0 auto;
            }
        }
        
        @media (max-width: 768px) {
            .main-content { padding: 16px 16px; }
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
            .dept-card-header { 
                flex-direction: column; 
                align-items: flex-start; 
            }
            .dept-info { width: 100%; }
            .stats-row { flex-wrap: wrap; gap: 8px; }
            .stat-item { min-width: calc(50% - 8px); }
            .page-header { flex-direction: column; align-items: flex-start !important; }
            .page-header h2 { font-size: 1.3rem; }
            .summary-number { font-size: 1.2rem; }
            .certificate-eligible-banner {
                flex-direction: column;
                text-align: center;
                padding: 16px 18px;
            }
            .certificate-info {
                flex-direction: column;
                text-align: center;
            }
            .btn-certificate-download,
            .btn-certificate-print {
                width: 100%;
                justify-content: center;
                padding: 10px 20px;
            }
            .workflow-steps { padding: 0; gap: 8px; }
            .workflow-step .step-circle { width: 32px; height: 32px; font-size: 0.55rem; }
            .workflow-step .step-label { font-size: 0.5rem; max-width: 50px; }
        }
        
        @media (max-width: 576px) {
            .main-content { padding: 12px 12px; }
            .page-header h2 { font-size: 1.15rem; }
            .page-header h2 i { font-size: 1.1rem; }
            .stats-summary { padding: 14px; }
            .summary-number { font-size: 1rem; }
            .dept-card-header { padding: 14px 16px; }
            .dept-card-body { padding: 14px 16px; }
            .dept-icon { width: 36px; height: 36px; font-size: 0.9rem; }
            .dept-title h4 { font-size: 0.85rem; }
            .status-badge { font-size: 0.65rem; padding: 3px 10px; }
            .certificate-eligible-banner .cert-text h4 { font-size: 0.9rem; }
            .clearance-item { font-size: 0.8rem; flex-wrap: wrap; gap: 4px; }
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
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h2><i class="fas fa-list-check"></i> Clearance Status</h2>
                    <p>Click on any unlocked department to view details and upload documents</p>
                </div>
            </div>
            
            <!-- Workflow Progress Bar -->
            <?php if (!empty($departments)): ?>
            <div class="workflow-progress">
                <div class="workflow-steps">
                    <?php foreach ($departments as $index => $dept): 
                        $is_locked = $dept['is_locked'] ?? false;
                        $is_active = $dept['is_active'] ?? false;
                        $is_approved = $dept['is_approved'] ?? false;
                        $is_rejected = $dept['is_rejected'] ?? false;
                        $display_status = $dept['display_status'] ?? 'pending';
                        
                        $circle_class = 'locked';
                        $label_class = 'locked';
                        $icon_class = 'locked';
                        
                        if ($is_approved) {
                            $circle_class = 'completed';
                            $label_class = 'completed';
                            $icon_class = 'completed';
                        } elseif ($is_rejected) {
                            $circle_class = 'rejected';
                            $label_class = 'rejected';
                            $icon_class = 'rejected';
                        } elseif ($is_active) {
                            $circle_class = 'active';
                            $label_class = 'active';
                            $icon_class = 'active';
                        } elseif ($display_status == 'no_items') {
                            $circle_class = 'no-items';
                            $label_class = 'no-items';
                            $icon_class = 'no-items';
                        } else {
                            $circle_class = 'locked';
                            $label_class = 'locked';
                            $icon_class = 'locked';
                        }
                    ?>
                    <div class="workflow-step">
                        <div class="step-circle <?php echo $circle_class; ?>">
                            <?php if ($display_status == 'no_items'): ?>
                                <i class="fas fa-circle"></i>
                            <?php elseif ($is_active): ?>
                                <i class="fas fa-spinner fa-pulse"></i>
                            <?php elseif ($is_approved): ?>
                                <i class="fas fa-check"></i>
                            <?php elseif ($is_rejected): ?>
                                <i class="fas fa-times"></i>
                            <?php elseif ($is_locked): ?>
                                <i class="fas fa-lock"></i>
                            <?php else: ?>
                                <?php echo $index + 1; ?>
                            <?php endif; ?>
                        </div>
                        <span class="step-label <?php echo $label_class; ?>"><?php echo htmlspecialchars(substr($dept['department_name'], 0, 15)); ?></span>
                        <span class="step-status-icon <?php echo $icon_class; ?>">
                            <?php if ($is_approved): ?>
                                <i class="fas fa-check-circle"></i>
                            <?php elseif ($is_rejected): ?>
                                <i class="fas fa-times-circle"></i>
                            <?php elseif ($is_active): ?>
                                <i class="fas fa-circle"></i>
                            <?php elseif ($display_status == 'no_items'): ?>
                                <i class="fas fa-circle"></i>
                            <?php else: ?>
                                <i class="fas fa-lock"></i>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Certificate Alert -->
            <?php if ($is_fully_cleared): ?>
                <div class="certificate-eligible-banner">
                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <div class="cert-icon-wrapper">
                            <i class="fas fa-certificate"></i>
                        </div>
                        <div class="cert-text">
                            <h4>🎉 Congratulations! You're cleared for graduation!</h4>
                            <p>All clearance requirements have been completed. Download your graduation clearance certificate now.</p>
                        </div>
                    </div>
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="download-certificate.php" class="btn-certificate-download">
                            <i class="fas fa-download"></i> Download Certificate
                        </a>
                        <a href="download-certificate.php?print=1" class="btn-certificate-print" target="_blank">
                            <i class="fas fa-print"></i> Print
                        </a>
                    </div>
                </div>
                
                <?php if ($certificate): ?>
                <div class="certificate-info">
                    <div>
                        <span class="label">Certificate Number</span>
                        <div class="value"><?php echo htmlspecialchars($certificate['certificate_number']); ?></div>
                    </div>
                    <div>
                        <span class="label">Verification Code</span>
                        <div class="value"><?php echo htmlspecialchars($certificate['verification_code']); ?></div>
                    </div>
                    <div>
                        <span class="label">Issued Date</span>
                        <div class="value"><?php echo date('F d, Y', strtotime($certificate['issued_date'])); ?></div>
                    </div>
                    <div>
                        <span class="badge-verified"><i class="fas fa-check-circle me-1"></i> Verified</span>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php elseif ($has_rejection): ?>
                <div class="progress-alert danger">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <h5 class="mb-0">Clearance Rejected</h5>
                            <p class="mb-0">One or more departments have rejected your clearance. Please contact them for resolution.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="progress-alert warning">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <i class="fas fa-chart-line"></i>
                        <div>
                            <h5 class="mb-0">Clearance In Progress</h5>
                            <p class="mb-0"><?php echo $completed_departments; ?>/<?php echo $total_departments; ?> departments completed (<?php echo $completion_rate; ?>%)</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Stats Summary -->
            <div class="stats-summary">
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <div class="summary-number"><?php echo $total_departments; ?></div>
                        <div class="summary-label">🏢 Departments</div>
                    </div>
                    <div class="col-6 col-md-3 mb-2 mb-md-0">
                        <div class="summary-number" style="color: var(--success);"><?php echo $completed_departments; ?></div>
                        <div class="summary-label">✅ Completed</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="summary-number"><?php echo $total_departments; ?></div>
                        <div class="summary-label">📋 Total Departments</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="summary-number" style="color: var(--primary);"><?php echo $completion_rate; ?>%</div>
                        <div class="summary-label">📊 Progress</div>
                    </div>
                </div>
            </div>
            
            <!-- Overall Progress Bar -->
            <div class="overall-progress">
                <div class="progress-label">
                    <span>Overall Clearance Progress</span>
                    <span class="fw-bold"><?php echo $completion_rate; ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $completion_rate; ?>%"></div>
                </div>
            </div>
            
            <!-- Departments List -->
            <div id="departments-list">
                <?php foreach ($departments as $index => $dept): 
                    $is_locked = $dept['is_locked'] ?? false;
                    $is_active = $dept['is_active'] ?? false;
                    $is_approved = $dept['is_approved'] ?? false;
                    $is_rejected = $dept['is_rejected'] ?? false;
                    $display_status = $dept['display_status'] ?? 'pending';
                    $remarks = $dept['remarks'] ?? null;
                    $reviewed_at = $dept['reviewed_at'] ?? null;
                    $clearance_items = $dept['clearance_items'] ?? [];
                    
                    $card_class = '';
                    if ($is_locked) $card_class = 'locked';
                    if ($is_active) $card_class = 'active';
                    if ($is_approved) $card_class = 'completed';
                    if ($is_rejected) $card_class = 'rejected';
                    if ($display_status == 'no_items') $card_class = 'no-items';
                ?>
                <?php if ($is_locked): ?>
                    <span class="dept-card <?php echo $card_class; ?>">
                <?php else: ?>
                    <a href="clearance-details.php?department_id=<?php echo $dept['id']; ?>" class="dept-card <?php echo $card_class; ?>">
                <?php endif; ?>
                    <div class="dept-card-header">
                        <div class="dept-info">
                            <div class="dept-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="dept-title">
                                <h4>
                                    <?php if ($is_locked): ?>
                                        <i class="fas fa-lock me-1" style="color: var(--gray-400); font-size: 0.65rem;"></i>
                                    <?php endif; ?>
                                    <?php if ($is_active): ?>
                                        <i class="fas fa-circle me-1" style="color: var(--primary); font-size: 0.5rem;"></i>
                                    <?php endif; ?>
                                    <?php if ($is_approved): ?>
                                        <i class="fas fa-check-circle me-1" style="color: var(--success); font-size: 0.7rem;"></i>
                                    <?php endif; ?>
                                    <?php if ($is_rejected): ?>
                                        <i class="fas fa-times-circle me-1" style="color: var(--danger); font-size: 0.7rem;"></i>
                                    <?php endif; ?>
                                    <?php if ($display_status == 'no_items'): ?>
                                        <i class="fas fa-circle me-1" style="color: var(--gray-400); font-size: 0.4rem;"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                    <span class="badge bg-light text-dark ms-1" style="font-size: 0.55rem; font-weight: 600; padding: 2px 8px;">
                                        Step <?php echo $index + 1; ?>
                                    </span>
                                </h4>
                                <p><?php echo htmlspecialchars($dept['department_code']); ?></p>
                            </div>
                        </div>
                        <div>
                            <?php if ($is_locked): ?>
                                <span class="status-badge status-locked">
                                    <i class="fas fa-lock"></i> Locked
                                </span>
                            <?php elseif ($is_approved): ?>
                                <span class="status-badge status-completed">
                                    <i class="fas fa-check-circle"></i> Completed
                                </span>
                            <?php elseif ($is_rejected): ?>
                                <span class="status-badge status-rejected">
                                    <i class="fas fa-times-circle"></i> Rejected
                                </span>
                            <?php elseif ($display_status == 'no_items'): ?>
                                <span class="status-badge status-no-items">
                                    <i class="fas fa-circle"></i> No Items
                                </span>
                            <?php elseif ($is_active): ?>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> In Progress
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dept-card-body">
                        <?php if ($is_locked): ?>
                            <div class="text-muted mb-2" style="font-size: 0.8rem;">
                                <i class="fas fa-info-circle me-1"></i> 
                                This department is locked. Please complete the previous department first.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_rejected && $remarks): ?>
                            <div class="alert alert-danger" style="font-size: 0.85rem; padding: 8px 14px; border-radius: 8px; margin-bottom: 10px;">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($remarks); ?>
                                <?php if ($reviewed_at): ?>
                                    <br><small class="text-muted">Reviewed on <?php echo date('M d, Y h:i A', strtotime($reviewed_at)); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_approved && $remarks): ?>
                            <div class="alert alert-success" style="font-size: 0.85rem; padding: 8px 14px; border-radius: 8px; margin-bottom: 10px;">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Remarks:</strong> <?php echo htmlspecialchars($remarks); ?>
                                <?php if ($reviewed_at): ?>
                                    <br><small class="text-muted">Approved on <?php echo date('M d, Y h:i A', strtotime($reviewed_at)); ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Clearance Items -->
                        <div class="clearance-items-list">
                            <div style="font-size: 0.7rem; font-weight: 600; color: var(--gray-600); margin-bottom: 6px;">
                                <i class="fas fa-list me-1"></i> Clearance Requirements
                            </div>
                            <?php if (!empty($clearance_items)): ?>
                                <?php foreach ($clearance_items as $item): 
                                    $item_status = $item['item_status'] ?? 'not_started';
                                    $item_remarks = $item['item_remarks'] ?? null;
                                    $item_reviewed_at = $item['item_reviewed_at'] ?? null;
                                    
                                    if ($item_status == 'not_started') {
                                        $status_label = 'Not Started';
                                        $status_icon = '<i class="fas fa-circle"></i>';
                                        $status_class = 'not-started';
                                    } elseif ($item_status == 'approved') {
                                        $status_label = 'Approved';
                                        $status_icon = '<i class="fas fa-check-circle"></i>';
                                        $status_class = 'approved';
                                    } elseif ($item_status == 'rejected') {
                                        $status_label = 'Rejected';
                                        $status_icon = '<i class="fas fa-times-circle"></i>';
                                        $status_class = 'rejected';
                                    } else {
                                        $status_label = 'Pending';
                                        $status_icon = '<i class="fas fa-clock"></i>';
                                        $status_class = 'pending';
                                    }
                                ?>
                                <div class="clearance-item">
                                    <div>
                                        <span class="item-name"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                        <?php if ($item['requires_document']): ?>
                                            <span class="requires-doc"><i class="fas fa-file-upload me-1"></i> Document Required</span>
                                        <?php endif; ?>
                                        <?php if ($item_remarks && ($item_status == 'approved' || $item_status == 'rejected')): ?>
                                            <span class="item-remarks <?php echo $item_status; ?>-remark">
                                                <i class="fas fa-comment me-1"></i> <?php echo htmlspecialchars($item_remarks); ?>
                                                <?php if ($item_reviewed_at): ?>
                                                    <span class="item-review-date">(<?php echo date('M d, Y', strtotime($item_reviewed_at)); ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="item-status <?php echo $status_class; ?>">
                                        <?php echo $status_icon; ?> <?php echo $status_label; ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-items-message">
                                    <i class="fas fa-info-circle me-1"></i> 
                                    No clearance requirements configured for this department.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$is_locked): ?>
                            <div class="view-details-hint">
                                <i class="fas fa-arrow-right"></i> Click to view details
                            </div>
                        <?php endif; ?>
                    </div>
                <?php if ($is_locked): ?>
                    </span>
                <?php else: ?>
                    </a>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($departments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x mb-3" style="color: var(--gray-300);"></i>
                <p class="text-muted">No active departments found.</p>
                <p class="text-muted small">Please contact the administrator to set up departments and the clearance workflow.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>