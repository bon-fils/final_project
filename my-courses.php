<?php
/**
 * My Courses - Enhanced Course Management for Lecturers
 * Displays courses with statistics, search, and management features
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_once "dashboard_utils.php";

require_role(['lecturer', 'hod']);

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

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
    // For debugging, create a default lecturer record
    $lecturer_dept = [
        'department_id' => 1, // Default department
        'lecturer_id' => 1,
        'department_name' => 'Default Department'
    ];
}

// Store lecturer_id in session for other pages to use
$_SESSION['lecturer_id'] = $lecturer_dept['lecturer_id'];
$_SESSION['department_id'] = $lecturer_dept['department_id'];
$_SESSION['department_name'] = $lecturer_dept['department_name'] ?? 'Not Assigned';

$lecturer_id = $lecturer_dept['lecturer_id'];

// First, add lecturer_id column to courses table if it doesn't exist
try {
    $pdo->query("ALTER TABLE courses ADD COLUMN lecturer_id INT NULL AFTER department_id");
    $pdo->query("CREATE INDEX idx_lecturer_id ON courses(lecturer_id)");
} catch (PDOException $e) {
    // Column might already exist, continue
}

// Update courses to assign them to the current lecturer if not already assigned
$update_stmt = $pdo->prepare("
    UPDATE courses
    SET lecturer_id = :lecturer_id
    WHERE lecturer_id IS NULL AND department_id = :department_id
");
$update_stmt->execute([
    'lecturer_id' => $lecturer_id,
    'department_id' => $lecturer_dept['department_id']
]);

// Fetch courses with enhanced statistics
try {
    $stmt = $pdo->prepare("
        SELECT
            c.id, c.name, c.course_code, c.credits, c.duration_hours, c.description,
            d.name as department_name,
            COUNT(DISTINCT s.id) as total_sessions,
            COUNT(ar.id) as total_attendance,
            COUNT(DISTINCT st.id) as enrolled_students,
            ROUND(AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END), 1) as attendance_rate,
            MAX(s.session_date) as last_session_date
        FROM courses c
        INNER JOIN departments d ON c.department_id = d.id
        LEFT JOIN attendance_sessions s ON c.id = s.course_id
        LEFT JOIN attendance_records ar ON s.id = ar.session_id
        LEFT JOIN students st ON st.option_id = c.option_id
        WHERE c.department_id = :department_id AND c.status = 'active'
        GROUP BY c.id, c.name, c.course_code, c.credits, c.duration_hours, c.description, d.name
        ORDER BY c.name ASC
    ");
    $stmt->execute(['department_id' => $lecturer_dept['department_id']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $courses = [];
}

// Get lecturer info
$user_stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
$lecturer_name = $user['username'];

// Calculate summary statistics
$total_courses = count($courses);
$total_sessions = array_sum(array_column($courses, 'total_sessions'));
$total_students = array_sum(array_column($courses, 'enrolled_students'));
$avg_attendance = $courses ? round(array_sum(array_column($courses, 'attendance_rate')) / count($courses), 1) : 0;

// Simple test output for debugging - remove this later
// echo "<h1>My Courses Page Test</h1>";
// echo "<p>Total courses: $total_courses</p>";
// echo "<p>Lecturer: $lecturer_name</p>";
// foreach ($courses as $course) {
//     echo "<div>Course: {$course['name']} ({$course['course_code']})</div>";
// }
// exit; // Stop here for testing
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Courses | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="css/admin-dashboard.css" rel="stylesheet">
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

/* Course Cards */
.course-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    margin-bottom: 20px;
    transition: var(--transition);
    border: 1px solid rgba(0,0,0,0.1);
    overflow: hidden;
}

.course-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-shadow-hover);
}

.course-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    padding: 20px;
    position: relative;
}

.course-header h5 {
    margin: 0;
    font-weight: 600;
}

.course-code {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 5px;
}

.course-body {
    padding: 20px;
}

.course-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: rgba(0,0,0,0.05);
    border-radius: 8px;
}

.stat-item .stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary-color);
    display: block;
}

.stat-item .stat-label {
    font-size: 0.8rem;
    color: #666;
    margin-top: 2px;
}

.course-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-course {
    flex: 1;
    min-width: 120px;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 500;
    transition: var(--transition);
}

.btn-course:hover {
    transform: translateY(-2px);
}

/* Search and Filters */
.search-container {
    background: white;
    border-radius: var(--border-radius);
    padding: 20px;
    box-shadow: var(--card-shadow);
    margin-bottom: 30px;
    border: 1px solid rgba(0,0,0,0.1);
}

