<?php
error_reporting(0);
session_start();
require_once "config.php";
require_once "session_check.php"; // Ensure only logged-in admins can access
require_role(['admin']);

// Handle AJAX request for options based on department
if(isset($_POST['get_options'])){
    header('Content-Type: application/json');
    $dep_id = $_POST['dep_id'];
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ? ORDER BY name");
        $stmt->execute([$dep_id]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'options' => $options]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to load options']);
    }
    exit;
}

// AJAX duplicate check
if(isset($_POST['check_duplicate'])){
    header('Content-Type: application/json');
    $field = $_POST['field'];
    $value = trim($_POST['value']);

    if(!in_array($field, ['email','reg_no','telephone']) || empty($value)){
        echo json_encode(['valid' => true]); // Allow empty values
        exit;
    }

    try {
        if($field == 'email'){
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE $field=?");
        }

        $stmt->execute([$value]);
        $count = $stmt->fetchColumn();

        echo json_encode([
            'valid' => ($count == 0),
            'exists' => ($count > 0),
            'message' => $count > 0 ? ucfirst(str_replace('_', ' ', $field)) . ' already exists' : ''
        ]);
    } catch (Exception $e) {
        echo json_encode(['valid' => false, 'message' => 'Validation error']);
    }
    exit;
}

// Handle student registration
if(isset($_POST['register_student'])){
    header('Content-Type: application/json');

    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email', 'reg_no', 'department_id', 'option_id', 'telephone', 'year_level', 'sex'];
    $errors = [];

    foreach($required_fields as $field) {
        if(empty(trim($_POST[$field]))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    // Email validation
    if(!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // Phone validation
    if(!empty($_POST['telephone']) && !preg_match('/^[0-9+\-\s()]+$/', $_POST['telephone'])) {
        $errors[] = 'Invalid telephone format';
    }

    if(!empty($errors)) {
        echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
        exit;
    }

    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $reg_no = trim($_POST['reg_no']);
    $department_id = intval($_POST['department_id']);
    $option_id = intval($_POST['option_id']);
    $telephone = trim($_POST['telephone']);
    $year_level = trim($_POST['year_level']);
    $sex = $_POST['sex'];
    $password = password_hash('12345', PASSWORD_DEFAULT); // Hash the default password

    // Handle photo upload
    $photo_path = '';
    $upload_error = '';

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0){
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB

        if(!in_array($_FILES['photo']['type'], $allowed_types)) {
            $upload_error = 'Invalid file type. Only JPG, PNG, and GIF are allowed.';
        } elseif($_FILES['photo']['size'] > $max_size) {
            $upload_error = 'File size too large. Maximum 5MB allowed.';
        } else {
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo_path = "uploads/students/" . time() . '_' . uniqid() . '.' . $ext;

            if(!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                $upload_error = 'Failed to upload photo.';
            }
        }
    } elseif(isset($_POST['camera_photo']) && $_POST['camera_photo'] != ''){
        // Photo captured from camera (base64)
        try {
            $data = $_POST['camera_photo'];
            if(strpos($data, ',') !== false) {
                list($type, $data) = explode(';', $data);
                list(, $data) = explode(',', $data);
                $data = base64_decode($data);
                $photo_path = "uploads/students/" . time() . "_camera_" . uniqid() . ".png";
                file_put_contents($photo_path, $data);
            }
        } catch (Exception $e) {
            $upload_error = 'Failed to process camera photo.';
        }
    }

    if(!empty($upload_error)) {
        echo json_encode(['success' => false, 'message' => $upload_error]);
        exit;
    }

    // Fingerprint handling
    $fingerprint = "";
    if(isset($_POST['fingerprint_data']) && !empty($_POST['fingerprint_data'])) {
        $fingerprint = $_POST['fingerprint_data'];
    } elseif(isset($_POST['fingerprint_template']) && !empty($_POST['fingerprint_template'])) {
        $fingerprint = $_POST['fingerprint_template'];
    }

    try{
        $pdo->beginTransaction();

        // Check for duplicates before insertion
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email=?");
        $stmt->execute([$email]);
        if($stmt->fetchColumn() > 0) {
            throw new Exception('Email already exists');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE reg_no=?");
        $stmt->execute([$reg_no]);
        if($stmt->fetchColumn() > 0) {
            throw new Exception('Registration number already exists');
        }

        // First insert user
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'student', NOW())");
        $stmt->execute([$first_name.' '.$last_name, $email, $password]);
        $user_id = $pdo->lastInsertId();

        // Insert into students table
        $stmt = $pdo->prepare("INSERT INTO students (user_id, option_id, year_level, first_name, last_name, email, reg_no, department_id, telephone, sex, photo, fingerprint, password, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $option_id, $year_level, $first_name, $last_name, $email, $reg_no, $department_id, $telephone, $sex, $photo_path, $fingerprint, $password]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Student registered successfully!',
            'student_id' => $pdo->lastInsertId(),
            'redirect' => 'admin-dashboard.php'
        ]);

    } catch(Exception $e){
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch departments for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Register Student | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<style>
/* ===== ROOT VARIABLES ===== */
:root {
  --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
  --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
  --light-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  --dark-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
  --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
  --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
  --border-radius: 12px;
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* ===== BODY & CONTAINER ===== */
body {
  background: var(--primary-gradient);
  min-height: 100vh;
  font-family: 'Inter', 'Segoe UI', sans-serif;
  margin: 0;
  position: relative;
  overflow-x: hidden;
}

body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>');
  pointer-events: none;
  z-index: -1;
}

/* ===== SIDEBAR ===== */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: 280px;
  height: 100vh;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  color: #333;
  padding: 30px 0;
  box-shadow: var(--shadow-medium);
  border-right: 1px solid rgba(255, 255, 255, 0.2);
  z-index: 1000;
}

.sidebar-header {
  text-align: center;
  padding: 0 20px 30px;
  border-bottom: 1px solid rgba(0,0,0,0.1);
  margin-bottom: 20px;
}

.sidebar-header h4 {
  color: #667eea;
  font-weight: 700;
  margin-bottom: 5px;
  font-size: 1.2rem;
}

.sidebar a {
  display: block;
  padding: 15px 25px;
  color: #666;
  text-decoration: none;
  font-weight: 500;
  border-radius: 0 25px 25px 0;
  margin: 5px 0;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
}

.sidebar a:hover, .sidebar a.active {
  background: var(--primary-gradient);
  color: white;
  padding-left: 35px;
  transform: translateX(5px);
}

.sidebar a i {
  margin-right: 12px;
  width: 20px;
  text-align: center;
}

/* ===== TOPBAR ===== */
.topbar {
  margin-left: 280px;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  padding: 20px 30px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 900;
  box-shadow: var(--shadow-light);
}

.topbar h5 {
  margin: 0;
  font-weight: 600;
  color: #333;
}

.topbar .badge {
  background: var(--primary-gradient);
  color: white;
  padding: 8px 16px;
  border-radius: 20px;
  font-size: 0.85rem;
}

/* ===== MAIN CONTENT ===== */
.main-content {
  margin-left: 280px;
  padding: 40px 30px;
  min-height: calc(100vh - 80px);
}

/* ===== CARDS ===== */
.card {
  border-radius: var(--border-radius);
  box-shadow: var(--shadow-light);
  border: none;
  transition: var(--transition);
  margin-bottom: 30px;
  background: rgba(255, 255, 255, 0.98);
  backdrop-filter: blur(10px);
  position: relative;
  overflow: hidden;
}

.card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
  background: var(--primary-gradient);
  opacity: 0;
  transition: var(--transition);
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: var(--shadow-medium);
}

.card:hover::before {
  opacity: 1;
}

.card-header {
  background: rgba(255, 255, 255, 0.9);
  border-bottom: 1px solid rgba(0,0,0,0.05);
  padding: 25px 30px;
  border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
}

.card-body {
  padding: 30px;
}

/* ===== FORM ELEMENTS ===== */
.form-label {
  font-weight: 600;
  color: #495057;
  margin-bottom: 8px;
  font-size: 0.95rem;
}

.input-group-text {
  background: var(--primary-gradient);
  color: white;
  border: none;
  border-radius: 8px 0 0 8px;
}

.form-control, .form-select {
  border-radius: 8px;
  border: 2px solid #e9ecef;
  transition: var(--transition);
  font-size: 0.95rem;
  background-color: #ffffff;
  color: #495057;
  padding: 12px 16px;
}

.form-control:focus, .form-select:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.3rem rgba(102, 126, 234, 0.15);
  transform: translateY(-1px);
  background-color: #ffffff;
}

