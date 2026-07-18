<?php
session_start();
require_once '../config/db.php';
require_once '../config/functions.php';

if (!isLoggedIn() || isAdmin()) {
    redirect(isAdmin() ? '../admin/dashboard.php' : '../auth/login.php');
}

$student_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$student_id]);
$user = $stmt->fetch();

if (!$user) redirect('../auth/logout.php');

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitizeInput($_POST['full_name']);
        $phone = sanitizeInput($_POST['phone']);
        $email = sanitizeInput($_POST['email']);
        
        if (empty($full_name) || empty($phone) || empty($email)) {
            $error = "Please fill in all fields.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $student_id]);
            if ($stmt->fetch()) {
                $error = "Email already in use.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, email = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$full_name, $phone, $email, $student_id])) {
                    $_SESSION['user_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $success = "Profile updated!";
                    // Refresh user data
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $user = $stmt->fetch();
                } else {
                    $error = "Update failed.";
                }
            }
        }
    }
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG, GIF, and WEBP images allowed.";
        } else {
            $new_filename = "user_" . $student_id . "_" . time() . "." . $ext;
            $upload_path = "../assets/uploads/Students-profile/";
            
            if (!file_exists($upload_path)) mkdir($upload_path, 0777, true);
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path . $new_filename)) {
                // Delete old profile pic if exists and not default
                if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default-avatar.png') {
                    $old_file = $upload_path . $user['profile_pic'];
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                if ($stmt->execute([$new_filename, $student_id])) {
                    $success = "Profile picture updated!";
                    $user['profile_pic'] = $new_filename;
                    // Redirect to refresh the page
                    header("Location: profile.php?success=1");
                    exit();
                } else {
                    $error = "Failed to update database.";
                }
            } else {
                $error = "Failed to upload image. Check folder permissions.";
            }
        }
    }
}

// Check for success parameter
if (isset($_GET['success'])) {
    $success = "Profile picture updated successfully!";
}

// Get unread messages for notification badge
$unread_messages = 0;
if (file_exists('../config/chat_functions.php')) {
    require_once '../config/chat_functions.php';
    if (function_exists('getUnreadMessageCount')) {
        $unread_messages = getUnreadMessageCount($pdo, $student_id);
    }
}

// Get profile picture path for navbar
$profile_pic_navbar = $user['profile_pic'] ?? 'default-avatar.png';
$profile_pic_exists = !empty($profile_pic_navbar) && $profile_pic_navbar != 'default-avatar.png' && file_exists('../assets/uploads/Students-profile/' . $profile_pic_navbar);

