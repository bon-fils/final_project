<?php
/**
 * Attendance Exporter
 * Handles exporting attendance data in various formats
 */

class AttendanceExporter {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Export attendance data for a session
     */
    public function exportSession($sessionId, $format = 'csv') {
        // Get session details
        $session = $this->getSessionDetails($sessionId);
        if (!$session) {
            throw new Exception('Session not found');
        }

        // Get attendance records
        $records = $this->getAttendanceRecords($sessionId);

        switch ($format) {
            case 'csv':
                return $this->exportAsCsv($session, $records);
            case 'json':
                return $this->exportAsJson($session, $records);
            default:
                throw new Exception('Unsupported export format');
        }
    }

    /**
     * Get session details
     */
    private function getSessionDetails($sessionId) {
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
     * Get attendance records for session
     */
    private function getAttendanceRecords($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT ar.*, s.reg_no as student_id,
                   CONCAT(u.first_name, ' ', u.last_name) as student_name,
                   u.email as student_email, s.year_level
            FROM attendance_records ar
            INNER JOIN students s ON ar.student_id = s.id
            INNER JOIN users u ON s.user_id = u.id
            WHERE ar.session_id = ?
            ORDER BY u.first_name, u.last_name
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Export as CSV
     */
    private function exportAsCsv($session, $records) {
        $csv = "Student ID,Name,Email,Year Level,Status,Method,Recorded At\n";

        foreach ($records as $record) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s\n",
                $record['student_id'],
                '"' . str_replace('"', '""', $record['student_name']) . '"',
                $record['student_email'],
                $record['year_level'] ?: 'N/A',
                ucfirst($record['status']),
                ucfirst(str_replace('_', ' ', $record['method'])),
                $record['recorded_at']
            );
        }

        $filename = sprintf(
            'attendance_%s_%s_%s.csv',
            $session['course_code'],
            date('Y-m-d', strtotime($session['session_date'])),
            date('His')
        );

        return [
            'status' => 'success',
            'message' => 'Attendance data exported successfully',
            'data' => [
                'content' => base64_encode($csv),
                'filename' => $filename,
                'format' => 'csv'
            ]
        ];
    }

    /**
     * Export as JSON
     */
    private function exportAsJson($session, $records) {
        $data = [
            'session_info' => [
                'course_name' => $session['course_name'],
                'course_code' => $session['course_code'],
                'department' => $session['department_name'],
                'option' => $session['option_name'],
                'lecturer' => $session['first_name'] . ' ' . $session['last_name'],
                'session_date' => $session['session_date'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'biometric_method' => $session['biometric_method']
            ],
            'attendance_records' => array_map(function($record) {
                return [
                    'student_id' => $record['student_id'],
                    'student_name' => $record['student_name'],
                    'email' => $record['student_email'],
                    'year_level' => $record['year_level'],
                    'status' => $record['status'],
                    'method' => $record['method'],
                    'recorded_at' => $record['recorded_at']
                ];
            }, $records),
            'summary' => [
                'total_records' => count($records),
                'present_count' => count(array_filter($records, fn($r) => $r['status'] === 'present')),
                'absent_count' => count(array_filter($records, fn($r) => $r['status'] === 'absent')),
                'exported_at' => date('Y-m-d H:i:s')
            ]
        ];

        $filename = sprintf(
            'attendance_%s_%s_%s.json',
            $session['course_code'],
            date('Y-m-d', strtotime($session['session_date'])),
            date('His')
        );

        return [
            'status' => 'success',
            'message' => 'Attendance data exported successfully',
            'data' => [
                'content' => base64_encode(json_encode($data, JSON_PRETTY_PRINT)),
                'filename' => $filename,
                'format' => 'json'
            ]
        ];
    }
}