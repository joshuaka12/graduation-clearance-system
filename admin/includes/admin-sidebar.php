<?php
// Admin Sidebar Component
?>
<style>
    .admin-sidebar {
        width: 280px;
        background: #800020;
        color: white;
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        transition: all 0.3s;
    }
    
    .sidebar-header {
        padding: 25px 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-header h4 {
        margin: 10px 0 0;
        font-size: 1.2rem;
    }
    
    .sidebar-header p {
        font-size: 0.8rem;
        opacity: 0.8;
    }
    
    .sidebar-menu {
        padding: 20px 0;
    }
    
    .menu-item {
        padding: 12px 25px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .menu-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        padding-left: 30px;
    }
    
    .menu-item.active {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border-left: 3px solid #ffd700;
    }
    
    .menu-item i {
        width: 25px;
    }
    
    .menu-divider {
        height: 1px;
        background: rgba(255, 255, 255, 0.1);
        margin: 15px 0;
    }
    
    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.75rem;
        text-align: center;
    }
    
    @media (max-width: 768px) {
        .admin-sidebar {
            transform: translateX(-100%);
        }
        .admin-sidebar.active {
            transform: translateX(0);
        }
        .menu-toggle {
            display: block;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: #800020;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
        }
    }
</style>

<div class="admin-sidebar">
    <div class="sidebar-header">
        <i class="fas fa-graduation-cap fa-2x"></i>
        <h4>Clearance System</h4>
        <p>Admin Panel</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="../dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="index.php" class="menu-item <?php echo strpos($_SERVER['PHP_SELF'], 'departments') !== false ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Departments</span>
        </a>
        
        <a href="../clearance-items/index.php" class="menu-item">
            <i class="fas fa-list-check"></i>
            <span>Clearance Items</span>
        </a>
        
        <div class="menu-divider"></div>
        
        <a href="../students/index.php" class="menu-item">
            <i class="fas fa-users"></i>
            <span>Students</span>
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
    
    <div class="sidebar-footer">
        <small>&copy; <?php echo date('Y'); ?> Clearance System</small>
    </div>
</div>

<button class="menu-toggle d-md-none" onclick="document.querySelector('.admin-sidebar').classList.toggle('active')">
    <i class="fas fa-bars"></i>
</button>