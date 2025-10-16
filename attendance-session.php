
<?php
require_once "session_check.php";
require_once "config.php";

// Check authentication and role
checkAuthentication();
if (!in_array($_SESSION['role'], ['admin', 'lecturer', 'hod'])) {
    $_SESSION['error'] = "Access denied. Insufficient privileges.";
    header("Location: index.php");
    exit();
}

$userRole = $_SESSION['role'];

// Initialize controller
$controller = new AttendanceSessionController($pdo, $_SESSION['user_id'], $userRole);

// Handle AJAX requests
if (isset($_GET['ajax']) || isset($_POST['action'])) {
    $controller->handleAjaxRequest();
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_session'])) {
    $result = $controller->startSession($_POST);
    if ($result['status'] === 'success') {
        $_SESSION['success'] = $result['message'];
        header("Location: attendance-session.php");
        exit();
    } elseif ($result['status'] === 'existing_session') {
        // Handle existing session - this would be handled by JavaScript
        $_SESSION['warning'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['end_session'])) {
    $result = $controller->endSession($_POST['session_id']);
    if ($result['status'] === 'success') {
        $_SESSION['success'] = $result['message'];
    } else {
        $_SESSION['error'] = $result['message'];
    }
    header("Location: attendance-session.php");
    exit();
}

// Page metadata
$pageTitle = "Attendance Session | " . ucfirst($userRole) . " | RP Attendance System";
$pageDescription = "Manage attendance sessions with face recognition and fingerprint scanning";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>" />
  <title><?php echo htmlspecialchars($pageTitle); ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" integrity="sha512-IoR2Bl8gNJzFJE6fGjOWnEnU7z0vH4B8B4Vv34JzF++1PJ0v+vaHkP2F5x3P5rJvA1n5l5fz8q5rWJz9J6J6z8" crossorigin="anonymous" referrerpolicy="no-referrer" />

  <!-- External CSS -->
  <link href="css/attendance-session.css" rel="stylesheet" />
</head>

<body>

  <!-- Sidebar -->
  <nav class="sidebar" aria-label="Sidebar Navigation">
    <div class="text-center mb-4">
      <h4><?php echo $userRole === 'admin' ? '👨‍💼 Admin' : '👨‍🏫 Lecturer'; ?></h4>
      <hr style="border-color: #ffffff66;" />
    </div>
    <?php if ($userRole === 'admin'): ?>
      <a href="admin-dashboard.php">Dashboard</a>
      <a href="admin-reports.php">Reports & Analytics</a>
      <a href="attendance-session.php" class="active"><i class="fas fa-video me-2"></i> Attendance Session</a>
      <a href="attendance-records.php">Attendance Records</a>
      <a href="leave-requests.php">Leave Management</a>
      <a href="manage-departments.php">Manage Departments</a>
      <a href="logout.php">Logout</a>
    <?php else: ?>
      <a href="lecturer-dashboard.php">Dashboard</a>
      <a href="lecturer-my-courses.php">My Courses</a>
      <a href="attendance-session.php" class="active"><i class="fas fa-video me-2"></i> Attendance Session</a>
      <a href="attendance-reports.php">Attendance Reports</a>
      <a href="leave-requests.php">Leave Requests</a>
      <a href="logout.php">Logout</a>
    <?php endif; ?>
  </nav>

  <!-- Topbar -->
  <header class="topbar" role="banner">
    <div>
      <h5 class="m-0 fw-bold">Attendance Session</h5>
      <div id="session-status-indicator" class="d-none">
        <span class="badge bg-danger">
          <i class="fas fa-video me-1"></i>
          <span id="session-status-text">Session Active</span>
        </span>
        <div class="ms-2">
          <small class="text-muted">
            <i class="fas fa-clock me-1"></i>
            <span id="session-timer">00:00:00</span>
          </small>
        </div>
      </div>
    </div>
    <span>RP Attendance System</span>
  </header>

  <!-- Main Content -->
  <main class="main-content" role="main" tabindex="-1">

    <!-- Information Panel for No Department Access -->
    <div id="noDepartmentInfo" class="alert alert-warning alert-dismissible fade show mb-4" style="border-left: 4px solid #ffc107;" role="alert">
      <div class="d-flex align-items-start">
        <i class="fas fa-info-circle fa-2x me-3 mt-1" aria-hidden="true"></i>
        <div class="flex-grow-1">
          <h5 class="alert-heading mb-2">Department Access Required</h5>
          <p class="mb-2">You don't have any departments assigned to your account. To use the attendance session feature, you need to:</p>
          <ul class="mb-3" style="margin-bottom: 0;">
            <li>Contact your system administrator</li>
            <li>Request to be assigned to a department</li>
            <li>Once assigned, refresh this page to access the attendance features</li>
          </ul>
          <button type="button" class="btn btn-sm btn-outline-warning me-2" onclick="location.reload()">
            <i class="fas fa-sync-alt me-1" aria-hidden="true"></i>Refresh Page
          </button>
          <button type="button" class="btn btn-sm btn-warning" onclick="showNotification('Please contact your administrator to get department access.', 'warning')">
            <i class="fas fa-envelope me-1" aria-hidden="true"></i>Contact Admin
          </button>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>

    <!-- Active Session Section -->
    <div id="activeSessionSection" class="d-none" role="region" aria-labelledby="active-session-heading">
      <div class="alert alert-success" role="alert">
        <h5 id="active-session-heading"><i class="fas fa-play-circle me-2" aria-hidden="true"></i>Active Attendance Session</h5>
        <p id="sessionInfo" class="mb-3"></p>

        <!-- Mark Attendance Section -->
        <div class="mark-attendance-section">
          <div class="row">
            <div class="col-md-6">
              <h6 class="mb-3"><i class="fas fa-camera me-2" aria-hidden="true"></i>Face Recognition Attendance</h6>

              <!-- Webcam Container -->
              <div id="webcam-container" class="text-center" role="region" aria-label="Webcam preview area">
                <video id="webcam-preview" autoplay muted playsinline style="display: none;" aria-label="Webcam feed"></video>
                <div id="webcam-placeholder" role="status" aria-live="polite">
                  <i class="fas fa-video fa-3x mb-3" aria-hidden="true"></i>
                  <p>Webcam not active</p>
                  <small class="text-muted">Click "Mark Attendance" to start</small>
                </div>
                <div id="webcam-overlay" role="status" aria-live="polite">
                  <div id="webcam-status">Processing...</div>
                </div>
              </div>

              <!-- Control Buttons -->
              <div class="mt-3">
                <button type="button" id="markAttendanceBtn" class="btn btn-primary btn-lg me-2" disabled aria-describedby="mark-attendance-help">
                  <i class="fas fa-camera me-2" aria-hidden="true"></i>Mark Attendance
                </button>
                <button type="button" id="endSessionBtn" class="btn btn-danger" aria-describedby="end-session-help">
                  <i class="fas fa-stop me-2" aria-hidden="true"></i>End Session
                </button>
                <div id="mark-attendance-help" class="visually-hidden">Capture face for attendance recognition</div>
                <div id="end-session-help" class="visually-hidden">Stop the current attendance session</div>
              </div>
            </div>

            <div class="col-md-6">
              <h6 class="mb-3"><i class="fas fa-fingerprint me-2" aria-hidden="true"></i>Fingerprint Attendance</h6>

              <!-- Fingerprint Section -->
              <div class="text-center">
                <div class="mb-3">
                  <i class="fas fa-fingerprint fa-4x text-info mb-3" aria-hidden="true"></i>
                  <p class="text-muted">ESP32 Fingerprint Scanner</p>
                </div>
                <button type="button" id="scanFingerprintBtn" class="btn btn-info btn-lg" aria-describedby="fingerprint-help">
                  <i class="fas fa-hand-paper me-2" aria-hidden="true"></i>Scan Fingerprint
                </button>
                <div id="fingerprint-status" class="mt-3" style="display: none;" role="status" aria-live="polite">
                  <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin me-2" aria-hidden="true"></i>
                    <span id="fingerprint-message">Connecting to ESP32...</span>
                  </div>
                </div>
                <div id="fingerprint-help" class="visually-hidden">Scan fingerprint for attendance recognition</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Session Setup Section -->
    <div id="sessionSetupSection" role="region" aria-labelledby="session-setup-heading">
      <h4 id="session-setup-heading" class="visually-hidden">Session Setup</h4>
      <!-- Session Filters -->
      <form id="sessionForm" class="row g-3 mb-4" method="POST" action="" novalidate>
        <div class="col-md-3">
          <label for="department" class="form-label fw-semibold">Department</label>
          <select id="department" name="department_id" class="form-select" required aria-describedby="department-help">
            <option value="" disabled selected>Select Department</option>
          </select>
          <div id="department-help" class="form-text">Choose your assigned department</div>
        </div>
        <div class="col-md-3">
          <label for="option" class="form-label fw-semibold">Option</label>
          <select id="option" name="option_id" class="form-select" required disabled aria-describedby="option-help">
            <option value="" disabled selected>Select Option</option>
          </select>
          <div id="option-help" class="form-text">Select the academic option</div>
        </div>
        <div class="col-md-3">
          <label for="course" class="form-label fw-semibold">
            Course
            <small class="text-muted ms-2">
              <i class="fas fa-keyboard" title="Press F2 to search courses" aria-hidden="true"></i>
            </small>
          </label>
          <div class="position-relative">
            <input type="text" id="course-search" class="form-control mb-2" placeholder="Search courses..." style="display: none;" aria-label="Search courses">
            <select id="course" name="course_id" class="form-select" required disabled aria-describedby="course-help">
              <option value="" disabled selected>Select Course</option>
            </select>
            <div id="course-loading" class="text-center py-1" style="display: none;" role="status" aria-live="polite">
              <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading courses...</span>
              </div>
              <small class="text-muted ms-2">Loading courses...</small>
            </div>
          </div>
          <small class="text-muted">
            <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
            <span id="course-info">Select your assigned department and option to load available courses</span>
            <button type="button" id="test-api" class="btn btn-sm btn-outline-info ms-2" style="display: none;" title="Test API connection" aria-label="Test API connection">
              <i class="fas fa-bug" aria-hidden="true"></i> Test API
            </button>
          </small>
          <div id="course-help" class="form-text">Choose the course for attendance</div>
        </div>

        <div class="col-md-3">
          <label for="biometric_method" class="form-label fw-semibold">Biometric Method</label>
          <select id="biometric_method" name="biometric_method" class="form-select" required aria-describedby="biometric-help">
            <option value="" disabled selected>Select Method</option>
            <option value="face">Face Recognition</option>
            <option value="finger">Fingerprint</option>
          </select>
          <div id="biometric-help" class="form-text">Choose attendance verification method</div>
        </div>

        <div class="col-12 d-flex flex-wrap gap-2">
          <button type="submit" id="start-session" name="start_session" class="btn btn-primary" disabled aria-describedby="start-session-help">
            <i class="fas fa-play me-2" aria-hidden="true"></i> Start Session
          </button>
          <button type="button" id="end-session" class="btn btn-danger" disabled aria-describedby="end-session-help">
            <i class="fas fa-stop me-2" aria-hidden="true"></i> End Session
          </button>
          <div id="start-session-help" class="visually-hidden">Begin a new attendance session</div>
          <div id="end-session-help" class="visually-hidden">Terminate the current active session</div>
        </div>
      </form>

    <!-- Webcam Preview for Face Recognition -->
    <div id="webcam-section" class="d-none" role="region" aria-labelledby="webcam-heading">
      <div class="card">
        <div class="card-header">
          <h6 id="webcam-heading" class="mb-0"><i class="fas fa-camera me-2" aria-hidden="true"></i>Face Recognition Setup</h6>
        </div>
        <div class="card-body text-center">
          <div id="webcam-container" role="region" aria-label="Webcam setup area">
            <video id="webcam-preview" autoplay muted playsinline aria-label="Webcam feed"></video>
            <div id="webcam-overlay" role="status" aria-live="polite">
              <div class="text-center">
                <div class="spinner-border text-light mb-2" role="status">
                  <span class="visually-hidden">Processing...</span>
                </div>
                <div id="face-recognition-status">Detecting faces...</div>
              </div>
            </div>
          </div>
          <div class="mt-3">
            <button type="button" id="startWebcamBtn" class="btn btn-primary" aria-describedby="start-webcam-help">
              <i class="fas fa-video me-2" aria-hidden="true"></i>Start Webcam
            </button>
            <div id="start-webcam-help" class="visually-hidden">Activate webcam for face recognition</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Fingerprint Setup Section -->
    <div id="fingerprint-section" class="d-none" role="region" aria-labelledby="fingerprint-heading">
      <div class="card">
        <div class="card-header">
          <h6 id="fingerprint-heading" class="mb-0"><i class="fas fa-fingerprint me-2" aria-hidden="true"></i>Fingerprint Scanner Setup</h6>
        </div>
        <div class="card-body text-center">
          <div class="mb-3">
            <i class="fas fa-microchip fa-4x text-info mb-3" aria-hidden="true"></i>
            <h5>ESP32 Fingerprint Scanner</h5>
            <p class="text-muted">Make sure your ESP32 device is connected and running</p>
          </div>
          <div class="alert alert-info" role="status" aria-live="polite">
            <strong>ESP32 IP Address:</strong> <span id="esp32-ip">Not detected</span><br>
            <strong>Status:</strong> <span id="esp32-status">Checking...</span>
          </div>
          <button type="button" id="testESP32Btn" class="btn btn-info" aria-describedby="test-esp32-help">
            <i class="fas fa-wifi me-2" aria-hidden="true"></i>Test ESP32 Connection
          </button>
          <div id="test-esp32-help" class="visually-hidden">Test connection to ESP32 fingerprint device</div>
        </div>
      </div>
    </div>

    <!-- Attendance Table -->
    <section class="attendance-table" aria-live="polite" role="region" aria-labelledby="attendance-table-heading">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 id="attendance-table-heading" class="mb-0">Live Attendance Records</h5>
        <div class="d-flex gap-2">
          <button type="button" id="refresh-attendance" class="btn btn-outline-primary btn-sm" aria-describedby="refresh-help">
            <i class="fas fa-sync-alt me-1" aria-hidden="true"></i>Refresh
          </button>
          <button type="button" id="export-attendance" class="btn btn-outline-success btn-sm" aria-describedby="export-help">
            <i class="fas fa-download me-1" aria-hidden="true"></i>Export
          </button>
          <div id="refresh-help" class="visually-hidden">Reload attendance records</div>
          <div id="export-help" class="visually-hidden">Download attendance data as CSV</div>
        </div>
      </div>
      <div id="attendance-loading" class="text-center py-4" style="display: none;" role="status" aria-live="polite">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading attendance records...</p>
      </div>
      <div id="attendance-table-container" style="display: none;">
        <table class="table table-bordered table-hover align-middle" role="table" aria-label="Attendance records">
          <thead class="table-light">
            <tr>
              <th scope="col">Student ID</th>
              <th scope="col">Name</th>
              <th scope="col">Time</th>
              <th scope="col">Status</th>
              <th scope="col">Method</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody id="attendance-list" role="rowgroup">
            <!-- Dynamic attendance records will be loaded here -->
          </tbody>
        </table>
      </div>
      <div id="no-attendance" class="text-center py-4" style="display: none;" role="status" aria-live="polite">
        <i class="fas fa-users fa-3x text-muted mb-3" aria-hidden="true"></i>
        <h5 class="text-muted">No Attendance Records</h5>
        <p class="text-muted">Start a session to begin recording attendance.</p>
      </div>
    </section>

    <!-- Session Statistics -->
    <section class="mt-5" id="session-stats-section" style="display: none;" role="region" aria-labelledby="stats-heading">
      <h5 id="stats-heading" class="fw-bold mb-3">Session Statistics</h5>
      <div class="row g-4">
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="fas fa-users fa-2x text-primary mb-2" aria-hidden="true"></i>
              <h4 class="mb-1" id="total-students" aria-live="polite">0</h4>
              <small class="text-muted">Total Students</small>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="fas fa-check-circle fa-2x text-success mb-2" aria-hidden="true"></i>
              <h4 class="mb-1" id="present-count" aria-live="polite">0</h4>
              <small class="text-muted">Present</small>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="fas fa-times-circle fa-2x text-danger mb-2" aria-hidden="true"></i>
              <h4 class="mb-1" id="absent-count" aria-live="polite">0</h4>
              <small class="text-muted">Absent</small>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="fas fa-percentage fa-2x text-info mb-2" aria-hidden="true"></i>
              <h4 class="mb-1" id="attendance-rate" aria-live="polite">0%</h4>
              <small class="text-muted">Attendance Rate</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Method Breakdown -->
      <div class="mt-4" role="region" aria-labelledby="method-breakdown-heading">
        <h6 id="method-breakdown-heading" class="fw-bold mb-3">Attendance Methods Used</h6>
        <div id="method-breakdown" class="row g-3" role="list" aria-live="polite">
          <!-- Method statistics will be loaded here -->
        </div>
      </div>
    </section>

  </main>

  <!-- Footer -->
  <footer class="footer" role="contentinfo">
    &copy; <?php echo date('Y'); ?> Rwanda Polytechnic | Lecturer Panel
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

  <!-- External JavaScript -->
  <script src="js/attendance-session.js"></script>
    // Global variables
    let currentSessionId = null;
    let webcamStream = null;
    let csrfToken = '<?php echo bin2hex(random_bytes(32)); ?>';
    let esp32IP = '192.168.1.100'; // Default ESP32 IP - should be configurable

    // DOM elements
    const departmentSelect = document.getElementById('department');
    const optionSelect = document.getElementById('option');
    const courseSelect = document.getElementById('course');
    const biometricMethodSelect = document.getElementById('biometric_method');
    const courseSearch = document.getElementById('course-search');
    const courseLoading = document.getElementById('course-loading');
    const startBtn = document.getElementById('start-session');
    const endBtn = document.getElementById('end-session');
    const sessionForm = document.getElementById('sessionForm');

    // Course data storage
    let allCourses = [];
    let filteredCourses = [];

    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
      loadDepartments();
      setupEventListeners();
      checkExistingSession();
      updateCourseInfo('Select your assigned department and option to load available courses');
    });

    // Setup event listeners
    function setupEventListeners() {
      departmentSelect.addEventListener('change', handleDepartmentChange);
      optionSelect.addEventListener('change', handleOptionChange);
      courseSelect.addEventListener('change', validateForm);
      biometricMethodSelect.addEventListener('change', handleBiometricMethodChange);
      courseSearch.addEventListener('input', handleCourseSearch);
      courseSearch.addEventListener('focus', () => courseSearch.style.display = 'block');
      courseSearch.addEventListener('blur', () => {
        setTimeout(() => {
          if (!courseSearch.matches(':focus')) {
            courseSearch.style.display = 'none';
          }
        }, 200);
      });
      startBtn.addEventListener('click', handleStartSession);
      endBtn.addEventListener('click', handleEndSession);
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

            // Hide the "Department Access
    // Handle scan fingerprint
    async function handleScanFingerprint() {
      const fingerprintBtn = document.getElementById('scanFingerprintBtn');
      const fingerprintStatus = document.getElementById('fingerprint-status');
      const fingerprintMessage = document.getElementById('fingerprint-message');

      try {
        // Show loading state
        fingerprintBtn.disabled = true;
        fingerprintBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Scanning...';
        fingerprintStatus.style.display = 'block';
        fingerprintMessage.textContent = 'Connecting to ESP32...';

        // Call ESP32 identify endpoint
        const response = await fetch(`http://${esp32IP}/identify`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json'
          }
        });

        const esp32Result = await response.json();
        console.log('ESP32 response:', esp32Result);

        if (esp32Result.success && esp32Result.fingerprint_id) {
          fingerprintMessage.textContent = 'Fingerprint detected! Processing...';

          // Call our API to mark attendance
          const apiResponse = await fetch(`api/mark_attendance.php?method=finger&fingerprint_id=${esp32Result.fingerprint_id}&session_id=${currentSessionId}`, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json'
            }
          });

          const apiResult = await apiResponse.json();
          console.log('Mark attendance API response:', apiResult);

          if (apiResult.status === 'success') {
            showNotification(`✅ Attendance marked for ${apiResult.student.name}!`, 'success');
            loadAttendanceRecords();
            loadSessionStats();
          } else {
            showNotification(`❌ ${apiResult.message}`, 'error');
          }
        } else {
          fingerprintMessage.textContent = 'No fingerprint match found';
          showNotification('No fingerprint match found', 'warning');
        }

      } catch (error) {
        console.error('Fingerprint scan error:', error);
        fingerprintMessage.textContent = 'Connection failed';
        showNotification('Failed to connect to ESP32 device', 'error');
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

        const response = await fetch(`http://${esp32IP}/status`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json'
          }
        });

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
        esp32Status.textContent = 'Connection failed';
        esp32Status.className = 'text-danger';
        esp32IPDisplay.textContent = 'Not detected';
        showNotification('Failed to connect to ESP32 device', 'error');
      } finally {
        testBtn.disabled = false;
        testBtn.innerHTML = '<i class="fas fa-wifi me-2"></i>Test ESP32 Connection';
      }
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
      const departmentId = departmentSelect.value;

      // Reset dependent selects
      optionSelect.innerHTML = '<option value="" disabled selected>Select Option</option>';
      optionSelect.disabled = true;
      courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
      courseSelect.disabled = true;
      courseSearch.style.display = 'none';
      courseSearch.value = '';
      allCourses = [];
      filteredCourses = [];
      startBtn.disabled = true;

      if (departmentId) {
        await loadOptions(departmentId);
        updateCourseInfo('Select option to load available courses');
      } else {
        updateCourseInfo('Select your assigned department to continue');
      }
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
       const departmentId = departmentSelect.value;
       const optionId = optionSelect.value;

       courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
       courseSelect.disabled = true;
       courseSearch.style.display = 'none';
       courseSearch.value = '';
       startBtn.disabled = true;

       if (departmentId && optionId) {
         await loadCourses(departmentId, optionId);
         // Show course search after courses are loaded (check inside loadCourses function)
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
             testApiBtn.style.display = 'none';

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
           testApiBtn.style.display = 'inline-block';
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
      const searchTerm = courseSearch.value.toLowerCase().trim();

      if (!searchTerm) {
        filteredCourses = [...allCourses];
        updateCourseInfo(`Showing all ${allCourses.length} course(s). Press F2 to search.`);
      } else {
        filteredCourses = allCourses.filter(course =>
          course.name.toLowerCase().includes(searchTerm) ||
          course.course_code.toLowerCase().includes(searchTerm) ||
          course.description?.toLowerCase().includes(searchTerm) ||
          course.lecturer_name?.toLowerCase().includes(searchTerm)
        );
        updateCourseInfo(`Found ${filteredCourses.length} course(s) matching "${searchTerm}"`);
      }

      // Update course dropdown
      courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';

      if (filteredCourses.length === 0) {
        courseSelect.innerHTML += '<option value="" disabled>No courses match your search</option>';
      } else {
        filteredCourses.forEach(course => {
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
      }

      // Show search results count
      if (searchTerm) {
        showNotification(`Found ${filteredCourses.length} course(s) matching "${searchTerm}"`, 'info');
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
        const isValid = departmentSelect.value && optionSelect.value && courseSelect.value && biometricMethodSelect.value;
        startBtn.disabled = !isValid;

        // Debug logging
        console.log('Form validation:', {
            department: departmentSelect.value,
            option: optionSelect.value,
            course: courseSelect.value,
            biometric_method: biometricMethodSelect.value,
            isValid: isValid
        });

        // Update button text to show validation status
        if (isValid) {
            startBtn.innerHTML = '<i class="fas fa-play me-2"></i> Start Session';
            startBtn.classList.remove('btn-secondary');
            startBtn.classList.add('btn-primary');
        } else {
            startBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> Fill All Fields';
            startBtn.classList.remove('btn-primary');
            startBtn.classList.add('btn-secondary');
        }
    }

    // Check for existing session
    async function checkExistingSession() {
      try {
        // First, try to get any active session for the current user
        const response = await fetch('api/attendance-session-api.php?action=get_user_active_session', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        const result = await response.json();

        if (result.status === 'success' && result.data) {
          currentSessionId = result.data.id;
          showActiveSession(result.data);

          // Also populate the form with session details
          if (result.data.department_id) departmentSelect.value = result.data.department_id;
          if (result.data.option_id) optionSelect.value = result.data.option_id;
          if (result.data.course_id) courseSelect.value = result.data.course_id;
          if (result.data.biometric_method) biometricMethodSelect.value = result.data.biometric_method;

          // Trigger dependent dropdowns
          if (result.data.department_id) {
            await loadOptions(result.data.department_id);
            optionSelect.value = result.data.option_id;
            await loadCourses(result.data.department_id, result.data.option_id);
            courseSelect.value = result.data.course_id;
          }

          showNotification('Resumed active attendance session', 'info');
          return;
        }

        // Fallback: check based on current form selection
        const departmentId = departmentSelect.value;
        const optionId = optionSelect.value;
        const courseId = courseSelect.value;

        if (departmentId && optionId && courseId) {
          const response = await fetch(
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
            currentSessionId = result.data.id;
            showActiveSession(result.data);
          }
        }
      } catch (error) {
        console.error('Error checking existing session:', error);
      }
    }

    // Handle start session
    async function handleStartSession(e) {
        e.preventDefault();

        console.log('🚀 Starting session...');

        const formData = new FormData(sessionForm);
        formData.append('csrf_token', csrfToken);

        // Debug logging
        console.log('Starting session with data:', {
            department_id: formData.get('department_id'),
            option_id: formData.get('option_id'),
            course_id: formData.get('course_id'),
            biometric_method: formData.get('biometric_method'),
            csrf_token: formData.get('csrf_token')
        });

        // Validate required fields before submission
        const departmentId = formData.get('department_id');
        const optionId = formData.get('option_id');
        const courseId = formData.get('course_id');
        const biometricMethod = formData.get('biometric_method');

        if (!departmentId || !optionId || !courseId || !biometricMethod) {
            console.error('❌ Missing required fields');
            showNotification('Please fill in all required fields (Department, Option, Course, Biometric Method)', 'error');
            return;
        }

       try {
         showLoading('Starting session...');

         console.log('📡 Sending request to API...');

         const response = await fetch('api/start_session.php', {
           method: 'POST',
           body: formData
         });

         console.log('📡 Response status:', response.status);
         console.log('📡 Response headers:', response.headers);

         const result = await response.json();

         // Debug logging
         console.log('📡 Session start response:', result);

         hideLoading();

         if (result.status === 'success') {
           currentSessionId = result.session_id;
           showNotification('Session started successfully!', 'success');
           showActiveSession(result.data);
           startAttendanceMonitoring();
         } else if (result.status === 'existing_session') {
           // Handle existing session - ask user what to do
           handleExistingSession(result.existing_session, formData);
         } else {
           showNotification('Error starting session: ' + result.message, 'error');
         }
       } catch (error) {
         hideLoading();
         console.error('Error starting session:', error);
         showNotification('Failed to start session', 'error');
       }
    }

    // Handle mark attendance button
    async function handleMarkAttendance() {
      if (!currentSessionId) {
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

        // Send to server for face recognition
        const formData = new FormData();
        formData.append('action', 'process_face_recognition');
        formData.append('image_data', imageData);
        formData.append('session_id', currentSessionId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('attendance-session.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        hideWebcamOverlay();

        if (result.status === 'success') {
          // Show success result
          showAttendanceResult('success', 'Attendance Marked!', `${result.student_name} (${result.student_reg}) - ${result.confidence}% confidence`);
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
        showNotification('Face recognition error: ' + error.message, 'error');
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
      if (!currentSessionId) {
        showNotification('No active session to end', 'warning');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('session_id', currentSessionId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api/end_session.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          showNotification('Session ended successfully!', 'success');
          hideActiveSession();
        } else {
          showNotification('Error ending session: ' + result.message, 'error');
        }
      } catch (error) {
        console.error('Error ending session:', error);
        showNotification('Failed to end session', 'error');
      }
    }

    // Show active session UI
    function showActiveSession(sessionData) {
      document.getElementById('sessionSetupSection').style.display = 'none';
      document.getElementById('activeSessionSection').classList.remove('d-none');

      // Update session info
      document.getElementById('sessionInfo').innerHTML = `
        <strong>Course:</strong> ${sessionData.course_name || 'Unknown'}<br>
        <strong>Department:</strong> ${sessionData.department_name || 'Unknown'}<br>
        <strong>Option:</strong> ${sessionData.option_name || 'Unknown'}<br>
        <strong>Method:</strong> ${sessionData.biometric_method === 'face' ? 'Face Recognition' : 'Fingerprint'}<br>
        <strong>Started:</strong> ${new Date(sessionData.start_time).toLocaleString()}
      `;

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

    // Hide active session UI
    function hideActiveSession() {
      document.getElementById('activeSessionSection').classList.add('d-none');
      document.getElementById('sessionSetupSection').style.display = 'block';

      currentSessionId = null;
      document.getElementById('markAttendanceBtn').disabled = true;

      // Stop webcam
      stopWebcam();
    }

    // Load session statistics
    async function loadSessionStats() {
      if (!currentSessionId) return;

      try {
        const response = await fetch(`api/attendance-session-api.php?action=get_session_stats&session_id=${currentSessionId}`);
        const result = await response.json();

        if (result.status === 'success') {
          const stats = result.data;
          document.getElementById('sessionStats').innerHTML = `
            <div class="alert alert-info">
              <strong>Session Statistics:</strong><br>
              Total Students: ${stats.total_students}<br>
              Present: ${stats.present_count}<br>
              Absent: ${stats.absent_count}<br>
              Attendance Rate: ${stats.attendance_rate}%
            </div>
          `;
        }
      } catch (error) {
        console.error('Error loading session stats:', error);
      }
    }

    // Load attendance records
    async function loadAttendanceRecords() {
      if (!currentSessionId) return;

      try {
        // Show loading state
        document.getElementById('attendance-loading').style.display = 'block';
        document.getElementById('attendance-table-container').style.display = 'none';
        document.getElementById('no-attendance').style.display = 'none';

        const response = await fetch(`api/attendance-session-api.php?action=get_attendance_records&session_id=${currentSessionId}`);
        const result = await response.json();

        // Hide loading state
        document.getElementById('attendance-loading').style.display = 'none';

        if (result.status === 'success' && result.data.length > 0) {
          const tbody = document.getElementById('attendance-list');
          tbody.innerHTML = '';

          result.data.forEach(record => {
            const row = document.createElement('tr');

            const methodIcon = record.method === 'face_recognition' ? 'fas fa-camera text-primary' :
                              record.method === 'fingerprint' ? 'fas fa-fingerprint text-info' :
                              'fas fa-pen text-secondary';
            const methodText = record.method === 'face_recognition' ? 'Face Recognition' :
                              record.method === 'fingerprint' ? 'Fingerprint' : 'Manual';

            row.innerHTML = `
              <td><strong>${record.student_id}</strong></td>
              <td>${record.student_name}</td>
              <td><small class="text-muted">${new Date(record.recorded_at).toLocaleString()}</small></td>
              <td>
                <span class="badge ${record.status === 'present' ? 'bg-success' : 'bg-danger'}">
                  <i class="fas ${record.status === 'present' ? 'fa-check' : 'fa-times'} me-1"></i>
                  ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                </span>
              </td>
              <td>
                <i class="${methodIcon} me-1"></i>
                <small>${methodText}</small>
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAttendance(${record.id})" title="Remove this record">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            `;

            tbody.appendChild(row);
          });

          document.getElementById('attendance-table-container').style.display = 'block';
        } else {
          document.getElementById('no-attendance').style.display = 'block';
        }
      } catch (error) {
        console.error('Error loading attendance records:', error);
        document.getElementById('attendance-loading').style.display = 'none';
        document.getElementById('no-attendance').style.display = 'block';
      }
    }

    // Handle export attendance
    async function handleExportAttendance() {
      if (!currentSessionId) {
        showNotification('No active session to export', 'warning');
        return;
      }

      try {
        showLoading('Exporting attendance data...');

        const response = await fetch(`api/attendance-session-api.php?action=export_attendance&session_id=${currentSessionId}&format=csv`);
        const result = await response.json();

        hideLoading();

        if (result.status === 'success') {
          // Create download link
          const link = document.createElement('a');
          link.href = 'data:text/csv;base64,' + result.data.content;
          link.download = result.data.filename;
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);

          showNotification('Attendance data exported successfully!', 'success');
        } else {
          showNotification('Failed to export attendance data: ' + result.message, 'error');
        }
      } catch (error) {
        hideLoading();
        console.error('Error exporting attendance:', error);
        showNotification('Failed to export attendance data', 'error');
      }
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
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api/attendance-session-api.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        hideLoading();

        if (result.status === 'success') {
          showNotification('Attendance record removed successfully', 'success');
          loadAttendanceRecords();
          loadSessionStats();
        } else {
          showNotification('Failed to remove attendance record: ' + result.message, 'error');
        }
      } catch (error) {
        hideLoading();
        console.error('Error removing attendance:', error);
        showNotification('Failed to remove attendance record', 'error');
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
      // Load attendance records every 30 seconds
      setInterval(() => {
        if (currentSessionId) {
          loadAttendanceRecords();
          loadSessionStats();
        }
      }, 30000);
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
  </script>

</body>

</html>