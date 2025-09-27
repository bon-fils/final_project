<?php
/**
 * Enhanced Validation Manager
 * Provides comprehensive validation for all backend operations
 * Version: 2.0 - Advanced validation with security features
 */

class ValidationManager {
    private $errors = [];
    private $data = [];
    private $rules = [];

    public function __construct($data = []) {
        $this->data = $data;
    }

    /**
     * Set data to validate
     */
    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * Add validation rule
     */
    public function addRule($field, $rule, $value = null) {
        $this->rules[$field][] = ['rule' => $rule, 'value' => $value];
        return $this;
    }

    /**
     * Validate required fields
     */
    public function required($fields) {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $field) {
            if (empty($this->data[$field]) && $this->data[$field] !== '0') {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' is required');
            }
        }
        return $this;
    }

    /**
     * Validate email with enhanced security
     */
    public function email($field, $strict = false) {
        if (empty($this->data[$field])) {
            return $this;
        }

        $email = $this->data[$field];

        // Basic email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'Invalid email format');
            return $this;
        }

        // Strict validation for additional security
        if ($strict) {
            // Check for suspicious patterns
            $suspiciousPatterns = [
                '/\.\./',  // Consecutive dots
                '/@.*@/',  // Multiple @ symbols
                '/\+.*\+/', // Multiple + symbols
                '/\s/',    // Whitespace
            ];

            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $email)) {
                    $this->addError($field, 'Email contains invalid characters');
                    return $this;
                }
            }

            // Check for valid domain
            $domain = substr(strrchr($email, "@"), 1);
            if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
                $this->addError($field, 'Email domain does not exist');
            }
        }

        return $this;
    }

    /**
     * Validate phone numbers with international support
     */
    public function phone($field, $type = 'standard') {
        if (empty($this->data[$field])) {
            return $this;
        }

        $phone = preg_replace('/[^\d+]/', '', $this->data[$field]);

        if ($type === 'strict') {
            // International format validation
            if (!preg_match('/^\+\d{10,15}$/', $phone)) {
                $this->addError($field, 'Phone number must be in international format (+countrycode number)');
            }
        } else {
            // Standard validation
            if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                $this->addError($field, 'Invalid phone number format');
            }
        }

        return $this;
    }

    /**
     * Validate string length
     */
    public function length($field, $min, $max) {
        if (empty($this->data[$field])) {
            return $this;
        }

        $length = strlen($this->data[$field]);

        if ($length < $min) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters long");
        }

        if ($length > $max) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters");
        }

        return $this;
    }

    /**
     * Validate alphanumeric characters
     */
    public function alphaNumeric($field, $allowSpaces = false) {
        if (empty($this->data[$field])) {
            return $this;
        }

        $pattern = $allowSpaces ? '/^[a-zA-Z0-9\s]+$/' : '/^[a-zA-Z0-9]+$/';

        if (!preg_match($pattern, $this->data[$field])) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must contain only alphanumeric characters');
        }

        return $this;
    }

    /**
     * Validate no special characters
     */
    public function noSpecialChars($fields) {
        if (!is_array($fields)) {
            $fields = [$fields];
        }

        foreach ($fields as $field) {
            if (empty($this->data[$field])) {
                continue;
            }

            if (preg_match('/[^a-zA-Z0-9\s]/', $this->data[$field])) {
                $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' contains invalid characters');
            }
        }

        return $this;
    }

    /**
     * Validate file uploads
     */
    public function file($field, $allowedTypes = [], $maxSize = 0) {
        if (empty($_FILES[$field])) {
            return $this;
        }

        $file = $_FILES[$field];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, 'File upload failed');
            return $this;
        }

        // Check file type
        if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
            $this->addError($field, 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
        }

        // Check file size
        if ($maxSize > 0 && $file['size'] > $maxSize) {
            $this->addError($field, 'File size exceeds limit of ' . ($maxSize / 1024 / 1024) . 'MB');
        }

        return $this;
    }

    /**
     * Validate date formats
     */
    public function date($field, $format = 'Y-m-d') {
        if (empty($this->data[$field])) {
            return $this;
        }

        $date = DateTime::createFromFormat($format, $this->data[$field]);
        if (!$date || $date->format($format) !== $this->data[$field]) {
            $this->addError($field, 'Invalid date format');
        }

        return $this;
    }

    /**
     * Validate Rwanda-specific administrative divisions
     */
    public function rwandaLocation($field, $type = 'province') {
        if (empty($this->data[$field])) {
            return $this;
        }

        $value = trim($this->data[$field]);
        $validLocations = [];

        switch ($type) {
            case 'province':
                $validLocations = [
                    'Kigali', 'Northern', 'Southern', 'Eastern', 'Western'
                ];
                break;
            case 'sector':
                // This would typically be populated based on selected province
                // For now, accept any non-empty value
                if (strlen($value) < 2) {
                    $this->addError($field, 'Sector name must be at least 2 characters');
                }
                return $this;
            case 'cell':
                // This would typically be populated based on selected sector
                // For now, accept any non-empty value
                if (strlen($value) < 2) {
                    $this->addError($field, 'Cell name must be at least 2 characters');
                }
                return $this;
            default:
                $this->addError($field, 'Invalid location type');
                return $this;
        }

        if (!in_array($value, $validLocations)) {
            $this->addError($field, 'Invalid ' . $type . '. Valid options: ' . implode(', ', $validLocations));
        }

        return $this;
    }

    /**
     * Validate student ID number (Rwanda national ID format)
     */
    public function studentIdNumber($field) {
        if (empty($this->data[$field])) {
            return $this;
        }

        $idNumber = $this->data[$field];

        // Rwanda ID number format: YYMMDD-XXXXXXX-X (12 digits + 2 dashes)
        if (!preg_match('/^\d{12}$/', str_replace('-', '', $idNumber))) {
            $this->addError($field, 'Student ID number must be 12 digits');
            return $this;
        }

        // Validate check digit (simplified validation)
        $cleanId = str_replace('-', '', $idNumber);
        if (strlen($cleanId) !== 12) {
            $this->addError($field, 'Student ID number must be exactly 12 digits');
        }

        return $this;
    }

    /**
     * Validate parent contact information
     */
    public function parentContact($field) {
        if (empty($this->data[$field])) {
            return $this;
        }

        $contact = $this->data[$field];

        // Accept phone numbers or email addresses
        if (!filter_var($contact, FILTER_VALIDATE_EMAIL) &&
            !preg_match('/^[\d\s\-\+\(\)]{10,15}$/', $contact)) {
            $this->addError($field, 'Parent contact must be a valid email or phone number');
        }

        return $this;
    }

    /**
     * Validate age based on date of birth (must be between 15-25 for students)
     */
    public function studentAge($field, $minAge = 15, $maxAge = 25) {
        if (empty($this->data[$field])) {
            return $this;
        }

        try {
            $dob = new DateTime($this->data[$field]);
            $today = new DateTime();
            $age = $today->diff($dob)->y;

            if ($age < $minAge || $age > $maxAge) {
                $this->addError($field, "Age must be between {$minAge} and {$maxAge} years");
            }
        } catch (Exception $e) {
            $this->addError($field, 'Invalid date of birth');
        }

        return $this;
    }

    /**
     * Validate numeric values
     */
    public function numeric($field, $min = null, $max = null) {
        if (empty($this->data[$field])) {
            return $this;
        }

        if (!is_numeric($this->data[$field])) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . ' must be a number');
            return $this;
        }

        if ($min !== null && $this->data[$field] < $min) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min}");
        }

        if ($max !== null && $this->data[$field] > $max) {
            $this->addError($field, ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max}");
        }

        return $this;
    }

    /**
     * Validate URL format
     */
    public function url($field) {
        if (empty($this->data[$field])) {
            return $this;
        }

        if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->addError($field, 'Invalid URL format');
        }

        return $this;
    }

    /**
     * Validate password strength
     */
    public function password($field, $minLength = 8) {
        if (empty($this->data[$field])) {
            return $this;
        }

        $password = $this->data[$field];

        if (strlen($password) < $minLength) {
            $this->addError($field, "Password must be at least {$minLength} characters long");
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $this->addError($field, 'Password must contain at least one uppercase letter');
        }

        if (!preg_match('/[a-z]/', $password)) {
            $this->addError($field, 'Password must contain at least one lowercase letter');
        }

        if (!preg_match('/[0-9]/', $password)) {
            $this->addError($field, 'Password must contain at least one number');
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $this->addError($field, 'Password must contain at least one special character');
        }

        return $this;
    }

    /**
     * Custom validation rule
     */
    public function custom($field, $callback, $message = 'Invalid value') {
        if (empty($this->data[$field])) {
            return $this;
        }

        if (!$callback($this->data[$field])) {
            $this->addError($field, $message);
        }

        return $this;
    }

    /**
     * Add custom error message
     */
    public function addError($field, $message) {
        $this->errors[$field][] = $message;
    }

    /**
     * Check if validation passes
     */
    public function passes() {
        return empty($this->errors);
    }

    /**
     * Check if validation fails
     */
    public function fails() {
        return !empty($this->errors);
    }

    /**
     * Get all errors
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * Get errors for specific field
     */
    public function getFieldErrors($field) {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get first error for specific field
     */
    public function getFirstError($field) {
        $errors = $this->getFieldErrors($field);
        return $errors[0] ?? null;
    }

    /**
     * Get all errors as flat array
     */
    public function allErrors() {
        $allErrors = [];
        foreach ($this->errors as $fieldErrors) {
            $allErrors = array_merge($allErrors, $fieldErrors);
        }
        return $allErrors;
    }

    /**
     * Sanitize input data
     */
    public function sanitize($field, $type = 'string') {
        if (!isset($this->data[$field])) {
            return null;
        }

        $value = $this->data[$field];

        switch ($type) {
            case 'email':
                return filter_var($value, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($value, FILTER_SANITIZE_URL);
            case 'int':
                return filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'string':
            default:
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
    }

    /**
     * Validate and sanitize all data
     */
    public function validateAndSanitize() {
        $sanitized = [];

        foreach ($this->data as $field => $value) {
            $sanitized[$field] = $this->sanitize($field);
        }

        return $sanitized;
    }
}