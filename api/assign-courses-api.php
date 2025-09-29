<?php
/**
 * Course Assignment API
 * Handles all course assignment operations for HoD users
 * Features: Enhanced security, caching, comprehensive error handling
 */

require_once "../config.php"; // Must be first - defines SESSION_LIFETIME
session_start();
require_once "../session_check.php";
require_once "../cache_utils.php";

// Enhanced security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Ensure user is logged in and is HoD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access - HoD role required',
        'error_code' => 'UNAUTHORIZED'
    ]);
    exit;
}

// Validate CSRF token for POST requests
$post_data = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $json = json_decode(file_get_contents('php://input'), true);
        $csrf_token = $json['csrf_token'] ?? '';
        $post_data = $json;
    } else {
        $csrf_token = $_POST['csrf_token'] ?? '';
        $post_data = $_POST;
    }
    if (!validate_csrf_token($csrf_token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'CSRF token validation failed',
            'error_code' => 'CSRF_INVALID'
        ]);
        exit;
    }
}

try {
    // Validate action parameter
    $action = $_GET['action'] ?? $post_data['action'] ?? '';
    if (empty($action)) {
        throw new Exception('Action parameter is required', 400);
    }

    switch ($action) {
        case 'get_courses':
            echo json_encode(handleGetCourses($pdo, $_SESSION['user_id']));
            break;

        case 'get_lecturers':
            echo json_encode(handleGetLecturers($pdo, $_SESSION['user_id']));
            break;

        case 'get_assigned_courses':
            echo json_encode(handleGetAssignedCourses($pdo, $_SESSION['user_id'], $_POST));
            break;

        case 'save_course_assignments':
            echo json_encode(handleSaveCourseAssignments($pdo, $_SESSION['user_id'], $_POST));
            break;

        case 'get_lecturer_statistics':
            echo json_encode(handleGetLecturerStatistics($pdo, $_SESSION['user_id']));
            break;

        case 'get_course_assignment_overview':
            echo json_encode(handleGetCourseAssignmentOverview($pdo, $_SESSION['user_id']));
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified',
                'error_code' => 'INVALID_ACTION',
                'available_actions' => ['get_courses', 'get_lecturers', 'get_assigned_courses', 'save_course_assignments', 'get_lecturer_statistics', 'get_course_assignment_overview']
            ]);
            break;
    }

} catch (PDOException $e) {
    error_log("Course assignment API database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error_code' => 'DATABASE_ERROR'
    ]);

} catch (Exception $e) {
    error_log("Course assignment API error: " . $e->getMessage());
    http_response_code($e->getCode() ?: 400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode() ?: 'GENERAL_ERROR'
    ]);
}

/**
 * Handler Functions
 * Each function handles a specific action with proper validation and error handling
 */

/**
 * Handle get_courses action - Get all courses for HoD's department
 */
