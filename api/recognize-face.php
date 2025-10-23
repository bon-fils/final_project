<?php
/**
 * Face Recognition API
 * Captures face image and compares with student database
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

if (!isset($input['image']) || !isset($input['session_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required parameters"
    ]);
    exit;
}

$imageData = $input['image'];
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
    
    // Remove base64 header
    $imageData = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
    $decodedImage = base64_decode($imageData);
    
    // Save captured image temporarily
    $temp_path = '../temp/captured_' . time() . '.jpg';
    if (!is_dir('../temp')) {
        mkdir('../temp', 0777, true);
    }
    file_put_contents($temp_path, $decodedImage);
    
    // Call Python face recognition script
    $pythonScript = __DIR__ . '/../face_recognition_compare.py';
    $command = sprintf(
        'python "%s" "%s" %d 2>&1',
        $pythonScript,
        $temp_path,
        $session_id
    );
    
    error_log("Executing face recognition: $command");
    $output = shell_exec($command);
    error_log("Face recognition output: $output");
    
    // Parse JSON output from Python script
    $recognitionResult = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Failed to parse Python output: $output");
        error_log("JSON Error: " . json_last_error_msg());
        echo json_encode([
            "status" => "error",
            "message" => "Face recognition service error",
            "debug" => "Failed to parse recognition result",
            "raw_output" => substr($output, 0, 500),  // First 500 chars for debugging
            "json_error" => json_last_error_msg()
        ]);
        exit;
    }
    
    // Check recognition result
    if ($recognitionResult['status'] !== 'success') {
        echo json_encode($recognitionResult);
        exit;
    }
    
    // Get recognized student details
    $recognizedStudent = $recognitionResult['student'];
    $confidence = $recognitionResult['confidence'];
    
    if ($recognizedStudent && isset($recognizedStudent['id'])) {
        // Check if already marked present
        $check_stmt = $pdo->prepare("
            SELECT id FROM attendance_records 
            WHERE session_id = ? AND student_id = ?
        ");
        $check_stmt->execute([$session_id, $recognizedStudent['id']]);
        
        $existing = $check_stmt->fetch();
        if ($existing) {
            echo json_encode([
                "status" => "already_marked",
                "message" => "Attendance already recorded",
                "details" => "This student's attendance is already marked for this session",
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
            (session_id, student_id, status, recorded_at)
            VALUES (?, ?, 'present', NOW())
        ");
        $insert_stmt->execute([
            $session_id,
            $recognizedStudent['id']
        ]);
        
        error_log("✅ REAL FACE MATCH: Session $session_id, Student {$recognizedStudent['reg_no']}, Confidence: $confidence%, Distance: {$recognitionResult['face_distance']}");
        
        echo json_encode([
            "status" => "success",
            "message" => "Attendance marked successfully",
            "student" => [
                "id" => $recognizedStudent['id'],
                "name" => $recognizedStudent['name'],
                "reg_no" => $recognizedStudent['reg_no']
            ],
            "confidence" => $confidence,
            "timestamp" => date('Y-m-d H:i:s')
        ]);
        
    } else {
        echo json_encode([
            "status" => "not_recognized",
            "message" => "Face not recognized",
            "details" => "No matching face found in database. Please ensure you are registered with a photo.",
            "debug" => $recognitionResult
        ]);
    }
    
    // Clean up temp file
    if (file_exists($temp_path)) {
        unlink($temp_path);
    }
    
} catch (Exception $e) {
    error_log("Face recognition error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Face recognition failed",
        "debug" => $e->getMessage()
    ]);
}
?>
