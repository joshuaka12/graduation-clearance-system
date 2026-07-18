<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get item details
$stmt = $pdo->prepare("
    SELECT ci.*, d.department_name 
    FROM clearance_items ci
    LEFT JOIN departments d ON ci.department_id = d.id
    WHERE ci.id = ?
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    redirect('index.php');
}

// Get departments for dropdown
$stmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $department_id = (int)$_POST['department_id'];
    $item_name = sanitizeInput($_POST['item_name']);
    $description = sanitizeInput($_POST['description']);
    $requires_document = isset($_POST['requires_document']) ? 1 : 0;
    $document_types = sanitizeInput($_POST['document_types']);
    $is_mandatory = isset($_POST['is_mandatory']) ? 1 : 0;
    $sort_order = (int)$_POST['sort_order'];
    
    if (empty($department_id) || empty($item_name)) {
        $error = "Department and item name are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE clearance_items SET
                    department_id = ?,
                    item_name = ?,
                    description = ?,
                    requires_document = ?,
                    document_types = ?,
                    is_mandatory = ?,
                    sort_order = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $department_id, $item_name, $description,
                $requires_document, $document_types, $is_mandatory,
                $sort_order, $item_id
            ]);
            
            logActivity($pdo, $_SESSION['user_id'], 'Edit Clearance Item', "Edited item: $item_name");
            $success = "Clearance item updated successfully!";
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = 'Edit Clearance Item';
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
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
        }
        
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
        }
        
        .page-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 500;
        }
        
        .btn-outline-custom {
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 10px 24px;
            border-radius: 12px;
            background: white;
        }
        
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
        }
        
        .card-header {
            background: white;
            padding: 20px 25px;
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--gray-800);
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            padding: 10px 15px;
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
            <h2><i class="fas fa-edit me-2"></i> Edit Clearance Item</h2>
            <a href="index.php" class="btn btn-outline-custom">
                <i class="fas fa-arrow-left me-2"></i> Back
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <script>setTimeout(function() { window.location.href = 'index.php'; }, 1500);</script>
        <?php endif; ?>
        
        <div class="card-modern">
            <div class="card-header">
                <i class="fas fa-edit me-2" style="color: var(--primary);"></i> Edit Item: <?php echo htmlspecialchars($item['item_name']); ?>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department *</label>
                            <select class="form-select" name="department_id" required>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['id']; ?>" <?php echo $dept['id'] == $item['department_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Item Name *</label>
                            <input type="text" class="form-control" name="item_name" required 
                                   value="<?php echo htmlspecialchars($item['item_name']); ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($item['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" value="<?php echo $item['sort_order']; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Document Types</label>
                            <input type="text" class="form-control" name="document_types" 
                                   value="<?php echo htmlspecialchars($item['document_types']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="requires_document" id="requiresDoc" 
                                       <?php echo $item['requires_document'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="requiresDoc">Requires Document Upload</label>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_mandatory" id="isMandatory" 
                                       <?php echo $item['is_mandatory'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="isMandatory">Mandatory for All Students</label>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="text-end">
                        <a href="index.php" class="btn btn-outline-custom me-2">Cancel</a>
                        <button type="submit" class="btn btn-primary-custom">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>