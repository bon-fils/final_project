<?php
/**
 * Test the fixed courses API
 */

// Start session to simulate logged-in admin
session_start();

// Simulate admin login
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

echo "<h2>Testing Fixed Courses API</h2>";

// Test different department IDs
$test_departments = [7, 3, 4, 6];

foreach ($test_departments as $dept_id) {
    echo "<h3>Testing Department ID: $dept_id</h3>";
    
    $url = "http://localhost/final_project_1/get-courses.php?department_id=$dept_id";
    echo "<p><strong>URL:</strong> $url</p>";
    
    $response = @file_get_contents($url);
    if ($response !== false) {
        echo "<div style='background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>✅ API Response:</strong><br>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        $json_data = json_decode($response, true);
        if ($json_data && $json_data['success']) {
            echo "<p style='color: green;'>✅ Success! Found " . count($json_data['courses']) . " courses</p>";
            if (!empty($json_data['courses'])) {
                echo "<ul>";
                foreach ($json_data['courses'] as $course) {
                    echo "<li><strong>" . htmlspecialchars($course['course_name']) . "</strong> (" . htmlspecialchars($course['course_code']) . ")</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ No courses found or error: " . ($json_data['message'] ?? 'Unknown error') . "</p>";
        }
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Failed to fetch from API</p>";
    }
    
    echo "<hr>";
}

echo "<br><a href='admin-register-lecturer.php'>← Back to Register Lecturer</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { white-space: pre-wrap; word-wrap: break-word; font-size: 12px; }
</style>
