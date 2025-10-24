<?php
/**
 * Enhanced Logout Script with CSRF Protection
 * Provides secure logout with proper session cleanup and user feedback
 * Version: 3.0 - Updated to use URL helper
 */

// Load configuration and URL helper
require_once __DIR__ . '/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enhanced security headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// CSRF Protection for logout (optional but recommended)
$csrf_valid = true;
if (isset($_GET['token'])) {
    $csrf_valid = isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['token']);
}

// Log the logout action for security audit
$user_data = [];
if (isset($_SESSION['user_id'])) {
    $user_data = [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'unknown',
        'role' => $_SESSION['role'] ?? 'unknown',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'logout_time' => date('Y-m-d H:i:s'),
        'csrf_valid' => $csrf_valid
    ];

    // Log to error log (in production, this should go to a proper logging system)
    error_log("User logout: " . json_encode($user_data));
}

// Store logout reason for display
$logout_reason = 'success';
if (!$csrf_valid && isset($_GET['token'])) {
    $logout_reason = 'csrf_error';
    error_log("CSRF token mismatch during logout for user: " . ($user_data['username'] ?? 'unknown'));
}

// Enhanced session cleanup
if (isset($_SESSION)) {
    // Clear all session variables
    $_SESSION = [];
    
    // Destroy the session completely
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Clear session cookie with enhanced security
$session_name = session_name();
if (isset($_COOKIE[$session_name])) {
    // Clear the session cookie with all security parameters
    setcookie(
        $session_name, 
        '', 
        [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ]
    );
}

// Clear any remember me cookies if they exist
$remember_cookies = ['remember_user', 'remember_token', 'user_session'];
foreach ($remember_cookies as $cookie_name) {
    if (isset($_COOKIE[$cookie_name])) {
        setcookie(
            $cookie_name, 
            '', 
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
}

// Set default redirect URL with logout message
$redirect_url = site_url('index.php', [
    'logout' => $logout_reason,
    't' => time() // Add timestamp to prevent caching
]);

// Check for custom redirect URL
if (isset($_GET['redirect']) && filter_var($_GET['redirect'], FILTER_VALIDATE_URL)) {
    $redirect_url = $_GET['redirect'];
    
    // Add logout parameter if not already present
    if (strpos($redirect_url, '?') === false) {
        $redirect_url .= '?logout=' . urlencode($logout_reason);
    } else {
        $redirect_url .= '&logout=' . urlencode($logout_reason);
    }
    
    // Add timestamp to prevent caching
    $redirect_url .= '&t=' . time();
}

// Ensure we have a valid URL
if (!headers_sent()) {
    header('Location: ' . $redirect_url);
    exit;
}

// Fallback in case headers were already sent
echo '<script>window.location.href = ' . json_encode($redirect_url) . ';</script>';
exit;
?>
