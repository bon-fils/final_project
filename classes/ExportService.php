<?php
/**
 * Export Service for Attendance Reports
 * Handles CSV, Excel, and PDF export functionality
 */

class ExportService {
    /**
     * Export attendance data based on format
     */
    public function export(array $reportData, string $format, string $filename): void {
        if (empty($reportData) || isset($reportData['error'])) {
            throw new InvalidArgumentException('Invalid report data for export');
        }

        $filename = $this->sanitizeFilename($filename);

        switch (strtolower($format)) {
            case 'csv':
                $this->exportToCSV($reportData, $filename);
                break;
            case 'excel':
                $this->exportToExcel($reportData, $filename);
                break;
            case 'pdf':
                $this->exportToPDF($reportData, $filename);
                break;
            default:
                throw new InvalidArgumentException('Unsupported export format');
        }
    }

    /**
     * Export to CSV format
     */
    private function exportToCSV(array $reportData, string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        header('Pragma: public');

        $output = fopen('php://output', 'w');

        // Add BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // CSV headers
        fputcsv($output, [
            'Student Name',
            'Registration No',
            'Department',
            'Attendance %',
            'Present Sessions',
            'Total Sessions',
            'Status',
            'Grade'
        ]);

        // Data rows
        foreach ($reportData['attendance'] as $studentId => $data) {
            $student = $data['student_info'];
            $summary = $data['summary'];
            $percentage = $summary['percentage'];

            // Determine status and grade
            if ($percentage >= 85) {
                $status = 'Allowed to Exam';
                $grade = 'A';
            } elseif ($percentage >= 70) {
                $status = 'Warning';
                $grade = 'B';
            } elseif ($percentage >= 60) {
                $status = 'At Risk';
                $grade = 'C';
            } else {
                $status = 'Not Allowed to Exam';
                $grade = 'F';
            }

            fputcsv($output, [
                $student['full_name'],
                $student['reg_no'] ?? '',
                $student['department_name'] ?? '',
                number_format($percentage, 1) . '%',
                $summary['present_count'],
                $summary['total_sessions'],
                $status,
                $grade
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Export to Excel format (HTML table)
     */
    private function exportToExcel(array $reportData, string $filename): void {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        header('Pragma: public');

        // Add BOM for Excel compatibility
        echo chr(0xEF).chr(0xBB).chr(0xBF);

        echo "<html><head><meta charset='utf-8'></head><body>";
        echo "<table border='1'>";

        // Header
        echo "<tr style='background-color: #0066cc; color: white; font-weight: bold;'>";
        echo "<th>Student Name</th>";
        echo "<th>Registration No</th>";
        echo "<th>Department</th>";
        echo "<th>Attendance %</th>";
        echo "<th>Present Sessions</th>";
        echo "<th>Total Sessions</th>";
        echo "<th>Status</th>";
        echo "<th>Grade</th>";
        echo "</tr>";

        // Data rows
        foreach ($reportData['attendance'] as $studentId => $data) {
            $student = $data['student_info'];
            $summary = $data['summary'];
            $percentage = $summary['percentage'];

            // Determine status and grade
            if ($percentage >= 85) {
                $status = 'Allowed to Exam';
                $bgColor = '#d4edda';
                $grade = 'A';
            } elseif ($percentage >= 70) {
                $status = 'Warning';
                $bgColor = '#fff3cd';
                $grade = 'B';
            } elseif ($percentage >= 60) {
                $status = 'At Risk';
                $bgColor = '#f8d7da';
                $grade = 'C';
            } else {
                $status = 'Not Allowed to Exam';
                $bgColor = '#f5c6cb';
                $grade = 'F';
            }

            echo "<tr style='background-color: $bgColor;'>";
            echo "<td>" . htmlspecialchars($student['full_name']) . "</td>";
            echo "<td>" . htmlspecialchars($student['reg_no'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($student['department_name'] ?? '') . "</td>";
            echo "<td style='text-align: center;'>" . number_format($percentage, 1) . "%</td>";
            echo "<td style='text-align: center;'>" . $summary['present_count'] . "</td>";
            echo "<td style='text-align: center;'>" . $summary['total_sessions'] . "</td>";
            echo "<td>" . $status . "</td>";
            echo "<td style='text-align: center; font-weight: bold;'>" . $grade . "</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "<br><br>";
        echo "<strong>Report Summary:</strong><br>";
        echo "Total Students: " . $reportData['summary']['total_students'] . "<br>";
        echo "Average Attendance: " . $reportData['summary']['average_attendance_rate'] . "%<br>";
        echo "Students Above 85%: " . $reportData['summary']['students_above_85_percent'] . "<br>";
        echo "Generated on: " . date('Y-m-d H:i:s');

        echo "</body></html>";
        exit;
    }

    /**
     * Export to PDF format
     */
    private function exportToPDF(array $reportData, string $filename): void {
        // Check if TCPDF is available
        if (!class_exists('TCPDF')) {
            // Fallback to basic HTML-to-PDF approach or error
            throw new RuntimeException('PDF library not available. Please install TCPDF or use CSV/Excel export.');
        }

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('RP Attendance System');
        $pdf->SetAuthor('RP Attendance System');
        $pdf->SetTitle('Attendance Report');
        $pdf->SetSubject('Student Attendance Report');

        // Set margins
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add a page
        $pdf->AddPage();

        // Report header
        $pdf->SetFont('helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Student Attendance Report', 0, 1, 'C');
        $pdf->Ln(5);

        // Report info
        $pdf->SetFont('helvetica', '', 10);
        if (isset($reportData['course_info'])) {
            $pdf->Cell(0, 6, 'Course: ' . $reportData['course_info']['course_name'] . ' (' . $reportData['course_info']['course_code'] . ')', 0, 1);
        }
        $pdf->Cell(0, 6, 'Report Period: ' . ($reportData['date_range']['start'] ?? 'All time') . ' to ' . ($reportData['date_range']['end'] ?? 'All time'), 0, 1);
        $pdf->Cell(0, 6, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 1);
        $pdf->Ln(5);

        // Summary statistics
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Summary Statistics', 0, 1);
        $pdf->SetFont('helvetica', '', 10);

        $summary = $reportData['summary'];
        $pdf->Cell(0, 6, 'Total Students: ' . $summary['total_students'], 0, 1);
        $pdf->Cell(0, 6, 'Total Sessions: ' . $summary['total_sessions'], 0, 1);
        $pdf->Cell(0, 6, 'Average Attendance Rate: ' . $summary['average_attendance_rate'] . '%', 0, 1);
        $pdf->Cell(0, 6, 'Students Above 85%: ' . $summary['students_above_85_percent'], 0, 1);
        $pdf->Cell(0, 6, 'Students Below 85%: ' . $summary['students_below_85_percent'], 0, 1);
        $pdf->Ln(5);

        // Student attendance table
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Student Attendance Details', 0, 1);

        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(66, 139, 202);
        $pdf->SetTextColor(255, 255, 255);

        $header = ['Student Name', 'Reg No', 'Department', 'Attendance %', 'Present', 'Total', 'Status'];
        $widths = [50, 25, 35, 20, 15, 15, 30];

        foreach ($header as $i => $col) {
            $pdf->Cell($widths[$i], 8, $col, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Table data
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);

        $fill = false;
        foreach ($reportData['attendance'] as $studentId => $data) {
            $student = $data['student_info'];
            $summary = $data['summary'];
            $percentage = $summary['percentage'];

            // Determine status
            if ($percentage >= 85) {
                $status = 'Allowed';
                $pdf->SetFillColor(212, 237, 218);
            } elseif ($percentage >= 70) {
                $status = 'Warning';
                $pdf->SetFillColor(255, 243, 205);
            } else {
                $status = 'Not Allowed';
                $pdf->SetFillColor(248, 215, 218);
            }

            $pdf->Cell($widths[0], 6, $this->truncateText($student['full_name'], 25), 1, 0, 'L', $fill);
            $pdf->Cell($widths[1], 6, $student['reg_no'] ?? '', 1, 0, 'C', $fill);
            $pdf->Cell($widths[2], 6, $this->truncateText($student['department_name'] ?? '', 20), 1, 0, 'L', $fill);
            $pdf->Cell($widths[3], 6, number_format($percentage, 1) . '%', 1, 0, 'C', $fill);
            $pdf->Cell($widths[4], 6, $summary['present_count'], 1, 0, 'C', $fill);
            $pdf->Cell($widths[5], 6, $summary['total_sessions'], 1, 0, 'C', $fill);
            $pdf->Cell($widths[6], 6, $status, 1, 0, 'C', $fill);
            $pdf->Ln();

            $fill = !$fill;
        }

        // Output PDF
        $pdf->Output($filename . '.pdf', 'D');
        exit;
    }

    /**
     * Sanitize filename for export
     */
    private function sanitizeFilename(string $filename): string {
        // Remove any path components and keep only filename
        $filename = basename($filename);

        // Replace special characters with underscores
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        // Ensure filename is not too long
        if (strlen($filename) > 100) {
            $filename = substr($filename, 0, 100);
        }

        return $filename;
    }

    /**
     * Truncate text for PDF cells
     */
    private function truncateText(string $text, int $maxLength): string {
        if (strlen($text) <= $maxLength) {
            return $text;
        }

        return substr($text, 0, $maxLength - 3) . '...';
    }
}