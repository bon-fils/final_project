<?php
session_start();
require_once "config.php"; // $pdo connection
require_once "session_check.php";
require_role(['lecturer', 'admin']);

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// Validate user_id
if (!$user_id) {
    error_log("Invalid user_id in attendance-reports");
    session_destroy();
    header("Location: index.php?error=invalid_session");
    exit();
}

// Initialize variables
$lecturer_dept = null;
$lecturer_id = null;

if ($user_role === 'admin') {
    // Admin can see all data, no lecturer restriction
    $lecturer_id = null;
} else {
    // For lecturer, get department and lecturer_id
    try {
        $dept_stmt = $pdo->prepare("
            SELECT l.department_id, l.id as lecturer_id
            FROM lecturers l
            INNER JOIN users u ON l.email = u.email
            WHERE u.id = :user_id AND u.role = 'lecturer'
        ");
        $dept_stmt->execute(['user_id' => $user_id]);
        $lecturer_dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching lecturer department: " . $e->getMessage());
        header("Location: lecturer-dashboard.php?error=lecturer_setup_required");
        exit;
    }

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

    $lecturer_id = $lecturer_dept['lecturer_id'];
    // Store lecturer_id in session for other pages to use
    $_SESSION['lecturer_id'] = $lecturer_id;
}

// First, add lecturer_id column to courses table if it doesn't exist
try {
    $pdo->query("ALTER TABLE courses ADD COLUMN lecturer_id INT NULL AFTER department_id");
    $pdo->query("CREATE INDEX idx_lecturer_id ON courses(lecturer_id)");
} catch (PDOException $e) {
    // Column might already exist, continue
}

// Update courses to assign them to the current lecturer if not already assigned (only for lecturers)
if ($lecturer_id) {
    $update_stmt = $pdo->prepare("
        UPDATE courses
        SET lecturer_id = :lecturer_id
        WHERE lecturer_id IS NULL AND department_id = :department_id
    ");
    $update_stmt->execute([
        'lecturer_id' => $lecturer_id,
        'department_id' => $lecturer_dept['department_id']
    ]);
}

// Fetch year levels from students in courses
try {
    $query = "
        SELECT DISTINCT s.year_level
        FROM students s
        INNER JOIN courses c ON s.option_id = c.id
    ";
    $params = [];
    if ($lecturer_id) {
        $query .= " WHERE c.lecturer_id = :lecturer_id";
        $params['lecturer_id'] = $lecturer_id;
    }
    $query .= " ORDER BY s.year_level ASC";

    $stmtClasses = $pdo->prepare($query);
    $stmtClasses->execute($params);
    $classRows = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

    $classes = [];
    foreach ($classRows as $row) {
        $classes[] = ['id' => $row['year_level'], 'name' => $row['year_level']];
    }
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
    $classes = [];
}

// Fetch courses for selected class
$selectedClassId = $_GET['class_id'] ?? null;
$selectedCourseId = $_GET['course_id'] ?? null;
$courses = [];
if ($selectedClassId) {
    try {
        $query = "
            SELECT c.id, c.name
            FROM courses c
            INNER JOIN students s ON s.option_id = c.id
            WHERE s.year_level = :year_level
        ";
        $params = ['year_level' => $selectedClassId];
        if ($lecturer_id) {
            $query .= " AND c.lecturer_id = :lecturer_id";
            $params['lecturer_id'] = $lecturer_id;
        }
        $query .= " GROUP BY c.id, c.name ORDER BY c.name ASC";

        $stmtCourses = $pdo->prepare($query);
        $stmtCourses->execute($params);
        $courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching courses: " . $e->getMessage());
        $courses = [];
    }
}

// Fetch attendance data
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$attendanceData = [];
$attendanceDetailsData = [];
if ($selectedClassId && $selectedCourseId) {
    try {
        // Main report - get students and their attendance for this course
        $query = "
            SELECT
                s.id AS student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                COUNT(CASE WHEN ar.status = 'present' THEN 1 END) AS present_count,
                COUNT(ar.id) AS total_count
            FROM students s
            LEFT JOIN attendance_records ar ON s.id = ar.student_id
            LEFT JOIN attendance_sessions sess ON ar.session_id = sess.id
            INNER JOIN courses c ON sess.course_id = c.id
            WHERE s.year_level = :year_level AND sess.course_id = :course_id
        ";
        $params = [
            'year_level' => $selectedClassId,
            'course_id' => $selectedCourseId
        ];
        if ($lecturer_id) {
            $query .= " AND c.lecturer_id = :lecturer_id";
            $params['lecturer_id'] = $lecturer_id;
        }
        if ($startDate && $endDate) {
            $query .= " AND sess.session_date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $startDate;
            $params['end_date'] = $endDate;
        }
        $query .= " GROUP BY s.id, s.first_name, s.last_name ORDER BY s.first_name, s.last_name ASC";

        $stmtAttendance = $pdo->prepare($query);
        $stmtAttendance->execute($params);
        $attendanceRows = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);

        // Collect student IDs for batch query
        $studentIds = array_column($attendanceRows, 'student_id');
        $studentNames = [];
        foreach ($attendanceRows as $row) {
            $percent = $row['total_count'] > 0 ? ($row['present_count'] / $row['total_count']) * 100 : 0;
            $attendanceData[] = [
                'student' => $row['student_name'],
                'attendance_percent' => round($percent)
            ];
            $studentNames[$row['student_id']] = $row['student_name'];
        }

        // Modal: detailed attendance - get all session dates and build complete attendance map
        if (!empty($studentIds)) {
            // Get all sessions for the course
            $sessionQuery = "SELECT id, DATE(session_date) as date FROM attendance_sessions WHERE course_id = :course_id";
            $sessionParams = ['course_id' => $selectedCourseId];
            if ($startDate && $endDate) {
                $sessionQuery .= " AND session_date BETWEEN :start_date AND :end_date";
                $sessionParams['start_date'] = $startDate;
                $sessionParams['end_date'] = $endDate;
            }
            $sessionQuery .= " ORDER BY session_date ASC";
            $stmtSessions = $pdo->prepare($sessionQuery);
            $stmtSessions->execute($sessionParams);
            $sessions = $stmtSessions->fetchAll(PDO::FETCH_ASSOC);

            // Get all attendance records for these sessions and students
            $sessionIds = array_column($sessions, 'id');
            if (!empty($sessionIds)) {
                $sessionPlaceholders = str_repeat('?,', count($sessionIds) - 1) . '?';
                $studentPlaceholders = str_repeat('?,', count($studentIds) - 1) . '?';
                $stmtAttendance = $pdo->prepare("
                    SELECT session_id, student_id, status
                    FROM attendance_records
                    WHERE session_id IN ($sessionPlaceholders) AND student_id IN ($studentPlaceholders)
                ");
                $stmtAttendance->execute(array_merge($sessionIds, $studentIds));
                $attendanceRows = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);

                // Build attendance map
                $attendanceMap = [];
                foreach ($attendanceRows as $row) {
                    $attendanceMap[$row['student_id']][$row['session_id']] = $row['status'];
                }

                // Build details data
                foreach ($studentIds as $studentId) {
                    $studentName = $studentNames[$studentId];
                    foreach ($sessions as $session) {
                        $status = $attendanceMap[$studentId][$session['id']] ?? 'absent';
                        $attendanceDetailsData[$studentName][$session['date']] = $status;
                    }
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching attendance data: " . $e->getMessage());
        $attendanceData = [];
        $attendanceDetailsData = [];
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv' && !empty($attendanceData)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['Student Name', 'Attendance %', 'Status']);

    // Data
    foreach ($attendanceData as $record) {
        $status = $record['attendance_percent'] >= 85 ? 'Allowed' : 'Not Allowed';
        fputcsv($output, [
            $record['student'],
            $record['attendance_percent'] . '%',
            $status
        ]);
    }

    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Attendance Reports | <?php echo $user_role === 'admin' ? 'Admin' : 'Lecturer'; ?> | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        :root {
            /* Primary Brand Colors - RP Blue with Modern Palette */
            --primary-color: #0066cc;
            --primary-dark: #003366;
            --primary-light: #e6f0ff;
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);

            /* Status Colors - Enhanced Contrast and Modern */
            --success-color: #10b981;
            --success-light: #d1fae5;
            --success-dark: #047857;
            --success-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);

            --danger-color: #ef4444;
            --danger-light: #fee2e2;
            --danger-dark: #dc2626;
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);

            --warning-color: #f59e0b;
            --warning-light: #fef3c7;
            --warning-dark: #d97706;
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);

            --info-color: #06b6d4;
            --info-light: #cffafe;
            --info-dark: #0891b2;
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);

            /* Layout Variables */
            --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
            --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0066cc;
            min-height: 100vh;
            margin: 0;
            position: relative;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: rgba(0, 51, 102, 0.95);
            backdrop-filter: blur(20px);
            color: white;
            padding: 30px 0;
            box-shadow: var(--shadow-heavy);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar a {
            display: block;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            font-weight: 500;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            margin: 8px 0;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .sidebar a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: var(--primary-gradient);
            transition: var(--transition);
            z-index: -1;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }

        .sidebar a:hover::before,
        .sidebar a.active::before {
            width: 100%;
        }

        .sidebar a:hover,
        .sidebar a.active {
            color: white;
            padding-left: 35px;
            transform: translateX(8px);
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }

        .topbar {
            margin-left: 250px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: var(--shadow-light);
        }

        .main-content {
            margin-left: 250px;
            padding: 40px 30px;
            max-width: calc(100% - 250px);
            overflow-x: auto;
        }

        .footer {
            text-align: center;
            margin-left: 250px;
            padding: 20px;
            font-size: 0.9rem;
            color: #666;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            position: fixed;
            bottom: 0;
            width: calc(100% - 250px);
            box-shadow: 0 -1px 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .btn-group-custom {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: var(--transition);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
        }

        .table {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .table thead th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
            position: relative;
        }

        .table tbody td {
            padding: 15px;
            border-color: rgba(0, 102, 204, 0.1);
            transition: var(--transition);
        }

        .table tbody tr:hover td {
            background: rgba(0, 102, 204, 0.05);
            transform: translateX(5px);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .topbar,
            .main-content,
            .footer {
                margin-left: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            .topbar {
                padding: 15px 20px;
            }

            .main-content {
                padding: 20px 15px;
            }

            .btn-group-custom {
                justify-content: center;
                flex-direction: column;
                align-items: stretch;
            }

            .btn-group-custom .btn {
                margin-bottom: 10px;
            }
        }

        .modal-xl {
            max-width: 95%;
        }

        #attendanceTableAll {
            min-width: 1000px;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" tabindex="0">
        <div class="text-center mb-4">
            <h4><?php echo $user_role === 'admin' ? '👨‍💼 Admin' : '👨‍🏫 Lecturer'; ?></h4>
            <hr style="border-color: #ffffff66;" />
        </div>
        <?php if ($user_role === 'admin') : ?>
            <a href="admin-dashboard.php">Dashboard</a>
            <a href="manage-users.php">Manage Users</a>
            <a href="manage-departments.php">Manage Departments</a>
            <a href="admin-reports.php">Reports</a>
            <a href="attendance-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Attendance Reports</a>
            <a href="system-logs.php">System Logs</a>
            <a href="index.php">Logout</a>
        <?php else : ?>
            <a href="lecturer-dashboard.php">Dashboard</a>
            <a href="lecturer-my-courses.php">My Courses</a>
            <a href="attendance-session.php">Attendance Session</a>
            <a href="attendance-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Attendance Reports</a>
            <a href="leave-requests.php">Leave Requests</a>
            <a href="index.php">Logout</a>
        <?php endif; ?>
    </div>

    <!-- Topbar -->
    <div class="topbar">
        <h5 class="m-0 fw-bold">Attendance Reports</h5>
        <span>RP Attendance System | <?php echo $user_role === 'admin' ? 'Admin' : 'Lecturer'; ?> Panel</span>
    </div>

    <!-- Main Content -->
    <div class="main-content container-fluid">
        <!-- Buttons -->
        <div class="btn-group-custom">
            <button id="printReport" class="btn btn-outline-primary">
                <i class="fas fa-print me-2"></i> Print Report
            </button>
            <?php if (!empty($attendanceData)) : ?>
                <a href="?class_id=<?= urlencode($selectedClassId) ?>&course_id=<?= urlencode($selectedCourseId) ?>&start_date=<?= urlencode($startDate ?? '') ?>&end_date=<?= urlencode($endDate ?? '') ?>&export=csv" class="btn btn-success">
                    <i class="fas fa-download me-2"></i> Export CSV
                </a>
            <?php endif; ?>
            <button id="viewAllAttendanceBtn" class="btn btn-info">
                <i class="fas fa-list me-2"></i> View All Attendance Details
            </button>
        </div>

        <!-- Filter Section -->
        <form id="filterForm" method="GET" class="row g-3 mb-4 align-items-end">
            <div class="col-md-4">
                <label for="class_id" class="form-label">Select Class</label>
                <select id="class_id" name="class_id" class="form-select" onchange="this.form.submit()" required>
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($classes as $class) : ?>
                        <option value="<?= $class['id'] ?>" <?= (isset($_GET['class_id']) && $_GET['class_id'] == $class['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($courses)) : ?>
                <div class="col-md-3">
                    <label for="course_id" class="form-label">Select Course</label>
                    <select id="course_id" name="course_id" class="form-select" required>
                        <option value="">-- Choose Course --</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?= $course['id'] ?>" <?= (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate ?? '') ?>">
                </div>
                <div class="col-md-2 d-flex justify-content-md-start justify-content-center align-items-end">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto">View Report</button>
                </div>
            <?php endif; ?>
        </form>

        <!-- Summary Statistics -->
        <?php if (!empty($attendanceData)) : ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><?= count($attendanceData) ?></h5>
                            <p class="card-text">Total Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-success">
                                <?= count(array_filter($attendanceData, fn($r) => $r['attendance_percent'] >= 85)) ?>
                            </h5>
                            <p class="card-text">Allowed to Exam</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-danger">
                                <?= count(array_filter($attendanceData, fn($r) => $r['attendance_percent'] < 85)) ?>
                            </h5>
                            <p class="card-text">Not Allowed to Exam</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-info">
                                <?= round(array_sum(array_column($attendanceData, 'attendance_percent')) / count($attendanceData), 1) ?>%
                            </h5>
                            <p class="card-text">Average Attendance</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Attendance Report Table -->
        <?php if (!empty($attendanceData)) : ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Student Name</th>
                            <th>Attendance %</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceData as $record) :
                            $statusClass = $record['attendance_percent'] >= 85 ? 'text-success' : 'text-danger';
                            $statusText = $record['attendance_percent'] >= 85 ? 'Allowed' : 'Not Allowed';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($record['student']) ?></td>
                                <td><?= $record['attendance_percent'] ?>%</td>
                                <td class="<?= $statusClass ?> fw-bold"><?= $statusText ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (isset($_GET['course_id'])) : ?>
            <p class="text-center text-muted">No attendance data available for this course.</p>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; <?= date('Y') ?> Rwanda Polytechnic | <?php echo $user_role === 'admin' ? 'Admin Panel' : 'Lecturer Panel'; ?>
    </div>

    <!-- Modal: All Attendance Details -->
    <div class="modal fade" id="attendanceDetailsModal" tabindex="-1" aria-labelledby="attendanceDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-height: 90vh; overflow-y: auto;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attendanceDetailsLabel">All Students Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="allAttendanceDetailsBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printAllDetailsBtn">
                        <i class="fas fa-print me-2"></i> Print Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
    <script>
        const attendanceDetailsData = <?= json_encode($attendanceDetailsData); ?>;

        function getAllDates(data) {
            const datesSet = new Set();
            for (const student in data) {
                Object.keys(data[student]).forEach(date => datesSet.add(date));
            }
            return Array.from(datesSet).sort();
        }

        function calculateAttendancePercent(attendanceObj) {
            const total = Object.keys(attendanceObj).length;
            const presentCount = Object.values(attendanceObj).filter(status => status === 'present').length;
            return total === 0 ? 0 : (presentCount / total) * 100;
        }

        const modal = new bootstrap.Modal(document.getElementById('attendanceDetailsModal'));
        const modalBody = document.getElementById('allAttendanceDetailsBody');

        document.getElementById('viewAllAttendanceBtn').addEventListener('click', () => {
            modalBody.innerHTML = '';
            const allDates = getAllDates(attendanceDetailsData);

            const table = document.createElement('table');
            table.id = "attendanceTableAll";
            table.className = 'table table-bordered table-hover table-sm';

            const thead = document.createElement('thead');
            thead.innerHTML = `<tr><th>Student Name</th><th>Decision</th>${allDates.map(date => `<th>${date}</th>`).join('')}</tr>`;
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            for (const student in attendanceDetailsData) {
                const attendance = attendanceDetailsData[student];
                const percent = calculateAttendancePercent(attendance);

                let row = `<td>${student}</td>`;
                row += percent < 85 ?
                    `<td><span class="badge bg-danger">Not Allowed to Do Exam</span></td>` :
                    `<td><span class="badge bg-success">Allowed</span></td>`;

                allDates.forEach(date => {
                    const status = attendance[date];
                    row += status === 'present' ?
                        `<td><span class="badge bg-success">Present</span></td>` :
                        status === 'absent' ?
                        `<td><span class="badge bg-danger">Absent</span></td>` :
                        `<td><span class="text-muted">-</span></td>`;
                });

                const tr = document.createElement('tr');
                tr.innerHTML = row;
                tbody.appendChild(tr);
            }

            table.appendChild(tbody);
            modalBody.appendChild(table);
            modal.show();
        });

        document.getElementById('printReport').addEventListener('click', () => window.print());

        document.getElementById('printAllDetailsBtn').addEventListener('click', () => {
            const printContents = document.getElementById('allAttendanceDetailsBody').innerHTML;
            const printWindow = window.open('', '', 'width=900,height=600');
            printWindow.document.write(`
                <html>
                  <head>
                    <title>Print Attendance Details</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
                  </head>
                  <body>
                    <h3 class="text-center mb-4">All Students Attendance Details</h3>
                    ${printContents}
                  </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();

            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 300);
        });
    </script>
</body>
</html>
