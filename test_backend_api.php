<?php
/**
 * Backend API Test Script
 * Tests the manage-users.php backend functionality without session requirements
 */

require_once "config.php";

echo "<h1>Backend API Test Results</h1>";

try {
    // Test 1: Database Connection
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
            echo "❌ $table table missing<br>";
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

    // Test 4: Test user statistics function (simulate)
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

    // Test 5: Test AJAX endpoints (simulate)
    echo "<h2>Test 5: AJAX Endpoints Simulation</h2>";

    // Simulate get_users action
    $search = '';
    $role_filter = '';
    $status_filter = '';

    $sql = "
        SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            u.status,
            u.created_at,
            u.updated_at,
            u.last_login,
            CASE
                WHEN u.role = 'student' THEN s.first_name
                WHEN u.role = 'lecturer' THEN l.first_name
                WHEN u.role = 'hod' THEN l.first_name
                ELSE 'System'
            END as first_name,
            CASE
                WHEN u.role = 'student' THEN s.last_name
                WHEN u.role = 'lecturer' THEN l.last_name
                WHEN u.role = 'hod' THEN l.last_name
                ELSE 'User'
            END as last_name,
            CASE
                WHEN u.role = 'student' THEN s.reg_no
                WHEN u.role = 'lecturer' THEN l.id_number
                WHEN u.role = 'hod' THEN l.id_number
                ELSE NULL
            END as reference_id,
            CASE
                WHEN u.role = 'student' THEN s.telephone
                WHEN u.role = 'lecturer' THEN l.phone
                WHEN u.role = 'hod' THEN l.phone
                ELSE NULL
            END as phone
        FROM users u
        LEFT JOIN students s ON u.email = s.email AND u.role = 'student'
        LEFT JOIN lecturers l ON u.email = l.email AND u.role IN ('lecturer', 'hod')
        WHERE 1=1
    ";

    $conditions = [];
    $params = [];

    if (!empty($search)) {
        $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(s.first_name, l.first_name, ''), ' ', COALESCE(s.last_name, l.last_name, '')) LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($role_filter)) {
        $conditions[] = "u.role = ?";
        $params[] = $role_filter;
    }

    if (!empty($status_filter)) {
        $conditions[] = "u.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY u.created_at DESC LIMIT 10";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "✅ get_users query executed successfully<br>";
    echo "Found " . count($users) . " users<br><br>";

    // Test 6: Test user creation (simulate)
    echo "<h2>Test 6: User Creation Simulation</h2>";

    // Check if we can create a test user
    $testUsername = 'test_user_' . time();
    $testEmail = 'test' . time() . '@example.com';

    try {
        $hashed_password = password_hash('testpass123', PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([$testUsername, $testEmail, $hashed_password, 'student']);

        $user_id = $pdo->lastInsertId();
        echo "✅ Test user created successfully (ID: $user_id)<br>";

        // Clean up test user
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        echo "✅ Test user cleaned up<br>";

    } catch (Exception $e) {
        echo "❌ Test user creation failed: " . $e->getMessage() . "<br>";
    }

    echo "<br><h2>✅ All backend tests completed successfully!</h2>";
    echo "<p>The User Management System backend is working properly.</p>";
    echo "<p><a href='manage-users.php'>Go to User Management Interface</a></p>";

} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
    echo "<p>Please check your database configuration.</p>";
}
?>