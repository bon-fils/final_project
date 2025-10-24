<?php
/**
 * Simple Get Courses API (No Authentication Required)
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
            'courses' => []
        ]);
        exit;
    }
    
    // Get basic course information (only columns that definitely exist)
    $stmt = $pdo->prepare("
        SELECT id, name, course_code, department_id, status
        FROM courses 
        WHERE department_id = ? 
        AND (lecturer_id IS NULL OR lecturer_id = 0)
        AND status = 'active'
        ORDER BY course_code ASC, name ASC
    ");
    $stmt->execute([$department_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get department name for context
    $dept_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $dept_stmt->execute([$department_id]);
    $department_name = $dept_stmt->fetchColumn();
    
    if (empty($courses)) {
        echo json_encode([
            'success' => false,
            'message' => "No unassigned courses found for " . ($department_name ?: "this department") . ".",
            'courses' => [],
            'department_name' => $department_name,
            'department_id' => $department_id
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => "Found " . count($courses) . " unassigned course(s) for " . ($department_name ?: "this department"),
            'courses' => $courses,
            'department_name' => $department_name,
            'department_id' => $department_id,
            'count' => count($courses)
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error in get-courses-simple.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'courses' => []
    ]);
}
?>
