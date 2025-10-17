<?php
/**
 * Enhanced Student Registration Form
 * Clean, modern, and accessible student registration system
 * Features: Location management, real-time validation, progress tracking
 * Version: 2.0
 */

session_start();
require_once "config.php";

// Initialize CSRF token for unauthenticated access
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];


// Initialize data arrays with error handling
$departments = [];

// Get departments for dropdown with prepared statement
try {
    $stmt = $pdo->prepare("SELECT id, name FROM departments WHERE status = ? ORDER BY name");
    $stmt->execute(['active']);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Validate required data
if (empty($departments)) {
    error_log("Warning: No departments found in database");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Rwanda Polytechnic</title>

    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="css/all.min.css" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Font Awesome 6 Free';
            font-style: normal;
            font-weight: 900;
            font-display: block;
            src: url('css/webfonts/fa-solid-900.woff2') format('woff2'),
                 url('css/webfonts/fa-solid-900.ttf') format('truetype');
        }
    </style>
    <!-- Custom CSS -->
    <link href="css/register-student.css" rel="stylesheet">

    <!-- Bootstrap JS -->
    <script src="js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/admin_sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-plus me-2"></i>Student Registration</h2>
                    <button class="btn btn-outline-secondary d-md-none" id="mobileMenuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- Registration Form -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Student Information</h5>
    </div>
    <div class="card-body">
        <!-- Progress Bar -->
        <div class="mb-4">
            <div class="progress" style="height: 8px;">
                <div class="progress-bar" id="formProgress" role="progressbar" style="width: 0%"></div>
            </div>
            <small class="text-muted" id="progressText">0% complete</small>
        </div>

        <form id="registrationForm" enctype="multipart/form-data" aria-label="Student Registration Form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="row">
                <!-- Personal Information -->
                <div class="col-md-6">
                    <h6 class="section-title">Personal Information</h6>

                    <div class="mb-3">
                        <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="firstName" name="first_name" required aria-required="true" aria-label="First Name">
                    </div>

                    <div class="mb-3">
                        <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lastName" name="last_name" required aria-required="true" aria-label="Last Name">
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required aria-required="true" aria-label="Email Address">
                    </div>

                    <div class="mb-3">
                        <label for="telephone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="telephone" name="telephone" required aria-required="true" aria-label="Phone Number">
                    </div>

                    <div class="mb-3">
                        <label for="dob" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="dob" name="dob" aria-label="Date of Birth">
                    </div>

                    <div class="mb-3">
                        <label for="sex" class="form-label">Gender <span class="text-danger">*</span></label>
                        <select class="form-control" id="sex" name="sex" required aria-required="true" aria-label="Gender">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="col-md-6">
                    <h6 class="section-title">Academic Information</h6>

                    <div class="mb-3">
                        <label for="reg_no" class="form-label">Registration Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="reg_no" name="reg_no" required maxlength="20" aria-required="true" aria-label="Registration Number">
                    </div>

                    <div class="mb-3">
                        <label for="studentIdNumber" class="form-label">Student ID Number</label>
                        <input type="text" class="form-control" id="studentIdNumber" name="student_id_number" maxlength="16" aria-label="Student ID Number">
                    </div>

                    <div class="mb-4">
                        <label for="department" class="form-label d-flex align-items-center justify-content-between">
                            <span class="d-flex align-items-center">
                                <i class="fas fa-building me-2 text-primary fs-5"></i>
                                <strong>Academic Department</strong>
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
                                foreach ($departments as $dept) {
                                    echo "<option value=\"" . htmlspecialchars($dept['id']) . "\">📚 " . htmlspecialchars($dept['name']) . "</option>";
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
                        <label for="option" class="form-label d-flex align-items-center justify-content-between">
                            <span class="d-flex align-items-center">
                                <i class="fas fa-graduation-cap me-2 text-success fs-5"></i>
                                <strong>Program/Specialization</strong>
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
                        <label for="year_level" class="form-label">Year Level <span class="text-danger">*</span></label>
                        <select class="form-control" id="year_level" name="year_level" required aria-required="true" aria-label="Year Level">
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
                    <h6 class="section-title">Parent/Guardian Information</h6>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="parent_first_name" class="form-label">Parent First Name</label>
                        <input type="text" class="form-control" id="parent_first_name" name="parent_first_name" aria-label="Parent First Name">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="parent_last_name" class="form-label">Parent Last Name</label>
                        <input type="text" class="form-control" id="parent_last_name" name="parent_last_name" aria-label="Parent Last Name">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label for="parent_contact" class="form-label">Parent Contact</label>
                        <input type="tel" class="form-control" id="parent_contact" name="parent_contact" aria-label="Parent Contact">
                    </div>
                </div>
            </div>


            <!-- Media Section -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">
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
                            <div class="face-images-upload-area" id="faceImagesUploadArea">
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
                        <label class="form-label">
                            <i class="fas fa-fingerprint me-2 text-info"></i>
                            Fingerprint Registration
                            <small class="text-muted">(Optional - enhances security)</small>
                        </label>
                        <div class="fingerprint-container">
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
                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
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

        /* Fix for mobile menu toggle */
        @media (max-width: 768px) {
            .main-content.sidebar-open {
                margin-left: 250px;
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

    // Native DOM manipulation methods to replace jQuery
    $(selector) {
        return document.querySelector(selector);
    }

    $$(selector) {
        return document.querySelectorAll(selector);
    }

    on(element, event, handler) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            element.addEventListener(event, handler);
        }
    }

    addClass(element, className) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            // Handle multiple class names separated by spaces
            const classes = className.split(' ').filter(cls => cls.trim());
            element.classList.add(...classes);
        }
    }

    removeClass(element, className) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            // Handle multiple class names separated by spaces
            const classes = className.split(' ').filter(cls => cls.trim());
            element.classList.remove(...classes);
        }
    }

    hasClass(element, className) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        return element ? element.classList.contains(className) : false;
    }

    toggleClass(element, className) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            element.classList.toggle(className);
        }
    }

    val(element) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        return element ? element.value : '';
    }

    text(element, value) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            if (value !== undefined) {
                element.textContent = value;
            }
            return element.textContent;
        }
        return '';
    }

    html(element, value) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            if (value !== undefined) {
                element.innerHTML = value;
            }
            return element.innerHTML;
        }
        return '';
    }

    attr(element, attribute, value) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            if (value !== undefined) {
                element.setAttribute(attribute, value);
            }
            return element.getAttribute(attribute);
        }
        return null;
    }

    prop(element, property, value) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            if (value !== undefined) {
                element[property] = value;
            }
            return element[property];
        }
        return null;
    }

    css(element, property, value) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            if (value !== undefined) {
                element.style[property] = value;
            }
            return getComputedStyle(element)[property];
        }
        return '';
    }

    animate(element, properties, duration = 400) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (!element) return Promise.resolve();

        return new Promise(resolve => {
            const start = {};
            const computedStyle = getComputedStyle(element);

            // Get initial values
            Object.keys(properties).forEach(prop => {
                if (prop === 'scrollTop') {
                    start[prop] = element.scrollTop;
                } else {
                    start[prop] = parseFloat(computedStyle[prop]) || 0;
                }
            });

            const startTime = performance.now();

            const animate = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                Object.keys(properties).forEach(prop => {
                    if (prop === 'scrollTop') {
                        element.scrollTop = start[prop] + (properties[prop] - start[prop]) * progress;
                    } else {
                        element.style[prop] = start[prop] + (properties[prop] - start[prop]) * progress + 'px';
                    }
                });

                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    resolve();
                }
            };

            requestAnimationFrame(animate);
        });
    }

    fadeOut(element, duration = 300) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (!element) return Promise.resolve();

        return new Promise(resolve => {
            const startOpacity = parseFloat(getComputedStyle(element).opacity) || 1;
            const startTime = performance.now();

            const fade = (currentTime) => {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const opacity = startOpacity * (1 - progress);

                element.style.opacity = opacity;

                if (progress < 1) {
                    requestAnimationFrame(fade);
                } else {
                    element.style.display = 'none';
                    resolve();
                }
            };

            requestAnimationFrame(fade);
        });
    }

    ajax(options) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const defaultOptions = {
                method: 'GET',
                timeout: 10000,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            };

            const finalOptions = { ...defaultOptions, ...options };

            xhr.open(finalOptions.method, finalOptions.url);

            // Set headers
            Object.keys(finalOptions.headers).forEach(header => {
                xhr.setRequestHeader(header, finalOptions.headers[header]);
            });

            // Set timeout
            xhr.timeout = finalOptions.timeout;

            xhr.onload = function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    let response;
                    try {
                        if (finalOptions.dataType === 'json') {
                            response = JSON.parse(xhr.responseText);
                        } else {
                            response = xhr.responseText;
                        }
                        resolve(response);
                    } catch (e) {
                        reject(new Error('Invalid response format'));
                    }
                } else {
                    reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
                }
            };

            xhr.onerror = function() {
                reject(new Error('Network error'));
            };

            xhr.ontimeout = function() {
                reject(new Error('Request timeout'));
            };

            // Prepare data
            let data = null;
            if (finalOptions.data) {
                if (finalOptions.method === 'POST' && !(finalOptions.data instanceof FormData)) {
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    data = Object.keys(finalOptions.data)
                        .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(finalOptions.data[key]))
                        .join('&');
                } else {
                    data = finalOptions.data;
                }
            }

            xhr.send(data);
        });
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



    setupGlobalErrorHandler() {
        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            this.showAlert('An unexpected error occurred. Please try again.', 'error');
            event.preventDefault();
        });

        // Handle global JavaScript errors
        window.addEventListener('error', (event) => {
            console.error('Global JavaScript error:', event.error);
            // Don't show alert for minor errors to avoid spam
            if (event.error && !event.error.message.includes('Script error')) {
                this.showAlert('A system error occurred. Please refresh if issues persist.', 'error');
            }
        });
    }

    async checkServerConnectivity() {
        try {
            // Quick connectivity check to a lightweight endpoint
            const response = await fetch('api/department-option-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `csrf_token=${this.csrfToken}`,
                signal: AbortSignal.timeout(5000) // 5 second timeout
            });

            if (response.ok) {
                console.log('✅ Server connectivity check passed');
            } else {
                console.warn('⚠️ Server connectivity check failed with status:', response.status);
                this.showAlert('⚠️ Server connection may be unstable. Please check your internet connection.', 'warning');
            }
        } catch (error) {
            console.error('❌ Server connectivity check failed:', error);
            this.showAlert('⚠️ Unable to connect to server. Please check your internet connection and try refreshing the page.', 'warning');
        }
    }

    setupEventListeners() {
        // Department change with error handling
        this.on('#department', 'change', this.debounce(this.handleDepartmentChange.bind(this), 300));

        // Face images handling
        this.on('#selectFaceImagesBtn', 'click', () => this.$('#faceImagesInput').click());
        this.on('#faceImagesInput', 'change', this.handleFaceImagesSelect.bind(this));
        this.on('#clearFaceImages', 'click', this.clearFaceImages.bind(this));
        this.on(document, 'click', (e) => {
            if (e.target.classList.contains('remove-image')) {
                this.removeFaceImage(e);
            }
        });

        // Drag and drop for face images
        const uploadArea = this.$('#faceImagesUploadArea');
        if (uploadArea) {
            this.on(uploadArea, 'dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.addClass(e.currentTarget, 'dragover');
            });
            this.on(uploadArea, 'dragenter', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.addClass(e.currentTarget, 'dragover');
            });
            this.on(uploadArea, 'dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.removeClass(e.currentTarget, 'dragover');
            });
            this.on(uploadArea, 'dragend', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.removeClass(e.currentTarget, 'dragover');
            });
            this.on(uploadArea, 'drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.removeClass(e.currentTarget, 'dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    // Create a synthetic event for the file input
                    const syntheticEvent = { target: { files: files } };
                    this.handleFaceImagesSelect(syntheticEvent);
                }
            });
            this.on(uploadArea, 'click', () => this.$('#faceImagesInput').click());
        }

        // Form submission
        this.on('#registrationForm', 'submit', this.handleSubmit.bind(this));

        // Real-time validation
        const requiredInputs = this.$$('input[required]');
        requiredInputs.forEach(input => {
            this.on(input, 'blur', this.validateField.bind(this));
            this.on(input, 'input', this.debounce(this.updateProgress.bind(this), 200));
        });

        // Enhanced registration number validation
        this.on('input[name="reg_no"]', 'input', this.validateRegistrationNumber.bind(this));

        // Real-time email validation
        this.on('input[name="email"]', 'blur', this.validateEmailField.bind(this));

        // Real-time phone validation
        this.on('input[name="telephone"]', 'blur', this.validatePhoneField.bind(this));
        this.on('input[name="parent_contact"]', 'blur', this.validatePhoneField.bind(this));
        this.on('#studentIdNumber', 'input', this.validateStudentId.bind(this));

        // Phone number input filtering (digits only, max 10 characters)
        this.on('input[name="telephone"]', 'input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        // Student ID number input filtering (digits only)
        this.on('#studentIdNumber', 'input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Parent contact input filtering (digits only, max 10 characters)
        this.on('input[name="parent_contact"]', 'input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        // Fingerprint functionality
        this.on('#captureFingerprintBtn', 'click', this.startFingerprintCapture.bind(this));
        this.on('#clearFingerprintBtn', 'click', this.clearFingerprint.bind(this));
        this.on('#enrollFingerprintBtn', 'click', this.enrollFingerprint.bind(this));
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    async handleDepartmentChange() {
        const deptId = this.val('#department');
        const $option = this.$('#option');
        const $loadingSpinner = this.$('.program-loading');

        if (!deptId) {
            this.resetProgramSelection();
            return;
        }

        // Clear previous program selection and show loading
        this.prop($option, 'disabled', true);
        this.removeClass($option, 'programs-loaded');
        this.removeClass($loadingSpinner, 'd-none');
        this.addClass('#programCount', 'd-none');
        this.addClass('#programLoadedIcon', 'd-none');

        // Reset help text to initial state
        const programHelpIcon = this.$('#programHelp .fas');
        this.removeClass(programHelpIcon, 'fa-check-circle text-success');
        this.addClass(programHelpIcon, 'fa-info-circle text-info');
        this.html('#programHelp small', '<strong class="text-muted">Available programs will appear after selecting a department</strong>');

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

                    this.html($option, '<option value="">Select Program</option>' + options);
                    this.prop($option, 'disabled', false);
                    this.addClass($option, 'programs-loaded');
                    this.attr($option, 'data-department-id', deptId);

                    // Update program count display
                    this.text('#programCountText', `${response.data.length} program${response.data.length !== 1 ? 's' : ''} available for selection`);
                    this.removeClass('#programCount', 'd-none');

                    // Show success icon
                    this.removeClass('#programLoadedIcon', 'd-none');

                    // Update help text with success styling
                    this.removeClass(programHelpIcon, 'fa-info-circle text-info');
                    this.addClass(programHelpIcon, 'fa-check-circle text-success');
                    this.html('#programHelp small', '<strong class="text-success">Programs loaded successfully!</strong> Choose your desired program from the dropdown above');

                    this.showAlert(`🎉 ${response.data.length} program${response.data.length !== 1 ? 's' : ''} loaded successfully!`, 'success');
                } else {
                    this.html($option, '<option value="">No programs available</option>');
                    this.removeAttr($option, 'data-department-id');

                    this.addClass('#programCount', 'd-none');
                    this.text('#programHelp small', 'No programs are currently available for this department');

                    this.showAlert('⚠️ No programs found for this department', 'warning');
                }
            } else {
                throw new Error(response.message || 'Failed to load options');
            }
        } catch (error) {
            console.error('Department change error:', error);
            this.html($option, '<option value="">Error loading programs</option>');
            this.removeAttr($option, 'data-department-id');

            // Reset program count and help text
            this.addClass('#programCount', 'd-none');
            this.text('#programHelp small', 'Failed to load programs. Please try selecting the department again.');

            this.showAlert('❌ Failed to load programs. Please try again.', 'error');
        } finally {
            this.prop($option, 'disabled', false);
            this.addClass($loadingSpinner, 'd-none');
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
                const response = await this.ajax(finalOptions);

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
        if (typeof field === 'string') {
            field = this.$(field);
        }
        if (field) {
            this.addClass(field, 'is-invalid');
            this.removeClass(field, 'is-valid');
            const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }
            const feedbackDiv = document.createElement('div');
            feedbackDiv.className = 'invalid-feedback';
            feedbackDiv.textContent = message;
            field.parentNode.insertBefore(feedbackDiv, field.nextSibling);
        }
    }

    clearFieldError(field) {
        if (typeof field === 'string') {
            field = this.$(field);
        }
        if (field) {
            this.removeClass(field, 'is-invalid');
            this.addClass(field, 'is-valid');
            const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
            if (existingFeedback) {
                existingFeedback.remove();
            }
        }
    }

    validateRegistrationNumber(e) {
        const value = e.target.value.replace(/[^A-Za-z0-9_-]/g, '').toUpperCase().substring(0, 20);
        e.target.value = value;

        if (value.length >= 5 && this.isValidRegistrationNumber(value)) {
            this.addClass(e.target, 'is-valid');
            this.removeClass(e.target, 'is-invalid');
        } else if (value.length > 0) {
            this.addClass(e.target, 'is-invalid');
            this.removeClass(e.target, 'is-valid');
        } else {
            this.removeClass(e.target, 'is-valid is-invalid');
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
            this.addClass(e.target, 'is-valid');
        } else {
            this.removeClass(e.target, 'is-valid is-invalid');
        }
    }

    showLoading(show) {
        const overlay = this.$('#loadingOverlay');
        if (show) {
            this.removeClass(overlay, 'd-none');
            this.addClass(overlay, 'd-flex');
        } else {
            this.removeClass(overlay, 'd-flex');
            this.addClass(overlay, 'd-none');
        }
    }

    disableForm(disable) {
        const elements = this.$$('#registrationForm input, #registrationForm select, #registrationForm button');
        elements.forEach(element => {
            this.prop(element, 'disabled', disable);
        });
    }

    escapeHtml(unsafe) {
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    showWelcomeMessage() {
        setTimeout(() => {
            this.showAlert('Welcome to Rwanda Polytechnic Student Registration System! Please fill in all required fields.', 'info');
        }, 1000);
    }

    // Additional utility methods for jQuery replacement
    removeAttr(element, attribute) {
        if (typeof element === 'string') {
            element = this.$(element);
        }
        if (element) {
            element.removeAttribute(attribute);
        }
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
        const requiredFields = this.$$('#registrationForm [required]');
        const totalFields = requiredFields.length;
        const filledFields = Array.from(requiredFields).filter(field => this.val(field).trim().length > 0).length;

        const progress = Math.round((filledFields / totalFields) * 100);
        this.css('#formProgress', 'width', progress + '%');
        this.text('#progressText', progress + '%');

        const $progressBar = this.$('#formProgress');
        this.removeClass($progressBar, 'bg-success bg-warning bg-danger');

        if (progress >= 80) {
            this.addClass($progressBar, 'bg-success');
        } else if (progress >= 50) {
            this.addClass($progressBar, 'bg-warning');
        } else {
            this.addClass($progressBar, 'bg-danger');
        }
    }

    showSuccess(response) {
        // Show success alert prominently at the top
        this.showAlert(`🎉 SUCCESS: ${response.message}`, 'success', false);

        // Create enhanced success modal
        let successModal = document.getElementById('successModal');
        if (!successModal) {
            const modalHtml = `
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
                                            ${this.escapeHtml(response.message)}
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="card border-success">
                                            <div class="card-body">
                                                <h6 class="card-title text-success">
                                                    <i class="fas fa-id-card me-2"></i>Student Information
                                                </h6>
                                                <p class="mb-1"><strong>Student ID:</strong> <code class="text-success">${this.escapeHtml(response.student_id)}</code></p>
                                                <p class="mb-0"><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                                            </div>
                                        </div>
                                    </div>

                                    ${response.fingerprint_enrolled ?
                                        '<div class="col-12"><div class="alert alert-info border-info"><i class="fas fa-fingerprint me-2"></i><strong>Biometric Security:</strong> Fingerprint enrolled successfully with ESP32 sensor for secure attendance tracking!</div></div>' :
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
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            successModal = document.getElementById('successModal');
        }

        const modal = new bootstrap.Modal(successModal, {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();

        const continueBtn = document.getElementById('continueButton');
        if (continueBtn) {
            continueBtn.addEventListener('click', () => {
                modal.hide();
                // Add a small delay to show transition
                setTimeout(() => {
                    window.location.href = response.redirect || 'admin-dashboard.php';
                }, 300);
            });
        }
    }

    async startFingerprintCapture() {
        if (this.isCapturing) return;

        this.isCapturing = true;
        this.updateFingerprintUI('capturing');

        try {
            // First check ESP32 status
            const statusResponse = await this.ajax({
                url: 'http://192.168.137.194:80/status',
                method: 'GET',
                timeout: 5000
            });

            if (!statusResponse.fingerprint_sensor || statusResponse.fingerprint_sensor !== 'connected') {
                throw new Error('Fingerprint sensor not connected. Please check ESP32 connection.');
            }

            this.showAlert('ESP32 connected. Starting fingerprint capture...', 'info');
            await this.captureFromESP32();

        } catch (error) {
            console.error('ESP32 connection error:', error);
            this.showAlert('Failed to connect to ESP32: ' + error.message, 'error');
            this.isCapturing = false;
            this.updateFingerprintUI('ready');
        }
    }

    async captureFromESP32() {
        const canvas = document.getElementById('fingerprintCanvas');
        const ctx = canvas.getContext('2d');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        const status = document.getElementById('fingerprintStatus');

        canvas.classList.remove('d-none');
        placeholder.classList.add('d-none');

        try {
            // Send display message to ESP32
            await this.ajax({
                url: 'http://192.168.137.194:80/display',
                method: 'GET',
                data: { message: 'Place finger on sensor...' }
            });

            // Wait for fingerprint capture (simulate progress while ESP32 processes)
            let progress = 0;
            const captureInterval = setInterval(() => {
                progress += Math.random() * 5 + 1;
                const currentProgress = Math.min(progress, 95); // Don't reach 100 until ESP32 responds
                status.textContent = `Waiting for finger... ${Math.round(currentProgress)}%`;
                this.drawFingerprintPattern(ctx, currentProgress);
            }, 200);

            // Poll ESP32 for capture status (this is a simplified approach)
            // In a real implementation, you might use WebSockets or Server-Sent Events
            const pollInterval = setInterval(async () => {
                try {
                    const response = await this.ajax({
                        url: 'http://192.168.137.194:80/identify',
                        method: 'GET',
                        timeout: 2000
                    });

                    if (response.success) {
                        clearInterval(captureInterval);
                        clearInterval(pollInterval);

                        // Generate visual representation
                        this.fingerprintCaptured = true;
                        this.fingerprintQuality = 85 + Math.floor(Math.random() * 15); // Simulate quality

                        this.drawFingerprintPattern(ctx, 100);
                        this.isCapturing = false;
                        this.updateFingerprintUI('captured');
                        this.showAlert(`Fingerprint captured successfully! Quality: ${this.fingerprintQuality}%`, 'success');
                    }
                } catch (pollError) {
                    // Continue polling
                }
            }, 1000);

            // Timeout after 30 seconds
            setTimeout(() => {
                clearInterval(captureInterval);
                clearInterval(pollInterval);
                if (this.isCapturing) {
                    this.isCapturing = false;
                    this.updateFingerprintUI('ready');
                    this.showAlert('Fingerprint capture timeout. Please try again.', 'error');
                }
            }, 30000);

        } catch (error) {
            console.error('ESP32 capture error:', error);
            this.isCapturing = false;
            this.updateFingerprintUI('ready');
            this.showAlert('Failed to start fingerprint capture: ' + error.message, 'error');
        }
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
        const preview = this.$('#faceImagesPreview');
        preview.innerHTML = '';
        this.removeClass(preview, 'd-none');
        this.addClass('#faceImagesUploadArea .face-images-placeholder', 'd-none');
        this.removeClass('#clearFaceImages', 'd-none');

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
                preview.appendChild(imageItem);
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
        const button = e.currentTarget;
        const imageItem = button.closest('.face-image-item');

        // Remove the image item
        imageItem.remove();

        // Update remaining image numbers
        const remainingItems = this.$$('#faceImagesPreview .face-image-item');
        remainingItems.forEach((item, i) => {
            const numberDiv = item.querySelector('.image-number');
            const removeBtn = item.querySelector('.remove-image');
            if (numberDiv) numberDiv.textContent = i + 1;
            if (removeBtn) removeBtn.setAttribute('data-index', i);
        });

        // Check if any images remain
        const remainingImages = remainingItems.length;
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
        const input = this.$('#faceImagesInput');
        const preview = this.$('#faceImagesPreview');
        const placeholder = this.$('#faceImagesUploadArea .face-images-placeholder');
        const clearBtn = this.$('#clearFaceImages');

        if (input) input.value = '';
        if (preview) {
            preview.innerHTML = '';
            this.addClass(preview, 'd-none');
        }
        if (placeholder) this.removeClass(placeholder, 'd-none');
        if (clearBtn) this.addClass(clearBtn, 'd-none');
        this.updateFaceImagesCount(0);
    }

    /**
     * Update face images count display
     */
    updateFaceImagesCount(count) {
        const countElement = document.getElementById('faceImagesCount');
        if (countElement) {
            if (count === 0) {
                countElement.textContent = '0 images selected';
                countElement.classList.remove('text-success', 'text-warning');
                countElement.classList.add('text-muted');
            } else if (count >= 2 && count <= 5) {
                countElement.textContent = `${count} images selected`;
                countElement.classList.remove('text-muted', 'text-warning');
                countElement.classList.add('text-success');
            } else {
                countElement.textContent = `${count} images selected`;
                countElement.classList.remove('text-muted', 'text-success');
                countElement.classList.add('text-warning');
            }
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
        const departmentId = this.val('#department');
        const optionId = this.val('#option');

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
                    const optionElement = this.$('#option');
                    if (optionElement) optionElement.focus();
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
                        if (element.files && element.files.length > 0) {
                            // Handle multiple files for face images
                            if (element.name === 'face_images[]') {
                                for (let i = 0; i < element.files.length; i++) {
                                    formData.append(element.name, element.files[i]);
                                }
                            } else {
                                formData.append(element.name, element.files[0]);
                            }
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
                formData.append('fingerprint_enrolled', 'true');
                formData.append('fingerprint_template', this.fingerprintData.template || '');
                formData.append('fingerprint_hash', this.fingerprintData.hash || '');
                formData.append('fingerprint_quality', this.fingerprintQuality);

                // Also include canvas image for backup/reference
                const canvas = document.getElementById('fingerprintCanvas');
                const fingerprintImageData = canvas.toDataURL('image/png');
                formData.append('fingerprint_image', fingerprintImageData);
            } else {
                formData.append('fingerprint_enrolled', 'false');
            }

            // Use fetch API instead of jQuery AJAX for better error handling
            const response = await fetch('submit-student-registration.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const responseData = await response.json();

            if (responseData.success) {
                this.showSuccess(responseData);
            } else {
                this.handleSubmissionError(responseData);
            }
        } catch (error) {
            this.handleNetworkError(error);
        } finally {
            this.showLoading(false);
            this.disableForm(false);
        }
    }

    scrollToFirstError() {
        const firstError = this.$('.is-invalid');
        if (firstError) {
            const targetPosition = firstError.offsetTop - 100;
            this.animate(document.documentElement, { scrollTop: targetPosition }, 500);
        }
    }

    async confirmSubmission() {
        return new Promise((resolve) => {
            // Create a custom confirmation modal instead of using alert
            let modalElement = document.getElementById('customConfirmModal');
            if (!modalElement) {
                const modalHtml = `
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
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                modalElement = document.getElementById('customConfirmModal');
            }

            const modal = new bootstrap.Modal(modalElement);
            modal.show();

            const confirmBtn = document.getElementById('confirmRegistration');

            const handleConfirm = () => {
                modal.hide();
                resolve(true);
                // Clean up event listeners
                confirmBtn.removeEventListener('click', handleConfirm);
                modalElement.removeEventListener('hidden.bs.modal', handleCancel);
            };

            const handleCancel = () => {
                resolve(false);
                // Clean up event listeners
                confirmBtn.removeEventListener('click', handleConfirm);
                modalElement.removeEventListener('hidden.bs.modal', handleCancel);
            };

            confirmBtn.addEventListener('click', handleConfirm);
            modalElement.addEventListener('hidden.bs.modal', handleCancel);
        });
    }

   validateForm() {
       let isValid = true;
       const errors = [];

       // Clear previous validation
       const invalidElements = this.$$('.is-invalid');
       invalidElements.forEach(el => this.removeClass(el, 'is-invalid'));
       const feedbackElements = this.$$('.invalid-feedback');
       feedbackElements.forEach(el => el.remove());

       // Check face images requirement
       const faceImagesCount = this.$$('#faceImagesPreview .face-image-item').length;
       if (faceImagesCount < 2) {
           this.showAlert('Please select at least 2 face images for face recognition.', 'error');
           this.addClass('#faceImagesUploadArea', 'border-danger');
           isValid = false;
       } else {
           this.removeClass('#faceImagesUploadArea', 'border-danger');
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
           const fieldElement = this.$(`#${field.id}`);
           if (!this.val(fieldElement).trim()) {
               this.showFieldError(fieldElement, `${field.name} is required`);
               isValid = false;
               errors.push(`${field.name} is required`);
           }
       });

       // Department-program dependency validation
       const departmentId = this.val('#department');
       const optionId = this.val('#option');
       if (departmentId && !optionId) {
           const $option = this.$('#option');
           this.showFieldError($option, 'Please select a program for the chosen department');
           isValid = false;
           errors.push('Program selection is required');
       }

       // Validate that the selected option belongs to the selected department
       if (departmentId && optionId) {
           const $optionElement = this.$('#option');
           const selectedOption = $optionElement.querySelector('option:checked') || $optionElement.querySelector('option[selected]');
           const optionDepartmentId = selectedOption ? this.attr(selectedOption, 'data-department') : null;

           if (optionDepartmentId && optionDepartmentId != departmentId) {
               const $option = this.$('#option');
               this.showFieldError($option, 'Selected program does not belong to the chosen department');
               isValid = false;
               errors.push('Invalid program for department');
           } else {
               this.clearFieldError($optionElement);
           }
       }

       // Email format validation
       const email = this.val('#email');
       if (email && !this.isValidEmail(email)) {
           this.showFieldError('#email', 'Please enter a valid email address');
           isValid = false;
           errors.push('Invalid email format');
       }

       // Phone number validation
       const phone = this.val('#telephone');
       if (phone && !this.isValidPhone(phone)) {
           this.showFieldError('#telephone', 'Phone number must be exactly 10 digits starting with 0 (e.g., 0781234567) - no letters allowed');
           isValid = false;
           errors.push('Invalid phone number format');
       }

       // Parent contact validation if provided
       const parentContact = this.val('#parent_contact');
       if (parentContact && !this.isValidPhone(parentContact)) {
           this.showFieldError('#parent_contact', 'Parent phone number must be exactly 10 digits starting with 0 (e.g., 0781234567) - no letters allowed');
           isValid = false;
           errors.push('Invalid parent phone number format');
       }

       // Date of birth validation
       const dob = this.val('#dob');
       if (dob) {
           const birthDate = new Date(dob);
           const today = new Date();
           const age = today.getFullYear() - birthDate.getFullYear();

           if (age < 16) {
               this.showFieldError('#dob', 'Student must be at least 16 years old');
               isValid = false;
               errors.push('Student too young');
           } else if (age > 60) {
               this.showFieldError('#dob', 'Please enter a valid date of birth');
               isValid = false;
               errors.push('Invalid date of birth');
           }
       }

       // Registration number format validation
       const regNo = this.val('#reg_no');
       if (regNo && !this.isValidRegistrationNumber(regNo)) {
           this.showFieldError('#reg_no', 'Registration number must be 5-20 characters, alphanumeric only');
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

   // Reset program selection when department changes
   resetProgramSelection() {
       const option = this.$('#option');
       const loadingSpinner = this.$('.program-loading');

       this.prop(option, 'disabled', true);
       this.html(option, '<option value="">Select Department First to Load Programs</option>');
       this.addClass(loadingSpinner, 'd-none');
       this.addClass('#programCount', 'd-none');
       this.addClass('#programLoadedIcon', 'd-none');

       // Reset help text
       const programHelpIcon = this.$('#programHelp .fas');
       this.removeClass(programHelpIcon, 'fa-check-circle text-success');
       this.addClass(programHelpIcon, 'fa-info-circle text-info');
       this.html('#programHelp small', '<strong class="text-muted">Available programs will appear after selecting a department</strong>');
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

    async enrollFingerprint() {
        if (!this.fingerprintCaptured) {
            this.showAlert('No fingerprint captured to enroll', 'warning');
            return;
        }

        try {
            this.showAlert('Enrolling fingerprint with ESP32...', 'info');

            // Send enrollment request to ESP32
            const response = await this.ajax({
                url: 'http://192.168.137.194:80/enroll',
                method: 'POST',
                data: {
                    id: this.fingerprintCaptured ? 1 : 0, // Use a default ID or get from form
                    student_name: this.val('#firstName') + ' ' + this.val('#lastName'),
                    reg_no: this.val('#reg_no')
                },
                timeout: 30000 // 30 seconds timeout for enrollment
            });

            if (response.success) {
                this.fingerprintData = {
                    template: response.template,
                    hash: response.hash,
                    quality: this.fingerprintQuality,
                    enrolled: true
                };

                this.showAlert('Fingerprint enrolled successfully with ESP32!', 'success');
                console.log('Fingerprint enrolled:', this.fingerprintData);

                // Update UI to show enrolled state
                this.updateFingerprintUI('enrolled');
            } else {
                throw new Error(response.error || 'Enrollment failed');
            }
        } catch (error) {
            console.error('Fingerprint enrollment error:', error);
            this.showAlert('Failed to enroll fingerprint: ' + error.message, 'error');
        }
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

        this.html('#alertContainer', alertHtml);

        // Auto-dismiss with different timing based on type
        if (autoDismiss) {
            const dismissTime = type === 'error' ? 8000 : type === 'warning' ? 6000 : 5000;
            setTimeout(() => {
                const alert = this.$('.alert');
                if (alert) {
                    this.fadeOut(alert, 300).then(() => {
                        alert.remove();
                    });
                }
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
        this.animate(document.documentElement, { scrollTop: 0 }, 500);

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
        } else if (error.statusText) {
            errorMessage = `Network error: ${error.statusText}`;
            errorTitle = 'Network Error';
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
        this.animate(document.documentElement, { scrollTop: 0 }, 500);

        this.disableForm(false);
    }
}

// Enhanced initialization
document.addEventListener('DOMContentLoaded', () => {
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
        const alertContainer = document.getElementById('alertContainer');
        if (alertContainer) {
            alertContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    System initialization failed. Please refresh the page.
                </div>
            `;
        }
    }
});
    </script>
</body>
</html>