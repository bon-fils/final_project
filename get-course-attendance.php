<?php
/**
 * Get Course Attendance Records
 * Fetches all student attendance records for a specific course
 */
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'hod', 'admin']);

header('Content-Type: application/json');

$course_id = $_GET['course_id'] ?? null;

if (!$course_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Course ID is required'
    ]);
    exit();
}

try {
    global $pdo;
    
    // Get course info
    $course_stmt = $pdo->prepare("
        SELECT c.id, c.course_code, c.course_name, c.option_id, c.year
        FROM courses c
        WHERE c.id = ?
    ");
    $course_stmt->execute([$course_id]);
    $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Course not found'
        ]);
        exit();
    }
    
    // Get all students enrolled in this course (based on option and year)
    $students_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.reg_no,
            s.student_id_number,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as student_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.option_id = ? 
          AND CAST(s.year_level AS UNSIGNED) = ?
          AND s.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $students_stmt->execute([$course['option_id'], $course['year']]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all sessions for this course
    $sessions_stmt = $pdo->prepare("
        SELECT id, session_date, start_time, end_time
        FROM attendance_sessions
        WHERE course_id = ?
        ORDER BY session_date DESC
    ");
    $sessions_stmt->execute([$course_id]);
    $sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_sessions = count($sessions);
    
    // Get attendance records for each student
    $student_attendance = [];
    $total_present = 0;
    $total_records = 0;
    
    foreach ($students as $student) {
        // Get attendance stats for this student in this course
        $attendance_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count
            FROM attendance_records ar
            JOIN attendance_sessions ats ON ar.session_id = ats.id
            WHERE ats.course_id = ? 
              AND ar.student_id = ?
        ");
        $attendance_stmt->execute([$course_id, $student['id']]);
        $attendance = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
        
        $present_count = intval($attendance['present_count'] ?? 0);
        $absent_count = intval($attendance['absent_count'] ?? 0);
        $total_records_student = intval($attendance['total_records'] ?? 0);
        
        // Calculate absent count: sessions without any record = absent
        // If student has records for only 1 session out of 4, then 3 are absent
        $actual_absent_count = $total_sessions - $present_count;
        
        $student_attendance[] = [
            'student_id' => $student['id'],
            'reg_no' => $student['reg_no'],
            'student_id_number' => $student['student_id_number'],
            'student_name' => $student['student_name'],
            'total_sessions' => $total_sessions,
            'present_count' => $present_count,
            'absent_count' => $actual_absent_count,
            'attendance_percentage' => $total_sessions > 0 ? round(($present_count / $total_sessions) * 100, 1) : 0
        ];
        
        $total_present += $present_count;
        $total_records += $total_sessions; // Count all sessions as total records
    }
    
    // Calculate average attendance
    $average_attendance = $total_records > 0 ? round(($total_present / $total_records) * 100, 1) : 0;
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'course' => $course,
            'students' => $student_attendance,
            'sessions' => $sessions,
            'total_sessions' => $total_sessions,
            'total_students' => count($students),
            'average_attendance' => $average_attendance
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching attendance records: ' . $e->getMessage()
    ]);
}
?>
