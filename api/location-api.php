<?php
/**
 * Location API - Rwanda Administrative Divisions
 * Handles province, district, sector, and cell data
 */

require_once '../session_check.php';
require_once '../config.php';
require_once '../rwanda_locations.php';

header('Content-Type: application/json');

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

try {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'get_provinces':
            handleGetProvinces();
            break;
        case 'get_districts':
            handleGetDistricts();
            break;
        case 'get_sectors':
            handleGetSectors();
            break;
        case 'get_cells':
            handleGetCells();
            break;
        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handleGetProvinces() {
    // Get provinces from comprehensive Rwanda locations data
    $provinces = getRwandaProvinces();

    echo json_encode([
        'success' => true,
        'provinces' => $provinces
    ]);
}

function handleGetDistricts() {
    $provinceId = (int)($_POST['province_id'] ?? 0);

    if (!$provinceId) {
        throw new Exception('Province ID is required');
    }

    // Get districts from comprehensive Rwanda locations data
    $districts = getRwandaDistricts($provinceId);

    echo json_encode([
        'success' => true,
        'districts' => $districts
    ]);
}

function handleGetSectors() {
    $districtId = (int)($_POST['district_id'] ?? 0);

    if (!$districtId) {
        throw new Exception('District ID is required');
    }

    // Get sectors from comprehensive Rwanda locations data
    $sectors = getRwandaSectors($districtId);

    echo json_encode([
        'success' => true,
        'sectors' => $sectors
    ]);
}

function handleGetCells() {
    $sectorId = (int)($_POST['sector_id'] ?? 0);

    if (!$sectorId) {
        throw new Exception('Sector ID is required');
    }

    // Get cells from comprehensive Rwanda locations data
    $cells = getRwandaCells($sectorId);

    echo json_encode([
        'success' => true,
        'cells' => $cells
    ]);
}
?>