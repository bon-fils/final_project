<?php
/**
 * Fix attendance_sessions table - Add missing columns
 */

require_once "config.php";

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Attendance Sessions Table</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-success { background: #28a745; color: white; }
        .btn-primary { background: #007bff; color: white; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Fix Attendance Sessions Table</h1>";

try {
    // Get current table structure
    $columns = $pdo->query("DESCRIBE attendance_sessions")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Table Structure:</h2>";
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $existing_columns = [];
    foreach ($columns as $col) {
        $existing_columns[] = $col['Field'];
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for missing columns
    $required_columns = [
        'status' => "ENUM('active', 'completed', 'cancelled') DEFAULT 'active'",
        'biometric_method' => "ENUM('face', 'finger') NOT NULL",
        'year_level' => "VARCHAR(20) NOT NULL",
        'session_date' => "DATE NOT NULL",
        'start_time' => "TIME NOT NULL",
        'end_time' => "TIME NULL",
        'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    
    $missing_columns = [];
    foreach ($required_columns as $col_name => $col_definition) {
        if (!in_array($col_name, $existing_columns)) {
            $missing_columns[$col_name] = $col_definition;
        }
    }
    
    if (empty($missing_columns)) {
        echo "<div class='success'>✅ All required columns exist!</div>";
    } else {
        echo "<h2>Missing Columns:</h2>";
        echo "<div class='warning'>⚠️ The following columns are missing:</div>";
        echo "<table>";
        echo "<tr><th>Column Name</th><th>Definition</th><th>Action</th></tr>";
        
        foreach ($missing_columns as $col_name => $col_definition) {
            echo "<tr>";
            echo "<td><strong>$col_name</strong></td>";
            echo "<td>$col_definition</td>";
            echo "<td><button class='btn btn-success' onclick='addColumn(\"$col_name\", \"$col_definition\")'>Add Column</button></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<button class='btn btn-primary' onclick='fixAllColumns()'>🚀 Fix All Columns Automatically</button>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "
<script>
async function addColumn(columnName, columnDef) {
    if (!confirm('Add column: ' + columnName + '?')) return;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=add_column&column=' + encodeURIComponent(columnName) + '&definition=' + encodeURIComponent(columnDef)
        });
        
        const result = await response.text();
        alert(result);
        location.reload();
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}

async function fixAllColumns() {
    if (!confirm('Add all missing columns automatically?')) return;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=fix_all'
        });
        
        const result = await response.text();
        alert(result);
        location.reload();
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}
</script>";

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_column') {
            $column = $_POST['column'];
            $definition = $_POST['definition'];
            
            $sql = "ALTER TABLE attendance_sessions ADD COLUMN $column $definition";
            $pdo->exec($sql);
            
            echo "✅ Column '$column' added successfully!";
            
        } elseif ($_POST['action'] === 'fix_all') {
            $required_columns = [
                'status' => "ENUM('active', 'completed', 'cancelled') DEFAULT 'active'",
                'biometric_method' => "ENUM('face', 'finger') NOT NULL",
                'year_level' => "VARCHAR(20) NOT NULL",
                'session_date' => "DATE NOT NULL",
                'start_time' => "TIME NOT NULL",
                'end_time' => "TIME NULL",
                'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
            ];
            
            // Get existing columns
            $columns = $pdo->query("DESCRIBE attendance_sessions")->fetchAll(PDO::FETCH_ASSOC);
            $existing_columns = array_column($columns, 'Field');
            
            $added = 0;
            $skipped = 0;
            
            foreach ($required_columns as $col_name => $col_definition) {
                if (!in_array($col_name, $existing_columns)) {
                    try {
                        $sql = "ALTER TABLE attendance_sessions ADD COLUMN $col_name $col_definition";
                        $pdo->exec($sql);
                        $added++;
                    } catch (Exception $e) {
                        // Column might already exist or have an issue
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            }
            
            echo "✅ Fixed! Added $added columns, skipped $skipped existing columns.";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . htmlspecialchars($e->getMessage());
    }
    exit;
}

echo "</div></body></html>";
?>
