<?php
/**
 * User Management System - Refactored Version
 * Business logic layer for user management operations
 *
 * This file handles all server-side operations including:
 * - AJAX request processing
 * - User CRUD operations
 * - Data validation and security
 * - API responses
 *
 * @version 3.0.0
 * @author Rwanda Polytechnic Development Team
 * @since 2024
 */

// ================================
// INITIALIZATION & DEPENDENCIES
// ================================

session_start();
require_once "config.php";
require_once "session_check.php";
require_once "user_management_functions.php";
require_role(['admin']);

// ================================
// CONFIGURATION & SETUP
// ================================

// Get role parameter for specialized views
$role_param = $_GET['role'] ?? '';
$is_lecturer_registration = ($role_param === 'lecturer');

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// ================================
// AJAX REQUEST HANDLER
// ================================

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    handleAjaxRequest();
    exit;
}

// ================================
// NON-AJAX REQUEST HANDLER
// ================================

// Get initial data for template
$stats = getUserStats();

// Include the presentation layer
include 'manage-users-template.php';

// ================================
// AJAX REQUEST PROCESSOR
// ================================

function handleAjaxRequest(): void
{
    // Set response headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Robots-Tag: noindex, nofollow');

    // Rate limiting check
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'message' => 'Too many requests. Please try again later.',
            'timestamp' => time()
        ], JSON_THROW_ON_ERROR);
        exit;
    }

    try {
        // Validate request method and CSRF token
        validateRequest();

        // Validate and sanitize action parameter
        $action = validateAction($_GET['action'] ?? '');

        // Execute action with proper error handling
        $response = executeAction($action);

        // Log successful action
        logActivity('ajax_action', "Action '{$action}' executed successfully", $_SESSION['user_id'] ?? null);

        echo json_encode($response, JSON_THROW_ON_ERROR);

    } catch (ValidationException $e) {
        handleValidationError($e);
    } catch (SecurityException $e) {
        handleSecurityError($e);
    } catch (DatabaseException $e) {
        handleDatabaseError($e);
    } catch (Throwable $e) {
        handleGeneralError($e, $action ?? 'unknown');
    }
}

// ================================
// ACTION HANDLERS
// ================================

function handleGetUsers(): array
{
    $filters = [
        'search' => sanitize_input($_GET['search'] ?? ''),
        'role' => sanitize_input($_GET['role'] ?? ''),
        'status' => sanitize_input($_GET['status'] ?? ''),
        'department' => sanitize_input($_GET['department'] ?? ''),
        'year_level' => sanitize_input($_GET['year_level'] ?? ''),
        'gender' => sanitize_input($_GET['gender'] ?? ''),
        'age' => sanitize_input($_GET['age'] ?? ''),
        'reg_no' => sanitize_input($_GET['reg_no'] ?? ''),
        'email' => sanitize_input($_GET['email'] ?? '')
    ];

    validateFilters($filters);

    $users = getAllUsers(...array_values($filters));
    $stats = getUserStats();

    return [
        'status' => 'success',
        'data' => $users,
        'stats' => $stats,
        'timestamp' => time(),
        'filtered_count' => count($users)
    ];
}

function handleExportUsers(): never
{
    $filters = [
        'search' => sanitize_input($_GET['search'] ?? ''),
        'role' => sanitize_input($_GET['role'] ?? ''),
        'status' => sanitize_input($_GET['status'] ?? ''),
        'department' => sanitize_input($_GET['department'] ?? ''),
        'year_level' => sanitize_input($_GET['year_level'] ?? ''),
        'gender' => sanitize_input($_GET['gender'] ?? ''),
        'age' => sanitize_input($_GET['age'] ?? ''),
        'reg_no' => sanitize_input($_GET['reg_no'] ?? ''),
        'email' => sanitize_input($_GET['email'] ?? '')
    ];

    validateFilters($filters);

    $users = getAllUsers(...array_values($filters));
    exportUsersToCSV($users);
}

function handleGetDepartments(): array
{
    $departments = getDepartmentsForFilter();
    return [
        'status' => 'success',
        'data' => $departments,
        'count' => count($departments)
    ];
}

