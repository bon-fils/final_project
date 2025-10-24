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
    
    // Check for request routing fields
    echo "<h3>Checking for request routing fields:</h3>";
    $routing_fields = ['requested_to', 'assigned_to', 'reviewer_id', 'approver_id', 'hod_id', 'lecturer_id'];
    foreach ($routing_fields as $field) {
        $field_exists = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $field) {
                echo "<p>✅ Found field: <strong>$field</strong> ({$column['Type']})</p>";
                $field_exists = true;
                break;
            }
        }
        if (!$field_exists) {
            echo "<p>❌ Field not found: $field</p>";
        }
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
    
    // Check business logic - who should review what
    echo "<h3>Business Logic Analysis:</h3>";
    echo "<h4>Understanding the request flow:</h4>";
    echo "<ol>";
    echo "<li><strong>Student submits leave request</strong> - stored in leave_requests table</li>";
    echo "<li><strong>Request needs to be routed to specific reviewer</strong> - HOD of student's department</li>";
    echo "<li><strong>Only that specific reviewer can approve/reject</strong></li>";
    echo "</ol>";
    
    echo "<h4>Current implementation questions:</h4>";
    echo "<ul>";
    echo "<li>❓ Does the system automatically assign requests to department HOD?</li>";
    echo "<li>❓ Is there a field that specifies WHO should review each request?</li>";
    echo "<li>❓ Should we determine the reviewer based on student's department?</li>";
    echo "</ul>";
    
    // Test the current logic
    echo "<h4>Testing current request-to-reviewer logic:</h4>";
    $stmt = $pdo->query("
        SELECT 
            lr.id,
            lr.student_id,
            lr.status,
            s.department_id,
            d.name as department_name,
            d.hod_id,
            u_student.first_name as student_first_name,
            u_student.last_name as student_last_name,
            u_hod.first_name as hod_first_name,
            u_hod.last_name as hod_last_name
        FROM leave_requests lr
        JOIN students s ON lr.student_id = s.id
        JOIN users u_student ON s.user_id = u_student.id
        JOIN departments d ON s.department_id = d.id
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u_hod ON l.user_id = u_hod.id
        LIMIT 5
    ");
    $test_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($test_requests)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Request ID</th><th>Student</th><th>Department</th><th>Should be reviewed by (HOD)</th><th>Status</th></tr>";
        foreach ($test_requests as $request) {
            echo "<tr>";
            echo "<td>" . $request['id'] . "</td>";
            echo "<td>" . htmlspecialchars($request['student_first_name'] . ' ' . $request['student_last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($request['department_name']) . "</td>";
            echo "<td>" . htmlspecialchars(($request['hod_first_name'] ?? 'No HOD') . ' ' . ($request['hod_last_name'] ?? '')) . "</td>";
            echo "<td>" . htmlspecialchars($request['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
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
