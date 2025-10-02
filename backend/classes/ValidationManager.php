<?php
/**
 * Validation Management Class
 * Rwanda Polytechnic Attendance System
 * Handles input validation and sanitization
 */

class ValidationManager {

    /**
     * Validate department creation data
     */
    public function validateDepartmentData($name, $hodId = null, $programs = []) {
        // Validate department name
        if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
            return [
                'valid' => false,
                'message' => 'Department name must be 2-100 characters'
            ];
        }

        if (!preg_match('/^[a-zA-Z0-9\s\-\.\(\)]+$/', $name)) {
            return [
                'valid' => false,
                'message' => 'Department name contains invalid characters'
            ];
        }

        // Validate HoD ID if provided
        if ($hodId !== null && (!is_numeric($hodId) || $hodId <= 0)) {
            return [
                'valid' => false,
                'message' => 'Invalid HoD selection'
            ];
        }

        // Validate programs if provided
        if (!empty($programs)) {
            foreach ($programs as $program) {
                $validation = $this->validateProgramData($program);
                if (!$validation['valid']) {
                    return $validation;
                }
            }
        }

        return ['valid' => true];
    }

    /**
     * Validate program data
     */
    public function validateProgramData($name, $status = 'active') {
        if (empty($name) || strlen($name) < 2 || strlen($name) > 100) {
            return [
                'valid' => false,
                'message' => 'Program name must be 2-100 characters'
            ];
        }

        if (!preg_match('/^[a-zA-Z0-9\s\-\.\(\)\&]+$/', $name)) {
            return [
                'valid' => false,
                'message' => 'Program name contains invalid characters'
            ];
        }

        $validStatuses = ['active', 'inactive'];
        if (!in_array($status, $validStatuses)) {
            return [
                'valid' => false,
                'message' => 'Invalid program status'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate CSRF token
     */
    public function validateCSRF($token, $sessionToken) {
        if (empty($token) || empty($sessionToken)) {
            return [
                'valid' => false,
                'message' => 'CSRF token missing'
            ];
        }

        if (!hash_equals($sessionToken, $token)) {
            return [
                'valid' => false,
                'message' => 'Invalid CSRF token'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Sanitize input data
     */
    public function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, 'sanitizeInput'], $data);
        }

        return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate numeric ID
     */
    public function validateId($id, $fieldName = 'ID') {
        if (!is_numeric($id) || $id <= 0) {
            return [
                'valid' => false,
                'message' => "Invalid $fieldName"
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate required fields
     */
    public function validateRequired($data, $requiredFields) {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty(trim($data[$field]))) {
                return [
                    'valid' => false,
                    'message' => "Field '$field' is required"
                ];
            }
        }

        return ['valid' => true];
    }
}
?>