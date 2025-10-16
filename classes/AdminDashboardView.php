<?php
/**
 * Admin Dashboard View
 * Handles HTML rendering for the admin dashboard
 * Version: 3.0 - MVC Architecture with component-based rendering
 */

require_once 'Config.php';
require_once 'AdminDashboardController.php';

class AdminDashboardView {
    private $controller;
    private $data;

    public function __construct() {
        $this->controller = new AdminDashboardController();
    }

    /**
     * Render the complete dashboard page
     * @param array $stats Dashboard statistics
     * @return string HTML content
     */
    public function render($stats = null) {
        if ($stats === null) {
            $stats = $this->controller->getDashboardStats();
        }

        $this->data = $stats;

        ob_start();
        $this->renderHeader();
        $this->renderContent();
        $this->renderFooter();
        return ob_get_clean();
    }

    /**
     * Render page header
     */
    private function renderHeader() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="description" content="Admin Dashboard - Rwanda Polytechnic Attendance System">
            <meta name="robots" content="noindex, nofollow">
            <title>Admin Dashboard | RP Attendance System</title>

            <!-- Preload critical resources -->
            <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" as="style">
            <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style">

            <!-- Stylesheets -->
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
            <link href="css/admin-dashboard.css" rel="stylesheet">

            <!-- Scripts -->
            <script src="https://code.jquery.com/jquery-3.7.1.min.js" defer></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
        </head>

        <body>
            <!-- Mobile Menu Toggle -->
            <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>

