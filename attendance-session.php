<?php
/**
 * Attendance Session Management - Frontend Only
 * Complete frontend implementation with real face recognition
 * No backend dependencies - works as standalone demo
 */

// Get user session data
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin', 'lecturer', 'hod']);

$user_id = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'lecturer';
$lecturer_id = $_SESSION['lecturer_id'] ?? null;
$department_id = $_SESSION['department_id'] ?? null;

// Get user information
try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.email, u.first_name, u.last_name,
               l.department_id, d.name as department_name
        FROM users u
        LEFT JOIN lecturers l ON u.id = l.user_id
        LEFT JOIN departments d ON l.department_id = d.id
        WHERE u.id = :user_id
    ");
    $stmt->execute(['user_id' => $user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        header("Location: login.php?error=user_not_found");
        exit;
    }

} catch (PDOException $e) {
    error_log("User info error: " . $e->getMessage());
    header("Location: login.php?error=database");
    exit;
}

// Page metadata
$pageTitle = "Attendance Session | " . ucfirst($userRole) . " | RP Attendance System";
$pageDescription = "Manage attendance sessions with face recognition and fingerprint scanning";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="author" content="RP Attendance System">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"
            integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

    <!-- Custom Styles -->
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
            --warning-gradient: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            --info-gradient: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);
            --sidebar-width: 250px;
            --border-radius: 10px;
            --box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Demo Notice */
        .demo-notice {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            padding: 12px 20px;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1000;
        }

        .demo-notice i {
            margin-right: 8px;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--primary-gradient);
            color: white;
            padding: 20px 0;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }

        .sidebar .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 18px;
        }

        .sidebar .sidebar-header .subtitle {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 4px;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .sidebar a:hover, .sidebar a.active {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: rgba(255, 255, 255, 0.5);
        }

        .sidebar a i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }

        /* Topbar */
        .topbar {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .topbar .page-title {
            margin: 0;
            font-weight: 600;
            font-size: 24px;
            color: #2c3e50;
        }

        .topbar .system-title {
            color: #7f8c8d;
            font-weight: 500;
            font-size: 14px;
        }

        .session-status {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .session-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 14px;
        }

        .session-badge.active {
            background: var(--success-gradient);
            color: white;
        }

        .session-timer {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #666;
            font-weight: 500;
            font-size: 14px;
        }

        /* Session Cards */
        .session-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .session-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .session-card .card-title {
            font-weight: 600;
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .session-card .card-title i {
            color: #667eea;
        }

        /* Form Styles */
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .form-section .section-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 15px;
            font-size: 16px;
        }

        .form-select, .form-control {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            padding: 10px 15px;
            font-size: 14px;
        }

        .form-select:focus, .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 10px 20px;
            transition: var(--transition);
        }

        .btn-success {
            background: var(--success-gradient);
            border: none;
        }

        .btn-success:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
        }

        .btn-danger {
            background: var(--danger-gradient);
            border: none;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(244, 67, 54, 0.3);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
        }

        .btn-info {
            background: var(--info-gradient);
            border: none;
        }

        /* Webcam Container */
        .webcam-container {
            border: 3px solid #007bff;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            min-height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .webcam-container.active {
            border-color: #28a745;
            background: linear-gradient(135deg, #e8f5e8 0%, #f8f9fa 100%);
        }

        .webcam-container video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            object-fit: cover;
        }

        .webcam-status {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .webcam-status.active {
            background: rgba(40, 167, 69, 0.9);
        }

        .webcam-placeholder {
            text-align: center;
            color: #6c757d;
        }

        .webcam-placeholder i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: block;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stats-card {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            transition: var(--transition);
        }

        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(102, 126, 234, 0.3);
        }

        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.9;
        }

        .stats-card h4 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stats-card small {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Table Styles */
        .table {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 15px;
        }

        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
        }

        .badge {
            font-size: 12px;
            padding: 6px 12px;
            border-radius: 20px;
        }

        /* Status Indicators */
        .status-processing {
            background: var(--warning-gradient);
            color: white;
        }

        .status-success {
            background: var(--success-gradient);
            color: white;
        }

        .status-error {
            background: var(--danger-gradient);
            color: white;
        }

        /* Notifications */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 350px;
            max-width: 500px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .notification .alert {
            margin-bottom: 0;
            border: none;
            border-radius: 8px;
        }

        /* Loading States */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .topbar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .session-card {
                padding: 15px;
            }
        }

        /* Utility Classes */
        .text-gradient {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .shadow-hover {
            transition: var(--transition);
        }

        .shadow-hover:hover {
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }

        /* Animation for new attendance records */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in-row {
            animation: fadeIn 0.5s ease-in;
        }
    </style>
</head>

<body>
    <!-- System Status Notice -->
    <div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
        <i class="fas fa-info-circle me-2"></i><strong>Live System:</strong> Connected to RP Attendance Database.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Include Lecturer Sidebar -->
    <?php include 'includes/lecturer-sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div>
                <h1 class="page-title">Attendance Session Management</h1>
                <div class="system-title">Rwanda Polytechnic Attendance System</div>
            </div>

            <div class="session-status" id="session-status-indicator" style="display: none;">
                <span class="session-badge active">
                    <i class="fas fa-play-circle"></i>
                    <span id="session-status-text">Session Active</span>
                </span>
                <div class="session-timer">
                    <i class="fas fa-clock"></i>
                    <span id="session-timer">00:00:00</span>
                </div>
            </div>
        </header>

        <!-- Information Panel for No Department Access -->
        <div id="noDepartmentInfo" class="alert alert-warning alert-dismissible fade show mb-4 d-none" style="border-left: 4px solid #ffc107;" role="alert">
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

        <!-- Session Setup Section -->
        <section class="session-card" id="sessionSetupSection">
            <h2 class="card-title">
                <i class="fas fa-cogs"></i>Session Configuration
            </h2>

            <form id="sessionForm" novalidate>
                <div class="form-section">
                    <h3 class="section-title">Course Selection</h3>
                    <div class="row g-3">
                        <!-- Department Selection -->
                        <div class="col-md-3">
                            <label for="department" class="form-label fw-semibold">
                                <i class="fas fa-building me-1"></i>Department
                            </label>
                            <select id="department" name="department_id" class="form-select" required>
                                <option value="" disabled selected>Select Department</option>
                            </select>
                        </div>

                        <!-- Option Selection -->
                        <div class="col-md-3">
                            <label for="option" class="form-label fw-semibold">
                                <i class="fas fa-list me-1"></i>Option
                            </label>
                            <select id="option" name="option_id" class="form-select" required disabled>
                                <option value="" disabled selected>Select Option</option>
                            </select>
                        </div>

                        <!-- Year Level Selection -->
                        <div class="col-md-2">
                            <label for="year_level" class="form-label fw-semibold">
                                <i class="fas fa-graduation-cap me-1"></i>Year Level
                            </label>
                            <select id="year_level" name="year_level" class="form-select" required>
                                <option value="" disabled selected>Select Year</option>
                                <option value="1">Year 1</option>
                                <option value="2">Year 2</option>
                                <option value="3">Year 3</option>
                                <option value="4">Year 4</option>
                            </select>
                        </div>

                        <!-- Course Selection -->
                        <div class="col-md-4">
                            <label for="course" class="form-label fw-semibold">
                                <i class="fas fa-book me-1"></i>Course
                            </label>
                            <select id="course" name="course_id" class="form-select" required disabled>
                                <option value="" disabled selected>Select Course</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Attendance Method</h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="biometric_method" class="form-label fw-semibold">
                                <i class="fas fa-fingerprint me-1"></i>Biometric Method
                            </label>
                            <select id="biometric_method" name="biometric_method" class="form-select" required>
                                <option value="" disabled selected>Select Method</option>
                                <option value="face">Face Recognition</option>
                                <option value="finger">Fingerprint</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="col-md-8 d-flex align-items-end gap-2">
                            <button type="submit" id="start-session" class="btn btn-success" disabled>
                                <i class="fas fa-play me-2"></i>Start Session
                            </button>
                            <button type="button" id="end-session" class="btn btn-danger" disabled>
                                <i class="fas fa-stop me-2"></i>End Session
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <!-- Active Session Section -->
        <div id="activeSessionSection" style="display: none;">
            <!-- Face Recognition Section -->
            <section class="session-card d-none" id="faceRecognitionSection">
                <h2 class="card-title">
                    <i class="fas fa-camera"></i>Face Recognition Attendance
                </h2>

                <div class="row">
                    <div class="col-md-8">
                        <div id="webcam-container" class="webcam-container">
                            <video id="webcam-preview" autoplay muted playsinline style="width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); object-fit: cover;"></video>
                            <div id="webcam-placeholder" class="webcam-placeholder">
                                <i class="fas fa-video fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-3">Camera not active</p>
                                <button type="button" id="startWebcamBtn" class="btn btn-primary btn-lg">
                                    <i class="fas fa-video me-2"></i>Start Camera
                                </button>
                            </div>
                            <div class="webcam-status d-none" id="webcam-status">
                                <i class="fas fa-circle text-success"></i>
                                <span>Camera Active</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="d-grid gap-3">
                            <button type="button" id="markAttendanceBtn" class="btn btn-success btn-lg" disabled>
                                <i class="fas fa-camera me-2"></i>Mark Attendance
                            </button>

                            <button type="button" id="endSessionBtn" class="btn btn-danger btn-lg">
                                <i class="fas fa-stop me-2"></i>End Session
                            </button>

                            <div class="card border-info">
                                <div class="card-body">
                                    <h6 class="card-title text-info">
                                        <i class="fas fa-info-circle me-2"></i>Instructions
                                    </h6>
                                    <ul class="list-unstyled small mb-0">
                                        <li><i class="fas fa-check text-success me-2"></i>Ensure good lighting</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Face the camera directly</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Remove glasses/sunglasses</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Keep face in frame</li>
                                    </ul>
                                </div>
                            </div>

                            <div id="face-status" class="alert alert-info d-none">
                                <i class="fas fa-spinner loading-spinner me-2"></i>
                                <span id="face-message">Ready to scan...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Fingerprint Section -->
            <section class="session-card d-none" id="fingerprintSection">
                <h2 class="card-title">
                    <i class="fas fa-fingerprint"></i>Fingerprint Attendance
                </h2>

                <div class="row">
                    <div class="col-md-6">
                        <div class="text-center p-4">
                            <i class="fas fa-fingerprint fa-4x text-info mb-3"></i>
                            <h5>ESP32 Fingerprint Scanner</h5>
                            <p class="text-muted">Make sure your ESP32 device is connected and running</p>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="d-grid gap-3">
                            <button type="button" id="scanFingerprintBtn" class="btn btn-info btn-lg">
                                <i class="fas fa-hand-paper me-2"></i>Scan Fingerprint
                            </button>

                            <button type="button" id="testESP32Btn" class="btn btn-outline-info">
                                <i class="fas fa-wifi me-2"></i>Test ESP32 Connection
                            </button>

                            <div id="fingerprint-status" class="alert alert-info d-none">
                                <i class="fas fa-spinner loading-spinner me-2"></i>
                                <span id="fingerprint-message">Connecting to ESP32...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Session Statistics -->
                <section class="session-card" id="sessionStatsSection">
                    <h2 class="card-title">
                        <i class="fas fa-chart-bar"></i>Live Session Statistics
                    </h2>
    
                    <div class="stats-grid">
                        <div class="stats-card">
                            <i class="fas fa-users"></i>
                            <h4 id="total-students">15</h4>
                            <small>Total Students</small>
                        </div>
    
                        <div class="stats-card">
                            <i class="fas fa-check-circle"></i>
                            <h4 id="present-count">0</h4>
                            <small>Present</small>
                        </div>
    
                        <div class="stats-card">
                            <i class="fas fa-times-circle"></i>
                            <h4 id="absent-count">0</h4>
                            <small>Absent</small>
                        </div>
    
                        <div class="stats-card">
                            <i class="fas fa-percentage"></i>
                            <h4 id="attendance-rate">0%</h4>
                            <small>Attendance Rate</small>
                        </div>
                    </div>
    
                    <!-- Sample Students List -->
                    <div class="mt-4">
                        <h6 class="text-muted mb-3">
                            <i class="fas fa-list me-2"></i>Sample Students in This Session
                        </h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <small class="text-muted d-block">
                                    <strong>Computer Science (Year 1):</strong><br>
                                    • John Doe (STU001) • Jane Smith (STU002)<br>
                                    • Henry Davis (STU010) • Maya Patel (STU015)
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">
                                    <strong>Information Technology (Year 2):</strong><br>
                                    • Bob Johnson (STU003) • Ivy Chen (STU011)<br>
                                    <strong>Electrical Engineering (Year 3):</strong><br>
                                    • Charlie Wilson (STU005) • Grace Lee (STU009)
                                </small>
                            </div>
                        </div>
                    </div>
                </section>

            <!-- Attendance Records Table -->
            <section class="session-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="card-title mb-0">
                        <i class="fas fa-list"></i>Live Attendance Records
                    </h2>

                    <div class="d-flex gap-2">
                        <button type="button" id="refresh-attendance" class="btn btn-outline-primary">
                            <i class="fas fa-sync-alt me-1"></i>Refresh
                        </button>
                        <button type="button" id="export-attendance" class="btn btn-outline-success">
                            <i class="fas fa-download me-1"></i>Export CSV
                        </button>
                    </div>
                </div>

                <div id="attendance-loading" class="text-center py-4 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading attendance records...</p>
                </div>

                <div id="attendance-table-container" class="d-none">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><i class="fas fa-id-card me-1"></i>Student ID</th>
                                <th><i class="fas fa-user me-1"></i>Name</th>
                                <th><i class="fas fa-clock me-1"></i>Time</th>
                                <th><i class="fas fa-info-circle me-1"></i>Status</th>
                                <th><i class="fas fa-fingerprint me-1"></i>Method</th>
                                <th><i class="fas fa-cogs me-1"></i>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="attendance-list">
                            <!-- Dynamic attendance records will be loaded here -->
                        </tbody>
                    </table>
                </div>

                <div id="no-attendance" class="text-center py-4">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Attendance Records</h5>
                    <p class="text-muted">Start a session to begin recording attendance.</p>
                </div>
            </section>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Application Scripts -->
    <script src="js/attendance-session-demo.js"></script>
</body>
</html>