<?php
session_start();
require_once "config.php"; // $pdo connection
require_once "session_check.php";
require_once "dashboard_utils.php"; // Dashboard utility functions

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

require_role(['lecturer']);

// Debug logging
error_log("Lecturer dashboard access - user_id: " . ($user_id ?? 'null') . ", session role: " . ($_SESSION['role'] ?? 'null'));

// Fetch lecturer info with improved error handling
try {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    error_log("User query result: " . ($user ? json_encode($user) : 'null'));

    // Only allow lecturers (double check)
    if (!$user || $user['role'] !== 'lecturer') {
        error_log("Lecturer dashboard access denied. User: " . ($user ? json_encode($user) : 'null') . ", user_id: " . ($user_id ?? 'null') . ", session role: " . ($_SESSION['role'] ?? 'null'));
        session_destroy();
        header("Location: index.php?error=unauthorized");
        exit();
    }
} catch (PDOException $e) {
    error_log("Database error in lecturer dashboard: " . $e->getMessage());
    session_destroy();
    header("Location: index.php?error=database");
    exit();
}

$lecturer_name = $user['username'];
$lecturer_email = $user['email'];

// Validate user_id
if (!$user_id) {
    error_log("Invalid user_id in lecturer dashboard");
    session_destroy();
    header("Location: index.php?error=invalid_session");
    exit();
}

// Get lecturer's department_id first - join on email instead of ID
$dept_stmt = $pdo->prepare("
    SELECT l.department_id, l.id as lecturer_id, d.name as department_name
    FROM lecturers l
    INNER JOIN users u ON l.email = u.email
    LEFT JOIN departments d ON l.department_id = d.id
    WHERE u.id = :user_id AND u.role = 'lecturer'
");
$dept_stmt->execute(['user_id' => $user_id]);
$lecturer_dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer_dept || !isset($lecturer_dept['department_id'])) {
    // Try to create a lecturer record if it doesn't exist - but don't assign a default department
    $create_lecturer_stmt = $pdo->prepare("
        INSERT INTO lecturers (first_name, last_name, email, role, password)
        SELECT
            CASE WHEN username LIKE '% %' THEN SUBSTRING_INDEX(username, ' ', 1) ELSE username END as first_name,
            CASE WHEN username LIKE '% %' THEN SUBSTRING_INDEX(username, ' ', -1) ELSE '' END as last_name,
            email, 'lecturer', '12345'
        FROM users
        WHERE id = :user_id AND role = 'lecturer'
        ON DUPLICATE KEY UPDATE email = email
    ");
    $create_lecturer_stmt->execute(['user_id' => $user_id]);

    // Try again to get the department
    $dept_stmt->execute(['user_id' => $user_id]);
    $lecturer_dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer_dept || !isset($lecturer_dept['department_id'])) {
        // If still not found, redirect with error
        header("Location: index.php?error=lecturer_setup_required");
        exit;
    }
}

// Store lecturer_id and department info in session for other pages to use
$_SESSION['lecturer_id'] = $lecturer_dept['lecturer_id'];
$_SESSION['department_id'] = $lecturer_dept['department_id'];
$_SESSION['department_name'] = $lecturer_dept['department_name'] ?? 'Not Assigned';

// Handle error parameter
if (isset($_GET['error'])) {
    $error_message = '';
    switch ($_GET['error']) {
        case 'lecturer_not_found':
            $error_message = 'Lecturer record not found. Please contact administrator.';
            break;
        case 'lecturer_setup_required':
            $error_message = 'Lecturer setup required. Please contact administrator.';
            break;
        default:
            $error_message = 'An error occurred. Please try again.';
    }

    echo '<div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>' . htmlspecialchars($error_message) . '
          </div>';
}

