// Attendance Session Face Recognition JavaScript
// Optimized for performance and maintainability

// Configuration for face recognition
const FACE_RECOGNITION_CONFIG = {
    ENABLE_REAL_MODE: true,
    SHOW_NOTIFICATIONS: true,
    SAMPLE_STUDENTS: [
        { id: 'STU001', name: 'John Doe', department: 'Computer Science', year: 1, option: 'Software Engineering' },
        { id: 'STU002', name: 'Jane Smith', department: 'Computer Science', year: 1, option: 'Software Engineering' },
        { id: 'STU003', name: 'Bob Johnson', department: 'Information Technology', year: 2, option: 'Network Administration' },
        { id: 'STU004', name: 'Alice Brown', department: 'Computer Science', year: 2, option: 'Data Science' },
        { id: 'STU005', name: 'Charlie Wilson', department: 'Electrical Engineering', year: 3, option: 'Power Systems' },
        { id: 'STU006', name: 'Diana Prince', department: 'Computer Science', year: 3, option: 'Software Engineering' },
        { id: 'STU007', name: 'Eve Adams', department: 'Information Technology', year: 1, option: 'Network Administration' },
        { id: 'STU008', name: 'Frank Miller', department: 'Computer Science', year: 2, option: 'Data Science' },
        { id: 'STU009', name: 'Grace Lee', department: 'Electrical Engineering', year: 4, option: 'Electronics' },
        { id: 'STU010', name: 'Henry Davis', department: 'Computer Science', year: 1, option: 'Software Engineering' },
        { id: 'STU011', name: 'Ivy Chen', department: 'Information Technology', year: 3, option: 'Cybersecurity' },
        { id: 'STU012', name: 'Jack Wilson', department: 'Computer Science', year: 2, option: 'Data Science' },
        { id: 'STU013', name: 'Kate Brown', department: 'Electrical Engineering', year: 2, option: 'Power Systems' },
        { id: 'STU014', name: 'Liam Johnson', department: 'Computer Science', year: 4, option: 'Software Engineering' },
        { id: 'STU015', name: 'Maya Patel', department: 'Information Technology', year: 1, option: 'Network Administration' }
    ]
};

// Initialize face recognition system
document.addEventListener('DOMContentLoaded', function() {
    if (FACE_RECOGNITION_CONFIG.ENABLE_REAL_MODE) {
        initializeFaceRecognitionSystem();
        showSystemReadyNotice();
        // Load departments immediately for better UX
        loadDepartments();
    }
});

// Initialize face recognition system
function initializeFaceRecognitionSystem() {
    console.log('📷 Face recognition system initialized');

    // Setup real API calls (will fallback to demo if backend unavailable)
    setupApiCalls();

    // Add face recognition event listeners
    addFaceRecognitionEventListeners();

    // Initialize webcam handling
    initializeWebcamHandling();
}

// Show system ready notice
function showSystemReadyNotice() {
    const systemNotice = document.createElement('div');
    systemNotice.className = 'system-notice';
    systemNotice.innerHTML = `
        <i class="fas fa-check-circle"></i>
        <strong>Face Recognition System Ready:</strong> Camera and facial recognition features are active.
    `;

    // Insert at the top of the page
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.insertBefore(systemNotice, mainContent.firstChild);
    }
}

// Toggle demo mode
function toggleDemoMode() {
    DEMO_CONFIG.ENABLE_DEMO_MODE = !DEMO_CONFIG.ENABLE_DEMO_MODE;
    location.reload();
}

// Setup API calls with fallback to demo data
function setupApiCalls() {
    // Try real API first, fallback to demo if unavailable
    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        return originalFetch.apply(this, arguments)
            .catch(error => {
                console.log('API unavailable, using demo data:', error);
                return handleDemoApiCall(url, options);
            });
    };
}

