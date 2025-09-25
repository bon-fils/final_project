<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Enhanced JSON response function
function jsonResponse($status, $message, $extra = []) {
    http_response_code($status === 'success' ? 200 : 400);
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}

// Input validation function
function validateInput($data, $field, $minLength = 1, $maxLength = 255) {
    $data = trim($data ?? '');
    if (empty($data)) {
        return "Field '$field' is required";
    }
    if (strlen($data) < $minLength) {
        return "Field '$field' must be at least $minLength characters";
    }
    if (strlen($data) > $maxLength) {
        return "Field '$field' must not exceed $maxLength characters";
    }
    return null;
}

// AJAX Request Handler
if (isset($_GET['ajax']) && $_GET['ajax'] === '1' && isset($_GET['action'])) {
    $action = $_GET['action'];
    
    try {
        switch ($action) {
            case 'list_departments':
                handleListDepartments();
                break;
                
            case 'add_department':
                handleAddDepartment();
                break;
                
            case 'edit_department':
                handleEditDepartment();
                break;
                
            case 'add_program':
                handleAddProgram();
                break;
                
            case 'delete_department':
                handleDeleteDepartment();
                break;
                
            case 'delete_program':
                handleDeleteProgram();
                break;

            case 'get_statistics':
                handleGetStatistics();
                break;

            case 'export_csv':
                handleExportCSV();
                break;

            case 'export_pdf':
                handleExportPDF();
                break;

            case 'bulk_delete':
                handleBulkDelete();
                break;

            case 'bulk_assign_hod':
                handleBulkAssignHod();
                break;

            case 'get_department_hierarchy':
                handleGetDepartmentHierarchy();
                break;

            default:
                jsonResponse('error', 'Invalid action');
        }
    } catch (Exception $e) {
        error_log("Action '$action' failed: " . $e->getMessage());
        jsonResponse('error', 'Operation failed: ' . $e->getMessage());
    }
}

function handleListDepartments() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT d.id AS dept_id, d.name AS dept_name, d.hod_id, u.username AS hod_name
        FROM departments d
        LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
        ORDER BY d.name
    ");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($departments as &$dept) {
        $dept['programs'] = getDepartmentPrograms($dept['dept_id']);
        $dept['hod_name'] = $dept['hod_name'] ?: 'Not Assigned';
    }

    header('Content-Type: application/json');
    echo json_encode($departments);
    exit;
}

function getDepartmentPrograms($deptId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY name");
        $stmt->execute([$deptId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Error loading programs for department $deptId: " . $e->getMessage());
        return [];
    }
}

function handleAddDepartment() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $name = trim($_POST['department_name'] ?? '');
    $hodId = !empty($_POST['hod_id']) ? (int)$_POST['hod_id'] : null;
    $programs = $_POST['programs'] ?? [];

    // Validation
    if ($error = validateInput($name, 'Department name', 2, 100)) {
        jsonResponse('error', $error);
    }

    // Check for duplicate department name
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse('error', 'Department already exists');
    }

    // Validate HoD exists and has correct role
    if ($hodId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'hod'");
        $stmt->execute([$hodId]);
        if ($stmt->fetchColumn() === 0) {
            jsonResponse('error', 'Invalid Head of Department selected');
        }
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO departments (name, hod_id) VALUES (?, ?)");
        $stmt->execute([$name, $hodId]);
        $deptId = $pdo->lastInsertId();

        // Add programs
        if (!empty($programs)) {
            addProgramsToDepartment($deptId, $programs);
        }

        $pdo->commit();
        jsonResponse('success', 'Department added successfully', ['dept_id' => $deptId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function addProgramsToDepartment($deptId, $programs) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO options (name, department_id) VALUES (?, ?)");
    
    foreach ($programs as $program) {
        $program = trim($program);
        if (!empty($program)) {
            // Check for duplicate program name in department
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE name = ? AND department_id = ?");
            $checkStmt->execute([$program, $deptId]);
            if ($checkStmt->fetchColumn() === 0) {
                $stmt->execute([$program, $deptId]);
            }
        }
    }
}

function handleEditDepartment() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $deptId = (int)($_POST['department_id'] ?? 0);
    $name = trim($_POST['department_name'] ?? '');
    $hodId = !empty($_POST['hod_id']) ? (int)$_POST['hod_id'] : null;

    if ($deptId <= 0) {
        jsonResponse('error', 'Invalid department ID');
    }

    if ($error = validateInput($name, 'Department name', 2, 100)) {
        jsonResponse('error', $error);
    }

    // Check if department exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
    $stmt->execute([$deptId]);
    if ($stmt->fetchColumn() === 0) {
        jsonResponse('error', 'Department not found');
    }

    // Check for duplicate name (excluding current department)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE name = ? AND id != ?");
    $stmt->execute([$name, $deptId]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse('error', 'Department name already exists');
    }

    // Validate HoD
    if ($hodId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'hod'");
        $stmt->execute([$hodId]);
        if ($stmt->fetchColumn() === 0) {
            jsonResponse('error', 'Invalid Head of Department selected');
        }
    }

    $stmt = $pdo->prepare("UPDATE departments SET name = ?, hod_id = ? WHERE id = ?");
    $stmt->execute([$name, $hodId, $deptId]);
    
    jsonResponse('success', 'Department updated successfully');
}

function handleAddProgram() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $deptId = (int)($_POST['department_id'] ?? 0);
    $programName = trim($_POST['program_name'] ?? '');

    if ($deptId <= 0) {
        jsonResponse('error', 'Invalid department ID');
    }

    if ($error = validateInput($programName, 'Program name', 2, 100)) {
        jsonResponse('error', $error);
    }

    // Check if department exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
    $stmt->execute([$deptId]);
    if ($stmt->fetchColumn() === 0) {
        jsonResponse('error', 'Department not found');
    }

    // Check for duplicate program name in department
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE name = ? AND department_id = ?");
    $stmt->execute([$programName, $deptId]);
    if ($stmt->fetchColumn() > 0) {
        jsonResponse('error', 'Program already exists in this department');
    }

    $stmt = $pdo->prepare("INSERT INTO options (name, department_id) VALUES (?, ?)");
    $stmt->execute([$programName, $deptId]);
    
    jsonResponse('success', 'Program added successfully');
}

