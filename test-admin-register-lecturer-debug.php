<?php
/**
 * Debug test for admin-register-lecturer.php
 * This file helps identify issues preventing the page from loading
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug test...\n";

// Test basic includes
try {
    echo "Testing config.php include...\n";
    require_once "config.php";
    echo "✓ config.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ Error loading config.php: " . $e->getMessage() . "\n";
    exit;
}

try {
    echo "Testing session_check.php include...\n";
    require_once "session_check.php";
    echo "✓ session_check.php loaded successfully\n";
} catch (Exception $e) {
    echo "✗ Error loading session_check.php: " . $e->getMessage() . "\n";
    exit;
}

// Test backend classes
echo "Testing backend classes...\n";
$backendFiles = [
    "backend/classes/DatabaseManager.php",
    "backend/classes/InputValidator.php", 
    "backend/classes/LecturerRegistrationManager.php",
    "backend/classes/DepartmentManager.php"
];

foreach ($backendFiles as $file) {
    if (file_exists($file)) {
        try {
            require_once $file;
            echo "✓ $file loaded successfully\n";
        } catch (Exception $e) {
            echo "✗ Error loading $file: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✗ File not found: $file\n";
    }
}

// Test database connection
try {
    echo "Testing database connection...\n";
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT 1");
        echo "✓ Database connection working\n";
    } else {
        echo "✗ PDO not available\n";
    }
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}

// Test manager initialization
try {
    echo "Testing manager initialization...\n";
    if (class_exists('DatabaseManager')) {
        $dbManager = DatabaseManager::getInstance($pdo);
        echo "✓ DatabaseManager initialized\n";
    } else {
        echo "✗ DatabaseManager class not available\n";
    }
} catch (Exception $e) {
    echo "✗ Manager initialization error: " . $e->getMessage() . "\n";
}

echo "\nDebug test completed. If all tests pass, the main page should load correctly.\n";
?>
