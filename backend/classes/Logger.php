<?php
/**
 * Advanced Logging System
 * Provides comprehensive logging with multiple levels and storage options
 * Version: 2.0 - Enhanced with security and performance features
 */

class Logger {
    private $logFile;
    private $logLevel;
    private $maxFileSize;
    private $backupCount;
    private $databaseLogging;
    private $pdo;

    const EMERGENCY = 0;
    const ALERT = 1;
    const CRITICAL = 2;
    const ERROR = 3;
    const WARNING = 4;
    const NOTICE = 5;
    const INFO = 6;
    const DEBUG = 7;

    private $levelNames = [
        self::EMERGENCY => 'EMERGENCY',
        self::ALERT => 'ALERT',
        self::CRITICAL => 'CRITICAL',
        self::ERROR => 'ERROR',
        self::WARNING => 'WARNING',
        self::NOTICE => 'NOTICE',
        self::INFO => 'INFO',
        self::DEBUG => 'DEBUG'
    ];

    public function __construct($config = []) {
        $this->logFile = $config['file'] ?? 'logs/system.log';
        $this->logLevel = $config['level'] ?? self::INFO;
        $this->maxFileSize = $config['max_size'] ?? 10 * 1024 * 1024; // 10MB
        $this->backupCount = $config['backup_count'] ?? 5;
        $this->databaseLogging = $config['database'] ?? false;

        if ($this->databaseLogging && isset($config['pdo'])) {
            $this->pdo = $config['pdo'];
            $this->initializeDatabaseTable();
        }

        $this->ensureLogDirectory();
    }

