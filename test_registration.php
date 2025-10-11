<?php
// Simulate session for testing
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = 'test_token';

require_once "config.php";

echo "<h1>Testing Lecturer Registration</h1>";

// Simulate POST data
$_POST = [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'gender' => 'Male',
    'dob' => '1990-01-01',
    'id_number' => '1234567890123456',
    'email' => 'john.doe' . time() . '@test.com', // Unique email
    'phone' => '1234567890',
    'department_id' => '3', // Assuming department exists
    'education_level' => "Bachelor's",
    'selected_options' => ['1'], // Assuming option exists
    'selected_courses' => [], // No courses for simplicity
    'csrf_token' => 'test_token'
];

echo "<h2>Testing Registration Process</h2>";
echo "<pre>";

try {
    // Include the registration functions
    require_once "admin-register-lecturer.php";

    echo "Starting registration...\n";

    $start_time = microtime(true);
    $result = processLecturerRegistration($_POST);
    $end_time = microtime(true);

    $duration = round(($end_time - $start_time) * 1000, 2);

    echo "Registration completed in {$duration}ms\n";
    echo "Result: " . print_r($result, true) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>