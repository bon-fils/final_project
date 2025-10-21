<?php
/**
 * Test Page for HOD Students Queries
 * Tests all database queries used in hod-students.php
 */

require_once "config.php";
session_start();

// Test HOD authentication
$user_id = $_SESSION['user_id'] ?? 56; // Default test user
$department_id = null;

echo "<h1>HOD Students Queries Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border:1px solid #ddd;margin:10px 0;}</style>";

// Test 1: HOD Department Assignment
echo "<h2>Test 1: HOD Department Assignment</h2>";
try {
    $lecturer_stmt = $pdo->prepare("SELECT id, gender, dob, id_number, department_id, education_level FROM lecturers WHERE user_id = ?");
    $lecturer_stmt->execute([$user_id]);
    $lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        echo "<div class='error'>❌ No lecturer record found for user $user_id</div>";
        exit;
    }

    echo "<div class='success'>✅ Lecturer found: " . htmlspecialchars($lecturer['id']) . "</div>";

    // Try multiple approaches to find department assignment
    $dept_result = null;

    // Approach 1: Correct way - hod_id points to lecturers.id
    $stmt = $pdo->prepare("
        SELECT d.name as department_name, d.id as department_id, 'direct' as match_type
        FROM departments d
        WHERE d.hod_id = ? AND d.hod_id IS NOT NULL
    ");
    $stmt->execute([$lecturer['id']]);
    $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$dept_result) {
        // Approach 2: Legacy way - hod_id might point to users.id
        $stmt = $pdo->prepare("
            SELECT d.name as department_name, d.id as department_id, 'legacy' as match_type
            FROM departments d
            WHERE d.hod_id = ? AND d.hod_id IS NOT NULL
        ");
        $stmt->execute([$user_id]);
        $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$dept_result && $lecturer['department_id']) {
        // Approach 3: Check if lecturer's department_id matches any department's hod_id
        $stmt = $pdo->prepare("
            SELECT d.name as department_name, d.id as department_id, 'department_match' as match_type
            FROM departments d
            WHERE d.id = ? AND d.hod_id IS NOT NULL
        ");
        $stmt->execute([$lecturer['department_id']]);
        $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($dept_result) {
        $department_id = $dept_result['department_id'];
        echo "<div class='success'>✅ Department found: " . htmlspecialchars($dept_result['department_name']) . " (ID: $department_id, Method: {$dept_result['match_type']})</div>";
    } else {
        echo "<div class='error'>❌ No department assignment found</div>";
        exit;
    }

} catch (PDOException $e) {
    echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
    exit;
}

// Test 2: Main Students Query
echo "<h2>Test 2: Main Students Query</h2>";
if ($department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.reg_no, s.user_id, s.year_level, s.student_id_number,
                     u.first_name, u.last_name, u.email, u.status, u.phone, u.created_at,
                     o.name as program_name, o.id as option_id,
                     COUNT(ar.id) as total_attendance_30d,
                     COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count_30d,
                     COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count_30d,
                     COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count_30d,
                     MAX(ar.recorded_at) as last_attendance_date
             FROM students s
             JOIN users u ON s.user_id = u.id
             JOIN options o ON s.option_id = o.id
             LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
                 AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE o.department_id = ?
             GROUP BY s.reg_no, s.user_id, s.year_level, s.student_id_number,
                      u.first_name, u.last_name, u.email, u.status, u.phone, u.created_at,
                      o.name, o.id
             ORDER BY s.year_level, u.last_name, u.first_name
             LIMIT 5
         ");
        $stmt->execute([$department_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<div class='success'>✅ Query executed successfully. Found " . count($students) . " students.</div>";

        if (!empty($students)) {
            echo "<h3>Sample Student Data:</h3>";
            echo "<pre>";
            print_r(array_slice($students, 0, 2)); // Show first 2 students
            echo "</pre>";
        }

    } catch (PDOException $e) {
        echo "<div class='error'>❌ Main students query failed: " . $e->getMessage() . "</div>";
        echo "<div class='warning'>SQL State: " . $e->getCode() . "</div>";
    }
}

// Test 3: Statistics Query
echo "<h2>Test 3: Statistics Query</h2>";
if ($department_id) {
    try {
        $stats_stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT s.reg_no) as total_students,
                COUNT(DISTINCT CASE WHEN u.status = 'active' THEN s.reg_no END) as active_students,
                COUNT(DISTINCT CASE WHEN u.status = 'inactive' THEN s.reg_no END) as inactive_students,
                COUNT(DISTINCT CASE WHEN s.year_level = '1' THEN s.reg_no END) as year1_count,
                COUNT(DISTINCT CASE WHEN s.year_level = '2' THEN s.reg_no END) as year2_count,
                COUNT(DISTINCT CASE WHEN s.year_level = '3' THEN s.reg_no END) as year3_count,
                COUNT(DISTINCT CASE WHEN s.year_level = '4' THEN s.reg_no END) as year4_count,
                COUNT(DISTINCT o.id) as programs_count,
                COUNT(DISTINCT CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN s.reg_no END) as new_students_30d,
                COUNT(DISTINCT CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN s.reg_no END) as new_students_7d,
                AVG(CASE WHEN ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN
                    CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END
                END) as avg_attendance_rate_30d
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN options o ON s.option_id = o.id
            LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
                AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE o.department_id = ?
        ");
        $stats_stmt->execute([$department_id]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

        echo "<div class='success'>✅ Statistics query executed successfully.</div>";
        echo "<pre>";
        print_r($stats);
        echo "</pre>";

    } catch (PDOException $e) {
        echo "<div class='error'>❌ Statistics query failed: " . $e->getMessage() . "</div>";
        echo "<div class='warning'>SQL State: " . $e->getCode() . "</div>";
    }
}

// Test 4: Attendance Distribution Query
echo "<h2>Test 4: Attendance Distribution Query</h2>";
if ($department_id) {
    try {
        $attendance_stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT CASE WHEN attendance_rate >= 80 THEN attendance_stats.reg_no END) as excellent_count,
                COUNT(DISTINCT CASE WHEN attendance_rate >= 60 AND attendance_rate < 80 THEN attendance_stats.reg_no END) as good_count,
                COUNT(DISTINCT CASE WHEN attendance_rate >= 40 AND attendance_rate < 60 THEN attendance_stats.reg_no END) as fair_count,
                COUNT(DISTINCT CASE WHEN attendance_rate < 40 THEN attendance_stats.reg_no END) as poor_count
            FROM (
                SELECT s.reg_no,
                       CASE WHEN COUNT(ar.id) > 0
                           THEN (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) / COUNT(ar.id)) * 100
                           ELSE 0 END as attendance_rate
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN options o ON s.option_id = o.id
                LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
                    AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                WHERE o.department_id = ?
                GROUP BY s.reg_no
            ) as attendance_stats
        ");
        $attendance_stmt->execute([$department_id]);
        $attendance_dist = $attendance_stmt->fetch(PDO::FETCH_ASSOC);

        echo "<div class='success'>✅ Attendance distribution query executed successfully.</div>";
        echo "<pre>";
        print_r($attendance_dist);
        echo "</pre>";

    } catch (PDOException $e) {
        echo "<div class='error'>❌ Attendance distribution query failed: " . $e->getMessage() . "</div>";
        echo "<div class='warning'>SQL State: " . $e->getCode() . "</div>";
    }
}

// Test 5: Table Structure Check
echo "<h2>Test 5: Table Structure Check</h2>";
try {
    // Check students table structure
    $stmt = $pdo->query("DESCRIBE students");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='success'>✅ Students table structure retrieved.</div>";
    echo "<h3>Students Table Columns:</h3>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li><strong>{$col['Field']}</strong> - {$col['Type']} " . ($col['Null'] === 'NO' ? '(NOT NULL)' : '(NULL)') . "</li>";
    }
    echo "</ul>";

    // Check if 'id' column exists
    $has_id = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'id') {
            $has_id = true;
            break;
        }
    }

    if ($has_id) {
        echo "<div class='success'>✅ 'id' column exists in students table</div>";
    } else {
        echo "<div class='warning'>⚠️ 'id' column does NOT exist in students table. Using 'reg_no' as primary identifier.</div>";
    }

} catch (PDOException $e) {
    echo "<div class='error'>❌ Table structure check failed: " . $e->getMessage() . "</div>";
}

echo "<hr>";
echo "<p><strong>Test completed at:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>