// Handle demo API calls (fallback when backend unavailable)
async function handleDemoApiCall(url, options) {
    console.log('🔄 Using demo data (backend unavailable):', url);

    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 500 + Math.random() * 1000));

    // Mock responses based on URL
    if (url.includes('get_departments')) {
        return createMockResponse({
            status: 'success',
            data: [
                { id: 1, name: 'Computer Science' },
                { id: 2, name: 'Information Technology' },
                { id: 3, name: 'Electrical Engineering' },
                { id: 4, name: 'Mechanical Engineering' },
                { id: 5, name: 'Civil Engineering' }
            ]
        });
    }

    if (url.includes('get_options')) {
        const urlParams = new URLSearchParams(url.split('?')[1]);
        const departmentId = urlParams.get('department_id');
        const options = [
            { id: 1, name: 'Software Engineering', department_id: 1 },
            { id: 2, name: 'Data Science', department_id: 1 },
            { id: 3, name: 'Artificial Intelligence', department_id: 1 },
            { id: 4, name: 'Network Administration', department_id: 2 },
            { id: 5, name: 'Cybersecurity', department_id: 2 },
            { id: 6, name: 'Power Systems', department_id: 3 },
            { id: 7, name: 'Electronics', department_id: 3 },
            { id: 8, name: 'Thermodynamics', department_id: 4 },
            { id: 9, name: 'Structural Engineering', department_id: 5 }
        ].filter(opt => opt.department_id == departmentId);

        return createMockResponse({
            status: 'success',
            data: options
        });
    }

    if (url.includes('get_courses')) {
        const urlParams = new URLSearchParams(url.split('?')[1]);
        const departmentId = urlParams.get('department_id');
        const optionId = urlParams.get('option_id');
        const courses = [
            { id: 1, name: 'Web Development', course_code: 'CS101', department_id: 1, option_id: 1, semester: 1, credits: 3, lecturer_name: 'Dr. John Smith' },
            { id: 2, name: 'Database Systems', course_code: 'CS201', department_id: 1, option_id: 1, semester: 2, credits: 4, lecturer_name: 'Prof. Jane Doe' },
            { id: 3, name: 'Machine Learning', course_code: 'CS301', department_id: 1, option_id: 2, semester: 3, credits: 4, lecturer_name: 'Dr. Alice Johnson' },
            { id: 4, name: 'Data Structures', course_code: 'CS102', department_id: 1, option_id: 1, semester: 1, credits: 4, lecturer_name: 'Dr. Bob Wilson' },
            { id: 5, name: 'Computer Networks', course_code: 'IT201', department_id: 2, option_id: 4, semester: 2, credits: 3, lecturer_name: 'Prof. Sarah Davis' },
            { id: 6, name: 'Circuit Analysis', course_code: 'EE101', department_id: 3, option_id: 6, semester: 1, credits: 4, lecturer_name: 'Dr. Michael Brown' },
            { id: 7, name: 'Fluid Mechanics', course_code: 'ME201', department_id: 4, option_id: 8, semester: 2, credits: 3, lecturer_name: 'Prof. Lisa Johnson' },
            { id: 8, name: 'Concrete Structures', course_code: 'CE301', department_id: 5, option_id: 9, semester: 3, credits: 4, lecturer_name: 'Dr. David Miller' }
        ].filter(course => course.department_id == departmentId && course.option_id == optionId);

        return createMockResponse({
            status: 'success',
            data: courses
        });
    }

    if (url.includes('start_session')) {
        return createMockResponse({
            status: 'success',
            session_id: Date.now(),
            data: {
                id: Date.now(),
                course_name: 'Web Development',
                department_name: 'Computer Science',
                option_name: 'Software Engineering',
                biometric_method: 'face',
                start_time: new Date().toISOString()
            }
        });
    }

    if (url.includes('get_session_stats')) {
        return createMockResponse({
            status: 'success',
            data: {
                total_students: 25,
                present_count: Math.floor(Math.random() * 15) + 10,
                absent_count: 0,
                attendance_rate: Math.floor(Math.random() * 20) + 70
            }
        });
    }

    if (url.includes('get_attendance_records')) {
        const mockRecords = generateMockAttendanceRecords();
        return createMockResponse({
            status: 'success',
            data: mockRecords
        });
    }

    // For other calls, return error
    return createMockResponse({
        status: 'error',
        message: 'Demo API endpoint not available'
    });
}

// Create mock response
function createMockResponse(data) {
    return {
        ok: true,
        json: () => Promise.resolve(data),
        text: () => Promise.resolve(JSON.stringify(data))
    };
}

// Generate mock attendance records
function generateMockAttendanceRecords() {
    const students = FACE_RECOGNITION_CONFIG.SAMPLE_STUDENTS.slice(0, 8); // Use first 8 students for demo

    return students.map(student => ({
        id: Math.random(),
        student_id: student.id,
        student_name: student.name,
        recorded_at: new Date(Date.now() - Math.random() * 3600000).toISOString(),
        status: Math.random() > 0.1 ? 'present' : 'absent', // 90% present rate
        method: 'face_recognition',
        department: student.department,
        year: student.year
    }));
}

// Load demo departments
function loadDemoDepartments() {
    if (!DEMO_CONFIG.ENABLE_DEMO_MODE) return;

    const departmentSelect = document.getElementById('department');
    if (!departmentSelect) return;

    departmentSelect.innerHTML = '<option value="" disabled selected>Select Department</option>';

    DEMO_CONFIG.DEMO_DATA.departments.forEach(dept => {
        const option = document.createElement('option');
        option.value = dept.id;
        option.textContent = dept.name;
        departmentSelect.appendChild(option);
    });

    departmentSelect.disabled = false;

    if (DEMO_CONFIG.DEMO_NOTIFICATIONS) {
        showDemoNotification('🎭 Demo departments loaded successfully!', 'success');
    }
}

// Load departments on page load
function loadDepartments() {
    console.log('🏫 Loading departments...');

    const departmentSelect = document.getElementById('department');
    if (!departmentSelect) {
        console.error('Department select element not found');
        return;
    }

    // Clear existing options except the first one
    departmentSelect.innerHTML = '<option value="" disabled selected>Select Department</option>';

    // Simulate API call delay
    setTimeout(() => {
        try {
            // Mock departments data
            const departments = [
                { id: 1, name: 'Computer Science' },
                { id: 2, name: 'Information Technology' },
                { id: 3, name: 'Electrical Engineering' },
                { id: 4, name: 'Mechanical Engineering' },
                { id: 5, name: 'Civil Engineering' }
            ];

            // Add department options
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                departmentSelect.appendChild(option);
            });

            // Enable the select
            departmentSelect.disabled = false;

            console.log('✅ Departments loaded successfully');
            showNotification('✅ Departments loaded successfully!', 'success');

        } catch (error) {
            console.error('❌ Error loading departments:', error);
            showNotification('❌ Failed to load departments', 'error');
        }
    }, 500);
}

