<?php
/**
 * Registration Data Handler
 * Provides data for student registration form
 */

/**
 * Get active departments from database
 * @param PDO $pdo Database connection
 * @return array Array of departments or empty array on error
 */
function getDepartments(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Get provinces from database with fallback
 * @param PDO $pdo Database connection
 * @return array Array of provinces
 */
function getProvinces(PDO $pdo) {
    try {
        $stmt = $pdo->query("SELECT id, name FROM provinces ORDER BY name");
        $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching provinces: " . $e->getMessage());
        $provinces = [];
    }

    // Fallback to hardcoded provinces if DB query failed or returned empty
    if (empty($provinces)) {
        $provinces = [
            ['id' => 1, 'name' => 'Kigali City'],
            ['id' => 2, 'name' => 'Southern Province'],
            ['id' => 3, 'name' => 'Western Province'],
            ['id' => 4, 'name' => 'Eastern Province'],
            ['id' => 5, 'name' => 'Northern Province']
        ];
    }

    return $provinces;
}

/**
 * Validate required data availability
 * @param array $departments Departments array
 * @return void Logs warning if no departments
 */
function validateRequiredData(array $departments) {
    if (empty($departments)) {
        error_log("Warning: No departments found in database");
    }
}
?>