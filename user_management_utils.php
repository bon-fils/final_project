<?php
/**
 * User Management Utilities
 * Contains all user-related functions for the user management system
 */

require_once "config.php";

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
 * Sanitize input data
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate username format
 */
function validate_username($username) {
    // Username should be 3-50 characters, alphanumeric, underscore, dash
    return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username);
}

/**
 * Validate phone number format
 */
function validate_phone($phone) {
    if (empty($phone)) return true; // Optional field
    // Basic phone validation - allow international format
    return preg_match('/^\+?[\d\s\-\(\)]{7,20}$/', $phone);
}

/**
 * Validate name format
 */
function validate_name($name) {
    // Names should be 1-100 characters, letters, spaces, hyphens, apostrophes
    return preg_match("/^[a-zA-Z\s\-']{1,100}$/", $name) && strlen(trim($name)) > 0;
}

/**
 * Rate limiting check
 */
function check_rate_limit($action, $limit = 10, $window = 60) {
    $key = session_id() . '_' . $action;
    $now = time();

    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'reset' => $now + $window];
        return true;
    }

    if ($now > $_SESSION['rate_limit'][$key]['reset']) {
        $_SESSION['rate_limit'][$key] = ['count' => 1, 'reset' => $now + $window];
        return true;
    }

    if ($_SESSION['rate_limit'][$key]['count'] >= $limit) {
        return false;
    }

    $_SESSION['rate_limit'][$key]['count']++;
    return true;
}

/**
 * Enhanced input validation for user creation/update
 */
function validate_user_input($data, $is_update = false) {
    $errors = [];

    // Required fields
    $required = ['username', 'email', 'role', 'first_name', 'last_name'];
    if (!$is_update) {
        $required[] = 'password';
    }

    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    // Username validation
    if (!empty($data['username']) && !validate_username($data['username'])) {
        $errors[] = 'Username must be 3-50 characters long and contain only letters, numbers, underscores, and dashes';
    }

    // Email validation
    if (!empty($data['email']) && !validate_email($data['email'])) {
        $errors[] = 'Invalid email format';
    }

    // Role validation
    if (!empty($data['role']) && !validate_role($data['role'])) {
        $errors[] = 'Invalid role specified';
    }

    // Name validation
    if (!empty($data['first_name']) && !validate_name($data['first_name'])) {
        $errors[] = 'First name contains invalid characters';
    }
    if (!empty($data['last_name']) && !validate_name($data['last_name'])) {
        $errors[] = 'Last name contains invalid characters';
    }

    // Phone validation
    if (!empty($data['phone']) && !validate_phone($data['phone'])) {
        $errors[] = 'Invalid phone number format';
    }

    // Password validation
    if (!$is_update && !empty($data['password']) && strlen($data['password']) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }

    // Status validation
    if ($is_update && !empty($data['status']) && !validate_status($data['status'])) {
        $errors[] = 'Invalid status specified';
    }

    return $errors;
}

/**
 * Get all users with their details
 * Optimized with pagination support
 *
 * Recommended indexes for performance:
 * - CREATE INDEX idx_users_role_status ON users(role, status);
 * - CREATE INDEX idx_users_email ON users(email);
 * - CREATE INDEX idx_users_created_at ON users(created_at DESC);
 * - CREATE INDEX idx_students_email ON students(email);
 * - CREATE INDEX idx_lecturers_email ON lecturers(email);
 */
