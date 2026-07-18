<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$error = '';
$success = '';

// Handle add/edit role
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_role'])) {
        $role_name = sanitizeInput($_POST['role_name']);
        $description = sanitizeInput($_POST['description']);
        
        $stmt = $pdo->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
        if ($stmt->execute([$role_name, $description])) {
            $success = "Role added successfully!";
            logActivity($pdo, $_SESSION['user_id'], 'Add Role', "Added new role: $role_name");
            header("refresh:1;url=roles.php");
        } else {
            $error = "Failed to add role.";
        }
    }
    
    if (isset($_POST['update_role'])) {
        $role_id = (int)$_POST['role_id'];
        $role_name = sanitizeInput($_POST['role_name']);
        $description = sanitizeInput($_POST['description']);
        
        $stmt = $pdo->prepare("UPDATE roles SET role_name = ?, description = ? WHERE id = ?");
        if ($stmt->execute([$role_name, $description, $role_id])) {
            $success = "Role updated successfully!";
            logActivity($pdo, $_SESSION['user_id'], 'Update Role', "Updated role ID: $role_id");
            header("refresh:1;url=roles.php");
        } else {
            $error = "Failed to update role.";
        }
    }
    
    if (isset($_POST['delete_role'])) {
        $role_id = (int)$_POST['role_id'];
        
        $stmt = $pdo->prepare("DELETE FROM roles WHERE id = ?");
        if ($stmt->execute([$role_id])) {
            $success = "Role deleted successfully!";
            logActivity($pdo, $_SESSION['user_id'], 'Delete Role', "Deleted role ID: $role_id");
            header("refresh:1;url=roles.php");
        } else {
            $error = "Failed to delete role.";
        }
    }
}

// Get all roles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY id");
$roles = $stmt->fetchAll();

$page_title = 'Manage Roles';
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
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tags me-2" style="color: var(--primary);"></i> Manage Roles</h2>
            <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="fas fa-plus me-2"></i> Add Role
            </button>
        </div>
        
        <div class="card-modern">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><?php echo $role['id']; ?></td>
                        <td><strong><?php echo ucfirst($role['role_name']); ?></strong></td>
                        <td><?php echo $role['description']; ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editRoleModal" 
                                    data-id="<?php echo $role['id']; ?>" 
                                    data-name="<?php echo $role['role_name']; ?>" 
                                    data-desc="<?php echo $role['description']; ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteRoleModal" 
                                    data-id="<?php echo $role['id']; ?>" 
                                    data-name="<?php echo $role['role_name']; ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                         </td>
                     </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Add Role Modal -->
    <div class="modal fade" id="addRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header" style="background: var(--primary); color: white;">
                        <h5 class="modal-title">Add New Role</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" name="role_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_role" class="btn btn-primary">Add Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>