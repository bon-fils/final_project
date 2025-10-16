<?php
/**
 * Admin Dashboard Controller
 * Handles dashboard data retrieval and processing
 * Version: 3.0 - MVC Architecture with caching and optimization
 */

require_once 'Config.php';
require_once 'Logger.php';
require_once 'CacheService.php';

class AdminDashboardController {
    private $pdo;
    private $cache;
    private $logger;

    public function __construct() {
        $this->pdo = getDBConnection();
        $this->cache = new CacheService();
        $this->logger = new Logger();
    }

    /**
     * Get comprehensive dashboard statistics
     * @return array Dashboard statistics
     */
    public function getDashboardStats() {
        try {
            // Check cache first (5 minutes)
            $cacheKey = 'dashboard_stats';
            $cachedData = $this->cache->get($cacheKey);

            if ($cachedData !== null) {
                $this->logger->info('Dashboard stats served from cache');
                return $cachedData;
            }

            // Get fresh data
            $stats = $this->fetchDashboardStats();

            // Cache the results
            $this->cache->set($cacheKey, $stats, 300); // 5 minutes

            $this->logger->info('Dashboard stats calculated and cached');
            return $stats;

        } catch (Exception $e) {
            $this->logger->error('Failed to get dashboard stats', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->getFallbackStats($e->getMessage());
        }
    }

    /**
     * Fetch dashboard statistics from database
     * @return array
     */
    private function fetchDashboardStats() {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(CASE WHEN table_type = 'students' THEN 1 END) AS total_students,
                COUNT(CASE WHEN table_type = 'lecturers' AND role = 'lecturer' THEN 1 END) AS total_lecturers,
                COUNT(CASE WHEN table_type = 'lecturers' AND role = 'hod' THEN 1 END) AS total_hods,
                COUNT(CASE WHEN table_type = 'departments' THEN 1 END) AS total_departments,
                COUNT(CASE WHEN table_type = 'active_sessions' THEN 1 END) AS active_attendance,
                COUNT(CASE WHEN table_type = 'total_sessions' THEN 1 END) AS total_sessions,
                COUNT(CASE WHEN table_type = 'pending_requests' THEN 1 END) AS pending_requests,
                COUNT(CASE WHEN table_type = 'approved_requests' THEN 1 END) AS approved_requests,
                COUNT(CASE WHEN table_type = 'today_records' THEN 1 END) AS today_records,
                COUNT(CASE WHEN table_type = 'active_users_24h' THEN 1 END) AS active_users_24h,
                ROUND(AVG(CASE WHEN table_type = 'attendance_rate' THEN attendance_rate END), 1) AS avg_attendance_rate
            FROM (
                SELECT 'students' AS table_type, NULL AS role, NULL AS attendance_rate FROM students WHERE status = 'active'
                UNION ALL
                SELECT 'lecturers', u.role, NULL FROM lecturers l LEFT JOIN users u ON l.user_id = u.id WHERE l.status = 'active'
                UNION ALL
                SELECT 'departments', NULL, NULL FROM departments WHERE status = 'active'
                UNION ALL
                SELECT 'active_sessions', NULL, NULL FROM attendance_sessions WHERE end_time IS NULL AND DATE(created_at) = CURDATE()
                UNION ALL
                SELECT 'total_sessions', NULL, NULL FROM attendance_sessions WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                UNION ALL
                SELECT 'pending_requests', NULL, NULL FROM leave_requests WHERE status = 'pending' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                UNION ALL
                SELECT 'approved_requests', NULL, NULL FROM leave_requests WHERE status = 'approved' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                UNION ALL
                SELECT 'today_records', NULL, NULL FROM attendance_records WHERE DATE(recorded_at) = CURDATE()
                UNION ALL
                SELECT 'active_users_24h', NULL, NULL FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND role IN ('admin', 'lecturer', 'hod')
                UNION ALL
                SELECT 'attendance_rate', NULL, (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0))
                FROM attendance_sessions s
                LEFT JOIN attendance_records ar ON s.id = ar.session_id
                WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY s.id
            ) AS combined_data
        ");

        $stmt->execute();
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        // Sanitize and validate data
        return [
            'total_students' => max(0, (int)($counts['total_students'] ?? 0)),
            'total_lecturers' => max(0, (int)($counts['total_lecturers'] ?? 0)),
            'total_hods' => max(0, (int)($counts['total_hods'] ?? 0)),
            'total_departments' => max(0, (int)($counts['total_departments'] ?? 0)),
            'active_attendance' => max(0, (int)($counts['active_attendance'] ?? 0)),
            'total_sessions' => max(0, (int)($counts['total_sessions'] ?? 0)),
            'pending_requests' => max(0, (int)($counts['pending_requests'] ?? 0)),
            'approved_requests' => max(0, (int)($counts['approved_requests'] ?? 0)),
            'today_records' => max(0, (int)($counts['today_records'] ?? 0)),
            'active_users_24h' => max(0, (int)($counts['active_users_24h'] ?? 0)),
            'avg_attendance_rate' => round(max(0, min(100, (float)($counts['avg_attendance_rate'] ?? 0))), 1),
            'last_updated' => date('Y-m-d H:i:s'),
            'cache_status' => 'fresh'
        ];
    }

    /**
     * Get fallback statistics when database fails
     * @param string $error Error message
     * @return array
     */
    private function getFallbackStats($error = '') {
        return [
            'total_students' => 0,
            'total_lecturers' => 0,
            'total_hods' => 0,
            'total_departments' => 0,
            'active_attendance' => 0,
            'total_sessions' => 0,
            'pending_requests' => 0,
            'approved_requests' => 0,
            'today_records' => 0,
            'active_users_24h' => 0,
            'avg_attendance_rate' => 0,
            'last_updated' => date('Y-m-d H:i:s'),
            'error' => $error ?: 'Database temporarily unavailable',
            'cache_status' => 'error'
        ];
    }

    /**
     * Clear dashboard cache
     * @return bool
     */
    public function clearCache() {
        try {
            $this->cache->delete('dashboard_stats');
            $this->logger->info('Dashboard cache cleared');
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to clear dashboard cache', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get real-time dashboard data for AJAX requests
     * @return array
     */
    public function getRealtimeStats() {
        // Force fresh data for real-time requests
        $this->clearCache();
        return $this->getDashboardStats();
    }

    /**
     * Get system health status
     * @return array
     */
    public function getSystemHealth() {
        $health = [
            'database' => $this->checkDatabaseHealth(),
            'cache' => $this->checkCacheHealth(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $health['overall'] = $health['database']['status'] === 'healthy' &&
                           $health['cache']['status'] === 'healthy' ? 'healthy' : 'degraded';

        return $health;
    }

    /**
     * Check database health
     * @return array
     */
    private function checkDatabaseHealth() {
        try {
            $stmt = $this->pdo->query("SELECT 1");
            $stmt->execute();
            return ['status' => 'healthy', 'response_time' => microtime(true)];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }

    /**
     * Check cache health
     * @return array
     */
    private function checkCacheHealth() {
        try {
            $testKey = 'health_check_' . time();
            $this->cache->set($testKey, 'test', 10);
            $result = $this->cache->get($testKey);
            $this->cache->delete($testKey);

            return $result === 'test'
                ? ['status' => 'healthy']
                : ['status' => 'degraded', 'message' => 'Cache read/write test failed'];
        } catch (Exception $e) {
            return ['status' => 'unhealthy', 'error' => $e->getMessage()];
        }
    }
}