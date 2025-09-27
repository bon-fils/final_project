<?php
/**
 * Admin Sidebar Navigation
 * Common navigation for admin pages
 */
?>

<!-- Sidebar -->
<nav id="adminSidebar" class="col-md-3 col-lg-2 sidebar bg-dark text-white">
    <div class="position-sticky pt-3">
        <!-- Logo/Brand -->
        <div class="text-center mb-4">
            <h5 class="text-white">
                <i class="fas fa-graduation-cap me-2"></i>
                Rwanda Polytechnic
            </h5>
        </div>

        <!-- Navigation Menu -->
        <ul class="nav nav-pills flex-column">
            <li class="nav-item">
                <a href="admin-dashboard.php" class="nav-link text-white">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="register-student.php" class="nav-link text-white active">
                    <i class="fas fa-user-plus me-2"></i>Register Student
                </a>
            </li>
            <li class="nav-item">
                <a href="manage-departments.php" class="nav-link text-white">
                    <i class="fas fa-building me-2"></i>Departments
                </a>
            </li>
            <li class="nav-item">
                <a href="manage-users.php" class="nav-link text-white">
                    <i class="fas fa-users me-2"></i>Manage Users
                </a>
            </li>
            <li class="nav-item">
                <a href="attendance-session.php" class="nav-link text-white">
                    <i class="fas fa-calendar-check me-2"></i>Attendance
                </a>
            </li>
            <li class="nav-item">
                <a href="admin-reports.php" class="nav-link text-white">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a href="system-logs.php" class="nav-link text-white">
                    <i class="fas fa-history me-2"></i>System Logs
                </a>
            </li>
        </ul>

        <!-- User Info -->
        <div class="mt-4 pt-3 border-top border-secondary">
            <div class="text-center">
                <small class="text-muted">
                    Logged in as: <?php echo $_SESSION['role'] ?? 'Admin'; ?>
                </small>
                <br>
                <a href="logout.php" class="btn btn-outline-light btn-sm mt-2">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<style>
.sidebar {
    min-height: 100vh;
    padding: 20px 15px;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1000;
    width: 250px;
    overflow-y: auto;
}

.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    padding: 10px 15px;
    margin-bottom: 5px;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.sidebar .nav-link:hover {
    color: white !important;
    background-color: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.sidebar .nav-link.active {
    color: white !important;
    background-color: #0d6efd;
}

.sidebar .nav-link i {
    width: 20px;
    text-align: center;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .sidebar.show {
        transform: translateX(0);
    }
}
</style>