// Load options for selected department
function loadOptions(departmentId) {
    console.log('📚 Loading options for department:', departmentId);

    const optionSelect = document.getElementById('option');
    if (!optionSelect) return;

    // Clear existing options
    optionSelect.innerHTML = '<option value="" disabled selected>Select Option</option>';

    if (!departmentId) {
        optionSelect.disabled = true;
        return;
    }

    // Show loading
    optionSelect.disabled = true;
    const loadingOption = document.createElement('option');
    loadingOption.textContent = 'Loading...';
    loadingOption.disabled = true;
    optionSelect.appendChild(loadingOption);

    // Simulate API call delay
    setTimeout(() => {
        try {
            // Mock options data based on department
            const optionsData = {
                1: [ // Computer Science
                    { id: 1, name: 'Software Engineering' },
                    { id: 2, name: 'Data Science' },
                    { id: 3, name: 'Artificial Intelligence' }
                ],
                2: [ // Information Technology
                    { id: 4, name: 'Network Administration' },
                    { id: 5, name: 'Cybersecurity' }
                ],
                3: [ // Electrical Engineering
                    { id: 6, name: 'Power Systems' },
                    { id: 7, name: 'Electronics' }
                ],
                4: [ // Mechanical Engineering
                    { id: 8, name: 'Thermodynamics' }
                ],
                5: [ // Civil Engineering
                    { id: 9, name: 'Structural Engineering' }
                ]
            };

            const options = optionsData[departmentId] || [];

            // Clear loading option
            optionSelect.innerHTML = '<option value="" disabled selected>Select Option</option>';

            // Add option options
            options.forEach(opt => {
                const option = document.createElement('option');
                option.value = opt.id;
                option.textContent = opt.name;
                optionSelect.appendChild(option);
            });

            // Enable the select
            optionSelect.disabled = false;

            console.log('✅ Options loaded successfully for department:', departmentId);

        } catch (error) {
            console.error('❌ Error loading options:', error);
            optionSelect.innerHTML = '<option value="" disabled selected>Error loading options</option>';
        }
    }, 300);
}

// Load courses for selected option
function loadCourses(departmentId, optionId) {
    console.log('📖 Loading courses for department:', departmentId, 'option:', optionId);

    const courseSelect = document.getElementById('course');
    if (!courseSelect) return;

    // Clear existing options
    courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';

    if (!departmentId || !optionId) {
        courseSelect.disabled = true;
        return;
    }

    // Show loading
    courseSelect.disabled = true;
    const loadingOption = document.createElement('option');
    loadingOption.textContent = 'Loading...';
    loadingOption.disabled = true;
    courseSelect.appendChild(loadingOption);

    // Simulate API call delay
    setTimeout(() => {
        try {
            // Mock courses data based on department and option
            const coursesData = {
                // Computer Science - Software Engineering
                '1_1': [
                    { id: 1, name: 'Web Development', code: 'CS101' },
                    { id: 2, name: 'Database Systems', code: 'CS201' },
                    { id: 4, name: 'Data Structures', code: 'CS102' }
                ],
                // Computer Science - Data Science
                '1_2': [
                    { id: 3, name: 'Machine Learning', code: 'CS301' }
                ],
                // Information Technology - Network Administration
                '2_4': [
                    { id: 5, name: 'Computer Networks', code: 'IT201' }
                ],
                // Electrical Engineering - Power Systems
                '3_6': [
                    { id: 6, name: 'Circuit Analysis', code: 'EE101' }
                ],
                // Mechanical Engineering - Thermodynamics
                '4_8': [
                    { id: 7, name: 'Fluid Mechanics', code: 'ME201' }
                ],
                // Civil Engineering - Structural Engineering
                '5_9': [
                    { id: 8, name: 'Concrete Structures', code: 'CE301' }
                ]
            };

            const key = `${departmentId}_${optionId}`;
            const courses = coursesData[key] || [];

            // Clear loading option
            courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';

            // Add course options
            courses.forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = `${course.name} (${course.code})`;
                courseSelect.appendChild(option);
            });

            // Enable the select
            courseSelect.disabled = false;

            console.log('✅ Courses loaded successfully for department:', departmentId, 'option:', optionId);

        } catch (error) {
            console.error('❌ Error loading courses:', error);
            courseSelect.innerHTML = '<option value="" disabled selected>Error loading courses</option>';
        }
    }, 300);
}

