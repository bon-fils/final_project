<?php
/**
 * Enhanced Attendance Reports
 * Clean, modern, and accessible attendance reporting system
 * Features: Advanced filtering, data visualization, export functionality
 * Version: 2.0
 */

// Temporarily enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "config.php";
require_once "session_check.php";
require_once "attendance_reports_utils.php";

require_role(['lecturer', 'admin']);

// Get user data and setup
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: index.php?error=invalid_session");
    exit();
}

try {
    $lecturer_dept = getLecturerDepartment($user_id);
    setupLecturerCourses($lecturer_dept['lecturer_id'], $lecturer_dept['department_id']);
    $_SESSION['lecturer_id'] = $lecturer_dept['lecturer_id'];

    // Get available data
    $classes = getLecturerClasses($lecturer_dept['lecturer_id']);
    $selectedClassId = $_GET['class_id'] ?? null;
    $selectedCourseId = $_GET['course_id'] ?? null;

    $courses = [];
    $attendanceData = ['summary' => [], 'details' => []];
    $stats = ['total_students' => 0, 'total_sessions' => 0, 'avg_attendance' => 0];

    if ($selectedClassId) {
        $courses = getClassCourses($lecturer_dept['lecturer_id'], $selectedClassId);

        if ($selectedCourseId) {
            $attendanceData = getAttendanceReport($lecturer_dept['lecturer_id'], $selectedClassId, $selectedCourseId);
            $stats = getAttendanceStatistics($lecturer_dept['lecturer_id'], $selectedClassId, $selectedCourseId);
        } else {
            $stats = getAttendanceStatistics($lecturer_dept['lecturer_id'], $selectedClassId);
        }
    } else {
        $stats = getAttendanceStatistics($lecturer_dept['lecturer_id']);
    }

} catch (Exception $e) {
    error_log("Error in attendance reports: " . $e->getMessage());
    $error_message = "An error occurred while loading attendance data. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Advanced Attendance Reports - Rwanda Polytechnic">
    <meta name="author" content="Rwanda Polytechnic">
    <title>Attendance Reports | RP Attendance System</title>

    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/attendance-reports.css" rel="stylesheet">

    <!-- Chart.js for data visualization -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- External JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/attendance-reports.js"></script>

    <!-- Pass data to JavaScript -->
    <script>
        window.attendanceData = <?php echo json_encode($attendanceData['details']); ?>;
        window.reportStats = <?php echo json_encode($stats); ?>;
    </script>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-primary d-md-none position-fixed" id="mobileMenuToggle" style="top: 20px; left: 20px; z-index: 1050;">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <nav class="sidebar" role="navigation" aria-label="Main navigation">
        <div class="sidebar-header">
            <h2 class="sidebar-title">
                <i class="fas fa-user-graduate me-2"></i>Lecturer
            </h2>
            <p class="sidebar-subtitle">Panel</p>
        </div>

        <ul class="sidebar-nav">
            <li class="sidebar-nav-item">
                <a href="lecturer-dashboard.php" class="sidebar-nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="lecturer-my-courses.php" class="sidebar-nav-link">
                    <i class="fas fa-book"></i> My Courses
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="attendance-session.php" class="sidebar-nav-link">
                    <i class="fas fa-calendar-check"></i> Attendance Session
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="attendance-reports.php" class="sidebar-nav-link active" aria-current="page">
                    <i class="fas fa-chart-bar"></i> Attendance Reports
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="leave-requests.php" class="sidebar-nav-link">
                    <i class="fas fa-clipboard-list"></i> Leave Requests
                </a>
            </li>
            <li class="sidebar-nav-item">
                <a href="index.php" class="sidebar-nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </nav>

    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-title">
            <i class="fas fa-chart-bar me-2"></i>Attendance Reports
        </div>
        <div class="topbar-info">
            RP Attendance System
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="attendance-container">
            <!-- Page Header -->
            <header class="page-header">
                <div class="page-header-content">
                    <h1 class="page-title">
                        <i class="fas fa-chart-line me-3"></i>Advanced Attendance Reports
                    </h1>
                    <p class="page-subtitle">
                        Comprehensive attendance analytics and reporting for your courses
                    </p>
                </div>
            </header>

            <!-- Alert Container -->
            <div id="alertContainer" aria-live="polite" aria-atomic="true"></div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <section class="stats-grid" aria-labelledby="stats-heading">
                <h2 id="stats-heading" class="visually-hidden">Attendance Statistics</h2>

                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_students']); ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['total_sessions']); ?></div>
                        <div class="stat-label">Total Sessions</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-value"><?php echo number_format($stats['avg_attendance'], 1); ?>%</div>
                        <div class="stat-label">Average Attendance</div>
                    </div>
                </div>
            </section>

            <!-- Filters Section -->
            <section class="filters-section" aria-labelledby="filters-heading">
                <header class="filters-header">
                    <h2 id="filters-heading" class="filters-title">
                        <i class="fas fa-filter me-2"></i>Report Filters
                    </h2>
                </header>

                <form id="filterForm" method="GET" class="filters-form" aria-label="Attendance report filters">
                    <div class="form-group">
                        <label for="class_id" class="form-label">Select Class (Year Level)</label>
                        <select id="class_id" name="class_id" class="form-select" onchange="this.form.submit()" aria-describedby="class-help">
                            <option value="">Choose Class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                        <?php echo (isset($_GET['class_id']) && $_GET['class_id'] == $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="class-help" class="form-text">Select a year level to filter attendance data</div>
                    </div>

                    <?php if (!empty($courses)): ?>
                        <div class="form-group">
                            <label for="course_id" class="form-label">Select Course</label>
                            <select id="course_id" name="course_id" class="form-select" aria-describedby="course-help">
                                <option value="">Choose Course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo htmlspecialchars($course['id']); ?>"
                                            <?php echo (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="course-help" class="form-text">Choose a specific course for detailed reports</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Generate Report
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </section>

            <!-- Reports Section -->
            <?php if (!empty($attendanceData['summary'])): ?>
                <section class="reports-section" aria-labelledby="reports-heading">
                    <header class="reports-header">
                        <h2 id="reports-heading" class="reports-title">Attendance Report</h2>
                        <div class="reports-actions">
                            <button id="printReport" class="btn btn-outline-primary" aria-label="Print report">
                                <i class="fas fa-print me-2"></i>Print
                            </button>
                            <button id="viewAllAttendanceBtn" class="btn btn-info" aria-label="View detailed attendance">
                                <i class="fas fa-list me-2"></i>View Details
                            </button>
                            <button id="exportCsvBtn" class="btn btn-success" aria-label="Export as CSV">
                                <i class="fas fa-file-csv me-2"></i>Export CSV
                            </button>
                            <button id="exportPdfBtn" class="btn btn-danger" aria-label="Export as PDF">
                                <i class="fas fa-file-pdf me-2"></i>Export PDF
                            </button>
                        </div>
                    </header>

                    <!-- Data Table -->
                    <div class="table-container">
                        <table class="attendance-table" aria-label="Student attendance summary">
                            <thead>
                                <tr>
                                    <th scope="col">Student Name</th>
                                    <th scope="col">Attendance %</th>
                                    <th scope="col">Present Sessions</th>
                                    <th scope="col">Total Sessions</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceData['summary'] as $record):
                                    $statusClass = $record['attendance_percent'] >= 85 ? 'attendance-high' : 'attendance-low';
                                    $statusText = $record['attendance_percent'] >= 85 ? 'Allowed' : 'Not Allowed';
                                    $statusBadgeClass = $record['attendance_percent'] >= 85 ? 'status-allowed' : 'status-not-allowed';
                                ?>
                                    <tr>
                                        <td class="student-name"><?php echo htmlspecialchars($record['student']); ?></td>
                                        <td>
                                            <span class="attendance-percentage <?php echo $statusClass; ?>">
                                                <?php echo $record['attendance_percent']; ?>%
                                            </span>
                                        </td>
                                        <td><?php echo $record['present_count']; ?></td>
                                        <td><?php echo $record['total_count']; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $statusBadgeClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Chart Container -->
                    <div class="chart-container">
                        <h3 class="chart-title">
                            <i class="fas fa-chart-pie me-2"></i>Attendance Overview
                        </h3>
                        <canvas id="attendanceChart" width="400" height="200" aria-label="Attendance distribution chart"></canvas>
                    </div>
                </section>
            <?php elseif (isset($_GET['course_id'])): ?>
                <div class="empty-state" role="status" aria-live="polite">
                    <i class="fas fa-chart-bar empty-icon"></i>
                    <h3 class="empty-title">No Attendance Data</h3>
                    <p class="empty-message">No attendance records found for the selected course and class.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Attendance Details Modal -->
    <div class="modal fade" id="attendanceDetailsModal" tabindex="-1" aria-labelledby="attendanceDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="attendanceDetailsLabel">
                        <i class="fas fa-list me-2"></i>Detailed Attendance Records
                    </h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="allAttendanceDetailsBody">
                    <!-- Details table will be populated here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" id="printAllDetailsBtn">
                        <i class="fas fa-print me-2"></i>Print Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Initialize JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Update attendance data in JavaScript
            if (window.attendanceReports) {
                window.attendanceReports.updateAttendanceData(<?php echo json_encode($attendanceData['details']); ?>);
            }
        });
    </script>
</body>
</html>