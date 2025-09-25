<?php
require 'config.php';

// Test the statistics AJAX call
if (isset($_GET['action']) && $_GET['action'] === 'get_statistics') {
    try {
        $stats = [];

        // Total departments
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM departments");
        $stats['total_departments'] = $stmt->fetchColumn();

        // Departments with HoDs
        $stmt = $pdo->query("SELECT COUNT(*) as assigned FROM departments WHERE hod_id IS NOT NULL");
        $stats['assigned_hods'] = $stmt->fetchColumn();

        // Total programs
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM options");
        $stats['total_programs'] = $stmt->fetchColumn();

        // Programs per department average
        if ($stats['total_departments'] > 0) {
            $stats['avg_programs_per_dept'] = round($stats['total_programs'] / $stats['total_departments'], 1);
        } else {
            $stats['avg_programs_per_dept'] = 0;
        }

        // Recent changes (last 30 days) - handle missing table gracefully
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as recent_changes FROM system_logs WHERE action LIKE '%department%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stats['recent_changes'] = $stmt->fetchColumn();
        } catch (PDOException $e) {
            $stats['recent_changes'] = 0;
        }

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Statistics retrieved successfully', 'data' => $stats]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve statistics: ' . $e->getMessage()]);
        exit;
    }
}

// Test the list departments AJAX call
if (isset($_GET['action']) && $_GET['action'] === 'list_departments') {
    try {
        $stmt = $pdo->query("
            SELECT d.id AS dept_id, d.name AS dept_name, d.hod_id, u.username AS hod_name
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
            ORDER BY d.name
        ");
        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($departments as &$dept) {
            // Get programs for this department
            $progStmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY name");
            $progStmt->execute([$dept['dept_id']]);
            $dept['programs'] = $progStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $dept['hod_name'] = $dept['hod_name'] ?: 'Not Assigned';
        }

        header('Content-Type: application/json');
        echo json_encode($departments);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to load departments: ' . $e->getMessage()]);
        exit;
    }
}

echo "AJAX Test Page - Add ?action=get_statistics or ?action=list_departments to test";
?>