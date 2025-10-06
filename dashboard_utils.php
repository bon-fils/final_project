<?php
/**
 * Dashboard Utility Functions
 * Contains functions for lecturer dashboard data retrieval and processing
 */

/**
 * Get recent activities for lecturer dashboard
 * @param PDO $pdo Database connection
 * @param int $user_id Lecturer ID
 * @return array Recent activities
 */
function getRecentActivities($pdo, $user_id) {
    $activities = [];

    try {
        // Get lecturer's department
        $deptStmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $deptStmt->execute([$user_id]);
        $lecturerDept = $deptStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturerDept || !$lecturerDept['department_id']) {
            return [];
        }

        // Recent attendance sessions - filter by department
        $sessionsStmt = $pdo->prepare("
            SELECT 'session' as type, c.name as course_name, s.session_date, COUNT(ar.id) as attendance_count
            FROM attendance_sessions s
            LEFT JOIN attendance_records ar ON s.id = ar.session_id
            INNER JOIN courses c ON s.course_id = c.id
            WHERE c.department_id = ?
            GROUP BY s.id
            ORDER BY s.session_date DESC
            LIMIT 3
        ");
        $sessionsStmt->execute([$lecturerDept['department_id']]);
        $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sessions as $session) {
            $activities[] = [
                'type' => 'session',
                'title' => 'Started session for ' . htmlspecialchars($session['course_name']),
                'detail' => $session['attendance_count'] . ' students attended',
                'time' => date('M d, H:i', strtotime($session['created_at'])),
                'icon' => 'fas fa-video'
            ];
        }

        // Recent leave requests - filter by department
        $leavesStmt = $pdo->prepare("
            SELECT 'leave' as type, lr.reason, lr.status, lr.requested_at, u.first_name, u.last_name
            FROM leave_requests lr
            INNER JOIN students st ON lr.student_id = st.id
            INNER JOIN users u ON st.user_id = u.id
            INNER JOIN options o ON st.option_id = o.id
            WHERE o.department_id = ?
            ORDER BY lr.requested_at DESC
            LIMIT 3
        ");
        $leavesStmt->execute([$lecturerDept['department_id']]);
        $leaves = $leavesStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($leaves as $leave) {
            $activities[] = [
                'type' => 'leave',
                'title' => htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']) . ' requested leave',
                'detail' => 'Reason: ' . htmlspecialchars($leave['reason']) . ' (' . $leave['status'] . ')',
                'time' => date('M d, H:i', strtotime($leave['created_at'])),
                'icon' => 'fas fa-envelope'
            ];
        }

        // Sort by time descending and limit to 5
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($activities, 0, 5);

    } catch (PDOException $e) {
        error_log("Error fetching recent activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get comprehensive dashboard data for lecturer
 * @param PDO $pdo Database connection
 * @param int $user_id Lecturer ID
 * @return array Dashboard statistics
 */
function getDashboardData($pdo, $user_id) {
    try {
        // First, add lecturer_id column to courses table if it doesn't exist
        try {
            $pdo->query("ALTER TABLE courses ADD COLUMN lecturer_id INT NULL AFTER department_id");
            $pdo->query("CREATE INDEX idx_lecturer_id ON courses(lecturer_id)");
        } catch (PDOException $e) {
            // Column might already exist, continue
        }

        // Get lecturer's department for proper filtering
        $deptStmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $deptStmt->execute([$user_id]);
        $lecturerDept = $deptStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturerDept || !$lecturerDept['department_id']) {
            // If no department assigned, return empty data
            return [
                'assignedCourses' => 0,
                'totalStudents' => 0,
                'todayAttendance' => 0,
                'weekAttendance' => 0,
                'pendingLeaves' => 0,
                'approvedLeaves' => 0,
                'totalSessions' => 0,
                'activeSessions' => 0,
                'averageAttendance' => 0
            ];
        }

        $department_id = $lecturerDept['department_id'];

        // Assigned Courses - filter by department
        $coursesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM courses WHERE department_id = ?");
        $coursesStmt->execute([$department_id]);
        $assignedCourses = $coursesStmt->fetch()['total'] ?? 0;

        // Total Students - filter by department
        $studentsStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT st.id) as total
            FROM students st
            INNER JOIN options o ON st.option_id = o.id
            WHERE o.department_id = ?
        ");
        $studentsStmt->execute([$department_id]);
        $totalStudents = $studentsStmt->fetch()['total'] ?? 0;

        // Today's Attendance - filter by department
        $today = date('Y-m-d');
        $attendanceStmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM attendance_records ar
            INNER JOIN attendance_sessions s ON ar.session_id = s.id
            INNER JOIN courses c ON s.course_id = c.id
            WHERE c.department_id = ? AND DATE(ar.recorded_at) = ?
        ");
        $attendanceStmt->execute([$department_id, $today]);
        $todayAttendance = $attendanceStmt->fetch()['total'] ?? 0;

        // This Week's Attendance - filter by department
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        $weekAttendanceStmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM attendance_records ar
            INNER JOIN attendance_sessions s ON ar.session_id = s.id
            INNER JOIN courses c ON s.course_id = c.id
            WHERE c.department_id = ? AND DATE(ar.recorded_at) BETWEEN ? AND ?
        ");
        $weekAttendanceStmt->execute([$department_id, $weekStart, $weekEnd]);
        $weekAttendance = $weekAttendanceStmt->fetch()['total'] ?? 0;

        // Pending Leaves - filter by department
        $leaveStmt = $pdo->prepare("
            SELECT COUNT(*) as pending
            FROM leave_requests lr
            INNER JOIN students st ON lr.student_id = st.id
            INNER JOIN options o ON st.option_id = o.id
            WHERE o.department_id = ? AND lr.status = 'pending'
        ");
        $leaveStmt->execute([$department_id]);
        $pendingLeaves = $leaveStmt->fetch()['pending'] ?? 0;

        // Approved Leaves - filter by department
        $approvedLeaveStmt = $pdo->prepare("
            SELECT COUNT(*) as approved
            FROM leave_requests lr
            INNER JOIN students st ON lr.student_id = st.id
            INNER JOIN options o ON st.option_id = o.id
            WHERE o.department_id = ? AND lr.status = 'approved'
        ");
        $approvedLeaveStmt->execute([$department_id]);
        $approvedLeaves = $approvedLeaveStmt->fetch()['approved'] ?? 0;

        // Total Sessions - filter by department
        $sessionsStmt = $pdo->prepare("
            SELECT COUNT(*) as total
            FROM attendance_sessions s
            INNER JOIN courses c ON s.course_id = c.id
            WHERE c.department_id = ?
        ");
        $sessionsStmt->execute([$department_id]);
        $totalSessions = $sessionsStmt->fetch()['total'] ?? 0;

        // Additional metrics
        $activeSessions = getActiveSessionsCount($pdo, $user_id);
        $averageAttendance = getAverageAttendanceRate($pdo, $user_id);

        // Get chart data
        $attendanceTrends = getAttendanceTrends($pdo, $user_id);
        $coursePerformance = getCoursePerformanceData($pdo, $user_id);

        return [
            'assignedCourses' => $assignedCourses,
            'totalStudents' => $totalStudents,
            'todayAttendance' => $todayAttendance,
            'weekAttendance' => $weekAttendance,
            'pendingLeaves' => $pendingLeaves,
            'approvedLeaves' => $approvedLeaves,
            'totalSessions' => $totalSessions,
            'activeSessions' => $activeSessions,
            'averageAttendance' => $averageAttendance,
            'attendanceTrends' => $attendanceTrends,
            'coursePerformance' => $coursePerformance
        ];

    } catch (PDOException $e) {
        error_log("Error fetching dashboard data: " . $e->getMessage());
        return [
            'assignedCourses' => 0,
            'totalStudents' => 0,
            'todayAttendance' => 0,
            'weekAttendance' => 0,
            'pendingLeaves' => 0,
            'approvedLeaves' => 0,
            'totalSessions' => 0,
            'activeSessions' => 0,
            'averageAttendance' => 0
        ];
    }
}

/**
 * Get count of currently active sessions
 * @param PDO $pdo Database connection
 * @param int $user_id Lecturer ID
 * @return int Number of active sessions
 */
function getActiveSessionsCount($pdo, $user_id) {
    try {
        // Get lecturer's department
        $deptStmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $deptStmt->execute([$user_id]);
        $lecturerDept = $deptStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturerDept || !$lecturerDept['department_id']) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active
            FROM attendance_sessions s
            INNER JOIN courses c ON s.course_id = c.id
            WHERE c.department_id = ?
        ");
        $stmt->execute([$lecturerDept['department_id']]);
        return $stmt->fetch()['active'] ?? 0;
    } catch (PDOException $e) {
        error_log("Error fetching active sessions: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calculate average attendance rate for lecturer's courses
 * @param PDO $pdo Database connection
 * @param int $user_id Lecturer ID
 * @return float Average attendance rate (percentage)
 */
function getAverageAttendanceRate($pdo, $user_id) {
    try {
        // Get lecturer's department
        $deptStmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $deptStmt->execute([$user_id]);
        $lecturerDept = $deptStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturerDept || !$lecturerDept['department_id']) {
            return 0;
        }

        $stmt = $pdo->prepare("
            SELECT
                COUNT(ar.id) as attended,
                COUNT(DISTINCT s.id) as total_sessions,
                COUNT(DISTINCT st.id) as total_students
            FROM courses c
            LEFT JOIN attendance_sessions s ON c.id = s.course_id
            LEFT JOIN attendance_records ar ON s.id = ar.session_id
            LEFT JOIN students st ON st.option_id = c.option_id
            WHERE c.department_id = ?
        ");
        $stmt->execute([$lecturerDept['department_id']]);
        $result = $stmt->fetch();

        if ($result['total_sessions'] > 0 && $result['total_students'] > 0) {
            $total_possible = $result['total_sessions'] * $result['total_students'];
            return round(($result['attended'] / $total_possible) * 100, 1);
        }

        return 0;
    } catch (PDOException $e) {
        error_log("Error calculating attendance rate: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get course performance data for charts
 * @param PDO $pdo Database connection
 * @param int $user_id Lecturer ID
 * @return array Course performance data
 */
function getCoursePerformanceData($pdo, $user_id) {
    try {
        // Get lecturer's department
        $deptStmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $deptStmt->execute([$user_id]);
        $lecturerDept = $deptStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturerDept || !$lecturerDept['department_id']) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                c.name as course_name,
                COUNT(DISTINCT s.id) as sessions_count,
                COUNT(ar.id) as attendance_count,
                COUNT(DISTINCT st.id) as student_count
            FROM courses c
            LEFT JOIN attendance_sessions s ON c.id = s.course_id
            LEFT JOIN attendance_records ar ON s.id = ar.session_id
            LEFT JOIN students st ON st.option_id = c.option_id
            WHERE c.department_id = ?
            GROUP BY c.id, c.name
            ORDER BY c.name
        ");
        $stmt->execute([$lecturerDept['department_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching course performance data: " . $e->getMessage());
        return [];
    }
}

/**
 * Get attendance trends for the past 7 days
 * @param PDO $pdo Database connection
 * @param int $user_id Lecturer ID
 * @return array Daily attendance data
 */
function getAttendanceTrends($pdo, $user_id) {
    try {
        // Get lecturer's department
        $deptStmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
        $deptStmt->execute([$user_id]);
        $lecturerDept = $deptStmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturerDept || !$lecturerDept['department_id']) {
            return [];
        }

        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM attendance_records ar
                INNER JOIN attendance_sessions s ON ar.session_id = s.id
                INNER JOIN courses c ON s.course_id = c.id
                WHERE c.department_id = ? AND DATE(ar.recorded_at) = ?
            ");
            $stmt->execute([$lecturerDept['department_id'], $date]);
            $result = $stmt->fetch();
            $trends[] = [
                'day' => date('M d', strtotime($date)),
                'count' => $result['count'] ?? 0
            ];
        }
        return $trends;
    } catch (PDOException $e) {
        error_log("Error fetching attendance trends: " . $e->getMessage());
        return [];
    }
}
?>