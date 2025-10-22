<?php
/**
 * Start Attendance Session API
 * Creates a new attendance session and initializes biometric scanning
 */

// Suppress errors from being output (they'll be logged instead)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once "../config.php";
session_start();

// Set JSON header BEFORE any output
header("Content-Type: application/json");

// Clear any output buffers
if (ob_get_level()) ob_clean();

// Authentication check
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Authentication required"]);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['department_id', 'option_id', 'course_id', 'year_level', 'biometric_method', 'lecturer_id'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        echo json_encode([
            "status" => "error", 
            "message" => "Missing required field: $field"
        ]);
        exit;
    }
}

// Extract data
$department_id = (int)$input['department_id'];
$option_id = (int)$input['option_id'];
$course_id = (int)$input['course_id'];
$year_level = $input['year_level'];
$biometric_method = $input['biometric_method'];
$lecturer_id = (int)$input['lecturer_id'];

// Validate biometric method - match database enum values
if (!in_array($biometric_method, ['face_recognition', 'fingerprint'])) {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid biometric method. Must be 'face_recognition' or 'fingerprint'"
    ]);
    exit;
}

try {
    // Get course details
    $course_stmt = $pdo->prepare("
        SELECT c.id, c.name, c.course_code, c.credits,
               o.name as option_name,
               d.name as department_name
        FROM courses c
        JOIN options o ON c.option_id = o.id
        JOIN departments d ON o.department_id = d.id
        WHERE c.id = ?
    ");
    $course_stmt->execute([$course_id]);
    $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        echo json_encode([
            "status" => "error",
            "message" => "Course not found"
        ]);
        exit;
    }
    
    // Check for existing active session
    $check_stmt = $pdo->prepare("
        SELECT id FROM attendance_sessions 
        WHERE lecturer_id = ? 
        AND status = 'active'
        AND session_date = CURDATE()
    ");
    $check_stmt->execute([$lecturer_id]);
    $existing_session = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_session) {
        echo json_encode([
            "status" => "error",
            "message" => "You already have an active attendance session. Please end it before starting a new one.",
            "existing_session_id" => $existing_session['id']
        ]);
        exit;
    }
    
    // Create new attendance session
    $insert_stmt = $pdo->prepare("
        INSERT INTO attendance_sessions 
        (lecturer_id, course_id, option_id, department_id, year_level, 
         biometric_method, session_date, start_time, status)
        VALUES (?, ?, ?, ?, ?, ?, CURDATE(), NOW(), 'active')
    ");
    
    $insert_stmt->execute([
        $lecturer_id,
        $course_id,
        $option_id,
        $department_id,
        $year_level,
        $biometric_method
    ]);
    
    $session_id = $pdo->lastInsertId();
    
    // Get created session details
    $session_stmt = $pdo->prepare("
        SELECT 
            ats.id,
            ats.session_date,
            ats.start_time,
            ats.biometric_method,
            ats.year_level,
            ats.status,
            c.name as course_name,
            c.course_code,
            o.name as option_name,
            d.name as department_name,
            CONCAT(u.first_name, ' ', u.last_name) as lecturer_name
        FROM attendance_sessions ats
        JOIN courses c ON ats.course_id = c.id
        JOIN options o ON ats.option_id = o.id
        JOIN departments d ON ats.department_id = d.id
        JOIN users u ON ats.lecturer_id = u.id
        WHERE ats.id = ?
    ");
    $session_stmt->execute([$session_id]);
    $session_details = $session_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get student count for this session
    $student_count_stmt = $pdo->prepare("
        SELECT COUNT(*) as total_students
        FROM students
        WHERE option_id = ? AND year_level = ? AND status = 'active'
    ");
    $student_count_stmt->execute([$option_id, $year_level]);
    $student_count = $student_count_stmt->fetch(PDO::FETCH_ASSOC)['total_students'];
    
    // Log the session creation
    error_log("Attendance session created: ID=$session_id, Lecturer=$lecturer_id, Course=$course_id, Method=$biometric_method");
    
    // Return success response
    echo json_encode([
        "status" => "success",
        "message" => "Attendance session started successfully",
        "session" => [
            "id" => $session_id,
            "session_date" => $session_details['session_date'],
            "start_time" => $session_details['start_time'],
            "course_name" => $session_details['course_name'],
            "course_code" => $session_details['course_code'],
            "option_name" => $session_details['option_name'],
            "department_name" => $session_details['department_name'],
            "lecturer_name" => $session_details['lecturer_name'],
            "year_level" => $session_details['year_level'],
            "biometric_method" => $session_details['biometric_method'],
            "status" => $session_details['status'],
            "total_students" => $student_count,
            "students_present" => 0
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Start session database error: " . $e->getMessage());
    error_log("SQL State: " . $e->getCode());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        "status" => "error",
        "message" => "Database error occurred while starting session",
        "debug" => $e->getMessage(),
        "sql_state" => $e->getCode(),
        "error_info" => $e->errorInfo ?? null
    ]);
} catch (Exception $e) {
    error_log("Start session general error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        "status" => "error",
        "message" => "Failed to start attendance session",
        "debug" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
}
?>
