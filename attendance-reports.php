<?php
/**
 * Attendance Reports - Frontend Only
 * Complete frontend implementation with demo functionality
 * No backend dependencies - works as standalone demo
 */

// Get user session data
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin', 'lecturer', 'hod']);

$user_id = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'lecturer';
$lecturer_id = $_SESSION['lecturer_id'] ?? null;
$department_id = $_SESSION['department_id'] ?? null;
$is_admin = in_array($userRole, ['admin', 'tech']);
$user_context_error = null;

// Get user information
try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.email, u.first_name, u.last_name,
               l.department_id, d.name as department_name
        FROM users u
        LEFT JOIN lecturers l ON u.id = l.user_id
        LEFT JOIN departments d ON l.department_id = d.id
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

// Load real data from database
try {
    // Get departments
    $stmt = $pdo->prepare("SELECT id, name FROM departments ORDER BY name");
    $stmt->execute();
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

    // Get courses - filter based on user role
    $query = "SELECT id, course_name as name, course_code FROM courses WHERE 1=1";
    $params = [];

    if (!$is_admin && $lecturer_id) {
        $query .= " AND lecturer_id = :lecturer_id";
        $params['lecturer_id'] = $lecturer_id;
    }

    $query .= " ORDER BY course_name";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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
$reportType = $_GET['report_type'] ?? 'class'; // department, option, class, course
$selectedDepartmentId = $_GET['department_id'] ?? null;
$selectedOptionId = $_GET['option_id'] ?? null;
$selectedClassId = $_GET['class_id'] ?? null;
$selectedCourseId = $_GET['course_id'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Filter options based on selections
$filteredOptions = $selectedDepartmentId ? array_filter($options, fn($opt) => $opt['department_id'] == $selectedDepartmentId) : [];
$filteredCourses = $selectedClassId ? array_filter($courses, fn($course) => true) : $courses; // For demo, show all courses

// Generate demo attendance report data
function generateDemoReport($report_type, $filters) {
    // Demo students data
    $demoStudents = [
        1 => ['id' => 1, 'full_name' => 'John Doe', 'reg_no' => 'STU001', 'department_name' => 'Computer Science', 'year_level' => 1],
        2 => ['id' => 2, 'full_name' => 'Jane Smith', 'reg_no' => 'STU002', 'department_name' => 'Computer Science', 'year_level' => 1],
        3 => ['id' => 3, 'full_name' => 'Bob Johnson', 'reg_no' => 'STU003', 'department_name' => 'Information Technology', 'year_level' => 2],
        4 => ['id' => 4, 'full_name' => 'Alice Brown', 'reg_no' => 'STU004', 'department_name' => 'Computer Science', 'year_level' => 2],
        5 => ['id' => 5, 'full_name' => 'Charlie Wilson', 'reg_no' => 'STU005', 'department_name' => 'Electrical Engineering', 'year_level' => 3],
        6 => ['id' => 6, 'full_name' => 'Diana Davis', 'reg_no' => 'STU006', 'department_name' => 'Computer Science', 'year_level' => 3],
        7 => ['id' => 7, 'full_name' => 'Eve Miller', 'reg_no' => 'STU007', 'department_name' => 'Information Technology', 'year_level' => 1],
        8 => ['id' => 8, 'full_name' => 'Frank Garcia', 'reg_no' => 'STU008', 'department_name' => 'Computer Science', 'year_level' => 2]
    ];

    // Demo sessions data
    $demoSessions = [
        1 => ['id' => 1, 'course_id' => 1, 'session_date' => '2025-12-15', 'start_time' => '09:00', 'end_time' => '10:30'],
        2 => ['id' => 2, 'course_id' => 1, 'session_date' => '2025-12-14', 'start_time' => '09:00', 'end_time' => '10:30'],
        3 => ['id' => 3, 'course_id' => 2, 'session_date' => '2025-12-15', 'start_time' => '11:00', 'end_time' => '12:30'],
        4 => ['id' => 4, 'course_id' => 2, 'session_date' => '2025-12-14', 'start_time' => '11:00', 'end_time' => '12:30'],
        5 => ['id' => 5, 'course_id' => 3, 'session_date' => '2025-12-15', 'start_time' => '14:00', 'end_time' => '15:30'],
        6 => ['id' => 6, 'course_id' => 4, 'session_date' => '2025-12-15', 'start_time' => '16:00', 'end_time' => '17:30']
    ];

    // Demo attendance records
    $demoAttendance = [
        1 => [1 => ['status' => 'present'], 2 => ['status' => 'present'], 3 => ['status' => 'present']],
        2 => [1 => ['status' => 'present'], 2 => ['status' => 'absent'], 3 => ['status' => 'present']],
        3 => [1 => ['status' => 'absent'], 3 => ['status' => 'present'], 4 => ['status' => 'present']],
        4 => [1 => ['status' => 'present'], 2 => ['status' => 'present'], 4 => ['status' => 'present']],
        5 => [5 => ['status' => 'present'], 6 => ['status' => 'excused']],
        6 => [1 => ['status' => 'present'], 3 => ['status' => 'present'], 5 => ['status' => 'present']],
        7 => [1 => ['status' => 'present'], 2 => ['status' => 'absent'], 3 => ['status' => 'present']],
        8 => [4 => ['status' => 'present'], 6 => ['status' => 'present']]
    ];

    // Filter students based on report type
    $filteredStudents = [];
    switch ($report_type) {
        case 'department':
            $deptId = $filters['department_id'];
            $deptName = '';
            foreach ($GLOBALS['departments'] as $dept) {
                if ($dept['id'] == $deptId) {
                    $deptName = $dept['name'];
                    break;
                }
            }
            foreach ($demoStudents as $id => $student) {
                if ($student['department_name'] === $deptName) {
                    $filteredStudents[$id] = $student;
                }
            }
            break;
        case 'class':
            $classId = $filters['class_id'];
            foreach ($demoStudents as $id => $student) {
                if ($student['year_level'] == $classId) {
                    $filteredStudents[$id] = $student;
                }
            }
            break;
        default:
            $filteredStudents = $demoStudents;
    }

    if (empty($filteredStudents)) {
        return ['students' => [], 'sessions' => [], 'attendance' => [], 'summary' => []];
    }

    // Process attendance data
    $processedAttendance = [];
    foreach ($filteredStudents as $studentId => $student) {
        $studentAttendance = [
            'student_info' => $student,
            'sessions' => [],
            'summary' => ['total_sessions' => 0, 'present_count' => 0, 'absent_count' => 0, 'percentage' => 0]
        ];

        foreach ($demoSessions as $sessionId => $session) {
            $record = $demoAttendance[$studentId][$sessionId] ?? null;
            $status = $record ? $record['status'] : 'absent';

            $studentAttendance['sessions'][$sessionId] = [
                'session_info' => $session,
                'status' => $status,
                'recorded_at' => $record ? date('Y-m-d H:i:s') : null
            ];

            $studentAttendance['summary']['total_sessions']++;
            if ($status === 'present') {
                $studentAttendance['summary']['present_count']++;
            } else {
                $studentAttendance['summary']['absent_count']++;
            }
        }

        if ($studentAttendance['summary']['total_sessions'] > 0) {
            $studentAttendance['summary']['percentage'] = round(
                ($studentAttendance['summary']['present_count'] / $studentAttendance['summary']['total_sessions']) * 100,
                1
            );
        }

        $processedAttendance[$studentId] = $studentAttendance;
    }

    // Calculate summary statistics
    $summary = calculateDemoSummary($processedAttendance);

    return [
        'students' => $filteredStudents,
        'courses' => $GLOBALS['courses'],
        'sessions' => $demoSessions,
        'attendance' => $processedAttendance,
        'summary' => $summary,
        'date_range' => ['start' => $filters['start_date'], 'end' => $filters['end_date']]
    ];
}

function calculateDemoSummary($attendanceData) {
    $totalStudents = count($attendanceData);
    $totalSessions = 0;
    $totalPresent = 0;
    $studentsAbove85 = 0;
    $studentsBelow85 = 0;
    $perfectAttendance = 0;
    $zeroAttendance = 0;
    $totalPercentage = 0;

    foreach ($attendanceData as $studentData) {
        $summary = $studentData['summary'];
        $totalSessions += $summary['total_sessions'];
        $totalPresent += $summary['present_count'];
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
        'total_possible_attendances' => $totalStudents * 6, // 6 demo sessions
        'total_actual_attendances' => $totalPresent,
        'average_attendance_rate' => $totalStudents > 0 ? round($totalPercentage / $totalStudents, 1) : 0,
        'students_above_85_percent' => $studentsAbove85,
        'students_below_85_percent' => $studentsBelow85,
        'perfect_attendance' => $perfectAttendance,
        'zero_attendance' => $zeroAttendance
    ];
}

/**
 * Generate real attendance report data
 */
function generateRealReport($pdo, $report_type, $filters, $lecturer_id, $is_admin) {
    try {
        $students = [];
        $sessions = [];
        $attendance_records = [];

        // Get students based on report type
        switch ($report_type) {
            case 'department':
                $students = getStudentsForDepartment($pdo, $filters['department_id'], $lecturer_id, $is_admin);
                break;
            case 'option':
                $students = getStudentsForOption($pdo, $filters['option_id'], $lecturer_id, $is_admin);
                break;
            case 'class':
                $students = getStudentsForClass($pdo, $filters['class_id'], $lecturer_id, $is_admin);
                break;
            case 'course':
                $students = getStudentsForCourse($pdo, $filters['class_id'], $filters['course_id'], $lecturer_id, $is_admin);
                break;
        }

        if (empty($students)) {
            return ['students' => [], 'sessions' => [], 'attendance' => [], 'summary' => []];
        }

        // Get course IDs based on report type
        $course_ids = [];
        switch ($report_type) {
            case 'department':
                $course_ids = array_column(getCoursesForDepartment($pdo, $filters['department_id'], $lecturer_id, $is_admin), 'id');
                break;
            case 'option':
                $course_ids = array_column(getCoursesForOption($pdo, $filters['option_id'], $lecturer_id, $is_admin), 'id');
                break;
            case 'class':
            case 'course':
                $course_ids = [$filters['course_id']];
                break;
        }

        // Get sessions for the courses
        $sessions = getSessionsForCourses($pdo, $course_ids, $filters['start_date'], $filters['end_date']);

        // Get attendance records
        $attendance_records = getAttendanceRecordsForCourses($pdo, $course_ids, array_keys($students), $filters['start_date'], $filters['end_date']);

        // Process the data
        $processed_data = processAttendanceData($students, $sessions, $attendance_records);
        $summary = calculateAttendanceSummary($students, $sessions, $attendance_records);

        // Add additional report info
        $report_info = [];
        if ($report_type === 'course') {
            $report_info['course_info'] = getCourseInfo($pdo, $filters['course_id']);
        } elseif ($report_type === 'department') {
            $report_info['department_info'] = getDepartmentName($pdo, $filters['department_id']);
            $report_info['courses'] = getCoursesForDepartment($pdo, $filters['department_id'], $lecturer_id, $is_admin);
        }

        return [
            'students' => $students,
            'courses' => getCoursesForDepartment($pdo, $filters['department_id'] ?? null, $lecturer_id, $is_admin),
            'sessions' => $sessions,
            'attendance' => $processed_data,
            'summary' => $summary,
            'date_range' => ['start' => $filters['start_date'], 'end' => $filters['end_date']],
            'report_info' => $report_info
        ];

    } catch (Exception $e) {
        error_log("Error generating real report: " . $e->getMessage());
        return ['error' => 'Failed to generate report data'];
    }
}

/**
 * Get course information
 */
function getCourseInfo($pdo, $course_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.id, c.course_name, c.course_code, c.lecturer_id, d.name as department_name,
                     l.id_number as lecturer_name
            FROM courses c
            LEFT JOIN departments d ON c.department_id = d.id
            LEFT JOIN lecturers l ON c.lecturer_id = l.id
            WHERE c.id = :course_id
        ");
        $stmt->execute(['course_id' => $course_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get students for a department
 */
function getStudentsForDepartment($pdo, $department_id, $lecturer_id, $is_admin) {
    try {
        $query = "
            SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as full_name,
                    s.reg_no, s.student_id_number, d.name as department_name, s.year_level
            FROM students s
            INNER JOIN departments d ON s.department_id = d.id
            WHERE s.department_id = :department_id
        ";

        $params = ['department_id' => $department_id];

        if (!$is_admin && $lecturer_id) {
            $query .= " AND s.option_id IN (SELECT c.option_id FROM courses c WHERE c.lecturer_id = :lecturer_id AND c.department_id = :department_id)";
            $params['lecturer_id'] = $lecturer_id;
        }

        $query .= " ORDER BY s.year_level, s.first_name, s.last_name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $students = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $students[$row['id']] = $row;
        }

        return $students;
    } catch (PDOException $e) {
        error_log("Error fetching students for department: " . $e->getMessage());
        return [];
    }
}

/**
 * Get students for an option
 */
function getStudentsForOption($pdo, $option_id, $lecturer_id, $is_admin) {
    try {
        $query = "
            SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as full_name,
                    s.reg_no, s.student_id_number, d.name as department_name, s.year_level
            FROM students s
            INNER JOIN departments d ON s.department_id = d.id
            WHERE s.option_id = :option_id
        ";

        $params = ['option_id' => $option_id];

        $query .= " ORDER BY s.first_name, s.last_name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $students = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $students[$row['id']] = $row;
        }

        return $students;
    } catch (PDOException $e) {
        error_log("Error fetching students for option: " . $e->getMessage());
        return [];
    }
}

/**
 * Get students for a class
 */
function getStudentsForClass($pdo, $class_id, $lecturer_id, $is_admin) {
    try {
        $query = "
            SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as full_name,
                    s.reg_no, s.student_id_number, d.name as department_name
            FROM students s
            INNER JOIN departments d ON s.department_id = d.id
            WHERE s.year_level = :year_level
        ";

        $params = ['year_level' => $class_id];

        if (!$is_admin && $lecturer_id) {
            $query .= " AND s.department_id = (SELECT department_id FROM lecturers WHERE id = :lecturer_id) AND s.option_id IN (SELECT c.option_id FROM courses c WHERE c.lecturer_id = :lecturer_id)";
            $params['lecturer_id'] = $lecturer_id;
        }

        $query .= " ORDER BY s.first_name, s.last_name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $students = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $students[$row['id']] = $row;
        }

        return $students;
    } catch (PDOException $e) {
        error_log("Error fetching students for class: " . $e->getMessage());
        return [];
    }
}

/**
 * Get students enrolled in a specific course and class
 */
function getStudentsForCourse($pdo, $class_id, $course_id, $lecturer_id, $is_admin) {
    try {
        // First get the course details to determine which students should attend
        $courseStmt = $pdo->prepare("SELECT * FROM courses WHERE id = :course_id");
        $courseStmt->execute(['course_id' => $course_id]);
        $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) return [];

        $query = "
            SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as full_name,
                    s.reg_no, s.student_id_number, d.name as department_name
            FROM students s
            INNER JOIN departments d ON s.department_id = d.id
            WHERE s.year_level = :year_level AND s.option_id = :option_id
        ";

        $params = ['year_level' => $class_id, 'option_id' => $course['option_id']];

        if (!$is_admin && $lecturer_id) {
            $query .= " AND s.department_id = (SELECT department_id FROM lecturers WHERE id = :lecturer_id)";
            $params['lecturer_id'] = $lecturer_id;
        }

        $query .= " ORDER BY s.first_name, s.last_name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $students = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $students[$row['id']] = $row;
        }

        return $students;
    } catch (PDOException $e) {
        error_log("Error fetching students: " . $e->getMessage());
        return [];
    }
}

