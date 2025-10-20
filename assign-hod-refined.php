<?php
/**
 * HOD Assignment System - Refined Version
 * Enhanced with better security, performance, and user experience
 *
 * @version 4.0.0
 * @author RP System Development Team
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Essential dependencies
require_once "config.php";
require_once "session_check.php";
require_once "backend/classes/Logger.php";

// Initialize logger
$logger = new Logger('logs/hod_assignment.log', Logger::INFO);

// Verify admin access with enhanced security
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $logger->warning('HOD Assignment', 'Unauthorized access attempt', [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'role' => $_SESSION['role'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    header('Location: login.php?error=access_denied');
    exit();
}

// Generate CSRF token with session fingerprinting
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_created'] = time();
}
$csrf_token = $_SESSION['csrf_token'];

// Validate CSRF token freshness (24 hours)
if (isset($_SESSION['csrf_created']) && (time() - $_SESSION['csrf_created']) > 86400) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_created'] = time();
    $csrf_token = $_SESSION['csrf_token'];
}

// Rate limiting for API requests with exponential backoff
function checkApiRateLimit() {
    $max_requests_per_minute = 30;
    $time_window = 60;

    if (!isset($_SESSION['api_requests'])) {
        $_SESSION['api_requests'] = [];
    }

    $current_time = time();

    // Clean old requests
    $_SESSION['api_requests'] = array_filter($_SESSION['api_requests'], function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });

    // Check if under limit
    if (count($_SESSION['api_requests']) >= $max_requests_per_minute) {
        return false;
    }

    // Add current request
    $_SESSION['api_requests'][] = $current_time;
    return true;
}

// Enhanced CSRF validation
function validateCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    // Check token age (24 hours max)
    if (isset($_SESSION['csrf_created']) && (time() - $_SESSION['csrf_created']) > 86400) {
        return false;
    }

    return true;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!validateCsrfToken($_POST['csrf_token'])) {
        $logger->warning('HOD Assignment', 'Invalid CSRF token', [
            'user_id' => $_SESSION['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }
}

// --- Unified Data API for AJAX ---
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    // Security checks for AJAX requests
    require_once "config.php";
    require_once "session_check.php";

    // Ensure user is logged in and is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
        exit;
    }

    // Rate limiting check
    if (!checkApiRateLimit()) {
        http_response_code(429);
        echo json_encode(['status' => 'error', 'message' => 'Too many requests. Please wait before trying again.']);
        exit;
    }

    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('X-Robots-Tag: noindex, nofollow');

    $action = $_GET['action'] ?? '';

    try {
        // Log API request for debugging
        error_log("HOD Assignment API Request: Action '{$action}' by user ID: " . ($_SESSION['user_id'] ?? 'unknown') .
                  " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

        switch ($action) {
            case 'get_lecturers':
                // Get all lecturers (including HODs) with improved query
                $stmt = $pdo->prepare("
                    SELECT l.id, u.first_name, u.last_name, u.email, u.role, u.phone, l.department_id,
                        CONCAT(u.first_name, ' ', u.last_name) as full_name,
                        u.username, u.status, u.created_at, u.updated_at,
                        d.name as department_name
                    FROM lecturers l
                    LEFT JOIN users u ON l.user_id = u.id
                    LEFT JOIN departments d ON l.department_id = d.id
                    WHERE u.role IN ('lecturer', 'hod')
                    ORDER BY u.first_name, u.last_name
                ");
                $stmt->execute();
                $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Validate data integrity
                foreach ($lecturers as &$lecturer) {
                    if (empty($lecturer['username']) && !empty($lecturer['email'])) {
                        $lecturer['data_integrity'] = 'warning';
                        $lecturer['data_integrity_message'] = 'User account not found';
                    }
                }

                echo json_encode(['status' => 'success', 'data' => $lecturers, 'count' => count($lecturers)]);
                break;

            case 'get_hods':
                // Get only HODs
                $stmt = $pdo->prepare("
                    SELECT l.id, u.first_name, u.last_name, u.email, u.role, u.phone, l.department_id,
                        CONCAT(u.first_name, ' ', u.last_name) as full_name,
                        u.username, u.status, u.created_at, u.updated_at
                    FROM lecturers l
                    LEFT JOIN users u ON l.user_id = u.id
                    WHERE u.role = 'hod'
                    ORDER BY u.first_name, u.last_name
                ");
                $stmt->execute();
                $hods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $hods]);
                break;

            case 'get_departments':
                // Get departments with HOD information and data integrity checks
                $stmt = $pdo->prepare("
                    SELECT d.id, d.name, d.hod_id,
                        u.first_name AS hod_first_name,
                        u.last_name AS hod_last_name,
                        CONCAT(u.first_name, ' ', u.last_name) as hod_name,
                        u.email AS hod_email,
                        u.role AS hod_role,
                        CASE
                            WHEN d.hod_id IS NULL THEN 'unassigned'
                            WHEN d.hod_id IS NOT NULL AND l.id IS NULL THEN 'invalid'
                            WHEN d.hod_id IS NOT NULL AND u.role != 'hod' THEN 'invalid_role'
                            ELSE 'assigned'
                        END as assignment_status
                    FROM departments d
                    LEFT JOIN lecturers l ON d.hod_id = l.id
                    LEFT JOIN users u ON l.user_id = u.id
                    ORDER BY d.name
                ");
                $stmt->execute();
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Add data integrity warnings
                $integrity_issues = 0;
                foreach ($departments as &$dept) {
                    if ($dept['assignment_status'] === 'invalid' || $dept['assignment_status'] === 'invalid_role') {
                        $integrity_issues++;
                        $dept['data_integrity'] = 'warning';
                        $dept['data_integrity_message'] = $dept['assignment_status'] === 'invalid' ?
                            'HOD ID exists but lecturer not found' :
                            'Assigned lecturer is not marked as HOD';
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => $departments,
                    'count' => count($departments),
                    'integrity_issues' => $integrity_issues
                ]);
                break;

            case 'get_assignment_stats':
                // Get statistics
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM departments");
                $stmt->execute();
                $totalDepts = $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL");
                $stmt->execute();
                $assignedDepts = $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM lecturers l LEFT JOIN users u ON l.user_id = u.id WHERE u.role IN ('lecturer', 'hod') AND u.id IS NOT NULL");
                $stmt->execute();
                $totalLecturers = $stmt->fetchColumn();

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'total_departments' => $totalDepts,
                        'assigned_departments' => $assignedDepts,
                        'unassigned_departments' => $totalDepts - $assignedDepts,
                        'total_lecturers' => $totalLecturers
                    ]
                ]);
                break;

            case 'assign_hod':
                // Handle HOD assignment with comprehensive validation
                $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
                $hod_id = filter_input(INPUT_POST, 'hod_id', FILTER_VALIDATE_INT);

                // Validate department ID
                if (!$department_id || $department_id <= 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid department ID provided']);
                    exit;
                }

                // Verify department exists and get current assignment
                $stmt = $pdo->prepare("SELECT id, name, hod_id FROM departments WHERE id = ?");
                $stmt->execute([$department_id]);
                $department = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$department) {
                    echo json_encode(['status' => 'error', 'message' => 'Department not found in database']);
                    exit;
                }

                // Check if this is actually a change
                $current_hod_id = $department['hod_id'];
                if ($current_hod_id == $hod_id) {
                    echo json_encode([
                        'status' => 'info',
                        'message' => 'No changes made - same HOD assignment already exists'
                    ]);
                    exit;
                }

                $pdo->beginTransaction();

                try {
                    if ($hod_id) {
                        // Validate lecturer exists and get details
                        $stmt = $pdo->prepare("
                            SELECT l.id, u.first_name, u.last_name, u.email, u.role, l.department_id
                            FROM lecturers l
                            LEFT JOIN users u ON l.user_id = u.id
                            WHERE l.id = ?
                        ");
                        $stmt->execute([$hod_id]);
                        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$lecturer) {
                            throw new Exception('Lecturer not found in database');
                        }

                        // Check if lecturer is already HOD for another department
                        if ($lecturer['role'] === 'hod' && $current_hod_id != $hod_id) {
                            $stmt = $pdo->prepare("SELECT name FROM departments WHERE hod_id = ? AND id != ?");
                            $stmt->execute([$hod_id, $department_id]);
                            $other_dept = $stmt->fetch(PDO::FETCH_ASSOC);

                            if ($other_dept) {
                                throw new Exception("Lecturer is already HOD for department: {$other_dept['name']}");
                            }
                        }

                        // Create or update user account for HOD
                        $username = strtolower($lecturer['first_name'] . '.' . $lecturer['last_name']);
                        $default_password = password_hash('Welcome123!', PASSWORD_DEFAULT);

                        $stmt = $pdo->prepare("
                            INSERT INTO users (username, email, password, role, status, created_at)
                            VALUES (?, ?, ?, 'hod', 'active', NOW())
                            ON DUPLICATE KEY UPDATE
                            role = 'hod', status = 'active', updated_at = NOW()
                        ");
                        $stmt->execute([$username, $lecturer['email'], $default_password]);

                        // Log the assignment
                        $logger->info('HOD Assignment', 'HOD assigned successfully', [
                            'department' => $department['name'],
                            'hod_name' => $lecturer['first_name'] . ' ' . $lecturer['last_name'],
                            'user_id' => $_SESSION['user_id']
                        ]);

                    } else {
                        // Removing HOD assignment - log this action
                        $logger->info('HOD Assignment', 'HOD assignment removed', [
                            'department' => $department['name'],
                            'user_id' => $_SESSION['user_id']
                        ]);
                    }

                    // Update department HOD (this is the critical operation)
                    $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
                    $stmt->execute([$hod_id ?: null, $department_id]);

                    $pdo->commit();

                    $action = $hod_id ? 'assigned' : 'removed';
                    $hod_name = $hod_id ? "{$lecturer['first_name']} {$lecturer['last_name']}" : 'None';

                    echo json_encode([
                        'status' => 'success',
                        'message' => "HOD {$action} successfully for {$department['name']}",
                        'details' => [
                            'department' => $department['name'],
                            'hod_name' => $hod_name,
                            'action' => $action,
                            'previous_hod_id' => $current_hod_id,
                            'new_hod_id' => $hod_id
                        ]
                    ]);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $logger->error('HOD Assignment', 'Assignment failed', [
                        'error' => $e->getMessage(),
                        'department_id' => $department_id,
                        'hod_id' => $hod_id,
                        'user_id' => $_SESSION['user_id']
                    ]);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Assignment failed: ' . $e->getMessage()
                    ]);
                }
                break;

            default:
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        $logger->error('HOD Assignment', 'Database error', [
            'error' => $e->getMessage(),
            'action' => $action,
            'user_id' => $_SESSION['user_id']
        ]);

        // Don't expose database details to client
        echo json_encode([
            'status' => 'error',
            'message' => 'Database operation failed. Please try again or contact administrator if the problem persists.',
            'error_code' => 'DB_ERROR'
        ]);
    } catch (Exception $e) {
        $logger->error('HOD Assignment', 'General error', [
            'error' => $e->getMessage(),
            'action' => $action,
            'user_id' => $_SESSION['user_id']
        ]);

        echo json_encode([
            'status' => 'error',
            'message' => 'An unexpected error occurred: ' . htmlspecialchars($e->getMessage()),
            'error_code' => 'GENERAL_ERROR'
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <title>Assign HOD | RP Attendance System</title>

    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/assign-hod.css" rel="stylesheet">
</head>

<body>
    <!-- Skip Navigation for Accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner-border loading-spinner mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="mb-2">Loading HOD Assignment System</h5>
            <p class="mb-0">Please wait while we fetch the latest data...</p>
            <div class="progress mt-3" style="height: 4px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <h5><i class="fas fa-graduation-cap me-2"></i>RP System</h5>
            <small>Admin Panel</small>
        </div>

        <ul class="sidebar-nav">
            <li>
                <a href="admin-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>Dashboard
                </a>
            </li>
            <li>
                <a href="manage-departments.php">
                    <i class="fas fa-building"></i>Departments
                </a>
            </li>
            <li>
                <a href="assign-hod.php" class="active">
                    <i class="fas fa-user-tie"></i>Assign HOD
                </a>
            </li>
            <li>
                <a href="admin-reports.php">
                    <i class="fas fa-chart-bar"></i>Reports
                </a>
            </li>
            <li>
                <a href="manage-users.php">
                    <i class="fas fa-users"></i>Manage Users
                </a>
            </li>
            <li>
                <a href="system-logs.php">
                    <i class="fas fa-file-alt"></i>System Logs
                </a>
            </li>
            <li class="mt-4">
                <a href="logout.php" class="text-danger">
                    <i class="fas fa-sign-out-alt"></i>Logout
                </a>
            </li>
        </ul>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="window.hodManager?.toggleSidebar()" aria-label="Toggle navigation menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="topbar">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <h2 class="mb-0">
                        <i class="fas fa-user-tie me-3"></i>Assign Head of Department
                    </h2>
                </div>
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <div class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-user-shield me-1"></i>Admin
                    </div>
                    <button class="btn btn-outline-warning btn-sm" onclick="window.hodManager?.fixInvalidAssignments()" title="Fix invalid assignments" id="fixInvalidBtn" style="display: none;">
                        <i class="fas fa-tools me-1"></i>Fix Invalid
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="window.hodManager?.exportAssignments()" title="Export assignments to CSV">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="window.hodManager?.showHelp()" title="Show help">
                        <i class="fas fa-question-circle"></i>
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="window.hodManager?.loadInitialData()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer" class="container-fluid mt-3"></div>

        <!-- Statistics Cards -->
        <div class="container-fluid mt-4">
            <div class="row g-4 mb-5">
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="stats-card fade-in">
                        <i class="fas fa-building text-primary"></i>
                        <h3 id="totalDepartments" aria-live="polite">0</h3>
                        <p>Total Departments</p>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="stats-card fade-in">
                        <i class="fas fa-user-check text-success"></i>
                        <h3 id="assignedDepartments" aria-live="polite">0</h3>
                        <p>Assigned HODs</p>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="stats-card fade-in">
                        <i class="fas fa-chalkboard-teacher text-info"></i>
                        <h3 id="totalLecturers" aria-live="polite">0</h3>
                        <p>Available Lecturers</p>
                    </div>
                </div>
                <div class="col-xl-3 col-lg-6 col-md-6">
                    <div class="stats-card fade-in">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        <h3 id="unassignedDepartments" aria-live="polite">0</h3>
                        <p>Unassigned Departments</p>
                    </div>
                </div>
            </div>

            <!-- Assignment Form -->
            <div class="card border-0 shadow mb-4 fade-in">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-plus-circle me-2"></i>
                        HOD Assignment Form
                    </h5>
                </div>
                <div class="card-body">
                    <form id="assignHodForm" novalidate>
                        <input type="hidden" id="departmentId" name="department_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                        <!-- Search and Filter Row -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="departmentSearch" class="form-label fw-semibold">
                                    <i class="fas fa-search me-1" aria-hidden="true"></i>Search Departments
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light" aria-hidden="true"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control" id="departmentSearch"
                                           placeholder="Type to search departments..." aria-describedby="departmentSearchHelp"
                                           autocomplete="off" spellcheck="false">
                                </div>
                                <div id="departmentSearchHelp" class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1" aria-hidden="true"></i>Start typing to filter departments (case-insensitive)
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="lecturerSearch" class="form-label fw-semibold">
                                    <i class="fas fa-search me-1" aria-hidden="true"></i>Search Lecturers
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light" aria-hidden="true"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control" id="lecturerSearch"
                                           placeholder="Type to search lecturers..." aria-describedby="lecturerSearchHelp"
                                           autocomplete="off" spellcheck="false">
                                </div>
                                <div id="lecturerSearchHelp" class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1" aria-hidden="true"></i>Start typing to filter lecturers (case-insensitive)
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="departmentSelect" class="form-label fw-semibold">
                                    <i class="fas fa-building me-1" aria-hidden="true"></i>Department <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="departmentSelect" name="department_id" required
                                        aria-describedby="departmentSelectFeedback" aria-required="true">
                                    <option value="">-- Select Department --</option>
                                </select>
                                <div id="departmentSelectFeedback" class="form-text mt-2" role="status" aria-live="polite"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lecturerSelect" class="form-label fw-semibold">
                                    <i class="fas fa-user-graduate me-1" aria-hidden="true"></i>Head of Department
                                </label>
                                <select class="form-select" id="lecturerSelect" name="hod_id"
                                        aria-describedby="lecturerSelectFeedback">
                                    <option value="">-- Select Lecturer (Optional) --</option>
                                </select>
                                <div id="lecturerSelectFeedback" class="form-text mt-2 text-info" role="status" aria-live="polite">
                                    <i class="fas fa-info-circle me-1" aria-hidden="true"></i>Leave empty to remove HOD assignment
                                </div>
                            </div>
                        </div>

                        <!-- Current Assignment Info -->
                        <div class="alert alert-info fade-in" id="currentAssignmentInfo" style="display: none;" role="status">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Current Assignment:</strong> <span id="currentHodName"></span>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="window.hodManager?.clearCurrentAssignment()" aria-label="Clear current assignment">
                                    <i class="fas fa-times me-1"></i>Clear
                                </button>
                            </div>
                        </div>

                        <!-- Assignment Preview -->
                        <div class="alert alert-warning fade-in" id="assignmentPreview" style="display: none;" role="status">
                            <i class="fas fa-eye me-2"></i>
                            <strong>Assignment Preview:</strong>
                            <div id="previewText" class="mt-2"></div>
                        </div>

                        <div class="d-flex gap-2 flex-wrap">
                            <button type="submit" class="btn btn-primary" id="assignBtn">
                                <i class="fas fa-save me-2"></i>Assign HOD
                            </button>
                            <button type="button" class="btn btn-secondary" id="resetFormBtn">
                                <i class="fas fa-undo me-2"></i>Reset Form
                            </button>
                            <button type="button" class="btn btn-info" id="previewBtn">
                                <i class="fas fa-eye me-2"></i>Preview Assignment
                            </button>
                            <button type="button" class="btn btn-outline-success" id="bulkModeBtn">
                                <i class="fas fa-layer-group me-2"></i>Bulk Mode
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Current Assignments -->
            <div class="card border-0 shadow fade-in">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <h5 class="mb-0 fw-bold">
                            <i class="fas fa-list me-2"></i>Current HOD Assignments
                        </h5>
                        <div class="d-flex gap-2 mt-2 mt-md-0">
                            <div class="input-group input-group-sm" style="width: 200px;">
                                <span class="input-group-text bg-light"><i class="fas fa-filter"></i></span>
                                <select class="form-select" id="statusFilter">
                                    <option value="all">All Status</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="unassigned">Unassigned</option>
                                    <option value="invalid">Invalid</option>
                                </select>
                            </div>
                            <button class="btn btn-outline-light btn-sm" id="refreshAssignments">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body bg-white">
                    <div id="assignmentsContainer" class="row g-3 assignments-container">
                        <!-- Assignments will be loaded here -->
                        <div class="col-12 text-center py-5">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Loading assignments...</span>
                            </div>
                            <p class="text-muted">Loading department assignments...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/assign-hod.js"></script>

    <script>
        // Make CSRF token available globally
        window.csrfToken = "<?php echo $csrf_token; ?>";
    </script>
</body>
</html>