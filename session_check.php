<?php
// session_check.php
error_reporting(0);
session_start();

// Prevent caching of protected pages (stops back-button after logout)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    // Not logged in → redirect to login
    header("Location: login.php");
    exit;
}

// Optional: Restrict page access by role
function require_role($roles) {
    if (!in_array($_SESSION['role'], (array)$roles)) {
        header("Location: login.php");
        exit;
    }
}
?>
