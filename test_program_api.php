<?php
/**
 * Test script for department-option API
 */

require_once 'config.php';

try {
    echo "<h1>Testing Department-Option API</h1>";

    // Simulate being called from registration page
    $_SERVER['HTTP_REFERER'] = 'http://localhost/final_project_1/register-student.php';

    // Test getting options for department ID 1 (Civil Engineering)
    $_POST['action'] = 'get_options';
    $_POST['department_id'] = '1';
    $_POST['csrf_token'] = 'test';

    echo "<h2>Testing get_options for Department ID 1</h2>";
    echo "<pre>";
    // Change to api directory temporarily
    chdir('api');
    include 'department-option-api.php';
    chdir('..');
    echo "</pre>";

    // Test with a different department
    echo "<h2>Testing get_options for Department ID 5 (ICT)</h2>";
    $_POST['department_id'] = '5';
    echo "<pre>";
    chdir('api');
    include 'department-option-api.php';
    chdir('..');
    echo "</pre>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>