<?php
/**
 * Test Course Assignment API
 */

require_once "config.php";
session_start();

// Simulate HoD login
$_SESSION['user_id'] = 4; // The HoD user ID we found
$_SESSION['role'] = 'hod';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

echo "<h1>Course Assignment API Test</h1>";

try {
    echo "<h2>Testing get_courses API:</h2>";
    $url = "http://localhost/final_project_1/api/assign-courses-api.php?action=get_courses";
    $response = file_get_contents($url);

    if ($response === false) {
        echo "<p style='color: red;'>❌ Failed to connect to API</p>";
    } else {
        $data = json_decode($response, true);
        echo "<p>Response: " . ($data['success'] ? '✅ Success' : '❌ Failed') . "</p>";
        echo "<p>Message: " . $data['message'] . "</p>";

        if ($data['success']) {
            echo "<p>Courses found: " . count($data['data']) . "</p>";
            echo "<ul>";
            foreach ($data['data'] as $course) {
                echo "<li>{$course['course_code']}: {$course['course_name']} (Dept: {$course['department_id']})</li>";
            }
            echo "</ul>";
        }
    }

    echo "<h2>Testing get_lecturers API:</h2>";
    $url = "http://localhost/final_project_1/api/assign-courses-api.php?action=get_lecturers";
    $response = file_get_contents($url);

    if ($response === false) {
        echo "<p style='color: red;'>❌ Failed to connect to API</p>";
    } else {
        $data = json_decode($response, true);
        echo "<p>Response: " . ($data['success'] ? '✅ Success' : '❌ Failed') . "</p>";
        echo "<p>Message: " . $data['message'] . "</p>";

        if ($data['success']) {
            echo "<p>Lecturers found: " . count($data['data']) . "</p>";
            echo "<ul>";
            foreach ($data['data'] as $lecturer) {
                echo "<li>{$lecturer['first_name']} {$lecturer['last_name']} ({$lecturer['email']})</li>";
            }
            echo "</ul>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>