/**
 * Get courses for a department
 */
function getCoursesForDepartment($pdo, $department_id, $lecturer_id, $is_admin) {
    try {
        $query = "SELECT id, course_name as name, course_code FROM courses WHERE department_id = :department_id";
        $params = ['department_id' => $department_id];

        if (!$is_admin && $lecturer_id) {
            $query .= " AND lecturer_id = :lecturer_id";
            $params['lecturer_id'] = $lecturer_id;
        }

        $query .= " ORDER BY course_name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $courses = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $courses[$row['id']] = $row;
        }

        return $courses;
    } catch (PDOException $e) {
        error_log("Error fetching courses for department: " . $e->getMessage());
        return [];
    }
}

/**
 * Get courses for an option
 */
function getCoursesForOption($pdo, $option_id, $lecturer_id, $is_admin) {
    try {
        $query = "SELECT id, course_name as name, course_code FROM courses WHERE option_id = :option_id";
        $params = ['option_id' => $option_id];

        if (!$is_admin && $lecturer_id) {
            $query .= " AND lecturer_id = :lecturer_id";
            $params['lecturer_id'] = $lecturer_id;
        }

        $query .= " ORDER BY course_name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $courses = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $courses[$row['id']] = $row;
        }

        return $courses;
    } catch (PDOException $e) {
        error_log("Error fetching courses for option: " . $e->getMessage());
        return [];
    }
}

