/**
 * User Management JavaScript
 * Client-side functionality for user management interface
 *
 * @version 1.0.0
 * @author Rwanda Polytechnic Development Team
 * @since 2024
 */

let currentUsers = [];
let filteredUsers = [];

// Mobile sidebar toggle
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('show');
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

// Cross-page update listener
window.addEventListener('storage', function(e) {
    if (e.key === 'user_role_changed' || e.key === 'department_hod_changed' || e.key === 'department_changed') {
        console.log('External change detected, refreshing user data...');
        loadUsers();
    }
});

function showAlert(type, message, duration = 5000) {
    const iconMap = {
        'success': 'check-circle',
        'error': 'exclamation-triangle',
        'warning': 'exclamation-circle',
        'info': 'info-circle',
        'danger': 'times-circle'
    };

    const icon = iconMap[type] || 'info-circle';

    $("#alertBox").html(`
        <div class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-${icon} me-2 flex-shrink-0"></i>
                <div class="flex-grow-1">${message}</div>
                <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    `);

    // Auto-dismiss with animation
    if (duration > 0) {
        setTimeout(() => {
            const alert = $("#alertBox .alert");
            alert.fadeOut(300, function() {
                $(this).remove();
            });
        }, duration);
    }

    // Scroll to top if error
    if (type === 'error' || type === 'danger') {
        $('html, body').animate({ scrollTop: 0 }, 500);
    }
}

function showLoading() {
    $("#loadingOverlay").fadeIn();
}

function hideLoading() {
    $("#loadingOverlay").fadeOut();
}

