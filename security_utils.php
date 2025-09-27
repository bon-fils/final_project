<?php
/**
 * Enhanced Security Utilities
 * Provides comprehensive security-related functions for the application
 * Note: CSRF functions are handled by session_check.php
 */

/**
 * Comprehensive Input Validation Class
 */
class InputValidator {
    private $errors = [];
    private $data = [];

    public function __construct(array $data = []) {
        $this->data = $data;
    }

    /**
     * Validate required fields
     */
    public function required(array $fields): self {
        foreach ($fields as $field) {
            if (empty(trim($this->data[$field] ?? ''))) {
                $this->errors[$field][] = "The {$field} field is required";
            }
        }
        return $this;
    }

    /**
     * Validate string length
     */
    public function length(string $field, int $min, int $max = null): self {
        $value = trim($this->data[$field] ?? '');

        if (strlen($value) < $min) {
            $this->errors[$field][] = "The {$field} field must be at least {$min} characters";
        }

        if ($max !== null && strlen($value) > $max) {
            $this->errors[$field][] = "The {$field} field must not exceed {$max} characters";
        }

        return $this;
    }

    /**
     * Validate email format
     */
    public function email(string $field): self {
        $value = trim($this->data[$field] ?? '');

        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "Please enter a valid email address";
        }

        return $this;
    }

    /**
     * Validate phone number
     */
    public function phone(string $field): self {
        $value = trim($this->data[$field] ?? '');

        if (!empty($value)) {
            // Remove common formatting characters
            $phone = preg_replace('/[\s\-\(\)\.]/', '', $value);

            if (!preg_match('/^\d{10}$/', $phone)) {
                $this->errors[$field][] = "Please enter a valid 10-digit phone number (e.g., 0781234567)";
            }
        }

        return $this;
    }

    /**
     * Validate numeric value
     */
    public function numeric(string $field, int $min = null, int $max = null): self {
        $value = $this->data[$field] ?? null;

        if (!is_numeric($value)) {
            $this->errors[$field][] = "The {$field} field must be a number";
            return $this;
        }

        if ($min !== null && $value < $min) {
            $this->errors[$field][] = "The {$field} field must be at least {$min}";
        }

        if ($max !== null && $value > $max) {
            $this->errors[$field][] = "The {$field} field must not exceed {$max}";
        }

        return $this;
    }

    /**
     * Validate date format
     */
    public function date(string $field, string $format = 'Y-m-d'): self {
        $value = trim($this->data[$field] ?? '');

        if (!empty($value)) {
            $date = DateTime::createFromFormat($format, $value);
            if (!$date || $date->format($format) !== $value) {
                $this->errors[$field][] = "Please enter a valid date";
            }
        }

        return $this;
    }

    /**
     * Validate file upload
     */
    public function file(string $field, array $allowed_types = [], int $max_size = null): self {
        if (!isset($_FILES[$field])) {
            $this->errors[$field][] = "No file uploaded";
            return $this;
        }

        $file = $_FILES[$field];
        $max_size = $max_size ?: MAX_FILE_SIZE;

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $this->errors[$field][] = "File size exceeds maximum allowed size";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->errors[$field][] = "No file was uploaded";
                    break;
                default:
                    $this->errors[$field][] = "Upload error occurred";
            }
            return $this;
        }

        // Check file size
        if ($file['size'] > $max_size) {
            $this->errors[$field][] = "File size exceeds maximum allowed size of " . ($max_size / 1024 / 1024) . "MB";
        }

        // Check file type
        if (!empty($allowed_types)) {
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $file['tmp_name']);
            finfo_close($file_info);

            if (!in_array($mime_type, $allowed_types)) {
                $this->errors[$field][] = "File type not allowed. Allowed types: " . implode(', ', $allowed_types);
            }
        }

        return $this;
    }

    /**
     * Validate password strength
     */
    public function password(string $field, int $min_length = 8): self {
        $value = $this->data[$field] ?? '';

        if (empty($value)) {
            $this->errors[$field][] = "Password is required";
            return $this;
        }

        if (strlen($value) < $min_length) {
            $this->errors[$field][] = "Password must be at least {$min_length} characters long";
        }

        if (!preg_match('/[A-Z]/', $value)) {
            $this->errors[$field][] = "Password must contain at least one uppercase letter";
        }

        if (!preg_match('/[a-z]/', $value)) {
            $this->errors[$field][] = "Password must contain at least one lowercase letter";
        }

        if (!preg_match('/[0-9]/', $value)) {
            $this->errors[$field][] = "Password must contain at least one number";
        }

        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $this->errors[$field][] = "Password must contain at least one special character";
        }

        return $this;
    }

    /**
     * Validate URL format
     */
    public function url(string $field): self {
        $value = trim($this->data[$field] ?? '');

        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = "Please enter a valid URL";
        }

        return $this;
    }

    /**
     * Custom validation rule
     */
    public function custom(string $field, callable $callback, string $message): self {
        $value = $this->data[$field] ?? null;

        if (!$callback($value)) {
            $this->errors[$field][] = $message;
        }

        return $this;
    }

    /**
     * Check if validation passed
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Check if validation failed
     */
    public function fails(): bool {
        return !$this->passes();
    }

    /**
     * Get validation errors
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Get first error for a field
     */
    public function firstError(string $field): ?string {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Get all errors as a flat array
     */
    public function allErrors(): array {
        $errors = [];
        foreach ($this->errors as $field_errors) {
            $errors = array_merge($errors, $field_errors);
        }
        return $errors;
    }
}

