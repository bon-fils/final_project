<?php
require_once "config.php";

echo "=== LOGIN TEST ===\n\n";

// Test admin login
$email = "admin@rp.ac.rw";
$password = "admin123";
$role = "admin";

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND role = :role");
    $stmt->execute(['email' => $email, 'role' => $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "❌ User not found\n";
        exit;
    }

    echo "✅ User found: " . $user['username'] . "\n";
    echo "Role: " . $user['role'] . "\n";
    echo "Status: " . $user['status'] . "\n";

    // Test password verification
    $isValid = password_verify($password, $user['password']);
    echo "Password valid: " . ($isValid ? "✅" : "❌") . "\n";

    if ($isValid) {
        echo "\n🎉 Login test successful!\n";
    } else {
        echo "\n❌ Password verification failed\n";
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>