<?php
/**
 * Enhanced Location API - Rwanda Administrative Divisions
 * Handles province, district, sector, and cell data with caching and performance optimizations
 * Version: 2.0
 */

// Include session_check.php only when not from registration page
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isFromRegistration = strpos($referer, 'register-student.php') !== false;

if (!$isFromRegistration) {
    require_once '../session_check.php';
}

require_once '../config.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=3600'); // Cache for 1 hour

// Rate limiting for location API (skip for registration page)
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isFromRegistration = strpos($referer, 'register-student.php') !== false;

if (!$isFromRegistration) {
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate_limit_key = "location_api_{$client_ip}";
    if (!check_ip_rate_limit($rate_limit_key, 100, 3600)) { // 100 requests per hour
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => 3600
        ]);
        exit;
    }
}

// Allow demo access for location API when called from registration page
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isFromRegistration = strpos($referer, 'register-student.php') !== false;

if (!$isFromRegistration && (!isset($_SESSION['user_id']) || !isset($_SESSION['role']))) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Initialize cache for location data
$locationCache = [];
$cacheFile = sys_get_temp_dir() . '/rwanda_locations_cache.json';
$cacheExpiry = 24 * 60 * 60; // 24 hours

// Load cache if available and not expired
if (file_exists($cacheFile)) {
    $cacheData = json_decode(file_get_contents($cacheFile), true);
    if ($cacheData && (time() - $cacheData['timestamp']) < $cacheExpiry) {
        $locationCache = $cacheData['data'];
    }
}

try {
    // Skip CSRF validation for requests from registration page (unauthenticated access)
    if (!$isFromRegistration) {
        if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
            throw new Exception('Invalid CSRF token');
        }
    }

    $action = $_POST['action'] ?? '';
    $startTime = microtime(true);

    switch ($action) {
        case 'get_provinces':
            $result = handleGetProvinces();
            break;
        case 'get_districts':
            $result = handleGetDistricts();
            break;
        case 'get_sectors':
            $result = handleGetSectors();
            break;
        case 'get_cells':
            $result = handleGetCells();
            break;
        default:
            throw new Exception('Invalid action specified');
    }

    // Add performance metrics to response
    $result['performance'] = [
        'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
        'cached' => isset($locationCache[$action]),
        'timestamp' => date('c')
    ];

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Location API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => date('c')
    ]);
}

function handleGetProvinces() {
    global $pdo, $locationCache, $cacheFile;

    // Check cache first
    if (isset($locationCache['provinces'])) {
        return [
            'success' => true,
            'provinces' => $locationCache['provinces'],
            'cached' => true
        ];
    }

    try {
        // Get provinces from locations table
        $stmt = $pdo->query("SELECT DISTINCT province as name FROM locations ORDER BY province");
        $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($provinces)) {
            throw new Exception('No provinces data available');
        }

        // Add IDs to provinces (1-based index)
        $provincesWithIds = array_map(function($province, $index) {
            return [
                'id' => $index + 1,
                'name' => $province['name']
            ];
        }, $provinces, array_keys($provinces));

        // Cache the result
        $locationCache['provinces'] = $provincesWithIds;
        file_put_contents($cacheFile, json_encode([
            'timestamp' => time(),
            'data' => $locationCache
        ]));

        return [
            'success' => true,
            'provinces' => $provincesWithIds,
            'count' => count($provincesWithIds),
            'cached' => false
        ];
    } catch (Exception $e) {
        error_log("Error loading provinces: " . $e->getMessage());
        throw new Exception('Failed to load provinces data');
    }
}

