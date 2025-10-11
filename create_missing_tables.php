<?php
/**
 * Create Missing Attendance Tables
 * Adds attendance_sessions and attendance_records tables to the database
 */

require_once "config.php";

echo "<h1>🔧 Creating Missing Attendance Tables</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .info { color: #17a2b8; }
    .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>📊 Checking Database Connection...</h2>";

    // Test database connection
    $pdo->query("SELECT 1");
    echo "<p class='success'>✅ Database connection successful!</p>";
    echo "<p class='info'>Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "</p>";
    echo "</div>";

    // Create attendance_sessions table
    echo "<div class='card'>";
    echo "<h2>📅 Creating Attendance Sessions Table...</h2>";
    try {
        $pdo->query("DESCRIBE attendance_sessions");
        echo "<p class='success'>✅ Attendance sessions table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating attendance_sessions table...</p>";
        $pdo->exec("
            CREATE TABLE attendance_sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                lecturer_id INT NOT NULL,
                course_id INT NOT NULL,
                department_id INT NOT NULL,
                option_id INT NOT NULL,
                session_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NULL,
                status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_lecturer (lecturer_id),
                INDEX idx_course (course_id),
                INDEX idx_department (department_id),
                INDEX idx_option (option_id),
                INDEX idx_session_date (session_date),
                INDEX idx_status (status),
                FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Attendance sessions table created successfully</p>";
    }
    echo "</div>";

    // Create attendance_records table
    echo "<div class='card'>";
    echo "<h2>📝 Creating Attendance Records Table...</h2>";
    try {
        $pdo->query("DESCRIBE attendance_records");
        echo "<p class='success'>✅ Attendance records table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating attendance_records table...</p>";
        $pdo->exec("
            CREATE TABLE attendance_records (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                student_id INT NOT NULL,
                status ENUM('present', 'absent', 'late') NOT NULL DEFAULT 'present',
                method ENUM('manual', 'face_recognition', 'fingerprint') NOT NULL DEFAULT 'manual',
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_session (session_id),
                INDEX idx_student (student_id),
                INDEX idx_status (status),
                INDEX idx_method (method),
                INDEX idx_recorded_at (recorded_at),
                FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Attendance records table created successfully</p>";
    }
    echo "</div>";

    // Create student_photos table for face recognition
    echo "<div class='card'>";
    echo "<h2>📸 Creating Student Photos Table...</h2>";
    try {
        $pdo->query("DESCRIBE student_photos");
        echo "<p class='success'>✅ Student photos table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating student_photos table...</p>";
        $pdo->exec("
            CREATE TABLE student_photos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                photo_path VARCHAR(500) NOT NULL,
                photo_type ENUM('registration', 'verification') DEFAULT 'registration',
                is_primary BOOLEAN DEFAULT FALSE,
                uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_student (student_id),
                INDEX idx_photo_type (photo_type),
                INDEX idx_is_primary (is_primary),
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Student photos table created successfully</p>";
    }
    echo "</div>";

    // Create courses table if it doesn't exist
    echo "<div class='card'>";
    echo "<h2>📚 Creating Courses Table...</h2>";
    try {
        $pdo->query("DESCRIBE courses");
        echo "<p class='success'>✅ Courses table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating courses table...</p>";
        $pdo->exec("
            CREATE TABLE courses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                course_code VARCHAR(50) UNIQUE NOT NULL,
                description TEXT,
                credits INT DEFAULT 3,
                department_id INT NOT NULL,
                option_id INT NULL,
                lecturer_id INT NULL,
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_course_code (course_code),
                INDEX idx_department (department_id),
                INDEX idx_option (option_id),
                INDEX idx_lecturer (lecturer_id),
                INDEX idx_status (status),
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE SET NULL,
                FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Courses table created successfully</p>";
    }
    echo "</div>";

    // Create students table if it doesn't exist
    echo "<div class='card'>";
    echo "<h2>👨‍🎓 Creating Students Table...</h2>";
    try {
        $pdo->query("DESCRIBE students");
        echo "<p class='success'>✅ Students table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating students table...</p>";
        $pdo->exec("
            CREATE TABLE students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reg_no VARCHAR(50) UNIQUE NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                telephone VARCHAR(20),
                dob DATE,
                sex ENUM('Male', 'Female', 'Other') NOT NULL,
                department_id INT NOT NULL,
                option_id INT NOT NULL,
                year_level INT NOT NULL,
                student_id_number VARCHAR(20),
                parent_first_name VARCHAR(100),
                parent_last_name VARCHAR(100),
                parent_contact VARCHAR(20),
                province VARCHAR(100),
                district VARCHAR(100),
                sector VARCHAR(100),
                cell VARCHAR(100),
                password VARCHAR(255) NOT NULL,
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_reg_no (reg_no),
                INDEX idx_email (email),
                INDEX idx_department (department_id),
                INDEX idx_option (option_id),
                INDEX idx_year_level (year_level),
                INDEX idx_status (status),
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Students table created successfully</p>";
    }
    echo "</div>";

    // Show final statistics
    echo "<div class='card'>";
    echo "<h2>📈 Final Database Statistics</h2>";

    $tables = ['attendance_sessions', 'attendance_records', 'student_photos', 'courses', 'students'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "<p><strong>$table:</strong> $count records</p>";
        } catch (Exception $e) {
            echo "<p class='error'><strong>$table:</strong> Error - " . $e->getMessage() . "</p>";
        }
    }

    echo "<hr>";
    echo "<h3 class='success'>🎉 Missing tables created successfully!</h3>";
    echo "<p>The attendance system is now ready to use.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='attendance-session.php' class='btn btn-primary' style='margin-right: 10px; padding: 10px 20px; text-decoration: none; background: #007bff; color: white; border-radius: 5px;'>Go to Attendance Session</a>";
    echo "<a href='admin-dashboard.php' class='btn btn-success' style='padding: 10px 20px; text-decoration: none; background: #28a745; color: white; border-radius: 5px;'>Go to Admin Dashboard</a>";
    echo "</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<h2 class='error'>❌ Table Creation Failed</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
    echo "</div>";
}

echo "<div class='card' style='margin-top: 30px; padding-bottom: 30px;'>";
echo "<h3>🔧 Troubleshooting</h3>";
echo "<p>If you encounter issues:</p>";
echo "<ol>";
echo "<li>Ensure your database server is running</li>";
echo "<li>Check that the database 'rp_attendance_system' exists</li>";
echo "<li>Verify your database credentials in config.php</li>";
echo "<li>Make sure you have the necessary permissions to create tables</li>";
echo "</ol>";
echo "</div>";
?>

<style>
.btn {
    display: inline-block;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s;
}

.btn:hover {
    opacity: 0.9;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-success {
    background: #28a745;
    color: white;
}
</style>