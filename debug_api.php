<?php
// Debug script to test API endpoints
header('Content-Type: application/json');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=rp_attendance_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "=== TESTING API ENDPOINTS ===\n\n";

    // Test 1: Get departments
    echo "1. Testing get_departments:\n";
    $stmt = $pdo->prepare("
        SELECT d.id, d.name,
               u.id as hod_id,
               CONCAT(COALESCE(u.username, ''), ' ', COALESCE(u.email, '')) as hod_name,
               u.email as hod_email
        FROM departments d
        LEFT JOIN users u ON d.hod_id = u.id
        ORDER BY d.name
    ");
    $stmt->execute();
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($departments as &$dept) {
        $dept['hod_name'] = trim($dept['hod_name']);
        if (empty($dept['hod_name']) || $dept['hod_name'] === ' ') {
            $dept['hod_name'] = 'Not Assigned';
            $dept['hod_email'] = null;
        }
        $dept['is_assigned'] = !empty($dept['hod_id']);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Departments retrieved successfully',
        'data' => $departments,
        'count' => count($departments)
    ], JSON_PRETTY_PRINT);

    echo "\n\n=== RAW DEPARTMENTS DATA ===\n";
    $stmt = $pdo->query('SELECT * FROM departments');
    $rawDepts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rawDepts, JSON_PRETTY_PRINT);

    echo "\n\n=== RAW USERS DATA ===\n";
    $stmt = $pdo->query('SELECT id, username, email, role FROM users WHERE role IN ("lecturer", "hod")');
    $rawUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rawUsers, JSON_PRETTY_PRINT);

    echo "\n\n=== STATISTICS ===\n";
    $stmt = $pdo->query('SELECT COUNT(*) as total FROM departments');
    echo "Total Departments: " . $stmt->fetchColumn() . "\n";

    $stmt = $pdo->query('SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL');
    echo "Assigned Departments: " . $stmt->fetchColumn() . "\n";

    $stmt = $pdo->query('SELECT COUNT(*) as lecturers FROM users WHERE role IN ("lecturer", "hod")');
    echo "Total Lecturers/HODs: " . $stmt->fetchColumn() . "\n";

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>