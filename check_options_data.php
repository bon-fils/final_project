<?php
/**
 * Check Options Data
 * Quick check to see what options exist in the database
 */

require_once "config.php";

try {
    echo "<h2>Database Options Analysis</h2>";
    
    // Check total options
    $stmt = $pdo->query("SELECT COUNT(*) FROM options");
    $total_options = $stmt->fetchColumn();
    
    echo "<p><strong>Total Options in Database:</strong> $total_options</p>";
    
    if ($total_options > 0) {
        // Get all options with department info
        $stmt = $pdo->query("
            SELECT o.id, o.name, o.status, o.department_id,
                   d.name as department_name
            FROM options o
            LEFT JOIN departments d ON o.department_id = d.id
            ORDER BY d.name, o.name
        ");
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>All Options:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Option Name</th><th>Department</th><th>Status</th></tr>";
        
        foreach ($options as $option) {
            $status_color = $option['status'] === 'active' ? 'green' : 'red';
            echo "<tr>";
            echo "<td>" . $option['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($option['name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($option['department_name'] ?? 'N/A') . " (ID: " . $option['department_id'] . ")</td>";
            echo "<td style='color: $status_color;'>" . $option['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Count by status
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM options GROUP BY status");
        $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Options by Status:</h3>";
        foreach ($status_counts as $status) {
            echo "<p><strong>" . ucfirst($status['status']) . ":</strong> " . $status['count'] . " options</p>";
        }
        
    } else {
        echo "<div style='color: red; padding: 20px; background: #ffe6e6; border-radius: 10px; margin: 20px 0;'>";
        echo "<h3>❌ No Options Found!</h3>";
        echo "<p>There are no options in the database. This is why the lecturer registration form shows 'No Options Available'.</p>";
        echo "<p><strong>To fix this:</strong></p>";
        echo "<ul>";
        echo "<li>Create options for each department through the admin panel</li>";
        echo "<li>Make sure the options have status = 'active'</li>";
        echo "<li>Ensure options are properly linked to departments</li>";
        echo "</ul>";
        echo "</div>";
        
        // Show departments for reference
        $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Available Departments (for creating options):</h3>";
        echo "<ul>";
        foreach ($departments as $dept) {
            echo "<li><strong>" . htmlspecialchars($dept['name']) . "</strong> (ID: " . $dept['id'] . ")</li>";
        }
        echo "</ul>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
}

echo "<br><a href='admin-register-lecturer.php'>← Back to Register Lecturer</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
th { background-color: #f2f2f2; font-weight: bold; }
tr:nth-child(even) { background-color: #f9f9f9; }
</style>
