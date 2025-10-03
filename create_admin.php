<?php
require_once "config.php";

// Change these values for your first admin
$username = "admin";
$email = "admin@rp.ac.rw";
$plain_password = "admin123"; // plain text password
$role = "admin";

// Hash the password securely
$hashed_password = password_hash($plain_password, PASSWORD_ARGON2ID);

try {
    // Check if admin already exists
    $check = $pdo->prepare("SELECT * FROM users WHERE email = :email OR username = :username");
    $check->execute(["email" => $email, "username" => $username]);
    if ($check->rowCount() > 0) {
        echo "⚠️ Admin already exists!";
        exit;
    }

    // Insert admin with hashed password
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at)
                           VALUES (:username, :email, :password, :role, NOW())");
    $stmt->execute([
        "username" => $username,
        "email" => $email,
        "password" => $hashed_password,
        "role" => $role
    ]);

    echo "✅ Admin created successfully!<br>";
    echo "👉 Username: $username<br>";
    echo "👉 Email: $email<br>";
    echo "👉 Password: $plain_password<br>";
    echo "👉 Role: $role<br>";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
