<?php
/**
 * Check lecturer-course relationships and identify issues
 */

require_once "config.php";

try {
    echo "<h2>Lecturer-Course Relationship Analysis</h2>";
    
    // Get all lecturers
    $stmt = $pdo->query("
        SELECT l.id, l.user_id, u.first_name, u.last_name, 
               CONCAT(u.first_name, ' ', u.last_name) as full_name,
               d.name as department_name
        FROM lecturers l
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN departments d ON l.department_id = d.id
        ORDER BY l.id
    ");
    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Lecturers:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Department</th><th>Assigned Courses</th></tr>";
    
    foreach ($lecturers as $lecturer) {
        // Get courses for this lecturer
        $stmt = $pdo->prepare("
            SELECT c.id, c.course_code, c.course_name, c.year, o.name as option_name
            FROM courses c
            LEFT JOIN options o ON c.option_id = o.id
            WHERE c.lecturer_id = ?
            ORDER BY c.year, c.course_code
        ");
        $stmt->execute([$lecturer['id']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<tr>";
        echo "<td>" . $lecturer['id'] . "</td>";
        echo "<td>" . htmlspecialchars($lecturer['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($lecturer['department_name'] ?? 'N/A') . "</td>";
        echo "<td>";
        if (empty($courses)) {
            echo "<em style='color: orange;'>No courses assigned</em>";
        } else {
            foreach ($courses as $course) {
                echo "<div style='margin: 2px 0; padding: 2px; background: #f0f0f0; border-radius: 3px;'>";
                echo "<strong>" . $course['course_code'] . "</strong> - " . $course['course_name'];
                echo " (Year " . $course['year'] . ", " . ($course['option_name'] ?? 'No Option') . ")";
                echo "</div>";
            }
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Get courses without lecturers
    echo "<h3>Courses Without Assigned Lecturers:</h3>";
    $stmt = $pdo->query("
        SELECT c.id, c.course_code, c.course_name, c.year, 
               o.name as option_name, d.name as department_name
        FROM courses c
        LEFT JOIN options o ON c.option_id = o.id
        LEFT JOIN departments d ON c.department_id = d.id
        WHERE c.lecturer_id IS NULL
        ORDER BY d.name, c.year, c.course_code
    ");
    $unassigned_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($unassigned_courses)) {
        echo "<p style='color: green;'>✅ All courses have assigned lecturers!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr><th>Course Code</th><th>Course Name</th><th>Year</th><th>Option</th><th>Department</th></tr>";
        foreach ($unassigned_courses as $course) {
            echo "<tr>";
            echo "<td>" . $course['course_code'] . "</td>";
            echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
            echo "<td>" . $course['year'] . "</td>";
            echo "<td>" . htmlspecialchars($course['option_name'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($course['department_name'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p style='color: orange;'>⚠️ " . count($unassigned_courses) . " courses need lecturer assignment</p>";
    }
    
    // Check student-lecturer relationships for leave requests
    echo "<h3>Student-Lecturer Relationships for Leave Requests:</h3>";
    $stmt = $pdo->query("
        SELECT DISTINCT 
            s.id as student_id, s.reg_no,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            s.year_level, o.name as option_name,
            GROUP_CONCAT(DISTINCT CONCAT(l.id, ':', CONCAT(lu.first_name, ' ', lu.last_name)) SEPARATOR '; ') as available_lecturers
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN options o ON s.option_id = o.id
        LEFT JOIN courses c ON (c.option_id = s.option_id AND c.year = s.year_level)
        LEFT JOIN lecturers l ON c.lecturer_id = l.id
        LEFT JOIN users lu ON l.user_id = lu.id
        WHERE s.status = 'active' AND c.status = 'active'
        GROUP BY s.id, s.reg_no, student_name, s.year_level, o.name
        LIMIT 10
    ");
    $student_lecturer_relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr><th>Student</th><th>Reg No</th><th>Year/Option</th><th>Available Lecturers</th></tr>";
    foreach ($student_lecturer_relationships as $relationship) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($relationship['student_name']) . "</td>";
        echo "<td>" . htmlspecialchars($relationship['reg_no']) . "</td>";
        echo "<td>Year " . $relationship['year_level'] . " - " . htmlspecialchars($relationship['option_name'] ?? 'N/A') . "</td>";
        echo "<td>";
        if (empty($relationship['available_lecturers'])) {
            echo "<em style='color: red;'>No lecturers available</em>";
        } else {
            $lecturers_list = explode('; ', $relationship['available_lecturers']);
            foreach ($lecturers_list as $lecturer_info) {
                if (!empty($lecturer_info)) {
                    list($lecturer_id, $lecturer_name) = explode(':', $lecturer_info, 2);
                    echo "<div style='margin: 2px 0; padding: 2px; background: #e8f5e8; border-radius: 3px;'>";
                    echo "ID: " . $lecturer_id . " - " . htmlspecialchars($lecturer_name);
                    echo "</div>";
                }
            }
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
}

echo "<br><a href='request-leave.php'>← Back to Request Leave</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
th { background-color: #f2f2f2; font-weight: bold; }
tr:nth-child(even) { background-color: #f9f9f9; }
</style>