/**
 * Enhanced Data Sanitization Functions
 */
class DataSanitizer {
    /**
     * Sanitize string input
     */
    public static function string($input): string {
        if (is_array($input)) {
            return array_map([self::class, 'string'], $input);
        }
        return trim(stripslashes(htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * Sanitize email input
     */
    public static function email($input): string {
        return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitize URL input
     */
    public static function url($input): string {
        return filter_var(trim($input), FILTER_SANITIZE_URL);
    }

    /**
     * Sanitize numeric input
     */
    public static function number($input): ?int {
        if (is_numeric($input)) {
            return (int) $input;
        }
        return null;
    }

    /**
     * Sanitize float input
     */
    public static function float($input): ?float {
        if (is_numeric($input)) {
            return (float) $input;
        }
        return null;
    }

    /**
     * Sanitize filename
     */
    public static function filename($input): string {
        // Remove any path components
        $filename = basename($input);

        // Replace dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Ensure reasonable length
        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 255 - strlen($ext) - 1) . '.' . $ext;
        }

        return $filename;
    }

    /**
     * Sanitize array input
     */
    public static function array(array $input): array {
        return array_map(function($item) {
            if (is_string($item)) {
                return self::string($item);
            }
            return $item;
        }, $input);
    }

    /**
     * Sanitize JSON input
     */
    public static function json($input): ?array {
        $decoded = json_decode($input, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}


/**
 * Validate email address
 */
function validate_email(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (basic validation)
 */
function validate_phone(string $phone): bool {
    // Remove common formatting characters
    $phone = preg_replace('/[\s\-\(\)\.]/', '', $phone);

    // Check if it's a valid phone number (10-15 digits)
    return preg_match('/^\+?[\d]{10,15}$/', $phone);
}


/**
 * Get remaining rate limit
 */
function get_rate_limit_status(string $key, int $window_seconds, int $max_requests): array {
    $current_time = time();
    $rate_limit_file = sys_get_temp_dir() . "/rate_limit_{$key}.txt";

    if (file_exists($rate_limit_file)) {
        $data = json_decode(file_get_contents($rate_limit_file), true);
        if ($data && $data['window_start'] > ($current_time - $window_seconds)) {
            return [
                'remaining' => max(0, $max_requests - $data['count']),
                'reset_time' => $data['window_start'] + $window_seconds,
                'total' => $max_requests
            ];
        }
    }

    return [
        'remaining' => $max_requests,
        'reset_time' => $current_time + $window_seconds,
        'total' => $max_requests
    ];
}

/**
 * Clean up expired rate limit files
 */
function cleanup_rate_limits(): void {
    $temp_dir = sys_get_temp_dir();
    $files = glob($temp_dir . "/rate_limit_*.txt");

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['window_start'])) {
            unlink($file);
            continue;
        }

        // Remove files older than 1 hour
        if ($data['window_start'] < (time() - 3600)) {
            unlink($file);
        }
    }
}

/**
 * Generate secure random string
 */
function generate_secure_token(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password securely
 */
function hash_password(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

/**
 * Verify password
 */
function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Escape HTML output
 */
function escape_html($data): string {
    if (is_array($data)) {
        return array_map('escape_html', $data);
    }
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escape JavaScript output
 */
function escape_js($data): string {
    if (is_array($data)) {
        return array_map('escape_js', $data);
    }
    return str_replace(['\\', "'", '"', "\r", "\n"], ['\\\\', "\\'", '\\"', '\\r', '\\n'], $data);
}


/**
 * Generate secure filename
 */
function generate_secure_filename(string $original_filename): string {
    $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
    $basename = pathinfo($original_filename, PATHINFO_FILENAME);

    // Sanitize basename
    $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);

    // Generate unique filename
    $unique_id = uniqid('', true);
    return $basename . '_' . $unique_id . '.' . $extension;
}

/**
 * Log security events
 */
function log_security_event(string $event_type, string $details, string $ip_address = null, int $user_id = null): void {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'details' => $details,
        'ip_address' => $ip_address ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'user_id' => $user_id,
        'session_id' => session_id()
    ];

    $log_file = __DIR__ . '/../logs/security.log';
    $log_dir = dirname($log_file);

    // Create log directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    // Append to log file
    file_put_contents($log_file, json_encode($log_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Check if IP is suspicious (basic check)
 */
function is_suspicious_ip(string $ip): bool {
    // List of suspicious IP patterns (you can expand this)
    $suspicious_patterns = [
        '/^192\.168\./', // Local IPs
        '/^10\./',       // Private network
        '/^172\.(1[6-9]|2[0-9]|3[0-1])\./', // Private network
        '/^127\./',      // Localhost
    ];

    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $ip)) {
            return true;
        }
    }

    return false;
}

/**
 * Sanitize SQL input
 */
function sanitize_sql($data): string {
    if (is_array($data)) {
        return array_map('sanitize_sql', $data);
    }

    // Remove potential SQL injection characters
    return str_replace(['\'', '"', ';', '--', '/*', '*/'], '', $data);
}

/**
 * Validate URL
 */
function validate_url(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Generate API key
 */
function generate_api_key(): string {
    return 'ak_' . bin2hex(random_bytes(32));
}

/**
 * Validate API key format
 */
function validate_api_key_format(string $key): bool {
    return preg_match('/^ak_[a-f0-9]{64}$/', $key);
}

/**
 * Encrypt sensitive data
 */
function encrypt_data(string $data, string $key): string {
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function decrypt_data(string $encrypted_data, string $key): string {
    $data = base64_decode($encrypted_data);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

/**
 * Check if request is from allowed origin
 */
function is_allowed_origin(string $allowed_origins): bool {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (empty($origin)) {
        return true; // Allow requests without origin header
    }

    $allowed_origins_array = explode(',', $allowed_origins);
    return in_array($origin, $allowed_origins_array);
}

/**
 * Generate secure session ID
 */
function generate_secure_session_id(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Validate session integrity
 */
function validate_session_integrity(): bool {
    if (!isset($_SESSION['created']) || !isset($_SESSION['fingerprint'])) {
        return false;
    }

    $current_fingerprint = generate_session_fingerprint();
    return hash_equals($_SESSION['fingerprint'], $current_fingerprint);
}

/**
 * Generate session fingerprint
 */
function generate_session_fingerprint(): string {
    return hash('sha256', $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
}

/**
 * Initialize secure session
 */
function initialize_secure_session(): void {
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
        $_SESSION['fingerprint'] = generate_session_fingerprint();
    }
}
?>