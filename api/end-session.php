<?php
/**
 * End Attendance Session API
 * Closes an active attendance session
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
$user_id = $_SESSION['user_id'];

try {
    // Get session details and verify ownership
    $session_stmt = $pdo->prepare("
        SELECT id, lecturer_id, status 
        FROM attendance_sessions 
        WHERE id = ?
    ");
    $session_stmt->execute([$session_id]);
    $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode([
            "status" => "error",
            "message" => "Session not found"
        ]);
        exit;
    }
    
    // Verify the session belongs to this user
    if ($session['lecturer_id'] != $user_id) {
        echo json_encode([
            "status" => "error",
            "message" => "Unauthorized: This session does not belong to you"
        ]);
        exit;
    }
    
    // Check if session is already completed
    if ($session['status'] === 'completed') {
        echo json_encode([
            "status" => "error",
            "message" => "Session is already completed"
        ]);
        exit;
    }
    
    // End the session
    $update_stmt = $pdo->prepare("
        UPDATE attendance_sessions 
        SET status = 'completed',
            end_time = NOW()
        WHERE id = ?
    ");
    $update_stmt->execute([$session_id]);
    
    // Get final statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM students 
             WHERE option_id = (SELECT option_id FROM attendance_sessions WHERE id = ?)
             AND year_level = (SELECT year_level FROM attendance_sessions WHERE id = ?)
             AND status = 'active') as total_students,
            (SELECT COUNT(*) FROM attendance_records 
             WHERE session_id = ? AND status = 'present') as students_present
    ");
    $stats_stmt->execute([$session_id, $session_id, $session_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $attendance_rate = $stats['total_students'] > 0 
        ? round(($stats['students_present'] / $stats['total_students']) * 100, 1)
        : 0;
    
    error_log("Session ended: ID=$session_id, Present: {$stats['students_present']}/{$stats['total_students']} ($attendance_rate%)");
    
    echo json_encode([
        "status" => "success",
        "message" => "Session ended successfully",
        "statistics" => [
            "total_students" => $stats['total_students'],
            "students_present" => $stats['students_present'],
            "students_absent" => $stats['total_students'] - $stats['students_present'],
            "attendance_rate" => $attendance_rate
        ]
    ]);
    
} catch (Exception $e) {
    error_log("End session error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Failed to end session",
        "debug" => $e->getMessage()
    ]);
}
?>
