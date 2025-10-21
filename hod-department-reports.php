<?php
require_once "config.php";
session_start();
require_once "session_check.php";

// Ensure user is logged in and is HoD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: login_new.php");
    exit;
}

// Get HoD information and verify department assignment
$user_id = $_SESSION['user_id'];
$department_name = null;
$department_id = null;
try {
    // First, check if the user has a lecturer record
    $lecturer_stmt = $pdo->prepare("SELECT id, gender, dob, id_number, department_id, education_level FROM lecturers WHERE user_id = ?");
    $lecturer_stmt->execute([$user_id]);
    $lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        // HOD user doesn't have a lecturer record
        header("Location: login_new.php?error=not_assigned");
        exit;
    } else {
        $lecturer_id = $lecturer['id'];

        // Try multiple approaches to find department assignment (handles both correct and legacy hod_id references)
        $dept_result = null;

        // Approach 1: Correct way - hod_id points to lecturers.id
        $stmt = $pdo->prepare("
            SELECT d.name as department_name, d.id as department_id, 'direct' as match_type
            FROM departments d
            WHERE d.hod_id = ? AND d.hod_id IS NOT NULL
        ");
        $stmt->execute([$lecturer_id]);
        $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Approach 2: Legacy way - hod_id might point to users.id (incorrect but may exist in data)
        if (!$dept_result) {
            $stmt = $pdo->prepare("
                SELECT d.name as department_name, d.id as department_id, 'legacy' as match_type
                FROM departments d
                WHERE d.hod_id = ? AND d.hod_id IS NOT NULL
            ");
            $stmt->execute([$user_id]);
            $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);

            // If found via legacy method, log it for potential data fix
            if ($dept_result) {
                error_log("HOD Department Reports: Found department assignment via legacy hod_id match (user_id instead of lecturer_id) for user $user_id");
            }
        }

        // Approach 3: Check if lecturer's department_id matches any department's hod_id
        if (!$dept_result && $lecturer['department_id']) {
            $stmt = $pdo->prepare("
                SELECT d.name as department_name, d.id as department_id, 'department_match' as match_type
                FROM departments d
                WHERE d.id = ? AND d.hod_id IS NOT NULL
            ");
            $stmt->execute([$lecturer['department_id']]);
            $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$dept_result) {
            // User is HOD but not assigned to any department
            header("Location: login_new.php?error=not_assigned");
            exit;
        } else {
            $department_name = $dept_result['department_name'];
            $department_id = $dept_result['department_id'];

            // Create department array for compatibility
            $department = [
                'id' => $department_id,
                'name' => $department_name
            ];

            // Get user information
            $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user['department_name'] = $department_name;
            $user['department_id'] = $department_id;

            // Log match type for debugging
            error_log("HOD Department Reports: Department assignment found via {$dept_result['match_type']} for user $user_id, department $department_name");
        }
    }
} catch (PDOException $e) {
    error_log("Database error in hod-department-reports.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later. Error: " . $e->getMessage();
}

if (!$department) {
    // Debug information
    error_log("Department not found for HoD ID: $hod_id");
    error_log("Session data: " . print_r($_SESSION, true));

    // Check if departments table exists and has data
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM departments");
    $checkStmt->execute();
    $deptCount = $checkStmt->fetch(PDO::FETCH_ASSOC);

    error_log("Total departments in database: " . $deptCount['count']);

    if ($deptCount['count'] > 0) {
        $deptListStmt = $pdo->prepare("SELECT id, name, hod_id FROM departments");
        $deptListStmt->execute();
        $departments = $deptListStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Available departments: " . print_r($departments, true));
    }

    echo "<div style='padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin: 20px;'>";
    echo "<h3 style='color: #dc3545; margin-bottom: 15px;'>Department Not Found</h3>";
    echo "<p>It appears you are not properly assigned as Head of Department for any department.</p>";
    echo "<p><strong>Debug Information:</strong></p>";
    echo "<ul>";
    echo "<li>Your User ID: <strong>$hod_id</strong></li>";
    echo "<li>Your Role: <strong>" . ($_SESSION['role'] ?? 'Not set') . "</strong></li>";
    echo "<li>Total Departments: <strong>" . $deptCount['count'] . "</strong></li>";
    echo "</ul>";

    if ($deptCount['count'] > 0) {
        echo "<p><strong>Available Departments:</strong></p>";
        echo "<ul>";
        foreach ($departments as $dept) {
            echo "<li>{$dept['name']} (ID: {$dept['id']}, HoD ID: {$dept['hod_id']})</li>";
        }
        echo "</ul>";
        echo "<p><strong>Solution:</strong> Please contact your administrator to assign you as HoD for a department.</p>";
    } else {
        echo "<p><strong>Solution:</strong> No departments exist in the system. Please contact your administrator to create departments first.</p>";
    }

    echo "<hr style='margin: 20px 0;'>";
    echo "<p><a href='hod-dashboard.php' style='color: #0066cc; text-decoration: none;'><i class='fas fa-arrow-left me-2'></i>Return to Dashboard</a></p>";
    echo "</div>";

    exit;
}

// Fetch options for this department
try {
    $optionStmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = :dept_id ORDER BY name");
    $optionStmt->execute(['dept_id' => $department['id']]);
    $options = $optionStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Options query failed: " . $e->getMessage());
    $options = [];
}

// Fetch courses for this department with enhanced information
try {
    $courseStmt = $pdo->prepare("
        SELECT DISTINCT
            c.id,
            c.course_code,
            c.name as course_name,
            c.description,
            c.credits,
            c.duration_hours,
            c.status,
            c.lecturer_id,
            c.option_id,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM attendance_sessions s
                    WHERE s.course_id = c.id
                    AND EXISTS (
                        SELECT 1 FROM students st
                        WHERE st.department_id = :dept_id
                    )
                ) THEN 'Active'
                ELSE 'Inactive'
            END as course_activity_status
        FROM courses c
        WHERE c.department_id = :dept_id
        ORDER BY c.course_code, c.name
    ");
    $courseStmt->execute(['dept_id' => $department['id']]);
    $coursesAll = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Fallback to simpler query if the enhanced one fails
    error_log("Enhanced course query failed, using fallback: " . $e->getMessage());
    $courseStmt = $pdo->prepare("
        SELECT DISTINCT
            c.id,
            c.course_code,
            c.name as course_name,
            c.credits,
            'Active' as course_activity_status
        FROM courses c
        WHERE c.department_id = :dept_id
        ORDER BY c.course_code, c.name
    ");
    $courseStmt->execute(['dept_id' => $department['id']]);
    $coursesAll = $courseStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle AJAX filter request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $option_id = $_POST['option'] ?? '';
    $course_id = $_POST['course'] ?? '';
    $year_level = $_POST['year'] ?? '';
    $start_date = $_POST['startDate'] ?? '';
    $end_date = $_POST['endDate'] ?? '';

    if ($option_id && $course_id && $year_level && $start_date && $end_date) {
        $attStmt = $pdo->prepare("
            SELECT DATE(ar.recorded_at) AS date,
                   COUNT(CASE WHEN ar.status=1 THEN 1 END) AS present,
                   COUNT(CASE WHEN ar.status=0 THEN 1 END) AS absent
            FROM attendance_records ar
            INNER JOIN students s ON ar.student_id = s.id
            INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
            INNER JOIN courses c ON sess.course_id = c.id
            WHERE c.department_id = :dept_id
              AND s.option_id = :option_id
              AND s.year_level = :year_level
              AND sess.course_id = :course_id
              AND DATE(ar.recorded_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(ar.recorded_at)
            ORDER BY DATE(ar.recorded_at) ASC
        ");
        $attStmt->execute([
            'dept_id' => $department['id'],
            'option_id' => $option_id,
            'year_level' => $year_level,
            'course_id' => $course_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        $attendanceData = $attStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    header('Content-Type: application/json');
    echo json_encode($attendanceData ?? []);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Department Reports | HoD | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  :root {
    --primary-color: #003366;
    --secondary-color: #0059b3;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --danger-color: #dc3545;
    --light-bg: #f8f9fa;
    --card-shadow: 0 4px 12px rgba(0, 51, 102, 0.1);
    --card-shadow-hover: 0 8px 25px rgba(0, 51, 102, 0.15);
  }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #0066cc 0%, #004b99 50%, #003366 100%);
    margin: 0;
    min-height: 100vh;
  }

  .topbar {
    margin-left: 280px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
    padding: 20px 30px; border-bottom: 1px solid rgba(0,51,102,0.1);
    position: sticky; top: 0; z-index: 900; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border-radius: 0 0 15px 15px;
  }

  .main-content {
    margin-left: 280px; padding: 30px;
    min-height: calc(100vh - 80px);
  }

  .card {
    background: #fff; border-radius: 15px; padding: 30px;
    box-shadow: var(--card-shadow); margin-bottom: 30px;
    transition: all 0.3s ease; border: 1px solid rgba(0,51,102,0.05);
  }

  .card:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-shadow-hover);
  }

  .card h5, .card h6 {
    font-weight: 600; margin-bottom: 25px; color: var(--primary-color);
    border-bottom: 3px solid var(--secondary-color); padding-bottom: 10px;
  }

  .btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border: none; border-radius: 8px; font-weight: 500;
    transition: all 0.3s ease;
  }

  .btn-primary:hover {
    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
    transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,51,102,0.3);
  }

  .btn-info {
    background: linear-gradient(135deg, var(--info-color), #20a8d8);
    border: none; border-radius: 8px;
  }

  .btn-success {
    background: linear-gradient(135deg, var(--success-color), #20c997);
    border: none; border-radius: 8px;
  }

  .btn-warning {
    background: linear-gradient(135deg, var(--warning-color), #e0a800);
    border: none; border-radius: 8px;
  }

  .form-control, .form-select {
    border-radius: 8px; border: 1px solid rgba(0,51,102,0.2);
    transition: all 0.3s ease;
  }

  .form-control:focus, .form-select:focus {
    border-color: var(--secondary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
  }

  .table {
    background: #fff; border-radius: 10px; overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  }

  .table thead th {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white; font-weight: 600; border: none; padding: 15px;
    font-size: 0.9rem;
  }

  .table tbody td {
    padding: 12px 15px; vertical-align: middle;
    border-bottom: 1px solid rgba(0,51,102,0.1);
  }

  .table tbody tr:hover {
    background-color: rgba(0,102,204,0.02);
  }

  .footer {
    text-align: center; margin-left: 280px; padding: 20px;
    font-size: 0.9rem; color: #666; background: rgba(255,255,255,0.9);
    backdrop-filter: blur(10px); border-top: 1px solid rgba(0,51,102,0.1);
  }

  .stats-card {
    background: linear-gradient(135deg, var(--info-color), #20a8d8);
    color: white; border-radius: 15px; padding: 20px;
    text-align: center; box-shadow: var(--card-shadow);
  }

  .stats-card h3 {
    font-size: 2rem; font-weight: 700; margin-bottom: 5px;
  }

  .stats-card p {
    margin: 0; opacity: 0.9; font-size: 0.9rem;
  }

  .chart-container {
    position: relative; height: 400px; margin: 20px 0;
  }

  .filter-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px; padding: 25px; margin-bottom: 30px;
    border: 1px solid rgba(0,51,102,0.05);
  }

  .alert {
    border-radius: 10px; border: none;
  }

  .loading {
    display: inline-block; width: 20px; height: 20px;
    border: 3px solid rgba(255,255,255,.3); border-radius: 50%;
    border-top-color: #fff; animation: spin 1s ease-in-out infinite;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  @media (max-width: 768px) {
    .topbar, .main-content, .footer {
      margin-left: 0 !important; width: 100%;
    }
    .topbar { 
      padding: 15px 20px;
      border-radius: 0;
    }
    .main-content {
      padding: 20px 15px;
    }
    .table-responsive { font-size: 0.9rem; }
    .chart-container { height: 300px; }
    .stats-card {
      margin-bottom: 15px;
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
    background: var(--primary-color); border-radius: 4px;
  }

  ::-webkit-scrollbar-thumb:hover {
    background: var(--secondary-color);
  }
</style>
</head>
<body>
<!-- Enhanced HOD Sidebar -->
<?php include 'includes/hod_sidebar.php'; ?>

<div class="topbar">
  <div class="d-flex justify-content-between align-items-center">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="hod-dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
          <li class="breadcrumb-item active" aria-current="page">Department Reports</li>
        </ol>
      </nav>
      <h5 class="m-0 fw-bold">Department Reports & Analytics</h5>
      <small class="text-muted"><?= htmlspecialchars($department_name) ?> Department</small>
    </div>
    <div class="d-flex align-items-center">
      <span class="me-3">
        <i class="fas fa-user-tie me-1"></i>
        <?= htmlspecialchars($user['name'] ?? 'HOD') ?>
      </span>
      <div class="dropdown">
        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
          <i class="fas fa-cog me-1"></i> Actions
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="hod-dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
          <li><a class="dropdown-item" href="hod-students.php"><i class="fas fa-users me-2"></i>Students</a></li>
          <li><a class="dropdown-item" href="hod-courses.php"><i class="fas fa-book me-2"></i>Courses</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="main-content">
  <!-- Error Message Display -->
  <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <?= htmlspecialchars($error_message) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-2">
        <i class="fas fa-chart-bar text-primary me-2"></i>
        Department Analytics Dashboard
      </h2>
      <p class="text-muted mb-0">Comprehensive reports and insights for <?= htmlspecialchars($department_name) ?> Department</p>
    </div>
    <div>
      <button class="btn btn-success me-2" onclick="refreshAllData()">
        <i class="fas fa-sync-alt me-1"></i> Refresh Data
      </button>
      <button class="btn btn-primary" onclick="scheduleReport()">
        <i class="fas fa-calendar-plus me-1"></i> Schedule Report
      </button>
    </div>
  </div>

  <!-- Department Overview Cards -->
  <div class="row g-4 mb-4" id="overviewCards">
    <div class="col-md-3">
      <div class="stats-card">
        <i class="fas fa-users fa-2x mb-2"></i>
        <h3 id="totalStudents">0</h3>
        <p>Total Students</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stats-card">
        <i class="fas fa-book fa-2x mb-2"></i>
        <h3 id="totalCourses">0</h3>
        <p>Department Courses</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stats-card">
        <i class="fas fa-graduation-cap fa-2x mb-2"></i>
        <h3 id="activeCourses">0</h3>
        <p>Active Courses</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stats-card">
        <i class="fas fa-chart-line fa-2x mb-2"></i>
        <h3 id="avgAttendance">0%</h3>
        <p>Avg Attendance</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stats-card">
        <i class="fas fa-calendar-check fa-2x mb-2"></i>
        <h3 id="totalSessions">0</h3>
        <p>Total Sessions</p>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stats-card">
        <i class="fas fa-clock fa-2x mb-2"></i>
        <h3 id="totalCredits">0</h3>
        <p>Total Credits</p>
      </div>
    </div>
  </div>

  <!-- Course Quick Filters -->
  <div class="card mb-4">
    <div class="card-body">
      <h6 class="mb-3">
        <i class="fas fa-filter me-2"></i>Quick Course Filters
      </h6>
      <div class="row g-2">
        <div class="col-md-3">
          <select id="activityFilter" class="form-select form-select-sm">
            <option value="">All Courses</option>
            <option value="Active">Active Courses</option>
            <option value="Inactive">Inactive Courses</option>
          </select>
        </div>
        <div class="col-md-3">
          <select id="creditsFilter" class="form-select form-select-sm">
            <option value="">All Credits</option>
            <option value="3">3 Credits</option>
            <option value="4">4 Credits</option>
            <option value="other">Other Credits</option>
          </select>
        </div>
        <div class="col-md-3">
          <input type="text" id="courseSearch" class="form-control form-control-sm" placeholder="Search courses...">
        </div>
        <div class="col-md-3">
          <button class="btn btn-outline-secondary btn-sm w-100" onclick="resetFilters()">
            <i class="fas fa-undo me-1"></i>Reset Filters
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Enhanced Filter Section -->
  <div class="filter-section">
    <h5 class="mb-4">
      <i class="fas fa-filter me-2"></i>Filter Reports
    </h5>
    <form id="filterForm" class="row g-3 align-items-end">
      <div class="col-md-2">
        <label for="optionSelect" class="form-label">
          <i class="fas fa-list me-1"></i>Option
        </label>
        <select id="optionSelect" class="form-select" required>
          <option value="" selected disabled>Choose option...</option>
          <?php foreach($options as $opt): ?>
          <option value="<?= $opt['id'] ?>"><?= htmlspecialchars($opt['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label for="yearSelect" class="form-label">
          <i class="fas fa-graduation-cap me-1"></i>Year/Level
        </label>
        <select id="yearSelect" class="form-select" required>
          <option value="" selected disabled>Select year...</option>
          <option value="Year 1">Year 1</option>
          <option value="Year 2">Year 2</option>
          <option value="Year 3">Year 3</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="courseSelect" class="form-label">
          <i class="fas fa-book me-1"></i>Course
        </label>
        <select id="courseSelect" class="form-select" required>
          <option value="" selected disabled>Choose course...</option>
          <?php foreach($coursesAll as $c): ?>
          <option value="<?= $c['id'] ?>"
                  data-code="<?= htmlspecialchars($c['course_code'] ?? 'N/A') ?>"
                  data-credits="<?= $c['credits'] ?? '' ?>"
                  data-duration=""
                  data-lecturer="Not Assigned"
                  data-status="<?= $c['course_activity_status'] ?? 'Active' ?>">
            [<?= htmlspecialchars($c['course_code'] ?? 'N/A') ?>] <?= htmlspecialchars($c['course_name']) ?>
            <?php if (!empty($c['credits'])): ?>
              (<?= $c['credits'] ?> credits)
            <?php endif; ?>
            <?php if (($c['course_activity_status'] ?? 'Active') == 'Inactive'): ?>
              <span class="badge bg-warning text-dark ms-1">Inactive</span>
            <?php endif; ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div id="courseInfo" class="mt-1" style="font-size: 0.85rem; color: #666; display: none;">
          <small id="courseDetails"></small>
        </div>
      </div>
      <div class="col-md-2">
        <label for="startDate" class="form-label">
          <i class="fas fa-calendar-start me-1"></i>Start Date
        </label>
        <input type="date" id="startDate" class="form-control" required>
      </div>
      <div class="col-md-2">
        <label for="endDate" class="form-label">
          <i class="fas fa-calendar-end me-1"></i>End Date
        </label>
        <input type="date" id="endDate" class="form-control" required>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">
          <i class="fas fa-search me-2"></i>Generate Report
        </button>
      </div>
    </form>
  </div>

  <!-- Report Type Tabs -->
  <div class="card">
    <div class="card-header">
      <ul class="nav nav-tabs card-header-tabs" id="reportTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
            <i class="fas fa-chart-bar me-2"></i>Overview
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="detailed-tab" data-bs-toggle="tab" data-bs-target="#detailed" type="button" role="tab">
            <i class="fas fa-table me-2"></i>Detailed Report
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="trends-tab" data-bs-toggle="tab" data-bs-target="#trends" type="button" role="tab">
            <i class="fas fa-chart-line me-2"></i>Trends
          </button>
        </li>
      </ul>
    </div>

    <div class="card-body">
      <div class="tab-content" id="reportTabsContent">
        <!-- Overview Tab -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel">
          <div class="chart-container">
            <canvas id="attendanceChart"></canvas>
          </div>
        </div>

        <!-- Detailed Report Tab -->
        <div class="tab-pane fade" id="detailed" role="tabpanel">
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th><i class="fas fa-calendar me-1"></i>Date</th>
                  <th><i class="fas fa-list me-1"></i>Option</th>
                  <th><i class="fas fa-book me-1"></i>Course</th>
                  <th><i class="fas fa-code me-1"></i>Code</th>
                  <th><i class="fas fa-graduation-cap me-1"></i>Year</th>
                  <th><i class="fas fa-check me-1"></i>Present</th>
                  <th><i class="fas fa-times me-1"></i>Absent</th>
                  <th><i class="fas fa-percentage me-1"></i>Rate</th>
                  <th><i class="fas fa-info-circle me-1"></i>Status</th>
                </tr>
              </thead>
              <tbody id="attendanceTableBody"></tbody>
            </table>
          </div>
        </div>

        <!-- Trends Tab -->
        <div class="tab-pane fade" id="trends" role="tabpanel">
          <div class="chart-container">
            <canvas id="trendsChart"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Export Options -->
  <div class="card">
    <div class="card-body">
      <h6 class="mb-3">
        <i class="fas fa-download me-2"></i>Export Options
      </h6>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-primary" onclick="exportReport('pdf')">
          <i class="fas fa-file-pdf me-2"></i>Export PDF
        </button>
        <button class="btn btn-outline-success" onclick="exportReport('excel')">
          <i class="fas fa-file-excel me-2"></i>Export Excel
        </button>
        <button class="btn btn-outline-info" onclick="exportReport('csv')">
          <i class="fas fa-file-csv me-2"></i>Export CSV
        </button>
        <button class="btn btn-outline-secondary" onclick="printReport()">
          <i class="fas fa-print me-2"></i>Print Report
        </button>
      </div>
    </div>
  </div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | HoD Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Initialize variables
const today = new Date().toISOString().split("T")[0];
document.getElementById("startDate").setAttribute("max", today);
document.getElementById("endDate").setAttribute("max", today);

let attendanceChart, trendsChart;
let currentData = [];

// Set default date range (last 30 days)
const thirtyDaysAgo = new Date();
thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
document.getElementById("startDate").value = thirtyDaysAgo.toISOString().split("T")[0];
document.getElementById("endDate").value = today;

// Load overview statistics
function loadOverviewStats() {
    fetch('api/department-reports-api.php?action=get_overview_stats', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ department_id: <?= $department['id'] ?> })
    })
    .then(res => {
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            document.getElementById('totalStudents').textContent = data.statistics.total_students || '0';
            document.getElementById('totalCourses').textContent = data.statistics.total_courses || '0';
            document.getElementById('activeCourses').textContent = data.statistics.active_courses || '0';
            document.getElementById('avgAttendance').textContent = data.statistics.avg_attendance_rate || '0';
            document.getElementById('totalSessions').textContent = data.statistics.total_sessions || '0';
            document.getElementById('totalCredits').textContent = data.statistics.total_credits || '0';

            // Add visual feedback for cached data
            if (data.cached) {
                console.log('Overview stats loaded from cache');
            } else if (data.fallback) {
                console.log('Overview stats loaded via fallback query');
            }
        } else {
            console.error('Failed to load overview stats:', data.message);
            if (data.debug) {
                console.error('Debug info:', data.debug);
            }
            // Set default values instead of showing error
            document.getElementById('totalStudents').textContent = '0';
            document.getElementById('totalCourses').textContent = '0';
            document.getElementById('activeCourses').textContent = '0';
            document.getElementById('avgAttendance').textContent = '0';
            document.getElementById('totalSessions').textContent = '0';
            document.getElementById('totalCredits').textContent = '0';
        }
    })
    .catch(error => {
        console.error('Error loading overview stats:', error);
        console.error('Error details:', {
            message: error.message,
            stack: error.stack,
            department_id: <?= $department['id'] ?>
        });
        // Set default values instead of showing error
        document.getElementById('totalStudents').textContent = '0';
        document.getElementById('totalCourses').textContent = '0';
        document.getElementById('activeCourses').textContent = '0';
        document.getElementById('avgAttendance').textContent = '0';
        document.getElementById('totalSessions').textContent = '0';
        document.getElementById('totalCredits').textContent = '0';
    });
}

// Enhanced form submission with loading states
document.getElementById('filterForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const optionId = document.getElementById('optionSelect').value;
    const courseId = document.getElementById('courseSelect').value;
    const yearLevel = document.getElementById('yearSelect').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if (!optionId || !courseId || !yearLevel || !startDate || !endDate) {
        showAlert('Please fill in all required fields.', 'warning');
        return;
    }

    // Show loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
    submitBtn.disabled = true;

    // Prepare form data
    const formData = new FormData();
    formData.append('option', optionId);
    formData.append('course', courseId);
    formData.append('year', yearLevel);
    formData.append('startDate', startDate);
    formData.append('endDate', endDate);

    fetch('hod-department-reports.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        currentData = data;
        renderCharts(data);
        renderTable(data);
        showAlert('Report generated successfully!', 'success');
    })
    .catch(error => {
        console.error('Error generating report:', error);
        showAlert('Error generating report. Please try again.', 'danger');
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Render charts with enhanced styling
function renderCharts(data) {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const labels = data.map(d => new Date(d.date).toLocaleDateString());
    const presentData = data.map(d => d.present);
    const absentData = data.map(d => d.absent);

    // Destroy existing charts
    if (attendanceChart) attendanceChart.destroy();
    if (trendsChart) trendsChart.destroy();

    // Create main attendance chart
    attendanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Present',
                    data: presentData,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Absent',
                    data: absentData,
                    backgroundColor: 'rgba(220, 53, 69, 0.8)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Daily Attendance Overview',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Date'
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Students'
                    }
                }
            }
        }
    });

    // Create trends chart
    const trendsCtx = document.getElementById('trendsChart').getContext('2d');
    const attendanceRates = data.map(d => {
        const total = d.present + d.absent;
        return total === 0 ? 0 : Math.round((d.present / total) * 100);
    });

    trendsChart = new Chart(trendsCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Attendance Rate (%)',
                data: attendanceRates,
                borderColor: 'rgba(23, 162, 184, 1)',
                backgroundColor: 'rgba(23, 162, 184, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: 'Attendance Trends Over Time',
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Attendance Rate (%)'
                    }
                }
            }
        }
    });
}

