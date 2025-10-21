<?php
/**
 * Fix HOD Schema Mismatch
 * The departments.hod_id should point to lecturers.id, not users.id
 * This script will fix the data inconsistency
 */

require_once "config.php";

echo "<h1>🔧 Fixing HOD Schema Mismatch</h1>";
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
    echo "<h2>📊 Current Schema Analysis</h2>";
    
    // Check current departments with hod_id
    echo "<h3>Current Department HOD Assignments:</h3>";
    $current_assignments = $pdo->query("
        SELECT d.id, d.name, d.hod_id,
               u.username, u.email, u.role as user_role,
               l.id as lecturer_id
        FROM departments d
        LEFT JOIN users u ON d.hod_id = u.id
        LEFT JOIN lecturers l ON l.user_id = u.id
        WHERE d.hod_id IS NOT NULL
        ORDER BY d.id
    ");
    $assignments = $current_assignments->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($assignments)) {
        echo "<p class='info'>No HOD assignments found.</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Dept ID</th><th>Department</th><th>Current hod_id</th><th>Points to User</th><th>User Role</th><th>Lecturer ID</th><th>Status</th></tr>";
        
        foreach ($assignments as $assignment) {
            $status = 'Unknown';
            $status_class = 'warning';
            
            if ($assignment['username']) {
                if ($assignment['lecturer_id']) {
                    $status = 'Valid (User has Lecturer record)';
                    $status_class = 'success';
                } else {
                    $status = 'Invalid (User has no Lecturer record)';
                    $status_class = 'error';
                }
            } else {
                $status = 'Invalid (User not found)';
                $status_class = 'error';
            }
            
            echo "<tr>";
            echo "<td>{$assignment['id']}</td>";
            echo "<td>{$assignment['name']}</td>";
            echo "<td>{$assignment['hod_id']}</td>";
            echo "<td>" . ($assignment['username'] ?: 'Not Found') . "</td>";
            echo "<td>" . ($assignment['user_role'] ?: 'N/A') . "</td>";
            echo "<td>" . ($assignment['lecturer_id'] ?: 'N/A') . "</td>";
            echo "<td class='$status_class'>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🔧 Schema Fix Options</h2>";
    
    echo "<p>The system expects <code>departments.hod_id</code> to point to <code>lecturers.id</code>, but your data has it pointing to <code>users.id</code>.</p>";
    echo "<p>We have two options:</p>";
    
    echo "<h3>Option 1: Fix the Data (Recommended)</h3>";
    echo "<p>Convert <code>departments.hod_id</code> from <code>users.id</code> to <code>lecturers.id</code></p>";
    
    echo "<h3>Option 2: Fix the Queries</h3>";
    echo "<p>Update all HOD validation queries to work with <code>users.id</code> directly</p>";
    
    echo "<p><strong>Recommendation:</strong> Option 1 is better because it maintains the proper relational structure.</p>";
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🚀 Applying Fix (Option 1)</h2>";
    
    $pdo->beginTransaction();
    
    try {
        // Step 1: Update departments.hod_id from users.id to lecturers.id
        echo "<h3>Step 1: Converting hod_id values</h3>";
        
        $conversion_count = 0;
        foreach ($assignments as $assignment) {
            if ($assignment['lecturer_id']) {
                // Update departments.hod_id to point to lecturers.id instead of users.id
                $update_stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
                $update_stmt->execute([$assignment['lecturer_id'], $assignment['id']]);
                
                echo "<p class='success'>✅ Updated {$assignment['name']}: hod_id changed from {$assignment['hod_id']} (user) to {$assignment['lecturer_id']} (lecturer)</p>";
                $conversion_count++;
            } else {
                // User exists but no lecturer record - need to create one or set to NULL
                echo "<p class='error'>❌ {$assignment['name']}: User {$assignment['hod_id']} has no lecturer record</p>";
                
                // Set to NULL for now - admin will need to reassign properly
                $update_stmt = $pdo->prepare("UPDATE departments SET hod_id = NULL WHERE id = ?");
                $update_stmt->execute([$assignment['id']]);
                echo "<p class='warning'>⚠️ Set {$assignment['name']} hod_id to NULL - needs manual reassignment</p>";
            }
        }
        
        $pdo->commit();
        
        echo "<p class='success'>🎉 Successfully converted $conversion_count HOD assignments!</p>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<p class='error'>❌ Error during conversion: " . $e->getMessage() . "</p>";
        throw $e;
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🔍 Verification</h2>";
    
    // Verify the fix worked
    echo "<h3>Testing HOD Validation Query:</h3>";
    
    // Test with user ID 63 (from your data)
    $test_user_id = 63;
    $test_stmt = $pdo->prepare("
        SELECT d.name as department_name, d.id as department_id
        FROM departments d
        JOIN lecturers l ON d.hod_id = l.id
        JOIN users u ON l.user_id = u.id
        WHERE u.id = ? AND u.role = 'hod'
    ");
    $test_stmt->execute([$test_user_id]);
    $test_result = $test_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_result) {
        echo "<p class='success'>✅ HOD validation query now works!</p>";
        echo "<p><strong>User ID 63 is HOD of:</strong> {$test_result['department_name']} (ID: {$test_result['department_id']})</p>";
    } else {
        echo "<p class='warning'>⚠️ User ID 63 still not found as HOD. Checking user details...</p>";
        
        // Check user details
        $user_check = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
        $user_check->execute([$test_user_id]);
        $user_details = $user_check->fetch(PDO::FETCH_ASSOC);
        
        if ($user_details) {
            echo "<p>User details: " . json_encode($user_details) . "</p>";
            
            // Check if user has lecturer record
            $lecturer_check = $pdo->prepare("SELECT id, department_id FROM lecturers WHERE user_id = ?");
            $lecturer_check->execute([$test_user_id]);
            $lecturer_details = $lecturer_check->fetch(PDO::FETCH_ASSOC);
            
            if ($lecturer_details) {
                echo "<p>Lecturer details: " . json_encode($lecturer_details) . "</p>";
                
                // Check if any department points to this lecturer
                $dept_check = $pdo->prepare("SELECT id, name FROM departments WHERE hod_id = ?");
                $dept_check->execute([$lecturer_details['id']]);
                $dept_details = $dept_check->fetch(PDO::FETCH_ASSOC);
                
                if ($dept_details) {
                    echo "<p class='success'>✅ Found department assignment: " . json_encode($dept_details) . "</p>";
                } else {
                    echo "<p class='warning'>⚠️ No department points to lecturer ID {$lecturer_details['id']}</p>";
                }
            } else {
                echo "<p class='error'>❌ User has no lecturer record</p>";
            }
        } else {
            echo "<p class='error'>❌ User ID 63 not found</p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>📋 Next Steps</h2>";
    echo "<ol>";
    echo "<li><strong>Test Login:</strong> Try logging in as abayo@gmail.com with role 'Head of Department'</li>";
    echo "<li><strong>Check HOD Dashboard:</strong> The error message should be gone</li>";
    echo "<li><strong>Reassign if needed:</strong> Use the HOD assignment interface to properly assign HODs</li>";
    echo "<li><strong>Verify Foreign Keys:</strong> Make sure foreign key constraints are correct</li>";
    echo "</ol>";
    
    echo "<p><a href='login.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test HOD Login</a></p>";
    echo "<p><a href='assign-hod.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>HOD Assignment Interface</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<p class='error'>❌ Error occurred: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Schema fix completed!</em></p>";
?>
