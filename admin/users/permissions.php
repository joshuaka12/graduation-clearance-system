<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$error = '';
$success = '';

// Get all roles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY id");
$roles = $stmt->fetchAll();

// Get current role from URL
$selected_role = isset($_GET['role']) ? (int)$_GET['role'] : ($roles[0]['id'] ?? 0);

// Define permission categories
$permission_categories = [
    'Dashboard' => [
        'view_dashboard' => 'View Dashboard',
        'view_statistics' => 'View Statistics',
        'view_charts' => 'View Charts'
    ],
    'User Management' => [
        'view_users' => 'View Users',
        'add_users' => 'Add Users',
        'edit_users' => 'Edit Users',
        'delete_users' => 'Delete Users'
    ],
    'Department Management' => [
        'view_departments' => 'View Departments',
        'add_departments' => 'Add Departments',
        'edit_departments' => 'Edit Departments',
        'delete_departments' => 'Delete Departments'
    ],
    'Clearance Items' => [
        'view_items' => 'View Clearance Items',
        'add_items' => 'Add Clearance Items',
        'edit_items' => 'Edit Clearance Items',
        'delete_items' => 'Delete Clearance Items'
    ],
    'Student Clearances' => [
        'view_clearances' => 'View Student Clearances',
        'approve_clearances' => 'Approve Clearances',
        'reject_clearances' => 'Reject Clearances',
        'bulk_actions' => 'Bulk Actions'
    ],
    'Reports' => [
        'view_reports' => 'View Reports',
        'export_reports' => 'Export Reports',
        'print_reports' => 'Print Reports'
    ],
    'Settings' => [
        'view_settings' => 'View Settings',
        'edit_settings' => 'Edit Settings',
        'system_config' => 'System Configuration'
    ],
    'Certificates' => [
        'view_certificates' => 'View Certificates',
        'issue_certificates' => 'Issue Certificates',
        'download_certificates' => 'Download Certificates'
    ]
];

// Handle permission update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $role_id = (int)$_POST['role_id'];
    
    // Delete existing permissions
    $stmt = $pdo->prepare("DELETE FROM permissions WHERE role_id = ?");
    $stmt->execute([$role_id]);
    
    // Insert new permissions
    $stmt = $pdo->prepare("INSERT INTO permissions (role_id, permission_key, permission_value) VALUES (?, ?, ?)");
    
    foreach ($permission_categories as $category => $permissions) {
        foreach ($permissions as $key => $label) {
            $value = isset($_POST[$key]) ? 1 : 0;
            $stmt->execute([$role_id, $key, $value]);
        }
    }
    
    $success = "Permissions updated successfully!";
    logActivity($pdo, $_SESSION['user_id'], 'Update Permissions', "Updated permissions for role ID: $role_id");
}

// Get current permissions for selected role
$permissions = [];
if ($selected_role) {
    $stmt = $pdo->prepare("SELECT permission_key, permission_value FROM permissions WHERE role_id = ?");
    $stmt->execute([$selected_role]);
    while ($row = $stmt->fetch()) {
        $permissions[$row['permission_key']] = $row['permission_value'];
    }
}

$page_title = 'Manage Permissions';
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
            --gray-200: #e8ecef;
            --gray-800: #2d3440;
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
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .permission-group {
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .permission-group h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--gray-800);
        }
        
        .permission-group h4 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .form-check {
            margin-bottom: 10px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-key me-2" style="color: var(--primary);"></i> Role Permissions</h2>
        </div>
        
        <div class="card-modern">
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="list-group">
                        <?php foreach ($roles as $role): ?>
                            <a href="?role=<?php echo $role['id']; ?>" 
                               class="list-group-item list-group-item-action <?php echo $selected_role == $role['id'] ? 'active' : ''; ?>"
                               style="<?php echo $selected_role == $role['id'] ? 'background: var(--primary); border-color: var(--primary);' : ''; ?>">
                                <?php echo ucfirst($role['role_name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-9">
                    <?php if ($selected_role): ?>
                        <form method="POST">
                            <input type="hidden" name="role_id" value="<?php echo $selected_role; ?>">
                            
                            <?php foreach ($permission_categories as $category => $permissions_list): ?>
                            <div class="permission-group">
                                <h4><i class="fas fa-folder"></i> <?php echo $category; ?></h4>
                                <div class="row">
                                    <?php foreach ($permissions_list as $key => $label): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input type="checkbox" name="<?php echo $key; ?>" 
                                                   class="form-check-input" 
                                                   id="<?php echo $key; ?>"
                                                   <?php echo isset($permissions[$key]) && $permissions[$key] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="<?php echo $key; ?>">
                                                <?php echo $label; ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="text-end">
                                <button type="submit" name="update_permissions" class="btn-primary-custom">
                                    <i class="fas fa-save me-2"></i> Save Permissions
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">Select a role to manage permissions</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>