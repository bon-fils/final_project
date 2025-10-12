<?php
/**
 * RP Attendance System - Simplified Attendance Session
 * Handles biometric attendance sessions with face recognition and fingerprint authentication
 *
 * @author Kilo Code
 * @version 2.0.0
 * @date 2025-10-12
 */

session_start();

// Security: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Configuration
define('ESP_IP', '192.168.1.118');
define('SESSION_TABLE', 'attendance_sessions');
define('ATTENDANCE_TABLE', 'attendance_records');
define('STUDENTS_TABLE', 'students');
define('FACE_SCRIPT', __DIR__ . '/face_match.py');

// Database connection
require_once 'config.php';

// CSRF Token Generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper: Send JSON response and exit
function jsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Helper: Validate CSRF token
function validateCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper: Sanitize input
function sanitizeInput($input, $type = 'string') {
    switch ($type) {
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        case 'email':
            return filter_var($input, FILTER_SANITIZE_EMAIL);
        default:
            return filter_var($input, FILTER_SANITIZE_STRING);
    }
}

// Helper: Clean up temporary files
function cleanupTempFiles($pattern = 'temp_live_*') {
    $files = glob($pattern);
    foreach ($files as $file) {
        if (file_exists($file) && filemtime($file) < time() - 3600) { // Older than 1 hour
            @unlink($file);
        }
    }
}

// Register cleanup on shutdown
register_shutdown_function('cleanupTempFiles');

// AJAX Actions Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = sanitizeInput($_POST['action']);

    // Validate CSRF for all POST actions
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        jsonResponse(['status' => 'error', 'message' => 'CSRF token validation failed'], 403);
    }

    try {
        switch ($action) {
            case 'start_session':
                handleStartSession();
                break;

            case 'end_session':
                handleEndSession();
                break;

            case 'mark_by_fingerprint':
                handleFingerprintAttendance();
                break;

            case 'mark_by_face':
                handleFaceAttendance();
                break;

            case 'session_status':
                handleSessionStatus();
                break;

            default:
                jsonResponse(['status' => 'error', 'message' => 'Unknown action'], 400);
        }
    } catch (Exception $e) {
        error_log("Attendance Session Error: " . $e->getMessage());
        jsonResponse(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()], 500);
    }
}

