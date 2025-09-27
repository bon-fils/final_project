<?php
session_start();
require_once "config.php"; // $pdo connection
require_once "session_check.php";
require_role(['student']);

$user_id = $_SESSION['user_id'];

// Enhanced student data retrieval with error handling
try {
    // Get comprehensive student info
    $stmt = $pdo->prepare("
        SELECT
            s.id, s.first_name, s.last_name, s.email, s.reg_no, s.year_level, s.sex,
            s.photo, s.telephone, s.department_id, s.option_id,
            d.name as department_name,
            o.name as option_name,
            u.username, u.created_at as account_created
        FROM students s
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN options o ON s.option_id = o.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    // If student not found, destroy session and redirect
    if (!$student) {
        session_destroy();
        header("Location: index.php?error=student_not_found");
        exit();
    }

    $student_id = $student['id'];
    $student_name = $student['first_name'] . ' ' . $student['last_name'];

    // Comprehensive attendance statistics
    $attendanceStats = $pdo->prepare("
        SELECT
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            ROUND(AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END), 1) as attendance_percentage,
            MAX(recorded_at) as last_attendance
        FROM attendance_records
        WHERE student_id = ?
    ");
    $attendanceStats->execute([$student_id]);
    $attendance = $attendanceStats->fetch(PDO::FETCH_ASSOC);

    // Leave request statistics
    $leaveStats = $pdo->prepare("
        SELECT
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
            MAX(requested_at) as last_request
        FROM leave_requests
        WHERE student_id = ?
    ");
    $leaveStats->execute([$student_id]);
    $leave = $leaveStats->fetch(PDO::FETCH_ASSOC);

    // Recent attendance records (last 10)
    $recentAttendance = $pdo->prepare("
        SELECT
            ar.status, ar.recorded_at,
            sess.session_date, sess.start_time, sess.end_time,
            l.first_name as lecturer_fname, l.last_name as lecturer_lname,
            c.name as course_name
        FROM attendance_records ar
        LEFT JOIN attendance_sessions sess ON ar.session_id = sess.id
        LEFT JOIN lecturers l ON sess.lecturer_id = l.id
        LEFT JOIN courses c ON sess.course_id = c.id
        WHERE ar.student_id = ?
        ORDER BY ar.recorded_at DESC
        LIMIT 10
    ");
    $recentAttendance->execute([$student_id]);
    $recent_records = $recentAttendance->fetchAll(PDO::FETCH_ASSOC);

    // Today's schedule
    $todaySchedule = $pdo->prepare("
        SELECT
            sess.session_date, sess.start_time, sess.end_time,
            c.name as course_name,
            l.first_name as lecturer_fname, l.last_name as lecturer_lname,
            r.name as room_name
        FROM attendance_sessions sess
        LEFT JOIN courses c ON sess.course_id = c.id
        LEFT JOIN lecturers l ON sess.lecturer_id = l.id
        LEFT JOIN rooms r ON sess.room_id = r.id
        WHERE sess.session_date = CURDATE()
        AND sess.option_id = ?
        ORDER BY sess.start_time
    ");
    $todaySchedule->execute([$student['option_id']]);
    $today_sessions = $todaySchedule->fetchAll(PDO::FETCH_ASSOC);

    // Academic performance summary
    $performanceStats = $pdo->prepare("
        SELECT
            COUNT(DISTINCT sess.course_id) as enrolled_courses,
            AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END) as avg_course_attendance
        FROM attendance_sessions sess
        LEFT JOIN attendance_records ar ON sess.id = ar.session_id AND ar.student_id = ?
        WHERE sess.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND sess.option_id = ?
    ");
    $performanceStats->execute([$student_id, $student['option_id']]);
    $performance = $performanceStats->fetch(PDO::FETCH_ASSOC);

    // Notifications/Alerts
    $notifications = [];

    // Low attendance alert
    if (($attendance['attendance_percentage'] ?? 0) < 75) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'exclamation-triangle',
            'title' => 'Low Attendance Alert',
            'message' => 'Your attendance is below 75%. Please improve to avoid academic penalties.',
            'action' => 'attendance-records.php'
        ];
    }

    // Pending leave request reminder
    if (($leave['pending_count'] ?? 0) > 0) {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'clock',
            'title' => 'Pending Leave Request',
            'message' => 'You have ' . $leave['pending_count'] . ' pending leave request(s) awaiting approval.',
            'action' => 'leave-status.php'
        ];
    }

    // Today's classes reminder
    if (count($today_sessions) > 0) {
        $notifications[] = [
            'type' => 'success',
            'icon' => 'calendar-day',
            'title' => 'Today\'s Schedule',
            'message' => 'You have ' . count($today_sessions) . ' class(es) scheduled for today.',
            'action' => '#schedule'
        ];
    }

    // Welcome message for new students
    $accountAge = (time() - strtotime($student['account_created'])) / (60 * 60 * 24); // days
    if ($accountAge < 7) {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'user-plus',
            'title' => 'Welcome to RP Attendance System!',
            'message' => 'Complete your profile and familiarize yourself with the system.',
            'action' => '#profile'
        ];
    }

} catch (PDOException $e) {
    // Log error and provide fallback
    error_log("Student dashboard error: " . $e->getMessage());

    // Provide minimal fallback data
    $student = ['first_name' => 'Student', 'last_name' => '', 'department_name' => 'Unknown'];
    $student_name = 'Student';
    $attendance = ['total_sessions' => 0, 'present_count' => 0, 'attendance_percentage' => 0];
    $leave = ['pending_count' => 0, 'total_requests' => 0];
    $recent_records = [];
    $today_sessions = [];
    $performance = ['enrolled_courses' => 0, 'avg_course_attendance' => 0];
    $notifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Student Dashboard | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ===== ROOT VARIABLES ===== */
:root {
  --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
  --primary-gradient-hover: linear-gradient(135deg, #0052a3 0%, #002b50 100%);
  --secondary-gradient: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
  --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
  --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
  --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
  --info-gradient: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
  --light-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  --dark-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
  --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
  --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
  --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
  --border-radius: 12px;
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== BODY & CONTAINER ===== */
body {
  background: linear-gradient(to right, #0066cc, #003366);
  min-height: 100vh;
  font-family: 'Inter', 'Segoe UI', sans-serif;
  margin: 0;
  position: relative;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>');
  pointer-events: none;
  z-index: -1;
}

/* ===== SIDEBAR ===== */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: 280px;
  height: 100vh;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  color: #333;
  padding: 30px 0;
  box-shadow: var(--shadow-medium);
  border-right: 1px solid rgba(255, 255, 255, 0.2);
  z-index: 1000;
  overflow-y: auto;
}

.sidebar-header {
  text-align: center;
  padding: 0 20px 30px;
  border-bottom: 1px solid rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

.sidebar-header .student-avatar {
  width: 80px;
  height: 80px;
  border-radius: 50%;
  background: var(--primary-gradient);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 15px;
  color: white;
  font-size: 2rem;
  box-shadow: var(--shadow-medium);
}

.sidebar-header h5 {
  color: #0066cc;
  font-weight: 700;
  margin-bottom: 5px;
  font-size: 1.1rem;
}

.sidebar-header .department-badge {
  background: var(--success-gradient);
  color: white;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  display: inline-block;
  margin-top: 5px;
}

.sidebar a {
  display: block;
  padding: 15px 25px;
  color: #666;
  text-decoration: none;
  font-weight: 500;
  border-radius: 0 25px 25px 0;
  margin: 5px 0;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.sidebar a:hover, .sidebar a.active {
  background: var(--primary-gradient);
  color: white;
  padding-left: 35px;
  transform: translateX(5px);
}

.sidebar a i {
  margin-right: 12px;
  width: 20px;
  text-align: center;
}

/* ===== TOPBAR ===== */
.topbar {
  margin-left: 280px;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  padding: 20px 30px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 900;
  box-shadow: var(--shadow-light);
}

.topbar h5 {
  margin: 0;
  font-weight: 600;
  color: #333;
}

.topbar .user-info {
  display: flex;
  align-items: center;
  gap: 15px;
}

.topbar .notification-bell {
  position: relative;
  cursor: pointer;
  padding: 8px;
  border-radius: 50%;
  transition: var(--transition);
}

.topbar .notification-bell:hover {
  background: rgba(0, 102, 204, 0.1);
}

.notification-count {
  position: absolute;
  top: -5px;
  right: -5px;
  background: var(--danger-gradient);
  color: white;
  border-radius: 50%;
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.7rem;
  font-weight: 600;
}

/* ===== MAIN CONTENT ===== */
.main-content {
  margin-left: 280px;
  padding: 40px 30px;
  min-height: calc(100vh - 80px);
}

/* ===== WELCOME SECTION ===== */
.welcome-section {
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border-radius: var(--border-radius);
  padding: 30px;
  margin-bottom: 30px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: var(--shadow-light);
}

.welcome-content {
  display: flex;
  align-items: center;
  gap: 30px;
}

.welcome-text h2 {
  color: #333;
  font-weight: 700;
  margin-bottom: 10px;
}

.welcome-text p {
  color: #666;
  margin-bottom: 0;
  font-size: 1.1rem;
}

.welcome-avatar {
  width: 100px;
  height: 100px;
  border-radius: 50%;
  background: var(--primary-gradient);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 2.5rem;
  box-shadow: var(--shadow-medium);
}

/* ===== STATISTICS CARDS ===== */
.stats-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 30px;
  margin-bottom: 40px;
}

.stat-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: var(--border-radius);
  padding: 30px;
  box-shadow: var(--shadow-light);
  border: 1px solid rgba(255, 255, 255, 0.2);
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--primary-gradient);
  opacity: 0;
  transition: var(--transition);
}

.stat-card:hover {
  transform: translateY(-8px) scale(1.02);
  box-shadow: var(--shadow-heavy);
}

.stat-card:hover::before {
  opacity: 1;
}

.stat-card .icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  margin-bottom: 20px;
  box-shadow: var(--shadow-medium);
}

.stat-card.attendance .icon {
  background: var(--success-gradient);
  color: white;
}

.stat-card.leave .icon {
  background: var(--warning-gradient);
  color: white;
}

.stat-card.courses .icon {
  background: var(--info-gradient);
  color: white;
}

.stat-card.performance .icon {
  background: var(--primary-gradient);
  color: white;
}

.stat-card h3 {
  font-size: 2.5rem;
  font-weight: 700;
  color: #333;
  margin-bottom: 5px;
}

.stat-card p {
  color: #666;
  font-size: 0.95rem;
  margin-bottom: 0;
  font-weight: 500;
}

/* ===== NOTIFICATIONS ===== */
.notifications-section {
  margin-bottom: 40px;
}

.notification-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: var(--border-radius);
  padding: 25px;
  margin-bottom: 15px;
  border-left: 4px solid #0066cc;
  box-shadow: var(--shadow-light);
  transition: var(--transition);
}

