<?php
/**
 * Attendance Session API
 * Backend API for managing attendance sessions with database integration
 * Version: 1.0
 */

require_once "../config.php";
require_once "../session_check.php";
require_once "../backend/classes/Logger.php";
require_once "../backend/classes/InputValidator.php";

session_start();

// Initialize logger
$logger = new Logger('logs/attendance_session_api.log', Logger::INFO);

// Verify user access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

require_role(['admin', 'lecturer', 'hod']);

// Set JSON headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Handle API actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $logger->info('Attendance Session API', 'Request received', [
        'action' => $action,
        'user_id' => $_SESSION['user_id'],
        'method' => $_SERVER['REQUEST_METHOD']
    ]);

    switch ($action) {
        case 'start_session':
            handleStartSession();
            break;

        case 'end_session':
            handleEndSession();
            break;

        case 'get_session_status':
            handleGetSessionStatus();
            break;

        case 'record_attendance':
            handleRecordAttendance();
            break;

        case 'get_session_stats':
            handleGetSessionStats();
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }

} catch (Exception $e) {
    $logger->error('Attendance Session API', 'General error', [
        'error' => $e->getMessage(),
        'action' => $action,
        'user_id' => $_SESSION['user_id']
    ]);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An unexpected error occurred',
        'error_code' => 'GENERAL_ERROR'
    ]);
}

function handleStartSession() {
    global $pdo, $logger;

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $validator = new InputValidator($input);
    $validator->required(['department_id', 'option_id', 'course_id', 'biometric_method'])
              ->custom('department_id', function($v) { return is_numeric($v) && $v > 0; })
              ->custom('option_id', function($v) { return is_numeric($v) && $v > 0; })
              ->custom('course_id', function($v) { return is_numeric($v) && $v > 0; })
              ->custom('biometric_method', function($v) { return in_array($v, ['face', 'finger']); });

    if ($validator->fails()) {
        echo json_encode(['status' => 'error', 'message' => 'Validation failed']);
        return;
    }

    try {
        // Verify lecturer has access to department
        $stmt = $pdo->prepare("
            SELECT l.id as lecturer_id, d.name as department_name
            FROM lecturers l
            JOIN departments d ON l.department_id = d.id
            WHERE l.user_id = ? AND l.department_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $input['department_id']]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturer) {
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized: No access to this department']);
            return;
        }

        // Check for existing active session
        $stmt = $pdo->prepare("
            SELECT id FROM attendance_sessions
            WHERE lecturer_id = ? AND status = 'active'
        ");
        $stmt->execute([$lecturer['lecturer_id']]);
        if ($stmt->fetch()) {
            echo json_encode(['status' => 'error', 'message' => 'You already have an active session']);
            return;
        }

        // Create new session
        $stmt = $pdo->prepare("
            INSERT INTO attendance_sessions
            (lecturer_id, department_id, option_id, course_id, year_level, biometric_method, status, start_time)
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([
            $lecturer['lecturer_id'],
            $input['department_id'],
            $input['option_id'],
            $input['course_id'],
            $input['year_level'] ?? null,
            $input['biometric_method']
        ]);

        $sessionId = $pdo->lastInsertId();

        // Get session details
        $stmt = $pdo->prepare("
            SELECT s.*, c.name as course_name, o.name as option_name, d.name as department_name
            FROM attendance_sessions s
            JOIN courses c ON s.course_id = c.id
            JOIN options o ON s.option_id = o.id
            JOIN departments d ON s.department_id = d.id
            WHERE s.id = ?
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        $logger->info('Session Started', 'New attendance session created', [
            'session_id' => $sessionId,
            'lecturer_id' => $lecturer['lecturer_id'],
            'department' => $lecturer['department_name']
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Session started successfully',
            'session' => [
                'id' => $session['id'],
                'course_name' => $session['course_name'],
                'department_name' => $session['department_name'],
                'option_name' => $session['option_name'],
                'biometric_method' => $session['biometric_method'],
                'start_time' => $session['start_time']
            ]
        ]);

    } catch (PDOException $e) {
        $logger->error('Start Session Failed', $e->getMessage(), ['user_id' => $_SESSION['user_id']]);
        echo json_encode(['status' => 'error', 'message' => 'Failed to start session']);
    }
}

function handleEndSession() {
    global $pdo, $logger;

    $input = json_decode(file_get_contents('php://input'), true);
    $sessionId = $input['session_id'] ?? null;

    if (!$sessionId) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID required']);
        return;
    }

    try {
        // Verify session ownership
        $stmt = $pdo->prepare("
            SELECT s.*, l.user_id
            FROM attendance_sessions s
            JOIN lecturers l ON s.lecturer_id = l.id
            WHERE s.id = ? AND s.status = 'active'
        ");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session || $session['user_id'] != $_SESSION['user_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Session not found or access denied']);
            return;
        }

        // End session
        $stmt = $pdo->prepare("
            UPDATE attendance_sessions
            SET status = 'completed', end_time = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$sessionId]);

        $logger->info('Session Ended', 'Attendance session completed', [
            'session_id' => $sessionId,
            'user_id' => $_SESSION['user_id']
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Session ended successfully'
        ]);

    } catch (PDOException $e) {
        $logger->error('End Session Failed', $e->getMessage(), ['session_id' => $sessionId]);
        echo json_encode(['status' => 'error', 'message' => 'Failed to end session']);
    }
}