.filter-controls {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-controls .form-control,
.filter-controls .form-select {
    min-width: 200px;
}

/* Responsive Design */
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

    .course-stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .course-actions {
        flex-direction: column;
    }

    .btn-course {
        width: 100%;
    }

    .filter-controls {
        flex-direction: column;
        align-items: stretch;
    }

    .filter-controls .form-control,
    .filter-controls .form-select {
        min-width: auto;
    }
}

/* Logo styling */
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

@media (max-width: 768px) {
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

/* Loading states */
.loading-shimmer {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% { background-position: -200% 0; }
    100% { background-position: 200% 0; }
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 20px;
    opacity: 0.5;
}

/* Course modal */
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

/* Progress bars for attendance */
.progress {
    height: 8px;
    border-radius: 4px;
    background-color: #e9ecef;
    overflow: hidden;
    margin: 5px 0;
}

.progress-bar {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}

/* Status badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-high { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
.status-medium { background: rgba(251, 191, 36, 0.1); color: #d97706; }
.status-low { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
</style>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
        <i class="fas fa-bars" aria-hidden="true></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-container text-center">
            <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" onerror="this.style.display='none'">
            <div class="logo-glow"></div>
        </div>

        <div class="text-center mb-4">
            <h3 style="margin: 0; font-weight: 700; color: white;"><i class="fas fa-graduation-cap me-2"></i>RP System</h3>
            <small style="color: rgba(255,255,255,0.8);">Lecturer Panel</small>
        </div>

        <ul class="sidebar-nav">
            <li class="nav-section">
                <i class="fas fa-th-large me-2"></i>Main Dashboard
            </li>
            <li>
                <a href="lecturer-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i><span class="sidebar-text"> Dashboard Overview</span>
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-book me-2"></i>Course Management
            </li>
            <li>
                <a href="my-courses.php" class="active">
                    <i class="fas fa-book"></i><span class="sidebar-text"> My Courses</span>
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-calendar-check me-2"></i>Attendance
            </li>
            <li>
                <a href="attendance-session.php">
                    <i class="fas fa-video"></i><span class="sidebar-text"> Attendance Session</span>
                </a>
            </li>
            <li>
                <a href="attendance-reports.php">
                    <i class="fas fa-chart-line"></i><span class="sidebar-text"> Attendance Reports</span>
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-envelope me-2"></i>Requests
            </li>
            <li>
                <a href="leave-requests.php">
                    <i class="fas fa-envelope"></i><span class="sidebar-text"> Leave Requests</span>
                    <?php if (isset($_SESSION['pending_leaves']) && $_SESSION['pending_leaves'] > 0): ?>
                        <span class="notification-badge"><?= $_SESSION['pending_leaves'] ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-sign-out-alt me-2"></i>Account
            </li>
            <li>
                <a href="logout.php" class="text-danger">
                    <i class="fas fa-sign-out-alt"></i><span class="sidebar-text"> Logout</span>
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
                    My Courses
                </h1>
            </div>
        </div>

        <div class="topbar">
            <div class="d-flex align-items-center justify-content-end">
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-chalkboard-teacher me-1"></i>Lecturer
                    </div>
                    <div class="badge bg-info fs-6 px-3 py-2">
                        <i class="fas fa-building me-1"></i>Computer Science
                    </div>
                    <button class="btn btn-outline-primary btn-sm" onclick="location.reload()" title="Refresh Courses">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Course Statistics Overview -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-book text-primary"></i>
                    <h3><?= $total_courses ?></h3>
                    <p>Total Courses</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-users text-info"></i>
                    <h3><?= $total_students ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-video text-success"></i>
                    <h3><?= $total_sessions ?></h3>
                    <p>Total Sessions</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="widget">
                    <i class="fas fa-percentage text-warning"></i>
                    <h3><?= $avg_attendance ?>%</h3>
                    <p>Avg Attendance</p>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-container">
            <div class="filter-controls">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="courseSearch" class="form-control" placeholder="Search courses by name, code, or department...">
                </div>
                <select id="sortBy" class="form-select">
                    <option value="name">Sort by Name</option>
                    <option value="code">Sort by Code</option>
                    <option value="attendance">Sort by Attendance</option>
                    <option value="sessions">Sort by Sessions</option>
                </select>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="showStats" checked>
                    <label class="form-check-label" for="showStats">
                        Show Statistics
                    </label>
                </div>
            </div>
        </div>

        <!-- Courses Grid -->
        <div id="coursesContainer" class="row g-4">
            <?php if($courses): ?>
                <?php foreach($courses as $course): ?>
                    <div class="col-xl-6 col-lg-12 course-item" data-name="<?= htmlspecialchars(strtolower($course['name'])) ?>" data-code="<?= htmlspecialchars(strtolower($course['course_code'])) ?>" data-dept="<?= htmlspecialchars(strtolower($course['department_name'])) ?>">
                        <div class="course-card">
                            <div class="course-header">
                                <h5><?= htmlspecialchars($course['name']) ?></h5>
                                <div class="course-code"><?= htmlspecialchars($course['course_code']) ?></div>
                            </div>
                            <div class="course-body">
                                <div class="course-stats">
                                    <div class="stat-item">
                                        <span class="stat-value"><?= $course['enrolled_students'] ?? 0 ?></span>
                                        <span class="stat-label">Students</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?= $course['total_sessions'] ?? 0 ?></span>
                                        <span class="stat-label">Sessions</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?= number_format($course['attendance_rate'] ?? 0, 1) ?>%</span>
                                        <span class="stat-label">Attendance</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?= $course['credits'] ?? 0 ?></span>
                                        <span class="stat-label">Credits</span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-building me-1"></i><?= htmlspecialchars($course['department_name']) ?>
                                        <?php if($course['last_session_date']): ?>
                                            <br><i class="fas fa-clock me-1"></i>Last session: <?= date('M d, Y', strtotime($course['last_session_date'])) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>

                                <div class="course-actions">
                                    <button class="btn btn-primary btn-course" onclick="startSession(<?= $course['id'] ?>)">
                                        <i class="fas fa-play me-1"></i>Start Session
                                    </button>
                                    <button class="btn btn-success btn-course" onclick="viewReports(<?= $course['id'] ?>)">
                                        <i class="fas fa-chart-bar me-1"></i>Reports
                                    </button>
                                    <button class="btn btn-info btn-course" onclick="viewDetails(<?= $course['id'] ?>)">
                                        <i class="fas fa-eye me-1"></i>Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h4>No Courses Assigned</h4>
                        <p>You haven't been assigned any courses yet. Please contact your department administrator.</p>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Lecturer Panel
    </div>

    <!-- Course Details Modal -->
    <div class="modal fade" id="courseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-book me-2"></i>Course Details</h5>
                    <button type="button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="courseModalBody">
                    <!-- Course details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Sidebar toggle functionality
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('mobile-open');
    }

    // Course actions
    function startSession(courseId) {
        window.location.href = `attendance-session.php?course=${courseId}`;
    }

    function viewReports(courseId) {
        window.location.href = `attendance-reports.php?course=${courseId}`;
    }

    function viewDetails(courseId) {
        // Show loading state
        document.getElementById('courseModalBody').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading course details...</p>
            </div>
        `;

        // Load course details via AJAX
        fetch(`api/course-details-api.php?course_id=${courseId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const course = data.course;
                    const stats = data.stats;
                    const sessions = data.recent_sessions;
                    const students = data.students;

                    document.getElementById('courseModalBody').innerHTML = `
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-4">
                                    <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Course Information</h6>
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <strong>Course Name:</strong><br>
                                            ${course.name}
                                        </div>
                                        <div class="col-sm-6">
                                            <strong>Course Code:</strong><br>
                                            ${course.course_code}
                                        </div>
                                        <div class="col-sm-6">
                                            <strong>Department:</strong><br>
                                            ${course.department_name}
                                        </div>
                                        <div class="col-sm-6">
                                            <strong>Credits:</strong><br>
                                            ${course.credits}
                                        </div>
                                        <div class="col-sm-6">
                                            <strong>Duration:</strong><br>
                                            ${course.duration_hours} hours
                                        </div>
                                        <div class="col-sm-6">
                                            <strong>Last Session:</strong><br>
                                            ${stats.last_session || 'No sessions yet'}
                                        </div>
                                    </div>
                                    ${course.description ? `
                                        <div class="mt-3">
                                            <strong>Description:</strong><br>
                                            <p class="text-muted mb-0">${course.description}</p>
                                        </div>
                                    ` : ''}
                                </div>

                                ${sessions.length > 0 ? `
                                    <div class="mb-4">
                                        <h6 class="text-success mb-3"><i class="fas fa-history me-2"></i>Recent Sessions</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Attendance</th>
                                                        <th>Rate</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    ${sessions.map(session => `
                                                        <tr>
                                                            <td>${new Date(session.session_date).toLocaleDateString()}</td>
                                                            <td>${session.attendance_count}</td>
                                                            <td>
                                                                <span class="badge bg-${session.session_rate >= 80 ? 'success' : session.session_rate >= 60 ? 'warning' : 'danger'}">
                                                                    ${session.session_rate}%
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    `).join('')}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>

                            <div class="col-md-4">
                                <div class="mb-4">
                                    <h6 class="text-info mb-3"><i class="fas fa-chart-bar me-2"></i>Course Statistics</h6>
                                    <div class="d-grid gap-3">
                                        <div class="stat-item border rounded p-3 text-center">
                                            <div class="stat-value text-primary">${stats.students}</div>
                                            <div class="stat-label">Enrolled Students</div>
                                        </div>
                                        <div class="stat-item border rounded p-3 text-center">
                                            <div class="stat-value text-success">${stats.sessions}</div>
                                            <div class="stat-label">Total Sessions</div>
                                        </div>
                                        <div class="stat-item border rounded p-3 text-center">
                                            <div class="stat-value text-warning">${stats.attendance_rate}%</div>
                                            <div class="stat-label">Avg Attendance</div>
                                        </div>
                                        <div class="stat-item border rounded p-3 text-center">
                                            <div class="stat-value text-info">${stats.present_count}</div>
                                            <div class="stat-label">Total Present</div>
                                        </div>
                                    </div>
                                </div>

                                ${students.length > 0 ? `
                                    <div>
                                        <h6 class="text-secondary mb-3"><i class="fas fa-users me-2"></i>Top Performing Students</h6>
                                        <div style="max-height: 200px; overflow-y: auto;">
                                            ${students.slice(0, 5).map(student => `
                                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                                                    <div>
                                                        <small class="fw-bold">${student.first_name} ${student.last_name}</small>
                                                        <br>
                                                        <small class="text-muted">${student.total_sessions_attended} sessions</small>
                                                    </div>
                                                    <span class="badge bg-${student.attendance_rate >= 80 ? 'success' : student.attendance_rate >= 60 ? 'warning' : 'danger'}">
                                                        ${student.attendance_rate}%
                                                    </span>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                } else {
                    document.getElementById('courseModalBody').innerHTML = '<div class="alert alert-danger">Failed to load course details.</div>';
                }
            })
            .catch(error => {
                console.error('Error loading course details:', error);
                document.getElementById('courseModalBody').innerHTML = '<div class="alert alert-danger">Error loading course details. Please try again.</div>';
            });

        new bootstrap.Modal(document.getElementById('courseModal')).show();
    }

    // Search and filter functionality
    document.getElementById('courseSearch').addEventListener('input', filterCourses);
    document.getElementById('sortBy').addEventListener('change', sortCourses);
    document.getElementById('showStats').addEventListener('change', toggleStats);

    function filterCourses() {
        const query = document.getElementById('courseSearch').value.toLowerCase();
        const courseItems = document.querySelectorAll('.course-item');

        courseItems.forEach(item => {
            const name = item.dataset.name;
            const code = item.dataset.code;
            const dept = item.dataset.dept;

            const matches = name.includes(query) || code.includes(query) || dept.includes(query);
            item.style.display = matches ? '' : 'none';
        });
    }

    function sortCourses() {
        const sortBy = document.getElementById('sortBy').value;
        const container = document.getElementById('coursesContainer');
        const courseItems = Array.from(document.querySelectorAll('.course-item'));

        courseItems.sort((a, b) => {
            let aVal, bVal;

            switch(sortBy) {
                case 'code':
                    aVal = a.querySelector('.course-code').textContent.trim();
                    bVal = b.querySelector('.course-code').textContent.trim();
                    break;
                case 'attendance':
                    aVal = parseFloat(a.querySelector('.stat-value').textContent) || 0;
                    bVal = parseFloat(b.querySelector('.stat-value').textContent) || 0;
                    return bVal - aVal; // Higher attendance first
                case 'sessions':
                    // Get sessions count from the second stat item
                    aVal = parseInt(a.querySelectorAll('.stat-value')[1].textContent) || 0;
                    bVal = parseInt(b.querySelectorAll('.stat-value')[1].textContent) || 0;
                    return bVal - aVal; // Higher sessions first
                default: // name
                    aVal = a.dataset.name;
                    bVal = b.dataset.name;
            }

            return aVal.localeCompare(bVal);
        });

        // Re-append sorted items
        courseItems.forEach(item => container.appendChild(item));
    }

    function toggleStats() {
        const showStats = document.getElementById('showStats').checked;
        const statElements = document.querySelectorAll('.course-stats');

        statElements.forEach(stat => {
            stat.style.display = showStats ? '' : 'none';
        });
    }

    // Initialize sorting on page load
    document.addEventListener('DOMContentLoaded', function() {
        sortCourses();
    });
    </script>

</body>
</html>
