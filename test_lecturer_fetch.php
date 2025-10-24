<?php
/**
 * Test script to verify lecturer fetching logic
 */

require_once "config.php";

try {
    echo "<h2>Testing Lecturer Fetch Logic</h2>";
    
    // Test for IT Year 3 (option_id = 17, year = 3)
    $test_option_id = 17;
    $test_year = 3;
    
    echo "<h3>Testing for Option ID: $test_option_id, Year: $test_year</h3>";
    
    // Use the same query as in request-leave.php
    $stmt = $pdo->prepare("
        SELECT DISTINCT l.id, u.first_name, u.last_name,
               CONCAT(u.first_name, ' ', u.last_name) as full_name,
               COUNT(c.id) as course_count,
               GROUP_CONCAT(CONCAT(c.course_code, ' - ', c.course_name) SEPARATOR '<br>') as courses
        FROM lecturers l
        JOIN users u ON l.user_id = u.id
        JOIN courses c ON l.id = c.lecturer_id
        WHERE c.option_id = ? AND c.year = ? AND c.status = 'active' AND c.lecturer_id IS NOT NULL
        GROUP BY l.id, u.first_name, u.last_name
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$test_option_id, $test_year]);
    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Found " . count($lecturers) . " lecturers:</strong></p>";
    
    if (empty($lecturers)) {
        echo "<p style='color: red;'>❌ No lecturers found! This means students won't be able to select any lecturers.</p>";
        
        // Debug: Check what courses exist for this option and year
        echo "<h4>Debug: Checking courses for Option ID $test_option_id, Year $test_year:</h4>";
        $stmt = $pdo->prepare("
            SELECT c.id, c.course_code, c.course_name, c.lecturer_id,
                   CASE WHEN c.lecturer_id IS NULL THEN 'No Lecturer' 
                        ELSE CONCAT('Lecturer ID: ', c.lecturer_id) END as lecturer_status
            FROM courses c
            WHERE c.option_id = ? AND c.year = ? AND c.status = 'active'
            ORDER BY c.course_code
        ");
        $stmt->execute([$test_option_id, $test_year]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Course Code</th><th>Course Name</th><th>Lecturer Status</th></tr>";
        foreach ($courses as $course) {
            $color = $course['lecturer_id'] ? '#e8f5e8' : '#ffe8e8';
            echo "<tr style='background-color: $color;'>";
            echo "<td>" . $course['course_code'] . "</td>";
            echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
            echo "<td>" . $course['lecturer_status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Lecturer ID</th><th>Name</th><th>Course Count</th><th>Courses</th></tr>";
        foreach ($lecturers as $lecturer) {
            echo "<tr>";
            echo "<td>" . $lecturer['id'] . "</td>";
            echo "<td>" . htmlspecialchars($lecturer['full_name']) . "</td>";
            echo "<td>" . $lecturer['course_count'] . "</td>";
            echo "<td>" . $lecturer['courses'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<p style='color: green;'>✅ Great! Students should be able to select these lecturers for leave requests.</p>";
    }
    
    // Test for other years/options
    echo "<h3>Testing Other Year/Option Combinations:</h3>";
    $test_combinations = [
        ['option_id' => 17, 'year' => 1],
        ['option_id' => 17, 'year' => 2],
        ['option_id' => 17, 'year' => 4],
        ['option_id' => 1, 'year' => 1],  // CS
    ];
    
    foreach ($test_combinations as $combo) {
        $stmt->execute([$combo['option_id'], $combo['year']]);
        $count = $stmt->rowCount();
        $color = $count > 0 ? 'green' : 'red';
        $status = $count > 0 ? '✅' : '❌';
        echo "<p style='color: $color;'>$status Option ID {$combo['option_id']}, Year {$combo['year']}: $count lecturers</p>";
    }
    
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
</style>