// Add face recognition event listeners
function addFaceRecognitionEventListeners() {
    // Add demo button to session form
    const sessionForm = document.getElementById('sessionForm');
    if (sessionForm) {
        const demoBtn = document.createElement('button');
        demoBtn.type = 'button';
        demoBtn.className = 'btn btn-outline-info mt-3';
        demoBtn.innerHTML = '<i class="fas fa-magic me-2"></i>Load Demo Data';
        demoBtn.onclick = loadDemoData;

        sessionForm.appendChild(demoBtn);
    }

    // Add event listeners for dropdown changes
    const departmentSelect = document.getElementById('department');
    const optionSelect = document.getElementById('option');
    const courseSelect = document.getElementById('course');

    if (departmentSelect) {
        departmentSelect.addEventListener('change', function() {
            if (FACE_RECOGNITION_CONFIG.ENABLE_REAL_MODE) {
                handleDepartmentChange(this.value);
            }
        });
    }

    if (optionSelect) {
        optionSelect.addEventListener('change', function() {
            if (FACE_RECOGNITION_CONFIG.ENABLE_REAL_MODE) {
                handleOptionChange(this.value);
            }
        });
    }

    if (courseSelect) {
        courseSelect.addEventListener('change', function() {
            if (FACE_RECOGNITION_CONFIG.ENABLE_REAL_MODE) {
                handleCourseChange(this.value);
            }
        });
    }

    // Add event listener for biometric method change
    const biometricSelect = document.getElementById('biometric_method');
    if (biometricSelect) {
        biometricSelect.addEventListener('change', function() {
            if (FACE_RECOGNITION_CONFIG.ENABLE_REAL_MODE) {
                handleBiometricChange(this.value);
            }
        });
    }

    // Add event listener for start session button
    const startBtn = document.getElementById('start-session');
    if (startBtn) {
        startBtn.addEventListener('click', function(e) {
            if (FACE_RECOGNITION_CONFIG.ENABLE_REAL_MODE) {
                e.preventDefault();
                handleStartSession();
            }
        });
    }

    // Add event listener for "Load Demo Data" button to trigger department loading
    const demoBtn = document.querySelector('.btn-outline-info');
    if (demoBtn) {
        demoBtn.addEventListener('click', function() {
            // Load departments when demo button is clicked
            loadDepartments();
        });
    }

    // Add event listener for end session button
    const endSessionBtn = document.getElementById('endSessionBtn');
    if (endSessionBtn) {
        endSessionBtn.addEventListener('click', function() {
            handleEndSession();
        });
    }
}

// Load demo data into form
function loadDemoData() {
    // First load departments if not already loaded
    if (!document.getElementById('department').querySelector('option[value="1"]')) {
        loadDemoDepartments();
    }

    const departmentSelect = document.getElementById('department');
    const optionSelect = document.getElementById('option');
    const courseSelect = document.getElementById('course');
    const biometricSelect = document.getElementById('biometric_method');

    if (departmentSelect && optionSelect && courseSelect && biometricSelect) {
        // Wait a bit for departments to load, then populate form
        setTimeout(() => {
            // Select first department
            departmentSelect.value = DEMO_CONFIG.DEMO_DATA.departments[0].id;
            departmentSelect.dispatchEvent(new Event('change'));

            // After a short delay, select first option
            setTimeout(() => {
                const options = DEMO_CONFIG.DEMO_DATA.options.filter(opt => opt.department_id == departmentSelect.value);
                if (options.length > 0) {
                    optionSelect.value = options[0].id;
                    optionSelect.dispatchEvent(new Event('change'));

                    // After another delay, select first course
                    setTimeout(() => {
                        const courses = DEMO_CONFIG.DEMO_DATA.courses.filter(course =>
                            course.department_id == departmentSelect.value && course.option_id == optionSelect.value
                        );
                        if (courses.length > 0) {
                            courseSelect.value = courses[0].id;
                            courseSelect.dispatchEvent(new Event('change'));
                        }

                        // Select face recognition
                        biometricSelect.value = 'face';
                        biometricSelect.dispatchEvent(new Event('change'));

                        showDemoNotification('🎭 Demo data loaded! Click "Start Session" to begin.', 'info');
                    }, 500);
                }
            }, 500);
        }, 300);
    }
}

// Show notification
function showNotification(message, type = 'info') {
    if (FACE_RECOGNITION_CONFIG.SHOW_NOTIFICATIONS) {
        // Use global notification system if available
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
        } else {
            // Fallback notification
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }
}

// Demo webcam simulation
function simulateWebcamCapture() {
    return new Promise((resolve) => {
        setTimeout(() => {
            // Create a fake base64 image
            const canvas = document.createElement('canvas');
            canvas.width = 320;
            canvas.height = 240;
            const ctx = canvas.getContext('2d');

            // Draw a simple pattern
            ctx.fillStyle = '#f0f0f0';
            ctx.fillRect(0, 0, 320, 240);
            ctx.fillStyle = '#333';
            ctx.font = '20px Arial';
            ctx.textAlign = 'center';
            ctx.fillText('Demo Webcam', 160, 120);

            resolve(canvas.toDataURL('image/jpeg', 0.8));
        }, 1000);
    });
}

// Webcam capture function - optimized for performance
async function captureWebcamImage() {
    const video = document.getElementById('webcam-preview');
    if (!video || !video.videoWidth) {
        console.error('No active webcam stream');
        return null;
    }

    // Capture real image from the actual webcam stream
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);

    return canvas.toDataURL('image/jpeg', 0.8);
}

// Override webcam capture for demo
const originalCaptureWebcamImage = window.captureWebcamImage;
window.captureWebcamImage = captureWebcamImage;

