<?php
// session_check.php
error_reporting(0);
session_start();

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
