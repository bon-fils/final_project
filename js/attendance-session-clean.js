/**
 * Attendance Session Management JavaScript - Clean Version
 * Fixed all syntax errors and provides working functionality
 */

// Configuration
const ATTENDANCE_CONFIG = {
    ENABLE_REAL_MODE: true,
    SHOW_NOTIFICATIONS: true,
    ENABLE_BACKEND_INTEGRATION: true,
    API_BASE_URL: 'api/',
    LOG_LEVEL: 'info'
};

// Global state management
const AttendanceState = {
    departments: [],
    options: [],
    courses: [],
    currentSession: null,
    selectedDepartment: null,
    selectedOption: null,
    selectedCourse: null,
    isSessionActive: false,

    setDepartments: function(depts) {
        this.departments = depts;
    },

    setOptions: function(opts) {
        this.options = opts;
    },

    setCourses: function(courses) {
        this.courses = courses;
    },

    setSession: function(session) {
        this.currentSession = session;
        this.isSessionActive = !!session;
    }
};

// Utility functions
const Utils = {
    showNotification: function(message, type = 'info', duration = 3000) {
        console.log(`${type.toUpperCase()}: ${message}`);
        
        // Create notification container if it doesn't exist
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }
        
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${this.getBootstrapType(type)} alert-dismissible fade show`;
        notification.style.cssText = `
            margin-bottom: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: slideInRight 0.3s ease-out;
        `;
        
        // Get icon based on type
        const icon = this.getNotificationIcon(type);
        
        // Set notification content
        notification.innerHTML = `
            <i class="fas ${icon} me-2"></i>
            <span style="white-space: pre-line;">${message}</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Add to container
        container.appendChild(notification);
        
        // Auto-remove after duration
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    },
    
    getBootstrapType: function(type) {
        const typeMap = {
            'success': 'success',
            'error': 'danger',
            'warning': 'warning',
            'info': 'info'
        };
        return typeMap[type] || 'info';
    },
    
    getNotificationIcon: function(type) {
        const iconMap = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        return iconMap[type] || 'fa-info-circle';
    },

    showLoading: function(element, text = 'Loading...') {
        if (element) {
            element.innerHTML = `<option value="" disabled selected>${text}</option>`;
            element.disabled = true;
        }
    },

    hideLoading: function(element, originalText = '') {
        if (element) {
            element.disabled = false;
        }
    },

    validateForm: function() {
        const requiredFields = ['department', 'option', 'course', 'year_level', 'biometric_method'];
        let isValid = true;
        let filledCount = 0;

        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && (!field.value || field.value === '')) {
                isValid = false;
            } else if (field && field.value) {
                filledCount++;
            }
        });

        // Update start button state
        const startButton = document.getElementById('start-session');
        if (startButton) {
            if (isValid) {
                startButton.disabled = false;
                startButton.classList.remove('btn-secondary');
                startButton.classList.add('btn-success');
                startButton.innerHTML = '<i class="fas fa-play me-2"></i>Start Attendance Session';
            } else {
                startButton.disabled = true;
                startButton.classList.remove('btn-success');
                startButton.classList.add('btn-secondary');
                startButton.innerHTML = `<i class="fas fa-lock me-2"></i>Fill All Fields (${filledCount}/${requiredFields.length})`;
            }
        }

        console.log(`✓ Form validation: ${isValid ? 'VALID' : 'INVALID'} (${filledCount}/${requiredFields.length} fields filled)`);
        return isValid;
    },

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
    }
};

// API functions
const API = {
    async call(endpoint, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        const finalOptions = { ...defaultOptions, ...options };

        try {
            const response = await fetch(`${ATTENDANCE_CONFIG.API_BASE_URL}${endpoint}`, finalOptions);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error(`API call failed: ${endpoint}`, error);
            throw error;
        }
    },

    async getDepartments() {
        return this.call('get-departments.php');
    },

    async getOptions(departmentId) {
        return this.call(`get-options.php?department_id=${departmentId}`);
    },

    async getCourses(departmentId, optionId) {
        return this.call(`get-courses.php?department_id=${departmentId}&option_id=${optionId}`);
    },

    async startSession(sessionData) {
        return this.call('start-session.php', {
            method: 'POST',
            body: JSON.stringify(sessionData)
        });
    }
};

