<?php
/**
 * Test Script for User Management System
 * This script tests the backend functionality
 */

require_once "config.php";
require_once "session_check.php";

// Only allow admins to access this test
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admin role required.");
}

echo "<h1>User Management System - Backend Test</h1>";

try {
    // Test 1: Check database connection
    echo "<h2>Test 1: Database Connection</h2>";
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Database connection successful<br><br>";

    // Test 2: Check required tables
    echo "<h2>Test 2: Required Tables</h2>";

    $tables = ['users', 'students', 'lecturers'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("DESCRIBE $table");
            echo "✅ $table table exists<br>";
        } catch (Exception $e) {
            echo "❌ $table table missing: " . $e->getMessage() . "<br>";
        }
    }
    echo "<br>";

    // Test 3: Check users table structure
    echo "<h2>Test 3: Users Table Structure</h2>";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    $requiredColumns = ['id', 'username', 'email', 'password', 'role', 'status', 'created_at', 'updated_at', 'last_login'];
    foreach ($requiredColumns as $col) {
        if (in_array($col, $columnNames)) {
            echo "✅ $col column exists<br>";
        } else {
            echo "❌ $col column missing<br>";
        }
    }
    echo "<br>";

    // Test 4: Test user statistics function
    echo "<h2>Test 4: User Statistics</h2>";
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();
    echo "Total users in database: $totalUsers<br>";

    $stmt = $pdo->prepare("SELECT role, status, COUNT(*) as count FROM users GROUP BY role, status");
    $stmt->execute();
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stats as $stat) {
        echo "Role: {$stat['role']}, Status: {$stat['status']}, Count: {$stat['count']}<br>";
    }
    echo "<br>";

    // Test 5: Test AJAX endpoints
    echo "<h2>Test 5: AJAX Endpoints</h2>";
    echo "<p>Testing AJAX endpoints requires JavaScript. Please use the browser console:</p>";
    echo "<pre>
// Test getting users
fetch('manage-users.php?ajax=1&action=get_users')
  .then(r => r.json())
  .then(d => console.log('Users:', d));

// Test user statistics
fetch('manage-users.php?ajax=1&action=get_users')
  .then(r => r.json())
  .then(d => console.log('Stats:', d.stats));
    </pre>";
    echo "<br>";

    echo "<h2>✅ Backend tests completed!</h2>";
    echo "<p><a href='manage-users.php'>Go to User Management Interface</a></p>";

} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
    echo "<p>Please check your database configuration and run setup_user_tables.php first.</p>";
    echo "<p><a href='setup_user_tables.php'>Run Database Setup</a></p>";
}
?>