<?php
/**
 * Test script for HOD Assignment API
 * Tests database connectivity and API responses
 */

require_once "config.php";

// Simple session setup for testing
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'admin';
$_SESSION['role'] = 'admin';

// Mock the session_check functions if they don't exist
if (!function_exists('require_role')) {
    function require_role($roles) {
        // Mock function for testing
        return true;
    }
}

if (!function_exists('check_rate_limit')) {
    function check_rate_limit($key, $window, $max) {
        // Mock function for testing
        return true;
    }
}

if (!function_exists('validate_csrf_token')) {
    function validate_csrf_token($token) {
        // Mock function for testing
        return true;
    }
}

echo "<h1>HOD Assignment API Test</h1>";

// Test 1: Check database connection
echo "<h2>Test 1: Database Connection</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Database connection successful<br>";
    echo "Total departments: " . $result['count'] . "<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 2: Test departments API
echo "<h2>Test 2: Departments API</h2>";
try {
    // Test direct database query first
    $stmt = $pdo->prepare("
        SELECT d.id, d.name,
               u.id as hod_id,
               COALESCE(u.username, 'Not Assigned') as hod_name
        FROM departments d
        LEFT JOIN users u ON d.hod_id = u.id
        ORDER BY d.name
    ");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "✅ Direct database query successful<br>";
    echo "Found " . count($departments) . " departments<br>";

    echo "<h3>Departments from Database:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>HOD ID</th><th>HOD Name</th></tr>";
    foreach ($departments as $dept) {
        echo "<tr>";
        echo "<td>" . $dept['id'] . "</td>";
        echo "<td>" . $dept['name'] . "</td>";
        echo "<td>" . ($dept['hod_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($dept['hod_name'] ?: 'Not Assigned') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test lecturers query
    echo "<h3>Lecturers from Database:</h3>";
    $stmt = $pdo->prepare("
        SELECT id, username, email, role,
               username as full_name
        FROM users
        WHERE role IN ('lecturer', 'hod')
        ORDER BY username
    ");
    $stmt->execute();
    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
    foreach ($lecturers as $lecturer) {
        echo "<tr>";
        echo "<td>" . $lecturer['id'] . "</td>";
        echo "<td>" . $lecturer['full_name'] . "</td>";
        echo "<td>" . $lecturer['email'] . "</td>";
        echo "<td>" . $lecturer['role'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "❌ Database query failed: " . $e->getMessage() . "<br>";
}

// Test 3: Test lecturers API
echo "<h2>Test 3: Lecturers API</h2>";
$api_url = "api/assign-hod-api.php?action=get_lecturers";
$response = file_get_contents($api_url, false, $context);
if ($response === false) {
    echo "❌ Failed to call lecturers API<br>";
} else {
    $data = json_decode($response, true);
    echo "✅ Lecturers API response received<br>";
    echo "Status: " . $data['status'] . "<br>";
    echo "Message: " . $data['message'] . "<br>";
    echo "Count: " . count($data['data'] ?? []) . "<br>";

    if (isset($data['data']) && is_array($data['data'])) {
        echo "<h3>Lecturers:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
        foreach ($data['data'] as $lecturer) {
            echo "<tr>";
            echo "<td>" . $lecturer['id'] . "</td>";
            echo "<td>" . $lecturer['full_name'] . "</td>";
            echo "<td>" . $lecturer['email'] . "</td>";
            echo "<td>" . $lecturer['role'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// Test 4: Test statistics API
echo "<h2>Test 4: Statistics API</h2>";
$api_url = "api/assign-hod-api.php?action=get_assignment_stats";
$response = file_get_contents($api_url, false, $context);
if ($response === false) {
    echo "❌ Failed to call statistics API<br>";
} else {
    $data = json_decode($response, true);
    echo "✅ Statistics API response received<br>";
    echo "Status: " . $data['status'] . "<br>";
    echo "Message: " . $data['message'] . "<br>";

    if (isset($data['data']) && is_array($data['data'])) {
        echo "<h3>Statistics:</h3>";
        echo "<table border='1'>";
        foreach ($data['data'] as $key => $value) {
            echo "<tr><td><strong>" . $key . "</strong></td><td>" . $value . "</td></tr>";
        }
        echo "</table>";
    }
}

echo "<h2>Test Complete</h2>";
echo "<p><a href='assign-hod.php'>← Back to HOD Assignment Page</a></p>";
?>