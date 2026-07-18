<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/chat_functions.php';

if (!isLoggedIn() || !isDepartmentHead()) {
    redirect('../../auth/login.php');
}

// Check if department is selected
if (!isset($_SESSION['selected_department_id'])) {
    redirect('../select-department.php');
}

$department_id = $_SESSION['selected_department_id'];

// Get clearance items for this department
$stmt = $pdo->prepare("
    SELECT ci.*
    FROM clearance_items ci
    WHERE ci.department_id = ?
    ORDER BY ci.id ASC
");
$stmt->execute([$department_id]);
$items = $stmt->fetchAll();

// Get unread messages count
$unread_messages = getUnreadMessageCount($pdo, $_SESSION['user_id']);

$page_title = 'Clearance Items';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $page_title; ?> - Department Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128,0,32,0.08);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-600: #6c7293;
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
        }
        
        /* Mobile Menu Toggle */
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
        
        .mobile-toggle:hover {
            background: var(--primary-dark);
        }
        
        /* Sidebar */
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
            box-shadow: 10px 0 30px rgba(128,0,32,0.2);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }
        
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
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        
        .sidebar-header h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 10px 0 5px;
        }
        
        .sidebar-header p {
            font-size: 0.75rem;
            opacity: 0.7;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 0 15px 20px;
        }
        
        .menu-item {
            padding: 12px 15px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 500;
            position: relative;
        }
        
        .menu-item:hover {
            background: rgba(255,255,255,0.15);
            color: white;
            transform: translateX(5px);
        }
        
        .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 5px 15px rgba(128,0,32,0.3);
        }
        
        .menu-item i {
            width: 22px;
            font-size: 1rem;
        }
        
        .menu-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 20px 0;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Sidebar Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
            display: none;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
            transition: all 0.3s;
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
        
        .page-header h2 i {
            color: var(--primary);
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 5px 0 0;
            font-size: 0.85rem;
        }
        
        /* Professional Add Button */
        .btn-add {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128,0,32,0.25);
            color: white;
        }
        
        .card-modern {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        
        .card-header-custom {
            padding: 18px 25px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            min-width: 700px;
            margin-bottom: 0;
        }
        
        .table th {
            background: var(--gray-100);
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--gray-800);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table td {
            padding: 12px 15px;
            font-size: 0.85rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            text-decoration: none;
            margin: 0 2px;
        }
        
        .action-btn.edit { background: rgba(245,158,11,0.1); color: var(--warning); }
        .action-btn.delete { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .action-btn:hover {
            transform: scale(1.05);
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--gray-700);
        }
        
        .empty-state p {
            color: var(--gray-500);
            margin-bottom: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .mobile-toggle {
                display: flex;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
            
            .page-header h2 {
                font-size: 1.4rem;
            }
            
            .btn-add {
                padding: 6px 16px;
                font-size: 0.8rem;
            }
            
            .card-header-custom {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .table th, .table td {
                padding: 8px 10px;
                font-size: 0.75rem;
            }
            
            .action-btn {
                width: 28px;
                height: 28px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-graduation-cap"></i></div>
            <h4>Clearance System</h4>
            <p>Department Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="index.php" class="menu-item active"><i class="fas fa-list-check"></i> Clearance Items</a>
            <a href="../students.php" class="menu-item"><i class="fas fa-users"></i> Students</a>
            <a href="../reports.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="../message.php" class="menu-item">
                <i class="fas fa-comments"></i> Messages
                <?php if ($unread_messages > 0): ?>
                    <span class="notification-badge"><?php echo $unread_messages; ?></span>
                <?php endif; ?>
            </a>
            <div class="menu-divider"></div>
            <a href="../profile.php" class="menu-item"><i class="fas fa-user"></i> Profile</a>
            <a href="../change-password.php" class="menu-item"><i class="fas fa-key"></i> Change Password</a>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h2><i class="fas fa-list-check me-2"></i> Clearance Items</h2>
            <p>Manage clearance requirements for your department</p>
        </div>
        
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-building"></i> <?php echo htmlspecialchars($_SESSION['selected_department_name'] ?? 'Department'); ?> - Clearance Requirements</h5>
                <a href="add.php" class="btn-add">
                    <i class="fas fa-plus"></i> Add Item
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($items as $item): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                            <td><?php echo substr(htmlspecialchars($item['description']), 0, 60); ?><?php echo strlen($item['description']) > 60 ? '...' : ''; ?></td>
                            <td>
                                <?php if ($item['requires_document']): ?>
                                    <span class="badge" style="background: rgba(59,130,246,0.1); color: var(--info);">
                                        <i class="fas fa-file-pdf"></i> Required
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(108,114,147,0.1); color: var(--gray-600);">
                                        <i class="fas fa-times"></i> Not Required
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['is_mandatory']): ?>
                                    <span class="badge" style="background: rgba(16,185,129,0.1); color: var(--success);">
                                        <i class="fas fa-star"></i> Mandatory
                                    </span>
                                <?php else: ?>
                                    <span class="badge" style="background: rgba(245,158,11,0.1); color: var(--warning);">
                                        <i class="fas fa-star-of-life"></i> Optional
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="edit.php?id=<?php echo $item['id']; ?>" class="action-btn edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="delete.php?id=<?php echo $item['id']; ?>" class="action-btn delete" title="Delete" 
                                   onclick="return confirm('Delete this clearance item? This will affect all students.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h4>No Clearance Items Yet</h4>
                                    <p>Click the "Add Item" button to create your first clearance requirement</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar Toggle for Mobile
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
    </script>
</body>
</html>