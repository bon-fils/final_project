<?php
/**
 * Fix Foreign Key Constraint Issue
 * The departments.hod_id should reference lecturers.id, not users.id
 */

require_once "config.php";

echo "<h1>🔧 Fixing Foreign Key Constraint Issue</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .card { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 3px; }
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>📊 Checking Current Foreign Key Constraints</h2>";
    
    // Check current constraints on departments table
    $constraints_stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'rp_attendance_system' 
        AND TABLE_NAME = 'departments' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $constraints = $constraints_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Current Foreign Key Constraints on departments table:</strong></p>";
    foreach ($constraints as $constraint) {
        echo "<p>• {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} → {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}</p>";
    }
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🔧 Fixing the Constraint</h2>";
    
    // Step 1: Drop the incorrect foreign key constraint
    echo "<p class='info'>Step 1: Dropping incorrect foreign key constraint...</p>";
    try {
        $pdo->exec("ALTER TABLE departments DROP FOREIGN KEY departments_ibfk_1");
        echo "<p class='success'>✅ Dropped incorrect foreign key constraint</p>";
    } catch (Exception $e) {
        echo "<p class='warning'>⚠️ Could not drop constraint (might not exist): " . $e->getMessage() . "</p>";
    }
    
    // Step 2: Ensure lecturers table exists and has proper structure
    echo "<p class='info'>Step 2: Checking lecturers table structure...</p>";
    try {
        $lecturers_check = $pdo->query("DESCRIBE lecturers");
        $lecturers_columns = $lecturers_check->fetchAll(PDO::FETCH_ASSOC);
        echo "<p class='success'>✅ Lecturers table exists with " . count($lecturers_columns) . " columns</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Lecturers table issue: " . $e->getMessage() . "</p>";
    }
    
    // Step 3: Add the correct foreign key constraint
    echo "<p class='info'>Step 3: Adding correct foreign key constraint...</p>";
    try {
        $pdo->exec("
            ALTER TABLE departments 
            ADD CONSTRAINT fk_departments_hod_id 
            FOREIGN KEY (hod_id) REFERENCES lecturers(id) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
        ");
        echo "<p class='success'>✅ Added correct foreign key constraint: departments.hod_id → lecturers.id</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Could not add constraint: " . $e->getMessage() . "</p>";
        
        // Check if there are invalid hod_id values
        echo "<p class='info'>Checking for invalid hod_id values...</p>";
        $invalid_check = $pdo->query("
            SELECT d.id, d.name, d.hod_id 
            FROM departments d 
            LEFT JOIN lecturers l ON d.hod_id = l.id 
            WHERE d.hod_id IS NOT NULL AND l.id IS NULL
        ");
        $invalid_hods = $invalid_check->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($invalid_hods)) {
            echo "<p class='warning'>⚠️ Found departments with invalid hod_id values:</p>";
            foreach ($invalid_hods as $dept) {
                echo "<p>• Department '{$dept['name']}' (ID: {$dept['id']}) has hod_id = {$dept['hod_id']} but no lecturer with that ID exists</p>";
            }
            
            echo "<p class='info'>Cleaning up invalid hod_id values...</p>";
            $pdo->exec("UPDATE departments SET hod_id = NULL WHERE hod_id NOT IN (SELECT id FROM lecturers)");
            echo "<p class='success'>✅ Cleaned up invalid hod_id values</p>";
            
            // Try adding constraint again
            try {
                $pdo->exec("
                    ALTER TABLE departments 
                    ADD CONSTRAINT fk_departments_hod_id 
                    FOREIGN KEY (hod_id) REFERENCES lecturers(id) 
                    ON DELETE SET NULL 
                    ON UPDATE CASCADE
                ");
                echo "<p class='success'>✅ Successfully added correct foreign key constraint after cleanup</p>";
            } catch (Exception $e2) {
                echo "<p class='error'>❌ Still could not add constraint: " . $e2->getMessage() . "</p>";
            }
        }
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🔍 Verification</h2>";
    
    // Check constraints again
    $new_constraints_stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'rp_attendance_system' 
        AND TABLE_NAME = 'departments' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $new_constraints = $new_constraints_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p><strong>Updated Foreign Key Constraints:</strong></p>";
    if (empty($new_constraints)) {
        echo "<p class='warning'>⚠️ No foreign key constraints found</p>";
    } else {
        foreach ($new_constraints as $constraint) {
            $status = ($constraint['REFERENCED_TABLE_NAME'] === 'lecturers') ? 'success' : 'error';
            echo "<p class='$status'>• {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} → {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}</p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🚀 Now Try the HOD Assignment</h2>";
    echo "<p>The foreign key constraint has been fixed. You can now try these commands:</p>";
    echo "<pre>";
    echo "-- First, check which lecturers exist:\n";
    echo "SELECT id, user_id FROM lecturers WHERE user_id = (SELECT id FROM users WHERE email = 'abayo@gmail.com');\n\n";
    echo "-- Then assign HOD (replace X with the actual lecturer ID):\n";
    echo "UPDATE departments SET hod_id = X WHERE id = 7;\n";
    echo "</pre>";
    
    // Show current lecturer data for abayo
    echo "<p><strong>Current lecturer data for abayo@gmail.com:</strong></p>";
    $abayo_lecturer = $pdo->query("
        SELECT l.id, l.user_id, u.email, u.role 
        FROM lecturers l 
        LEFT JOIN users u ON l.user_id = u.id 
        WHERE u.email = 'abayo@gmail.com' OR l.user_id = (SELECT id FROM users WHERE email = 'abayo@gmail.com')
    ");
    $abayo_data = $abayo_lecturer->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($abayo_data)) {
        echo "<p class='warning'>⚠️ No lecturer record found for abayo@gmail.com</p>";
        echo "<p><a href='fix_abayo_hod.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Run Complete Fix for Abayo</a></p>";
    } else {
        foreach ($abayo_data as $data) {
            echo "<p>• Lecturer ID: {$data['id']}, User ID: {$data['user_id']}, Email: {$data['email']}, Role: {$data['role']}</p>";
            
            if ($data['id']) {
                echo "<p class='info'>You can now run: <code>UPDATE departments SET hod_id = {$data['id']} WHERE id = 7;</code></p>";
            }
        }
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<p class='error'>❌ Error occurred: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Foreign key constraint fix completed!</em></p>";
?>
