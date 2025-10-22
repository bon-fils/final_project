<?php
/**
 * Options Loading Endpoint
 * Returns available options for a specific department
 */

require_once "config.php";
require_once "session_check.php";

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if user is logged in and has appropriate role
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hod'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized access',
            'error_code' => 'UNAUTHORIZED'
        ]);
        exit;
    }

    // Get and validate department ID
    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);

    if (!$department_id || $department_id <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Valid department ID is required',
            'error_code' => 'INVALID_DEPARTMENT_ID'
        ]);
        exit;
    }

    // Verify department exists
    $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Department not found',
            'error_code' => 'DEPARTMENT_NOT_FOUND'
        ]);
        exit;
    }

    // Get options for the department
    $stmt = $pdo->prepare("
        SELECT
            id,
            name,
            department_id,
            description,
            status,
            created_at
        FROM options
        WHERE department_id = ?
        AND status = 'active'
        ORDER BY name ASC
    ");
    $stmt->execute([$department_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return response with additional metadata
    echo json_encode([
        'success' => true,
        'options' => $options,
        'count' => count($options),
        'department' => $department,
        'message' => count($options) > 0
            ? 'Options loaded successfully'
            : 'No active options found for this department',
        'timestamp' => date('c'),
        'request_id' => uniqid('opt_', true)
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-options.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error_code' => 'DATABASE_ERROR'
    ]);

} catch (Exception $e) {
    error_log("Error in get-options.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading options',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>
