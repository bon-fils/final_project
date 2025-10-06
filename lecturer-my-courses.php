<?php
session_start();
require_once "config.php"; // contains $pdo = new PDO(...);
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

// Fetch courses for this lecturer
$sql = "
  SELECT
    c.id AS course_id,
    c.name AS course_name,
    c.course_code AS course_code,
    d.name AS department_name,
    c.credits,
    c.duration_hours
  FROM courses c
  INNER JOIN departments d ON c.department_id = d.id
  WHERE c.lecturer_id = ? AND c.status = 'active'
  ORDER BY c.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$lecturer_dept['lecturer_id']]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <i class="fas fa-clock me-1"></i>Live Updates
                </div>
                <div class="badge bg-success fs-6 px-3 py-2">
                    <i class="fas fa-chalkboard-teacher me-1"></i>Lecturer
                </div>
                <button class="btn btn-outline-primary btn-sm" onclick="location.reload()" title="Refresh Courses">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>
    </div>

  <input type="text" id="courseSearch" class="form-control search-bar" placeholder="Search courses by name or code...">

  <div class="row g-4" id="courseContainer">
    <?php if ($courses): ?>
      <?php foreach ($courses as $course): ?>
        <div class="col-md-6 course-card">
          <div class="card p-4">
            <h6 class="fw-semibold course-title">
              <?= htmlspecialchars($course['course_name']) ?>
            </h6>
            <p class="text-muted small mb-2 course-code">
              <?= htmlspecialchars($course['course_code']) ?>
            </p>
            <p class="small text-muted mb-2">
              Dept: <?= htmlspecialchars($course['department_name']) ?> |
              Credits: <?= htmlspecialchars($course['credits']) ?> |
              Duration: <?= htmlspecialchars($course['duration_hours']) ?> hrs
            </p>
            <div>
              <a href="attendance-session.php?course_id=<?= $course['course_id'] ?>" class="btn btn-sm btn-primary btn-action">
                <i class="fas fa-video me-1"></i> Start Session
              </a>
              <a href="attendance-reports.php?course_id=<?= $course['course_id'] ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-chart-bar me-1"></i> View Reports
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="text-center">No courses assigned to you yet.</p>
    <?php endif; ?>
  </div>
</div>

<!-- Footer -->
<div class="footer">
  &copy; 2025 Rwanda Polytechnic | Lecturer Panel
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Filter Script -->
<script>
// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('mobile-open');
}

const searchInput = document.getElementById('courseSearch');
const courseCards = document.querySelectorAll('.course-card');

searchInput.addEventListener('keyup', function () {
  const query = this.value.toLowerCase();
  courseCards.forEach(card => {
    const title = card.querySelector('.course-title').textContent.toLowerCase();
    const code = card.querySelector('.course-code').textContent.toLowerCase();
    if (title.includes(query) || code.includes(query)) {
      card.style.display = 'block';
    } else {
      card.style.display = 'none';
    }
  });
});
</script>

</body>
</html>
