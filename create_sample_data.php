<?php
require_once 'config.php';

echo "=== CREATING TEST STUDENT REGISTRATION DATA ===\n\n";

// Create test_students table for testing
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS test_students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reg_no VARCHAR(50) NOT NULL UNIQUE,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            face_image_1 VARCHAR(255),
            face_image_2 VARCHAR(255),
            face_image_3 VARCHAR(255),
            face_image_4 VARCHAR(255),
            status ENUM('active','inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✅ Test students table created\n";
} catch (Exception $e) {
    echo "❌ Error creating test table: " . $e->getMessage() . "\n";
}

echo "=== CREATING SAMPLE ATTENDANCE DATA ===\n\n";

try {
    // Get the student we found
    $stmt = $pdo->query("SELECT s.id, u.first_name, u.last_name, s.option_id FROM students s JOIN users u ON s.user_id = u.id LIMIT 1");
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo "❌ No students found\n";
        exit;
    }

    echo "Found student: {$student['first_name']} {$student['last_name']} (ID: {$student['id']})\n\n";

    // Get a lecturer for the sessions
    $stmt = $pdo->query("SELECT l.id, l.user_id, u.first_name, u.last_name FROM lecturers l JOIN users u ON l.user_id = u.id LIMIT 1");
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        echo "❌ No lecturers found\n";
        exit;
    }

    echo "Found lecturer: {$lecturer['first_name']} {$lecturer['last_name']} (ID: {$lecturer['id']}, User ID: {$lecturer['user_id']})\n\n";

    // Get a course
    $stmt = $pdo->query("SELECT id, name FROM courses LIMIT 1");
    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$course) {
        echo "❌ No courses found\n";
        exit;
    }

    echo "Found course: {$course['name']} (ID: {$course['id']})\n\n";

    // Create sample attendance sessions for today and yesterday
    $sessions_data = [
        [
            'session_date' => date('Y-m-d'), // Today
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'course_id' => $course['id'],
            'lecturer_id' => $lecturer['user_id'],
            'option_id' => $student['option_id']
        ],
        [
            'session_date' => date('Y-m-d'), // Today
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'course_id' => $course['id'],
            'lecturer_id' => $lecturer['user_id'],
            'option_id' => $student['option_id']
        ],
        [
            'session_date' => date('Y-m-d', strtotime('-1 day')), // Yesterday
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'course_id' => $course['id'],
            'lecturer_id' => $lecturer['user_id'],
            'option_id' => $student['option_id']
        ]
    ];

    echo "Creating attendance sessions...\n";
    foreach ($sessions_data as $session) {
        $stmt = $pdo->prepare("
            INSERT INTO attendance_sessions (session_date, start_time, end_time, course_id, lecturer_id, option_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $session['session_date'],
            $session['start_time'],
            $session['end_time'],
            $session['course_id'],
            $session['lecturer_id'],
            $session['option_id']
        ]);
        echo "✅ Created session for {$session['session_date']} {$session['start_time']}\n";
    }

    // Create attendance records for the student
    echo "\nCreating attendance records...\n";
    $stmt = $pdo->prepare("SELECT id FROM attendance_sessions ORDER BY id DESC LIMIT 3");
    $stmt->execute();
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sessions as $session) {
        $stmt = $pdo->prepare("
            INSERT INTO attendance_records (student_id, session_id, status, recorded_at)
            VALUES (?, ?, 'present', NOW())
        ");
        $stmt->execute([$student['id'], $session['id']]);
        echo "✅ Created attendance record for session {$session['id']}\n";
    }

    // Create a pending leave request
    echo "\nCreating sample leave request...\n";
    $stmt = $pdo->prepare("
        INSERT INTO leave_requests (student_id, reason, status, requested_at)
        VALUES (?, 'Medical leave - Family emergency', 'pending', NOW())
    ");
    $stmt->execute([$student['id']]);
    echo "✅ Created pending leave request\n";

    echo "\n=== SUMMARY ===\n";
    echo "✅ Created 3 attendance sessions\n";
    echo "✅ Created 3 attendance records (100% attendance)\n";
    echo "✅ Created 1 pending leave request\n";
    echo "\nThe dashboard should now show real data!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>