function loadUsers() {
    showLoading();

    const search = $("#searchInput").val();
    const role = $("#roleFilter").val();
    const status = $("#statusFilter").val();
    const department = $("#departmentFilter").val();
    const yearLevel = $("#yearLevelFilter").val();
    const gender = $("#genderFilter").val();
    const age = $("#ageFilter").val();
    const regNo = $("#regNoFilter").val();
    const email = $("#emailFilter").val();

    $.ajax({
        url: 'manage-users.php',
        method: 'GET',
        data: {
            ajax: '1',
            action: 'get_users',
            search: search,
            role: role,
            status: status,
            department: department,
            year_level: yearLevel,
            gender: gender,
            age: age,
            reg_no: regNo,
            email: email,
            t: Date.now()
        },
        success: function(response) {
            hideLoading();

            if (response.status === 'success') {
                currentUsers = response.data;
                filteredUsers = [...currentUsers];
                updateStats(response.stats);
                renderUsersTable();
            } else {
                showAlert('danger', 'Failed to load users: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Error loading users:', error);
            showAlert('danger', 'Failed to load users. Please try again.');
        }
    });
}

function loadDepartmentsForFilter() {
    $.ajax({
        url: 'manage-users.php',
        method: 'GET',
        data: { ajax: '1', action: 'get_departments' },
        success: function(response) {
            if (response.status === 'success') {
                const deptSelect = $('#departmentFilter');
                deptSelect.empty().append('<option value="">All Departments</option>');

                response.data.forEach(function(dept) {
                    deptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load departments for filter:', error);
            showAlert('warning', 'Failed to load departments. Some filtering may not work properly.');
        }
    });
}

function loadDepartmentsForForms() {
    $.ajax({
        url: 'manage-users.php',
        method: 'GET',
        data: { ajax: '1', action: 'get_departments' },
        success: function(response) {
            if (response.status === 'success') {
                const createDeptSelect = $('#createUserForm [name="department_id"]');
                const editDeptSelect = $('#editUserForm [name="department_id"]');
                const lecturerDeptSelect = $('#lecturerRegistrationForm [name="department_id"]');

                // Load departments for create user form
                if (createDeptSelect.length) {
                    createDeptSelect.empty().append('<option value="">Select Department</option>');
                    response.data.forEach(function(dept) {
                        createDeptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
                    });
                }

                // Load departments for edit user form
                if (editDeptSelect.length) {
                    editDeptSelect.empty().append('<option value="">Select Department</option>');
                    response.data.forEach(function(dept) {
                        editDeptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
                    });
                }

                // Load departments for lecturer registration form
                if (lecturerDeptSelect.length) {
                    lecturerDeptSelect.empty().append('<option value="">Select Department</option>');
                    response.data.forEach(function(dept) {
                        lecturerDeptSelect.append(`<option value="${dept.id}">${dept.name}</option>`);
                    });
                }

                // Load options for student registration form
                const studentOptionSelect = $('#lecturerRegistrationForm [name="option_id"]');
                if (studentOptionSelect.length) {
                    // Load options from API
                    $.ajax({
                        url: 'manage-users.php',
                        method: 'GET',
                        data: { ajax: '1', action: 'get_options' },
                        success: function(optionsResponse) {
                            if (optionsResponse.status === 'success') {
                                studentOptionSelect.empty().append('<option value="">Select Program</option>');
                                optionsResponse.data.forEach(function(option) {
                                    studentOptionSelect.append(`<option value="${option.id}">${option.name}</option>`);
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Failed to load options for students:', error);
                        }
                    });
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load departments for forms:', error);
        }
    });
}

function toggleLecturerFields(formId) {
    const role = $(`#${formId} [name="role"]`).val();
    const lecturerFields = $(`#${formId} .lecturer-field`);
    const studentFields = $(`#${formId} .student-field`);

    // Hide all role-specific fields first
    lecturerFields.hide().find('input, select').prop('required', false);
    studentFields.hide().find('input, select').prop('required', false);

    if (role === 'lecturer' || role === 'hod') {
        lecturerFields.show().find('input, select').prop('required', true);
    } else if (role === 'student') {
        studentFields.show().find('input, select').prop('required', true);
    }
}

function exportUsers() {
    const exportBtn = $("#exportUsers");
    const originalText = exportBtn.html();

    // Show loading state
    exportBtn.html('<i class="fas fa-spinner fa-spin me-1"></i>Exporting...').prop('disabled', true);

    try {
        // Get current filter values
        const search = $("#searchInput").val();
        const role = $("#roleFilter").val();
        const status = $("#statusFilter").val();
        const department = $("#departmentFilter").val();
        const yearLevel = $("#yearLevelFilter").val();
        const gender = $("#genderFilter").val();
        const age = $("#ageFilter").val();
        const regNo = $("#regNoFilter").val();
        const email = $("#emailFilter").val();

        // Build export URL
        let exportUrl = 'manage-users.php?ajax=1&action=export_users';
        const params = new URLSearchParams({
            search: search,
            role: role,
            status: status,
            department: department,
            year_level: yearLevel,
            gender: gender,
            age: age,
            reg_no: regNo,
            email: email
        });

        exportUrl += '&' + params.toString();

        // Trigger download
        window.location.href = exportUrl;

        // Show success message after a short delay
        setTimeout(() => {
            showAlert('success', 'User data export initiated. Download should start shortly.');
        }, 1000);

    } catch (error) {
        console.error('Export error:', error);
        showAlert('danger', 'Failed to export user data. Please try again.');
    } finally {
        // Restore button state
        setTimeout(() => {
            exportBtn.html(originalText).prop('disabled', false);
        }, 2000);
    }
}

function updateStats(stats) {
    $("#totalUsers").text(stats.total);
    $("#activeUsers").text(stats.active);
    $("#inactiveUsers").text(stats.inactive);
    $("#activePercentage").text(stats.total > 0 ? Math.round((stats.active / stats.total) * 100) + '%' : '0%');
}

function renderUsersTable() {
    const tbody = $("#usersTableBody");
    const info = $("#usersInfo");
    const searchTerm = $("#searchInput").val().toLowerCase();

    if (filteredUsers.length === 0) {
        tbody.html(`
            <tr>
                <td colspan="9" class="text-center py-4">
                    <div class="empty-state">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted mb-2">No Users Found</h5>
                        <p class="text-muted mb-0">Try adjusting your search criteria or filters</p>
                        <button class="btn btn-outline-primary btn-sm mt-3" onclick="clearAllFilters()">
                            <i class="fas fa-times me-1"></i>Clear All Filters
                        </button>
                    </div>
                </td>
            </tr>
        `);
        info.text("No users found");
        return;
    }

    // Function to highlight search terms
    function highlightText(text, searchTerm) {
        if (!searchTerm || !text) return text;
        const regex = new RegExp(`(${searchTerm})`, 'gi');
        return text.replace(regex, '<mark class="bg-warning">$1</mark>');
    }

    tbody.html(filteredUsers.map(user => `
        <tr data-user-id="${user.id}">
            <td>
                <div class="form-check">
                    <input class="form-check-input user-checkbox" type="checkbox" value="${user.id}" id="user_${user.id}">
                    <label class="form-check-label visually-hidden" for="user_${user.id}">Select user ${user.first_name} ${user.last_name}</label>
                </div>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="user-avatar me-3">
                        ${user.first_name ? user.first_name.charAt(0).toUpperCase() : 'U'}
                    </div>
                    <div>
                        <h6 class="mb-0">${highlightText(escapeHtml(user.first_name || ''), searchTerm)} ${highlightText(escapeHtml(user.last_name || ''), searchTerm)}</h6>
                        <small class="text-muted">@${highlightText(escapeHtml(user.username), searchTerm)}</small>
                    </div>
                </div>
            </td>
            <td>
                <span class="role-badge role-${user.role}">${user.role}</span>
            </td>
            <td>
                <div>
                    <small class="fw-semibold">${escapeHtml(user.department_name || 'Not Assigned')}</small>
                </div>
            </td>
            <td>
                <div>
                    ${user.year_level ? `<div><small class="text-muted">Year ${user.year_level}</small></div>` : ''}
                    ${user.gender ? `<div><small class="text-muted">${user.gender}</small></div>` : ''}
                    ${user.dob ? `<div><small class="text-muted">${getAge(user.dob)} years old</small></div>` : ''}
                </div>
            </td>
            <td>
                <div>
                    <i class="fas fa-envelope text-muted me-1"></i>${escapeHtml(user.email)}
                    ${user.phone ? `<br><i class="fas fa-phone text-muted me-1"></i>${escapeHtml(user.phone)}` : ''}
                </div>
            </td>
            <td>
                <span class="status-indicator status-${user.status || 'active'}"></span>
                <span class="badge bg-${getStatusBadgeColor(user.status)}">
                    ${user.status || 'active'}
                </span>
            </td>
            <td>
                <small>${formatDate(user.created_at)}</small>
            </td>
            <td>
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(${user.id})" title="Edit User">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(${user.id})" title="Reset Password">
                        <i class="fas fa-key"></i>
                    </button>
                    <button class="btn btn-sm btn-${getStatusButtonClass(user.status)}"
                            onclick="toggleUserStatus(${user.id}, '${user.status || 'active'}')"
                            title="${getStatusButtonTitle(user.status)}">
                        <i class="fas fa-${getStatusButtonIcon(user.status)}"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join(''));

    info.text(`Showing ${filteredUsers.length} user${filteredUsers.length !== 1 ? 's' : ''}`);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function getStatusBadgeColor(status) {
    switch (status) {
        case 'active': return 'success';
        case 'inactive': return 'warning';
        case 'suspended': return 'danger';
        default: return 'secondary';
    }
}

function getStatusButtonClass(status) {
    switch (status) {
        case 'active': return 'outline-warning';
        case 'inactive': return 'outline-success';
        case 'suspended': return 'outline-primary';
        default: return 'outline-secondary';
    }
}

function getStatusButtonTitle(status) {
    switch (status) {
        case 'active': return 'Deactivate User';
        case 'inactive': return 'Activate User';
        case 'suspended': return 'Activate User';
        default: return 'Change Status';
    }
}

function getStatusButtonIcon(status) {
    switch (status) {
        case 'active': return 'times';
        case 'inactive': return 'check';
        case 'suspended': return 'check';
        default: return 'cog';
    }
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return 'Invalid Date';
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    } catch (e) {
        return 'Invalid Date';
    }
}

function getAge(dateString) {
    if (!dateString) return 'N/A';
    try {
        const birthDate = new Date(dateString);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();

        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }

        return age;
    } catch (e) {
        return 'N/A';
    }
}

function applyFilters() {
    const search = $("#searchInput").val().toLowerCase();
    const role = $("#roleFilter").val();
    const status = $("#statusFilter").val();
    const department = $("#departmentFilter").val();
    const yearLevel = $("#yearLevelFilter").val();
    const gender = $("#genderFilter").val();
    const age = $("#ageFilter").val();
    const regNo = $("#regNoFilter").val().toLowerCase();
    const email = $("#emailFilter").val().toLowerCase();

    filteredUsers = currentUsers.filter(user => {
        const fullName = `${user.first_name || ''} ${user.last_name || ''}`.toLowerCase();
        const matchesSearch = !search ||
            (user.username && user.username.toLowerCase().includes(search)) ||
            (user.email && user.email.toLowerCase().includes(search)) ||
            fullName.includes(search);

        const matchesRole = !role || user.role === role;
        const matchesStatus = !status || user.status === status;
        const matchesDepartment = !department || (user.department_name && user.department_name.toLowerCase().includes(department.toLowerCase()));
        const matchesYearLevel = !yearLevel || (user.year_level && user.year_level.toString() === yearLevel);
        const matchesGender = !gender || (user.gender && user.gender === gender);
        const matchesRegNo = !regNo || (user.reference_id && user.reference_id.toLowerCase().includes(regNo));
        const matchesEmail = !email || (user.email && user.email.toLowerCase().includes(email));

        // Age filtering logic
        let matchesAge = true;
        if (age && user.dob) {
            const birthDate = new Date(user.dob);
            const today = new Date();
            const userAge = today.getFullYear() - birthDate.getFullYear();

            switch(age) {
                case '16-18':
                    matchesAge = userAge >= 16 && userAge <= 18;
                    break;
                case '19-21':
                    matchesAge = userAge >= 19 && userAge <= 21;
                    break;
                case '22-25':
                    matchesAge = userAge >= 22 && userAge <= 25;
                    break;
                case '26+':
                    matchesAge = userAge >= 26;
                    break;
                default:
                    matchesAge = true;
            }
        }

        return matchesSearch && matchesRole && matchesStatus && matchesDepartment &&
               matchesYearLevel && matchesGender && matchesAge && matchesRegNo && matchesEmail;
    });

    renderUsersTable();
}

function editUser(userId) {
    const user = currentUsers.find(u => u.id === userId);
    if (!user) {
        showAlert('danger', 'User not found');
        return;
    }

    // Populate form
    $("#editUserForm [name='user_id']").val(user.id);
    $("#editUserForm [name='username']").val(user.username || '');
    $("#editUserForm [name='email']").val(user.email || '');
    $("#editUserForm [name='role']").val(user.role || '');
    $("#editUserForm [name='status']").val(user.status || 'active');
    $("#editUserForm [name='first_name']").val(user.first_name || '');
    $("#editUserForm [name='last_name']").val(user.last_name || '');
    $("#editUserForm [name='phone']").val(user.phone || '');
    $("#editUserForm [name='reference_id']").val(user.reference_id || '');
    $("#editUserForm [name='gender']").val(user.gender || '');
    $("#editUserForm [name='dob']").val(user.dob || '');
    $("#editUserForm [name='department_id']").val(user.department_id || '');
    $("#editUserForm [name='education_level']").val(user.education_level || '');
    $("#editUserForm [name='year_level']").val(user.year_level || '1');

    toggleLecturerFields('editUserForm');

    $("#editUserModal").modal('show');
}

function resetPassword(userId) {
    $("#resetPasswordForm [name='user_id']").val(userId);
    $("#resetPasswordModal").modal('show');
}

function toggleUserStatus(userId, currentStatus) {
    const statusOptions = ['active', 'inactive', 'suspended'];
    const currentIndex = statusOptions.indexOf(currentStatus);
    const nextStatus = statusOptions[(currentIndex + 1) % statusOptions.length];

    const statusLabels = {
        'active': 'deactivate',
        'inactive': 'activate',
        'suspended': 'activate'
    };

    if (!confirm(`Are you sure you want to ${statusLabels[nextStatus]} this user?`)) {
        return;
    }

    $.ajax({
        url: 'manage-users.php',
        method: 'POST',
        data: {
            ajax: '1',
            action: 'toggle_status',
            user_id: userId,
            status: nextStatus,
            csrf_token: "<?php echo generate_csrf_token(); ?>"
        },
        success: function(response) {
            if (response.status === 'success') {
                showAlert('success', response.message);
                loadUsers();
                // Trigger cross-page update for department management
                triggerCrossPageUpdate('user_status_changed', { timestamp: Date.now() });
            } else {
                showAlert('danger', response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error toggling status:', error);
            showAlert('danger', 'Failed to update user status');
        }
    });
}

function triggerCrossPageUpdate(eventType, data) {
    try {
        localStorage.setItem(eventType, JSON.stringify(data));
        // Immediately remove to trigger storage event in other tabs
        setTimeout(() => localStorage.removeItem(eventType), 100);
    } catch (e) {
        console.warn('Cross-page update failed:', e);
    }
}

function clearAllFilters() {
    $("#searchInput").val('');
    $("#roleFilter").val('');
    $("#statusFilter").val('');
    $("#departmentFilter").val('');
    $("#yearLevelFilter").val('');
    $("#genderFilter").val('');
    $("#ageFilter").val('');
    $("#regNoFilter").val('');
    $("#emailFilter").val('');
    $("#selectAllUsers").prop('checked', false);
    $('.user-checkbox').prop('checked', false);
    $('#bulkActivate, #bulkDeactivate, #bulkDelete').prop('disabled', true);
    applyFilters();
    showAlert('info', 'All filters cleared');
}

// Keyboard shortcuts
$(document).on('keydown', function(e) {
    // Ctrl/Cmd + F to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        $("#searchInput").focus().select();
    }

    // Escape to clear search
    if (e.key === 'Escape' && $("#searchInput").is(':focus')) {
        $("#searchInput").val('');
        applyFilters();
    }
});

// Event handlers
$(document).ready(function() {
    // Handle URL parameters for pre-filtering
    const urlParams = new URLSearchParams(window.location.search);
    const preselectedRole = urlParams.get('role');

    if (preselectedRole && ['admin', 'hod', 'lecturer', 'student'].includes(preselectedRole)) {
        $("#roleFilter").val(preselectedRole);
        // Update page title and heading
        if (preselectedRole === 'lecturer') {
            $("#mainContent h2").html('<i class="fas fa-chalkboard-teacher me-3"></i>Lecturer Registration');
            $("#mainContent p").text('Register new lecturers for the system');
            $("#createUserModal .modal-title").text('Register New Lecturer');
            $("#createUserForm [name='role']").val('lecturer');
            $("#createUserForm [name='role']").prop('disabled', true);
            toggleLecturerFields('createUserForm');
        }
    }

    // Load users and departments immediately (only for management mode)
    if (!window.isLecturerRegistration) {
        loadUsers();
        loadDepartmentsForFilter();
    }
    loadDepartmentsForForms();

    // Role change handlers for lecturer fields
    $('#createUserForm [name="role"]').on('change', function() {
        toggleLecturerFields('createUserForm');
    });
    $('#editUserForm [name="role"]').on('change', function() {
        toggleLecturerFields('editUserForm');
    });

    // Search and filter events
    $("#searchInput, #roleFilter, #statusFilter, #departmentFilter, #yearLevelFilter, #genderFilter, #ageFilter, #regNoFilter, #emailFilter").on('input change', function() {
        applyFilters();
    });

    $("#clearFilters").click(function() {
        $("#searchInput").val('');
        $("#roleFilter").val('');
        $("#statusFilter").val('');
        $("#departmentFilter").val('');
        $("#yearLevelFilter").val('');
        $("#genderFilter").val('');
        $("#ageFilter").val('');
        $("#regNoFilter").val('');
        $("#emailFilter").val('');
        applyFilters();
    });

    $("#applyAdvancedFilters").click(function() {
        applyFilters();
    });

    // Refresh button
    $("#refreshUsers").click(function() {
        loadUsers();
    });

    // Export button
    $("#exportUsers").click(function() {
        exportUsers();
    });

    // Create user form
    $("#createUserForm").submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('ajax', '1');
        formData.append('action', 'create_user');

        $.ajax({
            url: 'manage-users.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status === 'success') {
                    $("#createUserModal").modal('hide');
                    $("#createUserForm")[0].reset();
                    showAlert('success', response.message);
                    loadUsers();
                    // Trigger cross-page update for department management
                    triggerCrossPageUpdate('user_changed', { timestamp: Date.now() });
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error creating user:', error);
                showAlert('danger', 'Failed to create user');
            }
        });
    });

    // Edit user form
    $("#editUserForm").submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('ajax', '1');
        formData.append('action', 'update_user');

        $.ajax({
            url: 'manage-users.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status === 'success') {
                    $("#editUserModal").modal('hide');
                    showAlert('success', response.message);
                    loadUsers();
                    // Trigger cross-page update for department management
                    triggerCrossPageUpdate('user_changed', { timestamp: Date.now() });
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error updating user:', error);
                showAlert('danger', 'Failed to update user');
            }
        });
    });

    // Reset password form
    $("#resetPasswordForm").submit(function(e) {
        e.preventDefault();
        const newPassword = $(this).find('[name="new_password"]').val();
        const confirmPassword = $(this).find('[name="confirm_password"]').val();

        if (newPassword !== confirmPassword) {
            showAlert('danger', 'Passwords do not match');
            return;
        }

        const formData = new FormData(this);
        formData.append('ajax', '1');
        formData.append('action', 'reset_password');

        $.ajax({
            url: 'manage-users.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status === 'success') {
                    $("#resetPasswordModal").modal('hide');
                    $("#resetPasswordForm")[0].reset();
                    showAlert('success', response.message);
                    // Trigger cross-page update for department management
                    triggerCrossPageUpdate('user_changed', { timestamp: Date.now() });
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error resetting password:', error);
                showAlert('danger', 'Failed to reset password');
            }
        });
    });

    // Lecturer registration form (for dedicated lecturer registration page)
    $("#lecturerRegistrationForm").submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('ajax', '1');
        formData.append('action', 'create_user');

        // Set role to lecturer for this form
        formData.set('role', 'lecturer');

        $.ajax({
            url: 'manage-users.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status === 'success') {
                    $("#lecturerRegistrationForm")[0].reset();
                    showAlert('success', 'Lecturer registered successfully! Default password is the employee ID.');
                    // Redirect back to manage users after successful registration
                    setTimeout(() => {
                        window.location.href = 'manage-users.php';
                    }, 2000);
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error registering lecturer:', error);
                showAlert('danger', 'Failed to register lecturer');
            }
        });
    });

    // Auto-refresh every 2 minutes
    setInterval(loadUsers, 120000);

    // Bulk selection functionality
    $('#selectAllUsers').on('change', function() {
        const isChecked = $(this).is(':checked');
        $('.user-checkbox').prop('checked', isChecked).trigger('change');
    });

    $(document).on('change', '.user-checkbox', function() {
        const checkedBoxes = $('.user-checkbox:checked');
        const bulkButtons = $('#bulkActivate, #bulkDeactivate, #bulkDelete');

        if (checkedBoxes.length > 0) {
            bulkButtons.prop('disabled', false);
        } else {
            bulkButtons.prop('disabled', true);
        }

        // Update select all checkbox state
        const totalBoxes = $('.user-checkbox').length;
        const checkedCount = checkedBoxes.length;
        $('#selectAllUsers').prop('checked', checkedCount === totalBoxes && totalBoxes > 0);
        $('#selectAllUsers').prop('indeterminate', checkedCount > 0 && checkedCount < totalBoxes);
    });

    // Bulk operations
    $('#bulkActivate').on('click', function() {
        performBulkOperation('activate', 'active');
    });

    $('#bulkDeactivate').on('click', function() {
        performBulkOperation('deactivate', 'inactive');
    });

    $('#bulkDelete').on('click', function() {
        if (confirm('Are you sure you want to delete the selected users? This action cannot be undone.')) {
            performBulkOperation('delete');
        }
    });
});

function performBulkOperation(operation, status = null) {
    const selectedUsers = $('.user-checkbox:checked').map(function() {
        return $(this).val();
    }).get();

    if (selectedUsers.length === 0) {
        showAlert('warning', 'Please select users to perform this operation.');
        return;
    }

    showLoading();

    const requests = selectedUsers.map(userId => {
        if (operation === 'delete') {
            return $.ajax({
                url: 'manage-users.php',
                method: 'POST',
                data: {
                    ajax: '1',
                    action: 'delete_user',
                    user_id: userId,
                    csrf_token: "<?php echo generate_csrf_token(); ?>"
                }
            });
        } else {
            return $.ajax({
                url: 'manage-users.php',
                method: 'POST',
                data: {
                    ajax: '1',
                    action: 'toggle_status',
                    user_id: userId,
                    status: status,
                    csrf_token: "<?php echo generate_csrf_token(); ?>"
                }
            });
        }
    });

    Promise.all(requests)
        .then(results => {
            hideLoading();
            const successCount = results.filter(r => r.status === 'success').length;
            const failCount = results.length - successCount;

            if (successCount > 0) {
                showAlert('success', `Successfully ${operation}d ${successCount} user${successCount !== 1 ? 's' : ''}.`);
                loadUsers(); // Refresh the table
            }

            if (failCount > 0) {
                showAlert('warning', `Failed to ${operation} ${failCount} user${failCount !== 1 ? 's' : ''}.`);
            }
        })
        .catch(error => {
            hideLoading();
            console.error('Bulk operation error:', error);
            showAlert('danger', 'An error occurred during the bulk operation.');
        });
}