.notification-card:hover {
  transform: translateX(5px);
  box-shadow: var(--shadow-medium);
}

.notification-card.warning {
  border-left-color: #ffc107;
}

.notification-card.success {
  border-left-color: #198754;
}

.notification-card.info {
  border-left-color: #0dcaf0;
}

.notification-card .icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 15px;
  flex-shrink: 0;
}

.notification-card.warning .icon {
  background: var(--warning-gradient);
}

.notification-card.success .icon {
  background: var(--success-gradient);
}

.notification-card.info .icon {
  background: var(--info-gradient);
}

.notification-card h6 {
  font-weight: 600;
  margin-bottom: 5px;
  color: #333;
}

.notification-card p {
  color: #666;
  margin-bottom: 0;
  font-size: 0.9rem;
}

/* ===== QUICK ACTIONS ===== */
.quick-actions-section {
  margin-bottom: 40px;
}

.quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
}

.action-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: var(--border-radius);
  padding: 25px;
  text-align: center;
  text-decoration: none;
  color: #333;
  transition: var(--transition);
  box-shadow: var(--shadow-light);
  border: 1px solid rgba(255, 255, 255, 0.2);
  position: relative;
  overflow: hidden;
}

.action-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--primary-gradient);
  opacity: 0;
  transition: var(--transition);
}

