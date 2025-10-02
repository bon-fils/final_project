<?php
/**
 * Logging Class
 * Rwanda Polytechnic Attendance System
 * Handles application logging
 */

class Logger {
    private $logFile;
    private $logLevel;

    const DEBUG = 0;
    const INFO = 1;
    const WARNING = 2;
    const ERROR = 3;

    public function __construct($logFile = null, $logLevel = self::INFO) {
        $this->logFile = $logFile ?? __DIR__ . '/../logs/app.log';
        $this->logLevel = $logLevel;

        // Ensure log directory exists
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Log debug message
     */
    public function debug($message) {
        $this->log($message, self::DEBUG);
    }

    /**
     * Log info message
     */
    public function info($message) {
        $this->log($message, self::INFO);
    }

    /**
     * Log warning message
     */
    public function warning($message) {
        $this->log($message, self::WARNING);
    }

    /**
     * Log error message
     */
    public function error($message) {
        $this->log($message, self::ERROR);
    }

    /**
     * Main logging method
     */
    private function log($message, $level) {
        if ($level < $this->logLevel) {
            return;
        }

        $levelNames = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [{$levelNames[$level]}] $message" . PHP_EOL;

        // Write to file
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);

        // Also log to error_log for critical errors
        if ($level >= self::ERROR) {
            error_log($message);
        }
    }

    /**
     * Set log level
     */
    public function setLogLevel($level) {
        $this->logLevel = $level;
    }

    /**
     * Set log file path
     */
    public function setLogFile($logFile) {
        $this->logFile = $logFile;
    }
}
?>