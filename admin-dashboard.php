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
 * Get dashboard statistics
 */
function getDashboardStats() {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM students) AS total_students,
                (SELECT COUNT(*) FROM lecturers WHERE role = 'lecturer') AS total_lecturers,
                (SELECT COUNT(*) FROM lecturers WHERE role = 'hod') AS total_hods,
                (SELECT COUNT(*) FROM departments) AS total_departments,
                (SELECT COUNT(*) FROM attendance_sessions WHERE end_time IS NULL) AS active_attendance,
                (SELECT COUNT(*) FROM attendance_sessions) AS total_sessions,
                (SELECT COUNT(*) FROM leave_requests WHERE status = 'pending') AS pending_requests,
                (SELECT COUNT(*) FROM leave_requests WHERE status = 'approved') AS approved_requests,
                (SELECT COUNT(*) FROM attendance_records WHERE DATE(recorded_at) = CURDATE()) AS today_records,
                (SELECT COUNT(*) FROM lecturers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS active_users_24h,
                (SELECT ROUND(AVG(attendance_rate), 1) FROM (
                    SELECT COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as attendance_rate
                    FROM attendance_sessions s
                    LEFT JOIN attendance_records ar ON s.id = ar.session_id
                    WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY s.id
                ) rates) AS avg_attendance_rate
        ");

        $stmt->execute();
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
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

    } catch (PDOException $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
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
            'error' => true
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
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            --success-gradient: linear-gradient(135deg, #0066cc 0%, #004080 100%);
            --warning-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
            --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(to right, #0066cc, #003366);
            min-height: 100vh;
            font-family: 'Segoe UI', 'Roboto', sans-serif;
            margin: 0;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>');
            pointer-events: none;
            z-index: -1;
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
            background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
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

        .sidebar .logo h3 {
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

        .sidebar-nav li {
            margin: 2px 0;
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

        .main-content {
            margin-left: 280px;
            padding: 20px 30px;
            min-height: 100vh;
        }

        .topbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .dashboard-card {
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: none;
            transition: var(--transition);
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .dashboard-card .card-body {
            padding: 25px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .dashboard-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .dashboard-card h3 {
            font-size: 2.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .dashboard-card p {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
            margin: 0;
        }

        .stats-card {
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: var(--border-radius);
            transition: var(--transition);
            box-shadow: var(--shadow-light);
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stats-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-heavy);
        }

        .stats-card .card-body {
            padding: 25px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.8;
        }

        .stats-card h3 {
            font-size: 2.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-card p {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
            margin: 0;
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: var(--transition);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
        }

        .alert {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
        }

        .alert-success::before { background: var(--success-gradient); }
        .alert-danger::before { background: var(--danger-gradient); }
        .alert-warning::before { background: var(--warning-gradient); }
        .alert-info::before { background: var(--info-gradient); }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-overlay .spinner-border {
            color: #0066cc;
            width: 3rem;
            height: 3rem;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1003;
            background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
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

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .mobile-menu-toggle {
                display: block !important;
                z-index: 1003;
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

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .stats-card:nth-child(1) { animation-delay: 0.1s; }
        .stats-card:nth-child(2) { animation-delay: 0.2s; }
        .stats-card:nth-child(3) { animation-delay: 0.3s; }
        .stats-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
 </head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
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
                <a href="manage-users.php?role=lecturer">
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
    <!-- Connection Status Indicator -->
    <div class="alert alert-info d-none" id="connectionStatus" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <span id="connectionMessage">Checking database connection...</span>
    </div>

        <!-- Statistics Cards -->
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
                                <a href="manage-users.php?role=lecturer" class="btn btn-outline-info w-100">
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

    <script>
        // Mobile sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
            }
        });

        function showAlert(type, msg) {
            $("#alertBox").html(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${msg}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`);
            setTimeout(() => $("#alertBox").html(""), 5000);
        }

        function showLoading() {
            $("#loadingOverlay").fadeIn();
        }

        function hideLoading() {
            $("#loadingOverlay").fadeOut();
        }

        function fetchDashboardData() {
            showLoading();

            $.ajax({
                url: 'admin-dashboard.php',
                method: 'GET',
                data: { ajax: '1', t: Date.now() },
                timeout: 10000,
                success: function(response) {
                    hideLoading();

                    if (response.status === 'success') {
                        const data = response.data;

                        // Animate value updates
                        animateValue('total_students', data.total_students);
                        animateValue('total_lecturers', data.total_lecturers);
                        animateValue('total_hods', data.total_hods);
                        animateValue('total_departments', data.total_departments);
                        animateValue('active_attendance', data.active_attendance);
                        animateValue('total_sessions', data.total_sessions);
                        animateValue('pending_requests', data.pending_requests);
                        animateValue('avg_attendance_rate', data.avg_attendance_rate);

                        // Update additional metrics
                        $('#active_users_count').text(data.active_users_24h || 0);
                        $('#avg_attendance_display').text(data.avg_attendance_rate + '%');

                        // Update last update time
                        updateLastUpdateDisplay();

                        showAlert("success", "Dashboard updated successfully");
                    } else {
                        showAlert("danger", "Failed to update dashboard data");
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    showAlert("danger", "Failed to fetch dashboard data. Please try again.");
                    console.error('Dashboard AJAX Error:', error);
                }
            });
        }

        function animateValue(id, newValue, duration = 600) {
            const el = document.getElementById(id);
            if (!el) return;

            const current = parseInt(el.innerText) || 0;
            const startTime = Date.now();

            function update() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const value = Math.floor(current + (newValue - current) * easeOut);

                el.innerText = value;

                if (progress < 1) {
                    requestAnimationFrame(update);
                } else {
                    el.innerText = newValue;
                }
            }

            requestAnimationFrame(update);
        }

        function updateLastUpdateDisplay() {
            const now = new Date();
            const timeString = now.toLocaleTimeString();
            $('#last_update').text(timeString);
        }

        // Initialize dashboard
        $(document).ready(function() {
            // Initial data load
            fetchDashboardData();

            // Set up refresh button
            $('#refreshDashboard').click(function() {
                fetchDashboardData();
            });

            // Auto-refresh every 5 minutes
            setInterval(fetchDashboardData, 300000);

            // Update time display every minute
            setInterval(updateLastUpdateDisplay, 60000);

            // Initialize time display
            updateLastUpdateDisplay();
        });
    </script>
</body>
</html>
