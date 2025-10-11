/**
 * HOD Assignment System - Enhanced JavaScript
 * Separated from main PHP file for better maintainability
 * Version: 2.0.0
 */

class HodAssignmentManager {
    constructor() {
        this.config = {
            retryAttempts: 3,
            retryDelay: 1000,
            debounceDelay: 300,
            maxBulkOperations: 10
        };

        this.state = {
            departments: [],
            lecturers: [],
            selectedDepartments: new Set(),
            isBulkMode: false,
            isLoading: false,
            csrfToken: null
        };

        this.selectors = {
            form: '#assignHodForm',
            departmentSelect: '#departmentSelect',
            lecturerSelect: '#lecturerSelect',
            departmentSearch: '#departmentSearch',
            lecturerSearch: '#lecturerSearch',
            assignmentsContainer: '#assignmentsContainer',
            alertContainer: '#alertContainer',
            loadingOverlay: '#loadingOverlay',
            statusFilter: '#statusFilter'
        };

        this.init();
    }

    /**
     * Initialize the application
     */
    async init() {
        try {
            this.state.csrfToken = window.csrfToken || this.getCsrfToken();
            this.setupEventListeners();
            this.setupKeyboardShortcuts();
            this.setupAccessibility();
            this.loadInitialData();
        } catch (error) {
            console.error('HOD Assignment Manager initialization failed:', error);
            this.showAlert('error', 'Failed to initialize the application. Please refresh the page.');
        }
    }

    /**
     * Setup all event listeners
     */
    setupEventListeners() {
        // Form submission
        $(this.selectors.form).on('submit', (e) => this.handleFormSubmit(e));

        // Department and lecturer selection
        $(this.selectors.departmentSelect).on('change', () => this.handleDepartmentChange());
        $(this.selectors.lecturerSelect).on('change', () => this.handleLecturerChange());

        // Search functionality with debouncing
        $(this.selectors.departmentSearch).on('input', this.debounce((e) => {
            this.filterDepartments($(e.target).val());
        }, this.config.debounceDelay));

        $(this.selectors.lecturerSearch).on('input', this.debounce((e) => {
            this.filterLecturers($(e.target).val());
        }, this.config.debounceDelay));

        // Status filtering
        $(this.selectors.statusFilter).on('change', () => this.filterAssignments());

        // Button actions
        $('#resetFormBtn').on('click', () => this.resetForm());
        $('#refreshAssignments').on('click', () => this.loadAssignments());
        $('#previewBtn').on('click', () => this.showAssignmentPreview());
        $('#bulkModeBtn').on('click', () => this.toggleBulkMode());

        // Global loading management
        $(document).on('ajaxStart', () => this.showGlobalLoading());
        $(document).on('ajaxStop', () => this.hideGlobalLoading());
    }

    /**
     * Setup keyboard shortcuts
     */
    setupKeyboardShortcuts() {
        $(document).on('keydown', (e) => {
            // Ctrl/Cmd + R to refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.loadInitialData();
            }

            // Ctrl/Cmd + S to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 's' && $(this.selectors.departmentSelect).val()) {
                e.preventDefault();
                $(this.selectors.form).submit();
            }

            // Ctrl/Cmd + B to toggle bulk mode
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                this.toggleBulkMode();
            }

