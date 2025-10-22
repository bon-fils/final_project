<?php
session_start();
require_once "../config.php";
require_once "../session_check.php";
require_role(['lecturer', 'hod', 'admin']);

// Handle AJAX requests for face recognition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    try {
        // Validate CSRF token for security
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($csrf_token)) {
            throw new Exception('Security validation failed');
        }

        if ($_POST['action'] === 'process_face_recognition') {
            // Validate and sanitize inputs
            $imageData = trim($_POST['image_data'] ?? '');
            if (empty($imageData)) {
                throw new Exception('No image data provided');
            }

            $session_id = filter_var($_POST['session_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$session_id || $session_id <= 0) {
                throw new Exception('Invalid session ID');
            }

            // Verify session is active and user has access
            $stmt = $pdo->prepare("
                SELECT s.id, s.course_id, c.department_id
                FROM attendance_sessions s
                INNER JOIN courses c ON s.course_id = c.id
                WHERE s.id = ? AND s.end_time IS NULL
            ");
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                throw new Exception('Active session not found');
            }

            // Verify user has access to this session (lecturer owns the course)
            $user_id = $_SESSION['user_id'];
            $user_role = $_SESSION['role'];

            if ($user_role !== 'admin') {
                $stmt = $pdo->prepare("
                    SELECT 1 FROM courses c
                    WHERE c.id = ? AND c.lecturer_id = (
                        SELECT l.id FROM lecturers l WHERE l.user_id = ?
                    )
                ");
                $stmt->execute([$session['course_id'], $user_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('Access denied: You do not own this course');
                }
            }

            // Create secure temporary file for the captured image
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'face_recognition';
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempFile = tempnam($tempDir, 'face_capture_');
            $imageFile = $tempFile . '.jpg';

            try {
                // Clean up any existing temp file
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }

                // Decode and validate base64 image
                if (strpos($imageData, 'data:image') === 0) {
                    $imageData = explode(',', $imageData)[1];
                }

                $imageBinary = base64_decode($imageData, true);
                if ($imageBinary === false) {
                    throw new Exception('Invalid base64 image data');
                }

                // Validate image size (max 5MB)
                if (strlen($imageBinary) > 5 * 1024 * 1024) {
                    throw new Exception('Image file too large (max 5MB)');
                }

                // Save image to temporary file with secure permissions
                if (file_put_contents($imageFile, $imageBinary) === false) {
                    throw new Exception('Failed to save temporary image file');
                }

                // Set secure permissions (readable only by owner)
                chmod($imageFile, 0600);

            } catch (Exception $e) {
                // Clean up temp file if it exists
                if (file_exists($imageFile)) {
                    unlink($imageFile);
                }
                throw new Exception('Image processing failed: ' . $e->getMessage());
            }

            // Execute Python face recognition script with timeout and security
            $pythonScript = __DIR__ . '/../face_match.py';
            $command = sprintf(
                'timeout 30s %s %s 2>&1',
                escapeshellcmd('python3'),
                escapeshellarg($pythonScript) . ' ' . escapeshellarg($imageFile)
            );

            // Execute command with proper error handling
            $output = shell_exec($command);
            $exitCode = 0;

            // Clean up temporary file immediately
            if (file_exists($imageFile)) {
                unlink($imageFile);
            }

            // Log execution for debugging
            error_log("Face recognition command executed: " . substr($command, 0, 100) . "...");
            error_log("Exit code: $exitCode");

            // Parse JSON output with validation
            $result = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Invalid JSON from face recognition: " . $output);
                throw new Exception('Face recognition service returned invalid response');
            }

            // Process recognition result
            if (isset($result['status']) && $result['status'] === 'success' && isset($result['student_id'])) {
                $student_id = (int)$result['student_id'];

                // Verify student belongs to the session's department/option
                $stmt = $pdo->prepare("
                    SELECT s.id, s.reg_no, CONCAT(s.first_name, ' ', s.last_name) as full_name
                    FROM students s
                    INNER JOIN attendance_sessions sess ON sess.course_id IN (
                        SELECT c.id FROM courses c WHERE c.department_id = s.department_id
                    )
                    WHERE s.id = ? AND sess.id = ?
                ");
                $stmt->execute([$student_id, $session_id]);
                $student = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$student) {
                    throw new Exception('Student not found in this session\'s department');
                }

                // Check if attendance already recorded (prevent duplicates)
                $stmt = $pdo->prepare("
                    SELECT id, status FROM attendance_records
                    WHERE session_id = ? AND student_id = ?
                ");
                $stmt->execute([$session_id, $student_id]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    echo json_encode([
                        'status' => 'already_recorded',
                        'message' => 'Attendance already recorded for this student',
                        'student_name' => $student['full_name'],
                        'student_reg' => $student['reg_no'],
                        'current_status' => $existing['status']
                    ]);
                } else {
                    // Record new attendance
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance_records (session_id, student_id, status, recorded_at)
                        VALUES (?, ?, 'present', NOW())
                    ");
                    $stmt->execute([$session_id, $student_id]);

                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Attendance marked successfully!',
                        'student_name' => $student['full_name'],
                        'student_reg' => $student['reg_no'],
                        'confidence' => $result['confidence'] ?? 0,
                        'record_id' => $pdo->lastInsertId()
                    ]);
                }
            } else {
                echo json_encode([
                    'status' => 'no_match',
                    'message' => $result['message'] ?? 'No face match found',
                    'faces_detected' => $result['faces_detected'] ?? 0
                ]);
            }
        } else {
            throw new Exception('Invalid action requested');
        }
    } catch (Exception $e) {
        error_log("Face recognition error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// If not a POST request, return 405 Method Not Allowed
http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
exit;
?>