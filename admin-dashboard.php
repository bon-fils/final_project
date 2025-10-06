<?php
/**
 * Admin Dashboard
 * Main dashboard for administrators with comprehensive system overview
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

/**
 * Get dashboard statistics with optimized query
 * Uses a single query with multiple COUNT aggregations for better performance
 */
function getDashboardStats() {
    global $pdo;

    // Check cache first (cache for 5 minutes)
    $cache_key = 'dashboard_stats';
    $cache_file = 'cache/dashboard_stats.cache';
    $cache_time = 300; // 5 minutes

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            return $cached_data;
        }
    }

    try {
        // Optimized query using conditional aggregation
        $stmt = $pdo->prepare("
            SELECT
                COUNT(CASE WHEN table_type = 'students' THEN 1 END) AS total_students,
                COUNT(CASE WHEN table_type = 'lecturers' AND role = 'lecturer' THEN 1 END) AS total_lecturers,
                COUNT(CASE WHEN table_type = 'lecturers' AND role = 'hod' THEN 1 END) AS total_hods,
                COUNT(CASE WHEN table_type = 'departments' THEN 1 END) AS total_departments,
                COUNT(CASE WHEN table_type = 'active_sessions' THEN 1 END) AS active_attendance,
                COUNT(CASE WHEN table_type = 'total_sessions' THEN 1 END) AS total_sessions,
                COUNT(CASE WHEN table_type = 'pending_requests' THEN 1 END) AS pending_requests,
                COUNT(CASE WHEN table_type = 'approved_requests' THEN 1 END) AS approved_requests,
                COUNT(CASE WHEN table_type = 'today_records' THEN 1 END) AS today_records,
                COUNT(CASE WHEN table_type = 'active_users_24h' THEN 1 END) AS active_users_24h,
                ROUND(AVG(CASE WHEN table_type = 'attendance_rate' THEN attendance_rate END), 1) AS avg_attendance_rate
            FROM (
                SELECT 'students' AS table_type, NULL AS role, NULL AS attendance_rate FROM students
                UNION ALL
                SELECT 'lecturers', u.role, NULL FROM lecturers l LEFT JOIN users u ON l.user_id = u.id
                UNION ALL
                SELECT 'departments', NULL, NULL FROM departments
                UNION ALL
                SELECT 'active_sessions', NULL, NULL FROM attendance_sessions WHERE end_time IS NULL
                UNION ALL
                SELECT 'total_sessions', NULL, NULL FROM attendance_sessions
                UNION ALL
                SELECT 'pending_requests', NULL, NULL FROM leave_requests WHERE status = 'pending'
                UNION ALL
                SELECT 'approved_requests', NULL, NULL FROM leave_requests WHERE status = 'approved'
                UNION ALL
                SELECT 'today_records', NULL, NULL FROM attendance_records WHERE DATE(recorded_at) = CURDATE()
                UNION ALL
                SELECT 'active_users_24h', NULL, NULL FROM lecturers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                UNION ALL
                SELECT 'attendance_rate', NULL, (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0))
                FROM attendance_sessions s
                LEFT JOIN attendance_records ar ON s.id = ar.session_id
                WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY s.id
            ) AS combined_data
        ");

        $stmt->execute();
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        // Sanitize and validate data
        $stats = [
            'total_students' => max(0, (int)($counts['total_students'] ?? 0)),
            'total_lecturers' => max(0, (int)($counts['total_lecturers'] ?? 0)),
            'total_hods' => max(0, (int)($counts['total_hods'] ?? 0)),
            'total_departments' => max(0, (int)($counts['total_departments'] ?? 0)),
            'active_attendance' => max(0, (int)($counts['active_attendance'] ?? 0)),
            'total_sessions' => max(0, (int)($counts['total_sessions'] ?? 0)),
            'pending_requests' => max(0, (int)($counts['pending_requests'] ?? 0)),
            'approved_requests' => max(0, (int)($counts['approved_requests'] ?? 0)),
            'today_records' => max(0, (int)($counts['today_records'] ?? 0)),
            'active_users_24h' => max(0, (int)($counts['active_users_24h'] ?? 0)),
            'avg_attendance_rate' => round(max(0, min(100, (float)($counts['avg_attendance_rate'] ?? 0))), 1),
            'last_updated' => date('Y-m-d H:i:s')
        ];

        // Cache the results
        if (!is_dir('cache')) {
            mkdir('cache', 0755, true);
        }
        file_put_contents($cache_file, json_encode($stats));

        return $stats;

    } catch (PDOException $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        // Return fallback data with error flag
        return [
            'total_students' => 0,
            'total_lecturers' => 0,
            'total_hods' => 0,
            'total_departments' => 0,
            'active_attendance' => 0,
            'total_sessions' => 0,
            'pending_requests' => 0,
            'approved_requests' => 0,
            'today_records' => 0,
            'active_users_24h' => 0,
            'avg_attendance_rate' => 0,
            'last_updated' => date('Y-m-d H:i:s'),
            'error' => 'Database connection failed. Please try again later.',
            'error_code' => 'DB_ERROR'
        ];
    }
}

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    try {
        $stats = getDashboardStats();
        echo json_encode([
            'status' => 'success',
            'data' => $stats,
            'timestamp' => time()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to load dashboard data'
        ]);
    }
    exit;
}

