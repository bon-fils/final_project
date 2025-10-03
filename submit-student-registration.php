<?php
/**
 * Student Registration Submission Handler
 * Processes student registration form data
 */

require_once 'config.php';
require_once 'security_utils.php';
require_once 'backend/classes/Logger.php';

// Allow demo access for student registration when called from registration page
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$isFromRegistration = strpos($referer, 'register-student.php') !== false;

if (!$isFromRegistration) {
    require_once 'session_check.php';
} else {
    // For registration page access, initialize session and CSRF token
    session_start();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

header('Content-Type: application/json');

try {
    // Initialize logger
    $logger = new Logger('logs/student_registration.log', Logger::INFO);

    // Process the registration
    $result = processStudentRegistration($pdo, $logger, $isFromRegistration);
    echo json_encode($result);

} catch (Exception $e) {
    handleRegistrationError($e, $logger ?? null);
}

/**
 * Main function to process student registration
 */
function processStudentRegistration($pdo, $logger, $isFromRegistration) {
    // Validate CSRF token if not from registration page
    if (!$isFromRegistration && !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        return createErrorResponse('Invalid CSRF token', 403);
    }

    // Validate input data
    $validationResult = validateStudentInput($_POST, $logger);
    if (!$validationResult['valid']) {
        return $validationResult['response'];
    }

    $studentData = $validationResult['data'];

    // Check for duplicates
    $duplicateCheck = checkForDuplicateStudent($pdo, $studentData, $logger);
    if ($duplicateCheck) {
        return $duplicateCheck;
    }

    // Validate department and option relationships
    $relationshipCheck = validateDepartmentOptionRelationship($pdo, $studentData, $logger);
    if ($relationshipCheck) {
        return $relationshipCheck;
    }

    // Handle file uploads
    $photoPath = handlePhotoUploadSafely($_FILES['photo'] ?? null);
    $fingerprintData = handleFingerprintDataSafely($_POST['fingerprint_data'] ?? null, $studentData['reg_no']);

    // Create user and student records
    return createStudentRecords($pdo, $studentData, $photoPath, $fingerprintData, $logger, $isFromRegistration);
}

/**
 * Validate student input data
 */
function validateStudentInput($postData, $logger) {
    $validator = new InputValidator($postData);
    $validator
        ->required(['first_name', 'last_name', 'email', 'telephone', 'department_id', 'option_id', 'reg_no', 'sex', 'year_level'])
        ->length('first_name', 2, 50)
        ->length('last_name', 2, 50)
        ->email('email')
        ->phone('telephone')
        ->length('reg_no', 5, 20)
        ->custom('department_id', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid department')
        ->custom('option_id', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid program')
        ->custom('year_level', fn($v) => in_array($v, ['1','2','3']), 'Please select a valid year level')
        ->custom('sex', fn($v) => in_array($v, ['Male','Female']), 'Please select a valid gender');

    // Optional validations
    if (!empty($postData['province'])) {
        $validator->custom('province', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid province');
    }
    if (!empty($postData['district'])) {
        $validator->custom('district', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid district');
    }
    if (!empty($postData['sector'])) {
        $validator->custom('sector', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid sector');
    }
    if (!empty($postData['parent_contact'])) {
        $validator->phone('parent_contact');
    }
    if (!empty($postData['dob'])) {
        $dob = $postData['dob'];
        $birthDate = DateTime::createFromFormat('Y-m-d', $dob);
        $today = new DateTime();
        $age = $birthDate ? $today->diff($birthDate)->y : null;
        if (!$birthDate || $age < 16 || $age > 60) {
            $validator->custom('dob', fn() => false, 'Student must be between 16 and 60 years old');
        }
    }

    if ($validator->fails()) {
        $logger->warning('StudentRegistration', 'Validation failed for student registration', [
            'errors' => $validator->errors(),
            'email' => $postData['email'] ?? 'unknown'
        ]);

        return [
            'valid' => false,
            'response' => createErrorResponse('Validation failed', 422, $validator->errors())
        ];
    }

    // Sanitize and return data
    $studentData = [
        'first_name' => DataSanitizer::string($postData['first_name']),
        'last_name' => DataSanitizer::string($postData['last_name']),
        'email' => DataSanitizer::email($postData['email']),
        'telephone' => DataSanitizer::string($postData['telephone']),
        'department_id' => (int)$postData['department_id'],
        'option_id' => (int)$postData['option_id'],
        'reg_no' => DataSanitizer::string($postData['reg_no']),
        'student_id' => DataSanitizer::string($postData['studentIdNumber'] ?? ''),
        'province' => DataSanitizer::string($postData['province'] ?? ''),
        'district' => DataSanitizer::string($postData['district'] ?? ''),
        'sector' => DataSanitizer::string($postData['sector'] ?? ''),
        'cell' => DataSanitizer::string($postData['cell'] ?? ''),
        'parent_first_name' => DataSanitizer::string($postData['parent_first_name'] ?? ''),
        'parent_last_name' => DataSanitizer::string($postData['parent_last_name'] ?? ''),
        'parent_contact' => DataSanitizer::string($postData['parent_contact'] ?? ''),
        'dob' => DataSanitizer::string($postData['dob'] ?? ''),
        'sex' => DataSanitizer::string($postData['sex'] ?? ''),
        'year_level' => (int)($postData['year_level'] ?? 1)
    ];

    return ['valid' => true, 'data' => $studentData];
}

/**
 * Check for duplicate students
 */
function checkForDuplicateStudent($pdo, $studentData, $logger) {
    try {
        // Check both users and students tables for duplicates
        $stmt = $pdo->prepare("
            SELECT 'email' as type, COUNT(*) as count FROM users WHERE email = ?
            UNION ALL
            SELECT 'reg_no' as type, COUNT(*) as count FROM users WHERE username = ?
            UNION ALL
            SELECT 'email_student' as type, COUNT(*) as count FROM students WHERE email = ?
            UNION ALL
            SELECT 'reg_no_student' as type, COUNT(*) as count FROM students WHERE reg_no = ?
        ");
        $stmt->execute([
            $studentData['email'],
            $studentData['reg_no'],
            $studentData['email'],
            $studentData['reg_no']
        ]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Debug logging
        $logger->info('StudentRegistration', 'Duplicate check results', [
            'email' => $studentData['email'],
            'reg_no' => $studentData['reg_no'],
            'results' => $results
        ]);

        $userEmailExists = $results[0]['count'] > 0;
        $userRegNoExists = $results[1]['count'] > 0;
        $studentEmailExists = $results[2]['count'] > 0;
        $studentRegNoExists = $results[3]['count'] > 0;

        if ($userEmailExists || $userRegNoExists || $studentEmailExists || $studentRegNoExists) {
            $duplicateTypes = [];
            if ($userEmailExists || $studentEmailExists) $duplicateTypes[] = 'email';
            if ($userRegNoExists || $studentRegNoExists) $duplicateTypes[] = 'registration number';

            $duplicateType = implode(' and ', $duplicateTypes);

            $logger->warning('StudentRegistration', 'Duplicate student registration attempt', [
                'email' => $studentData['email'],
                'reg_no' => $studentData['reg_no'],
                'duplicate_type' => $duplicateType,
                'user_email_exists' => $userEmailExists,
                'user_regno_exists' => $userRegNoExists,
                'student_email_exists' => $studentEmailExists,
                'student_regno_exists' => $studentRegNoExists
            ]);

            return createErrorResponse("Student with this $duplicateType already exists.", 409);
        }

        return null;
    } catch (Exception $e) {
        $logger->error('StudentRegistration', 'Error in duplicate check', [
            'error' => $e->getMessage(),
            'email' => $studentData['email'],
            'reg_no' => $studentData['reg_no']
        ]);
        throw $e;
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
 * Handle photo upload safely
 */
function handlePhotoUploadSafely($file) {
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    return handlePhotoUpload($file);
}

/**
 * Handle fingerprint data safely
 */
function handleFingerprintDataSafely($fingerprintData, $regNo) {
    if (empty($fingerprintData)) {
        return ['path' => null, 'quality' => 0];
    }

    return processFingerprintData($fingerprintData, $regNo);
}

/**
 * Create user and student records
 */
function createStudentRecords($pdo, $studentData, $photoPath, $fingerprintData, $logger, $isFromRegistration) {
    try {
        // Begin transaction before any inserts
        $pdo->beginTransaction();

        // Create user account first
        $defaultPassword = password_hash($studentData['reg_no'], PASSWORD_BCRYPT);

        $insertUser = $pdo->prepare("
            INSERT INTO users (username, email, password, role, status, created_at)
            VALUES (:username, :email, :password, 'student', 'active', NOW())
        ");
        $insertUser->execute([
            ':username' => $studentData['reg_no'],
            ':email' => $studentData['email'],
            ':password' => $defaultPassword
        ]);
        $userId = $pdo->lastInsertId();

        // Insert student record
        $insertStudent = $pdo->prepare("
            INSERT INTO students (
                user_id, first_name, last_name, email, telephone, department_id, option_id,
                reg_no, student_id_number, province, district, sector, cell, parent_first_name,
                parent_last_name, parent_contact, dob, sex, year_level, photo, fingerprint,
                password, fingerprint_path, fingerprint_quality
            ) VALUES (
                :user_id, :first_name, :last_name, :email, :telephone, :department_id, :option_id,
                :reg_no, :student_id, :province, :district, :sector, :cell, :parent_first_name,
                :parent_last_name, :parent_contact, :dob, :sex, :year_level, :photo_path,
                :fingerprint_hash, :password, :fingerprint_path, :fingerprint_quality
            )
        ");

        $insertStudent->execute([
            ':user_id' => $userId,
            ':first_name' => $studentData['first_name'],
            ':last_name' => $studentData['last_name'],
            ':email' => $studentData['email'],
            ':telephone' => $studentData['telephone'],
            ':department_id' => $studentData['department_id'],
            ':option_id' => $studentData['option_id'],
            ':reg_no' => $studentData['reg_no'],
            ':student_id' => $studentData['student_id'],
            ':province' => $studentData['province'],
            ':district' => $studentData['district'],
            ':sector' => $studentData['sector'],
            ':cell' => $studentData['cell'],
            ':parent_first_name' => $studentData['parent_first_name'],
            ':parent_last_name' => $studentData['parent_last_name'],
            ':parent_contact' => $studentData['parent_contact'],
            ':dob' => $studentData['dob'],
            ':sex' => $studentData['sex'],
            ':year_level' => $studentData['year_level'],
            ':photo_path' => $photoPath,
            ':fingerprint_hash' => $fingerprintData['path'] ? password_hash($fingerprintData['path'], PASSWORD_BCRYPT) : null,
            ':password' => $defaultPassword,
            ':fingerprint_path' => $fingerprintData['path'],
            ':fingerprint_quality' => $fingerprintData['quality']
        ]);

        $studentId = $pdo->lastInsertId();

        // Log the registration
        $logger->info('StudentRegistration', 'New student registered successfully', [
            'student_name' => $studentData['first_name'] . ' ' . $studentData['last_name'],
            'reg_no' => $studentData['reg_no'],
            'email' => $studentData['email'],
            'user_id' => $userId,
            'student_id' => $studentId,
            'registered_by' => $_SESSION['user_id'] ?? null,
            'fingerprint_enrolled' => !empty($fingerprintData['path'])
        ]);

        $pdo->commit();

        $message = 'Student registered successfully!';
        if ($fingerprintData['path']) {
            $message .= ' Fingerprint enrolled successfully!';
        }

        return [
            'success' => true,
            'message' => $message,
            'student_id' => $studentId,
            'fingerprint_enrolled' => !empty($fingerprintData['path']),
            'redirect' => 'admin-dashboard.php'
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        // Handle specific database constraint violations
        if ($e->getCode() == 23000) { // Integrity constraint violation
            if (strpos($e->getMessage(), 'email') !== false) {
                $logger->error('StudentRegistration', 'Duplicate email constraint violation', [
                    'email' => $studentData['email'],
                    'error' => $e->getMessage()
                ]);
                return createErrorResponse('A user with this email address already exists.', 409);
            } elseif (strpos($e->getMessage(), 'username') !== false) {
                $logger->error('StudentRegistration', 'Duplicate username constraint violation', [
                    'reg_no' => $studentData['reg_no'],
                    'error' => $e->getMessage()
                ]);
                return createErrorResponse('A user with this registration number already exists.', 409);
            }
        }

        // Log and re-throw other database errors
        $logger->error('StudentRegistration', 'Database error during student creation', [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'email' => $studentData['email'],
            'reg_no' => $studentData['reg_no']
        ]);
        throw $e;
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
    $filename = 'student_' . uniqid() . '.' . $extension;
    $uploadPath = 'uploads/students/' . $filename;

    // Create directory if it doesn't exist
    if (!is_dir('uploads/students')) {
        mkdir('uploads/students', 0755, true);
    }

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to upload photo');
    }

    return $uploadPath;
}

function processFingerprintData($fingerprintData, $regNo) {
    // fingerprintData is a base64 encoded image data URL
    if (strpos($fingerprintData, 'data:image') !== 0) {
        throw new Exception('Invalid fingerprint data format');
    }

    // Extract base64 data
    $data = explode(',', $fingerprintData);
    if (count($data) !== 2) {
        throw new Exception('Invalid fingerprint data structure');
    }

    $base64Data = $data[1];
    $imageData = base64_decode($base64Data);

    if ($imageData === false) {
        throw new Exception('Failed to decode fingerprint data');
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
