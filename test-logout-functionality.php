<?php
/**
 * Logout Functionality Test Page
 * Tests all logout scenarios and security features
 */

session_start();
require_once 'includes/logout_helper.php';

// Simulate a logged-in user for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 999;
    $_SESSION['username'] = 'test_user';
    $_SESSION['role'] = 'admin';
    $_SESSION['first_name'] = 'Test';
    $_SESSION['last_name'] = 'User';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout Functionality Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-section {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .test-result {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin: 10px 0;
        }
        .status-good { border-left-color: #28a745; }
        .status-warning { border-left-color: #ffc107; }
        .status-error { border-left-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-sign-out-alt me-2"></i>Logout Functionality Test</h2>
                <p class="text-muted">Testing all logout scenarios and security features</p>
            </div>
        </div>

        <!-- Current Session Info -->
        <div class="test-section">
            <h4><i class="fas fa-user me-2"></i>Current Session Information</h4>
            <div class="test-result status-good">
                <strong>Session Status:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?><br>
                <strong>User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Not set'; ?><br>
                <strong>Username:</strong> <?php echo $_SESSION['username'] ?? 'Not set'; ?><br>
                <strong>Role:</strong> <?php echo $_SESSION['role'] ?? 'Not set'; ?><br>
                <strong>CSRF Token:</strong> <?php echo isset($_SESSION['csrf_token']) ? 'Present (' . substr($_SESSION['csrf_token'], 0, 8) . '...)' : 'Not set'; ?><br>
                <strong>Can Logout:</strong> <?php echo canLogout() ? 'Yes' : 'No'; ?>
            </div>
        </div>

        <!-- Logout Link Tests -->
        <div class="test-section">
            <h4><i class="fas fa-link me-2"></i>Secure Logout Links</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <h6>Standard Logout Link</h6>
                    <div class="test-result">
                        <?php echo getSecureLogoutLink('btn btn-outline-danger'); ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6>Custom Styled Link</h6>
                    <div class="test-result">
                        <?php echo getSecureLogoutLink('text-danger fw-bold', 'fas fa-power-off', 'Sign Out'); ?>
                    </div>
                </div>
            </div>

            <div class="row mt-3">
                <div class="col-md-6">
                    <h6>Logout Button</h6>
                    <div class="test-result">
                        <?php echo getSecureLogoutButton(); ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6>No Confirmation Link</h6>
                    <div class="test-result">
                        <?php echo getSecureLogoutLink('btn btn-sm btn-warning', 'fas fa-sign-out-alt', 'Quick Logout', false); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security Tests -->
        <div class="test-section">
            <h4><i class="fas fa-shield-alt me-2"></i>Security Tests</h4>
            
            <div class="test-result status-good">
                <strong>CSRF Token Generation:</strong> 
                <?php 
                $secure_url = getSecureLogoutUrl();
                echo parse_url($secure_url, PHP_URL_QUERY) ? 'Working' : 'Failed';
                ?>
            </div>
            
            <div class="test-result status-good">
                <strong>Secure URL:</strong> 
                <code><?php echo htmlspecialchars($secure_url); ?></code>
            </div>
            
            <div class="test-result status-warning">
                <strong>Test Invalid CSRF:</strong> 
                <a href="logout.php?token=invalid_token" class="btn btn-sm btn-outline-warning">
                    <i class="fas fa-exclamation-triangle me-1"></i>Test Invalid Token
                </a>
                <small class="text-muted d-block mt-1">This should show a CSRF error message</small>
            </div>
        </div>

        <!-- Manual Tests -->
        <div class="test-section">
            <h4><i class="fas fa-tasks me-2"></i>Manual Test Scenarios</h4>
            
            <div class="test-result">
                <h6>Test Scenarios to Verify:</h6>
                <ol>
                    <li><strong>Normal Logout:</strong> Click any logout link above - should redirect to login with success message</li>
                    <li><strong>CSRF Protection:</strong> Click "Test Invalid Token" - should show CSRF error</li>
                    <li><strong>Session Cleanup:</strong> After logout, try accessing a protected page - should redirect to login</li>
                    <li><strong>Cookie Cleanup:</strong> Check browser dev tools - session cookies should be cleared</li>
                    <li><strong>Back Button:</strong> After logout, try browser back button - should not access protected content</li>
                </ol>
            </div>
        </div>

        <!-- Browser Information -->
        <div class="test-section">
            <h4><i class="fas fa-info-circle me-2"></i>Browser & Server Information</h4>
            <div class="test-result">
                <strong>User Agent:</strong> <?php echo htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'); ?><br>
                <strong>IP Address:</strong> <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?><br>
                <strong>HTTPS:</strong> <?php echo isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'Yes' : 'No'; ?><br>
                <strong>Session Name:</strong> <?php echo session_name(); ?><br>
                <strong>Session ID:</strong> <?php echo substr(session_id(), 0, 8) . '...'; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="test-section">
            <h4><i class="fas fa-tools me-2"></i>Quick Actions</h4>
            <div class="test-result">
                <a href="login.php" class="btn btn-primary me-2">
                    <i class="fas fa-sign-in-alt me-1"></i>Go to Login
                </a>
                <a href="javascript:location.reload()" class="btn btn-secondary me-2">
                    <i class="fas fa-sync me-1"></i>Refresh Test
                </a>
                <button onclick="clearBrowserData()" class="btn btn-warning">
                    <i class="fas fa-trash me-1"></i>Clear Browser Data
                </button>
            </div>
        </div>
    </div>

    <script>
        function clearBrowserData() {
            if (typeof(Storage) !== "undefined") {
                localStorage.clear();
                sessionStorage.clear();
                alert('Browser storage cleared');
            } else {
                alert('Browser storage not supported');
            }
        }

        // Log session info to console
        console.log('Session Test Data:', {
            sessionActive: <?php echo json_encode(session_status() === PHP_SESSION_ACTIVE); ?>,
            userId: <?php echo json_encode($_SESSION['user_id'] ?? null); ?>,
            role: <?php echo json_encode($_SESSION['role'] ?? null); ?>,
            csrfToken: <?php echo json_encode(isset($_SESSION['csrf_token'])); ?>,
            secureUrl: <?php echo json_encode($secure_url); ?>
        });
    </script>
</body>
</html>