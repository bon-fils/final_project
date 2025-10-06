<?php
require_once 'config.php';

echo "=== DATABASE STRUCTURE CHECK ===\n\n";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Show all tables
    echo "1. TABLES IN DATABASE:\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        echo "  - $table\n";
    }

    echo "\n2. TABLE STRUCTURES:\n\n";

    // Check key tables
    $key_tables = ['courses', 'students', 'lecturers', 'departments', 'options', 'attendance_sessions', 'attendance_records'];

    foreach ($key_tables as $table) {
        if (in_array($table, $tables)) {
            echo "=== $table TABLE ===\n";
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($columns as $column) {
                echo "  {$column['Field']} ({$column['Type']})";
                if ($column['Key'] === 'PRI') echo " - PRIMARY KEY";
                if ($column['Null'] === 'NO') echo " - NOT NULL";
                if (!empty($column['Default'])) echo " - DEFAULT: {$column['Default']}";
                echo "\n";
            }
            echo "\n";
        } else {
            echo "❌ Table '$table' does not exist\n\n";
        }
    }

    // Check if we have any data
    echo "3. SAMPLE DATA COUNTS:\n";
    foreach ($key_tables as $table) {
        if (in_array($table, $tables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "  - $table: $count records\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}