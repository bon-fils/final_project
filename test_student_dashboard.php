<?php
// Test script to check student dashboard access
session_start();
require_once 'config.php';
require_once 'session_check.php';

echo "<h1>Student Dashboard Test</h1>";

// Check session
$user_id = (int)($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? 'none';

echo "<p>User ID: $user_id</p>";
echo "<p>Role: $role</p>";

// Check if user exists
if ($user_id > 0) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, role, status FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<p>User exists: " . ($user ? 'Yes' : 'No') . "</p>";
    if ($user) {
        echo "<p>User details: " . json_encode($user) . "</p>";
    }

    // Check if student record exists
    $stmt = $pdo->prepare("SELECT id, user_id, option_id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<p>Student record exists: " . ($student ? 'Yes' : 'No') . "</p>";
    if ($student) {
        echo "<p>Student details: " . json_encode($student) . "</p>";
    }
}

echo "<p><a href='students-dashboard.php'>Try accessing dashboard</a></p>";
echo "<p><a href='login.php'>Go to login</a></p>";
?>