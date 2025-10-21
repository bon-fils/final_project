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
                error_log("HOD Lecturer Performance: Found department assignment via legacy hod_id match (user_id instead of lecturer_id) for user $user_id");
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
            error_log("HOD Lecturer Performance: Department assignment found via {$dept_result['match_type']} for user $user_id, department $department_name");
        }
    }
} catch (PDOException $e) {
    error_log("Database error in hod-lecturer-performance.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later. Error: " . $e->getMessage();
}

// Get lecturer performance data
$lecturers_performance = [];
$stats = [];

if ($department_id) {
    try {
        // Get lecturers with performance metrics
        $stmt = $pdo->prepare("
            SELECT
                l.id, l.first_name, l.last_name, l.email, l.phone, l.hire_date,
                COUNT(DISTINCT c.id) as courses_assigned,
                COUNT(DISTINCT ats.id) as sessions_conducted,
                AVG(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100 as avg_attendance_rate,
                COUNT(DISTINCT s.id) as students_taught,
                DATEDIFF(NOW(), l.hire_date) as days_employed,
                l.status as employment_status
            FROM lecturers l
            LEFT JOIN courses c ON l.id = c.lecturer_id
            LEFT JOIN attendance_sessions ats ON c.id = ats.course_id
            LEFT JOIN attendance_records ar ON ats.id = ar.session_id
            LEFT JOIN students s ON c.option_id = s.option_id
            WHERE l.department_id = ? OR l.id IN (
                SELECT DISTINCT c2.lecturer_id
                FROM courses c2
                JOIN options o ON c2.option_id = o.id
                WHERE o.department_id = ?
            )
            GROUP BY l.id, l.first_name, l.last_name, l.email, l.phone, l.hire_date, l.status
            ORDER BY l.first_name, l.last_name
        ");
        $stmt->execute([$department_id, $department_id]);
        $lecturers_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate performance scores
        foreach ($lecturers_performance as &$lecturer) {
            $attendance_score = min(100, max(0, $lecturer['avg_attendance_rate'] ?? 0));
            $activity_score = min(100, ($lecturer['sessions_conducted'] ?? 0) * 2); // 2 points per session, max 100
            $workload_score = min(100, ($lecturer['courses_assigned'] ?? 0) * 20); // 20 points per course, max 100

            $lecturer['performance_score'] = round(($attendance_score * 0.4 + $activity_score * 0.3 + $workload_score * 0.3), 1);
            $lecturer['years_employed'] = round(($lecturer['days_employed'] ?? 0) / 365, 1);

            // Performance rating
            if ($lecturer['performance_score'] >= 80) {
                $lecturer['performance_rating'] = 'Excellent';
                $lecturer['rating_class'] = 'success';
            } elseif ($lecturer['performance_score'] >= 60) {
                $lecturer['performance_rating'] = 'Good';
                $lecturer['rating_class'] = 'info';
            } elseif ($lecturer['performance_score'] >= 40) {
                $lecturer['performance_rating'] = 'Average';
                $lecturer['rating_class'] = 'warning';
            } else {
                $lecturer['performance_rating'] = 'Needs Improvement';
                $lecturer['rating_class'] = 'danger';
            }
        }

        // Get summary statistics
        $total_lecturers = count($lecturers_performance);
        $avg_performance = $total_lecturers > 0 ? array_sum(array_column($lecturers_performance, 'performance_score')) / $total_lecturers : 0;
        $excellent_count = count(array_filter($lecturers_performance, function($l) { return $l['performance_score'] >= 80; }));
        $needs_improvement = count(array_filter($lecturers_performance, function($l) { return $l['performance_score'] < 40; }));

        $stats = [
            'total_lecturers' => $total_lecturers,
            'avg_performance' => round($avg_performance, 1),
            'excellent_performers' => $excellent_count,
            'needs_improvement' => $needs_improvement,
            'total_courses' => array_sum(array_column($lecturers_performance, 'courses_assigned')),
            'total_sessions' => array_sum(array_column($lecturers_performance, 'sessions_conducted'))
        ];

    } catch (PDOException $e) {
        error_log("Error fetching lecturer performance: " . $e->getMessage());
        $error_message = "Unable to load performance data.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Performance - HOD Dashboard</title>
    
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
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .performance-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
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
        
        .lecturer-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background: white;
        }
        
        .lecturer-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .performance-badge {
            font-size: 0.9rem;
            padding: 6px 12px;
            border-radius: 20px;
        }
        
        .performance-score {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
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
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Lecturer Performance Analytics
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="hod-dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="hod-manage-lecturers.php">Lecturers</a></li>
                            <li class="breadcrumb-item active">Performance</li>
                        </ol>
                    </nav>
                    <p class="text-muted mb-0"><?= htmlspecialchars($department_name ?? 'No Department Assigned') ?> Department</p>
                </div>
                <div>
                    <?php if ($department_id): ?>
                    <button class="btn btn-primary me-2" onclick="exportPerformanceReport()">
                        <i class="fas fa-download me-1"></i> Export Report
                    </button>
                    <button class="btn btn-success" onclick="scheduleReview()">
                        <i class="fas fa-calendar-plus me-1"></i> Schedule Review
                    </button>
                    <?php endif; ?>
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-primary"><?= $stats['total_lecturers'] ?></div>
                        <div class="stat-label">Total Lecturers</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-success"><?= $stats['avg_performance'] ?>%</div>
                        <div class="stat-label">Avg Performance</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-info"><?= $stats['excellent_performers'] ?></div>
                        <div class="stat-label">Excellent Performers</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-warning"><?= $stats['needs_improvement'] ?></div>
                        <div class="stat-label">Need Improvement</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Charts -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="performance-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-pie me-2"></i>
                        Performance Distribution
                    </h5>
                    <div class="chart-container">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="performance-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar me-2"></i>
                        Performance vs Experience
                    </h5>
                    <div class="chart-container">
                        <canvas id="experienceChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lecturer Performance List -->
        <div class="performance-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-users me-2"></i>
                    Individual Performance Metrics
                </h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="performanceFilter">
                        <option value="">All Performance Levels</option>
                        <option value="Excellent">Excellent (80%+)</option>
                        <option value="Good">Good (60-79%)</option>
                        <option value="Average">Average (40-59%)</option>
                        <option value="Needs Improvement">Needs Improvement (<40%)</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" id="searchLecturers" placeholder="Search lecturers...">
                </div>
            </div>
            
            <?php if (empty($lecturers_performance)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-line fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No Performance Data</h4>
                    <p class="text-muted">Performance metrics will appear here once lecturers start conducting classes.</p>
                </div>
            <?php else: ?>
                <div class="row" id="lecturersList">
                    <?php foreach ($lecturers_performance as $lecturer): ?>
                    <div class="col-lg-6 mb-3 lecturer-item" data-rating="<?= $lecturer['performance_rating'] ?>">
                        <div class="lecturer-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                        <?= strtoupper(substr($lecturer['first_name'], 0, 1) . substr($lecturer['last_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($lecturer['email']) ?></small>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?= $lecturer['years_employed'] ?> years experience
                                        </small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="performance-score text-<?= $lecturer['rating_class'] ?>">
                                        <?= $lecturer['performance_score'] ?>%
                                    </div>
                                    <span class="badge bg-<?= $lecturer['rating_class'] ?> performance-badge">
                                        <?= $lecturer['performance_rating'] ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-3">
                                    <div class="stat-number text-primary" style="font-size: 1.2rem;"><?= $lecturer['courses_assigned'] ?></div>
                                    <small class="text-muted">Courses</small>
                                </div>
                                <div class="col-3">
                                    <div class="stat-number text-success" style="font-size: 1.2rem;"><?= $lecturer['sessions_conducted'] ?></div>
                                    <small class="text-muted">Sessions</small>
                                </div>
                                <div class="col-3">
                                    <div class="stat-number text-info" style="font-size: 1.2rem;"><?= round($lecturer['avg_attendance_rate'] ?? 0, 1) ?>%</div>
                                    <small class="text-muted">Attendance</small>
                                </div>
                                <div class="col-3">
                                    <div class="stat-number text-warning" style="font-size: 1.2rem;"><?= $lecturer['students_taught'] ?></div>
                                    <small class="text-muted">Students</small>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?= $lecturer['rating_class'] ?>" 
                                         style="width: <?= $lecturer['performance_score'] ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="mt-3 d-flex justify-content-between">
                                <button class="btn btn-outline-primary btn-sm" onclick="viewLecturerDetails(<?= $lecturer['id'] ?>)">
                                    <i class="fas fa-eye me-1"></i> View Details
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="provideFeedback(<?= $lecturer['id'] ?>)">
                                    <i class="fas fa-comment me-1"></i> Feedback
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Performance Distribution Chart
        const performanceCtx = document.getElementById('performanceChart').getContext('2d');
        new Chart(performanceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent', 'Good', 'Average', 'Needs Improvement'],
                datasets: [{
                    data: [
                        <?= count(array_filter($lecturers_performance, function($l) { return $l['performance_score'] >= 80; })) ?>,
                        <?= count(array_filter($lecturers_performance, function($l) { return $l['performance_score'] >= 60 && $l['performance_score'] < 80; })) ?>,
                        <?= count(array_filter($lecturers_performance, function($l) { return $l['performance_score'] >= 40 && $l['performance_score'] < 60; })) ?>,
                        <?= count(array_filter($lecturers_performance, function($l) { return $l['performance_score'] < 40; })) ?>
                    ],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
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

        // Performance vs Experience Chart
        const experienceCtx = document.getElementById('experienceChart').getContext('2d');
        new Chart(experienceCtx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Performance vs Experience',
                    data: [
                        <?php foreach ($lecturers_performance as $lecturer): ?>
                        {
                            x: <?= $lecturer['years_employed'] ?>,
                            y: <?= $lecturer['performance_score'] ?>
                        },
                        <?php endforeach; ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.6)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: 'Years of Experience'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Performance Score (%)'
                        },
                        min: 0,
                        max: 100
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Filter functionality
        document.getElementById('performanceFilter').addEventListener('change', function() {
            filterLecturers();
        });

        document.getElementById('searchLecturers').addEventListener('input', function() {
            filterLecturers();
        });

        function filterLecturers() {
            const ratingFilter = document.getElementById('performanceFilter').value;
            const searchTerm = document.getElementById('searchLecturers').value.toLowerCase();
            const lecturers = document.querySelectorAll('.lecturer-item');

            lecturers.forEach(lecturer => {
                const lecturerRating = lecturer.getAttribute('data-rating');
                const lecturerName = lecturer.querySelector('h6').textContent.toLowerCase();
                const lecturerEmail = lecturer.querySelector('small').textContent.toLowerCase();

                const matchesRating = !ratingFilter || lecturerRating === ratingFilter;
                const matchesSearch = lecturerName.includes(searchTerm) || lecturerEmail.includes(searchTerm);

                if (matchesRating && matchesSearch) {
                    lecturer.style.display = 'block';
                } else {
                    lecturer.style.display = 'none';
                }
            });
        }

        function viewLecturerDetails(lecturerId) {
            window.location.href = `hod-lecturer-details.php?id=${lecturerId}`;
        }

        function provideFeedback(lecturerId) {
            // In a real application, this would open a feedback modal
            alert(`Provide feedback for lecturer ID: ${lecturerId}`);
        }

        function exportPerformanceReport() {
            // In a real application, this would generate and download a report
            alert('Performance report export functionality would be implemented here');
        }

        function scheduleReview() {
            // In a real application, this would open a scheduling interface
            alert('Performance review scheduling functionality would be implemented here');
        }
    </script>
</body>
</html>
