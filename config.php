<?php
/**
 * Enhanced Configuration Management
 * Supports environment variables and improved security
 */

// Prevent direct access
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

// Load environment variables if .env file exists
$env_file = APP_ROOT . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && (strpos($line, '#') !== 0)) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');
            $_ENV[$key] = $value;
        }
    }
}

// Database configuration with environment variable support
$host     = $_ENV['DB_HOST']     ?? 'localhost';
$db_name  = $_ENV['DB_NAME']     ?? 'rp_attendance_system';
$username = $_ENV['DB_USER']     ?? 'root';
$password = $_ENV['DB_PASS']     ?? '';

// Application configuration
define('APP_NAME', $_ENV['APP_NAME'] ?? 'RP Attendance System');
define('APP_VERSION', $_ENV['APP_VERSION'] ?? '1.0.0');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'development');

// Security configuration
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 1800)); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5));
define('LOGIN_LOCKOUT_TIME', (int)($_ENV['LOGIN_LOCKOUT_TIME'] ?? 900)); // 15 minutes

// File upload configuration
define('MAX_FILE_SIZE', (int)($_ENV['MAX_FILE_SIZE'] ?? 5242880)); // 5MB
define('UPLOAD_PATH', APP_ROOT . '/uploads/');
define('ALLOWED_FILE_TYPES', $_ENV['ALLOWED_FILE_TYPES'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx');

// Cache configuration
define('CACHE_ENABLED', filter_var($_ENV['CACHE_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN));
define('CACHE_TTL', (int)($_ENV['CACHE_TTL'] ?? 1800)); // 30 minutes

// Logging configuration
define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', APP_ROOT . '/logs/app.log');

// Rate limiting configuration
define('RATE_LIMIT_WINDOW', (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 300)); // 5 minutes
define('RATE_LIMIT_MAX_REQUESTS', (int)($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 100));

// Email configuration (if needed)
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? '');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');

// ESP32 Configuration
define('ESP32_IP', $_ENV['ESP32_IP'] ?? '192.168.137.173');
define('ESP32_PORT', (int)($_ENV['ESP32_PORT'] ?? 80));
define('ESP32_TIMEOUT', (int)($_ENV['ESP32_TIMEOUT'] ?? 30)); // seconds

// Validate required configuration
if (empty($db_name) || empty($username)) {
    error_log('Database configuration is incomplete');
    http_response_code(500);
    die('Database configuration error');
}

// Database connection with enhanced error handling
try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db_name;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 30 // 30 second timeout
        ]
    );

    // Execute charset setting manually for compatibility
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

    // Set connection attributes for better performance and security
    $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
    $pdo->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);

} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage() . " (Host: $host, DB: $db_name)");
    http_response_code(500);

    // In production, show generic error message
    if (APP_ENV === 'production') {
        die('Database connection failed. Please try again later.');
    } else {
        // Check if this is a CLI request (command line execution)
        if (php_sapi_name() === 'cli') {
            die("Database connection failed: " . $e->getMessage() . " (Host: $host, DB: $db_name)");
        } else {
            die("Database connection failed: " . $e->getMessage());
        }
    }
}

// Create logs directory if it doesn't exist
if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}

// Utility functions
function is_production(): bool {
    return APP_ENV === 'production';
}

function is_development(): bool {
    return APP_ENV === 'development';
}

function log_message(string $level, string $message, array $context = []): void {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => strtoupper($level),
        'message' => $message,
        'context' => $context,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'session_id' => session_id() ?: 'none'
    ];

    $log_levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
    $current_level = $log_levels[LOG_LEVEL] ?? 1;

    if ($log_levels[strtoupper($level)] >= $current_level) {
        file_put_contents(LOG_FILE, json_encode($log_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

function sanitize_filename(string $filename): string {
    // Remove any path components and keep only filename
    $filename = basename($filename);

    // Replace special characters with underscores
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    // Ensure filename is not too long
    if (strlen($filename) > 255) {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $filename = substr($name, 0, 255 - strlen($ext) - 1) . '.' . $ext;
    }

    return $filename;
}

// Initialize security features
require_once __DIR__ . '/security_utils.php';
require_once __DIR__ . '/redis_cache_manager.php';

// Enforce HTTPS in production
SecurityUtils::enforceHTTPS();

// Set security headers
SecurityUtils::setSecurityHeaders();

// Initialize Redis cache
$redisCache = new RedisCacheManager();

// ESP32 communication function
function esp32Request(string $endpoint, string $method = 'GET', array $data = []): ?array {
    $url = 'http://' . ESP32_IP . ':' . ESP32_PORT . $endpoint;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, ESP32_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: RP-Attendance-System/1.0'
    ]);

    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        log_message('error', 'ESP32 communication error', [
            'endpoint' => $endpoint,
            'error' => $error,
            'url' => $url
        ]);
        return null;
    }

    if ($httpCode !== 200) {
        log_message('warning', 'ESP32 returned non-200 status', [
            'endpoint' => $endpoint,
            'status' => $httpCode,
            'response' => $response
        ]);
        return null;
    }

    $decodedResponse = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        log_message('warning', 'Invalid JSON response from ESP32', [
            'endpoint' => $endpoint,
            'response' => $response
        ]);
        return null;
    }

    log_message('info', 'ESP32 request successful', [
        'endpoint' => $endpoint,
        'method' => $method,
        'response_keys' => array_keys($decodedResponse)
    ]);

    return $decodedResponse;
}

// Initialize application
log_message('info', 'Application initialized', [
    'version' => APP_VERSION,
    'environment' => APP_ENV,
    'database' => $db_name,
    'https_enabled' => SecurityUtils::isHTTPS(),
    'cache_type' => $redisCache->getStats()['type'] ?? 'unknown',
    'esp32_ip' => ESP32_IP,
    'esp32_port' => ESP32_PORT
]);
?>
