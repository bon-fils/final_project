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
    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f8f9fa;
    }

    .hero {
      background: linear-gradient(to right, #0066cc, #003366);
      color: white;
      padding: 80px 20px;
      text-align: center;
    }

    .icon-card {
      background: white;
      padding: 30px 20px;
      border-radius: 12px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }

    .icon-card:hover {
      transform: translateY(-5px);
    }

    .icon-card i {
      font-size: 2.5rem;
      color: #0066cc;
      margin-bottom: 15px;
    }

    .btn {
      transition: all 0.3s ease;
    }

    .footer {
      background: #002b50;
      color: #ffffff;
      padding: 20px;
      text-align: center;
    }

    @media (max-width: 768px) {
      .hero h1 {
        font-size: 1.75rem;
      }
    }
  </style>
</head>

<body>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
      <a class="navbar-brand fw-bold d-flex align-items-center" href="#">
        <img src="RP_Logo.jpeg" alt="RP Logo" style="height: 40px; width: auto; margin-right: 10px;" />
        RP Attendance System
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarMain">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
          <li class="nav-item"><a class="nav-link" href="#contact">Contact Us</a></li>
        </ul>
        <a href="login.php" class="btn btn-light ms-3">
          <i class="fas fa-sign-in-alt me-1"></i> Login
        </a>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero" data-aos="fade-up">
    <div class="container">
      <h1 class="display-5 fw-bold">Welcome to Rwanda Polytechnic Biometric Attendance System</h1>
      <p class="lead mt-3">Track student attendance with face recognition and fingerprint fallback</p>
      <a href="login.php" class="btn btn-outline-light btn-lg mt-4">
        <i class="fas fa-right-to-bracket me-2"></i>Login to Continue
      </a>
    </div>
  </section>

  <!-- Who Can Use This -->
  <section class="py-5" id="features">
    <div class="container">
      <h3 class="text-center mb-4 fw-semibold" data-aos="fade-up">Who Can Use This System?</h3>
      <div class="row g-4 justify-content-center">

        <div class="col-6 col-md-4 col-lg-2 icon-card text-center" data-aos="fade-right">
          <i class="fas fa-user-shield"></i>
          <h6 class="fw-bold mt-2">Admin</h6>
          <p class="small">Register users & manage setup</p>
        </div>

        <div class="col-6 col-md-4 col-lg-2 icon-card text-center" data-aos="fade-up">
          <i class="fas fa-chalkboard-teacher"></i>
          <h6 class="fw-bold mt-2">Lecturer</h6>
          <p class="small">Start attendance sessions</p>
        </div>

        <div class="col-6 col-md-4 col-lg-2 icon-card text-center" data-aos="fade-down">
          <i class="fas fa-user-graduate"></i>
          <h6 class="fw-bold mt-2">Student</h6>
          <p class="small">Check attendance & request leave</p>
        </div>

        <div class="col-6 col-md-4 col-lg-2 icon-card text-center" data-aos="fade-up">
          <i class="fas fa-user-tie"></i>
          <h6 class="fw-bold mt-2">HoD</h6>
          <p class="small">Approve leave, view reports</p>
        </div>

        <div class="col-6 col-md-4 col-lg-2 icon-card text-center" data-aos="fade-left">
          <i class="fas fa-tools"></i>
          <h6 class="fw-bold mt-2">Tech Staff</h6>
          <p class="small">Setup webcam & fingerprint</p>
        </div>
      </div>
    </div>
  </section>

  <!-- About Section -->
  <section class="bg-light py-5" id="about">
    <div class="container text-center" data-aos="fade-up">
      <h3 class="mb-4 fw-semibold">About This System</h3>
      <p class="lead">
        This platform is developed for Rwanda Polytechnic to ensure secure and accurate student attendance using facial recognition and fingerprint technology. It supports leave management, real-time session monitoring, and detailed departmental reporting.
      </p>
    </div>
  </section>

  <!-- Contact Section -->
  <section class="py-5" id="contact">
    <div class="container text-center" data-aos="fade-up">
      <h3 class="mb-3 fw-semibold">Contact Us</h3>
      <p class="mb-1">Need help? Email us at <a href="mailto:it@rp.ac.rw">it@rp.ac.rw</a></p>
      <p class="small text-muted">For login issues, device support, or account registration.</p>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2025 Rwanda Polytechnic | Biometric Attendance System v1.0</p>
  </footer>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.n et/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init();
  </script>

</body>

</html>