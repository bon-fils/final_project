<?php
require_once "config.php"; // PDO connection - must be first
session_start();
require_once "session_check.php";

// Ensure user is logged in and is HoD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: login.php");
    exit;
}

// Get HoD information
$user_id = $_SESSION['user_id'];
try {
    // First get the department ID from the departments table where hod_id matches the user_id
    $stmt = $pdo->prepare("SELECT d.name as department_name FROM departments d WHERE d.hod_id = ?");
    $stmt->execute([$user_id]);
    $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dept_result) {
        $department_name = $dept_result['department_name'];
    } else {
        // If no department found, try to get user info without department
        $stmt = $pdo->prepare("SELECT username as name, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user['department_name'] = 'Not Assigned';
        $department_name = 'Not Assigned';
    }

    // Get user information
    $stmt = $pdo->prepare("SELECT username as name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user['department_name'] = $department_name;

    if (!$user) {
        error_log("HoD user not found: User ID $user_id");
        header("Location: login.php");
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in hod-dashboard.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later.";
}

// Get dynamic statistics
$stats = [];
try {
    // Get department ID for this HoD
    $hod_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE hod_id = ?");
    $stmt->execute([$hod_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($department) {
        $department_id = $department['id'];

        // Calculate attendance percentage (last 30 days)
        $stmt = $pdo->prepare("
            SELECT
                COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                COUNT(*) as total_count
            FROM attendance_records ar
            JOIN students s ON ar.student_id = s.id
            WHERE s.department_id = ?
            AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$department_id]);
        $attendance_result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($attendance_result['total_count'] > 0) {
            $stats['avg_attendance'] = round(($attendance_result['present_count'] / $attendance_result['total_count']) * 100, 1);
        } else {
            $stats['avg_attendance'] = 0;
        }

        // Pending leave requests for department
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as pending_count
            FROM leave_requests lr
            JOIN students s ON lr.student_id = s.id
            WHERE s.department_id = ?
            AND lr.status = 'pending'
        ");
        $stmt->execute([$department_id]);
        $pending_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['pending_leaves'] = $pending_result['pending_count'];

        // Total students in department
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_students
            FROM students
            WHERE department_id = ?
        ");
        $stmt->execute([$department_id]);
        $students_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_students'] = $students_result['total_students'];

        // Total lecturers in department (from lecturers table)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_lecturers
            FROM lecturers
            WHERE department_id = ?
        ");
        $stmt->execute([$department_id]);
        $lecturers_result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_lecturers'] = $lecturers_result['total_lecturers'];

    } else {
        // No department assigned to this HoD
        $stats = [
            'avg_attendance' => 0,
            'pending_leaves' => 0,
            'total_students' => 0,
            'total_lecturers' => 0
        ];
    }

} catch (PDOException $e) {
    error_log("Database error fetching statistics in hod-dashboard.php: " . $e->getMessage());
    $stats = [
        'avg_attendance' => 0,
        'pending_leaves' => 0,
        'total_students' => 0,
        'total_lecturers' => 0
    ];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>HoD Dashboard | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #0066cc, #003366);
      margin: 0;
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 250px;
      height: 100vh;
      background-color: #003366;
      color: white;
      padding-top: 20px;
    }

    .sidebar a {
      display: block;
      padding: 12px 20px;
      color: #fff;
      text-decoration: none;
    }

    .sidebar a:hover {
      background-color: #0059b3;
    }

    .topbar {
      margin-left: 250px;
      background-color: #fff;
      padding: 10px 30px;
      border-bottom: 1px solid #ddd;
    }

    .main-content {
      margin-left: 250px;
      padding: 30px;
    }

    .card {
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .btn-primary {
      background-color: #0066cc;
      border: none;
    }

    .btn-primary:hover {
      background-color: #004b99;
    }

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
    }

    /* Accessibility improvements */
    .card {
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Loading animation */
    .loading {
      opacity: 0.6;
      pointer-events: none;
    }

    /* Statistics cards styling */
    .stats-icon {
      opacity: 0.3;
      transition: opacity 0.3s ease;
    }

    .card:hover .stats-icon {
      opacity: 0.6;
    }

    /* Improved responsive design */
    @media (max-width: 1200px) {
      .col-lg-3 {
        flex: 0 0 auto;
        width: 50%;
      }
    }

    @media (max-width: 768px) {
      .sidebar,
      .topbar,
      .main-content,
      .footer {
        margin-left: 0 !important;
        width: 100%;
      }

      .sidebar {
        display: none;
      }

      .topbar {
        padding: 10px 15px;
      }

      .main-content {
        padding: 20px 15px;
      }

      .display-4 {
        font-size: 2rem;
      }

      .dropdown {
        margin-top: 10px;
      }
    }

    @media (max-width: 576px) {
      .col-lg-3 {
        width: 100%;
      }

      .display-4 {
        font-size: 1.8rem;
      }
    }

    /* Focus styles for accessibility */
    .btn:focus,
    .form-select:focus,
    .dropdown-toggle:focus {
      box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
      outline: none;
    }

    /* High contrast mode support */
    @media (prefers-contrast: high) {
      .card {
        border: 2px solid #000;
      }

      .sidebar {
        background-color: #000;
        color: #fff;
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4>👔 Head of Department</h4>
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="hod-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="hod-department-reports.php"><i class="fas fa-chart-bar me-2"></i> Department Reports</a>
    <a href="hod-leave-management.php"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave Requests</a>
    <a href="#" onclick="showCourseAssignmentModal()"><i class="fas fa-book me-2"></i> Assign Courses</a>
    <a href="hod-manage-lecturers.php"><i class="fas fa-user-plus me-2"></i> Manage Lecturers</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1">
          <li class="breadcrumb-item"><a href="hod-dashboard.php"><i class="fas fa-home me-1"></i>Home</a></li>
          <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
        </ol>
      </nav>
      <h5 class="m-0 fw-bold">Head of Department Dashboard</h5>
      <small class="text-muted">Welcome back, <?php echo htmlspecialchars($user['name']); ?> | <?php echo htmlspecialchars($user['department_name']); ?></small>
      <?php if (isset($error_message)): ?>
        <div class="alert alert-warning alert-dismissible fade show mt-2 py-2" role="alert">
          <i class="fas fa-exclamation-triangle me-1"></i>
          <?php echo htmlspecialchars($error_message); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>
    </div>
    <div class="d-flex align-items-center">
      <span class="me-3">HoD Panel</span>
      <div class="dropdown">
        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($user['name']); ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="row g-4">

      <!-- Attendance Summary -->
      <div class="col-md-6 col-lg-3">
        <div class="card p-4">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="mb-3">Department Attendance</h6>
              <div class="display-4 fw-bold text-primary"><?php echo $stats['avg_attendance']; ?>%</div>
              <small class="text-muted">Average attendance (30 days)</small>
            </div>
            <i class="fas fa-chart-line fa-2x text-primary opacity-50"></i>
          </div>
        </div>
      </div>

      <!-- Pending Leave Requests -->
      <div class="col-md-6 col-lg-3">
        <div class="card p-4">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="mb-3">Pending Leave Requests</h6>
              <div class="display-4 fw-bold text-warning"><?php echo $stats['pending_leaves']; ?></div>
              <small class="text-muted">Requests awaiting approval</small>
            </div>
            <i class="fas fa-envelope-open-text fa-2x text-warning opacity-50"></i>
          </div>
        </div>
      </div>

      <!-- Total Students -->
      <div class="col-md-6 col-lg-3">
        <div class="card p-4">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="mb-3">Total Students</h6>
              <div class="display-4 fw-bold text-info"><?php echo $stats['total_students']; ?></div>
              <small class="text-muted">Students in department</small>
            </div>
            <i class="fas fa-users fa-2x text-info opacity-50"></i>
          </div>
        </div>
      </div>

      <!-- Total Lecturers -->
      <div class="col-md-6 col-lg-3">
        <div class="card p-4">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h6 class="mb-3">Total Lecturers</h6>
              <div class="display-4 fw-bold text-success"><?php echo $stats['total_lecturers']; ?></div>
              <small class="text-muted">Lecturers in department</small>
            </div>
            <i class="fas fa-chalkboard-teacher fa-2x text-success opacity-50"></i>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="col-md-6 col-lg-3">
        <div class="card p-4 d-flex flex-column justify-content-center align-items-start">
          <h6 class="mb-3"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
          <a href="hod-department-reports.php" class="btn btn-primary mb-2 w-100">
            <i class="fas fa-chart-bar me-2"></i> View Reports
          </a>
          <a href="hod-leave-management.php" class="btn btn-primary mb-2 w-100">
            <i class="fas fa-envelope-open-text me-2"></i> Manage Leave
          </a>
          <a href="hod-manage-lecturers.php" class="btn btn-primary mb-2 w-100">
            <i class="fas fa-user-plus me-2"></i> Manage Lecturers
          </a>
          <button type="button" class="btn btn-primary mb-2 w-100" onclick="showCourseAssignmentModal()">
            <i class="fas fa-book me-2"></i> Assign Courses
          </button>
          <a href="manage-departments.php" class="btn btn-outline-primary w-100">
            <i class="fas fa-cog me-2"></i> Department Settings
          </a>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="col-md-6 col-lg-3">
        <div class="card p-4">
          <h6 class="mb-3"><i class="fas fa-clock me-2"></i>Recent Activity</h6>
          <div class="activity-list">
            <?php
            try {
                // Get department ID first
                $dept_stmt = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
                $dept_stmt->execute([$hod_id]);
                $department = $dept_stmt->fetch(PDO::FETCH_ASSOC);

                if ($department) {
                    // Get recent leave requests for this department
                    $stmt = $pdo->prepare("
                        SELECT lr.requested_at, s.first_name, s.last_name, lr.status
                        FROM leave_requests lr
                        JOIN students s ON lr.student_id = s.id
                        WHERE s.department_id = ?
                        ORDER BY lr.requested_at DESC
                        LIMIT 3
                    ");
                    $stmt->execute([$department['id']]);
                    $recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if ($recent_activities) {
                        foreach ($recent_activities as $activity) {
                            $status_class = $activity['status'] === 'pending' ? 'warning' : 'success';
                            echo "<div class='d-flex align-items-center mb-2'>
                                    <i class='fas fa-user-graduate me-2 text-info'></i>
                                    <div class='flex-grow-1'>
                                      <small class='text-muted'>Leave request from {$activity['first_name']} {$activity['last_name']}</small>
                                      <br>
                                      <small class='badge bg-{$status_class}'>{$activity['status']}</small>
                                    </div>
                                  </div>";
                        }
                    } else {
                        echo "<p class='text-muted mb-0'><i class='fas fa-info-circle me-1'></i> No recent activity</p>";
                    }
                } else {
                    echo "<p class='text-muted mb-0'><i class='fas fa-info-circle me-1'></i> No department assigned</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='text-muted mb-0'><i class='fas fa-exclamation-triangle me-1'></i> Unable to load activity</p>";
            }
            ?>
          </div>
        </div>
      </div>

      <!-- Course Assignment Section -->
      <div class="col-12">
        <div class="card p-4 mt-3">
          <h6 class="mb-3"><i class="fas fa-book me-2"></i>Course Assignment Overview</h6>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Lecturer</th>
                  <th>Email</th>
                  <th>Assigned Courses</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <div id="assignmentTableContent">
                  <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading course assignments...</p>
                  </div>
                </div>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="col-12">
        <div class="card p-4 mt-3">
          <h6 class="mb-3"><i class="fas fa-filter me-2"></i>Filter Records</h6>
          <form class="row g-3" method="POST" action="hod-dashboard.php">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <div class="col-md-3">
              <label class="form-label">User Type</label>
              <select class="form-select" id="userType" name="user_type" onchange="updateEducationLevel()">
                <option value="">All Types</option>
                <option value="student" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'student') ? 'selected' : ''; ?>>Student</option>
                <option value="lecturer" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'lecturer') ? 'selected' : ''; ?>>Lecturer</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Department</label>
              <select class="form-select" name="department">
                <option value="">All Departments</option>
                <?php
                $dept_stmt = $pdo->prepare("SELECT id, name FROM departments ORDER BY name");
                $dept_stmt->execute();
                while ($dept = $dept_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $selected = (isset($_POST['department']) && $_POST['department'] == $dept['id']) ? 'selected' : '';
                    echo "<option value=\"{$dept['id']}\" $selected>" . htmlspecialchars($dept['name']) . "</option>";
                }
                ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Gender</label>
              <select class="form-select" name="gender">
                <option value="">All</option>
                <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" id="eduLabel">Education Level</label>
              <select class="form-select" name="education_level" id="eduLevel">
                <option value="">Select</option>
                <!-- Options will be filled by JS -->
              </select>
            </div>
            <div class="col-12 mt-3">
              <button type="submit" class="btn btn-primary me-2">
                <i class="fas fa-search me-2"></i> Apply Filter
              </button>
              <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                <i class="fas fa-times me-2"></i> Clear Filters
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

  <!-- Course Assignment Modal -->
  <div class="modal fade" id="courseAssignmentModal" tabindex="-1" aria-labelledby="courseAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="courseAssignmentModalLabel"><i class="fas fa-book me-2"></i>Assign Courses</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="assignmentContent">
            <div class="text-center">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <p class="mt-2">Loading course assignment...</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" onclick="saveCourseAssignments()">
            <i class="fas fa-save me-2"></i>Save Assignments
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | HoD Panel
  </div>

  <!-- JavaScript -->
  <script>
    // Global variables for course assignment
    let currentLecturerId = null;
    let currentLecturerName = '';
    let availableCourses = [];
    let assignedCourses = [];

    function showCourseAssignmentModal() {
      const modal = new bootstrap.Modal(document.getElementById('courseAssignmentModal'));
      modal.show();
      loadCourseAssignmentData();
    }

    function assignCourses(lecturerId, lecturerName) {
      currentLecturerId = lecturerId;
      currentLecturerName = lecturerName;
      showCourseAssignmentModal();
    }

    function loadCourseAssignmentData() {
      const content = document.getElementById('assignmentContent');
      content.innerHTML = `
        <div class="text-center">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2">Loading course assignment data...</p>
        </div>
      `;

      // Load available courses and current assignments
      Promise.all([
        fetch('api/assign-courses-api.php?action=get_courses'),
        fetch('api/assign-courses-api.php?action=get_assigned_courses', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ lecturer_id: currentLecturerId })
        })
      ])
      .then(responses => Promise.all(responses.map(r => r.json())))
      .then(data => {
        availableCourses = data[0];
        assignedCourses = data[1];
        renderCourseAssignmentInterface();
      })
      .catch(error => {
        console.error('Error loading course data:', error);
        content.innerHTML = `
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Error loading course assignment data. Please try again.
          </div>
        `;
      });
    }

    function renderCourseAssignmentInterface() {
      const content = document.getElementById('assignmentContent');
      const modalTitle = document.getElementById('courseAssignmentModalLabel');
      modalTitle.innerHTML = `<i class="fas fa-book me-2"></i>Assign Courses to ${currentLecturerName}`;

      content.innerHTML = `
        <div class="row">
          <div class="col-md-6">
            <h6><i class="fas fa-list me-2"></i>Available Courses</h6>
            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
              ${availableCourses.map(course => `
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" value="${course.id}"
                         id="course_${course.id}" ${assignedCourses.some(ac => ac.id === course.id) ? 'checked' : ''}>
                  <label class="form-check-label" for="course_${course.id}">
                    ${course.course_name} (${course.course_code})
                  </label>
                </div>
              `).join('')}
            </div>
          </div>
          <div class="col-md-6">
            <h6><i class="fas fa-check-circle me-2"></i>Currently Assigned</h6>
            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
              <div id="assignedCoursesList">
                ${assignedCourses.map(course => `
                  <div class="badge bg-primary me-1 mb-1 p-2">
                    ${course.course_name} (${course.course_code})
                    <button type="button" class="btn-close btn-close-white ms-1" onclick="removeCourseAssignment(${course.id})"></button>
                  </div>
                `).join('')}
              </div>
              <div id="noAssignments" class="${assignedCourses.length === 0 ? '' : 'd-none'} text-muted">
                No courses currently assigned
              </div>
            </div>
          </div>
        </div>
      `;
    }

    function saveCourseAssignments() {
      const selectedCourses = Array.from(document.querySelectorAll('#assignmentContent input[type="checkbox"]:checked'))
        .map(cb => parseInt(cb.value));

      if (!currentLecturerId) {
        alert('No lecturer selected');
        return;
      }

      const saveBtn = document.querySelector('#courseAssignmentModal .btn-primary');
      const originalText = saveBtn.innerHTML;
      saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
      saveBtn.disabled = true;

      fetch('api/assign-courses-api.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          action: 'save_course_assignments',
          lecturer_id: currentLecturerId,
          course_ids: selectedCourses
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Close modal and refresh page
          bootstrap.Modal.getInstance(document.getElementById('courseAssignmentModal')).hide();
          location.reload();
        } else {
          alert('Error saving course assignments: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error saving assignments:', error);
        alert('Error saving course assignments. Please try again.');
      })
      .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
      });
    }

    function removeCourseAssignment(courseId) {
      const checkbox = document.getElementById('course_' + courseId);
      if (checkbox) {
        checkbox.checked = false;
        updateAssignedCoursesDisplay();
      }
    }

    function updateAssignedCoursesDisplay() {
      const assignedList = document.getElementById('assignedCoursesList');
      const noAssignments = document.getElementById('noAssignments');
      const checkedBoxes = document.querySelectorAll('#assignmentContent input[type="checkbox"]:checked');

      assignedList.innerHTML = Array.from(checkedBoxes).map(cb => {
        const course = availableCourses.find(c => c.id == cb.value);
        return `
          <div class="badge bg-primary me-1 mb-1 p-2">
            ${course.course_name} (${course.course_code})
            <button type="button" class="btn-close btn-close-white ms-1" onclick="removeCourseAssignment(${course.id})"></button>
          </div>
        `;
      }).join('');

      if (checkedBoxes.length === 0) {
        noAssignments.classList.remove('d-none');
      } else {
        noAssignments.classList.add('d-none');
      }
    }

    // Add event listeners for checkboxes
    document.addEventListener('change', function(e) {
      if (e.target.type === 'checkbox' && e.target.id.startsWith('course_')) {
        updateAssignedCoursesDisplay();
      }
    });

    // Load course assignment overview table
    function loadCourseAssignmentOverview() {
      fetch('api/assign-courses-api.php?action=get_course_assignment_overview')
        .then(response => response.json())
        .then(data => {
          const content = document.getElementById('assignmentTableContent');

          if (data && data.length > 0) {
            content.innerHTML = data.map(lecturer => `
              <tr>
                <td>${lecturer.first_name} ${lecturer.last_name}</td>
                <td>${lecturer.email}</td>
                <td>${lecturer.courses || '<span class="text-muted">No courses assigned</span>'}</td>
                <td>
                  <button class='btn btn-sm btn-primary' onclick='assignCourses(${lecturer.id}, "${lecturer.first_name} ${lecturer.last_name}")'>
                    <i class='fas fa-plus me-1'></i>Assign
                  </button>
                </td>
              </tr>
            `).join('');
          } else {
            content.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No lecturers found in your department</td></tr>';
          }
        })
        .catch(error => {
          console.error('Error loading course assignments:', error);
          document.getElementById('assignmentTableContent').innerHTML =
            '<tr><td colspan="4" class="text-center text-danger">Error loading course assignments</td></tr>';
        });
    }

    // Load course assignment overview on page load
    document.addEventListener('DOMContentLoaded', function() {
      loadCourseAssignmentOverview();
    });

    function updateEducationLevel() {
      const userType = document.getElementById("userType").value;
      const eduLabel = document.getElementById("eduLabel");
      const eduLevel = document.getElementById("eduLevel");

      eduLevel.innerHTML = '<option value="">Select</option>'; // Clear old options

      if (userType === "student") {
        eduLabel.textContent = "Year / Level";
        ["Year 1", "Year 2", "Year 3"].forEach(year => {
          const option = document.createElement("option");
          option.value = year;
          option.text = year;
          eduLevel.add(option);
        });
      } else if (userType === "lecturer") {
        eduLabel.textContent = "Education Level";
        ["A1", "A0", "Masters", "PhD"].forEach(level => {
          const option = document.createElement("option");
          option.value = level;
          option.text = level;
          eduLevel.add(option);
        });
      }
    }

    function clearFilters() {
      document.getElementById("userType").value = "";
      document.getElementById("eduLevel").innerHTML = '<option value="">Select</option>';
      document.querySelector('select[name="department"]').value = "";
      document.querySelector('select[name="gender"]').value = "";
      document.getElementById("eduLabel").textContent = "Education Level";
    }

    // Auto-refresh statistics every 5 minutes
    setInterval(function() {
      if (!document.hidden) {
        location.reload();
      }
    }, 300000);

    // Add loading state to form submission
    document.querySelector('form').addEventListener('submit', function(e) {
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Applying Filter...';
      submitBtn.disabled = true;

      // Re-enable after 3 seconds as fallback
      setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
      }, 3000);
    });
  </script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>