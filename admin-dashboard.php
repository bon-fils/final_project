<?php
/**
 * Admin Dashboard
 * Main dashboard for administrators with comprehensive system overview
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

/**
 * Get dashboard statistics with optimized query
 * Uses a single query with multiple COUNT aggregations for better performance
 */
function getDashboardStats() {
    global $pdo, $redisCache;

    // Try Redis cache first
    $cachedStats = $redisCache->getCachedDashboardStats('admin');
    if ($cachedStats) {
        return $cachedStats;
    }

    // Check file cache as fallback
    $cache_key = 'dashboard_stats';
    $cache_file = 'cache/dashboard_stats.cache';
    $cache_time = 300; // 5 minutes

    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
        $cached_data = json_decode(file_get_contents($cache_file), true);
        if ($cached_data) {
            // Also cache in Redis for next time
            $redisCache->cacheDashboardStats('admin', $cached_data, $cache_time);
            return $cached_data;
        }
    }

    try {
        // Optimized query using conditional aggregation
        $stmt = $pdo->prepare("
            SELECT
                COUNT(CASE WHEN table_type = 'students' THEN 1 END) AS total_students,
                COUNT(CASE WHEN table_type = 'lecturers' AND role = 'lecturer' THEN 1 END) AS total_lecturers,
                COUNT(CASE WHEN table_type = 'lecturers' AND role = 'hod' THEN 1 END) AS total_hods,
                COUNT(CASE WHEN table_type = 'departments' THEN 1 END) AS total_departments,
                COUNT(CASE WHEN table_type = 'active_sessions' THEN 1 END) AS active_attendance,
                COUNT(CASE WHEN table_type = 'total_sessions' THEN 1 END) AS total_sessions,
                COUNT(CASE WHEN table_type = 'pending_requests' THEN 1 END) AS pending_requests,
                COUNT(CASE WHEN table_type = 'approved_requests' THEN 1 END) AS approved_requests,
                COUNT(CASE WHEN table_type = 'today_records' THEN 1 END) AS today_records,
                COUNT(CASE WHEN table_type = 'active_users_24h' THEN 1 END) AS active_users_24h,
                ROUND(AVG(CASE WHEN table_type = 'attendance_rate' THEN attendance_rate END), 1) AS avg_attendance_rate
            FROM (
                SELECT 'students' AS table_type, NULL AS role, NULL AS attendance_rate FROM students
                UNION ALL
                SELECT 'lecturers', u.role, NULL FROM lecturers l LEFT JOIN users u ON l.user_id = u.id
                UNION ALL
                SELECT 'departments', NULL, NULL FROM departments
                UNION ALL
                SELECT 'active_sessions', NULL, NULL FROM attendance_sessions WHERE end_time IS NULL
                UNION ALL
                SELECT 'total_sessions', NULL, NULL FROM attendance_sessions
                UNION ALL
                SELECT 'pending_requests', NULL, NULL FROM leave_requests WHERE status = 'pending'
                UNION ALL
                SELECT 'approved_requests', NULL, NULL FROM leave_requests WHERE status = 'approved'
                UNION ALL
                SELECT 'today_records', NULL, NULL FROM attendance_records WHERE DATE(recorded_at) = CURDATE()
                UNION ALL
                SELECT 'active_users_24h', NULL, NULL FROM lecturers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                UNION ALL
                SELECT 'attendance_rate', NULL, (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0))
                FROM attendance_sessions s
                LEFT JOIN attendance_records ar ON s.id = ar.session_id
                WHERE s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY s.id
            ) AS combined_data
        ");

        $stmt->execute();
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        // Sanitize and validate data
        $stats = [
            'total_students' => max(0, (int)($counts['total_students'] ?? 0)),
            'total_lecturers' => max(0, (int)($counts['total_lecturers'] ?? 0)),
            'total_hods' => max(0, (int)($counts['total_hods'] ?? 0)),
            'total_departments' => max(0, (int)($counts['total_departments'] ?? 0)),
            'active_attendance' => max(0, (int)($counts['active_attendance'] ?? 0)),
            'total_sessions' => max(0, (int)($counts['total_sessions'] ?? 0)),
            'pending_requests' => max(0, (int)($counts['pending_requests'] ?? 0)),
            'approved_requests' => max(0, (int)($counts['approved_requests'] ?? 0)),
            'today_records' => max(0, (int)($counts['today_records'] ?? 0)),
            'active_users_24h' => max(0, (int)($counts['active_users_24h'] ?? 0)),
            'avg_attendance_rate' => round(max(0, min(100, (float)($counts['avg_attendance_rate'] ?? 0))), 1),
            'last_updated' => date('Y-m-d H:i:s')
        ];

        // Cache the results in both Redis and file cache
        if (!is_dir('cache')) {
            mkdir('cache', 0755, true);
        }
        file_put_contents($cache_file, json_encode($stats));
        
        // Cache in Redis for better performance
        $redisCache->cacheDashboardStats('admin', $stats, $cache_time);

        return $stats;

    } catch (PDOException $e) {
        error_log("Dashboard stats error: " . $e->getMessage());
        // Return fallback data with error flag
        return [
            'total_students' => 0,
            'total_lecturers' => 0,
            'total_hods' => 0,
            'total_departments' => 0,
            'active_attendance' => 0,
            'total_sessions' => 0,
            'pending_requests' => 0,
            'approved_requests' => 0,
            'today_records' => 0,
            'active_users_24h' => 0,
            'avg_attendance_rate' => 0,
            'last_updated' => date('Y-m-d H:i:s'),
            'error' => 'Database connection failed. Please try again later.',
            'error_code' => 'DB_ERROR'
        ];
    }
}

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    try {
        $stats = getDashboardStats();
        echo json_encode([
            'status' => 'success',
            'data' => $stats,
            'timestamp' => time()
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to load dashboard data'
        ]);
    }
    exit;
}

