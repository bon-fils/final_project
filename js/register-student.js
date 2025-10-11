/**
 * Enhanced Student Registration System - JavaScript
 * Refined with better error handling, performance, and maintainability
 */

class StudentRegistration {
    constructor() {
        this.config = {
            retryAttempts: 3,
            retryDelay: 1000,
            maxFileSize: 5 * 1024 * 1024, // 5MB
            validImageTypes: ['image/jpeg', 'image/png', 'image/webp'],
            minFaceImages: 2,
            maxFaceImages: 5
        };

        this.state = {
            csrfToken: window.csrfToken || null,
            fingerprintCaptured: false,
            fingerprintData: null,
            fingerprintQuality: 0,
            isCapturing: false,
            isSubmitting: false,
            selectedFaceImagesCount: 0
        };

        this.cache = {
            districts: new Map(),
            sectors: new Map(),
            cells: new Map(),
            programs: new Map()
        };

        this.selectors = {
            form: '#registrationForm',
            province: '#province',
            district: '#district',
            sector: '#sector',
            cell: '#cell',
            department: '#department',
            program: '#option',
            alertContainer: '#alertContainer',
            loadingOverlay: '#loadingOverlay'
        };

        this.originalCellOptions = [];
        this.init();
    }

    /**
     * Initialize the application
     */
    async init() {
        try {
            await this.initializeDependencies();
            this.setupEventListeners();
            this.initializeFormState();
            this.showWelcomeMessage();
            this.setupGlobalErrorHandler();

            // Load provinces immediately for better UX
            await this.loadProvinces();

            // Non-blocking connectivity check
            setTimeout(() => this.checkServerConnectivity(), 1000);
        } catch (error) {
            console.error('Initialization error:', error);
            this.showAlert('System initialization failed. Please refresh the page.', 'error', false);
        }
    }

    /**
     * Initialize required dependencies and state
     */
    async initializeDependencies() {
        // CSRF token is already set from window.csrfToken

        // Wait for DOM to be fully ready
        if (document.readyState !== 'complete') {
            await new Promise(resolve => {
                document.addEventListener('DOMContentLoaded', resolve);
            });
        }
    }


    /**
     * Initialize form state and UI
     */
    initializeFormState() {
        this.updateProgress();
        this.initializeLocationFields();
        this.initializeProgramField();
        this.updateFingerprintUI('ready');

        // Pre-validate form after a short delay
        setTimeout(() => this.validateForm(), 500);
    }

    /**
     * Initialize program field with all programs loaded
     */
    initializeProgramField() {
        const $program = $(this.selectors.program);

        // Enable the program field since all programs are pre-loaded
        $program.prop('disabled', false);
        $program.find('option:first').text('🎓 Select Your Program (All Departments)');

        // Update help text
        $('#programHelp .fas').removeClass('fa-check-circle text-success').addClass('fa-info-circle text-info');
        $('#programHelp small').html('<strong class="text-muted">Select your academic department above to filter available programs</strong>');

        // Show program count
        const totalPrograms = $program.find('option[data-department]').length;
        if (totalPrograms > 0) {
            $('#programCountText').text(`${totalPrograms} total program${totalPrograms !== 1 ? 's' : ''} available across all departments`);
            $('#programCount').removeClass('d-none');
        }
    }

    /**
     * Initialize location fields and load provinces
     */
    initializeLocationFields() {
        $(this.selectors.province).addClass('enabled');
        this.resetLocationFields('province');
        // Provinces will be loaded by loadProvinces() called in init()
    }

    /**
     * Set up all event listeners
     */
    setupEventListeners() {
        const { form, province, district, sector, cell, department } = this.selectors;
        
        // Location cascading with debouncing
        $(province).on('change', this.debounce(this.handleProvinceChange.bind(this), 300));
        $(district).on('change', this.debounce(this.handleDistrictChange.bind(this), 300));
        $(sector).on('change', this.debounce(this.handleSectorChange.bind(this), 300));
        $(cell).on('change', this.debounce(this.handleCellChange.bind(this), 300));
        
        // Department and program handling
        $(department).on('change', this.debounce(this.handleDepartmentChange.bind(this), 300));
        
        // Form submission and validation
        $(form).on('submit', this.handleSubmit.bind(this));
        
        // Real-time validation
        $('input[required]').on('blur', this.validateField.bind(this));
        $('input[required]').on('input', this.debounce(this.updateProgress.bind(this), 200));
        
        // Enhanced input validation
        this.setupInputValidation();
        
        // File handling
        this.setupFileHandlers();
        
        // Fingerprint functionality
        this.setupFingerprintHandlers();
        
        // UI interactions
        this.setupUIHandlers();
    }

