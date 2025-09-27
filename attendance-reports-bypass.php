<?php
/**
 * Attendance Reports - Authentication Bypass for Testing
 * This version bypasses authentication to test the page functionality
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "config.php";
require_once "attendance_reports_utils.php";

// Simulate a lecturer login for testing
$_SESSION['user_id'] = 1; // Assume user ID 1 exists
$_SESSION['role'] = 'lecturer';

// Get user data and setup
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    die("No user ID set");
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
    $error_message = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Test Version</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/attendance-reports.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">Attendance Reports - Test Version</h1>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Debug Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Debug Information</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
                        <p><strong>Lecturer ID:</strong> <?php echo $lecturer_dept['lecturer_id'] ?? 'Not set'; ?></p>
                        <p><strong>Department ID:</strong> <?php echo $lecturer_dept['department_id'] ?? 'Not set'; ?></p>
                        <p><strong>Classes Found:</strong> <?php echo count($classes); ?></p>
                        <p><strong>Selected Class:</strong> <?php echo $selectedClassId ?: 'None'; ?></p>
                        <p><strong>Selected Course:</strong> <?php echo $selectedCourseId ?: 'None'; ?></p>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3><?php echo number_format($stats['total_students']); ?></h3>
                                <p class="text-muted">Total Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3><?php echo number_format($stats['total_sessions']); ?></h3>
                                <p class="text-muted">Total Sessions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3><?php echo number_format($stats['avg_attendance'], 1); ?>%</h3>
                                <p class="text-muted">Average Attendance</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Select Class</label>
                                <select name="class_id" class="form-select" onchange="this.form.submit()">
                                    <option value="">Choose Class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class['id']); ?>"
                                                <?php echo ($selectedClassId == $class['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php if (!empty($courses)): ?>
                                <div class="col-md-6">
                                    <label class="form-label">Select Course</label>
                                    <select name="course_id" class="form-select">
                                        <option value="">Choose Course</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?php echo htmlspecialchars($course['id']); ?>"
                                                    <?php echo ($selectedCourseId == $course['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($course['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Generate Report</button>
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Results -->
                <?php if (!empty($attendanceData['summary'])): ?>
                    <div class="card">
                        <div class="card-header">
                            <h5>Attendance Report</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Attendance %</th>
                                            <th>Present Sessions</th>
                                            <th>Total Sessions</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendanceData['summary'] as $record): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($record['student']); ?></td>
                                                <td><?php echo $record['attendance_percent']; ?>%</td>
                                                <td><?php echo $record['present_count']; ?></td>
                                                <td><?php echo $record['total_count']; ?></td>
                                                <td>
                                                    <span class="badge <?php echo $record['attendance_percent'] >= 85 ? 'bg-success' : 'bg-danger'; ?>">
                                                        <?php echo $record['attendance_percent'] >= 85 ? 'Allowed' : 'Not Allowed'; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif (isset($_GET['course_id'])): ?>
                    <div class="alert alert-info">
                        No attendance data found for the selected course.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>