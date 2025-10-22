<?php
require_once 'config.php';

echo "Checking attendance_records table structure:\n\n";

try {
    $stmt = $pdo->query('DESCRIBE attendance_records');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Columns in attendance_records table:\n";
    echo str_repeat("-", 80) . "\n";
    printf("%-25s %-20s %-10s %-10s\n", "Field", "Type", "Null", "Key");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($columns as $col) {
        printf("%-25s %-20s %-10s %-10s\n", 
            $col['Field'], 
            $col['Type'], 
            $col['Null'], 
            $col['Key']
        );
    }
    
    echo "\n\nSample INSERT that should work:\n";
    echo "INSERT INTO attendance_records (";
    echo implode(', ', array_column($columns, 'Field'));
    echo ") VALUES (...);\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
