<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get department info
$stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
$stmt->execute([$user['department_id']]);
$department = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitizeInput($_POST['full_name']);
    $phone = sanitizeInput($_POST['phone']);
    $email = sanitizeInput($_POST['email']);
    
    if (empty($full_name) || empty($phone) || empty($email)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = "Email already in use by another account.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$full_name, $phone, $email, $user_id])) {
                $_SESSION['user_name'] = $full_name;
                $_SESSION['email'] = $email;
                $success = "Profile updated successfully!";
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } else {
                $error = "Failed to update profile.";
            }
        }
    }
}

// Handle profile picture upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_pic']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        $error = "Only JPG, PNG, GIF images are allowed.";
    } else {
        $new_filename = "dept_head_" . $user_id . "_" . time() . "." . $ext;
        $upload_path = "../assets/uploads/profiles/";
        
        if (!file_exists($upload_path)) {
            mkdir($upload_path, 0777, true);
        }
        
        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path . $new_filename)) {
            // Delete old profile pic if exists
            if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default-avatar.png' && file_exists($upload_path . $user['profile_pic'])) {
                unlink($upload_path . $user['profile_pic']);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
            if ($stmt->execute([$new_filename, $user_id])) {
                $success = "Profile picture updated successfully!";
                $user['profile_pic'] = $new_filename;
                header("Refresh:0");
                exit();
            }
        } else {
            $error = "Failed to upload image.";
        }
    }
}

// Get unread messages count
$unread_messages = getUnreadMessageCount($pdo, $user_id);