// Form handlers
const FormHandlers = {
    isCreatingSession: false, // Flag to prevent double submission
    
    initialize: function() {
        console.log('🚀 Initializing form handlers...');
        this.setupEventListeners();
        this.loadInitialData();
        this.initializeAudioContext();
    },
    
    initializeAudioContext: function() {
        // Initialize AudioContext on first user interaction to avoid autoplay policy
        const initAudio = () => {
            if (!window.attendanceAudioContext) {
                try {
                    window.attendanceAudioContext = new (window.AudioContext || window.webkitAudioContext)();
                    console.log('🔊 Audio context initialized');
                } catch (e) {
                    console.log('Audio not available');
                }
            }
            // Remove listeners after first interaction
            document.removeEventListener('click', initAudio);
            document.removeEventListener('touchstart', initAudio);
        };
        
        // Wait for first user interaction
        document.addEventListener('click', initAudio, { once: true });
        document.addEventListener('touchstart', initAudio, { once: true });
    },

    setupEventListeners: function() {
        // Department change
        const departmentSelect = document.getElementById('department');
        if (departmentSelect) {
            departmentSelect.addEventListener('change', (e) => {
                console.log('🏫 Department changed:', e.target.value);
                this.handleDepartmentChange(e.target.value);
                Utils.validateForm();
            });
        }

        // Option change
        const optionSelect = document.getElementById('option');
        if (optionSelect) {
            optionSelect.addEventListener('change', (e) => {
                console.log('📚 Option changed:', e.target.value);
                this.handleOptionChange(e.target.value);
                Utils.validateForm();
            });
        }

        // Course change
        const courseSelect = document.getElementById('course');
        if (courseSelect) {
            courseSelect.addEventListener('change', (e) => {
                console.log('📖 Course changed:', e.target.value);
                this.handleCourseChange(e.target.value);
                Utils.validateForm();
            });
        }

        // Year level change
        const yearSelect = document.getElementById('year_level');
        if (yearSelect) {
            yearSelect.addEventListener('change', (e) => {
                console.log('🎓 Year level changed:', e.target.value);
                Utils.validateForm();
            });
        }

        // Biometric method change
        const biometricSelect = document.getElementById('biometric_method');
        if (biometricSelect) {
            biometricSelect.addEventListener('change', (e) => {
                console.log('🔐 Biometric method changed:', e.target.value);
                Utils.validateForm();
            });
        }

        // Start session button
        const startBtn = document.getElementById('start-session');
        if (startBtn) {
            startBtn.addEventListener('click', (e) => this.handleStartSession(e));
        }

        // Initial validation on page load
        setTimeout(() => {
            Utils.validateForm();
        }, 500);

        console.log('✅ Event listeners setup complete');
    },

    async loadInitialData() {
        console.log('📊 Loading initial data...');
        
        // Check for existing active session first
        if (window.BACKEND_CONFIG && window.BACKEND_CONFIG.ACTIVE_SESSION) {
            console.log('✅ Active session found, loading session view...');
            this.loadExistingSession(window.BACKEND_CONFIG.ACTIVE_SESSION);
            return; // Don't load form data if session is active
        }
        
        // Auto-load department data if available
        if (window.BACKEND_CONFIG && window.BACKEND_CONFIG.DEPARTMENT_ID) {
            AttendanceState.selectedDepartment = window.BACKEND_CONFIG.DEPARTMENT_ID;
            await this.loadOptions(window.BACKEND_CONFIG.DEPARTMENT_ID);
        }
    },

    loadExistingSession(sessionData) {
        console.log('🔄 Loading existing active session:', sessionData);
        
        // Store session data
        AttendanceState.setSession(sessionData);
        window.currentSession = sessionData;
        
        // Show active session view immediately
        this.showActiveSession(sessionData);
        
        // Show notification
        Utils.showNotification(
            `✅ Continuing active session: ${sessionData.course_name} (${sessionData.course_code})`,
            'success'
        );
    },

    async loadExistingSessionById(sessionId) {
        console.log('🔍 Fetching existing session data for ID:', sessionId);
        
        try {
            // Fetch full session data from server
            const response = await fetch(`api/get-session-details.php?session_id=${sessionId}`);
            const result = await response.json();
            
            if (result.status === 'success' && result.session) {
                console.log('✅ Session data loaded:', result.session);
                this.loadExistingSession(result.session);
            } else {
                throw new Error(result.message || 'Failed to load session details');
            }
        } catch (error) {
            console.error('❌ Failed to load existing session:', error);
            Utils.showNotification('❌ Failed to load existing session. Please refresh the page.', 'error');
            
            // Fallback: just reload the page
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        }
    },

    async handleDepartmentChange(departmentId) {
        console.log('🏫 Department changed:', departmentId);
        AttendanceState.selectedDepartment = departmentId;

        if (departmentId) {
            await this.loadOptions(departmentId);
        } else {
            this.resetOptions();
            this.resetCourses();
        }

        Utils.validateForm();
    },

    async handleOptionChange(optionId) {
        console.log('📚 Option changed:', optionId);
        AttendanceState.selectedOption = optionId;

        if (optionId && AttendanceState.selectedDepartment) {
            console.log('📚 Loading courses for department:', AttendanceState.selectedDepartment, 'option:', optionId);
            await this.loadCourses(AttendanceState.selectedDepartment, optionId);
            Utils.showNotification('✅ Option selected! Now choose your course.', 'info');
        } else {
            console.log('📚 Resetting courses - no valid option or department');
            this.resetCourses();
        }

        Utils.validateForm();
    },

    handleCourseChange(courseId) {
        console.log('📖 Course changed:', courseId);
        AttendanceState.selectedCourse = courseId;

        if (courseId) {
            Utils.showNotification('✅ Course selected! Now select a biometric method.', 'info');
        }

        Utils.validateForm();
    },

    async endExistingAndRetry(existingSessionId, newSessionData) {
        console.log('🔄 Ending existing session and retrying...', existingSessionId);
        
        try {
            // End the existing session
            const endResponse = await fetch('api/end-session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    session_id: existingSessionId
                })
            });
            
            const endResult = await endResponse.json();
            
            if (endResult.status === 'success') {
                console.log('✅ Existing session ended, retrying new session...');
                Utils.showNotification('✅ Previous session ended', 'success');
                
                // Wait a moment then retry
                setTimeout(async () => {
                    const retryResponse = await API.startSession(newSessionData);
                    
                    if (retryResponse.status === 'success') {
                        AttendanceState.setSession(retryResponse.session);
                        Utils.showNotification('✅ New session started successfully!', 'success');
                        this.showActiveSession(retryResponse.session);
                    } else {
                        throw new Error(retryResponse.message);
                    }
                }, 500);
                
            } else {
                throw new Error('Failed to end existing session: ' + endResult.message);
            }
            
        } catch (error) {
            console.error('❌ Error ending existing session:', error);
            Utils.showNotification('❌ Failed to end existing session. Please refresh and try again.', 'error');
        }
    },

    async handleStartSession(e) {
        e.preventDefault();

        // Prevent double submission
        if (this.isCreatingSession) {
            console.log('⚠️ Session creation already in progress, ignoring duplicate request');
            return;
        }

        if (!Utils.validateForm()) {
            Utils.showNotification('❌ Please complete all form fields', 'error');
            return;
        }

        const sessionData = {
            department_id: document.getElementById('department').value,
            option_id: document.getElementById('option').value,
            course_id: document.getElementById('course').value,
            year_level: document.getElementById('year_level').value,
            biometric_method: document.getElementById('biometric_method').value,
            lecturer_id: window.BACKEND_CONFIG ? window.BACKEND_CONFIG.LECTURER_ID : null
        };

        const startBtn = document.getElementById('start-session');
        Utils.showLoading(startBtn, 'Starting Session...');

        // Set flag to prevent double submission
        this.isCreatingSession = true;

        try {
            console.log('📤 Sending session creation request with data:', sessionData);
            const response = await API.startSession(sessionData);
            console.log('📡 Session creation response:', response);

            if (response.status === 'success') {
                console.log('✅ NEW SESSION CREATED SUCCESSFULLY - ID:', response.session.id);
                AttendanceState.setSession(response.session);
                Utils.showNotification('✅ Session started successfully!', 'success');
                this.showActiveSession(response.session);
            } else {
                // Check if there's an existing session
                if (response.existing_session_id) {
                    console.warn('⚠️ EXISTING SESSION DETECTED - ID:', response.existing_session_id);
                    console.warn('This means there was ALREADY an active session before we tried to create new one');
                    
                    const endExisting = confirm(
                        '⚠️ You already have an active session (ID: ' + response.existing_session_id + ')\n\n' +
                        'Would you like to end it and start a new one?\n\n' +
                        'Click OK to end existing session\n' +
                        'Click Cancel to keep existing session'
                    );
                    
                    if (endExisting) {
                        // End existing session and retry
                        await this.endExistingAndRetry(response.existing_session_id, sessionData);
                        return;
                    } else {
                        // User wants to keep existing session - load it
                        Utils.showNotification('⚠️ Loading existing active session...', 'info');
                        await this.loadExistingSessionById(response.existing_session_id);
                        return;
                    }
                }
                
                // Show detailed error information
                console.error('❌ Session creation failed:', response);
                console.error('Debug info:', response.debug);
                console.error('SQL State:', response.sql_state);
                console.error('Error Info:', response.error_info);
                
                let errorMessage = response.message || 'Failed to start session';
                if (response.debug) {
                    errorMessage += '\nDetails: ' + response.debug;
                }
                
                throw new Error(errorMessage);
            }
        } catch (error) {
            console.error('❌ Failed to start session:', error);
            console.error('Full error object:', error);
            
            // Show user-friendly message with details
            let displayMessage = '❌ Failed to start session';
            if (error.message.includes('Database error')) {
                displayMessage += '\n\n🔍 This appears to be a database issue. Please check:\n';
                displayMessage += '1. Does the attendance_sessions table exist?\n';
                displayMessage += '2. Are all required fields present in the table?\n';
                displayMessage += '3. Check browser console for detailed error info';
            }
            
            Utils.showNotification(displayMessage, 'error');
            alert(displayMessage + '\n\nCheck the browser console (F12) for technical details.');
        } finally {
            Utils.hideLoading(startBtn, '<i class="fas fa-play me-2"></i>Start Attendance Session');
            // Reset flag to allow future submissions
            this.isCreatingSession = false;
        }
    },

    async loadOptions(departmentId) {
        const optionSelect = document.getElementById('option');
        if (!optionSelect) {
            console.error('❌ Option select element not found');
            return;
        }

        console.log('🔄 Loading options for department:', departmentId);
        Utils.showLoading(optionSelect, 'Loading options...');

        try {
            const response = await API.getOptions(departmentId);
            console.log('📡 API Response:', response);

            if (response.status === 'success' && response.data && response.data.length > 0) {
                optionSelect.innerHTML = '<option value="" disabled selected>Choose an academic option</option>';
                
                response.data.forEach((option, index) => {
                    console.log(`📝 Adding option ${index + 1}:`, option);
                    const optionElement = document.createElement('option');
                    optionElement.value = option.id;
                    optionElement.textContent = option.name;
                    optionSelect.appendChild(optionElement);
                });

                AttendanceState.setOptions(response.data);
                optionSelect.disabled = false;
                console.log('✅ Options loaded successfully');

                const feedbackEl = document.getElementById('option-feedback');
                if (feedbackEl) {
                    feedbackEl.innerHTML = '<i class="fas fa-check text-success me-1"></i>Options loaded successfully';
                    feedbackEl.className = 'form-text text-success';
                }
            } else {
                optionSelect.innerHTML = '<option value="" disabled selected>No options available</option>';
                optionSelect.disabled = true;
                console.log('⚠️ No options found for department:', departmentId);
            }
        } catch (error) {
            console.error('❌ Failed to load options:', error);
            optionSelect.innerHTML = '<option value="" disabled selected>Error loading options</option>';
            optionSelect.disabled = true;
            Utils.showNotification('❌ Failed to load department options. Please try refreshing the page.', 'error');
        } finally {
            Utils.hideLoading(optionSelect);
            Utils.validateForm(); // Validate after options load
        }
    },

    async loadCourses(departmentId, optionId) {
        const courseSelect = document.getElementById('course');
        if (!courseSelect) return;

        console.log('🔄 Loading courses for department:', departmentId, 'option:', optionId);
        Utils.showLoading(courseSelect, 'Loading courses...');

        try {
            const response = await API.getCourses(departmentId, optionId);

            if (response.status === 'success' && response.data && response.data.length > 0) {
                courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';

                response.data.forEach(course => {
                    const optionElement = document.createElement('option');
                    optionElement.value = course.id;
                    optionElement.textContent = `${course.name} (${course.course_code})`;
                    courseSelect.appendChild(optionElement);
                });

                AttendanceState.setCourses(response.data);
                courseSelect.disabled = false;
                Utils.showNotification(`✅ Found ${response.data.length} course(s) for selected option.`, 'success');
                console.log('✅ Courses loaded:', response.data.length);
            } else {
                courseSelect.innerHTML = '<option value="" disabled selected>No courses available</option>';
                courseSelect.disabled = true;
                Utils.showNotification('⚠️ No courses found for the selected option.', 'warning');
                console.log('⚠️ No courses found for department:', departmentId, 'option:', optionId);
            }
        } catch (error) {
            console.error('Failed to load courses:', error);
            courseSelect.innerHTML = '<option value="" disabled selected>Error loading courses</option>';
            courseSelect.disabled = true;
            Utils.showNotification('❌ Failed to load courses. Please check your internet connection and try again.', 'error');
        } finally {
            Utils.hideLoading(courseSelect);
            Utils.validateForm(); // Validate after courses load
        }
    },

    resetOptions() {
        const optionSelect = document.getElementById('option');
        if (optionSelect) {
            optionSelect.innerHTML = '<option value="" disabled selected>Select Option</option>';
            optionSelect.disabled = true;
        }
        AttendanceState.setOptions([]);
    },

    resetCourses() {
        const courseSelect = document.getElementById('course');
        if (courseSelect) {
            courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
            courseSelect.disabled = true;
        }
        AttendanceState.setCourses([]);
    },

    showActiveSession(sessionData) {
        console.log('📱 Showing active session:', sessionData);
        
        // Hide setup section
        const setupSection = document.getElementById('sessionSetupSection');
        if (setupSection) {
            setupSection.style.display = 'none';
        }

        // Show active session section
        const activeSection = document.getElementById('activeSessionSection');
        if (activeSection) {
            activeSection.style.display = 'block';
        }

        // Update session info
        const sessionInfo = document.getElementById('sessionInfo');
        if (sessionInfo) {
            sessionInfo.innerHTML = `
                <h3><i class="fas fa-check-circle text-success me-2"></i>Attendance Session Active</h3>
                <div class="session-details mt-3">
                    <p><strong>Course:</strong> ${sessionData.course_name} (${sessionData.course_code})</p>
                    <p><strong>Program:</strong> ${sessionData.option_name}</p>
                    <p><strong>Year Level:</strong> ${sessionData.year_level}</p>
                    <p><strong>Started:</strong> ${sessionData.start_time}</p>
                    <p><strong>Method:</strong> ${sessionData.biometric_method === 'face' ? 'Face Recognition' : 'Fingerprint'}</p>
                </div>
            `;
        }

        // Update statistics
        document.getElementById('total-students').textContent = sessionData.total_students || 0;
        document.getElementById('present-count').textContent = sessionData.students_present || 0;
        document.getElementById('absent-count').textContent = (sessionData.total_students || 0) - (sessionData.students_present || 0);
        
        const attendanceRate = sessionData.total_students > 0 
            ? Math.round((sessionData.students_present / sessionData.total_students) * 100) 
            : 0;
        document.getElementById('attendance-rate').textContent = attendanceRate + '%';

        // Show appropriate biometric interface
        this.showBiometricInterface(sessionData.biometric_method);
        
        // Store session data globally
        window.currentSession = sessionData;
        
        Utils.showNotification('✅ Attendance session started successfully!', 'success');
    },

    showBiometricInterface(method) {
        console.log('🔐 Showing biometric interface for:', method);
        
        // Hide both interfaces first
        const faceSection = document.getElementById('faceRecognitionSection');
        const fingerSection = document.getElementById('fingerprintSection');
        
        if (faceSection) faceSection.style.display = 'none';
        if (fingerSection) fingerSection.style.display = 'none';
        
        // Show the correct interface
        if (method === 'face_recognition' && faceSection) {
            faceSection.style.display = 'block';
            console.log('✅ Face recognition interface shown');
            // Initialize camera
            setTimeout(() => {
                this.initializeCamera();
            }, 500);
        } else if (method === 'fingerprint' && fingerSection) {
            fingerSection.style.display = 'block';
            console.log('✅ Fingerprint interface shown');
            // Initialize fingerprint scanner
            setTimeout(() => {
                this.initializeFingerprintScanner();
            }, 500);
        }
    }
};

