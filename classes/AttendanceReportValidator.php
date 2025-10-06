<?php
/**
 * Attendance Report Input Validation
 * Provides comprehensive validation for all report parameters
 */

class AttendanceReportValidator {
    private $errors = [];

    /**
     * Validate all report filters
     */
    public function validateFilters(array $filters): bool {
        $this->errors = [];

        // Validate report type
        if (!isset($filters['report_type']) || empty($filters['report_type'])) {
            $this->errors[] = 'Report type is required';
        } elseif (!in_array($filters['report_type'], ['department', 'option', 'class', 'course'])) {
            $this->errors[] = 'Invalid report type';
        }

        // Validate department_id if provided
        if (isset($filters['department_id']) && !empty($filters['department_id'])) {
            if (!is_numeric($filters['department_id']) || $filters['department_id'] <= 0) {
                $this->errors[] = 'Invalid department ID';
            }
        }

        // Validate option_id if provided
        if (isset($filters['option_id']) && !empty($filters['option_id'])) {
            if (!is_numeric($filters['option_id']) || $filters['option_id'] <= 0) {
                $this->errors[] = 'Invalid option ID';
            }
        }

        // Validate class_id if provided
        if (isset($filters['class_id']) && !empty($filters['class_id'])) {
            if (!is_numeric($filters['class_id']) || $filters['class_id'] <= 0) {
                $this->errors[] = 'Invalid class ID';
            }
        }

        // Validate course_id if provided
        if (isset($filters['course_id']) && !empty($filters['course_id'])) {
            if (!is_numeric($filters['course_id']) || $filters['course_id'] <= 0) {
                $this->errors[] = 'Invalid course ID';
            }
        }

        // Validate dates
        if (isset($filters['start_date']) && !empty($filters['start_date'])) {
            if (!$this->isValidDate($filters['start_date'])) {
                $this->errors[] = 'Invalid start date format';
            }
        }

        if (isset($filters['end_date']) && !empty($filters['end_date'])) {
            if (!$this->isValidDate($filters['end_date'])) {
                $this->errors[] = 'Invalid end date format';
            }
        }

        // Validate date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            if (strtotime($filters['start_date']) > strtotime($filters['end_date'])) {
                $this->errors[] = 'Start date cannot be after end date';
            }
        }

        return empty($this->errors);
    }

    /**
     * Validate export format
     */
    public function validateExportFormat(string $format): bool {
        return in_array(strtolower($format), ['csv', 'excel', 'pdf']);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Check if date string is valid
     */
    private function isValidDate(string $date): bool {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Sanitize filter inputs
     */
    public function sanitizeFilters(array $filters): array {
        $sanitized = [];

        foreach ($filters as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim(strip_tags($value));
            } elseif (is_numeric($value)) {
                $sanitized[$key] = (int) $value;
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}