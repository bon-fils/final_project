<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

$conn = getDBConnection();

switch ($action) {
    case 'identify':
        handleIdentify();
        break;
    case 'enroll':
        handleEnroll();
        break;
    case 'status':
        handleStatus();
        break;
    case 'scan':
        handleScan();
        break;
    case 'start_scan_mode':
        handleStartScanMode();
        break;
    case 'stop_scan_mode':
        handleStopScanMode();
        break;
    case 'get_recent_activity':
        handleGetRecentActivity();
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

function handleIdentify() {
    global $conn;

    // Call ESP32 to get fingerprint identification
    $esp32Response = esp32Request('/identify');

    if ($esp32Response && $esp32Response['success']) {
        $fingerprintId = $esp32Response['fingerprint_id'];

        // Find student by fingerprint ID
        $stmt = $conn->prepare("SELECT id, student_id, name FROM students WHERE fingerprint_id = ? AND status = 'active'");
        $stmt->execute([$fingerprintId]);
        $student = $stmt->fetch();

        if ($student) {
            // Check current attendance status for today
            $stmt = $conn->prepare("SELECT id, check_in_time, check_out_time FROM attendance WHERE student_id = ? AND date = CURDATE() ORDER BY check_in_time DESC LIMIT 1");
            $stmt->execute([$student['id']]);
            $lastAttendance = $stmt->fetch();

            if (!$lastAttendance || $lastAttendance['check_out_time']) {
                // Check-in: No attendance today or last session was checked out
                $stmt = $conn->prepare("INSERT INTO attendance (student_id, fingerprint_id, date, status) VALUES (?, ?, CURDATE(), 'present')");
                $stmt->execute([$student['id'], $fingerprintId]);

                echo json_encode([
                    'success' => true,
                    'student' => $student,
                    'message' => 'Check-in recorded successfully',
                    'action' => 'check_in'
                ]);
            } else {
                // Check-out: Has check-in but no check-out
                $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE id = ?");
                $stmt->execute([$lastAttendance['id']]);

                echo json_encode([
                    'success' => true,
                    'student' => $student,
                    'message' => 'Check-out recorded successfully',
                    'action' => 'check_out'
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Student not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Fingerprint identification failed']);
    }
}

function handleEnroll() {
    global $conn;

    if (!isset($_POST['student_id'])) {
        echo json_encode(['success' => false, 'error' => 'Student ID required']);
        return;
    }

    $studentId = $_POST['student_id'];

    // Get student details
    $stmt = $conn->prepare("SELECT id, fingerprint_id FROM students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['success' => false, 'error' => 'Student not found']);
        return;
    }

    if ($student['fingerprint_id']) {
        echo json_encode(['success' => false, 'error' => 'Student already has fingerprint enrolled']);
        return;
    }

    // Generate fingerprint ID (using student database ID)
    $fingerprintId = $student['id'];

    // Call ESP32 to enroll fingerprint
    $esp32Response = esp32Request('/enroll', 'POST', ['id' => $fingerprintId]);

    if ($esp32Response && $esp32Response['success']) {
        // Update student with fingerprint ID
        $stmt = $conn->prepare("UPDATE students SET fingerprint_id = ? WHERE id = ?");
        $stmt->execute([$fingerprintId, $student['id']]);

        echo json_encode(['success' => true, 'message' => 'Fingerprint enrolled successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Fingerprint enrollment failed']);
    }
}

function handleStatus() {
    global $conn;

    // Get system stats
    $stmt = $conn->query("SELECT COUNT(*) as total_students FROM students WHERE status = 'active'");
    $totalStudents = $stmt->fetch()['total_students'];

    $stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) as today_attendance FROM attendance WHERE date = CURDATE()");
    $stmt->execute();
    $todayAttendance = $stmt->fetch()['today_attendance'];

    // Get ESP32 status
    $esp32Status = esp32Request('/status');

    echo json_encode([
        'success' => true,
        'system' => [
            'total_students' => $totalStudents,
            'today_attendance' => $todayAttendance,
            'database' => 'connected'
        ],
        'esp32' => $esp32Status ?: ['status' => 'disconnected']
    ]);
}

function handleScan() {
    global $conn;

    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'auto';

    // Call ESP32 to identify fingerprint
    $esp32Response = esp32Request('/identify');

    if ($esp32Response && $esp32Response['success']) {
        $fingerprintId = $esp32Response['fingerprint_id'];

        // Find student by fingerprint ID
        $stmt = $conn->prepare("SELECT id, student_id, name FROM students WHERE fingerprint_id = ? AND status = 'active'");
        $stmt->execute([$fingerprintId]);
        $student = $stmt->fetch();

        if ($student) {
            // Check current attendance status for today
            $stmt = $conn->prepare("SELECT id, check_in_time, check_out_time FROM attendance WHERE student_id = ? AND date = CURDATE() ORDER BY check_in_time DESC LIMIT 1");
            $stmt->execute([$student['id']]);
            $lastAttendance = $stmt->fetch();

            $hasCheckedIn = $lastAttendance && !$lastAttendance['check_out_time'];

            if ($mode === 'checkin') {
                // Check-in only mode
                if (!$lastAttendance || $lastAttendance['check_out_time']) {
                    // Allow check-in
                    $stmt = $conn->prepare("INSERT INTO attendance (student_id, fingerprint_id, date, status) VALUES (?, ?, CURDATE(), 'present')");
                    $stmt->execute([$student['id'], $fingerprintId]);

                    esp32Request('/display', 'GET', ['message' => 'Check-in OK']);

                    echo json_encode([
                        'success' => true,
                        'student' => $student,
                        'action' => 'check_in',
                        'message' => 'Check-in recorded successfully'
                    ]);
                } else {
                    // Already checked in
                    echo json_encode([
                        'success' => false,
                        'error' => 'already_checked_in',
                        'student' => $student,
                        'message' => 'Student already checked in today'
                    ]);
                }
            } elseif ($mode === 'checkout') {
                // Check-out only mode
                if ($hasCheckedIn) {
                    // Allow check-out
                    $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE id = ?");
                    $stmt->execute([$lastAttendance['id']]);

                    esp32Request('/display', 'GET', ['message' => 'Check-out OK']);

                    echo json_encode([
                        'success' => true,
                        'student' => $student,
                        'action' => 'check_out',
                        'message' => 'Check-out recorded successfully'
                    ]);
                } else {
                    // Not checked in yet
                    echo json_encode([
                        'success' => false,
                        'error' => 'not_checked_in',
                        'student' => $student,
                        'message' => 'Student not checked in yet'
                    ]);
                }
            } else {
                // Auto mode (original logic)
                if (!$lastAttendance || $lastAttendance['check_out_time']) {
                    // Check-in
                    $stmt = $conn->prepare("INSERT INTO attendance (student_id, fingerprint_id, date, status) VALUES (?, ?, CURDATE(), 'present')");
                    $stmt->execute([$student['id'], $fingerprintId]);

                    esp32Request('/display', 'GET', ['message' => 'Check-in OK']);

                    echo json_encode([
                        'success' => true,
                        'student' => $student,
                        'action' => 'check_in',
                        'message' => 'Check-in recorded successfully'
                    ]);
                } else {
                    // Check-out
                    $stmt = $conn->prepare("UPDATE attendance SET check_out_time = NOW() WHERE id = ?");
                    $stmt->execute([$lastAttendance['id']]);

                    esp32Request('/display', 'GET', ['message' => 'Check-out OK']);

                    echo json_encode([
                        'success' => true,
                        'student' => $student,
                        'action' => 'check_out',
                        'message' => 'Check-out recorded successfully'
                    ]);
                }
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Student not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'No fingerprint detected']);
    }
}

function handleStartScanMode() {
    // Tell ESP32 to enter continuous scan mode
    $esp32Response = esp32Request('/display', 'GET', ['message' => 'Scan Mode ON']);

    echo json_encode([
        'success' => true,
        'message' => 'Continuous scan mode started'
    ]);
}

function handleStopScanMode() {
    // Tell ESP32 to exit continuous scan mode
    $esp32Response = esp32Request('/display', 'GET', ['message' => 'Scan Mode OFF']);

    echo json_encode([
        'success' => true,
        'message' => 'Continuous scan mode stopped'
    ]);
}

function handleGetRecentActivity() {
    global $conn;

    $stmt = $conn->query("
        SELECT a.check_in_time, a.check_out_time, s.student_id, s.name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE DATE(a.check_in_time) = CURDATE()
        ORDER BY COALESCE(a.check_out_time, a.check_in_time) DESC
        LIMIT 5
    ");

    $html = '';
    while ($row = $stmt->fetch()) {
        $isCheckOut = !empty($row['check_out_time']);
        $time = $isCheckOut ? $row['check_out_time'] : $row['check_in_time'];
        $action = $isCheckOut ? 'Check-out' : 'Check-in';
        $iconClass = $isCheckOut ? 'text-green-600' : 'text-blue-600';
        $bgClass = $isCheckOut ? 'bg-green-50 border-green-200' : 'bg-blue-50 border-blue-200';

        $html .= "<div class='flex items-center justify-between p-2 rounded border {$bgClass}'>";
        $html .= "<div class='flex items-center space-x-2'>";
        $html .= "<i class='fas fa-user {$iconClass} text-sm'></i>";
        $html .= "<span class='text-sm font-medium'>{$row['name']}</span>";
        $html .= "<span class='text-xs text-gray-500'>({$row['student_id']})</span>";
        $html .= "</div>";
        $html .= "<div class='flex items-center space-x-2'>";
        $html .= "<span class='text-xs font-medium {$iconClass}'>{$action}</span>";
        $html .= "<span class='text-xs text-gray-500'>" . date('H:i', strtotime($time)) . "</span>";
        $html .= "</div>";
        $html .= "</div>";
    }

    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
}
?>