.action-card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-medium);
  color: white;
}

.action-card:hover::before {
  opacity: 0.9;
}

.action-card .icon {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 15px;
  font-size: 1.5rem;
  background: rgba(0, 102, 204, 0.1);
  color: #0066cc;
  transition: var(--transition);
}

.action-card:hover .icon {
  background: rgba(255, 255, 255, 0.2);
  color: white;
  transform: scale(1.1);
}

.action-card h6 {
  font-weight: 600;
  margin-bottom: 0;
  position: relative;
  z-index: 2;
}

/* ===== SCHEDULE SECTION ===== */
.schedule-section {
  margin-bottom: 40px;
}

.schedule-card {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-light);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.schedule-header {
  background: var(--primary-gradient);
  color: white;
  padding: 20px 25px;
  border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.schedule-header h6 {
  margin: 0;
  font-weight: 600;
}

.schedule-body {
  padding: 25px;
}

.schedule-item {
  display: flex;
  align-items: center;
  padding: 15px 0;
  border-bottom: 1px solid rgba(0,0,0,0.05);
}

.schedule-item:last-child {
  border-bottom: none;
}

.schedule-time {
  width: 80px;
  font-weight: 600;
  color: #0066cc;
  font-size: 0.9rem;
}

.schedule-content {
  flex: 1;
  margin-left: 20px;
}

.schedule-content h6 {
  margin: 0 0 5px 0;
  font-weight: 600;
  color: #333;
}

.schedule-content small {
  color: #666;
}

/* ===== FOOTER ===== */
.footer {
  text-align: center;
  margin-left: 280px;
  padding: 20px;
  font-size: 0.9rem;
  color: #666;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: var(--transition);
  }

  .sidebar.show {
    transform: translateX(0);
  }

  .topbar, .main-content, .footer {
    margin-left: 0 !important;
  }

  .topbar {
    padding: 15px 20px;
  }

  .main-content {
    padding: 20px 15px;
  }

  .welcome-content {
    flex-direction: column;
    text-align: center;
    gap: 20px;
  }

  .stats-row {
    grid-template-columns: 1fr;
    gap: 20px;
  }

  .quick-actions-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
  }

  .schedule-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
  }

  .schedule-time {
    width: auto;
  }
}