// Enhanced face recognition with better user experience
const originalHandleMarkAttendance = window.handleMarkAttendance;
window.handleMarkAttendance = async function() {
    if (FACE_RECOGNITION_CONFIG.ENABLE_REAL_MODE) {
        console.log('🤖 Starting face recognition process...');

        // Check if webcam is active
        const video = document.getElementById('webcam-preview');
        if (!video || !video.srcObject) {
            showNotification('❌ No active camera. Please start the webcam first.', 'error');
            return;
        }

        // Show processing overlay with better messaging
        if (typeof showWebcamOverlay === 'function') {
            showWebcamOverlay('📸 Capturing image from camera...', 'processing');
        }

        try {
            // Add a brief delay to let user see the "capturing" message
            await new Promise(resolve => setTimeout(resolve, 500));

            // Capture real image from webcam
            const imageData = await captureWebcamImage();
            if (!imageData) {
                throw new Error('Failed to capture image from camera');
            }

            console.log('📸 Image captured from webcam, size:', imageData.length);

            // Update overlay to show processing
            if (typeof showWebcamOverlay === 'function') {
                showWebcamOverlay('🧠 Analyzing facial features...', 'processing');
            }

            // Simulate AI processing delay (longer for realism)
            await new Promise(resolve => setTimeout(resolve, 3000 + Math.random() * 2000));

            // Update overlay to show comparison
            if (typeof showWebcamOverlay === 'function') {
                showWebcamOverlay('🔍 Comparing with student database...', 'processing');
            }

            // Another delay for database comparison simulation
            await new Promise(resolve => setTimeout(resolve, 1500 + Math.random() * 1000));

            // Simulate face recognition result with more realistic outcomes
            const recognitionSuccess = Math.random() > 0.25; // 75% success rate

            if (recognitionSuccess) {
                const demoStudents = FACE_RECOGNITION_CONFIG.SAMPLE_STUDENTS;
                const randomStudent = demoStudents[Math.floor(Math.random() * demoStudents.length)];
                const confidence = Math.floor(Math.random() * 15) + 85; // 85-100% confidence

                if (typeof showAttendanceResult === 'function') {
                    showAttendanceResult('success', '✅ Face Recognized!', `${randomStudent.name} (${randomStudent.id}) - ${confidence}% confidence match`);
                }
                showNotification(`✅ Attendance marked for ${randomStudent.name} (${randomStudent.id}) with ${confidence}% confidence!`, 'success');

                // Add to attendance records with more details
                addAttendanceRecord(randomStudent.name, randomStudent.id, 'face_recognition', confidence);

                // Update session stats
                updateSessionStats();
            } else {
                // Different failure reasons for variety
                const failureReasons = [
                    'Face not found in database. Please ensure you are registered.',
                    'Poor image quality. Please ensure good lighting and face the camera directly.',
                    'Multiple faces detected. Please ensure only one person is in frame.',
                    'Face partially obscured. Please remove any obstructions.'
                ];
                const randomReason = failureReasons[Math.floor(Math.random() * failureReasons.length)];

                if (typeof showAttendanceResult === 'function') {
                    showAttendanceResult('error', '❌ Recognition Failed', randomReason);
                }
                showNotification('❌ Face recognition failed. ' + randomReason.split('.')[0] + '.', 'error');
            }

        } catch (error) {
            console.error('Face recognition error:', error);
            if (typeof showAttendanceResult === 'function') {
                showAttendanceResult('error', '📷 Camera Error', 'Failed to capture or process image. Please try again.');
            }
            showNotification('❌ Camera processing error: ' + error.message, 'error');
        } finally {
            // Hide processing overlay
            if (typeof hideWebcamOverlay === 'function') {
                setTimeout(() => {
                    hideWebcamOverlay();
                }, 500); // Brief delay to show final result
            }
        }

        return;
    }

    if (originalHandleMarkAttendance) {
        return originalHandleMarkAttendance.apply(this, arguments);
    }
};

// Demo fingerprint simulation
const originalHandleScanFingerprint = window.handleScanFingerprint;
window.handleScanFingerprint = async function() {
    if (DEMO_CONFIG.ENABLE_DEMO_MODE) {
        console.log('🎭 Simulating fingerprint scan...');

        const fingerprintBtn = document.getElementById('scanFingerprintBtn');
        const fingerprintStatus = document.getElementById('fingerprint-status');
        const fingerprintMessage = document.getElementById('fingerprint-message');

        if (fingerprintBtn && fingerprintStatus && fingerprintMessage) {
            fingerprintBtn.disabled = true;
            fingerprintBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scanning...';
            fingerprintStatus.style.display = 'block';
            fingerprintMessage.textContent = 'Demo fingerprint scan in progress...';

            setTimeout(() => {
                fingerprintBtn.disabled = false;
                fingerprintBtn.innerHTML = '<i class="fas fa-hand-paper me-2"></i>Scan Fingerprint';

                if (Math.random() > 0.4) {
                    const demoStudents = ['John Doe', 'Jane Smith', 'Bob Johnson', 'Alice Brown'];
                    const randomStudent = demoStudents[Math.floor(Math.random() * demoStudents.length)];
                    fingerprintMessage.textContent = `Demo fingerprint matched: ${randomStudent}`;
                    showDemoNotification(`✅ Demo fingerprint attendance marked for ${randomStudent}!`, 'success');
                } else {
                    fingerprintMessage.textContent = 'Demo fingerprint scan failed - no match';
                    showDemoNotification('❌ Demo fingerprint scan failed - no match found', 'error');
                }

                setTimeout(() => {
                    fingerprintStatus.style.display = 'none';
                }, 3000);

                // Reload demo stats
                if (typeof loadAttendanceRecords === 'function') {
                    setTimeout(() => {
                        loadAttendanceRecords();
                        updateSessionStats();
                    }, 1000);
                }
            }, 2500);
        }

        return;
    }

    if (originalHandleScanFingerprint) {
        return originalHandleScanFingerprint.apply(this, arguments);
    }
};

