<?php
/**
 * Secure Logout Helper
 * Provides consistent and secure logout functionality across all pages
 */

/**
 * Generate a secure logout URL with CSRF token
 * @return string The secure logout URL
 */
function getSecureLogoutUrl() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Generate CSRF token if not exists
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // Build secure logout URL
    $logout_url = 'logout.php?token=' . urlencode($_SESSION['csrf_token']);
    
    return $logout_url;
}

/**
 * Generate a secure logout link HTML
 * @param string $class CSS classes for the link
 * @param string $icon Font Awesome icon class
 * @param string $text Link text
 * @param bool $confirm Whether to show confirmation dialog
 * @return string The complete logout link HTML
 */
function getSecureLogoutLink($class = '', $icon = 'fas fa-sign-out-alt', $text = 'Logout', $confirm = true) {
    $logout_url = getSecureLogoutUrl();
    
    $onclick = '';
    if ($confirm) {
        $onclick = "onclick=\"return confirm('Are you sure you want to logout?')\"";
    }
    
    $html = sprintf(
        '<a href="%s" class="%s" %s title="Secure Logout">',
        htmlspecialchars($logout_url),
        htmlspecialchars($class),
        $onclick
    );
    
    if (!empty($icon)) {
        $html .= sprintf('<i class="%s me-2"></i>', htmlspecialchars($icon));
    }
    
    $html .= htmlspecialchars($text) . '</a>';
    
    return $html;
}

/**
 * Generate logout button HTML
 * @param string $class CSS classes for the button
 * @param string $icon Font Awesome icon class
 * @param string $text Button text
 * @return string The complete logout button HTML
 */
function getSecureLogoutButton($class = 'btn btn-outline-danger', $icon = 'fas fa-sign-out-alt', $text = 'Logout') {
    $logout_url = getSecureLogoutUrl();
    
    $html = sprintf(
        '<button type="button" class="%s" onclick="secureLogout()" title="Secure Logout">',
        htmlspecialchars($class)
    );
    
    if (!empty($icon)) {
        $html .= sprintf('<i class="%s me-2"></i>', htmlspecialchars($icon));
    }
    
    $html .= htmlspecialchars($text) . '</button>';
    
    // Add JavaScript for secure logout
    $html .= '
    <script>
    function secureLogout() {
        if (confirm("Are you sure you want to logout?")) {
            // Clear any sensitive data from localStorage/sessionStorage
            if (typeof(Storage) !== "undefined") {
                localStorage.clear();
                sessionStorage.clear();
            }
            
            // Redirect to secure logout URL
            window.location.href = "' . htmlspecialchars($logout_url) . '";
        }
    }
    </script>';
    
    return $html;
}

/**
 * Check if current user can logout (has active session)
 * @return bool True if user can logout
 */
function canLogout() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user info for logout logging
 * @return array User information
 */
function getCurrentUserInfo() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'first_name' => $_SESSION['first_name'] ?? null,
        'last_name' => $_SESSION['last_name'] ?? null
    ];
}
?>