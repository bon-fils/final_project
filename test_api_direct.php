<?php
// Test the API directly without HTTP requests
require_once 'config.php';

// Simulate the API call
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM departments) as total_departments,
            (SELECT COUNT(*) FROM departments WHERE hod_id IS NOT NULL) as assigned_departments,
            (SELECT COUNT(*) FROM departments WHERE hod_id IS NULL) as unassigned_departments,
            (SELECT COUNT(*) FROM lecturers WHERE role IN ('lecturer', 'hod')) as total_lecturers,
            (SELECT COUNT(DISTINCT hod_id) FROM departments WHERE hod_id IS NOT NULL) as lecturers_assigned_as_hod,
            (SELECT COUNT(*) FROM users WHERE role = 'hod') as hod_user_accounts
    ");

    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add calculated fields
    $stats['assignment_percentage'] = $stats['total_departments'] > 0
        ? round(($stats['assigned_departments'] / $stats['total_departments']) * 100, 1)
        : 0;

    $stats['available_lecturers'] = $stats['total_lecturers'] - $stats['lecturers_assigned_as_hod'];
    $stats['accounts_missing'] = $stats['assigned_departments'] - $stats['hod_user_accounts'];

    $response = [
        'status' => 'success',
        'message' => 'Assignment statistics retrieved successfully',
        'data' => $stats
    ];

    echo "API Response:\n";
    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>