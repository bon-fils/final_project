<?php
declare(strict_types=1);
session_start();

require_once "config.php";
require_once "session_check.php";
require_once "cache_utils.php";
require_role(['student']);

/**
 * StudentDashboard class handles loading and processing dashboard data for students
 */
class StudentDashboard {
    private PDO $pdo;
    private int $user_id;
    private array $student = [];
    private array $dashboard_data = [];

    /**
     * Constructor
     * @param PDO $pdo Database connection
     * @param int $user_id Student user ID
     */
    public function __construct(PDO $pdo, int $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
    }
    
    /**
     * Load all dashboard data for the student
     * Uses caching to improve performance
     * @return bool True if data loaded successfully, false otherwise
     */
    public function loadDashboardData(): bool {
        try {
            // Always load student data first
            if (!$this->loadStudentData()) {
                return false;
            }

            // Check cache for dashboard data to avoid unnecessary database queries
            $cache_key = "dashboard_data_user_{$this->user_id}";
            $cached_data = cache_get($cache_key);
            if ($cached_data !== null) {
                $this->dashboard_data = $cached_data;
                return true;
            }

            // Load fresh dashboard data from database
            $this->dashboard_data = [
                'attendance' => $this->getAttendanceStats(),
                'leave' => $this->getLeaveStats(),
                'recent_records' => $this->getRecentAttendance(),
                'today_sessions' => $this->getTodaySchedule(),
                'performance' => $this->getPerformanceStats(),
                'notifications' => $this->getNotifications()
            ];

            // Cache the dashboard data for 5 minutes to reduce database load
            cache_set($cache_key, $this->dashboard_data, 300);

            return true;

        } catch (PDOException $e) {
            error_log("Student dashboard error for user {$this->user_id}: " . $e->getMessage());
            $this->setFallbackData();
            return false;
        }
    }
    
    private function loadStudentData(): bool {
        $sql = "
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
            WHERE s.user_id = ? AND s.status = 'active'
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->user_id]);
        $this->student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        
        return !empty($this->student);
    }
    
    private function getAttendanceStats(): array {
        $sql = "
            SELECT
                COUNT(*) as total_sessions,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                COALESCE(ROUND(
                    (SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0)
                ), 1), 0) as attendance_percentage,
                MAX(recorded_at) as last_attendance
            FROM attendance_records
            WHERE student_id = ? AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: $this->getDefaultAttendanceStats();
    }
    
    private function getLeaveStats(): array {
        $sql = "
            SELECT
                COUNT(*) as total_requests,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                MAX(requested_at) as last_request
            FROM leave_requests
            WHERE student_id = ? AND requested_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: $this->getDefaultLeaveStats();
    }
    
    private function getRecentAttendance(): array {
        $sql = "
            SELECT
                ar.status, ar.recorded_at,
                sess.session_date, sess.start_time, sess.end_time,
                l.first_name as lecturer_fname, l.last_name as lecturer_lname,
                c.name as course_name, c.code as course_code
            FROM attendance_records ar
            INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
            LEFT JOIN lecturers l ON sess.lecturer_id = l.id
            LEFT JOIN courses c ON sess.course_id = c.id
            WHERE ar.student_id = ?
            ORDER BY ar.recorded_at DESC
            LIMIT 10
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    private function getTodaySchedule(): array {
        $sql = "
            SELECT
                sess.session_date, sess.start_time, sess.end_time,
                c.name as course_name, c.code as course_code,
                l.first_name as lecturer_fname, l.last_name as lecturer_lname,
                r.name as room_name, r.floor as room_floor
            FROM attendance_sessions sess
            INNER JOIN courses c ON sess.course_id = c.id
            LEFT JOIN lecturers l ON sess.lecturer_id = l.id
            LEFT JOIN rooms r ON sess.room_id = r.id
            WHERE sess.session_date = CURDATE()
            AND sess.option_id = ?
            AND sess.status = 'scheduled'
            ORDER BY sess.start_time ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['option_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    private function getPerformanceStats(): array {
        $sql = "
            SELECT
                (SELECT COUNT(DISTINCT course_id) 
                 FROM attendance_sessions 
                 WHERE option_id = ? 
                 AND session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                 AND status = 'completed') as enrolled_courses,
                
                COALESCE(ROUND(AVG(course_pct), 1), 0) as avg_course_attendance
            FROM (
                SELECT 
                    sess.course_id, 
                    ROUND(
                        (SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100.0 / 
                         NULLIF(COUNT(ar.id), 0)
                    ), 1) as course_pct
                FROM attendance_sessions sess
                LEFT JOIN attendance_records ar ON sess.id = ar.session_id AND ar.student_id = ?
                WHERE sess.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) 
                AND sess.option_id = ?
                AND sess.status = 'completed'
                GROUP BY sess.course_id
            ) as course_averages
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $this->student['option_id'], 
            $this->student['id'], 
            $this->student['option_id']
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: $this->getDefaultPerformanceStats();
    }
    
    private function getNotifications(): array {
        $notifications = [];
        $attendance = $this->dashboard_data['attendance'] ?? [];
        $leave = $this->dashboard_data['leave'] ?? [];
        $today_sessions = $this->dashboard_data['today_sessions'] ?? [];
        
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
        $accountAge = time() - strtotime($this->student['account_created']);
        if ($accountAge < 7 * 24 * 60 * 60) { // 7 days in seconds
            $notifications[] = [
                'type' => 'info',
                'icon' => 'user-plus',
                'title' => 'Welcome to RP Attendance System!',
                'message' => 'Complete your profile and familiarize yourself with the system.',
                'action' => '#profile'
            ];
        }
        
        return $notifications;
    }
    
    private function setFallbackData(): void {
        $this->student = [
            'first_name' => 'Student', 
            'last_name' => '', 
            'department_name' => 'Unknown',
            'id' => 0,
            'option_id' => 0
        ];
        
        $this->dashboard_data = [
            'attendance' => $this->getDefaultAttendanceStats(),
            'leave' => $this->getDefaultLeaveStats(),
            'recent_records' => [],
            'today_sessions' => [],
            'performance' => $this->getDefaultPerformanceStats(),
            'notifications' => []
        ];
    }
    
    private function getDefaultAttendanceStats(): array {
        return [
            'total_sessions' => 0,
            'present_count' => 0,
            'absent_count' => 0,
            'attendance_percentage' => 0,
            'last_attendance' => null
        ];
    }
    
    private function getDefaultLeaveStats(): array {
        return [
            'total_requests' => 0,
            'pending_count' => 0,
            'approved_count' => 0,
            'rejected_count' => 0,
            'last_request' => null
        ];
    }
    
    private function getDefaultPerformanceStats(): array {
        return [
            'enrolled_courses' => 0,
            'avg_course_attendance' => 0
        ];
    }
    
    public function getStudent(): array {
        return $this->student;
    }
    
    public function getDashboardData(): array {
        return $this->dashboard_data;
    }
    
    public function getStudentName(): string {
        return htmlspecialchars(
            trim($this->student['first_name'] . ' ' . $this->student['last_name']),
            ENT_QUOTES, 'UTF-8'
        );
    }
    
    public function getStudentId(): int {
        return (int)($this->student['id'] ?? 0);
    }
}

