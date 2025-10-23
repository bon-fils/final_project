<?php
require_once "config.php";

$course_id = 11;
$test_reg_no = '22RP05419'; // First student from your console log

echo "<h2>Testing Attendance Join for Student: $test_reg_no</h2>";

// Test 1: Direct query with reg_no
echo "<h3>Test 1: Query with reg_no</h3>";
$stmt1 = $pdo->prepare("
    SELECT 
        ar.student_id,
        ar.status,
        ar.recorded_at,
        ats.session_date
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ar.session_id = ats.id
    WHERE ats.course_id = ? 
      AND ar.student_id = ?
");
$stmt1->execute([$course_id, $test_reg_no]);
$results1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);
echo "<p>Found " . count($results1) . " records</p>";
echo "<pre>";
print_r($results1);
echo "</pre>";

// Test 2: Get all attendance records for this course
echo "<h3>Test 2: All attendance records for course $course_id</h3>";
$stmt2 = $pdo->prepare("
    SELECT 
        ar.student_id,
        ar.status,
        COUNT(*) as count
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ar.session_id = ats.id
    WHERE ats.course_id = ?
    GROUP BY ar.student_id, ar.status
");
$stmt2->execute([$course_id]);
$results2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($results2);
echo "</pre>";

// Test 3: Check student table
echo "<h3>Test 3: Student data</h3>";
$stmt3 = $pdo->prepare("SELECT id, reg_no, student_id_number FROM students WHERE reg_no = ?");
$stmt3->execute([$test_reg_no]);
$student = $stmt3->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($student);
echo "</pre>";

// Test 4: Try matching by student.id instead
if ($student) {
    echo "<h3>Test 4: Query with student.id instead of reg_no</h3>";
    $stmt4 = $pdo->prepare("
        SELECT 
            ar.student_id,
            ar.status,
            COUNT(*) as count
        FROM attendance_records ar
        JOIN attendance_sessions ats ON ar.session_id = ats.id
        WHERE ats.course_id = ? 
          AND ar.student_id = ?
    ");
    $stmt4->execute([$course_id, $student['id']]);
    $results4 = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    echo "<p>Found " . count($results4) . " records using student.id = " . $student['id'] . "</p>";
    echo "<pre>";
    print_r($results4);
    echo "</pre>";
}
?>
