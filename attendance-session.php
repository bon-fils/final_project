<?php
/**
 * Attendance Session Management - Clean Implementation
 * Complete system with database integration, face recognition, and session management
 * Integrated with RP Attendance System backend
 */

// Get user session data and initialize backend
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin', 'lecturer', 'hod']);

// Initialize backend classes
require_once "backend/classes/Logger.php";
require_once "backend/classes/InputValidator.php";
require_once "backend/classes/DataSanitizer.php";

$user_id = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'lecturer';
$lecturer_id = $_SESSION['lecturer_id'] ?? null;
$department_id = $_SESSION['department_id'] ?? null;

// Initialize logger for attendance sessions
$logger = new Logger('logs/attendance_session.log', Logger::INFO);

// Get user information and department assignment with enhanced validation
try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.email, u.first_name, u.last_name,
               l.department_id, d.name as department_name,
               l.id as lecturer_id, u.status as user_status
        FROM users u
        LEFT JOIN lecturers l ON u.id = l.user_id
        LEFT JOIN departments d ON l.department_id = d.id
        WHERE u.id = :user_id
    ");
    $stmt->execute(['user_id' => $user_id]);
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        $logger->warning('Attendance Session', 'User not found', ['user_id' => $user_id]);
        header("Location: login.php?error=user_not_found");
        exit;
    }

    // Validate user status
    if ($user_info['user_status'] !== 'active') {
        $logger->warning('Attendance Session', 'Inactive user attempted access', [
            'user_id' => $user_id,
            'status' => $user_info['user_status']
        ]);
        header("Location: login.php?error=account_inactive");
        exit;
    }

    // Check if lecturer has department assignment
    if (!$user_info['department_id']) {
        $logger->warning('Attendance Session', 'User without department assignment', [
            'user_id' => $user_id,
            'username' => $user_info['username']
        ]);
        header("Location: attendance-session.php?error=no_department");
        exit;
    }

    $logger->info('Attendance Session', 'User authenticated successfully', [
        'user_id' => $user_id,
        'department_id' => $user_info['department_id'],
        'department_name' => $user_info['department_name']
    ]);

} catch (PDOException $e) {
    $logger->error('Attendance Session', 'Database error during user validation', [
        'user_id' => $user_id,
        'error' => $e->getMessage()
    ]);
    header("Location: login.php?error=database");
    exit;
}

// Set department variables for auto-selection
$assigned_department_id = $user_info['department_id'];
$assigned_department_name = $user_info['department_name'];
$lecturer_id = $user_info['lecturer_id'];

