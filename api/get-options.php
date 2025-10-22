<?php
/**
 * Get Academic Options API - Simplified Version
 * Returns options for a specific department
 */

require_once "../config.php";
session_start();

header("Content-Type: application/json");

// Basic authentication check
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

$department_id = filter_input(INPUT_GET, "department_id", FILTER_VALIDATE_INT);

if (!$department_id || $department_id <= 0) {
    echo json_encode(["status" => "error", "message" => "Invalid department ID"]);
    exit;
}

try {
    // Simple query - just get what we need
    $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY name");
    $stmt->execute([$department_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add description field if missing (for compatibility)
    foreach ($options as &$option) {
        if (!isset($option['description'])) {
            $option['description'] = '';
        }
    }
    
    echo json_encode([
        "status" => "success",
        "data" => $options,
        "count" => count($options)
    ]);
    
} catch (Exception $e) {
    error_log("Get options error: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to fetch options",
        "error_details" => $e->getMessage()
    ]);
}
?>