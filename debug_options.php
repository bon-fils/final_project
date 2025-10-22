<?php
/**
 * Debug Options Loading Issue
 * Check what's happening with department options
 */

require_once "config.php";
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Options Loading</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; }
        .btn { padding: 8px 16px; margin: 4px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Debug Options Loading Issue</h1>";

try {
    // Check current user
    echo "<h2>1. Current User Information</h2>";
    if (isset($_SESSION['user_id'])) {
        echo "<div class='success'>✅ User ID: {$_SESSION['user_id']}</div>";
        echo "<div class='success'>✅ Role: {$_SESSION['role']}</div>";
        
        // Get user details
        $stmt = $pdo->prepare("
            SELECT u.*, l.id as lecturer_id, l.department_id, d.name as department_name
            FROM users u
            LEFT JOIN lecturers l ON u.id = l.user_id
            LEFT JOIN departments d ON l.department_id = d.id
            WHERE u.id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            foreach ($user as $key => $value) {
                echo "<tr><td>$key</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='error'>❌ No user session found</div>";
    }

    // Check all departments
    echo "<h2>2. All Departments</h2>";
    $departments = $pdo->query("SELECT * FROM departments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($departments)) {
        echo "<div class='error'>❌ No departments found!</div>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>HOD ID</th><th>Options Count</th><th>Action</th></tr>";
        
        foreach ($departments as $dept) {
            // Count options for each department
            $options_count = $pdo->prepare("SELECT COUNT(*) as count FROM options WHERE department_id = ?");
            $options_count->execute([$dept['id']]);
            $count = $options_count->fetch(PDO::FETCH_ASSOC)['count'];
            
            echo "<tr>";
            echo "<td>{$dept['id']}</td>";
            echo "<td>{$dept['name']}</td>";
            echo "<td>" . ($dept['hod_id'] ?? 'NULL') . "</td>";
            echo "<td>$count</td>";
            echo "<td><button class='btn btn-primary' onclick='testAPI({$dept['id']})'>Test API</button></td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Check options for department 7 specifically
    echo "<h2>3. Options for Department ID 7</h2>";
    $stmt = $pdo->prepare("
        SELECT o.*, d.name as department_name
        FROM options o
        LEFT JOIN departments d ON o.department_id = d.id
        WHERE o.department_id = 7
        ORDER BY o.name
    ");
    $stmt->execute();
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($options)) {
        echo "<div class='error'>❌ No options found for department ID 7!</div>";
        echo "<div class='info'>💡 This is likely the cause of the error. Let's create some sample options.</div>";
        
        // Check if department 7 exists
        $dept_check = $pdo->prepare("SELECT name FROM departments WHERE id = 7");
        $dept_check->execute();
        $dept = $dept_check->fetch(PDO::FETCH_ASSOC);
        
        if ($dept) {
            echo "<div class='success'>✅ Department 7 exists: {$dept['name']}</div>";
            echo "<button class='btn btn-success' onclick='createSampleOptions(7)'>Create Sample Options for {$dept['name']}</button>";
        } else {
            echo "<div class='error'>❌ Department ID 7 doesn't exist!</div>";
        }
    } else {
        echo "<div class='success'>✅ Found " . count($options) . " options for department 7</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Name</th><th>Department</th><th>Created</th></tr>";
        foreach ($options as $option) {
            echo "<tr>";
            echo "<td>{$option['id']}</td>";
            echo "<td>{$option['name']}</td>";
            echo "<td>{$option['department_name']}</td>";
            echo "<td>" . ($option['created_at'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test the API directly
    echo "<h2>4. Direct API Test</h2>";
    echo "<div id='api-test-result'></div>";
    echo "<button class='btn btn-primary' onclick='testAPI(7)'>Test get-options.php for Department 7</button>";

    // Check if API file exists
    echo "<h2>5. API File Check</h2>";
    $api_file = __DIR__ . '/api/get-options.php';
    if (file_exists($api_file)) {
        echo "<div class='success'>✅ API file exists: $api_file</div>";
        echo "<div class='info'>File size: " . filesize($api_file) . " bytes</div>";
        echo "<div class='info'>Last modified: " . date('Y-m-d H:i:s', filemtime($api_file)) . "</div>";
    } else {
        echo "<div class='error'>❌ API file not found: $api_file</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "
<script>
async function testAPI(departmentId) {
    const resultDiv = document.getElementById('api-test-result');
    resultDiv.innerHTML = '<div class=\"info\">Testing API for department ' + departmentId + '...</div>';
    
    try {
        const response = await fetch('api/get-options.php?department_id=' + departmentId);
        const data = await response.json();
        
        resultDiv.innerHTML = '<div class=\"success\">✅ API Response:</div><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    } catch (error) {
        resultDiv.innerHTML = '<div class=\"error\">❌ API Error: ' + error.message + '</div>';
    }
}

async function createSampleOptions(departmentId) {
    if (!confirm('Create sample options for department ' + departmentId + '?')) return;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=create_options&department_id=' + departmentId
        });
        
        if (response.ok) {
            alert('Sample options created successfully!');
            location.reload();
        } else {
            alert('Error creating options');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>";

// Handle POST requests to create sample options
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_options') {
        $dept_id = (int)$_POST['department_id'];
        
        // Get department name
        $dept_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $dept_stmt->execute([$dept_id]);
        $dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dept) {
            $dept_name = $dept['name'];
            
            // Create sample options based on department
            $sample_options = [];
            
            switch (strtolower($dept_name)) {
                case 'information & communication technology':
                case 'ict':
                    $sample_options = [
                        'Software Development',
                        'Network Administration', 
                        'Database Management',
                        'Cybersecurity',
                        'Web Development'
                    ];
                    break;
                case 'civil engineering':
                    $sample_options = [
                        'Structural Engineering',
                        'Transportation Engineering',
                        'Environmental Engineering',
                        'Geotechnical Engineering'
                    ];
                    break;
                case 'mechanical engineering':
                    $sample_options = [
                        'Automotive Engineering',
                        'Manufacturing Engineering',
                        'Thermal Engineering',
                        'Mechanical Design'
                    ];
                    break;
                default:
                    $sample_options = [
                        $dept_name . ' - Level 1',
                        $dept_name . ' - Level 2', 
                        $dept_name . ' - Level 3'
                    ];
            }
            
            // Insert sample options
            $insert_stmt = $pdo->prepare("
                INSERT INTO options (name, department_id, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            foreach ($sample_options as $option_name) {
                $insert_stmt->execute([$option_name, $dept_id]);
            }
            
            echo "<div class='success'>✅ Created " . count($sample_options) . " sample options for $dept_name</div>";
        }
    }
    exit;
}

echo "</div></body></html>";
?>
