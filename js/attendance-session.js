/**
 * Attendance Session Management JavaScript
 * Clean, modular implementation for attendance session functionality
 * Handles form validation, API calls, and user interactions
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

    // State setters
    setDepartments: function(depts) {
        this.departments = depts;
        this.notify('departmentsChanged');
    },

    setOptions: function(opts) {
        this.options = opts;
        this.notify('optionsChanged');
    },

    setCourses: function(courses) {
        this.courses = courses;
        this.notify('coursesChanged');
    },

    setSession: function(session) {
        this.currentSession = session;
        this.isSessionActive = !!session;
        this.notify('sessionChanged');
    },

    // Observer pattern
    observers: {},
    subscribe: function(event, callback) {
        if (!this.observers[event]) this.observers[event] = [];
        this.observers[event].push(callback);
    },

    notify: function(event) {
        if (this.observers[event]) {
            this.observers[event].forEach(callback => callback());
        }
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

    showNotification: function(message, type = 'info') {
        if (!ATTENDANCE_CONFIG.SHOW_NOTIFICATIONS) return;

        // Use global notification system if available
        if (typeof window.showNotification === 'function' && window.showNotification !== this.showNotification) {
            window.showNotification(message, type);
        } else {
            // Fallback: create a simple notification
            console.log(`[${type.toUpperCase()}] ${message}`);
            this.createFallbackNotification(message, type);
        }
    },

    createFallbackNotification: function(message, type) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    },

    showLoading: function(element, text = 'Loading...') {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        if (element) {
            element.innerHTML = `<i class="fas fa-spinner fa-spin me-2"></i>${text}`;
            element.disabled = true;
        }
    },

    hideLoading: function(element, originalText = '') {
        if (typeof element === 'string') {
            element = document.getElementById(element);
        }
        if (element) {
            element.innerHTML = originalText;
            element.disabled = false;
        }
    },

    validateForm: function() {
        const department = document.getElementById('department').value;
        const option = document.getElementById('option').value;
        const course = document.getElementById('course').value;
        const yearLevel = document.getElementById('year_level').value;
        const biometric = document.getElementById('biometric_method').value;

        const startBtn = document.getElementById('start-session');
        const isValid = department && option && course && yearLevel && biometric;

        if (startBtn) {
            startBtn.disabled = !isValid;
            startBtn.innerHTML = isValid ?
                '<i class="fas fa-play me-2"></i>Start Attendance Session' :
                '<i class="fas fa-play me-2"></i>Complete Form to Start';
            startBtn.className = `btn ${isValid ? 'btn-primary' : 'btn-secondary'}`;
        }

        return isValid;
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
        return this.call(`get-options.php?department_id=${departmentId}`, {
            method: 'GET'
        });
    },

    async getCourses(departmentId, optionId) {
        return this.call(`get-courses.php?department_id=${departmentId}&option_id=${optionId}`);
    },

    async startSession(sessionData) {
        return this.call('start-session.php', {
            method: 'POST',
            body: JSON.stringify(sessionData)
        });
    },

    async endSession(sessionId) {
        return this.call('attendance-session-api.php?action=end_session', {
            method: 'POST',
            body: JSON.stringify({ session_id: sessionId })
        });
    },

    async getSessionStats(sessionId) {
        return this.call(`attendance-session-api.php?action=get_session_stats&session_id=${sessionId}`);
    },

    async recordAttendance(attendanceData) {
        return this.call('attendance-session-api.php?action=record_attendance', {
            method: 'POST',
            body: JSON.stringify(attendanceData)
        });
    }
};

// Form handlers
const FormHandlers = {
    initialize() {
        console.log('🚀 Initializing Attendance Session Management');
        this.setupEventListeners();
        this.setupFormValidation();
        this.loadInitialData();
    },

    setupEventListeners() {
        console.log('🎧 Setting up event listeners...');

        // Department change (auto-selected, but keep for consistency)
        const departmentSelect = document.getElementById('department');
        if (departmentSelect) {
            departmentSelect.addEventListener('change', (e) => {
                console.log('🏫 Department changed:', e.target.value);
                this.handleDepartmentChange(e.target.value);
            });
        }

        // Option change
        const optionSelect = document.getElementById('option');
        if (optionSelect) {
            console.log('🎯 Setting up option select listener');
            optionSelect.addEventListener('change', (e) => {
                console.log('📚 Option changed:', e.target.value);
                this.handleOptionChange(e.target.value);
            });

            // Add click listener for debugging
            optionSelect.addEventListener('click', (e) => {
                console.log('🖱️ Option select clicked, disabled:', e.target.disabled);
            });

            // Add focus listener for debugging
            optionSelect.addEventListener('focus', (e) => {
                console.log('🎯 Option select focused');
            });
        } else {
            console.error('❌ Option select element not found');
        }

        // Course change
        const courseSelect = document.getElementById('course');
        if (courseSelect) {
            courseSelect.addEventListener('change', (e) => {
                console.log('📖 Course changed:', e.target.value);
                this.handleCourseChange(e.target.value);
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

        console.log('✅ Event listeners setup complete');
    },

    setupFormValidation() {
        // Real-time validation
        const formElements = ['department', 'option', 'course', 'year_level', 'biometric_method'];
        formElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.addEventListener('change', Utils.validateForm);
                element.addEventListener('input', Utils.debounce(Utils.validateForm, 300));
            }
        });

        // Initial validation
        Utils.validateForm();
    },

    async loadInitialData() {
        console.log('🚀 Starting loadInitialData...');
        try {
            // Department is auto-selected, so load options for the assigned department
            const departmentSelect = document.getElementById('department');
            console.log('📋 Department select element:', departmentSelect);
            console.log('📋 Department value:', departmentSelect?.value);

            if (departmentSelect && departmentSelect.value) {
                console.log('🏫 Loading options for department:', departmentSelect.value);
                await this.loadOptions(departmentSelect.value);
                Utils.showNotification('✅ Department auto-selected. Loading available options...', 'info');
            } else {
                console.warn('⚠️ No department selected in loadInitialData');
                console.log('Department select details:', {
                    exists: !!departmentSelect,
                    value: departmentSelect?.value,
                    disabled: departmentSelect?.disabled,
                    options: departmentSelect?.options?.length
                });
            }
        } catch (error) {
            console.error('❌ Failed to load initial data:', error);
            Utils.showNotification('❌ Failed to load initial data', 'error');
        }
        console.log('✅ loadInitialData completed');
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
        console.log('🏫 Current department:', AttendanceState.selectedDepartment);

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

            if (response.status === 'success') {
                AttendanceState.setSession(response.session);
                Utils.showNotification('✅ Session started successfully!', 'success');

                // Update UI
                this.showActiveSession(response.session);
            } else {
                throw new Error(response.message || 'Failed to start session');
            }
        } catch (error) {
            console.error('Failed to start session:', error);
            Utils.showNotification('❌ Failed to start session: ' + error.message, 'error');
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

        console.log('🔄 Starting to load options for department:', departmentId);
        Utils.showLoading(optionSelect, 'Loading options...');

        try {
            console.log('📡 Making API call to get options...');
            const response = await API.getOptions(departmentId);
            console.log('📡 API Response received:', response);

            // Check response validity - API returns 'success' field, not 'status'
            const isValidResponse = response.success === true || response.data;
            console.log('✅ Response validity check:', isValidResponse);
            console.log('📡 Full response structure:', response);

            if (isValidResponse) {
                console.log('🧹 Clearing existing options...');
                // Clear existing options
                optionSelect.innerHTML = '<option value="" disabled selected>Choose an academic option</option>';

                // Check if data exists and has length
                const hasData = response.data && response.data.length > 0;
                console.log('📊 Data check - hasData:', hasData, 'length:', response.data?.length);

                // Use real API data if available, otherwise add sample options
                if (hasData) {
                    console.log('🔄 Adding real API options to dropdown...');
                    response.data.forEach((option, index) => {
                        console.log(`📝 Adding real option ${index + 1}:`, option);
                        const optionElement = document.createElement('option');
                        optionElement.value = option.id;
                        optionElement.textContent = option.name;
                        optionSelect.appendChild(optionElement);
                    });
                    console.log('✅ Real API options added');
                } else {
                    console.log('🧪 No real options found, adding sample test options...');

                    // Sample academic options for testing
                    const sampleOptions = [
                        { id: 'option_software_eng', name: 'Software Engineering' },
                        { id: 'option_computer_sci', name: 'Computer Science' }
                    ];

                    sampleOptions.forEach((option, index) => {
                        const optionElement = document.createElement('option');
                        optionElement.value = option.id;
                        optionElement.textContent = option.name;
                        optionElement.style.color = '#495057';
                        optionSelect.appendChild(optionElement);
                        console.log(`✅ Added sample option ${index + 1}: ${option.name}`);
                    });

                    console.log('✅ Sample test options added');
                }

                if (hasData) {
                    console.log('🔄 Adding options to dropdown...');
                    response.data.forEach((option, index) => {
                        console.log(`📝 Adding option ${index + 1}:`, option);
                        const optionElement = document.createElement('option');
                        optionElement.value = option.id;
                        optionElement.textContent = option.name;
                        optionElement.style.color = '#495057'; // Ensure proper text color
                        optionSelect.appendChild(optionElement);
                    });

                    // Note: Sample options are now added in the else block above

                    console.log('💾 Setting state and enabling dropdown...');
                    AttendanceState.setOptions(response.data);
                    optionSelect.disabled = false;
                    optionSelect.removeAttribute('disabled'); // Ensure disabled attribute is removed
                    optionSelect.style.cursor = 'pointer'; // Ensure cursor shows it's clickable

                    console.log('✅ Updating feedback text...');
                    const feedbackEl = document.getElementById('option-feedback');
                    if (feedbackEl) {
                        feedbackEl.innerHTML = '<i class="fas fa-check text-success me-1"></i>Options loaded successfully';
                        feedbackEl.className = 'form-text text-success';
                    }

                    // Force a change event to trigger validation
                    console.log('🔄 Triggering change event for validation...');
                    setTimeout(() => {
                        const changeEvent = new Event('change', { bubbles: true });
                        optionSelect.dispatchEvent(changeEvent);

                        const inputEvent = new Event('input', { bubbles: true });
                        optionSelect.dispatchEvent(inputEvent);

                        console.log('✅ Option dropdown should now be interactive');
                        console.log('📊 Current option select state:', {
                            disabled: optionSelect.disabled,
                            value: optionSelect.value,
                            optionsCount: optionSelect.options.length,
                            style: optionSelect.style.cssText,
                            hasDisabledAttr: optionSelect.hasAttribute('disabled'),
                            tabIndex: optionSelect.tabIndex
                        });

                        // Test if we can programmatically change the value
                        console.log('🧪 Testing dropdown interaction...');
                        if (optionSelect.options.length > 1) {
                            console.log('✅ Options available for selection');
                        } else {
                            console.warn('⚠️ Only placeholder option available');
                        }
                    }, 100); // Small delay for DOM updates
                } else {
                    console.log('⚠️ No options found in response data');
                    // No options found
                    optionSelect.innerHTML = '<option value="" disabled selected>No options available</option>';
                    optionSelect.disabled = true;
                    optionSelect.style.color = '#6c757d'; // Muted color for disabled state

                    const feedbackEl = document.getElementById('option-feedback');
                    if (feedbackEl) {
                        feedbackEl.innerHTML = '<i class="fas fa-exclamation-triangle text-warning me-1"></i>No academic options found for this department';
                        feedbackEl.className = 'form-text text-warning';
                    }

                    Utils.showNotification('⚠️ No options found for the selected department.', 'warning');
                    console.log('⚠️ No options found for department:', departmentId);
                    console.log('Response:', response);
                }
            } else {
                console.error('❌ Invalid response format:', response);
                throw new Error(response.message || 'Failed to load options');
            }
        } catch (error) {
            console.error('❌ Failed to load options:', error);
            optionSelect.innerHTML = '<option value="" disabled selected>Error loading options</option>';
            optionSelect.disabled = true;
            optionSelect.style.color = '#dc3545'; // Error color

            const feedbackEl = document.getElementById('option-feedback');
            if (feedbackEl) {
                feedbackEl.innerHTML = '<i class="fas fa-times text-danger me-1"></i>Failed to load options';
                feedbackEl.className = 'form-text text-danger';
            }

            Utils.showNotification('❌ Failed to load department options. Please try refreshing the page.', 'error');
        } finally {
            console.log('🔄 Hiding loading state...');
            Utils.hideLoading(optionSelect);
        }
    },

    async loadCourses(departmentId, optionId) {
        const courseSelect = document.getElementById('course');
        if (!courseSelect) return;

        Utils.showLoading(courseSelect, 'Loading courses...');

        try {
            const response = await API.getCourses(departmentId, optionId);

            if (response.status === 'success' && response.data && response.data.length > 0) {
                // Clear existing options
                courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';

                // Add new courses
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
                // No courses found
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

        // Update session info if element exists
        const sessionInfo = document.getElementById('sessionInfo');
        if (sessionInfo) {
            sessionInfo.innerHTML = `
                <strong>Course:</strong> ${sessionData.course_name || 'N/A'}<br>
                <strong>Department:</strong> ${sessionData.department_name || 'N/A'}<br>
                <strong>Option:</strong> ${sessionData.option_name || 'N/A'}<br>
                <strong>Method:</strong> ${sessionData.biometric_method === 'face' ? 'Face Recognition' : 'Fingerprint'}<br>
                <strong>Started:</strong> ${new Date(sessionData.start_time || Date.now()).toLocaleString()}
            `;
        }

        // Show appropriate biometric section
        if (sessionData.biometric_method === 'face') {
            this.initializeFaceRecognition();
        } else if (sessionData.biometric_method === 'finger') {
            this.initializeFingerprint();
        }

        // Load initial stats
        this.loadSessionStats();
    },

    initializeFaceRecognition() {
        const faceSection = document.getElementById('faceRecognitionSection');
        if (faceSection) faceSection.classList.add('active');
    },

    initializeFingerprint() {
        const fingerprintSection = document.getElementById('fingerprintSection');
        if (fingerprintSection) fingerprintSection.classList.add('active');
    },

    async loadSessionStats() {
        try {
            const sessionId = AttendanceState.currentSession?.id;
            if (sessionId) {
                const response = await API.getSessionStats(sessionId);
                if (response.status === 'success') {
                    this.updateStatsDisplay(response.data);
                }
            }
        } catch (error) {
            console.error('Failed to load session stats:', error);
        }
    },

    updateStatsDisplay(stats) {
        const totalStudentsEl = document.getElementById('total-students');
        const presentCountEl = document.getElementById('present-count');
        const absentCountEl = document.getElementById('absent-count');
        const attendanceRateEl = document.getElementById('attendance-rate');

        if (totalStudentsEl) totalStudentsEl.textContent = stats.total_students || 0;
        if (presentCountEl) presentCountEl.textContent = stats.present_count || 0;
        if (absentCountEl) absentCountEl.textContent = (stats.total_students || 0) - (stats.present_count || 0);
        if (attendanceRateEl) {
            const rate = stats.total_students > 0 ? Math.round((stats.present_count / stats.total_students) * 100) : 0;
            attendanceRateEl.textContent = rate + '%';
        }
    }
};

// Webcam and biometric functions
const BiometricHandlers = {
    webcamStream: null,

    async initializeWebcam() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640, min: 320 },
                    height: { ideal: 480, min: 240 },
                    facingMode: 'user'
                }
            });

            const video = document.getElementById('webcam-preview');
            if (video) {
                video.srcObject = stream;
                video.play();
                this.webcamStream = stream;

                // Update UI
                const placeholder = document.getElementById('webcam-placeholder');
                const status = document.getElementById('webcam-status');
                const container = document.getElementById('webcam-container');

                if (placeholder) placeholder.style.display = 'none';
                if (status) status.classList.add('active');
                if (container) container.classList.add('active');

                // Enable mark attendance button
                const markBtn = document.getElementById('markAttendanceBtn');
                if (markBtn) markBtn.disabled = false;

                Utils.showNotification('✅ Camera started successfully!', 'success');
            }
        } catch (error) {
            console.error('Webcam initialization failed:', error);
            Utils.showNotification('❌ Failed to access camera', 'error');
        }
    },

    async captureImage() {
        const video = document.getElementById('webcam-preview');
        if (!video || !video.videoWidth) {
            throw new Error('No active webcam');
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);

        return canvas.toDataURL('image/jpeg', 0.8);
    },

    async markAttendance() {
        try {
            const imageData = await this.captureImage();
            const sessionId = AttendanceState.currentSession?.id;

            if (!sessionId) {
                throw new Error('No active session');
            }

            const attendanceData = {
                session_id: sessionId,
                method: 'face',
                image_data: imageData
            };

            const response = await API.recordAttendance(attendanceData);

            if (response.status === 'success') {
                Utils.showNotification(`✅ Attendance marked for ${response.student_name || 'Student'}!`, 'success');
                // Refresh stats
                FormHandlers.loadSessionStats();
            } else {
                throw new Error(response.message || 'Failed to record attendance');
            }
        } catch (error) {
            console.error('Attendance marking failed:', error);
            Utils.showNotification('❌ Failed to mark attendance: ' + error.message, 'error');
        }
    },

    stopWebcam() {
        if (this.webcamStream) {
            this.webcamStream.getTracks().forEach(track => track.stop());
            this.webcamStream = null;
        }
    }
};

// Global functions for HTML event handlers
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

function endSession() {
    if (confirm('Are you sure you want to end this attendance session?')) {
        // End session logic here
        AttendanceState.setSession(null);

        // Hide active session
        const activeSection = document.getElementById('activeSessionSection');
        if (activeSection) {
            activeSection.style.display = 'none';
        }

        // Show setup section
        const setupSection = document.getElementById('sessionSetupSection');
        if (setupSection) {
            setupSection.style.display = 'block';
        }

        // Reset form
        const sessionForm = document.getElementById('sessionForm');
        if (sessionForm) {
            sessionForm.reset();
        }

        // Reset state
        FormHandlers.resetOptions();
        FormHandlers.resetCourses();

        // Stop webcam if active
        BiometricHandlers.stopWebcam();

        Utils.showNotification('✅ Session ended successfully!', 'success');
    }
}

function markAttendance() {
    BiometricHandlers.markAttendance();
}

function scanFingerprint() {
    Utils.showNotification('👆 Fingerprint scanning not yet implemented', 'info');
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initializing Attendance Session Management');

    // Initialize form handlers
    FormHandlers.initialize();

    console.log('✅ Attendance Session Management initialized');
});

// Export for debugging
window.AttendanceSession = {
    State: AttendanceState,
    Utils: Utils,
    API: API,
    FormHandlers: FormHandlers,
    BiometricHandlers: BiometricHandlers
};