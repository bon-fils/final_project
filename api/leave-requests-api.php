<?php
/**
 * Leave Requests API
 * Handles AJAX requests for leave management
 */

session_start();
require_once "../config.php";
require_once "../session_check.php";
require_role(['admin', 'lecturer', 'hod']);

header('Content-Type: application/json');

// Get user info
$user_id = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'student';
$is_admin = in_array($userRole, ['admin', 'hod', 'lecturer']);

$method = $_SERVER['REQUEST_METHOD'];
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    switch ($method) {
        case 'GET':
            // Get leave requests
            $response = getLeaveRequests($pdo, $is_admin, $user_id);
            break;

        case 'POST':
            $action = $_POST['action'] ?? '';

            switch ($action) {
                case 'update_status':
                    $response = updateLeaveStatus($pdo, $_POST, $user_id);
                    break;
                default:
                    $response = ['success' => false, 'message' => 'Invalid action'];
            }
            break;

        default:
            $response = ['success' => false, 'message' => 'Method not allowed'];
    }
} catch (Exception $e) {
    error_log("Leave API Error: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'Server error occurred'];
}

echo json_encode($response);

/**
 * Get leave requests based on user role
 */
function getLeaveRequests($pdo, $is_admin, $user_id) {
    try {
        if ($is_admin) {
            // Admin/lecturer can see all leave requests
            $stmt = $pdo->prepare("
                SELECT lr.id, lr.student_id, lr.reason, lr.status, lr.requested_at,
                       lr.reviewed_by, lr.reviewed_at, lr.supporting_file,
                       s.first_name, s.last_name, s.reg_no,
                       d.name as department_name
                FROM leave_requests lr
                INNER JOIN students s ON lr.student_id = s.id
                LEFT JOIN departments d ON s.department_id = d.id
                ORDER BY lr.requested_at DESC
            ");
            $stmt->execute();
        } else {
            // Students can only see their own requests
            $stmt = $pdo->prepare("
                SELECT lr.id, lr.student_id, lr.reason, lr.status, lr.requested_at,
                       lr.reviewed_by, lr.reviewed_at, lr.supporting_file,
                       s.first_name, s.last_name, s.reg_no,
                       d.name as department_name
                FROM leave_requests lr
                INNER JOIN students s ON lr.student_id = s.id
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE lr.student_id = (SELECT id FROM students WHERE user_id = :user_id)
                ORDER BY lr.requested_at DESC
            ");
            $stmt->execute(['user_id' => $user_id]);
        }

        $requests = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $requests[] = [
                'id' => $row['id'],
                'student_name' => $row['first_name'] . ' ' . $row['last_name'],
                'student_id' => $row['student_id'],
                'reg_no' => $row['reg_no'],
                'department_name' => $row['department_name'],
                'reason' => $row['reason'],
                'status' => $row['status'],
                'requested_at' => $row['requested_at'],
                'reviewed_at' => $row['reviewed_at'],
                'supporting_file' => $row['supporting_file']
            ];
        }

        return [
            'success' => true,
            'requests' => $requests,
            'total' => count($requests)
        ];

    } catch (PDOException $e) {
        error_log("Error fetching leave requests: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to load leave requests'];
    }
}

/**
 * Update leave request status
 */
function updateLeaveStatus($pdo, $data, $user_id) {
    try {
        $request_id = $data['request_id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$request_id || !in_array($status, ['approved', 'rejected'])) {
            return ['success' => false, 'message' => 'Invalid request data'];
        }

        // Check if user has permission to update
        $is_admin = in_array($_SESSION['role'] ?? '', ['admin', 'hod', 'lecturer']);
        if (!$is_admin) {
            return ['success' => false, 'message' => 'Unauthorized'];
        }

        // Update the leave request
        $stmt = $pdo->prepare("
            UPDATE leave_requests
            SET status = :status,
                reviewed_by = :reviewed_by,
                reviewed_at = NOW()
            WHERE id = :request_id
        ");

        $result = $stmt->execute([
            'status' => $status,
            'reviewed_by' => $user_id,
            'request_id' => $request_id
        ]);

        if ($result) {
            return [
                'success' => true,
                'message' => "Leave request {$status} successfully"
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to update leave request'];
        }

    } catch (PDOException $e) {
        error_log("Error updating leave status: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}
?>