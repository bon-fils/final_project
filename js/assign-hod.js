// Global variables
let allDepartments = [];
let allLecturers = [];

// Mobile sidebar toggle function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.querySelector('.mobile-menu-toggle');

    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
    }
});

// DOM Ready
$(document).ready(function() {
    loadData();
    setupEventHandlers();
    setupFullscreenHandling();
});

function setupEventHandlers() {
    // Department form submission
    $('#assignHodForm').on('submit', handleDepartmentSubmit);

    // Department selection change
    $('#departmentSelect').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const hodId = selectedOption.data('hod');
        const hodName = selectedOption.data('hod-name');

        if (hodId && hodName) {
            $('#currentAssignmentInfo').show();
            $('#currentHodName').text(hodName);
            $('#lecturerSelect').val(hodId);
        } else {
            $('#currentAssignmentInfo').hide();
            $('#lecturerSelect').val('');
        }
        updateAssignmentPreview();
    });

    // Lecturer selection change
    $('#lecturerSelect').on('change', function() {
        console.log('Lecturer selection changed:', $(this).val());
        updateAssignmentPreview();
        validateForm();
    });

    // Search functionality with debouncing
    let departmentSearchTimeout, lecturerSearchTimeout;

    $('#departmentSearch').on('input', function() {
        clearTimeout(departmentSearchTimeout);
        departmentSearchTimeout = setTimeout(() => {
            filterDepartments($(this).val());
        }, 300);
    });

    $('#lecturerSearch').on('input', function() {
        clearTimeout(lecturerSearchTimeout);
        lecturerSearchTimeout = setTimeout(() => {
            filterLecturers($(this).val());
        }, 300);
    });

    // Reset form
    $('#resetFormBtn').on('click', function() {
        $('#assignHodForm')[0].reset();
        $('#currentAssignmentInfo').hide();
        $('#assignmentPreview').hide();
        $('#departmentSearch').val('');
        $('#lecturerSearch').val('');
        $('.department-card').removeClass('selected');
        filterDepartments('');
        filterLecturers('');
    });

    // Refresh assignments
    $('#refreshAssignments').on('click', loadAssignments);

    // Form validation
    $('#departmentSelect').on('change', validateForm);
    $('#lecturerSelect').on('change', validateForm);

    // Keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + R to refresh
        if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
            e.preventDefault();
            loadData();
        }

        // Ctrl/Cmd + S to submit form
        if ((e.ctrlKey || e.metaKey) && e.key === 's' && $('#departmentSelect').val()) {
            e.preventDefault();
            $('#assignHodForm').submit();
        }

        // Escape to reset form
        if (e.key === 'Escape') {
            $('#resetFormBtn').click();
        }
    });

    // Auto-focus search on card click
    $(document).on('click', '.department-card', function() {
        $('#departmentSearch').focus();
    });
}

function setupFullscreenHandling() {
    // Function to check if we're in fullscreen
    function isFullscreen() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement ||
                document.mozFullScreenElement || document.msFullscreenElement);
    }

    // Function to adjust layout for fullscreen
    function adjustForFullscreen() {
        const fullscreen = isFullscreen();
        const windowWidth = $(window).width();

        if (fullscreen) {
            // Fullscreen mode adjustments
            $('html, body').css({
                'overflow-x': 'hidden',
                'width': '100vw',
                'height': '100vh'
            });

            if (windowWidth > 768) {
                $('.sidebar').css({
                    'width': '280px',
                    'height': '100vh',
                    'position': 'fixed'
                });
                $('.main-content').css({
                    'margin-left': '280px',
                    'width': 'calc(100vw - 280px)',
                    'height': '100vh'
                });
            } else {
                $('.sidebar').css({
                    'width': '100%',
                    'height': '100vh',
                    'position': 'fixed'
                });
                $('.main-content').css({
                    'margin-left': sidebarCollapsed ? '0' : '0',
                    'width': '100vw',
                    'height': '100vh'
                });
            }
        } else {
            // Normal mode - use dvh for mobile browsers
            const heightUnit = windowWidth <= 768 ? '100dvh' : '100vh';

            $('.sidebar').css({
                'height': heightUnit
            });
            $('.main-content').css({
                'min-height': heightUnit
            });
        }
    }

    // Listen for fullscreen changes
    $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
        setTimeout(adjustForFullscreen, 100);
    });

    // Also listen for orientation changes which can affect fullscreen
    $(window).on('orientationchange', function() {
        setTimeout(adjustForFullscreen, 200);
    });

    // Initial adjustment
    setTimeout(adjustForFullscreen, 500);
}

