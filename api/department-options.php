<?php
/**
 * Department Options API
 * Returns options for a specific department
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once "config.php";

try {
    if (isset($_GET['department_id']) && is_numeric($_GET['department_id'])) {
        $departmentId = (int)$_GET['department_id'];
        
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM options 
            WHERE department_id = ? AND status = 'active' 
            ORDER BY name
        ");
        $stmt->execute([$departmentId]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'options' => $options
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid department ID'
        ]);
    }
} catch (Exception $e) {
    error_log("Department options API error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch options'
    ]);
}
?>
