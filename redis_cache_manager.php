<?php
/**
 * Redis Cache Manager
 * High-performance caching system with Redis backend
 * Version: 1.0
 */

class RedisCacheManager {
    private $redis;
    private $enabled;
    private $fallbackCache;
    private $logger;
    
    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->enabled = $this->initializeRedis();
        $this->fallbackCache = [];
        
        if (!$this->enabled) {
            $this->log('warning', 'Redis not available, using fallback cache');
        }
    }
    
    /**
     * Initialize Redis connection
     */
    private function initializeRedis() {
        try {
            if (!class_exists('Redis')) {
                $this->log('warning', 'Redis extension not installed');
                return false;
            }
            
            $this->redis = new Redis();
            
            $host = $_ENV['REDIS_HOST'] ?? 'localhost';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            $password = $_ENV['REDIS_PASSWORD'] ?? '';
            $database = (int)($_ENV['REDIS_DB'] ?? 0);
            
            $this->redis->connect($host, $port, 2.5); // 2.5 second timeout
            
            if (!empty($password)) {
                $this->redis->auth($password);
            }
            
            $this->redis->select($database);
            
            // Test connection
            $this->redis->ping();
            
            $this->log('info', 'Redis connection established', [
                'host' => $host,
                'port' => $port,
                'database' => $database
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->log('error', 'Redis connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cached data
     */
    public function get($key) {
        try {
            if ($this->enabled) {
                $data = $this->redis->get($key);
                if ($data !== false) {
                    return json_decode($data, true);
                }
            } else {
                // Fallback to file cache
                return $this->getFromFileCache($key);
            }
        } catch (Exception $e) {
            $this->log('error', 'Cache get failed: ' . $e->getMessage());
            return $this->getFromFileCache($key);
        }
        
        return null;
    }
    
    /**
     * Set cached data
     */
    public function set($key, $data, $ttl = 3600) {
        try {
            if ($this->enabled) {
                $serialized = json_encode($data);
                $result = $this->redis->setex($key, $ttl, $serialized);
                return $result;
            } else {
                // Fallback to file cache
                return $this->setToFileCache($key, $data, $ttl);
            }
        } catch (Exception $e) {
            $this->log('error', 'Cache set failed: ' . $e->getMessage());
            return $this->setToFileCache($key, $data, $ttl);
        }
    }
    
    /**
     * Delete cached data
     */
    public function delete($key) {
        try {
            if ($this->enabled) {
                return $this->redis->del($key);
            } else {
                return $this->deleteFromFileCache($key);
            }
        } catch (Exception $e) {
            $this->log('error', 'Cache delete failed: ' . $e->getMessage());
            return $this->deleteFromFileCache($key);
        }
    }
    
    /**
     * Check if key exists
     */
    public function exists($key) {
        try {
            if ($this->enabled) {
                return $this->redis->exists($key);
            } else {
                return $this->existsInFileCache($key);
            }
        } catch (Exception $e) {
            $this->log('error', 'Cache exists check failed: ' . $e->getMessage());
            return $this->existsInFileCache($key);
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        try {
            if ($this->enabled) {
                return $this->redis->flushDB();
            } else {
                return $this->clearFileCache();
            }
        } catch (Exception $e) {
            $this->log('error', 'Cache clear failed: ' . $e->getMessage());
            return $this->clearFileCache();
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        try {
            if ($this->enabled) {
                $info = $this->redis->info();
                return [
                    'type' => 'redis',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'used_memory' => $info['used_memory_human'] ?? '0B',
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0
                ];
            } else {
                return [
                    'type' => 'file_fallback',
                    'cache_dir' => 'cache/',
                    'fallback_enabled' => true
                ];
            }
        } catch (Exception $e) {
            $this->log('error', 'Cache stats failed: ' . $e->getMessage());
            return ['type' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * File cache fallback methods
     */
    private function getFromFileCache($key) {
        $cacheFile = $this->getCacheFilePath($key);
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            
            if ($data && isset($data['expires']) && $data['expires'] > time()) {
                return $data['value'];
            } else {
                // Expired, delete file
                unlink($cacheFile);
            }
        }
        
        return null;
    }
    
    private function setToFileCache($key, $data, $ttl) {
        $cacheFile = $this->getCacheFilePath($key);
        $cacheDir = dirname($cacheFile);
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cacheData = [
            'value' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($cacheFile, json_encode($cacheData), LOCK_EX) !== false;
    }
    
    private function deleteFromFileCache($key) {
        $cacheFile = $this->getCacheFilePath($key);
        
        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }
        
        return true;
    }
    
    private function existsInFileCache($key) {
        $cacheFile = $this->getCacheFilePath($key);
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            return $data && isset($data['expires']) && $data['expires'] > time();
        }
        
        return false;
    }
    
    private function clearFileCache() {
        $cacheDir = 'cache/';
        
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*.cache');
            foreach ($files as $file) {
                unlink($file);
            }
            return true;
        }
        
        return false;
    }
    
    private function getCacheFilePath($key) {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return 'cache/' . $safeKey . '.cache';
    }
    
    /**
     * Logging helper
     */
    private function log($level, $message, $context = []) {
        if ($this->logger) {
            $this->logger->$level('RedisCacheManager', $message, $context);
        }
    }
    
    /**
     * Cache helper methods for common use cases
     */
    
    /**
     * Cache database query results
     */
    public function cacheQuery($query, $params, $result, $ttl = 300) {
        $key = 'query_' . md5($query . serialize($params));
        return $this->set($key, $result, $ttl);
    }
    
    /**
     * Get cached query results
     */
    public function getCachedQuery($query, $params) {
        $key = 'query_' . md5($query . serialize($params));
        return $this->get($key);
    }
    
    /**
     * Cache user session data
     */
    public function cacheUserSession($userId, $data, $ttl = 1800) {
        $key = 'user_session_' . $userId;
        return $this->set($key, $data, $ttl);
    }
    
    /**
     * Get cached user session data
     */
    public function getCachedUserSession($userId) {
        $key = 'user_session_' . $userId;
        return $this->get($key);
    }
    
    /**
     * Cache dashboard statistics
     */
    public function cacheDashboardStats($role, $data, $ttl = 300) {
        $key = 'dashboard_stats_' . $role;
        return $this->set($key, $data, $ttl);
    }
    
    /**
     * Get cached dashboard statistics
     */
    public function getCachedDashboardStats($role) {
        $key = 'dashboard_stats_' . $role;
        return $this->get($key);
    }
    
    /**
     * Cache department data
     */
    public function cacheDepartments($data, $ttl = 600) {
        $key = 'departments_list';
        return $this->set($key, $data, $ttl);
    }
    
    /**
     * Get cached department data
     */
    public function getCachedDepartments() {
        $key = 'departments_list';
        return $this->get($key);
    }
    
    /**
     * Invalidate user-related cache
     */
    public function invalidateUserCache($userId) {
        $patterns = [
            'user_session_' . $userId,
            'dashboard_stats_*',
            'user_data_' . $userId
        ];
        
        foreach ($patterns as $pattern) {
            if ($this->enabled) {
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            } else {
                $this->delete($pattern);
            }
        }
    }
}