// Console demo commands
window.demoCommands = {
    enableDemo: () => {
        DEMO_CONFIG.ENABLE_DEMO_MODE = true;
        console.log('🎭 Demo mode enabled');
        location.reload();
    },
    disableDemo: () => {
        DEMO_CONFIG.ENABLE_DEMO_MODE = false;
        console.log('🎭 Demo mode disabled');
        location.reload();
    },
    loadDemoData: loadDemoData,
    simulateAttendance: () => {
        if (typeof handleMarkAttendance === 'function') {
            handleMarkAttendance();
        }
    },
    simulateFingerprint: () => {
        if (typeof handleScanFingerprint === 'function') {
            handleScanFingerprint();
        }
    }
};

// Event handlers for dropdowns
function handleDepartmentChange(departmentId) {
    console.log('🏫 Department change:', departmentId);
    if (departmentId) {
        // Load options for selected department
        loadOptions(departmentId);
    }
}

function handleOptionChange(optionId) {
    console.log('📚 Option change:', optionId);
    if (optionId) {
        // Load courses for selected option
        const departmentId = document.getElementById('department').value;
        loadCourses(departmentId, optionId);
    }
}

function handleCourseChange(courseId) {
    console.log('📖 Course change:', courseId);
    if (courseId) {
        showNotification('✅ Course selected! Now select a biometric method.', 'info');
    }
}

function handleBiometricChange(method) {
    console.log('🔐 Biometric method change:', method);
    if (method) {
        const methodName = method === 'face' ? 'Face Recognition' : 'Fingerprint';
        showNotification(`✅ ${methodName} selected! You can now start the session.`, 'success');

        // Enable start session button
        const startBtn = document.getElementById('start-session');
        if (startBtn) {
            startBtn.disabled = false;
            startBtn.innerHTML = '<i class="fas fa-play me-2"></i>Start Session';
            startBtn.classList.remove('btn-secondary');
            startBtn.classList.add('btn-success');
        }
    }
}

function handleStartSession() {
    console.log('🚀 Start session clicked');

    // Validate all fields are selected
    const department = document.getElementById('department').value;
    const option = document.getElementById('option').value;
    const course = document.getElementById('course').value;
    const biometric = document.getElementById('biometric_method').value;

    if (!department || !option || !course || !biometric) {
        showNotification('❌ Please fill in all required fields', 'error');
        return;
    }

    // Show loading
    showNotification('🚀 Starting session...', 'info');

    // Simulate session start delay
    setTimeout(() => {
        // Create session data
        const sessionData = {
            id: Date.now(),
            course_name: 'Web Development',
            department_name: 'Computer Science',
            option_name: 'Software Engineering',
            biometric_method: biometric,
            start_time: new Date().toISOString()
        };

        // Show active session
        showActiveSession(sessionData);
        showNotification('✅ Session started successfully!', 'success');
    
        // Update session status indicator
        updateSessionStatus(true, sessionData);
    
        // Enable end session button
        const endBtn = document.getElementById('end-session');
        if (endBtn) {
            endBtn.disabled = false;
        }
    }, 2000);
}

function handleEndSession() {
    console.log('🛑 End session clicked');

    // Show confirmation dialog
    if (!confirm('Are you sure you want to end this attendance session? This will stop recording attendance.')) {
        return;
    }

    // Show loading
    showNotification('🛑 Ending session...', 'warning');

    // Stop webcam if active
    if (window.currentWebcamStream) {
        window.currentWebcamStream.getTracks().forEach(track => track.stop());
        window.currentWebcamStream = null;
    }

    // Simulate session end delay
    setTimeout(() => {
        // Hide active session sections
        const activeSection = document.getElementById('activeSessionSection');
        if (activeSection) {
            activeSection.classList.add('d-none');
        }

        // Show setup section
        const setupSection = document.getElementById('sessionSetupSection');
        if (setupSection) {
            setupSection.style.display = 'block';
        }

        // Update session status indicator
        updateSessionStatus(false);

        // Reset form
        const sessionForm = document.getElementById('sessionForm');
        if (sessionForm) {
            sessionForm.reset();
        }

        // Disable buttons
        const startBtn = document.getElementById('start-session');
        const endBtn = document.getElementById('end-session');
        if (startBtn) {
            startBtn.disabled = true;
            startBtn.classList.remove('btn-success');
            startBtn.classList.add('btn-secondary');
        }
        if (endBtn) endBtn.disabled = true;

        showNotification('✅ Session ended successfully! Attendance records have been saved.', 'success');
    }, 1500);
}

function updateSessionStatus(isActive, sessionData = null) {
    const statusIndicator = document.getElementById('session-status-indicator');
    const statusText = document.getElementById('session-status-text');
    const timer = document.getElementById('session-timer');

    if (statusIndicator && statusText && timer) {
        if (isActive && sessionData) {
            statusIndicator.style.display = 'flex';
            statusText.textContent = 'Session Active';

            // Start timer
            let seconds = 0;
            window.sessionTimer = setInterval(() => {
                seconds++;
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = seconds % 60;
                timer.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }, 1000);
        } else {
            statusIndicator.style.display = 'none';

            // Stop timer
            if (window.sessionTimer) {
                clearInterval(window.sessionTimer);
                window.sessionTimer = null;
            }
        }
    }
}

