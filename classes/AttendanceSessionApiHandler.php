<?php
/**
 * Attendance Session API Handler
 * Handles all AJAX requests for attendance session functionality
 */

class AttendanceSessionApiHandler {
    private $pdo;
    private $user_id;
    private $user_role;
    private $logger;
    private $sessionManager;

    public function __construct($pdo, $user_id, $user_role) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->user_role = $user_role;
        $this->logger = new AttendanceSessionLogger();
        $this->sessionManager = new AttendanceSessionManager($pdo);
    }

    /**
     * Handle incoming API request
     */
    public function handleRequest($action) {
        try {
            // Validate CSRF token for POST requests
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->validateCsrfToken();
            }

            // Route to appropriate handler
            switch ($action) {
                case 'get_departments':
                    return $this->handleGetDepartments();
                case 'get_options':
                    return $this->handleGetOptions();
                case 'get_courses':
                    return $this->handleGetCourses();
                case 'start_session':
                    return $this->handleStartSession();
                case 'end_session':
                    return $this->handleEndSession();
                case 'get_session_status':
                    return $this->handleGetSessionStatus();
                case 'get_user_active_session':
                    return $this->handleGetUserActiveSession();
                case 'get_session_stats':
                    return $this->handleGetSessionStats();
                case 'get_attendance_records':
                    return $this->handleGetAttendanceRecords();
                case 'process_face_recognition':
                    return $this->handleProcessFaceRecognition();
                case 'export_attendance':
                    return $this->handleExportAttendance();
                case 'remove_attendance':
                    return $this->handleRemoveAttendance();
                default:
                    throw new Exception('Invalid action specified');
            }
        } catch (Exception $e) {
            $this->logger->logError("API Error: " . $e->getMessage());
            return $this->createErrorResponse($e->getMessage());
        }
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrfToken() {
        $token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($token)) {
            throw new Exception('CSRF token validation failed');
        }
    }

    /**
     * Get departments for current user
     */
    private function handleGetDepartments() {
        $this->logger->logInfo("Getting departments for user: {$this->user_id}");

        try {
            if ($this->user_role === 'admin') {
                $stmt = $this->pdo->prepare("SELECT id, name FROM departments ORDER BY name");
                $stmt->execute();
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Get user's department with optimized query
                $departmentId = $this->getUserDepartmentId();
                if (!$departmentId) {
                    return $this->createSuccessResponse([], 'No department assigned');
                }

                $stmt = $this->pdo->prepare("SELECT id, name FROM departments WHERE id = ? LIMIT 1");
                $stmt->execute([$departmentId]);
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $this->createSuccessResponse($departments, 'Departments retrieved successfully');
        } catch (PDOException $e) {
            $this->logger->logError("Database error in handleGetDepartments: " . $e->getMessage());
            throw new Exception('Failed to retrieve departments');
        }
    }

    /**
     * Get options for a department
     */
    private function handleGetOptions() {
        $departmentId = $this->validateIntParam('department_id');

        // Verify user has access to this department
        if ($this->user_role !== 'admin') {
            $userDepartmentId = $this->getUserDepartmentId();
            if ($departmentId != $userDepartmentId) {
                throw new Exception('Access denied to this department');
            }
        }

        $stmt = $this->pdo->prepare("
            SELECT id, name FROM options
            WHERE department_id = ? AND status = 'active'
            ORDER BY name
        ");
        $stmt->execute([$departmentId]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->createSuccessResponse($options, 'Options retrieved successfully');
    }

    /**
     * Get courses for department and option
     */
    private function handleGetCourses() {
        $departmentId = InputValidator::validateDepartmentId($_GET['department_id'] ?? null);

        // Verify user has access to this department
        if ($this->user_role !== 'admin') {
            $userDepartmentId = $this->getUserDepartmentId();
            if ($departmentId != $userDepartmentId) {
                throw new Exception('Access denied to this department');
            }
        }

        try {
            $query = "
                SELECT
                    c.id, c.name, c.course_code, c.description, c.credits, c.semester,
                    c.is_available, COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Unassigned') as lecturer_name
                FROM courses c
                USE INDEX (idx_department_status)
                LEFT JOIN users u ON c.lecturer_id = u.id
                WHERE c.department_id = ? AND c.status = 'active'
            ";

            $params = [$departmentId];

            // For lecturers, only show their courses
            if ($this->user_role === 'lecturer') {
                $query .= " AND c.lecturer_id = ?";
                $params[] = $this->user_id;
            }

            $query .= " ORDER BY c.name LIMIT 500";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->createSuccessResponse($courses, 'Courses retrieved successfully');
        } catch (PDOException $e) {
            $this->logger->logError("Database error in handleGetCourses: " . $e->getMessage());
            throw new Exception('Failed to retrieve courses');
        }
    }

    /**
     * Start a new attendance session
     */
    private function handleStartSession() {
        $params = $this->validateRequestParams([
            'department_id' => ['type' => 'int', 'required' => true, 'min' => 1],
            'option_id' => ['type' => 'int', 'required' => true, 'min' => 1],
            'course_id' => ['type' => 'int', 'required' => true, 'min' => 1],
            'biometric_method' => ['type' => 'biometric_method', 'required' => true],
            'force_new' => ['type' => 'bool', 'required' => false, 'default' => false]
        ], 'POST');

        $departmentId = $params['department_id'];
        $optionId = $params['option_id'];
        $courseId = $params['course_id'];
        $biometricMethod = $params['biometric_method'];
        $forceNew = $params['force_new'];

        // Verify user has access to this department
        if ($this->user_role !== 'admin') {
            $userDepartmentId = $this->getUserDepartmentId();
            if ($departmentId != $userDepartmentId) {
                throw new Exception('Access denied to this department');
            }
        }

        // Check for existing active session
        $stmt = $this->pdo->prepare("
            SELECT id FROM attendance_sessions
            WHERE course_id = ? AND end_time IS NULL
        ");
        $stmt->execute([$courseId]);
        $existingSession = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingSession && !$forceNew) {
            // Return existing session info
            $sessionData = $this->getSessionData($existingSession['id']);
            return $this->createWarningResponse(
                'An active session already exists for this course',
                ['existing_session' => $sessionData],
                ['status' => 'existing_session']
            );
        }

        // End existing session if force_new
        if ($existingSession && $forceNew) {
            $stmt = $this->pdo->prepare("UPDATE attendance_sessions SET end_time = NOW() WHERE id = ?");
            $stmt->execute([$existingSession['id']]);
            $this->logger->logSessionOperation('ended_existing', $existingSession['id'], ['reason' => 'force_new']);
        }

        // Create session using session manager
        $sessionId = $this->sessionManager->createSession($this->user_id, $courseId, $optionId, $biometricMethod);

        $sessionData = $this->sessionManager->getSessionDetails($sessionId);
        return $this->createSuccessResponse($sessionData, 'Session started successfully', ['session_id' => $sessionId]);
    }

    /**
     * End an active session
     */
    private function handleEndSession() {
        $params = $this->validateRequestParams([
            'session_id' => ['type' => 'int', 'required' => true, 'min' => 1]
        ], 'POST');

        $sessionId = $params['session_id'];

        if ($this->sessionManager->endSession($sessionId, $this->user_id)) {
            return $this->createSuccessResponse(['session_id' => $sessionId], 'Session ended successfully');
        } else {
            throw new Exception('Session not found or already ended');
        }
    }

    /**
     * Get session status
     */
    private function handleGetSessionStatus() {
        $courseId = $this->validateIntParam('course_id');

        $stmt = $this->pdo->prepare("
            SELECT s.*, c.name as course_name, d.name as department_name, o.name as option_name,
                   COUNT(ar.id) as attendance_count,
                   COUNT(CASE WHEN ar.status = 'present' THEN 1 END) as present_count
            FROM attendance_sessions s
            INNER JOIN courses c ON s.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            INNER JOIN options o ON s.option_id = o.id
            LEFT JOIN attendance_records ar ON s.id = ar.session_id
            WHERE s.course_id = ?
            ORDER BY s.start_time DESC
            LIMIT 1
        ");
        $stmt->execute([$courseId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return $this->createSuccessResponse(null, 'No active session found', ['is_active' => false]);
        }

        return $this->createSuccessResponse($session, 'Session status retrieved', ['is_active' => $session['end_time'] === null]);
    }

    /**
     * Get user's active session
     */
    private function handleGetUserActiveSession() {
        if ($this->user_role === 'admin') {
            return $this->createSuccessResponse(null, 'No active session found');
        }

        $session = $this->sessionManager->getActiveSessionForUser($this->user_id);
        return $this->createSuccessResponse($session, $session ? 'Active session found' : 'No active session found');
    }

    /**
     * Get session statistics
     */
    private function handleGetSessionStats() {
        $params = $this->validateRequestParams([
            'session_id' => ['type' => 'int', 'required' => true, 'min' => 1]
        ], 'GET');

        $sessionId = $params['session_id'];

        // Verify session ownership
        $this->sessionManager->verifySessionOwnership($sessionId, $this->user_id);

        $stats = $this->sessionManager->getSessionStatistics($sessionId);

        if (!$stats) {
            throw new Exception('Session not found');
        }

        return $this->createSuccessResponse([
            'total_students' => (int)$stats['total_students'],
            'present_count' => (int)$stats['present_count'],
            'absent_count' => (int)$stats['absent_count'],
            'attendance_rate' => (float)$stats['attendance_rate']
        ], 'Session statistics retrieved');
    }

    /**
     * Get attendance records for a session
     */
    private function handleGetAttendanceRecords() {
        $sessionId = InputValidator::validateSessionId($_GET['session_id'] ?? null);

        // Verify session ownership
        $this->verifySessionOwnership($sessionId);

        // Optimized query with better performance
        $stmt = $this->pdo->prepare("
            SELECT
                ar.id,
                ar.status,
                ar.method,
                ar.recorded_at,
                s.reg_no as student_id,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.email as student_email,
                s.year_level
            FROM attendance_records ar
            USE INDEX (idx_session_student)
            INNER JOIN students s ON ar.student_id = s.id
            INNER JOIN users u ON s.user_id = u.id
            WHERE ar.session_id = ?
            ORDER BY ar.recorded_at DESC
            LIMIT 1000
        ");
        $stmt->execute([$sessionId]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->createSuccessResponse($records, 'Attendance records retrieved');
    }

    /**
     * Process face recognition
     */
    private function handleProcessFaceRecognition() {
        $params = $this->validateRequestParams([
            'image_data' => ['type' => 'base64_image', 'required' => true],
            'session_id' => ['type' => 'int', 'required' => true, 'min' => 1]
        ], 'POST');

        $imageData = $params['image_data'];
        $sessionId = $params['session_id'];

        // Verify session is active and user owns it
        $this->verifySessionOwnership($sessionId);

        $faceRecognition = new FaceRecognitionService();
        $result = $faceRecognition->processImage($imageData, $sessionId);

        if ($result['status'] === 'success' && isset($result['student_id'])) {
            // Record attendance
            $this->recordAttendance($sessionId, $result['student_id'], 'face_recognition', 'present');
            $this->logger->logAttendanceOperation('recorded', $sessionId, $result['student_id'], [
                'method' => 'face_recognition',
                'confidence' => $result['confidence'] ?? 0
            ]);
        }

        return $result;
    }

    /**
     * Export attendance data
     */
    private function handleExportAttendance() {
        $sessionId = $this->validateIntParam('session_id');
        $format = $_GET['format'] ?? 'csv';

        // Verify session ownership
        $this->verifySessionOwnership($sessionId);

        $exporter = new AttendanceExporter($this->pdo);
        return $exporter->exportSession($sessionId, $format);
    }

    /**
     * Remove attendance record
     */
    private function handleRemoveAttendance() {
        $recordId = InputValidator::validateInt($_POST['record_id'] ?? null, 1);

        // Verify record belongs to user's session
        $stmt = $this->pdo->prepare("
            SELECT ar.id, ar.student_id, ar.session_id FROM attendance_records ar
            INNER JOIN attendance_sessions s ON ar.session_id = s.id
            WHERE ar.id = ? AND s.lecturer_id = ?
        ");
        $stmt->execute([$recordId, $this->user_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            throw new Exception('Attendance record not found or access denied');
        }

        $stmt = $this->pdo->prepare("DELETE FROM attendance_records WHERE id = ?");
        $stmt->execute([$recordId]);

        $this->logger->logAttendanceOperation('removed', $record['session_id'], $record['student_id'], [
            'record_id' => $recordId
        ]);

        return $this->createSuccessResponse([], 'Attendance record removed successfully');
    }

    /**
     * Get user's department ID
     */
    private function getUserDepartmentId() {
        if ($this->user_role === 'hod') {
            $stmt = $this->pdo->prepare("SELECT id FROM departments WHERE hod_id = ?");
            $stmt->execute([$this->user_id]);
            $dept = $stmt->fetch(PDO::FETCH_ASSOC);
            return $dept ? $dept['id'] : null;
        } elseif ($this->user_role === 'lecturer') {
            $stmt = $this->pdo->prepare("
                SELECT l.department_id FROM lecturers l WHERE l.user_id = ?
            ");
            $stmt->execute([$this->user_id]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
            return $lecturer ? $lecturer['department_id'] : null;
        }
        return null;
    }

    /**
     * Verify session ownership
     */
    private function verifySessionOwnership($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM attendance_sessions
            WHERE id = ? AND lecturer_id = ?
        ");
        $stmt->execute([$sessionId, $this->user_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Session not found or access denied');
        }
    }

    /**
     * Get complete session data
     */
    private function getSessionData($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, c.name as course_name, d.name as department_name, o.name as option_name
            FROM attendance_sessions s
            INNER JOIN courses c ON s.course_id = c.id
            INNER JOIN departments d ON c.department_id = d.id
            INNER JOIN options o ON s.option_id = o.id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Record attendance
     */
    private function recordAttendance($sessionId, $studentId, $method, $status) {
        // Check if attendance already recorded
        $stmt = $this->pdo->prepare("SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?");
        $stmt->execute([$sessionId, $studentId]);
        $existing = $stmt->fetch();

        if (!$existing) {
            $stmt = $this->pdo->prepare("
                INSERT INTO attendance_records (session_id, student_id, status, method, recorded_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$sessionId, $studentId, $status, $method]);
        }
    }

    /**
     * Validate and sanitize request parameters
     */
    private function validateRequestParams($params, $method = 'GET') {
        $source = $method === 'POST' ? $_POST : $_GET;
        $validated = [];

        foreach ($params as $param => $rules) {
            $value = $source[$param] ?? null;

            // Check if parameter is required
            if (isset($rules['required']) && $rules['required'] && $value === null) {
                throw new InvalidArgumentException("Parameter '{$param}' is required");
            }

            // Skip validation if value is null and not required
            if ($value === null) {
                continue;
            }

            // Apply validation based on type
            switch ($rules['type']) {
                case 'int':
                    $validated[$param] = InputValidator::validateInt(
                        $value,
                        $rules['min'] ?? null,
                        $rules['max'] ?? null,
                        $rules['default'] ?? null
                    );
                    break;

                case 'string':
                    $validated[$param] = InputValidator::validateString(
                        $value,
                        $rules['max_length'] ?? null,
                        $rules['pattern'] ?? null,
                        $rules['default'] ?? null
                    );
                    break;

                case 'email':
                    $validated[$param] = InputValidator::validateEmail($value, $rules['default'] ?? null);
                    break;

                case 'bool':
                    $validated[$param] = InputValidator::validateBool($value, $rules['default'] ?? null);
                    break;

                case 'date':
                    $validated[$param] = InputValidator::validateDate($value, $rules['format'] ?? 'Y-m-d', $rules['default'] ?? null);
                    break;

                case 'biometric_method':
                    $validated[$param] = InputValidator::validateBiometricMethod($value);
                    break;

                case 'attendance_status':
                    $validated[$param] = InputValidator::validateAttendanceStatus($value);
                    break;

                case 'user_role':
                    $validated[$param] = InputValidator::validateUserRole($value);
                    break;

                case 'base64_image':
                    $validated[$param] = InputValidator::validateBase64Image($value, $rules['max_size'] ?? 5242880);
                    break;

                default:
                    $validated[$param] = InputValidator::sanitizeForDatabase($value);
            }
        }

        return $validated;
    }

    /**
     * Legacy validation methods for backward compatibility
     */
    private function validateIntParam($param, $method = 'GET') {
        $params = $this->validateRequestParams([$param => ['type' => 'int', 'required' => true]], $method);
        return $params[$param];
    }

    private function validateStringParam($param, $method = 'GET') {
        $params = $this->validateRequestParams([$param => ['type' => 'string', 'required' => true]], $method);
        return $params[$param];
    }

    /**
     * Create standardized success response
     */
    private function createSuccessResponse($data, $message = '', $extra = []) {
        return array_merge([
            'status' => 'success',
            'message' => $message ?: 'Operation completed successfully',
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $this->generateRequestId()
        ], $extra);
    }

    /**
     * Create standardized error response
     */
    private function createErrorResponse($message, $errorCode = null, $details = null) {
        $response = [
            'status' => 'error',
            'message' => $message,
            'data' => null,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $this->generateRequestId()
        ];

        if ($errorCode) {
            $response['error_code'] = $errorCode;
        }

        if ($details && getenv('APP_DEBUG') === 'true') {
            $response['details'] = $details;
        }

        return $response;
    }

    /**
     * Create standardized warning response
     */
    private function createWarningResponse($message, $data = null, $extra = []) {
        return array_merge([
            'status' => 'warning',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $this->generateRequestId()
        ], $extra);
    }

    /**
     * Generate unique request ID for tracking
     */
    private function generateRequestId() {
        return sprintf(
            '%s-%s-%s',
            date('YmdHis'),
            substr(md5(uniqid(mt_rand(), true)), 0, 8),
            $this->user_id
        );
    }
}