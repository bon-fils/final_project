<?php
require_once "config.php";

$course_id = 11; // ICT101

echo "<h2>Debugging Course ID: $course_id</h2>";

// Check attendance_sessions
echo "<h3>1. Attendance Sessions for Course $course_id:</h3>";
$sessions_stmt = $pdo->prepare("
    SELECT * FROM attendance_sessions 
    WHERE course_id = ?
    ORDER BY session_date DESC
");
$sessions_stmt->execute([$course_id]);
$sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($sessions);
echo "</pre>";
echo "<p><strong>Total Sessions: " . count($sessions) . "</strong></p>";

// Check attendance_records
echo "<h3>2. Attendance Records for Course $course_id:</h3>";
$records_stmt = $pdo->prepare("
    SELECT ar.*, ats.course_id, ats.session_date
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ar.session_id = ats.id
    WHERE ats.course_id = ?
    ORDER BY ar.recorded_at DESC
    LIMIT 20
");
$records_stmt->execute([$course_id]);
$records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($records);
echo "</pre>";
echo "<p><strong>Total Records: " . count($records) . "</strong></p>";

// Check student attendance stats
echo "<h3>3. Student Attendance Stats:</h3>";
$stats_stmt = $pdo->prepare("
    SELECT 
        ar.student_id,
        COUNT(*) as total_records,
        SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
        SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ar.session_id = ats.id
    WHERE ats.course_id = ?
    GROUP BY ar.student_id
");
$stats_stmt->execute([$course_id]);
$stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($stats);
echo "</pre>";

// Check if student_id matches reg_no
echo "<h3>4. Sample Student Data:</h3>";
$student_stmt = $pdo->prepare("
    SELECT id, reg_no, student_id_number, user_id
    FROM students
    WHERE reg_no IN ('22RP05419', 'RR33RR', '33RRRRR')
");
$student_stmt->execute();
$students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($students);
echo "</pre>";
?>
