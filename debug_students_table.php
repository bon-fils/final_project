<?php
require_once "config.php";

try {
    // Check the actual structure of the students table
    echo "<h2>Students Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE students");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Also check a sample of data
    echo "<h2>Sample Students Data:</h2>";
    $stmt = $pdo->query("SELECT * FROM students LIMIT 3");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($students)) {
        echo "<table border='1'>";
        // Headers
        echo "<tr>";
        foreach (array_keys($students[0]) as $header) {
            echo "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr>";
        
        // Data
        foreach ($students as $student) {
            echo "<tr>";
            foreach ($student as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
