<?php
// Simple test to check if the page loads
echo "Testing register-student.php<br>";

try {
    require_once 'session_check.php';
    echo "✓ session_check.php loaded successfully<br>";

    $csrf_token = generate_csrf_token();
    echo "✓ CSRF token generated: " . substr($csrf_token, 0, 10) . "...<br>";

    require_once 'config.php';
    echo "✓ config.php loaded successfully<br>";

    // Test database connection
    $testQuery = $pdo->query("SELECT COUNT(*) as count FROM departments");
    $result = $testQuery->fetch();
    echo "✓ Database connected, departments count: " . $result['count'] . "<br>";

    echo "<br><strong>✅ All dependencies working correctly!</strong><br>";
    echo "The issue might be with the browser cache or server configuration.<br>";
    echo "Try: <a href='register-student.php'>Direct Link</a><br>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>