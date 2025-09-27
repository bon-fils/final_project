<?php
/**
 * Test script to verify analytics endpoint
 */

require_once "config.php";

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== Testing Analytics Endpoint ===\n\n";

    // Test the analytics queries one by one
    echo "1. Testing department attendance query...\n";

    $stmt = $pdo->prepare("
        SELECT
            d.name as department,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
            COUNT(*) as total_count,
            ROUND(
                CASE
                    WHEN COUNT(*) > 0
                    THEN (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0) / COUNT(*)
                    ELSE 0
                END, 1
            ) as attendance_rate
        FROM departments d
        LEFT JOIN students s ON d.id = s.department_id
        LEFT JOIN attendance_records ar ON s.id = ar.student_id
        GROUP BY d.id, d.name
        ORDER BY attendance_rate DESC
    ");

    $stmt->execute();
    $dept_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Department query successful: " . count($dept_results) . " departments\n";

    foreach ($dept_results as $dept) {
        echo "  - {$dept['department']}: {$dept['attendance_rate']}% ({$dept['total_count']} records)\n";
    }

    echo "\n2. Testing daily trends query...\n";

    $stmt = $pdo->prepare("
        SELECT
            DATE(ar.recorded_at) as date,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
            COUNT(*) as total_count
        FROM attendance_records ar
        WHERE DATE(ar.recorded_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(ar.recorded_at)
        ORDER BY date
    ");

    $stmt->execute();
    $trend_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Daily trends query successful: " . count($trend_results) . " days\n";

    foreach ($trend_results as $trend) {
        echo "  - {$trend['date']}: {$trend['present_count']}/{$trend['absent_count']} ({$trend['total_count']} total)\n";
    }

    echo "\n3. Testing course performance query...\n";

    $stmt = $pdo->prepare("
        SELECT
            c.name as course_name,
            c.course_code,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
            COUNT(*) as total_count,
            ROUND(
                CASE
                    WHEN COUNT(*) > 0
                    THEN (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0) / COUNT(*)
                    ELSE 0
                END, 1
            ) as attendance_rate
        FROM courses c
        LEFT JOIN attendance_sessions s ON c.id = s.course_id
        LEFT JOIN attendance_records ar ON s.id = ar.session_id
        GROUP BY c.id, c.name, c.course_code
        HAVING total_count > 0
        ORDER BY attendance_rate DESC
        LIMIT 10
    ");

    $stmt->execute();
    $course_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ Course performance query successful: " . count($course_results) . " courses\n";

    foreach ($course_results as $course) {
        echo "  - {$course['course_name']}: {$course['attendance_rate']}% ({$course['total_count']} records)\n";
    }

    echo "\n4. Testing HOD reports query...\n";

    $stmt = $pdo->prepare("
        SELECT
            d.name as department_name,
            CONCAT(l.first_name, ' ', l.last_name) as hod_name,
            COUNT(DISTINCT c.id) as courses_count,
            COUNT(DISTINCT s.id) as students_count,
            COUNT(DISTINCT ar.id) as attendance_records
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN courses c ON d.id = c.department_id
        LEFT JOIN students s ON d.id = s.department_id
        LEFT JOIN attendance_sessions sess ON c.id = sess.course_id
        LEFT JOIN attendance_records ar ON sess.id = ar.session_id
        GROUP BY d.id, d.name, l.first_name, l.last_name
        ORDER BY d.name
    ");

    $stmt->execute();
    $hod_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✅ HOD reports query successful: " . count($hod_results) . " departments\n";

    foreach ($hod_results as $hod) {
        echo "  - {$hod['department_name']}: {$hod['hod_name']} ({$hod['students_count']} students, {$hod['attendance_records']} records)\n";
    }

    echo "\n✅ All analytics queries working correctly!\n";

    // Test the actual endpoint
    echo "\n5. Testing actual analytics endpoint...\n";

    // Simulate the AJAX request
    $_GET['ajax'] = '1';
    $_GET['action'] = 'get_analytics';

    ob_start();
    include 'admin-reports.php';
    $output = ob_get_clean();

    $json_data = json_decode($output, true);

    if ($json_data && isset($json_data['department_attendance'])) {
        echo "✅ Analytics endpoint working: " . count($json_data['department_attendance']) . " departments\n";
        echo "✅ Daily trends: " . count($json_data['daily_trends']) . " days\n";
        echo "✅ Course performance: " . count($json_data['course_performance']) . " courses\n";
        echo "✅ HOD reports: " . count($json_data['hod_reports']) . " departments\n";
    } else {
        echo "❌ Analytics endpoint failed. Response: " . $output . "\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>