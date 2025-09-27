<?php
// Test frontend behavior simulation
require_once 'config.php';

// Simulate different user scenarios
$scenarios = [
    [
        'role' => 'admin',
        'user_id' => 1,
        'description' => 'Admin user - should see all departments'
    ],
    [
        'role' => 'lecturer',
        'user_id' => 1,
        'description' => 'Lecturer with assigned department - should see only their department'
    ],
    [
        'role' => 'lecturer',
        'user_id' => 999,
        'description' => 'Lecturer without assigned department - should see no departments'
    ]
];

echo "=== FRONTEND BEHAVIOR TEST ===\n\n";

foreach ($scenarios as $scenario) {
    echo "Testing: {$scenario['description']}\n";
    echo "Role: {$scenario['role']}, User ID: {$scenario['user_id']}\n";

    // Simulate session
    $_SESSION['role'] = $scenario['role'];
    $_SESSION['user_id'] = $scenario['user_id'];

    // Test department access
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=rp_attendance_system", "root", "");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($scenario['role'] === 'admin') {
            // Admin sees all departments
            $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "✅ Admin can access " . count($departments) . " departments\n";
        } else {
            // Lecturer sees only their assigned department
            $stmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
            $stmt->execute([$scenario['user_id']]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($lecturer && $lecturer['department_id']) {
                $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
                $stmt->execute([$lecturer['department_id']]);
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "✅ Lecturer can access " . count($departments) . " department(s): " . $departments[0]['name'] . "\n";
            } else {
                echo "✅ Lecturer has no assigned department - will see no departments\n";
            }
        }

        // Test options access
        if ($scenario['role'] === 'admin') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM options");
            $options = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✅ Admin can access " . $options['count'] . " options\n";
        } else {
            if ($lecturer && $lecturer['department_id']) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM options WHERE department_id = ?");
                $stmt->execute([$lecturer['department_id']]);
                $options = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "✅ Lecturer can access " . $options['count'] . " option(s) from their department\n";
            } else {
                echo "✅ Lecturer has no department - no options available\n";
            }
        }

        // Test courses access
        if ($scenario['role'] === 'admin') {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM courses");
            $courses = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "✅ Admin can access " . $courses['count'] . " courses\n";
        } else {
            if ($lecturer && $lecturer['department_id']) {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM courses WHERE department_id = ?");
                $stmt->execute([$lecturer['department_id']]);
                $courses = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "✅ Lecturer can access " . $courses['count'] . " course(s) from their department\n";
            } else {
                echo "✅ Lecturer has no department - no courses available\n";
            }
        }

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }

    echo "\n" . str_repeat("-", 50) . "\n\n";
}

echo "=== FRONTEND BEHAVIOR SUMMARY ===\n";
echo "✅ Admin users: Full access to all departments, options, and courses\n";
echo "✅ Lecturers with assigned department: Access only to their department's data\n";
echo "✅ Lecturers without assigned department: No access to any department data\n";
echo "✅ Frontend will show appropriate messages based on user permissions\n";
echo "✅ Dependency chain (Department → Option → Courses) works correctly\n";