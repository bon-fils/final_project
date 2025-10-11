<?php
/**
 * Enhanced Attendance Reports - Modern & Comprehensive
 * Provides detailed attendance analytics with improved UI and functionality
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'hod', 'admin']);

// Get user information
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

// Validate user session
if (!$user_id) {
    error_log("Invalid user_id in attendance-reports");
    session_destroy();
    header("Location: index.php?error=invalid_session");
    exit();
}

// Initialize user context
try {
    $user_context = getUserContext($pdo, $user_id, $user_role);
    $lecturer_id = $user_context['lecturer_id'];
    $department_id = $user_context['department_id'];
    $is_admin = $user_role === 'admin';
} catch (Exception $e) {
    // Handle user context errors gracefully
    error_log("User context error in attendance-reports: " . $e->getMessage());
    $user_context_error = $e->getMessage(); // Use the actual error message
    $lecturer_id = null;
    $department_id = null;
    $is_admin = false;
}

/**
 * Get user context and permissions
 */
function getUserContext($pdo, $user_id, $user_role) {
    try {
        if ($user_role === 'admin') {
            return ['lecturer_id' => null, 'department_id' => null];
        }

        // Get lecturer information
        $stmt = $pdo->prepare("
            SELECT l.id as lecturer_id, l.department_id, l.id_number as lecturer_name
            FROM lecturers l
            WHERE l.user_id = :user_id
        ");
        $stmt->execute(['user_id' => $user_id]);
        $lecturer_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturer_data) {
            // Try to create lecturer record if missing
            createLecturerRecord($pdo, $user_id);
            // Retry fetch
            $stmt->execute(['user_id' => $user_id]);
            $lecturer_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturer_data) {
                // If still no lecturer record, log more details and throw exception
                error_log("Failed to create lecturer record for user_id: $user_id, role: $user_role");

                // Check if user exists and has correct role
                $userCheckStmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
                $userCheckStmt->execute([$user_id]);
                $userInfo = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
                error_log("User info: " . json_encode($userInfo));

                throw new Exception("Your lecturer account is not properly configured. Please contact your system administrator to set up your lecturer profile.");
            }
        }

        $_SESSION['lecturer_id'] = $lecturer_data['lecturer_id'];
        // Add first_name and last_name for backward compatibility
        $lecturer_data['first_name'] = $lecturer_data['lecturer_name'];
        $lecturer_data['last_name'] = '';
        return $lecturer_data;

    } catch (PDOException $e) {
        error_log("Error getting user context: " . $e->getMessage());
        error_log("SQL Query error details: " . $e->getMessage());
        error_log("User ID: $user_id, Role: $user_role");
        throw new Exception("Database error occurred while loading user information: " . $e->getMessage());
    }
}

/**
 * Create lecturer record if missing
 */