function handleGetOptions(): array
{
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT id, name, department_id FROM options WHERE status = 'active' ORDER BY name");
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status' => 'success',
            'data' => $options,
            'count' => count($options)
        ];
    } catch (PDOException $e) {
        logError("Error fetching options: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Failed to load options'
        ];
    }
}

function handleCreateUser(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $userData = extractUserData($_POST, $_FILES);
    validateUserData($userData, 'create');

    // Handle photo upload
    $photo_path = handlePhotoUpload($_FILES['photo'] ?? null);

    // For students, use option_id as reference_id
    if ($userData['role'] === 'student') {
        $userData['reference_id'] = $userData['option_id'];
    }

    // For lecturers, handle special registration with options and courses
    if ($userData['role'] === 'lecturer') {
        return handleCreateLecturer($userData, $photo_path);
    }

    $user_id = createUser(
        $userData['username'],
        $userData['email'],
        $userData['password'],
        $userData['role'],
        $userData['first_name'],
        $userData['last_name'],
        $userData['phone'],
        $userData['reference_id'],
        $userData['gender'],
        $userData['dob'],
        $userData['department_id'],
        $userData['education_level'],
        $photo_path,
        $userData['year_level'],
        $userData['reg_no']
    );

    logActivity('user_created', "User {$userData['username']} created successfully", $user_id);

    return [
        'status' => 'success',
        'message' => 'User created successfully',
        'user_id' => $user_id
    ];
}

