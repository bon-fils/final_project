<?php
/**
 * Diagnose Foreign Key Constraint Issue
 * This will help identify why the foreign key constraint is failing
 */

require_once "config.php";

echo "<h1>🔍 Diagnosing Foreign Key Constraint Issue</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .card { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>1. 📊 Checking Table Structures</h2>";
    
    // Check departments table structure
    echo "<h3>Departments Table Structure:</h3>";
    $dept_structure = $pdo->query("DESCRIBE departments");
    $dept_columns = $dept_structure->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($dept_columns as $col) {
        $highlight = ($col['Field'] === 'hod_id') ? 'style="background-color: #ffffcc;"' : '';
        echo "<tr $highlight>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check lecturers table structure
    echo "<h3>Lecturers Table Structure:</h3>";
    $lec_structure = $pdo->query("DESCRIBE lecturers");
    $lec_columns = $lec_structure->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($lec_columns as $col) {
        $highlight = ($col['Field'] === 'id') ? 'style="background-color: #ffffcc;"' : '';
        echo "<tr $highlight>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>2. 🔍 Data Type Compatibility Check</h2>";
    
    // Get specific column info
    $dept_hod_id_info = $pdo->query("
        SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'rp_attendance_system' 
        AND TABLE_NAME = 'departments' 
        AND COLUMN_NAME = 'hod_id'
    ")->fetch(PDO::FETCH_ASSOC);
    
    $lec_id_info = $pdo->query("
        SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'rp_attendance_system' 
        AND TABLE_NAME = 'lecturers' 
        AND COLUMN_NAME = 'id'
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>departments.hod_id:</strong> {$dept_hod_id_info['COLUMN_TYPE']}, Nullable: {$dept_hod_id_info['IS_NULLABLE']}</p>";
    echo "<p><strong>lecturers.id:</strong> {$lec_id_info['COLUMN_TYPE']}, Nullable: {$lec_id_info['IS_NULLABLE']}</p>";
    
    if ($dept_hod_id_info['COLUMN_TYPE'] !== $lec_id_info['COLUMN_TYPE']) {
        echo "<p class='error'>❌ Data type mismatch detected!</p>";
        echo "<p class='info'>departments.hod_id ({$dept_hod_id_info['COLUMN_TYPE']}) ≠ lecturers.id ({$lec_id_info['COLUMN_TYPE']})</p>";
    } else {
        echo "<p class='success'>✅ Data types match</p>";
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>3. 📋 Current Data Validation</h2>";
    
    // Check for invalid hod_id values
    echo "<h3>Invalid hod_id Values:</h3>";
    $invalid_hods = $pdo->query("
        SELECT d.id, d.name, d.hod_id 
        FROM departments d 
        LEFT JOIN lecturers l ON d.hod_id = l.id 
        WHERE d.hod_id IS NOT NULL AND l.id IS NULL
    ");
    $invalid_data = $invalid_hods->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($invalid_data)) {
        echo "<p class='success'>✅ No invalid hod_id values found</p>";
    } else {
        echo "<p class='error'>❌ Found invalid hod_id values:</p>";
        echo "<table>";
        echo "<tr><th>Dept ID</th><th>Department Name</th><th>Invalid hod_id</th></tr>";
        foreach ($invalid_data as $row) {
            echo "<tr>";
            echo "<td>{$row['id']}</td>";
            echo "<td>{$row['name']}</td>";
            echo "<td>{$row['hod_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Show all current hod_id values
    echo "<h3>All Current hod_id Values:</h3>";
    $all_hods = $pdo->query("SELECT id, name, hod_id FROM departments ORDER BY id");
    $all_hod_data = $all_hods->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Dept ID</th><th>Department Name</th><th>hod_id</th><th>Status</th></tr>";
    foreach ($all_hod_data as $row) {
        $status = 'NULL';
        $status_class = 'info';
        
        if ($row['hod_id']) {
            // Check if lecturer exists
            $lec_check = $pdo->prepare("SELECT id FROM lecturers WHERE id = ?");
            $lec_check->execute([$row['hod_id']]);
            if ($lec_check->fetch()) {
                $status = 'Valid';
                $status_class = 'success';
            } else {
                $status = 'Invalid';
                $status_class = 'error';
            }
        }
        
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>" . ($row['hod_id'] ?: 'NULL') . "</td>";
        echo "<td class='$status_class'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>4. 🔧 Recommended Fixes</h2>";
    
    $fixes_needed = [];
    
    // Check if data types match
    if ($dept_hod_id_info['COLUMN_TYPE'] !== $lec_id_info['COLUMN_TYPE']) {
        $fixes_needed[] = "Fix data type mismatch";
        echo "<h3>Fix 1: Data Type Mismatch</h3>";
        echo "<pre>";
        echo "ALTER TABLE departments MODIFY COLUMN hod_id {$lec_id_info['COLUMN_TYPE']} NULL;";
        echo "</pre>";
    }
    
    // Check for invalid data
    if (!empty($invalid_data)) {
        $fixes_needed[] = "Clean invalid data";
        echo "<h3>Fix 2: Clean Invalid Data</h3>";
        echo "<pre>";
        echo "UPDATE departments SET hod_id = NULL WHERE hod_id NOT IN (SELECT id FROM lecturers);";
        echo "</pre>";
    }
    
    // Check existing constraints
    echo "<h3>Fix 3: Remove Any Existing Constraints</h3>";
    $existing_constraints = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'rp_attendance_system' 
        AND TABLE_NAME = 'departments' 
        AND COLUMN_NAME = 'hod_id'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $constraints = $existing_constraints->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($constraints)) {
        echo "<p class='warning'>⚠️ Found existing constraints that need to be dropped first:</p>";
        foreach ($constraints as $constraint) {
            echo "<pre>ALTER TABLE departments DROP FOREIGN KEY {$constraint['CONSTRAINT_NAME']};</pre>";
        }
    } else {
        echo "<p class='success'>✅ No existing constraints found</p>";
    }
    
    echo "<h3>Fix 4: Add Correct Constraint</h3>";
    echo "<pre>";
    echo "ALTER TABLE departments \n";
    echo "ADD CONSTRAINT fk_departments_hod_id \n";
    echo "FOREIGN KEY (hod_id) REFERENCES lecturers(id) \n";
    echo "ON DELETE SET NULL \n";
    echo "ON UPDATE CASCADE;";
    echo "</pre>";
    
    if (empty($fixes_needed)) {
        echo "<p class='success'>🎉 No obvious issues found. The constraint should work!</p>";
    } else {
        echo "<p class='warning'>⚠️ Issues found that need fixing: " . implode(', ', $fixes_needed) . "</p>";
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>5. 🚀 Auto-Fix Option</h2>";
    echo "<p>Click the button below to automatically apply all necessary fixes:</p>";
    echo "<button onclick='applyFixes()' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>Apply All Fixes</button>";
    echo "<div id='fixResults' style='margin-top: 10px;'></div>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<p class='error'>❌ Error occurred: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Diagnosis completed!</em></p>";
?>

<script>
async function applyFixes() {
    const resultsDiv = document.getElementById('fixResults');
    resultsDiv.innerHTML = '<p style="color: blue;">🔄 Applying fixes...</p>';
    
    try {
        const response = await fetch('apply_fk_fixes.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        });
        
        const result = await response.text();
        resultsDiv.innerHTML = result;
    } catch (error) {
        resultsDiv.innerHTML = '<p style="color: red;">❌ Error applying fixes: ' + error.message + '</p>';
    }
}
</script>
