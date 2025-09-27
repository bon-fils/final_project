<?php
/**
 * HOD Assignment System - Frontend Interface
 * Streamlined, secure interface for managing Head of Department assignments
 *
 * @version 2.1.0
 * @author RP System Development Team
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <title>Assign HOD | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
            --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(to right, #0066cc, #003366);
            min-height: 100vh;
            min-height: 100dvh; /* Dynamic viewport height for mobile */
            font-family: 'Segoe UI', 'Roboto', sans-serif;
            margin: 0;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>');
            pointer-events: none;
            z-index: -1;
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

        .sidebar .logo h5 {
            color: #333;
            font-weight: 700;
            margin: 0;
            font-size: 1.2rem;
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
            min-height: 100dvh;
        }

        .topbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .dashboard-card {
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: none;
            transition: var(--transition);
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            position: relative;
            overflow: hidden;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .dashboard-card .card-body {
            padding: 25px;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: var(--transition);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
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

        .stats-card {
            text-align: center;
            padding: 20px;
        }

        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stats-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .assignment-card {
            border-left: 4px solid var(--primary-color);
            background-color: #ffffff;
            border: 1px solid rgba(0,0,0,0.125);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .assignment-card.assigned {
            border-left-color: var(--success-color);
            background-color: #ffffff;
        }

        .assignment-card.unassigned {
            border-left-color: var(--warning-color);
            background-color: #ffffff;
        }

        .assignment-card.invalid {
            border-left-color: var(--danger-color);
            background-color: #ffffff;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .assignment-card.invalid:hover {
            background-color: #ffffff;
            box-shadow: 0 4px 8px rgba(220, 53, 69, 0.1);
        }

        .assignment-card:hover {
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .invalid-assignment-warning {
            background: rgba(248, 215, 218, 0.9);
            backdrop-filter: blur(5px);
            border: 1px solid #dc3545;
            color: #721c24;
        }

        .data-integrity-alert {
            background: rgba(255, 243, 205, 0.9);
            backdrop-filter: blur(5px);
            border: 1px solid #ffc107;
            color: #856404;
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

        /* Desktop Styles - Sidebar always visible */
        @media (min-width: 769px) {
            .sidebar {
                width: 280px;
                height: 100vh;
                height: 100dvh;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                transform: translateX(0);
            }
            .main-content {
                margin-left: 280px;
                padding: 20px;
                width: calc(100vw - 280px);
                max-width: calc(100vw - 280px);
                min-height: 100vh;
                min-height: 100dvh;
            }
            .sidebar-toggle {
                display: none !important;
            }
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

        .search-highlight {
            background-color: rgba(255, 243, 205, 0.8);
            padding: 2px 4px;
            border-radius: 3px;
        }

        .assignment-preview {
            background: rgba(227, 242, 253, 0.9);
            backdrop-filter: blur(5px);
            border: 1px solid #2196f3;
        }

        .department-card {
            transition: all 0.3s ease;
            cursor: pointer;
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(0,0,0,0.125);
        }

        .department-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            background-color: rgba(255, 255, 255, 0.95);
        }

        .department-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }

        /* Improve text contrast */
        .department-card .card-title {
            color: #2c3e50;
            font-weight: 600;
        }

        .department-card .text-muted {
            color: #6c757d !important;
        }

        .department-card .fw-semibold {
            color: #495057;
            font-weight: 600;
        }

        .lecturer-option {
            border-bottom: 1px solid rgba(238, 238, 238, 0.8);
            padding: 8px 0;
        }

        .lecturer-option:last-child {
            border-bottom: none;
        }

        .validation-feedback {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stats-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .form-floating > .form-control {
            height: calc(3.5rem + 2px);
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
        }

        .form-floating > label {
            padding: 1rem 0.75rem;
            font-weight: 500;
        }

        @media (max-width: 576px) {
            .stats-card h3 {
                font-size: 1.8rem;
            }

            .card-body {
                padding: 1rem;
            }

            .btn-group {
                flex-direction: column;
                gap: 0.5rem;
            }

            .main-content {
                padding: 10px;
            }

            .stats-card {
                padding: 15px;
            }

            .assignment-card {
                margin-bottom: 1rem;
            }

            .stats-card .card-body {
                padding: 20px 15px;
            }
        }

        /* Mobile navigation toggle button styles */
        .mobile-menu-toggle {
            transition: all 0.3s ease;
            z-index: 1050;
            border-radius: 8px;
            width: 50px;
            height: 50px;
            box-shadow: var(--shadow-medium);
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-heavy);
        }

        /* Smooth sidebar transitions */
        .sidebar {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content {
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Fullscreen specific styles */
        .sidebar:fullscreen,
        .sidebar:-webkit-full-screen,
        .sidebar:-moz-full-screen {
            width: 280px !important;
            height: 100vh !important;
        }

        .main-content:fullscreen,
        .main-content:-webkit-full-screen,
        .main-content:-moz-full-screen {
            margin-left: 280px !important;
            width: calc(100vw - 280px) !important;
            height: 100vh !important;
        }

        /* Ensure proper display in all modes */
        html, body {
            overflow-x: hidden;
        }

        /* Fix for any potential layout shifts */
        * {
            box-sizing: border-box;
        }

        /* Ensure proper display in all modes */
        html, body {
            overflow-x: hidden;
        }

        /* Fix for any potential layout shifts */
        * {
            box-sizing: border-box;
        }

        /* Ensure minimum touch target size on mobile */
        @media (max-width: 768px) {
            .btn, .form-control, .form-select, .card {
                min-height: 44px;
            }
        }

        /* Ensure proper spacing on all devices */
        .container-fluid {
            padding-left: 15px;
            padding-right: 15px;
        }

        @media (min-width: 769px) {
            .container-fluid {
                padding-left: 20px;
                padding-right: 20px;
            }
        }

        /* Ensure minimum touch target size on mobile */
        @media (max-width: 768px) {
            .btn, .form-control, .form-select, .card {
                min-height: 44px;
            }
        }

        /* Improve data visibility */
        .assignments-container {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }

        /* Better contrast for badges and status indicators */
        .badge {
            font-weight: 600;
            padding: 6px 10px;
            border-radius: 6px;
        }

        /* Improve button visibility */
        .selectDepartment {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border: 2px solid #dee2e6;
            color: #495057;
            font-weight: 500;
        }

        .selectDepartment:hover {
            background-color: rgba(248, 249, 250, 0.9);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .stats-card:nth-child(1) { animation-delay: 0.1s; }
        .stats-card:nth-child(2) { animation-delay: 0.2s; }
        .stats-card:nth-child(3) { animation-delay: 0.3s; }
        .stats-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="mb-2">Loading HOD Assignment</h5>
            <p class="mb-0">Please wait while we fetch the latest data...</p>
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
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <h2 class="mb-0" style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                        <i class="fas fa-user-tie me-3"></i>Assign Head of Department
                    </h2>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <div class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-user-shield me-1"></i>Admin
                    </div>
                    <button class="btn btn-outline-warning btn-sm" onclick="fixInvalidAssignments()" title="Fix invalid assignments" id="fixInvalidBtn" style="display: none;">
                        <i class="fas fa-tools me-1"></i>Fix Invalid
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="testAPIs()" title="Test API endpoints">
                        <i class="fas fa-bug me-1"></i>Debug
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
        <div id="alertContainer" class="mb-4"></div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-building text-primary"></i>
                    <h3 id="totalDepartments">0</h3>
                    <p>Total Departments</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-user-check text-success"></i>
                    <h3 id="assignedDepartments">0</h3>
                    <p>Assigned HODs</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-chalkboard-teacher text-info"></i>
                    <h3 id="totalLecturers">0</h3>
                    <p>Available Lecturers</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    <h3 id="unassignedDepartments">0</h3>
                    <p>Unassigned Departments</p>
                </div>
            </div>
        </div>

        <!-- Assignment Form -->
        <div class="card border-0 shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0 fw-bold">
                    <i class="fas fa-plus-circle me-2"></i>
                    HOD Assignment Form
                </h5>
            </div>
            <div class="card-body">
                <form id="assignHodForm">
                    <input type="hidden" id="departmentId" name="department_id">

                    <!-- Search and Filter Row -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="departmentSearch" class="form-label">Search Departments</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="departmentSearch" placeholder="Type to search departments...">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="lecturerSearch" class="form-label">Search Lecturers</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" class="form-control" id="lecturerSearch" placeholder="Type to search lecturers...">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departmentSelect" class="form-label">
                                Department *
                                <small class="text-muted">(Click on a card below to select)</small>
                            </label>
                            <select class="form-select" id="departmentSelect" name="department_id" required>
                                <option value="">-- Select Department --</option>
                            </select>
                            <div id="departmentSelectFeedback" class="form-text"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lecturerSelect" class="form-label">
                                Head of Department
                                <small class="text-muted">(Optional - leave empty to remove HOD)</small>
                            </label>
                            <select class="form-select" id="lecturerSelect" name="hod_id">
                                <option value="">-- Select Lecturer --</option>
                            </select>
                            <div id="lecturerSelectFeedback" class="form-text"></div>
                        </div>
                    </div>

                    <!-- Current Assignment Info -->
                    <div class="alert alert-info" id="currentAssignmentInfo" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Current Assignment:</strong> <span id="currentHodName"></span>
                        <button type="button" class="btn btn-sm btn-outline-info float-end" onclick="clearCurrentAssignment()">
                            <i class="fas fa-times me-1"></i>Clear
                        </button>
                    </div>

                    <!-- Assignment Preview -->
                    <div class="alert alert-warning" id="assignmentPreview" style="display: none;">
                        <i class="fas fa-eye me-2"></i>
                        <strong>Assignment Preview:</strong>
                        <span id="previewText"></span>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="assignBtn">
                            <i class="fas fa-save me-2"></i>Assign HOD
                        </button>
                        <button type="button" class="btn btn-secondary" id="resetFormBtn">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                        <button type="button" class="btn btn-info" id="previewBtn" onclick="showAssignmentPreview()">
                            <i class="fas fa-eye me-2"></i>Preview
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Assignments -->
        <div class="card border-0 shadow">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-list me-2"></i>Current HOD Assignments
                    </h5>
                    <button class="btn btn-outline-light btn-sm" id="refreshAssignments">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
            <div class="card-body bg-white">
                <div id="assignmentsContainer" class="row g-3 assignments-container">
                    <!-- Assignments will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let allDepartments = [];
        let allLecturers = [];

        // Mobile sidebar toggle function
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

        // DOM Ready
        $(document).ready(function() {
            loadData();
            setupEventHandlers();
            setupFullscreenHandling();
        });

        function setupEventHandlers() {
            // Department form submission
            $('#assignHodForm').on('submit', handleDepartmentSubmit);

            // Department selection change
            $('#departmentSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
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
            });

            // Lecturer selection change
            $('#lecturerSelect').on('change', function() {
                updateAssignmentPreview();
            });

            // Search functionality with debouncing
            let departmentSearchTimeout, lecturerSearchTimeout;

            $('#departmentSearch').on('input', function() {
                clearTimeout(departmentSearchTimeout);
                departmentSearchTimeout = setTimeout(() => {
                    filterDepartments($(this).val());
                }, 300);
            });

            $('#lecturerSearch').on('input', function() {
                clearTimeout(lecturerSearchTimeout);
                lecturerSearchTimeout = setTimeout(() => {
                    filterLecturers($(this).val());
                }, 300);
            });

            // Reset form
            $('#resetFormBtn').on('click', function() {
                $('#assignHodForm')[0].reset();
                $('#currentAssignmentInfo').hide();
                $('#assignmentPreview').hide();
                $('#departmentSearch').val('');
                $('#lecturerSearch').val('');
                $('.department-card').removeClass('selected');
                filterDepartments('');
                filterLecturers('');
            });

            // Refresh assignments
            $('#refreshAssignments').on('click', loadAssignments);

            // Form validation
            $('#departmentSelect').on('change', validateForm);
            $('#lecturerSelect').on('change', validateForm);

            // Keyboard shortcuts
            $(document).on('keydown', function(e) {
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
                    $('#resetFormBtn').click();
                }
            });

            // Auto-focus search on card click
            $(document).on('click', '.department-card', function() {
                $('#departmentSearch').focus();
            });
        }


        function setupFullscreenHandling() {
            // Function to check if we're in fullscreen
            function isFullscreen() {
                return !!(document.fullscreenElement || document.webkitFullscreenElement ||
                        document.mozFullScreenElement || document.msFullscreenElement);
            }

            // Function to adjust layout for fullscreen
            function adjustForFullscreen() {
                const fullscreen = isFullscreen();
                const windowWidth = $(window).width();

                if (fullscreen) {
                    // Fullscreen mode adjustments
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
                    } else {
                        $('.sidebar').css({
                            'width': '100%',
                            'height': '100vh',
                            'position': 'fixed'
                        });
                        $('.main-content').css({
                            'margin-left': sidebarCollapsed ? '0' : '0',
                            'width': '100vw',
                            'height': '100vh'
                        });
                    }
                } else {
                    // Normal mode - use dvh for mobile browsers
                    const heightUnit = windowWidth <= 768 ? '100dvh' : '100vh';

                    $('.sidebar').css({
                        'height': heightUnit
                    });
                    $('.main-content').css({
                        'min-height': heightUnit
                    });
                }
            }

            // Listen for fullscreen changes
            $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
                setTimeout(adjustForFullscreen, 100);
            });

            // Also listen for orientation changes which can affect fullscreen
            $(window).on('orientationchange', function() {
                setTimeout(adjustForFullscreen, 200);
            });

            // Initial adjustment
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

                if (option.val() !== '') { // Don't hide the placeholder
                    option.toggle(shouldShow);
                    if (shouldShow) visibleCount++;
                }
            });

            // Update feedback message
            if (searchTerm) {
                if (visibleCount === 0) {
                    $('#departmentSelectFeedback')
                        .html('<i class="fas fa-exclamation-triangle me-1"></i>No departments found matching "<strong>' + searchTerm + '</strong>"')
                        .removeClass('text-success').addClass('text-warning');
                } else {
                    $('#departmentSelectFeedback')
                        .html('<i class="fas fa-check me-1"></i>Found ' + visibleCount + ' department' + (visibleCount !== 1 ? 's' : '') + ' matching "<strong>' + searchTerm + '</strong>"')
                        .removeClass('text-warning').addClass('text-success');
                }
            } else {
                $('#departmentSelectFeedback').text('').removeClass('text-success text-warning');
            }
        }

        function filterLecturers(searchTerm) {
            const select = $('#lecturerSelect');
            const options = select.find('option');
            let visibleCount = 0;

            options.each(function() {
                const option = $(this);
                const text = option.text().toLowerCase();
                const shouldShow = !searchTerm || text.includes(searchTerm.toLowerCase());

                if (option.val() !== '') { // Don't hide the placeholder
                    option.toggle(shouldShow);
                    if (shouldShow) visibleCount++;
                }
            });

            // Update feedback message
            if (searchTerm) {
                if (visibleCount === 0) {
                    $('#lecturerSelectFeedback')
                        .html('<i class="fas fa-exclamation-triangle me-1"></i>No lecturers found matching "<strong>' + searchTerm + '</strong>"')
                        .removeClass('text-success').addClass('text-warning');
                } else {
                    $('#lecturerSelectFeedback')
                        .html('<i class="fas fa-check me-1"></i>Found ' + visibleCount + ' lecturer' + (visibleCount !== 1 ? 's' : '') + ' matching "<strong>' + searchTerm + '</strong>"')
                        .removeClass('text-warning').addClass('text-success');
                }
            } else {
                $('#lecturerSelectFeedback').text('').removeClass('text-success text-warning');
            }
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

            // Add action summary
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
                $('#lecturerSelectFeedback')
                    .html('<i class="fas fa-check me-1"></i>HOD selected - user account will be created/updated')
                    .removeClass('text-warning text-danger').addClass('text-success');
            } else {
                $('#lecturerSelect').removeClass('is-valid is-invalid');
                $('#lecturerSelectFeedback')
                    .html('<i class="fas fa-info-circle me-1"></i>Optional - leave empty to remove HOD assignment')
                    .removeClass('text-success text-danger').addClass('text-info');
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

        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alertContainer').html(alertHtml);
            setTimeout(() => $('.alert').alert('close'), 5000);
        }

        function showLoading() {
            $("#loadingOverlay").fadeIn();
        }

        function hideLoading() {
            $("#loadingOverlay").fadeOut();
        }

        function loadData() {
            showLoading();

            // Load all data in parallel
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

        // Debug function to test API endpoints
        function testAPIs() {
            console.log('Testing API endpoints...');

            $.get('api/assign-hod-api.php?action=get_departments')
                .done(function(response) {
                    console.log('Departments API test:', response);
                    if (response.status === 'success') {
                        console.log('✅ Departments loaded:', response.count, 'departments');
                    } else {
                        console.error('❌ Departments API error:', response.message);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('❌ Departments API failed:', xhr, status, error);
                });

            $.get('api/assign-hod-api.php?action=get_lecturers')
                .done(function(response) {
                    console.log('Lecturers API test:', response);
                    if (response.status === 'success') {
                        console.log('✅ Lecturers loaded:', response.count, 'lecturers');
                    } else {
                        console.error('❌ Lecturers API error:', response.message);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('❌ Lecturers API failed:', xhr, status, error);
                });
        }

        function checkDataIntegrity() {
            const invalidAssignments = allDepartments.filter(dept =>
                dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
            );

            if (invalidAssignments.length > 0) {
                const departmentNames = invalidAssignments.map(dept => dept.name || dept.dept_name).join(', ');
                const alertHtml = `
                    <div class="alert data-integrity-alert alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Data Integrity Issue:</strong> Found ${invalidAssignments.length} department(s) with invalid HOD assignments.
                        <br><small class="text-muted">Affected departments: ${departmentNames}</small>
                        <br><small class="text-muted">These departments have hod_id values pointing to non-HOD users or missing records.</small>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                $('#alertContainer').append(alertHtml);

                // Show fix button
                $('#fixInvalidBtn').show();

                // Log details for debugging
                console.warn('Invalid HOD assignments found:', invalidAssignments);
                console.log('These departments have hod_id set but no valid HOD user/lecturer records');
            } else {
                // Hide fix button if no invalid assignments
                $('#fixInvalidBtn').hide();
            }
        }

        function loadDepartments() {
            return new Promise((resolve, reject) => {
                $.get('api/assign-hod-api.php?action=get_departments')
                    .done(function(response) {
                        console.log('Departments API response:', response);

                        if (response.status === 'success') {
                            allDepartments = response.data;
                            const select = $('#departmentSelect');
                            select.empty().append('<option value="">-- Select Department --</option>');

                            if (response.data && response.data.length > 0) {
                                response.data.forEach(dept => {
                                    const currentHod = dept.hod_name && dept.hod_name !== 'Not Assigned' ? dept.hod_name : null;
                                    const selected = currentHod ? ' (Current HOD: ' + currentHod + ')' : '';
                                    const isInvalid = dept.hod_id && (!currentHod || currentHod === 'Not Assigned');

                                    select.append(`<option value="${dept.id}" data-hod="${dept.hod_id || ''}" data-hod-name="${currentHod || ''}" data-invalid="${isInvalid ? 'true' : 'false'}">${dept.dept_name || dept.name}${selected}</option>`);
                                });
                            } else {
                                select.append('<option value="" disabled>No departments available</option>');
                            }

                            resolve(response.data);
                        } else {
                            console.error('Departments API error:', response);
                            // Try fallback with direct database query
                            loadDepartmentsFallback().then(resolve).catch(reject);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Departments API failed:', xhr, status, error);
                        // Try fallback with direct database query
                        loadDepartmentsFallback().then(resolve).catch(reject);
                    });
            });
        }

        function loadDepartmentsFallback() {
            return new Promise((resolve, reject) => {
                console.log('Attempting fallback department loading...');
                $.get('api/assign-hod-api.php?action=get_departments&fallback=1')
                    .done(function(response) {
                        console.log('Fallback departments response:', response);
                        if (response.status === 'success') {
                            resolve(response.data);
                        } else {
                            reject('Fallback also failed: ' + (response.message || 'Unknown error'));
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Fallback departments failed:', xhr, status, error);
                        reject('Both API and fallback failed: ' + error);
                    });
            });
        }

        function loadLecturers() {
            return new Promise((resolve, reject) => {
                $.get('api/assign-hod-api.php?action=get_lecturers')
                    .done(function(response) {
                        console.log('Lecturers API response:', response);

                        if (response.status === 'success') {
                            allLecturers = response.data;
                            const select = $('#lecturerSelect');
                            select.empty().append('<option value="">-- Select Lecturer --</option>');

                            if (response.data && response.data.length > 0) {
                                response.data.forEach(lecturer => {
                                    const displayName = lecturer.display_name || lecturer.full_name || `${lecturer.first_name} ${lecturer.last_name}`;
                                    select.append(`<option value="${lecturer.id}">${displayName}</option>`);
                                });
                            } else {
                                select.append('<option value="" disabled>No lecturers available</option>');
                            }

                            resolve(response.data);
                        } else {
                            console.error('Lecturers API error:', response);
                            // Try fallback with direct database query
                            loadLecturersFallback().then(resolve).catch(reject);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Lecturers API failed:', xhr, status, error);
                        // Try fallback with direct database query
                        loadLecturersFallback().then(resolve).catch(reject);
                    });
            });
        }

        function loadLecturersFallback() {
            return new Promise((resolve, reject) => {
                console.log('Attempting fallback lecturer loading...');
                $.get('api/assign-hod-api.php?action=get_lecturers&fallback=1')
                    .done(function(response) {
                        console.log('Fallback lecturers response:', response);
                        if (response.status === 'success') {
                            resolve(response.data);
                        } else {
                            reject('Fallback also failed: ' + (response.message || 'Unknown error'));
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Fallback lecturers failed:', xhr, status, error);
                        reject('Both API and fallback failed: ' + error);
                    });
            });
        }

        function loadStatistics() {
            return new Promise((resolve, reject) => {
                $.get('api/assign-hod-api.php?action=get_assignment_stats')
                    .done(function(response) {
                        if (response.status === 'success') {
                            const data = response.data;

                            // Animate numbers with improved animation
                            animateValue('totalDepartments', data.total_departments || 0);
                            animateValue('assignedDepartments', data.assigned_departments || 0);
                            animateValue('totalLecturers', data.total_lecturers || 0);
                            animateValue('unassignedDepartments', data.unassigned_departments || 0);

                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        reject('Failed to load statistics: ' + error);
                    });
            });
        }

        function animateValue(id, newValue, duration = 600) {
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
        }

        function animateNumber(selector, targetNumber, duration = 1000) {
            const element = $(selector);
            const startNumber = parseInt(element.text()) || 0;
            const startTime = Date.now();

            function updateNumber() {
                const currentTime = Date.now();
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Easing function for smooth animation
                const easeOut = 1 - Math.pow(1 - progress, 3);
                const currentNumber = Math.round(startNumber + (targetNumber - startNumber) * easeOut);

                element.text(currentNumber);

                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            }

            updateNumber();
        }

        function loadAssignments() {
            return new Promise((resolve, reject) => {
                $.get('api/assign-hod-api.php?action=get_departments')
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
            container.empty();

            if (departments.length === 0) {
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

            departments.forEach(dept => {
                // Check for invalid assignments (hod_id exists but no valid HOD name)
                const hasInvalidAssignment = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');
                const cardClass = hasInvalidAssignment ? 'assignment-card invalid' : (dept.hod_id ? 'assignment-card assigned' : 'assignment-card unassigned');
                const statusIcon = hasInvalidAssignment ? 'fas fa-exclamation-circle text-danger' : (dept.hod_id ? 'fas fa-user-check text-success' : 'fas fa-exclamation-triangle text-warning');
                const statusText = hasInvalidAssignment ? 'Invalid' : (dept.hod_id ? 'Assigned' : 'Unassigned');
                const statusColor = hasInvalidAssignment ? 'danger' : (dept.hod_id ? 'success' : 'warning');

                const cardHtml = `
                    <div class="col-md-6 col-lg-4">
                        <div class="card ${cardClass} h-100 department-card" data-department-id="${dept.id}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title text-primary mb-0">${dept.dept_name}</h6>
                                    <span class="badge bg-${statusColor}">${statusText}</span>
                                </div>

                                <div class="mb-3">
                                    <div class="row">
                                        <div class="col-8">
                                            <small class="text-muted d-block">Current HOD:</small>
                                            <div class="${dept.hod_id ? (hasInvalidAssignment ? 'text-danger' : 'text-success') : 'text-warning'} fw-semibold">
                                                ${hasInvalidAssignment ? 'INVALID ASSIGNMENT' : (dept.hod_name || 'Not Assigned')}
                                            </div>
                                            ${hasInvalidAssignment ? '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>HOD ID exists but lecturer not found</small>' : ''}
                                        </div>
                                        <div class="col-4 text-end">
                                            <small class="text-muted d-block">Programs:</small>
                                            <span class="badge bg-info">${dept.program_count || 0}</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar bg-${statusColor}" role="progressbar" style="width: ${hasInvalidAssignment ? '50%' : (dept.hod_id ? '100%' : '0%')}"></div>
                                    </div>
                                </div>

                                <button class="btn btn-sm ${hasInvalidAssignment ? 'btn-outline-danger' : 'btn-outline-primary'} w-100 selectDepartment"
                                        data-id="${dept.id}"
                                        data-name="${dept.dept_name}"
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
                container.append(cardHtml);
            });

            // Add event listeners to select buttons
            $('.selectDepartment').on('click', function() {
                const deptId = $(this).data('id');
                const deptName = $(this).data('name');
                const hodId = $(this).data('hod');
                const hodName = $(this).data('hod-name');
                const isInvalid = $(this).data('invalid');

                // Remove previous selection
                $('.department-card').removeClass('selected');
                // Add selection to clicked card
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

                // Scroll to form with animation
                $('html, body').animate({
                    scrollTop: $('#assignHodForm').offset().top - 100
                }, 500);

                const alertType = isInvalid ? 'warning' : 'info';
                const alertMessage = isInvalid
                    ? `Selected department with invalid assignment: <strong>${deptName}</strong>`
                    : `Selected department: <strong>${deptName}</strong>`;

                showAlert(alertType, alertMessage);

                // Update form validation
                validateForm();
            });

            // Add hover effects
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

            const departmentId = $('#departmentSelect').val();
            const hodId = $('#lecturerSelect').val();
            const departmentOption = $('#departmentSelect option:selected');
            const isInvalidAssignment = departmentOption.data('invalid') === 'true';

            if (!departmentId) {
                showAlert('warning', 'Please select a department');
                $('#departmentSelect').focus();
                return;
            }

            // Check if this is fixing an invalid assignment
            if (isInvalidAssignment && !hodId) {
                if (!confirm('This department has an invalid HOD assignment. Removing the HOD will clear this invalid reference. Continue?')) {
                    return;
                }
            }

            showLoading();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...').prop('disabled', true);

            $.post('api/assign-hod-api.php?action=assign_hod', {
                department_id: departmentId,
                hod_id: hodId,
                csrf_token: "<?php echo $csrf_token; ?>"
            })
            .done(function(response) {
                if (response.status === 'success') {
                    const message = isInvalidAssignment && hodId
                        ? 'Invalid HOD assignment fixed successfully!'
                        : response.message;
                    showAlert('success', message);
                    loadData(); // Refresh all data
                    $('#assignHodForm')[0].reset();
                    $('#currentAssignmentInfo').hide();
                } else if (response.status === 'warning' && response.requires_confirmation) {
                    if (confirm(response.message + ' Continue anyway?')) {
                        // Retry with confirmation
                        $.post('api/assign-hod-api.php?action=assign_hod', {
                            department_id: departmentId,
                            hod_id: hodId,
                            csrf_token: "<?php echo $csrf_token; ?>",
                            confirmed: true
                        })
                        .done(function(retryResponse) {
                            if (retryResponse.status === 'success') {
                                showAlert('success', 'HOD reassigned successfully!');
                                loadData();
                                $('#assignHodForm')[0].reset();
                                $('#currentAssignmentInfo').hide();
                            } else {
                                showAlert('danger', retryResponse.message);
                            }
                        })
                        .fail(function() {
                            showAlert('danger', 'Failed to reassign HOD. Please try again.');
                        });
                    }
                } else {
                    showAlert('danger', response.message);
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

        // Make loadData function available globally
        window.loadData = loadData;

        // Help functionality
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
                                            <li><kbd>Esc</kbd> <span class="text-muted">Reset form</span></li>
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
                                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if present
            $('#helpModal').remove();
            $('body').append(helpHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('helpModal'));
            modal.show();
        }

        // Function to fix invalid assignments
        function fixInvalidAssignments() {
            const invalidDepts = allDepartments.filter(dept =>
                dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
            );

            if (invalidDepts.length === 0) {
                showAlert('info', 'No invalid assignments found.');
                return;
            }

            console.log('Invalid assignments to fix:', invalidDepts);

            if (confirm(`Found ${invalidDepts.length} department(s) with invalid HOD assignments.\n\nThese departments have hod_id values pointing to users who are not HODs or missing lecturer records.\n\nFix them by clearing the invalid HOD assignments?`)) {
                showLoading();

                // Fix each invalid assignment one by one to handle errors gracefully
                let fixedCount = 0;
                let errorCount = 0;

                const fixNext = (index) => {
                    if (index >= invalidDepts.length) {
                        // All done
                        hideLoading();
                        if (errorCount === 0) {
                            showAlert('success', `Successfully fixed ${fixedCount} invalid HOD assignment(s)!`);
                        } else {
                            showAlert('warning', `Fixed ${fixedCount} assignment(s), but ${errorCount} failed. Please check the console for details.`);
                        }
                        loadData(); // Refresh all data
                        return;
                    }

                    const dept = invalidDepts[index];
                    console.log(`Fixing department: ${dept.name} (ID: ${dept.id})`);

                    $.post('api/assign-hod-api.php?action=assign_hod', {
                        department_id: dept.id,
                        hod_id: null,
                        csrf_token: "<?php echo $csrf_token; ?>"
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
    </script>
</body>
</html>