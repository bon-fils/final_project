<?php
require_once "config.php";

try {
    // Check current table structure
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Current leave_requests table structure:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "<td>" . $column['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if lecturer_id column exists
    $has_lecturer_id = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'lecturer_id') {
            $has_lecturer_id = true;
            break;
        }
    }
    
    echo "<h3>Lecturer ID column exists: " . ($has_lecturer_id ? "YES" : "NO") . "</h3>";
    
    if (!$has_lecturer_id) {
        echo "<p>Need to add lecturer_id column to the table.</p>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
