<?php
/**
 * User Management System - Refactored Version
 * Comprehensive user management interface for administrators
 * Handles user creation, editing, role management, and account status
 *
 * IMPROVEMENTS:
 * - Better code organization and separation of concerns
 * - Enhanced security with improved input validation
 * - Optimized database queries with prepared statements
 * - Improved UI/UX with modern design patterns
 * - Better error handling and user feedback
 * - Cross-page synchronization for real-time updates
 * - Advanced filtering and search capabilities
 * - Export functionality for user data
 *
 * @version 2.1.0
 * @author Rwanda Polytechnic Development Team
 * @since 2024
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Get role parameter for specialized views
$role_param = $_GET['role'] ?? '';
$is_lecturer_registration = ($role_param === 'lecturer');

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================


/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate role
 */
function validate_role($role) {
    $valid_roles = ['admin', 'hod', 'lecturer', 'student'];
    return in_array($role, $valid_roles);
}

/**
 * Validate status
 */
function validate_status($status) {
    $valid_statuses = ['active', 'inactive', 'suspended'];
    return in_array($status, $valid_statuses);
}


/**
 * Get all users with their details - Optimized Query
 */
function getAllUsers($search = '', $role_filter = '', $status_filter = '', $department_filter = '', $year_level_filter = '', $gender_filter = '', $age_filter = '', $reg_no_filter = '', $email_filter = '') {
    global $pdo;

    try {
        // Optimized query with better indexing and conditional joins
        $sql = "
            SELECT
                u.id,
                u.username,
                u.email,
                u.role,
                u.status,
                u.created_at,
                u.updated_at,
                u.last_login,
                COALESCE(s.first_name, l.first_name, 'System') as first_name,
                COALESCE(s.last_name, l.last_name, 'User') as last_name,
                CASE
                    WHEN u.role = 'student' THEN s.reg_no
                    WHEN u.role IN ('lecturer', 'hod') THEN l.id_number
                    ELSE NULL
                END as reference_id,
                COALESCE(s.telephone, l.phone) as phone,
                CASE
                    WHEN u.role = 'student' THEN s.dob
                    WHEN u.role IN ('lecturer', 'hod') THEN l.dob
                    ELSE NULL
                END as dob,
                CASE
                    WHEN u.role = 'student' THEN s.sex
                    WHEN u.role IN ('lecturer', 'hod') THEN l.gender
                    ELSE NULL
                END as gender,
                s.year_level,
                l.education_level,
                l.photo,
                CASE
                    WHEN u.role = 'student' THEN d.name
                    WHEN u.role IN ('lecturer', 'hod') THEN l_dept.name
                    ELSE NULL
                END as department_name,
                CASE
                    WHEN u.role = 'student' THEN s.department_id
                    WHEN u.role IN ('lecturer', 'hod') THEN l.department_id
                    ELSE NULL
                END as department_id
            FROM users u
            LEFT JOIN students s ON u.email = s.email AND u.role = 'student'
            LEFT JOIN lecturers l ON u.email = l.email AND u.role IN ('lecturer', 'hod')
            LEFT JOIN departments d ON s.department_id = d.id
            LEFT JOIN departments l_dept ON l.department_id = l_dept.id
            WHERE 1=1
        ";

        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(s.first_name, l.first_name, ''), ' ', COALESCE(s.last_name, l.last_name, '')) LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
        }

        if (!empty($role_filter) && validate_role($role_filter)) {
            $conditions[] = "u.role = ?";
            $params[] = $role_filter;
        }

        if (!empty($status_filter) && validate_status($status_filter)) {
            $conditions[] = "u.status = ?";
            $params[] = $status_filter;
        }

        if (!empty($department_filter)) {
            if (in_array($role_filter, ['lecturer', 'hod', ''])) {
                $conditions[] = "(l.department_id = ? OR s.department_id = ?)";
                $params[] = $department_filter;
                $params[] = $department_filter;
            } else {
                $conditions[] = "s.department_id = ?";
                $params[] = $department_filter;
            }
        }

        if (!empty($year_level_filter)) {
            $conditions[] = "s.year_level = ?";
            $params[] = $year_level_filter;
        }

        if (!empty($gender_filter)) {
            $conditions[] = "s.sex = ?";
            $params[] = $gender_filter;
        }

        if (!empty($age_filter)) {
            $age_ranges = explode('-', $age_filter);
            if (count($age_ranges) === 2) {
                $min_age = (int)$age_ranges[0];
                $max_age = (int)$age_ranges[1];
                $conditions[] = "TIMESTAMPDIFF(YEAR, s.dob, CURDATE()) BETWEEN ? AND ?";
                $params[] = $min_age;
                $params[] = $max_age;
            } elseif ($age_filter === '26+') {
                $conditions[] = "TIMESTAMPDIFF(YEAR, s.dob, CURDATE()) >= ?";
                $params[] = 26;
            }
        }

        if (!empty($reg_no_filter)) {
            $conditions[] = "s.reg_no LIKE ?";
            $params[] = "%{$reg_no_filter}%";
        }

        if (!empty($email_filter)) {
            $conditions[] = "u.email LIKE ?";
            $params[] = "%{$email_filter}%";
        }

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY u.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user statistics
 */
function getUserStats() {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT
                role,
                status,
                COUNT(*) as count
            FROM users
            GROUP BY role, status
        ");

        $stmt->execute();
        $roleStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate totals
        $total = array_sum(array_column($roleStats, 'count'));
        $active = array_sum(array_column(array_filter($roleStats, fn($s) => $s['status'] === 'active'), 'count'));
        $inactive = array_sum(array_column(array_filter($roleStats, fn($s) => $s['status'] === 'inactive'), 'count'));
        $suspended = array_sum(array_column(array_filter($roleStats, fn($s) => $s['status'] === 'suspended'), 'count'));

        // Group by role
        $byRole = [];
        foreach ($roleStats as $stat) {
            $role = $stat['role'];
            if (!isset($byRole[$role])) {
                $byRole[$role] = ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0];
            }
            $byRole[$role]['total'] += $stat['count'];
            $byRole[$role][$stat['status']] = $stat['count'];
        }

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'suspended' => $suspended,
            'by_role' => $byRole
        ];

    } catch (PDOException $e) {
        error_log("Error fetching user stats: " . $e->getMessage());
        return ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0, 'by_role' => []];
    }
}