function handleDeleteDepartment() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $deptId = (int)($_POST['department_id'] ?? 0);
    
    if ($deptId <= 0) {
        jsonResponse('error', 'Invalid department ID');
    }

    $pdo->beginTransaction();
    try {
        // Check if department has any dependencies
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE department_id = ?");
        $stmt->execute([$deptId]);
        $programCount = $stmt->fetchColumn();

        // Delete programs first
        $pdo->prepare("DELETE FROM options WHERE department_id = ?")->execute([$deptId]);
        
        // Delete department
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$deptId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Department not found or already deleted");
        }

        $pdo->commit();
        jsonResponse('success', "Department deleted successfully (removed $programCount programs)");
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleDeleteProgram() {
    global $pdo;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $progId = (int)($_POST['program_id'] ?? 0);
    
    if ($progId <= 0) {
        jsonResponse('error', 'Invalid program ID');
    }

    $stmt = $pdo->prepare("DELETE FROM options WHERE id = ?");
    $stmt->execute([$progId]);
    
    if ($stmt->rowCount() === 0) {
        jsonResponse('error', 'Program not found or already deleted');
    }
    
    jsonResponse('success', 'Program deleted successfully');
}

function handleGetStatistics() {
    global $pdo;

    try {
        // Get department statistics
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

        // Department with most programs
        $stmt = $pdo->prepare("
            SELECT d.name, COUNT(o.id) as program_count
            FROM departments d
            LEFT JOIN options o ON d.id = o.department_id
            GROUP BY d.id, d.name
            ORDER BY program_count DESC
            LIMIT 1
        ");
        $stmt->execute();
        $topDept = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['largest_department'] = $topDept ?: ['name' => 'None', 'program_count' => 0];

        // Recent changes (last 30 days) - only if system_logs table exists
        try {
            $stmt = $pdo->query("
                SELECT COUNT(*) as recent_changes
                FROM system_logs
                WHERE action LIKE '%department%' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stats['recent_changes'] = $stmt->fetchColumn();
        } catch (PDOException $e) {
            // system_logs table doesn't exist, skip this metric
            $stats['recent_changes'] = 0;
        }

        jsonResponse('success', 'Statistics retrieved successfully', $stats);
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to retrieve statistics: ' . $e->getMessage());
    }
}

function handleExportCSV() {
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT
                d.id,
                d.name as department_name,
                u.username as hod_name,
                COUNT(o.id) as program_count,
                GROUP_CONCAT(o.name ORDER BY o.name SEPARATOR '; ') as programs
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
            LEFT JOIN options o ON d.id = o.department_id
            GROUP BY d.id, d.name, u.username
            ORDER BY d.name
        ");

        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="departments_directory_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // CSV headers
        fputcsv($output, ['ID', 'Department Name', 'Head of Department', 'Program Count', 'Programs']);

        // CSV data
        foreach ($departments as $dept) {
            fputcsv($output, [
                $dept['id'],
                $dept['department_name'],
                $dept['hod_name'] ?: 'Not Assigned',
                $dept['program_count'],
                $dept['programs'] ?: 'No programs'
            ]);
        }

        fclose($output);
        exit;
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to export CSV: ' . $e->getMessage());
    }
}

function handleExportPDF() {
    // For PDF export, we'll create a simple HTML that can be printed/saved as PDF
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT
                d.id,
                d.name as department_name,
                u.username as hod_name,
                COUNT(o.id) as program_count,
                GROUP_CONCAT(o.name ORDER BY o.name SEPARATOR ', ') as programs
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
            LEFT JOIN options o ON d.id = o.department_id
            GROUP BY d.id, d.name, u.username
            ORDER BY d.name
        ");

        $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate HTML for PDF
        $html = generatePDFHTML($departments);

        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="departments_directory_' . date('Y-m-d') . '.html"');
        echo $html;
        exit;
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to export PDF: ' . $e->getMessage());
    }
}

