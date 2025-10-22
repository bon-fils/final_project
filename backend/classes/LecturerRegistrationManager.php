<?php
/**
 * Lecturer Registration Manager
 * Handles all lecturer registration operations with enhanced security and validation
 */

class LecturerRegistrationManager {
    private $pdo;
    private $dbManager;
    private $validator;
    private $logger;

    public function __construct($pdo, $dbManager, $validator) {
        $this->pdo = $pdo;
        $this->dbManager = $dbManager;
        $this->validator = $validator;
        $this->logger = new Logger();
    }

    /**
     * Register a new lecturer with comprehensive validation
     */
    public function registerLecturer($data) {
        try {
            // Start transaction
            $this->pdo->beginTransaction();

            // Validate input data
            $validationResult = $this->validateLecturerData($data);
            if (!$validationResult['valid']) {
                throw new Exception(implode('. ', $validationResult['errors']));
            }

            // Check for duplicates
            $this->checkDuplicates($data['email'], $data['id_number']);

            // Generate unique username
            $username = $this->generateUniqueUsername($data['first_name'], $data['last_name']);

            // Create user account
            $userId = $this->createUserAccount($data, $username);

            // Create lecturer record
            $lecturerId = $this->createLecturerRecord($userId, $data);

            // Handle assignments
            $assignmentResult = $this->handleAssignments($lecturerId, $data);

            // Log activity
            $this->logRegistrationActivity($userId, $data, $assignmentResult);

            // Commit transaction
            $this->pdo->commit();

            return [
                'success' => true,
                'message' => 'Lecturer registered successfully!',
                'username' => $username,
                'password' => '12345', // Default password
                'details' => $assignmentResult
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Lecturer registration failed', [
                'error' => $e->getMessage(),
                'data' => array_intersect_key($data, array_flip(['first_name', 'last_name', 'email']))
            ]);
            throw $e;
        }
    }

    /**
     * Validate lecturer registration data
     */
    private function validateLecturerData($data) {
        $errors = [];
        $rules = [
            'first_name' => ['required' => true, 'min_length' => 2, 'max_length' => 50, 'pattern' => '/^[A-Za-z\s]+$/'],
            'last_name' => ['required' => true, 'min_length' => 2, 'max_length' => 50, 'pattern' => '/^[A-Za-z\s]+$/'],
            'gender' => ['required' => true, 'in' => ['Male', 'Female', 'Other']],
            'dob' => ['required' => true, 'date' => true, 'age_range' => [21, 100]],
            'id_number' => ['required' => true, 'pattern' => '/^\d{16}$/'],
            'email' => ['required' => true, 'email' => true, 'max_length' => 100],
            'department_id' => ['required' => true, 'exists' => 'departments'],
            'education_level' => ['required' => true, 'in' => ["Bachelor's", "Master's", 'PhD', 'Other']]
        ];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $fieldErrors = $this->validator->validateField($value, $rule, $field);
            $errors = array_merge($errors, $fieldErrors);
        }

        // Optional phone validation
        if (!empty($data['phone'])) {
            $phoneErrors = $this->validator->validateField($data['phone'], ['pattern' => '/^\d{10}$/'], 'phone');
            $errors = array_merge($errors, $phoneErrors);
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    /**
     * Check for duplicate records
     */
    private function checkDuplicates($email, $idNumber) {
        // Check email uniqueness
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email address is already registered');
        }

        // Check ID number uniqueness
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE id_number = ?");
        $stmt->execute([$idNumber]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('ID Number already exists');
        }
    }

    /**
     * Generate unique username
     */
    private function generateUniqueUsername($firstName, $lastName) {
        $baseUsername = strtolower(preg_replace('/\s+/', '.', $firstName . '.' . $lastName));
        $username = $baseUsername;
        $counter = 0;

        do {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $exists = $stmt->fetchColumn() > 0;

            if ($exists) {
                $counter++;
                $username = $baseUsername . $counter;
            }
        } while ($exists);

        return $username;
    }

    /**
     * Create user account
     */
    private function createUserAccount($data, $username) {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (username, email, password, role, first_name, last_name, phone, sex, dob, status, created_at, updated_at)
            VALUES (?, ?, ?, 'lecturer', ?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");

        $stmt->execute([
            $username,
            $data['email'],
            password_hash('12345', PASSWORD_DEFAULT),
            $data['first_name'],
            $data['last_name'],
            $data['phone'] ?? null,
            $data['gender'],
            $data['dob']
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Create lecturer record
     */
    private function createLecturerRecord($userId, $data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO lecturers (user_id, gender, dob, id_number, department_id, education_level, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $userId,
            $data['gender'],
            $data['dob'],
            $data['id_number'],
            $data['department_id'],
            $data['education_level']
        ]);

        return $this->pdo->lastInsertId();
    }

    /**
     * Handle course and option assignments
     */
    private function handleAssignments($lecturerId, $data) {
        $coursesAssigned = 0;
        $optionsAssigned = 0;

        // Handle course assignments
        if (!empty($data['selected_courses'])) {
            $coursesAssigned = $this->assignCourses($lecturerId, $data['selected_courses'], $data['department_id']);
        }

        // Handle option assignments (for tracking purposes)
        if (!empty($data['selected_options'])) {
            $optionsAssigned = count($data['selected_options']);
        }

        return [
            'courses_assigned' => $coursesAssigned,
            'options_assigned' => $optionsAssigned
        ];
    }

    /**
     * Assign courses to lecturer
     */
    private function assignCourses($lecturerId, $courseIds, $departmentId) {
        $assigned = 0;

        foreach ($courseIds as $courseId) {
            // Verify course exists and is unassigned
            $stmt = $this->pdo->prepare("
                SELECT id FROM courses
                WHERE id = ? AND department_id = ? AND (lecturer_id IS NULL OR lecturer_id = 0)
            ");
            $stmt->execute([$courseId, $departmentId]);

            if ($stmt->fetch()) {
                $updateStmt = $this->pdo->prepare("UPDATE courses SET lecturer_id = ? WHERE id = ?");
                $updateStmt->execute([$lecturerId, $courseId]);
                $assigned++;
            }
        }

        return $assigned;
    }

    /**
     * Log registration activity
     */
    private function logRegistrationActivity($userId, $data, $assignmentResult) {
        $activityDetails = "Registered new lecturer: {$data['first_name']} {$data['last_name']} in department {$data['department_id']}";

        if ($assignmentResult['courses_assigned'] > 0) {
            $activityDetails .= " with {$assignmentResult['courses_assigned']} course(s) assigned";
        }

        if ($assignmentResult['options_assigned'] > 0) {
            $activityDetails .= " and {$assignmentResult['options_assigned']} option(s) assigned";
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, created_at)
            VALUES (?, 'lecturer_registration', ?, NOW())
        ");
        $stmt->execute([$userId, $activityDetails]);
    }

    /**
     * Get departments for dropdown
     */
    public function getDepartments() {
        return $this->departmentManager->getAllDepartments();
    }

    /**
     * Check rate limiting for registration
     */
    public function checkRateLimit() {
        $maxSubmissions = 10;
        $timeWindow = 3600; // 1 hour

        if (!isset($_SESSION['lecturer_reg_submissions'])) {
            $_SESSION['lecturer_reg_submissions'] = [];
        }

        $now = time();
        $_SESSION['lecturer_reg_submissions'] = array_filter(
            $_SESSION['lecturer_reg_submissions'],
            fn($timestamp) => ($now - $timestamp) < $timeWindow
        );

        if (count($_SESSION['lecturer_reg_submissions']) >= $maxSubmissions) {
            return false;
        }

        $_SESSION['lecturer_reg_submissions'][] = $now;
        return true;
    }
}