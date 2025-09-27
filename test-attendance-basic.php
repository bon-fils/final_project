<?php
// Simple test page without authentication
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Basic Attendance Test</h1>";
echo "<p>This page tests basic functionality without authentication.</p>";

// Test database connection
try {
    require_once "config.php";
    echo "<p style='color: green;'>✓ Database connection successful</p>";

    // Test a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as user_count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Users in database: " . $result['user_count'] . "</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database error: " . $e->getMessage() . "</p>";
}

echo "<p><a href='attendance-reports.php'>Back to main page</a></p>";
?>