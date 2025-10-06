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
                    return [];
                case 'getStudyTips':
                case 'getCampusHighlights':
                    return [];
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

        error_log("StudentDashboard: Checking user for user_id: {$this->user_id}");
        error_log("StudentDashboard: User data: " . json_encode($user));

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

        // Debug logging
        error_log("StudentDashboard: Loading student data for user_id: {$this->user_id}");
        error_log("StudentDashboard: Query result: " . json_encode($this->student));
        error_log("StudentDashboard: Student data empty: " . (empty($this->student) ? 'true' : 'false'));

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
                lu.first_name as lecturer_fname, lu.last_name as lecturer_lname,
                c.name as course_name, c.code as course_code
            FROM attendance_records ar
            INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
            LEFT JOIN lecturers l ON sess.lecturer_id = l.id
            LEFT JOIN users lu ON l.user_id = lu.id
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
                lu.first_name as lecturer_fname, lu.last_name as lecturer_lname
            FROM attendance_sessions sess
            INNER JOIN courses c ON sess.course_id = c.id
            LEFT JOIN lecturers l ON sess.lecturer_id = l.id
            LEFT JOIN users lu ON l.user_id = lu.id
            WHERE sess.session_date = CURDATE()
            AND sess.option_id = ?
            ORDER BY sess.start_time ASC
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['option_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    
    private function getPerformanceStats(): array {
        try {
            $sql = "
                SELECT
                    (SELECT COUNT(DISTINCT course_id)
                     FROM attendance_sessions
                     WHERE option_id = ?
                     AND session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as enrolled_courses,

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
        } catch (PDOException $e) {
            error_log("Error in getPerformanceStats: " . $e->getMessage());
            return $this->getDefaultPerformanceStats();
        }
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

        // Check if student is not fully registered
        if (($this->student['id'] ?? 0) == 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'exclamation-triangle',
                'title' => 'Student Registration Incomplete',
                'message' => 'Your student profile is not fully set up. Please contact your administrator to complete your registration.',
                'action' => 'contact-admin.php'
            ];
        }
        
        return $notifications;
    }

    private function getCoursePerformance(): array {
        $sql = "
            SELECT
                c.id, c.name as course_name, c.code as course_code,
                COUNT(ar.id) as total_sessions,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                ROUND(
                    (SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(ar.id)), 1
                ) as attendance_percentage,
                MAX(ar.recorded_at) as last_attendance
            FROM courses c
            INNER JOIN attendance_sessions sess ON c.id = sess.course_id
            LEFT JOIN attendance_records ar ON sess.id = ar.session_id AND ar.student_id = ?
            WHERE sess.option_id = ?
            AND sess.session_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY c.id, c.name, c.code
            HAVING COUNT(ar.id) > 0
            ORDER BY attendance_percentage DESC
            LIMIT 6
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['id'], $this->student['option_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getStudyTips(): array {
        // Static study tips based on attendance performance
        $attendance_rate = $this->dashboard_data['attendance']['attendance_percentage'] ?? 0;

        $tips = [];

        if ($attendance_rate >= 90) {
            $tips[] = [
                'icon' => 'trophy',
                'title' => 'Excellent Attendance!',
                'message' => 'Keep up the great work. Consistent attendance leads to better learning outcomes.',
                'type' => 'success'
            ];
        } elseif ($attendance_rate >= 75) {
            $tips[] = [
                'icon' => 'thumbs-up',
                'title' => 'Good Attendance',
                'message' => 'You\'re doing well! Try to maintain this level for optimal academic performance.',
                'type' => 'info'
            ];
        } else {
            $tips[] = [
                'icon' => 'exclamation-triangle',
                'title' => 'Improve Attendance',
                'message' => 'Regular attendance is crucial for academic success. Aim for 80%+ attendance.',
                'type' => 'warning'
            ];
        }

        // Add general study tips
        $general_tips = [
            [
                'icon' => 'book',
                'title' => 'Active Learning',
                'message' => 'Engage actively in class discussions and take notes to improve retention.',
                'type' => 'info'
            ],
            [
                'icon' => 'clock',
                'title' => 'Time Management',
                'message' => 'Create a study schedule and stick to it for better academic performance.',
                'type' => 'primary'
            ],
            [
                'icon' => 'users',
                'title' => 'Study Groups',
                'message' => 'Join study groups to discuss concepts and learn from peers.',
                'type' => 'success'
            ]
        ];

        return array_merge($tips, $general_tips);
    }

    private function getCampusHighlights(): array {
        // Static campus highlights - in real app, this could come from database
        return [
            [
                'icon' => 'graduation-cap',
                'title' => 'Graduation Ceremony',
                'message' => 'Annual graduation ceremony scheduled for June 2025.',
                'date' => 'June 2025'
            ],
            [
                'icon' => 'flask',
                'title' => 'New Lab Equipment',
                'message' => 'State-of-the-art laboratory equipment installed in Chemistry department.',
                'date' => 'This Month'
            ],
            [
                'icon' => 'trophy',
                'title' => 'Sports Tournament',
                'message' => 'Inter-departmental sports tournament begins next week.',
                'date' => 'Next Week'
            ],
            [
                'icon' => 'book-open',
                'title' => 'Library Expansion',
                'message' => 'New wing added to the main library with digital resources.',
                'date' => 'Recently'
            ]
        ];
    }

    private function getUpcomingAssignments(): array {
        $sql = "
            SELECT
                a.id, a.title, a.description, a.due_date,
                c.name as course_name, c.code as course_code,
                DATEDIFF(a.due_date, CURDATE()) as days_remaining
            FROM assignments a
            INNER JOIN courses c ON a.course_id = c.id
            INNER JOIN attendance_sessions sess ON c.id = sess.course_id
            WHERE sess.option_id = ?
            AND a.due_date >= CURDATE()
            AND a.due_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
            GROUP BY a.id
            ORDER BY a.due_date ASC
            LIMIT 5
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['option_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getAttendanceInsights(): array {
        $insights = [];

        $attendance_rate = $this->dashboard_data['attendance']['attendance_percentage'] ?? 0;
        $total_sessions = $this->dashboard_data['attendance']['total_sessions'] ?? 0;
        $present_count = $this->dashboard_data['attendance']['present_count'] ?? 0;

        // Attendance streak
        $sql = "
            SELECT COUNT(*) as streak
            FROM (
                SELECT ar.status, ar.recorded_at
                FROM attendance_records ar
                WHERE ar.student_id = ?
                AND ar.status = 'present'
                ORDER BY ar.recorded_at DESC
                LIMIT 10
            ) as recent
            WHERE recorded_at >= (
                SELECT MAX(recorded_at) - INTERVAL 10 DAY
                FROM attendance_records
                WHERE student_id = ? AND status = 'absent'
            )
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['id'], $this->student['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $streak = (int)($result['streak'] ?? 0);

            if ($streak >= 5) {
                $insights[] = [
                    'type' => 'success',
                    'icon' => 'fire',
                    'title' => 'Attendance Streak!',
                    'message' => "You've been present for {$streak} consecutive sessions. Keep it up!",
                    'metric' => "{$streak} days"
                ];
            }
        } catch (Exception $e) {
            // Ignore streak calculation errors
        }

        // Weekly attendance comparison
        $sql = "
            SELECT
                YEARWEEK(recorded_at) as week,
                ROUND(AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END), 1) as weekly_rate
            FROM attendance_records
            WHERE student_id = ?
            AND recorded_at >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
            GROUP BY YEARWEEK(recorded_at)
            ORDER BY week DESC
            LIMIT 2
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['id']]);
            $weekly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($weekly_data) >= 2) {
                $current_week = $weekly_data[0]['weekly_rate'];
                $last_week = $weekly_data[1]['weekly_rate'];
                $difference = $current_week - $last_week;

                if ($difference > 5) {
                    $insights[] = [
                        'type' => 'success',
                        'icon' => 'arrow-up',
                        'title' => 'Improving Attendance',
                        'message' => "Your attendance improved by " . round($difference, 1) . "% this week!",
                        'metric' => "+{$difference}%"
                    ];
                } elseif ($difference < -5) {
                    $insights[] = [
                        'type' => 'warning',
                        'icon' => 'arrow-down',
                        'title' => 'Attendance Declining',
                        'message' => "Your attendance decreased by " . round(abs($difference), 1) . "% this week.",
                        'metric' => "{$difference}%"
                    ];
                }
            }
        } catch (Exception $e) {
            // Ignore weekly comparison errors
        }

        // Overall performance
        if ($attendance_rate >= 85) {
            $insights[] = [
                'type' => 'success',
                'icon' => 'star',
                'title' => 'Excellent Performance',
                'message' => 'Your attendance rate is outstanding! You\'re setting a great example.',
                'metric' => "{$attendance_rate}%"
            ];
        } elseif ($attendance_rate < 75) {
            $insights[] = [
                'type' => 'warning',
                'icon' => 'exclamation-triangle',
                'title' => 'Attendance Needs Attention',
                'message' => 'Consider improving your attendance to avoid academic penalties.',
                'metric' => "{$attendance_rate}%"
            ];
        }

        return array_slice($insights, 0, 3); // Limit to 3 insights
    }

    private function getQuickStats(): array {
        return [
            [
                'label' => 'This Week',
                'value' => $this->getWeeklyAttendance(),
                'icon' => 'calendar-week',
                'color' => 'primary'
            ],
            [
                'label' => 'This Month',
                'value' => $this->getMonthlyAttendance(),
                'icon' => 'calendar-alt',
                'color' => 'info'
            ],
            [
                'label' => 'Best Course',
                'value' => $this->getBestPerformingCourse(),
                'icon' => 'trophy',
                'color' => 'success'
            ],
            [
                'label' => 'Study Streak',
                'value' => $this->getStudyStreak(),
                'icon' => 'fire',
                'color' => 'warning'
            ]
        ];
    }

    private function getWeeklyAttendance(): string {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM attendance_records
            WHERE student_id = ?
            AND YEARWEEK(recorded_at) = YEARWEEK(CURDATE())
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['total'] > 0) {
                $percentage = round(($result['present'] / $result['total']) * 100, 1);
                return "{$percentage}% ({$result['present']}/{$result['total']})";
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return 'No data';
    }

    private function getMonthlyAttendance(): string {
        $sql = "
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
            FROM attendance_records
            WHERE student_id = ?
            AND MONTH(recorded_at) = MONTH(CURDATE())
            AND YEAR(recorded_at) = YEAR(CURDATE())
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['total'] > 0) {
                $percentage = round(($result['present'] / $result['total']) * 100, 1);
                return "{$percentage}% ({$result['present']}/{$result['total']})";
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return 'No data';
    }

    private function getBestPerformingCourse(): string {
        $sql = "
            SELECT
                c.code as course_code,
                ROUND(AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END), 1) as attendance_rate
            FROM courses c
            INNER JOIN attendance_sessions sess ON c.id = sess.course_id
            LEFT JOIN attendance_records ar ON sess.id = ar.session_id AND ar.student_id = ?
            WHERE sess.option_id = ?
            AND sess.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY c.id, c.code
            HAVING COUNT(ar.id) > 0
            ORDER BY attendance_rate DESC
            LIMIT 1
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['id'], $this->student['option_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                return "{$result['course_code']} ({$result['attendance_rate']}%)";
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return 'No data';
    }

    private function getStudyStreak(): string {
        $sql = "
            SELECT COUNT(*) as streak
            FROM (
                SELECT ar.recorded_at, ar.status,
                       ROW_NUMBER() OVER (ORDER BY ar.recorded_at DESC) as rn
                FROM attendance_records ar
                WHERE ar.student_id = ?
                AND ar.status = 'present'
                ORDER BY ar.recorded_at DESC
            ) as numbered
            WHERE rn <= (
                SELECT MIN(rn) FROM (
                    SELECT ROW_NUMBER() OVER (ORDER BY recorded_at DESC) as rn
                    FROM attendance_records
                    WHERE student_id = ?
                    AND status = 'absent'
                    AND recorded_at >= (SELECT MIN(recorded_at) FROM attendance_records WHERE student_id = ? AND status = 'present')
                ) as absences
            )
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$this->student['id'], $this->student['id'], $this->student['id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['streak'] > 0) {
                return $result['streak'] . ' days';
            }
        } catch (Exception $e) {
            // Ignore errors
        }

        return '0 days';
    }

    private function getMotivationalQuote(): array {
        $quotes = [
            [
                'quote' => 'The only way to do great work is to love what you do.',
                'author' => 'Steve Jobs'
            ],
            [
                'quote' => 'Education is the most powerful weapon which you can use to change the world.',
                'author' => 'Nelson Mandela'
            ],
            [
                'quote' => 'The beautiful thing about learning is that no one can take it away from you.',
                'author' => 'B.B. King'
            ],
            [
                'quote' => 'Success is not final, failure is not fatal: It is the courage to continue that counts.',
                'author' => 'Winston Churchill'
            ],
            [
                'quote' => 'Your education is a dress rehearsal for a life that is yours to lead.',
                'author' => 'Nora Ephron'
            ]
        ];

        return $quotes[array_rand($quotes)];
    }

    private function getRecentActivities(): array {
        $sql = "
            SELECT
                'attendance' as type,
                CONCAT('Marked attendance for ', c.name, ' (', c.code, ')') as description,
                ar.recorded_at as timestamp
            FROM attendance_records ar
            INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
            INNER JOIN courses c ON sess.course_id = c.id
            WHERE ar.student_id = ?
            ORDER BY ar.recorded_at DESC
            LIMIT 5
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getAttendanceTrends(): array {
        $sql = "
            SELECT
                DATE(ar.recorded_at) as date,
                COUNT(*) as count,
                DAYNAME(ar.recorded_at) as day
            FROM attendance_records ar
            WHERE ar.student_id = ?
            AND ar.recorded_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(ar.recorded_at), DAYNAME(ar.recorded_at)
            ORDER BY DATE(ar.recorded_at) DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$this->student['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
            'course_performance' => [],
            'study_tips' => $this->getStudyTips(), // Static method, safe to call
            'campus_highlights' => $this->getCampusHighlights(), // Static method, safe to call
            'upcoming_assignments' => [],
            'attendance_insights' => [],
            'quick_stats' => $this->getQuickStats(), // Static method, safe to call
            'motivational_quote' => $this->getMotivationalQuote(), // Static method, safe to call
            'recent_activities' => [],
            'attendance_trends' => [],
            'notifications' => $this->getNotifications() // This depends on other data, but should be safe
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
        // Don't redirect - allow dashboard to load with fallback data
        // The dashboard class has already set fallback data internally
    }

    // Always extract data for template rendering (dashboard provides fallback data)
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
    $course_performance = $dashboard_data['course_performance'];
    $study_tips = $dashboard_data['study_tips'];
    $campus_highlights = $dashboard_data['campus_highlights'];
    $upcoming_assignments = $dashboard_data['upcoming_assignments'];
    $attendance_insights = $dashboard_data['attendance_insights'];
    $quick_stats = $dashboard_data['quick_stats'];
    $motivational_quote = $dashboard_data['motivational_quote'];
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
    $course_performance = [];
    $study_tips = [];
    $campus_highlights = [];
    $upcoming_assignments = [];
    $attendance_insights = [];
    $quick_stats = [];
    $motivational_quote = ['quote' => 'Education is the key to success.', 'author' => 'Unknown'];
    $recent_activities = [];
    $attendance_trends = [];
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
        
            <!-- Upcoming Assignments -->
            <?php if (count($upcoming_assignments) > 0): ?>
            <div class="upcoming-assignments-section mt-4">
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
                                <i class="fas fa-book me-1"></i><?php echo htmlspecialchars($assignment['course_name']); ?> (<?php echo htmlspecialchars($assignment['course_code']); ?>)
                            </small>
                            <div class="assignment-due-date">
                                <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        
            <!-- Recent Activities -->
            <?php if (count($recent_activities) > 0): ?>
            <div class="recent-activities-section mt-4">
                <h5 class="mb-3">
                    <i class="fas fa-history me-2 text-primary"></i>Recent Activities
                </h5>
                <div class="activities-list">
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-calendar-check text-success"></i>
                        </div>
                        <div class="activity-content">
                            <p class="mb-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i><?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
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

    <!-- Course Performance Overview -->
    <?php if (count($course_performance) > 0): ?>
    <div class="performance-overview-section">
        <h5 class="mb-3">
            <i class="fas fa-chart-bar me-2 text-primary"></i>Course Performance Overview
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

    <!-- Study Tips -->
    <?php if (count($study_tips) > 0): ?>
    <div class="study-tips-section mt-4">
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

    <!-- Campus Highlights -->
    <?php if (count($campus_highlights) > 0): ?>
    <div class="campus-highlights-section mt-4">
        <h5 class="mb-3">
            <i class="fas fa-star me-2 text-primary"></i>Campus Highlights
        </h5>
        <div class="campus-highlights-card">
            <?php foreach ($campus_highlights as $highlight): ?>
            <div class="highlight-item">
                <div class="highlight-icon">
                    <i class="fas fa-<?php echo htmlspecialchars($highlight['icon']); ?>"></i>
                </div>
                <div class="highlight-content">
                    <h6><?php echo htmlspecialchars($highlight['title']); ?></h6>
                    <p><?php echo htmlspecialchars($highlight['message']); ?></p>
                    <small class="text-muted"><?php echo htmlspecialchars($highlight['date']); ?></small>
                </div>
            </div>
            <?php endforeach; ?>
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

<style>
/* ===== QUICK STATS SECTION ===== */
.quick-stats-section {
    margin-bottom: 30px;
}

.quick-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.quick-stat-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.quick-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 2rem;
    opacity: 0.8;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 1.2rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    font-weight: 500;
}

/* ===== ATTENDANCE INSIGHTS ===== */
.attendance-insights-section {
    margin-bottom: 30px;
}

.insights-grid {
    display: grid;
    gap: 15px;
}

.insight-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
    gap: 15px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.insight-card.success {
    border-left: 4px solid #28a745;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.05) 0%, rgba(40, 167, 69, 0.02) 100%);
}

.insight-card.warning {
    border-left: 4px solid #ffc107;
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(255, 193, 7, 0.02) 100%);
}

