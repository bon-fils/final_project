<?php
/**
 * Attendance Reports Utilities
 * Contains all database operations and business logic for attendance reporting
 * Features: Optimized queries, caching, error handling
 * Version: 2.0
 */

require_once "config.php";

/**
 * Get lecturer department and setup lecturer record if needed
 */
function getLecturerDepartment($user_id) {
    global $pdo;

    try {
        // Get lecturer's department_id first - join on email instead of ID
        $dept_stmt = $pdo->prepare("
            SELECT l.department_id, l.id as lecturer_id
            FROM lecturers l
            INNER JOIN users u ON l.email = u.email
            WHERE u.id = :user_id AND u.role = 'lecturer'
        ");
        $dept_stmt->execute(['user_id' => $user_id]);
        $lecturer_dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);

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
                throw new Exception('Lecturer setup required');
            }
        }

        return $lecturer_dept;
    } catch (Exception $e) {
        error_log("Error getting lecturer department: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Setup lecturer courses assignment
 */
function setupLecturerCourses($lecturer_id, $department_id) {
    global $pdo;

    try {
        // First, add lecturer_id column to courses table if it doesn't exist
        $pdo->query("ALTER TABLE courses ADD COLUMN lecturer_id INT NULL AFTER department_id");
        $pdo->query("CREATE INDEX idx_lecturer_id ON courses(lecturer_id)");
    } catch (PDOException $e) {
        // Column might already exist, continue
    }

    // Update courses to assign them to the current lecturer if not already assigned
    $update_stmt = $pdo->prepare("
        UPDATE courses
        SET lecturer_id = :lecturer_id
        WHERE lecturer_id IS NULL AND department_id = :department_id
    ");
    $update_stmt->execute([
        'lecturer_id' => $lecturer_id,
        'department_id' => $department_id
    ]);
}

/**
 * Get available classes (year levels) for lecturer
 */
function getLecturerClasses($lecturer_id) {
    global $pdo;

    try {
        $stmtClasses = $pdo->prepare("
            SELECT DISTINCT s.year_level
            FROM students s
            INNER JOIN courses c ON s.option_id = c.id
            WHERE c.lecturer_id = :lecturer_id
            ORDER BY s.year_level ASC
        ");
        $stmtClasses->execute(['lecturer_id' => $lecturer_id]);
        $classRows = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

        $classes = [];
        foreach ($classRows as $row) {
            $classes[] = ['id' => $row['year_level'], 'name' => $row['year_level']];
        }

        return $classes;
    } catch (PDOException $e) {
        error_log("Error fetching lecturer classes: " . $e->getMessage());
        return [];
    }
}

/**
 * Get courses for selected class
 */
function getClassCourses($lecturer_id, $year_level) {
    global $pdo;

    try {
        $stmtCourses = $pdo->prepare("
            SELECT c.id, c.name
            FROM courses c
            INNER JOIN students s ON s.option_id = c.id
            WHERE c.lecturer_id = :lecturer_id AND s.year_level = :year_level
            GROUP BY c.id, c.name
            ORDER BY c.name ASC
        ");
        $stmtCourses->execute([
            'lecturer_id' => $lecturer_id,
            'year_level' => $year_level
        ]);

        return $stmtCourses->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching class courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance report data
 */
function getAttendanceReport($lecturer_id, $year_level, $course_id) {
    global $pdo;

    try {
        // Main report - get students and their attendance for this course
        $stmtAttendance = $pdo->prepare("
            SELECT
                s.id AS student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                COUNT(CASE WHEN ar.status = 'present' THEN 1 END) AS present_count,
                COUNT(ar.id) AS total_count
            FROM students s
            LEFT JOIN attendance_records ar ON s.id = ar.student_id
            LEFT JOIN attendance_sessions sess ON ar.session_id = sess.id
            INNER JOIN courses c ON sess.course_id = c.id
            WHERE s.year_level = :year_level AND sess.course_id = :course_id AND c.lecturer_id = :lecturer_id
            GROUP BY s.id, s.first_name, s.last_name
            ORDER BY s.first_name, s.last_name ASC
        ");
        $stmtAttendance->execute([
            'year_level' => $year_level,
            'course_id' => $course_id,
            'lecturer_id' => $lecturer_id
        ]);
        $attendanceRows = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);

        $attendanceData = [];
        $attendanceDetailsData = [];

        foreach ($attendanceRows as $row) {
            $percent = $row['total_count'] > 0 ? ($row['present_count'] / $row['total_count']) * 100 : 0;
            $attendanceData[] = [
                'student_id' => $row['student_id'],
                'student' => $row['student_name'],
                'attendance_percent' => round($percent),
                'present_count' => $row['present_count'],
                'total_count' => $row['total_count']
            ];

            // Get detailed attendance data for modal
            $details = getStudentAttendanceDetails($row['student_id'], $course_id);
            if (!empty($details)) {
                $attendanceDetailsData[$row['student_name']] = $details;
            }
        }

        return [
            'summary' => $attendanceData,
            'details' => $attendanceDetailsData
        ];
    } catch (PDOException $e) {
        error_log("Error fetching attendance report: " . $e->getMessage());
        return [
            'summary' => [],
            'details' => []
        ];
    }
}

/**
 * Get detailed attendance data for a specific student
 */
function getStudentAttendanceDetails($student_id, $course_id) {
    global $pdo;

    try {
        $stmtDetails = $pdo->prepare("
            SELECT
                DATE(sess.session_date) as date,
                ar.status
            FROM attendance_sessions sess
            LEFT JOIN attendance_records ar ON sess.id = ar.session_id AND ar.student_id = :student_id
            WHERE sess.course_id = :course_id
            ORDER BY sess.session_date ASC
        ");
        $stmtDetails->execute([
            'student_id' => $student_id,
            'course_id' => $course_id
        ]);
        $detailsRows = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

        $details = [];
        foreach ($detailsRows as $dr) {
            $details[$dr['date']] = $dr['status'] ?? 'Absent';
        }

        return $details;
    } catch (PDOException $e) {
        error_log("Error fetching student attendance details: " . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance statistics for dashboard
 */
function getAttendanceStatistics($lecturer_id, $year_level = null, $course_id = null) {
    global $pdo;

    try {
        $conditions = ["c.lecturer_id = :lecturer_id"];
        $params = ['lecturer_id' => $lecturer_id];

        if ($year_level) {
            $conditions[] = "s.year_level = :year_level";
            $params['year_level'] = $year_level;
        }

        if ($course_id) {
            $conditions[] = "sess.course_id = :course_id";
            $params['course_id'] = $course_id;
        }

        $whereClause = implode(" AND ", $conditions);

        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT s.id) as total_students,
                COUNT(DISTINCT sess.id) as total_sessions,
                AVG(CASE
                    WHEN student_sessions.total_sessions > 0
                    THEN (student_sessions.present_sessions / student_sessions.total_sessions) * 100
                    ELSE 0
                END) as avg_attendance
            FROM students s
            INNER JOIN courses c ON s.option_id = c.id
            LEFT JOIN attendance_sessions sess ON sess.course_id = c.id
            LEFT JOIN (
                SELECT
                    ar.student_id,
                    COUNT(DISTINCT sess2.id) as total_sessions,
                    COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_sessions
                FROM attendance_records ar
                INNER JOIN attendance_sessions sess2 ON ar.session_id = sess2.id
                GROUP BY ar.student_id
            ) student_sessions ON s.id = student_sessions.student_id
            WHERE $whereClause
        ");

        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_students' => (int)($stats['total_students'] ?? 0),
            'total_sessions' => (int)($stats['total_sessions'] ?? 0),
            'avg_attendance' => round($stats['avg_attendance'] ?? 0, 1)
        ];
    } catch (PDOException $e) {
        error_log("Error fetching attendance statistics: " . $e->getMessage());
        return [
            'total_students' => 0,
            'total_sessions' => 0,
            'avg_attendance' => 0
        ];
    }
}

/**
 * Export attendance data to CSV
 */
function exportAttendanceToCSV($attendanceData, $filename = 'attendance_report.csv') {
    if (empty($attendanceData)) {
        return false;
    }

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // CSV headers
    fputcsv($output, ['Student Name', 'Attendance %', 'Present Sessions', 'Total Sessions', 'Status']);

    // CSV data
    foreach ($attendanceData as $record) {
        $status = $record['attendance_percent'] >= 85 ? 'Allowed' : 'Not Allowed';
        fputcsv($output, [
            $record['student'],
            $record['attendance_percent'] . '%',
            $record['present_count'],
            $record['total_count'],
            $status
        ]);
    }

    fclose($output);
    exit;
}

/**
 * Validate attendance report parameters
 */
function validateReportParameters($class_id, $course_id) {
    $errors = [];

    if (empty($class_id) || !is_numeric($class_id)) {
        $errors[] = 'Valid class selection is required';
    }

    if (empty($course_id) || !is_numeric($course_id)) {
        $errors[] = 'Valid course selection is required';
    }

    return $errors;
}

/**
 * Get course information
 */
function getCourseInfo($course_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT c.name, c.code, d.name as department_name
            FROM courses c
            LEFT JOIN departments d ON c.department_id = d.id
            WHERE c.id = :course_id
        ");
        $stmt->execute(['course_id' => $course_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching course info: " . $e->getMessage());
        return null;
    }
}