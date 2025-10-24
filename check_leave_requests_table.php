<?php
require_once "config.php";

try {
    echo "<h2>Leave Requests Table Structure Analysis</h2>";
    
    // Check leave_requests table structure
    $stmt = $pdo->query("DESCRIBE leave_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Current leave_requests table structure:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . ($column['Extra'] ?? '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check sample data
    echo "<h3>Sample leave_requests data:</h3>";
    $stmt = $pdo->query("SELECT * FROM leave_requests LIMIT 5");
    $sample_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sample_data)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr>";
        foreach (array_keys($sample_data[0]) as $column) {
            echo "<th>" . htmlspecialchars($column) . "</th>";
        }
        echo "</tr>";
        
        foreach ($sample_data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No sample data found in leave_requests table.</p>";
    }
    
    // Check if reviewed_by references users or lecturers
    echo "<h3>Checking reviewed_by field relationships:</h3>";
    
    // Check foreign key constraints
    $stmt = $pdo->query("
        SELECT 
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'leave_requests' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $foreign_keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($foreign_keys)) {
        echo "<h4>Foreign Key Constraints:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Column</th><th>References Table</th><th>References Column</th></tr>";
        foreach ($foreign_keys as $fk) {
            echo "<tr>";
            echo "<td>" . $fk['COLUMN_NAME'] . "</td>";
            echo "<td>" . $fk['REFERENCED_TABLE_NAME'] . "</td>";
            echo "<td>" . $fk['REFERENCED_COLUMN_NAME'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No foreign key constraints found.</p>";
    }
    
    // Test what reviewed_by values look like
    echo "<h3>Analyzing reviewed_by values:</h3>";
    $stmt = $pdo->query("
        SELECT DISTINCT reviewed_by, COUNT(*) as count 
        FROM leave_requests 
        WHERE reviewed_by IS NOT NULL 
        GROUP BY reviewed_by 
        ORDER BY reviewed_by
    ");
    $reviewed_by_values = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($reviewed_by_values)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>reviewed_by Value</th><th>Count</th></tr>";
        foreach ($reviewed_by_values as $value) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($value['reviewed_by']) . "</td>";
            echo "<td>" . $value['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check if these values exist in users or lecturers table
        echo "<h4>Checking if reviewed_by values exist in users table:</h4>";
        foreach ($reviewed_by_values as $value) {
            $user_stmt = $pdo->prepare("SELECT id, username, first_name, last_name, role FROM users WHERE id = ?");
            $user_stmt->execute([$value['reviewed_by']]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo "<p>✅ reviewed_by = {$value['reviewed_by']} → User: {$user['username']} ({$user['first_name']} {$user['last_name']}) - Role: {$user['role']}</p>";
            } else {
                echo "<p>❌ reviewed_by = {$value['reviewed_by']} → Not found in users table</p>";
            }
        }
        
        echo "<h4>Checking if reviewed_by values exist in lecturers table:</h4>";
        foreach ($reviewed_by_values as $value) {
            $lecturer_stmt = $pdo->prepare("
                SELECT l.id, l.user_id, u.username, u.first_name, u.last_name, u.role 
                FROM lecturers l 
                JOIN users u ON l.user_id = u.id 
                WHERE l.id = ?
            ");
            $lecturer_stmt->execute([$value['reviewed_by']]);
            $lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lecturer) {
                echo "<p>✅ reviewed_by = {$value['reviewed_by']} → Lecturer ID {$lecturer['id']}, User: {$lecturer['username']} ({$lecturer['first_name']} {$lecturer['last_name']}) - Role: {$lecturer['role']}</p>";
            } else {
                echo "<p>❌ reviewed_by = {$value['reviewed_by']} → Not found in lecturers table</p>";
            }
        }
    } else {
        echo "<p>No reviewed_by values found (all NULL).</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>
