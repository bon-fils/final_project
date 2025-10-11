<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'hod']);

// Ensure lecturer is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
  header("Location: index.php");
  exit;
}

// Get lecturer's department_id first - join on email instead of ID
$dept_stmt = $pdo->prepare("
    SELECT l.department_id, l.id as lecturer_id
    FROM lecturers l
    INNER JOIN users u ON l.email = u.email
    WHERE u.id = :user_id AND u.role = 'lecturer'
");
$dept_stmt->execute(['user_id' => $user_id]);
$lecturer_dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer_dept || !isset($lecturer_dept['department_id'])) {
    // Try to create a lecturer record if it doesn't exist
    $create_lecturer_stmt = $pdo->prepare("
        INSERT INTO lecturers (first_name, last_name, email, department_id, role, password)
        SELECT
            CASE WHEN username LIKE '% %' THEN SUBSTRING_INDEX(username, ' ', 1) ELSE username END as first_name,
            CASE WHEN username LIKE '% %' THEN SUBSTRING_INDEX(username, ' ', -1) ELSE '' END as last_name,
            email, 7, 'lecturer', '12345'
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
        header("Location: lecturer-dashboard.php?error=lecturer_setup_required");
        exit;
    }
}

// Store lecturer_id in session for other pages to use
$_SESSION['lecturer_id'] = $lecturer_dept['lecturer_id'];

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
    'lecturer_id' => $lecturer_dept['lecturer_id'],
    'department_id' => $lecturer_dept['department_id']
]);

