<?php
// Test the standalone registration page
echo "<h1>Testing Standalone Registration Page</h1>";

try {
    // Test database connection
    require_once 'config.php';
    echo "✓ Database connection successful<br>";

    // Test CSRF token generation
    $csrf_token = bin2hex(random_bytes(32));
    echo "✓ CSRF token generated: " . substr($csrf_token, 0, 10) . "...<br>";

    // Test department loading
    $deptStmt = $pdo->query("SELECT COUNT(*) as count FROM departments");
    $result = $deptStmt->fetch();
    echo "✓ Departments available: " . $result['count'] . "<br>";

    // Test options loading
    $optStmt = $pdo->query("SELECT COUNT(*) as count FROM options");
    $result = $optStmt->fetch();
    echo "✓ Options available: " . $result['count'] . "<br>";

    echo "<br><strong>✅ Standalone page should work!</strong><br>";
    echo "<a href='register-student-standalone.php'>Test Standalone Registration Page</a><br>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>