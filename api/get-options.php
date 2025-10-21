<?php
/**
 * Get Academic Options API
 * Returns options for a specific department
 */

require_once "../config.php";
require_once "../session_check.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

$department_id = filter_input(INPUT_GET, "department_id", FILTER_VALIDATE_INT);

if (!$department_id) {
    echo json_encode(["status" => "error", "message" => "Invalid department ID"]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, description 
        FROM options 
        WHERE department_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$department_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "data" => $options,
        "count" => count($options)
    ]);
    
} catch (Exception $e) {
    error_log("Get options error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Failed to fetch options"]);
}
?>