.form-control.is-invalid {
  border-color: #dc3545;
  box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
}

.form-control.is-valid {
  border-color: #198754;
  box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
}

.invalid-feedback, .valid-feedback {
  font-size: 0.875rem;
  margin-top: 5px;
}

/* ===== BUTTONS ===== */
.btn {
  border-radius: 8px;
  font-weight: 600;
  padding: 12px 24px;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
  border: 2px solid transparent;
}

.btn::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 50%;
  transform: translate(-50%, -50%);
  transition: var(--transition);
}

.btn:hover::before {
  width: 300px;
  height: 300px;
}

.btn-primary {
  background: var(--primary-gradient);
  border: none;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
  background: var(--primary-gradient);
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-outline-secondary {
  border: 2px solid #6c757d;
  color: #6c757d;
}

.btn-outline-secondary:hover {
  background: #6c757d;
  border-color: #6c757d;
  transform: translateY(-2px);
}

/* ===== PHOTO UPLOAD ===== */
.photo-upload-area {
  border: 2px dashed #dee2e6;
  border-radius: var(--border-radius);
  padding: 30px;
  text-align: center;
  transition: var(--transition);
  background: rgba(248, 249, 250, 0.5);
  position: relative;
  overflow: hidden;
}

.photo-upload-area:hover {
  border-color: #667eea;
  background: rgba(102, 126, 234, 0.05);
}

.photo-upload-area.dragover {
  border-color: #667eea;
  background: rgba(102, 126, 234, 0.1);
  transform: scale(1.02);
}

.photo-preview {
  max-width: 150px;
  max-height: 150px;
  border-radius: 50%;
  object-fit: cover;
  border: 4px solid #667eea;
  box-shadow: var(--shadow-medium);
}

.camera-container {
  display: none;
  border-radius: var(--border-radius);
  overflow: hidden;
  box-shadow: var(--shadow-medium);
}

#video {
  width: 100%;
  max-width: 300px;
  border-radius: var(--border-radius);
}

