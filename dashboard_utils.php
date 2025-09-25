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
                'time' => date('M d, H:i', strtotime($session['created_at'])),
                'icon' => 'fas fa-video'
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
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active
            FROM attendance_sessions
            WHERE lecturer_id = ? AND status = 'active'
        ");
        $stmt->execute([$user_id]);
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
        $stmt = $pdo->prepare("
            SELECT
                COUNT(ar.id) as attended,
                COUNT(DISTINCT s.id) as total_sessions,
                COUNT(DISTINCT st.id) as total_students
            FROM courses c
            LEFT JOIN attendance_sessions s ON c.id = s.course_id AND s.lecturer_id = ?
            LEFT JOIN attendance_records ar ON s.id = ar.session_id
            LEFT JOIN students st ON c.id = st.option_id
            WHERE c.lecturer_id = ?
        ");
        $stmt->execute([$user_id, $user_id]);
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
        $stmt = $pdo->prepare("
            SELECT
                c.course_name,
                COUNT(DISTINCT s.id) as sessions_count,
                COUNT(ar.id) as attendance_count,
                COUNT(DISTINCT st.id) as student_count
            FROM courses c
            LEFT JOIN attendance_sessions s ON c.id = s.course_id AND s.lecturer_id = ?
            LEFT JOIN attendance_records ar ON s.id = ar.session_id
            LEFT JOIN students st ON c.id = st.option_id
            WHERE c.lecturer_id = ?
            GROUP BY c.id, c.course_name
            ORDER BY c.course_name
        ");
        $stmt->execute([$user_id, $user_id]);
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
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM attendance_records ar
                INNER JOIN attendance_sessions s ON ar.session_id = s.id
                WHERE s.lecturer_id = ? AND DATE(ar.recorded_at) = ?
            ");
            $stmt->execute([$user_id, $date]);
            $result = $stmt->fetch();
            $trends[] = [
                'date' => date('M d', strtotime($date)),
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