@media (max-width: 576px) {
  .welcome-section {
    padding: 20px;
  }

  .stat-card {
    padding: 20px;
  }

  .stat-card h3 {
    font-size: 2rem;
  }

  .action-card {
    padding: 20px;
  }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.stat-card, .action-card, .notification-card, .schedule-card {
  animation: fadeInUp 0.6s ease-out;
}

.stat-card:nth-child(1) { animation-delay: 0.1s; }
.stat-card:nth-child(2) { animation-delay: 0.2s; }
.stat-card:nth-child(3) { animation-delay: 0.3s; }
.stat-card:nth-child(4) { animation-delay: 0.4s; }

/* ===== CUSTOM SCROLLBAR ===== */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: var(--primary-gradient);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #0052a3 0%, #002b50 100%);
}
</style>
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="student-avatar">
            <i class="fas fa-user-graduate"></i>
        </div>
        <h5><?php echo htmlspecialchars($student_name); ?></h5>
        <div class="department-badge">
            <?php echo htmlspecialchars($student['department_name'] ?? 'Department'); ?>
        </div>
    </div>
    <a href="students-dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
    <a href="attendance-records.php"><i class="fas fa-calendar-check"></i> Attendance Records</a>
    <a href="request-leave.php"><i class="fas fa-file-signature"></i> Request Leave</a>
    <a href="leave-status.php"><i class="fas fa-envelope-open-text"></i> Leave Status</a>
    <a href="my-courses.php"><i class="fas fa-book"></i> My Courses</a>
    <a href="index.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Topbar -->