$page_title = 'My Profile';
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
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128,0,32,0.08);
            --gray-100: #f4f6f9;
            --gray-200: #e8ecef;
            --gray-600: #6c7683;
            --gray-800: #2d3440;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
        }
        
        /* Navbar - SAME AS DASHBOARD */
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
        
        /* Profile Dropdown in Navbar */
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
        
        /* Main Content */
        .main-content { 
            padding: 30px; 
            min-height: calc(100vh - 70px); 
        }
        
        .profile-card {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .profile-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 40px;
            text-align: center;
            color: white;
        }
        
        .profile-pic-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            position: relative;
            cursor: pointer;
        }
        
        .profile-pic-container img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.3);
        }
        
        .upload-overlay {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: var(--primary);
            border-radius: 50%;
            width: 32px;
            height: 32px;
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
        
        .upload-overlay i { font-size: 12px; color: white; }
        
        #profilePicInput { display: none; }
        
        .profile-body { padding: 30px; }
        
        .info-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .info-label { width: 130px; font-weight: 500; color: var(--gray-600); }
        .info-value { flex: 1; color: var(--gray-800); }
        
        .form-control {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 10px 15px;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
        }
        
        .btn-outline-custom {
            background: white;
            border: 1px solid var(--gray-200);
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-custom:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .alert-custom {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-success { background: rgba(16,185,129,0.1); color: var(--success); border: 1px solid rgba(16,185,129,0.2); }
        .alert-danger { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
        
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .info-row { flex-direction: column; gap: 5px; }
            .info-label { width: 100%; }
            .profile-header { padding: 25px; }
            .profile-body { padding: 20px; }
            
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
        }
    </style>
</head>
<body>
    <!-- Navbar - With Profile Picture -->
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
                    <li class="nav-item position-relative">
                        <a class="nav-link" href="message.php">
                            <i class="fas fa-comments me-1"></i> Messages
                            <?php if ($unread_messages > 0): ?>
                                <span class="notification-badge"><?php echo $unread_messages; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    
                    <!-- Profile Dropdown - Shows Profile Picture -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle profile-dropdown-toggle" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar-sm">
                                <?php if ($profile_pic_exists): ?>
                                    <img src="../assets/uploads/Students-profile/<?php echo $profile_pic_navbar; ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </span>
                            <span class="profile-name"><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                            <i class="fas fa-chevron-down profile-arrow"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profileDropdown">
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
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
            <div class="profile-card">
                <!-- Header with Profile Picture -->
                <div class="profile-header">
                    <div class="profile-pic-container" onclick="document.getElementById('profilePicInput').click()">
                        <?php
                        // Get profile picture path for display
                        $profile_pic_display = "";
                        if (!empty($user['profile_pic']) && $user['profile_pic'] != 'default-avatar.png') {
                            $check_path = "../assets/uploads/Students-profile/" . $user['profile_pic'];
                            if (file_exists($check_path)) {
                                $profile_pic_display = $check_path . "?v=" . time();
                            }
                        }
                        
                        // If no profile picture, show initials as image
                        if (empty($profile_pic_display)) {
                            $initials = strtoupper(substr($user['full_name'], 0, 1));
                            $profile_pic_display = "https://ui-avatars.com/api/?background=ffffff&color=800020&size=120&font-size=60&name=" . urlencode($initials);
                        }
                        ?>
                        <img src="<?php echo $profile_pic_display; ?>" alt="Profile Picture" id="profilePreview">
                        <div class="upload-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    
                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <div class="small"><i class="fas fa-id-card"></i> ID: <?php echo $user['student_id']; ?></div>
                </div>
                
                <!-- Body -->
                <div class="profile-body">
                    <?php if ($error): ?>
                        <div class="alert-custom alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert-custom alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <!-- View Mode -->
                    <div id="viewMode">
                        <div class="info-row"><div class="info-label">Full Name</div><div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div></div>
                        <div class="info-row"><div class="info-label">Student ID</div><div class="info-value"><?php echo $user['student_id']; ?></div></div>
                        <div class="info-row"><div class="info-label">Email</div><div class="info-value"><?php echo $user['email']; ?></div></div>
                        <div class="info-row"><div class="info-label">Phone</div><div class="info-value"><?php echo $user['phone'] ?: 'Not provided'; ?></div></div>
                        <div class="info-row"><div class="info-label">Year of Registration</div><div class="info-value"><?php echo $user['year_of_registration'] ?: 'Not provided'; ?></div></div>
                        <div class="info-row"><div class="info-label">Member Since</div><div class="info-value"><?php echo date('F d, Y', strtotime($user['created_at'])); ?></div></div>
                        <div class="info-row"><div class="info-label">Last Login</div><div class="info-value"><?php echo $user['last_login'] ? date('M d, Y h:i A', strtotime($user['last_login'])) : 'Never'; ?></div></div>
                        
                        <div class="text-end mt-4">
                            <button class="btn-outline-custom" onclick="toggleEdit(true)"><i class="fas fa-edit me-2"></i> Edit Profile</button>
                        </div>
                    </div>
                    
                    <!-- Edit Mode -->
                    <div id="editMode" style="display: none;">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control" value="<?php echo $user['phone']; ?>" required>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn-outline-custom" onclick="toggleEdit(false)">Cancel</button>
                                <button type="submit" name="update_profile" class="btn-primary-custom">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Separate Form for Profile Picture Upload -->
    <form method="POST" enctype="multipart/form-data" id="picUploadForm" style="display: none;">
        <input type="file" name="profile_pic" id="profilePicInput" accept="image/jpeg,image/png,image/gif,image/webp">
    </form>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleEdit(show) {
            document.getElementById('viewMode').style.display = show ? 'none' : 'block';
            document.getElementById('editMode').style.display = show ? 'block' : 'none';
        }
        
        // Handle profile picture upload
        document.getElementById('profilePicInput').addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                document.getElementById('picUploadForm').submit();
            }
        });
        
        // Refresh image preview after upload
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                setTimeout(function() {
                    location.href = 'profile.php';
                }, 2000);
            }
        }
    </script>
</body>
</html>