function handleCreateLecturer(array $userData, ?string $photo_path): array
{
    global $pdo;

    try {
        // Server-side validation for lecturer-specific fields
        if (empty($userData['gender'])) {
            throw new ValidationException('Gender is required for lecturers');
        }
        if (empty($userData['dob'])) {
            throw new ValidationException('Date of birth is required for lecturers');
        }
        if (empty($userData['department_id'])) {
            throw new ValidationException('Department is required for lecturers');
        }

        // Email validation
        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Please enter a valid email address.');
        }

        // Phone number validation - exactly 10 digits only
        if (!empty($userData['phone'])) {
            if (!preg_match('/^\d{10}$/', $userData['phone'])) {
                throw new ValidationException('Phone number must be exactly 10 digits only (no spaces, dashes, or country codes).');
            }
        }

        // ID number validation - exactly 16 characters (using reference_id as id_number)
        $id_number = $userData['reference_id'] ?? '';
        if (strlen($id_number) !== 16) {
            throw new ValidationException('ID Number must be exactly 16 characters long.');
        }

        // Date of birth validation - must be at least 21 years old
        if (!empty($userData['dob'])) {
            $birthDate = new DateTime($userData['dob']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;

            if ($age < 21) {
                throw new ValidationException('Lecturer must be at least 21 years old. Please select a valid date of birth.');
            }

            if ($age > 100) {
                throw new ValidationException('Please enter a valid date of birth. Age cannot exceed 100 years.');
            }
        }

        // Check for unique email and ID number
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE email = ? OR id_number = ?");
        $stmt->execute([$userData['email'], $id_number]);
        if ($stmt->fetchColumn() > 0) {
            throw new ValidationException('Email or ID Number already exists.');
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        if ($stmt->fetchColumn() > 0) {
            throw new ValidationException('Email already exists in the system.');
        }

        // Generate unique username: firstname.lastname (lowercase)
        $username_base = strtolower(trim(preg_replace('/\s+/', '.', $userData['first_name'] . ' ' . $userData['last_name'])));
        $username = $username_base;
        $suffix = 0;
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        do {
            $checkStmt->execute([$username]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) { $suffix++; $username = $username_base . $suffix; }
        } while ($exists);

        $pdo->beginTransaction();

        // Insert into users table first
        $stmtUser = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmtUser->execute([
            $username,
            $userData['email'],
            password_hash($userData['password'], PASSWORD_DEFAULT),
            'lecturer',
            date('Y-m-d H:i:s')
        ]);

        // Get new user ID
        $user_id = (int)$pdo->lastInsertId();

        // Insert into lecturers table with user_id
        $stmt = $pdo->prepare("INSERT INTO lecturers
            (user_id, first_name, last_name, gender, dob, id_number, email, phone, department_id, education_level, role, photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $userData['first_name'],
            $userData['last_name'],
            $userData['gender'],
            $userData['dob'],
            $id_number,
            $userData['email'],
            $userData['phone'],
            $userData['department_id'],
            $userData['education_level'],
            'lecturer',
            $photo_path
        ]);

        // Get new lecturer ID
        $lecturer_id = (int)$pdo->lastInsertId();

        // Handle option assignments (required, but allow empty if no options exist)
        $selected_options = $_POST['selected_options'] ?? [];
        if (empty($selected_options) || !is_array($selected_options)) {
            // Check if there are any options in the department
            $option_check_stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE department_id = ?");
            $option_check_stmt->execute([$userData['department_id']]);
            $option_count = $option_check_stmt->fetchColumn();

            if ($option_count > 0) {
                throw new ValidationException('At least one option must be selected for the lecturer.');
            }

            // If no options exist in department, allow empty selection
            $option_ids = [];
        } else {
            $option_ids = array_filter(array_map('intval', $selected_options));
            if (empty($option_ids)) {
                throw new ValidationException('Invalid option selection.');
            }
        }

        // Handle course assignments if any courses were selected
        $selected_courses = $_POST['selected_courses'] ?? [];
        if (!empty($selected_courses) && is_array($selected_courses)) {
            try {
                // Validate that all selected courses belong to the lecturer's department
                $course_ids = array_filter(array_map('intval', $selected_courses));
                if (!empty($course_ids)) {
                    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as valid_count
                        FROM courses
                        WHERE id IN ($placeholders) AND department_id = ?
                    ");
                    $stmt->execute(array_merge($course_ids, [$userData['department_id']]));
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result['valid_count'] == count($course_ids)) {
                        // Assign courses to the lecturer
                        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
                        $stmt = $pdo->prepare("
                            UPDATE courses
                            SET lecturer_id = ?
                            WHERE id IN ($placeholders)
                        ");
                        $stmt->execute(array_merge([$lecturer_id], $course_ids));

                        // Log the course assignments
                        $log_stmt = $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, details, created_at)
                            VALUES (?, 'course_assignment_registration', ?, NOW())
                        ");
                        $log_stmt->execute([
                            $_SESSION['user_id'],
                            "Assigned " . count($course_ids) . " courses to newly registered lecturer '{$userData['first_name']} {$userData['last_name']}' (ID: $lecturer_id)"
                        ]);
                    }
                }
            } catch (Exception $e) {
                // Log course assignment error but don't fail the entire registration
                error_log("Course assignment error during lecturer registration: " . $e->getMessage());
            }
        }

        $pdo->commit();

        // Clear statistics cache for this department
        require_once "cache_utils.php";
        cache_delete("lecturer_stats_dept_{$userData['department_id']}");

        // Include course and option assignment info in success message
        $course_count = !empty($selected_courses) ? count(array_filter($selected_courses)) : 0;
        $option_count = count($option_ids);
        $course_message = $course_count > 0 ? " and assigned to $course_count course(s)" : "";
        $option_message = $option_count > 0 ? " with access to $option_count option(s)" : " (no option access assigned)";

        logActivity('lecturer_created', "Lecturer {$userData['first_name']} {$userData['last_name']} created successfully$course_message$option_message", $user_id);

        return [
            'status' => 'success',
            'message' => "Lecturer registered successfully$course_message$option_message! Login credentials: Username: $username, Password: {$userData['password']}!",
            'user_id' => $user_id,
            'lecturer_id' => $lecturer_id
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Error registering lecturer: ' . $e->getMessage());
        throw $e;
    }
}

function handleUpdateUser(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $userData = extractUserData($_POST, $_FILES);
    $user_id = (int)($userData['user_id'] ?? 0);

    if (empty($user_id)) {
        throw new Exception('User ID is required');
    }

    validateUserData($userData, 'update');

    // Handle photo upload if provided
    $photo_path = handlePhotoUpload($_FILES['photo'] ?? null);

    updateUser(
        $user_id,
        $userData['username'],
        $userData['email'],
        $userData['role'],
        $userData['first_name'],
        $userData['last_name'],
        $userData['phone'],
        $userData['reference_id'],
        $userData['status'],
        $userData['gender'],
        $userData['dob'],
        $userData['department_id'],
        $userData['education_level'],
        $photo_path,
        $userData['year_level']
    );

    logActivity('user_updated', "User {$userData['username']} updated successfully", $user_id);

    return [
        'status' => 'success',
        'message' => 'User updated successfully'
    ];
}

function handleResetPassword(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    $new_password = $_POST['new_password'] ?? '';

    if (empty($user_id)) {
        throw new Exception('User ID is required');
    }

    if (empty($new_password)) {
        throw new Exception('New password is required');
    }

    validatePassword($new_password);

    $result = resetUserPassword($user_id, $new_password);

    if (!$result) {
        throw new Exception('User not found or password reset failed');
    }

    logActivity('password_reset', "Password reset for user ID {$user_id}", $user_id);

    return [
        'status' => 'success',
        'message' => 'Password reset successfully'
    ];
}

function handleToggleStatus(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    $status = sanitize_input($_POST['status'] ?? 'active');

    if (empty($user_id)) {
        throw new Exception('User ID is required');
    }

    validateStatus($status);

    toggleUserStatus($user_id, $status);

    logActivity('status_changed', "User status changed to {$status}", $user_id);

    return [
        'status' => 'success',
        'message' => 'User status updated successfully'
    ];
}

function handleDeleteUser(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $user_id = (int)($_POST['user_id'] ?? 0);

    if (empty($user_id)) {
        throw new Exception('User ID is required');
    }

    // Prevent self-deletion
    if ($user_id === $_SESSION['user_id']) {
        throw new Exception('Cannot delete your own account');
    }

    deleteUserSafely($user_id);

    logActivity('user_deleted', "User ID {$user_id} deleted", $user_id);

    return [
        'status' => 'success',
        'message' => 'User deleted successfully'
    ];
}

// ================================
// VALIDATION FUNCTIONS
// ================================

function validateRequest(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf_token)) {
            throw new Exception('Invalid CSRF token');
        }
    }
}

