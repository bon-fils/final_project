<?php
require_once 'config.php';

echo "<h1>Database Test</h1>";

try {
    // Test connection
    $pdo->query("SELECT 1");
    echo "<p style='color: green;'>✓ Database connection successful</p>";

    // Test users table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p>Users: " . $result['count'] . "</p>";

    // Test courses table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM courses");
    $result = $stmt->fetch();
    echo "<p>Courses: " . $result['count'] . "</p>";

    // Test departments table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
    $result = $stmt->fetch();
    echo "<p>Departments: " . $result['count'] . "</p>";

    // Test lecturers table
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM lecturers");
    $result = $stmt->fetch();
    echo "<p>Lecturers: " . $result['count'] . "</p>";

    // Show sample courses
    echo "<h2>Sample Courses:</h2>";
    $stmt = $pdo->query("SELECT id, name, course_code, department_id FROM courses LIMIT 5");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($courses as $course) {
        echo "<p>{$course['id']}: {$course['name']} ({$course['course_code']}) - Dept: {$course['department_id']}</p>";
    }

    // Show sample departments
    echo "<h2>Sample Departments:</h2>";
    $stmt = $pdo->query("SELECT id, name FROM departments LIMIT 5");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($departments as $dept) {
        echo "<p>{$dept['id']}: {$dept['name']}</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>