/* ===== ALERTS ===== */
.alert {
  border-radius: var(--border-radius);
  border: none;
  box-shadow: var(--shadow-light);
  position: relative;
  overflow: hidden;
  margin-bottom: 20px;
}

.alert::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 4px;
  height: 100%;
  background: var(--primary-gradient);
}

.alert-success::before { background: var(--success-gradient); }
.alert-danger::before { background: var(--danger-gradient); }
.alert-warning::before { background: var(--warning-gradient); }
.alert-info::before { background: var(--info-gradient); }

/* ===== LOADING OVERLAY ===== */
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(5px);
  display: none;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  border-radius: var(--border-radius);
}

.loading-overlay .spinner-border {
  color: #667eea;
  width: 3rem;
  height: 3rem;
}

/* ===== PROGRESS BAR ===== */
.progress-container {
  margin-top: 10px;
}

.progress {
  height: 6px;
  border-radius: 3px;
  background: rgba(0,0,0,0.1);
}

.progress-bar {
  background: var(--primary-gradient);
  border-radius: 3px;
}

/* ===== FOOTER ===== */
.footer {
  text-align: center;
  margin-left: 280px;
  padding: 20px;
  font-size: 0.9rem;
  color: #666;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border-top: 1px solid rgba(255, 255, 255, 0.2);
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    transition: var(--transition);
  }

  .sidebar.show {
    transform: translateX(0);
  }

  .topbar, .main-content, .footer {
    margin-left: 0 !important;
  }

  .topbar {
    padding: 15px 20px;
  }

  .main-content {
    padding: 20px 15px;
  }

  .card-body {
    padding: 20px;
  }

  .photo-upload-area {
    padding: 20px;
  }
}

@media (max-width: 576px) {
  .btn-group {
    flex-direction: column;
  }

  .btn-group .btn {
    margin-bottom: 5px;
  }

  .photo-preview {
    max-width: 120px;
    max-height: 120px;
  }
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.card {
  animation: fadeInUp 0.6s ease-out;
}

.card:nth-child(1) { animation-delay: 0.1s; }
.card:nth-child(2) { animation-delay: 0.2s; }

/* ===== CUSTOM SCROLLBAR ===== */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: var(--primary-gradient);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}
</style>
</head>
<body>
<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <div class="spinner-border mb-3" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h5 class="text-dark mb-2">Processing Registration</h5>
        <p class="text-muted mb-0">Please wait while we register the student...</p>
    </div>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4><i class="fas fa-user-shield me-2"></i>Admin Panel</h4>
        <small class="text-muted">Management System</small>
    </div>
    <a href="admin-dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="register-student.php" class="active"><i class="fas fa-user-plus"></i> Register Student</a>
    <a href="manage-departments.php"><i class="fas fa-building"></i> Manage Departments</a>
    <a href="admin-reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Topbar -->
