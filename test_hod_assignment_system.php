<?php
/**
 * Test HOD Assignment System
 * Comprehensive test to verify all improvements are working
 */

require_once "config.php";

echo "<h1>🧪 Testing HOD Assignment System</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .card { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
    table { border-collapse: collapse; width: 100%; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .test-pass { background-color: #d4edda; }
    .test-fail { background-color: #f8d7da; }
</style>";

$tests_passed = 0;
$tests_failed = 0;

function runTest($testName, $testFunction) {
    global $tests_passed, $tests_failed;
    
    echo "<div class='card'>";
    echo "<h3>🔬 Test: $testName</h3>";
    
    try {
        $result = $testFunction();
        if ($result['success']) {
            echo "<p class='success test-pass'>✅ PASS: {$result['message']}</p>";
            $tests_passed++;
        } else {
            echo "<p class='error test-fail'>❌ FAIL: {$result['message']}</p>";
            $tests_failed++;
        }
        
        if (isset($result['details'])) {
            echo "<div class='info'><strong>Details:</strong> {$result['details']}</div>";
        }
    } catch (Exception $e) {
        echo "<p class='error test-fail'>❌ ERROR: " . $e->getMessage() . "</p>";
        $tests_failed++;
    }
    
    echo "</div>";
}

try {
    echo "<div class='card'>";
    echo "<h2>📊 System Status Check</h2>";
    
    // Test 1: API Endpoint Accessibility
    runTest("API Endpoint Accessibility", function() {
        $api_file = __DIR__ . '/api/assign-hod-api-improved.php';
        if (file_exists($api_file)) {
            return ['success' => true, 'message' => 'Improved API endpoint exists'];
        } else {
            return ['success' => false, 'message' => 'Improved API endpoint not found'];
        }
    });
    
    // Test 2: Database Schema Consistency
    runTest("Database Schema Consistency", function() use ($pdo) {
        // Check if departments.hod_id points to lecturers.id (not users.id)
        $stmt = $pdo->query("
            SELECT d.id, d.name, d.hod_id, l.id as lecturer_id, u.id as user_id
            FROM departments d
            LEFT JOIN lecturers l ON d.hod_id = l.id
            LEFT JOIN users u ON l.user_id = u.id
            WHERE d.hod_id IS NOT NULL
            LIMIT 1
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            if ($result['lecturer_id'] && $result['user_id']) {
                return [
                    'success' => true, 
                    'message' => 'Schema is consistent - departments.hod_id points to lecturers.id',
                    'details' => "Department '{$result['name']}' has hod_id={$result['hod_id']} → lecturer_id={$result['lecturer_id']} → user_id={$result['user_id']}"
                ];
            } else {
                return [
                    'success' => false, 
                    'message' => 'Schema inconsistency detected',
                    'details' => "Department '{$result['name']}' has hod_id={$result['hod_id']} but lecturer or user not found"
                ];
            }
        } else {
            return ['success' => true, 'message' => 'No HOD assignments to test (empty database)'];
        }
    });
    
    // Test 3: No Duplicate Lecturer Records
    runTest("No Duplicate Lecturer Records", function() use ($pdo) {
        $stmt = $pdo->query("
            SELECT user_id, COUNT(*) as count
            FROM lecturers
            WHERE user_id IS NOT NULL
            GROUP BY user_id
            HAVING COUNT(*) > 1
        ");
        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($duplicates)) {
            return ['success' => true, 'message' => 'No duplicate lecturer records found'];
        } else {
            $details = "Found duplicates for user_ids: " . implode(', ', array_column($duplicates, 'user_id'));
            return ['success' => false, 'message' => 'Duplicate lecturer records exist', 'details' => $details];
        }
    });
    
    // Test 4: HOD Validation Query Works
    runTest("HOD Validation Query", function() use ($pdo) {
        // Find a user with HOD role
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'hod' LIMIT 1");
        $hod_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$hod_user) {
            return ['success' => true, 'message' => 'No HOD users to test'];
        }
        
        // Test the HOD validation query
        $stmt = $pdo->prepare("
            SELECT d.name as department_name, d.id as department_id
            FROM departments d
            JOIN lecturers l ON d.hod_id = l.id
            JOIN users u ON l.user_id = u.id
            WHERE u.id = ? AND u.role = 'hod'
        ");
        $stmt->execute([$hod_user['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return [
                'success' => true, 
                'message' => 'HOD validation query works correctly',
                'details' => "User {$hod_user['id']} is HOD of '{$result['department_name']}'"
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'HOD validation query failed',
                'details' => "User {$hod_user['id']} has HOD role but validation query returned no results"
            ];
        }
    });
    
    // Test 5: No Multiple HOD Assignments
    runTest("No Multiple HOD Assignments", function() use ($pdo) {
        $stmt = $pdo->query("
            SELECT l.id, COUNT(d.id) as dept_count, GROUP_CONCAT(d.name) as departments
            FROM lecturers l
            JOIN departments d ON d.hod_id = l.id
            GROUP BY l.id
            HAVING COUNT(d.id) > 1
        ");
        $multiple_assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($multiple_assignments)) {
            return ['success' => true, 'message' => 'No lecturer is HOD of multiple departments'];
        } else {
            $details = "Lecturer ID {$multiple_assignments[0]['id']} is HOD of: {$multiple_assignments[0]['departments']}";
            return ['success' => false, 'message' => 'Found lecturer assigned to multiple departments', 'details' => $details];
        }
    });
    
    // Test 6: Department-Lecturer Consistency
    runTest("Department-Lecturer Consistency", function() use ($pdo) {
        $stmt = $pdo->query("
            SELECT d.id, d.name, l.department_id as lecturer_dept_id
            FROM departments d
            JOIN lecturers l ON d.hod_id = l.id
            WHERE d.id != l.department_id
        ");
        $inconsistencies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($inconsistencies)) {
            return ['success' => true, 'message' => 'All HODs belong to their assigned departments'];
        } else {
            $details = "Department '{$inconsistencies[0]['name']}' has HOD from different department";
            return ['success' => false, 'message' => 'Found HOD not belonging to assigned department', 'details' => $details];
        }
    });
    
    // Test 7: API Response Format
    runTest("API Response Format", function() {
        // Simulate API call
        $_GET['action'] = 'get_lecturers';
        $_GET['department_id'] = '5'; // Mechanical Engineering
        $_SESSION['user_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['csrf_token'] = 'test_token';
        
        ob_start();
        include 'api/assign-hod-api-improved.php';
        $response = ob_get_clean();
        
        $data = json_decode($response, true);
        
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            $lecturer_count = count($data['data'] ?? []);
            return [
                'success' => true, 
                'message' => 'API returns proper response format',
                'details' => "Found $lecturer_count lecturers with proper status indicators"
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'API response format incorrect',
                'details' => 'Response: ' . substr($response, 0, 200) . '...'
            ];
        }
    });
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>📋 Test Summary</h2>";
    $total_tests = $tests_passed + $tests_failed;
    $pass_rate = $total_tests > 0 ? round(($tests_passed / $total_tests) * 100, 1) : 0;
    
    echo "<table>";
    echo "<tr><th>Metric</th><th>Value</th><th>Status</th></tr>";
    echo "<tr><td>Tests Passed</td><td>$tests_passed</td><td class='success'>✅</td></tr>";
    echo "<tr><td>Tests Failed</td><td>$tests_failed</td><td class='" . ($tests_failed > 0 ? 'error' : 'success') . "'>" . ($tests_failed > 0 ? '❌' : '✅') . "</td></tr>";
    echo "<tr><td>Pass Rate</td><td>$pass_rate%</td><td class='" . ($pass_rate >= 80 ? 'success' : ($pass_rate >= 60 ? 'warning' : 'error')) . "'>" . ($pass_rate >= 80 ? '✅' : ($pass_rate >= 60 ? '⚠️' : '❌')) . "</td></tr>";
    echo "</table>";
    
    if ($pass_rate >= 80) {
        echo "<p class='success'>🎉 <strong>System is working well!</strong> Most tests passed.</p>";
    } elseif ($pass_rate >= 60) {
        echo "<p class='warning'>⚠️ <strong>System has some issues</strong> that should be addressed.</p>";
    } else {
        echo "<p class='error'>❌ <strong>System has significant issues</strong> that need immediate attention.</p>";
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🚀 Next Steps</h2>";
    echo "<ol>";
    
    if ($tests_failed > 0) {
        echo "<li><strong>Fix Failed Tests:</strong> Address the issues identified in the failed tests above</li>";
    }
    
    echo "<li><strong>Test HOD Assignment:</strong> Go to <a href='assign-hod.php'>HOD Assignment Interface</a></li>";
    echo "<li><strong>Test HOD Login:</strong> Try logging in as <code>abayo@gmail.com</code> with role 'Head of Department'</li>";
    echo "<li><strong>Verify Department Filtering:</strong> Select different departments and check lecturer loading</li>";
    echo "<li><strong>Test Assignment Rules:</strong> Try assigning lecturers from different departments (should be blocked)</li>";
    echo "</ol>";
    
    echo "<p><a href='assign-hod.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test HOD Assignment Interface</a></p>";
    echo "<p><a href='login.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test HOD Login</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<p class='error'>❌ Test execution error: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Testing completed!</em></p>";
?>
