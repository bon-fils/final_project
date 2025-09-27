/**
 * Enhanced Student Registration System
 * Handles all client-side functionality for student registration
 * Features: Location management, form validation, progress tracking, accessibility
 * Version: 2.0
 */

class StudentRegistration {
    constructor() {
        this.csrfToken = '';
        this.fingerprintCaptured = false;
        this.fingerprintData = null;
        this.fingerprintQuality = 0;
        this.locationCache = {
            districts: new Map(),
            sectors: new Map(),
            cells: new Map()
        };
        this.originalCellOptions = [];
        this.debounceTimer = null;

        this.init();
    }

    /**
     * Initialize the registration system
     */
    init() {
        this.setupEventListeners();
        this.initializeLocationSystem();
        this.initializeFormValidation();
        this.initializeProgressTracking();
        this.initializeAccessibility();
    }

    /**
     * Setup all event listeners
     */
    setupEventListeners() {
        // Location change events
        $('#province').on('change', () => this.handleProvinceChange());
        $('#district').on('change', () => this.handleDistrictChange());
        $('#sector').on('change', () => this.handleSectorChange());
        $('#cell').on('change', () => this.handleCellChange());

        // Cell search
        $('#cellSearch').on('input', this.debounce(this.handleCellSearch.bind(this), 300));

        // Department change
        $('#department').on('change', () => this.handleDepartmentChange());

        // Option/Program change
        $('#option').on('change', () => this.handleOptionChange());

        // Form submission
        $('#registrationForm').on('submit', (e) => this.handleFormSubmission(e));

        // Mobile menu
        $('#mobileMenuToggle').on('click', () => this.toggleMobileMenu());

        // Fingerprint functionality
        $('#captureFingerprintBtn').on('click', () => this.captureFingerprint());
        $('#clearFingerprintBtn').on('click', () => this.clearFingerprint());
        $('#enrollFingerprintBtn').on('click', () => this.enrollFingerprint());

        // Form validation on input
        $('input, select, textarea').on('blur change', (e) => this.validateField(e.target));
    }

    /**
     * Initialize location system
     */
    initializeLocationSystem() {
        this.resetLocationFields();
        console.log('Location system initialized');
    }

    /**
     * Initialize form validation
     */
    initializeFormValidation() {
        this.setupFormValidationRules();
        console.log('Form validation initialized');
    }

    /**
     * Initialize progress tracking
     */
    initializeProgressTracking() {
        this.updateProgress();
        console.log('Progress tracking initialized');
    }

    /**
     * Initialize accessibility features
     */
    initializeAccessibility() {
        this.setupAriaLabels();
        this.setupKeyboardNavigation();
        console.log('Accessibility features initialized');
    }

    // ===== LOCATION MANAGEMENT =====

    /**
     * Handle province selection change
     */
    async handleProvinceChange() {
        const provinceId = $('#province').val();
        this.resetLocationFields('province');

        if (!provinceId) return;

        this.showLocationLoading($('#district'), true);
        await this.loadDistricts(provinceId);
        this.updateProgress();
    }

    /**
     * Handle district selection change
     */
    async handleDistrictChange() {
        const districtId = $('#district').val();
        this.resetLocationFields('district');

        if (!districtId) return;

        this.showLocationLoading($('#sector'), true);
        await this.loadSectors(districtId);
        this.updateProgress();
    }

    /**
     * Handle sector selection change
     */
    async handleSectorChange() {
        const sectorId = $('#sector').val();
        this.resetLocationFields('sector');

        if (!sectorId) return;

        this.showLocationLoading($('#cell'), true);
        await this.loadCells(sectorId);
        this.updateProgress();
    }

    /**
     * Handle cell selection change
     */
    handleCellChange() {
        const cellId = $('#cell').val();

        if (cellId) {
            this.updateProgress();
            const locationInfo = this.getLocationInfo();

            if (locationInfo) {
                const fullAddress = `${locationInfo.cell_name}, ${locationInfo.sector_name}, ${locationInfo.district_name}, ${locationInfo.province_name}`;
                this.showAlert(`✅ Complete location set: ${fullAddress}`, 'success');
                this.updateCellStatistics(cellId);
            }
        }
    }

