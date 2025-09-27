<?php
/**
 * Test version of attendance reports without authentication
 * For debugging purposes only
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Testing Attendance Reports</h1>";

// Test includes
echo "<h2>Testing Includes</h2>";
echo "<pre>";

echo "Config: ";
try {
    require_once "config.php";
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

echo "Utils: ";
try {
    require_once "attendance_reports_utils.php";
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

echo "Database: ";
try {
    $pdo->query("SELECT 1");
    echo "✓ OK\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

echo "</pre>";

// Test functions
echo "<h2>Testing Functions</h2>";
echo "<pre>";

echo "getLecturerClasses: ";
try {
    // Test with a dummy lecturer ID
    $classes = getLecturerClasses(1);
    echo "✓ OK - Found " . count($classes) . " classes\n";
} catch (Exception $e) {
    echo "✗ FAILED: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "<p><a href='attendance-reports-refactored.php'>Back to main page</a></p>";
?>