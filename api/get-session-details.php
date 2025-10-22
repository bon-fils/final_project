<?php
/**
 * Get Session Details API
 * Retrieves full session information by session ID
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

// Get session_id from GET parameter
if (!isset($_GET['session_id'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Session ID is required"
    ]);
    exit;
}

$session_id = (int)$_GET['session_id'];
$user_id = $_SESSION['user_id'];

try {
    // Get full session details with all related data
    $session_stmt = $pdo->prepare("
        SELECT 
            ats.id,
            ats.session_date,
            ats.start_time,
            ats.biometric_method,
            ats.year_level,
            ats.status,
            c.name as course_name,
            c.course_code,
            o.name as option_name,
            d.name as department_name,
            CONCAT(u.first_name, ' ', u.last_name) as lecturer_name,
            (SELECT COUNT(*) FROM students 
             WHERE option_id = ats.option_id 
             AND year_level = ats.year_level 
             AND status = 'active') as total_students,
            (SELECT COUNT(*) FROM attendance_records 
             WHERE session_id = ats.id 
             AND status = 'present') as students_present
        FROM attendance_sessions ats
        JOIN courses c ON ats.course_id = c.id
        JOIN options o ON ats.option_id = o.id
        JOIN departments d ON ats.department_id = d.id
        JOIN users u ON ats.lecturer_id = u.id
        WHERE ats.id = ?
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
    
    // Verify the session belongs to this user (security check)
    // We need to check if the lecturer_id in the session matches the current user
    $verify_stmt = $pdo->prepare("
        SELECT lecturer_id FROM attendance_sessions WHERE id = ?
    ");
    $verify_stmt->execute([$session_id]);
    $session_owner = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($session_owner['lecturer_id'] != $user_id) {
        echo json_encode([
            "status" => "error",
            "message" => "Unauthorized: This session does not belong to you"
        ]);
        exit;
    }
    
    error_log("Session details retrieved: ID=$session_id, Course: {$session['course_name']}");
    
    echo json_encode([
        "status" => "success",
        "message" => "Session details retrieved successfully",
        "session" => $session
    ]);
    
} catch (Exception $e) {
    error_log("Get session details error: " . $e->getMessage());
    echo json_encode([
        "status" => "error",
        "message" => "Failed to retrieve session details",
        "debug" => $e->getMessage()
    ]);
}
?>
