<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Get departments
$stmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

// Get unassigned or existing items
$selected_dept = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : 0;

$items = [];
if ($selected_dept > 0) {
    $stmt = $pdo->prepare("
        SELECT ci.*, 
               (SELECT COUNT(*) FROM student_clearance WHERE clearance_item_id = ci.id) as usage_count
        FROM clearance_items ci
        WHERE ci.department_id = ?
        ORDER BY ci.sort_order ASC
    ");
    $stmt->execute([$selected_dept]);
    $items = $stmt->fetchAll();
}

$error = '';
$success = '';

// Handle bulk assign
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_items'])) {
    $department_id = (int)$_POST['department_id'];
    $item_ids = $_POST['item_ids'] ?? [];
    
    foreach ($item_ids as $item_id) {
        $stmt = $pdo->prepare("UPDATE clearance_items SET department_id = ? WHERE id = ?");
        $stmt->execute([$department_id, $item_id]);
    }
    
    logActivity($pdo, $_SESSION['user_id'], 'Assign Items', "Assigned " . count($item_ids) . " items to department ID: $department_id");
    $success = count($item_ids) . " items assigned successfully!";
    
    header("refresh:2;url=assign.php?dept_id=$department_id");
}

$page_title = 'Assign Clearance Items';
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
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-800: #2d3047;
            --success: #10b981;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
        }
        
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
        }
        
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }
        
        .items-list {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .item-checkbox {
            margin-right: 15px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-exchange-alt me-2"></i> Assign Clearance Items</h2>
                <p class="text-muted">Assign or reassign clearance items to departments</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card-modern p-4">
                    <h5 class="mb-3"><i class="fas fa-building me-2" style="color: var(--primary);"></i> Select Department</h5>
                    <div class="list-group">
                        <?php foreach ($departments as $dept): ?>
                        <a href="?dept_id=<?php echo $dept['id']; ?>" 
                           class="list-group-item list-group-item-action <?php echo $selected_dept == $dept['id'] ? 'active' : ''; ?>"
                           style="<?php echo $selected_dept == $dept['id'] ? 'background: var(--primary); border-color: var(--primary);' : ''; ?>">
                            <i class="fas fa-building me-2"></i>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if ($selected_dept > 0): ?>
                    <div class="card-modern">
                        <div class="p-4 border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-list me-2" style="color: var(--primary);"></i>
                                Items for Selected Department
                            </h5>
                        </div>
                        
                        <?php if (empty($items)): ?>
                            <div class="p-4 text-center text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No items assigned to this department yet.</p>
                                <a href="../clearance-items/add.php" class="btn btn-primary-custom">Add New Item</a>
                            </div>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="department_id" value="<?php echo $selected_dept; ?>">
                                <div class="items-list">
                                    <?php foreach ($items as $item): ?>
                                    <div class="p-3 border-bottom">
                                        <div class="form-check">
                                            <input class="form-check-input item-checkbox" type="checkbox" 
                                                   name="item_ids[]" value="<?php echo $item['id']; ?>" 
                                                   id="item_<?php echo $item['id']; ?>" checked>
                                            <label class="form-check-label" for="item_<?php echo $item['id']; ?>">
                                                <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                <?php if ($item['usage_count'] > 0): ?>
                                                    <span class="badge bg-secondary ms-2"><?php echo $item['usage_count']; ?> students</span>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 100); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="p-3 text-end border-top">
                                    <button type="submit" name="assign_items" class="btn btn-primary-custom">
                                        <i class="fas fa-save me-2"></i> Save Assignments
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card-modern p-5 text-center">
                        <i class="fas fa-arrow-left fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Select a department from the left to view and manage its clearance items</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>