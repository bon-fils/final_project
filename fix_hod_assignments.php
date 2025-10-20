<?php
/**
 * Fix HOD Assignment Issues
 * This script helps resolve HOD login issues by ensuring proper relationships
 * between users, lecturers, and departments tables
 */

require_once "config.php";

echo "<h1>🔧 Fixing HOD Assignment Issues</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
</style>";

try {
    echo "<h2>📊 Current System Status</h2>";
    
    // Check HOD users
    echo "<h3>1. HOD Users in System</h3>";
    $hod_users_stmt = $pdo->query("
        SELECT id, username, email, first_name, last_name, status 
        FROM users 
        WHERE role = 'hod' 
        ORDER BY id
    ");
    $hod_users = $hod_users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($hod_users)) {
        echo "<p class='warning'>⚠️ No HOD users found in the system!</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Status</th></tr>";
        foreach ($hod_users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['first_name']} {$user['last_name']}</td>";
            echo "<td>{$user['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check lecturer records for HOD users
    echo "<h3>2. Lecturer Records for HOD Users</h3>";
    $lecturer_check_stmt = $pdo->query("
        SELECT u.id as user_id, u.username, u.email, l.id as lecturer_id, l.first_name, l.last_name, l.department_id
        FROM users u
        LEFT JOIN lecturers l ON (l.user_id = u.id OR l.email = u.email)
        WHERE u.role = 'hod'
        ORDER BY u.id
    ");
    $lecturer_records = $lecturer_check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>User ID</th><th>Username</th><th>Email</th><th>Lecturer ID</th><th>Lecturer Name</th><th>Dept ID</th><th>Status</th></tr>";
    foreach ($lecturer_records as $record) {
        $status = $record['lecturer_id'] ? 'Has Lecturer Record' : 'Missing Lecturer Record';
        $status_class = $record['lecturer_id'] ? 'success' : 'error';
        echo "<tr>";
        echo "<td>{$record['user_id']}</td>";
        echo "<td>{$record['username']}</td>";
        echo "<td>{$record['email']}</td>";
        echo "<td>" . ($record['lecturer_id'] ?: 'N/A') . "</td>";
        echo "<td>" . ($record['first_name'] ? $record['first_name'] . ' ' . $record['last_name'] : 'N/A') . "</td>";
        echo "<td>" . ($record['department_id'] ?: 'N/A') . "</td>";
        echo "<td class='$status_class'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check department HOD assignments
    echo "<h3>3. Department HOD Assignments</h3>";
    $dept_hod_stmt = $pdo->query("
        SELECT d.id, d.name, d.hod_id, l.first_name, l.last_name, l.email, u.id as user_id, u.username
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON (l.user_id = u.id OR l.email = u.email) AND u.role = 'hod'
        ORDER BY d.id
    ");
    $dept_assignments = $dept_hod_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>Dept ID</th><th>Department</th><th>HOD Lecturer ID</th><th>HOD Name</th><th>HOD Email</th><th>User ID</th><th>Username</th><th>Status</th></tr>";
    foreach ($dept_assignments as $dept) {
        $status = 'No HOD Assigned';
        $status_class = 'warning';
        
        if ($dept['hod_id']) {
            if ($dept['user_id']) {
                $status = 'Properly Assigned';
                $status_class = 'success';
            } else {
                $status = 'HOD Lecturer exists but no User link';
                $status_class = 'error';
            }
        }
        
        echo "<tr>";
        echo "<td>{$dept['id']}</td>";
        echo "<td>{$dept['name']}</td>";
        echo "<td>" . ($dept['hod_id'] ?: 'N/A') . "</td>";
        echo "<td>" . ($dept['first_name'] ? $dept['first_name'] . ' ' . $dept['last_name'] : 'N/A') . "</td>";
        echo "<td>" . ($dept['email'] ?: 'N/A') . "</td>";
        echo "<td>" . ($dept['user_id'] ?: 'N/A') . "</td>";
        echo "<td>" . ($dept['username'] ?: 'N/A') . "</td>";
        echo "<td class='$status_class'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>🔧 Automatic Fixes</h2>";
    
    // Fix 1: Create missing lecturer records for HOD users
    echo "<h3>Fix 1: Creating Missing Lecturer Records</h3>";
    $missing_lecturer_stmt = $pdo->query("
        SELECT u.id, u.username, u.email, u.first_name, u.last_name
        FROM users u
        LEFT JOIN lecturers l ON (l.user_id = u.id OR l.email = u.email)
        WHERE u.role = 'hod' AND l.id IS NULL
    ");
    $missing_lecturers = $missing_lecturer_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($missing_lecturers)) {
        echo "<p class='success'>✅ All HOD users have lecturer records</p>";
    } else {
        foreach ($missing_lecturers as $user) {
            try {
                $insert_lecturer = $pdo->prepare("
                    INSERT INTO lecturers (user_id, first_name, last_name, email, gender, dob, id_number, education_level, role, password)
                    VALUES (?, ?, ?, ?, 'Other', '1980-01-01', ?, 'Master\'s', 'hod', ?)
                ");
                
                $id_number = 'HOD' . str_pad($user['id'], 6, '0', STR_PAD_LEFT);
                $password = password_hash('password123', PASSWORD_DEFAULT); // Default password
                
                $insert_lecturer->execute([
                    $user['id'],
                    $user['first_name'] ?: 'HOD',
                    $user['last_name'] ?: 'User',
                    $user['email'],
                    $id_number,
                    $password
                ]);
                
                echo "<p class='success'>✅ Created lecturer record for HOD user: {$user['username']} (ID: {$user['id']})</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Failed to create lecturer record for {$user['username']}: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Fix 2: Update user_id links in lecturers table
    echo "<h3>Fix 2: Updating user_id Links</h3>";
    $unlinked_lecturers = $pdo->query("
        SELECT l.id, l.email, u.id as user_id, u.username
        FROM lecturers l
        JOIN users u ON l.email = u.email
        WHERE l.user_id IS NULL AND u.role IN ('hod', 'lecturer')
    ");
    $unlinked = $unlinked_lecturers->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($unlinked)) {
        echo "<p class='success'>✅ All lecturer records are properly linked to users</p>";
    } else {
        foreach ($unlinked as $lecturer) {
            try {
                $update_stmt = $pdo->prepare("UPDATE lecturers SET user_id = ? WHERE id = ?");
                $update_stmt->execute([$lecturer['user_id'], $lecturer['id']]);
                echo "<p class='success'>✅ Linked lecturer ID {$lecturer['id']} to user {$lecturer['username']} (ID: {$lecturer['user_id']})</p>";
            } catch (Exception $e) {
                echo "<p class='error'>❌ Failed to link lecturer {$lecturer['id']}: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "<h2>📋 Manual Actions Required</h2>";
    echo "<p class='info'>If you still have issues:</p>";
    echo "<ol>";
    echo "<li><strong>Assign HODs to Departments:</strong> Go to the HOD Assignment page and assign lecturers as HODs to departments</li>";
    echo "<li><strong>Verify Credentials:</strong> Make sure HOD users can log in with correct username/email and password</li>";
    echo "<li><strong>Check Role:</strong> Ensure users have role='hod' in the users table</li>";
    echo "<li><strong>Test Login:</strong> Try logging in as HOD after running this fix</li>";
    echo "</ol>";
    
    echo "<h2>🔄 Quick Test</h2>";
    echo "<p><a href='login_new.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test HOD Login</a></p>";
    echo "<p><a href='assign-hod.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Assign HODs to Departments</a></p>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p class='error'>Stack trace: " . $e->getTraceAsString() . "</p>";
}

echo "<hr>";
echo "<p><strong>Fix completed!</strong> Check the results above and take any manual actions if needed.</p>";
?>
