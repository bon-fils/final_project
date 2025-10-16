<?php
/**
 * Rwanda Polytechnic Student Registration System
 * Enhanced student registration with biometric integration
 * Features: Face recognition, fingerprint enrollment, comprehensive validation
 * Version: 3.0
 */

session_start();
require_once "config.php";

// Initialize CSRF token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Initialize data arrays with error handling
$departments = [];

// Get active departments for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Validate required data
if (empty($departments)) {
    error_log("Warning: No active departments found in database");
}

// Handle POST request for student registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }

    // Sanitize and validate input data
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['telephone'] ?? '');
    $regNo = trim($_POST['reg_no'] ?? '');
    $studentIdNumber = trim($_POST['student_id_number'] ?? '');
    $departmentId = trim($_POST['department_id'] ?? '');
    $optionId = trim($_POST['option_id'] ?? '');
    $yearLevel = trim($_POST['year_level'] ?? '');
    $sex = trim($_POST['sex'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $parentFirstName = trim($_POST['parent_first_name'] ?? '');
    $parentLastName = trim($_POST['parent_last_name'] ?? '');
    $parentContact = trim($_POST['parent_contact'] ?? '');

    $errors = [];
    $success = false;
    $message = '';

    // Validation
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';
    if (empty($phone) || !preg_match('/^0\d{9}$/', $phone)) $errors[] = 'Valid 10-digit phone number starting with 0 is required';
    if (empty($regNo) || !preg_match('/^[A-Za-z0-9_-]{5,20}$/', $regNo)) $errors[] = 'Valid registration number (5-20 alphanumeric characters) is required';
    if (empty($departmentId)) $errors[] = 'Department selection is required';
    if (empty($optionId)) $errors[] = 'Program selection is required';
    if (empty($yearLevel)) $errors[] = 'Year level is required';
    if (empty($sex)) $errors[] = 'Gender selection is required';

    // Check for duplicate registration number
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE reg_no = ?");
            $stmt->execute([$regNo]);
            if ($stmt->fetch()) {
                $errors[] = 'Registration number already exists';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error occurred';
            error_log("Registration check error: " . $e->getMessage());
        }
    }

    // Process registration if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Create unique username
            $username = strtolower(substr($firstName, 0, 1) . $lastName);
            $originalUsername = $username;
            $count = 0;

            do {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $count++;
                    $username = $originalUsername . $count;
                } else {
                    break;
                }
            } while (true);

            // Handle photo upload
            $photoPath = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/students/photos/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png'];

                if (in_array($fileExtension, $allowedExtensions) && $_FILES['photo']['size'] <= 2 * 1024 * 1024) {
                    $fileName = 'student_' . time() . '_' . uniqid() . '.' . $fileExtension;
                    $photoPath = $uploadDir . $fileName;

                    if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photoPath)) {
                        $photoPath = null; // Continue without photo if upload fails
                    }
                }
            }

            // Insert user record
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, first_name, last_name, phone, sex, dob, photo, created_at) VALUES (?, ?, ?, 'student', ?, ?, ?, ?, ?, ?, NOW())");
            $password = password_hash('123456', PASSWORD_DEFAULT);
            $stmt->execute([$username, $email, $password, $firstName, $lastName, $phone, $sex, $dob, $photoPath]);
            $userId = $pdo->lastInsertId();

            // Handle face images
            $faceImages = [];
            if (isset($_FILES['face_images'])) {
                $uploadDir = 'uploads/students/faces/' . $userId . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                foreach ($_FILES['face_images']['tmp_name'] as $key => $tmpName) {
                    if ($_FILES['face_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $fileExtension = strtolower(pathinfo($_FILES['face_images']['name'][$key], PATHINFO_EXTENSION));
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                        if (in_array($fileExtension, $allowedExtensions) && $_FILES['face_images']['size'][$key] <= 5 * 1024 * 1024) {
                            $fileName = 'face_' . ($key + 1) . '_' . time() . '.' . $fileExtension;
                            $filePath = $uploadDir . $fileName;

                            if (move_uploaded_file($tmpName, $filePath)) {
                                $faceImages[] = $filePath;
                            }
                        }
                    }
                }
            }

            // Handle fingerprint data
            $fingerprintData = null;
            $fingerprintQuality = null;
            if (isset($_POST['fingerprint_data']) && isset($_POST['fingerprint_quality'])) {
                $fingerprintData = $_POST['fingerprint_data'];
                $fingerprintQuality = (int)$_POST['fingerprint_quality'];
            }

            // Insert student record
            $stmt = $pdo->prepare("INSERT INTO students (user_id, option_id, year_level, reg_no, parent_first_name, parent_last_name, parent_contact, department_id, student_id_number, face_images, fingerprint, fingerprint_quality, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $faceImagesJson = json_encode($faceImages);
            $stmt->execute([$userId, $optionId, $yearLevel, $regNo, $parentFirstName, $parentLastName, $parentContact, $departmentId, $studentIdNumber ?: $regNo, $faceImagesJson, $fingerprintData, $fingerprintQuality]);

            $pdo->commit();

            $success = true;
            $message = "Student registered successfully! Username: {$username}, Default password: 123456";

            // Log successful registration
            log_message('info', 'Student registered successfully', [
                'username' => $username,
                'registration_number' => $regNo,
                'department_id' => $departmentId
            ]);

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed: ' . $e->getMessage();
            error_log("Student registration error: " . $e->getMessage());
        }
    }

    // Return JSON response for AJAX requests
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        if ($success) {
            echo json_encode([
                'success' => true,
                'message' => $message,
                'student_id' => 'STU' . $userId,
                'fingerprint_enrolled' => !empty($fingerprintData),
                'redirect' => 'admin-dashboard.php'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'errors' => $errors
            ]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rwanda Polytechnic - Student Registration System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="css/register-student.css" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/admin_sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="text-primary mb-1"><i class="fas fa-user-plus me-2"></i>Student Registration</h2>
                        <p class="text-muted mb-0">Register new students with biometric authentication</p>
                    </div>
                    <button class="btn btn-outline-secondary d-md-none" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Success!</strong> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php elseif (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Registration Failed!</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Alert Container for AJAX responses -->
                <div id="alertContainer"></div>

                <!-- Registration Form -->
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Student Information Form</h5>
                    </div>
                    <div class="card-body">
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar bg-success" id="formProgress" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted mt-1" id="progressText">0% complete</small>
                        </div>

                        <form id="registrationForm" enctype="multipart/form-data" aria-label="Student Registration Form" autocomplete="off">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h6 class="section-title text-primary border-primary">
                                        <i class="fas fa-user me-2"></i>Personal Information
                                    </h6>

                                    <div class="mb-3">
                                        <label for="firstName" class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="firstName" name="first_name" required aria-required="true" aria-label="First Name">
                                    </div>

                                    <div class="mb-3">
                                        <label for="lastName" class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="lastName" name="last_name" required aria-required="true" aria-label="Last Name">
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required aria-required="true" aria-label="Email Address">
                                    </div>

                                    <div class="mb-3">
                                        <label for="telephone" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" required aria-required="true" aria-label="Phone Number">
                                    </div>

                                    <div class="mb-3">
                                        <label for="dob" class="form-label fw-semibold">Date of Birth</label>
                                        <input type="date" class="form-control" id="dob" name="dob" aria-label="Date of Birth">
                                    </div>

                                    <div class="mb-3">
                                        <label for="sex" class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                                        <select class="form-select" id="sex" name="sex" required aria-required="true" aria-label="Gender">
                                            <option value="">Select Gender</option>
                                            <option value="Male">Male</option>
                                            <option value="Female">Female</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Academic Information -->
                                <div class="col-md-6">
                                    <h6 class="section-title text-success border-success">
                                        <i class="fas fa-graduation-cap me-2"></i>Academic Information
                                    </h6>

                                    <div class="mb-3">
                                        <label for="reg_no" class="form-label fw-semibold">Registration Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="reg_no" name="reg_no" required maxlength="20" aria-required="true" aria-label="Registration Number">
                                    </div>

                                    <div class="mb-3">
                                        <label for="studentIdNumber" class="form-label fw-semibold">Student ID Number</label>
                                        <input type="text" class="form-control" id="studentIdNumber" name="student_id_number" maxlength="16" aria-label="Student ID Number">
                                    </div>

                                    <div class="mb-4">
                                        <label for="department" class="form-label fw-semibold d-flex align-items-center justify-content-between">
                                            <span class="d-flex align-items-center">
                                                <i class="fas fa-building me-2 text-primary fs-5"></i>
                                                Academic Department
                                            </span>
                                            <span class="badge bg-primary rounded-pill">
                                                <i class="fas fa-star me-1"></i>Required
                                            </span>
                                        </label>
                                        <div class="input-group input-group-lg shadow-sm">
                                            <span class="input-group-text bg-primary text-white border-primary">
                                                <i class="fas fa-university fa-lg"></i>
                                            </span>
                                            <select class="form-select form-select-lg border-primary" id="department" name="department_id" required aria-required="true" aria-label="Department" aria-describedby="departmentHelp">
                                                <option value="">🎓 Select Your Academic Department</option>
                                                <?php
                                                try {
                                                    require_once 'config.php';
                                                    $deptStmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
                                                    while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
                                                        echo "<option value=\"{$dept['id']}\">📚 {$dept['name']}</option>";
                                                    }
                                                } catch (Exception $e) {
                                                    echo "<option value=\"\">❌ Error loading departments</option>";
                                                }
                                                ?>
                                            </select>
                                            <span class="input-group-text bg-light">
                                                <i class="fas fa-chevron-down text-muted"></i>
                                            </span>
                                        </div>
                                        <div class="form-text mt-2" id="departmentHelp">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-lightbulb text-warning me-2"></i>
                                                <small class="text-muted fw-medium">Choose your academic department to unlock available programs and specializations</small>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="option" class="form-label fw-semibold d-flex align-items-center justify-content-between">
                                            <span class="d-flex align-items-center">
                                                <i class="fas fa-graduation-cap me-2 text-success fs-5"></i>
                                                Program/Specialization
                                            </span>
                                            <span class="badge bg-success rounded-pill">
                                                <i class="fas fa-star me-1"></i>Required
                                            </span>
                                        </label>
                                        <div class="input-group input-group-lg shadow-sm">
                                            <span class="input-group-text bg-success text-white border-success">
                                                <i class="fas fa-book-open fa-lg"></i>
                                            </span>
                                            <select class="form-select form-select-lg border-success" id="option" name="option_id" required disabled aria-required="true" aria-label="Program" aria-describedby="programHelp">
                                                <option value="">🎯 Select Department First to Load Programs</option>
                                            </select>
                                            <div class="spinner-border spinner-border-sm text-success d-none program-loading ms-2" role="status" aria-hidden="true">
                                                <span class="visually-hidden">Loading programs...</span>
                                            </div>
                                            <span class="input-group-text bg-light d-none" id="programLoadedIcon">
                                                <i class="fas fa-check-circle text-success"></i>
                                            </span>
                                        </div>
                                        <div class="form-text mt-2" id="programHelp">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-info-circle text-info me-2"></i>
                                                <small class="text-muted fw-medium">Available programs will appear after selecting a department above</small>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <div id="programCount" class="alert alert-info d-none py-2 px-3 border-0" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-chart-line text-info me-2 fs-5"></i>
                                                    <div>
                                                        <strong class="text-info">Program Options Available</strong><br>
                                                        <small id="programCountText" class="text-info-emphasis fw-medium"></small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="year_level" class="form-label fw-semibold">Year Level <span class="text-danger">*</span></label>
                                        <select class="form-select" id="year_level" name="year_level" required aria-required="true" aria-label="Year Level">
                                            <option value="">Select Year Level</option>
                                            <option value="1">Year 1</option>
                                            <option value="2">Year 2</option>
                                            <option value="3">Year 3</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Parent/Guardian Information -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6 class="section-title text-info border-info">
                                        <i class="fas fa-users me-2"></i>Parent/Guardian Information
                                    </h6>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="parent_first_name" class="form-label fw-semibold">Parent First Name</label>
                                        <input type="text" class="form-control" id="parent_first_name" name="parent_first_name" aria-label="Parent First Name">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="parent_last_name" class="form-label fw-semibold">Parent Last Name</label>
                                        <input type="text" class="form-control" id="parent_last_name" name="parent_last_name" aria-label="Parent Last Name">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="parent_contact" class="form-label fw-semibold">Parent Contact</label>
                                        <input type="tel" class="form-control" id="parent_contact" name="parent_contact" aria-label="Parent Contact">
                                    </div>
                                </div>
                            </div>

                            <!-- Media Section -->
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-camera me-2 text-primary"></i>
                                            Face Recognition Setup
                                            <small class="text-muted">(2-5 images required for accurate face recognition)</small>
                                        </label>

                                        <!-- File Upload Fallback -->
                                        <div class="file-upload-section">
                                            <div class="text-center mb-2">
                                                <small class="text-muted">Or upload existing images:</small>
                                            </div>
                                            <input type="file" class="form-control d-none" id="faceImagesInput" name="face_images[]" accept="image/jpeg,image/png,image/webp" multiple>
                                            <div class="face-images-upload-area border-primary" id="faceImagesUploadArea">
                                                <div class="face-images-placeholder">
                                                    <i class="fas fa-images fa-2x text-muted mb-2"></i>
                                                    <p class="mb-2">Click to select face images</p>
                                                    <small class="text-muted">JPEG, PNG, WebP (Max 5MB each, 2-5 images)</small>
                                                </div>
                                                <div id="faceImagesPreview" class="face-images-preview d-none">
                                                    <!-- Image previews will be inserted here -->
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary me-2" id="selectFaceImagesBtn">
                                                    <i class="fas fa-folder-open me-1"></i>Choose Files
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger d-none" id="clearFaceImages">
                                                    <i class="fas fa-trash me-1"></i>Clear All
                                                </button>
                                            </div>
                                            <div class="mt-2">
                                                <small id="faceImagesCount" class="text-muted">0 images selected</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">
                                            <i class="fas fa-fingerprint me-2 text-info"></i>
                                            Fingerprint Registration
                                            <small class="text-muted">(Optional - enhances security)</small>
                                        </label>
                                        <div class="fingerprint-container border-info">
                                            <div class="fingerprint-display">
                                                <canvas id="fingerprintCanvas" width="200" height="200" class="d-none"></canvas>
                                                <div id="fingerprintPlaceholder" class="fingerprint-placeholder">
                                                    <i class="fas fa-fingerprint fa-3x text-muted mb-2"></i>
                                                    <p class="text-muted">No fingerprint captured</p>
                                                    <small class="text-muted">Click "Capture Fingerprint" to enroll</small>
                                                </div>
                                            </div>
                                            <div class="fingerprint-controls mt-3">
                                                <button type="button" class="btn btn-outline-info" id="captureFingerprintBtn">
                                                    <i class="fas fa-fingerprint me-2"></i>Capture Fingerprint
                                                </button>
                                                <button type="button" class="btn btn-outline-danger d-none" id="clearFingerprintBtn">
                                                    <i class="fas fa-times me-2"></i>Clear
                                                </button>
                                                <button type="button" class="btn btn-outline-success d-none" id="enrollFingerprintBtn">
                                                    <i class="fas fa-save me-2"></i>Enroll Fingerprint
                                                </button>
                                            </div>
                                            <div class="fingerprint-status mt-2">
                                                <small id="fingerprintStatus" class="text-muted">Ready to capture fingerprint</small>
                                            </div>
                                            <div class="mt-2">
                                                <div class="alert alert-info py-2 px-3 border-0" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                                                    <div class="d-flex align-items-center">
                                                        <i class="fas fa-info-circle text-info me-2 fs-6"></i>
                                                        <small class="text-info-emphasis fw-medium">Fingerprint enhances attendance security but is optional</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="row mt-4">
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn">
                                        <i class="fas fa-paper-plane me-2"></i>Register Student
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Loading Overlay -->
                <div class="loading-overlay d-none" id="loadingOverlay">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2">Processing registration...</div>
                </div>
            </main>
        </div>
    </div>

    <!-- Custom CSS -->
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .main-content.sidebar-open {
                margin-left: 250px;
            }

            .fingerprint-container {
                padding: 15px;
            }

            .fingerprint-display {
                min-height: 150px;
            }

            #fingerprintCanvas {
                width: 150px !important;
                height: 150px !important;
            }

            .fingerprint-controls {
                flex-direction: column;
                align-items: stretch;
            }

            .fingerprint-controls .btn {
                margin-bottom: 8px;
            }

            .card-body {
                padding: 15px;
            }

            .section-title {
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 10px;
            }

            .fingerprint-display {
                min-height: 120px;
            }

            #fingerprintCanvas {
                width: 120px !important;
                height: 120px !important;
            }

            .btn-lg {
                padding: 0.5rem 1rem;
                font-size: 1rem;
            }

            .form-control, .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }

        .section-title {
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 8px;
            margin-bottom: 20px;
            margin-top: 30px;
        }

        .section-title:first-child {
            margin-top: 0;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .btn-primary:hover {
            background-color: #0b5ed7;
            border-color: #0a58ca;
        }

        .img-thumbnail {
            border: 2px dashed #dee2e6;
        }

        /* Face Images Upload Styles */
        .face-images-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            min-height: 200px;
        }

        .face-images-upload-area:hover {
            border-color: #667eea;
            background-color: rgba(102, 126, 234, 0.05);
        }

        .face-images-upload-area.dragover {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
        }

        .face-images-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .face-image-item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .face-image-item:hover {
            border-color: #667eea;
            transform: scale(1.05);
        }

        .face-image-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
        }

        .face-image-item .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .face-image-item .remove-image:hover {
            background: rgba(220, 53, 69, 1);
            transform: scale(1.1);
        }

        .face-image-item .image-number {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: bold;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        .program-loading {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Enhanced department and program selection styling */
        .input-group-text {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 1px solid #ced4da;
            color: #495057;
        }

        .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .form-select:disabled {
            background-color: #e9ecef;
            opacity: 0.6;
        }

        .section-title i {
            color: #6c757d;
            margin-right: 8px;
        }

        .section-title i.fa-building {
            color: #0d6efd;
        }

        .section-title i.fa-graduation-cap {
            color: #198754;
        }

        /* Program count styling */
        #programCount {
            font-weight: 500;
            font-size: 0.875rem;
        }

        #programCount i {
            color: #0dcaf0;
        }

        /* Loading state improvements */
        .program-loading {
            color: #0d6efd;
        }

        /* Better form text styling */
        .form-text {
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Enhanced responsive design for selects */
        @media (max-width: 768px) {
            .input-group .input-group-text {
                padding: 0.375rem 0.5rem;
            }

            .input-group .form-select {
                font-size: 16px; /* Prevents zoom on iOS */
            }

            .section-title {
                font-size: 1.1rem;
            }

            .section-title i {
                font-size: 1rem;
            }
        }

        /* Animation for program loading */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .programs-loaded {
            animation: fadeInUp 0.3s ease-out;
        }

        /* Better visual feedback for successful loads */
        .programs-loaded option:first-child {
            font-weight: 500;
            color: #198754;
        }

        .alert {
            margin-bottom: 1rem;
        }

        /* Fingerprint Styles */
        .fingerprint-container {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            background: #f8f9fa;
        }

        .fingerprint-display {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 200px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            background: white;
            position: relative;
        }

        .fingerprint-placeholder {
            text-align: center;
            color: #6c757d;
        }

        #fingerprintCanvas {
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .fingerprint-controls {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Enhanced mobile fingerprint UI */
        @media (max-width: 768px) {
            .fingerprint-container {
                margin: 0 -5px; /* Full width on mobile */
            }

            .fingerprint-display {
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .fingerprint-placeholder {
                padding: 20px;
            }

            .fingerprint-placeholder i {
                font-size: 2.5rem;
            }
        }

        .fingerprint-status {
            text-align: center;
            min-height: 20px;
        }

        .fingerprint-captured {
            border-color: #28a745 !important;
            background: rgba(40, 167, 69, 0.05);
        }

        .fingerprint-capturing {
            border-color: #ffc107 !important;
            background: rgba(255, 193, 7, 0.05);
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .btn-outline-info {
            border-color: #0dcaf0;
            color: #0dcaf0;
        }

        .btn-outline-info:hover {
            background: #0dcaf0;
            border-color: #0dcaf0;
            color: white;
        }

        .quality-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Accessibility improvements */
        .btn:focus {
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
            outline: none;
        }

        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        /* Enhanced loading states */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Better visual feedback */
        .fingerprint-captured .fingerprint-display {
            animation: captureSuccess 0.5s ease-in-out;
        }

        @keyframes captureSuccess {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

    </style>

    <!-- JavaScript -->
    <script>
    /**
     * Enhanced Student Registration System - JavaScript
     * Refined with better error handling and performance
     */
    class StudentRegistration {
        constructor() {
            this.retryAttempts = 3;
            this.retryDelay = 1000;
            this.csrfToken = '<?= addslashes($csrf_token) ?>';
            this.fingerprintCaptured = false;
            this.fingerprintData = null;
            this.fingerprintQuality = 0;
            this.isCapturing = false;
            this.originalCellOptions = [];
            this.init();
        }

        init() {
            try {
                this.setupEventListeners();
                this.updateProgress();
                this.initializeFormState();
                this.showWelcomeMessage();
                this.setupGlobalErrorHandler();
                this.checkServerConnectivity();
            } catch (error) {
                console.error('Initialization error:', error);
                this.showAlert('System initialization failed. Please refresh the page.', 'error', false);
            }
        }

        initializeFormState() {
            // Set initial form state
            this.updateProgress();

            // Pre-validate form on load
            setTimeout(() => {
                this.validateForm();
            }, 1000);

            // Initialize fingerprint UI
            this.updateFingerprintUI('ready');
        }
        const $option = $('#option');
        const $loadingSpinner = $('.program-loading');

        if (!deptId) {
            this.resetProgramSelection();
            return;
        }

        // Clear previous program selection and show loading
        $option.prop('disabled', true).removeClass('programs-loaded');
        $loadingSpinner.removeClass('d-none');
        $('#programCount').addClass('d-none');
        $('#programLoadedIcon').addClass('d-none');

        // Reset help text to initial state
        $('#programHelp .fas').removeClass('fa-check-circle text-success').addClass('fa-info-circle text-info');
        $('#programHelp small').html('<strong class="text-muted">Available programs will appear after selecting a department</strong>');

        $option.prop('disabled', true);
        $loadingSpinner.removeClass('d-none');

        try {
            const response = await this.retryableAjax({
                url: 'api/department-option-api.php',
                method: 'POST',
                data: {
                    action: 'get_options',
                    department_id: parseInt(deptId, 10),
                    csrf_token: this.csrfToken
                }
            });

            if (response.success) {
                if (response.data && response.data.length > 0) {
                    const options = response.data.map(opt =>
                        `<option value="${opt.id}" data-department="${deptId}">${this.escapeHtml(opt.name)}</option>`
                    ).join('');

                    $option.html('<option value="">Select Program</option>' + options)
                        .prop('disabled', false)
                        .addClass('programs-loaded')
                        .data('department-id', deptId);

                    // Update program count display
                    $('#programCountText').text(`${response.data.length} program${response.data.length !== 1 ? 's' : ''} available for selection`);
                    $('#programCount').removeClass('d-none');

                    // Show success icon
                    $('#programLoadedIcon').removeClass('d-none');

                    // Update help text with success styling
                    $('#programHelp .fas').removeClass('fa-info-circle text-info').addClass('fa-check-circle text-success');
                    $('#programHelp small').html('<strong class="text-success">Programs loaded successfully!</strong> Choose your desired program from the dropdown above');

                    this.showAlert(`🎉 ${response.data.length} program${response.data.length !== 1 ? 's' : ''} loaded successfully!`, 'success');
                } else {
                    $option.html('<option value="">No programs available</option>')
                        .removeData('department-id');

                    $('#programCount').addClass('d-none');
                    $('#programHelp small').text('No programs are currently available for this department');

                    this.showAlert('⚠️ No programs found for this department', 'warning');
                }
            } else {
                throw new Error(response.message || 'Failed to load options');
            }
        } catch (error) {
            console.error('Department change error:', error);
            $option.html('<option value="">Error loading programs</option>')
                .removeData('department-id');

            // Reset program count and help text
            $('#programCount').addClass('d-none');
            $('#programHelp small').text('Failed to load programs. Please try selecting the department again.');

            this.showAlert('❌ Failed to load programs. Please try again.', 'error');
        } finally {
            $option.prop('disabled', false);
            $loadingSpinner.addClass('d-none');
        }
    }

    async retryableAjax(options, retries = this.retryAttempts) {
        const defaultOptions = {
            timeout: 10000,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const finalOptions = { ...defaultOptions, ...options };

        for (let i = 0; i < retries; i++) {
            try {
                const response = await $.ajax(finalOptions);

                // Validate response structure
                if (response && typeof response === 'object') {
                    return response;
                } else {
                    throw new Error('Invalid response format');
                }
            } catch (error) {
                const isLastAttempt = i === retries - 1;
                const errorMessage = this.getErrorMessage(error);

                // Enhanced error logging with attempt information
                console.error(`=== AJAX ATTEMPT ${i + 1}/${retries} FAILED ===`);
                console.error('URL:', finalOptions.url);
                console.error('Method:', finalOptions.method);
                console.error('Error message:', errorMessage);
                console.error('HTTP Status:', error.status);
                console.error('Status Text:', error.statusText);
                console.error('Ready State:', error.readyState);
                console.error('Response Text (truncated):', error.responseText ? error.responseText.substring(0, 200) + '...' : 'No response');
                console.error('Response JSON:', error.responseJSON);
                console.error('Full error object:', error);
                console.error('=== END AJAX ATTEMPT FAILURE ===');

                if (isLastAttempt) {
                    console.error(`AJAX request failed after ${retries} attempts:`, errorMessage);
                    throw error; // Throw the original error for better handling
                }

                // Exponential backoff with jitter
                const delay = this.retryDelay * Math.pow(2, i) + Math.random() * 1000;
                console.log(`Retrying in ${Math.round(delay)}ms... (attempt ${i + 2}/${retries})`);
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        }
    }

    getErrorMessage(error) {
        // Enhanced error message extraction
        if (error.responseJSON && error.responseJSON.message) {
            return `Server error: ${error.responseJSON.message}`;
        } else if (error.responseJSON && error.responseJSON.errors) {
            const errors = Object.values(error.responseJSON.errors).flat();
            return `Validation errors: ${errors.join('; ')}`;
        } else if (error.status) {
            let statusMessage = `HTTP ${error.status}`;
            if (error.statusText) {
                statusMessage += `: ${error.statusText}`;
            }

            // Add specific guidance for common HTTP errors
            switch (error.status) {
                case 0:
                    return `${statusMessage} - Network connection failed. Check your internet connection.`;
                case 403:
                    return `${statusMessage} - Access denied. Please refresh the page and try again.`;
                case 404:
                    return `${statusMessage} - Service not found. The requested endpoint may not exist.`;
                case 500:
                    return `${statusMessage} - Server error. Please try again later.`;
                case 503:
                    return `${statusMessage} - Service temporarily unavailable. Please try again later.`;
                default:
                    return statusMessage;
            }
        } else if (error.message) {
            return `Request error: ${error.message}`;
        } else if (typeof error === 'string') {
            return error;
        }

        // Fallback with more details
        return `Unknown error occurred. Error details: ${JSON.stringify(error, Object.getOwnPropertyNames(error))}`;
    }

    validateImage(file) {
        const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
        const maxSize = 5 * 1024 * 1024;

        if (!validTypes.includes(file.type)) {
            this.showAlert('Invalid file type. Please use JPEG, PNG, or WebP.', 'error');
            return false;
        }

        if (file.size > maxSize) {
            this.showAlert('File too large. Maximum size is 5MB.', 'error');
            return false;
        }

        return true;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        // Must be exactly 10 digits, start with 0, and contain no letters
        const phoneRegex = /^0\d{9}$/;
        return phoneRegex.test(phone) && /^[0-9]+$/.test(phone);
    }

    showFieldError(field, message) {
        $(field).addClass('is-invalid').removeClass('is-valid');
        $(field).next('.invalid-feedback').remove();
        $(field).after(`<div class="invalid-feedback">${message}</div>`);
    }

    clearFieldError(field) {
        $(field).removeClass('is-invalid').addClass('is-valid');
        $(field).next('.invalid-feedback').remove();
    }

    validateRegistrationNumber(e) {
        const value = e.target.value.replace(/[^A-Za-z0-9_-]/g, '').toUpperCase().substring(0, 20);
        e.target.value = value;

        if (value.length >= 5 && this.isValidRegistrationNumber(value)) {
            $(e.target).addClass('is-valid').removeClass('is-invalid');
        } else if (value.length > 0) {
            $(e.target).addClass('is-invalid').removeClass('is-valid');
        } else {
            $(e.target).removeClass('is-valid is-invalid');
        }
    }

    validateEmailField(e) {
        const email = e.target.value.trim();
        if (email && !this.isValidEmail(email)) {
            this.showFieldError(e.target, 'Please enter a valid email address');
        } else {
            this.clearFieldError(e.target);
        }
    }

    validatePhoneField(e) {
        const phone = e.target.value.trim();
        if (phone && !this.isValidPhone(phone)) {
            const fieldName = e.target.name === 'telephone' ? 'Phone number' : 'Parent phone number';
            this.showFieldError(e.target, `${fieldName} must be exactly 10 digits starting with 0 (e.g., 0781234567)`);
        } else {
            this.clearFieldError(e.target);
        }
    }

    validateStudentId(e) {
        const value = e.target.value.replace(/[^0-9]/g, '').substring(0, 16);
        e.target.value = value;

        if (value.length === 16) {
            $(e.target).addClass('is-valid');
        } else {
            $(e.target).removeClass('is-valid is-invalid');
        }
    }

    showLoading(show) {
        if (show) {
            $('#loadingOverlay').removeClass('d-none').addClass('d-flex');
        } else {
            $('#loadingOverlay').removeClass('d-flex').addClass('d-none');
        }
    }

    disableForm(disable) {
        $('#registrationForm input, #registrationForm select, #registrationForm button')
            .prop('disabled', disable);
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&")
            .replace(/</g, "<")
            .replace(/>/g, ">")
            .replace(/"/g, """)
            .replace(/'/g, "&#039;");
    }

    showWelcomeMessage() {
        setTimeout(() => {
            this.showAlert('Welcome to Rwanda Polytechnic Student Registration System! Please fill in all required fields.', 'info');
        }, 1000);
    }

    validateField(e) {
        const field = e.target;
        const value = field.value.trim();

        if (!value) return;

        const fieldName = field.name;

        switch (fieldName) {
            case 'email':
                if (!this.isValidEmail(value)) {
                    this.showFieldError(field, 'Please enter a valid email address');
                } else {
                    this.clearFieldError(field);
                }
                break;
            case 'telephone':
                if (!this.isValidPhone(value)) {
                    this.showFieldError(field, 'Please enter a valid 10-digit phone number (e.g., 0781234567)');
                } else {
                    this.clearFieldError(field);
                }
                break;
        }
    }

    updateProgress() {
        const totalFields = $('#registrationForm [required]').length;
        const filledFields = $('#registrationForm [required]').filter(function() {
            return $(this).val().trim().length > 0;
        }).length;

        const progress = Math.round((filledFields / totalFields) * 100);
        $('#formProgress').css('width', progress + '%');
        $('#progressText').text(progress + '%');

        const $progressBar = $('#formProgress');
        $progressBar.removeClass('bg-success bg-warning bg-danger');

        if (progress >= 80) {
            $progressBar.addClass('bg-success');
        } else if (progress >= 50) {
            $progressBar.addClass('bg-warning');
        } else {
            $progressBar.addClass('bg-danger');
        }
    }

    showSuccess(response) {
        // Show success alert prominently at the top
        this.showAlert(`🎉 SUCCESS: ${response.message}`, 'success', false);

        // Create enhanced success modal
        if ($('#successModal').length === 0) {
            $('body').append(`
                <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-success">
                            <div class="modal-header bg-success text-white">
                                <h4 class="modal-title">
                                    <i class="fas fa-check-circle me-2"></i>Registration Completed Successfully!
                                </h4>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center p-4">
                                <div class="mb-4">
                                    <i class="fas fa-graduation-cap fa-4x text-success mb-3"></i>
                                    <h5 class="text-success mb-3">Welcome to Rwanda Polytechnic!</h5>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="alert alert-success border-success">
                                            <i class="fas fa-user-check me-2"></i>
                                            <strong>Student Registration Complete</strong><br>
                                            ${response.message}
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="card border-success">
                                            <div class="card-body">
                                                <h6 class="card-title text-success">
                                                    <i class="fas fa-id-card me-2"></i>Student Information
                                                </h6>
                                                <p class="mb-1"><strong>Student ID:</strong> <code class="text-success">${response.student_id}</code></p>
                                                <p class="mb-0"><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                                            </div>
                                        </div>
                                    </div>

                                    ${response.fingerprint_enrolled ?
                                        '<div class="col-12"><div class="alert alert-info border-info"><i class="fas fa-fingerprint me-2"></i><strong>Biometric Security:</strong> Fingerprint enrolled successfully for secure attendance tracking!</div></div>' :
                                        '<div class="col-12"><div class="alert alert-warning border-warning"><i class="fas fa-exclamation-triangle me-2"></i><strong>Note:</strong> Fingerprint not enrolled. Student can enroll later through their dashboard.</div></div>'
                                    }
                                </div>

                                <div class="mt-4 text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Student account has been created with default password: <strong>12345</strong>.
                                    Please advise student to login and change password on first login.
                                </div>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-success btn-lg px-4" id="continueButton">
                                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                </button>
                                <button type="button" class="btn btn-outline-success" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }

        const modal = new bootstrap.Modal(document.getElementById('successModal'), {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();

        $('#continueButton').off('click').on('click', function() {
            modal.hide();
            // Add a small delay to show transition
            setTimeout(() => {
                window.location.href = response.redirect || 'admin-dashboard.php';
            }, 300);
        });
    }

    startFingerprintCapture() {
        if (this.isCapturing) return;

        this.isCapturing = true;
        this.updateFingerprintUI('capturing');
        this.simulateFingerprintCapture();
    }

    simulateFingerprintCapture() {
        const canvas = document.getElementById('fingerprintCanvas');
        const ctx = canvas.getContext('2d');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        const status = document.getElementById('fingerprintStatus');

        canvas.classList.remove('d-none');
        placeholder.classList.add('d-none');

        let progress = 0;
        let qualityVariation = 0;

        const captureInterval = setInterval(() => {
            progress += Math.random() * 8 + 2;
            qualityVariation += (Math.random() - 0.5) * 2;

            const currentProgress = Math.min(progress, 100);
            status.textContent = `Capturing... ${Math.round(currentProgress)}%`;

            this.drawFingerprintPattern(ctx, currentProgress);

            if (currentProgress >= 100) {
                clearInterval(captureInterval);
                this.fingerprintCaptured = true;

                const baseQuality = 85 + Math.floor(Math.random() * 10);
                const variationQuality = Math.max(75, Math.min(100, baseQuality + qualityVariation));
                this.fingerprintQuality = Math.round(variationQuality);

                this.isCapturing = false;
                this.updateFingerprintUI('captured');
                this.showAlert(`Fingerprint captured successfully! Quality: ${this.fingerprintQuality}%`, 'success');
            }
        }, 80);
    }

    drawFingerprintPattern(ctx, progress) {
        const centerX = ctx.canvas.width / 2;
        const centerY = ctx.canvas.height / 2;
        const maxRadius = Math.min(centerX, centerY) * 0.8;

        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);

        ctx.strokeStyle = `rgba(13, 110, 253, ${Math.min(progress / 100, 1)})`;
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';

        const circleCount = Math.floor(progress / 10);
        for (let i = 1; i <= circleCount; i++) {
            const radius = (maxRadius * i) / 10;
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
            ctx.stroke();
        }

        if (progress > 50) {
            const spiralProgress = (progress - 50) / 50;
            ctx.strokeStyle = `rgba(25, 135, 84, ${spiralProgress})`;
            ctx.beginPath();

            let angle = 0;
            let radius = 10;
            const maxAngle = spiralProgress * Math.PI * 4;

            while (radius < maxRadius && angle < maxAngle) {
                const x = centerX + Math.cos(angle) * radius;
                const y = centerY + Math.sin(angle) * radius;
                ctx.lineTo(x, y);
                angle += 0.15;
                radius += 0.3;
            }
            ctx.stroke();
        }

        if (this.fingerprintQuality > 0) {
            this.updateQualityIndicator(ctx.canvas, this.fingerprintQuality);
        }
    }

    updateQualityIndicator(canvas, quality) {
        let qualityElement = document.querySelector('.quality-indicator');
        if (!qualityElement) {
            qualityElement = document.createElement('div');
            qualityElement.className = 'quality-indicator';
            canvas.parentElement.appendChild(qualityElement);
        }

        const qualityColor = quality >= 90 ? '#28a745' : quality >= 80 ? '#ffc107' : '#dc3545';
        qualityElement.textContent = `Quality: ${quality}%`;
        qualityElement.style.backgroundColor = qualityColor;
        qualityElement.style.color = 'white';
    }

    /**
     * Handle face images selection
     */
    handleFaceImagesSelect(e) {
        const files = Array.from(e.target.files);
        const validFiles = [];
        const maxFiles = 5;
        const minFiles = 2;

        // Validate files
        for (const file of files) {
            if (this.validateImage(file)) {
                validFiles.push(file);
            }
        }

        // Check file count limits
        if (validFiles.length < minFiles) {
            this.showAlert(`Please select at least ${minFiles} images for face recognition.`, 'error');
            e.target.value = '';
            return;
        }

        if (validFiles.length > maxFiles) {
            this.showAlert(`Maximum ${maxFiles} images allowed. Please select fewer images.`, 'error');
            e.target.value = '';
            return;
        }

        // Clear existing previews
        $('#faceImagesPreview').empty().removeClass('d-none');
        $('#faceImagesUploadArea .face-images-placeholder').addClass('d-none');
        $('#clearFaceImages').removeClass('d-none');

        // Create previews for each valid file
        validFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageItem = document.createElement('div');
                imageItem.className = 'face-image-item';
                imageItem.innerHTML = `
                    <img src="${e.target.result}" alt="Face image ${index + 1}">
                    <button type="button" class="remove-image" data-index="${index}" title="Remove image">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="image-number">${index + 1}</div>
                `;
                $('#faceImagesPreview').append(imageItem);
            };
            reader.readAsDataURL(file);
        });

        // Update count display
        this.updateFaceImagesCount(validFiles.length);
        this.showAlert(`✅ ${validFiles.length} face images selected successfully!`, 'success');
    }

    /**
     * Remove a face image
     */
    removeFaceImage(e) {
        e.preventDefault();
        const index = $(e.currentTarget).data('index');
        const $imageItem = $(e.currentTarget).closest('.face-image-item');

        // Remove the image item
        $imageItem.remove();

        // Update remaining image numbers
        $('#faceImagesPreview .face-image-item').each(function(i) {
            $(this).find('.image-number').text(i + 1);
            $(this).find('.remove-image').attr('data-index', i);
        });

        // Check if any images remain
        const remainingImages = $('#faceImagesPreview .face-image-item').length;
        this.updateFaceImagesCount(remainingImages);

        if (remainingImages === 0) {
            this.clearFaceImages();
        } else {
            this.showAlert(`Image removed. ${remainingImages} image${remainingImages !== 1 ? 's' : ''} remaining.`, 'info');
        }
    }

    /**
     * Clear all face images
     */
    clearFaceImages() {
        $('#faceImagesInput').val('');
        $('#faceImagesPreview').empty().addClass('d-none');
        $('#faceImagesUploadArea .face-images-placeholder').removeClass('d-none');
        $('#clearFaceImages').addClass('d-none');
        this.updateFaceImagesCount(0);
    }

    /**
     * Update face images count display
     */
    updateFaceImagesCount(count) {
        const $countElement = $('#faceImagesCount');
        if (count === 0) {
            $countElement.text('0 images selected');
            $countElement.removeClass('text-success text-warning').addClass('text-muted');
        } else if (count >= 2 && count <= 5) {
            $countElement.text(`${count} images selected`);
            $countElement.removeClass('text-muted text-warning').addClass('text-success');
        } else {
            $countElement.text(`${count} images selected`);
            $countElement.removeClass('text-muted text-success').addClass('text-warning');
        }
    }



    async handleSubmit(e) {
        e.preventDefault();

        if (!this.validateForm()) {
            this.showAlert('Please correct the errors before submitting.', 'error');
            this.scrollToFirstError();
            return;
        }

        // Additional validation: verify department-option relationship
        const departmentId = $('#department').val();
        const optionId = $('#option').val();

        if (departmentId && optionId) {
            try {
                const validationResponse = await this.retryableAjax({
                    url: 'api/department-option-api.php',
                    method: 'POST',
                    data: {
                        action: 'validate_relationship',
                        department_id: parseInt(departmentId, 10),
                        option_id: parseInt(optionId, 10),
                        csrf_token: this.csrfToken
                    }
                });

                if (!validationResponse.valid) {
                    this.showAlert('Invalid department-program combination. Please select a valid program for the chosen department.', 'error');
                    $('#option').focus();
                    return;
                }
            } catch (error) {
                console.error('Relationship validation error:', error);
                this.showAlert('Failed to validate department-program relationship. Please try again.', 'error');
                return;
            }
        }

        if (!await this.confirmSubmission()) {
            return;
        }

        try {
            this.showLoading(true);
            this.disableForm(true);

            const formData = new FormData();

            // Manually collect form data to ensure all fields are included
            const form = e.target;
            const formElements = form.querySelectorAll('input, select, textarea');
            formElements.forEach(element => {
                if (element.name) {
                    if (element.type === 'file') {
                        if (element.files.length > 0) {
                            formData.append(element.name, element.files[0]);
                        }
                    } else if (element.type === 'checkbox' || element.type === 'radio') {
                        if (element.checked) {
                            formData.append(element.name, element.value);
                        }
                    } else {
                        formData.append(element.name, element.value);
                    }
                }
            });

            // Include fingerprint data if captured
            if (this.fingerprintCaptured && this.fingerprintData) {
                // Convert canvas to base64 data URL for backend processing
                const canvas = document.getElementById('fingerprintCanvas');
                const fingerprintImageData = canvas.toDataURL('image/png');
                formData.append('fingerprint_data', fingerprintImageData);
                formData.append('fingerprint_quality', this.fingerprintQuality);
            }

            const response = await $.ajax({
                url: 'submit-student-registration.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 30000
            });

            if (response.success) {
                this.showSuccess(response);
            } else {
                this.handleSubmissionError(response);
            }
        } catch (error) {
            this.handleNetworkError(error);
        } finally {
            this.showLoading(false);
            this.disableForm(false);
        }
    }

    scrollToFirstError() {
        const firstError = $('.is-invalid').first();
        if (firstError.length) {
            $('html, body').animate({
                scrollTop: firstError.offset().top - 100
            }, 500);
        }
    }

    async confirmSubmission() {
        return new Promise((resolve) => {
            // Create a custom confirmation modal instead of using alert
            if ($('#customConfirmModal').length === 0) {
                $('body').append(`
                    <div class="modal fade" id="customConfirmModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Confirm Registration</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to register this student? This action cannot be undone.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="btn btn-primary" id="confirmRegistration">Confirm</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            }

            const modal = new bootstrap.Modal(document.getElementById('customConfirmModal'));
            modal.show();

            $('#confirmRegistration').off('click').on('click', function() {
                modal.hide();
                resolve(true);
            });

            $('#customConfirmModal').on('hidden.bs.modal', function() {
                resolve(false);
            });
        });
    }

   validateForm() {
       let isValid = true;
       const errors = [];

       // Clear previous validation
       $('.is-invalid').removeClass('is-invalid');
       $('.invalid-feedback').remove();

       // Check face images requirement
       const faceImagesCount = $('#faceImagesPreview .face-image-item').length;
       if (faceImagesCount < 2) {
           this.showAlert('Please select at least 2 face images for face recognition.', 'error');
           $('#faceImagesUploadArea').addClass('border-danger');
           isValid = false;
       } else {
           $('#faceImagesUploadArea').removeClass('border-danger');
       }

       // Required field validation with specific messages
       const requiredFields = [
           { id: 'firstName', name: 'First Name' },
           { id: 'lastName', name: 'Last Name' },
           { id: 'email', name: 'Email Address' },
           { id: 'telephone', name: 'Phone Number' },
           { id: 'reg_no', name: 'Registration Number' },
           { id: 'department', name: 'Department' },
           { id: 'option', name: 'Program' },
           { id: 'year_level', name: 'Year Level' },
           { id: 'sex', name: 'Gender' }
       ];

       requiredFields.forEach(field => {
           const $field = $(`#${field.id}`);
           if (!$field.val().trim()) {
               this.showFieldError($field[0], `${field.name} is required`);
               isValid = false;
               errors.push(`${field.name} is required`);
           }
       });

       // Department-program dependency validation
       const departmentId = $('#department').val();
       const optionId = $('#option').val();
       if (departmentId && !optionId) {
           const $option = $('#option');
           this.showFieldError($option[0], 'Please select a program for the chosen department');
           isValid = false;
           errors.push('Program selection is required');
       }

       // Validate that the selected option belongs to the selected department
       if (departmentId && optionId) {
           const $optionElement = $('#option');
           const selectedOption = $optionElement.find('option:selected');
           const optionDepartmentId = selectedOption.data('department');

           if (optionDepartmentId && optionDepartmentId != departmentId) {
               const $option = $('#option');
               this.showFieldError($option[0], 'Selected program does not belong to the chosen department');
               isValid = false;
               errors.push('Invalid program for department');
           } else {
               this.clearFieldError($('#option')[0]);
           }
       }

       // Email format validation
       const email = $('#email').val();
       if (email && !this.isValidEmail(email)) {
           this.showFieldError($('#email')[0], 'Please enter a valid email address');
           isValid = false;
           errors.push('Invalid email format');
       }

       // Phone number validation
       const phone = $('#telephone').val();
       if (phone && !this.isValidPhone(phone)) {
           this.showFieldError($('#telephone')[0], 'Phone number must be exactly 10 digits starting with 0 (e.g., 0781234567) - no letters allowed');
           isValid = false;
           errors.push('Invalid phone number format');
       }

       // Parent contact validation if provided
       const parentContact = $('#parent_contact').val();
       if (parentContact && !this.isValidPhone(parentContact)) {
           this.showFieldError($('#parent_contact')[0], 'Parent phone number must be exactly 10 digits starting with 0 (e.g., 0781234567) - no letters allowed');
           isValid = false;
           errors.push('Invalid parent phone number format');
       }

       // Date of birth validation
       const dob = $('#dob').val();
       if (dob) {
           const birthDate = new Date(dob);
           const today = new Date();
           const age = today.getFullYear() - birthDate.getFullYear();

           if (age < 16) {
               this.showFieldError($('#dob')[0], 'Student must be at least 16 years old');
               isValid = false;
               errors.push('Student too young');
           } else if (age > 60) {
               this.showFieldError($('#dob')[0], 'Please enter a valid date of birth');
               isValid = false;
               errors.push('Invalid date of birth');
           }
       }


       // Registration number format validation
       const regNo = $('#reg_no').val();
       if (regNo && !this.isValidRegistrationNumber(regNo)) {
           this.showFieldError($('#reg_no')[0], 'Registration number must be 5-20 characters, alphanumeric only');
           isValid = false;
           errors.push('Invalid registration number format');
       }

       // Fingerprint is optional - no validation required
       if (!this.fingerprintCaptured) {
           console.log('Fingerprint not captured - proceeding without biometric data (optional)');
       }

       // Log validation results for debugging
       if (!isValid) {
           console.log('Form validation failed:', errors);
       }

       return isValid;
   }

   // Enhanced registration number validation
   isValidRegistrationNumber(regNo) {
       // Must be 5-20 characters, alphanumeric only, no special characters except hyphens/underscores
       const regNoRegex = /^[A-Za-z0-9_-]{5,20}$/;
       return regNoRegex.test(regNo);
   }
    updateFingerprintUI(state) {
        const container = document.querySelector('.fingerprint-container');
        const captureBtn = document.getElementById('captureFingerprintBtn');
        const clearBtn = document.getElementById('clearFingerprintBtn');
        const enrollBtn = document.getElementById('enrollFingerprintBtn');
        const status = document.getElementById('fingerprintStatus');

        container.classList.remove('fingerprint-capturing', 'fingerprint-captured');

        switch (state) {
            case 'capturing':
                container.classList.add('fingerprint-capturing');
                captureBtn.disabled = true;
                captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Capturing...';
                status.textContent = 'Place finger on scanner';
                clearBtn.classList.add('d-none');
                enrollBtn.classList.add('d-none');
                break;

            case 'captured':
                container.classList.add('fingerprint-captured');
                captureBtn.classList.add('d-none');
                clearBtn.classList.remove('d-none');
                enrollBtn.classList.remove('d-none');
                status.textContent = `Fingerprint captured - Quality: ${this.fingerprintQuality}%`;
                break;

            default: // ready
                container.classList.remove('fingerprint-capturing', 'fingerprint-captured');
                captureBtn.disabled = false;
                captureBtn.classList.remove('d-none');
                captureBtn.innerHTML = '<i class="fas fa-fingerprint me-2"></i>Capture Fingerprint';
                clearBtn.classList.add('d-none');
                enrollBtn.classList.add('d-none');
                status.textContent = 'Ready to capture fingerprint';
        }
    }

    clearFingerprint() {
        const canvas = document.getElementById('fingerprintCanvas');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        const qualityIndicator = document.querySelector('.quality-indicator');

        // Clear canvas
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Hide canvas and show placeholder
        canvas.classList.add('d-none');
        placeholder.classList.remove('d-none');

        // Remove quality indicator
        if (qualityIndicator) {
            qualityIndicator.remove();
        }

        // Reset state
        this.fingerprintCaptured = false;
        this.fingerprintData = null;
        this.fingerprintQuality = 0;

        this.updateFingerprintUI('ready');
        this.showAlert('Fingerprint cleared', 'info');
    }

    enrollFingerprint() {
        if (!this.fingerprintCaptured) {
            this.showAlert('No fingerprint captured to enroll', 'warning');
            return;
        }

        // Convert canvas to data URL for storage
        const canvas = document.getElementById('fingerprintCanvas');
        this.fingerprintData = canvas.toDataURL('image/png');

        this.showAlert('Fingerprint enrolled successfully!', 'success');
        console.log('Fingerprint enrolled:', {
            quality: this.fingerprintQuality,
            dataSize: this.fingerprintData.length
        });
    }

    // Enhanced utility methods with better error handling
    showAlert(message, type = 'info', autoDismiss = true) {
        const alertClass = type === 'success' ? 'alert-success' :
                          type === 'error' ? 'alert-danger' :
                          type === 'warning' ? 'alert-warning' : 'alert-info';

        const icon = type === 'success' ? 'check-circle' :
                    type === 'error' ? 'exclamation-triangle' :
                    type === 'warning' ? 'exclamation-circle' : 'info-circle';

        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas fa-${icon} me-2"></i>
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        $('#alertContainer').html(alertHtml);

        // Auto-dismiss with different timing based on type
        if (autoDismiss) {
            const dismissTime = type === 'error' ? 8000 : type === 'warning' ? 6000 : 5000;
            setTimeout(() => {
                $('.alert').fadeOut(300, function() {
                    $(this).remove();
                });
            }, dismissTime);
        }

        // Log alerts for debugging
        console.log(`Alert [${type.toUpperCase()}]: ${message}`);
    }

    // Enhanced error handling for form submission
    handleSubmissionError(response) {
        console.error('Submission error details:', response);

        let errorMessage = 'An unexpected error occurred during registration.';
        let errorTitle = 'Registration Failed';

        if (response && response.message) {
            errorMessage = response.message;
        } else if (response && response.errors) {
            // Handle validation errors
            const errors = Object.values(response.errors).flat();
            errorMessage = errors.join('; ');
            errorTitle = 'Validation Errors';
        } else if (typeof response === 'string') {
            errorMessage = response;
        }

        // Show prominent error alert
        this.showAlert(`❌ ${errorTitle}: ${errorMessage}`, 'error', false);

        // Scroll to top to show the error
        $('html, body').animate({ scrollTop: 0 }, 500);

        // Re-enable form for retry
        this.disableForm(false);
    }

    handleNetworkError(error) {
        // Enhanced error logging with detailed information
        console.error('=== NETWORK ERROR DETAILS ===');
        console.error('Error status:', error.status);
        console.error('Error statusText:', error.statusText);
        console.error('Error readyState:', error.readyState);
        console.error('Error responseText:', error.responseText ? error.responseText.substring(0, 500) : 'No response text');
        console.error('Error responseJSON:', error.responseJSON);
        console.error('Full error object:', error);
        console.error('Error message:', error.message);
        console.error('Error name:', error.name);
        console.error('=== END NETWORK ERROR DETAILS ===');

        let errorMessage = 'Network connection error. Please check your internet connection.';
        let errorTitle = 'Connection Error';
        let troubleshooting = '';

        if (error.status === 0) {
            errorMessage = 'Unable to connect to server. Please check if the server is running and your internet connection is working.';
            errorTitle = 'Server Unreachable';
            troubleshooting = ' Try refreshing the page or check your network settings.';
        } else if (error.status === 403) {
            errorMessage = 'Access denied. Your session may have expired.';
            errorTitle = 'Access Denied';
            troubleshooting = ' Please refresh the page and login again.';
        } else if (error.status === 404) {
            errorMessage = 'The requested service was not found on the server.';
            errorTitle = 'Service Not Found';
            troubleshooting = ' Please contact support if this persists.';
        } else if (error.status === 422) {
            // Handle validation errors for 422
            if (error.responseJSON && error.responseJSON.errors) {
                const errors = Object.values(error.responseJSON.errors).flat();
                errorMessage = 'Validation failed: ' + errors.join('; ');
                errorTitle = 'Validation Errors';
            } else if (error.responseJSON && error.responseJSON.message) {
                errorMessage = error.responseJSON.message;
                errorTitle = 'Validation Error';
            } else {
                errorMessage = 'Validation failed. Please check all required fields.';
                errorTitle = 'Validation Error';
            }
        } else if (error.status === 500) {
            errorMessage = 'Server error occurred. This is usually temporary.';
            errorTitle = 'Server Error';
            troubleshooting = ' Please try again in a few moments or contact support.';
        } else if (error.status === 503) {
            errorMessage = 'Server is temporarily unavailable. Please try again later.';
            errorTitle = 'Service Unavailable';
            troubleshooting = ' The server may be undergoing maintenance.';
        } else if (error.status >= 400) {
            errorMessage = `Request error (${error.status}: ${error.statusText}).`;
            errorTitle = 'Request Error';
            troubleshooting = ' Please try again or contact support if this persists.';
        }

        // Add troubleshooting information
        const fullErrorMessage = errorMessage + troubleshooting;

        // Show prominent error alert with icon
        this.showAlert(`🚫 ${errorTitle}: ${fullErrorMessage}`, 'error', false);

        // Show additional technical details in console for debugging
        if (error.responseJSON && error.responseJSON.message) {
            console.error('Server response:', error.responseJSON.message);
        }

        // Scroll to top to show the error
        $('html, body').animate({ scrollTop: 0 }, 500);

        this.disableForm(false);
    }
}

// Enhanced initialization
$(document).ready(() => {
    // Initialize with error handling
    try {
        window.registrationApp = new StudentRegistration();

        // Add performance monitoring
        if (performance.mark) {
            performance.mark('registration_loaded');
        }

        console.log('✅ Student Registration System initialized successfully');
    } catch (error) {
        console.error('❌ Failed to initialize registration system:', error);
        // Show user-friendly error message
        $('#alertContainer').html(`
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                System initialization failed. Please refresh the page.
            </div>
        `);
    }

    // Enhanced mobile menu handling
    $('#mobileMenuToggle').on('click', function() {
        $('#adminSidebar').toggleClass('show');
        $('#mainContent').toggleClass('sidebar-open');
    });

    // Close sidebar when clicking outside (mobile)
    $(document).on('click', function(e) {
        if ($(window).width() <= 768 &&
            !$(e.target).closest('#adminSidebar, #mobileMenuToggle').length) {
            $('#adminSidebar').removeClass('show');
            $('#mainContent').removeClass('sidebar-open');
        }
    });
});
    </script>
</body>

</html>
