<?php
/**
 * Input Validator
 * Provides comprehensive input validation and sanitization
 */

class InputValidator {
    /**
     * Validate and sanitize integer input
     */
    public static function validateInt($value, $min = null, $max = null, $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        if ($intValue === false) {
            throw new InvalidArgumentException('Invalid integer value');
        }

        if ($min !== null && $intValue < $min) {
            throw new InvalidArgumentException("Value must be at least {$min}");
        }

        if ($max !== null && $intValue > $max) {
            throw new InvalidArgumentException("Value must be at most {$max}");
        }

        return $intValue;
    }

    /**
     * Validate and sanitize string input
     */
    public static function validateString($value, $maxLength = null, $pattern = null, $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }

        $stringValue = trim($value);
        if (empty($stringValue)) {
            return $default;
        }

        if ($maxLength !== null && strlen($stringValue) > $maxLength) {
            throw new InvalidArgumentException("String exceeds maximum length of {$maxLength}");
        }

        if ($pattern !== null && !preg_match($pattern, $stringValue)) {
            throw new InvalidArgumentException('String does not match required pattern');
        }

        return $stringValue;
    }

    /**
     * Validate email address
     */
    public static function validateEmail($value, $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }

        $email = filter_var($value, FILTER_VALIDATE_EMAIL);
        if ($email === false) {
            throw new InvalidArgumentException('Invalid email address');
        }

        return $email;
    }

    /**
     * Validate boolean value
     */
    public static function validateBool($value, $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Validate date string
     */
    public static function validateDate($value, $format = 'Y-m-d', $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }

        $date = DateTime::createFromFormat($format, $value);
        if (!$date || $date->format($format) !== $value) {
            throw new InvalidArgumentException("Invalid date format. Expected: {$format}");
        }

        return $value;
    }

    /**
     * Validate array input
     */
    public static function validateArray($value, $default = null) {
        if ($value === null || $value === '') {
            return $default;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('Value must be an array');
        }

        return $value;
    }

    /**
     * Sanitize string for database insertion
     */
    public static function sanitizeForDatabase($value) {
        if (!is_string($value)) {
            return $value;
        }

        // Remove null bytes
        $value = str_replace("\0", "", $value);

        // Escape HTML entities
        $value = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $value;
    }

    /**
     * Validate biometric method
     */
    public static function validateBiometricMethod($value) {
        $validMethods = ['face', 'finger'];
        if (!in_array($value, $validMethods)) {
            throw new InvalidArgumentException('Invalid biometric method');
        }
        return $value;
    }

    /**
     * Validate attendance status
     */
    public static function validateAttendanceStatus($value) {
        $validStatuses = ['present', 'absent'];
        if (!in_array($value, $validStatuses)) {
            throw new InvalidArgumentException('Invalid attendance status');
        }
        return $value;
    }

    /**
     * Validate user role
     */
    public static function validateUserRole($value) {
        $validRoles = ['admin', 'lecturer', 'hod', 'student'];
        if (!in_array($value, $validRoles)) {
            throw new InvalidArgumentException('Invalid user role');
        }
        return $value;
    }

    /**
     * Validate base64 image data
     */
    public static function validateBase64Image($value, $maxSize = 5242880) { // 5MB default
        if (empty($value)) {
            throw new InvalidArgumentException('Image data is required');
        }

        // Check if it's a data URL
        if (strpos($value, 'data:image') === 0) {
            $parts = explode(',', $value);
            if (count($parts) !== 2) {
                throw new InvalidArgumentException('Invalid image data format');
            }
            $value = $parts[1];
        }

        // Validate base64
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $value)) {
            throw new InvalidArgumentException('Invalid base64 encoding');
        }

        // Decode and check size
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid base64 data');
        }

        if (strlen($decoded) > $maxSize) {
            throw new InvalidArgumentException('Image file too large');
        }

        return $value;
    }

    /**
     * Validate session ID format
     */
    public static function validateSessionId($value) {
        if (!is_numeric($value) || $value <= 0) {
            throw new InvalidArgumentException('Invalid session ID');
        }
        return (int)$value;
    }

    /**
     * Validate student ID format
     */
    public static function validateStudentId($value) {
        if (!is_numeric($value) || $value <= 0) {
            throw new InvalidArgumentException('Invalid student ID');
        }
        return (int)$value;
    }

    /**
     * Validate department ID
     */
    public static function validateDepartmentId($value) {
        return self::validateInt($value, 1);
    }

    /**
     * Validate option ID
     */
    public static function validateOptionId($value) {
        return self::validateInt($value, 1);
    }

    /**
     * Validate course ID
     */
    public static function validateCourseId($value) {
        return self::validateInt($value, 1);
    }
}