function handleGetCourses(PDO $pdo, int $hod_id): array {
    try {
        // Get HoD's department with validation
        $department = getHodDepartment($pdo, $hod_id);
        if (!$department) {
            return [
                'success' => false,
                'message' => 'No department assigned to this HoD',
                'error_code' => 'NO_DEPARTMENT'
            ];
        }

        // Get all unassigned courses for the department (lecturer_id IS NULL)
        $stmt = $pdo->prepare("
            SELECT id, name as course_name, course_code, department_id, credits, duration_hours, status
            FROM courses
            WHERE department_id = ? AND (lecturer_id IS NULL OR lecturer_id = 0)
            ORDER BY name
        ");
        $stmt->execute([$department['id']]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'message' => 'Courses retrieved successfully',
            'data' => $courses,
            'count' => count($courses),
            'department_id' => $department['id'],
            'department_name' => $department['name']
        ];

    } catch (PDOException $e) {
        error_log("Error in handleGetCourses: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to retrieve courses',
            'error_code' => 'COURSE_RETRIEVAL_ERROR'
        ];
    }
}

/**
 * Handle get_lecturers action - Get all lecturers in HoD's department
 */
function handleGetLecturers(PDO $pdo, int $hod_id): array {
    try {
        // Get HoD's department with validation
        $department = getHodDepartment($pdo, $hod_id);
        if (!$department) {
            return [
                'success' => false,
                'message' => 'No department assigned to this HoD',
                'error_code' => 'NO_DEPARTMENT'
            ];
        }

        $stmt = $pdo->prepare("
            SELECT
                l.id,
                l.first_name,
                l.last_name,
                l.email,
                l.phone,
                l.education_level,
                l.gender,
                u.username,
                u.role as user_role
            FROM lecturers l
            INNER JOIN users u ON l.email = u.email
            WHERE l.department_id = ?
                AND u.role = 'lecturer'
                AND l.role IN ('lecturer', 'hod')
            ORDER BY l.first_name, l.last_name
        ");
        $stmt->execute([$department['id']]);
        $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add additional metadata
        foreach ($lecturers as &$lecturer) {
            $lecturer['full_name'] = $lecturer['first_name'] . ' ' . $lecturer['last_name'];
            $lecturer['has_user_account'] = !empty($lecturer['username']);
        }

        return [
            'success' => true,
            'message' => 'Lecturers retrieved successfully',
            'data' => $lecturers,
            'count' => count($lecturers),
            'department_id' => $department['id'],
            'department_name' => $department['name']
        ];

    } catch (PDOException $e) {
        error_log("Error in handleGetLecturers: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to retrieve lecturers',
            'error_code' => 'LECTURER_RETRIEVAL_ERROR'
        ];
    }
}

/**
 * Handle get_assigned_courses action - Get courses assigned to a specific lecturer
 */
function handleGetAssignedCourses(PDO $pdo, int $hod_id, array $post_data): array {
    try {
        // Validate input
        $lecturer_id = filter_var($post_data['lecturer_id'] ?? 0, FILTER_VALIDATE_INT);
        if (!$lecturer_id || $lecturer_id <= 0) {
            return [
                'success' => false,
                'message' => 'Valid lecturer ID is required',
                'error_code' => 'INVALID_LECTURER_ID'
            ];
        }

        // Verify lecturer belongs to HoD's department
        $department = getHodDepartment($pdo, $hod_id);
        if (!$department) {
            return [
                'success' => false,
                'message' => 'No department assigned to this HoD',
                'error_code' => 'NO_DEPARTMENT'
            ];
        }

        // Verify lecturer authorization
        $verify_stmt = $pdo->prepare("
            SELECT id FROM lecturers
            WHERE id = ? AND department_id = ?
        ");
        $verify_stmt->execute([$lecturer_id, $department['id']]);
        if (!$verify_stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Unauthorized access to lecturer data',
                'error_code' => 'UNAUTHORIZED_LECTURER_ACCESS'
            ];
        }

        // Get currently assigned courses
        $stmt = $pdo->prepare("
            SELECT
                c.id,
                c.course_name,
                c.course_code,
                c.department_id
            FROM courses c
            WHERE c.lecturer_id = ?
            ORDER BY c.course_name
        ");
        $stmt->execute([$lecturer_id]);
        $assigned_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'message' => 'Assigned courses retrieved successfully',
            'data' => $assigned_courses,
            'count' => count($assigned_courses),
            'lecturer_id' => $lecturer_id
        ];

    } catch (PDOException $e) {
        error_log("Error in handleGetAssignedCourses: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to retrieve assigned courses',
            'error_code' => 'ASSIGNED_COURSES_ERROR'
        ];
    }
}

/**
 * Handle save_course_assignments action - Save course assignments for a lecturer
 */
function handleSaveCourseAssignments(PDO $pdo, int $hod_id, array $post_data): array {
    try {
        // Validate input
        $lecturer_id = filter_var($post_data['lecturer_id'] ?? 0, FILTER_VALIDATE_INT);
        $course_ids = $post_data['course_ids'] ?? [];

        if (!$lecturer_id || $lecturer_id <= 0) {
            return [
                'success' => false,
                'message' => 'Valid lecturer ID is required',
                'error_code' => 'INVALID_LECTURER_ID'
            ];
        }

        // Validate course_ids is an array
        if (!is_array($course_ids)) {
            return [
                'success' => false,
                'message' => 'Course IDs must be an array',
                'error_code' => 'INVALID_COURSE_IDS_FORMAT'
            ];
        }

        // Get and verify HoD's department
        $department = getHodDepartment($pdo, $hod_id);
        if (!$department) {
            return [
                'success' => false,
                'message' => 'No department assigned to this HoD',
                'error_code' => 'NO_DEPARTMENT'
            ];
        }

        // Verify lecturer authorization
        $verify_stmt = $pdo->prepare("
            SELECT id, first_name, last_name FROM lecturers
            WHERE id = ? AND department_id = ?
        ");
        $verify_stmt->execute([$lecturer_id, $department['id']]);
        $lecturer = $verify_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturer) {
            return [
                'success' => false,
                'message' => 'Unauthorized access to lecturer or lecturer not found',
                'error_code' => 'UNAUTHORIZED_LECTURER_ACCESS'
            ];
        }

        // Validate all course IDs belong to the department
        if (!empty($course_ids)) {
            $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as valid_count
                FROM courses
                WHERE id IN ($placeholders) AND department_id = ?
            ");
            $stmt->execute(array_merge($course_ids, [$department['id']]));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['valid_count'] != count($course_ids)) {
                return [
                    'success' => false,
                    'message' => 'One or more courses do not belong to your department',
                    'error_code' => 'INVALID_COURSE_DEPARTMENT'
                ];
            }
        }

        // Start transaction
        $pdo->beginTransaction();

        try {
            // Remove all existing assignments for this lecturer
            $stmt = $pdo->prepare("UPDATE courses SET lecturer_id = NULL WHERE lecturer_id = ?");
            $stmt->execute([$lecturer_id]);

            // Assign new courses if any selected
            if (!empty($course_ids)) {
                $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
                $stmt = $pdo->prepare("
                    UPDATE courses
                    SET lecturer_id = ?
                    WHERE id IN ($placeholders)
                ");
                $stmt->execute(array_merge([$lecturer_id], $course_ids));
            }

            $pdo->commit();

            // Log the assignment activity
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, created_at)
                VALUES (?, 'course_assignment', ?, NOW())
            ");
            $log_stmt->execute([
                $hod_id,
                "Assigned " . count($course_ids) . " courses to lecturer '{$lecturer['first_name']} {$lecturer['last_name']}' (ID: $lecturer_id)"
            ]);

            return [
                'success' => true,
                'message' => 'Course assignments saved successfully',
                'data' => [
                    'lecturer_id' => $lecturer_id,
                    'lecturer_name' => $lecturer['first_name'] . ' ' . $lecturer['last_name'],
                    'courses_assigned' => count($course_ids),
                    'department_id' => $department['id'],
                    'department_name' => $department['name']
                ]
            ];

        } catch (Exception $e) {
            $pdo->rollBack();
            throw new Exception('Transaction failed: ' . $e->getMessage());
        }

    } catch (PDOException $e) {
        error_log("Error in handleSaveCourseAssignments: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to save course assignments',
            'error_code' => 'SAVE_ASSIGNMENTS_ERROR'
        ];
    }
}

/**
 * Handle get_lecturer_statistics action - Get cached lecturer statistics
 */
function handleGetLecturerStatistics(PDO $pdo, int $hod_id): array {
    try {
        // Get HoD's department with validation
        $department = getHodDepartment($pdo, $hod_id);
        if (!$department) {
            return [
                'success' => false,
                'message' => 'No department assigned to this HoD',
                'error_code' => 'NO_DEPARTMENT'
            ];
        }

        // Create cache key based on department
        $cache_key = "lecturer_stats_dept_{$department['id']}";

        // Use cached query with 5-minute TTL for statistics
        $statistics = cached_query(
            $pdo,
            "SELECT
                COUNT(*) as total_lecturers,
                COUNT(CASE WHEN gender = 'Male' THEN 1 END) as male_lecturers,
                COUNT(CASE WHEN gender = 'Female' THEN 1 END) as female_lecturers,
                COUNT(CASE WHEN education_level = 'PhD' THEN 1 END) as phd_holders,
                COUNT(CASE WHEN education_level = 'Master\\'s' THEN 1 END) as masters_holders,
                COUNT(CASE WHEN education_level = 'Bachelor\\'s' THEN 1 END) as bachelors_holders,
                AVG(CASE WHEN phone IS NOT NULL AND phone != '' THEN 1 ELSE 0 END) as contact_info_completeness
            FROM lecturers
            WHERE department_id = ?",
            [$department['id']],
            $cache_key,
            300 // 5 minutes TTL
        );

        if (!empty($statistics)) {
            $stats = $statistics[0];

            return [
                'success' => true,
                'message' => 'Statistics retrieved successfully',
                'statistics' => [
                    'total_lecturers' => (int)($stats['total_lecturers'] ?? 0),
                    'male_lecturers' => (int)($stats['male_lecturers'] ?? 0),
                    'female_lecturers' => (int)($stats['female_lecturers'] ?? 0),
                    'phd_holders' => (int)($stats['phd_holders'] ?? 0),
                    'masters_holders' => (int)($stats['masters_holders'] ?? 0),
                    'bachelors_holders' => (int)($stats['bachelors_holders'] ?? 0),
                    'contact_info_completeness' => round((float)($stats['contact_info_completeness'] ?? 0) * 100, 1) . '%',
                    'gender_ratio' => $stats['total_lecturers'] > 0 ?
                        round(($stats['male_lecturers'] / $stats['total_lecturers']) * 100, 1) . '% male' : 'N/A'
                ],
                'cached' => cache_has($cache_key),
                'department_id' => $department['id'],
                'department_name' => $department['name'],
                'last_updated' => date('Y-m-d H:i:s')
            ];
        } else {
            return [
                'success' => false,
                'message' => 'No statistics available',
                'error_code' => 'NO_STATISTICS'
            ];
        }

    } catch (PDOException $e) {
        error_log("Error in handleGetLecturerStatistics: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to retrieve statistics',
            'error_code' => 'STATISTICS_ERROR'
        ];
    }
}

/**
 * Handle get_course_assignment_overview action - Get overview of all assignments
 */
function handleGetCourseAssignmentOverview(PDO $pdo, int $hod_id): array {
    try {
        // Get HoD's department with validation
        $department = getHodDepartment($pdo, $hod_id);
        if (!$department) {
            return [
                'success' => false,
                'message' => 'No department assigned to this HoD',
                'error_code' => 'NO_DEPARTMENT'
            ];
        }

        $stmt = $pdo->prepare("
            SELECT
                l.id,
                l.first_name,
                l.last_name,
                l.email,
                l.phone,
                l.education_level,
                u.username,
                u.role as user_role,
                GROUP_CONCAT(
                    DISTINCT CONCAT(c.course_name, ' (', c.course_code, ')')
                    ORDER BY c.course_name SEPARATOR ', '
                ) as courses,
                COUNT(DISTINCT c.id) as course_count,
                COUNT(DISTINCT s.id) as session_count
            FROM lecturers l
            INNER JOIN users u ON l.email = u.email
            LEFT JOIN courses c ON l.id = c.lecturer_id
            LEFT JOIN attendance_sessions s ON c.id = s.course_id
            WHERE l.department_id = ?
            GROUP BY l.id, l.first_name, l.last_name, l.email, l.phone, l.education_level, u.username, u.role
            ORDER BY l.first_name, l.last_name
        ");
        $stmt->execute([$department['id']]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add additional metadata
        foreach ($assignments as &$assignment) {
            $assignment['full_name'] = $assignment['first_name'] . ' ' . $assignment['last_name'];
            $assignment['has_user_account'] = !empty($assignment['username']);
            $assignment['has_contact_info'] = !empty($assignment['phone']);
            $assignment['education_level'] = $assignment['education_level'] ?: 'Not specified';
        }

        return [
            'success' => true,
            'message' => 'Course assignment overview retrieved successfully',
            'data' => $assignments,
            'count' => count($assignments),
            'summary' => [
                'total_lecturers' => count($assignments),
                'lecturers_with_courses' => count(array_filter($assignments, fn($a) => $a['course_count'] > 0)),
                'lecturers_without_courses' => count(array_filter($assignments, fn($a) => $a['course_count'] == 0)),
                'total_course_assignments' => array_sum(array_column($assignments, 'course_count')),
                'total_sessions' => array_sum(array_column($assignments, 'session_count'))
            ],
            'department_id' => $department['id'],
            'department_name' => $department['name']
        ];

    } catch (PDOException $e) {
        error_log("Error in handleGetCourseAssignmentOverview: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to retrieve assignment overview',
            'error_code' => 'OVERVIEW_ERROR'
        ];
    }
}

/**
 * Helper function to get HoD's department with validation
 */
function getHodDepartment(PDO $pdo, int $hod_id): ?array {
    static $cache = [];

    if (isset($cache[$hod_id])) {
        return $cache[$hod_id];
    }

    $stmt = $pdo->prepare("
        SELECT d.id, d.name
        FROM departments d
        JOIN lecturers l ON d.hod_id = l.id
        JOIN users u ON l.email = u.email AND u.role = 'hod'
        WHERE u.id = ?
    ");
    $stmt->execute([$hod_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    $cache[$hod_id] = $department ?: null;
    return $cache[$hod_id];
}
?>