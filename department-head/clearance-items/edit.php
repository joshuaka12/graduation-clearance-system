<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/chat_functions.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../../auth/login.php');
}

if (isset($_SESSION['must_change_password']) && $_SESSION['must_change_password'] == 1) {
    redirect('../../auth/force-change-password.php');
}

$user_id = $_SESSION['user_id'];

// Get the department assigned to this department head
$stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();

if (!$user_data || !$user_data['department_id']) {
    $_SESSION['error'] = "No department has been assigned to you. Please contact the administrator.";
    redirect('../../auth/login.php');
}

$department_id = $user_data['department_id'];

// Get department name
$stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ? AND is_active = 1");
$stmt->execute([$department_id]);
$dept = $stmt->fetch();

if (!$dept) {
    $_SESSION['error'] = "Your assigned department is not active. Please contact the administrator.";
    redirect('../../auth/login.php');
}

$department_name = $dept['department_name'];

// Get the clearance item ID from URL
$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$item_id) {
    $_SESSION['error'] = "Invalid clearance item ID.";
    redirect('index.php');
}

// Get the clearance item and verify it belongs to this department
$stmt = $pdo->prepare("
    SELECT ci.*, d.department_name 
    FROM clearance_items ci
    JOIN departments d ON ci.department_id = d.id
    WHERE ci.id = ? AND ci.department_id = ?
");
$stmt->execute([$item_id, $department_id]);
$item = $stmt->fetch();

if (!$item) {
    $_SESSION['error'] = "Clearance item not found or you don't have permission to edit it.";
    redirect('index.php');
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = sanitizeInput($_POST['item_name']);
    $description = sanitizeInput($_POST['description']);
    $requires_document = isset($_POST['requires_document']) ? 1 : 0;
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    
    if (empty($item_name)) {
        $error = "Item name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE clearance_items 
                SET item_name = ?, 
                    description = ?, 
                    requires_document = ?, 
                    is_mandatory = ?,
                    updated_at = NOW()
                WHERE id = ? AND department_id = ?
            ");
            
            $result = $stmt->execute([
                $item_name,
                $description,
                $requires_document,
                $is_mandatory,
                $item_id,
                $department_id
            ]);
            
            if ($result) {
                logActivity($pdo, $user_id, 'Edit Clearance Item', "Edited item: $item_name");
                $success = "Clearance item updated successfully!";
                
                // Refresh item data
                $stmt = $pdo->prepare("
                    SELECT ci.*, d.department_name 
                    FROM clearance_items ci
                    JOIN departments d ON ci.department_id = d.id
                    WHERE ci.id = ? AND ci.department_id = ?
                ");
                $stmt->execute([$item_id, $department_id]);
                $item = $stmt->fetch();
            } else {
                $error = "Failed to update clearance item. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            error_log("Edit Clearance Item Error: " . $e->getMessage());
        }
    }
}

// Get chat unread count
$unread_messages = getUnreadMessageCount($pdo, $user_id);

$page_title = 'Edit Clearance Item';
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
            --gray-700: #4a5360;
            --gray-800: #2d3047;
            --success: #10b981;
            --danger: #ef4444;
            --info: #3b82f6;
            --warning: #f59e0b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
        }
        
        /* Mobile Menu Toggle - Transparent */
        .mobile-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: transparent;
            color: var(--primary);
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1.5rem;
        }
        
        .mobile-toggle:hover {
            background: rgba(128, 0, 32, 0.1);
        }
        
        /* Sidebar - Consistent with dashboard */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #5a0016 0%, #3a000e 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.2);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
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
        }
        
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 10px 24px;
            border-radius: 12px;
            background: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: var(--primary);
            color: white;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
        }
        
        /* Card */
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            max-width: 700px;
            margin: 0 auto;
            overflow: hidden;
        }
        
        .card-header-custom {
            padding: 20px 25px;
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
        
        .card-body-custom {
            padding: 25px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-size: 0.85rem;
        }
        
        .form-label i {
            color: var(--primary);
            margin-right: 6px;
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger);
        }
        
        .form-control, .form-select {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 10px 15px;
            transition: all 0.3s;
            font-size: 0.85rem;
            width: 100%;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
            outline: none;
        }
        
        textarea.form-control {
            resize: vertical;
        }
        
        .form-check {
            margin-top: 10px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-text {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin-top: 5px;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .alert-danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.2);
        }
        
        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.2);
        }
        
        .info-box {
            background: var(--primary-soft);
            border-radius: 12px;
            padding: 15px;
            margin-top: 15px;
            font-size: 0.85rem;
        }
        
        .info-box ul {
            padding-left: 20px;
            margin-top: 5px;
        }
        
        .info-box ul li {
            margin-bottom: 3px;
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
            
            .page-header h2 {
                font-size: 1.4rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .card-body-custom {
                padding: 20px;
            }
            
            .btn-back, .btn-save {
                padding: 8px 20px;
                font-size: 0.85rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .card-header-custom {
                padding: 15px 18px;
            }
            
            .card-body-custom {
                padding: 15px 18px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle - Transparent -->
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
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="index.php" class="menu-item active"><i class="fas fa-list-check"></i> Clearance Items</a>
            <a href="../students.php" class="menu-item"><i class="fas fa-users"></i> Students</a>
            <a href="../reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="../message.php" class="menu-item">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="notification-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <div class="menu-divider"></div>
            <a href="../profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
            <a href="../change-password.php" class="menu-item"><i class="fas fa-key"></i> Change Password</a>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <div>
                <h2><i class="fas fa-edit me-2"></i> Edit Clearance Item</h2>
                <p>Editing item for: <strong><?php echo htmlspecialchars($department_name); ?></strong></p>
            </div>
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Items
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-pencil-alt"></i> Item Information</h5>
            </div>
            <div class="card-body-custom">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label required-field"><i class="fas fa-tag"></i> Item Name</label>
                        <input type="text" name="item_name" class="form-control" value="<?php echo htmlspecialchars($item['item_name']); ?>" required placeholder="e.g., Library Clearance">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Brief description of this clearance item"><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><i class="fas fa-toggle-on"></i> Settings</label>
                            <div class="form-check">
                                <input type="checkbox" name="requires_document" class="form-check-input" id="requires_document" <?php echo $item['requires_document'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="requires_document">Requires Document Upload</label>
                            </div>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_mandatory" class="form-check-input" id="is_mandatory" <?php echo $item['is_mandatory'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_mandatory">Mandatory Clearance</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-box">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong>
                        <ul>
                            <li>Changes to this item will affect all students who have not yet completed it</li>
                            <li>Students who have already completed this item will not be affected</li>
                            <li>Making an item mandatory requires all students to complete it</li>
                        </ul>
                    </div>
                    
                    <div class="text-end mt-4">
                        <a href="index.php" class="btn-back me-2">Cancel</a>
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save me-2"></i> Update Item
                        </button>
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
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
    </script>
</body>
</html>