<div class="topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-secondary d-md-none me-3" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-tachometer-alt me-2 text-primary"></i>Student Dashboard
        </h5>
    </div>
    <div class="user-info">
        <div class="notification-bell" onclick="toggleNotifications()">
            <i class="fas fa-bell"></i>
            <?php if (count($notifications) > 0): ?>
                <span class="notification-count"><?php echo count($notifications); ?></span>
            <?php endif; ?>
        </div>
        <div class="text-end">
            <small class="text-muted d-block">Welcome back</small>
            <span class="fw-semibold"><?php echo htmlspecialchars($student['first_name']); ?></span>
        </div>
        <div class="user-avatar">
            <i class="fas fa-user-circle fa-2x text-primary"></i>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Welcome Section -->
    <div class="welcome-section">
        <div class="welcome-content">
            <div class="welcome-avatar">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="welcome-text">
                <h2>Welcome back, <?php echo htmlspecialchars($student['first_name']); ?>! 👋</h2>
                <p>Here's your academic overview for today. Stay focused and keep up the great work!</p>
                <div class="d-flex gap-3 mt-3">
                    <div class="text-muted small">
                        <i class="fas fa-calendar me-1"></i><?php echo date('l, F j, Y'); ?>
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-clock me-1"></i><?php echo date('H:i'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-row">
        <!-- Attendance Rate -->
        <div class="stat-card attendance">
            <div class="icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h3><?php echo htmlspecialchars($attendance['attendance_percentage'] ?? 0); ?>%</h3>
            <p>Attendance Rate</p>
            <div class="progress mt-2" style="height: 4px;">
                <div class="progress-bar bg-success" style="width: <?php echo htmlspecialchars($attendance['attendance_percentage'] ?? 0); ?>%"></div>
            </div>
            <small class="text-muted mt-1 d-block">
                <?php echo htmlspecialchars($attendance['present_count'] ?? 0); ?>/<?php echo htmlspecialchars($attendance['total_sessions'] ?? 0); ?> sessions
            </small>
        </div>

        <!-- Pending Leave Requests -->
        <div class="stat-card leave">
            <div class="icon">
                <i class="fas fa-envelope-open-text"></i>
            </div>
            <h3><?php echo htmlspecialchars($leave['pending_count'] ?? 0); ?></h3>
            <p>Pending Leave Requests</p>
            <small class="text-muted">
                <?php echo htmlspecialchars($leave['approved_count'] ?? 0); ?> approved,
                <?php echo htmlspecialchars($leave['rejected_count'] ?? 0); ?> rejected
            </small>
        </div>

        <!-- Enrolled Courses -->
        <div class="stat-card courses">
            <div class="icon">
                <i class="fas fa-book"></i>
            </div>
            <h3><?php echo htmlspecialchars($performance['enrolled_courses'] ?? 0); ?></h3>
            <p>Enrolled Courses</p>
            <small class="text-muted">
                <?php echo htmlspecialchars($performance['avg_course_attendance'] ?? 0); ?>% avg attendance
            </small>
        </div>

        <!-- Today's Sessions -->
        <div class="stat-card performance">
            <div class="icon">
                <i class="fas fa-calendar-day"></i>
            </div>
            <h3><?php echo count($today_sessions); ?></h3>
            <p>Today's Classes</p>
            <small class="text-muted">
                <?php echo count($today_sessions) > 0 ? 'Next: ' . date('H:i', strtotime($today_sessions[0]['start_time'] ?? '00:00')) : 'No classes today'; ?>
            </small>
        </div>
    </div>

    <!-- Notifications Section -->
    <?php if (count($notifications) > 0): ?>
    <div class="notifications-section">
        <h5 class="mb-3">
            <i class="fas fa-bell me-2 text-primary"></i>Notifications
        </h5>
        <?php foreach ($notifications as $notification): ?>
        <div class="notification-card <?php echo $notification['type']; ?>">
            <div class="icon">
                <i class="fas fa-<?php echo $notification['icon']; ?>"></i>
            </div>
            <div class="flex-grow-1">
                <h6><?php echo htmlspecialchars($notification['title']); ?></h6>
                <p><?php echo htmlspecialchars($notification['message']); ?></p>
            </div>
            <?php if (isset($notification['action'])): ?>
            <a href="<?php echo $notification['action']; ?>" class="btn btn-sm btn-outline-primary ms-3">
                <i class="fas fa-arrow-right me-1"></i>View
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="quick-actions-section">
        <h5 class="mb-3">
            <i class="fas fa-bolt me-2 text-primary"></i>Quick Actions
        </h5>
        <div class="quick-actions-grid">
            <a href="attendance-records.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h6>View Attendance</h6>
            </a>
            <a href="request-leave.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-file-signature"></i>
                </div>
                <h6>Request Leave</h6>
            </a>
            <a href="leave-status.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-envelope-open-text"></i>
                </div>
                <h6>Leave Status</h6>
            </a>
            <a href="my-courses.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-book"></i>
                </div>
                <h6>My Courses</h6>
            </a>
            <a href="#profile" class="action-card" onclick="showProfileModal()">
                <div class="icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <h6>Edit Profile</h6>
            </a>
            <a href="system-logs.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-history"></i>
                </div>
                <h6>Activity Log</h6>
            </a>
        </div>
    </div>

    <!-- Today's Schedule -->
    <?php if (count($today_sessions) > 0): ?>
    <div class="schedule-section">
        <h5 class="mb-3">
            <i class="fas fa-calendar-day me-2 text-primary"></i>Today's Schedule
        </h5>
        <div class="schedule-card">
            <div class="schedule-header">
                <h6><i class="fas fa-clock me-2"></i>Class Schedule - <?php echo date('l, F j'); ?></h6>
            </div>
            <div class="schedule-body">
                <?php foreach ($today_sessions as $session): ?>
                <div class="schedule-item">
                    <div class="schedule-time">
                        <?php echo date('H:i', strtotime($session['start_time'])); ?> -
                        <?php echo date('H:i', strtotime($session['end_time'])); ?>
                    </div>
                    <div class="schedule-content">
                        <h6><?php echo htmlspecialchars($session['course_name']); ?></h6>
                        <small class="text-muted">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($session['lecturer_fname'] . ' ' . $session['lecturer_lname']); ?>
                            <?php if (isset($session['room_name'])): ?>
                                | <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($session['room_name']); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Attendance -->
    <?php if (count($recent_records) > 0): ?>
    <div class="schedule-section">
        <h5 class="mb-3">
            <i class="fas fa-history me-2 text-primary"></i>Recent Attendance
        </h5>
        <div class="schedule-card">
            <div class="schedule-body">
                <?php foreach (array_slice($recent_records, 0, 5) as $record): ?>
                <div class="schedule-item">
                    <div class="schedule-time">
                        <?php echo date('M d', strtotime($record['recorded_at'])); ?>
                    </div>
                    <div class="schedule-content">
                        <h6><?php echo htmlspecialchars($record['course_name'] ?? 'Course'); ?></h6>
                        <small class="text-muted">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars(($record['lecturer_fname'] ?? '') . ' ' . ($record['lecturer_lname'] ?? '')); ?>
                            | <span class="badge bg-<?php echo $record['status'] === 'present' ? 'success' : 'danger'; ?>">
                                <i class="fas fa-<?php echo $record['status'] === 'present' ? 'check' : 'times'; ?> me-1"></i>
                                <?php echo ucfirst($record['status']); ?>
                            </span>
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Footer -->
<div class="footer">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>&copy; 2025 Rwanda Polytechnic | Student Management System</div>
        <div class="d-flex gap-3">
            <small><i class="fas fa-server me-1"></i>System Online</small>
            <small><i class="fas fa-clock me-1"></i>Last updated: <?php echo date('H:i:s'); ?></small>
        </div>
    </div>
