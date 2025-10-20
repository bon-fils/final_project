<?php
/**
 * Security Utilities
 * Enhanced security features for production deployment
 * Version: 1.0
 */

if (!class_exists('SecurityUtils')) {
class SecurityUtils {
    
    /**
     * Force HTTPS redirect in production
     */
    public static function enforceHTTPS() {
        // Only enforce HTTPS in production
        if (defined('APP_ENV') && APP_ENV === 'production') {
            $forceHttps = $_ENV['FORCE_HTTPS'] ?? 'true';
            $httpsRedirect = $_ENV['HTTPS_REDIRECT'] ?? 'true';
            
            if (filter_var($forceHttps, FILTER_VALIDATE_BOOLEAN) && 
                filter_var($httpsRedirect, FILTER_VALIDATE_BOOLEAN)) {
                
                // Check if request is not HTTPS
                if (!self::isHTTPS()) {
                    $redirectURL = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    header("Location: $redirectURL", true, 301);
                    exit();
                }
            }
        }
    }
    
    /**
     * Check if current request is HTTPS
     */
    public static function isHTTPS() {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            $_SERVER['SERVER_PORT'] == 443 ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        );
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://cdnjs.cloudflare.com; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        header("Content-Security-Policy: $csp");
        
        // HSTS (HTTP Strict Transport Security) - only in production with HTTPS
        if (self::isHTTPS() && defined('APP_ENV') && APP_ENV === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Remove server information
        header_remove('X-Powered-By');
        header_remove('Server');
    }
    
    /**
     * Validate file upload security
     */
    public static function validateFileUpload($file, $allowedTypes = [], $maxSize = 5242880) {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'No file uploaded or invalid upload';
            return $errors;
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            $errors[] = 'File too large. Maximum size: ' . self::formatBytes($maxSize);
        }

        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowedTypes) && !in_array($mimeType, $allowedTypes)) {
            $errors[] = 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes);
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = 'Invalid file extension';
        }
        
        // Scan for malicious content
        if (self::containsMaliciousContent($file['tmp_name'])) {
            $errors[] = 'File contains potentially malicious content';
        }
        
        return $errors;
    }
    
    /**
     * Check for malicious content in uploaded files
     */
    private static function containsMaliciousContent($filePath) {
        $content = file_get_contents($filePath);
        
        // Check for common malicious patterns
        $maliciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload=/i',
            '/onerror=/i',
            '/eval\(/i',
            '/base64_decode/i'
        ];
        
        foreach ($maliciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
            return true;
        }
    }

    return false;
}

/**
     * Format bytes to human readable format
     */
    private static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Generate secure random token
     */
    public static function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate CSRF token with timing attack protection
     */
    public static function validateCSRFToken($token, $sessionToken) {
        if (empty($token) || empty($sessionToken)) {
            return false;
        }
        
        return hash_equals($sessionToken, $token);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data, $maxLength = 1000) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        $data = trim($data ?? '');
        
        if (strlen($data) > $maxLength) {
            $data = substr($data, 0, $maxLength);
        }
        
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Rate limiting implementation
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $windowSeconds = 300) {
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'reset_time' => time() + $windowSeconds
            ];
        }
        
        $rateLimit = &$_SESSION[$key];
        
        // Reset if window expired
        if (time() > $rateLimit['reset_time']) {
            $rateLimit = [
                'count' => 0,
                'reset_time' => time() + $windowSeconds
            ];
        }
        
        $rateLimit['count']++;
        
        return $rateLimit['count'] <= $maxRequests;
    }
}
}