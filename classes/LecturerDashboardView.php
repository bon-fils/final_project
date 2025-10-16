<?php
class LecturerDashboardView {
    private $controller;
    private $stats;
    private $activities;
    private $attendanceTrends;

    public function __construct($controller) {
        $this->controller = $controller;
        $this->loadData();
    }

    private function loadData() {
        $this->stats = $this->controller->getDashboardStats();
        $this->activities = $this->controller->getRecentActivities();
        $this->attendanceTrends = $this->controller->getAttendanceTrends();
    }

    public function render() {
        $this->renderHeader();
        $this->renderContent();
        $this->renderFooter();
    }

    private function renderHeader() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Lecturer Dashboard | RP Attendance System</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
            <link href="css/admin-dashboard.css" rel="stylesheet">
            <style>
                :root {
                    --primary-color: #0ea5e9;
                    --secondary-color: #0284c7;
                    --accent-color: #06b6d4;
                    --success-color: #10b981;
                    --warning-color: #f59e0b;
                    --danger-color: #ef4444;
                    --info-color: #3b82f6;
                    --light-bg: #ffffff;
                    --dark-bg: #1f2937;
                    --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                    --card-shadow-hover: 0 20px 40px rgba(0, 0, 0, 0.15);
                    --border-radius: 16px;
                    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                }

                body {
                    font-family: 'Inter', 'Segoe UI', sans-serif;
                    background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 50%, #bae6fd 100%);
                    margin: 0;
                    min-height: 100vh;
                    color: var(--dark-bg);
                }

                .sidebar {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 280px;
                    height: 100vh;
                    background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                    color: white;
                    padding-top: 30px;
                    box-shadow: 4px 0 20px rgba(0,0,0,0.15);
                    z-index: 1000;
                    overflow-y: auto;
                    transition: var(--transition);
                }

                .sidebar.collapsed {
                    width: 80px;
                }

                .sidebar a {
                    display: flex;
                    align-items: center;
                    padding: 15px 25px;
                    color: #fff;
                    text-decoration: none;
                    transition: var(--transition);
                    margin: 5px 15px;
                    border-radius: 12px;
                    font-weight: 500;
                }

                .sidebar a:hover, .sidebar a.active {
                    background: rgba(255,255,255,0.15);
                    transform: translateX(8px) scale(1.02);
                }

                .main-content {
                    margin-left: 280px;
                    padding: 40px;
                    transition: var(--transition);
                }

                .card {
                    border-radius: var(--border-radius);
                    box-shadow: var(--card-shadow);
                    margin-bottom: 30px;
                    transition: var(--transition);
                    border: none;
                    background: #ffffff;
                }

                .card:hover {
                    transform: translateY(-8px);
                    box-shadow: var(--card-shadow-hover);
                }

                .widget {
                    background: #ffffff;
                    border-radius: var(--border-radius);
                    padding: 30px;
                    text-align: center;
                    box-shadow: var(--card-shadow);
                    transition: var(--transition);
                    position: relative;
                    overflow: hidden;
                    height: 100%;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }

                .widget:hover {
                    transform: translateY(-8px);
                    box-shadow: var(--card-shadow-hover);
                }

                .widget h3 {
                    margin-bottom: 20px;
                    color: #000000;
                    font-size: 0.9rem;
                    font-weight: 700;
                    letter-spacing: -0.025em;
                }