// Handle AJAX request
if(isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    try {
        $data = getDashboardData($pdo, $user_id);
        echo json_encode($data);
    } catch (Exception $e) {
        error_log("AJAX error in lecturer dashboard: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to load dashboard data']);
    }
    exit();
}

// Initial dashboard data with error handling
try {
    $data = getDashboardData($pdo, $user_id);
    $activities = getRecentActivities($pdo, $user_id);
    $coursePerformance = getCoursePerformanceData($pdo, $user_id);
    $attendanceTrends = getAttendanceTrends($pdo, $user_id);
} catch (Exception $e) {
    error_log("Error loading dashboard data: " . $e->getMessage());
    $data = [
        'assignedCourses' => 0,
        'totalStudents' => 0,
        'todayAttendance' => 0,
        'weekAttendance' => 0,
        'pendingLeaves' => 0,
        'approvedLeaves' => 0,
        'totalSessions' => 0,
        'activeSessions' => 0,
        'averageAttendance' => 0
    ];
    $activities = [];
    $coursePerformance = [];
    $attendanceTrends = [];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Lecturer Dashboard | RP Attendance System</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/chart.js" rel="stylesheet" />

<style>
:root {
    --primary-color: #003366;
    --secondary-color: #0059b3;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --danger-color: #dc3545;
    --light-bg: #f8f9fa;
    --card-shadow: 0 4px 12px rgba(0,0,0,0.1);
    --card-shadow-hover: 0 8px 25px rgba(0,0,0,0.15);
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(to right, #0066cc, #003366);
    margin: 0;
    min-height: 100vh;
}

.sidebar {
    position: fixed; top: 0; left: 0; width: 250px; height: 100vh;
    background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white; padding-top: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    z-index: 1000; overflow-y: auto;
}

.sidebar a {
    display: block; padding: 12px 20px; color: #fff; text-decoration: none;
    transition: all 0.3s ease; margin: 2px 10px; border-radius: 8px;
}

.sidebar a:hover, .sidebar a.active {
    background-color: rgba(255,255,255,0.1);
    transform: translateX(5px);
}

.topbar {
    margin-left: 250px; background-color: #fff; padding: 15px 30px;
    border-bottom: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    backdrop-filter: blur(10px);
}

.main-content {
    margin-left: 250px; padding: 30px;
}

.card {
    border-radius: 15px; box-shadow: var(--card-shadow); margin-bottom: 30px;
    transition: all 0.3s ease; border: none; overflow: hidden;
}

.card:hover { transform: translateY(-5px); box-shadow: var(--card-shadow-hover); }

.widget {
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border-radius: 15px; padding: 25px; text-align: center; box-shadow: var(--card-shadow);
    transition: all 0.3s ease; position: relative; overflow: hidden; border: none;
}

.widget:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-shadow-hover);
}

.widget::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px;
    background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
}

.widget h3 {
    margin-bottom: 15px; color: var(--primary-color); font-size: 1.1rem;
    font-weight: 600;
}

.widget p {
    font-size: 2.5rem; font-weight: 700; margin: 0; color: var(--primary-color);
}

.widget.quick-actions { text-align: left; }
.widget.quick-actions h3 { text-align: center; }

.nav-links a {
    display: inline-block; margin-right: 15px; color: #0066cc; font-weight: 600;
    text-decoration: none; border-bottom: 2px solid transparent; padding-bottom: 3px;
    transition: all 0.3s ease; border-radius: 5px; padding: 8px 16px;
}

.nav-links a:hover, .nav-links a.active {
    border-color: #0066cc; color: #004b99; transform: translateY(-1px);
    background-color: rgba(0,102,204,0.1);
}

.activity-item {
    transition: all 0.3s ease; padding: 12px; border-radius: 10px;
    border-left: 4px solid var(--info-color);
}

