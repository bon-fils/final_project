<?php
require_once 'config.php';

echo "=== USERS TABLE (LECTURERS) ===\n\n";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->query("SELECT id, username, email, role FROM users WHERE role = 'lecturer' ORDER BY id");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        echo "ID {$row['id']}: {$row['username']} ({$row['email']}) - Role: {$row['role']}\n";
    }

    echo "\n=== LECTURER-USER RELATIONSHIPS ===\n";
    $stmt = $pdo->query("
        SELECT u.id as user_id, u.username, u.email, l.id as lecturer_id, l.first_name, l.last_name, l.department_id, d.name as department_name
        FROM users u
        INNER JOIN lecturers l ON u.email = l.email
        LEFT JOIN departments d ON l.department_id = d.id
        WHERE u.role = 'lecturer'
        ORDER BY u.id
    ");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dept_name = $row['department_name'] ?? 'Not Assigned';
        echo "User ID {$row['user_id']}: {$row['username']} ({$row['email']}) -> Lecturer ID {$row['lecturer_id']}: {$row['first_name']} {$row['last_name']} - Department: {$dept_name}\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}