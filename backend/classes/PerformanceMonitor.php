<?php
/**
 * Performance Monitoring System
 * Tracks system performance, database queries, and resource usage
 * Version: 2.0 - Enhanced with detailed metrics and alerting
 */

class PerformanceMonitor {
    private $db;
    private $logger;
    private $metrics = [];
    private $startTime;
    private $memoryStart;
    private $queryCount = 0;
    private $slowQueries = [];
    private $alertThresholds = [];

    public function __construct($pdo, $logger = null) {
        $this->db = $pdo;
        $this->logger = $logger;
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage();
        $this->initializeMetrics();
        $this->setDefaultThresholds();
    }

    /**
     * Initialize performance metrics
     */
    private function initializeMetrics() {
        $this->metrics = [
            'system' => [
                'start_time' => $this->startTime,
                'memory_start' => $this->memoryStart,
                'memory_peak' => 0,
                'execution_time' => 0,
                'cpu_usage' => 0
            ],
            'database' => [
                'query_count' => 0,
                'total_query_time' => 0,
                'slow_queries' => 0,
                'failed_queries' => 0
            ],
            'cache' => [
                'hits' => 0,
                'misses' => 0,
                'hit_ratio' => 0
            ],
            'requests' => [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'average_response_time' => 0
            ]
        ];
    }

    /**
     * Set default alert thresholds
     */
    private function setDefaultThresholds() {
        $this->alertThresholds = [
            'memory_usage' => 50 * 1024 * 1024, // 50MB
            'execution_time' => 5.0, // 5 seconds
            'query_time' => 1.0, // 1 second
            'error_rate' => 0.1, // 10%
            'slow_queries' => 5
        ];
    }

    /**
     * Record database query
     */
    public function recordQuery($sql, $executionTime, $success = true) {
        $this->queryCount++;
        $this->metrics['database']['query_count']++;
        $this->metrics['database']['total_query_time'] += $executionTime;

        if (!$success) {
            $this->metrics['database']['failed_queries']++;
        }

        // Track slow queries
        if ($executionTime > $this->alertThresholds['query_time']) {
            $this->slowQueries[] = [
                'sql' => substr($sql, 0, 200), // Truncate for logging
                'execution_time' => $executionTime,
                'timestamp' => microtime(true)
            ];
            $this->metrics['database']['slow_queries']++;
        }

        // Check for alerts
        $this->checkAlerts();
    }

    /**
     * Record cache operation
     */
    public function recordCacheOperation($hit = true) {
        if ($hit) {
            $this->metrics['cache']['hits']++;
        } else {
            $this->metrics['cache']['misses']++;
        }

        $total = $this->metrics['cache']['hits'] + $this->metrics['cache']['misses'];
        if ($total > 0) {
            $this->metrics['cache']['hit_ratio'] = ($this->metrics['cache']['hits'] / $total) * 100;
        }
    }

    /**
     * Record request
     */
    public function recordRequest($success = true, $responseTime = 0) {
        $this->metrics['requests']['total']++;

        if ($success) {
            $this->metrics['requests']['successful']++;
        } else {
            $this->metrics['requests']['failed']++;
        }

        if ($responseTime > 0) {
            $this->metrics['requests']['average_response_time'] =
                ($this->metrics['requests']['average_response_time'] * ($this->metrics['requests']['total'] - 1) + $responseTime) /
                $this->metrics['requests']['total'];
        }
    }

    /**
     * Update system metrics
     */
    public function updateSystemMetrics() {
        $this->metrics['system']['memory_current'] = memory_get_usage();
        $this->metrics['system']['memory_peak'] = memory_get_peak_usage();
        $this->metrics['system']['execution_time'] = microtime(true) - $this->startTime;

        // Calculate CPU usage (simplified)
        $cpuUsage = $this->getCPUUsage();
        $this->metrics['system']['cpu_usage'] = $cpuUsage;
    }

