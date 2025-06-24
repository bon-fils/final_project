<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Forgot Password | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #0066cc, #003366);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .container-wrapper {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 15px;
    }

    .forgot-box {
      background: #fff;
      border-radius: 12px;
      padding: 35px 30px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 400px;
    }

    .btn-primary {
      background-color: #0066cc;
      border: none;
      border-radius: 8px;
    }

    .btn-primary:hover {
      background-color: #004b99;
    }

    .form-icon {
      font-size: 2.5rem;
      color: #0066cc;
      margin-bottom: 15px;
    }

    .footer {
      text-align: center;
      color: #ffffffcc;
      font-size: 0.9rem;
      padding: 20px 0;
      background: transparent;
    }
  </style>
</head>
<body>

<!-- Optional Navbar (if you want consistency) -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="index.php">
      <i class="fas fa-fingerprint me-2"></i>RP Attendance System
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarForgot">
      <span class="navbar-toggler-icon"></span>
    </button>
  </div>
</nav>

<div class="container-wrapper">
  <div class="forgot-box">
    <div class="text-center">
      <div class="form-icon">
        <i class="fas fa-unlock-alt"></i>
      </div>
      <h4 class="fw-bold">Forgot Password</h4>
      <p class="text-muted small mb-4">Enter your registered email to receive reset instructions.</p>
    </div>

    <form>
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" id="email" class="form-control" placeholder="you@example.com" required />
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-primary">
          <i class="fas fa-envelope me-2"></i>Send Reset Link
        </button>
      </div>
    </form>

    <div class="text-center mt-4">
      <a href="login.php" class="text-decoration-none text-primary">&larr; Back to Login</a>
    </div>
  </div>
</div>

<div class="footer">
  &copy; 2025 Rwanda Polytechnic | All rights reserved
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
