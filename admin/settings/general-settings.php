<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$page_title = 'General Settings';
$success_message = '';
$error_message = '';

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_general'])) {
    $university_name = sanitizeInput($_POST['university_name'] ?? '');
    $academic_year = sanitizeInput($_POST['academic_year'] ?? '');
    $graduation_year = sanitizeInput($_POST['graduation_year'] ?? '');
    $current_logo = $settings['university_logo'] ?? '';
    
    // Validate required fields
    if (empty($university_name)) {
        $error_message = 'University name is required.';
    } else {
        try {
            // Update or insert settings
            $setting_keys = [
                'university_name' => $university_name,
                'academic_year' => $academic_year,
                'graduation_year' => $graduation_year
            ];
            
            foreach ($setting_keys as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                       VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            // Handle logo upload
            if (isset($_FILES['university_logo']) && $_FILES['university_logo']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
                $filename = $_FILES['university_logo']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $file_size = $_FILES['university_logo']['size'];
                
                if (!in_array($ext, $allowed)) {
                    $error_message = 'Invalid file type. Please upload JPG, PNG, GIF, WEBP, or SVG.';
                } elseif ($file_size > 5242880) { // 5MB
                    $error_message = 'File size exceeds 5MB limit.';
                } else {
                    // Delete old logo if exists
                    if (!empty($current_logo) && $current_logo != 'default-logo.png') {
                        $old_path = '../../assets/uploads/' . $current_logo;
                        if (file_exists($old_path)) {
                            unlink($old_path);
                        }
                    }
                    
                    $new_filename = 'logo_' . time() . '.' . $ext;
                    $upload_path = '../../assets/uploads/' . $new_filename;
                    
                    if (move_uploaded_file($_FILES['university_logo']['tmp_name'], $upload_path)) {
                        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                               VALUES ('university_logo', ?) 
                                               ON DUPLICATE KEY UPDATE setting_value = ?");
                        $stmt->execute([$new_filename, $new_filename]);
                        $settings['university_logo'] = $new_filename;
                    } else {
                        $error_message = 'Failed to upload logo. Please check folder permissions.';
                    }
                }
            }
            
            if (empty($error_message)) {
                // Refresh settings
                $settings['university_name'] = $university_name;
                $settings['academic_year'] = $academic_year;
                $settings['graduation_year'] = $graduation_year;
                
                $success_message = 'General settings have been updated successfully.';
                logActivity($pdo, $_SESSION['user_id'], 'Update Settings', 'Updated general settings');
                
                // Redirect after successful save to remove the message on refresh
                header("Location: general-settings.php?success=1");
                exit();
            }
        } catch (Exception $e) {
            $error_message = 'Unable to save changes. Please try again.';
            error_log("General Settings Error: " . $e->getMessage());
        }
    }
}

// Check for success parameter from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success_message = 'General settings have been updated successfully.';
}

// Handle logo removal
if (isset($_POST['remove_logo'])) {
    try {
        $current_logo = $settings['university_logo'] ?? '';
        if (!empty($current_logo) && $current_logo != 'default-logo.png') {
            $old_path = '../../assets/uploads/' . $current_logo;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }
        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                               VALUES ('university_logo', 'default-logo.png') 
                               ON DUPLICATE KEY UPDATE setting_value = 'default-logo.png'");
        $stmt->execute();
        $settings['university_logo'] = 'default-logo.png';
        $success_message = 'Logo removed successfully.';
        header("Location: general-settings.php?success=1");
        exit();
    } catch (Exception $e) {
        $error_message = 'Failed to remove logo.';
    }
}

// Get current values
$university_name = $settings['university_name'] ?? 'Graduation Clearance System';
$university_logo = $settings['university_logo'] ?? 'default-logo.png';
$academic_year = $settings['academic_year'] ?? '';
$graduation_year = $settings['graduation_year'] ?? '';

