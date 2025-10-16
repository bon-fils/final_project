<?php
class AttendanceSessionController {
    private $pdo;
    private $user_id;
    private $user_role;
    private $current_lecturer_id;

    public function __construct($pdo, $user_id, $user_role) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->user_role = $user_role;
        $this->initializeLecturerData();
    }

    private function initializeLecturerData() {
        if ($this->user_role === 'lecturer') {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT l.id as lecturer_id, l.department_id
                    FROM lecturers l
                    WHERE l.user_id = ?
                ");
                $stmt->execute([$this->user_id]);
                $lecturerData = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($lecturerData) {
                    $this->current_lecturer_id = $lecturerData['lecturer_id'];
                    $_SESSION['lecturer_id'] = $this->current_lecturer_id;
                    $_SESSION['department_id'] = $lecturerData['department_id'];
                }
            } catch (PDOException $e) {
                error_log("Lecturer initialization error: " . $e->getMessage());
            }
        }
    }

    public function handleAjaxRequest() {
        header('Content-Type: application/json');

        try {
            $action = $_POST['action'] ?? $_GET['action'] ?? '';

            switch ($action) {
                case 'process_face_recognition':
                    $this->processFaceRecognition();
                    break;
                case 'get_departments':
                    $this->getDepartments();
                    break;
                case 'get_options':
                    $this->getOptions();
                    break;
                case 'get_courses':
                    $this->getCourses();
                    break;
                case 'get_user_active_session':
                    $this->getUserActiveSession();
                    break;
                case 'get_session_status':
                    $this->getSessionStatus();
                    break;
                case 'get_session_stats':
                    $this->getSessionStats();
                    break;
                case 'get_attendance_records':
                    $this->getAttendanceRecords();
                    break;
                case 'export_attendance':
                    $this->exportAttendance();
                    break;
                case 'remove_attendance':
                    $this->removeAttendance();
                    break;
                default:
                    throw new Exception('Invalid action');
            }
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    private function processFaceRecognition() {
        $imageData = $_POST['image_data'] ?? '';
        $sessionId = $_POST['session_id'] ?? null;

        if (empty($imageData)) {
            throw new Exception('No image data provided');
        }

        if (!$sessionId) {
            throw new Exception('Session ID is required');
        }

        // Verify session is active
        $stmt = $this->pdo->prepare("SELECT id FROM attendance_sessions WHERE id = ? AND end_time IS NULL");
        $stmt->execute([$sessionId]);
        if (!$stmt->fetch()) {
            throw new Exception('Active session not found');
        }

        // Create temporary file for the captured image
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'face_capture_');
        $imageFile = $tempFile . '.jpg';

        // Clean up temp file if it exists
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        // Decode and save base64 image
        if (strpos($imageData, 'data:image') === 0) {
            $imageData = explode(',', $imageData)[1];
        }
        $imageBinary = base64_decode($imageData);

        if ($imageBinary === false) {
            throw new Exception('Invalid base64 image data');
        }

        if (file_put_contents($imageFile, $imageBinary) === false) {
            throw new Exception('Failed to save temporary image file');
        }

        // Set proper permissions
        chmod($imageFile, 0644);

        // Call Python face recognition script
        $pythonScript = __DIR__ . '/../face_match.py';
        $command = escapeshellcmd('python3') . ' ' . escapeshellarg($pythonScript) . ' ' . escapeshellarg($imageFile);

        // Execute command
        $output = shell_exec($command . " 2>&1");

        // Clean up temporary file
        if (file_exists($imageFile)) {
            unlink($imageFile);
        }

        // Parse JSON output
        $result = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid response from face recognition script');
        }

        // If match found, record attendance
        if ($result['status'] === 'success' && isset($result['student_id'])) {
            // Check if attendance already recorded
            $stmt = $this->pdo->prepare("SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?");
            $stmt->execute([$sessionId, $result['student_id']]);
            $existing = $stmt->fetch();

            if (!$existing) {
                // Record attendance
                $stmt = $this->pdo->prepare("
                    INSERT INTO attendance_records (session_id, student_id, status, method, recorded_at)
                    VALUES (?, ?, 'present', 'face_recognition', NOW())
                ");
                $stmt->execute([$sessionId, $result['student_id']]);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Attendance marked successfully!',
                'student_name' => $result['student_name'],
                'student_reg' => $result['student_reg'],
                'confidence' => $result['confidence']
            ]);
        } else {
            echo json_encode([
                'status' => 'no_match',
                'message' => $result['message'] ?? 'No face match found'
            ]);
        }
    }

    private function getDepartments() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT d.id, d.name
                FROM departments d
                INNER JOIN lecturers l ON d.id = l.department_id
                WHERE l.user_id = ?
                ORDER BY d.name
            ");
            $stmt->execute([$this->user_id]);
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $departments
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to load departments'
            ]);
        }
    }

    private function getOptions() {
        $departmentId = $_GET['department_id'] ?? null;

        if (!$departmentId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Department ID is required'
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, name
                FROM options
                WHERE department_id = ? AND status = 'active'
                ORDER BY name
            ");
            $stmt->execute([$departmentId]);
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $options
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to load options'
            ]);
        }
    }

    private function getCourses() {
        $departmentId = $_GET['department_id'] ?? null;
        $optionId = $_GET['option_id'] ?? null;

        if (!$departmentId || !$optionId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Department ID and Option ID are required'
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT
                    c.id, c.course_code, c.name, c.description, c.credits, c.semester,
                    c.is_available, u.first_name, u.last_name,
                    CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
                FROM courses c
                LEFT JOIN users u ON c.lecturer_id = u.id
                WHERE c.department_id = ? AND c.status = 'active'
                ORDER BY c.name
            ");
            $stmt->execute([$departmentId]);
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format course data
            foreach ($courses as &$course) {
                $course['lecturer_name'] = $course['lecturer_name'] ?: 'Unknown Lecturer';
                $course['is_available'] = (bool)$course['is_available'];
            }

            echo json_encode([
                'status' => 'success',
                'data' => $courses
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to load courses'
            ]);
        }
    }

    private function getUserActiveSession() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, c.course_code, c.name as course_name,
                       d.name as department_name, o.name as option_name
                FROM attendance_sessions s
                INNER JOIN courses c ON s.course_id = c.id
                INNER JOIN departments d ON c.department_id = d.id
                INNER JOIN options o ON s.option_id = o.id
                WHERE s.lecturer_id = ? AND s.end_time IS NULL
                ORDER BY s.start_time DESC
                LIMIT 1
            ");
            $stmt->execute([$this->current_lecturer_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                echo json_encode([
                    'status' => 'success',
                    'data' => $session
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'data' => null
                ]);
            }
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to get active session'
            ]);
        }
    }

    private function getSessionStatus() {
        $departmentId = $_GET['department_id'] ?? null;
        $optionId = $_GET['option_id'] ?? null;
        $courseId = $_GET['course_id'] ?? null;

        if (!$departmentId || !$optionId || !$courseId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'All parameters are required'
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT s.*, c.course_code, c.name as course_name,
                       d.name as department_name, o.name as option_name
                FROM attendance_sessions s
                INNER JOIN courses c ON s.course_id = c.id
                INNER JOIN departments d ON c.department_id = d.id
                INNER JOIN options o ON s.option_id = o.id
                WHERE s.course_id = ? AND s.end_time IS NULL
                ORDER BY s.start_time DESC
                LIMIT 1
            ");
            $stmt->execute([$courseId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $session,
                'is_active' => $session ? true : false
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to get session status'
            ]);
        }
    }

    private function getSessionStats() {
        $sessionId = $_GET['session_id'] ?? null;

        if (!$sessionId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Session ID is required'
            ]);
            return;
        }

        try {
            // Get total students in the option
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total_students
                FROM students s
                INNER JOIN attendance_sessions sess ON s.option_id = sess.option_id
                WHERE sess.id = ? AND s.status = 'active'
            ");
            $stmt->execute([$sessionId]);
            $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total_students'];

            // Get attendance counts
            $stmt = $this->pdo->prepare("
                SELECT
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count
                FROM attendance_records
                WHERE session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $counts = $stmt->fetch(PDO::FETCH_ASSOC);

            $presentCount = (int)($counts['present_count'] ?? 0);
            $absentCount = (int)($counts['absent_count'] ?? 0);
            $attendanceRate = $totalStudents > 0 ? round(($presentCount / $totalStudents) * 100, 1) : 0;

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total_students' => $totalStudents,
                    'present_count' => $presentCount,
                    'absent_count' => $absentCount,
                    'attendance_rate' => $attendanceRate
                ]
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to get session stats'
            ]);
        }
    }

    private function getAttendanceRecords() {
        $sessionId = $_GET['session_id'] ?? null;

        if (!$sessionId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Session ID is required'
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                SELECT ar.*, s.reg_no as student_id,
                       CONCAT(u.first_name, ' ', u.last_name) as student_name,
                       u.email as student_email
                FROM attendance_records ar
                INNER JOIN students s ON ar.student_id = s.id
                INNER JOIN users u ON s.user_id = u.id
                WHERE ar.session_id = ?
                ORDER BY ar.recorded_at DESC
            ");
            $stmt->execute([$sessionId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $records
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to get attendance records'
            ]);
        }
    }

    private function exportAttendance() {
        $sessionId = $_GET['session_id'] ?? null;
        $format = $_GET['format'] ?? 'csv';

        if (!$sessionId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Session ID is required'
            ]);
            return;
        }

        try {
            // Get session details
            $stmt = $this->pdo->prepare("
                SELECT s.*, c.course_code, c.name as course_name,
                       d.name as department_name, o.name as option_name
                FROM attendance_sessions s
                INNER JOIN courses c ON s.course_id = c.id
                INNER JOIN departments d ON c.department_id = d.id
                INNER JOIN options o ON s.option_id = o.id
                WHERE s.id = ?
            ");
            $stmt->execute([$sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                throw new Exception('Session not found');
            }

            // Get attendance records
            $stmt = $this->pdo->prepare("
                SELECT ar.*, s.reg_no as student_id,
                       CONCAT(u.first_name, ' ', u.last_name) as student_name,
                       u.email as student_email
                FROM attendance_records ar
                INNER JOIN students s ON ar.student_id = s.id
                INNER JOIN users u ON s.user_id = u.id
                WHERE ar.session_id = ?
                ORDER BY u.first_name, u.last_name
            ");
            $stmt->execute([$sessionId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($format === 'csv') {
                $csv = "Student ID,Name,Email,Status,Method,Recorded At\n";

                foreach ($records as $record) {
                    $csv .= sprintf(
                        "%s,%s,%s,%s,%s,%s\n",
                        $record['student_id'],
                        '"' . str_replace('"', '""', $record['student_name']) . '"',
                        $record['student_email'],
                        $record['status'],
                        $record['method'],
                        $record['recorded_at']
                    );
                }

                $filename = sprintf(
                    'attendance_%s_%s_%s.csv',
                    $session['course_code'],
                    date('Y-m-d', strtotime($session['session_date'])),
                    date('His')
                );

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'content' => base64_encode($csv),
                        'filename' => $filename
                    ]
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to export attendance: ' . $e->getMessage()
            ]);
        }
    }

    private function removeAttendance() {
        $recordId = $_POST['record_id'] ?? null;

        if (!$recordId) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Record ID is required'
            ]);
            return;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM attendance_records WHERE id = ?");
            $stmt->execute([$recordId]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Attendance record removed successfully'
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to remove attendance record'
            ]);
        }
    }

    public function startSession($formData) {
        try {
            // Validate required fields
            $departmentId = $formData['department_id'] ?? null;
            $optionId = $formData['option_id'] ?? null;
            $courseId = $formData['course_id'] ?? null;
            $biometricMethod = $formData['biometric_method'] ?? null;

            if (!$departmentId || !$optionId || !$courseId || !$biometricMethod) {
                return [
                    'status' => 'error',
                    'message' => 'All fields are required'
                ];
            }

            // Check for existing active session
            $stmt = $this->pdo->prepare("
                SELECT id FROM attendance_sessions
                WHERE course_id = ? AND end_time IS NULL
            ");
            $stmt->execute([$courseId]);
            $existingSession = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existingSession && !isset($formData['force_new'])) {
                // Return existing session info
                $stmt = $this->pdo->prepare("
                    SELECT s.*, c.course_code, c.name as course_name,
                           d.name as department_name, o.name as option_name
                    FROM attendance_sessions s
                    INNER JOIN courses c ON s.course_id = c.id
                    INNER JOIN departments d ON c.department_id = d.id
                    INNER JOIN options o ON s.option_id = o.id
                    WHERE s.id = ?
                ");
                $stmt->execute([$existingSession['id']]);
                $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'status' => 'existing_session',
                    'existing_session' => $sessionData,
                    'message' => 'An active session already exists for this course'
                ];
            }

            // End existing session if force_new is set
            if ($existingSession && isset($formData['force_new'])) {
                $stmt = $this->pdo->prepare("UPDATE attendance_sessions SET end_time = NOW() WHERE id = ?");
                $stmt->execute([$existingSession['id']]);
            }

            // Start new session
            $stmt = $this->pdo->prepare("
                INSERT INTO attendance_sessions
                (lecturer_id, course_id, option_id, session_date, start_time, biometric_method)
                VALUES (?, ?, ?, CURDATE(), NOW(), ?)
            ");
            $stmt->execute([
                $this->current_lecturer_id,
                $courseId,
                $optionId,
                $biometricMethod
            ]);

            $sessionId = $this->pdo->lastInsertId();

            // Get session data
            $stmt = $this->pdo->prepare("
                SELECT s.*, c.course_code, c.name as course_name,
                       d.name as department_name, o.name as option_name
                FROM attendance_sessions s
                INNER JOIN courses c ON s.course_id = c.id
                INNER JOIN departments d ON c.department_id = d.id
                INNER JOIN options o ON s.option_id = o.id
                WHERE s.id = ?
            ");
            $stmt->execute([$sessionId]);
            $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'session_id' => $sessionId,
                'data' => $sessionData,
                'message' => 'Session started successfully'
            ];

        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public function endSession($sessionId) {
        try {
            if (!$sessionId) {
                return [
                    'status' => 'error',
                    'message' => 'Session ID is required'
                ];
            }

            $stmt = $this->pdo->prepare("
                UPDATE attendance_sessions
                SET end_time = NOW()
                WHERE id = ? AND end_time IS NULL
            ");
            $stmt->execute([$sessionId]);

            if ($stmt->rowCount() > 0) {
                return [
                    'status' => 'success',
                    'message' => 'Session ended successfully'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Session not found or already ended'
                ];
            }

        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}
?>