                .widget p {
                    font-size: 1.5rem;
                    font-weight: 800;
                    margin: 0;
                    color: #000000;
                }

                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                    gap: 25px;
                    margin-bottom: 40px;
                }

                .simple-bar-chart {
                    display: flex;
                    align-items: flex-end;
                    justify-content: space-between;
                    height: 220px;
                    padding: 20px 10px;
                    gap: 6px;
                    overflow-x: auto;
                }

                .bar-item {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    min-width: 45px;
                    max-width: 60px;
                }

                .bar-fill {
                    width: 100%;
                    position: absolute;
                    bottom: 0;
                    border-radius: 6px 6px 0 0;
                    transition: height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                }

                .activity-item {
                    transition: var(--transition);
                    padding: 15px;
                    border-radius: 12px;
                    border-left: 5px solid #000000;
                    background: #ffffff;
                    margin-bottom: 10px;
                    border: 1px solid #000000;
                }

                .activity-item:hover {
                    background-color: rgba(49, 130, 206, 0.1);
                    transform: translateX(8px) scale(1.01);
                }

                @media (max-width: 768px) {
                    .sidebar, .main-content {
                        margin-left: 0 !important;
                        width: 100%;
                    }
                    .sidebar {
                        position: fixed;
                        width: 100%;
                        height: auto;
                        display: block;
                        transform: translateY(-100%);
                        transition: transform 0.3s ease;
                    }
                    .sidebar.mobile-open {
                        transform: translateY(0);
                    }
                }
            </style>
        </head>
        <body>
            <?php include 'includes/sidebar.php'; ?>
        <?php
    }

    private function renderContent() {
        $lecturerInfo = $this->controller->getLecturerInfo();
        ?>
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header text-center py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%); border-bottom: 2px solid #0ea5e9; margin-bottom: 20px;">
                <div class="d-flex align-items-center justify-content-center">
                    <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" style="height: 60px; width: auto; margin-right: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                    <h1 style="background: linear-gradient(135deg, #0ea5e9, #0284c7); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0; font-weight: 700;">
                        Lecturer Dashboard
                    </h1>
                </div>
            </div>

            <!-- Top Bar -->
            <div class="d-flex align-items-center justify-content-end mb-4">
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <div class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-chalkboard-teacher me-1"></i>Lecturer
                    </div>
                    <button class="btn btn-outline-primary btn-sm" id="refreshDashboard">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>

            <!-- Alert Messages -->
            <div id="alertBox" class="mb-4"></div>

            <?php if (isset($this->stats['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Database Error:</strong> <?= htmlspecialchars($this->stats['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php else: ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="widget">
                    <i class="fas fa-book text-primary"></i>
                    <h3 id="assignedCourses"><?= $this->stats['assignedCourses'] ?></h3>
                    <p>Assigned Courses</p>
                </div>
                <div class="widget">
                    <i class="fas fa-users text-info"></i>
                    <h3 id="totalStudents"><?= $this->stats['totalStudents'] ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="widget">
                    <i class="fas fa-calendar-check text-success"></i>
                    <h3 id="todayAttendance" class="<?= $this->stats['todayAttendance'] > 0 ? 'pulse' : '' ?>">
                        <?= $this->stats['todayAttendance'] ?>
                    </h3>
                    <p>Today's Attendance</p>
                </div>
                <div class="widget">
                    <i class="fas fa-chart-line text-warning"></i>
                    <h3 id="weekAttendance"><?= $this->stats['weekAttendance'] ?></h3>
                    <p>Weekly Attendance</p>
                </div>
                <div class="widget">
                    <i class="fas fa-clock text-secondary"></i>
                    <h3 id="pendingLeaves"><?= $this->stats['pendingLeaves'] ?></h3>
                    <p>Pending Leaves</p>
                </div>
                <div class="widget">
                    <i class="fas fa-check-circle text-success"></i>
                    <h3 id="approvedLeaves"><?= $this->stats['approvedLeaves'] ?></h3>
                    <p>Approved Leaves</p>
                </div>
                <div class="widget">
                    <i class="fas fa-video text-danger"></i>
                    <h3 id="totalSessions"><?= $this->stats['totalSessions'] ?></h3>
                    <p>Total Sessions</p>
                </div>
                <div class="widget">
                    <i class="fas fa-percentage text-info"></i>
                    <h3 id="averageAttendance">
                        <?php if ($this->stats['averageAttendance'] > 0): ?>
                            <?= $this->stats['averageAttendance'] ?>%
                        <?php else: ?>
                            0%
                        <?php endif; ?>
                    </h3>
                    <p>Avg Attendance</p>
                </div>
            </div>

            <!-- Quick Actions & System Status -->
            <div class="row g-4 mb-5">
                <div class="col-lg-6">
                    <div class="card border-0 shadow">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0 fw-bold">
                                <i class="fas fa-bolt me-2"></i>Quick Actions
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a href="attendance-session.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-play me-2"></i>Start Session
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="lecturer-my-courses.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-book me-2"></i>My Courses
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="attendance-reports.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-chart-line me-2"></i>Reports
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="leave-requests.php" class="btn btn-outline-warning w-100">
                                        <i class="fas fa-envelope me-2"></i>Leave Requests
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card border-0 shadow">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0 fw-bold">
                                <i class="fas fa-server me-2"></i>Session Status
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-video text-primary me-2"></i>Active Sessions</span>
                                    <span class="badge bg-info" id="activeSessions"><?= $this->stats['activeSessions'] ?></span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-clock text-warning me-2"></i>Last Update</span>
                                    <span class="badge bg-secondary" id="last_update">Just now</span>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-building text-info me-2"></i>Department</span>
                                    <span class="badge bg-info">
                                        <?= htmlspecialchars(substr($lecturerInfo['department_name'], 0, 15)) ?>
                                    </span>
                                </div>
                            </div>
                            <div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="fas fa-shield-alt text-danger me-2"></i>Status</span>
                                    <span class="badge bg-success">Active</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Activities -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0 fw-bold">
                                <i class="fas fa-chart-bar me-2"></i>Attendance Trends (Last 7 Days)
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="simple-bar-chart" id="attendanceChart">
                                <?php if (!empty($this->attendanceTrends)): ?>
                                    <?php
                                    $maxCount = max(array_column($this->attendanceTrends, 'count')) ?: 1;
                                    foreach ($this->attendanceTrends as $trend):
                                        $percentage = ($trend['count'] / $maxCount) * 100;
                                    ?>
                                    <div class="bar-item">
                                        <div class="bar-container">
                                            <div class="bar-fill" style="height: <?= $percentage ?>%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));"></div>
                                        </div>
                                        <div class="bar-label"><?= htmlspecialchars($trend['day']) ?></div>
                                        <div class="bar-value"><?= $trend['count'] ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-chart-bar fa-3x mb-3 opacity-50"></i>
                                        <p>No attendance data available</p>
                                        <small>Start an attendance session to see trends</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-warning text-white">
                            <h6 class="mb-0 fw-bold">
                                <i class="fas fa-history me-2"></i>Recent Activities
                            </h6>
                        </div>
                        <div class="card-body" id="recentActivities">
                            <?php if (!empty($this->activities)): ?>
                                <?php foreach ($this->activities as $activity): ?>
                                <div class="activity-item">
                                    <strong><?= htmlspecialchars($activity['type']) ?>:</strong>
                                    <?= htmlspecialchars($activity['description']) ?>
                                    <br><small class="text-muted">
                                        <?= date('M j, g:i A', strtotime($activity['timestamp'])) ?>
                                    </small>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">No recent activities</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
        <?php
    }

    private function renderFooter() {
        ?>
        <div class="footer">
            &copy; 2025 Rwanda Polytechnic | Lecturer Panel
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
        // Dashboard refresh functionality
        function refreshDashboard() {
            $('#alertBox').html('<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Refreshing dashboard...</div>');

            $.ajax({
                url: 'lecturer-dashboard.php?ajax=1',
                method: 'GET',
                dataType: 'json',
                timeout: 10000,
                success: function(data) {
                    if (data.status === 'success') {
                        const stats = data.data;

                        // Update statistics
                        $('#assignedCourses').text(stats.assignedCourses);
                        $('#totalStudents').text(stats.totalStudents);
                        $('#todayAttendance').text(stats.todayAttendance);
                        $('#weekAttendance').text(stats.weekAttendance);
                        $('#pendingLeaves').text(stats.pendingLeaves);
                        $('#approvedLeaves').text(stats.approvedLeaves);
                        $('#totalSessions').text(stats.totalSessions);
                        $('#activeSessions').text(stats.activeSessions);
                        $('#averageAttendance').text(stats.averageAttendance + '%');
                        $('#last_update').text('Just now');

                        // Update chart
                        updateChart(data.data.attendanceTrends);

                        // Update activities
                        updateActivities(data.data.activities);

                        showAlert('Dashboard refreshed successfully', 'success');
                    } else {
                        showAlert('Failed to refresh dashboard', 'danger');
                    }
                },
                error: function() {
                    showAlert('Error refreshing dashboard', 'danger');
                }
            });
        }

        function updateChart(trends) {
            const chartContainer = $('#attendanceChart');
            chartContainer.empty();

            if (trends && trends.length > 0) {
                const maxCount = Math.max(...trends.map(t => t.count), 1);
                trends.forEach(trend => {
                    const percentage = (trend.count / maxCount) * 100;
                    chartContainer.append(`
                        <div class="bar-item">
                            <div class="bar-container">
                                <div class="bar-fill" style="height: ${percentage}%; background: linear-gradient(135deg, #0ea5e9, #0284c7);"></div>
                            </div>
                            <div class="bar-label">${trend.day}</div>
                            <div class="bar-value">${trend.count}</div>
                        </div>
                    `);
                });
            } else {
                chartContainer.html(`
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-chart-bar fa-3x mb-3 opacity-50"></i>
                        <p>No attendance data available</p>
                    </div>
                `);
            }
        }

        function updateActivities(activities) {
            const container = $('#recentActivities');
            container.empty();

            if (activities.length > 0) {
                activities.forEach(activity => {
                    container.append(`
                        <div class="activity-item">
                            <strong>${activity.type}:</strong> ${activity.description}
                            <br><small class="text-muted">${new Date(activity.timestamp * 1000).toLocaleString()}</small>
                        </div>
                    `);
                });
            } else {
                container.html('<p class="text-muted text-center">No recent activities</p>');
            }
        }

        function showAlert(message, type = 'info') {
            const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
            $('#alertBox').html(alertHtml);
            setTimeout(() => $('.alert').fadeOut(), 5000);
        }

        // Event listeners
        $(document).ready(function() {
            $('#refreshDashboard').click(refreshDashboard);
            setInterval(refreshDashboard, 30000); // Auto-refresh every 30 seconds
        });
        </script>
        </body>
        </html>
        <?php
    }
}
?>