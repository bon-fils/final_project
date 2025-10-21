<?php
/**
 * Critical Fixes for Attendance Session System
 * This script creates the missing database tables and API endpoints
 */

require_once "config.php";

echo "<h1>🔧 Fixing Critical Attendance Session Issues</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .card { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
    pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>📊 Creating Missing Database Tables</h2>";
    
    // Create options table if not exists
    echo "<h3>1. Creating 'options' table</h3>";
    $sql_options = "
    CREATE TABLE IF NOT EXISTS options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        department_id INT NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_department_id (department_id),
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_options);
    echo "<p class='success'>✅ Options table created/verified</p>";
    
    // Create attendance_sessions table
    echo "<h3>2. Creating 'attendance_sessions' table</h3>";
    $sql_sessions = "
    CREATE TABLE IF NOT EXISTS attendance_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lecturer_id INT NOT NULL,
        department_id INT NOT NULL,
        option_id INT,
        course_id INT,
        year_level INT,
        biometric_method ENUM('face', 'finger') NOT NULL,
        session_date DATE NOT NULL,
        start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        end_time TIMESTAMP NULL,
        status ENUM('active', 'ended', 'cancelled') DEFAULT 'active',
        total_students INT DEFAULT 0,
        present_count INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_lecturer_id (lecturer_id),
        INDEX idx_department_id (department_id),
        INDEX idx_session_date (session_date),
        INDEX idx_status (status),
        FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE CASCADE,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
        FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE SET NULL,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_sessions);
    echo "<p class='success'>✅ Attendance sessions table created/verified</p>";
    
    // Create attendance_records table
    echo "<h3>3. Creating 'attendance_records' table</h3>";
    $sql_records = "
    CREATE TABLE IF NOT EXISTS attendance_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        student_id INT NOT NULL,
        status ENUM('present', 'absent', 'late') DEFAULT 'present',
        marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        biometric_data TEXT,
        verification_method ENUM('face', 'finger', 'manual') NOT NULL,
        confidence_score DECIMAL(5,2),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_session_id (session_id),
        INDEX idx_student_id (student_id),
        INDEX idx_marked_at (marked_at),
        FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        UNIQUE KEY unique_session_student (session_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql_records);
    echo "<p class='success'>✅ Attendance records table created/verified</p>";
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>📝 Inserting Sample Data</h2>";
    
    // Insert sample options for each department
    echo "<h3>Adding Sample Academic Options</h3>";
    $departments = $pdo->query("SELECT id, name FROM departments")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($departments as $dept) {
        // Check if options already exist for this department
        $existing = $pdo->prepare("SELECT COUNT(*) FROM options WHERE department_id = ?");
        $existing->execute([$dept['id']]);
        
        if ($existing->fetchColumn() == 0) {
            // Insert sample options based on department
            $options = [];
            switch (strtolower($dept['name'])) {
                case 'information & communication technology':
                case 'ict':
                    $options = [
                        'Software Development',
                        'Network Administration', 
                        'Database Management',
                        'Cybersecurity'
                    ];
                    break;
                case 'mechanical engineering':
                    $options = [
                        'Automotive Engineering',
                        'Manufacturing Technology',
                        'Thermal Systems',
                        'Machine Design'
                    ];
                    break;
                case 'civil engineering':
                    $options = [
                        'Structural Engineering',
                        'Transportation Engineering',
                        'Water Resources',
                        'Construction Management'
                    ];
                    break;
                case 'electrical & electronics engineering':
                    $options = [
                        'Power Systems',
                        'Electronics & Communication',
                        'Control Systems',
                        'Renewable Energy'
                    ];
                    break;
                default:
                    $options = [
                        'General Studies',
                        'Applied Sciences',
                        'Technical Skills',
                        'Professional Development'
                    ];
            }
            
            $insert_option = $pdo->prepare("INSERT INTO options (name, department_id) VALUES (?, ?)");
            foreach ($options as $option) {
                $insert_option->execute([$option, $dept['id']]);
            }
            
            echo "<p class='success'>✅ Added options for {$dept['name']}</p>";
        } else {
            echo "<p class='info'>ℹ️ Options already exist for {$dept['name']}</p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🔗 Creating Missing API Endpoints</h2>";
    
    // Create get-options.php API
    $api_options_content = '<?php
/**
 * Get Academic Options API
 * Returns options for a specific department
 */

require_once "../config.php";
require_once "../session_check.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

$department_id = filter_input(INPUT_GET, "department_id", FILTER_VALIDATE_INT);

if (!$department_id) {
    echo json_encode(["status" => "error", "message" => "Invalid department ID"]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, name, description 
        FROM options 
        WHERE department_id = ? 
        ORDER BY name
    ");
    $stmt->execute([$department_id]);
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "data" => $options,
        "count" => count($options)
    ]);
    
} catch (Exception $e) {
    error_log("Get options error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Failed to fetch options"]);
}
?>';
    
    if (!file_exists('api/get-options.php')) {
        file_put_contents('api/get-options.php', $api_options_content);
        echo "<p class='success'>✅ Created api/get-options.php</p>";
    } else {
        echo "<p class='info'>ℹ️ api/get-options.php already exists</p>";
    }
    
    // Create get-courses.php API
    $api_courses_content = '<?php
