<?php
session_start();

echo "<h1>HOD Access Debug Test</h1>";

echo "<h2>Session Data (Before session_check.php)</h2>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
echo "<p><strong>Actual Role:</strong> " . ($_SESSION['actual_role'] ?? 'Not set') . "</p>";
echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p><strong>Username:</strong> " . ($_SESSION['username'] ?? 'Not set') . "</p>";
echo "<p><strong>Login Time:</strong> " . ($_SESSION['login_time'] ?? 'Not set') . "</p>";

echo "<h2>All Session Variables</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if basic session variables are set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "<div style='background: #ffcccc; padding: 10px; border: 1px solid red;'>";
    echo "<h3>❌ Session Check Will Fail</h3>";
    echo "<p>Missing required session variables. You need to login first.</p>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #ccffcc; padding: 10px; border: 1px solid green;'>";
    echo "<h3>✅ Basic Session Variables Present</h3>";
    echo "<p>Now testing session_check.php...</p>";
    echo "</div>";
    
    // Now include session_check.php
    try {
        require_once "session_check.php";
        echo "<p style='color: green;'>✅ session_check.php passed</p>";
        
        // Test role functions
        echo "<h2>Testing Role Access</h2>";
        
        try {
            require_role(['lecturer']);
            echo "<p style='color: green;'>✅ Successfully passed lecturer role check</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Failed lecturer role check: " . $e->getMessage() . "</p>";
        }

        try {
            require_role(['hod']);
            echo "<p style='color: green;'>✅ Successfully passed HOD role check</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Failed HOD role check: " . $e->getMessage() . "</p>";
        }

        echo "<h2>Navigation Links</h2>";
        echo "<p><a href='lecturer-dashboard.php'>Go to Lecturer Dashboard</a></p>";
        echo "<p><a href='hod-dashboard.php'>Go to HOD Dashboard</a></p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ session_check.php failed: " . $e->getMessage() . "</p>";
    }
}

echo "<p><a href='login.php'>Back to Login</a></p>";
?>