function handleRecordAttendance() {
    global $pdo, $logger;

    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $validator = new InputValidator($input);
    $validator->required(['session_id', 'student_id', 'method'])
              ->custom('method', function($v) { return in_array($v, ['face', 'finger']); });

    if ($validator->fails()) {
        echo json_encode(['status' => 'error', 'message' => 'Validation failed']);
        return;
    }

    try {
        // Verify session is active and owned by user
        $stmt = $pdo->prepare("
            SELECT s.id, l.user_id
            FROM attendance_sessions s
            JOIN lecturers l ON s.lecturer_id = l.id
            WHERE s.id = ? AND s.status = 'active'
        ");
        $stmt->execute([$input['session_id']]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session || $session['user_id'] != $_SESSION['user_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid session']);
            return;
        }

        // Verify student exists
        $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
        $stmt->execute([$input['student_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            echo json_encode(['status' => 'error', 'message' => 'Student not found']);
            return;
        }

        // Record attendance
        $stmt = $pdo->prepare("
            INSERT INTO attendance_records
            (session_id, student_id, method, status, recorded_at)
            VALUES (?, ?, ?, 'present', NOW())
        ");
        $stmt->execute([
            $input['session_id'],
            $student['id'],
            $input['method']
        ]);

        $logger->info('Attendance Recorded', 'Student attendance marked', [
            'session_id' => $input['session_id'],
            'student_id' => $input['student_id'],
            'method' => $input['method']
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Attendance recorded successfully'
        ]);

    } catch (PDOException $e) {
        $logger->error('Record Attendance Failed', $e->getMessage(), [
            'session_id' => $input['session_id'],
            'student_id' => $input['student_id']
        ]);
        echo json_encode(['status' => 'error', 'message' => 'Failed to record attendance']);
    }
}

function handleGetSessionStats() {
    global $pdo, $logger;

    $sessionId = $_GET['session_id'] ?? null;

    if (!$sessionId) {
        echo json_encode(['status' => 'error', 'message' => 'Session ID required']);
        return;
    }

    try {
        // Get attendance statistics
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as total_records,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count
            FROM attendance_records
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'total_records' => (int)$stats['total_records'],
                'present_count' => (int)$stats['present_count'],
                'absent_count' => 0 // Could be calculated differently
            ]
        ]);

    } catch (PDOException $e) {
        $logger->error('Get Session Stats Failed', $e->getMessage(), ['session_id' => $sessionId]);
        echo json_encode(['status' => 'error', 'message' => 'Failed to get session statistics']);
    }
}

function handleGetSessionStatus() {
    global $pdo, $logger;

    try {
        // Get active session for current user
        $stmt = $pdo->prepare("
            SELECT s.*, c.name as course_name, d.name as department_name
            FROM attendance_sessions s
            JOIN lecturers l ON s.lecturer_id = l.id
            JOIN courses c ON s.course_id = c.id
            JOIN departments d ON s.department_id = d.id
            WHERE l.user_id = ? AND s.status = 'active'
            ORDER BY s.start_time DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            echo json_encode([
                'status' => 'success',
                'active_session' => true,
                'session' => $session
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'active_session' => false
            ]);
        }

    } catch (PDOException $e) {
        $logger->error('Get Session Status Failed', $e->getMessage(), ['user_id' => $_SESSION['user_id']]);
        echo json_encode(['status' => 'error', 'message' => 'Failed to get session status']);
    }
}
?>