function filterDepartments(searchTerm) {
    const select = $('#departmentSelect');
    const options = select.find('option');
    let visibleCount = 0;

    options.each(function() {
        const option = $(this);
        const text = option.text().toLowerCase();
        const shouldShow = !searchTerm || text.includes(searchTerm.toLowerCase());

        if (option.val() !== '') { // Don't hide the placeholder
            option.toggle(shouldShow);
            if (shouldShow) visibleCount++;
        }
    });

    // Update feedback message
    if (searchTerm) {
        if (visibleCount === 0) {
            $('#departmentSelectFeedback')
                .html('<i class="fas fa-exclamation-triangle me-1"></i>No departments found matching "<strong>' + searchTerm + '</strong>"')
                .removeClass('text-success').addClass('text-warning');
        } else {
            $('#departmentSelectFeedback')
                .html('<i class="fas fa-check me-1"></i>Found ' + visibleCount + ' department' + (visibleCount !== 1 ? 's' : '') + ' matching "<strong>' + searchTerm + '</strong>"')
                .removeClass('text-warning').addClass('text-success');
        }
    } else {
        $('#departmentSelectFeedback').text('').removeClass('text-success text-warning');
    }
}

function filterLecturers(searchTerm) {
    const select = $('#lecturerSelect');
    const options = select.find('option');
    let visibleCount = 0;

    options.each(function() {
        const option = $(this);
        const text = option.text().toLowerCase();
        const shouldShow = !searchTerm || text.includes(searchTerm.toLowerCase());

        if (option.val() !== '') { // Don't hide the placeholder
            option.toggle(shouldShow);
            if (shouldShow) visibleCount++;
        }
    });

    // Update feedback message
    if (searchTerm) {
        if (visibleCount === 0) {
            $('#lecturerSelectFeedback')
                .html('<i class="fas fa-exclamation-triangle me-1"></i>No lecturers found matching "<strong>' + searchTerm + '</strong>"')
                .removeClass('text-success').addClass('text-warning');
        } else {
            $('#lecturerSelectFeedback')
                .html('<i class="fas fa-check me-1"></i>Found ' + visibleCount + ' lecturer' + (visibleCount !== 1 ? 's' : '') + ' matching "<strong>' + searchTerm + '</strong>"')
                .removeClass('text-warning').addClass('text-success');
        }
    } else {
        $('#lecturerSelectFeedback').text('').removeClass('text-success text-warning');
    }
}

