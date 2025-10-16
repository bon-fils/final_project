<?php
class AttendanceSessionSecurity {
    private static $csrf_token_key = 'attendance_session_csrf_token';

    public static function generateCSRFToken() {
        if (!isset($_SESSION[self::$csrf_token_key])) {
            $_SESSION[self::$csrf_token_key] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::$csrf_token_key];
    }

    public static function validateCSRFToken($token) {
        if (!isset($_SESSION[self::$csrf_token_key]) || !hash_equals($_SESSION[self::$csrf_token_key], $token)) {
            throw new Exception('CSRF token validation failed');
        }
        return true;
    }

    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    public static function validateSessionData($data) {
        $errors = [];

        // Validate course_id
        if (empty($data['course_id']) || !filter_var($data['course_id'], FILTER_VALIDATE_INT)) {
            $errors[] = "Invalid course selection";
        }

        // Validate option_id
        if (empty($data['option_id']) || !filter_var($data['option_id'], FILTER_VALIDATE_INT)) {
            $errors[] = "Invalid program selection";
        }

        // Validate session_date
        if (empty($data['session_date'])) {
            $errors[] = "Session date is required";
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $data['session_date']);
            if (!$date || $date->format('Y-m-d') !== $data['session_date']) {
                $errors[] = "Invalid session date format";
            } elseif ($date < new DateTime('today')) {
                $errors[] = "Session date cannot be in the past";
            }
        }

        // Validate start_time
        if (empty($data['start_time'])) {
            $errors[] = "Start time is required";
        } elseif (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['start_time'])) {
            $errors[] = "Invalid start time format";
        }

        // Validate end_time if provided
        if (!empty($data['end_time'])) {
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $data['end_time'])) {
                $errors[] = "Invalid end time format";
            } elseif ($data['end_time'] <= $data['start_time']) {
                $errors[] = "End time must be after start time";
            }
        }

        // Validate biometric_method
        $valid_methods = ['face_recognition', 'fingerprint'];
        if (empty($data['biometric_method']) || !in_array($data['biometric_method'], $valid_methods)) {
            $errors[] = "Invalid biometric method selected";
        }

        return $errors;
    }

    public static function checkRateLimit($user_id, $action = 'create_session', $max_attempts = 10, $time_window = 3600) {
        $key = "rate_limit_{$action}_{$user_id}";
        $now = time();

        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => $now];
            return true;
        }

        $attempts = $_SESSION[$key]['attempts'];
        $first_attempt = $_SESSION[$key]['first_attempt'];

        // Reset if time window has passed
        if ($now - $first_attempt > $time_window) {
            $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => $now];
            return true;
        }

        // Check if limit exceeded
        if ($attempts >= $max_attempts) {
            return false;
        }

        // Increment attempts
        $_SESSION[$key]['attempts'] = $attempts + 1;
        return true;
    }

    public static function logSecurityEvent($event, $user_id, $details = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'user_id' => $user_id,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];

        $log_file = __DIR__ . '/../logs/security.log';
        $log_dir = dirname($log_file);

        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_message = json_encode($log_entry) . PHP_EOL;
        file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    }

    public static function validateUserPermissions($user_role, $user_id, $required_role = null, $resource_owner_id = null) {
        // Check role-based permissions
        if ($required_role && !in_array($user_role, (array)$required_role)) {
            return false;
        }

        // Check resource ownership for lecturers
        if ($user_role === 'lecturer' && $resource_owner_id && $resource_owner_id != $user_id) {
            return false;
        }

        return true;
    }

    public static function escapeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'escapeOutput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}
?>