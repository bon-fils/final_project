<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_stats':
            try {
                // Get real statistics from database
                $stmt = $pdo->query("
                    SELECT
                        (SELECT COUNT(*) FROM students) AS total_students,
                        (SELECT COUNT(*) FROM attendance_sessions) AS total_sessions,
                        (SELECT COUNT(*) FROM leave_requests WHERE status='pending') AS pending_leaves,
                        (SELECT
                            CASE
                                WHEN (SELECT COUNT(*) FROM attendance_records) > 0
                                THEN ROUND(((SELECT COUNT(*) FROM attendance_records WHERE status='present') * 100.0) / (SELECT COUNT(*) FROM attendance_records), 1)
                                ELSE 0
                            END
                        ) AS avg_attendance
                ");
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode([
                    'total_students' => (int)($stats['total_students'] ?? 0),
                    'total_sessions' => (int)($stats['total_sessions'] ?? 0),
                    'avg_attendance' => (float)($stats['avg_attendance'] ?? 0),
                    'pending_leaves' => (int)($stats['pending_leaves'] ?? 0)
                ]);
            } catch (PDOException $e) {
                error_log("Reports stats error: " . $e->getMessage());
                echo json_encode([
                    'total_students' => 0,
                    'total_sessions' => 0,
                    'avg_attendance' => 0,
                    'pending_leaves' => 0
                ]);
            }
            exit;

        case 'get_departments':
            try {
                $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($departments);
            } catch (PDOException $e) {
                error_log("Get departments error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_options':
            try {
                $deptId = $_GET['department_id'] ?? 0;
                if ($deptId) {
                    $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY name");
                    $stmt->execute([$deptId]);
                } else {
                    $stmt = $pdo->query("SELECT id, name FROM options ORDER BY name");
                }
                $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($options);
            } catch (PDOException $e) {
                error_log("Get options error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_courses':
            try {
                $deptId = $_GET['department_id'] ?? 0;
                $optionId = $_GET['option_id'] ?? 0;

                if ($deptId && $optionId) {
                    // Get courses for specific department and option
                    $stmt = $pdo->prepare("SELECT id, name, course_code FROM courses WHERE department_id = ? ORDER BY name");
                    $stmt->execute([$deptId]);
                } elseif ($deptId) {
                    // Get all courses for the department
                    $stmt = $pdo->prepare("SELECT id, name, course_code FROM courses WHERE department_id = ? ORDER BY name");
                    $stmt->execute([$deptId]);
                } else {
                    // Get all courses
                    $stmt = $pdo->query("SELECT id, name, course_code FROM courses ORDER BY name");
                }

                $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($courses);
            } catch (PDOException $e) {
                error_log("Get courses error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'get_reports':
            try {
                $where = [];
                $params = [];

                // Build filters
                if (!empty($_GET['department_id'])) {
                    $where[] = "d.id = ?";
                    $params[] = $_GET['department_id'];
                }

                if (!empty($_GET['option_id'])) {
                    $where[] = "s.option_id = ?";
                    $params[] = $_GET['option_id'];
                }

                if (!empty($_GET['course_id'])) {
                    $where[] = "c.id = ?";
                    $params[] = $_GET['course_id'];
                }

                if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                    $where[] = "DATE(ar.recorded_at) BETWEEN ? AND ?";
                    $params[] = $_GET['date_from'];
                    $params[] = $_GET['date_to'];
                }

                $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

                $stmt = $pdo->prepare("
                    SELECT
                        ar.id,
                        s.first_name,
                        s.last_name,
                        d.name as department_name,
                        o.name as option_name,
                        c.name as course_name,
                        c.course_code,
                        DATE(ar.recorded_at) as attendance_date,
                        ar.status,
                        ar.recorded_at
                    FROM attendance_records ar
                    INNER JOIN students s ON ar.student_id = s.id
                    INNER JOIN options o ON s.option_id = o.id
                    INNER JOIN departments d ON s.department_id = d.id
                    LEFT JOIN courses c ON ar.course_id = c.id
                    {$whereClause}
                    ORDER BY ar.recorded_at DESC
                    LIMIT 1000
                ");

                $stmt->execute($params);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode($reports);
            } catch (PDOException $e) {
                error_log("Get reports error: " . $e->getMessage());
                echo json_encode([]);
            }
            exit;

        case 'export_reports':
            try {
                $where = [];
                $params = [];

                // Build filters same as get_reports
                if (!empty($_GET['department_id'])) {
                    $where[] = "d.id = ?";
                    $params[] = $_GET['department_id'];
                }

                if (!empty($_GET['option_id'])) {
                    $where[] = "s.option_id = ?";
                    $params[] = $_GET['option_id'];
                }

                if (!empty($_GET['course_id'])) {
                    $where[] = "c.id = ?";
                    $params[] = $_GET['course_id'];
                }

                if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                    $where[] = "DATE(ar.recorded_at) BETWEEN ? AND ?";
                    $params[] = $_GET['date_from'];
                    $params[] = $_GET['date_to'];
                }

                $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

                $stmt = $pdo->prepare("
                    SELECT
                        ar.id,
                        s.first_name,
                        s.last_name,
                        d.name as department_name,
                        o.name as option_name,
                        c.name as course_name,
                        c.course_code,
                        DATE(ar.recorded_at) as attendance_date,
                        ar.status,
                        ar.recorded_at
                    FROM attendance_records ar
                    INNER JOIN students s ON ar.student_id = s.id
                    INNER JOIN options o ON s.option_id = o.id
                    INNER JOIN departments d ON s.department_id = d.id
                    LEFT JOIN courses c ON ar.course_id = c.id
                    {$whereClause}
                    ORDER BY ar.recorded_at DESC
                ");

                $stmt->execute($params);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $format = $_GET['export'] ?? 'excel';

                if ($format === 'excel') {
                    exportToExcel($reports);
                } elseif ($format === 'pdf') {
                    exportToPDF($reports);
                }

            } catch (PDOException $e) {
                error_log("Export reports error: " . $e->getMessage());
                die("Export failed: " . $e->getMessage());
            }
            exit;

        case 'get_analytics':
            try {
                $analytics = [];

                // Department-wise attendance
                $stmt = $pdo->prepare("
                    SELECT
                        d.name as department,
                        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
                        COUNT(*) as total_count,
                        ROUND(
                            CASE
                                WHEN COUNT(*) > 0
                                THEN (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0) / COUNT(*)
                                ELSE 0
                            END, 1
                        ) as attendance_rate
                    FROM departments d
                    LEFT JOIN students s ON d.id = s.department_id
                    LEFT JOIN attendance_records ar ON s.id = ar.student_id
                    GROUP BY d.id, d.name
                    ORDER BY attendance_rate DESC
                ");
                $stmt->execute();
                $analytics['department_attendance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Daily attendance trends (most recent 30 days with data)
                $stmt = $pdo->prepare("
                    SELECT
                        DATE(ar.recorded_at) as date,
                        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
                        COUNT(*) as total_count
                    FROM attendance_records ar
                    GROUP BY DATE(ar.recorded_at)
                    ORDER BY date DESC
                    LIMIT 30
                ");
                $stmt->execute();
                $analytics['daily_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Course performance
                $stmt = $pdo->prepare("
                    SELECT
                        c.name as course_name,
                        c.course_code,
                        COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
                        COUNT(*) as total_count,
                        ROUND(
                            CASE
                                WHEN COUNT(*) > 0
                                THEN (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0) / COUNT(*)
                                ELSE 0
                            END, 1
                        ) as attendance_rate
                    FROM courses c
                    LEFT JOIN attendance_sessions s ON c.id = s.course_id
                    LEFT JOIN attendance_records ar ON s.id = ar.session_id
                    GROUP BY c.id, c.name, c.course_code
                    HAVING total_count > 0
                    ORDER BY attendance_rate DESC
                    LIMIT 10
                ");
                $stmt->execute();
                $analytics['course_performance'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // HOD Reports
                $stmt = $pdo->prepare("
                    SELECT
                        d.name as department_name,
                        CONCAT(l.first_name, ' ', l.last_name) as hod_name,
                        COUNT(DISTINCT c.id) as courses_count,
                        COUNT(DISTINCT s.id) as students_count,
                        COUNT(DISTINCT ar.id) as attendance_records
                    FROM departments d
                    LEFT JOIN lecturers l ON d.hod_id = l.id
                    LEFT JOIN courses c ON d.id = c.department_id
                    LEFT JOIN students s ON d.id = s.department_id
                    LEFT JOIN attendance_sessions sess ON c.id = sess.course_id
                    LEFT JOIN attendance_records ar ON sess.id = ar.session_id
                    GROUP BY d.id, d.name, l.first_name, l.last_name
                    ORDER BY d.name
                ");
                $stmt->execute();
                $analytics['hod_reports'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Ensure we have valid data structure
                $analytics = array_merge([
                    'department_attendance' => [],
                    'daily_trends' => [],
                    'course_performance' => [],
                    'hod_reports' => []
                ], $analytics);

                echo json_encode($analytics);
            } catch (PDOException $e) {
                error_log("Get analytics error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'error' => 'Database error occurred while loading analytics',
                    'details' => $e->getMessage(),
                    'department_attendance' => [],
                    'daily_trends' => [],
                    'course_performance' => [],
                    'hod_reports' => []
                ]);
            } catch (Exception $e) {
                error_log("General analytics error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'error' => 'Failed to load analytics: ' . $e->getMessage(),
                    'department_attendance' => [],
                    'daily_trends' => [],
                    'course_performance' => [],
                    'hod_reports' => []
                ]);
            }
            exit;

        case 'get_lecturer_reports':
            try {
                $stmt = $pdo->prepare("
                    SELECT
                        CONCAT(l.first_name, ' ', l.last_name) as lecturer_name,
                        l.email,
                        d.name as department_name,
                        COUNT(DISTINCT c.id) as courses_count,
                        COUNT(DISTINCT ar.id) as attendance_records,
                        COUNT(DISTINCT ar.student_id) as unique_students,
                        ROUND(
                            CASE
                                WHEN COUNT(DISTINCT ar.student_id) > 0
                                THEN (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0) / COUNT(*)
                                ELSE 0
                            END, 1
                        ) as avg_attendance_rate
                    FROM lecturers l
                    LEFT JOIN departments d ON l.department_id = d.id
                    LEFT JOIN courses c ON l.id = c.lecturer_id
                    LEFT JOIN attendance_sessions sess ON c.id = sess.course_id
                    LEFT JOIN attendance_records ar ON sess.id = ar.session_id
                    WHERE l.role IN ('lecturer', 'hod')
                    GROUP BY l.id, l.first_name, l.last_name, l.email, d.name
                    ORDER BY avg_attendance_rate DESC
                ");
                $stmt->execute();
                $lecturer_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'status' => 'success',
                    'data' => $lecturer_reports
                ]);
            } catch (PDOException $e) {
                error_log("Get lecturer reports error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to load lecturer reports']);
            }
            exit;

        case 'get_paginated_reports':
            try {
                $page = max(1, (int)($_GET['page'] ?? 1));
                $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
                $offset = ($page - 1) * $limit;

                $where = [];
                $params = [];

                // Build filters
                if (!empty($_GET['department_id'])) {
                    $where[] = "d.id = ?";
                    $params[] = $_GET['department_id'];
                }

                if (!empty($_GET['option_id'])) {
                    $where[] = "s.option_id = ?";
                    $params[] = $_GET['option_id'];
                }

                if (!empty($_GET['course_id'])) {
                    $where[] = "c.id = ?";
                    $params[] = $_GET['course_id'];
                }

                if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
                    $where[] = "DATE(ar.recorded_at) BETWEEN ? AND ?";
                    $params[] = $_GET['date_from'];
                    $params[] = $_GET['date_to'];
                }

                $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

                // Get total count
                $countStmt = $pdo->prepare("
                    SELECT COUNT(*) as total
                    FROM attendance_records ar
                    INNER JOIN students s ON ar.student_id = s.id
                    INNER JOIN options o ON s.option_id = o.id
                    INNER JOIN departments d ON s.department_id = d.id
                    LEFT JOIN courses c ON ar.course_id = c.id
                    {$whereClause}
                ");
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();

                // Get paginated data
                $stmt = $pdo->prepare("
                    SELECT
                        ar.id,
                        s.first_name,
                        s.last_name,
                        d.name as department_name,
                        o.name as option_name,
                        c.name as course_name,
                        c.course_code,
                        DATE(ar.recorded_at) as attendance_date,
                        ar.status,
                        ar.recorded_at
                    FROM attendance_records ar
                    INNER JOIN students s ON ar.student_id = s.id
                    INNER JOIN options o ON s.option_id = o.id
                    INNER JOIN departments d ON s.department_id = d.id
                    LEFT JOIN courses c ON ar.course_id = c.id
                    {$whereClause}
                    ORDER BY ar.recorded_at DESC
                    LIMIT ? OFFSET ?
                ");

                $params[] = $limit;
                $params[] = $offset;
                $stmt->execute($params);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'data' => $reports,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            } catch (PDOException $e) {
                error_log("Get paginated reports error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to load reports']);
            }
            exit;
    }
}

// Export functions
function exportToExcel($reports) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    echo "Student Name\tDepartment\tOption\tCourse\tCourse Code\tDate\tStatus\n";

    foreach ($reports as $report) {
        $studentName = $report['first_name'] . ' ' . $report['last_name'];
        $courseCode = $report['course_code'] ?? 'N/A';
        echo "{$studentName}\t{$report['department_name']}\t{$report['option_name']}\t{$report['course_name']}\t{$courseCode}\t{$report['attendance_date']}\t{$report['status']}\n";
    }
    exit;
}

function exportToPDF($reports) {
    // Simple HTML to PDF conversion (you might want to use a proper PDF library like TCPDF or FPDF)
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.pdf"');

    echo "<html><head><title>Attendance Report</title></head><body>";
    echo "<h1>Attendance Report - " . date('Y-m-d') . "</h1>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Student Name</th><th>Department</th><th>Option</th><th>Course</th><th>Course Code</th><th>Date</th><th>Status</th></tr>";

    foreach ($reports as $report) {
        $studentName = $report['first_name'] . ' ' . $report['last_name'];
        $courseCode = $report['course_code'] ?? 'N/A';
        echo "<tr>";
        echo "<td>{$studentName}</td>";
        echo "<td>{$report['department_name']}</td>";
        echo "<td>{$report['option_name']}</td>";
        echo "<td>{$report['course_name']}</td>";
        echo "<td>{$courseCode}</td>";
        echo "<td>{$report['attendance_date']}</td>";
        echo "<td>{$report['status']}</td>";
        echo "</tr>";
    }

    echo "</table></body></html>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Reports | RP Attendance System</title>

  <!-- Bootstrap & Font-Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

  <style>
    body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(to right, #0066cc, #003366); margin: 0; }
    .sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100vh; background:#003366; color:#fff; padding-top:20px;}
    .sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none;}
    .sidebar a:hover, .sidebar a.active { background:#0066cc;}
    .topbar   { margin-left:250px; background:#fff; padding:10px 30px; border-bottom:1px solid #ddd;}
    .main-content{ margin-left:250px; padding:30px;}
    .card   { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,.05);}
    .footer { text-align:center; margin-left:250px; padding:15px; font-size:.9rem; color:#666; background:#f0f0f0;}
    @media (max-width:768px){
        .sidebar,.topbar,.main-content,.footer{margin-left:0;width:100%;} .sidebar{display:none;}
        .stats-row { flex-direction: column; gap: 15px; }
        .filter-row { flex-direction: column; gap: 15px; }
        .export-buttons { justify-content: center; }
    }

    /* Enhanced card styling */
    .stats-card {
        background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
        border-radius: 15px;
        padding: 25px;
        color: white;
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 35px rgba(0,0,0,0.2);
    }

    .stats-card .card-icon {
        font-size: 3rem;
        opacity: 0.9;
        margin-bottom: 15px;
    }

    .stats-card h6 {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-bottom: 10px;
        font-weight: 500;
    }

    .stats-card h4 {
        font-size: 2.2rem;
        font-weight: 700;
        margin: 0;
    }

    .stats-card .subtitle {
        font-size: 0.8rem;
        opacity: 0.8;
        margin-top: 5px;
    }

    /* Filter enhancements */
    .filter-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        border: none;
    }

    .filter-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
        border-radius: 12px 12px 0 0 !important;
    }

    .form-select:focus, .form-control:focus {
        border-color: #0066cc;
        box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    }

    /* Table enhancements */
    .table-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .table-card .card-header {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #dee2e6;
        font-weight: 600;
    }

    .table th {
        background: #f8f9fa;
        border-top: none;
        font-weight: 600;
        color: #495057;
        padding: 12px;
    }

    .table td {
        padding: 12px;
        vertical-align: middle;
    }

    .badge {
        font-size: 0.75rem;
        padding: 6px 10px;
        border-radius: 6px;
    }

    /* Loading states */
    .loading-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255,255,255,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        z-index: 10;
    }

    /* Button enhancements */
    .btn-primary {
        background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
        border: none;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #004080 0%, #002b50 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(0, 102, 204, 0.4);
    }

    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        border: none;
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
        border: none;
    }

    /* Enhanced mobile responsiveness */
    @media (max-width: 768px) {
        .main-content {
            padding: 15px;
        }

        .stats-card {
            padding: 20px 15px;
            margin-bottom: 15px;
        }

        .stats-card h4 {
            font-size: 1.8rem;
        }

        .stats-card .card-icon {
            font-size: 2.5rem;
        }

        .chart-container {
            height: 250px !important;
        }

        .nav-tabs .nav-link {
            font-size: 0.9rem;
            padding: 8px 12px;
        }

        .table th, .table td {
            padding: 8px 4px;
            font-size: 0.85rem;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.8rem;
        }

        .filter-card .card-body {
            padding: 15px;
        }

        .export-buttons {
            flex-direction: column;
            gap: 8px;
        }

        .d-flex.gap-2 {
            flex-wrap: wrap;
            gap: 8px !important;
        }
    }

    @media (max-width: 576px) {
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .stats-card {
            padding: 15px 10px;
        }

        .stats-card h4 {
            font-size: 1.5rem;
        }

        .stats-card h6 {
            font-size: 0.8rem;
        }

        .chart-container {
            height: 200px !important;
        }

        .table-responsive {
            font-size: 0.8rem;
        }

        .pagination {
            font-size: 0.8rem;
        }

        .pagination .page-link {
            padding: 6px 10px;
        }
    }

    /* Chart containers */
    .chart-container {
        position: relative;
        height: 300px;
        width: 100%;
    }

    /* Enhanced loading states */
    .loading-shimmer {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
    }

    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }

    /* Enhanced table styling */
    .table-enhanced {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        border-radius: 8px;
        overflow: hidden;
    }

    .table-enhanced thead th {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: none;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
    }

    /* Status badges */
    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-present {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-absent {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Enhanced filter styling */
    .filter-enhanced {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    /* Performance indicators */
    .performance-excellent { color: #28a745; }
    .performance-good { color: #ffc107; }
    .performance-poor { color: #dc3545; }

    /* Smooth transitions */
    * {
        transition: all 0.3s ease;
    }

    /* Enhanced button hover effects */
    .btn-enhanced:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    /* Progress bars */
    .progress-enhanced {
        height: 8px;
        border-radius: 4px;
        background: #e9ecef;
        overflow: hidden;
    }

    .progress-enhanced .progress-bar {
        border-radius: 4px;
        transition: width 0.6s ease;
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4>👩‍💼 Admin</h4>
      <hr style="border-color:#ffffff66;">
    </div>
    <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
    <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
    <a href="admin-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Reports</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Attendance Reports</h5>
    <span>Admin Panel</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">

    <!-- Stats -->
    <div class="row mb-4 g-3 stats-row">
      <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stats-card">
          <div class="text-center">
            <i class="fas fa-users card-icon"></i>
            <h6>Total Students</h6>
            <h4 id="totalStudents">--</h4>
            <div class="subtitle">Registered students</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stats-card">
          <div class="text-center">
            <i class="fas fa-calendar-check card-icon"></i>
            <h6>Total Sessions</h6>
            <h4 id="totalSessions">--</h4>
            <div class="subtitle">Attendance sessions</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stats-card">
          <div class="text-center">
            <i class="fas fa-percent card-icon"></i>
            <h6>Average Attendance</h6>
            <h4 id="avgAttendance">--%</h4>
            <div class="subtitle">Overall attendance rate</div>
          </div>
        </div>
      </div>
      <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="stats-card">
          <div class="text-center">
            <i class="fas fa-clock card-icon"></i>
            <h6>Pending Leaves</h6>
            <h4 id="pendingLeave">--</h4>
            <div class="subtitle">Awaiting approval</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Analytics Section -->
    <div class="row mb-4 g-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Analytics Dashboard</h6>
            <div class="btn-group" role="group">
              <input type="radio" class="btn-check" name="chartPeriod" id="chart7days" value="7" checked>
              <label class="btn btn-outline-primary btn-sm" for="chart7days">7 Days</label>
              <input type="radio" class="btn-check" name="chartPeriod" id="chart30days" value="30">
              <label class="btn btn-outline-primary btn-sm" for="chart30days">30 Days</label>
              <input type="radio" class="btn-check" name="chartPeriod" id="chart90days" value="90">
              <label class="btn btn-outline-primary btn-sm" for="chart90days">90 Days</label>
            </div>
          </div>
          <div class="card-body">
            <div class="row g-4">
              <div class="col-lg-8">
                <div class="chart-container" style="position: relative; height: 300px;">
                  <canvas id="attendanceTrendChart"></canvas>
                </div>
              </div>
              <div class="col-lg-4">
                <div class="chart-container" style="position: relative; height: 300px;">
                  <canvas id="departmentChart"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Report Type Tabs -->
    <div class="row mb-4 g-3">
      <div class="col-12">
        <ul class="nav nav-tabs" id="reportTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance-reports" type="button" role="tab">
              <i class="fas fa-calendar-check me-2"></i>Attendance Reports
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="hod-tab" data-bs-toggle="tab" data-bs-target="#hod-reports" type="button" role="tab">
              <i class="fas fa-user-tie me-2"></i>HOD Reports
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#advanced-analytics" type="button" role="tab">
              <i class="fas fa-chart-bar me-2"></i>Advanced Analytics
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="lecturer-tab" data-bs-toggle="tab" data-bs-target="#lecturer-reports" type="button" role="tab">
              <i class="fas fa-chalkboard-teacher me-2"></i>Lecturer Reports
            </button>
          </li>
        </ul>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="reportTabContent">

      <!-- Attendance Reports Tab -->
      <div class="tab-pane fade show active" id="attendance-reports" role="tabpanel">
        <!-- Filters -->
    <div class="card mb-4">
      <div class="card-header bg-light">
        <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Reports</h6>
      </div>
      <div class="card-body">
        <form id="filterForm" class="row g-3 align-items-end">
          <div class="col-lg-3 col-md-6">
            <label for="departmentFilter" class="form-label fw-semibold">
              <i class="fas fa-building me-1"></i>Department
            </label>
            <select id="departmentFilter" class="form-select">
              <option value="">All Departments</option>
            </select>
          </div>

          <div class="col-lg-3 col-md-6">
            <label for="optionFilter" class="form-label fw-semibold">
              <i class="fas fa-graduation-cap me-1"></i>Option
            </label>
            <select id="optionFilter" class="form-select" disabled>
              <option value="">All Options</option>
            </select>
          </div>

          <div class="col-lg-3 col-md-6">
            <label for="courseFilter" class="form-label fw-semibold">
              <i class="fas fa-book me-1"></i>Course
            </label>
            <select id="courseFilter" class="form-select" disabled>
              <option value="">All Courses</option>
            </select>
          </div>

          <div class="col-lg-3 col-md-6">
            <label for="dateRange" class="form-label fw-semibold">
              <i class="fas fa-calendar-alt me-1"></i>Date Range
            </label>
            <input type="text" id="dateRange" class="form-control" placeholder="Select date range">
          </div>

          <div class="col-12 d-flex justify-content-between align-items-center mt-3">
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter me-2"></i>Apply Filters
              </button>
              <button type="button" class="btn btn-outline-secondary" id="resetFilters">
                <i class="fas fa-undo me-2"></i>Reset
              </button>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-success" id="exportExcel">
                <i class="fas fa-file-excel me-2"></i>Export Excel
              </button>
              <button type="button" class="btn btn-danger" id="exportPdf">
                <i class="fas fa-file-pdf me-2"></i>Export PDF
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Report Table -->
    <div class="table-card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-table me-2"></i>Attendance Reports</h6>
        <div class="d-flex align-items-center gap-2">
          <small class="text-muted">Showing <span id="recordCount">0</span> records</small>
          <div class="spinner-border spinner-border-sm d-none" id="tableSpinner" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>#</th>
                <th><i class="fas fa-user me-1"></i>Student Name</th>
                <th><i class="fas fa-building me-1"></i>Department</th>
                <th><i class="fas fa-graduation-cap me-1"></i>Option</th>
                <th><i class="fas fa-book me-1"></i>Course</th>
                <th><i class="fas fa-calendar me-1"></i>Date</th>
                <th><i class="fas fa-check-circle me-1"></i>Status</th>
                <th><i class="fas fa-fingerprint me-1"></i>Method</th>
              </tr>
            </thead>
            <tbody id="reportTableBody">
              <tr>
                <td colspan="8" class="text-center py-5">
                  <div class="d-flex flex-column align-items-center">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Data Available</h5>
                    <p class="text-muted mb-0">Apply filters to view attendance reports</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- HOD Reports Tab -->
      <div class="tab-pane fade" id="hod-reports" role="tabpanel">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-user-tie me-2"></i>Department HOD Reports</h6>
            <button class="btn btn-outline-primary btn-sm" onclick="exportHODReport()">
              <i class="fas fa-download me-1"></i>Export HOD Report
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Department</th>
                    <th>HOD Name</th>
                    <th>Courses</th>
                    <th>Students</th>
                    <th>Attendance Records</th>
                    <th>Performance</th>
                  </tr>
                </thead>
                <tbody id="hodReportsTableBody">
                  <tr>
                    <td colspan="6" class="text-center py-4">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Advanced Analytics Tab -->
      <div class="tab-pane fade" id="advanced-analytics" role="tabpanel">
        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Top Performing Courses</h6>
              </div>
              <div class="card-body">
                <div class="chart-container" style="position: relative; height: 250px;">
                  <canvas id="coursePerformanceChart"></canvas>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card">
              <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Attendance Distribution</h6>
              </div>
              <div class="card-body">
                <div class="chart-container" style="position: relative; height: 250px;">
                  <canvas id="attendanceDistributionChart"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Detailed Analytics Table -->
        <div class="card mt-4">
          <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-table me-2"></i>Detailed Analytics</h6>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Metric</th>
                    <th>Value</th>
                    <th>Change</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="analyticsTableBody">
                  <tr>
                    <td colspan="4" class="text-center py-4">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Lecturer Reports Tab -->
      <div class="tab-pane fade" id="lecturer-reports" role="tabpanel">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Lecturer Performance Reports</h6>
            <button class="btn btn-outline-success btn-sm" onclick="exportLecturerReport()">
              <i class="fas fa-download me-1"></i>Export Lecturer Report
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead>
                  <tr>
                    <th>Lecturer Name</th>
                    <th>Department</th>
                    <th>Courses</th>
                    <th>Students Taught</th>
                    <th>Attendance Records</th>
                    <th>Avg Attendance Rate</th>
                    <th>Performance</th>
                  </tr>
                </thead>
                <tbody id="lecturerReportsTableBody">
                  <tr>
                    <td colspan="7" class="text-center py-4">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Advanced Filters (Collapsible) -->
    <div class="row mb-4 g-3">
      <div class="col-12">
        <div class="card">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0"><i class="fas fa-search-plus me-2"></i>Advanced Filters</h6>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#advancedFilters">
              <i class="fas fa-chevron-down me-1"></i>Toggle Advanced Filters
            </button>
          </div>
          <div class="collapse" id="advancedFilters">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-3">
                  <label for="attendanceStatusFilter" class="form-label">Attendance Status</label>
                  <select id="attendanceStatusFilter" class="form-select">
                    <option value="">All Status</option>
                    <option value="present">Present Only</option>
                    <option value="absent">Absent Only</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="lecturerFilter" class="form-label">Lecturer</label>
                  <select id="lecturerFilter" class="form-select">
                    <option value="">All Lecturers</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="yearLevelFilter" class="form-label">Year Level</label>
                  <select id="yearLevelFilter" class="form-select">
                    <option value="">All Years</option>
                    <option value="1">Year 1</option>
                    <option value="2">Year 2</option>
                    <option value="3">Year 3</option>
                    <option value="4">Year 4</option>
                    <option value="5">Year 5</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label for="recordsPerPage" class="form-label">Records per Page</label>
                  <select id="recordsPerPage" class="form-select">
                    <option value="25">25</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                    <option value="500">500</option>
                  </select>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">&copy; 2025 Rwanda Polytechnic | Admin Panel</div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>

  <script>
    let currentFilters = {};
    let attendanceTrendChart = null;
    let departmentChart = null;
    let coursePerformanceChart = null;
    let attendanceDistributionChart = null;
    let currentPage = 1;
    let totalPages = 1;

    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
      loadStatistics();
      loadDepartments();
      initializeDateRangePicker();
      setupEventListeners();
      loadAnalytics();

      // Initialize tab change handler
      document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
          const target = e.target.getAttribute('data-bs-target');
          if (target === '#hod-reports') {
            loadHODReports();
          } else if (target === '#advanced-analytics') {
            loadAdvancedAnalytics();
          } else if (target === '#lecturer-reports') {
            loadLecturerReports();
          }
        });
      });

      // Load lecturers for advanced filter
      loadLecturersForFilter();
    });

    // Load statistics
    function loadStatistics() {
      $.getJSON('admin-reports.php?ajax=1&action=get_stats', function(data) {
        $('#totalStudents').text(data.total_students);
        $('#totalSessions').text(data.total_sessions);
        $('#avgAttendance').text(data.avg_attendance + '%');
        $('#pendingLeave').text(data.pending_leaves);
      }).fail(function() {
        console.error('Failed to load statistics');
        showAlert('Failed to load statistics', 'danger');
      });
    }

    // Load analytics data
    function loadAnalytics(period = 30) {
      console.log('Loading analytics data...');

      $.getJSON('admin-reports.php?ajax=1&action=get_analytics', function(data) {
        console.log('Analytics response received:', data);

        if (data && data.error) {
          console.error('Analytics error:', data.error);
          showAlert('Failed to load analytics: ' + data.error, 'danger');
          return;
        }

        if (data) {
          console.log('Rendering charts with data:', {
            departments: data.department_attendance?.length || 0,
            trends: data.daily_trends?.length || 0,
            courses: data.course_performance?.length || 0,
            hod_reports: data.hod_reports?.length || 0
          });
          renderCharts(data, period);
          renderDepartmentAnalytics(data.department_attendance || []);
        } else {
          console.error('No analytics data received');
          showAlert('No analytics data available', 'warning');
        }
      }).fail(function(xhr, status, error) {
        console.error('Failed to load analytics:', {
          xhr: xhr,
          status: status,
          error: error,
          responseText: xhr.responseText
        });
        showAlert('Failed to load analytics. Please try again.', 'danger');
      });
    }

    // Render charts
    function renderCharts(data, period) {
      // Validate data structure
      if (!data) {
        console.error('No data provided to renderCharts');
        showAlert('Failed to load chart data', 'danger');
        return;
      }

      const ctx1 = document.getElementById('attendanceTrendChart')?.getContext('2d');
      const ctx2 = document.getElementById('departmentChart')?.getContext('2d');
      const ctx3 = document.getElementById('coursePerformanceChart')?.getContext('2d');
      const ctx4 = document.getElementById('attendanceDistributionChart')?.getContext('2d');

      if (!ctx1 || !ctx2 || !ctx3 || !ctx4) {
        console.error('Chart canvas elements not found');
        return;
      }

      // Destroy existing charts
      if (attendanceTrendChart) attendanceTrendChart.destroy();
      if (departmentChart) departmentChart.destroy();
      if (coursePerformanceChart) coursePerformanceChart.destroy();
      if (attendanceDistributionChart) attendanceDistributionChart.destroy();

      // Daily attendance trend chart
      const dailyTrends = data.daily_trends || [];
      if (dailyTrends.length === 0) {
        // Create empty chart with better messaging
        attendanceTrendChart = new Chart(ctx1, {
          type: 'line',
          data: {
            labels: [],
            datasets: []
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              title: {
                display: true,
                text: 'Attendance Trend - Last 30 Days'
              },
              legend: {
                display: false
              }
            },
            scales: {
              x: {
                display: true,
                title: {
                  display: true,
                  text: 'Date'
                }
              },
              y: {
                display: true,
                title: {
                  display: true,
                  text: 'Count'
                },
                beginAtZero: true
              }
            }
          },
          plugins: [{
            id: 'emptyState',
            beforeDraw: function(chart) {
              const {ctx, width, height} = chart;
              ctx.save();
              ctx.textAlign = 'center';
              ctx.textBaseline = 'middle';
              ctx.fillStyle = '#6c757d';
              ctx.font = '16px Arial';
              ctx.fillText('No attendance data available', width / 2, height / 2 - 20);
              ctx.font = '14px Arial';
              ctx.fillStyle = '#9ca3af';
              ctx.fillText('Data will appear here once attendance is recorded', width / 2, height / 2 + 10);
              ctx.restore();
            }
          }]
        });
      } else {
        const trendLabels = dailyTrends.map(item => item.date || '');
        const presentData = dailyTrends.map(item => item.present_count || 0);
        const absentData = dailyTrends.map(item => item.absent_count || 0);

        attendanceTrendChart = new Chart(ctx1, {
          type: 'line',
          data: {
            labels: trendLabels,
            datasets: [{
              label: 'Present',
              data: presentData,
              borderColor: '#28a745',
              backgroundColor: 'rgba(40, 167, 69, 0.1)',
              tension: 0.4,
              fill: true
            }, {
              label: 'Absent',
              data: absentData,
              borderColor: '#dc3545',
              backgroundColor: 'rgba(220, 53, 69, 0.1)',
              tension: 0.4,
              fill: true
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              title: {
                display: true,
                text: `Attendance Trend (Last ${period} Days)`
              }
            },
            scales: {
              y: {
                beginAtZero: true
              }
            }
          }
        });
      }

      // Department performance chart
      const departmentAttendance = data.department_attendance || [];
      if (departmentAttendance.length === 0) {
        departmentChart = new Chart(ctx2, {
          type: 'bar',
          data: {
            labels: ['No Data'],
            datasets: [{
              label: 'No Data Available',
              data: [0],
              backgroundColor: ['#6c757d']
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              title: {
                display: true,
                text: 'Department Attendance Rates (No Data)'
              }
            }
          }
        });
      } else {
        const deptLabels = departmentAttendance.map(item => item.department || 'Unknown');
        const attendanceRates = departmentAttendance.map(item => parseFloat(item.attendance_rate) || 0);

        departmentChart = new Chart(ctx2, {
          type: 'bar',
          data: {
            labels: deptLabels,
            datasets: [{
              label: 'Attendance Rate (%)',
              data: attendanceRates,
              backgroundColor: attendanceRates.map(rate =>
                rate >= 90 ? '#28a745' :
                rate >= 75 ? '#ffc107' : '#dc3545'
              ),
              borderWidth: 1
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              title: {
                display: true,
                text: 'Department Attendance Rates'
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                max: 100
              }
            }
          }
        });
      }

      // Course performance chart
      const coursePerformance = data.course_performance || [];
      const courseLabels = coursePerformance.slice(0, 5).map(item => item.course_name || 'Unknown');
      const courseRates = coursePerformance.slice(0, 5).map(item => parseFloat(item.attendance_rate) || 0);

      coursePerformanceChart = new Chart(ctx3, {
        type: 'doughnut',
        data: {
          labels: courseLabels,
          datasets: [{
            data: courseRates,
            backgroundColor: [
              '#007bff', '#28a745', '#ffc107', '#fd7e14', '#dc3545'
            ]
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            title: {
              display: true,
              text: 'Top 5 Course Performance'
            }
          }
        }
      });

      // Attendance distribution
      const dailyTrendsData = data.daily_trends || [];
      const totalPresent = dailyTrendsData.reduce((sum, item) => sum + (item.present_count || 0), 0);
      const totalAbsent = dailyTrendsData.reduce((sum, item) => sum + (item.absent_count || 0), 0);

      attendanceDistributionChart = new Chart(ctx4, {
        type: 'pie',
        data: {
          labels: ['Present', 'Absent'],
          datasets: [{
            data: [totalPresent, totalAbsent],
            backgroundColor: ['#28a745', '#dc3545']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            title: {
              display: true,
              text: 'Overall Attendance Distribution'
            }
          }
        }
      });
    }

    // Render department analytics table
    function renderDepartmentAnalytics(departments) {
      const tbody = $('#analyticsTableBody');
      tbody.empty();

      departments.forEach(dept => {
        const change = Math.random() * 10 - 5; // Mock change data
        const statusClass = change > 0 ? 'success' : 'danger';
        const statusIcon = change > 0 ? 'up' : 'down';

        tbody.append(`
          <tr>
            <td>${dept.department}</td>
            <td>${dept.attendance_rate}%</td>
            <td>
              <span class="badge bg-${statusClass}">
                <i class="fas fa-arrow-${statusIcon} me-1"></i>
                ${Math.abs(change).toFixed(1)}%
              </span>
            </td>
            <td>
              <span class="badge bg-${dept.attendance_rate >= 90 ? 'success' : dept.attendance_rate >= 75 ? 'warning' : 'danger'}">
                ${dept.attendance_rate >= 90 ? 'Excellent' : dept.attendance_rate >= 75 ? 'Good' : 'Needs Improvement'}
              </span>
            </td>
          </tr>
        `);
      });
    }

    // Load HOD reports
    function loadHODReports() {
      $.getJSON('admin-reports.php?ajax=1&action=get_analytics', function(data) {
        const tbody = $('#hodReportsTableBody');
        tbody.empty();

        if (data.hod_reports && data.hod_reports.length > 0) {
          data.hod_reports.forEach(hod => {
            const performance = hod.attendance_records > 0 ?
              Math.round((hod.attendance_records / hod.students_count) * 100) : 0;

            tbody.append(`
              <tr>
                <td>${hod.department_name}</td>
                <td>${hod.hod_name || 'Not Assigned'}</td>
                <td>${hod.courses_count}</td>
                <td>${hod.students_count}</td>
                <td>${hod.attendance_records}</td>
                <td>
                  <div class="progress" style="width: 100px;">
                    <div class="progress-bar bg-${performance >= 80 ? 'success' : performance >= 60 ? 'warning' : 'danger'}"
                         role="progressbar" style="width: ${performance}%">
                      ${performance}%
                    </div>
                  </div>
                </td>
              </tr>
            `);
          });
        } else {
          tbody.html('<tr><td colspan="6" class="text-center py-4">No HOD reports available</td></tr>');
        }
      }).fail(function() {
        $('#hodReportsTableBody').html('<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load HOD reports</td></tr>');
      });
    }

    // Load advanced analytics
    function loadAdvancedAnalytics() {
      // This is handled by the main loadAnalytics function
      // Additional analytics can be added here
    }

    // Show alert helper
    function showAlert(message, type = 'info') {
      const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
          <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      `;
      $('.main-content').prepend(alertHtml);
      setTimeout(() => $('.alert').alert('close'), 5000);
    }

    // Load lecturer reports
    function loadLecturerReports() {
      $.getJSON('admin-reports.php?ajax=1&action=get_lecturer_reports', function(response) {
        const tbody = $('#lecturerReportsTableBody');
        tbody.empty();

        if (response.error) {
          tbody.html(`<tr><td colspan="7" class="text-center py-4 text-danger">${response.error}</td></tr>`);
          return;
        }

        if (response.data && response.data.length > 0) {
          response.data.forEach(lecturer => {
            const performanceClass = lecturer.avg_attendance_rate >= 90 ? 'excellent' :
                                   lecturer.avg_attendance_rate >= 75 ? 'good' : 'poor';

            tbody.append(`
              <tr>
                <td>${lecturer.lecturer_name}</td>
                <td>${lecturer.department_name || 'Not Assigned'}</td>
                <td>${lecturer.courses_count}</td>
                <td>${lecturer.unique_students}</td>
                <td>${lecturer.attendance_records}</td>
                <td>${lecturer.avg_attendance_rate}%</td>
                <td>
                  <span class="badge bg-${performanceClass === 'excellent' ? 'success' : performanceClass === 'good' ? 'warning' : 'danger'}">
                    ${performanceClass.charAt(0).toUpperCase() + performanceClass.slice(1)}
                  </span>
                </td>
              </tr>
            `);
          });
        } else {
          tbody.html('<tr><td colspan="7" class="text-center py-4">No lecturer reports available</td></tr>');
        }
      }).fail(function() {
        $('#lecturerReportsTableBody').html('<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load lecturer reports</td></tr>');
      });
    }

    // Load lecturers for filter dropdown
    function loadLecturersForFilter() {
      $.getJSON('api/assign-hod-api.php?action=get_lecturers', function(response) {
        const select = $('#lecturerFilter');
        select.empty().append('<option value="">All Lecturers</option>');

        if (response.status === 'success' && response.data) {
          response.data.forEach(lecturer => {
            const displayName = lecturer.display_name || `${lecturer.first_name} ${lecturer.last_name}`;
            select.append(`<option value="${lecturer.id}">${displayName}</option>`);
          });
        }
      }).fail(function() {
        console.error('Failed to load lecturers for filter');
      });
    }

    // Export HOD report
    function exportHODReport() {
      $.getJSON('admin-reports.php?ajax=1&action=get_analytics', function(data) {
        if (data.hod_reports && data.hod_reports.length > 0) {
          exportToExcel(data.hod_reports, 'hod_report');
        } else {
          showAlert('No HOD data available to export', 'warning');
        }
      });
    }

    // Export lecturer report
    function exportLecturerReport() {
      $.getJSON('admin-reports.php?ajax=1&action=get_lecturer_reports', function(response) {
        if (response.data && response.data.length > 0) {
          exportToExcel(response.data, 'lecturer_report');
        } else {
          showAlert('No lecturer data available to export', 'warning');
        }
      });
    }

    // Enhanced export function
    function exportToExcel(data, type = 'attendance') {
      let csvContent = '';

      if (type === 'hod_report') {
        csvContent = "Department,HOD Name,Courses,Students,Attendance Records\n";
        data.forEach(row => {
          csvContent += `"${row.department_name}","${row.hod_name || 'Not Assigned'}","${row.courses_count}","${row.students_count}","${row.attendance_records}"\n`;
        });
      } else {
        csvContent = "Student Name,Department,Option,Course,Date,Status\n";
        data.forEach(row => {
          csvContent += `"${row.first_name} ${row.last_name}","${row.department_name}","${row.option_name}","${row.course_name || 'N/A'}","${row.attendance_date}","${row.status}"\n`;
        });
      }

      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.setAttribute('href', url);
      link.setAttribute('download', `${type}_report_${new Date().toISOString().split('T')[0]}.csv`);
      link.style.visibility = 'hidden';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // Load departments
    function loadDepartments() {
      $.getJSON('admin-reports.php?ajax=1&action=get_departments', function(data) {
        const deptSelect = $('#departmentFilter');
        deptSelect.empty().append('<option value="">All Departments</option>');

        data.forEach(function(dept) {
          deptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
        });
      }).fail(function() {
        console.error('Failed to load departments');
      });
    }

    // Load options based on department
    function loadOptions(departmentId) {
      if (!departmentId) {
        $('#optionFilter').html('<option value="">All Options</option>').prop('disabled', true);
        $('#courseFilter').html('<option value="">All Courses</option>').prop('disabled', true);
        return;
      }

      $.getJSON('admin-reports.php?ajax=1&action=get_options', {department_id: departmentId}, function(data) {
        const optionSelect = $('#optionFilter');
        optionSelect.empty().append('<option value="">All Options</option>');

        data.forEach(function(option) {
          optionSelect.append(`<option value="${option.id}">${option.name}</option>`);
        });

        optionSelect.prop('disabled', false);
      }).fail(function() {
        console.error('Failed to load options');
      });
    }

    // Load courses based on department and option
    function loadCourses(departmentId, optionId) {
      if (!departmentId) {
        $('#courseFilter').html('<option value="">All Courses</option>').prop('disabled', true);
        return;
      }

      $.getJSON('admin-reports.php?ajax=1&action=get_courses', {
        department_id: departmentId,
        option_id: optionId
      }, function(data) {
        const courseSelect = $('#courseFilter');
        courseSelect.empty().append('<option value="">All Courses</option>');

        data.forEach(function(course) {
          const courseDisplay = course.course_code ? `${course.name} (${course.course_code})` : course.name;
          courseSelect.append(`<option value="${course.id}">${courseDisplay}</option>`);
        });

        courseSelect.prop('disabled', false);
      }).fail(function() {
        console.error('Failed to load courses');
      });
    }

    // Initialize date range picker
    function initializeDateRangePicker() {
      $('#dateRange').daterangepicker({
        autoUpdateInput: false,
        locale: {
          cancelLabel: 'Clear',
          format: 'YYYY-MM-DD'
        },
        ranges: {
          'Today': [moment(), moment()],
          'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
          'Last 7 Days': [moment().subtract(6, 'days'), moment()],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'This Month': [moment().startOf('month'), moment().endOf('month')],
          'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
      });

      $('#dateRange').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
      });

      $('#dateRange').on('cancel.daterangepicker', function(ev, picker) {
        $(this).val('');
      });
    }

    // Setup event listeners
    function setupEventListeners() {
      // Chart period change
      $('input[name="chartPeriod"]').change(function() {
        const period = $(this).val();
        loadAnalytics(period);
      });

      // Department change
      $('#departmentFilter').change(function() {
        const deptId = $(this).val();
        loadOptions(deptId);
        $('#courseFilter').html('<option value="">All Courses</option>').prop('disabled', true);
      });

      // Option change
      $('#optionFilter').change(function() {
        const deptId = $('#departmentFilter').val();
        const optionId = $(this).val();
        loadCourses(deptId, optionId);
      });

      // Filter form submission
      $('#filterForm').submit(function(e) {
        e.preventDefault();
        applyFilters();
      });

      // Reset filters
      $('#resetFilters').click(function() {
        resetFilters();
      });

      // Export buttons
      $('#exportExcel').click(function() {
        exportData('excel');
      });

      $('#exportPdf').click(function() {
        exportData('pdf');
      });

      // Auto-refresh statistics every 5 minutes
      setInterval(function() {
        loadStatistics();
      }, 300000);
    }

    // Apply filters
    function applyFilters() {
      const filters = {
        department_id: $('#departmentFilter').val(),
        option_id: $('#optionFilter').val(),
        course_id: $('#courseFilter').val(),
        attendance_status: $('#attendanceStatusFilter').val(),
        lecturer_id: $('#lecturerFilter').val(),
        year_level: $('#yearLevelFilter').val(),
        records_per_page: $('#recordsPerPage').val()
      };

      // Parse date range
      const dateRange = $('#dateRange').val();
      if (dateRange && dateRange.includes(' to ')) {
        const [startDate, endDate] = dateRange.split(' to ');
        filters.date_from = startDate;
        filters.date_to = endDate;
      }

      currentFilters = filters;
      currentPage = 1; // Reset to first page when applying filters
      loadReports(filters, 1);

      // Update URL without page reload
      const queryParams = $.param(filters);
      const newUrl = `${window.location.pathname}?${queryParams}`;
      window.history.pushState({}, '', newUrl);
    }

    // Load reports data with pagination
    function loadReports(filters = {}, page = 1) {
      $('#tableSpinner').removeClass('d-none');
      $('#reportTableBody').html(`
        <tr>
          <td colspan="8" class="text-center py-4">
            <div class="spinner-border text-primary" style="color: #0066cc !important;" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2">Loading reports...</div>
          </td>
        </tr>
      `);

      const queryParams = $.param({...filters, page, limit: 50});
      $.getJSON(`admin-reports.php?ajax=1&action=get_paginated_reports&${queryParams}`, function(response) {
        $('#tableSpinner').addClass('d-none');
        if (response.error) {
          $('#reportTableBody').html(`
            <tr>
              <td colspan="8" class="text-center py-5">
                <div class="text-danger">
                  <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                  <div>${response.error}</div>
                </div>
              </td>
            </tr>
          `);
        } else {
          displayReports(response.data);
          renderPagination(response.pagination);
        }
      }).fail(function() {
        $('#tableSpinner').addClass('d-none');
        $('#reportTableBody').html(`
          <tr>
            <td colspan="8" class="text-center py-5">
              <div class="text-danger">
                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                <div>Failed to load reports</div>
              </div>
            </td>
          </tr>
        `);
      });
    }

    // Render pagination
    function renderPagination(pagination) {
      const { page, pages, total } = pagination;
      currentPage = page;
      totalPages = pages;

      let paginationHtml = `
        <nav aria-label="Reports pagination">
          <ul class="pagination justify-content-center">
            <li class="page-item ${page <= 1 ? 'disabled' : ''}">
              <a class="page-link" href="#" onclick="changePage(${page - 1})">Previous</a>
            </li>
      `;

      for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) {
        paginationHtml += `
          <li class="page-item ${i === page ? 'active' : ''}">
            <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
          </li>
        `;
      }

      paginationHtml += `
            <li class="page-item ${page >= pages ? 'disabled' : ''}">
              <a class="page-link" href="#" onclick="changePage(${page + 1})">Next</a>
            </li>
          </ul>
          <div class="text-center text-muted mt-2">
            Showing page ${page} of ${pages} (${total} total records)
          </div>
        </nav>
      `;

      $('#reportTableBody').after(paginationHtml);
    }

    // Change page
    function changePage(newPage) {
      if (newPage >= 1 && newPage <= totalPages) {
        loadReports(currentFilters, newPage);
      }
    }

    // Display reports in table
    function displayReports(reports) {
      $('#recordCount').text(reports.length);

      if (reports.length === 0) {
        $('#reportTableBody').html(`
          <tr>
            <td colspan="8" class="text-center py-5">
              <div class="d-flex flex-column align-items-center">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Records Found</h5>
                <p class="text-muted mb-0">Try adjusting your filters</p>
              </div>
            </td>
          </tr>
        `);
        return;
      }

      let html = '';
      reports.forEach(function(report, index) {
        const statusBadge = report.status === 'present'
          ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Present</span>'
          : '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Absent</span>';

        const methodIcon = report.method === 'face'
          ? '<i class="fas fa-camera text-info"></i>'
          : '<i class="fas fa-fingerprint text-warning"></i>';

        html += `
          <tr>
            <td>${index + 1}</td>
            <td>${report.first_name} ${report.last_name}</td>
            <td>${report.department_name}</td>
            <td>${report.option_name}</td>
            <td>${report.course_name || 'N/A'} ${report.course_code ? `(${report.course_code})` : ''}</td>
            <td>${report.attendance_date}</td>
            <td>${statusBadge}</td>
            <td class="text-center"><i class="fas fa-fingerprint text-warning"></i></td>
          </tr>
        `;
      });

      $('#reportTableBody').html(html);
    }

    // Reset filters
    function resetFilters() {
      $('#departmentFilter').val('');
      $('#optionFilter').html('<option value="">All Options</option>').prop('disabled', true);
      $('#courseFilter').html('<option value="">All Courses</option>').prop('disabled', true);
      $('#dateRange').val('');
      currentFilters = {};
      loadReports();
    }

    // Export data
    function exportData(format) {
      if (Object.keys(currentFilters).length === 0) {
        alert('Please apply filters first to export data');
        return;
      }

      const queryParams = $.param({
        ...currentFilters,
        export: format,
        ajax: 1,
        action: 'export_reports'
      });

      window.open(`admin-reports.php?${queryParams}`, '_blank');
    }

    // Load initial reports
    loadReports();
  </script>
</body>
</html>