// Load dashboard statistics
$dashboard_stats = getDashboardStats();
$total_students = $dashboard_stats['total_students'];
$total_lecturers = $dashboard_stats['total_lecturers'];
$total_hods = $dashboard_stats['total_hods'];
$total_departments = $dashboard_stats['total_departments'];
$active_attendance = $dashboard_stats['active_attendance'];
$total_sessions = $dashboard_stats['total_sessions'];
$pending_requests = $dashboard_stats['pending_requests'];
$approved_requests = $dashboard_stats['approved_requests'];
$today_records = $dashboard_stats['today_records'];
$active_users_24h = $dashboard_stats['active_users_24h'];
$avg_attendance_rate = $dashboard_stats['avg_attendance_rate'];
$last_updated = $dashboard_stats['last_updated'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/admin-dashboard.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
 </head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <!-- Include Admin Sidebar -->
    <?php include 'includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="topbar">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <h2 class="mb-0">
                        <i class="fas fa-tachometer-alt me-3"></i>Admin Dashboard
                    </h2>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <div class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-user-shield me-1"></i>Admin
                    </div>
                    <button class="btn btn-outline-primary btn-sm" id="refreshDashboard" title="Refresh Dashboard Data">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center text-white">
                <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Loading Dashboard</h5>
                <p class="mb-0">Please wait while we fetch the latest data...</p>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertBox" class="mb-4"></div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-5">
            <!-- First Row -->
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-users text-primary"></i>
                    <h3 id="total_students"><?= $total_students ?></h3>
                    <p>Total Students</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-chalkboard-teacher text-info"></i>
                    <h3 id="total_lecturers"><?= $total_lecturers ?></h3>
                    <p>Total Lecturers</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-user-tie text-success"></i>
                    <h3 id="total_hods"><?= $total_hods ?></h3>
                    <p>Department Heads</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-building text-warning"></i>
                    <h3 id="total_departments"><?= $total_departments ?></h3>
                    <p>Departments</p>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row g-4 mb-5">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-video text-danger"></i>
                    <h3 id="active_attendance"><?= $active_attendance ?></h3>
                    <p>Active Sessions</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-calendar-check text-secondary"></i>
                    <h3 id="total_sessions"><?= $total_sessions ?></h3>
                    <p>Total Sessions</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-clock text-warning"></i>
                    <h3 id="pending_requests"><?= $pending_requests ?></h3>
                    <p>Pending Leaves</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="stats-card">
                    <i class="fas fa-chart-line text-success"></i>
                    <h3 id="avg_attendance_rate">
                        <?php if ($avg_attendance_rate > 0): ?>
                            <?= $avg_attendance_rate ?>%
                        <?php else: ?>
                            0%
                        <?php endif; ?>
                    </h3>
                    <p>Avg Attendance</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions & System Status -->
        <div class="row g-4">
            <!-- Quick Actions -->
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
                                <a href="register-student.php" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Register Student
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="admin-register-lecturer.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>Register Lecturer
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="manage-departments.php" class="btn btn-outline-success w-100">
                                    <i class="fas fa-building me-2"></i>Manage Departments
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="admin-reports.php" class="btn btn-outline-warning w-100">
                                    <i class="fas fa-chart-bar me-2"></i>View Reports
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="assign-hod.php" class="btn btn-outline-danger w-100">
                                    <i class="fas fa-user-tie me-2"></i>Assign HOD
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="hod-leave-management.php" class="btn btn-outline-info w-100">
                                    <i class="fas fa-clipboard-list me-2"></i>Leave Management
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="system-logs.php" class="btn btn-outline-secondary w-100">
                                    <i class="fas fa-history me-2"></i>System Logs
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="manage-users.php" class="btn btn-outline-dark w-100">
                                    <i class="fas fa-users me-2"></i>Manage Users
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Status -->
            <div class="col-lg-6">
                <div class="card border-0 shadow">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0 fw-bold">
                            <i class="fas fa-server me-2"></i>System Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-database text-primary me-2"></i>Database</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-users text-info me-2"></i>Active Users (24h)</span>
                                <span class="badge bg-info" id="active_users_count">0</span>
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
                                <span><i class="fas fa-chart-line text-success me-2"></i>Avg Attendance</span>
                                <span class="badge bg-success" id="avg_attendance_display">
                                    <?php if ($avg_attendance_rate > 0): ?>
                                        <?= $avg_attendance_rate ?>%
                                    <?php else: ?>
                                        0%
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-shield-alt text-danger me-2"></i>Security Status</span>
                                <span class="badge bg-success">Secure</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave Management Section -->
        <div id="leaveManagementSection" class="mt-5" style="display: none;">
            <div class="card border-0 shadow">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-clipboard-list me-2"></i>Leave Management Overview
                    </h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-light btn-sm" onclick="refreshLeaveData()">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                        <button class="btn btn-outline-light btn-sm" onclick="hideLeaveManagement()">
                            <i class="fas fa-times me-1"></i>Close
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Leave Statistics -->
                    <div class="row g-4 mb-4">
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stats-card">
                                <i class="fas fa-clock text-warning"></i>
                                <h3 id="totalLeaves">0</h3>
                                <p>Total Leave Requests</p>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stats-card">
                                <i class="fas fa-hourglass-half text-warning"></i>
                                <h3 id="pendingLeaves">0</h3>
                                <p>Pending Requests</p>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stats-card">
                                <i class="fas fa-check-circle text-success"></i>
                                <h3 id="approvedLeaves">0</h3>
                                <p>Approved Requests</p>
                            </div>
                        </div>
                        <div class="col-xl-3 col-lg-6 col-md-6">
                            <div class="stats-card">
                                <i class="fas fa-times-circle text-danger"></i>
                                <h3 id="rejectedLeaves">0</h3>
                                <p>Rejected Requests</p>
                            </div>
                        </div>
                    </div>

                    <!-- Filters and Search -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label for="leaveStatusFilter" class="form-label fw-semibold">Status</label>
                            <select id="leaveStatusFilter" class="form-select" onchange="filterLeaves()">
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="leaveDepartmentFilter" class="form-label fw-semibold">Department</label>
                            <select id="leaveDepartmentFilter" class="form-select" onchange="filterLeaves()">
                                <option value="all">All Departments</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="leaveSearch" class="form-label fw-semibold">Search</label>
                            <input type="text" id="leaveSearch" class="form-control" placeholder="Search by name..." onkeyup="filterLeaves()">
                        </div>
                        <div class="col-md-3">
                            <label for="leaveDateFilter" class="form-label fw-semibold">Date Range</label>
                            <select id="leaveDateFilter" class="form-select" onchange="filterLeaves()">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                            </select>
                        </div>
                    </div>

                    <!-- Leave Requests Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="leaveTable">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Student Name</th>
                                    <th scope="col">Department</th>
                                    <th scope="col">Class</th>
                                    <th scope="col">From Date</th>
                                    <th scope="col">To Date</th>
                                    <th scope="col">Days</th>
                                    <th scope="col">Reason</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Requested</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="leaveTableBody">
                                <tr>
                                    <td colspan="11" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading leave requests...</span>
                                        </div>
                                        <p class="mt-2 text-muted">Loading leave requests...</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav id="leavePagination" class="mt-4" style="display: none;">
                        <ul class="pagination justify-content-center" id="leavePaginationControls"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <script src="js/admin-dashboard.js"></script>
    <script>
        // Leave Management Functions
        let allLeaveRequests = [];
        let filteredLeaveRequests = [];
        let currentLeavePage = 1;
        const leavesPerPage = 10;

        function showLeaveManagement() {
            document.getElementById('leaveManagementSection').style.display = 'block';
            document.getElementById('leaveManagementSection').scrollIntoView({ behavior: 'smooth' });

            // Update active link
            document.querySelectorAll('.sidebar-nav a').forEach(link => link.classList.remove('active'));
            document.querySelector('.leave-management-link').classList.add('active');

            // Load leave data if not already loaded
            if (allLeaveRequests.length === 0) {
                loadLeaveData();
            }
        }

        function hideLeaveManagement() {
            document.getElementById('leaveManagementSection').style.display = 'none';
            // Remove active class from leave management link
            document.querySelector('.leave-management-link').classList.remove('active');
        }

        async function loadLeaveData() {
            try {
                const response = await fetch('api/admin-leave-api.php?action=get_all_leaves', {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.status === 'success') {
                    allLeaveRequests = result.data.leaves || [];
                    filteredLeaveRequests = [...allLeaveRequests];

                    // Update statistics
                    updateLeaveStatistics(result.data.stats || {});

                    // Load departments for filter
                    loadLeaveDepartments(result.data.departments || []);

                    // Render table
                    renderLeaveTable();
                } else {
                    showLeaveAlert('danger', 'Failed to load leave data: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error loading leave data:', error);
                showLeaveAlert('danger', 'Failed to load leave data. Please try again.');
            }
        }

        function updateLeaveStatistics(stats) {
            document.getElementById('totalLeaves').textContent = stats.total || 0;
            document.getElementById('pendingLeaves').textContent = stats.pending || 0;
            document.getElementById('approvedLeaves').textContent = stats.approved || 0;
            document.getElementById('rejectedLeaves').textContent = stats.rejected || 0;
        }

        function loadLeaveDepartments(departments) {
            const select = document.getElementById('leaveDepartmentFilter');
            select.innerHTML = '<option value="all">All Departments</option>';

            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                select.appendChild(option);
            });
        }

        function filterLeaves() {
            const statusFilter = document.getElementById('leaveStatusFilter').value;
            const departmentFilter = document.getElementById('leaveDepartmentFilter').value;
            const searchTerm = document.getElementById('leaveSearch').value.toLowerCase();
            const dateFilter = document.getElementById('leaveDateFilter').value;

            filteredLeaveRequests = allLeaveRequests.filter(leave => {
                // Status filter
                if (statusFilter !== 'all' && leave.status !== statusFilter) {
                    return false;
                }

                // Department filter
                if (departmentFilter !== 'all' && leave.department_id != departmentFilter) {
                    return false;
                }

                // Search filter
                if (searchTerm) {
                    const fullName = `${leave.first_name} ${leave.last_name}`.toLowerCase();
                    if (!fullName.includes(searchTerm) && !leave.reason.toLowerCase().includes(searchTerm)) {
                        return false;
                    }
                }

                // Date filter
                if (dateFilter !== 'all') {
                    const requestedDate = new Date(leave.requested_at);
                    const now = new Date();

                    switch (dateFilter) {
                        case 'today':
                            if (requestedDate.toDateString() !== now.toDateString()) return false;
                            break;
                        case 'week':
                            const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                            if (requestedDate < weekAgo) return false;
                            break;
                        case 'month':
                            const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                            if (requestedDate < monthAgo) return false;
                            break;
                    }
                }

                return true;
            });

            currentLeavePage = 1;
            renderLeaveTable();
        }

        function renderLeaveTable() {
            const tbody = document.getElementById('leaveTableBody');
            const startIndex = (currentLeavePage - 1) * leavesPerPage;
            const endIndex = startIndex + leavesPerPage;
            const pageData = filteredLeaveRequests.slice(startIndex, endIndex);

            if (pageData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="11" class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Leave Requests Found</h5>
                            <p class="text-muted">No leave requests match your current filters.</p>
                        </td>
                    </tr>
                `;
                document.getElementById('leavePagination').style.display = 'none';
                return;
            }

            let html = '';
            pageData.forEach((leave, index) => {
                const statusClass = leave.status === 'approved' ? 'bg-success' :
                                  leave.status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark';
                const statusText = leave.status.charAt(0).toUpperCase() + leave.status.slice(1);

                // Calculate days
                const fromDate = new Date(leave.from_date);
                const toDate = new Date(leave.to_date);
                const days = Math.ceil((toDate - fromDate) / (1000 * 60 * 60 * 24)) + 1;

                html += `
                    <tr>
                        <td>${startIndex + index + 1}</td>
                        <td>
                            <strong>${leave.first_name} ${leave.last_name}</strong>
                            <br><small class="text-muted">ID: ${leave.student_id}</small>
                        </td>
                        <td>${leave.department_name}</td>
                        <td>${leave.year_level}</td>
                        <td>${new Date(leave.from_date).toLocaleDateString()}</td>
                        <td>${new Date(leave.to_date).toLocaleDateString()}</td>
                        <td><span class="badge bg-info">${days} day${days !== 1 ? 's' : ''}</span></td>
                        <td>
                            <span title="${leave.reason}">${leave.reason.length > 50 ? leave.reason.substring(0, 50) + '...' : leave.reason}</span>
                        </td>
                        <td><span class="badge ${statusClass}">${statusText}</span></td>
                        <td><small class="text-muted">${new Date(leave.requested_at).toLocaleDateString()}</small></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="viewLeaveDetails(${leave.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${leave.supporting_file ? `<a href="uploads/${leave.supporting_file}" target="_blank" class="btn btn-sm btn-outline-secondary" title="View Supporting File">
                                <i class="fas fa-file"></i>
                            </a>` : ''}
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            renderLeavePagination();
        }

        function renderLeavePagination() {
            const totalPages = Math.ceil(filteredLeaveRequests.length / leavesPerPage);
            const pagination = document.getElementById('leavePaginationControls');

            if (totalPages <= 1) {
                document.getElementById('leavePagination').style.display = 'none';
                return;
            }

            document.getElementById('leavePagination').style.display = 'block';

            let html = '';

            // Previous button
            html += `<li class="page-item ${currentLeavePage === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="changeLeavePage(${currentLeavePage - 1})">Previous</a>
            </li>`;

            // Page numbers
            const startPage = Math.max(1, currentLeavePage - 2);
            const endPage = Math.min(totalPages, currentLeavePage + 2);

            if (startPage > 1) {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changeLeavePage(1)">1</a></li>`;
                if (startPage > 2) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `<li class="page-item ${i === currentLeavePage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="changeLeavePage(${i})">${i}</a>
                </li>`;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }
                html += `<li class="page-item"><a class="page-link" href="#" onclick="changeLeavePage(${totalPages})">${totalPages}</a></li>`;
            }

            // Next button
            html += `<li class="page-item ${currentLeavePage === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="changeLeavePage(${currentLeavePage + 1})">Next</a>
            </li>`;

            pagination.innerHTML = html;
        }

        function changeLeavePage(page) {
            if (page < 1 || page > Math.ceil(filteredLeaveRequests.length / leavesPerPage)) {
                return;
            }
            currentLeavePage = page;
            renderLeaveTable();
        }

        function viewLeaveDetails(leaveId) {
            const leave = allLeaveRequests.find(l => l.id == leaveId);
            if (!leave) return;

            // Create modal for leave details
            const modalHtml = `
                <div class="modal fade" id="leaveDetailModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Leave Request Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Student Information</h6>
                                        <p><strong>Name:</strong> ${leave.first_name} ${leave.last_name}</p>
                                        <p><strong>Student ID:</strong> ${leave.student_id}</p>
                                        <p><strong>Department:</strong> ${leave.department_name}</p>
                                        <p><strong>Class:</strong> ${leave.year_level}</p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Leave Information</h6>
                                        <p><strong>From:</strong> ${new Date(leave.from_date).toLocaleDateString()}</p>
                                        <p><strong>To:</strong> ${new Date(leave.to_date).toLocaleDateString()}</p>
                                        <p><strong>Days:</strong> ${Math.ceil((new Date(leave.to_date) - new Date(leave.from_date)) / (1000 * 60 * 60 * 24)) + 1}</p>
                                        <p><strong>Status:</strong> <span class="badge ${leave.status === 'approved' ? 'bg-success' : leave.status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'}">${leave.status.charAt(0).toUpperCase() + leave.status.slice(1)}</span></p>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <h6>Reason</h6>
                                    <p>${leave.reason}</p>
                                </div>
                                ${leave.supporting_file ? `
                                    <div class="mt-3">
                                        <h6>Supporting Document</h6>
                                        <a href="uploads/${leave.supporting_file}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-file me-1"></i>View Document
                                        </a>
                                    </div>
                                ` : ''}
                                <div class="mt-3">
                                    <h6>Request Timeline</h6>
                                    <p><strong>Requested:</strong> ${new Date(leave.requested_at).toLocaleString()}</p>
                                    ${leave.reviewed_at ? `<p><strong>Reviewed:</strong> ${new Date(leave.reviewed_at).toLocaleString()}</p>` : ''}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Remove existing modal if present
            const existingModal = document.getElementById('leaveDetailModal');
            if (existingModal) existingModal.remove();

            // Add new modal
            document.body.insertAdjacentHTML('beforeend', modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('leaveDetailModal'));
            modal.show();
        }

        function refreshLeaveData() {
            allLeaveRequests = [];
            filteredLeaveRequests = [];
            loadLeaveData();
        }

        function showLeaveAlert(type, message) {
            const alertContainer = document.getElementById('alertBox');
            const alertClass = type === 'success' ? 'alert-success' :
                             type === 'danger' ? 'alert-danger' :
                             type === 'warning' ? 'alert-warning' : 'alert-info';

            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="fas fa-info-circle me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            alertContainer.innerHTML = alertHtml;

            // Auto-hide after 5 seconds
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }

        // Make functions globally available
        window.showLeaveManagement = showLeaveManagement;
        window.hideLeaveManagement = hideLeaveManagement;
        window.refreshLeaveData = refreshLeaveData;
        window.filterLeaves = filterLeaves;
        window.changeLeavePage = changeLeavePage;
        window.viewLeaveDetails = viewLeaveDetails;
    </script>
</body>
</html>