</div>

<!-- Profile Modal (Placeholder for future implementation) -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Profile editing functionality will be available soon.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Global variables
let notificationsVisible = false;

// Initialize when document is ready
$(document).ready(function() {
    initializeDashboard();
    setupEventListeners();
    loadDashboardData();
});

// Initialize dashboard components
function initializeDashboard() {
    // Set current date/time
    updateDateTime();

    // Initialize animations
    initializeAnimations();

    // Check for low attendance warning
    checkAttendanceStatus();

    // Update time every minute
    setInterval(updateDateTime, 60000);
}

// Setup all event listeners
function setupEventListeners() {
    // Sidebar toggle for mobile
    $('#sidebarToggle').on('click', toggleSidebar);

    // Notification bell click
    $('.notification-bell').on('click', toggleNotifications);

    // Close notifications when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.notification-bell, .notifications-dropdown').length) {
            hideNotifications();
        }
    });

    // Keyboard shortcuts
    $(document).on('keydown', handleKeyboardShortcuts);

    // Handle window resize
    $(window).on('resize', handleWindowResize);
}

// Update date and time display
function updateDateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    $('.topbar .text-muted').last().html(`<i class="fas fa-clock me-1"></i>${timeString}`);
}

// Initialize animations
function initializeAnimations() {
    // Add entrance animations to cards
    $('.stat-card, .action-card, .notification-card, .schedule-card').each(function(index) {
        $(this).css('animation-delay', (index * 0.1) + 's');
    });
}

