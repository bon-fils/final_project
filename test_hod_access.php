<?php
/**
 * Test HOD Access and Session Status
 */

session_start();

echo "<h2>HOD Access Test</h2>";

echo "<h3>1. Current Session Status</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ User is logged in (ID: " . $_SESSION['user_id'] . ")</p>";
    
    if (isset($_SESSION['role'])) {
        echo "<p><strong>Role:</strong> " . $_SESSION['role'] . "</p>";
        
        if ($_SESSION['role'] === 'hod') {
            echo "<p style='color: green;'>✅ User has HOD role</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ User role is '" . $_SESSION['role'] . "', not 'hod'</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ No role in session</p>";
    }
} else {
    echo "<p style='color: red;'>❌ No user logged in</p>";
    echo "<p><a href='login.php'>Click here to login</a></p>";
}

// Test the verifyHODAccess function if user is logged in
if (isset($_SESSION['user_id'])) {
    echo "<h3>2. Testing HOD Access Verification</h3>";
    
    try {
        require_once "config.php";
        require_once "includes/hod_auth_helper.php";
        
        $auth_result = verifyHODAccess($pdo, $_SESSION['user_id']);
        
        echo "<h4>Authentication Result:</h4>";
        echo "<pre>";
        print_r($auth_result);
        echo "</pre>";
        
        if ($auth_result['success']) {
            echo "<p style='color: green;'>✅ HOD access verified successfully!</p>";
            echo "<p><strong>Department:</strong> " . $auth_result['department_name'] . " (ID: " . $auth_result['department_id'] . ")</p>";
            echo "<p><strong>User:</strong> " . $auth_result['user']['name'] . "</p>";
            
            echo "<p style='background: #d4edda; padding: 10px; border-radius: 5px; color: #155724;'>";
            echo "<strong>✅ You should be able to access the HOD Attendance Overview page!</strong><br>";
            echo "<a href='hod-attendance-overview.php' style='color: #155724; font-weight: bold;'>Click here to access HOD Attendance Overview</a>";
            echo "</p>";
        } else {
            echo "<p style='color: red;'>❌ HOD access denied</p>";
            echo "<p><strong>Error:</strong> " . $auth_result['error_message'] . "</p>";
            echo "<p><strong>Error Code:</strong> " . $auth_result['error_code'] . "</p>";
            
            // Provide specific guidance based on error
            switch ($auth_result['error_code']) {
                case 'USER_NOT_FOUND':
                    echo "<p style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
                    echo "Your user account was not found or is inactive. Please contact the administrator.";
                    echo "</p>";
                    break;
                    
                case 'INSUFFICIENT_PRIVILEGES':
                    echo "<p style='background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;'>";
                    echo "Your account doesn't have HOD privileges. You need to login with an HOD account.";
                    echo "</p>";
                    break;
                    
                case 'NO_DEPARTMENT_ASSIGNMENT':
                    echo "<p style='background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;'>";
                    echo "You are not assigned to any department. Please contact the administrator to assign you to a department.";
                    echo "</p>";
                    break;
                    
                case 'NOT_DEPARTMENT_HOD':
                    echo "<p style='background: #fff3cd; padding: 10px; border-radius: 5px; color: #856404;'>";
                    echo "You are not designated as the HOD for your assigned department. Please contact the administrator.";
                    echo "</p>";
                    break;
                    
                default:
                    echo "<p style='background: #f8d7da; padding: 10px; border-radius: 5px; color: #721c24;'>";
                    echo "An unexpected error occurred. Please try logging out and logging back in.";
                    echo "</p>";
            }
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error testing HOD access: " . $e->getMessage() . "</p>";
    }
}

echo "<h3>3. Quick Actions</h3>";
echo "<ul>";
echo "<li><a href='login.php'>Login Page</a></li>";
echo "<li><a href='logout.php'>Logout</a></li>";
echo "<li><a href='hod-dashboard.php'>HOD Dashboard</a></li>";
echo "<li><a href='hod-attendance-overview.php'>HOD Attendance Overview</a></li>";
echo "</ul>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
</style>
