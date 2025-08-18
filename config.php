<?php
// Database configuration
$host = "localhost";
$db_name = "rp_attendance_system";
$username = "root"; // Change if not using default XAMPP/MAMP credentials
$password = "";     // Change if your DB has a password

try {
    // Create a PDO connection
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);

    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
