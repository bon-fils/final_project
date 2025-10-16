// Attendance Session JavaScript

// Configuration constants
const CONFIG = {
  ESP32_DEFAULT_IP: '192.168.137.63',
  API_TIMEOUT: 10000,
  MONITORING_INTERVAL: 30000,
  NOTIFICATION_DURATION: 6000,
  WEBCAM_OVERLAY_DURATION: 3000
};

// Global state
const state = {
  currentSessionId: null,
  webcamStream: null,
  csrfToken: '<?php echo bin2hex(random_bytes(32)); ?>',
  esp32IP: '192.168.1.100',
  allCourses: [],
  filteredCourses: [],
  monitoringInterval: null
};

// DOM elements cache
const elements = {
  departmentSelect: document.getElementById('department'),
  optionSelect: document.getElementById('option'),
  courseSelect: document.getElementById('course'),
  biometricMethodSelect: document.getElementById('biometric_method'),
  courseSearch: document.getElementById('course-search'),
  courseLoading: document.getElementById('course-loading'),
  startBtn: document.getElementById('start-session'),
  endBtn: document.getElementById('end-session'),
  sessionForm: document.getElementById('sessionForm')
};

// Initialize the page
document.addEventListener('DOMContentLoaded', function() {
  loadDepartments();
  setupEventListeners();
  checkExistingSession();
  updateCourseInfo('Select your assigned department and option to load available courses');
});

// Utility functions
const utils = {
  // Debounce function for search input
  debounce: (func, wait) => {
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

  // Validate required fields
  validateFormData: (formData) => {
    const required = ['department_id', 'option_id', 'course_id', 'biometric_method'];
    return required.every(field => formData.get(field));
  },

  // Create fetch with timeout
  fetchWithTimeout: (url, options = {}) => {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), CONFIG.API_TIMEOUT);

    return fetch(url, {
      ...options,
      signal: controller.signal
    }).finally(() => clearTimeout(timeoutId));
  }
};

// Setup event listeners
function setupEventListeners() {
  elements.departmentSelect.addEventListener('change', handleDepartmentChange);
  elements.optionSelect.addEventListener('change', handleOptionChange);
  elements.courseSelect.addEventListener('change', validateForm);
  elements.biometricMethodSelect.addEventListener('change', handleBiometricMethodChange);

  // Debounced search for better performance
  const debouncedSearch = utils.debounce(handleCourseSearch, 300);
  elements.courseSearch.addEventListener('input', debouncedSearch);

  elements.courseSearch.addEventListener('focus', () => elements.courseSearch.style.display = 'block');
  elements.courseSearch.addEventListener('blur', () => {
    setTimeout(() => {
      if (!elements.courseSearch.matches(':focus')) {
        elements.courseSearch.style.display = 'none';
      }
    }, 200);
  });

  elements.startBtn.addEventListener('click', handleStartSession);
  elements.endBtn.addEventListener('click', handleEndSession);
  document.getElementById('markAttendanceBtn').addEventListener('click', handleMarkAttendance);
  document.getElementById('scanFingerprintBtn').addEventListener('click', handleScanFingerprint);
  document.getElementById('startWebcamBtn').addEventListener('click', startWebcam);
  document.getElementById('testESP32Btn').addEventListener('click', testESP32Connection);
  document.getElementById('refresh-attendance').addEventListener('click', loadAttendanceRecords);
  document.getElementById('export-attendance').addEventListener('click', handleExportAttendance);
}

// Handle biometric method change
function handleBiometricMethodChange() {
  const method = biometricMethodSelect.value;
  const webcamSection = document.getElementById('webcam-section');
  const fingerprintSection = document.getElementById('fingerprint-section');

  if (method === 'face') {
    webcamSection.classList.remove('d-none');
    fingerprintSection.classList.add('d-none');
  } else if (method === 'finger') {
    fingerprintSection.classList.remove('d-none');
    webcamSection.classList.add('d-none');
  } else {
    webcamSection.classList.add('d-none');
    fingerprintSection.classList.add('d-none');
  }

  validateForm();
}

