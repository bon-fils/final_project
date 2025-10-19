<?php
/**
 * Admin Attendance Reports - Clean Design Version
 * Professional attendance reporting system for administrators
 * Clean, modern design without gradients
 */

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
                // Get comprehensive statistics from database
                $stmt = $pdo->query("
                    SELECT
                        (SELECT COUNT(*) FROM students WHERE status = 'active') AS total_students,
                        (SELECT COUNT(*) FROM attendance_sessions WHERE DATE(session_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS total_sessions,
                        (SELECT COUNT(*) FROM leave_requests WHERE status='pending') AS pending_leaves,
                        (SELECT COUNT(DISTINCT student_id) FROM attendance_records WHERE DATE(recorded_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS active_students,
                        (SELECT
                            CASE
                                WHEN (SELECT COUNT(*) FROM attendance_records WHERE DATE(recorded_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) > 0
                                THEN ROUND(((SELECT COUNT(*) FROM attendance_records WHERE status='present' AND DATE(recorded_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) * 100.0) /
                                          (SELECT COUNT(*) FROM attendance_records WHERE DATE(recorded_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 1)
                                ELSE 0
                            END
                        ) AS avg_attendance,
                        (SELECT COUNT(*) FROM lecturers WHERE status = 'active') AS total_lecturers,
                        (SELECT COUNT(*) FROM departments) AS total_departments
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'total_students' => (int)($stats['total_students'] ?? 0),
                    'total_sessions' => (int)($stats['total_sessions'] ?? 0),
                    'active_students' => (int)($stats['active_students'] ?? 0),
                    'avg_attendance' => (float)($stats['avg_attendance'] ?? 0),
                    'pending_leaves' => (int)($stats['pending_leaves'] ?? 0),
                    'total_lecturers' => (int)($stats['total_lecturers'] ?? 0),
                    'total_departments' => (int)($stats['total_departments'] ?? 0)
                ]);
            } catch (PDOException $e) {
                error_log("Reports stats error: " . $e->getMessage());
                echo json_encode([
                    'total_students' => 0,
                    'total_sessions' => 0,
                    'active_students' => 0,
                    'avg_attendance' => 0,
                    'pending_leaves' => 0,
                    'total_lecturers' => 0,
                    'total_departments' => 0
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

        case 'get_lecturers':
            try {
                $optionId = $_GET['option_id'] ?? 0;
                if ($optionId) {
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT l.id, CONCAT(l.first_name, ' ', l.last_name) as name
                        FROM lecturers l
                        INNER JOIN lecturer_departments ld ON l.id = ld.lecturer_id
                        INNER JOIN departments d ON ld.department_id = d.id
                        INNER JOIN options o ON d.id = o.department_id
                        WHERE o.id = ? AND l.status = 'active'
                        ORDER BY l.last_name, l.first_name
                    ");
                    $stmt->execute([$optionId]);
                } else {
                    $stmt = $pdo->query("
                        SELECT DISTINCT l.id, CONCAT(l.first_name, ' ', l.last_name) as name
                        FROM lecturers l
                        WHERE l.status = 'active'
                        ORDER BY l.last_name, l.first_name
                    ");
                }
                $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($lecturers);
            } catch (PDOException $e) {
                error_log("Get lecturers error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_lecturer_courses':
            try {
                $lecturerId = $_GET['lecturer_id'] ?? 0;
                if ($lecturerId) {
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT c.id, c.course_name as name, c.course_code
                        FROM courses c
                        INNER JOIN attendance_sessions asess ON c.id = asess.course_id
                        WHERE asess.lecturer_id = ? AND c.status = 'active'
                        ORDER BY c.course_name
                    ");
                    $stmt->execute([$lecturerId]);
                    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    echo json_encode($courses);
                } else {
                    echo json_encode([]);
                }
            } catch (PDOException $e) {
                error_log("Get lecturer courses error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_courses':
            try {
                $deptId = $_GET['department_id'] ?? 0;
                $optionId = $_GET['option_id'] ?? 0;

                if ($deptId && $optionId) {
                    // Get courses for specific department and option
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT c.id, c.course_name as name, c.course_code
                        FROM courses c
                        INNER JOIN course_assignments ca ON c.id = ca.course_id
                        WHERE c.department_id = ? AND ca.option_id = ?
                        ORDER BY c.course_name
                    ");
                    $stmt->execute([$deptId, $optionId]);
                } elseif ($deptId) {
                    // Get all courses for the department
                    $stmt = $pdo->prepare("
                        SELECT DISTINCT c.id, c.course_name as name, c.course_code
                        FROM courses c
                        WHERE c.department_id = ?
                        ORDER BY c.course_name
                    ");
                    $stmt->execute([$deptId]);
                } else {
                    // Get all courses
                    $stmt = $pdo->query("
                        SELECT DISTINCT c.id, c.course_name as name, c.course_code
                        FROM courses c
                        ORDER BY c.course_name
                    ");
                }
                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($courses);
            } catch (PDOException $e) {
                error_log("Get courses error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_paginated_reports':
            try {
                $page = (int)($_GET['page'] ?? 1);
                $limit = (int)($_GET['limit'] ?? 50);
                $offset = ($page - 1) * $limit;

                $filters = $_GET;
                unset($filters['ajax'], $filters['action'], $filters['page'], $filters['limit']);

                // Build WHERE conditions
                $whereConditions = [];
                $params = [];

                if (!empty($filters['department_id'])) {
                    $whereConditions[] = "s.department_id = ?";
                    $params[] = $filters['department_id'];
                }
                if (!empty($filters['course_id'])) {
                    $whereConditions[] = "ar.course_id = ?";
                    $params[] = $filters['course_id'];
                }
                if (!empty($filters['date_from'])) {
                    $whereConditions[] = "ar.attendance_date >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $whereConditions[] = "ar.attendance_date <= ?";
                    $params[] = $filters['date_to'];
                }

                $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

                // Get total count
                $countQuery = "
                    SELECT COUNT(DISTINCT ar.id) as total
                    FROM attendance_records ar
                    INNER JOIN students s ON ar.student_id = s.id
                    $whereClause
                ";
                $countStmt = $pdo->prepare($countQuery);
                $countStmt->execute($params);
                $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

                // Get paginated data
                $dataQuery = "
                    SELECT
                        s.first_name,
                        s.last_name,
                        s.registration_number,
                        d.name as department_name,
                        c.course_name,
                        ar.attendance_date,
                        ar.status,
                        ar.recorded_at
                    FROM attendance_records ar
                    INNER JOIN students s ON ar.student_id = s.id
                    LEFT JOIN departments d ON s.department_id = d.id
                    LEFT JOIN courses c ON ar.course_id = c.id
                    $whereClause
                    ORDER BY ar.attendance_date DESC, s.last_name, s.first_name
                    LIMIT ? OFFSET ?
                ";

                $params[] = $limit;
                $params[] = $offset;

                $dataStmt = $pdo->prepare($dataQuery);
                $dataStmt->execute($params);
                $data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

                $pages = ceil($total / $limit);

                echo json_encode([
                    'data' => $data,
                    'pagination' => [
                        'page' => $page,
                        'pages' => $pages,
                        'total' => $total,
                        'limit' => $limit
                    ]
                ]);
            } catch (PDOException $e) {
                error_log("Get paginated reports error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to load reports']);
            }
            exit;
    }
}

$user_id = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'admin';

// Get user information
try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.email, u.first_name, u.last_name
        FROM users u
        WHERE u.id = :user_id
    ");
    $stmt->execute(['user_id' => $user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        header("Location: login.php?error=user_not_found");
        exit;
    }

} catch (PDOException $e) {
    error_log("User info error: " . $e->getMessage());
    header("Location: login.php?error=database");
    exit;
}

// Load data from database
try {
    // Get departments
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get options/programs
    $stmt = $pdo->prepare("SELECT id, name, department_id FROM options ORDER BY name");
    $stmt->execute();
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get classes/year levels
    $classes = [
        ['id' => 1, 'name' => 'Year 1'],
        ['id' => 2, 'name' => 'Year 2'],
        ['id' => 3, 'name' => 'Year 3'],
        ['id' => 4, 'name' => 'Year 4']
    ];

    // Get all courses with proper field names
    $stmt = $pdo->query("SELECT id, course_name as name, course_code FROM courses ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error loading report data: " . $e->getMessage());
    $departments = [];
    $options = [];
    $classes = [];
    $courses = [];
    $user_context_error = "Failed to load report data. Please try again.";
}

// Get filter parameters
$reportType = $_GET['report_type'] ?? 'department';
$selectedDepartmentId = $_GET['department_id'] ?? null;
$selectedOptionId = $_GET['option_id'] ?? null;
$selectedClassId = $_GET['class_id'] ?? null;
$selectedCourseId = $_GET['course_id'] ?? null;
$selectedLecturerId = $_GET['lecturer_id'] ?? null;
$selectedLecturerDeptId = $_GET['lecturer_department_id'] ?? null;
$selectedLecturerOptionId = $_GET['lecturer_option_id'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Filter options based on selections
$filteredOptions = $selectedDepartmentId ? array_filter($options, fn($opt) => $opt['department_id'] == $selectedDepartmentId) : [];
$filteredCourses = $selectedClassId ? array_filter($courses, fn($course) => true) : $courses;

// Generate real attendance report data from database with proper grouping
function generateRealReport($report_type, $filters) {
    global $pdo;

    try {
        $whereConditions = [];
        $params = [];
        $groupBy = "";
        $orderBy = "";

        // Build WHERE conditions and grouping based on report type
        switch ($report_type) {
            case 'department':
                if (!empty($filters['department_id'])) {
                    $whereConditions[] = "s.department_id = ?";
                    $params[] = $filters['department_id'];
                }
                $groupBy = "d.name, s.last_name, s.first_name";
                $orderBy = "d.name ASC, s.last_name ASC, s.first_name ASC, asess.session_date ASC";
                break;
            case 'option':
                if (!empty($filters['option_id'])) {
                    $whereConditions[] = "s.option_id = ?";
                    $params[] = $filters['option_id'];
                }
                $groupBy = "o.name, s.last_name, s.first_name";
                $orderBy = "o.name ASC, s.last_name ASC, s.first_name ASC, asess.session_date ASC";
                break;
            case 'lecturer':
                if (!empty($filters['lecturer_id'])) {
                    $whereConditions[] = "asess.lecturer_id = ?";
                    $params[] = $filters['lecturer_id'];
                }
                if (!empty($filters['course_id'])) {
                    $whereConditions[] = "asess.course_id = ?";
                    $params[] = $filters['course_id'];
                }
                $groupBy = "l.last_name, l.first_name, c.course_name, s.last_name, s.first_name";
                $orderBy = "l.last_name ASC, l.first_name ASC, c.course_name ASC, s.last_name ASC, s.first_name ASC, asess.session_date ASC";
                break;
        }

        // Add date range conditions
        if (!empty($filters['start_date'])) {
            $whereConditions[] = "asess.session_date >= ?";
            $params[] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $whereConditions[] = "asess.session_date <= ?";
            $params[] = $filters['end_date'];
        }

        $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

        // Get attendance data with proper joins and grouping
        $query = "
            SELECT
                s.id as student_id,
                s.first_name,
                s.last_name,
                s.registration_number,
                s.year_level,
                d.name as department_name,
                o.name as option_name,
                c.course_name,
                c.course_code,
                l.first_name as lecturer_first_name,
                l.last_name as lecturer_last_name,
                asess.id as session_id,
                asess.session_date,
                asess.start_time,
                asess.end_time,
                ar.status,
                ar.recorded_at,
                ar.id as record_id
            FROM students s
            LEFT JOIN departments d ON s.department_id = d.id
            LEFT JOIN options o ON s.option_id = o.id
            LEFT JOIN attendance_records ar ON s.id = ar.student_id
            LEFT JOIN attendance_sessions asess ON ar.session_id = asess.id
            LEFT JOIN courses c ON asess.course_id = c.id
            LEFT JOIN lecturers l ON asess.lecturer_id = l.id
            $whereClause
            ORDER BY $orderBy
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rawData)) {
            return ['students' => [], 'sessions' => [], 'attendance' => [], 'summary' => []];
        }

        // Process data into structured format with proper grouping
        $students = [];
        $sessions = [];
        $attendance = [];
        $groupingData = [];

        foreach ($rawData as $row) {
            $studentId = $row['student_id'];
            $sessionId = $row['session_id'];

            // Create grouping key based on report type
            $groupKey = "";
            switch ($report_type) {
                case 'department':
                    $groupKey = $row['department_name'];
                    break;
                case 'option':
                    $groupKey = $row['option_name'];
                    break;
                case 'lecturer':
                    $groupKey = $row['lecturer_first_name'] . ' ' . $row['lecturer_last_name'];
                    if (!empty($filters['course_id'])) {
                        $groupKey .= ' - ' . $row['course_name'];
                    }
                    break;
            }

            if (!isset($groupingData[$groupKey])) {
                $groupingData[$groupKey] = [];
            }

            // Build student info
            if (!isset($students[$studentId])) {
                $students[$studentId] = [
                    'id' => $studentId,
                    'full_name' => $row['first_name'] . ' ' . $row['last_name'],
                    'reg_no' => $row['registration_number'],
                    'department_name' => $row['department_name'],
                    'option_name' => $row['option_name'],
                    'year_level' => $row['year_level'],
                    'group_key' => $groupKey
                ];
            }

            // Build session info
            if ($sessionId && !isset($sessions[$sessionId])) {
                $sessions[$sessionId] = [
                    'id' => $sessionId,
                    'course_id' => null,
                    'session_date' => $row['session_date'],
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'course_name' => $row['course_name'],
                    'course_code' => $row['course_code'],
                    'lecturer_name' => $row['lecturer_first_name'] . ' ' . $row['lecturer_last_name']
                ];
            }

            // Build attendance records
            if (!isset($attendance[$studentId])) {
                $attendance[$studentId] = [
                    'student_info' => $students[$studentId],
                    'sessions' => [],
                    'summary' => ['total_sessions' => 0, 'present_count' => 0, 'absent_count' => 0, 'excused_count' => 0, 'percentage' => 0]
                ];
            }

            if ($sessionId) {
                $status = $row['status'] ?? 'absent';
                $attendance[$studentId]['sessions'][$sessionId] = [
                    'session_info' => $sessions[$sessionId],
                    'status' => $status,
                    'recorded_at' => $row['recorded_at']
                ];

                $attendance[$studentId]['summary']['total_sessions']++;
                switch ($status) {
                    case 'present':
                        $attendance[$studentId]['summary']['present_count']++;
                        break;
                    case 'absent':
                        $attendance[$studentId]['summary']['absent_count']++;
                        break;
                    case 'excused':
                        $attendance[$studentId]['summary']['excused_count']++;
                        break;
                }
            }

            // Add to grouping data
            if (!isset($groupingData[$groupKey][$studentId])) {
                $groupingData[$groupKey][$studentId] = $attendance[$studentId];
            }
        }

        // Calculate percentages
        foreach ($attendance as $studentId => $data) {
            if ($data['summary']['total_sessions'] > 0) {
                $attendance[$studentId]['summary']['percentage'] = round(
                    ($data['summary']['present_count'] / $data['summary']['total_sessions']) * 100,
                    1
                );
            }
        }

        // Calculate summary statistics
        $summary = calculateRealSummary($attendance);

        return [
            'students' => $students,
            'courses' => [],
            'sessions' => $sessions,
            'attendance' => $attendance,
            'grouping_data' => $groupingData,
            'summary' => $summary,
            'date_range' => ['start' => $filters['start_date'], 'end' => $filters['end_date']],
            'report_type' => $report_type
        ];

    } catch (PDOException $e) {
        error_log("Real report generation error: " . $e->getMessage());
        return ['error' => 'Failed to generate report from database'];
    }
}

function calculateRealSummary($attendanceData) {
    $totalStudents = count($attendanceData);
    $totalSessions = 0;
    $totalPresent = 0;
    $totalAbsent = 0;
    $totalExcused = 0;
    $studentsAbove85 = 0;
    $studentsBelow85 = 0;
    $perfectAttendance = 0;
    $zeroAttendance = 0;
    $totalPercentage = 0;

    foreach ($attendanceData as $studentData) {
        $summary = $studentData['summary'];
        $totalSessions += $summary['total_sessions'];
        $totalPresent += $summary['present_count'];
        $totalAbsent += $summary['absent_count'];
        $totalExcused += $summary['excused_count'];
        $percentage = $summary['percentage'];
        $totalPercentage += $percentage;

        if ($percentage >= 85) $studentsAbove85++;
        else $studentsBelow85++;

        if ($percentage == 100) $perfectAttendance++;
        elseif ($percentage == 0) $zeroAttendance++;
    }

    return [
        'total_students' => $totalStudents,
        'total_sessions' => $totalSessions,
        'total_possible_attendances' => $totalStudents * $totalSessions,
        'total_actual_attendances' => $totalPresent,
        'total_absent' => $totalAbsent,
        'total_excused' => $totalExcused,
        'average_attendance_rate' => $totalStudents > 0 ? round($totalPercentage / $totalStudents, 1) : 0,
        'students_above_85_percent' => $studentsAbove85,
        'students_below_85_percent' => $studentsBelow85,
        'perfect_attendance' => $perfectAttendance,
        'zero_attendance' => $zeroAttendance
    ];
}

// Generate real report data
$report_data = [];
$filters = [
    'department_id' => $selectedDepartmentId,
    'option_id' => $selectedOptionId,
    'class_id' => $selectedClassId,
    'course_id' => $selectedCourseId,
    'lecturer_id' => $selectedLecturerId,
    'lecturer_department_id' => $selectedLecturerDeptId,
    'lecturer_option_id' => $selectedLecturerOptionId,
    'start_date' => $startDate,
    'end_date' => $endDate
];

$hasRequiredFilters = false;
switch ($reportType) {
    case 'department':
        $hasRequiredFilters = !empty($selectedDepartmentId);
        break;
    case 'option':
        $hasRequiredFilters = !empty($selectedOptionId);
        break;
    case 'lecturer':
        $hasRequiredFilters = !empty($selectedLecturerId);
        break;
}

if ($hasRequiredFilters) {
    try {
        $report_data = generateRealReport($reportType, $filters);
    } catch (Exception $e) {
        error_log("Report generation error: " . $e->getMessage());
        $report_data = ['error' => 'Failed to generate report. Please try again.'];
    }
}

// Legacy variables for backward compatibility
$attendanceData = [];
$attendanceDetailsData = [];

if (!isset($report_data['error']) && !empty($report_data['attendance'])) {
    foreach ($report_data['attendance'] as $student_id => $data) {
        $attendanceData[] = [
            'student' => $data['student_info']['full_name'],
            'attendance_percent' => $data['summary']['percentage']
        ];

        // Build details data for modal
        foreach ($data['sessions'] as $session_id => $session_data) {
            $session_date = date('Y-m-d', strtotime($session_data['session_info']['session_date']));
            $attendanceDetailsData[$data['student_info']['full_name']][$session_date] = $session_data['status'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Attendance Report | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="css/attendance-reports.css" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #000000;
            --primary-dark: #000000;
            --primary-light: #ffffff;
            --success-color: #000000;
            --success-light: #ffffff;
            --success-dark: #000000;
            --danger-color: #000000;
            --danger-light: #ffffff;
            --danger-dark: #000000;
            --warning-color: #000000;
            --warning-light: #ffffff;
            --warning-dark: #000000;
            --info-color: #000000;
            --info-light: #ffffff;
            --info-dark: #000000;
            --light-bg: #ffffff;
            --dark-bg: #000000;
            --shadow-light: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-heavy: 0 10px 15px rgba(0,0,0,0.1);
            --border-radius: 8px;
            --border-radius-sm: 6px;
            --border-radius-lg: 12px;
            --transition: all 0.2s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            min-height: 100vh;
            color: #000000;
        }

        /* All text must be black for better readability */
        .text-primary, .text-success, .text-danger, .text-warning, .text-info,
        .sidebar-nav a, .sidebar-nav .nav-section, .form-label, .stat-label,
        .page-header p, .course-info p, .table thead th, .table tbody td,
        .sidebar .logo h4, .sidebar .logo small, .sidebar-nav a, .sidebar-nav .nav-section,
        .topbar, .main-content, .footer, .card, .btn, .alert, .modal-content,
        .stat-value, .stat-label, .status-badge, .btn-primary, .btn-success,
        .btn-info, .btn-danger, .btn-warning, .form-select, .form-control,
        .table tbody td, .table thead th, .modal-header, .modal-body {
            color: #000000 !important;
        }

        /* Remove background colors from form elements */
        .form-select, .form-control {
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        /* Ensure select options are black */
        .form-select option {
            color: #000000 !important;
            background-color: #ffffff !important;
        }

        /* Remove all background gradients and colors */
        .sidebar .logo, .sidebar-nav a:hover, .sidebar-nav a.active,
        .btn-primary, .btn-success, .btn-info, .btn-danger, .btn-warning,
        .modal-header, .alert-info, .stat-icon, .status-allowed, .status-not-allowed,
        .attendance-high, .attendance-medium, .attendance-low, .mobile-menu-toggle,
        .sidebar .logo::before, .dashboard-card::before, .stats-card::before,
        .alert::before, .alert-success::before, .alert-danger::before,
        .alert-warning::before, .alert-info::before {
            background: transparent !important;
            background-color: transparent !important;
            background-image: none !important;
        }

        /* Ensure all backgrounds are white */
        .sidebar, .topbar, .main-content, .footer, .card, .table,
        .filter-section, .stat-card, .modal-content, .alert, .btn {
            background-color: #ffffff !important;
        }
            background-color: #ffffff !important;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #003366 0%, #0066cc 100%);
            color: white;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar .logo {
            background: #ffffff;
            color: #000000;
            padding: 25px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            border-bottom: 1px solid #000000;
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

        .sidebar .logo h3 {
            color: white;
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .sidebar .logo small {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
            font-weight: 500;
            position: relative;
            z-index: 2;
        }

        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .sidebar-nav li {
            margin: 2px 0;
        }

        .sidebar-nav .nav-section {
            padding: 15px 20px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border-color);
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
            background: #f0f0f0;
            color: #000000;
            border-left-color: #000000;
            transform: translateX(8px);
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
        }

        .sidebar-nav a.active {
            background: #f0f0f0;
            color: #000000;
            border-left-color: #000000;
            box-shadow: 2px 0 12px rgba(0, 0, 0, 0.2);
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
            background: var(--primary-color);
            border-radius: 0 2px 2px 0;
        }

        .sidebar-nav a i {
            margin-right: 12px;
            width: 18px;
            text-align: center;
            font-size: 1rem;
        }

        .topbar {
            margin-left: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: var(--transition);
        }

        .main-content {
            margin-left: 280px;
            margin-top: 70px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }

        .footer {
            text-align: center;
            margin-left: 280px;
            padding: 20px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            background: white;
            border-top: 1px solid var(--border-color);
            position: fixed;
            bottom: 0;
            width: calc(100% - 280px);
            box-shadow: 0 -1px 3px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }

        .card-header {
            background: var(--card-background);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 24px;
            font-weight: 600;
            color: var(--text-primary);
        }

        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            padding: 10px 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: none;
            font-size: 0.9rem;
            letter-spacing: 0.025em;
        }

        .btn-primary {
            background-color: #ffffff;
            color: #000000;
            border: 1px solid #000000;
        }

        .btn-primary:hover {
            background-color: #f0f0f0;
            color: #000000;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .btn-success {
            background-color: #ffffff;
            color: #000000;
            border: 1px solid #000000;
        }

        .btn-info {
            background-color: #ffffff;
            color: #000000;
            border: 1px solid #000000;
        }

        .btn-danger {
            background-color: #ffffff;
            color: #000000;
            border: 1px solid #000000;
        }

        .table {
            background: var(--card-background);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        .table thead th {
            background: var(--card-background);
            color: var(--text-primary);
            border: none;
            font-weight: 600;
            padding: 16px;
            position: relative;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
            text-transform: uppercase;
        }

        .table thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background-color: var(--border-color);
        }

        .table tbody td {
            padding: 16px;
            border-color: var(--border-color);
            transition: var(--transition);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background-color: var(--primary-light);
        }

        .table tbody tr:hover td {
            border-color: var(--border-color);
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1003;
            background-color: #ffffff;
            color: #000000;
            border: 1px solid #000000;
            border-radius: var(--border-radius-sm);
            padding: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: var(--transition);
        }

        .mobile-menu-toggle:hover {
            background-color: #f0f0f0;
            transform: scale(1.05);
        }

        .mobile-menu-toggle:active {
            transform: scale(0.95);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                width: 280px;
                z-index: 1002;
            }

            .sidebar.show {
                transform: translateX(0);
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            }

            .sidebar.show::after {
                content: '';
                position: fixed;
                top: 0;
                left: 280px;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
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
                padding: 16px 20px;
                margin-left: 0;
            }

            .main-content {
                padding: 20px 16px;
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block !important;
            }

            .btn-group-custom {
                justify-content: center;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .btn-group-custom .btn {
                margin-bottom: 0;
                width: 100%;
            }

            .sidebar-nav a {
                padding: 16px 20px;
                font-size: 1rem;
                border-radius: 0 6px 6px 0;
            }

            .sidebar-nav .nav-section {
                padding: 12px 20px 8px;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px 12px;
            }

            .topbar {
                padding: 14px 16px;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 8px;
                font-size: 0.8rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .btn {
                padding: 10px 16px;
                font-size: 0.85rem;
            }
        }

        .modal-xl {
            max-width: 95%;
            margin: 1rem auto;
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-heavy);
        }

        .modal-header {
            background: #ffffff;
            color: #000000;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-bottom: 1px solid #000000;
            padding: 20px 24px;
        }

        .modal-body {
            padding: 24px;
        }

        #attendanceTableAll {
            min-width: 1000px;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-select, .form-control {
            padding: 10px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-select:focus, .form-control:focus {
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
            outline: none;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 12px;
            background-color: #f0f0f0;
            color: #000000;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* Attendance Bar */
        .attendance-bar {
            height: 6px;
            background-color: #e5e7eb;
            border-radius: 3px;
            overflow: hidden;
            margin: 6px 0;
        }

        .attendance-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.6s ease;
        }

        .attendance-high {
            background-color: #e0e0e0;
        }

        .attendance-medium {
            background-color: #d0d0d0;
        }

        .attendance-low {
            background-color: #c0c0c0;
        }

        /* Status Badges */
        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-allowed {
            background-color: #f0f0f0;
            color: #000000;
            border: 1px solid #000000;
        }

        .status-not-allowed {
            background-color: #e0e0e0;
            color: #000000;
            border: 1px solid #000000;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid var(--border-color);
        }

        .page-header h2 {
            color: var(--text-primary);
            font-weight: 700;
            margin: 0;
            font-size: 1.5rem;
        }

        .page-header p {
            color: var(--text-secondary);
            margin: 6px 0 0 0;
            font-size: 0.9rem;
        }

        /* Course Info */
        .course-info {
            background: var(--card-background);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .course-info h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 12px;
        }

        .course-info p {
            margin-bottom: 6px;
            font-size: 0.875rem;
        }

        .course-info strong {
            color: var(--text-primary);
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }

        .loading-overlay.d-none {
            opacity: 0;
            pointer-events: none;
        }

        /* System Status Notice */
        .alert-info {
            border: none;
            background-color: #f0f0f0;
            color: #000000;
            border-radius: var(--border-radius);
            border-left: 4px solid #000000;
        }
    </style>
</head>

<body>
    <!-- System Status Notice -->
    <div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
        <i class="fas fa-info-circle me-2"></i><strong>Admin Panel:</strong> Attendance Reports System Active
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay d-none" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2">Generating attendance report...</div>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Admin Sidebar -->
    <?php include 'includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h2><i class="fas fa-chart-bar me-3"></i>Admin Attendance Report</h2>
                <p>Comprehensive attendance analytics and reporting for administrators</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($report_data) && !isset($report_data['error'])) : ?>
                <div class="export-buttons">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success btn-sm">
                        <i class="fas fa-file-csv me-1"></i>CSV
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-info btn-sm">
                        <i class="fas fa-file-excel me-1"></i>Excel
                    </a>
                    <button onclick="window.print()" class="btn btn-primary btn-sm">
                        <i class="fas fa-print me-1"></i>Print
                    </button>
                    <button onclick="exportToPDF()" class="btn btn-danger btn-sm">
                        <i class="fas fa-file-pdf me-1"></i>PDF
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Error Display -->
        <?php if (isset($user_context_error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>System Error:</strong> <?= htmlspecialchars($user_context_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php else: ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-row">
                <div>
                    <label class="form-label fw-bold">Report Type</label>
                    <select name="report_type" class="form-select" onchange="updateFilters(this.value)">
                        <option value="department" <?= ($reportType == 'department') ? 'selected' : '' ?>>🏢 Department Report</option>
                        <option value="option" <?= ($reportType == 'option') ? 'selected' : '' ?>>📚 Program/Option Report</option>
                        <option value="lecturer" <?= ($reportType == 'lecturer') ? 'selected' : '' ?>>👨‍🏫 Lecturer Report</option>
                    </select>
                </div>

                <div id="departmentFilter" style="display: <?= ($reportType == 'department' || $reportType == 'option') ? 'block' : 'none' ?>">
                    <label class="form-label fw-bold">Department</label>
                    <select name="department_id" class="form-select" onchange="this.form.submit()">
                        <option value="">🏢 Select Department</option>
                        <?php foreach ($departments as $dept) : ?>
                            <option value="<?= $dept['id'] ?>" <?= ($selectedDepartmentId == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="optionFilter" style="display: <?= ($reportType == 'option') ? 'block' : 'none' ?>">
                    <label class="form-label fw-bold">Program/Option</label>
                    <select name="option_id" class="form-select">
                        <option value="">📚 Select Program</option>
                        <?php
                        $filteredOptions = $selectedDepartmentId ? array_filter($options, fn($opt) => $opt['department_id'] == $selectedDepartmentId) : [];
                        foreach ($filteredOptions as $opt) : ?>
                            <option value="<?= $opt['id'] ?>" <?= ($selectedOptionId == $opt['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="lecturerDeptFilter" style="display: <?= ($reportType == 'lecturer') ? 'block' : 'none' ?>">
                    <label class="form-label fw-bold">Department</label>
                    <select name="lecturer_department_id" class="form-select" onchange="loadLecturerOptions(this.value)">
                        <option value="">🏢 Select Department</option>
                        <?php foreach ($departments as $dept) : ?>
                            <option value="<?= $dept['id'] ?>" <?= ($selectedLecturerDeptId == $dept['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="lecturerOptionFilter" style="display: <?= ($reportType == 'lecturer') ? 'block' : 'none' ?>">
                    <label class="form-label fw-bold">Program/Option</label>
                    <select name="lecturer_option_id" class="form-select" onchange="loadLecturersForOption(this.value)">
                        <option value="">📚 Select Program</option>
                        <!-- Options will be loaded when department is selected -->
                    </select>
                </div>

                <div id="lecturerFilter" style="display: <?= ($reportType == 'lecturer') ? 'block' : 'none' ?>">
                    <label class="form-label fw-bold">Lecturer</label>
                    <select name="lecturer_id" class="form-select" onchange="loadLecturerCourses(this.value)">
                        <option value="">👨‍🏫 Select Lecturer</option>
                        <!-- Lecturers will be loaded when option is selected -->
                    </select>
                </div>

                <div id="courseFilter" style="display: <?= ($reportType == 'lecturer') ? 'block' : 'none' ?>">
                    <label class="form-label fw-bold">Course (Optional)</label>
                    <select name="course_id" class="form-select">
                        <option value="">📖 All Courses</option>
                        <!-- Courses will be loaded when lecturer is selected -->
                    </select>
                </div>


                <div>
                    <label class="form-label fw-bold">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate ?? '') ?>">
                </div>

                <div>
                    <label class="form-label fw-bold">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate ?? '') ?>">
                </div>

                <div class="d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($report_data) && !isset($report_data['error'])) : ?>
            <!-- Report Information -->
            <div class="course-info">
                <div class="row">
                    <div class="col-md-6">
                        <h5><i class="fas fa-info-circle me-2"></i>Report Details</h5>
                        <?php if (isset($report_data['course_info'])) : ?>
                            <p><strong>Course:</strong> <?= htmlspecialchars($report_data['course_info']['course_name']) ?> (<?= htmlspecialchars($report_data['course_info']['course_code']) ?>)</p>
                            <p><strong>Department:</strong> <?= htmlspecialchars($report_data['course_info']['department_name']) ?></p>
                            <p><strong>Lecturer:</strong> <?= htmlspecialchars($report_data['course_info']['lecturer_name'] ?? 'Not Assigned') ?></p>
                        <?php elseif (isset($report_data['department_info'])) : ?>
                            <p><strong>Department:</strong> <?= htmlspecialchars($report_data['department_info']['name']) ?></p>
                            <p><strong>Report Type:</strong> Department-wide Attendance</p>
                            <p><strong>Courses Included:</strong> <?php echo isset($report_data['courses']) ? count($report_data['courses']) : 0; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Admin Panel
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const topbar = document.querySelector('.topbar');
            const mainContent = document.querySelector('.main-content');
            const footer = document.querySelector('.footer');

            sidebar.classList.toggle('show');
        }

        // Update filter visibility based on report type
        function updateFilters(reportType) {
            const departmentFilter = document.getElementById('departmentFilter');
            const optionFilter = document.getElementById('optionFilter');
            const lecturerDeptFilter = document.getElementById('lecturerDeptFilter');
            const lecturerOptionFilter = document.getElementById('lecturerOptionFilter');
            const lecturerFilter = document.getElementById('lecturerFilter');
            const courseFilter = document.getElementById('courseFilter');

            // Hide all filters first
            departmentFilter.style.display = 'none';
            optionFilter.style.display = 'none';
            lecturerDeptFilter.style.display = 'none';
            lecturerOptionFilter.style.display = 'none';
            lecturerFilter.style.display = 'none';
            courseFilter.style.display = 'none';

            // Show relevant filters based on report type
            switch(reportType) {
                case 'department':
                    departmentFilter.style.display = 'block';
                    break;
                case 'option':
                    departmentFilter.style.display = 'block';
                    optionFilter.style.display = 'block';
                    break;
                case 'lecturer':
                    lecturerDeptFilter.style.display = 'block';
                    lecturerOptionFilter.style.display = 'block';
                    lecturerFilter.style.display = 'block';
                    courseFilter.style.display = 'block';
                    break;
            }
        }


        // Load options for selected department
        function loadOptionsForDepartment(deptId) {
            const optionSelect = $('#optionFilter select');
            optionSelect.prop('disabled', true);

            if (!deptId) {
                optionSelect.empty().append('<option value="">📚 Select Program</option>');
                return;
            }

            $.getJSON('?ajax=1&action=get_options', {
                department_id: deptId
            }, function(data) {
                optionSelect.empty().append('<option value="">📚 Select Program</option>');
                data.forEach(function(option) {
                    optionSelect.append(`<option value="${option.id}">${option.name}</option>`);
                });
                optionSelect.prop('disabled', false);
            });
        }

        // Setup event listeners
        function setupEventListeners() {
            $('#deptFilter').change(function() {
                // Load options for selected department (for option reports)
                const deptId = $(this).val();
                loadOptionsForDepartment(deptId);
            });
        }

        // Load options for lecturer department
        function loadLecturerOptions(deptId) {
            const optionSelect = $('#lecturerOptionFilter select');
            optionSelect.prop('disabled', true);

            if (!deptId) {
                optionSelect.empty().append('<option value="">📚 Select Program</option>');
                return;
            }

            $.getJSON('?ajax=1&action=get_options', {
                department_id: deptId
            }, function(data) {
                optionSelect.empty().append('<option value="">📚 Select Program</option>');
                data.forEach(function(option) {
                    optionSelect.append(`<option value="${option.id}">${option.name}</option>`);
                });
                optionSelect.prop('disabled', false);
            });
        }

        // Load lecturers for selected option
        function loadLecturersForOption(optionId) {
            const lecturerSelect = $('#lecturerFilter select');
            lecturerSelect.prop('disabled', true);

            if (!optionId) {
                lecturerSelect.empty().append('<option value="">👨‍🏫 Select Lecturer</option>');
                return;
            }

            $.getJSON('?ajax=1&action=get_lecturers', {
                option_id: optionId
            }, function(response) {
                lecturerSelect.empty().append('<option value="">👨‍🏫 Select Lecturer</option>');

                if (response && response.length > 0) {
                    response.forEach(lecturer => {
                        lecturerSelect.append(`<option value="${lecturer.id}">${lecturer.name}</option>`);
                    });
                }
                lecturerSelect.prop('disabled', false);
            });
        }

        // Load courses for selected lecturer
        function loadLecturerCourses(lecturerId) {
            const courseSelect = $('#courseFilter select');
            courseSelect.prop('disabled', true);
            courseSelect.empty().append('<option value="">📖 All Courses</option>');

            if (!lecturerId) {
                courseSelect.prop('disabled', false);
                return;
            }

            $.getJSON('?ajax=1&action=get_lecturer_courses', {
                lecturer_id: lecturerId
            }, function(data) {
                data.forEach(function(course) {
                    courseSelect.append(`<option value="${course.id}">${course.name} (${course.course_code})</option>`);
                });
                courseSelect.prop('disabled', false);
            });
        }

        // PDF Export function
        function exportToPDF() {
            // For now, trigger CSV download with PDF headers
            // In a real implementation, you'd use a PDF library like jsPDF or server-side PDF generation
            const csvUrl = "?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>";
            window.open(csvUrl, '_blank');
            alert('PDF export would generate a formatted PDF report. Currently downloading CSV format.');
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    if (alert.classList.contains('alert-dismissible')) {
                        alert.style.display = 'none';
                    }
                });
            }, 5000);
        });
    </script>
</body>
</html>
<?php endif; ?>