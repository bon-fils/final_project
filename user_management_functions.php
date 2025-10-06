<?php
/**
 * User Management Functions
 * Utility functions for user management operations
 *
 * @version 1.0.0
 * @author Rwanda Polytechnic Development Team
 * @since 2024
 */

require_once "config.php";
require_once "session_check.php";

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
// function sanitize_input($input) {
//     return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
// }

/**
 * Get all users with their details - Optimized Query
 */
function getAllUsers($search = '', $role_filter = '', $status_filter = '', $department_filter = '', $year_level_filter = '', $gender_filter = '', $age_filter = '', $reg_no_filter = '', $email_filter = '') {
    global $pdo;

    try {
        // Optimized query with normalized schema joins
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
                u.first_name,
                u.last_name,
                u.phone,
                u.sex as gender,
                u.photo,
                u.dob as dob,
                CASE
                    WHEN u.role = 'student' THEN s.reg_no
                    WHEN u.role IN ('lecturer', 'hod') THEN l.id_number
                    ELSE NULL
                END as reference_id,
                s.year_level,
                l.education_level,
                CASE
                    WHEN u.role = 'student' THEN d.name
                    WHEN u.role IN ('lecturer', 'hod') THEN l_dept.name
                    ELSE NULL
                END as department_name,
                CASE
                    WHEN u.role = 'student' THEN o.id
                    WHEN u.role IN ('lecturer', 'hod') THEN l.department_id
                    ELSE NULL
                END as department_id,
                CASE
                    WHEN u.role = 'student' THEN o.name
                    ELSE NULL
                END as option_name
            FROM users u
            LEFT JOIN students s ON u.id = s.user_id AND u.role = 'student'
            LEFT JOIN lecturers l ON u.id = l.user_id AND u.role IN ('lecturer', 'hod')
            LEFT JOIN options o ON s.option_id = o.id
            LEFT JOIN departments d ON o.department_id = d.id
            LEFT JOIN departments l_dept ON l.department_id = l_dept.id
            WHERE 1=1
        ";

        $conditions = [];
        $params = [];

        if (!empty($search)) {
            $conditions[] = "(u.username LIKE ? OR u.email LIKE ? OR CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) LIKE ?)";
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
                $conditions[] = "(l.department_id = ? OR o.department_id = ?)";
                $params[] = $department_filter;
                $params[] = $department_filter;
            } else {
                $conditions[] = "o.department_id = ?";
                $params[] = $department_filter;
            }
        }

        if (!empty($year_level_filter)) {
            $conditions[] = "s.year_level = ?";
            $params[] = $year_level_filter;
        }

        if (!empty($gender_filter)) {
            $conditions[] = "u.gender = ?";
            $params[] = $gender_filter;
        }

        if (!empty($age_filter)) {
            $age_ranges = explode('-', $age_filter);
            if (count($age_ranges) === 2) {
                $min_age = (int)$age_ranges[0];
                $max_age = (int)$age_ranges[1];
                $conditions[] = "TIMESTAMPDIFF(YEAR, COALESCE(s.dob, l.dob, u.date_of_birth), CURDATE()) BETWEEN ? AND ?";
                $params[] = $min_age;
                $params[] = $max_age;
            } elseif ($age_filter === '26+') {
                $conditions[] = "TIMESTAMPDIFF(YEAR, COALESCE(s.dob, l.dob, u.date_of_birth), CURDATE()) >= ?";
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
function createUser($username, $email, $password, $role, $first_name, $last_name, $phone = null, $reference_id = null, $gender = null, $dob = null, $department_id = null, $education_level = null, $photo = null, $year_level = null, $reg_no = null) {
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

        // Insert user with personal information (normalized schema)
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, first_name, last_name, phone, gender, photo, date_of_birth, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $stmt->execute([$username, $email, $hashed_password, $role, $first_name, $last_name, $phone, $gender, $photo, $dob]);

        $user_id = $pdo->lastInsertId();

        // Insert role-specific data with user_id FK (normalized schema)
        switch ($role) {
            case 'student':
                if (empty($reference_id)) {
                    throw new Exception('Program/Option is required for students');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO students (user_id, option_id, year_level, reg_no)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $reference_id, $year_level ?? '1', $reg_no ?? $reference_id]);
                break;

            case 'lecturer':
            case 'hod':
                if (empty($reference_id)) {
                    throw new Exception('Employee ID is required for lecturers');
                }
                if (empty($department_id)) {
                    throw new Exception('Department is required for lecturers');
                }
                if (empty($gender)) {
                    throw new Exception('Gender is required for lecturers');
                }
                if (empty($dob)) {
                    throw new Exception('Date of birth is required for lecturers');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO lecturers (user_id, id_number, department_id, education_level, role, gender, dob)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$user_id, $reference_id, $department_id, $education_level, $role, $gender, $dob]);
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
function updateUser($user_id, $username, $email, $role, $first_name, $last_name, $phone = null, $reference_id = null, $status = 'active', $gender = null, $dob = null, $department_id = null, $education_level = null, $photo = null, $year_level = null) {
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

        // Handle photo upload
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
            $stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            $photo_path = $existing['photo'] ?? null;
        }

        // Start transaction
        $pdo->beginTransaction();

        // Update user
        $updateFields = "username = ?, email = ?, role = ?, status = ?, updated_at = NOW()";
        $params = [$username, $email, $role, $status];

        if ($photo_path !== null) {
            $updateFields .= ", photo = ?";
            $params[] = $photo_path;
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET {$updateFields}
            WHERE id = ?
        ");
        $params[] = $user_id;
        $stmt->execute($params);


        // Update role-specific data using user_id
        switch ($role) {
            case 'student':
                $stmt = $pdo->prepare("
                    UPDATE students
                    SET reg_no = ?, year_level = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$reference_id, $year_level ?? '1', $user_id]);
                break;

            case 'lecturer':
            case 'hod':
                $stmt = $pdo->prepare("
                    UPDATE lecturers
                    SET id_number = ?, role = ?, gender = ?, dob = ?, department_id = ?, education_level = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute([$reference_id, $role, $gender, $dob, $department_id, $education_level, $user_id]);
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

        $hashed_password = password_hash($new_password, PASSWORD_ARGON2ID);

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
?>