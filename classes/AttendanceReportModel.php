<?php
/**
 * Attendance Report Model
 * Handles all database operations for attendance reporting
 * Optimized queries with proper error handling
 */

class AttendanceReportModel {
    private $pdo;
    private $cache = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Get user context and permissions
     */
    public function getUserContext(int $userId, string $userRole): array {
        try {
            if ($userRole === 'admin') {
                return ['lecturer_id' => null, 'department_id' => null];
            }

            $stmt = $this->pdo->prepare("
                SELECT l.id as lecturer_id, l.department_id, l.first_name, l.last_name
                FROM lecturers l
                INNER JOIN users u ON l.email = u.email
                WHERE u.id = :user_id AND u.role IN ('lecturer', 'hod')
            ");
            $stmt->execute(['user_id' => $userId]);
            $lecturerData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturerData) {
                // Try to create lecturer record
                $this->createLecturerRecord($userId);
                $stmt->execute(['user_id' => $userId]);
                $lecturerData = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$lecturerData) {
                    throw new Exception('Lecturer setup required');
                }
            }

            return $lecturerData;

        } catch (Exception $e) {
            error_log("Error getting user context: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create lecturer record if missing
     */
    private function createLecturerRecord(int $userId): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO lecturers (first_name, last_name, email, department_id, role, password, created_at)
                SELECT
                    CASE WHEN LOCATE(' ', username) > 0 THEN SUBSTRING_INDEX(username, ' ', 1) ELSE username END as first_name,
                    CASE WHEN LOCATE(' ', username) > 0 THEN SUBSTRING_INDEX(username, ' ', -1) ELSE '' END as last_name,
                    email, 1, u.role, 'default_password', NOW()
                FROM users u
                WHERE id = :user_id AND role IN ('lecturer', 'hod')
                ON DUPLICATE KEY UPDATE email = email
            ");
            $stmt->execute(['user_id' => $userId]);
        } catch (PDOException $e) {
            error_log("Error creating lecturer record: " . $e->getMessage());
        }
    }

    /**
     * Ensure database schema is up to date
     */
    public function ensureDatabaseSchema(): void {
        try {
            $this->pdo->exec("ALTER TABLE courses ADD COLUMN IF NOT EXISTS lecturer_id INT NULL AFTER department_id");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_lecturer_id ON courses(lecturer_id)");
            $this->pdo->exec("ALTER TABLE courses ADD CONSTRAINT IF NOT EXISTS fk_courses_lecturer FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE SET NULL");
        } catch (PDOException $e) {
            error_log("Schema update warning: " . $e->getMessage());
        }
    }