    /**
     * Handle cell search input
     */
    handleCellSearch() {
        const searchTerm = $('#cellSearch').val().toLowerCase();
        const $cell = $('#cell');
        const currentValue = $cell.val();

        if (!searchTerm) {
            const options = this.originalCellOptions.map(cell =>
                `<option value="${cell.id}">${this.escapeHtml(cell.name)}</option>`
            ).join('');
            $cell.html('<option value="">📍 Select Cell</option>' + options);

            if (currentValue && this.originalCellOptions.some(cell => cell.id == currentValue)) {
                $cell.val(currentValue);
            }
            return;
        }

        const filteredCells = this.originalCellOptions.filter(cell =>
            cell.name.toLowerCase().includes(searchTerm)
        );

        if (filteredCells.length === 0) {
            $cell.html('<option value="">🔍 No cells found</option>');
        } else {
            const options = filteredCells.map(cell =>
                `<option value="${cell.id}">${this.escapeHtml(cell.name)}</option>`
            ).join('');
            $cell.html('<option value="">📍 Select Cell</option>' + options);

            if (currentValue && filteredCells.some(cell => cell.id == currentValue)) {
                $cell.val(currentValue);
            }
        }
    }

    /**
     * Load districts for selected province
     */
    async loadDistricts(provinceId) {
        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_districts',
                    province_id: provinceId,
                    csrf_token: this.csrfToken
                }
            });

            if (response.success && response.districts) {
                const options = response.districts.map(d =>
                    `<option value="${d.id}">${d.name}</option>`
                ).join('');

                $('#district').html('<option value="">Select District</option>' + options).prop('disabled', false);
                this.locationCache.districts.set(provinceId, response.districts);

                this.showAlert(`🏛️ ${response.districts.length} districts loaded`, 'success');
            } else {
                throw new Error(response.message || 'No districts found');
            }
        } catch (error) {
            console.error('District loading error:', error);
            $('#district').html('<option value="">❌ Failed to load districts</option>');
            this.showAlert('❌ Failed to load districts. Please try selecting the province again.', 'error');
        } finally {
            this.showLocationLoading($('#district'), false);
        }
    }

    /**
     * Load sectors for selected district
     */
    async loadSectors(districtId) {
        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_sectors',
                    district_id: districtId,
                    csrf_token: this.csrfToken
                }
            });

            if (response.success && response.sectors) {
                const options = response.sectors.map(s =>
                    `<option value="${s.id}">${s.name}</option>`
                ).join('');

                $('#sector').html('<option value="">Select Sector</option>' + options).prop('disabled', false);
                this.locationCache.sectors.set(districtId, response.sectors);

                this.showAlert(`🏘️ ${response.sectors.length} sectors loaded`, 'success');
            } else {
                throw new Error(response.message || 'No sectors found');
            }
        } catch (error) {
            console.error('Sector loading error:', error);
            $('#sector').html('<option value="">❌ Failed to load sectors</option>');
            this.showAlert('❌ Failed to load sectors. Please try selecting the district again.', 'error');
        } finally {
            this.showLocationLoading($('#sector'), false);
        }
    }

    /**
     * Load cells for selected sector
     */
    async loadCells(sectorId) {
        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_cells',
                    sector_id: sectorId,
                    csrf_token: this.csrfToken
                }
            });

            if (response.success && response.cells) {
                this.populateCells(response.cells);
                this.locationCache.cells.set(sectorId, response.cells);

                this.showAlert(`🏘️ ${response.cells.length} cells loaded for ${$('#sector option:selected').text()}`, 'success');
            } else {
                throw new Error(response.message || 'No cells found');
            }
        } catch (error) {
            console.error('Cell loading error:', error);
            $('#cell').html('<option value="">❌ Failed to load cells</option>');
            this.showAlert('❌ Failed to load cells. Please try selecting the sector again.', 'error');
        } finally {
            this.showLocationLoading($('#cell'), false);
        }
    }

    /**
     * Populate cells dropdown with search functionality
     */
    populateCells(cells) {
        const $cell = $('#cell');
        const options = cells.map(cell =>
            `<option value="${cell.id}">${this.escapeHtml(cell.name)}</option>`
        ).join('');

        $cell.html('<option value="">📍 Select Cell</option>' + options).prop('disabled', false);
        this.originalCellOptions = cells;

        if (cells.length > 5) {
            $('#cellSearchContainer').show();
            $('#cellSearch').val('');
        } else {
            $('#cellSearchContainer').hide();
        }
    }

    /**
     * Reset location fields from specified level
     */
    resetLocationFields(fromLevel = 'province') {
        const levels = ['district', 'sector', 'cell'];

        levels.forEach(level => {
            if (levels.indexOf(fromLevel) <= levels.indexOf(level)) {
                const $field = $(`#${level}`);
                $field.prop('disabled', true).val('');

                if (level === 'district') {
                    $field.html('<option value="">Select Province First</option>');
                } else if (level === 'sector') {
                    $field.html('<option value="">Select District First</option>');
                } else if (level === 'cell') {
                    $field.html('<option value="">Select Sector First</option>');
                    $('#cellSearchContainer').hide();
                    $('#cellSearch').val('');
                }
            }
        });
    }

    /**
     * Get complete location information
     */
    getLocationInfo() {
        const provinceId = $('#province').val();
        const districtId = $('#district').val();
        const sectorId = $('#sector').val();
        const cellId = $('#cell').val();

        if (!provinceId || !districtId || !sectorId || !cellId) {
            return null;
        }

        return {
            province_id: provinceId,
            province_name: $('#province option:selected').text(),
            district_id: districtId,
            district_name: $('#district option:selected').text(),
            sector_id: sectorId,
            sector_name: $('#sector option:selected').text(),
            cell_id: cellId,
            cell_name: $('#cell option:selected').text()
        };
    }

    // ===== ACADEMIC MANAGEMENT =====

    /**
     * Handle department selection change
     */
    async handleDepartmentChange() {
        const departmentId = $('#department').val();
        this.resetAcademicFields();

        if (!departmentId) return;

        $('#programLoadingIcon').removeClass('d-none');
        await this.loadPrograms(departmentId);
    }

    /**
     * Handle program/option selection change
     */
    handleOptionChange() {
        const optionId = $('#option').val();
        if (optionId) {
            this.updateProgress();
            this.showAlert(`📚 Program selected: ${$('#option option:selected').text()}`, 'success');
        }
    }

    /**
     * Load programs for selected department
     */
    async loadPrograms(departmentId) {
        try {
            const response = await this.retryableAjax({
                url: 'api/department-option-api.php',
                method: 'POST',
                data: {
                    action: 'get_options',
                    department_id: departmentId,
                    csrf_token: this.csrfToken
                }
            });

            if (response.success && response.data) {
                const options = response.data.map(option =>
                    `<option value="${option.id}" data-department="${departmentId}">${option.name}</option>`
                ).join('');

                $('#option').html('<option value="">🎯 Select Your Program</option>' + options).prop('disabled', false);
                $('#programLoadedIcon').removeClass('d-none');
                $('#programCount').removeClass('d-none');
                $('#programCountText').text(`${response.data.length} programs available`);

                this.showAlert(`📚 ${response.data.length} programs loaded for ${$('#department option:selected').text()}`, 'success');
            } else {
                $('#option').html('<option value="">❌ No programs available</option>');
                this.showAlert('❌ No programs found for selected department', 'warning');
            }
        } catch (error) {
            console.error('Program loading error:', error);
            $('#option').html('<option value="">❌ Failed to load programs</option>');
            this.showAlert('❌ Failed to load programs. Please try selecting the department again.', 'error');
        } finally {
            $('#programLoadingIcon').addClass('d-none');
        }
    }

    /**
     * Reset academic fields
     */
    resetAcademicFields() {
        $('#option').prop('disabled', true).val('').html('<option value="">Select Department First</option>');
        $('#programLoadedIcon').addClass('d-none');
        $('#programCount').addClass('d-none');
    }

    // ===== FORM VALIDATION =====

    /**
     * Setup form validation rules
     */
    setupFormValidationRules() {
        // Add custom validation methods if needed
        this.setupRealTimeValidation();
    }

    /**
     * Setup real-time validation
     */
    setupRealTimeValidation() {
        // Email validation
        $('#email').on('blur', () => {
            const email = $('#email').val();
            if (email && !this.isValidEmail(email)) {
                this.showFieldError($('#email')[0], 'Please enter a valid email address');
            } else {
                this.clearFieldError($('#email')[0]);
            }
        });

        // Phone validation
        $('#telephone').on('blur', () => {
            const phone = $('#telephone').val();
            if (phone && !this.isValidPhone(phone)) {
                this.showFieldError($('#telephone')[0], 'Please enter a valid phone number (e.g., 0781234567)');
            } else {
                this.clearFieldError($('#telephone')[0]);
            }
        });
    }

    /**
     * Validate entire form
     */
    validateForm() {
        let isValid = true;
        const errors = [];

        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();

        // Required field validation
        const requiredFields = [
            { id: 'firstName', name: 'First Name' },
            { id: 'lastName', name: 'Last Name' },
            { id: 'email', name: 'Email Address' },
            { id: 'telephone', name: 'Phone Number' },
            { id: 'reg_no', name: 'Registration Number' },
            { id: 'department', name: 'Department' },
            { id: 'option', name: 'Program' },
            { id: 'year_level', name: 'Year Level' },
            { id: 'sex', name: 'Gender' }
        ];

        requiredFields.forEach(field => {
            const $field = $(`#${field.id}`);
            if (!$field.val().trim()) {
                this.showFieldError($field[0], `${field.name} is required`);
                isValid = false;
                errors.push(`${field.name} is required`);
            }
        });

        // Department-program dependency validation
        const departmentId = $('#department').val();
        const optionId = $('#option').val();
        if (departmentId && !optionId) {
            const $option = $('#option');
            this.showFieldError($option[0], 'Please select a program for the chosen department');
            isValid = false;
            errors.push('Program selection is required');
        }

        // Validate selected option belongs to selected department
        if (departmentId && optionId) {
            const $optionElement = $('#option');
            const selectedOption = $optionElement.find('option:selected');
            const optionDepartmentId = selectedOption.data('department');

            if (optionDepartmentId && optionDepartmentId != departmentId) {
                const $option = $('#option');
                this.showFieldError($option[0], 'Selected program does not belong to the chosen department');
                isValid = false;
                errors.push('Invalid program for department');
            } else {
                this.clearFieldError($('#option')[0]);
            }
        }

        // Email format validation
        const email = $('#email').val();
        if (email && !this.isValidEmail(email)) {
            this.showFieldError($('#email')[0], 'Please enter a valid email address');
            isValid = false;
            errors.push('Invalid email format');
        }

        // Phone number validation
        const phone = $('#telephone').val();
        if (phone && !this.isValidPhone(phone)) {
            this.showFieldError($('#telephone')[0], 'Please enter a valid phone number (e.g., 0781234567)');
            isValid = false;
            errors.push('Invalid phone number format');
        }

        // Parent contact validation if provided
        const parentContact = $('#parent_contact').val();
        if (parentContact && !this.isValidPhone(parentContact)) {
            this.showFieldError($('#parent_contact')[0], 'Please enter a valid parent phone number');
            isValid = false;
            errors.push('Invalid parent phone number format');
        }

        // Registration number validation
        const regNo = $('#reg_no').val();
        if (regNo && regNo.length < 5) {
            this.showFieldError($('#reg_no')[0], 'Registration number must be at least 5 characters');
            isValid = false;
            errors.push('Registration number too short');
        }

        // Date of birth validation
        const dob = $('#dob').val();
        if (dob) {
            const birthDate = new Date(dob);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();

            if (age < 16) {
                this.showFieldError($('#dob')[0], 'Student must be at least 16 years old');
                isValid = false;
                errors.push('Student too young');
            } else if (age > 60) {
                this.showFieldError($('#dob')[0], 'Please enter a valid date of birth');
                isValid = false;
                errors.push('Invalid date of birth');
            }
        }

        // Location validation
        if (!this.validateLocation()) {
            isValid = false;
            errors.push('Location information is incomplete');
        }

        if (!isValid) {
            console.log('Form validation failed:', errors);
        }

        return isValid;
    }

    /**
     * Validate location completeness
     */
    validateLocation() {
        const provinceId = $('#province').val();
        const districtId = $('#district').val();
        const sectorId = $('#sector').val();
        const cellId = $('#cell').val();

        return provinceId && districtId && sectorId && cellId;
    }

    /**
     * Validate single field
     */
    validateField(field) {
        const $field = $(field);
        const fieldId = field.id;
        const value = $field.val();

        // Clear previous errors
        this.clearFieldError(field);

        // Required field check
        if ($field.prop('required') && !value.trim()) {
            this.showFieldError(field, `${$field.attr('aria-label') || fieldId} is required`);
            return false;
        }

        // Email validation
        if (fieldId === 'email' && value && !this.isValidEmail(value)) {
            this.showFieldError(field, 'Please enter a valid email address');
            return false;
        }

        // Phone validation
        if ((fieldId === 'telephone' || fieldId === 'parent_contact') && value && !this.isValidPhone(value)) {
            this.showFieldError(field, 'Please enter a valid phone number (e.g., 0781234567)');
            return false;
        }

        return true;
    }

    // ===== FORM SUBMISSION =====

    /**
     * Handle form submission
     */
    async handleFormSubmission(e) {
        e.preventDefault();

        if (!this.validateForm()) {
            this.showAlert('Please correct the errors in the form before submitting.', 'danger');
            return;
        }

        this.disableForm(true);
        this.showAlert('Submitting registration...', 'info');

        try {
            const formData = new FormData(e.target);

            // Add location data
            const locationInfo = this.getLocationInfo();
            if (locationInfo) {
                formData.append('province_id', locationInfo.province_id);
                formData.append('district_id', locationInfo.district_id);
                formData.append('sector_id', locationInfo.sector_id);
                formData.append('cell_id', locationInfo.cell_id);
            }

            // Add fingerprint data if captured
            if (this.fingerprintCaptured && this.fingerprintData) {
                formData.append('fingerprint_data', this.fingerprintData);
                formData.append('fingerprint_quality', this.fingerprintQuality);
            }

            const response = await this.retryableAjax({
                url: 'submit-student-registration.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            });

            if (response.success) {
                this.showAlert('🎉 Registration successful! Welcome to Rwanda Polytechnic.', 'success');
                setTimeout(() => {
                    window.location.href = 'login.php?registered=1';
                }, 2000);
            } else {
                throw new Error(response.message || 'Registration failed');
            }

        } catch (error) {
            console.error('Submission error:', error);
            this.handleSubmissionError(error);
        } finally {
            this.disableForm(false);
        }
    }

    /**
     * Handle submission error
     */
    handleSubmissionError(error) {
        console.error('Submission error details:', error);

        let errorMessage = 'An unexpected error occurred during registration.';

        if (error.responseJSON && error.responseJSON.message) {
            errorMessage = error.responseJSON.message;
        } else if (error.responseJSON && error.responseJSON.errors) {
            const errors = Object.values(error.responseJSON.errors).flat();
            errorMessage = errors.join('; ');
        } else if (typeof error === 'string') {
            errorMessage = error;
        }

        this.showAlert(`Registration failed: ${errorMessage}`, 'error', false);
        this.disableForm(false);
    }

    // ===== PROGRESS TRACKING =====

    /**
     * Update form progress
     */
    updateProgress() {
        const totalFields = 9; // Required fields count
        let completedFields = 0;

        // Check required fields
        const requiredFields = ['firstName', 'lastName', 'email', 'telephone', 'reg_no', 'department', 'option', 'year_level', 'sex'];
        requiredFields.forEach(fieldId => {
            if ($(`#${fieldId}`).val().trim()) {
                completedFields++;
            }
        });

        // Check location completeness
        if (this.validateLocation()) {
            completedFields++;
        }

        const progress = Math.round((completedFields / totalFields) * 100);
        $('#formProgress').css('width', `${progress}%`);
        $('#progressText').text(`${progress}% complete`);
    }

    // ===== UTILITY METHODS =====

    /**
     * Show alert message
     */
    showAlert(message, type = 'info', autoDismiss = true) {
        const alertClass = type === 'success' ? 'alert-success' :
                          type === 'error' ? 'alert-danger' :
                          type === 'warning' ? 'alert-warning' : 'alert-info';

        const icon = type === 'success' ? 'check-circle' :
                    type === 'error' ? 'exclamation-triangle' :
                    type === 'warning' ? 'exclamation-circle' : 'info-circle';

        const alertHtml = `
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas fa-${icon} me-2"></i>
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;

        $('#alertContainer').html(alertHtml);

        if (autoDismiss) {
            const dismissTime = type === 'error' ? 8000 : type === 'warning' ? 6000 : 5000;
            setTimeout(() => {
                $('.alert').fadeOut(300, function() {
                    $(this).remove();
                });
            }, dismissTime);
        }

        console.log(`Alert [${type.toUpperCase()}]: ${message}`);
    }

    /**
     * Show field error
     */
    showFieldError(field, message) {
        const $field = $(field);
        $field.addClass('is-invalid');

        if (!$field.next('.invalid-feedback').length) {
            $field.after(`<div class="invalid-feedback">${message}</div>`);
        }
    }

    /**
     * Clear field error
     */
    clearFieldError(field) {
        const $field = $(field);
        $field.removeClass('is-invalid');
        $field.next('.invalid-feedback').remove();
    }

    /**
     * Disable/enable form
     */
    disableForm(disabled) {
        const $form = $('#registrationForm');
        const $submitBtn = $form.find('button[type="submit"]');

        $form.find('input, select, textarea, button').prop('disabled', disabled);

        if (disabled) {
            $submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Submitting...');
        } else {
            $submitBtn.html('<i class="fas fa-save me-2"></i>Complete Registration');
        }
    }

    /**
     * Show location loading state
     */
    showLocationLoading($element, show) {
        if (show) {
            $element.prop('disabled', true).html('<option value="">Loading...</option>');
        }
    }

    /**
     * Update cell statistics (placeholder for future use)
     */
    updateCellStatistics(cellId) {
        // Could implement cell population statistics here
        console.log('Cell selected:', cellId);
    }

    /**
     * Toggle mobile menu
     */
    toggleMobileMenu() {
        $('#adminSidebar').toggleClass('show');
        $('#mainContent').toggleClass('sidebar-open');
    }

    /**
     * Setup ARIA labels and accessibility
     */
    setupAriaLabels() {
        // Add proper ARIA labels where needed
        $('input, select, textarea').each(function() {
            if (!$(this).attr('aria-label') && !$(this).attr('aria-labelledby')) {
                const label = $(this).prev('label').text() || $(this).attr('placeholder') || this.id;
                if (label) {
                    $(this).attr('aria-label', label);
                }
            }
        });
    }

    /**
     * Setup keyboard navigation
     */
    setupKeyboardNavigation() {
        // Add keyboard navigation support
        $(document).on('keydown', (e) => {
            if (e.key === 'Escape') {
                // Close modals or clear selections
                $('.modal').modal('hide');
            }
        });
    }

    /**
     * Debounce function for input handling
     */
    debounce(func, wait) {
        return (...args) => {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => func.apply(this, args), wait);
        };
    }

    /**
     * Retryable AJAX with exponential backoff
     */
    async retryableAjax(options, maxRetries = 3) {
        for (let attempt = 1; attempt <= maxRetries; attempt++) {
            try {
                return await $.ajax(options);
            } catch (error) {
                if (attempt === maxRetries || error.status < 500) {
                    throw error;
                }

                // Wait before retrying (exponential backoff)
                await new Promise(resolve => setTimeout(resolve, Math.pow(2, attempt) * 1000));
            }
        }
    }

    /**
     * Email validation
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Phone validation
     */
    isValidPhone(phone) {
        // Rwanda phone number validation (basic)
        const phoneRegex = /^(\+250|0)[7-8][0-9]{7}$/;
        return phoneRegex.test(phone.replace(/\s/g, ''));
    }

    /**
     * HTML escape
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ===== FINGERPRINT FUNCTIONALITY =====

    /**
     * Capture fingerprint
     */
    async captureFingerprint() {
        // Placeholder for fingerprint capture functionality
        this.updateFingerprintUI('capturing');

        // Simulate capture process
        setTimeout(() => {
            this.fingerprintCaptured = true;
            this.fingerprintQuality = Math.floor(Math.random() * 40) + 60; // 60-100%
            this.updateFingerprintUI('captured');
            this.showAlert(`Fingerprint captured with ${this.fingerprintQuality}% quality`, 'success');
        }, 2000);
    }

    /**
     * Clear fingerprint
     */
    clearFingerprint() {
        const canvas = document.getElementById('fingerprintCanvas');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        const qualityIndicator = document.querySelector('.quality-indicator');

        if (canvas) {
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.classList.add('d-none');
        }

        if (placeholder) {
            placeholder.classList.remove('d-none');
        }

        if (qualityIndicator) {
            qualityIndicator.remove();
        }

        this.fingerprintCaptured = false;
        this.fingerprintData = null;
        this.fingerprintQuality = 0;

        this.updateFingerprintUI('ready');
        this.showAlert('Fingerprint cleared', 'info');
    }

    /**
     * Enroll fingerprint
     */
    enrollFingerprint() {
        if (!this.fingerprintCaptured) {
            this.showAlert('No fingerprint captured to enroll', 'warning');
            return;
        }

        // Convert canvas to data URL for storage
        const canvas = document.getElementById('fingerprintCanvas');
        if (canvas) {
            this.fingerprintData = canvas.toDataURL('image/png');
        }

        this.showAlert('Fingerprint enrolled successfully!', 'success');
        console.log('Fingerprint enrolled:', {
            quality: this.fingerprintQuality,
            dataSize: this.fingerprintData ? this.fingerprintData.length : 0
        });
    }

    /**
     * Update fingerprint UI
     */
    updateFingerprintUI(state) {
        const container = document.querySelector('.fingerprint-container');
        const captureBtn = document.getElementById('captureFingerprintBtn');
        const clearBtn = document.getElementById('clearFingerprintBtn');
        const enrollBtn = document.getElementById('enrollFingerprintBtn');
        const status = document.getElementById('fingerprintStatus');

        if (!container || !captureBtn || !status) return;

        container.classList.remove('fingerprint-capturing', 'fingerprint-captured');

        switch (state) {
            case 'capturing':
                container.classList.add('fingerprint-capturing');
                captureBtn.disabled = true;
                captureBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Capturing...';
                status.textContent = 'Place finger on scanner';
                if (clearBtn) clearBtn.classList.add('d-none');
                if (enrollBtn) enrollBtn.classList.add('d-none');
                break;

            case 'captured':
                container.classList.add('fingerprint-captured');
                if (captureBtn) captureBtn.classList.add('d-none');
                if (clearBtn) clearBtn.classList.remove('d-none');
                if (enrollBtn) enrollBtn.classList.remove('d-none');
                status.textContent = `Fingerprint captured - Quality: ${this.fingerprintQuality}%`;
                break;

            default: // ready
                container.classList.remove('fingerprint-capturing', 'fingerprint-captured');
                captureBtn.disabled = false;
                captureBtn.classList.remove('d-none');
                captureBtn.innerHTML = '<i class="fas fa-fingerprint me-2"></i>Capture Fingerprint';
                if (clearBtn) clearBtn.classList.add('d-none');
                if (enrollBtn) enrollBtn.classList.add('d-none');
                status.textContent = 'Ready to capture fingerprint';
        }
    }
}

// Initialize when document is ready
$(document).ready(() => {
    try {
        window.registrationApp = new StudentRegistration();
        console.log('✅ Enhanced Student Registration System initialized successfully');
    } catch (error) {
        console.error('❌ Failed to initialize registration system:', error);
        $('#alertContainer').html(`
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                System initialization failed. Please refresh the page.
            </div>
        `);
    }
});