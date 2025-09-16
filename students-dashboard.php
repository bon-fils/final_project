<?php
session_start();
require_once "config.php"; // $pdo connection

// Ensure student is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get student info
$stmt = $pdo->prepare("SELECT id, first_name, last_name, department_id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

// If student not found, destroy session and redirect
if (!$student) {
    session_destroy();
    header("Location: index.php?error=student_not_found");
    exit();
}

$student_id = $student['id'];
$student_name = $student['first_name'] . ' ' . $student['last_name'];

// Fetch department name
$deptStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
$deptStmt->execute([$student['department_id']]);
$department = $deptStmt->fetch()['name'] ?? 'Unknown';

// Attendance percentage
$totalStmt = $pdo->prepare("SELECT COUNT(*) as total FROM attendance_records WHERE student_id = ?");
$totalStmt->execute([$student_id]);
$totalSessions = $totalStmt->fetch()['total'] ?? 0;

$presentStmt = $pdo->prepare("SELECT COUNT(*) as present FROM attendance_records WHERE student_id = ? AND status = 'present'");
$presentStmt->execute([$student_id]);
$presentCount = $presentStmt->fetch()['present'] ?? 0;

$attendancePercentage = ($totalSessions > 0) ? round(($presentCount / $totalSessions) * 100) : 0;

// Pending leave requests
$leaveStmt = $pdo->prepare("SELECT COUNT(*) as pending FROM leave_requests WHERE student_id = ? AND status = 'pending'");
$leaveStmt->execute([$student_id]);
$pendingLeaves = $leaveStmt->fetch()['pending'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Dashboard | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<style>
body { font-family: 'Segoe UI', sans-serif; background-color: #f5f7fa; margin: 0; }
.sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background-color: #003366; color: white; padding-top: 20px; display: flex; flex-direction: column; }
.sidebar .logo { width: 80px; margin: 0 auto 10px; display: block; }
.sidebar h5 { text-align: center; margin-bottom: 5px; }
.sidebar hr { border-color: rgba(255,255,255,0.3); margin: 10px 0 20px; }
.sidebar a { display: block; padding: 12px 20px; color: #fff; text-decoration: none; font-size: 0.95rem; }
.sidebar a:hover, .sidebar a.active { background-color: #0059b3; }
.topbar { margin-left: 240px; background-color: #fff; padding: 12px 25px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
.topbar h5 { margin: 0; font-weight: bold; color: #003366; }
.main-content { margin-left: 240px; padding: 25px; min-height: calc(100vh - 70px); }
.widget { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); text-align: center; }
.widget h3 { font-weight: bold; font-size: 2rem; margin-bottom: 8px; color: #0066cc; }
.widget p { font-size: 0.95rem; color: #555; margin-bottom: 0; }
.nav-section { margin-top: 35px; display: flex; gap: 20px; flex-wrap: wrap; justify-content: center; }
.nav-card { flex: 1 1 200px; max-width: 230px; background: white; border-radius: 12px; padding: 20px 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); cursor: pointer; transition: transform 0.2s ease, background-color 0.3s ease; text-align: center; color: #003366; text-decoration: none; }
.nav-card:hover { background-color: #e6f0ff; transform: translateY(-3px); }
.nav-card i { font-size: 2.5rem; margin-bottom: 8px; color: #0066cc; }
.footer { text-align: center; margin-left: 240px; padding: 12px; font-size: 0.9rem; color: #666; background-color: #f0f0f0; border-top: 1px solid #ddd; }
@media (max-width:768px) { .sidebar{display:none;} .topbar,.main-content,.footer{margin-left:0;} }
</style>
</head>
<body>

<div class="sidebar">
    <div class="text-center">
        <img src="RP_Logo.jpeg" alt="RP Logo" class="logo rounded-circle" />
        <h5>👨‍🎓 <?= htmlspecialchars($student_name) ?></h5>
        <p class="text-center small"><?= htmlspecialchars($department) ?></p>
        <hr />
    </div>
    <a href="students-dashboard.php" class="active"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i> Attendance Records</a>
    <a href="request-leave.php"><i class="fas fa-file-signature me-2"></i> Request Leave</a>
    <a href="leave-status.php"><i class="fas fa-envelope-open-text me-2"></i> Leave Status</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar">
    <h5>Student Dashboard</h5>
    <span>RP Attendance System</span>
</div>

<div class="main-content">
    <div class="row g-4 justify-content-center">
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="widget">
                <h3><?= $attendancePercentage ?>%</h3>
                <p>Attendance Percentage</p>
            </div>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="widget">
                <h3><?= $pendingLeaves ?></h3>
                <p>Pending Leave Requests</p>
            </div>
        </div>
    </div>

    <div class="nav-section">
        <a href="attendance-records.php" class="nav-card">
            <i class="fas fa-calendar-check"></i>
            <h6>Attendance Records</h6>
        </a>
        <a href="request-leave.php" class="nav-card">
            <i class="fas fa-file-signature"></i>
            <h6>Request Leave</h6>
        </a>
        <a href="leave-status.php" class="nav-card">
            <i class="fas fa-envelope-open-text"></i>
            <h6>Leave Status</h6>
        </a>
    </div>
</div>

<div class="footer">
    &copy; 2025 Rwanda Polytechnic | Student Panel
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
