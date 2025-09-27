<?php
require_once 'config.php';

echo "=== LECTURER DEPARTMENT ASSIGNMENTS ===\n\n";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT l.id, l.first_name, l.last_name, l.email, l.department_id, d.name as department_name
        FROM lecturers l
        LEFT JOIN departments d ON l.department_id = d.id
        ORDER BY l.id
    ");
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dept_name = $row['department_name'] ?? 'Not Assigned';
        echo "ID {$row['id']}: {$row['first_name']} {$row['last_name']} ({$row['email']}) - Department: {$dept_name}\n";
    }

    echo "\n=== COURSES IN CREATIVE ARTS DEPARTMENT ===\n";
    $stmt = $pdo->prepare("SELECT course_code, name FROM courses WHERE department_id = 4");
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  - {$row['course_code']}: {$row['name']}\n";
    }

    echo "\n=== OPTIONS IN CREATIVE ARTS DEPARTMENT ===\n";
    $stmt = $pdo->prepare("SELECT name FROM options WHERE department_id = 4");
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "  - {$row['name']}\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}