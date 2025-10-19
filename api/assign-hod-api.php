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
require_once "../backend/classes/Logger.php";

// Initialize logger
$logger = new Logger('logs/hod_assignment_api.log', Logger::INFO);

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

// Rate limiting
function checkApiRateLimit() {
    $max_requests_per_minute = 30;
    $time_window = 60;

    if (!isset($_SESSION['api_requests'])) {
        $_SESSION['api_requests'] = [];
    }

    $current_time = time();
    $_SESSION['api_requests'] = array_filter($_SESSION['api_requests'], function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });

    if (count($_SESSION['api_requests']) >= $max_requests_per_minute) {
        return false;
    }

    $_SESSION['api_requests'][] = $current_time;
    return true;
}

// CSRF validation
function validateCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    if (isset($_SESSION['csrf_created']) && (time() - $_SESSION['csrf_created']) > 86400) {
        return false;
    }

    return true;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
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

// Rate limiting check
if (!checkApiRateLimit()) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait before trying again.']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    // Log API request
    $logger->info('HOD Assignment API', 'Request received', [
        'action' => $action,
        'user_id' => $_SESSION['user_id'],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD']
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
        'message' => 'Database operation failed. Please try again or contact administrator.',
        'error_code' => 'DB_ERROR'
    ]);
} catch (Exception $e) {
    $logger->error('HOD Assignment API', 'General error', [
        'error' => $e->getMessage(),
        'action' => $action,
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred: ' . htmlspecialchars($e->getMessage()),
        'error_code' => 'GENERAL_ERROR'
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

        // Validate data integrity
        foreach ($lecturers as &$lecturer) {
            if (empty($lecturer['username']) && !empty($lecturer['email'])) {
                $lecturer['data_integrity'] = 'warning';
                $lecturer['data_integrity_message'] = 'User account not found';
            }
        }

        $logger->info('Get Lecturers', 'Retrieved lecturers successfully', [
            'department_id' => $department_id,
            'count' => count($lecturers)
        ]);

        echo json_encode([
            'status' => 'success',
            'data' => $lecturers,
            'count' => count($lecturers)
        ]);

    } catch (Exception $e) {
        $logger->error('Get Lecturers', 'Failed to retrieve lecturers', [
            'department_id' => $department_id,
            'error' => $e->getMessage()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve lecturers'
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
            'error' => $e->getMessage()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve HODs'
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
                    WHEN d.hod_id IS NOT NULL AND l.id IS NULL THEN 'invalid'
                    WHEN d.hod_id IS NOT NULL AND u.role != 'hod' THEN 'invalid_role'
                    ELSE 'assigned'
                END as assignment_status
            FROM departments d
            LEFT JOIN lecturers l ON d.hod_id = l.id
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY d.name
        ");
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add data integrity warnings
        $integrity_issues = 0;
        foreach ($departments as &$dept) {
            if ($dept['assignment_status'] === 'invalid' || $dept['assignment_status'] === 'invalid_role') {
                $integrity_issues++;
                $dept['data_integrity'] = 'warning';
                $dept['data_integrity_message'] = $dept['assignment_status'] === 'invalid' ?
                    'HOD ID exists but lecturer not found' :
                    'Assigned lecturer is not marked as HOD';
            }
        }

        $logger->info('Get Departments', 'Retrieved departments successfully', [
            'count' => count($departments),
            'integrity_issues' => $integrity_issues
        ]);

        echo json_encode([
            'status' => 'success',
            'data' => $departments,
            'count' => count($departments),
            'integrity_issues' => $integrity_issues
        ]);

    } catch (Exception $e) {
        $logger->error('Get Departments', 'Failed to retrieve departments', [
            'error' => $e->getMessage()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve departments'
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

        $logger->info('Get Assignment Stats', 'Retrieved statistics successfully', [
            'total_departments' => $totalDepts,
            'assigned_departments' => $assignedDepts,
            'total_lecturers' => $totalLecturers
        ]);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_departments' => $totalDepts,
                'assigned_departments' => $assignedDepts,
                'unassigned_departments' => $totalDepts - $assignedDepts,
                'total_lecturers' => $totalLecturers
            ]
        ]);

    } catch (Exception $e) {
        $logger->error('Get Assignment Stats', 'Failed to retrieve statistics', [
            'error' => $e->getMessage()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve assignment statistics'
        ]);
    }
}

function handleAssignHod() {
    global $pdo, $logger;

    $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $hod_id_raw = $_POST['hod_id'] ?? null;

    // Validate department ID
    if (!$department_id || $department_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid department ID provided']);
        return;
    }

    // Handle hod_id - can be null, empty string, or integer
    $hod_id = null;
    if (!empty($hod_id_raw) && $hod_id_raw !== 'null' && $hod_id_raw !== '') {
        $hod_id = filter_var($hod_id_raw, FILTER_VALIDATE_INT);
        if ($hod_id === false || $hod_id < 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid HOD ID provided']);
            return;
        }
    }

    // Log the assignment attempt
    $logger->info('HOD Assignment', 'Processing assignment request', [
        'department_id' => $department_id,
        'hod_id' => $hod_id,
        'user_id' => $_SESSION['user_id']
    ]);

    // Verify department exists and get current assignment
    $stmt = $pdo->prepare("SELECT id, name, hod_id FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        echo json_encode(['status' => 'error', 'message' => 'Department not found in database']);
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
            // Validate lecturer exists and get details
            $stmt = $pdo->prepare("
                SELECT l.id, u.first_name, u.last_name, u.email, u.role, l.department_id
                FROM lecturers l
                LEFT JOIN users u ON l.user_id = u.id
                WHERE l.id = ?
            ");
            $stmt->execute([$hod_id]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturer) {
                throw new Exception('Lecturer not found in database');
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

        // Update department HOD
        $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
        $stmt->execute([$hod_id ?: null, $department_id]);

        // Verify the update was successful
        $stmt = $pdo->prepare("SELECT hod_id FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $updated_dept = $stmt->fetch(PDO::FETCH_ASSOC);

        if (($updated_dept['hod_id'] ?? null) != $hod_id) {
            throw new Exception('Department update verification failed');
        }

        $pdo->commit();

        $action = $hod_id ? 'assigned' : 'removed';
        $hod_name = $hod_id ? "{$lecturer['first_name']} {$lecturer['last_name']}" : 'None';

        $logger->info('HOD Assignment', 'Assignment completed successfully', [
            'action' => $action,
            'department' => $department['name'],
            'hod_name' => $hod_name,
            'user_id' => $_SESSION['user_id']
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => "Successfully assigned",
            'details' => [
                'department' => $department['name'],
                'hod_name' => $hod_name,
                'action' => $action,
                'previous_hod_id' => $current_hod_id,
                'new_hod_id' => $hod_id,
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
            'message' => 'Assignment failed: ' . $e->getMessage()
        ]);
    }
}
?>