.insight-card.info {
    border-left: 4px solid #17a2b8;
    background: linear-gradient(135deg, rgba(23, 162, 184, 0.05) 0%, rgba(23, 162, 184, 0.02) 100%);
}

.insight-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.insight-icon {
    font-size: 1.5rem;
    margin-top: 2px;
}

.insight-card.success .insight-icon {
    color: #28a745;
}

.insight-card.warning .insight-icon {
    color: #ffc107;
}

.insight-card.info .insight-icon {
    color: #17a2b8;
}

.insight-content h6 {
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
}

.insight-content p {
    margin-bottom: 8px;
    color: #6c757d;
    font-size: 0.9rem;
    line-height: 1.4;
}

.insight-metric {
    font-weight: 700;
    font-size: 0.9rem;
    color: #495057;
}

/* ===== UPCOMING ASSIGNMENTS ===== */
.upcoming-assignments-section {
    margin-bottom: 30px;
}

.assignments-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.assignment-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.assignment-item.urgent {
    border-left: 4px solid #dc3545;
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.05) 0%, rgba(220, 53, 69, 0.02) 100%);
}

.assignment-item.warning {
    border-left: 4px solid #ffc107;
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.05) 0%, rgba(255, 193, 7, 0.02) 100%);
}

.assignment-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.assignment-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.assignment-header h6 {
    margin: 0;
    font-weight: 600;
    color: #2c3e50;
    flex: 1;
    margin-right: 12px;
}

.assignment-details {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.assignment-due-date {
    font-size: 0.85rem;
    color: #6c757d;
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 768px) {
    .quick-stats-grid {
        grid-template-columns: 1fr;
    }

    .quick-stat-card {
        padding: 15px;
    }

    .stat-icon {
        font-size: 1.5rem;
    }

    .stat-value {
        font-size: 1rem;
    }

    .insights-grid {
        gap: 10px;
    }

    .insight-card {
        padding: 15px;
        flex-direction: column;
        text-align: center;
    }

    .assignments-list {
        gap: 10px;
    }

    .assignment-item {
        padding: 12px;
    }

    .assignment-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .assignment-details {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}

/* ===== RECENT ACTIVITIES ===== */
.recent-activities-section {
    margin-bottom: 30px;
}

.activities-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.activity-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.activity-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.activity-icon {
    font-size: 1.2rem;
    color: #28a745;
    min-width: 20px;
}

.activity-content {
    flex: 1;
}

.activity-content p {
    margin: 0;
    color: #2c3e50;
    font-size: 0.9rem;
}
</style>

</body>
</html>
