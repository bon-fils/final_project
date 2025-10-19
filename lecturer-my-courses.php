<?php
/**
 * Lecturer My Courses - Backend Implementation
 * Displays courses assigned to lecturer with real database data
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_once "dashboard_utils.php";
require_role(['lecturer', 'hod']);

// Get user information
$user_id = $_SESSION['user_id'] ?? null;
$lecturer_id = $_SESSION['lecturer_id'] ?? null;

// Debug session data
error_log("lecturer-my-courses.php - Session Debug:");
error_log("user_id: " . ($user_id ?? 'NULL'));
error_log("lecturer_id: " . ($lecturer_id ?? 'NULL'));
error_log("username: " . ($_SESSION['username'] ?? 'NULL'));
error_log("role: " . ($_SESSION['role'] ?? 'NULL'));

if (!$user_id || !$lecturer_id) {
    error_log("Session expired or invalid - redirecting to login");
    header("Location: login.php?error=session_expired");
    exit;
}

// Get lecturer information
try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.email, u.first_name, u.last_name,
               l.department_id, d.name as department_name
        FROM users u
        INNER JOIN lecturers l ON u.id = l.user_id
        LEFT JOIN departments d ON l.department_id = d.id
        WHERE u.id = :user_id AND u.role = 'lecturer'
    ");
    $stmt->execute(['user_id' => $user_id]);
    $lecturer_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer_info) {
        header("Location: login.php?error=lecturer_not_found");
        exit;
    }

    $lecturer_name = trim(($lecturer_info['first_name'] ?? '') . ' ' . ($lecturer_info['last_name'] ?? ''));
    if (empty($lecturer_name)) {
        $lecturer_name = $lecturer_info['username'];
    }

} catch (PDOException $e) {
    error_log("Lecturer info error: " . $e->getMessage());
    header("Location: login.php?error=database");
    exit;
}

// Get courses assigned to this lecturer
try {
    $courses = [];

    error_log("Querying courses for lecturer_id: " . $lecturer_id);

    // Get courses where lecturer_id matches the logged-in lecturer
    $stmt = $pdo->prepare("
        SELECT
            c.id as course_id,
            c.course_code,
            c.name as course_name,
            c.description,
            d.name as department_name,
            c.credits,
            c.duration_hours,
            c.created_at,
            COUNT(DISTINCT st.id) as student_count,
            COUNT(DISTINCT s.id) as total_sessions,
            ROUND(
                CASE
                    WHEN COUNT(DISTINCT st.id) > 0 AND COUNT(DISTINCT s.id) > 0
                    THEN (COUNT(ar.id) * 100.0) / (COUNT(DISTINCT st.id) * COUNT(DISTINCT s.id))
                    ELSE 0
                END, 1
            ) as avg_attendance_rate,
            COUNT(DISTINCT CASE WHEN DATE(s.session_date) = CURDATE() THEN s.id END) as today_sessions
        FROM courses c
        INNER JOIN departments d ON c.department_id = d.id
        LEFT JOIN options o ON c.option_id = o.id
        LEFT JOIN students st ON o.id = st.option_id
        LEFT JOIN attendance_sessions s ON c.id = s.course_id
        LEFT JOIN attendance_records ar ON s.id = ar.session_id
        WHERE c.lecturer_id = :lecturer_id
        GROUP BY c.id, c.course_code, c.name, c.description, d.name, c.credits, c.duration_hours, c.created_at
        ORDER BY c.created_at DESC
    ");
    $stmt->execute(['lecturer_id' => $lecturer_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Found " . count($courses) . " courses for lecturer_id: " . $lecturer_id);
    if (!empty($courses)) {
        foreach ($courses as $course) {
            error_log("Course: " . $course['course_code'] . " - " . $course['course_name']);
        }
    }

    // Calculate total statistics
    $total_courses = count($courses);
    $total_students = array_sum(array_column($courses, 'student_count'));
    $avg_attendance = $total_courses > 0 ? round(array_sum(array_column($courses, 'avg_attendance_rate')) / $total_courses, 1) : 0;
    $today_sessions = array_sum(array_column($courses, 'today_sessions'));

    error_log("Statistics: total_courses=$total_courses, total_students=$total_students, avg_attendance=$avg_attendance, today_sessions=$today_sessions");

} catch (PDOException $e) {
    error_log("Courses query error: " . $e->getMessage());
    $courses = [];
    $total_courses = 0;
    $total_students = 0;
    $avg_attendance = 0;
    $today_sessions = 0;
}

// Handle AJAX requests for real-time updates
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    try {
        // Refresh course data - get courses assigned to this lecturer
        $stmt = $pdo->prepare("
            SELECT
                c.id as course_id,
                COUNT(DISTINCT st.id) as student_count,
                COUNT(DISTINCT s.id) as total_sessions,
                ROUND(
                    CASE
                        WHEN COUNT(DISTINCT st.id) > 0 AND COUNT(DISTINCT s.id) > 0
                        THEN (COUNT(ar.id) * 100.0) / (COUNT(DISTINCT st.id) * COUNT(DISTINCT s.id))
                        ELSE 0
                    END, 1
                ) as avg_attendance_rate,
                COUNT(DISTINCT CASE WHEN DATE(s.session_date) = CURDATE() THEN s.id END) as today_sessions
            FROM courses c
            LEFT JOIN options o ON c.option_id = o.id
            LEFT JOIN students st ON o.id = st.option_id
            LEFT JOIN attendance_sessions s ON c.id = s.course_id
            LEFT JOIN attendance_records ar ON s.id = ar.session_id
            WHERE c.lecturer_id = :lecturer_id
            GROUP BY c.id
        ");
        $stmt->execute(['lecturer_id' => $lecturer_id]);
        $updated_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_courses' => count($updated_stats),
                'total_students' => array_sum(array_column($updated_stats, 'student_count')),
                'avg_attendance' => count($updated_stats) > 0 ? round(array_sum(array_column($updated_stats, 'avg_attendance_rate')) / count($updated_stats), 1) : 0,
                'today_sessions' => array_sum(array_column($updated_stats, 'today_sessions')),
                'courses' => $courses
            ],
            'timestamp' => time()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to refresh course data'
        ]);
    }
    exit;
}
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
  <!-- Custom Styles -->
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

      /* Sidebar styles */
      .sidebar {
          position: fixed;
          top: 0;
          left: 0;
          height: 100vh;
          width: 250px;
          background: var(--primary-gradient);
          color: white;
          padding: 20px 0;
          z-index: 100;
          box-shadow: 2px 0 10px rgba(0,0,0,0.1);
      }

      .sidebar .sidebar-header {
          padding: 0 20px 20px;
          border-bottom: 1px solid rgba(255,255,255,0.2);
          margin-bottom: 20px;
      }

      .sidebar .sidebar-header h4 {
          margin: 0;
          font-weight: 600;
          font-size: 18px;
      }

      .sidebar .sidebar-header .subtitle {
          font-size: 12px;
          opacity: 0.8;
          margin-top: 4px;
      }

      .sidebar a {
          color: white;
          text-decoration: none;
          padding: 12px 20px;
          display: block;
          transition: all 0.3s ease;
          border-left: 3px solid transparent;
      }

      .sidebar a:hover, .sidebar a.active {
          background: rgba(255, 255, 255, 0.1);
          border-left-color: rgba(255, 255, 255, 0.5);
      }

      .sidebar a i {
          margin-right: 10px;
          width: 16px;
          text-align: center;
      }

      .main-content {
          margin-left: 250px;
          padding: 20px;
          min-height: 100vh;
      }

      .topbar {
          background: white;
          padding: 20px;
          border-radius: 10px;
          box-shadow: 0 4px 6px rgba(0,0,0,0.1);
          margin-bottom: 20px;
          display: flex;
          justify-content: space-between;
          align-items: center;
      }

      .page-header {
          text-align: center;
          padding: 30px 0;
          margin-bottom: 30px;
      }

      .footer {
          text-align: center;
          padding: 20px;
          color: #718096;
          font-size: 0.9rem;
      }
  </style>

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
    <!-- System Status Notice -->
    <div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
        <i class="fas fa-info-circle me-2"></i><strong>Live System:</strong> Connected to RP Attendance Database.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Include Lecturer Sidebar -->
    <?php include 'includes/lecturer-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h1 class="page-title">My Courses</h1>
                <div class="system-title">Rwanda Polytechnic Attendance System</div>
                <div class="user-info mt-1">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($lecturer_name); ?> |
                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($lecturer_info['department_name'] ?? 'Not Assigned'); ?>
                    </small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success">Live System</span>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshCourses()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
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
                <i class="fas fa-th-large me-1"></i>All Courses (<?= $total_courses ?>)
            </button>
            <button class="filter-btn" data-filter="assigned">
                <i class="fas fa-user-check me-1"></i>Assigned to Me
            </button>
            <button class="filter-btn" data-filter="department">
                <i class="fas fa-building me-1"></i>Department Courses
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
        <?php if (!empty($courses) && is_array($courses)): ?>
            <?php foreach ($courses as $index => $course): ?>
                <div class="col-lg-6 col-xl-4 course-card fade-in" data-course-id="<?= htmlspecialchars($course['course_id']) ?>"
                      data-attendance-rate="<?= htmlspecialchars($course['avg_attendance_rate']) ?>"
                      data-today-sessions="<?= htmlspecialchars($course['today_sessions']) ?>"
                      data-created-at="<?= htmlspecialchars(strtotime($course['created_at'])) ?>"
                      style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="course-card">
                        <div class="course-header">
                            <div class="course-status bg-success">
                                Department
                            </div>
                            <h5 class="course-title mb-1">
                                <?= htmlspecialchars($course['course_name']) ?>
                            </h5>
                            <div class="course-code">
                                <?= htmlspecialchars($course['course_code']) ?>
                            </div>
                        </div>

                        <div class="course-body">
                            <?php if (!empty($course['description'])): ?>
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
                                <a href="attendance-session.php?course_id=<?= htmlspecialchars($course['course_id']) ?>" class="btn btn-primary btn-action">
                                    <i class="fas fa-video me-1"></i> Start Session
                                </a>
                                <a href="lecturer-attendance-reports.php?course_id=<?= htmlspecialchars($course['course_id']) ?>" class="btn btn-outline-secondary btn-action">
                                    <i class="fas fa-chart-bar me-1"></i> Reports
                                </a>
                                <button class="btn btn-outline-info btn-action" onclick="viewCourseDetails(<?= htmlspecialchars($course['course_id']) ?>)">
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
                    <h4>No Courses Available</h4>
                    <p>No courses are currently assigned to your department. Please contact your department head or administrator.</p>
                    <button class="btn btn-primary" onclick="refreshCourses()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh Data
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

    <!-- Footer -->
    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Lecturer Panel - Live System
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Enhanced JavaScript for Live System -->
    <script>
    // Global variables
    let allCourses = [];
    let filteredCourses = [];

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeCourses();
        setupEventListeners();
        showWelcomeMessage();
        setupAutoRefresh();
    });

    // Show welcome message
    function showWelcomeMessage() {
        setTimeout(() => {
            showNotification('Welcome to your Course Dashboard! Real-time data from RP Attendance System.', 'success');
        }, 1000);
    }

    // Initialize courses data
    function initializeCourses() {
        const courseCards = document.querySelectorAll('.course-card');
        allCourses = Array.from(courseCards);
        filteredCourses = [...allCourses];
        updateFilterCounts();
    }

    // Setup event listeners
    function setupEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('courseSearch');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(handleSearch, 300));
        }

        // Filter buttons
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', handleFilter);
        });
    }

    // Setup auto-refresh functionality
    function setupAutoRefresh() {
        // Auto-refresh every 5 minutes
        setInterval(refreshCourses, 300000);

        // Handle visibility change
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshCourses();
            }
        });
    }

    // Refresh courses data from server
    function refreshCourses() {
        const refreshBtn = document.querySelector('button[onclick="refreshCourses()"]');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
        }

        fetch('lecturer-my-courses.php?ajax=1', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update statistics
                updateStatistics(data.data);

                // Update course cards if needed
                if (data.data.courses) {
                    updateCourseCards(data.data.courses);
                }

                showNotification('Course data refreshed successfully!', 'success');
            } else {
                showNotification('Failed to refresh course data.', 'error');
            }
        })
        .catch(error => {
            console.error('Refresh error:', error);
            showNotification('Error refreshing course data.', 'error');
        })
        .finally(() => {
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Refresh';
            }
        });
    }

    // Update statistics display
    function updateStatistics(data) {
        const statElements = {
            'totalCourses': data.total_courses,
            'totalStudents': data.total_students,
            'avgAttendance': data.avg_attendance + '%',
            'todaySessions': data.today_sessions
        };

        Object.keys(statElements).forEach(key => {
            const element = document.getElementById(key);
            if (element) {
                element.textContent = statElements[key];
            }
        });
    }

    // Update course cards (for future dynamic updates)
    function updateCourseCards(courses) {
        // This could be enhanced to dynamically update course cards
        // For now, just refresh the page data
        initializeCourses();
    }

    // Enhanced search functionality with debouncing
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
            case 'assigned':
                return card.querySelector('.course-status').textContent.trim() === 'Assigned';
            case 'department':
                return card.querySelector('.course-status').textContent.trim() === 'Department';
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

        // Update filter counts after filtering
        updateFilterCounts();
    }

    // Update filter button counts
    function updateFilterCounts() {
        const filters = {
            'all': allCourses.length,
            'assigned': allCourses.filter(card => card.querySelector('.course-status').textContent.trim() === 'Assigned').length,
            'department': allCourses.filter(card => card.querySelector('.course-status').textContent.trim() === 'Department').length,
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

    // View course details
    function viewCourseDetails(courseId) {
        showNotification(`Loading detailed information for course ${courseId}...`, 'info');
        // Could implement modal or redirect to course details page
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

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 4000);
    }

    // Debounce utility function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
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

    console.log('🎓 Lecturer Courses Live System loaded successfully!');
    console.log('💡 Real-time data from RP Attendance Database');
    console.log('🔍 Use Ctrl+F to quickly focus the search bar');
    console.log('🔄 Auto-refresh enabled - data updates every 5 minutes');
    </script>

</body>
</html>