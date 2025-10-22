<?php
/**
 * Debug Session Creation Issue
 */

require_once "config.php";
session_start();

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Check for active sessions
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            session_date, 
            start_time, 
            status,
            course_id,
            option_id,
            year_level,
            biometric_method,
            TIMESTAMPDIFF(SECOND, start_time, NOW()) as seconds_since_start
        FROM attendance_sessions 
        WHERE lecturer_id = ? 
        AND status = 'active'
        ORDER BY id DESC
    ");
    $stmt->execute([$user_id]);
    $active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check today's sessions specifically
    $today_stmt = $pdo->prepare("
        SELECT 
            id, 
            session_date, 
            start_time, 
            status
        FROM attendance_sessions 
        WHERE lecturer_id = ? 
        AND session_date = CURDATE()
        ORDER BY id DESC
    ");
    $today_stmt->execute([$user_id]);
    $today_sessions = $today_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "user_id" => $user_id,
        "timestamp" => date('Y-m-d H:i:s'),
        "active_sessions_count" => count($active_sessions),
        "active_sessions" => $active_sessions,
        "today_sessions_count" => count($today_sessions),
        "today_sessions" => $today_sessions,
        "notes" => [
            "If active_sessions_count > 0, that's why you see the prompt",
            "Check seconds_since_start - if very small (<5), it's the newly created one"
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}
?>
