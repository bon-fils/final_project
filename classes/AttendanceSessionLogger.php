<?php
/**
 * Attendance Session Logger
 * Handles logging for attendance session operations
 */

class AttendanceSessionLogger {
    private $logFile;
    private $logLevel;

    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    public function __construct($logFile = null) {
        $this->logFile = $logFile ?: __DIR__ . '/../logs/attendance_session.log';
        $this->logLevel = getenv('LOG_LEVEL') ?: self::LEVEL_INFO;

        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Log debug message
     */
    public function logDebug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log info message
     */
    public function logInfo($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     */
    public function logWarning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     */
    public function logError($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log message with level
     */
    private function log($level, $message, $context = []) {
        // Check if this level should be logged
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $userId = $_SESSION['user_id'] ?? 'unknown';
        $userRole = $_SESSION['role'] ?? 'unknown';

        $logEntry = sprintf(
            "[%s] %s [%s:%s] %s",
            $timestamp,
            $level,
            $userRole,
            $userId,
            $message
        );

        if (!empty($context)) {
            $logEntry .= " | " . json_encode($context);
        }

        $logEntry .= "\n";

        // Write to file
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Check if message should be logged based on level
     */
    private function shouldLog($level) {
        $levels = [
            self::LEVEL_DEBUG => 1,
            self::LEVEL_INFO => 2,
            self::LEVEL_WARNING => 3,
            self::LEVEL_ERROR => 4
        ];

        $currentLevelValue = $levels[$this->logLevel] ?? 2;
        $messageLevelValue = $levels[$level] ?? 2;

        return $messageLevelValue >= $currentLevelValue;
    }

    /**
     * Log API request
     */
    public function logApiRequest($action, $params = []) {
        $this->logInfo("API Request: {$action}", [
            'action' => $action,
            'params' => $this->sanitizeParams($params),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }

    /**
     * Log API response
     */
    public function logApiResponse($action, $status, $message = '') {
        $level = $status === 'success' ? self::LEVEL_INFO : self::LEVEL_WARNING;
        $this->log($level, "API Response: {$action} - {$status}", [
            'action' => $action,
            'status' => $status,
            'message' => $message
        ]);
    }

    /**
     * Log session operation
     */
    public function logSessionOperation($operation, $sessionId, $details = []) {
        $this->logInfo("Session Operation: {$operation}", array_merge([
            'operation' => $operation,
            'session_id' => $sessionId
        ], $details));
    }

    /**
     * Log attendance operation
     */
    public function logAttendanceOperation($operation, $sessionId, $studentId, $details = []) {
        $this->logInfo("Attendance Operation: {$operation}", array_merge([
            'operation' => $operation,
            'session_id' => $sessionId,
            'student_id' => $studentId
        ], $details));
    }

    /**
     * Sanitize parameters for logging (remove sensitive data)
     */
    private function sanitizeParams($params) {
        $sensitiveKeys = ['password', 'csrf_token', 'image_data'];

        $sanitized = [];
        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_string($value) && strlen($value) > 100) {
                $sanitized[$key] = substr($value, 0, 100) . '...';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}