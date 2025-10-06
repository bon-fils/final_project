<?php
/**
 * Course Details API
 * Returns detailed information about a specific course
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once "../config.php";

try {
    if (!isset($_GET['course_id']) || !is_numeric($_GET['course_id'])) {
        throw new Exception('Invalid course ID');
    }

    $course_id = (int)$_GET['course_id'];

    // Get course basic information
    $courseStmt = $pdo->prepare("
        SELECT
            c.id, c.name, c.course_code, c.credits, c.duration_hours, c.description,
            d.name as department_name
        FROM courses c
        INNER JOIN departments d ON c.department_id = d.id
        WHERE c.id = ? AND c.status = 'active'
    ");
    $courseStmt->execute([$course_id]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        throw new Exception('Course not found');
    }

    // Get course statistics
    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT s.id) as total_sessions,
            COUNT(ar.id) as total_attendance,
            COUNT(DISTINCT st.id) as enrolled_students,
            ROUND(AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END), 1) as attendance_rate,
            MAX(s.session_date) as last_session_date,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count
        FROM courses c
        LEFT JOIN attendance_sessions s ON c.id = s.course_id
        LEFT JOIN attendance_records ar ON s.id = ar.session_id
        LEFT JOIN students st ON st.option_id = c.option_id
        WHERE c.id = ?
    ");
    $statsStmt->execute([$course_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Get recent sessions
    $sessionsStmt = $pdo->prepare("
        SELECT
            s.session_date,
            s.start_time,
            s.end_time,
            COUNT(ar.id) as attendance_count,
            ROUND(AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END), 1) as session_rate
        FROM attendance_sessions s
        LEFT JOIN attendance_records ar ON s.id = ar.session_id
        WHERE s.course_id = ?
        GROUP BY s.id
        ORDER BY s.session_date DESC, s.start_time DESC
        LIMIT 5
    ");
    $sessionsStmt->execute([$course_id]);
    $recentSessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get enrolled students
    $studentsStmt = $pdo->prepare("
        SELECT
            u.first_name,
            u.last_name,
            u.username,
            COUNT(ar.id) as total_sessions_attended,
            ROUND(AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END), 1) as attendance_rate
        FROM students st
        INNER JOIN users u ON st.user_id = u.id
        LEFT JOIN attendance_sessions s ON st.option_id = s.option_id AND s.course_id = ?
        LEFT JOIN attendance_records ar ON s.id = ar.session_id AND ar.student_id = st.id
        WHERE st.option_id IN (
            SELECT option_id FROM courses WHERE id = ?
        )
        GROUP BY st.id, u.first_name, u.last_name, u.username
        ORDER BY u.first_name, u.last_name
    ");
    $studentsStmt->execute([$course_id, $course_id]);
    $students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'course' => $course,
        'stats' => [
            'students' => $stats['enrolled_students'] ?? 0,
            'sessions' => $stats['total_sessions'] ?? 0,
            'attendance_rate' => $stats['attendance_rate'] ?? 0,
            'present_count' => $stats['present_count'] ?? 0,
            'absent_count' => $stats['absent_count'] ?? 0,
            'last_session' => $stats['last_session_date'] ? date('M d, Y', strtotime($stats['last_session_date'])) : null
        ],
        'recent_sessions' => $recentSessions,
        'students' => $students
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>