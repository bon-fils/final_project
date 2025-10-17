<?php
/**
 * Data Sanitization Utility Class
 * Rwanda Polytechnic Attendance System
 * Handles data sanitization and validation
 */

class DataSanitizer {
    /**
     * Sanitize string input
     */
    public static function string($input) {
        if ($input === null || $input === '') {
            return '';
        }
        return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize email input
     */
    public static function email($input) {
        if ($input === null || $input === '') {
            return '';
        }
        $sanitized = filter_var(trim((string)$input), FILTER_SANITIZE_EMAIL);
        return $sanitized ? $sanitized : '';
    }

    /**
     * Sanitize integer input
     */
    public static function int($input) {
        if ($input === null || $input === '') {
            return 0;
        }
        return (int)$input;
    }

    /**
     * Sanitize float input
     */
    public static function float($input) {
        if ($input === null || $input === '') {
            return 0.0;
        }
        return (float)$input;
    }

    /**
     * Sanitize URL input
     */
    public static function url($input) {
        if ($input === null || $input === '') {
            return '';
        }
        $sanitized = filter_var(trim((string)$input), FILTER_SANITIZE_URL);
        return $sanitized ? $sanitized : '';
    }

    /**
     * Sanitize phone number (remove all non-numeric characters except +)
     */
    public static function phone($input) {
        if ($input === null || $input === '') {
            return '';
        }
        return preg_replace('/[^\d+]/', '', trim((string)$input));
    }

    /**
     * Sanitize filename (remove dangerous characters)
     */
    public static function filename($input) {
        if ($input === null || $input === '') {
            return '';
        }
        return preg_replace('/[^a-zA-Z0-9._-]/', '', trim((string)$input));
    }

    /**
     * Sanitize SQL identifier (table/column names)
     */
    public static function sqlIdentifier($input) {
        if ($input === null || $input === '') {
            return '';
        }
        return preg_replace('/[^a-zA-Z0-9_]/', '', trim((string)$input));
    }

    /**
     * Sanitize HTML content (allow basic tags)
     */
    public static function html($input, $allowedTags = '<p><br><strong><em><u><h1><h2><h3><h4><h5><h6><ul><ol><li><a>') {
        if ($input === null || $input === '') {
            return '';
        }
        return strip_tags(trim((string)$input), $allowedTags);
    }

    /**
     * Sanitize JSON string
     */
    public static function json($input) {
        if ($input === null || $input === '') {
            return '';
        }
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return '';
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Sanitize array of strings
     */
    public static function stringArray($input) {
        if (!is_array($input)) {
            return [];
        }
        return array_map([self::class, 'string'], $input);
    }

    /**
     * Check if input is safe (no XSS attempts)
     */
    public static function isSafeString($input) {
        if ($input === null || $input === '') {
            return true;
        }
        $string = (string)$input;

        // Check for common XSS patterns
        $dangerous = [
            '<script',
            'javascript:',
            'vbscript:',
            'onload=',
            'onerror=',
            'onclick=',
            '<iframe',
            '<object',
            '<embed'
        ];

        $lower = strtolower($string);
        foreach ($dangerous as $pattern) {
            if (strpos($lower, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize and validate input with length limits
     */
    public static function stringWithLength($input, $maxLength = 255) {
        $sanitized = self::string($input);
        return substr($sanitized, 0, $maxLength);
    }

    /**
     * Sanitize database field name
     */
    public static function fieldName($input) {
        if ($input === null || $input === '') {
            return '';
        }
        // Only allow alphanumeric, underscore, and dash
        return preg_replace('/[^a-zA-Z0-9_-]/', '', trim((string)$input));
    }
}
?>