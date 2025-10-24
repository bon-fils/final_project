<?php
/**
 * HOD Authentication Helper Functions
 * Provides authentication and authorization functions for HOD users
 */

/**
 * Verify HOD access and get department information
 * @param PDO $pdo Database connection
 * @param int $user_id User ID from session
 * @return array Result array with success status and data
 */
function verifyHODAccess($pdo, $user_id) {
    try {
        // Check if user exists and get basic info
        $stmt = $pdo->prepare("
            SELECT u.id, u.username, u.email, u.first_name, u.last_name, u.role
            FROM users u 
            WHERE u.id = ? AND u.status = 'active'
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [
                'success' => false,
                'error_message' => 'User not found or inactive',
                'error_code' => 'USER_NOT_FOUND'
            ];
        }
        
        // Check if user has HOD role
        if ($user['role'] !== 'hod') {
            return [
                'success' => false,
                'error_message' => 'Access denied. HOD role required.',
                'error_code' => 'INSUFFICIENT_PRIVILEGES'
            ];
        }
        
        // Get lecturer record for HOD
        $stmt = $pdo->prepare("
            SELECT l.id, l.department_id, d.name as department_name, d.status as department_status
            FROM lecturers l
            JOIN departments d ON l.department_id = d.id
            WHERE l.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $lecturer_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$lecturer_info) {
            return [
                'success' => false,
                'error_message' => 'No department assignment found. Please contact administrator.',
                'error_code' => 'NO_DEPARTMENT_ASSIGNMENT'
            ];
        }
        
        // Check if department is active
        if ($lecturer_info['department_status'] !== 'active') {
            return [
                'success' => false,
                'error_message' => 'Department is inactive. Please contact administrator.',
                'error_code' => 'DEPARTMENT_INACTIVE'
            ];
        }
        
        // Verify HOD is assigned to this department
        $stmt = $pdo->prepare("
            SELECT id FROM departments 
            WHERE id = ? AND hod_id = ?
        ");
        $stmt->execute([$lecturer_info['department_id'], $lecturer_info['id']]);
        $hod_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$hod_assignment) {
            return [
                'success' => false,
                'error_message' => 'You are not assigned as HOD for this department.',
                'error_code' => 'NOT_DEPARTMENT_HOD'
            ];
        }
        
        // Success - return all relevant data
        return [
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role']
            ],
            'lecturer_id' => $lecturer_info['id'],
            'department_id' => $lecturer_info['department_id'],
            'department_name' => $lecturer_info['department_name']
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in verifyHODAccess: " . $e->getMessage());
        return [
            'success' => false,
            'error_message' => 'Database error occurred. Please try again.',
            'error_code' => 'DATABASE_ERROR'
        ];
    } catch (Exception $e) {
        error_log("Error in verifyHODAccess: " . $e->getMessage());
        return [
            'success' => false,
            'error_message' => 'An unexpected error occurred. Please try again.',
            'error_code' => 'GENERAL_ERROR'
        ];
    }
}

/**
 * Check if user has HOD permissions for a specific department
 * @param PDO $pdo Database connection
 * @param int $user_id User ID
 * @param int $department_id Department ID to check
 * @return bool True if user is HOD of the department
 */
function isHODOfDepartment($pdo, $user_id, $department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT d.id 
            FROM departments d
            JOIN lecturers l ON d.hod_id = l.id
            WHERE d.id = ? AND l.user_id = ? AND d.status = 'active'
        ");
        $stmt->execute([$department_id, $user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Exception $e) {
        error_log("Error in isHODOfDepartment: " . $e->getMessage());
        return false;
    }
}

/**
 * Get HOD's department statistics
 * @param PDO $pdo Database connection
 * @param int $department_id Department ID
 * @return array Department statistics
 */
function getHODDepartmentStats($pdo, $department_id) {
    try {
        $stats = [];
        
        // Get total lecturers in department
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_lecturers
            FROM lecturers l
            JOIN users u ON l.user_id = u.id
            WHERE l.department_id = ? AND u.status = 'active'
        ");
        $stmt->execute([$department_id]);
        $stats['total_lecturers'] = $stmt->fetchColumn();
        
        // Get total students in department
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_students
            FROM students s
            JOIN users u ON s.user_id = u.id
            WHERE s.department_id = ? AND u.status = 'active'
        ");
        $stmt->execute([$department_id]);
        $stats['total_students'] = $stmt->fetchColumn();
        
        // Get total courses in department
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_courses
            FROM courses
            WHERE department_id = ? AND status = 'active'
        ");
        $stmt->execute([$department_id]);
        $stats['total_courses'] = $stmt->fetchColumn();
        
        // Get total options in department
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_options
            FROM options
            WHERE department_id = ? AND status = 'active'
        ");
        $stmt->execute([$department_id]);
        $stats['total_options'] = $stmt->fetchColumn();
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error in getHODDepartmentStats: " . $e->getMessage());
        return [
            'total_lecturers' => 0,
            'total_students' => 0,
            'total_courses' => 0,
            'total_options' => 0
        ];
    }
}
?>