.activity-item:hover { background-color: #f8f9fa; transform: translateX(5px); }

.footer {
    text-align: center; margin-left: 250px; padding: 15px;
    font-size: 0.9rem; color: #666; background-color: #f0f0f0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.chart-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: var(--card-shadow);
    margin-bottom: 20px;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .sidebar, .topbar, .main-content, .footer {
        margin-left: 0 !important; width: 100%;
    }
    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        display: block;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .sidebar a {
        padding: 10px 15px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .widget p { font-size: 2rem; }
}

@media (max-width: 992px) {
    .col-lg-3 { margin-bottom: 20px; }
    .stats-grid { grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: var(--secondary-color);
}

/* Accessibility improvements */
.widget:focus-within {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}

.nav-links a:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
    border-radius: 4px;
}

.btn:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .widget {
        border: 2px solid var(--primary-color);
    }

    .card {
        border: 2px solid #333;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .widget,
    .card,
    .activity-item,
    .nav-links a {
        transition: none;
    }

    .pulse {
        animation: none;
    }
}

/* Touch device improvements */
@media (hover: none) and (pointer: coarse) {
    .widget {
        cursor: default;
    }

    .nav-links a {
        padding: 12px 20px;
        margin-bottom: 8px;
        display: block;
    }

    .btn {
        min-height: 44px;
        padding: 12px 24px;
    }
}

/* Print styles */
@media print {
    .sidebar,
    .topbar,
    .footer,
    .btn {
        display: none !important;
    }

    .main-content {
        margin-left: 0 !important;
    }

    .widget {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ccc;
    }
}

/* Loading animation for better UX */
.loading-shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* Enhanced focus indicators */
.widget:focus {
    outline: 3px solid var(--primary-color);
    outline-offset: 2px;
}

/* Better color contrast */
.text-muted {
    color: #6c757d !important;
}

.badge {
    font-weight: 600;
}
</style>
</head>

<body>

<div class="sidebar">
    <div class="text-center mb-4">
        <h4>👨‍🏫 Lecturer</h4>
        <p class="small mb-1">
            <i class="fas fa-envelope me-1"></i>
            <?= htmlspecialchars($lecturer_email) ?>
        </p>
        <?php if ($lecturer_dept['department_name']): ?>
            <p class="small mb-2">
                <i class="fas fa-building me-1"></i>
                <strong><?= htmlspecialchars($lecturer_dept['department_name']) ?></strong>
            </p>
        <?php else: ?>
            <p class="small mb-2 text-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>
                <strong>Department Not Assigned</strong>
            </p>
        <?php endif; ?>
        <hr style="border-color: #ffffff66;">
    </div>
    <a href="lecturer-dashboard.php" class="active"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="my-courses.php"><i class="fas fa-book me-2"></i> My Courses</a>
    <a href="attendance-session.php"><i class="fas fa-video me-2"></i> Attendance Session</a>
    <a href="attendance-reports.php"><i class="fas fa-chart-line me-2"></i> Attendance Reports</a>
    <a href="leave-requests.php"><i class="fas fa-envelope me-2"></i> Leave Requests</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Lecturer Dashboard</h5>
    <span><?= htmlspecialchars($lecturer_email) ?></span>
</div>

<div class="main-content">
<!-- Welcome Section -->
<div class="card p-4 mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h2 class="mb-2">Welcome back, <?= htmlspecialchars($lecturer_name) ?>! 👨‍🏫</h2>
            <p class="text-muted mb-2">Here's your attendance management overview for today.</p>
            <?php if ($lecturer_dept['department_name']): ?>
                <p class="mb-0">
                    <span class="badge bg-info">
                        <i class="fas fa-building me-1"></i>
                        Department: <?= htmlspecialchars($lecturer_dept['department_name']) ?>
                    </span>
                </p>
            <?php else: ?>
                <div class="alert alert-warning py-2 mb-0" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Department Not Assigned:</strong> Please contact your administrator to assign you to a department.
                </div>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge bg-primary fs-6 p-2">
                <i class="fas fa-calendar-day me-1"></i>
                <?= date('l, F j, Y') ?>
            </span>
        </div>
    </div>
</div>

<!-- Statistics Grid -->
<div class="stats-grid" role="region" aria-label="Dashboard Statistics">
    <div class="widget" tabindex="0" role="article" aria-labelledby="courses-heading">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <i class="fas fa-book fa-2x text-primary" aria-hidden="true"></i>
            <span class="badge bg-primary">Courses</span>
        </div>
        <h3 id="courses-heading">Assigned Courses</h3>
        <p id="assignedCourses" aria-label="Number of assigned courses" role="text">
            <?= $data['assignedCourses'] ?>
        </p>
        <small class="text-muted">Active courses this semester</small>
    </div>

    <div class="widget" tabindex="0" role="article" aria-labelledby="students-heading">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <i class="fas fa-users fa-2x text-success" aria-hidden="true"></i>
            <span class="badge bg-success">Students</span>
        </div>
        <h3 id="students-heading">Total Students</h3>
        <p id="totalStudents" aria-label="Total number of students" role="text">
            <?= $data['totalStudents'] ?>
        </p>
        <small class="text-muted">Across all your courses</small>
    </div>

    <div class="widget" tabindex="0" role="article" aria-labelledby="today-heading">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <i class="fas fa-calendar-check fa-2x text-info" aria-hidden="true"></i>
            <span class="badge bg-info">Today</span>
        </div>
        <h3 id="today-heading">Today's Attendance</h3>
        <p id="todayAttendance" aria-label="Today's attendance count" role="text" class="<?= $data['todayAttendance'] > 0 ? 'pulse' : '' ?>">
            <?= $data['todayAttendance'] ?>
        </p>
        <small class="text-muted">Students marked present</small>
    </div>

    <div class="widget" tabindex="0" role="article" aria-labelledby="weekly-heading">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <i class="fas fa-chart-line fa-2x text-warning" aria-hidden="true"></i>
            <span class="badge bg-warning">Weekly</span>
        </div>
        <h3 id="weekly-heading">Weekly Attendance</h3>
        <p id="weekAttendance" aria-label="This week's attendance count" role="text">
            <?= $data['weekAttendance'] ?>
        </p>
        <small class="text-muted">This week's total</small>
    </div>

    <div class="widget" tabindex="0" role="article" aria-labelledby="pending-heading">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <i class="fas fa-clock fa-2x text-secondary" aria-hidden="true"></i>
            <span class="badge bg-secondary">Pending</span>
        </div>
        <h3 id="pending-heading">Pending Leaves</h3>
        <p id="pendingLeaves" aria-label="Number of pending leave requests" role="text">
            <?= $data['pendingLeaves'] ?>
        </p>
        <small class="text-muted">Awaiting approval</small>
    </div>

    <div class="widget" tabindex="0" role="article" aria-labelledby="approved-heading">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <i class="fas fa-check-circle fa-2x text-success" aria-hidden="true"></i>
            <span class="badge bg-success">Approved</span>
        </div>
        <h3 id="approved-heading">Approved Leaves</h3>
        <p id="approvedLeaves" aria-label="Number of approved leave requests" role="text">
            <?= $data['approvedLeaves'] ?>
        </p>
        <small class="text-muted">This semester</small>
    </div>

    <div class="widget" tabindex="0" role="article" aria-labelledby="sessions-heading">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <i class="fas fa-video fa-2x text-primary" aria-hidden="true"></i>
            <span class="badge bg-primary">Sessions</span>
        </div>
        <h3 id="sessions-heading">Total Sessions</h3>
        <p id="totalSessions" aria-label="Total number of attendance sessions" role="text">
            <?= $data['totalSessions'] ?>
        </p>
        <small class="text-muted">Conducted sessions</small>
    </div>

    <div class="widget" tabindex="0" role="article" aria-labelledby="rate-heading">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <i class="fas fa-percentage fa-2x text-info" aria-hidden="true"></i>
            <span class="badge bg-info">Rate</span>
        </div>
        <h3 id="rate-heading">Avg Attendance</h3>
        <p id="averageAttendance" aria-label="Average attendance rate" role="text">
            <?= $data['averageAttendance'] ?>%
        </p>
        <small class="text-muted">Overall attendance rate</small>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="widget quick-actions">
            <h3><i class="fas fa-bolt me-2"></i>Quick Actions</h3>
            <div class="d-grid gap-3">
                <a href="attendance-session.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-play me-2"></i>Start Attendance Session
                </a>
                <a href="my-courses.php" class="btn btn-outline-primary btn-lg">
                    <i class="fas fa-book me-2"></i>View My Courses
                </a>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="widget">
            <h3><i class="fas fa-chart-bar me-2"></i>Active Sessions</h3>
            <div class="text-center">
                <p class="display-4 text-primary mb-2" id="activeSessions">
                    <?= $data['activeSessions'] ?>
                </p>
                <small class="text-muted">Currently running sessions</small>
                <?php if ($data['activeSessions'] > 0): ?>
                    <div class="mt-2">
                        <span class="badge bg-success">
                            <i class="fas fa-circle me-1"></i>Active
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

    <div class="nav-links mb-4 p-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 10px; border-left: 4px solid var(--primary-color);">
        <h6 class="mb-3 text-primary"><i class="fas fa-link me-2"></i>Quick Access</h6>
        <a href="my-courses.php" class="btn btn-outline-primary me-2 mb-2">
            <i class="fas fa-book me-1"></i>My Courses
        </a>
        <a href="attendance-session.php" class="btn btn-outline-success me-2 mb-2">
            <i class="fas fa-video me-1"></i>Start/Stop Session
        </a>
        <a href="attendance-reports.php" class="btn btn-outline-info me-2 mb-2">
            <i class="fas fa-chart-line me-1"></i>Attendance Reports
        </a>
        <a href="leave-requests.php" class="btn btn-outline-warning me-2 mb-2">
            <i class="fas fa-envelope me-1"></i>Leave Requests
        </a>
    </div>

    <!-- Charts and Analytics Section -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="chart-container">
                <h5 class="mb-4"><i class="fas fa-chart-line me-2"></i>Attendance Trends (Last 7 Days)</h5>
                <canvas id="attendanceChart" width="400" height="200"></canvas>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-4">
                <h6 class="mb-3"><i class="fas fa-history me-2"></i>Recent Activities</h6>
                <div id="recentActivities">
                    <?php if (empty($activities)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <p class="text-muted small">No recent activities</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item mb-3 pb-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <small class="fw-bold d-flex align-items-center">
                                            <i class="fas <?= $activity['icon'] ?> me-2 text-primary"></i>
                                            <?= $activity['title'] ?>
                                        </small>
                                        <br>
                                        <small class="text-muted"><?= $activity['detail'] ?></small>
                                    </div>
                                    <small class="text-muted ms-2 flex-shrink-0">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= $activity['time'] ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Performance Section -->
    <?php if (!empty($coursePerformance)): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="chart-container">
                <h5 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Course Performance Overview</h5>
                <canvas id="coursePerformanceChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navigation Links -->
    <div class="card p-4 mb-4" style="border-left: 4px solid var(--primary-color);">
        <h6 class="mb-3"><i class="fas fa-compass me-2"></i>Quick Navigation</h6>
        <div class="nav-links">
            <a href="my-courses.php" class="btn btn-outline-primary me-2 mb-2">
                <i class="fas fa-book me-1"></i>My Courses
            </a>
            <a href="attendance-session.php" class="btn btn-outline-success me-2 mb-2">
                <i class="fas fa-video me-1"></i>Start/Stop Session
            </a>
            <a href="attendance-reports.php" class="btn btn-outline-info me-2 mb-2">
                <i class="fas fa-chart-line me-1"></i>Attendance Reports
            </a>
            <a href="leave-requests.php" class="btn btn-outline-warning me-2 mb-2">
                <i class="fas fa-envelope me-1"></i>Leave Requests
            </a>
        </div>
    </div>
</div>

<div class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Global variables for charts
let attendanceChart = null;
let coursePerformanceChart = null;

// Chart configuration
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                padding: 20,
                usePointStyle: true
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: {
                color: 'rgba(0,0,0,0.1)'
            }
        },
        x: {
            grid: {
                display: false
            }
        }
    }
};

