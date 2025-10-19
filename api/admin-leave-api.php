<?php
/**
 * Admin Leave Management API
 * Provides leave management functionality for administrators
 */

require_once "../config.php";
require_once "../session_check.php";
require_role(['admin']);

// Handle AJAX requests
if (isset($_GET['ajax']) || isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        $action = $_GET['action'] ?? $_POST['action'] ?? '';

        switch ($action) {
            case 'get_all_leaves':
                getAllLeaves();
                break;

            default:
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid action'
                ]);
                break;
        }
    } catch (Exception $e) {
        error_log("Admin Leave API Error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'An error occurred while processing your request'
        ]);
    }
    exit;
}

function getAllLeaves() {
    global $pdo;

    try {
        // Get all leave requests with student and department information
        $stmt = $pdo->prepare("
            SELECT
                lr.id,
                lr.student_id,
                lr.reason,
                lr.supporting_file,
                lr.status,
                lr.from_date,
                lr.to_date,
                lr.requested_at,
                lr.reviewed_at,
                s.first_name,
                s.last_name,
                s.year_level,
                s.department_id,
                d.name as department_name
            FROM leave_requests lr
            INNER JOIN students s ON lr.student_id = s.id
            INNER JOIN departments d ON s.department_id = d.id
            ORDER BY lr.requested_at DESC
        ");

        $stmt->execute();
        $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get statistics
        $statsStmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM leave_requests
        ");

        $statsStmt->execute();
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

        // Get departments list
        $deptStmt = $pdo->prepare("SELECT id, name FROM departments ORDER BY name");
        $deptStmt->execute();
        $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'leaves' => $leaves,
                'stats' => $stats,
                'departments' => $departments
            ]
        ]);

    } catch (PDOException $e) {
        error_log("Database error in getAllLeaves: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve leave data'
        ]);
    }
}
?>