<div class="topbar">
    <div class="d-flex align-items-center">
        <button class="btn btn-outline-secondary d-md-none me-3" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <h5 class="mb-0 fw-bold">
            <i class="fas fa-user-plus me-2 text-primary"></i>Student Registration
        </h5>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="badge bg-success">
            <i class="fas fa-clock me-1"></i>Live System
        </div>
        <div class="text-muted small">
            <i class="fas fa-calendar me-1"></i><?php echo date('M d, Y'); ?>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Alert Container -->
    <div id="alertContainer"></div>

    <!-- Registration Form Card -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 rounded-circle p-3 me-3">
                        <i class="fas fa-user-plus fa-lg text-primary"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-primary">Student Registration</h5>
                        <small class="text-muted">Add new student to the system</small>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-muted small">Form Progress</div>
                    <div class="progress-container">
                        <div class="progress">
                            <div class="progress-bar" id="formProgress" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form id="registrationForm" enctype="multipart/form-data">
                <input type="hidden" name="register_student" value="1">

                <!-- Personal Information Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-user me-2"></i>Personal Information
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">
                                <i class="fas fa-user text-primary me-1"></i>First Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="first_name" id="first_name" class="form-control"
                                   placeholder="Enter first name" required>
                            <div class="invalid-feedback">Please enter a valid first name.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="last_name" class="form-label">
                                <i class="fas fa-user text-primary me-1"></i>Last Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="last_name" id="last_name" class="form-control"
                                   placeholder="Enter last name" required>
                            <div class="invalid-feedback">Please enter a valid last name.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                <i class="fas fa-envelope text-primary me-1"></i>Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" name="email" id="email" class="form-control"
                                   placeholder="student@example.com" required>
                            <div class="invalid-feedback" id="emailFeedback">Please enter a valid email address.</div>
                            <div class="valid-feedback">Email is available.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="reg_no" class="form-label">
                                <i class="fas fa-id-card text-primary me-1"></i>Registration Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="reg_no" id="reg_no" class="form-control"
                                   placeholder="e.g. 22RP0001" required>
                            <div class="invalid-feedback" id="regnoFeedback">Please enter a valid registration number.</div>
                            <div class="valid-feedback">Registration number is available.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="telephone" class="form-label">
                                <i class="fas fa-phone text-primary me-1"></i>Telephone <span class="text-danger">*</span>
                            </label>
                            <input type="tel" name="telephone" id="telephone" class="form-control"
                                   placeholder="+250 XXX XXX XXX" required>
                            <div class="invalid-feedback" id="telephoneFeedback">Please enter a valid telephone number.</div>
                            <div class="valid-feedback">Telephone number is available.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="sex" class="form-label">
                                <i class="fas fa-venus-mars text-primary me-1"></i>Gender <span class="text-danger">*</span>
                            </label>
                            <select name="sex" id="sex" class="form-select" required>
                                <option value="">-- Select Gender --</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                            <div class="invalid-feedback">Please select a gender.</div>
                        </div>
                    </div>
                </div>

                <!-- Academic Information Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-graduation-cap me-2"></i>Academic Information
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="department" class="form-label">
                                <i class="fas fa-building text-primary me-1"></i>Department <span class="text-danger">*</span>
                            </label>
                            <select name="department_id" id="department" class="form-select" required>
                                <option value="">-- Select Department --</option>
                                <?php foreach($departments as $dep): ?>
                                    <option value="<?= $dep['id'] ?>"><?= htmlspecialchars($dep['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a department.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="option" class="form-label">
                                <i class="fas fa-book text-primary me-1"></i>Program/Option <span class="text-danger">*</span>
                            </label>
                            <select name="option_id" id="option" class="form-select" required disabled>
                                <option value="">-- Select Program First --</option>
                            </select>
                            <div class="invalid-feedback">Please select a program/option.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="year_level" class="form-label">
                                <i class="fas fa-layer-group text-primary me-1"></i>Year Level <span class="text-danger">*</span>
                            </label>
                            <select name="year_level" id="year_level" class="form-select" required>
                                <option value="">-- Select Year Level --</option>
                                <option value="1">Year 1</option>
                                <option value="2">Year 2</option>
                                <option value="3">Year 3</option>
                                <option value="4">Year 4</option>
                            </select>
                            <div class="invalid-feedback">Please select a year level.</div>
                        </div>
                    </div>
                </div>

                <!-- Photo Upload Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-camera me-2"></i>Photo Capture
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="photo-upload-area" id="photoUploadArea">
                                <div id="uploadContent">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <h6 class="text-muted">Upload Photo</h6>
                                    <p class="text-muted small mb-3">Drag & drop or click to select</p>
                                    <input type="file" name="photo" id="photoInput" accept="image/*" class="d-none">
                                    <button type="button" class="btn btn-outline-primary" id="selectPhotoBtn">
                                        <i class="fas fa-folder-open me-2"></i>Select File
                                    </button>
                                </div>
                                <div id="previewContent" class="d-none">
                                    <img id="photoPreview" src="" alt="Photo Preview" class="photo-preview mb-3">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="removePhoto">
                                            <i class="fas fa-trash me-1"></i>Remove
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="changePhoto">
                                            <i class="fas fa-exchange-alt me-1"></i>Change
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-success" id="useCameraBtn">
                                    <i class="fas fa-camera me-2"></i>Use Camera
                                </button>
                                <div class="camera-container" id="cameraContainer">
                                    <video id="video" autoplay playsinline></video>
                                    <div class="d-flex gap-2 mt-2 justify-content-center">
                                        <button type="button" class="btn btn-success btn-sm" id="captureBtn">
                                            <i class="fas fa-camera me-1"></i>Capture
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="cancelCamera">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </button>
                                    </div>
                                </div>
                                <input type="hidden" name="camera_photo" id="cameraPhoto">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fingerprint Section -->
                <div class="mb-4">
                    <h6 class="fw-bold text-primary mb-3">
                        <i class="fas fa-fingerprint me-2"></i>Biometric Data
                    </h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <div id="fingerprintStatus" class="mb-3">
                                        <i class="fas fa-fingerprint fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">Fingerprint Not Captured</h6>
                                        <small class="text-muted">Click capture to enroll fingerprint</small>
                                    </div>
                                    <div id="fingerprintCaptured" class="d-none">
                                        <i class="fas fa-fingerprint fa-3x text-success mb-3"></i>
                                        <h6 class="text-success">Fingerprint Captured</h6>
                                        <small class="text-muted">Fingerprint successfully enrolled</small>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary" id="captureFingerprintBtn">
                                        <i class="fas fa-fingerprint me-2"></i>Capture Fingerprint
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm d-none mt-2" id="clearFingerprintBtn">
                                        <i class="fas fa-trash me-1"></i>Clear Fingerprint
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="text-primary mb-3">Fingerprint Instructions</h6>
                                    <div class="mb-3">
                                        <div class="d-flex align-items-start mb-2">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <small>Place your finger on the scanner</small>
                                        </div>
                                        <div class="d-flex align-items-start mb-2">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <small>Keep finger steady during scan</small>
                                        </div>
                                        <div class="d-flex align-items-start mb-2">
                                            <i class="fas fa-check-circle text-success me-2 mt-1"></i>
                                            <small>Wait for capture confirmation</small>
                                        </div>
                                        <div class="d-flex align-items-start">
                                            <i class="fas fa-info-circle text-info me-2 mt-1"></i>
                                            <small>Alternative: Use registration number + default password</small>
                                        </div>
                                    </div>
                                    <div class="alert alert-warning py-2">
                                        <small class="mb-0">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Fingerprint scanner required for this feature
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden fingerprint data -->
                    <input type="hidden" name="fingerprint_data" id="fingerprintData">
                    <input type="hidden" name="fingerprint_template" id="fingerprintTemplate">
                </div>

                <!-- Form Actions -->
                <div class="d-flex justify-content-between align-items-center pt-4 border-top">
                    <button type="button" class="btn btn-outline-secondary" id="resetForm">
                        <i class="fas fa-undo me-2"></i>Reset Form
                    </button>
                    <div class="d-flex gap-3">
                        <button type="button" class="btn btn-outline-primary" id="previewBtn">
                            <i class="fas fa-eye me-2"></i>Preview
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-2"></i>Register Student
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div>&copy; 2025 Rwanda Polytechnic | Student Management System</div>
        <div class="d-flex gap-3">
            <small><i class="fas fa-shield-alt me-1"></i>Secure Registration</small>
            <small><i class="fas fa-clock me-1"></i>Last updated: <?php echo date('H:i'); ?></small>
        </div>
    </div>
</div>

<?php if(isset($success)): ?><script>alert("<?= addslashes($success) ?>");</script><?php endif; ?>

<script>
// Global variables
let videoStream = null;
let currentPhotoData = null;

// Initialize when document is ready
$(document).ready(function(){
    initializeForm();
    setupEventListeners();
    updateFormProgress();
});

// Initialize form components
function initializeForm() {
    // Set current date/time
    $('#currentDateTime').text(new Date().toLocaleString());

    // Focus on first input
    $('#first_name').focus();
}

// Setup all event listeners
function setupEventListeners() {
    // Department change handler
    $('#department').on('change', handleDepartmentChange);

    // Real-time validation
    setupRealTimeValidation();

    // Photo upload handlers
    setupPhotoUpload();

    // Camera handlers
    setupCameraHandlers();

    // Fingerprint handlers
    setupFingerprintHandlers();

    // Form submission
    $('#registrationForm').on('submit', handleFormSubmission);

    // Form reset
    $('#resetForm').on('click', resetForm);

    // Preview button
    $('#previewBtn').on('click', showPreview);

    // Sidebar toggle for mobile
    $('#sidebarToggle').on('click', toggleSidebar);

    // Form progress tracking
    $('input, select').on('input change', updateFormProgress);
}

// Handle department selection
function handleDepartmentChange() {
    const depId = $(this).val();
    const optionSelect = $('#option');

    if(depId) {
        showLoading('Loading programs...');
        optionSelect.prop('disabled', true);

        $.ajax({
            url: '',
            method: 'POST',
            data: { get_options: 1, dep_id: depId },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if(response.success) {
                    let optionsHtml = '<option value="">-- Select Program --</option>';
                    response.options.forEach(function(option) {
                        optionsHtml += `<option value="${option.id}">${option.name}</option>`;
                    });
                    optionSelect.html(optionsHtml);
                    optionSelect.prop('disabled', false);
                } else {
                    showAlert('danger', 'Failed to load programs. Please try again.');
                }
            },
            error: function() {
                hideLoading();
                showAlert('danger', 'Network error. Please check your connection.');
            }
        });
    } else {
        optionSelect.html('<option value="">-- Select Program First --</option>');
        optionSelect.prop('disabled', true);
    }
}

// Setup real-time validation
function setupRealTimeValidation() {
    const fields = ['email', 'reg_no', 'telephone'];

    fields.forEach(field => {
        $(`#${field}`).on('blur', function() {
            validateField(field, $(this).val());
        });

        $(`#${field}`).on('input', function() {
            clearFieldValidation(field);
        });
    });
}

// Validate individual field
function validateField(field, value) {
    if(!value.trim()) return;

    const feedbackId = field + 'Feedback';
    const inputElement = $(`#${field}`);

    inputElement.removeClass('is-valid is-invalid');

    $.ajax({
        url: '',
        method: 'POST',
        data: { check_duplicate: 1, field: field, value: value },
        dataType: 'json',
        success: function(response) {
            if(response.valid) {
                inputElement.addClass('is-valid');
                $(`#${feedbackId}`).text(response.message || 'Available').show();
            } else {
                inputElement.addClass('is-invalid');
                $(`#${feedbackId}`).text(response.message || 'Already exists').show();
            }
        },
        error: function() {
            inputElement.addClass('is-invalid');
            $(`#${feedbackId}`).text('Validation error').show();
        }
    });
}

// Clear field validation
function clearFieldValidation(field) {
    const inputElement = $(`#${field}`);
    const feedbackId = field + 'Feedback';

    inputElement.removeClass('is-valid is-invalid');
    $(`#${feedbackId}`).hide();
}

// Setup photo upload functionality
function setupPhotoUpload() {
    const photoInput = $('#photoInput');
    const uploadArea = $('#photoUploadArea');
    const uploadContent = $('#uploadContent');
    const previewContent = $('#previewContent');
    const photoPreview = $('#photoPreview');

    // Click to select file
    $('#selectPhotoBtn').on('click', function() {
        photoInput.click();
    });

    // File selection
    photoInput.on('change', function(e) {
        const file = e.target.files[0];
        if(file) {
            handleFileSelection(file);
        }
    });

    // Drag and drop
    uploadArea.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    uploadArea.on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    uploadArea.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');

        const files = e.originalEvent.dataTransfer.files;
        if(files.length > 0) {
            handleFileSelection(files[0]);
        }
    });

    // Remove photo
    $('#removePhoto').on('click', function() {
        clearPhoto();
    });

    // Change photo
    $('#changePhoto').on('click', function() {
        photoInput.click();
    });

    function handleFileSelection(file) {
        // Validate file type
        if(!file.type.startsWith('image/')) {
            showAlert('danger', 'Please select a valid image file.');
            return;
        }

        // Validate file size (5MB)
        if(file.size > 5 * 1024 * 1024) {
            showAlert('danger', 'File size must be less than 5MB.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            photoPreview.attr('src', e.target.result);
            currentPhotoData = null; // Clear camera data
            uploadContent.addClass('d-none');
            previewContent.removeClass('d-none');
        };
        reader.readAsDataURL(file);
    }

    function clearPhoto() {
        photoInput.val('');
        photoPreview.attr('src', '');
        currentPhotoData = null;
        uploadContent.removeClass('d-none');
        previewContent.addClass('d-none');
    }
}

