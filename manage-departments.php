<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Enhanced JSON response function with improved error handling
function jsonResponse($status, $message, $extra = [], $httpCode = null) {
    $httpCode = $httpCode ?? ($status === 'success' ? 200 : 400);
    http_response_code($httpCode);
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    $response = array_merge(['status' => $status, 'message' => $message], $extra);

    // Log errors for debugging
    if ($status === 'error') {
        error_log("API Error: $message | Extra: " . json_encode($extra));
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Enhanced input validation and sanitization function
function validateInput($data, $field, $minLength = 1, $maxLength = 255, $pattern = null) {
    // Sanitize input
    $data = trim($data ?? '');

    // Remove potential XSS vectors
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    $data = strip_tags($data);

    // Check for required field
    if (empty($data) && $minLength > 0) {
        return "Field '$field' is required and cannot be empty";
    }

    // Check minimum length
    if (strlen($data) < $minLength) {
        return "Field '$field' must be at least $minLength characters long";
    }

    // Check maximum length
    if (strlen($data) > $maxLength) {
        return "Field '$field' must not exceed $maxLength characters";
    }

    // Check pattern if provided
    if ($pattern && !preg_match($pattern, $data)) {
        return "Field '$field' contains invalid characters. Only letters, numbers, spaces, hyphens, dots, and parentheses are allowed";
    }

    return null;
}

// Additional validation functions
function validateId($id, $field = 'ID') {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        return "Invalid $field provided";
    }
    return null;
}

function validateStatus($status, $allowedStatuses = ['active', 'inactive']) {
    if (!in_array($status, $allowedStatuses, true)) {
        return "Invalid status value";
    }
    return null;
}

function sanitizeArray($array, $field) {
    if (!is_array($array)) {
        return [$field => "Invalid array format"];
    }

    $sanitized = [];
    $errors = [];

    foreach ($array as $key => $value) {
        $sanitized[$key] = trim(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'));
        if (empty($sanitized[$key])) {
            $errors[] = "Item " . ($key + 1) . " in $field cannot be empty";
        }
    }

    return $errors ? $errors : $sanitized;
}

// Additional validation functions
function validateDepartmentUniqueness($name, $excludeId = null) {
    global $pdo;

    $query = "SELECT COUNT(*) FROM departments WHERE LOWER(TRIM(name)) = LOWER(TRIM(?))";
    $params = [$name];

    if ($excludeId) {
        $query .= " AND id != ?";
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchColumn() == 0;
}

function validateProgramUniqueness($name, $departmentId, $excludeId = null) {
    global $pdo;

    $query = "SELECT COUNT(*) FROM options WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND department_id = ?";
    $params = [$name, $departmentId];

    if ($excludeId) {
        $query .= " AND id != ?";
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    return $stmt->fetchColumn() == 0;
}

// Enhanced AJAX Request Handler with security and validation
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action'])) {
    // Sanitize action parameter
    $action = trim(htmlspecialchars($_GET['action'], ENT_QUOTES, 'UTF-8'));

    // Validate action against whitelist
    $allowedActions = [
        'list_departments', 'add_department', 'edit_department', 'add_program',
        'delete_department', 'delete_program', 'get_statistics', 'export_csv',
        'export_pdf', 'bulk_delete', 'bulk_assign_hod', 'get_department_hierarchy',
        'list_options', 'edit_program', 'update_program_status', 'bulk_update_program_status',
        'bulk_delete_programs'
    ];

    if (!in_array($action, $allowedActions, true)) {
        error_log("Invalid action attempted: $action from IP: " . $_SERVER['REMOTE_ADDR']);
        jsonResponse('error', 'Invalid action requested', [], 400);
    }

    // Basic rate limiting (simplified)
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitKey = 'api_requests_' . md5($clientIP . date('Y-m-d-H-i'));

    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = 0;
    }

    if ($_SESSION[$rateLimitKey] > 50) { // 50 requests per minute
        jsonResponse('error', 'Rate limit exceeded. Please try again later.', [], 429);
    }

    $_SESSION[$rateLimitKey]++;

    try {
        // Log API access for auditing
        error_log("API Access: Action=$action, User=" . ($_SESSION['username'] ?? 'unknown') . ", IP=$clientIP");

        switch ($action) {
            case 'list_departments':
                handleListDepartments();
                break;

            case 'add_department':
                handleAddDepartment();
                break;

            case 'edit_department':
                handleEditDepartment();
                break;

            case 'add_program':
                handleAddProgram();
                break;

            case 'delete_department':
                handleDeleteDepartment();
                break;

            case 'delete_program':
                handleDeleteProgram();
                break;

            case 'get_statistics':
                handleGetStatistics();
                break;

            case 'export_csv':
                handleExportCSV();
                break;

            case 'export_pdf':
                handleExportPDF();
                break;

            case 'bulk_delete':
                handleBulkDelete();
                break;

            case 'bulk_assign_hod':
                handleBulkAssignHod();
                break;

            case 'get_department_hierarchy':
                handleGetDepartmentHierarchy();
                break;

            case 'list_options':
                handleListOptions();
                break;

            case 'edit_program':
                handleEditProgram();
                break;

            case 'update_program_status':
                handleUpdateProgramStatus();
                break;

            case 'bulk_update_program_status':
                handleBulkUpdateProgramStatus();
                break;

            case 'bulk_delete_programs':
                handleBulkDeletePrograms();
                break;

            default:
                jsonResponse('error', 'Action not implemented', [], 501);
        }
    } catch (PDOException $e) {
        error_log("Database error in action '$action': " . $e->getMessage());
        jsonResponse('error', 'Database error occurred', [], 500);
    } catch (Exception $e) {
        error_log("Unexpected error in action '$action': " . $e->getMessage());
        jsonResponse('error', 'An unexpected error occurred', [], 500);
    }
}

function handleListOptions() {
    global $pdo;

    try {
        // Try to select with status and created_at columns
        $stmt = $pdo->query("
            SELECT o.id, o.name, o.department_id, o.status, o.created_at,
                   d.name as department_name
            FROM options o
            LEFT JOIN departments d ON o.department_id = d.id
            ORDER BY o.name
        ");
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add default values if columns are null
        foreach ($options as &$option) {
            $option['status'] = $option['status'] ?? 'active';
            $option['created_at'] = $option['created_at'] ?? date('Y-m-d H:i:s');
        }
    } catch (Exception $e) {
        // Fallback query without status and created_at columns
        try {
            $stmt = $pdo->query("
                SELECT o.id, o.name, o.department_id,
                       d.name as department_name
                FROM options o
                LEFT JOIN departments d ON o.department_id = d.id
                ORDER BY o.name
            ");
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add default status and created_at
            foreach ($options as &$option) {
                $option['status'] = 'active';
                $option['created_at'] = date('Y-m-d H:i:s');
            }
        } catch (Exception $e2) {
            // If both queries fail, return empty array
            $options = [];
        }
    }

    jsonResponse('success', 'Programs loaded successfully', ['programs' => $options]);
}

function handleEditProgram() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $progId = (int)($_POST['program_id'] ?? 0);
    $name = trim($_POST['program_name'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($progId <= 0) {
        jsonResponse('error', 'Invalid program ID');
    }

    if ($error = validateInput($name, 'Program name', 2, 100)) {
        jsonResponse('error', $error);
    }

    if (!in_array($status, ['active', 'inactive'])) {
        jsonResponse('error', 'Invalid status value');
    }

    try {
        // Check if program exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE id = ?");
        $stmt->execute([$progId]);
        if ($stmt->fetchColumn() === 0) {
            jsonResponse('error', 'Program not found');
        }

        // Check for duplicate name (excluding current program)
        // First get the department_id of the current program
        $stmt = $pdo->prepare("SELECT department_id FROM options WHERE id = ?");
        $stmt->execute([$progId]);
        $currentDept = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentDept) {
            jsonResponse('error', 'Program not found');
        }

        if (!validateProgramUniqueness($name, $currentDept['department_id'], $progId)) {
            jsonResponse('error', 'A program with this name already exists in this department. Please choose a different name.');
        }

        $stmt = $pdo->prepare("UPDATE options SET name = ?, status = ? WHERE id = ?");
        $result = $stmt->execute([$name, $status, $progId]);

        if ($result && $stmt->rowCount() > 0) {
            jsonResponse('success', 'Program updated successfully');
        } else {
            jsonResponse('error', 'Failed to update program');
        }
    } catch (Exception $e) {
        error_log("Error updating program $progId: " . $e->getMessage());
        jsonResponse('error', 'An error occurred while updating the program');
    }
}

function handleUpdateProgramStatus() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $progId = (int)($_POST['program_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($progId <= 0) {
        jsonResponse('error', 'Invalid program ID');
    }

    if (!in_array($status, ['active', 'inactive'])) {
        jsonResponse('error', 'Invalid status value');
    }

    try {
        $stmt = $pdo->prepare("UPDATE options SET status = ? WHERE id = ?");
        $result = $stmt->execute([$status, $progId]);

        if ($result && $stmt->rowCount() > 0) {
            jsonResponse('success', 'Program status updated successfully');
        } else {
            jsonResponse('error', 'Program not found or status unchanged');
        }
    } catch (Exception $e) {
        error_log("Error updating program status $progId: " . $e->getMessage());
        jsonResponse('error', 'An error occurred while updating program status');
    }
}

function handleListDepartments() {
    global $pdo;

    try {
        // Optimized query with JOIN for HOD information
        $stmt = $pdo->query("
            SELECT
                d.id AS dept_id,
                d.name AS dept_name,
                d.hod_id,
                COALESCE(u.username, 'Not Assigned') AS hod_name
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
            ORDER BY d.name ASC
        ");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get programs for each department
        foreach ($departments as &$dept) {
            $dept['programs'] = getDepartmentPrograms($dept['dept_id']);
        }

        // Add metadata
        $response = [
            'departments' => $departments,
            'total_count' => count($departments),
            'timestamp' => date('c')
        ];

        jsonResponse('success', 'Departments retrieved successfully', $response);

    } catch (PDOException $e) {
        error_log("Database error in handleListDepartments: " . $e->getMessage());
        jsonResponse('error', 'Failed to retrieve departments: ' . $e->getMessage(), [], 500);
    } catch (Exception $e) {
        error_log("Unexpected error in handleListDepartments: " . $e->getMessage());
        jsonResponse('error', 'An unexpected error occurred: ' . $e->getMessage(), [], 500);
    }
}

function getDepartmentPrograms($deptId) {
    global $pdo;

    // Validate department ID
    if (!is_numeric($deptId) || $deptId <= 0) {
        error_log("Invalid department ID provided to getDepartmentPrograms: $deptId");
        return [];
    }

    try {
        // Single optimized query with proper defaults
        $stmt = $pdo->prepare("
            SELECT
                id,
                name,
                COALESCE(status, 'active') AS status,
                COALESCE(created_at, NOW()) AS created_at
            FROM options
            WHERE department_id = ?
            ORDER BY name ASC
        ");

        $stmt->execute([$deptId]);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add display indicators
        foreach ($programs as &$program) {
            $program['status_label'] = $program['status'] === 'active' ? 'Active' : 'Inactive';
            $program['status_badge'] = $program['status'] === 'active' ? 'success' : 'warning';
            $program['created_at_formatted'] = date('M j, Y', strtotime($program['created_at']));
        }

        return $programs;

    } catch (PDOException $e) {
        error_log("Database error in getDepartmentPrograms for dept $deptId: " . $e->getMessage());
        return [];
    } catch (Exception $e) {
        error_log("Unexpected error in getDepartmentPrograms for dept $deptId: " . $e->getMessage());
        return [];
    }
}

function handleAddDepartment() {
    global $pdo;

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method', [], 405);
    }

    // Sanitize and validate inputs
    $name = $_POST['department_name'] ?? '';
    $hodId = $_POST['hod_id'] ?? null;
    $programs = $_POST['programs'] ?? [];

    // Validate department name
    if ($error = validateInput($name, 'Department name', 2, 100, '/^[a-zA-Z0-9\s\-\.\(\)]+$/')) {
        jsonResponse('error', $error);
    }

    // Validate HoD ID if provided
    if ($hodId && ($error = validateId($hodId, 'Head of Department ID'))) {
        jsonResponse('error', $error);
    }

    // Validate programs array
    if (!empty($programs)) {
        $validatedPrograms = sanitizeArray($programs, 'Programs');
        if (is_array($validatedPrograms) && isset($validatedPrograms['Programs'])) {
            jsonResponse('error', implode(', ', $validatedPrograms));
        }
        $programs = $validatedPrograms;
    }

    try {
        // Check for duplicate department name
        if (!validateDepartmentUniqueness($name)) {
            jsonResponse('error', 'A department with this name already exists. Please choose a different name.');
        }

        // Validate HoD exists and has correct role
        if ($hodId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'hod' AND status = 'active'");
            $stmt->execute([$hodId]);
            if ($stmt->fetchColumn() === 0) {
                jsonResponse('error', 'Invalid or inactive Head of Department selected');
            }
        }

        $pdo->beginTransaction();

        try {
            // Insert department
            $stmt = $pdo->prepare("INSERT INTO departments (name, hod_id, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $hodId]);
            $deptId = $pdo->lastInsertId();

            // Add programs if provided
            if (!empty($programs)) {
                addProgramsToDepartment($deptId, $programs);
            }

            $pdo->commit();

            // Log successful operation
            error_log("Department created: ID=$deptId, Name=$name, HOD=$hodId, Programs=" . count($programs));

            $message = 'Department added successfully';
            if ($hodId) {
                $message .= '. Head of Department assigned.';
            }
            jsonResponse('success', $message, [
                'dept_id' => $deptId,
                'dept_name' => $name,
                'hod_id' => $hodId,
                'programs_count' => count($programs)
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to create department '$name': " . $e->getMessage());
            throw $e;
        }

    } catch (PDOException $e) {
        error_log("Database error in handleAddDepartment: " . $e->getMessage());
        jsonResponse('error', 'A database error occurred while creating the department. Please try again or contact support if the problem persists.', [], 500);
    }
}

function addProgramsToDepartment($deptId, $programs) {
    global $pdo;

    if (!is_array($programs) || empty($programs)) {
        return;
    }

    $stmt = $pdo->prepare("INSERT INTO options (name, department_id, status, created_at) VALUES (?, ?, 'active', NOW())");
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE LOWER(name) = LOWER(?) AND department_id = ?");

    $addedPrograms = [];
    $skippedPrograms = [];

    foreach ($programs as $program) {
        $program = trim($program);

        // Skip empty programs
        if (empty($program)) {
            continue;
        }

        // Validate program name
        if (strlen($program) < 2 || strlen($program) > 100) {
            $skippedPrograms[] = $program . ' (invalid length)';
            continue;
        }

        // Check for duplicates (case-insensitive)
        $checkStmt->execute([$program, $deptId]);
        if ($checkStmt->fetchColumn() > 0) {
            $skippedPrograms[] = $program . ' (already exists)';
            continue;
        }

        try {
            $stmt->execute([$program, $deptId]);
            $addedPrograms[] = $program;
        } catch (PDOException $e) {
            error_log("Failed to add program '$program' to department $deptId: " . $e->getMessage());
            $skippedPrograms[] = $program . ' (database error)';
        }
    }

    // Log results
    if (!empty($addedPrograms)) {
        error_log("Added programs to department $deptId: " . implode(', ', $addedPrograms));
    }
    if (!empty($skippedPrograms)) {
        error_log("Skipped programs for department $deptId: " . implode(', ', $skippedPrograms));
    }
}

function handleEditDepartment() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $deptId = (int)($_POST['department_id'] ?? 0);
    $name = trim($_POST['department_name'] ?? '');
    $hodId = !empty($_POST['hod_id']) ? (int)$_POST['hod_id'] : null;

    if ($deptId <= 0) {
        jsonResponse('error', 'Invalid department ID');
    }

    if ($error = validateInput($name, 'Department name', 2, 100)) {
        jsonResponse('error', $error);
    }

    // Check if department exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
    $stmt->execute([$deptId]);
    if ($stmt->fetchColumn() === 0) {
        jsonResponse('error', 'Department not found');
    }

    // Check for duplicate name (excluding current department)
    if (!validateDepartmentUniqueness($name, $deptId)) {
        jsonResponse('error', 'A department with this name already exists. Please choose a different name.');
    }

    // Validate HoD
    if ($hodId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'hod'");
        $stmt->execute([$hodId]);
        if ($stmt->fetchColumn() === 0) {
            jsonResponse('error', 'Invalid Head of Department selected');
        }
    }

    $stmt = $pdo->prepare("UPDATE departments SET name = ?, hod_id = ? WHERE id = ?");
    $stmt->execute([$name, $hodId, $deptId]);

    $message = 'Department updated successfully';
    if ($hodId) {
        $message .= '. Head of Department assigned.';
    }
    jsonResponse('success', $message);
}

function handleAddProgram() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $deptId = (int)($_POST['department_id'] ?? 0);
    $programName = trim($_POST['program_name'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($deptId <= 0) {
        jsonResponse('error', 'Invalid department ID');
    }

    if ($error = validateInput($programName, 'Program name', 2, 100)) {
        jsonResponse('error', $error);
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
    }

    // Check if department exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
    $stmt->execute([$deptId]);
    if ($stmt->fetchColumn() === 0) {
        jsonResponse('error', 'Department not found');
    }

    // Check for duplicate program name in department
    if (!validateProgramUniqueness($programName, $deptId)) {
        jsonResponse('error', 'A program with this name already exists in the selected department. Please choose a different name.');
    }

    $stmt = $pdo->prepare("INSERT INTO options (name, department_id, status) VALUES (?, ?, ?)");
    $stmt->execute([$programName, $deptId, $status]);

    $newProgramId = $pdo->lastInsertId();

    jsonResponse('success', 'Program added successfully', [
        'program_id' => $newProgramId,
        'program_name' => $programName,
        'status' => $status
    ]);
}

function handleDeleteDepartment() {
    global $pdo;

    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method', [], 405);
    }

    // Validate and sanitize department ID
    $deptId = $_POST['department_id'] ?? '';
    if ($error = validateId($deptId, 'Department ID')) {
        jsonResponse('error', $error);
    }

    try {
        // Check if department exists and get details
        $stmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
        $stmt->execute([$deptId]);
        $deptName = $stmt->fetchColumn();

        if (!$deptName) {
            jsonResponse('error', 'Department not found');
        }

        // Check for dependencies (students, etc.)
        $dependencyChecks = [
            'students' => "SELECT COUNT(*) FROM students WHERE department_id = ?",
            'courses' => "SELECT COUNT(*) FROM courses WHERE department_id = ?",
            'lecturers' => "SELECT COUNT(*) FROM lecturers WHERE department_id = ?"
        ];

        $dependencies = [];
        foreach ($dependencyChecks as $type => $query) {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$deptId]);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $dependencies[] = "$count $type";
            }
        }

        if (!empty($dependencies)) {
            jsonResponse('error', 'Cannot delete department. It has dependencies: ' . implode(', ', $dependencies));
        }

        $pdo->beginTransaction();

        try {
            // Get program count before deletion
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE department_id = ?");
            $stmt->execute([$deptId]);
            $programCount = $stmt->fetchColumn();

            // Delete programs first (cascade delete)
            $pdo->prepare("DELETE FROM options WHERE department_id = ?")->execute([$deptId]);

            // Delete department
            $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
            $stmt->execute([$deptId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception("Department not found or already deleted");
            }

            $pdo->commit();

            // Log successful deletion
            error_log("Department deleted: ID=$deptId, Name=$deptName, Programs removed=$programCount");

            jsonResponse('success', "Department '$deptName' deleted successfully", [
                'dept_id' => $deptId,
                'dept_name' => $deptName,
                'programs_removed' => $programCount
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Failed to delete department $deptId: " . $e->getMessage());
            throw $e;
        }

    } catch (PDOException $e) {
        error_log("Database error in handleDeleteDepartment: " . $e->getMessage());
        jsonResponse('error', 'Database error occurred while deleting department', [], 500);
    }
}

function handleDeleteProgram() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $progId = (int)($_POST['program_id'] ?? 0);

    if ($progId <= 0) {
        jsonResponse('error', 'Invalid program ID');
    }

    $stmt = $pdo->prepare("DELETE FROM options WHERE id = ?");
    $stmt->execute([$progId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse('error', 'Program not found or already deleted');
    }

    jsonResponse('success', 'Program deleted successfully');
}

function handleBulkUpdateProgramStatus() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method', [], 405);
    }

    $programIds = $_POST['program_ids'] ?? [];
    $status = $_POST['status'] ?? '';

    if (empty($programIds)) {
        jsonResponse('error', 'No programs selected');
    }

    if (!in_array($status, ['active', 'inactive'])) {
        jsonResponse('error', 'Invalid status value');
    }

    // Ensure program_ids is an array
    if (!is_array($programIds)) {
        $programIds = [$programIds];
    }

    $programIds = array_map('intval', array_filter($programIds, function($id) {
        return is_numeric($id) && $id > 0;
    }));

    if (empty($programIds)) {
        jsonResponse('error', 'No valid program IDs provided');
    }

    try {
        $placeholders = str_repeat('?,', count($programIds) - 1) . '?';
        $stmt = $pdo->prepare("UPDATE options SET status = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$status], $programIds));

        $updatedCount = $stmt->rowCount();

        // Log successful operation
        error_log("Bulk program status update: $updatedCount programs set to $status");

        jsonResponse('success', "Successfully updated $updatedCount programs to $status status", [
            'updated_count' => $updatedCount,
            'new_status' => $status
        ]);

    } catch (PDOException $e) {
        error_log("Database error in handleBulkUpdateProgramStatus: " . $e->getMessage());
        jsonResponse('error', 'Database error occurred while updating programs', [], 500);
    }
}

function handleBulkDeletePrograms() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method', [], 405);
    }

    $programIds = $_POST['program_ids'] ?? [];

    if (empty($programIds)) {
        jsonResponse('error', 'No programs selected');
    }

    // Ensure program_ids is an array
    if (!is_array($programIds)) {
        $programIds = [$programIds];
    }

    $programIds = array_map('intval', array_filter($programIds, function($id) {
        return is_numeric($id) && $id > 0;
    }));

    if (empty($programIds)) {
        jsonResponse('error', 'No valid program IDs provided');
    }

    try {
        $pdo->beginTransaction();

        // Get program names for logging
        $placeholders = str_repeat('?,', count($programIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT name FROM options WHERE id IN ($placeholders)");
        $stmt->execute($programIds);
        $programNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Delete programs
        $stmt = $pdo->prepare("DELETE FROM options WHERE id IN ($placeholders)");
        $stmt->execute($programIds);

        $deletedCount = $stmt->rowCount();

        $pdo->commit();

        // Log successful operation
        error_log("Bulk program deletion: $deletedCount programs deleted: " . implode(', ', $programNames));

        jsonResponse('success', "Successfully deleted $deletedCount programs", [
            'deleted_count' => $deletedCount,
            'deleted_programs' => $programNames
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error in handleBulkDeletePrograms: " . $e->getMessage());
        jsonResponse('error', 'Database error occurred while deleting programs', [], 500);
    }
}

function handleGetStatistics() {
    global $pdo;

    try {
        $stats = [];

        // Simple queries to get basic statistics
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
        $stats['total_departments'] = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL");
        $stats['assigned_hods'] = $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM options");
        $stats['total_programs'] = $stmt->fetchColumn();

        // Calculate average programs per department
        if ($stats['total_departments'] > 0) {
            $stats['avg_programs_per_dept'] = round($stats['total_programs'] / $stats['total_departments'], 1);
        } else {
            $stats['avg_programs_per_dept'] = 0;
        }

        // Department with most programs
        $stmt = $pdo->query("
            SELECT d.name, COUNT(o.id) as program_count
            FROM departments d
            LEFT JOIN options o ON d.id = o.department_id
            GROUP BY d.id, d.name
            ORDER BY program_count DESC
            LIMIT 1
        ");
        $topDept = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['largest_department'] = $topDept ?: ['name' => 'None', 'program_count' => 0];

        // Add metadata
        $stats['generated_at'] = date('c');

        jsonResponse('success', 'Statistics retrieved successfully', $stats);

    } catch (PDOException $e) {
        error_log("Database error in handleGetStatistics: " . $e->getMessage());
        jsonResponse('error', 'Failed to retrieve statistics: ' . $e->getMessage(), [], 500);
    } catch (Exception $e) {
        error_log("Unexpected error in handleGetStatistics: " . $e->getMessage());
        jsonResponse('error', 'An unexpected error occurred: ' . $e->getMessage(), [], 500);
    }
}

function handleExportCSV() {
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT
                d.id,
                d.name as department_name,
                u.username as hod_name,
                COUNT(o.id) as program_count,
                GROUP_CONCAT(o.name ORDER BY o.name SEPARATOR '; ') as programs
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
            LEFT JOIN options o ON d.id = o.department_id
            GROUP BY d.id, d.name, u.username
            ORDER BY d.name
        ");

        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="departments_directory_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, ['ID', 'Department Name', 'Head of Department', 'Program Count', 'Programs']);

        // CSV data
        foreach ($departments as $dept) {
            fputcsv($output, [
                $dept['id'],
                $dept['department_name'],
                $dept['hod_name'] ?: 'Not Assigned',
                $dept['program_count'],
                $dept['programs'] ?: 'No programs'
            ]);
        }

        fclose($output);
        exit;
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to export CSV: ' . $e->getMessage());
    }
}

function handleExportPDF() {
    // For PDF export, we'll create a simple HTML that can be printed/saved as PDF
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT
                d.id,
                d.name as department_name,
                u.username as hod_name,
                COUNT(o.id) as program_count,
                GROUP_CONCAT(o.name ORDER BY o.name SEPARATOR ', ') as programs
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
            LEFT JOIN options o ON d.id = o.department_id
            GROUP BY d.id, d.name, u.username
            ORDER BY d.name
        ");

        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate HTML for PDF
        $html = generatePDFHTML($departments);

        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="departments_directory_' . date('Y-m-d') . '.html"');
        echo $html;
        exit;
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to export PDF: ' . $e->getMessage());
    }
}

function generatePDFHTML($departments) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Departments Directory</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .department { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
            .dept-name { font-size: 18px; font-weight: bold; color: #0066cc; }
            .hod-name { font-style: italic; color: #666; }
            .programs { margin-top: 10px; }
            .program { display: inline-block; background: #f0f0f0; padding: 3px 8px; margin: 2px; border-radius: 3px; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Rwanda Polytechnic - Departments Directory</h1>
            <p>Generated on: ' . date('F j, Y, g:i a') . '</p>
        </div>';

    foreach ($departments as $dept) {
        $html .= '
        <div class="department">
            <div class="dept-name">' . htmlspecialchars($dept['department_name']) . '</div>
            <div class="hod-name">Head of Department: ' . htmlspecialchars($dept['hod_name'] ?: 'Not Assigned') . '</div>
            <div class="programs">
                <strong>Programs (' . $dept['program_count'] . '):</strong><br>';

        if ($dept['programs']) {
            $programs = explode(', ', $dept['programs']);
            foreach ($programs as $program) {
                $html .= '<span class="program">' . htmlspecialchars($program) . '</span>';
            }
        } else {
            $html .= '<em>No programs assigned</em>';
        }

        $html .= '
            </div>
        </div>';
    }

    $html .= '
    </body>
    </html>';

    return $html;
}

function handleBulkDelete() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method', [], 405);
    }

    $departmentIds = $_POST['department_ids'] ?? [];

    if (empty($departmentIds)) {
        jsonResponse('error', 'No departments selected for deletion');
    }

    // Ensure department_ids is an array and validate
    if (!is_array($departmentIds)) {
        $departmentIds = [$departmentIds];
    }

    $departmentIds = array_map('intval', array_filter($departmentIds, function($id) {
        return is_numeric($id) && $id > 0;
    }));

    if (empty($departmentIds)) {
        jsonResponse('error', 'No valid department IDs provided');
    }

    // Validate that all departments exist
    $placeholders = str_repeat('?,', count($departmentIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id IN ($placeholders)");
    $stmt->execute($departmentIds);
    $existingDepts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($existingDepts) !== count($departmentIds)) {
        $foundIds = array_column($existingDepts, 'id');
        $missingIds = array_diff($departmentIds, $foundIds);
        jsonResponse('error', 'Some departments not found: ' . implode(', ', $missingIds));
    }

    // Check for dependencies
    $dependencyErrors = [];
    foreach ($existingDepts as $dept) {
        $dependencyChecks = [
            'students' => "SELECT COUNT(*) FROM students WHERE department_id = ?",
            'courses' => "SELECT COUNT(*) FROM courses WHERE department_id = ?",
            'lecturers' => "SELECT COUNT(*) FROM lecturers WHERE department_id = ?"
        ];

        $deptDependencies = [];
        foreach ($dependencyChecks as $type => $query) {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$dept['id']]);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $deptDependencies[] = "$count $type";
            }
        }

        if (!empty($deptDependencies)) {
            $dependencyErrors[] = "{$dept['name']}: " . implode(', ', $deptDependencies);
        }
    }

    if (!empty($dependencyErrors)) {
        jsonResponse('error', 'Cannot delete departments with dependencies: ' . implode('; ', $dependencyErrors));
    }

    $pdo->beginTransaction();
    try {
        // Delete programs first
        $stmt = $pdo->prepare("DELETE FROM options WHERE department_id IN ($placeholders)");
        $stmt->execute($departmentIds);

        // Delete departments
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id IN ($placeholders)");
        $stmt->execute($departmentIds);

        $deletedCount = $stmt->rowCount();

        $pdo->commit();
        jsonResponse('success', "Successfully deleted $deletedCount department(s) and their associated programs", [
            'deleted_count' => $deletedCount,
            'deleted_departments' => array_column($existingDepts, 'name')
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Bulk delete transaction failed: " . $e->getMessage());
        jsonResponse('error', 'Failed to delete departments due to a database error. Please try again.', [], 500);
    }
}

function handleBulkAssignHod() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $departmentIds = $_POST['department_ids'] ?? [];
    $hodId = (int)($_POST['hod_id'] ?? 0);

    if (empty($departmentIds)) {
        jsonResponse('error', 'No departments selected');
    }

    if ($hodId <= 0) {
        jsonResponse('error', 'Invalid Head of Department selected');
    }

    // Validate HoD exists and has correct role
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'hod'");
    $stmt->execute([$hodId]);
    if ($stmt->fetchColumn() === 0) {
        jsonResponse('error', 'Invalid Head of Department selected');
    }

    // Ensure department_ids is an array
    if (!is_array($departmentIds)) {
        $departmentIds = [$departmentIds];
    }

    $departmentIds = array_map('intval', $departmentIds);
    $placeholders = str_repeat('?,', count($departmentIds) - 1) . '?';

    try {
        $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$hodId], $departmentIds));

        $updatedCount = $stmt->rowCount();
        jsonResponse('success', "Successfully assigned HoD to $updatedCount departments");
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to assign HoD: ' . $e->getMessage());
    }
}

function handleGetDepartmentHierarchy() {
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT
                d.id,
                d.name as department_name,
                d.hod_id,
                u.username as hod_name,
                COUNT(o.id) as program_count,
                COUNT(DISTINCT s.id) as student_count
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
            LEFT JOIN options o ON d.id = o.department_id
            LEFT JOIN students s ON s.department_id = d.id
            GROUP BY d.id, d.name, d.hod_id, u.username
            ORDER BY d.name
        ");

        $hierarchy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse('success', 'Department hierarchy retrieved successfully', $hierarchy);
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to retrieve hierarchy: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments Management | RP Attendance System</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            /* Primary Brand Colors - Refined for Skyblue Background */
            --primary-color: #1e40af;
            --primary-dark: #1e3a8a;
            --primary-light: #dbeafe;
            --primary-gradient: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);

            /* Status Colors - Refined for Skyblue Theme */
            --success-color: #059669;
            --success-light: #ecfdf5;
            --success-dark: #065f46;
            --success-gradient: linear-gradient(135deg, #059669 0%, #065f46 100%);

            --danger-color: #dc2626;
            --danger-light: #fef2f2;
            --danger-dark: #991b1b;
            --danger-gradient: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);

            --warning-color: #d97706;
            --warning-light: #fffbeb;
            --warning-dark: #92400e;
            --warning-gradient: linear-gradient(135deg, #d97706 0%, #92400e 100%);

            --info-color: #0891b2;
            --info-light: #ecfeff;
            --info-dark: #0e7490;
            --info-gradient: linear-gradient(135deg, #0891b2 0%, #0e7490 100%);

            /* Neutral Colors - Refined Palette */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;

            /* Layout Variables */
            --sidebar-width: 280px;
            --header-height: 70px;

            /* Design Tokens */
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --border-radius-sm: 4px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --shadow-hover: 0 4px 12px rgba(0,0,0,0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;

            /* Typography */
            --font-family: 'Inter', sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 2rem;
            --font-weight-normal: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
            --font-weight-bold: 700;

            /* Spacing */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: skyblue;
            min-height: 100vh;
            overflow-x: hidden;
            font-size: var(--font-size-base);
            line-height: 1.6;
            color: var(--gray-800);
            font-weight: var(--font-weight-normal);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 25% 25%, rgba(255,255,255,0.15) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(255,255,255,0.1) 0%, transparent 50%),
                linear-gradient(135deg, rgba(135,206,235,0.9) 0%, rgba(70,130,180,0.95) 100%);
            pointer-events: none;
            z-index: -1;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="subtle-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="0.5" fill="rgba(255,255,255,0.03)"/></pattern></defs><rect width="100" height="100" fill="url(%23subtle-pattern)"/></svg>');
            pointer-events: none;
            z-index: -1;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 30px;
            min-height: calc(100vh - var(--header-height));
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(1px);
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .text-muted {
            color: var(--gray-600) !important;
        }

        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(248,249,250,0.95) 100%);
            border: none;
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .card-header h5 {
            color: var(--gray-900);
            font-weight: 600;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            text-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .card-header h5 i {
            margin-right: 10px;
            color: #0066cc;
        }

        .card-header.bg-success {
            background: var(--success-gradient) !important;
        }

        .card-header.bg-info {
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%) !important;
            color: white !important;
            border: none;
        }

        .card-header.bg-info h5 {
            color: white !important;
        }

        .card-header.bg-info .form-check-label {
            color: white !important;
            font-weight: 500;
        }

        .card-header.bg-warning {
            background: var(--warning-gradient) !important;
        }

        .card-header.bg-danger {
            background: var(--danger-gradient) !important;
        }

        .card-body {
            padding: 0;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 10px;
            color: #0066cc;
        }

        .card-text {
            color: var(--gray-700);
            line-height: 1.6;
        }

        /* Buttons */
        .btn {
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: var(--font-weight-medium);
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            position: relative;
            overflow: hidden;
            font-size: var(--font-size-sm);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: var(--transition-fast);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
            color: white;
            border: none;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
            color: white;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
            border-color: var(--primary-color);
        }

        .btn-outline-secondary {
            border: 2px solid var(--gray-300);
            color: var(--gray-600);
            background: transparent;
            font-weight: 500;
        }

        .btn-outline-secondary:hover {
            background: var(--gray-600);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-success {
            background: var(--success-gradient);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            font-weight: 500;
        }

        .btn-success:hover {
            background: var(--success-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-danger {
            background: var(--danger-gradient);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            font-weight: 500;
        }

        .btn-danger:hover {
            background: var(--danger-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .btn-warning {
            background: var(--warning-gradient);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
            font-weight: 500;
        }

        .btn-warning:hover {
            background: var(--warning-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
        }

        .btn-info {
            background: var(--info-gradient);
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
            font-weight: 500;
        }

        .btn-info:hover {
            background: var(--info-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
        }

        .btn-sm {
            padding: var(--spacing-xs) calc(var(--spacing-sm) + 0.25rem);
            font-size: var(--font-size-xs);
        }

        .btn-lg {
            padding: calc(var(--spacing-sm) + 0.25rem) var(--spacing-lg);
            font-size: var(--font-size-base);
        }

        /* Forms */
        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 2px solid var(--gray-200);
            padding: calc(var(--spacing-sm) + 0.25rem);
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            font-size: var(--font-size-sm);
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--gray-300);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }

        .form-control::placeholder {
            color: var(--gray-400);
            font-weight: var(--font-weight-normal);
        }

        .form-label {
            font-weight: var(--font-weight-semibold);
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
            font-size: var(--font-size-sm);
        }

        .form-text {
            color: var(--gray-500);
            font-size: var(--font-size-xs);
            margin-top: var(--spacing-xs);
        }

        .input-group .form-control:focus + .btn {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .input-group-text {
            background: var(--gray-50);
            border-color: var(--gray-200);
            color: var(--gray-600);
        }

        /* Form Validation */
        .is-invalid {
            border-color: var(--danger-color) !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
        }

        .is-valid {
            border-color: var(--success-color) !important;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1) !important;
        }

        .invalid-feedback {
            color: var(--danger-color);
            font-size: var(--font-size-xs);
            margin-top: var(--spacing-xs);
        }

        .valid-feedback {
            color: var(--success-color);
            font-size: var(--font-size-xs);
            margin-top: var(--spacing-xs);
        }

        /* Department Directory Items */
        .department-item {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.12);
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-xl);
        }

        .department-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .department-item:hover::before {
            transform: scaleX(1);
        }

        .department-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 25px rgba(0, 102, 204, 0.15);
            border-left-color: var(--primary-dark);
        }

        .department-item .department-header {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }

        .department-item .department-name {
            font-size: var(--font-size-2xl);
            font-weight: var(--font-weight-bold);
            color: var(--gray-900);
            margin: 0;
            flex-grow: 1;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .department-item .department-checkbox {
            margin-right: var(--spacing-md);
        }

        .department-item .department-info {
            margin-bottom: var(--spacing-sm);
            color: var(--gray-900);
        }

        .department-item .department-info strong {
            color: var(--gray-700);
            font-weight: var(--font-weight-bold);
            text-shadow: 0 1px 1px rgba(0, 0, 0, 0.05);
        }

        .department-item .programs-list {
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-md) var(--spacing-lg);
            background: rgba(248, 249, 250, 0.9);
            border-radius: var(--border-radius);
            border: 1px solid rgba(0, 0, 0, 0.1);
            color: var(--gray-900);
        }

        .department-item .programs-list em {
            color: var(--gray-600);
            font-style: italic;
            text-shadow: 0 1px 1px rgba(255, 255, 255, 0.5);
        }

        .department-item .program-item {
            padding: 3px 0;
            color: var(--gray-900);
            font-weight: var(--font-weight-medium);
            line-height: 1.4;
        }

        .department-item .add-program-section {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
            padding: var(--spacing-sm);
            background: rgba(240, 248, 255, 0.8);
            border-radius: var(--border-radius);
            border: 1px solid rgba(0, 102, 204, 0.2);
        }

        .department-item .add-program-input {
            flex: 1;
            max-width: 300px;
            border: 2px solid rgba(0, 102, 204, 0.2);
        }

        .department-item .add-program-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .department-item .action-buttons {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-sm);
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        /* Program Badges */
        .program-badge {
            border-radius: 20px;
            padding: var(--spacing-xs) var(--spacing-md);
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-semibold);
            margin: var(--spacing-xs) calc(var(--spacing-xs) + 0.25rem) var(--spacing-xs) 0;
            display: inline-block;
            transition: var(--transition);
            color: var(--gray-800);
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid var(--primary-light);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .program-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: var(--transition);
        }

        .program-badge:hover::before {
            left: 100%;
        }

        .program-badge:hover {
            transform: scale(1.08) translateY(-1px);
            box-shadow: 0 4px 12px rgba(44,62,80,0.13);
            background: #e3f0ff;
            color: var(--primary-dark);
        }

        .program-badge.success {
            color: var(--success-dark);
            background: var(--success-light);
            border-color: rgba(40, 167, 69, 0.2);
        }

        .program-badge.success:hover {
            background: var(--success-color);
            color: white;
        }

        .program-badge.warning {
            color: var(--warning-dark);
            background: var(--warning-light);
            border-color: rgba(255, 193, 7, 0.2);
        }

        .program-badge.warning:hover {
            background: var(--warning-color);
            color: var(--gray-800);
        }

        /* Status Colors */
        .bg-success { background: var(--success-color) !important; }
        .bg-warning { background: var(--warning-color) !important; }
        .bg-info { background: var(--info-color) !important; }
        .bg-danger { background: var(--danger-color) !important; }
        .bg-light { background: var(--gray-50) !important; }
        .bg-dark { background: var(--gray-800) !important; }

        .text-success { color: var(--success-color) !important; }
        .text-warning { color: var(--warning-color) !important; }
        .text-info { color: var(--info-color) !important; }
        .text-danger { color: var(--danger-color) !important; }
        .text-muted { color: var(--gray-500) !important; }

        /* Statistics Cards */
        .statistics-container .card {
            text-align: center;
            border: none;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .statistics-container .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .statistics-container .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .statistics-container .card-body {
            padding: 0;
            position: relative;
            z-index: 1;
        }

        .statistics-container i {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
        }

        .statistics-container h4 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 5px;
        }

        .statistics-container .card-title {
            color: var(--gray-700);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0;
        }

        /* Individual Statistics Card Colors - Using gradients from admin/index.php */
        .statistics-container .card.border-primary i {
            background: var(--primary-gradient);
        }

        .statistics-container .card.border-success i {
            background: var(--success-gradient);
        }

        .statistics-container .card.border-info i {
            background: var(--info-gradient);
        }

        .statistics-container .card.border-warning i {
            background: var(--warning-gradient);
        }

        /* Navigation Tabs */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
            transition: var(--transition);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            background: var(--primary-light);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background: #fff;
            border-bottom: 3px solid var(--primary-color);
            font-weight: 600;
        }

        /* Tables */
        .table {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .table th {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border: none;
            padding: 15px 12px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            box-shadow: inset 0 -1px 0 rgba(255, 255, 255, 0.1);
        }

        .table th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
        }

        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: rgba(0, 0, 0, 0.08);
            font-size: 0.9rem;
            color: var(--gray-900);
            font-weight: 500;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(30, 64, 175, 0.04);
        }

        .table-striped tbody tr:nth-of-type(even) {
            background-color: rgba(255, 255, 255, 0.95);
        }

        .table-hover tbody tr {
            transition: all 0.3s ease;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(30, 64, 175, 0.08);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            border-left: 3px solid var(--primary-color);
        }

        /* Special styling for programs table */
        #programsTable {
            background: white;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
        }

        #programsTable thead th {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            font-weight: 700;
            font-size: 0.8rem;
            padding: 16px 12px;
        }

        #programsTable tbody tr:nth-of-type(odd) {
            background-color: #f8fafc;
        }

        #programsTable tbody tr:nth-of-type(even) {
            background-color: #ffffff;
        }

        #programsTable tbody tr:hover {
            background-color: #e0f2fe;
            border-left: 4px solid #1e40af;
        }

        #programsTable td {
            border-bottom: 1px solid #e2e8f0;
            color: #1e293b;
            font-weight: 500;
        }

        /* Checkbox styling in table */
        .table .form-check-input {
            border: 2px solid #cbd5e1;
            background-color: white;
        }

        .table .form-check-input:checked {
            background-color: #1e40af;
            border-color: #1e40af;
        }

        .table .form-check-input:focus {
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }

        /* Bulk actions button styling */
        #bulkProgramsActions:not(:disabled) {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            color: white;
            border-color: #1e40af;
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
        }

        #bulkProgramsActions:not(:disabled):hover {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.4);
        }

        #bulkProgramsActions:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .table-responsive {
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        /* Badges */
        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .badge.bg-success {
            background: linear-gradient(135deg, #059669 0%, #065f46 100%) !important;
            color: white !important;
            font-weight: 600;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, #d97706 0%, #92400e 100%) !important;
            color: white !important;
            font-weight: 600;
        }

        .badge.bg-info {
            background: linear-gradient(135deg, #0891b2 0%, #0e7490 100%) !important;
            color: white !important;
            font-weight: 600;
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%) !important;
            color: white !important;
            font-weight: 600;
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: var(--success-light);
            color: var(--gray-900);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: var(--danger-light);
            color: var(--gray-900);
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background: var(--warning-light);
            color: var(--gray-900);
            border-left: 4px solid var(--warning-color);
        }

        /* Modals */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .modal-body {
            padding: 25px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .btn-close {
            font-size: 1.2rem;
            opacity: 0.6;
            transition: all 0.3s ease;
        }

        .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0066cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Loading States */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--gray-300);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Pulse Animation */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Dropdowns */
        .dropdown-menu {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-hover);
            padding: 0.5rem;
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        /* Search and Filters */
        .search-container {
            position: relative;
        }

        .search-container .form-control {
            border-radius: var(--border-radius) 0 0 var(--border-radius);
        }

        .search-container .btn {
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }

        /* Action Buttons */
        .action-buttons .btn {
            margin: 0.125rem;
            transition: var(--transition);
        }

        .action-buttons .btn:hover {
            transform: translateY(-1px);
        }

        /* Status Indicators */
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-active { background: var(--success-color); }
        .status-inactive { background: var(--warning-color); }

        /* Print Styles */
        @media print {
            .sidebar,
            .btn,
            .dropdown,
            .modal,
            .loading-overlay,
            .alert,
            .nav-tabs,
            .action-buttons {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
                break-inside: avoid;
                margin-bottom: 20px;
            }

            .card-header {
                background: #f8f9fa !important;
                color: #000 !important;
                border-bottom: 1px solid #000 !important;
            }

            .text-primary {
                color: #000 !important;
            }
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }

            .action-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .card {
                padding: 20px;
                margin-bottom: 20px;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .department-card {
                margin-bottom: 20px;
            }

            .statistics-container {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .statistics-container .col-md-3 {
                margin-bottom: 0;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }

            .card {
                padding: 15px;
            }

            .statistics-container {
                grid-template-columns: 1fr;
            }

            .modal-dialog {
                margin: 10px;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            :root {
                --gray-50: #0f172a;
                --gray-100: #1e293b;
                --gray-200: #334155;
                --gray-300: #475569;
                --gray-400: #64748b;
                --gray-500: #94a3b8;
                --gray-600: #cbd5e1;
                --gray-700: #e2e8f0;
                --gray-800: #f1f5f9;
                --gray-900: #ffffff;
            }

            body {
                background: linear-gradient(135deg, #4a90e2 0%, #357abd 100%);
                color: var(--gray-700);
            }

            .card {
                background: rgba(50, 70, 100, 0.95);
                color: var(--gray-700);
            }

            .form-control {
                background: rgba(70, 90, 120, 0.9);
                border-color: var(--gray-300);
                color: var(--gray-700);
            }

            .form-control:focus {
                background: rgba(70, 90, 120, 0.95);
                color: var(--gray-700);
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Focus States & Accessibility */
        .btn:focus,
        .form-control:focus,
        .form-select:focus,
        .nav-link:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.25);
            border-color: var(--primary-color);
        }

        .btn:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Skip to main content link for screen readers */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--primary-color);
            color: white;
            padding: 8px;
            text-decoration: none;
            border-radius: var(--border-radius);
            z-index: 10000;
        }

        .skip-link:focus {
            top: 6px;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .card {
                border: 2px solid var(--gray-400);
            }

            .btn {
                border: 2px solid currentColor;
            }

            .form-control {
                border: 2px solid var(--gray-400);
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }

        /* Utility Classes */
        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .shadow-sm { box-shadow: var(--shadow-sm) !important; }
        .shadow-md { box-shadow: var(--shadow-md) !important; }
        .shadow-lg { box-shadow: var(--shadow-lg) !important; }

        .border-left-primary { border-left: 4px solid var(--primary-color) !important; }
        .border-left-success { border-left: 4px solid var(--success-color) !important; }
        .border-left-warning { border-left: 4px solid var(--warning-color) !important; }
        .border-left-info { border-left: 4px solid var(--info-color) !important; }

        .rounded-sm { border-radius: var(--border-radius-sm) !important; }
        .rounded { border-radius: var(--border-radius) !important; }
        .rounded-lg { border-radius: var(--border-radius-lg) !important; }

        .d-none { display: none !important; }
        .d-block { display: block !important; }
        .d-flex { display: flex !important; }
        .d-grid { display: grid !important; }

        /* Hover Effects */
        .hover-lift:hover {
            transform: translateY(-2px);
            transition: var(--transition);
        }

        .hover-glow:hover {
            box-shadow: 0 0 20px rgba(0, 102, 204, 0.3);
        }

        /* Text Utilities */
        .text-xs { font-size: var(--font-size-xs) !important; }
        .text-sm { font-size: var(--font-size-sm) !important; }
        .text-base { font-size: var(--font-size-base) !important; }
        .text-lg { font-size: var(--font-size-lg) !important; }

        .font-normal { font-weight: var(--font-weight-normal) !important; }
        .font-medium { font-weight: var(--font-weight-medium) !important; }
        .font-semibold { font-weight: var(--font-weight-semibold) !important; }
        .font-bold { font-weight: var(--font-weight-bold) !important; }

        /* Spacing Utilities */
        .m-0 { margin: 0 !important; }
        .m-1 { margin: var(--spacing-xs) !important; }
        .m-2 { margin: var(--spacing-sm) !important; }
        .m-3 { margin: var(--spacing-md) !important; }
        .m-4 { margin: var(--spacing-lg) !important; }
        .m-5 { margin: var(--spacing-xl) !important; }

        .p-0 { padding: 0 !important; }
        .p-1 { padding: var(--spacing-xs) !important; }
        .p-2 { padding: var(--spacing-sm) !important; }
        .p-3 { padding: var(--spacing-md) !important; }
        .p-4 { padding: var(--spacing-lg) !important; }
        .p-5 { padding: var(--spacing-xl) !important; }

    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none btn btn-primary position-fixed top-0 start-0 m-3" style="z-index: 10000;" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar position-fixed top-0 start-0 h-100 bg-primary-dark text-white" style="width: var(--sidebar-width); z-index: 1000;">
        <div class="p-4 text-center">
            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                <i class="fas fa-cog"></i>
            </div>
            <h5 class="mb-0">Admin Dashboard</h5>
        </div>
        
        <nav class="nav flex-column p-3">
            <a class="nav-link text-white mb-2" href="admin-dashboard.php">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
            <a class="nav-link text-white mb-2 active" href="manage-departments.php">
                <i class="fas fa-building me-2"></i>Departments
            </a>
            <a class="nav-link text-white mb-2" href="register-student.php">
                <i class="fas fa-user-plus me-2"></i>Register Student
            </a>
            <a class="nav-link text-white mb-2" href="admin-reports.php">
                <i class="fas fa-chart-bar me-2"></i>Reports
            </a>
            <a class="nav-link text-white mb-2" href="assign-hod.php">
                <i class="fas fa-user-tie me-2"></i>Assign HOD
            </a>
            <a class="nav-link text-white" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 text-primary">
                    <i class="fas fa-building me-2"></i>Departments Directory
                </h1>
                <p class="text-muted mb-0">Comprehensive management of departments and programs</p>
            </div>
            <div class="d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportData('csv')">
                            <i class="fas fa-file-csv me-2"></i>Export as CSV
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportData('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>Export as PDF
                        </a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs me-2"></i>Bulk Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="showBulkDeleteModal()">
                            <i class="fas fa-trash me-2"></i>Bulk Delete
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="showBulkAssignHodModal()">
                            <i class="fas fa-user-tie me-2"></i>Bulk Assign HoD
                        </a></li>
                    </ul>
                </div>
                <button class="btn btn-primary" onclick="loadDepartments()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="row g-3 mb-4 statistics-container" id="statisticsContainer">
            <!-- Statistics will be loaded here -->
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab">
                    <i class="fas fa-building me-2"></i>Departments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="programs-tab" data-bs-toggle="tab" data-bs-target="#programs" type="button" role="tab">
                    <i class="fas fa-graduation-cap me-2"></i>Programs
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="mainTabContent">
            <!-- Departments Tab -->
            <div class="tab-pane fade show active" id="departments" role="tabpanel">
                <!-- Department Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-plus-circle me-2"></i>
                            <span id="formTitle">Add New Department</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="departmentForm">
                            <input type="hidden" id="departmentId" name="department_id">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="departmentName" class="form-label">Department Name *</label>
                                    <input type="text" class="form-control" id="departmentName" name="department_name" 
                                           placeholder="Enter department name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="hodSelect" class="form-label">Head of Department</label>
                                    <select class="form-select" id="hodSelect" name="hod_id">
                                        <option value="">-- Select HoD --</option>
                                        <?php
                                        $hods = $pdo->query("SELECT id, username FROM users WHERE role = 'hod' ORDER BY username")->fetchAll();
                                        foreach ($hods as $hod) {
                                            echo "<option value='{$hod['id']}'>{$hod['username']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Programs</label>
                                <div id="programsContainer">
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control" name="programs[]" 
                                               placeholder="Enter program name">
                                        <button type="button" class="btn btn-outline-danger remove-program" disabled>
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addProgramField">
                                    <i class="fas fa-plus me-1"></i>Add Program
                                </button>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    <span id="submitText">Save Department</span>
                                </button>
                                <button type="button" class="btn btn-secondary" id="cancelEdit" style="display: none;">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Departments List -->
                <div class="card">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0 me-3">
                                <i class="fas fa-list me-2"></i>Departments Directory
                            </h5>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                                <label class="form-check-label text-white" for="selectAllCheckbox">
                                    Select All
                                </label>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="dropdown">
                                <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-sort me-1"></i>Sort
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="sortDepartments('name')">
                                        <i class="fas fa-sort-alpha-down me-2"></i>By Name
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="sortDepartments('programs')">
                                        <i class="fas fa-sort-numeric-down me-2"></i>By Programs Count
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="sortDepartments('hod')">
                                        <i class="fas fa-sort me-2"></i>By HoD Status
                                    </a></li>
                                </ul>
                            </div>
                            <div class="input-group" style="width: 300px;">
                                <input type="text" class="form-control" placeholder="Search departments..." id="searchInput">
                                <button class="btn btn-light" type="button" id="clearSearch">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="departmentsContainer">
                            <!-- Departments will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Programs Tab -->
            <div class="tab-pane fade" id="programs" role="tabpanel">
                <!-- Programs Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1 text-primary">
                            <i class="fas fa-graduation-cap me-2"></i>All Programs
                        </h1>
                        <p class="text-muted mb-0">Manage all programs across departments</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-success" onclick="showAddProgramModal()">
                            <i class="fas fa-plus me-2"></i>Add Program
                        </button>
                        <button class="btn btn-primary" onclick="loadPrograms()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <!-- Programs Table -->
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0 me-3">
                                <i class="fas fa-list me-2"></i>Programs Directory
                            </h5>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAllProgramsCheckbox" style="border: 2px solid rgba(255,255,255,0.8); background: rgba(255,255,255,0.1);">
                                <label class="form-check-label text-white fw-bold" for="selectAllProgramsCheckbox">
                                    <i class="fas fa-check-square me-1"></i>Select All
                                </label>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="dropdown">
                                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" id="bulkProgramsActions" disabled>
                                    <i class="fas fa-cogs me-1"></i>With selected:
                                </button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="#" onclick="showBulkProgramStatusModal('active')">
                                        <i class="fas fa-toggle-on me-2 text-success"></i>Activate Selected
                                    </a></li>
                                    <li><a class="dropdown-item" href="#" onclick="showBulkProgramStatusModal('inactive')">
                                        <i class="fas fa-toggle-off me-2 text-warning"></i>Deactivate Selected
                                    </a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="showBulkProgramDeleteModal()">
                                        <i class="fas fa-trash me-2"></i>Delete Selected
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="programsTable">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" class="form-check-input" id="headerProgramCheckbox" style="margin: 0;"></th>
                                        <th>Full texts id</th>
                                        <th>Full texts name</th>
                                        <th>Full texts department_id</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="programsTableBody">
                                    <!-- Programs will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Delete Modal -->
        <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="fas fa-trash me-2"></i>Bulk Delete Departments
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the selected departments? This action cannot be undone.</p>
                        <div id="selectedDepartmentsList" class="mb-3"></div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            All programs and associated data will also be deleted.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()">
                            <i class="fas fa-trash me-2"></i>Delete Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Assign HoD Modal -->
        <div class="modal fade" id="bulkAssignHodModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-primary">
                            <i class="fas fa-user-tie me-2"></i>Bulk Assign Head of Department
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="bulkHodSelect" class="form-label">Select Head of Department</label>
                            <select class="form-select" id="bulkHodSelect" required>
                                <option value="">-- Choose HoD --</option>
                                <?php
                                $hods = $pdo->query("SELECT id, username FROM users WHERE role = 'hod' ORDER BY username")->fetchAll();
                                foreach ($hods as $hod) {
                                    echo "<option value='{$hod['id']}'>{$hod['username']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div id="bulkAssignDepartmentsList" class="mb-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="confirmBulkAssignHod()">
                            <i class="fas fa-user-tie me-2"></i>Assign HoD
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Program Modal -->
        <div class="modal fade" id="addProgramModal" tabindex="-1" aria-labelledby="addProgramModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProgramModalLabel">
                            <i class="fas fa-plus me-2"></i>Add New Program
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addProgramForm">
                            <div class="mb-3">
                                <label for="addProgramName" class="form-label">Program Name *</label>
                                <input type="text" class="form-control" id="addProgramName" required maxlength="100" placeholder="Enter program name">
                                <div class="invalid-feedback">Program name is required and must be less than 100 characters.</div>
                            </div>
                            <div class="mb-3">
                                <label for="addProgramDepartment" class="form-label">Department *</label>
                                <select class="form-select" id="addProgramDepartment" required>
                                    <option value="">-- Select Department --</option>
                                    <?php
                                    $departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
                                    foreach ($departments as $dept) {
                                        echo "<option value='{$dept['id']}'>{$dept['name']}</option>";
                                    }
                                    ?>
                                </select>
                                <div class="invalid-feedback">Please select a department.</div>
                            </div>
                            <div class="mb-3">
                                <label for="addProgramStatus" class="form-label">Status</label>
                                <select class="form-select" id="addProgramStatus">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="saveNewProgram()">
                            <i class="fas fa-plus me-2"></i>Add Program
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Program Confirmation Modal -->
        <div class="modal fade" id="deleteProgramModal" tabindex="-1" aria-labelledby="deleteProgramModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger" id="deleteProgramModalLabel">
                            <i class="fas fa-trash me-2"></i>Delete Program
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the program <strong id="deleteProgramName"></strong>?</p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This action cannot be undone. The program will be permanently removed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmDeleteProgram()">
                            <i class="fas fa-trash me-2"></i>Delete Program
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables
        let allDepartments = [];
        let currentEditId = null;
        let selectedDepartments = new Set();
        let currentSort = { field: 'name', direction: 'asc' };
        let selectedPrograms = new Set();
        let allPrograms = [];
        let programToDelete = null;

        // Initialize allDepartments as empty array to prevent undefined errors
        if (!Array.isArray(allDepartments)) {
            allDepartments = [];
        }

        // DOM Ready
        $(document).ready(function() {
            loadDepartments();
            loadStatistics();
            setupEventHandlers();

            // Tab change handler
            $('#mainTabs button').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('data-bs-target');
                if (target === '#programs') {
                    loadPrograms();
                } else if (target === '#departments') {
                    loadDepartments();
                    loadStatistics();
                }
            });

            // Mobile sidebar toggle
            window.toggleSidebar = function() {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.getElementById('mainContent');
                sidebar.classList.toggle('d-none');
                mainContent.classList.toggle('expanded');
            };
        });

        function setupEventHandlers() {
            // Department form submission
            $('#departmentForm').on('submit', handleDepartmentSubmit);

            // Add program field
            $('#addProgramField').on('click', addProgramField);

            // Remove program field
            $(document).on('click', '.remove-program', function() {
                if ($('#programsContainer .input-group').length > 1) {
                    $(this).closest('.input-group').remove();
                }
            });

            // Cancel edit
            $('#cancelEdit').on('click', cancelEdit);

            // Search functionality
            $('#searchInput').on('input', filterDepartments);
            $('#clearSearch').on('click', function() {
                $('#searchInput').val('');
                filterDepartments();
            });

            // Select all checkbox
            $('#selectAllCheckbox').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.department-checkbox').prop('checked', isChecked);

                if (isChecked) {
                    $('.department-checkbox').each(function() {
                        selectedDepartments.add(parseInt($(this).val()));
                    });
                } else {
                    selectedDepartments.clear();
                }
                updateBulkActionsVisibility();
            });

            // Individual department checkboxes
            $(document).on('change', '.department-checkbox', function() {
                const deptId = parseInt($(this).val());
                if ($(this).is(':checked')) {
                    selectedDepartments.add(deptId);
                } else {
                    selectedDepartments.delete(deptId);
                    $('#selectAllCheckbox').prop('checked', false);
                }
                updateBulkActionsVisibility();
            });

            // Form reset
            $('#departmentForm').on('reset', function() {
                $('#programsContainer').html(`
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" name="programs[]" placeholder="Enter program name">
                        <button type="button" class="btn btn-outline-danger remove-program" disabled>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `);
                cancelEdit();
            });

            // Program bulk selection
            $('#selectAllProgramsCheckbox, #headerProgramCheckbox').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.program-checkbox').prop('checked', isChecked);

                if (isChecked) {
                    $('.program-checkbox').each(function() {
                        selectedPrograms.add(parseInt($(this).val()));
                    });
                } else {
                    selectedPrograms.clear();
                }
                updateBulkProgramActionsVisibility();
            });

            // Individual program checkboxes
            $(document).on('change', '.program-checkbox', function() {
                const progId = parseInt($(this).val());
                if ($(this).is(':checked')) {
                    selectedPrograms.add(progId);
                } else {
                    selectedPrograms.delete(progId);
                    $('#selectAllProgramsCheckbox').prop('checked', false);
                    $('#headerProgramCheckbox').prop('checked', false);
                }
                updateBulkProgramActionsVisibility();
            });
        }

        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show">
                    <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alertContainer').html(alertHtml);
            setTimeout(() => $('.alert').alert('close'), 5000);
        }

        function showLoading() {
            $('#loadingOverlay').removeClass('d-none').addClass('d-flex');
        }

        function hideLoading() {
            $('#loadingOverlay').removeClass('d-flex').addClass('d-none');
        }

        function loadDepartments() {
            showLoading();

            $.ajax({
                url: '?ajax=1&action=list_departments',
                method: 'GET',
                timeout: 30000, // 30 second timeout
                dataType: 'json'
            })
            .done(function(data) {
                if (data && data.status === 'success' && Array.isArray(data.departments)) {
                    allDepartments = data.departments;
                    renderDepartments(data.departments);
                    updateStatistics(data.departments);
                    showAlert('success', data.message);
                } else {
                    console.error('Invalid response format:', data);
                    showAlert('danger', 'Received invalid data from server. Please refresh the page and try again.');
                    allDepartments = [];
                    renderDepartments([]);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error loading departments:', {xhr: xhr, status: status, error: error});

                let errorMessage = 'Failed to load departments. ';
                if (status === 'timeout') {
                    errorMessage += 'Request timed out. Please check your connection and try again.';
                } else if (xhr.status === 403) {
                    errorMessage += 'Access denied. Please log in again.';
                } else if (xhr.status === 500) {
                    errorMessage += 'Server error occurred. Please try again later.';
                } else if (xhr.status === 0) {
                    errorMessage += 'Network error. Please check your internet connection.';
                } else {
                    errorMessage += 'Please try again or contact support if the problem persists.';
                }

                showAlert('danger', errorMessage);
                allDepartments = [];
                renderDepartments([]);
            })
            .always(function() {
                hideLoading();
            });
        }

        function renderDepartments(departments) {
            const container = $('#departmentsContainer');

            // Ensure departments is an array
            if (!Array.isArray(departments)) {
                console.error('Invalid departments data:', departments);
                container.html(`
                    <div class="text-center py-5">
                        <span class="text-danger">Error loading departments. Please try again.</span>
                    </div>
                `);
                return;
            }

            if (departments.length === 0) {
                container.html(`
                    <div class="text-center py-5">
                        <span class="text-muted">No departments found.</span>
                    </div>
                `);
                return;
            }

            let html = '';
            departments.forEach(dept => {
                // Filter out duplicate program names
                let uniquePrograms = [];
                let seenNames = new Set();
                if (Array.isArray(dept.programs) && dept.programs.length > 0) {
                    dept.programs.forEach(prog => {
                        if (!seenNames.has(prog.name)) {
                            uniquePrograms.push(prog);
                            seenNames.add(prog.name);
                        }
                    });
                }

                let programsHtml = '<em class="text-muted">No programs</em>';
                if (uniquePrograms.length > 0) {
                    programsHtml = uniquePrograms.map(prog => `
                        <div class="d-inline-flex align-items-center me-2 mb-1">
                            <span class="program-badge ${prog.status_badge} me-1">${prog.name}</span>
                            <button class="btn btn-sm btn-outline-danger delete-program-from-dept" data-prog-id="${prog.id}" data-prog-name="${prog.name}" title="Delete Program">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `).join('');
                }

                html += `
                    <div class="department-item bg-light border-left-info shadow-md rounded-lg p-3 mb-4">
                        <div class="department-header d-flex align-items-center mb-2">
                            <input type="checkbox" class="form-check-input department-checkbox me-2" value="${dept.dept_id}">
                            <h5 class="department-name mb-0">${dept.dept_name}</h5>
                        </div>
                        <div class="department-info mb-1">
                            <strong>Head of Department:</strong> <span>${dept.hod_name === 'Not Assigned' ? '<span class=\"text-danger\">Not Assigned</span>' : dept.hod_name}</span>
                        </div>
                        <div class="department-info mb-1">
                            <strong>Programs (${uniquePrograms.length}):</strong>
                        </div>
                        <div class="programs-list mb-2">
                            ${programsHtml}
                        </div>
                        <div class="add-program-section d-flex gap-2 mb-2">
                            <input type="text" class="form-control form-control-sm add-program-input" placeholder="New program" data-dept-id="${dept.dept_id}">
                            <button class="btn btn-sm btn-outline-success add-program-btn" data-dept-id="${dept.dept_id}">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="action-buttons d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary edit-department" data-dept-id="${dept.dept_id}" data-dept-name="${dept.dept_name}" data-hod-id="${dept.hod_id || ''}">
                                <i class="fas fa-edit me-1"></i>Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-department" data-dept-id="${dept.dept_id}" data-dept-name="${dept.dept_name}">
                                <i class="fas fa-trash me-1"></i>Delete
                            </button>
                        </div>
                    </div>
                `;
            });

            container.html(html);
            attachDepartmentEventHandlers();
        }

        function attachDepartmentEventHandlers() {
            // Edit department
            $('.edit-department').on('click', function(e) {
                e.preventDefault();
                const deptId = $(this).data('dept-id');
                const deptName = $(this).data('dept-name');
                const hodId = $(this).data('hod-id');
                
                editDepartment(deptId, deptName, hodId);
            });

            // Delete department
            $('.delete-department').on('click', function(e) {
                e.preventDefault();
                const deptId = $(this).data('dept-id');
                const deptName = $(this).data('dept-name');
                
                if (confirm(`Are you sure you want to delete "${deptName}" and all its programs?`)) {
                    deleteDepartment(deptId);
                }
            });

            // Add program
            $('.add-program-btn').on('click', function() {
                const deptId = $(this).data('dept-id');
                const input = $(this).siblings('.add-program-input');
                const programName = input.val().trim();
                
                if (programName) {
                    addProgram(deptId, programName);
                    input.val('');
                }
            });

            // Enter key for adding programs
            $('.add-program-input').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $(this).siblings('.add-program-btn').click();
                }
            });

            // Delete program from department view
            $('.delete-program-from-dept').on('click', function(e) {
                e.preventDefault();
                const progId = $(this).data('prog-id');
                const progName = $(this).data('prog-name');
                showDeleteProgramModal(progId, progName);
            });
        }

        function editDepartment(deptId, deptName, hodId) {
            currentEditId = deptId;
            $('#departmentId').val(deptId);
            $('#departmentName').val(deptName);
            $('#hodSelect').val(hodId || '');
            $('#formTitle').text('Edit Department');
            $('#submitText').text('Update Department');
            $('#cancelEdit').show();
            
            $('html, body').animate({
                scrollTop: $('#departmentForm').offset().top - 20
            }, 500);
        }

        function cancelEdit() {
            currentEditId = null;
            $('#departmentId').val('');
            $('#formTitle').text('Add New Department');
            $('#submitText').text('Save Department');
            $('#cancelEdit').hide();
        }

        function handleDepartmentSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const programs = Array.from(formData.getAll('programs[]')).filter(p => p.trim());
            formData.delete('programs[]');
            programs.forEach(p => formData.append('programs[]', p));
            
            const action = currentEditId ? 'edit_department' : 'add_department';
            const url = `?ajax=1&action=${action}`;
            
            showLoading();
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    $('#departmentForm')[0].reset();
                    cancelEdit();
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred. Please try again.');
            })
            .finally(() => {
                hideLoading();
            });
        }

        function addProgram(deptId, programName) {
            showLoading();
            
            $.post('?ajax=1&action=add_program', {
                department_id: deptId,
                program_name: programName
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', 'Program added successfully');
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to add program. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function deleteDepartment(deptId) {
            showLoading();
            
            $.post('?ajax=1&action=delete_department', {
                department_id: deptId
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to delete department. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function filterDepartments() {
            const searchTerm = $('#searchInput').val().toLowerCase();

            if (!searchTerm) {
                renderDepartments(allDepartments);
                return;
            }

            // Ensure allDepartments is an array
            if (!Array.isArray(allDepartments)) {
                console.error('Invalid allDepartments data for filtering:', allDepartments);
                renderDepartments([]);
                return;
            }

            const filtered = allDepartments.filter(dept =>
                dept.dept_name.toLowerCase().includes(searchTerm) ||
                dept.hod_name.toLowerCase().includes(searchTerm) ||
                (dept.programs && dept.programs.some(prog =>
                    prog.name.toLowerCase().includes(searchTerm)
                ))
            );

            renderDepartments(filtered);
        }

        function loadStatistics() {
            $.get('?ajax=1&action=get_statistics')
                .done(function(data) {
                    if (data && data.status === 'success') {
                        renderStatistics(data);
                        showAlert('success', data.message);
                    } else {
                        console.error('Invalid statistics response:', data);
                        showAlert('danger', 'Failed to load statistics.');
                        renderStatistics({
                            total_departments: 0,
                            assigned_hods: 0,
                            total_programs: 0,
                            avg_programs_per_dept: 0
                        });
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Failed to load statistics:', error);
                    showAlert('danger', 'Failed to load statistics.');
                    renderStatistics({
                        total_departments: 0,
                        assigned_hods: 0,
                        total_programs: 0,
                        avg_programs_per_dept: 0
                    });
                });
        }

        function renderStatistics(stats) {
            const container = $('#statisticsContainer');
            const html = `
                <div class="col-md-3">
                    <div class="card text-center border-primary">
                        <div class="card-body">
                            <i class="fas fa-building fa-2x text-primary mb-2"></i>
                            <h4 class="text-primary">${stats.total_departments || 0}</h4>
                            <p class="text-muted mb-0">Total Departments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-success">
                        <div class="card-body">
                            <i class="fas fa-user-tie fa-2x text-success mb-2"></i>
                            <h4 class="text-success">${stats.assigned_hods || 0}</h4>
                            <p class="text-muted mb-0">Assigned HoDs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-info">
                        <div class="card-body">
                            <i class="fas fa-graduation-cap fa-2x text-info mb-2"></i>
                            <h4 class="text-info">${stats.total_programs || 0}</h4>
                            <p class="text-muted mb-0">Total Programs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-warning">
                        <div class="card-body">
                            <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                            <h4 class="text-warning">${stats.avg_programs_per_dept || 0}</h4>
                            <p class="text-muted mb-0">Avg Programs/Dept</p>
                        </div>
                    </div>
                </div>
            `;
            container.html(html);
        }

        function exportData(format) {
            const action = format === 'csv' ? 'export_csv' : 'export_pdf';
            window.location.href = `?ajax=1&action=${action}`;
        }

        function showBulkDeleteModal() {
            if (selectedDepartments.size === 0) {
                showAlert('warning', 'Please select departments to delete');
                return;
            }

            // Ensure allDepartments is an array
            if (!Array.isArray(allDepartments)) {
                console.error('Invalid allDepartments data for bulk delete:', allDepartments);
                showAlert('danger', 'Error loading department data. Please refresh the page.');
                return;
            }

            const selectedDepts = allDepartments.filter(dept => selectedDepartments.has(dept.dept_id));
            const listHtml = selectedDepts.map(dept =>
                `<div class="d-flex align-items-center mb-2">
                    <i class="fas fa-building me-2 text-danger"></i>
                    <span>${dept.dept_name}</span>
                </div>`
            ).join('');

            $('#selectedDepartmentsList').html(listHtml);
            $('#bulkDeleteModal').modal('show');
        }

        function showBulkAssignHodModal() {
            if (selectedDepartments.size === 0) {
                showAlert('warning', 'Please select departments to assign HoD');
                return;
            }

            // Ensure allDepartments is an array
            if (!Array.isArray(allDepartments)) {
                console.error('Invalid allDepartments data for bulk assign:', allDepartments);
                showAlert('danger', 'Error loading department data. Please refresh the page.');
                return;
            }

            const selectedDepts = allDepartments.filter(dept => selectedDepartments.has(dept.dept_id));
            const listHtml = selectedDepts.map(dept =>
                `<div class="d-flex align-items-center mb-2">
                    <i class="fas fa-building me-2 text-primary"></i>
                    <span>${dept.dept_name}</span>
                </div>`
            ).join('');

            $('#bulkAssignDepartmentsList').html(listHtml);
            $('#bulkAssignHodModal').modal('show');
        }

        function confirmBulkDelete() {
            if (selectedDepartments.size === 0) return;

            // Ensure allDepartments is an array before proceeding
            if (!Array.isArray(allDepartments)) {
                console.error('Invalid allDepartments data during bulk delete:', allDepartments);
                showAlert('danger', 'Error: Department data is not available. Please refresh the page.');
                return;
            }

            showLoading();

            $.post('?ajax=1&action=bulk_delete', {
                department_ids: Array.from(selectedDepartments)
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    $('#bulkDeleteModal').modal('hide');
                    selectedDepartments.clear();
                    updateBulkActionsVisibility();
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to delete departments. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function confirmBulkAssignHod() {
            const hodId = $('#bulkHodSelect').val();

            if (!hodId) {
                showAlert('warning', 'Please select a Head of Department');
                return;
            }

            if (selectedDepartments.size === 0) return;

            // Ensure allDepartments is an array before proceeding
            if (!Array.isArray(allDepartments)) {
                console.error('Invalid allDepartments data during bulk assign:', allDepartments);
                showAlert('danger', 'Error: Department data is not available. Please refresh the page.');
                return;
            }

            showLoading();

            $.post('?ajax=1&action=bulk_assign_hod', {
                department_ids: Array.from(selectedDepartments),
                hod_id: hodId
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    $('#bulkAssignHodModal').modal('hide');
                    selectedDepartments.clear();
                    updateBulkActionsVisibility();
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to assign HoD. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function updateBulkActionsVisibility() {
            const hasSelection = selectedDepartments.size > 0;
            $('.dropdown-toggle').prop('disabled', !hasSelection);
        }

        function updateBulkProgramActionsVisibility() {
            const hasSelection = selectedPrograms.size > 0;
            $('#bulkProgramsActions').prop('disabled', !hasSelection);
        }

        function showBulkProgramStatusModal(newStatus) {
            if (selectedPrograms.size === 0) {
                showAlert('warning', 'Please select programs to update');
                return;
            }

            const actionText = newStatus === 'active' ? 'activate' : 'deactivate';
            const selectedProgs = allPrograms.filter(prog => selectedPrograms.has(prog.id));
            const listHtml = selectedProgs.map(prog =>
                `<div class="d-flex align-items-center mb-2">
                    <i class="fas fa-graduation-cap me-2 text-primary"></i>
                    <span>${prog.name}</span>
                </div>`
            ).join('');

            // Create modal
            const modalHtml = `
                <div class="modal fade" id="bulkProgramStatusModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title text-primary">
                                    <i class="fas fa-toggle-${newStatus === 'active' ? 'on' : 'off'} me-2"></i>
                                    Bulk ${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Programs
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to ${actionText} the selected programs?</p>
                                <div id="selectedProgramsList" class="mb-3">${listHtml}</div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-${newStatus === 'active' ? 'success' : 'warning'}" onclick="confirmBulkProgramStatus('${newStatus}')">
                                    <i class="fas fa-toggle-${newStatus === 'active' ? 'on' : 'off'} me-2"></i>${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('bulkProgramStatusModal'));
            modal.show();

            document.getElementById('bulkProgramStatusModal').addEventListener('hidden.bs.modal', function () {
                $('#bulkProgramStatusModal').remove();
            });
        }

        function showBulkProgramDeleteModal() {
            if (selectedPrograms.size === 0) {
                showAlert('warning', 'Please select programs to delete');
                return;
            }

            const selectedProgs = allPrograms.filter(prog => selectedPrograms.has(prog.id));
            const listHtml = selectedProgs.map(prog =>
                `<div class="d-flex align-items-center mb-2">
                    <i class="fas fa-graduation-cap me-2 text-danger"></i>
                    <span>${prog.name}</span>
                </div>`
            ).join('');

            // Create modal
            const modalHtml = `
                <div class="modal fade" id="bulkProgramDeleteModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title text-danger">
                                    <i class="fas fa-trash me-2"></i>Bulk Delete Programs
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete the selected programs? This action cannot be undone.</p>
                                <div id="selectedProgramsDeleteList" class="mb-3">${listHtml}</div>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    This will permanently remove the selected programs.
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-danger" onclick="confirmBulkProgramDelete()">
                                    <i class="fas fa-trash me-2"></i>Delete Selected
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('bulkProgramDeleteModal'));
            modal.show();

            document.getElementById('bulkProgramDeleteModal').addEventListener('hidden.bs.modal', function () {
                $('#bulkProgramDeleteModal').remove();
            });
        }

        function confirmBulkProgramStatus(newStatus) {
            if (selectedPrograms.size === 0) return;

            showLoading();

            $.post('?ajax=1&action=bulk_update_program_status', {
                program_ids: Array.from(selectedPrograms),
                status: newStatus
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('bulkProgramStatusModal')).hide();
                    selectedPrograms.clear();
                    updateBulkProgramActionsVisibility();
                    loadPrograms();
                    loadDepartments(); // Refresh departments to show updated program status
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to update program status. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function confirmBulkProgramDelete() {
            if (selectedPrograms.size === 0) return;

            showLoading();

            $.post('?ajax=1&action=bulk_delete_programs', {
                program_ids: Array.from(selectedPrograms)
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('bulkProgramDeleteModal')).hide();
                    selectedPrograms.clear();
                    updateBulkProgramActionsVisibility();
                    loadPrograms();
                    loadDepartments(); // Refresh departments to remove deleted programs
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to delete programs. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function sortDepartments(field) {
            // Ensure allDepartments is an array
            if (!Array.isArray(allDepartments)) {
                console.error('Invalid allDepartments data for sorting:', allDepartments);
                return;
            }

            let sortedDepartments = [...allDepartments];

            switch (field) {
                case 'name':
                    sortedDepartments.sort((a, b) => {
                        const comparison = a.dept_name.localeCompare(b.dept_name);
                        return currentSort.direction === 'asc' ? comparison : -comparison;
                    });
                    break;
                case 'programs':
                    sortedDepartments.sort((a, b) => {
                        const aPrograms = a.programs ? a.programs.length : 0;
                        const bPrograms = b.programs ? b.programs.length : 0;
                        const comparison = aPrograms - bPrograms;
                        return currentSort.direction === 'asc' ? comparison : -comparison;
                    });
                    break;
                case 'hod':
                    sortedDepartments.sort((a, b) => {
                        const aHasHod = a.hod_id ? 1 : 0;
                        const bHasHod = b.hod_id ? 1 : 0;
                        const comparison = aHasHod - bHasHod;
                        return currentSort.direction === 'asc' ? comparison : -comparison;
                    });
                    break;
            }

            currentSort.field = field;
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            renderDepartments(sortedDepartments);
        }

        function updateStatistics(departments) {
            // Ensure departments is an array
            if (!Array.isArray(departments)) {
                console.error('Invalid departments data for statistics:', departments);
                return;
            }

            const totalDepts = departments.length;
            const totalPrograms = departments.reduce((sum, dept) => sum + (dept.programs ? dept.programs.length : 0), 0);
            const assignedHods = departments.filter(dept => dept.hod_id).length;

            // Update statistics cards if they exist
            if ($('#statisticsContainer').length) {
                loadStatistics();
            }
        }

        function addProgramField() {
            const container = $('#programsContainer');
            const count = container.children().length;

            container.append(`
                <div class="input-group mb-2">
                    <input type="text" class="form-control" name="programs[]" placeholder="Enter program name">
                    <button type="button" class="btn btn-outline-danger remove-program">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);

            // Enable remove buttons if there's more than one field
            if (count > 0) {
                $('.remove-program').prop('disabled', false);
            }
        }

        function loadPrograms() {
            showLoading();

            $.get('?ajax=1&action=list_options')
                .done(function(data) {
                    if (data.status === 'success') {
                        renderPrograms(data.programs);
                        showAlert('success', data.message);
                    } else {
                        showAlert('danger', data.message || 'Failed to load programs.');
                        renderPrograms([]);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Error loading programs:', error);
                    showAlert('danger', 'Failed to load programs. Please try again.');
                    renderPrograms([]);
                })
                .always(function() {
                    hideLoading();
                });
        }

        function renderPrograms(programs) {
            const tbody = $('#programsTableBody');

            if (!Array.isArray(programs)) {
                console.error('Invalid programs data:', programs);
                tbody.html(`
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <span class="text-danger">Error loading programs. Please try again.</span>
                        </td>
                    </tr>
                `);
                return;
            }

            if (programs.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <span class="text-muted">No programs found.</span>
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';
            programs.forEach(prog => {
                // Ensure program object has required properties
                if (!prog || typeof prog !== 'object') {
                    console.warn('Invalid program object:', prog);
                    return;
                }
                const statusBadge = prog.status === 'active' ? 'success' : 'warning';
                const statusText = prog.status === 'active' ? 'Active' : 'Inactive';
                const createdDate = new Date(prog.created_at).toLocaleDateString();

                html += `
                    <tr>
                        <td><input type="checkbox" class="form-check-input program-checkbox" value="${prog.id}" style="margin: 0;"></td>
                        <td>${prog.id}</td>
                        <td>
                            <span class="fw-bold">${prog.name}</span>
                        </td>
                        <td>${prog.department_id || 'N/A'}</td>
                        <td>
                            <span class="badge bg-${statusBadge}" id="status-${prog.id}">${statusText}</span>
                        </td>
                        <td>${createdDate}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary edit-program-btn" data-program-id="${prog.id}" data-program-name="${prog.name}" data-status="${prog.status}" title="Edit Program">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-${statusBadge} toggle-status-btn" data-program-id="${prog.id}" data-current-status="${prog.status}" title="Toggle Status">
                                    <i class="fas fa-toggle-${prog.status === 'active' ? 'on' : 'off'}"></i>
                                </button>
                                <button class="btn btn-outline-danger delete-program-btn" data-program-id="${prog.id}" data-program-name="${prog.name}" title="Delete Program">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.html(html);
            allPrograms = programs; // Store programs data for bulk operations
            attachProgramEventHandlers();
        }

        function attachProgramEventHandlers() {
            // Edit program
            $('.edit-program-btn').on('click', function() {
                const progId = $(this).data('program-id');
                const progName = $(this).data('program-name');
                const status = $(this).data('status');

                showEditProgramModal(progId, progName, status);
            });

            // Toggle status
            $('.toggle-status-btn').on('click', function() {
                const progId = $(this).data('program-id');
                const currentStatus = $(this).data('current-status');
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

                updateProgramStatus(progId, newStatus);
            });

            // Delete program
            $('.delete-program-btn').on('click', function() {
                const progId = $(this).data('program-id');
                const progName = $(this).data('program-name');

                showDeleteProgramModal(progId, progName);
            });
        }

        function showEditProgramModal(progId, progName, status) {
            // Remove existing modal if present
            $('#editProgramModal').remove();

            const modalHtml = `
                <div class="modal fade" id="editProgramModal" tabindex="-1" aria-labelledby="editProgramModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editProgramModalLabel">Edit Program</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editProgramForm">
                                    <input type="hidden" id="editProgramId" value="${progId}">
                                    <div class="mb-3">
                                        <label for="editProgramName" class="form-label">Program Name *</label>
                                        <input type="text" class="form-control" id="editProgramName" value="${progName}" required maxlength="100">
                                        <div class="invalid-feedback">Program name is required and must be less than 100 characters.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editProgramStatus" class="form-label">Status</label>
                                        <select class="form-select" id="editProgramStatus">
                                            <option value="active" ${status === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="inactive" ${status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveProgramEdit()">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('editProgramModal'));

            document.getElementById('editProgramModal').addEventListener('hidden.bs.modal', function () {
                $('#editProgramModal').remove();
            });

            modal.show();
        }

        function saveProgramEdit() {
            const progId = $('#editProgramId').val();
            const progName = $('#editProgramName').val().trim();
            const status = $('#editProgramStatus').val();

            if (!progName) {
                $('#editProgramName').addClass('is-invalid');
                showAlert('warning', 'Program name is required');
                return;
            }

            if (progName.length > 100) {
                $('#editProgramName').addClass('is-invalid');
                showAlert('warning', 'Program name must be less than 100 characters');
                return;
            }

            $('#editProgramName').removeClass('is-invalid');
            showLoading();

            $.post('?ajax=1&action=edit_program', {
                program_id: progId,
                program_name: progName,
                status: status
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('editProgramModal')).hide();
                    loadPrograms();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error updating program:', error);
                showAlert('danger', 'Failed to update program. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function updateProgramStatus(progId, newStatus) {
            showLoading();

            $.post('?ajax=1&action=update_program_status', {
                program_id: progId,
                status: newStatus
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    loadPrograms();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to update program status. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function deleteProgramGlobal(progId) {
            showLoading();

            $.post('?ajax=1&action=delete_program', {
                program_id: progId
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    loadPrograms();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to delete program. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function showAddProgramModal() {
            // Reset form
            $('#addProgramForm')[0].reset();
            $('#addProgramName').removeClass('is-invalid');
            $('#addProgramDepartment').removeClass('is-invalid');

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('addProgramModal'));
            modal.show();
        }

        function saveNewProgram() {
            const progName = $('#addProgramName').val().trim();
            const deptId = $('#addProgramDepartment').val();
            const status = $('#addProgramStatus').val();

            // Validation
            let isValid = true;

            if (!progName) {
                $('#addProgramName').addClass('is-invalid');
                isValid = false;
            } else {
                $('#addProgramName').removeClass('is-invalid');
            }

            if (!deptId) {
                $('#addProgramDepartment').addClass('is-invalid');
                isValid = false;
            } else {
                $('#addProgramDepartment').removeClass('is-invalid');
            }

            if (progName.length > 100) {
                $('#addProgramName').addClass('is-invalid');
                showAlert('warning', 'Program name must be less than 100 characters');
                return;
            }

            if (!isValid) {
                showAlert('warning', 'Please fill in all required fields');
                return;
            }

            showLoading();

            $.post('?ajax=1&action=add_program', {
                program_name: progName,
                department_id: deptId,
                status: status
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('addProgramModal')).hide();
                    loadPrograms();
                    // Also refresh departments to show the new program
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error adding program:', error);
                showAlert('danger', 'Failed to add program. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function showDeleteProgramModal(progId, progName) {
            programToDelete = { id: progId, name: progName };
            $('#deleteProgramName').text(`"${progName}"`);
            const modal = new bootstrap.Modal(document.getElementById('deleteProgramModal'));
            modal.show();
        }

        function confirmDeleteProgram() {
            if (!programToDelete) return;

            const progId = programToDelete.id;
            const progName = programToDelete.name;

            bootstrap.Modal.getInstance(document.getElementById('deleteProgramModal')).hide();
            deleteProgramGlobal(progId);
            programToDelete = null;
        }
    </script>
</body>
</html>