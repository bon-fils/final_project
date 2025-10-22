<?php
/**
 * Simple debug version to isolate the white page issue
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!-- Debug: Starting page load -->\n";

try {
    echo "<!-- Debug: Loading config -->\n";
    require_once "config.php";
    echo "<!-- Debug: Config loaded -->\n";
    
    echo "<!-- Debug: Loading session check -->\n";
    require_once "session_check.php";
    echo "<!-- Debug: Session check loaded -->\n";
    
    echo "<!-- Debug: Checking role -->\n";
    require_role(['admin']);
    echo "<!-- Debug: Role check passed -->\n";
    
    echo "<!-- Debug: Setting CSRF token -->\n";
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo "<!-- Debug: CSRF token set -->\n";
    
    echo "<!-- Debug: Setting headers -->\n";
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    echo "<!-- Debug: Headers set -->\n";
    
} catch (Exception $e) {
    echo "<!-- Debug: Error in initial setup: " . htmlspecialchars($e->getMessage()) . " -->\n";
    die("Error in initial setup: " . htmlspecialchars($e->getMessage()));
}

echo "<!-- Debug: Starting HTML output -->\n";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Debug - Register Lecturer | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success">
            <h4>Debug Test - Page Loading Successfully!</h4>
            <p>If you can see this message, the basic page structure is working.</p>
            <p><strong>Session User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Not set'; ?></p>
            <p><strong>Session Role:</strong> <?php echo $_SESSION['role'] ?? 'Not set'; ?></p>
            <p><strong>CSRF Token:</strong> <?php echo isset($_SESSION['csrf_token']) ? 'Set' : 'Not set'; ?></p>
        </div>
        
        <div class="alert alert-info">
            <h5>Next Steps:</h5>
            <ol>
                <li>If this page loads correctly, the issue is in the main admin-register-lecturer.php file</li>
                <li>Check the browser's developer console for JavaScript errors</li>
                <li>Check the network tab for failed resource loads</li>
            </ol>
        </div>
        
        <a href="admin-register-lecturer.php" class="btn btn-primary">
            Try Main Page Again
        </a>
    </div>
</body>
</html>
