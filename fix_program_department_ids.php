<?php
/**
 * Fix program department IDs to match actual department IDs
 */

require_once 'config.php';

try {
    echo "<h1>Fixing Program Department IDs</h1>";

    // Get actual departments
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY id");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Actual Departments:</h3>";
    echo "<ul>";
    foreach ($departments as $dept) {
        echo "<li>ID {$dept['id']}: {$dept['name']}</li>";
    }
    echo "</ul>";

    // Map department names to correct IDs
    $deptMap = [];
    foreach ($departments as $dept) {
        $deptMap[$dept['name']] = $dept['id'];
    }

    // Programs that were added by my script (they have wrong department_ids)
    $programUpdates = [
        // Civil Engineering (should be ID 3)
        ['old_dept_id' => 1, 'new_dept_id' => 3, 'dept_name' => 'Civil Engineering'],
        // Creative Arts (should be ID 4)
        ['old_dept_id' => 2, 'new_dept_id' => 4, 'dept_name' => 'Creative Arts'],
        // Mechanical Engineering (should be ID 5)
        ['old_dept_id' => 3, 'new_dept_id' => 5, 'dept_name' => 'Mechanical Engineering'],
        // Electrical & Electronics Engineering (should be ID 6)
        ['old_dept_id' => 4, 'new_dept_id' => 6, 'dept_name' => 'Electrical & Electronics Engineering'],
        // Information & Communication Technology (should be ID 7)
        ['old_dept_id' => 5, 'new_dept_id' => 7, 'dept_name' => 'Information & Communication Technology'],
        // Mining Engineering (should be ID 8)
        ['old_dept_id' => 6, 'new_dept_id' => 8, 'dept_name' => 'Mining Engineering'],
        // Transport & Logistics (should be ID 9)
        ['old_dept_id' => 7, 'new_dept_id' => 9, 'dept_name' => 'Transport & Logistics'],
        // General Courses (should be ID 10)
        ['old_dept_id' => 8, 'new_dept_id' => 10, 'dept_name' => 'General Courses']
    ];

    echo "<h3>Updating Program Department IDs:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Department</th><th>Old ID</th><th>New ID</th><th>Programs Updated</th></tr>";

    foreach ($programUpdates as $update) {
        $oldId = $update['old_dept_id'];
        $newId = $update['new_dept_id'];
        $deptName = $update['dept_name'];

        // Update the department_id for programs
        $updateStmt = $pdo->prepare("UPDATE options SET department_id = ? WHERE department_id = ?");
        $updateStmt->execute([$newId, $oldId]);

        $affectedRows = $updateStmt->rowCount();

        echo "<tr>";
        echo "<td>{$deptName}</td>";
        echo "<td>{$oldId}</td>";
        echo "<td>{$newId}</td>";
        echo "<td>{$affectedRows}</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Show final mapping
    echo "<h3>Final Department-Program Mapping:</h3>";
    $finalStmt = $pdo->query("
        SELECT d.id, d.name, COUNT(o.id) as program_count
        FROM departments d
        LEFT JOIN options o ON d.id = o.department_id
        GROUP BY d.id, d.name
        ORDER BY d.id
    ");
    $finalMapping = $finalStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Dept ID</th><th>Department Name</th><th>Programs</th></tr>";
    foreach ($finalMapping as $map) {
        echo "<tr><td>{$map['id']}</td><td>{$map['name']}</td><td>{$map['program_count']}</td></tr>";
    }
    echo "</table>";

    echo "<h3 style='color: green;'>✅ Department IDs fixed successfully!</h3>";
    echo "<p>Program selection should now work correctly in the registration form.</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>