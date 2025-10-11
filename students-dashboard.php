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

            // Load fresh dashboard data from database with error resilience
            $this->dashboard_data = [
                'attendance' => $this->safeExecute('getAttendanceStats'),
                'leave' => $this->safeExecute('getLeaveStats'),
                'recent_records' => $this->safeExecute('getRecentAttendance'),
                'today_sessions' => $this->safeExecute('getTodaySchedule'),
                'performance' => $this->safeExecute('getPerformanceStats'),
                'course_performance' => $this->safeExecute('getCoursePerformance'),
                'study_tips' => $this->safeExecute('getStudyTips'),
                'campus_highlights' => $this->safeExecute('getCampusHighlights'),
                'upcoming_assignments' => $this->safeExecute('getUpcomingAssignments'),
                'attendance_insights' => $this->safeExecute('getAttendanceInsights'),
                'quick_stats' => $this->safeExecute('getQuickStats'),
                'motivational_quote' => $this->safeExecute('getMotivationalQuote'),
                'recent_activities' => $this->safeExecute('getRecentActivities'),
                'attendance_trends' => $this->safeExecute('getAttendanceTrends'),
                'academic_calendar' => $this->safeExecute('getAcademicCalendar'),
                'library_stats' => $this->safeExecute('getLibraryStats'),
                'fee_status' => $this->safeExecute('getFeeStatus'),
                'campus_resources' => $this->safeExecute('getCampusResources'),
                'peer_comparison' => $this->safeExecute('getPeerComparison'),
                'learning_resources' => $this->safeExecute('getLearningResources'),
                'career_opportunities' => $this->safeExecute('getCareerOpportunities'),
                'health_wellness' => $this->safeExecute('getHealthWellnessTips'),
                'notifications' => $this->safeExecute('getNotifications')
            ];

            // Cache the dashboard data for 5 minutes to reduce database load
            cache_set($cache_key, $this->dashboard_data, 300);

            return true;

        } catch (Throwable $e) {
            error_log("Student dashboard error for user {$this->user_id}: " . $e->getMessage());
            error_log("Exception type: " . get_class($e));
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->setFallbackData();
            return false;
        }
    }

    /**
     * Safely execute a method and return fallback data on failure
     * @param string $methodName The method name to execute
     * @return mixed The method result or fallback data
     */
    private function safeExecute(string $methodName) {
        try {
            return $this->$methodName();
        } catch (Throwable $e) {
            error_log("Error in {$methodName} for user {$this->user_id}: " . $e->getMessage());
            // Return appropriate fallback based on method
            switch ($methodName) {
                case 'getAttendanceStats':
                case 'getLeaveStats':
                case 'getPerformanceStats':
                    return $this->{'getDefault' . ucfirst(str_replace('get', '', $methodName))}();
                case 'getRecentAttendance':
                case 'getTodaySchedule':
                case 'getCoursePerformance':
                case 'getRecentActivities':
                case 'getAttendanceTrends':
                case 'getAcademicCalendar':
                case 'getCampusResources':
                case 'getLearningResources':
                case 'getCareerOpportunities':
                case 'getHealthWellnessTips':
                    return [];
                case 'getStudyTips':
                case 'getCampusHighlights':
                    return [];
                case 'getLibraryStats':
                case 'getFeeStatus':
                case 'getPeerComparison':
                    return $this->{'getDefault' . ucfirst(str_replace('get', '', $methodName))}();
                case 'getMotivationalQuote':
                    return ['quote' => 'Education is the key to success.', 'author' => 'Unknown'];
                case 'getNotifications':
                    return [];
                default:
                    return null;
            }
        }
    }
    
    private function loadStudentData(): bool {
        // First check if user exists and is active
        $userCheckSql = "SELECT id, first_name, last_name, email, role, status FROM users WHERE id = ? AND status = 'active'";
        $userStmt = $this->pdo->prepare($userCheckSql);
        $userStmt->execute([$this->user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            error_log("StudentDashboard: User not found or inactive");
            return false;
        }

        if ($user['role'] !== 'student') {
            error_log("StudentDashboard: User is not a student, role: {$user['role']}");
            return false;
        }

        // Now check for student record
        $sql = "
            SELECT
                s.id, u.first_name, u.last_name, u.email, s.reg_no, s.year_level, u.sex,
                u.photo, u.phone as telephone, s.option_id,
                d.name as department_name,
                o.name as option_name,
                u.username, u.created_at as account_created
            FROM students s
            LEFT JOIN options o ON s.option_id = o.id
            LEFT JOIN departments d ON o.department_id = d.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.user_id = ? AND u.status = 'active'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->user_id]);
        $this->student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if (empty($this->student)) {
            error_log("StudentDashboard: No student record found for user_id: {$this->user_id}. User needs to be registered as a student.");
            // Don't return false here, instead set fallback data
            $this->student = [
                'id' => 0,
                'first_name' => $user['first_name'] ?? 'Student',
                'last_name' => $user['last_name'] ?? '',
                'email' => $user['email'] ?? '',
                'reg_no' => 'Not Registered',
                'year_level' => 'N/A',
                'sex' => 'N/A',
                'photo' => null,
                'telephone' => 'N/A',
                'option_id' => 0,
                'department_name' => 'Not Assigned',
                'option_name' => 'Not Assigned',
                'username' => $user['username'] ?? 'unknown',
                'account_created' => $user['created_at'] ?? date('Y-m-d H:i:s')
            ];
            return true; // Allow dashboard to load with limited data
        }

        return true;
    }

    // ... (previous methods: getAttendanceStats, getLeaveStats, getRecentAttendance, getTodaySchedule, getPerformanceStats, getNotifications, getCoursePerformance, getStudyTips, getCampusHighlights, getUpcomingAssignments, getAttendanceInsights, getQuickStats, getMotivationalQuote, getRecentActivities, getAttendanceTrends)

    // NEW METHODS FOR ENHANCED DASHBOARD

    private function getAcademicCalendar(): array {
        $sql = "
            SELECT 
                event_name, event_date, event_type, description, location
            FROM academic_calendar 
            WHERE event_date >= CURDATE() 
            AND event_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY event_date ASC
            LIMIT 6
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [
            [
                'event_name' => 'Mid-Term Examinations',
                'event_date' => date('Y-m-d', strtotime('+2 weeks')),
                'event_type' => 'academic',
                'description' => 'Mid-term examinations for all courses',
                'location' => 'Various Classrooms'
            ],
            [
                'event_name' => 'Career Fair',
                'event_date' => date('Y-m-d', strtotime('+3 weeks')),
                'event_type' => 'career',
                'description' => 'Annual career fair with industry partners',
                'location' => 'Main Campus Hall'
            ]
        ];
    }

    private function getLibraryStats(): array {
        $sql = "
            SELECT 
                COUNT(*) as borrowed_books,
                (SELECT COUNT(*) FROM books WHERE available_copies > 0) as available_books,
                (SELECT COUNT(*) FROM books) as total_books,
                (SELECT COUNT(*) FROM library_reservations WHERE student_id = ? AND status = 'active') as active_reservations
            FROM library_loans 
            WHERE student_id = ? AND return_date IS NULL
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['id'], $this->student['id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: $this->getDefaultLibraryStats();
        } catch (Exception $e) {
            return $this->getDefaultLibraryStats();
        }
    }

    private function getFeeStatus(): array {
        $sql = "
            SELECT 
                fee_type, amount, due_date, status, paid_amount,
                DATEDIFF(due_date, CURDATE()) as days_remaining
            FROM fee_payments 
            WHERE student_id = ? 
            AND academic_year = YEAR(CURDATE())
            ORDER BY due_date ASC
            LIMIT 5
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['id']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: $this->getDefaultFeeStatus();
        } catch (Exception $e) {
            return $this->getDefaultFeeStatus();
        }
    }

    private function getCampusResources(): array {
        return [
            [
                'name' => 'Writing Center',
                'description' => 'Get help with academic writing and research papers',
                'location' => 'Library Building, Room 201',
                'hours' => 'Mon-Fri: 9AM-5PM',
                'contact' => 'writing@rpolytechnic.rw'
            ],
            [
                'name' => 'Career Services',
                'description' => 'Career counseling, resume reviews, and job placement',
                'location' => 'Student Services Building',
                'hours' => 'Mon-Fri: 8AM-4PM',
                'contact' => 'career@rpolytechnic.rw'
            ],
            [
                'name' => 'IT Help Desk',
                'description' => 'Technical support for campus systems and WiFi',
                'location' => 'ICT Building, Ground Floor',
                'hours' => '24/7',
                'contact' => 'helpdesk@rpolytechnic.rw'
            ],
            [
                'name' => 'Health Center',
                'description' => 'Medical services and wellness counseling',
                'location' => 'Health Services Building',
                'hours' => 'Mon-Sat: 8AM-6PM',
                'contact' => 'health@rpolytechnic.rw'
            ]
        ];
    }

    private function getPeerComparison(): array {
        $sql = "
            SELECT 
                ROUND(AVG(
                    CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END
                ), 1) as department_avg,
                (SELECT ROUND(AVG(
                    CASE WHEN ar2.status = 'present' THEN 100 ELSE 0 END
                ), 1) 
                 FROM attendance_records ar2 
                 INNER JOIN students s2 ON ar2.student_id = s2.id 
                 WHERE s2.option_id = ?) as program_avg,
                (SELECT COUNT(DISTINCT student_id) 
                 FROM attendance_records ar3 
                 INNER JOIN students s3 ON ar3.student_id = s3.id 
                 WHERE s3.option_id = ? 
                 AND (SELECT ROUND(AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END), 1) 
                      FROM attendance_records WHERE student_id = ar3.student_id) > ?) as students_better_than_you
            FROM attendance_records ar
            INNER JOIN students s ON ar.student_id = s.id
            WHERE s.department_id = (SELECT department_id FROM options WHERE id = ?)
        ";

        try {
            $attendance_rate = $this->dashboard_data['attendance']['attendance_percentage'] ?? 0;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->student['option_id'],
                $this->student['option_id'],
                $attendance_rate,
                $this->student['option_id']
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: $this->getDefaultPeerComparison();
        } catch (Exception $e) {
            return $this->getDefaultPeerComparison();
        }
    }

    private function getLearningResources(): array {
        return [
            [
                'title' => 'Online Library Portal',
                'description' => 'Access to digital books, journals, and research papers',
                'link' => 'https://library.rpolytechnic.rw',
                'type' => 'digital',
                'icon' => 'book'
            ],
            [
                'title' => 'Video Tutorials',
                'description' => 'Recorded lectures and tutorial videos for all courses',
                'link' => 'https://learn.rpolytechnic.rw',
                'type' => 'video',
                'icon' => 'play-circle'
            ],
            [
                'title' => 'Practice Exercises',
                'description' => 'Interactive exercises and quizzes for self-assessment',
                'link' => 'https://practice.rpolytechnic.rw',
                'type' => 'interactive',
                'icon' => 'puzzle-piece'
            ],
            [
                'title' => 'Study Groups',
                'description' => 'Join virtual study groups with your classmates',
                'link' => 'https://groups.rpolytechnic.rw',
                'type' => 'collaborative',
                'icon' => 'users'
            ]
        ];
    }

    private function getCareerOpportunities(): array {
        $sql = "
            SELECT 
                job_title, company_name, application_deadline, job_type,
                DATEDIFF(application_deadline, CURDATE()) as days_remaining
            FROM career_opportunities 
            WHERE (target_department_id = ? OR target_department_id IS NULL)
            AND application_deadline >= CURDATE()
            AND status = 'active'
            ORDER BY application_deadline ASC
            LIMIT 4
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['department_id'] ?? 0]);
            $opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($opportunities)) {
                return [
                    [
                        'job_title' => 'Software Developer Intern',
                        'company_name' => 'Tech Solutions Rwanda',
                        'application_deadline' => date('Y-m-d', strtotime('+30 days')),
                        'job_type' => 'Internship',
                        'days_remaining' => 30
                    ],
                    [
                        'job_title' => 'Data Analyst',
                        'company_name' => 'Kigali Analytics',
                        'application_deadline' => date('Y-m-d', strtotime('+45 days')),
                        'job_type' => 'Full-time',
                        'days_remaining' => 45
                    ]
                ];
            }
            
            return $opportunities;
        } catch (Exception $e) {
            return [];
        }
    }

    private function getHealthWellnessTips(): array {
        return [
            [
                'title' => 'Study-Life Balance',
                'tip' => 'Take regular breaks during study sessions - 5 minutes every 25 minutes improves focus',
                'category' => 'mental_health',
                'icon' => 'brain'
            ],
            [
                'title' => 'Physical Activity',
                'tip' => '30 minutes of daily exercise can improve memory and concentration',
                'category' => 'physical_health',
                'icon' => 'running'
            ],
            [
                'title' => 'Sleep Quality',
                'tip' => 'Aim for 7-9 hours of sleep for optimal cognitive performance',
                'category' => 'sleep',
                'icon' => 'moon'
            ],
            [
                'title' => 'Nutrition',
                'tip' => 'Stay hydrated and include brain foods like nuts and fruits in your diet',
                'category' => 'nutrition',
                'icon' => 'apple-alt'
            ]
        ];
    }

    // Default data methods
    private function getDefaultLibraryStats(): array {
        return [
            'borrowed_books' => 0,
            'available_books' => 12500,
            'total_books' => 15000,
            'active_reservations' => 0
        ];
    }

    private function getDefaultFeeStatus(): array {
        return [
            [
                'fee_type' => 'Tuition Fee',
                'amount' => 250000,
                'due_date' => date('Y-m-d', strtotime('+30 days')),
                'status' => 'pending',
                'paid_amount' => 0,
                'days_remaining' => 30
            ]
        ];
    }

    private function getDefaultPeerComparison(): array {
        return [
            'department_avg' => 85.5,
            'program_avg' => 82.3,
            'students_better_than_you' => 45
        ];
    }

    // ... (rest of the existing methods: setFallbackData, getDefaultAttendanceStats, getDefaultLeaveStats, getDefaultPerformanceStats, getStudent, getDashboardData, getStudentName, getStudentId)
    
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
            'course_performance' => [],
            'study_tips' => $this->getStudyTips(),
            'campus_highlights' => $this->getCampusHighlights(),
            'upcoming_assignments' => [],
            'attendance_insights' => [],
            'quick_stats' => $this->getQuickStats(),
            'motivational_quote' => $this->getMotivationalQuote(),
            'recent_activities' => [],
            'attendance_trends' => [],
            'academic_calendar' => $this->getAcademicCalendar(),
            'library_stats' => $this->getDefaultLibraryStats(),
            'fee_status' => $this->getDefaultFeeStatus(),
            'campus_resources' => $this->getCampusResources(),
            'peer_comparison' => $this->getDefaultPeerComparison(),
            'learning_resources' => $this->getLearningResources(),
            'career_opportunities' => $this->getCareerOpportunities(),
            'health_wellness' => $this->getHealthWellnessTips(),
            'notifications' => $this->getNotifications()
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

    // Load dashboard data - continue even if loading fails to provide fallback experience
    if (!$dashboard->loadDashboardData()) {
        error_log("Failed to load dashboard data for user ID: {$user_id}. Using fallback data.");
    }

    // Always extract data for template rendering (dashboard provides fallback data)
    $student = $dashboard->getStudent();
    $dashboard_data = $dashboard->getDashboardData();
    $student_name = $dashboard->getStudentName();
    $student_id = $dashboard->getStudentId();

    // Extract all data arrays for template access
    $attendance = $dashboard_data['attendance'];
    $leave = $dashboard_data['leave'];
    $recent_records = $dashboard_data['recent_records'];
    $today_sessions = $dashboard_data['today_sessions'];
    $performance = $dashboard_data['performance'];
    $course_performance = $dashboard_data['course_performance'];
    $study_tips = $dashboard_data['study_tips'];
    $campus_highlights = $dashboard_data['campus_highlights'];
    $upcoming_assignments = $dashboard_data['upcoming_assignments'];
    $attendance_insights = $dashboard_data['attendance_insights'];
    $quick_stats = $dashboard_data['quick_stats'];
    $motivational_quote = $dashboard_data['motivational_quote'];
    $recent_activities = $dashboard_data['recent_activities'];
    $academic_calendar = $dashboard_data['academic_calendar'];
    $library_stats = $dashboard_data['library_stats'];
    $fee_status = $dashboard_data['fee_status'];
    $campus_resources = $dashboard_data['campus_resources'];
    $peer_comparison = $dashboard_data['peer_comparison'];
    $learning_resources = $dashboard_data['learning_resources'];
    $career_opportunities = $dashboard_data['career_opportunities'];
    $health_wellness = $dashboard_data['health_wellness'];
    $notifications = $dashboard_data['notifications'];

} catch (Exception $e) {
    // Log the error and provide fallback data
    error_log("Dashboard initialization error: " . $e->getMessage());
    
    // Fallback data setup
    $student = ['first_name' => 'Student', 'last_name' => '', 'department_name' => 'Unknown'];
    $student_name = 'Student';
    $student_id = 0;
    $attendance = ['total_sessions' => 0, 'present_count' => 0, 'attendance_percentage' => 0];
    $leave = ['pending_count' => 0, 'total_requests' => 0];
    $recent_records = [];
    $today_sessions = [];
    $performance = ['enrolled_courses' => 0, 'avg_course_attendance' => 0];
    $course_performance = [];
    $study_tips = [];
    $campus_highlights = [];
    $upcoming_assignments = [];
    $attendance_insights = [];
    $quick_stats = [];
    $motivational_quote = ['quote' => 'Education is the key to success.', 'author' => 'Unknown'];
    $recent_activities = [];
    $academic_calendar = [];
    $library_stats = ['borrowed_books' => 0, 'available_books' => 0, 'total_books' => 0, 'active_reservations' => 0];
    $fee_status = [];
    $campus_resources = [];
    $peer_comparison = [];
    $learning_resources = [];
    $career_opportunities = [];
    $health_wellness = [];
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
    <a href="academic-calendar.php"><i class="fas fa-calendar-alt"></i> Academic Calendar</a>
    <a href="library-portal.php"><i class="fas fa-book-open"></i> Library</a>
    <a href="fee-payments.php"><i class="fas fa-credit-card"></i> Fee Payments</a>
    <a href="career-portal.php"><i class="fas fa-briefcase"></i> Career Portal</a>
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
                <p>Here's your complete academic overview. Stay focused and keep up the great work!</p>
                <blockquote class="motivational-quote mt-3">
                    <p class="mb-1">"<?php echo htmlspecialchars($motivational_quote['quote']); ?>"</p>
                    <footer class="blockquote-footer mb-0"><?php echo htmlspecialchars($motivational_quote['author']); ?></footer>
                </blockquote>
                <div class="d-flex gap-3 mt-3">
                    <div class="text-muted small">
                        <i class="fas fa-calendar me-1"></i><?php echo date('l, F j, Y'); ?>
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-clock me-1"></i><?php echo date('H:i'); ?>
                    </div>
                    <div class="text-muted small">
                        <i class="fas fa-graduation-cap me-1"></i><?php echo htmlspecialchars($student['year_level'] ?? 'Year 1'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Statistics Cards -->
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

        <!-- Library Usage -->
        <div class="stat-card library">
            <div class="icon">
                <i class="fas fa-book-open"></i>
            </div>
            <h3><?php echo htmlspecialchars($library_stats['borrowed_books'] ?? 0); ?></h3>
            <p>Borrowed Books</p>
            <small class="text-muted">
                <?php echo htmlspecialchars($library_stats['available_books'] ?? 0); ?> available
            </small>
        </div>

        <!-- Fee Status -->
        <div class="stat-card fees">
            <div class="icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <h3>
                <?php 
                $pending_fees = array_filter($fee_status, fn($fee) => $fee['status'] === 'pending');
                echo count($pending_fees); 
                ?>
            </h3>
            <p>Pending Fees</p>
            <small class="text-muted">
                <?php 
                $total_pending = array_sum(array_column($pending_fees, 'amount'));
                echo number_format($total_pending); ?> RWF
            </small>
        </div>

        <!-- Career Opportunities -->
        <div class="stat-card career">
            <div class="icon">
                <i class="fas fa-briefcase"></i>
            </div>
            <h3><?php echo count($career_opportunities); ?></h3>
            <p>Career Opportunities</p>
            <small class="text-muted">
                <?php 
                $urgent_opportunities = array_filter($career_opportunities, fn($opp) => $opp['days_remaining'] <= 7);
                echo count($urgent_opportunities); ?> urgent
            </small>
        </div>
    </div>

    <!-- Quick Stats Overview -->
    <?php if (count($quick_stats) > 0): ?>
    <div class="quick-stats-section">
        <h5 class="mb-3">
            <i class="fas fa-chart-line me-2 text-primary"></i>Quick Stats
        </h5>
        <div class="quick-stats-grid">
            <?php foreach ($quick_stats as $stat): ?>
            <div class="quick-stat-card">
                <div class="stat-icon">
                    <i class="fas fa-<?php echo htmlspecialchars($stat['icon']); ?> text-<?php echo htmlspecialchars($stat['color']); ?>"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo htmlspecialchars($stat['value']); ?></div>
                    <div class="stat-label"><?php echo htmlspecialchars($stat['label']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Three Column Layout for Comprehensive Overview -->
    <div class="row mt-4">
        <!-- Left Column - Academic -->
        <div class="col-lg-4">
            <!-- Today's Schedule -->
            <?php if (count($today_sessions) > 0): ?>
            <div class="schedule-section">
                <h5 class="mb-3">
                    <i class="fas fa-calendar-day me-2 text-primary"></i>Today's Schedule
                </h5>
                <div class="schedule-card">
                    <div class="schedule-body">
                        <?php foreach ($today_sessions as $session): ?>
                        <div class="schedule-item">
                            <div class="schedule-time">
                                <?php echo date('H:i', strtotime($session['start_time'])); ?>
                            </div>
                            <div class="schedule-content">
                                <h6><?php echo htmlspecialchars($session['course_name']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($session['lecturer_fname'] . ' ' . $session['lecturer_lname']); ?>
                                </small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Upcoming Assignments -->
            <?php if (count($upcoming_assignments) > 0): ?>
            <div class="upcoming-assignments-section">
                <h5 class="mb-3">
                    <i class="fas fa-tasks me-2 text-primary"></i>Upcoming Assignments
                </h5>
                <div class="assignments-list">
                    <?php foreach ($upcoming_assignments as $assignment):
                        $urgency_class = $assignment['days_remaining'] <= 1 ? 'urgent' : ($assignment['days_remaining'] <= 3 ? 'warning' : 'normal');
                    ?>
                    <div class="assignment-item <?php echo $urgency_class; ?>">
                        <div class="assignment-header">
                            <h6><?php echo htmlspecialchars($assignment['title']); ?></h6>
                            <span class="badge bg-<?php echo $assignment['days_remaining'] <= 1 ? 'danger' : ($assignment['days_remaining'] <= 3 ? 'warning' : 'secondary'); ?>">
                                <?php echo $assignment['days_remaining'] == 0 ? 'Due Today' : ($assignment['days_remaining'] == 1 ? 'Tomorrow' : $assignment['days_remaining'] . ' days'); ?>
                            </span>
                        </div>
                        <div class="assignment-details">
                            <small class="text-muted">
                                <i class="fas fa-book me-1"></i><?php echo htmlspecialchars($assignment['course_name']); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Academic Calendar -->
            <?php if (count($academic_calendar) > 0): ?>
            <div class="academic-calendar-section">
                <h5 class="mb-3">
                    <i class="fas fa-calendar-alt me-2 text-primary"></i>Academic Calendar
                </h5>
                <div class="calendar-events">
                    <?php foreach ($academic_calendar as $event): ?>
                    <div class="calendar-event">
                        <div class="event-date">
                            <div class="event-day"><?php echo date('d', strtotime($event['event_date'])); ?></div>
                            <div class="event-month"><?php echo date('M', strtotime($event['event_date'])); ?></div>
                        </div>
                        <div class="event-details">
                            <h6><?php echo htmlspecialchars($event['event_name']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($event['description']); ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Middle Column - Performance -->
        <div class="col-lg-4">
            <!-- Course Performance Overview -->
            <?php if (count($course_performance) > 0): ?>
            <div class="performance-overview-section">
                <h5 class="mb-3">
                    <i class="fas fa-chart-bar me-2 text-primary"></i>Course Performance
                </h5>
                <div class="performance-cards">
                    <?php foreach ($course_performance as $course): ?>
                    <div class="performance-card">
                        <div class="course-info">
                            <h6><?php echo htmlspecialchars($course['course_code']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($course['course_name']); ?></small>
                        </div>
                        <div class="attendance-info">
                            <div class="progress-circle" data-percentage="<?php echo htmlspecialchars($course['attendance_percentage']); ?>">
                                <span class="percentage"><?php echo htmlspecialchars($course['attendance_percentage']); ?>%</span>
                            </div>
                            <small class="text-muted">
                                <?php echo htmlspecialchars($course['present_count']); ?>/<?php echo htmlspecialchars($course['total_sessions']); ?> sessions
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Attendance Insights -->
            <?php if (count($attendance_insights) > 0): ?>
            <div class="attendance-insights-section">
                <h5 class="mb-3">
                    <i class="fas fa-lightbulb me-2 text-primary"></i>Attendance Insights
                </h5>
                <div class="insights-grid">
                    <?php foreach ($attendance_insights as $insight): ?>
                    <div class="insight-card <?php echo htmlspecialchars($insight['type']); ?>">
                        <div class="insight-icon">
                            <i class="fas fa-<?php echo htmlspecialchars($insight['icon']); ?>"></i>
                        </div>
                        <div class="insight-content">
                            <h6><?php echo htmlspecialchars($insight['title']); ?></h6>
                            <p><?php echo htmlspecialchars($insight['message']); ?></p>
                            <?php if (isset($insight['metric'])): ?>
                            <div class="insight-metric"><?php echo htmlspecialchars($insight['metric']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Peer Comparison -->
            <div class="peer-comparison-section">
                <h5 class="mb-3">
                    <i class="fas fa-users me-2 text-primary"></i>Peer Comparison
                </h5>
                <div class="comparison-card">
                    <div class="comparison-item">
                        <div class="comparison-label">Your Attendance</div>
                        <div class="comparison-value"><?php echo htmlspecialchars($attendance['attendance_percentage'] ?? 0); ?>%</div>
                    </div>
                    <div class="comparison-item">
                        <div class="comparison-label">Department Average</div>
                        <div class="comparison-value"><?php echo htmlspecialchars($peer_comparison['department_avg'] ?? 0); ?>%</div>
                    </div>
                    <div class="comparison-item">
                        <div class="comparison-label">Program Average</div>
                        <div class="comparison-value"><?php echo htmlspecialchars($peer_comparison['program_avg'] ?? 0); ?>%</div>
                    </div>
                    <div class="comparison-stats">
                        <small class="text-muted">
                            <i class="fas fa-user-graduate me-1"></i>
                            <?php echo htmlspecialchars($peer_comparison['students_better_than_you'] ?? 0); ?> students have better attendance
                        </small>
                    </div>
                </div>
            </div>

            <!-- Study Tips -->
            <?php if (count($study_tips) > 0): ?>
            <div class="study-tips-section">
                <h5 class="mb-3">
                    <i class="fas fa-lightbulb me-2 text-primary"></i>Study Tips
                </h5>
                <div class="study-tips-card">
                    <?php foreach ($study_tips as $tip): ?>
                    <div class="study-tip-item <?php echo htmlspecialchars($tip['type']); ?>">
                        <div class="tip-icon">
                            <i class="fas fa-<?php echo htmlspecialchars($tip['icon']); ?>"></i>
                        </div>
                        <div class="tip-content">
                            <h6><?php echo htmlspecialchars($tip['title']); ?></h6>
                            <p><?php echo htmlspecialchars($tip['message']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column - Resources & Opportunities -->
        <div class="col-lg-4">
            <!-- Career Opportunities -->
            <?php if (count($career_opportunities) > 0): ?>
            <div class="career-opportunities-section">
                <h5 class="mb-3">
                    <i class="fas fa-briefcase me-2 text-primary"></i>Career Opportunities
                </h5>
                <div class="opportunities-list">
                    <?php foreach ($career_opportunities as $opportunity): ?>
                    <div class="opportunity-item">
                        <div class="opportunity-header">
                            <h6><?php echo htmlspecialchars($opportunity['job_title']); ?></h6>
                            <span class="badge bg-info"><?php echo htmlspecialchars($opportunity['job_type']); ?></span>
                        </div>
                        <div class="opportunity-details">
                            <small class="text-muted">
                                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($opportunity['company_name']); ?>
                            </small>
                            <div class="opportunity-deadline">
                                <i class="fas fa-clock me-1"></i>
                                Apply by <?php echo date('M d', strtotime($opportunity['application_deadline'])); ?>
                                (<?php echo $opportunity['days_remaining']; ?> days left)
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Learning Resources -->
            <?php if (count($learning_resources) > 0): ?>
            <div class="learning-resources-section">
                <h5 class="mb-3">
                    <i class="fas fa-book-open me-2 text-primary"></i>Learning Resources
                </h5>
                <div class="resources-grid">
                    <?php foreach ($learning_resources as $resource): ?>
                    <a href="<?php echo htmlspecialchars($resource['link']); ?>" class="resource-card" target="_blank">
                        <div class="resource-icon">
                            <i class="fas fa-<?php echo htmlspecialchars($resource['icon']); ?>"></i>
                        </div>
                        <div class="resource-content">
                            <h6><?php echo htmlspecialchars($resource['title']); ?></h6>
                            <p><?php echo htmlspecialchars($resource['description']); ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Campus Resources -->
            <?php if (count($campus_resources) > 0): ?>
            <div class="campus-resources-section">
                <h5 class="mb-3">
                    <i class="fas fa-university me-2 text-primary"></i>Campus Resources
                </h5>
                <div class="resources-list">
                    <?php foreach ($campus_resources as $resource): ?>
                    <div class="campus-resource">
                        <div class="resource-header">
                            <h6><?php echo htmlspecialchars($resource['name']); ?></h6>
                            <i class="fas fa-map-marker-alt text-muted"></i>
                        </div>
                        <p class="resource-description"><?php echo htmlspecialchars($resource['description']); ?></p>
                        <div class="resource-details">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($resource['hours']); ?>
                            </small>
                            <small class="text-muted">
                                <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($resource['contact']); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Health & Wellness -->
            <?php if (count($health_wellness) > 0): ?>
            <div class="health-wellness-section">
                <h5 class="mb-3">
                    <i class="fas fa-heart me-2 text-primary"></i>Health & Wellness
                </h5>
                <div class="wellness-tips">
                    <?php foreach ($health_wellness as $tip): ?>
                    <div class="wellness-tip">
                        <div class="tip-icon">
                            <i class="fas fa-<?php echo htmlspecialchars($tip['icon']); ?>"></i>
                        </div>
                        <div class="tip-content">
                            <h6><?php echo htmlspecialchars($tip['title']); ?></h6>
                            <p><?php echo htmlspecialchars($tip['tip']); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notifications Section -->
    <?php if (count($notifications) > 0): ?>
    <div class="notifications-section mt-4">
        <h5 class="mb-3">
            <i class="fas fa-bell me-2 text-primary"></i>Important Notifications
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
    <div class="quick-actions-section mt-4">
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
            <a href="library-portal.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h6>Library Portal</h6>
            </a>
            <a href="fee-payments.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h6>Fee Payments</h6>
            </a>
            <a href="career-portal.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h6>Career Portal</h6>
            </a>
            <a href="academic-calendar.php" class="action-card">
                <div class="icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h6>Academic Calendar</h6>
            </a>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>&copy; 2025 Rwanda Polytechnic | Student Management System</div>
        <div class="d-flex gap-3">
            <small><i class="fas fa-server me-1"></i>System Online</small>
            <small><i class="fas fa-clock me-1"></i>Last updated: <?php echo date('H:i:s'); ?></small>
            <small><i class="fas fa-user-graduate me-1"></i><?php echo htmlspecialchars($student['reg_no'] ?? 'N/A'); ?></small>
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

<style>
/* Enhanced CSS styles for new components */
/* ... (previous CSS styles remain the same) ... */

/* Academic Calendar Styles */
.academic-calendar-section {
    margin-bottom: 30px;
}

.calendar-events {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.calendar-event {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.calendar-event:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.event-date {
    text-align: center;
    min-width: 50px;
}

.event-day {
    font-size: 1.5rem;
    font-weight: 700;
    color: #007bff;
    line-height: 1;
}

.event-month {
    font-size: 0.8rem;
    color: #6c757d;
    text-transform: uppercase;
    font-weight: 600;
}

.event-details h6 {
    margin-bottom: 5px;
    font-weight: 600;
    color: #2c3e50;
}

/* Peer Comparison Styles */
.peer-comparison-section {
    margin-bottom: 30px;
}

.comparison-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
}

.comparison-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f8f9fa;
}

.comparison-item:last-child {
    border-bottom: none;
}

.comparison-label {
    font-weight: 500;
    color: #6c757d;
}

.comparison-value {
    font-weight: 700;
    color: #2c3e50;
    font-size: 1.1rem;
}

.comparison-stats {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
    text-align: center;
}

/* Career Opportunities Styles */
.career-opportunities-section {
    margin-bottom: 30px;
}

.opportunities-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.opportunity-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.opportunity-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.opportunity-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.opportunity-header h6 {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
    flex: 1;
    margin-right: 12px;
}

.opportunity-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.opportunity-deadline {
    font-size: 0.85rem;
    color: #6c757d;
}

/* Learning Resources Styles */
.learning-resources-section {
    margin-bottom: 30px;
}

.resources-grid {
    display: grid;
    gap: 12px;
}

.resource-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: inherit;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.resource-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    color: inherit;
    text-decoration: none;
}

.resource-icon {
    font-size: 1.5rem;
    color: #007bff;
    min-width: 40px;
}

.resource-content h6 {
    margin-bottom: 5px;
    font-weight: 600;
    color: #2c3e50;
}

.resource-content p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Campus Resources Styles */
.campus-resources-section {
    margin-bottom: 30px;
}

.resources-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.campus-resource {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.campus-resource:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.resource-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.resource-header h6 {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
}

.resource-description {
    margin-bottom: 8px;
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.4;
}

.resource-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

/* Health & Wellness Styles */
.health-wellness-section {
    margin-bottom: 30px;
}

.wellness-tips {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.wellness-tip {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.wellness-tip:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.wellness-tip .tip-icon {
    font-size: 1.2rem;
    color: #28a745;
    margin-top: 2px;
}

.wellness-tip .tip-content h6 {
    margin-bottom: 5px;
    font-weight: 600;
    color: #2c3e50;
}

.wellness-tip .tip-content p {
    margin: 0;
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.4;
}

/* Enhanced Statistics Cards */
.stat-card.library {
    border-left: 4px solid #17a2b8;
}

.stat-card.fees {
    border-left: 4px solid #ffc107;
}

.stat-card.career {
    border-left: 4px solid #6f42c1;
}

/* Responsive Design */
@media (max-width: 768px) {
    .calendar-event {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .event-date {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .event-day, .event-month {
        display: inline-block;
    }
    
    .opportunity-header {
        flex-direction: column;
        gap: 8px;
    }
    
    .resource-card {
        flex-direction: column;
        text-align: center;
    }
    
    .resource-icon {
        margin-bottom: 8px;
    }
}
</style>

</body>
</html>