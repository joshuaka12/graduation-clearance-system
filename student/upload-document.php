<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';
require_once '../config/chat_functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

// If admin tries to access, redirect to admin dashboard
if (isAdmin()) {
    redirect('../admin/dashboard.php');
}

$student_id = $_SESSION['user_id'];

// Get settings
$university_name = 'Graduation Clearance System';
$academic_year = '';
$graduation_year = '';

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        if ($row['setting_key'] == 'university_name') $university_name = $row['setting_value'];
        elseif ($row['setting_key'] == 'academic_year') $academic_year = $row['setting_value'];
        elseif ($row['setting_key'] == 'graduation_year') $graduation_year = $row['setting_value'];
    }
} catch (Exception $e) {}

$error = '';
$success = '';

// Get user profile picture
$stmt = $pdo->prepare("SELECT profile_pic, full_name FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();
$profile_pic = $user['profile_pic'] ?? 'default-avatar.png';
$profile_pic_exists = !empty($profile_pic) && $profile_pic != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic);

$unread_messages = getUnreadMessageCount($pdo, $student_id);

// ============================================================
// HANDLE DOCUMENT DELETE - FIXED
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    $student_clearance_id = (int)$_POST['student_clearance_id'];
    
    // Verify the student owns this document
    $stmt = $pdo->prepare("
        SELECT id, document_path, clearance_item_id 
        FROM student_clearance 
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$student_clearance_id, $student_id]);
    $record = $stmt->fetch();
    
    if ($record) {
        $upload_dir = "../assets/uploads/documents/";
        $file_path = $upload_dir . $record['document_path'];
        
        // Delete physical file if it exists
        if ($record['document_path'] && file_exists($file_path)) {
            if (unlink($file_path)) {
                // File deleted successfully
            }
        }
        
        // Update database - set document_path to NULL
        $stmt = $pdo->prepare("
            UPDATE student_clearance 
            SET document_path = NULL, status = 'pending', remarks = 'Document deleted by student', updated_at = NOW()
            WHERE id = ? AND student_id = ?
        ");
        $stmt->execute([$student_clearance_id, $student_id]);
        
        $success = "Document deleted successfully. You can upload a new one.";
        logActivity($pdo, $student_id, 'Delete Document', "Deleted document for clearance record ID: $student_clearance_id");
        
        // Refresh to show updated list
        echo '<meta http-equiv="refresh" content="1;url=upload-document.php">';
    } else {
        $error = "Document not found or you don't have permission to delete it.";
    }
}

