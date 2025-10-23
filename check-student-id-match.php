<?php
require_once "config.php";

echo "<h2>Checking Student ID Matching</h2>";

// Get a sample attendance record
$sample = $pdo->query("
    SELECT ar.student_id, ar.status, s.id as student_table_id, s.reg_no, s.student_id_number
    FROM attendance_records ar
    LEFT JOIN students s ON ar.student_id = s.reg_no
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Matching by reg_no (ar.student_id = s.reg_no):</h3>";
echo "<pre>";
print_r($sample);
echo "</pre>";

// Try matching by id
$sample2 = $pdo->query("
    SELECT ar.student_id, ar.status, s.id as student_table_id, s.reg_no, s.student_id_number
    FROM attendance_records ar
    LEFT JOIN students s ON ar.student_id = s.id
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Matching by id (ar.student_id = s.id):</h3>";
echo "<pre>";
print_r($sample2);
echo "</pre>";

// Check what values are actually in attendance_records.student_id
$values = $pdo->query("
    SELECT DISTINCT student_id FROM attendance_records LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Actual values in attendance_records.student_id:</h3>";
echo "<pre>";
print_r($values);
echo "</pre>";
?>
