<?php
/**
 * Enhanced Admin Sidebar Navigation
 * Professional navigation with modern design and organization
 */
?>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <h4>👨‍💼 Admin</h4>
        <small>RP Attendance System</small>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-section">
            <i class="fas fa-th-large me-2"></i>Main Dashboard
        </li>
        <li>
            <a href="admin-dashboard.php">
                <i class="fas fa-tachometer-alt"></i>Dashboard Overview
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-users me-2"></i>User Management
        </li>
        <li>
            <a href="register-student.php" class="active">
                <i class="fas fa-user-plus"></i>Register Student
            </a>
        </li>
        <li>
            <a href="manage-users.php">
                <i class="fas fa-users-cog"></i>Manage Users
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-sitemap me-2"></i>Organization
        </li>
        <li>
            <a href="manage-departments.php">
                <i class="fas fa-building"></i>Departments
            </a>
        </li>
        <li>
            <a href="assign-hod.php">
                <i class="fas fa-user-tie"></i>Assign HOD
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
        </li>
        <li>
            <a href="admin-reports.php">
                <i class="fas fa-chart-line"></i>Analytics Reports
            </a>
        </li>
        <li>
            <a href="attendance-reports.php">
                <i class="fas fa-calendar-check"></i>Attendance Reports
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-cog me-2"></i>System
        </li>
        <li>
            <a href="system-logs.php">
                <i class="fas fa-file-code"></i>System Logs
            </a>
        </li>
        <li>
            <a href="hod-leave-management.php">
                <i class="fas fa-clipboard-list"></i>Leave Management
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-sign-out-alt me-2"></i>Account
        </li>
        <li>
            <a href="<?php echo logout_url(); ?>" class="text-danger" onclick="return confirm('Are you sure you want to logout?')">
                <i class="fas fa-sign-out-alt"></i>Logout
            </a>
        </li>
    </ul>
</div>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
        --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
        --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
        border-right: 1px solid rgba(0, 102, 204, 0.1);
        padding: 0;
        overflow-y: auto;
        z-index: 1000;
        box-shadow: 0 0 20px rgba(0, 102, 204, 0.1);
    }

    .sidebar .logo {
        background: var(--primary-gradient);
        color: white;
        padding: 25px 20px;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .sidebar .logo::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="20" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
        pointer-events: none;
    }

    .sidebar .logo h4 {
        color: white;
        font-weight: 700;
        margin: 0;
        font-size: 1.4rem;
        position: relative;
        z-index: 2;
        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
    }

    .sidebar .logo small {
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.75rem;
        font-weight: 500;
        position: relative;
        z-index: 2;
    }

    .sidebar-nav {
        list-style: none;
        padding: 20px 0;
        margin: 0;
    }

    .sidebar-nav .nav-section {
        padding: 15px 20px 10px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid rgba(0, 102, 204, 0.1);
        margin-bottom: 10px;
    }

    .sidebar-nav a {
        display: block;
        padding: 14px 25px;
        color: #495057;
        text-decoration: none;
        border-radius: 0 25px 25px 0;
        margin: 0 0 2px 0;
        transition: var(--transition);
        font-weight: 500;
        position: relative;
        border-left: 3px solid transparent;
    }

    .sidebar-nav a:hover {
        background: rgba(0, 102, 204, 0.08);
        color: #0066cc;
        border-left-color: #0066cc;
        transform: translateX(8px);
        box-shadow: 2px 0 8px rgba(0, 102, 204, 0.15);
    }

    .sidebar-nav a.active {
        background: linear-gradient(90deg, rgba(0, 102, 204, 0.15) 0%, rgba(0, 102, 204, 0.05) 100%);
        color: #0066cc;
        border-left-color: #0066cc;
        box-shadow: 2px 0 12px rgba(0, 102, 204, 0.2);
        font-weight: 600;
    }

    .sidebar-nav a.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 60%;
        background: #0066cc;
        border-radius: 0 2px 2px 0;
    }

    .sidebar-nav a i {
        margin-right: 12px;
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
    }

    .sidebar-nav .mt-4 {
        margin-top: 2rem !important;
        padding-top: 2rem !important;
        border-top: 1px solid rgba(0, 102, 204, 0.1);
    }

    .mobile-menu-toggle {
        display: none;
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 1003;
        background: var(--primary-gradient);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 12px;
        box-shadow: 0 4px 20px rgba(0, 102, 204, 0.3);
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: var(--transition);
    }

    .mobile-menu-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 260px;
            z-index: 1002;
        }

        .sidebar.show {
            transform: translateX(0);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
        }

        .sidebar.show::after {
            content: '';
            position: fixed;
            top: 0;
            left: 260px;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
            z-index: -1;
        }

        .mobile-menu-toggle {
            display: block !important;
        }

        .sidebar-nav a {
            padding: 16px 20px;
            font-size: 0.95rem;
        }

        .sidebar-nav .nav-section {
            padding: 12px 20px 8px;
            font-size: 0.7rem;
        }
    }
</style>