function createLecturerRecord($pdo, $user_id) {
    try {
        // First get user information
        $userStmt = $pdo->prepare("SELECT username, role FROM users WHERE id = :user_id");
        $userStmt->execute(['user_id' => $user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("User not found for lecturer creation: $user_id");
            return;
        }

        // Insert lecturer record with proper columns
        $stmt = $pdo->prepare("
            INSERT INTO lecturers (id_number, gender, dob, department_id, user_id, created_at, updated_at)
            VALUES (:id_number, 'Male', CURDATE(), 1, :user_id, NOW(), NOW())
            ON DUPLICATE KEY UPDATE user_id = user_id
        ");
        $stmt->execute([
            'id_number' => $user['username'], // Use username as id_number
            'user_id' => $user_id
        ]);

        error_log("Created lecturer record for user_id: $user_id");
    } catch (PDOException $e) {
        error_log("Error creating lecturer record: " . $e->getMessage());
    }
}

/**
 * Ensure database schema is up to date
 */
function ensureDatabaseSchema($pdo) {
    try {
        // Add lecturer_id column to courses if it doesn't exist
        $pdo->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS lecturer_id INT NULL AFTER department_id");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_lecturer_id ON courses(lecturer_id)");
        $pdo->exec("ALTER TABLE courses ADD CONSTRAINT IF NOT EXISTS fk_courses_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE SET NULL");
    } catch (PDOException $e) {
        error_log("Schema update warning: " . $e->getMessage());
    }
}

/**
 * Get available departments for the current user
 */
function getAvailableDepartments($pdo, $lecturer_id, $is_admin) {
    try {
        if ($is_admin) {
            // Admin can see all departments
            $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
        } else {
            // Lecturer can only see their department
            $stmt = $pdo->prepare("
                SELECT d.id, d.name
                FROM departments d
                INNER JOIN lecturers l ON d.id = l.department_id
                WHERE l.id = :lecturer_id
                ORDER BY d.name ASC
            ");
            $stmt->execute(['lecturer_id' => $lecturer_id]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get options for a specific department
 */
function getOptionsForDepartment($pdo, $department_id, $lecturer_id, $is_admin) {
    try {
        $query = "SELECT DISTINCT o.id, o.name FROM options o";
        $params = [];

        if (!$is_admin && $lecturer_id) {
            // Lecturers can only see options for courses they teach
            $query .= "
                INNER JOIN courses c ON o.id = c.option_id
                WHERE c.lecturer_id = :lecturer_id AND o.department_id = :department_id";
            $params['lecturer_id'] = $lecturer_id;
            $params['department_id'] = $department_id;
        } else {
            // Admins can see all options in the department
            $query .= " WHERE o.department_id = :department_id";
            $params['department_id'] = $department_id;
        }

        $query .= " ORDER BY o.name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching options: " . $e->getMessage());
        return [];
    }
}

/**
 * Get available classes (year levels) for the current user
 */
function getAvailableClasses($pdo, $lecturer_id, $is_admin) {
    try {
        if ($is_admin) {
            // Admin can see all classes
            $stmt = $pdo->query("
                SELECT DISTINCT year_level as id, CONCAT('Year ', year_level) as name
                FROM students
                WHERE year_level IS NOT NULL
                ORDER BY year_level ASC
            ");
        } else {
            // Lecturer can only see classes in their department
            $stmt = $pdo->prepare("
                SELECT DISTINCT s.year_level as id, CONCAT('Year ', s.year_level) as name
                FROM students s
                INNER JOIN lecturers l ON s.department_id = l.department_id
                WHERE l.id = :lecturer_id AND s.year_level IS NOT NULL
                ORDER BY s.year_level ASC
            ");
            $stmt->execute(['lecturer_id' => $lecturer_id]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching classes: " . $e->getMessage());
        return [];
    }
}

/**
 * Get courses for a specific class/year level
 */
function getCoursesForClass($pdo, $class_id, $lecturer_id, $is_admin) {
    try {
        if (!$is_admin && $lecturer_id) {
            // Lecturers can only see courses they teach in the specified class
            $query = "
                SELECT DISTINCT c.id, c.course_name as name, c.course_code
                FROM courses c
                WHERE c.lecturer_id = :lecturer_id AND c.option_id IN (SELECT s.option_id FROM students s WHERE s.year_level = :year_level AND s.department_id = (SELECT l.department_id FROM lecturers l WHERE l.id = :lecturer_id))
            ";
            $params = ['year_level' => $class_id, 'lecturer_id' => $lecturer_id];
        } else {
            // Admins can see all courses for the class
            $query = "
                SELECT DISTINCT c.id, c.course_name as name, c.course_code
                FROM courses c
                INNER JOIN options o ON c.option_id = o.id
                INNER JOIN students s ON o.id = s.option_id
                WHERE s.year_level = :year_level
            ";
            $params = ['year_level' => $class_id];
        }

        $query .= " ORDER BY c.course_name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Assign courses to lecturer if not already assigned
 */
function assignCoursesToLecturer($pdo, $lecturer_id, $department_id) {
    if (!$lecturer_id || !$department_id) return;

    try {
        $stmt = $pdo->prepare("
            UPDATE courses
            SET lecturer_id = :lecturer_id
            WHERE lecturer_id IS NULL AND department_id = :department_id
        ");
        $stmt->execute([
            'lecturer_id' => $lecturer_id,
            'department_id' => $department_id
        ]);
    } catch (PDOException $e) {
        error_log("Error assigning courses: " . $e->getMessage());
    }
}

// Initialize database and get parameters
ensureDatabaseSchema($pdo);
if (!$is_admin) {
    assignCoursesToLecturer($pdo, $lecturer_id, $department_id);
}

// Get filter parameters
$reportType = $_GET['report_type'] ?? 'class'; // department, option, class, course
$selectedDepartmentId = $_GET['department_id'] ?? null;
$selectedOptionId = $_GET['option_id'] ?? null;
$selectedClassId = $_GET['class_id'] ?? null;
$selectedCourseId = $_GET['course_id'] ?? null;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

// Get available data based on user role
$departments = getAvailableDepartments($pdo, $lecturer_id, $is_admin);
$options = $selectedDepartmentId ? getOptionsForDepartment($pdo, $selectedDepartmentId, $lecturer_id, $is_admin) : [];
$classes = getAvailableClasses($pdo, $lecturer_id, $is_admin);

// For course selection, lecturers should only see courses they teach
if (!$is_admin && $lecturer_id) {
    // Get courses assigned to this lecturer
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT c.id, c.course_name as name, c.course_code
            FROM courses c
            WHERE c.lecturer_id = ?
            ORDER BY c.course_name ASC
        ");
        $stmt->execute([$lecturer_id]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching lecturer courses: " . $e->getMessage());
        $courses = [];
    }
} else {
    // Admins can see all courses for the selected class
    $courses = $selectedClassId ? getCoursesForClass($pdo, $selectedClassId, $lecturer_id, $is_admin) : [];
}

/**
 * Generate comprehensive attendance report based on report type
 */
function generateAttendanceReport($pdo, $report_type, $filters, $lecturer_id, $is_admin) {
    try {
        $start_date = $filters['start_date'] ?? null;
        $end_date = $filters['end_date'] ?? null;

        switch ($report_type) {
            case 'department':
                return generateDepartmentReport($pdo, $filters['department_id'], $start_date, $end_date, $lecturer_id, $is_admin);
            case 'option':
                return generateOptionReport($pdo, $filters['option_id'], $start_date, $end_date, $lecturer_id, $is_admin);
            case 'class':
                return generateClassReport($pdo, $filters['class_id'], $start_date, $end_date, $lecturer_id, $is_admin);
            case 'course':
                return generateCourseReport($pdo, $filters['course_id'], $start_date, $end_date, $lecturer_id, $is_admin);
            default:
                return ['error' => 'Invalid report type'];
        }

    } catch (PDOException $e) {
        error_log("Error generating attendance report: " . $e->getMessage());
        return ['error' => 'Database error occurred'];
    }
}

/**
 * Generate department-wide attendance report
 */
function generateDepartmentReport($pdo, $department_id, $start_date, $end_date, $lecturer_id, $is_admin) {
    // Get all students in the department
    $students = getStudentsForDepartment($pdo, $department_id, $lecturer_id, $is_admin);
    if (empty($students)) {
        return ['students' => [], 'sessions' => [], 'attendance' => [], 'summary' => []];
    }

    // Get all courses in the department
    $courses = getCoursesForDepartment($pdo, $department_id, $lecturer_id, $is_admin);

    // Get all sessions for these courses
    $sessions = getSessionsForCourses($pdo, array_keys($courses), $start_date, $end_date);

    // Get attendance records
    $attendance_records = getAttendanceRecordsForCourses($pdo, array_keys($courses), array_keys($students), $start_date, $end_date);

    return [
        'department_info' => ['id' => $department_id, 'name' => getDepartmentName($pdo, $department_id)],
        'students' => $students,
        'courses' => $courses,
        'sessions' => $sessions,
        'attendance' => processAttendanceData($students, $sessions, $attendance_records),
        'summary' => calculateAttendanceSummary($students, $sessions, $attendance_records),
        'date_range' => ['start' => $start_date, 'end' => $end_date]
    ];
}

/**
 * Generate option-specific attendance report
 */
function generateOptionReport($pdo, $option_id, $start_date, $end_date, $lecturer_id, $is_admin) {
    // Get all students in the option
    $students = getStudentsForOption($pdo, $option_id, $lecturer_id, $is_admin);
    if (empty($students)) {
        return ['students' => [], 'sessions' => [], 'attendance' => [], 'summary' => []];
    }

    // Get all courses for this option
    $courses = getCoursesForOption($pdo, $option_id, $lecturer_id, $is_admin);

    // Get all sessions for these courses
    $sessions = getSessionsForCourses($pdo, array_keys($courses), $start_date, $end_date);

    // Get attendance records
    $attendance_records = getAttendanceRecordsForCourses($pdo, array_keys($courses), array_keys($students), $start_date, $end_date);

    return [
        'option_info' => ['id' => $option_id, 'name' => getOptionName($pdo, $option_id)],
        'students' => $students,
        'courses' => $courses,
        'sessions' => $sessions,
        'attendance' => processAttendanceData($students, $sessions, $attendance_records),
        'summary' => calculateAttendanceSummary($students, $sessions, $attendance_records),
        'date_range' => ['start' => $start_date, 'end' => $end_date]
    ];
}

/**
 * Generate class-specific attendance report
 */
function generateClassReport($pdo, $class_id, $start_date, $end_date, $lecturer_id, $is_admin) {
    // Get all students in the class
    $students = getStudentsForClass($pdo, $class_id, $lecturer_id, $is_admin);
    if (empty($students)) {
        return ['students' => [], 'sessions' => [], 'attendance' => [], 'summary' => []];
    }

    // Get all courses for this class
    $courses = getCoursesForClass($pdo, $class_id, $lecturer_id, $is_admin);

    // Get all sessions for these courses
    $sessions = getSessionsForCourses($pdo, array_keys($courses), $start_date, $end_date);

    // Get attendance records
    $attendance_records = getAttendanceRecordsForCourses($pdo, array_keys($courses), array_keys($students), $start_date, $end_date);

    return [
        'class_info' => ['id' => $class_id, 'name' => "Year {$class_id}"],
        'students' => $students,
        'courses' => $courses,
        'sessions' => $sessions,
        'attendance' => processAttendanceData($students, $sessions, $attendance_records),
        'summary' => calculateAttendanceSummary($students, $sessions, $attendance_records),
        'date_range' => ['start' => $start_date, 'end' => $end_date]
    ];
}

/**
 * Generate course-specific attendance report (original functionality)
 */
function generateCourseReport($pdo, $course_id, $start_date, $end_date, $lecturer_id, $is_admin) {
    // Get course information
    $course_info = getCourseInfo($pdo, $course_id);
    if (!$course_info) {
        return ['error' => 'Course not found'];
    }

    // Check if lecturer has permission to view this course
    if (!$is_admin && $lecturer_id && $course_info['lecturer_id'] != $lecturer_id) {
        return ['error' => 'You do not have permission to view reports for this course'];
    }

    // Get all students who should attend this course (based on their class/year)
    $students = getStudentsForCourse($pdo, $course_info['year_level'] ?? null, $course_id, $lecturer_id, $is_admin);
    if (empty($students)) {
        return ['students' => [], 'sessions' => [], 'attendance' => [], 'summary' => []];
    }

    // Get all attendance sessions for the course within date range
    $sessions = getAttendanceSessions($pdo, $course_id, $start_date, $end_date);

    // Get attendance records
    $attendance_records = getAttendanceRecords($pdo, $course_id, array_keys($students), $start_date, $end_date);

    // Build comprehensive report
    return [
        'course_info' => $course_info,
        'students' => $students,
        'sessions' => $sessions,
        'attendance' => processAttendanceData($students, $sessions, $attendance_records),
        'summary' => calculateAttendanceSummary($students, $sessions, $attendance_records),
        'date_range' => ['start' => $start_date, 'end' => $end_date]
    ];
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

// Generate report data
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
    $report_data = generateAttendanceReport($pdo, $reportType, $filters, $lecturer_id, $is_admin);
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
    <title>Attendance Reports | <?php echo $user_role === 'admin' ? 'Admin' : 'Lecturer'; ?> | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="css/attendance-reports.css" rel="stylesheet" />
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
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2">Generating report...</div>
    </div>

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
                <li><a href="admin-dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard Overview</a></li>
                <li class="nav-section"><i class="fas fa-users me-2"></i>User Management</li>
                <li><a href="manage-users.php"><i class="fas fa-users-cog"></i>Manage Users</a></li>
                <li><a href="register-student.php"><i class="fas fa-user-plus"></i>Register Student</a></li>
                <li class="nav-section"><i class="fas fa-sitemap me-2"></i>Organization</li>
                <li><a href="manage-departments.php"><i class="fas fa-building"></i>Departments</a></li>
                <li><a href="assign-hod.php"><i class="fas fa-user-tie"></i>Assign HOD</a></li>
                <li class="nav-section"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</li>
                <li><a href="admin-reports.php"><i class="fas fa-chart-line"></i>Analytics Reports</a></li>
                <li><a href="attendance-reports.php" class="active"><i class="fas fa-calendar-check"></i>Attendance Reports</a></li>
                <li class="nav-section"><i class="fas fa-cog me-2"></i>System</li>
                <li><a href="system-logs.php"><i class="fas fa-file-code"></i>System Logs</a></li>
                <li><a href="hod-leave-management.php"><i class="fas fa-clipboard-list"></i>Leave Management</a></li>
            <?php else : ?>
                <li><a href="lecturer-dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                <li class="nav-section"><i class="fas fa-graduation-cap me-2"></i>Academic</li>
                <li><a href="lecturer-my-courses.php"><i class="fas fa-book"></i>My Courses</a></li>
                <li><a href="attendance-session.php"><i class="fas fa-video"></i>Attendance Session</a></li>
                <li><a href="attendance-reports.php" class="active"><i class="fas fa-chart-bar"></i>Attendance Reports</a></li>
                <li class="nav-section"><i class="fas fa-cog me-2"></i>Management</li>
                <li><a href="leave-requests.php"><i class="fas fa-clipboard-list"></i>Leave Requests</a></li>
            <?php endif; ?>
            <li class="nav-section"><i class="fas fa-sign-out-alt me-2"></i>Account</li>
            <li><a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        </ul>
    </div>

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Global variables
        const reportData = <?= json_encode($report_data ?? []) ?>;
        let detailedView = false;

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

        // Initialize charts when data is available
        <?php if (!empty($report_data) && !isset($report_data['error'])) : ?>
        document.addEventListener('DOMContentLoaded', function() {
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
        });
        <?php endif; ?>

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
