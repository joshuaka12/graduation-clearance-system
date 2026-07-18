<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'department_head'");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('index.php');
}

// Get current selected department name if exists
$selected_dept_name = '';
if ($user['selected_department_id']) {
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $stmt->execute([$user['selected_department_id']]);
    $dept = $stmt->fetch();
    $selected_dept_name = $dept ? $dept['department_name'] : 'Unknown';
}

// Process reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_reset'])) {
    // Reset the selected department
    $stmt = $pdo->prepare("UPDATE users SET selected_department_id = NULL WHERE id = ?");
    if ($stmt->execute([$user_id])) {
        logActivity($pdo, $_SESSION['user_id'], 'Reset Department Selection', "Reset department selection for user: {$user['full_name']} (ID: $user_id)");
        $success = "Department selection has been reset successfully!";
        
        // Clear session if this user is currently logged in (optional)
        // You might want to force logout the user or just let them select again on next login
        
        header("refresh:2;url=index.php");
    } else {
        $error = "Failed to reset department selection. Please try again.";
    }
}

$page_title = 'Reset Department Selection - ' . htmlspecialchars($user['full_name']);
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
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-600: #6c7293;
            --gray-800: #2d3047;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
        }
        
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 5px 0 0;
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
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
            color: white;
        }
        
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
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
        
        .user-info {
            background: var(--primary-soft);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
            color: white;
        }
        
        .user-info h4 {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 0 5px 0;
            color: var(--gray-800);
        }
        
        .user-info p {
            margin: 0;
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        
        .info-box {
            background: #fff3cd;
            border: 1px solid #ffecb5;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .info-box i {
            color: #856404;
        }
        
        .info-box ul {
            margin: 10px 0 0 20px;
        }
        
        .alert-custom {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .warning-text {
            color: var(--warning);
            font-weight: 500;
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
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-sync-alt me-2" style="color: var(--primary);"></i> Reset Department Selection</h2>
                <p>Reset a department head's selected department</p>
            </div>
            <a href="index.php" class="btn-back">
                <i class="fas fa-arrow-left me-2"></i> Back to Users
            </a>
        </div>
        
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
        
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-user-tie"></i> Department Head Information</h5>
            </div>
            <div class="card-body-custom">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                    </div>
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p><i class="fas fa-envelope me-1"></i> <?php echo $user['email']; ?></p>
                    <p><i class="fas fa-phone me-1"></i> <?php echo $user['phone'] ?: 'No phone number'; ?></p>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Current Selection Status:</strong>
                    <?php if ($user['selected_department_id']): ?>
                        <p class="mt-2 mb-0">
                            This department head has selected: 
                            <strong class="warning-text"><?php echo htmlspecialchars($selected_dept_name); ?></strong>
                        </p>
                        <p class="mt-2 mb-0 small text-muted">
                            <i class="fas fa-calendar-alt me-1"></i> 
                            This selection was made on: <?php echo date('F d, Y', strtotime($user['updated_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p class="mt-2 mb-0">
                            This department head has <strong>NOT selected a department yet</strong>.
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-exclamation-triangle me-2" style="color: #856404;"></i>
                    <strong>What happens when you reset?</strong>
                    <ul>
                        <li>The department head will lose their current department selection</li>
                        <li>On next login, they will be prompted to choose a department again</li>
                        <li>All existing clearance data and messages remain intact</li>
                        <li>This action is useful when a department head changes roles or departments</li>
                    </ul>
                </div>
                
                <?php if ($user['selected_department_id']): ?>
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> Resetting will require this department head to select a new department on their next login.
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="text-center">
                        <?php if ($user['selected_department_id']): ?>
                            <button type="submit" name="confirm_reset" class="btn-primary-custom" 
                                    onclick="return confirm('Are you sure you want to reset the department selection for <?php echo addslashes($user['full_name']); ?>? They will need to choose a new department on next login.')">
                                <i class="fas fa-sync-alt me-2"></i> Reset Department Selection
                            </button>
                        <?php else: ?>
                            <div class="text-muted">
                                <i class="fas fa-check-circle me-2" style="color: var(--success);"></i>
                                No reset needed - this department head hasn't selected a department yet.
                            </div>
                            <a href="index.php" class="btn-primary-custom mt-3">
                                <i class="fas fa-arrow-left me-2"></i> Back to Users
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>