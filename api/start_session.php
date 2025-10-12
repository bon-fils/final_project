<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once "../config.php";
require_once "../session_check.php";

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // Get POST data
    $lecturer_id = $_SESSION['user_id'] ?? null;
    $department_id = $_POST['department_id'] ?? null;
    $option_id = $_POST['option_id'] ?? null;
    $course_id = $_POST['course_id'] ?? null;
    $biometric_method = $_POST['biometric_method'] ?? null;

    // Validate required fields
    if (!$lecturer_id || !$department_id || !$option_id || !$course_id || !$biometric_method) {
        throw new Exception('Missing required fields');
    }

    // Validate biometric method
    if (!in_array($biometric_method, ['face', 'finger'])) {
        throw new Exception('Invalid biometric method');
    }

    // Check if there's already an active session for this lecturer
    $stmt = $pdo->prepare("
        SELECT id, course_id, start_time
        FROM attendance_sessions
        WHERE lecturer_id = ? AND end_time IS NULL
        ORDER BY start_time DESC LIMIT 1
    ");
    $stmt->execute([$lecturer_id]);
    $existing_session = $stmt->fetch();

    if ($existing_session) {
        // Return existing session info
        echo json_encode([
            'status' => 'existing_session',
            'message' => 'Active session already exists',
            'existing_session' => [
                'id' => $existing_session['id'],
                'course_id' => $existing_session['course_id'],
                'start_time' => $existing_session['start_time']
            ]
        ]);
        exit;
    }

    // Start new session
    $stmt = $pdo->prepare("
        INSERT INTO attendance_sessions
        (lecturer_id, department_id, option_id, course_id, session_date, start_time, biometric_method)
        VALUES (?, ?, ?, ?, CURDATE(), NOW(), ?)
    ");

    $stmt->execute([$lecturer_id, $department_id, $option_id, $course_id, $biometric_method]);
    $session_id = $pdo->lastInsertId();

    // Get session details for response
    $stmt = $pdo->prepare("
        SELECT
            s.*,
            d.name as department_name,
            o.name as option_name,
            c.name as course_name,
            c.course_code
        FROM attendance_sessions s
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN options o ON s.option_id = o.id
        LEFT JOIN courses c ON s.course_id = c.id
        WHERE s.id = ?
    ");
    $stmt->execute([$session_id]);
    $session_data = $stmt->fetch();

    echo json_encode([
        'status' => 'success',
        'message' => 'Session started successfully',
        'session_id' => $session_id,
        'data' => $session_data
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>