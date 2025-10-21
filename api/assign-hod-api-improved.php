<?php
/**
 * Improved HOD Assignment API - Fixed Version
 * Handles all AJAX requests for HOD assignment functionality with proper validation
 *
 * Key Improvements:
 * 1. Prevents one lecturer from being HOD of multiple departments
 * 2. Ensures lecturer belongs to the department before assignment
 * 3. Better validation and error handling
 *
 * @version 3.0.0
 * @author RP System Development Team
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

// Simple logging function
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
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// CSRF validation for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
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

$action = $_GET['action'] ?? '';

try {
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
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database operation failed',
        'error_code' => 'DB_ERROR'
    ]);
} catch (Exception $e) {
    $logger->error('HOD Assignment API', 'General error', [
        'error' => $e->getMessage(),
        'action' => $action,
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred',
        'error_code' => 'GENERAL_ERROR'
    ]);
}

function handleGetLecturers() {
    global $pdo, $logger;

    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);

    try {
        // Get lecturers with HOD status information
        $where_clause = "WHERE u.role IN ('lecturer', 'hod') AND u.status = 'active'";
        $params = [];

        if ($department_id) {
            $where_clause .= " AND l.department_id = ?";
            $params[] = $department_id;
        }

        $stmt = $pdo->prepare("
            SELECT l.id, u.first_name, u.last_name, u.email, u.role, u.phone, l.department_id,
                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                u.username, u.status, l.education_level,
                d.name as department_name,
                hod_dept.id as current_hod_dept_id,
                hod_dept.name as current_hod_dept_name,
                CASE 
                    WHEN hod_dept.id IS NOT NULL THEN 'already_hod'
                    WHEN u.role = 'hod' THEN 'hod_role_no_dept'
                    ELSE 'available'
                END as hod_status
            FROM lecturers l
            LEFT JOIN users u ON l.user_id = u.id
            LEFT JOIN departments d ON l.department_id = d.id
            LEFT JOIN departments hod_dept ON hod_dept.hod_id = l.id
            {$where_clause}
            ORDER BY 
                CASE WHEN hod_dept.id IS NOT NULL THEN 1 ELSE 0 END,
                u.first_name, u.last_name
        ");
        $stmt->execute($params);
        $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add additional validation flags
        foreach ($lecturers as &$lecturer) {
            $lecturer['can_be_hod'] = ($lecturer['hod_status'] === 'available');
            $lecturer['warning_message'] = '';
            
            if ($lecturer['hod_status'] === 'already_hod') {
                $lecturer['warning_message'] = "Already HOD of {$lecturer['current_hod_dept_name']}";
            } elseif ($lecturer['hod_status'] === 'hod_role_no_dept') {
                $lecturer['warning_message'] = "Has HOD role but not assigned to any department";
            }
            
            // Check if lecturer belongs to the requested department
            if ($department_id && $lecturer['department_id'] != $department_id) {
                $lecturer['can_be_hod'] = false;
                $lecturer['warning_message'] = "Not a member of this department";
            }
        }

        echo json_encode([
            'status' => 'success',
            'data' => $lecturers,
            'count' => count($lecturers),
            'department_filter' => $department_id
        ]);

    } catch (Exception $e) {
        $logger->error('Get Lecturers', 'Failed to retrieve lecturers', [
            'department_id' => $department_id,
            'error' => $e->getMessage()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve lecturers. Please try again.'
        ]);
    }
}

function handleGetDepartments() {
    global $pdo, $logger;

    try {
        $stmt = $pdo->prepare("
            SELECT d.id, d.name, d.hod_id,
                l.id as lecturer_id,
                u.first_name AS hod_first_name,
                u.last_name AS hod_last_name,
                CONCAT(u.first_name, ' ', u.last_name) as hod_name,
                u.email AS hod_email,
                u.role AS hod_role,
                l.department_id as hod_lecturer_dept_id,
                CASE
                    WHEN d.hod_id IS NULL THEN 'unassigned'
                    WHEN l.id IS NULL THEN 'invalid_lecturer'
                    WHEN u.id IS NULL THEN 'invalid_user'
                    WHEN u.role != 'hod' THEN 'invalid_role'
                    WHEN l.department_id != d.id THEN 'wrong_department'
                    ELSE 'assigned'
                END as assignment_status,
                (SELECT COUNT(*) FROM lecturers WHERE department_id = d.id) as lecturer_count
            FROM departments d
            LEFT JOIN lecturers l ON d.hod_id = l.id
            LEFT JOIN users u ON l.user_id = u.id
            ORDER BY d.name
        ");
        $stmt->execute();
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add detailed status information
        $integrity_issues = 0;
        foreach ($departments as &$dept) {
            $dept['status_message'] = '';
            $dept['can_assign'] = true;
            
            switch ($dept['assignment_status']) {
                case 'unassigned':
                    $dept['status_message'] = 'No HOD assigned';
                    break;
                case 'invalid_lecturer':
                    $dept['status_message'] = 'HOD ID exists but lecturer not found';
                    $dept['can_assign'] = false;
                    $integrity_issues++;
                    break;
                case 'invalid_user':
                    $dept['status_message'] = 'Lecturer exists but no user account';
                    $dept['can_assign'] = false;
                    $integrity_issues++;
                    break;
                case 'invalid_role':
                    $dept['status_message'] = 'Assigned user does not have HOD role';
                    $dept['can_assign'] = false;
                    $integrity_issues++;
                    break;
                case 'wrong_department':
                    $dept['status_message'] = 'HOD belongs to different department';
                    $dept['can_assign'] = false;
                    $integrity_issues++;
                    break;
                case 'assigned':
                    $dept['status_message'] = "HOD: {$dept['hod_name']}";
                    break;
            }
            
            if ($dept['lecturer_count'] == 0) {
                $dept['warning'] = 'No lecturers in this department';
            }
        }

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
            'message' => 'Failed to retrieve departments. Please try again.'
        ]);
    }
}

function handleGetAssignmentStats() {
    global $pdo, $logger;

    try {
        // Get comprehensive statistics
        $stats = [];
        
        // Total departments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments");
        $stmt->execute();
        $stats['total_departments'] = $stmt->fetchColumn();

        // Assigned departments (with valid assignments)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM departments d
            JOIN lecturers l ON d.hod_id = l.id
            JOIN users u ON l.user_id = u.id
            WHERE u.role = 'hod' AND l.department_id = d.id
        ");
        $stmt->execute();
        $stats['assigned_departments'] = $stmt->fetchColumn();

        // Invalid assignments
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM departments d
            LEFT JOIN lecturers l ON d.hod_id = l.id
            LEFT JOIN users u ON l.user_id = u.id
            WHERE d.hod_id IS NOT NULL AND (
                l.id IS NULL OR 
                u.id IS NULL OR 
                u.role != 'hod' OR 
                l.department_id != d.id
            )
        ");
        $stmt->execute();
        $stats['invalid_assignments'] = $stmt->fetchColumn();

        // Available lecturers (not already HODs)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM lecturers l
            JOIN users u ON l.user_id = u.id
            LEFT JOIN departments d ON d.hod_id = l.id
            WHERE u.role IN ('lecturer', 'hod') 
            AND u.status = 'active' 
            AND d.id IS NULL
        ");
        $stmt->execute();
        $stats['available_lecturers'] = $stmt->fetchColumn();

        // Total lecturers
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM lecturers l
            JOIN users u ON l.user_id = u.id
            WHERE u.role IN ('lecturer', 'hod') AND u.status = 'active'
        ");
        $stmt->execute();
        $stats['total_lecturers'] = $stmt->fetchColumn();

        $stats['unassigned_departments'] = $stats['total_departments'] - $stats['assigned_departments'] - $stats['invalid_assignments'];

        echo json_encode([
            'status' => 'success',
            'data' => $stats
        ]);

    } catch (Exception $e) {
        $logger->error('Get Assignment Stats', 'Failed to retrieve statistics', [
            'error' => $e->getMessage()
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve assignment statistics. Please try again.'
        ]);
    }
}

function handleAssignHod() {
    global $pdo, $logger;

    // Input validation
    if (!isset($_POST['department_id']) || empty($_POST['department_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Department ID is required']);
        return;
    }

    $department_id = filter_var($_POST['department_id'], FILTER_VALIDATE_INT);
    if ($department_id === false || $department_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid department ID']);
        return;
    }

    $hod_id_raw = $_POST['hod_id'] ?? null;
    $hod_id = null;
    if (!empty($hod_id_raw) && $hod_id_raw !== 'null' && $hod_id_raw !== '') {
        $hod_id = filter_var($hod_id_raw, FILTER_VALIDATE_INT);
        if ($hod_id === false || $hod_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid HOD ID']);
            return;
        }
    }

    $logger->info('HOD Assignment', 'Processing assignment request', [
        'department_id' => $department_id,
        'hod_id' => $hod_id,
        'user_id' => $_SESSION['user_id']
    ]);

    // Verify department exists
    $stmt = $pdo->prepare("SELECT id, name, hod_id FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        echo json_encode(['status' => 'error', 'message' => 'Department not found']);
        return;
    }

    // Check if this is the same assignment
    $current_hod_id = $department['hod_id'];
    if ($current_hod_id == $hod_id) {
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
            // CRITICAL VALIDATION: Verify lecturer exists and belongs to this department
            $stmt = $pdo->prepare("
                SELECT l.id, l.department_id, u.first_name, u.last_name, u.email, u.role, u.status
                FROM lecturers l
                JOIN users u ON l.user_id = u.id
                WHERE l.id = ?
            ");
            $stmt->execute([$hod_id]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturer) {
                throw new Exception('Lecturer not found in database');
            }

            // CRITICAL CHECK: Lecturer must belong to the department
            if ($lecturer['department_id'] != $department_id) {
                throw new Exception('Cannot assign lecturer as HOD: Lecturer does not belong to this department. Please transfer the lecturer to this department first.');
            }

            // Check if lecturer is active
            if ($lecturer['status'] !== 'active') {
                throw new Exception('Cannot assign inactive lecturer as HOD');
            }

            // CRITICAL CHECK: Prevent lecturer from being HOD of multiple departments
            $stmt = $pdo->prepare("
                SELECT d.name as dept_name 
                FROM departments d 
                WHERE d.hod_id = ? AND d.id != ?
            ");
            $stmt->execute([$hod_id, $department_id]);
            $existing_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_assignment) {
                throw new Exception("Cannot assign lecturer as HOD: {$lecturer['first_name']} {$lecturer['last_name']} is already HOD of '{$existing_assignment['dept_name']}'. A lecturer can only be HOD of one department at a time.");
            }

            // Update user role to HOD if not already
            if ($lecturer['role'] !== 'hod') {
                $stmt = $pdo->prepare("UPDATE users SET role = 'hod', updated_at = NOW() WHERE email = ?");
                $stmt->execute([$lecturer['email']]);
                $logger->info('User Role Update', 'Updated lecturer to HOD role', [
                    'email' => $lecturer['email'],
                    'lecturer_id' => $hod_id
                ]);
            }

            $action_message = "Successfully assigned {$lecturer['first_name']} {$lecturer['last_name']} as HOD of {$department['name']}";
            $hod_name = "{$lecturer['first_name']} {$lecturer['last_name']}";

        } else {
            // Removing HOD assignment
            if ($current_hod_id) {
                // Get current HOD info for logging
                $stmt = $pdo->prepare("
                    SELECT u.first_name, u.last_name 
                    FROM lecturers l 
                    JOIN users u ON l.user_id = u.id 
                    WHERE l.id = ?
                ");
                $stmt->execute([$current_hod_id]);
                $current_hod = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($current_hod) {
                    // Optionally demote user role back to lecturer
                    $stmt = $pdo->prepare("
                        UPDATE users u 
                        JOIN lecturers l ON u.id = l.user_id 
                        SET u.role = 'lecturer', u.updated_at = NOW() 
                        WHERE l.id = ?
                    ");
                    $stmt->execute([$current_hod_id]);
                }
            }
            
            $action_message = "Successfully removed HOD assignment from {$department['name']}";
            $hod_name = 'None';
        }

        // Update department HOD assignment
        $stmt = $pdo->prepare("UPDATE departments SET hod_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hod_id, $department_id]);

        // Verify the update
        $stmt = $pdo->prepare("SELECT hod_id FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $updated_dept = $stmt->fetch(PDO::FETCH_ASSOC);

        if (($updated_dept['hod_id'] ?? null) != $hod_id) {
            throw new Exception('Department update verification failed');
        }

        $pdo->commit();

        $logger->info('HOD Assignment', 'Assignment completed successfully', [
            'action' => $hod_id ? 'assigned' : 'removed',
            'department' => $department['name'],
            'hod_name' => $hod_name,
            'admin_user_id' => $_SESSION['user_id'],
            'department_id' => $department_id,
            'lecturer_id' => $hod_id
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => $action_message,
            'details' => [
                'department' => $department['name'],
                'hod_name' => $hod_name,
                'action' => $hod_id ? 'assigned' : 'removed',
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
            'message' => $e->getMessage(),
            'error_code' => 'ASSIGNMENT_ERROR'
        ]);
    }
}
?>
