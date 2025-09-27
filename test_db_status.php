<?php
/**
 * Simple Database Status Test
 */

require_once "config.php";

echo "<h1>Database Status Test</h1>";

try {
    echo "<p>Testing database connection...</p>";

    // Test database connection
    $result = $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✅ Database connection successful!</p>";

    echo "<p>Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "</p>";

    echo "<h2>Users with HoD role:</h2>";
    $stmt = $pdo->query("SELECT id, username, email, role FROM users WHERE role = 'hod'");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($users) > 0) {
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li>ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: orange;'>⚠️ No users with HoD role found</p>";
    }

    echo "<h2>Departments and their HoD assignments:</h2>";
    $stmt = $pdo->query("SELECT d.id, d.name, d.hod_id, l.first_name, l.last_name, l.email as lecturer_email
                         FROM departments d
                         LEFT JOIN lecturers l ON d.hod_id = l.id");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($departments) > 0) {
        echo "<ul>";
        foreach ($departments as $dept) {
            echo "<li><strong>{$dept['name']}</strong> (ID: {$dept['id']})";
            if ($dept['hod_id']) {
                echo " - HoD: {$dept['first_name']} {$dept['last_name']} ({$dept['lecturer_email']})";
            } else {
                echo " - <span style='color: red;'>No HoD assigned</span>";
            }
            echo "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ No departments found</p>";
    }

    echo "<h2>Courses in the system:</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM courses");
    $course_count = $stmt->fetch()['count'];
    echo "<p>Total courses: $course_count</p>";

    if ($course_count > 0) {
        $stmt = $pdo->query("SELECT id, course_code, name, department_id, lecturer_id FROM courses LIMIT 5");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<ul>";
        foreach ($courses as $course) {
            echo "<li>{$course['course_code']}: {$course['name']} (Dept: {$course['department_id']}, Lecturer: " . ($course['lecturer_id'] ?: 'Unassigned') . ")</li>";
        }
        echo "</ul>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
}
?>