function getAllUsers($search = '', $role_filter = '', $status_filter = '', $limit = null, $offset = 0) {
    global $pdo;

    try {
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
                CASE
                    WHEN u.role = 'student' THEN s.first_name
                    WHEN u.role = 'lecturer' THEN l.first_name
                    WHEN u.role = 'hod' THEN l.first_name
                    ELSE 'System'
                END as first_name,
                CASE
                    WHEN u.role = 'student' THEN s.last_name
                    WHEN u.role = 'lecturer' THEN l.last_name
                    WHEN u.role = 'hod' THEN l.last_name
                    ELSE 'User'
                END as last_name,
                CASE
                    WHEN u.role = 'student' THEN s.reg_no
                    WHEN u.role = 'lecturer' THEN l.id_number
                    WHEN u.role = 'hod' THEN l.id_number
                    ELSE NULL
                END as reference_id,
                CASE
                    WHEN u.role = 'student' THEN s.telephone
                    WHEN u.role = 'lecturer' THEN l.phone
                    WHEN u.role = 'hod' THEN l.phone
                    ELSE NULL
                END as phone
            FROM users u
            LEFT JOIN students s ON u.email = s.email AND u.role = 'student'
            LEFT JOIN lecturers l ON u.email = l.email AND u.role IN ('lecturer', 'hod')
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

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY u.created_at DESC";

        // Add pagination
        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $params[] = (int)$limit;
            $params[] = (int)$offset;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total user count for pagination
 */
function getTotalUserCount($search = '', $role_filter = '', $status_filter = '') {
    global $pdo;

    try {
        $sql = "
            SELECT COUNT(*) as total
            FROM users u
            LEFT JOIN students s ON u.email = s.email AND u.role = 'student'
            LEFT JOIN lecturers l ON u.email = l.email AND u.role IN ('lecturer', 'hod')
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

        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['total'];

    } catch (PDOException $e) {
        error_log("Error getting user count: " . $e->getMessage());
        return 0;
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
        $active = array_sum(array_column(array_filter($roleStats, function($s) { return $s['status'] === 'active'; }), 'count'));
        $inactive = array_sum(array_column(array_filter($roleStats, function($s) { return $s['status'] === 'inactive'; }), 'count'));
        $suspended = array_sum(array_column(array_filter($roleStats, function($s) { return $s['status'] === 'suspended'; }), 'count'));

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
 * Create new user
 */
function createUser($username, $email, $password, $role, $first_name, $last_name, $phone = null, $reference_id = null) {
    global $pdo;

    try {
        // Enhanced validation
        $validation_data = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone
        ];

        $errors = validate_user_input($validation_data, false);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }

        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);

        if ($stmt->fetch()) {
            throw new Exception('Username or email already exists');
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Start transaction
        $pdo->beginTransaction();

        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([$username, $email, $hashed_password, $role]);

        $user_id = $pdo->lastInsertId();

        // Insert role-specific data
        switch ($role) {
            case 'student':
                $stmt = $pdo->prepare("
                    INSERT INTO students (first_name, last_name, email, telephone, reg_no, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $reference_id]);
                break;

            case 'lecturer':
            case 'hod':
                $stmt = $pdo->prepare("
                    INSERT INTO lecturers (first_name, last_name, email, phone, id_number, role, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$first_name, $last_name, $email, $phone, $reference_id, $role]);
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
function updateUser($user_id, $username, $email, $role, $first_name, $last_name, $phone = null, $reference_id = null, $status = 'active') {
    global $pdo;

    try {
        // Enhanced validation
        $validation_data = [
            'username' => $username,
            'email' => $email,
            'role' => $role,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'phone' => $phone,
            'status' => $status
        ];

        $errors = validate_user_input($validation_data, true);
        if (!empty($errors)) {
            throw new Exception(implode(', ', $errors));
        }

        if (empty($user_id)) {
            throw new Exception('User ID is required');
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

        // Update role-specific data
        switch ($role) {
            case 'student':
                $stmt = $pdo->prepare("
                    UPDATE students
                    SET first_name = ?, last_name = ?, telephone = ?, reg_no = ?, updated_at = NOW()
                    WHERE email = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $reference_id, $email]);
                break;

            case 'lecturer':
            case 'hod':
                $stmt = $pdo->prepare("
                    UPDATE lecturers
                    SET first_name = ?, last_name = ?, phone = ?, id_number = ?, role = ?, updated_at = NOW()
                    WHERE email = ?
                ");
                $stmt->execute([$first_name, $last_name, $phone, $reference_id, $role, $email]);
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
 * Delete user (soft delete by setting status to deleted or actually delete)
 */
function deleteUser($user_id) {
    global $pdo;

    try {
        if (empty($user_id)) {
            throw new Exception('User ID is required');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Get user details first
        $stmt = $pdo->prepare("SELECT role, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('User not found');
        }

        // Delete from role-specific table first
        switch ($user['role']) {
            case 'student':
                $stmt = $pdo->prepare("DELETE FROM students WHERE email = ?");
                $stmt->execute([$user['email']]);
                break;
            case 'lecturer':
            case 'hod':
                $stmt = $pdo->prepare("DELETE FROM lecturers WHERE email = ?");
                $stmt->execute([$user['email']]);
                break;
        }

        // Delete from users table
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        $pdo->commit();
        return $stmt->rowCount() > 0;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting user: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Get user by ID
 */
function getUserById($user_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.username,
                u.email,
                u.role,
                u.status,
                u.created_at,
                u.updated_at,
                u.last_login,
                CASE
                    WHEN u.role = 'student' THEN s.first_name
                    WHEN u.role = 'lecturer' THEN l.first_name
                    WHEN u.role = 'hod' THEN l.first_name
                    ELSE 'System'
                END as first_name,
                CASE
                    WHEN u.role = 'student' THEN s.last_name
                    WHEN u.role = 'lecturer' THEN l.last_name
                    WHEN u.role = 'hod' THEN l.last_name
                    ELSE 'User'
                END as last_name,
                CASE
                    WHEN u.role = 'student' THEN s.reg_no
                    WHEN u.role = 'lecturer' THEN l.id_number
                    WHEN u.role = 'hod' THEN l.id_number
                    ELSE NULL
                END as reference_id,
                CASE
                    WHEN u.role = 'student' THEN s.telephone
                    WHEN u.role = 'lecturer' THEN l.phone
                    WHEN u.role = 'hod' THEN l.phone
                    ELSE NULL
                END as phone
            FROM users u
            LEFT JOIN students s ON u.email = s.email AND u.role = 'student'
            LEFT JOIN lecturers l ON u.email = l.email AND u.role IN ('lecturer', 'hod')
            WHERE u.id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}