<?php
/**
 * HOD Assignment System - Complete Fixed Version
 * Enhanced with better security, performance, and user experience
 *
 * @version 3.1.0
 * @author RP System Development Team
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Essential dependencies
require_once "config.php";
require_once "session_check.php";

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=access_denied');
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
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

    header('Content-Type: application/json');
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_lecturers':
                // Get all lecturers (including HODs)
                $stmt = $pdo->prepare("
                    SELECT l.id, l.first_name, l.last_name, l.email, l.role, l.phone, l.department_id,
                        CONCAT(l.first_name, ' ', l.last_name) as full_name,
                        u.username, u.status, u.created_at, u.updated_at
                    FROM lecturers l
                    LEFT JOIN users u ON u.email = l.email AND u.role = l.role
                    WHERE l.role IN ('lecturer', 'hod')
                    ORDER BY l.first_name, l.last_name
                ");
                $stmt->execute();
                $lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $lecturers]);
                break;
                
            case 'get_hods':
                // Get only HODs
                $stmt = $pdo->prepare("
                    SELECT l.id, l.first_name, l.last_name, l.email, l.role, l.phone, l.department_id,
                        CONCAT(l.first_name, ' ', l.last_name) as full_name,
                        u.username, u.status, u.created_at, u.updated_at
                    FROM lecturers l
                    LEFT JOIN users u ON u.email = l.email AND u.role = l.role
                    WHERE l.role = 'hod'
                    ORDER BY l.first_name, l.last_name
                ");
                $stmt->execute();
                $hods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $hods]);
                break;
                
            case 'get_departments':
                // Get departments with HOD information
                $stmt = $pdo->prepare("
                    SELECT d.id, d.name, d.hod_id,
                        l.first_name AS hod_first_name, 
                        l.last_name AS hod_last_name, 
                        CONCAT(l.first_name, ' ', l.last_name) as hod_name,
                        l.email AS hod_email
                    FROM departments d
                    LEFT JOIN lecturers l ON d.hod_id = l.id
                    ORDER BY d.name
                ");
                $stmt->execute();
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['status' => 'success', 'data' => $departments]);
                break;
                
            case 'get_assignment_stats':
                // Get statistics
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM departments");
                $stmt->execute();
                $totalDepts = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL");
                $stmt->execute();
                $assignedDepts = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM lecturers WHERE role IN ('lecturer', 'hod')");
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
                // Handle HOD assignment
                $department_id = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
                $hod_id = filter_input(INPUT_POST, 'hod_id', FILTER_VALIDATE_INT);
                
                if (!$department_id) {
                    echo json_encode(['status' => 'error', 'message' => 'Invalid department ID']);
                    exit;
                }
                
                // Verify department exists
                $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
                $stmt->execute([$department_id]);
                $department = $stmt->fetch();
                
                if (!$department) {
                    echo json_encode(['status' => 'error', 'message' => 'Department not found']);
                    exit;
                }
                
                if ($hod_id) {
                    // Verify lecturer exists and can be HOD
                    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM lecturers WHERE id = ?");
                    $stmt->execute([$hod_id]);
                    $lecturer = $stmt->fetch();
                    
                    if (!$lecturer) {
                        echo json_encode(['status' => 'error', 'message' => 'Lecturer not found']);
                        exit;
                    }
                    
                    // Update lecturer role to HOD if not already
                    $stmt = $pdo->prepare("UPDATE lecturers SET role = 'hod' WHERE id = ?");
                    $stmt->execute([$hod_id]);
                    
                    // Create or update user account for HOD
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, role, status, created_at) 
                        VALUES (?, ?, ?, 'hod', 'active', NOW())
                        ON DUPLICATE KEY UPDATE 
                        role = 'hod', status = 'active', updated_at = NOW()
                    ");
                    $username = strtolower($lecturer['first_name'] . '.' . $lecturer['last_name']);
                    $default_password = password_hash('password123', PASSWORD_DEFAULT);
                    $stmt->execute([$username, $lecturer['email'], $default_password]);
                }
                
                // Update department HOD
                $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
                $stmt->execute([$hod_id ?: null, $department_id]);
                
                $action = $hod_id ? 'assigned' : 'removed';
                echo json_encode([
                    'status' => 'success', 
                    'message' => "HOD {$action} successfully for {$department['name']}"
                ]);
                break;
                
            default:
                echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("HOD Assignment API Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Database error occurred: ' . $e->getMessage()]);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --sidebar-width: 280px;
            --topbar-height: 80px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(10px);
        }

        .loading-content {
            text-align: center;
            color: white;
            max-width: 400px;
            padding: 2rem;
        }

        .loading-spinner {
            width: 4rem;
            height: 4rem;
            border-width: 0.3em;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%);
            color: white;
            z-index: 1000;
            transition: transform 0.3s ease;
            box-shadow: 3px 0 15px rgba(0, 0, 0, 0.1);
        }

        .sidebar .logo {
            padding: 2rem 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar .logo h5 {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .sidebar .logo small {
            opacity: 0.7;
            font-size: 0.8rem;
        }

        .sidebar-nav {
            list-style: none;
            padding: 1.5rem 0;
        }

        .sidebar-nav li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: #3498db;
        }

        .sidebar-nav a i {
            width: 1.5rem;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        /* Mobile Menu */
        .mobile-menu-toggle {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--primary-gradient);
            border: none;
            color: white;
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .topbar {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid #e9ecef;
        }

        .topbar h2 {
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Statistics Cards */
        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            height: 100%;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .stats-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stats-card p {
            color: #6c757d;
            font-weight: 500;
            margin: 0;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .card-header {
            border-radius: 1rem 1rem 0 0 !important;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        /* Assignment Cards */
        .assignments-container {
            min-height: 300px;
        }

        .assignment-card {
            border: none;
            border-radius: 1rem;
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
        }

        .assignment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .assignment-card.assigned {
            border-left: 4px solid #28a745;
        }

        .assignment-card.unassigned {
            border-left: 4px solid #ffc107;
        }

        .assignment-card.invalid {
            border-left: 4px solid #dc3545;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        .assignment-card.selected {
            border: 2px solid #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        }

        /* Buttons */
        .btn {
            border-radius: 0.75rem;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: var(--primary-gradient);
        }

        .btn-success {
            background: var(--success-gradient);
        }

        .btn-warning {
            background: var(--warning-gradient);
        }

        .btn-info {
            background: var(--info-gradient);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
        }

        .data-integrity-alert {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border-left: 4px solid #ffc107;
        }

        /* Progress Bars */
        .progress {
            border-radius: 1rem;
            height: 6px;
            background: #e9ecef;
        }

        .progress-bar {
            border-radius: 1rem;
            transition: width 0.6s ease;
        }

        /* Form Controls */
        .form-control,
        .form-select {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        /* Badges */
        .badge {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: flex;
            }

            .topbar {
                padding: 1rem;
            }

            .topbar h2 {
                font-size: 1.5rem;
            }

            .stats-card {
                padding: 1rem;
            }

            .stats-card h3 {
                font-size: 2rem;
            }
        }

        /* Skip Link for Accessibility */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: #000;
            color: white;
            padding: 8px;
            z-index: 10000;
            text-decoration: none;
            border-radius: 0 0 4px 4px;
        }

        .skip-link:focus {
            top: 0;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-gradient);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
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

        /* Print Styles */
        @media print {
            .sidebar, .topbar, .btn, .mobile-menu-toggle {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
            }
        }
    </style>
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
                    <button class="btn btn-primary btn-sm" onclick="loadData()">
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
                                    <i class="fas fa-search me-1"></i>Search Departments
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control" id="departmentSearch" 
                                           placeholder="Type to search departments..." aria-describedby="departmentSearchHelp">
                                </div>
                                <div id="departmentSearchHelp" class="form-text">Start typing to filter departments</div>
                            </div>
                            <div class="col-md-6">
                                <label for="lecturerSearch" class="form-label fw-semibold">
                                    <i class="fas fa-search me-1"></i>Search Lecturers
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-search text-muted"></i></span>
                                    <input type="text" class="form-control" id="lecturerSearch" 
                                           placeholder="Type to search lecturers..." aria-describedby="lecturerSearchHelp">
                                </div>
                                <div id="lecturerSearchHelp" class="form-text">Start typing to filter lecturers</div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="departmentSelect" class="form-label fw-semibold">
                                    <i class="fas fa-building me-1"></i>Department *
                                </label>
                                <select class="form-select" id="departmentSelect" name="department_id" required aria-describedby="departmentSelectFeedback">
                                    <option value="">-- Select Department --</option>
                                </select>
                                <div id="departmentSelectFeedback" class="form-text mt-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lecturerSelect" class="form-label fw-semibold">
                                    <i class="fas fa-user-graduate me-1"></i>Head of Department
                                </label>
                                <select class="form-select" id="lecturerSelect" name="hod_id" aria-describedby="lecturerSelectFeedback">
                                    <option value="">-- Select Lecturer --</option>
                                </select>
                                <div id="lecturerSelectFeedback" class="form-text mt-2"></div>
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
                            <div class="input-group input-group-sm" style="width: 200px;">
                                <span class="input-group-text bg-light"><i class="fas fa-filter"></i></span>
                                <select class="form-select" id="statusFilter" onchange="filterAssignments()">
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

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make CSRF token available globally
        window.csrfToken = "<?php echo $csrf_token; ?>";

        // Global state management
        const AppState = {
            departments: [],
            lecturers: [],
            selectedDepartments: new Set(),
            filters: {
                search: '',
                status: 'all'
            },
            isBulkMode: false,
            
            // State setters
            setDepartments: function(depts) {
                this.departments = depts;
                this.notify('departmentsChanged');
            },
            
            setLecturers: function(lects) {
                this.lecturers = lects;
                this.notify('lecturersChanged');
            },
            
            // Observer pattern for state changes
            observers: {},
            subscribe: function(event, callback) {
                if (!this.observers[event]) this.observers[event] = [];
                this.observers[event].push(callback);
            },
            
            notify: function(event, data) {
                if (this.observers[event]) {
                    this.observers[event].forEach(callback => callback(data));
                }
            }
        };

        // Utility functions
        const Utils = {
            debounce: function(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            },

            animateValue: function(id, newValue, duration = 600) {
                const el = document.getElementById(id);
                if (!el) return;

                const current = parseInt(el.innerText) || 0;
                const startTime = Date.now();

                function update() {
                    const elapsed = Date.now() - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const easeOut = 1 - Math.pow(1 - progress, 3);
                    const value = Math.floor(current + (newValue - current) * easeOut);

                    el.innerText = value;

                    if (progress < 1) {
                        requestAnimationFrame(update);
                    } else {
                        el.innerText = newValue;
                    }
                }

                requestAnimationFrame(update);
            },

            exportToCSV: function(data, filename) {
                const headers = Object.keys(data[0]);
                const csvContent = [
                    headers.join(','),
                    ...data.map(row => headers.map(header => {
                        const value = row[header];
                        return typeof value === 'string' && value.includes(',') ? `"${value}"` : value;
                    }).join(','))
                ].join('\n');

                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.setAttribute('hidden', '');
                a.setAttribute('href', url);
                a.setAttribute('download', filename);
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }
        };

        // DOM Ready
        $(document).ready(function() {
            initializeApp();
            setupEventHandlers();
            setupFullscreenHandling();
            loadData();
        });

        function initializeApp() {
            console.log('Initializing HOD Assignment System...');
            
            // Subscribe to state changes
            AppState.subscribe('departmentsChanged', renderAssignments);
            AppState.subscribe('lecturersChanged', updateLecturerSelect);
            
            // Add keyboard shortcuts help
            $(document).on('keydown', handleKeyboardShortcuts);
        }

        function setupEventHandlers() {
            // Department form submission
            $('#assignHodForm').on('submit', handleDepartmentSubmit);

            // Department selection change
            $('#departmentSelect').on('change', function() {
                updateCurrentAssignmentInfo();
                validateForm();
            });

            // Lecturer selection change
            $('#lecturerSelect').on('change', function() {
                validateForm();
                updateAssignmentPreview();
            });

            // Search functionality with debouncing
            const debouncedDepartmentSearch = Utils.debounce((term) => {
                filterDepartments(term);
            }, 300);

            const debouncedLecturerSearch = Utils.debounce((term) => {
                filterLecturers(term);
            }, 300);

            $('#departmentSearch').on('input', function() {
                debouncedDepartmentSearch($(this).val());
            });

            $('#lecturerSearch').on('input', function() {
                debouncedLecturerSearch($(this).val());
            });

            // Reset form
            $('#resetFormBtn').on('click', function() {
                resetForm();
            });

            // Refresh assignments
            $('#refreshAssignments').on('click', function() {
                loadAssignments();
            });

            // Form validation on change
            $('#departmentSelect, #lecturerSelect').on('change', validateForm);
        }

        function handleKeyboardShortcuts(e) {
            // Ctrl/Cmd + R to refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                loadData();
            }

            // Ctrl/Cmd + S to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 's' && $('#departmentSelect').val()) {
                e.preventDefault();
                $('#assignHodForm').submit();
            }

            // Escape to reset form
            if (e.key === 'Escape') {
                if (AppState.isBulkMode) {
                    disableBulkMode();
                } else {
                    $('#resetFormBtn').click();
                }
            }

            // Ctrl/Cmd + B to toggle bulk mode
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                toggleBulkMode();
            }
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
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

        function setupFullscreenHandling() {
            function isFullscreen() {
                return !!(document.fullscreenElement || document.webkitFullscreenElement ||
                        document.mozFullScreenElement || document.msFullscreenElement);
            }

            function adjustForFullscreen() {
                const fullscreen = isFullscreen();
                const windowWidth = $(window).width();

                if (fullscreen) {
                    $('html, body').css({
                        'overflow-x': 'hidden',
                        'width': '100vw',
                        'height': '100vh'
                    });

                    if (windowWidth > 768) {
                        $('.sidebar').css({
                            'width': '280px',
                            'height': '100vh',
                            'position': 'fixed'
                        });
                        $('.main-content').css({
                            'margin-left': '280px',
                            'width': 'calc(100vw - 280px)',
                            'height': '100vh'
                        });
                    }
                } else {
                    const heightUnit = windowWidth <= 768 ? '100dvh' : '100vh';
                    $('.sidebar').css({
                        'height': heightUnit
                    });
                    $('.main-content').css({
                        'min-height': heightUnit
                    });
                }
            }

            $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
                setTimeout(adjustForFullscreen, 100);
            });

            $(window).on('orientationchange', function() {
                setTimeout(adjustForFullscreen, 200);
            });

            setTimeout(adjustForFullscreen, 500);
        }

        function filterDepartments(searchTerm) {
            const select = $('#departmentSelect');
            const options = select.find('option');
            let visibleCount = 0;

            options.each(function() {
                const option = $(this);
                const text = option.text().toLowerCase();
                const shouldShow = !searchTerm || text.includes(searchTerm.toLowerCase());

                if (option.val() !== '') {
                    option.toggle(shouldShow);
                    if (shouldShow) visibleCount++;
                }
            });

            updateSearchFeedback('departmentSelectFeedback', searchTerm, visibleCount, 'department');
        }

        function filterLecturers(searchTerm) {
            const select = $('#lecturerSelect');
            const options = select.find('option');
            let visibleCount = 0;

            options.each(function() {
                const option = $(this);
                const text = option.text().toLowerCase();
                const shouldShow = !searchTerm || text.includes(searchTerm.toLowerCase());

                if (option.val() !== '') {
                    option.toggle(shouldShow);
                    if (shouldShow) visibleCount++;
                }
            });

            updateSearchFeedback('lecturerSelectFeedback', searchTerm, visibleCount, 'lecturer');
        }

        function updateSearchFeedback(elementId, searchTerm, visibleCount, type) {
            const feedback = $('#' + elementId);
            
            if (searchTerm) {
                if (visibleCount === 0) {
                    feedback.html(`<i class="fas fa-exclamation-triangle me-1"></i>No ${type}s found matching "<strong>${searchTerm}</strong>"`)
                        .removeClass('text-success').addClass('text-warning');
                } else {
                    feedback.html(`<i class="fas fa-check me-1"></i>Found ${visibleCount} ${type}${visibleCount !== 1 ? 's' : ''} matching "<strong>${searchTerm}</strong>"`)
                        .removeClass('text-warning').addClass('text-success');
                }
            } else {
                feedback.text('').removeClass('text-success text-warning');
            }
        }

        function updateCurrentAssignmentInfo() {
            const selectedOption = $('#departmentSelect').find('option:selected');
            const hodId = selectedOption.data('hod');
            const hodName = selectedOption.data('hod-name');

            if (hodId && hodName) {
                $('#currentAssignmentInfo').show();
                $('#currentHodName').text(hodName);
                $('#lecturerSelect').val(hodId);
            } else {
                $('#currentAssignmentInfo').hide();
                $('#lecturerSelect').val('');
            }
            updateAssignmentPreview();
        }

        function updateAssignmentPreview() {
            const departmentId = $('#departmentSelect').val();
            const lecturerId = $('#lecturerSelect').val();

            if (!departmentId) {
                $('#assignmentPreview').hide();
                return;
            }

            const departmentOption = $('#departmentSelect option:selected');
            const lecturerOption = $('#lecturerSelect option:selected');
            const isInvalidAssignment = departmentOption.data('invalid') === 'true';

            const departmentName = departmentOption.text();
            const lecturerName = lecturerOption.text() || 'No HOD';

            let previewHtml = '<div class="row">';
            previewHtml += '<div class="col-md-6">';
            previewHtml += '<h6><i class="fas fa-building me-2"></i>Department</h6>';
            previewHtml += `<p class="mb-1"><strong>${departmentName}</strong></p>`;
            if (isInvalidAssignment) {
                previewHtml += '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Has invalid current assignment</small>';
            }
            previewHtml += '</div>';

            if (lecturerId) {
                previewHtml += '<div class="col-md-6">';
                previewHtml += '<h6><i class="fas fa-user-graduate me-2"></i>New HOD</h6>';
                previewHtml += `<p class="mb-1"><strong>${lecturerName}</strong></p>`;
                previewHtml += '<small class="text-success"><i class="fas fa-check me-1"></i>Will create/update user account</small>';
                previewHtml += '</div>';
            } else {
                previewHtml += '<div class="col-md-6">';
                previewHtml += '<h6><i class="fas fa-user-times me-2"></i>HOD Assignment</h6>';
                previewHtml += '<p class="mb-1"><span class="text-warning">Will be removed</span></p>';
                previewHtml += '<small class="text-muted"><i class="fas fa-info-circle me-1"></i>Department will have no HOD</small>';
                previewHtml += '</div>';
            }

            previewHtml += '</div>';
            previewHtml += '<hr class="my-2">';
            previewHtml += '<div class="text-center">';
            
            if (isInvalidAssignment && lecturerId) {
                previewHtml += `<p class="mb-0"><i class="fas fa-tools me-2"></i><strong>Action:</strong> Fix invalid assignment</p>`;
                previewHtml += '<small class="text-success">This will resolve the data integrity issue</small>';
            } else if (isInvalidAssignment && !lecturerId) {
                previewHtml += `<p class="mb-0"><i class="fas fa-times me-2"></i><strong>Action:</strong> Remove invalid assignment</p>`;
                previewHtml += '<small class="text-warning">This will clear the invalid HOD reference</small>';
            } else if (lecturerId) {
                previewHtml += `<p class="mb-0"><i class="fas fa-arrow-right me-2"></i><strong>Action:</strong> Assign HOD to department</p>`;
            } else {
                previewHtml += `<p class="mb-0"><i class="fas fa-times me-2"></i><strong>Action:</strong> Remove HOD from department</p>`;
            }
            
            previewHtml += '</div>';

            $('#previewText').html(previewHtml);
            $('#assignmentPreview').show();
        }

        function showAssignmentPreview() {
            updateAssignmentPreview();
        }

        function clearCurrentAssignment() {
            $('#currentAssignmentInfo').hide();
            $('#lecturerSelect').val('');
            updateAssignmentPreview();
        }

        function validateForm() {
            const departmentId = $('#departmentSelect').val();
            const lecturerId = $('#lecturerSelect').val();
            let isValid = true;

            // Validate department selection
            if (departmentId) {
                $('#departmentSelect').removeClass('is-invalid').addClass('is-valid');
                $('#departmentSelectFeedback')
                    .html('<i class="fas fa-check me-1"></i>Department selected successfully')
                    .removeClass('text-warning text-danger').addClass('text-success');
            } else {
                $('#departmentSelect').removeClass('is-valid').addClass('is-invalid');
                $('#departmentSelectFeedback')
                    .html('<i class="fas fa-exclamation-triangle me-1"></i>Please select a department')
                    .removeClass('text-success text-warning').addClass('text-danger');
                isValid = false;
            }

            // Validate lecturer selection (optional)
            if (lecturerId) {
                $('#lecturerSelect').removeClass('is-invalid').addClass('is-valid');
                const selectedLecturer = $('#lecturerSelect option:selected').text();
                $('#lecturerSelectFeedback')
                    .html(`<i class="fas fa-check me-1"></i>HOD selected: <strong>${selectedLecturer}</strong>`)
                    .removeClass('text-warning text-danger').addClass('text-success');
            } else {
                $('#lecturerSelect').removeClass('is-valid is-invalid');
                const lecturerCount = $('#lecturerSelect').find('option').length - 1;
                if (lecturerCount === 0) {
                    $('#lecturerSelectFeedback')
                        .html('<i class="fas fa-exclamation-triangle me-1"></i>No lecturers available in database')
                        .removeClass('text-success text-info').addClass('text-warning');
                } else {
                    $('#lecturerSelectFeedback')
                        .html('<i class="fas fa-info-circle me-1"></i>Optional - leave empty to remove HOD assignment')
                        .removeClass('text-success text-danger').addClass('text-info');
                }
            }

            // Update submit button state
            const submitBtn = $('#assignBtn');
            if (isValid) {
                submitBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
            } else {
                submitBtn.prop('disabled', true).removeClass('btn-primary').addClass('btn-secondary');
            }

            return isValid;
        }

        function resetForm() {
            $('#assignHodForm')[0].reset();
            $('#currentAssignmentInfo').hide();
            $('#assignmentPreview').hide();
            $('#departmentSearch').val('');
            $('#lecturerSearch').val('');
            $('.department-card').removeClass('selected');
            filterDepartments('');
            filterLecturers('');
            AppState.selectedDepartments.clear();
            
            if (AppState.isBulkMode) {
                disableBulkMode();
            }
        }

        function showAlert(type, message, duration = 5000) {
            const icon = type === 'success' ? 'check-circle' : 
                        type === 'warning' ? 'exclamation-triangle' : 
                        type === 'info' ? 'info-circle' : 'exclamation-circle';
            
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${icon} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('#alertContainer').html(alertHtml);
            
            if (duration > 0) {
                setTimeout(() => $('.alert').alert('close'), duration);
            }
        }

        function showLoading() {
            $("#loadingOverlay").fadeIn();
        }

        function hideLoading() {
            $("#loadingOverlay").fadeOut();
        }

        function loadData() {
            showLoading();
            
            Promise.all([
                loadDepartments(),
                loadLecturers(),
                loadStatistics(),
                loadAssignments()
            ])
            .then(() => {
                hideLoading();
                checkDataIntegrity();
                console.log('All data loaded successfully');
            })
            .catch(error => {
                hideLoading();
                console.error('Error loading data:', error);
                showAlert('danger', 'Failed to load data: ' + error);
            });
        }

        function loadDepartments() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'assign-hod.php?ajax=1&action=get_departments',
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .done(function(response) {
                    console.log('Departments API Response:', response);
                    
                    if (response.status === 'success') {
                        AppState.setDepartments(response.data);
                        populateDepartmentSelect(response.data);
                        resolve(response.data);
                    } else {
                        reject(response.message || 'Failed to load departments');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Departments API Error:', xhr, status, error);
                    reject('Departments API failed: ' + error);
                });
            });
        }

        function populateDepartmentSelect(departments) {
            const select = $('#departmentSelect');
            select.empty().append('<option value="">-- Select Department --</option>');
            
            if (departments && departments.length > 0) {
                departments.forEach(dept => {
                    const hodName = dept.hod_first_name && dept.hod_last_name ? 
                        `${dept.hod_first_name} ${dept.hod_last_name}` : '';
                    const selected = hodName ? ` (Current HOD: ${hodName})` : '';
                    
                    select.append(`<option value="${dept.id}" 
                        data-hod="${dept.hod_id || ''}" 
                        data-hod-name="${hodName}">
                        ${dept.name}${selected}
                    </option>`);
                });
            } else {
                select.append('<option value="" disabled>No departments available</option>');
            }
        }

        function loadLecturers() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'assign-hod.php?ajax=1&action=get_lecturers',
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .done(function(response) {
                    console.log('Lecturers API Response:', response);
                    
                    if (response.status === 'success') {
                        AppState.setLecturers(response.data);
                        resolve(response.data);
                    } else {
                        reject(response.message || 'Failed to load lecturers');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Lecturers API Error:', xhr, status, error);
                    reject('Lecturers API failed: ' + error);
                });
            });
        }

        function updateLecturerSelect(lecturers) {
            console.log('Updating lecturer select with:', lecturers);
            
            const select = $('#lecturerSelect');
            select.empty().append('<option value="">-- Select Lecturer --</option>');
            
            if (lecturers && lecturers.length > 0) {
                lecturers.forEach(lecturer => {
                    const displayName = lecturer.full_name || 
                                       `${lecturer.first_name} ${lecturer.last_name}`;
                    
                    select.append(`<option value="${lecturer.id}">${displayName}</option>`);
                });
                console.log(`Loaded ${lecturers.length} lecturers into dropdown`);
            } else {
                select.append('<option value="" disabled>No lecturers available</option>');
                console.warn('No lecturers available for dropdown');
            }
            
            select.trigger('change');
        }

        function loadStatistics() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'assign-hod.php?ajax=1&action=get_assignment_stats',
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .done(function(response) {
                    console.log('Statistics API Response:', response);
                    
                    if (response.status === 'success') {
                        const data = response.data;
                        Utils.animateValue('totalDepartments', data.total_departments || 0);
                        Utils.animateValue('assignedDepartments', data.assigned_departments || 0);
                        Utils.animateValue('totalLecturers', data.total_lecturers || 0);
                        Utils.animateValue('unassignedDepartments', data.unassigned_departments || 0);
                        resolve(response.data);
                    } else {
                        reject(response.message);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Statistics API Error:', xhr, status, error);
                    reject('Failed to load statistics: ' + error);
                });
            });
        }

        function loadAssignments() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: 'assign-hod.php?ajax=1&action=get_departments',
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .done(function(response) {
                    if (response.status === 'success') {
                        renderAssignments(response.data);
                        resolve(response.data);
                    } else {
                        reject(response.message);
                    }
                })
                .fail(function(xhr, status, error) {
                    reject('Failed to load assignments: ' + error);
                });
            });
        }

        function renderAssignments(departments) {
            const container = $('#assignmentsContainer');
            
            if (!departments || departments.length === 0) {
                container.html(`
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Departments Found</h5>
                        <p class="text-muted">No departments are available for HOD assignment.</p>
                        <button class="btn btn-primary" onclick="loadData()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh Data
                        </button>
                    </div>
                `);
                return;
            }

            let html = '';
            const filteredDepts = filterAssignmentsByStatus(departments);
            
            filteredDepts.forEach(dept => {
                const hasInvalidAssignment = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');
                const cardClass = hasInvalidAssignment ? 'assignment-card invalid' : 
                                (dept.hod_id ? 'assignment-card assigned' : 'assignment-card unassigned');
                const statusIcon = hasInvalidAssignment ? 'fas fa-exclamation-circle text-danger' : 
                                 (dept.hod_id ? 'fas fa-user-check text-success' : 'fas fa-exclamation-triangle text-warning');
                const statusText = hasInvalidAssignment ? 'Invalid' : (dept.hod_id ? 'Assigned' : 'Unassigned');
                const statusColor = hasInvalidAssignment ? 'danger' : (dept.hod_id ? 'success' : 'warning');
                const isSelected = AppState.selectedDepartments.has(dept.id.toString());

                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="card ${cardClass} h-100 department-card ${isSelected ? 'selected' : ''}" 
                             data-department-id="${dept.id}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title text-primary mb-0">${dept.name}</h6>
                                    <span class="badge bg-${statusColor}">
                                        <i class="${statusIcon} me-1"></i>${statusText}
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Current HOD:</small>
                                    <div class="${dept.hod_id ? (hasInvalidAssignment ? 'text-danger' : 'text-success') : 'text-warning'} fw-semibold">
                                        ${hasInvalidAssignment ? 'INVALID ASSIGNMENT' : (dept.hod_name || 'Not Assigned')}
                                    </div>
                                    ${hasInvalidAssignment ? 
                                        '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>HOD ID exists but lecturer not found</small>' : ''}
                                </div>

                                <div class="mb-3">
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar bg-${statusColor}" role="progressbar" 
                                             style="width: ${hasInvalidAssignment ? '50%' : (dept.hod_id ? '100%' : '0%')}">
                                        </div>
                                    </div>
                                </div>

                                <button class="btn btn-sm ${hasInvalidAssignment ? 'btn-outline-danger' : 'btn-outline-primary'} w-100 selectDepartment"
                                        data-id="${dept.id}"
                                        data-name="${dept.name}"
                                        data-hod="${dept.hod_id || ''}"
                                        data-hod-name="${dept.hod_name || ''}"
                                        data-invalid="${hasInvalidAssignment}">
                                    <i class="fas fa-${hasInvalidAssignment ? 'exclamation-triangle' : 'mouse-pointer'} me-1"></i>
                                    ${hasInvalidAssignment ? 'Fix Assignment' : 'Select for Assignment'}
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.html(html);
            setupAssignmentCardInteractions();
        }

        function filterAssignmentsByStatus(departments) {
            const status = $('#statusFilter').val();
            
            if (status === 'all') return departments;
            
            return departments.filter(dept => {
                const hasInvalidAssignment = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');
                
                switch (status) {
                    case 'assigned': return dept.hod_id && !hasInvalidAssignment;
                    case 'unassigned': return !dept.hod_id;
                    case 'invalid': return hasInvalidAssignment;
                    default: return true;
                }
            });
        }

        function filterAssignments() {
            renderAssignments(AppState.departments);
        }

        function setupAssignmentCardInteractions() {
            // Select department for assignment
            $('.selectDepartment').on('click', function() {
                const deptId = $(this).data('id');
                const deptName = $(this).data('name');
                const hodId = $(this).data('hod');
                const hodName = $(this).data('hod-name');
                const isInvalid = $(this).data('invalid');

                if (AppState.isBulkMode) {
                    // Toggle selection in bulk mode
                    if (AppState.selectedDepartments.has(deptId.toString())) {
                        AppState.selectedDepartments.delete(deptId.toString());
                        $(this).closest('.department-card').removeClass('selected');
                    } else {
                        AppState.selectedDepartments.add(deptId.toString());
                        $(this).closest('.department-card').addClass('selected');
                    }
                    updateBulkModeUI();
                } else {
                    // Single selection mode
                    $('.department-card').removeClass('selected');
                    $(this).closest('.department-card').addClass('selected');

                    $('#departmentSelect').val(deptId);
                    $('#lecturerSelect').val(hodId);

                    if (hodId && hodName && !isInvalid) {
                        $('#currentAssignmentInfo').show();
                        $('#currentHodName').text(hodName);
                    } else if (isInvalid) {
                        $('#currentAssignmentInfo').hide();
                        showAlert('warning', `Department <strong>${deptName}</strong> has an invalid HOD assignment that needs to be fixed.`);
                    } else {
                        $('#currentAssignmentInfo').hide();
                    }

                    // Scroll to form
                    $('html, body').animate({
                        scrollTop: $('#assignHodForm').offset().top - 100
                    }, 500);

                    const alertType = isInvalid ? 'warning' : 'info';
                    const alertMessage = isInvalid ?
                        `Selected department with invalid assignment: <strong>${deptName}</strong>` :
                        `Selected department: <strong>${deptName}</strong>`;

                    showAlert(alertType, alertMessage);
                    validateForm();
                }
            });

            // Hover effects
            $('.department-card').hover(
                function() {
                    if (!$(this).hasClass('selected')) {
                        $(this).addClass('shadow');
                    }
                },
                function() {
                    if (!$(this).hasClass('selected')) {
                        $(this).removeClass('shadow');
                    }
                }
            );
        }

        function handleDepartmentSubmit(e) {
            e.preventDefault();

            if (AppState.isBulkMode && AppState.selectedDepartments.size > 0) {
                handleBulkAssignment();
                return;
            }

            const departmentId = $('#departmentSelect').val();
            const hodId = $('#lecturerSelect').val();

            if (!departmentId) {
                showAlert('warning', 'Please select a department');
                $('#departmentSelect').focus();
                return;
            }

            const department = AppState.departments.find(dept => dept.id == departmentId);
            const isInvalidAssignment = department.hod_id && (!department.hod_name || department.hod_name === 'Not Assigned');

            // Confirmation for critical actions
            if (isInvalidAssignment && !hodId) {
                if (!confirm('This department has an invalid HOD assignment. Removing the HOD will clear this invalid reference. Continue?')) {
                    return;
                }
            }

            submitAssignment(departmentId, hodId);
        }

        function submitAssignment(departmentId, hodId, isBulk = false) {
            showLoading();
            const submitBtn = $('#assignBtn');
            const originalText = submitBtn.html();
            
            if (isBulk) {
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing Bulk Assignment...');
            } else {
                submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
            }
            
            submitBtn.prop('disabled', true);

            $.post('assign-hod.php?ajax=1&action=assign_hod', {
                department_id: departmentId,
                hod_id: hodId,
                csrf_token: window.csrfToken
            })
            .done(function(response) {
                if (response.status === 'success') {
                    const message = isBulk ? 
                        'Bulk assignment completed successfully!' : 
                        response.message || 'HOD assignment updated successfully!';
                    showAlert('success', message);
                    loadData();
                    if (!isBulk) {
                        resetForm();
                    }
                } else {
                    showAlert('danger', response.message || 'Failed to assign HOD');
                }
            })
            .fail(function(xhr, status, error) {
                let errorMessage = 'Failed to process HOD assignment. Please try again.';
                if (xhr.status === 400) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        errorMessage = errorResponse.message || errorMessage;
                    } catch (e) {
                        // Use default error message
                    }
                }
                showAlert('danger', errorMessage);
            })
            .always(function() {
                hideLoading();
                submitBtn.html(originalText).prop('disabled', false);
            });
        }

        function checkDataIntegrity() {
            const invalidAssignments = AppState.departments.filter(dept =>
                dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
            );

            if (invalidAssignments.length > 0) {
                const departmentNames = invalidAssignments.map(dept => dept.name).join(', ');
                const alertHtml = `
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Data Integrity Issue:</strong> Found ${invalidAssignments.length} department(s) with invalid HOD assignments.
                        <br><small class="text-muted">Affected departments: ${departmentNames}</small>
                        <br><small class="text-muted">These departments have hod_id values pointing to non-HOD users or missing records.</small>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                $('#alertContainer').append(alertHtml);
                $('#fixInvalidBtn').show();
            } else {
                $('#fixInvalidBtn').hide();
            }
        }

        function fixInvalidAssignments() {
            const invalidDepts = AppState.departments.filter(dept =>
                dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
            );

            if (invalidDepts.length === 0) {
                showAlert('info', 'No invalid assignments found.');
                return;
            }

            if (confirm(`Found ${invalidDepts.length} department(s) with invalid HOD assignments.\n\nThese departments have hod_id values pointing to users who are not HODs or missing lecturer records.\n\nFix them by clearing the invalid HOD assignments?`)) {
                showLoading();

                let fixedCount = 0;
                let errorCount = 0;

                const fixNext = (index) => {
                    if (index >= invalidDepts.length) {
                        hideLoading();
                        if (errorCount === 0) {
                            showAlert('success', `Successfully fixed ${fixedCount} invalid HOD assignment(s)!`);
                        } else {
                            showAlert('warning', `Fixed ${fixedCount} assignment(s), but ${errorCount} failed. Please check the console for details.`);
                        }
                        loadData();
                        return;
                    }

                    const dept = invalidDepts[index];
                    console.log(`Fixing department: ${dept.name} (ID: ${dept.id})`);

                    $.post('assign-hod.php?ajax=1&action=assign_hod', {
                        department_id: dept.id,
                        hod_id: null,
                        csrf_token: window.csrfToken
                    })
                    .done(function(response) {
                        if (response.status === 'success') {
                            console.log(`✅ Fixed: ${dept.name}`);
                            fixedCount++;
                        } else {
                            console.error(`❌ Failed to fix ${dept.name}:`, response.message);
                            errorCount++;
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error(`❌ Request failed for ${dept.name}:`, xhr, status, error);
                        errorCount++;
                    })
                    .always(function() {
                        fixNext(index + 1);
                    });
                };

                fixNext(0);
            }
        }

        function enableBulkMode() {
            AppState.isBulkMode = true;
            $('#bulkModeBtn').removeClass('btn-outline-success').addClass('btn-success')
                .html('<i class="fas fa-layer-group me-2"></i>Bulk Mode Active');
            $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign to Selected');
            showAlert('info', 'Bulk mode activated. Select multiple departments and choose an HOD to assign to all selected departments.', 0);
        }

        function disableBulkMode() {
            AppState.isBulkMode = false;
            AppState.selectedDepartments.clear();
            $('#bulkModeBtn').removeClass('btn-success').addClass('btn-outline-success')
                .html('<i class="fas fa-layer-group me-2"></i>Bulk Mode');
            $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign HOD');
            $('.department-card').removeClass('selected');
            showAlert('info', 'Bulk mode disabled.');
        }

        function toggleBulkMode() {
            if (AppState.isBulkMode) {
                disableBulkMode();
            } else {
                enableBulkMode();
            }
        }

        function updateBulkModeUI() {
            const selectedCount = AppState.selectedDepartments.size;
            if (selectedCount > 0) {
                $('#assignBtn').html(`<i class="fas fa-save me-2"></i>Assign to ${selectedCount} Departments`);
            } else {
                $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign to Selected');
            }
        }

        function handleBulkAssignment() {
            const hodId = $('#lecturerSelect').val();
            const selectedCount = AppState.selectedDepartments.size;

            if (!hodId) {
                showAlert('warning', 'Please select an HOD for bulk assignment');
                return;
            }

            if (confirm(`Assign the selected HOD to ${selectedCount} department(s)?`)) {
                showLoading();
                
                let processed = 0;
                let successCount = 0;
                let errorCount = 0;

                AppState.selectedDepartments.forEach(deptId => {
                    $.post('assign-hod.php?ajax=1&action=assign_hod', {
                        department_id: deptId,
                        hod_id: hodId,
                        csrf_token: window.csrfToken
                    })
                    .done(function(response) {
                        if (response.status === 'success') {
                            successCount++;
                        } else {
                            errorCount++;
                        }
                    })
                    .fail(function() {
                        errorCount++;
                    })
                    .always(function() {
                        processed++;
                        if (processed === selectedCount) {
                            hideLoading();
                            if (errorCount === 0) {
                                showAlert('success', `Successfully assigned HOD to ${successCount} department(s)!`);
                            } else {
                                showAlert('warning', `Assigned HOD to ${successCount} department(s), but ${errorCount} failed.`);
                            }
                            loadData();
                            disableBulkMode();
                        }
                    });
                });
            }
        }

        function exportAssignments() {
            const assignments = AppState.departments.map(dept => ({
                'Department ID': dept.id,
                'Department Name': dept.name,
                'HOD Name': dept.hod_name || 'Not Assigned',
                'HOD Email': dept.hod_email || '',
                'Status': dept.hod_id ? 'Assigned' : 'Unassigned',
                'Last Updated': dept.updated_at || 'N/A'
            }));

            Utils.exportToCSV(assignments, `hod-assignments-${new Date().toISOString().split('T')[0]}.csv`);
            showAlert('success', 'Assignments exported successfully!');
        }

        function showHelp() {
            const helpHtml = `
                <div class="modal fade" id="helpModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title fw-bold">
                                    <i class="fas fa-question-circle me-2"></i>Help & Shortcuts
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts</h6>
                                        <ul class="list-unstyled">
                                            <li><kbd>Ctrl+R</kbd> <span class="text-muted">Refresh all data</span></li>
                                            <li><kbd>Ctrl+S</kbd> <span class="text-muted">Submit assignment form</span></li>
                                            <li><kbd>Ctrl+B</kbd> <span class="text-muted">Toggle bulk mode</span></li>
                                            <li><kbd>Esc</kbd> <span class="text-muted">Reset form / Exit bulk mode</span></li>
                                        </ul>

                                        <h6><i class="fas fa-search me-2"></i>Search Features</h6>
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success me-2"></i>Real-time filtering</li>
                                            <li><i class="fas fa-eye me-2"></i>Live assignment preview</li>
                                            <li><i class="fas fa-chart-bar me-2"></i>Statistics dashboard</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><i class="fas fa-info-circle me-2"></i>How to Use</h6>
                                        <ol class="small">
                                            <li>Search or select a department from the cards below</li>
                                            <li>Choose a lecturer to assign as HOD (optional)</li>
                                            <li>Review the assignment preview</li>
                                            <li>Click "Assign HOD" to save changes</li>
                                        </ol>

                                        <h6><i class="fas fa-cogs me-2"></i>Features</h6>
                                        <ul class="list-unstyled small">
                                            <li><i class="fas fa-user-plus text-success me-2"></i>Auto-creates user accounts</li>
                                            <li><i class="fas fa-shield-alt text-info me-2"></i>CSRF protection</li>
                                            <li><i class="fas fa-sync-alt text-warning me-2"></i>Real-time updates</li>
                                            <li><i class="fas fa-mobile-alt text-primary me-2"></i>Mobile responsive</li>
                                        </ul>

                                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Data Integrity</h6>
                                        <ul class="list-unstyled small">
                                            <li><i class="fas fa-times text-danger me-2"></i>Detects invalid assignments</li>
                                            <li><i class="fas fa-tools text-warning me-2"></i>Provides fix options</li>
                                            <li><i class="fas fa-chart-line text-info me-2"></i>Shows data health status</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('#helpModal').remove();
            $('body').append(helpHtml);

            const modal = new bootstrap.Modal(document.getElementById('helpModal'));
            modal.show();
        }

        // Make functions globally available
        window.loadData = loadData;
        window.fixInvalidAssignments = fixInvalidAssignments;
        window.exportAssignments = exportAssignments;
        window.showHelp = showHelp;
        window.toggleSidebar = toggleSidebar;
        window.enableBulkMode = enableBulkMode;
        window.toggleBulkMode = toggleBulkMode;
        window.filterAssignments = filterAssignments;

        // Debug function
        window.debugLecturerSelection = function() {
            console.log('=== DEBUG LECTURER SELECTION ===');
            console.log('All Lecturers:', AppState.lecturers);
            console.log('Lecturer Select Element:', $('#lecturerSelect'));
            console.log('Selected Lecturer Value:', $('#lecturerSelect').val());
            console.log('Lecturer Options:', $('#lecturerSelect').find('option').length);
            
            if (AppState.lecturers && AppState.lecturers.length > 0) {
                console.log('First lecturer:', AppState.lecturers[0]);
            } else {
                console.log('No lecturers loaded in AppState');
            }
        };
    </script>
</body>
</html>