function handleGetDistricts() {
    global $pdo;
    $provinceId = (int)($_POST['province_id'] ?? 0);

    if (!$provinceId || $provinceId <= 0) {
        throw new Exception('Valid province ID is required');
    }

    global $locationCache;

    // Check cache first
    $cacheKey = "districts_{$provinceId}";
    if (isset($locationCache[$cacheKey])) {
        return [
            'success' => true,
            'districts' => $locationCache[$cacheKey],
            'province_id' => $provinceId,
            'cached' => true
        ];
    }

    try {
        // Map province ID to province name
        $provinceNames = [
            1 => 'Kigali City',
            2 => 'Southern Province',
            3 => 'Western Province',
            4 => 'Eastern Province',
            5 => 'Northern Province'
        ];

        if (!isset($provinceNames[$provinceId])) {
            throw new Exception('Invalid province ID');
        }

        $provinceName = $provinceNames[$provinceId];

        // Get districts from locations table
        $stmt = $pdo->prepare("SELECT DISTINCT district as name FROM locations WHERE province = ? ORDER BY district");
        $stmt->execute([$provinceName]);
        $districts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($districts)) {
            return [
                'success' => true,
                'districts' => [],
                'province_id' => $provinceId,
                'message' => 'No districts found for this province',
                'cached' => false
            ];
        }

        // Add IDs to districts (1-based index)
        $districtsWithIds = array_map(function($district, $index) {
            return [
                'id' => $index + 1,
                'name' => $district['name']
            ];
        }, $districts, array_keys($districts));

        // Cache the result
        $locationCache[$cacheKey] = $districtsWithIds;
        updateLocationCache();

        return [
            'success' => true,
            'districts' => $districtsWithIds,
            'province_id' => $provinceId,
            'count' => count($districtsWithIds),
            'cached' => false
        ];
    } catch (Exception $e) {
        error_log("Error loading districts for province {$provinceId}: " . $e->getMessage());
        throw new Exception('Failed to load districts data');
    }
}

function handleGetSectors() {
    global $pdo;
    $districtId = (int)($_POST['district_id'] ?? 0);
    $provinceId = (int)($_POST['province_id'] ?? 0);

    if (!$districtId || $districtId <= 0) {
        throw new Exception('Valid district ID is required');
    }

    if (!$provinceId || $provinceId <= 0) {
        throw new Exception('Valid province ID is required');
    }

    global $locationCache;

    // Check cache first
    $cacheKey = "sectors_{$provinceId}_{$districtId}";
    if (isset($locationCache[$cacheKey])) {
        return [
            'success' => true,
            'sectors' => $locationCache[$cacheKey],
            'district_id' => $districtId,
            'province_id' => $provinceId,
            'cached' => true
        ];
    }

    try {
        // Map province ID to province name
        $provinceNames = [
            1 => 'Kigali City',
            2 => 'Southern Province',
            3 => 'Western Province',
            4 => 'Eastern Province',
            5 => 'Northern Province'
        ];

        if (!isset($provinceNames[$provinceId])) {
            throw new Exception('Invalid province ID');
        }

        $provinceName = $provinceNames[$provinceId];

        // Get all districts for this province to find the district name by ID
        $stmt = $pdo->prepare("SELECT DISTINCT district FROM locations WHERE province = ? ORDER BY district");
        $stmt->execute([$provinceName]);
        $allDistricts = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!isset($allDistricts[$districtId - 1])) {
            throw new Exception('Invalid district ID for this province');
        }

        $districtName = $allDistricts[$districtId - 1];

        // Get sectors from locations table
        $stmt = $pdo->prepare("SELECT DISTINCT sector as name FROM locations WHERE province = ? AND district = ? ORDER BY sector");
        $stmt->execute([$provinceName, $districtName]);
        $sectors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($sectors)) {
            return [
                'success' => true,
                'sectors' => [],
                'district_id' => $districtId,
                'province_id' => $provinceId,
                'message' => 'No sectors found for this district',
                'cached' => false
            ];
        }

        // Add IDs to sectors (1-based index)
        $sectorsWithIds = array_map(function($sector, $index) {
            return [
                'id' => $index + 1,
                'name' => $sector['name']
            ];
        }, $sectors, array_keys($sectors));

        // Cache the result
        $locationCache[$cacheKey] = $sectorsWithIds;
        updateLocationCache();

        return [
            'success' => true,
            'sectors' => $sectorsWithIds,
            'district_id' => $districtId,
            'province_id' => $provinceId,
            'count' => count($sectorsWithIds),
            'cached' => false
        ];
    } catch (Exception $e) {
        error_log("Error loading sectors for district {$districtId}: " . $e->getMessage());
        throw new Exception('Failed to load sectors data');
    }
}

