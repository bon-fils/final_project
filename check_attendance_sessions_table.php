<?php
/**
 * Check attendance_sessions table structure
 */

require_once "config.php";

echo "<!DOCTYPE html>
<html>
<head>
    <title>Check Attendance Sessions Table</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Check Attendance Sessions Table</h1>";

try {
    // Check if table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'attendance_sessions'")->fetchAll();
    
    if (empty($tables)) {
        echo "<div class='error'>❌ Table 'attendance_sessions' does NOT exist!</div>";
        echo "<div class='info'>💡 Need to create the table. Here's the SQL:</div>";
        echo "<pre>CREATE TABLE attendance_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    lecturer_id INT NOT NULL,
    course_id INT NOT NULL,
    option_id INT NOT NULL,
    department_id INT NOT NULL,
    year_level VARCHAR(20) NOT NULL,
    biometric_method ENUM('face', 'finger') NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NULL,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);</pre>";
        
        echo "<button onclick='createTable()' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;'>Create Table</button>";
        
    } else {
        echo "<div class='success'>✅ Table 'attendance_sessions' exists!</div>";
        
        // Get table structure
        echo "<h2>Table Structure:</h2>";
        $columns = $pdo->query("DESCRIBE attendance_sessions")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        foreach ($columns as $col) {
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Get row count
        $count = $pdo->query("SELECT COUNT(*) as count FROM attendance_sessions")->fetch(PDO::FETCH_ASSOC);
        echo "<div class='info'>📊 Total records: {$count['count']}</div>";
        
        // Show recent sessions
        if ($count['count'] > 0) {
            echo "<h2>Recent Sessions:</h2>";
            $sessions = $pdo->query("SELECT * FROM attendance_sessions ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<table>";
            $headers = array_keys($sessions[0]);
            echo "<tr>";
            foreach ($headers as $header) {
                echo "<th>$header</th>";
            }
            echo "</tr>";
            
            foreach ($sessions as $session) {
                echo "<tr>";
                foreach ($session as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "
<script>
async function createTable() {
    if (!confirm('Create attendance_sessions table?')) return;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=create_table'
        });
        
        if (response.ok) {
            alert('✅ Table created successfully!');
            location.reload();
        } else {
            alert('❌ Error creating table');
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}
</script>";

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_table') {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS attendance_sessions (
                id INT PRIMARY KEY AUTO_INCREMENT,
                lecturer_id INT NOT NULL,
                course_id INT NOT NULL,
                option_id INT NOT NULL,
                department_id INT NOT NULL,
                year_level VARCHAR(20) NOT NULL,
                biometric_method ENUM('face', 'finger') NOT NULL,
                session_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NULL,
                status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
            
            echo "<div class='success'>✅ Table created successfully!</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
    exit;
}

echo "</div></body></html>";
?>