function generatePDFHTML($departments) {
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Departments Directory</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; margin-bottom: 30px; }
            .department { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
            .dept-name { font-size: 18px; font-weight: bold; color: #0066cc; }
            .hod-name { font-style: italic; color: #666; }
            .programs { margin-top: 10px; }
            .program { display: inline-block; background: #f0f0f0; padding: 3px 8px; margin: 2px; border-radius: 3px; }
            @media print { body { margin: 0; } }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Rwanda Polytechnic - Departments Directory</h1>
            <p>Generated on: ' . date('F j, Y, g:i a') . '</p>
        </div>';

    foreach ($departments as $dept) {
        $html .= '
        <div class="department">
            <div class="dept-name">' . htmlspecialchars($dept['department_name']) . '</div>
            <div class="hod-name">Head of Department: ' . htmlspecialchars($dept['hod_name'] ?: 'Not Assigned') . '</div>
            <div class="programs">
                <strong>Programs (' . $dept['program_count'] . '):</strong><br>';

        if ($dept['programs']) {
            $programs = explode(', ', $dept['programs']);
            foreach ($programs as $program) {
                $html .= '<span class="program">' . htmlspecialchars($program) . '</span>';
            }
        } else {
            $html .= '<em>No programs assigned</em>';
        }

        $html .= '
            </div>
        </div>';
    }

    $html .= '
    </body>
    </html>';

    return $html;
}

function handleBulkDelete() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $departmentIds = $_POST['department_ids'] ?? [];

    if (empty($departmentIds)) {
        jsonResponse('error', 'No departments selected for deletion');
    }

    // Ensure department_ids is an array
    if (!is_array($departmentIds)) {
        $departmentIds = [$departmentIds];
    }

    $departmentIds = array_map('intval', $departmentIds);
    $placeholders = str_repeat('?,', count($departmentIds) - 1) . '?';

    $pdo->beginTransaction();
    try {
        // Delete programs first
        $stmt = $pdo->prepare("DELETE FROM options WHERE department_id IN ($placeholders)");
        $stmt->execute($departmentIds);

        // Delete departments
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id IN ($placeholders)");
        $stmt->execute($departmentIds);

        $deletedCount = $stmt->rowCount();

        $pdo->commit();
        jsonResponse('success', "Successfully deleted $deletedCount departments");
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse('error', 'Failed to delete departments: ' . $e->getMessage());
    }
}

function handleBulkAssignHod() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $departmentIds = $_POST['department_ids'] ?? [];
    $hodId = (int)($_POST['hod_id'] ?? 0);

    if (empty($departmentIds)) {
        jsonResponse('error', 'No departments selected');
    }

    if ($hodId <= 0) {
        jsonResponse('error', 'Invalid Head of Department selected');
    }

    // Validate HoD exists and has correct role
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ? AND role = 'hod'");
    $stmt->execute([$hodId]);
    if ($stmt->fetchColumn() === 0) {
        jsonResponse('error', 'Invalid Head of Department selected');
    }

    // Ensure department_ids is an array
    if (!is_array($departmentIds)) {
        $departmentIds = [$departmentIds];
    }

    $departmentIds = array_map('intval', $departmentIds);
    $placeholders = str_repeat('?,', count($departmentIds) - 1) . '?';

    try {
        $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id IN ($placeholders)");
        $stmt->execute(array_merge([$hodId], $departmentIds));

        $updatedCount = $stmt->rowCount();
        jsonResponse('success', "Successfully assigned HoD to $updatedCount departments");
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to assign HoD: ' . $e->getMessage());
    }
}

function handleGetDepartmentHierarchy() {
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT
                d.id,
                d.name as department_name,
                d.hod_id,
                u.username as hod_name,
                COUNT(o.id) as program_count,
                COUNT(DISTINCT s.id) as student_count
            FROM departments d
            LEFT JOIN users u ON d.hod_id = u.id AND u.role = 'hod'
            LEFT JOIN options o ON d.id = o.department_id
            LEFT JOIN students s ON s.department_id = d.id
            GROUP BY d.id, d.name, d.hod_id, u.username
            ORDER BY d.name
        ");

        $hierarchy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse('success', 'Department hierarchy retrieved successfully', $hierarchy);
    } catch (Exception $e) {
        jsonResponse('error', 'Failed to retrieve hierarchy: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments Management | RP Attendance System</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #0066cc;
            --primary-dark: #003366;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .sidebar {
            background: var(--primary-dark);
            color: white;
            height: 100vh;
            position: fixed;
            width: 280px;
        }

        .main-content {
            margin-left: 280px;
            padding: 20px;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .btn {
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: var(--primary-dark);
        }

        .program-badge {
            background: #e9ecef;
            border-radius: 20px;
            padding: 4px 12px;
            font-size: 0.875rem;
        }

        .loading-overlay {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(5px);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }

        .department-card {
            border-left: 4px solid var(--primary-color);
        }

        .action-buttons .btn {
            margin: 2px;
        }

        /* Enhanced mobile responsiveness */
        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }

            .statistics-container .col-md-3 {
                margin-bottom: 15px;
            }

            .card-title {
                font-size: 1rem;
            }

            .dropdown-menu {
                width: 200px;
            }

            .search-container {
                width: 100% !important;
                margin-top: 10px;
            }

            .bulk-actions {
                width: 100%;
                margin-top: 10px;
            }

            .bulk-actions .btn {
                width: 100%;
                margin-bottom: 5px;
            }
        }

        /* Print styles */
        @media print {
            .sidebar,
            .btn,
            .dropdown,
            .modal,
            .loading-overlay,
            .alert {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
                break-inside: avoid;
                margin-bottom: 20px;
            }

            .department-card {
                border: 1px solid #000 !important;
                margin-bottom: 15px;
            }

            .statistics-container {
                display: none;
            }

            .card-header {
                background: #f8f9fa !important;
                color: #000 !important;
                border-bottom: 1px solid #000 !important;
            }

            .text-primary {
                color: #000 !important;
            }
        }

        /* Statistics cards animation */
        .statistics-container .card {
            transition: transform 0.2s ease;
        }

        .statistics-container .card:hover {
            transform: translateY(-2px);
        }

        /* Enhanced department cards */
        .department-card {
            position: relative;
            overflow: hidden;
        }

        .department-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }

        .department-card.selected {
            border: 2px solid var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }

        /* Loading animation for cards */
        .card-loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Enhanced form styling */
        .form-floating {
            margin-bottom: 1rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }

        /* Notification badges */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: bold;
        }

        /* Enhanced search */
        .search-container {
            position: relative;
        }

        .search-container .btn {
            border-left: none;
        }

        .search-container .form-control:focus + .btn {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay position-fixed top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center" 
         id="loadingOverlay" style="z-index: 9999;">
        <div class="text-center">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
            <p class="text-muted">Processing your request...</p>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4 text-center">
            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                <i class="fas fa-cog"></i>
            </div>
            <h5 class="mb-0">Admin Dashboard</h5>
        </div>
        
        <nav class="nav flex-column p-3">
            <a class="nav-link text-white mb-2" href="admin-dashboard.php">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
            <a class="nav-link text-white mb-2 active" href="manage-departments.php">
                <i class="fas fa-building me-2"></i>Departments
            </a>
            <a class="nav-link text-white mb-2" href="register-student.php">
                <i class="fas fa-user-plus me-2"></i>Register Student
            </a>
            <a class="nav-link text-white mb-2" href="admin-reports.php">
                <i class="fas fa-chart-bar me-2"></i>Reports
            </a>
            <a class="nav-link text-white mb-2" href="assign-hod.php">
                <i class="fas fa-user-tie me-2"></i>Assign HOD
            </a>
            <a class="nav-link text-white" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 text-primary">
                    <i class="fas fa-building me-2"></i>Departments Directory
                </h1>
                <p class="text-muted mb-0">Comprehensive management of departments and programs</p>
            </div>
            <div class="d-flex gap-2">
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-download me-2"></i>Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="exportData('csv')">
                            <i class="fas fa-file-csv me-2"></i>Export as CSV
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="exportData('pdf')">
                            <i class="fas fa-file-pdf me-2"></i>Export as PDF
                        </a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs me-2"></i>Bulk Actions
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="showBulkDeleteModal()">
                            <i class="fas fa-trash me-2"></i>Bulk Delete
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="showBulkAssignHodModal()">
                            <i class="fas fa-user-tie me-2"></i>Bulk Assign HoD
                        </a></li>
                    </ul>
                </div>
                <button class="btn btn-primary" onclick="loadDepartments()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh
                </button>
            </div>
        </div>

        <!-- Statistics Dashboard -->
        <div class="row g-3 mb-4" id="statisticsContainer">
            <!-- Statistics will be loaded here -->
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Department Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    <span id="formTitle">Add New Department</span>
                </h5>
            </div>
            <div class="card-body">
                <form id="departmentForm">
                    <input type="hidden" id="departmentId" name="department_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departmentName" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="departmentName" name="department_name" 
                                   placeholder="Enter department name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="hodSelect" class="form-label">Head of Department</label>
                            <select class="form-select" id="hodSelect" name="hod_id">
                                <option value="">-- Select HoD --</option>
                                <?php
                                $hods = $pdo->query("SELECT id, username FROM users WHERE role = 'hod' ORDER BY username")->fetchAll();
                                foreach ($hods as $hod) {
                                    echo "<option value='{$hod['id']}'>{$hod['username']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Programs</label>
                        <div id="programsContainer">
                            <div class="input-group mb-2">
                                <input type="text" class="form-control" name="programs[]" 
                                       placeholder="Enter program name">
                                <button type="button" class="btn btn-outline-danger remove-program" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addProgramField">
                            <i class="fas fa-plus me-1"></i>Add Program
                        </button>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            <span id="submitText">Save Department</span>
                        </button>
                        <button type="button" class="btn btn-secondary" id="cancelEdit" style="display: none;">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Departments List -->
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <h5 class="mb-0 me-3">
                        <i class="fas fa-list me-2"></i>Departments Directory
                    </h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                        <label class="form-check-label text-white" for="selectAllCheckbox">
                            Select All
                        </label>
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-sort me-1"></i>Sort
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#" onclick="sortDepartments('name')">
                                <i class="fas fa-sort-alpha-down me-2"></i>By Name
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="sortDepartments('programs')">
                                <i class="fas fa-sort-numeric-down me-2"></i>By Programs Count
                            </a></li>
                            <li><a class="dropdown-item" href="#" onclick="sortDepartments('hod')">
                                <i class="fas fa-sort me-2"></i>By HoD Status
                            </a></li>
                        </ul>
                    </div>
                    <div class="input-group" style="width: 300px;">
                        <input type="text" class="form-control" placeholder="Search departments..." id="searchInput">
                        <button class="btn btn-light" type="button" id="clearSearch">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="departmentsContainer" class="row g-3">
                    <!-- Departments will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Bulk Delete Modal -->
        <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger">
                            <i class="fas fa-trash me-2"></i>Bulk Delete Departments
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete the selected departments? This action cannot be undone.</p>
                        <div id="selectedDepartmentsList" class="mb-3"></div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            All programs and associated data will also be deleted.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()">
                            <i class="fas fa-trash me-2"></i>Delete Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Assign HoD Modal -->
        <div class="modal fade" id="bulkAssignHodModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-primary">
                            <i class="fas fa-user-tie me-2"></i>Bulk Assign Head of Department
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="bulkHodSelect" class="form-label">Select Head of Department</label>
                            <select class="form-select" id="bulkHodSelect" required>
                                <option value="">-- Choose HoD --</option>
                                <?php
                                $hods = $pdo->query("SELECT id, username FROM users WHERE role = 'hod' ORDER BY username")->fetchAll();
                                foreach ($hods as $hod) {
                                    echo "<option value='{$hod['id']}'>{$hod['username']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div id="bulkAssignDepartmentsList" class="mb-3"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="confirmBulkAssignHod()">
                            <i class="fas fa-user-tie me-2"></i>Assign HoD
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variables
        let allDepartments = [];
        let currentEditId = null;
        let selectedDepartments = new Set();
        let currentSort = { field: 'name', direction: 'asc' };

        // DOM Ready
        $(document).ready(function() {
            loadDepartments();
            loadStatistics();
            setupEventHandlers();
        });

        function setupEventHandlers() {
            // Department form submission
            $('#departmentForm').on('submit', handleDepartmentSubmit);

            // Add program field
            $('#addProgramField').on('click', addProgramField);

            // Remove program field
            $(document).on('click', '.remove-program', function() {
                if ($('#programsContainer .input-group').length > 1) {
                    $(this).closest('.input-group').remove();
                }
            });

            // Cancel edit
            $('#cancelEdit').on('click', cancelEdit);

            // Search functionality
            $('#searchInput').on('input', filterDepartments);
            $('#clearSearch').on('click', function() {
                $('#searchInput').val('');
                filterDepartments();
            });

            // Select all checkbox
            $('#selectAllCheckbox').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.department-checkbox').prop('checked', isChecked);

                if (isChecked) {
                    $('.department-checkbox').each(function() {
                        selectedDepartments.add(parseInt($(this).val()));
                    });
                } else {
                    selectedDepartments.clear();
                }
                updateBulkActionsVisibility();
            });

            // Individual department checkboxes
            $(document).on('change', '.department-checkbox', function() {
                const deptId = parseInt($(this).val());
                if ($(this).is(':checked')) {
                    selectedDepartments.add(deptId);
                } else {
                    selectedDepartments.delete(deptId);
                    $('#selectAllCheckbox').prop('checked', false);
                }
                updateBulkActionsVisibility();
            });

            // Form reset
            $('#departmentForm').on('reset', function() {
                $('#programsContainer').html(`
                    <div class="input-group mb-2">
                        <input type="text" class="form-control" name="programs[]" placeholder="Enter program name">
                        <button type="button" class="btn btn-outline-danger remove-program" disabled>
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `);
                cancelEdit();
            });
        }

        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show">
                    <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alertContainer').html(alertHtml);
            setTimeout(() => $('.alert').alert('close'), 5000);
        }

        function showLoading() {
            $('#loadingOverlay').removeClass('d-none').addClass('d-flex');
        }

        function hideLoading() {
            $('#loadingOverlay').removeClass('d-flex').addClass('d-none');
        }

        function loadDepartments() {
            showLoading();
            
            $.get('?ajax=1&action=list_departments')
                .done(function(data) {
                    allDepartments = data;
                    renderDepartments(data);
                    updateStatistics(data);
                })
                .fail(function(xhr, status, error) {
                    console.error('Error loading departments:', error);
                    showAlert('danger', 'Failed to load departments. Please try again.');
                })
                .always(function() {
                    hideLoading();
                });
        }

        function renderDepartments(departments) {
            const container = $('#departmentsContainer');
            
            if (departments.length === 0) {
                container.html(`
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Departments Found</h5>
                        <p class="text-muted">Start by adding your first department using the form above.</p>
                    </div>
                `);
                return;
            }

            let html = '';
            departments.forEach(dept => {
                const programsHtml = dept.programs.map(prog => `
                    <span class="program-badge d-inline-block me-2 mb-2">
                        ${prog.name}
                        <button class="btn btn-sm btn-link text-danger p-0 ms-1 delete-program" 
                                data-program-id="${prog.id}" title="Delete program">
                            <i class="fas fa-times"></i>
                        </button>
                    </span>
                `).join('');

                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="card department-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center">
                                        <div class="form-check me-2">
                                            <input class="form-check-input department-checkbox"
                                                   type="checkbox" value="${dept.dept_id}"
                                                   id="dept-${dept.dept_id}">
                                            <label class="form-check-label" for="dept-${dept.dept_id}"></label>
                                        </div>
                                        <h6 class="card-title text-primary mb-0">${dept.dept_name}</h6>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary border-0 dropdown-toggle"
                                                type="button" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item edit-department" href="#"
                                                   data-dept-id="${dept.dept_id}"
                                                   data-dept-name="${dept.dept_name}"
                                                   data-hod-id="${dept.hod_id || ''}">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item text-danger delete-department" href="#"
                                                   data-dept-id="${dept.dept_id}"
                                                   data-dept-name="${dept.dept_name}">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Head of Department:</small>
                                    <div>${dept.hod_name}</div>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Programs (${dept.programs.length}):</small>
                                    <div class="mt-1">${programsHtml || '<em class="text-muted">No programs</em>'}</div>
                                </div>
                                
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm add-program-input" 
                                           placeholder="Add new program" data-dept-id="${dept.dept_id}">
                                    <button class="btn btn-sm btn-primary add-program-btn" 
                                            data-dept-id="${dept.dept_id}">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.html(html);
            attachDepartmentEventHandlers();
        }

        function attachDepartmentEventHandlers() {
            // Edit department
            $('.edit-department').on('click', function(e) {
                e.preventDefault();
                const deptId = $(this).data('dept-id');
                const deptName = $(this).data('dept-name');
                const hodId = $(this).data('hod-id');
                
                editDepartment(deptId, deptName, hodId);
            });

            // Delete department
            $('.delete-department').on('click', function(e) {
                e.preventDefault();
                const deptId = $(this).data('dept-id');
                const deptName = $(this).data('dept-name');
                
                if (confirm(`Are you sure you want to delete "${deptName}" and all its programs?`)) {
                    deleteDepartment(deptId);
                }
            });

            // Add program
            $('.add-program-btn').on('click', function() {
                const deptId = $(this).data('dept-id');
                const input = $(this).siblings('.add-program-input');
                const programName = input.val().trim();
                
                if (programName) {
                    addProgram(deptId, programName);
                    input.val('');
                }
            });

            // Delete program
            $('.delete-program').on('click', function() {
                const programId = $(this).data('program-id');
                
                if (confirm('Are you sure you want to delete this program?')) {
                    deleteProgram(programId);
                }
            });

            // Enter key for adding programs
            $('.add-program-input').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    $(this).siblings('.add-program-btn').click();
                }
            });
        }

        function editDepartment(deptId, deptName, hodId) {
            currentEditId = deptId;
            $('#departmentId').val(deptId);
            $('#departmentName').val(deptName);
            $('#hodSelect').val(hodId || '');
            $('#formTitle').text('Edit Department');
            $('#submitText').text('Update Department');
            $('#cancelEdit').show();
            
            $('html, body').animate({
                scrollTop: $('#departmentForm').offset().top - 20
            }, 500);
        }

        function cancelEdit() {
            currentEditId = null;
            $('#departmentId').val('');
            $('#formTitle').text('Add New Department');
            $('#submitText').text('Save Department');
            $('#cancelEdit').hide();
        }

        function handleDepartmentSubmit(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const programs = Array.from(formData.getAll('programs[]')).filter(p => p.trim());
            formData.delete('programs[]');
            programs.forEach(p => formData.append('programs[]', p));
            
            const action = currentEditId ? 'edit_department' : 'add_department';
            const url = `?ajax=1&action=${action}`;
            
            showLoading();
            
            fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    $('#departmentForm')[0].reset();
                    cancelEdit();
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred. Please try again.');
            })
            .finally(() => {
                hideLoading();
            });
        }

        function addProgram(deptId, programName) {
            showLoading();
            
            $.post('?ajax=1&action=add_program', {
                department_id: deptId,
                program_name: programName
            })
            .done(function(data) {
                if (data.status === 'success') {
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to add program. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function deleteDepartment(deptId) {
            showLoading();
            
            $.post('?ajax=1&action=delete_department', {
                department_id: deptId
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to delete department. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function deleteProgram(programId) {
            showLoading();
            
            $.post('?ajax=1&action=delete_program', {
                program_id: programId
            })
            .done(function(data) {
                if (data.status === 'success') {
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to delete program. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function filterDepartments() {
            const searchTerm = $('#searchInput').val().toLowerCase();
            
            if (!searchTerm) {
                renderDepartments(allDepartments);
                return;
            }
            
            const filtered = allDepartments.filter(dept => 
                dept.dept_name.toLowerCase().includes(searchTerm) ||
                dept.hod_name.toLowerCase().includes(searchTerm) ||
                dept.programs.some(prog => prog.name.toLowerCase().includes(searchTerm))
            );
            
            renderDepartments(filtered);
        }

        function loadStatistics() {
            $.get('?ajax=1&action=get_statistics')
                .done(function(data) {
                    if (data.status === 'success') {
                        renderStatistics(data.data);
                    }
                })
                .fail(function() {
                    console.error('Failed to load statistics');
                });
        }

        function renderStatistics(stats) {
            const container = $('#statisticsContainer');
            const html = `
                <div class="col-md-3">
                    <div class="card text-center border-primary">
                        <div class="card-body">
                            <i class="fas fa-building fa-2x text-primary mb-2"></i>
                            <h4 class="text-primary">${stats.total_departments}</h4>
                            <p class="text-muted mb-0">Total Departments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-success">
                        <div class="card-body">
                            <i class="fas fa-user-tie fa-2x text-success mb-2"></i>
                            <h4 class="text-success">${stats.assigned_hods}</h4>
                            <p class="text-muted mb-0">Assigned HoDs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-info">
                        <div class="card-body">
                            <i class="fas fa-graduation-cap fa-2x text-info mb-2"></i>
                            <h4 class="text-info">${stats.total_programs}</h4>
                            <p class="text-muted mb-0">Total Programs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-warning">
                        <div class="card-body">
                            <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                            <h4 class="text-warning">${stats.avg_programs_per_dept}</h4>
                            <p class="text-muted mb-0">Avg Programs/Dept</p>
                        </div>
                    </div>
                </div>
            `;
            container.html(html);
        }

        function exportData(format) {
            const action = format === 'csv' ? 'export_csv' : 'export_pdf';
            window.location.href = `?ajax=1&action=${action}`;
        }

        function showBulkDeleteModal() {
            if (selectedDepartments.size === 0) {
                showAlert('warning', 'Please select departments to delete');
                return;
            }

            const selectedDepts = allDepartments.filter(dept => selectedDepartments.has(dept.dept_id));
            const listHtml = selectedDepts.map(dept =>
                `<div class="d-flex align-items-center mb-2">
                    <i class="fas fa-building me-2 text-danger"></i>
                    <span>${dept.dept_name}</span>
                </div>`
            ).join('');

            $('#selectedDepartmentsList').html(listHtml);
            $('#bulkDeleteModal').modal('show');
        }

        function showBulkAssignHodModal() {
            if (selectedDepartments.size === 0) {
                showAlert('warning', 'Please select departments to assign HoD');
                return;
            }

            const selectedDepts = allDepartments.filter(dept => selectedDepartments.has(dept.dept_id));
            const listHtml = selectedDepts.map(dept =>
                `<div class="d-flex align-items-center mb-2">
                    <i class="fas fa-building me-2 text-primary"></i>
                    <span>${dept.dept_name}</span>
                </div>`
            ).join('');

            $('#bulkAssignDepartmentsList').html(listHtml);
            $('#bulkAssignHodModal').modal('show');
        }

        function confirmBulkDelete() {
            if (selectedDepartments.size === 0) return;

            showLoading();

            $.post('?ajax=1&action=bulk_delete', {
                department_ids: Array.from(selectedDepartments)
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    $('#bulkDeleteModal').modal('hide');
                    selectedDepartments.clear();
                    updateBulkActionsVisibility();
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to delete departments. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function confirmBulkAssignHod() {
            const hodId = $('#bulkHodSelect').val();

            if (!hodId) {
                showAlert('warning', 'Please select a Head of Department');
                return;
            }

            if (selectedDepartments.size === 0) return;

            showLoading();

            $.post('?ajax=1&action=bulk_assign_hod', {
                department_ids: Array.from(selectedDepartments),
                hod_id: hodId
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    $('#bulkAssignHodModal').modal('hide');
                    selectedDepartments.clear();
                    updateBulkActionsVisibility();
                    loadDepartments();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to assign HoD. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function updateBulkActionsVisibility() {
            const hasSelection = selectedDepartments.size > 0;
            $('.dropdown-toggle').prop('disabled', !hasSelection);
        }

        function sortDepartments(field) {
            let sortedDepartments = [...allDepartments];

            switch (field) {
                case 'name':
                    sortedDepartments.sort((a, b) => {
                        const comparison = a.dept_name.localeCompare(b.dept_name);
                        return currentSort.direction === 'asc' ? comparison : -comparison;
                    });
                    break;
                case 'programs':
                    sortedDepartments.sort((a, b) => {
                        const comparison = a.programs.length - b.programs.length;
                        return currentSort.direction === 'asc' ? comparison : -comparison;
                    });
                    break;
                case 'hod':
                    sortedDepartments.sort((a, b) => {
                        const aHasHod = a.hod_id ? 1 : 0;
                        const bHasHod = b.hod_id ? 1 : 0;
                        const comparison = aHasHod - bHasHod;
                        return currentSort.direction === 'asc' ? comparison : -comparison;
                    });
                    break;
            }

            currentSort.field = field;
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
            renderDepartments(sortedDepartments);
        }

        function updateStatistics(departments) {
            const totalDepts = departments.length;
            const totalPrograms = departments.reduce((sum, dept) => sum + dept.programs.length, 0);
            const assignedHods = departments.filter(dept => dept.hod_id).length;

            // Update statistics cards if they exist
            if ($('#statisticsContainer').length) {
                loadStatistics();
            }
        }

        function addProgramField() {
            const container = $('#programsContainer');
            const count = container.children().length;
            
            container.append(`
                <div class="input-group mb-2">
                    <input type="text" class="form-control" name="programs[]" placeholder="Enter program name">
                    <button type="button" class="btn btn-outline-danger remove-program">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `);
            
            // Enable remove buttons if there's more than one field
            if (count > 0) {
                $('.remove-program').prop('disabled', false);
            }
        }

        // Keyboard shortcuts
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + R to refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                loadDepartments();
            }
            
            // Escape to cancel edit
            if (e.key === 'Escape' && currentEditId) {
                cancelEdit();
            }
        });
    </script>
</body>
</html>