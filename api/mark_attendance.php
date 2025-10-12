<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once "../config.php";
require_once "../session_check.php";

try {
    // Get parameters
    $method = $_GET['method'] ?? $_POST['method'] ?? null;
    $session_id = $_GET['session_id'] ?? $_POST['session_id'] ?? null;

    // Validate method
    if (!$method || !in_array($method, ['face', 'finger'])) {
        throw new Exception('Invalid or missing method parameter');
    }

    // Validate session_id
    if (!$session_id) {
        throw new Exception('Session ID is required');
    }

    // Verify session is active
    $stmt = $pdo->prepare("
        SELECT id, lecturer_id, biometric_method, session_date
        FROM attendance_sessions
        WHERE id = ? AND end_time IS NULL
    ");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();

    if (!$session) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Session Not Active'
        ]);
        exit;
    }

    // Check if session date matches today
    $today = date('Y-m-d');
    if ($session['session_date'] !== $today) {
        throw new Exception('Session is not for today');
    }

    $student_id = null;
    $confidence = null;

    if ($method === 'face') {
        // Handle face recognition
        $image_data = $_POST['image_data'] ?? null;
        if (!$image_data) {
            throw new Exception('Image data is required for face recognition');
        }

        // Process face recognition (call Python script)
        $temp_file = processFaceImage($image_data);
        $face_result = callFaceRecognitionScript($temp_file);

        if ($face_result['status'] !== 'success') {
            echo json_encode([
                'status' => 'error',
                'message' => $face_result['message'] ?? 'Face recognition failed'
            ]);
            exit;
        }

        $student_id = $face_result['student_id'];
        $confidence = $face_result['confidence'];

    } elseif ($method === 'finger') {
        // Handle fingerprint
        $fingerprint_id = $_GET['fingerprint_id'] ?? $_POST['fingerprint_id'] ?? null;
        if (!$fingerprint_id) {
            throw new Exception('Fingerprint ID is required');
        }

        // Get student_id from fingerprint_id
        $stmt = $pdo->prepare("
            SELECT student_id
            FROM student_biometric_data
            WHERE fingerprint_id = ? AND biometric_type = 'fingerprint'
        ");
        $stmt->execute([$fingerprint_id]);
        $biometric_data = $stmt->fetch();

        if (!$biometric_data) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Fingerprint Not Registered'
            ]);
            exit;
        }

        $student_id = $biometric_data['student_id'];
    }

    // Check if student already marked attendance today
    $stmt = $pdo->prepare("
        SELECT id FROM attendance_records
        WHERE session_id = ? AND student_id = ? AND DATE(recorded_at) = CURDATE()
    ");
    $stmt->execute([$session_id, $student_id]);
    $existing_record = $stmt->fetch();

    if ($existing_record) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Already Marked Today'
        ]);
        exit;
    }

    // Record attendance
    $stmt = $pdo->prepare("
        INSERT INTO attendance_records
        (session_id, student_id, status, method, recorded_at, confidence)
        VALUES (?, ?, 'present', ?, NOW(), ?)
    ");
    $stmt->execute([$session_id, $student_id, $method, $confidence]);

    // Get student details for response
    $stmt = $pdo->prepare("
        SELECT
            s.first_name,
            s.last_name,
            s.registration_number,
            CONCAT(s.first_name, ' ', s.last_name) as full_name
        FROM students s
        WHERE s.id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    echo json_encode([
        'status' => 'success',
        'message' => 'Attendance Marked',
        'student' => [
            'id' => $student_id,
            'name' => $student['full_name'] ?? 'Unknown Student',
            'registration_number' => $student['registration_number'] ?? null
        ],
        'method' => $method,
        'confidence' => $confidence,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function processFaceImage($imageData) {
    // Create temporary file for the image
    $tempDir = sys_get_temp_dir();
    $tempFile = tempnam($tempDir, 'face_capture_');
    $imageFile = $tempFile . '.jpg';

    // Clean up temp file if it exists
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }

    // Decode and save base64 image
    if (strpos($imageData, 'data:image') === 0) {
        $imageData = explode(',', $imageData)[1];
    }
    $imageBinary = base64_decode($imageData);

    if ($imageBinary === false) {
        throw new Exception('Invalid base64 image data');
    }

    if (file_put_contents($imageFile, $imageBinary) === false) {
        throw new Exception('Failed to save temporary image file');
    }

    return $imageFile;
}

function callFaceRecognitionScript($imageFile) {
    // Call Python face recognition script
    $pythonScript = __DIR__ . '/../face_match.py';
    $command = escapeshellcmd('python3') . ' ' . escapeshellarg($pythonScript) . ' ' . escapeshellarg($imageFile);

    // Execute command
    $output = shell_exec($command . " 2>&1");

    // Clean up temporary file
    if (file_exists($imageFile)) {
        unlink($imageFile);
    }

    // Parse JSON output
    $result = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid response from face recognition script');
    }

    return $result;
}
?>