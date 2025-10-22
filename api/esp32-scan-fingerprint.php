<?php
/**
 * ESP32 Fingerprint Scanner Communication API
 * Scans fingerprint from ESP32 and matches with enrolled students
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once "../config.php";
session_start();

header("Content-Type: application/json");
if (ob_get_level()) ob_clean();

// Authentication check
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['session_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Session ID is required"
    ]);
    exit;
}

$session_id = (int)$input['session_id'];

try {
    // Get session details
    $session_stmt = $pdo->prepare("
        SELECT ats.*, c.name as course_name
        FROM attendance_sessions ats
        JOIN courses c ON ats.course_id = c.id
        WHERE ats.id = ? AND ats.status = 'active'
    ");
    $session_stmt->execute([$session_id]);
    $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid or inactive session"
        ]);
        exit;
    }
    
    // Step 1: Request fingerprint scan from ESP32
    error_log("📡 Requesting fingerprint scan from ESP32: " . ESP32_IP);
    
    $esp32_url = "http://" . ESP32_IP . ":" . ESP32_PORT . "/scan";
    
    // Initialize cURL with timeout
    $ch = curl_init($esp32_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => ESP32_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['action' => 'scan'])
    ]);
    
    $esp32_response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Check for connection errors
    if ($esp32_response === false || $http_code !== 200) {
        error_log("❌ ESP32 Connection Failed: $curl_error (HTTP: $http_code)");
        echo json_encode([
            "status" => "error",
            "message" => "Failed to connect to fingerprint scanner",
            "details" => "Scanner offline or network issue",
            "guidance" => "Please check:\n1. ESP32 is powered on\n2. Connected to WiFi\n3. IP address is correct: " . ESP32_IP,
            "esp32_ip" => ESP32_IP
        ]);
        exit;
    }
    
    // Parse ESP32 response
    $scan_data = json_decode($esp32_response, true);
    
    if (!$scan_data || !isset($scan_data['status'])) {
        error_log("❌ Invalid ESP32 response: " . $esp32_response);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid scanner response",
            "details" => "Scanner returned unexpected data"
        ]);
        exit;
    }
    
    // Check scan status
    if ($scan_data['status'] !== 'success') {
        error_log("⚠️ Fingerprint scan failed: " . ($scan_data['message'] ?? 'Unknown error'));
        
        echo json_encode([
            "status" => "scan_failed",
            "message" => $scan_data['message'] ?? "Fingerprint scan failed",
            "details" => $scan_data['details'] ?? "No finger detected or poor quality",
            "guidance" => "Please:\n1. Place finger firmly on sensor\n2. Keep finger still\n3. Ensure finger is clean and dry\n4. Try different finger if problem persists"
        ]);
        exit;
    }
    
    // Step 2: Get fingerprint ID from ESP32 response
    $fingerprint_id = $scan_data['fingerprint_id'] ?? null;
    $confidence = $scan_data['confidence'] ?? 0;
    
    if (!$fingerprint_id) {
        error_log("⚠️ Fingerprint not recognized by scanner");
        echo json_encode([
            "status" => "not_recognized",
            "message" => "Fingerprint not recognized",
            "details" => "No matching fingerprint found in scanner memory",
            "guidance" => "This fingerprint is not enrolled.\nPlease ensure:\n1. Student has registered fingerprint\n2. Using correct finger\n3. Fingerprint is enrolled in system"
        ]);
        exit;
    }
    
    error_log("✅ Fingerprint scanned - ID: $fingerprint_id, Confidence: $confidence%");
    
    // Step 3: Find student with this fingerprint ID in database
    error_log("🔍 Searching for student with fingerprint_id=$fingerprint_id in option_id={$session['option_id']}, year_level={$session['year_level']}");
    
    $student_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.user_id,
            s.reg_no,
            s.fingerprint_id,
            s.fingerprint_status,
            CONCAT(u.first_name, ' ', u.last_name) as name,
            u.first_name,
            u.last_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.option_id = ?
        AND s.year_level = ?
        AND s.status = 'active'
        AND s.fingerprint_id = ?
        AND s.fingerprint_status = 'enrolled'
    ");
    
    $student_stmt->execute([
        $session['option_id'],
        $session['year_level'],
        $fingerprint_id
    ]);
    
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        error_log("⚠️ No student found with fingerprint ID: $fingerprint_id for session {$session['id']}");
        
        // Debug: Check if fingerprint exists in database at all
        $debug_stmt = $pdo->prepare("
            SELECT s.id, s.reg_no, s.fingerprint_id, s.fingerprint_status, 
                   s.option_id, s.year_level, s.status,
                   CONCAT(u.first_name, ' ', u.last_name) as name
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.fingerprint_id = ?
            LIMIT 5
        ");
        $debug_stmt->execute([$fingerprint_id]);
        $debug_students = $debug_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($debug_students)) {
            error_log("📋 Found " . count($debug_students) . " student(s) with fingerprint_id=$fingerprint_id in database:");
            foreach ($debug_students as $ds) {
                error_log("  - {$ds['name']} (ID:{$ds['id']}, Reg:{$ds['reg_no']}, Status:{$ds['fingerprint_status']}, Option:{$ds['option_id']}, Year:{$ds['year_level']})");
            }
        } else {
            error_log("❌ Fingerprint ID $fingerprint_id does NOT exist in database at all!");
        }
        
        // Check if fingerprint exists but in different class
        $other_student_stmt = $pdo->prepare("
            SELECT CONCAT(u.first_name, ' ', u.last_name) as name, s.year_level, o.name as option_name
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN options o ON s.option_id = o.id
            WHERE s.fingerprint_id = ? AND s.fingerprint_status = 'enrolled'
        ");
        $other_student_stmt->execute([$fingerprint_id]);
        $other_student = $other_student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($other_student) {
            echo json_encode([
                "status" => "wrong_class",
                "message" => "Student not in this class",
                "details" => "Fingerprint belongs to: " . $other_student['name'],
                "guidance" => "This student is enrolled in:\n• " . $other_student['option_name'] . "\n• " . $other_student['year_level'] . "\n\nNot in current session class.",
                "fingerprint_id" => $fingerprint_id,
                "debug_url" => "http://localhost/final_project_1/check_fingerprint_ids.php?search_id=$fingerprint_id"
            ]);
        } else {
            echo json_encode([
                "status" => "not_enrolled",
                "message" => "Fingerprint not enrolled in system",
                "details" => "Scanner recognized fingerprint but no student record found",
                "guidance" => "Please contact administrator to:\n1. Check student registration\n2. Verify fingerprint enrollment\n3. Update student records",
                "fingerprint_id" => $fingerprint_id,
                "esp32_returned" => "Fingerprint ID: $fingerprint_id",
                "debug_url" => "http://localhost/final_project_1/check_fingerprint_ids.php?search_id=$fingerprint_id",
                "help" => "Visit debug_url to see if this fingerprint_id exists in database"
            ]);
        }
        exit;
    }
    
    // Step 4: Check if attendance already marked
    $check_stmt = $pdo->prepare("
        SELECT id, recorded_at 
        FROM attendance_records 
        WHERE session_id = ? AND student_id = ?
    ");
    $check_stmt->execute([$session_id, $student['id']]);
    $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_record) {
        error_log("⚠️ Attendance already marked for: " . $student['name']);
        echo json_encode([
            "status" => "already_marked",
            "message" => "Attendance already recorded",
            "student" => [
                "id" => $student['id'],
                "name" => $student['name'],
                "reg_no" => $student['reg_no']
            ],
            "details" => "Marked at: " . date('H:i:s', strtotime($existing_record['recorded_at'])),
            "guidance" => "This student's attendance is already recorded for this session."
        ]);
        exit;
    }
    
    // Step 5: Mark attendance
    $insert_stmt = $pdo->prepare("
        INSERT INTO attendance_records 
        (session_id, student_id, status, recorded_at)
        VALUES (?, ?, 'present', NOW())
    ");
    
    $insert_stmt->execute([
        $session_id,
        $student['id']
    ]);
    
    error_log("✅ Attendance marked - Student: {$student['name']} ({$student['reg_no']}), Session: $session_id");
    
    echo json_encode([
        "status" => "success",
        "message" => "Attendance marked successfully",
        "student" => [
            "id" => $student['id'],
            "name" => $student['name'],
            "reg_no" => $student['reg_no'],
            "first_name" => $student['first_name'],
            "last_name" => $student['last_name']
        ],
        "confidence" => $confidence,
        "fingerprint_id" => $fingerprint_id,
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("❌ Fingerprint scan error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "System error occurred",
        "details" => $e->getMessage(),
        "guidance" => "Please try again or contact system administrator"
    ]);
}
?>
