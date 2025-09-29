<?php
/**
 * Student Registration API
 * RESTful API endpoints for student registration operations
 * Version: 2.0 - Enhanced with security and validation
 */

require_once __DIR__ . '/../classes/ValidationManager.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/DatabaseManager.php';

class StudentRegistrationAPI {
    private $db;
    private $logger;
    private $validator;
    private $allowedOrigins = [
        'http://localhost',
        'http://localhost:80',
        'http://localhost:3000',
        'https://localhost',
        'https://localhost:443'
    ];

    public function __construct($pdo, $logger = null) {
        $this->db = new DatabaseManager($pdo, $logger);
        $this->logger = $logger ?: new Logger(['file' => 'logs/api.log']);
        $this->validator = new ValidationManager();

        $this->setCORSHeaders();
        $this->handleRequest();
    }

    /**
     * Set CORS headers
     */
    private function setCORSHeaders() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (in_array($origin, $this->allowedOrigins) || strpos($origin, 'localhost') !== false) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400'); // 24 hours

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    /**
     * Handle API request
     */
    private function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['path'] ?? '';
        $pathParts = explode('/', trim($path, '/'));

        // Authenticate request
        if (!$this->authenticateRequest()) {
            $this->sendResponse(401, ['error' => 'Authentication required']);
            return;
        }

        try {
            switch ($method) {
                case 'GET':
                    $this->handleGET($pathParts);
                    break;
                case 'POST':
                    $this->handlePOST($pathParts);
                    break;
                case 'PUT':
                    $this->handlePUT($pathParts);
                    break;
                case 'DELETE':
                    $this->handleDELETE($pathParts);
                    break;
                default:
                    $this->sendResponse(405, ['error' => 'Method not allowed']);
            }
        } catch (Exception $e) {
            $this->logger->error('StudentRegistrationAPI', $e->getMessage(), [
                'method' => $method,
                'path' => $path,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);
            $this->sendResponse(500, ['error' => 'Internal server error']);
        }
    }

    /**
     * Authenticate API request
     */
    private function authenticateRequest() {
        // Check for API key authentication
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

        if ($apiKey) {
            return $this->authenticateWithApiKey($apiKey);
        }

        // Check for session authentication
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
    }

    /**
     * Authenticate with API key
     */
    private function authenticateWithApiKey($apiKey) {
        // In a real implementation, validate API key against database
        // For now, accept any non-empty API key for demonstration
        return !empty($apiKey);
    }

    /**
     * Handle GET requests
     */
    private function handleGET($pathParts) {
        $resource = $pathParts[0] ?? '';

        switch ($resource) {
            case 'students':
                $this->getStudents($pathParts);
                break;
            case 'departments':
                $this->getDepartments();
                break;
            case 'options':
                $this->getOptions($pathParts);
                break;
            case 'stats':
                $this->getStatistics();
                break;
            case 'audit':
                $this->getAuditTrail($pathParts);
                break;
            default:
                $this->sendResponse(404, ['error' => 'Resource not found']);
        }
    }

    /**
     * Handle POST requests
     */
    private function handlePOST($pathParts) {
        $resource = $pathParts[0] ?? '';

        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        switch ($resource) {
            case 'students':
                $this->createStudent($data);
                break;
            case 'validate':
                $this->validateData($data);
                break;
            case 'duplicate-check':
                $this->checkDuplicate($data);
                break;
            case 'backup':
                $this->createBackup($data);
                break;
            default:
                $this->sendResponse(404, ['error' => 'Resource not found']);
        }
    }

    /**
     * Handle PUT requests
     */
    private function handlePUT($pathParts) {
        $resource = $pathParts[0] ?? '';

        if (count($pathParts) < 2) {
            $this->sendResponse(400, ['error' => 'Resource ID required']);
            return;
        }

        $id = $pathParts[1];
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;

        switch ($resource) {
            case 'students':
                $this->updateStudent($id, $data);
                break;
            default:
                $this->sendResponse(404, ['error' => 'Resource not found']);
        }
    }

    /**
     * Handle DELETE requests
     */
    private function handleDELETE($pathParts) {
        $resource = $pathParts[0] ?? '';

        if (count($pathParts) < 2) {
            $this->sendResponse(400, ['error' => 'Resource ID required']);
            return;
        }

        $id = $pathParts[1];

        switch ($resource) {
            case 'students':
                $this->deleteStudent($id);
                break;
            default:
                $this->sendResponse(404, ['error' => 'Resource not found']);
        }
    }

