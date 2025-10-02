<?php
/**
 * Attendance Session API
 * Handles all AJAX requests for attendance session functionality
 * Supports face recognition, fingerprint, and manual attendance marking
 */

require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../session_check.php";
require_once __DIR__ . "/../cache_utils.php";
session_start();

// Ensure user is logged in and is lecturer, hod, or admin
require_role(['lecturer', 'hod', 'admin']);

// Set JSON response headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Initialize response structure
$response = [
    'status' => 'error',
    'message' => 'Unknown error occurred',
    'data' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Validate CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf_token)) {
            throw new Exception('CSRF token validation failed');
        }
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Route actions based on request
    switch ($action) {
        case 'get_departments':
            $response = handleGetDepartments($pdo);
            break;

        case 'get_options':
            $response = handleGetOptions($pdo);
            break;

        case 'get_students':
            $response = handleGetStudents($pdo);
            break;

        case 'get_courses':
            $response = handleGetCourses($pdo);
            break;

        case 'start_session':
            $response = handleStartSession($pdo);
            break;

        case 'end_session':
            $response = handleEndSession($pdo);
            break;

        case 'record_attendance':
            $response = handleRecordAttendance($pdo);
            break;

        case 'get_session_status':
            $response = handleGetSessionStatus($pdo);
            break;

        case 'get_session_stats':
            $response = handleGetSessionStats($pdo);
            break;

        case 'manual_attendance':
            $response = handleManualAttendance($pdo);
            break;

        case 'get_attendance_history':
            $response = handleGetAttendanceHistory($pdo);
            break;

        case 'test_database':
            $response = handleTestDatabase($pdo);
            break;

        case 'debug_courses':
            $response = handleDebugCourses($pdo);
            break;

        case 'test_courses':
            $response = handleTestCourses($pdo);
            break;

        default:
            http_response_code(400);
            $response['message'] = 'Invalid action specified';
    }

} catch (PDOException $e) {
    error_log("Database Error in Attendance Session API: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Database error occurred';

} catch (Exception $e) {
    error_log("Error in Attendance Session API: " . $e->getMessage());
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
exit;

/**
 * Get departments for the current lecturer
 */
function handleGetDepartments(PDO $pdo): array {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    error_log("handleGetDepartments called for user_id: $user_id, role: $user_role");

    // Get user's department - could be lecturer or hod
    if ($user_role === 'hod') {
        // For HODs, get department from departments table where they are assigned as HOD
        error_log("Querying departments for HOD user_id: $user_id");
        $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE hod_id = ?");
        $stmt->execute([$user_id]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("HOD department query result: " . ($department ? "Found department " . $department['id'] : "No department found"));

        if (!$department) {
            return [
                'status' => 'success',
                'message' => 'No department assigned to this HOD',
                'data' => [],
                'count' => 0
            ];
        }

        // Return the department data
        return [
            'status' => 'success',
            'message' => 'Departments retrieved successfully',
            'data' => [$department],
            'count' => 1
        ];
    } else {
        // For lecturers, get department from lecturers table
        error_log("Querying departments for lecturer user_id: $user_id");
        $stmt = $pdo->prepare("
            SELECT l.id as lecturer_id, l.department_id, d.name
            FROM lecturers l
            JOIN users u ON l.email = u.email
            JOIN departments d ON l.department_id = d.id
            WHERE u.id = ? AND u.role = 'lecturer'
        ");
        $stmt->execute([$user_id]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Lecturer department query result: " . ($lecturer ? "Found department " . $lecturer['department_id'] : "No department found"));

        if (!$lecturer || !$lecturer['department_id']) {
            return [
                'status' => 'success',
                'message' => 'No department assigned to this lecturer',
                'data' => [],
                'count' => 0
            ];
        }

        // Return the department data
        return [
            'status' => 'success',
            'message' => 'Departments retrieved successfully',
            'data' => [['id' => $lecturer['department_id'], 'name' => $lecturer['name']]],
            'count' => 1
        ];
    }

    // For admins, return all departments
    if ($_SESSION['role'] === 'admin') {
        $stmt = $pdo->prepare("SELECT id, name FROM departments ORDER BY name");
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // For lecturers, return only their assigned department
        $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ? ORDER BY name");
        $stmt->execute([$lecturer_department_id]);
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'status' => 'success',
        'message' => 'Departments retrieved successfully',
        'data' => $departments,
        'count' => count($departments)
    ];
}

/**
 * Get options for a specific department
 */
function handleGetOptions(PDO $pdo): array {
    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);

    if (!$department_id || $department_id <= 0) {
        throw new Exception('Invalid department ID');
    }

    // Get current user's department to ensure they can only access their department's options
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    if ($user_role === 'hod') {
        // For HODs, get department from departments table where they are assigned as HOD
        $stmt = $pdo->prepare("SELECT id as department_id FROM departments WHERE hod_id = ?");
        $stmt->execute([$user_id]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$department) {
            return [
                'status' => 'error',
                'message' => 'No department assigned to this HOD'
            ];
        }

        $lecturer_department_id = $department['department_id'];
    } else {
        // For lecturers, get department from lecturers table
        $stmt = $pdo->prepare("
            SELECT l.department_id
            FROM lecturers l
            JOIN users u ON l.email = u.email
            WHERE u.id = ? AND u.role = 'lecturer'
        ");
        $stmt->execute([$user_id]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturer || !$lecturer['department_id']) {
            return [
                'status' => 'error',
                'message' => 'No department assigned to this lecturer'
            ];
        }

        $lecturer_department_id = $lecturer['department_id'];
    }

    // Verify that the requested department matches the lecturer's department (unless admin)
    if ($_SESSION['role'] !== 'admin' && $department_id != $lecturer_department_id) {
        return [
            'status' => 'error',
            'message' => 'You can only access options from your assigned department'
        ];
    }

    $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY name");
    $stmt->execute([$department_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'status' => 'success',
        'message' => 'Options retrieved successfully',
        'data' => $options,
        'count' => count($options)
    ];
}

/**
 * Get students for a specific department, option, and year level
 */
function handleGetStudents(PDO $pdo): array {
    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);
    $option_id = filter_input(INPUT_GET, 'option_id', FILTER_VALIDATE_INT);
    $year_level = filter_input(INPUT_GET, 'year_level', FILTER_SANITIZE_STRING);

    if (!$department_id || $department_id <= 0) {
        throw new Exception('Invalid department ID');
    }

    $params = [$department_id];
    $query = "
        SELECT
            s.id,
            s.student_id,
            s.first_name,
            s.last_name,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            s.email,
            s.phone,
            s.profile_image,
            s.year_level,
            d.name as department_name,
            o.name as option_name
        FROM students s
        INNER JOIN departments d ON s.department_id = d.id
        INNER JOIN options o ON s.option_id = o.id
        WHERE s.department_id = ?
    ";

    if ($option_id && $option_id > 0) {
        $query .= " AND s.option_id = ?";
        $params[] = $option_id;
    }

    if ($year_level) {
        $query .= " AND s.year_level = ?";
        $params[] = $year_level;
    }

    $query .= " ORDER BY s.first_name, s.last_name";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'status' => 'success',
        'message' => 'Students retrieved successfully',
        'data' => $students,
        'count' => count($students)
    ];
}

/**
 * Get courses for a specific department, option, and year level
 */
function handleGetCourses(PDO $pdo): array {
    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);
    $option_id = filter_input(INPUT_GET, 'option_id', FILTER_VALIDATE_INT);
    $year_level = filter_input(INPUT_GET, 'year_level', FILTER_SANITIZE_STRING);

    if (!$department_id || $department_id <= 0) {
        throw new Exception('Invalid department ID');
    }

    // Note: year_level is optional for course loading since courses don't have year levels
    // Year level filtering should be applied when getting students, not courses

    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    if ($user_role === 'hod') {
        // For HODs, get department from departments table where they are assigned as HOD
        $stmt = $pdo->prepare("SELECT id as department_id FROM departments WHERE hod_id = ?");
        $stmt->execute([$user_id]);
        $department = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$department) {
            return [
                'status' => 'error',
                'message' => 'No department assigned to this HOD'
            ];
        }

        $lecturer_department_id = $department['id'];
        $lecturer_id = null; // HODs don't have lecturer_id
    } else {
        // For lecturers, get department from lecturers table
        $stmt = $pdo->prepare("
            SELECT l.id as lecturer_id, l.department_id
            FROM lecturers l
            JOIN users u ON l.email = u.email
            WHERE u.id = ? AND u.role = 'lecturer'
        ");
        $stmt->execute([$user_id]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturer || !$lecturer['department_id']) {
            return [
                'status' => 'error',
                'message' => 'No department assigned to this lecturer'
            ];
        }

        $lecturer_department_id = $lecturer['department_id'];
        $lecturer_id = $lecturer['lecturer_id'];
    }

    // Verify that the requested department matches the lecturer's department (unless admin)
    if ($_SESSION['role'] !== 'admin' && $department_id != $lecturer_department_id) {
        return [
            'status' => 'error',
            'message' => 'You can only access courses from your assigned department'
        ];
    }

    // Debug: Check if tables exist and have data
    try {
        $pdo->query("SELECT 1 FROM courses LIMIT 1");
        $pdo->query("SELECT 1 FROM lecturers LIMIT 1");
        $pdo->query("SELECT 1 FROM attendance_sessions LIMIT 1");
        $pdo->query("SELECT 1 FROM attendance_records LIMIT 1");

        // Check for data issues
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM courses WHERE department_id = ?");
        $stmt->execute([$department_id]);
        $course_count = $stmt->fetch()['count'];

        if ($course_count == 0) {
            error_log("No courses found for department $department_id - returning empty result");
            // Return empty result instead of throwing exception
            return [
                'status' => 'success',
                'message' => 'No courses available for the selected department',
                'data' => [],
                'count' => 0
            ];
        }

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM lecturers");
        $lecturer_count = $stmt->fetch()['count'];

        if ($lecturer_count == 0) {
            error_log("No lecturers found in database - this may cause issues with course display");
            // Don't throw exception, just log and continue - courses can still be displayed without lecturer info
        }

        // Check if lecturer_id values in courses match lecturers table (but don't fail)
        $stmt = $pdo->prepare("
            SELECT c.lecturer_id, COUNT(*) as course_count
            FROM courses c
            WHERE c.department_id = ?
            GROUP BY c.lecturer_id
        ");
        $stmt->execute([$department_id]);
        $lecturer_usage = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($lecturer_usage as $usage) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM lecturers WHERE id = ?");
            $stmt->execute([$usage['lecturer_id']]);
            $lecturer_exists = $stmt->fetch()['count'];

            if ($lecturer_exists == 0) {
                error_log("Course references non-existent lecturer_id: " . $usage['lecturer_id'] . " - will use fallback");
                // Don't throw exception, just log and continue
            }
        }

        error_log("Database validation passed: $course_count courses, $lecturer_count lecturers");

    } catch (PDOException $e) {
        error_log("Table check failed: " . $e->getMessage());
        throw new Exception('Database validation failed: ' . $e->getMessage());
    }

    $params = [$department_id];

    // First try to get courses that have attendance sessions
    $query = "
        SELECT DISTINCT
            c.id,
            c.name,
            c.course_code,
            c.description,
            c.semester,
            c.credits,
            c.is_available,
            COALESCE(l.first_name, 'Unknown') as first_name,
            COALESCE(l.last_name, 'Lecturer') as last_name,
            CASE
                WHEN l.first_name IS NOT NULL AND l.last_name IS NOT NULL
                THEN CONCAT(l.first_name, ' ', l.last_name)
                ELSE 'Unknown Lecturer'
            END as lecturer_name
        FROM courses c
        LEFT JOIN lecturers l ON c.lecturer_id = l.id
        LEFT JOIN attendance_sessions s ON c.id = s.course_id AND s.department_id = ?
    ";

    if ($option_id && $option_id > 0) {
        $query .= " AND (s.option_id = ? OR s.option_id IS NULL)";
        $params[] = $option_id;
    }

    // Note: Courses don't have year_level - students do
    // Year level filtering should be applied when getting students, not courses

    $query .= " ORDER BY c.name";

    // If no courses found with attendance sessions, get all courses for the department
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no courses found and we have an option_id filter, try without the option filter
    if (empty($courses) && $option_id && $option_id > 0) {
        $params = [$department_id];
        $query = "
            SELECT DISTINCT
                c.id,
                c.name,
                c.course_code,
                c.description,
                c.semester,
                c.credits,
                c.is_available,
                COALESCE(l.first_name, 'Unknown') as first_name,
                COALESCE(l.last_name, 'Lecturer') as last_name,
                CASE
                    WHEN l.first_name IS NOT NULL AND l.last_name IS NOT NULL
                    THEN CONCAT(l.first_name, ' ', l.last_name)
                    ELSE 'Unknown Lecturer'
                END as lecturer_name
            FROM courses c
            LEFT JOIN lecturers l ON c.lecturer_id = l.id
            LEFT JOIN attendance_sessions s ON c.id = s.course_id
            WHERE (s.department_id = ? OR s.department_id IS NULL)
        ";

        // Note: Courses don't have year_level - students do
        $query .= " ORDER BY c.name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // If still no courses, get all courses for the department regardless of sessions
    if (empty($courses)) {
        $params = [$department_id];
        $query = "
            SELECT DISTINCT
                c.id,
                c.name,
                c.course_code,
                c.description,
                c.semester,
                c.credits,
                c.is_available,
                COALESCE(l.first_name, 'Unknown') as first_name,
                COALESCE(l.last_name, 'Lecturer') as last_name,
                CASE
                    WHEN l.first_name IS NOT NULL AND l.last_name IS NOT NULL
                    THEN CONCAT(l.first_name, ' ', l.last_name)
                    ELSE 'Unknown Lecturer'
                END as lecturer_name
            FROM courses c
            LEFT JOIN lecturers l ON c.lecturer_id = l.id
            WHERE c.department_id = ?
        ";

        // Note: Courses don't have year_level - students do
        $query .= " ORDER BY c.name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Final fallback: get all courses for the department without lecturer info
    if (empty($courses)) {
        $params = [$department_id];
        $query = "
            SELECT DISTINCT
                c.id,
                c.name,
                c.course_code,
                c.description,
                c.semester,
                c.credits,
                c.is_available,
                '' as first_name,
                '' as last_name,
                'Unknown Lecturer' as lecturer_name
            FROM courses c
            WHERE c.department_id = ?
        ";

        // Note: Courses don't have year_level - students do
        // Year level filtering should be applied when getting students, not courses

        $query .= " ORDER BY c.name";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("Using fallback query (no lecturer info) - found " . count($courses) . " courses for department $department_id");
    }

    // Last resort: get courses even without department filter
    if (empty($courses)) {
        $params = [];
        $query = "
            SELECT DISTINCT
                c.id,
                c.name,
                c.course_code,
                c.description,
                c.semester,
                c.credits,
                c.is_available,
                '' as first_name,
                '' as last_name,
                'Unknown Lecturer' as lecturer_name
            FROM courses c
        ";

        // Note: Courses don't have year_level - students do
        $query .= " ORDER BY c.name";

        $stmt = $pdo->prepare($query);
        $stmt->execute();
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("Using last resort query - found " . count($courses) . " courses total");
    }

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Log successful query for debugging
        error_log("Courses query executed successfully. Found " . count($courses) . " courses for department $department_id");
        error_log("Query: $query");
        error_log("Params: " . implode(', ', $params));

        return [
            'status' => 'success',
            'message' => 'Courses retrieved successfully',
            'data' => $courses,
            'count' => count($courses)
        ];
    } catch (PDOException $e) {
        error_log("Error in handleGetCourses: " . $e->getMessage());
        error_log("Query: $query");
        error_log("Params: " . implode(', ', $params));
        error_log("Full error details: " . print_r($e, true));

        // Let's also check if the tables exist and have data
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM courses WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $course_count = $stmt->fetch()['count'];
            error_log("Found $course_count courses for department $department_id");

            $stmt = $pdo->query("SELECT COUNT(*) as count FROM lecturers");
            $lecturer_count = $stmt->fetch()['count'];
            error_log("Found $lecturer_count lecturers total");

            $stmt = $pdo->prepare("SELECT lecturer_id, COUNT(*) as count FROM courses WHERE department_id = ? GROUP BY lecturer_id");
            $stmt->execute([$department_id]);
            $lecturer_usage = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Lecturer usage in courses: " . print_r($lecturer_usage, true));

        } catch (Exception $debug_e) {
            error_log("Debug query failed: " . $debug_e->getMessage());
        }

        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

/**
 * Start a new attendance session
 */
function handleStartSession(PDO $pdo): array {
    $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $option_id = filter_input(INPUT_POST, 'option_id', FILTER_VALIDATE_INT);
    $course_id = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);
    $class_level = filter_input(INPUT_POST, 'classLevel', FILTER_SANITIZE_STRING);
    $lecturer_id = $_SESSION['user_id'];

    if (!$department_id || !$option_id || !$course_id) {
        throw new Exception('Department, option, class level, and course are required');
    }

    // Check if there's already an active session for this course
    $stmt = $pdo->prepare("
        SELECT id FROM attendance_sessions
        WHERE course_id = ? AND option_id = ? AND department_id = ? AND end_time IS NULL
    ");
    $stmt->execute([$course_id, $option_id, $department_id]);
    $existing_session = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_session) {
        throw new Exception('An active session already exists for this course');
    }

    // Start transaction
    $pdo->beginTransaction();
    try {
        // Create new session
        $stmt = $pdo->prepare("
            INSERT INTO attendance_sessions (
                course_id, option_id, department_id, lecturer_id,
                session_date, start_time, status, created_at
            ) VALUES (?, ?, ?, ?, CURDATE(), NOW(), 'active', NOW())
        ");
        $stmt->execute([$course_id, $option_id, $department_id, $lecturer_id]);
        $session_id = $pdo->lastInsertId();

        // Get session details
        $stmt = $pdo->prepare("
            SELECT
                s.id,
                s.session_date,
                s.start_time,
                c.name as course_name,
                c.course_code,
                CONCAT(l.first_name, ' ', l.last_name) as lecturer_name,
                d.name as department_name,
                o.name as option_name
            FROM attendance_sessions s
            INNER JOIN courses c ON s.course_id = c.id
            INNER JOIN lecturers l ON s.lecturer_id = l.id
            INNER JOIN departments d ON s.department_id = d.id
            INNER JOIN options o ON s.option_id = o.id
            WHERE s.id = ?
        ");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        $pdo->commit();

        return [
            'status' => 'success',
            'message' => 'Attendance session started successfully',
            'data' => $session,
            'session_id' => $session_id
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        throw new Exception('Failed to start session: ' . $e->getMessage());
    }
}

/**
 * End an active attendance session
 */
function handleEndSession(PDO $pdo): array {
    $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);

    if (!$session_id || $session_id <= 0) {
        throw new Exception('Invalid session ID');
    }

    // Check if session exists and is active
    $stmt = $pdo->prepare("
        SELECT id, course_id FROM attendance_sessions
        WHERE id = ? AND end_time IS NULL
    ");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        throw new Exception('Active session not found');
    }

    // End the session
    $stmt = $pdo->prepare("
        UPDATE attendance_sessions
        SET end_time = NOW(), status = 'completed', updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$session_id]);

    // Get session statistics
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_records,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count
        FROM attendance_records
        WHERE session_id = ?
    ");
    $stmt->execute([$session_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'status' => 'success',
        'message' => 'Attendance session ended successfully',
        'data' => [
            'session_id' => $session_id,
            'total_records' => (int)($stats['total_records'] ?? 0),
            'present_count' => (int)($stats['present_count'] ?? 0),
            'absent_count' => (int)($stats['absent_count'] ?? 0)
        ]
    ];
}

/**
 * Record attendance for a student
 */
function handleRecordAttendance(PDO $pdo): array {
    $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $method = filter_input(INPUT_POST, 'method', FILTER_SANITIZE_STRING);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if (!$session_id || !$student_id || !$method || !$status) {
        throw new Exception('Session ID, student ID, method, and status are required');
    }

    // Validate method
    $valid_methods = ['face_recognition', 'fingerprint', 'manual'];
    if (!in_array($method, $valid_methods)) {
        throw new Exception('Invalid attendance method');
    }

    // Validate status
    $valid_statuses = ['present', 'absent'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid attendance status');
    }

    // Check if attendance already recorded for this student in this session
    $stmt = $pdo->prepare("
        SELECT id FROM attendance_records
        WHERE session_id = ? AND student_id = ?
    ");
    $stmt->execute([$session_id, $student_id]);
    $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_record) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE attendance_records
            SET status = ?, recorded_at = NOW()
            WHERE session_id = ? AND student_id = ?
        ");
        $stmt->execute([$status, $session_id, $student_id]);
        $record_id = $existing_record['id'];
    } else {
        // Create new record
        $stmt = $pdo->prepare("
            INSERT INTO attendance_records (
                session_id, student_id, status, recorded_at
            ) VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$session_id, $student_id, $status]);
        $record_id = $pdo->lastInsertId();
    }

    return [
        'status' => 'success',
        'message' => 'Attendance recorded successfully',
        'data' => [
            'record_id' => $record_id,
            'session_id' => $session_id,
            'student_id' => $student_id,
            'status' => $status,
            'method' => $method,
            'recorded_at' => date('Y-m-d H:i:s')
        ]
    ];
}

/**
 * Get current session status
 */
function handleGetSessionStatus(PDO $pdo): array {
    $course_id = filter_input(INPUT_GET, 'course_id', FILTER_VALIDATE_INT);
    $option_id = filter_input(INPUT_GET, 'option_id', FILTER_VALIDATE_INT);
    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);
    $class_level = filter_input(INPUT_GET, 'classLevel', FILTER_SANITIZE_STRING);

    if (!$course_id || !$option_id || !$department_id) {
        throw new Exception('Course ID, option ID, department ID, and class level are required');
    }

    $stmt = $pdo->prepare("
        SELECT
            s.id,
            s.session_date,
            s.start_time,
            s.end_time,
            s.status,
            c.name as course_name,
            COUNT(ar.id) as attendance_count,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count
        FROM attendance_sessions s
        INNER JOIN courses c ON s.course_id = c.id
        LEFT JOIN attendance_records ar ON s.id = ar.session_id
        WHERE s.course_id = ? AND s.option_id = ? AND s.department_id = ?
        ORDER BY s.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$course_id, $option_id, $department_id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return [
            'status' => 'success',
            'message' => 'No active session found',
            'data' => null,
            'is_active' => false
        ];
    }

    return [
        'status' => 'success',
        'message' => 'Session status retrieved successfully',
        'data' => $session,
        'is_active' => $session['status'] === 'active'
    ];
}

/**
 * Get session statistics
 */
function handleGetSessionStats(PDO $pdo): array {
    $session_id = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);

    if (!$session_id || $session_id <= 0) {
        throw new Exception('Invalid session ID');
    }

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_students,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
            ROUND(
                (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / COUNT(*)), 1
            ) as attendance_rate
        FROM attendance_records ar
        WHERE ar.session_id = ?
    ");
    $stmt->execute([$session_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get method breakdown (using 'manual' as default since method field doesn't exist in current schema)
    $method_stats = [
        [
            'method' => 'manual',
            'count' => $stats['total_students'],
            'present_count' => $stats['present_count']
        ]
    ];

    return [
        'status' => 'success',
        'message' => 'Session statistics retrieved successfully',
        'data' => [
            'total_students' => (int)($stats['total_students'] ?? 0),
            'present_count' => (int)($stats['present_count'] ?? 0),
            'absent_count' => (int)($stats['absent_count'] ?? 0),
            'attendance_rate' => (float)($stats['attendance_rate'] ?? 0),
            'method_breakdown' => $method_stats
        ]
    ];
}

/**
 * Record manual attendance
 */
function handleManualAttendance(PDO $pdo): array {
    $student_id = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
    $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);

    if (!$student_id || !$session_id || !$status || !$date) {
        throw new Exception('Student ID, session ID, status, and date are required');
    }

    // Validate status
    $valid_statuses = ['present', 'absent'];
    if (!in_array($status, $valid_statuses)) {
        throw new Exception('Invalid attendance status');
    }

    // Check if attendance already recorded for this student in this session
    $stmt = $pdo->prepare("
        SELECT id FROM attendance_records
        WHERE session_id = ? AND student_id = ?
    ");
    $stmt->execute([$session_id, $student_id]);
    $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_record) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE attendance_records
            SET status = ?, recorded_at = ?
            WHERE session_id = ? AND student_id = ?
        ");
        $stmt->execute([$status, $date . ' ' . date('H:i:s'), $session_id, $student_id]);
        $record_id = $existing_record['id'];
    } else {
        // Create new record
        $stmt = $pdo->prepare("
            INSERT INTO attendance_records (
                session_id, student_id, status, recorded_at
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$session_id, $student_id, $status, $date . ' ' . date('H:i:s')]);
        $record_id = $pdo->lastInsertId();
    }

    return [
        'status' => 'success',
        'message' => 'Manual attendance recorded successfully',
        'data' => [
            'record_id' => $record_id,
            'session_id' => $session_id,
            'student_id' => $student_id,
            'status' => $status,
            'method' => 'manual',
            'recorded_at' => $date . ' ' . date('H:i:s')
        ]
    ];
}

/**
 * Get attendance history for a session
 */
function handleGetAttendanceHistory(PDO $pdo): array {
    $session_id = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);

    if (!$session_id || $session_id <= 0) {
        throw new Exception('Invalid session ID');
    }

    $stmt = $pdo->prepare("
        SELECT
            ar.id,
            ar.status,
            'manual' as method,
            ar.recorded_at,
            s.student_id,
            s.first_name,
            s.last_name,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            s.photo as profile_image
        FROM attendance_records ar
        INNER JOIN students s ON ar.student_id = s.id
        WHERE ar.session_id = ?
        ORDER BY ar.recorded_at DESC
    ");
    $stmt->execute([$session_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'status' => 'success',
        'message' => 'Attendance history retrieved successfully',
        'data' => $history,
        'count' => count($history)
    ];
}

/**
 * Test database connectivity and table structure
 */
function handleTestDatabase(PDO $pdo): array {
    $results = [];

    try {
        // Test basic connectivity
        $results['connection'] = 'OK';

        // Test required tables
        $tables = ['departments', 'options', 'courses', 'lecturers', 'students', 'attendance_sessions', 'attendance_records'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
                $results['tables'][$table] = 'EXISTS';
            } catch (PDOException $e) {
                $results['tables'][$table] = 'MISSING: ' . $e->getMessage();
            }
        }

        // Test sample data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
        $dept_count = $stmt->fetch()['count'];
        $results['sample_data']['departments'] = $dept_count;

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM courses");
        $course_count = $stmt->fetch()['count'];
        $results['sample_data']['courses'] = $course_count;

        $stmt = $pdo->query("SELECT COUNT(*) as count FROM lecturers");
        $lecturer_count = $stmt->fetch()['count'];
        $results['sample_data']['lecturers'] = $lecturer_count;

        return [
            'status' => 'success',
            'message' => 'Database test completed',
            'data' => $results
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Database test failed: ' . $e->getMessage(),
            'data' => $results
        ];
    }
}

/**
 * Debug courses query issues
 */
function handleDebugCourses(PDO $pdo): array {
    $debug_info = [];

    try {
        // Check all courses
        $stmt = $pdo->query("SELECT * FROM courses");
        $all_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug_info['all_courses'] = $all_courses;

        // Check all lecturers
        $stmt = $pdo->query("SELECT * FROM lecturers");
        $all_lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debug_info['all_lecturers'] = $all_lecturers;

        // Check courses with invalid lecturer_id
        $stmt = $pdo->query("
            SELECT c.*, l.id as lecturer_exists
            FROM courses c
            LEFT JOIN lecturers l ON c.lecturer_id = l.id
        ");
        $courses_with_lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $invalid_courses = array_filter($courses_with_lecturers, function($course) {
            return $course['lecturer_exists'] === null;
        });

        $debug_info['courses_with_invalid_lecturer_id'] = array_values($invalid_courses);

        // Try the actual query
        $department_id = 7; // ICT department
        $query = "
            SELECT DISTINCT
                c.id,
                c.name,
                c.course_code,
                c.description,
                l.first_name,
                l.last_name,
                CONCAT(l.first_name, ' ', l.last_name) as lecturer_name
            FROM courses c
            INNER JOIN lecturers l ON c.lecturer_id = l.id
            WHERE c.department_id = ?
            ORDER BY c.name
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$department_id]);
        $working_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $debug_info['working_query_results'] = $working_courses;
        $debug_info['working_query_count'] = count($working_courses);

        return [
            'status' => 'success',
            'message' => 'Debug information retrieved',
            'data' => $debug_info
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Debug failed: ' . $e->getMessage(),
            'data' => $debug_info
        ];
    }
}

/**
 * Simple test to get courses without validation
 */
function handleTestCourses(PDO $pdo): array {
    try {
        // Simple query to get all courses
        $stmt = $pdo->query("
            SELECT
                c.id,
                c.name,
                c.course_code,
                c.description,
                c.department_id,
                c.lecturer_id,
                'Unknown Lecturer' as lecturer_name
            FROM courses c
            ORDER BY c.name
        ");

        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status' => 'success',
            'message' => 'Test courses retrieved successfully',
            'data' => $courses,
            'count' => count($courses)
        ];

    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Test failed: ' . $e->getMessage(),
            'data' => []
        ];
    }
}
?>