<?php
/**
 * Test HOD Attendance Overview
 */

echo "<h2>Testing HOD Attendance Overview</h2>";

// Test if the file can be parsed without errors
echo "<h3>1. Testing PHP Syntax</h3>";
$output = shell_exec('php -l "d:\xampp\htdocs\final_project_1\hod-attendance-overview.php" 2>&1');
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// Test if helper file exists and can be included
echo "<h3>2. Testing Helper File</h3>";
try {
    if (file_exists('includes/hod_auth_helper.php')) {
        echo "<p style='color: green;'>✅ Helper file exists</p>";
        require_once 'includes/hod_auth_helper.php';
        echo "<p style='color: green;'>✅ Helper file loaded successfully</p>";
        
        if (function_exists('verifyHODAccess')) {
            echo "<p style='color: green;'>✅ verifyHODAccess function is available</p>";
        } else {
            echo "<p style='color: red;'>❌ verifyHODAccess function not found</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Helper file not found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading helper: " . $e->getMessage() . "</p>";
}

// Test if main file can be accessed
echo "<h3>3. Testing Main File Access</h3>";
echo "<p><a href='hod-attendance-overview.php' target='_blank'>Click here to test the HOD Attendance Overview page</a></p>";
echo "<p><em>Note: You need to be logged in as an HOD user to access this page properly.</em></p>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>