    /**
     * Get students with filtering and pagination
     */
    private function getStudents($pathParts) {
        $params = [];

        // Build query with filters
        $whereConditions = [];
        $allowedFilters = ['department_id', 'year_level', 'status', 'reg_no', 'email'];

        foreach ($_GET as $key => $value) {
            if (in_array($key, $allowedFilters) && !empty($value)) {
                $whereConditions[] = "{$key} = ?";
                $params[] = $value;
            }
        }

        $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);

        // Pagination
        $page = (int)($_GET['page'] ?? 1);
        $perPage = min((int)($_GET['per_page'] ?? 20), 100); // Max 100 per page

        $sql = "SELECT s.*, u.username, d.name as department_name, o.name as option_name
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN options o ON s.option_id = o.id
                {$whereClause}
                ORDER BY s.created_at DESC";

        $result = $this->db->paginate($sql, $params, $page, $perPage, 'Get students');

        $this->sendResponse(200, [
            'success' => true,
            'data' => $result['data'],
            'pagination' => $result['pagination']
        ]);
    }

    /**
     * Get departments
     */
    private function getDepartments() {
        $sql = "SELECT * FROM departments WHERE status = 'active' ORDER BY name";
        $departments = $this->db->findAll($sql, [], 'Get departments');

        $this->sendResponse(200, [
            'success' => true,
            'data' => $departments
        ]);
    }

    /**
     * Get options for a department
     */
    private function getOptions($pathParts) {
        if (count($pathParts) < 2) {
            $this->sendResponse(400, ['error' => 'Department ID required']);
            return;
        }

        $departmentId = (int)$pathParts[1];

        $sql = "SELECT * FROM options WHERE department_id = ? ORDER BY name";
        $options = $this->db->findAll($sql, [$departmentId], 'Get options');

        $this->sendResponse(200, [
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * Get system statistics
     */
    private function getStatistics() {
        try {
            // Student statistics
            $studentStats = $this->db->findAll("
                SELECT
                    COUNT(*) as total_students,
                    COUNT(CASE WHEN year_level = '1' THEN 1 END) as year_1,
                    COUNT(CASE WHEN year_level = '2' THEN 1 END) as year_2,
                    COUNT(CASE WHEN year_level = '3' THEN 1 END) as year_3,
                    COUNT(CASE WHEN year_level = '4' THEN 1 END) as year_4,
                    COUNT(CASE WHEN sex = 'Male' THEN 1 END) as male,
                    COUNT(CASE WHEN sex = 'Female' THEN 1 END) as female
                FROM students
                WHERE status = 'active'
            ")[0];

            // Department statistics
            $departmentStats = $this->db->findAll("
                SELECT d.name, COUNT(s.id) as student_count
                FROM departments d
                LEFT JOIN students s ON d.id = s.department_id AND s.status = 'active'
                GROUP BY d.id, d.name
                ORDER BY student_count DESC
            ");

            $this->sendResponse(200, [
                'success' => true,
                'data' => [
                    'students' => $studentStats,
                    'departments' => $departmentStats,
                    'generated_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } catch (Exception $e) {
            $this->sendResponse(500, ['error' => 'Failed to retrieve statistics']);
        }
    }

    /**
     * Get audit trail
     */
    private function getAuditTrail($pathParts) {
        $limit = (int)($_GET['limit'] ?? 50);
        $table = $_GET['table'] ?? null;

        $sql = "SELECT * FROM audit_trail";
        $params = [];

        if ($table) {
            $sql .= " WHERE table_name = ?";
            $params[] = $table;
        }

        $sql .= " ORDER BY timestamp DESC LIMIT ?";
        $params[] = $limit;

        $auditTrail = $this->db->findAll($sql, $params, 'Get audit trail');

        $this->sendResponse(200, [
            'success' => true,
            'data' => $auditTrail
        ]);
    }

    /**
     * Create new student
     */
    private function createStudent($data) {
        // Validate input data
        $this->validator->setData($data);

        $validation = $this->validator
            ->required([
                'first_name', 'last_name', 'email', 'reg_no', 'student_id_number',
                'department_id', 'option_id', 'telephone', 'year_level', 'sex',
                'dob', 'cell', 'sector', 'province', 'parent_first_name',
                'parent_last_name', 'parent_contact'
            ])
            ->email('email', true)
            ->phone('telephone', 'strict')
            ->length('first_name', 2, 50)
            ->length('last_name', 2, 50)
            ->length('reg_no', 5, 20)
            ->length('telephone', 10, 15)
            ->length('cell', 2, 100)
            ->length('sector', 2, 100)
            ->length('province', 2, 100)
            ->length('parent_first_name', 2, 50)
            ->length('parent_last_name', 2, 50)
            ->length('parent_contact', 10, 20)
            ->alphaNumeric('reg_no', true)
            ->studentIdNumber('student_id_number')
            ->rwandaLocation('province', 'province')
            ->rwandaLocation('sector', 'sector')
            ->rwandaLocation('cell', 'cell')
            ->parentContact('parent_contact')
            ->studentAge('dob', 15, 25)
            ->date('dob', 'Y-m-d')
            ->noSpecialChars(['first_name', 'last_name', 'parent_first_name', 'parent_last_name']);

        if ($this->validator->fails()) {
            $this->sendResponse(400, [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $this->validator->getErrors()
            ]);
            return;
        }

        try {
            $this->db->beginTransaction();

            // Check for duplicates
            $this->checkDuplicates($data['email'], $data['reg_no'], $data['student_id_number']);

            // Create user account
            $userId = $this->createUserAccount($data);

            // Create student record
            $studentData = [
                'user_id' => $userId,
                'option_id' => (int)$data['option_id'],
                'year_level' => $data['year_level'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'dob' => $data['dob'],
                'cell' => $data['cell'],
                'sector' => $data['sector'],
                'province' => $data['province'],
                'parent_first_name' => $data['parent_first_name'],
                'parent_last_name' => $data['parent_last_name'],
                'parent_contact' => $data['parent_contact'],
                'email' => $data['email'],
                'reg_no' => $data['reg_no'],
                'student_id_number' => $data['student_id_number'],
                'department_id' => (int)$data['department_id'],
                'telephone' => $data['telephone'],
                'sex' => $data['sex'],
                'photo' => $data['photo'] ?? null,
                'fingerprint' => $data['fingerprint'] ?? null,
                'password' => password_hash('Welcome123!', PASSWORD_ARGON2ID)
            ];

            $studentId = $this->db->insert('students', $studentData, $_SESSION['user_id'] ?? null);

            $this->db->commit();

            $this->logger->info('StudentRegistrationAPI', 'Student created successfully', [
                'student_id' => $studentId,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            $this->sendResponse(201, [
                'success' => true,
                'message' => 'Student created successfully',
                'data' => ['id' => $studentId]
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('StudentRegistrationAPI', 'Failed to create student: ' . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to create student']);
        }
    }

    /**
     * Update student
     */
    private function updateStudent($id, $data) {
        // Validate input data
        $this->validator->setData($data);

        $validation = $this->validator
            ->length('first_name', 2, 50)
            ->length('last_name', 2, 50)
            ->length('reg_no', 5, 20)
            ->length('telephone', 10, 15)
            ->email('email', true)
            ->phone('telephone', 'strict');

        if ($this->validator->fails()) {
            $this->sendResponse(400, [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $this->validator->getErrors()
            ]);
            return;
        }

        try {
            $updateData = [];
            $allowedFields = ['first_name', 'last_name', 'email', 'reg_no', 'department_id', 'option_id', 'year_level', 'telephone', 'sex', 'photo', 'fingerprint', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            if (empty($updateData)) {
                $this->sendResponse(400, ['error' => 'No valid fields to update']);
                return;
            }

            $conditions = ['id' => $id];
            $rowsAffected = $this->db->update('students', $updateData, $conditions, [$id], $_SESSION['user_id'] ?? null);

            if ($rowsAffected > 0) {
                $this->sendResponse(200, [
                    'success' => true,
                    'message' => 'Student updated successfully'
                ]);
            } else {
                $this->sendResponse(404, ['error' => 'Student not found']);
            }

        } catch (Exception $e) {
            $this->logger->error('StudentRegistrationAPI', 'Failed to update student: ' . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to update student']);
        }
    }

    /**
     * Delete student
     */
    private function deleteStudent($id) {
        try {
            $conditions = ['id' => $id];
            $rowsAffected = $this->db->delete('students', $conditions, [$id], $_SESSION['user_id'] ?? null);

            if ($rowsAffected > 0) {
                $this->sendResponse(200, [
                    'success' => true,
                    'message' => 'Student deleted successfully'
                ]);
            } else {
                $this->sendResponse(404, ['error' => 'Student not found']);
            }

        } catch (Exception $e) {
            $this->logger->error('StudentRegistrationAPI', 'Failed to delete student: ' . $e->getMessage());
            $this->sendResponse(500, ['error' => 'Failed to delete student']);
        }
    }

    /**
     * Validate data
     */
    private function validateData($data) {
        $this->validator->setData($data);

        $field = $data['field'] ?? '';
        $value = $data['value'] ?? '';

        if (empty($field) || empty($value)) {
            $this->sendResponse(400, ['error' => 'Field and value are required']);
            return;
        }

        $allowedFields = ['email', 'reg_no', 'telephone'];
        if (!in_array($field, $allowedFields)) {
            $this->sendResponse(400, ['error' => 'Invalid field for validation']);
            return;
        }

        // Check for duplicates
        $table = ($field === 'email') ? 'users' : 'students';
        $column = ($field === 'email') ? 'email' : $field;

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
        $result = $this->db->findOne($sql, [$value], 'Check duplicate');

        $exists = $result['COUNT(*)'] > 0;

        $this->sendResponse(200, [
            'success' => true,
            'valid' => !$exists,
            'exists' => $exists,
            'message' => $exists ? ucfirst(str_replace('_', ' ', $field)) . ' already exists' : ''
        ]);
    }

    /**
     * Check for duplicates
     */
    private function checkDuplicate($data) {
        $field = $data['field'] ?? '';
        $value = $data['value'] ?? '';

        if (empty($field) || empty($value)) {
            $this->sendResponse(400, ['error' => 'Field and value are required']);
            return;
        }

        $this->validateData($data);
    }

    /**
     * Create user account
     */
    private function createUserAccount($data) {
        $username = $this->generateUsername($data['first_name'], $data['last_name']);
        $password = password_hash('Welcome123!', PASSWORD_ARGON2ID);

        $userData = [
            'username' => $username,
            'email' => $data['email'],
            'password' => $password,
            'role' => 'student'
        ];

        return $this->db->insert('users', $userData, $_SESSION['user_id'] ?? null);
    }

    /**
     * Generate unique username
     */
    private function generateUsername($firstName, $lastName) {
        $baseUsername = strtolower($firstName . '.' . $lastName);
        $baseUsername = preg_replace('/[^a-z0-9.]/', '', $baseUsername);

        $username = $baseUsername;
        $counter = 1;

        while (true) {
            $result = $this->db->findOne("SELECT COUNT(*) FROM users WHERE username = ?", [$username], 'Check username');
            if ($result['COUNT(*)'] == 0) {
                break;
            }
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Check for duplicate records
     */
    private function checkDuplicates($email, $regNo, $studentIdNumber) {
        // Check email in users table
        $result = $this->db->findOne("SELECT COUNT(*) FROM users WHERE email = ?", [$email], 'Check email duplicate');
        if ($result['COUNT(*)'] > 0) {
            throw new Exception('Email already exists');
        }

        // Check registration number in students table
        $result = $this->db->findOne("SELECT COUNT(*) FROM students WHERE reg_no = ?", [$regNo], 'Check reg_no duplicate');
        if ($result['COUNT(*)'] > 0) {
            throw new Exception('Registration number already exists');
        }

        // Check student ID number in students table
        $result = $this->db->findOne("SELECT COUNT(*) FROM students WHERE student_id_number = ?", [$studentIdNumber], 'Check student_id_number duplicate');
        if ($result['COUNT(*)'] > 0) {
            throw new Exception('Student ID number already exists');
        }
    }

    /**
     * Create backup
     */
    private function createBackup($data) {
        $tables = $data['tables'] ?? ['students', 'users', 'departments', 'options'];

        $backupResults = [];

        foreach ($tables as $table) {
            try {
                $backupTable = $this->db->backupTable($table);
                $backupResults[] = [
                    'table' => $table,
                    'backup_table' => $backupTable,
                    'success' => true
                ];
            } catch (Exception $e) {
                $backupResults[] = [
                    'table' => $table,
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }

        $this->sendResponse(200, [
            'success' => true,
            'message' => 'Backup completed',
            'data' => $backupResults
        ]);
    }

    /**
     * Send API response
     */
    private function sendResponse($statusCode, $data) {
        http_response_code($statusCode);
        header('Content-Type: application/json');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Log API response
        $this->logger->info('StudentRegistrationAPI', 'API Response', [
            'status_code' => $statusCode,
            'response_size' => strlen(json_encode($data)),
            'user_id' => $_SESSION['user_id'] ?? null
        ]);

        exit;
    }
}

// Initialize API
try {
    $api = new StudentRegistrationAPI($pdo, $logger ?? null);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'API initialization failed']);
    exit;
}