<?php
require_once "config.php";
require_once "session_check.php";

// Simulate HoD session
$_SESSION['user_id'] = 4; // HoD ID from department 3
$_SESSION['role'] = 'hod';

try {
    echo "Testing API with HoD session:\n\n";

    // Test the API call directly
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/final_project_1/api/department-option-api.php?action=get_options");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id());
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Requested-With: XMLHttpRequest'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP Status Code: $http_code\n";
    echo "Response:\n$response\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>