function refreshDashboard() {
    // Show loading state
    const widgets = document.querySelectorAll('.widget p[id], .widget .display-4[id]');
    widgets.forEach(p => {
        if (!p.innerHTML.includes('fa-spinner')) {
            p.innerHTML = '<div class="loading"></div>';
        }
    });

    fetch('lecturer-dashboard.php?ajax=1')
    .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
    })
    .then(data => {
        // Update statistics
        const statsMap = {
            'assignedCourses': data.assignedCourses,
            'totalStudents': data.totalStudents,
            'todayAttendance': data.todayAttendance,
            'weekAttendance': data.weekAttendance,
            'pendingLeaves': data.pendingLeaves,
            'approvedLeaves': data.approvedLeaves,
            'totalSessions': data.totalSessions,
            'averageAttendance': data.averageAttendance + '%',
            'activeSessions': data.activeSessions
        };

        Object.entries(statsMap).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
                // Add pulse effect for today's attendance if > 0
                if (id === 'todayAttendance' && value > 0) {
                    element.classList.add('pulse');
                }
            }
        });

        // Update charts if data is available
        if (data.attendanceTrends) {
            updateAttendanceChart(data.attendanceTrends);
        }
        if (data.coursePerformance) {
            updateCoursePerformanceChart(data.coursePerformance);
        }
    })
    .catch(err => {
        console.log('Dashboard refresh error:', err);
        // Reset loading state on error
        widgets.forEach(p => {
            if (p.innerHTML.includes('loading')) {
                p.innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
            }
        });

        // Show error toast
        showToast('Error refreshing dashboard data', 'error');
    });
}

