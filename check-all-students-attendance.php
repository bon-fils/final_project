<?php
require_once "config.php";

$course_id = 11;

echo "<h2>Checking All Students Attendance for Course $course_id</h2>";

// Get all students for this course
$students_stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.reg_no,
        s.student_id_number,
        CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.option_id = 17
      AND CAST(s.year_level AS UNSIGNED) = 3
      AND s.status = 'active'
    ORDER BY u.first_name, u.last_name
");
$students_stmt->execute();
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Students in Option 17, Year 3:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Reg No</th><th>Name</th><th>Has Records?</th><th>Present</th><th>Absent</th></tr>";

foreach ($students as $student) {
    // Check attendance records
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
    
    $has_records = $attendance['total_records'] > 0 ? 'YES' : 'NO';
    
    echo "<tr>";
    echo "<td>{$student['id']}</td>";
    echo "<td>{$student['reg_no']}</td>";
    echo "<td>{$student['student_name']}</td>";
    echo "<td><strong>{$has_records}</strong></td>";
    echo "<td>{$attendance['present_count']}</td>";
    echo "<td>{$attendance['absent_count']}</td>";
    echo "</tr>";
}

echo "</table>";

// Show all attendance records for this course
echo "<h3>All Attendance Records for Course $course_id:</h3>";
$all_records = $pdo->prepare("
    SELECT 
        ar.id,
        ar.student_id,
        ar.status,
        ar.recorded_at,
        ats.session_date,
        s.reg_no,
        CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ar.session_id = ats.id
    LEFT JOIN students s ON ar.student_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE ats.course_id = ?
    ORDER BY ats.session_date, ar.student_id
");
$all_records->execute([$course_id]);
$records = $all_records->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Record ID</th><th>Student ID</th><th>Reg No</th><th>Name</th><th>Status</th><th>Session Date</th><th>Recorded At</th></tr>";

foreach ($records as $record) {
    echo "<tr>";
    echo "<td>{$record['id']}</td>";
    echo "<td>{$record['student_id']}</td>";
    echo "<td>{$record['reg_no']}</td>";
    echo "<td>{$record['student_name']}</td>";
    echo "<td><strong>{$record['status']}</strong></td>";
    echo "<td>{$record['session_date']}</td>";
    echo "<td>{$record['recorded_at']}</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p><strong>Total Records Found: " . count($records) . "</strong></p>";
?>
