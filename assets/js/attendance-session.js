// Attendance Session JavaScript functionality

document.addEventListener('DOMContentLoaded', function() {
    initializeFormValidation();
    initializeDynamicFilters();
    initializeSessionActions();
});

// Form validation for session creation/editing
function initializeFormValidation() {
    const sessionForm = document.getElementById('sessionForm');
    if (!sessionForm) return;

    sessionForm.addEventListener('submit', function(e) {
        const courseId = document.getElementById('course_id').value;
        const optionId = document.getElementById('option_id').value;
        const sessionDate = document.getElementById('session_date').value;
        const startTime = document.getElementById('start_time').value;
        const biometricMethod = document.getElementById('biometric_method').value;

        // Validate required fields
        if (!courseId || !optionId || !sessionDate || !startTime || !biometricMethod) {
            e.preventDefault();
            showAlert('Please fill in all required fields.', 'danger');
            return false;
        }

        // Validate date is not in the past
        const selectedDate = new Date(sessionDate + 'T' + startTime);
        const now = new Date();

        if (selectedDate < now) {
            e.preventDefault();
            showAlert('Session date and time cannot be in the past.', 'warning');
            return false;
        }

        // Validate end time is after start time if provided
        const endTime = document.getElementById('end_time').value;
        if (endTime && startTime >= endTime) {
            e.preventDefault();
            showAlert('End time must be after start time.', 'warning');
            return false;
        }

        return true;
    });
}

// Dynamic filtering for course and program selection
function initializeDynamicFilters() {
    const courseSelect = document.getElementById('course_id');
    const optionSelect = document.getElementById('option_id');

    if (!courseSelect || !optionSelect) return;

    courseSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const departmentId = selectedOption.getAttribute('data-department');

        // Filter options by department
        filterOptionsByDepartment(departmentId);
    });

    // Initialize on page load
    const initialSelectedOption = courseSelect.options[courseSelect.selectedIndex];
    if (initialSelectedOption && initialSelectedOption.value) {
        const departmentId = initialSelectedOption.getAttribute('data-department');
        filterOptionsByDepartment(departmentId);
    }
}

function filterOptionsByDepartment(departmentId) {
    const optionSelect = document.getElementById('option_id');

    for (let option of optionSelect.options) {
        if (option.value === '') continue; // Skip "Select Program" option

        const optionDept = option.getAttribute('data-department');
        if (departmentId && optionDept !== departmentId) {
            option.style.display = 'none';
            if (option.selected) option.selected = false;
        } else {
            option.style.display = '';
        }
    }
}

// Session actions (end session, delete session)
function initializeSessionActions() {
    // End session confirmation
    const endSessionBtn = document.querySelector('button[name="end_session"]');
    if (endSessionBtn) {
        endSessionBtn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to end this session? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });
    }

    // Delete session confirmation
    const deleteSessionBtn = document.querySelector('button[name="delete_session"]');
    if (deleteSessionBtn) {
        deleteSessionBtn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this session? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });
    }
}

// Utility function to show alerts
function showAlert(message, type = 'info') {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());

    // Create new alert
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

    // Insert at the top of the main content
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        const firstChild = mainContent.firstElementChild;
        mainContent.insertBefore(alertDiv, firstChild);
    }

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Date range validation for filters
function validateDateRange() {
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');

    if (!dateFrom || !dateTo) return;

    dateTo.addEventListener('change', function() {
        if (dateFrom.value && this.value && dateFrom.value > this.value) {
            showAlert('End date cannot be before start date.', 'warning');
            this.value = '';
        }
    });

    dateFrom.addEventListener('change', function() {
        if (dateTo.value && this.value && this.value > dateTo.value) {
            showAlert('Start date cannot be after end date.', 'warning');
            this.value = '';
        }
    });
}

// Initialize date range validation
validateDateRange();

// Export functions for potential use in other scripts
window.AttendanceSession = {
    showAlert: showAlert,
    validateDateRange: validateDateRange,
    filterOptionsByDepartment: filterOptionsByDepartment
};