$page_title = 'My Profile';
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
            --gray-600: #6c7293;
            --gray-800: #2d3047;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
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
        
        /* Sidebar */
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
            padding: 25px 30px;
            transition: all 0.3s;
        }
        
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }
        
        .page-header h2 i {
            color: var(--primary);
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 5px 0 0;
            font-size: 0.85rem;
        }
        
        .btn-back {
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 8px;
            background: white;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        
        .btn-back:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.04);
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .profile-pic-container {
            width: 100px;
            height: 100px;
            margin: 0 auto 15px;
            position: relative;
            cursor: pointer;
        }
        
        .profile-pic-container img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .upload-overlay {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary);
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid white;
            transition: all 0.3s;
        }
        
        .upload-overlay:hover {
            transform: scale(1.1);
        }
        
        .upload-overlay i {
            font-size: 12px;
            color: white;
        }
        
        #profilePicInput {
            display: none;
        }
        
        .profile-header h3 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.15);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            margin-top: 8px;
        }
        
        .profile-body {
            padding: 25px;
        }
        
        .info-section {
            margin-bottom: 25px;
        }
        
        .info-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary);
            display: inline-block;
        }
        
        .info-title i {
            color: var(--primary);
            margin-right: 6px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-800);
            padding: 6px 0;
            border-bottom: 1px dashed var(--gray-200);
        }
        
        .info-value i {
            color: var(--primary);
            margin-right: 6px;
            width: 18px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--gray-700);
            font-size: 0.8rem;
        }
        
        .form-label i {
            color: var(--primary);
            margin-right: 6px;
        }
        
        .form-control {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-soft);
            outline: none;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128,0,32,0.3);
        }
        
        .btn-edit {
            background: white;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-edit:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-cancel {
            background: white;
            border: 1px solid var(--gray-200);
            color: var(--gray-600);
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .alert-custom {
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.8rem;
        }
        
        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.2);
        }
        
        .alert-danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.2);
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
                padding: 20px 15px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-header {
                padding: 25px;
            }
            
            .profile-body {
                padding: 20px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-header h2 {
                font-size: 1.4rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }
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
    
    <!-- Sidebar -->
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
            <a href="message.php" class="menu-item">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="notification-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <div class="menu-divider"></div>
            <a href="profile.php" class="menu-item active"><i class="fas fa-user"></i> Profile</a>
            <a href="change-password.php" class="menu-item"><i class="fas fa-key"></i> Change Password</a>
            <a href="../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <div>
                <h2><i class="fas fa-user-circle me-2"></i> My Profile</h2>
                <p>View and manage your account information</p>
            </div>
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="profile-card">
            <!-- Profile Header with Picture -->
            <div class="profile-header">
                <div class="profile-pic-container" onclick="document.getElementById('profilePicInput').click()">
                    <?php
                    $profile_pic_path = "";
                    if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default-avatar.png') {
                        $check_path = "../assets/uploads/profiles/" . $user['profile_pic'];
                        if (file_exists($check_path)) {
                            $profile_pic_path = $check_path . "?v=" . time();
                        }
                    }
                    
                    if (empty($profile_pic_path)) {
                        $initials = strtoupper(substr($user['full_name'], 0, 1));
                        $profile_pic_path = "https://ui-avatars.com/api/?background=ffffff&color=800020&size=100&font-size=50&name=" . urlencode($initials);
                    }
                    ?>
                    <img src="<?php echo $profile_pic_path; ?>" alt="Profile Picture" id="profilePreview">
                    <div class="upload-overlay">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <form method="POST" enctype="multipart/form-data" id="picUploadForm">
                    <input type="file" name="profile_pic" id="profilePicInput" accept="image/jpeg,image/png,image/gif">
                </form>
                <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                <div class="profile-badge">
                    <i class="fas fa-user-tie"></i>
                    <span>Department Head</span>
                </div>
            </div>
            
            <!-- Profile Body -->
            <div class="profile-body">
                <?php if ($error): ?>
                    <div class="alert-custom alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert-custom alert-success">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <!-- View Mode -->
                <div id="viewMode">
                    <div class="info-section">
                        <div class="info-title"><i class="fas fa-user"></i> Personal Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><i class="fas fa-user"></i> <?php echo htmlspecialchars($user['full_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email Address</div>
                                <div class="info-value"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Department</div>
                                <div class="info-value"><i class="fas fa-building"></i> <?php echo htmlspecialchars($department['department_name'] ?? 'Not assigned'); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <div class="info-title"><i class="fas fa-calendar-alt"></i> Account Information</div>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><i class="fas fa-calendar-plus"></i> <?php echo date('F d, Y', strtotime($user['created_at'])); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Last Login</div>
                                <div class="info-value"><i class="fas fa-clock"></i> <?php echo $user['last_login'] ? date('F d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Account Status</div>
                                <div class="info-value">
                                    <?php if ($user['is_active']): ?>
                                        <span style="color: var(--success);"><i class="fas fa-circle fa-2xs me-1"></i> Active</span>
                                    <?php else: ?>
                                        <span style="color: var(--danger);"><i class="fas fa-circle fa-2xs me-1"></i> Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Role</div>
                                <div class="info-value"><i class="fas fa-tag"></i> Department Head</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end mt-3">
                        <button class="btn-edit" onclick="toggleEdit(true)">
                            <i class="fas fa-edit me-2"></i> Edit Profile
                        </button>
                    </div>
                </div>
                
                <!-- Edit Mode -->
                <div id="editMode" style="display: none;">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-user"></i> Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label"><i class="fas fa-building"></i> Department</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($department['department_name'] ?? 'Not assigned'); ?>" disabled>
                            <small class="text-muted">Department cannot be changed</small>
                        </div>
                        <div class="d-flex justify-content-end gap-3 mt-3">
                            <button type="button" class="btn-cancel" onclick="toggleEdit(false)">
                                <i class="fas fa-times me-2"></i> Cancel
                            </button>
                            <button type="submit" name="update_profile" class="btn-save">
                                <i class="fas fa-save me-2"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="text-center mt-4">
            <a href="change-password.php" class="btn btn-outline-secondary" style="border-radius: 8px; padding: 8px 20px; font-size: 0.85rem;">
                <i class="fas fa-key me-2"></i> Change Password
            </a>
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
        
        function toggleEdit(show) {
            if (show) {
                document.getElementById('viewMode').style.display = 'none';
                document.getElementById('editMode').style.display = 'block';
            } else {
                document.getElementById('viewMode').style.display = 'block';
                document.getElementById('editMode').style.display = 'none';
            }
        }
        
        // Auto-upload profile picture
        document.getElementById('profilePicInput').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                document.getElementById('picUploadForm').submit();
            }
        });
    </script>
</body>
</html>