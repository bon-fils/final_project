<?php
// Standalone version for testing - no authentication required
$csrf_token = bin2hex(random_bytes(32));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Rwanda Polytechnic</title>

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
            <nav id="adminSidebar" class="col-md-3 col-lg-2 sidebar bg-dark text-white">
                <div class="position-sticky pt-3">
                    <!-- Logo/Brand -->
                    <div class="text-center mb-4">
                        <h5 class="text-white">
                            <i class="fas fa-graduation-cap me-2"></i>
                            Rwanda Polytechnic
                        </h5>
                    </div>

                    <!-- Navigation Menu -->
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a href="admin-dashboard.php" class="nav-link text-white">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="register-student-standalone.php" class="nav-link text-white active">
                                <i class="fas fa-user-plus me-2"></i>Register Student
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="manage-departments.php" class="nav-link text-white">
                                <i class="fas fa-building me-2"></i>Departments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="manage-users.php" class="nav-link text-white">
                                <i class="fas fa-users me-2"></i>Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="attendance-session.php" class="nav-link text-white">
                                <i class="fas fa-calendar-check me-2"></i>Attendance
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="admin-reports.php" class="nav-link text-white">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="system-logs.php" class="nav-link text-white">
                                <i class="fas fa-history me-2"></i>System Logs
                            </a>
                        </li>
                    </ul>

                    <!-- User Info -->
                    <div class="mt-4 pt-3 border-top border-secondary">
                        <div class="text-center">
                            <small class="text-muted">
                                Demo Mode - No Authentication Required
                            </small>
                            <br>
                            <a href="login.php" class="btn btn-outline-light btn-sm mt-2">
                                <i class="fas fa-sign-in-alt me-1"></i>Login
                            </a>
                        </div>
                    </div>
                </div>
            </nav>

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

                        <form id="registrationForm" enctype="multipart/form-data">
                            <div class="row">
                                <!-- Personal Information -->
                                <div class="col-md-6">
                                    <h6 class="section-title">Personal Information</h6>

                                    <div class="mb-3">
                                        <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="firstName" name="first_name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="lastName" name="last_name" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="telephone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="telephone" name="telephone" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="studentIdNumber" class="form-label">Student ID Number</label>
                                        <input type="text" class="form-control" id="studentIdNumber" name="studentIdNumber" maxlength="16">
                                    </div>
                                </div>

                                <!-- Academic Information -->
                                <div class="col-md-6">
                                    <h6 class="section-title">Academic Information</h6>

                                    <div class="mb-3">
                                        <label for="reg_no" class="form-label">Registration Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="reg_no" name="reg_no" required maxlength="20">
                                    </div>

                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                                        <select class="form-control" id="department" name="department" required>
                                            <option value="">Select Department</option>
                                            <?php
                                            try {
                                                require_once 'config.php';
                                                $deptStmt = $pdo->query("SELECT id, name, code FROM departments ORDER BY name");
                                                while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
                                                    echo "<option value=\"{$dept['id']}\">{$dept['name']} ({$dept['code']})</option>";
                                                }
                                            } catch (Exception $e) {
                                                echo "<option value=\"\">Error loading departments</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="option" class="form-label">Program <span class="text-danger">*</span></label>
                                        <select class="form-control" id="option" name="option" required disabled>
                                            <option value="">Select Department First</option>
                                        </select>
                                        <div class="spinner-border spinner-border-sm d-none program-loading" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="photoInput" class="form-label">Photo</label>
                                        <input type="file" class="form-control d-none" id="photoInput" name="photo" accept="image/*">
                                        <button type="button" class="btn btn-outline-primary" id="selectPhotoBtn">
                                            <i class="fas fa-camera me-2"></i>Choose Photo
                                        </button>
                                        <button type="button" class="btn btn-outline-danger d-none" id="removePhoto">
                                            <i class="fas fa-times me-2"></i>Remove
                                        </button>
                                        <img id="photoPreview" class="img-thumbnail mt-2 d-none" style="max-width: 200px;">
                                    </div>
                                </div>
                            </div>

                            <!-- Location Information -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h6 class="section-title">Location Information</h6>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="province" class="form-label">Province</label>
                                        <select class="form-control" id="province" name="province">
                                            <option value="">Select Province</option>
                                            <option value="1">Kigali City</option>
                                            <option value="2">Southern Province</option>
                                            <option value="3">Western Province</option>
                                            <option value="4">Eastern Province</option>
                                            <option value="5">Northern Province</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="district" class="form-label">District</label>
                                        <select class="form-control" id="district" name="district" disabled>
                                            <option value="">Select Province First</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="sector" class="form-label">Sector</label>
                                        <select class="form-control" id="sector" name="sector" disabled>
                                            <option value="">Select District First</option>
                                        </select>
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
            </main>
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
            }

            .main-content.sidebar-open {
                margin-left: 250px;
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

        .alert {
            margin-bottom: 1rem;
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
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.updateProgress();
        this.showWelcomeMessage();
    }

    setupEventListeners() {
        // Department change with error handling
        $('#department').on('change', this.debounce(this.handleDepartmentChange.bind(this), 300));

        // Location hierarchy
        $('#province').on('change', this.debounce(this.handleProvinceChange.bind(this), 300));
        $('#district').on('change', this.debounce(this.handleDistrictChange.bind(this), 300));
        $('#sector').on('change', this.debounce(this.handleSectorChange.bind(this), 300));

        // Photo handling
        $('#selectPhotoBtn').on('click', () => $('#photoInput').click());
        $('#photoInput').on('change', this.handlePhotoSelect.bind(this));
        $('#removePhoto').on('click', this.removePhoto.bind(this));

        // Form submission
        $('#registrationForm').on('submit', this.handleSubmit.bind(this));

        // Real-time validation
        $('input[required]').on('blur', this.validateField.bind(this));
        $('input[required]').on('input', this.debounce(this.updateProgress.bind(this), 200));

        // Enhanced registration number validation
        $('input[name="reg_no"]').on('input', this.validateRegistrationNumber.bind(this));
        $('#studentIdNumber').on('input', this.validateStudentId.bind(this));
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
        const deptId = $('#department').val();
        const $option = $('#option');
        const $loadingSpinner = $('.program-loading');

        if (!deptId) {
            $option.prop('disabled', true).html('<option value="">Select Department First</option>');
            return;
        }

        $option.prop('disabled', true);
        $loadingSpinner.removeClass('d-none');

        try {
            const response = await this.retryableAjax({
                url: 'api/department-option-api.php',
                method: 'POST',
                data: {
                    action: 'get_options',
                    department_id: deptId,
                    csrf_token: '<?= addslashes($csrf_token) ?>'
                }
            });

            if (response.success) {
                if (response.options && response.options.length > 0) {
                    const options = response.options.map(opt =>
                        `<option value="${opt.id}">${this.escapeHtml(opt.name)} (${opt.code})</option>`
                    ).join('');

                    $option.html('<option value="">Select Program</option>' + options)
                            .prop('disabled', false)
                            .addClass('programs-loaded');

                    this.showAlert(`${response.options.length} programs loaded`, 'success');
                } else {
                    $option.html('<option value="">No programs available</option>');
                    this.showAlert('No programs found for this department', 'warning');
                }
            } else {
                throw new Error(response.message || 'Failed to load options');
            }
        } catch (error) {
            console.error('Department change error:', error);
            $option.html('<option value="">Error loading programs</option>');
            this.showAlert('Failed to load programs. Please try again.', 'error');
        } finally {
            $loadingSpinner.addClass('d-none');
        }
    }

    async retryableAjax(options, retries = this.retryAttempts) {
        for (let i = 0; i < retries; i++) {
            try {
                const response = await $.ajax({
                    ...options,
                    timeout: 10000,
                    dataType: 'json'
                });
                return response;
            } catch (error) {
                if (i === retries - 1) throw error;
                await new Promise(resolve => setTimeout(resolve, this.retryDelay * (i + 1)));
            }
        }
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

    async handleSubmit(e) {
        e.preventDefault();

        if (!this.validateForm()) {
            this.showAlert('Please correct the errors before submitting.', 'error');
            this.scrollToFirstError();
            return;
        }

        if (!await this.confirmSubmission()) {
            return;
        }

        try {
            this.showLoading(true);
            this.disableForm(true);

            const formData = new FormData(e.target);
            // Ensure CSRF token is included
            formData.append('csrf_token', '<?= $csrf_token ?>');

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
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();

        // Required field validation
        $('#registrationForm [required]').each(function() {
            if (!$(this).val().trim()) {
                $(this).addClass('is-invalid');
                isValid = false;
            }
        });

        // Department-program dependency
        const departmentId = $('#department').val();
        const optionId = $('#option').val();
        if (departmentId && !optionId) {
            $('#option').addClass('is-invalid')
                       .after('<div class="invalid-feedback">Please select a program</div>');
            isValid = false;
        }

        // Email format validation
        const email = $('#email').val();
        if (email && !this.isValidEmail(email)) {
            $('#email').addClass('is-invalid')
                      .after('<div class="invalid-feedback">Please enter a valid email address</div>');
            isValid = false;
        }

        return isValid;
    }

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    showSuccess(response) {
        this.showAlert(response.message, 'success');

        // Create success modal
        if ($('#successModal').length === 0) {
            $('body').append(`
                <div class="modal fade" id="successModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-success text-white">
                                <h5 class="modal-title">Registration Successful</h5>
                            </div>
                            <div class="modal-body text-center">
                                <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                <p>${response.message}</p>
                                <p><strong>Student ID:</strong> ${response.student_id}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" id="continueButton">Continue</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }

        const modal = new bootstrap.Modal(document.getElementById('successModal'));
        modal.show();

        $('#continueButton').off('click').on('click', function() {
            modal.hide();
            window.location.href = response.redirect || 'admin-dashboard.php';
        });
    }

    updateProgress() {
        const totalFields = $('#registrationForm [required]').length;
        const filledFields = $('#registrationForm [required]').filter(function() {
            return $(this).val().trim().length > 0;
        }).length;

        const progress = Math.round((filledFields / totalFields) * 100);
        $('#formProgress').css('width', progress + '%');
        $('#progressText').text(progress + '% complete');

        // Update progress bar color based on completion
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

    handleSubmissionError(response) {
        console.error('Submission error:', response);
        this.showAlert(`Submission failed: ${response.message || 'Unknown error'}`, 'error');
    }

    handleNetworkError(error) {
        console.error('Network error:', error);
        this.showAlert('Network error occurred. Please check your connection and try again.', 'error');
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

        // Basic validation based on field type
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
                    this.showFieldError(field, 'Please enter a valid phone number');
                } else {
                    this.clearFieldError(field);
                }
                break;
        }
    }

    isValidPhone(phone) {
        const phoneRegex = /^(\+?250|0)?[0-9]{9}$/;
        return phoneRegex.test(phone);
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
        const value = e.target.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase().substring(0, 20);
        e.target.value = value;

        if (value.length >= 5) {
            $(e.target).addClass('is-valid');
        } else {
            $(e.target).removeClass('is-valid is-invalid');
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

    // Location hierarchy handlers
    async handleProvinceChange() {
        const provinceId = $('#province').val();
        const $district = $('#district');

        if (!provinceId) {
            $district.prop('disabled', true).html('<option value="">Select Province First</option>');
            return;
        }

        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_districts',
                    province_id: provinceId,
                    csrf_token: '<?= addslashes($csrf_token) ?>'
                }
            });

            if (response.success && response.districts) {
                const options = response.districts.map(district =>
                    `<option value="${district.id}">${this.escapeHtml(district.name)}</option>`
                ).join('');

                $district.html('<option value="">Select District</option>' + options)
                        .prop('disabled', false);
            }
        } catch (error) {
            console.error('Province change error:', error);
            $district.html('<option value="">Error loading districts</option>');
        }
    }

    async handleDistrictChange() {
        const districtId = $('#district').val();
        const $sector = $('#sector');

        if (!districtId) {
            $sector.prop('disabled', true).html('<option value="">Select District First</option>');
            return;
        }

        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_sectors',
                    district_id: districtId,
                    csrf_token: '<?= addslashes($csrf_token) ?>'
                }
            });

            if (response.success && response.sectors) {
                const options = response.sectors.map(sector =>
                    `<option value="${sector.id}">${this.escapeHtml(sector.name)}</option>`
                ).join('');

                $sector.html('<option value="">Select Sector</option>' + options)
                      .prop('disabled', false);
            }
        } catch (error) {
            console.error('District change error:', error);
            $sector.html('<option value="">Error loading sectors</option>');
        }
    }

    async handleSectorChange() {
        // Sector change logic if needed
        const sectorId = $('#sector').val();
        if (sectorId) {
            console.log('Sector selected:', sectorId);
        }
    }

    // Photo handling methods
    handlePhotoSelect(e) {
        const file = e.target.files[0];
        if (file && this.validateImage(file)) {
            const reader = new FileReader();
            reader.onload = (e) => {
                $('#photoPreview').attr('src', e.target.result).removeClass('d-none');
                $('#removePhoto').removeClass('d-none');
            };
            reader.readAsDataURL(file);
        }
    }

    removePhoto() {
        $('#photoInput').val('');
        $('#photoPreview').addClass('d-none').attr('src', '');
        $('#removePhoto').addClass('d-none');
    }

    // Additional utility methods
    showAlert(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' :
                          type === 'error' ? 'alert-danger' :
                          type === 'warning' ? 'alert-warning' : 'alert-info';

        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

        $('#alertContainer').html(alertHtml);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            $('.alert').fadeOut();
        }, 5000);
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