<?php
session_start();
require_once "config.php"; // $pdo connection

// Ensure user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: index.php");
    exit();
}

// Fetch lecturer info
$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Only allow lecturers
if (!$user || $user['role'] !== 'lecturer') {
    session_destroy();
    header("Location: index.php");
    exit();
}

$lecturer_name = $user['username'];
$lecturer_email = $user['email'];

// Function to get dashboard data
function getDashboardData($pdo, $user_id) {
    // Assigned Courses
    $coursesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM courses WHERE lecturer_id = ?");
    $coursesStmt->execute([$user_id]);
    $assignedCourses = $coursesStmt->fetch()['total'] ?? 0;

    // Today's Attendance
    $today = date('Y-m-d');
    $attendanceStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM attendance_records ar
        INNER JOIN attendance_sessions s ON ar.session_id = s.id
        WHERE s.lecturer_id = ? AND DATE(ar.recorded_at) = ?
    ");
    $attendanceStmt->execute([$user_id, $today]);
    $todayAttendance = $attendanceStmt->fetch()['total'] ?? 0;

    // Pending Leaves
    $leaveStmt = $pdo->prepare("
        SELECT COUNT(*) as pending
        FROM leave_requests lr
        INNER JOIN students st ON lr.student_id = st.id
        INNER JOIN courses c ON st.option_id = c.id
        WHERE c.lecturer_id = ? AND lr.status = 'pending'
    ");
    $leaveStmt->execute([$user_id]);
    $pendingLeaves = $leaveStmt->fetch()['pending'] ?? 0;

    return [
        'assignedCourses' => $assignedCourses,
        'todayAttendance' => $todayAttendance,
        'pendingLeaves' => $pendingLeaves
    ];
}

// Handle AJAX request
if(isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    echo json_encode(getDashboardData($pdo, $user_id));
    exit();
}

// Initial dashboard data
$data = getDashboardData($pdo, $user_id);

?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Lecturer Dashboard | RP Attendance System</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

<style>
body { font-family: 'Segoe UI', sans-serif; background-color: #f5f7fa; margin: 0; }
.sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100vh; background-color: #003366; color: white; padding-top: 20px; }
.sidebar a { display: block; padding: 12px 20px; color: #fff; text-decoration: none; }
.sidebar a:hover, .sidebar a.active { background-color: #0059b3; }
.topbar { margin-left: 250px; background-color: #fff; padding: 10px 30px; border-bottom: 1px solid #ddd; }
.main-content { margin-left: 250px; padding: 30px; }
.card { border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px; }
.widget { background-color: #fff; border-radius: 10px; padding: 20px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.widget h3 { margin-bottom: 10px; color: #0066cc; }
.widget p { font-size: 1.5rem; font-weight: 700; margin: 0; color: #003366; }
.nav-links a { display: inline-block; margin-right: 15px; color: #0066cc; font-weight: 600; text-decoration: none; border-bottom: 2px solid transparent; padding-bottom: 3px; transition: border-color 0.3s ease; }
.nav-links a:hover, .nav-links a.active { border-color: #0066cc; color: #004b99; }
.footer { text-align: center; margin-left: 250px; padding: 15px; font-size: 0.9rem; color: #666; background-color: #f0f0f0; }
@media (max-width: 768px) { .sidebar,.topbar,.main-content,.footer { margin-left:0 !important; width:100%; } .sidebar{display:none;} }
</style>
</head>

<body>

<div class="sidebar">
    <div class="text-center mb-4">
        <h4>👨‍🏫 Lecturer</h4>
        <p class="small"><?= htmlspecialchars($lecturer_email) ?></p>
        <hr style="border-color: #ffffff66;">
    </div>
    <a href="lecturer-dashboard.php" class="active"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="my-courses.php"><i class="fas fa-book me-2"></i> My Courses</a>
    <a href="attendance-session.php"><i class="fas fa-video me-2"></i> Attendance Session</a>
    <a href="attendance-reports.php"><i class="fas fa-chart-line me-2"></i> Attendance Reports</a>
    <a href="leave-requests.php"><i class="fas fa-envelope me-2"></i> Leave Requests</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Lecturer Dashboard</h5>
    <span><?= htmlspecialchars($lecturer_email) ?></span>
</div>

<div class="main-content">
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="widget">
                <h3>Assigned Courses</h3>
                <p id="assignedCourses"><?= $data['assignedCourses'] ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="widget">
                <h3>Today's Attendance</h3>
                <p id="todayAttendance"><?= $data['todayAttendance'] ?></p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="widget">
                <h3>Pending Leave Requests</h3>
                <p id="pendingLeaves"><?= $data['pendingLeaves'] ?></p>
            </div>
        </div>
    </div>

    <div class="nav-links mb-3">
        <a href="my-courses.php" class="active">My Courses</a>
        <a href="attendance-session.php">Start/Stop Session</a>
        <a href="attendance-reports.php">Attendance Reports</a>
        <a href="leave-requests.php">Leave Requests</a>
    </div>

    <div class="card p-4">
        <h6>Welcome back, <?= htmlspecialchars($lecturer_name) ?>!</h6>
        <p>Use the navigation above to manage your courses, take attendance, and handle leave requests.</p>
    </div>
</div>

<div class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function refreshDashboard() {
    fetch('lecturer-dashboard.php?ajax=1')
    .then(res => res.json())
    .then(data => {
        document.getElementById('assignedCourses').textContent = data.assignedCourses;
        document.getElementById('todayAttendance').textContent = data.todayAttendance;
        document.getElementById('pendingLeaves').textContent = data.pendingLeaves;
    })
    .catch(err => console.log('Dashboard refresh error:', err));
}

// Auto-refresh every 30 seconds
setInterval(refreshDashboard, 30000);
</script>

</body>
</html>