function validateFilters(array $filters): void
{
    $allowed_roles = ['admin', 'hod', 'lecturer', 'student'];
    $allowed_statuses = ['active', 'inactive', 'suspended'];

    if (!empty($filters['role']) && !in_array($filters['role'], $allowed_roles)) {
        throw new Exception('Invalid role filter');
    }

    if (!empty($filters['status']) && !in_array($filters['status'], $allowed_statuses)) {
        throw new Exception('Invalid status filter');
    }

    if (!empty($filters['year_level']) && !in_array($filters['year_level'], ['1', '2', '3', '4'])) {
        throw new Exception('Invalid year level filter');
    }
}

function extractUserData(array $post, array $files): array
{
    // Handle lecturer registration form which uses different field names
    $is_lecturer_registration = ($post['role'] ?? '') === 'lecturer' && isset($post['id_number']);

    if ($is_lecturer_registration) {
        return [
            'user_id' => (int)($post['user_id'] ?? 0),
            'username' => '', // Will be generated
            'email' => sanitize_input($post['email'] ?? ''),
            'password' => $post['password'] ?? 'Welcome123!', // Default password for lecturers
            'role' => 'lecturer',
            'status' => 'active',
            'first_name' => sanitize_input($post['first_name'] ?? ''),
            'last_name' => sanitize_input($post['last_name'] ?? ''),
            'phone' => sanitize_input($post['phone'] ?? ''),
            'reference_id' => sanitize_input($post['id_number'] ?? ''), // ID number as reference_id
            'gender' => sanitize_input($post['gender'] ?? ''),
            'dob' => sanitize_input($post['dob'] ?? ''),
            'department_id' => sanitize_input($post['department_id'] ?? ''),
            'education_level' => sanitize_input($post['education_level'] ?? ''),
            'option_id' => '',
            'year_level' => '',
            'reg_no' => '',
            'photo' => $files['photo'] ?? null
        ];
    }

    return [
        'user_id' => (int)($post['user_id'] ?? 0),
        'username' => sanitize_input($post['username'] ?? ''),
        'email' => sanitize_input($post['email'] ?? ''),
        'password' => $post['password'] ?? '',
        'role' => sanitize_input($post['role'] ?? ''),
        'status' => sanitize_input($post['status'] ?? 'active'),
        'first_name' => sanitize_input($post['first_name'] ?? ''),
        'last_name' => sanitize_input($post['last_name'] ?? ''),
        'phone' => sanitize_input($post['phone'] ?? ''),
        'reference_id' => sanitize_input($post['reference_id'] ?? ''),
        'gender' => sanitize_input($post['gender'] ?? ''),
        'dob' => sanitize_input($post['dob'] ?? ''),
        'department_id' => sanitize_input($post['department_id'] ?? ''),
        'education_level' => sanitize_input($post['education_level'] ?? ''),
        'option_id' => sanitize_input($post['option_id'] ?? ''),
        'year_level' => sanitize_input($post['year_level'] ?? '1'),
        'reg_no' => sanitize_input($post['reg_no'] ?? ''),
        'photo' => $files['photo'] ?? null
    ];
}

