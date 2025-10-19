<?php
/**
 * Access Control Functions
 * Manages role-based access control for different user types
 */

/**
 * Check if a student has access to a specific feature
 * @param string $feature The feature to check access for
 * @param array $student Student data array
 * @return bool True if access is granted, false otherwise
 */
function hasStudentAccess($feature, $student) {
    // Default access rules for students
    $defaultAccess = [
        'attendance_records' => true,
        'leave_request' => true,
        'leave_status' => true,
        'my_courses' => true,
        'academic_calendar' => true,
        'library_portal' => true,
        'fee_payments' => true,
        'career_portal' => true,
    ];

    // Check if student is registered (has valid student record)
    $isRegistered = isset($student['id']) && $student['id'] > 0;

    // Special rules for unregistered students
    if (!$isRegistered) {
        $restrictedAccess = [
            'fee_payments' => false,       // No fee payments if not registered
        ];

        if (isset($restrictedAccess[$feature])) {
            return $restrictedAccess[$feature];
        }
    }

    // Check for feature-specific access rules
    switch ($feature) {
        case 'attendance_records':
            // Students can view their own attendance records (even if not fully registered)
            return true;

        case 'leave_request':
            // Students can request leave (even if not fully registered)
            return true;

        case 'leave_status':
            // Students can view leave status (even if not fully registered)
            return true;

        case 'my_courses':
            // All registered students can view their courses
            return $isRegistered;

        case 'academic_calendar':
            // Academic calendar is available to all students
            return true;

        case 'library_portal':
            // Library access requires registration
            return $isRegistered;

        case 'fee_payments':
            // Fee payments require registration
            return $isRegistered;

        case 'career_portal':
            // Career portal is available to all students
            return true;

        default:
            // Default to allowed if not specified
            return $defaultAccess[$feature] ?? false;
    }
}

/**
 * Check if a lecturer has access to a specific feature
 * @param string $feature The feature to check access for
 * @param array $lecturer Lecturer data array
 * @return bool True if access is granted, false otherwise
 */
function hasLecturerAccess($feature, $lecturer) {
    // Default access rules for lecturers
    $defaultAccess = [
        'dashboard_overview' => true,
        'my_courses' => true,
        'attendance_session' => true,
        'attendance_reports' => true,
        'leave_requests' => true,
    ];

    // Check if lecturer is properly assigned
    $isAssigned = isset($lecturer['department_id']) && $lecturer['department_id'] > 0;

    // Special rules for unassigned lecturers
    if (!$isAssigned) {
        $restrictedAccess = [
            'attendance_session' => false,  // Cannot start sessions if not assigned
            'attendance_reports' => false,  // No reports if not assigned
            'leave_requests' => false,      // No leave management if not assigned
        ];

        if (isset($restrictedAccess[$feature])) {
            return $restrictedAccess[$feature];
        }
    }

    // Check for feature-specific access rules
    switch ($feature) {
        case 'dashboard_overview':
            // All lecturers can access dashboard
            return true;

        case 'my_courses':
            // Lecturers can view courses if assigned to department
            return $isAssigned;

        case 'attendance_session':
            // Lecturers can start sessions if assigned to department
            return $isAssigned;

        case 'attendance_reports':
            // Lecturers can view reports if assigned to department
            return $isAssigned;

        case 'leave_requests':
            // Lecturers can manage leave requests if assigned to department
            return $isAssigned;

        default:
            // Default to allowed if not specified
            return $defaultAccess[$feature] ?? false;
    }
}

/**
 * Check if an admin has access to a specific feature
 * @param string $feature The feature to check access for
 * @param array $admin Admin data array
 * @return bool True if access is granted, false otherwise
 */
function hasAdminAccess($feature, $admin) {
    // Admins have access to all features by default
    $adminAccess = [
        'dashboard_overview' => true,
        'user_management' => true,
        'register_student' => true,
        'register_lecturer' => true,
        'manage_users' => true,
        'view_users' => true,
        'departments' => true,
        'assign_hod' => true,
        'analytics_reports' => true,
        'attendance_reports' => true,
        'system_logs' => true,
        'leave_management' => true,
    ];

    return $adminAccess[$feature] ?? false;
}

/**
 * Check if a HOD has access to a specific feature
 * @param string $feature The feature to check access for
 * @param array $hod HOD data array
 * @return bool True if access is granted, false otherwise
 */
function hasHodAccess($feature, $hod) {
    // Default access rules for HODs
    $hodAccess = [
        'dashboard_overview' => true,
        'department_reports' => true,
        'manage_lecturers' => true,
        'leave_management' => true,
        'attendance_reports' => true,
    ];

    // Check if HOD is properly assigned
    $isAssigned = isset($hod['department_id']) && $hod['department_id'] > 0;

    // HODs need to be assigned to a department for most features
    if (!$isAssigned) {
        return $feature === 'dashboard_overview'; // Only basic dashboard access
    }

    return $hodAccess[$feature] ?? false;
}

/**
 * General access control function that routes to specific role functions
 * @param string $role User role (student, lecturer, admin, hod)
 * @param string $feature Feature to check access for
 * @param array $userData User data array
 * @return bool True if access is granted, false otherwise
 */
function hasAccess($role, $feature, $userData = []) {
    switch (strtolower($role)) {
        case 'student':
            return hasStudentAccess($feature, $userData);
        case 'lecturer':
            return hasLecturerAccess($feature, $userData);
        case 'admin':
            return hasAdminAccess($feature, $userData);
        case 'hod':
            return hasHodAccess($feature, $userData);
        default:
            return false;
    }
}

/**
 * Get accessible features for a user role
 * @param string $role User role
 * @param array $userData User data array
 * @return array Array of accessible features
 */
function getAccessibleFeatures($role, $userData = []) {
    $features = [];

    // Define all possible features based on role
    $allFeatures = [
        'student' => [
            'attendance_records', 'leave_request', 'leave_status', 'my_courses',
            'academic_calendar', 'library_portal', 'fee_payments', 'career_portal'
        ],
        'lecturer' => [
            'dashboard_overview', 'my_courses', 'attendance_session',
            'attendance_reports', 'leave_requests'
        ],
        'admin' => [
            'dashboard_overview', 'user_management', 'register_student', 'register_lecturer',
            'manage_users', 'view_users', 'departments', 'assign_hod', 'analytics_reports',
            'attendance_reports', 'system_logs', 'leave_management'
        ],
        'hod' => [
            'dashboard_overview', 'department_reports', 'manage_lecturers',
            'leave_management', 'attendance_reports'
        ]
    ];

    if (!isset($allFeatures[$role])) {
        return $features;
    }

    foreach ($allFeatures[$role] as $feature) {
        if (hasAccess($role, $feature, $userData)) {
            $features[] = $feature;
        }
    }

    return $features;
}
?>