<!DOCTYPE html>
<html>
<head>
    <title>Debug Login Process</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .step { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔍 Debug Login Process for HOD Access</h1>
    
    <?php
    session_start();
    
    echo "<div class='step'>";
    echo "<h2>Step 1: Current Session Status</h2>";
    
    if (empty($_SESSION)) {
        echo "<div class='error'>";
        echo "<h3>❌ No Session Data</h3>";
        echo "<p>You are not logged in. Please follow these steps:</p>";
        echo "<ol>";
        echo "<li><strong>Go to login page:</strong> <a href='login.php'>login.php</a></li>";
        echo "<li><strong>Login as HOD user</strong> with role = 'lecturer'</li>";
        echo "<li><strong>Come back to this page</strong> to verify session</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div class='success'>";
        echo "<h3>✅ Session Active</h3>";
        echo "<p><strong>User ID:</strong> " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
        echo "<p><strong>Username:</strong> " . ($_SESSION['username'] ?? 'Not set') . "</p>";
        echo "<p><strong>Session Role:</strong> " . ($_SESSION['role'] ?? 'Not set') . "</p>";
        echo "<p><strong>Actual Role:</strong> " . ($_SESSION['actual_role'] ?? 'Not set') . "</p>";
        echo "<p><strong>Login Time:</strong> " . (isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'Not set') . "</p>";
        echo "</div>";
        
        echo "<h3>Full Session Data:</h3>";
        echo "<pre>";
        print_r($_SESSION);
        echo "</pre>";
    }
    echo "</div>";
    
    if (!empty($_SESSION) && isset($_SESSION['user_id'])) {
        echo "<div class='step'>";
        echo "<h2>Step 2: Test Role Access Functions</h2>";
        
        // Include session_check to get the require_role function
        try {
            require_once "session_check.php";
            echo "<div class='success'><p>✅ session_check.php loaded successfully</p></div>";
            
            // Test lecturer role
            echo "<h3>Testing Lecturer Role Access:</h3>";
            try {
                require_role(['lecturer']);
                echo "<div class='success'><p>✅ LECTURER role check PASSED</p></div>";
            } catch (Exception $e) {
                echo "<div class='error'><p>❌ LECTURER role check FAILED: " . $e->getMessage() . "</p></div>";
            }
            
            // Test HOD role
            echo "<h3>Testing HOD Role Access:</h3>";
            try {
                require_role(['hod']);
                echo "<div class='success'><p>✅ HOD role check PASSED</p></div>";
            } catch (Exception $e) {
                echo "<div class='error'><p>❌ HOD role check FAILED: " . $e->getMessage() . "</p></div>";
            }
            
            // Test combined role
            echo "<h3>Testing Combined Role Access ['lecturer', 'hod']:</h3>";
            try {
                require_role(['lecturer', 'hod']);
                echo "<div class='success'><p>✅ COMBINED role check PASSED</p></div>";
            } catch (Exception $e) {
                echo "<div class='error'><p>❌ COMBINED role check FAILED: " . $e->getMessage() . "</p></div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'><p>❌ Failed to load session_check.php: " . $e->getMessage() . "</p></div>";
        }
        echo "</div>";
        
        echo "<div class='step'>";
        echo "<h2>Step 3: Test Page Access</h2>";
        echo "<p>If the role checks above passed, these links should work:</p>";
        echo "<ul>";
        echo "<li><a href='lecturer-dashboard.php' target='_blank'>Lecturer Dashboard</a></li>";
        echo "<li><a href='hod-dashboard.php' target='_blank'>HOD Dashboard</a></li>";
        echo "<li><a href='test-hod-access.php' target='_blank'>Original Test Page</a></li>";
        echo "</ul>";
        echo "</div>";
    }
    
    echo "<div class='step'>";
    echo "<h2>Troubleshooting Steps</h2>";
    echo "<div class='warning'>";
    echo "<h3>If you're still getting redirected to index.php:</h3>";
    echo "<ol>";
    echo "<li><strong>Clear browser cache and cookies</strong> completely</li>";
    echo "<li><strong>Restart your browser</strong></li>";
    echo "<li><strong>Try logging in again</strong> with HOD credentials and role='lecturer'</li>";
    echo "<li><strong>Check if you're accessing the correct URL</strong> (http://localhost/final_project_1/...)</li>";
    echo "<li><strong>Check browser console</strong> for any JavaScript errors</li>";
    echo "</ol>";
    echo "</div>";
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>Quick Actions</h2>";
    echo "<p><a href='login.php'>🔐 Go to Login</a></p>";
    echo "<p><a href='login.php?action=logout'>🚪 Logout (Clear Session)</a></p>";
    echo "<p><a href='javascript:location.reload()'>🔄 Refresh This Page</a></p>";
    echo "</div>";
    ?>
</body>
</html>
