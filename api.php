<?php
/**
 * Enhanced API for ESP32 Fingerprint Integration
 * Handles fingerprint enrollment, identification, and attendance tracking
 * Version: 2.0 - ESP32 Direct Communication
 */

require_once 'config.php';
require_once 'security_utils.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// ESP32 Configuration - Now using config.php constants
define('ESP32_TIMEOUT', 10); // seconds

$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

switch ($action) {
    case 'identify':
        handleIdentify();
        break;
    case 'enroll':
        handleEnroll();
        break;
    case 'status':
        handleStatus();
        break;
    case 'scan':
        handleScan();
        break;
    case 'start_scan_mode':
        handleStartScanMode();
        break;
    case 'stop_scan_mode':
        handleStopScanMode();
        break;
    case 'get_recent_activity':
        handleGetRecentActivity();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function handleIdentify() {
    global $conn;

    try {
        // Call ESP32 to get fingerprint identification
        $esp32Response = esp32Request('/identify');

        if (!$esp32Response || !$esp32Response['success']) {
            echo json_encode([
                'success' => false,
                'error' => 'ESP32 communication failed',
                'details' => $esp32Response['error'] ?? 'Unknown ESP32 error'
            ]);
            return;
        }

        $fingerprintId = $esp32Response['fingerprint_id'] ?? null;

        if (!$fingerprintId) {
            echo json_encode(['success' => false, 'error' => 'No fingerprint detected']);
            return;
        }

        // Find student by fingerprint ID
        $stmt = $conn->prepare("SELECT id, student_id, first_name, last_name, reg_no FROM students WHERE fingerprint_id = ? AND status = 'active'");
        $stmt->execute([$fingerprintId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            // Log unknown fingerprint attempt
            log_message('warning', 'Unknown fingerprint detected', ['fingerprint_id' => $fingerprintId]);
            echo json_encode(['success' => false, 'error' => 'Fingerprint not recognized']);
            return;
        }

        // Check current attendance status for today
        $stmt = $conn->prepare("SELECT id, check_in_time, check_out_time FROM attendance WHERE student_id = ? AND date = CURDATE() ORDER BY check_in_time DESC LIMIT 1");
        $stmt->execute([$student['id']]);
        $lastAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

        $studentName = $student['first_name'] . ' ' . $student['last_name'];

        if (!$lastAttendance || $lastAttendance['check_out_time']) {
            // Check-in: No attendance today or last session was checked out
            $stmt = $conn->prepare("INSERT INTO attendance (student_id, fingerprint_id, date, check_in_time, status) VALUES (?, ?, CURDATE(), NOW(), 'present')");
            $stmt->execute([$student['id'], $fingerprintId]);

            // Send success message to ESP32
            esp32Request('/display', 'GET', ['message' => 'Check-in OK']);

            log_message('info', 'Student check-in recorded', [
                'student_id' => $student['student_id'],
                'reg_no' => $student['reg_no'],
                'fingerprint_id' => $fingerprintId
            ]);

            echo json_encode([
                'success' => true,
                'student' => [
                    'id' => $student['id'],
                    'student_id' => $student['student_id'],
                    'name' => $studentName,
                    'reg_no' => $student['reg_no']
                ],
                'message' => 'Check-in recorded successfully',
                'action' => 'check_in',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            // Check-out: Has check-in but no check-out
            $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE id = ?");
            $stmt->execute([$lastAttendance['id']]);

            // Send success message to ESP32
            esp32Request('/display', 'GET', ['message' => 'Check-out OK']);

            log_message('info', 'Student check-out recorded', [
                'student_id' => $student['student_id'],
                'reg_no' => $student['reg_no'],
                'fingerprint_id' => $fingerprintId
            ]);

            echo json_encode([
                'success' => true,
                'student' => [
                    'id' => $student['id'],
                    'student_id' => $student['student_id'],
                    'name' => $studentName,
                    'reg_no' => $student['reg_no']
                ],
                'message' => 'Check-out recorded successfully',
                'action' => 'check_out',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    } catch (Exception $e) {
        error_log("Fingerprint identification error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Identification failed',
            'details' => $e->getMessage()
        ]);
    }
}

function handleEnroll() {
    global $conn;

    try {
        if (!isset($_POST['student_id'])) {
            echo json_encode(['success' => false, 'error' => 'Student ID required']);
            return;
        }

        $studentId = (int)$_POST['student_id'];

        // Get student details
        $stmt = $conn->prepare("SELECT id, student_id, first_name, last_name, reg_no, fingerprint_id, fingerprint_status FROM students WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode(['success' => false, 'error' => 'Student not found']);
            return;
        }

        if ($student['fingerprint_id'] && $student['fingerprint_status'] === 'enrolled') {
            echo json_encode(['success' => false, 'error' => 'Student already has fingerprint enrolled']);
            return;
        }

        // Check ESP32 connectivity first
        $statusResponse = esp32Request('/status');
        if (!$statusResponse || !$statusResponse['success']) {
            echo json_encode([
                'success' => false,
                'error' => 'ESP32 not connected',
                'details' => 'Cannot enroll fingerprint - ESP32 device is not reachable'
            ]);
            return;
        }

        // Generate or use existing fingerprint ID
        $fingerprintId = $student['fingerprint_id'] ?: generateFingerprintId();

        // Update student status to enrolling
        $stmt = $conn->prepare("UPDATE students SET fingerprint_id = ?, fingerprint_status = 'enrolling' WHERE id = ?");
        $stmt->execute([$fingerprintId, $student['id']]);

        // Prepare enrollment data for ESP32
        $enrollmentData = [
            'id' => $fingerprintId,
            'student_name' => $student['first_name'] . ' ' . $student['last_name'],
            'reg_no' => $student['reg_no']
        ];

        // Call ESP32 to enroll fingerprint
        $esp32Response = esp32Request('/enroll', 'POST', $enrollmentData);

        if ($esp32Response && $esp32Response['success']) {
            // Update student with successful enrollment
            $stmt = $conn->prepare("UPDATE students SET fingerprint_status = 'enrolled', fingerprint_enrolled_at = NOW() WHERE id = ?");
            $stmt->execute([$student['id']]);

            log_message('info', 'Fingerprint enrollment completed', [
                'student_id' => $student['student_id'],
                'reg_no' => $student['reg_no'],
                'fingerprint_id' => $fingerprintId
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Fingerprint enrolled successfully',
                'fingerprint_id' => $fingerprintId,
                'student' => [
                    'id' => $student['id'],
                    'student_id' => $student['student_id'],
                    'name' => $student['first_name'] . ' ' . $student['last_name'],
                    'reg_no' => $student['reg_no']
                ]
            ]);
        } else {
            // Reset student status on failure
            $stmt = $conn->prepare("UPDATE students SET fingerprint_id = NULL, fingerprint_status = NULL WHERE id = ?");
            $stmt->execute([$student['id']]);

            $errorMsg = $esp32Response['error'] ?? 'Unknown ESP32 error';
            log_message('error', 'Fingerprint enrollment failed', [
                'student_id' => $student['student_id'],
                'error' => $errorMsg
            ]);

            echo json_encode([
                'success' => false,
                'error' => 'Fingerprint enrollment failed',
                'details' => $errorMsg
            ]);
        }
    } catch (Exception $e) {
        error_log("Fingerprint enrollment error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Enrollment failed',
            'details' => $e->getMessage()
        ]);
    }
}

function handleStatus() {
    global $conn;

    try {
        // Get system stats
        $stmt = $conn->query("SELECT COUNT(*) as total_students FROM students WHERE status = 'active'");
        $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];

        $stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as today_attendance FROM attendance WHERE date = CURDATE()");
        $stmt->execute();
        $todayAttendance = $stmt->fetch(PDO::FETCH_ASSOC)['today_attendance'];

        $stmt = $conn->query("SELECT COUNT(*) as enrolled_fingerprints FROM students WHERE fingerprint_status = 'enrolled'");
        $enrolledFingerprints = $stmt->fetch(PDO::FETCH_ASSOC)['enrolled_fingerprints'];

        // Get ESP32 status
        $esp32Status = esp32Request('/status');

        $systemStatus = [
            'database' => 'connected',
            'total_students' => (int)$totalStudents,
            'today_attendance' => (int)$todayAttendance,
            'enrolled_fingerprints' => (int)$enrolledFingerprints,
            'server_time' => date('Y-m-d H:i:s'),
            'uptime' => getSystemUptime()
        ];

        $esp32Info = $esp32Status ?: [
            'status' => 'disconnected',
            'fingerprint_sensor' => 'unknown',
            'wifi' => 'disconnected',
            'ip' => null
        ];

        echo json_encode([
            'success' => true,
            'system' => $systemStatus,
            'esp32' => $esp32Info,
            'network' => [
                'esp32_ip' => ESP32_IP,
                'esp32_port' => ESP32_PORT,
                'server_ip' => $_SERVER['SERVER_ADDR'] ?? 'unknown'
            ]
        ]);
    } catch (Exception $e) {
        error_log("Status check error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Status check failed',
            'details' => $e->getMessage()
        ]);
    }
}

function handleScan() {
    global $conn;

    try {
        $mode = isset($_GET['mode']) ? $_GET['mode'] : 'auto';

        // Call ESP32 to identify fingerprint
        $esp32Response = esp32Request('/identify');

        if (!$esp32Response || !$esp32Response['success']) {
            echo json_encode([
                'success' => false,
                'error' => 'ESP32 scan failed',
                'details' => $esp32Response['error'] ?? 'No fingerprint detected'
            ]);
            return;
        }

        $fingerprintId = $esp32Response['fingerprint_id'] ?? null;

        if (!$fingerprintId) {
            echo json_encode(['success' => false, 'error' => 'No fingerprint detected']);
            return;
        }

        // Find student by fingerprint ID
        $stmt = $conn->prepare("SELECT id, student_id, first_name, last_name, reg_no FROM students WHERE fingerprint_id = ? AND status = 'active'");
        $stmt->execute([$fingerprintId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            log_message('warning', 'Unrecognized fingerprint scan', ['fingerprint_id' => $fingerprintId]);
            echo json_encode(['success' => false, 'error' => 'Fingerprint not recognized']);
            return;
        }

        $studentName = $student['first_name'] . ' ' . $student['last_name'];

        // Check current attendance status for today
        $stmt = $conn->prepare("SELECT id, check_in_time, check_out_time FROM attendance WHERE student_id = ? AND date = CURDATE() ORDER BY check_in_time DESC LIMIT 1");
        $stmt->execute([$student['id']]);
        $lastAttendance = $stmt->fetch(PDO::FETCH_ASSOC);

        $hasCheckedIn = $lastAttendance && !$lastAttendance['check_out_time'];

        if ($mode === 'checkin') {
            // Check-in only mode
            if (!$lastAttendance || $lastAttendance['check_out_time']) {
                // Allow check-in
                $stmt = $conn->prepare("INSERT INTO attendance (student_id, fingerprint_id, date, check_in_time, status) VALUES (?, ?, CURDATE(), NOW(), 'present')");
                $stmt->execute([$student['id'], $fingerprintId]);

                esp32Request('/display', 'GET', ['message' => 'Check-in OK']);

                log_message('info', 'Manual check-in recorded', [
                    'student_id' => $student['student_id'],
                    'reg_no' => $student['reg_no'],
                    'mode' => 'checkin'
                ]);

                echo json_encode([
                    'success' => true,
                    'student' => [
                        'id' => $student['id'],
                        'student_id' => $student['student_id'],
                        'name' => $studentName,
                        'reg_no' => $student['reg_no']
                    ],
                    'action' => 'check_in',
                    'message' => 'Check-in recorded successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Already checked in
                echo json_encode([
                    'success' => false,
                    'error' => 'already_checked_in',
                    'student' => [
                        'id' => $student['id'],
                        'student_id' => $student['student_id'],
                        'name' => $studentName,
                        'reg_no' => $student['reg_no']
                    ],
                    'message' => 'Student already checked in today'
                ]);
            }
        } elseif ($mode === 'checkout') {
            // Check-out only mode
            if ($hasCheckedIn) {
                // Allow check-out
                $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE id = ?");
                $stmt->execute([$lastAttendance['id']]);

                esp32Request('/display', 'GET', ['message' => 'Check-out OK']);

                log_message('info', 'Manual check-out recorded', [
                    'student_id' => $student['student_id'],
                    'reg_no' => $student['reg_no'],
                    'mode' => 'checkout'
                ]);

                echo json_encode([
                    'success' => true,
                    'student' => [
                        'id' => $student['id'],
                        'student_id' => $student['student_id'],
                        'name' => $studentName,
                        'reg_no' => $student['reg_no']
                    ],
                    'action' => 'check_out',
                    'message' => 'Check-out recorded successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Not checked in yet
                echo json_encode([
                    'success' => false,
                    'error' => 'not_checked_in',
                    'student' => [
                        'id' => $student['id'],
                        'student_id' => $student['student_id'],
                        'name' => $studentName,
                        'reg_no' => $student['reg_no']
                    ],
                    'message' => 'Student not checked in yet'
                ]);
            }
        } else {
            // Auto mode (original logic)
            if (!$lastAttendance || $lastAttendance['check_out_time']) {
                // Check-in
                $stmt = $conn->prepare("INSERT INTO attendance (student_id, fingerprint_id, date, check_in_time, status) VALUES (?, ?, CURDATE(), NOW(), 'present')");
                $stmt->execute([$student['id'], $fingerprintId]);

                esp32Request('/display', 'GET', ['message' => 'Check-in OK']);

                log_message('info', 'Auto check-in recorded', [
                    'student_id' => $student['student_id'],
                    'reg_no' => $student['reg_no'],
                    'mode' => 'auto'
                ]);

                echo json_encode([
                    'success' => true,
                    'student' => [
                        'id' => $student['id'],
                        'student_id' => $student['student_id'],
                        'name' => $studentName,
                        'reg_no' => $student['reg_no']
                    ],
                    'action' => 'check_in',
                    'message' => 'Check-in recorded successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                // Check-out
                $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE id = ?");
                $stmt->execute([$lastAttendance['id']]);

                esp32Request('/display', 'GET', ['message' => 'Check-out OK']);

                log_message('info', 'Auto check-out recorded', [
                    'student_id' => $student['student_id'],
                    'reg_no' => $student['reg_no'],
                    'mode' => 'auto'
                ]);

                echo json_encode([
                    'success' => true,
                    'student' => [
                        'id' => $student['id'],
                        'student_id' => $student['student_id'],
                        'name' => $studentName,
                        'reg_no' => $student['reg_no']
                    ],
                    'action' => 'check_out',
                    'message' => 'Check-out recorded successfully',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Scan error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Scan failed',
            'details' => $e->getMessage()
        ]);
    }
}

function handleStartScanMode() {
    try {
        // Tell ESP32 to enter continuous scan mode
        $esp32Response = esp32Request('/start_scan');

        if ($esp32Response && $esp32Response['success']) {
            log_message('info', 'Continuous scan mode started');
            echo json_encode([
                'success' => true,
                'message' => 'Continuous scan mode started',
                'esp32_response' => $esp32Response
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to start ESP32 scan mode',
                'details' => $esp32Response['error'] ?? 'Unknown error'
            ]);
        }
    } catch (Exception $e) {
        error_log("Start scan mode error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Failed to start scan mode',
            'details' => $e->getMessage()
        ]);
    }
}

function handleStopScanMode() {
    try {
        // Tell ESP32 to exit continuous scan mode
        $esp32Response = esp32Request('/stop_scan');

        if ($esp32Response && $esp32Response['success']) {
            log_message('info', 'Continuous scan mode stopped');
            echo json_encode([
                'success' => true,
                'message' => 'Continuous scan mode stopped',
                'esp32_response' => $esp32Response
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to stop ESP32 scan mode',
                'details' => $esp32Response['error'] ?? 'Unknown error'
            ]);
        }
    } catch (Exception $e) {
        error_log("Stop scan mode error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Failed to stop scan mode',
            'details' => $e->getMessage()
        ]);
    }
}

function handleGetRecentActivity() {
    global $conn;

    try {
        $stmt = $conn->query("
            SELECT a.check_in_time, a.check_out_time, s.student_id, s.first_name, s.last_name, s.reg_no
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            WHERE DATE(a.check_in_time) = CURDATE()
            ORDER BY COALESCE(a.check_out_time, a.check_in_time) DESC
            LIMIT 10
        ");

        $activities = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $isCheckOut = !empty($row['check_out_time']);
            $time = $isCheckOut ? $row['check_out_time'] : $row['check_in_time'];
            $action = $isCheckOut ? 'Check-out' : 'Check-in';

            $activities[] = [
                'student_id' => $row['student_id'],
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'reg_no' => $row['reg_no'],
                'action' => $action,
                'time' => date('H:i:s', strtotime($time)),
                'timestamp' => $time,
                'is_checkout' => $isCheckOut
            ];
        }

        echo json_encode([
            'success' => true,
            'activities' => $activities,
            'count' => count($activities),
            'date' => date('Y-m-d')
        ]);
    } catch (Exception $e) {
        error_log("Get recent activity error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Failed to get recent activity',
            'details' => $e->getMessage()
        ]);
    }
}
/**
 * Enhanced ESP32 Communication Function
 */
// Use the centralized ESP32 communication function from config.php

/**
 * Generate unique fingerprint ID
 */
function generateFingerprintId() {
    global $conn;

    $maxAttempts = 100;
    $attempts = 0;

    do {
        $id = rand(1, 999); // ESP32 typically supports 1-999 fingerprints
        $attempts++;

        $stmt = $conn->prepare("SELECT id FROM students WHERE fingerprint_id = ?");
        $stmt->execute([$id]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$exists) {
            return $id;
        }
    } while ($attempts < $maxAttempts);

    throw new Exception('Unable to generate unique fingerprint ID');
}

/**
 * Get system uptime (simplified)
 */
function getSystemUptime() {
    // This is a simplified uptime calculation
    // In production, you might read from /proc/uptime on Linux
    static $startTime = null;
    if ($startTime === null) {
        $startTime = time();
    }
    return time() - $startTime;
}

/**
 * Enhanced logging function
 */
function log_message($level, $message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => strtoupper($level),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];

    $logFile = __DIR__ . '/../logs/api_' . date('Y-m-d') . '.log';
    $logDir = dirname($logFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

?>