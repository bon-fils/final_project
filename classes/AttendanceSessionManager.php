<?php
/**
 * Attendance Session Manager
 * Handles session lifecycle management and validation
 */

class AttendanceSessionManager {
    private $pdo;
    private $logger;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logger = new AttendanceSessionLogger();
    }

    /**
     * Create a new attendance session
     */
    public function createSession($lecturerId, $courseId, $optionId, $biometricMethod) {
        // Validate inputs
        $this->validateSessionCreation($lecturerId, $courseId, $optionId, $biometricMethod);

        // Check for existing active session
        $existingSession = $this->getActiveSessionForCourse($courseId);
        if ($existingSession) {
            throw new Exception('An active session already exists for this course');
        }

        // Start transaction
        $this->pdo->beginTransaction();

        try {
            // Create new session
            $stmt = $this->pdo->prepare("
                INSERT INTO attendance_sessions
                (lecturer_id, course_id, option_id, session_date, start_time, biometric_method, status)
                VALUES (?, ?, ?, CURDATE(), NOW(), ?, 'active')
            ");
            $stmt->execute([$lecturerId, $courseId, $optionId, $biometricMethod]);
            $sessionId = $this->pdo->lastInsertId();

            $this->pdo->commit();

            $this->logger->logSessionOperation('created', $sessionId, [
                'lecturer_id' => $lecturerId,
                'course_id' => $courseId,
                'biometric_method' => $biometricMethod
            ]);

            return $sessionId;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * End an active session
     */
    public function endSession($sessionId, $lecturerId) {
        // Verify session ownership
        $this->verifySessionOwnership($sessionId, $lecturerId);

        $stmt = $this->pdo->prepare("
            UPDATE attendance_sessions
            SET end_time = NOW(), status = 'completed'
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$sessionId]);

        if ($stmt->rowCount() > 0) {
            $this->logger->logSessionOperation('ended', $sessionId);
            return true;
        }

        return false;
    }

    /**
     * Get active session for user
     */
    public function getActiveSessionForUser($userId) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, c.name as course_name, d.name as department_name, o.name as option_name
            FROM attendance_sessions s
            INNER JOIN courses c ON s.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            INNER JOIN options o ON s.option_id = o.id
            WHERE s.lecturer_id = ? AND s.status = 'active'
            ORDER BY s.start_time DESC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get active session for course
     */
    public function getActiveSessionForCourse($courseId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM attendance_sessions
            WHERE course_id = ? AND status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$courseId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Validate session creation parameters
     */
    private function validateSessionCreation($lecturerId, $courseId, $optionId, $biometricMethod) {
        // Validate lecturer has access to the course
        $stmt = $this->pdo->prepare("
            SELECT c.id FROM courses c
            INNER JOIN lecturers l ON c.lecturer_id = l.user_id
            WHERE c.id = ? AND l.user_id = ?
        ");
        $stmt->execute([$courseId, $lecturerId]);

        if (!$stmt->fetch()) {
            throw new Exception('Access denied: You do not have permission to create sessions for this course');
        }

        // Validate biometric method
        if (!in_array($biometricMethod, ['face', 'finger'])) {
            throw new Exception('Invalid biometric method');
        }
    }

    /**
     * Verify session ownership
     */
    public function verifySessionOwnership($sessionId, $userId) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM attendance_sessions
            WHERE id = ? AND lecturer_id = ?
        ");
        $stmt->execute([$sessionId, $userId]);

        if (!$stmt->fetch()) {
            throw new Exception('Session not found or access denied');
        }
    }

    /**
     * Check if session is active
     */
    public function isSessionActive($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM attendance_sessions
            WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$sessionId]);
        return (bool)$stmt->fetch();
    }

    /**
     * Get session details
     */
    public function getSessionDetails($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, c.name as course_name, c.course_code,
                   d.name as department_name, o.name as option_name,
                   u.first_name, u.last_name
            FROM attendance_sessions s
            INNER JOIN courses c ON s.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            INNER JOIN options o ON s.option_id = o.id
            INNER JOIN users u ON s.lecturer_id = u.id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Force end session (admin only)
     */
    public function forceEndSession($sessionId, $adminId) {
        // Log the force end action
        $this->logger->logSessionOperation('force_ended', $sessionId, [
            'admin_id' => $adminId,
            'reason' => 'Administrative action'
        ]);

        $stmt = $this->pdo->prepare("
            UPDATE attendance_sessions
            SET end_time = NOW(), status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([$sessionId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get session statistics
     */
    public function getSessionStatistics($sessionId) {
        // Single optimized query for all stats
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(st.total_students, 0) as total_students,
                COALESCE(ar.present_count, 0) as present_count,
                COALESCE(ar.absent_count, 0) as absent_count,
                ROUND(
                    CASE
                        WHEN COALESCE(st.total_students, 0) > 0
                        THEN (COALESCE(ar.present_count, 0) * 100.0 / st.total_students)
                        ELSE 0
                    END, 1
                ) as attendance_rate
            FROM attendance_sessions sess
            LEFT JOIN (
                SELECT option_id, COUNT(*) as total_students
                FROM students
                WHERE status = 'active'
                GROUP BY option_id
            ) st ON sess.option_id = st.option_id
            LEFT JOIN (
                SELECT
                    session_id,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
                FROM attendance_records
                GROUP BY session_id
            ) ar ON sess.id = ar.session_id
            WHERE sess.id = ?
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}