// Setup camera functionality
function setupCameraHandlers() {
    const cameraContainer = $('#cameraContainer');
    const video = $('#video');
    const captureBtn = $('#captureBtn');
    const cancelBtn = $('#cancelCamera');
    const cameraPhoto = $('#cameraPhoto');

    $('#useCameraBtn').on('click', function() {
        if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            cameraContainer.show();
            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Starting Camera...');

            navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240 } })
                .then(function(stream) {
                    videoStream = stream;
                    video[0].srcObject = stream;
                    $('#useCameraBtn').prop('disabled', false).html('<i class="fas fa-camera me-2"></i>Use Camera');
                })
                .catch(function(error) {
                    console.error('Camera error:', error);
                    showAlert('danger', 'Unable to access camera. Please check permissions.');
                    $('#useCameraBtn').prop('disabled', false).html('<i class="fas fa-camera me-2"></i>Use Camera');
                    cameraContainer.hide();
                });
        } else {
            showAlert('danger', 'Camera not supported on this device.');
        }
    });

    captureBtn.on('click', function() {
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.width = video[0].videoWidth;
        canvas.height = video[0].videoHeight;
        context.drawImage(video[0], 0, 0);

        const dataURL = canvas.toDataURL('image/png');
        cameraPhoto.val(dataURL);
        currentPhotoData = dataURL;

        // Stop camera
        if(videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
        }

        // Show preview
        $('#photoPreview').attr('src', dataURL);
        $('#uploadContent').addClass('d-none');
        $('#previewContent').removeClass('d-none');
        cameraContainer.hide();

        showAlert('success', 'Photo captured successfully!');
    });

    cancelBtn.on('click', function() {
        if(videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
        }
        cameraContainer.hide();
    });
}

