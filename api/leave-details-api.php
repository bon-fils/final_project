<?php
declare(strict_types=1);
session_start();

require_once "../config.php";
require_once "../session_check.php";
require_role(['student']);

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request ID']);
    exit();
}

$request_id = (int)$_GET['id'];
$user_id = (int)($_SESSION['user_id'] ?? 0);

// Get student ID
try {
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        exit();
    }

    $student_id = $student['id'];

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit();
}

// Get leave request details (ensure it belongs to the current student)
try {
    $stmt = $pdo->prepare("
        SELECT
            id, leave_type, start_date, end_date, reason, status,
            created_at, reviewed_at, reviewer_comments
        FROM leave_requests
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$request_id, $student_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        echo json_encode(['success' => false, 'error' => 'Leave request not found']);
        exit();
    }

    echo json_encode([
        'success' => true,
        'request' => $request
    ]);

} catch (Exception $e) {
    error_log("Leave details API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to load leave details']);
}
?>