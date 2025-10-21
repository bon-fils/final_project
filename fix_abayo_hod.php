<?php
/**
 * Quick Fix for abayo@gmail.com HOD Issue
 * This script specifically fixes the HOD assignment for abayo@gmail.com
 */

require_once "config.php";

echo "<h1>🔧 Quick Fix for abayo@gmail.com HOD Issue</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .card { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>📊 Checking Current Status for abayo@gmail.com</h2>";
    
    // Check if user exists
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $user_stmt->execute(['abayo@gmail.com']);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p class='error'>❌ User abayo@gmail.com not found in users table!</p>";
        echo "<p class='info'>Creating user record...</p>";
        
        $create_user = $pdo->prepare("
            INSERT INTO users (username, email, password, role, status, first_name, last_name, created_at) 
            VALUES (?, ?, ?, 'hod', 'active', 'Abayo', 'User', NOW())
        ");
        $password_hash = password_hash('Welcome123!', PASSWORD_DEFAULT);
        $create_user->execute(['abayo', 'abayo@gmail.com', $password_hash]);
        
        $user_id = $pdo->lastInsertId();
        echo "<p class='success'>✅ Created user record for abayo@gmail.com (ID: $user_id)</p>";
        echo "<p class='warning'>⚠️ Default password set to: Welcome123!</p>";
    } else {
        $user_id = $user['id'];
        echo "<p class='success'>✅ User found: ID {$user['id']}, Role: {$user['role']}, Status: {$user['status']}</p>";
        
        // Update role to HOD if not already
        if ($user['role'] !== 'hod') {
            $update_role = $pdo->prepare("UPDATE users SET role = 'hod' WHERE id = ?");
            $update_role->execute([$user_id]);
            echo "<p class='success'>✅ Updated user role to HOD</p>";
        }
    }
    
    // Check lecturer record
    $lecturer_stmt = $pdo->prepare("SELECT * FROM lecturers WHERE user_id = ? OR email = ?");
    $lecturer_stmt->execute([$user_id, 'abayo@gmail.com']);
    $lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lecturer) {
        echo "<p class='error'>❌ No lecturer record found for abayo@gmail.com</p>";
        echo "<p class='info'>Creating lecturer record...</p>";
        
        $create_lecturer = $pdo->prepare("
            INSERT INTO lecturers (user_id, gender, dob, id_number, department_id, education_level, created_at, updated_at) 
            VALUES (?, 'Male', '2003-01-08', '5555555555773988', 7, 'Master\'s', NOW(), NOW())
        ");
        $create_lecturer->execute([$user_id]);
        
        $lecturer_id = $pdo->lastInsertId();
        echo "<p class='success'>✅ Created lecturer record (ID: $lecturer_id) linked to user ID $user_id</p>";
    } else {
        $lecturer_id = $lecturer['id'];
        echo "<p class='success'>✅ Lecturer record found: ID {$lecturer['id']}, Department: {$lecturer['department_id']}</p>";
        
        // Update user_id if missing
        if (!$lecturer['user_id']) {
            $update_lecturer = $pdo->prepare("UPDATE lecturers SET user_id = ? WHERE id = ?");
            $update_lecturer->execute([$user_id, $lecturer_id]);
            echo "<p class='success'>✅ Linked lecturer record to user ID $user_id</p>";
        }
    }
    
    // Check department assignment
    $dept_stmt = $pdo->prepare("SELECT * FROM departments WHERE hod_id = ?");
    $dept_stmt->execute([$lecturer_id]);
    $dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dept) {
        echo "<p class='warning'>⚠️ Lecturer is not assigned as HOD to any department</p>";
        echo "<p class='info'>Assigning to department ID 7...</p>";
        
        $assign_hod = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = 7");
        $assign_hod->execute([$lecturer_id]);
        echo "<p class='success'>✅ Assigned lecturer as HOD to department ID 7</p>";
    } else {
        echo "<p class='success'>✅ Already assigned as HOD to department: {$dept['name']} (ID: {$dept['id']})</p>";
    }
    
    echo "</div>";
    
    // Final verification
    echo "<div class='card'>";
    echo "<h2>🔍 Final Verification</h2>";
    
    $verify_stmt = $pdo->prepare("
        SELECT d.name as department_name, d.id as department_id, u.username, u.email, u.role
        FROM departments d
        JOIN lecturers l ON d.hod_id = l.id
        JOIN users u ON l.user_id = u.id
        WHERE u.email = ? AND u.role = 'hod'
    ");
    $verify_stmt->execute(['abayo@gmail.com']);
    $verification = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($verification) {
        echo "<p class='success'>🎉 SUCCESS! abayo@gmail.com can now log in as HOD</p>";
        echo "<p><strong>Department:</strong> {$verification['department_name']} (ID: {$verification['department_id']})</p>";
        echo "<p><strong>Username:</strong> {$verification['username']}</p>";
        echo "<p><strong>Email:</strong> {$verification['email']}</p>";
        echo "<p><strong>Role:</strong> {$verification['role']}</p>";
    } else {
        echo "<p class='error'>❌ Verification failed - there may still be an issue</p>";
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🚀 Next Steps</h2>";
    echo "<ol>";
    echo "<li><strong>Test Login:</strong> Try logging in with email 'abayo@gmail.com' and role 'Head of Department'</li>";
    echo "<li><strong>Password:</strong> Use the existing password or 'Welcome123!' if it was just created</li>";
    echo "<li><strong>Access HOD Dashboard:</strong> After login, you should be redirected to the HOD dashboard</li>";
    echo "</ol>";
    
    echo "<p><a href='login.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Login Now</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<p class='error'>❌ Error occurred: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Fix completed! Check the results above.</em></p>";
?>