/**
 * Get Courses API
 * Returns courses for a specific option and department
 */

require_once "../config.php";
require_once "../session_check.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

$option_id = filter_input(INPUT_GET, "option_id", FILTER_VALIDATE_INT);
$department_id = filter_input(INPUT_GET, "department_id", FILTER_VALIDATE_INT);

if (!$option_id && !$department_id) {
    echo json_encode(["status" => "error", "message" => "Option ID or Department ID required"]);
    exit;
}

try {
    $sql = "SELECT id, name, code, credits FROM courses WHERE 1=1";
    $params = [];
    
    if ($department_id) {
        $sql .= " AND department_id = ?";
        $params[] = $department_id;
    }
    
    if ($option_id) {
        $sql .= " AND option_id = ?";
        $params[] = $option_id;
    }
    
    $sql .= " ORDER BY name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "status" => "success",
        "data" => $courses,
        "count" => count($courses)
    ]);
    
} catch (Exception $e) {
    error_log("Get courses error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Failed to fetch courses"]);
}
?>';
    
    if (!file_exists('api/get-courses.php')) {
        file_put_contents('api/get-courses.php', $api_courses_content);
        echo "<p class='success'>✅ Created api/get-courses.php</p>";
    } else {
        echo "<p class='info'>ℹ️ api/get-courses.php already exists</p>";
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🧪 Testing the System</h2>";
    
    // Test database tables
    echo "<h3>Database Tables Status:</h3>";
    $tables = ['options', 'attendance_sessions', 'attendance_records'];
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p class='success'>✅ Table '$table': $count records</p>";
        } catch (Exception $e) {
            echo "<p class='error'>❌ Table '$table': Error - " . $e->getMessage() . "</p>";
        }
    }
    
    // Test API endpoints
    echo "<h3>API Endpoints Status:</h3>";
    $apis = ['api/get-options.php', 'api/get-courses.php'];
    foreach ($apis as $api) {
        if (file_exists($api)) {
            echo "<p class='success'>✅ $api exists</p>";
        } else {
            echo "<p class='error'>❌ $api missing</p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='card'>";
    echo "<h2>🚀 Next Steps</h2>";
    echo "<ol>";
    echo "<li><strong>Test the Attendance Session:</strong> Go to <a href='attendance-session.php'>attendance-session.php</a></li>";
    echo "<li><strong>Verify Options Loading:</strong> Select your department and check if options load</li>";
    echo "<li><strong>Test Course Loading:</strong> Select an option and verify courses load</li>";
    echo "<li><strong>Check Form Validation:</strong> Try to start a session with all fields filled</li>";
    echo "<li><strong>Review JavaScript Console:</strong> Check for any JavaScript errors</li>";
    echo "</ol>";
    
    echo "<p><a href='attendance-session.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Attendance Session</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<p class='error'>❌ Error occurred: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Critical fixes completed!</em></p>";
?>
