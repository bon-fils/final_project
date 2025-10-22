<?php
/**
 * Get Session Statistics API
 * Returns real-time attendance statistics for an active session
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

$session_id = filter_input(INPUT_GET, "session_id", FILTER_VALIDATE_INT);

if (!$session_id) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid session ID"
    ]);
    exit;
}

try {
    // Get session details
    $session_stmt = $pdo->prepare("SELECT * FROM attendance_sessions WHERE id = ?");
    $session_stmt->execute([$session_id]);
    $session = $session_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        echo json_encode([
            "status" => "error",
            "message" => "Session not found"
        ]);
        exit;
    }
    
    // Get total students for this session
    $total_stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM students
        WHERE option_id = ? AND year_level = ? AND status = 'active'
    ");
    $total_stmt->execute([$session['option_id'], $session['year_level']]);
    $total_count = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get present count
    $present_stmt = $pdo->prepare("
        SELECT COUNT(*) as present
        FROM attendance_records
        WHERE session_id = ? AND status = 'present'
    ");
    $present_stmt->execute([$session_id]);
    $present_count = $present_stmt->fetch(PDO::FETCH_ASSOC)['present'];
    
    // Calculate stats
    $absent_count = $total_count - $present_count;
    $attendance_rate = $total_count > 0 ? round(($present_count / $total_count) * 100, 1) : 0;
    
    echo json_encode([
        "status" => "success",
        "stats" => [
            "total" => $total_count,
            "present" => $present_count,
            "absent" => $absent_count,
            "rate" => $attendance_rate
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get session stats error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Failed to get session statistics",
        "debug" => $e->getMessage()
    ]);
}
?>