// Load dashboard statistics
$dashboard_stats = getDashboardStats();
$total_students = $dashboard_stats['total_students'];
$total_lecturers = $dashboard_stats['total_lecturers'];
$total_hods = $dashboard_stats['total_hods'];
$total_departments = $dashboard_stats['total_departments'];
$active_attendance = $dashboard_stats['active_attendance'];
$total_sessions = $dashboard_stats['total_sessions'];
$pending_requests = $dashboard_stats['pending_requests'];
$approved_requests = $dashboard_stats['approved_requests'];
$today_records = $dashboard_stats['today_records'];
$active_users_24h = $dashboard_stats['active_users_24h'];
$avg_attendance_rate = $dashboard_stats['avg_attendance_rate'];
$last_updated = $dashboard_stats['last_updated'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin-dashboard.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
 </head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <!-- Sidebar -->
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
                <a href="admin-dashboard.php" class="active">
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

    <!-- Main Content -->
    <div class="main-content">
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

        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center text-white">
                <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Loading Dashboard</h5>
                <p class="mb-0">Please wait while we fetch the latest data...</p>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertBox" class="mb-4"></div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-5">
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-users text-primary"></i>
                    <h3 id="total_students"><?= $total_students ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-chalkboard-teacher text-info"></i>
                    <h3 id="total_lecturers"><?= $total_lecturers ?></h3>
                    <p>Total Lecturers</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-user-tie text-success"></i>
                    <h3 id="total_hods"><?= $total_hods ?></h3>
                    <p>Department Heads</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-building text-warning"></i>
                    <h3 id="total_departments"><?= $total_departments ?></h3>
                    <p>Departments</p>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-video text-danger"></i>
                    <h3 id="active_attendance"><?= $active_attendance ?></h3>
                    <p>Active Sessions</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-calendar-check text-secondary"></i>
                    <h3 id="total_sessions"><?= $total_sessions ?></h3>
                    <p>Total Sessions</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-clock text-warning"></i>
                    <h3 id="pending_requests"><?= $pending_requests ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-chart-line text-success"></i>
                    <h3 id="avg_attendance_rate">
                        <?php if ($avg_attendance_rate > 0): ?>
                            <?= $avg_attendance_rate ?>%
                        <?php else: ?>
                            0%
                        <?php endif; ?>
                    </h3>
                    <p>Avg Attendance</p>
                </div>
            </div>
        </div>

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
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-database text-primary me-2"></i>Database</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-users text-info me-2"></i>Active Users (24h)</span>
                                <span class="badge bg-info" id="active_users_count">0</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-clock text-warning me-2"></i>Last Update</span>
                                <span class="badge bg-secondary" id="last_update">Just now</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-chart-line text-success me-2"></i>Avg Attendance</span>
                                <span class="badge bg-success" id="avg_attendance_display">
                                    <?php if ($avg_attendance_rate > 0): ?>
                                        <?= $avg_attendance_rate ?>%
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
    </div>

    <script src="js/admin-dashboard.js"></script>
</body>
</html>
