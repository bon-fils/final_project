/**
 * Enhanced Student Registration System - JavaScript
 * Refined with better error handling and performance
 * External file for better maintainability
 */

class StudentRegistration {
    constructor() {
        this.retryAttempts = 3;
        this.retryDelay = 1000;
        this.csrfToken = null;
        this.fingerprintCaptured = false;
        this.fingerprintData = null;
        this.fingerprintQuality = 0;
        this.isCapturing = false;
        this.selectedFaceImagesCount = 0;

        // Location caching for performance
        this.locationCache = {
            districts: new Map(),
            sectors: new Map(),
            cells: new Map()
        };
        this.originalCellOptions = [];
        this.init();
    }

    init() {
        try {
            // Get CSRF token safely
            this.csrfToken = this.getCsrfToken();

            this.setupEventListeners();
            this.updateProgress();
            this.initializeFormState();
            this.showWelcomeMessage();
            this.setupGlobalErrorHandler();
            this.checkServerConnectivity();
        } catch (error) {
            console.error('Initialization error:', error);
            this.showAlert('System initialization failed. Please refresh the page.', 'error', false);
        }
    }

    getCsrfToken() {
        const csrfInput = document.querySelector('input[name="csrf_token"]');
        if (!csrfInput) {
            console.warn('CSRF token input not found. Some features may not work properly.');
            return null;
        }
        return csrfInput.value;
    }

    initializeFormState() {
        // Set initial form state
        this.updateProgress();

        // Initialize location fields
        this.initializeLocationFields();

        // Pre-validate form on load
        setTimeout(() => {
            this.validateForm();
        }, 1000);

        // Initialize fingerprint UI
        this.updateFingerprintUI('ready');
    }

    initializeLocationFields() {
        // Load provinces on page load
        this.loadProvinces();

        // Add enabled class to province field (always available)
        $('#province').addClass('enabled');

        // Reset location fields to initial state
        this.resetLocationFields('province');
    }