/**
 * Get sessions for multiple courses
 */
function getSessionsForCourses($pdo, $course_ids, $start_date, $end_date) {
    if (empty($course_ids)) return [];

    try {
        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';

        $query = "SELECT id, course_id, session_date, start_time, end_time FROM attendance_sessions WHERE course_id IN ($placeholders)";
        $params = $course_ids;

        if ($start_date && $end_date) {
            $query .= " AND session_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }

        $query .= " ORDER BY session_date ASC, start_time ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sessions[$row['id']] = $row;
        }

        return $sessions;
    } catch (PDOException $e) {
        error_log("Error fetching sessions for courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance records for multiple courses
 */
function getAttendanceRecordsForCourses($pdo, $course_ids, $student_ids, $start_date, $end_date) {
    if (empty($course_ids) || empty($student_ids)) return [];

    try {
        $course_placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
        $student_placeholders = str_repeat('?,', count($student_ids) - 1) . '?';

        $query = "
            SELECT ar.student_id, ar.session_id, ar.status, ar.recorded_at
            FROM attendance_records ar
            INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
            WHERE sess.course_id IN ($course_placeholders) AND ar.student_id IN ($student_placeholders)
        ";

        $params = array_merge($course_ids, $student_ids);

        if ($start_date && $end_date) {
            $query .= " AND sess.session_date BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $records = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $records[$row['student_id']][$row['session_id']] = $row;
        }

        return $records;
    } catch (PDOException $e) {
        error_log("Error fetching attendance records for courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get department name
 */
function getDepartmentName($pdo, $department_id) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = :id");
        $stmt->execute(['id' => $department_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['name'] : 'Unknown Department';
    } catch (PDOException $e) {
        return 'Unknown Department';
    }
}

/**
 * Get option name
 */
function getOptionName($pdo, $option_id) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM options WHERE id = :id");
        $stmt->execute(['id' => $option_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['name'] : 'Unknown Option';
    } catch (PDOException $e) {
        return 'Unknown Option';
    }
}

/**
 * Get attendance sessions for a course
 */
function getAttendanceSessions($pdo, $course_id, $start_date, $end_date) {
    try {
        $query = "SELECT id, session_date, start_time, end_time FROM attendance_sessions WHERE course_id = :course_id";
        $params = ['course_id' => $course_id];

        if ($start_date && $end_date) {
            $query .= " AND session_date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $start_date;
            $params['end_date'] = $end_date;
        }

        $query .= " ORDER BY session_date ASC, start_time ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $sessions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $sessions[$row['id']] = $row;
        }

        return $sessions;
    } catch (PDOException $e) {
        error_log("Error fetching sessions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance records for students and sessions
 */
function getAttendanceRecords($pdo, $course_id, $student_ids, $start_date, $end_date) {
    if (empty($student_ids)) return [];

    try {
        $student_placeholders = str_repeat('?,', count($student_ids) - 1) . '?';

        $query = "
            SELECT ar.student_id, ar.session_id, ar.status, ar.recorded_at
            FROM attendance_records ar
            INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
            WHERE sess.course_id = :course_id AND ar.student_id IN ($student_placeholders)
        ";

        $params = array_merge(['course_id' => $course_id], $student_ids);

        if ($start_date && $end_date) {
            $query .= " AND sess.session_date BETWEEN :start_date AND :end_date";
            $params['start_date'] = $start_date;
            $params['end_date'] = $end_date;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        $records = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $records[$row['student_id']][$row['session_id']] = $row;
        }

        return $records;
    } catch (PDOException $e) {
        error_log("Error fetching attendance records: " . $e->getMessage());
        return [];
    }
}

/**
 * Process attendance data into comprehensive format
 */
function processAttendanceData($students, $sessions, $attendance_records) {
    $processed_data = [];

    foreach ($students as $student_id => $student) {
        $student_attendance = [
            'student_info' => $student,
            'sessions' => [],
            'summary' => [
                'total_sessions' => count($sessions),
                'present_count' => 0,
                'absent_count' => 0,
                'percentage' => 0
            ]
        ];

        foreach ($sessions as $session_id => $session) {
            $record = $attendance_records[$student_id][$session_id] ?? null;
            $status = $record ? $record['status'] : 'absent';

            $student_attendance['sessions'][$session_id] = [
                'session_info' => $session,
                'status' => $status,
                'recorded_at' => $record ? $record['recorded_at'] : null
            ];

            if ($status === 'present') {
                $student_attendance['summary']['present_count']++;
            } else {
                $student_attendance['summary']['absent_count']++;
            }
        }

        // Calculate percentage
        if ($student_attendance['summary']['total_sessions'] > 0) {
            $student_attendance['summary']['percentage'] =
                round(($student_attendance['summary']['present_count'] / $student_attendance['summary']['total_sessions']) * 100, 1);
        }

        $processed_data[$student_id] = $student_attendance;
    }

    return $processed_data;
}

/**
 * Calculate attendance summary statistics
 */
function calculateAttendanceSummary($students, $sessions, $attendance_records) {
    $total_students = count($students);
    $total_sessions = count($sessions);

    $summary = [
        'total_students' => $total_students,
        'total_sessions' => $total_sessions,
        'total_possible_attendances' => $total_students * $total_sessions,
        'total_actual_attendances' => 0,
        'average_attendance_rate' => 0,
        'students_above_85_percent' => 0,
        'students_below_85_percent' => 0,
        'perfect_attendance' => 0,
        'zero_attendance' => 0
    ];

    $total_percentage = 0;

    foreach ($students as $student_id => $student) {
        $present_count = 0;

        foreach ($sessions as $session_id => $session) {
            $record = $attendance_records[$student_id][$session_id] ?? null;
            if ($record && $record['status'] === 'present') {
                $present_count++;
                $summary['total_actual_attendances']++;
            }
        }

        $percentage = $total_sessions > 0 ? ($present_count / $total_sessions) * 100 : 0;
        $total_percentage += $percentage;

        if ($percentage >= 85) {
            $summary['students_above_85_percent']++;
        } else {
            $summary['students_below_85_percent']++;
        }

        if ($percentage == 100) {
            $summary['perfect_attendance']++;
        } elseif ($percentage == 0) {
            $summary['zero_attendance']++;
        }
    }

    if ($total_students > 0) {
        $summary['average_attendance_rate'] = round($total_percentage / $total_students, 1);
    }

    return $summary;
}

// Generate real report data
$report_data = [];
$filters = [
    'department_id' => $selectedDepartmentId,
    'option_id' => $selectedOptionId,
    'class_id' => $selectedClassId,
    'course_id' => $selectedCourseId,
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
    case 'class':
        $hasRequiredFilters = !empty($selectedClassId);
        break;
    case 'course':
        $hasRequiredFilters = !empty($selectedCourseId);
        break;
}

if ($hasRequiredFilters) {
    try {
        $report_data = generateRealReport($pdo, $reportType, $filters, $lecturer_id, $is_admin);
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

/**
 * Handle various export formats
 */
function handleExport($report_data, $format) {
    if (empty($report_data) || isset($report_data['error'])) {
        return;
    }

    $filename = 'attendance_report_' . date('Y-m-d_H-i-s');

    switch ($format) {
        case 'csv':
            exportToCSV($report_data, $filename);
            break;
        case 'pdf':
            exportToPDF($report_data, $filename);
            break;
        case 'excel':
            exportToExcel($report_data, $filename);
            break;
    }
}

/**
 * Export attendance data to CSV
 */
function exportToCSV($report_data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['Student Name', 'Registration No', 'Department', 'Attendance %', 'Present', 'Total Sessions', 'Status']);

    // Data rows
    foreach ($report_data['attendance'] as $student_id => $data) {
        $student = $data['student_info'];
        $summary = $data['summary'];
        $status = $summary['percentage'] >= 85 ? 'Allowed to Exam' : 'Not Allowed to Exam';

        fputcsv($output, [
            $student['full_name'],
            $student['reg_no'],
            $student['department_name'],
            $summary['percentage'] . '%',
            $summary['present_count'],
            $summary['total_sessions'],
            $status
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Export to PDF (basic implementation)
 */
function exportToPDF($report_data, $filename) {
    // For now, redirect to CSV - can be enhanced with proper PDF library
    header('Location: ?' . http_build_query(array_merge($_GET, ['export' => 'csv'])));
    exit;
}

/**
 * Export to Excel (basic implementation)
 */
function exportToExcel($report_data, $filename) {
    // For now, use CSV format with Excel headers
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');

    echo "<table border='1'>";
    echo "<tr><th>Student Name</th><th>Registration No</th><th>Department</th><th>Attendance %</th><th>Present</th><th>Total Sessions</th><th>Status</th></tr>";

    foreach ($report_data['attendance'] as $student_id => $data) {
        $student = $data['student_info'];
        $summary = $data['summary'];
        $status = $summary['percentage'] >= 85 ? 'Allowed to Exam' : 'Not Allowed to Exam';

        echo "<tr>";
        echo "<td>{$student['full_name']}</td>";
        echo "<td>{$student['reg_no']}</td>";
        echo "<td>{$student['department_name']}</td>";
        echo "<td>{$summary['percentage']}%</td>";
        echo "<td>{$summary['present_count']}</td>";
        echo "<td>{$summary['total_sessions']}</td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }

    echo "</table>";
    exit;
}

// Handle export requests
if (isset($_GET['export']) && !empty($report_data)) {
    handleExport($report_data, $_GET['export']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Attendance Reports | <?php echo $userRole === 'admin' ? 'Admin' : 'Lecturer'; ?> | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="css/attendance-reports.css" rel="stylesheet" />
    <style>
        :root {
            /* Enhanced Brand Colors - RP Blue with Modern Palette */
            --primary-color: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #f0f9ff;
            --primary-gradient: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            --primary-glow: 0 0 20px rgba(14, 165, 233, 0.3);

            /* Enhanced Status Colors - Better Contrast and Accessibility */
            --success-color: #10b981;
            --success-light: #ecfdf5;
            --success-dark: #059669;
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);

            --danger-color: #ef4444;
            --danger-light: #fef2f2;
            --danger-dark: #dc2626;
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);

            --warning-color: #f59e0b;
            --warning-light: #fffbeb;
            --warning-dark: #d97706;
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);

            --info-color: #06b6d4;
            --info-light: #ecfeff;
            --info-dark: #0891b2;
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);

            /* Enhanced Layout Variables */
            --shadow-light: 0 2px 8px rgba(0,0,0,0.06);
            --shadow-medium: 0 4px 16px rgba(0,0,0,0.1);
            --shadow-heavy: 0 8px 32px rgba(0,0,0,0.15);
            --shadow-glow: 0 0 0 1px rgba(14, 165, 233, 0.1);
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --border-radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            margin: 0;
            position: relative;
            overflow-x: hidden;
            color: #000000;
            line-height: 1.6;
        }

        /* Clean background - no gradients */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffff;
            pointer-events: none;
            z-index: -1;
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
            background: #ffffff;
            color: #000000;
            padding: 30px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
            border-bottom: 1px solid #e5e7eb;
        }

        .sidebar .logo::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffff;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
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
            color: #000000;
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
            position: relative;
            z-index: 2;
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
            background: #f3f4f6;
            color: #000000;
            border-left-color: #000000;
            transform: translateX(8px);
            box-shadow: 2px 0 12px rgba(0,0,0,0.1);
            border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;
        }

        .sidebar-nav a.active {
            background: #f3f4f6;
            color: #000000;
            border-left-color: #000000;
            box-shadow: 2px 0 16px rgba(0,0,0,0.1);
            font-weight: 600;
            position: relative;
        }

        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: #000000;
            border-radius: 0 2px 2px 0;
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
            margin-left: 280px;
            background: #ffffff;
            padding: 24px 32px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .topbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }

        .main-content {
            margin-left: 280px;
            padding: 48px 32px;
            max-width: calc(100% - 280px);
            overflow-x: auto;
            transition: var(--transition);
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
            background: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid #e5e7eb;
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
            height: 3px;
            background: #000000;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #ffffff;
            pointer-events: none;
            border-radius: var(--border-radius);
        }

        .card:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: var(--shadow-heavy);
            border-color: #e5e7eb;
        }

        .card-header {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 24px 32px;
            font-weight: 600;
            color: #000000;
        }

        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            padding: 12px 24px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: none;
            font-size: 0.9rem;
            letter-spacing: 0.025em;
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
            transition: var(--transition-fast);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: #000000;
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
            color: white;
        }

        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            color: white;
        }

        .btn-success {
            background: var(--success-gradient);
            box-shadow: 0 4px 16px rgba(16, 185, 129, 0.25);
            color: white;
        }

        .btn-info {
            background: var(--info-gradient);
            box-shadow: 0 4px 16px rgba(6, 182, 212, 0.25);
            color: white;
        }

        .btn-danger {
            background: var(--danger-gradient);
            box-shadow: 0 4px 16px rgba(239, 68, 68, 0.25);
            color: white;
        }

        .table {
            background: #ffffff;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid #e5e7eb;
        }

        .table thead th {
            background: #f9fafb;
            color: #000000;
            border: none;
            font-weight: 600;
            padding: 20px 16px;
            position: relative;
            font-size: 0.9rem;
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
            background: rgba(255, 255, 255, 0.2);
        }

        .table tbody td {
            padding: 18px 16px;
            border-color: rgba(14, 165, 233, 0.06);
            transition: var(--transition);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: #f9fafb;
            transform: translateX(4px);
            box-shadow: 4px 0 12px rgba(0,0,0,0.05);
        }

        .table tbody tr:hover td {
            border-color: #e5e7eb;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1003;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 14px;
            box-shadow: 0 4px 20px rgba(14, 165, 233, 0.3);
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(14, 165, 233, 0.4);
        }

        .mobile-menu-toggle:active {
            transform: scale(0.95);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                width: 280px;
                z-index: 1002;
            }

            .sidebar.show {
                transform: translateX(0);
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.4);
            }

            .sidebar.show::after {
                content: '';
                position: fixed;
                top: 0;
                left: 280px;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
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
                padding: 18px 24px;
                margin-left: 0;
            }

            .main-content {
                padding: 24px 16px;
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
                padding: 18px 24px;
                font-size: 1rem;
                border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;
            }

            .sidebar-nav .nav-section {
                padding: 16px 24px 12px;
                font-size: 0.75rem;
            }

            .card {
                margin-bottom: 24px;
            }

            .table-responsive {
                border-radius: var(--border-radius-sm);
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 16px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px 12px;
            }

            .topbar {
                padding: 16px 20px;
            }

            .card-header {
                padding: 20px 16px;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 8px;
                font-size: 0.85rem;
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
            margin: 2rem auto;
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-heavy);
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-bottom: none;
            padding: 24px 32px;
        }

        .modal-body {
            padding: 32px;
        }

        #attendanceTableAll {
            min-width: 1000px;
        }

        /* Filter Section Enhancements */
        .filter-section {
            background: #ffffff;
            border-radius: var(--border-radius);
            padding: 32px;
            margin-bottom: 32px;
            box-shadow: var(--shadow-light);
            border: 1px solid #e5e7eb;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-select, .form-control {
            border-radius: var(--border-radius-sm);
            border: 2px solid #e5e7eb;
            padding: 12px 16px;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .form-select:focus, .form-control:focus {
            border-color: #000000;
            box-shadow: 0 0 0 3px rgba(0,0,0,0.1);
            outline: none;
        }

        /* Stats Grid Enhancements */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            padding: 24px;
            box-shadow: var(--shadow-light);
            border: 1px solid #e5e7eb;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: #f9fafb;
            border-radius: 0 0 0 var(--border-radius);
            opacity: 0.5;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 16px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #000000;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #000000;
            font-weight: 500;
        }

        /* Attendance Bar Enhancements */
        .attendance-bar {
            height: 8px;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }

        .attendance-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.8s ease;
        }

        .attendance-high {
            background: var(--success-gradient);
        }

        .attendance-medium {
            background: var(--warning-gradient);
        }

        .attendance-low {
            background: var(--danger-gradient);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-allowed {
            background: var(--success-light);
            color: var(--success-dark);
            border: 1px solid var(--success-color);
        }

        .status-not-allowed {
            background: var(--danger-light);
            color: var(--danger-dark);
            border: 1px solid var(--danger-color);
        }

        /* Page Header Enhancements */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 2px solid rgba(14, 165, 233, 0.1);
        }

        .page-header h2 {
            color: #000000;
            font-weight: 700;
            margin: 0;
            font-size: 1.75rem;
        }

        .page-header p {
            color: #000000;
            margin: 8px 0 0 0;
            font-size: 1rem;
        }

        /* Course Info Section */
        .course-info {
            background: #f9fafb;
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 32px;
            border: 1px solid #e5e7eb;
        }

        .course-info h5 {
            color: #000000;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .course-info p {
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .course-info strong {
            color: #000000;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
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
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.1) 0%, rgba(14, 165, 233, 0.05) 100%);
            color: #0ea5e9;
            border-radius: var(--border-radius);
            border-left: 4px solid #0ea5e9;
        }
    </style>
</head>

<body>
    <!-- System Status Notice -->
    <div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
        <i class="fas fa-info-circle me-2"></i><strong>Live System:</strong> Connected to RP Attendance Database.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2">Generating demo report...</div>
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
                <h2><i class="fas fa-chart-bar me-3"></i>Advanced Attendance Reports</h2>
                <p>Comprehensive attendance analytics and reporting system</p>
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
                        <option value="option" <?= ($reportType == 'option') ? 'selected' : '' ?>>📚 Option/Program Report</option>
                        <option value="class" <?= ($reportType == 'class') ? 'selected' : '' ?>>🎓 Class/Year Report</option>
                        <option value="course" <?= ($reportType == 'course') ? 'selected' : '' ?>>📖 Course Report</option>
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
                        <?php foreach ($options as $opt) : ?>
                            <option value="<?= $opt['id'] ?>" <?= ($selectedOptionId == $opt['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opt['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="classFilter" style="display: <?= ($reportType == 'class' || $reportType == 'course') ? 'block' : 'none' ?>">
                    <label class="form-label fw-bold">Class/Year Level</label>
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="">🎓 Select Class</option>
                        <?php foreach ($classes as $class) : ?>
                            <option value="<?= $class['id'] ?>" <?= ($selectedClassId == $class['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($class['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="courseFilter" style="display: <?= ($reportType == 'course') ? 'block' : 'none' ?>">
                    <label class="form-label fw-bold">Course</label>
                    <select name="course_id" class="form-select">
                        <option value="">
                            📖 Select Course
                            <?php if (!$is_admin && $lecturer_id): ?>
                                (Your Assigned Courses)
                            <?php endif; ?>
                        </option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?= $course['id'] ?>" <?= ($selectedCourseId == $course['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$is_admin && $lecturer_id && empty($courses)): ?>
                        <div class="text-muted small mt-1">
                            <i class="fas fa-info-circle me-1"></i>
                            No courses are currently assigned to you. Please contact your administrator.
                        </div>
                    <?php endif; ?>
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
                            <p><strong>Courses Included:</strong> <?= count($report_data['courses']) ?></p>
                        <?php elseif (isset($report_data['option_info'])) : ?>
                            <p><strong>Program:</strong> <?= htmlspecialchars($report_data['option_info']['name']) ?></p>
                            <p><strong>Report Type:</strong> Program-specific Attendance</p>
                            <p><strong>Courses Included:</strong> <?= count($report_data['courses']) ?></p>
                        <?php elseif (isset($report_data['class_info'])) : ?>
                            <p><strong>Class:</strong> <?= htmlspecialchars($report_data['class_info']['name']) ?></p>
                            <p><strong>Report Type:</strong> Class-wide Attendance</p>
                            <p><strong>Courses Included:</strong> <?= count($report_data['courses']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h5><i class="fas fa-calendar me-2"></i>Report Period</h5>
                        <p><strong>From:</strong> <?= $report_data['date_range']['start'] ? date('M d, Y', strtotime($report_data['date_range']['start'])) : 'All time' ?></p>
                        <p><strong>To:</strong> <?= $report_data['date_range']['end'] ? date('M d, Y', strtotime($report_data['date_range']['end'])) : 'All time' ?></p>
                        <p><strong>Total Sessions:</strong> <?= count($report_data['sessions']) ?></p>
                        <p><strong>Total Students:</strong> <?= count($report_data['students']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Statistics Dashboard -->
            <?php $summary = $report_data['summary']; ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--primary-gradient);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= $summary['total_students'] ?></div>
                    <div class="stat-label">Total Students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--success-gradient);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?= $summary['students_above_85_percent'] ?></div>
                    <div class="stat-label">Above 85%</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--warning-gradient);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value"><?= $summary['students_below_85_percent'] ?></div>
                    <div class="stat-label">Below 85%</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: var(--info-gradient);">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-value"><?= $summary['average_attendance_rate'] ?>%</div>
                    <div class="stat-label">Average Rate</div>
                </div>
            </div>

            <!-- Detailed Attendance Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-table me-2"></i>Student Attendance Details</h5>
                    <button class="btn btn-outline-primary btn-sm" onclick="toggleDetailedView()">
                        <i class="fas fa-eye me-1"></i>Toggle Details
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Registration No</th>
                                    <th>Department</th>
                                    <th>Attendance Rate</th>
                                    <th>Status</th>
                                    <th>Present/Absent</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data['attendance'] as $student_id => $data) :
                                    $student = $data['student_info'];
                                    $summary = $data['summary'];
                                    $percentage = $summary['percentage'];

                                    // Determine status and colors
                                    if ($percentage >= 85) {
                                        $status = 'Allowed to Exam';
                                        $statusClass = 'status-allowed';
                                        $barClass = 'attendance-high';
                                    } elseif ($percentage >= 70) {
                                        $status = 'Warning';
                                        $statusClass = 'bg-warning text-dark';
                                        $barClass = 'attendance-medium';
                                    } else {
                                        $status = 'Not Allowed to Exam';
                                        $statusClass = 'status-not-allowed';
                                        $barClass = 'attendance-low';
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($student['reg_no']) ?></td>
                                    <td><?= htmlspecialchars($student['department_name']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-2 fw-bold"><?= $percentage ?>%</div>
                                            <div class="attendance-bar">
                                                <div class="attendance-fill <?= $barClass ?>" style="width: <?= $percentage ?>%"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="status-badge <?= $statusClass ?>"><?= $status ?></span></td>
                                    <td><span class="badge bg-success"><?= $summary['present_count'] ?></span> / <span class="badge bg-danger"><?= $summary['absent_count'] ?></span></td>
                                    <td>
                                        <button class="btn btn-outline-info btn-sm" onclick="showStudentDetails(<?= $student_id ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Attendance Distribution</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Performance Overview</h6>
                        </div>
                        <div class="card-body">
                            <canvas id="performanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($selectedClassId && $selectedCourseId) : ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                    <h5>No Attendance Data Found</h5>
                    <p class="text-muted">No attendance records found for the selected course and date range.</p>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i>Student Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Global variables
        const reportData = <?= json_encode($report_data ?? []) ?>;
        let detailedView = false;

        // Demo notification system
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

            setTimeout(() => {
                if (alert.parentNode) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 300);
                }
            }, 4000);
        }

        // Sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Update filter visibility based on report type
        function updateFilters(reportType) {
            const departmentFilter = document.getElementById('departmentFilter');
            const optionFilter = document.getElementById('optionFilter');
            const classFilter = document.getElementById('classFilter');
            const courseFilter = document.getElementById('courseFilter');

            // Hide all filters first
            departmentFilter.style.display = 'none';
            optionFilter.style.display = 'none';
            classFilter.style.display = 'none';
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
                case 'class':
                    classFilter.style.display = 'block';
                    break;
                case 'course':
                    <?php if (!$is_admin && $lecturer_id): ?>
                        // For lecturers, show courses directly without class filter
                        courseFilter.style.display = 'block';
                    <?php else: ?>
                        // For admins, show class filter first, then courses
                        classFilter.style.display = 'block';
                        courseFilter.style.display = 'block';
                    <?php endif; ?>
                    break;
            }
        }

        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            const reportTypeSelect = document.querySelector('select[name="report_type"]');
            if (reportTypeSelect) {
                updateFilters(reportTypeSelect.value);
            }
        });

        // Show student details
        function showStudentDetails(studentId) {
            if (!reportData.attendance || !reportData.attendance[studentId]) return;

            const student = reportData.attendance[studentId];
            const studentInfo = student.student_info;
            const sessions = student.sessions;

            let html = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Student Information</h6>
                        <p><strong>Name:</strong> ${studentInfo.full_name}</p>
                        <p><strong>Registration No:</strong> ${studentInfo.reg_no}</p>
                        <p><strong>Department:</strong> ${studentInfo.department_name}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Attendance Summary</h6>
                        <p><strong>Overall Rate:</strong> ${student.summary.percentage}%</p>
                        <p><strong>Present:</strong> ${student.summary.present_count}</p>
                        <p><strong>Absent:</strong> ${student.summary.absent_count}</p>
                    </div>
                </div>
                <h6>Session Details</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>`;

            Object.values(sessions).forEach(session => {
                const sessionInfo = session.session_info;
                const status = session.status;
                const statusBadge = status === 'present'
                    ? '<span class="badge bg-success">Present</span>'
                    : '<span class="badge bg-danger">Absent</span>';

                html += `
                    <tr>
                        <td>${new Date(sessionInfo.session_date).toLocaleDateString()}</td>
                        <td>${sessionInfo.start_time} - ${sessionInfo.end_time}</td>
                        <td>${statusBadge}</td>
                    </tr>`;
            });

            html += `
                        </tbody>
                    </table>
                </div>`;

            document.getElementById('studentDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('studentDetailsModal')).show();
        }

        // Toggle detailed view
        function toggleDetailedView() {
            detailedView = !detailedView;
            // Implementation for toggling detailed view
        }

        // Initialize live system features
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('Live attendance reports loaded successfully!', 'success');

            // Initialize charts when data is available
            <?php if (!empty($report_data) && !isset($report_data['error'])) : ?>
            // Attendance Distribution Chart
            const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
            const summary = <?= json_encode($report_data['summary']) ?>;

            new Chart(attendanceCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Above 85%', 'Below 85%', 'Perfect Attendance', 'Zero Attendance'],
                    datasets: [{
                        data: [
                            summary.students_above_85_percent,
                            summary.students_below_85_percent,
                            summary.perfect_attendance,
                            summary.zero_attendance
                        ],
                        backgroundColor: [
                            '#10b981',
                            '#ef4444',
                            '#3b82f6',
                            '#6b7280'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Performance Chart
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            const attendanceRates = Object.values(<?= json_encode($report_data['attendance']) ?>).map(s => s.summary.percentage);

            new Chart(performanceCtx, {
                type: 'bar',
                data: {
                    labels: ['0-25%', '26-50%', '51-75%', '76-85%', '86-100%'],
                    datasets: [{
                        label: 'Number of Students',
                        data: [
                            attendanceRates.filter(r => r <= 25).length,
                            attendanceRates.filter(r => r > 25 && r <= 50).length,
                            attendanceRates.filter(r => r > 50 && r <= 75).length,
                            attendanceRates.filter(r => r > 75 && r <= 85).length,
                            attendanceRates.filter(r => r > 85).length
                        ],
                        backgroundColor: '#0066cc'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            showNotification('Live charts generated with real attendance data!', 'info');
            <?php endif; ?>
        });

        // Loading overlay functions
        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('d-none');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('d-none');
        }

        // Auto-hide loading on page load
        window.addEventListener('load', hideLoading);

        // PDF Export function
        function exportToPDF() {
            // For now, trigger CSV download with PDF headers
            // In a real implementation, you'd use a PDF library like jsPDF or server-side PDF generation
            const csvUrl = "?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>";
            window.open(csvUrl, '_blank');
            alert('PDF export would generate a formatted PDF report. Currently downloading CSV format.');
        }
    </script>
</body>
</html>