            <!-- Sidebar -->
            <?php $this->renderSidebar(); ?>
        <?php
    }

    /**
     * Render sidebar navigation
     */
    private function renderSidebar() {
        ?>
        <div class="sidebar" id="sidebar">
            <div class="logo">
                <h3><i class="fas fa-graduation-cap me-2"></i>RP System</h3>
                <small>Admin Panel</small>
            </div>

            <ul class="sidebar-nav">
                <li class="nav-section">
                    <i class="fas fa-th-large me-2"></i>Main Dashboard
                </li>
                <li>
                    <a href="admin-dashboard.php" class="active" aria-current="page">
                        <i class="fas fa-tachometer-alt"></i>Dashboard Overview
                    </a>
                </li>

                <li class="nav-section">
                    <i class="fas fa-users me-2"></i>User Management
                </li>
                <li>
                    <a href="register-student.php">
                        <i class="fas fa-user-plus"></i>Register Student
                    </a>
                </li>
                <li>
                    <a href="admin-register-lecturer.php">
                        <i class="fas fa-chalkboard-teacher"></i>Register Lecturer
                    </a>
                </li>
                <li>
                    <a href="manage-users.php">
                        <i class="fas fa-users-cog"></i>Manage Users
                    </a>
                </li>
                <li>
                    <a href="admin-view-users.php">
                        <i class="fas fa-users"></i>View Users
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
                    <a href="logout.php" class="text-danger">
                        <i class="fas fa-sign-out-alt"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
        <?php
    }

    /**
     * Render main content
     */
    private function renderContent() {
        ?>
        <!-- Main Content -->
        <div class="main-content">
            <?php $this->renderTopbar(); ?>
            <?php $this->renderLoadingOverlay(); ?>
            <?php $this->renderAlertContainer(); ?>
            <?php $this->renderStatisticsCards(); ?>
            <?php $this->renderQuickActions(); ?>
        </div>
        <?php
    }

    /**
     * Render topbar
     */
    private function renderTopbar() {
        ?>
        <div class="topbar">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <h2 class="mb-0" style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        <i class="fas fa-tachometer-alt me-3"></i>Admin Dashboard
                    </h2>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <div class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-user-shield me-1"></i>Admin
                    </div>
                    <button class="btn btn-outline-primary btn-sm" id="refreshDashboard" title="Refresh Dashboard Data">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render loading overlay
     */
    private function renderLoadingOverlay() {
        ?>
        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center text-white">
                <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Loading Dashboard</h5>
                <p class="mb-0">Please wait while we fetch the latest data...</p>
            </div>
        </div>
        <?php
    }

    /**
     * Render alert container
     */
    private function renderAlertContainer() {
        ?>
        <!-- Alert Messages -->
        <div id="alertBox" class="mb-4" role="alert" aria-live="polite"></div>
        <?php
    }

    /**
     * Render statistics cards
     */
    private function renderStatisticsCards() {
        ?>
        <!-- Statistics Cards -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-users text-primary"></i>
                    <h3 id="total_students"><?= htmlspecialchars($this->data['total_students']) ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-chalkboard-teacher text-info"></i>
                    <h3 id="total_lecturers"><?= htmlspecialchars($this->data['total_lecturers']) ?></h3>
                    <p>Total Lecturers</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-user-tie text-success"></i>
                    <h3 id="total_hods"><?= htmlspecialchars($this->data['total_hods']) ?></h3>
                    <p>Department Heads</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-building text-warning"></i>
                    <h3 id="total_departments"><?= htmlspecialchars($this->data['total_departments']) ?></h3>
                    <p>Departments</p>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-video text-danger"></i>
                    <h3 id="active_attendance"><?= htmlspecialchars($this->data['active_attendance']) ?></h3>
                    <p>Active Sessions</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-calendar-check text-secondary"></i>
                    <h3 id="total_sessions"><?= htmlspecialchars($this->data['total_sessions']) ?></h3>
                    <p>Total Sessions</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-clock text-warning"></i>
                    <h3 id="pending_requests"><?= htmlspecialchars($this->data['pending_requests']) ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-chart-line text-success"></i>
                    <h3 id="avg_attendance_rate">
                        <?php if ($this->data['avg_attendance_rate'] > 0): ?>
                            <?= htmlspecialchars($this->data['avg_attendance_rate']) ?>%
                        <?php else: ?>
                            0%
                        <?php endif; ?>
                    </h3>
                    <p>Avg Attendance</p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render quick actions and system status
     */
    private function renderQuickActions() {
        ?>
        <!-- Quick Actions & System Status -->
        <div class="row g-4">
            <!-- Quick Actions -->
            <div class="col-lg-6">
                <div class="card border-0 shadow">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="register-student.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Register Student
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="admin-register-lecturer.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>Register Lecturer
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="manage-departments.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-building me-2"></i>Manage Departments
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="admin-reports.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-chart-bar me-2"></i>View Reports
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="assign-hod.php" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-user-tie me-2"></i>Assign HOD
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="system-logs.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-history me-2"></i>System Logs
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="manage-users.php" class="btn btn-outline-dark w-100">
                                    <i class="fas fa-users me-2"></i>Manage Users
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="col-lg-6">
                <div class="card border-0 shadow">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-server me-2"></i>System Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php $health = $this->controller->getSystemHealth(); ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-database text-primary me-2"></i>Database</span>
                                <span class="badge bg-<?= $health['database']['status'] === 'healthy' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($health['database']['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-memory text-info me-2"></i>Cache System</span>
                                <span class="badge bg-<?= $health['cache']['status'] === 'healthy' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($health['cache']['status']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-users text-info me-2"></i>Active Users (24h)</span>
                                <span class="badge bg-info" id="active_users_count">
                                    <?= htmlspecialchars($this->data['active_users_24h']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-clock text-warning me-2"></i>Last Update</span>
                                <span class="badge bg-secondary" id="last_update">
                                    <?= htmlspecialchars(date('H:i:s', strtotime($this->data['last_updated']))) ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-chart-line text-success me-2"></i>Avg Attendance</span>
                                <span class="badge bg-success" id="avg_attendance_display">
                                    <?php if ($this->data['avg_attendance_rate'] > 0): ?>
                                        <?= htmlspecialchars($this->data['avg_attendance_rate']) ?>%
                                    <?php else: ?>
                                        0%
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-shield-alt text-danger me-2"></i>Security Status</span>
                                <span class="badge bg-success">Secure</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render page footer
     */
    private function renderFooter() {
        ?>
            <script src="js/admin-dashboard.js"></script>
        </body>
        </html>
        <?php
    }

    /**
     * Render JSON response for AJAX requests
     * @param array $data Response data
     */
    public function renderJson($data) {
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode($data);
    }

    /**
     * Render error page
     * @param string $message Error message
     * @param int $code HTTP status code
     */
    public function renderError($message, $code = 500) {
        http_response_code($code);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Error | Admin Dashboard</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body class="bg-light">
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Error (<?= $code ?>)
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-3"><?= htmlspecialchars($message) ?></p>
                                <a href="admin-dashboard.php" class="btn btn-primary">
                                    <i class="fas fa-home me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}