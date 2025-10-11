<?php
session_start();
require_once "config.php";
require_once "session_check.php";

echo "<h1>Session Start Debug</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ User not logged in</p>";
    exit;
}

echo "<p>✅ User logged in: ID " . $_SESSION['user_id'] . ", Role: " . $_SESSION['role'] . "</p>";

// Check database connection
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>✅ Database connection OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
    exit;
}

// Check required tables
$tables = ['departments', 'options', 'courses', 'lecturers', 'students', 'attendance_sessions', 'attendance_records'];
foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        echo "<p>✅ Table '$table' exists</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Table '$table' missing: " . $e->getMessage() . "</p>";
    }
}

// Check user's department assignment
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if ($user_role === 'admin') {
    echo "<p>✅ User is admin - can access all departments</p>";
} else {
    // Check lecturer assignment
    $stmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lecturer) {
        echo "<p>✅ User is lecturer in department: " . $lecturer['department_id'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ User is not assigned to any department as lecturer</p>";
    }

    // Check HOD assignment
    $stmt = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
    $stmt->execute([$user_id]);
    $hod_dept = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($hod_dept) {
        echo "<p>✅ User is HOD of department: " . $hod_dept['id'] . "</p>";
    } else {
        echo "<p>⚠️ User is not HOD of any department</p>";
    }
}

// Test API call
echo "<h2>Test API Call</h2>";
echo "<form method='POST' action='api/attendance-session-api.php?action=start_session'>";
echo "<input type='hidden' name='csrf_token' value='" . generate_csrf_token() . "'>";
echo "<input type='hidden' name='department_id' value='1'>";
echo "<input type='hidden' name='option_id' value='1'>";
echo "<input type='hidden' name='course_id' value='1'>";
echo "<input type='hidden' name='classLevel' value='Year 1'>";
echo "<button type='submit'>Test Start Session</button>";
echo "</form>";

function generate_csrf_token() {
    return bin2hex(random_bytes(32));
}
?>