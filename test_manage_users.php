<?php
require_once "config.php";

echo "=== Testing database connection ===\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total users in database: " . $result['count'] . "\n";

    echo "\n=== Testing users query ===\n";
    $sql = "
        SELECT
            u.id,
            u.username,
            u.email,
            u.role,
            u.status,
            u.created_at,
            u.updated_at,
            u.last_login,
            CASE
                WHEN u.role = 'student' THEN s.first_name
                WHEN u.role = 'lecturer' THEN l.first_name
                WHEN u.role = 'hod' THEN l.first_name
                ELSE 'System'
            END as first_name,
            CASE
                WHEN u.role = 'student' THEN s.last_name
                WHEN u.role = 'lecturer' THEN l.last_name
                WHEN u.role = 'hod' THEN l.last_name
                ELSE 'User'
            END as last_name
        FROM users u
        LEFT JOIN students s ON u.email = s.email AND u.role = 'student'
        LEFT JOIN lecturers l ON u.email = l.email AND u.role IN ('lecturer', 'hod')
        ORDER BY u.created_at DESC
        LIMIT 5
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($users) . " users\n";
    foreach ($users as $user) {
        echo "User: {$user['username']} ({$user['role']}) - {$user['first_name']} {$user['last_name']}\n";
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>