/**
 * Create new user - Enhanced with better validation
 */
function createUser($username, $email, $password, $role, $first_name, $last_name, $phone = null, $reference_id = null, $gender = null, $dob = null, $department_id = null, $education_level = null, $photo = null) {
    global $pdo;

    try {
        // Comprehensive input validation
        $username = sanitize_input($username);
        $email = sanitize_input($email);
        $role = sanitize_input($role);
        $first_name = sanitize_input($first_name);
        $last_name = sanitize_input($last_name);
        $phone = $phone ? sanitize_input($phone) : null;
        $reference_id = $reference_id ? sanitize_input($reference_id) : null;

        // Required field validation
        $required_fields = ['username', 'email', 'password', 'role', 'first_name', 'last_name'];
        foreach ($required_fields as $field) {
            if (empty($$field)) {
                throw new Exception("Field '{$field}' is required");
            }
        }

        // Format validation
        if (!validate_email($email)) {
            throw new Exception('Invalid email format');
        }

        if (!validate_role($role)) {
            throw new Exception('Invalid role specified');
        }

        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }

        // Username format validation
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            throw new Exception('Username must be 3-50 characters long and contain only letters, numbers, and underscores');
        }

        // Name validation
        if (strlen($first_name) < 2 || strlen($last_name) < 2) {
            throw new Exception('First and last names must be at least 2 characters long');
        }

        // Lecturer-specific validation
        if ($role === 'lecturer') {
            if (empty($gender)) {
                throw new Exception('Gender is required for lecturers');
            }
            if (empty($dob)) {
                throw new Exception('Date of birth is required for lecturers');
            }
            if (empty($department_id)) {
                throw new Exception('Department is required for lecturers');
            }
            if (empty($education_level)) {
                throw new Exception('Education level is required for lecturers');
            }
            // Validate DOB format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                throw new Exception('Invalid date of birth format');
            }
        }

        // Check for existing username or email
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }

        // Hash password with better algorithm
        $hashed_password = password_hash($password, PASSWORD_ARGON2ID);

        // Start transaction
        $pdo->beginTransaction();

        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([$username, $email, $hashed_password, $role]);

        $user_id = $pdo->lastInsertId();

        // Insert role-specific data with validation
        switch ($role) {
            case 'student':
                if (empty($reference_id)) {
                    throw new Exception('Registration number is required for students');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO students (first_name, last_name, email, telephone, reg_no, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $reference_id]);
                break;

            case 'lecturer':
            case 'hod':
                if (empty($reference_id)) {
                    throw new Exception('Employee ID is required for lecturers');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO lecturers (first_name, last_name, email, phone, id_number, role, gender, dob, department_id, education_level, photo, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $reference_id, $role, $gender, $dob, $department_id, $education_level, $photo]);
                break;
        }

        $pdo->commit();
        return $user_id;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creating user: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Update user
 */
function updateUser($user_id, $username, $email, $role, $first_name, $last_name, $phone = null, $reference_id = null, $status = 'active', $gender = null, $dob = null, $department_id = null, $education_level = null, $photo = null) {
    global $pdo;

    try {
        // Validate inputs
        if (empty($user_id) || empty($username) || empty($email) || empty($role) || empty($first_name) || empty($last_name)) {
            throw new Exception('All required fields must be filled');
        }

        if (!validate_email($email)) {
            throw new Exception('Invalid email format');
        }

        if (!validate_role($role)) {
            throw new Exception('Invalid role specified');
        }

        if (!validate_status($status)) {
            throw new Exception('Invalid status specified');
        }

        // Lecturer-specific validation
        if ($role === 'lecturer') {
            if (empty($gender)) {
                throw new Exception('Gender is required for lecturers');
            }
            if (empty($dob)) {
                throw new Exception('Date of birth is required for lecturers');
            }
            if (empty($department_id)) {
                throw new Exception('Department is required for lecturers');
            }
            if (empty($education_level)) {
                throw new Exception('Education level is required for lecturers');
            }
            // Validate DOB format
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                throw new Exception('Invalid date of birth format');
            }
        }

        // Check if username or email already exists (excluding current user)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user_id]);

        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }

        // Start transaction
        $pdo->beginTransaction();


        // Update user
        $stmt = $pdo->prepare("
            UPDATE users
            SET username = ?, email = ?, role = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$username, $email, $role, $status, $user_id]);

        // Get previous email for role-specific update
        $prevEmailStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $prevEmailStmt->execute([$user_id]);
        $prevEmail = $prevEmailStmt->fetchColumn();

        // Update role-specific data using user_id if possible, else fallback to email
        switch ($role) {
            case 'student':
                $stmt = $pdo->prepare("
                    UPDATE students
                    SET first_name = ?, last_name = ?, telephone = ?, reg_no = ?, email = ?, updated_at = NOW()
                    WHERE email = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $reference_id, $email, $prevEmail]);
                break;

            case 'lecturer':
            case 'hod':
                $stmt = $pdo->prepare("
                    UPDATE lecturers
                    SET first_name = ?, last_name = ?, phone = ?, id_number = ?, role = ?, gender = ?, dob = ?, department_id = ?, education_level = ?, photo = ?, email = ?, updated_at = NOW()
                    WHERE email = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $reference_id, $role, $gender, $dob, $department_id, $education_level, $photo, $email, $prevEmail]);
                break;
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error updating user: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Reset user password
 */
