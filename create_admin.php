<?php
require_once "db.php";

// Change these values for your first admin
$username = "admin";
$email = "admin@rp.ac.rw";
$password = "admin123"; // plain text password (⚠️ not secure in production)
$role = "admin";

try {
    // Check if admin already exists
    $check = $pdo->prepare("SELECT * FROM users WHERE email = :email OR username = :username");
    $check->execute(["email" => $email, "username" => $username]);
    if ($check->rowCount() > 0) {
        echo "⚠️ Admin already exists!";
        exit;
    }

    // Insert admin (no hashing)
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) 
                           VALUES (:username, :email, :password, :role, NOW())");
    $stmt->execute([
        "username" => $username,
        "email" => $email,
        "password" => $password,
        "role" => $role
    ]);

    echo "✅ Admin created successfully!<br>";
    echo "👉 Username: $username<br>";
    echo "👉 Email: $email<br>";
    echo "👉 Password: $password<br>";
    echo "👉 Role: $role<br>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