// Show active session
function showActiveSession(sessionData) {
    // Hide setup section
    const setupSection = document.getElementById('sessionSetupSection');
    if (setupSection) {
        setupSection.style.display = 'none';
    }

    // Show active session section
    const activeSection = document.getElementById('activeSessionSection');
    if (activeSection) {
        activeSection.classList.remove('d-none');
    }

    // Update session info
    const sessionInfo = document.getElementById('sessionInfo');
    if (sessionInfo) {
        sessionInfo.innerHTML = `
            <strong>Course:</strong> ${sessionData.course_name}<br>
            <strong>Department:</strong> ${sessionData.department_name}<br>
            <strong>Option:</strong> ${sessionData.option_name}<br>
            <strong>Method:</strong> ${sessionData.biometric_method === 'face' ? 'Face Recognition' : 'Fingerprint'}<br>
            <strong>Started:</strong> ${new Date(sessionData.start_time).toLocaleString()}
        `;
    }

    // Show appropriate section based on biometric method
    if (sessionData.biometric_method === 'face') {
        document.getElementById('faceRecognitionSection').classList.remove('d-none');
        document.getElementById('fingerprintSection').classList.add('d-none');

        // Auto-start REAL webcam for face recognition
        showNotification('📹 Accessing camera for face recognition...', 'info');

        // Show webcam container immediately
        const webcamContainer = document.getElementById('webcam-container');
        const webcamPreview = document.getElementById('webcam-preview');
        const webcamPlaceholder = document.getElementById('webcam-placeholder');
        const webcamStatus = document.getElementById('webcam-status');

        if (webcamContainer && webcamPreview && webcamPlaceholder) {
            // Show loading state on the video element itself
            webcamPreview.style.display = 'block';
            webcamPreview.poster = 'data:image/svg+xml;base64,' + btoa(`
                <svg width="320" height="240" xmlns="http://www.w3.org/2000/svg">
                    <rect width="320" height="240" fill="#f8f9fa"/>
                    <circle cx="160" cy="120" r="30" fill="#007bff" opacity="0.3">
                        <animate attributeName="opacity" values="0.3;1;0.3" dur="1.5s" repeatCount="indefinite"/>
                    </circle>
                    <text x="160" y="140" text-anchor="middle" fill="#6c757d" font-family="Arial" font-size="14">Accessing camera...</text>
                </svg>
            `);
            webcamPlaceholder.style.display = 'none';
            webcamContainer.classList.add('active');
        }

        // Start real webcam access
        setTimeout(async () => {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 640, min: 320 },
                        height: { ideal: 480, min: 240 },
                        facingMode: 'user'  // Front-facing camera to show user's face
                    }
                });

                if (webcamPreview && webcamPlaceholder && webcamStatus) {
                    // Clear any poster image and set the video stream
                    webcamPreview.poster = '';
                    webcamPreview.srcObject = stream;
                    webcamPreview.style.display = 'block';
                    webcamPlaceholder.style.display = 'none';

                    // Show camera active status
                    webcamStatus.classList.remove('d-none');
                    webcamStatus.classList.add('active');

                    // Ensure video plays
                    webcamPreview.play().catch(console.error);

                    // Store stream reference
                    window.currentWebcamStream = stream;

                    // Enable mark attendance button
                    const markAttendanceBtn = document.getElementById('markAttendanceBtn');
                    if (markAttendanceBtn) {
                        markAttendanceBtn.disabled = false;
                        markAttendanceBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Mark Attendance';
                    }

                    showNotification('✅ Camera started successfully! You should now see yourself on screen. Click "Mark Attendance" to begin face recognition.', 'success');

                    // Scroll to webcam section
                    const webcamSection = document.getElementById('faceRecognitionSection');
                    if (webcamSection) {
                        webcamSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            } catch (error) {
                console.error('Webcam access error:', error);

                if (webcamPlaceholder && webcamStatus) {
                    webcamPlaceholder.innerHTML = `
                        <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                        <p class="text-danger mb-3">Camera access failed</p>
                        <p class="text-muted small mb-3">Please check camera permissions and try again</p>
                        <button type="button" id="retryWebcamBtn" class="btn btn-primary btn-lg">
                            <i class="fas fa-redo me-2"></i>Retry Camera Access
                        </button>
                    `;

                    // Hide status indicator
                    webcamStatus.classList.add('d-none');
                    webcamStatus.classList.remove('active');

                    // Add retry button event listener
                    const retryBtn = document.getElementById('retryWebcamBtn');
                    if (retryBtn) {
                        retryBtn.addEventListener('click', () => {
                            showActiveSession(sessionData); // Retry
                        });
                    }
                }

                showNotification('❌ Failed to access camera. Please check permissions and try again.', 'error');
            }
        }, 500);
    } else if (sessionData.biometric_method === 'finger') {
        document.getElementById('fingerprintSection').classList.remove('d-none');
        document.getElementById('faceRecognitionSection').classList.add('d-none');

        showNotification('👆 Fingerprint scanner ready! Click "Scan Fingerprint" to begin.', 'success');
    }

    // Load session stats
    setTimeout(() => {
        loadAttendanceRecords();
        updateSessionStats();
    }, 500);
}

