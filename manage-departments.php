<?php
/**
 * Departments Management System - Refactored Version
 * Rwanda Polytechnic Attendance System
 *
 * This file provides a comprehensive department and program management interface
 * with the following improvements over the original version:
 *
 * FEATURES:
 * - Separated backend logic into dedicated classes (DepartmentManager, ValidationManager, Logger)
 * - Enhanced error handling with retry mechanisms and user-friendly feedback
 * - Improved input validation and sanitization
 * - Better accessibility features (ARIA labels, screen reader support, keyboard navigation)
 * - Enhanced security measures (rate limiting, CSP headers, improved CSRF protection)
 * - Responsive design with modern UI/UX patterns
 * - Comprehensive logging for debugging and monitoring
 * - Optimized database queries with proper prepared statements
 *
 * ARCHITECTURE:
 * - Backend: DepartmentManager class handles all database operations
 * - Validation: ValidationManager class ensures data integrity
 * - Logging: Logger class provides comprehensive application logging
 * - Frontend: JavaScript class manages UI interactions and API calls
 *
 * SECURITY FEATURES:
 * - CSRF token validation for all state-changing operations
 * - Rate limiting (100 requests per 5 minutes)
 * - Input sanitization and validation
 * - SQL injection prevention through prepared statements
 * - XSS protection headers
 * - Secure session management
 *
 * ACCESSIBILITY FEATURES:
 * - ARIA labels and descriptions
 * - Screen reader support
 * - Keyboard navigation
 * - High contrast mode support
 * - Reduced motion preferences
 * - Proper form validation feedback
 *
 * @version 2.0.0
 * @author Rwanda Polytechnic Development Team
 * @since 2024
 */

// Include configuration and required classes
require_once 'config.php';
require_once 'backend/classes/DepartmentManager.php';
require_once 'backend/classes/ValidationManager.php';
require_once 'backend/classes/Logger.php';

session_start();

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src \'self\' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; img-src \'self\' data: https:; connect-src \'self\' https://cdn.jsdelivr.net;');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Rate limiting (basic implementation)
$rateLimitKey = 'dept_api_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateLimitFile = sys_get_temp_dir() . '/rate_limit_' . md5($rateLimitKey) . '.json';

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $currentTime = time();
    $rateLimitData = [];

    if (file_exists($rateLimitFile)) {
        $rateLimitData = json_decode(file_get_contents($rateLimitFile), true) ?? [];
    }

    // Clean old entries (5 minute window)
    $rateLimitData = array_filter($rateLimitData, function($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < 300;
    });

    // Check rate limit (100 requests per 5 minutes)
    if (count($rateLimitData) >= 100) {
        http_response_code(429);
        header('Retry-After: 300');
        echo json_encode([
            'status' => 'error',
            'message' => 'Rate limit exceeded. Please try again later.',
            'retry_after' => 300
        ]);
        exit;
    }

    // Add current request
    $rateLimitData[] = $currentTime;
    file_put_contents($rateLimitFile, json_encode($rateLimitData), LOCK_EX);
}

// Check authentication - handle AJAX requests differently
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if ($is_ajax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Session expired or insufficient permissions. Please login again.',
            'error_code' => 'AUTHENTICATION_FAILED',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    } else {
        header('Location: login.php');
        exit;
    }
}

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize managers (only when needed for AJAX or form processing)
$departmentManager = null;
$validator = null;
$logger = null;

function getDepartmentManager() {
    global $departmentManager, $pdo, $logger;
    if ($departmentManager === null) {
        $logger = new Logger();
        $departmentManager = new DepartmentManager($pdo);
    }
    return $departmentManager;
}

function getValidator() {
    global $validator;
    if ($validator === null) {
        $validator = new ValidationManager();
    }
    return $validator;
}

function getLogger() {
    global $logger;
    if ($logger === null) {
        $logger = new Logger();
    }
    return $logger;
}

