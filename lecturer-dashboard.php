<?php
/**
 * Lecturer Dashboard
 * Main dashboard for lecturers with comprehensive course and attendance overview
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_once "dashboard_utils.php";
require_role(['lecturer', 'hod']);

/**
 * Get lecturer dashboard statistics with optimized query
 */
function getLecturerDashboardStats($lecturer_id) {
    global $pdo;

    // Check cache first (cache for 5 minutes)
    $cache_key = 'lecturer_dashboard_' . $lecturer_id;
    $cache_file = 'cache/' . $cache_key . '.cache';
    $cache_time = 300; // 5 minutes

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            return $cached_data;
        }
    }

    try {
        // Get lecturer's department
        $deptStmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $deptStmt->execute([$lecturer_id]);
        $lecturerDept = $deptStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturerDept || !$lecturerDept['department_id']) {
            return [
                'assignedCourses' => 0,
                'totalStudents' => 0,
                'todayAttendance' => 0,
                'weekAttendance' => 0,
                'pendingLeaves' => 0,
                'approvedLeaves' => 0,
                'totalSessions' => 0,
                'activeSessions' => 0,
                'averageAttendance' => 0,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        }

        $department_id = $lecturerDept['department_id'];

        // Optimized query using conditional aggregation
        $stmt = $pdo->prepare("
            SELECT
                COUNT(CASE WHEN table_type = 'assigned_courses' THEN 1 END) AS assigned_courses,
                COUNT(CASE WHEN table_type = 'total_students' THEN 1 END) AS total_students,
                COUNT(CASE WHEN table_type = 'today_attendance' THEN 1 END) AS today_attendance,
                COUNT(CASE WHEN table_type = 'week_attendance' THEN 1 END) AS week_attendance,
                COUNT(CASE WHEN table_type = 'pending_leaves' THEN 1 END) AS pending_leaves,
                COUNT(CASE WHEN table_type = 'approved_leaves' THEN 1 END) AS approved_leaves,
                COUNT(CASE WHEN table_type = 'total_sessions' THEN 1 END) AS total_sessions,
                COUNT(CASE WHEN table_type = 'active_sessions' THEN 1 END) AS active_sessions,
                ROUND(AVG(CASE WHEN table_type = 'attendance_rate' THEN attendance_rate END), 1) AS average_attendance
            FROM (
                SELECT 'assigned_courses' AS table_type, NULL AS attendance_rate FROM courses WHERE department_id = ?
                UNION ALL
                SELECT 'total_students', NULL FROM students st INNER JOIN options o ON st.option_id = o.id WHERE o.department_id = ?
                UNION ALL
                SELECT 'today_attendance', NULL FROM attendance_records ar
                INNER JOIN attendance_sessions s ON ar.session_id = s.id
                INNER JOIN courses c ON s.course_id = c.id
                WHERE c.department_id = ? AND DATE(ar.recorded_at) = CURDATE()
                UNION ALL
                SELECT 'week_attendance', NULL FROM attendance_records ar
                INNER JOIN attendance_sessions s ON ar.session_id = s.id
                INNER JOIN courses c ON s.course_id = c.id
                WHERE c.department_id = ? AND DATE(ar.recorded_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                UNION ALL
                SELECT 'pending_leaves', NULL FROM leave_requests lr
                INNER JOIN students st ON lr.student_id = st.id
                INNER JOIN options o ON st.option_id = o.id
                WHERE o.department_id = ? AND lr.status = 'pending'
                UNION ALL
                SELECT 'approved_leaves', NULL FROM leave_requests lr
                INNER JOIN students st ON lr.student_id = st.id
                INNER JOIN options o ON st.option_id = o.id
                WHERE o.department_id = ? AND lr.status = 'approved'
                UNION ALL
                SELECT 'total_sessions', NULL FROM attendance_sessions s
                INNER JOIN courses c ON s.course_id = c.id
                WHERE c.department_id = ?
                UNION ALL
                SELECT 'active_sessions', NULL FROM attendance_sessions s
                INNER JOIN courses c ON s.course_id = c.id
                WHERE c.department_id = ? AND s.end_time IS NULL
                UNION ALL
                SELECT 'attendance_rate', (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0))
                FROM attendance_sessions s
                LEFT JOIN attendance_records ar ON s.id = ar.session_id
                INNER JOIN courses c ON s.course_id = c.id
                WHERE c.department_id = ? AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY s.id
            ) AS combined_data
        ");

        $stmt->execute([
            $department_id, $department_id, $department_id, $department_id,
            $department_id, $department_id, $department_id, $department_id, $department_id
        ]);

        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        // Sanitize and validate data
        $stats = [
            'assignedCourses' => max(0, (int)($counts['assigned_courses'] ?? 0)),
            'totalStudents' => max(0, (int)($counts['total_students'] ?? 0)),
            'todayAttendance' => max(0, (int)($counts['today_attendance'] ?? 0)),
            'weekAttendance' => max(0, (int)($counts['week_attendance'] ?? 0)),
            'pendingLeaves' => max(0, (int)($counts['pending_leaves'] ?? 0)),
            'approvedLeaves' => max(0, (int)($counts['approved_leaves'] ?? 0)),
            'totalSessions' => max(0, (int)($counts['total_sessions'] ?? 0)),
            'activeSessions' => max(0, (int)($counts['active_sessions'] ?? 0)),
            'averageAttendance' => round(max(0, min(100, (float)($counts['average_attendance'] ?? 0))), 1),
            'last_updated' => date('Y-m-d H:i:s')
        ];

        // Cache the results
        if (!is_dir('cache')) {
            mkdir('cache', 0755, true);
        }
        file_put_contents($cache_file, json_encode($stats));

        return $stats;

    } catch (PDOException $e) {
        error_log("Lecturer dashboard stats error: " . $e->getMessage());
        return [
            'assignedCourses' => 0,
            'totalStudents' => 0,
            'todayAttendance' => 0,
            'weekAttendance' => 0,
            'pendingLeaves' => 0,
            'approvedLeaves' => 0,
            'totalSessions' => 0,
            'activeSessions' => 0,
            'averageAttendance' => 0,
            'last_updated' => date('Y-m-d H:i:s'),
            'error' => 'Database connection failed. Please try again later.'
        ];
    }
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

// Fetch lecturer info with improved error handling
try {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Only allow lecturers (double check)
    if (!$user || $user['role'] !== 'lecturer') {
        session_destroy();
        header("Location: index.php?error=unauthorized");
        exit();
    }
} catch (PDOException $e) {
    session_destroy();
    header("Location: index.php?error=database");
    exit();
}

$lecturer_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($lecturer_name)) {
    $lecturer_name = $user['username'];
}
$lecturer_email = $user['email'];

// Get lecturer's department_id
$dept_stmt = $pdo->prepare("
    SELECT l.department_id, l.id as lecturer_id, d.name as department_name
    FROM lecturers l
    INNER JOIN users u ON l.user_id = u.id
    LEFT JOIN departments d ON l.department_id = d.id
    WHERE u.id = :user_id AND u.role = 'lecturer'
");
$dept_stmt->execute(['user_id' => $user_id]);
$lecturer_dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer_dept || !isset($lecturer_dept['department_id'])) {
    header("Location: index.php?error=lecturer_setup_required");
    exit;
}

// Store lecturer_id and department info in session
$_SESSION['lecturer_id'] = $lecturer_dept['lecturer_id'];
$_SESSION['department_id'] = $lecturer_dept['department_id'];
$_SESSION['department_name'] = $lecturer_dept['department_name'] ?? 'Not Assigned';

