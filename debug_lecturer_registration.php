<?php
/**
 * Debug Lecturer Registration
 * This script helps identify why admin-register-lecturer.php returns nothing
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debugging Lecturer Registration</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .warning { color: orange; }
    .card { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .debug-step { margin: 10px 0; padding: 10px; border-left: 3px solid #007cba; }
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>📋 Step-by-Step Debug Process</h2>";
    
    // Step 1: Check if config.php loads
    echo "<div class='debug-step'>";
    echo "<h3>Step 1: Loading config.php</h3>";
    try {
        require_once "config.php";
        echo "<p class='success'>✅ config.php loaded successfully</p>";
        echo "<p><strong>Database connection:</strong> " . (isset($pdo) ? 'Available' : 'Not available') . "</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Failed to load config.php: " . $e->getMessage() . "</p>";
        throw $e;
    }
    echo "</div>";
    
    // Step 2: Check session_check.php
    echo "<div class='debug-step'>";
    echo "<h3>Step 2: Loading session_check.php</h3>";
    try {
        require_once "session_check.php";
        echo "<p class='success'>✅ session_check.php loaded successfully</p>";
        echo "<p><strong>Session status:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</p>";
        echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
        echo "<p><strong>User Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Failed to load session_check.php: " . $e->getMessage() . "</p>";
        throw $e;
    }
    echo "</div>";
    
    // Step 3: Check role requirement
    echo "<div class='debug-step'>";
    echo "<h3>Step 3: Testing require_role(['admin'])</h3>";
    try {
        if (function_exists('require_role')) {
            echo "<p class='info'>🔍 require_role function exists</p>";
            
            // Check if user has admin role
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                echo "<p class='success'>✅ User has admin role - access should be granted</p>";
            } else {
                echo "<p class='error'>❌ User does not have admin role</p>";
                echo "<p><strong>Current role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
                echo "<p><strong>Required role:</strong> admin</p>";
                
                // This would cause the redirect/exit
                echo "<p class='warning'>⚠️ This is likely why the page returns nothing - role check fails</p>";
            }
        } else {
            echo "<p class='error'>❌ require_role function not found</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error checking role: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Step 4: Test database connection
    echo "<div class='debug-step'>";
    echo "<h3>Step 4: Testing Database Connection</h3>";
    try {
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
            $dept_count = $stmt->fetchColumn();
            echo "<p class='success'>✅ Database connection works</p>";
            echo "<p><strong>Departments count:</strong> $dept_count</p>";
        } else {
            echo "<p class='error'>❌ PDO not available</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Database error: " . $e->getMessage() . "</p>";
    }
    echo "</div>";
    
    // Step 5: Check file permissions
    echo "<div class='debug-step'>";
    echo "<h3>Step 5: File Permissions Check</h3>";
    $lecturer_file = 'admin-register-lecturer.php';
    if (file_exists($lecturer_file)) {
        echo "<p class='success'>✅ admin-register-lecturer.php exists</p>";
        echo "<p><strong>File size:</strong> " . filesize($lecturer_file) . " bytes</p>";
        echo "<p><strong>Readable:</strong> " . (is_readable($lecturer_file) ? 'Yes' : 'No') . "</p>";
    } else {
        echo "<p class='error'>❌ admin-register-lecturer.php not found</p>";
    }
    echo "</div>";
    
    echo "</div>";
    
    // Provide solutions
    echo "<div class='card'>";
    echo "<h2>🛠️ Potential Solutions</h2>";
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo "<h3>Issue: User Role Problem</h3>";
        echo "<p class='warning'>The most likely issue is that you're not logged in as an admin user.</p>";
        echo "<ol>";
        echo "<li><strong>Login as Admin:</strong> Go to <a href='login.php'>login page</a> and login with admin credentials</li>";
        echo "<li><strong>Check User Role:</strong> Ensure your user account has role='admin' in the database</li>";
        echo "<li><strong>Session Issue:</strong> Clear browser cookies and login again</li>";
        echo "</ol>";
        
        // Show how to fix in database
        echo "<h4>Database Fix (if needed):</h4>";
        echo "<pre>";
        echo "-- Check current user roles\n";
        echo "SELECT id, username, email, role FROM users WHERE role = 'admin';\n\n";
        echo "-- Update a user to admin (replace 'your_email@example.com' with your email)\n";
        echo "UPDATE users SET role = 'admin' WHERE email = 'your_email@example.com';";
        echo "</pre>";
    }
    
    echo "<h3>Quick Test Links:</h3>";
    echo "<p><a href='login.php' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Page</a></p>";
    echo "<p><a href='admin-register-lecturer.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Try Lecturer Registration</a></p>";
    echo "</div>";
    
    // Show current session data
    echo "<div class='card'>";
    echo "<h2>📊 Current Session Data</h2>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<p class='error'>❌ Critical error during debug: " . $e->getMessage() . "</p>";
    echo "<p class='error'>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    echo "<p class='error'>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}

echo "<hr>";
echo "<p><em>Debug completed!</em></p>";
?>
