<?php
/**
 * Export Attendance Report API
 * Exports attendance data to CSV or PDF format
 */

session_start();
require_once "../config.php";
require_once "../session_check.php";
require_role(['lecturer', 'hod', 'admin']);

try {
    $export_format = $_GET['format'] ?? 'csv'; // csv or pdf
    $report_type = $_GET['report_type'] ?? 'course';
    $course_id = $_GET['course_id'] ?? null;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $lecturer_id = $_SESSION['lecturer_id'] ?? null;
    
    if (!$lecturer_id) {
        throw new Exception('Lecturer ID not found');
    }
    
    // Get report data (reuse the function from get-attendance-reports.php)
    require_once "get-attendance-reports.php";
    
    $data = null;
    switch ($report_type) {
        case 'course':
            $data = getCourseReport($pdo, $course_id, $start_date, $end_date, $lecturer_id);
            break;
        default:
            throw new Exception('Invalid report type for export');
    }
    
    if ($export_format === 'csv') {
        exportToCSV($data);
    } elseif ($export_format === 'pdf') {
        exportToPDF($data);
    } else {
        throw new Exception('Invalid export format');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Export to CSV
 */
function exportToCSV($data) {
    $filename = "attendance_report_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, ['Attendance Report']);
    fputcsv($output, ['Course:', $data['course']['course_name'] ?? 'N/A']);
    fputcsv($output, ['Course Code:', $data['course']['course_code'] ?? 'N/A']);
    fputcsv($output, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Write summary
    fputcsv($output, ['Summary Statistics']);
    fputcsv($output, ['Total Students', $data['summary']['total_students']]);
    fputcsv($output, ['Total Sessions', $data['summary']['total_sessions']]);
    fputcsv($output, ['Average Attendance', $data['summary']['average_attendance'] . '%']);
    fputcsv($output, ['Students Above 85%', $data['summary']['students_above_85']]);
    fputcsv($output, ['Students Below 75%', $data['summary']['students_below_75']]);
    fputcsv($output, []);
    
    // Write student data header
    fputcsv($output, [
        'Reg No',
        'Student Name',
        'Email',
        'Total Sessions',
        'Present',
        'Absent',
        'Attendance Rate (%)',
        'Status'
    ]);
    
    // Write student data
    foreach ($data['students'] as $student) {
        fputcsv($output, [
            $student['reg_no'],
            $student['student_name'],
            $student['email'],
            $student['total_sessions'],
            $student['present_count'],
            $student['absent_count'],
            $student['attendance_rate'],
            ucfirst($student['attendance_status'])
        ]);
    }
    
    fclose($output);
    exit;
}

/**
 * Export to PDF (Simple HTML to PDF)
 */
function exportToPDF($data) {
    $filename = "attendance_report_" . date('Y-m-d_H-i-s') . ".pdf";
    
    // For now, we'll generate an HTML version that can be printed to PDF
    // In production, you'd use a library like TCPDF or mPDF
    
    header('Content-Type: text/html');
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Attendance Report</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
            }
            .summary {
                background: #f5f5f5;
                padding: 15px;
                margin-bottom: 20px;
                border-radius: 5px;
            }
            .summary-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
            }
            .summary-item {
                text-align: center;
            }
            .summary-item h3 {
                margin: 0;
                color: #0066cc;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 12px;
                text-align: left;
            }
            th {
                background-color: #0066cc;
                color: white;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .excellent { color: #10b981; font-weight: bold; }
            .good { color: #f59e0b; font-weight: bold; }
            .average { color: #06b6d4; font-weight: bold; }
            .poor { color: #ef4444; font-weight: bold; }
            @media print {
                button { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Attendance Report</h1>
            <h2><?= htmlspecialchars($data['course']['course_name']) ?></h2>
            <p>Course Code: <?= htmlspecialchars($data['course']['course_code']) ?></p>
            <p>Generated: <?= date('F d, Y H:i:s') ?></p>
        </div>
        
        <div class="summary">
            <h3>Summary Statistics</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <h3><?= $data['summary']['total_students'] ?></h3>
                    <p>Total Students</p>
                </div>
                <div class="summary-item">
                    <h3><?= $data['summary']['total_sessions'] ?></h3>
                    <p>Total Sessions</p>
                </div>
                <div class="summary-item">
                    <h3><?= $data['summary']['average_attendance'] ?>%</h3>
                    <p>Average Attendance</p>
                </div>
            </div>
        </div>
        
        <button onclick="window.print()" style="padding: 10px 20px; background: #0066cc; color: white; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px;">
            Print / Save as PDF
        </button>
        
        <table>
            <thead>
                <tr>
                    <th>Reg No</th>
                    <th>Student Name</th>
                    <th>Total Sessions</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Attendance Rate</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['students'] as $student): ?>
                <tr>
                    <td><?= htmlspecialchars($student['reg_no']) ?></td>
                    <td><?= htmlspecialchars($student['student_name']) ?></td>
                    <td><?= $student['total_sessions'] ?></td>
                    <td><?= $student['present_count'] ?></td>
                    <td><?= $student['absent_count'] ?></td>
                    <td><?= $student['attendance_rate'] ?>%</td>
                    <td class="<?= $student['attendance_status'] ?>">
                        <?= ucfirst($student['attendance_status']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>
