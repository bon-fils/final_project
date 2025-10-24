<?php
require_once "config.php";
session_start();

echo "<h2>HOD Assignment Debug</h2>";

if (!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ No user logged in. Please login first.</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
    exit;
}

$user_id = $_SESSION['user_id'];
echo "<h3>Current Session Info:</h3>";
echo "<ul>";
echo "<li><strong>User ID:</strong> " . $user_id . "</li>";
echo "<li><strong>Username:</strong> " . ($_SESSION['username'] ?? 'Not set') . "</li>";
echo "<li><strong>Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</li>";
echo "</ul>";

try {
    // Check user details
    echo "<h3>1. User Details:</h3>";
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        foreach ($user as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ User not found in database!</p>";
        exit;
    }
    
    // Check lecturer record
    echo "<h3>2. Lecturer Record:</h3>";
    $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($lecturer) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        foreach ($lecturer as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
        $lecturer_id = $lecturer['id'];
    } else {
        echo "<p style='color: red;'>❌ No lecturer record found for this user!</p>";
        echo "<p><strong>Solution:</strong> This user needs to be registered as a lecturer first.</p>";
        exit;
    }
    
    // Check department assignment
    echo "<h3>3. Department Assignment Check:</h3>";
    
    // Method 1: Check if this lecturer is assigned as HOD
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.hod_id, d.status
        FROM departments d 
        WHERE d.hod_id = ?
    ");
    $stmt->execute([$lecturer_id]);
    $dept_by_lecturer_id = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dept_by_lecturer_id) {
        echo "<p style='color: green;'>✅ <strong>Method 1 (Correct):</strong> Found department assignment by lecturer ID</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        foreach ($dept_by_lecturer_id as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ <strong>Method 1:</strong> No department found where hod_id = lecturer.id ($lecturer_id)</p>";
    }
    
    // Method 2: Check if hod_id points to user_id (legacy/incorrect)
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.hod_id, d.status
        FROM departments d 
        WHERE d.hod_id = ?
    ");
    $stmt->execute([$user_id]);
    $dept_by_user_id = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dept_by_user_id) {
        echo "<p style='color: orange;'>⚠️ <strong>Method 2 (Legacy):</strong> Found department assignment by user ID (should be lecturer ID)</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        foreach ($dept_by_user_id as $key => $value) {
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ <strong>Method 2:</strong> No department found where hod_id = user.id ($user_id)</p>";
    }
    
    // Show all departments and their HOD assignments
    echo "<h3>4. All Departments and HOD Assignments:</h3>";
    $stmt = $pdo->query("
        SELECT 
            d.id as dept_id,
            d.name as dept_name,
            d.hod_id,
            d.status as dept_status,
            l.id as lecturer_id,
            l.user_id as lecturer_user_id,
            u.username,
            u.first_name,
            u.last_name,
            u.role
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY d.id
    ");
    $all_depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Dept ID</th><th>Department</th><th>HOD ID</th><th>Lecturer ID</th><th>User ID</th><th>HOD Name</th><th>Username</th><th>Role</th><th>Status</th></tr>";
    foreach ($all_depts as $dept) {
        $highlight = ($dept['lecturer_user_id'] == $user_id) ? 'background-color: #d4edda;' : '';
        echo "<tr style='$highlight'>";
        echo "<td>" . $dept['dept_id'] . "</td>";
        echo "<td>" . htmlspecialchars($dept['dept_name']) . "</td>";
        echo "<td>" . ($dept['hod_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($dept['lecturer_id'] ?? 'NULL') . "</td>";
        echo "<td>" . ($dept['lecturer_user_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars(($dept['first_name'] ?? '') . ' ' . ($dept['last_name'] ?? '')) . "</td>";
        echo "<td>" . htmlspecialchars($dept['username'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($dept['role'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($dept['dept_status'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Provide solutions
    echo "<h3>5. Solutions:</h3>";
    
    if (!$dept_by_lecturer_id && !$dept_by_user_id) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
        echo "<h4>🔧 Fix Required: Assign HOD to Department</h4>";
        echo "<p>This user is not assigned as HOD to any department. You need to:</p>";
        echo "<ol>";
        echo "<li>Choose which department this HOD should manage</li>";
        echo "<li>Update the department's hod_id to point to lecturer.id ($lecturer_id)</li>";
        echo "</ol>";
        
        echo "<h5>SQL Commands to fix:</h5>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
        echo "-- Example: Assign to Information Technology department (ID 7)\n";
        echo "UPDATE departments SET hod_id = $lecturer_id WHERE id = 7;\n\n";
        echo "-- Or assign to any other department by changing the WHERE clause\n";
        echo "-- First check available departments:\n";
        echo "SELECT id, name FROM departments WHERE hod_id IS NULL OR hod_id = 0;";
        echo "</pre>";
        echo "</div>";
    }
    
    if ($dept_by_user_id && !$dept_by_lecturer_id) {
        echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
        echo "<h4>🔧 Fix Required: Update HOD Assignment</h4>";
        echo "<p>The department hod_id is pointing to user.id instead of lecturer.id. Fix with:</p>";
        echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 3px;'>";
        echo "UPDATE departments SET hod_id = $lecturer_id WHERE hod_id = $user_id;";
        echo "</pre>";
        echo "</div>";
    }
    
    if ($dept_by_lecturer_id) {
        echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
        echo "<h4>✅ Assignment Looks Correct</h4>";
        echo "<p>The HOD assignment appears to be correct. If you're still seeing 'No Department Assigned', there might be an issue with the verifyHODAccess function.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>
