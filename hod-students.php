<?php
require_once "config.php";
session_start();
require_once "session_check.php";

// Ensure user is logged in and is HoD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: login_new.php");
    exit;
}

// Get HoD information and verify department assignment
$user_id = $_SESSION['user_id'];
$department_name = null;
$department_id = null;
try {
    // First, check if the user has a lecturer record
    $lecturer_stmt = $pdo->prepare("SELECT id, gender, dob, id_number, department_id, education_level FROM lecturers WHERE user_id = ?");
    $lecturer_stmt->execute([$user_id]);
    $lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        // HOD user doesn't have a lecturer record
        $error_message = "You are not registered as a lecturer. Please contact administrator.";
    } else {
        $lecturer_id = $lecturer['id'];

        // Try multiple approaches to find department assignment (handles both correct and legacy hod_id references)
        $dept_result = null;

        // Approach 1: Correct way - hod_id points to lecturers.id
        $stmt = $pdo->prepare("
            SELECT d.name as department_name, d.id as department_id, 'direct' as match_type
            FROM departments d
            WHERE d.hod_id = ? AND d.hod_id IS NOT NULL
        ");
        $stmt->execute([$lecturer_id]);
        $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Approach 2: Legacy way - hod_id might point to users.id (incorrect but may exist in data)
        if (!$dept_result) {
            $stmt = $pdo->prepare("
                SELECT d.name as department_name, d.id as department_id, 'legacy' as match_type
                FROM departments d
                WHERE d.hod_id = ? AND d.hod_id IS NOT NULL
            ");
            $stmt->execute([$user_id]);
            $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);

            // If found via legacy method, log it for potential data fix
            if ($dept_result) {
                error_log("HOD Students: Found department assignment via legacy hod_id match (user_id instead of lecturer_id) for user $user_id");
            }
        }

        // Approach 3: Check if lecturer's department_id matches any department's hod_id
        if (!$dept_result && $lecturer['department_id']) {
            $stmt = $pdo->prepare("
                SELECT d.name as department_name, d.id as department_id, 'department_match' as match_type
                FROM departments d
                WHERE d.id = ? AND d.hod_id IS NOT NULL
            ");
            $stmt->execute([$lecturer['department_id']]);
            $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$dept_result) {
            // User is HOD but not assigned to any department
            $error_message = "You are not assigned as Head of Department for any department. Please contact administrator.";
        } else {
            $department_name = $dept_result['department_name'];
            $department_id = $dept_result['department_id'];

            // Get user information
            $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user['department_name'] = $department_name;
            $user['department_id'] = $department_id;

            // Log match type for debugging
            error_log("HOD Students: Department assignment found via {$dept_result['match_type']} for user $user_id, department $department_name");
        }
    }
} catch (PDOException $e) {
    error_log("Database error in hod-students.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later. Error: " . $e->getMessage();
}

// Get students in this department
$students = [];
$stats = [];
$hod_info = null;

if ($department_id) {
    try {
        // Store HOD information for backend use
        $hod_info = [
            'user_id' => $user_id,
            'lecturer_id' => $lecturer_id,
            'department_id' => $department_id,
            'department_name' => $department_name,
            'name' => $user['name'],
            'email' => $user['email']
        ];

        // Get students with their program information and recent attendance (last 30 days)
        $stmt = $pdo->prepare("
            SELECT s.reg_no, s.user_id, s.year_level, s.student_id_number,
                     u.first_name, u.last_name, u.email, u.status, u.phone, u.created_at,
                     o.name as program_name, o.id as option_id,
                     COUNT(ar.id) as total_attendance_30d,
                     COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count_30d,
                     COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count_30d,
                     COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count_30d,
                     MAX(ar.recorded_at) as last_attendance_date
             FROM students s
             JOIN users u ON s.user_id = u.id
             JOIN options o ON s.option_id = o.id
             LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
                 AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             WHERE o.department_id = ?
             GROUP BY s.reg_no, s.user_id, s.year_level, s.student_id_number,
                      u.first_name, u.last_name, u.email, u.status, u.phone, u.created_at,
                      o.name, o.id
             ORDER BY s.year_level, u.last_name, u.first_name
         ");
        $stmt->execute([$department_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate attendance percentages and status
        foreach ($students as &$student) {
            $total_30d = $student['total_attendance_30d'];
            $present_30d = $student['present_count_30d'];

            $student['attendance_rate_30d'] = $total_30d > 0 ?
                round(($present_30d / $total_30d) * 100, 1) : 0;

            // Determine attendance status based on recent performance
            if ($total_30d == 0) {
                $student['attendance_status'] = 'no_data';
            } elseif ($student['attendance_rate_30d'] >= 80) {
                $student['attendance_status'] = 'excellent';
            } elseif ($student['attendance_rate_30d'] >= 60) {
                $student['attendance_status'] = 'good';
            } elseif ($student['attendance_rate_30d'] >= 40) {
                $student['attendance_status'] = 'fair';
            } else {
                $student['attendance_status'] = 'poor';
            }

            // Format last attendance date
            $student['last_attendance_formatted'] = $student['last_attendance_date'] ?
                date('M j, Y', strtotime($student['last_attendance_date'])) : 'Never';
        }

        // Get comprehensive summary statistics
        $stats_stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT s.id) as total_students,
                COUNT(DISTINCT CASE WHEN u.status = 'active' THEN s.id END) as active_students,
                COUNT(DISTINCT CASE WHEN u.status = 'inactive' THEN s.id END) as inactive_students,
                COUNT(DISTINCT CASE WHEN s.year_level = '1' THEN s.id END) as year1_count,
                COUNT(DISTINCT CASE WHEN s.year_level = '2' THEN s.id END) as year2_count,
                COUNT(DISTINCT CASE WHEN s.year_level = '3' THEN s.id END) as year3_count,
                COUNT(DISTINCT CASE WHEN s.year_level = '4' THEN s.id END) as year4_count,
                COUNT(DISTINCT o.id) as programs_count,
                COUNT(DISTINCT CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN s.id END) as new_students_30d,
                COUNT(DISTINCT CASE WHEN u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN s.id END) as new_students_7d,
                AVG(CASE WHEN ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN
                    CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END
                END) as avg_attendance_rate_30d
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN options o ON s.option_id = o.id
            LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
                AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            WHERE o.department_id = ?
        ");
        $stats_stmt->execute([$department_id]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

        // Calculate additional statistics
        $stats['inactive_students'] = ($stats['total_students'] ?? 0) - ($stats['active_students'] ?? 0);
        $stats['avg_attendance_rate_30d'] = round($stats['avg_attendance_rate_30d'] ?? 0, 1);

        // Get attendance distribution
        $attendance_stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT CASE WHEN attendance_rate >= 80 THEN attendance_stats.reg_no END) as excellent_count,
                COUNT(DISTINCT CASE WHEN attendance_rate >= 60 AND attendance_rate < 80 THEN attendance_stats.reg_no END) as good_count,
                COUNT(DISTINCT CASE WHEN attendance_rate >= 40 AND attendance_rate < 60 THEN attendance_stats.reg_no END) as fair_count,
                COUNT(DISTINCT CASE WHEN attendance_rate < 40 THEN attendance_stats.reg_no END) as poor_count
            FROM (
                SELECT s.reg_no,
                       CASE WHEN COUNT(ar.id) > 0
                           THEN (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) / COUNT(ar.id)) * 100
                           ELSE 0 END as attendance_rate
                FROM students s
                JOIN users u ON s.user_id = u.id
                JOIN options o ON s.option_id = o.id
                LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
                    AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                WHERE o.department_id = ?
                GROUP BY s.reg_no
            ) as attendance_stats
        ");
        $attendance_stmt->execute([$department_id]);
        $attendance_dist = $attendance_stmt->fetch(PDO::FETCH_ASSOC);

        $stats = array_merge($stats, $attendance_dist);
        
        // Log successful data loading
        error_log("Successfully loaded " . count($students) . " students for department " . $department_id);

    } catch (PDOException $e) {
        error_log("Error fetching students in hod-students.php: " . $e->getMessage());
        error_log("Error details: " . $e->getFile() . " on line " . $e->getLine());
        error_log("SQL State: " . $e->getCode());
        $error_message = "Unable to load student data. Error: " . $e->getMessage();
    }
}

// Backend API handling for AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    if (!$hod_info) {
        echo json_encode(['success' => false, 'message' => 'HOD not authenticated']);
        exit;
    }

    $action = $_GET['action'] ?? '';

    try {
        switch ($action) {
            case 'get_students':
                // Return paginated students list
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 25;
                $offset = ($page - 1) * $limit;

                $year_filter = $_GET['year'] ?? '';
                $status_filter = $_GET['status'] ?? '';
                $program_filter = $_GET['program'] ?? '';

                $where_conditions = ["o.department_id = ?"];
                $params = [$hod_info['department_id']];

                if ($year_filter !== '') {
                    $where_conditions[] = "s.year_level = ?";
                    $params[] = $year_filter;
                }

                if ($status_filter !== '') {
                    $where_conditions[] = "u.status = ?";
                    $params[] = $status_filter;
                }

                if ($program_filter !== '') {
                    $where_conditions[] = "o.id = ?";
                    $params[] = $program_filter;
                }

                $where_clause = implode(' AND ', $where_conditions);

                // Get total count
                $count_stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT s.id) as total
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    JOIN options o ON s.option_id = o.id
                    WHERE $where_clause
                ");
                $count_stmt->execute($params);
                $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

                // Get paginated students
                $stmt = $pdo->prepare("
                    SELECT s.reg_no, s.user_id, s.year_level, s.student_id_number,
                            u.first_name, u.last_name, u.email, u.status, u.phone, u.created_at,
                            o.name as program_name,
                            COUNT(ar.id) as total_attendance_30d,
                            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count_30d
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    JOIN options o ON s.option_id = o.id
                    LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
                        AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    WHERE $where_clause
                    GROUP BY s.reg_no, s.user_id, s.year_level, s.student_id_number,
                             u.first_name, u.last_name, u.email, u.status, u.phone, u.created_at, o.name
                    ORDER BY s.year_level, u.last_name, u.first_name
                    LIMIT ? OFFSET ?
                ");
                $params[] = $limit;
                $params[] = $offset;
                $stmt->execute($params);
                $paginated_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Calculate attendance rates for paginated results
                foreach ($paginated_students as &$student) {
                    $student['attendance_rate_30d'] = $student['total_attendance_30d'] > 0 ?
                        round(($student['present_count_30d'] / $student['total_attendance_30d']) * 100, 1) : 0;
                }

                echo json_encode([
                    'success' => true,
                    'students' => $paginated_students,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total_count,
                        'total_pages' => ceil($total_count / $limit)
                    ]
                ]);
                break;

            case 'get_student_details':
                $student_id = $_GET['student_id'] ?? 0;

                if (!$student_id) {
                    echo json_encode(['success' => false, 'message' => 'Student ID required']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.status as user_status,
                           o.name as program_name, d.name as department_name
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    JOIN options o ON s.option_id = o.id
                    JOIN departments d ON o.department_id = d.id
                    WHERE s.id = ? AND o.department_id = ?
                ");
                $stmt->execute([$student_id, $hod_info['department_id']]);
                $student_details = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$student_details) {
                    echo json_encode(['success' => false, 'message' => 'Student not found']);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'student' => $student_details
                ]);
                break;

            case 'get_attendance_stats':
                $student_id = $_GET['student_id'] ?? 0;
                $period = $_GET['period'] ?? '30'; // days

                if (!$student_id) {
                    echo json_encode(['success' => false, 'message' => 'Student ID required']);
                    exit;
                }

                $stmt = $pdo->prepare("
                    SELECT
                        COUNT(*) as total_sessions,
                        COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                        COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                        DATE(recorded_at) as date
                    FROM attendance_records
                    WHERE student_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY DATE(recorded_at)
                    ORDER BY date DESC
                ");
                $stmt->execute([$student_id, $period]);
                $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'attendance' => $attendance_data
                ]);
                break;

            case 'export_students':
                // Generate CSV export
                $filename = 'department_students_' . date('Y-m-d_H-i-s') . '.csv';

                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');

                $output = fopen('php://output', 'w');

                // CSV headers
                fputcsv($output, ['Reg No', 'Name', 'Email', 'Program', 'Year Level', 'Status', 'Attendance Rate (30d)', 'Phone', 'Registration Date']);

                // Get all students for export
                $export_stmt = $pdo->prepare("
                    SELECT s.reg_no, CONCAT(u.first_name, ' ', u.last_name) as full_name,
                           u.email, o.name as program_name, s.year_level, u.status, u.phone, u.created_at,
                           COUNT(ar.id) as total_attendance,
                           COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count
                    FROM students s
                    JOIN users u ON s.user_id = u.id
                    JOIN options o ON s.option_id = o.id
                    LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
                        AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    WHERE o.department_id = ?
                    GROUP BY s.reg_no, s.user_id, s.year_level, s.student_id_number,
                             u.first_name, u.last_name, u.email, o.name, u.status, u.phone, u.created_at
                    ORDER BY s.year_level, u.last_name, u.first_name
                ");
                $export_stmt->execute([$hod_info['department_id']]);
                $export_students = $export_stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($export_students as $student) {
                    $attendance_rate = $student['total_attendance'] > 0 ?
                        round(($student['present_count'] / $student['total_attendance']) * 100, 1) : 0;

                    fputcsv($output, [
                        $student['reg_no'],
                        $student['full_name'],
                        $student['email'],
                        $student['program_name'],
                        'Year ' . $student['year_level'],
                        ucfirst($student['status']),
                        $attendance_rate . '%',
                        $student['phone'] ?? '',
                        date('M j, Y', strtotime($student['created_at']))
                    ]);
                }

                fclose($output);
                exit;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (PDOException $e) {
        error_log("AJAX error in hod-students.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Overview - HOD Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }
        
        .content-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .students-table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .attendance-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
        }
    </style>
</head>

<body>
    <!-- Include HOD Sidebar -->
    <?php include 'includes/hod_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="fas fa-users text-primary me-2"></i>
                        Students Overview
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="hod-dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Students</li>
                        </ol>
                    </nav>
                    <p class="text-muted mb-0"><?= htmlspecialchars($department_name ?? 'No Department Assigned') ?> Department</p>
                </div>
                <div>
                    <?php if ($department_id): ?>
                    <button class="btn btn-primary me-2" onclick="exportStudentData()">
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                    <button class="btn btn-success" onclick="addNewStudent()">
                        <i class="fas fa-plus me-1"></i> Add Student
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($error_message) && !empty($students)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Note:</strong> Some data may be incomplete due to: <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php elseif (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-primary"><?= $stats['total_students'] ?? 0 ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-success"><?= $stats['active_students'] ?? 0 ?></div>
                        <div class="stat-label">Active Students</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-info"><?= $stats['programs_count'] ?? 0 ?></div>
                        <div class="stat-label">Programs</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-warning">
                            <?= ($stats['year1_count'] ?? 0) + ($stats['year2_count'] ?? 0) + ($stats['year3_count'] ?? 0) ?>
                        </div>
                        <div class="stat-label">All Years</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Year Level Distribution -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-info"><?= $stats['year1_count'] ?? 0 ?></div>
                        <div class="stat-label">Year 1 Students</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-warning"><?= $stats['year2_count'] ?? 0 ?></div>
                        <div class="stat-label">Year 2 Students</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-danger"><?= $stats['year3_count'] ?? 0 ?></div>
                        <div class="stat-label">Year 3 Students</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="students-table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Students List
                </h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="yearFilter" onchange="filterByYear()">
                        <option value="">All Years</option>
                        <option value="1">Year 1</option>
                        <option value="2">Year 2</option>
                        <option value="3">Year 3</option>
                    </select>
                    <select class="form-select form-select-sm" id="statusFilter" onchange="filterByStatus()">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover" id="studentsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Reg No</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Program</th>
                            <th>Year</th>
                            <th>Status</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                         <tr>
                             <td>
                                 <strong><?= htmlspecialchars($student['reg_no'] ?? 'N/A') ?></strong>
                             </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                        <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($student['email']) ?></td>
                            <td><?= htmlspecialchars($student['program_name']) ?></td>
                            <td>
                                <span class="badge bg-info">Year <?= $student['year_level'] ?></span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $student['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($student['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $attendance_rate = $student['attendance_rate_30d'];
                                $badge_class = $attendance_rate >= 80 ? 'success' : ($attendance_rate >= 60 ? 'warning' : 'danger');
                                ?>
                                <span class="badge bg-<?= $badge_class ?> attendance-badge">
                                    <?= $attendance_rate ?>%
                                </span>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewStudent(<?= $student['id'] ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-info" onclick="viewAttendance(<?= $student['id'] ?>)" title="View Attendance">
                                        <i class="fas fa-calendar-check"></i>
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="editStudent(<?= $student['id'] ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery first -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Wait for all scripts to load
        function initializeDataTable() {
            if (typeof $ !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
                $('#studentsTable').DataTable({
                    responsive: true,
                    pageLength: 25,
                    order: [[1, 'asc']],
                    language: {
                        search: "Search students:",
                        lengthMenu: "Show _MENU_ students per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ students"
                    }
                });
            } else {
                // Retry after a short delay
                setTimeout(initializeDataTable, 100);
            }
        }

        // Initialize when document is ready
        $(document).ready(function() {
            initializeDataTable();
        });

        function filterByYear() {
            const year = document.getElementById('yearFilter').value;
            const table = $('#studentsTable').DataTable();
            table.column(4).search(year ? 'Year ' + year : '').draw();
        }

        function filterByStatus() {
            const status = document.getElementById('statusFilter').value;
            const table = $('#studentsTable').DataTable();
            table.column(5).search(status ? status : '').draw();
        }

        function viewStudent(studentId) {
            window.location.href = `hod-student-details.php?id=${studentId}`;
        }

        function viewAttendance(studentId) {
            window.location.href = `hod-student-attendance.php?id=${studentId}`;
        }

        function editStudent(studentId) {
            window.location.href = `hod-edit-student.php?id=${studentId}`;
        }

        function exportStudentData() {
            window.location.href = 'hod-export-students.php';
        }

        function addNewStudent() {
            window.location.href = 'register-student.php';
        }
    </script>
</body>
</html>