// Fetch courses for this lecturer with basic data
$sql = "
  SELECT
    c.id AS course_id,
    c.name AS course_name,
    c.course_code AS course_code,
    c.description,
    d.name AS department_name,
    c.credits,
    c.duration_hours,
    c.created_at
  FROM courses c
  INNER JOIN departments d ON c.department_id = d.id
  WHERE c.lecturer_id = ? AND c.status = 'active'
  ORDER BY c.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$lecturer_dept['lecturer_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add basic statistics to each course
foreach ($courses as &$course) {
    $course_id = $course['course_id'];

    // Get student count
    $student_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_sessions WHERE course_id = ?");
    $student_stmt->execute([$course_id]);
    $student_result = $student_stmt->fetch(PDO::FETCH_ASSOC);
    $course['student_count'] = (int)($student_result['count'] ?? 0);

    // Get total sessions
    $attendance_stmt = $pdo->prepare("SELECT COUNT(*) as total_sessions FROM attendance_sessions WHERE course_id = ?");
    $attendance_stmt->execute([$course_id]);
    $attendance_result = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
    $course['total_sessions'] = (int)($attendance_result['total_sessions'] ?? 0);
    $course['avg_attendance_rate'] = 0; // Placeholder

    // Get today's sessions
    $today_stmt = $pdo->prepare("SELECT COUNT(*) as today_count FROM attendance_sessions WHERE course_id = ? AND DATE(start_time) = CURDATE()");
    $today_stmt->execute([$course_id]);
    $today_result = $today_stmt->fetch(PDO::FETCH_ASSOC);
    $course['today_sessions'] = (int)($today_result['today_count'] ?? 0);
}

// Get total statistics for the lecturer
$total_courses = count($courses);
$total_students = array_sum(array_column($courses, 'student_count'));
$avg_attendance = 0; // Placeholder
$today_sessions = array_sum(array_column($courses, 'today_sessions'));
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
  <link href="css/lecturer-dashboard.css" rel="stylesheet">

  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
      --card-shadow-hover: 0 20px 40px rgba(0,0,0,0.15);
    }

    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
    }

    .stats-card {
      background: white;
      border-radius: 15px;
      padding: 25px;
      box-shadow: var(--card-shadow);
      transition: all 0.3s ease;
      border: none;
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
      transform: translateY(-5px);
      box-shadow: var(--card-shadow-hover);
    }

    .stats-icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin-bottom: 15px;
    }

    .course-card {
      background: white;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: var(--card-shadow);
      transition: all 0.3s ease;
      border: none;
      margin-bottom: 20px;
    }

    .course-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--card-shadow-hover);
    }

    .course-header {
      background: var(--primary-gradient);
      color: white;
      padding: 20px;
      position: relative;
    }

    .course-status {
      position: absolute;
      top: 15px;
      right: 15px;
      padding: 5px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .course-body {
      padding: 25px;
    }

    .course-title {
      font-size: 1.3rem;
      font-weight: 600;
      margin-bottom: 5px;
      color: #2d3748;
    }

    .course-code {
      color: #718096;
      font-weight: 500;
      margin-bottom: 15px;
    }

    .course-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-bottom: 20px;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: 5px;
      color: #718096;
      font-size: 0.9rem;
    }

    .attendance-stats {
      background: #f8fafc;
      border-radius: 10px;
      padding: 15px;
      margin-bottom: 20px;
    }

    .stat-item {
      text-align: center;
    }

    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: #2d3748;
    }

    .stat-label {
      font-size: 0.8rem;
      color: #718096;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .action-buttons {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn-action {
      border-radius: 25px;
      padding: 8px 20px;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .search-container {
      background: white;
      border-radius: 15px;
      padding: 25px;
      box-shadow: var(--card-shadow);
      margin-bottom: 30px;
    }

    .search-input {
      border: 2px solid #e2e8f0;
      border-radius: 25px;
      padding: 12px 20px;
      font-size: 1rem;
      transition: all 0.3s ease;
    }

    .search-input:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      outline: none;
    }

    .filter-buttons {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 15px;
    }

    .filter-btn {
      border-radius: 20px;
      padding: 6px 15px;
      border: 2px solid #e2e8f0;
      background: white;
      color: #718096;
      transition: all 0.3s ease;
    }

    .filter-btn.active {
      background: var(--primary-gradient);
      border-color: transparent;
      color: white;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #718096;
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: 20px;
      opacity: 0.5;
    }

    @media (max-width: 768px) {
      .stats-card {
        margin-bottom: 20px;
      }

      .action-buttons {
        flex-direction: column;
      }

      .action-buttons .btn {
        width: 100%;
      }
    }

    .fade-in {
      animation: fadeIn 0.5s ease-in;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
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
            <a href="lecturer-my-courses.php" class="active">
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

<!-- Topbar -->
<div class="topbar d-flex justify-content-between align-items-center">
  <h5 class="m-0 fw-bold">My Courses</h5>
  <span>Lecturer Panel</span>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Page Header -->
    <div class="page-header text-center py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%); border-bottom: 2px solid #0ea5e9; margin-bottom: 30px;">
        <div class="d-flex align-items-center justify-content-center">
            <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" style="height: 60px; width: auto; margin-right: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
            <h1 style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0; font-weight: 700;">
                My Courses
            </h1>
        </div>
    </div>

    <!-- Statistics Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-book"></i>
                </div>
                <div class="stat-value text-primary"><?= $total_courses ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-success" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value text-success"><?= $total_students ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-warning" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-value text-warning"><?= $avg_attendance ?>%</div>
                <div class="stat-label">Avg Attendance</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-info" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); color: white;">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-value text-info"><?= $today_sessions ?></div>
                <div class="stat-label">Today's Sessions</div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-container">
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                <input type="text" id="courseSearch" class="form-control search-input" placeholder="🔍 Search courses by name, code, or description...">
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <button class="btn btn-outline-primary btn-sm" onclick="location.reload()" title="Refresh Courses">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">
                <i class="fas fa-th-large me-1"></i>All Courses (<?= count($courses) ?>)
            </button>
            <button class="filter-btn" data-filter="high-attendance">
                <i class="fas fa-trophy me-1"></i>High Attendance (>80%)
            </button>
            <button class="filter-btn" data-filter="today-sessions">
                <i class="fas fa-calendar-day me-1"></i>Today's Sessions
            </button>
            <button class="filter-btn" data-filter="recent">
                <i class="fas fa-clock me-1"></i>Recently Added
            </button>
        </div>
    </div>

    <!-- Courses Grid -->
    <div class="row g-4" id="courseContainer">
        <?php if ($courses): ?>
            <?php foreach ($courses as $index => $course): ?>
                <div class="col-lg-6 col-xl-4 course-card fade-in" data-course-id="<?= $course['course_id'] ?>"
                     data-attendance-rate="<?= $course['avg_attendance_rate'] ?>"
                     data-today-sessions="<?= $course['today_sessions'] ?>"
                     data-created-at="<?= strtotime($course['created_at']) ?>"
                     style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="course-card">
                        <div class="course-header">
                            <div class="course-status bg-success">Active</div>
                            <h5 class="course-title mb-1">
                                <?= htmlspecialchars($course['course_name']) ?>
                            </h5>
                            <div class="course-code">
                                <?= htmlspecialchars($course['course_code']) ?>
                            </div>
                        </div>

                        <div class="course-body">
                            <?php if ($course['description']): ?>
                                <p class="text-muted small mb-3">
                                    <?= htmlspecialchars(substr($course['description'], 0, 100)) ?>
                                    <?= strlen($course['description']) > 100 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>

                            <div class="course-meta">
                                <div class="meta-item">
                                    <i class="fas fa-building text-primary"></i>
                                    <span><?= htmlspecialchars($course['department_name']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-graduation-cap text-success"></i>
                                    <span><?= htmlspecialchars($course['credits']) ?> Credits</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock text-warning"></i>
                                    <span><?= htmlspecialchars($course['duration_hours']) ?> hrs</span>
                                </div>
                            </div>

                            <!-- Attendance Statistics -->
                            <div class="attendance-stats">
                                <div class="row g-3">
                                    <div class="col-4 stat-item">
                                        <div class="stat-value text-primary">
                                            <?= htmlspecialchars($course['student_count']) ?>
                                        </div>
                                        <div class="stat-label">Students</div>
                                    </div>
                                    <div class="col-4 stat-item">
                                        <div class="stat-value text-success">
                                            <?= htmlspecialchars($course['avg_attendance_rate']) ?>%
                                        </div>
                                        <div class="stat-label">Attendance</div>
                                    </div>
                                    <div class="col-4 stat-item">
                                        <div class="stat-value text-info">
                                            <?= htmlspecialchars($course['total_sessions']) ?>
                                        </div>
                                        <div class="stat-label">Sessions</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <a href="attendance-session.php?course_id=<?= $course['course_id'] ?>" class="btn btn-primary btn-action">
                                    <i class="fas fa-video me-1"></i> Start Session
                                </a>
                                <a href="attendance-reports.php?course_id=<?= $course['course_id'] ?>" class="btn btn-outline-secondary btn-action">
                                    <i class="fas fa-chart-bar me-1"></i> Reports
                                </a>
                                <button class="btn btn-outline-info btn-action" onclick="viewCourseDetails(<?= $course['course_id'] ?>)">
                                    <i class="fas fa-info-circle me-1"></i> Details
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-book text-muted"></i>
                    <h4>No Courses Assigned</h4>
                    <p>You haven't been assigned any courses yet. Please contact your department head or administrator.</p>
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh Page
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<div class="footer">
  &copy; 2025 Rwanda Polytechnic | Lecturer Panel
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Enhanced JavaScript -->
<script>
// Global variables
let allCourses = [];
let filteredCourses = [];

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCourses();
    setupEventListeners();
});