function updateAssignmentPreview() {
    const departmentId = $('#departmentSelect').val();
    const lecturerId = $('#lecturerSelect').val();

    console.log('updateAssignmentPreview called:', { departmentId, lecturerId });

    if (!departmentId) {
        $('#assignmentPreview').hide();
        return;
    }

    const departmentOption = $('#departmentSelect option:selected');
    const lecturerOption = $('#lecturerSelect option:selected');
    const isInvalidAssignment = departmentOption.data('invalid') === 'true';

    const departmentName = departmentOption.text();
    const lecturerName = lecturerOption.text() || 'No HOD';

    console.log('Preview data:', {
        departmentName,
        lecturerName,
        lecturerOptionText: lecturerOption.text(),
        lecturerOptionVal: lecturerOption.val()
    });

    let previewHtml = '<div class="row">';
    previewHtml += '<div class="col-md-6">';
    previewHtml += '<h6><i class="fas fa-building me-2"></i>Department</h6>';
    previewHtml += `<p class="mb-1"><strong>${departmentName}</strong></p>`;
    if (isInvalidAssignment) {
        previewHtml += '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Has invalid current assignment</small>';
    }
    previewHtml += '</div>';

    if (lecturerId) {
        previewHtml += '<div class="col-md-6">';
        previewHtml += '<h6><i class="fas fa-user-graduate me-2"></i>New HOD</h6>';
        previewHtml += `<p class="mb-1"><strong>${lecturerName}</strong></p>`;
        previewHtml += '<small class="text-success"><i class="fas fa-check me-1"></i>Will create/update user account</small>';
        previewHtml += '</div>';
    } else {
        previewHtml += '<div class="col-md-6">';
        previewHtml += '<h6><i class="fas fa-user-times me-2"></i>HOD Assignment</h6>';
        previewHtml += '<p class="mb-1"><span class="text-warning">Will be removed</span></p>';
        previewHtml += '<small class="text-muted"><i class="fas fa-info-circle me-1"></i>Department will have no HOD</small>';
        previewHtml += '</div>';
    }

    previewHtml += '</div>';

    // Add action summary
    previewHtml += '<hr class="my-2">';
    previewHtml += '<div class="text-center">';
    if (isInvalidAssignment && lecturerId) {
        previewHtml += `<p class="mb-0"><i class="fas fa-tools me-2"></i><strong>Action:</strong> Fix invalid assignment</p>`;
        previewHtml += '<small class="text-success">This will resolve the data integrity issue</small>';
    } else if (isInvalidAssignment && !lecturerId) {
        previewHtml += `<p class="mb-0"><i class="fas fa-times me-2"></i><strong>Action:</strong> Remove invalid assignment</p>`;
        previewHtml += '<small class="text-warning">This will clear the invalid HOD reference</small>';
    } else if (lecturerId) {
        previewHtml += `<p class="mb-0"><i class="fas fa-arrow-right me-2"></i><strong>Action:</strong> Assign HOD to department</p>`;
    } else {
        previewHtml += `<p class="mb-0"><i class="fas fa-times me-2"></i><strong>Action:</strong> Remove HOD from department</p>`;
    }
    previewHtml += '</div>';

    $('#previewText').html(previewHtml);
    $('#assignmentPreview').show();
}

function showAssignmentPreview() {
    console.log('showAssignmentPreview called');
    updateAssignmentPreview();
}

function clearCurrentAssignment() {
    $('#currentAssignmentInfo').hide();
    $('#lecturerSelect').val('');
    updateAssignmentPreview();
}

function validateForm() {
    const departmentId = $('#departmentSelect').val();
    const lecturerId = $('#lecturerSelect').val();
    let isValid = true;

    // Validate department selection
    if (departmentId) {
        $('#departmentSelect').removeClass('is-invalid').addClass('is-valid');
        $('#departmentSelectFeedback')
            .html('<i class="fas fa-check me-1"></i>Department selected successfully')
            .removeClass('text-warning text-danger').addClass('text-success');
    } else {
        $('#departmentSelect').removeClass('is-valid').addClass('is-invalid');
        $('#departmentSelectFeedback')
            .html('<i class="fas fa-exclamation-triangle me-1"></i>Please select a department')
            .removeClass('text-success text-warning').addClass('text-danger');
        isValid = false;
    }

    // Validate lecturer selection (optional)
    if (lecturerId) {
        $('#lecturerSelect').removeClass('is-invalid').addClass('is-valid');
        $('#lecturerSelectFeedback')
            .html('<i class="fas fa-check me-1"></i>HOD selected - user account will be created/updated')
            .removeClass('text-warning text-danger').addClass('text-success');
    } else {
        $('#lecturerSelect').removeClass('is-valid is-invalid');
        $('#lecturerSelectFeedback')
            .html('<i class="fas fa-info-circle me-1"></i>Optional - leave empty to remove HOD assignment')
            .removeClass('text-success text-danger').addClass('text-info');
    }

    // Update submit button state
    const submitBtn = $('#assignBtn');
    if (isValid) {
        submitBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
    } else {
        submitBtn.prop('disabled', true).removeClass('btn-primary').addClass('btn-secondary');
    }

    return isValid;
}

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('#alertContainer').html(alertHtml);
    setTimeout(() => $('.alert').alert('close'), 5000);
}

