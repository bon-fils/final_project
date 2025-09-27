<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'hod';

require_once 'config.php';
require_once 'cache_utils.php';

// Test the API directly
$_POST['action'] = 'get_overview_stats';
$_POST['department_id'] = 1;

ob_start(); // Capture output
require_once 'api/department-reports-api.php';
$output = ob_get_clean();

echo "API Output:\n";
echo $output;
echo "\n\nDebug Info:\n";
echo "Session: " . print_r($_SESSION, true);
echo "POST: " . print_r($_POST, true);
?>