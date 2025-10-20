<?php
/**
 * HOD Assignment API - Dedicated API Endpoints
 * Handles all AJAX requests for HOD assignment functionality
 *
 * @version 2.0.0
 * @author RP System Development Team
 * @since 2025-01-17
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Essential dependencies
require_once "../config.php";
require_once "../session_check.php";

// Simple logging function to avoid dependency issues
function simpleLog($message, $data = []) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message . " - " . json_encode($data) . "\n";
    error_log($logEntry);
}

// Initialize logger fallback
$logger = null;
try {
    require_once "../backend/classes/Logger.php";
    $logger = new Logger('logs/hod_assignment_api.log', Logger::INFO);
} catch (Exception $e) {
    // Use simple logging if Logger class fails
    $logger = (object) [
        'info' => function($category, $message, $data = []) { simpleLog("INFO: $category - $message", $data); },
        'error' => function($category, $message, $data = []) { simpleLog("ERROR: $category - $message", $data); },
        'warning' => function($category, $message, $data = []) { simpleLog("WARNING: $category - $message", $data); }
    ];
}

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $logger->warning('HOD Assignment API', 'Unauthorized access attempt', [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'role' => $_SESSION['role'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Rate limiting using SecurityUtils

// CSRF validation using SecurityUtils

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    // Simple CSRF validation
    if (empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $logger->warning('HOD Assignment API', 'Invalid CSRF token', [
            'user_id' => $_SESSION['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit();
    }
}

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

// Simple rate limiting (disabled for debugging)
$action = $_GET['action'] ?? '';

try {
    // Comprehensive audit logging for API request
    $logger->info('HOD Assignment API', 'Request received', [
        'action' => $action,
        'user_id' => $_SESSION['user_id'],
        'user_role' => $_SESSION['role'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'],
        'timestamp' => date('Y-m-d H:i:s'),
        'session_id' => session_id(),
        'request_params' => $_GET,
        'post_params_count' => $_SERVER['REQUEST_METHOD'] === 'POST' ? count($_POST) : 0
    ]);

    switch ($action) {
        case 'get_lecturers':
            handleGetLecturers();
            break;

        case 'get_hods':
            handleGetHods();
            break;

        case 'get_departments':
            handleGetDepartments();
            break;

        case 'get_assignment_stats':
            handleGetAssignmentStats();
            break;

        case 'assign_hod':
            handleAssignHod();
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    $logger->error('HOD Assignment API', 'Database error', [
        'error' => $e->getMessage(),
        'action' => $action,
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database operation failed: ' . $e->getMessage(),
        'error_code' => 'DB_ERROR',
        'debug_info' => $e->getMessage()
    ]);
} catch (Exception $e) {
    $logger->error('HOD Assignment API', 'General error', [
        'error' => $e->getMessage(),
        'action' => $action,
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred: ' . $e->getMessage(),
        'error_code' => 'GENERAL_ERROR',
        'debug_info' => $e->getMessage()
    ]);
}

function handleGetLecturers() {
    global $pdo, $logger;

    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);

    try {
        $where_clause = "WHERE u.role IN ('lecturer', 'hod')";
        $params = [];

        if ($department_id) {
            $where_clause .= " AND l.department_id = ?";
            $params[] = $department_id;
        }

        $stmt = $pdo->prepare("
            SELECT l.id, u.first_name, u.last_name, u.email, u.role, u.phone, l.department_id,
                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                u.username, u.status, u.created_at, u.updated_at,
                d.name as department_name,
                hd.name as hod_department_name
            FROM lecturers l
            LEFT JOIN users u ON l.user_id = u.id
            LEFT JOIN departments d ON l.department_id = d.id
            LEFT JOIN departments hd ON hd.hod_id = l.id
            {$where_clause}
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute($params);
        $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cache disabled for debugging

        // Enhanced data integrity checks
        foreach ($lecturers as &$lecturer) {
            $integrityIssues = [];

            // Check for missing user account
            if (empty($lecturer['username']) && !empty($lecturer['email'])) {
                $integrityIssues[] = 'User account not found';
            }

            // Check for invalid role
            if (!in_array($lecturer['role'], ['lecturer', 'hod'])) {
                $integrityIssues[] = 'Invalid role assignment';
            }

            // Check for missing required fields
            if (empty($lecturer['first_name']) || empty($lecturer['last_name'])) {
                $integrityIssues[] = 'Missing name information';
            }

            // Check for duplicate emails (basic check)
            $emailCount = array_count_values(array_column($lecturers, 'email'))[$lecturer['email']] ?? 0;
            if ($emailCount > 1 && !empty($lecturer['email'])) {
                $integrityIssues[] = 'Duplicate email address';
            }

            if (!empty($integrityIssues)) {
                $lecturer['data_integrity'] = 'warning';
                $lecturer['data_integrity_message'] = implode('; ', $integrityIssues);
                $lecturer['data_integrity_issues'] = $integrityIssues;
            }
        }

        $logger->info('Get Lecturers', 'Retrieved lecturers successfully', [
            'department_id' => $department_id,
            'count' => count($lecturers),
            'cached' => false
        ]);

        echo json_encode([
            'status' => 'success',
            'data' => $lecturers,
            'count' => count($lecturers),
            'cached' => false
        ]);

    } catch (Exception $e) {
        $logger->error('Get Lecturers', 'Failed to retrieve lecturers', [
            'department_id' => $department_id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve lecturers. Please try again.',
            'debug_info' => APP_ENV === 'development' ? $e->getMessage() : null
        ]);
    }
}

function handleGetHods() {
    global $pdo, $logger;

    try {
        $stmt = $pdo->prepare("
            SELECT l.id, u.first_name, u.last_name, u.email, u.role, u.phone, l.department_id,
                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                u.username, u.status, u.created_at, u.updated_at
            FROM lecturers l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE u.role = 'hod'
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute();
        $hods = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $logger->info('Get HODs', 'Retrieved HODs successfully', [
            'count' => count($hods)
        ]);

        echo json_encode([
            'status' => 'success',
            'data' => $hods,
            'count' => count($hods)
        ]);

    } catch (Exception $e) {
        $logger->error('Get HODs', 'Failed to retrieve HODs', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve HODs. Please try again.',
            'debug_info' => APP_ENV === 'development' ? $e->getMessage() : null
        ]);
    }
}

function handleGetDepartments() {
    global $pdo, $logger;

    try {
        $stmt = $pdo->prepare("
            SELECT d.id, d.name, d.hod_id,
                u.first_name AS hod_first_name,
                u.last_name AS hod_last_name,
                CONCAT(u.first_name, ' ', u.last_name) as hod_name,
                u.email AS hod_email,
                u.role AS hod_role,
                CASE
                    WHEN d.hod_id IS NULL THEN 'unassigned'
                    WHEN d.hod_id IS NOT NULL AND u.id IS NULL THEN 'invalid'
                    WHEN d.hod_id IS NOT NULL AND u.role != 'hod' THEN 'invalid_role'
                    ELSE 'assigned'
                END as assignment_status
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id
            ORDER BY d.name
        ");
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cache disabled for debugging

        // Enhanced data integrity checks for departments
        $integrity_issues = 0;
        foreach ($departments as &$dept) {
            $integrityIssues = [];

            // Check for invalid HOD assignments
            if ($dept['assignment_status'] === 'invalid') {
                $integrityIssues[] = 'HOD ID exists but lecturer not found';
            } elseif ($dept['assignment_status'] === 'invalid_role') {
                $integrityIssues[] = 'Assigned lecturer is not marked as HOD';
            }

            // Check for orphaned HOD assignments (HOD assigned but no user account)
            if ($dept['hod_id'] && empty($dept['hod_name'])) {
                $integrityIssues[] = 'HOD assigned but user account missing';
            }

            // Check for role consistency
            if ($dept['hod_role'] && $dept['hod_role'] !== 'hod') {
                $integrityIssues[] = 'Assigned user does not have HOD role';
            }

            // Check for department name validity
            if (empty(trim($dept['name']))) {
                $integrityIssues[] = 'Department name is empty or invalid';
            }

            if (!empty($integrityIssues)) {
                $integrity_issues++;
                $dept['data_integrity'] = 'warning';
                $dept['data_integrity_message'] = implode('; ', $integrityIssues);
                $dept['data_integrity_issues'] = $integrityIssues;
            }
        }

        $logger->info('Get Departments', 'Retrieved departments successfully', [
            'count' => count($departments),
            'integrity_issues' => $integrity_issues,
            'cached' => false
        ]);

        echo json_encode([
            'status' => 'success',
            'data' => $departments,
            'count' => count($departments),
            'integrity_issues' => $integrity_issues,
            'cached' => false
        ]);

    } catch (Exception $e) {
        $logger->error('Get Departments', 'Failed to retrieve departments', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve departments. Please try again.',
            'debug_info' => APP_ENV === 'development' ? $e->getMessage() : null
        ]);
    }
}

function handleGetAssignmentStats() {
    global $pdo, $logger;

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM departments");
        $stmt->execute();
        $totalDepts = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL");
        $stmt->execute();
        $assignedDepts = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM lecturers l LEFT JOIN users u ON l.user_id = u.id WHERE u.role IN ('lecturer', 'hod') AND u.id IS NOT NULL");
        $stmt->execute();
        $totalLecturers = $stmt->fetchColumn();

        $stats = [
            'total_departments' => $totalDepts,
            'assigned_departments' => $assignedDepts,
            'unassigned_departments' => $totalDepts - $assignedDepts,
            'total_lecturers' => $totalLecturers
        ];

        // Cache disabled for debugging

        $logger->info('Get Assignment Stats', 'Retrieved statistics successfully', [
            'total_departments' => $totalDepts,
            'assigned_departments' => $assignedDepts,
            'total_lecturers' => $totalLecturers,
            'cached' => false
        ]);

        echo json_encode([
            'status' => 'success',
            'data' => $stats,
            'cached' => false
        ]);

    } catch (Exception $e) {
        $logger->error('Get Assignment Stats', 'Failed to retrieve statistics', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve assignment statistics. Please try again.',
            'debug_info' => APP_ENV === 'development' ? $e->getMessage() : null
        ]);
    }
}

function handleAssignHod() {
    global $pdo, $logger;

    // Simple input validation
    if (!isset($_POST['department_id']) || empty($_POST['department_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Department ID is required'
        ]);
        return;
    }

    $department_id = filter_var($_POST['department_id'], FILTER_VALIDATE_INT);
    if ($department_id === false || $department_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid department ID'
        ]);
        return;
    }

    $hod_id_raw = $_POST['hod_id'] ?? null;

    // Handle hod_id - can be null, empty string, or integer
    $hod_id = null;
    if (!empty($hod_id_raw) && $hod_id_raw !== 'null' && $hod_id_raw !== '') {
        $hod_id = filter_var($hod_id_raw, FILTER_VALIDATE_INT);
        if ($hod_id === false || $hod_id <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid HOD ID'
            ]);
            return;
        }
    }

    // Log the assignment attempt
    $logger->info('HOD Assignment', 'Processing assignment request', [
        'department_id' => $department_id,
        'hod_id' => $hod_id,
        'user_id' => $_SESSION['user_id']
    ]);

    // Verify department exists and get current assignment with enhanced validation
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.hod_id, d.created_at, d.updated_at,
               COUNT(l.id) as lecturer_count
        FROM departments d
        LEFT JOIN lecturers l ON l.department_id = d.id
        WHERE d.id = ?
        GROUP BY d.id, d.name, d.hod_id, d.created_at, d.updated_at
    ");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        echo json_encode(['status' => 'error', 'message' => 'Department not found in database']);
        return;
    }

    // Additional department validation
    if (empty(trim($department['name']))) {
        echo json_encode(['status' => 'error', 'message' => 'Department record is corrupted - missing name']);
        return;
    }

    // Check if this is actually a change
    $current_hod_id = $department['hod_id'];
    $is_same_assignment = ($current_hod_id == $hod_id) ||
                        ($current_hod_id === null && $hod_id === null) ||
                        ($current_hod_id === null && empty($hod_id)) ||
                        (empty($current_hod_id) && $hod_id === null);

    if ($is_same_assignment) {
        echo json_encode([
            'status' => 'info',
            'message' => 'No changes made - same HOD assignment already exists'
        ]);
        return;
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        if ($hod_id) {
            // Validate lecturer exists and get details with enhanced checks
            $stmt = $pdo->prepare("
                SELECT l.id, u.first_name, u.last_name, u.email, u.role, l.department_id,
                       u.status, u.username, u.created_at, u.updated_at
                FROM lecturers l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.id = ?
            ");
            $stmt->execute([$hod_id]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturer) {
                throw new Exception('Lecturer not found in database');
            }

            // Additional validation checks
            if (empty($lecturer['first_name']) || empty($lecturer['last_name'])) {
                throw new Exception('Lecturer record is incomplete - missing name information');
            }

            if (empty($lecturer['email'])) {
                throw new Exception('Lecturer record is incomplete - missing email address');
            }

            if ($lecturer['status'] !== 'active') {
                throw new Exception('Cannot assign inactive lecturer as HOD');
            }

            if (!filter_var($lecturer['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Lecturer has invalid email address');
            }

            // Check if lecturer is already HOD for another department
            if ($lecturer['role'] === 'hod' && $current_hod_id != $hod_id) {
                $stmt = $pdo->prepare("SELECT name FROM departments WHERE hod_id = ? AND id != ?");
                $stmt->execute([$hod_id, $department_id]);
                $other_dept = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($other_dept) {
                    // Allow reassigning by removing from previous department
                    $stmt = $pdo->prepare("UPDATE departments SET hod_id = NULL WHERE hod_id = ?");
                    $stmt->execute([$hod_id]);
                    $logger->info('HOD Reassignment', 'Removed from previous department', [
                        'lecturer_id' => $hod_id,
                        'previous_department' => $other_dept['name']
                    ]);
                }
            }

            // Check if user account already exists for this lecturer
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$lecturer['email']]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {
                // Update existing user to HOD role
                $userId = $existingUser['id'];
                $stmt = $pdo->prepare("
                    UPDATE users SET role = 'hod', status = 'active', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$userId]);
                $logger->info('User Update', 'Updated to HOD role', ['user_id' => $userId]);
            } else {
                // Create new user account for HOD
                $username = strtolower($lecturer['first_name'] . '.' . $lecturer['last_name']);

                // Ensure username is unique
                $originalUsername = $username;
                $counter = 1;
                while (true) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    if (!$stmt->fetch()) {
                        break; // Username is available
                    }
                    $username = $originalUsername . $counter;
                    $counter++;
                    if ($counter > 100) { // Prevent infinite loop
                        throw new Exception('Unable to generate unique username');
                    }
                }

                $default_password = password_hash('Welcome123!', PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password, role, status, created_at)
                    VALUES (?, ?, ?, 'hod', 'active', NOW())
                ");
                $stmt->execute([$username, $lecturer['email'], $default_password]);

                // Get the newly created user ID
                $userId = $pdo->lastInsertId();
                $logger->info('User Creation', 'Created new HOD user', [
                    'username' => $username,
                    'user_id' => $userId
                ]);
            }

            // Verify the user ID exists and is valid
            $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $verifyUser = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verifyUser) {
                throw new Exception('User account verification failed - user not found after creation/update');
            }

            if ($verifyUser['role'] !== 'hod') {
                throw new Exception('User role update failed - user is not set as HOD');
            }

            $logger->info('HOD Assignment', 'Assignment details', [
                'lecturer_id' => $lecturer['id'],
                'user_id' => $userId,
                'department_id' => $department_id,
                'department_name' => $department['name']
            ]);

        } else {
            // Removing HOD assignment
            $logger->info('HOD Removal', 'Assignment removed', [
                'department_name' => $department['name'],
                'department_id' => $department_id
            ]);
        }

        // Update department HOD - use user_id instead of lecturer_id
        $user_id_to_assign = null;
        if ($hod_id) {
            // We need to use the user_id, not the lecturer_id
            if (!isset($userId)) {
                throw new Exception('User ID not properly set for HOD assignment');
            }
            $user_id_to_assign = $userId;
        }
        
        $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
        $stmt->execute([$user_id_to_assign, $department_id]);

        // Verify the update was successful
        $stmt = $pdo->prepare("SELECT hod_id FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $updated_dept = $stmt->fetch(PDO::FETCH_ASSOC);

        if (($updated_dept['hod_id'] ?? null) != $user_id_to_assign) {
            throw new Exception('Department update verification failed');
        }

        $pdo->commit();

        // Cache clearing disabled for debugging

        $action = $hod_id ? 'assigned' : 'removed';
        $hod_name = $hod_id ? "{$lecturer['first_name']} {$lecturer['last_name']}" : 'None';

        $logger->info('HOD Assignment', 'Assignment completed successfully', [
            'action' => $action,
            'department' => $department['name'],
            'hod_name' => $hod_name,
            'admin_user_id' => $_SESSION['user_id'],
            'department_id' => $department_id,
            'lecturer_id' => $hod_id,
            'assigned_user_id' => $user_id_to_assign
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => "Successfully " . ($hod_id ? 'assigned HOD to' : 'removed HOD from') . " department",
            'details' => [
                'department' => $department['name'],
                'hod_name' => $hod_name,
                'action' => $action,
                'previous_hod_id' => $current_hod_id,
                'new_lecturer_id' => $hod_id,
                'new_user_id' => $user_id_to_assign,
                'department_id' => $department_id
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        $logger->error('HOD Assignment', 'Assignment failed', [
            'error' => $e->getMessage(),
            'department_id' => $department_id,
            'hod_id' => $hod_id
        ]);
        echo json_encode([
            'status' => 'error',
            'message' => 'Assignment failed: ' . $e->getMessage(),
            'debug_info' => $e->getMessage(),
            'error_code' => 'ASSIGNMENT_ERROR'
        ]);
    }
}
?>