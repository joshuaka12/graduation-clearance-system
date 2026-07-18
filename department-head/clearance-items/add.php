<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../../auth/login.php');
}

// Check if department is selected
if (!isset($_SESSION['selected_department_id'])) {
    redirect('../select-department.php');
}

$department_id = $_SESSION['selected_department_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = sanitizeInput($_POST['item_name']);
    $description = sanitizeInput($_POST['description']);
    $requires_document = isset($_POST['requires_document']) ? 1 : 0;
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    
    if (empty($item_name)) {
        $error = "Item name is required.";
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO clearance_items (department_id, item_name, description, requires_document, is_mandatory, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        if ($stmt->execute([$department_id, $item_name, $description, $requires_document, $is_mandatory])) {
            logActivity($pdo, $_SESSION['user_id'], 'Add Clearance Item', "Added: $item_name");
            $success = "Clearance item added successfully!";
            header("refresh:2;url=index.php");
        } else {
            $error = "Failed to add item.";
        }
    }
}

$page_title = 'Add Clearance Item';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Department Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-soft: rgba(128,0,32,0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-800: #2d3047;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
        }
        
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
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #9e0028, #800020);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
        }
        
        .sidebar-menu {
            padding: 0 15px;
        }
        
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
        }
        
        .menu-item:hover, .menu-item.active {
            background: linear-gradient(135deg, #800020, #5a0016);
            color: white;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, #800020, #5a0016);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
        }
        
        .card-modern {
            background: white;
            border-radius: 16px;
            max-width: 600px;
            margin: 0 auto;
            padding: 25px;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 10px 15px;
            width: 100%;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #800020;
            box-shadow: 0 0 0 3px var(--primary-soft);
            outline: none;
        }
        
        .form-check-input:checked {
            background-color: #800020;
            border-color: #800020;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .card-modern { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Department Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="index.php" class="menu-item"><i class="fas fa-list-check"></i> Clearance Items</a>
            <a href="../students.php" class="menu-item"><i class="fas fa-users"></i> Students</a>
            <a href="../reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <div style="height:1px;background:rgba(255,255,255,0.1);margin:15px 0;"></div>
            <a href="../profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
            <a href="../change-password.php" class="menu-item"><i class="fas fa-key"></i> Change Password</a>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-plus-circle me-2"></i> Add Clearance Item</h2>
            <a href="index.php" class="btn btn-secondary">Back</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card-modern">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Item Name *</label>
                    <input type="text" name="item_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="requires_document" class="form-check-input" id="doc">
                            <label class="form-check-label" for="doc">Requires Document Upload</label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="is_mandatory" class="form-check-input" id="mandatory" checked>
                            <label class="form-check-label" for="mandatory">Mandatory for All Students</label>
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn-primary-custom">Create Item</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>