// Initialize courses data
function initializeCourses() {
    const courseCards = document.querySelectorAll('.course-card');
    allCourses = Array.from(courseCards);
    filteredCourses = [...allCourses];
}

// Setup event listeners
function setupEventListeners() {
    // Sidebar toggle
    const sidebarToggle = document.querySelector('.mobile-menu-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }

    // Search functionality
    const searchInput = document.getElementById('courseSearch');
    if (searchInput) {
        searchInput.addEventListener('input', handleSearch);
    }

    // Filter buttons
    const filterButtons = document.querySelectorAll('.filter-btn');
    filterButtons.forEach(button => {
        button.addEventListener('click', handleFilter);
    });
}

// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('mobile-open');
    }
}

// Enhanced search functionality
function handleSearch() {
    const query = this.value.toLowerCase().trim();
    const filterType = document.querySelector('.filter-btn.active').dataset.filter;

    filteredCourses = allCourses.filter(card => {
        const title = card.querySelector('.course-title').textContent.toLowerCase();
        const code = card.querySelector('.course-code').textContent.toLowerCase();
        const description = card.querySelector('p') ? card.querySelector('p').textContent.toLowerCase() : '';

        const matchesSearch = !query ||
            title.includes(query) ||
            code.includes(query) ||
            description.includes(query);

        const matchesFilter = checkFilterMatch(card, filterType);

        return matchesSearch && matchesFilter;
    });

    updateCourseDisplay();
}

