t<?php
/**
 * Complete Database Setup Script
 * Creates all required tables for the RP Attendance System
 */

require_once "config.php";

echo "<h1>🏫 RP Attendance System - Database Setup</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .warning { color: #ffc107; }
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

    // Create departments table
    echo "<div class='card'>";
    echo "<h2>🏢 Setting up Departments Table...</h2>";
    try {
        $pdo->query("DESCRIBE departments");
        echo "<p class='success'>✅ Departments table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating departments table...</p>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS departments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                hod_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_name (name),
                INDEX idx_hod_id (hod_id),
                FOREIGN KEY (hod_id) REFERENCES lecturers(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Departments table created successfully</p>";
    }
    echo "</div>";

    // Create lecturers table
    echo "<div class='card'>";
    echo "<h2>👨‍🏫 Setting up Lecturers Table...</h2>";
    try {
        $pdo->query("DESCRIBE lecturers");
        echo "<p class='success'>✅ Lecturers table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating lecturers table...</p>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lecturers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                gender ENUM('Male', 'Female', 'Other') NOT NULL,
                dob DATE NOT NULL,
                id_number VARCHAR(20) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                phone VARCHAR(20),
                department_id INT,
                education_level ENUM('Bachelor\'s', 'Master\'s', 'PhD', 'Other') NOT NULL,
                role ENUM('lecturer', 'hod') NOT NULL DEFAULT 'lecturer',
                photo VARCHAR(255),
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email),
                INDEX idx_department_id (department_id),
                INDEX idx_role (role),
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Lecturers table created successfully</p>";
    }
    echo "</div>";

    // Create users table
    echo "<div class='card'>";
    echo "<h2>👤 Setting up Users Table...</h2>";
    try {
        $pdo->query("DESCRIBE users");
        echo "<p class='success'>✅ Users table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating users table...</p>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('admin', 'hod', 'lecturer', 'student') NOT NULL,
                status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Users table created successfully</p>";
    }
    echo "</div>";

    // Create options table (for programs)
    echo "<div class='card'>";
    echo "<h2>📚 Setting up Options Table...</h2>";
    try {
        $pdo->query("DESCRIBE options");
        echo "<p class='success'>✅ Options table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating options table...</p>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS options (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                department_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_department_id (department_id),
                INDEX idx_name (name),
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Options table created successfully</p>";
    }
    echo "</div>";

    // Insert sample data if tables are empty
    echo "<div class='card'>";
    echo "<h2>🌱 Inserting Sample Data...</h2>";

    // Check if departments table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
    $deptCount = $stmt->fetch()['count'];

    if ($deptCount == 0) {
        echo "<p class='info'>Inserting sample departments...</p>";
        $pdo->exec("
            INSERT INTO departments (name) VALUES
            ('Civil Engineering'),
            ('Creative Arts'),
            ('Mechanical Engineering'),
            ('Electrical & Electronics Engineering'),
            ('Information & Communication Technology'),
            ('Mining Engineering'),
            ('Transport & Logistics'),
            ('General Courses')
        ");
        echo "<p class='success'>✅ Sample departments inserted</p>";
    } else {
        echo "<p class='info'>Departments table already has data (count: $deptCount)</p>";
    }

    // Check if lecturers table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM lecturers");
    $lecCount = $stmt->fetch()['count'];

    if ($lecCount == 0) {
        echo "<p class='info'>Inserting sample lecturers...</p>";
        $pdo->exec("
            INSERT INTO lecturers (first_name, last_name, gender, dob, id_number, email, phone, department_id, education_level, role, password) VALUES
            ('Frank', 'Mugabe', 'Male', '2004-09-02', '120018001269039', 'frankm@gmail.com', '0784615059', 4, 'PhD', 'lecturer', '" . password_hash('Welcome123!', PASSWORD_DEFAULT) . "'),
            ('Scott', 'Adkin', 'Male', '2008-06-25', '12345678900987654', 'scott@gmail.com', '078789234', 3, 'PhD', 'lecturer', '" . password_hash('Welcome123!', PASSWORD_DEFAULT) . "')
        ");
        echo "<p class='success'>✅ Sample lecturers inserted</p>";
    } else {
        echo "<p class='info'>Lecturers table already has data (count: $lecCount)</p>";
    }

    // Check if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];

    if ($userCount == 0) {
        echo "<p class='info'>Inserting sample users...</p>";
        // Hash the default admin password securely
        $adminPasswordHash = password_hash('Admin123!', PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        $pdo->exec("
            INSERT INTO users (username, email, password, role, status) VALUES
            ('admin', 'admin@rp.edu', '" . $adminPasswordHash . "', 'admin', 'active')
        ");
        echo "<p class='success'>✅ Sample admin user inserted with secure password</p>";
        echo "<p class='warning'>⚠️  <strong>Important:</strong> Default admin credentials - Username: admin, Password: Admin123!</p>";
    } else {
        echo "<p class='info'>Users table already has data (count: $userCount)</p>";
    }

    echo "</div>";

    // Create leave_requests table
    echo "<div class='card'>";
    echo "<h2>📝 Setting up Leave Requests Table...</h2>";
    try {
        $pdo->query("DESCRIBE leave_requests");
        echo "<p class='success'>✅ Leave requests table already exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating leave_requests table...</p>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS leave_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                reason TEXT NOT NULL,
                from_date DATE NOT NULL,
                to_date DATE NOT NULL,
                status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                supporting_file VARCHAR(255),
                request_to ENUM('hod', 'lecturer') NOT NULL,
                course_id INT,
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reviewed_at TIMESTAMP NULL,
                reviewed_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_student_id (student_id),
                INDEX idx_status (status),
                INDEX idx_request_to (request_to),
                INDEX idx_requested_at (requested_at),
                FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL,
                FOREIGN KEY (reviewed_by) REFERENCES lecturers(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<p class='success'>✅ Leave requests table created successfully</p>";
    }
    echo "</div>";

    // Create face recognition test logs table
    echo "<div class='card'>";
    echo "<h2>📊 Setting up Face Recognition Test Logs Table...</h2>";
    echo "<p class='info'>Dropping existing table if it exists...</p>";
    $pdo->exec("DROP TABLE IF EXISTS face_recognition_logs");

    echo "<p class='info'>Creating face recognition logs table...</p>";
    $pdo->exec("
        CREATE TABLE face_recognition_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            captured_image_path VARCHAR(255),
            captured_image_size INT,
            student_id INT,
            student_reg_no VARCHAR(50),
            student_type ENUM('regular', 'test') DEFAULT 'regular',
            comparison_score DECIMAL(5,4),
            confidence_level ENUM('high', 'medium', 'low'),
            processing_method VARCHAR(50),
            distance DECIMAL(5,4),
            pixel_similarity DECIMAL(5,4),
            size_similarity DECIMAL(5,4),
            match_found BOOLEAN DEFAULT FALSE,
            processing_time DECIMAL(5,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student (student_id),
            INDEX idx_student_type (student_type),
            INDEX idx_created_at (created_at),
            INDEX idx_match_found (match_found)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p class='success'>✅ Face recognition logs table created successfully</p>";
    echo "</div>";

    // Show final statistics
    echo "<div class='card'>";
    echo "<h2>📈 Final Database Statistics</h2>";

    $tables = ['departments', 'lecturers', 'users', 'options', 'leave_requests', 'face_recognition_logs'];
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
    echo "<h3 class='success'>🎉 Database setup completed successfully!</h3>";
    echo "<p>The RP Attendance System is now ready to use.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='admin-dashboard.php' class='btn btn-primary' style='margin-right: 10px; padding: 10px 20px; text-decoration: none; background: #007bff; color: white; border-radius: 5px;'>Go to Admin Dashboard</a>";
    echo "<a href='assign-hod.php' class='btn btn-success' style='padding: 10px 20px; text-decoration: none; background: #28a745; color: white; border-radius: 5px;'>Assign HODs</a>";
    echo "</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<h2 class='error'>❌ Database Setup Failed</h2>";
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