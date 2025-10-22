<?php
/**
 * Fix Attendance Session Issues
 * Comprehensive fix for attendance session configuration problems
 */

require_once "config.php";
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix Attendance Session</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { color: #856404; background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .btn { padding: 12px 24px; margin: 8px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 500; }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }
        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f8f9fa; font-weight: 600; }
        .fix-section { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .step-number { background: #007bff; color: white; border-radius: 50%; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Fix Attendance Session Configuration</h1>";
echo "<p>This tool will diagnose and fix issues with attendance session configuration.</p>";

$fixes_applied = 0;

try {
    // Step 1: Check current user and department
    echo "<div class='fix-section'>";
    echo "<h2><span class='step-number'>1</span>Current User & Department Check</h2>";
    
    if (!isset($_SESSION['user_id'])) {
        echo "<div class='error'>❌ No user session found. Please <a href='login_new.php'>login first</a>.</div>";
        echo "</div></div></body></html>";
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    echo "<div class='success'>✅ User ID: $user_id</div>";
    echo "<div class='success'>✅ Role: {$_SESSION['role']}</div>";
    
    // Get user's department information
    $user_stmt = $pdo->prepare("
        SELECT u.*, l.id as lecturer_id, l.department_id, d.name as department_name
        FROM users u
        LEFT JOIN lecturers l ON u.id = l.user_id
        LEFT JOIN departments d ON l.department_id = d.id
        WHERE u.id = ?
    ");
    $user_stmt->execute([$user_id]);
    $user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_info) {
        echo "<div class='error'>❌ User information not found!</div>";
        echo "</div></div></body></html>";
        exit;
    }
    
    if (!$user_info['department_id']) {
        echo "<div class='error'>❌ User is not assigned to any department!</div>";
        echo "<div class='info'>💡 Please contact administrator to assign you to a department.</div>";
    } else {
        echo "<div class='success'>✅ Department: {$user_info['department_name']} (ID: {$user_info['department_id']})</div>";
    }
    
    echo "</div>";

    // Step 2: Check department options
    echo "<div class='fix-section'>";
    echo "<h2><span class='step-number'>2</span>Department Options Check</h2>";
    
    if ($user_info['department_id']) {
        $dept_id = $user_info['department_id'];
        
        // Check existing options
        $options_stmt = $pdo->prepare("SELECT * FROM options WHERE department_id = ? ORDER BY name");
        $options_stmt->execute([$dept_id]);
        $existing_options = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($existing_options)) {
            echo "<div class='error'>❌ No academic programs (options) found for {$user_info['department_name']}!</div>";
            echo "<div class='warning'>⚠️ This is why the attendance session is failing to load options.</div>";
            echo "<button class='btn btn-success' onclick='createDepartmentOptions({$dept_id})'>Create Default Options for {$user_info['department_name']}</button>";
        } else {
            echo "<div class='success'>✅ Found " . count($existing_options) . " academic programs:</div>";
            echo "<table>";
            echo "<tr><th>ID</th><th>Program Name</th><th>Created</th><th>Actions</th></tr>";
            foreach ($existing_options as $option) {
                echo "<tr>";
                echo "<td>{$option['id']}</td>";
                echo "<td>{$option['name']}</td>";
                echo "<td>" . ($option['created_at'] ?? 'N/A') . "</td>";
                echo "<td><button class='btn btn-primary' onclick='testOption({$option['id']})'>Test</button></td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    echo "</div>";

    // Step 3: Check courses for options
    echo "<div class='fix-section'>";
    echo "<h2><span class='step-number'>3</span>Courses Check</h2>";
    
    if ($user_info['department_id'] && !empty($existing_options)) {
        $total_courses = 0;
        foreach ($existing_options as $option) {
            $courses_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM courses WHERE option_id = ?");
            $courses_stmt->execute([$option['id']]);
            $course_count = $courses_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $total_courses += $course_count;
            
            if ($course_count == 0) {
                echo "<div class='warning'>⚠️ No courses found for {$option['name']}</div>";
                echo "<button class='btn btn-warning' onclick='createCoursesForOption({$option['id']})'>Create Sample Courses</button>";
            } else {
                echo "<div class='success'>✅ {$option['name']}: $course_count courses</div>";
            }
        }
        
        echo "<div class='info'>📊 Total courses across all programs: $total_courses</div>";
    }
    
    echo "</div>";

    // Step 4: API Test
    echo "<div class='fix-section'>";
    echo "<h2><span class='step-number'>4</span>API Functionality Test</h2>";
    
    if ($user_info['department_id']) {
        echo "<div id='api-test-results'></div>";
        echo "<button class='btn btn-primary' onclick='testAPI({$user_info['department_id']})'>Test get-options.php API</button>";
        echo "<button class='btn btn-primary' onclick='testFullFlow({$user_info['department_id']})'>Test Full Flow</button>";
    }
    
    echo "</div>";

    // Step 5: Quick Fix All
    echo "<div class='fix-section'>";
    echo "<h2><span class='step-number'>5</span>Quick Fix Options</h2>";
    
    if ($user_info['department_id']) {
        echo "<button class='btn btn-success' onclick='fixEverything({$user_info['department_id']})'>🚀 Fix Everything Automatically</button> ";
        echo "<button class='btn btn-warning' onclick='resetDepartmentData({$user_info['department_id']})'>🔄 Reset Department Data</button> ";
        echo "<a href='attendance-session.php' class='btn btn-primary'>📝 Test Attendance Session</a>";
    }
    
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "
<script>
async function createDepartmentOptions(deptId) {
    if (!confirm('Create default academic programs for this department?')) return;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=create_options&department_id=' + deptId
        });
        
        const result = await response.text();
        if (response.ok) {
            alert('✅ Academic programs created successfully!');
            location.reload();
        } else {
            alert('❌ Error creating programs: ' + result);
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}

async function createCoursesForOption(optionId) {
    if (!confirm('Create sample courses for this program?')) return;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=create_courses&option_id=' + optionId
        });
        
        if (response.ok) {
            alert('✅ Sample courses created successfully!');
            location.reload();
        } else {
            alert('❌ Error creating courses');
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}

async function testAPI(deptId) {
    const resultsDiv = document.getElementById('api-test-results');
    resultsDiv.innerHTML = '<div class=\"info\">🔄 Testing API...</div>';
    
    try {
        const response = await fetch('api/get-options.php?department_id=' + deptId);
        const data = await response.json();
        
        let html = '<div class=\"success\">✅ API Response:</div>';
        html += '<pre style=\"background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;\">';
        html += JSON.stringify(data, null, 2);
        html += '</pre>';
        
        if (data.status === 'success' && data.count > 0) {
            html += '<div class=\"success\">✅ API is working correctly with ' + data.count + ' options found!</div>';
        } else if (data.status === 'success' && data.count === 0) {
            html += '<div class=\"warning\">⚠️ API works but no options found. Create some options first.</div>';
        } else {
            html += '<div class=\"error\">❌ API returned an error: ' + (data.message || 'Unknown error') + '</div>';
        }
        
        resultsDiv.innerHTML = html;
    } catch (error) {
        resultsDiv.innerHTML = '<div class=\"error\">❌ API Test Failed: ' + error.message + '</div>';
    }
}

async function testFullFlow(deptId) {
    const resultsDiv = document.getElementById('api-test-results');
    resultsDiv.innerHTML = '<div class=\"info\">🔄 Testing full attendance session flow...</div>';
    
    try {
        // Test 1: Get options
        const optionsResponse = await fetch('api/get-options.php?department_id=' + deptId);
        const optionsData = await optionsResponse.json();
        
        let html = '<h4>Step 1: Get Options</h4>';
        if (optionsData.status === 'success' && optionsData.count > 0) {
            html += '<div class=\"success\">✅ Options loaded: ' + optionsData.count + ' found</div>';
            
            // Test 2: Get courses for first option
            const firstOption = optionsData.data[0];
            html += '<h4>Step 2: Get Courses for \"' + firstOption.name + '\"</h4>';
            
            try {
                const coursesResponse = await fetch('api/get-courses.php?department_id=' + deptId + '&option_id=' + firstOption.id);
                const coursesData = await coursesResponse.json();
                
                if (coursesData.status === 'success') {
                    html += '<div class=\"success\">✅ Courses loaded: ' + (coursesData.count || 0) + ' found</div>';
                } else {
                    html += '<div class=\"warning\">⚠️ No courses found for this option</div>';
                }
            } catch (e) {
                html += '<div class=\"error\">❌ Error loading courses: ' + e.message + '</div>';
            }
            
        } else {
            html += '<div class=\"error\">❌ No options found - this is the main issue!</div>';
        }
        
        resultsDiv.innerHTML = html;
    } catch (error) {
        resultsDiv.innerHTML = '<div class=\"error\">❌ Full Flow Test Failed: ' + error.message + '</div>';
    }
}

async function fixEverything(deptId) {
    if (!confirm('This will create a complete setup for attendance sessions. Continue?')) return;
    
    try {
        const response = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=fix_everything&department_id=' + deptId
        });
        
        if (response.ok) {
            alert('✅ Everything fixed! You can now use attendance sessions.');
            location.reload();
        } else {
            alert('❌ Error during fix process');
        }
    } catch (error) {
        alert('❌ Error: ' + error.message);
    }
}
</script>";

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'create_options':
            $dept_id = (int)$_POST['department_id'];
            
            // Get department name
            $dept_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $dept_stmt->execute([$dept_id]);
            $dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dept) {
                $dept_name = $dept['name'];
                $sample_options = [];
                
                // Create department-specific options
                switch (strtolower($dept_name)) {
                    case 'information & communication technology':
                    case 'ict':
                        $sample_options = [
                            'Software Development',
                            'Network Administration', 
                            'Database Management',
                            'Cybersecurity',
                            'Web Development',
                            'Mobile App Development'
                        ];
                        break;
                    case 'civil engineering':
                        $sample_options = [
                            'Structural Engineering',
                            'Transportation Engineering',
                            'Environmental Engineering',
                            'Geotechnical Engineering',
                            'Construction Management'
                        ];
                        break;
                    case 'mechanical engineering':
                        $sample_options = [
                            'Automotive Engineering',
                            'Manufacturing Engineering',
                            'Thermal Engineering',
                            'Mechanical Design',
                            'Industrial Engineering'
                        ];
                        break;
                    case 'electrical & electronics engineering':
                        $sample_options = [
                            'Power Systems',
                            'Electronics Engineering',
                            'Control Systems',
                            'Telecommunications',
                            'Renewable Energy'
                        ];
                        break;
                    default:
                        $sample_options = [
                            $dept_name . ' - Level 1',
                            $dept_name . ' - Level 2', 
                            $dept_name . ' - Level 3',
                            $dept_name . ' - Advanced'
                        ];
                }
                
                // Insert options
                $insert_stmt = $pdo->prepare("
                    INSERT INTO options (name, department_id, created_at) 
                    VALUES (?, ?, NOW())
                ");
                
                foreach ($sample_options as $option_name) {
                    $insert_stmt->execute([$option_name, $dept_id]);
                }
                
                echo "<div class='success'>✅ Created " . count($sample_options) . " academic programs for $dept_name</div>";
            }
            break;
            
        case 'create_courses':
            $option_id = (int)$_POST['option_id'];
            
            // Get option info
            $option_stmt = $pdo->prepare("SELECT name FROM options WHERE id = ?");
            $option_stmt->execute([$option_id]);
            $option = $option_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($option) {
                $sample_courses = [
                    ['name' => 'Introduction to ' . $option['name'], 'code' => 'INT101'],
                    ['name' => 'Advanced ' . $option['name'], 'code' => 'ADV201'],
                    ['name' => $option['name'] . ' Practical', 'code' => 'PRC301'],
                    ['name' => $option['name'] . ' Project', 'code' => 'PRJ401']
                ];
                
                $insert_stmt = $pdo->prepare("
                    INSERT INTO courses (course_code, name, option_id, created_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                
                foreach ($sample_courses as $course) {
                    $insert_stmt->execute([$course['code'], $course['name'], $option_id]);
                }
                
                echo "<div class='success'>✅ Created " . count($sample_courses) . " courses for {$option['name']}</div>";
            }
            break;
            
        case 'fix_everything':
            $dept_id = (int)$_POST['department_id'];
            
            // Get department info
            $dept_stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $dept_stmt->execute([$dept_id]);
            $dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($dept) {
                // Create options if none exist
                $options_check = $pdo->prepare("SELECT COUNT(*) as count FROM options WHERE department_id = ?");
                $options_check->execute([$dept_id]);
                $options_count = $options_check->fetch(PDO::FETCH_ASSOC)['count'];
                
                if ($options_count == 0) {
                    // Create default options (same logic as above)
                    $dept_name = $dept['name'];
                    $sample_options = ['Software Development', 'Network Administration', 'Database Management'];
                    
                    $insert_stmt = $pdo->prepare("INSERT INTO options (name, department_id, created_at) VALUES (?, ?, NOW())");
                    foreach ($sample_options as $option_name) {
                        $insert_stmt->execute([$option_name, $dept_id]);
                    }
                    
                    echo "<div class='success'>✅ Created academic programs</div>";
                }
                
                // Create courses for each option
                $options_stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ?");
                $options_stmt->execute([$dept_id]);
                $options = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($options as $option) {
                    $courses_check = $pdo->prepare("SELECT COUNT(*) as count FROM courses WHERE option_id = ?");
                    $courses_check->execute([$option['id']]);
                    $courses_count = $courses_check->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($courses_count == 0) {
                        $sample_courses = [
                            ['name' => 'Introduction to ' . $option['name'], 'code' => 'INT101'],
                            ['name' => 'Advanced ' . $option['name'], 'code' => 'ADV201']
                        ];
                        
                        $insert_stmt = $pdo->prepare("INSERT INTO courses (course_code, name, option_id, created_at) VALUES (?, ?, ?, NOW())");
                        foreach ($sample_courses as $course) {
                            $insert_stmt->execute([$course['code'], $course['name'], $option['id']]);
                        }
                    }
                }
                
                echo "<div class='success'>✅ Complete setup created successfully!</div>";
            }
            break;
    }
    exit;
}

echo "</div></body></html>";
?>