// Setup fingerprint functionality
function setupFingerprintHandlers() {
    const captureBtn = $('#captureFingerprintBtn');
    const clearBtn = $('#clearFingerprintBtn');
    const fingerprintData = $('#fingerprintData');
    const fingerprintTemplate = $('#fingerprintTemplate');

    captureBtn.on('click', function() {
        startFingerprintCapture();
    });

    clearBtn.on('click', function() {
        clearFingerprint();
    });

    function startFingerprintCapture() {
        // Check if WebUSB or fingerprint API is available
        if (!navigator.usb && !window.Fingerprint && !window.webkitFingerprint) {
            showFingerprintSimulation();
            return;
        }

        // Show loading state
        captureBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Capturing...');

        // Simulate fingerprint capture process
        simulateFingerprintCapture();
    }

    function simulateFingerprintCapture() {
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 10;
            updateFingerprintProgress(progress);

            if (progress >= 100) {
                clearInterval(progressInterval);
                completeFingerprintCapture();
            }
        }, 300);
    }

    function updateFingerprintProgress(progress) {
        const statusDiv = $('#fingerprintStatus');
        statusDiv.html(`
            <div class="text-center">
                <div class="mb-3">
                    <i class="fas fa-fingerprint fa-3x text-primary mb-3"></i>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-primary" style="width: ${progress}%"></div>
                    </div>
                </div>
                <h6 class="text-primary">Capturing Fingerprint</h6>
                <small class="text-muted">Keep finger on scanner... ${progress}%</small>
            </div>
        `);
    }

    function completeFingerprintCapture() {
        // Generate mock fingerprint data
        const mockFingerprintData = generateMockFingerprintData();
        const mockTemplate = generateMockTemplate();

        // Store fingerprint data
        fingerprintData.val(JSON.stringify(mockFingerprintData));
        fingerprintTemplate.val(mockTemplate);

        // Update UI
        $('#fingerprintStatus').addClass('d-none');
        $('#fingerprintCaptured').removeClass('d-none');
        captureBtn.prop('disabled', false).html('<i class="fas fa-fingerprint me-2"></i>Recapture Fingerprint');
        clearBtn.removeClass('d-none');

        showAlert('success', 'Fingerprint captured successfully!');
    }

    function clearFingerprint() {
        fingerprintData.val('');
        fingerprintTemplate.val('');

        $('#fingerprintStatus').removeClass('d-none');
        $('#fingerprintCaptured').addClass('d-none');
        captureBtn.prop('disabled', false).html('<i class="fas fa-fingerprint me-2"></i>Capture Fingerprint');
        clearBtn.addClass('d-none');
    }

    function generateMockFingerprintData() {
        // Generate mock fingerprint data for demonstration
        const data = [];
        for (let i = 0; i < 256; i++) {
            data.push(Math.floor(Math.random() * 256));
        }
        return data;
    }

    function generateMockTemplate() {
        // Generate mock fingerprint template
        return btoa(String.fromCharCode(...new Array(512).fill(0).map(() => Math.floor(Math.random() * 256))));
    }

    function showFingerprintSimulation() {
        // Show simulation modal for demonstration
        const modal = $(`
            <div class="modal fade" id="fingerprintModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-fingerprint me-2"></i>Fingerprint Capture
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-4">
                                <i class="fas fa-fingerprint fa-4x text-primary mb-3"></i>
                                <h6>Fingerprint Scanner Simulation</h6>
                                <p class="text-muted">This is a simulation of fingerprint capture.</p>
                            </div>

                            <div class="alert alert-info">
                                <strong>Note:</strong> In a real implementation, this would connect to a fingerprint scanner device.
                            </div>

                            <div class="mb-3">
                                <small class="text-muted">Supported fingerprint scanners:</small>
                                <ul class="text-start mt-2">
                                    <li>DigitalPersona U.are.U</li>
                                    <li>Futronic FS80</li>
                                    <li>SecuGen Hamster</li>
                                    <li>Integrated Windows Hello</li>
                                </ul>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="simulateCapture">
                                <i class="fas fa-play me-1"></i>Simulate Capture
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);

        $('body').append(modal);
        const bsModal = new bootstrap.Modal(modal[0]);
        bsModal.show();

        $('#simulateCapture').on('click', function() {
            bsModal.hide();
            modal.remove();
            startFingerprintCapture();
        });

        modal.on('hidden.bs.modal', function() {
            $(this).remove();
        });
    }
}

// Handle form submission
function handleFormSubmission(e) {
    e.preventDefault();

    if(!validateForm()) {
        return;
    }

    const formData = new FormData(this);
    if(currentPhotoData) {
        formData.set('camera_photo', currentPhotoData);
    }

    showLoading('Registering student...');

    $.ajax({
        url: '',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            hideLoading();

            if(response.success) {
                showAlert('success', response.message);

                // Reset form after successful registration
                setTimeout(function() {
                    if(response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        resetForm();
                    }
                }, 2000);
            } else {
                if(response.errors) {
                    // Show field-specific errors
                    response.errors.forEach(function(error) {
                        showAlert('danger', error);
                    });
                } else {
                    showAlert('danger', response.message || 'Registration failed');
                }
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Registration error:', xhr.responseText);
            showAlert('danger', 'Network error. Please try again.');
        }
    });
}

// Validate entire form
function validateForm() {
    let isValid = true;
    const requiredFields = ['first_name', 'last_name', 'email', 'reg_no', 'department_id', 'option_id', 'telephone', 'year_level', 'sex'];

    requiredFields.forEach(field => {
        const element = $(`#${field}`);
        if(!element.val().trim()) {
            element.addClass('is-invalid');
            isValid = false;
        } else {
            element.removeClass('is-invalid');
        }
    });

    // Email validation
    const email = $('#email').val();
    if(email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        $('#email').addClass('is-invalid');
        isValid = false;
    }

    // Phone validation
    const phone = $('#telephone').val();
    if(phone && !/^[\d\s\-\+\(\)]+$/.test(phone)) {
        $('#telephone').addClass('is-invalid');
        isValid = false;
    }

    return isValid;
}

