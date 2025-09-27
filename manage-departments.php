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
                
            case 'list_options':
                handleListOptions();
                break;

            case 'edit_program':
                handleEditProgram();
                break;

            case 'update_program_status':
                handleUpdateProgramStatus();
                break;

            default:
                jsonResponse('error', 'Invalid action');
        }
    } catch (Exception $e) {
        error_log("Action '$action' failed: " . $e->getMessage());
        jsonResponse('error', 'Operation failed: ' . $e->getMessage());
    }
}

function handleListOptions() {
    global $pdo;

    try {
        // Try to select with status and created_at columns
        $stmt = $pdo->query("
            SELECT o.id, o.name, o.department_id, o.status, o.created_at,
                   d.name as department_name
            FROM options o
            LEFT JOIN departments d ON o.department_id = d.id
            ORDER BY o.name
        ");
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add default values if columns are null
        foreach ($options as &$option) {
            $option['status'] = $option['status'] ?? 'active';
            $option['created_at'] = $option['created_at'] ?? date('Y-m-d H:i:s');
        }
    } catch (Exception $e) {
        // Fallback query without status and created_at columns
        try {
            $stmt = $pdo->query("
                SELECT o.id, o.name, o.department_id,
                       d.name as department_name
                FROM options o
                LEFT JOIN departments d ON o.department_id = d.id
                ORDER BY o.name
            ");
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add default status and created_at
            foreach ($options as &$option) {
                $option['status'] = 'active';
                $option['created_at'] = date('Y-m-d H:i:s');
            }
        } catch (Exception $e2) {
            // If both queries fail, return empty array
            $options = [];
        }
    }

    header('Content-Type: application/json');
    echo json_encode($options);
    exit;
}

function handleEditProgram() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $progId = (int)($_POST['program_id'] ?? 0);
    $name = trim($_POST['program_name'] ?? '');
    $status = trim($_POST['status'] ?? 'active');

    if ($progId <= 0) {
        jsonResponse('error', 'Invalid program ID');
    }

    if ($error = validateInput($name, 'Program name', 2, 100)) {
        jsonResponse('error', $error);
    }

    if (!in_array($status, ['active', 'inactive'])) {
        jsonResponse('error', 'Invalid status value');
    }

    try {
        // Check if program exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE id = ?");
        $stmt->execute([$progId]);
        if ($stmt->fetchColumn() === 0) {
            jsonResponse('error', 'Program not found');
        }

        // Check for duplicate name (excluding current program)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE name = ? AND id != ?");
        $stmt->execute([$name, $progId]);
        if ($stmt->fetchColumn() > 0) {
            jsonResponse('error', 'Program name already exists');
        }

        $stmt = $pdo->prepare("UPDATE options SET name = ?, status = ? WHERE id = ?");
        $result = $stmt->execute([$name, $status, $progId]);

        if ($result && $stmt->rowCount() > 0) {
            jsonResponse('success', 'Program updated successfully');
        } else {
            jsonResponse('error', 'Failed to update program');
        }
    } catch (Exception $e) {
        error_log("Error updating program $progId: " . $e->getMessage());
        jsonResponse('error', 'An error occurred while updating the program');
    }
}

function handleUpdateProgramStatus() {
    global $pdo;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse('error', 'Invalid request method');
    }

    $progId = (int)($_POST['program_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($progId <= 0) {
        jsonResponse('error', 'Invalid program ID');
    }

    if (!in_array($status, ['active', 'inactive'])) {
        jsonResponse('error', 'Invalid status value');
    }

    try {
        $stmt = $pdo->prepare("UPDATE options SET status = ? WHERE id = ?");
        $result = $stmt->execute([$status, $progId]);

        if ($result && $stmt->rowCount() > 0) {
            jsonResponse('success', 'Program status updated successfully');
        } else {
            jsonResponse('error', 'Program not found or status unchanged');
        }
    } catch (Exception $e) {
        error_log("Error updating program status $progId: " . $e->getMessage());
        jsonResponse('error', 'An error occurred while updating program status');
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

    foreach ($departments as $key => $dept) {
        $departments[$key]['programs'] = getDepartmentPrograms($dept['dept_id']);
        $departments[$key]['hod_name'] = $dept['hod_name'] ?: 'Not Assigned';
    }

    header('Content-Type: application/json');
    echo json_encode($departments);
    exit;
}

function getDepartmentPrograms($deptId) {
    global $pdo;

    try {
        // Try to select with status and created_at columns
        $stmt = $pdo->prepare("SELECT id, name, status, created_at FROM options WHERE department_id = ? ORDER BY name");
        $stmt->execute([$deptId]);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add status indicators for display
        foreach ($programs as &$program) {
            $program['status'] = $program['status'] ?? 'active';
            $program['status_label'] = $program['status'] === 'active' ? 'Active' : 'Inactive';
            $program['status_badge'] = $program['status'] === 'active' ? 'success' : 'warning';
        }

        return $programs ?: [];
    } catch (Exception $e) {
        // Fallback query without status and created_at columns
        try {
            $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY name");
            $stmt->execute([$deptId]);
            $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Add default status indicators
            foreach ($programs as &$program) {
                $program['status'] = 'active';
                $program['status_label'] = 'Active';
                $program['status_badge'] = 'success';
                $program['created_at'] = date('Y-m-d H:i:s');
            }

            return $programs ?: [];
        } catch (Exception $e2) {
            error_log("Error loading programs for department $deptId: " . $e->getMessage());
            return [];
        }
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
    $status = trim($_POST['status'] ?? 'active');

    if ($deptId <= 0) {
        jsonResponse('error', 'Invalid department ID');
    }

    if ($error = validateInput($programName, 'Program name', 2, 100)) {
        jsonResponse('error', $error);
    }

    // Validate status
    if (!in_array($status, ['active', 'inactive'])) {
        $status = 'active';
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

    $stmt = $pdo->prepare("INSERT INTO options (name, department_id, status) VALUES (?, ?, ?)");
    $stmt->execute([$programName, $deptId, $status]);

    $newProgramId = $pdo->lastInsertId();

    jsonResponse('success', 'Program added successfully', [
        'program_id' => $newProgramId,
        'program_name' => $programName,
        'status' => $status
    ]);
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
            /* Primary Brand Colors - RP Blue with Modern Palette */
            --primary-color: #0066cc;
            --primary-dark: #003366;
            --primary-light: #e6f0ff;
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);

            /* Status Colors - Enhanced Contrast and Modern */
            --success-color: #10b981;
            --success-light: #d1fae5;
            --success-dark: #047857;
            --success-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);

            --danger-color: #ef4444;
            --danger-light: #fee2e2;
            --danger-dark: #dc2626;
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);

            --warning-color: #f59e0b;
            --warning-light: #fef3c7;
            --warning-dark: #d97706;
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);

            --info-color: #06b6d4;
            --info-light: #cffafe;
            --info-dark: #0891b2;
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);

            /* Neutral Colors */
            --gray-50: #f8f9fa;
            --gray-100: #e9ecef;
            --gray-200: #dee2e6;
            --gray-300: #ced4da;
            --gray-400: #adb5bd;
            --gray-500: #6c757d;
            --gray-600: #495057;
            --gray-700: #343a40;
            --gray-800: #212529;
            --gray-900: #000000;

            /* Layout Variables */
            --sidebar-width: 280px;
            --header-height: 70px;

            /* Design Tokens */
            --border-radius: 8px;
            --border-radius-lg: 12px;
            --border-radius-sm: 4px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow: 0 2px 8px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.12);
            --shadow-lg: 0 8px 24px rgba(0,0,0,0.15);
            --shadow-hover: 0 4px 12px rgba(0,0,0,0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;

            /* Typography */
            --font-family: 'Inter', sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 2rem;
            --font-weight-normal: 400;
            --font-weight-medium: 500;
            --font-weight-semibold: 600;
            --font-weight-bold: 700;

            /* Spacing */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0066cc;
            min-height: 100vh;
            overflow-x: hidden;
            font-size: var(--font-size-base);
            line-height: 1.6;
            color: var(--gray-700);
            font-weight: var(--font-weight-normal);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            margin: 0;
            position: relative;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 30px;
            min-height: calc(100vh - var(--header-height));
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Typography */
        h1, h2, h3, h4, h5, h6 {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .text-muted {
            color: #6c757d !important;
            opacity: 0.8;
        }

        /* Cards - Matching admin/index.php */
        .card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            padding: 0;
            margin-bottom: 20px;
        }

        .card-header h5 {
            color: #003366;
            font-weight: 600;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .card-header h5 i {
            margin-right: 10px;
            color: #0066cc;
        }

        .card-header.bg-success {
            background: var(--success-gradient) !important;
        }

        .card-header.bg-info {
            background: var(--info-gradient) !important;
        }

        .card-header.bg-warning {
            background: var(--warning-gradient) !important;
        }

        .card-header.bg-danger {
            background: var(--danger-gradient) !important;
        }

        .card-body {
            padding: 0;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #003366;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 10px;
            color: #0066cc;
        }

        .card-text {
            color: #6c757d;
            line-height: 1.6;
        }

        /* Buttons */
        .btn {
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: var(--font-weight-medium);
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            position: relative;
            overflow: hidden;
            font-size: var(--font-size-sm);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: var(--transition-fast);
        }

        .btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: var(--primary-gradient);
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
            color: white;
            border: none;
            font-weight: 500;
        }

        .btn-primary:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
            color: white;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            font-weight: 500;
        }

        .btn-outline-primary:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
            border-color: var(--primary-color);
        }

        .btn-outline-secondary {
            border: 2px solid var(--gray-300);
            color: var(--gray-600);
            background: transparent;
            font-weight: 500;
        }

        .btn-outline-secondary:hover {
            background: var(--gray-600);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }

        .btn-success {
            background: var(--success-gradient);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            font-weight: 500;
        }

        .btn-success:hover {
            background: var(--success-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
        }

        .btn-danger {
            background: var(--danger-gradient);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
            font-weight: 500;
        }

        .btn-danger:hover {
            background: var(--danger-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        .btn-warning {
            background: var(--warning-gradient);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
            font-weight: 500;
        }

        .btn-warning:hover {
            background: var(--warning-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
        }

        .btn-info {
            background: var(--info-gradient);
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
            font-weight: 500;
        }

        .btn-info:hover {
            background: var(--info-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
        }

        .btn-sm {
            padding: var(--spacing-xs) calc(var(--spacing-sm) + 0.25rem);
            font-size: var(--font-size-xs);
        }

        .btn-lg {
            padding: calc(var(--spacing-sm) + 0.25rem) var(--spacing-lg);
            font-size: var(--font-size-base);
        }

        /* Forms */
        .form-control, .form-select {
            border-radius: var(--border-radius);
            border: 2px solid var(--gray-200);
            padding: calc(var(--spacing-sm) + 0.25rem);
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.9);
            font-size: var(--font-size-sm);
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--gray-300);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
            background: white;
        }

        .form-control::placeholder {
            color: var(--gray-400);
            font-weight: var(--font-weight-normal);
        }

        .form-label {
            font-weight: var(--font-weight-semibold);
            color: var(--gray-700);
            margin-bottom: var(--spacing-xs);
            font-size: var(--font-size-sm);
        }

        .form-text {
            color: var(--gray-500);
            font-size: var(--font-size-xs);
            margin-top: var(--spacing-xs);
        }

        .input-group .form-control:focus + .btn {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        .input-group-text {
            background: var(--gray-50);
            border-color: var(--gray-200);
            color: var(--gray-600);
        }

        /* Form Validation */
        .is-invalid {
            border-color: var(--danger-color) !important;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1) !important;
        }

        .is-valid {
            border-color: var(--success-color) !important;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1) !important;
        }

        .invalid-feedback {
            color: var(--danger-color);
            font-size: var(--font-size-xs);
            margin-top: var(--spacing-xs);
        }

        .valid-feedback {
            color: var(--success-color);
            font-size: var(--font-size-xs);
            margin-top: var(--spacing-xs);
        }

        /* Department Cards */
        .department-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            border-left: 4px solid var(--primary-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            margin-bottom: var(--spacing-xl);
        }

        .department-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: var(--transition);
        }

        .department-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            width: 0;
            background: linear-gradient(135deg, rgba(0, 102, 204, 0.05), rgba(0, 102, 204, 0.02));
            transition: var(--transition);
        }

        .department-card:hover::before {
            transform: scaleX(1);
        }

        .department-card:hover::after {
            width: 100%;
        }

        .department-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-left-color: var(--primary-dark);
        }

        .department-card .card-body {
            padding: var(--spacing-lg);
        }

        .department-card .card-title {
            color: var(--primary-dark);
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-semibold);
            margin-bottom: var(--spacing-sm);
        }

        .department-card .card-subtitle {
            color: var(--gray-600);
            font-size: var(--font-size-sm);
            margin-bottom: var(--spacing-md);
        }

        /* Program Badges */
        .program-badge {
            border-radius: 20px;
            padding: var(--spacing-xs) var(--spacing-md);
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-medium);
            margin: var(--spacing-xs) calc(var(--spacing-xs) + 0.25rem) var(--spacing-xs) 0;
            display: inline-block;
            transition: var(--transition);
            color: var(--primary-color);
            background: var(--primary-light);
            border: 1px solid rgba(0, 102, 204, 0.2);
            position: relative;
            overflow: hidden;
        }

        .program-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: var(--transition);
        }

        .program-badge:hover::before {
            left: 100%;
        }

        .program-badge:hover {
            transform: scale(1.05) translateY(-1px);
            box-shadow: var(--shadow-md);
            background: var(--primary-color);
            color: white;
        }

        .program-badge.success {
            color: var(--success-dark);
            background: var(--success-light);
            border-color: rgba(40, 167, 69, 0.2);
        }

        .program-badge.success:hover {
            background: var(--success-color);
            color: white;
        }

        .program-badge.warning {
            color: var(--warning-dark);
            background: var(--warning-light);
            border-color: rgba(255, 193, 7, 0.2);
        }

        .program-badge.warning:hover {
            background: var(--warning-color);
            color: var(--gray-800);
        }

        /* Status Colors */
        .bg-success { background: var(--success-color) !important; }
        .bg-warning { background: var(--warning-color) !important; }
        .bg-info { background: var(--info-color) !important; }
        .bg-danger { background: var(--danger-color) !important; }
        .bg-light { background: var(--gray-50) !important; }
        .bg-dark { background: var(--gray-800) !important; }

        .text-success { color: var(--success-color) !important; }
        .text-warning { color: var(--warning-color) !important; }
        .text-info { color: var(--info-color) !important; }
        .text-danger { color: var(--danger-color) !important; }
        .text-muted { color: var(--gray-500) !important; }

        /* Statistics Cards - Matching admin/index.php */
        .statistics-container .card {
            text-align: center;
            border: none;
            background: white;
            position: relative;
            overflow: hidden;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .statistics-container .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .statistics-container .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .statistics-container .card-body {
            padding: 0;
            position: relative;
            z-index: 1;
        }

        .statistics-container i {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
        }

        .statistics-container h4 {
            font-size: 2rem;
            font-weight: 700;
            color: #003366;
            margin-bottom: 5px;
        }

        .statistics-container .card-title {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0;
        }

        /* Individual Statistics Card Colors - Using gradients from admin/index.php */
        .statistics-container .card.border-primary i {
            background: var(--primary-gradient);
        }

        .statistics-container .card.border-success i {
            background: var(--success-gradient);
        }

        .statistics-container .card.border-info i {
            background: var(--info-gradient);
        }

        .statistics-container .card.border-warning i {
            background: var(--warning-gradient);
        }

        /* Navigation Tabs */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
            transition: var(--transition);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            background: var(--primary-light);
        }

        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background: #fff;
            border-bottom: 3px solid var(--primary-color);
            font-weight: 600;
        }

        /* Tables - Matching admin/index.php */
        .table {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            background: white;
        }

        .table th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            padding: 15px;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
        }

        .table th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.3);
        }

        .table td {
            padding: 15px;
            vertical-align: middle;
            border-color: #e9ecef;
            font-size: 0.9rem;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 102, 204, 0.02);
        }

        .table-striped tbody tr:nth-of-type(even) {
            background-color: white;
        }

        .table-hover tbody tr {
            transition: all 0.3s ease;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 102, 204, 0.05);
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .table-responsive {
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        /* Badges */
        .badge {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.35em 0.65em;
        }

        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: var(--success-light);
            color: #155724;
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: var(--danger-light);
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background: var(--warning-light);
            color: #856404;
            border-left: 4px solid var(--warning-color);
        }

        /* Modals - Matching admin/index.php */
        .modal-content {
            border-radius: 16px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            background: white;
            position: relative;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #003366;
        }

        .modal-body {
            padding: 25px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 25px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .btn-close {
            font-size: 1.2rem;
            opacity: 0.6;
            transition: all 0.3s ease;
        }

        .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }

        /* Loading Overlay - Matching admin/index.php */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0066cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Loading States */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid var(--gray-300);
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Pulse Animation */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Dropdowns */
        .dropdown-menu {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-hover);
            padding: 0.5rem;
        }

        .dropdown-item {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            transition: var(--transition);
        }

        .dropdown-item:hover {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        /* Search and Filters */
        .search-container {
            position: relative;
        }

        .search-container .form-control {
            border-radius: var(--border-radius) 0 0 var(--border-radius);
        }

        .search-container .btn {
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }

        /* Action Buttons */
        .action-buttons .btn {
            margin: 0.125rem;
            transition: var(--transition);
        }

        .action-buttons .btn:hover {
            transform: translateY(-1px);
        }

        /* Status Indicators */
        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-active { background: var(--success-color); }
        .status-inactive { background: var(--warning-color); }

        /* Print Styles */
        @media print {
            .sidebar,
            .btn,
            .dropdown,
            .modal,
            .loading-overlay,
            .alert,
            .nav-tabs,
            .action-buttons {
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

            .card-header {
                background: #f8f9fa !important;
                color: #000 !important;
                border-bottom: 1px solid #000 !important;
            }

            .text-primary {
                color: #000 !important;
            }
        }

        /* Mobile Responsiveness - Matching admin/index.php */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 20px;
                margin-bottom: 30px;
            }

            .action-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .card {
                padding: 20px;
                margin-bottom: 20px;
            }

            .table-responsive {
                font-size: 0.875rem;
            }

            .department-card {
                margin-bottom: 20px;
            }

            .statistics-container {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }

            .statistics-container .col-md-3 {
                margin-bottom: 0;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 15px;
            }

            .card {
                padding: 15px;
            }

            .statistics-container {
                grid-template-columns: 1fr;
            }

            .modal-dialog {
                margin: 10px;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            :root {
                --gray-50: #1a1a1a;
                --gray-100: #2d2d2d;
                --gray-200: #404040;
                --gray-300: #525252;
                --gray-400: #737373;
                --gray-500: #a3a3a3;
                --gray-600: #d4d4d4;
                --gray-700: #e5e5e5;
                --gray-800: #f5f5f5;
                --gray-900: #ffffff;
            }

            body {
                background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
                color: var(--gray-700);
            }

            .card {
                background: rgba(30, 30, 30, 0.95);
                color: var(--gray-700);
            }

            .form-control {
                background: rgba(45, 45, 45, 0.9);
                border-color: var(--gray-300);
                color: var(--gray-700);
            }

            .form-control:focus {
                background: rgba(30, 30, 30, 0.95);
                color: var(--gray-700);
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Focus States & Accessibility */
        .btn:focus,
        .form-control:focus,
        .form-select:focus,
        .nav-link:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.25);
            border-color: var(--primary-color);
        }

        .btn:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Skip to main content link for screen readers */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--primary-color);
            color: white;
            padding: 8px;
            text-decoration: none;
            border-radius: var(--border-radius);
            z-index: 10000;
        }

        .skip-link:focus {
            top: 6px;
        }

        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .card {
                border: 2px solid var(--gray-400);
            }

            .btn {
                border: 2px solid currentColor;
            }

            .form-control {
                border: 2px solid var(--gray-400);
            }
        }

        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Animation Classes */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .slide-in {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }

        /* Utility Classes */
        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .shadow-sm { box-shadow: var(--shadow-sm) !important; }
        .shadow-md { box-shadow: var(--shadow-md) !important; }
        .shadow-lg { box-shadow: var(--shadow-lg) !important; }

        .border-left-primary { border-left: 4px solid var(--primary-color) !important; }
        .border-left-success { border-left: 4px solid var(--success-color) !important; }
        .border-left-warning { border-left: 4px solid var(--warning-color) !important; }
        .border-left-info { border-left: 4px solid var(--info-color) !important; }

        .rounded-sm { border-radius: var(--border-radius-sm) !important; }
        .rounded { border-radius: var(--border-radius) !important; }
        .rounded-lg { border-radius: var(--border-radius-lg) !important; }

        .d-none { display: none !important; }
        .d-block { display: block !important; }
        .d-flex { display: flex !important; }
        .d-grid { display: grid !important; }

        /* Hover Effects */
        .hover-lift:hover {
            transform: translateY(-2px);
            transition: var(--transition);
        }

        .hover-glow:hover {
            box-shadow: 0 0 20px rgba(0, 102, 204, 0.3);
        }

        /* Text Utilities */
        .text-xs { font-size: var(--font-size-xs) !important; }
        .text-sm { font-size: var(--font-size-sm) !important; }
        .text-base { font-size: var(--font-size-base) !important; }
        .text-lg { font-size: var(--font-size-lg) !important; }

        .font-normal { font-weight: var(--font-weight-normal) !important; }
        .font-medium { font-weight: var(--font-weight-medium) !important; }
        .font-semibold { font-weight: var(--font-weight-semibold) !important; }
        .font-bold { font-weight: var(--font-weight-bold) !important; }

        /* Spacing Utilities */
        .m-0 { margin: 0 !important; }
        .m-1 { margin: var(--spacing-xs) !important; }
        .m-2 { margin: var(--spacing-sm) !important; }
        .m-3 { margin: var(--spacing-md) !important; }
        .m-4 { margin: var(--spacing-lg) !important; }
        .m-5 { margin: var(--spacing-xl) !important; }

        .p-0 { padding: 0 !important; }
        .p-1 { padding: var(--spacing-xs) !important; }
        .p-2 { padding: var(--spacing-sm) !important; }
        .p-3 { padding: var(--spacing-md) !important; }
        .p-4 { padding: var(--spacing-lg) !important; }
        .p-5 { padding: var(--spacing-xl) !important; }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none btn btn-primary position-fixed top-0 start-0 m-3" style="z-index: 10000;" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar position-fixed top-0 start-0 h-100 bg-primary-dark text-white" style="width: var(--sidebar-width); z-index: 1000;">
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
    <div class="main-content" id="mainContent">
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
        <div class="row g-3 mb-4 statistics-container" id="statisticsContainer">
            <!-- Statistics will be loaded here -->
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs mb-4" id="mainTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="departments-tab" data-bs-toggle="tab" data-bs-target="#departments" type="button" role="tab">
                    <i class="fas fa-building me-2"></i>Departments
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="programs-tab" data-bs-toggle="tab" data-bs-target="#programs" type="button" role="tab">
                    <i class="fas fa-graduation-cap me-2"></i>Programs
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="mainTabContent">
            <!-- Departments Tab -->
            <div class="tab-pane fade show active" id="departments" role="tabpanel">
                <!-- Department Form -->
                <div class="card mb-4">
                    <div class="card-header">
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
            </div>

            <!-- Programs Tab -->
            <div class="tab-pane fade" id="programs" role="tabpanel">
                <!-- Programs Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1 text-primary">
                            <i class="fas fa-graduation-cap me-2"></i>All Programs
                        </h1>
                        <p class="text-muted mb-0">Manage all programs across departments</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary" onclick="loadPrograms()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                </div>

                <!-- Programs Table -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>Programs Directory
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="programsTable">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Program Name</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="programsTableBody">
                                    <!-- Programs will be loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
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

            // Tab change handler
            $('#mainTabs button').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr('data-bs-target');
                if (target === '#programs') {
                    loadPrograms();
                } else if (target === '#departments') {
                    loadDepartments();
                    loadStatistics();
                }
            });

            // Mobile sidebar toggle
            window.toggleSidebar = function() {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.getElementById('mainContent');
                sidebar.classList.toggle('d-none');
                mainContent.classList.toggle('expanded');
            };
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
                        <span class="text-muted">No departments found.</span>
                    </div>
                `);
                return;
            }
            
            let html = '';
            departments.forEach(dept => {
                let programsHtml = '';
                if (Array.isArray(dept.programs) && dept.programs.length > 0) {
                    programsHtml = dept.programs.map(prog => {
                        const statusBadge = prog.status === 'active' ? 'success' : 'warning';
                        return `
                            <span class="program-badge d-inline-block me-2 mb-2 bg-${statusBadge}">
                                ${prog.name}
                            </span>
                        `;
                    }).join('');
                } else {
                    programsHtml = '<span class="text-muted">No programs</span>';
                }
                
                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="card department-card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="card-title mb-0">${dept.dept_name}</h5>
                                    <input type="checkbox" class="form-check-input department-checkbox" value="${dept.dept_id}">
                                </div>
                                <div class="mb-2">
                                    <strong>Head of Department:</strong> 
                                    <span class="${dept.hod_name === 'Not Assigned' ? 'text-danger' : 'text-success'}">
                                        ${dept.hod_name}
                                    </span>
                                </div>
                                <div class="mb-3">
                                    <strong>Programs (${dept.programs ? dept.programs.length : 0}):</strong> 
                                    <div class="mt-1">${programsHtml}</div>
                                </div>
                                <div class="d-flex gap-1 flex-wrap">
                                    <button class="btn btn-sm btn-outline-primary edit-department" 
                                            data-dept-id="${dept.dept_id}" 
                                            data-dept-name="${dept.dept_name}" 
                                            data-hod-id="${dept.hod_id || ''}">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger delete-department" 
                                            data-dept-id="${dept.dept_id}" 
                                            data-dept-name="${dept.dept_name}">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                    <div class="input-group input-group-sm" style="width: auto;">
                                        <input type="text" class="form-control add-program-input" placeholder="New program">
                                        <button class="btn btn-outline-success add-program-btn" data-dept-id="${dept.dept_id}">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
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
                    showAlert('success', 'Program added successfully');
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

        function filterDepartments() {
            const searchTerm = $('#searchInput').val().toLowerCase();
            
            if (!searchTerm) {
                renderDepartments(allDepartments);
                return;
            }
            
            const filtered = allDepartments.filter(dept => 
                dept.dept_name.toLowerCase().includes(searchTerm) ||
                dept.hod_name.toLowerCase().includes(searchTerm) ||
                (dept.programs && dept.programs.some(prog => 
                    prog.name.toLowerCase().includes(searchTerm)
                ))
            );
            
            renderDepartments(filtered);
        }

        function loadStatistics() {
            $.get('?ajax=1&action=get_statistics')
                .done(function(data) {
                    if (data && data.status === 'success') {
                        renderStatistics(data);
                    } else {
                        console.error('Invalid statistics response:', data);
                        renderStatistics({
                            total_departments: 0,
                            assigned_hods: 0,
                            total_programs: 0,
                            avg_programs_per_dept: 0
                        });
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Failed to load statistics:', error);
                    renderStatistics({
                        total_departments: 0,
                        assigned_hods: 0,
                        total_programs: 0,
                        avg_programs_per_dept: 0
                    });
                });
        }

        function renderStatistics(stats) {
            const container = $('#statisticsContainer');
            const html = `
                <div class="col-md-3">
                    <div class="card text-center border-primary">
                        <div class="card-body">
                            <i class="fas fa-building fa-2x text-primary mb-2"></i>
                            <h4 class="text-primary">${stats.total_departments || 0}</h4>
                            <p class="text-muted mb-0">Total Departments</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-success">
                        <div class="card-body">
                            <i class="fas fa-user-tie fa-2x text-success mb-2"></i>
                            <h4 class="text-success">${stats.assigned_hods || 0}</h4>
                            <p class="text-muted mb-0">Assigned HoDs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-info">
                        <div class="card-body">
                            <i class="fas fa-graduation-cap fa-2x text-info mb-2"></i>
                            <h4 class="text-info">${stats.total_programs || 0}</h4>
                            <p class="text-muted mb-0">Total Programs</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-warning">
                        <div class="card-body">
                            <i class="fas fa-chart-line fa-2x text-warning mb-2"></i>
                            <h4 class="text-warning">${stats.avg_programs_per_dept || 0}</h4>
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
                        const aPrograms = a.programs ? a.programs.length : 0;
                        const bPrograms = b.programs ? b.programs.length : 0;
                        const comparison = aPrograms - bPrograms;
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
            const totalPrograms = departments.reduce((sum, dept) => sum + (dept.programs ? dept.programs.length : 0), 0);
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

        function loadPrograms() {
            showLoading();

            $.get('?ajax=1&action=list_options')
                .done(function(data) {
                    if (data && typeof data === 'object' && data.status === 'error') {
                        console.error('Server error loading programs:', data.message);
                        showAlert('danger', data.message || 'Failed to load programs.');
                        renderPrograms([]);
                    } else {
                        renderPrograms(data);
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Error loading programs:', error);
                    showAlert('danger', 'Failed to load programs. Please try again.');
                    renderPrograms([]);
                })
                .always(function() {
                    hideLoading();
                });
        }

        function renderPrograms(programs) {
            const tbody = $('#programsTableBody');

            if (!Array.isArray(programs)) {
                console.error('Invalid programs data:', programs);
                tbody.html(`
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <span class="text-danger">Error loading programs. Please try again.</span>
                        </td>
                    </tr>
                `);
                return;
            }

            if (programs.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <span class="text-muted">No programs found.</span>
                        </td>
                    </tr>
                `);
                return;
            }

            let html = '';
            programs.forEach(prog => {
                const statusBadge = prog.status === 'active' ? 'success' : 'warning';
                const statusText = prog.status === 'active' ? 'Active' : 'Inactive';
                const createdDate = new Date(prog.created_at).toLocaleDateString();

                html += `
                    <tr>
                        <td>${prog.id}</td>
                        <td>
                            <span class="fw-bold">${prog.name}</span>
                        </td>
                        <td>${prog.department_name || 'No Department'}</td>
                        <td>
                            <span class="badge bg-${statusBadge}" id="status-${prog.id}">${statusText}</span>
                        </td>
                        <td>${createdDate}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary edit-program-btn" data-program-id="${prog.id}" data-program-name="${prog.name}" data-status="${prog.status}" title="Edit Program">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-${statusBadge} toggle-status-btn" data-program-id="${prog.id}" data-current-status="${prog.status}" title="Toggle Status">
                                    <i class="fas fa-toggle-${prog.status === 'active' ? 'on' : 'off'}"></i>
                                </button>
                                <button class="btn btn-outline-danger delete-program-btn" data-program-id="${prog.id}" data-program-name="${prog.name}" title="Delete Program">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            tbody.html(html);
            attachProgramEventHandlers();
        }

        function attachProgramEventHandlers() {
            // Edit program
            $('.edit-program-btn').on('click', function() {
                const progId = $(this).data('program-id');
                const progName = $(this).data('program-name');
                const status = $(this).data('status');

                showEditProgramModal(progId, progName, status);
            });

            // Toggle status
            $('.toggle-status-btn').on('click', function() {
                const progId = $(this).data('program-id');
                const currentStatus = $(this).data('current-status');
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

                updateProgramStatus(progId, newStatus);
            });

            // Delete program
            $('.delete-program-btn').on('click', function() {
                const progId = $(this).data('program-id');
                const progName = $(this).data('program-name');

                if (confirm(`Are you sure you want to delete "${progName}"?`)) {
                    deleteProgramGlobal(progId);
                }
            });
        }

        function showEditProgramModal(progId, progName, status) {
            // Remove existing modal if present
            $('#editProgramModal').remove();

            const modalHtml = `
                <div class="modal fade" id="editProgramModal" tabindex="-1" aria-labelledby="editProgramModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="editProgramModalLabel">Edit Program</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form id="editProgramForm">
                                    <input type="hidden" id="editProgramId" value="${progId}">
                                    <div class="mb-3">
                                        <label for="editProgramName" class="form-label">Program Name *</label>
                                        <input type="text" class="form-control" id="editProgramName" value="${progName}" required maxlength="100">
                                        <div class="invalid-feedback">Program name is required and must be less than 100 characters.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="editProgramStatus" class="form-label">Status</label>
                                        <select class="form-select" id="editProgramStatus">
                                            <option value="active" ${status === 'active' ? 'selected' : ''}>Active</option>
                                            <option value="inactive" ${status === 'inactive' ? 'selected' : ''}>Inactive</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveProgramEdit()">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('editProgramModal'));

            document.getElementById('editProgramModal').addEventListener('hidden.bs.modal', function () {
                $('#editProgramModal').remove();
            });

            modal.show();
        }

        function saveProgramEdit() {
            const progId = $('#editProgramId').val();
            const progName = $('#editProgramName').val().trim();
            const status = $('#editProgramStatus').val();

            if (!progName) {
                $('#editProgramName').addClass('is-invalid');
                showAlert('warning', 'Program name is required');
                return;
            }

            if (progName.length > 100) {
                $('#editProgramName').addClass('is-invalid');
                showAlert('warning', 'Program name must be less than 100 characters');
                return;
            }

            $('#editProgramName').removeClass('is-invalid');
            showLoading();

            $.post('?ajax=1&action=edit_program', {
                program_id: progId,
                program_name: progName,
                status: status
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    bootstrap.Modal.getInstance(document.getElementById('editProgramModal')).hide();
                    loadPrograms();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Error updating program:', error);
                showAlert('danger', 'Failed to update program. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function updateProgramStatus(progId, newStatus) {
            showLoading();

            $.post('?ajax=1&action=update_program_status', {
                program_id: progId,
                status: newStatus
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    loadPrograms();
                } else {
                    showAlert('danger', data.message);
                }
            })
            .fail(function() {
                showAlert('danger', 'Failed to update program status. Please try again.');
            })
            .always(function() {
                hideLoading();
            });
        }

        function deleteProgramGlobal(progId) {
            showLoading();

            $.post('?ajax=1&action=delete_program', {
                program_id: progId
            })
            .done(function(data) {
                if (data.status === 'success') {
                    showAlert('success', data.message);
                    loadPrograms();
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
    </script>
</body>
</html>