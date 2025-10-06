<?php
require_once "config.php";

echo "<h1>Database Debug</h1>";

try {
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h2>Tables:</h2><ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";

    // Check lecturers table structure
    echo "<h2>Lecturers table structure:</h2>";
    $stmt = $pdo->query("DESCRIBE lecturers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Key']}</td><td>{$col['Default']}</td><td>{$col['Extra']}</td></tr>";
    }
    echo "</table>";

    // Check data counts
    echo "<h2>Data counts:</h2><ul>";
    $tables_to_check = ['departments', 'lecturers', 'users', 'options', 'courses'];
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
            $stmt->execute();
            $count = $stmt->fetch()['count'];
            echo "<li>$table: $count records</li>";
        } catch (Exception $e) {
            echo "<li>$table: Error - {$e->getMessage()}</li>";
        }
    }
    echo "</ul>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}