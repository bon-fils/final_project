<?php
/**
 * Improved Admin Lecturer Registration Page
 * Fixed version with better error handling and debugging
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Start output buffering to catch any early output
ob_start();

try {
    // Load dependencies
    require_once "config.php";
    require_once "session_check.php";
    
    // Check if user is logged in and has admin role
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php?error=not_logged_in');
        exit;
    }
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header('Location: login.php?error=insufficient_permissions');
        exit;
    }
    
    // Initialize CSRF token
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Error in lecturer registration initialization: " . $e->getMessage());
    
    // Show user-friendly error
    ob_clean();
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
    echo "<h1>System Error</h1>";
    echo "<p>There was an error loading the lecturer registration system.</p>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='login.php'>Return to Login</a></p>";
    echo "</body></html>";
    exit;
}

// Additional security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// ================================
// HELPER FUNCTIONS
// ================================

/**
 * Get all departments for dropdown
 */
function getDepartments() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting departments: " . $e->getMessage());
        return [];
    }
}

/**
 * Validate lecturer registration data
 */
function validateLecturerData($data) {
    $errors = [];

    // Required field validation
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
            $errors[] = "{$label} is required";
        }
    }

    // Email validation
    if (!empty($data['email'])) {
        $email = trim($data['email']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
    }

    // Phone validation (optional)
    if (!empty($data['phone'])) {
        $phone = preg_replace('/\D/', '', $data['phone']);
        if (!preg_match('/^\d{10}$/', $phone)) {
            $errors[] = 'Phone number must be exactly 10 digits';
        }
    }

    // ID number validation
    if (!empty($data['id_number'])) {
        $id_number = preg_replace('/\D/', '', $data['id_number']);
        if (!preg_match('/^\d{16}$/', $id_number)) {
            $errors[] = 'ID Number must be exactly 16 digits';
        }
    }

    // Date of birth validation
    if (!empty($data['dob'])) {
        try {
            $birthDate = new DateTime($data['dob']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;

            if ($birthDate > $today) {
                $errors[] = 'Date of birth cannot be in the future';
            } elseif ($age < 21) {
                $errors[] = 'Lecturer must be at least 21 years old';
            } elseif ($age > 100) {
                $errors[] = 'Please enter a valid date of birth';
            }
        } catch (Exception $e) {
            $errors[] = 'Please enter a valid date of birth';
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

    try {
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
    } catch (Exception $e) {
        error_log("Error checking duplicates: " . $e->getMessage());
        $errors[] = 'Error checking for duplicates';
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

    try {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        do {
            $checkStmt->execute([$username]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) {
                $suffix++;
                $username = $username_base . $suffix;
            }
        } while ($exists && $suffix < 100); // Prevent infinite loop

        return $username;
    } catch (Exception $e) {
        error_log("Error generating username: " . $e->getMessage());
        return $username_base . '_' . time();
    }
}

/**
 * Process lecturer registration
 */
function processLecturerRegistration($post_data) {
    global $pdo;

    // Extract and sanitize data
    $data = [
        'first_name' => htmlspecialchars(trim($post_data['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'last_name' => htmlspecialchars(trim($post_data['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'gender' => $post_data['gender'] ?? '',
        'dob' => htmlspecialchars($post_data['dob'] ?? '', ENT_QUOTES, 'UTF-8'),
        'id_number' => preg_replace('/\D/', '', $post_data['id_number'] ?? ''),
        'email' => filter_var(trim($post_data['email'] ?? ''), FILTER_SANITIZE_EMAIL),
        'phone' => preg_replace('/\D/', '', $post_data['phone'] ?? ''),
        'department_id' => filter_var($post_data['department_id'] ?? '', FILTER_VALIDATE_INT),
        'education_level' => $post_data['education_level'] ?? ''
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
        // Generate username and password
        $username = generateUniqueUsername($data['first_name'], $data['last_name']);
        $password_plain = 'Welcome123!';

        // Insert into users table
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, first_name, last_name, phone, sex, dob, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $username,
            $data['email'],
            password_hash($password_plain, PASSWORD_DEFAULT),
            'lecturer',
            $data['first_name'],
            $data['last_name'],
            $data['phone'] ?: null,
            $data['gender'],
            $data['dob'],
            'active',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);

        $user_id = (int)$pdo->lastInsertId();

        // Insert into lecturers table
        $stmt = $pdo->prepare("
            INSERT INTO lecturers (user_id, gender, dob, id_number, department_id, education_level, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $data['gender'],
            $data['dob'],
            $data['id_number'],
            $data['department_id'],
            $data['education_level'],
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => "Lecturer registered successfully! Username: {$username}, Password: {$password_plain}",
            'username' => $username,
            'password' => $password_plain
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ================================
// MAIN PROCESSING
// ================================

$formError = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $formError = 'Security validation failed. Please refresh the page and try again.';
    } else {
        try {
            error_log("Lecturer registration attempt by user ID: " . $_SESSION['user_id']);
            
            $result = processLecturerRegistration($_POST);
            if ($result['success']) {
                $_SESSION['success_message'] = $result['message'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Generate new token
                
                header("Location: admin-register-lecturer-improved.php");
                exit;
            }
        } catch (Exception $e) {
            error_log('Lecturer registration error: ' . $e->getMessage());
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

// End output buffering and send content
ob_end_flush();
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .main-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .registration-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn {
            border-radius: 0.75rem;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border: none;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
        }
        
        .section-header {
            border-bottom: 3px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .section-header::after {
            content: '';
            position: absolute;
            bottom: -3px;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 2px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="registration-card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus me-2"></i>Register New Lecturer</h3>
                <p class="mb-0">Add a new lecturer to the system</p>
            </div>
            
            <div class="card-body">
                <?php if ($formError): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo $formError; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($successMessage): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="section-header">
                        <h6><i class="fas fa-user me-2"></i>Personal Information</h6>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="gender" class="form-label">Gender *</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="dob" class="form-label">Date of Birth *</label>
                            <input type="date" class="form-control" id="dob" name="dob" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="id_number" class="form-label">ID Number *</label>
                            <input type="text" class="form-control" id="id_number" name="id_number" 
                                   placeholder="16-digit ID number" maxlength="16" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="phone" name="phone" 
                                   placeholder="10-digit phone number" maxlength="10">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="section-header">
                        <h6><i class="fas fa-graduation-cap me-2"></i>Academic Information</h6>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="department_id" class="form-label">Department *</label>
                            <select class="form-select" id="department_id" name="department_id" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="education_level" class="form-label">Education Level *</label>
                            <select class="form-select" id="education_level" name="education_level" required>
                                <option value="">Select Education Level</option>
                                <option value="Bachelor's">Bachelor's Degree</option>
                                <option value="Master's">Master's Degree</option>
                                <option value="PhD">PhD</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-secondary me-md-2" onclick="resetForm()">
                            <i class="fas fa-undo me-2"></i>Reset Form
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Register Lecturer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                document.querySelector('form').reset();
            }
        }
        
        // Auto-format ID number and phone number
        document.getElementById('id_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substring(0, 16);
        });
        
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });
    </script>
</body>
</html>
