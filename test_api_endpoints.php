<?php
/**
 * Test API endpoints via HTTP requests
 * This simulates what the frontend JavaScript does
 */

echo "<h1>API Endpoints Test</h1>";

// Test 1: Departments API
echo "<h2>Test 1: Departments API</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/final_project_1/api/assign-hod-api.php?action=get_departments");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo "❌ Failed to connect to API<br>";
    echo "Error: " . curl_error($ch) . "<br>";
} else {
    echo "✅ API Response received (HTTP " . $http_code . ")<br>";

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Invalid JSON response<br>";
        echo "Raw response: " . htmlspecialchars($response) . "<br>";
    } else {
        echo "Status: " . $data['status'] . "<br>";
        echo "Message: " . $data['message'] . "<br>";
        echo "Count: " . count($data['data'] ?? []) . "<br>";

        if (isset($data['data']) && is_array($data['data'])) {
            echo "<h3>Departments:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Name</th><th>HOD ID</th><th>HOD Name</th></tr>";
            foreach ($data['data'] as $dept) {
                echo "<tr>";
                echo "<td>" . $dept['id'] . "</td>";
                echo "<td>" . $dept['name'] . "</td>";
                echo "<td>" . ($dept['hod_id'] ?? 'NULL') . "</td>";
                echo "<td>" . ($dept['hod_name'] ?? 'NULL') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
}

curl_close($ch);

// Test 2: Lecturers API
echo "<h2>Test 2: Lecturers API</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/final_project_1/api/assign-hod-api.php?action=get_lecturers");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo "❌ Failed to connect to lecturers API<br>";
    echo "Error: " . curl_error($ch) . "<br>";
} else {
    echo "✅ Lecturers API Response received (HTTP " . $http_code . ")<br>";

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Invalid JSON response<br>";
        echo "Raw response: " . htmlspecialchars($response) . "<br>";
    } else {
        echo "Status: " . $data['status'] . "<br>";
        echo "Message: " . $data['message'] . "<br>";
        echo "Count: " . count($data['data'] ?? []) . "<br>";

        if (isset($data['data']) && is_array($data['data'])) {
            echo "<h3>Lecturers:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th></tr>";
            foreach ($data['data'] as $lecturer) {
                echo "<tr>";
                echo "<td>" . $lecturer['id'] . "</td>";
                echo "<td>" . $lecturer['full_name'] . "</td>";
                echo "<td>" . $lecturer['email'] . "</td>";
                echo "<td>" . $lecturer['role'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
}

curl_close($ch);

// Test 3: Statistics API
echo "<h2>Test 3: Statistics API</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost/final_project_1/api/assign-hod-api.php?action=get_assignment_stats");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Requested-With: XMLHttpRequest'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo "❌ Failed to connect to statistics API<br>";
    echo "Error: " . curl_error($ch) . "<br>";
} else {
    echo "✅ Statistics API Response received (HTTP " . $http_code . ")<br>";

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ Invalid JSON response<br>";
        echo "Raw response: " . htmlspecialchars($response) . "<br>";
    } else {
        echo "Status: " . $data['status'] . "<br>";
        echo "Message: " . $data['message'] . "<br>";

        if (isset($data['data']) && is_array($data['data'])) {
            echo "<h3>Statistics:</h3>";
            echo "<table border='1'>";
            foreach ($data['data'] as $key => $value) {
                echo "<tr><td><strong>" . $key . "</strong></td><td>" . $value . "</td></tr>";
            }
            echo "</table>";
        }
    }
}

curl_close($ch);

echo "<h2>Test Complete</h2>";
echo "<p><a href='assign-hod.php'>← Back to HOD Assignment Page</a></p>";
echo "<p><a href='test_hod_api.php'>→ Database Test Page</a></p>";
?>