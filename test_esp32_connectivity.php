<?php
/**
 * ESP32 Connectivity Test Script
 * Tests connection to ESP32 fingerprint system
 */

require_once "config.php";

echo "ESP32 Fingerprint System - Connectivity Test\n";
echo "============================================\n\n";

// Test configuration
$esp32Ip = $_ENV['ESP32_IP'] ?? '192.168.1.100';
$esp32Port = $_ENV['ESP32_PORT'] ?? '80';
$timeout = $_ENV['ESP32_TIMEOUT'] ?? 10;

echo "Testing ESP32 at: {$esp32Ip}:{$esp32Port}\n";
echo "Timeout: {$timeout} seconds\n\n";

// Test 1: Basic connectivity
echo "1. Testing basic connectivity...\n";
$url = "http://{$esp32Ip}:{$esp32Port}/test";
$result = testESP32Endpoint($url, $timeout);

if ($result['success']) {
    echo "   ✅ ESP32 is responding\n";
    echo "   Response: " . $result['data']['message'] . "\n";
} else {
    echo "   ❌ ESP32 is not responding\n";
    echo "   Error: " . $result['error'] . "\n";
    exit(1);
}

echo "\n";

// Test 2: Status endpoint
echo "2. Testing status endpoint...\n";
$url = "http://{$esp32Ip}:{$esp32Port}/status";
$result = testESP32Endpoint($url, $timeout);

if ($result['success']) {
    echo "   ✅ Status endpoint working\n";
    $status = $result['data'];
    echo "   WiFi: " . $status['wifi'] . "\n";
    echo "   Fingerprint Sensor: " . $status['fingerprint_sensor'] . "\n";
    echo "   IP Address: " . $status['ip'] . "\n";
    echo "   Enrollment Active: " . $status['enrollment_active'] . "\n";
} else {
    echo "   ❌ Status endpoint failed\n";
    echo "   Error: " . $result['error'] . "\n";
}

echo "\n";

// Test 3: Display endpoint
echo "3. Testing display endpoint...\n";
$url = "http://{$esp32Ip}:{$esp32Port}/display?message=Test%20Message";
$result = testESP32Endpoint($url, $timeout);

if ($result['success']) {
    echo "   ✅ Display endpoint working\n";
    echo "   Message sent to OLED display\n";
} else {
    echo "   ❌ Display endpoint failed\n";
    echo "   Error: " . $result['error'] . "\n";
}

echo "\n";

// Test 4: Fingerprint identification (if sensor is ready)
echo "4. Testing fingerprint identification...\n";
$url = "http://{$esp32Ip}:{$esp32Port}/identify";
$result = testESP32Endpoint($url, $timeout);

if ($result['success']) {
    echo "   ✅ Fingerprint identification working\n";
    if (isset($result['data']['fingerprint_id'])) {
        echo "   Fingerprint ID: " . $result['data']['fingerprint_id'] . "\n";
        echo "   Confidence: " . $result['data']['confidence'] . "\n";
    }
} else {
    echo "   ⚠️ Fingerprint identification test failed (this is normal if no finger is placed)\n";
    echo "   Error: " . $result['error'] . "\n";
}

echo "\n";

// Test 5: Enrollment endpoint
echo "5. Testing enrollment endpoint...\n";
$url = "http://{$esp32Ip}:{$esp32Port}/enroll";
$data = [
    'id' => 999,
    'student_name' => 'Test Student',
    'reg_no' => 'TEST001'
];

$result = testESP32Endpoint($url, $timeout, $data);

if ($result['success']) {
    echo "   ✅ Enrollment endpoint working\n";
    echo "   Test enrollment started (ID: 999)\n";
} else {
    echo "   ❌ Enrollment endpoint failed\n";
    echo "   Error: " . $result['error'] . "\n";
}

echo "\n";

// Summary
echo "Test Summary\n";
echo "============\n";
echo "ESP32 IP: {$esp32Ip}\n";
echo "ESP32 Port: {$esp32Port}\n";
echo "Connection Status: " . ($result['success'] ? "✅ Connected" : "❌ Failed") . "\n";

if ($result['success']) {
    echo "\n🎉 ESP32 Fingerprint System is ready for use!\n";
    echo "You can now test fingerprint enrollment in the student registration form.\n";
} else {
    echo "\n❌ ESP32 Fingerprint System is not ready.\n";
    echo "Please check the setup guide and troubleshoot the issues.\n";
}

/**
 * Test ESP32 endpoint
 */
function testESP32Endpoint($url, $timeout, $postData = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($postData) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => "CURL Error: $error"
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "HTTP $httpCode"
        ];
    }
    
    $decodedResponse = json_decode($response, true);
    
    return [
        'success' => true,
        'data' => $decodedResponse
    ];
}
?>
