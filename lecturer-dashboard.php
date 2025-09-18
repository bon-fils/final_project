<?php
session_start();
require_once "config.php"; // $pdo connection
require_once "session_check.php";
require_role(['lecturer']);

// Fetch lecturer info
$stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Only allow lecturers (double check)
if (!$user || $user['role'] !== 'lecturer') {
    error_log("Lecturer dashboard access denied. User: " . ($user ? json_encode($user) : 'null') . ", user_id: " . ($user_id ?? 'null') . ", session role: " . ($_SESSION['role'] ?? 'null'));
    session_destroy();
    header("Location: index.php");
    exit();
}

$lecturer_name = $user['username'];
$lecturer_email = $user['email'];

// Function to get recent activities
function getRecentActivities($pdo, $user_id) {
    $activities = [];

    // Recent attendance sessions
    $sessionsStmt = $pdo->prepare("
        SELECT 'session' as type, s.course_name, s.created_at, COUNT(ar.id) as attendance_count
        FROM attendance_sessions s
        LEFT JOIN attendance_records ar ON s.id = ar.session_id
        WHERE s.lecturer_id = ?
        GROUP BY s.id
        ORDER BY s.created_at DESC
        LIMIT 3
    ");
    $sessionsStmt->execute([$user_id]);
    $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sessions as $session) {
        $activities[] = [
            'type' => 'session',
            'title' => 'Started session for ' . htmlspecialchars($session['course_name']),
            'detail' => $session['attendance_count'] . ' students attended',
            'time' => date('M d, H:i', strtotime($session['created_at']))
        ];
    }

    // Recent leave requests
    $leavesStmt = $pdo->prepare("
        SELECT 'leave' as type, lr.reason, lr.status, lr.created_at, st.first_name, st.last_name
        FROM leave_requests lr
        INNER JOIN students st ON lr.student_id = st.id
        INNER JOIN courses c ON st.option_id = c.id
        WHERE c.lecturer_id = ?
        ORDER BY lr.created_at DESC
        LIMIT 3
    ");
    $leavesStmt->execute([$user_id]);
    $leaves = $leavesStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($leaves as $leave) {
        $activities[] = [
            'type' => 'leave',
            'title' => htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) . ' requested leave',
            'detail' => 'Reason: ' . htmlspecialchars($leave['reason']) . ' (' . $leave['status'] . ')',
            'time' => date('M d, H:i', strtotime($leave['created_at']))
        ];
    }

    // Sort by time descending and limit to 5
    usort($activities, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    return array_slice($activities, 0, 5);
}

// Function to get dashboard data
function getDashboardData($pdo, $user_id) {
   // Assigned Courses
   $coursesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM courses WHERE lecturer_id = ?");
   $coursesStmt->execute([$user_id]);
   $assignedCourses = $coursesStmt->fetch()['total'] ?? 0;

   // Total Students
   $studentsStmt = $pdo->prepare("
       SELECT COUNT(DISTINCT st.id) as total
       FROM students st
       INNER JOIN courses c ON st.option_id = c.id
       WHERE c.lecturer_id = ?
   ");
   $studentsStmt->execute([$user_id]);
   $totalStudents = $studentsStmt->fetch()['total'] ?? 0;

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

   // This Week's Attendance
   $weekStart = date('Y-m-d', strtotime('monday this week'));
   $weekEnd = date('Y-m-d', strtotime('sunday this week'));
   $weekAttendanceStmt = $pdo->prepare("
       SELECT COUNT(*) as total
       FROM attendance_records ar
       INNER JOIN attendance_sessions s ON ar.session_id = s.id
       WHERE s.lecturer_id = ? AND DATE(ar.recorded_at) BETWEEN ? AND ?
   ");
   $weekAttendanceStmt->execute([$user_id, $weekStart, $weekEnd]);
   $weekAttendance = $weekAttendanceStmt->fetch()['total'] ?? 0;

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

   // Approved Leaves
   $approvedLeaveStmt = $pdo->prepare("
       SELECT COUNT(*) as approved
       FROM leave_requests lr
       INNER JOIN students st ON lr.student_id = st.id
       INNER JOIN courses c ON st.option_id = c.id
       WHERE c.lecturer_id = ? AND lr.status = 'approved'
   ");
   $approvedLeaveStmt->execute([$user_id]);
   $approvedLeaves = $approvedLeaveStmt->fetch()['approved'] ?? 0;

   // Total Sessions
   $sessionsStmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance_sessions WHERE lecturer_id = ?");
   $sessionsStmt->execute([$user_id]);
   $totalSessions = $sessionsStmt->fetch()['total'] ?? 0;

   return [
       'assignedCourses' => $assignedCourses,
       'totalStudents' => $totalStudents,
       'todayAttendance' => $todayAttendance,
       'weekAttendance' => $weekAttendance,
       'pendingLeaves' => $pendingLeaves,
       'approvedLeaves' => $approvedLeaves,
       'totalSessions' => $totalSessions
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
$activities = getRecentActivities($pdo, $user_id);

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
.sidebar a { display: block; padding: 12px 20px; color: #fff; text-decoration: none; transition: all 0.3s ease; }
.sidebar a:hover, .sidebar a.active { background-color: #0059b3; border-radius: 6px; }
.topbar { margin-left: 250px; background-color: #fff; padding: 15px 30px; border-bottom: 1px solid #ddd; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.main-content { margin-left: 250px; padding: 30px; }
.card { border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-bottom: 30px; transition: transform 0.3s ease; }
.card:hover { transform: translateY(-2px); }
.widget { background-color: #fff; border-radius: 12px; padding: 25px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); transition: all 0.3s ease; position: relative; overflow: hidden; }
.widget:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
.widget::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #003366, #0059b3); }
.widget h3 { margin-bottom: 15px; color: #003366; font-size: 1.1rem; }
.widget p { font-size: 2rem; font-weight: 700; margin: 0; color: #003366; }
.widget.quick-actions { text-align: left; }
.widget.quick-actions h3 { text-align: center; }
.nav-links a { display: inline-block; margin-right: 15px; color: #0066cc; font-weight: 600; text-decoration: none; border-bottom: 2px solid transparent; padding-bottom: 3px; transition: all 0.3s ease; }
.nav-links a:hover, .nav-links a.active { border-color: #0066cc; color: #004b99; transform: translateY(-1px); }
.activity-item { transition: background-color 0.3s ease; padding: 8px; border-radius: 6px; }
.activity-item:hover { background-color: #f8f9fa; }
.footer { text-align: center; margin-left: 250px; padding: 15px; font-size: 0.9rem; color: #666; background-color: #f0f0f0; }
@media (max-width: 768px) { .sidebar,.topbar,.main-content,.footer { margin-left:0 !important; width:100%; } .sidebar{display:none;} }
@media (max-width: 992px) { .col-lg-3 { margin-bottom: 20px; } }
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
        <div class="col-lg-3 col-md-6">
            <div class="widget">
                <i class="fas fa-book fa-2x text-primary mb-2"></i>
                <h3>Assigned Courses</h3>
                <p id="assignedCourses" aria-label="Number of assigned courses"><?= $data['assignedCourses'] ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="widget">
                <i class="fas fa-users fa-2x text-success mb-2"></i>
                <h3>Total Students</h3>
                <p id="totalStudents" aria-label="Total number of students"><?= $data['totalStudents'] ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="widget">
                <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                <h3>Today's Attendance</h3>
                <p id="todayAttendance" aria-label="Today's attendance count"><?= $data['todayAttendance'] ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="widget">
                <i class="fas fa-calendar-week fa-2x text-warning mb-2"></i>
                <h3>This Week's Attendance</h3>
                <p id="weekAttendance" aria-label="This week's attendance count"><?= $data['weekAttendance'] ?></p>
            </div>
        </div>
    </div>
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="widget">
                <i class="fas fa-clock fa-2x text-secondary mb-2"></i>
                <h3>Pending Leaves</h3>
                <p id="pendingLeaves" aria-label="Number of pending leave requests"><?= $data['pendingLeaves'] ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="widget">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h3>Approved Leaves</h3>
                <p id="approvedLeaves" aria-label="Number of approved leave requests"><?= $data['approvedLeaves'] ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="widget">
                <i class="fas fa-video fa-2x text-primary mb-2"></i>
                <h3>Total Sessions</h3>
                <p id="totalSessions" aria-label="Total number of attendance sessions"><?= $data['totalSessions'] ?></p>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="widget quick-actions">
                <h3>Quick Actions</h3>
                <div class="d-flex flex-column gap-2">
                    <a href="attendance-session.php" class="btn btn-sm btn-primary"><i class="fas fa-play me-1"></i>Start Session</a>
                    <a href="my-courses.php" class="btn btn-sm btn-secondary"><i class="fas fa-book me-1"></i>My Courses</a>
                </div>
            </div>
        </div>
    </div>

    <div class="nav-links mb-3">
        <a href="my-courses.php" class="active">My Courses</a>
        <a href="attendance-session.php">Start/Stop Session</a>
        <a href="attendance-reports.php">Attendance Reports</a>
        <a href="leave-requests.php">Leave Requests</a>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card p-4">
                <h6>Welcome back, <?= htmlspecialchars($lecturer_name) ?>!</h6>
                <p>Use the navigation above to manage your courses, take attendance, and handle leave requests.</p>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-4">
                <h6 class="mb-3"><i class="fas fa-history me-2"></i>Recent Activities</h6>
                <div id="recentActivities">
                    <?php if (empty($activities)): ?>
                        <p class="text-muted small">No recent activities</p>
                    <?php else: ?>
                        <?php foreach ($activities as $activity): ?>
                            <div class="activity-item mb-3 pb-2 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <small class="fw-bold">
                                            <i class="fas fa-<?= $activity['type'] === 'session' ? 'video' : 'envelope' ?> me-1"></i>
                                            <?= $activity['title'] ?>
                                        </small>
                                        <br>
                                        <small class="text-muted"><?= $activity['detail'] ?></small>
                                    </div>
                                    <small class="text-muted ms-2 flex-shrink-0"><?= $activity['time'] ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function refreshDashboard() {
    // Show loading state
    const widgets = document.querySelectorAll('.widget p[id]');
    widgets.forEach(p => {
        p.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    });

    fetch('lecturer-dashboard.php?ajax=1')
    .then(res => {
        if (!res.ok) throw new Error('Network response was not ok');
        return res.json();
    })
    .then(data => {
        document.getElementById('assignedCourses').textContent = data.assignedCourses;
        document.getElementById('totalStudents').textContent = data.totalStudents;
        document.getElementById('todayAttendance').textContent = data.todayAttendance;
        document.getElementById('weekAttendance').textContent = data.weekAttendance;
        document.getElementById('pendingLeaves').textContent = data.pendingLeaves;
        document.getElementById('approvedLeaves').textContent = data.approvedLeaves;
        document.getElementById('totalSessions').textContent = data.totalSessions;
    })
    .catch(err => {
        console.log('Dashboard refresh error:', err);
        // Reset loading state on error
        widgets.forEach(p => {
            if (p.innerHTML.includes('fa-spinner')) {
                p.textContent = 'Error';
            }
        });
    });
}

// Auto-refresh every 30 seconds
setInterval(refreshDashboard, 30000);

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    refreshDashboard();
});
</script>

</body>
</html>