// Enhanced table rendering
function renderTable(data) {
    const tbody = document.getElementById('attendanceTableBody');
    tbody.innerHTML = '';

    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-4">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No attendance data found for the selected criteria.</p>
                </td>
            </tr>
        `;
        return;
    }

    data.forEach((d, index) => {
        const total = d.present + d.absent;
        const percent = total === 0 ? 0 : Math.round((d.present / total) * 100);
        const statusClass = percent >= 80 ? 'success' : percent >= 60 ? 'warning' : 'danger';
        const statusIcon = percent >= 80 ? 'check-circle' : percent >= 60 ? 'exclamation-triangle' : 'times-circle';

        tbody.insertAdjacentHTML('beforeend', `
            <tr class="animate-in" style="animation-delay: ${index * 0.1}s">
                <td>
                    <i class="fas fa-calendar text-primary me-2"></i>
                    ${new Date(d.date).toLocaleDateString()}
                </td>
                <td>
                    <span class="badge bg-info">
                        <i class="fas fa-list me-1"></i>
                        ${document.getElementById('optionSelect').selectedOptions[0].text}
                    </span>
                </td>
                <td>
                    <span class="badge bg-primary">
                        <i class="fas fa-book me-1"></i>
                        ${document.getElementById('courseSelect').selectedOptions[0].text.split('[')[0].trim()}
                    </span>
                </td>
                <td>
                    <span class="badge bg-info">
                        <i class="fas fa-code me-1"></i>
                        ${document.getElementById('courseSelect').selectedOptions[0].getAttribute('data-code')}
                    </span>
                </td>
                <td>
                    <span class="badge bg-secondary">
                        <i class="fas fa-graduation-cap me-1"></i>
                        ${document.getElementById('yearSelect').value}
                    </span>
                </td>
                <td>
                    <span class="badge bg-success">
                        <i class="fas fa-check me-1"></i>
                        ${d.present}
                    </span>
                </td>
                <td>
                    <span class="badge bg-danger">
                        <i class="fas fa-times me-1"></i>
                        ${d.absent}
                    </span>
                </td>
                <td>
                    <div class="progress" style="width: 60px;">
                        <div class="progress-bar bg-${statusClass}" role="progressbar"
                             style="width: ${percent}%" aria-valuenow="${percent}"
                             aria-valuemin="0" aria-valuemax="100">
                            ${percent}%
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-${statusClass}">
                        <i class="fas fa-${statusIcon} me-1"></i>
                        ${percent >= 80 ? 'Excellent' : percent >= 60 ? 'Good' : 'Poor'}
                    </span>
                </td>
            </tr>
        `);
    });
}

// Export functionality
function exportReport(format) {
    if (currentData.length === 0) {
        showAlert('No data to export. Please generate a report first.', 'warning');
        return;
    }

    const data = {
        format: format,
        reportData: currentData,
        filters: {
            option: document.getElementById('optionSelect').selectedOptions[0]?.text || '',
            course: document.getElementById('courseSelect').selectedOptions[0]?.text || '',
            year: document.getElementById('yearSelect').value,
            startDate: document.getElementById('startDate').value,
            endDate: document.getElementById('endDate').value
        }
    };

    // Create and trigger download
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `attendance_report_${new Date().toISOString().split('T')[0]}.${format}`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showAlert(`Report exported as ${format.toUpperCase()} successfully!`, 'success');
}

// Print functionality
function printReport() {
    if (currentData.length === 0) {
        showAlert('No data to print. Please generate a report first.', 'warning');
        return;
    }

    window.print();
}

// Utility function for alerts
function showAlert(message, type) {
    const alertContainer = document.createElement('div');
    alertContainer.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertContainer.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertContainer.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    document.body.appendChild(alertContainer);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertContainer.parentNode) {
            alertContainer.remove();
        }
    }, 5000);
}

// Course filtering functions
function filterCourses() {
    const activityFilter = document.getElementById('activityFilter').value;
    const creditsFilter = document.getElementById('creditsFilter').value;
    const searchTerm = document.getElementById('courseSearch').value.toLowerCase();
    const courseSelect = document.getElementById('courseSelect');

    // Store original options
    if (!courseSelect.dataset.originalOptions) {
        courseSelect.dataset.originalOptions = courseSelect.innerHTML;
    }

    // Reset to original options
    courseSelect.innerHTML = courseSelect.dataset.originalOptions;

    // Add default option back
    const defaultOption = courseSelect.querySelector('option[value=""]');
    const options = Array.from(courseSelect.querySelectorAll('option:not([value=""])'));

    // Filter options
    const filteredOptions = options.filter(option => {
        const status = option.getAttribute('data-status');
        const credits = option.getAttribute('data-credits');
        const text = option.textContent.toLowerCase();

        // Activity filter
        if (activityFilter && status !== activityFilter) {
            return false;
        }

        // Credits filter
        if (creditsFilter) {
            if (creditsFilter === 'other') {
                if (credits === '3' || credits === '4') return false;
            } else if (credits !== creditsFilter) {
                return false;
            }
        }

        // Search filter
        if (searchTerm && !text.includes(searchTerm)) {
            return false;
        }

        return true;
    });

    // Clear and repopulate
    courseSelect.innerHTML = '';
    if (defaultOption) courseSelect.appendChild(defaultOption.cloneNode(true));

    filteredOptions.forEach(option => {
        courseSelect.appendChild(option);
    });
}

function resetFilters() {
    document.getElementById('activityFilter').value = '';
    document.getElementById('creditsFilter').value = '';
    document.getElementById('courseSearch').value = '';

    const courseSelect = document.getElementById('courseSelect');
    if (courseSelect.dataset.originalOptions) {
        courseSelect.innerHTML = courseSelect.dataset.originalOptions;
    }
}

// Additional utility functions
function refreshAllData() {
    const refreshBtn = document.querySelector('[onclick="refreshAllData()"]');
    const originalText = refreshBtn.innerHTML;
    refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Refreshing...';
    refreshBtn.disabled = true;
    
    // Clear any cached data
    localStorage.removeItem('hod_reports_cache');
    
    // Reload overview stats
    loadOverviewStats();
    
    // If there's current report data, regenerate it
    if (currentData.length > 0) {
        document.getElementById('filterForm').dispatchEvent(new Event('submit'));
    }
    
    setTimeout(() => {
        refreshBtn.innerHTML = originalText;
        refreshBtn.disabled = false;
        showAlert('Data refreshed successfully!', 'success');
    }, 2000);
}

function scheduleReport() {
    // Create modal for scheduling reports
    const modalHtml = `
        <div class="modal fade" id="scheduleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Schedule Automated Report</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="scheduleForm">
                            <div class="mb-3">
                                <label class="form-label">Report Frequency</label>
                                <select class="form-select" name="frequency" required>
                                    <option value="">Select frequency...</option>
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Recipients</label>
                                <input type="email" class="form-control" name="recipients" 
                                       placeholder="Enter email addresses (comma separated)" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Report Format</label>
                                <select class="form-select" name="format" required>
                                    <option value="pdf">PDF Report</option>
                                    <option value="excel">Excel Spreadsheet</option>
                                    <option value="both">Both PDF and Excel</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="submitSchedule()">Schedule Report</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('scheduleModal');
    if (existingModal) existingModal.remove();
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    new bootstrap.Modal(document.getElementById('scheduleModal')).show();
}

