<?php
/**
 * Get Courses API
 * Returns courses for a specific option and department
 */

require_once "../config.php";
require_once "../session_check.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

$option_id = filter_input(INPUT_GET, "option_id", FILTER_VALIDATE_INT);
$department_id = filter_input(INPUT_GET, "department_id", FILTER_VALIDATE_INT);

if (!$option_id && !$department_id) {
    echo json_encode(["status" => "error", "message" => "Option ID or Department ID required"]);
    exit;
}

try {
    $sql = "SELECT id, name, code, credits FROM courses WHERE 1=1";
    $params = [];
    
    if ($department_id) {
        $sql .= " AND department_id = ?";
        $params[] = $department_id;
    }
    
    if ($option_id) {
        $sql .= " AND option_id = ?";
        $params[] = $option_id;
    }
    
    $sql .= " ORDER BY name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "data" => $courses,
        "count" => count($courses)
    ]);
    
} catch (Exception $e) {
    error_log("Get courses error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Failed to fetch courses"]);
}
?>