function handleGetCells() {
    global $pdo;
    $sectorId = (int)($_POST['sector_id'] ?? 0);
    $districtId = (int)($_POST['district_id'] ?? 0);
    $provinceId = (int)($_POST['province_id'] ?? 0);

    if (!$sectorId || $sectorId <= 0) {
        throw new Exception('Valid sector ID is required');
    }

    if (!$districtId || $districtId <= 0) {
        throw new Exception('Valid district ID is required');
    }

    if (!$provinceId || $provinceId <= 0) {
        throw new Exception('Valid province ID is required');
    }

    global $locationCache;

    // Check cache first
    $cacheKey = "cells_{$provinceId}_{$districtId}_{$sectorId}";
    if (isset($locationCache[$cacheKey])) {
        return [
            'success' => true,
            'cells' => $locationCache[$cacheKey],
            'sector_id' => $sectorId,
            'district_id' => $districtId,
            'province_id' => $provinceId,
            'cached' => true
        ];
    }

    try {
        // Map province ID to province name
        $provinceNames = [
            1 => 'Kigali City',
            2 => 'Southern Province',
            3 => 'Western Province',
            4 => 'Eastern Province',
            5 => 'Northern Province'
        ];

        if (!isset($provinceNames[$provinceId])) {
            throw new Exception('Invalid province ID');
        }

        $provinceName = $provinceNames[$provinceId];

        // Get all districts for this province
        $stmt = $pdo->prepare("SELECT DISTINCT district FROM locations WHERE province = ? ORDER BY district");
        $stmt->execute([$provinceName]);
        $allDistricts = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!isset($allDistricts[$districtId - 1])) {
            throw new Exception('Invalid district ID for this province');
        }

        $districtName = $allDistricts[$districtId - 1];

        // Get all sectors for this province and district
        $stmt = $pdo->prepare("SELECT DISTINCT sector FROM locations WHERE province = ? AND district = ? ORDER BY sector");
        $stmt->execute([$provinceName, $districtName]);
        $allSectors = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!isset($allSectors[$sectorId - 1])) {
            throw new Exception('Invalid sector ID for this district');
        }

        $sectorName = $allSectors[$sectorId - 1];

        // Get cells from locations table
        $stmt = $pdo->prepare("SELECT DISTINCT cell as name FROM locations WHERE province = ? AND district = ? AND sector = ? ORDER BY cell");
        $stmt->execute([$provinceName, $districtName, $sectorName]);
        $cells = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($cells)) {
            return [
                'success' => true,
                'cells' => [],
                'sector_id' => $sectorId,
                'district_id' => $districtId,
                'province_id' => $provinceId,
                'message' => 'No cells found for this sector',
                'cached' => false
            ];
        }

        // Add IDs to cells (1-based index)
        $cellsWithIds = array_map(function($cell, $index) {
            return [
                'id' => $index + 1,
                'name' => $cell['name']
            ];
        }, $cells, array_keys($cells));

        // Cache the result
        $locationCache[$cacheKey] = $cellsWithIds;
        updateLocationCache();

        return [
            'success' => true,
            'cells' => $cellsWithIds,
            'sector_id' => $sectorId,
            'district_id' => $districtId,
            'province_id' => $provinceId,
            'count' => count($cellsWithIds),
            'cached' => false
        ];
    } catch (Exception $e) {
        error_log("Error loading cells for sector {$sectorId}: " . $e->getMessage());
        throw new Exception('Failed to load cells data');
    }
}

/**
 * Update the location cache file
 */
function updateLocationCache() {
    global $locationCache, $cacheFile;
    file_put_contents($cacheFile, json_encode([
        'timestamp' => time(),
        'data' => $locationCache
    ]));
}
?>