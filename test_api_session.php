<?php
// Test the API with session simulation
session_start();
require_once 'config.php';

// Simulate admin session
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Set the action parameter
$_GET['action'] = 'get_assignment_stats';

// Now include the API file
require_once 'api/assign-hod-api.php';
?>