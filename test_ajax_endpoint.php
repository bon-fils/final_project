<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

header('Content-Type: application/json');

try {
    // Validate CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf_token)) {
            throw new Exception('Invalid CSRF token');
        }
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_users':
            $search = sanitize_input($_GET['search'] ?? '');
            $role_filter = sanitize_input($_GET['role'] ?? '');
            $status_filter = sanitize_input($_GET['status'] ?? '');

            // Include the functions from manage-users.php
            require_once "manage-users.php";

            $users = getAllUsers($search, $role_filter, $status_filter);
            $stats = getUserStats();

            echo json_encode([
                'status' => 'success',
                'data' => $users,
                'stats' => $stats,
                'timestamp' => time()
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
exit;
?>

<?php
// Test the endpoint
$_GET['action'] = 'get_users';
include __FILE__;
?>