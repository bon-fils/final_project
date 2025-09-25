<?php
require 'config.php';

echo "=== Database Connection Test ===\n";
try {
    echo "✓ Database connected successfully\n";

    echo "\n=== Checking Tables ===\n";
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Available tables: " . implode(', ', $tables) . "\n";

    echo "\n=== Checking system_logs table ===\n";
    if (in_array('system_logs', $tables)) {
        echo "✓ system_logs table exists\n";

        // Test the query used in statistics
        $stmt = $pdo->query("SELECT COUNT(*) as recent_changes FROM system_logs WHERE action LIKE '%department%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $result = $stmt->fetch();
        echo "Recent department changes: " . $result['recent_changes'] . "\n";
    } else {
        echo "✗ system_logs table missing\n";
    }

    echo "\n=== Testing Statistics Query ===\n";
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
        $result = $stmt->fetch();
        echo "Total departments: " . $result['total'] . "\n";

        $stmt = $pdo->query("SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL");
        $result = $stmt->fetch();
        echo "Assigned HoDs: " . $result['assigned'] . "\n";

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM options");
        $result = $stmt->fetch();
        echo "Total programs: " . $result['total'] . "\n";

        echo "✓ All statistics queries working\n";
    } catch (Exception $e) {
        echo "✗ Statistics query error: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
?>