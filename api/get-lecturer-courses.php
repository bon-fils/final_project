<?php
/**
 * Get Lecturer Courses API
 * Returns all courses taught by the logged-in lecturer
 */

session_start();
require_once "../config.php";
require_once "../session_check.php";
require_role(['lecturer', 'hod', 'admin']);

header('Content-Type: application/json');

try {
    $lecturer_id = $_SESSION['lecturer_id'] ?? null;
    
    if (!$lecturer_id) {
        throw new Exception('Lecturer ID not found in session');
    }
    
    // Get all courses taught by this lecturer
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.course_name,
            c.course_code,
            c.year,
            c.credits,
            o.id as option_id,
            o.name as option_name,
            d.id as department_id,
            d.name as department_name,
            COUNT(DISTINCT s.id) as student_count,
            COUNT(DISTINCT ats.id) as session_count
        FROM courses c
        LEFT JOIN options o ON c.option_id = o.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN students s ON s.option_id = c.option_id AND s.year_level = c.year AND s.status = 'active'
        LEFT JOIN attendance_sessions ats ON ats.course_id = c.id
        WHERE c.lecturer_id = ? AND c.status = 'active'
        GROUP BY c.id
        ORDER BY c.course_name
    ");
    
    $stmt->execute([$lecturer_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'data' => $courses
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