// Function: Start Session
function handleStartSession() {
    global $pdo;

    $lecturer_id = sanitizeInput($_POST['lecturer_id'] ?? '', 'int');
    $course_id = sanitizeInput($_POST['course_id'] ?? '', 'int');
    $option_id = sanitizeInput($_POST['option_id'] ?? '', 'int');
    $biometric_method = sanitizeInput($_POST['biometric_method'] ?? 'fingerprint');

    // Validate required fields
    if (!$lecturer_id || !$course_id) {
        jsonResponse(['status' => 'error', 'message' => 'Lecturer ID and Course ID are required'], 400);
    }

    // Validate biometric method
    $valid_methods = ['face_recognition', 'fingerprint'];
    if (!in_array($biometric_method, $valid_methods)) {
        $biometric_method = 'fingerprint';
    }

    // Check if lecturer has permission for this course (same department)
    $stmt = $pdo->prepare("
        SELECT c.id FROM courses c
        INNER JOIN lecturers l ON l.user_id = ?
        WHERE c.id = ? AND c.department_id = l.department_id
    ");
    $stmt->execute([$lecturer_id, $course_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['status' => 'error', 'message' => 'You do not have permission to start sessions for this course'], 403);
    }

    // Check for existing active session
    $stmt = $pdo->prepare("
        SELECT id FROM " . SESSION_TABLE . "
        WHERE course_id = ? AND end_time IS NULL
    ");
    $stmt->execute([$course_id]);
    if ($stmt->fetch()) {
        jsonResponse(['status' => 'error', 'message' => 'An active session already exists for this course'], 409);
    }

    // Start new session
    $stmt = $pdo->prepare("
        INSERT INTO " . SESSION_TABLE . " (
            lecturer_id, course_id, option_id, session_date,
            start_time, biometric_method
        ) VALUES (?, ?, ?, CURDATE(), NOW(), ?)
    ");

    if ($stmt->execute([$lecturer_id, $course_id, $option_id, $biometric_method])) {
        $session_id = $pdo->lastInsertId();
        jsonResponse([
            'status' => 'success',
            'session_id' => $session_id,
            'message' => 'Session started successfully'
        ]);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'Failed to start session'], 500);
    }
}

// Function: End Session
function handleEndSession() {
    global $pdo;

    $session_id = sanitizeInput($_POST['session_id'] ?? '', 'int');

    if (!$session_id) {
        jsonResponse(['status' => 'error', 'message' => 'Session ID is required'], 400);
    }

    // Verify session ownership
    $stmt = $pdo->prepare("
        SELECT id FROM " . SESSION_TABLE . "
        WHERE id = ? AND lecturer_id = ? AND end_time IS NULL
    ");
    $stmt->execute([$session_id, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        jsonResponse(['status' => 'error', 'message' => 'Session not found or access denied'], 404);
    }

    $stmt = $pdo->prepare("
        UPDATE " . SESSION_TABLE . "
        SET end_time = NOW() WHERE id = ?
    ");

    if ($stmt->execute([$session_id])) {
        jsonResponse(['status' => 'success', 'message' => 'Session ended successfully']);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'Failed to end session'], 500);
    }
}

// Function: Handle Fingerprint Attendance
function handleFingerprintAttendance() {
    global $pdo;

    $session_id = sanitizeInput($_POST['session_id'] ?? '', 'int');
    $fingerprint_id = sanitizeInput($_POST['fingerprint_id'] ?? '', 'int');

    if (!$session_id || !$fingerprint_id) {
        jsonResponse(['status' => 'error', 'message' => 'Session ID and Fingerprint ID are required'], 400);
    }

    // Verify session is active
    $stmt = $pdo->prepare("
        SELECT id FROM " . SESSION_TABLE . "
        WHERE id = ? AND end_time IS NULL
    ");
    $stmt->execute([$session_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['status' => 'error', 'message' => 'Session not found or ended'], 404);
    }

    // Find student by fingerprint
    $stmt = $pdo->prepare("
        SELECT student_id, CONCAT(first_name, ' ', last_name) as name
        FROM " . STUDENTS_TABLE . "
        WHERE fingerprint_id = ?
    ");
    $stmt->execute([$fingerprint_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        jsonResponse(['status' => 'error', 'message' => 'Fingerprint not registered to any student'], 404);
    }

    // Check for duplicate attendance
    $stmt = $pdo->prepare("
        SELECT id FROM " . ATTENDANCE_TABLE . "
        WHERE student_id = ? AND session_id = ?
    ");
    $stmt->execute([$student['student_id'], $session_id]);
    if ($stmt->fetch()) {
        jsonResponse([
            'status' => 'already',
            'message' => 'Attendance already marked',
            'student' => $student
        ]);
    }

    // Record attendance
    $stmt = $pdo->prepare("
        INSERT INTO " . ATTENDANCE_TABLE . " (
            student_id, session_id, status, method, recorded_at
        ) VALUES (?, ?, 'present', 'fingerprint', NOW())
    ");

    if ($stmt->execute([$student['student_id'], $session_id])) {
        jsonResponse([
            'status' => 'success',
            'message' => "Attendance marked for {$student['name']}",
            'student' => $student
        ]);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'Failed to record attendance'], 500);
    }
}

// Function: Handle Face Attendance
function handleFaceAttendance() {
    global $pdo;

    $session_id = sanitizeInput($_POST['session_id'] ?? '', 'int');

    if (!$session_id) {
        jsonResponse(['status' => 'error', 'message' => 'Session ID is required'], 400);
    }

    // Verify session is active
    $stmt = $pdo->prepare("
        SELECT id FROM " . SESSION_TABLE . "
        WHERE id = ? AND end_time IS NULL
    ");
    $stmt->execute([$session_id]);
    if (!$stmt->fetch()) {
        jsonResponse(['status' => 'error', 'message' => 'Session not found or ended'], 404);
    }

    // Validate file upload
    if (!isset($_FILES['live_photo']) || $_FILES['live_photo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['status' => 'error', 'message' => 'Photo upload failed'], 400);
    }

    $file = $_FILES['live_photo'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];

    if (!in_array($file['type'], $allowed_types)) {
        jsonResponse(['status' => 'error', 'message' => 'Invalid file type. Only JPEG and PNG allowed'], 400);
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        jsonResponse(['status' => 'error', 'message' => 'File too large. Maximum 5MB allowed'], 400);
    }

    // Generate secure temporary filename
    $temp_file = tempnam(sys_get_temp_dir(), 'face_capture_') . '.jpg';

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
        jsonResponse(['status' => 'error', 'message' => 'Failed to save uploaded photo'], 500);
    }

    try {
        // Call Python face recognition script
        $command = escapeshellcmd('python3') . ' ' . escapeshellarg(FACE_SCRIPT) . ' ' . escapeshellarg($temp_file);
        $output = shell_exec($command . " 2>&1");

        if ($output === null) {
            throw new Exception('Face recognition script execution failed');
        }

        $result = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid response from face recognition script');
        }

        if (($result['status'] ?? '') !== 'success') {
            jsonResponse([
                'status' => 'error',
                'message' => $result['message'] ?? 'Face not recognized'
            ]);
        }

        $student_id = $result['student_id'] ?? null;
        if (!$student_id) {
            jsonResponse(['status' => 'error', 'message' => 'No student ID returned from recognition'], 500);
        }

        // Get student details
        $stmt = $pdo->prepare("
            SELECT student_id, CONCAT(first_name, ' ', last_name) as name
            FROM " . STUDENTS_TABLE . "
            WHERE student_id = ?
        ");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            jsonResponse(['status' => 'error', 'message' => 'Recognized student not found in database'], 404);
        }

        // Check for duplicate attendance
        $stmt = $pdo->prepare("
            SELECT id FROM " . ATTENDANCE_TABLE . "
            WHERE student_id = ? AND session_id = ?
        ");
        $stmt->execute([$student_id, $session_id]);
        if ($stmt->fetch()) {
            jsonResponse([
                'status' => 'already',
                'message' => 'Attendance already marked',
                'student' => $student
            ]);
        }

        // Record attendance
        $stmt = $pdo->prepare("
            INSERT INTO " . ATTENDANCE_TABLE . " (
                student_id, session_id, status, method, recorded_at
            ) VALUES (?, ?, 'present', 'face_recognition', NOW())
        ");

        if ($stmt->execute([$student_id, $session_id])) {
            jsonResponse([
                'status' => 'success',
                'message' => "Attendance marked for {$student['name']}",
                'student' => $student,
                'confidence' => $result['confidence'] ?? null
            ]);
        } else {
            jsonResponse(['status' => 'error', 'message' => 'Failed to record attendance'], 500);
        }

    } finally {
        // Clean up temporary file
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
    }
}

// Function: Get Session Status
function handleSessionStatus() {
    global $pdo;

    $session_id = sanitizeInput($_POST['session_id'] ?? '', 'int');

    if (!$session_id) {
        jsonResponse(['status' => 'error', 'message' => 'Session ID is required'], 400);
    }

    // Get session info
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as course_name, c.course_code,
               d.name as department_name, o.name as option_name
        FROM " . SESSION_TABLE . " s
        LEFT JOIN courses c ON s.course_id = c.id
        LEFT JOIN departments d ON c.department_id = d.id
        LEFT JOIN options o ON s.option_id = o.id
        WHERE s.id = ? AND s.lecturer_id = ?
    ");
    $stmt->execute([$session_id, $_SESSION['user_id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        jsonResponse(['status' => 'error', 'message' => 'Session not found or access denied'], 404);
    }

    // Get attendees
    $stmt = $pdo->prepare("
        SELECT ar.*, CONCAT(s.first_name, ' ', s.last_name) as student_name,
               ar.recorded_at as attendance_date
        FROM " . ATTENDANCE_TABLE . " ar
        LEFT JOIN " . STUDENTS_TABLE . " s ON ar.student_id = s.student_id
        WHERE ar.session_id = ?
        ORDER BY ar.recorded_at DESC
    ");
    $stmt->execute([$session_id]);
    $attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'status' => 'success',
        'session' => $session,
        'attendees' => $attendees
    ]);
}

// HTML Interface
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Session - RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .card { border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .attendee { transition: all 0.3s ease; }
        .attendee:hover { background-color: #f8f9fa; }
        .status-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .status-active { background-color: #28a745; animation: pulse 2s infinite; }
        .status-inactive { background-color: #6c757d; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-video me-2"></i>Attendance Session</h4>
                    </div>

                    <div class="card-body">
                        <!-- Session Controls -->
                        <div id="sessionControls">
                            <h5>Start New Session</h5>
                            <form id="sessionForm" class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Biometric Method</label>
                                    <select name="biometric_method" class="form-select" required>
                                        <option value="fingerprint">Fingerprint Scanner</option>
                                        <option value="face_recognition">Face Recognition</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Course</label>
                                    <select name="course_id" class="form-select" required>
                                        <option value="">Select Course</option>
                                        <?php
                                        // Get courses for current lecturer
                                        $stmt = $pdo->prepare("
                                            SELECT c.id, c.name, c.course_code
                                            FROM courses c
                                            WHERE c.department_id = (
                                                SELECT department_id FROM lecturers WHERE user_id = ?
                                            )
                                            ORDER BY c.name
                                        ");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        while ($course = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='{$course['id']}'>{$course['name']} ({$course['course_code']})</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Option</label>
                                    <select name="option_id" class="form-select">
                                        <option value="">Select Option (Optional)</option>
                                        <?php
                                        $stmt = $pdo->query("SELECT id, name FROM options ORDER BY name");
                                        while ($option = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='{$option['id']}'>{$option['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-play me-2"></i>Start Session
                                    </button>
                                </div>

                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="start_session">
                                <input type="hidden" name="lecturer_id" value="<?php echo $_SESSION['user_id']; ?>">
                            </form>
                        </div>

                        <!-- Active Session Panel -->
                        <div id="activeSessionPanel" class="d-none">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">
                                    <span class="status-indicator status-active"></span>
                                    Active Session
                                    <small id="sessionId" class="text-muted"></small>
                                </h5>
                                <button id="endSessionBtn" class="btn btn-danger">
                                    <i class="fas fa-stop me-2"></i>End Session
                                </button>
                            </div>

                            <div id="sessionInfo" class="alert alert-info mb-3"></div>

                            <!-- Attendance Controls -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div id="fingerprintControls">
                                        <button id="scanFingerprintBtn" class="btn btn-info w-100">
                                            <i class="fas fa-fingerprint me-2"></i>Start Fingerprint Scanning
                                        </button>
                                        <div id="fingerprintStatus" class="mt-2 small text-muted"></div>
                                    </div>

                                    <div id="faceControls" class="d-none">
                                        <button id="captureFaceBtn" class="btn btn-success w-100">
                                            <i class="fas fa-camera me-2"></i>Capture Face
                                        </button>
                                        <div id="faceStatus" class="mt-2 small text-muted"></div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="card border-light">
                                        <div class="card-body text-center">
                                            <h6 class="card-title">Session Statistics</h6>
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <div class="h4 text-primary" id="totalAttendees">0</div>
                                                    <small class="text-muted">Present</small>
                                                </div>
                                                <div class="col-6">
                                                    <div class="h4 text-info" id="sessionDuration">00:00</div>
                                                    <small class="text-muted">Duration</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Attendees List -->
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Attendees</h6>
                                </div>
                                <div class="card-body">
                                    <div id="attendeesList" class="list-group list-group-flush" style="max-height: 400px; overflow-y: auto;">
                                        <div class="text-center text-muted py-4" id="noAttendees">
                                            <i class="fas fa-users fa-2x mb-2"></i>
                                            <p>No attendees yet</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentSessionId = 0;
        let scanInterval = null;
        let sessionStartTime = null;
        let durationInterval = null;

        // CSRF token for AJAX requests
        const csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

        // AJAX helper
        async function ajax(formData) {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            return await response.json();
        }

        // Session Form Handler
        document.getElementById('sessionForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('csrf_token', csrfToken);

            try {
                const result = await ajax(formData);

                if (result.status === 'success') {
                    currentSessionId = result.session_id;
                    sessionStartTime = new Date();
                    showActiveSession(result.session_id);
                    startDurationTimer();
                    showNotification('Session started successfully!', 'success');
                } else {
                    showNotification(result.message || 'Failed to start session', 'error');
                }
            } catch (error) {
                console.error('Session start error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        // Show Active Session
        function showActiveSession(sessionId) {
            document.getElementById('sessionControls').classList.add('d-none');
            document.getElementById('activeSessionPanel').classList.remove('d-none');
            document.getElementById('sessionId').textContent = `#${sessionId}`;

            // Show appropriate controls based on biometric method
            const biometricMethod = document.querySelector('select[name="biometric_method"]').value;
            if (biometricMethod === 'face_recognition') {
                document.getElementById('faceControls').classList.remove('d-none');
                document.getElementById('fingerprintControls').classList.add('d-none');
            } else {
                document.getElementById('fingerprintControls').classList.remove('d-none');
                document.getElementById('faceControls').classList.add('d-none');
            }

            fetchSessionStatus();
        }

        // End Session Handler
        document.getElementById('endSessionBtn').addEventListener('click', async () => {
            if (!currentSessionId) return;

            const formData = new FormData();
            formData.append('action', 'end_session');
            formData.append('session_id', currentSessionId);
            formData.append('csrf_token', csrfToken);

            try {
                const result = await ajax(formData);

                if (result.status === 'success') {
                    hideActiveSession();
                    stopScanning();
                    stopDurationTimer();
                    showNotification('Session ended successfully!', 'success');
                } else {
                    showNotification(result.message || 'Failed to end session', 'error');
                }
            } catch (error) {
                console.error('Session end error:', error);
                showNotification('Network error occurred', 'error');
            }
        });

        // Hide Active Session
        function hideActiveSession() {
            document.getElementById('activeSessionPanel').classList.add('d-none');
            document.getElementById('sessionControls').classList.remove('d-none');
            currentSessionId = 0;
            sessionStartTime = null;
        }

        // Fingerprint Scanning
        document.getElementById('scanFingerprintBtn').addEventListener('click', () => {
            if (!currentSessionId) {
                showNotification('No active session', 'warning');
                return;
            }

            if (scanInterval) {
                stopScanning();
            } else {
                startFingerprintScanning();
            }
        });

        async function startFingerprintScanning() {
            const btn = document.getElementById('scanFingerprintBtn');
            const status = document.getElementById('fingerprintStatus');

            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scanning...';
            btn.classList.remove('btn-info');
            btn.classList.add('btn-warning');
            status.textContent = 'Connecting to fingerprint scanner...';

            scanInterval = setInterval(async () => {
                try {
                    const response = await fetch(`http://${'<?php echo ESP_IP; ?>'}/identify`, {
                        cache: 'no-store',
                        signal: AbortSignal.timeout(5000)
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.status === 'success' && data.fingerprint_id) {
                        // Mark attendance
                        await markFingerprintAttendance(data.fingerprint_id);
                    } else if (data.status === 'error') {
                        status.textContent = `Scanner: ${data.message || 'Error'}`;
                    }
                } catch (error) {
                    if (error.name === 'TimeoutError') {
                        status.textContent = 'Scanner timeout - retrying...';
                    } else {
                        status.textContent = 'Scanner not reachable';
                    }
                }
            }, 2000);
        }

        function stopScanning() {
            if (scanInterval) {
                clearInterval(scanInterval);
                scanInterval = null;
            }

            const btn = document.getElementById('scanFingerprintBtn');
            btn.innerHTML = '<i class="fas fa-fingerprint me-2"></i>Start Fingerprint Scanning';
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-info');

            document.getElementById('fingerprintStatus').textContent = '';
        }

        async function markFingerprintAttendance(fingerprintId) {
            const formData = new FormData();
            formData.append('action', 'mark_by_fingerprint');
            formData.append('session_id', currentSessionId);
            formData.append('fingerprint_id', fingerprintId);
            formData.append('csrf_token', csrfToken);

            try {
                const result = await ajax(formData);
                handleAttendanceResult(result);
            } catch (error) {
                console.error('Fingerprint attendance error:', error);
                showNotification('Failed to record fingerprint attendance', 'error');
            }
        }

        // Face Capture
        document.getElementById('captureFaceBtn').addEventListener('click', async () => {
            if (!currentSessionId) {
                showNotification('No active session', 'warning');
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: 640, height: 480, facingMode: 'user' }
                });

                const video = document.createElement('video');
                video.srcObject = stream;
                video.autoplay = true;
                video.style.display = 'none';
                document.body.appendChild(video);

                // Wait for video to load
                await new Promise(resolve => {
                    video.onloadedmetadata = resolve;
                });

                // Capture after 1 second
                setTimeout(async () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0);

                    canvas.toBlob(async (blob) => {
                        const formData = new FormData();
                        formData.append('action', 'mark_by_face');
                        formData.append('session_id', currentSessionId);
                        formData.append('live_photo', blob, 'face_capture.jpg');
                        formData.append('csrf_token', csrfToken);

                        document.getElementById('faceStatus').textContent = 'Processing face recognition...';

                        try {
                            const result = await ajax(formData);
                            handleAttendanceResult(result);
                        } catch (error) {
                            console.error('Face attendance error:', error);
                            showNotification('Failed to process face recognition', 'error');
                        }

                        // Cleanup
                        stream.getTracks().forEach(track => track.stop());
                        video.remove();
                        document.getElementById('faceStatus').textContent = '';
                    }, 'image/jpeg', 0.9);
                }, 1000);

            } catch (error) {
                console.error('Camera access error:', error);
                showNotification('Camera access denied or unavailable', 'error');
            }
        });

        // Handle Attendance Results
        function handleAttendanceResult(result) {
            if (result.status === 'success') {
                showNotification(result.message, 'success');
                addAttendeeToList(result.student);
                fetchSessionStatus();
            } else if (result.status === 'already') {
                showNotification(`Already marked: ${result.student?.name || 'Student'}`, 'warning');
            } else {
                showNotification(result.message || 'Attendance recording failed', 'error');
            }
        }

        // Fetch Session Status
        async function fetchSessionStatus() {
            if (!currentSessionId) return;

            const formData = new FormData();
            formData.append('action', 'session_status');
            formData.append('session_id', currentSessionId);
            formData.append('csrf_token', csrfToken);

            try {
                const result = await ajax(formData);
                if (result.status === 'success') {
                    updateAttendeesList(result.attendees || []);
                    updateSessionInfo(result.session);
                }
            } catch (error) {
                console.error('Session status fetch error:', error);
            }
        }

        // Update Attendees List
        function updateAttendeesList(attendees) {
            const container = document.getElementById('attendeesList');
            const noAttendees = document.getElementById('noAttendees');

            if (attendees.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-4" id="noAttendees"><i class="fas fa-users fa-2x mb-2"></i><p>No attendees yet</p></div>';
                document.getElementById('totalAttendees').textContent = '0';
                return;
            }

            noAttendees.style.display = 'none';
            document.getElementById('totalAttendees').textContent = attendees.length;

            container.innerHTML = attendees.map(attendee => `
                <div class="list-group-item d-flex justify-content-between align-items-center attendee">
                    <div>
                        <strong>${attendee.student_name || attendee.student_id}</strong>
                        <br><small class="text-muted">${new Date(attendee.attendance_date).toLocaleString()}</small>
                    </div>
                    <span class="badge bg-success">
                        <i class="fas fa-check me-1"></i>${attendee.method === 'fingerprint' ? 'Fingerprint' : 'Face'}
                    </span>
                </div>
            `).join('');
        }

        // Update Session Info
        function updateSessionInfo(session) {
            const infoDiv = document.getElementById('sessionInfo');
            if (session) {
                infoDiv.innerHTML = `
                    <strong>Course:</strong> ${session.course_name || 'N/A'} |
                    <strong>Method:</strong> ${session.biometric_method === 'face_recognition' ? 'Face Recognition' : 'Fingerprint'} |
                    <strong>Started:</strong> ${new Date(session.start_time).toLocaleString()}
                `;
            }
        }

        // Add Attendee to List (real-time)
        function addAttendeeToList(student) {
            if (!student) return;

            const container = document.getElementById('attendeesList');
            const noAttendees = document.getElementById('noAttendees');

            if (noAttendees) noAttendees.style.display = 'none';

            const attendeeDiv = document.createElement('div');
            attendeeDiv.className = 'list-group-item d-flex justify-content-between align-items-center attendee';
            attendeeDiv.innerHTML = `
                <div>
                    <strong>${student.name}</strong>
                    <br><small class="text-muted">${new Date().toLocaleString()}</small>
                </div>
                <span class="badge bg-success">
                    <i class="fas fa-check me-1"></i>Just now
                </span>
            `;

            container.insertBefore(attendeeDiv, container.firstChild);

            // Update count
            const currentCount = parseInt(document.getElementById('totalAttendees').textContent) || 0;
            document.getElementById('totalAttendees').textContent = currentCount + 1;
        }

        // Duration Timer
        function startDurationTimer() {
            durationInterval = setInterval(() => {
                if (sessionStartTime) {
                    const now = new Date();
                    const diff = Math.floor((now - sessionStartTime) / 1000);
                    const minutes = Math.floor(diff / 60);
                    const seconds = diff % 60;
                    document.getElementById('sessionDuration').textContent =
                        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                }
            }, 1000);
        }

        function stopDurationTimer() {
            if (durationInterval) {
                clearInterval(durationInterval);
                durationInterval = null;
            }
        }

        // Notification System
        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' :
                              type === 'error' ? 'alert-danger' :
                              type === 'warning' ? 'alert-warning' : 'alert-info';

            const icon = type === 'success' ? 'fas fa-check-circle' :
                        type === 'error' ? 'fas fa-exclamation-triangle' :
                        type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;';
            alert.innerHTML = `
                <div class="d-flex align-items-start">
                    <i class="${icon} me-2 mt-1"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${type.toUpperCase()}</div>
                        <div>${message}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            document.body.appendChild(alert);

            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 300);
                }
            }, 5000);
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            stopScanning();
            stopDurationTimer();
        });
    </script>
</body>
</html>