// Filter functionality
function handleFilter() {
    // Update active filter button
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    this.classList.add('active');

    const filterType = this.dataset.filter;
    const searchQuery = document.getElementById('courseSearch').value.toLowerCase().trim();

    filteredCourses = allCourses.filter(card => {
        const matchesSearch = !searchQuery ||
            card.querySelector('.course-title').textContent.toLowerCase().includes(searchQuery) ||
            card.querySelector('.course-code').textContent.toLowerCase().includes(searchQuery);

        const matchesFilter = checkFilterMatch(card, filterType);

        return matchesSearch && matchesFilter;
    });

    updateCourseDisplay();
}

// Check if course matches filter criteria
function checkFilterMatch(card, filterType) {
    switch (filterType) {
        case 'all':
            return true;
        case 'high-attendance':
            const attendanceRate = parseFloat(card.dataset.attendanceRate) || 0;
            return attendanceRate > 80;
        case 'today-sessions':
            const todaySessions = parseInt(card.dataset.todaySessions) || 0;
            return todaySessions > 0;
        case 'recent':
            const createdAt = parseInt(card.dataset.createdAt) || 0;
            const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
            return createdAt > thirtyDaysAgo;
        default:
            return true;
    }
}

// Update course display with animations
function updateCourseDisplay() {
    const container = document.getElementById('courseContainer');

    // Hide all cards first
    allCourses.forEach(card => {
        card.style.display = 'none';
        card.classList.remove('fade-in');
    });

    // Show filtered cards with staggered animation
    filteredCourses.forEach((card, index) => {
        setTimeout(() => {
            card.style.display = 'block';
            card.classList.add('fade-in');
        }, index * 50);
    });

    // Update filter button counts
    updateFilterCounts();
}

// Update filter button counts
function updateFilterCounts() {
    const filters = {
        'all': allCourses.length,
        'high-attendance': allCourses.filter(card => (parseFloat(card.dataset.attendanceRate) || 0) > 80).length,
        'today-sessions': allCourses.filter(card => (parseInt(card.dataset.todaySessions) || 0) > 0).length,
        'recent': allCourses.filter(card => {
            const createdAt = parseInt(card.dataset.createdAt) || 0;
            const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
            return createdAt > thirtyDaysAgo;
        }).length
    };

    document.querySelectorAll('.filter-btn').forEach(button => {
        const filterType = button.dataset.filter;
        const count = filters[filterType] || 0;
        const icon = button.querySelector('i').outerHTML;
        const text = button.textContent.replace(/\(\d+\)$/, '').trim();
        button.innerHTML = `${icon} ${text} (${count})`;
    });
}

// View course details (placeholder for future implementation)
function viewCourseDetails(courseId) {
    // Show loading state
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';
    button.disabled = true;

    // Simulate API call
    setTimeout(() => {
        button.innerHTML = originalText;
        button.disabled = false;

        // Show notification
        showNotification('Course details feature coming soon!', 'info');
    }, 1000);
}

// Enhanced notification system
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' :
                      type === 'error' ? 'alert-danger' :
                      type === 'warning' ? 'alert-warning' : 'alert-info';

    const icon = type === 'success' ? 'fas fa-check-circle' :
                 type === 'error' ? 'fas fa-exclamation-triangle' :
                 type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;';
    alert.innerHTML = `
        <div class="d-flex align-items-start">
            <i class="${icon} me-2 mt-1"></i>
            <div class="flex-grow-1">
                <div class="fw-bold">${type.toUpperCase()}</div>
                <div>${message}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    document.body.appendChild(alert);

    // Add animation class
    setTimeout(() => alert.classList.add('show'), 10);

    // Auto remove after 4 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 300);
        }
    }, 4000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl + F to focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        const searchInput = document.getElementById('courseSearch');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    // Escape to clear search
    if (e.key === 'Escape') {
        const searchInput = document.getElementById('courseSearch');
        if (searchInput && document.activeElement === searchInput) {
            searchInput.value = '';
            searchInput.blur();
            handleSearch.call(searchInput);
        }
    }
});

// Auto-refresh functionality (optional)
let autoRefreshInterval = null;

function startAutoRefresh() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    autoRefreshInterval = setInterval(() => {
        // Only refresh if page is visible and no active interactions
        if (!document.hidden && !document.querySelector(':focus')) {
            updateFilterCounts();
        }
    }, 30000); // Refresh every 30 seconds
}

function stopAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
    }
}

// Start auto-refresh when page becomes visible
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

// Initialize auto-refresh
startAutoRefresh();
</script>

</body>
</html>