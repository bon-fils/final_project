<?php
// login.php
session_start();
require 'config.php'; // contains $pdo connection

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $emailOrUsername = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($role && $emailOrUsername && $password) {
        try {
            // Query by email OR username and role
            $stmt = $pdo->prepare("
                SELECT * FROM users
                WHERE (email = :email OR username = :username)
                  AND role = :role
                  AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([
                'email' => $emailOrUsername,
                'username' => $emailOrUsername,
                'role' => $role
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                error_log("Login failed: User not found for email/username: $emailOrUsername, role: $role");
            }

            $isAuthenticated = false;

            if ($user) {
                $storedPassword = (string)($user['password'] ?? '');

                // If stored is a hash, verify securely; otherwise fall back to plain and upgrade to hash-on-login
                $looksHashed = (strpos($storedPassword, '$2y$') === 0) || (strpos($storedPassword, '$argon2') === 0);

                if ($looksHashed) {
                    $isAuthenticated = password_verify($password, $storedPassword);
                    // Rehash if needed (algorithm cost changes, etc.)
                    if ($isAuthenticated && password_needs_rehash($storedPassword, PASSWORD_BCRYPT)) {
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                        $upd->execute(['p' => $newHash, 'id' => $user['id']]);
                    }
                } else {
                    // Legacy plaintext support: if matches, upgrade to hash
                    if (hash_equals($storedPassword, $password)) {
                        $isAuthenticated = true;
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
                        $upd->execute(['p' => $newHash, 'id' => $user['id']]);
                        // If there is a duplicate password column for students, try to update too (best-effort)
                        if ($user['role'] === 'student') {
                            try {
                                $updS = $pdo->prepare("UPDATE students SET password = :p WHERE user_id = :uid");
                                $updS->execute(['p' => $newHash, 'uid' => $user['id']]);
                            } catch (Throwable $t) {
                                // ignore silently; column may not exist
                            }
                        }
                    }
                }
            }

            if ($isAuthenticated) {
                // Update last login timestamp
                try {
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->execute([$user['id']]);
                } catch (Throwable $t) {
                    // Ignore if update fails
                }

                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role']; // Changed from 'role' to 'user_role' to match validation
                $_SESSION['role']     = $user['role']; // Keep for backward compatibility

                // Initialize required session keys for session integrity validation
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['login_time'] = time();

                // Set role-specific IDs for consistency across pages
                switch ($user['role']) {
                    case 'student':
                        try {
                            $s = $pdo->prepare("SELECT id FROM students WHERE user_id = ? LIMIT 1");
                            $s->execute([$user['id']]);
                            $stu = $s->fetch(PDO::FETCH_ASSOC);
                            if (!empty($stu['id'])) {
                                $_SESSION['student_id'] = (int)$stu['id'];
                            }
                        } catch (Throwable $t) {}
                        header("Location: students-dashboard.php");
                        exit;
                    case 'lecturer':
                        try {
                            $l = $pdo->prepare("SELECT id FROM lecturers WHERE email = ? LIMIT 1");
                            $l->execute([$user['email']]);
                            $lec = $l->fetch(PDO::FETCH_ASSOC);
                            if (!empty($lec['id'])) {
                                $_SESSION['lecturer_id'] = (int)$lec['id'];
                            }
                        } catch (Throwable $t) {}
                        error_log("Redirecting lecturer to dashboard: " . $user['username']);
                        header("Location: lecturer-dashboard.php");
                        exit;
                    case 'admin':
                        header("Location: admin-dashboard.php");
                        exit;
                    case 'hod':
                        header("Location: hod-dashboard.php");
                        exit;
                    case 'tech':
                        header("Location: tech-dashboard.php");
                        exit;
                    default:
                        $error = "Role not recognized.";
                }
            } else {
                error_log("Login failed: Authentication failed for user: " . ($user['username'] ?? 'unknown') . ", role: $role");
                $error = "Invalid email/username, password, or role.";
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again later.";
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <!-- AOS Animations -->
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #0066cc, #003366);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding-top: 70px;
    }
    .login-box {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 12px;
      padding: 40px 30px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
      border: 1px solid rgba(255, 255, 255, 0.2);
      width: 100%;
      max-width: 400px;
      margin: 30px 15px 60px;
      position: relative;
      overflow: hidden;
    }

    .login-box::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary-gradient);
    }
    .form-control, .form-select { border-radius: 8px; }
    .btn-primary {
      border-radius: 8px;
      background-color: #0066cc;
      border: none;
    }
    .btn-primary:hover { background-color: #004b99; }
    .form-icon { color: #0066cc; font-size: 2rem; margin-bottom: 15px; }
    .footer { text-align: center; color: #ffffffbb; font-size: 0.9rem; margin-bottom: 15px; }
    @media (max-width: 576px) { .login-box { padding: 30px 20px; } }
  </style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top" style="background: linear-gradient(135deg, #0066cc 0%, #003366 100%) !important; backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
    <div class="container">
      <a class="navbar-brand fw-bold" href="index.php" style="color: white !important;">
        <i class="fas fa-fingerprint me-2"></i>RP Attendance System
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarLogin" aria-controls="navbarLogin" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarLogin">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="index.php" style="color: rgba(255, 255, 255, 0.8) !important; font-weight: 500;">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#features" style="color: rgba(255, 255, 255, 0.8) !important; font-weight: 500;">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#about" style="color: rgba(255, 255, 255, 0.8) !important; font-weight: 500;">About Us</a></li>
          <li class="nav-item"><a class="nav-link" href="index.php#contact" style="color: rgba(255, 255, 255, 0.8) !important; font-weight: 500;">Contact Us</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Login Box -->
  <div class="login-box" data-aos="zoom-in">
    <div class="text-center mb-4">
      <img src="RP_Logo.jpeg" alt="RP Logo" style="height: 60px; width: auto; margin-bottom: 15px;" />
      <h4 class="fw-bold">Login to Your Dashboard</h4>
      <p class="text-muted small">Rwanda Polytechnic Attendance System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger text-center"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Login Form -->
    <form method="POST" action="">
      <div class="mb-3 text-center">
        <label for="role" class="form-label fw-semibold">Select Role</label>
        <select id="role" name="role" class="form-select" required>
          <option value="" disabled selected>-- Choose your role --</option>
          <option value="admin">Admin</option>
          <option value="lecturer">Lecturer</option>
          <option value="student">Student</option>
          <option value="hod">Head of Department (HoD)</option>
          <option value="tech">Technical Staff</option>
        </select>
      </div>

      <div class="mb-3">
        <label for="email" class="form-label">Email or Username</label>
        <input type="text" class="form-control" id="email" name="email" placeholder="Enter your email or username" required>
      </div>

      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
      </div>

      <div class="mb-3 form-check">
        <input type="checkbox" class="form-check-input" id="rememberMe">
        <label class="form-check-label" for="rememberMe">Remember Me</label>
      </div>

      <div class="d-grid mb-3">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-sign-in-alt me-2"></i>Login
        </button>
      </div>

      <div class="text-center">
        <a href="forgot-password.php" class="small text-decoration-none text-primary">Forgot Password?</a>
      </div>
    </form>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | All rights reserved
  </div>

  <!-- Bootstrap + AOS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init();
  </script>

</body>
</html>
