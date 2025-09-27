<?php
/**
 * Enhanced Student Registration Form
 * Clean, modern, and accessible student registration system
 * Features: Location management, real-time validation, progress tracking
 * Version: 2.0
 */

session_start();
require_once "config.php";
require_once "session_check.php";

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Get departments for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Rwanda Polytechnic Student Registration System">
    <meta name="author" content="Rwanda Polytechnic">
    <title>Student Registration - Rwanda Polytechnic</title>

    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="css/register-student-enhanced.css" rel="stylesheet">

    <!-- External JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/register-student-enhanced.js"></script>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-primary d-md-none position-fixed" id="mobileMenuToggle" style="top: 20px; left: 20px; z-index: 1050;">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="registration-container">
            <!-- Header -->
            <header class="registration-header">
                <h1><i class="fas fa-user-plus me-3"></i>Student Registration</h1>
                <p>Join Rwanda Polytechnic and start your educational journey</p>
            </header>

            <!-- Alert Container -->
            <div id="alertContainer" aria-live="polite" aria-atomic="true"></div>

            <!-- Registration Card -->
            <div class="registration-card">
                <!-- Progress Section -->
                <div class="progress-container">
                    <h5><i class="fas fa-chart-line me-2"></i>Registration Progress</h5>
                    <div class="progress">
                        <div class="progress-bar" id="formProgress" role="progressbar" style="width: 0%"></div>
                    </div>
                    <small class="text-white-50" id="progressText">0% complete</small>
                </div>

                <!-- Form -->
                <form id="registrationForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <!-- Personal Information Section -->
                    <section class="form-section">
                        <header class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Personal Information</h2>
                                <p class="section-description">Basic personal details and contact information</p>
                            </div>
                        </header>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="firstName" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="lastName" name="last_name" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="telephone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob">
                            </div>
                            <div class="form-group">
                                <label for="sex" class="form-label">Gender <span class="text-danger">*</span></label>
                                <select class="form-select" id="sex" name="sex" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <!-- Academic Information Section -->
                    <section class="form-section">
                        <header class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Academic Information</h2>
                                <p class="section-description">Department, program, and academic details</p>
                            </div>
                        </header>

                        <div class="academic-fields">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="reg_no" class="form-label">Registration Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="reg_no" name="reg_no" required maxlength="20">
                                </div>
                                <div class="form-group">
                                    <label for="studentIdNumber" class="form-label">Student ID Number</label>
                                    <input type="text" class="form-control" id="studentIdNumber" name="student_id_number" maxlength="16">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group form-group-full">
                                    <label for="department" class="form-label">Academic Department <span class="text-danger">*</span></label>
                                    <select class="form-select" id="department" name="department_id" required>
                                        <option value="">Select Your Academic Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['id']); ?>">
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group form-group-full">
                                    <label for="option" class="form-label">Program/Specialization <span class="text-danger">*</span></label>
                                    <select class="form-select" id="option" name="option_id" required disabled>
                                        <option value="">Select Department First</option>
                                    </select>
                                    <div class="program-status mt-2" id="programCount" style="display: none;">
                                        <i class="fas fa-info-circle"></i>
                                        <span id="programCountText"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="year_level" class="form-label">Year Level <span class="text-danger">*</span></label>
                                    <select class="form-select" id="year_level" name="year_level" required>
                                        <option value="">Select Year Level</option>
                                        <option value="1">Year 1</option>
                                        <option value="2">Year 2</option>
                                        <option value="3">Year 3</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Parent/Guardian Information Section -->
                    <section class="form-section">
                        <header class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Parent/Guardian Information</h2>
                                <p class="section-description">Emergency contact details (optional)</p>
                            </div>
                        </header>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="parent_first_name" class="form-label">Parent First Name</label>
                                <input type="text" class="form-control" id="parent_first_name" name="parent_first_name">
                            </div>
                            <div class="form-group">
                                <label for="parent_last_name" class="form-label">Parent Last Name</label>
                                <input type="text" class="form-control" id="parent_last_name" name="parent_last_name">
                            </div>
                            <div class="form-group">
                                <label for="parent_contact" class="form-label">Parent Contact</label>
                                <input type="tel" class="form-control" id="parent_contact" name="parent_contact">
                            </div>
                        </div>
                    </section>

                    <!-- Location Information Section -->
                    <section class="form-section">
                        <header class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Location Information</h2>
                                <p class="section-description">Complete address in Rwanda (Province → District → Sector → Cell)</p>
                            </div>
                        </header>

                        <div class="location-fields">
                            <div class="location-grid">
                                <div class="location-field">
                                    <label for="province" class="form-label">Province <span class="text-danger">*</span></label>
                                    <select class="form-select" id="province" name="province" required>
                                        <option value="">Select Province</option>
                                        <?php foreach ($provinces as $province): ?>
                                            <option value="<?php echo htmlspecialchars($province['id']); ?>">
                                                <?php echo htmlspecialchars($province['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="location-field">
                                    <label for="district" class="form-label">District <span class="text-danger">*</span></label>
                                    <select class="form-select" id="district" name="district" disabled required>
                                        <option value="">Select Province First</option>
                                    </select>
                                </div>

                                <div class="location-field">
                                    <label for="sector" class="form-label">Sector <span class="text-danger">*</span></label>
                                    <select class="form-select" id="sector" name="sector" disabled required>
                                        <option value="">Select District First</option>
                                    </select>
                                </div>

                                <div class="location-field">
                                    <label for="cell" class="form-label">Cell <span class="text-danger">*</span></label>
                                    <select class="form-select" id="cell" name="cell" disabled required>
                                        <option value="">Select Sector First</option>
                                    </select>
                                    <div class="cell-search-container" id="cellSearchContainer" style="display: none;">
                                        <input type="text" class="form-control" id="cellSearch" placeholder="Search cells...">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Media Section -->
                    <section class="form-section">
                        <header class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-camera"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Media & Biometrics</h2>
                                <p class="section-description">Photo and fingerprint capture (optional but recommended)</p>
                            </div>
                        </header>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Student Photo</label>
                                <input type="file" class="form-control d-none" id="photoInput" name="photo" accept="image/*">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary" id="selectPhotoBtn">
                                        <i class="fas fa-camera me-2"></i>Choose Photo
                                    </button>
                                    <button type="button" class="btn btn-outline-danger d-none" id="removePhoto">
                                        <i class="fas fa-times me-2"></i>Remove
                                    </button>
                                </div>
                                <img id="photoPreview" class="img-thumbnail mt-2 d-none" style="max-width: 200px;">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Fingerprint Capture</label>
                                <div class="fingerprint-container">
                                    <div class="fingerprint-display">
                                        <canvas id="fingerprintCanvas" width="200" height="200" class="d-none"></canvas>
                                        <div id="fingerprintPlaceholder" class="fingerprint-placeholder">
                                            <i class="fas fa-fingerprint fa-3x text-muted mb-2"></i>
                                            <p class="text-muted">No fingerprint captured</p>
                                        </div>
                                    </div>
                                    <div class="fingerprint-buttons">
                                        <button type="button" class="btn btn-outline-info" id="captureFingerprintBtn">
                                            <i class="fas fa-fingerprint me-2"></i>Capture
                                        </button>
                                        <button type="button" class="btn btn-outline-danger d-none" id="clearFingerprintBtn">
                                            <i class="fas fa-times me-2"></i>Clear
                                        </button>
                                        <button type="button" class="btn btn-outline-warning d-none" id="enrollFingerprintBtn">
                                            <i class="fas fa-save me-2"></i>Enroll
                                        </button>
                                    </div>
                                    <div class="fingerprint-status">
                                        <small id="fingerprintStatus" class="text-muted">Ready to capture fingerprint</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Complete Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div class="loading-overlay d-none" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5>Processing Registration</h5>
            <p class="mb-0">Please wait while we process your information...</p>
        </div>
    </div>
</body>
</html>