<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Get departments for filter
$stmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

// Build query for clearance records
$sql = "
    SELECT 
        sc.*,
        u.full_name,
        u.student_id,
        u.email,
        u.phone,
        d.department_name,
        d.department_code,
        ci.item_name,
        ci.requires_document,
        reviewer.full_name as reviewed_by_name
    FROM student_clearance sc
    JOIN users u ON sc.student_id = u.id
    JOIN clearance_items ci ON sc.clearance_item_id = ci.id
    JOIN departments d ON ci.department_id = d.id
    LEFT JOIN users reviewer ON sc.reviewed_by = reviewer.id
    WHERE 1=1
";

$params = [];

if ($date_from && $date_to) {
    $sql .= " AND DATE(sc.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
}

if ($department_filter > 0) {
    $sql .= " AND d.id = ?";
    $params[] = $department_filter;
}

if ($status_filter !== 'all') {
    $sql .= " AND sc.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY sc.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clearances = $stmt->fetchAll();

// Get statistics
$sql_stats = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
        ROUND((SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 1) as approval_rate
    FROM student_clearance sc
    WHERE 1=1
";

$params_stats = [];

if ($date_from && $date_to) {
    $sql_stats .= " AND DATE(sc.created_at) BETWEEN ? AND ?";
    $params_stats[] = $date_from;
    $params_stats[] = $date_to;
}

if ($department_filter > 0) {
    $sql_stats .= " AND department_id IN (SELECT id FROM clearance_items WHERE department_id = ?)";
    $params_stats[] = $department_filter;
}

$stmt_stats = $pdo->prepare($sql_stats);
$stmt_stats->execute($params_stats);
$stats = $stmt_stats->fetch();

// Get daily trends
$sql_trends = "
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM student_clearance
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
";
$stmt_trends = $pdo->query($sql_trends);
$daily_trends = $stmt_trends->fetchAll();

$page_title = 'Clearance Report';
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Sidebar - EXACT MATCH to Admin Dashboard */
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--gray-200);
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(128,0,32,0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 12px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--gray-800);
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.8rem;
        }
        
        .stat-trend {
            font-size: 0.7rem;
            margin-top: 5px;
            color: var(--primary);
            font-weight: 500;
        }
        
        .card-modern {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            overflow: hidden;
            margin-bottom: 25px;
        }
        
        .card-header-custom {
            padding: 18px 25px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
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
        
        .chart-container {
            height: 300px;
            padding: 20px;
        }
        
        .btn-export {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            border: none;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-excel {
            background: #28a745;
            color: white;
        }
        
        .btn-pdf {
            background: #dc3545;
            color: white;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            filter: brightness(1.05);
            color: white;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-approved { background: rgba(16,185,129,0.1); color: var(--success); }
        .status-pending { background: rgba(245,158,11,0.1); color: var(--warning); }
        .status-rejected { background: rgba(239,68,68,0.1); color: var(--danger); }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            min-width: 800px;
        }
        
        .table th {
            background: var(--gray-100);
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .table td {
            padding: 10px 15px;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 992px) {
            .stats-grid { gap: 15px; }
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .card-header-custom {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-bar .row > div {
                margin-bottom: 10px;
            }
        }
        
        @media print {
            .sidebar, .filter-bar, .card-header-custom .btn-export, .page-header .btn-export, .mobile-toggle, .sidebar-overlay, .no-print {
                display: none !important;
            }
            .main-content { margin-left: 0; padding: 0; }
            .stat-card, .card-modern { box-shadow: none; border: 1px solid #ddd; }
            .stats-grid { grid-template-columns: repeat(4, 1fr); }
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
    
    <!-- Sidebar - EXACT MATCH to Admin Dashboard -->
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
            <div class="menu-divider"></div>
            <a href="../users/index.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <div class="menu-divider"></div>
            <a href="index.php" class="menu-item active">
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
        <div class="page-header">
            <h2><i class="fas fa-file-alt me-2"></i> Clearance Report</h2>
            <div>
                <a href="export-excel.php?type=clearance&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&department=<?php echo $department_filter; ?>&status=<?php echo $status_filter; ?>" class="btn-export btn-excel me-2">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <button class="btn-export btn-pdf" onclick="window.print()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn w-100" style="background: var(--primary); color: white; border: none; padding: 10px; border-radius: 10px;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(128,0,32,0.1); color: var(--primary);"><i class="fas fa-list"></i></div>
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">Total Clearances</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['approved'] ?? 0); ?></div>
                <div class="stat-label">Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo number_format($stats['pending'] ?? 0); ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: var(--danger);"><i class="fas fa-times-circle"></i></div>
                <div class="stat-number"><?php echo number_format($stats['rejected'] ?? 0); ?></div>
                <div class="stat-label">Rejected</div>
                <div class="stat-trend">Approval Rate: <?php echo $stats['approval_rate'] ?? 0; ?>%</div>
            </div>
        </div>
        
        <!-- Daily Trends Chart -->
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-chart-line"></i> Daily Clearance Trends (Last 14 Days)</h5>
            </div>
            <div class="chart-container">
                <canvas id="trendsChart"></canvas>
            </div>
        </div>
        
        <!-- Clearance Records Table -->
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-table"></i> Clearance Records</h5>
                <span class="text-muted small">Total: <?php echo count($clearances); ?> records</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Department</th>
                            <th>Item</th>
                            <th>Status</th>
                            <th>Reviewed By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clearances)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x mb-3 d-block" style="color: var(--gray-300);"></i>
                                    <p class="mb-0">No clearance records found</p>
                                 </td>
                              </tr>
                        <?php else: ?>
                            <?php foreach ($clearances as $clearance): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($clearance['created_at'])); ?></div>
                                <td>
                                    <strong><?php echo htmlspecialchars($clearance['full_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo $clearance['email']; ?></small>
                                 </div>
                                <td><?php echo $clearance['student_id']; ?></div>
                                <td><?php echo htmlspecialchars($clearance['department_name']); ?></div>
                                <td><?php echo htmlspecialchars($clearance['item_name']); ?></div>
                                <td>
                                    <?php if ($clearance['status'] == 'approved'): ?>
                                        <span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Approved</span>
                                    <?php elseif ($clearance['status'] == 'rejected'): ?>
                                        <span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>
                                    <?php else: ?>
                                        <span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>
                                    <?php endif; ?>
                                 </div>
                                <td><?php echo htmlspecialchars($clearance['reviewed_by_name'] ?? '—'); ?></div>
                                <td><?php echo htmlspecialchars($clearance['remarks'] ?: '—'); ?></div>
                            </tr>
                            <?php endforeach; ?>
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
            var sidebar = document.getElementById('sidebar');
            var overlay = document.querySelector('.sidebar-overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        }
        
        function closeSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.querySelector('.sidebar-overlay');
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
        
        // Daily Trends Chart
        var canvas = document.getElementById('trendsChart');
        if (canvas) {
            var ctx = canvas.getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($daily_trends, 'date')); ?>,
                    datasets: [{
                        label: 'Total Clearances',
                        data: <?php echo json_encode(array_column($daily_trends, 'total')); ?>,
                        borderColor: '#800020',
                        backgroundColor: 'rgba(128, 0, 32, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Approved',
                        data: <?php echo json_encode(array_column($daily_trends, 'approved')); ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.05)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, title: { display: true, text: 'Number of Clearances' } } }
                }
            });
        }
    </script>
</body>
</html>