<?php
/**
 * Apply Foreign Key Fixes
 * This script automatically applies all necessary fixes for the foreign key constraint
 */

require_once "config.php";

header('Content-Type: text/html; charset=utf-8');

echo "<div style='font-family: Arial, sans-serif;'>";
echo "<h3>🔧 Applying Foreign Key Fixes</h3>";

try {
    // Step 1: Drop any existing foreign key constraints on hod_id
    echo "<p style='color: blue;'>Step 1: Removing existing constraints...</p>";
    
    $existing_constraints = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'rp_attendance_system' 
        AND TABLE_NAME = 'departments' 
        AND COLUMN_NAME = 'hod_id'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $constraints = $existing_constraints->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($constraints as $constraint) {
        try {
            $pdo->exec("ALTER TABLE departments DROP FOREIGN KEY {$constraint['CONSTRAINT_NAME']}");
            echo "<p style='color: green;'>✅ Dropped constraint: {$constraint['CONSTRAINT_NAME']}</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'>⚠️ Could not drop {$constraint['CONSTRAINT_NAME']}: " . $e->getMessage() . "</p>";
        }
    }
    
    if (empty($constraints)) {
        echo "<p style='color: green;'>✅ No existing constraints to remove</p>";
    }
    
    // Step 2: Check and fix data types
    echo "<p style='color: blue;'>Step 2: Checking data types...</p>";
    
    $dept_hod_id_info = $pdo->query("
        SELECT COLUMN_TYPE 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'rp_attendance_system' 
        AND TABLE_NAME = 'departments' 
        AND COLUMN_NAME = 'hod_id'
    ")->fetch(PDO::FETCH_ASSOC);
    
    $lec_id_info = $pdo->query("
        SELECT COLUMN_TYPE 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = 'rp_attendance_system' 
        AND TABLE_NAME = 'lecturers' 
        AND COLUMN_NAME = 'id'
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($dept_hod_id_info['COLUMN_TYPE'] !== $lec_id_info['COLUMN_TYPE']) {
        echo "<p style='color: orange;'>⚠️ Data type mismatch detected. Fixing...</p>";
        echo "<p>departments.hod_id: {$dept_hod_id_info['COLUMN_TYPE']}</p>";
        echo "<p>lecturers.id: {$lec_id_info['COLUMN_TYPE']}</p>";
        
        try {
            $pdo->exec("ALTER TABLE departments MODIFY COLUMN hod_id {$lec_id_info['COLUMN_TYPE']} NULL");
            echo "<p style='color: green;'>✅ Fixed data type mismatch</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Could not fix data type: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color: green;'>✅ Data types match</p>";
    }
    
    // Step 3: Clean invalid data
    echo "<p style='color: blue;'>Step 3: Cleaning invalid data...</p>";
    
    $invalid_count = $pdo->query("
        SELECT COUNT(*) as count
        FROM departments d 
        LEFT JOIN lecturers l ON d.hod_id = l.id 
        WHERE d.hod_id IS NOT NULL AND l.id IS NULL
    ")->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($invalid_count > 0) {
        echo "<p style='color: orange;'>⚠️ Found $invalid_count invalid hod_id values. Cleaning...</p>";
        $pdo->exec("UPDATE departments SET hod_id = NULL WHERE hod_id NOT IN (SELECT id FROM lecturers)");
        echo "<p style='color: green;'>✅ Cleaned $invalid_count invalid hod_id values</p>";
    } else {
        echo "<p style='color: green;'>✅ No invalid data found</p>";
    }
    
    // Step 4: Add the correct foreign key constraint
    echo "<p style='color: blue;'>Step 4: Adding correct foreign key constraint...</p>";
    
    try {
        $pdo->exec("
            ALTER TABLE departments 
            ADD CONSTRAINT fk_departments_hod_id 
            FOREIGN KEY (hod_id) REFERENCES lecturers(id) 
            ON DELETE SET NULL 
            ON UPDATE CASCADE
        ");
        echo "<p style='color: green;'>🎉 Successfully added foreign key constraint!</p>";
        
        // Verify the constraint was added
        $verify_constraint = $pdo->query("
            SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = 'rp_attendance_system' 
            AND TABLE_NAME = 'departments' 
            AND COLUMN_NAME = 'hod_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetch(PDO::FETCH_ASSOC);
        
        if ($verify_constraint) {
            echo "<p style='color: green;'>✅ Verified: {$verify_constraint['CONSTRAINT_NAME']} → {$verify_constraint['REFERENCED_TABLE_NAME']}.{$verify_constraint['REFERENCED_COLUMN_NAME']}</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Could not add foreign key constraint: " . $e->getMessage() . "</p>";
        
        // Additional debugging
        echo "<p style='color: blue;'>Debugging information:</p>";
        echo "<p>MySQL Error Code: " . $pdo->errorCode() . "</p>";
        echo "<p>MySQL Error Info: " . print_r($pdo->errorInfo(), true) . "</p>";
        
        // Check if tables have proper engines
        $dept_engine = $pdo->query("
            SELECT ENGINE 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = 'rp_attendance_system' 
            AND TABLE_NAME = 'departments'
        ")->fetch(PDO::FETCH_ASSOC)['ENGINE'];
        
        $lec_engine = $pdo->query("
            SELECT ENGINE 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = 'rp_attendance_system' 
            AND TABLE_NAME = 'lecturers'
        ")->fetch(PDO::FETCH_ASSOC)['ENGINE'];
        
        echo "<p>departments table engine: $dept_engine</p>";
        echo "<p>lecturers table engine: $lec_engine</p>";
        
        if ($dept_engine !== $lec_engine) {
            echo "<p style='color: orange;'>⚠️ Table engines don't match. This might cause issues.</p>";
        }
    }
    
    echo "<h3>🎯 Summary</h3>";
    echo "<p>Foreign key constraint fixes have been applied. You should now be able to:</p>";
    echo "<ol>";
    echo "<li>Assign HODs to departments using: <code>UPDATE departments SET hod_id = [lecturer_id] WHERE id = [dept_id];</code></li>";
    echo "<li>Run the complete HOD fix script without constraint errors</li>";
    echo "<li>Use the HOD assignment interface properly</li>";
    echo "</ol>";
    
    echo "<p><a href='fix_abayo_hod.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Now Fix Abayo HOD Assignment</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Critical error: " . $e->getMessage() . "</p>";
    echo "<p style='color: red;'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
}

echo "</div>";
?>