// Page metadata
$pageTitle = "Attendance Session - " . htmlspecialchars($assigned_department_name) . " | " . ucfirst($userRole) . " | RP Attendance System";
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
            color: #2d3748;
        }

        .topbar .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .topbar .user-info .badge {
            font-size: 12px;
        }

        /* Form Styles */
        .form-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-section .section-title {
            color: #2d3748;
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-text {
            font-size: 12px;
            margin-top: 6px;
        }

        .btn {
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* Session Status */
        .session-status {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }

        .session-status .status-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .session-status .status-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .session-status .status-text {
            color: #718096;
            margin-bottom: 20px;
        }

        /* Biometric Section */
        .biometric-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
            display: none;
        }

        .biometric-section.active {
            display: block;
        }

        .biometric-card {
            text-align: center;
            padding: 30px;
            border: 2px dashed #e2e8f0;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .biometric-card:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .biometric-icon {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 20px;
        }

        .biometric-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .biometric-text {
            color: #718096;
            margin-bottom: 20px;
        }

        /* Webcam Container */
        .webcam-container {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .webcam-placeholder {
            background: #f7fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 300px;
            color: #a0aec0;
            font-size: 16px;
        }

        #webcam-preview {
            width: 100%;
            height: 300px;
            object-fit: cover;
        }

        .webcam-status {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(72, 187, 120, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            display: none;
        }

        .webcam-status.active {
            display: block;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 25px;
            text-align: center;
            transition: var(--transition);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #718096;
            font-size: 14px;
            font-weight: 500;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-content {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            margin-bottom: 20px;
        }

        /* Mobile Responsive */
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
                gap: 15px;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body>
    <!-- Demo Notice -->
    <div class="demo-notice">
        <i class="fas fa-info-circle"></i>
        Attendance Session Management System - <?php echo htmlspecialchars($assigned_department_name); ?>
    </div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-graduation-cap me-2"></i>RP System</h4>
            <div class="subtitle"><?php echo ucfirst($userRole); ?> Panel</div>
        </div>

        <nav>
            <a href="admin-dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a>
            <a href="manage-departments.php"><i class="fas fa-building"></i>Departments</a>
            <a href="assign-hod.php"><i class="fas fa-user-tie"></i>Assign HOD</a>
            <a href="admin-reports.php"><i class="fas fa-chart-bar"></i>Reports</a>
            <a href="manage-users.php"><i class="fas fa-users"></i>Manage Users</a>
            <a href="system-logs.php"><i class="fas fa-file-alt"></i>System Logs</a>
            <a href="logout.php" class="mt-4 text-danger"><i class="fas fa-sign-out-alt"></i>Logout</a>
        </nav>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="btn btn-primary d-md-none position-fixed" style="top: 80px; right: 20px; z-index: 1001;" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <h1 class="page-title">
                <i class="fas fa-clock me-3"></i>
                Attendance Session - <?php echo htmlspecialchars($assigned_department_name); ?>
            </h1>
            <div class="user-info">
                <span class="badge bg-primary"><?php echo ucfirst($userRole); ?></span>
                <span class="text-muted"><?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?></span>
            </div>
        </div>

        <!-- Session Setup Form -->
        <div class="form-section" id="sessionSetupSection">
            <h2 class="section-title">
                <i class="fas fa-cogs me-2"></i>Session Configuration
            </h2>

            <form id="sessionForm">
                <!-- Department (Auto-selected) -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="department" class="form-label">
                                <i class="fas fa-building me-1"></i>Department
                            </label>
                            <select class="form-select" id="department" disabled>
                                <option value="<?php echo $assigned_department_id; ?>" selected>
                                    <?php echo htmlspecialchars($assigned_department_name); ?>
                                </option>
                            </select>
                            <div class="form-text text-success">
                                <i class="fas fa-check me-1"></i>Auto-selected based on your assignment
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="option" class="form-label">
                                <i class="fas fa-graduation-cap me-1"></i>Academic Option
                            </label>
                            <select class="form-select" id="option" name="option_id" required>
                                <option value="" disabled selected>Choose an academic option</option>
                            </select>
                            <div id="option-feedback" class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Select the academic option for this session
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="course" class="form-label">
                                <i class="fas fa-book me-1"></i>Course
                            </label>
                            <select class="form-select" id="course" name="course_id" required disabled>
                                <option value="" disabled selected>Please select an option first</option>
                            </select>
                            <div id="course-feedback" class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Courses will load after option selection
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="year_level" class="form-label">
                                <i class="fas fa-calendar me-1"></i>Year Level
                            </label>
                            <select class="form-select" id="year_level" name="year_level" required>
                                <option value="" disabled selected>Select year level</option>
                                <option value="1">Year 1</option>
                                <option value="2">Year 2</option>
                                <option value="3">Year 3</option>
                                <option value="4">Year 4</option>
                            </select>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Select the academic year for this session
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="biometric_method" class="form-label">
                                <i class="fas fa-fingerprint me-1"></i>Biometric Method
                            </label>
                            <select class="form-select" id="biometric_method" name="biometric_method" required>
                                <option value="" disabled selected>Choose biometric method</option>
                                <option value="face">Face Recognition</option>
                                <option value="finger">Fingerprint</option>
                            </select>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>Select face recognition or fingerprint scanning
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" id="start-session" disabled>
                            <i class="fas fa-play me-2"></i>Start Attendance Session
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Active Session Section -->
        <div id="activeSessionSection" style="display: none;">
            <!-- Session Status -->
            <div class="session-status">
                <div class="status-icon text-success">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="status-title text-success">Session Active</div>
                <div class="status-text" id="sessionInfo">
                    Session details will appear here
                </div>
                <button class="btn btn-danger" onclick="endSession()">
                    <i class="fas fa-stop me-2"></i>End Session
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value" id="total-students">0</div>
                    <div class="stat-label">Total Students</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value" id="present-count">0</div>
                    <div class="stat-label">Present</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value" id="absent-count">0</div>
                    <div class="stat-label">Absent</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-value" id="attendance-rate">0%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            </div>

            <!-- Face Recognition Section -->
            <div class="biometric-section" id="faceRecognitionSection">
                <div class="biometric-card">
                    <div class="biometric-icon">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="biometric-title">Face Recognition Active</div>
                    <div class="biometric-text">
                        Camera is ready for attendance marking. Click the button below to capture and recognize faces.
                    </div>

                    <div class="webcam-container mb-3">
                        <div class="webcam-placeholder" id="webcam-placeholder">
                            <i class="fas fa-camera fa-3x mb-3"></i>
                            <div>Initializing camera...</div>
                        </div>
                        <video id="webcam-preview" autoplay playsinline style="display: none;"></video>
                        <div class="webcam-status" id="webcam-status">
                            <i class="fas fa-circle text-success me-1"></i>Camera Active
                        </div>
                    </div>

                    <button class="btn btn-success btn-lg" id="markAttendanceBtn" onclick="markAttendance()" disabled>
                        <i class="fas fa-user-check me-2"></i>Mark Attendance
                    </button>
                </div>
            </div>

            <!-- Fingerprint Section -->
            <div class="biometric-section" id="fingerprintSection">
                <div class="biometric-card">
                    <div class="biometric-icon">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div class="biometric-title">Fingerprint Scanner Ready</div>
                    <div class="biometric-text">
                        Fingerprint scanner is connected and ready. Click the button below to scan fingerprints.
                    </div>

                    <button class="btn btn-primary btn-lg" onclick="scanFingerprint()">
                        <i class="fas fa-fingerprint me-2"></i>Scan Fingerprint
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-content">
            <div class="spinner-border loading-spinner text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h5 id="loadingText">Loading Attendance Session System</h5>
            <p class="mb-0">Please wait while we initialize the system...</p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Attendance Session JavaScript -->
    <script src="js/attendance-session.js"></script>

    <!-- Backend Configuration -->
    <script>
        // Backend configuration for JavaScript
        window.BACKEND_CONFIG = {
            DEPARTMENT_ID: <?php echo json_encode($assigned_department_id); ?>,
            DEPARTMENT_NAME: <?php echo json_encode($assigned_department_name); ?>,
            LECTURER_ID: <?php echo json_encode($lecturer_id); ?>,
            USER_ROLE: <?php echo json_encode($userRole); ?>,
            API_BASE_URL: 'api/'
        };

        console.log('Backend config loaded:', window.BACKEND_CONFIG);
        
        // Initialize the attendance session system when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 DOM loaded, initializing attendance session...');
            if (typeof FormHandlers !== 'undefined') {
                FormHandlers.initialize();
            } else {
                console.error('❌ FormHandlers not found - check attendance-session.js');
            }
        });
    </script>
</body>
</html>