function validateUserData(array $data, string $operation): void
{
    $required_fields = ['email', 'first_name', 'last_name'];

    if ($operation === 'create') {
        $required_fields[] = 'role';
        // For regular user creation, require username and password
        if ($data['role'] !== 'lecturer') {
            $required_fields[] = 'username';
            $required_fields[] = 'password';
        }
    }

    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            throw new ValidationException("{$field} is required");
        }
    }

    validateEmail($data['email']);

    if ($operation === 'create' && $data['role'] !== 'lecturer' && strlen($data['password']) < 8) {
        throw new ValidationException('Password must be at least 8 characters long');
    }

    $valid_roles = ['admin', 'hod', 'lecturer', 'student'];
    if (!in_array($data['role'], $valid_roles)) {
        throw new ValidationException('Invalid role specified');
    }

    $valid_statuses = ['active', 'inactive', 'suspended'];
    if (!in_array($data['status'], $valid_statuses)) {
        throw new ValidationException('Invalid status specified');
    }

    // Role-specific validation
    if (in_array($data['role'], ['lecturer', 'hod'])) {
        if (empty($data['gender'])) {
            throw new ValidationException('Gender is required for lecturers');
        }
        if (empty($data['dob'])) {
            throw new ValidationException('Date of birth is required for lecturers');
        }
        if (empty($data['department_id'])) {
            throw new ValidationException('Department is required for lecturers');
        }
        // For lecturers, reference_id should be the ID number (16 characters)
        if ($data['role'] === 'lecturer' && strlen($data['reference_id']) !== 16) {
            throw new ValidationException('ID Number must be exactly 16 characters long');
        }
    }

    if ($data['role'] === 'student') {
        if (empty($data['option_id'])) {
            throw new ValidationException('Program/Option is required for students');
        }
        if (empty($data['reg_no'])) {
            throw new ValidationException('Registration number is required for students');
        }
    }
}

function validateStatus(string $status): void
{
    $valid_statuses = ['active', 'inactive', 'suspended'];
    if (!in_array($status, $valid_statuses)) {
        throw new ValidationException('Invalid status specified');
    }
}

// ================================
// ENHANCED ERROR HANDLING & SECURITY
// ================================

/**
 * Custom exception classes for better error handling
 */
class ValidationException extends Exception {}
class SecurityException extends Exception {}
class DatabaseException extends Exception {}

/**
 * Validate and sanitize action parameter
 */
function validateAction(string $action): string
{
    $allowed_actions = [
        'get_users', 'export_users', 'get_departments', 'get_options',
        'create_user', 'update_user', 'reset_password', 'toggle_status', 'delete_user'
    ];

    if (empty($action)) {
        throw new ValidationException('Action parameter is required');
    }

    if (!in_array($action, $allowed_actions)) {
        throw new SecurityException('Invalid action specified');
    }

    return $action;
}

/**
 * Execute action with proper error handling
 */
function executeAction(string $action): array
{
    return match ($action) {
        'get_users' => handleGetUsers(),
        'export_users' => handleExportUsers(),
        'get_departments' => handleGetDepartments(),
        'get_options' => handleGetOptions(),
        'create_user' => handleCreateUser(),
        'update_user' => handleUpdateUser(),
        'reset_password' => handleResetPassword(),
        'toggle_status' => handleToggleStatus(),
        'delete_user' => handleDeleteUser(),
        default => throw new Exception('Unhandled action: ' . $action)
    };
}

