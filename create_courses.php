<?php
require_once 'config.php';

echo "=== CREATING SAMPLE COURSES ===\n\n";

try {
    // Create courses table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            course_code VARCHAR(50) UNIQUE NOT NULL,
            description TEXT,
            credits INT DEFAULT 3,
            department_id INT NOT NULL,
            option_id INT,
            lecturer_id INT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_department_id (department_id),
            INDEX idx_option_id (option_id),
            INDEX idx_lecturer_id (lecturer_id),
            INDEX idx_status (status),
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
            FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE SET NULL,
            FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Courses table created\n";

    // Create students table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            reg_no VARCHAR(50) UNIQUE NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            phone VARCHAR(20),
            dob DATE,
            sex ENUM('Male', 'Female', 'Other') NOT NULL,
            department_id INT NOT NULL,
            option_id INT NOT NULL,
            year_level INT NOT NULL,
            photo VARCHAR(255),
            password VARCHAR(255) NOT NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_reg_no (reg_no),
            INDEX idx_department_id (department_id),
            INDEX idx_option_id (option_id),
            INDEX idx_year_level (year_level),
            INDEX idx_status (status),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
            FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Students table created\n";

    // Create student_photos table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS student_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            photo_path VARCHAR(255) NOT NULL,
            is_primary BOOLEAN DEFAULT FALSE,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student_id (student_id),
            INDEX idx_is_primary (is_primary),
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Student photos table created\n";

    // Create attendance_sessions table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lecturer_id INT NOT NULL,
            course_id INT NOT NULL,
            option_id INT NOT NULL,
            session_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_lecturer_id (lecturer_id),
            INDEX idx_course_id (course_id),
            INDEX idx_option_id (option_id),
            INDEX idx_session_date (session_date),
            FOREIGN KEY (lecturer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            FOREIGN KEY (option_id) REFERENCES options(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Attendance sessions table created\n";

    // Create attendance_records table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance_records (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            status ENUM('present', 'absent', 'late') NOT NULL,
            method ENUM('manual', 'face_recognition', 'fingerprint') DEFAULT 'manual',
            recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_student_id (student_id),
            INDEX idx_status (status),
            INDEX idx_method (method),
            FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Attendance records table created\n";

    // Get department and option IDs
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY id LIMIT 1");
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        throw new Exception("No departments found. Please run setup_database.php first.");
    }

    $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY id LIMIT 1");
    $stmt->execute([$department['id']]);
    $option = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$option) {
        throw new Exception("No options found for department {$department['name']}.");
    }

    // Get lecturer ID (use user_id from lecturers table)
    $stmt = $pdo->query("SELECT user_id as lecturer_user_id FROM lecturers ORDER BY id LIMIT 1");
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        throw new Exception("No lecturers found.");
    }

    echo "Using department: {$department['name']} (ID: {$department['id']})\n";
    echo "Using option: {$option['name']} (ID: {$option['id']})\n";
    echo "Using lecturer user ID: {$lecturer['lecturer_user_id']}\n\n";

    // Insert sample courses
    $courses = [
        ['name' => 'Introduction to Programming', 'code' => 'CS101', 'description' => 'Basic programming concepts', 'credits' => 3],
        ['name' => 'Data Structures and Algorithms', 'code' => 'CS201', 'description' => 'Advanced data structures', 'credits' => 4],
        ['name' => 'Database Management Systems', 'code' => 'CS301', 'description' => 'Database design and management', 'credits' => 3],
        ['name' => 'Web Development', 'code' => 'CS401', 'description' => 'Modern web development', 'credits' => 3],
        ['name' => 'Software Engineering', 'code' => 'CS501', 'description' => 'Software development methodologies', 'credits' => 3]
    ];

    foreach ($courses as $course) {
        $stmt = $pdo->prepare("
            INSERT INTO courses (name, course_code, description, credits, department_id, option_id, lecturer_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $course['name'],
            $course['code'],
            $course['description'],
            $course['credits'],
            $department['id'],
            $option['id'],
            $lecturer['lecturer_user_id']
        ]);
        echo "✅ Created course: {$course['name']} ({$course['code']})\n";
    }

    // Insert sample student
    $stmt = $pdo->prepare("
        INSERT INTO students (reg_no, first_name, last_name, email, phone, dob, sex, department_id, option_id, year_level, password, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
    ");
    $stmt->execute([
        '22RP06557',
        'John',
        'Doe',
        'john.doe@rp.edu',
        '0781234567',
        '2000-01-01',
        'Male',
        $department['id'],
        $option['id'],
        3,
        password_hash('password123', PASSWORD_DEFAULT)
    ]);

    $studentId = $pdo->lastInsertId();
    echo "✅ Created sample student: John Doe (ID: $studentId)\n";

    // Create sample student photos
    $samplePhotos = [
        'uploads/29.jpg',
        'uploads/68a6f7cc789cd.png',
        'uploads/68a6f5584d0f1.png'
    ];

    foreach ($samplePhotos as $index => $photoPath) {
        if (file_exists($photoPath)) {
            $stmt = $pdo->prepare("
                INSERT INTO student_photos (student_id, photo_path, is_primary)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$studentId, $photoPath, $index === 0]);
            echo "✅ Added photo: $photoPath\n";
        }
    }

    echo "\n=== SUMMARY ===\n";
    echo "✅ Created 5 sample courses\n";
    echo "✅ Created 1 sample student with photos\n";
    echo "✅ Database is now ready for attendance sessions!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>