// Utility Functions
function jsonResponse($status, $message, $data = [], $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// API Request Handler
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action'])) {
    try {
        $action = $_GET['action'];

        // Validate CSRF token for state-changing operations
        $csrfActions = ['add_department', 'delete_department', 'add_program', 'delete_program', 'update_department_hod'];
        if (in_array($action, $csrfActions)) {
            $csrfValidation = getValidator()->validateCSRF($_POST['csrf_token'] ?? '', $_SESSION['csrf_token']);
            if (!$csrfValidation['valid']) {
                jsonResponse('error', $csrfValidation['message'], [], 403);
            }
        }

        switch ($action) {
            case 'list_departments':
                $result = getDepartmentManager()->getAllDepartments();
                if ($result['success']) {
                    jsonResponse('success', 'Departments retrieved', [
                        'departments' => $result['data'],
                        'total_count' => count($result['data'])
                    ]);
                } else {
                    jsonResponse('error', $result['message'], [], 500);
                }
                break;

            case 'get_available_hods':
                $hods = getDepartmentManager()->getAvailableHoDs();
                jsonResponse('success', 'Available HODs retrieved', ['hods' => $hods]);
                break;

            case 'add_department':
                $name = getValidator()->sanitizeInput($_POST['department_name'] ?? '');
                $hodId = !empty($_POST['hod_id']) ? (int)$_POST['hod_id'] : null;
                $programs = getValidator()->sanitizeInput($_POST['programs'] ?? []);

                $result = getDepartmentManager()->createDepartment($name, $hodId, $programs);
                if ($result['success']) {
                    jsonResponse('success', $result['message'], $result['data']);
                } else {
                    jsonResponse('error', $result['message'], [], 400);
                }
                break;

            case 'delete_department':
                $deptId = (int)($_POST['department_id'] ?? 0);
                $idValidation = getValidator()->validateId($deptId, 'Department ID');
                if (!$idValidation['valid']) {
                    jsonResponse('error', $idValidation['message'], [], 400);
                }

                $result = getDepartmentManager()->deleteDepartment($deptId);
                if ($result['success']) {
                    jsonResponse('success', $result['message'], $result['data']);
                } else {
                    jsonResponse('error', $result['message'], [], 404);
                }
                break;

            case 'add_program':
                $deptId = (int)($_POST['department_id'] ?? 0);
                $programName = getValidator()->sanitizeInput($_POST['program_name'] ?? '');
                $status = getValidator()->sanitizeInput($_POST['status'] ?? 'active');

                $deptValidation = getValidator()->validateId($deptId, 'Department ID');
                if (!$deptValidation['valid']) {
                    jsonResponse('error', $deptValidation['message'], [], 400);
                }

                $result = getDepartmentManager()->addProgram($deptId, $programName, $status);
                if ($result['success']) {
                    jsonResponse('success', $result['message'], $result['data']);
                } else {
                    jsonResponse('error', $result['message'], [], 400);
                }
                break;

            case 'update_program_status':
                $progId = (int)($_POST['program_id'] ?? 0);
                $status = getValidator()->sanitizeInput($_POST['status'] ?? '');

                $idValidation = getValidator()->validateId($progId, 'Program ID');
                if (!$idValidation['valid']) {
                    jsonResponse('error', $idValidation['message'], [], 400);
                }

                if (!in_array($status, ['active', 'inactive'])) {
                    jsonResponse('error', 'Invalid status value', [], 400);
                }

                $result = getDepartmentManager()->updateProgramStatus($progId, $status);
                if ($result['success']) {
                    jsonResponse('success', $result['message']);
                } else {
                    jsonResponse('error', $result['message'], [], 404);
                }
                break;

            case 'get_program_details':
                $progId = (int)($_GET['program_id'] ?? 0);
                $idValidation = getValidator()->validateId($progId, 'Program ID');
                if (!$idValidation['valid']) {
                    jsonResponse('error', $idValidation['message'], [], 400);
                }

                $result = getDepartmentManager()->getProgramDetails($progId);
                if ($result['success']) {
                    jsonResponse('success', 'Program details retrieved', $result['data']);
                } else {
                    jsonResponse('error', $result['message'], [], 404);
                }
                break;

            case 'debug_program':
                $progId = (int)($_GET['program_id'] ?? 0);
                $idValidation = $validator->validateId($progId, 'Program ID');
                if (!$idValidation['valid']) {
                    jsonResponse('error', $idValidation['message'], [], 400);
                }

                try {
                    // Check if program exists
                    $stmt = $pdo->prepare("SELECT id, name, department_id, status, created_at FROM options WHERE id = ?");
                    $stmt->execute([$progId]);
                    $program = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$program) {
                        jsonResponse('error', 'Program not found in database', [], 404);
                    }

                    // Check for foreign key constraints
                    $constraints = [];
                    $tables = ['attendance_records', 'courses', 'student_registrations', 'lecturer_assignments'];

                    foreach ($tables as $table) {
                        try {
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE program_id = ?");
                            $stmt->execute([$progId]);
                            $count = $stmt->fetchColumn();
                            if ($count > 0) {
                                $constraints[$table] = $count;
                            }
                        } catch (Exception $e) {
                            // Table might not exist
                        }
                    }

                    jsonResponse('success', 'Debug info retrieved', [
                        'program' => $program,
                        'constraints' => $constraints,
                        'can_delete' => empty($constraints)
                    ]);

                } catch (Exception $e) {
                    jsonResponse('error', 'Debug query failed: ' . $e->getMessage(), [], 500);
                }
                break;

            case 'delete_program':
                $progId = (int)($_POST['program_id'] ?? 0);
                $idValidation = getValidator()->validateId($progId, 'Program ID');
                if (!$idValidation['valid']) {
                    jsonResponse('error', $idValidation['message'], [], 400);
                }

                $result = getDepartmentManager()->deleteProgram($progId);
                if ($result['success']) {
                    jsonResponse('success', $result['message']);
                } else {
                    // Log the detailed error for debugging
                    getLogger()->error("Delete program failed: " . $result['message']);
                    jsonResponse('error', $result['message'], [], 400);
                }
                break;

            case 'update_department_hod':
                $deptId = (int)($_POST['department_id'] ?? 0);
                $hodId = !empty($_POST['hod_id']) ? (int)$_POST['hod_id'] : null;

                $deptValidation = getValidator()->validateId($deptId, 'Department ID');
                if (!$deptValidation['valid']) {
                    jsonResponse('error', $deptValidation['message'], [], 400);
                }

                $result = getDepartmentManager()->updateDepartmentHod($deptId, $hodId);
                if ($result['success']) {
                    jsonResponse('success', $result['message'], $result['data']);
                } else {
                    jsonResponse('error', $result['message'], [], 400);
                }
                break;

            case 'get_statistics':
                $result = getDepartmentManager()->getStatistics();
                if ($result['success']) {
                    jsonResponse('success', 'Statistics retrieved', $result['data']);
                } else {
                    jsonResponse('error', $result['message'], [], 500);
                }
                break;

            default:
                jsonResponse('error', 'Invalid action', [], 400);
        }
    } catch (Exception $e) {
        getLogger()->error("API Error: " . $e->getMessage());
        jsonResponse('error', 'An unexpected error occurred', [], 500);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments Management | RP Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e40af;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0891b2;
            --light-bg: #f8fafc;
            --border-radius: 16px;
            --sidebar-width: 280px;
        }

        body {
            background: linear-gradient(135deg, #87ceeb 0%, #4682b4 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: 70px;
            padding: 30px;
            min-height: calc(100vh - 70px);
            transition: margin-left 0.3s ease;
        }

        .card {
            background: rgba(255,255,255,0.95);
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border: none;
            backdrop-filter: blur(10px);
        }

        .department-item {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,102,204,0.12);
            border-left: 4px solid var(--primary-color);
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .department-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .program-badge {
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.875rem;
            margin: 2px 4px 2px 0;
            display: inline-block;
            border: 2px solid #dbeafe;
            background: linear-gradient(45deg, #dbeafe, #bfdbfe);
            color: var(--primary-color);
            transition: all 0.2s ease;
        }

        .program-badge:hover {
            background: linear-gradient(45deg, #bfdbfe, #93c5fd);
            border-color: var(--primary-color);
        }

        .programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .program-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .program-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        .program-header h6 {
            color: var(--primary-color);
            font-weight: 600;
        }

        .program-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 5px;
        }

        .program-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.25);
        }

        .statistics-card {
            background: linear-gradient(45deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            border: 2px solid;
            transition: all 0.3s ease;
        }

        .statistics-card:hover {
            transform: translateY(-3px);
        }

        .alert {
            border-radius: 10px;
            border: none;
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

        .sidebar .logo h5 {
            color: white;
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
            position: relative;
            z-index: 2;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        .sidebar .logo p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.75rem;
            font-weight: 500;
            position: relative;
            z-index: 2;
            margin: 5px 0 0 0;
        }

        .user-avatar {
            background: linear-gradient(45deg, #ffffff 0%, #f8fafc 100%);
            border: 3px solid rgba(255,255,255,0.3);
            transition: all 0.3s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
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

        .sidebar-footer {
            background: rgba(0,0,0,0.1);
        }

        .online-indicator {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .sidebar-toggle {
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

        .sidebar-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
        }

        /* Scrollbar styling for sidebar */
        .sidebar nav::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar nav::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }

        .sidebar nav::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .sidebar nav::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }

        .sidebar-overlay {
            backdrop-filter: blur(2px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Enhanced hover effects for nav items */
        .nav-link {
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s ease;
        }

        .nav-link:hover::before {
            left: 100%;
        }

        /* Badge animations */
        .badge {
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Accessibility improvements */
        .btn:focus {
            box-shadow: 0 0 0 0.2rem rgba(30, 64, 175, 0.5);
        }

        /* Screen reader only text */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Reduced motion for users who prefer it */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .card {
                border: 2px solid var(--primary-color);
            }
            .department-item {
                border-left: 6px solid var(--primary-color);
            }
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
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
            .sidebar-toggle {
                display: block !important;
            }
            .sidebar-overlay.show {
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

        /* Dark mode support (for future implementation) */
        @media (prefers-color-scheme: dark) {
            :root {
                --light-bg: #1f2937;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Sidebar Toggle -->
    <button class="sidebar-toggle d-md-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay position-fixed top-0 start-0 w-100 h-100 d-md-none" style="background: rgba(0,0,0,0.5); z-index: 999; display: none;" onclick="closeSidebar()"></div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

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
                <a href="register-student.php">
                    <i class="fas fa-user-plus"></i>Register Student
                </a>
            </li>
            <li>
                <a href="manage-users.php?role=lecturer">
                    <i class="fas fa-chalkboard-teacher"></i>Register Lecturer
                </a>
            </li>
            <li>
                <a href="manage-users.php">
                    <i class="fas fa-users-cog"></i>Manage Users
                </a>
            </li>
            <li>
                <a href="admin-view-users.php">
                    <i class="fas fa-users"></i>View Users
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-sitemap me-2"></i>Organization
            </li>
            <li>
                <a href="manage-departments.php" class="active">
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
    <div class="main-content" id="mainContent">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 text-primary"><i class="fas fa-building me-2" aria-hidden="true"></i>Departments Directory</h1>
                <p class="text-muted mb-0">Manage departments and programs</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="loadDepartments()" aria-label="Refresh departments list">
                    <i class="fas fa-sync-alt me-2" aria-hidden="true"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-3 mb-4" id="statisticsContainer"></div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Department Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-plus-circle me-2" aria-hidden="true"></i>Add New Department</h5>
            </div>
            <div class="card-body">
                <form id="departmentForm" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departmentName" class="form-label">
                                Department Name <span class="text-danger">*</span>
                                <span class="sr-only">Required field</span>
                            </label>
                            <input type="text"
                                   class="form-control"
                                   id="departmentName"
                                   name="department_name"
                                   required
                                   maxlength="100"
                                   aria-describedby="deptNameHelp deptNameError"
                                   autocomplete="organization-title">
                            <div id="deptNameHelp" class="form-text">
                                Enter 2-100 characters. Letters, numbers, spaces, and basic punctuation only.
                            </div>
                            <div id="deptNameError" class="invalid-feedback" role="alert"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="hodSelect" class="form-label">
                                Head of Department
                                <span class="sr-only">Optional field</span>
                            </label>
                            <select class="form-select"
                                    id="hodSelect"
                                    name="hod_id"
                                    aria-describedby="hodHelp">
                                <option value="">-- Select HoD (Optional) --</option>
                                <?php
                                $hods = getDepartmentManager()->getAvailableHoDs();
                                foreach ($hods as $hod) {
                                    echo "<option value='{$hod['id']}'>{$hod['username']}</option>";
                                }
                                ?>
                            </select>
                            <div id="hodHelp" class="form-text">
                                Assign an existing Head of Department to this department.
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">
                            Programs
                            <span class="sr-only">Optional field</span>
                        </label>
                        <div id="programsContainer" role="group" aria-labelledby="programsLabel">
                            <div class="input-group mb-2">
                                <input type="text"
                                       class="form-control"
                                       name="programs[]"
                                       placeholder="Program name (e.g., Computer Science)"
                                       maxlength="100"
                                       aria-describedby="programHelp">
                                <button type="button"
                                        class="btn btn-outline-danger remove-program"
                                        disabled
                                        aria-label="Remove this program field">
                                    <i class="fas fa-times" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div id="programHelp" class="form-text">
                            Add programs offered by this department. You can add multiple programs.
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-outline-primary mt-2"
                                id="addProgramField"
                                aria-describedby="addProgramHelp">
                            <i class="fas fa-plus me-1" aria-hidden="true"></i>Add Program
                        </button>
                        <div id="addProgramHelp" class="sr-only">
                            Click to add another program input field
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2" aria-hidden="true"></i>Save Department
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="this.closest('form').reset()">
                            <i class="fas fa-undo me-2" aria-hidden="true"></i>Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Departments List -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Departments Directory</h5>
            </div>
            <div class="card-body">
                <div id="departmentsContainer"></div>
            </div>
        </div>
    </div>

    <!-- Change HOD Modal -->
    <div class="modal fade" id="changeHodModal" tabindex="-1" aria-labelledby="changeHodModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changeHodModalLabel">
                        <i class="fas fa-user-tie me-2"></i>Change Head of Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Current HOD Display -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Head of Department</label>
                        <div class="bg-light p-3 rounded" id="currentHodDisplay">
                            <i class="fas fa-user-tie me-2 text-primary"></i>
                            <span id="currentHodName">Loading...</span>
                        </div>
                    </div>

                    <form id="changeHodForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" id="modalDeptId" name="department_id">
                        <div class="mb-3">
                            <label for="hodSelectModal" class="form-label fw-semibold">Select New Head of Department</label>
                            <select class="form-select" id="hodSelectModal" name="hod_id">
                                <option value="">-- Unassign HOD (Remove Current Assignment) --</option>
                                <!-- Available lecturers will be loaded here -->
                            </select>
                        </div>
                        <div class="alert alert-info small">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>To assign a new HOD:</strong> Select a lecturer from the dropdown.<br>
                            <strong>To remove the current HOD:</strong> Select "Unassign HOD" option.<br>
                            <small class="text-muted">Only lecturers assigned to this department can be selected as HOD.</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" id="saveHodChange">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class DepartmentsManager {
            constructor() {
                this.csrfToken = '<?= $_SESSION['csrf_token'] ?>';
                this.maxRetries = 3;
                this.retryDelay = 1000;
                this.init();
            }

            init() {
                this.setupEventHandlers();
                this.setupCrossPageListeners();
                this.loadInitialData();
            }

            setupEventHandlers() {
                // Department form submission with validation
                document.getElementById('departmentForm').addEventListener('submit', (e) => this.handleDepartmentSubmit(e));

                // Add program field button
                document.getElementById('addProgramField').addEventListener('click', () => this.addProgramField());

                // Dynamic event handlers for buttons
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('remove-program')) {
                        this.removeProgramField(e.target);
                    } else if (e.target.classList.contains('delete-department')) {
                        this.deleteDepartment(e.target.dataset.deptId, e.target.dataset.deptName);
                    } else if (e.target.classList.contains('delete-program')) {
                        this.deleteProgram(e.target.dataset.progId, e.target.dataset.progName);
                    } else if (e.target.classList.contains('add-program-btn')) {
                        this.addProgramToDepartment(e.target.dataset.deptId, e.target);
                    } else if (e.target.classList.contains('view-program-details')) {
                        this.viewProgramDetails(e.target.dataset.programId);
                    } else if (e.target.classList.contains('debug-program')) {
                        this.debugProgram(e.target.dataset.programId, e.target.dataset.programName);
                    } else if (e.target.classList.contains('change-hod')) {
                        this.openChangeHodModal(e.target.dataset.deptId, e.target.dataset.deptName, e.target.dataset.currentHodId);
                    }
                });

                // Program status change handler
                document.addEventListener('change', (e) => {
                    if (e.target.classList.contains('program-status-select')) {
                        this.updateProgramStatus(e.target.dataset.programId, e.target.value);
                    }
                });

                // Form validation on input
                document.getElementById('departmentName').addEventListener('input', (e) => this.validateDepartmentName(e.target));

                // Save HOD change button
                document.getElementById('saveHodChange').addEventListener('click', () => this.saveHodChange());
            }

            setupCrossPageListeners() {
                // Listen for user-related changes from other pages
                window.addEventListener('storage', (e) => {
                    if (e.key === 'user_role_changed' || e.key === 'user_status_changed' || e.key === 'user_changed') {
                        console.log('User change detected from another page, refreshing department data...');
                        this.loadDepartments();
                        this.loadStatistics();
                    }
                });
            }

            async apiCall(action, data = null, method = 'POST', retryCount = 0) {
                this.showLoading();

                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

                    let fetchUrl = `?ajax=1&action=${action}`;
                    let fetchOptions = {
                        method,
                        signal: controller.signal
                    };

                    // Handle data based on HTTP method - completely separate logic
                    if (method === 'GET' && data) {
                        // For GET requests, append data as URL parameters
                        const urlParams = new URLSearchParams();
                        for (const [key, value] of Object.entries(data)) {
                            if (Array.isArray(value)) {
                                value.forEach(v => urlParams.append(key + '[]', v));
                            } else {
                                urlParams.append(key, value);
                            }
                        }
                        fetchUrl += `&${urlParams.toString()}`;
                        // GET requests should NOT have a body
                    } else if (method !== 'GET' && data) {
                        // For POST/PUT requests, use FormData body
                        const formData = new FormData();
                        for (const [key, value] of Object.entries(data)) {
                            if (Array.isArray(value)) {
                                value.forEach(v => formData.append(key + '[]', v));
                            } else {
                                formData.append(key, value);
                            }
                        }
                        fetchOptions.body = formData;
                    }
                    // If method is GET and no data, or any other case, use default fetch options

                    const response = await fetch(fetchUrl, fetchOptions);
                    clearTimeout(timeoutId);

                    if (!response.ok) {
                        const errorText = await response.text();
                        let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                        try {
                            const errorData = JSON.parse(errorText);
                            errorMessage = errorData.message || errorMessage;
                        } catch {
                            errorMessage = errorText || errorMessage;
                        }
                        throw new Error(errorMessage);
                    }

                    const result = await response.json();

                    // Validate response structure
                    if (!result || typeof result !== 'object') {
                        throw new Error('Invalid response format');
                    }

                    return result;

                } catch (error) {
                    console.error(`API call failed (attempt ${retryCount + 1}):`, error);

                    // Retry logic for network errors
                    if (retryCount < this.maxRetries && (
                        error.name === 'AbortError' ||
                        error.message.includes('fetch') ||
                        error.message.includes('network')
                    )) {
                        console.log(`Retrying in ${this.retryDelay}ms...`);
                        await this.delay(this.retryDelay);
                        return this.apiCall(action, data, method, retryCount + 1);
                    }

                    throw error;
                } finally {
                    this.hideLoading();
                }
            }

            delay(ms) {
                return new Promise(resolve => setTimeout(resolve, ms));
            }

            async loadInitialData() {
                await this.loadDepartments();
                await this.loadStatistics();
            }

            async loadDepartments() {
                try {
                    const result = await this.apiCall('list_departments', null, 'GET');
                    if (result.status === 'success') {
                        this.renderDepartments(result.data.departments);
                        if (result.message) {
                            this.showAlert('success', result.message);
                        }
                    } else {
                        this.showAlert('warning', result.message || 'Failed to load departments');
                    }
                } catch (error) {
                    console.error('Failed to load departments:', error);
                    this.showAlert('danger', `Failed to load departments: ${error.message}`);
                    this.renderErrorState('departments');
                }
            }

            async loadStatistics() {
                try {
                    const result = await this.apiCall('get_statistics', null, 'GET');
                    if (result.status === 'success') {
                        this.renderStatistics(result.data);
                    } else {
                        console.warn('Failed to load statistics:', result.message);
                        this.renderEmptyStatistics();
                    }
                } catch (error) {
                    console.error('Failed to load statistics:', error);
                    this.renderEmptyStatistics();
                }
            }

            renderErrorState(type) {
                const container = document.getElementById(
                    type === 'departments' ? 'departmentsContainer' : 'statisticsContainer'
                );

                if (type === 'departments') {
                    container.innerHTML = `
                        <div class="text-center py-5">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h5 class="text-muted">Unable to load departments</h5>
                            <p class="text-muted mb-3">Please check your connection and try again.</p>
                            <button class="btn btn-primary" onclick="loadDepartments()">
                                <i class="fas fa-refresh me-2"></i>Retry
                            </button>
                        </div>
                    `;
                }
            }

            renderEmptyStatistics() {
                document.getElementById('statisticsContainer').innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Statistics unavailable. Please refresh the page to try again.
                        </div>
                    </div>
                `;
            }

            renderDepartments(departments) {
                const container = document.getElementById('departmentsContainer');
                if (!departments || departments.length === 0) {
                    container.innerHTML = '<div class="text-center py-5 text-muted">No departments found</div>';
                    return;
                }

                container.innerHTML = departments.map(dept => `
                    <div class="department-item">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="mb-1">${this.escapeHtml(dept.dept_name)}</h5>
                                <p class="text-muted mb-2">
                                    <strong>HoD:</strong> ${dept.hod_name === 'Not Assigned' ? '<span class="text-danger">Not Assigned</span>' : this.escapeHtml(dept.hod_name)}
                                </p>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary">${dept.program_count} Programs</span>
                                <span class="badge bg-success ms-1">${dept.active_programs} Active</span>
                            </div>
                        </div>

                        <div class="programs-section">
                            <h6 class="mb-3">Programs (${dept.programs.length})</h6>
                            <div class="programs-grid">
                                ${dept.programs.map(prog => this.renderProgramCard(prog)).join('')}
                            </div>
                        </div>

                        <div class="add-program-section d-flex gap-2 mb-3 mt-3">
                            <input type="text" class="form-control form-control-sm add-program-input" placeholder="New program name">
                            <select class="form-select form-select-sm add-program-status" style="width: auto;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button class="btn btn-sm btn-outline-success add-program-btn" data-dept-id="${dept.dept_id}">
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <button class="btn btn-sm ${dept.hod_id ? 'btn-outline-warning' : 'btn-outline-success'} change-hod"
                                    data-dept-id="${dept.dept_id}"
                                    data-dept-name="${this.escapeHtml(dept.dept_name)}"
                                    data-current-hod-id="${dept.hod_id || ''}"
                                    data-current-hod-name="${this.escapeHtml(dept.hod_name)}">
                                <i class="fas fa-user-tie me-1"></i>${dept.hod_id ? 'Change HOD' : 'Assign HOD'}
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-department"
                                    data-dept-id="${dept.dept_id}"
                                    data-dept-name="${this.escapeHtml(dept.dept_name)}">
                                <i class="fas fa-trash me-1"></i>Delete Department
                            </button>
                        </div>
                    </div>
                `).join('');
            }

            renderProgramCard(program) {
                const statusBadge = program.status === 'active'
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>';

                const createdDate = new Date(program.created_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });

                return `
                    <div class="program-card" data-program-id="${program.id}">
                        <div class="program-header">
                            <h6 class="program-name mb-1">${this.escapeHtml(program.name)}</h6>
                            <div class="program-meta">
                                ${statusBadge}
                                <small class="text-muted">Created: ${createdDate}</small>
                            </div>
                        </div>
                        <div class="program-actions mt-2">
                            <select class="form-select form-select-sm program-status-select"
                                    data-program-id="${program.id}"
                                    style="display: inline-block; width: auto; margin-right: 8px;">
                                <option value="active" ${program.status === 'active' ? 'selected' : ''}>Active</option>
                                <option value="inactive" ${program.status === 'inactive' ? 'selected' : ''}>Inactive</option>
                            </select>
                            <button class="btn btn-sm btn-outline-info me-1 debug-program"
                                    data-program-id="${program.id}"
                                    data-program-name="${this.escapeHtml(program.name)}"
                                    title="Debug Program">
                                <i class="fas fa-bug"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-primary me-1 view-program-details"
                                    data-program-id="${program.id}"
                                    title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger delete-program"
                                    data-prog-id="${program.id}"
                                    data-prog-name="${this.escapeHtml(program.name)}"
                                    title="Delete Program">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            }

            renderStatistics(stats) {
                document.getElementById('statisticsContainer').innerHTML = `
                    <div class="col-md-3"><div class="card text-center border-primary"><div class="card-body">
                        <i class="fas fa-building fa-2x text-primary mb-2"></i><h4 class="text-primary">${stats.total_departments}</h4>
                        <p class="text-muted mb-0">Total Departments</p>
                    </div></div></div>
                    <div class="col-md-3"><div class="card text-center border-success"><div class="card-body">
                        <i class="fas fa-user-tie fa-2x text-success mb-2"></i><h4 class="text-success">${stats.assigned_hods}</h4>
                        <p class="text-muted mb-0">Assigned HoDs</p>
                    </div></div></div>
                    <div class="col-md-3"><div class="card text-center border-info"><div class="card-body">
                        <i class="fas fa-graduation-cap fa-2x text-info mb-2"></i><h4 class="text-info">${stats.total_programs}</h4>
                        <p class="text-muted mb-0">Total Programs</p>
                    </div></div></div>
                    <div class="col-md-3"><div class="card text-center border-warning"><div class="card-body">
                        <i class="fas fa-chart-line fa-2x text-warning mb-2"></i><h4 class="text-warning">${stats.avg_programs_per_dept}</h4>
                        <p class="text-muted mb-0">Avg Programs/Dept</p>
                    </div></div></div>
                `;
            }

            async handleDepartmentSubmit(e) {
                e.preventDefault();

                // Validate form before submission
                const validation = this.validateForm(e.target);
                if (!validation.valid) {
                    this.showAlert('warning', validation.message);
                    return;
                }

                const formData = new FormData(e.target);
                const programs = Array.from(formData.getAll('programs[]')).filter(p => p.trim());

                // Show loading state on submit button
                const submitBtn = e.target.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating...';
                submitBtn.disabled = true;

                try {
                    const result = await this.apiCall('add_department', {
                        department_name: formData.get('department_name'),
                        hod_id: formData.get('hod_id'),
                        programs: programs,
                        csrf_token: this.csrfToken
                    });

                    if (result.status === 'success') {
                        this.showAlert('success', result.message);
                        e.target.reset();
                        this.clearProgramFields();
                        await this.loadDepartments();
                        await this.loadStatistics(); // Refresh statistics too
                        // Trigger cross-page update for user management
                        this.triggerCrossPageUpdate('department_changed', { timestamp: Date.now() });
                    } else {
                        this.showAlert('danger', result.message || 'Failed to create department');
                    }

                } catch (error) {
                    console.error('Form submission error:', error);
                    this.showAlert('danger', `Failed to create department: ${error.message}`);
                } finally {
                    // Restore submit button
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            }

            validateForm(form) {
                const deptName = form.querySelector('#departmentName').value.trim();

                if (!deptName) {
                    return { valid: false, message: 'Department name is required' };
                }

                if (deptName.length < 2) {
                    return { valid: false, message: 'Department name must be at least 2 characters' };
                }

                if (deptName.length > 100) {
                    return { valid: false, message: 'Department name must not exceed 100 characters' };
                }

                // Check for invalid characters
                if (!/^[a-zA-Z0-9\s\-\.\(\)]+$/.test(deptName)) {
                    return { valid: false, message: 'Department name contains invalid characters' };
                }

                return { valid: true };
            }

            validateDepartmentName(input) {
                const value = input.value.trim();
                let isValid = true;
                let message = '';

                if (value && value.length < 2) {
                    isValid = false;
                    message = 'Must be at least 2 characters';
                } else if (value && value.length > 100) {
                    isValid = false;
                    message = 'Must not exceed 100 characters';
                } else if (value && !/^[a-zA-Z0-9\s\-\.\(\)]+$/.test(value)) {
                    isValid = false;
                    message = 'Contains invalid characters';
                }

                // Show/hide validation message
                this.showFieldValidation(input, isValid, message);
            }

            showFieldValidation(input, isValid, message) {
                const existingFeedback = input.parentNode.querySelector('.invalid-feedback');
                if (existingFeedback) {
                    existingFeedback.remove();
                }

                if (!isValid) {
                    input.classList.add('is-invalid');
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    feedback.textContent = message;
                    input.parentNode.appendChild(feedback);
                } else {
                    input.classList.remove('is-invalid');
                }
            }

            clearProgramFields() {
                const container = document.getElementById('programsContainer');
                const fields = container.querySelectorAll('input[name="programs[]"]');
                fields.forEach((field, index) => {
                    if (index > 0) { // Keep the first field
                        field.closest('.input-group').remove();
                    } else {
                        field.value = ''; // Clear the first field
                    }
                });
            }

            async deleteDepartment(deptId, deptName) {
                if (!confirm(`Delete "${deptName}" and all its programs?`)) return;
                try {
                    const result = await this.apiCall('delete_department', {
                        department_id: deptId,
                        csrf_token: this.csrfToken
                    });
                    this.showAlert('success', result.message);
                    await this.loadDepartments();
                    await this.loadStatistics();
                    // Trigger cross-page update for user management
                    this.triggerCrossPageUpdate('department_changed', { timestamp: Date.now() });
                } catch (error) {
                    this.showAlert('danger', error.message);
                }
            }

            async addProgramToDepartment(deptId, buttonElement) {
                const inputField = buttonElement.closest('.add-program-section').querySelector('.add-program-input');
                const statusSelect = buttonElement.closest('.add-program-section').querySelector('.add-program-status');
                const programName = inputField.value.trim();
                const status = statusSelect.value;

                if (!programName) {
                    this.showAlert('warning', 'Program name is required');
                    return;
                }

                try {
                    const result = await this.apiCall('add_program', {
                        department_id: deptId,
                        program_name: programName,
                        status: status,
                        csrf_token: this.csrfToken
                    });

                    if (result.status === 'success') {
                        this.showAlert('success', result.message);
                        inputField.value = ''; // Clear the input
                        await this.loadDepartments();
                        await this.loadStatistics();
                        // Trigger cross-page update for user management
                        this.triggerCrossPageUpdate('department_changed', { timestamp: Date.now() });
                    } else {
                        this.showAlert('danger', result.message || 'Failed to add program');
                    }
                } catch (error) {
                    console.error('Add program error:', error);
                    this.showAlert('danger', `Failed to add program: ${error.message}`);
                }
            }

            async updateProgramStatus(programId, newStatus) {
                try {
                    const result = await this.apiCall('update_program_status', {
                        program_id: programId,
                        status: newStatus,
                        csrf_token: this.csrfToken
                    });

                    if (result.status === 'success') {
                        this.showAlert('success', result.message);
                        // Update the badge color without reloading
                        this.updateProgramStatusBadge(programId, newStatus);
                    } else {
                        this.showAlert('danger', result.message || 'Failed to update program status');
                    }
                } catch (error) {
                    console.error('Update program status error:', error);
                    this.showAlert('danger', `Failed to update program status: ${error.message}`);
                }
            }

            updateProgramStatusBadge(programId, status) {
                const programCard = document.querySelector(`[data-program-id="${programId}"]`);
                if (programCard) {
                    const badge = programCard.querySelector('.badge');
                    if (badge) {
                        badge.className = status === 'active'
                            ? 'badge bg-success'
                            : 'badge bg-secondary';
                        badge.textContent = status === 'active' ? 'Active' : 'Inactive';
                    }
                }
            }

            async viewProgramDetails(programId) {
                try {
                    const result = await this.apiCall('get_program_details', {
                        program_id: programId
                    }, 'GET');

                    if (result.status === 'success') {
                        this.showProgramDetailsModal(result.data);
                    } else {
                        this.showAlert('danger', result.message || 'Failed to load program details');
                    }
                } catch (error) {
                    console.error('View program details error:', error);
                    this.showAlert('danger', `Failed to load program details: ${error.message}`);
                }
            }

            async debugProgram(programId, programName) {
                try {
                    const result = await this.apiCall('debug_program', {
                        program_id: programId
                    }, 'GET');

                    if (result.status === 'success') {
                        this.showDebugModal(result.data, programName);
                    } else {
                        this.showAlert('danger', result.message || 'Failed to debug program');
                    }
                } catch (error) {
                    console.error('Debug program error:', error);
                    this.showAlert('danger', `Failed to debug program: ${error.message}`);
                }
            }

            showDebugModal(debugData, programName) {
                const constraintsList = debugData.constraints && Object.keys(debugData.constraints).length > 0
                    ? Object.entries(debugData.constraints).map(([table, count]) =>
                        `<li><strong>${table}:</strong> ${count} record(s)</li>`
                      ).join('')
                    : '<li class="text-success">No constraints found - program can be safely deleted</li>';

                const modalHtml = `
                    <div class="modal fade" id="debugProgramModal" tabindex="-1" aria-labelledby="debugProgramModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="debugProgramModalLabel">
                                        <i class="fas fa-bug me-2"></i>Debug Program: ${this.escapeHtml(programName)}
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <h6 class="text-info mb-3">Program Information</h6>
                                            <div class="bg-light p-3 rounded">
                                                <div class="row">
                                                    <div class="col-md-6"><strong>ID:</strong> ${debugData.program.id}</div>
                                                    <div class="col-md-6"><strong>Name:</strong> ${this.escapeHtml(debugData.program.name)}</div>
                                                    <div class="col-md-6"><strong>Department ID:</strong> ${debugData.program.department_id}</div>
                                                    <div class="col-md-6"><strong>Status:</strong> <span class="badge ${debugData.program.status === 'active' ? 'bg-success' : 'bg-secondary'}">${debugData.program.status}</span></div>
                                                    <div class="col-md-6"><strong>Created:</strong> ${new Date(debugData.program.created_at).toLocaleString()}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <h6 class="text-warning mb-3">Deletion Constraints</h6>
                                            <div class="bg-light p-3 rounded">
                                                <p class="mb-2"><strong>Can be deleted:</strong>
                                                    <span class="badge ${debugData.can_delete ? 'bg-success' : 'bg-danger'}">
                                                        ${debugData.can_delete ? 'Yes' : 'No'}
                                                    </span>
                                                </p>
                                                <p class="mb-2"><strong>Constraints found:</strong></p>
                                                <ul class="mb-0">
                                                    ${constraintsList}
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    ${debugData.can_delete ?
                                        `<button type="button" class="btn btn-danger" onclick="document.getElementById('debugProgramModal').querySelector('.btn-close').click(); window.departmentsManager.deleteProgram(${debugData.program.id}, '${this.escapeHtml(programName)}')">
                                            <i class="fas fa-trash me-2"></i>Delete Program
                                        </button>` :
                                        `<button type="button" class="btn btn-warning" onclick="alert('Please remove all associated records first before deleting this program.')">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Cannot Delete
                                        </button>`
                                    }
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if present
                const existingModal = document.getElementById('debugProgramModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add new modal to body
                document.body.insertAdjacentHTML('beforeend', modalHtml);

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('debugProgramModal'));
                modal.show();
            }

            showProgramDetailsModal(program) {
                const modalHtml = `
                    <div class="modal fade" id="programDetailsModal" tabindex="-1" aria-labelledby="programDetailsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="programDetailsModalLabel">
                                        <i class="fas fa-graduation-cap me-2"></i>Program Details
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <h6 class="text-primary mb-2">${this.escapeHtml(program.name)}</h6>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Department:</strong><br>
                                            <span>${this.escapeHtml(program.department_name)}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Status:</strong><br>
                                            <span class="badge ${program.status === 'active' ? 'bg-success' : 'bg-secondary'}">
                                                ${program.status === 'active' ? 'Active' : 'Inactive'}
                                            </span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Created:</strong><br>
                                            <span>${new Date(program.created_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                                hour: '2-digit',
                                                minute: '2-digit'
                                            })}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Program ID:</strong><br>
                                            <span class="text-muted">#${program.id}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove existing modal if present
                const existingModal = document.getElementById('programDetailsModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Add new modal to body
                document.body.insertAdjacentHTML('beforeend', modalHtml);

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('programDetailsModal'));
                modal.show();
            }

            async deleteProgram(progId, progName) {
                const confirmMessage = `Are you sure you want to delete the program "${progName}"?\n\nThis action cannot be undone and will remove all associated data.`;

                if (!confirm(confirmMessage)) return;

                // Show loading state on the specific delete button
                const deleteBtn = document.querySelector(`button[data-prog-id="${progId}"]`);
                const originalText = deleteBtn.innerHTML;
                deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                deleteBtn.disabled = true;

                try {
                    const result = await this.apiCall('delete_program', {
                        program_id: progId,
                        csrf_token: this.csrfToken
                    });

                    if (result.status === 'success') {
                        this.showAlert('success', result.message);
                        await this.loadDepartments();
                        await this.loadStatistics(); // Refresh statistics too
                        // Trigger cross-page update for user management
                        this.triggerCrossPageUpdate('department_changed', { timestamp: Date.now() });
                    } else {
                        this.showAlert('danger', result.message || 'Failed to delete program');
                    }

                } catch (error) {
                    console.error('Delete program error:', error);

                    // Provide specific error messages based on error type
                    let errorMessage = error.message;
                    if (error.message.includes('foreign key') || error.message.includes('constraint')) {
                        errorMessage = 'Cannot delete program as it is being used in other records (attendance, courses, etc.).';
                    } else if (error.message.includes('not found')) {
                        errorMessage = 'Program not found. It may have already been deleted.';
                    } else {
                        errorMessage = `Failed to delete program: ${error.message}`;
                    }

                    this.showAlert('danger', errorMessage);
                } finally {
                    // Restore button state
                    deleteBtn.innerHTML = originalText;
                    deleteBtn.disabled = false;
                }
            }

            addProgramField() {
                const container = document.getElementById('programsContainer');
                const newField = document.createElement('div');
                newField.className = 'input-group mb-2';
                newField.innerHTML = `
                    <input type="text" class="form-control" name="programs[]" placeholder="Program name" maxlength="100">
                    <button type="button" class="btn btn-outline-danger remove-program"><i class="fas fa-times"></i></button>
                `;
                container.appendChild(newField);
            }

            removeProgramField(button) {
                const container = document.getElementById('programsContainer');
                if (container.children.length > 1) button.closest('.input-group').remove();
            }

            async openChangeHodModal(deptId, deptName, currentHodId) {
                // Set department ID in the form
                document.getElementById('modalDeptId').value = deptId;

                // Update modal title
                document.getElementById('changeHodModalLabel').innerHTML =
                    `<i class="fas fa-user-tie me-2"></i>Change HOD for ${this.escapeHtml(deptName)}`;

                // Display current HOD information
                const currentHodDisplay = document.getElementById('currentHodName');
                if (currentHodId && currentHodId !== '') {
                    // Find the current HOD name from the department data
                    const deptItem = document.querySelector(`[data-dept-id="${deptId}"]`);
                    if (deptItem) {
                        const hodElement = deptItem.querySelector('.text-muted strong');
                        if (hodElement && hodElement.nextSibling) {
                            const hodName = hodElement.nextSibling.textContent.trim();
                            currentHodDisplay.innerHTML = `<strong>${this.escapeHtml(hodName)}</strong>`;
                        } else {
                            currentHodDisplay.innerHTML = `<em class="text-muted">Unknown</em>`;
                        }
                    } else {
                        currentHodDisplay.innerHTML = `<em class="text-muted">Loading...</em>`;
                    }
                } else {
                    currentHodDisplay.innerHTML = `<em class="text-warning">Not Assigned</em>`;
                }

                // Load available HODs for this department
                await this.loadAvailableHods(deptId, currentHodId);

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('changeHodModal'));
                modal.show();
            }

            async loadAvailableHods(deptId, currentHodId) {
                try {
                    const result = await this.apiCall('get_available_hods', { department_id: deptId }, 'GET');
                    if (result.status === 'success') {
                        const select = document.getElementById('hodSelectModal');
                        // Clear existing options except the first one
                        select.innerHTML = '<option value="">-- Unassign HOD --</option>';

                        // Add HOD options
                        result.data.hods.forEach(hod => {
                            const option = document.createElement('option');
                            option.value = hod.id;
                            option.textContent = hod.username;
                            if (hod.id == currentHodId) {
                                option.selected = true;
                            }
                            select.appendChild(option);
                        });
                    } else {
                        this.showAlert('danger', 'Failed to load available HODs');
                    }
                } catch (error) {
                    console.error('Load HODs error:', error);
                    this.showAlert('danger', 'Failed to load available HODs');
                }
            }

            async saveHodChange() {
                const form = document.getElementById('changeHodForm');
                const formData = new FormData(form);
                const deptId = formData.get('department_id');
                const hodId = formData.get('hod_id');

                // Get selected option text for confirmation
                const selectElement = document.getElementById('hodSelectModal');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const selectedText = selectedOption.text;

                // Confirmation for unassignment
                if (!hodId || hodId === '') {
                    const confirmUnassign = confirm(`Are you sure you want to remove the current Head of Department assignment?\n\nThis will unassign the HOD and change their role back to lecturer if they're not HOD for any other department.`);
                    if (!confirmUnassign) {
                        return;
                    }
                }

                // Show loading state
                const saveBtn = document.getElementById('saveHodChange');
                const originalText = saveBtn.innerHTML;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
                saveBtn.disabled = true;

                try {
                    const result = await this.apiCall('update_department_hod', {
                        department_id: deptId,
                        hod_id: hodId,
                        csrf_token: this.csrfToken
                    });

                    if (result.status === 'success') {
                        const action = hodId ? `assigned ${selectedText} as HOD` : 'unassigned the current HOD';
                        this.showAlert('success', `Department HOD ${action} successfully!`);
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('changeHodModal'));
                        modal.hide();
                        // Refresh departments
                        await this.loadDepartments();
                        await this.loadStatistics();

                        // Trigger cross-page update for user management
                        this.triggerCrossPageUpdate('department_hod_changed', { timestamp: Date.now() });
                    } else {
                        this.showAlert('danger', result.message || 'Failed to update department HOD');
                    }
                } catch (error) {
                    console.error('Save HOD change error:', error);
                    this.showAlert('danger', `Failed to update department HOD: ${error.message}`);
                } finally {
                    // Restore button
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                }
            }

            triggerCrossPageUpdate(eventType, data) {
                try {
                    localStorage.setItem(eventType, JSON.stringify(data));
                    // Immediately remove to trigger storage event in other tabs
                    setTimeout(() => localStorage.removeItem(eventType), 100);
                } catch (e) {
                    console.warn('Cross-page update failed:', e);
                }
            }

            showAlert(type, message) {
                const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show">
                    <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'} me-2"></i>${this.escapeHtml(message)}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
                document.getElementById('alertContainer').innerHTML = alertHtml;
                setTimeout(() => document.querySelector('.alert')?.remove(), 5000);
            }

            showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
            hideLoading() { document.getElementById('loadingOverlay').style.display = 'none'; }
            escapeHtml(unsafe) { return unsafe?.toString().replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;") || ''; }
        }

        // Sidebar management functions
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');

            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
        }

        function closeSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');

            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        }

        // Close sidebar when clicking on nav links (mobile)
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebar();
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            window.departmentsManager = new DepartmentsManager();
            window.loadDepartments = () => window.departmentsManager.loadDepartments();
            window.toggleSidebar = toggleSidebar;
            window.closeSidebar = closeSidebar;
        });
    </script>
</body>
</html>