$lecturer_id = $_SESSION['lecturer_id'];

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    try {
        $stats = getLecturerDashboardStats($lecturer_id);

        // Check if there's a database error in stats
        if (isset($stats['error'])) {
            echo json_encode([
                'status' => 'error',
                'message' => $stats['error']
            ]);
            exit;
        }

        $activities = getRecentActivities($pdo, $lecturer_id);
        $coursePerformance = getCoursePerformanceData($pdo, $lecturer_id);
        $attendanceTrends = getAttendanceTrends($pdo, $lecturer_id);

        // Calculate attendance trends summary
        $totalAttendance7Days = array_sum(array_column($attendanceTrends, 'count'));
        $avgAttendance7Days = count($attendanceTrends) > 0 ? round($totalAttendance7Days / count($attendanceTrends), 1) : 0;
        $peakAttendance7Days = !empty($attendanceTrends) ? max(array_column($attendanceTrends, 'count')) : 0;
        $lowestAttendance7Days = !empty($attendanceTrends) ? min(array_column($attendanceTrends, 'count')) : 0;

        echo json_encode([
            'status' => 'success',
            'data' => array_merge($stats, [
                'activities' => $activities,
                'coursePerformance' => $coursePerformance,
                'attendanceTrends' => $attendanceTrends,
                'totalAttendance7Days' => $totalAttendance7Days,
                'avgAttendance7Days' => $avgAttendance7Days,
                'peakAttendance7Days' => $peakAttendance7Days,
                'lowestAttendance7Days' => $lowestAttendance7Days
            ]),
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
}

// Load dashboard statistics
$dashboard_stats = getLecturerDashboardStats($lecturer_id);

// Check if there's a database error
if (isset($dashboard_stats['error'])) {
    $error_message = $dashboard_stats['error'];
} else {
    $activities = getRecentActivities($pdo, $lecturer_id);
    $coursePerformance = getCoursePerformanceData($pdo, $lecturer_id);
    $attendanceTrends = getAttendanceTrends($pdo, $lecturer_id);
}

// Only process dashboard data if no error
if (!isset($dashboard_stats['error'])) {
    // Calculate attendance trends summary
    $totalAttendance7Days = array_sum(array_column($attendanceTrends, 'count'));
    $avgAttendance7Days = count($attendanceTrends) > 0 ? round($totalAttendance7Days / count($attendanceTrends), 1) : 0;
    $peakAttendance7Days = !empty($attendanceTrends) ? max(array_column($attendanceTrends, 'count')) : 0;
    $lowestAttendance7Days = !empty($attendanceTrends) ? min(array_column($attendanceTrends, 'count')) : 0;

    // Extract stats for easier access
    $total_students = $dashboard_stats['totalStudents'];
    $assigned_courses = $dashboard_stats['assignedCourses'];
    $today_attendance = $dashboard_stats['todayAttendance'];
    $week_attendance = $dashboard_stats['weekAttendance'];
    $pending_leaves = $dashboard_stats['pendingLeaves'];
    $approved_leaves = $dashboard_stats['approvedLeaves'];
    $total_sessions = $dashboard_stats['totalSessions'];
    $active_sessions = $dashboard_stats['activeSessions'];
    $avg_attendance_rate = $dashboard_stats['averageAttendance'];
    $last_updated = $dashboard_stats['last_updated'];
} else {
    // Set default values when there's an error
    $totalAttendance7Days = 0;
    $avgAttendance7Days = 0;
    $peakAttendance7Days = 0;
    $lowestAttendance7Days = 0;
    $total_students = 0;
    $assigned_courses = 0;
    $today_attendance = 0;
    $week_attendance = 0;
    $pending_leaves = 0;
    $approved_leaves = 0;
    $total_sessions = 0;
    $active_sessions = 0;
    $avg_attendance_rate = 0;
    $last_updated = date('Y-m-d H:i:s');
    $activities = [];
    $coursePerformance = [];
    $attendanceTrends = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lecturer Dashboard | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/admin-dashboard.css" rel="stylesheet">
    <link href="css/lecturer-dashboard.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>

<style>
:root {
    --primary-color: #0ea5e9;
    --secondary-color: #0ea5e9;
    --accent-color: #0ea5e9;
    --success-color: #0ea5e9;
    --warning-color: #0ea5e9;
    --info-color: #0ea5e9;
    --danger-color: #0ea5e9;
    --light-bg: #ffffff;
    --dark-bg: #000000;
    --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    --card-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
    --border-radius: 16px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

* {
    box-sizing: border-box;
}

body {
    font-family: 'Poppins', 'Inter', 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 50%, #7dd3fc 100%);
    margin: 0;
    min-height: 100vh;
    color: var(--dark-bg);
    line-height: 1.6;
}

.sidebar {
    position: fixed; 
    top: 0; 
    left: 0; 
    width: 280px; 
    height: 100vh;
    background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    background-attachment: fixed;
    color: white; 
    padding-top: 30px; 
    box-shadow: 4px 0 20px rgba(0,0,0,0.15);
    z-index: 1000; 
    overflow-y: auto;
    backdrop-filter: blur(10px);
    transition: var(--transition);
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar.collapsed .sidebar-text {
    display: none;
}

.sidebar.collapsed .profile-info {
    display: none;
}

.sidebar a {
    display: flex; 
    align-items: center;
    padding: 15px 25px; 
    color: #fff; 
    text-decoration: none;
    transition: var(--transition); 
    margin: 5px 15px; 
    border-radius: 12px;
    font-weight: 500; 
    position: relative; 
    overflow: hidden;
}

.sidebar a::before {
    content: ''; 
    position: absolute; 
    top: 0; 
    left: -100%; 
    width: 100%; 
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.sidebar a:hover::before, .sidebar a.active::before {
    left: 100%;
}

.sidebar a:hover, .sidebar a.active {
    background: rgba(255,255,255,0.15);
    transform: translateX(8px) scale(1.02);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.sidebar-toggle {
    position: absolute;
    top: 20px;
    right: -15px;
    background: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    cursor: pointer;
    z-index: 1001;
    transition: var(--transition);
}

.sidebar-toggle:hover {
    transform: scale(1.1);
}

.topbar {
    margin-left: 280px; 
    background: rgba(255,255,255,0.95); 
    padding: 20px 40px;
    border-bottom: 1px solid rgba(0,0,0,0.1); 
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    backdrop-filter: blur(20px); 
    -webkit-backdrop-filter: blur(20px);
    transition: var(--transition);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.topbar.expanded {
    margin-left: 80px;
}

.main-content {
    margin-left: 280px; 
    padding: 40px;
    transition: var(--transition);
}

.main-content.expanded {
    margin-left: 80px;
}

.card {
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    margin-bottom: 30px;
    transition: var(--transition);
    border: 2px solid #000000;
    overflow: hidden;
    background: #ffffff;
}

.card:hover { 
    transform: translateY(-8px) scale(1.01); 
    box-shadow: var(--card-shadow-hover); 
}

.widget {
    background: #ffffff;
    border-radius: var(--border-radius);
    padding: 30px;
    text-align: center;
    box-shadow: var(--card-shadow);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    border: 2px solid #000000;
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.widget:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: var(--card-shadow-hover);
}

.widget::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: #000000;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.widget h3 {
    margin-bottom: 20px;
    color: #000000;
    font-size: 0.9rem;
    font-weight: 700;
    letter-spacing: -0.025em;
}

.widget p {
    font-size: 1.5rem;
    font-weight: 800;
    margin: 0;
    color: #000000;
    text-shadow: none;
}

.widget.quick-actions { 
    text-align: left; 
}
.widget.quick-actions h3 { 
    text-align: center; 
}

.nav-links a {
    display: inline-block; 
    margin-right: 15px; 
    color: var(--primary-color); 
    font-weight: 600;
    text-decoration: none; 
    border-bottom: 2px solid transparent; 
    padding-bottom: 5px;
    transition: var(--transition); 
    border-radius: 8px; 
    padding: 10px 20px;
    background: rgba(255,255,255,0.8); 
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.nav-links a:hover, .nav-links a.active {
    border-color: var(--secondary-color); 
    color: var(--secondary-color); 
    transform: translateY(-2px);
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white; 
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.activity-item {
    transition: var(--transition);
    padding: 15px;
    border-radius: 12px;
    border-left: 5px solid #000000;
    background: #ffffff;
    margin-bottom: 10px;
    border: 1px solid #000000;
}

.activity-item:hover { 
    background-color: rgba(49, 130, 206, 0.1); 
    transform: translateX(8px) scale(1.01); 
}

.footer {
    text-align: center;
    margin-left: 280px;
    padding: 20px;
    font-size: 0.9rem;
    color: #000000;
    background: #ffffff;
    border-top: 2px solid #000000;
    transition: var(--transition);
}

.footer.expanded {
    margin-left: 80px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.chart-container {
    background: #ffffff;
    border-radius: var(--border-radius);
    padding: 30px;
    box-shadow: var(--card-shadow);
    margin-bottom: 25px;
    border: 2px solid #000000;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
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

.stats-grid .widget {
    animation: fadeInUp 0.6s ease-out forwards;
}

.stats-grid .widget:nth-child(1) { animation-delay: 0.1s; }
.stats-grid .widget:nth-child(2) { animation-delay: 0.2s; }
.stats-grid .widget:nth-child(3) { animation-delay: 0.3s; }
.stats-grid .widget:nth-child(4) { animation-delay: 0.4s; }
.stats-grid .widget:nth-child(5) { animation-delay: 0.5s; }
.stats-grid .widget:nth-child(6) { animation-delay: 0.6s; }
.stats-grid .widget:nth-child(7) { animation-delay: 0.7s; }
.stats-grid .widget:nth-child(8) { animation-delay: 0.8s; }

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

/* Dark mode support - using sky blue and white only */
@media (prefers-color-scheme: dark) {
    body {
        background: #ffffff;
    }

    .widget, .card, .chart-container {
        background: #ffffff;
        color: #0ea5e9;
    }

    .widget h3, .widget p {
        color: #0ea5e9;
    }

    .topbar {
        background: rgba(255, 255, 255, 0.95);
        color: #0ea5e9;
    }

    .footer {
        background: #ffffff;
        color: #0ea5e9;
    }
}

@media (max-width: 768px) {
    .sidebar, .topbar, .main-content, .footer {
        margin-left: 0 !important; 
        width: 100%;
    }
    .sidebar {
        position: fixed;
        width: 100%;
        height: auto;
        display: block;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 0 0 var(--border-radius) var(--border-radius);
        transform: translateY(-100%);
        transition: transform 0.3s ease;
        z-index: 1000;
    }
    
    .sidebar.mobile-open {
        transform: translateY(0);
    }
    
    .sidebar a {
        padding: 12px 18px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin: 0;
        border-radius: 0;
    }
    
    .widget p { 
        font-size: 2.2rem; 
    }
    
    .main-content { 
        padding: 20px; 
    }
    
    .mobile-menu-toggle {
        display: block !important;
        position: fixed;
        top: 20px;
        left: 20px;
        background: var(--primary-color);
        color: white;
        border: none;
        border-radius: 8px;
        width: 50px;
        height: 50px;
        z-index: 1001;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
}

@media (max-width: 992px) {
    .col-lg-3 { 
        margin-bottom: 25px; 
    }
    .stats-grid { 
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); 
        gap: 20px; 
    }
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

/* Logo styling to match login page */
.logo-container {
    position: relative;
    margin-bottom: 1.5rem;
    display: inline-block;
}

.logo-container img {
    height: 70px;
    width: auto;
    border-radius: 16px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    transition: var(--transition);
    border: 2px solid rgba(255,255,255,0.2);
}

.logo-container:hover img {
    transform: scale(1.08) rotate(2deg);
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.2);
}

.logo-glow {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, rgba(26, 54, 93, 0.3), rgba(43, 119, 230, 0.3));
    border-radius: 50%;
    filter: blur(25px);
    opacity: 0.7;
    z-index: -1;
    animation: pulse 4s ease-in-out infinite;
}

/* Mobile menu toggle */
.mobile-menu-toggle {
    display: none;
}

/* Notification badge */
.notification-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--danger-color);
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 0.7rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Progress indicators */
.progress-container {
    margin-top: 10px;
}

.progress {
    height: 8px;
    border-radius: 4px;
    background-color: #e9ecef;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}

/* Status indicators */
.status-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 5px;
}

.status-active {
    background-color: var(--success-color);
}

.status-inactive {
    background-color: var(--danger-color);
}

.status-pending {
    background-color: var(--warning-color);
}

/* Search box */
.search-box {
    position: relative;
    max-width: 300px;
}

.search-box input {
    padding-right: 40px;
    border-radius: 20px;
}

.search-box .search-icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
}

/* Toast notifications */
.toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    min-width: 300px;
}

/* Empty state styling */
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Card header improvements */
.card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
    padding: 20px 25px;
    font-weight: 600;
}

/* Table improvements */
.table-responsive {
    border-radius: var(--border-radius);
    overflow: hidden;
}

.table th {
    background-color: var(--light-bg);
    font-weight: 600;
    border: none;
}

.table td {
    border-color: #e9ecef;
    vertical-align: middle;
}

/* Button improvements */
.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: var(--transition);
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn i {
    margin-right: 8px;
}

.btn-lg {
    padding: 12px 24px;
    font-size: 1.1rem;
}

/* Form improvements */
.form-control, .form-select {
    border-radius: 8px;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    transition: var(--transition);
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* Alert improvements */
.alert {
    border-radius: var(--border-radius);
    border: none;
    padding: 15px 20px;
}

.alert i {
    margin-right: 10px;
}

/* Badge improvements */
.badge {
    border-radius: 6px;
    padding: 6px 12px;
    font-weight: 500;
}

/* Modal improvements */
.modal-content {
    border-radius: var(--border-radius);
    border: none;
    box-shadow: var(--card-shadow);
}

.modal-header {
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.modal-footer {
    border-radius: 0 0 var(--border-radius) var(--border-radius);
}

/* Tooltip improvements */
.tooltip {
    font-size: 0.8rem;
}

.tooltip .tooltip-inner {
    border-radius: 6px;
    padding: 6px 12px;
}

/* Pagination improvements */
.pagination {
    border-radius: 8px;
}

.page-link {
    border-radius: 6px;
    margin: 0 3px;
    border: none;
    color: var(--primary-color);
}

.page-item.active .page-link {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-color: var(--primary-color);
}

/* Filter controls */
.filter-controls {
    background: rgba(255,255,255,0.8);
    border-radius: var(--border-radius);
    padding: 20px;
    margin-bottom: 25px;
    backdrop-filter: blur(10px);
}

/* Data table actions */
.data-table-actions {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

/* Responsive text */
@media (max-width: 576px) {
    h1 {
        font-size: 1.8rem !important;
    }
    
    h2 {
        font-size: 1.5rem !important;
    }
    
    h3 {
        font-size: 1.3rem !important;
    }
    
    .widget p {
        font-size: 2rem !important;
    }
}

/* Focus management for accessibility */
.skip-link {
    position: absolute;
    top: -40px;
    left: 6px;
    background: var(--primary-color);
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    z-index: 10000;
    transition: top 0.3s;
}

.skip-link:focus {
    top: 6px;
}

/* Print optimizations */
@media print {
    .no-print {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    body {
        background: white !important;
        color: black !important;
    }
}

/* Performance optimizations */
.will-change {
    will-change: transform, opacity;
}

/* Custom utility classes */
.gradient-bg {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
}

.text-gradient {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.border-gradient {
    border: 2px solid;
    border-image: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) 1;
}

.shadow-custom {
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

.shadow-custom-hover {
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}

/* Animation classes */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

.slide-in-left {
    animation: slideInLeft 0.5s ease-out;
}

.slide-in-right {
    animation: slideInRight 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideInLeft {
    from { transform: translateX(-100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

@keyframes slideInRight {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Grid layout improvements */
.grid {
    display: grid;
    gap: 25px;
}

.grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
}

.grid-3 {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.grid-4 {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

/* Flex layout improvements */
.flex-center {
    display: flex;
    align-items: center;
    justify-content: center;
}

.flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.flex-around {
    display: flex;
    align-items: center;
    justify-content: space-around;
}

/* Spacing utilities */
.mb-0 { margin-bottom: 0 !important; }
.mt-0 { margin-top: 0 !important; }
.ml-0 { margin-left: 0 !important; }
.mr-0 { margin-right: 0 !important; }

.pb-0 { padding-bottom: 0 !important; }
.pt-0 { padding-top: 0 !important; }
.pl-0 { padding-left: 0 !important; }
.pr-0 { padding-right: 0 !important; }

/* Text utilities */
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.text-truncate-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Custom component styles */
.avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
}

.avatar-sm {
    width: 30px;
    height: 30px;
}

.avatar-lg {
    width: 60px;
    height: 60px;
}

.avatar-xl {
    width: 80px;
    height: 80px;
}

/* Icon wrapper */
.icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: rgba(0, 51, 102, 0.1);
    color: var(--primary-color);
}

.icon-wrapper-sm {
    width: 30px;
    height: 30px;
}

.icon-wrapper-lg {
    width: 50px;
    height: 50px;
}

/* Divider */
.divider {
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,0,0,0.1), transparent);
    margin: 20px 0;
}

/* Custom checkbox and radio */
.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

/* Custom range slider */
.form-range::-webkit-slider-thumb {
    background: var(--primary-color);
}

.form-range::-moz-range-thumb {
    background: var(--primary-color);
}

/* Custom toggle switch */
.form-switch .form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

/* Custom file input */
.form-file-input:focus ~ .form-file-label {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* Custom select */
.form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* Custom input group */
.input-group-text {
    background-color: var(--light-bg);
    border-color: #ced4da;
}

/* Custom list group */
.list-group-item {
    border-color: #e9ecef;
    padding: 15px 20px;
}

.list-group-item.active {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-color: var(--primary-color);
}

/* Custom breadcrumb */
.breadcrumb {
    background-color: var(--light-bg);
    border-radius: 8px;
    padding: 10px 15px;
}

.breadcrumb-item.active {
    color: var(--primary-color);
    font-weight: 500;
}

/* Custom nav tabs */
.nav-tabs {
    border-bottom: 2px solid #e9ecef;
}

.nav-tabs .nav-link {
    border: none;
    border-bottom: 2px solid transparent;
    color: #6c757d;
    font-weight: 500;
    padding: 12px 20px;
    transition: var(--transition);
}

.nav-tabs .nav-link:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
}

.nav-tabs .nav-link.active {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: transparent;
}

/* Custom nav pills */
.nav-pills .nav-link {
    border-radius: 8px;
    padding: 10px 20px;
    font-weight: 500;
    transition: var(--transition);
}

.nav-pills .nav-link.active {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
}

/* Custom accordion */
.accordion-button {
    border-radius: 8px;
    font-weight: 500;
    padding: 15px 20px;
}

.accordion-button:not(.collapsed) {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.accordion-button:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* Custom card groups */
.card-group {
    border-radius: var(--border-radius);
    overflow: hidden;
}

.card-group .card {
    margin-bottom: 0;
}

/* Custom jumbotron */
.jumbotron {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-radius: var(--border-radius);
    padding: 40px;
}

/* Custom alerts with icons */
.alert-with-icon {
    display: flex;
    align-items: flex-start;
}

.alert-icon {
    margin-right: 15px;
    font-size: 1.5rem;
}

.alert-content {
    flex: 1;
}

/* Custom progress bars */
.progress {
    background-color: #e9ecef;
    border-radius: 10px;
    overflow: hidden;
}

.progress-bar {
    border-radius: 10px;
}

/* Custom badges with icons */
.badge-with-icon {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

/* Custom dropdowns */
.dropdown-menu {
    border-radius: 8px;
    border: none;
    box-shadow: var(--card-shadow);
    padding: 10px 0;
}

.dropdown-item {
    padding: 8px 20px;
    transition: var(--transition);
}

.dropdown-item:hover {
    background-color: var(--light-bg);
}

.dropdown-divider {
    margin: 10px 0;
}

/* Custom carousel */
.carousel {
    border-radius: var(--border-radius);
    overflow: hidden;
}

.carousel-item {
    border-radius: var(--border-radius);
}

/* Custom close button */
.btn-close:focus {
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* Custom spinner */
.spinner-border {
    border-width: 2px;
}

/* Custom toasts */
.toast {
    border-radius: 8px;
    border: none;
    box-shadow: var(--card-shadow);
}

.toast-header {
    border-radius: 8px 8px 0 0;
}

/* Custom tooltips */
.tooltip {
    font-size: 0.8rem;
}

.tooltip .tooltip-inner {
    border-radius: 6px;
    padding: 6px 12px;
}

/* Custom popovers */
.popover {
    border-radius: 8px;
    border: none;
    box-shadow: var(--card-shadow);
}

.popover-header {
    border-radius: 8px 8px 0 0;
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

/* Custom modal backdrop */
.modal-backdrop {
    background-color: rgba(0, 0, 0, 0.5);
}

/* Custom offcanvas */
.offcanvas {
    border-radius: 0;
}

.offcanvas-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

/* Custom figure */
.figure {
    border-radius: var(--border-radius);
    overflow: hidden;
}

.figure-caption {
    color: #6c757d;
}

/* Custom blockquote */
.blockquote {
    border-left: 4px solid var(--primary-color);
    padding-left: 20px;
    font-style: italic;
}

.blockquote-footer {
    color: #6c757d;
}

/* Custom code */
code {
    background-color: var(--light-bg);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.9em;
}

pre {
    background-color: var(--light-bg);
    padding: 15px;
    border-radius: 8px;
    overflow-x: auto;
}

/* Custom kbd */
kbd {
    background-color: var(--dark-bg);
    color: white;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 0.9em;
}

/* Custom mark */
mark {
    background: linear-gradient(135deg, #fff9c4, #fff59d);
    padding: 2px 6px;
    border-radius: 4px;
}

/* Custom small */
small {
    font-size: 0.8em;
    opacity: 0.8;
}

/* Custom strong */
strong {
    font-weight: 600;
}

/* Custom em */
em {
    font-style: italic;
}

/* Custom abbr */
abbr[title] {
    text-decoration: none;
    border-bottom: 1px dotted #6c757d;
    cursor: help;
}

/* Custom initialism */
.initialism {
    font-size: 0.9em;
    text-transform: uppercase;
}

/* Custom list */
.list-unstyled {
    padding-left: 0;
    list-style: none;
}

.list-inline {
    padding-left: 0;
    list-style: none;
}

.list-inline-item {
    display: inline-block;
}

.list-inline-item:not(:last-child) {
    margin-right: 10px;
}

/* Custom dl */
dl {
    margin-bottom: 0;
}

dt {
    font-weight: 600;
}

dd {
    margin-left: 0;
}

/* Custom horizontal rule */
hr {
    border: none;
    height: 1px;
    background: linear-gradient(90deg, transparent, rgba(0,0,0,0.1), transparent);
    margin: 20px 0;
}

/* Custom selection */
::selection {
    background: rgba(0, 51, 102, 0.2);
}

::-moz-selection {
    background: rgba(0, 51, 102, 0.2);
}

/* Custom placeholder */
::placeholder {
    color: #6c757d;
    opacity: 1;
}

::-webkit-input-placeholder {
    color: #6c757d;
    opacity: 1;
}

::-moz-placeholder {
    color: #6c757d;
    opacity: 1;
}

:-ms-input-placeholder {
    color: #6c757d;
    opacity: 1;
}

/* Custom autofill */
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
input:-webkit-autofill:active {
    -webkit-box-shadow: 0 0 0 30px white inset !important;
    -webkit-text-fill-color: var(--dark-bg) !important;
}

/* Custom number input */
input[type="number"]::-webkit-outer-spin-button,
input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

input[type="number"] {
    -moz-appearance: textfield;
}

/* Custom date input */
input[type="date"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    border-radius: 4px;
    padding: 5px;
}

input[type="date"]::-webkit-calendar-picker-indicator:hover {
    background-color: var(--light-bg);
}

/* Custom time input */
input[type="time"]::-webkit-calendar-picker-indicator {
    cursor: pointer;
    border-radius: 4px;
    padding: 5px;
}

input[type="time"]::-webkit-calendar-picker-indicator:hover {
    background-color: var(--light-bg);
}

/* Custom color input */
input[type="color"] {
    -webkit-appearance: none;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    cursor: pointer;
}

input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 0;
}

input[type="color"]::-webkit-color-swatch {
    border: none;
    border-radius: 4px;
}

/* Custom range input */
input[type="range"] {
    -webkit-appearance: none;
    width: 100%;
    height: 8px;
    border-radius: 4px;
    background: #e9ecef;
    outline: none;
}

input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--primary-color);
    cursor: pointer;
    transition: var(--transition);
}

input[type="range"]::-webkit-slider-thumb:hover {
    transform: scale(1.1);
}

input[type="range"]::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--primary-color);
    cursor: pointer;
    border: none;
    transition: var(--transition);
}

input[type="range"]::-moz-range-thumb:hover {
    transform: scale(1.1);
}

/* Custom file input */
.form-file {
    position: relative;
}

.form-file-input {
    position: absolute;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.form-file-label {
    display: block;
    padding: 10px 15px;
    border: 1px solid #ced4da;
    border-radius: 8px;
    background-color: white;
    cursor: pointer;
    transition: var(--transition);
}

.form-file-label:hover {
    border-color: var(--primary-color);
}

.form-file-input:focus ~ .form-file-label {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* Custom switch */
.form-switch {
    padding-left: 3.5em;
}

.form-switch .form-check-input {
    width: 3em;
    height: 1.5em;
    margin-left: -3.5em;
}

.form-switch .form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

/* Custom check and radio */
.form-check-input {
    width: 1.2em;
    height: 1.2em;
    margin-top: 0.2em;
}

.form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.form-check-input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* Custom select */
.form-select {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
}

.form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
}

/* Custom input group */
.input-group-text {
    background-color: var(--light-bg);
    border-color: #ced4da;
}

.input-group .form-control:focus,
.input-group .form-select:focus {
    z-index: 3;
}

/* Custom validation */
.form-control.is-valid,
.form-select.is-valid {
    border-color: var(--success-color);
}

.form-control.is-invalid,
.form-select.is-invalid {
    border-color: var(--danger-color);
}

.valid-feedback {
    color: var(--success-color);
}

.invalid-feedback {
    color: var(--danger-color);
}

/* Custom floating labels */
.form-floating > .form-control:focus ~ label,
.form-floating > .form-control:not(:placeholder-shown) ~ label,
.form-floating > .form-select ~ label {
    color: var(--primary-color);
}

.form-floating > .form-control:focus ~ label::after,
.form-floating > .form-control:not(:placeholder-shown) ~ label::after,
.form-floating > .form-select ~ label::after {
    background-color: white;
}

/* Custom input group with floating labels */
.input-group .form-floating {
    flex: 1 1 auto;
    width: 1%;
}

.input-group .form-floating:not(:first-child) .form-control {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.input-group .form-floating:not(:last-child) .form-control {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

/* Custom form grid */
.form-grid {
    display: grid;
    gap: 20px;
}

.form-grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.form-grid-3 {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

/* Custom form sections */
.form-section {
    background: white;
    border-radius: var(--border-radius);
    padding: 25px;
    box-shadow: var(--card-shadow);
    margin-bottom: 25px;
}

.form-section-header {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.form-section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.form-section-description {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Custom form actions */
.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

/* Custom form help */
.form-help {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 5px;
}

/* Custom form icons */
.form-icon {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    left: 15px;
    color: #6c757d;
    z-index: 5;
}

.form-icon ~ .form-control {
    padding-left: 45px;
}

/* Custom form groups */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    font-weight: 500;
    margin-bottom: 8px;
    color: var(--dark-bg);
}

/* Custom form rows */
.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

.form-row .form-group {
    flex: 1;
    min-width: 200px;
}

/* Custom form compact */
.form-compact .form-group {
    margin-bottom: 15px;
}

.form-compact .form-control,
.form-compact .form-select {
    padding: 8px 12px;
    font-size: 0.9rem;
}

/* Custom form large */
.form-large .form-group {
    margin-bottom: 25px;
}

.form-large .form-control,
.form-large .form-select {
    padding: 15px 20px;
    font-size: 1.1rem;
}

/* Custom form horizontal */
.form-horizontal .form-group {
    display: flex;
    align-items: center;
}

.form-horizontal label {
    flex: 0 0 150px;
    margin-bottom: 0;
    margin-right: 20px;
    text-align: right;
}

.form-horizontal .form-control {
    flex: 1;
}

/* Custom form inline */
.form-inline {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.form-inline .form-group {
    margin-bottom: 0;
    flex: 1;
}

/* Custom form steps */
.form-steps {
    display: flex;
    margin-bottom: 30px;
    counter-reset: step;
}

.form-step {
    flex: 1;
    text-align: center;
    position: relative;
}

.form-step::before {
    counter-increment: step;
    content: counter(step);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: #e9ecef;
    color: #6c757d;
    margin: 0 auto 10px;
    font-weight: 600;
}

.form-step.active::before {
    background: var(--primary-color);
    color: white;
}

.form-step.completed::before {
    background: var(--success-color);
    color: white;
    content: '✓';
}

.form-step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 15px;
    left: 50%;
    width: 100%;
    height: 2px;
    background: #e9ecef;
    z-index: -1;
}

.form-step.active:not(:last-child)::after,
.form-step.completed:not(:last-child)::after {
    background: var(--primary-color);
}

/* Custom form wizard */
.form-wizard {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.form-wizard-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 20px 25px;
}

.form-wizard-body {
    padding: 25px;
}

.form-wizard-footer {
    padding: 20px 25px;
    background: var(--light-bg);
    display: flex;
    justify-content: space-between;
}

/* Custom form tabs */
.form-tabs {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.form-tabs-header {
    background: var(--light-bg);
    padding: 0;
    border-bottom: 1px solid #e9ecef;
}

.form-tabs-body {
    padding: 25px;
}

/* Custom form accordion */
.form-accordion {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.form-accordion-item {
    border-bottom: 1px solid #e9ecef;
}

.form-accordion-item:last-child {
    border-bottom: none;
}

.form-accordion-header {
    padding: 20px 25px;
    background: var(--light-bg);
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-accordion-body {
    padding: 25px;
    display: none;
}

.form-accordion-item.active .form-accordion-body {
    display: block;
}

/* Custom form fieldset */
.form-fieldset {
    border: 1px solid #e9ecef;
    border-radius: var(--border-radius);
    padding: 25px;
    margin-bottom: 25px;
}

.form-fieldset-legend {
    font-weight: 600;
    color: var(--primary-color);
    padding: 0 10px;
    margin-left: -10px;
    background: white;
}

/* Custom form card */
.form-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 25px;
    margin-bottom: 25px;
}

.form-card-header {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.form-card-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 5px;
}

.form-card-description {
    color: #6c757d;
    font-size: 0.9rem;
}

.form-card-body {
    margin-bottom: 20px;
}

.form-card-footer {
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Custom form sidebar */
.form-sidebar {
    background: var(--light-bg);
    border-radius: var(--border-radius);
    padding: 25px;
    margin-bottom: 25px;
}

.form-sidebar-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.form-sidebar-content {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Custom form alerts */
.form-alert {
    background: var(--light-bg);
    border-radius: var(--border-radius);
    padding: 20px;
    margin-bottom: 25px;
    border-left: 4px solid var(--info-color);
}

.form-alert-success {
    border-left-color: var(--success-color);
    background: rgba(56, 161, 105, 0.1);
}

.form-alert-warning {
    border-left-color: var(--warning-color);
    background: rgba(214, 158, 46, 0.1);
}

.form-alert-danger {
    border-left-color: var(--danger-color);
    background: rgba(229, 62, 62, 0.1);
}

.form-alert-title {
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--dark-bg);
}

.form-alert-content {
    color: #6c757d;
    font-size: 0.9rem;
}

/* Custom form examples */
.form-example {
    background: var(--light-bg);
    border-radius: var(--border-radius);
    padding: 25px;
    margin-bottom: 25px;
}

.form-example-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.form-example-content {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Custom form code */
.form-code {
    background: var(--dark-bg);
    border-radius: var(--border-radius);
    padding: 25px;
    margin-bottom: 25px;
    overflow-x: auto;
}

.form-code pre {
    background: transparent;
    color: white;
    margin: 0;
    padding: 0;
}

/* Custom form preview */
.form-preview {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 25px;
    margin-bottom: 25px;
}

.form-preview-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.form-preview-content {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Custom form summary */
.form-summary {
    background: var(--light-bg);
    border-radius: var(--border-radius);
    padding: 25px;
    margin-bottom: 25px;
}

.form-summary-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.form-summary-content {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Custom form success */
.form-success {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 40px;
    text-align: center;
    margin-bottom: 25px;
}

.form-success-icon {
    font-size: 4rem;
    color: var(--success-color);
    margin-bottom: 20px;
}

.form-success-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.form-success-content {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 20px;
}

/* Custom form error */
.form-error {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 40px;
    text-align: center;
    margin-bottom: 25px;
}

.form-error-icon {
    font-size: 4rem;
    color: var(--danger-color);
    margin-bottom: 20px;
}

.form-error-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.form-error-content {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 20px;
}

/* Custom form loading */
.form-loading {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 40px;
    text-align: center;
    margin-bottom: 25px;
}

.form-loading-icon {
    font-size: 4rem;
    color: var(--primary-color);
    margin-bottom: 20px;
    animation: spin 1s linear infinite;
}

.form-loading-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.form-loading-content {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 20px;
}

/* Custom form empty */
.form-empty {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 40px;
    text-align: center;
    margin-bottom: 25px;
}

.form-empty-icon {
    font-size: 4rem;
    color: #6c757d;
    margin-bottom: 20px;
    opacity: 0.5;
}

.form-empty-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.form-empty-content {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 20px;
}

/* Custom form actions */
.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #e9ecef;
}

.form-actions-left {
    justify-content: flex-start;
}

.form-actions-center {
    justify-content: center;
}

.form-actions-between {
    justify-content: space-between;
}

.form-actions-stacked {
    flex-direction: column;
}

.form-actions-stacked .btn {
    width: 100%;
}

/* Custom form groups */
.form-group-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

.form-group-column {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 20px;
}

.form-group-column .form-group {
    margin-bottom: 0;
}

/* Custom form layouts */
.form-layout-vertical {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-layout-horizontal {
    display: flex;
    gap: 20px;
}

.form-layout-grid {
    display: grid;
    gap: 20px;
}

.form-layout-grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.form-layout-grid-3 {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

/* Custom form responsive */
@media (max-width: 768px) {
    .form-layout-horizontal {
        flex-direction: column;
    }
    
    .form-group-row {
        flex-direction: column;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}

/* Custom form utilities */
.form-required label::after {
    content: ' *';
    color: var(--danger-color);
}

.form-disabled {
    opacity: 0.6;
    pointer-events: none;
}

.form-readonly .form-control {
    background-color: var(--light-bg);
    pointer-events: none;
}

.form-compact .form-group {
    margin-bottom: 15px;
}

.form-large .form-group {
    margin-bottom: 25px;
}

/* Custom form themes */
.form-theme-light {
    background: white;
    color: var(--dark-bg);
}

.form-theme-dark {
    background: var(--dark-bg);
    color: white;
}

.form-theme-dark .form-control {
    background: #2d3748;
    border-color: #4a5568;
    color: white;
}

.form-theme-dark .form-control:focus {
    background: #2d3748;
    border-color: var(--primary-color);
    color: white;
}

.form-theme-dark .form-select {
    background: #2d3748 url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    border-color: #4a5568;
    color: white;
}

.form-theme-dark .form-select:focus {
    background: #2d3748;
    border-color: var(--primary-color);
    color: white;
}

.form-theme-dark .input-group-text {
    background: #4a5568;
    border-color: #4a5568;
    color: white;
}

.form-theme-dark .form-check-input {
    background: #2d3748;
    border-color: #4a5568;
}

.form-theme-dark .form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.form-theme-dark .form-check-label {
    color: white;
}

.form-theme-dark .form-text {
    color: #a0aec0;
}

/* Custom form animations */
.form-group {
    transition: var(--transition);
}

.form-control {
    transition: var(--transition);
}

.form-select {
    transition: var(--transition);
}

.form-check-input {
    transition: var(--transition);
}

.form-switch .form-check-input {
    transition: var(--transition);
}

/* Custom form focus states */
.form-control:focus {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 51, 102, 0.15);
}

.form-select:focus {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 51, 102, 0.15);
}

.form-check-input:focus {
    transform: scale(1.1);
}

.form-switch .form-check-input:focus {
    transform: scale(1.1);
}

/* Custom form hover states */
.form-control:hover {
    border-color: var(--primary-color);
}

.form-select:hover {
    border-color: var(--primary-color);
}

.form-check-input:hover {
    border-color: var(--primary-color);
}

.form-switch .form-check-input:hover {
    border-color: var(--primary-color);
}

/* Custom form active states */
.form-control:active {
    transform: translateY(0);
}

.form-select:active {
    transform: translateY(0);
}

.form-check-input:active {
    transform: scale(1);
}

.form-switch .form-check-input:active {
    transform: scale(1);
}

/* Custom form disabled states */
.form-control:disabled {
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}

.form-select:disabled {
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}

.form-check-input:disabled {
    opacity: 0.6;
    transform: none;
}

.form-switch .form-check-input:disabled {
    opacity: 0.6;
    transform: none;
}

/* Custom form validation states */
.form-control.is-valid {
    border-color: var(--success-color);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2338a169' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-invalid {
    border-color: var(--danger-color);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23e53e3e'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6.4.4.4-.4'/%3e%3cpath d='M6 7v2'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-select.is-valid {
    border-color: var(--success-color);
}

.form-select.is-invalid {
    border-color: var(--danger-color);
}

.form-check-input.is-valid {
    border-color: var(--success-color);
}

.form-check-input.is-valid:checked {
    background-color: var(--success-color);
    border-color: var(--success-color);
}

.form-check-input.is-invalid {
    border-color: var(--danger-color);
}

.form-check-input.is-invalid:checked {
    background-color: var(--danger-color);
    border-color: var(--danger-color);
}

/* Custom form feedback */
.valid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--success-color);
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--danger-color);
}

/* Custom form tooltips */
.form-tooltip {
    position: relative;
}

.form-tooltip .tooltip {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 5;
    display: none;
    max-width: 100%;
    padding: 0.5rem;
    margin-top: 0.1rem;
    font-size: 0.875rem;
    color: white;
    background-color: var(--dark-bg);
    border-radius: 0.375rem;
}

.form-tooltip:hover .tooltip {
    display: block;
}

/* Custom form help text */
.form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #6c757d;
}

.form-help a {
    color: var(--primary-color);
    text-decoration: none;
}

.form-help a:hover {
    text-decoration: underline;
}

/* Simple Bar Chart Styles */
.simple-bar-chart {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    height: 220px;
    padding: 20px 10px;
    gap: 6px;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--primary-color) transparent;
}

.simple-bar-chart::-webkit-scrollbar {
    height: 4px;
}

.simple-bar-chart::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.05);
    border-radius: 2px;
}

.simple-bar-chart::-webkit-scrollbar-thumb {
    background: var(--primary-color);
    border-radius: 2px;
}

.bar-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 45px;
    max-width: 60px;
}

.bar-container {
    width: 100%;
    height: 160px;
    background: rgba(0,0,0,0.08);
    border-radius: 6px 6px 0 0;
    position: relative;
    overflow: hidden;
    margin-bottom: 10px;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.05);
}

.bar-fill {
    width: 100%;
    position: absolute;
    bottom: 0;
    border-radius: 6px 6px 0 0;
    transition: height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.bar-fill:hover {
    opacity: 0.9;
}

.bar-label {
    font-size: 0.75rem;
    color: var(--dark-bg);
    font-weight: 600;
    text-align: center;
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.bar-value {
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--primary-color);
    background: rgba(14, 165, 233, 0.1);
    padding: 2px 6px;
    border-radius: 12px;
    border: 1px solid rgba(14, 165, 233, 0.2);
}

/* Attendance Trends Summary Responsive */
@media (max-width: 768px) {
    .attendance-summary .col-md-3 {
        margin-bottom: 1rem;
    }
    .attendance-summary .col-md-3:last-child {
        margin-bottom: 0;
    }

    .simple-bar-chart {
        height: 180px;
        gap: 4px;
        padding: 15px 5px;
    }

    .bar-item {
        min-width: 35px;
        max-width: 45px;
    }

    .bar-container {
        height: 130px;
    }

    .bar-label {
        font-size: 0.65rem;
        margin-bottom: 8px;
    }

    .bar-value {
        font-size: 0.75rem;
        padding: 1px 4px;
    }
}

@media (max-width: 576px) {
    .simple-bar-chart {
        height: 160px;
        gap: 2px;
    }

    .bar-item {
        min-width: 30px;
        max-width: 40px;
    }

    .bar-container {
        height: 110px;
    }

    .bar-label {
        font-size: 0.6rem;
    }

    .bar-value {
        font-size: 0.7rem;
    }
}

/* Custom form examples */
.form-example {
    background: var(--light-bg);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-example-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.form-example-content {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Custom form code */
.form-code {
    background: var(--dark-bg);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    overflow-x: auto;
}

.form-code pre {
    background: transparent;
    color: white;
    margin: 0;
    padding: 0;
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.9rem;
}

/* Custom form preview */
.form-preview {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-preview-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.form-preview-content {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Custom form summary */
.form-summary {
    background: var(--light-bg);
    border-radius: var(--border-radius);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-summary-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 0.5rem;
}

.form-summary-content {
    font-size: 0.9rem;
    color: #6c757d;
}

/* Custom form success */
.form-success {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 2.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.form-success-icon {
    font-size: 4rem;
    color: var(--success-color);
    margin-bottom: 1.25rem;
}

.form-success-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 0.625rem;
}

.form-success-content {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 1.25rem;
}

/* Custom form error */
.form-error {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 2.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.form-error-icon {
    font-size: 4rem;
    color: var(--danger-color);
    margin-bottom: 1.25rem;
}

.form-error-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 0.625rem;
}

.form-error-content {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 1.25rem;
}

/* Custom form loading */
.form-loading {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 2.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.form-loading-icon {
    font-size: 4rem;
    color: var(--primary-color);
    margin-bottom: 1.25rem;
    animation: spin 1s linear infinite;
}

.form-loading-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 0.625rem;
}

.form-loading-content {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 1.25rem;
}

/* Custom form empty */
.form-empty {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    padding: 2.5rem;
    text-align: center;
    margin-bottom: 1.5rem;
}

.form-empty-icon {
    font-size: 4rem;
    color: #6c757d;
    margin-bottom: 1.25rem;
    opacity: 0.5;
}

.form-empty-title {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 0.625rem;
}

.form-empty-content {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 1.25rem;
}

/* Custom form actions */
.form-actions {
    display: flex;
    gap: 0.625rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
    padding-top: 1.25rem;
    border-top: 1px solid #e9ecef;
}

.form-actions-left {
    justify-content: flex-start;
}

.form-actions-center {
    justify-content: center;
}

.form-actions-between {
    justify-content: space-between;
}

.form-actions-stacked {
    flex-direction: column;
}

.form-actions-stacked .btn {
    width: 100%;
}

/* Custom form groups */
.form-group-row {
    display: flex;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

.form-group-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

.form-group-column {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

.form-group-column .form-group {
    margin-bottom: 0;
}

/* Custom form layouts */
.form-layout-vertical {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-layout-horizontal {
    display: flex;
    gap: 1.25rem;
}

.form-layout-grid {
    display: grid;
    gap: 1.25rem;
}

.form-layout-grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.form-layout-grid-3 {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

/* Custom form responsive */
@media (max-width: 768px) {
    .form-layout-horizontal {
        flex-direction: column;
    }
    
    .form-group-row {
        flex-direction: column;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-actions .btn {
        width: 100%;
    }
}

/* Custom form utilities */
.form-required label::after {
    content: ' *';
    color: var(--danger-color);
}

.form-disabled {
    opacity: 0.6;
    pointer-events: none;
}

.form-readonly .form-control {
    background-color: var(--light-bg);
    pointer-events: none;
}

.form-compact .form-group {
    margin-bottom: 0.9375rem;
}

.form-large .form-group {
    margin-bottom: 1.5625rem;
}

/* Custom form themes */
.form-theme-light {
    background: white;
    color: var(--dark-bg);
}

.form-theme-dark {
    background: var(--dark-bg);
    color: white;
}

.form-theme-dark .form-control {
    background: #2d3748;
    border-color: #4a5568;
    color: white;
}

.form-theme-dark .form-control:focus {
    background: #2d3748;
    border-color: var(--primary-color);
    color: white;
}

.form-theme-dark .form-select {
    background: #2d3748 url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
    border-color: #4a5568;
    color: white;
}

.form-theme-dark .form-select:focus {
    background: #2d3748;
    border-color: var(--primary-color);
    color: white;
}

.form-theme-dark .input-group-text {
    background: #4a5568;
    border-color: #4a5568;
    color: white;
}

.form-theme-dark .form-check-input {
    background: #2d3748;
    border-color: #4a5568;
}

.form-theme-dark .form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.form-theme-dark .form-check-label {
    color: white;
}

.form-theme-dark .form-text {
    color: #a0aec0;
}

/* Custom form animations */
.form-group {
    transition: var(--transition);
}

.form-control {
    transition: var(--transition);
}

.form-select {
    transition: var(--transition);
}

.form-check-input {
    transition: var(--transition);
}

.form-switch .form-check-input {
    transition: var(--transition);
}

/* Custom form focus states */
.form-control:focus {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 51, 102, 0.15);
}

.form-select:focus {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 51, 102, 0.15);
}

.form-check-input:focus {
    transform: scale(1.1);
}

.form-switch .form-check-input:focus {
    transform: scale(1.1);
}

/* Custom form hover states */
.form-control:hover {
    border-color: var(--primary-color);
}

.form-select:hover {
    border-color: var(--primary-color);
}

.form-check-input:hover {
    border-color: var(--primary-color);
}

.form-switch .form-check-input:hover {
    border-color: var(--primary-color);
}

/* Custom form active states */
.form-control:active {
    transform: translateY(0);
}

.form-select:active {
    transform: translateY(0);
}

.form-check-input:active {
    transform: scale(1);
}

.form-switch .form-check-input:active {
    transform: scale(1);
}

/* Custom form disabled states */
.form-control:disabled {
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}

.form-select:disabled {
    opacity: 0.6;
    transform: none;
    box-shadow: none;
}

.form-check-input:disabled {
    opacity: 0.6;
    transform: none;
}

.form-switch .form-check-input:disabled {
    opacity: 0.6;
    transform: none;
}

/* Custom form validation states */
.form-control.is-valid {
    border-color: var(--success-color);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2338a169' d='M2.3 6.73.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-control.is-invalid {
    border-color: var(--danger-color);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23e53e3e'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='m5.8 3.6.4.4.4-.4'/%3e%3cpath d='M6 7v2'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

.form-select.is-valid {
    border-color: var(--success-color);
}

.form-select.is-invalid {
    border-color: var(--danger-color);
}

.form-check-input.is-valid {
    border-color: var(--success-color);
}

.form-check-input.is-valid:checked {
    background-color: var(--success-color);
    border-color: var(--success-color);
}

.form-check-input.is-invalid {
    border-color: var(--danger-color);
}

.form-check-input.is-invalid:checked {
    background-color: var(--danger-color);
    border-color: var(--danger-color);
}

/* Custom form feedback */
.valid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--success-color);
}

.invalid-feedback {
    display: block;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: var(--danger-color);
}

/* Custom form tooltips */
.form-tooltip {
    position: relative;
}

.form-tooltip .tooltip {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 5;
    display: none;
    max-width: 100%;
    padding: 0.5rem;
    margin-top: 0.1rem;
    font-size: 0.875rem;
    color: white;
    background-color: var(--dark-bg);
    border-radius: 0.375rem;
}

.form-tooltip:hover .tooltip {
    display: block;
}

/* Custom form help text */
.form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.875rem;
    color: #6c757d;
}

.form-help a {
    color: var(--primary-color);
    text-decoration: none;
}

.form-help a:hover {
    text-decoration: underline;
}
</style>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo" style="text-align: center; padding: 20px 0;">
            <h3 style="margin: 0; font-weight: 700; color: white;"><i class="fas fa-graduation-cap me-2"></i>RP System</h3>
            <small style="color: rgba(255,255,255,0.8);">Lecturer Panel</small>
        </div>

        <ul class="sidebar-nav">
            <li class="nav-section">
                <i class="fas fa-th-large me-2"></i>Main Dashboard
            </li>
            <li>
                <a href="lecturer-dashboard.php" class="active">
                    <i class="fas fa-tachometer-alt"></i>Dashboard Overview
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-book me-2"></i>Course Management
            </li>
            <li>
                <a href="lecturer-my-courses.php">
                    <i class="fas fa-book"></i>My Courses
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-calendar-check me-2"></i>Attendance
            </li>
            <li>
                <a href="attendance-session.php">
                    <i class="fas fa-video"></i>Attendance Session
                </a>
            </li>
            <li>
                <a href="attendance-reports.php">
                    <i class="fas fa-chart-line"></i>Attendance Reports
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-envelope me-2"></i>Requests
            </li>
            <li>
                <a href="leave-requests.php">
                    <i class="fas fa-envelope"></i>Leave Requests
                    <?php if ($pending_leaves > 0): ?>
                        <span class="notification-badge"><?= $pending_leaves ?></span>
                    <?php endif; ?>
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
        <!-- Page Header -->
        <div class="page-header text-center py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%); border-bottom: 2px solid #0ea5e9; margin-bottom: 20px;">
            <div class="d-flex align-items-center justify-content-center">
                <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" style="height: 60px; width: auto; margin-right: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                <h1 style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0; font-weight: 700;">
                    Lecturer Dashboard
                </h1>
            </div>
        </div>
        <div class="topbar">
            <div class="d-flex align-items-center justify-content-end">
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <div class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-chalkboard-teacher me-1"></i>Lecturer
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

        <!-- Error Message Display -->
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Error:</strong> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($dashboard_stats['error'])): ?>
        <!-- Error Display -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Database Error:</strong> <?= htmlspecialchars($dashboard_stats['error']) ?>
            <br><small class="text-muted">Please contact your system administrator if this problem persists.</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php else: ?>
        <!-- Statistics Cards -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-book text-primary"></i>
                    <h3 id="assignedCourses"><?= $assigned_courses ?></h3>
                    <p>Assigned Courses</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-users text-info"></i>
                    <h3 id="totalStudents"><?= $total_students ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-calendar-check text-success"></i>
                    <h3 id="todayAttendance" class="<?= $today_attendance > 0 ? 'pulse' : '' ?>"><?= $today_attendance ?></h3>
                    <p>Today's Attendance</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-chart-line text-warning"></i>
                    <h3 id="weekAttendance"><?= $week_attendance ?></h3>
                    <p>Weekly Attendance</p>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-clock text-secondary"></i>
                    <h3 id="pendingLeaves"><?= $pending_leaves ?></h3>
                    <p>Pending Leaves</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-check-circle text-success"></i>
                    <h3 id="approvedLeaves"><?= $approved_leaves ?></h3>
                    <p>Approved Leaves</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-video text-danger"></i>
                    <h3 id="totalSessions"><?= $total_sessions ?></h3>
                    <p>Total Sessions</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-percentage text-info"></i>
                    <h3 id="averageAttendance">
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
                                <a href="attendance-session.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-play me-2"></i>Start Session
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="lecturer-my-courses.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-book me-2"></i>My Courses
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="attendance-reports.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-chart-line me-2"></i>Reports
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="leave-requests.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-envelope me-2"></i>Leave Requests
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
                            <i class="fas fa-server me-2"></i>Session Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-video text-primary me-2"></i>Active Sessions</span>
                                <span class="badge bg-info" id="activeSessions"><?= $active_sessions ?></span>
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
                                <span><i class="fas fa-building text-info me-2"></i>Department</span>
                                <span class="badge bg-info">
                                    <?php if ($lecturer_dept['department_name']): ?>
                                        <?= htmlspecialchars(substr($lecturer_dept['department_name'], 0, 15)) ?>
                                    <?php else: ?>
                                        Not Assigned
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-shield-alt text-danger me-2"></i>Status</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Trends Chart & Recent Activities -->
        <div class="row g-4 mb-5">
            <div class="col-lg-6">
                <div class="chart-container">
                    <h5 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Attendance Trends (Last 7 Days)</h5>
                    <div class="simple-bar-chart" id="attendanceChart">
                        <!-- Chart will be populated by JS -->
                    </div>
                    <div class="attendance-summary mt-4" id="attendanceSummary">
                        <!-- Summary will be populated by JS -->
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-history me-2"></i>Recent Activities
                        </h6>
                    </div>
                    <div class="card-body" id="recentActivities">
                        <div class="text-center text-muted">
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading activities...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Lecturer Panel
    </div>

    <!-- Toast Container -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Sidebar toggle functionality
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const topbar = document.querySelector('.topbar');
        const mainContent = document.querySelector('.main-content');
        const footer = document.querySelector('.footer');

        sidebar.classList.toggle('mobile-open');
    }

    // Dashboard refresh functionality
    function refreshDashboard() {
        $('#loadingOverlay').fadeIn(200);

        $.ajax({
            url: 'lecturer-dashboard.php?ajax=1',
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(data) {
                if (data.status === 'success') {
                    // Update statistics
                    const stats = data.data;
                    $('#assignedCourses').text(stats.assignedCourses);
                    $('#totalStudents').text(stats.totalStudents);
                    $('#todayAttendance').text(stats.todayAttendance);
                    $('#weekAttendance').text(stats.weekAttendance);
                    $('#pendingLeaves').text(stats.pendingLeaves);
                    $('#approvedLeaves').text(stats.approvedLeaves);
                    $('#totalSessions').text(stats.totalSessions);
                    $('#activeSessions').text(stats.activeSessions);
                    $('#averageAttendance').text(stats.averageAttendance + '%');

                    // Update notification badge
                    const badge = $('.notification-badge');
                    if (stats.pendingLeaves > 0) {
                        badge.text(stats.pendingLeaves).show();
                    } else {
                        badge.hide();
                    }

                    // Update last update time
                    $('#last_update').text('Just now');

                    // Update attendance chart
                    const trends = data.data.attendanceTrends;
                    const chartContainer = $('#attendanceChart');
                    chartContainer.empty();

                    if (trends && trends.length > 0) {
                        const maxCount = Math.max(...trends.map(t => t.count), 1);
                        trends.forEach(trend => {
                            const percentage = (trend.count / maxCount) * 100;
                            chartContainer.append(`
                                <div class="bar-item">
                                    <div class="bar-container">
                                        <div class="bar-fill" style="height: ${percentage}%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));" title="${trend.count} attendance on ${trend.day}"></div>
                                    </div>
                                    <div class="bar-label">${trend.day}</div>
                                    <div class="bar-value">${trend.count}</div>
                                </div>
                            `);
                        });
                    } else {
                        chartContainer.html(`
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-chart-bar fa-3x mb-3 opacity-50"></i>
                                <p>No attendance data available for the last 7 days</p>
                                <small>Start an attendance session to see trends</small>
                            </div>
                        `);
                    }

                    // Update attendance summary
                    const summaryContainer = $('#attendanceSummary');
                    const totalAttendance = data.data.totalAttendance7Days || 0;
                    const avgAttendance = data.data.avgAttendance7Days || 0;
                    const peakAttendance = data.data.peakAttendance7Days || 0;
                    const lowestAttendance = data.data.lowestAttendance7Days || 0;

                    summaryContainer.html(`
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h5 mb-1 text-primary">${totalAttendance}</div>
                                    <small class="text-muted">Total Attendance</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h5 mb-1 text-info">${avgAttendance}</div>
                                    <small class="text-muted">Daily Average</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h5 mb-1 text-success">${peakAttendance}</div>
                                    <small class="text-muted">Peak Day</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h5 mb-1 text-warning">${lowestAttendance}</div>
                                    <small class="text-muted">Lowest Day</small>
                                </div>
                            </div>
                        </div>
                    `);

                    // Update recent activities
                    const activities = data.data.activities;
                    const activitiesContainer = $('#recentActivities');
                    activitiesContainer.empty();
                    if (activities.length > 0) {
                        activities.forEach(activity => {
                            activitiesContainer.append(`
                                <div class="activity-item">
                                    <strong>${activity.type}:</strong> ${activity.description}
                                    <br><small class="text-muted">${activity.timestamp}</small>
                                </div>
                            `);
                        });
                    } else {
                        activitiesContainer.html('<p class="text-muted text-center">No recent activities</p>');
                    }

                    // Show success message
                    showAlert('Dashboard data refreshed successfully', 'success');
                } else {
                    showAlert('Failed to refresh dashboard data', 'danger');
                }
            },
            error: function(xhr, status, error) {
                console.error('Dashboard refresh error:', error);
                showAlert('Error refreshing dashboard data. Please try again.', 'danger');
            },
            complete: function() {
                $('#loadingOverlay').fadeOut(200);
            }
        });
    }

    // Alert display function
    function showAlert(message, type = 'info') {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        $('#alertBox').html(alertHtml);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut(300, function() { $(this).remove(); });
        }, 5000);
    }

    // Document ready
    $(document).ready(function() {
        // Refresh button click
        $('#refreshDashboard').click(function() {
            refreshDashboard();
        });

        // Auto-refresh every 30 seconds
        setInterval(refreshDashboard, 30000);

        // Handle visibility change
        $(document).on('visibilitychange', function() {
            if (!document.hidden) {
                refreshDashboard();
            }
        });
    });
    </script>
    <?php endif; ?>

</body>
</html>