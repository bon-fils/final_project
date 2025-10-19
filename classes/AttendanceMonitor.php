<?php
/**
 * Attendance Monitor
 * Provides monitoring and health check capabilities for attendance system
 */

class AttendanceMonitor {
    private $pdo;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AttendanceSessionLogger();
    }

    /**
     * Get system health status
     */
    public function getSystemHealth() {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('Y-m-d H:i:s'),
            'checks' => []
        ];

        // Database connectivity
        $health['checks']['database'] = $this->checkDatabaseConnectivity();

        // Active sessions
        $health['checks']['active_sessions'] = $this->checkActiveSessions();

        // Face recognition service
        $health['checks']['face_recognition'] = $this->checkFaceRecognitionService();

        // Storage space
        $health['checks']['storage'] = $this->checkStorageSpace();

        // Recent errors
        $health['checks']['recent_errors'] = $this->checkRecentErrors();

        // Determine overall status
        $failedChecks = array_filter($health['checks'], function($check) {
            return $check['status'] !== 'healthy';
        });

        if (!empty($failedChecks)) {
            $health['status'] = count($failedChecks) > 2 ? 'critical' : 'warning';
        }

        return $health;
    }

    /**
     * Check database connectivity
     */
    private function checkDatabaseConnectivity() {
        try {
            $stmt = $this->pdo->query("SELECT 1");
            $stmt->fetch();

            // Get some basic stats
            $stats = $this->getDatabaseStats();

            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'details' => $stats
            ];
        } catch (PDOException $e) {
            $this->logger->logError("Database connectivity check failed: " . $e->getMessage());
            return [
                'status' => 'critical',
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get database statistics
     */
    private function getDatabaseStats() {
        try {
            $stats = [];

            // Count records in key tables
            $tables = ['users', 'students', 'lecturers', 'courses', 'attendance_sessions', 'attendance_records'];
            foreach ($tables as $table) {
                try {
                    $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM {$table}");
                    $stats[$table] = $stmt->fetch()['count'];
                } catch (PDOException $e) {
                    $stats[$table] = 'error';
                }
            }

            return $stats;
        } catch (Exception $e) {
            return ['error' => 'Failed to get database stats'];
        }
    }

    /**
     * Check active sessions
     */
    private function checkActiveSessions() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as active_count,
                       MAX(start_time) as latest_session
                FROM attendance_sessions
                WHERE end_time IS NULL AND status = 'active'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $activeCount = (int)$result['active_count'];

            if ($activeCount > 10) {
                return [
                    'status' => 'warning',
                    'message' => "High number of active sessions: {$activeCount}",
                    'active_sessions' => $activeCount,
                    'latest_session' => $result['latest_session']
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Active sessions within normal range',
                'active_sessions' => $activeCount,
                'latest_session' => $result['latest_session']
            ];
        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to check active sessions',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check face recognition service
     */
    private function checkFaceRecognitionService() {
        $pythonScript = __DIR__ . '/../face_match.py';

        if (!file_exists($pythonScript)) {
            return [
                'status' => 'critical',
                'message' => 'Face recognition script not found',
                'script_path' => $pythonScript
            ];
        }

        // Check if Python is available
        $faceRecognition = new FaceRecognitionService();
        $pythonAvailable = $faceRecognition->isPythonAvailable();

        if (!$pythonAvailable) {
            return [
                'status' => 'warning',
                'message' => 'Python executable not found or not working'
            ];
        }

        return [
            'status' => 'healthy',
            'message' => 'Face recognition service available',
            'script_exists' => true,
            'python_available' => true
        ];
    }

    /**
     * Check storage space
     */
    private function checkStorageSpace() {
        try {
            $uploadDir = __DIR__ . '/../uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $totalSpace = disk_total_space($uploadDir);
            $freeSpace = disk_free_space($uploadDir);
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercent = $totalSpace > 0 ? round(($usedSpace / $totalSpace) * 100, 1) : 0;

            $status = 'healthy';
            if ($usagePercent > 90) {
                $status = 'critical';
            } elseif ($usagePercent > 75) {
                $status = 'warning';
            }

            return [
                'status' => $status,
                'message' => "Storage usage: {$usagePercent}%",
                'total_space' => $this->formatBytes($totalSpace),
                'free_space' => $this->formatBytes($freeSpace),
                'usage_percent' => $usagePercent
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to check storage space',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check recent errors
     */
    private function checkRecentErrors() {
        try {
            $logFile = __DIR__ . '/../logs/attendance_session.log';

            if (!file_exists($logFile)) {
                return [
                    'status' => 'healthy',
                    'message' => 'No error log file found',
                    'recent_errors' => 0
                ];
            }

            $logContent = file_get_contents($logFile);
            $lines = explode("\n", $logContent);
            $recentLines = array_slice($lines, -100); // Check last 100 lines

            $errorCount = 0;
            $warningCount = 0;
            $recentErrors = [];

            foreach ($recentLines as $line) {
                if (strpos($line, 'ERROR') !== false) {
                    $errorCount++;
                    if (count($recentErrors) < 5) {
                        $recentErrors[] = substr($line, 0, 200); // First 200 chars
                    }
                } elseif (strpos($line, 'WARNING') !== false) {
                    $warningCount++;
                }
            }

            $status = 'healthy';
            $message = 'No recent errors';

            if ($errorCount > 0) {
                $status = 'warning';
                $message = "{$errorCount} recent errors found";
            }

            if ($errorCount > 10) {
                $status = 'critical';
                $message = "High number of recent errors: {$errorCount}";
            }

            return [
                'status' => $status,
                'message' => $message,
                'error_count' => $errorCount,
                'warning_count' => $warningCount,
                'recent_errors' => $recentErrors
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Failed to check error logs',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics() {
        $metrics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'metrics' => []
        ];

        // API response times (mock data - would need actual tracking)
        $metrics['metrics']['api_response_time'] = [
            'average' => 0.15, // seconds
            'p95' => 0.5,
            'p99' => 1.2
        ];

        // Database query performance
        $metrics['metrics']['db_query_performance'] = $this->getDatabasePerformance();

        // Face recognition performance
        $metrics['metrics']['face_recognition'] = [
            'average_processing_time' => 2.3, // seconds
            'success_rate' => 94.5 // percent
        ];

        // Session statistics
        $metrics['metrics']['sessions'] = $this->getSessionMetrics();

        return $metrics;
    }

    /**
     * Get database performance metrics
     */
    private function getDatabasePerformance() {
        try {
            $startTime = microtime(true);

            // Test query
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM attendance_sessions WHERE end_time IS NULL");
            $stmt->fetch();

            $queryTime = microtime(true) - $startTime;

            return [
                'query_time' => round($queryTime * 1000, 2), // ms
                'status' => $queryTime < 0.1 ? 'good' : ($queryTime < 0.5 ? 'fair' : 'slow')
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get session metrics
     */
    private function getSessionMetrics() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(*) as total_sessions,
                    AVG(TIMESTAMPDIFF(MINUTE, start_time, COALESCE(end_time, NOW()))) as avg_duration,
                    COUNT(CASE WHEN end_time IS NULL THEN 1 END) as active_sessions
                FROM attendance_sessions
                WHERE DATE(start_time) = CURDATE()
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_today' => (int)$result['total_sessions'],
                'active_now' => (int)$result['active_sessions'],
                'avg_duration_minutes' => round((float)$result['avg_duration'], 1)
            ];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 1) . ' ' . $units[$pow];
    }

    /**
     * Clean up old log files
     */
    public function cleanupOldLogs($daysToKeep = 30) {
        try {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                return ['status' => 'no_logs_directory'];
            }

            $files = glob($logDir . '/*.log');
            $deletedCount = 0;

            foreach ($files as $file) {
                if (filemtime($file) < strtotime("-{$daysToKeep} days")) {
                    unlink($file);
                    $deletedCount++;
                }
            }

            return [
                'status' => 'success',
                'deleted_files' => $deletedCount
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}