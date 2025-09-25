<?php
/**
 * HOD Assignment API
 * Handles all AJAX requests for HOD assignment functionality
 * Automatically creates user accounts when assigning HODs
 */

session_start();
require_once __DIR__ . "/../config.php";
require_once __DIR__ . "/../session_check.php";
require_role(['admin']);

// Set JSON response headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Initialize response structure
$response = [
    'status' => 'error',
    'message' => 'Unknown error occurred',
    'data' => null,
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Validate CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf_token)) {
            throw new Exception('CSRF token validation failed');
        }
    }

    $action = $_GET['action'] ?? '';

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
            $response['message'] = 'Invalid action specified';
    }

} catch (PDOException $e) {
    error_log("Database Error in HOD API: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Database error occurred';

} catch (Exception $e) {
    error_log("Error in HOD API: " . $e->getMessage());
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

// Output JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
exit;

/**
 * Handle get_departments action - Get all departments with current HOD assignments
 */
function handleGetDepartments(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT 
            d.id, 
            d.name as dept_name,
            d.hod_id,
            CASE 
                WHEN l.id IS NOT NULL THEN CONCAT(l.first_name, ' ', l.last_name)
                ELSE 'Not Assigned'
            END as hod_name,
            l.email as hod_email,
            u.username as hod_username
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON u.email = l.email AND u.role = 'hod'
        ORDER BY d.name
    ");

    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add additional metadata
    foreach ($departments as &$dept) {
        $dept['is_assigned'] = !empty($dept['hod_id']);
        $dept['assignment_status'] = $dept['is_assigned'] ? 'assigned' : 'unassigned';
        $dept['has_user_account'] = !empty($dept['hod_username']);
        
        // Get program count for each department
        $programStmt = $pdo->prepare("SELECT COUNT(*) as program_count FROM options WHERE department_id = ?");
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
            l.first_name,
            l.last_name,
            CONCAT(l.first_name, ' ', l.last_name) as full_name,
            l.email,
            l.education_level,
            l.department_id,
            l.role,
            l.phone,
            u.username,
            u.id as user_id
        FROM lecturers l
        LEFT JOIN users u ON u.email = l.email AND u.role IN ('lecturer', 'hod')
        WHERE l.role IN ('lecturer', 'hod')
        ORDER BY l.first_name, l.last_name
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
 */
function handleAssignHod(PDO $pdo): array {
    // Validate input
    $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $hod_id = !empty($_POST['hod_id']) ? filter_input(INPUT_POST, 'hod_id', FILTER_VALIDATE_INT) : null;

    if (!$department_id || $department_id <= 0) {
        throw new Exception('Invalid department selected');
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
        $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE id = ? AND role IN ('lecturer', 'hod')");
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

    // Start transaction for atomic operation
    $pdo->beginTransaction();
    try {
        // If assigning a new HOD, remove them from any previous HOD role
        if ($hod_id) {
            $stmt = $pdo->prepare("UPDATE departments SET hod_id = NULL WHERE hod_id = ?");
            $stmt->execute([$hod_id]);
        }

        // Update the department with the new HOD
        $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
        $stmt->execute([$hod_id, $department_id]);

        // If assigning a HOD, create/update user account
        if ($hod_id) {
            createOrUpdateHodUserAccount($pdo, $lecturer);
        }

        // Get assignment details for response
        $stmt = $pdo->prepare("
            SELECT 
                d.name as dept_name,
                CASE 
                    WHEN l.id IS NOT NULL THEN CONCAT(l.first_name, ' ', l.last_name)
                    ELSE 'Not Assigned'
                END as hod_name,
                u.username as hod_username
            FROM departments d
            LEFT JOIN lecturers l ON l.id = ?
            LEFT JOIN users u ON u.email = l.email AND u.role = 'hod'
            WHERE d.id = ?
        ");
        $stmt->execute([$hod_id, $department_id]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log the assignment
        error_log(sprintf(
            "HOD Assignment: Department '%s' (ID: %d) assigned to %s",
            $assignment['dept_name'],
            $department_id,
            $hod_id ? "HOD '{$assignment['hod_name']}' (ID: {$hod_id})" : 'no HOD (unassigned)'
        ));

        $pdo->commit();

        return [
            'status' => 'success',
            'message' => $hod_id ? 'HOD assigned successfully and user account created/updated' : 'HOD assignment removed successfully',
            'data' => $assignment
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("HOD Assignment Transaction Failed: " . $e->getMessage());
        throw new Exception('Database transaction failed: ' . $e->getMessage());
    }
}

/**
 * Create or update HOD user account in users table
 */
function createOrUpdateHodUserAccount(PDO $pdo, array $lecturer): void {
    // Generate username from first name and last name
    $username = generateUsername($lecturer['first_name'], $lecturer['last_name']);
    $password = generateTemporaryPassword();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$lecturer['email']]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_user) {
        // Update existing user to HOD role
        $stmt = $pdo->prepare("UPDATE users SET username = ?, role = 'hod', password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$username, $hashed_password, $existing_user['id']]);
        
        error_log("Updated existing user to HOD role: {$lecturer['email']}");
    } else {
        // Create new HOD user account
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, created_at, updated_at) 
            VALUES (?, ?, ?, 'hod', NOW(), NOW())
        ");
        $stmt->execute([$username, $lecturer['email'], $hashed_password]);
        
        error_log("Created new HOD user account: {$lecturer['email']} with temporary password");
    }

    // Log the user account creation (you might want to email the credentials in a real system)
    error_log("HOD User Account - Username: {$username}, Temporary Password: {$password}");
}

/**
 * Generate username from first and last name
 */
function generateUsername(string $firstName, string $lastName): string {
    $baseUsername = strtolower($firstName . '.' . $lastName);
    $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername);
    
    // Check if username exists and add number if needed
    global $pdo;
    $username = $baseUsername;
    $counter = 1;
    
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() == 0) {
            break;
        }
        $username = $baseUsername . $counter;
        $counter++;
    }
    
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
 */
function handleGetAssignmentStats(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM departments) as total_departments,
            (SELECT COUNT(*) FROM departments WHERE hod_id IS NOT NULL) as assigned_departments,
            (SELECT COUNT(*) FROM departments WHERE hod_id IS NULL) as unassigned_departments,
            (SELECT COUNT(*) FROM lecturers WHERE role IN ('lecturer', 'hod')) as total_lecturers,
            (SELECT COUNT(DISTINCT hod_id) FROM departments WHERE hod_id IS NOT NULL) as lecturers_assigned_as_hod,
            (SELECT COUNT(*) FROM users WHERE role = 'hod') as hod_user_accounts
    ");

    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add calculated fields
    $stats['assignment_percentage'] = $stats['total_departments'] > 0
        ? round(($stats['assigned_departments'] / $stats['total_departments']) * 100, 1)
        : 0;

    $stats['available_lecturers'] = $stats['total_lecturers'] - $stats['lecturers_assigned_as_hod'];
    $stats['accounts_missing'] = $stats['assigned_departments'] - $stats['hod_user_accounts'];

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
            d.name as dept_name,
            d.hod_id,
            CASE 
                WHEN l.id IS NOT NULL THEN CONCAT(l.first_name, ' ', l.last_name)
                ELSE 'Not Assigned'
            END as hod_name,
            l.email as hod_email,
            l.education_level as hod_education,
            u.username as hod_username
        FROM departments d
        LEFT JOIN lecturers l ON d.hod_id = l.id
        LEFT JOIN users u ON u.email = l.email AND u.role = 'hod'
        WHERE d.id = ?
    ");

    $stmt->execute([$department_id]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$department) {
        throw new Exception('Department not found');
    }

    // Get program count and list
    $programStmt = $pdo->prepare("SELECT COUNT(*) as program_count FROM options WHERE department_id = ?");
    $programStmt->execute([$department_id]);
    $department['program_count'] = $programStmt->fetchColumn();

    $programListStmt = $pdo->prepare("SELECT name FROM options WHERE department_id = ? ORDER BY name");
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
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM lecturers WHERE id = ? AND role IN ('lecturer', 'hod')");
        $stmt->execute([$hod_id]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $validation_result['hod_valid'] = (bool)$lecturer;
        $validation_result['hod_name'] = $lecturer ? $lecturer['first_name'] . ' ' . $lecturer['last_name'] : null;
        $validation_result['hod_email'] = $lecturer['email'] ?? null;

        if ($validation_result['hod_valid']) {
            // Check for conflicts
            $stmt = $pdo->prepare("SELECT name FROM departments WHERE hod_id = ? AND id != ?");
            $stmt->execute([$hod_id, $department_id]);
            $conflicts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($conflicts)) {
                $validation_result['conflicts'] = $conflicts;
                $validation_result['warnings'][] = 'This lecturer is already assigned as HOD to other departments';
            }

            // Check if user account already exists
            $stmt = $pdo->prepare("SELECT username FROM users WHERE email = ? AND role = 'hod'");
            $stmt->execute([$lecturer['email']]);
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

    // Get lecturer details
    $stmt = $pdo->prepare("SELECT * FROM lecturers WHERE id = ? AND role IN ('lecturer', 'hod')");
    $stmt->execute([$hod_id]);
    $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        throw new Exception('Lecturer not found');
    }

    try {
        createOrUpdateHodUserAccount($pdo, $lecturer);
        
        return [
            'status' => 'success',
            'message' => 'HOD user account created/updated successfully'
        ];
    } catch (Exception $e) {
        throw new Exception('Failed to create HOD user account: ' . $e->getMessage());
    }
}

?>