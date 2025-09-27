<?php
/**
 * User Management System
 * Comprehensive user management interface for administrators
 * Handles user creation, editing, role management, and account status
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);



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
 * Get all users with their details
 */
function getAllUsers($search = '', $role_filter = '', $status_filter = '') {
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
 * Create new user
 */
function createUser($username, $email, $password, $role, $first_name, $last_name, $phone = null, $reference_id = null) {
    global $pdo;

    try {
        // Validate inputs
        if (empty($username) || empty($email) || empty($password) || empty($role) || empty($first_name) || empty($last_name)) {
            throw new Exception('All required fields must be filled');
        }

        if (!validate_email($email)) {
            throw new Exception('Invalid email format');
        }

        if (!validate_role($role)) {
            throw new Exception('Invalid role specified');
        }

        if (strlen($password) < 8) {
            throw new Exception('Password must be at least 8 characters long');
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

// Handle AJAX requests
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
                $users = getAllUsers($search, $role_filter, $status_filter);
                $stats = getUserStats();

                echo json_encode([
                    'status' => 'success',
                    'data' => $users,
                    'stats' => $stats,
                    'timestamp' => time()
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

                $user_id = createUser($username, $email, $password, $role, $first_name, $last_name, $phone, $reference_id);

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

                updateUser($user_id, $username, $email, $role, $first_name, $last_name, $phone, $reference_id, $status);

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
    <title>Manage Users | RP Attendance System</title>
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
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            padding: 20px 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--shadow-medium);
        }

        .sidebar .logo {
            text-align: center;
            margin-bottom: 30px;
            padding: 0 20px;
        }

        .sidebar .logo h3 {
            color: #333;
            font-weight: 700;
            margin: 0;
            font-size: 1.5rem;
        }

        .sidebar .logo small {
            color: #6c757d;
            font-size: 0.8rem;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-nav li {
            margin: 5px 0;
        }

        .sidebar-nav a {
            display: block;
            padding: 12px 20px;
            color: #495057;
            text-decoration: none;
            border-radius: 8px;
            margin: 0 15px;
            transition: var(--transition);
            font-weight: 500;
        }

        .sidebar-nav a:hover {
            background: rgba(0, 102, 204, 0.1);
            color: #0066cc;
            transform: translateX(5px);
        }

        .sidebar-nav a.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }

        .sidebar-nav a i {
            margin-right: 10px;
            width: 18px;
            text-align: center;
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
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px;
            box-shadow: var(--shadow-medium);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .mobile-menu-toggle {
                display: block !important;
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
            <li><a href="admin-dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
            <li><a href="register-student.php"><i class="fas fa-user-plus"></i>Register Student</a></li>
            <li><a href="manage-departments.php"><i class="fas fa-building"></i>Departments</a></li>
            <li><a href="assign-hod.php"><i class="fas fa-user-tie"></i>Assign HOD</a></li>
            <li><a href="admin-reports.php"><i class="fas fa-chart-bar"></i>Reports</a></li>
            <li><a href="system-logs.php"><i class="fas fa-file-alt"></i>System Logs</a></li>
            <li><a href="manage-users.php" class="active"><i class="fas fa-users"></i>Manage Users</a></li>
            <li><a href="attendance-reports.php"><i class="fas fa-calendar-check"></i>Attendance</a></li>
            <li><a href="hod-leave-management.php"><i class="fas fa-clipboard-list"></i>Leave Mgmt</a></li>
            <li class="mt-4"><a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i>Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="mb-1 text-primary">
                    <i class="fas fa-users me-3"></i>User Management
                </h2>
                <p class="text-muted mb-0">Manage user accounts, roles, and permissions</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-outline-primary" id="refreshUsers">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="fas fa-plus me-1"></i>Create User
                </button>
            </div>
        </div>

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
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search users...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="roleFilter">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="hod">HOD</option>
                            <option value="lecturer">Lecturer</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" id="clearFilters">
                            <i class="fas fa-times me-1"></i>Clear
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
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Last Login</th>
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

            $.ajax({
                url: 'manage-users.php',
                method: 'GET',
                data: {
                    ajax: '1',
                    action: 'get_users',
                    search: search,
                    role: role,
                    status: status,
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
                        <td colspan="7" class="text-center py-4">
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
                        <small>${user.last_login ? formatDate(user.last_login) : 'Never'}</small>
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

        function applyFilters() {
            const search = $("#searchInput").val().toLowerCase();
            const role = $("#roleFilter").val();
            const status = $("#statusFilter").val();

            filteredUsers = currentUsers.filter(user => {
                const matchesSearch = !search ||
                    (user.username && user.username.toLowerCase().includes(search)) ||
                    (user.email && user.email.toLowerCase().includes(search)) ||
                    `${user.first_name || ''} ${user.last_name || ''}`.toLowerCase().includes(search);

                const matchesRole = !role || user.role === role;
                const matchesStatus = !status || user.status === status;

                return matchesSearch && matchesRole && matchesStatus;
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

        // Event handlers
        $(document).ready(function() {
            // Load users immediately
            loadUsers();

            // Search and filter events
            $("#searchInput, #roleFilter, #statusFilter").on('input change', function() {
                applyFilters();
            });

            $("#clearFilters").click(function() {
                $("#searchInput").val('');
                $("#roleFilter").val('');
                $("#statusFilter").val('');
                applyFilters();
            });

            // Refresh button
            $("#refreshUsers").click(function() {
                loadUsers();
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

            // Auto-refresh every 2 minutes
            setInterval(loadUsers, 120000);
        });
    </script>
</body>
</html>