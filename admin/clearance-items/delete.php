<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get item details
$stmt = $pdo->prepare("SELECT item_name, department_id FROM clearance_items WHERE id = ?");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    redirect('index.php');
}

// Check if item has student clearances
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM student_clearance WHERE clearance_item_id = ?");
$stmt->execute([$item_id]);
$usage_count = $stmt->fetch()['count'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm'])) {
        try {
            // Delete the item
            $stmt = $pdo->prepare("DELETE FROM clearance_items WHERE id = ?");
            $stmt->execute([$item_id]);
            
            logActivity($pdo, $_SESSION['user_id'], 'Delete Clearance Item', "Deleted item: {$item['item_name']}");
            $success = "Clearance item deleted successfully!";
            
            header("refresh:2;url=index.php");
            
        } catch (PDOException $e) {
            $error = "Cannot delete: " . $e->getMessage();
        }
    } else {
        redirect('index.php');
    }
}

$page_title = 'Delete Clearance Item';
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
            --danger: #ef4444;
            --gray-100: #f8f9fc;
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
        
        .delete-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            max-width: 600px;
            margin: 50px auto;
            text-align: center;
            padding: 40px;
        }
        
        .delete-icon {
            width: 80px;
            height: 80px;
            background: rgba(239, 68, 68, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .delete-icon i {
            font-size: 2.5rem;
            color: var(--danger);
        }
        
        .btn-danger-custom {
            background: var(--danger);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 12px;
        }
        
        .btn-secondary-custom {
            background: var(--gray-800);
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 12px;
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
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="delete-card">
            <div class="delete-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="mb-3">Delete Clearance Item</h3>
            <p class="text-muted mb-4">
                Are you sure you want to delete <strong>"<?php echo htmlspecialchars($item['item_name']); ?>"</strong>?
            </p>
            
            <?php if ($usage_count > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle me-2"></i>
                    This item has been used by <strong><?php echo $usage_count; ?> students</strong>. 
                    Deleting it will remove all associated clearance records!
                </div>
            <?php endif; ?>
            
            <div class="d-flex justify-content-center gap-3">
                <a href="index.php" class="btn btn-secondary-custom">
                    <i class="fas fa-times me-2"></i> Cancel
                </a>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="confirm" class="btn btn-danger-custom">
                        <i class="fas fa-trash me-2"></i> Yes, Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>