    async loadProvinces() {
        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_provinces',
                    csrf_token: this.csrfToken
                }
            });

            if (response.success && response.provinces) {
                const options = response.provinces.map(province =>
                    `<option value="${province.id}">${this.escapeHtml(province.name)}</option>`
                ).join('');

                $('#province').append(options);
                this.showAlert(`📍 ${response.provinces.length} provinces loaded successfully!`, 'success');
            } else {
                throw new Error(response.message || 'Failed to load provinces');
            }
        } catch (error) {
            console.error('Province loading error:', error);
            $('#province').html('<option value="">❌ Failed to load provinces</option>');
            this.showAlert('❌ Failed to load provinces. Please refresh the page.', 'error');
        }
    }

    async handleProvinceChange() {
        const provinceId = $('#province').val();
        const $district = $('#district');

        // Reset dependent fields
        this.resetLocationFields('district');

        if (!provinceId) {
            return;
        }

        // Check cache first
        if (this.locationCache.districts.has(provinceId)) {
            this.populateDistricts(this.locationCache.districts.get(provinceId));
            return;
        }

        // Show loading state
        $district.prop('disabled', true).html('<option value="">Loading districts...</option>');
        this.showLocationLoading($district, true);

        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_districts',
                    province_id: parseInt(provinceId, 10),
                    csrf_token: this.csrfToken
                }
            });

            if (response.success && response.districts) {
                // Cache the results
                this.locationCache.districts.set(provinceId, response.districts);
                this.populateDistricts(response.districts);

                this.showAlert(`🏛️ ${response.districts.length} district${response.districts.length !== 1 ? 's' : ''} loaded for ${$('#province option:selected').text()}`, 'success');
            } else {
                throw new Error(response.message || 'No districts found');
            }
        } catch (error) {
            console.error('Province change error:', error);
            $district.html('<option value="">❌ Failed to load districts</option>');
            this.showAlert('❌ Failed to load districts. Please try selecting the province again.', 'error');
        } finally {
            this.showLocationLoading($district, false);
            $district.prop('disabled', false);
        }
    }

    populateDistricts(districts) {
        const $district = $('#district');
        const options = districts.map(district =>
            `<option value="${district.id}">${this.escapeHtml(district.name)}</option>`
        ).join('');

        $district.html('<option value="">🏛️ Select District</option>' + options)
                .addClass('enabled');
    }

    resetLocationFields(fromLevel) {
        const levels = ['district', 'sector', 'cell'];

        levels.forEach(level => {
            if (levels.indexOf(fromLevel) <= levels.indexOf(level)) {
                const $field = $(`#${level}`);
                $field.prop('disabled', true).val('').removeClass('enabled');

                if (level === 'district') {
                    $field.html('<option value="">🏛️ Select Province First</option>');
                } else if (level === 'sector') {
                    $field.html('<option value="">🏘️ Select District First</option>');
                } else if (level === 'cell') {
                    $field.html('<option value="">📍 Select Sector First</option>');
                    // Hide cell information and search when resetting
                    $('#cellInfo').hide();
                    $('#cellSearchContainer').hide();
                    $('#cellSearch').val('');
                    this.originalCellOptions = [];
                }

                // Clear cache for dependent levels
                if (level === 'district') {
                    this.locationCache.sectors.clear();
                    this.locationCache.cells.clear();
                } else if (level === 'sector') {
                    this.locationCache.cells.clear();
                }
            }
        });
    }

    showLocationLoading($element, show) {
        const loadingClass = 'location-loading';
        if (show) {
            $element.addClass(loadingClass);
        } else {
            $element.removeClass(loadingClass);
        }
    }

    async handleDistrictChange() {
        const districtId = $('#district').val();
        const $sector = $('#sector');

        // Reset dependent fields
        this.resetLocationFields('sector');

        if (!districtId) {
            return;
        }

        // Check cache first
        if (this.locationCache.sectors.has(districtId)) {
            this.populateSectors(this.locationCache.sectors.get(districtId));
            return;
        }

        // Show loading state
        $sector.prop('disabled', true).html('<option value="">Loading sectors...</option>');
        this.showLocationLoading($sector, true);

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
                // Cache the results
                this.locationCache.sectors.set(districtId, response.sectors);
                this.populateSectors(response.sectors);

                this.showAlert(`🏘️ ${response.sectors.length} sector${response.sectors.length !== 1 ? 's' : ''} loaded for ${$('#district option:selected').text()}`, 'success');
            } else {
                throw new Error(response.message || 'No sectors found');
            }
        } catch (error) {
            console.error('District change error:', error);
            $sector.html('<option value="">❌ Failed to load sectors</option>');
            this.showAlert('❌ Failed to load sectors. Please try selecting the district again.', 'error');
        } finally {
            this.showLocationLoading($sector, false);
            $sector.prop('disabled', false);
        }
    }

    populateSectors(sectors) {
        const $sector = $('#sector');
        const options = sectors.map(sector =>
            `<option value="${sector.id}">${this.escapeHtml(sector.name)}</option>`
        ).join('');

        $sector.html('<option value="">🏘️ Select Sector</option>' + options)
               .addClass('enabled');
    }

    async handleSectorChange() {
        const sectorId = $('#sector').val();
        const $cell = $('#cell');

        // Reset dependent fields
        this.resetLocationFields('cell');

        if (!sectorId) {
            return;
        }

        // Check cache first
        if (this.locationCache.cells.has(sectorId)) {
            this.populateCells(this.locationCache.cells.get(sectorId));
            return;
        }

        // Show loading state
        $cell.prop('disabled', true).html('<option value="">Loading cells...</option>');
        this.showLocationLoading($cell, true);

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
                // Cache the results
                this.locationCache.cells.set(sectorId, response.cells);
                this.populateCells(response.cells);

                this.showAlert(`🏘️ ${response.cells.length} cell${response.cells.length !== 1 ? 's' : ''} loaded for ${$('#sector option:selected').text()}`, 'success');
            } else {
                throw new Error(response.message || 'No cells found');
            }
        } catch (error) {
            console.error('Sector change error:', error);
            $cell.html('<option value="">❌ Failed to load cells</option>');
            this.showAlert('❌ Failed to load cells. Please try selecting the sector again.', 'error');
        } finally {
            this.showLocationLoading($cell, false);
            $cell.prop('disabled', false);
        }
    }

    async handleCellChange() {
        const cellId = $('#cell').val();

        if (cellId) {
            // Update progress when location is complete
            this.updateProgress();

            // Get complete location information
            const locationInfo = this.getLocationInfo();
            if (locationInfo) {
                // Show detailed success message
                const fullAddress = `${locationInfo.cell_name}, ${locationInfo.sector_name}, ${locationInfo.district_name}, ${locationInfo.province_name}`;
                this.showAlert(`✅ Complete location set: ${fullAddress}`, 'success');

                // Update cell statistics if available
                this.updateCellStatistics(cellId);
            }
        }
    }

    updateCellStatistics(cellId) {
        const cellSelect = $('#cell');
        const selectedOption = cellSelect.find('option:selected');
        const cellInfo = $('#cellInfo');
        const cellInfoText = $('#cellInfoText');

        if (selectedOption.length) {
            const cellName = selectedOption.text();
            const sectorName = $('#sector option:selected').text();
            const districtName = $('#district option:selected').text();
            const provinceName = $('#province option:selected').text();

            // Show cell information
            const fullLocation = `${cellName} Cell, ${sectorName} Sector, ${districtName} District, ${provinceName}`;
            cellInfoText.text(`📍 Selected: ${fullLocation}`);
            cellInfo.show();

            // Add visual feedback
            cellSelect.addClass('border-success');
            setTimeout(() => {
                cellSelect.removeClass('border-success');
            }, 2000);

            console.log(`Cell "${cellName}" selected with ID: ${cellId}`);
            console.log(`Complete location: ${fullLocation}`);
        } else {
            cellInfo.hide();
        }
    }

    populateCells(cells) {
        const $cell = $('#cell');

        if (cells.length === 0) {
            $cell.html('<option value="">No cells available for this sector</option>')
                  .addClass('enabled');
            $('#cellSearchContainer').hide();
            this.originalCellOptions = [];
            return;
        }

        const options = cells.map(cell =>
            `<option value="${cell.id}">${this.escapeHtml(cell.name)}</option>`
        ).join('');

        $cell.html('<option value="">📍 Select Cell</option>' + options)
              .addClass('enabled');

        // Store original options for search functionality
        this.originalCellOptions = cells;

        // Show search container if there are many cells
        if (cells.length > 5) {
            $('#cellSearchContainer').show();
            $('#cellSearch').val(''); // Clear search
        } else {
            $('#cellSearchContainer').hide();
        }
    }

    handleCellSearch() {
        const searchTerm = $('#cellSearch').val().toLowerCase();
        const $cell = $('#cell');
        const currentValue = $cell.val(); // Preserve current selection

        if (!searchTerm) {
            // Show all options
            const options = this.originalCellOptions.map(cell =>
                `<option value="${cell.id}">${this.escapeHtml(cell.name)}</option>`
            ).join('');
            $cell.html('<option value="">📍 Select Cell</option>' + options);
            // Restore previous selection if it exists
            if (currentValue) {
                $cell.val(currentValue);
            }
            return;
        }

        // Filter options based on search term
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
            // Restore previous selection if it's in the filtered results
            if (currentValue && filteredCells.some(cell => cell.id == currentValue)) {
                $cell.val(currentValue);
            }
        }
    }

    // Get complete location info
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

    setupGlobalErrorHandler() {
        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            this.showAlert('An unexpected error occurred. Please try again.', 'error');
            event.preventDefault();
        });

        // Handle global JavaScript errors
        window.addEventListener('error', (event) => {
            console.error('Global JavaScript error:', event.error);
            // Don't show alert for minor errors to avoid spam
            if (event.error && !event.error.message.includes('Script error')) {
                this.showAlert('A system error occurred. Please refresh if issues persist.', 'error');
            }
        });
    }

    async checkServerConnectivity() {
        try {
            // Quick connectivity check to a lightweight endpoint
            const response = await fetch('api/location-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=get_provinces&csrf_token=${this.csrfToken}`,
                signal: AbortSignal.timeout(5000) // 5 second timeout
            });

            if (response.ok) {
                console.log('✅ Server connectivity check passed');
            } else {
                console.warn('⚠️ Server connectivity check failed with status:', response.status);
                this.showAlert('⚠️ Server connection may be unstable. Please check your internet connection.', 'warning');
            }
        } catch (error) {
            console.error('❌ Server connectivity check failed:', error);
            this.showAlert('⚠️ Unable to connect to server. Please check your internet connection and try refreshing the page.', 'warning');
        }
    }

    setupEventListeners() {
        // Department change with error handling
        $('#department').on('change', this.debounce(this.handleDepartmentChange.bind(this), 300));

        // Location cascading
        $('#province').on('change', this.debounce(this.handleProvinceChange.bind(this), 300));
        $('#district').on('change', this.debounce(this.handleDistrictChange.bind(this), 300));
        $('#sector').on('change', this.debounce(this.handleSectorChange.bind(this), 300));
        $('#cell').on('change', this.debounce(this.handleCellChange.bind(this), 300));
        $('#cellSearch').on('input', this.debounce(this.handleCellSearch.bind(this), 300));

        // Face images handling
        $('#faceImagesInput').on('change', this.handleFaceImagesSelect.bind(this));
        $('#clearFaceImages').on('click', this.clearFaceImages.bind(this));
        $(document).on('click', '.remove-image', this.removeFaceImage.bind(this));

        // Form submission
        $('#registrationForm').on('submit', this.handleSubmit.bind(this));

        // Real-time validation
        $('input[required]').on('blur', this.validateField.bind(this));
        $('input[required]').on('input', this.debounce(this.updateProgress.bind(this), 200));

        // Enhanced registration number validation
        $('input[name="reg_no"]').on('input', this.validateRegistrationNumber.bind(this));

        // Real-time email validation
        $('input[name="email"]').on('blur', this.validateEmailField.bind(this));

        // Real-time phone validation
        $('input[name="telephone"], input[name="parent_contact"]').on('blur', this.validatePhoneField.bind(this));
        $('#studentIdNumber').on('input', this.validateStudentId.bind(this));

        // Phone number input filtering (digits only, max 10 characters)
        $('input[name="telephone"]').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        // Student ID number input filtering (digits only)
        $('#studentIdNumber').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Parent contact input filtering (digits only, max 10 characters)
        $('input[name="parent_contact"]').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        // Fingerprint functionality
        $('#captureFingerprintBtn').on('click', this.startFingerprintCapture.bind(this));
        $('#clearFingerprintBtn').on('click', this.clearFingerprint.bind(this));
        $('#enrollFingerprintBtn').on('click', this.enrollFingerprint.bind(this));

        // Mobile menu toggle
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

        // Reset form functionality
        $('#resetBtn').on('click', this.resetForm.bind(this));
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
            this.resetProgramSelection();
            return;
        }

        // Clear previous program selection and show loading
        $option.prop('disabled', true).removeClass('programs-loaded');
        $loadingSpinner.removeClass('d-none');
        $('#programCount').addClass('d-none');
        $('#programLoadedIcon').addClass('d-none');

        // Reset help text to initial state
        $('#programHelp .fas').removeClass('fa-check-circle text-success').addClass('fa-info-circle text-info');
        $('#programHelp small').html('<strong class="text-muted">Available programs will appear after selecting a department</strong>');

        $option.prop('disabled', true);
        $loadingSpinner.removeClass('d-none');

        try {
            const response = await this.retryableAjax({
                url: 'api/department-option-api.php',
                method: 'POST',
                data: {
                    action: 'get_options',
                    department_id: parseInt(deptId, 10),
                    csrf_token: this.csrfToken
                }
            });

            if (response.success) {
                if (response.data && response.data.length > 0) {
                    const options = response.data.map(opt =>
                        `<option value="${opt.id}" data-department="${deptId}">${this.escapeHtml(opt.name)}</option>`
                    ).join('');

                    $option.html('<option value="">Select Program</option>' + options)
                        .prop('disabled', false)
                        .addClass('programs-loaded')
                        .data('department-id', deptId);

                    // Update program count display
                    $('#programCountText').text(`${response.data.length} program${response.data.length !== 1 ? 's' : ''} available for selection`);
                    $('#programCount').removeClass('d-none');

                    // Show success icon
                    $('#programLoadedIcon').removeClass('d-none');

                    // Update help text with success styling
                    $('#programHelp .fas').removeClass('fa-info-circle text-info').addClass('fa-check-circle text-success');
                    $('#programHelp small').html('<strong class="text-success">Programs loaded successfully!</strong> Choose your desired program from the dropdown above');

                    this.showAlert(`🎉 ${response.data.length} program${response.data.length !== 1 ? 's' : ''} loaded successfully!`, 'success');
                } else {
                    $option.html('<option value="">No programs available</option>')
                        .removeData('department-id');

                    $('#programCount').addClass('d-none');
                    $('#programHelp small').text('No programs are currently available for this department');

                    this.showAlert('⚠️ No programs found for this department', 'warning');
                }
            } else {
                throw new Error(response.message || 'Failed to load options');
            }
        } catch (error) {
            console.error('Department change error:', error);
            $option.html('<option value="">Error loading programs</option>')
                .removeData('department-id');

            // Reset program count and help text
            $('#programCount').addClass('d-none');
            $('#programHelp small').text('Failed to load programs. Please try selecting the department again.');

            this.showAlert('❌ Failed to load programs. Please try again.', 'error');
        } finally {
            $option.prop('disabled', false);
            $loadingSpinner.addClass('d-none');
        }
    }

    async retryableAjax(options, retries = this.retryAttempts) {
        const defaultOptions = {
            timeout: 10000,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const finalOptions = { ...defaultOptions, ...options };

        for (let i = 0; i < retries; i++) {
            try {
                const response = await $.ajax(finalOptions);

                // Validate response structure
                if (response && typeof response === 'object') {
                    return response;
                } else {
                    throw new Error('Invalid response format');
                }
            } catch (error) {
                const isLastAttempt = i === retries - 1;
                const errorMessage = this.getErrorMessage(error);

                // Enhanced error logging with attempt information
                console.error(`=== AJAX ATTEMPT ${i + 1}/${retries} FAILED ===`);
                console.error('URL:', finalOptions.url);
                console.error('Method:', finalOptions.method);
                console.error('Error message:', errorMessage);
                console.error('HTTP Status:', error.status);
                console.error('Status Text:', error.statusText);
                console.error('Ready State:', error.readyState);
                console.error('Response Text (truncated):', error.responseText ? error.responseText.substring(0, 200) + '...' : 'No response text');
                console.error('Response JSON:', error.responseJSON);
                console.error('Full error object:', error);
                console.error('=== END AJAX ATTEMPT FAILURE ===');

                if (isLastAttempt) {
                    console.error(`AJAX request failed after ${retries} attempts:`, errorMessage);
                    throw error; // Throw the original error for better handling
                }

                // Exponential backoff with jitter
                const delay = this.retryDelay * Math.pow(2, i) + Math.random() * 1000;
                console.log(`Retrying in ${Math.round(delay)}ms... (attempt ${i + 2}/${retries})`);
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        }
    }

    getErrorMessage(error) {
        // Enhanced error message extraction
        if (error.responseJSON && error.responseJSON.message) {
            return `Server error: ${error.responseJSON.message}`;
        } else if (error.responseJSON && error.responseJSON.errors) {
            const errors = Object.values(error.responseJSON.errors).flat();
            return `Validation errors: ${errors.join('; ')}`;
        } else if (error.status) {
            let statusMessage = `HTTP ${error.status}`;
            if (error.statusText) {
                statusMessage += `: ${error.statusText}`;
            }

            // Add specific guidance for common HTTP errors
            switch (error.status) {
                case 0:
                    return `${statusMessage} - Network connection failed. Check your internet connection.`;
                case 403:
                    return `${statusMessage} - Access denied. Please refresh the page and try again.`;
                case 404:
                    return `${statusMessage} - Service not found. The requested endpoint may not exist.`;
                case 500:
                    return `${statusMessage} - Server error. Please try again later.`;
                case 503:
                    return `${statusMessage} - Service temporarily unavailable. Please try again later.`;
                default:
                    return statusMessage;
            }
        } else if (error.message) {
            return `Request error: ${error.message}`;
        } else if (typeof error === 'string') {
            return error;
        }

        // Fallback with more details
        return `Unknown error occurred. Error details: ${JSON.stringify(error, Object.getOwnPropertyNames(error))}`;
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

    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    isValidPhone(phone) {
        // Must be exactly 10 digits, start with 0, and contain no letters
        const phoneRegex = /^0\d{9}$/;
        return phoneRegex.test(phone) && /^[0-9]+$/.test(phone);
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
        const value = e.target.value.replace(/[^A-Za-z0-9_-]/g, '').toUpperCase().substring(0, 20);
        e.target.value = value;

        if (value.length >= 5 && this.isValidRegistrationNumber(value)) {
            $(e.target).addClass('is-valid').removeClass('is-invalid');
        } else if (value.length > 0) {
            $(e.target).addClass('is-invalid').removeClass('is-valid');
        } else {
            $(e.target).removeClass('is-valid is-invalid');
        }
    }

    validateEmailField(e) {
        const email = e.target.value.trim();
        if (email && !this.isValidEmail(email)) {
            this.showFieldError(e.target, 'Please enter a valid email address');
        } else {
            this.clearFieldError(e.target);
        }
    }

    validatePhoneField(e) {
        const phone = e.target.value.trim();
        if (phone && !this.isValidPhone(phone)) {
            const fieldName = e.target.name === 'telephone' ? 'Phone number' : 'Parent phone number';
            this.showFieldError(e.target, `${fieldName} must be exactly 10 digits starting with 0 (e.g., 0781234567)`);
        } else {
            this.clearFieldError(e.target);
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

    showAlert(message, type = 'info', dismissible = true) {
        const alertContainer = document.getElementById('alertContainer');
        if (!alertContainer) return;

        const alertClass = `alert alert-${type} ${dismissible ? 'alert-dismissible fade show' : ''}`;
        const closeButton = dismissible ? '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' : '';

        const alertHtml = `
            <div class="${alertClass}" role="alert">
                ${message}
                ${closeButton}
            </div>
        `;

        alertContainer.innerHTML = alertHtml;

        // Auto-hide success and info alerts after 5 seconds
        if ((type === 'success' || type === 'info') && dismissible) {
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 150);
                }
            }, 5000);
        }
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
                    this.showFieldError(field, 'Please enter a valid 10-digit phone number (e.g., 0781234567)');
                } else {
                    this.clearFieldError(field);
                }
                break;
        }
    }

    updateProgress() {
        const totalFields = $('#registrationForm [required]').length;
        const filledFields = $('#registrationForm [required]').filter(function() {
            return $(this).val().trim().length > 0;
        }).length;

        const progress = Math.round((filledFields / totalFields) * 100);
        $('#formProgress').css('width', progress + '%');
        $('#progressText').text(progress + '%');

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

    showSuccess(response) {
        // Show success alert prominently at the top
        this.showAlert(`🎉 SUCCESS: ${response.message}`, 'success', false);

        // Create enhanced success modal
        if ($('#successModal').length === 0) {
            $('body').append(`
                <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-success">
                            <div class="modal-header bg-success text-white">
                                <h4 class="modal-title">
                                    <i class="fas fa-check-circle me-2"></i>Registration Completed Successfully!
                                </h4>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center p-4">
                                <div class="mb-4">
                                    <i class="fas fa-graduation-cap fa-4x text-success mb-3"></i>
                                    <h5 class="text-success mb-3">Welcome to Rwanda Polytechnic!</h5>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="alert alert-success border-success">
                                            <i class="fas fa-user-check me-2"></i>
                                            <strong>Student Registration Complete</strong><br>
                                            ${response.message}
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="card border-success">
                                            <div class="card-body">
                                                <h6 class="card-title text-success">
                                                    <i class="fas fa-id-card me-2"></i>Student Information
                                                </h6>
                                                <p class="mb-1"><strong>Student ID:</strong> <code class="text-success">${response.student_id}</code></p>
                                                <p class="mb-0"><strong>Status:</strong> <span class="badge bg-success">Active</span></p>
                                            </div>
                                        </div>
                                    </div>

                                    ${response.fingerprint_enrolled ?
                                        '<div class="col-12"><div class="alert alert-info border-info"><i class="fas fa-fingerprint me-2"></i><strong>Biometric Security:</strong> Fingerprint enrolled successfully for secure attendance tracking!</div></div>' :
                                        '<div class="col-12"><div class="alert alert-warning border-warning"><i class="fas fa-exclamation-triangle me-2"></i><strong>Note:</strong> Fingerprint not enrolled. Student can enroll later through their dashboard.</div></div>'
                                    }
                                </div>

                                <div class="mt-4 text-muted small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Student account has been created with default password: <strong>12345</strong>.
                                    Please advise student to login and change password on first login.
                                </div>
                            </div>
                            <div class="modal-footer justify-content-center">
                                <button type="button" class="btn btn-success btn-lg px-4" id="continueButton">
                                    <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                                </button>
                                <button type="button" class="btn btn-outline-success" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }

        const modal = new bootstrap.Modal(document.getElementById('successModal'), {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();

        $('#continueButton').off('click').on('click', function() {
            modal.hide();
            // Add a small delay to show transition
            setTimeout(() => {
                window.location.href = response.redirect || 'admin-dashboard.php';
            }, 300);
        });
    }

    startFingerprintCapture() {
        if (this.isCapturing) return;

        this.isCapturing = true;
        this.updateFingerprintUI('capturing');
        this.simulateFingerprintCapture();
    }

    simulateFingerprintCapture() {
        const canvas = document.getElementById('fingerprintCanvas');
        const ctx = canvas.getContext('2d');
        const placeholder = document.getElementById('fingerprintPlaceholder');
        const status = document.getElementById('fingerprintStatus');

        canvas.classList.remove('d-none');
        placeholder.classList.add('d-none');

        let progress = 0;
        let qualityVariation = 0;

        const captureInterval = setInterval(() => {
            progress += Math.random() * 8 + 2;
            qualityVariation += (Math.random() - 0.5) * 2;

            const currentProgress = Math.min(progress, 100);
            status.textContent = `Capturing... ${Math.round(currentProgress)}%`;

            this.drawFingerprintPattern(ctx, currentProgress);

            if (currentProgress >= 100) {
                clearInterval(captureInterval);
                this.fingerprintCaptured = true;

                const baseQuality = 85 + Math.floor(Math.random() * 10);
                const variationQuality = Math.max(75, Math.min(100, baseQuality + qualityVariation));
                this.fingerprintQuality = Math.round(variationQuality);

                this.isCapturing = false;
                this.updateFingerprintUI('captured');
                this.showAlert(`Fingerprint captured successfully! Quality: ${this.fingerprintQuality}%`, 'success');
            }
        }, 80);
    }

    drawFingerprintPattern(ctx, progress) {
        const centerX = ctx.canvas.width / 2;
        const centerY = ctx.canvas.height / 2;
        const maxRadius = Math.min(centerX, centerY) * 0.8;

        ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);

        ctx.strokeStyle = `rgba(13, 110, 253, ${Math.min(progress / 100, 1)})`;
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';

        const circleCount = Math.floor(progress / 10);
        for (let i = 1; i <= circleCount; i++) {
            const radius = (maxRadius * i) / 10;
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
            ctx.stroke();
        }

        if (progress > 50) {
            const spiralProgress = (progress - 50) / 50;
            ctx.strokeStyle = `rgba(25, 135, 84, ${spiralProgress})`;
            ctx.beginPath();

            let angle = 0;
            let radius = 10;
            const maxAngle = spiralProgress * Math.PI * 4;

            while (radius < maxRadius && angle < maxAngle) {
                const x = centerX + Math.cos(angle) * radius;
                const y = centerY + Math.sin(angle) * radius;
                ctx.lineTo(x, y);
                angle += 0.15;
                radius += 0.3;
            }
            ctx.stroke();
        }

        if (this.fingerprintQuality > 0) {
            this.updateQualityIndicator(ctx.canvas, this.fingerprintQuality);
        }
    }

    updateQualityIndicator(canvas, quality) {
        let qualityElement = document.querySelector('.quality-indicator');
        if (!qualityElement) {
            qualityElement = document.createElement('div');
            qualityElement.className = 'quality-indicator';
            canvas.parentElement.appendChild(qualityElement);
        }

        const qualityColor = quality >= 90 ? '#28a745' : quality >= 80 ? '#ffc107' : '#dc3545';
        qualityElement.textContent = `Quality: ${quality}%`;
        qualityElement.style.backgroundColor = qualityColor;
        qualityElement.style.color = 'white';
    }

    handleFaceImagesSelect(e) {
        const files = Array.from(e.target.files);
        const validFiles = [];
        const maxFiles = 5;
        const minFiles = 2;

        // Validate files
        for (const file of files) {
            if (this.validateImage(file)) {
                validFiles.push(file);
            }
        }

        // Check file count limits
        if (validFiles.length < minFiles) {
            this.showAlert(`Please select at least ${minFiles} images for face recognition.`, 'error');
            e.target.value = '';
            return;
        }

        if (validFiles.length > maxFiles) {
            this.showAlert(`Maximum ${maxFiles} images allowed. Please select fewer images.`, 'error');
            e.target.value = '';
            return;
        }

        // Clear existing previews
        $('#faceImagesPreview').empty().removeClass('d-none');
        $('#faceImagesUploadArea .face-images-placeholder').addClass('d-none');
        $('#clearFaceImages').removeClass('d-none');

        // Create previews for each valid file
        validFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageItem = document.createElement('div');
                imageItem.className = 'face-image-item';
                imageItem.innerHTML = `
                    <img src="${e.target.result}" alt="Face image ${index + 1}">
                    <button type="button" class="remove-image" data-index="${index}" title="Remove image">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="image-number">${index + 1}</div>
                `;
                $('#faceImagesPreview').append(imageItem);
            };
            reader.readAsDataURL(file);
        });

        // Update count display
        this.updateFaceImagesCount(validFiles.length);
        this.showAlert(`✅ ${validFiles.length} face images selected successfully!`, 'success');
    }

    removeFaceImage(e) {
        e.preventDefault();
        const index = $(e.currentTarget).data('index');
        const $imageItem = $(e.currentTarget).closest('.face-image-item');

        // Remove the image item
        $imageItem.remove();

        // Update remaining image numbers
        $('#faceImagesPreview .face-image-item').each(function(i) {
            $(this).find('.image-number').text(i + 1);
            $(this).find('.remove-image').attr('data-index', i);
        });

        // Check if any images remain
        const remainingImages = $('#faceImagesPreview .face-image-item').length;
        this.updateFaceImagesCount(remainingImages);

        if (remainingImages === 0) {
            this.clearFaceImages();
        } else {
            this.showAlert(`Image removed. ${remainingImages} image${remainingImages !== 1 ? 's' : ''} remaining.`, 'info');
        }
    }

    clearFaceImages() {
        $('#faceImagesInput').val('');
        $('#faceImagesPreview').empty().addClass('d-none');
        $('#faceImagesUploadArea .face-images-placeholder').removeClass('d-none');
        $('#clearFaceImages').addClass('d-none');
        this.updateFaceImagesCount(0);
    }

    updateFaceImagesCount(count) {
        const $countElement = $('#faceImagesCount');
        if (count === 0) {
            $countElement.text('0 images selected');
            $countElement.removeClass('text-success text-warning').addClass('text-muted');
        } else if (count >= 2 && count <= 5) {
            $countElement.text(`${count} images selected`);
            $countElement.removeClass('text-muted text-warning').addClass('text-success');
        } else {
            $countElement.text(`${count} images selected`);
            $countElement.removeClass('text-muted text-success').addClass('text-warning');
        }
    }

    // Validate location selection (optional fields)
    validateLocation() {
        // Location fields are optional, so always return true
        return true;
    }

    async handleSubmit(e) {
        e.preventDefault();

        if (!this.validateForm()) {
            this.showAlert('Please correct the errors before submitting.', 'error');
            this.scrollToFirstError();
            return;
        }

        // Additional validation: verify department-option relationship
        const departmentId = $('#department').val();
        const optionId = $('#option').val();

        if (departmentId && optionId) {
            try {
                const validationResponse = await this.retryableAjax({
                    url: 'api/department-option-api.php',
                    method: 'POST',
                    data: {
                        action: 'validate_relationship',
                        department_id: parseInt(departmentId, 10),
                        option_id: parseInt(optionId, 10),
                        csrf_token: this.csrfToken
                    }
                });

                if (!validationResponse.valid) {
                    this.showAlert('Invalid department-program combination. Please select a valid program for the chosen department.', 'error');
                    $('#option').focus();
                    return;
                }
            } catch (error) {
                console.error('Relationship validation error:', error);
                this.showAlert('Failed to validate department-program relationship. Please try again.', 'error');
                return;
            }
        }

        if (!await this.confirmSubmission()) {
            return;
        }

        try {
            this.showLoading(true);
            this.disableForm(true);

            const formData = new FormData();

            // Manually collect form data to ensure all fields are included
            const form = e.target;
            const formElements = form.querySelectorAll('input, select, textarea');
            formElements.forEach(element => {
                if (element.name) {
                    if (element.type === 'file') {
                        if (element.files.length > 0) {
                            formData.append(element.name, element.files[0]);
                        }
                    } else if (element.type === 'checkbox' || element.type === 'radio') {
                        if (element.checked) {
                            formData.append(element.name, element.value);
                        }
                    } else {
                        formData.append(element.name, element.value);
                    }
                }
            });

            // Include fingerprint data if captured
            if (this.fingerprintCaptured && this.fingerprintData) {
                // Convert canvas to base64 data URL for backend processing
                const canvas = document.getElementById('fingerprintCanvas');
                const fingerprintImageData = canvas.toDataURL('image/png');
                formData.append('fingerprint_data', fingerprintImageData);
                formData.append('fingerprint_quality', this.fingerprintQuality);
            }

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
        const errors = [];

        // Clear previous validation
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();

        // Check face images requirement
        const faceImagesCount = $('#faceImagesPreview .face-image-item').length;
        if (faceImagesCount < 2) {
            this.showAlert('Please select at least 2 face images for recognition.', 'error');
            $('#faceImagesUploadArea').addClass('border-danger');
            isValid = false;
        } else {
            $('#faceImagesUploadArea').removeClass('border-danger');
        }

        // Required field validation with specific messages
        const requiredFields = [
            { id: 'firstName', name: 'First Name' },
            { id: 'lastName', name: 'Last Name' },
            { id: 'email', name: 'Email' },
            { id: 'telephone', name: 'Phone Number' },
            { id: 'department', name: 'Department' },
            { id: 'option', name: 'Program' },
            { id: 'reg_no', name: 'Registration Number' },
            { id: 'sex', name: 'Gender' },
            { id: 'year_level', name: 'Year Level' }
        ];

        requiredFields.forEach(field => {
            const $element = $(`#${field.id}`);
            const value = $element.val()?.trim();

            if (!value) {
                this.showFieldError($element[0], `${field.name} is required`);
                errors.push(`${field.name} is required`);
                isValid = false;
            }
        });

        // Additional validations
        const email = $('#email').val()?.trim();
        if (email && !this.isValidEmail(email)) {
            this.showFieldError(document.getElementById('email'), 'Please enter a valid email address');
            isValid = false;
        }

        const phone = $('#telephone').val()?.trim();
        if (phone && !this.isValidPhone(phone)) {
            this.showFieldError(document.getElementById('telephone'), 'Phone number must be exactly 10 digits starting with 0');
            isValid = false;
        }

        return isValid;
    }

    // Additional methods would go here...

    isValidRegistrationNumber(value) {
        // Add your registration number validation logic here
        return value.length >= 5 && value.length <= 20;
    }

    handleSubmissionError(response) {
        console.error('Submission error:', response);
        this.showAlert(response.message || 'Registration failed. Please try again.', 'error');
    }

    handleNetworkError(error) {
        console.error('Network error:', error);
        this.showAlert('Network error occurred. Please check your connection and try again.', 'error');
    }

    resetForm() {
        $('#registrationForm')[0].reset();
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        this.clearFaceImages();
        this.updateFingerprintUI('ready');
        this.updateProgress();
        this.showAlert('Form has been reset.', 'info');
    }
}

// Initialize the application when DOM is ready
$(document).ready(function() {
    // Application initialization is handled in the HTML file
});
