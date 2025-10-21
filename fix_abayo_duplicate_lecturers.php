<?php
/**
 * Fix Abayo Duplicate Lecturer Records
 * User abayo.iradukunda (ID: 63) has multiple lecturer records which is causing issues
 */

require_once "config.php";

echo "<h1>🔧 Fixing Abayo Duplicate Lecturer Records</h1>";
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
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>📊 Analyzing Abayo's Lecturer Records</h2>";
    
    // Get detailed info about abayo's lecturer records
    $abayo_lecturers = $pdo->prepare("
        SELECT l.id, l.gender, l.dob, l.id_number, l.department_id, l.education_level, 
               l.created_at, l.updated_at, l.user_id,
               d.name as department_name,
               u.username, u.email, u.role
        FROM lecturers l
        LEFT JOIN departments d ON l.department_id = d.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE l.user_id = 63 OR u.username = 'abayo.iradukunda'
        ORDER BY l.id
    ");
    $abayo_lecturers->execute();
    $lecturer_records = $abayo_lecturers->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Abayo's Lecturer Records:</h3>";
    echo "<table>";
    echo "<tr><th>Lecturer ID</th><th>User ID</th><th>Department</th><th>ID Number</th><th>Education</th><th>Created</th><th>Status</th></tr>";
    
    $primary_lecturer = null;
    $duplicate_lecturers = [];
    
    foreach ($lecturer_records as $record) {
        $status = 'Valid';
        $status_class = 'success';
        
        // Determine which should be the primary record
        if (!$primary_lecturer) {
            $primary_lecturer = $record;
        } else {
            $duplicate_lecturers[] = $record;
            $status = 'Duplicate';
            $status_class = 'warning';
        }
        
        echo "<tr class='$status_class'>";
        echo "<td>{$record['id']}</td>";
        echo "<td>{$record['user_id']}</td>";
        echo "<td>" . ($record['department_name'] ?: 'N/A') . " (ID: {$record['department_id']})</td>";
        echo "<td>{$record['id_number']}</td>";
        echo "<td>{$record['education_level']}</td>";
        echo "<td>{$record['created_at']}</td>";
        echo "<td class='$status_class'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    if (count($lecturer_records) > 1) {
        echo "<p class='warning'>⚠️ Found " . count($lecturer_records) . " lecturer records for the same user!</p>";
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🔧 Fixing the Duplicate Issue</h2>";
    
    if (count($lecturer_records) <= 1) {
        echo "<p class='success'>✅ No duplicates found - only one lecturer record exists.</p>";
    } else {
        echo "<p class='info'>We'll keep the first lecturer record (ID: {$primary_lecturer['id']}) and remove duplicates.</p>";
        
        $pdo->beginTransaction();
        
        try {
            // Step 1: Update departments.hod_id to use the primary lecturer ID
            echo "<h3>Step 1: Updating Department Assignment</h3>";
            
            $update_dept = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE hod_id = 63");
            $update_dept->execute([$primary_lecturer['id']]);
            
            echo "<p class='success'>✅ Updated departments.hod_id from 63 (user_id) to {$primary_lecturer['id']} (lecturer_id)</p>";
            
            // Step 2: Remove duplicate lecturer records
            echo "<h3>Step 2: Removing Duplicate Lecturer Records</h3>";
            
            foreach ($duplicate_lecturers as $duplicate) {
                // Check if this duplicate is referenced anywhere
                $dept_check = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE hod_id = ?");
                $dept_check->execute([$duplicate['id']]);
                $dept_refs = $dept_check->fetchColumn();
                
                if ($dept_refs > 0) {
                    echo "<p class='warning'>⚠️ Lecturer ID {$duplicate['id']} is referenced by departments - updating references first</p>";
                    $update_refs = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE hod_id = ?");
                    $update_refs->execute([$primary_lecturer['id'], $duplicate['id']]);
                }
                
                // Delete the duplicate lecturer record
                $delete_duplicate = $pdo->prepare("DELETE FROM lecturers WHERE id = ?");
                $delete_duplicate->execute([$duplicate['id']]);
                
                echo "<p class='success'>✅ Removed duplicate lecturer record ID: {$duplicate['id']}</p>";
            }
            
            // Step 3: Ensure user has correct role
            echo "<h3>Step 3: Ensuring Correct User Role</h3>";
            
            $update_user_role = $pdo->prepare("UPDATE users SET role = 'hod' WHERE id = 63");
            $update_user_role->execute();
            
            echo "<p class='success'>✅ Set user role to 'hod' for user ID 63</p>";
            
            $pdo->commit();
            
            echo "<p class='success'>🎉 Successfully fixed duplicate lecturer records!</p>";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<p class='error'>❌ Error during fix: " . $e->getMessage() . "</p>";
            throw $e;
        }
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🔍 Verification</h2>";
    
    // Test the HOD validation query
    echo "<h3>Testing HOD Validation Query:</h3>";
    
    $test_hod_query = $pdo->prepare("
        SELECT d.name as department_name, d.id as department_id
        FROM departments d
        JOIN lecturers l ON d.hod_id = l.id
        JOIN users u ON l.user_id = u.id
        WHERE u.id = 63 AND u.role = 'hod'
    ");
    $test_hod_query->execute();
    $hod_result = $test_hod_query->fetch(PDO::FETCH_ASSOC);
    
    if ($hod_result) {
        echo "<p class='success'>🎉 SUCCESS! HOD validation query now works!</p>";
        echo "<p><strong>User ID 63 (abayo.iradukunda) is HOD of:</strong> {$hod_result['department_name']} (ID: {$hod_result['department_id']})</p>";
    } else {
        echo "<p class='error'>❌ HOD validation still failing. Let's debug...</p>";
        
        // Debug step by step
        echo "<h4>Debug Information:</h4>";
        
        // Check user
        $user_check = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = 63");
        $user_check->execute();
        $user = $user_check->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>User:</strong> " . json_encode($user) . "</p>";
        
        // Check lecturer
        $lecturer_check = $pdo->prepare("SELECT id, user_id, department_id FROM lecturers WHERE user_id = 63");
        $lecturer_check->execute();
        $lecturer = $lecturer_check->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Lecturer:</strong> " . json_encode($lecturer) . "</p>";
        
        // Check department
        if ($lecturer) {
            $dept_check = $pdo->prepare("SELECT id, name, hod_id FROM departments WHERE hod_id = ?");
            $dept_check->execute([$lecturer['id']]);
            $dept = $dept_check->fetch(PDO::FETCH_ASSOC);
            echo "<p><strong>Department:</strong> " . json_encode($dept) . "</p>";
        }
    }
    
    // Show final state
    echo "<h3>Final State Check:</h3>";
    $final_check = $pdo->query("
        SELECT d.id, d.name, d.hod_id, u.username, l.id as lecturer_id
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON l.user_id = u.id
        WHERE d.hod_id IS NOT NULL
    ");
    $final_results = $final_check->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Dept ID</th><th>Department</th><th>hod_id</th><th>Username</th><th>Lecturer ID</th></tr>";
    foreach ($final_results as $result) {
        echo "<tr>";
        echo "<td>{$result['id']}</td>";
        echo "<td>{$result['name']}</td>";
        echo "<td>{$result['hod_id']}</td>";
        echo "<td>{$result['username']}</td>";
        echo "<td>{$result['lecturer_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🚀 Next Steps</h2>";
    echo "<ol>";
    echo "<li><strong>Test Login:</strong> Try logging in as abayo@gmail.com with role 'Head of Department'</li>";
    echo "<li><strong>Expected Result:</strong> Should access HOD dashboard without error</li>";
    echo "<li><strong>Department:</strong> Should show as HOD of Mechanical Engineering</li>";
    echo "</ol>";
    
    echo "<p><a href='login.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test HOD Login Now</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<p class='error'>❌ Error occurred: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Duplicate lecturer fix completed!</em></p>";
?>
