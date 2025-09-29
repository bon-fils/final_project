<?php
/**
 * Cache Utilities
 * Provides caching functionality for the application
 */

/**
 * Simple file-based cache implementation
 */
class SimpleCache {
    private $cache_dir;
    private $default_ttl;

    public function __construct(string $cache_dir = null, int $default_ttl = 3600) {
        $this->cache_dir = $cache_dir ?: sys_get_temp_dir() . '/app_cache';
        $this->default_ttl = $default_ttl;

        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }

    /**
     * Get cached data
     */
    public function get(string $key): ?array {
        $cache_file = $this->getCacheFile($key);

        if (!file_exists($cache_file)) {
            return null;
        }

        $data = json_decode(file_get_contents($cache_file), true);

        if (!$data || !isset($data['expires']) || !isset($data['value'])) {
            $this->delete($key);
            return null;
        }

        if ($data['expires'] < time()) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    /**
     * Set cached data
     */
    public function set(string $key, $value, int $ttl = null): bool {
        $cache_file = $this->getCacheFile($key);
        $ttl = $ttl ?: $this->default_ttl;

        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];

        return file_put_contents($cache_file, json_encode($data), LOCK_EX) !== false;
    }

    /**
     * Delete cached data
     */
    public function delete(string $key): bool {
        $cache_file = $this->getCacheFile($key);
        if (file_exists($cache_file)) {
            return unlink($cache_file);
        }
        return true;
    }

    /**
     * Clear all cached data
     */
    public function clear(): bool {
        $files = glob($this->cache_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        return true;
    }

    /**
     * Check if cache exists and is valid
     */
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    /**
     * Get cache file path
     */
    private function getCacheFile(string $key): string {
        $hash = hash('sha256', $key);
        return $this->cache_dir . '/' . $hash . '.cache';
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array {
        $files = glob($this->cache_dir . '/*');
        $total_files = count($files);
        $total_size = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                $total_size += filesize($file);
            }
        }

        return [
            'total_files' => $total_files,
            'total_size' => $total_size,
            'cache_dir' => $this->cache_dir
        ];
    }
}

/**
 * Global cache instance
 */
$cache = new SimpleCache(__DIR__ . '/cache', 1800); // 30 minutes default TTL

/**
 * Cache wrapper functions
 */
function cache_get(string $key): ?array {
    global $cache;
    return $cache->get($key);
}

function cache_set(string $key, $value, int $ttl = null): bool {
    global $cache;
    return $cache->set($key, $value, $ttl);
}

function cache_delete(string $key): bool {
    global $cache;
    return $cache->delete($key);
}

function cache_has(string $key): bool {
    global $cache;
    return $cache->has($key);
}

function cache_clear(): bool {
    global $cache;
    return $cache->clear();
}

function cache_stats(): array {
    global $cache;
    return $cache->getStats();
}

/**
 * Cached database query function
 */
function cached_query(PDO $pdo, string $query, array $params = [], string $cache_key = null, int $ttl = 300): array {
    if ($cache_key) {
        $cached_result = cache_get($cache_key);
        if ($cached_result !== null) {
            return $cached_result;
        }
    }

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($cache_key) {
            cache_set($cache_key, $result, $ttl);
        }

        return $result;
    } catch (PDOException $e) {
        error_log("Cached query error: " . $e->getMessage());
        return [];
    }
}

/**
 * Cache cleanup function (call periodically)
 */
function cleanup_expired_cache(): void {
    global $cache;
    $stats = $cache->getStats();

    // If cache directory has too many files, clear some expired ones
    if ($stats['total_files'] > 1000) {
        $files = glob($cache->getCacheDir() . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                $data = json_decode(file_get_contents($file), true);
                if ($data && isset($data['expires']) && $data['expires'] < $now) {
                    unlink($file);
                }
            }
        }
    }
}

/**
 * Get cache directory path
 */
function get_cache_dir(): string {
    global $cache;
    return $cache->getCacheDir();
}
?>