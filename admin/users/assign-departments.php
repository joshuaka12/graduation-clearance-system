<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get department head info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'department_head'");
$stmt->execute([$user_id]);
$dept_head = $stmt->fetch();

if (!$dept_head) {
    redirect('index.php');
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Clear existing assignments
    $stmt = $pdo->prepare("DELETE FROM assigned_departments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Add new assignments
    $departments = $_POST['departments'] ?? [];
    $primary_dept = $_POST['primary_dept'] ?? 0;
    
    foreach ($departments as $dept_id) {
        $is_primary = ($dept_id == $primary_dept) ? 1 : 0;
        $stmt = $pdo->prepare("INSERT INTO assigned_departments (user_id, department_id, is_primary) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $dept_id, $is_primary]);
    }
    
    logActivity($pdo, $_SESSION['user_id'], 'Assign Departments', "Assigned departments to department head: {$dept_head['full_name']}");
    $success = "Departments assigned successfully!";
}

// Get all departments
$stmt = $pdo->query("SELECT id, department_name, department_code FROM departments WHERE is_active = 1 ORDER BY department_name");
$all_departments = $stmt->fetchAll();

// Get currently assigned departments
$stmt = $pdo->prepare("SELECT department_id FROM assigned_departments WHERE user_id = ?");
$stmt->execute([$user_id]);
$assigned = $stmt->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Assign Departments - ' . htmlspecialchars($dept_head['full_name']);
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
        :root { --primary: #800020; --primary-dark: #5a0016; --primary-soft: rgba(128,0,32,0.08); --gray-100: #f8f9fc; --gray-200: #e4e7ef; --gray-800: #2d3047; --success: #10b981; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--gray-100); }
        .main-content { margin-left: 280px; padding: 25px 30px; }
        .card { background: white; border-radius: 20px; border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.03); max-width: 700px; margin: 0 auto; padding: 25px; }
        .btn-primary-custom { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border: none; padding: 10px 24px; border-radius: 12px; }
        .dept-list { max-height: 400px; overflow-y: auto; border: 1px solid var(--gray-200); border-radius: 12px; padding: 15px; }
        .form-check { margin-bottom: 10px; }
        @media (max-width: 768px) { .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-building me-2" style="color: var(--primary);"></i> Assign Departments</h2>
            <a href="index.php" class="btn btn-secondary">Back</a>
        </div>
        
        <div class="card">
            <h5 class="mb-3">Department Head: <?php echo htmlspecialchars($dept_head['full_name']); ?></h5>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Select Departments</label>
                    <div class="dept-list">
                        <?php foreach ($all_departments as $dept): ?>
                            <div class="form-check">
                                <input type="checkbox" name="departments[]" value="<?php echo $dept['id']; ?>" 
                                       class="form-check-input dept-checkbox" id="dept_<?php echo $dept['id']; ?>"
                                       <?php echo in_array($dept['id'], $assigned) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dept_<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?> (<?php echo $dept['department_code']; ?>)
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Primary Department</label>
                    <select name="primary_dept" class="form-select" id="primaryDept">
                        <option value="">Select primary department...</option>
                        <?php foreach ($all_departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo in_array($dept['id'], $assigned) ? '' : 'disabled'; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">The primary department will be selected by default when the department head logs in.</small>
                </div>
                
                <div class="text-end">
                    <button type="submit" class="btn-primary-custom">Save Assignments</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        document.querySelectorAll('.dept-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const primarySelect = document.getElementById('primaryDept');
                const option = primarySelect.querySelector(`option[value="${this.value}"]`);
                if (option) {
                    option.disabled = !this.checked;
                }
            });
        });
    </script>
</body>
</html>