// Reset form
function resetForm() {
    $('#registrationForm')[0].reset();
    $('#registrationForm input, #registrationForm select').removeClass('is-valid is-invalid');
    $('.invalid-feedback, .valid-feedback').hide();
    $('#uploadContent').removeClass('d-none');
    $('#previewContent').addClass('d-none');
    $('#photoPreview').attr('src', '');
    $('#option').html('<option value="">-- Select Program First --</option>').prop('disabled', true);
    $('#cameraContainer').hide();
    currentPhotoData = null;
    updateFormProgress();

    // Clear any camera streams
    if(videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
}

// Show preview
function showPreview() {
    const data = {
        first_name: $('#first_name').val(),
        last_name: $('#last_name').val(),
        email: $('#email').val(),
        reg_no: $('#reg_no').val(),
        telephone: $('#telephone').val(),
        department: $('#department option:selected').text(),
        option: $('#option option:selected').text(),
        year_level: $('#year_level option:selected').text(),
        sex: $('#sex').val()
    };

    let previewHtml = '<div class="card"><div class="card-header"><h6 class="mb-0">Registration Preview</h6></div><div class="card-body">';
    previewHtml += '<div class="row g-3">';

    Object.keys(data).forEach(key => {
        if(data[key]) {
            const label = key.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
            previewHtml += `<div class="col-md-6"><strong>${label}:</strong> ${data[key]}</div>`;
        }
    });

    previewHtml += '</div></div></div>';

    // Create modal
    const modal = $(`
        <div class="modal fade" id="previewModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Registration Preview</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">${previewHtml}</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="$(\'#submitBtn\').click()">Confirm Registration</button>
                    </div>
                </div>
            </div>
        </div>
    `);

    $('body').append(modal);
    const bsModal = new bootstrap.Modal(modal[0]);
    bsModal.show();

    modal.on('hidden.bs.modal', function() {
        $(this).remove();
    });
}

// Update form progress
function updateFormProgress() {
    const totalFields = $('input[required], select[required]').length;
    const filledFields = $('input[required], select[required]').filter(function() {
        return $(this).val().trim() !== '';
    }).length;

    const progress = totalFields > 0 ? (filledFields / totalFields) * 100 : 0;
    $('#formProgress').css('width', progress + '%');
}

// Show loading overlay
function showLoading(message = 'Processing...') {
    $('#loadingOverlay .text-center h5').text(message);
    $('#loadingOverlay').fadeIn();
}

// Hide loading overlay
function hideLoading() {
    $('#loadingOverlay').fadeOut();
}

// Show alert
function showAlert(type, message) {
    const alertId = 'alert_' + Date.now();
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" id="${alertId}" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    $('#alertContainer').append(alertHtml);

    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        $(`#${alertId}`).fadeOut(function() {
            $(this).remove();
        });
    }, 5000);
}

// Toggle sidebar for mobile
function toggleSidebar() {
    $('#sidebar').toggleClass('show');
}

// Cleanup on page unload
$(window).on('beforeunload', function() {
    if(videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
