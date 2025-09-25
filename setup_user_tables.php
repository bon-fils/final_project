<?php
/**
 * Database Setup Script for User Management System
 * This script ensures all required tables and columns exist
 */

require_once "config.php";

echo "<h1>Setting up User Management Database Tables</h1>";

try {
    // Check if users table exists and has required columns
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    echo "<h2>Checking Users Table...</h2>";

    // Add missing columns to users table
    if (!in_array('status', $columnNames)) {
        echo "Adding 'status' column to users table...<br>";
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active'");
    }

    if (!in_array('updated_at', $columnNames)) {
        echo "Adding 'updated_at' column to users table...<br>";
        $pdo->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    if (!in_array('last_login', $columnNames)) {
        echo "Adding 'last_login' column to users table...<br>";
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL");
    }

    echo "Users table is ready!<br><br>";

    // Check if students table exists
    echo "<h2>Checking Students Table...</h2>";
    try {
        $stmt = $pdo->query("DESCRIBE students");
        echo "Students table exists!<br>";
    } catch (Exception $e) {
        echo "Creating students table...<br>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS students (
                id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                phone VARCHAR(20),
                student_id VARCHAR(50) UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "Students table created!<br>";
    }

    // Check if lecturers table exists
    echo "<h2>Checking Lecturers Table...</h2>";
    try {
        $stmt = $pdo->query("DESCRIBE lecturers");
        echo "Lecturers table exists!<br>";
    } catch (Exception $e) {
        echo "Creating lecturers table...<br>";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lecturers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                phone VARCHAR(20),
                employee_id VARCHAR(50) UNIQUE,
                role ENUM('lecturer','hod') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "Lecturers table created!<br>";
    }

    echo "<br><h2>✅ Database setup completed successfully!</h2>";
    echo "<p>The User Management System is now ready to use.</p>";
    echo "<p><a href='manage-users.php'>Go to User Management</a></p>";

} catch (Exception $e) {
    echo "<h2>❌ Error: " . $e->getMessage() . "</h2>";
    echo "<p>Please check your database configuration and try again.</p>";
}
?>