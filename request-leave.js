// request-leave.js - Enhanced form handling and validation

// Global variables
let selectedFile = null;
let isDragOver = false;

// Initialize when document is ready
$(document).ready(function() {
    initializeForm();
    setupEventListeners();
    setupFileUpload();
    setupFormValidation();
    updateFormProgress();
});

// Initialize form components
function initializeForm() {
    // Set minimum dates
    const today = new Date().toISOString().split('T')[0];
    $('#fromDate, #toDate').attr('min', today);

    // Focus on first field
    $('#requestTo').focus();

    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
}

// Setup all event listeners
function setupEventListeners() {
    // Request type change handler
    $('#requestTo').on('change', handleRequestTypeChange);

    // Date change handlers
    $('#fromDate, #toDate').on('change', handleDateChange);

    // Reason input handler
    $('#reason').on('input', handleReasonInput);

    // Form submission
    $('#leaveRequestForm').on('submit', handleFormSubmission);

    // Preview button
    $('#previewBtn').on('click', showPreview);

    // Reset button
    $('#resetForm').on('click', resetForm);

    // Sidebar toggle
    $('#sidebarToggle').on('click', toggleSidebar);

    // Form progress tracking
    $('input, select, textarea').on('input change', updateFormProgress);
}

// Handle request type change
function handleRequestTypeChange() {
    const requestType = $(this).val();
    const courseWrapper = $('#courseSelectWrapper');
    const courseSelect = $('#courseId');

    if (requestType === 'lecturer') {
        courseWrapper.slideDown(300);
        courseSelect.attr('required', 'required');
    } else {
        courseWrapper.slideUp(300);
        courseSelect.removeAttr('required');
        courseSelect.val('');
    }

    updateFormProgress();
}

// Handle date changes
function handleDateChange() {
    const fromDate = $('#fromDate').val();
    const toDate = $('#toDate').val();

    if (fromDate && toDate) {
        calculateLeaveDuration(fromDate, toDate);
        validateDateRange(fromDate, toDate);
    } else {
        $('#durationInfo').hide();
    }
}

// Calculate and display leave duration
function calculateLeaveDuration(fromDate, toDate) {
    const start = new Date(fromDate);
    const end = new Date(toDate);
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // Include both dates

    $('#leaveDuration').text(`${diffDays} day${diffDays !== 1 ? 's' : ''}`);
    $('#durationInfo').show();
}

// Validate date range
function validateDateRange(fromDate, toDate) {
    const start = new Date(fromDate);
    const end = new Date(toDate);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Clear previous validation
    $('#fromDate, #toDate').removeClass('is-invalid is-valid');

    if (start < today || end < today) {
        showValidationError('Cannot request leave for past dates');
        $('#fromDate, #toDate').addClass('is-invalid');
        return false;
    }

    if (start > end) {
        showValidationError('From date cannot be later than to date');
        $('#fromDate, #toDate').addClass('is-invalid');
        return false;
    }

    $('#fromDate, #toDate').addClass('is-valid');
    return true;
}

// Handle reason input
function handleReasonInput() {
    const reason = $(this).val();
    const length = reason.length;
    const maxLength = 500;

    $('#reasonCount').text(`${length}/${maxLength}`);

    // Visual feedback
    if (length > maxLength * 0.9) {
        $('#reasonCount').removeClass('text-muted').addClass('text-danger');
    } else if (length > maxLength * 0.7) {
        $('#reasonCount').removeClass('text-muted text-danger').addClass('text-warning');
    } else {
        $('#reasonCount').removeClass('text-warning text-danger').addClass('text-muted');
    }
}

