<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Handle AJAX requests for live counts
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    // Debug database connection
    error_log("AJAX Request received. PDO connection status: " . (isset($pdo) ? "Connected" : "Not connected"));

    try {
        // Debug: Check what tables exist
        try {
            $tablesStmt = $pdo->query("SHOW TABLES");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
            error_log("Available tables: " . implode(", ", $tables));
        } catch (Exception $e) {
            error_log("Failed to list tables: " . $e->getMessage());
        }

        // Try different approaches for students count
        $studentsCount = 0;
        try {
            $studentStmt = $pdo->query("SELECT COUNT(*) as count FROM students");
            $studentsCount = (int) $studentStmt->fetch()['count'];
            error_log("AJAX students count from students table: " . $studentsCount);
        } catch (Exception $e) {
            error_log("AJAX students table query failed: " . $e->getMessage());
            // Fallback: try counting users with student role
            try {
                $fallbackStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role='student'");
                $studentsCount = (int) $fallbackStmt->fetch()['count'];
                error_log("Using AJAX fallback students count from users table: " . $studentsCount);
            } catch (Exception $e2) {
                error_log("AJAX fallback students count also failed: " . $e2->getMessage());
            }
        }

        $stmt = $pdo->query("
            SELECT
                '{$studentsCount}' AS total_students,
                (SELECT COUNT(*) FROM users WHERE role='lecturer') AS total_lecturers,
                (SELECT COUNT(*) FROM users WHERE role='hod') AS total_hods,
                (SELECT COUNT(*) FROM departments) AS total_departments,
                (SELECT COUNT(*) FROM attendance_sessions WHERE status='active') AS active_attendance,
                (SELECT COUNT(*) FROM attendance_sessions) AS total_sessions,
                (SELECT COUNT(*) FROM leave_requests WHERE status='pending') AS pending_requests,
                (SELECT COUNT(*) FROM leave_requests WHERE status='approved') AS approved_requests,
                (SELECT COUNT(*) FROM attendance_records WHERE DATE(recorded_at) = CURDATE()) AS today_records
        ");
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        // Debug logging
        error_log("AJAX Dashboard counts: " . json_encode($counts));

        echo json_encode([
            'total_students'    => (int) ($counts['total_students'] ?? 0),
            'total_lecturers'   => (int) ($counts['total_lecturers'] ?? 0),
            'total_hods'        => (int) ($counts['total_hods'] ?? 0),
            'total_departments' => (int) ($counts['total_departments'] ?? 0),
            'active_attendance' => (int) ($counts['active_attendance'] ?? 0),
            'total_sessions'    => (int) ($counts['total_sessions'] ?? 0),
            'pending_requests'  => (int) ($counts['pending_requests'] ?? 0),
            'approved_requests' => (int) ($counts['approved_requests'] ?? 0),
            'today_records'     => (int) ($counts['today_records'] ?? 0)
        ]);
    } catch (PDOException $e) {
        error_log("Dashboard AJAX error: " . $e->getMessage());
        echo json_encode([
            'total_students'    => 0,
            'total_lecturers'   => 0,
            'total_hods'        => 0,
            'total_departments' => 0,
            'active_attendance' => 0,
            'total_sessions'    => 0,
            'pending_requests'  => 0,
            'approved_requests' => 0,
            'today_records'     => 0
        ]);
    }
    exit;
}

