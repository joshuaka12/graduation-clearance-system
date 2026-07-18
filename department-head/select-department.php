<?php
session_start();
require_once '../config/config.php';
require_once '../config/chat_functions.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check if user already has a saved department selection
$stmt = $pdo->prepare("SELECT selected_department_id FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user['selected_department_id']) {
    // User already has a saved department, redirect to dashboard
    $_SESSION['selected_department_id'] = $user['selected_department_id'];
    
    // Get department name
    $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
    $stmt->execute([$user['selected_department_id']]);
    $dept = $stmt->fetch();
    $_SESSION['selected_department_name'] = $dept['department_name'];
    
    redirect('dashboard.php');
}

// Get all departments assigned to this department head
$stmt = $pdo->prepare("
    SELECT d.*, ad.is_primary 
    FROM departments d
    JOIN assigned_departments ad ON d.id = ad.department_id
    WHERE ad.user_id = ? AND d.is_active = 1
    ORDER BY ad.is_primary DESC, d.department_name ASC
");
$stmt->execute([$user_id]);
$assigned_departments = $stmt->fetchAll();

// If no departments assigned, get all departments (fallback for admin)
if (empty($assigned_departments)) {
    $stmt = $pdo->query("SELECT id, department_name, department_code FROM departments WHERE is_active = 1 ORDER BY department_name");
    $all_departments = $stmt->fetchAll();
} else {
    $all_departments = $assigned_departments;
}

// Process department selection (ONE TIME)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_department'])) {
    $selected_dept = (int)$_POST['department_id'];
    
    // Verify department is assigned to this head
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM assigned_departments 
        WHERE user_id = ? AND department_id = ?
    ");
    $stmt->execute([$user_id, $selected_dept]);
    $is_assigned = $stmt->fetch()['count'];
    
    if ($is_assigned > 0 || empty($assigned_departments)) {
        // Save the selection to database (ONE TIME)
        $stmt = $pdo->prepare("UPDATE users SET selected_department_id = ? WHERE id = ?");
        $stmt->execute([$selected_dept, $user_id]);
        
        $_SESSION['selected_department_id'] = $selected_dept;
        
        // Get department name
        $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE id = ?");
        $stmt->execute([$selected_dept]);
        $dept = $stmt->fetch();
        $_SESSION['selected_department_name'] = $dept['department_name'];
        
        $success = "Department selected successfully!";
        header("refresh:2;url=dashboard.php");
    } else {
        $error = "You don't have permission to manage this department.";
    }
}

$page_title = 'Select Department';
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
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-800: #2d3047;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #800020 0%, #4a0012 50%, #2d000a 100%);
            min-height: 100vh;
        }
        
        .container-center {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .selection-card {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            animation: fadeInUp 0.6s ease;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .card-header i {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .card-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .card-header p {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .department-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .department-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }
        
        .department-option:hover {
            border-color: var(--primary);
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(128,0,32,0.1);
        }
        
        .department-option.selected {
            border-color: var(--primary);
            background: var(--primary-soft);
        }
        
        .dept-info h4 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 4px 0;
            color: var(--gray-800);
        }
        
        .dept-info p {
            font-size: 0.75rem;
            color: #6c7683;
            margin: 0;
        }
        
        .primary-badge {
            background: var(--primary);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        .btn-select {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 14px;
            border-radius: 50px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-select:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128,0,32,0.3);
        }
        
        .btn-select:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .info-text {
            text-align: center;
            margin-top: 20px;
            font-size: 0.75rem;
            color: #6c7683;
        }
        
        .warning-text {
            background: #fff3cd;
            border: 1px solid #ffecb5;
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 0.8rem;
            color: #856404;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 520px) {
            .card-header { padding: 25px; }
            .card-body { padding: 25px; }
            .department-option { padding: 12px 16px; }
        }
    </style>
</head>
<body>
    <div class="container-center">
        <div class="selection-card">
            <div class="card-header">
                <i class="fas fa-building"></i>
                <h2>Select Your Department</h2>
                <p>Choose the department you want to manage</p>
            </div>
            
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="warning-text">
                    <i class="fas fa-info-circle me-2"></i>
                    This selection will be saved for future logins. You can only change this by contacting the administrator.
                </div>
                
                <form method="POST" id="selectForm">
                    <div class="department-list">
                        <?php foreach ($all_departments as $dept): ?>
                            <div class="department-option" data-id="<?php echo $dept['id']; ?>" onclick="selectDepartment(<?php echo $dept['id']; ?>)">
                                <div class="dept-info">
                                    <h4><?php echo htmlspecialchars($dept['department_name']); ?></h4>
                                    <p>Code: <?php echo htmlspecialchars($dept['department_code']); ?></p>
                                </div>
                                <?php if (isset($dept['is_primary']) && $dept['is_primary']): ?>
                                    <span class="primary-badge"><i class="fas fa-star"></i> Primary</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <input type="hidden" name="department_id" id="selected_dept_id" required>
                    <button type="submit" name="select_department" class="btn-select" id="submitBtn" disabled>
                        <i class="fas fa-save me-2"></i> Confirm Selection
                    </button>
                </form>
                
                <div class="info-text">
                    <i class="fas fa-lock me-1"></i>
                    Once selected, this will be your default department for all future logins.
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let selectedId = null;
        
        function selectDepartment(id) {
            selectedId = id;
            document.getElementById('selected_dept_id').value = id;
            document.getElementById('submitBtn').disabled = false;
            
            // Update UI
            document.querySelectorAll('.department-option').forEach(option => {
                option.classList.remove('selected');
                if (option.dataset.id == id) {
                    option.classList.add('selected');
                }
            });
        }
    </script>
</body>
</html>