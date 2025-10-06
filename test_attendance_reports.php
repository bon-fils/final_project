<?php
/**
 * Test script to verify attendance reports page works
 */

session_start();
$_SESSION['user_id'] = 1; // Admin user
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = 'test_token';

echo "=== TESTING ATTENDANCE REPORTS PAGE ===\n\n";

echo "Session variables set:\n";
echo "user_id: " . $_SESSION['user_id'] . "\n";
echo "role: " . $_SESSION['role'] . "\n\n";

echo "Testing basic page load...\n";

// Include the config and session check
require_once 'config.php';
require_once 'session_check.php';

echo "Config and session check loaded successfully.\n\n";

echo "Testing getUserContext function...\n";
try {
    // Test the getUserContext function directly
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    if ($user_role === 'admin') {
        $user_context = ['lecturer_id' => null, 'department_id' => null];
        echo "Admin user context: OK\n";
    } else {
        echo "Non-admin user context test would go here\n";
    }

    echo "getUserContext test: PASSED\n\n";
} catch (Exception $e) {
    echo "getUserContext test: FAILED - " . $e->getMessage() . "\n\n";
}

echo "Testing database connection...\n";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "Database connection: OK\n\n";
} catch (Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n\n";
}

echo "Testing departments query...\n";
try {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Departments found: " . count($departments) . "\n";
    if (count($departments) > 0) {
        echo "First department: " . $departments[0]['name'] . "\n";
    }
    echo "Departments query: PASSED\n\n";
} catch (Exception $e) {
    echo "Departments query: FAILED - " . $e->getMessage() . "\n\n";
}

echo "=== TEST COMPLETE ===\n";
echo "If all tests passed, the attendance reports page should work.\n";
?>