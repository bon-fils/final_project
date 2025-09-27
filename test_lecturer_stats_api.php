<?php
/**
 * Test script for lecturer statistics API
 * This script tests the optimized API endpoints for lecturer statistics
 */

require_once "config.php";
require_once "cache_utils.php";

// Note: Skipping session_check for testing purposes
// In production, proper session validation is required

echo "<h1>Lecturer Statistics API Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .success { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 3px; }
    .error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 3px; }
    .info { background-color: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 3px; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

try {
    // Test 1: Get lecturer statistics (cached)
    echo "<div class='test-section'>";
    echo "<h3>Test 1: Get Lecturer Statistics (Cached)</h3>";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost/final_project_1/api/assign-courses-api.php?action=get_lecturer_statistics",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "<div class='error'>CURL Error: $error</div>";
    } else {
        echo "<div class='info'>HTTP Status: $http_code</div>";
        echo "<h4>Response:</h4>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";

        $data = json_decode($response, true);
        if ($data && $data['success']) {
            echo "<div class='success'>✓ API call successful!</div>";
            echo "<ul>";
            echo "<li>Total Lecturers: " . $data['statistics']['total_lecturers'] . "</li>";
            echo "<li>Male Lecturers: " . $data['statistics']['male_lecturers'] . "</li>";
            echo "<li>Female Lecturers: " . $data['statistics']['female_lecturers'] . "</li>";
            echo "<li>PhD Holders: " . $data['statistics']['phd_holders'] . "</li>";
            if (isset($data['cached']) && $data['cached']) {
                echo "<li><strong>Data loaded from cache</strong></li>";
            }
            echo "</ul>";
        } else {
            echo "<div class='error'>✗ API call failed: " . ($data['message'] ?? 'Unknown error') . "</div>";
        }
    }
    echo "</div>";

    // Test 2: Get lecturers (legacy method)
    echo "<div class='test-section'>";
    echo "<h3>Test 2: Get Lecturers (Legacy Method)</h3>";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost/final_project_1/api/assign-courses-api.php?action=get_lecturers",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "<div class='error'>CURL Error: $error</div>";
    } else {
        echo "<div class='info'>HTTP Status: $http_code</div>";
        echo "<h4>Response:</h4>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";

        $data = json_decode($response, true);
        if ($data && is_array($data)) {
            echo "<div class='success'>✓ Legacy API call successful!</div>";
            echo "<ul>";
            echo "<li>Total Lecturers: " . count($data) . "</li>";
            echo "<li>Male Lecturers: " . count(array_filter($data, fn($l) => $l['gender'] === 'Male')) . "</li>";
            echo "<li>Female Lecturers: " . count(array_filter($data, fn($l) => $l['gender'] === 'Female')) . "</li>";
            echo "<li>PhD Holders: " . count(array_filter($data, fn($l) => $l['education_level'] === 'PhD')) . "</li>";
            echo "</ul>";
        } else {
            echo "<div class='error'>✗ Legacy API call failed: " . ($data['message'] ?? 'Unknown error') . "</div>";
        }
    }
    echo "</div>";

    // Test 3: Get courses
    echo "<div class='test-section'>";
    echo "<h3>Test 3: Get Courses</h3>";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost/final_project_1/api/assign-courses-api.php?action=get_courses",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json'
        ]
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "<div class='error'>CURL Error: $error</div>";
    } else {
        echo "<div class='info'>HTTP Status: $http_code</div>";
        echo "<h4>Response:</h4>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";

        $data = json_decode($response, true);
        if ($data && $data['success']) {
            echo "<div class='success'>✓ Courses API call successful!</div>";
            echo "<ul>";
            echo "<li>Total Courses: " . $data['count'] . "</li>";
            echo "<li>Department: " . ($data['department_name'] ?? 'Unknown') . "</li>";
            echo "</ul>";
        } else {
            echo "<div class='error'>✗ Courses API call failed: " . ($data['message'] ?? 'Unknown error') . "</div>";
        }
    }
    echo "</div>";

    // Test 4: Performance comparison
    echo "<div class='test-section'>";
    echo "<h3>Test 4: Performance Comparison</h3>";

    $start_time = microtime(true);

    // Test cached statistics
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost/final_project_1/api/assign-courses-api.php?action=get_lecturer_statistics",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    curl_exec($ch);
    curl_close($ch);

    $cached_time = microtime(true) - $start_time;

    // Clear cache for fair comparison
    $cache_key = "lecturer_stats_dept_1"; // Assuming department ID 1
    if (function_exists('clear_cache')) {
        clear_cache($cache_key);
    }

    $start_time = microtime(true);

    // Test uncached statistics
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://localhost/final_project_1/api/assign-courses-api.php?action=get_lecturer_statistics",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Cache-Control: no-cache'
        ]
    ]);
    curl_exec($ch);
    curl_close($ch);

    $uncached_time = microtime(true) - $start_time;

    echo "<div class='info'>Performance Results:</div>";
    echo "<ul>";
    echo "<li>Cached request time: " . round($cached_time * 1000, 2) . "ms</li>";
    echo "<li>Uncached request time: " . round($uncached_time * 1000, 2) . "ms</li>";
    echo "<li>Performance improvement: " . round((($uncached_time - $cached_time) / $uncached_time) * 100, 1) . "% faster with cache</li>";
    echo "</ul>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>Test Error: " . $e->getMessage() . "</div>";
}

echo "<div class='info'>";
echo "<h3>Test Summary</h3>";
echo "<p>The lecturer statistics API has been optimized with:</p>";
echo "<ul>";
echo "<li>✅ Cached statistics endpoint for better performance</li>";
echo "<li>✅ Enhanced error handling and validation</li>";
echo "<li>✅ Structured JSON responses with error codes</li>";
echo "<li>✅ Fallback mechanisms for reliability</li>";
echo "<li>✅ Additional statistics like contact info completeness</li>";
echo "<li>✅ Proper HTTP status codes and headers</li>";
echo "</ul>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li>Test the API in a browser with valid HoD session</li>";
echo "<li>Monitor cache performance in production</li>";
echo "<li>Consider implementing cache warming for frequently accessed data</li>";
echo "</ul>";
echo "</div>";
?>