    /**
     * Set up input validation handlers
     */
    setupInputValidation() {
        // Registration number validation
        $('input[name="reg_no"]').on('input', this.validateRegistrationNumber.bind(this));
        
        // Email validation
        $('input[name="email"]').on('blur', this.validateEmailField.bind(this));
        
        // Phone validation
        $('input[name="telephone"], input[name="parent_contact"]').on('blur', this.validatePhoneField.bind(this));
        $('#studentIdNumber').on('input', this.validateStudentId.bind(this));
        
        // Input filtering
        $('input[name="telephone"]').on('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').substring(0, 10);
        });
        
        $('#studentIdNumber').on('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        $('input[name="parent_contact"]').on('input', (e) => {
            e.target.value = e.target.value.replace(/[^0-9]/g, '').substring(0, 10);
        });
    }

    /**
     * Set up file upload handlers
     */
    setupFileHandlers() {
        // Photo selection
        $('#selectPhotoBtn').on('click', () => $('#photoInput').click());
        $('#photoInput').on('change', this.handlePhotoSelect.bind(this));
        $('#removePhoto').on('click', this.removePhoto.bind(this));

        // Face images
        $('#faceImagesInput').on('change', this.handleFaceImagesSelect.bind(this));
        $('#clearFaceImages').on('click', this.clearFaceImages.bind(this));
        $(document).on('click', '.remove-image', this.removeFaceImage.bind(this));
    }

    /**
     * Handle photo selection
     */
    handlePhotoSelect(e) {
        const file = e.target.files[0];
        if (file && this.validateImage(file)) {
            const reader = new FileReader();
            reader.onload = (e) => {
                $('#photoPreview').attr('src', e.target.result).removeClass('d-none');
                $('#removePhoto').removeClass('d-none');
                $('#selectPhotoBtn').addClass('d-none');
                this.showAlert('✅ Photo selected successfully!', 'success');
            };
            reader.readAsDataURL(file);
        } else {
            // Clear the input if file is invalid
            e.target.value = '';
            this.showAlert('❌ Invalid image file. Please select a JPEG, PNG, or WebP image under 5MB.', 'error');
        }
    }

    /**
     * Remove selected photo
     */
    removePhoto() {
        $('#photoInput').val('');
        $('#photoPreview').addClass('d-none').attr('src', '');
        $('#removePhoto').addClass('d-none');
        $('#selectPhotoBtn').removeClass('d-none');
        this.showAlert('🗑️ Photo removed.', 'info');
    }

    /**
     * Handle face images selection
     */
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

        // Create previews for each valid file with enhanced quality feedback
        validFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageItem = document.createElement('div');
                imageItem.className = 'face-image-item position-relative';

                // Estimate image quality based on file size (rough approximation)
                const qualityClass = file.size > 500000 ? 'text-success' : file.size > 200000 ? 'text-warning' : 'text-danger';
                const qualityText = file.size > 500000 ? 'High' : file.size > 200000 ? 'Medium' : 'Low';

                imageItem.innerHTML = `
                    <img src="${e.target.result}" alt="Face image ${index + 1}" class="img-fluid rounded">
                    <button type="button" class="remove-image btn btn-sm btn-danger position-absolute" data-index="${index}" title="Remove image" style="top: 5px; right: 5px;">
                        <i class="fas fa-times"></i>
                    </button>
                    <div class="image-info position-absolute bottom-0 start-0 bg-dark bg-opacity-75 text-white px-2 py-1 rounded-top">
                        <small><i class="fas fa-image me-1"></i>${index + 1}</small>
                        <span class="badge ${qualityClass} ms-1">${qualityText}</span>
                    </div>
                    <div class="image-size text-muted small mt-1">
                        ${(file.size / 1024).toFixed(1)} KB
                    </div>
                `;
                $('#faceImagesPreview').append(imageItem);
            };
            reader.readAsDataURL(file);
        });

        // Update count display
        this.state.selectedFaceImagesCount = validFiles.length;
        this.updateFaceImagesCount();
        this.showAlert(`✅ ${validFiles.length} face images selected successfully!`, 'success');
    }

    /**
     * Remove a face image
     */
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

        // Update count
        this.state.selectedFaceImagesCount--;
        this.updateFaceImagesCount();

        // Check if any images remain
        const remainingImages = $('#faceImagesPreview .face-image-item').length;

        if (remainingImages === 0) {
            this.clearFaceImages();
        } else {
            this.showAlert(`Image removed. ${remainingImages} image${remainingImages !== 1 ? 's' : ''} remaining.`, 'info');
        }
    }

    /**
     * Clear all face images
     */
    clearFaceImages() {
        $('#faceImagesInput').val('');
        $('#faceImagesPreview').empty().addClass('d-none');
        $('#faceImagesUploadArea .face-images-placeholder').removeClass('d-none');
        $('#clearFaceImages').addClass('d-none');
        this.state.selectedFaceImagesCount = 0;
        this.updateFaceImagesCount();
    }

    /**
     * Update face images count display
     */
    updateFaceImagesCount() {
        const count = this.state.selectedFaceImagesCount;
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

    /**
     * Set up fingerprint handlers
     */
    setupFingerprintHandlers() {
        $('#captureFingerprintBtn').on('click', this.startFingerprintCapture.bind(this));
        $('#clearFingerprintBtn').on('click', this.clearFingerprint.bind(this));
        $('#enrollFingerprintBtn').on('click', this.enrollFingerprint.bind(this));
    }

    /**
     * Set up UI interaction handlers
     */
    setupUIHandlers() {
        // Mobile menu
        $('#mobileMenuToggle').on('click', () => {
            $('#adminSidebar').toggleClass('show');
            $('#mainContent').toggleClass('sidebar-open');
        });

        // Close sidebar when clicking outside (mobile)
        $(document).on('click', (e) => {
            if ($(window).width() <= 768 &&
                !$(e.target).closest('#adminSidebar, #mobileMenuToggle').length) {
                $('#adminSidebar').removeClass('show');
                $('#mainContent').removeClass('sidebar-open');
            }
        });

        // Reset form
        $('#resetBtn').on('click', this.resetForm.bind(this));
        
        // Cell search
        $('#cellSearch').on('input', this.debounce(this.handleCellSearch.bind(this), 300));
    }

    /**
     * Load provinces data
     */
    async loadProvinces() {
        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'get_provinces',
                    csrf_token: this.state.csrfToken
                }
            });

            if (response.success && response.provinces) {
                this.populateSelect(this.selectors.province, response.provinces, '📍 Select Province');
                this.showAlert(`📍 ${response.provinces.length} provinces loaded successfully!`, 'success');
            } else {
                throw new Error(response.message || 'Failed to load provinces');
            }
        } catch (error) {
            this.handleDataLoadError(error, this.selectors.province, 'provinces');
        }
    }

    /**
     * Handle province change event
     */
    async handleProvinceChange() {
        const provinceId = $(this.selectors.province).val();
        const $district = $(this.selectors.district);

        this.resetLocationFields('district');

        if (!provinceId) return;

        // Check cache first
        if (this.cache.districts.has(provinceId)) {
            this.populateDistricts(this.cache.districts.get(provinceId));
            return;
        }

        await this.loadLocationData({
            action: 'get_districts',
            province_id: parseInt(provinceId, 10),
            cacheKey: provinceId,
            cache: this.cache.districts,
            target: this.selectors.district,
            populateMethod: this.populateDistricts.bind(this),
            loadingText: 'Loading districts...',
            successText: (count, provinceName) => 
                `🏛️ ${count} district${count !== 1 ? 's' : ''} loaded for ${provinceName}`
        });
    }

    /**
     * Handle district change event
     */
    async handleDistrictChange() {
        const districtId = $(this.selectors.district).val();
        const provinceId = $(this.selectors.province).val();
        const $sector = $(this.selectors.sector);

        this.resetLocationFields('sector');

        if (!districtId) return;

        if (!provinceId) {
            this.showAlert('Please select a province first.', 'error');
            $(this.selectors.district).val('');
            return;
        }

        // Check cache first
        const cacheKey = `${provinceId}_${districtId}`;
        if (this.cache.sectors.has(cacheKey)) {
            this.populateSectors(this.cache.sectors.get(cacheKey));
            return;
        }

        await this.loadLocationData({
            action: 'get_sectors',
            province_id: parseInt(provinceId, 10),
            district_id: parseInt(districtId, 10),
            cacheKey: cacheKey,
            cache: this.cache.sectors,
            target: this.selectors.sector,
            populateMethod: this.populateSectors.bind(this),
            loadingText: 'Loading sectors...',
            successText: (count, sectorName) =>
                `🏘️ ${count} sector${count !== 1 ? 's' : ''} loaded for ${sectorName}`
        });
    }

    /**
     * Generic method to load location data
     */
    async loadLocationData(options) {
        const {
            action,
            province_id,
            district_id,
            sector_id,
            cacheKey,
            cache,
            target,
            populateMethod,
            loadingText,
            successText
        } = options;

        const $target = $(target);
        
        // Show loading state
        $target.prop('disabled', true).html(`<option value="">${loadingText}</option>`);
        this.showLocationLoading($target, true);

        try {
            const data = { action, csrf_token: this.state.csrfToken };
            if (province_id) data.province_id = province_id;
            if (district_id) data.district_id = district_id;
            if (sector_id) data.sector_id = sector_id;

            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data
            });

            if (response.success && response[action.replace('get_', '')]) {
                const items = response[action.replace('get_', '')];
                
                // Cache the results
                cache.set(cacheKey, items);
                populateMethod(items);

                const parentName = this.getParentLocationName(action);
                this.showAlert(successText(items.length, parentName), 'success');
            } else {
                throw new Error(response.message || `No ${action.replace('get_', '')} found`);
            }
        } catch (error) {
            this.handleDataLoadError(error, target, action.replace('get_', ''));
        } finally {
            this.showLocationLoading($target, false);
            $target.prop('disabled', false);
        }
    }

    /**
     * Get parent location name for success messages
     */
    getParentLocationName(action) {
        switch (action) {
            case 'get_districts':
                return $(this.selectors.province + ' option:selected').text();
            case 'get_sectors':
                return $(this.selectors.district + ' option:selected').text();
            case 'get_cells':
                return $(this.selectors.sector + ' option:selected').text();
            default:
                return '';
        }
    }

    /**
     * Populate districts select
     */
    populateDistricts(districts) {
        this.populateSelect(this.selectors.district, districts, '🏛️ Select District');
    }

    /**
     * Populate sectors select
     */
    populateSectors(sectors) {
        this.populateSelect(this.selectors.sector, sectors, '🏘️ Select Sector');
    }

    /**
     * Populate cells select
     */
    populateCells(cells) {
        const $cell = $(this.selectors.cell);

        if (cells.length === 0) {
            $cell.html('<option value="">No cells available for this sector</option>')
                  .addClass('enabled');
            $('#cellSearchContainer').hide();
            this.originalCellOptions = [];
            return;
        }

        this.populateSelect(this.selectors.cell, cells, '📍 Select Cell');
        this.originalCellOptions = cells;

        // Show search container if there are many cells
        $('#cellSearchContainer').toggle(cells.length > 5);
        $('#cellSearch').val('');
    }

    /**
     * Generic method to populate select elements
     */
    populateSelect(selector, items, placeholder) {
        const $select = $(selector);
        const options = items.map(item =>
            `<option value="${item.id}">${this.escapeHtml(item.name)}</option>`
        ).join('');

        $select.html(`<option value="">${placeholder}</option>${options}`)
               .addClass('enabled');
    }

    /**
     * Reset location fields based on changed level
     */
    resetLocationFields(fromLevel) {
        const levels = ['district', 'sector', 'cell'];
        const levelIndex = levels.indexOf(fromLevel);

        levels.forEach((level, index) => {
            if (index > levelIndex) {
                const $field = $(`#${level}`);
                $field.prop('disabled', true).val('').removeClass('enabled');

                const placeholders = {
                    district: '🏛️ Select Province First',
                    sector: '🏘️ Select District First',
                    cell: '📍 Select Sector First'
                };

                $field.html(`<option value="">${placeholders[level]}</option>`);

                // Clear cache for dependent levels
                if (level === 'district') {
                    this.cache.sectors.clear();
                    this.cache.cells.clear();
                } else if (level === 'sector') {
                    this.cache.cells.clear();
                }

                // Hide cell information when resetting
                if (level === 'cell') {
                    $('#cellInfo').hide();
                    $('#cellSearchContainer').hide();
                    $('#cellSearch').val('');
                    this.originalCellOptions = [];
                }
            }
        });
    }

    /**
     * Handle department change event
     */
    async handleDepartmentChange() {
        const deptId = $(this.selectors.department).val();
        const $program = $(this.selectors.program);

        if (!deptId) {
            this.resetProgramSelection();
            return;
        }

        // Get department name for filtering
        const deptName = $(this.selectors.department + ' option:selected').text().replace('📚 ', '');

        // Filter existing programs by department
        this.filterProgramsByDepartment(deptId, deptName);
    }

    /**
     * Filter programs by selected department
     */
    filterProgramsByDepartment(departmentId, departmentName) {
        const $program = $(this.selectors.program);
        const $allOptions = $program.find('option[data-department]');
        const $loadingSpinner = $('.program-loading');

        // Show loading state
        $loadingSpinner.removeClass('d-none');
        $program.prop('disabled', true);

        // Reset help text
        $('#programHelp .fas').removeClass('fa-check-circle text-success').addClass('fa-info-circle text-info');
        $('#programHelp small').html('<strong class="text-muted">Filtering programs for selected department...</strong>');

        // Hide all program options first
        $allOptions.addClass('d-none');

        // Show only programs for selected department
        const $deptPrograms = $allOptions.filter(function() {
            const optionDept = $(this).data('department');
            return optionDept && optionDept.includes(departmentName.replace(' Department', '').trim());
        });

        // Show matching programs
        $deptPrograms.removeClass('d-none');

        // Update the first option text
        const programCount = $deptPrograms.length;
        if (programCount > 0) {
            $program.find('option:first').text('🎓 Select Your Program');
            $program.prop('disabled', false).addClass('programs-loaded').data('department-id', departmentId);
            this.updateProgramUI(programCount, true);
            this.showAlert(`✅ ${programCount} program${programCount !== 1 ? 's' : ''} available for ${departmentName}`, 'success');
        } else {
            $program.find('option:first').text('❌ No programs available for this department');
            $program.prop('disabled', true).removeClass('programs-loaded');
            this.updateProgramUI(0, false);
            this.showAlert('⚠️ No programs found for this department', 'warning');
        }

        // Hide loading spinner
        $loadingSpinner.addClass('d-none');
    }

    /**
     * Update program selection UI
     */
    updateProgramUI(programCount, success) {
        const $programCount = $('#programCount');
        const $programIcon = $('#programLoadedIcon');
        const $programHelp = $('#programHelp');

        if (success && programCount > 0) {
            $('#programCountText').text(`${programCount} program${programCount !== 1 ? 's' : ''} available for selection`);
            $programCount.removeClass('d-none');
            $programIcon.removeClass('d-none');
            
            $programHelp.find('.fas').removeClass('fa-info-circle text-info').addClass('fa-check-circle text-success');
            $programHelp.find('small').html('<strong class="text-success">Programs loaded successfully!</strong> Choose your desired program from the dropdown above');
        } else {
            $programCount.addClass('d-none');
            $programHelp.find('small').text(
                programCount === 0 ? 
                'No programs are currently available for this department' :
                'Failed to load programs. Please try selecting the department again.'
            );
        }
    }

    /**
     * Handle data load errors consistently
     */
    handleDataLoadError(error, target, dataType) {
        console.error(`${dataType} loading error:`, error);
        $(target).html(`<option value="">❌ Failed to load ${dataType}</option>`);
        this.showAlert(`❌ Failed to load ${dataType}. Please try again.`, 'error');
    }

    /**
     * Handle program load errors
     */
    handleProgramLoadError(error) {
        console.error('Program loading error:', error);
        $(this.selectors.program).html('<option value="">Error loading programs</option>')
                                .removeData('department-id');
        this.updateProgramUI(0, false);
        this.showAlert('❌ Failed to load programs. Please try again.', 'error');
    }

    /**
     * Retryable AJAX wrapper with exponential backoff
     */
    async retryableAjax(options, retries = this.config.retryAttempts) {
        const defaultOptions = {
            timeout: 10000,
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const finalOptions = { ...defaultOptions, ...options };

        for (let attempt = 0; attempt < retries; attempt++) {
            try {
                const response = await $.ajax(finalOptions);

                if (response && typeof response === 'object') {
                    return response;
                } else {
                    throw new Error('Invalid response format');
                }
            } catch (error) {
                const isLastAttempt = attempt === retries - 1;
                
                if (isLastAttempt) {
                    console.error(`AJAX request failed after ${retries} attempts:`, this.getErrorMessage(error));
                    throw error;
                }

                // Exponential backoff with jitter
                const delay = this.config.retryDelay * Math.pow(2, attempt) + Math.random() * 1000;
                console.log(`Retrying in ${Math.round(delay)}ms... (attempt ${attempt + 2}/${retries})`);
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        }
    }

    /**
     * Handle sector change event
     */
    async handleSectorChange() {
        const sectorId = $(this.selectors.sector).val();
        const districtId = $(this.selectors.district).val();
        const provinceId = $(this.selectors.province).val();
        const $cell = $(this.selectors.cell);

        this.resetLocationFields('cell');

        if (!sectorId) return;

        if (!provinceId || !districtId) {
            this.showAlert('Please select province and district first.', 'error');
            $(this.selectors.sector).val('');
            return;
        }

        // Check cache first
        const cacheKey = `${provinceId}_${districtId}_${sectorId}`;
        if (this.cache.cells.has(cacheKey)) {
            this.populateCells(this.cache.cells.get(cacheKey));
            return;
        }

        await this.loadLocationData({
            action: 'get_cells',
            province_id: parseInt(provinceId, 10),
            district_id: parseInt(districtId, 10),
            sector_id: parseInt(sectorId, 10),
            cacheKey: cacheKey,
            cache: this.cache.cells,
            target: this.selectors.cell,
            populateMethod: this.populateCells.bind(this),
            loadingText: 'Loading cells...',
            successText: (count, sectorName) =>
                `📍 ${count} cell${count !== 1 ? 's' : ''} loaded for ${sectorName}`
        });
    }

    /**
     * Handle cell change event
     */
    handleCellChange() {
        const cellId = $(this.selectors.cell).val();

        if (!cellId) {
            $('#cellInfo').hide();
            return;
        }

        // Find cell information
        const selectedCell = this.originalCellOptions.find(cell => cell.id == cellId);
        if (selectedCell) {
            $('#cellInfo').show();
            // Could display additional cell information here if available
        }
    }

    /**
     * Handle cell search functionality
     */
    handleCellSearch() {
        const searchTerm = $('#cellSearch').val().toLowerCase();
        const $cellSelect = $(this.selectors.cell);

        if (!searchTerm) {
            // Reset to original options
            this.populateCells(this.originalCellOptions);
            return;
        }

        // Filter cells based on search term
        const filteredCells = this.originalCellOptions.filter(cell =>
            cell.name.toLowerCase().includes(searchTerm)
        );

        if (filteredCells.length === 0) {
            $cellSelect.html('<option value="">No cells match your search</option>');
        } else {
            this.populateSelect(this.selectors.cell, filteredCells, `📍 ${filteredCells.length} cell${filteredCells.length !== 1 ? 's' : ''} found`);
        }
    }

    /**
     * Validate individual field
     */
    validateField(e) {
        const $field = $(e.target);
        const fieldName = $field.attr('name');
        const value = $field.val().trim();

        // Remove previous validation classes
        $field.removeClass('is-valid is-invalid');

        // Check if field is required and empty
        if ($field.prop('required') && !value) {
            $field.addClass('is-invalid');
            return false;
        }

        // Field-specific validation
        switch (fieldName) {
            case 'email':
                return this.validateEmailField($field);
            case 'telephone':
            case 'parent_contact':
                return this.validatePhoneField($field);
            case 'student_id_number':
                return this.validateStudentId($field);
            case 'reg_no':
                return this.validateRegistrationNumber($field);
            default:
                // For other fields, just check if required fields have value
                if ($field.prop('required') && value) {
                    $field.addClass('is-valid');
                    return true;
                }
                return true;
        }
    }

    /**
     * Validate email field
     */
    validateEmailField($field) {
        const email = $field.val().trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        // Remove existing feedback
        $field.siblings('.invalid-feedback').remove();
        $field.siblings('.valid-feedback').remove();

        if (!email) {
            if ($field.prop('required')) {
                $field.addClass('is-invalid');
                $field.after('<div class="invalid-feedback">Email address is required.</div>');
                return false;
            }
            return true;
        }

        if (!emailRegex.test(email)) {
            $field.addClass('is-invalid');
            $field.after('<div class="invalid-feedback">Please enter a valid email address (e.g., student@university.edu).</div>');
            return false;
        }

        $field.addClass('is-valid');
        $field.after('<div class="valid-feedback">Email address looks good!</div>');
        return true;
    }

    /**
     * Validate phone field
     */
    validatePhoneField($field) {
        const phone = $field.val().trim();

        // Remove existing feedback
        $field.siblings('.invalid-feedback').remove();
        $field.siblings('.valid-feedback').remove();

        if (!phone) {
            if ($field.prop('required')) {
                $field.addClass('is-invalid');
                $field.after('<div class="invalid-feedback">Phone number is required.</div>');
                return false;
            }
            return true;
        }

        // Rwanda phone number format: starts with 0, followed by 9 digits
        const phoneRegex = /^0\d{9}$/;

        if (!phoneRegex.test(phone)) {
            $field.addClass('is-invalid');
            $field.after('<div class="invalid-feedback">Phone number must be 10 digits starting with 0 (e.g., 0781234567).</div>');
            return false;
        }

        $field.addClass('is-valid');
        $field.after('<div class="valid-feedback">Phone number is valid!</div>');
        return true;
    }

    /**
     * Validate student ID number
     */
    validateStudentId($field) {
        const id = $field.val().trim();

        if (!id) {
            if ($field.prop('required')) {
                $field.addClass('is-invalid');
                return false;
            }
            return true;
        }

        // Student ID should be exactly 16 digits
        if (!/^\d{16}$/.test(id)) {
            $field.addClass('is-invalid');
            return false;
        }

        $field.addClass('is-valid');
        return true;
    }

    /**
     * Validate registration number
     */
    validateRegistrationNumber($field) {
        const regNo = $field.val().trim();

        // Remove existing feedback
        $field.siblings('.invalid-feedback').remove();
        $field.siblings('.valid-feedback').remove();

        if (!regNo) {
            if ($field.prop('required')) {
                $field.addClass('is-invalid');
                $field.after('<div class="invalid-feedback">Registration number is required.</div>');
                return false;
            }
            return true;
        }

        // Registration number: alphanumeric, underscores, hyphens, 5-20 chars
        const regNoRegex = /^[A-Za-z0-9_-]{5,20}$/;

        if (!regNoRegex.test(regNo)) {
            $field.addClass('is-invalid');
            $field.after('<div class="invalid-feedback">Registration number must be 5-20 characters, containing only letters, numbers, underscores, and hyphens.</div>');
            return false;
        }

        $field.addClass('is-valid');
        $field.after('<div class="valid-feedback">Registration number format is valid!</div>');
        return true;
    }

    /**
     * Validate entire form
     */
    validateForm() {
        let isValid = true;
        const $form = $(this.selectors.form);

        // Clear previous validation
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.is-valid').removeClass('is-valid');

        // Validate required fields
        $form.find('[required]').each((index, element) => {
            const $field = $(element);
            const value = $field.val();

            if (!value || value.trim() === '') {
                $field.addClass('is-invalid');
                isValid = false;
            } else {
                // Additional validation for specific fields
                switch ($field.attr('name')) {
                    case 'email':
                        if (!this.validateEmailField($field)) isValid = false;
                        break;
                    case 'telephone':
                        if (!this.validatePhoneField($field)) isValid = false;
                        break;
                    case 'student_id_number':
                        if (!this.validateStudentId($field)) isValid = false;
                        break;
                    case 'reg_no':
                        if (!this.validateRegistrationNumber($field)) isValid = false;
                        break;
                    default:
                        $field.addClass('is-valid');
                }
            }
        });

        // Validate face images
        const faceImagesCount = $('#faceImagesInput')[0].files.length;
        if (faceImagesCount < this.config.minFaceImages) {
            this.showAlert(`Please select at least ${this.config.minFaceImages} face images.`, 'error');
            isValid = false;
        }

        // Validate file sizes and types
        const files = Array.from($('#faceImagesInput')[0].files);
        for (const file of files) {
            if (!this.validateImage(file)) {
                isValid = false;
                break;
            }
        }

        return isValid;
    }

    /**
     * Validate image file
     */
    validateImage(file) {
        const validTypes = this.config.validImageTypes;
        const maxSize = this.config.maxFileSize;

        if (!validTypes.includes(file.type)) {
            this.showAlert(`Invalid file type: ${file.name}. Please use JPEG, PNG, or WebP.`, 'error');
            return false;
        }

        if (file.size > maxSize) {
            this.showAlert(`File too large: ${file.name}. Maximum size is ${maxSize / (1024 * 1024)}MB.`, 'error');
            return false;
        }

        return true;
    }

    /**
     * Handle form submission
     */
    async handleSubmit(e) {
        e.preventDefault();

        if (this.state.isSubmitting) {
            return; // Prevent double submission
        }

        if (!this.validateForm()) {
            this.showAlert('Please correct the errors before submitting.', 'error');
            return;
        }

        if (!await this.confirmSubmission()) {
            return;
        }

        this.state.isSubmitting = true;

        try {
            this.showLoading(true);

            const formData = new FormData(e.target);
            formData.append('csrf_token', this.state.csrfToken);

            const response = await this.retryableAjax({
                url: 'submit-student-registration.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 30000
            });

            if (response.success) {
                this.handleSubmissionSuccess(response);
            } else {
                this.handleSubmissionError(response);
            }
        } catch (error) {
            this.handleNetworkError(error);
        } finally {
            this.state.isSubmitting = false;
            this.showLoading(false);
        }
    }

    /**
     * Confirm submission with user
     */
    async confirmSubmission() {
        return new Promise((resolve) => {
            const confirmed = confirm('Are you sure you want to register this student? This action cannot be undone.');
            resolve(confirmed);
        });
    }

    /**
     * Handle successful submission
     */
    handleSubmissionSuccess(response) {
        this.showAlert(`✅ ${response.message}`, 'success');

        // Reset form
        this.resetForm();

        // Show success details
        setTimeout(() => {
            alert(`Student registered successfully!\nStudent ID: ${response.student_id || 'N/A'}`);
        }, 1000);
    }

    /**
     * Handle submission error
     */
    handleSubmissionError(response) {
        const message = response.message || 'An error occurred during registration.';
        this.showAlert(`❌ ${message}`, 'error');

        // Provide recovery suggestions based on error type
        if (message.includes('email') && message.includes('already exists')) {
            this.showAlert('💡 Try using a different email address or contact support if you believe this is an error.', 'info');
        } else if (message.includes('registration number') && message.includes('already exists')) {
            this.showAlert('💡 This registration number is already in use. Please verify your details or contact your department.', 'info');
        } else if (message.includes('network') || message.includes('connection')) {
            this.showAlert('🔄 Network error detected. Your data may have been saved. Please check your connection and try again.', 'warning');
        } else if (message.includes('file') || message.includes('upload')) {
            this.showAlert('📁 File upload failed. Please check your images and try again.', 'warning');
        }

        // Log error for debugging
        console.error('Registration submission error:', response);
    }

    /**
     * Handle network error
     */
    handleNetworkError(error) {
        console.error('Network error:', error);
        this.showAlert('❌ Network error. Please check your connection and try again.', 'error');

        // Offer retry option for network errors
        setTimeout(() => {
            if (confirm('Network error occurred. Would you like to retry the submission?')) {
                this.handleSubmit({ preventDefault: () => {} });
            }
        }, 2000);
    }

    /**
     * Show loading overlay
     */
    showLoading(show) {
        const $overlay = $(this.selectors.loadingOverlay);
        const $submitBtn = $('#submitBtn');

        if (show) {
            $overlay.removeClass('d-none');
            $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Registering...');
        } else {
            $overlay.addClass('d-none');
            $submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Register Student');
        }
    }

    /**
     * Show alert message
     */
    showAlert(message, type = 'info', autoDismiss = true) {
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';

        const icon = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-triangle',
            'warning': 'fa-exclamation-circle',
            'info': 'fa-info-circle'
        }[type] || 'fa-info-circle';

        const $alertContainer = $(this.selectors.alertContainer);
        const alert = $(`
            <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                <i class="fas ${icon} me-2"></i>
                ${this.escapeHtml(message)}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);

        $alertContainer.html(alert);

        // Auto-dismiss after 5 seconds
        if (autoDismiss) {
            setTimeout(() => {
                alert.alert('close');
            }, 5000);
        }
    }

    /**
     * Update form progress
     */
    updateProgress() {
        const $form = $(this.selectors.form);
        const totalFields = $form.find('[required]').length;
        const filledFields = $form.find('[required]').filter(function() {
            return $(this).val().trim().length > 0;
        }).length;

        const progress = totalFields > 0 ? Math.round((filledFields / totalFields) * 100) : 0;

        $('#formProgress').css('width', progress + '%');
        $('#progressText').text(progress + '% complete');
    }

    /**
     * Start fingerprint capture
     */
    async startFingerprintCapture() {
        if (this.state.isCapturing) return;

        this.state.isCapturing = true;
        this.updateFingerprintUI('capturing');

        try {
            // Simulate fingerprint capture (replace with actual implementation)
            this.showAlert('🔍 Starting fingerprint capture...', 'info');

            // In a real implementation, this would interface with fingerprint hardware
            // For now, we'll simulate the process
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Simulate successful capture
            this.state.fingerprintCaptured = true;
            this.state.fingerprintData = 'simulated_fingerprint_data_' + Date.now();
            this.state.fingerprintQuality = Math.floor(Math.random() * 100) + 1;

            this.updateFingerprintUI('captured');
            this.showAlert('✅ Fingerprint captured successfully!', 'success');

        } catch (error) {
            console.error('Fingerprint capture error:', error);
            this.updateFingerprintUI('error');
            this.showAlert('❌ Failed to capture fingerprint. Please try again.', 'error');
        } finally {
            this.state.isCapturing = false;
        }
    }

    /**
     * Clear fingerprint data
     */
    clearFingerprint() {
        this.state.fingerprintCaptured = false;
        this.state.fingerprintData = null;
        this.state.fingerprintQuality = 0;
        this.updateFingerprintUI('ready');
        this.showAlert('🗑️ Fingerprint data cleared.', 'info');
    }

    /**
     * Enroll fingerprint
     */
    async enrollFingerprint() {
        if (!this.state.fingerprintCaptured) {
            this.showAlert('❌ No fingerprint captured to enroll.', 'error');
            return;
        }

        try {
            this.updateFingerprintUI('enrolling');

            // Simulate enrollment process
            await new Promise(resolve => setTimeout(resolve, 1500));

            // In a real implementation, send data to server for storage
            this.showAlert('✅ Fingerprint enrolled successfully!', 'success');
            this.updateFingerprintUI('enrolled');

        } catch (error) {
            console.error('Fingerprint enrollment error:', error);
            this.updateFingerprintUI('error');
            this.showAlert('❌ Failed to enroll fingerprint. Please try again.', 'error');
        }
    }

    /**
     * Update fingerprint UI state
     */
    updateFingerprintUI(state) {
        const $canvas = $('#fingerprintCanvas');
        const $placeholder = $('#fingerprintPlaceholder');
        const $status = $('#fingerprintStatus');
        const $captureBtn = $('#captureFingerprintBtn');
        const $clearBtn = $('#clearFingerprintBtn');
        const $enrollBtn = $('#enrollFingerprintBtn');

        switch (state) {
            case 'ready':
                $canvas.addClass('d-none');
                $placeholder.removeClass('d-none').html(`
                    <i class="fas fa-fingerprint fa-3x text-muted mb-2"></i>
                    <p class="text-muted small">No fingerprint captured</p>
                `);
                $status.text('Ready to capture fingerprint');
                $captureBtn.removeClass('d-none');
                $clearBtn.addClass('d-none');
                $enrollBtn.addClass('d-none');
                break;

            case 'capturing':
                $placeholder.html(`
                    <i class="fas fa-spinner fa-spin fa-3x text-primary mb-2"></i>
                    <p class="text-muted small">Capturing fingerprint...</p>
                `);
                $status.text('Capturing...');
                $captureBtn.prop('disabled', true);
                break;

            case 'captured':
                $canvas.removeClass('d-none');
                $placeholder.addClass('d-none');
                // In real implementation, draw fingerprint image on canvas
                $status.html(`<span class="text-success">Captured (Quality: ${this.state.fingerprintQuality}%)</span>`);
                $captureBtn.addClass('d-none');
                $clearBtn.removeClass('d-none');
                $enrollBtn.removeClass('d-none');
                break;

            case 'enrolling':
                $status.html('<span class="text-warning"><i class="fas fa-spinner fa-spin me-1"></i>Enrolling...</span>');
                $enrollBtn.prop('disabled', true);
                break;

            case 'enrolled':
                $status.html('<span class="text-success">Enrolled successfully</span>');
                $enrollBtn.addClass('d-none');
                break;

            case 'error':
                $status.html('<span class="text-danger">Error occurred</span>');
                $captureBtn.prop('disabled', false);
                break;
        }
    }

    /**
     * Reset form
     */
    resetForm() {
        const $form = $(this.selectors.form);

        // Reset form fields
        $form[0].reset();

        // Clear validation classes
        $form.find('.is-valid, .is-invalid').removeClass('is-valid is-invalid');

        // Reset state
        this.state.selectedFaceImagesCount = 0;
        this.state.fingerprintCaptured = false;
        this.state.fingerprintData = null;
        this.state.fingerprintQuality = 0;

        // Reset UI elements
        this.clearFaceImages();
        this.updateFingerprintUI('ready');
        this.resetLocationFields('province');
        this.resetProgramSelection();
        this.updateProgress();

        // Reset progress bar
        $('#formProgress').css('width', '0%');
        $('#progressText').text('0% complete');

        // Clear alerts
        $(this.selectors.alertContainer).empty();

        this.showAlert('🔄 Form reset successfully.', 'info');
    }

    /**
     * Show welcome message
     */
    showWelcomeMessage() {
        this.showAlert('👋 Welcome to Student Registration! Please fill in all required fields.', 'info');
    }

    /**
     * Set up global error handler
     */
    setupGlobalErrorHandler() {
        window.addEventListener('error', (event) => {
            console.error('Global error:', event.error);
            this.showAlert('An unexpected error occurred. Please refresh the page if issues persist.', 'error');
        });

        window.addEventListener('unhandledrejection', (event) => {
            console.error('Unhandled promise rejection:', event.reason);
            this.showAlert('An unexpected error occurred. Please try again.', 'error');
        });
    }

    /**
     * Check server connectivity
     */
    async checkServerConnectivity() {
        try {
            const response = await this.retryableAjax({
                url: 'api/location-api.php',
                method: 'POST',
                data: {
                    action: 'ping',
                    csrf_token: this.state.csrfToken
                },
                timeout: 5000
            });

            if (response.success) {
                console.log('Server connectivity check passed');
            }
        } catch (error) {
            console.warn('Server connectivity check failed:', error);
            this.showAlert('⚠️ Server connection may be slow. Some features might not work properly.', 'warning');
        }
    }

    /**
     * Get error message from AJAX error
     */
    getErrorMessage(error) {
        if (error.responseJSON && error.responseJSON.message) {
            return error.responseJSON.message;
        }
        if (error.statusText) {
            return error.statusText;
        }
        return error.message || 'Unknown error';
    }

    /**
     * Utility method to escape HTML
     */
    escapeHtml(unsafe) {
        if (!unsafe) return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    /**
     * Debounce function for performance
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
     * Show loading state for location fields
     */
    showLocationLoading($element, show) {
        const loadingClass = 'location-loading';
        $element.toggleClass(loadingClass, show);
    }

    /**
     * Reset program selection
     */
    resetProgramSelection() {
        const $program = $(this.selectors.program);

        // Show all programs again
        $program.find('option[data-department]').removeClass('d-none');
        $program.find('option:first').text('🎓 Select Your Program (All Departments)');
        $program.prop('disabled', false)
                .removeClass('programs-loaded')
                .removeData('department-id');

        $('#programCount').addClass('d-none');
        $('#programLoadedIcon').addClass('d-none');
        $('#programHelp .fas').removeClass('fa-check-circle text-success').addClass('fa-info-circle text-info');
        $('#programHelp small').html('<strong class="text-muted">Select your academic department above to filter available programs</strong>');
    }
}

// Initialize the application when DOM is ready
$(document).ready(function() {
    window.studentRegistration = new StudentRegistration();
});