function showLoading() {
    $("#loadingOverlay").fadeIn();
}

function hideLoading() {
    $("#loadingOverlay").fadeOut();
}

function loadData() {
    showLoading();

    // Load all data in parallel
    Promise.all([
        loadDepartments(),
        loadLecturers(),
        loadStatistics(),
        loadAssignments()
    ])
    .then(() => {
        hideLoading();
        checkDataIntegrity();
        console.log('All data loaded successfully');
    })
    .catch(error => {
        hideLoading();
        console.error('Error loading data:', error);
        showAlert('danger', 'Failed to load data: ' + error);
    });
}

// Debug function to test API endpoints
function testAPIs() {
    console.log('Testing API endpoints...');

    $.get('api/assign-hod-api.php?action=get_departments')
        .done(function(response) {
            console.log('Departments API test:', response);
            if (response.status === 'success') {
                console.log('✅ Departments loaded:', response.count, 'departments');
            } else {
                console.error('❌ Departments API error:', response.message);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('❌ Departments API failed:', xhr, status, error);
        });

    $.get('api/assign-hod-api.php?action=get_lecturers')
        .done(function(response) {
            console.log('Lecturers API test:', response);
            if (response.status === 'success') {
                console.log('✅ Lecturers loaded:', response.count, 'lecturers');
            } else {
                console.error('❌ Lecturers API error:', response.message);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('❌ Lecturers API failed:', xhr, status, error);
        });
}

function checkDataIntegrity() {
    const invalidAssignments = allDepartments.filter(dept =>
        dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
    );

    if (invalidAssignments.length > 0) {
        const departmentNames = invalidAssignments.map(dept => dept.name || dept.dept_name).join(', ');
        const alertHtml = `
            <div class="alert data-integrity-alert alert-dismissible fade show">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Data Integrity Issue:</strong> Found ${invalidAssignments.length} department(s) with invalid HOD assignments.
                <br><small class="text-muted">Affected departments: ${departmentNames}</small>
                <br><small class="text-muted">These departments have hod_id values pointing to non-HOD users or missing records.</small>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#alertContainer').append(alertHtml);

        // Show fix button
        $('#fixInvalidBtn').show();

        // Log details for debugging
        console.warn('Invalid HOD assignments found:', invalidAssignments);
        console.log('These departments have hod_id set but no valid HOD user/lecturer records');
    } else {
        // Hide fix button if no invalid assignments
        $('#fixInvalidBtn').hide();
    }
}

function loadDepartments() {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'api/assign-hod-api.php?action=get_departments',
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .done(function(response) {
                // Handle session expiration
                if (response.error_code === 'SESSION_EXPIRED') {
                    showAlert('warning', 'Your session has expired. Redirecting to login...');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                    reject('Session expired');
                    return;
                }

                if (response.status === 'success') {
                    allDepartments = response.data;
                    const select = $('#departmentSelect');
                    select.empty().append('<option value="">-- Select Department --</option>');
                    if (response.data && response.data.length > 0) {
                        response.data.forEach(dept => {
                            const hodName = dept.hod_name || 'Not Assigned';
                            const selected = hodName !== 'Not Assigned' ? ' (Current HOD: ' + hodName + ')' : '';
                            select.append(`<option value="${dept.id}" data-hod="${dept.hod_id || ''}" data-hod-name="${hodName}" >${dept.name}${selected}</option>`);
                        });
                    } else {
                        select.append('<option value="" disabled>No departments available</option>');
                    }
                    resolve(response.data);
                } else {
                    console.error('Departments API error:', response);
                    reject(response.message || 'Failed to load departments');
                }
            })
            .fail(function(xhr, status, error) {
                // Handle HTTP errors
                if (xhr.status === 401) {
                    showAlert('warning', 'Session expired. Please login again.');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                    return;
                }
                console.error('Departments API failed:', xhr, status, error);
                reject('Failed to load departments: ' + error);
            });
    });
}


function loadLecturers() {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'api/assign-hod-api.php?action=get_lecturers',
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .done(function(response) {
                // Handle session expiration
                if (response.error_code === 'SESSION_EXPIRED') {
                    showAlert('warning', 'Your session has expired. Redirecting to login...');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                    reject('Session expired');
                    return;
                }

                if (response.status === 'success') {
                    allLecturers = response.data;
                    console.log('Lecturers loaded:', response.data);
                    const select = $('#lecturerSelect');
                    select.empty().append('<option value="">-- Select Lecturer --</option>');
                    if (response.data && response.data.length > 0) {
                        response.data.forEach(lecturer => {
                            const displayName = lecturer.display_name || lecturer.full_name || `${lecturer.first_name} ${lecturer.last_name}`;
                            console.log('Adding lecturer option:', lecturer.id, displayName);
                            select.append(`<option value="${lecturer.id}">${displayName}</option>`);
                        });
                        console.log('Lecturer select populated with', response.data.length, 'options');
                    } else {
                        select.append('<option value="" disabled>No lecturers available</option>');
                        console.log('No lecturers available');
                    }
                    resolve(response.data);
                } else {
                    console.error('Lecturers API error:', response);
                    reject(response.message || 'Failed to load lecturers');
                }
            })
            .fail(function(xhr, status, error) {
                // Handle HTTP errors
                if (xhr.status === 401) {
                    showAlert('warning', 'Session expired. Please login again.');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                    return;
                }
                console.error('Lecturers API failed:', xhr, status, error);
                reject('Failed to load lecturers: ' + error);
            });
    });
}


function loadStatistics() {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'api/assign-hod-api.php?action=get_assignment_stats',
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .done(function(response) {
                if (response.status === 'success') {
                    const data = response.data;

                    // Animate numbers with improved animation
                    animateValue('totalDepartments', data.total_departments || 0);
                    animateValue('assignedDepartments', data.assigned_departments || 0);
                    animateValue('totalLecturers', data.total_lecturers || 0);
                    animateValue('unassignedDepartments', data.unassigned_departments || 0);

                    resolve(response.data);
                } else {
                    reject(response.message);
                }
            })
            .fail(function(xhr, status, error) {
                reject('Failed to load statistics: ' + error);
            });
    });
}

function animateValue(id, newValue, duration = 600) {
    const el = document.getElementById(id);
    if (!el) return;

    const current = parseInt(el.innerText) || 0;
    const startTime = Date.now();

    function update() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const value = Math.floor(current + (newValue - current) * easeOut);

        el.innerText = value;

        if (progress < 1) {
            requestAnimationFrame(update);
        } else {
            el.innerText = newValue;
        }
    }

    requestAnimationFrame(update);
}

function animateNumber(selector, targetNumber, duration = 1000) {
    const element = $(selector);
    const startNumber = parseInt(element.text()) || 0;
    const startTime = Date.now();

    function updateNumber() {
        const currentTime = Date.now();
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Easing function for smooth animation
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const currentNumber = Math.round(startNumber + (targetNumber - startNumber) * easeOut);

        element.text(currentNumber);

        if (progress < 1) {
            requestAnimationFrame(updateNumber);
        }
    }

    updateNumber();
}

function loadAssignments() {
    return new Promise((resolve, reject) => {
        $.ajax({
            url: 'api/assign-hod-api.php?action=get_departments',
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .done(function(response) {
                // Handle session expiration
                if (response.error_code === 'SESSION_EXPIRED') {
                    showAlert('warning', 'Your session has expired. Redirecting to login...');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                    reject('Session expired');
                    return;
                }

                if (response.status === 'success') {
                    renderAssignments(response.data);
                    resolve(response.data);
                } else {
                    reject(response.message);
                }
            })
            .fail(function(xhr, status, error) {
                reject('Failed to load assignments: ' + error);
            });
    });
}

function renderAssignments(departments) {
    const container = $('#assignmentsContainer');
    container.empty();

    if (departments.length === 0) {
        container.html(`
            <div class="col-12 text-center py-5">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Departments Found</h5>
                <p class="text-muted">No departments are available for HOD assignment.</p>
                <button class="btn btn-primary" onclick="loadData()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh Data
                </button>
            </div>
        `);
        return;
    }

    departments.forEach(dept => {
        // Check for invalid assignments (hod_id exists but no valid HOD name)
        const hasInvalidAssignment = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');
        const cardClass = hasInvalidAssignment ? 'assignment-card invalid' : (dept.hod_id ? 'assignment-card assigned' : 'assignment-card unassigned');
        const statusIcon = hasInvalidAssignment ? 'fas fa-exclamation-circle text-danger' : (dept.hod_id ? 'fas fa-user-check text-success' : 'fas fa-exclamation-triangle text-warning');
        const statusText = hasInvalidAssignment ? 'Invalid' : (dept.hod_id ? 'Assigned' : 'Unassigned');
        const statusColor = hasInvalidAssignment ? 'danger' : (dept.hod_id ? 'success' : 'warning');

        const cardHtml = `
            <div class="col-md-6 col-lg-4">
                <div class="card ${cardClass} h-100 department-card" data-department-id="${dept.id}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="card-title text-primary mb-0">${dept.dept_name}</h6>
                            <span class="badge bg-${statusColor}">${statusText}</span>
                        </div>

                        <div class="mb-3">
                            <div class="row">
                                <div class="col-8">
                                    <small class="text-muted d-block">Current HOD:</small>
                                    <div class="${dept.hod_id ? (hasInvalidAssignment ? 'text-danger' : 'text-success') : 'text-warning'} fw-semibold">
                                        ${hasInvalidAssignment ? 'INVALID ASSIGNMENT' : (dept.hod_name || 'Not Assigned')}
                                    </div>
                                    ${hasInvalidAssignment ? '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>HOD ID exists but lecturer not found</small>' : ''}
                                </div>
                                <div class="col-4 text-end">
                                    <small class="text-muted d-block">Programs:</small>
                                    <span class="badge bg-info">${dept.program_count || 0}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="progress" style="height: 4px;">
                                <div class="progress-bar bg-${statusColor}" role="progressbar" style="width: ${hasInvalidAssignment ? '50%' : (dept.hod_id ? '100%' : '0%')}"></div>
                            </div>
                        </div>

                        <button class="btn btn-sm ${hasInvalidAssignment ? 'btn-outline-danger' : 'btn-outline-primary'} w-100 selectDepartment"
                                data-id="${dept.id}"
                                data-name="${dept.dept_name}"
                                data-hod="${dept.hod_id || ''}"
                                data-hod-name="${dept.hod_name || ''}"
                                data-invalid="${hasInvalidAssignment}">
                            <i class="fas fa-${hasInvalidAssignment ? 'exclamation-triangle' : 'mouse-pointer'} me-1"></i>
                            ${hasInvalidAssignment ? 'Fix Assignment' : 'Select for Assignment'}
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.append(cardHtml);
    });

    // Add event listeners to select buttons
    $('.selectDepartment').on('click', function() {
        const deptId = $(this).data('id');
        const deptName = $(this).data('name');
        const hodId = $(this).data('hod');
        const hodName = $(this).data('hod-name');
        const isInvalid = $(this).data('invalid');

        // Remove previous selection
        $('.department-card').removeClass('selected');
        // Add selection to clicked card
        $(this).closest('.department-card').addClass('selected');

        $('#departmentSelect').val(deptId);
        $('#lecturerSelect').val(hodId);

        if (hodId && hodName && !isInvalid) {
            $('#currentAssignmentInfo').show();
            $('#currentHodName').text(hodName);
        } else if (isInvalid) {
            $('#currentAssignmentInfo').hide();
            showAlert('warning', `Department <strong>${deptName}</strong> has an invalid HOD assignment that needs to be fixed.`);
        } else {
            $('#currentAssignmentInfo').hide();
        }

        // Scroll to form with animation
        $('html, body').animate({
            scrollTop: $('#assignHodForm').offset().top - 100
        }, 500);

        const alertType = isInvalid ? 'warning' : 'info';
        const alertMessage = isInvalid
            ? `Selected department with invalid assignment: <strong>${deptName}</strong>`
            : `Selected department: <strong>${deptName}</strong>`;

        showAlert(alertType, alertMessage);

        // Update form validation
        validateForm();
    });

    // Add hover effects
    $('.department-card').hover(
        function() {
            if (!$(this).hasClass('selected')) {
                $(this).addClass('shadow');
            }
        },
        function() {
            if (!$(this).hasClass('selected')) {
                $(this).removeClass('shadow');
            }
        }
    );
}

function handleDepartmentSubmit(e) {
    e.preventDefault();

    const departmentId = $('#departmentSelect').val();
    const hodId = $('#lecturerSelect').val();
    const departmentOption = $('#departmentSelect option:selected');
    const isInvalidAssignment = departmentOption.data('invalid') === 'true';

    if (!departmentId) {
        showAlert('warning', 'Please select a department');
        $('#departmentSelect').focus();
        return;
    }

    // Check if this is fixing an invalid assignment
    if (isInvalidAssignment && !hodId) {
        if (!confirm('This department has an invalid HOD assignment. Removing the HOD will clear this invalid reference. Continue?')) {
            return;
        }
    }

    showLoading();
    const submitBtn = $(this).find('button[type="submit"]');
    const originalText = submitBtn.html();
    submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...').prop('disabled', true);

    $.post('api/assign-hod-api.php?action=assign_hod', {
        department_id: departmentId,
        hod_id: hodId,
        csrf_token: window.csrfToken
    })
    .done(function(response) {
        if (response.status === 'success') {
            const message = isInvalidAssignment && hodId
                ? 'Invalid HOD assignment fixed successfully!'
                : response.message;
            showAlert('success', message);
            loadData(); // Refresh all data
            $('#assignHodForm')[0].reset();
            $('#currentAssignmentInfo').hide();
        } else if (response.status === 'warning' && response.requires_confirmation) {
            if (confirm(response.message + ' Continue anyway?')) {
                // Retry with confirmation
                $.post('api/assign-hod-api.php?action=assign_hod', {
                    department_id: departmentId,
                    hod_id: hodId,
                    csrf_token: window.csrfToken,
                    confirmed: true
                })
                .done(function(retryResponse) {
                    if (retryResponse.status === 'success') {
                        showAlert('success', 'HOD reassigned successfully!');
                        loadData();
                        $('#assignHodForm')[0].reset();
                        $('#currentAssignmentInfo').hide();
                    } else {
                        showAlert('danger', retryResponse.message);
                    }
                })
                .fail(function() {
                    showAlert('danger', 'Failed to reassign HOD. Please try again.');
                });
            }
        } else {
            showAlert('danger', response.message);
        }
    })
    .fail(function(xhr, status, error) {
        let errorMessage = 'Failed to process HOD assignment. Please try again.';

        if (xhr.status === 400) {
            try {
                const errorResponse = JSON.parse(xhr.responseText);
                errorMessage = errorResponse.message || errorMessage;
            } catch (e) {
                // Use default error message
            }
        }

        showAlert('danger', errorMessage);
    })
    .always(function() {
        hideLoading();
        submitBtn.html(originalText).prop('disabled', false);
    });
}

// Make loadData function available globally
window.loadData = loadData;

// Help functionality
function showHelp() {
    const helpHtml = `
        <div class="modal fade" id="helpModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-question-circle me-2"></i>Help & Shortcuts
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts</h6>
                                <ul class="list-unstyled">
                                    <li><kbd>Ctrl+R</kbd> <span class="text-muted">Refresh all data</span></li>
                                    <li><kbd>Ctrl+S</kbd> <span class="text-muted">Submit assignment form</span></li>
                                    <li><kbd>Esc</kbd> <span class="text-muted">Reset form</span></li>
                                </ul>

                                <h6><i class="fas fa-search me-2"></i>Search Features</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check text-success me-2"></i>Real-time filtering</li>
                                    <li><i class="fas fa-eye me-2"></i>Live assignment preview</li>
                                    <li><i class="fas fa-chart-bar me-2"></i>Statistics dashboard</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-info-circle me-2"></i>How to Use</h6>
                                <ol class="small">
                                    <li>Search or select a department from the cards below</li>
                                    <li>Choose a lecturer to assign as HOD (optional)</li>
                                    <li>Review the assignment preview</li>
                                    <li>Click "Assign HOD" to save changes</li>
                                </ol>

                                <h6><i class="fas fa-cogs me-2"></i>Features</h6>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-user-plus text-success me-2"></i>Auto-creates user accounts</li>
                                    <li><i class="fas fa-shield-alt text-info me-2"></i>CSRF protection</li>
                                    <li><i class="fas fa-sync-alt text-warning me-2"></i>Real-time updates</li>
                                    <li><i class="fas fa-mobile-alt text-primary me-2"></i>Mobile responsive</li>
                                </ul>

                                <h6><i class="fas fa-exclamation-triangle me-2"></i>Data Integrity</h6>
                                <ul class="list-unstyled small">
                                    <li><i class="fas fa-times text-danger me-2"></i>Detects invalid assignments</li>
                                    <li><i class="fas fa-tools text-warning me-2"></i>Provides fix options</li>
                                    <li><i class="fas fa-chart-line text-info me-2"></i>Shows data health status</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-outline-light" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove existing modal if present
    $('#helpModal').remove();
    $('body').append(helpHtml);

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('helpModal'));
    modal.show();
}

// Function to fix invalid assignments
function fixInvalidAssignments() {
    const invalidDepts = allDepartments.filter(dept =>
        dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
    );

    if (invalidDepts.length === 0) {
        showAlert('info', 'No invalid assignments found.');
        return;
    }

    console.log('Invalid assignments to fix:', invalidDepts);

    if (confirm(`Found ${invalidDepts.length} department(s) with invalid HOD assignments.\n\nThese departments have hod_id values pointing to users who are not HODs or missing lecturer records.\n\nFix them by clearing the invalid HOD assignments?`)) {
        showLoading();

        // Fix each invalid assignment one by one to handle errors gracefully
        let fixedCount = 0;
        let errorCount = 0;

        const fixNext = (index) => {
            if (index >= invalidDepts.length) {
                // All done
                hideLoading();
                if (errorCount === 0) {
                    showAlert('success', `Successfully fixed ${fixedCount} invalid HOD assignment(s)!`);
                } else {
                    showAlert('warning', `Fixed ${fixedCount} assignment(s), but ${errorCount} failed. Please check the console for details.`);
                }
                loadData(); // Refresh all data
                return;
            }

            const dept = invalidDepts[index];
            console.log(`Fixing department: ${dept.name} (ID: ${dept.id})`);

            $.post('api/assign-hod-api.php?action=assign_hod', {
                department_id: dept.id,
                hod_id: null,
                csrf_token: window.csrfToken
            })
            .done(function(response) {
                if (response.status === 'success') {
                    console.log(`✅ Fixed: ${dept.name}`);
                    fixedCount++;
                } else {
                    console.error(`❌ Failed to fix ${dept.name}:`, response.message);
                    errorCount++;
                }
            })
            .fail(function(xhr, status, error) {
                console.error(`❌ Request failed for ${dept.name}:`, xhr, status, error);
                errorCount++;
            })
            .always(function() {
                fixNext(index + 1);
            });
        };

        fixNext(0);
    }
}