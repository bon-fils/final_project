<?php
class LecturerDashboardController {
    private $pdo;
    private $user_id;
    private $lecturer_id;
    private $department_id;

    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->initializeLecturerData();
    }

    private function initializeLecturerData() {
        try {
            // Get lecturer information
            $stmt = $this->pdo->prepare("
                SELECT l.id as lecturer_id, l.department_id, d.name as department_name,
                       u.first_name, u.last_name, u.username, u.email
                FROM lecturers l
                INNER JOIN users u ON l.user_id = u.id
                LEFT JOIN departments d ON l.department_id = d.id
                WHERE u.id = ? AND u.role = 'lecturer'
            ");
            $stmt->execute([$this->user_id]);
            $lecturerData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturerData) {
                throw new Exception("Lecturer data not found");
            }

            $this->lecturer_id = $lecturerData['lecturer_id'];
            $this->department_id = $lecturerData['department_id'];

            // Store in session for compatibility
            $_SESSION['lecturer_id'] = $this->lecturer_id;
            $_SESSION['department_id'] = $this->department_id;
            $_SESSION['department_name'] = $lecturerData['department_name'] ?? 'Not Assigned';

        } catch (Exception $e) {
            error_log("Lecturer initialization error: " . $e->getMessage());
            $_SESSION['error'] = "Failed to initialize lecturer data";
            header("Location: index.php");
            exit();
        }
    }

    public function handleRequest() {
        if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
            $this->handleAjaxRequest();
            exit();
        }
    }

    private function handleAjaxRequest() {
        header('Content-Type: application/json');

        try {
            $stats = $this->getDashboardStats();
            $activities = $this->getRecentActivities();
            $attendanceTrends = $this->getAttendanceTrends();

            // Calculate attendance trends summary
            $totalAttendance7Days = array_sum(array_column($attendanceTrends, 'count'));
            $avgAttendance7Days = count($attendanceTrends) > 0 ? round($totalAttendance7Days / count($attendanceTrends), 1) : 0;
            $peakAttendance7Days = !empty($attendanceTrends) ? max(array_column($attendanceTrends, 'count')) : 0;
            $lowestAttendance7Days = !empty($attendanceTrends) ? min(array_column($attendanceTrends, 'count')) : 0;

            echo json_encode([
                'status' => 'success',
                'data' => array_merge($stats, [
                    'activities' => $activities,
                    'attendanceTrends' => $attendanceTrends,
                    'totalAttendance7Days' => $totalAttendance7Days,
                    'avgAttendance7Days' => $avgAttendance7Days,
                    'peakAttendance7Days' => $peakAttendance7Days,
                    'lowestAttendance7Days' => $lowestAttendance7Days
                ]),
                'timestamp' => time()
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to load dashboard data: ' . $e->getMessage()
            ]);
        }
    }

    public function getDashboardStats() {
        // Check cache first (5 minutes)
        $cache_key = 'lecturer_dashboard_' . $this->lecturer_id;
        $cache_file = 'cache/' . $cache_key . '.cache';
        $cache_time = 300;

        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
            $cached_data = json_decode(file_get_contents($cache_file), true);
            if ($cached_data) return $cached_data;
        }

        try {
            // Optimized single query for all stats
            $stmt = $this->pdo->prepare("
                SELECT
                    COUNT(CASE WHEN table_type = 'assigned_courses' THEN 1 END) AS assigned_courses,
                    COUNT(CASE WHEN table_type = 'total_students' THEN 1 END) AS total_students,
                    COUNT(CASE WHEN table_type = 'today_attendance' THEN 1 END) AS today_attendance,
                    COUNT(CASE WHEN table_type = 'week_attendance' THEN 1 END) AS week_attendance,
                    COUNT(CASE WHEN table_type = 'pending_leaves' THEN 1 END) AS pending_leaves,
                    COUNT(CASE WHEN table_type = 'approved_leaves' THEN 1 END) AS approved_leaves,
                    COUNT(CASE WHEN table_type = 'total_sessions' THEN 1 END) AS total_sessions,
                    COUNT(CASE WHEN table_type = 'active_sessions' THEN 1 END) AS active_sessions,
                    ROUND(AVG(CASE WHEN table_type = 'attendance_rate' THEN attendance_rate END), 1) AS average_attendance
                FROM (
                    SELECT 'assigned_courses' AS table_type, NULL AS attendance_rate FROM courses WHERE department_id = ?
                    UNION ALL
                    SELECT 'total_students', NULL FROM students st INNER JOIN options o ON st.option_id = o.id WHERE o.department_id = ?
                    UNION ALL
                    SELECT 'today_attendance', NULL FROM attendance_records ar
                    INNER JOIN attendance_sessions s ON ar.session_id = s.id
                    INNER JOIN courses c ON s.course_id = c.id
                    WHERE c.department_id = ? AND DATE(ar.recorded_at) = CURDATE()
                    UNION ALL
                    SELECT 'week_attendance', NULL FROM attendance_records ar
                    INNER JOIN attendance_sessions s ON ar.session_id = s.id
                    INNER JOIN courses c ON s.course_id = c.id
                    WHERE c.department_id = ? AND DATE(ar.recorded_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    UNION ALL
                    SELECT 'pending_leaves', NULL FROM leave_requests lr
                    INNER JOIN students st ON lr.student_id = st.id
                    INNER JOIN options o ON st.option_id = o.id
                    WHERE o.department_id = ? AND lr.status = 'pending'
                    UNION ALL
                    SELECT 'approved_leaves', NULL FROM leave_requests lr
                    INNER JOIN students st ON lr.student_id = st.id
                    INNER JOIN options o ON st.option_id = o.id
                    WHERE o.department_id = ? AND lr.status = 'approved'
                    UNION ALL
                    SELECT 'total_sessions', NULL FROM attendance_sessions s
                    INNER JOIN courses c ON s.course_id = c.id
                    WHERE c.department_id = ?
                    UNION ALL
                    SELECT 'active_sessions', NULL FROM attendance_sessions s
                    INNER JOIN courses c ON s.course_id = c.id
                    WHERE c.department_id = ? AND s.end_time IS NULL
                    UNION ALL
                    SELECT 'attendance_rate', (COUNT(CASE WHEN ar.status = 'present' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0))
                    FROM attendance_sessions s
                    LEFT JOIN attendance_records ar ON s.id = ar.session_id
                    INNER JOIN courses c ON s.course_id = c.id
                    WHERE c.department_id = ? AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY s.id
                ) AS combined_data
            ");

            $stmt->execute([
                $this->department_id, $this->department_id, $this->department_id, $this->department_id,
                $this->department_id, $this->department_id, $this->department_id, $this->department_id, $this->department_id
            ]);

            $counts = $stmt->fetch(PDO::FETCH_ASSOC);

            $stats = [
                'assignedCourses' => max(0, (int)($counts['assigned_courses'] ?? 0)),
                'totalStudents' => max(0, (int)($counts['total_students'] ?? 0)),
                'todayAttendance' => max(0, (int)($counts['today_attendance'] ?? 0)),
                'weekAttendance' => max(0, (int)($counts['week_attendance'] ?? 0)),
                'pendingLeaves' => max(0, (int)($counts['pending_leaves'] ?? 0)),
                'approvedLeaves' => max(0, (int)($counts['approved_leaves'] ?? 0)),
                'totalSessions' => max(0, (int)($counts['total_sessions'] ?? 0)),
                'activeSessions' => max(0, (int)($counts['active_sessions'] ?? 0)),
                'averageAttendance' => round(max(0, min(100, (float)($counts['average_attendance'] ?? 0))), 1),
                'last_updated' => date('Y-m-d H:i:s')
            ];

            // Cache results
            if (!is_dir('cache')) mkdir('cache', 0755, true);
            file_put_contents($cache_file, json_encode($stats));

            return $stats;

        } catch (PDOException $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
            return [
                'assignedCourses' => 0, 'totalStudents' => 0, 'todayAttendance' => 0,
                'weekAttendance' => 0, 'pendingLeaves' => 0, 'approvedLeaves' => 0,
                'totalSessions' => 0, 'activeSessions' => 0, 'averageAttendance' => 0,
                'last_updated' => date('Y-m-d H:i:s'), 'error' => 'Database connection failed'
            ];
        }
    }

    public function getRecentActivities() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    CASE
                        WHEN ar.id IS NOT NULL THEN 'Attendance Recorded'
                        WHEN s.id IS NOT NULL THEN 'Session Created'
                        WHEN lr.id IS NOT NULL THEN 'Leave Request'
                        ELSE 'Activity'
                    END as type,
                    CASE
                        WHEN ar.id IS NOT NULL THEN CONCAT('Attendance recorded for session: ', c.course_code)
                        WHEN s.id IS NOT NULL THEN CONCAT('New session created: ', c.course_code)
                        WHEN lr.id IS NOT NULL THEN CONCAT('Leave request from student: ', u.first_name, ' ', u.last_name)
                        ELSE 'Unknown activity'
                    END as description,
                    COALESCE(ar.recorded_at, s.created_at, lr.created_at) as timestamp
                FROM attendance_sessions s
                LEFT JOIN attendance_records ar ON s.id = ar.session_id
                LEFT JOIN leave_requests lr ON lr.student_id IN (
                    SELECT st.id FROM students st
                    INNER JOIN options o ON st.option_id = o.id
                    WHERE o.department_id = ?
                )
                LEFT JOIN courses c ON s.course_id = c.id
                LEFT JOIN students st ON lr.student_id = st.id
                LEFT JOIN users u ON st.user_id = u.id
                WHERE (s.lecturer_id = ? OR lr.id IS NOT NULL)
                AND COALESCE(ar.recorded_at, s.created_at, lr.created_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY COALESCE(ar.recorded_at, s.created_at, lr.created_at) DESC
                LIMIT 10
            ");
            $stmt->execute([$this->department_id, $this->lecturer_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Recent activities error: " . $e->getMessage());
            return [];
        }
    }

    public function getAttendanceTrends() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    DATE_FORMAT(ar.recorded_at, '%a') as day,
                    COUNT(*) as count
                FROM attendance_records ar
                INNER JOIN attendance_sessions s ON ar.session_id = s.id
                INNER JOIN courses c ON s.course_id = c.id
                WHERE c.department_id = ?
                AND ar.recorded_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                AND ar.status = 'present'
                GROUP BY DATE(ar.recorded_at)
                ORDER BY DATE(ar.recorded_at)
            ");
            $stmt->execute([$this->department_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Attendance trends error: " . $e->getMessage());
            return [];
        }
    }

    public function getLecturerInfo() {
        return [
            'lecturer_id' => $this->lecturer_id,
            'department_id' => $this->department_id,
            'department_name' => $_SESSION['department_name'] ?? 'Not Assigned'
        ];
    }
}
?>