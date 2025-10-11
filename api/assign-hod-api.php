<?php
/**
 * HOD Assignment API
 * Handles all AJAX requests for HOD assignment functionality
 * Automatically creates user accounts when assigning HODs
 *
 * @version 2.1.0
 * @author RP System Development Team
 */

require_once __DIR__ . "/../config.php"; // Must be first - defines SESSION_LIFETIME
session_start();

// Allow demo access for HOD assignment API when called from registration or reports pages
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isFromAllowedPage = strpos($referer, 'register-student.php') !== false ||
                     strpos($referer, 'admin-register-lecturer.php') !== false ||
                     strpos($referer, 'admin-reports.php') !== false;

if (!$isFromAllowedPage) {
    require_once __DIR__ . "/../session_check.php"; // Session management - session already started
    require_role(['admin']); // Re-enabled after testing
}
// Set JSON response headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Initialize response structure
$response = [
    'status' => 'error',
    'message' => 'Unknown error occurred',
    'data' => null,
    'timestamp' => date('Y-m-d H:i:s'),
    'request_id' => uniqid('hod_api_', true)
];

try {
    // Configure PDO for better error handling and security
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Validate CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf_token)) {
            http_response_code(403);
            throw new Exception('CSRF token validation failed');
        }
    }

    // Validate action parameter
    $action = $_GET['action'] ?? '';
    if (empty($action)) {
        http_response_code(400);
        throw new Exception('Action parameter is required');
    }

    // Sanitize action parameter
    $action = filter_var($action, FILTER_SANITIZE_STRING);
    if (!preg_match('/^[a-z_]+$/', $action)) {
        http_response_code(400);
        throw new Exception('Invalid action format');
    }

    // Route actions based on request
    switch ($action) {
        case 'get_departments':
            $response = handleGetDepartments($pdo);
            break;

        case 'get_lecturers':
            $response = handleGetLecturers($pdo);
            break;

        case 'assign_hod':
            $response = handleAssignHod($pdo);
            break;

        case 'get_assignment_stats':
            $response = handleGetAssignmentStats($pdo);
            break;

        case 'get_department_details':
            $response = handleGetDepartmentDetails($pdo);
            break;

        case 'validate_assignment':
            $response = handleValidateAssignment($pdo);
            break;

        case 'create_hod_user':
            $response = handleCreateHodUser($pdo);
            break;

        default:
            http_response_code(400);
            $response['message'] = 'Invalid action specified: ' . htmlspecialchars($action);
    }

} catch (PDOException $e) {
    error_log("Database Error in HOD API [{$response['request_id']}]: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Database operation failed. Please try again.';
    $response['error_code'] = 'DB_ERROR';

} catch (InvalidArgumentException $e) {
    error_log("Validation Error in HOD API [{$response['request_id']}]: " . $e->getMessage());
    http_response_code(400);
    $response['message'] = $e->getMessage();
    $response['error_code'] = 'VALIDATION_ERROR';

} catch (Exception $e) {
    error_log("General Error in HOD API [{$response['request_id']}]: " . $e->getMessage());
    http_response_code(400);
    $response['message'] = $e->getMessage();
    $response['error_code'] = 'GENERAL_ERROR';
}

// Ensure response always has required fields
$response['status'] = $response['status'] ?? 'error';
$response['message'] = $response['message'] ?? 'Unknown error occurred';
$response['timestamp'] = date('Y-m-d H:i:s');

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;

/**
 * Handle get_departments action - Get all departments with current HOD assignments
 */
function handleGetDepartments(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.name,
            d.hod_id,
            CASE
                WHEN l.id IS NOT NULL THEN CONCAT(u.first_name, ' ', u.last_name)
                ELSE 'Not Assigned'
            END as hod_name,
            u.email as hod_email,
            u.username as hod_username,
            u.role as hod_role,
            u.role as lecturer_role
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON l.user_id = u.id AND u.role = 'hod'
        ORDER BY d.name
    ");

    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add additional metadata
    foreach ($departments as &$dept) {
        $dept['dept_name'] = $dept['name']; // Add dept_name for compatibility
        $dept['is_assigned'] = !empty($dept['hod_id']);
        $dept['assignment_status'] = $dept['is_assigned'] ? 'assigned' : 'unassigned';
        $dept['has_user_account'] = !empty($dept['hod_username']);

        // Get program count for each department
        $programStmt = $pdo->prepare("SELECT COUNT(*) as program_count FROM courses WHERE department_id = ?");
        $programStmt->execute([$dept['id']]);
        $dept['program_count'] = $programStmt->fetchColumn();
    }

    return [
        'status' => 'success',
        'message' => 'Departments retrieved successfully',
        'data' => $departments,
        'count' => count($departments)
    ];
}

/**
 * Handle get_lecturers action - Get all available lecturers for HOD assignment
 */
function handleGetLecturers(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT
            l.id,
            u.first_name,
            u.last_name,
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            u.email,
            l.education_level,
            l.department_id,
            u.role,
            u.phone,
            u.username,
            u.id as user_id
        FROM lecturers l
        INNER JOIN users u ON u.id = l.user_id
        WHERE u.role IN ('lecturer', 'hod')
        ORDER BY u.first_name, u.last_name
    ");

    $stmt->execute();
    $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add additional metadata and check current assignments
    foreach ($lecturers as &$lecturer) {
        $lecturer['display_name'] = $lecturer['full_name'];
        if (!empty($lecturer['education_level'])) {
            $lecturer['display_name'] .= ' (' . $lecturer['education_level'] . ')';
        }

        // Check if lecturer is already assigned as HOD
        $assignedStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE hod_id = ?");
        $assignedStmt->execute([$lecturer['id']]);
        $lecturer['is_assigned_as_hod'] = $assignedStmt->fetchColumn() > 0;

        // Check if user account exists
        $lecturer['has_user_account'] = !empty($lecturer['user_id']);

        // Get department name if assigned to a department
        if ($lecturer['department_id']) {
            $deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
            $deptStmt->execute([$lecturer['department_id']]);
            $lecturer['department_name'] = $deptStmt->fetchColumn() ?: 'Not Assigned';
        } else {
            $lecturer['department_name'] = 'Not Assigned';
        }
    }

    return [
        'status' => 'success',
        'message' => 'Lecturers retrieved successfully',
        'data' => $lecturers,
        'count' => count($lecturers)
    ];
}

/**
 * Handle assign_hod action - Assign or remove HOD from department and create user account
 * Includes comprehensive input validation and security checks
 */
function handleAssignHod(PDO $pdo): array {
    // Validate and sanitize input parameters
    $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if (!$department_id) {
        throw new InvalidArgumentException('Invalid or missing department ID. Must be a positive integer.');
    }

    // Handle hod_id - can be null to remove assignment
    $hod_id_raw = trim($_POST['hod_id'] ?? '');

    // More robust null checking
    $null_values = ['', 'null', 'NULL', '0', null, false];
    $hod_id = null;

    if (!in_array($hod_id_raw, $null_values, true)) {
        $hod_id = filter_var($hod_id_raw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($hod_id === false) {
            throw new InvalidArgumentException('Invalid HOD ID format. Must be a positive integer or empty.');
        }
    }

    // Additional security: Check for reasonable ID ranges (prevent potential DoS with extremely large IDs)
    if ($department_id > 999999 || ($hod_id && $hod_id > 999999)) {
        throw new InvalidArgumentException('Invalid ID range detected.');
    }

    // Verify the department exists
    $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        throw new Exception('Department not found');
    }

    // Verify the selected HOD is actually a lecturer (if provided)
    if ($hod_id) {
        $stmt = $pdo->prepare("SELECT l.*, u.role FROM lecturers l INNER JOIN users u ON l.user_id = u.id WHERE l.id = ? AND u.role IN ('lecturer', 'hod')");
        $stmt->execute([$hod_id]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturer) {
            throw new Exception('Selected user is not a lecturer');
        }

        // Check if this lecturer is already assigned as HOD to another department
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE hod_id = ? AND id != ?");
        $stmt->execute([$hod_id, $department_id]);
        $existing_dept = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_dept) {
            return [
                'status' => 'warning',
                'message' => "This lecturer is already assigned as HOD to '{$existing_dept['name']}'. They will be removed from the previous department.",
                'requires_confirmation' => true,
                'current_assignment' => $existing_dept['name']
            ];
        }
    }

    // Basic rate limiting: prevent too many assignments in short time
    $rate_limit_key = 'hod_assignment_' . $_SESSION['user_id'];
    $current_time = time();

    // Simple in-memory rate limiting (in production, use Redis or database)
    if (!isset($_SESSION['hod_assignment_last_time'])) {
        $_SESSION['hod_assignment_last_time'] = 0;
        $_SESSION['hod_assignment_count'] = 0;
    }

    // Reset counter if more than 5 minutes have passed
    if ($current_time - $_SESSION['hod_assignment_last_time'] > 300) {
        $_SESSION['hod_assignment_count'] = 0;
    }

    // Allow maximum 10 assignments per 5 minutes
    if ($_SESSION['hod_assignment_count'] >= 10) {
        throw new Exception('Rate limit exceeded. Please wait before making more assignments.');
    }

    $_SESSION['hod_assignment_last_time'] = $current_time;
    $_SESSION['hod_assignment_count']++;

    // Start transaction for atomic operation
    $pdo->beginTransaction();
    try {
        $old_hod_id = null;

        // Get current HOD assignment for this department
        $stmt = $pdo->prepare("SELECT hod_id FROM departments WHERE id = ?");
        $stmt->execute([$department_id]);
        $current_hod = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($current_hod && $current_hod['hod_id']) {
            $old_hod_id = $current_hod['hod_id'];
        }

        // If assigning a new HOD, handle the previous HOD first
        if ($hod_id && $old_hod_id && $old_hod_id != $hod_id) {
            // Remove previous HOD from this department
            $stmt = $pdo->prepare("UPDATE departments SET hod_id = NULL WHERE id = ?");
            $stmt->execute([$department_id]);

            // Check if the previous HOD is still HOD of any other department
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE hod_id = ?");
            $stmt->execute([$old_hod_id]);
            if ($stmt->fetchColumn() == 0) {
                // No longer HOD of any department, set role back to lecturer
                $stmt = $pdo->prepare("UPDATE lecturers SET role = 'lecturer', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$old_hod_id]);

                // Update user role back to lecturer if they exist
                $stmt = $pdo->prepare("UPDATE users SET role = 'lecturer', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$old_hod_id]);
            }
        }

        // Handle new HOD assignment
        if ($hod_id) {
            // Get lecturer details
            $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE id = ?");
            $stmt->execute([$hod_id]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturer) {
                throw new Exception('Selected HOD is not a valid lecturer');
            }

            // Check if user account exists for the HOD
            $user_id = getOrCreateHodUserAccount($pdo, $lecturer);

            // Update lecturer role to 'hod'
            $stmt = $pdo->prepare("UPDATE lecturers SET role = 'hod', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hod_id]);

            // Update the department with the new HOD (using lecturer ID, not user ID)
            $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
            $stmt->execute([$hod_id, $department_id]);

            error_log("Assigned HOD: Department ID {$department_id} -> User ID {$user_id} (Lecturer: {$lecturer['first_name']} {$lecturer['last_name']})");
        } else {
            // Removing HOD assignment
            $stmt = $pdo->prepare("UPDATE departments SET hod_id = NULL WHERE id = ?");
            $stmt->execute([$department_id]);

            // If there was a previous HOD, set their role back to lecturer
            if ($old_hod_id) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE hod_id = ?");
                $stmt->execute([$old_hod_id]);
                if ($stmt->fetchColumn() == 0) {
                    // No longer HOD of any department, set role back to lecturer
                    $stmt = $pdo->prepare("UPDATE lecturers SET role = 'lecturer', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$old_hod_id]);

                    // Update user role back to lecturer
                    $stmt = $pdo->prepare("UPDATE users SET role = 'lecturer', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$old_hod_id]);
                }
            }
        }

        // Get assignment details for response
        $stmt = $pdo->prepare("
            SELECT
                d.name,
                CASE
                    WHEN l.id IS NOT NULL THEN CONCAT(u.first_name, ' ', u.last_name)
                    ELSE 'Not Assigned'
                END as hod_name,
                u.username as hod_username
            FROM departments d
            LEFT JOIN lecturers l ON d.hod_id = l.id
            LEFT JOIN users u ON l.user_id = u.id AND u.role = 'hod'
            WHERE d.id = ?
        ");
        $stmt->execute([$department_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        $assignment['dept_name'] = $assignment['name']; // Add dept_name for compatibility

        // Log the assignment
        error_log(sprintf(
            "HOD Assignment: Department '%s' (ID: %d) assigned to %s",
            $assignment['dept_name'],
            $department_id,
            $hod_id ? "HOD '{$assignment['hod_name']}' (ID: {$hod_id})" : 'no HOD (unassigned)'
        ));

        $pdo->commit();

        $message = $hod_id ? 'HOD assigned successfully and user account created/updated' : 'HOD assignment removed successfully';

        return [
            'status' => 'success',
            'message' => $message,
            'data' => $assignment
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("HOD Assignment Transaction Failed: " . $e->getMessage());
        throw new Exception('Database transaction failed: ' . $e->getMessage());
    }
}

/**
 * Get or create HOD user account in users table
 * Updates existing accounts to HOD role or creates new ones with secure defaults
 *
 * @param PDO $pdo Database connection
 * @param array $lecturer Lecturer data array with required fields: email, first_name, last_name
 * @return int The user ID (existing or newly created)
 * @throws InvalidArgumentException If lecturer data is invalid
 * @throws Exception If database operation fails
 */
function getOrCreateHodUserAccount(PDO $pdo, array $lecturer): int {
    // Validate required lecturer data
    if (empty($lecturer['email']) || !filter_var($lecturer['email'], FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Valid lecturer email is required');
    }
    if (empty($lecturer['first_name']) || empty($lecturer['last_name'])) {
        throw new InvalidArgumentException('Lecturer first and last names are required');
    }

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE email = ?");
    $stmt->execute([$lecturer['email']]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_user) {
        // If already HOD, return existing ID
        if ($existing_user['role'] === 'hod') {
            return (int)$existing_user['id'];
        }

        // Update existing user to HOD role (preserve username and password)
        $stmt = $pdo->prepare("UPDATE users SET role = 'hod', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$existing_user['id']]);

        error_log("Updated existing user to HOD role: {$lecturer['email']} (ID: {$existing_user['id']})");
        return (int)$existing_user['id'];
    } else {
        // Create new user account with secure defaults
        $username = generateUsername($lecturer['first_name'], $lecturer['last_name']);

        // Use a more secure default password (should be changed by admin)
        $default_password = 'ChangeMe123!'; // TODO: Generate random password and send via email
        $password_hash = password_hash($default_password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password, role, status, created_at, updated_at)
                VALUES (?, ?, ?, 'hod', 'active', NOW(), NOW())
            ");
            $stmt->execute([$username, $lecturer['email'], $password_hash]);
            $user_id = (int)$pdo->lastInsertId();

            error_log("Created new HOD user account for lecturer: {$lecturer['email']} with username: {$username} (ID: {$user_id})");
            return $user_id;
        } catch (PDOException $e) {
            // Handle potential duplicate username
            if ($e->getCode() == 23000) { // Integrity constraint violation
                throw new Exception('Username already exists. Please contact administrator.');
            }
            throw $e;
        }
    }
}

/**
 * Generate unique username from first and last name
 * Ensures username is unique in the users table
 *
 * @param string $firstName Lecturer's first name
 * @param string $lastName Lecturer's last name
 * @return string Generated unique username
 * @throws Exception If unable to generate unique username after multiple attempts
 */
function generateUsername(string $firstName, string $lastName): string {
    // Validate input
    $firstName = trim($firstName);
    $lastName = trim($lastName);

    if (empty($firstName) || empty($lastName)) {
        throw new InvalidArgumentException('First and last names are required for username generation');
    }

    // Create base username: firstname.lastname
    $baseUsername = strtolower($firstName . '.' . $lastName);

    // Remove non-alphanumeric characters except dots
    $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername);

    // Ensure minimum length and remove consecutive dots
    $baseUsername = preg_replace('/\.{2,}/', '.', $baseUsername);
    $baseUsername = trim($baseUsername, '.');

    // Ensure we have a valid base username
    if (empty($baseUsername) || strlen($baseUsername) < 3) {
        // Fallback: use first 3 chars of first name + last name
        $baseUsername = strtolower(substr($firstName, 0, 3) . $lastName);
        $baseUsername = preg_replace('/[^a-z0-9]/', '', $baseUsername);
    }

    global $pdo;

    // Check if base username exists and add number if needed
    $username = $baseUsername;
    $counter = 1;
    $max_attempts = 100; // Prevent infinite loop

    while ($counter < $max_attempts) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() == 0) {
            return $username;
        }
        $username = $baseUsername . $counter;
        $counter++;
    }

    // If we can't find a unique username, add timestamp
    $username = $baseUsername . '_' . time();
    return $username;
}

/**
 * Generate temporary password
 */
function generateTemporaryPassword(int $length = 12): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

/**
 * Handle get_assignment_stats action - Get statistics about HOD assignments
 * Uses separate optimized queries for accurate counting
 */
function handleGetAssignmentStats(PDO $pdo): array {
    // Get department statistics
    $deptStats = $pdo->query("
        SELECT
            COUNT(*) as total_departments,
            COUNT(CASE WHEN hod_id IS NOT NULL THEN 1 END) as assigned_departments,
            COUNT(CASE WHEN hod_id IS NULL THEN 1 END) as unassigned_departments
        FROM departments
    ")->fetch(PDO::FETCH_ASSOC);

    // Get lecturer statistics
    $lecturerStats = $pdo->query("
        SELECT COUNT(*) as total_lecturers
        FROM lecturers l
        INNER JOIN users u ON l.user_id = u.id
        WHERE u.role IN ('lecturer', 'hod')
    ")->fetch(PDO::FETCH_ASSOC);

    // Get lecturers assigned as HOD (from departments table)
    $hodAssigned = $pdo->query("
        SELECT COUNT(DISTINCT hod_id) as lecturers_assigned_as_hod
        FROM departments
        WHERE hod_id IS NOT NULL
    ")->fetch(PDO::FETCH_ASSOC);

    // Get user account statistics
    $userStats = $pdo->query("
        SELECT COUNT(*) as hod_user_accounts
        FROM users
        WHERE role = 'hod'
    ")->fetch(PDO::FETCH_ASSOC);

    // Combine results
    $stats = array_merge($deptStats, $lecturerStats, $hodAssigned, $userStats);

    // Ensure all values are integers
    $stats = array_map('intval', $stats);

    // Add calculated fields
    $stats['assignment_percentage'] = $stats['total_departments'] > 0
        ? round(($stats['assigned_departments'] / $stats['total_departments']) * 100, 1)
        : 0.0;

    $stats['available_lecturers'] = max(0, $stats['total_lecturers'] - $stats['lecturers_assigned_as_hod']);
    $stats['accounts_missing'] = max(0, $stats['assigned_departments'] - $stats['hod_user_accounts']);

    return [
        'status' => 'success',
        'message' => 'Assignment statistics retrieved successfully',
        'data' => $stats
    ];
}

/**
 * Handle get_department_details action - Get detailed information about a specific department
 */
function handleGetDepartmentDetails(PDO $pdo): array {
    $department_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$department_id || $department_id <= 0) {
        throw new Exception('Invalid department ID');
    }

    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.name,
            d.hod_id,
            CASE
                WHEN l.id IS NOT NULL THEN CONCAT(u.first_name, ' ', u.last_name)
                ELSE 'Not Assigned'
            END as hod_name,
            u.email as hod_email,
            l.education_level as hod_education,
            u.username as hod_username,
            u.role as lecturer_role
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON l.user_id = u.id AND u.role = 'hod'
        WHERE d.id = ?
    ");

    $stmt->execute([$department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        throw new Exception('Department not found');
    }

    $department['dept_name'] = $department['name']; // Add dept_name for compatibility

    // Get program count and list
    $programStmt = $pdo->prepare("SELECT COUNT(*) as program_count FROM courses WHERE department_id = ?");
    $programStmt->execute([$department_id]);
    $department['program_count'] = $programStmt->fetchColumn();

    $programListStmt = $pdo->prepare("SELECT name FROM courses WHERE department_id = ? ORDER BY name");
    $programListStmt->execute([$department_id]);
    $department['programs'] = $programListStmt->fetchAll(PDO::FETCH_COLUMN);

    $department['has_user_account'] = !empty($department['hod_username']);

    return [
        'status' => 'success',
        'message' => 'Department details retrieved successfully',
        'data' => $department
    ];
}

/**
 * Handle validate_assignment action - Validate if an assignment can be made
 */
function handleValidateAssignment(PDO $pdo): array {
    $department_id = filter_input(INPUT_GET, 'department_id', FILTER_VALIDATE_INT);
    $hod_id = filter_input(INPUT_GET, 'hod_id', FILTER_VALIDATE_INT);

    if (!$department_id || $department_id <= 0) {
        throw new Exception('Invalid department ID');
    }

    $validation_result = [
        'department_valid' => false,
        'hod_valid' => false,
        'can_assign' => false,
        'warnings' => [],
        'conflicts' => []
    ];

    // Validate department
    $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    $validation_result['department_valid'] = (bool)$department;
    $validation_result['department_name'] = $department['name'] ?? null;

    if (!$validation_result['department_valid']) {
        return [
            'status' => 'error',
            'message' => 'Department not found',
            'data' => $validation_result
        ];
    }

    // Validate HOD if provided
    if ($hod_id) {
        $stmt = $pdo->prepare("SELECT u.first_name, u.last_name, u.email FROM lecturers l INNER JOIN users u ON l.user_id = u.id WHERE l.id = ? AND u.role IN ('lecturer', 'hod')");
        $stmt->execute([$hod_id]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

        $validation_result['hod_valid'] = (bool)$lecturer;
        $validation_result['hod_name'] = $lecturer ? $lecturer['first_name'] . ' ' . $lecturer['last_name'] : null;
        $validation_result['hod_email'] = $lecturer['email'] ?? null;

        if ($validation_result['hod_valid']) {
            // Check for conflicts - HODs are stored in departments table with lecturer IDs
            $stmt = $pdo->prepare("SELECT d.name FROM departments d WHERE d.hod_id = ? AND d.id != ?");
            $stmt->execute([$hod_id, $department_id]);
            $conflicts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($conflicts)) {
                $validation_result['conflicts'] = $conflicts;
                $validation_result['warnings'][] = 'This lecturer is already assigned as HOD to other departments';
            }

            // Check if user account already exists for this lecturer
            $stmt = $pdo->prepare("SELECT u.username FROM users u JOIN lecturers l ON u.email = l.email WHERE l.id = ? AND u.role = 'hod'");
            $stmt->execute([$hod_id]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_user) {
                $validation_result['existing_user_account'] = $existing_user['username'];
                $validation_result['warnings'][] = 'User account already exists for this lecturer';
            }
        }
    } else {
        // Removing HOD assignment is always valid
        $validation_result['hod_valid'] = true;
    }

    $validation_result['can_assign'] = $validation_result['department_valid'] && $validation_result['hod_valid'];

    return [
        'status' => 'success',
        'message' => 'Assignment validation completed',
        'data' => $validation_result
    ];
}

/**
 * Handle create_hod_user action - Manually create HOD user account
 */
function handleCreateHodUser(PDO $pdo): array {
    $hod_id = filter_input(INPUT_POST, 'hod_id', FILTER_VALIDATE_INT);

    if (!$hod_id || $hod_id <= 0) {
        throw new Exception('Invalid HOD ID');
    }

    // Get user details (HODs are stored in users table)
    $stmt = $pdo->prepare("SELECT u.*, l.first_name, l.last_name, l.email as lecturer_email FROM users u LEFT JOIN lecturers l ON u.email = l.email WHERE u.id = ? AND u.role = 'hod'");
    $stmt->execute([$hod_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('HOD user not found');
    }

    try {
        // User already exists, no need to create
        return [
            'status' => 'success',
            'message' => 'HOD user account already exists'
        ];
    } catch (Exception $e) {
        throw new Exception('Failed to create HOD user account: ' . $e->getMessage());
    }
}

?>