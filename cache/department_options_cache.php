<?php
/**
 * Department Options Caching Utility
 * Provides simple file-based caching for department-option relationships
 * Version: 1.0
 */

class DepartmentOptionsCache {
    private $cacheDir;
    private $defaultTtl = 3600; // 1 hour default TTL

    public function __construct($cacheDir = null) {
        $this->cacheDir = $cacheDir ?: __DIR__ . '/cache';
        $this->ensureCacheDirectory();
    }

    /**
     * Get cached data or execute callback and cache result
     */
    public function getOrSet($key, $ttl, $callback) {
        $cacheFile = $this->getCacheFilePath($key);

        // Check if cache exists and is valid
        if ($this->isCacheValid($cacheFile, $ttl)) {
            return $this->getFromCache($cacheFile);
        }

        // Execute callback and cache result
        $data = $callback();
        $this->setCache($cacheFile, $data, $ttl);

        return $data;
    }

    /**
     * Clear cache for specific key or all cache
     */
    public function clear($key = null) {
        if ($key) {
            $cacheFile = $this->getCacheFilePath($key);
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        } else {
            // Clear all cache files
            $files = glob($this->cacheDir . '/*.json');
            foreach ($files as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats() {
        $files = glob($this->cacheDir . '/*.json');
        $totalFiles = count($files);
        $totalSize = 0;

        foreach ($files as $file) {
            $totalSize += filesize($file);
        }

        return [
            'total_files' => $totalFiles,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2)
        ];
    }

    /**
     * Check if cache file exists and is valid
     */
    private function isCacheValid($cacheFile, $ttl) {
        if (!file_exists($cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($cacheFile);
        $currentTime = time();

        return ($currentTime - $cacheTime) < $ttl;
    }

    /**
     * Get data from cache file
     */
    private function getFromCache($cacheFile) {
        $data = json_decode(file_get_contents($cacheFile), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Invalid JSON, remove cache file
            unlink($cacheFile);
            return null;
        }

        return $data;
    }

    /**
     * Set cache data
     */
    private function setCache($cacheFile, $data, $ttl) {
        $cacheData = [
            'data' => $data,
            'expires_at' => time() + $ttl,
            'created_at' => time()
        ];

        file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT));
    }

    /**
     * Get cache file path for key
     */
    private function getCacheFilePath($key) {
        $safeKey = md5($key);
        return $this->cacheDir . '/' . $safeKey . '.json';
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }

        // Create .gitkeep to ensure directory is tracked
        if (!file_exists($this->cacheDir . '/.gitkeep')) {
            file_put_contents($this->cacheDir . '/.gitkeep', '');
        }
    }
}

// Utility functions for easy access
function getCachedDepartmentOptions($pdo, $departmentId, $ttl = 3600) {
    $cache = new DepartmentOptionsCache();

    return $cache->getOrSet(
        "dept_options_{$departmentId}",
        $ttl,
        function() use ($pdo, $departmentId) {
            $stmt = $pdo->prepare("
                SELECT id, name
                FROM options
                WHERE department_id = ?
                ORDER BY name
            ");
            $stmt->execute([$departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    );
}

function getCachedDepartments($pdo, $ttl = 3600) {
    $cache = new DepartmentOptionsCache();

    return $cache->getOrSet(
        'all_departments',
        $ttl,
        function() use ($pdo) {
            $stmt = $pdo->prepare("
                SELECT id, name
                FROM departments
                ORDER BY name
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    );
}

function clearDepartmentCache($departmentId = null) {
    $cache = new DepartmentOptionsCache();
    $cache->clear($departmentId ? "dept_options_{$departmentId}" : null);
}

function getCacheStats() {
    $cache = new DepartmentOptionsCache();
    return $cache->getStats();
}
?>