<?php
/**
 * Simplified Admin Lecturer Registration - Debug Version
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config.php";
require_once "session_check.php";

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php?error=insufficient_permissions');
    exit('Access denied. Admin role required.');
}

// Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$formError = '';
$successMessage = '';

// Simple form processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<!-- DEBUG: POST received -->\n";
    
    try {
        // Basic validation
        $required_fields = ['first_name', 'last_name', 'gender', 'dob', 'id_number', 'email', 'department_id', 'education_level'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty(trim($_POST[$field] ?? ''))) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
        }
        
        // CSRF check
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Security validation failed');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Generate username
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $base_username = strtolower($first_name . '.' . $last_name);
        $username = $base_username;
        
        // Check if username exists and make it unique
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() == 0) break;
            $username = $base_username . $counter;
            $counter++;
        }
        
        $password_plain = '12345';
        
        // Insert into users table
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone, sex, dob, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $username,
            trim($_POST['email']),
            password_hash($password_plain, PASSWORD_DEFAULT),
            'lecturer',
            $first_name,
            $last_name,
            trim($_POST['phone'] ?? ''),
            $_POST['gender'],
            $_POST['dob'],
            'active',
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
        
        $user_id = $pdo->lastInsertId();
        
        // Insert into lecturers table
        $stmt = $pdo->prepare("INSERT INTO lecturers (user_id, gender, dob, id_number, department_id, education_level, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $_POST['gender'],
            $_POST['dob'],
            trim($_POST['id_number']),
            (int)$_POST['department_id'],
            $_POST['education_level'],
            date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
        
        $lecturer_id = $pdo->lastInsertId();
        
        // Handle option assignments if any
        if (!empty($_POST['selected_options']) && is_array($_POST['selected_options'])) {
            foreach ($_POST['selected_options'] as $option_id) {
                $option_id = (int)$option_id;
                if ($option_id > 0) {
                    $stmt = $pdo->prepare("INSERT INTO lecturer_options (lecturer_id, option_id, created_at) VALUES (?, ?, ?)");
                    $stmt->execute([$lecturer_id, $option_id, date('Y-m-d H:i:s')]);
                }
            }
        }
        
        // Handle course assignments if any
        if (!empty($_POST['selected_courses']) && is_array($_POST['selected_courses'])) {
            foreach ($_POST['selected_courses'] as $course_id) {
                $course_id = (int)$course_id;
                if ($course_id > 0) {
                    $stmt = $pdo->prepare("INSERT INTO course_assignments (lecturer_id, course_id, created_at) VALUES (?, ?, ?)");
                    $stmt->execute([$lecturer_id, $course_id, date('Y-m-d H:i:s')]);
                }
            }
        }
        
        $pdo->commit();
        
        $successMessage = "✅ Lecturer registered successfully!<br>Username: <strong>{$username}</strong><br>Password: <strong>{$password_plain}</strong>";
        
        // Clear form by redirecting
        $_SESSION['success_message'] = $successMessage;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: admin-register-lecturer-simple.php");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $formError = "Registration failed: " . $e->getMessage();
        error_log("Lecturer registration error: " . $e->getMessage());
    }
}

// Handle success message from redirect
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Load departments
$departments = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $formError = "Failed to load departments: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Lecturer Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-user-plus me-2"></i>Simple Lecturer Registration</h4>
                        <small>Simplified version for testing - bypasses complex validation</small>
                    </div>
                    <div class="card-body">
                        
                        <?php if ($formError): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($formError) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($successMessage): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?= $successMessage ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="first_name" class="form-label">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="last_name" class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                                
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
                                
                                <div class="col-md-6">
                                    <label for="id_number" class="form-label">ID Number *</label>
                                    <input type="text" class="form-control" id="id_number" name="id_number" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="department_id" class="form-label">Department *</label>
                                    <select class="form-select" id="department_id" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>"><?= htmlspecialchars($dept['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="education_level" class="form-label">Education Level *</label>
                                    <select class="form-select" id="education_level" name="education_level" required>
                                        <option value="">Select Education Level</option>
                                        <option value="Bachelor's Degree">Bachelor's Degree</option>
                                        <option value="Master's Degree">Master's Degree</option>
                                        <option value="PhD">PhD</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <h6>Options (Optional)</h6>
                                    <div id="optionsContainer">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="selected_options[]" value="1" id="opt1">
                                            <label class="form-check-label" for="opt1">Option 1</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="selected_options[]" value="2" id="opt2">
                                            <label class="form-check-label" for="opt2">Option 2</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-12">
                                    <h6>Courses (Optional)</h6>
                                    <div id="coursesContainer">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="selected_courses[]" value="1" id="course1">
                                            <label class="form-check-label" for="course1">Course 1</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="selected_courses[]" value="2" id="course2">
                                            <label class="form-check-label" for="course2">Course 2</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus me-2"></i>Register Lecturer
                                </button>
                                <a href="admin-register-lecturer.php" class="btn btn-secondary ms-2">
                                    Back to Full Form
                                </a>
                                <a href="test-lecturer-form.php" class="btn btn-info ms-2">
                                    Test Form
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
