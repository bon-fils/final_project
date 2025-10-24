<?php
require_once "config.php";
session_start();

echo "<h2>Fix HOD Assignment</h2>";

if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ Please login first.</p>";
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Unknown';

echo "<p><strong>Current User:</strong> $username (ID: $user_id)</p>";

try {
    // Get lecturer info
    $stmt = $pdo->prepare("SELECT id, department_id FROM lecturers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lecturer) {
        echo "<p style='color: red;'>❌ No lecturer record found!</p>";
        exit;
    }
    
    $lecturer_id = $lecturer['id'];
    $current_dept_id = $lecturer['department_id'];
    
    echo "<p><strong>Lecturer ID:</strong> $lecturer_id</p>";
    echo "<p><strong>Current Department ID:</strong> $current_dept_id</p>";
    
    // Show available departments
    echo "<h3>Available Departments (without HOD):</h3>";
    $stmt = $pdo->query("
        SELECT id, name, status 
        FROM departments 
        WHERE (hod_id IS NULL OR hod_id = 0) AND status = 'active'
        ORDER BY name
    ");
    $available_depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($available_depts)) {
        echo "<p style='color: orange;'>⚠️ No departments available without HOD assignment.</p>";
        
        // Show option to replace existing HOD
        echo "<h3>Alternative: Replace Existing HOD</h3>";
        echo "<p>You can replace an existing HOD assignment. Current assignments:</p>";
        
        $stmt = $pdo->query("
            SELECT d.id, d.name, d.hod_id, l.id as lecturer_id, u.username, u.first_name, u.last_name
            FROM departments d
            JOIN lecturers l ON d.hod_id = l.id
            JOIN users u ON l.user_id = u.id
            WHERE d.hod_id IS NOT NULL AND d.status = 'active'
            ORDER BY d.name
        ");
        $occupied_depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Dept ID</th><th>Department</th><th>Current HOD</th><th>Action</th></tr>";
        foreach ($occupied_depts as $dept) {
            echo "<tr>";
            echo "<td>" . $dept['id'] . "</td>";
            echo "<td>" . htmlspecialchars($dept['name']) . "</td>";
            echo "<td>" . htmlspecialchars($dept['first_name'] . ' ' . $dept['last_name']) . " (" . $dept['username'] . ")</td>";
            echo "<td><a href='?action=replace&dept_id=" . $dept['id'] . "' onclick='return confirm(\"Replace " . htmlspecialchars($dept['first_name'] . ' ' . $dept['last_name']) . " as HOD?\")'>Replace</a></td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Dept ID</th><th>Department Name</th><th>Action</th></tr>";
        foreach ($available_depts as $dept) {
            $highlight = ($dept['id'] == $current_dept_id) ? 'background-color: #d4edda;' : '';
            echo "<tr style='$highlight'>";
            echo "<td>" . $dept['id'] . "</td>";
            echo "<td>" . htmlspecialchars($dept['name']) . "</td>";
            echo "<td><a href='?action=assign&dept_id=" . $dept['id'] . "' onclick='return confirm(\"Assign as HOD to " . htmlspecialchars($dept['name']) . "?\")'>Assign as HOD</a></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        if ($current_dept_id) {
            echo "<p style='color: green;'>💡 <strong>Recommended:</strong> Assign to your current department (highlighted in green)</p>";
        }
    }
    
    // Handle assignment action
    if (isset($_GET['action']) && isset($_GET['dept_id'])) {
        $action = $_GET['action'];
        $dept_id = intval($_GET['dept_id']);
        
        if ($action === 'assign' || $action === 'replace') {
            // Get department name
            $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $stmt->execute([$dept_id]);
            $dept_name = $stmt->fetchColumn();
            
            if ($dept_name) {
                // Update department HOD assignment
                $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
                $success = $stmt->execute([$lecturer_id, $dept_id]);
                
                if ($success) {
                    // Also update user role to 'hod' if it's not already
                    $stmt = $pdo->prepare("UPDATE users SET role = 'hod' WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Update session
                    $_SESSION['role'] = 'hod';
                    
                    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 20px 0;'>";
                    echo "<h4>✅ Success!</h4>";
                    echo "<p><strong>$username</strong> has been assigned as HOD of <strong>" . htmlspecialchars($dept_name) . "</strong></p>";
                    echo "<p>User role updated to 'hod'</p>";
                    echo "<p><a href='hod-leave-management.php' class='btn'>Go to Leave Management</a></p>";
                    echo "</div>";
                    
                    // Log the change
                    error_log("HOD Assignment: User $user_id ($username) assigned as HOD to department $dept_id ($dept_name)");
                    
                } else {
                    echo "<p style='color: red;'>❌ Failed to update department assignment</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ Department not found</p>";
            }
        }
    }
    
    echo "<h3>Manual SQL Commands:</h3>";
    echo "<p>If you prefer to run SQL manually:</p>";
    echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
    echo "-- Assign $username (lecturer ID: $lecturer_id) as HOD to a department:\n";
    echo "UPDATE departments SET hod_id = $lecturer_id WHERE id = [DEPARTMENT_ID];\n\n";
    echo "-- Update user role to HOD:\n";
    echo "UPDATE users SET role = 'hod' WHERE id = $user_id;\n\n";
    echo "-- Example: Assign to Civil Engineering (ID: 3):\n";
    echo "UPDATE departments SET hod_id = $lecturer_id WHERE id = 3;";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; width: 100%; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
.btn { 
    display: inline-block; 
    padding: 10px 20px; 
    background: #007bff; 
    color: white; 
    text-decoration: none; 
    border-radius: 5px; 
    margin-top: 10px;
}
.btn:hover { background: #0056b3; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
