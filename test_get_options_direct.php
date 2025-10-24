<?php
/**
 * Test get-options.php directly
 */

// Start session to simulate logged-in admin
session_start();

// Simulate admin login (you might need to adjust these values)
$_SESSION['user_id'] = 1;  // Adjust this to a valid admin user ID
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

echo "<h2>Testing get-options.php API Directly</h2>";

// Test different department IDs
$test_departments = [3, 4, 6, 7, 5, 8, 9, 10];

foreach ($test_departments as $dept_id) {
    echo "<h3>Testing Department ID: $dept_id</h3>";
    
    // Simulate the API call
    $_GET['department_id'] = $dept_id;
    
    echo "<p><strong>URL:</strong> get-options.php?department_id=$dept_id</p>";
    
    try {
        // Capture the output
        ob_start();
        include 'get-options.php';
        $output = ob_get_clean();
        
        echo "<p><strong>Response:</strong></p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
        echo htmlspecialchars($output);
        echo "</pre>";
        
        // Try to decode JSON
        $json_data = json_decode($output, true);
        if ($json_data) {
            echo "<p><strong>Parsed JSON:</strong></p>";
            echo "<ul>";
            echo "<li>Success: " . ($json_data['success'] ? 'true' : 'false') . "</li>";
            echo "<li>Message: " . htmlspecialchars($json_data['message'] ?? 'N/A') . "</li>";
            echo "<li>Options Count: " . count($json_data['options'] ?? []) . "</li>";
            if (!empty($json_data['options'])) {
                echo "<li>Options: ";
                foreach ($json_data['options'] as $option) {
                    echo htmlspecialchars($option['name']) . " (ID: " . $option['id'] . "), ";
                }
                echo "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red;'>❌ Invalid JSON response</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
}

echo "<br><a href='admin-register-lecturer.php'>← Back to Register Lecturer</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { white-space: pre-wrap; word-wrap: break-word; }
</style>
