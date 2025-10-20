<?php
/**
 * HOD Assignment System - Frontend Interface
 * Clean, modular interface for HOD assignment management
 *
 * This file serves as the main UI for the HOD assignment system.
 * All business logic has been moved to dedicated classes and API endpoints.
 *
 * @version 6.1.0
 * @author RP System Development Team
 * @since 2025-01-17
 */

// Security headers - Prevent common web vulnerabilities
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Essential dependencies - Load configuration and security
require_once "config.php";
require_once "session_check.php";
require_once "backend/classes/Logger.php";

// Initialize logger for security and debugging
$logger = new Logger('logs/hod_assignment_ui.log', Logger::INFO);

// Verify admin access with comprehensive security checks
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $logger->warning('HOD Assignment UI', 'Unauthorized access attempt', [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'role' => $_SESSION['role'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    header('Location: login.php?error=access_denied');
    exit();
}

// Generate CSRF token with session fingerprinting for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = SecurityUtils::generateSecureToken(32);
    $_SESSION['csrf_created'] = time();
}
$csrf_token = $_SESSION['csrf_token'];

// Validate CSRF token freshness (24 hours expiry)
if (isset($_SESSION['csrf_created']) && (time() - $_SESSION['csrf_created']) > 86400) {
    $_SESSION['csrf_token'] = SecurityUtils::generateSecureToken(32);
    $_SESSION['csrf_created'] = time();
    $csrf_token = $_SESSION['csrf_token'];
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!SecurityUtils::validateCSRFToken($_POST['csrf_token'], $_SESSION['csrf_token'])) {
        $logger->warning('HOD Assignment UI', 'Invalid CSRF token in POST request', [
            'user_id' => $_SESSION['user_id'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid security token']);
        exit;
    }
}

// Handle AJAX requests by redirecting to dedicated API endpoint
// This maintains backward compatibility while centralizing API logic
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    // For POST requests, forward to API with POST data
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Build the target URL
        $target_url = 'api/assign-hod-api.php?' . http_build_query($_GET);

        // Create a new POST request to the API endpoint
        $ch = curl_init($target_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($_POST));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'X-Forwarded-For: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']),
            'User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Add timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Add connection timeout

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        // Check for cURL errors
        if ($curl_error) {
            $logger->error('HOD Assignment UI', 'cURL error forwarding request', [
                'error' => $curl_error,
                'target_url' => $target_url,
                'user_id' => $_SESSION['user_id'] ?? 'unknown'
            ]);
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to process request: ' . $curl_error]);
            exit;
        }

        // Separate headers and body
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        // Set the response code and output
        http_response_code($http_code);
        echo $body;
        exit;
    } else {
        // For GET requests, simple redirect
        header('Location: api/assign-hod-api.php?' . http_build_query($_GET));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <title>Assign HOD | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/assign-hod.css" rel="stylesheet">
</head>

<body>
    <!-- Skip Navigation for Accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Enhanced Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner-container mb-3">
                <div class="spinner-border loading-spinner text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="spinner-border loading-spinner text-secondary ms-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="spinner-border loading-spinner text-success ms-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <h5 class="mb-2" id="loadingTitle">Loading HOD Assignment System</h5>
            <p class="mb-3" id="loadingMessage">Please wait while we fetch the latest data...</p>
            <div class="progress mt-3" style="height: 6px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="loadingProgress" style="width: 0%"></div>
            </div>
            <small class="text-muted mt-2" id="loadingStep">Initializing...</small>
        </div>
    </div>

    <!-- Toast Notification Container -->
    <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

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
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
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
                    <button class="btn btn-outline-warning btn-sm" onclick="fixInvalidAssignments()" title="Fix invalid assignments" id="fixInvalidBtn" style="display: none;">
                        <i class="fas fa-tools me-1"></i>Fix Invalid
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="exportAssignments()" title="Export assignments to CSV">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <button class="btn btn-outline-info btn-sm" onclick="showHelp()" title="Show help">
                        <i class="fas fa-question-circle"></i>
                    </button>
                    <button class="btn btn-success btn-sm" id="quickStatsBtn" onclick="showQuickStats()" title="Show quick statistics">
                        <i class="fas fa-chart-bar me-1"></i>Stats
                    </button>
                    <button class="btn btn-primary btn-sm" onclick="loadData()" id="refreshBtn">
                        <i class="fas fa-sync-alt me-1"></i><span id="refreshText">Refresh</span>
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
                                    <i class="fas fa-info-circle me-1" aria-hidden="true"></i>Leave empty to remove current HOD assignment
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
                                <button type="button" class="btn btn-sm btn-outline-info" onclick="clearCurrentAssignment()" aria-label="Clear current assignment">
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
                            <button type="button" class="btn btn-info" id="previewBtn" onclick="showAssignmentPreview()">
                                <i class="fas fa-eye me-2"></i>Preview Assignment
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="enableBulkMode()" id="bulkModeBtn">
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
                            <div class="d-flex gap-2 align-items-center flex-wrap">
                                <div class="input-group input-group-sm" style="width: 180px;">
                                    <span class="input-group-text bg-light"><i class="fas fa-filter"></i></span>
                                    <select class="form-select" id="statusFilter" onchange="filterAssignments()">
                                        <option value="all">All Status</option>
                                        <option value="assigned">Assigned</option>
                                        <option value="unassigned">Unassigned</option>
                                        <option value="invalid">Invalid</option>
                                    </select>
                                </div>
                                <div class="input-group input-group-sm" style="width: 200px;">
                                    <span class="input-group-text bg-light"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="globalSearch" placeholder="Search departments..." oninput="globalSearch()">
                                </div>
                                <div class="badge bg-info fs-7 px-2 py-1" id="resultCount" style="display: none;">
                                    <i class="fas fa-list me-1"></i><span id="resultCountText">0</span> results
                                </div>
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

    <!-- External Libraries -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Application Scripts -->
    <script src="js/assign-hod.js?v=3.0.2"></script>

    <!-- CSRF Token Configuration -->
    <script>
        // Make CSRF token available globally for AJAX requests
        window.csrfToken = "<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>";
    </script>
</body>
</html>