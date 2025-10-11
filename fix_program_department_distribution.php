<?php
/**
 * Properly distribute programs across all departments based on logical groupings
 */

require_once 'config.php';

try {
    echo "<h1>Fixing Program Department Distribution</h1>";

    // Get all departments
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY id");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Departments:</h3>";
    echo "<ul>";
    foreach ($departments as $dept) {
        echo "<li>ID {$dept['id']}: {$dept['name']}</li>";
    }
    echo "</ul>";

    // Define logical program groupings for each department
    $programGroups = [
        3 => [ // Civil Engineering
            'Civil Engineering', 'Construction Engineering', 'Structural Engineering',
            'Transportation Engineering', 'Water Resources Engineering', 'Highway Technology',
            'Construction Technology', 'Land Surveying', 'Geomatics', 'Quantity Surveying'
        ],
        4 => [ // Creative Arts
            'Graphic Design', 'Fashion Design', 'Fine Arts', 'Digital Media',
            'Photography', 'Film Making & TV Production', 'Graphic Design & Animation',
            'Visual Arts', 'Performing Arts'
        ],
        5 => [ // Mechanical Engineering
            'Mechanical Engineering', 'Automotive Engineering', 'Manufacturing Engineering',
            'Robotics Engineering', 'Energy Systems Engineering', 'Automobile Technology',
            'Manufacturing Technology', 'Mechatronics Technology', 'Air Conditioning & Refrigeration Technology'
        ],
        6 => [ // Electrical & Electronics Engineering
            'Electrical Engineering', 'Electronics Engineering', 'Telecommunications Engineering',
            'Power Systems Engineering', 'Control Systems Engineering', 'Electrical Technology',
            'Electronics & Telecommunication Technology', 'Biomedical Equipment Technology'
        ],
        7 => [ // Information & Communication Technology
            'Information Technology', 'Computer Science', 'Cybersecurity', 'Data Science',
            'Software Engineering'
        ],
        8 => [ // Mining Engineering
            'Mining Engineering', 'Geological Engineering', 'Mineral Processing',
            'Mine Safety Engineering', 'Environmental Mining', 'Mining Technology',
            'Mineral Processing Technology', 'Mining Safety & Environment'
        ],
        9 => [ // Transport & Logistics
            'Transport Engineering', 'Logistics Management', 'Supply Chain Management',
            'Aviation Management', 'Railway Engineering', 'Transport Management',
            'Logistics & Supply Chain Management', 'Fleet Management', 'Civil Engineering Technology',
            'Water Engineering Technology'
        ],
        10 => [ // General Courses
            'Business Administration', 'Accounting', 'Human Resource Management',
            'Marketing', 'Entrepreneurship', 'Business Studies', 'Foundation Program',
            'General Studies', 'Pre-Engineering', 'Renewable Energy Technology'
        ]
    ];

    echo "<h3>Reassigning Programs to Correct Departments:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Department</th><th>Programs Assigned</th><th>Count</th></tr>";

    // First, clear all existing program assignments
    $pdo->exec("UPDATE options SET department_id = NULL");

    foreach ($programGroups as $deptId => $programs) {
        $deptName = '';
        foreach ($departments as $dept) {
            if ($dept['id'] == $deptId) {
                $deptName = $dept['name'];
                break;
            }
        }

        $assignedCount = 0;
        foreach ($programs as $programName) {
            // Find programs with similar names and assign them
            $likePatterns = [
                $programName,
                str_replace(' Engineering', '', $programName),
                str_replace(' Technology', '', $programName),
                str_replace(' & ', '%', $programName)
            ];

            foreach ($likePatterns as $pattern) {
                $stmt = $pdo->prepare("UPDATE options SET department_id = ? WHERE name LIKE ? AND department_id IS NULL");
                $stmt->execute([$deptId, '%' . $pattern . '%']);
                $assignedCount += $stmt->rowCount();
            }
        }

        echo "<tr>";
        echo "<td>{$deptName}</td>";
        echo "<td>" . implode(', ', array_slice($programs, 0, 3)) . "...</td>";
        echo "<td>{$assignedCount}</td>";
        echo "</tr>";
    }

    // Assign any remaining programs to General Courses as fallback
    $stmt = $pdo->prepare("UPDATE options SET department_id = 10 WHERE department_id IS NULL");
    $remainingCount = $stmt->rowCount();

    if ($remainingCount > 0) {
        echo "<tr><td>General Courses (Fallback)</td><td>Unassigned programs</td><td>{$remainingCount}</td></tr>";
    }

    echo "</table>";

    // Show final distribution
    echo "<h3>Final Program Distribution:</h3>";
    $finalStmt = $pdo->query("
        SELECT d.id, d.name, COUNT(o.id) as count
        FROM departments d
        LEFT JOIN options o ON d.id = o.department_id
        GROUP BY d.id, d.name
        ORDER BY d.id
    ");
    $finalDist = $finalStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Dept ID</th><th>Department</th><th>Programs</th></tr>";
    foreach ($finalDist as $dist) {
        echo "<tr><td>{$dist['id']}</td><td>{$dist['name']}</td><td>{$dist['count']}</td></tr>";
    }
    echo "</table>";

    echo "<h3 style='color: green;'>✅ Programs properly distributed across departments!</h3>";
    echo "<p>The registration form will now show programs correctly grouped by their departments.</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>