/**
 * Check rate limiting for API requests
 */
function checkRateLimit(): bool
{
    $max_requests = 100; // requests per hour
    $time_window = 3600; // 1 hour in seconds

    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $current_time = time();

    // Simple in-memory rate limiting (in production, use Redis or database)
    static $request_counts = [];

    if (!isset($request_counts[$user_ip])) {
        $request_counts[$user_ip] = [
            'count' => 1,
            'window_start' => $current_time
        ];
        return true;
    }

    $user_requests = &$request_counts[$user_ip];

    // Reset window if expired
    if (($current_time - $user_requests['window_start']) > $time_window) {
        $user_requests['count'] = 1;
        $user_requests['window_start'] = $current_time;
        return true;
    }

    // Check if under limit
    if ($user_requests['count'] >= $max_requests) {
        return false;
    }

    $user_requests['count']++;
    return true;
}

/**
 * Handle validation errors
 */
function handleValidationError(ValidationException $e): never
{
    logError('Validation Error: ' . $e->getMessage(), [
        'action' => $_GET['action'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'type' => 'validation',
        'message' => $e->getMessage(),
        'timestamp' => time()
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Handle security errors
 */
function handleSecurityError(SecurityException $e): never
{
    logError('Security Error: ' . $e->getMessage(), [
        'action' => $_GET['action'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    // Don't reveal security details to client
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'type' => 'security',
        'message' => 'Access denied',
        'timestamp' => time()
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Handle database errors
 */
function handleDatabaseError(DatabaseException $e): never
{
    logError('Database Error: ' . $e->getMessage(), [
        'action' => $_GET['action'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'type' => 'database',
        'message' => 'Database operation failed',
        'timestamp' => time()
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Handle general errors
 */
function handleGeneralError(Throwable $e, string $action): never
{
    logError('General Error: ' . $e->getMessage(), [
        'action' => $action,
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'type' => 'system',
        'message' => 'An unexpected error occurred',
        'timestamp' => time()
    ], JSON_THROW_ON_ERROR);
    exit;
}

/**
 * Enhanced input sanitization
 */
// function sanitize_input(string $input): string
// {
//     $input = trim($input);
//     $input = stripslashes($input);
//     $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

//     // Remove potential null bytes
//     $input = str_replace("\0", '', $input);

//     // Limit length
//     if (strlen($input) > 1000) {
//         throw new ValidationException('Input too long');
//     }

//     return $input;
// }

/**
 * Validate email with enhanced checks
 */
function validateEmail(string $email): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ValidationException('Invalid email format');
    }

    // Check for suspicious patterns
    $suspicious_patterns = ['..', '@@', 'javascript:', 'data:', 'vbscript:'];
    foreach ($suspicious_patterns as $pattern) {
        if (stripos($email, $pattern) !== false) {
            throw new SecurityException('Invalid email format');
        }
    }

    // Check email length
    if (strlen($email) > 254) {
        throw new ValidationException('Email address too long');
    }
}

/**
 * Enhanced password validation
 */
function validatePassword(string $password): void
{
    if (strlen($password) < 8) {
        throw new ValidationException('Password must be at least 8 characters long');
    }

    if (strlen($password) > 128) {
        throw new ValidationException('Password too long');
    }

    if (!preg_match('/[A-Z]/', $password)) {
        throw new ValidationException('Password must contain at least one uppercase letter');
    }

    if (!preg_match('/[a-z]/', $password)) {
        throw new ValidationException('Password must contain at least one lowercase letter');
    }

    if (!preg_match('/[0-9]/', $password)) {
        throw new ValidationException('Password must contain at least one number');
    }

    // Check for common weak passwords
    $weak_passwords = ['password', '123456', 'password123', 'admin', 'qwerty'];
    if (in_array(strtolower($password), $weak_passwords)) {
        throw new ValidationException('Password is too common');
    }
}

/**
 * Enhanced file upload validation
 */
function handlePhotoUpload(?array $photo): ?string
{
    if (!$photo || $photo['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Check for upload errors
    switch ($photo['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new ValidationException('File size too large');
        case UPLOAD_ERR_PARTIAL:
            throw new ValidationException('File upload incomplete');
        case UPLOAD_ERR_NO_FILE:
            return null;
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            throw new Exception('File upload failed due to server configuration');
        default:
            throw new Exception('Unknown upload error');
    }

    // Validate file type using magic bytes for better security
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_info = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($file_info, $photo['tmp_name']);
    finfo_close($file_info);

    if (!in_array($mime_type, $allowed_types)) {
        throw new ValidationException('Invalid file type. Only JPEG, PNG, and GIF images are allowed');
    }

    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024;
    if ($photo['size'] > $max_size) {
        throw new ValidationException('File size too large. Maximum size is 5MB');
    }

    // Validate file extension matches MIME type
    $extension = strtolower(pathinfo($photo['name'], PATHINFO_EXTENSION));
    $expected_extensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif']
    ];

    if (!isset($expected_extensions[$mime_type]) || !in_array($extension, $expected_extensions[$mime_type])) {
        throw new SecurityException('File extension does not match file type');
    }

    // Create upload directory if it doesn't exist
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Failed to create upload directory');
        }
    }

    // Generate cryptographically secure filename
    try {
        $extension = pathinfo($photo['name'], PATHINFO_EXTENSION);
        $random_bytes = random_bytes(16);
        $file_name = time() . '_' . bin2hex($random_bytes) . '.' . $extension;
        $target_file = $upload_dir . $file_name;
    } catch (Exception $e) {
        throw new Exception('Failed to generate secure filename');
    }

    // Move uploaded file with error checking
    if (!move_uploaded_file($photo['tmp_name'], $target_file)) {
        throw new Exception('Failed to save uploaded file');
    }

    // Verify file was moved successfully
    if (!file_exists($target_file)) {
        throw new Exception('Uploaded file verification failed');
    }

    return $target_file;
}

/**
 * Enhanced database error handling
 */
function deleteUserSafely(int $user_id): void
{
    global $pdo;

    // Validate user_id
    if ($user_id <= 0) {
        throw new ValidationException('Invalid user ID');
    }

    // Start transaction for safe deletion
    $pdo->beginTransaction();

    try {
        // Check if user exists before deletion
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new ValidationException('User not found');
        }

        // Delete from role-specific tables first (due to foreign key constraints)
        $stmt = $pdo->prepare("DELETE FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);

        $stmt = $pdo->prepare("DELETE FROM lecturers WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Delete from users table
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        $pdo->commit();

        logActivity('user_deleted', "User '{$user['username']}' (ID: {$user_id}) deleted successfully", $user_id);

    } catch (PDOException $e) {
        $pdo->rollBack();
        throw new DatabaseException('Failed to delete user: ' . $e->getMessage());
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Enhanced error message handling
 */
function getErrorMessage(Throwable $e): string
{
    // Return user-friendly error messages based on exception type
    return match (get_class($e)) {
        'PDOException' => 'Database operation failed',
        'ValidationException' => $e->getMessage(),
        'SecurityException' => 'Security validation failed',
        'DatabaseException' => $e->getMessage(),
        'Exception' => 'An error occurred',
        default => 'An unexpected error occurred'
    };
}

/**
 * Enhanced logging with context
 */
function logError(string $message, array $context = []): void
{
    $log_entry = sprintf(
        '[%s] %s %s',
        date('Y-m-d H:i:s'),
        $message,
        $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
    );

    error_log($log_entry);

    // In production, you might want to log to a file or external service
    // file_put_contents('logs/error.log', $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Enhanced activity logging
 */
function logActivity(string $action, string $description, ?int $user_id = null): void
{
    static $log_cache = [];
    $cache_key = $action . '|' . $user_id . '|' . time();

    // Prevent duplicate logging within the same second
    if (isset($log_cache[$cache_key])) {
        return;
    }
    $log_cache[$cache_key] = true;

    try {
        global $pdo;

        // Use prepared statement with proper error handling
        $stmt = $pdo->prepare("
            INSERT INTO system_logs (user_id, action, description, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

    } catch (PDOException $e) {
        logError('Failed to log activity: ' . $e->getMessage(), [
            'action' => $action,
            'user_id' => $user_id,
            'description' => $description
        ]);
    } catch (Exception $e) {
        logError('Unexpected error logging activity: ' . $e->getMessage());
    }
}