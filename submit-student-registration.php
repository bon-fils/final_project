<?php
/**
 * Enhanced Student Registration Submission Handler
 * Processes student registration form data with improved security and performance
 * Version: 2.0
 */

require_once 'config.php';
require_once 'security_utils.php';
require_once 'backend/classes/Logger.php';
require_once 'backend/classes/DataSanitizer.php';

// Fallback DataSanitizer class if the file doesn't exist
if (!class_exists('DataSanitizer')) {
    class DataSanitizer {
        public static function string($value) {
            return trim(filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES));
        }

        public static function email($value) {
            return filter_var(trim($value), FILTER_SANITIZE_EMAIL);
        }
    }
}

// Rate limiting for registration attempts
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_key = "student_registration_{$client_ip}";
if (!SecurityUtils::checkRateLimit($rate_limit_key, 5, 300)) { // 5 attempts per 5 minutes
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many registration attempts. Please try again later.',
        'retry_after' => 300
    ]);
    exit;
}

// Allow demo access for student registration when called from registration page
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isFromRegistration = strpos($referer, 'register-student.php') !== false;

if (!$isFromRegistration) {
    require_once 'session_check.php';
} else {
    // For registration page access, just start session (CSRF token should already be set by the registration page)
    session_start();
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Set execution time limit for file processing
set_time_limit(120);

try {
    // Initialize logger with enhanced configuration
    $logger = new Logger('logs/student_registration.log', Logger::INFO);

    // Log registration attempt
    $logger->info('StudentRegistration', 'Registration attempt started', [
        'ip' => $client_ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer' => $referer,
        'is_from_registration_page' => $isFromRegistration
    ]);

    // Process the registration
    $result = processStudentRegistration($pdo, $logger, $isFromRegistration);
    echo json_encode($result);

} catch (Exception $e) {
    handleRegistrationError($e, $logger ?? null);
}

/**
 * Enhanced main function to process student registration with performance monitoring
 */
function processStudentRegistration($pdo, $logger, $isFromRegistration) {
    $registrationId = uniqid('reg_', true);
    $startTime = microtime(true);

    $logger->info('StudentRegistration', 'Starting student registration process', [
        'registration_id' => $registrationId,
        'is_from_registration_page' => $isFromRegistration,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    try {
        // Step 1: CSRF validation
        $csrfStart = microtime(true);
        if (!$isFromRegistration && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
            $logger->warning('StudentRegistration', 'CSRF token validation failed', [
                'registration_id' => $registrationId,
                'has_token' => isset($_POST['csrf_token']),
                'token_length' => isset($_POST['csrf_token']) ? strlen($_POST['csrf_token']) : 0
            ]);
            return createErrorResponse('Security validation failed. Please refresh the page and try again.', 403);
        }
        $csrfTime = microtime(true) - $csrfStart;

        // Step 2: Input validation
        $validationStart = microtime(true);
        $validationResult = validateStudentInput($_POST, $logger);
        if (!$validationResult['valid']) {
            $logger->warning('StudentRegistration', 'Input validation failed', [
                'registration_id' => $registrationId,
                'validation_time_ms' => round((microtime(true) - $validationStart) * 1000, 2)
            ]);
            return $validationResult['response'];
        }
        $studentData = $validationResult['data'];
        $validationTime = microtime(true) - $validationStart;

        // Step 3: Duplicate check
        $duplicateStart = microtime(true);
        $duplicateCheck = checkForDuplicateStudent($pdo, $studentData, $logger);
        if ($duplicateCheck) {
            $logger->warning('StudentRegistration', 'Duplicate student detected', [
                'registration_id' => $registrationId,
                'email' => $studentData['email'],
                'reg_no' => $studentData['reg_no'],
                'duplicate_check_time_ms' => round((microtime(true) - $duplicateStart) * 1000, 2)
            ]);
            return $duplicateCheck;
        }
        $duplicateTime = microtime(true) - $duplicateStart;

        // Step 4: Relationship validation
        $relationshipStart = microtime(true);
        $relationshipCheck = validateDepartmentOptionRelationship($pdo, $studentData, $logger);
        if ($relationshipCheck) {
            $logger->warning('StudentRegistration', 'Department-option relationship validation failed', [
                'registration_id' => $registrationId,
                'department_id' => $studentData['department_id'],
                'option_id' => $studentData['option_id']
            ]);
            return $relationshipCheck;
        }
        $relationshipTime = microtime(true) - $relationshipStart;

        // Step 5: File upload handling
        $uploadStart = microtime(true);
        $faceImagePaths = handleFaceImagesUploadSafely($_FILES['face_images'] ?? null, $logger);
        $fingerprintData = handleFingerprintDataSafely($_POST, $studentData['reg_no']);
        $uploadTime = microtime(true) - $uploadStart;

        // Step 6: Database record creation
        $dbStart = microtime(true);
        $result = createStudentRecords($pdo, $studentData, $faceImagePaths, $fingerprintData, $logger, $isFromRegistration);
        $dbTime = microtime(true) - $dbStart;

        // Log successful registration with performance metrics
        $totalTime = microtime(true) - $startTime;
        $logger->info('StudentRegistration', 'Student registration completed successfully', [
            'registration_id' => $registrationId,
            'student_name' => $studentData['first_name'] . ' ' . $studentData['last_name'],
            'reg_no' => $studentData['reg_no'],
            'email' => $studentData['email'],
            'performance_metrics' => [
                'total_time_ms' => round($totalTime * 1000, 2),
                'csrf_time_ms' => round($csrfTime * 1000, 2),
                'validation_time_ms' => round($validationTime * 1000, 2),
                'duplicate_check_time_ms' => round($duplicateTime * 1000, 2),
                'relationship_check_time_ms' => round($relationshipTime * 1000, 2),
                'upload_time_ms' => round($uploadTime * 1000, 2),
                'database_time_ms' => round($dbTime * 1000, 2),
                'face_images_count' => count($faceImagePaths),
                'fingerprint_enrolled' => !empty($fingerprintData['path'])
            ]
        ]);

        return $result;

    } catch (Exception $e) {
        $totalTime = microtime(true) - $startTime;
        $logger->error('StudentRegistration', 'Student registration failed', [
            'registration_id' => $registrationId,
            'error' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'total_time_ms' => round($totalTime * 1000, 2),
            'email' => $studentData['email'] ?? 'unknown',
            'reg_no' => $studentData['reg_no'] ?? 'unknown'
        ]);
        throw $e;
    }
}

/**
 * Enhanced validation for student input data with improved security
 */
function validateStudentInput($postData, $logger) {
    // Log validation attempt
    $logger->info('StudentRegistration', 'Starting input validation', [
        'fields_count' => count($postData),
        'has_files' => isset($_FILES['face_images'])
    ]);

    // Check if InputValidator class exists, fallback to manual validation
    if (class_exists('InputValidator')) {
        $validator = new InputValidator($postData);

        // Required field validations with enhanced security
        $validator
            ->required(['first_name', 'last_name', 'email', 'telephone', 'department_id', 'option_id', 'reg_no', 'sex', 'year_level'])
            ->length('first_name', 2, 50)
            ->length('last_name', 2, 50)
            ->email('email')
            ->phone('telephone')
            ->length('reg_no', 5, 20)
            ->custom('reg_no', function($v) {
                // Additional validation for registration number format
                return preg_match('/^[A-Za-z0-9_-]{5,20}$/', $v);
            }, 'Registration number must contain only letters, numbers, underscores, and hyphens (5-20 characters)')
            ->custom('department_id', fn($v) => is_numeric($v) && (int)$v > 0, 'Please select a valid department')
            ->custom('option_id', fn($v) => is_numeric($v) && (int)$v > 0, 'Please select a valid program')
            ->custom('year_level', fn($v) => in_array((string)$v, ['1','2','3']), 'Please select a valid year level')
            ->custom('sex', fn($v) => in_array($v, ['Male','Female','Other']), 'Please select a valid gender');
    } else {
        // Fallback manual validation
        $errors = [];

        // Required fields check
        $requiredFields = ['first_name', 'last_name', 'email', 'telephone', 'department_id', 'option_id', 'reg_no', 'sex', 'year_level'];
        foreach ($requiredFields as $field) {
            if (empty(trim($postData[$field] ?? ''))) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Length validations
        if (isset($postData['first_name']) && (strlen($postData['first_name']) < 2 || strlen($postData['first_name']) > 50)) {
            $errors['first_name'] = 'First name must be between 2 and 50 characters';
        }
        if (isset($postData['last_name']) && (strlen($postData['last_name']) < 2 || strlen($postData['last_name']) > 50)) {
            $errors['last_name'] = 'Last name must be between 2 and 50 characters';
        }

        // Email validation
        if (isset($postData['email']) && !filter_var($postData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address';
        }

        // Phone validation (basic)
        if (isset($postData['phone']) && !preg_match('/^0\d{9}$/', $postData['phone'])) {
            $errors['telephone'] = 'Phone number must be exactly 10 digits starting with 0';
        }

        // Registration number validation
        if (isset($postData['reg_no'])) {
            if (strlen($postData['reg_no']) < 5 || strlen($postData['reg_no']) > 20) {
                $errors['reg_no'] = 'Registration number must be 5-20 characters';
            } elseif (!preg_match('/^[A-Za-z0-9_-]{5,20}$/', $postData['reg_no'])) {
                $errors['reg_no'] = 'Registration number must contain only letters, numbers, underscores, and hyphens';
            }
        }

        // Department and option validation
        if (isset($postData['department_id']) && (!is_numeric($postData['department_id']) || (int)$postData['department_id'] <= 0)) {
            $errors['department_id'] = 'Please select a valid department';
        }
        if (isset($postData['option_id']) && (!is_numeric($postData['option_id']) || (int)$postData['option_id'] <= 0)) {
            $errors['option_id'] = 'Please select a valid program';
        }

        // Year level validation
        if (isset($postData['year_level']) && !in_array((string)$postData['year_level'], ['1','2','3'])) {
            $errors['year_level'] = 'Please select a valid year level';
        }

        // Gender validation
        if (isset($postData['sex']) && !in_array($postData['sex'], ['Male','Female','Other'])) {
            $errors['sex'] = 'Please select a valid gender';
        }

        if (!empty($errors)) {
            $logger->warning('StudentRegistration', 'Validation failed for student registration', [
                'errors' => $errors,
                'error_count' => count($errors),
                'email' => $postData['email'] ?? 'unknown',
                'reg_no' => $postData['reg_no'] ?? 'unknown'
            ]);

            return [
                'valid' => false,
                'response' => createErrorResponse('Please correct the following errors:', 422, $errors)
            ];
        }

        // Skip the rest of the validation function since we did manual validation
        $validator = null; // Set to null to indicate manual validation was used
    }

    if ($validator) {
        if (!empty($postData['parent_contact'])) {
            $validator->phone('parent_contact');
        }
        if (!empty($postData['studentIdNumber'])) {
            $validator->custom('studentIdNumber', function($v) {
                return preg_match('/^\d{16}$/', $v);
            }, 'Student ID number must be exactly 16 digits');
        }
        if (!empty($postData['dob'])) {
            $validator->date('dob', 'Y-m-d');
            $dob = $postData['dob'];
            $birthDate = DateTime::createFromFormat('Y-m-d', $dob);
            $today = new DateTime();
            $age = $birthDate ? $today->diff($birthDate)->y : null;
            if (!$birthDate || $age < 16 || $age > 60) {
                $validator->custom('dob', fn() => false, 'Student must be between 16 and 60 years old');
            }
        }
    }

    // Validate name fields for potential XSS (only if validator exists)
    if ($validator) {
        $nameFields = ['first_name', 'last_name', 'parent_first_name', 'parent_last_name'];
        foreach ($nameFields as $field) {
            if (!empty($postData[$field])) {
                $validator->custom($field, function($v) {
                    // Check for suspicious patterns
                    return !preg_match('/[<>\"\'&]/', $v);
                }, "Invalid characters in {$field}");
            }
        }
    }

    if ($validator && $validator->fails()) {
        $errors = $validator->errors();
        $logger->warning('StudentRegistration', 'Validation failed for student registration', [
            'errors' => $errors,
            'error_count' => count($errors),
            'email' => $postData['email'] ?? 'unknown',
            'reg_no' => $postData['reg_no'] ?? 'unknown'
        ]);

        return [
            'valid' => false,
            'response' => createErrorResponse('Please correct the following errors:', 422, $errors)
        ];
    }

    // Enhanced data sanitization with additional security measures
    $studentData = [
        'first_name' => trim($postData['first_name']),
        'last_name' => trim($postData['last_name']),
        'email' => filter_var(trim($postData['email']), FILTER_SANITIZE_EMAIL),
        'telephone' => trim($postData['telephone']),
        'department_id' => (int)$postData['department_id'],
        'option_id' => (int)$postData['option_id'],
        'reg_no' => strtoupper(trim($postData['reg_no'])), // Normalize to uppercase
        'student_id' => trim($postData['studentIdNumber'] ?? ''),
        'parent_first_name' => trim($postData['parent_first_name'] ?? ''),
        'parent_last_name' => trim($postData['parent_last_name'] ?? ''),
        'parent_contact' => trim($postData['parent_contact'] ?? ''),
        'dob' => trim($postData['dob'] ?? ''),
        'sex' => trim($postData['sex'] ?? ''),
        'year_level' => (int)($postData['year_level'] ?? 1)
    ];

    $logger->info('StudentRegistration', 'Input validation passed', [
        'student_data_sanitized' => true,
        'reg_no' => $studentData['reg_no'],
        'email' => $studentData['email']
    ]);

    return ['valid' => true, 'data' => $studentData];
}

/**
 * Enhanced duplicate student check with optimized queries
 */
function checkForDuplicateStudent($pdo, $studentData, $logger) {
    $startTime = microtime(true);

    try {
        // Check for duplicates using separate queries for better error handling
        $emailCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $emailCount->execute([$studentData['email']]);
        $email_count = $emailCount->fetchColumn();

        $usernameCount = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $usernameCount->execute([$studentData['reg_no']]);
        $username_count = $usernameCount->fetchColumn();

        $studentRegnoCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE reg_no = ?");
        $studentRegnoCount->execute([$studentData['reg_no']]);
        $student_regno_count = $studentRegnoCount->fetchColumn();

        $result = [
            'email_count' => $email_count,
            'username_count' => $username_count,
            'student_regno_count' => $student_regno_count
        ];

        $checkTime = microtime(true) - $startTime;

        // Log the duplicate check results
        $logger->info('StudentRegistration', 'Duplicate check completed', [
            'email' => $studentData['email'],
            'reg_no' => $studentData['reg_no'],
            'email_count' => $result['email_count'],
            'username_count' => $result['username_count'],
            'student_regno_count' => $result['student_regno_count'],
            'check_time_ms' => round($checkTime * 1000, 2)
        ]);

        $userEmailExists = $result['email_count'] > 0;
        $userRegNoExists = $result['username_count'] > 0;
        $studentRegNoExists = $result['student_regno_count'] > 0;

        if ($userEmailExists || $userRegNoExists || $studentRegNoExists) {
            $duplicateTypes = [];
            if ($userEmailExists) $duplicateTypes[] = 'email address';
            if ($userRegNoExists || $studentRegNoExists) $duplicateTypes[] = 'registration number';

            $duplicateType = implode(' and ', $duplicateTypes);

            $logger->warning('StudentRegistration', 'Duplicate student registration attempt blocked', [
                'email' => $studentData['email'],
                'reg_no' => $studentData['reg_no'],
                'duplicate_type' => $duplicateType,
                'user_email_exists' => $userEmailExists,
                'user_regno_exists' => $userRegNoExists,
                'student_regno_exists' => $studentRegNoExists,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            return createErrorResponse("A student with this $duplicateType already exists in the system.", 409);
        }

        return null;
    } catch (PDOException $e) {
        $logger->error('StudentRegistration', 'Database error during duplicate check', [
            'error' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'email' => $studentData['email'],
            'reg_no' => $studentData['reg_no'],
            'check_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);
        throw new Exception('Database error during registration validation. Please try again.');
    }
}

/**
 * Validate department and option relationships
 */
function validateDepartmentOptionRelationship($pdo, $studentData, $logger) {
    // Validate department and option in a single query for better performance
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM departments WHERE id = ?) as dept_exists,
            (SELECT COUNT(*) FROM options WHERE id = ? AND department_id = ?) as option_exists
    ");
    $stmt->execute([
        $studentData['department_id'],
        $studentData['option_id'],
        $studentData['department_id']
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['dept_exists'] == 0) {
        $logger->warning('StudentRegistration', 'Invalid department ID', [
            'department_id' => $studentData['department_id']
        ]);
        return createErrorResponse('Invalid department selected.', 400);
    }

    if ($result['option_exists'] == 0) {
        $logger->warning('StudentRegistration', 'Invalid program option', [
            'option_id' => $studentData['option_id'],
            'department_id' => $studentData['department_id']
        ]);
        return createErrorResponse('Invalid program selected for the chosen department.', 400);
    }

    return null;
}

/**
 * Enhanced face images upload with improved security and validation
 */
function handleFaceImagesUploadSafely($files, $logger = null) {
    if (!$files || !is_array($files['name'])) {
        return [];
    }

    $uploadedPaths = [];
    $maxFiles = 5;
    $minFiles = 2;
    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    // Count valid files
    $fileCount = count(array_filter($files['name'], function($name) {
        return !empty(trim($name));
    }));

    if ($fileCount < $minFiles) {
        throw new Exception("At least $minFiles face images are required for recognition.");
    }
    if ($fileCount > $maxFiles) {
        throw new Exception("Maximum $maxFiles face images allowed.");
    }

    $logger && $logger->info('StudentRegistration', 'Starting face image upload', [
        'file_count' => $fileCount,
        'max_files' => $maxFiles,
        'min_files' => $minFiles
    ]);

    // Process each file with enhanced validation
    foreach ($files['name'] as $index => $filename) {
        if (empty(trim($filename))) continue;

        $file = [
            'name' => $files['name'][$index],
            'type' => $files['type'][$index],
            'tmp_name' => $files['tmp_name'][$index],
            'error' => $files['error'][$index],
            'size' => $files['size'][$index]
        ];

        // Enhanced file validation
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMsg = getUploadErrorMessage($file['error']);
            $logger && $logger->warning('StudentRegistration', 'File upload error', [
                'filename' => $filename,
                'error_code' => $file['error'],
                'error_message' => $errorMsg
            ]);
            continue; // Skip this file but continue with others
        }

        // Validate file size
        if ($file['size'] > $maxFileSize) {
            $logger && $logger->warning('StudentRegistration', 'File too large', [
                'filename' => $filename,
                'size' => $file['size'],
                'max_size' => $maxFileSize
            ]);
            continue;
        }

        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            $logger && $logger->warning('StudentRegistration', 'Invalid file type', [
                'filename' => $filename,
                'type' => $file['type'],
                'allowed_types' => $allowedTypes
            ]);
            continue;
        }

        // Additional security: check file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($extension, $allowedExtensions)) {
            $logger && $logger->warning('StudentRegistration', 'Invalid file extension', [
                'filename' => $filename,
                'extension' => $extension
            ]);
            continue;
        }

        try {
            $path = handlePhotoUpload($file);
            if ($path) {
                $uploadedPaths[] = $path;
                $logger && $logger->info('StudentRegistration', 'Face image uploaded successfully', [
                    'filename' => $filename,
                    'path' => $path,
                    'size' => $file['size']
                ]);
            }
        } catch (Exception $e) {
            $logger && $logger->error('StudentRegistration', 'Failed to process face image', [
                'filename' => $filename,
                'error' => $e->getMessage()
            ]);
            // Continue with other files
        }
    }

    $successfulUploads = count($uploadedPaths);

    if ($successfulUploads < $minFiles) {
        // Clean up uploaded files if we don't have enough
        foreach ($uploadedPaths as $path) {
            if (file_exists($path)) {
                unlink($path);
                $logger && $logger->info('StudentRegistration', 'Cleaned up uploaded file', ['path' => $path]);
            }
        }
        throw new Exception("Failed to upload enough valid face images. Successfully uploaded $successfulUploads, but at least $minFiles images required.");
    }

    $logger && $logger->info('StudentRegistration', 'Face image upload completed', [
        'total_files' => $fileCount,
        'successful_uploads' => $successfulUploads,
        'uploaded_paths' => $uploadedPaths
    ]);

    return $uploadedPaths;
}

/**
 * Get human-readable upload error message
 */
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File size exceeds maximum allowed size';
        case UPLOAD_ERR_PARTIAL:
            return 'File was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension';
        default:
            return 'Unknown upload error';
    }
}

/**
 * Handle fingerprint data safely
 */
function handleFingerprintDataSafely($postData, $regNo) {
    // Check if fingerprint is enrolled
    if (empty($postData['fingerprint_enrolled']) || $postData['fingerprint_enrolled'] !== 'true') {
        return ['path' => null, 'quality' => 0, 'id' => null, 'enrolled' => false];
    }

    // Process fingerprint image if available
    $fingerprintPath = null;
    if (!empty($postData['fingerprint_image'])) {
        $fingerprintPath = processFingerprintData($postData['fingerprint_image'], $regNo);
    }

    return [
        'path' => $fingerprintPath['path'] ?? null,
        'quality' => (int)($postData['fingerprint_quality'] ?? 0),
        'id' => $postData['fingerprint_id'] ?? null,
        'template' => $postData['fingerprint_template'] ?? null,
        'hash' => $postData['fingerprint_hash'] ?? null,
        'enrolled_at' => $postData['fingerprint_enrolled_at'] ?? null,
        'enrolled' => true
    ];
}


/**
 * Create user and student records
 */
function createStudentRecords($pdo, $studentData, $faceImagePaths, $fingerprintData, $logger, $isFromRegistration) {
    $recordStartTime = microtime(true);

    try {
        // Begin transaction before any inserts
        $pdo->beginTransaction();

        // Create user account first
        $defaultPassword = password_hash('12345', PASSWORD_BCRYPT);

        $insertUser = $pdo->prepare("
            INSERT INTO users (username, email, password, role, status, first_name, last_name, phone, sex, dob, created_at)
            VALUES (:username, :email, :password, 'student', 'active', :first_name, :last_name, :phone, :sex, :dob, NOW())
        ");
        $insertUser->execute([
            ':username' => $studentData['reg_no'],
            ':email' => $studentData['email'],
            ':password' => $defaultPassword,
            ':first_name' => $studentData['first_name'],
            ':last_name' => $studentData['last_name'],
            ':phone' => $studentData['telephone'],
            ':sex' => $studentData['sex'],
            ':dob' => $studentData['dob']
        ]);
        $userId = $pdo->lastInsertId();

        // Prepare detailed biometric data JSON matching schema with quality validation
        $faceImages = [];
        $currentTime = date('Y-m-d\TH:i:s\Z');
        $totalQualityScore = 0;

        foreach ($faceImagePaths as $index => $path) {
            // Basic quality assessment based on file size (rough estimation)
            $fileSize = filesize($path);
            $qualityScore = min(1.0, max(0.5, $fileSize / 100000)); // Rough quality based on file size

            $templateId = 'face_' . uniqid() . '_' . ($index + 1);
            $faceImage = [
                'id' => $index + 1,
                'template_id' => $templateId,
                'image_path' => $path,
                'image_type' => 'face_recognition',
                'image_order' => $index + 1,
                'quality_score' => round($qualityScore, 2),
                'capture_angle' => $index === 0 ? 'front' : ($index === 1 ? 'slight_left' : ($index === 2 ? 'slight_right' : 'front')),
                'lighting_condition' => 'good',
                'facial_expression' => 'neutral',
                'file_size_bytes' => $fileSize,
                'created_at' => $currentTime,
                'updated_at' => $currentTime
            ];

            $faceImages[] = $faceImage;
            $totalQualityScore += $qualityScore;
        }

        $averageQuality = count($faceImages) > 0 ? round($totalQualityScore / count($faceImages), 2) : 0;

        // Log quality metrics
        $logger->info('BiometricQuality', 'Face image quality assessment', [
            'face_images_count' => count($faceImages),
            'average_quality_score' => $averageQuality,
            'quality_distribution' => array_column($faceImages, 'quality_score'),
            'min_quality' => count($faceImages) > 0 ? min(array_column($faceImages, 'quality_score')) : 0,
            'max_quality' => count($faceImages) > 0 ? max(array_column($faceImages, 'quality_score')) : 0
        ]);

        $biometricData = [
            'biometric_data' => [
                'face_images' => $faceImages,
                'fingerprint' => !empty($fingerprintData['path']) ? [
                    'path' => $fingerprintData['path'],
                    'quality' => $fingerprintData['quality']
                ] : null,
                'fingerprint_quality' => $fingerprintData['quality'] ?? 0,
                'has_biometric_data' => !empty($faceImages) || !empty($fingerprintData['path']),
                'biometric_types' => array_filter([
                    !empty($faceImages) ? 'face_recognition' : null,
                    !empty($fingerprintData['path']) ? 'fingerprint' : null
                ]),
                'face_templates_count' => count($faceImages),
                'face_quality_average' => $averageQuality,
                'primary_face_template' => !empty($faceImages) ? $faceImages[0]['template_id'] : null
            ],
            'metadata' => [
                'created_at' => $currentTime,
                'last_updated' => $currentTime,
                'version' => '1.0'
            ]
        ];

        // Insert student record (normalized - no redundant fields)
        $insertStudent = $pdo->prepare("
            INSERT INTO students (
                user_id, option_id, year_level, reg_no, student_id_number,
                fingerprint_path, fingerprint_quality, fingerprint_id, student_photos,
                department_id, parent_first_name, parent_last_name, parent_contact,
                status, fingerprint_status, fingerprint_enrolled_at
            ) VALUES (
                :user_id, :option_id, :year_level, :reg_no, :student_id,
                :fingerprint_path, :fingerprint_quality, :fingerprint_id, :student_photos,
                :department_id, :parent_first_name, :parent_last_name, :parent_contact,
                'active', :fingerprint_status, :fingerprint_enrolled_at
            )
        ");

        $insertStudent->execute([
            ':user_id' => $userId,
            ':option_id' => $studentData['option_id'],
            ':year_level' => (string)$studentData['year_level'],
            ':reg_no' => $studentData['reg_no'],
            ':student_id' => $studentData['student_id'],
            ':fingerprint_path' => $fingerprintData['path'],
            ':fingerprint_quality' => $fingerprintData['quality'],
            ':fingerprint_id' => $fingerprintData['id'],
            ':student_photos' => json_encode($biometricData),
            ':department_id' => $studentData['department_id'],
            ':parent_first_name' => $studentData['parent_first_name'],
            ':parent_last_name' => $studentData['parent_last_name'],
            ':parent_contact' => $studentData['parent_contact'],
            ':fingerprint_status' => $fingerprintData['enrolled'] ? 'enrolled' : 'not_enrolled',
            ':fingerprint_enrolled_at' => $fingerprintData['enrolled_at']
        ]);

        $studentId = $pdo->lastInsertId();


        // Create guardian record if guardian data provided (normalized)
        if (!empty($studentData['parent_first_name']) || !empty($studentData['parent_last_name'])) {
            $pdo->prepare("
                INSERT INTO guardians (student_id, first_name, last_name, contact, relationship)
                VALUES (?, ?, ?, ?, 'Parent/Guardian')
            ")->execute([
                $studentId,
                $studentData['parent_first_name'],
                $studentData['parent_last_name'],
                $studentData['parent_contact']
            ]);
        }

        // Enhanced logging with comprehensive registration details
        $registrationDetails = [
            'student_name' => $studentData['first_name'] . ' ' . $studentData['last_name'],
            'reg_no' => $studentData['reg_no'],
            'email' => $studentData['email'],
            'user_id' => $userId,
            'student_id' => $studentId,
            'department_id' => $studentData['department_id'],
            'option_id' => $studentData['option_id'],
            'year_level' => $studentData['year_level'],
            'registered_by' => $_SESSION['user_id'] ?? null,
            'registration_timestamp' => date('Y-m-d H:i:s'),
            'biometric_data_stored' => !empty($faceImagePaths) || $fingerprintData['enrolled'],
            'face_images_count' => count($faceImagePaths),
            'fingerprint_enrolled' => $fingerprintData['enrolled'] ?? false,
            'fingerprint_quality' => $fingerprintData['quality'] ?? 0,
            'fingerprint_status' => $fingerprintData['enrolled'] ? 'enrolled' : 'not_enrolled',
            'biometric_types' => array_filter([
                !empty($faceImagePaths) ? 'face_recognition' : null,
                $fingerprintData['enrolled'] ? 'fingerprint' : null
            ]),
            'guardian_info_provided' => !empty($studentData['parent_first_name']) || !empty($studentData['parent_last_name'])
        ];

        $logger->info('StudentRegistration', 'New student registered successfully', $registrationDetails);

        $pdo->commit();

        $recordTime = microtime(true) - $recordStartTime;

        // Create comprehensive success message
        $message = 'Student registered successfully!';
        $registrationSummary = [];

        if (!empty($faceImagePaths)) {
            $faceCount = count($faceImagePaths);
            $message .= " {$faceCount} face image" . ($faceCount > 1 ? 's' : '') . ' stored!';
            $registrationSummary[] = "Face Recognition: {$faceCount} images";
        }

        if ($fingerprintData['enrolled']) {
            $message .= ' Fingerprint enrolled successfully!';
            $registrationSummary[] = 'Fingerprint: Enrolled';
        }


        if (!empty($studentData['parent_first_name']) || !empty($studentData['parent_last_name'])) {
            $registrationSummary[] = 'Guardian: Registered';
        }

        // Log performance metrics
        $logger->info('StudentRegistration', 'Student record creation completed', [
            'student_id' => $studentId,
            'record_creation_time_ms' => round($recordTime * 1000, 2),
            'face_images_count' => count($faceImagePaths),
            'fingerprint_enrolled' => $fingerprintData['enrolled'] ?? false,
            'fingerprint_status' => $fingerprintData['enrolled'] ? 'enrolled' : 'not_enrolled',
            'guardian_registered' => !empty($studentData['parent_first_name']) || !empty($studentData['parent_last_name'])
        ]);

        return [
            'success' => true,
            'message' => $message,
            'student_id' => $studentId,
            'student_name' => $studentData['first_name'] . ' ' . $studentData['last_name'],
            'reg_no' => $studentData['reg_no'],
            'fingerprint_enrolled' => $fingerprintData['enrolled'] ?? false,
            'biometric_complete' => (!empty($faceImagePaths) || $fingerprintData['enrolled']),
            'registration_summary' => $registrationSummary,
            'redirect' => 'login.php',
            'performance' => [
                'record_creation_time_ms' => round($recordTime * 1000, 2),
                'total_registration_time_ms' => round((microtime(true) - $recordStartTime) * 1000, 2)
            ]
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Log detailed error information
        $logger->error('StudentRegistration', 'Database error during student creation', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'sql_state' => $e->errorInfo[0] ?? 'unknown',
            'driver_error' => $e->errorInfo[1] ?? 'unknown',
            'database_error' => $e->errorInfo[2] ?? 'unknown',
            'email' => $studentData['email'],
            'reg_no' => $studentData['reg_no'],
            'user_id' => $userId ?? 'not set',
            'student_data' => $studentData,
            'fingerprint_data' => $fingerprintData
        ]);

        // Handle specific database constraint violations
        if ($e->getCode() == 23000) { // Integrity constraint violation
            if (strpos($e->getMessage(), 'email') !== false) {
                return createErrorResponse('A user with this email address already exists. Please use a different email or contact support.', 409);
            } elseif (strpos($e->getMessage(), 'username') !== false) {
                return createErrorResponse('A user with this registration number already exists. Please verify your registration number.', 409);
            } elseif (strpos($e->getMessage(), 'option_id') !== false) {
                return createErrorResponse('Invalid program selected. Please select a valid program from the list.', 400);
            } elseif (strpos($e->getMessage(), 'department_id') !== false) {
                return createErrorResponse('Invalid department selected. Please select a valid department.', 400);
            } elseif (strpos($e->getMessage(), 'user_id') !== false) {
                return createErrorResponse('User account creation failed. Please try again or contact support.', 500);
            } elseif (strpos($e->getMessage(), 'location_id') !== false) {
                return createErrorResponse('Invalid location data provided. Please check your province, district, sector, and cell selections.', 400);
            }
        }

        // Handle other specific errors
        if (strpos($e->getMessage(), 'Data too long') !== false) {
            return createErrorResponse('Some data is too long. Please check your input lengths and try again.', 400);
        }

        if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            return createErrorResponse('Invalid data relationship detected. Please check your selections and try again.', 400);
        }

        if (strpos($e->getMessage(), 'duplicate key') !== false) {
            return createErrorResponse('Duplicate data detected. This record may already exist.', 409);
        }

        // Handle JSON encoding errors
        if (strpos($e->getMessage(), 'json') !== false) {
            return createErrorResponse('Data encoding error. Please try again or contact support.', 500);
        }

        // Generic database error with more context
        $errorMsg = 'Registration failed due to a database error. ';
        if (isset($studentData['email'])) {
            $errorMsg .= 'If this persists, please contact support with your email: ' . $studentData['email'];
        } else {
            $errorMsg .= 'Please try again in a few minutes.';
        }

        return createErrorResponse($errorMsg, 500);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Create error response
 */
function createErrorResponse($message, $statusCode = 400, $errors = null) {
    http_response_code($statusCode);
    $response = ['success' => false, 'message' => $message];
    if ($errors) {
        $response['errors'] = $errors;
    }
    return $response;
}

/**
 * Handle registration errors
 */
function handleRegistrationError($e, $logger = null) {
    // Log the error
    if ($logger) {
        $logger->error('StudentRegistration', 'Student registration failed', [
            'error' => $e->getMessage(),
            'email' => $_POST['email'] ?? 'unknown',
            'reg_no' => $_POST['reg_no'] ?? 'unknown'
        ]);
    } else {
        error_log("Student registration error: " . $e->getMessage());
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function handlePhotoUpload($file) {
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, and WebP are allowed.');
    }

    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        throw new Exception('File too large. Maximum size is 5MB.');
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'face_' . uniqid() . '.' . $extension;
    $uploadPath = 'uploads/students/' . $filename;

    // Create directory if it doesn't exist
    if (!is_dir('uploads/students')) {
        mkdir('uploads/students', 0755, true);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload image');
    }

    return $uploadPath;
}

function processFingerprintData($fingerprintData, $regNo) {
    // fingerprintData is a base64 encoded image data URL
    if (strpos($fingerprintData, 'data:image') !== 0) {
        return ['path' => null, 'quality' => 0]; // Invalid format, skip
    }

    // Extract base64 data
    $data = explode(',', $fingerprintData);
    if (count($data) !== 2) {
        return ['path' => null, 'quality' => 0]; // Invalid structure, skip
    }

    $base64Data = $data[1];
    $imageData = base64_decode($base64Data);

    if ($imageData === false || empty($imageData)) {
        return ['path' => null, 'quality' => 0]; // Failed to decode, skip
    }

    // Generate unique filename
    $filename = 'fingerprint_' . $regNo . '_' . uniqid() . '.png';
    $uploadPath = 'uploads/fingerprints/' . $filename;

    // Create directory if it doesn't exist
    if (!is_dir('uploads/fingerprints')) {
        mkdir('uploads/fingerprints', 0755, true);
    }

    // Save the image file
    if (file_put_contents($uploadPath, $imageData) === false) {
        throw new Exception('Failed to save fingerprint image');
    }

    return [
        'path' => $uploadPath,
        'quality' => (int)($_POST['fingerprint_quality'] ?? 0)
    ];
}
?>
