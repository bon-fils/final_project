<?php
declare(strict_types=1);
session_start();

require_once "config.php";
require_once "session_check.php";
require_role(['student']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Get student information
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.reg_no, s.year_level, s.option_id,
               u.first_name, u.last_name, u.email,
               d.name as department_name, o.name as option_name
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN options o ON s.option_id = o.id
        LEFT JOIN departments d ON o.department_id = d.id
        WHERE s.user_id = ? AND u.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: login.php?error=student_not_found");
        exit();
    }

} catch (Exception $e) {
    error_log("Student info error: " . $e->getMessage());
    header("Location: login.php?error=database");
    exit();
}

// Get filter parameters
$selected_course_id = $_GET['course_id'] ?? null;
$view_type = $_GET['view'] ?? 'all'; // 'all' or 'under_85'

// Get courses for this student's option and year level
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.course_name, c.course_code
        FROM courses c
        WHERE c.option_id = ? AND c.year = ? AND c.status = 'active'
        ORDER BY c.course_name
    ");
    $stmt->execute([$student['option_id'], $student['year_level']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courses = [];
}

// Get attendance data
$attendance_data = [];
$summary_stats = [
    'total_sessions' => 0,
    'present_count' => 0,
    'absent_count' => 0,
    'attendance_percentage' => 0,
    'courses_under_85' => []
];

try {
    if ($selected_course_id) {
        // Get attendance for specific course
        // Logic: Get all sessions for the course, then check if student has attendance record
        $stmt = $pdo->prepare("
            SELECT
                c.course_name, c.course_code, c.id as course_id,
                ats.id as session_id, ats.session_date, ats.start_time, ats.end_time,
                ar.status, ar.recorded_at,
                l.first_name as lecturer_fname, l.last_name as lecturer_lname
            FROM courses c
            INNER JOIN attendance_sessions ats ON c.id = ats.course_id
            LEFT JOIN attendance_records ar ON ats.id = ar.session_id AND ar.student_id = ?
            LEFT JOIN lecturers l ON ats.lecturer_id = l.id
            WHERE c.id = ? AND c.option_id = ? AND c.year = ?
            ORDER BY ats.session_date DESC, ats.start_time DESC
        ");
        $stmt->execute([$student['id'], $selected_course_id, $student['option_id'], $student['year_level']]);
        $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get attendance for all courses student is enrolled in
        // Logic: 1. Get all courses for student's option
        //        2. Get all attendance sessions for those courses
        //        3. Check if student has attendance record for each session
        $stmt = $pdo->prepare("
            SELECT
                c.course_name, c.course_code, c.id as course_id,
                ats.id as session_id, ats.session_date, ats.start_time, ats.end_time,
                ar.status, ar.recorded_at,
                l.first_name as lecturer_fname, l.last_name as lecturer_lname
            FROM courses c
            INNER JOIN attendance_sessions ats ON c.id = ats.course_id
            LEFT JOIN attendance_records ar ON ats.id = ar.session_id AND ar.student_id = ?
            LEFT JOIN lecturers l ON ats.lecturer_id = l.id
            WHERE c.option_id = ? AND c.year = ?
            ORDER BY ats.session_date DESC, ats.start_time DESC
        ");
        $stmt->execute([$student['id'], $student['option_id'], $student['year_level']]);
        $attendance_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Calculate summary statistics
    $course_stats = [];
    foreach ($attendance_data as $record) {
        $course_key = $record['course_code'];
        if (!isset($course_stats[$course_key])) {
            $course_stats[$course_key] = [
                'course_name' => $record['course_name'],
                'course_code' => $record['course_code'],
                'total_sessions' => 0,
                'present_count' => 0,
                'absent_count' => 0
            ];
        }

        // Only count sessions where student has a status (present/absent)
        // NULL status means session exists but student wasn't marked
        if ($record['status'] !== null) {
            $course_stats[$course_key]['total_sessions']++;
            $summary_stats['total_sessions']++;
            
            if ($record['status'] === 'present') {
                $course_stats[$course_key]['present_count']++;
                $summary_stats['present_count']++;
            } elseif ($record['status'] === 'absent') {
                $course_stats[$course_key]['absent_count']++;
                $summary_stats['absent_count']++;
            }
        }
    }

    // Calculate percentages and identify courses under 85%
    foreach ($course_stats as $code => $stats) {
        $percentage = $stats['total_sessions'] > 0 ?
            round(($stats['present_count'] / $stats['total_sessions']) * 100, 1) : 0;

        $course_stats[$code]['percentage'] = $percentage;

        if ($percentage < 85 && $percentage > 0) {
            $summary_stats['courses_under_85'][] = [
                'course_name' => $stats['course_name'],
                'course_code' => $stats['course_code'],
                'percentage' => $percentage
            ];
        }
    }

    if ($summary_stats['total_sessions'] > 0) {
        $summary_stats['attendance_percentage'] =
            round(($summary_stats['present_count'] / $summary_stats['total_sessions']) * 100, 1);
    }

} catch (Exception $e) {
    error_log("Attendance data error: " . $e->getMessage());
    $attendance_data = [];
}

$page_title = "Attendance Records";
$current_page = "attendance-records";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark-blue: #1e3a8a;
            --secondary-dark-blue: #1e40af;
            --light-blue: #dbeafe;
            --accent-blue: #3b82f6;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --success-color: #16a34a;
            --warning-color: #d97706;
            --danger-color: #dc2626;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light-blue) 0%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--text-dark);
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }

        .topbar {
            margin-left: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            border-bottom: 1px solid rgba(30, 58, 138, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.08);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.05);
            border: 1px solid rgba(30, 58, 138, 0.1);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stat-primary .stat-icon {
            background: linear-gradient(135deg, var(--primary-dark-blue) 0%, var(--secondary-dark-blue) 100%);
            color: white;
        }

        .stat-success .stat-icon {
            background: linear-gradient(135deg, var(--success-color) 0%, #15803d 100%);
            color: white;
        }

        .stat-warning .stat-icon {
            background: linear-gradient(135deg, var(--warning-color) 0%, #92400e 100%);
            color: white;
        }

        .stat-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-content p {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.05);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .form-select, .form-control {
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            padding: 10px 12px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary-dark-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            outline: none;
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-dark-blue) 0%, var(--secondary-dark-blue) 100%);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.05);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: none;
            font-weight: 600;
            color: var(--text-dark);
            padding: 16px;
            font-size: 0.9rem;
        }

        .table tbody td {
            padding: 16px;
            border-top: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-present {
            background: rgba(22, 163, 74, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(22, 163, 74, 0.2);
        }

        .status-absent {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .status-excused {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(217, 119, 6, 0.2);
        }

        .courses-under-85 {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.05);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .warning-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .warning-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--warning-color) 0%, #92400e 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .footer {
            margin-left: 280px;
            text-align: center;
            padding: 20px;
            border-top: 1px solid rgba(30, 58, 138, 0.1);
            background: white;
        }

        @media (max-width: 768px) {
            .main-content, .topbar, .footer {
                margin-left: 0;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card {
                flex-direction: column;
                text-align: center;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Student Sidebar -->
    <?php include_once 'includes/students-sidebar.php'; ?>

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-calendar-check me-2"></i><?php echo $page_title; ?>
            </h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <small class="text-muted d-block">Welcome back</small>
                <span class="fw-semibold"><?php echo htmlspecialchars($student['first_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="mb-4">
            <h2 class="mb-1"><i class="fas fa-calendar-check me-2"></i><?php echo $page_title; ?></h2>
            <p class="text-muted mb-0">View your attendance records across all courses</p>
        </div>

        <!-- Statistics Overview -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-chart-line text-white fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 small">Overall Attendance</h6>
                                <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars((string)$summary_stats['attendance_percentage']); ?>%</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <i class="fas fa-check-circle text-white fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 small">Present Sessions</h6>
                                <h3 class="mb-0 fw-bold text-success"><?php echo htmlspecialchars((string)$summary_stats['present_count']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                <i class="fas fa-times-circle text-white fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 small">Absent Sessions</h6>
                                <h3 class="mb-0 fw-bold text-danger"><?php echo htmlspecialchars((string)$summary_stats['absent_count']); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <i class="fas fa-book text-white fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 small">Total Courses</h6>
                                <h3 class="mb-0 fw-bold"><?php echo count($courses); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Courses Alert -->
        <?php if (count($courses) == 0): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>No Courses Found!</strong> 
            There are currently no courses available for <strong><?php echo htmlspecialchars($student['option_name']); ?> - Year <?php echo htmlspecialchars((string)$student['year_level']); ?></strong>.
            Please contact your academic advisor or administrator.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" class="filter-row">
                <div>
                    <label class="form-label fw-bold">Course Filter</label>
                    <select name="course_id" class="form-select">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"
                                    <?php echo ($selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_name'] . ' (' . $course['course_code'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="form-label fw-bold">View Type</label>
                    <select name="view" class="form-select">
                        <option value="all" <?php echo ($view_type == 'all') ? 'selected' : ''; ?>>All Records</option>
                        <option value="under_85" <?php echo ($view_type == 'under_85') ? 'selected' : ''; ?>>Courses Under 85%</option>
                    </select>
                </div>

                <div class="d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Attendance Records Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Course</th>
                        <th>Lecturer</th>
                        <th>Status</th>
                        <th>Recorded At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendance_data)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Attendance Records Found</h5>
                                <p class="text-muted mb-0">Try adjusting your filters or check back later</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendance_data as $record): ?>
                            <?php
                            $status_class = match($record['status']) {
                                'present' => 'status-present',
                                'absent' => 'status-absent',
                                'excused' => 'status-excused',
                                default => 'status-absent'
                            };

                            $status_text = ucfirst($record['status'] ?? 'absent');
                            $lecturer_name = trim(($record['lecturer_fname'] ?? '') . ' ' . ($record['lecturer_lname'] ?? ''));
                            if (empty($lecturer_name)) $lecturer_name = 'Not Assigned';
                            ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($record['session_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($record['start_time'])) . ' - ' . date('H:i', strtotime($record['end_time'])); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($record['course_code']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['course_name']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($lecturer_name); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($record['recorded_at']): ?>
                                        <?php echo date('M d, H:i', strtotime($record['recorded_at'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Courses Under 85% Alert -->
        <?php if (!empty($summary_stats['courses_under_85'])): ?>
            <div class="courses-under-85">
                <div class="warning-header">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <h5 class="mb-1 text-warning">Attention Required</h5>
                        <p class="mb-0 text-muted">Courses with attendance below 85% minimum rate</p>
                    </div>
                </div>
                <div class="row g-3">
                    <?php foreach ($summary_stats['courses_under_85'] as $course): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="alert alert-warning border-warning">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-book me-3 text-warning"></i>
                                    <div>
                                        <strong><?php echo htmlspecialchars($course['course_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($course['course_name']); ?></small><br>
                                        <span class="badge bg-warning text-dark"><?php echo $course['percentage']; ?>% attendance</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div>&copy; 2025 Rwanda Polytechnic | Student Management System</div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.student-sidebar');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
        }

        // Auto-hide mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.student-sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768 && sidebar && toggle) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
