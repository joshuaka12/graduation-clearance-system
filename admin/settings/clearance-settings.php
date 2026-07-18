<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$page_title = 'Clearance Settings';
$success_message = '';
$error_message = '';

// Get current settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get all departments
$stmt = $pdo->query("SELECT id, department_name, department_code, clearance_order FROM departments ORDER BY clearance_order ASC, department_name ASC");
$departments = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_clearance'])) {
        $clearance_start = sanitizeInput($_POST['clearance_start'] ?? '');
        $clearance_end = sanitizeInput($_POST['clearance_end'] ?? '');
        
        try {
            $setting_keys = [
                'clearance_start' => $clearance_start,
                'clearance_end' => $clearance_end
            ];
            
            foreach ($setting_keys as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                       VALUES (?, ?) 
                                       ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $value, $value]);
            }
            
            $success_message = 'Clearance period settings have been updated successfully.';
            logActivity($pdo, $_SESSION['user_id'], 'Update Settings', 'Updated clearance period settings');
            
            // Refresh settings
            $settings = [];
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
        } catch (Exception $e) {
            $error_message = 'Unable to save changes. Please try again.';
            error_log("Clearance Settings Error: " . $e->getMessage());
        }
    }
    
    // Handle department order update
    if (isset($_POST['update_workflow'])) {
        $dept_order = $_POST['dept_order'] ?? [];
        
        try {
            foreach ($dept_order as $dept_id => $order) {
                $stmt = $pdo->prepare("UPDATE departments SET clearance_order = ? WHERE id = ?");
                $stmt->execute([$order, $dept_id]);
            }
            
            $success_message = 'Clearance workflow order has been updated successfully.';
            logActivity($pdo, $_SESSION['user_id'], 'Update Settings', 'Updated clearance workflow order');
            
            // Refresh departments
            $stmt = $pdo->query("SELECT id, department_name, department_code, clearance_order FROM departments ORDER BY clearance_order ASC, department_name ASC");
            $departments = $stmt->fetchAll();
            
        } catch (Exception $e) {
            $error_message = 'Unable to save workflow order. Please try again.';
            error_log("Workflow Update Error: " . $e->getMessage());
        }
    }
}

