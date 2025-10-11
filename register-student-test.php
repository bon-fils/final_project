<?php
/**
 * TEST VERSION - Enhanced Student Registration Form
 * Clean, modern, and accessible student registration system
 * Features: Location management, real-time validation, progress tracking
 * Version: 2.0 - TEST MODE
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
$provinces = [];

// Get departments for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching departments: " . $e->getMessage());
    $departments = [];
}

// Get provinces for location dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM provinces ORDER BY name");
    $provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching provinces: " . $e->getMessage());
    $provinces = [];
}

// Fallback to hardcoded provinces if DB query failed or returned empty
if (empty($provinces)) {
    $provinces = [
        ['id' => 1, 'name' => 'Kigali City'],
        ['id' => 2, 'name' => 'Southern Province'],
        ['id' => 3, 'name' => 'Western Province'],
        ['id' => 4, 'name' => 'Eastern Province'],
        ['id' => 5, 'name' => 'Northern Province']
    ];
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
    <title>TEST MODE - Student Registration - Rwanda Polytechnic</title>

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
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/admin_sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-user-plus me-2"></i>TEST MODE - Student Registration</h2>
                    <div class="d-flex gap-2">
                        <span class="badge bg-warning text-dark fs-6">TEST ENVIRONMENT</span>
                        <button class="btn btn-outline-secondary d-md-none" id="mobileMenuToggle">
                            <i class="fas fa-bars"></i>
                        </button>
                    </div>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- Registration Form -->
<div class="card">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-flask me-2"></i>TEST MODE - Student Information</h5>
        <small class="text-muted">This is a test environment. Data entered here will be for testing purposes only.</small>
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
            <input type="hidden" name="test_mode" value="1">

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

            <!-- Location Information -->
            <div class="row mt-4">
                <div class="col-12">
                    <h6 class="section-title">
                        <i class="fas fa-map-marker-alt me-2"></i>Location Information
                    </h6>
                    <p class="text-muted small mb-3">Please select your complete location in Rwanda (Province → District → Sector → Cell)</p>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="province" class="form-label d-flex align-items-center">
                            <i class="fas fa-city me-2 text-success"></i>
                            <strong>Province</strong>
                        </label>
                        <select class="form-control" id="province" name="province" aria-label="Province">
                            <option value="">Select Province</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="district" class="form-label d-flex align-items-center">
                            <i class="fas fa-building me-2 text-primary"></i>
                            <strong>District</strong>
                        </label>
                        <select class="form-control" id="district" name="district" disabled aria-label="District">
                            <option value="">Select Province First</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="sector" class="form-label d-flex align-items-center">
                            <i class="fas fa-home me-2 text-info"></i>
                            <strong>Sector</strong>
                        </label>
                        <select class="form-control" id="sector" name="sector" disabled aria-label="Sector">
                            <option value="">Select District First</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="mb-3">
                        <label for="cell" class="form-label d-flex align-items-center justify-content-between">
                            <span class="d-flex align-items-center">
                                <i class="fas fa-map-pin me-2 text-warning fs-5"></i>
                                <strong>Cell</strong>
                            </span>
                            <span class="badge bg-warning text-dark rounded-pill">
                                <i class="fas fa-home me-1"></i>Final
                            </span>
                        </label>
                        <select class="form-control" id="cell" name="cell" disabled aria-label="Cell">
                            <option value="">Select Sector First</option>
                        </select>
                        <div class="mt-2" id="cellSearchContainer" style="display: none;">
                            <input type="text" class="form-control form-control-sm" id="cellSearch" placeholder="🔍 Search cells..." aria-label="Search cells">
                        </div>
                        <div class="mt-2 alert alert-info py-2 px-3 border-0 d-none" id="cellInfo" style="background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-map-marker-alt text-info me-2 fs-5"></i>
                                <div>
                                    <strong class="text-info">Location Selected</strong><br>
                                    <small id="cellInfoText" class="text-info-emphasis fw-medium"></small>
                                </div>
                            </div>
                        </div>
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

                        <!-- Webcam Capture Section -->
                        <div class="webcam-capture-container mb-3">
                            <div class="webcam-display border rounded p-3 bg-light">
                                <video id="registrationWebcam" autoplay muted playsinline class="d-none" style="width: 100%; max-width: 300px; border-radius: 8px;"></video>
                                <div id="webcamPlaceholder" class="text-center py-4">
                                    <i class="fas fa-video fa-2x text-muted mb-2"></i>
                                    <p class="text-muted mb-2">Webcam not active</p>
                                    <small class="text-muted">Click "Start Webcam" to capture face images</small>
                                </div>
                            </div>
                            <div class="webcam-controls mt-2 d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-sm btn-outline-success" id="startWebcamBtn">
                                    <i class="fas fa-video me-1"></i>Start Webcam
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary d-none" id="captureFaceBtn">
                                    <i class="fas fa-camera me-1"></i>Capture Image
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger d-none" id="stopWebcamBtn">
                                    <i class="fas fa-stop me-1"></i>Stop Webcam
                                </button>
                            </div>
                            <div class="mt-2">
                                <small id="webcamStatus" class="text-muted">Webcam ready</small>
                            </div>
                        </div>

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
                    <button type="submit" class="btn btn-warning btn-lg" id="submitBtn">
                        <i class="fas fa-flask me-2"></i>Test Register Student
                    </button>
                    <div class="mt-2">
                        <small class="text-muted">This will create a test student record</small>
                    </div>
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
        <div class="mt-2">Processing test registration...</div>
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

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }

        .btn-warning:hover {
            background-color: #ffb300;
            border-color: #ffb300;
            color: #212529;
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

        /* Location loading states */
        .location-loading {
            position: relative;
            opacity: 0.7;
        }

        .location-loading::after {
            content: '';
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            border: 2px solid #0d6efd;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translateY(-50%) rotate(0deg); }
            50% { transform: translateY(-50%) rotate(45deg); }
            100% { transform: translateY(-50%) rotate(360deg); }
        }

        /* Enhanced location section styling */
        .section-title i.fa-map-marker-alt {
            color: #198754;
        }

        /* Location cascade animation */
        .location-field {
            transition: all 0.3s ease;
        }

        .location-field.enabled {
            animation: fadeInUp 0.3s ease-out;
        }

        /* Better visual hierarchy for location fields */
        #province, #district, #sector {
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        #province:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }

        #district:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        #sector:focus {
            border-color: #6f42c1;
            box-shadow: 0 0 0 0.2rem rgba(111, 66, 193, 0.25);
        }

        #cell:focus {
            border-color: #fd7e14;
            box-shadow: 0 0 0 0.2rem rgba(253, 126, 20, 0.25);
        }

        /* Cell information display */
        #cellInfo {
            animation: fadeInDown 0.3s ease-out;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Enhanced cell selection feedback */
        #cell.border-success {
            animation: successPulse 2s ease-in-out;
        }

        @keyframes successPulse {
            0% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
            50% { box-shadow: 0 0 0 4px rgba(25, 135, 84, 0.3); }
            100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0); }
        }

        /* Cell badge styling */
        .badge.bg-warning.text-dark {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%) !important;
            border: none;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
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
  * TEST MODE - Enhanced Student Registration System - JavaScript
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
        this.isTestMode = true; // Test mode flag
        // Location caching for performance
        this.locationCache = {
            districts: new Map(),
            sectors: new Map(),
            cells: new Map()
        };
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

        // Initialize location fields
        this.initializeLocationFields();

        // Pre-validate form on load
        setTimeout(() => {
            this.validateForm();
        }, 1000);

        // Initialize fingerprint UI
        this.updateFingerprintUI('ready');

        // Show test mode warning
        this.showAlert('⚠️ TEST MODE: This form creates test student records only.', 'warning', false);
    }

    initializeLocationFields() {
        // Load provinces on page load
        this.loadProvinces();

        // Add enabled class to province field (always available)
        $('#province').addClass('enabled');

        // Reset location fields to initial state
        this.resetLocationFields('province');
    }

    async loadProvinces() {
        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_provinces',
                    csrf_token: this.csrfToken
                }
            });

            if (response.success && response.provinces) {
                const options = response.provinces.map(province =>
                    `<option value="${province.id}">${this.escapeHtml(province.name)}</option>`
                ).join('');

                $('#province').append(options);
                this.showAlert(`📍 ${response.provinces.length} provinces loaded successfully!`, 'success');
            } else {
                throw new Error(response.message || 'Failed to load provinces');
            }
        } catch (error) {
            console.error('Province loading error:', error);
            $('#province').html('<option value="">❌ Failed to load provinces</option>');
            this.showAlert('❌ Failed to load provinces. Please refresh the page.', 'error');
        }
    }

    async handleProvinceChange() {
        const provinceId = $('#province').val();
        const $district = $('#district');

        // Reset dependent fields
        this.resetLocationFields('district');

        if (!provinceId) {
            return;
        }

        // Check cache first
        if (this.locationCache.districts.has(provinceId)) {
            this.populateDistricts(this.locationCache.districts.get(provinceId));
            return;
        }

        // Show loading state
        $district.prop('disabled', true).html('<option value="">Loading districts...</option>');
        this.showLocationLoading($district, true);

        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_districts',
                    province_id: parseInt(provinceId, 10),
                    csrf_token: this.csrfToken
                }
            });

            if (response.success && response.districts) {
                // Cache the results
                this.locationCache.districts.set(provinceId, response.districts);
                this.populateDistricts(response.districts);

                this.showAlert(`🏛️ ${response.districts.length} district${response.districts.length !== 1 ? 's' : ''} loaded for ${$('#province option:selected').text()}`, 'success');
            } else {
                throw new Error(response.message || 'No districts found');
            }
        } catch (error) {
            console.error('Province change error:', error);
            $district.html('<option value="">❌ Failed to load districts</option>');
            this.showAlert('❌ Failed to load districts. Please try selecting the province again.', 'error');
        } finally {
            this.showLocationLoading($district, false);
            $district.prop('disabled', false);
        }
    }

    populateDistricts(districts) {
        const $district = $('#district');
        const options = districts.map(district =>
            `<option value="${district.id}">${this.escapeHtml(district.name)}</option>`
        ).join('');

        $district.html('<option value="">🏛️ Select District</option>' + options)
                .addClass('enabled');
    }

    resetLocationFields(fromLevel) {
        const levels = ['district', 'sector', 'cell'];

        levels.forEach(level => {
            if (levels.indexOf(fromLevel) <= levels.indexOf(level)) {
                const $field = $(`#${level}`);
                $field.prop('disabled', true).val('').removeClass('enabled');

                if (level === 'district') {
                    $field.html('<option value="">🏛️ Select Province First</option>');
                } else if (level === 'sector') {
                    this.locationCache.cells.clear();
                } else if (level === 'sector') {
                    this.locationCache.cells.clear();
                }
            }
        });
