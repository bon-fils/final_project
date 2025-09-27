<?php
require_once "../config.php"; // Must be first - defines SESSION_LIFETIME
require_once "../cache_utils.php";

// Simple session validation for API
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'hod') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access - HoD role required']);
    exit;
}

// Get HoD's department ID
$hod_id = $_SESSION['user_id'];
try {
    $deptStmt = $pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
    $deptStmt->execute([$hod_id]);
    $department = $deptStmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Department not found for HoD']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Department lookup error in API: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_overview_stats':
            $department_id = $_POST['department_id'] ?? 0;
            if (!$department_id) {
                echo json_encode(['success' => false, 'message' => 'Department ID required']);
                break;
            }

            // Create cache key
            $cache_key = "dept_overview_stats_{$department_id}";

            try {
                // Use cached query with 10-minute TTL
                $stats = cached_query(
                    $pdo,
                    "SELECT
                        (SELECT COUNT(*) FROM students WHERE department_id = ?) as total_students,
                        (SELECT COUNT(*) FROM courses WHERE department_id = ?) as total_courses,
                        (SELECT COUNT(DISTINCT c.id) FROM courses c
                         INNER JOIN attendance_sessions s ON c.id = s.course_id
                         WHERE c.department_id = ?) as active_courses,
                        COALESCE(AVG(CASE
                            WHEN ar.status = 1 THEN 100.0
                            ELSE 0.0
                        END), 0) as avg_attendance,
                        (SELECT COUNT(DISTINCT s.id) FROM attendance_sessions s
                         INNER JOIN courses c ON s.course_id = c.id
                         WHERE c.department_id = ?) as total_sessions,
                        COALESCE((SELECT SUM(c.credits) FROM courses c WHERE c.department_id = ?), 0) as total_credits",
                    [$department_id, $department_id, $department_id, $department_id, $department_id],
                    $cache_key,
                    600 // 10 minutes TTL
                );

                if (!empty($stats) && isset($stats[0])) {
                    echo json_encode([
                        'success' => true,
                        'statistics' => [
                            'total_students' => (int)($stats[0]['total_students'] ?? 0),
                            'total_courses' => (int)($stats[0]['total_courses'] ?? 0),
                            'active_courses' => (int)($stats[0]['active_courses'] ?? 0),
                            'avg_attendance_rate' => round((float)($stats[0]['avg_attendance'] ?? 0), 1),
                            'total_sessions' => (int)($stats[0]['total_sessions'] ?? 0),
                            'total_credits' => (int)($stats[0]['total_credits'] ?? 0)
                        ],
                        'cached' => cache_has($cache_key)
                    ]);
                } else {
                    // Fallback: try direct query without cache
                    $stmt = $pdo->prepare("
                        SELECT
                            (SELECT COUNT(*) FROM students WHERE department_id = ?) as total_students,
                            (SELECT COUNT(*) FROM courses WHERE department_id = ?) as total_courses,
                            (SELECT COUNT(DISTINCT c.id) FROM courses c
                             INNER JOIN attendance_sessions s ON c.id = s.course_id
                             WHERE c.department_id = ?) as active_courses,
                            COALESCE(AVG(CASE
                                WHEN ar.status = 1 THEN 100.0
                                ELSE 0.0
                            END), 0) as avg_attendance,
                            (SELECT COUNT(DISTINCT s.id) FROM attendance_sessions s
                             INNER JOIN courses c ON s.course_id = c.id
                             WHERE c.department_id = ?) as total_sessions,
                            COALESCE((SELECT SUM(c.credits) FROM courses c WHERE c.department_id = ?), 0) as total_credits
                    ");
                    $stmt->execute([$department_id, $department_id, $department_id, $department_id, $department_id]);
                    $direct_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!empty($direct_stats) && isset($direct_stats[0])) {
                        echo json_encode([
                            'success' => true,
                            'statistics' => [
                                'total_students' => (int)($direct_stats[0]['total_students'] ?? 0),
                                'total_courses' => (int)($direct_stats[0]['total_courses'] ?? 0),
                                'active_courses' => (int)($direct_stats[0]['active_courses'] ?? 0),
                                'avg_attendance_rate' => round((float)($direct_stats[0]['avg_attendance'] ?? 0), 1),
                                'total_sessions' => (int)($direct_stats[0]['total_sessions'] ?? 0),
                                'total_credits' => (int)($direct_stats[0]['total_credits'] ?? 0)
                            ],
                            'cached' => false,
                            'fallback' => true
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'No statistics available for department',
                            'debug' => [
                                'department_id' => $department_id,
                                'cache_key' => $cache_key,
                                'cached_result_count' => count($stats ?? []),
                                'direct_result_count' => count($direct_stats ?? [])
                            ]
                        ]);
                    }
                }
            } catch (PDOException $e) {
                error_log("Department stats query error: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error occurred',
                    'debug' => ['error' => $e->getMessage()]
                ]);
            }
            break;

        case 'get_detailed_report':
            $department_id = $_POST['department_id'] ?? 0;
            $option_id = $_POST['option_id'] ?? '';
            $course_id = $_POST['course_id'] ?? '';
            $year_level = $_POST['year_level'] ?? '';
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';

            if (!$department_id || !$option_id || !$course_id || !$year_level || !$start_date || !$end_date) {
                echo json_encode(['success' => false, 'message' => 'All parameters required']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT
                    DATE(ar.recorded_at) AS date,
                    COUNT(CASE WHEN ar.status = 1 THEN 1 END) AS present,
                    COUNT(CASE WHEN ar.status = 0 THEN 1 END) AS absent,
                    COUNT(*) as total,
                    ROUND(
                        (COUNT(CASE WHEN ar.status = 1 THEN 1 END) * 100.0 / COUNT(*)), 1
                    ) as attendance_rate
                FROM attendance_records ar
                INNER JOIN students s ON ar.student_id = s.id
                INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
                WHERE s.department_id = ?
                  AND s.option_id = ?
                  AND s.year_level = ?
                  AND sess.course_id = ?
                  AND DATE(ar.recorded_at) BETWEEN ? AND ?
                GROUP BY DATE(ar.recorded_at)
                ORDER BY DATE(ar.recorded_at) ASC
            ");

            $stmt->execute([$department_id, $option_id, $year_level, $course_id, $start_date, $end_date]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;

        case 'get_course_performance':
            $department_id = $_POST['department_id'] ?? 0;
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';

            if (!$department_id || !$start_date || !$end_date) {
                echo json_encode(['success' => false, 'message' => 'Department ID and date range required']);
                break;
            }

            $stmt = $pdo->prepare("
                SELECT
                    c.name as course_name,
                    COUNT(DISTINCT ar.session_id) as total_sessions,
                    COUNT(DISTINCT ar.student_id) as total_students,
                    AVG(CASE
                        WHEN ar.status = 1 THEN 100.0
                        ELSE 0.0
                    END) as avg_attendance_rate
                FROM courses c
                INNER JOIN attendance_sessions sess ON c.id = sess.course_id
                INNER JOIN attendance_records ar ON sess.id = ar.session_id
                INNER JOIN students s ON ar.student_id = s.id
                WHERE c.department_id = ?
                  AND DATE(ar.recorded_at) BETWEEN ? AND ?
                GROUP BY c.id, c.name
                ORDER BY avg_attendance_rate DESC
            ");

            $stmt->execute([$department_id, $start_date, $end_date]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;

        case 'get_student_attendance_summary':
            $department_id = $_POST['department_id'] ?? 0;
            $option_id = $_POST['option_id'] ?? '';
            $course_id = $_POST['course_id'] ?? '';
            $limit = (int)($_POST['limit'] ?? 10);

            if (!$department_id) {
                echo json_encode(['success' => false, 'message' => 'Department ID required']);
                break;
            }

            $query = "
                SELECT
                    s.first_name,
                    s.last_name,
                    s.student_id,
                    COUNT(ar.id) as total_sessions,
                    COUNT(CASE WHEN ar.status = 1 THEN 1 END) as present_sessions,
                    ROUND(
                        (COUNT(CASE WHEN ar.status = 1 THEN 1 END) * 100.0 / COUNT(ar.id)), 1
                    ) as attendance_rate
                FROM students s
                LEFT JOIN attendance_records ar ON s.id = ar.student_id
                LEFT JOIN attendance_sessions sess ON ar.session_id = sess.id
                WHERE s.department_id = ?
            ";

            $params = [$department_id];

            if ($option_id) {
                $query .= " AND s.option_id = ?";
                $params[] = $option_id;
            }

            if ($course_id) {
                $query .= " AND sess.course_id = ?";
                $params[] = $course_id;
            }

            $query .= "
                GROUP BY s.id, s.first_name, s.last_name, s.student_id
                HAVING total_sessions > 0
                ORDER BY attendance_rate DESC
                LIMIT ?
            ";
            $params[] = $limit;

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    error_log("Department reports API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>