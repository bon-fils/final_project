<?php
/**
 * Fingerprint Scanner API
 * Handles fingerprint scanning and student verification
 */

require_once "../config.php";
session_start();

header("Content-Type: application/json");

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
        "message" => "Missing session ID"
    ]);
    exit;
}

$session_id = (int)$input['session_id'];

try {
    // Get session details
    $session_stmt = $pdo->prepare("
        SELECT * FROM attendance_sessions 
        WHERE id = ? AND status = 'active'
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
    
    // TODO: Implement actual fingerprint scanner integration
    // This would typically involve:
    // 1. Communication with fingerprint scanner hardware/SDK
    // 2. Capturing fingerprint data
    // 3. Comparing with enrolled fingerprints in database
    
    // For now, using mock implementation for development
    // Get students with enrolled fingerprints
    $students_stmt = $pdo->prepare("
        SELECT s.id, s.user_id, s.reg_no, s.fingerprint_id, s.fingerprint_status,
               CONCAT(u.first_name, ' ', u.last_name) as name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.option_id = ? 
        AND s.year_level = ? 
        AND s.status = 'active'
        AND s.fingerprint_status = 'enrolled'
    ");
    $students_stmt->execute([$session['option_id'], $session['year_level']]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        echo json_encode([
            "status" => "error",
            "message" => "No students with enrolled fingerprints found for this session"
        ]);
        exit;
    }
    
    // Mock fingerprint scanning - select random student for testing
    $recognizedStudent = $students[array_rand($students)];
    
    error_log("DEVELOPMENT MODE: Mock fingerprint scan - selected student: " . $recognizedStudent['reg_no']);
    
    // Check if already marked present
    $check_stmt = $pdo->prepare("
        SELECT id FROM attendance_records 
        WHERE session_id = ? AND student_id = ?
    ");
    $check_stmt->execute([$session_id, $recognizedStudent['id']]);
    
    if ($check_stmt->fetch()) {
        echo json_encode([
            "status" => "error",
            "message" => "Attendance already marked for this student",
            "student" => [
                "name" => $recognizedStudent['name'],
                "reg_no" => $recognizedStudent['reg_no']
            ]
        ]);
        exit;
    }
    
    // Mark attendance
    $insert_stmt = $pdo->prepare("
        INSERT INTO attendance_records 
        (session_id, student_id, status, recorded_at, verification_method, biometric_data)
        VALUES (?, ?, 'present', NOW(), 'fingerprint', ?)
    ");
    $insert_stmt->execute([
        $session_id,
        $recognizedStudent['id'],
        $recognizedStudent['fingerprint_id']
    ]);
    
    error_log("Attendance marked: Session $session_id, Student {$recognizedStudent['reg_no']}, Method: fingerprint");
    
    echo json_encode([
        "status" => "success",
        "message" => "Attendance marked successfully",
        "student" => [
            "id" => $recognizedStudent['id'],
            "name" => $recognizedStudent['name'],
            "reg_no" => $recognizedStudent['reg_no']
        ],
        "timestamp" => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    error_log("Fingerprint scan error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Fingerprint scanning failed",
        "debug" => $e->getMessage()
    ]);
}
?>