function submitSchedule() {
    const form = document.getElementById('scheduleForm');
    const formData = new FormData(form);
    
    // Here you would typically send to a backend API
    // For now, just show success message
    showAlert('Report scheduled successfully! You will receive automated reports as configured.', 'success');
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    loadOverviewStats();

    // Add course info display functionality
    document.getElementById('courseSelect').addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        const courseInfo = document.getElementById('courseInfo');
        const courseDetails = document.getElementById('courseDetails');

        if (selectedOption && selectedOption.value) {
            const credits = selectedOption.getAttribute('data-credits');
            const duration = selectedOption.getAttribute('data-duration');
            const lecturer = selectedOption.getAttribute('data-lecturer');
            const status = selectedOption.getAttribute('data-status');

            let details = [];
            if (credits) details.push(`Credits: ${credits}`);
            if (duration) details.push(`Duration: ${duration}h`);
            if (lecturer && lecturer !== 'Not Assigned') details.push(`Lecturer: ${lecturer}`);
            if (status) details.push(`Status: ${status}`);

            courseDetails.textContent = details.join(' • ');
            courseInfo.style.display = 'block';
        } else {
            courseInfo.style.display = 'none';
        }
    });

    // Add filter event listeners
    document.getElementById('activityFilter').addEventListener('change', filterCourses);
    document.getElementById('creditsFilter').addEventListener('change', filterCourses);
    document.getElementById('courseSearch').addEventListener('input', filterCourses);

    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
        @keyframes animateIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in {
            animation: animateIn 0.5s ease-out forwards;
        }
    `;
    document.head.appendChild(style);
});
</script>
</body>
</html>