    /**
     * Initialize database logging table
     */
    private function initializeDatabaseTable() {
        if (!$this->pdo) return;

        try {
            $sql = "CREATE TABLE IF NOT EXISTS system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME NOT NULL,
                level VARCHAR(20) NOT NULL,
                category VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                user_id INT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                session_id VARCHAR(255) NULL,
                request_uri TEXT NULL,
                context TEXT NULL,
                INDEX idx_timestamp (timestamp),
                INDEX idx_level (level),
                INDEX idx_category (category),
                INDEX idx_user_id (user_id)
            )";

            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            // Log to file if database initialization fails
            $this->logToFile(self::ERROR, 'Logger', 'Failed to initialize database table: ' . $e->getMessage());
        }
    }

    /**
     * Ensure log directory exists
     */
    private function ensureLogDirectory() {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Emergency level logging
     */
    public function emergency($category, $message, $context = []) {
        $this->log(self::EMERGENCY, $category, $message, $context);
    }

    /**
     * Alert level logging
     */
    public function alert($category, $message, $context = []) {
        $this->log(self::ALERT, $category, $message, $context);
    }

    /**
     * Critical level logging
     */
    public function critical($category, $message, $context = []) {
        $this->log(self::CRITICAL, $category, $message, $context);
    }

    /**
     * Error level logging
     */
    public function error($category, $message, $context = []) {
        $this->log(self::ERROR, $category, $message, $context);
    }

    /**
     * Warning level logging
     */
    public function warning($category, $message, $context = []) {
        $this->log(self::WARNING, $category, $message, $context);
    }

    /**
     * Notice level logging
     */
    public function notice($category, $message, $context = []) {
        $this->log(self::NOTICE, $category, $message, $context);
    }

    /**
     * Info level logging
     */
    public function info($category, $message, $context = []) {
        $this->log(self::INFO, $category, $message, $context);
    }

    /**
     * Debug level logging
     */
    public function debug($category, $message, $context = []) {
        $this->log(self::DEBUG, $category, $message, $context);
    }

    /**
     * Main logging method
     */
    public function log($level, $category, $message, $context = []) {
        if ($level > $this->logLevel) {
            return;
        }

        $logEntry = $this->formatLogEntry($level, $category, $message, $context);

        // Log to file
        $this->logToFile($level, $category, $message, $context);

        // Log to database if enabled
        if ($this->databaseLogging) {
            $this->logToDatabase($level, $category, $message, $context);
        }

        // Trigger system alerts for critical levels
        if ($level <= self::ERROR) {
            $this->triggerAlert($level, $category, $message, $context);
        }
    }

    /**
     * Format log entry
     */
    private function formatLogEntry($level, $category, $message, $context) {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = $this->levelNames[$level];
        $sessionId = session_id() ?: 'none';
        $userId = $_SESSION['user_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';

        $contextString = '';
        if (!empty($context)) {
            $contextString = ' | Context: ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return sprintf(
            "[%s] %s.%s: %s | User: %s | IP: %s | Session: %s | URI: %s | Agent: %s%s",
            $timestamp,
            $levelName,
            $category,
            $message,
            $userId ?: 'anonymous',
            $ipAddress,
            $sessionId,
            $requestUri,
            substr($userAgent, 0, 100),
            $contextString
        );
    }

    /**
     * Log to file
     */
    private function logToFile($level, $category, $message, $context) {
        $logEntry = $this->formatLogEntry($level, $category, $message, $context) . PHP_EOL;

        // Check if file needs rotation
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxFileSize) {
            $this->rotateLogFile();
        }

        // Write to log file
        if (file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            // Fallback to error log if main log fails
            error_log("Failed to write to log file: {$this->logFile}");
        }
    }

    /**
     * Log to database
     */
    private function logToDatabase($level, $category, $message, $context) {
        if (!$this->pdo) return;

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO system_logs (
                    timestamp, level, category, message, user_id, ip_address,
                    user_agent, session_id, request_uri, context
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                date('Y-m-d H:i:s'),
                $this->levelNames[$level],
                $category,
                $message,
                $_SESSION['user_id'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                session_id() ?: null,
                $_SERVER['REQUEST_URI'] ?? null,
                !empty($context) ? json_encode($context) : null
            ]);
        } catch (PDOException $e) {
            // Fallback to file logging if database logging fails
            $this->logToFile(self::ERROR, 'Logger', 'Database logging failed: ' . $e->getMessage());
        }
    }

    /**
     * Rotate log files
     */
    private function rotateLogFile() {
        $baseName = pathinfo($this->logFile, PATHINFO_FILENAME);
        $extension = pathinfo($this->logFile, PATHINFO_EXTENSION);
        $directory = dirname($this->logFile);

        // Remove old backup files
        for ($i = $this->backupCount; $i >= 1; $i--) {
            $oldFile = "{$directory}/{$baseName}.{$i}.{$extension}";
            $newFile = "{$directory}/{$baseName}." . ($i + 1) . ".{$extension}";

            if (file_exists($oldFile)) {
                if ($i >= $this->backupCount) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Rotate current log file
        if (file_exists($this->logFile)) {
            rename($this->logFile, "{$directory}/{$baseName}.1.{$extension}");
        }
    }

    /**
     * Trigger system alerts for critical errors
     */
    private function triggerAlert($level, $category, $message, $context) {
        // Email notification for critical errors
        if ($level <= self::CRITICAL) {
            $this->sendEmailAlert($level, $category, $message, $context);
        }

        // SMS notification for emergency errors
        if ($level <= self::EMERGENCY) {
            $this->sendSMSAlert($level, $category, $message);
        }
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert($level, $category, $message, $context) {
        // Implementation for email alerts
        // This would integrate with your email system
    }

    /**
     * Send SMS alert
     */
    private function sendSMSAlert($level, $category, $message) {
        // Implementation for SMS alerts
        // This would integrate with your SMS system
    }

    /**
     * Get recent logs
     */
    public function getRecentLogs($limit = 100, $level = null, $category = null) {
        if ($this->databaseLogging) {
            return $this->getLogsFromDatabase($limit, $level, $category);
        } else {
            return $this->getLogsFromFile($limit, $level, $category);
        }
    }

    /**
     * Get logs from database
     */
    private function getLogsFromDatabase($limit, $level, $category) {
        if (!$this->pdo) return [];

        try {
            $sql = "SELECT * FROM system_logs WHERE 1=1";
            $params = [];

            if ($level) {
                $sql .= " AND level = ?";
                $params[] = $this->levelNames[$level];
            }

            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }

            $sql .= " ORDER BY timestamp DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get logs from file
     */
    private function getLogsFromFile($limit, $level, $category) {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $logs = [];
        $file = new SplFileObject($this->logFile);
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $limit);
        $file->seek($startLine);

        while (!$file->eof()) {
            $line = trim($file->fgets());
            if (!empty($line)) {
                $logs[] = $line;
            }
        }

        return array_reverse($logs);
    }

    /**
     * Clean old logs
     */
    public function cleanOldLogs($days = 30) {
        if ($this->databaseLogging) {
            $this->cleanDatabaseLogs($days);
        }
        $this->cleanFileLogs($days);
    }

    /**
     * Clean database logs
     */
    private function cleanDatabaseLogs($days) {
        if (!$this->pdo) return;

        try {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $stmt = $this->pdo->prepare("DELETE FROM system_logs WHERE timestamp < ?");
            $stmt->execute([$cutoffDate]);
        } catch (PDOException $e) {
            $this->logToFile(self::ERROR, 'Logger', 'Failed to clean database logs: ' . $e->getMessage());
        }
    }

    /**
     * Clean file logs
     */
    private function cleanFileLogs($days) {
        $cutoffTime = strtotime("-{$days} days");

        if (file_exists($this->logFile)) {
            $tempFile = $this->logFile . '.tmp';
            $input = fopen($this->logFile, 'r');
            $output = fopen($tempFile, 'w');

            while (($line = fgets($input)) !== false) {
                // Extract timestamp from log line
                if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                    $logTime = strtotime($matches[1]);
                    if ($logTime >= $cutoffTime) {
                        fwrite($output, $line);
                    }
                }
            }

            fclose($input);
            fclose($output);

            rename($tempFile, $this->logFile);
        }
    }
}