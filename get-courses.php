<?php
/**
 * Course Loading Endpoint
 * Returns unassigned courses for a specific department
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

    // Get unassigned courses for the department
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            COALESCE(c.course_name, c.name) as course_name,
            c.name,
            c.course_code,
            c.department_id,
            c.credits,
            c.duration_hours,
            c.status,
            c.description,
            c.year,
            c.option_id,
            o.name as option_name
        FROM courses c
        LEFT JOIN options o ON c.option_id = o.id
        WHERE c.department_id = ?
        AND (c.lecturer_id IS NULL OR c.lecturer_id = 0)
        AND c.status = 'active'
        ORDER BY c.course_code ASC, COALESCE(c.course_name, c.name) ASC
    ");
    $stmt->execute([$department_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return response with additional metadata
    echo json_encode([
        'success' => true,
        'courses' => $courses,
        'count' => count($courses),
        'department' => $department,
        'message' => count($courses) > 0
            ? 'Courses loaded successfully'
            : 'No unassigned courses found for this department',
        'timestamp' => date('c'),
        'request_id' => uniqid('courses_', true)
    ]);

} catch (PDOException $e) {
    error_log("Database error in get-courses.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error_code' => 'DATABASE_ERROR'
    ]);

} catch (Exception $e) {
    error_log("Error in get-courses.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading courses',
        'error_code' => 'INTERNAL_ERROR'
    ]);
}
?>