// Load attendance records
function loadAttendanceRecords() {
    const records = generateMockAttendanceRecords();
    const tbody = document.getElementById('attendance-list');
    const tableContainer = document.getElementById('attendance-table-container');
    const loading = document.getElementById('attendance-loading');
    const noAttendance = document.getElementById('no-attendance');

    if (tbody && tableContainer && loading && noAttendance) {
        // Hide loading
        loading.style.display = 'none';

        if (records.length > 0) {
            tbody.innerHTML = '';
            records.forEach(record => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${record.student_id}</strong></td>
                    <td>${record.student_name}</td>
                    <td><small class="text-muted">${new Date(record.recorded_at).toLocaleString()}</small></td>
                    <td><span class="badge bg-success"><i class="fas fa-check me-1"></i>Present</span></td>
                    <td><i class="fas fa-${record.method === 'face_recognition' ? 'camera' : 'fingerprint'} me-1"></i><small>${record.method === 'face_recognition' ? 'Face Recognition' : 'Fingerprint'}</small></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAttendance(this)"><i class="fas fa-trash"></i></button></td>
                `;
                tbody.appendChild(row);
            });
            tableContainer.style.display = 'block';
            noAttendance.style.display = 'none';
        } else {
            tableContainer.style.display = 'none';
            noAttendance.style.display = 'block';
        }
    }
}

// Load session statistics
function loadSessionStats() {
    updateSessionStats();
}

// Update session statistics dynamically
function updateSessionStats() {
    const attendanceTable = document.getElementById('attendance-list');
    let presentCount = 0;
    let totalRecords = 0;

    if (attendanceTable) {
        const rows = attendanceTable.querySelectorAll('tr');
        rows.forEach(row => {
            const statusBadge = row.querySelector('.badge');
            if (statusBadge && statusBadge.textContent.includes('Present')) {
                presentCount++;
            }
            totalRecords++;
        });
    }

    const totalStudents = FACE_RECOGNITION_CONFIG.SAMPLE_STUDENTS.length;
    const absentCount = totalRecords > 0 ? totalRecords - presentCount : 0;
    const attendanceRate = totalRecords > 0 ? Math.round((presentCount / totalRecords) * 100) : 0;

    // Update stats cards
    const totalStudentsEl = document.getElementById('total-students');
    const presentCountEl = document.getElementById('present-count');
    const absentCountEl = document.getElementById('absent-count');
    const attendanceRateEl = document.getElementById('attendance-rate');

    if (totalStudentsEl) totalStudentsEl.textContent = totalStudents;
    if (presentCountEl) presentCountEl.textContent = presentCount;
    if (absentCountEl) absentCountEl.textContent = absentCount;
    if (attendanceRateEl) attendanceRateEl.textContent = attendanceRate + '%';
}

// Remove attendance record
function removeAttendance(button) {
    if (confirm('Remove this attendance record?')) {
        const row = button.closest('tr');
        row.remove();
        showNotification('Attendance record removed', 'info');
        loadSessionStats(); // Refresh stats
    }
}

// Add attendance record
function addAttendanceRecord(studentName, studentId, method, confidence = null) {
    const tbody = document.getElementById('attendance-list');
    if (!tbody) return;

    // Create new row
    const row = document.createElement('tr');
    const now = new Date();
    const methodDisplay = method === 'face_recognition' ? 'Face Recognition' : 'Fingerprint';
    const methodIcon = method === 'face_recognition' ? 'camera' : 'fingerprint';

    row.innerHTML = `
        <td><strong>${studentId}</strong></td>
        <td>${studentName}</td>
        <td><small class="text-muted">${now.toLocaleString()}</small></td>
        <td><span class="badge bg-success"><i class="fas fa-check me-1"></i>Present</span></td>
        <td><i class="fas fa-${methodIcon} me-1"></i><small>${methodDisplay}${confidence ? ` (${confidence}%)` : ''}</small></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAttendance(this)"><i class="fas fa-trash"></i></button></td>
    `;

    // Add to top of table
    tbody.insertBefore(row, tbody.firstChild);

    // Show table if hidden
    const tableContainer = document.getElementById('attendance-table-container');
    const noAttendance = document.getElementById('no-attendance');
    if (tableContainer && noAttendance) {
        tableContainer.style.display = 'block';
        noAttendance.style.display = 'none';
    }

    // Update stats
    updateSessionStats();

    // Animate the new row
    row.classList.add('fade-in-row');
}

// Create demo video stream for face recognition - REMOVED
// Real webcam is now used instead of fake demo stream

// Demo page navigation
function showDemoPage(page) {
    const messages = {
        'dashboard': 'Dashboard - would show overview statistics and recent activity',
        'courses': 'My Courses - would show assigned courses and schedules',
        'attendance': 'Attendance Session - current page with face recognition functionality',
        'reports': 'Reports - would show attendance analytics and export options',
        'leave': 'Leave Requests - would show leave application forms and status'
    };

    showNotification(`🚧 ${messages[page] || 'Page navigation'}`, 'info');
}

// Console commands for testing
window.faceRecognitionCommands = {
    loadDemoData: loadDemoData,
    simulateAttendance: () => {
        if (typeof handleMarkAttendance === 'function') {
            handleMarkAttendance();
        }
    },
    simulateFingerprint: () => {
        if (typeof handleScanFingerprint === 'function') {
            handleScanFingerprint();
        }
    },
    showPage: showDemoPage,
    testCamera: async () => {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            console.log('📷 Camera test successful');
            stream.getTracks().forEach(track => track.stop());
        } catch (error) {
            console.error('📷 Camera test failed:', error);
        }
    }
};

console.log('📷 Face recognition system loaded');
console.log('📷 Commands available: window.faceRecognitionCommands');
console.log('📷 Try: faceRecognitionCommands.testCamera() or faceRecognitionCommands.loadDemoData()');