    /**
     * Get CPU usage (simplified implementation)
     */
    private function getCPUUsage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0] * 100; // Convert to percentage
        }

        // Fallback for Windows or systems without sys_getloadavg
        $cpu = 0;
        if (is_readable('/proc/stat')) {
            $stats = file_get_contents('/proc/stat');
            if (preg_match('/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stats, $matches)) {
                $total = $matches[1] + $matches[2] + $matches[3] + $matches[4];
                $idle = $matches[4];
                $cpu = (($total - $idle) / $total) * 100;
            }
        }

        return $cpu;
    }

    /**
     * Check for performance alerts
     */
    private function checkAlerts() {
        $alerts = [];

        // Memory usage alert
        $memoryUsage = memory_get_usage();
        if ($memoryUsage > $this->alertThresholds['memory_usage']) {
            $alerts[] = [
                'type' => 'memory',
                'level' => 'warning',
                'message' => "High memory usage: " . round($memoryUsage / 1024 / 1024, 2) . "MB",
                'value' => $memoryUsage
            ];
        }

        // Execution time alert
        $executionTime = microtime(true) - $this->startTime;
        if ($executionTime > $this->alertThresholds['execution_time']) {
            $alerts[] = [
                'type' => 'execution_time',
                'level' => 'warning',
                'message' => "High execution time: " . round($executionTime, 2) . "s",
                'value' => $executionTime
            ];
        }

        // Error rate alert
        $totalRequests = $this->metrics['requests']['total'];
        if ($totalRequests > 0) {
            $errorRate = $this->metrics['requests']['failed'] / $totalRequests;
            if ($errorRate > $this->alertThresholds['error_rate']) {
                $alerts[] = [
                    'type' => 'error_rate',
                    'level' => 'critical',
                    'message' => "High error rate: " . round($errorRate * 100, 2) . "%",
                    'value' => $errorRate
                ];
            }
        }

        // Slow queries alert
        if ($this->metrics['database']['slow_queries'] > $this->alertThresholds['slow_queries']) {
            $alerts[] = [
                'type' => 'slow_queries',
                'level' => 'warning',
                'message' => "Too many slow queries: " . $this->metrics['database']['slow_queries'],
                'value' => $this->metrics['database']['slow_queries']
            ];
        }

        // Log alerts
        foreach ($alerts as $alert) {
            if ($this->logger) {
                $this->logger->warning('PerformanceMonitor', $alert['message'], [
                    'type' => $alert['type'],
                    'level' => $alert['level'],
                    'value' => $alert['value']
                ]);
            }
        }
    }

    /**
     * Get performance metrics
     */
    public function getMetrics() {
        $this->updateSystemMetrics();

        $metrics = $this->metrics;

        // Calculate averages
        if ($this->metrics['database']['query_count'] > 0) {
            $metrics['database']['average_query_time'] = $this->metrics['database']['total_query_time'] / $this->metrics['database']['query_count'];
        }

        // Add system info
        $metrics['system']['php_version'] = PHP_VERSION;
        $metrics['system']['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $metrics['system']['database_version'] = $this->getDatabaseVersion();

        return $metrics;
    }

    /**
     * Get database version
     */
    private function getDatabaseVersion() {
        try {
            return $this->db->getAttribute(PDO::ATTR_SERVER_VERSION);
        } catch (Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get slow queries
     */
    public function getSlowQueries($limit = 10) {
        usort($this->slowQueries, function($a, $b) {
            return $b['execution_time'] <=> $a['execution_time'];
        });

        return array_slice($this->slowQueries, 0, $limit);
    }

    /**
     * Generate performance report
     */
    public function generateReport($format = 'json') {
        $metrics = $this->getMetrics();
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'metrics' => $metrics,
            'alerts' => $this->checkAlerts(),
            'slow_queries' => $this->getSlowQueries(),
            'recommendations' => $this->generateRecommendations()
        ];

        switch ($format) {
            case 'json':
                return json_encode($report, JSON_PRETTY_PRINT);
            case 'html':
                return $this->generateHTMLReport($report);
            case 'text':
                return $this->generateTextReport($report);
            default:
                return json_encode($report);
        }
    }

    /**
     * Generate recommendations based on metrics
     */
    private function generateRecommendations() {
        $recommendations = [];
        $metrics = $this->getMetrics();

        // Memory recommendations
        $memoryUsage = $metrics['system']['memory_current'];
        if ($memoryUsage > $this->alertThresholds['memory_usage']) {
            $recommendations[] = "Consider optimizing memory usage or increasing memory limit";
        }

        // Query recommendations
        if ($metrics['database']['slow_queries'] > 0) {
            $recommendations[] = "Review and optimize slow database queries";
        }

        if ($metrics['database']['query_count'] > 100) {
            $recommendations[] = "Consider implementing query caching for frequently executed queries";
        }

        // Cache recommendations
        $hitRatio = $metrics['cache']['hit_ratio'];
        if ($hitRatio > 0 && $hitRatio < 80) {
            $recommendations[] = "Cache hit ratio is low ({$hitRatio}%). Consider optimizing cache strategy";
        }

        // Error rate recommendations
        $errorRate = ($metrics['requests']['total'] > 0) ?
            ($metrics['requests']['failed'] / $metrics['requests']['total']) * 100 : 0;

        if ($errorRate > 5) {
            $recommendations[] = "Error rate is high ({$errorRate}%). Investigate and fix errors";
        }

        return $recommendations;
    }

    /**
     * Generate HTML report
     */
    private function generateHTMLReport($report) {
        $html = "<!DOCTYPE html>
<html>
<head>
    <title>Performance Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .metric { background: #f5f5f5; padding: 10px; margin: 10px 0; }
        .alert { color: red; }
        .warning { color: orange; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Performance Report - " . $report['timestamp'] . "</h1>";

        $html .= "<h2>System Metrics</h2>";
        $html .= "<div class='metric'>Memory Usage: " . round($report['metrics']['system']['memory_current'] / 1024 / 1024, 2) . " MB</div>";
        $html .= "<div class='metric'>Peak Memory: " . round($report['metrics']['system']['memory_peak'] / 1024 / 1024, 2) . " MB</div>";
        $html .= "<div class='metric'>Execution Time: " . round($report['metrics']['system']['execution_time'], 2) . " seconds</div>";

        $html .= "<h2>Database Metrics</h2>";
        $html .= "<div class='metric'>Total Queries: " . $report['metrics']['database']['query_count'] . "</div>";
        $html .= "<div class='metric'>Slow Queries: " . $report['metrics']['database']['slow_queries'] . "</div>";

        $html .= "<h2>Recommendations</h2>";
        foreach ($report['recommendations'] as $recommendation) {
            $html .= "<div class='metric'>• {$recommendation}</div>";
        }

        $html .= "</body></html>";

        return $html;
    }

    /**
     * Generate text report
     */
    private function generateTextReport($report) {
        $text = "Performance Report - " . $report['timestamp'] . "\n";
        $text .= "=" . str_repeat("=", 50) . "\n\n";

        $text .= "System Metrics:\n";
        $text .= "- Memory Usage: " . round($report['metrics']['system']['memory_current'] / 1024 / 1024, 2) . " MB\n";
        $text .= "- Peak Memory: " . round($report['metrics']['system']['memory_peak'] / 1024 / 1024, 2) . " MB\n";
        $text .= "- Execution Time: " . round($report['metrics']['system']['execution_time'], 2) . " seconds\n\n";

        $text .= "Database Metrics:\n";
        $text .= "- Total Queries: " . $report['metrics']['database']['query_count'] . "\n";
        $text .= "- Slow Queries: " . $report['metrics']['database']['slow_queries'] . "\n\n";

        $text .= "Recommendations:\n";
        foreach ($report['recommendations'] as $recommendation) {
            $text .= "- {$recommendation}\n";
        }

        return $text;
    }

    /**
     * Store metrics in database
     */
    public function storeMetrics() {
        try {
            $metrics = $this->getMetrics();

            $sql = "INSERT INTO performance_metrics (
                timestamp, memory_usage, memory_peak, execution_time,
                query_count, slow_queries, cache_hit_ratio, error_rate
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                date('Y-m-d H:i:s'),
                $metrics['system']['memory_current'],
                $metrics['system']['memory_peak'],
                $metrics['system']['execution_time'],
                $metrics['database']['query_count'],
                $metrics['database']['slow_queries'],
                $metrics['cache']['hit_ratio'],
                ($metrics['requests']['total'] > 0) ?
                    ($metrics['requests']['failed'] / $metrics['requests']['total']) : 0
            ]);

            if ($this->logger) {
                $this->logger->info('PerformanceMonitor', 'Metrics stored in database');
            }

        } catch (Exception $e) {
            if ($this->logger) {
                $this->logger->error('PerformanceMonitor', 'Failed to store metrics: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get historical metrics
     */
    public function getHistoricalMetrics($hours = 24) {
        $sql = "SELECT * FROM performance_metrics
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY timestamp DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hours]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clean up old metrics
     */
    public function cleanupOldMetrics($days = 30) {
        $sql = "DELETE FROM performance_metrics
                WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([$days]);

        if ($this->logger) {
            $this->logger->info('PerformanceMonitor', "Cleaned up old metrics", [
                'days' => $days,
                'deleted_rows' => $result
            ]);
        }

        return $result;
    }
}