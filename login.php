<?php
// login.php
session_start();
require 'config.php'; // contains $pdo connection

// Enhanced security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ensure CSRF token is valid
if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = "";
$emailOrUsername = "";

// Enhanced account-based rate limiting with IP consideration
function checkRateLimit($emailOrUsername) {
    $identifier = strtolower(trim($emailOrUsername));
    $key = 'login_attempts_' . md5($identifier . '_' . ($_SERVER['REMOTE_ADDR'] ?? ''));
    
    // Global rate limiting key
    $globalKey = 'global_login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? '');

    // Initialize session keys if not set
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 0,
            'time' => time()
        ];
    }
    
    if (!isset($_SESSION[$globalKey])) {
        $_SESSION[$globalKey] = [
            'count' => 0,
            'time' => time()
        ];
    }

    $attempts = &$_SESSION[$key];
    $globalAttempts = &$_SESSION[$globalKey];

    // Reset if more than 15 minutes passed
    $resetTime = 900; // 15 minutes
    if (time() - $attempts['time'] > $resetTime) {
        $attempts = [
            'count' => 0,
            'time' => time()
        ];
    }
    
    if (time() - $globalAttempts['time'] > $resetTime) {
        $globalAttempts = [
            'count' => 0,
            'time' => time()
        ];
    }

    // Check global limits (more restrictive)
    if ($globalAttempts['count'] >= 10) {
        error_log("Global rate limit exceeded for IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }

    // Check account-specific limits
    if ($attempts['count'] >= 5) {
        error_log("Account rate limit exceeded for: $identifier from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }

    $attempts['count']++;
    $globalAttempts['count']++;
    return true;
}

// Enhanced input sanitization function
function sanitizeInput($input, $maxLength = 100) {
    $input = trim($input ?? '');
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

// Enhanced password verification supporting multiple hash types
function verifyPassword($inputPassword, $storedHash) {
    $inputPassword = trim($inputPassword);
    $storedHash = trim($storedHash);
    
    if (empty($storedHash)) {
        return false;
    }
    
    // Try password_verify first (handles bcrypt, argon2, etc.)
    if (password_verify($inputPassword, $storedHash)) {
        return true;
    }
    
    // Check for legacy plaintext passwords with timing-safe comparison
    $isLegacyMatch = false;
    if (strlen($storedHash) <= 255 && !preg_match('/^\$2[ayb]\$.{56}$/', $storedHash) && !preg_match('/^\$argon2id\$/', $storedHash)) {
        // Likely a legacy password - use hash_equals for timing attack protection
        $isLegacyMatch = hash_equals($storedHash, $inputPassword);
    }
    
    return $isLegacyMatch;
}

// Update password hash if needed
function updatePasswordHash($userId, $password, $pdo) {
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newHash, $userId]);
        return true;
    } catch (PDOException $e) {
        error_log("Password upgrade failed for user $userId: " . $e->getMessage());
        return false;
    }
}

// Load role-specific session data with optimized queries
function loadRoleSpecificData($pdo, $user) {
    try {
        switch ($user['role']) {
            case 'student':
                error_log("Loading student data for user_id {$user['id']}");
                $stmt = $pdo->prepare("
                    SELECT s.id, s.reg_no, s.year_level, o.name as option_name, d.name as department_name,
                           u.first_name, u.last_name, u.phone, u.sex as gender, u.photo, u.dob as date_of_birth
                    FROM students s
                    LEFT JOIN options o ON s.option_id = o.id
                    LEFT JOIN departments d ON o.department_id = d.id
                    INNER JOIN users u ON s.user_id = u.id
                    WHERE s.user_id = ? AND u.status = 'active' LIMIT 1
                ");
                $stmt->execute([$user['id']]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($data) {
                    error_log("Student data loaded successfully for user_id {$user['id']}: " . json_encode($data));
                    $_SESSION['student_id'] = (int)$data['id'];
                    $_SESSION['reg_no'] = $data['reg_no'];
                    $_SESSION['year_level'] = $data['year_level'];
                    $_SESSION['option_name'] = $data['option_name'];
                    $_SESSION['department_name'] = $data['department_name'];
                    // Set personal info
                    setPersonalInfo($data);
                    return "students-dashboard.php";
                } else {
                    error_log("No student data found for user_id {$user['id']}. Allowing login with limited access.");
                    // Allow login even without student record - dashboard will handle notification
                    $_SESSION['student_id'] = 0;
                    $_SESSION['reg_no'] = 'Not Registered';
                    $_SESSION['year_level'] = 'N/A';
                    $_SESSION['option_name'] = 'Not Assigned';
                    $_SESSION['department_name'] = 'Not Assigned';
                    // Set basic personal info from users table
                    setPersonalInfo([
                        'first_name' => $user['first_name'] ?? null,
                        'last_name' => $user['last_name'] ?? null,
                        'phone' => null,
                        'gender' => null,
                        'photo' => null,
                        'date_of_birth' => null
                    ]);
                    return "students-dashboard.php";
                }
                break;

            case 'lecturer':
            case 'hod':
                $stmt = $pdo->prepare("
                    SELECT l.id, l.id_number, l.education_level,
                           d.id as department_id, d.name as department_name,
                           u.first_name, u.last_name, u.phone, l.gender as gender, u.photo, u.dob as date_of_birth
                    FROM lecturers l
                    LEFT JOIN departments d ON l.department_id = d.id
                    INNER JOIN users u ON l.user_id = u.id
                    WHERE l.user_id = ? LIMIT 1
                ");
                $stmt->execute([$user['id']]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($data) {
                    $_SESSION['lecturer_id'] = (int)$data['id'];
                    $_SESSION['department_id'] = (int)$data['department_id'];
                    $_SESSION['department_name'] = $data['department_name'];
                    $_SESSION['id_number'] = $data['id_number'];
                    $_SESSION['education_level'] = $data['education_level'];
                    // Set personal info
                    setPersonalInfo($data);
                    return ($user['role'] === 'hod') ? "hod-dashboard.php" : "lecturer-dashboard.php";
                }
                break;

            case 'admin':
            case 'tech':
                $stmt = $pdo->prepare("
                    SELECT first_name, last_name, phone, sex as gender, photo, dob as date_of_birth
                    FROM users WHERE id = ? LIMIT 1
                ");
                $stmt->execute([$user['id']]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($data) {
                    setPersonalInfo($data);
                    return ($user['role'] === 'admin') ? "admin-dashboard.php" : "tech-dashboard.php";
                }
                break;
        }

        // Log missing profile for debugging
        error_log("Profile missing for user {$user['username']} (ID: {$user['id']}), role: {$user['role']}");
        // For students, allow login even without student record - dashboard will handle notification
        if ($user['role'] === 'student') {
            error_log("Allowing student login without student record for user {$user['username']}");
            $_SESSION['student_id'] = 0;
            $_SESSION['reg_no'] = 'Not Registered';
            $_SESSION['year_level'] = 'N/A';
            $_SESSION['option_name'] = 'Not Assigned';
            $_SESSION['department_name'] = 'Not Assigned';
            setPersonalInfo([
                'first_name' => $user['first_name'] ?? null,
                'last_name' => $user['last_name'] ?? null,
                'phone' => null,
                'gender' => null,
                'photo' => null,
                'date_of_birth' => null
            ]);
            return "students-dashboard.php";
        }
        return false;

    } catch (Throwable $t) {
        error_log("Error loading role-specific data for user {$user['id']}: " . $t->getMessage());
        return false;
    }
}

// Helper function to set personal information in session
function setPersonalInfo($data) {
    $_SESSION['first_name'] = $data['first_name'] ?? null;
    $_SESSION['last_name'] = $data['last_name'] ?? null;
    $_SESSION['phone'] = $data['phone'] ?? null;
    $_SESSION['gender'] = $data['gender'] ?? null;
    $_SESSION['photo'] = $data['photo'] ?? null;
    $_SESSION['date_of_birth'] = $data['date_of_birth'] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token first
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token invalid. Please refresh the page and try again.";
        error_log("CSRF token validation failed for IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    } else {
        $role = sanitizeInput($_POST['role'] ?? '');
        $emailOrUsername = sanitizeInput($_POST['email'] ?? '');
        $password = $_POST['password'] ?? ''; // Don't sanitize password
        $remember_me = isset($_POST['remember_me']);

        // Enhanced input validation
        if (empty($role) || empty($emailOrUsername) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!in_array($role, ['admin', 'lecturer', 'student', 'hod', 'tech'])) {
            $error = "Invalid role selected.";
            error_log("Invalid role attempted: $role from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        } elseif (strlen($emailOrUsername) > 100) {
            $error = "Email/username is too long.";
        } elseif (!checkRateLimit($emailOrUsername)) {
            $error = "Too many login attempts. Please try again in 15 minutes.";
        } else {
            try {
                // Single optimized query with prepared statement
                $stmt = $pdo->prepare("
                    SELECT id, username, email, password, role, status, last_login 
                    FROM users 
                    WHERE (email = ? OR username = ?) 
                    AND role = ? 
                    AND status = 'active' 
                    LIMIT 1
                ");
                $stmt->execute([$emailOrUsername, $emailOrUsername, $role]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    error_log("Login failed: User not found for email/username: $emailOrUsername, role: $role from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

                    // Check if user exists with different role or inactive status
                    $checkUser = $pdo->prepare("SELECT id, role, status FROM users WHERE (email = ? OR username = ?) LIMIT 1");
                    $checkUser->execute([$emailOrUsername, $emailOrUsername]);
                    $existingUser = $checkUser->fetch(PDO::FETCH_ASSOC);

                    if ($existingUser) {
                        if ($existingUser['status'] !== 'active') {
                            $error = "Your account is currently inactive. Please contact support for assistance.";
                        } else {
                            $error = "Account found but role mismatch. Please select the correct role: " . htmlspecialchars(ucfirst($existingUser['role']));
                        }
                    } else {
                        // Generic error message to prevent user enumeration
                        $error = "Invalid credentials. Please check your email/username, password, and role.";
                    }
                } else {
                    $isAuthenticated = verifyPassword($password, $user['password']);

                    if ($isAuthenticated) {
                        // Clear rate limiting on successful login
                        $identifier = strtolower(trim($emailOrUsername));
                        $key = 'login_attempts_' . md5($identifier . '_' . ($_SERVER['REMOTE_ADDR'] ?? ''));
                        $globalKey = 'global_login_attempts_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
                        unset($_SESSION[$key], $_SESSION[$globalKey]);

                        // Update last login timestamp and upgrade password hash if needed
                        try {
                            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                            $updateStmt->execute([$user['id']]);
                            
                            // Upgrade legacy passwords to secure hashes
                            $storedHash = trim($user['password']);
                            if (password_needs_rehash($storedHash, PASSWORD_DEFAULT) || 
                                (strlen($storedHash) <= 255 && !preg_match('/^\$2[ayb]\$.{56}$/', $storedHash) && !preg_match('/^\$argon2id\$/', $storedHash))) {
                                updatePasswordHash($user['id'], $password, $pdo);
                            }
                        } catch (PDOException $e) {
                            error_log("Failed to update last login for user {$user['id']}: " . $e->getMessage());
                        }

                        // Log successful authentication
                        error_log("Successful login: User {$user['username']} ({$user['role']}) from IP {$_SERVER['REMOTE_ADDR']}");

                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';

                        // Set role-specific IDs and validate relationships
                        $redirect_url = '';
                        $role_valid = true;

                        // Load role-specific session data using optimized queries
                        $redirect_url = loadRoleSpecificData($pdo, $user);
                        if (!$redirect_url) {
                            error_log("Login failed: loadRoleSpecificData returned false for user {$user['id']} with role {$user['role']}");
                            $error = "Unable to load user profile. Please contact support.";
                            $role_valid = false;
                        }

                        // Only redirect if role validation passed
                        if (!$role_valid) {
                            // Clear any session data that might have been set
                            session_unset();
                            session_destroy();
                            session_start();
                            error_log("Login failed: Role validation failed for user {$user['username']} with role {$user['role']}");
                        }

                        if ($role_valid && $redirect_url && empty($error)) {
                            // Regenerate session ID for security
                            session_regenerate_id(true);

                            // Set secure cookie parameters if remember me is checked
                            if ($remember_me) {
                                $sessionParams = session_get_cookie_params();
                                setcookie(
                                    session_name(), 
                                    session_id(), 
                                    time() + 86400 * 30, // 30 days
                                    $sessionParams["path"], 
                                    $sessionParams["domain"], 
                                    true, // secure
                                    true  // httponly
                                );
                            }

                            // Redirect immediately
                            header("Location: $redirect_url");
                            // echo ($_SESSION['user_id'] ? 'User ID: ' . $_SESSION['user_id'] . ' logged in successfully. Redirecting...' : 'Login successful. Redirecting...');
                            // echo ("Redirecting user {$user['username']} to $redirect_url");
                            // error_log("Redirecting user {$user['username']} to $redirect_url");
                            exit;
                        }
                    } else {
                        error_log("Login failed: Authentication failed for user: " . ($user['username'] ?? 'unknown') . ", role: $role from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                        // Generic error message to prevent user enumeration
                        $error = "Invalid credentials. Please check your email/username, password, and role.";
                    }
                }
            } catch (PDOException $e) {
                error_log("Database error during login: " . $e->getMessage());
                $error = "System temporarily unavailable. Please try again later.";
            } catch (Throwable $t) {
                error_log("Unexpected error during login: " . $t->getMessage());
                $error = "An unexpected error occurred. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | RP Attendance System</title>

  <!-- Preload critical resources -->
  <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
  
  <!-- Fallback for preload -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
      --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --success-color: #28a745;
      --danger-color: #dc3545;
      --warning-color: #ffc107;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: var(--primary-gradient);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding-top: 70px;
      position: relative;
      overflow-x: hidden;
      margin: 0;
    }

    .login-box {
      background: rgba(255, 255, 255, 0.98);
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
      width: 100%;
      max-width: 400px;
      margin: 2rem 1rem;
      border: 1px solid rgba(255, 255, 255, 0.3);
      position: relative;
      animation: fadeIn 0.5s ease-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .logo-container {
      position: relative;
      margin-bottom: 1.5rem;
      display: inline-block;
    }

    .logo-container img {
      height: 60px;
      width: auto;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .logo-container:hover img {
      transform: scale(1.05) rotate(1deg);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .logo-glow {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, rgba(0, 102, 204, 0.2), rgba(102, 126, 234, 0.2));
      border-radius: 50%;
      filter: blur(20px);
      opacity: 0.6;
      z-index: -1;
      animation: pulse 3s ease-in-out infinite;
    }

    .login-box::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--secondary-gradient);
      border-radius: 16px 16px 0 0;
    }

    .form-control, .form-select {
      border-radius: 10px;
      padding: 0.75rem 1rem;
      border: 2px solid #e9ecef;
      transition: all 0.2s ease;
      font-size: 0.95rem;
    }

    .form-control:focus, .form-select:focus {
      border-color: #0066cc;
      box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.15);
    }

    .btn-login {
      border-radius: 10px;
      background: var(--secondary-gradient);
      border: none;
      padding: 0.75rem 1.5rem;
      font-weight: 600;
      font-size: 1rem;
      transition: all 0.2s ease;
      position: relative;
      overflow: hidden;
    }

    .btn-login:hover {
      transform: translateY(-1px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .password-toggle {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #6c757d;
      cursor: pointer;
      z-index: 3;
      transition: color 0.2s;
    }

    .password-toggle:hover {
      color: #0066cc;
    }

    /* Optimized loading animation */
    .btn-loading {
      position: relative;
      color: transparent !important;
      pointer-events: none;
    }

    .btn-loading::after {
      content: '';
      position: absolute;
      width: 18px;
      height: 18px;
      top: 50%;
      left: 50%;
      margin: -9px 0 0 -9px;
      border: 2px solid transparent;
      border-top: 2px solid #ffffff;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    @keyframes pulse {
      0%, 100% { opacity: 0.4; transform: scale(1); }
      50% { opacity: 0.8; transform: scale(1.05); }
    }

    /* Critical CSS above the fold */
    .initial-loading {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: var(--primary-gradient);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 9999;
      transition: opacity 0.3s ease;
    }

    .loading-spinner {
      width: 40px;
      height: 40px;
      border: 3px solid rgba(255,255,255,0.3);
      border-top: 3px solid #fff;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    .alert {
      border-radius: 10px;
      border: none;
      animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .footer {
      width: 100%;
      background: rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(10px);
      margin-top: auto;
    }

    .navbar {
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .form-check-input:checked {
      background-color: #0066cc;
      border-color: #0066cc;
    }

    /* Enhanced form validation styles */
    .was-validated .form-control:invalid,
    .was-validated .form-select:invalid {
      border-color: var(--danger-color);
    }

    .was-validated .form-control:valid,
    .was-validated .form-select:valid {
      border-color: var(--success-color);
    }

    .invalid-feedback {
      display: none;
      width: 100%;
      margin-top: 0.25rem;
      font-size: 0.875em;
      color: var(--danger-color);
    }

    .was-validated .form-control:invalid ~ .invalid-feedback,
    .was-validated .form-select:invalid ~ .invalid-feedback {
      display: block;
    }

    @media (max-width: 576px) {
      .login-box {
        padding: 1.5rem;
        margin: 1rem 0.5rem;
      }
      
      body {
        padding-top: 60px;
      }
      
      .navbar-brand span {
        font-size: 1rem;
      }
    }

    /* Accessibility improvements */
    @media (prefers-reduced-motion: reduce) {
      * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
      }
    }

    /* Focus styles for better accessibility */
    .btn:focus,
    .form-control:focus,
    .form-select:focus,
    .form-check-input:focus {
      box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
    }
  </style>
</head>
<body>

  <!-- Loading screen for initial load -->
  <div class="initial-loading" id="initialLoading">
    <div class="loading-spinner"></div>
  </div>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
    <div class="container">
      <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
        <i class="fas fa-fingerprint me-2"></i>
        <span>RP Attendance System</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item">
            <a class="nav-link" href="index.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#features">Features</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#about">About Us</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#contact">Contact Us</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Login Box -->
  <div class="login-box">
    <div class="text-center mb-4">
      <div class="logo-container">
        <div class="logo-glow"></div>
        <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" onerror="this.style.display='none'" />
      </div>
      <h4 class="fw-bold text-dark mb-2">Welcome Back</h4>
      <p class="text-muted small">Rwanda Polytechnic Attendance System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <div class="small"><?php echo htmlspecialchars($error); ?></div>
      </div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="" id="loginForm" novalidate>
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
      
      <div class="mb-3">
        <label for="role" class="form-label fw-semibold text-dark">Select Your Role</label>
        <select id="role" name="role" class="form-select" required>
          <option value="" disabled selected>-- Choose your role --</option>
          <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
          <option value="lecturer" <?php echo (isset($_POST['role']) && $_POST['role'] === 'lecturer') ? 'selected' : ''; ?>>Lecturer</option>
          <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>Student</option>
          <option value="hod" <?php echo (isset($_POST['role']) && $_POST['role'] === 'hod') ? 'selected' : ''; ?>>Head of Department</option>
          <option value="tech" <?php echo (isset($_POST['role']) && $_POST['role'] === 'tech') ? 'selected' : ''; ?>>Technical Staff</option>
        </select>
        <div class="invalid-feedback">
          Please select your role.
        </div>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Email or Username</label>
        <input type="text" class="form-control" id="email" name="email"
               value="<?php echo htmlspecialchars($emailOrUsername); ?>"
               placeholder="Enter your email or username" required>
        <div class="invalid-feedback">
          Please enter your email or username.
        </div>
      </div>

      <div class="mb-3 position-relative">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password"
               placeholder="Enter your password" required minlength="1">
        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
          <i class="fas fa-eye"></i>
        </button>
        <div class="invalid-feedback">
          Please enter your password.
        </div>
      </div>

      <div class="mb-4">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="remember_me" id="rememberMe">
          <label class="form-check-label" for="rememberMe">
            Remember me
          </label>
        </div>
      </div>

      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-login text-white py-2" id="loginBtn">
          <i class="fas fa-sign-in-alt me-2"></i>Sign In
        </button>
      </div>

      <div class="text-center">
        <a href="forgot-password.php" class="text-decoration-none text-primary small">
          Forgot Password?
        </a>
      </div>
    </form>
  </div>

  <!-- Footer -->
  <div class="footer text-white text-center mt-auto py-3">
    &copy; 2025 Rwanda Polytechnic | All rights reserved
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Optimized JavaScript -->
  <script>
    // Hide loading screen when page loads
    window.addEventListener('load', function() {
      const loading = document.getElementById('initialLoading');
      if (loading) {
        loading.style.opacity = '0';
        setTimeout(() => {
          loading.style.display = 'none';
        }, 300);
      }
    });

    // Hide loading screen after max 3 seconds (fallback)
    setTimeout(() => {
      const loading = document.getElementById('initialLoading');
      if (loading && loading.style.display !== 'none') {
        loading.style.opacity = '0';
        setTimeout(() => {
          loading.style.display = 'none';
        }, 300);
      }
    }, 3000);

    // Password toggle functionality
    document.getElementById('togglePassword')?.addEventListener('click', function() {
      const passwordInput = document.getElementById('password');
      const icon = this.querySelector('i');
      const isPassword = passwordInput.type === 'password';
      
      passwordInput.type = isPassword ? 'text' : 'password';
      icon.className = isPassword ? 'fas fa-eye-slash' : 'fas fa-eye';
      this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });

    // Enhanced form validation and submission
    document.getElementById('loginForm')?.addEventListener('submit', function(e) {
      const form = this;
      const loginBtn = document.getElementById('loginBtn');
      
      // Check form validity
      if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        form.classList.add('was-validated');
        
        // Focus on first invalid field
        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
          firstInvalid.focus();
        }
      } else if (loginBtn) {
        // Show loading state
        loginBtn.classList.add('btn-loading');
        loginBtn.disabled = true;
        
        // Add a small delay to show the loading state
        setTimeout(() => {
          if (!form.classList.contains('was-validated')) {
            form.submit();
          }
        }, 500);
      }
    });

    // Auto-focus on email field
    document.addEventListener('DOMContentLoaded', function() {
      const emailField = document.getElementById('email');
      if (emailField && !emailField.value) {
        setTimeout(() => emailField.focus(), 100);
      }
    });

    // Real-time validation for better UX
    const inputs = document.querySelectorAll('#loginForm input, #loginForm select');
    inputs.forEach(input => {
      input.addEventListener('blur', function() {
        if (this.classList.contains('is-invalid') || this.value.trim() === '') {
          this.classList.add('is-invalid');
        } else {
          this.classList.remove('is-invalid');
        }
      });
      
      input.addEventListener('input', function() {
        if (this.checkValidity()) {
          this.classList.remove('is-invalid');
        }
      });
    });

    // Enhanced accessibility - handle Enter key on form elements
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && e.target.tagName !== 'BUTTON' && e.target.type !== 'submit') {
        const form = document.getElementById('loginForm');
        if (form && form.contains(e.target)) {
          e.preventDefault();
          const submitBtn = form.querySelector('button[type="submit"]');
          if (submitBtn) {
            submitBtn.click();
          }
        }
      }
    });
  </script>

</body>
</html>