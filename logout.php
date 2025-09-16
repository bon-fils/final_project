<?php
// logout.php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Prevent caching (important to stop back button access)
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect to login page
header("Location: login.php");
exit;
?>
