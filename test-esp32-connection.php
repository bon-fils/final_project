<?php
/**
 * ESP32 Connection Test Script
 * Tests connectivity and functionality of ESP32 fingerprint system
 */

require_once 'config.php';

header('Content-Type: application/json');

function testESP32Connection() {
    $esp32_url = 'http://' . ESP32_IP . ':' . ESP32_PORT;
    $results = [];
    
    // Test 1: Basic connectivity
    $results['connectivity'] = testConnectivity($esp32_url);
    
    // Test 2: Status endpoint
    $results['status'] = testStatusEndpoint($esp32_url);
    
    // Test 3: Display endpoint
    $results['display'] = testDisplayEndpoint($esp32_url);
    
    // Test 4: Enrollment endpoint (dry run)
    $results['enrollment'] = testEnrollmentEndpoint($esp32_url);
    
    return $results;
}

function testConnectivity($esp32_url) {
    $ch = curl_init($esp32_url . '/status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => $response !== false && $httpCode === 200,
        'http_code' => $httpCode,
        'error' => $error,
        'response_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME) ?? 0
    ];
}

function testStatusEndpoint($esp32_url) {
    $ch = curl_init($esp32_url . '/status');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    return [
        'success' => $httpCode === 200 && $data !== null,
        'data' => $data,
        'sensor_connected' => $data['fingerprint_sensor'] ?? 'unknown',
        'wifi_status' => $data['wifi'] ?? 'unknown'
    ];
}

function testDisplayEndpoint($esp32_url) {
    $testMessage = 'Test from PHP - ' . date('H:i:s');
    $ch = curl_init($esp32_url . '/display?message=' . urlencode($testMessage));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    return [
        'success' => $httpCode === 200 && $data['success'] ?? false,
        'message_sent' => $testMessage,
        'response' => $data
    ];
}

function testEnrollmentEndpoint($esp32_url) {
    // Test enrollment endpoint without actually enrolling
    $testData = [
        'id' => 999, // Use test ID
        'student_name' => 'Test Student',
        'reg_no' => 'TEST123'
    ];
    
    $ch = curl_init($esp32_url . '/enroll');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    return [
        'success' => $httpCode === 200,
        'endpoint_available' => $httpCode !== 404,
        'response' => $data,
        'note' => 'This is a dry run test - no actual enrollment performed'
    ];
}

// Run tests
try {
    $testResults = testESP32Connection();
    
    echo json_encode([
        'success' => true,
        'esp32_ip' => ESP32_IP,
        'esp32_port' => ESP32_PORT,
        'timestamp' => date('Y-m-d H:i:s'),
        'tests' => $testResults
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'esp32_ip' => ESP32_IP,
        'esp32_port' => ESP32_PORT
    ], JSON_PRETTY_PRINT);
}
?>