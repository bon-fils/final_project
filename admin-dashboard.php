<?php
session_start();
require_once "config.php";
require_once "session_check.php";

// Handle AJAX requests for live counts
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    try {
        $stmt = $pdo->query("
            SELECT 
                (SELECT COUNT(*) FROM students) AS total_students,
                (SELECT COUNT(*) FROM users WHERE role='lecturer') AS total_lecturers,
                (SELECT COUNT(*) FROM attendance_sessions WHERE status='active') AS active_attendance,
                (SELECT COUNT(*) FROM leave_requests WHERE status='pending') AS pending_requests
        ");
        $counts = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'total_students'    => (int) ($counts['total_students'] ?? 0),
            'total_lecturers'   => (int) ($counts['total_lecturers'] ?? 0),
            'active_attendance' => (int) ($counts['active_attendance'] ?? 0),
            'pending_requests'  => (int) ($counts['pending_requests'] ?? 0)
        ]);
    } catch (PDOException $e) {
        error_log("Dashboard AJAX error: " . $e->getMessage());
        echo json_encode([
            'total_students'    => 0,
            'total_lecturers'   => 0,
            'active_attendance' => 0,
            'pending_requests'  => 0
        ]);
    }
    exit;
}

// Initial counts
try {
    $stmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM students) AS total_students,
            (SELECT COUNT(*) FROM users WHERE role='lecturer') AS total_lecturers,
            (SELECT COUNT(*) FROM attendance_sessions WHERE status='active') AS active_attendance,
            (SELECT COUNT(*) FROM leave_requests WHERE status='pending') AS pending_requests
    ");
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_students    = (int) ($counts['total_students'] ?? 0);
    $total_lecturers   = (int) ($counts['total_lecturers'] ?? 0);
    $active_attendance = (int) ($counts['active_attendance'] ?? 0);
    $pending_requests  = (int) ($counts['pending_requests'] ?? 0);
} catch (PDOException $e) {
    $total_students = $total_lecturers = $active_attendance = $pending_requests = 0;
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
    transition: 0.2s;
}
.sidebar a:hover, .sidebar a.active { background-color: #0059b3; }
.topbar {
    margin-left: 250px;
    background: #fff;
    padding: 12px 30px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 900;
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
.bg-students { background: linear-gradient(45deg, #007bff, #3399ff); }
.bg-lecturers { background: linear-gradient(45deg, #28a745, #5cd65c); }
.bg-attendance { background: linear-gradient(45deg, #fd7e14, #ffa94d); }
.bg-requests { background: linear-gradient(45deg, #dc3545, #ff6b81); }
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
    .row.dashboard-row { flex-direction: column; }
    .dashboard-card { width: 100%; }
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
    <h5 class="m-0 fw-bold">Admin Dashboard</h5>
    <span>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></span>
</div>

<div class="main-content container-fluid">
    <!-- First Row -->
    <div class="row dashboard-row">
        <div class="dashboard-card bg-students">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6>Total Students</h6>
                    <h4 id="total_students"><?= $total_students ?></h4>
                </div>
                <i class="fas fa-users fa-3x"></i>
            </div>
        </div>
        <div class="dashboard-card bg-lecturers">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6>Total Lecturers</h6>
                    <h4 id="total_lecturers"><?= $total_lecturers ?></h4>
                </div>
                <i class="fas fa-chalkboard-teacher fa-3x"></i>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="row dashboard-row">
        <div class="dashboard-card bg-attendance">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6>Active Attendance</h6>
                    <h4 id="active_attendance"><?= $active_attendance ?></h4>
                </div>
                <i class="fas fa-video fa-3x"></i>
            </div>
        </div>
        <div class="dashboard-card bg-requests">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6>Pending Requests</h6>
                    <h4 id="pending_requests"><?= $pending_requests ?></h4>
                </div>
                <i class="fas fa-envelope-open-text fa-3x"></i>
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
    $.getJSON('admin-dashboard.php', {ajax: '1'}, function(data){
        animateValue('total_students', data.total_students);
        animateValue('total_lecturers', data.total_lecturers);
        animateValue('active_attendance', data.active_attendance);
        animateValue('pending_requests', data.pending_requests);
    });
}
fetchTotals();
setInterval(fetchTotals, 5000);
</script>

</body>
</html>
