// assign-hod.js - Enhanced HOD Assignment System Frontend
// Version: 3.0.0
// Dependencies: jQuery 3.7.1+, Bootstrap 5.3.3+

(function(window, document, $) {
    'use strict';

    // Configuration
    const CONFIG = {
        apiBaseUrl: 'api/assign-hod-api-improved.php',
        cacheTimeout: 5 * 60 * 1000, // 5 minutes
        debounceDelay: 300,
        maxRetries: 3,
        retryDelay: 1000
    };

    // State management
    const AppState = {
        departments: [],
        lecturers: [],
        selectedDepartments: new Set(),
        filters: {
            search: '',
            status: 'all'
        },
        isBulkMode: false,
        isLoading: false,
        cache: new Map(),

        // State setters with observers
        setDepartments: function(depts) {
            this.departments = depts;
            this.notify('departmentsChanged', depts);
        },

        setLecturers: function(lects) {
            this.lecturers = lects;
            this.notify('lecturersChanged', lects);
        },

        // Observer pattern
        observers: {},
        subscribe: function(event, callback) {
            if (!this.observers[event]) this.observers[event] = [];
            this.observers[event].push(callback);
        },

        notify: function(event, data) {
            if (this.observers[event]) {
                this.observers[event].forEach(callback => callback(data));
            }
        },

        // Cache management
        setCache: function(key, data, ttl = CONFIG.cacheTimeout) {
            this.cache.set(key, {
                data: data,
                timestamp: Date.now(),
                ttl: ttl
            });
        },

        getCache: function(key) {
            const cached = this.cache.get(key);
            if (cached && (Date.now() - cached.timestamp) < cached.ttl) {
                return cached.data;
            }
            this.cache.delete(key);
            return null;
        },

        clearCache: function() {
            this.cache.clear();
        }
    };

    // Utility functions
    const Utils = {
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        animateValue: function(id, newValue, duration = 600) {
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
        },

        exportToCSV: function(data, filename) {
            const headers = Object.keys(data[0]);
            const csvContent = [
                headers.join(','),
                ...data.map(row => headers.map(header => {
                    const value = row[header];
                    return typeof value === 'string' && value.includes(',') ? `"${value}"` : value;
                }).join(','))
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.setAttribute('hidden', '');
            a.setAttribute('href', url);
            a.setAttribute('download', filename);
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        },

        showToast: function(type, message, duration = 5000) {
            const toastId = 'toast-' + Date.now();
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white bg-${type} border-0" role="alert">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;

            if (!$('#toastContainer').length) {
                $('body').append('<div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3"></div>');
            }

            $('#toastContainer').append(toastHtml);
            const toast = new bootstrap.Toast(document.getElementById(toastId));
            toast.show();

            if (duration > 0) {
                setTimeout(() => {
                    toast.hide();
                    setTimeout(() => $(`#${toastId}`).remove(), 500);
                }, duration);
            }
        },

        formatDateTime: function(dateString) {
            if (!dateString) return 'N/A';
            return new Date(dateString).toLocaleString();
        },

        sanitizeHtml: function(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // API service
    const ApiService = {
        request: function(action, data = {}, method = 'GET') {
            return new Promise((resolve, reject) => {
                const url = `${CONFIG.apiBaseUrl}?action=${action}&ajax=1`;

                const ajaxOptions = {
                    url: url,
                    method: method,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': window.csrfToken
                    },
                    timeout: 30000,
                    success: function(response) {
                        if (response.status === 'success') {
                            resolve(response);
                        } else {
                            reject(new Error(response.message || 'API request failed'));
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMessage = 'Network error occurred';
                        if (xhr.status === 429) {
                            errorMessage = 'Too many requests. Please wait and try again.';
                        } else if (xhr.status === 403) {
                            errorMessage = 'Access denied. Please refresh the page.';
                        } else if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        reject(new Error(errorMessage));
                    }
                };

                if (method === 'POST' && data) {
                    ajaxOptions.data = data;
                    ajaxOptions.contentType = 'application/x-www-form-urlencoded';
                }

                $.ajax(ajaxOptions);
            });
        },

        getDepartments: function() {
            return this.request('get_departments');
        },

        getLecturers: function(departmentId = null) {
            const data = departmentId ? { department_id: departmentId } : {};
            return this.request('get_lecturers', data);
        },

        getStats: function() {
            return this.request('get_assignment_stats');
        },

        assignHod: function(departmentId, hodId) {
            return this.request('assign_hod', {
                department_id: departmentId,
                hod_id: hodId,
                csrf_token: window.csrfToken
            }, 'POST');
        }
    };

    // UI Components
    const UI = {
        showLoading: function(message = 'Loading...') {
            if (!AppState.isLoading) {
                AppState.isLoading = true;
                $("#loadingOverlay").fadeIn();
                $("#loadingOverlay .loading-content h5").text(message);
            }
        },

        hideLoading: function() {
            if (AppState.isLoading) {
                AppState.isLoading = false;
                $("#loadingOverlay").fadeOut();
            }
        },

        showAlert: function(type, message, duration = 5000) {
            Utils.showToast(type, message, duration);
        },

        updateStats: function(stats) {
            if (stats.cached) {
                console.log('Stats loaded from cache');
            }

            Utils.animateValue('totalDepartments', stats.total_departments || 0);
            Utils.animateValue('assignedDepartments', stats.assigned_departments || 0);
            Utils.animateValue('totalLecturers', stats.total_lecturers || 0);
            Utils.animateValue('unassignedDepartments', stats.unassigned_departments || 0);
        },

        renderDepartments: function(departments) {
            const container = $('#assignmentsContainer');
            const filteredDepts = UI.filterDepartments(departments);

            if (!filteredDepts || filteredDepts.length === 0) {
                container.html(`
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Departments Found</h5>
                        <p class="text-muted">No departments match your current filters.</p>
                        <button class="btn btn-primary" onclick="loadData()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh Data
                        </button>
                    </div>
                `);
                return;
            }

            let html = '';
            filteredDepts.forEach(dept => {
                const hasInvalidAssignment = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');
                const cardClass = hasInvalidAssignment ? 'assignment-card invalid' : (dept.hod_id ? 'assignment-card assigned' : 'assignment-card unassigned');
                const statusIcon = hasInvalidAssignment ? 'fas fa-exclamation-circle text-danger' : (dept.hod_id ? 'fas fa-user-check text-success' : 'fas fa-exclamation-triangle text-warning');
                const statusText = hasInvalidAssignment ? 'Invalid' : (dept.hod_id ? 'Assigned' : 'Unassigned');
                const statusColor = hasInvalidAssignment ? 'danger' : (dept.hod_id ? 'success' : 'warning');
                const isSelected = AppState.selectedDepartments.has(dept.id.toString());

                html += `
                    <div class="col-md-6 col-lg-4">
                        <div class="card ${cardClass} h-100 department-card ${isSelected ? 'selected' : ''}"
                             data-department-id="${dept.id}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title text-primary mb-0">${Utils.sanitizeHtml(dept.name)}</h6>
                                    <span class="badge bg-${statusColor}">
                                        <i class="${statusIcon} me-1"></i>${statusText}
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted d-block">Current HOD:</small>
                                    <div class="${dept.hod_id ? (hasInvalidAssignment ? 'text-danger' : 'text-success') : 'text-warning'} fw-semibold">
                                        ${hasInvalidAssignment ? 'INVALID ASSIGNMENT' : (Utils.sanitizeHtml(dept.hod_name) || 'Not Assigned')}
                                    </div>
                                    ${hasInvalidAssignment ? '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>HOD ID exists but lecturer not found</small>' : ''}
                                    ${dept.data_integrity ? `<small class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>${Utils.sanitizeHtml(dept.data_integrity_message)}</small>` : ''}
                                </div>

                                <div class="mb-3">
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar bg-${statusColor}" role="progressbar"
                                             style="width: ${hasInvalidAssignment ? '50%' : (dept.hod_id ? '100%' : '0%')}">
                                        </div>
                                    </div>
                                </div>

                                <button class="btn btn-sm ${hasInvalidAssignment ? 'btn-outline-danger' : 'btn-outline-primary'} w-100 selectDepartment"
                                        data-id="${dept.id}"
                                        data-name="${Utils.sanitizeHtml(dept.name)}"
                                        data-hod="${dept.hod_id || ''}"
                                        data-hod-name="${Utils.sanitizeHtml(dept.hod_name || '')}"
                                        data-invalid="${hasInvalidAssignment}">
                                    <i class="fas fa-${hasInvalidAssignment ? 'exclamation-triangle' : 'mouse-pointer'} me-1"></i>
                                    ${hasInvalidAssignment ? 'Fix Assignment' : 'Select for Assignment'}
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });

            container.html(html);
            UI.setupDepartmentCardInteractions();
        },

        filterDepartments: function(departments) {
            if (!departments || !Array.isArray(departments)) {
                console.warn('filterDepartments: Invalid departments data', departments);
                return [];
            }

            const status = AppState.filters.status;
            const search = AppState.filters.search.toLowerCase();

            return departments.filter(dept => {
                if (!dept || typeof dept !== 'object') return false;

                // Status filter
                if (status !== 'all') {
                    const hasInvalidAssignment = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');
                    const matchesStatus =
                        (status === 'assigned' && dept.hod_id && !hasInvalidAssignment) ||
                        (status === 'unassigned' && !dept.hod_id) ||
                        (status === 'invalid' && hasInvalidAssignment);

                    if (!matchesStatus) return false;
                }

                // Search filter
                if (search) {
                    const searchableText = ((dept.name || '') + ' ' + (dept.hod_name || '')).toLowerCase();
                    if (!searchableText.includes(search)) return false;
                }

                return true;
            });
        },

        setupDepartmentCardInteractions: function() {
            $('.selectDepartment').off('click').on('click', function() {
                const deptId = $(this).data('id');
                const deptName = $(this).data('name');
                const hodId = $(this).data('hod');
                const hodName = $(this).data('hod-name');
                const isInvalid = $(this).data('invalid');

                if (AppState.isBulkMode) {
                    if (AppState.selectedDepartments.has(deptId.toString())) {
                        AppState.selectedDepartments.delete(deptId.toString());
                        $(this).closest('.department-card').removeClass('selected');
                    } else {
                        AppState.selectedDepartments.add(deptId.toString());
                        $(this).closest('.department-card').addClass('selected');
                    }
                    UI.updateBulkModeUI();
                } else {
                    $('.department-card').removeClass('selected');
                    $(this).closest('.department-card').addClass('selected');

                    $('#departmentSelect').val(deptId);
                    
                    // Load lecturers for the selected department
                    console.log('Loading lecturers for selected department:', deptId);
                    loadLecturersForDepartment(deptId).then(() => {
                        // After lecturers are loaded, set the current HOD if valid
                        if (hodId && hodName && !isInvalid) {
                            // Find the lecturer that corresponds to this HOD
                            const matchingLecturer = AppState.lecturers.find(lecturer => 
                                lecturer.full_name === hodName || 
                                `${lecturer.first_name} ${lecturer.last_name}` === hodName
                            );
                            
                            if (matchingLecturer) {
                                $('#lecturerSelect').val(matchingLecturer.id);
                                $('#currentAssignmentInfo').show();
                                $('#currentHodName').text(hodName);
                            } else {
                                $('#lecturerSelect').val('');
                                $('#currentAssignmentInfo').hide();
                                console.warn('Could not find matching lecturer for HOD:', hodName);
                            }
                        } else {
                            $('#lecturerSelect').val('');
                            if (isInvalid) {
                                $('#currentAssignmentInfo').hide();
                                UI.showAlert('warning', `Department <strong>${deptName}</strong> has an invalid HOD assignment that needs to be fixed.`);
                            } else {
                                $('#currentAssignmentInfo').hide();
                            }
                        }
                        
                        Validation.validateForm();
                    }).catch(error => {
                        console.error('Failed to load lecturers for department:', error);
                        $('#lecturerSelect').val('');
                        $('#currentAssignmentInfo').hide();
                        UI.showAlert('warning', 'Failed to load lecturers for selected department');
                    });

                    $('#assignHodForm')[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'start',
                        inline: 'nearest'
                    });

                    const alertType = isInvalid ? 'warning' : 'info';
                    const alertMessage = isInvalid ? `Selected department with invalid assignment: <strong>${deptName}</strong>` : `Selected department: <strong>${deptName}</strong>`;

                    UI.showAlert(alertType, alertMessage);
                }
            });

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
        },

        updateBulkModeUI: function() {
            const selectedCount = AppState.selectedDepartments.size;
            if (selectedCount > 0) {
                $('#assignBtn').html(`<i class="fas fa-save me-2"></i>Assign to ${selectedCount} Departments`);
            } else {
                $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign to Selected');
            }
        }
    };

    // Form validation
    const Validation = {
        validateForm: function() {
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
                const selectedLecturer = $('#lecturerSelect option:selected').text();
                $('#lecturerSelectFeedback')
                    .html(`<i class="fas fa-check me-1"></i>HOD selected: <strong>${Utils.sanitizeHtml(selectedLecturer)}</strong>`)
                    .removeClass('text-warning text-danger').addClass('text-success');
            } else {
                $('#lecturerSelect').removeClass('is-valid is-invalid');
                const lecturerCount = $('#lecturerSelect').find('option').length - 1;
                if (lecturerCount === 0) {
                    $('#lecturerSelectFeedback')
                        .html('<i class="fas fa-exclamation-triangle me-1"></i>No lecturers available in database')
                        .removeClass('text-success text-info').addClass('text-warning');
                } else {
                    $('#lecturerSelectFeedback')
                        .html('<i class="fas fa-info-circle me-1"></i>Optional - leave empty to remove HOD assignment')
                        .removeClass('text-success text-danger').addClass('text-info');
                }
            }

            // Update submit button state
            const submitBtn = $('#assignBtn');
            if (isValid) {
                submitBtn.prop('disabled', false).removeClass('btn-secondary').addClass('btn-primary');
            } else {
                submitBtn.prop('disabled', true).removeClass('btn-primary').addClass('btn-secondary');
            }

            return isValid;
        },

        resetForm: function() {
            $('#assignHodForm')[0].reset();
            $('#currentAssignmentInfo').hide();
            $('#assignmentPreview').hide();
            $('#departmentSearch').val('');
            $('#lecturerSearch').val('');
            $('.department-card').removeClass('selected');
            AppState.selectedDepartments.clear();

            if (AppState.isBulkMode) {
                disableBulkMode();
            }
        }
    };

    // Main application functions
    function initializeApp() {
        console.log('Initializing HOD Assignment System...');

        // Subscribe to state changes
        AppState.subscribe('departmentsChanged', UI.renderDepartments);
        AppState.subscribe('lecturersChanged', updateLecturerSelect);

        // Add keyboard shortcuts help
        $(document).on('keydown', handleKeyboardShortcuts);
    }

    function handleKeyboardShortcuts(e) {
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
            if (AppState.isBulkMode) {
                window.disableBulkMode();
            } else {
                Validation.resetForm();
            }
        }

        // Ctrl/Cmd + B to toggle bulk mode
        if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
            e.preventDefault();
            window.toggleBulkMode();
        }
    }

    function setupEventHandlers() {
        // Department form submission
        $('#assignHodForm').on('submit', handleDepartmentSubmit);

        // Department selection change
        $('#departmentSelect').on('change', function() {
            const selectedDeptId = $(this).val();
            updateCurrentAssignmentInfo();
            Validation.validateForm();

            // Load lecturers for the selected department
            if (selectedDeptId) {
                console.log('Loading lecturers for department:', selectedDeptId);
                loadLecturersForDepartment(selectedDeptId).then(() => {
                    console.log('Lecturers loaded for department:', selectedDeptId);
                }).catch(error => {
                    console.error('Failed to load lecturers for department:', error);
                    UI.showAlert('warning', 'Failed to load lecturers for selected department');
                });
            } else {
                // If no department selected, load all lecturers
                loadLecturers().then(() => {
                    console.log('All lecturers loaded');
                }).catch(error => {
                    console.error('Failed to load lecturers:', error);
                });
            }
        });

        // Lecturer selection change
        $('#lecturerSelect').on('change', function() {
            Validation.validateForm();
            updateAssignmentPreview();
        });

        // Search functionality with debouncing
        const debouncedDepartmentSearch = Utils.debounce((term) => {
            AppState.filters.search = term;
            UI.renderDepartments(AppState.departments);
        }, CONFIG.debounceDelay);

        const debouncedLecturerSearch = Utils.debounce((term) => {
            filterLecturers(term);
        }, CONFIG.debounceDelay);

        $('#departmentSearch').on('input', function() {
            debouncedDepartmentSearch($(this).val());
        });

        $('#lecturerSearch').on('input', function() {
            debouncedLecturerSearch($(this).val());
        });

        // Reset form
        $('#resetFormBtn').on('click', Validation.resetForm);

        // Refresh assignments
        $('#refreshAssignments').on('click', loadAssignments);

        // Form validation on change
        $('#departmentSelect, #lecturerSelect').on('change', Validation.validateForm);
    }

    function handleDepartmentSubmit(e) {
        e.preventDefault();

        if (AppState.isBulkMode && AppState.selectedDepartments.size > 0) {
            handleBulkAssignment();
            return;
        }

        const departmentId = $('#departmentSelect').val();
        const hodId = $('#lecturerSelect').val();

        if (!departmentId) {
            UI.showAlert('warning', 'Please select a department');
            $('#departmentSelect').focus();
            return;
        }

        const department = AppState.departments.find(dept => dept.id == departmentId);
        const isInvalidAssignment = department.hod_id && (!department.hod_name || department.hod_name === 'Not Assigned');

        // Confirmation for critical actions
        if (isInvalidAssignment && !hodId) {
            if (!confirm('This department has an invalid HOD assignment. Removing the HOD will clear this invalid reference. Continue?')) {
                return;
            }
        }

        submitAssignment(departmentId, hodId);
    }

    function submitAssignment(departmentId, hodId, isBulk = false) {
        UI.showLoading();
        const submitBtn = $('#assignBtn');
        const originalText = submitBtn.html();

        if (isBulk) {
            submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing Bulk Assignment...');
        } else {
            submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
        }

        submitBtn.prop('disabled', true);

        ApiService.assignHod(departmentId, hodId)
            .then(response => {
                const message = isBulk ?
                    'Bulk assignment completed successfully!' :
                    response.message || 'HOD assignment updated successfully!';
                UI.showAlert('success', message);
                loadData().then(() => {
                    // After loading data, refresh lecturers for currently selected department if any
                    const selectedDeptId = $('#departmentSelect').val();
                    if (selectedDeptId) {
                        loadLecturersForDepartment(selectedDeptId).catch(error => {
                            console.error('Failed to refresh lecturers after assignment:', error);
                        });
                    }
                });
                if (!isBulk) {
                    Validation.resetForm();
                }
            })
            .catch(error => {
                let errorMessage = 'Failed to process HOD assignment. Please try again.';
                if (error.message) {
                    errorMessage = error.message;
                }
                UI.showAlert('danger', errorMessage);
            })
            .finally(() => {
                UI.hideLoading();
                submitBtn.html(originalText).prop('disabled', false);
            });
    }

    function handleBulkAssignment() {
        const hodId = $('#lecturerSelect').val();
        const selectedCount = AppState.selectedDepartments.size;

        if (!hodId) {
            UI.showAlert('warning', 'Please select an HOD for bulk assignment');
            return;
        }

        if (confirm(`Assign the selected HOD to ${selectedCount} department(s)?`)) {
            UI.showLoading();

            let processed = 0;
            let successCount = 0;
            let errorCount = 0;

            AppState.selectedDepartments.forEach(deptId => {
                ApiService.assignHod(deptId, hodId)
                    .then(() => {
                        successCount++;
                    })
                    .catch(() => {
                        errorCount++;
                    })
                    .finally(() => {
                        processed++;
                        if (processed === selectedCount) {
                            UI.hideLoading();
                            if (errorCount === 0) {
                                UI.showAlert('success', `Successfully assigned HOD to ${successCount} department(s)!`);
                            } else {
                                UI.showAlert('warning', `Assigned HOD to ${successCount} department(s), but ${errorCount} failed.`);
                            }
                            loadData();
                            window.disableBulkMode();
                        }
                    });
            });
        }
    }

    function updateCurrentAssignmentInfo() {
        const selectedOption = $('#departmentSelect').find('option:selected');
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
    }

    function updateAssignmentPreview() {
        const departmentId = $('#departmentSelect').val();
        const lecturerId = $('#lecturerSelect').val();

        if (!departmentId) {
            $('#assignmentPreview').hide();
            return;
        }

        const departmentOption = $('#departmentSelect option:selected');
        const lecturerOption = $('#lecturerSelect option:selected');
        const isInvalidAssignment = departmentOption.data('invalid') === 'true';

        const departmentName = departmentOption.text();
        const lecturerName = lecturerOption.text() || 'No HOD';

        let previewHtml = '<div class="row">';
        previewHtml += '<div class="col-md-6">';
        previewHtml += '<h6><i class="fas fa-building me-2"></i>Department</h6>';
        previewHtml += `<p class="mb-1"><strong>${Utils.sanitizeHtml(departmentName)}</strong></p>`;
        if (isInvalidAssignment) {
            previewHtml += '<small class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Has invalid current assignment</small>';
        }
        previewHtml += '</div>';

        if (lecturerId) {
            previewHtml += '<div class="col-md-6">';
            previewHtml += '<h6><i class="fas fa-user-graduate me-2"></i>New HOD</h6>';
            previewHtml += `<p class="mb-1"><strong>${Utils.sanitizeHtml(lecturerName)}</strong></p>`;
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
        updateAssignmentPreview();
    }

    function clearCurrentAssignment() {
        $('#currentAssignmentInfo').hide();
        $('#lecturerSelect').val('');
        updateAssignmentPreview();
    }

    function filterLecturers(searchTerm) {
        const select = $('#lecturerSelect');
        const options = select.find('option');
        let visibleCount = 0;

        options.each(function() {
            const option = $(this);
            const text = option.text().toLowerCase();
            const shouldShow = !searchTerm || text.includes(searchTerm.toLowerCase());

            if (option.val() !== '') {
                option.toggle(shouldShow);
                if (shouldShow) visibleCount++;
            }
        });

        updateSearchFeedback('lecturerSelectFeedback', searchTerm, visibleCount, 'lecturer');
    }

    function updateSearchFeedback(elementId, searchTerm, visibleCount, type) {
        const feedback = $('#' + elementId);

        if (searchTerm) {
            if (visibleCount === 0) {
                feedback.html(`<i class="fas fa-exclamation-triangle me-1"></i>No ${type}s found matching "<strong>${Utils.sanitizeHtml(searchTerm)}</strong>"`)
                    .removeClass('text-success').addClass('text-warning');
            } else {
                feedback.html(`<i class="fas fa-check me-1"></i>Found ${visibleCount} ${type}${visibleCount !== 1 ? 's' : ''} matching "<strong>${Utils.sanitizeHtml(searchTerm)}</strong>"`)
                    .removeClass('text-warning').addClass('text-success');
            }
        } else {
            feedback.text('').removeClass('text-success text-warning');
        }
    }

    function checkDataIntegrity() {
        const invalidAssignments = AppState.departments.filter(dept =>
            dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
        );

        if (invalidAssignments.length > 0) {
            const departmentNames = invalidAssignments.map(dept => dept.name).join(', ');
            const alertHtml = `
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Data Integrity Issue:</strong> Found ${invalidAssignments.length} department(s) with invalid HOD assignments.
                    <br><small class="text-muted">Affected departments: ${Utils.sanitizeHtml(departmentNames)}</small>
                    <br><small class="text-muted">These departments have hod_id values pointing to non-HOD users or missing records.</small>
                    <button type="button class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alertContainer').append(alertHtml);
            $('#fixInvalidBtn').show();
        } else {
            $('#fixInvalidBtn').hide();
        }
    }

    function fixInvalidAssignments() {
        const invalidDepts = AppState.departments.filter(dept =>
            dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
        );

        if (invalidDepts.length === 0) {
            UI.showAlert('info', 'No invalid assignments found.');
            return;
        }

        if (confirm(`Found ${invalidDepts.length} department(s) with invalid HOD assignments.\n\nThese departments have hod_id values pointing to users who are not HODs or missing lecturer records.\n\nFix them by clearing the invalid HOD assignments?`)) {
            UI.showLoading();

            let fixedCount = 0;
            let errorCount = 0;

            const fixNext = (index) => {
                if (index >= invalidDepts.length) {
                    UI.hideLoading();
                    if (errorCount === 0) {
                        UI.showAlert('success', `Successfully fixed ${fixedCount} invalid HOD assignment(s)!`);
                    } else {
                        UI.showAlert('warning', `Fixed ${fixedCount} assignment(s), but ${errorCount} failed. Please check the console for details.`);
                    }
                    loadData();
                    return;
                }

                const dept = invalidDepts[index];
                console.log(`Fixing department: ${dept.name} (ID: ${dept.id})`);

                ApiService.assignHod(dept.id, null)
                    .then(() => {
                        console.log(`✅ Fixed: ${dept.name}`);
                        fixedCount++;
                    })
                    .catch(error => {
                        console.error(`❌ Failed to fix ${dept.name}:`, error);
                        errorCount++;
                    })
                    .finally(() => {
                        fixNext(index + 1);
                    });
            };

            fixNext(0);
        }
    }

    function setupFullscreenHandling() {
        function isFullscreen() {
            return !!(document.fullscreenElement || document.webkitFullscreenElement ||
                    document.mozFullScreenElement || document.msFullscreenElement);
        }

        function adjustForFullscreen() {
            const fullscreen = isFullscreen();
            const windowWidth = $(window).width();

            if (fullscreen) {
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
                }
            } else {
                const heightUnit = windowWidth <= 768 ? '100dvh' : '100vh';
                $('.sidebar').css({
                    'height': heightUnit
                });
                $('.main-content').css({
                    'min-height': heightUnit
                });
            }
        }

        $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange MSFullscreenChange', function() {
            setTimeout(adjustForFullscreen, 100);
        });

        $(window).on('orientationchange', function() {
            setTimeout(adjustForFullscreen, 200);
        });

        setTimeout(adjustForFullscreen, 500);
    }

    function loadData() {
        UI.showLoading('Loading HOD Assignment System');

        return Promise.all([
            loadDepartments(),
            loadLecturers(),
            loadStatistics(),
            loadAssignments()
        ])
        .then(() => {
            UI.hideLoading();
            checkDataIntegrity();
            console.log('All data loaded successfully');
        })
        .catch(error => {
            UI.hideLoading();
            console.error('Error loading data:', error);
            UI.showAlert('danger', 'Failed to load data: ' + error.message);
            throw error; // Re-throw to maintain Promise chain
        });
    }

    function loadDepartments() {
        return ApiService.getDepartments()
            .then(response => {
                AppState.setDepartments(response.data);
                populateDepartmentSelect(response.data);
                if (response.cached) {
                    console.log('Departments loaded from cache');
                }
                return response.data;
            });
    }

    function loadLecturers(departmentId = null) {
        return ApiService.getLecturers(departmentId)
            .then(response => {
                AppState.setLecturers(response.data);
                if (response.cached) {
                    console.log('Lecturers loaded from cache');
                }
                return response.data;
            });
    }

    function loadStatistics() {
        return ApiService.getStats()
            .then(response => {
                UI.updateStats(response.data);
                return response.data;
            });
    }

    function loadAssignments() {
        // This is the same as loadDepartments for now
        return loadDepartments();
    }

    function populateDepartmentSelect(departments) {
        const select = $('#departmentSelect');
        select.empty().append('<option value="">-- Select Department --</option>');

        if (departments && departments.length > 0) {
            departments.forEach(dept => {
                const hodName = dept.hod_first_name && dept.hod_last_name ?
                    `${dept.hod_first_name} ${dept.hod_last_name}` : '';
                const selected = hodName ? ` (Current HOD: ${hodName})` : '';

                select.append(`<option value="${dept.id}"
                    data-hod="${dept.hod_id || ''}"
                    data-hod-name="${hodName}">
                    ${Utils.sanitizeHtml(dept.name)}${selected}
                </option>`);
            });
        } else {
            select.append('<option value="" disabled>No departments available</option>');
        }
    }

    function updateLecturerSelect() {
        const lecturers = AppState.lecturers;
        console.log('Updating lecturer select with:', lecturers);

        const select = $('#lecturerSelect');
        select.empty().append('<option value="">-- Select Lecturer (Optional) --</option>');

        if (lecturers && lecturers.length > 0) {
            let availableCount = 0;
            lecturers.forEach(lecturer => {
                const displayName = lecturer.full_name ||
                                   `${lecturer.first_name} ${lecturer.last_name}`;

                // Check if lecturer can be assigned as HOD
                let optionClass = '';
                let warningText = '';
                let disabled = false;

                if (lecturer.hod_status === 'already_hod') {
                    warningText = ` (Already HOD of ${lecturer.current_hod_dept_name})`;
                    optionClass = 'text-warning';
                    disabled = true;
                } else if (lecturer.warning_message) {
                    warningText = ` (${lecturer.warning_message})`;
                    optionClass = 'text-muted';
                    disabled = !lecturer.can_be_hod;
                } else if (lecturer.can_be_hod) {
                    availableCount++;
                }

                const option = `<option value="${lecturer.id}" ${disabled ? 'disabled' : ''} class="${optionClass}">
                    ${Utils.sanitizeHtml(displayName)}${warningText}
                </option>`;
                
                select.append(option);
            });
            
            console.log(`Loaded ${lecturers.length} lecturers (${availableCount} available) into dropdown`);
            
            // Update feedback based on available lecturers
            if (availableCount === 0) {
                $('#lecturerSelectFeedback')
                    .html('<i class="fas fa-exclamation-triangle me-1"></i>No lecturers available for HOD assignment in this department')
                    .removeClass('text-success text-info').addClass('text-warning');
            } else {
                $('#lecturerSelectFeedback')
                    .html(`<i class="fas fa-info-circle me-1"></i>${availableCount} lecturer(s) available for HOD assignment`)
                    .removeClass('text-success text-warning').addClass('text-info');
            }
        } else {
            select.append('<option value="" disabled>No lecturers found in this department</option>');
            $('#lecturerSelectFeedback')
                .html('<i class="fas fa-exclamation-triangle me-1"></i>No lecturers found in this department')
                .removeClass('text-success text-info').addClass('text-warning');
            console.warn('No lecturers available for dropdown');
        }

        select.trigger('change');
    }

    // Additional utility functions
    function globalSearch() {
        const searchTerm = $('#globalSearch').val().trim();
        AppState.filters.search = searchTerm;
        UI.renderDepartments(AppState.departments);
        updateResultCount();
    }

    function updateResultCount() {
        const filteredCount = UI.filterDepartments(AppState.departments).length;
        const totalCount = AppState.departments.length;

        if (AppState.filters.search || AppState.filters.status !== 'all') {
            $('#resultCountText').text(filteredCount);
            $('#resultCount').show();
        } else {
            $('#resultCount').hide();
        }
    }

    function showQuickStats() {
        const stats = {
            total: AppState.departments.length,
            assigned: AppState.departments.filter(d => d.hod_id).length,
            unassigned: AppState.departments.filter(d => !d.hod_id).length,
            invalid: AppState.departments.filter(d => d.hod_id && (!d.hod_name || d.hod_name === 'Not Assigned')).length
        };

        const statsHtml = `
            <div class="modal fade" id="quickStatsModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-chart-bar me-2"></i>Quick Statistics
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="h3 text-primary">${stats.total}</div>
                                        <small class="text-muted">Total Departments</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="h3 text-success">${stats.assigned}</div>
                                        <small class="text-muted">Assigned HODs</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="h3 text-warning">${stats.unassigned}</div>
                                        <small class="text-muted">Unassigned</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <div class="h3 text-danger">${stats.invalid}</div>
                                        <small class="text-muted">Invalid Assignments</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        $('#quickStatsModal').remove();
        $('body').append(statsHtml);
        const modal = new bootstrap.Modal(document.getElementById('quickStatsModal'));
        modal.show();
    }

    // Make functions globally available
    window.globalSearch = globalSearch;
    window.showQuickStats = showQuickStats;

    // DOM Ready
    $(document).ready(function() {
        initializeApp();
        setupEventHandlers();
        setupFullscreenHandling();
        loadData();
    });

    // Global exports
    window.loadData = loadData;
    window.exportAssignments = function() {
        const assignments = AppState.departments.map(dept => ({
            'Department ID': dept.id,
            'Department Name': dept.name,
            'HOD Name': dept.hod_name || 'Not Assigned',
            'HOD Email': dept.hod_email || '',
            'Status': dept.hod_id ? 'Assigned' : 'Unassigned',
            'Last Updated': dept.updated_at || 'N/A'
        }));

        Utils.exportToCSV(assignments, `hod-assignments-${new Date().toISOString().split('T')[0]}.csv`);
        UI.showAlert('success', 'Assignments exported successfully!');
    };
    window.showHelp = function() {
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
                                <li><kbd>Ctrl+B</kbd> <span class="text-muted">Toggle bulk mode</span></li>
                                <li><kbd>Esc</kbd> <span class="text-muted">Reset form / Exit bulk mode</span></li>
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
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Got it!</button>
                </div>
            </div>
        </div>
    `;

        $('#helpModal').remove();
        $('body').append(helpHtml);

        const modal = new bootstrap.Modal(document.getElementById('helpModal'));
        modal.show();
    };
    window.toggleSidebar = function() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('show');
    };
    window.enableBulkMode = function() {
        AppState.isBulkMode = true;
        $('#bulkModeBtn').removeClass('btn-outline-success').addClass('btn-success')
            .html('<i class="fas fa-layer-group me-2"></i>Bulk Mode Active');
        $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign to Selected');
        UI.showAlert('info', 'Bulk mode activated. Select multiple departments and choose an HOD to assign to all selected departments.', 0);
    };
    window.disableBulkMode = function() {
        AppState.isBulkMode = false;
        AppState.selectedDepartments.clear();
        $('#bulkModeBtn').removeClass('btn-success').addClass('btn-outline-success')
            .html('<i class="fas fa-layer-group me-2"></i>Bulk Mode');
        $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign HOD');
        $('.department-card').removeClass('selected');
        UI.showAlert('info', 'Bulk mode disabled.');
    };
    window.toggleBulkMode = function() {
        if (AppState.isBulkMode) {
            window.disableBulkMode();
        } else {
            window.enableBulkMode();
        }
    };
    window.filterAssignments = function() {
        UI.renderDepartments(AppState.departments);
    };

    // Add the missing function for department-specific lecturer loading
    function loadLecturersForDepartment(departmentId) {
        return new Promise((resolve, reject) => {
            console.log(`Loading lecturers for department ID: ${departmentId}`);
            
            // Show loading state
            const select = $('#lecturerSelect');
            select.empty().append('<option value="">Loading lecturers...</option>');
            select.prop('disabled', true);
            
            const url = `${CONFIG.apiBaseUrl}?action=get_lecturers&ajax=1&department_id=${departmentId}`;
            
            $.ajax({
                url: url,
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.csrfToken
                },
                timeout: 30000,
                success: function(response) {
                    console.log('Lecturer loading response:', response);
                    
                    if (response.status === 'success') {
                        AppState.setLecturers(response.data || []);
                        console.log(`Successfully loaded ${(response.data || []).length} lecturers for department ${departmentId}`);
                        
                        // Re-enable the select
                        select.prop('disabled', false);
                        
                        // Show success message if no lecturers found
                        if (!response.data || response.data.length === 0) {
                            UI.showAlert('info', 'No lecturers found in this department. You may need to add lecturers to this department first.');
                        }
                        
                        resolve(response.data || []);
                    } else {
                        console.error('API returned error:', response.message);
                        select.prop('disabled', false);
                        select.empty().append('<option value="">Error loading lecturers</option>');
                        UI.showAlert('danger', response.message || 'Failed to load lecturers for selected department');
                        reject(new Error(response.message || 'Failed to load lecturers'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error loading lecturers:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        responseText: xhr.responseText,
                        error: error
                    });
                    
                    let errorMessage = 'Failed to load lecturers for selected department';
                    
                    if (xhr.status === 0) {
                        errorMessage = 'Network connection failed. Please check your internet connection.';
                    } else if (xhr.status === 404) {
                        errorMessage = 'API endpoint not found. Please contact administrator.';
                    } else if (xhr.status === 429) {
                        errorMessage = 'Too many requests. Please wait and try again.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'Access denied. Please refresh the page and try again.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error occurred. Please try again later.';
                    } else if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    }
                    
                    // Re-enable select and show error
                    select.prop('disabled', false);
                    select.empty().append('<option value="">Error loading lecturers</option>');
                    
                    UI.showAlert('danger', errorMessage);
                    reject(new Error(errorMessage));
                }
            });
        });
    }

})(window, document, jQuery);