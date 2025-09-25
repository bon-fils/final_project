<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rp_attendance_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== DEPARTMENTS ===\n";
    $stmt = $pdo->query('SELECT * FROM departments');
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($departments as $dept) {
        echo "ID: {$dept['id']}, Name: {$dept['name']}, HOD ID: {$dept['hod_id']}\n";
    }

    echo "\n=== USERS WITH LECTURER/HOD ROLES ===\n";
    $stmt = $pdo->query('SELECT id, username, email, role FROM users WHERE role IN ("lecturer", "hod")');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($users as $user) {
        echo "ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Role: {$user['role']}\n";
    }

    echo "\n=== TOTAL STATISTICS ===\n";
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM departments');
    echo "Total Departments: " . $stmt->fetchColumn() . "\n";

    $stmt = $pdo->query('SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL');
    echo "Assigned Departments: " . $stmt->fetchColumn() . "\n";

    $stmt = $pdo->query('SELECT COUNT(*) as lecturers FROM users WHERE role IN ("lecturer", "hod")');
    echo "Total Lecturers/HODs: " . $stmt->fetchColumn() . "\n";

} catch (Exception $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
?>