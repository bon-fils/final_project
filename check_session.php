<?php
session_start();

echo "<h2>Session Debug Information</h2>";

echo "<h3>Current Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>Session Status:</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";

if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green;'>✅ User ID: " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ No user_id in session</p>";
}

if (isset($_SESSION['role'])) {
    echo "<p style='color: green;'>✅ Role: " . $_SESSION['role'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ No role in session</p>";
}

if (isset($_SESSION['username'])) {
    echo "<p style='color: green;'>✅ Username: " . $_SESSION['username'] . "</p>";
} else {
    echo "<p style='color: red;'>❌ No username in session</p>";
}

// Check if user has admin access
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    echo "<p style='color: green;'>✅ User has admin access</p>";
} else {
    echo "<p style='color: red;'>❌ User does NOT have admin access</p>";
    echo "<p>You need to login as an admin user to access the lecturer registration form.</p>";
}

echo "<br><a href='login.php'>← Go to Login</a>";
echo "<br><a href='admin-register-lecturer.php'>← Back to Register Lecturer</a>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>
