<?php
// Debug version to identify the white page issue
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug: Attendance Reports</h1>";
echo "<pre>";

// Test basic includes
echo "Testing config.php... ";
try {
    require_once "config.php";
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

echo "Testing session_check.php... ";
try {
    require_once "session_check.php";
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

echo "Testing attendance_reports_utils.php... ";
try {
    require_once "attendance_reports_utils.php";
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

// Test session
echo "Testing session... ";
session_start();
$user_id = $_SESSION['user_id'] ?? null;
echo "User ID: " . ($user_id ?: 'NOT SET') . "\n";

// Test database connection
echo "Testing database connection... ";
try {
    $pdo->query("SELECT 1");
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

// Test functions
echo "Testing getLecturerDepartment function... ";
if (function_exists('getLecturerDepartment')) {
    try {
        if ($user_id) {
            $result = getLecturerDepartment($user_id);
            echo "✓ OK - Result: " . json_encode($result) . "\n";
        } else {
            echo "⚠ SKIPPED - No user ID\n";
        }
    } catch (Exception $e) {
        echo "✗ FAILED: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ FUNCTION NOT FOUND\n";
}

echo "</pre>";
echo "<p><a href='attendance-reports-refactored.php'>Back to main page</a></p>";
?>