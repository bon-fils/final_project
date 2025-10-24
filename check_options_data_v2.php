<?php
/**
 * Check Options Data - Version 2
 * Clean version to check what options exist in the database
 */

require_once "config.php";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Options Data Analysis</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
    <h2>Database Options Analysis</h2>

<?php
try {
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
        echo "<table>";
        echo "<tr><th>ID</th><th>Option Name</th><th>Department</th><th>Status</th></tr>";
        
        foreach ($options as $option) {
            $status_color = $option['status'] === 'active' ? 'success' : 'error';
            echo "<tr>";
            echo "<td>" . $option['id'] . "</td>";
            echo "<td><strong>" . htmlspecialchars($option['name']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($option['department_name'] ?? 'N/A') . " (ID: " . $option['department_id'] . ")</td>";
            echo "<td class='$status_color'>" . $option['status'] . "</td>";
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
        echo "<p>There are no options in the database.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<h3 class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
?>

<br>
<a href="admin-register-lecturer.php">← Back to Register Lecturer</a>

</body>
</html>
