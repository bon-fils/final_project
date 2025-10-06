<?php
/**
 * Admin Lecturer Registration Page
 * Allows administrators to register new lecturers with full option and course assignment capabilities
 */

// ================================
// INITIALIZATION & CONFIGURATION
// ================================

require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Initialize CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Additional security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

// ================================
// DATA LOADING FUNCTIONS
// ================================

/**
 * Get all departments for dropdown
 */
function getDepartments() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Validate lecturer registration data with comprehensive checks
 */
function validateLecturerData($data) {
    $errors = [];

    // Required field validation with better messages
    $required_fields = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'gender' => 'Gender',
        'dob' => 'Date of birth',
        'id_number' => 'ID number',
        'email' => 'Email address',
        'department_id' => 'Department',
        'education_level' => 'Education level'
    ];

    foreach ($required_fields as $field => $label) {
        if (empty(trim($data[$field] ?? ''))) {
            $errors[] = "{$label} is required and cannot be empty";
        }
    }

    // Enhanced email validation
    if (!empty($data['email'])) {
        $email = trim($data['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address (e.g., user@example.com)';
        } elseif (strlen($email) > 100) {
            $errors[] = 'Email address is too long (maximum 100 characters)';
        } elseif (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email)) {
            $errors[] = 'Email address format is invalid';
        } elseif (preg_match('/\.\./', $email)) {
            $errors[] = 'Email address contains consecutive dots which is invalid';
        }
    }



    // Enhanced phone validation (optional but must be valid if provided)
    if (!empty($data['phone'])) {
        $phone = preg_replace('/\D/', '', $data['phone']); // Remove non-digits
        if (!preg_match('/^\d{10}$/', $phone)) {
            $current_length = strlen($phone);
            if ($current_length < 10) {
                $errors[] = "Phone number must be exactly 10 digits (currently {$current_length} digits - add " . (10 - $current_length) . " more digits)";
            } elseif ($current_length > 10) {
                $errors[] = "Phone number must be exactly 10 digits (currently {$current_length} digits - remove " . ($current_length - 10) . " digits)";
            } else {
                $errors[] = 'Phone number must contain only numeric digits (0-9)';
            }
        }
    }

    // Enhanced ID number validation
    if (!empty($data['id_number'])) {
        $id_number = preg_replace('/\D/', '', $data['id_number']); // Remove non-digits
        if (!preg_match('/^\d{16}$/', $id_number)) {
            $current_length = strlen($id_number);
            if ($current_length < 16) {
                $errors[] = "ID Number must be exactly 16 digits (currently {$current_length} digits - add " . (16 - $current_length) . " more digits)";
            } elseif ($current_length > 16) {
                $errors[] = "ID Number must be exactly 16 digits (currently {$current_length} digits - remove " . ($current_length - 16) . " digits)";
            } else {
                $errors[] = 'ID Number must contain only numeric digits (0-9)';
            }
        }
    }

    // Enhanced date of birth validation
    if (!empty($data['dob'])) {
        try {
            $birthDate = new DateTime($data['dob']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;

            // Check if date is not in the future
            if ($birthDate > $today) {
                $errors[] = 'Date of birth cannot be in the future';
            } elseif ($age < 21) {
                $errors[] = 'Lecturer must be at least 21 years old';
            } elseif ($age > 100) {
                $errors[] = 'Please enter a valid date of birth (age cannot exceed 100 years)';
            }

            // Check if date is reasonable (not too old)
            $min_birth_year = 1900;
            if ($birthDate->format('Y') < $min_birth_year) {
                $errors[] = 'Please enter a valid date of birth';
            }
        } catch (Exception $e) {
            $errors[] = 'Please enter a valid date of birth';
        } 
    }

    // Validate gender
    if (!empty($data['gender'])) {
        $valid_genders = ['Male', 'Female', 'Other'];
        if (!in_array($data['gender'], $valid_genders)) {
            $errors[] = 'Please select a valid gender';
        }
    }

    // Validate education level
    if (!empty($data['education_level'])) {
        $education_level = trim($data['education_level']);
        $valid_levels = ["Bachelor's", "Master's", 'PhD', 'Other'];
        // Also accept display text in case of form submission bug
        $valid_display = ["Bachelor's Degree", "Master's Degree", "PhD", "Other"];
        if (!in_array($education_level, $valid_levels) && !in_array($education_level, $valid_display)) {
            $errors[] = 'Please select a valid education level';
        }
    }

    // Validate department exists
    if (!empty($data['department_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
        $stmt->execute([$data['department_id']]);
        if ($stmt->fetchColumn() == 0) {
            $errors[] = 'Selected department does not exist';
        }
    }

    return $errors;
}

/**
 * Check for duplicate records
 */
function checkDuplicates($email, $id_number) {
    global $pdo;
    $errors = [];

    // Check ID number uniqueness
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE id_number = ?");
    $stmt->execute([$id_number]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'ID Number already exists';
    }

    // Check email uniqueness
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Email already exists in the system';
    }

    return $errors;
}

/**
 * Generate unique username
 */
function generateUniqueUsername($first_name, $last_name) {
    global $pdo;

    $username_base = strtolower(trim(preg_replace('/\s+/', '.', $first_name . ' ' . $last_name)));
    $username = $username_base;
    $suffix = 0;

    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    do {
        $checkStmt->execute([$username]);
        $exists = (int)$checkStmt->fetchColumn() > 0;
        if ($exists) {
            $suffix++;
            $username = $username_base . $suffix;
        }
    } while ($exists);

    return $username;
}




/**
 * Process lecturer registration
 */
function processLecturerRegistration($post_data) {
    global $pdo;

    // Extract and sanitize data with additional security checks
    $data = [
        'first_name' => htmlspecialchars(trim($post_data['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'last_name' => htmlspecialchars(trim($post_data['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'gender' => $post_data['gender'] ?? '',
        'dob' => htmlspecialchars($post_data['dob'] ?? '', ENT_QUOTES, 'UTF-8'),
        'id_number' => preg_replace('/\D/', '', $post_data['id_number'] ?? ''), // Only digits
        'email' => filter_var(trim($post_data['email'] ?? ''), FILTER_SANITIZE_EMAIL),
        'phone' => preg_replace('/\D/', '', $post_data['phone'] ?? ''), // Only digits
        'department_id' => filter_var($post_data['department_id'] ?? '', FILTER_VALIDATE_INT),
        'education_level' => $post_data['education_level'] ?? '',
        'selected_options' => is_array($post_data['selected_options'] ?? []) ?
            array_map('intval', $post_data['selected_options']) : [],
        'selected_courses' => is_array($post_data['selected_courses'] ?? []) ?
            array_map('intval', $post_data['selected_courses']) : []
    ];

    // Validate data
    $validation_errors = validateLecturerData($data);
    if (!empty($validation_errors)) {
        throw new Exception(implode('. ', $validation_errors));
    }

    // Check for duplicates
    $duplicate_errors = checkDuplicates($data['email'], $data['id_number']);
    if (!empty($duplicate_errors)) {
        throw new Exception(implode('. ', $duplicate_errors));
    }

    $pdo->beginTransaction();

    try {
        // Generate username
        $username = generateUniqueUsername($data['first_name'], $data['last_name']);
        $password_plain = '12345';



        // Insert into users table
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $username,
            $data['email'],
            password_hash($password_plain, PASSWORD_DEFAULT),
            'lecturer',
            date('Y-m-d H:i:s')
        ]);

        $user_id = (int)$pdo->lastInsertId();

        // Insert into lecturers table
        $stmt = $pdo->prepare("INSERT INTO lecturers
            (user_id, gender, dob, id_number, department_id, education_level)
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $data['gender'],
            $data['dob'],
            $data['id_number'],
            $data['department_id'],
            $data['education_level']
        ]);

        $lecturer_id = (int)$pdo->lastInsertId();

        // Handle assignments
        $assignment_result = handleAssignments($lecturer_id, $data);

        $pdo->commit();

        // Clear cache
        require_once "cache_utils.php";
        cache_delete("lecturer_stats_dept_{$data['department_id']}");

        // Generate success message
        $course_count = $assignment_result['courses_assigned'];
        $option_count = $assignment_result['options_assigned'];

        $course_message = $course_count > 0 ? " and assigned to $course_count course(s)" : "";
        $option_message = $option_count > 0 ? " with access to $option_count option(s)" : " (no option access assigned)";

        return [
            'success' => true,
            'message' => "Lecturer registered successfully$course_message$option_message! Login credentials: Username: $username, Password: 12345",
            'username' => $username,
            'password' => $password_plain
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Handle option and course assignments
 */
function handleAssignments($lecturer_id, $data) {
    global $pdo;

    $courses_assigned = 0;
    $options_assigned = 0;

    // Validate option assignments
    $selected_options = is_array($data['selected_options']) ? array_filter(array_map('intval', $data['selected_options'])) : [];

    if (!empty($selected_options)) {
        // Validate options belong to department (though this should be handled client-side)
        $placeholders = str_repeat('?,', count($selected_options) - 1) . '?';
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE id IN ($placeholders) AND department_id = ?");
        $stmt->execute(array_merge($selected_options, [$data['department_id']]));

        if ($stmt->fetchColumn() == count($selected_options)) {
            $options_assigned = count($selected_options);
        }
    }

    // Handle course assignments
    $selected_courses = is_array($data['selected_courses']) ? array_filter(array_map('intval', $data['selected_courses'])) : [];

    if (!empty($selected_courses)) {
        // Validate courses belong to department and are unassigned
        $placeholders = str_repeat('?,', count($selected_courses) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM courses
            WHERE id IN ($placeholders) AND department_id = ? AND (lecturer_id IS NULL OR lecturer_id = 0)
        ");
        $stmt->execute(array_merge($selected_courses, [$data['department_id']]));

        if ($stmt->fetchColumn() == count($selected_courses)) {
            // Assign courses
            $stmt = $pdo->prepare("UPDATE courses SET lecturer_id = ? WHERE id IN ($placeholders)");
            $stmt->execute(array_merge([$lecturer_id], $selected_courses));
            $courses_assigned = count($selected_courses);

            // Log assignment
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, created_at)
                VALUES (?, 'course_assignment_registration', ?, NOW())
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                "Assigned $courses_assigned courses to newly registered lecturer (ID: $lecturer_id)"
            ]);
        }
    }

    return [
        'courses_assigned' => $courses_assigned,
        'options_assigned' => $options_assigned
    ];
}

// ================================
// RATE LIMITING & SECURITY
// ================================

/**
 * Check rate limiting for form submissions
 */
function checkFormSubmissionRateLimit() {
    $max_submissions_per_hour = 10; // Reasonable limit for lecturer registration
    $time_window = 3600; // 1 hour in seconds

    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $current_time = time();

    // Use session to track submissions (more reliable than static variables)
    if (!isset($_SESSION['form_submissions'])) {
        $_SESSION['form_submissions'] = [];
    }

    // Clean old submissions
    $_SESSION['form_submissions'] = array_filter($_SESSION['form_submissions'], function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });

    // Check if under limit
    if (count($_SESSION['form_submissions']) >= $max_submissions_per_hour) {
        return false;
    }

    // Add current submission
    $_SESSION['form_submissions'][] = $current_time;
    return true;
}

// ================================
// MAIN PROCESSING LOGIC
// ================================

$formError = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting check
    if (!checkFormSubmissionRateLimit()) {
        $formError = 'Too many registration attempts. Please wait before trying again.';
    } elseif (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $formError = 'Security validation failed. Please refresh the page and try again.';
    } else {
        try {
            // Log the registration attempt
            error_log("Lecturer registration attempt by user ID: " . ($_SESSION['user_id'] ?? 'unknown') .
                     " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            $result = processLecturerRegistration($_POST);
            if ($result['success']) {
                $_SESSION['success_message'] = $result['message'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Clear form submission tracking on success
                unset($_SESSION['form_submissions']);

                header("Location: admin-register-lecturer.php");
                exit;
            }
        } catch (Exception $e) {
            error_log('Lecturer registration error: ' . $e->getMessage() .
                     ' | User ID: ' . ($_SESSION['user_id'] ?? 'unknown') .
                     ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

            $formError = 'Registration failed: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// Load data for form
$departments = getDepartments();

// Handle success message from redirect
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register Lecturer | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .registration-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 2rem auto;
            max-width: 800px;
        }
        .card-header {
            background: linear-gradient(135deg, #003366, #0059b3);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .card-body {
            padding: 2rem;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 0.75rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0059b3;
            box-shadow: 0 0 0 0.2rem rgba(0, 89, 179, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #003366, #0059b3);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 8px;
            font-weight: 600;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0059b3, #003366);
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(180deg, #003366 0%, #0059b3 100%);
            color: white;
            padding-top: 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: rgba(255,255,255,0.1);
        }
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                margin-bottom: 1rem;
            }
            .sidebar a {
                display: inline-block;
                margin: 0.25rem;
                padding: 0.5rem 1rem;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .registration-card {
                margin: 1rem 0;
                max-width: 100%;
            }
            .card-header {
                padding: 1.5rem;
            }
            .card-body {
                padding: 1.5rem;
            }
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .loading-overlay.show {
            display: flex;
        }
        .alert {
            border-radius: 8px;
            border: none;
        }
        .course-item {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        .course-item:hover {
            border-color: #0059b3;
            background: #f0f8ff;
        }
        .course-info {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.5rem;
        }
        .option-selection-container, .course-selection-container {
            min-height: 100px;
        }
        .validation-message {
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
        .validation-message.success {
            color: #198754;
        }
        .validation-message.error {
            color: #dc3545;
        }
        .validation-message.info {
            color: #6c757d;
        }
        .section-header {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .section-header h6 {
            font-weight: 600;
            color: #0059b3;
            margin-bottom: 0;
        }
        .section-header i {
            color: #0059b3;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h4><i class="fas fa-graduation-cap me-2"></i>RP System</h4>
            <small>Admin Panel</small>
        </div>
        <a href="admin-dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
        <a href="register-student.php"><i class="fas fa-user-plus me-2"></i>Register Student</a>
        <a href="admin-register-lecturer.php" class="active"><i class="fas fa-chalkboard-teacher me-2"></i>Register Lecturer</a>
        <a href="manage-users.php"><i class="fas fa-users-cog me-2"></i>Manage Users</a>
        <a href="manage-departments.php"><i class="fas fa-building me-2"></i>Departments</a>
        <a href="admin-reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
        <a href="logout.php" class="mt-4 text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 class="mb-2">Registering Lecturer</h5>
            <p class="mb-0">Please wait while we process the registration...</p>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="registration-card">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i>Register New Lecturer</h3>
                <p class="mb-0 mt-2 opacity-75">Add a new lecturer to the system</p>
            </div>
            <div class="card-body">
                <?php if(!empty($formError)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= $formError ?>
                    </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?= $_SESSION['success_message'] ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <form id="lecturerRegistrationForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32)); ?>">
                    <input type="hidden" name="role" value="lecturer">

                    <!-- Personal Information Section -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 section-header">
                            <h6>
                                <i class="fas fa-user me-2"></i>Personal Information
                            </h6>
                        </div>
                        <div class="col-md-6">
                             <label class="form-label" for="first_name">
                                 <i class="fas fa-user me-1"></i>First Name <span class="text-danger">*</span>
                             </label>
                             <input type="text" id="first_name" name="first_name" class="form-control"
                                    required aria-required="true" maxlength="50" minlength="2"
                                    pattern="[A-Za-z\s]+" title="Only letters and spaces allowed"
                                    placeholder="Enter first name">
                             <div class="invalid-feedback">
                                 Please enter a valid first name (2-50 characters, letters only).
                             </div>
                         </div>
                         <div class="col-md-6">
                             <label class="form-label" for="last_name">
                                 <i class="fas fa-user me-1"></i>Last Name <span class="text-danger">*</span>
                             </label>
                             <input type="text" id="last_name" name="last_name" class="form-control"
                                    required aria-required="true" maxlength="50" minlength="2"
                                    pattern="[A-Za-z\s]+" title="Only letters and spaces allowed"
                                    placeholder="Enter last name">
                             <div class="invalid-feedback">
                                 Please enter a valid last name (2-50 characters, letters only).
                             </div>
                         </div>
                         <div class="col-md-6">
                             <label class="form-label" for="gender">
                                 <i class="fas fa-venus-mars me-1"></i>Gender <span class="text-danger">*</span>
                             </label>
                             <select id="gender" name="gender" class="form-select" required aria-required="true">
                                 <option value="">Select Gender</option>
                                 <option value="Male">Male</option>
                                 <option value="Female">Female</option>
                                 <option value="Other">Other</option>
                             </select>
                             <div class="invalid-feedback">
                                 Please select a gender.
                             </div>
                         </div>
                         <div class="col-md-6">
                             <label class="form-label" for="dob">
                                 <i class="fas fa-calendar me-1"></i>Date of Birth <span class="text-danger">*</span>
                             </label>
                             <input type="date" id="dob" name="dob" class="form-control"
                                    required aria-required="true"
                                    max="<?php echo date('Y-m-d', strtotime('-21 years')); ?>"
                                    min="<?php echo date('Y-m-d', strtotime('-100 years')); ?>">
                             <small class="form-text text-muted">
                                 <i class="fas fa-info-circle me-1"></i>Must be at least 21 years old and not more than 100 years ago.
                             </small>
                             <div class="invalid-feedback">
                                 Please enter a valid date of birth.
                             </div>
                         </div>
                    </div>

                    <!-- Department & Education Section -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 section-header">
                            <h6>
                                <i class="fas fa-building me-2"></i>Department & Education
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">
                                Department <span class="text-danger">*</span>
                            </label>
                            <select id="department_id" name="department_id" class="form-select" required aria-required="true">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['id']) ?>">
                                        <?= htmlspecialchars($dept['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Selecting a department will load available courses below
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label for="education_level" class="form-label">
                                Education Level <span class="text-danger">*</span>
                            </label>
                            <select id="education_level" name="education_level" class="form-select" required aria-required="true">
                                <option value="">Select Level</option>
                                <option value="Bachelor's">Bachelor's Degree</option>
                                <option value="Master's">Master's Degree</option>
                                <option value="PhD">PhD</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <!-- Contact & Identification Section -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 section-header">
                            <h6>
                                <i class="fas fa-id-card me-2"></i>Contact & Identification
                            </h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="id_number">
                                <i class="fas fa-id-card me-1"></i>ID Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="id_number" name="id_number" class="form-control"
                                   required aria-required="true" maxlength="16" minlength="16"
                                   pattern="\d{16}" inputmode="numeric"
                                   placeholder="1234567890123456"
                                   onkeypress="return isNumberKey(event)"
                                   oninput="validateIdNumber(this)"
                                   title="Must be exactly 16 digits">
                            <div class="d-flex justify-content-between">
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle me-1"></i>Must be exactly 16 digits (numbers only).
                                </small>
                                <small id="id-counter" class="form-text text-info fw-bold" style="display: none;">0/16</small>
                            </div>
                            <div class="invalid-feedback">
                                ID Number must be exactly 16 digits.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">
                                <i class="fas fa-envelope me-1"></i>Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" id="email" name="email" class="form-control"
                                   required aria-required="true" maxlength="100"
                                   placeholder="lecturer@university.edu"
                                   pattern="[^\s@]+@[^\s@]+\.[^\s@]+">
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Valid email address required for account creation.
                            </small>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="1234567890" onkeypress="return isNumberKey(event)" oninput="validatePhoneNumber(this)">
                            <small class="form-text text-muted">Optional. Must be exactly 10 digits only.</small>
                        </div>
                    </div>

                    <!-- Course and Option Assignment Section -->
                    <div class="row g-3 mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-cogs me-2"></i>Access Permissions & Assignments
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <!-- Option Assignment Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="fas fa-list me-2"></i>Option Access (Required)
                                                <small class="text-muted fw-normal">- Select options this lecturer can access based on department you selected</small>
                                            </label>
                                            <div class="option-selection-container">
                                                <div id="optionsContainer" class="text-center">
                                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                        <span class="visually-hidden">Loading options...</span>
                                                    </div>
                                                    <p class="text-muted mt-2">Loading available options...</p>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="form-text text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Select at least one option for the lecturer to access.
                                                </small>
                                                <small class="text-primary fw-bold">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    <span id="selectedOptionsCount">0</span> options selected
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Course Assignment Section -->
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="fas fa-book me-2"></i>Course Assignment (Optional)
                                                <small class="text-warning fw-bold">- Only unassigned courses are displayed for selection</small>
                                            </label>
                                            <div class="course-selection-container">
                                                <div id="coursesContainer" class="text-center">
                                                    <div class="text-muted py-4">
                                                        <i class="fas fa-book-open fa-2x mb-3"></i>
                                                        <h6 class="mb-2">Course Assignment</h6>
                                                        <p class="mb-0">Select a department above to view available courses</p>
                                                        <small class="text-muted">Only unassigned courses will be shown for assignment</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="form-text text-warning fw-bold">
                                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <strong>Important:</strong> Only courses that are not currently assigned to any lecturer are shown here.
                                                </small>
                                                <small class="text-primary fw-bold">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    <span id="selectedCoursesCount">0</span> courses selected
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden inputs to store selected course and option IDs -->
                    <div id="selectedOptionsInputs" style="display: none;"></div>
                    <div id="selectedCoursesInputs" style="display: none;"></div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="addBtn">
                            <i class="fas fa-plus me-2"></i>Register Lecturer
                        </button>
                        <a href="admin-dashboard.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Course and Option assignment functionality for lecturer registration
    let availableCourses = [];
    let assignedCourses = [];
    let availableOptions = [];
    let selectedOptionsForRegistration = [];
    let selectedCoursesForRegistration = [];

    // Function to check if key pressed is a number
    function isNumberKey(evt) {
        var charCode = (evt.which) ? evt.which : evt.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            return false;
        }
        return true;
    }


    // Validate ID number field
    function validateIdNumber(input) {
        // Remove any non-numeric characters
        input.value = input.value.replace(/\D/g, '');

        // Limit to 16 characters
        if (input.value.length > 16) {
            input.value = input.value.substring(0, 16);
        }
    }


    // Load options for registration form
    function loadOptionsForRegistration(departmentId = null) {
        const optionsContainer = document.getElementById('optionsContainer');

        if (!departmentId) {
            optionsContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Please select a department first</strong><br>
                    <small>Choose a department above to view and assign available options for this lecturer.</small>
                </div>
            `;
            availableOptions = [];
            renderOptionsForRegistration();
            return;
        }

        // Show loading state
        optionsContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Loading options...</span>
                </div>
                <h6 class="text-primary mb-2">Loading Available Options</h6>
                <p class="text-muted mb-0">Fetching options for the selected department...</p>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
                </div>
            </div>
        `;

        fetch(`api/department-option-api.php?action=get_options&department_id=${departmentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    availableOptions = data.data;
                    renderOptionsForRegistration();
                } else {
                    optionsContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>No Options Available</strong><br>
                            ${data.message || 'There are no options in this department.'}<br>
                            <small class="text-muted">You can still register the lecturer and assign options later through the option management system.</small>
                        </div>
                    `;
                    availableOptions = [];
                    renderOptionsForRegistration();
                }
            })
            .catch(error => {
                console.error('Error loading options:', error);
                optionsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Failed to Load Options</strong><br>
                        <small>There was an error loading options for this department. Please try refreshing the page or contact support if the problem persists.</small><br>
                        <button class="btn btn-sm btn-outline-danger mt-2" onclick="loadOptionsForRegistration(${departmentId})">
                            <i class="fas fa-refresh me-1"></i>Try Again
                        </button>
                    </div>
                `;
                availableOptions = [];
                renderOptionsForRegistration();
            });
    }

    // Load courses for registration form
    function loadCoursesForRegistration(departmentId = null) {
        const coursesContainer = document.getElementById('coursesContainer');

        // If no department specified, try to get from form
        if (!departmentId) {
            const departmentSelect = document.getElementById('department_id');
            departmentId = departmentSelect ? departmentSelect.value : null;
        }

        if (!departmentId) {
            coursesContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Please select a department first</strong><br>
                    <small>Choose a department above to view and assign available courses for this lecturer.</small>
                </div>
            `;
            return;
        }

        // Show loading state with better messaging
        coursesContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary mb-3" role="status" style="width: 2rem; height: 2rem;">
                    <span class="visually-hidden">Loading courses...</span>
                </div>
                <h6 class="text-primary mb-2">Loading Available Courses</h6>
                <p class="text-muted mb-0">Fetching courses for the selected department...</p>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
                </div>
            </div>
        `;

        fetch(`api/assign-courses-api.php?action=get_courses&department_id=${departmentId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    availableCourses = data.data;
                    renderCoursesForRegistration();
                } else {
                    coursesContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>No Courses Available</strong><br>
                            ${data.message || 'There are no unassigned courses in this department.'}<br>
                            <small class="text-muted">You can still register the lecturer and assign courses later through the course management system.</small>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading courses:', error);
                coursesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Failed to Load Courses</strong><br>
                        <small>There was an error loading courses for this department. Please try refreshing the page or contact support if the problem persists.</small><br>
                        <button class="btn btn-sm btn-outline-danger mt-2" onclick="loadCoursesForRegistration()">
                            <i class="fas fa-refresh me-1"></i>Try Again
                        </button>
                    </div>
                `;
            });
    }

    function renderOptionsForRegistration() {
        const optionsContainer = document.getElementById('optionsContainer');

        if (availableOptions.length === 0) {
            optionsContainer.innerHTML = `
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <h6>No Options Available</h6>
                    <p class="mb-0">No options are available.</p>
                    <small class="text-muted">Please contact your administrator to create options first.</small>
                    <br><br>
                    <button class="btn btn-primary btn-sm" onclick="loadOptionsForRegistration()">
                        <i class="fas fa-refresh me-1"></i>Retry Loading Options
                    </button>
                </div>
            `;
            return;
        }

        let html = `
            <div class="row g-2">
        `;

        availableOptions.forEach(option => {
            html += `
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input option-checkbox" type="checkbox"
                               value="${option.id}" id="option_reg_${option.id}"
                               onchange="updateSelectedOptions()">
                        <label class="form-check-label fw-bold" for="option_reg_${option.id}">
                            <i class="fas fa-list me-1"></i>
                            ${option.name}
                        </label>
                    </div>
                </div>
            `;
        });

        html += `
            </div>
        `;

        optionsContainer.innerHTML = html;
        updateSelectedOptions();
    }

    function renderCoursesForRegistration() {
        const coursesContainer = document.getElementById('coursesContainer');

        if (availableCourses.length === 0) {
            coursesContainer.innerHTML = `
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    <h6>No Unassigned Courses Available</h6>
                    <p class="mb-0">All courses are already assigned to lecturers.</p>
                    <small class="text-muted">You can assign courses later using the course assignment feature.</small>
                </div>
            `;
            return;
        }

        // Display courses in table format
        let html = `
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th scope="col" style="width: 50px;">
                                <input type="checkbox" id="selectAllCourses" onchange="toggleAllCourses(this.checked)">
                            </th>
                            <th scope="col">Course Code</th>
                            <th scope="col">Course Name</th>
                            <th scope="col">Credits</th>
                            <th scope="col">Duration (Hours)</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        availableCourses.forEach(course => {
            const isActive = course.status === 'active';
            html += `
                <tr class="${!isActive ? 'table-secondary opacity-75' : ''}">
                    <td>
                        <input class="form-check-input course-checkbox" type="checkbox"
                               value="${course.id}" id="course_reg_${course.id}"
                               onchange="updateSelectedCourses()"
                               ${!isActive ? 'disabled' : ''}>
                    </td>
                    <td>
                        <label class="form-check-label fw-bold" for="course_reg_${course.id}">
                            ${course.course_code}
                        </label>
                    </td>
                    <td>
                        <strong>${course.course_name || course.name}</strong>
                        ${!isActive ? '<small class="text-muted ms-2">(Inactive)</small>' : ''}
                        ${course.description ? `<br><small class="text-muted">${course.description}</small>` : ''}
                    </td>
                    <td>${course.credits || 'N/A'}</td>
                    <td>${course.duration_hours || 'N/A'}</td>
                    <td>
                        <span class="badge bg-${isActive ? 'success' : 'secondary'}">
                            ${course.status || 'unknown'}
                        </span>
                    </td>
                </tr>
            `;
        });

        html += `
                    </tbody>
                </table>
            </div>
        `;

        coursesContainer.innerHTML = html;
        updateSelectedCourses();
    }


    function updateSelectedOptions() {
        const checkboxes = document.querySelectorAll('.option-checkbox:checked');
        const selectedCount = document.getElementById('selectedOptionsCount');
        const selectedInputs = document.getElementById('selectedOptionsInputs');

        selectedOptionsForRegistration = Array.from(checkboxes).map(cb => cb.value);

        if (selectedCount) {
            selectedCount.textContent = selectedOptionsForRegistration.length;
        }

        // Update hidden inputs for form submission
        if (selectedInputs) {
            selectedInputs.innerHTML = selectedOptionsForRegistration.map(optionId => `
                <input type="hidden" name="selected_options[]" value="${optionId}">
            `).join('');
        }
    }

    function toggleAllCourses(checked) {
        const checkboxes = document.querySelectorAll('.course-checkbox:not([disabled])');
        checkboxes.forEach(cb => {
            cb.checked = checked;
        });
        updateSelectedCourses();
    }

    function updateSelectedCourses() {
        const checkboxes = document.querySelectorAll('.course-checkbox:checked');
        const selectedCount = document.getElementById('selectedCoursesCount');
        const selectedInputs = document.getElementById('selectedCoursesInputs');
        const selectAllCheckbox = document.getElementById('selectAllCourses');

        selectedCoursesForRegistration = Array.from(checkboxes).map(cb => cb.value);

        if (selectedCount) {
            selectedCount.textContent = selectedCoursesForRegistration.length;
        }

        // Update select all checkbox state
        if (selectAllCheckbox) {
            const totalCheckboxes = document.querySelectorAll('.course-checkbox:not([disabled])').length;
            const checkedCheckboxes = document.querySelectorAll('.course-checkbox:checked:not([disabled])').length;
            selectAllCheckbox.checked = totalCheckboxes > 0 && checkedCheckboxes === totalCheckboxes;
            selectAllCheckbox.indeterminate = checkedCheckboxes > 0 && checkedCheckboxes < totalCheckboxes;
        }

        // Update hidden inputs for form submission
        if (selectedInputs) {
            selectedInputs.innerHTML = selectedCoursesForRegistration.map(courseId => `
                <input type="hidden" name="selected_courses[]" value="${courseId}">
            `).join('');
        }
    }

    // Validate phone number field
    function validatePhoneNumber(input) {
        // Remove any non-numeric characters
        input.value = input.value.replace(/\D/g, '');

        // Limit to 10 characters
        if (input.value.length > 10) {
            input.value = input.value.substring(0, 10);
        }
    }

    // Real-time validation feedback
    document.addEventListener('DOMContentLoaded', function() {
        // Add real-time validation for phone number
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                const value = this.value;
                const feedback = document.getElementById('phone-feedback') || createFeedbackElement('phone');

                if (value === '') {
                    feedback.textContent = '';
                    feedback.className = 'validation-message info';
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (/^\d{10}$/.test(value)) {
                    feedback.textContent = '✓ Valid phone number';
                    feedback.className = 'validation-message success';
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    feedback.textContent = 'Phone must be exactly 10 digits';
                    feedback.className = 'validation-message error';
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        }

        // Add validation for ID number on blur (when user leaves the field)
        const idInput = document.getElementById('id_number');
        const idCounter = document.getElementById('id-counter');
        if (idInput) {
            idInput.addEventListener('blur', function() {
                const value = this.value;
                const feedback = document.getElementById('id-feedback') || createFeedbackElement('id_number');

                if (value === '') {
                    // Hide counter and clear feedback for empty field
                    if (idCounter) idCounter.style.display = 'none';
                    feedback.textContent = '';
                    feedback.className = 'validation-message info';
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (value.length === 16) {
                    // Hide counter and show success for valid length
                    if (idCounter) idCounter.style.display = 'none';
                    feedback.textContent = '✓ ID number is valid';
                    feedback.className = 'validation-message success';
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else if (value.length < 16) {
                    // Show counter and error message for insufficient characters
                    if (idCounter) {
                        idCounter.textContent = `${value.length}/16`;
                        idCounter.className = 'form-text text-danger';
                        idCounter.style.display = 'block';
                    }
                    feedback.textContent = `ID must be exactly 16 characters (${value.length}/16)`;
                    feedback.className = 'validation-message error';
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                } else {
                    // Hide counter and show error for too many characters
                    if (idCounter) idCounter.style.display = 'none';
                    feedback.textContent = 'ID must be exactly 16 characters - Too long!';
                    feedback.className = 'validation-message error';
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });

            // Clear validation message and hide counter when user starts typing again
            idInput.addEventListener('focus', function() {
                const feedback = document.getElementById('id-feedback');
                if (feedback) {
                    feedback.textContent = '';
                    feedback.className = 'validation-message info';
                }
                if (idCounter) {
                    idCounter.style.display = 'none';
                }
                this.classList.remove('is-valid', 'is-invalid');
            });
        }

        // Add real-time validation for date of birth
        const dobInput = document.getElementById('dob');
        if (dobInput) {
            dobInput.addEventListener('change', function() {
                const value = this.value;
                const feedback = document.getElementById('dob-feedback') || createFeedbackElement('dob');

                if (value === '') {
                    feedback.textContent = '';
                    feedback.className = 'validation-message info';
                    this.classList.remove('is-valid', 'is-invalid');
                } else {
                    const birthDate = new Date(value);
                    const today = new Date();
                    const age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();

                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }

                    if (age >= 21 && age <= 100) {
                        feedback.textContent = `✓ Valid age: ${age} years old`;
                        feedback.className = 'validation-message success';
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else if (age < 21) {
                        feedback.textContent = 'Must be at least 21 years old';
                        feedback.className = 'validation-message error';
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    } else {
                        feedback.textContent = 'Age cannot exceed 100 years';
                        feedback.className = 'validation-message error';
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                }
            });
        }


        // Load options on page load
        loadOptionsForRegistration();

        // Add event listener for department change to load courses and options
        const departmentSelect = document.getElementById('department_id');
        if (departmentSelect) {
            departmentSelect.addEventListener('change', function() {
                const selectedDepartment = this.value;
                if (selectedDepartment) {
                    loadCoursesForRegistration(selectedDepartment);
                    loadOptionsForRegistration(selectedDepartment);
                } else {
                    const coursesContainer = document.getElementById('coursesContainer');
                    coursesContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Please select a department first to load available courses.
                        </div>
                    `;
                    loadOptionsForRegistration();
                }
            });
        }

        // Load courses and options if department is already selected (on page refresh)
        if (departmentSelect && departmentSelect.value) {
            loadCoursesForRegistration(departmentSelect.value);
            loadOptionsForRegistration(departmentSelect.value);
        } else {
            // Load options with no department selected initially
            loadOptionsForRegistration();
        }
    });




    // Helper function to create feedback elements
    function createFeedbackElement(fieldName) {
        const element = document.createElement('div');
        element.id = fieldName + '-feedback';
        element.className = 'validation-message info';
        element.style.fontSize = '0.8rem';
        element.style.marginTop = '0.25rem';

        const field = document.getElementById(fieldName);
        if (field && field.parentNode) {
            field.parentNode.appendChild(element);
        }

        return element;
    }

    // Enhanced form validation before submission
    document.getElementById('lecturerRegistrationForm')?.addEventListener('submit', function(e) {
        // Prevent double submission
        if (this.dataset.submitting === 'true') {
            e.preventDefault();
            return false;
        }

        // Ensure selections are updated before validation
        updateSelectedOptions();
        updateSelectedCourses();

        // Get form values
        const formData = new FormData(this);
        const firstName = formData.get('first_name')?.trim() || '';
        const lastName = formData.get('last_name')?.trim() || '';
        const gender = formData.get('gender') || '';
        const dob = formData.get('dob') || '';
        const idNumber = formData.get('id_number')?.trim() || '';
        const email = formData.get('email')?.trim() || '';
        const department = formData.get('department_id') || '';
        const education = formData.get('education_level') || '';

        // Validate selected options (required)
        const selectedOptions = document.querySelectorAll('.option-checkbox:checked');
        const availableOptionElements = document.querySelectorAll('.option-checkbox');

        if (availableOptionElements.length > 0 && selectedOptions.length === 0) {
            showAlert('Please select at least one option for the lecturer to access.', 'warning');
            e.preventDefault();
            return false;
        }

        if (availableOptionElements.length === 0) {
            // No options available - show warning but allow submission
            if (!confirm('No options are available for assignment. The lecturer will be created without option access. Continue?')) {
                e.preventDefault();
                return false;
            }
        }

        // Validate option IDs are numeric
        for (let checkbox of selectedOptions) {
            if (isNaN(checkbox.value) || parseInt(checkbox.value) <= 0) {
                showAlert('Invalid option selection detected. Please refresh the page and try again.', 'danger');
                e.preventDefault();
                return false;
            }
        }

        // Validate selected courses if any
        const selectedCourses = document.querySelectorAll('.course-checkbox:checked');
        if (selectedCourses.length > 0) {
            for (let checkbox of selectedCourses) {
                if (isNaN(checkbox.value) || parseInt(checkbox.value) <= 0) {
                    showAlert('Invalid course selection detected. Please refresh the page and try again.', 'danger');
                    e.preventDefault();
                    return false;
                }
            }
        }

        // Comprehensive field validation
        const validationErrors = [];

        // Required field validation
        if (!firstName) validationErrors.push('First name is required');
        if (!lastName) validationErrors.push('Last name is required');
        if (!gender) validationErrors.push('Gender is required');
        if (!dob) validationErrors.push('Date of birth is required');
        if (!idNumber) validationErrors.push('ID number is required');
        if (!email) validationErrors.push('Email is required');
        if (!department) validationErrors.push('Department is required');
        if (!education) validationErrors.push('Education level is required');

        // Name validation
        if (firstName && (firstName.length < 2 || !/^[A-Za-z\s]+$/.test(firstName))) {
            validationErrors.push('First name must be at least 2 characters and contain only letters');
        }
        if (lastName && (lastName.length < 2 || !/^[A-Za-z\s]+$/.test(lastName))) {
            validationErrors.push('Last name must be at least 2 characters and contain only letters');
        }

        // Email validation
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            validationErrors.push('Please enter a valid email address');
        }


        // ID number validation
        if (idNumber && idNumber.length !== 16) {
            validationErrors.push('ID Number must be exactly 16 digits');
        }

        // Date of birth validation
        if (dob) {
            const birthDate = new Date(dob);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();

            let adjustedAge = age;
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                adjustedAge--;
            }

            if (adjustedAge < 21) {
                validationErrors.push('Lecturer must be at least 21 years old');
            } else if (adjustedAge > 100) {
                validationErrors.push('Please enter a valid date of birth');
            }
        }

        // Show validation errors
        if (validationErrors.length > 0) {
            showAlert('Please correct the following errors:\n• ' + validationErrors.join('\n• '), 'danger');
            e.preventDefault();
            return false;
        }

        // Mark form as submitting to prevent double submission
        this.dataset.submitting = 'true';

        // Set loading state
        const addBtn = document.getElementById('addBtn');
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (addBtn) {
            addBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Registering Lecturer...';
            addBtn.disabled = true;
        }
        if (loadingOverlay) {
            loadingOverlay.classList.add('show');
        }

        return true;
    });

    // Helper function to show alerts
    function showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlerts = document.querySelectorAll('.alert');
        existingAlerts.forEach(alert => alert.remove());

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'danger' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(alertDiv);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    </script>
</body>
</html>