// Get current values
$clearance_start = $settings['clearance_start'] ?? '';
$clearance_end = $settings['clearance_end'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
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
            --primary-glow: rgba(128, 0, 32, 0.12);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-300: #dce1e8;
            --gray-400: #b8bfcc;
            --gray-600: #6c7293;
            --gray-700: #4a5360;
            --gray-800: #2d3047;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--gray-100);
            overflow-x: hidden;
            min-height: 100vh;
        }
        
        .mobile-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: var(--primary);
            color: white;
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(128,0,32,0.3);
            transition: all 0.3s;
        }
        .mobile-toggle:hover { background: var(--primary-dark); }
        
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
            transition: transform 0.3s ease;
            box-shadow: 10px 0 30px rgba(128, 0, 32, 0.2);
        }
        
        .sidebar::-webkit-scrollbar { width: 5px; }
        .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); }
        .sidebar::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: 10px; }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-light), var(--primary));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
            box-shadow: 0 10px 20px rgba(128, 0, 32, 0.4);
        }
        
        .sidebar-header h4 { font-size: 1.2rem; font-weight: 600; margin: 10px 0 5px; }
        .sidebar-header p { font-size: 0.75rem; opacity: 0.7; margin: 0; }
        
        .sidebar-menu { padding: 0 15px 20px; }
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
            font-size: 0.9rem;
            font-weight: 500;
        }
        .menu-item:hover, .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateX(5px);
        }
        .menu-item i { width: 22px; font-size: 1rem; }
        .menu-divider { height: 1px; background: rgba(255,255,255,0.1); margin: 20px 0; }
        
        .main-content {
            margin-left: 280px;
            padding: 30px 40px;
            transition: all 0.3s;
            min-height: 100vh;
        }
        
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            backdrop-filter: blur(2px);
            display: none;
        }
        .sidebar-overlay.active { display: block; }
        
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--gray-200);
        }
        .page-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-header h2 i { color: var(--primary); font-size: 1.8rem; }
        .page-header p { color: var(--gray-600); margin: 6px 0 0 0; font-size: 0.95rem; }
        
        /* Settings Cards */
        .settings-card {
            background: white;
            border-radius: 18px;
            padding: 32px 35px 30px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            margin-bottom: 25px;
        }
        
        .settings-card .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .settings-card .card-title i { color: var(--primary); }
        
        .settings-card .card-subtitle {
            color: var(--gray-600);
            font-size: 0.85rem;
            margin-bottom: 25px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--gray-100);
        }
        
        .form-group { margin-bottom: 22px; }
        .form-group label {
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--gray-800);
            margin-bottom: 6px;
            display: block;
        }
        .form-group label .required { color: var(--danger); margin-left: 3px; }
        .form-group .form-control,
        .form-group .form-select {
            border-radius: 10px;
            border: 1.5px solid var(--gray-200);
            padding: 10px 14px;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: white;
            width: 100%;
        }
        .form-group .form-control:focus,
        .form-group .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px var(--primary-soft);
            outline: none;
        }
        .form-group .help-text { font-size: 0.75rem; color: var(--gray-600); margin-top: 5px; }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 10px;
            padding-top: 25px;
            border-top: 1px solid var(--gray-100);
            flex-wrap: wrap;
        }
        
        .btn-save {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 32px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(128, 0, 32, 0.3);
            color: white;
        }
        
        .btn-reset {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 28px;
            background: transparent;
            color: var(--gray-700);
            border: 1.5px solid var(--gray-200);
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        .btn-reset:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }
        
        /* Alerts */
        .alert-custom {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success {
            background: rgba(16,185,129,0.08);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.15);
        }
        .alert-danger {
            background: rgba(239,68,68,0.08);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.15);
        }
        .alert-custom i { font-size: 1.3rem; flex-shrink: 0; }
        .alert-custom .close-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: inherit;
            opacity: 0.6;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0 5px;
            transition: opacity 0.3s;
        }
        .alert-custom .close-btn:hover { opacity: 1; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeInUp 0.4s ease forwards; }
        
        /* Workflow List */
        .workflow-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .workflow-item {
            display: flex;
            align-items: center;
            padding: 12px 18px;
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            margin-bottom: 8px;
            cursor: grab;
            transition: all 0.3s;
            position: relative;
        }
        
        .workflow-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px var(--primary-glow);
        }
        
        .workflow-item.dragging {
            opacity: 0.5;
            border-color: var(--primary);
        }
        
        .workflow-item .drag-handle {
            color: var(--gray-400);
            margin-right: 15px;
            cursor: grab;
            font-size: 1.1rem;
        }
        
        .workflow-item .order-number {
            background: var(--primary-soft);
            color: var(--primary);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .workflow-item .dept-info {
            flex: 1;
        }
        
        .workflow-item .dept-info strong {
            font-size: 0.9rem;
            color: var(--gray-800);
        }
        
        .workflow-item .dept-info small {
            font-size: 0.7rem;
            color: var(--gray-600);
            display: block;
        }
        
        .workflow-item .dept-code {
            background: var(--gray-100);
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--gray-600);
        }
        
        .workflow-item .final-badge {
            background: rgba(201, 168, 76, 0.15);
            color: #c9a84c;
            padding: 2px 12px;
            border-radius: 12px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .workflow-item input[type="number"] {
            width: 60px;
            padding: 4px 8px;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.8rem;
            text-align: center;
            margin-left: 10px;
        }
        
        .workflow-item input[type="number"]:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        
        @media (max-width: 992px) {
            .main-content { padding: 25px 30px; }
            .settings-card { padding: 25px; }
            .form-row { grid-template-columns: 1fr; }
        }
        
        @media (max-width: 768px) {
            .mobile-toggle { display: flex; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 18px; }
            .page-header h2 { font-size: 1.6rem; }
            .settings-card { padding: 18px; border-radius: 14px; }
            .form-actions { flex-direction: column; }
            .btn-save, .btn-reset { width: 100%; justify-content: center; }
            .workflow-item { flex-wrap: wrap; gap: 8px; }
            .workflow-item input[type="number"] { margin-left: 0; }
        }
        
        @media (max-width: 480px) {
            .main-content { padding: 15px 14px; }
            .page-header h2 { font-size: 1.3rem; }
            .settings-card { padding: 14px; }
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Administrator Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="../departments/index.php" class="menu-item"><i class="fas fa-building"></i> Departments</a>
            <div class="menu-divider"></div>
            <a href="../users/index.php" class="menu-item"><i class="fas fa-users"></i> Users</a>
            <div class="menu-divider"></div>
            <a href="../reports/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="index.php" class="menu-item"><i class="fas fa-cog"></i> Settings</a>
            <a href="clearance-settings.php" class="menu-item active"><i class="fas fa-list-check"></i> Clearance Settings</a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <div class="page-header animate-in" style="animation-delay: 0.05s;">
            <h2><i class="fas fa-list-check"></i> Clearance Settings</h2>
            <p>Configure the graduation clearance process and workflow.</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert-custom alert-success animate-in" style="animation-delay: 0.1s;">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success_message; ?></span>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert-custom alert-danger animate-in" style="animation-delay: 0.1s;">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error_message; ?></span>
                <button class="close-btn" onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- ============================================================
             CLEARANCE PERIOD
        ============================================================ -->
        <div class="settings-card animate-in" style="animation-delay: 0.15s;">
            <div class="card-title">
                <i class="fas fa-calendar-alt"></i> Clearance Period
            </div>
            <div class="card-subtitle">
                Define when students can submit clearance applications.
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="clearance_start">Clearance Start Date</label>
                        <input type="date" class="form-control" id="clearance_start" name="clearance_start" 
                               value="<?php echo htmlspecialchars($clearance_start); ?>">
                        <div class="help-text">Students can start submitting clearance on this date</div>
                    </div>
                    <div class="form-group">
                        <label for="clearance_end">Clearance End Date</label>
                        <input type="date" class="form-control" id="clearance_end" name="clearance_end" 
                               value="<?php echo htmlspecialchars($clearance_end); ?>">
                        <div class="help-text">Students must complete clearance by this date</div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_clearance" class="btn-save">
                        <i class="fas fa-save"></i> Save Clearance Period
                    </button>
                </div>
            </form>
        </div>
        
        <!-- ============================================================
             CLEARANCE WORKFLOW
        ============================================================ -->
        <div class="settings-card animate-in" style="animation-delay: 0.2s;">
            <div class="card-title">
                <i class="fas fa-arrows-alt-v"></i> Clearance Workflow
            </div>
            <div class="card-subtitle">
                Define the order in which departments clear students. Drag and drop to reorder.
            </div>
            
            <form method="POST" id="workflowForm">
                <div class="workflow-list" id="workflowList">
                    <?php 
                    $order = 1;
                    $total_depts = count($departments);
                    foreach ($departments as $dept): 
                        $is_last = ($order == $total_depts);
                    ?>
                    <div class="workflow-item" draggable="true" data-id="<?php echo $dept['id']; ?>">
                        <span class="drag-handle"><i class="fas fa-grip-lines"></i></span>
                        <span class="order-number"><?php echo $order; ?></span>
                        <div class="dept-info">
                            <strong><?php echo htmlspecialchars($dept['department_name']); ?></strong>
                            <small><?php echo htmlspecialchars($dept['department_code']); ?></small>
                        </div>
                        <?php if ($is_last): ?>
                            <span class="final-badge"><i class="fas fa-flag-checkered me-1"></i> Final</span>
                        <?php endif; ?>
                        <input type="number" name="dept_order[<?php echo $dept['id']; ?>]" 
                               value="<?php echo $dept['clearance_order'] ?? $order; ?>" min="1" max="<?php echo $total_depts; ?>">
                    </div>
                    <?php 
                        $order++;
                    endforeach; 
                    ?>
                </div>
                
                <div class="help-text" style="margin-top: 10px; color: var(--gray-600); font-size: 0.85rem;">
                    <i class="fas fa-info-circle me-1" style="color: var(--primary);"></i>
                    Drag and drop departments to change the clearance order. The <strong>Academic Registry</strong> should normally be the final department.
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_workflow" class="btn-save">
                        <i class="fas fa-save"></i> Save Workflow Order
                    </button>
                    <button type="reset" class="btn-reset" onclick="location.reload();">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-custom');
            alerts.forEach(function(alert) {
                if (!alert.classList.contains('alert-danger')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }
            });
        }, 5000);
        
        // ============================================================
        // DRAG AND DROP WORKFLOW
        // ============================================================
        const workflowList = document.getElementById('workflowList');
        let dragItem = null;
        
        if (workflowList) {
            workflowList.addEventListener('dragstart', function(e) {
                const item = e.target.closest('.workflow-item');
                if (item) {
                    dragItem = item;
                    item.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', item.dataset.id);
                }
            });
            
            workflowList.addEventListener('dragend', function(e) {
                const item = e.target.closest('.workflow-item');
                if (item) {
                    item.classList.remove('dragging');
                }
            });
            
            workflowList.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                
                const target = e.target.closest('.workflow-item');
                if (target && target !== dragItem) {
                    const rect = target.getBoundingClientRect();
                    const midY = rect.top + rect.height / 2;
                    
                    if (e.clientY < midY) {
                        workflowList.insertBefore(dragItem, target);
                    } else {
                        workflowList.insertBefore(dragItem, target.nextSibling);
                    }
                }
            });
            
            workflowList.addEventListener('drop', function(e) {
                e.preventDefault();
                updateOrderNumbers();
            });
        }
        
        function updateOrderNumbers() {
            const items = document.querySelectorAll('.workflow-item');
            const orderInputs = document.querySelectorAll('.workflow-item input[type="number"]');
            
            items.forEach(function(item, index) {
                const orderNum = index + 1;
                const orderSpan = item.querySelector('.order-number');
                if (orderSpan) {
                    orderSpan.textContent = orderNum;
                }
                
                const input = item.querySelector('input[type="number"]');
                if (input) {
                    input.value = orderNum;
                }
            });
        }
    </script>
</body>
</html>