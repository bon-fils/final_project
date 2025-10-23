<?php
/**
 * Get Student Session-by-Session Attendance Details
 * Shows which sessions the student attended and which they missed
 */
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'hod', 'admin']);

header('Content-Type: application/json');

$student_id = $_GET['student_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;

if (!$student_id || !$course_id) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Student ID and Course ID are required'
    ]);
    exit();
}

try {
    global $pdo;
    
    // Get student info
    $student_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.reg_no,
            s.student_id_number,
            CONCAT(u.first_name, ' ', u.last_name) as student_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $student_stmt->execute([$student_id]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Student not found'
        ]);
        exit();
    }
    
    // Get all sessions for this course
    $sessions_stmt = $pdo->prepare("
        SELECT 
            id,
            session_date,
            start_time,
            end_time
        FROM attendance_sessions
        WHERE course_id = ?
        ORDER BY session_date DESC
    ");
    $sessions_stmt->execute([$course_id]);
    $sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get attendance records for this student in this course
    $attendance_stmt = $pdo->prepare("
        SELECT 
            ar.session_id,
            ar.status,
            ar.recorded_at
        FROM attendance_records ar
        JOIN attendance_sessions ats ON ar.session_id = ats.id
        WHERE ats.course_id = ?
          AND ar.student_id = ?
    ");
    $attendance_stmt->execute([$course_id, $student_id]);
    $attendance_records = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of session_id => attendance record
    $attendance_map = [];
    foreach ($attendance_records as $record) {
        $attendance_map[$record['session_id']] = $record;
    }
    
    // Build session details with attendance status
    $session_details = [];
    $present_count = 0;
    $absent_count = 0;
    
    foreach ($sessions as $session) {
        $session_id = $session['id'];
        
        if (isset($attendance_map[$session_id])) {
            // Student has a record for this session
            $status = $attendance_map[$session_id]['status'];
            $marked_at = $attendance_map[$session_id]['recorded_at'];
            
            if ($status === 'present') {
                $present_count++;
            }
        } else {
            // No record = absent
            $status = 'absent';
            $marked_at = null;
            $absent_count++;
        }
        
        $session_details[] = [
            'session_id' => $session_id,
            'session_date' => $session['session_date'],
            'start_time' => $session['start_time'],
            'end_time' => $session['end_time'],
            'status' => $status,
            'marked_at' => $marked_at
        ];
    }
    
    // Calculate attendance percentage
    $total_sessions = count($sessions);
    $attendance_percentage = $total_sessions > 0 
        ? round(($present_count / $total_sessions) * 100, 1) 
        : 0;
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'student' => $student,
            'sessions' => $session_details,
            'present_count' => $present_count,
            'absent_count' => $absent_count,
            'total_sessions' => $total_sessions,
            'attendance_percentage' => $attendance_percentage
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error fetching session details: ' . $e->getMessage()
    ]);
}
?>