// ============================================================
// GET PENDING DOCUMENTS (not uploaded yet or rejected)
// ============================================================
$stmt = $pdo->prepare("
    SELECT ci.*, d.department_name, d.department_code, 
           sc.status, sc.document_path, sc.id as student_clearance_id, sc.remarks as rejection_reason
    FROM clearance_items ci
    JOIN departments d ON ci.department_id = d.id
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
    WHERE ci.requires_document = 1 
    AND d.is_active = 1
    AND (sc.id IS NULL OR sc.status = 'rejected' OR sc.status = 'pending')
    ORDER BY d.department_name ASC, ci.sort_order ASC
");
$stmt->execute([$student_id]);
$pending_documents = $stmt->fetchAll();

// ============================================================
// GET UPLOADED DOCUMENTS
// ============================================================
$stmt = $pdo->prepare("
    SELECT ci.*, d.department_name, d.department_code,
           sc.id as student_clearance_id, sc.status, sc.document_path, sc.updated_at, sc.remarks
    FROM clearance_items ci
    JOIN departments d ON ci.department_id = d.id
    JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = ?
    WHERE ci.requires_document = 1 
    AND sc.document_path IS NOT NULL
    ORDER BY sc.updated_at DESC
");
$stmt->execute([$student_id]);
$uploaded_documents = $stmt->fetchAll();

// ============================================================
// HANDLE DOCUMENT UPLOAD
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $clearance_item_id = (int)$_POST['clearance_item_id'];
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    
    $stmt = $pdo->prepare("
        SELECT ci.*, d.id as department_id 
        FROM clearance_items ci 
        JOIN departments d ON ci.department_id = d.id 
        WHERE ci.id = ?
    ");
    $stmt->execute([$clearance_item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        $error = "Invalid clearance item.";
    } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] != 0) {
        $error = "Please select a file to upload.";
    } else {
        $file = $_FILES['document'];
        $allowed_types = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $max_size = 5 * 1024 * 1024;
        
        if (!in_array($file_ext, $allowed_types)) {
            $error = "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX";
        } elseif ($file['size'] > $max_size) {
            $error = "File too large. Maximum size is 5MB.";
        } else {
            $upload_dir = "../assets/uploads/documents/";
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $new_filename = "student_{$student_id}_item_{$clearance_item_id}_" . time() . "." . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Check if clearance record exists
                $stmt = $pdo->prepare("
                    SELECT id, document_path 
                    FROM student_clearance 
                    WHERE student_id = ? AND clearance_item_id = ?
                ");
                $stmt->execute([$student_id, $clearance_item_id]);
                $existing = $stmt->fetch();
                
                if ($existing) {
                    // Delete old document if exists
                    if ($existing['document_path'] && file_exists($upload_dir . $existing['document_path'])) {
                        unlink($upload_dir . $existing['document_path']);
                    }
                    
                    // Update existing record
                    $stmt = $pdo->prepare("
                        UPDATE student_clearance 
                        SET document_path = ?, status = 'pending', remarks = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$new_filename, $remarks, $existing['id']]);
                    $success = "Document uploaded successfully!";
                } else {
                    // Create new clearance record
                    $stmt = $pdo->prepare("
                        INSERT INTO student_clearance (student_id, department_id, clearance_item_id, document_path, remarks, status, created_at)
                        VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([$student_id, $item['department_id'], $clearance_item_id, $new_filename, $remarks]);
                    $success = "Document uploaded successfully!";
                }
                
                logActivity($pdo, $student_id, 'Upload Document', "Uploaded document for: {$item['item_name']}");
                echo '<meta http-equiv="refresh" content="2;url=upload-document.php">';
            } else {
                $error = "Failed to upload file. Please try again.";
            }
        }
    }
}

$page_title = 'Upload Documents';
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
            --primary-soft: rgba(128, 0, 32, 0.08);
            --gray-100: #f4f6f9;
            --gray-200: #e8ecef;
            --gray-600: #6c7683;
            --gray-700: #4a5360;
            --gray-800: #2d3440;
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
            border: 2px solid rgba(255,255,255,0.5);
            background: rgba(255,255,255,0.15);
            padding: 8px 12px;
            border-radius: 8px;
        }
        .navbar-toggler:hover { background: rgba(255,255,255,0.25); border-color: white; }
        .navbar-toggler:focus { box-shadow: none; outline: none; }
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
        .card-modern {
            background: white;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }
        .card-header-custom {
            padding: 16px 22px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        .card-header-custom h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        .card-header-custom h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        .document-item {
            padding: 16px 22px;
            border-bottom: 1px solid var(--gray-200);
            transition: all 0.3s;
        }
        .document-item:last-child { border-bottom: none; }
        .document-item:hover { background: var(--gray-50); }
        .doc-title {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        .doc-department {
            font-size: 0.75rem;
            color: var(--gray-600);
            margin-bottom: 6px;
        }
        .doc-department i { color: var(--primary); }
        .status-badge {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-approved { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        .btn-upload {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
            color: white;
        }
        .btn-view {
            background: var(--info);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-view:hover { background: #2c6eb0; color: white; }
        .btn-delete {
            background: var(--danger);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.7rem;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
        }
        .btn-delete:hover { background: #dc2626; color: white; }
        .upload-area {
            border: 2px dashed var(--gray-200);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        .upload-area:hover {
            border-color: var(--primary);
            background: var(--primary-soft);
        }
        .upload-area.dragover {
            border-color: var(--primary);
            background: var(--primary-soft);
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray-600);
        }
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.3;
            display: block;
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
            .document-item .row > div {
                margin-bottom: 8px;
            }
            .document-item .row > div:last-child {
                margin-bottom: 0;
            }
        }
        @media (max-width: 576px) {
            .main-content { padding: 12px; }
            .page-header h2 { font-size: 1.3rem; }
            .page-header h2 i { font-size: 1.1rem; }
            .document-item { padding: 14px 16px; }
            .card-header-custom { padding: 14px 16px; }
            .btn-upload, .btn-view, .btn-delete { width: 100%; justify-content: center; margin-top: 4px; }
        }
    </style>
</head>
<body>
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="clearance-status.php"><i class="fas fa-list-check me-1"></i> Clearance</a></li>
                    <li class="nav-item"><a class="nav-link active" href="upload-document.php"><i class="fas fa-cloud-upload-alt me-1"></i> Upload</a></li>
                    <li class="nav-item"><a class="nav-link" href="help-support.php"><i class="fas fa-headset me-1"></i> Help</a></li>
                    <li class="nav-item"><a class="nav-link" href="message.php"><i class="fas fa-comments me-1"></i> Messages<?php if ($unread_messages > 0): ?><span class="notification-badge"><?php echo $unread_messages; ?></span><?php endif; ?></a></li>
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
            <div class="system-info-banner">
                <div class="info-text">
                    <span class="uni-name"><?php echo htmlspecialchars($university_name); ?></span>
                    <div class="details">
                        <?php if ($academic_year): ?>
                            <span class="detail-item"><i class="fas fa-calendar-alt"></i><strong>Academic Year:</strong> <?php echo htmlspecialchars($academic_year); ?></span>
                        <?php endif; ?>
                        <?php if ($graduation_year): ?>
                            <span class="detail-item"><i class="fas fa-graduation-cap"></i><strong>Graduation:</strong> <?php echo htmlspecialchars($graduation_year); ?></span>
                        <?php endif; ?>
                        <span class="detail-item"><i class="fas fa-id-card"></i><strong>ID:</strong> <?php echo htmlspecialchars($_SESSION['student_id']); ?></span>
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
            
            <a href="clearance-status.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Clearance Status</a>
            
            <div class="page-header">
                <h2><i class="fas fa-cloud-upload-alt"></i> Upload Documents</h2>
                <p>Upload required documents for clearance approval</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius: 12px;">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px;">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Pending Documents -->
            <div class="card-modern">
                <div class="card-header-custom">
                    <h5><i class="fas fa-clock" style="color: var(--warning);"></i> Documents Pending Upload</h5>
                </div>
                <?php if (empty($pending_documents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle" style="color: var(--success);"></i>
                        <p class="mb-0">No pending documents! All required documents have been uploaded.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_documents as $doc): ?>
                    <div class="document-item">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <div class="doc-title"><?php echo htmlspecialchars($doc['item_name']); ?></div>
                                <div class="doc-department">
                                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($doc['department_name']); ?> (<?php echo $doc['department_code']; ?>)
                                </div>
                                <?php if ($doc['status'] == 'rejected' && $doc['rejection_reason']): ?>
                                    <div class="mt-1 text-danger small">
                                        <i class="fas fa-exclamation-circle me-1"></i> Rejected: <?php echo htmlspecialchars($doc['rejection_reason']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                                <button class="btn-upload" onclick="showUploadModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars($doc['item_name']); ?>')">
                                    <i class="fas fa-cloud-upload-alt me-2"></i> 
                                    <?php echo ($doc['status'] == 'rejected') ? 'Re-upload' : 'Upload Document'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Uploaded Documents -->
            <div class="card-modern">
                <div class="card-header-custom">
                    <h5><i class="fas fa-file-alt"></i> Uploaded Documents</h5>
                </div>
                <?php if (empty($uploaded_documents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p class="mb-0">No documents uploaded yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($uploaded_documents as $doc): ?>
                    <div class="document-item">
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <div class="doc-title"><?php echo htmlspecialchars($doc['item_name']); ?></div>
                                <div class="doc-department">
                                    <i class="fas fa-building me-1"></i> <?php echo htmlspecialchars($doc['department_name']); ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-calendar-alt me-1"></i> Uploaded: <?php echo date('M d, Y', strtotime($doc['updated_at'])); ?>
                                </small>
                            </div>
                            <div class="col-md-3">
                                <span class="status-badge status-<?php echo $doc['status']; ?>">
                                    <i class="fas <?php echo $doc['status'] == 'approved' ? 'fa-check-circle' : ($doc['status'] == 'rejected' ? 'fa-times-circle' : 'fa-clock'); ?> me-1"></i>
                                    <?php echo ucfirst($doc['status']); ?>
                                </span>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <?php if ($doc['document_path']): ?>
                                    <a href="../assets/uploads/documents/<?php echo $doc['document_path']; ?>" class="btn-view" target="_blank">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                <?php endif; ?>
                                <!-- DELETE BUTTON - FIXED -->
                                <form method="POST" style="display: inline-block;" onsubmit="return confirmDelete('<?php echo addslashes(htmlspecialchars($doc['item_name'])); ?>')">
                                    <input type="hidden" name="student_clearance_id" value="<?php echo $doc['student_clearance_id']; ?>">
                                    <button type="submit" name="delete_document" class="btn-delete">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php if ($doc['remarks']): ?>
                        <div class="mt-2 p-2 bg-light rounded small" style="font-size: 0.8rem;">
                            <strong>Remarks:</strong> <?php echo htmlspecialchars($doc['remarks']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-radius: 20px 20px 0 0; border: none;">
                    <h5 class="modal-title"><i class="fas fa-cloud-upload-alt me-2"></i> <span id="modalTitle">Upload Document</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body" style="padding: 25px;">
                        <input type="hidden" name="clearance_item_id" id="modal_item_id">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Document</label>
                            <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-cloud-upload-alt fa-2x mb-2" style="color: var(--primary);"></i>
                                <p class="mb-1">Click to browse or drag and drop</p>
                                <small class="text-muted">PDF, JPG, PNG, DOC, DOCX (Max 5MB)</small>
                                <input type="file" name="document" id="fileInput" class="d-none" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                            </div>
                            <div id="fileName" class="mt-2 small text-muted"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Comments (Optional)</label>
                            <textarea name="remarks" class="form-control" rows="3" placeholder="Add any additional information..." style="border-radius: 10px;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer" style="border-radius: 0 0 20px 20px; border-top: 1px solid var(--gray-200);">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                        <button type="submit" name="upload_document" class="btn" id="submitBtn" style="background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-radius: 10px;">
                            <i class="fas fa-upload me-2"></i> Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function showUploadModal(itemId, itemName) {
            document.getElementById('modal_item_id').value = itemId;
            document.getElementById('modalTitle').textContent = 'Upload: ' + itemName;
            document.getElementById('fileInput').value = '';
            document.getElementById('fileName').innerHTML = '';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-upload me-2"></i> Upload Document';
            $('#uploadModal').modal('show');
        }
        
        function confirmDelete(itemName) {
            return confirm('Are you sure you want to delete the document for "' + itemName + '"? This action cannot be undone.');
        }
        
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            document.getElementById('fileName').innerHTML = file ? `<i class="fas fa-file me-1"></i> ${file.name} (${(file.size / 1024).toFixed(1)} KB)` : '';
        });
        
        const uploadArea = document.getElementById('uploadArea');
        if (uploadArea) {
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                const file = e.dataTransfer.files[0];
                if (file) {
                    const input = document.getElementById('fileInput');
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    input.files = dataTransfer.files;
                    document.getElementById('fileName').innerHTML = `<i class="fas fa-file me-1"></i> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
                }
            });
        }
        
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                if (!alert.classList.contains('alert-danger')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() { alert.style.display = 'none'; }, 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>