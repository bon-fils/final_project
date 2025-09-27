<?php
/**
 * Student Registration Submission Handler
 * Processes student registration form data
 */

require_once 'session_check.php';
require_once 'config.php';
require_once 'security_utils.php';

header('Content-Type: application/json');

try {
    // Validate CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid CSRF token');
    }


    // Use InputValidator for robust validation
    $validator = new InputValidator($_POST);
    $validator
        ->required(['first_name', 'last_name', 'email', 'telephone', 'department_id', 'option_id', 'reg_no', 'sex', 'year_level'])
        ->length('first_name', 2, 50)
        ->length('last_name', 2, 50)
        ->email('email')
        ->phone('telephone')
        ->length('reg_no', 5, 20)
        // Validate select options are not left as default/empty
        ->custom('department_id', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid department')
        ->custom('option_id', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid program')
        ->custom('year_level', fn($v) => in_array($v, ['1','2','3']), 'Please select a valid year level')
        ->custom('sex', fn($v) => in_array($v, ['Male','Female']), 'Please select a valid gender');

    // Optional: validate location selects if provided
    if (!empty($_POST['province'])) {
        $validator->custom('province', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid province');
    }
    if (!empty($_POST['district'])) {
        $validator->custom('district', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid district');
    }
    if (!empty($_POST['sector'])) {
        $validator->custom('sector', fn($v) => !empty($v) && $v !== '' && $v !== '0', 'Please select a valid sector');
    }

    // Optional: validate parent contact if provided
    if (!empty($_POST['parent_contact'])) {
        $validator->phone('parent_contact');
    }

    // Optional: validate date of birth (age 16-60)
    if (!empty($_POST['dob'])) {
        $dob = $_POST['dob'];
        $birthDate = DateTime::createFromFormat('Y-m-d', $dob);
        $today = new DateTime();
        $age = $birthDate ? $today->diff($birthDate)->y : null;
        if (!$birthDate || $age < 16 || $age > 60) {
            $validator->custom('dob', fn() => false, 'Student must be between 16 and 60 years old');
        }
    }

    if ($validator->fails()) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ]);
        exit;
    }

    // Sanitize input data
    $studentData = [
        'first_name' => DataSanitizer::string($_POST['first_name']),
        'last_name' => DataSanitizer::string($_POST['last_name']),
        'email' => DataSanitizer::email($_POST['email']),
        'telephone' => DataSanitizer::string($_POST['telephone']),
        'department_id' => (int)$_POST['department_id'],
        'option_id' => (int)$_POST['option_id'],
        'reg_no' => DataSanitizer::string($_POST['reg_no']),
        'student_id' => DataSanitizer::string($_POST['studentIdNumber'] ?? ''),
        'province' => DataSanitizer::string($_POST['province'] ?? ''),
        'district' => DataSanitizer::string($_POST['district'] ?? ''),
        'sector' => DataSanitizer::string($_POST['sector'] ?? ''),
        'parent_first_name' => DataSanitizer::string($_POST['parent_first_name'] ?? ''),
        'parent_last_name' => DataSanitizer::string($_POST['parent_last_name'] ?? ''),
        'parent_contact' => DataSanitizer::string($_POST['parent_contact'] ?? ''),
        'dob' => DataSanitizer::string($_POST['dob'] ?? ''),
        'sex' => DataSanitizer::string($_POST['sex'] ?? ''),
        'year_level' => (int)($_POST['year_level'] ?? 1),
        'registration_date' => date('Y-m-d H:i:s')
    ];


    // Check for unique reg_no and email
    $existingStudent = $pdo->prepare("
        SELECT id FROM students
        WHERE email = ? OR reg_no = ?
    ");
    $existingStudent->execute([
        $studentData['email'],
        $studentData['reg_no']
    ]);
    if ($existingStudent->fetch()) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Student with this email or registration number already exists.'
        ]);
        exit;
    }


    // Handle photo upload (optional)
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $photoPath = handlePhotoUpload($_FILES['photo']);
    }


    // Handle fingerprint data (optional)
    $fingerprintData = null;
    $fingerprintQuality = 0;
    if (isset($_POST['fingerprint_data']) && !empty($_POST['fingerprint_data'])) {
        $fingerprintData = processFingerprintData($_POST['fingerprint_data']);
        $fingerprintQuality = (int)($_POST['fingerprint_quality'] ?? 0);
    }

    // Insert student record
    $pdo->beginTransaction();


    $insertStudent = $pdo->prepare("
        INSERT INTO students (
            first_name, last_name, email, telephone, department_id, option_id,
            reg_no, student_id_number, province, district, sector, parent_first_name, parent_last_name, parent_contact, dob, sex, year_level, photo, fingerprint, registration_date, created_at
        ) VALUES (
            :first_name, :last_name, :email, :telephone, :department_id, :option_id,
            :reg_no, :student_id, :province, :district, :sector, :parent_first_name, :parent_last_name, :parent_contact, :dob, :sex, :year_level, :photo_path, :fingerprint_data, :registration_date, NOW()
        )
    ");

    $insertStudent->execute([
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
        ':parent_first_name' => $studentData['parent_first_name'],
        ':parent_last_name' => $studentData['parent_last_name'],
        ':parent_contact' => $studentData['parent_contact'],
        ':dob' => $studentData['dob'],
        ':sex' => $studentData['sex'],
        ':year_level' => $studentData['year_level'],
        ':photo_path' => $photoPath,
        ':fingerprint_data' => $fingerprintData,
        ':registration_date' => $studentData['registration_date']
    ]);

    $studentId = $pdo->lastInsertId();

    // Log the registration
    $logStmt = $pdo->prepare("
        INSERT INTO system_logs (action, description, user_id, created_at)
        VALUES ('student_registration', 'New student registered: ' . ?, ?, NOW())
    ");
    $logStmt->execute([
        $studentData['first_name'] . ' ' . $studentData['last_name'],
        $_SESSION['user_id'] ?? null
    ]);

    $pdo->commit();

    $message = 'Student registered successfully!';
    if ($fingerprintData) {
        $message .= ' Fingerprint enrolled successfully!';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'student_id' => $studentId,
        'fingerprint_enrolled' => !empty($fingerprintData),
        'redirect' => 'admin-dashboard.php'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log("Student registration error: " . $e->getMessage());

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

function processFingerprintData($fingerprintData) {
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

    // Generate a hash of the fingerprint data for storage
    // This creates a unique identifier while keeping the data secure
    $fingerprintHash = password_hash($base64Data, PASSWORD_DEFAULT);

    return $fingerprintHash;
}
?>