<?php
/**
 * Add sample programs/options for each department
 */

require_once 'config.php';

try {
    echo "<h1>Adding Sample Programs to Departments</h1>";

    // Get all departments
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($departments)) {
        echo "<p style='color: red;'>❌ No departments found. Please run setup_database.php first.</p>";
        exit;
    }

    echo "<p>Found " . count($departments) . " departments. Adding sample programs...</p>";

    // Sample programs for each department
    $programsByDepartment = [
        'Civil Engineering' => [
            'Civil Engineering',
            'Construction Engineering',
            'Structural Engineering',
            'Transportation Engineering',
            'Water Resources Engineering'
        ],
        'Creative Arts' => [
            'Graphic Design',
            'Fashion Design',
            'Fine Arts',
            'Digital Media',
            'Photography'
        ],
        'Mechanical Engineering' => [
            'Mechanical Engineering',
            'Automotive Engineering',
            'Manufacturing Engineering',
            'Robotics Engineering',
            'Energy Systems Engineering'
        ],
        'Electrical & Electronics Engineering' => [
            'Electrical Engineering',
            'Electronics Engineering',
            'Telecommunications Engineering',
            'Power Systems Engineering',
            'Control Systems Engineering'
        ],
        'Information & Communication Technology' => [
            'Software Engineering',
            'Computer Science',
            'Information Technology',
            'Cybersecurity',
            'Data Science'
        ],
        'Mining Engineering' => [
            'Mining Engineering',
            'Geological Engineering',
            'Mineral Processing',
            'Mine Safety Engineering',
            'Environmental Mining'
        ],
        'Transport & Logistics' => [
            'Transport Engineering',
            'Logistics Management',
            'Supply Chain Management',
            'Aviation Management',
            'Railway Engineering'
        ],
        'General Courses' => [
            'Business Administration',
            'Accounting',
            'Human Resource Management',
            'Marketing',
            'Entrepreneurship'
        ]
    ];

    $totalProgramsAdded = 0;

    foreach ($departments as $dept) {
        $deptName = $dept['name'];
        $deptId = $dept['id'];

        if (isset($programsByDepartment[$deptName])) {
            $programs = $programsByDepartment[$deptName];

            echo "<h3>Adding programs for: {$deptName}</h3>";
            echo "<ul>";

            foreach ($programs as $programName) {
                try {
                    // Check if program already exists
                    $checkStmt = $pdo->prepare("SELECT id FROM options WHERE name = ? AND department_id = ?");
                    $checkStmt->execute([$programName, $deptId]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing) {
                        echo "<li style='color: orange;'>⚠️ {$programName} already exists</li>";
                        continue;
                    }

                    // Insert new program
                    $insertStmt = $pdo->prepare("INSERT INTO options (name, department_id, status) VALUES (?, ?, 'active')");
                    $insertStmt->execute([$programName, $deptId]);

                    echo "<li style='color: green;'>✅ Added: {$programName}</li>";
                    $totalProgramsAdded++;

                } catch (Exception $e) {
                    echo "<li style='color: red;'>❌ Error adding {$programName}: " . $e->getMessage() . "</li>";
                }
            }

            echo "</ul>";
        } else {
            echo "<p style='color: orange;'>⚠️ No sample programs defined for department: {$deptName}</p>";
        }
    }

    echo "<hr>";
    echo "<h2 style='color: green;'>✅ Summary</h2>";
    echo "<p>Total programs added: <strong>{$totalProgramsAdded}</strong></p>";

    // Show final count
    $finalStmt = $pdo->query("SELECT COUNT(*) as total FROM options");
    $total = $finalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Total programs in database: <strong>{$total}</strong></p>";

    // Show programs by department
    echo "<h3>Programs by Department:</h3>";
    $summaryStmt = $pdo->query("
        SELECT d.name as department, COUNT(o.id) as program_count
        FROM departments d
        LEFT JOIN options o ON d.id = o.department_id
        GROUP BY d.id, d.name
        ORDER BY d.name
    ");
    $summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; margin-top: 20px;'>";
    echo "<tr><th style='padding: 8px;'>Department</th><th style='padding: 8px;'>Programs</th></tr>";
    foreach ($summary as $row) {
        echo "<tr><td style='padding: 8px;'>{$row['department']}</td><td style='padding: 8px;'>{$row['program_count']}</td></tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>