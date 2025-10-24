<?php
/**
 * Simple Get Options API (No Authentication Required)
 * For testing purposes only
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once "config.php";

try {
    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);
    
    if (!$department_id) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Valid department ID is required',
            'options' => []
        ]);
        exit;
    }
    
    // Get options for the specified department
    $stmt = $pdo->prepare("
        SELECT id, name, status
        FROM options 
        WHERE department_id = ? AND status = 'active'
        ORDER BY name ASC
    ");
    $stmt->execute([$department_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get department name for context
    $dept_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $dept_stmt->execute([$department_id]);
    $department_name = $dept_stmt->fetchColumn();
    
    if (empty($options)) {
        echo json_encode([
            'success' => false,
            'message' => "No active options found for " . ($department_name ?: "this department") . ".",
            'options' => [],
            'department_name' => $department_name,
            'department_id' => $department_id
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Found " . count($options) . " option(s) for " . ($department_name ?: "this department"),
            'options' => $options,
            'department_name' => $department_name,
            'department_id' => $department_id,
            'count' => count($options)
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get-options-simple.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'options' => []
    ]);
}
?>
