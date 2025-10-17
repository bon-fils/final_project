<?php
require_once "config.php";

echo "=== LOGIN TEST ===\n\n";

// Use environment variables for test credentials
$email = $_ENV['TEST_ADMIN_EMAIL'] ?? "admin@rp.ac.rw";
$password = $_ENV['TEST_ADMIN_PASSWORD'] ?? null;
$role = "admin";

if (!$password) {
    echo "❌ Test password not configured. Set TEST_ADMIN_PASSWORD environment variable.\n";
    echo "For security, never hardcode passwords in source code.\n";
    exit(1);
}

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