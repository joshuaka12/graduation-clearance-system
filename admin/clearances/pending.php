<?php
session_start();
require_once '../../config/config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../../auth/login.php');
}

$error = '';
$success = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clearance_id = (int)$_POST['clearance_id'];
    $action = $_POST['action'];
    $remarks = sanitizeInput($_POST['remarks'] ?? '');
    
    if ($action == 'approve') {
        $stmt = $pdo->prepare("
            UPDATE student_clearance 
            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW(), remarks = ? 
            WHERE id = ?
        ");
        if ($stmt->execute([$_SESSION['user_id'], 'Approved by admin', $clearance_id])) {
            $success = "Clearance approved successfully!";
            logActivity($pdo, $_SESSION['user_id'], 'Approve Clearance', "Approved clearance ID: $clearance_id");
        } else {
            $error = "Failed to approve clearance.";
        }
    } elseif ($action == 'reject') {
        if (empty($remarks)) {
            $error = "Please provide a reason for rejection.";
        } else {
            $stmt = $pdo->prepare("
                UPDATE student_clearance 
                SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), remarks = ? 
                WHERE id = ?
            ");
            if ($stmt->execute([$_SESSION['user_id'], $remarks, $clearance_id])) {
                $success = "Clearance rejected successfully!";
                logActivity($pdo, $_SESSION['user_id'], 'Reject Clearance', "Rejected clearance ID: $clearance_id");
            } else {
                $error = "Failed to reject clearance.";
            }
        }
    }
    
    if ($success) {
        header("refresh:2;url=pending.php");
    }
}

// Get filter parameters
$department_filter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query for pending clearances
$sql = "
    SELECT sc.*, u.full_name, u.student_id, u.email, u.phone,
           d.department_name, d.department_code, ci.item_name, ci.requires_document,
           reviewer.full_name as reviewed_by_name
    FROM student_clearance sc
    JOIN users u ON sc.student_id = u.id
    JOIN clearance_items ci ON sc.clearance_item_id = ci.id
    JOIN departments d ON ci.department_id = d.id
    LEFT JOIN users reviewer ON sc.reviewed_by = reviewer.id
    WHERE sc.status = 'pending'
";

$params = [];

if ($department_filter > 0) {
    $sql .= " AND d.id = ?";
    $params[] = $department_filter;
}

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.student_id LIKE ? OR u.email LIKE ? OR ci.item_name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sql .= " ORDER BY sc.created_at ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pending_clearances = $stmt->fetchAll();

// Get departments for filter
$stmt = $pdo->query("SELECT id, department_name FROM departments WHERE is_active = 1 ORDER BY department_name");
$departments = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as count FROM student_clearance WHERE status = 'pending'");
$total_pending = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM student_clearance WHERE status = 'approved'");
$total_approved = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM student_clearance WHERE status = 'rejected'");
$total_rejected = $stmt->fetch()['count'];

