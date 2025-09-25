<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_stats':
            try {
                // Get real statistics from database
                $stmt = $pdo->query("
                    SELECT
                        (SELECT COUNT(*) FROM students) AS total_students,
                        (SELECT COUNT(*) FROM attendance_sessions) AS total_sessions,
                        (SELECT COUNT(*) FROM leave_requests WHERE status='pending') AS pending_leaves,
                        (SELECT
                            CASE
                                WHEN (SELECT COUNT(*) FROM attendance_records) > 0
                                THEN ROUND(((SELECT COUNT(*) FROM attendance_records WHERE status='present') * 100.0) / (SELECT COUNT(*) FROM attendance_records), 1)
                                ELSE 0
                            END
                        ) AS avg_attendance
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'total_students' => (int)($stats['total_students'] ?? 0),
                    'total_sessions' => (int)($stats['total_sessions'] ?? 0),
                    'avg_attendance' => (float)($stats['avg_attendance'] ?? 0),
                    'pending_leaves' => (int)($stats['pending_leaves'] ?? 0)
                ]);
            } catch (PDOException $e) {
                error_log("Reports stats error: " . $e->getMessage());
                echo json_encode([
                    'total_students' => 0,
                    'total_sessions' => 0,
                    'avg_attendance' => 0,
                    'pending_leaves' => 0
                ]);
            }
            exit;

        case 'get_departments':
            try {
                $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($departments);
            } catch (PDOException $e) {
                error_log("Get departments error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_options':
            try {
                $deptId = $_GET['department_id'] ?? 0;
                if ($deptId) {
                    $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY name");
                    $stmt->execute([$deptId]);
                } else {
                    $stmt = $pdo->query("SELECT id, name FROM options ORDER BY name");
                }
                $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($options);
            } catch (PDOException $e) {
                error_log("Get options error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_courses':
            try {
                $deptId = $_GET['department_id'] ?? 0;
                $optionId = $_GET['option_id'] ?? 0;

                if ($deptId && $optionId) {
                    // Get courses for specific department and option
                    $stmt = $pdo->prepare("SELECT id, name, course_code FROM courses WHERE department_id = ? ORDER BY name");
                    $stmt->execute([$deptId]);
                } elseif ($deptId) {
                    // Get all courses for the department
                    $stmt = $pdo->prepare("SELECT id, name, course_code FROM courses WHERE department_id = ? ORDER BY name");
                    $stmt->execute([$deptId]);
                } else {
                    // Get all courses
                    $stmt = $pdo->query("SELECT id, name, course_code FROM courses ORDER BY name");
                }

                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($courses);
            } catch (PDOException $e) {
                error_log("Get courses error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_reports':
            try {
                $where = [];
                $params = [];

                // Build filters
                if (!empty($_GET['department_id'])) {
                    $where[] = "d.id = ?";
                    $params[] = $_GET['department_id'];
                }

                if (!empty($_GET['option_id'])) {
                    $where[] = "s.option_id = ?";
                    $params[] = $_GET['option_id'];
                }

                if (!empty($_GET['course_id'])) {
                    $where[] = "c.id = ?";
                    $params[] = $_GET['course_id'];
                }

                if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                    $where[] = "DATE(ar.recorded_at) BETWEEN ? AND ?";
                    $params[] = $_GET['date_from'];
                    $params[] = $_GET['date_to'];
                }

                $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

                $stmt = $pdo->prepare("
                    SELECT
                        ar.id,
                        s.first_name,
                        s.last_name,
                        d.name as department_name,
                        o.name as option_name,
                        c.name as course_name,
                        c.course_code,
                        DATE(ar.recorded_at) as attendance_date,
                        ar.status,
                        ar.recorded_at
                    FROM attendance_records ar
                    INNER JOIN students s ON ar.student_id = s.id
                    INNER JOIN options o ON s.option_id = o.id
                    INNER JOIN departments d ON s.department_id = d.id
                    LEFT JOIN courses c ON ar.course_id = c.id
                    {$whereClause}
                    ORDER BY ar.recorded_at DESC
                    LIMIT 1000
                ");

                $stmt->execute($params);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode($reports);
            } catch (PDOException $e) {
                error_log("Get reports error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'export_reports':
            try {
                $where = [];
                $params = [];

                // Build filters same as get_reports
                if (!empty($_GET['department_id'])) {
                    $where[] = "d.id = ?";
                    $params[] = $_GET['department_id'];
                }

                if (!empty($_GET['option_id'])) {
                    $where[] = "s.option_id = ?";
                    $params[] = $_GET['option_id'];
                }

                if (!empty($_GET['course_id'])) {
                    $where[] = "c.id = ?";
                    $params[] = $_GET['course_id'];
                }

                if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                    $where[] = "DATE(ar.recorded_at) BETWEEN ? AND ?";
                    $params[] = $_GET['date_from'];
                    $params[] = $_GET['date_to'];
                }

                $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

                $stmt = $pdo->prepare("
                    SELECT
                        ar.id,
                        s.first_name,
                        s.last_name,
                        d.name as department_name,
                        o.name as option_name,
                        c.name as course_name,
                        c.course_code,
                        DATE(ar.recorded_at) as attendance_date,
                        ar.status,
                        ar.recorded_at
                    FROM attendance_records ar
                    INNER JOIN students s ON ar.student_id = s.id
                    INNER JOIN options o ON s.option_id = o.id
                    INNER JOIN departments d ON s.department_id = d.id
                    LEFT JOIN courses c ON ar.course_id = c.id
                    {$whereClause}
                    ORDER BY ar.recorded_at DESC
                ");

                $stmt->execute($params);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $format = $_GET['export'] ?? 'excel';

                if ($format === 'excel') {
                    exportToExcel($reports);
                } elseif ($format === 'pdf') {
                    exportToPDF($reports);
                }

            } catch (PDOException $e) {
                error_log("Export reports error: " . $e->getMessage());
                die("Export failed: " . $e->getMessage());
            }
            exit;
    }
}

// Export functions
function exportToExcel($reports) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo "Student Name\tDepartment\tOption\tCourse\tCourse Code\tDate\tStatus\n";

    foreach ($reports as $report) {
        $studentName = $report['first_name'] . ' ' . $report['last_name'];
        $courseCode = $report['course_code'] ?? 'N/A';
        echo "{$studentName}\t{$report['department_name']}\t{$report['option_name']}\t{$report['course_name']}\t{$courseCode}\t{$report['attendance_date']}\t{$report['status']}\n";
    }
    exit;
}

function exportToPDF($reports) {
    // Simple HTML to PDF conversion (you might want to use a proper PDF library like TCPDF or FPDF)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.pdf"');

    echo "<html><head><title>Attendance Report</title></head><body>";
    echo "<h1>Attendance Report - " . date('Y-m-d') . "</h1>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Student Name</th><th>Department</th><th>Option</th><th>Course</th><th>Course Code</th><th>Date</th><th>Status</th></tr>";

    foreach ($reports as $report) {
        $studentName = $report['first_name'] . ' ' . $report['last_name'];
        $courseCode = $report['course_code'] ?? 'N/A';
        echo "<tr>";
        echo "<td>{$studentName}</td>";
        echo "<td>{$report['department_name']}</td>";
        echo "<td>{$report['option_name']}</td>";
        echo "<td>{$report['course_name']}</td>";
        echo "<td>{$courseCode}</td>";
        echo "<td>{$report['attendance_date']}</td>";
        echo "<td>{$report['status']}</td>";
        echo "</tr>";
    }

    echo "</table></body></html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Reports | RP Attendance System</title>

  <!-- Bootstrap & Font-Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css" rel="stylesheet" />

  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f5f7fa; margin: 0; }
    .sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100vh; background:#003366; color:#fff; padding-top:20px;}
    .sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none;}
    .sidebar a:hover, .sidebar a.active { background:#0059b3;}
    .topbar   { margin-left:250px; background:#fff; padding:10px 30px; border-bottom:1px solid #ddd;}
    .main-content{ margin-left:250px; padding:30px;}
    .card   { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.05);}
    .footer { text-align:center; margin-left:250px; padding:15px; font-size:.9rem; color:#666; background:#f0f0f0;}
    @media (max-width:768px){
        .sidebar,.topbar,.main-content,.footer{margin-left:0;width:100%;} .sidebar{display:none;}
        .stats-row { flex-direction: column; gap: 15px; }
        .filter-row { flex-direction: column; gap: 15px; }
        .export-buttons { justify-content: center; }
    }

    /* Enhanced card styling */
    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 25px;
        color: white;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.2);
    }

    .stats-card .card-icon {
        font-size: 3rem;
        opacity: 0.9;
        margin-bottom: 15px;
    }

    .stats-card h6 {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 10px;
        font-weight: 500;
    }

    .stats-card h4 {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0;
    }

    .stats-card .subtitle {
        font-size: 0.8rem;
        opacity: 0.8;
        margin-top: 5px;
    }

    /* Filter enhancements */
    .filter-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border: none;
    }

    .filter-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
        border-radius: 12px 12px 0 0 !important;
    }

    .form-select:focus, .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    /* Table enhancements */
    .table-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .table-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }

    .table th {
        background: #f8f9fa;
        border-top: none;
        font-weight: 600;
        color: #495057;
        padding: 12px;
    }

    .table td {
        padding: 12px;
        vertical-align: middle;
    }

    .badge {
        font-size: 0.75rem;
        padding: 6px 10px;
        border-radius: 6px;
    }

    /* Loading states */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        z-index: 10;
    }

    /* Button enhancements */
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        border: none;
    }

    /* Responsive improvements */
    @media (max-width: 576px) {
        .stats-card {
            padding: 20px;
        }

        .stats-card h4 {
            font-size: 1.8rem;
        }

        .table th, .table td {
            padding: 8px 4px;
            font-size: 0.9rem;
        }
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4>👩‍💼 Admin</h4>
      <hr style="border-color:#ffffff66;">
    </div>
    <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
    <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
    <a href="admin-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Reports</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Attendance Reports</h5>
    <span>Admin Panel</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">

    <!-- Stats -->
    <div class="row mb-4 g-3 stats-row">
      <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stats-card">
          <div class="text-center">
            <i class="fas fa-users card-icon"></i>
            <h6>Total Students</h6>
            <h4 id="totalStudents">--</h4>
            <div class="subtitle">Registered students</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stats-card">
          <div class="text-center">
            <i class="fas fa-calendar-check card-icon"></i>
            <h6>Total Sessions</h6>
            <h4 id="totalSessions">--</h4>
            <div class="subtitle">Attendance sessions</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stats-card">
          <div class="text-center">
            <i class="fas fa-percent card-icon"></i>
            <h6>Average Attendance</h6>
            <h4 id="avgAttendance">--%</h4>
            <div class="subtitle">Overall attendance rate</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stats-card">
          <div class="text-center">
            <i class="fas fa-clock card-icon"></i>
            <h6>Pending Leaves</h6>
            <h4 id="pendingLeave">--</h4>
            <div class="subtitle">Awaiting approval</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Reports</h6>
      </div>
      <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end">
          <div class="col-lg-3 col-md-6">
            <label for="departmentFilter" class="form-label fw-semibold">
              <i class="fas fa-building me-1"></i>Department
            </label>
            <select id="departmentFilter" class="form-select">
              <option value="">All Departments</option>
            </select>
          </div>

          <div class="col-lg-3 col-md-6">
            <label for="optionFilter" class="form-label fw-semibold">
              <i class="fas fa-graduation-cap me-1"></i>Option
            </label>
            <select id="optionFilter" class="form-select" disabled>
              <option value="">All Options</option>
            </select>
          </div>

          <div class="col-lg-3 col-md-6">
            <label for="courseFilter" class="form-label fw-semibold">
              <i class="fas fa-book me-1"></i>Course
            </label>
            <select id="courseFilter" class="form-select" disabled>
              <option value="">All Courses</option>
            </select>
          </div>

          <div class="col-lg-3 col-md-6">
            <label for="dateRange" class="form-label fw-semibold">
              <i class="fas fa-calendar-alt me-1"></i>Date Range
            </label>
            <input type="text" id="dateRange" class="form-control" placeholder="Select date range">
          </div>

          <div class="col-12 d-flex justify-content-between align-items-center mt-3">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter me-2"></i>Apply Filters
              </button>
              <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                <i class="fas fa-undo me-2"></i>Reset
              </button>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-success" id="exportExcel">
                <i class="fas fa-file-excel me-2"></i>Export Excel
              </button>
              <button type="button" class="btn btn-danger" id="exportPdf">
                <i class="fas fa-file-pdf me-2"></i>Export PDF
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Report Table -->
    <div class="table-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-table me-2"></i>Attendance Reports</h6>
        <div class="d-flex align-items-center gap-2">
          <small class="text-muted">Showing <span id="recordCount">0</span> records</small>
          <div class="spinner-border spinner-border-sm d-none" id="tableSpinner" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th><i class="fas fa-user me-1"></i>Student Name</th>
                <th><i class="fas fa-building me-1"></i>Department</th>
                <th><i class="fas fa-graduation-cap me-1"></i>Option</th>
                <th><i class="fas fa-book me-1"></i>Course</th>
                <th><i class="fas fa-calendar me-1"></i>Date</th>
                <th><i class="fas fa-check-circle me-1"></i>Status</th>
                <th><i class="fas fa-fingerprint me-1"></i>Method</th>
              </tr>
            </thead>
            <tbody id="reportTableBody">
              <tr>
                <td colspan="8" class="text-center py-5">
                  <div class="d-flex flex-column align-items-center">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Data Available</h5>
                    <p class="text-muted mb-0">Apply filters to view attendance reports</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">&copy; 2025 Rwanda Polytechnic | Admin Panel</div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>

  <script>
    let currentFilters = {};

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      loadStatistics();
      loadDepartments();
      initializeDateRangePicker();
      setupEventListeners();
    });

    // Load statistics
    function loadStatistics() {
      $.getJSON('admin-reports.php?ajax=1&action=get_stats', function(data) {
        $('#totalStudents').text(data.total_students);
        $('#totalSessions').text(data.total_sessions);
        $('#avgAttendance').text(data.avg_attendance + '%');
        $('#pendingLeave').text(data.pending_leaves);
      }).fail(function() {
        console.error('Failed to load statistics');
      });
    }

    // Load departments
    function loadDepartments() {
      $.getJSON('admin-reports.php?ajax=1&action=get_departments', function(data) {
        const deptSelect = $('#departmentFilter');
        deptSelect.empty().append('<option value="">All Departments</option>');

        data.forEach(function(dept) {
          deptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
        });
      }).fail(function() {
        console.error('Failed to load departments');
      });
    }

    // Load options based on department
    function loadOptions(departmentId) {
      if (!departmentId) {
        $('#optionFilter').html('<option value="">All Options</option>').prop('disabled', true);
        $('#courseFilter').html('<option value="">All Courses</option>').prop('disabled', true);
        return;
      }

      $.getJSON('admin-reports.php?ajax=1&action=get_options', {department_id: departmentId}, function(data) {
        const optionSelect = $('#optionFilter');
        optionSelect.empty().append('<option value="">All Options</option>');

        data.forEach(function(option) {
          optionSelect.append(`<option value="${option.id}">${option.name}</option>`);
        });

        optionSelect.prop('disabled', false);
      }).fail(function() {
        console.error('Failed to load options');
      });
    }

    // Load courses based on department and option
    function loadCourses(departmentId, optionId) {
      if (!departmentId) {
        $('#courseFilter').html('<option value="">All Courses</option>').prop('disabled', true);
        return;
      }

      $.getJSON('admin-reports.php?ajax=1&action=get_courses', {
        department_id: departmentId,
        option_id: optionId
      }, function(data) {
        const courseSelect = $('#courseFilter');
        courseSelect.empty().append('<option value="">All Courses</option>');

        data.forEach(function(course) {
          const courseDisplay = course.course_code ? `${course.name} (${course.course_code})` : course.name;
          courseSelect.append(`<option value="${course.id}">${courseDisplay}</option>`);
        });

        courseSelect.prop('disabled', false);
      }).fail(function() {
        console.error('Failed to load courses');
      });
    }

    // Initialize date range picker
    function initializeDateRangePicker() {
      $('#dateRange').daterangepicker({
        autoUpdateInput: false,
        locale: {
          cancelLabel: 'Clear',
          format: 'YYYY-MM-DD'
        },
        ranges: {
          'Today': [moment(), moment()],
          'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
          'Last 7 Days': [moment().subtract(6, 'days'), moment()],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'This Month': [moment().startOf('month'), moment().endOf('month')],
          'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
      });

      $('#dateRange').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
      });

      $('#dateRange').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
      });
    }

    // Setup event listeners
    function setupEventListeners() {
      // Department change
      $('#departmentFilter').change(function() {
        const deptId = $(this).val();
        loadOptions(deptId);
        $('#courseFilter').html('<option value="">All Courses</option>').prop('disabled', true);
      });

      // Option change
      $('#optionFilter').change(function() {
        const deptId = $('#departmentFilter').val();
        const optionId = $(this).val();
        loadCourses(deptId, optionId);
      });

      // Filter form submission
      $('#filterForm').submit(function(e) {
        e.preventDefault();
        applyFilters();
      });

      // Reset filters
      $('#resetFilters').click(function() {
        resetFilters();
      });

      // Export buttons
      $('#exportExcel').click(function() {
        exportData('excel');
      });

      $('#exportPdf').click(function() {
        exportData('pdf');
      });
    }

    // Apply filters
    function applyFilters() {
      const filters = {
        department_id: $('#departmentFilter').val(),
        option_id: $('#optionFilter').val(),
        course_id: $('#courseFilter').val()
      };

      // Parse date range
      const dateRange = $('#dateRange').val();
      if (dateRange && dateRange.includes(' to ')) {
        const [startDate, endDate] = dateRange.split(' to ');
        filters.date_from = startDate;
        filters.date_to = endDate;
      }

      currentFilters = filters;
      loadReports(filters);
    }

    // Load reports data
    function loadReports(filters = {}) {
      $('#tableSpinner').removeClass('d-none');
      $('#reportTableBody').html(`
        <tr>
          <td colspan="8" class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2">Loading reports...</div>
          </td>
        </tr>
      `);

      const queryParams = $.param(filters);
      $.getJSON(`admin-reports.php?ajax=1&action=get_reports&${queryParams}`, function(data) {
        $('#tableSpinner').addClass('d-none');
        displayReports(data);
      }).fail(function() {
        $('#tableSpinner').addClass('d-none');
        $('#reportTableBody').html(`
          <tr>
            <td colspan="8" class="text-center py-5">
              <div class="text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <div>Failed to load reports</div>
              </div>
            </td>
          </tr>
        `);
      });
    }

    // Display reports in table
    function displayReports(reports) {
      $('#recordCount').text(reports.length);

      if (reports.length === 0) {
        $('#reportTableBody').html(`
          <tr>
            <td colspan="8" class="text-center py-5">
              <div class="d-flex flex-column align-items-center">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Records Found</h5>
                <p class="text-muted mb-0">Try adjusting your filters</p>
              </div>
            </td>
          </tr>
        `);
        return;
      }

      let html = '';
      reports.forEach(function(report, index) {
        const statusBadge = report.status === 'present'
          ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Present</span>'
          : '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Absent</span>';

        const methodIcon = report.method === 'face'
          ? '<i class="fas fa-camera text-info"></i>'
          : '<i class="fas fa-fingerprint text-warning"></i>';

        html += `
          <tr>
            <td>${index + 1}</td>
            <td>${report.first_name} ${report.last_name}</td>
            <td>${report.department_name}</td>
            <td>${report.option_name}</td>
            <td>${report.course_name || 'N/A'} ${report.course_code ? `(${report.course_code})` : ''}</td>
            <td>${report.attendance_date}</td>
            <td>${statusBadge}</td>
            <td class="text-center"><i class="fas fa-fingerprint text-warning"></i></td>
          </tr>
        `;
      });

      $('#reportTableBody').html(html);
    }

    // Reset filters
    function resetFilters() {
      $('#departmentFilter').val('');
      $('#optionFilter').html('<option value="">All Options</option>').prop('disabled', true);
      $('#courseFilter').html('<option value="">All Courses</option>').prop('disabled', true);
      $('#dateRange').val('');
      currentFilters = {};
      loadReports();
    }

    // Export data
    function exportData(format) {
      if (Object.keys(currentFilters).length === 0) {
        alert('Please apply filters first to export data');
        return;
      }

      const queryParams = $.param({
        ...currentFilters,
        export: format,
        ajax: 1,
        action: 'export_reports'
      });

      window.open(`admin-reports.php?${queryParams}`, '_blank');
    }

    // Load initial reports
    loadReports();
  </script>
</body>
</html>