// Setup file upload functionality
function setupFileUpload() {
    const uploadArea = $('#fileUploadArea');
    const fileInput = $('#supportingFile');
    const selectFileBtn = $('#selectFileBtn');
    const removeFileBtn = $('#removeFile');

    // Click to select file
    selectFileBtn.on('click', function() {
        fileInput.click();
    });

    // File input change
    fileInput.on('change', function() {
        handleFileSelection(this.files[0]);
    });

    // Drag and drop
    uploadArea.on('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('dragover');
    });

    uploadArea.on('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');
    });

    uploadArea.on('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('dragover');

        const files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            handleFileSelection(files[0]);
        }
    });

    // Remove file
    removeFileBtn.on('click', function() {
        removeSelectedFile();
    });
}

// Handle file selection
function handleFileSelection(file) {
    if (!file) return;

    // Validate file
    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/jpg', 'image/png'];
    const maxSize = 5 * 1024 * 1024; // 5MB

    if (!allowedTypes.includes(file.type)) {
        showAlert('Invalid file type. Please select PDF, DOC, DOCX, or image files.', 'danger');
        return;
    }

    if (file.size > maxSize) {
        showAlert('File size too large. Maximum size is 5MB.', 'danger');
        return;
    }

    selectedFile = file;
    displayFilePreview(file);
}

// Display file preview
function displayFilePreview(file) {
    const fileName = file.name;
    const fileSize = (file.size / 1024).toFixed(1) + ' KB';
    const fileIcon = getFileIcon(file.type);

    $('#fileName').text(fileName);
    $('#fileSize').text(fileSize);
    $('#filePreview .file-icon i').attr('class', `fas fa-${fileIcon}`);

    $('#uploadContent').hide();
    $('#filePreview').show();

    // Update form validation
    $('#supportingFile')[0].files = createFileList(file);
}

// Get appropriate file icon
function getFileIcon(mimeType) {
    if (mimeType === 'application/pdf') return 'file-pdf';
    if (mimeType.includes('word')) return 'file-word';
    if (mimeType.includes('image')) return 'file-image';
    return 'file';
}

// Create file list for input
function createFileList(file) {
    const dt = new DataTransfer();
    dt.items.add(file);
    return dt.files;
}

// Remove selected file
function removeSelectedFile() {
    selectedFile = null;
    $('#supportingFile').val('');
    $('#uploadContent').show();
    $('#filePreview').hide();
}

// Setup form validation
function setupFormValidation() {
    // Real-time validation for required fields
    $('input[required], select[required], textarea[required]').on('blur', function() {
        validateField($(this));
    });

    // Email validation if needed
    $('#email').on('blur', function() {
        validateEmail($(this));
    });
}

// Validate individual field
function validateField(field) {
    const value = field.val().trim();
    const isValid = value !== '';

    field.toggleClass('is-valid', isValid);
    field.toggleClass('is-invalid', !isValid);

    return isValid;
}

// Validate email format
function validateEmail(field) {
    const email = field.val().trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const isValid = email === '' || emailRegex.test(email);

    field.toggleClass('is-valid', isValid && email !== '');
    field.toggleClass('is-invalid', !isValid);

    return isValid;
}

