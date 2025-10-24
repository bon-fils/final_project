<?php
/**
 * Fingerprint Status API
 * Handles status updates from ESP32 fingerprint server
 * Provides real-time enrollment progress tracking
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once "../config.php";

// Log fingerprint operations
function logFingerprintOperation($operation, $data = []) {
    $logFile = '../logs/fingerprint_operations.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'operation' => $operation,
        'data' => $data,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    file_put_contents($logFile, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Handle status updates from ESP32
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new Exception('Invalid JSON data');
        }
        
        $status = $data['status'] ?? '';
        $statusData = $data['data'] ?? '';
        $timestamp = $data['timestamp'] ?? time();
        
        // Log the status update
        logFingerprintOperation('status_update', [
            'status' => $status,
            'data' => $statusData,
            'esp32_timestamp' => $timestamp
        ]);
        
        // Store status in database for real-time tracking
        $stmt = $pdo->prepare("
            INSERT INTO fingerprint_status_log (status, data, esp32_timestamp, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        try {
            $stmt->execute([$status, $statusData, $timestamp]);
        } catch (PDOException $e) {
            // Create table if it doesn't exist
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $createTable = "
                CREATE TABLE fingerprint_status_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    status VARCHAR(100) NOT NULL,
                    data TEXT,
                    esp32_timestamp BIGINT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ";
                $pdo->exec($createTable);
                
                // Retry the insert
                $stmt->execute([$status, $statusData, $timestamp]);
            } else {
                throw $e;
            }
        }
        
        // Handle specific status types
        switch ($status) {
            case 'enrollment_started':
                $enrollmentData = json_decode($statusData, true);
                if ($enrollmentData) {
                    // Update session storage for real-time UI updates
                    $_SESSION['fingerprint_enrollment'] = [
                        'active' => true,
                        'id' => $enrollmentData['id'] ?? null,
                        'student_name' => $enrollmentData['student_name'] ?? '',
                        'started_at' => time(),
                        'step' => 'first_scan'
                    ];
                }
                break;
                
            case 'first_scan_complete':
                if (isset($_SESSION['fingerprint_enrollment'])) {
                    $_SESSION['fingerprint_enrollment']['step'] = 'remove_finger';
                }
                break;
                
            case 'ready_second_scan':
                if (isset($_SESSION['fingerprint_enrollment'])) {
                    $_SESSION['fingerprint_enrollment']['step'] = 'second_scan';
                }
                break;
                
            case 'enrollment_complete':
                $enrollmentData = json_decode($statusData, true);
                if ($enrollmentData && isset($_SESSION['fingerprint_enrollment'])) {
                    $_SESSION['fingerprint_enrollment']['step'] = 'complete';
                    $_SESSION['fingerprint_enrollment']['completed_at'] = time();
                    $_SESSION['fingerprint_enrollment']['fingerprint_id'] = $enrollmentData['id'] ?? null;
                }
                break;
                
            case 'enrollment_cancelled':
            case 'timeout':
            case 'storage_error':
            case 'model_error':
                // Clear enrollment session on failure
                unset($_SESSION['fingerprint_enrollment']);
                break;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully',
            'status' => $status
        ]);
        
    } elseif ($method === 'GET') {
        // Get current enrollment status
        $action = $_GET['action'] ?? 'status';
        
        switch ($action) {
            case 'status':
                $enrollmentStatus = $_SESSION['fingerprint_enrollment'] ?? null;
                
                echo json_encode([
                    'success' => true,
                    'enrollment' => $enrollmentStatus,
                    'esp32_connected' => checkESP32Connection()
                ]);
                break;
                
            case 'recent_logs':
                $limit = (int)($_GET['limit'] ?? 10);
                $stmt = $pdo->prepare("
                    SELECT status, data, esp32_timestamp, created_at 
                    FROM fingerprint_status_log 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'logs' => $logs
                ]);
                break;
                
            case 'clear_session':
                unset($_SESSION['fingerprint_enrollment']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Enrollment session cleared'
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    logFingerprintOperation('error', [
        'message' => $e->getMessage(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'input' => file_get_contents('php://input')
    ]);
}

/**
 * Check if ESP32 is connected and responding
 */
function checkESP32Connection() {
    $esp32_ip = '192.168.137.90'; // Update this to match your ESP32 IP
    $timeout = 2;
    
    $context = stream_context_create([
        'http' => [
            'timeout' => $timeout,
            'method' => 'GET'
        ]
    ]);
    
    $result = @file_get_contents("http://{$esp32_ip}/status", false, $context);
    
    if ($result !== false) {
        $status = json_decode($result, true);
        return $status && isset($status['status']) && $status['status'] === 'ok';
    }
    
    return false;
}
?>
