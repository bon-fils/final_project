<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'hod', 'admin']);

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = $_SESSION['role'] ?? 'admin';

// Handle AJAX requests for face recognition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    try {
        if ($_POST['action'] === 'process_face_recognition') {
            // Get the captured image data
            $imageData = $_POST['image_data'] ?? '';
            if (empty($imageData)) {
                throw new Exception('No image data provided');
            }

            $session_id = $_POST['session_id'] ?? null;
            if (!$session_id) {
                throw new Exception('Session ID is required');
            }

            // Verify session is active
            $stmt = $pdo->prepare("SELECT id FROM attendance_sessions WHERE id = ? AND end_time IS NULL");
            $stmt->execute([$session_id]);
            if (!$stmt->fetch()) {
                throw new Exception('Active session not found');
            }

            // Create temporary file for the captured image
            $tempDir = sys_get_temp_dir();
            $tempFile = tempnam($tempDir, 'face_capture_');
            $imageFile = $tempFile . '.jpg';

            // Clean up temp file if it exists
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            // Decode and save base64 image
            if (strpos($imageData, 'data:image') === 0) {
                $imageData = explode(',', $imageData)[1];
            }
            $imageBinary = base64_decode($imageData);

            if ($imageBinary === false) {
                throw new Exception('Invalid base64 image data');
            }

            if (file_put_contents($imageFile, $imageBinary) === false) {
                throw new Exception('Failed to save temporary image file');
            }

            // Set proper permissions
            chmod($imageFile, 0644);

            // Call Python face recognition script
            $pythonScript = __DIR__ . '/face_match.py';
            $command = escapeshellcmd('python3') . ' ' . escapeshellarg($pythonScript) . ' ' . escapeshellarg($imageFile);

            // Execute command
            $output = shell_exec($command . " 2>&1");

            // Clean up temporary file
            if (file_exists($imageFile)) {
                unlink($imageFile);
            }

            // Parse JSON output
            $result = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid response from face recognition script');
            }

            // If match found, record attendance
            if ($result['status'] === 'success' && isset($result['student_id'])) {
                // Check if attendance already recorded
                $stmt = $pdo->prepare("SELECT id FROM attendance_records WHERE session_id = ? AND student_id = ?");
                $stmt->execute([$session_id, $result['student_id']]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    // Record attendance
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance_records (session_id, student_id, status, method, recorded_at)
                        VALUES (?, ?, 'present', 'face_recognition', NOW())
                    ");
                    $stmt->execute([$session_id, $result['student_id']]);
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Attendance marked successfully!',
                    'student_name' => $result['student_name'],
                    'student_reg' => $result['student_reg'],
                    'confidence' => $result['confidence']
                ]);
            } else {
                echo json_encode([
                    'status' => 'no_match',
                    'message' => $result['message'] ?? 'No face match found'
                ]);
            }
        } else {
            throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
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
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(to right, #87CEEB, #4682B4);
      margin: 0;
      line-height: 1.6;
      color: #333;
      font-size: 14px;
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
      background-color: #87CEEB;
    }

    .topbar {
      margin-left: 250px;
      background-color: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 15px 30px;
      border-bottom: 1px solid rgba(135, 206, 235, 0.3);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .topbar h5 {
      font-weight: 600;
      color: #2c3e50;
      margin: 0;
      font-size: 1.25rem;
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
      border: 2px solid #87CEEB;
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
      border-radius: 15px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      border: none;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-card .icon {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 20px;
      background: linear-gradient(135deg, #87CEEB 0%, #4682B4 100%);
      color: white;
      font-size: 1.5rem;
    }

    .stat-card h4 {
      font-size: 2rem;
      font-weight: 700;
      color: #2c3e50;
      margin-bottom: 5px;
    }

    .stat-card small {
      font-size: 0.85rem;
      color: #6c757d;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Attendance Table Enhancements */
    .attendance-table .table th {
      background: linear-gradient(135deg, #87CEEB 0%, #4682B4 100%);
      color: white;
      border-top: none;
      font-weight: 600;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      padding: 12px;
    }

    .attendance-table .table td {
      padding: 12px;
      vertical-align: middle;
      font-size: 0.9rem;
      color: #2c3e50;
    }

    .attendance-table .badge {
      font-size: 0.75rem;
      padding: 0.4rem 0.8rem;
      font-weight: 500;
      border-radius: 20px;
    }

    .attendance-table h5 {
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 1rem;
    }

    /* Loading States */
    .spinner-border {
      width: 2rem;
      height: 2rem;
    }

    /* Notification Styles */
    .alert {
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
      border: none;
      border-left: 4px solid;
      border-radius: 10px;
      padding: 15px 20px;
      margin-bottom: 20px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
    }

    .alert h5, .alert .alert-heading {
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 10px;
      font-size: 1.1rem;
    }

    .alert p {
      color: #5a6c7d;
      line-height: 1.6;
      margin-bottom: 0;
    }

    .alert-success {
      border-left-color: #10b981;
      background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
    }

    .alert-info {
      border-left-color: #06b6d4;
      background: linear-gradient(135deg, rgba(6, 182, 212, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
    }

    .alert-warning {
      border-left-color: #f59e0b;
      background: linear-gradient(135deg, rgba(245, 158, 11, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
    }

    .alert-danger {
      border-left-color: #ef4444;
      background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(255, 255, 255, 0.95) 100%);
    }

    /* Enhanced form styling */
    .form-label {
      font-weight: 600;
      color: #2c3e50;
      margin-bottom: 8px;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-select, .form-control {
      border-radius: 8px;
      border: 2px solid #e1e8ed;
      padding: 10px 15px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      background-color: #fff;
    }

    .form-select:disabled, .form-control:disabled {
      background-color: #f8f9fa;
      opacity: 0.7;
      cursor: not-allowed;
      border-color: #dee2e6;
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    /* Enhanced Button Styling */
    .btn {
      border-radius: 8px;
      font-weight: 600;
      padding: 12px 24px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      font-size: 0.9rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      border: none;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    .btn:active {
      transform: translateY(0);
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }

    .btn-primary {
      background: linear-gradient(135deg, #87CEEB 0%, #4682B4 100%);
      color: white;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #4682B4 0%, #87CEEB 100%);
    }

    .btn-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
    }

    .btn-danger:hover {
      background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    }

    .btn-secondary {
      background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
      color: white;
    }

    .btn-secondary:hover {
      background: linear-gradient(135deg, #495057 0%, #6c757d 100%);
    }

    .btn-info {
      background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
      color: white;
    }

    .btn-info:hover {
      background: linear-gradient(135deg, #0891b2 0%, #06b6d4 100%);
    }

    .btn-outline-primary {
      border: 2px solid #87CEEB;
      color: #87CEEB;
      background: transparent;
    }

    .btn-outline-primary:hover {
      background: #87CEEB;
      color: white;
    }

    .btn-outline-success {
      border: 2px solid #10b981;
      color: #10b981;
      background: transparent;
    }

    .btn-outline-success:hover {
      background: #10b981;
      color: white;
    }

    /* Loading animation for dropdowns */
    .form-select.loading {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23666' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'/%3e%3c/svg%3e");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1rem;
    }

    /* Webcam and Face Recognition Styles */
    #webcam-container {
      position: relative;
      display: inline-block;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }

    #webcam-preview {
      width: 100%;
      max-width: 400px;
      border: 3px solid #87CEEB;
      border-radius: 10px;
      background-color: #000;
      display: block;
    }

    #webcam-placeholder {
      width: 100%;
      max-width: 400px;
      height: 300px;
      background: #f8f9fa;
      border: 2px dashed #dee2e6;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6c757d;
      text-align: center;
    }

    #webcam-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      display: none;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 1.2rem;
      font-weight: 600;
      backdrop-filter: blur(2px);
      border-radius: 10px;
    }

    #webcam-overlay.processing {
      background: rgba(13, 202, 240, 0.9);
      border: 2px solid #0dcaf0;
    }

    #webcam-overlay.success {
      background: rgba(25, 135, 84, 0.9);
      border: 2px solid #198754;
    }

    #webcam-overlay.error {
      background: rgba(220, 53, 69, 0.9);
      border: 2px solid #dc3545;
    }

    .mark-attendance-section {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border-radius: 15px;
      padding: 30px;
      margin: 20px 0;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .attendance-result {
      margin-top: 20px;
      padding: 15px;
      border-radius: 10px;
      display: none;
    }

    .attendance-result.success {
      background: linear-gradient(135deg, rgba(25, 135, 84, 0.1) 0%, rgba(255, 255, 255, 0.9) 100%);
      border: 1px solid #198754;
      color: #155724;
    }

    .attendance-result.error {
      background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(255, 255, 255, 0.9) 100%);
      border: 1px solid #dc3545;
      color: #721c24;
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
      border-color: #87CEEB;
      box-shadow: 0 0 0 0.2rem rgba(135, 206, 235, 0.25);
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
      border-color: #87CEEB;
      box-shadow: 0 0 0 0.2rem rgba(135, 206, 235, 0.25);
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

    /* Session status indicator */
    #session-status-indicator .badge {
      font-size: 0.9rem;
      padding: 0.5rem 1rem;
      animation: pulse 2s infinite;
    }

    /* Enhanced end session button */
    #end-session.pulse {
      animation: buttonPulse 1.5s infinite;
      box-shadow: 0 0 20px rgba(220, 53, 69, 0.4);
    }

    @keyframes buttonPulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.05); }
      100% { transform: scale(1); }
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

    <!-- Active Session Section -->
    <div id="activeSessionSection" class="d-none">
      <div class="alert alert-success">
        <h5><i class="fas fa-play-circle me-2"></i>Active Attendance Session</h5>
        <p id="sessionInfo" class="mb-3"></p>

        <!-- Mark Attendance Section -->
        <div class="mark-attendance-section">
          <div class="row">
            <div class="col-md-6">
              <h6 class="mb-3"><i class="fas fa-camera me-2"></i>Face Recognition Attendance</h6>

              <!-- Webcam Container -->
              <div id="webcam-container" class="text-center">
                <video id="webcam-preview" autoplay muted playsinline style="display: none;"></video>
                <div id="webcam-placeholder">
                  <i class="fas fa-video fa-3x mb-3"></i>
                  <p>Webcam not active</p>
                  <small class="text-muted">Click "Mark Attendance" to start</small>
                </div>
                <div id="webcam-overlay">
                  <div id="webcam-status">Processing...</div>
                </div>
              </div>

              <!-- Control Buttons -->
              <div class="mt-3">
                <button type="button" id="markAttendanceBtn" class="btn btn-primary btn-lg me-2" disabled>
                  <i class="fas fa-camera me-2"></i>Mark Attendance
                </button>
                <button type="button" id="endSessionBtn" class="btn btn-danger">
                  <i class="fas fa-stop me-2"></i>End Session
                </button>
              </div>
            </div>

            <div class="col-md-6">
              <h6 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Session Statistics</h6>
              <div id="sessionStats">
                <div class="alert alert-info">
                  <i class="fas fa-spinner fa-spin me-2"></i>Loading session statistics...
                </div>
              </div>

              <!-- Attendance Result -->
              <div id="attendanceResult" class="attendance-result">
                <h6 id="resultTitle"></h6>
                <p id="resultMessage"></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Session Setup Section -->
    <div id="sessionSetupSection">
      <!-- Session Filters -->
      <form id="sessionForm" class="row g-3 mb-4">
      <div class="col-md-3">
        <label for="department" class="form-label fw-semibold">Department</label>
        <select id="department" name="department_id" class="form-select" required>
          <option value="" disabled selected>Select Department</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="option" class="form-label fw-semibold">Option</label>
        <select id="option" name="option_id" class="form-select" required disabled>
          <option value="" disabled selected>Select Option</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="classLevel" class="form-label fw-semibold">Class (Year)</label>
        <select id="classLevel" name="classLevel" class="form-select" required disabled>
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
          <select id="course" name="course_id" class="form-select" required disabled>
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

      <!-- Face Recognition Status Indicator -->
      <div id="face-recognition-indicator" class="face-recognition-indicator d-none">
        <div class="indicator-light">
          <i class="fas fa-camera"></i>
        </div>
        <div class="indicator-text">Face Recognition Active</div>
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
    let webcamStream = null;
    let csrfToken = '<?php echo bin2hex(random_bytes(32)); ?>';

    // DOM elements
    const departmentSelect = document.getElementById('department');
    const optionSelect = document.getElementById('option');
    const classSelect = document.getElementById('classLevel');
    const courseSelect = document.getElementById('course');
    const courseSearch = document.getElementById('course-search');
    const courseLoading = document.getElementById('course-loading');
    const startBtn = document.getElementById('start-session');
    const endBtn = document.getElementById('end-session');
    const markAttendanceBtn = document.getElementById('markAttendanceBtn');
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
      classSelect.addEventListener('change', handleClassChange);
      courseSelect.addEventListener('change', validateForm);
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
      markAttendanceBtn.addEventListener('click', handleMarkAttendance);
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
            classLevel: formData.get('classLevel'),
            course_id: formData.get('course_id'),
            csrf_token: formData.get('csrf_token')
        });

        // Validate required fields before submission
        const departmentId = formData.get('department_id');
        const optionId = formData.get('option_id');
        const courseId = formData.get('course_id');

        if (!departmentId || !optionId || !courseId) {
            console.error('❌ Missing required fields');
            showNotification('Please fill in all required fields (Department, Option, Course)', 'error');
            return;
        }

       try {
         showLoading('Starting session...');

         console.log('📡 Sending request to API...');

         const response = await fetch('api/attendance-session-api.php?action=start_session', {
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
        markAttendanceBtn.disabled = false;

      } catch (error) {
        console.error('Webcam error:', error);
        showNotification('Could not access webcam. Please check permissions.', 'error');
        markAttendanceBtn.disabled = true;
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
        formData.append('action', 'end_session');
        formData.append('session_id', currentSessionId);
        formData.append('csrf_token', csrfToken);

        const response = await fetch('api/attendance-session-api.php', {
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
        <strong>Started:</strong> ${new Date(sessionData.start_time).toLocaleString()}
      `;

      // Enable mark attendance button
      markAttendanceBtn.disabled = false;

      // Start webcam
      startWebcam();

      // Load session stats
      loadSessionStats();
    }

    // Hide active session UI
    function hideActiveSession() {
      document.getElementById('activeSessionSection').classList.add('d-none');
      document.getElementById('sessionSetupSection').style.display = 'block';

      currentSessionId = null;
      markAttendanceBtn.disabled = true;

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

    // Enhanced face recognition using API with better error handling
    async function processFaceRecognition(imageData) {
      console.log('🔍 Processing face recognition with API...');

      try {
        const formData = new FormData();
        formData.append('image_data', imageData);
        formData.append('session_id', currentSessionId);
        formData.append('csrf_token', csrfToken);

        // Add timeout for the request
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); // 10 second timeout

        const response = await fetch('api/attendance-session-api.php?action=process_face_recognition', {
          method: 'POST',
          body: formData,
          signal: controller.signal
        });

        clearTimeout(timeoutId);

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const result = await response.json();
        console.log('📊 Face recognition API result:', result);

        if (result.status === 'success') {
          // Enhanced result processing
          const enhancedResult = {
            recognized: result.recognized || false,
            student_id: result.student_id || null,
            confidence: result.confidence || 0,
            confidence_level: result.confidence_level || 'low',
            student_name: result.student_name || null,
            student_reg: result.student_reg || null,
            auto_mark: result.auto_mark || false,
            faces_detected: result.faces_detected || 0,
            top_matches: result.top_matches || [],
            timestamp: result.timestamp || new Date().toISOString(),
            message: result.message || null
          };

          // Log detailed recognition info
          if (enhancedResult.recognized) {
            console.log(`✅ Face recognized: ${enhancedResult.student_name || `ID ${enhancedResult.student_id}`} (${enhancedResult.confidence}% confidence, ${enhancedResult.confidence_level} level)`);
          } else {
            console.log(`❌ No face match found. Faces detected: ${enhancedResult.faces_detected}`);
          }

          return enhancedResult;
        } else {
          console.error('🚫 Face recognition API error:', result.message);
          return {
            recognized: false,
            error: result.message || 'Face recognition failed',
            faces_detected: 0
          };
        }
      } catch (error) {
        if (error.name === 'AbortError') {
          console.error('⏰ Face recognition request timed out');
          return {
            recognized: false,
            error: 'Face recognition request timed out',
            timeout: true
          };
        }

        console.error('💥 Face recognition request failed:', error);
        return {
          recognized: false,
          error: error.message || 'Network error during face recognition',
          network_error: true
        };
      }
    }

    // Fingerprint authentication (placeholder)
    async function processFingerprint() {
      // This would integrate with fingerprint scanner
      console.log('Processing fingerprint...');
    
      // Simulate fingerprint processing
      await new Promise(resolve => setTimeout(resolve, 2000));
    
      // For demo purposes, use a real student ID
      // In a real implementation, this would scan and match fingerprints
      return { authenticated: true, student_id: 1 }; // Use a real student ID
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
        if (sessionTimerInterval) {
          clearInterval(sessionTimerInterval);
          sessionTimerInterval = null;
        }
      } else if (!document.hidden && currentSessionId) {
        // Resume monitoring when tab becomes visible
        console.log('Tab visible, resuming attendance monitoring');
        loadAttendanceRecords();
        // Resume timer
        if (sessionStartTime && !sessionTimerInterval) {
          sessionTimerInterval = setInterval(updateSessionTimer, 1000);
        }
      }
    });

    // Warn user before leaving with active session
    window.addEventListener('beforeunload', function(e) {
      if (currentSessionId) {
        // Show confirmation dialog
        const message = 'You have an active attendance session. Are you sure you want to leave? The session will remain active.';
        e.returnValue = message; // Standard for most browsers
        return message; // For some older browsers
      }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
      if (currentSessionId) {
        stopAttendanceMonitoring();
        stopWebcam();
        stopSessionTimer();
      }
    });

    // Enhanced Face Recognition Implementation
    let faceRecognitionInterval = null;
    let lastRecognitionTime = 0;
    let recognitionCooldown = 5000; // 5 seconds cooldown after successful recognition
    let consecutiveFailures = 0;
    let maxConsecutiveFailures = 3;

    function startFaceRecognition() {
      if (!faceRecognitionActive || !webcamPreview.srcObject) return;

      console.log('🚀 Starting enhanced face recognition...');

      faceRecognitionInterval = setInterval(async () => {
        try {
          // Check if we're in cooldown period
          const now = Date.now();
          if (now - lastRecognitionTime < recognitionCooldown) {
            return; // Skip this cycle
          }

          const video = webcamPreview;

          // Ensure video is ready
          if (video.videoWidth === 0 || video.videoHeight === 0) {
            console.log('Video not ready yet, skipping...');
            return;
          }

          const canvas = document.createElement('canvas');
          canvas.width = video.videoWidth;
          canvas.height = video.videoHeight;

          const ctx = canvas.getContext('2d');
          ctx.drawImage(video, 0, 0);

          const imageData = canvas.toDataURL('image/jpeg', 0.8);

          // Show processing overlay with enhanced feedback
          updateFaceRecognitionStatus('🔍 Scanning for faces...', 'processing');

          const result = await processFaceRecognition(imageData);

          if (result.recognized && currentSessionId) {
            // Successful recognition
            consecutiveFailures = 0; // Reset failure counter
            lastRecognitionTime = now;

            // Enhanced success feedback with validation info
            const studentInfo = result.student_name || `ID ${result.student_id}`;
            const confidenceText = result.confidence ? ` (${result.confidence}% confidence)` : '';
            const confidenceLevel = result.confidence_level || 'unknown';

            // Check validation results for additional feedback
            const validation = result.validation || {};
            let validationMessage = '';

            if (!validation.face_size_ok) {
              validationMessage += ' (Face size improved)';
            }
            if (!validation.face_centered) {
              validationMessage += ' (Positioning improved)';
            }
            if (!validation.confidence_margin_ok) {
              validationMessage += ' (Lighting improved)';
            }

            // Show detailed success status
            const statusMessage = `✅ Face Matched — ${studentInfo}${confidenceText}${validationMessage}`;
            updateFaceRecognitionStatus(statusMessage, 'success');

            // Record attendance with enhanced logging
            const attendanceRecorded = await recordAttendance(result.student_id, 'face_recognition', 'present');

            if (attendanceRecorded) {
              // Enhanced notification with more details
              const notificationText = result.student_name ?
                `${result.student_name}${confidenceText}` :
                `Student ID ${result.student_id}${confidenceText}`;

              showNotification(`🎯 Face Recognition Success: Attendance marked for ${notificationText}`, 'success');

              // Show success overlay longer for better visibility
              setTimeout(() => hideFaceRecognitionOverlay(), 4000);

              // Add visual feedback to webcam container
              flashWebcamContainer('success');
            } else {
              updateFaceRecognitionStatus('⚠️ Face recognized but attendance recording failed', 'warning');
              setTimeout(() => hideFaceRecognitionOverlay(), 3000);
            }

          } else {
            // No match found
            consecutiveFailures++;

            if (consecutiveFailures >= maxConsecutiveFailures) {
              // Show different message after multiple failures
              updateFaceRecognitionStatus('🔄 Scanning... No matches yet', 'info');
            } else {
              updateFaceRecognitionStatus('❌ No face match found', 'error');
            }

            // Hide overlay after shorter time for non-matches
            setTimeout(() => hideFaceRecognitionOverlay(), 1500);
          }

        } catch (error) {
          console.error('Face recognition error:', error);
          consecutiveFailures++;

          if (consecutiveFailures >= maxConsecutiveFailures) {
            updateFaceRecognitionStatus('⚠️ Face recognition temporarily unavailable', 'warning');
            setTimeout(() => hideFaceRecognitionOverlay(), 2000);
          } else {
            hideFaceRecognitionOverlay();
          }
        }
      }, 2500); // Check every 2.5 seconds for better responsiveness
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

    function updateFaceRecognitionStatus(message, type = 'info') {
      const overlay = document.getElementById('webcam-overlay');
      const status = document.getElementById('face-recognition-status');

      // Update status text
      status.textContent = message;

      // Update styling based on type
      status.className = '';
      overlay.className = 'webcam-overlay'; // Reset classes

      if (type === 'success') {
        status.classList.add('text-success', 'fw-bold');
        overlay.classList.add('success');
      } else if (type === 'error') {
        status.classList.add('text-danger', 'fw-bold');
        overlay.classList.add('error');
      } else if (type === 'warning') {
        status.classList.add('text-warning', 'fw-bold');
        overlay.classList.add('warning');
      } else if (type === 'processing') {
        status.classList.add('text-info', 'fw-bold');
        overlay.classList.add('processing');
      } else {
        status.classList.add('text-light');
        overlay.classList.add('info');
      }

      overlay.style.display = 'flex';
    }

    function hideFaceRecognitionOverlay() {
      const overlay = document.getElementById('webcam-overlay');
      overlay.style.display = 'none';
      overlay.className = 'webcam-overlay'; // Reset classes
    }

    // Enhanced visual feedback function
    function flashWebcamContainer(type = 'success') {
      const container = document.getElementById('webcam-container');
      if (!container) return;

      // Remove existing flash classes
      container.classList.remove('flash-success', 'flash-error', 'flash-info');

      // Add appropriate flash class
      container.classList.add(`flash-${type}`);

      // Remove flash class after animation
      setTimeout(() => {
        container.classList.remove(`flash-${type}`);
      }, 1000);
    }

    // Enhanced webcam start with face recognition
    const originalStartWebcam = startWebcam;
    startWebcam = async function() {
      await originalStartWebcam();
      if (faceRecognitionActive) {
        startFaceRecognition();
        updateFaceRecognitionIndicator('active');
      }
    };

    const originalStopWebcam = stopWebcam;
    stopWebcam = function() {
      originalStopWebcam();
      stopFaceRecognition();
      updateFaceRecognitionIndicator('inactive');
    };

    // Face recognition indicator control
    function updateFaceRecognitionIndicator(status) {
      const indicator = document.getElementById('face-recognition-indicator');
      const light = indicator.querySelector('.indicator-light');
      const text = indicator.querySelector('.indicator-text');

      if (!indicator) return;

      light.className = 'indicator-light';

      switch (status) {
        case 'active':
          indicator.classList.remove('d-none');
          light.classList.add('active');
          text.textContent = 'Face Recognition Active';
          break;
        case 'inactive':
          indicator.classList.add('d-none');
          break;
        case 'error':
          indicator.classList.remove('d-none');
          light.classList.add('error');
          text.textContent = 'Face Recognition Error';
          break;
        default:
          indicator.classList.add('d-none');
      }
    }

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
      if (!currentSessionId) {
        console.error('No active session ID for recording attendance');
        return false;
      }

      console.log(`Recording attendance: student=${studentId}, method=${method}, status=${status}, session=${currentSessionId}`);

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
        console.log('Attendance recording API response:', result);

        if (result.status === 'success') {
          console.log('Attendance recorded successfully');
          loadAttendanceRecords();
          loadSessionStats();
          return true;
        } else {
          console.error('Failed to record attendance:', result.message);
          showNotification('Failed to record attendance: ' + result.message, 'error');
          return false;
        }
      } catch (error) {
        console.error('Error recording attendance:', error);
        showNotification('Error recording attendance: ' + error.message, 'error');
        return false;
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
        const response = await fetch(`api/attendance-session-api.php?action=get_user_active_session`);
        const result = await response.json();

        // console.log('Resume session API response:', result);

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
