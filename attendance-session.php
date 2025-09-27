<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'admin']);

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance Session | <?php echo ucfirst($userRole); ?> | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #0066cc, #003366);
      margin: 0;
    }

    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      width: 250px;
      height: 100vh;
      background-color: #003366;
      color: white;
      padding-top: 20px;
      overflow-y: auto;
      z-index: 1000;
      box-shadow: 2px 0 5px rgba(0,0,0,0.1);
    }

    .sidebar a {
      display: block;
      padding: 12px 20px;
      color: #fff;
      text-decoration: none;
      font-weight: 500;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background-color: #0066cc;
    }

    .topbar {
      margin-left: 250px;
      background-color: #fff;
      padding: 10px 30px;
      border-bottom: 1px solid #ddd;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
    }

    .main-content {
      margin-left: 250px;
      padding: 30px;
      min-height: calc(100vh - 112px);
    }

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
      border-top: 1px solid #ddd;
    }

    #webcam-preview {
      width: 100%;
      max-width: 640px;
      border: 2px solid #0066cc;
      border-radius: 8px;
      background-color: #000;
      margin-bottom: 20px;
      aspect-ratio: 4/3;
      object-fit: cover;
    }

    @media (max-width: 768px) {

      .sidebar,
      .topbar,
      .main-content,
      .footer {
        margin-left: 0 !important;
        width: 100% !important;
      }

      .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        display: block;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }

      .sidebar a {
        padding: 10px 15px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
      }
    }

    .progress {
      background-color: #e9ecef;
    }

    .progress-bar {
      font-weight: bold;
    }

    /* Session Statistics Cards */
    .stat-card {
      border-radius: 10px;
      transition: transform 0.2s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
    }

    .stat-card .icon {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 15px;
    }

    /* Attendance Table Enhancements */
    .attendance-table .table th {
      background-color: #f8f9fa;
      border-top: none;
      font-weight: 600;
    }

    .attendance-table .badge {
      font-size: 0.75rem;
      padding: 0.375rem 0.75rem;
    }

    /* Loading States */
    .spinner-border {
      width: 2rem;
      height: 2rem;
    }

    /* Notification Styles */
    .alert {
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      border: none;
      border-left: 4px solid;
    }

    .alert-success {
      border-left-color: #28a745;
    }

    .alert-info {
      border-left-color: #17a2b8;
    }

    .alert-warning {
      border-left-color: #ffc107;
    }

    .alert-danger {
      border-left-color: #dc3545;
    }

    /* Enhanced form styling */
    .form-select:disabled {
      background-color: #f8f9fa;
      opacity: 0.6;
      cursor: not-allowed;
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Loading animation for dropdowns */
    .form-select.loading {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23666' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1rem;
    }

    /* Webcam Container */
    #webcam-container {
      position: relative;
      display: inline-block;
    }

    #webcam-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      display: none;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.2rem;
    }

    /* Method Breakdown Cards */
    #method-breakdown .card {
      transition: all 0.3s ease;
    }

    #method-breakdown .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    /* Course Search Styling */
    #course-search {
      border-radius: 6px;
      border: 1px solid #dee2e6;
      transition: all 0.2s ease;
    }

    #course-search:focus {
      border-color: #0066cc;
      box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    }

    #course-loading {
      background-color: #f8f9fa;
      border-radius: 6px;
      margin-top: 5px;
    }

    /* Course Select Enhancements */
    #course {
      border-radius: 6px;
      border: 1px solid #dee2e6;
    }

    #course:focus {
      border-color: #0066cc;
      box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    }

    #course option {
      padding: 8px 12px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    /* Responsive Improvements */
    @media (max-width: 992px) {
      .stat-card {
        margin-bottom: 1rem;
      }

      #method-breakdown .col-md-4 {
        margin-bottom: 1rem;
      }

      #course-search {
        font-size: 16px; /* Prevent zoom on iOS */
      }
    }

    /* Animation Classes */
    .fade-in {
      animation: fadeIn 0.5s ease-in;
    }

    .slide-up {
      animation: slideUp 0.3s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes slideUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Status Indicators */
    .session-active {
      position: relative;
    }

    .session-active::after {
      content: '';
      position: absolute;
      top: 5px;
      right: 5px;
      width: 10px;
      height: 10px;
      background: #28a745;
      border-radius: 50%;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.7);
      }
      70% {
        box-shadow: 0 0 0 10px rgba(40, 167, 69, 0);
      }
      100% {
        box-shadow: 0 0 0 0 rgba(40, 167, 69, 0);
      }
    }
  </style>
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
    <h5 class="m-0 fw-bold">Attendance Session</h5>
    <span>RP Attendance System</span>
  </header>

  <!-- Main Content -->
  <main class="main-content" role="main" tabindex="-1">

    <!-- Information Panel for No Department Access -->
    <div id="noDepartmentInfo" class="alert alert-warning alert-dismissible fade show mb-4" style="border-left: 4px solid #ffc107;">
      <div class="d-flex align-items-start">
        <i class="fas fa-info-circle fa-2x me-3 mt-1"></i>
        <div class="flex-grow-1">
          <h5 class="alert-heading mb-2">Department Access Required</h5>
          <p class="mb-2">You don't have any departments assigned to your account. To use the attendance session feature, you need to:</p>
          <ul class="mb-3" style="margin-bottom: 0;">
            <li>Contact your system administrator</li>
            <li>Request to be assigned to a department</li>
            <li>Once assigned, refresh this page to access the attendance features</li>
          </ul>
          <button type="button" class="btn btn-sm btn-outline-warning me-2" onclick="location.reload()">
            <i class="fas fa-sync-alt me-1"></i>Refresh Page
          </button>
          <button type="button" class="btn btn-sm btn-warning" onclick="showNotification('Please contact your administrator to get department access.', 'warning')">
            <i class="fas fa-envelope me-1"></i>Contact Admin
          </button>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    </div>

    <!-- Session Filters -->
    <form id="sessionForm" class="row g-3 mb-4">
      <div class="col-md-3">
        <label for="department" class="form-label fw-semibold">Department</label>
        <select id="department" class="form-select" required>
          <option value="" disabled selected>Select Department</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="option" class="form-label fw-semibold">Option</label>
        <select id="option" class="form-select" required disabled>
          <option value="" disabled selected>Select Option</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="classLevel" class="form-label fw-semibold">Class (Year)</label>
        <select id="classLevel" class="form-select" required disabled>
          <option value="" disabled selected>Select Class</option>
          <option value="Year 1">Year 1</option>
          <option value="Year 2">Year 2</option>
          <option value="Year 3">Year 3</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="course" class="form-label fw-semibold">
          Course
          <small class="text-muted ms-2">
            <i class="fas fa-keyboard" title="Press F2 to search courses"></i>
          </small>
        </label>
        <div class="position-relative">
          <input type="text" id="course-search" class="form-control mb-2" placeholder="Search courses..." style="display: none;">
          <select id="course" class="form-select" required disabled>
            <option value="" disabled selected>Select Course</option>
          </select>
          <div id="course-loading" class="text-center py-1" style="display: none;">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">Loading courses...</span>
            </div>
            <small class="text-muted ms-2">Loading courses...</small>
          </div>
        </div>
        <small class="text-muted">
          <i class="fas fa-info-circle me-1"></i>
          <span id="course-info">Select your assigned department and option to load available courses</span>
          <button type="button" id="test-api" class="btn btn-sm btn-outline-info ms-2" style="display: none;" title="Test API connection">
            <i class="fas fa-bug"></i> Test API
          </button>
        </small>
      </div>

      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" id="start-session" class="btn btn-primary" disabled>
          <i class="fas fa-play me-2"></i> Start Session
        </button>
        <button type="button" id="end-session" class="btn btn-danger" disabled>
          <i class="fas fa-stop me-2"></i> End Session
        </button>
        <a href="#" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#manualMarkModal">
          <i class="fas fa-pen me-2"></i> Manual Mark
        </a>
        <button type="button" class="btn btn-info">
          <i class="fas fa-fingerprint me-2"></i> Use Fingerprint
        </button>
      </div>
    </form>

    <!-- Webcam Preview -->
    <div id="webcam-container" class="position-relative">
      <video id="webcam-preview" autoplay muted playsinline></video>
      <div id="webcam-overlay">
        <div class="text-center">
          <div class="spinner-border text-light mb-2" role="status">
            <span class="visually-hidden">Processing...</span>
          </div>
          <div id="face-recognition-status">Detecting faces...</div>
        </div>
      </div>
    </div>

    <!-- Attendance Table -->
    <section class="attendance-table" aria-live="polite">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Live Attendance Records</h5>
        <div class="d-flex gap-2">
          <button type="button" id="refresh-attendance" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-sync-alt me-1"></i>Refresh
          </button>
          <button type="button" id="export-attendance" class="btn btn-outline-success btn-sm">
            <i class="fas fa-download me-1"></i>Export
          </button>
        </div>
      </div>
      <div id="attendance-loading" class="text-center py-4" style="display: none;">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2 text-muted">Loading attendance records...</p>
      </div>
      <div id="attendance-table-container" style="display: none;">
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Student ID</th>
              <th>Name</th>
              <th>Time</th>
              <th>Status</th>
              <th>Method</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="attendance-list">
            <!-- Dynamic attendance records will be loaded here -->
          </tbody>
        </table>
      </div>
      <div id="no-attendance" class="text-center py-4" style="display: none;">
        <i class="fas fa-users fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">No Attendance Records</h5>
        <p class="text-muted">Start a session to begin recording attendance.</p>
      </div>
    </section>

    <!-- Session Statistics -->
    <section class="mt-5" id="session-stats-section" style="display: none;">
      <h5 class="fw-bold mb-3">Session Statistics</h5>
      <div class="row g-4">
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="fas fa-users fa-2x text-primary mb-2"></i>
              <h4 class="mb-1" id="total-students">0</h4>
              <small class="text-muted">Total Students</small>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
              <h4 class="mb-1" id="present-count">0</h4>
              <small class="text-muted">Present</small>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
              <h4 class="mb-1" id="absent-count">0</h4>
              <small class="text-muted">Absent</small>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="fas fa-percentage fa-2x text-info mb-2"></i>
              <h4 class="mb-1" id="attendance-rate">0%</h4>
              <small class="text-muted">Attendance Rate</small>
            </div>
          </div>
        </div>
      </div>

      <!-- Method Breakdown -->
      <div class="mt-4">
        <h6 class="fw-bold mb-3">Attendance Methods Used</h6>
        <div id="method-breakdown" class="row g-3">
          <!-- Method statistics will be loaded here -->
        </div>
      </div>
    </section>

  </main>

  <!-- Manual Mark Modal -->
  <div class="modal fade" id="manualMarkModal" tabindex="-1" aria-labelledby="manualMarkLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="manualMarkLabel">Manual Attendance Marking</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="studentName" class="form-label">Student Name</label>
            <select id="studentName" class="form-select" required>
              <option selected disabled>Select Student</option>
              <!-- Students will be loaded dynamically -->
            </select>
          </div>
          <div class="mb-3">
            <label for="attendanceDate" class="form-label">Date</label>
            <input type="date" id="attendanceDate" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label d-block">Status</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="attendanceStatus" id="statusPresent" value="Present" required>
              <label class="form-check-label" for="statusPresent">Present</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="attendanceStatus" id="statusAbsent" value="Absent" required>
              <label class="form-check-label" for="statusAbsent">Absent</label>
            </div>
          </div>
          <div class="mb-3">
            <label for="attendanceMethod" class="form-label">Method</label>
            <input type="text" id="attendanceMethod" class="form-control" value="Manual" readonly>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Attendance</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Global variables
    let currentSessionId = null;
    let attendanceCheckInterval = null;
    let faceRecognitionActive = false;
    let csrfToken = '<?php echo generate_csrf_token(); ?>';

    // DOM elements
    const departmentSelect = document.getElementById('department');
    const optionSelect = document.getElementById('option');
    const classSelect = document.getElementById('classLevel');
    const courseSelect = document.getElementById('course');
    const courseSearch = document.getElementById('course-search');
    const courseLoading = document.getElementById('course-loading');
    const startBtn = document.getElementById('start-session');
    const endBtn = document.getElementById('end-session');
    const webcamPreview = document.getElementById('webcam-preview');
    const sessionForm = document.getElementById('sessionForm');
    const attendanceList = document.getElementById('attendance-list');
    const attendanceLoading = document.getElementById('attendance-loading');
    const attendanceTableContainer = document.getElementById('attendance-table-container');
    const noAttendance = document.getElementById('no-attendance');
    const sessionStatsSection = document.getElementById('session-stats-section');
    const refreshAttendanceBtn = document.getElementById('refresh-attendance');
    const exportAttendanceBtn = document.getElementById('export-attendance');
    const testApiBtn = document.getElementById('test-api');

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
      classSelect.addEventListener('change', handleClassChange);
      courseSelect.addEventListener('change', validateForm);
      courseSearch.addEventListener('input', handleCourseSearch);
      courseSearch.addEventListener('focus', () => courseSearch.style.display = 'block');
      courseSearch.addEventListener('blur', () => {
        // Hide search after 200ms delay to allow for selection
        setTimeout(() => {
          if (!courseSearch.matches(':focus')) {
            courseSearch.style.display = 'none';
          }
        }, 200);
      });
      startBtn.addEventListener('click', handleStartSession);
      endBtn.addEventListener('click', handleEndSession);
      refreshAttendanceBtn.addEventListener('click', loadAttendanceRecords);
      exportAttendanceBtn.addEventListener('click', exportAttendanceData);
      testApiBtn.addEventListener('click', testApiConnection);

      // Manual attendance modal
      document.getElementById('manualMarkModal').addEventListener('show.bs.modal', loadStudentsForManualMarking);
      document.querySelector('#manualMarkModal form').addEventListener('submit', handleManualAttendance);
    }

    // Load departments from API
    async function loadDepartments() {
      try {
        // Show loading state
        departmentSelect.innerHTML = '<option value="" disabled selected>Loading departments...</option>';
        departmentSelect.disabled = true;

        const response = await fetch('api/attendance-session-api.php?action=get_departments', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json'
          }
        });

        const result = await response.json();

        if (result.status === 'success') {
          departmentSelect.innerHTML = '<option value="" disabled selected>Select Department</option>';

          if (result.data.length === 0) {
            departmentSelect.innerHTML += '<option value="" disabled>No departments available</option>';
            updateCourseInfo('⚠️ No departments are assigned to your account. Please contact your administrator to get access to attendance sessions.');
            showNotification('No departments are assigned to your account. Please contact your administrator.', 'warning');
          } else {
            result.data.forEach(dept => {
              const option = document.createElement('option');
              option.value = dept.id;
              option.textContent = dept.name;
              departmentSelect.appendChild(option);
            });
            updateCourseInfo(`✅ Loaded ${result.data.length} department(s). Select your department to continue.`);
            showNotification(`✅ Loaded ${result.data.length} department(s) for your account`, 'success');
          }
        } else {
          departmentSelect.innerHTML = '<option value="" disabled selected>Error loading departments</option>';
          showNotification('Error loading departments: ' + result.message, 'error');
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
      classSelect.disabled = false;
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
            'Content-Type': 'application/json'
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
       const classLevel = classSelect.value;

       courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
       courseSelect.disabled = true;
       courseSearch.style.display = 'none';
       courseSearch.value = '';
       startBtn.disabled = true;

       if (departmentId && optionId) {
         await loadCourses(departmentId, optionId, classLevel);
         // Show course search after courses are loaded
         if (allCourses.length > 5) { // Only show search if more than 5 courses
           courseSearch.style.display = 'block';
         }
       } else if (departmentId) {
         updateCourseInfo('Select option to load courses');
       }
    }

    // Load courses for selected department, option, and class level
    async function loadCourses(departmentId, optionId, classLevel = null) {
        try {
          // Show loading state
          courseLoading.style.display = 'block';
          courseSelect.innerHTML = '<option value="" disabled selected>Loading courses...</option>';
          courseSelect.disabled = true;

          // Build URL with parameters
          let url = `api/attendance-session-api.php?action=get_courses&department_id=${departmentId}&option_id=${optionId}`;
          if (classLevel) {
            url += `&year_level=${encodeURIComponent(classLevel)}`;
          }

          console.log('Loading courses with URL:', url);

          const response = await fetch(url, {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json'
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

    // Handle class change
    async function handleClassChange() {
       const departmentId = departmentSelect.value;
       const optionId = optionSelect.value;
       const classLevel = classSelect.value;

       courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
       courseSelect.disabled = true;
       courseSearch.style.display = 'none';
       courseSearch.value = '';
       startBtn.disabled = true;

       if (departmentId && optionId) {
         await loadCourses(departmentId, optionId, classLevel);
         // Show course search after courses are loaded
         if (allCourses.length > 5) { // Only show search if more than 5 courses
           courseSearch.style.display = 'block';
         }
       } else if (departmentId && optionId) {
         updateCourseInfo('Select option to load courses');
       } else if (departmentId) {
         updateCourseInfo('Select option to load courses');
       } else {
         updateCourseInfo('Select department and option to load courses');
       }

       validateForm();
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
       const isValid = departmentSelect.value && optionSelect.value && classSelect.value && courseSelect.value;
       startBtn.disabled = !isValid;

       // Debug logging
       console.log('Form validation:', {
         department: departmentSelect.value,
         option: optionSelect.value,
         classLevel: classSelect.value,
         course: courseSelect.value,
         isValid: isValid
       });
    }

    // Check for existing session
    async function checkExistingSession() {
      const departmentId = departmentSelect.value;
      const optionId = optionSelect.value;
      const courseId = courseSelect.value;

      if (departmentId && optionId && courseId) {
        try {
          const response = await fetch(
            `api/attendance-session-api.php?action=get_session_status&department_id=${departmentId}&option_id=${optionId}&course_id=${courseId}`,
            {
              method: 'GET',
              headers: {
                'Content-Type': 'application/json'
              }
            }
          );

          const result = await response.json();

          if (result.status === 'success' && result.data && result.is_active) {
            currentSessionId = result.data.id;
            showActiveSession(result.data);
          }
        } catch (error) {
          console.error('Error checking existing session:', error);
        }
      }
    }

    // Handle start session
    async function handleStartSession(e) {
       e.preventDefault();

       const formData = new FormData(sessionForm);
       formData.append('csrf_token', csrfToken);

       // Debug logging
       console.log('Starting session with data:', {
         department_id: formData.get('department_id'),
         option_id: formData.get('option_id'),
         classLevel: formData.get('classLevel'),
         course_id: formData.get('course_id'),
         csrf_token: formData.get('csrf_token')
       });

       try {
         showLoading('Starting session...');

         const response = await fetch('api/attendance-session-api.php?action=start_session', {
           method: 'POST',
           body: formData
         });

         const result = await response.json();

         // Debug logging
         console.log('Session start response:', result);

         hideLoading();

         if (result.status === 'success') {
           currentSessionId = result.session_id;
           showNotification('Session started successfully!', 'success');
           showActiveSession(result.data);
           startAttendanceMonitoring();
         } else {
           showNotification('Error starting session: ' + result.message, 'error');
         }
       } catch (error) {
         hideLoading();
         console.error('Error starting session:', error);
         showNotification('Failed to start session', 'error');
       }
    }

    // Handle end session
    async function handleEndSession() {
      if (!currentSessionId) {
        showNotification('No active session to end', 'warning');
        return;
      }

      try {
        showLoading('Ending session...');

        const formData = new FormData();
        formData.append('session_id', currentSessionId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api/attendance-session-api.php?action=end_session', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        hideLoading();

        if (result.status === 'success') {
          showNotification('Session ended successfully!', 'success');
          hideActiveSession();
          stopAttendanceMonitoring();
          loadSessionStats();
        } else {
          showNotification('Error ending session: ' + result.message, 'error');
        }
      } catch (error) {
        hideLoading();
        console.error('Error ending session:', error);
        showNotification('Failed to end session', 'error');
      }
    }

    // Show active session UI
    function showActiveSession(sessionData) {
      startBtn.disabled = true;
      endBtn.disabled = false;
      sessionStatsSection.style.display = 'block';

      // Start webcam
      startWebcam();

      // Load initial attendance data
      loadAttendanceRecords();
      loadSessionStats();
    }

    // Hide active session UI
    function hideActiveSession() {
      startBtn.disabled = false;
      endBtn.disabled = true;
      sessionStatsSection.style.display = 'none';
      currentSessionId = null;

      // Stop webcam
      stopWebcam();

      // Clear attendance data
      attendanceList.innerHTML = '';
      noAttendance.style.display = 'block';
      attendanceTableContainer.style.display = 'none';
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
        webcamPreview.srcObject = stream;
        faceRecognitionActive = true;
      } catch (error) {
        console.error('Error accessing webcam:', error);
        showNotification('Could not access webcam. Face recognition will be disabled.', 'warning');
      }
    }

    // Stop webcam
    function stopWebcam() {
      if (webcamPreview.srcObject) {
        webcamPreview.srcObject.getTracks().forEach(track => track.stop());
        webcamPreview.srcObject = null;
      }
      faceRecognitionActive = false;
    }

    // Start attendance monitoring
    function startAttendanceMonitoring() {
      // Load attendance records every 5 seconds
      attendanceCheckInterval = setInterval(loadAttendanceRecords, 5000);
    }

    // Stop attendance monitoring
    function stopAttendanceMonitoring() {
      if (attendanceCheckInterval) {
        clearInterval(attendanceCheckInterval);
        attendanceCheckInterval = null;
      }
    }

    // Load attendance records
    async function loadAttendanceRecords() {
      if (!currentSessionId) return;

      try {
        attendanceLoading.style.display = 'block';
        attendanceTableContainer.style.display = 'none';
        noAttendance.style.display = 'none';

        const response = await fetch(`api/attendance-session-api.php?action=get_attendance_history&session_id=${currentSessionId}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json'
          }
        });

        const result = await response.json();

        attendanceLoading.style.display = 'none';

        if (result.status === 'success') {
          if (result.data && result.data.length > 0) {
            displayAttendanceRecords(result.data);
            attendanceTableContainer.style.display = 'block';
          } else {
            noAttendance.style.display = 'block';
          }
        } else {
          showNotification('Error loading attendance records: ' + result.message, 'error');
          noAttendance.style.display = 'block';
        }
      } catch (error) {
        attendanceLoading.style.display = 'none';
        console.error('Error loading attendance records:', error);
        showNotification('Failed to load attendance records', 'error');
        noAttendance.style.display = 'block';
      }
    }

    // Display attendance records
    function displayAttendanceRecords(records) {
      attendanceList.innerHTML = '';

      records.forEach(record => {
        const row = document.createElement('tr');

        const statusBadge = record.status === 'present'
          ? '<span class="badge bg-success">Present</span>'
          : '<span class="badge bg-danger">Absent</span>';

        const methodText = record.method === 'face_recognition' ? 'Face Recognition' :
                          record.method === 'fingerprint' ? 'Fingerprint' : (record.method || 'Manual');

        row.innerHTML = `
          <td>${record.student_id}</td>
          <td>
            <div class="d-flex align-items-center">
              ${record.profile_image ? `<img src="${record.profile_image}" class="rounded-circle me-2" width="32" height="32">` : '<i class="fas fa-user-circle me-2"></i>'}
              ${record.full_name}
            </div>
          </td>
          <td>${new Date(record.recorded_at).toLocaleTimeString()}</td>
          <td>${statusBadge}</td>
          <td>${methodText}</td>
          <td>
            <button class="btn btn-sm btn-outline-primary" onclick="editAttendance(${record.id})">
              <i class="fas fa-edit"></i>
            </button>
          </td>
        `;

        attendanceList.appendChild(row);
      });
    }

    // Load session statistics
    async function loadSessionStats() {
      if (!currentSessionId) return;

      try {
        const response = await fetch(`api/attendance-session-api.php?action=get_session_stats&session_id=${currentSessionId}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json'
          }
        });

        const result = await response.json();

        if (result.status === 'success') {
          const stats = result.data;
          document.getElementById('total-students').textContent = stats.total_students;
          document.getElementById('present-count').textContent = stats.present_count;
          document.getElementById('absent-count').textContent = stats.absent_count;
          document.getElementById('attendance-rate').textContent = stats.attendance_rate + '%';

          displayMethodBreakdown(stats.method_breakdown);
        }
      } catch (error) {
        console.error('Error loading session stats:', error);
      }
    }

    // Display method breakdown
    function displayMethodBreakdown(methods) {
      const container = document.getElementById('method-breakdown');
      container.innerHTML = '';

      if (!methods || methods.length === 0) {
        container.innerHTML = '<p class="text-muted">No attendance methods recorded yet.</p>';
        return;
      }

      methods.forEach(method => {
        const percentage = method.count > 0 ? ((method.present_count / method.count) * 100).toFixed(1) : 0;
        const icon = method.method === 'face_recognition' ? 'fas fa-camera' :
                    method.method === 'fingerprint' ? 'fas fa-fingerprint' : 'fas fa-pen';

        const col = document.createElement('div');
        col.className = 'col-md-4';
        col.innerHTML = `
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
              <i class="${icon} fa-2x text-info mb-2"></i>
              <h6 class="mb-1">${method.method.replace('_', ' ').toUpperCase()}</h6>
              <p class="mb-0">${method.count} records (${percentage}% present)</p>
            </div>
          </div>
        `;
        container.appendChild(col);
      });
    }

    // Load students for manual marking
    async function loadStudentsForManualMarking() {
      const departmentId = departmentSelect.value;
      const optionId = optionSelect.value;

      if (!departmentId || !optionId) {
        showNotification('Please select department and option first', 'warning');
        return;
      }

      try {
        const response = await fetch(
          `api/attendance-session-api.php?action=get_students&department_id=${departmentId}&option_id=${optionId}&year_level=${classSelect.value}`,
          {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json'
            }
          }
        );

        const result = await response.json();

        if (result.status === 'success') {
          const studentSelect = document.getElementById('studentName');
          studentSelect.innerHTML = '<option selected disabled>Select Student</option>';

          result.data.forEach(student => {
            const option = document.createElement('option');
            option.value = student.id;
            option.textContent = `${student.full_name} (${student.student_id})`;
            studentSelect.appendChild(option);
          });
        }
      } catch (error) {
        console.error('Error loading students:', error);
        showNotification('Failed to load students', 'error');
      }
    }

    // Handle manual attendance
    async function handleManualAttendance(e) {
      e.preventDefault();

      const studentId = document.getElementById('studentName').value;
      const date = document.getElementById('attendanceDate').value;
      const status = document.querySelector('input[name="attendanceStatus"]:checked').value;

      if (!currentSessionId || !studentId || !date || !status) {
        showNotification('Please fill in all required fields', 'warning');
        return;
      }

      try {
        const formData = new FormData();
        formData.append('session_id', currentSessionId);
        formData.append('student_id', studentId);
        formData.append('status', status);
        formData.append('date', date);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api/attendance-session-api.php?action=manual_attendance', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          showNotification('Manual attendance recorded successfully!', 'success');
          bootstrap.Modal.getInstance(document.getElementById('manualMarkModal')).hide();
          loadAttendanceRecords();
          loadSessionStats();
        } else {
          showNotification('Error recording attendance: ' + result.message, 'error');
        }
      } catch (error) {
        console.error('Error recording manual attendance:', error);
        showNotification('Failed to record attendance', 'error');
      }
    }

    // Test API connection
    async function testApiConnection() {
      try {
        showLoading('Testing API connection...');

        const response = await fetch('api/attendance-session-api.php?action=test_courses', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json'
          }
        });

        const result = await response.json();

        hideLoading();

        if (result.status === 'success') {
          showNotification(`API test successful! Found ${result.count} courses.`, 'success');
          console.log('API Test Results:', result.data);

          // If we got courses, try to populate the dropdown
          if (result.data && result.data.length > 0) {
            courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
            result.data.forEach(course => {
              const option = document.createElement('option');
              option.value = course.id;
              option.textContent = `${course.name} (${course.course_code}) - ${course.lecturer_name}`;
              courseSelect.appendChild(option);
            });
            courseSelect.disabled = false;
            updateCourseInfo(`API test loaded ${result.count} courses`);
          }
        } else {
          showNotification('API test failed: ' + result.message, 'error');
          console.error('API Test Error:', result);
        }
      } catch (error) {
        hideLoading();
        console.error('Error testing API:', error);
        showNotification('API test error: ' + error.message, 'error');
      }
    }

    // Export attendance data
    function exportAttendanceData() {
      if (!currentSessionId) {
        showNotification('No active session to export', 'warning');
        return;
      }

      // Create CSV content
      const csvContent = generateAttendanceCSV();
      const blob = new Blob([csvContent], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);

      const a = document.createElement('a');
      a.href = url;
      a.download = `attendance_session_${currentSessionId}_${new Date().toISOString().split('T')[0]}.csv`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);

      showNotification('Attendance data exported successfully!', 'success');
    }

    // Generate CSV content
    function generateAttendanceCSV() {
      const headers = ['Student ID', 'Name', 'Time', 'Status', 'Method'];
      const rows = Array.from(attendanceList.querySelectorAll('tr')).map(row => {
        const cells = row.querySelectorAll('td');
        return [
          cells[0].textContent,
          cells[1].textContent.replace(/\s+/g, ' ').trim(),
          cells[2].textContent,
          cells[3].textContent.replace(/<[^>]*>/g, '').trim(),
          cells[4].textContent
        ];
      });

      return [headers, ...rows].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
    }

    // Edit attendance record
    function editAttendance(recordId) {
      showNotification('Edit functionality will be implemented', 'info');
    }

    // Utility functions
    function showLoading(message = 'Loading...') {
      // You can implement a loading modal here
      console.log(message);
    }

    function hideLoading() {
      // Hide loading modal
    }

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

    // Generate CSRF token
    function generateCSRFToken() {
      return Array.from(crypto.getRandomValues(new Uint8Array(32)))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('');
    }

    // Face recognition simulation (placeholder)
    async function processFaceRecognition(imageData) {
      // This would integrate with actual face recognition API
      console.log('Processing face recognition...');

      // Simulate face recognition delay
      await new Promise(resolve => setTimeout(resolve, 1000));

      // For demo purposes, randomly mark students as present
      if (Math.random() > 0.3) {
        return { recognized: true, student_id: Math.floor(Math.random() * 1000) + 1 };
      }

      return { recognized: false };
    }

    // Fingerprint authentication (placeholder)
    async function processFingerprint() {
      // This would integrate with fingerprint scanner
      console.log('Processing fingerprint...');

      // Simulate fingerprint processing
      await new Promise(resolve => setTimeout(resolve, 2000));

      return { authenticated: true, student_id: Math.floor(Math.random() * 1000) + 1 };
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl + S to start session
      if (e.ctrlKey && e.key === 's' && !startBtn.disabled) {
        e.preventDefault();
        handleStartSession(e);
      }

      // Ctrl + E to end session
      if (e.ctrlKey && e.key === 'e' && !endBtn.disabled) {
        e.preventDefault();
        handleEndSession();
      }

      // Ctrl + R to refresh attendance
      if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        loadAttendanceRecords();
      }

      // F1 for manual marking
      if (e.key === 'F1') {
        e.preventDefault();
        const modal = new bootstrap.Modal(document.getElementById('manualMarkModal'));
        modal.show();
      }

      // F2 to focus course search
      if (e.key === 'F2' && courseSelect.disabled === false) {
        e.preventDefault();
        courseSearch.style.display = 'block';
        courseSearch.focus();
      }

      // Escape to hide course search
      if (e.key === 'Escape' && courseSearch.style.display === 'block') {
        courseSearch.style.display = 'none';
        courseSearch.value = '';
        handleCourseSearch(); // Reset to show all courses
      }
    });

    // Webcam error handling
    webcamPreview.addEventListener('error', function() {
      console.error('Webcam error occurred');
      showNotification('Webcam error occurred. Face recognition disabled.', 'warning');
      faceRecognitionActive = false;
    });

    // Page visibility change handling
    document.addEventListener('visibilitychange', function() {
      if (document.hidden && currentSessionId) {
        // Pause monitoring when tab is not visible
        console.log('Tab hidden, pausing attendance monitoring');
      } else if (!document.hidden && currentSessionId) {
        // Resume monitoring when tab becomes visible
        console.log('Tab visible, resuming attendance monitoring');
        loadAttendanceRecords();
      }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
      if (currentSessionId) {
        stopAttendanceMonitoring();
        stopWebcam();
      }
    });

    // Face Recognition Implementation
    let faceRecognitionInterval = null;

    function startFaceRecognition() {
      if (!faceRecognitionActive || !webcamPreview.srcObject) return;

      faceRecognitionInterval = setInterval(async () => {
        try {
          const video = webcamPreview;
          const canvas = document.createElement('canvas');
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;

          const ctx = canvas.getContext('2d');
          ctx.drawImage(video, 0, 0);

          const imageData = canvas.toDataURL('image/jpeg', 0.8);

          // Show processing overlay
          showFaceRecognitionOverlay('Processing...');

          const result = await processFaceRecognition(imageData);

          hideFaceRecognitionOverlay();

          if (result.recognized && currentSessionId) {
            // Record attendance
            await recordAttendance(result.student_id, 'face_recognition', 'present');
            showNotification(`Student ${result.student_id} marked present via face recognition`, 'success');
          }

        } catch (error) {
          console.error('Face recognition error:', error);
          hideFaceRecognitionOverlay();
        }
      }, 3000); // Check every 3 seconds
    }

    function stopFaceRecognition() {
      if (faceRecognitionInterval) {
        clearInterval(faceRecognitionInterval);
        faceRecognitionInterval = null;
      }
      hideFaceRecognitionOverlay();
    }

    function showFaceRecognitionOverlay(message) {
      const overlay = document.getElementById('webcam-overlay');
      const status = document.getElementById('face-recognition-status');
      status.textContent = message;
      overlay.style.display = 'flex';
    }

    function hideFaceRecognitionOverlay() {
      const overlay = document.getElementById('webcam-overlay');
      overlay.style.display = 'none';
    }

    // Enhanced webcam start with face recognition
    const originalStartWebcam = startWebcam;
    startWebcam = async function() {
      await originalStartWebcam();
      if (faceRecognitionActive) {
        startFaceRecognition();
      }
    };

    const originalStopWebcam = stopWebcam;
    stopWebcam = function() {
      originalStopWebcam();
      stopFaceRecognition();
    };

    // Fingerprint Authentication
    async function handleFingerprintAuth() {
      try {
        showLoading('Authenticating fingerprint...');

        const result = await processFingerprint();

        hideLoading();

        if (result.authenticated && currentSessionId) {
          await recordAttendance(result.student_id, 'fingerprint', 'present');
          showNotification(`Student ${result.student_id} authenticated via fingerprint`, 'success');
        } else {
          showNotification('Fingerprint authentication failed', 'error');
        }
      } catch (error) {
        hideLoading();
        console.error('Fingerprint authentication error:', error);
        showNotification('Fingerprint authentication error', 'error');
      }
    }

    // Record attendance helper function
    async function recordAttendance(studentId, method, status) {
      if (!currentSessionId) return;

      try {
        const formData = new FormData();
        formData.append('session_id', currentSessionId);
        formData.append('student_id', studentId);
        formData.append('method', method);
        formData.append('status', status);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api/attendance-session-api.php?action=record_attendance', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();

        if (result.status === 'success') {
          loadAttendanceRecords();
          loadSessionStats();
          return true;
        } else {
          console.error('Failed to record attendance:', result.message);
          return false;
        }
      } catch (error) {
        console.error('Error recording attendance:', error);
        return false;
      }
    }

    // Add fingerprint button functionality
    document.addEventListener('DOMContentLoaded', function() {
      const fingerprintBtn = document.querySelector('.btn-info');
      if (fingerprintBtn) {
        fingerprintBtn.addEventListener('click', handleFingerprintAuth);
      }
    });

    // Enhanced session start with webcam
    const originalShowActiveSession = showActiveSession;
    showActiveSession = function(sessionData) {
      originalShowActiveSession(sessionData);

      // Add session active indicator
      const webcamContainer = document.getElementById('webcam-container');
      if (webcamContainer) {
        webcamContainer.classList.add('session-active');
      }
    };

    const originalHideActiveSession = hideActiveSession;
    hideActiveSession = function() {
      originalHideActiveSession();

      // Remove session active indicator
      const webcamContainer = document.getElementById('webcam-container');
      if (webcamContainer) {
        webcamContainer.classList.remove('session-active');
      }
    };
  </script>

</body>

</html>
