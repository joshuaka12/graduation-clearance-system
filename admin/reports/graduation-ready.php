<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

// Get filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Query to get students who have completed ALL clearances with certificate status
$sql = "
    SELECT 
        u.id,
        u.full_name,
        u.student_id,
        u.email,
        u.phone,
        u.created_at as registered_date,
        COUNT(DISTINCT ci.id) as total_requirements,
        COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) as completed_requirements,
        SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        MAX(sc.updated_at) as last_activity,
        ROUND((COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) / NULLIF(COUNT(DISTINCT ci.id), 0)) * 100, 1) as completion_percentage,
        CASE 
            WHEN COUNT(DISTINCT CASE WHEN sc.status = 'approved' THEN ci.id END) = COUNT(DISTINCT ci.id) THEN 'ready'
            WHEN SUM(CASE WHEN sc.status = 'rejected' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
            ELSE 'in_progress'
        END as graduation_status,
        (SELECT COUNT(*) FROM clearance_certificates WHERE student_id = u.id) as has_certificate
    FROM users u
    CROSS JOIN clearance_items ci
    LEFT JOIN student_clearance sc ON ci.id = sc.clearance_item_id AND sc.student_id = u.id
    WHERE u.role = 'student' AND u.is_active = 1
      AND ci.department_id IN (SELECT id FROM departments WHERE is_active = 1)
    GROUP BY u.id
    HAVING graduation_status = 'ready'
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY u.full_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get statistics
$total_ready = count($students);

// Get all students count
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1");
$total_students = $stmt->fetch()['count'];

$completion_rate = $total_students > 0 ? round(($total_ready / $total_students) * 100, 1) : 0;

// Check for success/error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success']);
unset($_SESSION['error']);

$page_title = 'Graduation Ready';
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
            padding: 20px 25px;
            transition: all 0.3s;
        }
        
        .page-header {
            margin-bottom: 20px;
        }
        
        .page-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
        }
        
        .page-header h2 i {
            color: var(--primary);
        }
        
        .page-header p {
            color: var(--gray-600);
            margin: 3px 0 0;
            font-size: 0.8rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 15px 18px;
            border: 1px solid rgba(0,0,0,0.04);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(128,0,32,0.08);
        }
        
        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }
        
        .stat-number {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--gray-800);
            line-height: 1.2;
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.7rem;
            font-weight: 500;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 12px 18px;
            margin-bottom: 18px;
            border: 1px solid rgba(0,0,0,0.04);
        }
        
        .form-label {
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 3px;
            color: var(--gray-700);
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid var(--gray-200);
            padding: 6px 12px;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-soft);
            outline: none;
        }
        
        /* Cards */
        .card-modern {
            background: white;
            border-radius: 14px;
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            overflow: hidden;
        }
        
        .card-header-custom {
            padding: 12px 18px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .card-header-custom h5 {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .card-header-custom h5 i {
            color: var(--primary);
            margin-right: 6px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            min-width: 500px;
            margin-bottom: 0;
        }
        
        .table th {
            background: var(--gray-100);
            padding: 8px 12px;
            font-weight: 600;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
        }
        
        .table td {
            padding: 8px 12px;
            font-size: 0.8rem;
            vertical-align: middle;
        }
        
        .table tr:hover td {
            background: var(--gray-50);
        }
        
        .certificate-issued-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 12px;
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .certificate-missing-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 12px;
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .progress {
            background: var(--gray-200);
            border-radius: 8px;
            overflow: hidden;
            height: 4px;
            width: 70px;
        }
        
        .progress-bar {
            background: linear-gradient(90deg, var(--success), #059669);
            height: 100%;
        }
        
        .btn-export {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            padding: 5px 14px;
            border-radius: 30px;
            font-size: 0.65rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(128,0,32,0.2);
        }
        
        /* Alert Messages */
        .alert-custom {
            padding: 8px 14px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.15);
        }
        
        .alert-danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.15);
        }
        
        .empty-state {
            padding: 30px 20px;
            text-align: center;
            color: var(--gray-500);
        }
        
        .empty-state i {
            font-size: 2.5rem;
            color: var(--gray-300);
            margin-bottom: 10px;
            display: block;
        }
        
        .empty-state p {
            margin: 0;
            font-size: 0.85rem;
        }
        
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
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
                padding: 15px 12px;
            }
            
            .page-header h2 {
                font-size: 1.2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            
            .stat-card {
                padding: 12px 14px;
            }
            
            .stat-number {
                font-size: 1.2rem;
            }
            
            .stat-icon {
                width: 36px;
                height: 36px;
                font-size: 0.9rem;
            }
            
            .card-header-custom {
                flex-direction: column;
                align-items: flex-start;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 10px 8px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .stat-card {
                padding: 10px 12px;
            }
            
            .table td, .table th {
                padding: 6px 8px;
                font-size: 0.7rem;
            }
            
            .filter-bar {
                padding: 10px 12px;
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
            <a href="index.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="graduation-ready.php" class="menu-item active">
                <i class="fas fa-graduation-cap"></i>
                <span>Graduation Ready</span>
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
            <h2><i class="fas fa-graduation-cap me-2"></i> Graduation Ready</h2>
            <p>Students who have completed all clearance requirements</p>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert-custom alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert-custom alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="fas fa-user-check"></i></div>
                <div>
                    <div class="stat-number"><?php echo $total_ready; ?></div>
                    <div class="stat-label">Ready for Graduation</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(128,0,32,0.1); color: var(--primary);"><i class="fas fa-users"></i></div>
                <div>
                    <div class="stat-number"><?php echo number_format($total_students); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: var(--info);"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div class="stat-number"><?php echo $completion_rate; ?>%</div>
                    <div class="stat-label">Completion Rate</div>
                </div>
            </div>
        </div>
        
        <!-- Search Bar -->
        <div class="filter-bar">
            <div class="row g-2 align-items-end">
                <div class="col-12">
                    <label class="form-label">Search Students</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name, student ID or email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
        </div>
        
        <!-- Students Table -->
        <div class="card-modern">
            <div class="card-header-custom">
                <h5><i class="fas fa-user-graduate"></i> Students List</h5>
                <button class="btn-export" onclick="exportToExcel()"><i class="fas fa-file-excel me-1"></i> Export</button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="studentsTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Progress</th>
                            <th>Certificate</th>
                        </tr>
                    </thead>
                    <tbody id="studentsTableBody">
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="fas fa-inbox"></i>
                                        <p>No students have completed all clearance requirements yet</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $counter = 1; foreach ($students as $student): 
                                $has_cert = $student['has_certificate'] > 0;
                            ?>
                            <tr data-search="<?php echo strtolower($student['full_name'] . ' ' . $student['student_id'] . ' ' . $student['email']); ?>">
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                    <br><small class="text-muted">Registered: <?php echo date('M d, Y', strtotime($student['registered_date'])); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress">
                                            <div class="progress-bar" style="width: <?php echo $student['completion_percentage']; ?>%;"></div>
                                        </div>
                                        <span class="small"><?php echo $student['completed_requirements']; ?>/<?php echo $student['total_requirements']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($has_cert): ?>
                                        <span class="certificate-issued-badge">
                                            <i class="fas fa-check-circle"></i> Issued
                                        </span>
                                    <?php else: ?>
                                        <span class="certificate-missing-badge">
                                            <i class="fas fa-times-circle"></i> Not Issued
                                        </span>
                                    <?php endif; ?>
                                </td>
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
        // ============================================================
        // SIDEBAR FUNCTIONS
        // ============================================================
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
        
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        // ============================================================
        // AUTO-SEARCH WITHOUT REFRESH
        // ============================================================
        document.getElementById('searchInput').addEventListener('input', function() {
            var searchTerm = this.value.toLowerCase().trim();
            var rows = document.querySelectorAll('#studentsTableBody tr');
            var visibleCount = 0;
            
            rows.forEach(function(row) {
                // Skip empty state rows
                if (row.querySelector('.empty-state')) {
                    return;
                }
                
                var searchData = row.getAttribute('data-search') || '';
                var matches = searchTerm === '' || searchData.includes(searchTerm);
                
                if (matches) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Show empty state if no results
            if (visibleCount === 0) {
                var tbody = document.getElementById('studentsTableBody');
                var existingEmpty = tbody.querySelector('.empty-state');
                if (!existingEmpty) {
                    var emptyRow = tbody.querySelector('tr:last-child');
                    if (emptyRow && emptyRow.querySelector('.empty-state')) {
                        // Already exists
                    } else {
                        var tr = document.createElement('tr');
                        tr.innerHTML = '<td colspan="5"><div class="empty-state"><i class="fas fa-search"></i><p>No students found matching your search</p></div></td>';
                        tbody.appendChild(tr);
                    }
                }
            } else {
                var emptyStateRow = document.querySelector('#studentsTableBody tr .empty-state');
                if (emptyStateRow) {
                    emptyStateRow.closest('tr').remove();
                }
            }
        });
        
        // ============================================================
        // EXPORT FUNCTION
        // ============================================================
        function exportToExcel() {
            var url = 'export-graduation-ready.php?';
            var search = document.querySelector('#searchInput').value;
            if (search) url += 'search=' + encodeURIComponent(search);
            window.location.href = url;
        }
    </script>
</body>
</html>