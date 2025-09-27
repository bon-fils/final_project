<?php
require_once "config.php";

try {
    echo "Testing database queries for department statistics:\n\n";

    // Test basic counts
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE department_id = ?");
    $stmt->execute([1]);
    $students = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Students in department 1: " . $students['count'] . "\n";

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM courses WHERE department_id = ?");
    $stmt->execute([1]);
    $courses = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Courses in department 1: " . $courses['count'] . "\n";

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(c.credits), 0) as total FROM courses c WHERE c.department_id = ?");
    $stmt->execute([1]);
    $credits = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total credits in department 1: " . $credits['total'] . "\n";

    // Test attendance sessions
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT s.id) as count FROM attendance_sessions s INNER JOIN courses c ON s.course_id = c.id WHERE c.department_id = ?");
    $stmt->execute([1]);
    $sessions = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Attendance sessions in department 1: " . $sessions['count'] . "\n";

    // Test active courses
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) as count FROM courses c INNER JOIN attendance_sessions s ON c.id = s.course_id WHERE c.department_id = ?");
    $stmt->execute([1]);
    $active_courses = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Active courses in department 1: " . $active_courses['count'] . "\n";

    echo "\nAll department course counts:\n";
    $stmt = $pdo->prepare("SELECT c.department_id, d.name as dept_name, COUNT(*) as course_count FROM courses c LEFT JOIN departments d ON c.department_id = d.id GROUP BY c.department_id, d.name ORDER BY c.department_id");
    $stmt->execute();
    $dept_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($dept_courses as $dept) {
        echo "Department " . $dept['department_id'] . " (" . ($dept['dept_name'] ?: 'Unknown') . "): " . $dept['course_count'] . " courses\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>