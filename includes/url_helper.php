<?php
/**
 * URL Helper Functions
 * Provides utility functions for generating URLs
 */

if (!function_exists('base_url')) {
    /**
     * Generate a base URL
     * 
     * @param string $path Optional path to append to the base URL
     * @return string Full URL
     */
    function base_url($path = '') {
        static $base_url = null;
        
        if ($base_url === null) {
            // Check if running in a web context
            if (isset($_SERVER['HTTP_HOST'])) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || 
                            $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
                $base_url = rtrim(APP_URL, '/') . '/';
            } else {
                // Fallback to configured APP_URL if not in web context
                $base_url = rtrim(defined('APP_URL') ? APP_URL : 'http://localhost/final_project_1', '/') . '/';
            }
        }
        
        // Remove any leading slashes from path to prevent double slashes
        $path = ltrim($path, '/');
        
        return $base_url . $path;
    }
}

if (!function_exists('site_url')) {
    /**
     * Generate a full URL to a site resource
     * 
     * @param string $uri URI to the resource
     * @param array $params Optional query parameters
     * @return string Full URL
     */
    function site_url($uri = '', $params = []) {
        $url = base_url($uri);
        
        if (!empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }
        
        return $url;
    }
}

if (!function_exists('current_url')) {
    /**
     * Get the current URL
     * 
     * @param bool $with_query Include query string
     * @return string Current URL
     */
    function current_url($with_query = true) {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
               '://' . $_SERVER['HTTP_HOST'] . 
               $_SERVER['REQUEST_URI'];
        
        if (!$with_query && ($pos = strpos($url, '?')) !== false) {
            $url = substr($url, 0, $pos);
        }
        
        return $url;
    }
}

if (!function_exists('logout_url')) {
    /**
     * Generate a logout URL with CSRF protection
     * 
     * @param string $redirect_url URL to redirect to after logout
     * @return string Logout URL with CSRF token
     */
    function logout_url($redirect_url = '') {
        $url = site_url('logout.php');
        
        // Add CSRF token if available
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['csrf_token'])) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'token=' . urlencode($_SESSION['csrf_token']);
        }
        
        // Add redirect URL if provided
        if ($redirect_url) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'redirect=' . urlencode($redirect_url);
        }
        
        return $url;
    }
}