function updateAttendanceChart(trendsData) {
    const ctx = document.getElementById('attendanceChart');
    if (!ctx) return;

    const labels = trendsData.map(item => item.date);
    const data = trendsData.map(item => item.count);

    if (attendanceChart) {
        attendanceChart.destroy();
    }

    attendanceChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Attendance Count',
                data: data,
                borderColor: 'rgb(0, 51, 102)',
                backgroundColor: 'rgba(0, 51, 102, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgb(0, 51, 102)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: chartOptions
    });
}

function updateCoursePerformanceChart(performanceData) {
    const ctx = document.getElementById('coursePerformanceChart');
    if (!ctx) return;

    const labels = performanceData.map(item => item.course_name);
    const attendanceData = performanceData.map(item => item.attendance_count);
    const sessionData = performanceData.map(item => item.sessions_count);

    if (coursePerformanceChart) {
        coursePerformanceChart.destroy();
    }

    coursePerformanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Attendance Count',
                data: attendanceData,
                backgroundColor: 'rgba(0, 51, 102, 0.8)',
                borderColor: 'rgb(0, 51, 102)',
                borderWidth: 1
            }, {
                label: 'Sessions Count',
                data: sessionData,
                backgroundColor: 'rgba(0, 89, 179, 0.8)',
                borderColor: 'rgb(0, 89, 179)',
                borderWidth: 1
            }]
        },
        options: {
            ...chartOptions,
            scales: {
                x: {
                    ...chartOptions.scales.x,
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                },
                y: {
                    ...chartOptions.scales.y,
                    stacked: false
                }
            }
        }
    });
}