$page_title = 'Pending Clearances';
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
        
        .menu-item i {
            width: 22px;
            font-size: 1rem;
        }
        
        .menu-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
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
            box-shadow: 0 10px 25px var(--primary-glow);
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
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(0,0,0,0.04);
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
        
        /* Table */
        .card {
            background: white;
            border-radius: 20px;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        
        .card-header {
            padding: 18px 25px;
            border-bottom: 1px solid var(--gray-200);
            background: white;
        }
        
        .card-header h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            color: var(--gray-800);
        }
        
        .card-header h5 i {
            color: var(--primary);
            margin-right: 8px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            min-width: 800px;
            margin-bottom: 0;
        }
        
        .table th {
            background: var(--gray-100);
            padding: 12px 15px;
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--gray-800);
        }
        
        .table td {
            padding: 12px 15px;
            font-size: 0.85rem;
            vertical-align: middle;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: rgba(245,158,11,0.1);
            color: var(--warning);
        }
        
        .btn-approve {
            background: var(--success);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }
        
        .btn-reject {
            background: var(--danger);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(239,68,68,0.3);
        }
        
        .btn-view {
            background: var(--info);
            color: white;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .modal-content {
            border-radius: 20px;
        }
        
        .modal-header {
            background: var(--primary);
            color: white;
            border-radius: 20px 20px 0 0;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .alert-custom {
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(16,185,129,0.1);
            color: var(--success);
            border: 1px solid rgba(16,185,129,0.2);
        }
        
        .alert-danger {
            background: rgba(239,68,68,0.1);
            color: var(--danger);
            border: 1px solid rgba(239,68,68,0.2);
        }
        
        /* Action Buttons Group */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                gap: 15px;
            }
            
            .stat-number {
                font-size: 1.5rem;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
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
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-number {
                font-size: 1.2rem;
            }
            
            .stat-label {
                font-size: 0.7rem;
            }
            
            .stat-icon {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
                margin-bottom: 8px;
            }
            
            .filter-bar .row > div {
                margin-bottom: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }
            
            .btn-approve, .btn-reject, .btn-view {
                padding: 4px 10px;
                font-size: 0.7rem;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 15px 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .stat-card {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 15px;
            }
            
            .stat-icon {
                margin-bottom: 0;
            }
            
            .card-header {
                padding: 15px 18px;
            }
            
            .table th, .table td {
                padding: 10px 12px;
                font-size: 0.8rem;
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
            <p>Administrator Panel</p>
        </div>
        <div class="sidebar-menu">
            <a href="../dashboard.php" class="menu-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="../departments/index.php" class="menu-item"><i class="fas fa-building"></i> Departments</a>
            <a href="../clearance-items/index.php" class="menu-item"><i class="fas fa-list-check"></i> Clearance Items</a>
            <div class="menu-divider"></div>
            <a href="../users/index.php" class="menu-item"><i class="fas fa-users"></i> Users</a>
            <a href="pending.php" class="menu-item active"><i class="fas fa-clock"></i> Pending Clearances</a>
            <div class="menu-divider"></div>
            <a href="../reports/index.php" class="menu-item"><i class="fas fa-chart-bar"></i> Reports</a>
            <a href="../settings/index.php" class="menu-item"><i class="fas fa-cog"></i> Settings</a>
            <div class="menu-divider"></div>
            <a href="../../auth/logout.php" class="menu-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h2><i class="fas fa-clock me-2"></i> Pending Clearances</h2>
            <p>Review and process student clearance requests</p>
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
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245,158,11,0.1); color: var(--warning);"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="stat-number"><?php echo $total_pending; ?></div>
                    <div class="stat-label">Pending Approvals</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: var(--success);"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="stat-number"><?php echo $total_approved; ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(239,68,68,0.1); color: var(--danger);"><i class="fas fa-times-circle"></i></div>
                <div>
                    <div class="stat-number"><?php echo $total_rejected; ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4 col-sm-12">
                    <label class="form-label">📁 Department</label>
                    <select name="department" class="form-select">
                        <option value="0">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 col-sm-8">
                    <label class="form-label">🔍 Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by student name, ID, email or item..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 col-sm-4">
                    <button type="submit" class="btn w-100" style="background: var(--primary); color: white; border: none; padding: 10px; border-radius: 10px;">
                        <i class="fas fa-search me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Pending Clearances Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Pending Clearance Requests</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Department</th>
                            <th>Item</th>
                            <th>Submitted</th>
                            <th>Document</th>
                            <th>Actions</th>
                        </thead>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_clearances)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-check-circle fa-3x mb-3" style="color: var(--gray-300);"></i>
                                    <p class="mb-0">✅ No pending clearance requests</p>
                                    <small class="text-muted">All clearances have been processed</small>
                                 </div>
                              </td>
                        <?php else: ?>
                            <?php foreach ($pending_clearances as $clearance): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($clearance['full_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo $clearance['email']; ?></small>
                                 </div>
                                <td><?php echo $clearance['student_id']; ?></div>
                                <td><?php echo htmlspecialchars($clearance['department_name']); ?></div>
                                <td><?php echo htmlspecialchars($clearance['item_name']); ?></div>
                                <td><?php echo date('M d, Y H:i', strtotime($clearance['created_at'])); ?></div>
                                <td>
                                    <?php if ($clearance['requires_document'] && $clearance['document_path']): ?>
                                        <a href="../../assets/uploads/documents/<?php echo $clearance['document_path']; ?>" class="btn-view" target="_blank">
                                            <i class="fas fa-file-pdf"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted"><i class="fas fa-times"></i> No document</span>
                                    <?php endif; ?>
                                 </div>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-approve" onclick="approveClearance(<?php echo $clearance['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn-reject" onclick="showRejectModal(<?php echo $clearance['id']; ?>, '<?php echo htmlspecialchars($clearance['full_name']); ?>')">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                 </div>
                              </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-times-circle me-2"></i> Reject Clearance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="clearance_id" id="reject_clearance_id">
                        <input type="hidden" name="action" value="reject">
                        <div class="mb-3">
                            <label class="form-label">Reason for Rejection</label>
                            <textarea name="remarks" class="form-control" rows="4" required placeholder="Please provide a reason for rejecting this clearance..."></textarea>
                            <small class="text-muted">⚠️ This reason will be visible to the student</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn" style="background: var(--danger); color: white;">Confirm Rejection</button>
                    </div>
                </form>
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
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });
        
        function approveClearance(id) {
            if (confirm('Are you sure you want to approve this clearance request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="clearance_id" value="${id}">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="remarks" value="Approved by admin">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function showRejectModal(id, studentName) {
            document.getElementById('reject_clearance_id').value = id;
            $('#rejectModal').modal('show');
        }
    </script>
</body>
</html>