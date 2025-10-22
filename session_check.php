<?php
// session_check.php - Enhanced security session management
error_reporting(0); // Keep disabled for production

// Configure session cookie for AJAX compatibility - must be set before session_start()
if (!headers_sent()) {
    session_set_cookie_params([
        'lifetime' => 0, // Session cookie
        'path' => '/',
        'domain' => '', // Leave empty for current domain
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => false, // Allow JavaScript access for AJAX
        'samesite' => 'Lax' // Allow cross-site requests
    ]);
}

session_start();

// Detect AJAX requests early
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$current_page = basename($_SERVER['PHP_SELF']);

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self' data:; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");

// Prevent caching of protected pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Session security settings - only regenerate for non-AJAX requests to avoid session issues
if (!isset($_SESSION['initiated']) && !$is_ajax) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Initialize session activity tracking
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
}

// Session timeout using configuration constant
$session_timeout = defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // For AJAX requests to API endpoints, don't destroy session - just return error
    if ($is_ajax && strpos($current_page, '-api.php') !== false) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Session expired. Please refresh the page and login again.',
            'error_code' => 'SESSION_EXPIRED',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }

    // For regular pages, destroy session and redirect
    error_log("Session expired for user_id: " . ($_SESSION['user_id'] ?? 'unknown') . ", IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

// CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in (allow register-student.php and login.php for demo)

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Allow access to registration and login pages
    if ($current_page !== 'register-student.php' && $current_page !== 'login.php' && $current_page !== 'login_new.php' && $current_page !== 'forgot-password.php' && $current_page !== 'reset-password.php') {
        error_log("Session check failed: user_id or role not set for page: $current_page");

        if ($is_ajax) {
            // For AJAX requests, return JSON error instead of HTML
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Session expired. Please refresh the page and login again.',
                'error_code' => 'SESSION_EXPIRED',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        } else {
            // For regular requests, use HTML redirect
            echo "<script>window.location.href='login.php';</script>";
            echo "<p>If you are not redirected, click this link <a href='login.php'>click here</a>.</p>";
            exit;
        }
    }
}

// Validate session data
if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
    error_log("Session check failed: user_id or role is empty");

    if ($is_ajax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid session data. Please login again.',
            'error_code' => 'INVALID_SESSION',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    } else {
        session_destroy();
        echo "<script>window.location.href='login.php';</script>";
        echo "<p>If you are not redirected, <a href='login.php'>click here</a>.</p>";
        exit;
    }
}

// Additional validation for user_id format
if (!is_numeric($_SESSION['user_id'])) {
    error_log("Session check failed: user_id is not numeric");

    if ($is_ajax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid session format. Please login again.',
            'error_code' => 'INVALID_SESSION_FORMAT',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    } else {
        session_destroy();
        echo "<script>window.location.href='login.php';</script>";
        echo "<p>If you are not redirected, <a href='login.php'>click here</a>.</p>";
        exit;
    }
}

// Enhanced role-based access control
function require_role($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }

    // Check both session role and actual role (for HOD users who login as lecturers)
    $userRole = $_SESSION['role'];
    $actualRole = $_SESSION['actual_role'] ?? $_SESSION['role'];
    
    $hasAccess = in_array($userRole, $roles) || in_array($actualRole, $roles);
    
    if (!$hasAccess) {
        // Log unauthorized access attempt
        error_log("Unauthorized access attempt: User ID {$_SESSION['user_id']} tried to access restricted area. Required roles: " . implode(', ', $roles) . ", User role: {$userRole}, Actual role: {$actualRole}");

        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($is_ajax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Insufficient permissions to access this resource.',
                'error_code' => 'INSUFFICIENT_PERMISSIONS',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        } else {
            // Use direct redirect instead of header to avoid issues
            echo "<script>window.location.href='login.php?error=unauthorized';</script>";
            echo "<p>If you are not redirected, <a href='login.php?error=unauthorized'>click here</a>.</p>";
            exit;
        }
    }
}

// Generate CSRF token for forms
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input data
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate file upload
function validate_file_upload($file, $allowed_types = [], $max_size = 5242880) { // 5MB default
    $errors = [];

    // Check if file was uploaded
    if (!isset($file['error']) || is_array($file['error'])) {
        $errors[] = 'Invalid file upload parameters.';
        return $errors;
    }

    // Check for upload errors
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            $errors[] = 'No file was uploaded.';
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = 'File size exceeds maximum allowed size.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errors[] = 'File was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errors[] = 'Missing temporary directory.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errors[] = 'Failed to write file to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $errors[] = 'File upload stopped by extension.';
            break;
        default:
            $errors[] = 'Unknown upload error.';
            break;
    }

    if (!empty($errors)) {
        return $errors;
    }

    // Validate file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds maximum allowed size of ' . ($max_size / 1024 / 1024) . 'MB.';
    }

    // Validate file type
    if (!empty($allowed_types)) {
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $file['tmp_name']);
        finfo_close($file_info);

        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowed_types);
        }
    }

    return $errors;
}

// Secure file upload function
function secure_file_upload($file, $destination, $allowed_types = [], $max_size = 5242880) {
    $errors = validate_file_upload($file, $allowed_types, $max_size);

    if (!empty($errors)) {
        return ['success' => false, 'errors' => $errors];
    }

    // Generate secure filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $secure_filename = bin2hex(random_bytes(16)) . '.' . strtolower($extension);

    // Ensure destination directory exists and is secure
    $destination_dir = dirname($destination);
    if (!is_dir($destination_dir)) {
        mkdir($destination_dir, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination . $secure_filename)) {
        return ['success' => true, 'filename' => $secure_filename];
    } else {
        return ['success' => false, 'errors' => ['Failed to move uploaded file.']];
    }
}

// Rate limiting function
function check_rate_limit($action, $limit = 5, $time_window = 300) { // 5 attempts per 5 minutes
    $key = $action . '_' . $_SESSION['user_id'];

    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }

    $now = time();

    // Clean old entries
    foreach ($_SESSION['rate_limit'] as $k => $v) {
        if ($now - $v['time'] > $time_window) {
            unset($_SESSION['rate_limit'][$k]);
        }
    }

    // Check current limit
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = ['count' => 0, 'time' => $now];
    }

    if ($_SESSION['rate_limit'][$key]['count'] >= $limit) {
        return false;
    }

    $_SESSION['rate_limit'][$key]['count']++;
    return true;
}

// Authentication check function
function checkAuthentication() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        // Detect AJAX requests
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($is_ajax) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Authentication required. Please login.',
                'error_code' => 'AUTH_REQUIRED',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        } else {
            header("Location: login.php");
            exit;
        }
    }

    // Additional validation for user_id format
    if (!is_numeric($_SESSION['user_id'])) {
        session_destroy();
        header("Location: login.php?error=invalid_session");
        exit;
    }
}
?>
