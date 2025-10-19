<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=rp_attendance_system;charset=utf8mb4', 'root', '');
    echo 'Database connection successful';
} catch (Exception $e) {
    echo 'Database connection failed: ' . $e->getMessage();
}
?>