function showToast(message, type = 'info') {
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;

    document.body.appendChild(toast);

    // Initialize and show toast
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Remove after 5 seconds
    setTimeout(() => {
        toast.remove();
    }, 5000);
}

// Auto-refresh every 30 seconds
setInterval(refreshDashboard, 30000);

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    refreshDashboard();

    // Add smooth scrolling for navigation links
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            if (href) {
                window.location.href = href;
            }
        });
    });

    // Add hover effects to widgets
    document.querySelectorAll('.widget').forEach(widget => {
        widget.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });

        widget.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });

        // Add keyboard navigation
        widget.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
    });

    // Add keyboard navigation for nav links
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                window.location.href = this.href;
            }
        });
    });

    // Add skip to main content link for screen readers
    const skipLink = document.createElement('a');
    skipLink.href = '#main-content';
    skipLink.textContent = 'Skip to main content';
    skipLink.className = 'btn btn-primary position-absolute';
    skipLink.style.cssText = 'top: -40px; left: 6px; z-index: 1000; transition: top 0.3s;';
    skipLink.addEventListener('focus', function() {
        this.style.top = '6px';
    });
    skipLink.addEventListener('blur', function() {
        this.style.top = '-40px';
    });
    document.body.insertBefore(skipLink, document.body.firstChild);

    // Add main content id
    document.querySelector('.main-content').id = 'main-content';
});

// Handle visibility change (tab switching)
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        refreshDashboard();
    }
});
</script>

</body>
</html>
