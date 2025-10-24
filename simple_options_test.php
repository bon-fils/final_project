<?php
echo "<h2>Simple Options Test</h2>";

try {
    require_once "config.php";
    echo "<p>✅ Config loaded successfully</p>";
    
    // Test database connection
    $stmt = $pdo->query("SELECT COUNT(*) FROM options");
    $total_options = $stmt->fetchColumn();
    echo "<p>✅ Database connected successfully</p>";
    echo "<p><strong>Total Options:</strong> $total_options</p>";
    
    // Get first 5 options
    $stmt = $pdo->query("SELECT id, name, department_id, status FROM options LIMIT 5");
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>First 5 Options:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Dept ID</th><th>Status</th></tr>";
    
    foreach ($options as $option) {
        echo "<tr>";
        echo "<td>" . $option['id'] . "</td>";
        echo "<td>" . htmlspecialchars($option['name']) . "</td>";
        echo "<td>" . $option['department_id'] . "</td>";
        echo "<td>" . $option['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p>✅ Test completed successfully!</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; }
th { background-color: #f2f2f2; }
</style>
