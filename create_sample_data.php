<?php
require_once 'config.php';

echo "=== CREATING SAMPLE ATTENDANCE DATA ===\n\n";

try {
    // Get the student we found
    $stmt = $pdo->query("SELECT id, first_name, last_name, department_id, option_id FROM students LIMIT 1");
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo "❌ No students found\n";
        exit;
    }

    echo "Found student: {$student['first_name']} {$student['last_name']} (ID: {$student['id']})\n\n";

    // Get a lecturer for the sessions
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM lecturers LIMIT 1");
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        echo "❌ No lecturers found\n";
        exit;
    }

    echo "Found lecturer: {$lecturer['first_name']} {$lecturer['last_name']} (ID: {$lecturer['id']})\n\n";

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
            'lecturer_id' => $lecturer['id'],
            'option_id' => $student['option_id']
        ],
        [
            'session_date' => date('Y-m-d'), // Today
            'start_time' => '14:00:00',
            'end_time' => '16:00:00',
            'course_id' => $course['id'],
            'lecturer_id' => $lecturer['id'],
            'option_id' => $student['option_id']
        ],
        [
            'session_date' => date('Y-m-d', strtotime('-1 day')), // Yesterday
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'course_id' => $course['id'],
            'lecturer_id' => $lecturer['id'],
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