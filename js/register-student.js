/**
 * Enhanced Student Registration JavaScript
 * Handles form validation, AJAX requests, and UI interactions
 * Version: 2.0 - Refined and optimized
 */

// Global variables
let videoStream = null;
let currentPhotoData = null;
let formAutoSave = null;

/**
 * Initialize form when document is ready
 */
$(document).ready(function() {
    initializeForm();
    setupEventListeners();
    updateFormProgress();
    initializeAutoSave();
});

/**
 * Initialize form components
 */
function initializeForm() {
    // Set current date/time
    $('#currentDateTime').text(new Date().toLocaleString());

    // Focus on first input
    $('#first_name').focus();

    // Initialize tooltips if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

/**
 * Setup all event listeners
 */
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

    // Auto-save on form changes
    $('#registrationForm input, #registrationForm select').on('input change', function() {
        clearTimeout(formAutoSave);
        formAutoSave = setTimeout(saveFormData, 2000); // Auto-save after 2 seconds
    });
}

/**
 * Handle department selection change
 */
function handleDepartmentChange() {
    const depId = $(this).val();
    const optionSelect = $('#option');

    if (depId) {
        showLoading('Loading programs...');
        optionSelect.prop('disabled', true);

        $.ajax({
            url: '',
            method: 'POST',
            data: { get_options: 1, dep_id: depId },
            dataType: 'json',
            success: function(response) {
                hideLoading();
                if (response.success) {
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

/**
 * Setup real-time validation for form fields
 */
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

/**
 * Validate individual field via AJAX
 */
function validateField(field, value) {
    if (!value.trim()) return;

    const feedbackId = field + 'Feedback';
    const inputElement = $(`#${field}`);

    inputElement.removeClass('is-valid is-invalid');

    $.ajax({
        url: '',
        method: 'POST',
        data: { check_duplicate: 1, field: field, value: value },
        dataType: 'json',
        success: function(response) {
            if (response.valid) {
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

/**
 * Clear field validation styling
 */
function clearFieldValidation(field) {
    const inputElement = $(`#${field}`);
    const feedbackId = field + 'Feedback';

    inputElement.removeClass('is-valid is-invalid');
    $(`#${feedbackId}`).hide();
}

/**
 * Setup photo upload functionality
 */
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
        if (file) {
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
        if (files.length > 0) {
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
        if (!file.type.startsWith('image/')) {
            showAlert('danger', 'Please select a valid image file.');
            return;
        }

        // Validate file size (5MB)
        if (file.size > 5 * 1024 * 1024) {
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

/**
 * Setup camera functionality
 */
function setupCameraHandlers() {
    const cameraContainer = $('#cameraContainer');
    const video = $('#video');
    const captureBtn = $('#captureBtn');
    const cancelBtn = $('#cancelCamera');
    const cameraPhoto = $('#cameraPhoto');

    $('#useCameraBtn').on('click', function() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            cameraContainer.show();
            $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Starting Camera...');

            navigator.mediaDevices.getUserMedia({
                video: {
                    width: 320,
                    height: 240,
                    facingMode: 'user'
                }
            })
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
        if (videoStream) {
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
        if (videoStream) {
            videoStream.getTracks().forEach(track => track.stop());
        }
        cameraContainer.hide();
    });
}

/**
 * Setup fingerprint functionality
 */
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

/**
 * Handle form submission with enhanced validation
 */
function handleFormSubmission(e) {
    e.preventDefault();

    if (!validateForm()) {
        showAlert('warning', 'Please fill in all required fields correctly.');
        return;
    }

    const formData = new FormData(this);
    if (currentPhotoData) {
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

            if (response.success) {
                showAlert('success', response.message);

                // Clear auto-save data
                localStorage.removeItem('studentRegistrationData');

                // Reset form after successful registration
                setTimeout(function() {
                    if (response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        resetForm();
                    }
                }, 2000);
            } else {
                if (response.errors && Array.isArray(response.errors)) {
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

            let errorMessage = 'Network error. Please try again.';
            if (xhr.status === 413) {
                errorMessage = 'File too large. Please choose a smaller image.';
            } else if (xhr.status === 403) {
                errorMessage = 'Security token expired. Please refresh the page.';
            }

            showAlert('danger', errorMessage);
        }
    });
}

/**
 * Validate entire form before submission
 */
function validateForm() {
    let isValid = true;
    const requiredFields = ['first_name', 'last_name', 'email', 'reg_no', 'department_id', 'option_id', 'telephone', 'year_level', 'sex'];

    requiredFields.forEach(field => {
        const element = $(`#${field}`);
        if (!element.val().trim()) {
            element.addClass('is-invalid');
            isValid = false;
        } else {
            element.removeClass('is-invalid');
        }
    });

    // Email validation
    const email = $('#email').val();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        $('#email').addClass('is-invalid');
        isValid = false;
    }

    // Phone validation
    const phone = $('#telephone').val();
    if (phone && !/^[\d\s\-\+\(\)]+$/.test(phone)) {
        $('#telephone').addClass('is-invalid');
        isValid = false;
    }

    // Registration number validation
    const regNo = $('#reg_no').val();
    if (regNo && !/^[A-Za-z0-9]+$/.test(regNo)) {
        $('#reg_no').addClass('is-invalid');
        isValid = false;
    }

    return isValid;
}

/**
 * Reset form to initial state
 */
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

    // Clear auto-save data
    localStorage.removeItem('studentRegistrationData');

    // Clear any camera streams
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
}

/**
 * Show registration preview modal
 */
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
        if (data[key]) {
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
                        <button type="button" class="btn btn-primary" onclick="$('#submitBtn').click()">Confirm Registration</button>
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

/**
 * Update form progress bar
 */
function updateFormProgress() {
    const totalFields = $('input[required], select[required]').length;
    const filledFields = $('input[required], select[required]').filter(function() {
        return $(this).val().trim() !== '';
    }).length;

    const progress = totalFields > 0 ? (filledFields / totalFields) * 100 : 0;
    $('#formProgress').css('width', progress + '%');
}

/**
 * Initialize auto-save functionality
 */
function initializeAutoSave() {
    // Load saved data on page load
    const savedData = localStorage.getItem('studentRegistrationData');
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            Object.keys(data).forEach(key => {
                $(`#${key}`).val(data[key]);
            });
            showAlert('info', 'Previous form data restored.');
        } catch (e) {
            console.error('Error loading saved data:', e);
        }
    }
}

/**
 * Save form data to localStorage
 */
function saveFormData() {
    const formData = {
        first_name: $('#first_name').val(),
        last_name: $('#last_name').val(),
        email: $('#email').val(),
        reg_no: $('#reg_no').val(),
        telephone: $('#telephone').val(),
        department_id: $('#department').val(),
        option_id: $('#option').val(),
        year_level: $('#year_level').val(),
        sex: $('#sex').val()
    };

    localStorage.setItem('studentRegistrationData', JSON.stringify(formData));
    console.log('Form data auto-saved');
}

/**
 * Show loading overlay
 */
function showLoading(message = 'Processing...') {
    $('#loadingOverlay .text-center h5').text(message);
    $('#loadingOverlay').fadeIn();
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    $('#loadingOverlay').fadeOut();
}

/**
 * Show alert message
 */
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

/**
 * Toggle sidebar for mobile
 */
function toggleSidebar() {
    $('#sidebar').toggleClass('show');
}

/**
 * Cleanup on page unload
 */
$(window).on('beforeunload', function() {
    if (videoStream) {
        videoStream.getTracks().forEach(track => track.stop());
    }
});