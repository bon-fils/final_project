<?php
/**
 * Export Attendance Reports
 * Supports CSV and PDF formats
 */
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'hod', 'admin']);

$type = $_GET['type'] ?? 'all'; // 'course' or 'all'
$format = $_GET['format'] ?? 'csv'; // 'csv' or 'pdf'
$course_id = $_GET['course_id'] ?? null;

$lecturer_id = $_SESSION['lecturer_id'] ?? null;

if (!$lecturer_id) {
    die("Unauthorized access");
}

global $pdo;

try {
    if ($type === 'course' && $course_id) {
        // Export single course
        exportCourseAttendance($pdo, $course_id, $format, $lecturer_id);
    } else {
        // Export all courses
        exportAllCourses($pdo, $format, $lecturer_id);
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

function exportCourseAttendance($pdo, $course_id, $format, $lecturer_id) {
    // Get course info
    $course_stmt = $pdo->prepare("
        SELECT c.*, o.name as option_name
        FROM courses c
        LEFT JOIN options o ON c.option_id = o.id
        WHERE c.id = ? AND c.lecturer_id = ?
    ");
    $course_stmt->execute([$course_id, $lecturer_id]);
    $course = $course_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        die("Course not found");
    }
    
    // Get students
    $students_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.reg_no,
            CONCAT(u.first_name, ' ', u.last_name) as student_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.option_id = ? 
          AND CAST(s.year_level AS UNSIGNED) = ?
          AND s.status = 'active'
        ORDER BY u.first_name, u.last_name
    ");
    $students_stmt->execute([$course['option_id'], $course['year']]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sessions count
    $sessions_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_sessions WHERE course_id = ?");
    $sessions_stmt->execute([$course_id]);
    $total_sessions = $sessions_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get attendance for each student
    $data = [];
    foreach ($students as $student) {
        $attendance_stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count
            FROM attendance_records ar
            JOIN attendance_sessions ats ON ar.session_id = ats.id
            WHERE ats.course_id = ? AND ar.student_id = ?
        ");
        $attendance_stmt->execute([$course_id, $student['id']]);
        $attendance = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
        
        $present_count = intval($attendance['present_count'] ?? 0);
        $absent_count = $total_sessions - $present_count;
        $percentage = $total_sessions > 0 ? round(($present_count / $total_sessions) * 100, 1) : 0;
        
        $data[] = [
            'reg_no' => $student['reg_no'],
            'student_name' => $student['student_name'],
            'total_sessions' => $total_sessions,
            'present' => $present_count,
            'absent' => $absent_count,
            'percentage' => $percentage
        ];
    }
    
    if ($format === 'csv') {
        exportCSV($data, $course, 'course');
    } else {
        exportPDF($data, $course, 'course');
    }
}

function exportAllCourses($pdo, $format, $lecturer_id) {
    // Get all courses
    $courses_stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.course_code,
            c.course_name,
            c.option_id,
            o.name as option_name,
            c.year
        FROM courses c
        LEFT JOIN options o ON c.option_id = o.id
        WHERE c.lecturer_id = ? AND c.status = 'active'
        ORDER BY c.year, c.course_code
    ");
    $courses_stmt->execute([$lecturer_id]);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $data = [];
    foreach ($courses as $course) {
        // Count students
        $student_stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT s.id) as count
            FROM students s
            WHERE s.option_id = ? 
              AND CAST(s.year_level AS UNSIGNED) = ? 
              AND s.status = 'active'
        ");
        $student_stmt->execute([$course['option_id'], $course['year']]);
        $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Count sessions
        $session_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance_sessions WHERE course_id = ?");
        $session_stmt->execute([$course['id']]);
        $session_count = $session_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get attendance
        $attendance_stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count
            FROM attendance_records ar
            JOIN attendance_sessions ats ON ar.session_id = ats.id
            WHERE ats.course_id = ?
        ");
        $attendance_stmt->execute([$course['id']]);
        $present_count = intval($attendance_stmt->fetch(PDO::FETCH_ASSOC)['present_count'] ?? 0);
        
        $total_possible = $student_count * $session_count;
        $avg_attendance = $total_possible > 0 ? round(($present_count / $total_possible) * 100, 1) : 0;
        
        $data[] = [
            'course_code' => $course['course_code'],
            'course_name' => $course['course_name'],
            'option' => $course['option_name'] ?? 'N/A',
            'year' => $course['year'],
            'students' => $student_count,
            'sessions' => $session_count,
            'avg_attendance' => $avg_attendance
        ];
    }
    
    if ($format === 'csv') {
        exportCSV($data, null, 'all');
    } else {
        exportPDF($data, null, 'all');
    }
}

function exportCSV($data, $course, $type) {
    $filename = $type === 'course' 
        ? "attendance_" . $course['course_code'] . "_" . date('Y-m-d') . ".csv"
        : "all_courses_attendance_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    if ($type === 'course') {
        // Course header
        fputcsv($output, ['Course: ' . $course['course_code'] . ' - ' . $course['course_name']]);
        fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        // Column headers
        fputcsv($output, ['Student ID', 'Student Name', 'Total Sessions', 'Present', 'Absent', 'Attendance %']);
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, [
                $row['reg_no'],
                $row['student_name'],
                $row['total_sessions'],
                $row['present'],
                $row['absent'],
                $row['percentage'] . '%'
            ]);
        }
    } else {
        // All courses header
        fputcsv($output, ['All Courses Attendance Report']);
        fputcsv($output, ['Generated: ' . date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        
        // Column headers
        fputcsv($output, ['Course Code', 'Course Name', 'Option', 'Year', 'Students', 'Sessions', 'Avg Attendance %']);
        
        // Data rows
        foreach ($data as $row) {
            fputcsv($output, [
                $row['course_code'],
                $row['course_name'],
                $row['option'],
                $row['year'],
                $row['students'],
                $row['sessions'],
                $row['avg_attendance'] . '%'
            ]);
        }
    }
    
    fclose($output);
    exit();
}

function exportPDF($data, $course, $type) {
    // Simple HTML to PDF conversion
    $filename = $type === 'course' 
        ? "attendance_" . $course['course_code'] . "_" . date('Y-m-d') . ".pdf"
        : "all_courses_attendance_" . date('Y-m-d') . ".pdf";
    
    // For now, generate HTML that can be printed to PDF
    header('Content-Type: text/html');
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Attendance Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #0066cc; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #0066cc; color: white; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .header { margin-bottom: 20px; }
            @media print {
                button { display: none; }
            }
        </style>
    </head>
    <body>';
    
    if ($type === 'course') {
        echo '<div class="header">';
        echo '<h1>Attendance Report</h1>';
        echo '<p><strong>Course:</strong> ' . htmlspecialchars($course['course_code']) . ' - ' . htmlspecialchars($course['course_name']) . '</p>';
        echo '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        echo '</div>';
        
        echo '<table>';
        echo '<thead><tr><th>Student ID</th><th>Student Name</th><th>Total Sessions</th><th>Present</th><th>Absent</th><th>Attendance %</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['reg_no']) . '</td>';
            echo '<td>' . htmlspecialchars($row['student_name']) . '</td>';
            echo '<td>' . $row['total_sessions'] . '</td>';
            echo '<td>' . $row['present'] . '</td>';
            echo '<td>' . $row['absent'] . '</td>';
            echo '<td>' . $row['percentage'] . '%</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<div class="header">';
        echo '<h1>All Courses Attendance Report</h1>';
        echo '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        echo '</div>';
        
        echo '<table>';
        echo '<thead><tr><th>Course Code</th><th>Course Name</th><th>Option</th><th>Year</th><th>Students</th><th>Sessions</th><th>Avg Attendance %</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($data as $row) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($row['course_code']) . '</td>';
            echo '<td>' . htmlspecialchars($row['course_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['option']) . '</td>';
            echo '<td>' . $row['year'] . '</td>';
            echo '<td>' . $row['students'] . '</td>';
            echo '<td>' . $row['sessions'] . '</td>';
            echo '<td>' . $row['avg_attendance'] . '%</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    echo '<br><button onclick="window.print()">Print / Save as PDF</button>';
    echo '</body></html>';
    exit();
}
?>