// Check if logo exists
$logo_exists = !empty($university_logo) && $university_logo != 'default-logo.png' && file_exists('../../assets/uploads/' . $university_logo);
$logo_path = $logo_exists ? '../../assets/uploads/' . $university_logo : '../../assets/uploads/default-logo.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --primary-glow: rgba(128, 0, 32, 0.12);
            --gray-50: #fafbfc;
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-300: #dce1e8;
            --gray-400: #b8bfcc;
            --gray-600: #6c7293;
            --gray-700: #4a5360;
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
            min-height: 100vh;
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
        
        .mobile-toggle:hover { background: var(--primary-dark); }
        
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
            box-shadow: 10px 0 30px rgba(128, 0, 32, 0.2);
        }
        
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }
        
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
            box-shadow: 0 10px 20px rgba(128, 0, 32, 0.4);
        }
        
        .sidebar-header h4 { font-size: 1.2rem; font-weight: 600; margin: 10px 0 5px; }
        .sidebar-header p { font-size: 0.75rem; opacity: 0.7; margin: 0; }
        
        .sidebar-menu { padding: 0 15px 20px; }
        .menu-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .menu-item:hover, .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateX(5px);
        }
        .menu-item i { width: 22px; font-size: 1rem; }
        .menu-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 20px 0; }
        
        .main-content {
            margin-left: 280px;
            padding: 30px 40px;
            transition: all 0.3s;
            min-height: 100vh;
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
            display: none;
        }
        .sidebar-overlay.active { display: block; }
        
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--gray-200);
        }
        .page-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h2 i { color: var(--primary); font-size: 1.8rem; }
        .page-header p { color: var(--gray-600); margin: 6px 0 0 0; font-size: 0.95rem; }
        
        .settings-card {
            background: white;
            border-radius: 18px;
            padding: 32px 35px 30px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            max-width: 800px;
        }
        .settings-card .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .settings-card .card-title i { color: var(--primary); }
        .settings-card .card-subtitle {
            color: var(--gray-600);
            font-size: 0.85rem;
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .form-group { margin-bottom: 22px; }
        .form-group label {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-800);
            margin-bottom: 6px;
            display: block;
        }
        .form-group label .required { color: var(--danger); margin-left: 3px; }
        .form-group .form-control,
        .form-group .form-select {
            border-radius: 10px;
            border: 1.5px solid var(--gray-200);
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: white;
        }
        .form-group .form-control:focus,
        .form-group .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-soft);
            outline: none;
        }
        .form-group .help-text { font-size: 0.75rem; color: var(--gray-600); margin-top: 5px; }
        
        .logo-upload-area {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }
        .logo-preview {
            width: 120px;
            height: 120px;
            border-radius: 16px;
            border: 2px dashed var(--gray-300);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: var(--gray-50);
            flex-shrink: 0;
            transition: all 0.3s;
        }
        .logo-preview:hover { border-color: var(--primary); }
        .logo-preview img { max-width: 100%; max-height: 100%; object-fit: contain; padding: 8px; }
        .logo-preview .no-logo { color: var(--gray-400); font-size: 0.75rem; text-align: center; padding: 10px; }
        
        .logo-upload-controls { flex: 1; min-width: 200px; }
        .logo-upload-controls .file-input-wrapper { position: relative; display: inline-block; }
        .logo-upload-controls .file-input-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            top: 0;
            left: 0;
        }
        
        .btn-upload {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-upload:hover {
            background: var(--primary-soft);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-remove-logo {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: transparent;
            color: var(--danger);
            border: 1.5px solid rgba(239, 68, 68, 0.2);
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            margin-top: 10px;
        }
        .btn-remove-logo:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
            padding-top: 25px;
            border-top: 1px solid var(--gray-100);
            flex-wrap: wrap;
        }
        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(128, 0, 32, 0.3);
            color: white;
        }
        .btn-reset {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 28px;
            background: transparent;
            color: var(--gray-700);
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-reset:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }
        
        .alert-custom {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            max-width: 800px;
        }
        .alert-success {
            background: rgba(16,185,129,0.08);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.15);
        }
        .alert-danger {
            background: rgba(239,68,68,0.08);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.15);
        }
        .alert-custom i { font-size: 1.3rem; flex-shrink: 0; }
        .alert-custom .close-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: inherit;
            opacity: 0.6;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0 5px;
            transition: opacity 0.3s;
        }
        .alert-custom .close-btn:hover { opacity: 1; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeInUp 0.4s ease forwards; }
        
        @media (max-width: 992px) {
            .main-content { padding: 25px 30px; }
            .settings-card { padding: 28px 25px; }
        }
        
        @media (max-width: 768px) {
            .mobile-toggle { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 18px; }
            .page-header h2 { font-size: 1.6rem; }
            .settings-card { padding: 20px 18px; border-radius: 14px; }
            .logo-upload-area { flex-direction: column; align-items: flex-start; }
            .logo-preview { width: 100px; height: 100px; }
            .logo-upload-controls { width: 100%; }
            .form-actions { flex-direction: column; }
            .btn-save, .btn-reset { width: 100%; justify-content: center; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 15px 14px; }
            .page-header h2 { font-size: 1.3rem; }
            .settings-card { padding: 16px 14px; }
            .logo-preview { width: 80px; height: 80px; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Administrator Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="../departments/index.php" class="menu-item"><i class="fas fa-building"></i> Departments</a>
            <div class="menu-divider"></div>
            <a href="../users/index.php" class="menu-item"><i class="fas fa-users"></i> Users</a>
            <div class="menu-divider"></div>
            <a href="../reports/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="index.php" class="menu-item"><i class="fas fa-cog"></i> Settings</a>
            <a href="general-settings.php" class="menu-item active"><i class="fas fa-university"></i> General Settings</a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <div class="page-header animate-in" style="animation-delay: 0.05s;">
            <h2><i class="fas fa-university"></i> General Settings</h2>
            <p>Manage the university's basic information and branding.</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert-custom alert-success animate-in" style="animation-delay: 0.1s;">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert-custom alert-danger animate-in" style="animation-delay: 0.1s;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error_message; ?></span>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <div class="settings-card animate-in" style="animation-delay: 0.15s;">
            <div class="card-title">
                <i class="fas fa-edit"></i> University Information
            </div>
            <div class="card-subtitle">
                Update the basic information that appears throughout the system.
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="generalSettingsForm">
                <div class="form-group">
                    <label for="university_name">University Name <span class="required">*</span></label>
                    <input type="text" class="form-control" id="university_name" name="university_name" 
                           value="<?php echo htmlspecialchars($university_name); ?>" 
                           placeholder="e.g., Ndejje University" required>
                </div>
                
                <div class="form-group">
                    <label>University Logo</label>
                    <div class="logo-upload-area">
                        <div class="logo-preview">
                            <?php if ($logo_exists): ?>
                                <img src="<?php echo $logo_path; ?>" alt="University Logo">
                            <?php else: ?>
                                <div class="no-logo">
                                    <i class="fas fa-image fa-2x d-block mb-1"></i>
                                    No logo uploaded
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="logo-upload-controls">
                            <div class="file-input-wrapper">
                                <button type="button" class="btn-upload">
                                    <i class="fas fa-upload"></i> Choose Logo
                                </button>
                                <input type="file" name="university_logo" id="university_logo" accept="image/*">
                            </div>
                            <div class="help-text">Recommended: Square image, max 5MB. JPG, PNG, GIF, WEBP, SVG.</div>
                            <?php if ($logo_exists): ?>
                                <form method="POST" style="display: inline-block;">
                                    <button type="submit" name="remove_logo" class="btn-remove-logo" onclick="return confirm('Remove the current logo?')">
                                        <i class="fas fa-trash-alt"></i> Remove Logo
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="academic_year">Academic Year</label>
                    <input type="text" class="form-control" id="academic_year" name="academic_year" 
                           value="<?php echo htmlspecialchars($academic_year); ?>" 
                           placeholder="e.g., 2026/2027">
                    <div class="help-text">The current academic year (e.g., 2026/2027)</div>
                </div>
                
                <div class="form-group">
                    <label for="graduation_year">Graduation Year</label>
                    <input type="number" class="form-control" id="graduation_year" name="graduation_year" 
                           value="<?php echo htmlspecialchars($graduation_year); ?>" 
                           placeholder="e.g., 2026" min="2000" max="2100">
                    <div class="help-text">The year of the upcoming graduation ceremony</div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_general" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="reset" class="btn-reset">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(function(alert) {
                if (!alert.classList.contains('alert-danger')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }
            });
        }, 5000);
        
        document.getElementById('university_logo')?.addEventListener('change', function(e) {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.logo-preview');
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo Preview">';
                };
                reader.readAsDataURL(file);
            }
        });
        
        document.querySelector('.btn-reset')?.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Reset all fields to their current saved values?')) {
                location.reload();
            }
        });
    </script>
</body>
</html>