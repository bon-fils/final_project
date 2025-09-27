<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>RP Biometric Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <!-- AOS Animations -->
  <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet" />

  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
      --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      --info-gradient: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
      --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
      --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
      --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
      --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
      --border-radius: 12px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #0066cc, #003366);
      min-height: 100vh;
      margin: 0;
      position: relative;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>');
      pointer-events: none;
      z-index: -1;
    }

    .hero {
      background: var(--primary-gradient);
      color: white;
      padding: 80px 20px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .hero::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(1px);
    }

    .hero-content {
      position: relative;
      z-index: 2;
    }

    .icon-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      padding: 30px 20px;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow-light);
      border: 1px solid rgba(255, 255, 255, 0.2);
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .icon-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary-gradient);
    }

    .icon-card:hover {
      transform: translateY(-8px) scale(1.02);
      box-shadow: var(--shadow-medium);
    }

    .icon-card i {
      font-size: 2.5rem;
      color: #0066cc;
      margin-bottom: 15px;
      transition: var(--transition);
    }

    .icon-card:hover i {
      transform: scale(1.1);
      color: #003366;
    }

    .btn {
      transition: var(--transition);
      border-radius: 8px;
      font-weight: 600;
      position: relative;
      overflow: hidden;
    }

    .btn::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      transform: translate(-50%, -50%);
      transition: var(--transition);
    }

    .btn:hover::before {
      width: 300px;
      height: 300px;
    }

    .btn-primary {
      background: var(--primary-gradient);
      border: none;
      box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
    }

    .btn-primary:hover {
      background: var(--primary-gradient);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
    }

    .btn-outline-light {
      border: 2px solid rgba(255, 255, 255, 0.8);
      color: white;
    }

    .btn-outline-light:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: white;
      color: white;
      transform: translateY(-2px);
    }

    .footer {
      background: rgba(0, 43, 80, 0.9);
      backdrop-filter: blur(10px);
      color: #ffffff;
      padding: 20px;
      text-align: center;
      border-top: 1px solid rgba(255, 255, 255, 0.2);
      position: relative;
      z-index: 2;
    }

    .section-card {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(10px);
      border-radius: var(--border-radius);
      padding: 40px;
      box-shadow: var(--shadow-light);
      border: 1px solid rgba(255, 255, 255, 0.2);
      margin-bottom: 30px;
    }

    @media (max-width: 768px) {
      .hero {
        padding: 60px 15px;
      }

      .hero h1 {
        font-size: 1.75rem;
      }

      .icon-card {
        padding: 25px 15px;
      }

      .section-card {
        padding: 30px 20px;
      }
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .icon-card {
      animation: fadeInUp 0.6s ease-out;
    }

    .icon-card:nth-child(1) { animation-delay: 0.1s; }
    .icon-card:nth-child(2) { animation-delay: 0.2s; }
    .icon-card:nth-child(3) { animation-delay: 0.3s; }
    .icon-card:nth-child(4) { animation-delay: 0.4s; }
    .icon-card:nth-child(5) { animation-delay: 0.5s; }
  </style>
</head>

<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top" style="background: var(--primary-gradient) !important; backdrop-filter: blur(10px); border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
    <div class="container">
      <a class="navbar-brand fw-bold d-flex align-items-center" href="#" style="color: white !important;">
        <img src="RP_Logo.jpeg" alt="RP Logo" style="height: 40px; width: auto; margin-right: 10px;" />
        RP Attendance System
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarMain">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link active" href="#" style="color: white !important; font-weight: 500;">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="#features" style="color: rgba(255, 255, 255, 0.8) !important; font-weight: 500;">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="#about" style="color: rgba(255, 255, 255, 0.8) !important; font-weight: 500;">About Us</a></li>
          <li class="nav-item"><a class="nav-link" href="#contact" style="color: rgba(255, 255, 255, 0.8) !important; font-weight: 500;">Contact Us</a></li>
        </ul>
        <a href="login.php" class="btn ms-3" style="background: rgba(255, 255, 255, 0.9); color: #0066cc; border: none; font-weight: 600; border-radius: 8px;">
          <i class="fas fa-sign-in-alt me-1"></i> Login
        </a>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero" data-aos="fade-up">
    <div class="container">
      <div class="hero-content">
        <h1 class="display-5 fw-bold">Welcome to Rwanda Polytechnic Biometric Attendance System</h1>
        <p class="lead mt-3">Track student attendance with face recognition and fingerprint fallback</p>
        <a href="login.php" class="btn btn-outline-light btn-lg mt-4">
          <i class="fas fa-right-to-bracket me-2"></i>Login to Continue
        </a>
      </div>
    </div>
  </section>

  <!-- Who Can Use This -->
  <section class="py-5" id="features">
    <div class="container">
      <div class="text-center mb-5" data-aos="fade-up">
        <h3 class="fw-semibold" style="color: white; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Who Can Use This System?</h3>
      </div>
      <div class="row g-4 justify-content-center">
        <div class="col-6 col-md-4 col-lg-2" data-aos="fade-right">
          <div class="icon-card text-center">
            <i class="fas fa-user-shield"></i>
            <h6 class="fw-bold mt-2">Admin</h6>
            <p class="small text-muted">Register users & manage setup</p>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2" data-aos="fade-up">
          <div class="icon-card text-center">
            <i class="fas fa-chalkboard-teacher"></i>
            <h6 class="fw-bold mt-2">Lecturer</h6>
            <p class="small text-muted">Start attendance sessions</p>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2" data-aos="fade-down">
          <div class="icon-card text-center">
            <i class="fas fa-user-graduate"></i>
            <h6 class="fw-bold mt-2">Student</h6>
            <p class="small text-muted">Check attendance & request leave</p>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2" data-aos="fade-up">
          <div class="icon-card text-center">
            <i class="fas fa-user-tie"></i>
            <h6 class="fw-bold mt-2">HoD</h6>
            <p class="small text-muted">Approve leave, view reports</p>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2" data-aos="fade-left">
          <div class="icon-card text-center">
            <i class="fas fa-tools"></i>
            <h6 class="fw-bold mt-2">Tech Staff</h6>
            <p class="small text-muted">Setup webcam & fingerprint</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- About Section -->
  <section class="py-5" id="about">
    <div class="container">
      <div class="section-card text-center" data-aos="fade-up">
        <h3 class="mb-4 fw-semibold" style="color: #0066cc;">About This System</h3>
        <p class="lead">
          This platform is developed for Rwanda Polytechnic to ensure secure and accurate student attendance using facial recognition and fingerprint technology. It supports leave management, real-time session monitoring, and detailed departmental reporting.
        </p>
      </div>
    </div>
  </section>

  <!-- Contact Section -->
  <section class="py-5" id="contact">
    <div class="container">
      <div class="section-card text-center" data-aos="fade-up">
        <h3 class="mb-3 fw-semibold" style="color: #0066cc;">Contact Us</h3>
        <p class="mb-1">Need help? Email us at <a href="mailto:it@rp.ac.rw" style="color: #0066cc; text-decoration: none; font-weight: 600;">it@rp.ac.rw</a></p>
        <p class="small text-muted">For login issues, device support, or account registration.</p>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2025 Rwanda Polytechnic | Biometric Attendance System v1.0</p>
  </footer>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init();
  </script>

</body>

</html>