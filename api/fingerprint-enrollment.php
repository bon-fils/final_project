<?php
/**
 * Fingerprint Enrollment API
 * Handles fingerprint enrollment for student registration
 * Integrates with ESP32 fingerprint system
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once "config.php";
require_once "security_utils.php";

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $requiredFields = ['student_id', 'action'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    $studentId = (int)$input['student_id'];
    $action = $input['action'];
    
    // Validate student exists
    $stmt = $pdo->prepare("SELECT id, reg_no, first_name, last_name FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student not found');
    }
    
    switch ($action) {
        case 'start_enrollment':
            $result = startFingerprintEnrollment($studentId, $student);
            break;
            
        case 'check_status':
            $result = checkEnrollmentStatus($studentId);
            break;
            
        case 'complete_enrollment':
            $result = completeFingerprintEnrollment($studentId, $student);
            break;
            
        case 'test_fingerprint':
            $result = testFingerprintScan();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    error_log("Fingerprint enrollment error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Start fingerprint enrollment process
 */
function startFingerprintEnrollment($studentId, $student) {
    global $pdo;
    
    // Check if student already has fingerprint enrolled
    $stmt = $pdo->prepare("SELECT fingerprint_id FROM students WHERE id = ? AND fingerprint_id IS NOT NULL");
    $stmt->execute([$studentId]);
    if ($stmt->fetch()) {
        throw new Exception('Student already has fingerprint enrolled');
    }
    
    // Generate unique fingerprint ID
    $fingerprintId = generateFingerprintId();
    
    // Update student record with fingerprint ID
    $stmt = $pdo->prepare("UPDATE students SET fingerprint_id = ?, fingerprint_status = 'enrolling' WHERE id = ?");
    $stmt->execute([$fingerprintId, $studentId]);
    
    // Send enrollment command to ESP32
    $esp32Response = sendToESP32('/enroll', [
        'id' => $fingerprintId,
        'student_name' => $student['first_name'] . ' ' . $student['last_name'],
        'reg_no' => $student['reg_no']
    ]);
    
    if (!$esp32Response['success']) {
        // Rollback database change
        $stmt = $pdo->prepare("UPDATE students SET fingerprint_id = NULL, fingerprint_status = NULL WHERE id = ?");
        $stmt->execute([$studentId]);
        throw new Exception('Failed to start ESP32 enrollment: ' . $esp32Response['error']);
    }
    
    // Log enrollment start
    log_message('info', 'Fingerprint enrollment started', [
        'student_id' => $studentId,
        'fingerprint_id' => $fingerprintId,
        'reg_no' => $student['reg_no']
    ]);
    
    return [
        'fingerprint_id' => $fingerprintId,
        'status' => 'enrolling',
        'message' => 'Place finger on sensor for enrollment',
        'esp32_status' => $esp32Response['data'] ?? null
    ];
}

/**
 * Check enrollment status
 */
function checkEnrollmentStatus($studentId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT fingerprint_id, fingerprint_status FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student not found');
    }
    
    $status = $student['fingerprint_status'] ?? 'not_enrolled';
    $fingerprintId = $student['fingerprint_id'];
    
    // Check ESP32 status
    $esp32Status = checkESP32Status();
    
    return [
        'fingerprint_id' => $fingerprintId,
        'status' => $status,
        'esp32_status' => $esp32Status,
        'message' => getStatusMessage($status)
    ];
}

/**
 * Complete fingerprint enrollment
 */
function completeFingerprintEnrollment($studentId, $student) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT fingerprint_id FROM students WHERE id = ?");
    $stmt->execute([$studentId]);
    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$studentData || !$studentData['fingerprint_id']) {
        throw new Exception('No active enrollment found');
    }
    
    // Test the enrolled fingerprint
    $testResult = testFingerprintScan();
    
    if (!$testResult['success']) {
        throw new Exception('Fingerprint test failed: ' . $testResult['error']);
    }
    
    // Update student record
    $stmt = $pdo->prepare("UPDATE students SET fingerprint_status = 'enrolled', fingerprint_enrolled_at = NOW() WHERE id = ?");
    $stmt->execute([$studentId]);
    
    // Log successful enrollment
    log_message('info', 'Fingerprint enrollment completed', [
        'student_id' => $studentId,
        'fingerprint_id' => $studentData['fingerprint_id'],
        'reg_no' => $student['reg_no']
    ]);
    
    return [
        'fingerprint_id' => $studentData['fingerprint_id'],
        'status' => 'enrolled',
        'message' => 'Fingerprint enrollment completed successfully',
        'test_result' => $testResult
    ];
}

/**
 * Test fingerprint scan
 */
function testFingerprintScan() {
    $esp32Response = sendToESP32('/identify', []);
    
    if (!$esp32Response['success']) {
        return [
            'success' => false,
            'error' => $esp32Response['error']
        ];
    }
    
    return [
        'success' => true,
        'fingerprint_id' => $esp32Response['data']['fingerprint_id'] ?? null,
        'message' => 'Fingerprint scan successful'
    ];
}

/**
 * Check ESP32 status
 */
function checkESP32Status() {
    $esp32Response = sendToESP32('/status', []);
    
    if (!$esp32Response['success']) {
        return [
            'connected' => false,
            'error' => $esp32Response['error']
        ];
    }
    
    return [
        'connected' => true,
        'data' => $esp32Response['data']
    ];
}

/**
 * Send request to ESP32
 */
function sendToESP32($endpoint, $data = []) {
    $esp32Ip = $_ENV['ESP32_IP'] ?? '192.168.137.129';
    $esp32Port = $_ENV['ESP32_PORT'] ?? '80';
    $timeout = 10; // seconds
    
    $url = "http://{$esp32Ip}:{$esp32Port}{$endpoint}";
    
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
    
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => "ESP32 communication error: $error"
        ];
    }
    
    if ($httpCode !== 200) {
        return [
            'success' => false,
            'error' => "ESP32 returned HTTP $httpCode"
        ];
    }
    
    $decodedResponse = json_decode($response, true);
    
    return [
        'success' => true,
        'data' => $decodedResponse
    ];
}

/**
 * Generate unique fingerprint ID
 */
function generateFingerprintId() {
    global $pdo;
    
    do {
        $id = rand(1, 1000); // ESP32 typically supports 1-1000 fingerprints
        
        $stmt = $pdo->prepare("SELECT id FROM students WHERE fingerprint_id = ?");
        $stmt->execute([$id]);
        
        if (!$stmt->fetch()) {
            return $id;
        }
    } while (true);
}

/**
 * Get status message
 */
function getStatusMessage($status) {
    $messages = [
        'not_enrolled' => 'Fingerprint not enrolled',
        'enrolling' => 'Fingerprint enrollment in progress',
        'enrolled' => 'Fingerprint enrolled successfully',
        'failed' => 'Fingerprint enrollment failed'
    ];
    
    return $messages[$status] ?? 'Unknown status';
}
?>
