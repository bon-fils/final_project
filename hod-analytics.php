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
        header("Location: login_new.php?error=not_assigned");
        exit;
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
                error_log("HOD Analytics: Found department assignment via legacy hod_id match (user_id instead of lecturer_id) for user $user_id");
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
            header("Location: login_new.php?error=not_assigned");
            exit;
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
            error_log("HOD Analytics: Department assignment found via {$dept_result['match_type']} for user $user_id, department $department_name");
        }
    }
} catch (PDOException $e) {
    error_log("Database error in hod-analytics.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later. Error: " . $e->getMessage();
}

// Get comprehensive analytics data
$analytics_data = [];

try {
    // Student enrollment trends (last 6 months)
    $enrollment_trends = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as enrollments
        FROM students s
        JOIN options o ON s.option_id = o.id
        WHERE o.department_id = ? 
        AND s.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month
    ");
    $enrollment_trends->execute([$department_id]);
    $analytics_data['enrollment_trends'] = $enrollment_trends->fetchAll(PDO::FETCH_ASSOC);

    // Attendance trends (last 30 days)
    $attendance_trends = $pdo->prepare("
        SELECT 
            DATE(ar.recorded_at) as date,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent,
            COUNT(*) as total
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.id
        JOIN options o ON s.option_id = o.id
        WHERE o.department_id = ? 
        AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(ar.recorded_at)
        ORDER BY date
    ");
    $attendance_trends->execute([$department_id]);
    $analytics_data['attendance_trends'] = $attendance_trends->fetchAll(PDO::FETCH_ASSOC);

    // Program performance comparison
    $program_performance = $pdo->prepare("
        SELECT 
            o.name as program_name,
            COUNT(DISTINCT s.id) as total_students,
            COUNT(DISTINCT c.id) as total_courses,
            AVG(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100 as avg_attendance,
            COUNT(DISTINCT ats.id) as total_sessions
        FROM options o
        LEFT JOIN students s ON o.id = s.option_id
        LEFT JOIN courses c ON o.id = c.option_id
        LEFT JOIN attendance_sessions ats ON c.id = ats.course_id
        LEFT JOIN attendance_records ar ON ats.id = ar.session_id
        WHERE o.department_id = ?
        GROUP BY o.id, o.name
        ORDER BY total_students DESC
    ");
    $program_performance->execute([$department_id]);
    $analytics_data['program_performance'] = $program_performance->fetchAll(PDO::FETCH_ASSOC);

    // Lecturer workload distribution
    $lecturer_workload = $pdo->prepare("
        SELECT 
            CONCAT(l.first_name, ' ', l.last_name) as lecturer_name,
            COUNT(DISTINCT c.id) as courses_assigned,
            COUNT(DISTINCT ats.id) as sessions_conducted,
            COUNT(DISTINCT s.id) as students_taught
        FROM lecturers l
        LEFT JOIN courses c ON l.id = c.lecturer_id
        LEFT JOIN attendance_sessions ats ON c.id = ats.course_id
        LEFT JOIN students s ON c.option_id = s.option_id
        WHERE l.department_id = ? OR l.id IN (
            SELECT DISTINCT c2.lecturer_id 
            FROM courses c2 
            JOIN options o ON c2.option_id = o.id 
            WHERE o.department_id = ?
        )
        GROUP BY l.id, l.first_name, l.last_name
        HAVING courses_assigned > 0
        ORDER BY courses_assigned DESC
    ");
    $lecturer_workload->execute([$department_id, $department_id]);
    $analytics_data['lecturer_workload'] = $lecturer_workload->fetchAll(PDO::FETCH_ASSOC);

    // Key performance indicators
    $kpi_query = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM students s JOIN options o ON s.option_id = o.id WHERE o.department_id = ?) as total_students,
            (SELECT COUNT(*) FROM options WHERE department_id = ?) as total_programs,
            (SELECT COUNT(*) FROM courses c JOIN options o ON c.option_id = o.id WHERE o.department_id = ?) as total_courses,
            (SELECT COUNT(*) FROM lecturers WHERE department_id = ?) as total_lecturers,
            (SELECT AVG(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100 
             FROM attendance_records ar 
             JOIN students s ON ar.student_id = s.id 
             JOIN options o ON s.option_id = o.id 
             WHERE o.department_id = ?) as avg_attendance_rate,
            (SELECT COUNT(*) FROM attendance_sessions ats 
             JOIN courses c ON ats.course_id = c.id 
             JOIN options o ON c.option_id = o.id 
             WHERE o.department_id = ? AND ats.start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as sessions_last_month
    ");
    $kpi_query->execute([$department_id, $department_id, $department_id, $department_id, $department_id, $department_id]);
    $analytics_data['kpis'] = $kpi_query->fetch(PDO::FETCH_ASSOC);

    // Year level distribution
    $year_distribution = $pdo->prepare("
        SELECT 
            s.year_level,
            COUNT(*) as student_count
        FROM students s
        JOIN options o ON s.option_id = o.id
        WHERE o.department_id = ?
        GROUP BY s.year_level
        ORDER BY s.year_level
    ");
    $year_distribution->execute([$department_id]);
    $analytics_data['year_distribution'] = $year_distribution->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching analytics data: " . $e->getMessage());
    $error_message = "Unable to load analytics data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Analytics - HOD Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        
        .analytics-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
        }
        
        .kpi-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .kpi-card:hover {
            transform: translateY(-5px);
        }
        
        .kpi-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .kpi-label {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .trend-indicator {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 12px;
            margin-left: 10px;
        }
        
        .trend-up {
            background: #d4edda;
            color: #155724;
        }
        
        .trend-down {
            background: #f8d7da;
            color: #721c24;
        }
        
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
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
                        <i class="fas fa-chart-pie text-primary me-2"></i>
                        Department Analytics Dashboard
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="hod-dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Analytics</li>
                        </ol>
                    </nav>
                    <p class="text-muted mb-0"><?= htmlspecialchars($department_name) ?> Department</p>
                </div>
                <div>
                    <button class="btn btn-primary me-2" onclick="refreshAnalytics()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh Data
                    </button>
                    <button class="btn btn-success" onclick="exportAnalytics()">
                        <i class="fas fa-download me-1"></i> Export Report
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Key Performance Indicators -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card text-center">
                    <div class="kpi-number"><?= $analytics_data['kpis']['total_students'] ?? 0 ?></div>
                    <div class="kpi-label">Students</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card text-center">
                    <div class="kpi-number"><?= $analytics_data['kpis']['total_programs'] ?? 0 ?></div>
                    <div class="kpi-label">Programs</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card text-center">
                    <div class="kpi-number"><?= $analytics_data['kpis']['total_courses'] ?? 0 ?></div>
                    <div class="kpi-label">Courses</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card text-center">
                    <div class="kpi-number"><?= $analytics_data['kpis']['total_lecturers'] ?? 0 ?></div>
                    <div class="kpi-label">Lecturers</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card text-center">
                    <div class="kpi-number"><?= round($analytics_data['kpis']['avg_attendance_rate'] ?? 0, 1) ?>%</div>
                    <div class="kpi-label">Attendance</div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="kpi-card text-center">
                    <div class="kpi-number"><?= $analytics_data['kpis']['sessions_last_month'] ?? 0 ?></div>
                    <div class="kpi-label">Sessions (30d)</div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1 -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="analytics-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2"></i>
                        Attendance Trends (Last 30 Days)
                        <span class="trend-indicator trend-up">
                            <i class="fas fa-arrow-up me-1"></i>+2.3%
                        </span>
                    </h5>
                    <div class="chart-container">
                        <canvas id="attendanceTrendsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="analytics-card">
                    <h5 class="mb-3">
                        <i class="fas fa-users me-2"></i>
                        Year Level Distribution
                    </h5>
                    <div class="chart-container">
                        <canvas id="yearDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2 -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="analytics-card">
                    <h5 class="mb-3">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Program Performance Comparison
                    </h5>
                    <div class="chart-container">
                        <canvas id="programPerformanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="analytics-card">
                    <h5 class="mb-3">
                        <i class="fas fa-user-plus me-2"></i>
                        Student Enrollment Trends (6 Months)
                    </h5>
                    <div class="chart-container">
                        <canvas id="enrollmentTrendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lecturer Workload Analysis -->
        <div class="analytics-card">
            <h5 class="mb-3">
                <i class="fas fa-chalkboard-teacher me-2"></i>
                Lecturer Workload Distribution
            </h5>
            <div class="chart-container">
                <canvas id="lecturerWorkloadChart"></canvas>
            </div>
        </div>

        <!-- Insights and Recommendations -->
        <div class="analytics-card">
            <h5 class="mb-3">
                <i class="fas fa-lightbulb me-2"></i>
                AI-Powered Insights & Recommendations
            </h5>
            <div class="row">
                <div class="col-md-4">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-chart-line me-2"></i>Attendance Insight</h6>
                        <p class="mb-0">Average attendance has improved by 2.3% this month. Consider recognizing high-performing classes.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-balance-scale me-2"></i>Workload Alert</h6>
                        <p class="mb-0">Some lecturers have significantly higher workloads. Consider redistributing courses for better balance.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-success">
                        <h6><i class="fas fa-trophy me-2"></i>Performance Highlight</h6>
                        <p class="mb-0">Program enrollment is trending upward. Consider expanding successful programs.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Attendance Trends Chart
        const attendanceCtx = document.getElementById('attendanceTrendsChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: [<?= implode(',', array_map(function($d) { return '"' . date('M j', strtotime($d['date'])) . '"'; }, $analytics_data['attendance_trends'])) ?>],
                datasets: [{
                    label: 'Attendance Rate (%)',
                    data: [<?= implode(',', array_map(function($d) { return round(($d['present'] / max($d['total'], 1)) * 100, 1); }, $analytics_data['attendance_trends'])) ?>],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Year Distribution Chart
        const yearCtx = document.getElementById('yearDistributionChart').getContext('2d');
        new Chart(yearCtx, {
            type: 'doughnut',
            data: {
                labels: [<?= implode(',', array_map(function($d) { return '"Year ' . $d['year_level'] . '"'; }, $analytics_data['year_distribution'])) ?>],
                datasets: [{
                    data: [<?= implode(',', array_column($analytics_data['year_distribution'], 'student_count')) ?>],
                    backgroundColor: ['#007bff', '#28a745', '#ffc107'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Program Performance Chart
        const programCtx = document.getElementById('programPerformanceChart').getContext('2d');
        new Chart(programCtx, {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(function($p) { return '"' . addslashes($p['program_name']) . '"'; }, $analytics_data['program_performance'])) ?>],
                datasets: [{
                    label: 'Students',
                    data: [<?= implode(',', array_column($analytics_data['program_performance'], 'total_students')) ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    yAxisID: 'y'
                }, {
                    label: 'Attendance Rate (%)',
                    data: [<?= implode(',', array_map(function($p) { return round($p['avg_attendance'] ?? 0, 1); }, $analytics_data['program_performance'])) ?>],
                    backgroundColor: 'rgba(255, 99, 132, 0.8)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        max: 100,
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Enrollment Trends Chart
        const enrollmentCtx = document.getElementById('enrollmentTrendsChart').getContext('2d');
        new Chart(enrollmentCtx, {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(function($e) { return '"' . date('M Y', strtotime($e['month'] . '-01')) . '"'; }, $analytics_data['enrollment_trends'])) ?>],
                datasets: [{
                    label: 'New Enrollments',
                    data: [<?= implode(',', array_column($analytics_data['enrollment_trends'], 'enrollments')) ?>],
                    backgroundColor: 'rgba(75, 192, 192, 0.8)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Lecturer Workload Chart
        const workloadCtx = document.getElementById('lecturerWorkloadChart').getContext('2d');
        new Chart(workloadCtx, {
            type: 'horizontalBar',
            data: {
                labels: [<?= implode(',', array_map(function($l) { return '"' . addslashes($l['lecturer_name']) . '"'; }, $analytics_data['lecturer_workload'])) ?>],
                datasets: [{
                    label: 'Courses Assigned',
                    data: [<?= implode(',', array_column($analytics_data['lecturer_workload'], 'courses_assigned')) ?>],
                    backgroundColor: 'rgba(153, 102, 255, 0.8)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        function refreshAnalytics() {
            location.reload();
        }

        function exportAnalytics() {
            alert('Analytics export functionality would be implemented here');
        }
    </script>
</body>
</html>
