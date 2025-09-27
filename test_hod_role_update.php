<?php
/**
 * Test script to verify HOD role update functionality
 */

require_once "config.php";

// Test database connection
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== HOD Role Update Test ===\n\n";

    // 1. Check current state
    echo "1. Current State:\n";
    $stmt = $pdo->query("
        SELECT d.name as dept_name, d.hod_id, l.first_name, l.last_name, l.role as lecturer_role, u.role as user_role
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON d.hod_id = u.id
        ORDER BY d.name
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($results as $row) {
        echo "Department: {$row['dept_name']}\n";
        echo "  HOD ID: " . ($row['hod_id'] ?: 'None') . "\n";
        echo "  Lecturer: " . ($row['first_name'] && $row['last_name'] ? "{$row['first_name']} {$row['last_name']}" : 'None') . "\n";
        echo "  Lecturer Role: " . ($row['lecturer_role'] ?: 'N/A') . "\n";
        echo "  User Role: " . ($row['user_role'] ?: 'N/A') . "\n";
        echo "\n";
    }

    // 2. Test role update logic
    echo "2. Testing Role Update Logic:\n";

    // Check if there are any inconsistencies
    $inconsistencies = [];
    foreach ($results as $row) {
        if ($row['hod_id']) {
            // If there's a HOD assigned
            if (!$row['lecturer_role']) {
                $inconsistencies[] = "Department {$row['dept_name']}: HOD assigned but no lecturer record";
            } elseif ($row['lecturer_role'] !== 'hod') {
                $inconsistencies[] = "Department {$row['dept_name']}: HOD assigned but lecturer role is '{$row['lecturer_role']}' not 'hod'";
            }
            if (!$row['user_role']) {
                $inconsistencies[] = "Department {$row['dept_name']}: HOD assigned but no user account";
            } elseif ($row['user_role'] !== 'hod') {
                $inconsistencies[] = "Department {$row['dept_name']}: HOD assigned but user role is '{$row['user_role']}' not 'hod'";
            }
        } else {
            // If no HOD assigned, check for orphaned HOD roles
            if ($row['lecturer_role'] === 'hod') {
                $inconsistencies[] = "Department {$row['dept_name']}: No HOD assigned but lecturer role is 'hod'";
            }
        }
    }

    if (empty($inconsistencies)) {
        echo "✅ No inconsistencies found!\n";
    } else {
        echo "❌ Found inconsistencies:\n";
        foreach ($inconsistencies as $issue) {
            echo "  - $issue\n";
        }
    }

    echo "\n3. Summary:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
    $total_depts = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM departments WHERE hod_id IS NOT NULL");
    $assigned_depts = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM lecturers WHERE role = 'hod'");
    $hod_lecturers = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'hod'");
    $hod_users = $stmt->fetchColumn();

    echo "Total Departments: $total_depts\n";
    echo "Assigned Departments: $assigned_depts\n";
    echo "Lecturers with HOD role: $hod_lecturers\n";
    echo "Users with HOD role: $hod_users\n";

    if ($assigned_depts === $hod_lecturers && $hod_lecturers === $hod_users) {
        echo "✅ Role counts are consistent!\n";
    } else {
        echo "❌ Role counts are inconsistent!\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>