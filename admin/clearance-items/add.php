<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$error = '';
$success = '';

// Get departments for dropdown
$stmt = $pdo->query("SELECT id, department_name, department_code FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_id = (int)$_POST['department_id'];
    $item_name = sanitizeInput($_POST['item_name']);
    $description = sanitizeInput($_POST['description']);
    $requires_document = isset($_POST['requires_document']) ? 1 : 0;
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    $sort_order = (int)$_POST['sort_order'];
    
    if (empty($department_id) || empty($item_name)) {
        $error = "Department and item name are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO clearance_items (department_id, item_name, description, requires_document, is_mandatory, sort_order, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$department_id, $item_name, $description, $requires_document, $is_mandatory, $sort_order])) {
                logActivity($pdo, $_SESSION['user_id'], 'Add Clearance Item', "Added item: $item_name");
                $success = "Clearance item added successfully!";
                header("refresh:2;url=index.php");
            } else {
                $error = "Failed to add item.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
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
    <title><?php echo $page_title; ?> - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-soft: rgba(128, 0, 32, 0.08);
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
        }
        
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
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
        
        .card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            max-width: 700px;
            margin: 0 auto;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        
        .card-header h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .card-header h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: #4a5360;
            display: block;
        }
        
        .form-label i {
            color: var(--primary);
            margin-right: 6px;
        }
        
        .required:after {
            content: " *";
            color: var(--danger);
        }
        
        .form-control, .form-select {
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 10px 15px;
            transition: all 0.3s;
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
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
        }
        
        .btn-back {
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 10px 24px;
            border-radius: 12px;
            background: white;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-back:hover {
            background: var(--primary);
            color: white;
        }
        
        .alert {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
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
        
        .info-text {
            font-size: 0.7rem;
            color: var(--gray-600);
            margin-top: 5px;
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .card-body { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Administrator Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="../departments/index.php" class="menu-item"><i class="fas fa-building"></i> Departments</a>
            <a href="index.php" class="menu-item active"><i class="fas fa-list-check"></i> Clearance Items</a>
            <div style="height:1px;background:rgba(255,255,255,0.1);margin:15px 0;"></div>
            <a href="../users/index.php" class="menu-item"><i class="fas fa-users"></i> Users</a>
            <a href="../clearances/pending.php" class="menu-item"><i class="fas fa-clock"></i> Pending Clearances</a>
            <div style="height:1px;background:rgba(255,255,255,0.1);margin:15px 0;"></div>
            <a href="../reports/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="../settings/index.php" class="menu-item"><i class="fas fa-cog"></i> Settings</a>
            <div style="height:1px;background:rgba(255,255,255,0.1);margin:15px 0;"></div>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <h2><i class="fas fa-plus-circle me-2"></i> Add Clearance Item</h2>
            <a href="index.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Items</a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-info-circle"></i> Item Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label required"><i class="fas fa-building"></i> Department</label>
                        <select name="department_id" class="form-select" required>
                            <option value="">Select Department...</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo $dept['department_code']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required"><i class="fas fa-tag"></i> Item Name</label>
                        <input type="text" name="item_name" class="form-control" required placeholder="e.g., Return all library books">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Detailed description of the requirement"></textarea>
                        <div class="info-text">Optional - Provide additional details about this requirement</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-sort-numeric-down"></i> Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="0" placeholder="Display order">
                        <div class="info-text">Lower numbers appear first in the list</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="requires_document" class="form-check-input" id="requiresDoc">
                                <label class="form-check-label" for="requiresDoc">
                                    <i class="fas fa-file-alt"></i> Requires Document Upload
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input type="checkbox" name="is_mandatory" class="form-check-input" id="isMandatory" checked>
                                <label class="form-check-label" for="isMandatory">
                                    <i class="fas fa-star"></i> Mandatory for All Students
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-end mt-4">
                        <a href="index.php" class="btn-back me-2">Cancel</a>
                        <button type="submit" class="btn-save"><i class="fas fa-save me-2"></i> Create Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>