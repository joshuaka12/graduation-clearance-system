<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT ci.*, d.department_name, d.department_code,
           COUNT(DISTINCT sc.id) as total_submissions,
           SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
           SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending_count,
           SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
    FROM clearance_items ci
    LEFT JOIN departments d ON ci.department_id = d.id
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id
    WHERE ci.id = ?
    GROUP BY ci.id
");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    redirect('index.php');
}

$page_title = 'View Clearance Item';
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
        
        .detail-card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            margin-bottom: 25px;
        }
        
        .detail-header {
            background: linear-gradient(135deg, var(--primary), #5a0016);
            color: white;
            padding: 25px;
            border-radius: 20px 20px 0 0;
        }
        
        .stat-box {
            background: var(--gray-100);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
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
                <h2><i class="fas fa-info-circle me-2"></i> Item Details</h2>
            </div>
            <div>
                <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-2"></i> Edit Item
                </a>
                <a href="index.php" class="btn btn-secondary ms-2">
                    <i class="fas fa-arrow-left me-2"></i> Back
                </a>
            </div>
        </div>
        
        <div class="detail-card">
            <div class="detail-header">
                <h3 class="mb-2"><?php echo htmlspecialchars($item['item_name']); ?></h3>
                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($item['department_code']); ?></span>
            </div>
            <div class="p-4">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h6><i class="fas fa-align-left me-2" style="color: var(--primary);"></i> Description</h6>
                        <p><?php echo nl2br(htmlspecialchars($item['description'] ?: 'No description provided.')); ?></p>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-box">
                            <i class="fas fa-building fa-2x mb-2" style="color: var(--primary);"></i>
                            <h5><?php echo htmlspecialchars($item['department_name']); ?></h5>
                            <small>Department</small>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <i class="fas fa-file-alt fa-2x mb-2" style="color: var(--primary);"></i>
                            <h5><?php echo $item['requires_document'] ? 'Yes' : 'No'; ?></h5>
                            <small>Document Required</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <i class="fas fa-star fa-2x mb-2" style="color: var(--primary);"></i>
                            <h5><?php echo $item['is_mandatory'] ? 'Mandatory' : 'Optional'; ?></h5>
                            <small>Type</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <i class="fas fa-sort-numeric-down fa-2x mb-2" style="color: var(--primary);"></i>
                            <h5><?php echo $item['sort_order']; ?></h5>
                            <small>Sort Order</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <i class="fas fa-users fa-2x mb-2" style="color: var(--primary);"></i>
                            <h5><?php echo $item['total_submissions']; ?></h5>
                            <small>Total Submissions</small>
                        </div>
                    </div>
                </div>
                
                <?php if ($item['document_types']): ?>
                <div class="mt-4 p-3" style="background: var(--gray-100); border-radius: 15px;">
                    <strong><i class="fas fa-file me-2"></i> Allowed Document Types:</strong>
                    <span class="ms-2"><?php echo htmlspecialchars($item['document_types']); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="mt-4 pt-3 border-top">
                    <small class="text-muted">
                        <i class="far fa-calendar-alt me-1"></i> Created: <?php echo date('F d, Y', strtotime($item['created_at'])); ?>
                        <?php if ($item['updated_at'] != $item['created_at']): ?>
                            | Last updated: <?php echo date('F d, Y', strtotime($item['updated_at'])); ?>
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</body>
</html>