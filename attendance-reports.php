<?php
session_start();
require_once "config.php"; // $pdo connection
require_once "session_check.php";
require_role(['lecturer', 'hod', 'admin']);

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
            WHERE u.id = :user_id AND u.role IN ('lecturer', 'hod')
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
                email, 7, u.role, '12345'
            FROM users u
            WHERE id = :user_id AND role IN ('lecturer', 'hod')
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
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
            border-right: 1px solid rgba(0, 102, 204, 0.1);
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(0, 102, 204, 0.1);
        }

        .sidebar .logo {
            background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
            color: white;
            padding: 25px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .sidebar .logo::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="20" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
            pointer-events: none;
        }

        .sidebar .logo h4 {
            color: white;
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .sidebar .logo hr {
            border-color: rgba(255, 255, 255, 0.3);
            margin: 15px 0;
        }

        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .sidebar-nav .nav-section {
            padding: 15px 20px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(0, 102, 204, 0.1);
            margin-bottom: 10px;
        }

        .sidebar-nav a {
            display: block;
            padding: 14px 25px;
            color: #495057;
            text-decoration: none;
            border-radius: 0 25px 25px 0;
            margin: 0 0 2px 0;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover {
            background: rgba(0, 102, 204, 0.08);
            color: #0066cc;
            border-left-color: #0066cc;
            transform: translateX(8px);
            box-shadow: 2px 0 8px rgba(0, 102, 204, 0.15);
        }

        .sidebar-nav a.active {
            background: linear-gradient(90deg, rgba(0, 102, 204, 0.15) 0%, rgba(0, 102, 204, 0.05) 100%);
            color: #0066cc;
            border-left-color: #0066cc;
            box-shadow: 2px 0 12px rgba(0, 102, 204, 0.2);
            font-weight: 600;
        }

        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: #0066cc;
            border-radius: 0 2px 2px 0;
        }

        .sidebar-nav a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sidebar-nav .mt-4 {
            margin-top: 2rem !important;
            padding-top: 2rem !important;
            border-top: 1px solid rgba(0, 102, 204, 0.1);
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

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1003;
            background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            box-shadow: 0 4px 20px rgba(0, 102, 204, 0.3);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: var(--transition);
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                width: 260px;
                z-index: 1002;
            }

            .sidebar.show {
                transform: translateX(0);
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            }

            .sidebar.show::after {
                content: '';
                position: fixed;
                top: 0;
                left: 260px;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
                z-index: -1;
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

            .mobile-menu-toggle {
                display: block !important;
            }

            .btn-group-custom {
                justify-content: center;
                flex-direction: column;
                align-items: stretch;
            }

            .btn-group-custom .btn {
                margin-bottom: 10px;
            }

            .sidebar-nav a {
                padding: 16px 20px;
                font-size: 0.95rem;
            }

            .sidebar-nav .nav-section {
                padding: 12px 20px 8px;
                font-size: 0.7rem;
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
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <h4><?php echo $user_role === 'admin' ? '👨‍💼 Admin' : '👨‍🏫 Lecturer'; ?></h4>
            <small>RP Attendance System</small>
        </div>

        <ul class="sidebar-nav">
            <li class="nav-section">
                <i class="fas fa-th-large me-2"></i>Main Dashboard
            </li>
            <?php if ($user_role === 'admin') : ?>
                <li>
                    <a href="admin-dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard Overview
                    </a>
                </li>

                <li class="nav-section">
                    <i class="fas fa-users me-2"></i>User Management
                </li>
                <li>
                    <a href="manage-users.php">
                        <i class="fas fa-users-cog"></i>Manage Users
                    </a>
                </li>
                <li>
                    <a href="register-student.php">
                        <i class="fas fa-user-plus"></i>Register Student
                    </a>
                </li>

                <li class="nav-section">
                    <i class="fas fa-sitemap me-2"></i>Organization
                </li>
                <li>
                    <a href="manage-departments.php">
                        <i class="fas fa-building"></i>Departments
                    </a>
                </li>
                <li>
                    <a href="assign-hod.php">
                        <i class="fas fa-user-tie"></i>Assign HOD
                    </a>
                </li>

                <li class="nav-section">
                    <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
                </li>
                <li>
                    <a href="admin-reports.php">
                        <i class="fas fa-chart-line"></i>Analytics Reports
                    </a>
                </li>
                <li>
                    <a href="attendance-reports.php" class="active">
                        <i class="fas fa-calendar-check"></i>Attendance Reports
                    </a>
                </li>

                <li class="nav-section">
                    <i class="fas fa-cog me-2"></i>System
                </li>
                <li>
                    <a href="system-logs.php">
                        <i class="fas fa-file-code"></i>System Logs
                    </a>
                </li>
                <li>
                    <a href="hod-leave-management.php">
                        <i class="fas fa-clipboard-list"></i>Leave Management
                    </a>
                </li>
            <?php else : ?>
                <li>
                    <a href="lecturer-dashboard.php">
                        <i class="fas fa-tachometer-alt"></i>Dashboard
                    </a>
                </li>

                <li class="nav-section">
                    <i class="fas fa-graduation-cap me-2"></i>Academic
                </li>
                <li>
                    <a href="lecturer-my-courses.php">
                        <i class="fas fa-book"></i>My Courses
                    </a>
                </li>
                <li>
                    <a href="attendance-session.php">
                        <i class="fas fa-video"></i>Attendance Session
                    </a>
                </li>
                <li>
                    <a href="attendance-reports.php" class="active">
                        <i class="fas fa-chart-bar"></i>Attendance Reports
                    </a>
                </li>

                <li class="nav-section">
                    <i class="fas fa-cog me-2"></i>Management
                </li>
                <li>
                    <a href="leave-requests.php">
                        <i class="fas fa-clipboard-list"></i>Leave Requests
                    </a>
                </li>
            <?php endif; ?>

            <li class="nav-section">
                <i class="fas fa-sign-out-alt me-2"></i>Account
            </li>
            <li>
                <a href="logout.php" class="text-danger">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </li>
        </ul>
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
        // Mobile sidebar toggle functions
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
            }
        });

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