    /**
     * Get available departments for user
     */
    public function getAvailableDepartments(?int $lecturerId, bool $isAdmin): array {
        try {
            if ($isAdmin) {
                $stmt = $this->pdo->query("SELECT id, name FROM departments ORDER BY name ASC");
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT d.id, d.name
                    FROM departments d
                    INNER JOIN lecturers l ON d.id = l.department_id
                    WHERE l.id = :lecturer_id
                    ORDER BY d.name ASC
                ");
                $stmt->execute(['lecturer_id' => $lecturerId]);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching departments: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get options for department
     */
    public function getOptionsForDepartment(int $departmentId, ?int $lecturerId, bool $isAdmin): array {
        try {
            $query = "SELECT id, name FROM options WHERE department_id = :department_id ORDER BY name ASC";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute(['department_id' => $departmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching options: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get available classes (year levels)
     */
    public function getAvailableClasses(?int $lecturerId, bool $isAdmin): array {
        try {
            if ($isAdmin) {
                $stmt = $this->pdo->query("
                    SELECT DISTINCT year_level as id, CONCAT('Year ', year_level) as name
                    FROM students
                    WHERE year_level IS NOT NULL
                    ORDER BY year_level ASC
                ");
            } else {
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT s.year_level as id, CONCAT('Year ', s.year_level) as name
                    FROM students s
                    INNER JOIN lecturers l ON s.department_id = l.department_id
                    WHERE l.id = :lecturer_id AND s.year_level IS NOT NULL
                    ORDER BY s.year_level ASC
                ");
                $stmt->execute(['lecturer_id' => $lecturerId]);
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching classes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get courses for class
     */
    public function getCoursesForClass(int $classId, ?int $lecturerId, bool $isAdmin): array {
        try {
            $query = "
                SELECT DISTINCT c.id, c.course_name as name, c.course_code
                FROM courses c
                INNER JOIN options o ON c.option_id = o.id
                INNER JOIN students s ON o.id = s.option_id
                WHERE s.year_level = :year_level
            ";

            $params = ['year_level' => $classId];

            if (!$isAdmin && $lecturerId) {
                $query .= " AND c.lecturer_id = :lecturer_id";
                $params['lecturer_id'] = $lecturerId;
            }

            $query .= " ORDER BY c.course_name ASC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching courses: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate attendance report based on type
     */
    public function generateReport(string $reportType, array $filters, ?int $lecturerId, bool $isAdmin): array {
        switch ($reportType) {
            case 'department':
                return $this->generateDepartmentReport($filters['department_id'], $filters['start_date'], $filters['end_date'], $lecturerId, $isAdmin);
            case 'option':
                return $this->generateOptionReport($filters['option_id'], $filters['start_date'], $filters['end_date'], $lecturerId, $isAdmin);
            case 'class':
                return $this->generateClassReport($filters['class_id'], $filters['start_date'], $filters['end_date'], $lecturerId, $isAdmin);
            case 'course':
                return $this->generateCourseReport($filters['course_id'], $filters['start_date'], $filters['end_date'], $lecturerId, $isAdmin);
            default:
                throw new InvalidArgumentException('Invalid report type');
        }
    }

    /**
     * Generate department report
     */
    private function generateDepartmentReport(int $departmentId, ?string $startDate, ?string $endDate, ?int $lecturerId, bool $isAdmin): array {
        $students = $this->getStudentsForDepartment($departmentId, $lecturerId, $isAdmin);
        if (empty($students)) {
            return $this->getEmptyReportStructure();
        }

        $courses = $this->getCoursesForDepartment($departmentId, $lecturerId, $isAdmin);
        $sessions = $this->getSessionsForCourses(array_keys($courses), $startDate, $endDate);
        $attendanceRecords = $this->getAttendanceRecordsForCourses(array_keys($courses), array_keys($students), $startDate, $endDate);

        return [
            'department_info' => ['id' => $departmentId, 'name' => $this->getDepartmentName($departmentId)],
            'students' => $students,
            'courses' => $courses,
            'sessions' => $sessions,
            'attendance' => $this->processAttendanceData($students, $sessions, $attendanceRecords),
            'summary' => $this->calculateAttendanceSummary($students, $sessions, $attendanceRecords),
            'date_range' => ['start' => $startDate, 'end' => $endDate]
        ];
    }

    /**
     * Generate course report (most common use case)
     */
    private function generateCourseReport(int $courseId, ?string $startDate, ?string $endDate, ?int $lecturerId, bool $isAdmin): array {
        $courseInfo = $this->getCourseInfo($courseId);
        if (!$courseInfo) {
            throw new Exception('Course not found');
        }

        $students = $this->getStudentsForCourse($courseInfo['year_level'] ?? null, $courseId, $lecturerId, $isAdmin);
        if (empty($students)) {
            return $this->getEmptyReportStructure();
        }

        $sessions = $this->getAttendanceSessions($courseId, $startDate, $endDate);
        $attendanceRecords = $this->getAttendanceRecords($courseId, array_keys($students), $startDate, $endDate);

        return [
            'course_info' => $courseInfo,
            'students' => $students,
            'sessions' => $sessions,
            'attendance' => $this->processAttendanceData($students, $sessions, $attendanceRecords),
            'summary' => $this->calculateAttendanceSummary($students, $sessions, $attendanceRecords),
            'date_range' => ['start' => $startDate, 'end' => $endDate]
        ];
    }

    /**
     * Get students for course
     */
    private function getStudentsForCourse(?int $classId, int $courseId, ?int $lecturerId, bool $isAdmin): array {
        try {
            $query = "
                SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as full_name,
                        s.reg_no, s.student_id_number, d.name as department_name
                FROM students s
                INNER JOIN departments d ON s.department_id = d.id
                WHERE s.year_level = :year_level
            ";

            $params = ['year_level' => $classId];

            if (!$isAdmin && $lecturerId) {
                $query .= " AND s.department_id = (SELECT department_id FROM lecturers WHERE id = :lecturer_id)";
                $params['lecturer_id'] = $lecturerId;
            }

            $query .= " ORDER BY s.first_name, s.last_name";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $students = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $students[$row['id']] = $row;
            }

            return $students;
        } catch (PDOException $e) {
            error_log("Error fetching students: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get attendance sessions for course
     */
    private function getAttendanceSessions(int $courseId, ?string $startDate, ?string $endDate): array {
        try {
            $query = "SELECT id, session_date, start_time, end_time FROM attendance_sessions WHERE course_id = :course_id";
            $params = ['course_id' => $courseId];

            if ($startDate && $endDate) {
                $query .= " AND session_date BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            $query .= " ORDER BY session_date ASC, start_time ASC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $sessions = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sessions[$row['id']] = $row;
            }

            return $sessions;
        } catch (PDOException $e) {
            error_log("Error fetching sessions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get attendance records for course
     */
    private function getAttendanceRecords(int $courseId, array $studentIds, ?string $startDate, ?string $endDate): array {
        if (empty($studentIds)) return [];

        try {
            $studentPlaceholders = str_repeat('?,', count($studentIds) - 1) . '?';

            $query = "
                SELECT ar.student_id, ar.session_id, ar.status, ar.recorded_at
                FROM attendance_records ar
                INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
                WHERE sess.course_id = :course_id AND ar.student_id IN ($studentPlaceholders)
            ";

            $params = array_merge(['course_id' => $courseId], $studentIds);

            if ($startDate && $endDate) {
                $query .= " AND sess.session_date BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $records = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $records[$row['student_id']][$row['session_id']] = $row;
            }

            return $records;
        } catch (PDOException $e) {
            error_log("Error fetching attendance records: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Process attendance data into structured format
     */
    private function processAttendanceData(array $students, array $sessions, array $attendanceRecords): array {
        $processedData = [];

        foreach ($students as $studentId => $student) {
            $studentAttendance = [
                'student_info' => $student,
                'sessions' => [],
                'summary' => [
                    'total_sessions' => count($sessions),
                    'present_count' => 0,
                    'absent_count' => 0,
                    'percentage' => 0
                ]
            ];

            foreach ($sessions as $sessionId => $session) {
                $record = $attendanceRecords[$studentId][$sessionId] ?? null;
                $status = $record ? $record['status'] : 'absent';

                $studentAttendance['sessions'][$sessionId] = [
                    'session_info' => $session,
                    'status' => $status,
                    'recorded_at' => $record ? $record['recorded_at'] : null
                ];

                if ($status === 'present') {
                    $studentAttendance['summary']['present_count']++;
                } else {
                    $studentAttendance['summary']['absent_count']++;
                }
            }

            // Calculate percentage
            if ($studentAttendance['summary']['total_sessions'] > 0) {
                $studentAttendance['summary']['percentage'] =
                    round(($studentAttendance['summary']['present_count'] / $studentAttendance['summary']['total_sessions']) * 100, 1);
            }

            $processedData[$studentId] = $studentAttendance;
        }

        return $processedData;
    }

    /**
     * Calculate attendance summary statistics
     */
    private function calculateAttendanceSummary(array $students, array $sessions, array $attendanceRecords): array {
        $totalStudents = count($students);
        $totalSessions = count($sessions);

        $summary = [
            'total_students' => $totalStudents,
            'total_sessions' => $totalSessions,
            'total_possible_attendances' => $totalStudents * $totalSessions,
            'total_actual_attendances' => 0,
            'average_attendance_rate' => 0,
            'students_above_85_percent' => 0,
            'students_below_85_percent' => 0,
            'perfect_attendance' => 0,
            'zero_attendance' => 0
        ];

        $totalPercentage = 0;

        foreach ($students as $studentId => $student) {
            $presentCount = 0;

            foreach ($sessions as $sessionId => $session) {
                $record = $attendanceRecords[$studentId][$sessionId] ?? null;
                if ($record && $record['status'] === 'present') {
                    $presentCount++;
                    $summary['total_actual_attendances']++;
                }
            }

            $percentage = $totalSessions > 0 ? ($presentCount / $totalSessions) * 100 : 0;
            $totalPercentage += $percentage;

            if ($percentage >= 85) {
                $summary['students_above_85_percent']++;
            } else {
                $summary['students_below_85_percent']++;
            }

            if ($percentage == 100) {
                $summary['perfect_attendance']++;
            } elseif ($percentage == 0) {
                $summary['zero_attendance']++;
            }
        }

        if ($totalStudents > 0) {
            $summary['average_attendance_rate'] = round($totalPercentage / $totalStudents, 1);
        }

        return $summary;
    }

    /**
     * Get course information
     */
    private function getCourseInfo(int $courseId): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.id, c.course_name, c.course_code, d.name as department_name,
                        CONCAT(l.first_name, ' ', l.last_name) as lecturer_name
                FROM courses c
                LEFT JOIN departments d ON c.department_id = d.id
                LEFT JOIN lecturers l ON c.lecturer_id = l.id
                WHERE c.id = :course_id
            ");
            $stmt->execute(['course_id' => $courseId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Helper methods for other report types
     */
    private function getStudentsForDepartment(int $departmentId, ?int $lecturerId, bool $isAdmin): array {
        // Implementation similar to getStudentsForCourse but for department scope
        try {
            $query = "
                SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as full_name,
                        s.reg_no, s.student_id_number, d.name as department_name, s.year_level
                FROM students s
                INNER JOIN departments d ON s.department_id = d.id
                WHERE s.department_id = :department_id
            ";

            $params = ['department_id' => $departmentId];
            $query .= " ORDER BY s.year_level, s.first_name, s.last_name";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $students = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $students[$row['id']] = $row;
            }

            return $students;
        } catch (PDOException $e) {
            error_log("Error fetching students for department: " . $e->getMessage());
            return [];
        }
    }

    private function getCoursesForDepartment(int $departmentId, ?int $lecturerId, bool $isAdmin): array {
        try {
            $query = "SELECT id, course_name as name, course_code FROM courses WHERE department_id = :department_id";
            $params = ['department_id' => $departmentId];

            if (!$isAdmin && $lecturerId) {
                $query .= " AND lecturer_id = :lecturer_id";
                $params['lecturer_id'] = $lecturerId;
            }

            $query .= " ORDER BY course_name ASC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $courses = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $courses[$row['id']] = $row;
            }

            return $courses;
        } catch (PDOException $e) {
            error_log("Error fetching courses for department: " . $e->getMessage());
            return [];
        }
    }

    private function getSessionsForCourses(array $courseIds, ?string $startDate, ?string $endDate): array {
        if (empty($courseIds)) return [];

        try {
            $placeholders = str_repeat('?,', count($courseIds) - 1) . '?';

            $query = "SELECT id, course_id, session_date, start_time, end_time FROM attendance_sessions WHERE course_id IN ($placeholders)";
            $params = $courseIds;

            if ($startDate && $endDate) {
                $query .= " AND session_date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $query .= " ORDER BY session_date ASC, start_time ASC";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $sessions = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sessions[$row['id']] = $row;
            }

            return $sessions;
        } catch (PDOException $e) {
            error_log("Error fetching sessions for courses: " . $e->getMessage());
            return [];
        }
    }

    private function getAttendanceRecordsForCourses(array $courseIds, array $studentIds, ?string $startDate, ?string $endDate): array {
        if (empty($courseIds) || empty($studentIds)) return [];

        try {
            $coursePlaceholders = str_repeat('?,', count($courseIds) - 1) . '?';
            $studentPlaceholders = str_repeat('?,', count($studentIds) - 1) . '?';

            $query = "
                SELECT ar.student_id, ar.session_id, ar.status, ar.recorded_at
                FROM attendance_records ar
                INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
                WHERE sess.course_id IN ($coursePlaceholders) AND ar.student_id IN ($studentPlaceholders)
            ";

            $params = array_merge($courseIds, $studentIds);

            if ($startDate && $endDate) {
                $query .= " AND sess.session_date BETWEEN ? AND ?";
                $params[] = $startDate;
                $params[] = $endDate;
            }

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            $records = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $records[$row['student_id']][$row['session_id']] = $row;
            }

            return $records;
        } catch (PDOException $e) {
            error_log("Error fetching attendance records for courses: " . $e->getMessage());
            return [];
        }
    }

    private function getDepartmentName(int $departmentId): string {
        try {
            $stmt = $this->pdo->prepare("SELECT name FROM departments WHERE id = :id");
            $stmt->execute(['id' => $departmentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['name'] : 'Unknown Department';
        } catch (PDOException $e) {
            return 'Unknown Department';
        }
    }

    private function getEmptyReportStructure(): array {
        return [
            'students' => [],
            'sessions' => [],
            'attendance' => [],
            'summary' => []
        ];
    }

    // Placeholder methods for other report types
    private function generateOptionReport(int $optionId, ?string $startDate, ?string $endDate, ?int $lecturerId, bool $isAdmin): array {
        // Similar implementation to department report but scoped to option
        return $this->getEmptyReportStructure();
    }

    private function generateClassReport(int $classId, ?string $startDate, ?string $endDate, ?int $lecturerId, bool $isAdmin): array {
        // Similar implementation to course report but for entire class
        return $this->getEmptyReportStructure();
    }
}