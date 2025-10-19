<?php
/**
 * Enhanced Logout Script
 * Provides better user experience with feedback and security
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout action for security
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'] ?? 'unknown';
    $logout_time = date('Y-m-d H:i:s');

    // Log to error log (in production, this should go to a proper logging system)
    error_log("User logout: ID=$user_id, Role=$user_role, Time=$logout_time");
}

// Clear all session variables
$_SESSION = [];

// Destroy the session completely
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Prevent caching to stop back button access
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Redirect to login page with success message
header("Location: login.php?logout=success");
exit;
?>
