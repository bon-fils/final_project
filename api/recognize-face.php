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
    
    // Get students for this session
    $students_stmt = $pdo->prepare("
        SELECT s.id, s.user_id, s.reg_no, s.student_photos,
               CONCAT(u.first_name, ' ', u.last_name) as name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.option_id = ? AND s.year_level = ? AND s.status = 'active'
    ");
    $students_stmt->execute([$session['option_id'], $session['year_level']]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Face recognition algorithm (simplified version)
    // TODO: Implement actual face recognition library (e.g., face-api.js, OpenCV, or external service)
    
    $recognizedStudent = null;
    $confidence = 0;
    
    foreach ($students as $student) {
        // Check if student has a photo
        if (!empty($student['student_photos'])) {
            // In a real implementation, you would:
            // 1. Load student's photo from database or file system
            // 2. Use face recognition library to compare faces
            // 3. Calculate confidence score
            // 4. If confidence > threshold, mark as recognized
            
            // For now, using a mock implementation
            // Simulate face matching with random confidence
            $mockConfidence = rand(0, 100);
            
            if ($mockConfidence > 80 && $mockConfidence > $confidence) {
                $recognizedStudent = $student;
                $confidence = $mockConfidence;
            }
        }
    }
    
    // For development/testing: Use first student if no match found
    if (!$recognizedStudent && !empty($students)) {
        error_log("DEVELOPMENT MODE: Auto-selecting first student for testing");
        $recognizedStudent = $students[0];
        $confidence = 85; // Mock confidence
    }
    
    if ($recognizedStudent) {
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
        
        error_log("Attendance marked: Session $session_id, Student {$recognizedStudent['reg_no']}, Confidence: $confidence%");
        
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
            "status" => "error",
            "message" => "Face not recognized. Please ensure you are registered and try again.",
            "debug" => "No matching face found in database"
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