// Check attendance status and show warnings
function checkAttendanceStatus() {
    const attendanceRate = <?php echo json_encode($attendance['attendance_percentage'] ?? 0); ?>;

    if (attendanceRate < 75) {
        showAttendanceWarning(attendanceRate);
    }
}

// Show attendance warning
function showAttendanceWarning(rate) {
    const warningHtml = `
        <div class="alert alert-warning alert-dismissible fade show position-fixed"
             style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Attendance Alert!</strong> Your attendance rate is ${rate}%.
            Please improve to avoid academic penalties.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    $('body').append(warningHtml);

    // Auto-dismiss after 10 seconds
    setTimeout(() => {
        $('.alert-warning').fadeOut(function() {
            $(this).remove();
        });
    }, 10000);
}

// Toggle sidebar for mobile
function toggleSidebar() {
    $('#sidebar').toggleClass('show');
}

// Toggle notifications dropdown
function toggleNotifications() {
    if (notificationsVisible) {
        hideNotifications();
    } else {
        showNotifications();
    }
}

// Show notifications
function showNotifications() {
    // Create notifications dropdown if it doesn't exist
    if (!$('.notifications-dropdown').length) {
        const notificationsHtml = `
            <div class="notifications-dropdown position-absolute bg-white rounded shadow"
                 style="top: 50px; right: 0; width: 350px; max-height: 400px; overflow-y: auto; z-index: 1000;">
                <div class="p-3 border-bottom">
                    <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6>
                </div>
                <div class="notifications-list">
                    <?php if (count($notifications) > 0): ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item p-3 border-bottom hover-bg-light" style="cursor: pointer;"
                             onclick="handleNotificationClick('<?php echo $notification['action'] ?? '#'; ?>')">
                            <div class="d-flex">
                                <div class="notification-icon me-3">
                                    <div class="bg-<?php echo $notification['type'] === 'warning' ? 'warning' : ($notification['type'] === 'success' ? 'success' : 'info'); ?> bg-opacity-10 rounded-circle p-2">
                                        <i class="fas fa-<?php echo $notification['icon']; ?> text-<?php echo $notification['type'] === 'warning' ? 'warning' : ($notification['type'] === 'success' ? 'success' : 'info'); ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($notification['title']); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($notification['message']); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted">
                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                            <div>No new notifications</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        `;

        $('.notification-bell').append(notificationsHtml);
    }

    $('.notifications-dropdown').fadeIn(200);
    notificationsVisible = true;
}

// Hide notifications
function hideNotifications() {
    $('.notifications-dropdown').fadeOut(200);
    notificationsVisible = false;
}

// Handle notification click
function handleNotificationClick(action) {
    if (action && action !== '#') {
        window.location.href = action;
    }
    hideNotifications();
}

// Handle keyboard shortcuts
function handleKeyboardShortcuts(e) {
    // Alt + D to focus dashboard
    if (e.altKey && e.key === 'd') {
        e.preventDefault();
        $('html, body').animate({ scrollTop: 0 }, 500);
    }

    // Alt + A for attendance
    if (e.altKey && e.key === 'a') {
        e.preventDefault();
        window.location.href = 'attendance-records.php';
    }

    // Alt + L for leave
    if (e.altKey && e.key === 'l') {
        e.preventDefault();
        window.location.href = 'request-leave.php';
    }

    // Escape to close modals/dropdowns
    if (e.key === 'Escape') {
        hideNotifications();
        $('.modal').modal('hide');
    }
}

// Handle window resize
function handleWindowResize() {
    // Close sidebar on desktop
    if ($(window).width() >= 768) {
        $('#sidebar').removeClass('show');
    }
}

// Load dashboard data (for future AJAX updates)
function loadDashboardData() {
    // This could be used for real-time updates
    // For now, it's a placeholder for future enhancements
}

// Show profile modal
function showProfileModal() {
    $('#profileModal').modal('show');
}

// Animate value changes
function animateValue(element, newValue, duration = 1000) {
    const $element = $(element);
    const currentValue = parseInt($element.text()) || 0;

    $({ val: currentValue }).animate({ val: newValue }, {
        duration: duration,
        easing: 'swing',
        step: function(val) {
            $element.text(Math.floor(val));
        },
        complete: function() {
            $element.text(newValue);
        }
    });
}

// Performance monitoring
function monitorPerformance() {
    if ('performance' in window && 'getEntriesByType' in performance) {
        // Monitor page load performance
        window.addEventListener('load', function() {
            setTimeout(() => {
                const perfData = performance.getEntriesByType('navigation')[0];
                console.log('Page load time:', perfData.loadEventEnd - perfData.fetchStart + 'ms');
            }, 0);
        });
    }
}

// Initialize performance monitoring
monitorPerformance();

// Handle online/offline status
window.addEventListener('online', function() {
    showStatusMessage('Connection restored', 'success');
});

window.addEventListener('offline', function() {
    showStatusMessage('You are offline', 'warning');
});

// Show status messages
function showStatusMessage(message, type = 'info') {
    const alertId = 'status_alert_' + Date.now();
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show position-fixed"
             id="${alertId}" style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    $('body').append(alertHtml);

    setTimeout(() => {
        $(`#${alertId}`).fadeOut(function() {
            $(this).remove();
        });
    }, 5000);
}

// Add hover effects for better UX
$(document).on('mouseenter', '.action-card, .stat-card', function() {
    $(this).addClass('hover-lift');
});

$(document).on('mouseleave', '.action-card, .stat-card', function() {
    $(this).removeClass('hover-lift');
});

// Add CSS for hover effects
$('<style>')
    .text(`
        .hover-lift {
            transform: translateY(-5px) !important;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        .notifications-dropdown .notification-item:hover {
            background-color: rgba(0, 102, 204, 0.05);
        }
    `)
    .appendTo('head');
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