// Initial counts
try {
    // Debug: Check database connection
    error_log("Initial load - PDO connection status: " . (isset($pdo) ? "Connected" : "Not connected"));

    // Test basic database connectivity
    try {
        $testStmt = $pdo->query("SELECT 1 as test");
        $testResult = $testStmt->fetch();
        error_log("Database connectivity test: " . ($testResult ? "PASSED" : "FAILED"));
    } catch (Exception $e) {
        error_log("Database connectivity test failed: " . $e->getMessage());
    }

    // Debug: Check what tables exist
    try {
        $tablesStmt = $pdo->query("SHOW TABLES");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
        error_log("Initial load - Available tables: " . implode(", ", $tables));
    } catch (Exception $e) {
        error_log("Initial load - Failed to list tables: " . $e->getMessage());
    }

    // Try different approaches for students count
    $studentsCount = 0;
    try {
        $studentStmt = $pdo->query("SELECT COUNT(*) as count FROM students");
        $studentsCount = (int) $studentStmt->fetch()['count'];
        error_log("Initial students count from students table: " . $studentsCount);
    } catch (Exception $e) {
        error_log("Initial students table query failed: " . $e->getMessage());
        // Fallback: try counting users with student role
        try {
            $fallbackStmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role='student'");
            $studentsCount = (int) $fallbackStmt->fetch()['count'];
            error_log("Using initial fallback students count from users table: " . $studentsCount);
        } catch (Exception $e2) {
            error_log("Initial fallback students count also failed: " . $e2->getMessage());
        }
    }

    $stmt = $pdo->query("
        SELECT
            '{$studentsCount}' AS total_students,
            (SELECT COUNT(*) FROM users WHERE role='lecturer') AS total_lecturers,
            (SELECT COUNT(*) FROM users WHERE role='hod') AS total_hods,
            (SELECT COUNT(*) FROM departments) AS total_departments,
            (SELECT COUNT(*) FROM attendance_sessions WHERE status='active') AS active_attendance,
            (SELECT COUNT(*) FROM attendance_sessions) AS total_sessions,
            (SELECT COUNT(*) FROM leave_requests WHERE status='pending') AS pending_requests,
            (SELECT COUNT(*) FROM leave_requests WHERE status='approved') AS approved_requests,
            (SELECT COUNT(*) FROM attendance_records WHERE DATE(recorded_at) = CURDATE()) AS today_records
    ");
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug logging
    error_log("Dashboard counts: " . json_encode($counts));

    // Additional debug for students table
    try {
        $studentTest = $pdo->query("SELECT COUNT(*) as count FROM students");
        $studentCount = $studentTest->fetch()['count'];
        error_log("Direct students count: " . $studentCount);

        // Also check if there are students in users table
        $userStudentTest = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role='student'");
        $userStudentCount = $userStudentTest->fetch()['count'];
        error_log("Students in users table: " . $userStudentCount);
    } catch (Exception $e) {
        error_log("Error querying students table: " . $e->getMessage());
    }

    $total_students    = (int) ($counts['total_students'] ?? 0);
    $total_lecturers   = (int) ($counts['total_lecturers'] ?? 0);
    $total_hods        = (int) ($counts['total_hods'] ?? 0);
    $total_departments = (int) ($counts['total_departments'] ?? 0);
    $active_attendance = (int) ($counts['active_attendance'] ?? 0);
    $total_sessions    = (int) ($counts['total_sessions'] ?? 0);
    $pending_requests  = (int) ($counts['pending_requests'] ?? 0);
    $approved_requests = (int) ($counts['approved_requests'] ?? 0);
    $today_records     = (int) ($counts['today_records'] ?? 0);
} catch (PDOException $e) {
    $total_students = $total_lecturers = $total_hods = $total_departments = $active_attendance = $total_sessions = $pending_requests = $approved_requests = $today_records = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin Dashboard | RP Attendance System</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<style>
body {
    font-family: 'Segoe UI', sans-serif;
    background-color: #f5f7fa;
    margin: 0;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background-color: #003366;
    color: white;
    padding-top: 20px;
    z-index: 1000;
    overflow-y: auto;
}
.sidebar .logo {
    width: 120px;
    margin: 0 auto 15px;
    display: block;
}
.sidebar h4 { font-weight: bold; }
.sidebar a {
    display: block;
    padding: 12px 20px;
    color: white;
    text-decoration: none;
    transition: all 0.3s ease;
    border-radius: 8px;
    margin: 2px 8px;
    position: relative;
    overflow: hidden;
}
.sidebar a:hover, .sidebar a.active {
    background: linear-gradient(135deg, #0059b3 0%, #007bff 100%);
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.sidebar a::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 4px;
    background: linear-gradient(135deg, #fff 0%, #007bff 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
}
.sidebar a:hover::before, .sidebar a.active::before {
    transform: scaleY(1);
}
.topbar {
    margin-left: 250px;
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    padding: 15px 30px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 900;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
}
.main-content {
    margin-left: 250px;
    padding: 30px;
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 30px;
}
.row.dashboard-row {
    display: flex;
    gap: 30px;
    justify-content: center;
}
.dashboard-card {
    flex: 1;
    min-width: 250px;
    border-radius: 12px;
    padding: 30px;
    color: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: 0.3s ease;
}
.dashboard-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 15px rgba(0,0,0,0.15);
}
.dashboard-card h6 { margin-bottom: 10px; }
.dashboard-card h4 {
    font-weight: bold;
    font-size: 2rem;
    transition: all 0.3s;
}
.bg-students { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-lecturers { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.bg-hods { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.bg-departments { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
.bg-attendance { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.bg-sessions { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }
.bg-requests { background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); }
.bg-approved { background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); }
.quick-actions-card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.system-status-card { border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
.status-item { padding: 8px 0; border-bottom: 1px solid #f8f9fa; }
.status-item:last-child { border-bottom: none; }
.footer {
    text-align: center;
    padding: 15px;
    margin-left: 250px;
    font-size: 0.9rem;
    color: #555;
    background: #f0f0f0;
}
@media(max-width:768px){
    .sidebar,.topbar,.main-content,.footer{margin-left:0 !important; width:100%;}
    .sidebar{display:none;}
    .dashboard-card { margin-bottom: 20px; }
    .quick-actions-card .btn, .system-status-card .btn { font-size: 0.9rem; padding: 8px 12px; }
    .topbar { padding: 10px 15px; }
    .topbar h5 { font-size: 1.1rem; }
    .user-avatar { width: 35px; height: 35px; }
    .user-avatar i { font-size: 1.5rem; }
}
@media(max-width:576px){
    .dashboard-card h4 { font-size: 1.8rem; }
    .dashboard-card h6 { font-size: 0.9rem; }
    .main-content { padding: 20px 15px; }
}
</style>
</head>

<body>

<div class="sidebar">
    <div class="text-center mb-3">
        <img src="RP_Logo.jpeg" alt="RP Logo" class="logo">
        <h4>👩‍💼 Admin</h4>
        <hr style="border-color:#ffffff66;">
    </div>
    <a href="admin-dashboard.php" class="active"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
    <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
    <a href="admin-reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar">
    <div class="d-flex align-items-center">
        <i class="fas fa-tachometer-alt fa-lg me-3 text-primary"></i>
        <div>
            <h5 class="m-0 fw-bold">Admin Dashboard</h5>
            <small class="text-muted">System Overview & Management</small>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-outline-primary btn-sm" id="refreshDashboard" title="Refresh Dashboard Data">
            <i class="fas fa-sync-alt me-1"></i>Refresh
        </button>
        <small class="text-muted">Last updated: <span id="pageLastUpdate">Just now</span></small>
    </div>
    <div class="d-flex align-items-center">
        <div class="me-3 text-end">
            <small class="text-muted d-block">Welcome back</small>
            <span class="fw-semibold"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
        </div>
        <div class="user-avatar">
            <i class="fas fa-user-circle fa-2x text-primary"></i>
        </div>
    </div>
</div>

<div class="main-content">
    <!-- Connection Status Indicator -->
    <div class="alert alert-info d-none" id="connectionStatus" role="alert">
        <i class="fas fa-info-circle me-2"></i>
        <span id="connectionMessage">Checking database connection...</span>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-5">
        <!-- First Row -->
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="dashboard-card bg-students">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Students</h6>
                        <h4 id="total_students"><?= $total_students ?></h4>
                        <small class="text-white-50">Registered students</small>
                    </div>
                    <i class="fas fa-users fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="dashboard-card bg-lecturers">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Lecturers</h6>
                        <h4 id="total_lecturers"><?= $total_lecturers ?></h4>
                        <small class="text-white-50">Active faculty</small>
                    </div>
                    <i class="fas fa-chalkboard-teacher fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="dashboard-card bg-hods">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Department Heads</h6>
                        <h4 id="total_hods"><?= $total_hods ?></h4>
                        <small class="text-white-50">HoD accounts</small>
                    </div>
                    <i class="fas fa-user-tie fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="dashboard-card bg-departments">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Departments</h6>
                        <h4 id="total_departments"><?= $total_departments ?></h4>
                        <small class="text-white-50">Academic units</small>
                    </div>
                    <i class="fas fa-building fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="row g-4 mb-5">
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="dashboard-card bg-attendance">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Active Sessions</h6>
                        <h4 id="active_attendance"><?= $active_attendance ?></h4>
                        <small class="text-white-50">Live attendance</small>
                    </div>
                    <i class="fas fa-video fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="dashboard-card bg-sessions">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Total Sessions</h6>
                        <h4 id="total_sessions"><?= $total_sessions ?></h4>
                        <small class="text-white-50">All time</small>
                    </div>
                    <i class="fas fa-calendar-check fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="dashboard-card bg-requests">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Pending Requests</h6>
                        <h4 id="pending_requests"><?= $pending_requests ?></h4>
                        <small class="text-white-50">Awaiting approval</small>
                    </div>
                    <i class="fas fa-clock fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6">
            <div class="dashboard-card bg-approved">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6>Today's Records</h6>
                        <h4 id="today_records"><?= $today_records ?></h4>
                        <small class="text-white-50">Attendance marked</small>
                    </div>
                    <i class="fas fa-check-circle fa-3x opacity-75"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions & Recent Activity -->
    <div class="row g-4">
        <!-- Quick Actions -->
        <div class="col-lg-6">
            <div class="card quick-actions-card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="register-student.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-user-plus me-2"></i>Register Student
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="manage-departments.php" class="btn btn-outline-success w-100">
                                <i class="fas fa-building me-2"></i>Manage Departments
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="admin-reports.php" class="btn btn-outline-info w-100">
                                <i class="fas fa-chart-bar me-2"></i>View Reports
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="system-logs.php" class="btn btn-outline-warning w-100">
                                <i class="fas fa-history me-2"></i>System Logs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status -->
        <div class="col-lg-6">
            <div class="card system-status-card">
                <div class="card-header bg-success text-white">
                    <h6 class="mb-0"><i class="fas fa-server me-2"></i>System Status</h6>
                </div>
                <div class="card-body">
                    <div class="status-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-database text-primary me-2"></i>Database</span>
                            <span class="badge bg-success">Online</span>
                        </div>
                    </div>
                    <div class="status-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-users text-success me-2"></i>Active Users</span>
                            <span class="badge bg-info" id="active_users_count">0</span>
                        </div>
                    </div>
                    <div class="status-item mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-clock text-warning me-2"></i>Last Update</span>
                            <span class="badge bg-secondary" id="last_update">Just now</span>
                        </div>
                    </div>
                    <div class="status-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-shield-alt text-danger me-2"></i>Security Status</span>
                            <span class="badge bg-success">Secure</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="footer">
    &copy; 2025 Rwanda Polytechnic | Admin Panel
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function animateValue(id, newValue) {
    const el = document.getElementById(id);
    const current = parseInt(el.innerText) || 0;
    $({val: current}).animate({val: newValue}, {
        duration: 600,
        step: function(val){ el.innerText = Math.floor(val); },
        complete: function(){ el.innerText = newValue; }
    });
}
function fetchTotals() {
    $('#connectionStatus').removeClass('d-none alert-danger alert-success').addClass('alert-info');
    $('#connectionMessage').text('Fetching data from database...');

    $.getJSON('admin-dashboard.php', {ajax: '1'}, function(data){
        $('#connectionStatus').removeClass('alert-info alert-danger').addClass('alert-success d-none');
        $('#connectionMessage').text('Data loaded successfully');

        animateValue('total_students', data.total_students);
        animateValue('total_lecturers', data.total_lecturers);
        animateValue('total_hods', data.total_hods);
        animateValue('total_departments', data.total_departments);
        animateValue('active_attendance', data.active_attendance);
        animateValue('total_sessions', data.total_sessions);
        animateValue('pending_requests', data.pending_requests);
        animateValue('today_records', data.today_records);

        // Update system status
        const totalUsers = data.total_students + data.total_lecturers + data.total_hods + 1; // +1 for admin
        $('#active_users_count').text(totalUsers);
        $('#last_update').text('Just now');

        // Hide status after 3 seconds
        setTimeout(function(){
            $('#connectionStatus').addClass('d-none');
        }, 3000);
    }).fail(function(xhr, status, error){
        $('#connectionStatus').removeClass('alert-info alert-success').addClass('alert-danger');
        $('#connectionMessage').text('Failed to fetch data: ' + error);
        $('#last_update').text('Connection failed');

        console.error('AJAX Error:', {
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText,
            error: error
        });
    });
}

function updateLastUpdateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    $('#last_update').text(timeString);
    $('#pageLastUpdate').text(timeString);
}

// Manual refresh button
$('#refreshDashboard').click(function(){
    const btn = $(this);
    const originalHtml = btn.html();
    btn.html('<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...').prop('disabled', true);
    fetchTotals();
    setTimeout(() => {
        btn.html(originalHtml).prop('disabled', false);
    }, 2000);
});

// Initial load
fetchTotals();

// Update every 30 seconds
setInterval(fetchTotals, 30000);

// Update time display every minute
setInterval(updateLastUpdateTime, 60000);
</script>

</body>
</html>