function resetUserPassword($user_id, $new_password) {
    global $pdo;

    try {
        if (empty($user_id) || empty($new_password)) {
            throw new Exception('User ID and new password are required');
        }

        if (strlen($new_password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            UPDATE users
            SET password = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$hashed_password, $user_id]);

        return $stmt->rowCount() > 0;

    } catch (Exception $e) {
        error_log("Error resetting password: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Toggle user status
 */
function toggleUserStatus($user_id, $status) {
    global $pdo;

    try {
        if (empty($user_id)) {
            throw new Exception('User ID is required');
        }

        if (!validate_status($status)) {
            throw new Exception('Invalid status value');
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $user_id]);

        return $stmt->rowCount() > 0;

    } catch (Exception $e) {
        error_log("Error toggling user status: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Export users data to CSV
 */
function exportUsersToCSV($users) {
    try {
        $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, [
            'ID',
            'Username',
            'Email',
            'Role',
            'Status',
            'First Name',
            'Last Name',
            'Reference ID',
            'Phone',
            'Department',
            'Year Level',
            'Gender',
            'Age',
            'Created Date',
            'Last Login'
        ]);

        // CSV data
        foreach ($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['username'],
                $user['email'],
                ucfirst($user['role']),
                ucfirst($user['status'] ?? 'active'),
                $user['first_name'],
                $user['last_name'],
                $user['reference_id'],
                $user['phone'],
                $user['department_name'],
                $user['year_level'],
                $user['gender'],
                $user['dob'] ? calculateAge($user['dob']) : '',
                $user['created_at'] ? date('Y-m-d H:i:s', strtotime($user['created_at'])) : '',
                $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : ''
            ]);
        }

        fclose($output);
        exit;

    } catch (Exception $e) {
        error_log("Error exporting users to CSV: " . $e->getMessage());
        throw new Exception('Failed to export users data');
    }
}

/**
 * Calculate age from date of birth
 */
function calculateAge($dob) {
    if (!$dob) return '';

    try {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        $age = $today->diff($birthDate);
        return $age->y;
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Get departments for filter dropdown
 */
function getDepartmentsForFilter() {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

// ============================================================================
// AJAX REQUEST HANDLER
// ============================================================================

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    try {
        // Validate CSRF token for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrf_token = $_POST['csrf_token'] ?? '';
            if (!validate_csrf_token($csrf_token)) {
                throw new Exception('Invalid CSRF token');
            }
        }

        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'get_users':
                $search = sanitize_input($_GET['search'] ?? '');
                $role_filter = sanitize_input($_GET['role'] ?? '');
                $status_filter = sanitize_input($_GET['status'] ?? '');
                $department_filter = sanitize_input($_GET['department'] ?? '');
                $year_level_filter = sanitize_input($_GET['year_level'] ?? '');
                $gender_filter = sanitize_input($_GET['gender'] ?? '');
                $age_filter = sanitize_input($_GET['age'] ?? '');
                $reg_no_filter = sanitize_input($_GET['reg_no'] ?? '');
                $email_filter = sanitize_input($_GET['email'] ?? '');

                $users = getAllUsers($search, $role_filter, $status_filter, $department_filter, $year_level_filter, $gender_filter, $age_filter, $reg_no_filter, $email_filter);
                $stats = getUserStats();

                echo json_encode([
                    'status' => 'success',
                    'data' => $users,
                    'stats' => $stats,
                    'timestamp' => time()
                ]);
                break;

            case 'export_users':
                $search = sanitize_input($_GET['search'] ?? '');
                $role_filter = sanitize_input($_GET['role'] ?? '');
                $status_filter = sanitize_input($_GET['status'] ?? '');
                $department_filter = sanitize_input($_GET['department'] ?? '');
                $year_level_filter = sanitize_input($_GET['year_level'] ?? '');
                $gender_filter = sanitize_input($_GET['gender'] ?? '');
                $age_filter = sanitize_input($_GET['age'] ?? '');
                $reg_no_filter = sanitize_input($_GET['reg_no'] ?? '');
                $email_filter = sanitize_input($_GET['email'] ?? '');

                $users = getAllUsers($search, $role_filter, $status_filter, $department_filter, $year_level_filter, $gender_filter, $age_filter, $reg_no_filter, $email_filter);
                exportUsersToCSV($users);
                break;

            case 'get_departments':
                $departments = getDepartmentsForFilter();
                echo json_encode([
                    'status' => 'success',
                    'data' => $departments
                ]);
                break;

            case 'create_user':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method');
                }

                $username = sanitize_input($_POST['username'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = sanitize_input($_POST['role'] ?? '');
                $first_name = sanitize_input($_POST['first_name'] ?? '');
                $last_name = sanitize_input($_POST['last_name'] ?? '');
                $phone = sanitize_input($_POST['phone'] ?? '');
                $reference_id = sanitize_input($_POST['reference_id'] ?? '');
                $gender = sanitize_input($_POST['gender'] ?? '');
                $dob = sanitize_input($_POST['dob'] ?? '');
                $department_id = sanitize_input($_POST['department_id'] ?? '');
                $education_level = sanitize_input($_POST['education_level'] ?? '');

                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $file_name = time() . '_' . basename($_FILES['photo']['name']);
                    $target_file = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                        $photo_path = $target_file;
                    }
                }

                $user_id = createUser($username, $email, $password, $role, $first_name, $last_name, $phone, $reference_id, $gender, $dob, $department_id, $education_level, $photo_path);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'User created successfully',
                    'user_id' => $user_id
                ]);
                break;

            case 'update_user':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method');
                }

                $user_id = (int)($_POST['user_id'] ?? 0);
                $username = sanitize_input($_POST['username'] ?? '');
                $email = sanitize_input($_POST['email'] ?? '');
                $role = sanitize_input($_POST['role'] ?? '');
                $status = sanitize_input($_POST['status'] ?? 'active');
                $first_name = sanitize_input($_POST['first_name'] ?? '');
                $last_name = sanitize_input($_POST['last_name'] ?? '');
                $phone = sanitize_input($_POST['phone'] ?? '');
                $reference_id = sanitize_input($_POST['reference_id'] ?? '');
                $gender = sanitize_input($_POST['gender'] ?? '');
                $dob = sanitize_input($_POST['dob'] ?? '');
                $department_id = sanitize_input($_POST['department_id'] ?? '');
                $education_level = sanitize_input($_POST['education_level'] ?? '');

                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $file_name = time() . '_' . basename($_FILES['photo']['name']);
                    $target_file = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                        $photo_path = $target_file;
                    }
                } else {
                    // Keep existing photo if not uploading new
                    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $userEmail = $stmt->fetchColumn();
                    if ($userEmail) {
                        $stmt = $pdo->prepare("SELECT photo FROM lecturers WHERE email = ?");
                        $stmt->execute([$userEmail]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        $photo_path = $existing['photo'] ?? null;
                    }
                }

                updateUser($user_id, $username, $email, $role, $first_name, $last_name, $phone, $reference_id, $status, $gender, $dob, $department_id, $education_level, $photo_path);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'User updated successfully'
                ]);
                break;

            case 'reset_password':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method');
                }

                $user_id = (int)($_POST['user_id'] ?? 0);
                $new_password = $_POST['new_password'] ?? '';

                resetUserPassword($user_id, $new_password);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Password reset successfully'
                ]);
                break;

            case 'toggle_status':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method');
                }

                $user_id = (int)($_POST['user_id'] ?? 0);
                $status = sanitize_input($_POST['status'] ?? 'active');

                toggleUserStatus($user_id, $status);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'User status updated successfully'
                ]);
                break;

            default:
                throw new Exception('Invalid action');
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Get initial data
$stats = getUserStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $is_lecturer_registration ? 'Register Lecturer' : 'Manage Users'; ?> | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
            --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
        }

        body {
            background: linear-gradient(to right, #0066cc, #003366);
            min-height: 100vh;
            font-family: 'Segoe UI', 'Roboto', sans-serif;
            margin: 0;
            position: relative;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
            border-right: 1px solid rgba(0, 102, 204, 0.1);
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(0, 102, 204, 0.1);
        }

        .sidebar .logo {
            background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
            color: white;
            padding: 25px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .sidebar .logo::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="20" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
            pointer-events: none;
        }

        .sidebar .logo h3 {
            color: white;
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .sidebar .logo small {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
            font-weight: 500;
            position: relative;
            z-index: 2;
        }

        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .sidebar-nav .nav-section {
            padding: 15px 20px 10px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid rgba(0, 102, 204, 0.1);
            margin-bottom: 10px;
        }

        .sidebar-nav a {
            display: block;
            padding: 14px 25px;
            color: #495057;
            text-decoration: none;
            border-radius: 0 25px 25px 0;
            margin: 0 0 2px 0;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover {
            background: rgba(0, 102, 204, 0.08);
            color: #0066cc;
            border-left-color: #0066cc;
            transform: translateX(8px);
            box-shadow: 2px 0 8px rgba(0, 102, 204, 0.15);
        }

        .sidebar-nav a.active {
            background: linear-gradient(90deg, rgba(0, 102, 204, 0.15) 0%, rgba(0, 102, 204, 0.05) 100%);
            color: #0066cc;
            border-left-color: #0066cc;
            box-shadow: 2px 0 12px rgba(0, 102, 204, 0.2);
            font-weight: 600;
        }

        .sidebar-nav a.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: #0066cc;
            border-radius: 0 2px 2px 0;
        }

        .sidebar-nav a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .sidebar-nav .mt-4 {
            margin-top: 2rem !important;
            padding-top: 2rem !important;
            border-top: 1px solid rgba(0, 102, 204, 0.1);
        }

        .main-content {
            margin-left: 280px;
            padding: 20px 30px;
            min-height: 100vh;
        }

        .card {
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: none;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
        }

        .table {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .table thead th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }

        .badge {
            font-size: 0.8rem;
            padding: 6px 12px;
            border-radius: 20px;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 10px 15px;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-heavy);
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
            border: none;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-overlay .spinner-border {
            color: #0066cc;
            width: 3rem;
            height: 3rem;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1003;
            background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            box-shadow: 0 4px 20px rgba(0, 102, 204, 0.3);
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: var(--transition);
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                width: 260px;
                z-index: 1002;
            }

            .sidebar.show {
                transform: translateX(0);
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            }

            .sidebar.show::after {
                content: '';
                position: fixed;
                top: 0;
                left: 260px;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
                z-index: -1;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .mobile-menu-toggle {
                display: block !important;
            }

            .sidebar-nav a {
                padding: 16px 20px;
                font-size: 0.95rem;
            }

            .sidebar-nav .nav-section {
                padding: 12px 20px 8px;
                font-size: 0.7rem;
            }
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .status-active {
            background-color: #28a745;
        }

        .status-inactive {
            background-color: #dc3545;
        }

        .status-suspended {
            background-color: #ffc107;
        }

        .role-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .role-admin { background-color: #dc3545; color: white; }
        .role-hod { background-color: #fd7e14; color: white; }
        .role-lecturer { background-color: #20c997; color: white; }
        .role-student { background-color: #6f42c1; color: white; }
    </style>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <h3><i class="fas fa-graduation-cap me-2"></i>RP System</h3>
            <small>Admin Panel</small>
        </div>

        <ul class="sidebar-nav">
            <li class="nav-section">
                <i class="fas fa-th-large me-2"></i>Main Dashboard
            </li>
            <li>
                <a href="admin-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>Dashboard Overview
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-users me-2"></i>User Management
            </li>
            <li>
                <a href="manage-users.php" class="<?php echo !$is_lecturer_registration ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>Manage Users
                </a>
            </li>
            <?php if ($is_lecturer_registration): ?>
            <li>
                <a href="manage-users.php?role=lecturer" class="active">
                    <i class="fas fa-chalkboard-teacher"></i>Register Lecturer
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="register-student.php">
                    <i class="fas fa-user-plus"></i>Register Student
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-sitemap me-2"></i>Organization
            </li>
            <li>
                <a href="manage-departments.php">
                    <i class="fas fa-building"></i>Departments
                </a>
            </li>
            <li>
                <a href="assign-hod.php">
                    <i class="fas fa-user-tie"></i>Assign HOD
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
            </li>
            <li>
                <a href="admin-reports.php">
                    <i class="fas fa-chart-line"></i>Analytics Reports
                </a>
            </li>
            <li>
                <a href="attendance-reports.php">
                    <i class="fas fa-calendar-check"></i>Attendance Reports
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-cog me-2"></i>System
            </li>
            <li>
                <a href="system-logs.php">
                    <i class="fas fa-file-code"></i>System Logs
                </a>
            </li>
            <li>
                <a href="hod-leave-management.php">
                    <i class="fas fa-clipboard-list"></i>Leave Management
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-sign-out-alt me-2"></i>Account
            </li>
            <li>
                <a href="logout.php" class="text-danger">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (!$is_lecturer_registration): ?>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="mb-1 text-primary">
                    <i class="fas fa-users me-3"></i>User Management
                </h2>
                <p class="text-muted mb-0">Manage user accounts, roles, and permissions</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-success" id="exportUsers" title="Export to CSV">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <button class="btn btn-outline-primary" id="refreshUsers" title="Refresh data">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="fas fa-plus me-1"></i>Create User
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center mb-4">
            <h2 class="mb-1 text-primary">
                <i class="fas fa-chalkboard-teacher me-3"></i>Lecturer Registration
            </h2>
            <p class="text-muted mb-0">Register new lecturers for the system</p>
        </div>
        <?php endif; ?>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center text-white">
                <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Loading Users</h5>
                <p class="mb-0">Please wait while we fetch user data...</p>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertBox" class="mb-4"></div>

        <?php if (!$is_lecturer_registration): ?>
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-users text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mt-3 mb-1" id="totalUsers"><?php echo $stats['total']; ?></h3>
                        <p class="text-muted mb-0">Total Users</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-user-check text-success" style="font-size: 2rem;"></i>
                        <h3 class="mt-3 mb-1" id="activeUsers"><?php echo $stats['active']; ?></h3>
                        <p class="text-muted mb-0">Active Users</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-user-times text-danger" style="font-size: 2rem;"></i>
                        <h3 class="mt-3 mb-1" id="inactiveUsers"><?php echo $stats['inactive']; ?></h3>
                        <p class="text-muted mb-0">Inactive Users</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card text-center">
                    <div class="card-body">
                        <i class="fas fa-percentage text-info" style="font-size: 2rem;"></i>
                        <h3 class="mt-3 mb-1" id="activePercentage">
                            <?php echo $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100, 1) : 0; ?>%
                        </h3>
                        <p class="text-muted mb-0">Active Rate</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search students...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="roleFilter">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="hod">HOD</option>
                            <option value="lecturer">Lecturer</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="departmentFilter">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="yearLevelFilter">
                            <option value="">All Years</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">BTech</option>

                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-outline-secondary w-100" id="clearFilters">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-2">
                        <select class="form-select" id="genderFilter">
                            <option value="">All Genders</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="ageFilter">
                            <option value="">All Ages</option>
                            <option value="16-18">16-18 years</option>
                            <option value="19-21">19-21 years</option>
                            <option value="22-25">22-25 years</option>
                            <option value="26+">26+ years</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control" id="regNoFilter" placeholder="Reg. Number">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="emailFilter" placeholder="Email Address">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" id="applyAdvancedFilters">
                            <i class="fas fa-search me-1"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="usersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Academic Info</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Users will be loaded here -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted" id="usersInfo">
                        Loading users...
                    </div>
                    <nav id="paginationNav">
                        <!-- Pagination will be generated here -->
                    </nav>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Lecturer Registration Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lecturer Information</h5>
            </div>
            <div class="card-body">
                <form id="lecturerRegistrationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="role" value="lecturer">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                            <small class="form-text text-muted">Password must be at least 8 characters long</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Employee ID *</label>
                            <input type="text" class="form-control" name="reference_id" required placeholder="Employee ID">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Gender *</label>
                            <select class="form-select" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" name="dob" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department *</label>
                            <select class="form-select" name="department_id" required>
                                <option value="">Select Department</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Education Level *</label>
                            <select class="form-select" name="education_level" required>
                                <option value="">Select Level</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Diploma">Diploma</option>
                                <option value="Bachelor">Bachelor</option>
                                <option value="Master">Master</option>
                                <option value="PhD">PhD</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Photo</label>
                            <input type="file" class="form-control" name="photo" accept="image/*">
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Register Lecturer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="createUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required minlength="8">
                                <small class="form-text text-muted">Password must be at least 8 characters long</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="hod">HOD</option>
                                    <option value="lecturer">Lecturer</option>
                                    <option value="student">Student</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference ID</label>
                                <input type="text" class="form-control" name="reference_id" placeholder="Student ID or Employee ID">
                            </div>
                            <!-- Lecturer-specific fields -->
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob">
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id">
                                    <option value="">Select Department</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Education Level</label>
                                <select class="form-select" name="education_level">
                                    <option value="">Select Level</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Bachelor">Bachelor</option>
                                    <option value="Master">Master</option>
                                    <option value="PhD">PhD</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Photo</label>
                                <input type="file" class="form-control" name="photo" accept="image/*">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm">
                    <input type="hidden" name="user_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="hod">HOD</option>
                                    <option value="lecturer">Lecturer</option>
                                    <option value="student">Student</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference ID</label>
                                <input type="text" class="form-control" name="reference_id" placeholder="Student ID or Employee ID">
                            </div>
                            <!-- Lecturer-specific fields -->
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob">
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id">
                                    <option value="">Select Department</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Education Level</label>
                                <select class="form-select" name="education_level">
                                    <option value="">Select Level</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Bachelor">Bachelor</option>
                                    <option value="Master">Master</option>
                                    <option value="PhD">PhD</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Photo</label>
                                <input type="file" class="form-control" name="photo" accept="image/*">
                                <small class="form-text text-muted">Leave empty to keep current photo</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="resetPasswordForm">
                    <input type="hidden" name="user_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You are about to reset this user's password. This action cannot be undone.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password *</label>
                            <input type="password" class="form-control" name="new_password" required minlength="8">
                            <small class="form-text text-muted">Password must be at least 8 characters long</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="8">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-1"></i>Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentUsers = [];
        let filteredUsers = [];

        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
            }
        });

        // Cross-page update listener
        window.addEventListener('storage', function(e) {
            if (e.key === 'user_role_changed' || e.key === 'department_hod_changed' || e.key === 'department_changed') {
                console.log('External change detected, refreshing user data...');
                loadUsers();
            }
        });

        function showAlert(type, message) {
            $("#alertBox").html(`
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
            setTimeout(() => $("#alertBox").html(""), 5000);
        }

        function showLoading() {
            $("#loadingOverlay").fadeIn();
        }

        function hideLoading() {
            $("#loadingOverlay").fadeOut();
        }

        function loadUsers() {
            showLoading();

            const search = $("#searchInput").val();
            const role = $("#roleFilter").val();
            const status = $("#statusFilter").val();
            const department = $("#departmentFilter").val();
            const yearLevel = $("#yearLevelFilter").val();
            const gender = $("#genderFilter").val();
            const age = $("#ageFilter").val();
            const regNo = $("#regNoFilter").val();
            const email = $("#emailFilter").val();

            $.ajax({
                url: 'manage-users.php',
                method: 'GET',
                data: {
                    ajax: '1',
                    action: 'get_users',
                    search: search,
                    role: role,
                    status: status,
                    department: department,
                    year_level: yearLevel,
                    gender: gender,
                    age: age,
                    reg_no: regNo,
                    email: email,
                    t: Date.now()
                },
                success: function(response) {
                    hideLoading();

                    if (response.status === 'success') {
                        currentUsers = response.data;
                        filteredUsers = [...currentUsers];
                        updateStats(response.stats);
                        renderUsersTable();
                    } else {
                        showAlert('danger', 'Failed to load users: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Error loading users:', error);
                    showAlert('danger', 'Failed to load users. Please try again.');
                }
            });
        }

        function loadDepartmentsForFilter() {
            $.ajax({
                url: 'manage-users.php',
                method: 'GET',
                data: { ajax: '1', action: 'get_departments' },
                success: function(response) {
                    if (response.status === 'success') {
                        const deptSelect = $('#departmentFilter');
                        deptSelect.empty().append('<option value="">All Departments</option>');

                        response.data.forEach(function(dept) {
                            deptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load departments for filter:', error);
                    showAlert('warning', 'Failed to load departments. Some filtering may not work properly.');
                }
            });
        }

        function loadDepartmentsForForms() {
            $.ajax({
                url: 'manage-users.php',
                method: 'GET',
                data: { ajax: '1', action: 'get_departments' },
                success: function(response) {
                    if (response.status === 'success') {
                        const createDeptSelect = $('#createUserForm [name="department_id"]');
                        const editDeptSelect = $('#editUserForm [name="department_id"]');
                        const lecturerDeptSelect = $('#lecturerRegistrationForm [name="department_id"]');

                        // Load departments for create user form
                        if (createDeptSelect.length) {
                            createDeptSelect.empty().append('<option value="">Select Department</option>');
                            response.data.forEach(function(dept) {
                                createDeptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
                            });
                        }

                        // Load departments for edit user form
                        if (editDeptSelect.length) {
                            editDeptSelect.empty().append('<option value="">Select Department</option>');
                            response.data.forEach(function(dept) {
                                editDeptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
                            });
                        }

                        // Load departments for lecturer registration form
                        if (lecturerDeptSelect.length) {
                            lecturerDeptSelect.empty().append('<option value="">Select Department</option>');
                            response.data.forEach(function(dept) {
                                lecturerDeptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
                            });
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Failed to load departments for forms:', error);
                }
            });
        }

        function toggleLecturerFields(formId) {
            const role = $(`#${formId} [name="role"]`).val();
            const lecturerFields = $(`#${formId} .lecturer-field`);
            if (role === 'lecturer') {
                lecturerFields.show().find('input, select').prop('required', true);
            } else {
                lecturerFields.hide().find('input, select').prop('required', false);
            }
        }

        function exportUsers() {
            const exportBtn = $("#exportUsers");
            const originalText = exportBtn.html();

            // Show loading state
            exportBtn.html('<i class="fas fa-spinner fa-spin me-1"></i>Exporting...').prop('disabled', true);

            try {
                // Get current filter values
                const search = $("#searchInput").val();
                const role = $("#roleFilter").val();
                const status = $("#statusFilter").val();
                const department = $("#departmentFilter").val();
                const yearLevel = $("#yearLevelFilter").val();
                const gender = $("#genderFilter").val();
                const age = $("#ageFilter").val();
                const regNo = $("#regNoFilter").val();
                const email = $("#emailFilter").val();

                // Build export URL
                let exportUrl = 'manage-users.php?ajax=1&action=export_users';
                const params = new URLSearchParams({
                    search: search,
                    role: role,
                    status: status,
                    department: department,
                    year_level: yearLevel,
                    gender: gender,
                    age: age,
                    reg_no: regNo,
                    email: email
                });

                exportUrl += '&' + params.toString();

                // Trigger download
                window.location.href = exportUrl;

                // Show success message after a short delay
                setTimeout(() => {
                    showAlert('success', 'User data export initiated. Download should start shortly.');
                }, 1000);

            } catch (error) {
                console.error('Export error:', error);
                showAlert('danger', 'Failed to export user data. Please try again.');
            } finally {
                // Restore button state
                setTimeout(() => {
                    exportBtn.html(originalText).prop('disabled', false);
                }, 2000);
            }
        }

        function updateStats(stats) {
            $("#totalUsers").text(stats.total);
            $("#activeUsers").text(stats.active);
            $("#inactiveUsers").text(stats.inactive);
            $("#activePercentage").text(stats.total > 0 ? Math.round((stats.active / stats.total) * 100) + '%' : '0%');
        }

        function renderUsersTable() {
            const tbody = $("#usersTableBody");
            const info = $("#usersInfo");

            if (filteredUsers.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="8" class="text-center py-4">
                            <i class="fas fa-users fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No users found</p>
                        </td>
                    </tr>
                `);
                info.text("No users found");
                return;
            }

            tbody.html(filteredUsers.map(user => `
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-3">
                                ${user.first_name ? user.first_name.charAt(0).toUpperCase() : 'U'}
                            </div>
                            <div>
                                <h6 class="mb-0">${escapeHtml(user.first_name || '')} ${escapeHtml(user.last_name || '')}</h6>
                                <small class="text-muted">@${escapeHtml(user.username)}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="role-badge role-${user.role}">${user.role}</span>
                    </td>
                    <td>
                        <div>
                            <small class="fw-semibold">${escapeHtml(user.department_name || 'Not Assigned')}</small>
                        </div>
                    </td>
                    <td>
                        <div>
                            ${user.year_level ? `<div><small class="text-muted">Year ${user.year_level}</small></div>` : ''}
                            ${user.gender ? `<div><small class="text-muted">${user.gender}</small></div>` : ''}
                            ${user.dob ? `<div><small class="text-muted">${getAge(user.dob)} years old</small></div>` : ''}
                        </div>
                    </td>
                    <td>
                        <div>
                            <i class="fas fa-envelope text-muted me-1"></i>${escapeHtml(user.email)}
                            ${user.phone ? `<br><i class="fas fa-phone text-muted me-1"></i>${escapeHtml(user.phone)}` : ''}
                        </div>
                    </td>
                    <td>
                        <span class="status-indicator status-${user.status || 'active'}"></span>
                        <span class="badge bg-${getStatusBadgeColor(user.status)}">
                            ${user.status || 'active'}
                        </span>
                    </td>
                    <td>
                        <small>${formatDate(user.created_at)}</small>
                    </td>
                    <td>
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(${user.id})" title="Edit User">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(${user.id})" title="Reset Password">
                                <i class="fas fa-key"></i>
                            </button>
                            <button class="btn btn-sm btn-${getStatusButtonClass(user.status)}"
                                    onclick="toggleUserStatus(${user.id}, '${user.status || 'active'}')"
                                    title="${getStatusButtonTitle(user.status)}">
                                <i class="fas fa-${getStatusButtonIcon(user.status)}"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join(''));

            info.text(`Showing ${filteredUsers.length} user${filteredUsers.length !== 1 ? 's' : ''}`);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function getStatusBadgeColor(status) {
            switch (status) {
                case 'active': return 'success';
                case 'inactive': return 'warning';
                case 'suspended': return 'danger';
                default: return 'secondary';
            }
        }

        function getStatusButtonClass(status) {
            switch (status) {
                case 'active': return 'outline-warning';
                case 'inactive': return 'outline-success';
                case 'suspended': return 'outline-primary';
                default: return 'outline-secondary';
            }
        }

        function getStatusButtonTitle(status) {
            switch (status) {
                case 'active': return 'Deactivate User';
                case 'inactive': return 'Activate User';
                case 'suspended': return 'Activate User';
                default: return 'Change Status';
            }
        }

        function getStatusButtonIcon(status) {
            switch (status) {
                case 'active': return 'times';
                case 'inactive': return 'check';
                case 'suspended': return 'check';
                default: return 'cog';
            }
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return 'Invalid Date';
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            } catch (e) {
                return 'Invalid Date';
            }
        }

        function getAge(dateString) {
            if (!dateString) return 'N/A';
            try {
                const birthDate = new Date(dateString);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();

                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }

                return age;
            } catch (e) {
                return 'N/A';
            }
        }

        function applyFilters() {
            const search = $("#searchInput").val().toLowerCase();
            const role = $("#roleFilter").val();
            const status = $("#statusFilter").val();
            const department = $("#departmentFilter").val();
            const yearLevel = $("#yearLevelFilter").val();
            const gender = $("#genderFilter").val();
            const age = $("#ageFilter").val();
            const regNo = $("#regNoFilter").val().toLowerCase();
            const email = $("#emailFilter").val().toLowerCase();

            filteredUsers = currentUsers.filter(user => {
                const fullName = `${user.first_name || ''} ${user.last_name || ''}`.toLowerCase();
                const matchesSearch = !search ||
                    (user.username && user.username.toLowerCase().includes(search)) ||
                    (user.email && user.email.toLowerCase().includes(search)) ||
                    fullName.includes(search);

                const matchesRole = !role || user.role === role;
                const matchesStatus = !status || user.status === status;
                const matchesDepartment = !department || user.department_name === department;
                const matchesYearLevel = !yearLevel || (user.year_level && user.year_level.toString() === yearLevel);
                const matchesGender = !gender || (user.gender && user.gender === gender);
                const matchesRegNo = !regNo || (user.reference_id && user.reference_id.toLowerCase().includes(regNo));
                const matchesEmail = !email || (user.email && user.email.toLowerCase().includes(email));

                // Age filtering logic
                let matchesAge = true;
                if (age && user.dob) {
                    const birthDate = new Date(user.dob);
                    const today = new Date();
                    const userAge = today.getFullYear() - birthDate.getFullYear();

                    switch(age) {
                        case '16-18':
                            matchesAge = userAge >= 16 && userAge <= 18;
                            break;
                        case '19-21':
                            matchesAge = userAge >= 19 && userAge <= 21;
                            break;
                        case '22-25':
                            matchesAge = userAge >= 22 && userAge <= 25;
                            break;
                        case '26+':
                            matchesAge = userAge >= 26;
                            break;
                        default:
                            matchesAge = true;
                    }
                }

                return matchesSearch && matchesRole && matchesStatus && matchesDepartment &&
                       matchesYearLevel && matchesGender && matchesAge && matchesRegNo && matchesEmail;
            });

            renderUsersTable();
        }

        function editUser(userId) {
            const user = currentUsers.find(u => u.id === userId);
            if (!user) {
                showAlert('danger', 'User not found');
                return;
            }

            // Populate form
            $("#editUserForm [name='user_id']").val(user.id);
            $("#editUserForm [name='username']").val(user.username || '');
            $("#editUserForm [name='email']").val(user.email || '');
            $("#editUserForm [name='role']").val(user.role || '');
            $("#editUserForm [name='status']").val(user.status || 'active');
            $("#editUserForm [name='first_name']").val(user.first_name || '');
            $("#editUserForm [name='last_name']").val(user.last_name || '');
            $("#editUserForm [name='phone']").val(user.phone || '');
            $("#editUserForm [name='reference_id']").val(user.reference_id || '');
            $("#editUserForm [name='gender']").val(user.gender || '');
            $("#editUserForm [name='dob']").val(user.dob || '');
            $("#editUserForm [name='department_id']").val(user.department_id || '');
            $("#editUserForm [name='education_level']").val(user.education_level || '');

            toggleLecturerFields('editUserForm');

            $("#editUserModal").modal('show');
        }

        function resetPassword(userId) {
            $("#resetPasswordForm [name='user_id']").val(userId);
            $("#resetPasswordModal").modal('show');
        }

        function toggleUserStatus(userId, currentStatus) {
            const statusOptions = ['active', 'inactive', 'suspended'];
            const currentIndex = statusOptions.indexOf(currentStatus);
            const nextStatus = statusOptions[(currentIndex + 1) % statusOptions.length];

            const statusLabels = {
                'active': 'deactivate',
                'inactive': 'activate',
                'suspended': 'activate'
            };

            if (!confirm(`Are you sure you want to ${statusLabels[nextStatus]} this user?`)) {
                return;
            }

            $.ajax({
                url: 'manage-users.php',
                method: 'POST',
                data: {
                    ajax: '1',
                    action: 'toggle_status',
                    user_id: userId,
                    status: nextStatus,
                    csrf_token: "<?php echo generate_csrf_token(); ?>"
                },
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert('success', response.message);
                        loadUsers();
                        // Trigger cross-page update for department management
                        triggerCrossPageUpdate('user_status_changed', { timestamp: Date.now() });
                    } else {
                        showAlert('danger', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error toggling status:', error);
                    showAlert('danger', 'Failed to update user status');
                }
            });
        }

        function triggerCrossPageUpdate(eventType, data) {
            try {
                localStorage.setItem(eventType, JSON.stringify(data));
                // Immediately remove to trigger storage event in other tabs
                setTimeout(() => localStorage.removeItem(eventType), 100);
            } catch (e) {
                console.warn('Cross-page update failed:', e);
            }
        }

        // Event handlers
        $(document).ready(function() {
            // Handle URL parameters for pre-filtering
            const urlParams = new URLSearchParams(window.location.search);
            const preselectedRole = urlParams.get('role');

            if (preselectedRole && ['admin', 'hod', 'lecturer', 'student'].includes(preselectedRole)) {
                $("#roleFilter").val(preselectedRole);
                // Update page title and heading
                if (preselectedRole === 'lecturer') {
                    $("#mainContent h2").html('<i class="fas fa-chalkboard-teacher me-3"></i>Lecturer Registration');
                    $("#mainContent p").text('Register new lecturers for the system');
                    $("#createUserModal .modal-title").text('Register New Lecturer');
                    $("#createUserForm [name='role']").val('lecturer');
                    $("#createUserForm [name='role']").prop('disabled', true);
                    toggleLecturerFields('createUserForm');
                }
            }

            // Load users and departments immediately (only for management mode)
            if (!<?php echo $is_lecturer_registration ? 'true' : 'false'; ?>) {
                loadUsers();
                loadDepartmentsForFilter();
            }
            loadDepartmentsForForms();

            // Role change handlers for lecturer fields
            $('#createUserForm [name="role"]').on('change', function() {
                toggleLecturerFields('createUserForm');
            });
            $('#editUserForm [name="role"]').on('change', function() {
                toggleLecturerFields('editUserForm');
            });

            // Search and filter events
            $("#searchInput, #roleFilter, #statusFilter, #departmentFilter, #yearLevelFilter, #genderFilter, #ageFilter, #regNoFilter, #emailFilter").on('input change', function() {
                applyFilters();
            });

            $("#clearFilters").click(function() {
                $("#searchInput").val('');
                $("#roleFilter").val('');
                $("#statusFilter").val('');
                $("#departmentFilter").val('');
                $("#yearLevelFilter").val('');
                $("#genderFilter").val('');
                $("#ageFilter").val('');
                $("#regNoFilter").val('');
                $("#emailFilter").val('');
                applyFilters();
            });

            $("#applyAdvancedFilters").click(function() {
                applyFilters();
            });

            // Refresh button
            $("#refreshUsers").click(function() {
                loadUsers();
            });

            // Export button
            $("#exportUsers").click(function() {
                exportUsers();
            });

            // Create user form
            $("#createUserForm").submit(function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('ajax', '1');
                formData.append('action', 'create_user');

                $.ajax({
                    url: 'manage-users.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.status === 'success') {
                            $("#createUserModal").modal('hide');
                            $("#createUserForm")[0].reset();
                            showAlert('success', response.message);
                            loadUsers();
                            // Trigger cross-page update for department management
                            triggerCrossPageUpdate('user_changed', { timestamp: Date.now() });
                        } else {
                            showAlert('danger', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error creating user:', error);
                        showAlert('danger', 'Failed to create user');
                    }
                });
            });

            // Edit user form
            $("#editUserForm").submit(function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('ajax', '1');
                formData.append('action', 'update_user');

                $.ajax({
                    url: 'manage-users.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.status === 'success') {
                            $("#editUserModal").modal('hide');
                            showAlert('success', response.message);
                            loadUsers();
                            // Trigger cross-page update for department management
                            triggerCrossPageUpdate('user_changed', { timestamp: Date.now() });
                        } else {
                            showAlert('danger', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error updating user:', error);
                        showAlert('danger', 'Failed to update user');
                    }
                });
            });

            // Reset password form
            $("#resetPasswordForm").submit(function(e) {
                e.preventDefault();
                const newPassword = $(this).find('[name="new_password"]').val();
                const confirmPassword = $(this).find('[name="confirm_password"]').val();

                if (newPassword !== confirmPassword) {
                    showAlert('danger', 'Passwords do not match');
                    return;
                }

                const formData = new FormData(this);
                formData.append('ajax', '1');
                formData.append('action', 'reset_password');

                $.ajax({
                    url: 'manage-users.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.status === 'success') {
                            $("#resetPasswordModal").modal('hide');
                            $("#resetPasswordForm")[0].reset();
                            showAlert('success', response.message);
                            // Trigger cross-page update for department management
                            triggerCrossPageUpdate('user_changed', { timestamp: Date.now() });
                        } else {
                            showAlert('danger', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error resetting password:', error);
                        showAlert('danger', 'Failed to reset password');
                    }
                });
            });

            // Lecturer registration form (for dedicated lecturer registration page)
            $("#lecturerRegistrationForm").submit(function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('ajax', '1');
                formData.append('action', 'create_user');

                $.ajax({
                    url: 'manage-users.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.status === 'success') {
                            $("#lecturerRegistrationForm")[0].reset();
                            showAlert('success', 'Lecturer registered successfully! Default password is the employee ID.');
                            // Redirect back to manage users after successful registration
                            setTimeout(() => {
                                window.location.href = 'manage-users.php';
                            }, 2000);
                        } else {
                            showAlert('danger', response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error registering lecturer:', error);
                        showAlert('danger', 'Failed to register lecturer');
                    }
                });
            });

            // Auto-refresh every 2 minutes
            setInterval(loadUsers, 120000);
        });
    </script>
</body>
</html>