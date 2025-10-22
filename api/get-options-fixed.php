<?php
/**
 * Get Academic Options API - Fixed Version
 * Returns options for a specific department with robust error handling
 */

require_once "../config.php";
session_start();

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Enhanced authentication check
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error", 
        "message" => "Authentication required",
        "debug" => "No user session found"
    ]);
    exit;
}

$department_id = filter_input(INPUT_GET, "department_id", FILTER_VALIDATE_INT);

if (!$department_id || $department_id <= 0) {
    echo json_encode([
        "status" => "error", 
        "message" => "Invalid department ID",
        "debug" => "Department ID: " . ($_GET['department_id'] ?? 'not provided')
    ]);
    exit;
}

try {
    // First check if department exists
    $dept_check = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $dept_check->execute([$department_id]);
    $department = $dept_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        echo json_encode([
            "status" => "error",
            "message" => "Department not found",
            "debug" => "Department ID $department_id does not exist"
        ]);
        exit;
    }
    
    // Check what columns exist in options table
    $columns_check = $pdo->query("DESCRIBE options");
    $columns = $columns_check->fetchAll(PDO::FETCH_COLUMN);
    
    // Build query based on available columns
    $select_fields = ["id", "name"];
    
    if (in_array('description', $columns)) {
        $select_fields[] = "description";
    } else {
        $select_fields[] = "'' as description";
    }
    
    if (in_array('created_at', $columns)) {
        $select_fields[] = "created_at";
    } else {
        $select_fields[] = "NOW() as created_at";
    }
    
    if (in_array('status', $columns)) {
        $select_fields[] = "status";
        $where_clause = "WHERE department_id = ? AND (status IS NULL OR status = 'active')";
    } else {
        $select_fields[] = "'active' as status";
        $where_clause = "WHERE department_id = ?";
    }
    
    $query = "SELECT " . implode(", ", $select_fields) . " FROM options " . $where_clause . " ORDER BY name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$department_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Success response
    echo json_encode([
        "status" => "success",
        "data" => $options,
        "count" => count($options),
        "department_name" => $department['name'],
        "department_id" => $department_id,
        "debug" => [
            "query_executed" => true,
            "query_used" => $query,
            "available_columns" => $columns,
            "user_id" => $_SESSION["user_id"],
            "timestamp" => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Get options database error: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Database error occurred",
        "debug" => [
            "error_message" => $e->getMessage(),
            "error_code" => $e->getCode(),
            "department_id" => $department_id
        ]
    ]);
} catch (Exception $e) {
    error_log("Get options general error: " . $e->getMessage());
    echo json_encode([
        "status" => "error", 
        "message" => "Failed to fetch options",
        "debug" => $e->getMessage()
    ]);
}
?>
