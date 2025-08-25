<?php
// Database configuration
$host     = "localhost";
$db_name  = "rp_attendance_system";
$username = "root";    // Change if not using default XAMPP/MAMP credentials
$password = "";        // Change if your DB has a password

// Optional: Set default timezone
date_default_timezone_set('Africa/Kigali');

try {
    // Create a PDO connection with UTF-8 encoding
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch results as associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false                   // Use native prepared statements
        ]
    );

} catch (PDOException $e) {
    // If connection fails, terminate script with error message
    die("Database connection failed: " . $e->getMessage());
}
?>