// Session Manager (for compatibility)
const SessionManager = FormHandlers;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initializing Attendance Session Management');
    FormHandlers.initialize();
});

// Face Recognition Camera System
const FaceRecognitionSystem = {
    stream: null,
    video: null,
    canvas: null,
    isCapturing: false,
    autoScanInterval: null,
    lastScanTime: 0,
    scanCooldown: 3000, // 3 seconds between scans

    async initializeCamera() {
        console.log('📷 Initializing camera for face recognition...');
        
        this.video = document.getElementById('webcam-preview');
        const placeholder = document.getElementById('webcam-placeholder');
        const statusEl = document.getElementById('webcam-status');
        const markBtn = document.getElementById('markAttendanceBtn');
        
        try {
            // Request camera access
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: 'user'
                },
                audio: false
            });
            
            // Show video feed
            this.video.srcObject = this.stream;
            this.video.style.display = 'block';
            placeholder.style.display = 'none';
            
            // Hide manual button (auto-scan will handle it)
            if (markBtn) {
                markBtn.style.display = 'none';
            }
            
            if (statusEl) {
                statusEl.innerHTML = '<i class="fas fa-circle text-success me-1"></i>Camera Active - Auto-scanning...';
            }
            
            console.log('✅ Camera initialized successfully');
            Utils.showNotification('✅ Camera is ready! Auto-scanning for faces...', 'success');
            
            // Create status overlay on video
            this.createStatusOverlay();
            
            // Start automatic face detection after video is ready
            this.video.addEventListener('loadeddata', () => {
                console.log('📹 Video stream ready, starting auto-scan...');
                this.startAutoScan();
            });
            
        } catch (error) {
            console.error('❌ Camera access denied:', error);
            
            if (placeholder) {
                placeholder.innerHTML = '<i class="fas fa-exclamation-triangle fa-3x mb-3 text-danger"></i><div>Camera access denied</div>';
            }
            
            if (statusEl) {
                statusEl.innerHTML = '<i class="fas fa-circle text-danger me-1"></i>Camera Error';
            }
            
            Utils.showNotification('❌ Camera access denied. Please allow camera permissions.', 'error');
        }
    },
    
    startAutoScan() {
        console.log('🔄 Starting automatic face detection...');
        
        // Clear any existing interval
        if (this.autoScanInterval) {
            clearInterval(this.autoScanInterval);
        }
        
        // Scan every 2 seconds
        this.autoScanInterval = setInterval(() => {
            this.autoDetectAndRecognize();
        }, 2000);
        
        // Also do first scan immediately
        setTimeout(() => this.autoDetectAndRecognize(), 500);
    },
    
    stopAutoScan() {
        if (this.autoScanInterval) {
            clearInterval(this.autoScanInterval);
            this.autoScanInterval = null;
            console.log('⏸️ Auto face detection stopped');
        }
    },
    
    async autoDetectAndRecognize() {
        // Skip if already capturing or in cooldown
        const now = Date.now();
        if (this.isCapturing || (now - this.lastScanTime) < this.scanCooldown) {
            return;
        }
        
        // Skip if video not ready
        if (!this.video || !this.video.videoWidth || !this.video.videoHeight) {
            return;
        }
        
        console.log('👁️ Auto-detecting face...');
        await this.captureAndRecognize();
    },

    async captureAndRecognize() {
        if (this.isCapturing) return;
        
        this.isCapturing = true;
        const markBtn = document.getElementById('markAttendanceBtn');
        if (markBtn) {
            markBtn.disabled = true;
            markBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        }
        
        try {
            // Create canvas for image capture
            if (!this.canvas) {
                this.canvas = document.createElement('canvas');
            }
            
            // Set canvas size to match video
            this.canvas.width = this.video.videoWidth;
            this.canvas.height = this.video.videoHeight;
            
            // Draw current video frame to canvas
            const ctx = this.canvas.getContext('2d');
            ctx.drawImage(this.video, 0, 0);
            
            // Convert to base64 image
            const imageData = this.canvas.toDataURL('image/jpeg', 0.8);
            
            console.log('📸 Image captured, sending for recognition...');
            this.updateStatusOverlay('Analyzing face...', 'scanning');
            
            // Send to server for face recognition
            const response = await fetch('api/recognize-face.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    image: imageData,
                    session_id: window.currentSession ? window.currentSession.id : null
                })
            });
            
            const result = await response.json();
            console.log('📡 Recognition result:', result);
            
            // Update last scan time
            this.lastScanTime = Date.now();
            
            if (result.status === 'success' && result.student) {
                console.log('✅ SUCCESS: Face recognized!', result.student.name);
                
                this.updateStatusOverlay(`✅ ${result.student.name}`, 'success');
                
                Utils.showNotification(
                    `✅ Attendance marked!\n${result.student.name} (${result.student.reg_no})\nConfidence: ${result.confidence || 'N/A'}%`,
                    'success'
                );
                
                // Update attendance count
                this.updateAttendanceStats();
                
                // Play success sound
                FingerprintSystem.playSuccessSound();
                
                // Show success animation
                this.showSuccessAnimation(result.student);
                
            } else if (result.status === 'already_marked') {
                console.log('⚠️ Already marked:', result.message);
                
                this.updateStatusOverlay('Already marked', 'warning');
                
                // Show notification with student info if available
                let message = `⚠️ ${result.message}`;
                if (result.student && result.student.name) {
                    message += `\n${result.student.name} (${result.student.reg_no})`;
                }
                if (result.details) {
                    message += `\n${result.details}`;
                }
                
                Utils.showNotification(message, 'warning', 4000);
                
            } else if (result.status === 'not_recognized') {
                console.log('⏳ No face detected or not recognized');
                
                this.updateStatusOverlay('No match found', 'info');
                
                // Show helpful message occasionally (not every scan to avoid spam)
                if (!this.lastNotRecognizedTime || (Date.now() - this.lastNotRecognizedTime) > 10000) {
                    let message = '🔍 No matching face found';
                    if (result.matches_checked) {
                        message += `\nChecked ${result.matches_checked} students`;
                    }
                    if (result.best_distance) {
                        message += `\nClosest match: ${Math.round((1 - result.best_distance) * 100)}% confidence`;
                    }
                    message += '\n\nTip: Ensure you are registered with a photo';
                    
                    Utils.showNotification(message, 'info', 5000);
                    this.lastNotRecognizedTime = Date.now();
                }
                
            } else if (result.status === 'error') {
                console.log('⚠️ Recognition error:', result.message);
                
                // Show error notification for important errors
                const importantErrors = [
                    'No face detected',
                    'Multiple faces detected',
                    'Face recognition service error',
                    'Database error'
                ];
                
                const isImportant = importantErrors.some(err => result.message.includes(err));
                
                if (isImportant) {
                    // Update overlay
                    if (result.message.includes('No face detected')) {
                        this.updateStatusOverlay('No face detected', 'error');
                    } else if (result.message.includes('Multiple faces')) {
                        this.updateStatusOverlay('Multiple faces detected', 'error');
                    } else {
                        this.updateStatusOverlay('Error', 'error');
                    }
                    
                    // Show error but throttle to avoid spam
                    if (!this.lastErrorTime || (Date.now() - this.lastErrorTime) > 5000) {
                        let errorMessage = `⚠️ ${result.message}`;
                        
                        // Add helpful tips based on error type
                        if (result.message.includes('No face detected')) {
                            errorMessage += '\n\n💡 Tips:\n• Ensure good lighting\n• Face the camera directly\n• Move closer to camera';
                        } else if (result.message.includes('Multiple faces')) {
                            errorMessage += '\n\n💡 Tip: Ensure only one person is in frame';
                        }
                        
                        Utils.showNotification(errorMessage, 'warning', 6000);
                        this.lastErrorTime = Date.now();
                    }
                }
                
            } else {
                console.log('⚠️ Recognition failed:', result.message);
                // Silent for other failures
            }
            
        } catch (error) {
            console.error('❌ Face recognition error:', error);
            // Silent for auto-scan errors
        } finally {
            this.isCapturing = false;
            if (markBtn) {
                markBtn.disabled = false;
                markBtn.innerHTML = '<i class="fas fa-user-check me-2"></i>Mark Attendance';
            }
        }
    },
    
    createStatusOverlay() {
        // Create status overlay on video
        const videoContainer = this.video.parentElement;
        if (!videoContainer) return;
        
        // Remove existing overlay if any
        const existing = videoContainer.querySelector('.face-status-overlay');
        if (existing) existing.remove();
        
        const overlay = document.createElement('div');
        overlay.className = 'face-status-overlay';
        overlay.style.cssText = `
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            z-index: 10;
            display: none;
        `;
        overlay.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i>Scanning...';
        
        videoContainer.style.position = 'relative';
        videoContainer.appendChild(overlay);
        
        this.statusOverlay = overlay;
    },
    
    updateStatusOverlay(message, type = 'info') {
        if (!this.statusOverlay) return;
        
        const icons = {
            'scanning': 'fa-circle-notch fa-spin',
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-circle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        };
        
        const colors = {
            'scanning': 'rgba(0, 123, 255, 0.9)',
            'success': 'rgba(40, 167, 69, 0.9)',
            'error': 'rgba(220, 53, 69, 0.9)',
            'warning': 'rgba(255, 193, 7, 0.9)',
            'info': 'rgba(23, 162, 184, 0.9)'
        };
        
        this.statusOverlay.innerHTML = `<i class="fas ${icons[type]} me-2"></i>${message}`;
        this.statusOverlay.style.background = colors[type];
        this.statusOverlay.style.display = 'block';
        
        // Auto-hide after 3 seconds for non-scanning messages
        if (type !== 'scanning') {
            setTimeout(() => {
                if (this.statusOverlay) {
                    this.statusOverlay.style.display = 'none';
                }
            }, 3000);
        }
    },
    
    showSuccessAnimation(student) {
        // Flash green on video container
        const videoContainer = document.querySelector('.card-body');
        if (videoContainer) {
            videoContainer.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                videoContainer.style.backgroundColor = '';
            }, 1000);
        }
    },

    updateAttendanceStats() {
        // Refresh stats from server
        if (window.currentSession) {
            fetch(`api/get-session-stats.php?session_id=${window.currentSession.id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        document.getElementById('present-count').textContent = data.stats.present;
                        document.getElementById('absent-count').textContent = data.stats.absent;
                        document.getElementById('attendance-rate').textContent = data.stats.rate + '%';
                    }
                })
                .catch(err => console.error('Failed to update stats:', err));
        }
    },

    stopCamera() {
        // Stop auto-scanning
        this.stopAutoScan();
        
        // Stop camera stream
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
            console.log('📷 Camera stopped');
        }
        
        // Reset video element
        if (this.video) {
            this.video.srcObject = null;
        }
    }
};

// Fingerprint Scanner System
const FingerprintSystem = {
    isScanning: false,
    autoScanInterval: null,
    scanInProgress: false,
    lastScanTime: 0,
    scanDelay: 2000, // 2 seconds between scans

    async initializeScanner() {
        console.log('👆 Initializing fingerprint scanner...');
        
        // Show scanner status
        const statusEl = document.getElementById('fingerprint-status');
        if (statusEl) {
            statusEl.innerHTML = '<i class="fas fa-circle text-success me-1"></i>Scanner Ready';
        }
        
        // Start automatic scanning
        this.startAutoScan();
        
        Utils.showNotification('👆 Fingerprint scanner active! Place finger on sensor.', 'info');
    },

    startAutoScan() {
        console.log('🔄 Starting automatic fingerprint scanning...');
        
        // Clear any existing interval
        this.stopAutoScan();
        
        // Update UI to show scanning mode
        const scanBtn = document.getElementById('scanFingerprintBtn');
        if (scanBtn) {
            scanBtn.innerHTML = '<i class="fas fa-fingerprint fa-pulse me-2"></i>Scanning Active...';
            scanBtn.disabled = true;
            scanBtn.classList.add('btn-success');
            scanBtn.classList.remove('btn-primary');
        }
        
        // Start continuous scanning
        this.autoScanInterval = setInterval(() => {
            this.scanAndVerify();
        }, this.scanDelay);
        
        // Do first scan immediately
        setTimeout(() => {
            this.scanAndVerify();
        }, 500);
    },

    stopAutoScan() {
        if (this.autoScanInterval) {
            clearInterval(this.autoScanInterval);
            this.autoScanInterval = null;
            console.log('🛑 Automatic scanning stopped');
        }
    },

    async scanAndVerify() {
        // Prevent overlapping scans
        if (this.scanInProgress) {
            console.log('⏳ Scan already in progress, skipping...');
            return;
        }
        
        // Check session
        if (!window.currentSession || !window.currentSession.id) {
            console.log('⚠️ No active session, stopping auto-scan');
            this.stopAutoScan();
            return;
        }
        
        this.scanInProgress = true;
        const scanStartTime = Date.now();
        
        try {
            console.log('👆 Requesting fingerprint scan from ESP32...');
            
            // Update status
            this.updateScanStatus('scanning', 'Scanning...');
            
            // Send request to ESP32 scanner API
            const response = await fetch('api/esp32-scan-fingerprint.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    session_id: window.currentSession.id
                })
            });
            
            const result = await response.json();
            console.log('📡 Scan result:', result);
            
            // Handle different statuses
            if (result.status === 'success') {
                // Success - attendance marked
                this.updateScanStatus('success', `✅ ${result.student.name}`);
                
                Utils.showNotification(
                    `✅ Attendance marked!\n${result.student.name} (${result.student.reg_no})\nConfidence: ${result.confidence}%`,
                    'success'
                );
                
                // Play success sound (optional)
                this.playSuccessSound();
                
                // Update statistics
                FaceRecognitionSystem.updateAttendanceStats();
                
                // Show success animation
                this.showSuccessAnimation(result.student);
                
            } else if (result.status === 'scan_failed') {
                // No finger detected - show brief message
                this.updateScanStatus('waiting', result.message || 'No finger detected');
                console.log('⏳ Scan failed:', result.message);
                
                // Show subtle notification for scan failures
                if (result.details && result.details !== 'Please place finger on sensor') {
                    Utils.showNotification(
                        `⚠️ ${result.message}\n${result.details}`,
                        'warning',
                        3000
                    );
                }
                
            } else if (result.status === 'not_recognized') {
                // Fingerprint not recognized - show clear message
                this.updateScanStatus('warning', '⚠️ Fingerprint not recognized');
                console.log('⚠️ Fingerprint not recognized');
                
                Utils.showNotification(
                    `⚠️ ${result.message}\n\n${result.guidance || 'Please try again or contact administrator'}`,
                    'warning',
                    4000
                );
                
            } else if (result.status === 'already_marked') {
                // Already marked - show warning briefly
                this.updateScanStatus('warning', `⚠️ Already marked: ${result.student.name}`);
                
                Utils.showNotification(
                    `⚠️ ${result.student.name} already marked at ${result.details}`,
                    'warning'
                );
                
                setTimeout(() => {
                    this.updateScanStatus('waiting', 'Place finger on sensor...');
                }, 3000);
                
            } else if (result.status === 'wrong_class') {
                // Wrong class
                this.updateScanStatus('error', result.message);
                
                Utils.showNotification(
                    `❌ ${result.message}\n${result.details}\n\n${result.guidance}`,
                    'error'
                );
                
                setTimeout(() => {
                    this.updateScanStatus('waiting', 'Place finger on sensor...');
                }, 5000);
                
            } else {
                // Other errors
                this.updateScanStatus('error', result.message);
                
                if (result.guidance) {
                    console.error('❌ Scanner error:', result.message);
                    console.error('Guidance:', result.guidance);
                }
                
                // Show error briefly, then back to waiting
                setTimeout(() => {
                    this.updateScanStatus('waiting', 'Place finger on sensor...');
                }, 3000);
            }
            
        } catch (error) {
            console.error('❌ Fingerprint scan error:', error);
            this.updateScanStatus('error', 'Connection error');
            
            // Check if scanner is offline
            if (error.message && error.message.includes('Failed to fetch')) {
                Utils.showNotification(
                    '❌ Cannot connect to scanner.\nPlease check:\n1. ESP32 is powered on\n2. Connected to WiFi\n3. Network connection',
                    'error'
                );
            }
            
            // Back to waiting after error
            setTimeout(() => {
                this.updateScanStatus('waiting', 'Place finger on sensor...');
            }, 3000);
            
        } finally {
            this.scanInProgress = false;
            this.lastScanTime = Date.now();
        }
    },

    updateScanStatus(type, message) {
        const statusEl = document.getElementById('fingerprint-status');
        if (!statusEl) return;
        
        const icons = {
            scanning: '<i class="fas fa-spinner fa-spin text-primary me-1"></i>',
            waiting: '<i class="fas fa-hand-point-up text-info me-1"></i>',
            success: '<i class="fas fa-check-circle text-success me-1"></i>',
            warning: '<i class="fas fa-exclamation-triangle text-warning me-1"></i>',
            error: '<i class="fas fa-times-circle text-danger me-1"></i>'
        };
        
        statusEl.innerHTML = (icons[type] || '') + message;
    },

    showSuccessAnimation(student) {
        // Create success flash
        const container = document.querySelector('.biometric-card');
        if (container) {
            container.style.backgroundColor = '#d4edda';
            setTimeout(() => {
                container.style.backgroundColor = '';
            }, 1000);
        }
    },

    playSuccessSound() {
        // Optional: Play success beep
        try {
            // Create or reuse AudioContext (avoid autoplay policy issues)
            if (!window.attendanceAudioContext) {
                window.attendanceAudioContext = new (window.AudioContext || window.webkitAudioContext)();
            }
            
            const audioContext = window.attendanceAudioContext;
            
            // Resume context if suspended (browser autoplay policy)
            if (audioContext.state === 'suspended') {
                audioContext.resume().catch(() => {
                    console.log('Audio autoplay blocked by browser');
                });
            }
            
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        } catch (e) {
            // Audio not supported or blocked - silently fail
            console.log('Audio playback not available:', e.message);
        }
    }
};

// Global functions for button clicks
function markAttendance() {
    FaceRecognitionSystem.captureAndRecognize();
}

function scanFingerprint() {
    FingerprintSystem.scanAndVerify();
}

async function startNewSession() {
    if (!window.currentSession || !window.currentSession.id) {
        alert('No active session found');
        return;
    }
    
    const confirmed = confirm(
        '⚠️ End current session and start a new one?\n\n' +
        'Current session: ' + window.currentSession.course_name + '\n' +
        'Started: ' + window.currentSession.start_time + '\n\n' +
        'This will end the current session and reload the page to start a new one.'
    );
    
    if (!confirmed) return;
    
    try {
        // Stop camera if active
        FaceRecognitionSystem.stopCamera();
        
        // Stop fingerprint scanner if active
        FingerprintSystem.stopAutoScan();
        
        // End the current session
        const response = await fetch('api/end-session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                session_id: window.currentSession.id
            })
        });
        
        const result = await response.json();
        console.log('End session for new one result:', result);
        
        if (result.status === 'success') {
            const stats = result.statistics;
            alert(`Previous Session Ended!\n\n` +
                  `Final Statistics:\n` +
                  `• Present: ${stats.students_present}/${stats.total_students}\n` +
                  `• Attendance Rate: ${stats.attendance_rate}%\n\n` +
                  `You can now start a new session.`);
            
            // Reload page to show the form
            window.location.reload();
        } else {
            alert('Failed to end session: ' + result.message);
        }
        
    } catch (error) {
        console.error('Error ending session:', error);
        alert('Failed to end session. Please try again.');
    }
}

async function endSession() {
    if (!confirm('Are you sure you want to end this attendance session?')) return;
    
    if (!window.currentSession || !window.currentSession.id) {
        alert('No active session found');
        return;
    }
    
    try {
        // Stop camera if active
        FaceRecognitionSystem.stopCamera();
        
        // Stop fingerprint scanner if active
        FingerprintSystem.stopAutoScan();
        
        // Call API to end session
        const response = await fetch('api/end-session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                session_id: window.currentSession.id
            })
        });
        
        const result = await response.json();
        console.log('End session result:', result);
        
        if (result.status === 'success') {
            // Show success message with statistics
            const stats = result.statistics;
            alert(`Session Ended Successfully!\n\nFinal Statistics:\n` +
                  `• Total Students: ${stats.total_students}\n` +
                  `• Present: ${stats.students_present}\n` +
                  `• Absent: ${stats.students_absent}\n` +
                  `• Attendance Rate: ${stats.attendance_rate}%`);
            
            // Reload page to reset form
            window.location.reload();
        } else {
            alert('Failed to end session: ' + result.message);
        }
        
    } catch (error) {
        console.error('Error ending session:', error);
        alert('Failed to end session. Please try again.');
    }
}

// Add camera initialization to FormHandlers
FormHandlers.initializeCamera = function() {
    FaceRecognitionSystem.initializeCamera();
};

FormHandlers.initializeFingerprintScanner = function() {
    FingerprintSystem.initializeScanner();
};

// Make objects available globally
window.FormHandlers = FormHandlers;
window.SessionManager = SessionManager;
window.AttendanceState = AttendanceState;
window.Utils = Utils;
window.API = API;
window.FaceRecognitionSystem = FaceRecognitionSystem;
window.FingerprintSystem = FingerprintSystem;
