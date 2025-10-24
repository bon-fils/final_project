<?php
/**
 * Test API endpoints by making actual HTTP requests
 */

echo "<h2>Testing API Endpoints with HTTP Requests</h2>";

// Test departments
$test_departments = [3, 7, 4, 6];

foreach ($test_departments as $dept_id) {
    echo "<h3>Testing Department ID: $dept_id</h3>";
    
    // Test simple API
    $url_simple = "http://localhost/final_project_1/get-options-simple.php?department_id=$dept_id";
    echo "<p><strong>Testing Simple API:</strong> $url_simple</p>";
    
    $response_simple = @file_get_contents($url_simple);
    if ($response_simple !== false) {
        echo "<div style='background: #e8f5e8; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>✅ Simple API Response:</strong><br>";
        echo "<pre>" . htmlspecialchars($response_simple) . "</pre>";
        
        $json_data = json_decode($response_simple, true);
        if ($json_data && $json_data['success']) {
            echo "<p style='color: green;'>✅ Success! Found " . count($json_data['options']) . " options</p>";
            if (!empty($json_data['options'])) {
                echo "<ul>";
                foreach ($json_data['options'] as $option) {
                    echo "<li>" . htmlspecialchars($option['name']) . " (ID: " . $option['id'] . ")</li>";
                }
                echo "</ul>";
            }
        } else {
            echo "<p style='color: orange;'>⚠️ No options found or error: " . ($json_data['message'] ?? 'Unknown error') . "</p>";
        }
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Failed to fetch from simple API</p>";
    }
    
    // Test original API (this will likely fail due to authentication)
    $url_original = "http://localhost/final_project_1/get-options.php?department_id=$dept_id";
    echo "<p><strong>Testing Original API:</strong> $url_original</p>";
    
    $response_original = @file_get_contents($url_original);
    if ($response_original !== false) {
        echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
        echo "<strong>Original API Response:</strong><br>";
        echo "<pre>" . htmlspecialchars($response_original) . "</pre>";
        echo "</div>";
    } else {
        echo "<p style='color: red;'>❌ Failed to fetch from original API (likely authentication issue)</p>";
    }
    
    echo "<hr>";
}

echo "<br><a href='admin-register-lecturer.php'>← Back to Register Lecturer</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { white-space: pre-wrap; word-wrap: break-word; font-size: 12px; }
</style>