// Main execution - Initialize dashboard with error handling
try {
    // Validate user session
    $user_id = (int)($_SESSION['user_id'] ?? 0);

    if ($user_id <= 0) {
        throw new Exception('Invalid or missing user session ID');
    }

    // Initialize dashboard
    $dashboard = new StudentDashboard($pdo, $user_id);

    // Load dashboard data
    if (!$dashboard->loadDashboardData()) {
        // If student data not found, destroy session and redirect
        error_log("Failed to load dashboard data for user ID: {$user_id}");
        session_destroy();
        header("Location: index.php?error=student_not_found");
        exit();
    }

    // Extract data for template rendering
    $student = $dashboard->getStudent();
    $dashboard_data = $dashboard->getDashboardData();
    $student_name = $dashboard->getStudentName();
    $student_id = $dashboard->getStudentId();

    // Extract individual data arrays for easier template access
    $attendance = $dashboard_data['attendance'];
    $leave = $dashboard_data['leave'];
    $recent_records = $dashboard_data['recent_records'];
    $today_sessions = $dashboard_data['today_sessions'];
    $performance = $dashboard_data['performance'];
    $notifications = $dashboard_data['notifications'];

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Dashboard initialization error: " . $e->getMessage());

    // Provide fallback data to prevent page crash
    $student = ['first_name' => 'Student', 'last_name' => '', 'department_name' => 'Unknown'];
    $student_name = 'Student';
    $student_id = 0;
    $attendance = ['total_sessions' => 0, 'present_count' => 0, 'attendance_percentage' => 0];
    $leave = ['pending_count' => 0, 'total_requests' => 0];
    $recent_records = [];
    $today_sessions = [];
    $performance = ['enrolled_courses' => 0, 'avg_course_attendance' => 0];
    $notifications = [];

    // Only destroy session for authentication-related errors
    if (strpos(strtolower($e->getMessage()), 'session') !== false ||
        strpos(strtolower($e->getMessage()), 'user') !== false ||
        strpos(strtolower($e->getMessage()), 'auth') !== false) {
        session_destroy();
        header("Location: index.php?error=session_expired");
        exit();
    }
}

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
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
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<link href="students-dashboard.css" rel="stylesheet" />
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="student-avatar">
            <i class="fas fa-user-graduate"></i>
        </div>
        <h5><?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?></h5>
        <div class="department-badge">
            <?php echo htmlspecialchars($student['department_name'] ?? 'Department', ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>
    <a href="students-dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
    <a href="attendance-records.php"><i class="fas fa-calendar-check"></i> Attendance Records</a>
    <a href="request-leave.php"><i class="fas fa-file-signature"></i> Request Leave</a>
    <a href="leave-status.php"><i class="fas fa-envelope-open-text"></i> Leave Status</a>
    <a href="my-courses.php"><i class="fas fa-book"></i> My Courses</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
            <span class="fw-semibold"><?php echo htmlspecialchars($student['first_name'], ENT_QUOTES, 'UTF-8'); ?></span>
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
                <h2>Welcome back, <?php echo htmlspecialchars($student['first_name'], ENT_QUOTES, 'UTF-8'); ?>! 👋</h2>
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
                <div class="progress-bar bg-success" style="width: <?php echo min(100, htmlspecialchars($attendance['attendance_percentage'] ?? 0)); ?>%"></div>
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
        <div class="notification-card <?php echo htmlspecialchars($notification['type']); ?>">
            <div class="icon">
                <i class="fas fa-<?php echo htmlspecialchars($notification['icon']); ?>"></i>
            </div>
            <div class="flex-grow-1">
                <h6><?php echo htmlspecialchars($notification['title']); ?></h6>
                <p><?php echo htmlspecialchars($notification['message']); ?></p>
            </div>
            <?php if (isset($notification['action'])): ?>
            <a href="<?php echo htmlspecialchars($notification['action']); ?>" class="btn btn-sm btn-outline-primary ms-3">
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

<!-- Profile Modal -->
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
window.attendanceRate = <?php echo json_encode($attendance['attendance_percentage'] ?? 0); ?>;
window.notifications = <?php echo json_encode($notifications); ?>;
</script>
<script src="students-dashboard.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>