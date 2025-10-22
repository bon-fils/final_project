<?php
/**
 * Verify and Fix attendance_sessions Table
 * Checks all required columns and adds missing ones
 */

require_once "config.php";

echo "<!DOCTYPE html><html><head><title>Verify Table</title>
<style>
body { font-family: Arial; margin: 20px; background: #f5f5f5; }
.container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
.success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
.error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
.info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f8f9fa; }
</style>
</head><body><div class='container'>";

echo "<h1>🔍 Verify attendance_sessions Table</h1>";

try {
    // Get current columns
    $columns = $pdo->query("DESCRIBE attendance_sessions")->fetchAll(PDO::FETCH_ASSOC);
    
    $existing = array_column($columns, 'Field');
    
    // Required columns based on your table structure
    $required = [
        'id' => 'INT PRIMARY KEY AUTO_INCREMENT',
        'lecturer_id' => 'INT NOT NULL',
        'course_id' => 'INT NOT NULL',
        'option_id' => 'INT NOT NULL',
        'department_id' => 'INT NOT NULL',
        'session_date' => 'DATE NOT NULL',
        'start_time' => 'TIME NOT NULL',
        'end_time' => 'TIME NULL',
        'biometric_method' => "ENUM('face_recognition', 'fingerprint') NOT NULL",
        'status' => "ENUM('active', 'completed', 'cancelled') DEFAULT 'active'",
        'year_level' => 'VARCHAR(20) NOT NULL',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    echo "<h2>Current Columns:</h2>";
    echo "<table><tr><th>Field</th><th>Type</th><th>Status</th></tr>";
    
    foreach ($columns as $col) {
        $status = isset($required[$col['Field']]) ? '✅ Required' : '⚠️ Extra';
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>$status</td></tr>";
    }
    echo "</table>";
    
    // Check for missing columns
    $missing = array_diff(array_keys($required), $existing);
    
    if (empty($missing)) {
        echo "<div class='success'>✅ All required columns exist!</div>";
    } else {
        echo "<h2>❌ Missing Columns:</h2>";
        foreach ($missing as $col) {
            echo "<div class='error'>Missing: <strong>$col</strong> ({$required[$col]})</div>";
        }
        
        echo "<div class='info'>To add missing columns, run this in your database:</div>";
        echo "<pre>";
        foreach ($missing as $col) {
            echo "ALTER TABLE attendance_sessions ADD COLUMN $col {$required[$col]};\n";
        }
        echo "</pre>";
    }
    
    // Check if department_id exists
    if (!in_array('department_id', $existing)) {
        echo "<div class='error'><strong>⚠️ CRITICAL: department_id column is missing!</strong></div>";
        echo "<div class='info'>Run this SQL:</div>";
        echo "<pre>ALTER TABLE attendance_sessions ADD COLUMN department_id INT NOT NULL AFTER option_id;</pre>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
