<?php
class AttendanceSessionLogger {
    private $log_file;
    private $max_file_size = 10485760; // 10MB

    public function __construct($log_file = null) {
        $this->log_file = $log_file ?: __DIR__ . '/../logs/attendance_sessions.log';
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory() {
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }

    private function rotateLogIfNeeded() {
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_file_size) {
            $backup_file = $this->log_file . '.' . date('Y-m-d-H-i-s') . '.bak';
            rename($this->log_file, $backup_file);

            // Keep only last 5 backup files
            $this->cleanupOldBackups();
        }
    }

    private function cleanupOldBackups() {
        $log_dir = dirname($this->log_file);
        $pattern = $log_dir . '/attendance_sessions.log.*.bak';
        $backups = glob($pattern);

        if (count($backups) > 5) {
            // Sort by modification time, keep newest 5
            usort($backups, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });

            $to_delete = array_slice($backups, 5);
            foreach ($to_delete as $file) {
                unlink($file);
            }
        }
    }

    public function log($level, $message, $context = []) {
        $this->rotateLogIfNeeded();

        $timestamp = date('Y-m-d H:i:s');
        $user_id = $_SESSION['user_id'] ?? 'unknown';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $log_entry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'user_id' => $user_id,
            'ip' => $ip,
            'message' => $message,
            'context' => $context
        ];

        $log_line = json_encode($log_entry) . PHP_EOL;

        if (file_put_contents($this->log_file, $log_line, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write to attendance session log: " . $this->log_file);
        }
    }

    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }

    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }

    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }

    public function debug($message, $context = []) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $this->log('debug', $message, $context);
        }
    }

    public function logSessionAction($action, $session_id, $user_id, $details = []) {
        $this->info("Session action: {$action}", array_merge([
            'session_id' => $session_id,
            'user_id' => $user_id,
            'action' => $action
        ], $details));
    }

    public function logPerformance($operation, $duration, $details = []) {
        $this->info("Performance: {$operation}", array_merge([
            'operation' => $operation,
            'duration_ms' => $duration
        ], $details));
    }

    public function getRecentLogs($limit = 100, $level = null) {
        if (!file_exists($this->log_file)) {
            return [];
        }

        $logs = [];
        $handle = fopen($this->log_file, 'r');

        if ($handle) {
            $lines_read = 0;
            while (($line = fgets($handle)) !== false && $lines_read < $limit) {
                $log_entry = json_decode($line, true);
                if ($log_entry) {
                    if (!$level || strtoupper($log_entry['level']) === strtoupper($level)) {
                        $logs[] = $log_entry;
                        $lines_read++;
                    }
                }
            }
            fclose($handle);
        }

        return array_reverse($logs); // Most recent first
    }

    public function searchLogs($query, $limit = 50) {
        if (!file_exists($this->log_file)) {
            return [];
        }

        $results = [];
        $handle = fopen($this->log_file, 'r');

        if ($handle) {
            while (($line = fgets($handle)) !== false && count($results) < $limit) {
                if (stripos($line, $query) !== false) {
                    $log_entry = json_decode($line, true);
                    if ($log_entry) {
                        $results[] = $log_entry;
                    }
                }
            }
            fclose($handle);
        }

        return array_reverse($results);
    }
}
?>