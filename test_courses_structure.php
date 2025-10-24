<?php
require_once "config.php";

try {
    echo "<h2>Courses Table Structure Analysis</h2>";
    
    // Check courses table structure
    $stmt = $pdo->query("DESCRIBE courses");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Current courses table structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . ($column['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test the problematic query
    echo "<h3>Testing the get-courses.php query for department 7:</h3>";
    
    try {
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
                c.created_at,
                o.name as option_name
            FROM courses c
            LEFT JOIN options o ON c.option_id = o.id
            WHERE c.department_id = ?
            AND (c.lecturer_id IS NULL OR c.lecturer_id = 0)
            AND c.status = 'active'
            ORDER BY c.course_code ASC, COALESCE(c.course_name, c.name) ASC
        ");
        $stmt->execute([7]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<p style='color: green;'>✅ Query executed successfully!</p>";
        echo "<p><strong>Found " . count($courses) . " courses for department 7</strong></p>";
        
        if (!empty($courses)) {
            echo "<h4>Sample courses:</h4>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Course Name</th><th>Course Code</th><th>Status</th><th>Option</th></tr>";
            foreach (array_slice($courses, 0, 5) as $course) {
                echo "<tr>";
                echo "<td>" . $course['id'] . "</td>";
                echo "<td>" . htmlspecialchars($course['course_name']) . "</td>";
                echo "<td>" . htmlspecialchars($course['course_code'] ?? 'N/A') . "</td>";
                echo "<td>" . htmlspecialchars($course['status']) . "</td>";
                echo "<td>" . htmlspecialchars($course['option_name'] ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Query failed: " . $e->getMessage() . "</p>";
        
        // Try a simpler query to identify the issue
        echo "<h4>Testing simpler query:</h4>";
        try {
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE department_id = ? LIMIT 3");
            $stmt->execute([7]);
            $simple_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<p style='color: green;'>✅ Simple query works! Found " . count($simple_courses) . " courses</p>";
            
            if (!empty($simple_courses)) {
                echo "<h5>Available columns in first course:</h5>";
                echo "<ul>";
                foreach (array_keys($simple_courses[0]) as $column) {
                    echo "<li>" . htmlspecialchars($column) . "</li>";
                }
                echo "</ul>";
            }
            
        } catch (Exception $e2) {
            echo "<p style='color: red;'>❌ Even simple query failed: " . $e2->getMessage() . "</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>
