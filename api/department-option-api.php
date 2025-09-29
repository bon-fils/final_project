<?php
/**
 * Centralized Department-Option API
 * Provides consistent department-option relationship management
 * Version: 1.0
 */

require_once "../config.php";
session_start();
require_once "../session_check.php";

// Allow demo access for department-option API when called from registration page
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isFromRegistration = strpos($referer, 'register-student.php') !== false;

if (!$isFromRegistration) {
    require_role(['admin', 'lecturer', 'hod']);
}

// Use the same database connection as the main application
require_once "../config.php";

// Make PDO available to all functions
global $pdo;

// Set JSON response headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Request handler
try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'get_options':
            handleGetOptions();
            break;

        case 'validate_relationship':
            handleValidateRelationship();
            break;

        case 'get_department_stats':
            handleGetDepartmentStats();
            break;

        case 'get_option_details':
            handleGetOptionDetails();
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get options for a specific department
 */

function handleGetOptions() {
    global $pdo;

    // Check if department_id is provided (for admin usage)
    $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);

    if (!$departmentId) {
        // Fallback to HoD's department ID from session (for HOD usage) by joining through lecturers table
        $hod_id = $_SESSION['user_id'];
        $deptStmt = $pdo->prepare("
            SELECT d.id
            FROM departments d
            JOIN lecturers l ON d.hod_id = l.id
            JOIN users u ON l.email = u.email AND u.role = 'hod'
            WHERE u.id = ?
        ");
        $deptStmt->execute([$hod_id]);
        $department = $deptStmt->fetch(PDO::FETCH_ASSOC);

        if (!$department) {
            throw new Exception('Department not found for HoD');
        }

        $departmentId = $department['id'];
    }

    try {
        // Check if department exists
         $stmt = $pdo->prepare("
             SELECT id, name
             FROM departments
             WHERE id = ?
         ");
         $stmt->execute([$departmentId]);
         $department = $stmt->fetch(PDO::FETCH_ASSOC);

         if (!$department) {
             throw new Exception('Department not found');
         }

        // Get options for the department
         $stmt = $pdo->prepare("
             SELECT id, name
             FROM options
             WHERE department_id = ?
             ORDER BY name
         ");
        $stmt->execute([$departmentId]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Check if any options exist
        if (empty($options)) {
            echo json_encode([
                'success' => true,
                'department_id' => $departmentId,
                'department_name' => $department['name'],
                'options' => [],
                'count' => 0,
                'message' => 'No active programs found for this department',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $options,
            'count' => count($options),
            'message' => count($options) === 1 ? '1 option found' : count($options) . ' options found',
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (PDOException $e) {
        error_log("Database error in getOptions: " . $e->getMessage());
        throw new Exception('Failed to retrieve options from database: ' . $e->getMessage());
    }
}

/**
 * Validate department-option relationship
 */
function handleValidateRelationship() {
    global $pdo;
    $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);
    $optionId = filter_input(INPUT_POST, 'option_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'option_id', FILTER_VALIDATE_INT);

    if (!$departmentId || $departmentId <= 0) {
        throw new Exception('Valid department ID is required');
    }

    if (!$optionId || $optionId <= 0) {
        throw new Exception('Valid option ID is required');
    }

    try {
        // Check if both department and option exist
         $stmt = $pdo->prepare("
             SELECT
                 d.id as dept_id, d.name as dept_name,
                 o.id as opt_id, o.name as opt_name
             FROM departments d
             INNER JOIN options o ON o.department_id = d.id
             WHERE d.id = ? AND o.id = ?
         ");
         $stmt->execute([$departmentId, $optionId]);
         $relationship = $stmt->fetch(PDO::FETCH_ASSOC);

         if (!$relationship) {
             echo json_encode([
                 'success' => true,
                 'valid' => false,
                 'department_id' => $departmentId,
                 'option_id' => $optionId,
                 'message' => 'Option does not belong to specified department',
                 'details' => 'No relationship found between department and option',
                 'timestamp' => date('Y-m-d H:i:s')
             ]);
             return;
         }

         // Relationship is valid if it exists
         $isValid = true;

        echo json_encode([
             'success' => true,
             'valid' => $isValid,
             'department_id' => $departmentId,
             'option_id' => $optionId,
             'department_name' => $relationship['dept_name'],
             'option_name' => $relationship['opt_name'],
             'message' => $isValid ? 'Valid relationship' : 'Invalid relationship',
             'timestamp' => date('Y-m-d H:i:s')
         ]);

    } catch (PDOException $e) {
        error_log("Database error in validateRelationship: " . $e->getMessage());
        throw new Exception('Failed to validate relationship: ' . $e->getMessage());
    }
}

/**
 * Get department statistics including option counts
 */
function handleGetDepartmentStats() {
    global $pdo;
    try {
        $stmt = $pdo->query("
             SELECT
                 d.id,
                 d.name as department_name,
                 COUNT(DISTINCT o.id) as total_options,
                 COUNT(DISTINCT s.id) as enrolled_students
             FROM departments d
             LEFT JOIN options o ON d.id = o.department_id
             LEFT JOIN students s ON d.id = s.department_id
             GROUP BY d.id, d.name
             ORDER BY d.name
         ");

        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'departments' => $stats,
            'total_departments' => count($stats),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (PDOException $e) {
        error_log("Database error in getDepartmentStats: " . $e->getMessage());
        throw new Exception('Failed to retrieve department statistics');
    }
}

/**
 * Get detailed information about a specific option
 */
function handleGetOptionDetails() {
    global $pdo;
    $optionId = filter_input(INPUT_GET, 'option_id', FILTER_VALIDATE_INT);

    if (!$optionId) {
        throw new Exception('Valid option ID is required');
    }

    try {
        $stmt = $pdo->prepare("
             SELECT
                 o.id,
                 o.name,
                 d.id as department_id,
                 d.name as department_name,
                 COUNT(s.id) as enrolled_students
             FROM options o
             INNER JOIN departments d ON o.department_id = d.id
             LEFT JOIN students s ON o.id = s.option_id
             WHERE o.id = ?
             GROUP BY o.id, o.name, d.id, d.name
         ");
        $stmt->execute([$optionId]);
        $option = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$option) {
            throw new Exception('Option not found or inactive');
        }

        echo json_encode([
            'success' => true,
            'option' => $option,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

    } catch (PDOException $e) {
        error_log("Database error in getOptionDetails: " . $e->getMessage());
        throw new Exception('Failed to retrieve option details');
    }
}
?>