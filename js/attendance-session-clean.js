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
    showNotification: function(message, type = 'info') {
        console.log(`${type.toUpperCase()}: ${message}`);
        // You can add actual notification UI here
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
    initialize: function() {
        console.log('🚀 Initializing form handlers...');
        this.setupEventListeners();
        this.loadInitialData();
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

        try {
            const response = await API.startSession(sessionData);
            console.log('📡 Session creation response:', response);

            if (response.status === 'success') {
                AttendanceState.setSession(response.session);
                Utils.showNotification('✅ Session started successfully!', 'success');
                this.showActiveSession(response.session);
            } else {
                // Check if there's an existing session
                if (response.existing_session_id) {
                    console.warn('⚠️ Active session already exists:', response.existing_session_id);
                    
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
                        Utils.showNotification('⚠️ Please end your active session before starting a new one', 'warning');
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
            
            // Enable mark attendance button
            if (markBtn) {
                markBtn.disabled = false;
            }
            
            if (statusEl) {
                statusEl.innerHTML = '<i class="fas fa-circle text-success me-1"></i>Camera Active';
            }
            
            console.log('✅ Camera initialized successfully');
            Utils.showNotification('✅ Camera is ready! You can now mark attendance.', 'success');
            
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
            
            if (result.status === 'success' && result.student) {
                Utils.showNotification(
                    `✅ Attendance marked for ${result.student.name} (${result.student.reg_no})`,
                    'success'
                );
                
                // Update attendance count
                this.updateAttendanceStats();
            } else {
                Utils.showNotification(
                    result.message || '❌ Face not recognized. Please try again.',
                    'warning'
                );
            }
            
        } catch (error) {
            console.error('❌ Face recognition error:', error);
            Utils.showNotification('❌ Failed to process image. Please try again.', 'error');
        } finally {
            this.isCapturing = false;
            if (markBtn) {
                markBtn.disabled = false;
                markBtn.innerHTML = '<i class="fas fa-user-check me-2"></i>Mark Attendance';
            }
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
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
            this.stream = null;
            console.log('📷 Camera stopped');
        }
    }
};

// Fingerprint Scanner System
const FingerprintSystem = {
    isScanning: false,

    async initializeScanner() {
        console.log('👆 Initializing fingerprint scanner...');
        Utils.showNotification('👆 Fingerprint scanner ready. Click to scan.', 'info');
    },

    async scanAndVerify() {
        if (this.isScanning) return;
        
        this.isScanning = true;
        
        try {
            console.log('👆 Scanning fingerprint...');
            
            // Send request to fingerprint scanner API
            const response = await fetch('api/scan-fingerprint.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    session_id: window.currentSession ? window.currentSession.id : null
                })
            });
            
            const result = await response.json();
            console.log('📡 Scan result:', result);
            
            if (result.status === 'success' && result.student) {
                Utils.showNotification(
                    `✅ Attendance marked for ${result.student.name} (${result.student.reg_no})`,
                    'success'
                );
                FaceRecognitionSystem.updateAttendanceStats();
            } else {
                Utils.showNotification(
                    result.message || '❌ Fingerprint not recognized.',
                    'warning'
                );
            }
            
        } catch (error) {
            console.error('❌ Fingerprint scan error:', error);
            Utils.showNotification('❌ Failed to scan fingerprint.', 'error');
        } finally {
            this.isScanning = false;
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