            // Escape to reset form
            if (e.key === 'Escape') {
                if (this.state.isBulkMode) {
                    this.disableBulkMode();
                } else {
                    this.resetForm();
                }
            }
        });
    }

    /**
     * Setup accessibility features
     */
    setupAccessibility() {
        // Add ARIA labels and live regions
        $(this.selectors.alertContainer).attr('aria-live', 'polite');
        $(this.selectors.alertContainer).attr('aria-atomic', 'true');

        // Focus management
        $(this.selectors.form).find('input, select').on('focus', function() {
            $(this).attr('aria-describedby', $(this).next('.form-text').attr('id'));
        });
    }

    /**
     * Load initial data
     */
    async loadInitialData() {
        try {
            this.showLoading();

            const [departments, lecturers, stats] = await Promise.all([
                this.apiCall('get_departments'),
                this.apiCall('get_lecturers'),
                this.apiCall('get_assignment_stats')
            ]);

            this.state.departments = departments.data || [];
            this.state.lecturers = lecturers.data || [];

            this.updateStatistics(stats.data);
            this.renderDepartments();
            this.renderLecturers();
            this.renderAssignments();

            this.checkDataIntegrity();
            this.showAlert('success', 'Data loaded successfully');

        } catch (error) {
            console.error('Failed to load initial data:', error);
            this.showAlert('error', 'Failed to load data. Please refresh the page.');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Handle form submission
     */
    async handleFormSubmit(e) {
        e.preventDefault();

        if (this.state.isBulkMode) {
            await this.handleBulkAssignment();
            return;
        }

        await this.handleSingleAssignment();
    }

    /**
     * Handle single department assignment
     */
    async handleSingleAssignment() {
        const departmentId = $(this.selectors.departmentSelect).val();
        const lecturerId = $(this.selectors.lecturerSelect).val();

        if (!departmentId) {
            this.showAlert('warning', 'Please select a department');
            $(this.selectors.departmentSelect).focus();
            return;
        }

        if (!await this.confirmAssignment(departmentId, lecturerId)) {
            return;
        }

        try {
            const response = await this.apiCall('assign_hod', {
                department_id: departmentId,
                hod_id: lecturerId || null
            });

            if (response.status === 'success') {
                this.showAlert('success', response.message || 'HOD assigned successfully');
                await this.loadInitialData();
                this.resetForm();
            } else {
                this.showAlert('error', response.message || 'Assignment failed');
            }
        } catch (error) {
            this.showAlert('error', 'Assignment failed. Please try again.');
        }
    }

    /**
     * Handle bulk assignment
     */
    async handleBulkAssignment() {
        const lecturerId = $(this.selectors.lecturerSelect).val();

        if (!lecturerId) {
            this.showAlert('warning', 'Please select an HOD for bulk assignment');
            return;
        }

        if (this.state.selectedDepartments.size === 0) {
            this.showAlert('warning', 'Please select at least one department');
            return;
        }

        if (!confirm(`Assign HOD to ${this.state.selectedDepartments.size} department(s)?`)) {
            return;
        }

        this.showLoading();
        let successCount = 0;
        let errorCount = 0;

        for (const deptId of this.state.selectedDepartments) {
            try {
                const response = await this.apiCall('assign_hod', {
                    department_id: deptId,
                    hod_id: lecturerId
                });

                if (response.status === 'success') {
                    successCount++;
                } else {
                    errorCount++;
                }
            } catch (error) {
                errorCount++;
            }
        }

        this.hideLoading();

        if (errorCount === 0) {
            this.showAlert('success', `Successfully assigned HOD to ${successCount} department(s)`);
        } else {
            this.showAlert('warning', `Assigned to ${successCount} department(s), ${errorCount} failed`);
        }

        await this.loadInitialData();
        this.disableBulkMode();
    }

    /**
     * Confirm assignment action
     */
    async confirmAssignment(departmentId, lecturerId) {
        const department = this.state.departments.find(d => d.id == departmentId);
        if (!department) return false;

        const isInvalid = department.hod_id && (!department.hod_name || department.hod_name === 'Not Assigned');

        if (isInvalid && !lecturerId) {
            return confirm('This department has an invalid HOD assignment. Removing the HOD will clear this invalid reference. Continue?');
        }

        return true;
    }

    /**
     * Handle department selection change
     */
    handleDepartmentChange() {
        const deptId = $(this.selectors.departmentSelect).val();
        const department = this.state.departments.find(d => d.id == deptId);

        if (department && department.hod_id) {
            $('#currentAssignmentInfo').show();
            $('#currentHodName').text(department.hod_name || 'Invalid Assignment');
            $(this.selectors.lecturerSelect).val(department.hod_id);
        } else {
            $('#currentAssignmentInfo').hide();
            $(this.selectors.lecturerSelect).val('');
        }

        this.updateAssignmentPreview();
        this.validateForm();
    }

    /**
     * Handle lecturer selection change
     */
    handleLecturerChange() {
        this.updateAssignmentPreview();
        this.validateForm();
    }

    /**
     * Update assignment preview
     */
    updateAssignmentPreview() {
        const deptId = $(this.selectors.departmentSelect).val();
        const lecturerId = $(this.selectors.lecturerSelect).val();

        if (!deptId) {
            $('#assignmentPreview').hide();
            return;
        }

        const department = this.state.departments.find(d => d.id == deptId);
        const lecturer = this.state.lecturers.find(l => l.id == lecturerId);

        let previewHtml = `
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-building me-2"></i>Department</h6>
                    <p class="mb-1"><strong>${department?.name || 'Unknown'}</strong></p>
                </div>
                <div class="col-md-6">
                    <h6><i class="fas fa-user-graduate me-2"></i>HOD Assignment</h6>
                    <p class="mb-1"><strong>${lecturer?.full_name || 'No HOD'}</strong></p>
                </div>
            </div>
        `;

        $('#previewText').html(previewHtml);
        $('#assignmentPreview').show();
    }

    /**
     * Show assignment preview
     */
    showAssignmentPreview() {
        this.updateAssignmentPreview();
    }

    /**
     * Filter departments based on search
     */
    filterDepartments(searchTerm) {
        const options = $(this.selectors.departmentSelect).find('option');
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

        this.updateSearchFeedback('departmentSelectFeedback', searchTerm, visibleCount, 'department');
    }

    /**
     * Filter lecturers based on search
     */
    filterLecturers(searchTerm) {
        const options = $(this.selectors.lecturerSelect).find('option');
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

        this.updateSearchFeedback('lecturerSelectFeedback', searchTerm, visibleCount, 'lecturer');
    }

    /**
     * Update search feedback
     */
    updateSearchFeedback(elementId, searchTerm, visibleCount, type) {
        const feedback = $(`#${elementId}`);

        if (searchTerm) {
            if (visibleCount === 0) {
                feedback.html(`<i class="fas fa-exclamation-triangle me-1"></i>No ${type}s found matching "<strong>${searchTerm}</strong>"`)
                    .removeClass('text-success').addClass('text-warning');
            } else {
                feedback.html(`<i class="fas fa-check me-1"></i>Found ${visibleCount} ${type}${visibleCount !== 1 ? 's' : ''} matching "<strong>${searchTerm}</strong>"`)
                    .removeClass('text-warning').addClass('text-success');
            }
        } else {
            feedback.text('').removeClass('text-success text-warning');
        }
    }

    /**
     * Render departments in select dropdown
     */
    renderDepartments() {
        const select = $(this.selectors.departmentSelect);
        select.empty().append('<option value="">-- Select Department --</option>');

        if (this.state.departments.length > 0) {
            this.state.departments.forEach(dept => {
                const hodInfo = dept.hod_name ? ` (Current HOD: ${dept.hod_name})` : '';
                const isInvalid = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');

                select.append(`
                    <option value="${dept.id}"
                            data-hod="${dept.hod_id || ''}"
                            data-hod-name="${dept.hod_name || ''}"
                            data-invalid="${isInvalid}">
                        ${dept.name}${hodInfo}
                    </option>
                `);
            });
        } else {
            select.append('<option value="" disabled>No departments available</option>');
        }
    }

    /**
     * Render lecturers in select dropdown
     */
    renderLecturers() {
        const select = $(this.selectors.lecturerSelect);
        select.empty().append('<option value="">-- Select Lecturer --</option>');

        if (this.state.lecturers.length > 0) {
            this.state.lecturers.forEach(lecturer => {
                const displayName = lecturer.full_name || `${lecturer.first_name} ${lecturer.last_name}`;
                select.append(`<option value="${lecturer.id}">${displayName}</option>`);
            });
        } else {
            select.append('<option value="" disabled>No lecturers available</option>');
        }
    }

    /**
     * Render department assignment cards
     */
    renderAssignments() {
        const container = $(this.selectors.assignmentsContainer);
        const filteredDepts = this.filterAssignmentsByStatus();

        if (filteredDepts.length === 0) {
            container.html(`
                <div class="col-12 text-center py-5">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Departments Found</h5>
                    <p class="text-muted">No departments match the current filter.</p>
                </div>
            `);
            return;
        }

        let html = '';
        filteredDepts.forEach(dept => {
            const hasInvalidAssignment = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');
            const cardClass = hasInvalidAssignment ? 'assignment-card invalid' :
                            (dept.hod_id ? 'assignment-card assigned' : 'assignment-card unassigned');
            const statusIcon = hasInvalidAssignment ? 'fas fa-exclamation-circle text-danger' :
                              (dept.hod_id ? 'fas fa-user-check text-success' : 'fas fa-exclamation-triangle text-warning');
            const statusText = hasInvalidAssignment ? 'Invalid' : (dept.hod_id ? 'Assigned' : 'Unassigned');
            const statusColor = hasInvalidAssignment ? 'danger' : (dept.hod_id ? 'success' : 'warning');
            const isSelected = this.state.selectedDepartments.has(dept.id.toString());

            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card ${cardClass} h-100 department-card ${isSelected ? 'selected' : ''}"
                         data-department-id="${dept.id}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6 class="card-title text-primary mb-0">${dept.name}</h6>
                                <span class="badge bg-${statusColor}">
                                    <i class="${statusIcon} me-1"></i>${statusText}
                                </span>
                            </div>

                            <div class="mb-3">
                                <small class="text-muted d-block">Current HOD:</small>
                                <div class="${dept.hod_id ? (hasInvalidAssignment ? 'text-danger' : 'text-success') : 'text-warning'} fw-semibold">
                                    ${hasInvalidAssignment ? 'INVALID ASSIGNMENT' : (dept.hod_name || 'Not Assigned')}
                                </div>
                            </div>

                            <button class="btn btn-sm ${hasInvalidAssignment ? 'btn-outline-danger' : 'btn-outline-primary'} w-100 select-department-btn"
                                    data-id="${dept.id}"
                                    data-name="${dept.name}"
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
        });

        container.html(html);
        this.setupAssignmentCardInteractions();
    }

    /**
     * Filter assignments by status
     */
    filterAssignmentsByStatus() {
        const status = $(this.selectors.statusFilter).val();

        if (status === 'all') return this.state.departments;

        return this.state.departments.filter(dept => {
            const hasInvalidAssignment = dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned');

            switch (status) {
                case 'assigned': return dept.hod_id && !hasInvalidAssignment;
                case 'unassigned': return !dept.hod_id;
                case 'invalid': return hasInvalidAssignment;
                default: return true;
            }
        });
    }

    /**
     * Filter assignments (called from UI)
     */
    filterAssignments() {
        this.renderAssignments();
    }

    /**
     * Setup assignment card interactions
     */
    setupAssignmentCardInteractions() {
        $('.select-department-btn').on('click', (e) => {
            const btn = $(e.currentTarget);
            const deptData = {
                id: btn.data('id'),
                name: btn.data('name'),
                hod: btn.data('hod'),
                hodName: btn.data('hod-name'),
                invalid: btn.data('invalid')
            };

            this.handleDepartmentCardClick(deptData, btn);
        });

        // Hover effects
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

    /**
     * Handle department card click
     */
    handleDepartmentCardClick(deptData, btn) {
        if (this.state.isBulkMode) {
            // Toggle selection in bulk mode
            const deptId = deptData.id.toString();
            if (this.state.selectedDepartments.has(deptId)) {
                this.state.selectedDepartments.delete(deptId);
                btn.closest('.department-card').removeClass('selected');
            } else {
                this.state.selectedDepartments.add(deptId);
                btn.closest('.department-card').addClass('selected');
            }
            this.updateBulkModeUI();
        } else {
            // Single selection mode
            $('.department-card').removeClass('selected');
            btn.closest('.department-card').addClass('selected');

            $(this.selectors.departmentSelect).val(deptData.id);
            $(this.selectors.lecturerSelect).val(deptData.hod || '');

            if (deptData.hod && deptData.hodName && !deptData.invalid) {
                $('#currentAssignmentInfo').show();
                $('#currentHodName').text(deptData.hodName);
            } else if (deptData.invalid) {
                $('#currentAssignmentInfo').hide();
                this.showAlert('warning', `Department <strong>${deptData.name}</strong> has an invalid HOD assignment that needs to be fixed.`);
            } else {
                $('#currentAssignmentInfo').hide();
            }

            // Scroll to form
            $('html, body').animate({
                scrollTop: $(this.selectors.form).offset().top - 100
            }, 500);

            this.validateForm();
        }
    }

    /**
     * Update statistics display
     */
    updateStatistics(stats) {
        if (!stats) return;

        this.animateValue('totalDepartments', stats.total_departments || 0);
        this.animateValue('assignedDepartments', stats.assigned_departments || 0);
        this.animateValue('totalLecturers', stats.total_lecturers || 0);
        this.animateValue('unassignedDepartments', stats.unassigned_departments || 0);
    }

    /**
     * Load assignments data
     */
    async loadAssignments() {
        try {
            const response = await this.apiCall('get_departments');
            if (response.status === 'success') {
                this.state.departments = response.data || [];
                this.renderAssignments();
                this.checkDataIntegrity();
            }
        } catch (error) {
            this.showAlert('error', 'Failed to load assignments');
        }
    }

    /**
     * Check data integrity
     */
    checkDataIntegrity() {
        const invalidAssignments = this.state.departments.filter(dept =>
            dept.hod_id && (!dept.hod_name || dept.hod_name === 'Not Assigned')
        );

        if (invalidAssignments.length > 0) {
            const departmentNames = invalidAssignments.map(dept => dept.name).join(', ');
            const alertHtml = `
                <div class="alert alert-warning alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Data Integrity Issue:</strong> Found ${invalidAssignments.length} department(s) with invalid HOD assignments.
                    <br><small class="text-muted">Affected departments: ${departmentNames}</small>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $(this.selectors.alertContainer).append(alertHtml);
            $('#fixInvalidBtn').show();
        } else {
            $('#fixInvalidBtn').hide();
        }
    }

    /**
     * Validate form
     */
    validateForm() {
        const departmentId = $(this.selectors.departmentSelect).val();
        const lecturerId = $(this.selectors.lecturerSelect).val();
        let isValid = true;

        // Validate department selection
        if (departmentId) {
            $(this.selectors.departmentSelect).removeClass('is-invalid').addClass('is-valid');
            $('#departmentSelectFeedback')
                .html('<i class="fas fa-check me-1"></i>Department selected successfully')
                .removeClass('text-warning text-danger').addClass('text-success');
        } else {
            $(this.selectors.departmentSelect).removeClass('is-valid').addClass('is-invalid');
            $('#departmentSelectFeedback')
                .html('<i class="fas fa-exclamation-triangle me-1"></i>Please select a department')
                .removeClass('text-success text-warning').addClass('text-danger');
            isValid = false;
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

    /**
     * Reset form
     */
    resetForm() {
        $(this.selectors.form)[0].reset();
        $('#currentAssignmentInfo').hide();
        $('#assignmentPreview').hide();
        $(this.selectors.departmentSearch).val('');
        $(this.selectors.lecturerSearch).val('');
        $('.department-card').removeClass('selected');
        this.filterDepartments('');
        this.filterLecturers('');
        this.state.selectedDepartments.clear();

        if (this.state.isBulkMode) {
            this.disableBulkMode();
        }
    }

    /**
     * Toggle bulk mode
     */
    toggleBulkMode() {
        if (this.state.isBulkMode) {
            this.disableBulkMode();
        } else {
            this.enableBulkMode();
        }
    }

    /**
     * Enable bulk mode
     */
    enableBulkMode() {
        this.state.isBulkMode = true;
        $('#bulkModeBtn').removeClass('btn-outline-success').addClass('btn-success')
            .html('<i class="fas fa-layer-group me-2"></i>Bulk Mode Active');
        $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign to Selected');
        this.showAlert('info', 'Bulk mode activated. Select multiple departments and choose an HOD to assign to all selected departments.', 0);
    }

    /**
     * Disable bulk mode
     */
    disableBulkMode() {
        this.state.isBulkMode = false;
        this.state.selectedDepartments.clear();
        $('#bulkModeBtn').removeClass('btn-success').addClass('btn-outline-success')
            .html('<i class="fas fa-layer-group me-2"></i>Bulk Mode');
        $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign HOD');
        $('.department-card').removeClass('selected');
        this.showAlert('info', 'Bulk mode disabled.');
    }

    /**
     * Update bulk mode UI
     */
    updateBulkModeUI() {
        const selectedCount = this.state.selectedDepartments.size;
        if (selectedCount > 0) {
            $('#assignBtn').html(`<i class="fas fa-save me-2"></i>Assign to ${selectedCount} Departments`);
        } else {
            $('#assignBtn').html('<i class="fas fa-save me-2"></i>Assign to Selected');
        }
    }

    /**
     * API call wrapper with error handling and retries
     */
    async apiCall(action, data = {}) {
        const url = `assign-hod.php?ajax=1&action=${action}`;

        for (let attempt = 1; attempt <= this.config.retryAttempts; attempt++) {
            try {
                const response = await $.ajax({
                    url: url,
                    method: 'POST',
                    data: { ...data, csrf_token: this.state.csrfToken },
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (response.status === 'error' && response.error_code === 'DB_ERROR') {
                    throw new Error('Database error occurred');
                }

                return response;
            } catch (error) {
                console.error(`API call attempt ${attempt} failed:`, error);

                if (attempt === this.config.retryAttempts) {
                    throw error;
                }

                // Wait before retry
                await new Promise(resolve => setTimeout(resolve, this.config.retryDelay * attempt));
            }
        }
    }

    /**
     * Show alert message
     */
    showAlert(type, message, duration = 5000) {
        const icon = type === 'success' ? 'check-circle' :
                     type === 'error' ? 'exclamation-triangle' :
                     type === 'warning' ? 'exclamation-circle' : 'info-circle';

        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas fa-${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        $(this.selectors.alertContainer).html(alertHtml);

        if (duration > 0) {
            setTimeout(() => $('.alert').alert('close'), duration);
        }
    }

    /**
     * Show loading overlay
     */
    showLoading() {
        this.state.isLoading = true;
        $(this.selectors.loadingOverlay).fadeIn();
    }

    /**
     * Hide loading overlay
     */
    hideLoading() {
        this.state.isLoading = false;
        $(this.selectors.loadingOverlay).fadeOut();
    }

    /**
     * Show global loading indicator
     */
    showGlobalLoading() {
        if (!this.state.isLoading) {
            $('#loadingOverlay').fadeIn();
        }
    }

    /**
     * Hide global loading indicator
     */
    hideGlobalLoading() {
        if (!this.state.isLoading) {
            $('#loadingOverlay').fadeOut();
        }
    }

    /**
     * Animate value change
     */
    animateValue(id, newValue, duration = 600) {
        const el = document.getElementById(id);
        if (!el) return;

        const current = parseInt(el.innerText) || 0;
        const startTime = Date.now();

        const update = () => {
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
        };

        requestAnimationFrame(update);
    }

    /**
     * Debounce function
     */
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

    /**
     * Get CSRF token
     */
    getCsrfToken() {
        return $('input[name="csrf_token"]').val() || '';
    }
}

// Initialize when DOM is ready
$(document).ready(() => {
    window.hodManager = new HodAssignmentManager();
});

// Export functions for global access
window.loadData = () => window.hodManager?.loadInitialData();
window.fixInvalidAssignments = () => window.hodManager?.fixInvalidAssignments();
window.exportAssignments = () => window.hodManager?.exportAssignments();
window.showHelp = () => window.hodManager?.showHelp();
window.toggleSidebar = () => window.hodManager?.toggleSidebar();
window.enableBulkMode = () => window.hodManager?.enableBulkMode();
window.toggleBulkMode = () => window.hodManager?.toggleBulkMode();
window.filterAssignments = () => window.hodManager?.filterAssignments();