<?php
/**
 * Debug HOD Login Issues
 * Quick diagnostic tool to identify HOD login problems
 */

require_once "config.php";

echo "<h1>🔍 HOD Login Debug Tool</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .debug-box { background: #f5f5f5; padding: 15px; margin: 10px 0; border-left: 4px solid #007cba; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

// Get HOD user ID from URL parameter for testing
$test_user_id = $_GET['user_id'] ?? null;

if ($test_user_id) {
    echo "<div class='debug-box'>";
    echo "<h2>🧪 Testing HOD User ID: $test_user_id</h2>";
    
    try {
        // Test the exact logic from hod-dashboard.php
        echo "<h3>Step 1: Check if user exists and has HOD role</h3>";
        $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'hod'");
        $user_stmt->execute([$test_user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "<p class='error'>❌ User $test_user_id not found or not HOD role</p>";
            echo "</div>";
            return;
        }
        
        echo "<p class='success'>✅ User found: {$user['username']} ({$user['email']})</p>";
        
        echo "<h3>Step 2: Check if user has lecturer record</h3>";
        $lecturer_stmt = $pdo->prepare("SELECT id, first_name, last_name, department_id FROM lecturers WHERE user_id = ?");
        $lecturer_stmt->execute([$test_user_id]);
        $lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lecturer) {
            echo "<p class='error'>❌ No lecturer record found for user_id $test_user_id</p>";
            echo "<p class='info'>💡 This is likely the cause of the 'not_assigned' error</p>";
            
            // Check if there's a lecturer with matching email
            $email_lecturer_stmt = $pdo->prepare("SELECT id, first_name, last_name, department_id FROM lecturers WHERE email = ?");
            $email_lecturer_stmt->execute([$user['email']]);
            $email_lecturer = $email_lecturer_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($email_lecturer) {
                echo "<p class='warning'>⚠️ Found lecturer record with matching email but no user_id link</p>";
                echo "<p class='info'>Lecturer ID: {$email_lecturer['id']}, Name: {$email_lecturer['first_name']} {$email_lecturer['last_name']}</p>";
            }
            echo "</div>";
            return;
        }
        
        echo "<p class='success'>✅ Lecturer record found: ID {$lecturer['id']}, Name: {$lecturer['first_name']} {$lecturer['last_name']}</p>";
        
        echo "<h3>Step 3: Check if lecturer is assigned as HOD to any department</h3>";
        $dept_stmt = $pdo->prepare("
            SELECT d.id, d.name 
            FROM departments d 
            WHERE d.hod_id = ?
        ");
        $dept_stmt->execute([$lecturer['id']]);
        $department = $dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$department) {
            echo "<p class='error'>❌ Lecturer {$lecturer['id']} is not assigned as HOD to any department</p>";
            echo "<p class='info'>💡 This is the cause of the 'not_assigned' error</p>";
            
            // Show available departments
            $all_depts = $pdo->query("SELECT id, name, hod_id FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
            echo "<h4>Available Departments:</h4>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Name</th><th>Current HOD ID</th><th>Status</th></tr>";
            foreach ($all_depts as $dept) {
                $status = $dept['hod_id'] ? "Assigned to Lecturer {$dept['hod_id']}" : "No HOD assigned";
                echo "<tr>";
                echo "<td>{$dept['id']}</td>";
                echo "<td>{$dept['name']}</td>";
                echo "<td>" . ($dept['hod_id'] ?: 'None') . "</td>";
                echo "<td>$status</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "</div>";
            return;
        }
        
        echo "<p class='success'>✅ Lecturer is assigned as HOD to: {$department['name']} (ID: {$department['id']})</p>";
        echo "<p class='success'>🎉 All checks passed! HOD login should work for this user.</p>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error during testing: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
} else {
    echo "<h2>📋 Quick Diagnosis</h2>";
    
    try {
        // Show all HOD users and their status
        echo "<h3>All HOD Users in System:</h3>";
        $all_hods = $pdo->query("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.status,
                   l.id as lecturer_id, l.first_name as lec_fname, l.last_name as lec_lname,
                   d.id as dept_id, d.name as dept_name
            FROM users u
            LEFT JOIN lecturers l ON l.user_id = u.id
            LEFT JOIN departments d ON d.hod_id = l.id
            WHERE u.role = 'hod'
            ORDER BY u.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($all_hods)) {
            echo "<p class='warning'>⚠️ No HOD users found in the system</p>";
        } else {
            echo "<table>";
            echo "<tr><th>User ID</th><th>Username</th><th>Email</th><th>Name</th><th>Status</th><th>Lecturer ID</th><th>Department</th><th>Can Login?</th><th>Test</th></tr>";
            foreach ($all_hods as $hod) {
                $can_login = ($hod['lecturer_id'] && $hod['dept_id']) ? 'Yes' : 'No';
                $can_login_class = $can_login === 'Yes' ? 'success' : 'error';
                
                echo "<tr>";
                echo "<td>{$hod['id']}</td>";
                echo "<td>{$hod['username']}</td>";
                echo "<td>{$hod['email']}</td>";
                echo "<td>{$hod['first_name']} {$hod['last_name']}</td>";
                echo "<td>{$hod['status']}</td>";
                echo "<td>" . ($hod['lecturer_id'] ?: 'None') . "</td>";
                echo "<td>" . ($hod['dept_name'] ?: 'Not assigned') . "</td>";
                echo "<td class='$can_login_class'>$can_login</td>";
                echo "<td><a href='?user_id={$hod['id']}'>Test Login</a></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
        echo "<h3>🔧 Quick Actions:</h3>";
        echo "<ul>";
        echo "<li><a href='fix_hod_assignments.php' style='color: #007cba;'>🔧 Run HOD Assignment Fix</a></li>";
        echo "<li><a href='assign-hod.php' style='color: #28a745;'>👥 Assign HODs to Departments</a></li>";
        echo "<li><a href='manage-users.php' style='color: #ffc107;'>👤 Manage Users</a></li>";
        echo "</ul>";
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ol>";
echo "<li>If 'Can Login?' shows 'No', click 'Test Login' to see detailed diagnosis</li>";
echo "<li>Run the HOD Assignment Fix to automatically resolve common issues</li>";
echo "<li>Use the Assign HODs page to manually assign lecturers to departments</li>";
echo "<li>Ensure HOD users have proper lecturer records and department assignments</li>";
echo "</ol>";
?>
