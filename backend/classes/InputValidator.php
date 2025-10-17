<?php
/**
 * Input Validation Class
 * Rwanda Polytechnic Attendance System
 * Handles comprehensive input validation and sanitization
 */

class InputValidator {
    private $data;
    private $errors = [];
    private $rules = [];

    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * Set required fields
     */
    public function required($fields) {
        foreach ($fields as $field) {
            if (!isset($this->data[$field]) || empty(trim($this->data[$field]))) {
                $this->errors[$field][] = "The $field field is required";
            }
        }
        return $this;
    }

    /**
     * Validate length constraints
     */
    public function length($field, $min, $max) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $value = trim($this->data[$field]);
            $length = strlen($value);
            if ($length < $min) {
                $this->errors[$field][] = "The $field field must be at least $min characters";
            }
            if ($length > $max) {
                $this->errors[$field][] = "The $field field must not exceed $max characters";
            }
        }
        return $this;
    }

    /**
     * Validate email format
     */
    public function email($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $email = trim($this->data[$field]);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field][] = "The $field field must be a valid email address";
            }
        }
        return $this;
    }

    /**
     * Validate phone number format
     */
    public function phone($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = trim($this->data[$field]);
            // Rwanda phone number validation (starts with 0, exactly 10 digits)
            if (!preg_match('/^0\d{9}$/', $phone)) {
                $this->errors[$field][] = "The $field field must be a valid 10-digit phone number starting with 0";
            }
        }
        return $this;
    }

    /**
     * Validate date format
     */
    public function date($field, $format = 'Y-m-d') {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $date = trim($this->data[$field]);
            $d = DateTime::createFromFormat($format, $date);
            if (!$d || $d->format($format) !== $date) {
                $this->errors[$field][] = "The $field field must be a valid date";
            }
        }
        return $this;
    }

    /**
     * Custom validation rule
     */
    public function custom($field, $callback, $message = null) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $value = $this->data[$field];
            if (!$callback($value)) {
                $errorMessage = $message ?: "The $field field is invalid";
                $this->errors[$field][] = $errorMessage;
            }
        }
        return $this;
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
     * Get all validation errors
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * Get all errors as flat array
     */
    public function allErrors() {
        $flat = [];
        foreach ($this->errors as $field => $messages) {
            $flat = array_merge($flat, $messages);
        }
        return $flat;
    }

    /**
     * Get errors for specific field
     */
    public function getErrors($field) {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if field has errors
     */
    public function hasErrors($field = null) {
        if ($field === null) {
            return !empty($this->errors);
        }
        return isset($this->errors[$field]);
    }

    /**
     * Get first error for field
     */
    public function firstError($field) {
        if (isset($this->errors[$field]) && !empty($this->errors[$field])) {
            return $this->errors[$field][0];
        }
        return null;
    }

    /**
     * Clear all errors
     */
    public function clearErrors() {
        $this->errors = [];
        return $this;
    }

    /**
     * Clear errors for specific field
     */
    public function clearFieldErrors($field) {
        unset($this->errors[$field]);
        return $this;
    }

    /**
     * Add custom error message
     */
    public function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
        return $this;
    }

    /**
     * Get validated data
     */
    public function validated() {
        $validated = [];
        foreach ($this->data as $key => $value) {
            if (!isset($this->errors[$key])) {
                $validated[$key] = $value;
            }
        }
        return $validated;
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
                return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'string':
            default:
                return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
    }
}
?>