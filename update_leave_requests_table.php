<?php
/**
 * Update leave_requests table to match the new schema requirements
 */

require_once "config.php";

try {
    echo "<h2>Updating leave_requests table structure...</h2>";
    
    // First, check current table structure
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Current table structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check which columns exist
    $existing_columns = array_column($columns, 'Field');
    
    $required_columns = [
        'from_date' => "ALTER TABLE leave_requests ADD COLUMN from_date DATE NOT NULL AFTER reason",
        'to_date' => "ALTER TABLE leave_requests ADD COLUMN to_date DATE NOT NULL AFTER from_date", 
        'request_to' => "ALTER TABLE leave_requests ADD COLUMN request_to ENUM('lecturer', 'hod') NOT NULL DEFAULT 'hod' AFTER to_date"
    ];
    
    echo "<h3>Adding missing columns:</h3>";
    
    foreach ($required_columns as $column => $sql) {
        if (!in_array($column, $existing_columns)) {
            try {
                $pdo->exec($sql);
                echo "<p style='color: green;'>✅ Added column: $column</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ Failed to add column $column: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ Column $column already exists</p>";
        }
    }
    
    // Update the requested_to column to be nullable since we're using request_to enum now
    try {
        $pdo->exec("ALTER TABLE leave_requests MODIFY requested_to VARCHAR(255) NULL");
        echo "<p style='color: green;'>✅ Updated requested_to column to be nullable</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠️ Could not update requested_to column: " . $e->getMessage() . "</p>";
    }
    
    // Show final table structure
    echo "<h3>Final table structure:</h3>";
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $final_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($final_columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>✅ Table update completed successfully!</h3>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error updating table: " . $e->getMessage() . "</h3>";
}

echo "<br><a href='request-leave.php'>← Back to Request Leave</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>
