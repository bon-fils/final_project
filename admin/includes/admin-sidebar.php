<?php
/**
 * Admin Sidebar Component
 * Reusable sidebar for all admin pages
 */

// Get current page for active state
$current_page = $_GET['page'] ?? 'dashboard';
?>

<!-- Sidebar -->
<nav class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-graduation-cap"></i>
            <div>
                <h5 class="sidebar-title">RP System</h5>
                <small class="sidebar-subtitle">Admin Panel</small>
            </div>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['role'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h6>Admin User</h6>
                <small>Administrator</small>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <div class="nav-item">
                <a href="index.php" class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="reports.php" class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports & Analytics</span>
                    <span class="nav-badge">Live</span>
                </a>
            </div>
        </div>

        <!-- Management Section -->
        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <div class="nav-item">
                <a href="users.php" class="nav-link <?php echo $current_page == 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="departments.php" class="nav-link <?php echo $current_page == 'departments' ? 'active' : ''; ?>">
                    <i class="fas fa-building"></i>
                    <span>Departments</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="hod-management.php" class="nav-link <?php echo $current_page == 'hod-management' ? 'active' : ''; ?>">
                    <i class="fas fa-user-tie"></i>
                    <span>HOD Management</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="courses.php" class="nav-link <?php echo $current_page == 'courses' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>Courses</span>
                </a>
            </div>
        </div>

        <!-- Academic Section -->
        <div class="nav-section">
            <div class="nav-section-title">Academic</div>
            <div class="nav-item">
                <a href="attendance.php" class="nav-link <?php echo $current_page == 'attendance' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="leave-management.php" class="nav-link <?php echo $current_page == 'leave-management' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Leave Management</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="student-registration.php" class="nav-link <?php echo $current_page == 'student-registration' ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus"></i>
                    <span>Student Registration</span>
                </a>
            </div>
        </div>

        <!-- System Section -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <div class="nav-item">
                <a href="system-logs.php" class="nav-link <?php echo $current_page == 'system-logs' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>System Logs</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link <?php echo $current_page == 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="../logout.php" class="nav-link" style="color: #dc3545;">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>
</nav>