<?php
require_once "config.php";
session_start();
require_once "session_check.php";
require_once "includes/hod_auth_helper.php";

// Verify HOD access and get department information
$auth_result = verifyHODAccess($pdo, $_SESSION['user_id']);

if (!$auth_result['success']) {
    // Show error message instead of redirect for better UX
    $error_message = $auth_result['error_message'];
    $department_name = 'No Department Assigned';
    $department_id = null;
    $user = ['name' => $_SESSION['username'] ?? 'User'];
} else {
    $department_name = $auth_result['department_name'];
    $department_id = $auth_result['department_id'];
    $user = $auth_result['user'];
    $lecturer_id = $auth_result['lecturer_id'];
}

// Get attendance statistics with enhanced backend processing
$attendance_stats = [];
$program_stats = [];
$sessions = [];
$trends_data = [];
$alerts = [];

if ($department_id) {
    try {
        // Overall department attendance with enhanced metrics - Fixed JOIN
        $stmt = $pdo->prepare("
            SELECT
                COUNT(ar.id) as total_records,
                COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
                COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
                COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
                COUNT(DISTINCT DATE(ar.recorded_at)) as total_days,
                COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN DATE(ar.recorded_at) END) as present_days,
                COUNT(CASE WHEN DATE(ar.recorded_at) = CURDATE() THEN 1 END) as today_total,
                COUNT(CASE WHEN ar.status = 'present' AND DATE(ar.recorded_at) = CURDATE() THEN 1 END) as today_present,
                COUNT(CASE WHEN ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_total,
                COUNT(CASE WHEN ar.status = 'present' AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_present,
                COUNT(CASE WHEN ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as month_total,
                COUNT(CASE WHEN ar.status = 'present' AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as month_present,
                COUNT(CASE WHEN ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) as quarter_total,
                COUNT(CASE WHEN ar.status = 'present' AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 90 DAY) THEN 1 END) as quarter_present,
                MIN(ar.recorded_at) as first_record_date,
                MAX(ar.recorded_at) as last_record_date
            FROM attendance_records ar
            JOIN students s ON ar.student_id = s.id
            JOIN options o ON s.option_id = o.id
            WHERE o.department_id = ?
        ");
        $stmt->execute([$department_id]);
        $attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calculate enhanced percentages and metrics
    $attendance_stats['overall_rate'] = $attendance_stats['total_records'] > 0 ?
        round(($attendance_stats['present_count'] / $attendance_stats['total_records']) * 100, 1) : 0;

    $attendance_stats['today_rate'] = $attendance_stats['today_total'] > 0 ?
        round(($attendance_stats['today_present'] / $attendance_stats['today_total']) * 100, 1) : 0;

    $attendance_stats['week_rate'] = $attendance_stats['week_total'] > 0 ?
        round(($attendance_stats['week_present'] / $attendance_stats['week_total']) * 100, 1) : 0;

    $attendance_stats['month_rate'] = $attendance_stats['month_total'] > 0 ?
        round(($attendance_stats['month_present'] / $attendance_stats['month_total']) * 100, 1) : 0;

    $attendance_stats['quarter_rate'] = $attendance_stats['quarter_total'] > 0 ?
        round(($attendance_stats['quarter_present'] / $attendance_stats['quarter_total']) * 100, 1) : 0;

    // Calculate consistency metrics
    $attendance_stats['consistency_rate'] = $attendance_stats['total_days'] > 0 ?
        round(($attendance_stats['present_days'] / $attendance_stats['total_days']) * 100, 1) : 0;

    // Get attendance by program with enhanced metrics
    $program_attendance = $pdo->prepare("
        SELECT
            o.id as option_id,
            o.name as program_name,
            COUNT(DISTINCT s.reg_no) as total_students,
            COUNT(ar.id) as total_records,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_count,
            COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_count,
            ROUND((COUNT(CASE WHEN ar.status = 'present' THEN 1 END) / NULLIF(COUNT(ar.id), 0)) * 100, 1) as attendance_rate,
            ROUND((COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) / NULLIF(COUNT(ar.id), 0)) * 100, 1) as absence_rate,
            ROUND((COUNT(CASE WHEN ar.status = 'late' THEN 1 END) / NULLIF(COUNT(ar.id), 0)) * 100, 1) as late_rate,
            COUNT(DISTINCT DATE(ar.recorded_at)) as active_days,
            MAX(ar.recorded_at) as last_attendance
        FROM options o
        LEFT JOIN students s ON o.id = s.option_id
        LEFT JOIN attendance_records ar ON s.reg_no = ar.student_id
        WHERE o.department_id = ?
        GROUP BY o.id, o.name
        ORDER BY attendance_rate DESC, total_students DESC
    ");
    $program_attendance->execute([$department_id]);
    $program_stats = $program_attendance->fetchAll(PDO::FETCH_ASSOC);

    // Get attendance trends over time (last 30 days)
    $trends_stmt = $pdo->prepare("
        SELECT
            DATE(ar.recorded_at) as attendance_date,
            COUNT(ar.id) as total_records,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count,
            ROUND((COUNT(CASE WHEN ar.status = 'present' THEN 1 END) / COUNT(ar.id)) * 100, 1) as attendance_rate
        FROM attendance_records ar
        JOIN students s ON ar.student_id = s.reg_no
        JOIN options o ON s.option_id = o.id
        WHERE o.department_id = ? AND ar.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(ar.recorded_at)
        ORDER BY attendance_date
    ");
    $trends_stmt->execute([$department_id]);
    $trends_data = $trends_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent attendance sessions with enhanced details
    $recent_sessions = $pdo->prepare("
        SELECT
            ats.id, ats.session_date, ats.start_time, ats.end_time, ats.biometric_method,
            c.id as course_id, c.name as course_name, c.course_code,
            u.first_name as lecturer_fname, u.last_name as lecturer_lname,
            COUNT(DISTINCT ar.student_id) as total_students,
            COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_students,
            COUNT(CASE WHEN ar.status = 'absent' THEN 1 END) as absent_students,
            COUNT(CASE WHEN ar.status = 'late' THEN 1 END) as late_students,
            ROUND((COUNT(CASE WHEN ar.status = 'present' THEN 1 END) / NULLIF(COUNT(ar.id), 0)) * 100, 1) as attendance_rate,
            TIMESTAMPDIFF(MINUTE, ats.start_time, COALESCE(ats.end_time, NOW())) as duration_minutes
        FROM attendance_sessions ats
        LEFT JOIN courses c ON ats.course_id = c.id
        LEFT JOIN lecturers l ON c.lecturer_id = l.id
        LEFT JOIN users u ON l.user_id = u.id
        LEFT JOIN attendance_records ar ON ats.id = ar.session_id
        LEFT JOIN students s ON ar.student_id = s.reg_no
        LEFT JOIN options o ON s.option_id = o.id
        WHERE (o.department_id = ? OR o.department_id IS NULL)
        GROUP BY ats.id, ats.session_date, ats.start_time, ats.end_time, ats.biometric_method,
                 c.id, c.name, c.course_code, u.first_name, u.last_name
        ORDER BY ats.start_time DESC
        LIMIT 15
    ");
    $recent_sessions->execute([$department_id]);
    $sessions = $recent_sessions->fetchAll(PDO::FETCH_ASSOC);

    // Generate attendance alerts and insights
    $alerts = [];

    // Low attendance alert
    if ($attendance_stats['overall_rate'] < 70) {
        $alerts[] = [
            'type' => 'warning',
            'title' => 'Low Overall Attendance',
            'message' => "Department attendance rate is {$attendance_stats['overall_rate']}%, below the 70% threshold.",
            'icon' => 'fas fa-exclamation-triangle'
        ];
    }

    // Programs with low attendance
    foreach ($program_stats as $program) {
        if ($program['attendance_rate'] < 60 && $program['total_records'] > 10) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Critical: Low Program Attendance',
                'message' => "{$program['program_name']} has {$program['attendance_rate']}% attendance rate.",
                'icon' => 'fas fa-times-circle'
            ];
        }
    }

    // Recent improvement/deterioration
    if (count($trends_data) >= 7) {
        $recent_week = array_slice($trends_data, -7);
        $previous_week = array_slice($trends_data, -14, 7);

        $recent_avg = array_sum(array_column($recent_week, 'attendance_rate')) / count($recent_week);
        $previous_avg = count($previous_week) > 0 ? array_sum(array_column($previous_week, 'attendance_rate')) / count($previous_week) : $recent_avg;

        $change = $recent_avg - $previous_avg;
        if (abs($change) > 5) {
            $alerts[] = [
                'type' => $change > 0 ? 'success' : 'warning',
                'title' => $change > 0 ? 'Attendance Improving' : 'Attendance Declining',
                'message' => "Recent attendance " . ($change > 0 ? 'increased' : 'decreased') . " by " . abs(round($change, 1)) . "% compared to previous week.",
                'icon' => $change > 0 ? 'fas fa-arrow-up' : 'fas fa-arrow-down'
            ];
        }
    }

    // Sessions with no attendance
    $empty_sessions = array_filter($sessions, function($session) {
        return $session['total_students'] == 0;
    });

    if (count($empty_sessions) > 0) {
        $alerts[] = [
            'type' => 'info',
            'title' => 'Sessions Without Attendance',
            'message' => count($empty_sessions) . " recent session(s) have no attendance records.",
            'icon' => 'fas fa-info-circle'
        ];
    }

} catch (PDOException $e) {
    error_log("Error fetching attendance data in hod-attendance-overview.php: " . $e->getMessage());
    $error_message = "Unable to load attendance data. Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Overview - HOD Dashboard</title>
    
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
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-item {
            text-align: center;
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
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        .session-item {
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-radius: 0 8px 8px 0;
        }
        
        .session-item.active {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .session-item.completed {
            border-left-color: #6c757d;
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
                        <i class="fas fa-calendar-check text-success me-2"></i>
                        Attendance Overview
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="hod-dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Attendance</li>
                        </ol>
                    </nav>
                    <p class="text-muted mb-0"><?= htmlspecialchars($department_name) ?> Department</p>
                </div>
                <div>
                    <button class="btn btn-primary me-2" onclick="exportAttendanceReport()">
                        <i class="fas fa-download me-1"></i> Export Report
                    </button>
                    <button class="btn btn-success" onclick="createAttendanceSession()">
                        <i class="fas fa-plus me-1"></i> New Session
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

        <!-- Attendance Statistics -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-primary"><?= $attendance_stats['overall_rate'] ?? 0 ?>%</div>
                        <div class="stat-label">Overall Attendance</div>
                        <small class="text-muted"><?= number_format($attendance_stats['present_count'] ?? 0) ?> / <?= number_format($attendance_stats['total_records'] ?? 0) ?> records</small>
                        <div class="mt-2">
                            <small class="text-info">
                                <i class="fas fa-calendar-check me-1"></i>Consistency: <?= $attendance_stats['consistency_rate'] ?? 0 ?>%
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-success"><?= $attendance_stats['today_rate'] ?? 0 ?>%</div>
                        <div class="stat-label">Today's Attendance</div>
                        <small class="text-muted"><?= $attendance_stats['today_present'] ?? 0 ?> / <?= $attendance_stats['today_total'] ?? 0 ?> present</small>
                        <div class="mt-2">
                            <small class="text-success">
                                <i class="fas fa-clock me-1"></i>Live data
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-info"><?= $attendance_stats['week_rate'] ?? 0 ?>%</div>
                        <div class="stat-label">This Week</div>
                        <small class="text-muted"><?= $attendance_stats['week_present'] ?? 0 ?> / <?= $attendance_stats['week_total'] ?? 0 ?> present</small>
                        <div class="mt-2">
                            <small class="text-info">
                                <i class="fas fa-chart-line me-1"></i>7-day trend
                            </small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-warning"><?= $attendance_stats['month_rate'] ?? 0 ?>%</div>
                        <div class="stat-label">This Month</div>
                        <small class="text-muted"><?= $attendance_stats['month_present'] ?? 0 ?> / <?= $attendance_stats['month_total'] ?? 0 ?> present</small>
                        <div class="mt-2">
                            <small class="text-warning">
                                <i class="fas fa-calendar-alt me-1"></i>30-day period
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Alerts and Insights -->
        <?php if (!empty($alerts)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-card">
                    <h5 class="mb-3">
                        <i class="fas fa-bell me-2"></i>
                        Attendance Insights & Alerts
                    </h5>
                    <div class="row">
                        <?php foreach ($alerts as $alert): ?>
                        <div class="col-md-6 mb-3">
                            <div class="alert alert-<?= $alert['type'] ?> d-flex align-items-center">
                                <i class="<?= $alert['icon'] ?> me-2"></i>
                                <div>
                                    <strong><?= htmlspecialchars($alert['title']) ?></strong><br>
                                    <small><?= htmlspecialchars($alert['message']) ?></small>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Charts and Program Stats -->
        <div class="row mb-4">
            <!-- Attendance Breakdown Chart -->
            <div class="col-lg-6 mb-3">
                <div class="stats-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>
                        Attendance Breakdown
                    </h5>
                    <div class="chart-container">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Program Performance -->
            <div class="col-lg-6 mb-3">
                <div class="stats-card">
                    <h5 class="mb-3">
                        <i class="fas fa-graduation-cap me-2"></i>
                        Program Performance
                    </h5>
                    <div class="chart-container">
                        <canvas id="programChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Program Statistics Table -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-card">
                    <h5 class="mb-3">
                        <i class="fas fa-list me-2"></i>
                        Program Attendance Analysis
                    </h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Program</th>
                                    <th>Students</th>
                                    <th>Active Days</th>
                                    <th>Attendance Rate</th>
                                    <th>Absence Rate</th>
                                    <th>Late Rate</th>
                                    <th>Last Activity</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($program_stats as $program): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($program['program_name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= number_format($program['total_students']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= number_format($program['active_days']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $program['attendance_rate'] >= 80 ? 'success' : ($program['attendance_rate'] >= 60 ? 'warning' : 'danger') ?>">
                                            <?= $program['attendance_rate'] ?? 0 ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger"><?= $program['absence_rate'] ?? 0 ?>%</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark"><?= $program['late_rate'] ?? 0 ?>%</span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php if ($program['last_attendance']): ?>
                                                <i class="fas fa-clock me-1"></i>
                                                <?= date('M j, Y', strtotime($program['last_attendance'])) ?>
                                            <?php else: ?>
                                                <i class="fas fa-minus me-1"></i>No records
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-<?= $program['attendance_rate'] >= 80 ? 'success' : ($program['attendance_rate'] >= 60 ? 'warning' : 'danger') ?>"
                                                  style="width: <?= $program['attendance_rate'] ?? 0 ?>%"></div>
                                        </div>
                                        <small class="text-muted mt-1 d-block">
                                            <?= number_format($program['present_count']) ?>/<?= number_format($program['total_records']) ?> records
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Trends Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2"></i>
                        Attendance Trends (Last 30 Days)
                    </h5>
                    <div class="chart-container">
                        <canvas id="trendsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Sessions -->
        <div class="row">
            <div class="col-12">
                <div class="stats-card">
                    <h5 class="mb-3">
                        <i class="fas fa-clock me-2"></i>
                        Recent Attendance Sessions
                    </h5>
                    <?php if (empty($sessions)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No recent attendance sessions found.</p>
                            <button class="btn btn-primary" onclick="createAttendanceSession()">
                                <i class="fas fa-plus me-1"></i> Create First Session
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($sessions as $session): ?>
                        <div class="session-item <?= $session['status'] ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($session['session_name']) ?></h6>
                                    <p class="mb-1 text-muted">
                                        <i class="fas fa-book me-1"></i>
                                        <?= htmlspecialchars($session['course_name'] ?? 'No course assigned') ?>
                                    </p>
                                    <p class="mb-1 text-muted">
                                        <i class="fas fa-user me-1"></i>
                                        <?= htmlspecialchars(($session['lecturer_fname'] ?? '') . ' ' . ($session['lecturer_lname'] ?? '')) ?>
                                    </p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= date('M j, Y g:i A', strtotime($session['start_time'])) ?>
                                        <?php if ($session['end_time']): ?>
                                            - <?= date('g:i A', strtotime($session['end_time'])) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="mb-2">
                                        <span class="badge bg-<?= $session['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($session['status']) ?>
                                        </span>
                                    </div>
                                    <div>
                                        <strong><?= $session['present_students'] ?> / <?= $session['total_students'] ?></strong>
                                        <small class="text-muted d-block">Present</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php
    // Prepare data for JavaScript to avoid PHP functions inside JS
    $program_labels = array_map(function($p) { return addslashes($p['program_name']); }, $program_stats);
    $program_rates = array_column($program_stats, 'attendance_rate');
    ?>
    
    <script>
        // Attendance Breakdown Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(attendanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [
                        <?= $attendance_stats['present_count'] ?? 0 ?>,
                        <?= $attendance_stats['absent_count'] ?? 0 ?>,
                        <?= $attendance_stats['late_count'] ?? 0 ?>
                    ],
                    backgroundColor: ['#28a745', '#dc3545', '#ffc107'],
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Program Performance Chart
        const programCtx = document.getElementById('programChart').getContext('2d');
        new Chart(programCtx, {
            type: 'bar',
            data: {
                labels: [<?= '"' . implode('","', $program_labels) . '"' ?>],
                datasets: [{
                    label: 'Attendance Rate (%)',
                    data: [<?= implode(',', $program_rates) ?>],
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
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

        // Attendance Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        const trendsData = <?= json_encode($trends_data) ?>;

        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(item => new Date(item.attendance_date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Attendance Rate (%)',
                    data: trendsData.map(item => item.attendance_rate),
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
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
                    },
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Attendance: ' + context.parsed.y + '%';
                            }
                        }
                    }
                }
            }
        });

        function exportAttendanceReport() {
            window.location.href = 'hod-export-attendance.php';
        }

        function createAttendanceSession() {
            window.location.href = 'attendance-session.php';
        }
    </script>
</body>
</html>
