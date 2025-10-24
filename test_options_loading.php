<?php
/**
 * Test Options Loading
 * Test script to verify options are loading correctly for departments
 */

require_once "config.php";

try {
    echo "<h2>Testing Options Loading for Departments</h2>";
    
    // Get all departments
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Available Departments:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Department Name</th><th>Options Count</th><th>Options Available</th></tr>";
    
    foreach ($departments as $dept) {
        // Get options for this department
        $stmt = $pdo->prepare("
            SELECT id, name, status
            FROM options 
            WHERE department_id = ? AND status = 'active'
            ORDER BY name ASC
        ");
        $stmt->execute([$dept['id']]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<tr>";
        echo "<td>" . $dept['id'] . "</td>";
        echo "<td>" . htmlspecialchars($dept['name']) . "</td>";
        echo "<td>" . count($options) . "</td>";
        echo "<td>";
        
        if (empty($options)) {
            echo "<em style='color: red;'>No options available</em>";
        } else {
            foreach ($options as $option) {
                echo "<div style='margin: 2px 0; padding: 2px; background: #f0f0f0; border-radius: 3px;'>";
                echo "<strong>" . htmlspecialchars($option['name']) . "</strong>";
                echo "</div>";
            }
        }
        echo "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test the API endpoint directly
    echo "<h3>Testing API Endpoints:</h3>";
    
    foreach ($departments as $dept) {
        echo "<h4>Department: " . htmlspecialchars($dept['name']) . " (ID: " . $dept['id'] . ")</h4>";
        
        // Simulate the API call
        $department_id = $dept['id'];
        
        $stmt = $pdo->prepare("
            SELECT id, name, status
            FROM options 
            WHERE department_id = ? AND status = 'active'
            ORDER BY name ASC
        ");
        $stmt->execute([$department_id]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($options)) {
            echo "<div style='color: red; padding: 10px; background: #ffe6e6; border-radius: 5px; margin: 5px 0;'>";
            echo "❌ No active options found for this department";
            echo "</div>";
            
            // Check if there are any options at all (including inactive)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $total_options = $stmt->fetchColumn();
            
            if ($total_options > 0) {
                echo "<p style='color: orange;'>⚠️ Found $total_options total options, but none are active</p>";
            } else {
                echo "<p style='color: red;'>❌ No options exist for this department at all</p>";
            }
        } else {
            echo "<div style='color: green; padding: 10px; background: #e6ffe6; border-radius: 5px; margin: 5px 0;'>";
            echo "✅ Found " . count($options) . " active option(s)";
            echo "</div>";
            
            echo "<ul>";
            foreach ($options as $option) {
                echo "<li><strong>" . htmlspecialchars($option['name']) . "</strong> (ID: " . $option['id'] . ")</li>";
            }
            echo "</ul>";
        }
        
        echo "<hr>";
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
