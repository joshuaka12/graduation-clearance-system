<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/functions.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Handle status update
if (isset($_GET['toggle']) && isset($_GET['status'])) {
    $item_id = $_GET['toggle'];
    $status = $_GET['status'] == '1' ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE clearance_items SET is_mandatory = ? WHERE id = ?");
    $stmt->execute([$status, $item_id]);
    
    header("Location: index.php");
    exit();
}

// Get all clearance items with department info
$stmt = $pdo->query("
    SELECT ci.*, d.department_name, d.department_code,
           COUNT(sc.id) as usage_count,
           SUM(CASE WHEN sc.status = 'approved' THEN 1 ELSE 0 END) as approved_count
    FROM clearance_items ci
    LEFT JOIN departments d ON ci.department_id = d.id
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id
    GROUP BY ci.id
    ORDER BY d.department_name ASC, ci.sort_order ASC
");
$items = $stmt->fetchAll();

// Get departments for filter
$stmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

$page_title = 'Clearance Items';
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
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #800020;
            --primary-dark: #5a0016;
            --primary-light: #9e0028;
            --primary-soft: rgba(128, 0, 32, 0.08);
            --primary-glow: rgba(128, 0, 32, 0.15);
            --gray-100: #f8f9fc;
            --gray-200: #e4e7ef;
            --gray-600: #6c7293;
            --gray-800: #2d3047;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
            box-shadow: 10px 0 30px rgba(128, 0, 32, 0.2);
        }
        
        .sidebar.closed {
            transform: translateX(-100%);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-light);
            border-radius: 10px;
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
        
        .sidebar-header h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 10px 0 5px;
            color: white;
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
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .menu-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
        }
        
        .menu-item i {
            width: 24px;
            font-size: 1.1rem;
        }
        
        .menu-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
            margin: 20px 0;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 25px 30px;
            transition: all 0.3s;
        }
        
        .main-content.expanded {
            margin-left: 0;
        }
        
        /* Overlay */
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
        
        /* Page Header */
        .page-header {
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
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
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(128, 0, 32, 0.3);
            color: white;
        }
        
        .btn-outline-custom {
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 9px 20px;
            border-radius: 12px;
            font-weight: 500;
            background: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-outline-custom:hover {
            background: var(--primary);
            color: white;
        }
        
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
            overflow: hidden;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-mandatory {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-optional {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .document-badge {
            padding: 4px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .action-btn.edit { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        .action-btn.toggle { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .action-btn.delete { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .action-btn.view { background: rgba(59, 130, 246, 0.1); color: var(--info); }
        
        .action-btn:hover {
            transform: scale(1.05);
        }
        
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .form-select, .form-control {
            border-radius: 10px;
            border-color: var(--gray-200);
            font-size: 0.85rem;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
        }
        
        /* DataTables Responsive */
        .dataTables_wrapper {
            padding: 0 15px 15px;
        }
        
        .dataTables_length, .dataTables_filter {
            margin: 15px;
        }
        
        .dataTables_filter input {
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            padding: 8px 12px;
        }
        
        table.dataTable {
            border-collapse: collapse !important;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start !important;
            }
            
            .page-header > div:last-child {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
        }
        
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
            
            .filter-bar .row > div {
                margin-bottom: 10px;
            }
            
            .dataTables_wrapper {
                overflow-x: auto;
            }
            
            table.dataTable {
                min-width: 600px;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .btn-primary-custom, .btn-outline-custom {
                padding: 8px 14px;
                font-size: 0.8rem;
            }
            
            .action-buttons {
                gap: 5px;
            }
            
            .action-btn {
                width: 28px;
                height: 28px;
            }
            
            .filter-bar {
                padding: 12px 15px;
            }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-in {
            animation: fadeInUp 0.4s ease forwards;
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
            <div class="logo-icon">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h4>Clearance System</h4>
            <p>Administrator Panel</p>
        </div>
        
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="../departments/index.php" class="menu-item">
                <i class="fas fa-building"></i>
                <span>Departments</span>
            </a>
            <a href="index.php" class="menu-item active">
                <i class="fas fa-list-check"></i>
                <span>Clearance Items</span>
            </a>
            <div class="menu-divider"></div>
            <a href="../users/index.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <a href="../clearances/pending.php" class="menu-item">
                <i class="fas fa-clock"></i>
                <span>Pending Clearances</span>
            </a>
            <div class="menu-divider"></div>
            <a href="../reports/index.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="../settings/index.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h2><i class="fas fa-list-check me-2"></i> Clearance Items</h2>
                <p>Manage all clearance requirements across departments</p>
            </div>
            <div>
                <a href="add.php" class="btn-primary-custom me-2">
                    <i class="fas fa-plus me-1"></i> Add New
                </a>
                <a href="assign.php" class="btn-outline-custom">
                    <i class="fas fa-exchange-alt me-1"></i> Assign
                </a>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="row g-3">
                <div class="col-md-4 col-sm-6">
                    <select id="departmentFilter" class="form-select">
                        <option value="all">📁 All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-sm-6">
                    <select id="typeFilter" class="form-select">
                        <option value="all">📋 All Types</option>
                        <option value="mandatory">✓ Mandatory Items</option>
                        <option value="optional">○ Optional Items</option>
                    </select>
                </div>
                <div class="col-md-4 col-sm-12">
                    <select id="documentFilter" class="form-select">
                        <option value="all">📄 All Items</option>
                        <option value="yes">📎 Requires Document</option>
                        <option value="no">✗ No Document Required</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="card-modern">
            <div class="table-responsive">
                <table id="itemsTable" class="table table-hover mb-0">
                    <thead style="background: var(--gray-100);">
                        <tr>
                            <th>#</th>
                            <th>Item Name</th>
                            <th>Department</th>
                            <th>Description</th>
                            <th>Document</th>
                            <th>Type</th>
                            <th>Usage</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; foreach ($items as $item): ?>
                        <tr data-department="<?php echo $item['department_id']; ?>" 
                            data-type="<?php echo $item['is_mandatory'] ? 'mandatory' : 'optional'; ?>"
                            data-document="<?php echo $item['requires_document'] ? 'yes' : 'no'; ?>">
                            <td><?php echo $counter++; ?></td>
                            <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                            <td>
                                <span class="badge" style="background: rgba(128,0,32,0.1); color: var(--primary);">
                                    <?php echo htmlspecialchars($item['department_code']); ?>
                                </span>
                             </div>
                            <td><?php echo substr(htmlspecialchars($item['description']), 0, 50); ?><?php echo strlen($item['description']) > 50 ? '...' : ''; ?></div>
                            <td>
                                <?php if ($item['requires_document']): ?>
                                    <span class="document-badge">
                                        <i class="fas fa-file-pdf"></i> Required
                                    </span>
                                <?php else: ?>
                                    <span class="document-badge" style="background: rgba(108, 114, 147, 0.1); color: var(--gray-600);">
                                        <i class="fas fa-times"></i> Not Required
                                    </span>
                                <?php endif; ?>
                             </div>
                            <td>
                                <span class="status-badge <?php echo $item['is_mandatory'] ? 'status-mandatory' : 'status-optional'; ?>">
                                    <?php echo $item['is_mandatory'] ? 'Mandatory' : 'Optional'; ?>
                                </span>
                             </div>
                            <td>
                                <small class="text-muted">
                                    <i class="fas fa-users me-1"></i> <?php echo $item['usage_count']; ?> students
                                </small>
                             </div>
                            <td>
                                <div class="action-buttons">
                                    <a href="view.php?id=<?php echo $item['id']; ?>" class="action-btn view" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $item['id']; ?>" class="action-btn edit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?toggle=<?php echo $item['id']; ?>&status=<?php echo $item['is_mandatory'] ? 0 : 1; ?>" 
                                       class="action-btn toggle" title="Toggle Type"
                                       onclick="return confirm('Change item type?')">
                                        <i class="fas fa-exchange-alt"></i>
                                    </a>
                                    <a href="delete.php?id=<?php echo $item['id']; ?>" 
                                       class="action-btn delete" title="Delete"
                                       onclick="return confirm('Delete this clearance item? All related student data will be lost!')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                             </div>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block" style="color: var(--gray-300);"></i>
                                    <p class="text-muted">No clearance items found</p>
                                    <a href="add.php" class="btn-primary-custom btn-sm mt-2">Add New Item</a>
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
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
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
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        $(document).ready(function() {
            var table = $('#itemsTable').DataTable({
                pageLength: 15,
                order: [[2, 'asc']],
                responsive: true,
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ items",
                    info: "Showing _START_ to _END_ of _TOTAL_ items",
                    emptyTable: "No clearance items found"
                }
            });
            
            // Department filter
            $('#departmentFilter').on('change', function() {
                var deptId = $(this).val();
                if (deptId === 'all') {
                    table.columns(2).search('').draw();
                } else {
                    var deptName = $(this).find('option:selected').text();
                    table.columns(2).search(deptName).draw();
                }
            });
            
            // Type filter
            $('#typeFilter').on('change', function() {
                var type = $(this).val();
                if (type === 'all') {
                    table.columns(5).search('').draw();
                } else if (type === 'mandatory') {
                    table.columns(5).search('Mandatory').draw();
                } else {
                    table.columns(5).search('Optional').draw();
                }
            });
            
            // Document filter
            $('#documentFilter').on('change', function() {
                var doc = $(this).val();
                if (doc === 'all') {
                    table.columns(4).search('').draw();
                } else if (doc === 'yes') {
                    table.columns(4).search('Required').draw();
                } else {
                    table.columns(4).search('Not Required').draw();
                }
            });
        });
    </script>
</body>
</html>