// Handle form submission
function handleFormSubmission(e) {
    e.preventDefault();

    // Validate all required fields
    let isValid = true;
    $('input[required], select[required], textarea[required]').each(function() {
        if (!validateField($(this))) {
            isValid = false;
        }
    });

    // Additional validations
    const fromDate = $('#fromDate').val();
    const toDate = $('#toDate').val();

    if (fromDate && toDate) {
        if (!validateDateRange(fromDate, toDate)) {
            isValid = false;
        }
    }

    if (!isValid) {
        showAlert('Please fill in all required fields correctly.', 'danger');
        // Scroll to first invalid field
        const firstInvalid = $('.is-invalid').first();
        if (firstInvalid.length) {
            firstInvalid[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }

    // Show loading overlay
    showLoading('Submitting your leave request...');

    // Submit form
    const formData = new FormData(this);

    $.ajax({
        url: '',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function(response) {
            hideLoading();

            if (response.success) {
                showAlert(response.message, 'success');

                // Reset form after successful submission
                setTimeout(function() {
                    resetForm();
                    // Optionally redirect to leave status page
                    // window.location.href = 'leave-status.php';
                }, 2000);
            } else {
                showAlert(response.message || 'Failed to submit leave request.', 'danger');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Submission error:', error);
            showAlert('Network error. Please try again.', 'danger');
        }
    });
}

// Show preview modal
function showPreview() {
    const formData = {
        requestTo: $('#requestTo option:selected').text(),
        course: $('#courseId option:selected').text(),
        fromDate: $('#fromDate').val(),
        toDate: $('#toDate').val(),
        reason: $('#reason').val(),
        fileName: selectedFile ? selectedFile.name : 'No file attached'
    };

    let previewHtml = `
        <div class="row g-3">
            <div class="col-md-6">
                <h6>Request Details</h6>
                <p><strong>Request To:</strong> ${formData.requestTo}</p>
                ${formData.course !== '-- Select Course --' ? `<p><strong>Course:</strong> ${formData.course}</p>` : ''}
                <p><strong>From Date:</strong> ${formData.fromDate || 'Not set'}</p>
                <p><strong>To Date:</strong> ${formData.toDate || 'Not set'}</p>
            </div>
            <div class="col-md-6">
                <h6>Reason & Documents</h6>
                <p><strong>Reason:</strong> ${formData.reason || 'Not provided'}</p>
                <p><strong>Attachment:</strong> ${formData.fileName}</p>
            </div>
        </div>
    `;

    $('#previewContent').html(previewHtml);
    $('#previewModal').modal('show');
}

// Reset form
function resetForm() {
    $('#leaveRequestForm')[0].reset();
    $('.is-valid, .is-invalid').removeClass('is-valid is-invalid');
    $('#courseSelectWrapper').hide();
    $('#durationInfo').hide();
    removeSelectedFile();
    updateFormProgress();
    $('#reasonCount').text('0/500').removeClass('text-warning text-danger').addClass('text-muted');
}

// Update form progress
function updateFormProgress() {
    const totalFields = $('input[required], select[required], textarea[required]').length;
    const filledFields = $('input[required], select[required], textarea[required]').filter(function() {
        return $(this).val().trim() !== '';
    }).length;

    const progress = totalFields > 0 ? (filledFields / totalFields) * 100 : 0;
    $('.progress-bar').css('width', progress + '%');
}

// Toggle sidebar for mobile
function toggleSidebar() {
    $('#sidebar').toggleClass('show');
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
function showAlert(message, type = 'info') {
    const alertId = 'alert_' + Date.now();
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" id="${alertId}" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    $('#alertContainer').prepend(alertHtml);

    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        $(`#${alertId}`).fadeOut(function() {
            $(this).remove();
        });
    }, 5000);
}

// Show validation error
function showValidationError(message) {
    showAlert(message, 'warning');
}

// Handle keyboard shortcuts
$(document).on('keydown', function(e) {
    // Ctrl/Cmd + Enter to submit
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        $('#submitBtn').click();
    }

    // Escape to close modals
    if (e.key === 'Escape') {
        $('.modal').modal('hide');
    }
});

// Performance monitoring
$(window).on('load', function() {
    console.log('Leave request page loaded successfully');
});

// Add visual feedback for form interactions
$(document).on('mouseenter', '.btn', function() {
    $(this).addClass('hover-lift');
});

$(document).on('mouseleave', '.btn', function() {
    $(this).removeClass('hover-lift');
});

// Add CSS for additional effects
$('<style>')
    .text(`
        .hover-lift {
            transform: translateY(-2px) !important;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15) !important;
        }
        .file-upload-area.dragover {
            border-color: #0066cc !important;
            background: rgba(0, 102, 204, 0.1) !important;
        }
        .history-item:hover {
            background: rgba(0, 102, 204, 0.05) !important;
        }
    `)
    .appendTo('head');