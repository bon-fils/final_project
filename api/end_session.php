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
    $session_id = $_POST['session_id'] ?? null;
    $lecturer_id = $_SESSION['user_id'] ?? null;

    // Validate required fields
    if (!$session_id || !$lecturer_id) {
        throw new Exception('Missing required fields');
    }

    // Verify session exists and belongs to this lecturer
    $stmt = $pdo->prepare("
        SELECT id, lecturer_id, start_time, end_time
        FROM attendance_sessions
        WHERE id = ? AND lecturer_id = ? AND end_time IS NULL
    ");
    $stmt->execute([$session_id, $lecturer_id]);
    $session = $stmt->fetch();

    if (!$session) {
        throw new Exception('Active session not found or access denied');
    }

    // End the session
    $stmt = $pdo->prepare("
        UPDATE attendance_sessions
        SET end_time = NOW()
        WHERE id = ? AND lecturer_id = ?
    ");
    $stmt->execute([$session_id, $lecturer_id]);

    // Get final session statistics
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_records,
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count
        FROM attendance_records
        WHERE session_id = ?
    ");
    $stmt->execute([$session_id]);
    $stats = $stmt->fetch();

    echo json_encode([
        'status' => 'success',
        'message' => 'Session ended successfully',
        'session_id' => $session_id,
        'statistics' => [
            'total_records' => (int)$stats['total_records'],
            'present_count' => (int)$stats['present_count'],
            'absent_count' => (int)$stats['absent_count']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>