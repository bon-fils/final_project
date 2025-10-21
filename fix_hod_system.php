<?php
/**
 * Comprehensive HOD System Fix
 * This script resolves all HOD assignment issues and ensures proper system setup
 */

require_once "config.php";

// Set content type and disable output buffering for real-time feedback
header('Content-Type: text/html; charset=UTF-8');
ob_implicit_flush(true);
ob_end_flush();

echo "<!DOCTYPE html>
<html>
<head>
    <title>HOD System Fix</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 5px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 10px; border-radius: 5px; margin: 5px 0; }
        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .btn { padding: 10px 20px; margin: 5px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .step { border: 1px solid #dee2e6; padding: 20px; margin: 15px 0; border-radius: 8px; }
        .step h3 { margin-top: 0; color: #495057; }
        .progress { background: #e9ecef; height: 20px; border-radius: 10px; margin: 10px 0; }
        .progress-bar { background: #007bff; height: 100%; border-radius: 10px; transition: width 0.3s; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 HOD System Comprehensive Fix</h1>";
echo "<p>This tool will diagnose and fix all HOD-related issues in your system.</p>";

$fixes_applied = 0;
$total_steps = 6;

try {
    // Step 1: Analyze Current System
    echo "<div class='step'>";
    echo "<h3>Step 1: System Analysis</h3>";
    echo "<div class='progress'><div class='progress-bar' style='width: " . (1/$total_steps*100) . "%'></div></div>";
    
    // Check HOD users
    $hod_users_stmt = $pdo->query("
        SELECT id, username, email, first_name, last_name, status 
        FROM users 
        WHERE role = 'hod' 
        ORDER BY id
    ");
    $hod_users = $hod_users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>Found " . count($hod_users) . " HOD users in the system</div>";
    
    if (!empty($hod_users)) {
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
    echo "</div>";
    
    // Step 2: Check Lecturer Records
    echo "<div class='step'>";
    echo "<h3>Step 2: Lecturer Records Analysis</h3>";
    echo "<div class='progress'><div class='progress-bar' style='width: " . (2/$total_steps*100) . "%'></div></div>";
    
    $lecturer_check_stmt = $pdo->query("
        SELECT l.id, l.first_name, l.last_name, l.email, l.user_id, l.department_id,
               u.id as user_exists, u.role as user_role
        FROM lecturers l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE u.role = 'hod' OR l.user_id IS NULL
        ORDER BY l.id
    ");
    $lecturers = $lecturer_check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>Found " . count($lecturers) . " lecturer records related to HODs</div>";
    
    if (!empty($lecturers)) {
        echo "<table>";
        echo "<tr><th>Lecturer ID</th><th>Name</th><th>Email</th><th>User ID</th><th>User Role</th><th>Status</th></tr>";
        foreach ($lecturers as $lecturer) {
            $status = $lecturer['user_exists'] ? 
                ($lecturer['user_role'] === 'hod' ? 'Linked to HOD' : 'Linked to ' . $lecturer['user_role']) : 
                'No User Link';
            echo "<tr>";
            echo "<td>{$lecturer['id']}</td>";
            echo "<td>{$lecturer['first_name']} {$lecturer['last_name']}</td>";
            echo "<td>{$lecturer['email']}</td>";
            echo "<td>" . ($lecturer['user_id'] ?: 'NULL') . "</td>";
            echo "<td>" . ($lecturer['user_role'] ?: 'N/A') . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Step 3: Check Department Assignments
    echo "<div class='step'>";
    echo "<h3>Step 3: Department HOD Assignments</h3>";
    echo "<div class='progress'><div class='progress-bar' style='width: " . (3/$total_steps*100) . "%'></div></div>";
    
    $dept_check_stmt = $pdo->query("
        SELECT d.id, d.name, d.hod_id, 
               l.first_name as hod_fname, l.last_name as hod_lname, l.email as hod_email,
               u.id as user_id, u.username, u.role
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY d.id
    ");
    $departments = $dept_check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>Found " . count($departments) . " departments</div>";
    
    if (!empty($departments)) {
        echo "<table>";
        echo "<tr><th>Dept ID</th><th>Department</th><th>HOD ID</th><th>HOD Name</th><th>HOD Email</th><th>User ID</th><th>Status</th></tr>";
        foreach ($departments as $dept) {
            $status = 'No HOD Assigned';
            if ($dept['hod_id']) {
                if ($dept['user_id'] && $dept['role'] === 'hod') {
                    $status = 'Properly Assigned';
                } else {
                    $status = 'Assignment Issue';
                }
            }
            echo "<tr>";
            echo "<td>{$dept['id']}</td>";
            echo "<td>{$dept['name']}</td>";
            echo "<td>" . ($dept['hod_id'] ?: 'NULL') . "</td>";
            echo "<td>" . ($dept['hod_fname'] ? $dept['hod_fname'] . ' ' . $dept['hod_lname'] : 'N/A') . "</td>";
            echo "<td>" . ($dept['hod_email'] ?: 'N/A') . "</td>";
            echo "<td>" . ($dept['user_id'] ?: 'NULL') . "</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    echo "</div>";
    
    // Step 4: Fix Missing Lecturer Records
    echo "<div class='step'>";
    echo "<h3>Step 4: Creating Missing Lecturer Records</h3>";
    echo "<div class='progress'><div class='progress-bar' style='width: " . (4/$total_steps*100) . "%'></div></div>";
    
    foreach ($hod_users as $hod_user) {
        // Check if this HOD user has a lecturer record
        $check_lecturer = $pdo->prepare("SELECT id FROM lecturers WHERE user_id = ? OR email = ?");
        $check_lecturer->execute([$hod_user['id'], $hod_user['email']]);
        $existing_lecturer = $check_lecturer->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_lecturer) {
            // Create lecturer record for this HOD user
            $create_lecturer = $pdo->prepare("
                INSERT INTO lecturers (first_name, last_name, email, user_id, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $create_lecturer->execute([
                $hod_user['first_name'],
                $hod_user['last_name'], 
                $hod_user['email'],
                $hod_user['id']
            ]);
            
            echo "<div class='success'>✅ Created lecturer record for HOD: {$hod_user['first_name']} {$hod_user['last_name']} ({$hod_user['email']})</div>";
            $fixes_applied++;
        } else {
            // Update existing lecturer record to link with user
            $update_lecturer = $pdo->prepare("UPDATE lecturers SET user_id = ? WHERE id = ? AND user_id IS NULL");
            $result = $update_lecturer->execute([$hod_user['id'], $existing_lecturer['id']]);
            
            if ($update_lecturer->rowCount() > 0) {
                echo "<div class='success'>✅ Linked existing lecturer record to HOD user: {$hod_user['email']}</div>";
                $fixes_applied++;
            }
        }
    }
    echo "</div>";
    
    // Step 5: Fix Email Mismatches
    echo "<div class='step'>";
    echo "<h3>Step 5: Fixing Email Mismatches</h3>";
    echo "<div class='progress'><div class='progress-bar' style='width: " . (5/$total_steps*100) . "%'></div></div>";
    
    $mismatch_fix = $pdo->prepare("
        UPDATE lecturers l
        JOIN users u ON l.user_id = u.id
        SET l.email = u.email
        WHERE l.email != u.email AND u.role = 'hod'
    ");
    $mismatch_fix->execute();
    
    if ($mismatch_fix->rowCount() > 0) {
        echo "<div class='success'>✅ Fixed {$mismatch_fix->rowCount()} email mismatches between users and lecturers</div>";
        $fixes_applied++;
    } else {
        echo "<div class='info'>ℹ️ No email mismatches found</div>";
    }
    echo "</div>";
    
    // Step 6: Summary and Next Steps
    echo "<div class='step'>";
    echo "<h3>Step 6: Summary and Next Steps</h3>";
    echo "<div class='progress'><div class='progress-bar' style='width: 100%'></div></div>";
    
    echo "<div class='success'><strong>✅ System Analysis Complete!</strong></div>";
    echo "<div class='info'>Applied $fixes_applied automatic fixes</div>";
    
    // Check current status after fixes
    $final_check = $pdo->query("
        SELECT COUNT(*) as hod_count,
               COUNT(CASE WHEN l.id IS NOT NULL THEN 1 END) as with_lecturer,
               COUNT(CASE WHEN d.hod_id IS NOT NULL THEN 1 END) as assigned_to_dept
        FROM users u
        LEFT JOIN lecturers l ON u.id = l.user_id
        LEFT JOIN departments d ON l.id = d.hod_id
        WHERE u.role = 'hod'
    ");
    $status = $final_check->fetch(PDO::FETCH_ASSOC);
    
    echo "<h4>Current System Status:</h4>";
    echo "<ul>";
    echo "<li><strong>Total HOD Users:</strong> {$status['hod_count']}</li>";
    echo "<li><strong>HODs with Lecturer Records:</strong> {$status['with_lecturer']}</li>";
    echo "<li><strong>HODs Assigned to Departments:</strong> {$status['assigned_to_dept']}</li>";
    echo "</ul>";
    
    if ($status['hod_count'] > $status['with_lecturer']) {
        echo "<div class='warning'>⚠️ Some HOD users still don't have lecturer records. This should be resolved automatically.</div>";
    }
    
    if ($status['with_lecturer'] > $status['assigned_to_dept']) {
        echo "<div class='warning'>⚠️ Some HODs are not assigned to departments. Use the assignment tool below.</div>";
        echo "<a href='assign-hod.php' class='btn btn-primary'>🎯 Assign HODs to Departments</a>";
    }
    
    if ($status['hod_count'] === $status['with_lecturer'] && $status['with_lecturer'] === $status['assigned_to_dept']) {
        echo "<div class='success'>🎉 All HODs are properly configured!</div>";
    }
    
    echo "<h4>Quick Actions:</h4>";
    echo "<a href='hod-dashboard.php' class='btn btn-success'>🏠 Test HOD Login</a>";
    echo "<a href='assign-hod.php' class='btn btn-primary'>👥 Manage HOD Assignments</a>";
    echo "<a href='debug_hod_login.php' class='btn btn-warning'>🔍 Debug Specific User</a>";
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'>Please check your database connection and try again.</div>";
}

echo "</div></body></html>";
?>