// Load departments from API
async function loadDepartments() {
  try {
    // Show loading state
    departmentSelect.innerHTML = '<option value="" disabled selected>Loading departments...</option>';
    departmentSelect.disabled = true;

    console.log('Loading departments...');
    const response = await fetch('api/attendance-session-api.php?action=get_departments', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    const result = await response.json();
    console.log('Departments API response:', result);

    if (result.status === 'success') {
      departmentSelect.innerHTML = '<option value="" disabled selected>Select Department</option>';

      if (result.data.length === 0) {
        departmentSelect.innerHTML += '<option value="" disabled>No departments available</option>';
        updateCourseInfo('⚠️ No departments are assigned to your account. Please contact your administrator to get access to attendance sessions.');
        showNotification('No departments are assigned to your account. Please contact your administrator.', 'warning');

        // Hide the "Department Access Required" alert since we got a successful response
        const noDepartmentInfo = document.getElementById('noDepartmentInfo');
        if (noDepartmentInfo) {
          noDepartmentInfo.style.display = 'none';
        }
      } else {
        result.data.forEach(dept => {
          const option = document.createElement('option');
          option.value = dept.id;
          option.textContent = dept.name;
          departmentSelect.appendChild(option);
        });
        updateCourseInfo(`✅ Loaded ${result.data.length} department(s). Select your department to continue.`);
        showNotification(`✅ Loaded ${result.data.length} department(s) for your account`, 'success');

        // Hide the "Department Access Required" alert since departments were loaded
        const noDepartmentInfo = document.getElementById('noDepartmentInfo');
        if (noDepartmentInfo) {
          noDepartmentInfo.style.display = 'none';
        }
      }
    } else {
      departmentSelect.innerHTML = '<option value="" disabled selected>Error loading departments</option>';
      showNotification('Error loading departments: ' + result.message, 'error');
      console.error('API Error:', result);
    }
  } catch (error) {
    console.error('Error loading departments:', error);
    departmentSelect.innerHTML = '<option value="" disabled selected>Failed to load departments</option>';
    showNotification('Failed to load departments. Please refresh the page.', 'error');
  } finally {
    departmentSelect.disabled = false;
  }
}

// Handle department change
async function handleDepartmentChange() {
  const departmentId = elements.departmentSelect.value;

  // Reset dependent selects
  resetDependentSelects();

  if (departmentId) {
    await loadOptions(departmentId);
    updateCourseInfo('Select option to load available courses');
  } else {
    updateCourseInfo('Select your assigned department to continue');
  }
}

// Reset dependent form elements
function resetDependentSelects() {
  elements.optionSelect.innerHTML = '<option value="" disabled selected>Select Option</option>';
  elements.optionSelect.disabled = true;
  elements.courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
  elements.courseSelect.disabled = true;
  elements.courseSearch.style.display = 'none';
  elements.courseSearch.value = '';
  state.allCourses = [];
  state.filteredCourses = [];
  elements.startBtn.disabled = true;
}

// Load options for selected department
async function loadOptions(departmentId) {
  try {
    // Show loading state
    optionSelect.innerHTML = '<option value="" disabled selected>Loading options...</option>';
    optionSelect.disabled = true;

    const response = await fetch(`api/attendance-session-api.php?action=get_options&department_id=${departmentId}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    const result = await response.json();

    if (result.status === 'success') {
      optionSelect.innerHTML = '<option value="" disabled selected>Select Option</option>';

      if (result.data.length === 0) {
        optionSelect.innerHTML += '<option value="" disabled>No options available</option>';
        updateCourseInfo('⚠️ No options available for the selected department.');
        showNotification('No options available for the selected department.', 'warning');
      } else {
        result.data.forEach(opt => {
          const option = document.createElement('option');
          option.value = opt.id;
          option.textContent = opt.name;
          optionSelect.appendChild(option);
        });
        updateCourseInfo(`✅ Loaded ${result.data.length} option(s). Select an option to continue.`);
        showNotification(`✅ Loaded ${result.data.length} option(s)`, 'success');
      }

      optionSelect.disabled = false;
    } else {
      optionSelect.innerHTML = '<option value="" disabled selected>Error loading options</option>';
      showNotification('Error loading options: ' + result.message, 'error');
    }
  } catch (error) {
    console.error('Error loading options:', error);
    optionSelect.innerHTML = '<option value="" disabled selected>Failed to load options</option>';
    showNotification('Failed to load options. Please try again.', 'error');
  } finally {
    optionSelect.disabled = false;
  }
}

// Handle option change
async function handleOptionChange() {
  const departmentId = elements.departmentSelect.value;
  const optionId = elements.optionSelect.value;

  elements.courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
  elements.courseSelect.disabled = true;
  elements.courseSearch.style.display = 'none';
  elements.courseSearch.value = '';
  elements.startBtn.disabled = true;

  if (departmentId && optionId) {
    await loadCourses(departmentId, optionId);
  } else if (departmentId) {
    updateCourseInfo('Select option to load courses');
  } else {
    updateCourseInfo('Select department and option to load courses');
  }

  validateForm();
}

// Load courses for selected department and option
async function loadCourses(departmentId, optionId) {
  try {
    // Show loading state
    courseLoading.style.display = 'block';
    courseSelect.innerHTML = '<option value="" disabled selected>Loading courses...</option>';
    courseSelect.disabled = true;

    const response = await fetch(`api/attendance-session-api.php?action=get_courses&department_id=${departmentId}&option_id=${optionId}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    const result = await response.json();

    // Hide loading state
    courseLoading.style.display = 'none';

    if (result.status === 'success') {
      // Store all courses for search functionality
      allCourses = result.data.sort((a, b) => a.name.localeCompare(b.name));
      filteredCourses = [...allCourses];

      // Populate course dropdown
      courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';

      if (allCourses.length === 0) {
        courseSelect.innerHTML += '<option value="" disabled>No courses available</option>';
        updateCourseInfo('⚠️ No courses available for the selected department and option. Please contact your administrator to add courses.');
        showNotification('No courses available for the selected department and option. Please contact your administrator to add courses.', 'warning');
      } else {
        allCourses.forEach(course => {
          const option = document.createElement('option');
          option.value = course.id;

          // Enhanced course display with more details
          const semester = course.semester ? `Sem ${course.semester}` : '';
          const credits = course.credits ? `${course.credits}cr` : '';
          const availability = course.is_available ? '' : ' (Not Available)';
          const lecturer = course.lecturer_name !== 'Unknown Lecturer' ? course.lecturer_name : '';

          let displayText = `${course.name} (${course.course_code})`;
          if (semester || credits) {
            displayText += ` - ${[semester, credits].filter(Boolean).join(', ')}`;
          }
          if (lecturer) {
            displayText += ` - ${lecturer}`;
          }
          displayText += availability;

          option.textContent = displayText;
          option.title = `Course: ${course.name}\nCode: ${course.course_code}\nDescription: ${course.description || 'N/A'}\nCredits: ${course.credits || 'N/A'}\nSemester: ${course.semester || 'N/A'}\nLecturer: ${lecturer || 'Not assigned'}`;

          courseSelect.appendChild(option);
        });

        courseSelect.disabled = false;

        // Hide test API button when courses load successfully
        const testApiBtn = document.getElementById('test-api');
        if (testApiBtn) {
          testApiBtn.style.display = 'none';
        }

        // Show course count
        updateCourseInfo(`✅ Loaded ${allCourses.length} course(s). Press F2 to search.`);
        if (allCourses.length > 5) {
          showNotification(`✅ Loaded ${allCourses.length} courses. Press F2 to search or use the search box.`, 'success');
        } else {
          showNotification(`✅ Loaded ${allCourses.length} course(s)`, 'success');
        }
      }
    } else {
      console.error('API Error:', result);
      courseSelect.innerHTML = '<option value="" disabled selected>Error loading courses</option>';
      updateCourseInfo('⚠️ Error loading courses. Please try again or contact your administrator.');
      showNotification('Error loading courses: ' + result.message, 'error');

      // Show test API button when there's an error
      const testApiBtn = document.getElementById('test-api');
      if (testApiBtn) {
        testApiBtn.style.display = 'inline-block';
      }
    }
  } catch (error) {
    courseLoading.style.display = 'none';
    console.error('Error loading courses:', error);
    courseSelect.innerHTML = '<option value="" disabled selected>Failed to load courses</option>';
    updateCourseInfo('⚠️ Failed to load courses. Please check your connection and try again.');
    showNotification('Failed to load courses. Please try again.', 'error');
  } finally {
    courseSelect.disabled = false;
  }
}

// Handle course search
function handleCourseSearch() {
  const searchTerm = elements.courseSearch.value.toLowerCase().trim();

  if (!searchTerm) {
    state.filteredCourses = [...state.allCourses];
    updateCourseInfo(`Showing all ${state.allCourses.length} course(s). Press F2 to search.`);
  } else {
    state.filteredCourses = state.allCourses.filter(course =>
      course.name.toLowerCase().includes(searchTerm) ||
      course.course_code.toLowerCase().includes(searchTerm) ||
      course.description?.toLowerCase().includes(searchTerm) ||
      course.lecturer_name?.toLowerCase().includes(searchTerm)
    );
    updateCourseInfo(`Found ${state.filteredCourses.length} course(s) matching "${searchTerm}"`);
  }

  // Update course dropdown
  populateCourseDropdown(state.filteredCourses);

  // Show search results count
  if (searchTerm) {
    showNotification(`Found ${state.filteredCourses.length} course(s) matching "${searchTerm}"`, 'info');
  }
}

// Populate course dropdown (reusable function)
function populateCourseDropdown(courses) {
  elements.courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';

  if (courses.length === 0) {
    elements.courseSelect.innerHTML += '<option value="" disabled>No courses match your search</option>';
  } else {
    courses.forEach(course => {
      const option = document.createElement('option');
      option.value = course.id;

      // Enhanced course display with more details
      const semester = course.semester ? `Sem ${course.semester}` : '';
      const credits = course.credits ? `${course.credits}cr` : '';
      const availability = course.is_available ? '' : ' (Not Available)';
      const lecturer = course.lecturer_name !== 'Unknown Lecturer' ? course.lecturer_name : '';

      let displayText = `${course.name} (${course.course_code})`;
      if (semester || credits) {
        displayText += ` - ${[semester, credits].filter(Boolean).join(', ')}`;
      }
      if (lecturer) {
        displayText += ` - ${lecturer}`;
      }
      displayText += availability;

      option.textContent = displayText;
      option.title = `Course: ${course.name}\nCode: ${course.course_code}\nDescription: ${course.description || 'N/A'}\nCredits: ${course.credits || 'N/A'}\nSemester: ${course.semester || 'N/A'}\nLecturer: ${lecturer || 'Not assigned'}`;

      elements.courseSelect.appendChild(option);
    });
  }
}

// Update course info text
function updateCourseInfo(message) {
  const courseInfo = document.getElementById('course-info');
  if (courseInfo) {
    courseInfo.textContent = message;
  }
}

// Validate form
function validateForm() {
  const isValid = elements.departmentSelect.value &&
                  elements.optionSelect.value &&
                  elements.courseSelect.value &&
                  elements.biometricMethodSelect.value;

  elements.startBtn.disabled = !isValid;

  // Debug logging
  console.log('Form validation:', {
    department: elements.departmentSelect.value,
    option: elements.optionSelect.value,
    course: elements.courseSelect.value,
    biometric_method: elements.biometricMethodSelect.value,
    isValid: isValid
  });

  // Update button text to show validation status
  updateStartButton(isValid);
}

// Update start button appearance
function updateStartButton(isValid) {
  if (isValid) {
    elements.startBtn.innerHTML = '<i class="fas fa-play me-2" aria-hidden="true"></i> Start Session';
    elements.startBtn.classList.remove('btn-secondary');
    elements.startBtn.classList.add('btn-primary');
  } else {
    elements.startBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i> Fill All Fields';
    elements.startBtn.classList.remove('btn-primary');
    elements.startBtn.classList.add('btn-secondary');
  }
}

// Check for existing session
async function checkExistingSession() {
  try {
    // First, try to get any active session for the current user
    const response = await utils.fetchWithTimeout('api/attendance-session-api.php?action=get_user_active_session', {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });

    const result = await response.json();

    if (result.status === 'success' && result.data) {
      state.currentSessionId = result.data.id;
      showActiveSession(result.data);

      // Populate form with session details
      populateFormWithSessionData(result.data);

      showNotification('Resumed active attendance session', 'info');
      return;
    }

    // Fallback: check based on current form selection
    await checkFormBasedSession();
  } catch (error) {
    console.error('Error checking existing session:', error);
  }
}

// Populate form with session data
function populateFormWithSessionData(sessionData) {
  if (sessionData.department_id) elements.departmentSelect.value = sessionData.department_id;
  if (sessionData.option_id) elements.optionSelect.value = sessionData.option_id;
  if (sessionData.course_id) elements.courseSelect.value = sessionData.course_id;
  if (sessionData.biometric_method) elements.biometricMethodSelect.value = sessionData.biometric_method;

  // Trigger dependent dropdowns
  if (sessionData.department_id) {
    loadOptions(sessionData.department_id).then(() => {
      elements.optionSelect.value = sessionData.option_id;
      loadCourses(sessionData.department_id, sessionData.option_id).then(() => {
        elements.courseSelect.value = sessionData.course_id;
      });
    });
  }
}

// Check for session based on form values
async function checkFormBasedSession() {
  const departmentId = elements.departmentSelect.value;
  const optionId = elements.optionSelect.value;
  const courseId = elements.courseSelect.value;

  if (departmentId && optionId && courseId) {
    try {
      const response = await utils.fetchWithTimeout(
        `api/attendance-session-api.php?action=get_session_status&department_id=${departmentId}&option_id=${optionId}&course_id=${courseId}`,
        {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        }
      );

      const result = await response.json();

      if (result.status === 'success' && result.data && result.is_active) {
        state.currentSessionId = result.data.id;
        showActiveSession(result.data);
      }
    } catch (error) {
      console.error('Error checking form-based session:', error);
    }
  }
}

// Handle start session
async function handleStartSession(e) {
  e.preventDefault();

  console.log('🚀 Starting session...');

  const formData = new FormData(elements.sessionForm);
  formData.append('csrf_token', state.csrfToken);

  // Debug logging
  console.log('Starting session with data:', {
    department_id: formData.get('department_id'),
    option_id: formData.get('option_id'),
    course_id: formData.get('course_id'),
    biometric_method: formData.get('biometric_method'),
    csrf_token: formData.get('csrf_token')
  });

  // Validate form data
  if (!utils.validateFormData(formData)) {
    console.error('❌ Missing required fields');
    showNotification('Please fill in all required fields (Department, Option, Course, Biometric Method)', 'error');
    return;
  }

  // Additional validation
  const biometricMethod = formData.get('biometric_method');
  if (!['face', 'finger'].includes(biometricMethod)) {
    showNotification('Invalid biometric method selected', 'error');
    return;
  }

  try {
    showLoading('Starting session...');

    console.log('📡 Sending request to API...');

    const response = await utils.fetchWithTimeout('api/start_session.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    // Debug logging
    console.log('📡 Session start response:', result);

    hideLoading();

    if (result.status === 'success') {
      state.currentSessionId = result.session_id;
      showNotification('Session started successfully!', 'success');
      showActiveSession(result.data);
      startAttendanceMonitoring();
    } else if (result.status === 'existing_session') {
      // Handle existing session - ask user what to do
      handleExistingSession(result.existing_session, formData);
    } else {
      showNotification('Error starting session: ' + (result.message || 'Unknown error'), 'error');
    }
  } catch (error) {
    hideLoading();
    console.error('Error starting session:', error);
    showNotification('Failed to start session. Please check your connection and try again.', 'error');
  }
}

// Handle mark attendance button
async function handleMarkAttendance() {
  if (!state.currentSessionId) {
    showNotification('No active session', 'error');
    return;
  }

  try {
    // Show processing overlay
    showWebcamOverlay('Processing...', 'processing');

    // Capture image from webcam
    const imageData = captureWebcamImage();

    if (!imageData) {
      hideWebcamOverlay();
      showNotification('Failed to capture image from webcam', 'error');
      return;
    }

    // Validate image data
    if (imageData.length < 1000) { // Basic check for valid image data
      hideWebcamOverlay();
      showNotification('Invalid image captured. Please try again.', 'error');
      return;
    }

    // Send to server for face recognition
    const formData = new FormData();
    formData.append('action', 'process_face_recognition');
    formData.append('image_data', imageData);
    formData.append('session_id', state.currentSessionId);
    formData.append('csrf_token', state.csrfToken);

    const response = await fetch('attendance-session.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    hideWebcamOverlay();

    if (result.status === 'success') {
      // Validate response data
      if (!result.student_name || !result.student_reg) {
        showNotification('Invalid response from server', 'error');
        return;
      }

      // Show success result
      showAttendanceResult('success', 'Attendance Marked!', `${result.student_name} (${result.student_reg}) - ${result.confidence || 0}% confidence`);
      showNotification(`✅ Attendance marked for ${result.student_name}!`, 'success');

      // Reload session stats
      loadAttendanceRecords();
      loadSessionStats();
    } else {
      // Show error result
      showAttendanceResult('error', 'No Match Found', result.message || 'Face not recognized');
      showNotification('❌ ' + (result.message || 'No face match found'), 'error');
    }

  } catch (error) {
    hideWebcamOverlay();
    console.error('Face recognition error:', error);
    showAttendanceResult('error', 'Error', 'Face recognition failed');
    showNotification('Face recognition error: ' + (error.message || 'Unknown error'), 'error');
  }
}

// Capture image from webcam
function captureWebcamImage() {
  const video = document.getElementById('webcam-preview');
  const canvas = document.createElement('canvas');

  if (!video.videoWidth || !video.videoHeight) {
    return null;
  }

  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;

  const ctx = canvas.getContext('2d');
  ctx.drawImage(video, 0, 0);

  return canvas.toDataURL('image/jpeg', 0.8);
}

// Show webcam overlay
function showWebcamOverlay(message, type = 'processing') {
  const overlay = document.getElementById('webcam-overlay');
  const status = document.getElementById('webcam-status');

  status.textContent = message;
  overlay.className = `webcam-overlay ${type}`;
  overlay.style.display = 'flex';
}

// Hide webcam overlay
function hideWebcamOverlay() {
  document.getElementById('webcam-overlay').style.display = 'none';
}

// Show attendance result
function showAttendanceResult(type, title, message) {
  const resultDiv = document.getElementById('attendanceResult');
  const titleDiv = document.getElementById('resultTitle');
  const messageDiv = document.getElementById('resultMessage');

  resultDiv.className = `attendance-result ${type}`;
  titleDiv.textContent = title;
  messageDiv.textContent = message;
  resultDiv.style.display = 'block';

  // Auto-hide after 5 seconds
  setTimeout(() => {
    resultDiv.style.display = 'none';
  }, 5000);
}

// Start webcam
async function startWebcam() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({
      video: {
        width: { ideal: 640 },
        height: { ideal: 480 },
        facingMode: 'user'
      }
    });

    const video = document.getElementById('webcam-preview');
    const placeholder = document.getElementById('webcam-placeholder');

    video.srcObject = stream;
    webcamStream = stream;

    video.style.display = 'block';
    placeholder.style.display = 'none';

    // Enable mark attendance button
    document.getElementById('markAttendanceBtn').disabled = false;

  } catch (error) {
    console.error('Webcam error:', error);
    showNotification('Could not access webcam. Please check permissions.', 'error');
    document.getElementById('markAttendanceBtn').disabled = true;
  }
}

// Stop webcam
function stopWebcam() {
  if (webcamStream) {
    webcamStream.getTracks().forEach(track => track.stop());
    webcamStream = null;
  }

  const video = document.getElementById('webcam-preview');
  const placeholder = document.getElementById('webcam-placeholder');

  video.style.display = 'none';
  video.srcObject = null;
  placeholder.style.display = 'block';
}

// Handle end session
async function handleEndSession() {
  if (!state.currentSessionId) {
    showNotification('No active session to end', 'warning');
    return;
  }

  try {
    const formData = new FormData();
    formData.append('session_id', state.currentSessionId);
    formData.append('csrf_token', state.csrfToken);

    const response = await utils.fetchWithTimeout('api/end_session.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    if (result.status === 'success') {
      showNotification('Session ended successfully!', 'success');
      hideActiveSession();
    } else {
      showNotification('Error ending session: ' + (result.message || 'Unknown error'), 'error');
    }
  } catch (error) {
    console.error('Error ending session:', error);
    showNotification('Failed to end session: ' + (error.message || 'Unknown error'), 'error');
  }
}

// Show active session UI
function showActiveSession(sessionData) {
  document.getElementById('sessionSetupSection').style.display = 'none';
  document.getElementById('activeSessionSection').classList.remove('d-none');

  // Update session info
  updateSessionInfo(sessionData);

  // Enable mark attendance button
  document.getElementById('markAttendanceBtn').disabled = false;

  // Start webcam if face recognition
  if (sessionData.biometric_method === 'face') {
    startWebcam();
  }

  // Load session stats
  loadAttendanceRecords();
  loadSessionStats();
}

// Update session information display
function updateSessionInfo(sessionData) {
  const sessionInfo = `
    <strong>Course:</strong> ${sessionData.course_name || 'Unknown'}<br>
    <strong>Department:</strong> ${sessionData.department_name || 'Unknown'}<br>
    <strong>Option:</strong> ${sessionData.option_name || 'Unknown'}<br>
    <strong>Method:</strong> ${sessionData.biometric_method === 'face' ? 'Face Recognition' : 'Fingerprint'}<br>
    <strong>Started:</strong> ${new Date(sessionData.start_time).toLocaleString()}
  `;

  document.getElementById('sessionInfo').innerHTML = sessionInfo;
}

// Hide active session UI
function hideActiveSession() {
  document.getElementById('activeSessionSection').classList.add('d-none');
  document.getElementById('sessionSetupSection').style.display = 'block';

  state.currentSessionId = null;
  document.getElementById('markAttendanceBtn').disabled = true;

  // Stop monitoring and webcam
  stopAttendanceMonitoring();
  stopWebcam();
}

// Load session statistics
async function loadSessionStats() {
  if (!state.currentSessionId) return;

  try {
    const response = await utils.fetchWithTimeout(`api/attendance-session-api.php?action=get_session_stats&session_id=${state.currentSessionId}`);
    const result = await response.json();

    if (result.status === 'success') {
      const stats = result.data;
      updateSessionStatsDisplay(stats);
    }
  } catch (error) {
    console.error('Error loading session stats:', error);
  }
}

// Update session statistics display
function updateSessionStatsDisplay(stats) {
  const statsHtml = `
    <div class="alert alert-info">
      <strong>Session Statistics:</strong><br>
      Total Students: ${stats.total_students || 0}<br>
      Present: ${stats.present_count || 0}<br>
      Absent: ${stats.absent_count || 0}<br>
      Attendance Rate: ${stats.attendance_rate || 0}%
    </div>
  `;

  document.getElementById('sessionStats').innerHTML = statsHtml;
}

// Load attendance records
async function loadAttendanceRecords() {
  if (!state.currentSessionId) return;

  try {
    // Show loading state
    showAttendanceLoading(true);

    const response = await utils.fetchWithTimeout(`api/attendance-session-api.php?action=get_attendance_records&session_id=${state.currentSessionId}`);
    const result = await response.json();

    // Hide loading state
    showAttendanceLoading(false);

    if (result.status === 'success' && result.data.length > 0) {
      renderAttendanceTable(result.data);
    } else {
      showNoAttendance();
    }
  } catch (error) {
    console.error('Error loading attendance records:', error);
    showAttendanceLoading(false);
    showNoAttendance();
  }
}

// Show/hide attendance loading state
function showAttendanceLoading(show) {
  document.getElementById('attendance-loading').style.display = show ? 'block' : 'none';
  document.getElementById('attendance-table-container').style.display = show ? 'none' : 'block';
  document.getElementById('no-attendance').style.display = 'none';
}

// Show no attendance message
function showNoAttendance() {
  document.getElementById('attendance-loading').style.display = 'none';
  document.getElementById('attendance-table-container').style.display = 'none';
  document.getElementById('no-attendance').style.display = 'block';
}

// Render attendance table
function renderAttendanceTable(records) {
  const tbody = document.getElementById('attendance-list');
  tbody.innerHTML = '';

  records.forEach(record => {
    const row = createAttendanceRow(record);
    tbody.appendChild(row);
  });

  document.getElementById('attendance-table-container').style.display = 'block';
}

// Create attendance table row
function createAttendanceRow(record) {
  const row = document.createElement('tr');

  const methodInfo = getMethodInfo(record.method);
  const statusInfo = getStatusInfo(record.status);

  row.innerHTML = `
    <td><strong>${record.student_id}</strong></td>
    <td>${record.student_name}</td>
    <td><small class="text-muted">${new Date(record.recorded_at).toLocaleString()}</small></td>
    <td>
      <span class="badge ${statusInfo.className}">
        <i class="fas ${statusInfo.icon} me-1" aria-hidden="true"></i>
        ${statusInfo.text}
      </span>
    </td>
    <td>
      <i class="${methodInfo.icon} me-1" aria-hidden="true"></i>
      <small>${methodInfo.text}</small>
    </td>
    <td>
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAttendance(${record.id})" title="Remove this record">
        <i class="fas fa-trash" aria-hidden="true"></i>
      </button>
    </td>
  `;

  return row;
}

// Get method display info
function getMethodInfo(method) {
  const methodMap = {
    'face_recognition': { icon: 'fas fa-camera text-primary', text: 'Face Recognition' },
    'fingerprint': { icon: 'fas fa-fingerprint text-info', text: 'Fingerprint' },
    'manual': { icon: 'fas fa-pen text-secondary', text: 'Manual' }
  };

  return methodMap[method] || methodMap.manual;
}

// Get status display info
function getStatusInfo(status) {
  const statusMap = {
    'present': { className: 'bg-success', icon: 'fa-check', text: 'Present' },
    'absent': { className: 'bg-danger', icon: 'fa-times', text: 'Absent' }
  };

  return statusMap[status] || { className: 'bg-secondary', icon: 'fa-question', text: status };
}

// Handle export attendance
async function handleExportAttendance() {
  if (!state.currentSessionId) {
    showNotification('No active session to export', 'warning');
    return;
  }

  try {
    showLoading('Exporting attendance data...');

    const response = await utils.fetchWithTimeout(`api/attendance-session-api.php?action=export_attendance&session_id=${state.currentSessionId}&format=csv`);
    const result = await response.json();

    hideLoading();

    if (result.status === 'success') {
      downloadFile(result.data.content, result.data.filename);
      showNotification('Attendance data exported successfully!', 'success');
    } else {
      showNotification('Failed to export attendance data: ' + (result.message || 'Unknown error'), 'error');
    }
  } catch (error) {
    hideLoading();
    console.error('Error exporting attendance:', error);
    showNotification('Failed to export attendance data: ' + (error.message || 'Unknown error'), 'error');
  }
}

// Download file utility
function downloadFile(content, filename) {
  const link = document.createElement('a');
  link.href = 'data:text/csv;base64,' + content;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Remove attendance record
async function removeAttendance(recordId) {
  if (!confirm('Are you sure you want to remove this attendance record?')) {
    return;
  }

  try {
    showLoading('Removing attendance record...');

    const formData = new FormData();
    formData.append('action', 'remove_attendance');
    formData.append('record_id', recordId);
    formData.append('csrf_token', state.csrfToken);

    const response = await utils.fetchWithTimeout('api/attendance-session-api.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();
    hideLoading();

    if (result.status === 'success') {
      showNotification('Attendance record removed successfully', 'success');
      loadAttendanceRecords();
      loadSessionStats();
    } else {
      showNotification('Failed to remove attendance record: ' + (result.message || 'Unknown error'), 'error');
    }
  } catch (error) {
    hideLoading();
    console.error('Error removing attendance:', error);
    showNotification('Failed to remove attendance record: ' + (error.message || 'Unknown error'), 'error');
  }
}

// Show notification
function showNotification(message, type = 'info') {
  // Create and show notification
  const alertClass = type === 'success' ? 'alert-success' :
                    type === 'error' ? 'alert-danger' :
                    type === 'warning' ? 'alert-warning' : 'alert-info';

  const icon = type === 'success' ? 'fas fa-check-circle' :
               type === 'error' ? 'fas fa-exclamation-triangle' :
               type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

  const alert = document.createElement('div');
  alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
  alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;';
  alert.innerHTML = `
    <div class="d-flex align-items-start">
      <i class="${icon} me-2 mt-1"></i>
      <div class="flex-grow-1">
        <div class="fw-bold">${type.toUpperCase()}</div>
        <div>${message}</div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  `;

  document.body.appendChild(alert);

  // Add animation class
  setTimeout(() => alert.classList.add('show'), 10);

  // Auto remove after 6 seconds
  setTimeout(() => {
    if (alert.parentNode) {
      alert.classList.remove('show');
      setTimeout(() => alert.remove(), 300);
    }
  }, 6000);
}

// Show loading overlay
function showLoading(message = 'Loading...') {
  const loading = document.createElement('div');
  loading.id = 'loading-overlay';
  loading.className = 'position-fixed w-100 h-100 d-flex align-items-center justify-content-center';
  loading.style.cssText = 'top: 0; left: 0; background: rgba(0,0,0,0.5); z-index: 9999;';
  loading.innerHTML = `
    <div class="bg-white p-4 rounded shadow">
      <div class="d-flex align-items-center">
        <div class="spinner-border text-primary me-3" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <div>${message}</div>
      </div>
    </div>
  `;
  document.body.appendChild(loading);
}

// Hide loading overlay
function hideLoading() {
  const loading = document.getElementById('loading-overlay');
  if (loading) {
    loading.remove();
  }
}

// Start attendance monitoring
function startAttendanceMonitoring() {
  // Clear any existing interval
  if (state.monitoringInterval) {
    clearInterval(state.monitoringInterval);
  }

  // Load attendance records every configured interval
  state.monitoringInterval = setInterval(() => {
    if (state.currentSessionId) {
      loadAttendanceRecords();
      loadSessionStats();
    }
  }, CONFIG.MONITORING_INTERVAL);
}

// Stop attendance monitoring
function stopAttendanceMonitoring() {
  if (state.monitoringInterval) {
    clearInterval(state.monitoringInterval);
    state.monitoringInterval = null;
  }
}

// Handle existing session dialog
function handleExistingSession(existingSession, originalFormData) {
  // Create modal dialog
  const modalHtml = `
    <div class="modal fade" id="existingSessionModal" tabindex="-1" aria-labelledby="existingSessionLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="existingSessionLabel">
              <i class="fas fa-exclamation-triangle text-warning me-2"></i>
              Active Session Found
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-3">An active attendance session already exists for this course:</p>
            <div class="alert alert-info">
              <strong>Session Details:</strong><br>
              Started: ${new Date(existingSession.start_time).toLocaleString()}<br>
              Date: ${existingSession.session_date}
            </div>
            <p class="mb-0">What would you like to do?</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-1"></i>Cancel
            </button>
            <button type="button" class="btn btn-primary" onclick="resumeExistingSession(${existingSession.id})">
              <i class="fas fa-play me-1"></i>Resume Session
            </button>
            <button type="button" class="btn btn-danger" onclick="forceStartNewSession()">
              <i class="fas fa-plus me-1"></i>Start New Session
            </button>
          </div>
        </div>
      </div>
    </div>
  `;

  // Add modal to page
  document.body.insertAdjacentHTML('beforeend', modalHtml);

  // Store form data for later use
  window.pendingSessionFormData = originalFormData;

  // Show modal
  const modal = new bootstrap.Modal(document.getElementById('existingSessionModal'));
  modal.show();

  // Clean up modal when hidden
  document.getElementById('existingSessionModal').addEventListener('hidden.bs.modal', function() {
    this.remove();
  });
}

// Resume existing session
async function resumeExistingSession(sessionId) {
  // Close modal
  bootstrap.Modal.getInstance(document.getElementById('existingSessionModal')).hide();

  try {
    showLoading('Resuming session...');

    // Get session details
    const response = await fetch('api/attendance-session-api.php?action=get_user_active_session');
    const result = await response.json();

    console.log('Resume session API response:', result);

    hideLoading();

    if (result.status === 'success' && result.data) {
      currentSessionId = result.data.id;
      showNotification('Resumed existing session', 'success');
      showActiveSession(result.data);
      startAttendanceMonitoring();
    } else {
      console.error('Resume session failed:', result);
      showNotification('Failed to resume session: ' + (result.message || 'Unknown error'), 'error');
    }
  } catch (error) {
    hideLoading();
    console.error('Error resuming session:', error);
    showNotification('Failed to resume session: ' + error.message, 'error');
  }
}

// Force start new session (end existing and start new)
async function forceStartNewSession() {
  // Close modal
  bootstrap.Modal.getInstance(document.getElementById('existingSessionModal')).hide();

  if (!window.pendingSessionFormData) {
    showNotification('Session data not available', 'error');
    return;
  }

  try {
    showLoading('Starting new session...');

    // Add force flag to form data
    const formData = new FormData();
    for (let [key, value] of window.pendingSessionFormData.entries()) {
      formData.append(key, value);
    }
    formData.append('force_new', '1');

    const response = await fetch('api/attendance-session-api.php?action=start_session', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    hideLoading();

    if (result.status === 'success') {
      currentSessionId = result.session_id;
      showNotification('New session started successfully!', 'success');
      showActiveSession(result.data);
      startAttendanceMonitoring();
    } else {
      showNotification('Error starting new session: ' + result.message, 'error');
    }
  } catch (error) {
    hideLoading();
    console.error('Error starting new session:', error);
    showNotification('Failed to start new session', 'error');
  }
}

// Handle scan fingerprint
async function handleScanFingerprint() {
  const fingerprintBtn = document.getElementById('scanFingerprintBtn');
  const fingerprintStatus = document.getElementById('fingerprint-status');
  const fingerprintMessage = document.getElementById('fingerprint-message');

  if (!currentSessionId) {
    showNotification('No active session', 'error');
    return;
  }

  try {
    // Show loading state
    fingerprintBtn.disabled = true;
    fingerprintBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scanning...';
    fingerprintStatus.style.display = 'block';
    fingerprintMessage.textContent = 'Connecting to ESP32...';

    // Validate ESP32 IP
    if (!esp32IP || esp32IP === '192.168.1.100') {
      throw new Error('ESP32 IP address not configured');
    }

    // Call ESP32 identify endpoint with timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

    const response = await fetch(`http://${esp32IP}/identify`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      },
      signal: controller.signal
    });

    clearTimeout(timeoutId);

    if (!response.ok) {
      throw new Error(`ESP32 responded with status: ${response.status}`);
    }

    const esp32Result = await response.json();
    console.log('ESP32 response:', esp32Result);

    if (esp32Result.success && esp32Result.fingerprint_id) {
      // Validate fingerprint ID
      if (!esp32Result.fingerprint_id || isNaN(esp32Result.fingerprint_id)) {
        throw new Error('Invalid fingerprint ID received');
      }

      fingerprintMessage.textContent = 'Fingerprint detected! Processing...';

      // Call our API to mark attendance
      const apiResponse = await fetch(`api/mark_attendance.php?method=finger&fingerprint_id=${esp32Result.fingerprint_id}&session_id=${currentSessionId}`, {
        method: 'GET',
        headers: {
          'Content-Type': 'application/json'
        }
      });

      if (!apiResponse.ok) {
        throw new Error(`API responded with status: ${apiResponse.status}`);
      }

      const apiResult = await apiResponse.json();
      console.log('Mark attendance API response:', apiResult);

      if (apiResult.status === 'success') {
        if (!apiResult.student || !apiResult.student.name) {
          throw new Error('Invalid student data received');
        }

        showNotification(`✅ Attendance marked for ${apiResult.student.name}!`, 'success');
        loadAttendanceRecords();
        loadSessionStats();
      } else {
        showNotification(`❌ ${apiResult.message || 'Unknown error'}`, 'error');
      }
    } else {
      fingerprintMessage.textContent = 'No fingerprint match found';
      showNotification('No fingerprint match found', 'warning');
    }

  } catch (error) {
    console.error('Fingerprint scan error:', error);

    if (error.name === 'AbortError') {
      fingerprintMessage.textContent = 'Connection timeout';
      showNotification('Connection to ESP32 device timed out', 'error');
    } else {
      fingerprintMessage.textContent = 'Connection failed';
      showNotification('Failed to connect to ESP32 device: ' + (error.message || 'Unknown error'), 'error');
    }
  } finally {
    // Reset button state
    fingerprintBtn.disabled = false;
    fingerprintBtn.innerHTML = '<i class="fas fa-hand-paper me-2"></i>Scan Fingerprint';

    // Hide status after 3 seconds
    setTimeout(() => {
      fingerprintStatus.style.display = 'none';
    }, 3000);
  }
}

// Test ESP32 connection
async function testESP32Connection() {
  const testBtn = document.getElementById('testESP32Btn');
  const esp32Status = document.getElementById('esp32-status');
  const esp32IPDisplay = document.getElementById('esp32-ip');

  try {
    testBtn.disabled = true;
    testBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Testing...';
    esp32Status.textContent = 'Testing connection...';

    // Validate ESP32 IP
    if (!esp32IP || esp32IP === '192.168.1.100') {
      throw new Error('ESP32 IP address not configured');
    }

    // Test connection with timeout
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout

    const response = await fetch(`http://${esp32IP}/status`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      },
      signal: controller.signal
    });

    clearTimeout(timeoutId);

    if (!response.ok) {
      throw new Error(`ESP32 responded with status: ${response.status}`);
    }

    const result = await response.json();

    if (result.status === 'ok') {
      esp32Status.textContent = 'Connected';
      esp32Status.className = 'text-success';
      showNotification('ESP32 connection successful!', 'success');
    } else {
      esp32Status.textContent = 'Connected (limited functionality)';
      esp32Status.className = 'text-warning';
      showNotification('ESP32 connected but may have limited functionality', 'warning');
    }

    esp32IPDisplay.textContent = esp32IP;

  } catch (error) {
    console.error('ESP32 test error:', error);

    if (error.name === 'AbortError') {
      esp32Status.textContent = 'Connection timeout';
      esp32Status.className = 'text-danger';
      esp32IPDisplay.textContent = 'Timeout';
      showNotification('Connection to ESP32 device timed out', 'error');
    } else {
      esp32Status.textContent = 'Connection failed';
      esp32Status.className = 'text-danger';
      esp32IPDisplay.textContent = 'Not detected';
      showNotification('Failed to connect to ESP32 device: ' + (error.message || 'Unknown error'), 'error');
    }
  } finally {
    testBtn.disabled = false;
    testBtn.innerHTML = '<i class="fas fa-wifi me-2"></i>Test ESP32 Connection';
  }
}