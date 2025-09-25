<?php
/**
 * Test Manage Users Backend - No Session Required
 */

header('Content-Type: application/json');

try {
    require_once "config.php";

    // Test database connection
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    // Test getAllUsers function
    $users = [];
    $stmt = $pdo->prepare("
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
    ");

    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Test getUserStats function
    $stmt = $pdo->prepare("
        SELECT
            role,
            status,
            COUNT(*) as count
        FROM users
        GROUP BY role, status
    ");

    $stmt->execute();
    $roleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = array_sum(array_column($roleStats, 'count'));
    $active = array_sum(array_column(array_filter($roleStats, fn($s) => $s['status'] === 'active'), 'count'));
    $inactive = array_sum(array_column(array_filter($roleStats, fn($s) => $s['status'] === 'inactive'), 'count'));
    $suspended = array_sum(array_column(array_filter($roleStats, fn($s) => $s['status'] === 'suspended'), 'count'));

    $stats = [
        'total' => $total,
        'active' => $active,
        'inactive' => $inactive,
        'suspended' => $suspended
    ];

    echo json_encode([
        'status' => 'success',
        'message' => 'Backend test successful',
        'data' => [
            'users' => $users,
            'stats' => $stats,
            'user_count' => count($users),
            'database_connected' => true,
            'timestamp' => time()
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
}
?>