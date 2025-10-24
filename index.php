<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Rwanda Polytechnic Biometric Attendance System - Advanced face recognition and fingerprint technology for secure student attendance tracking." />
  <meta name="keywords" content="biometric attendance, face recognition, fingerprint, Rwanda Polytechnic, student attendance" />
  <meta name="author" content="Rwanda Polytechnic" />
  <meta name="robots" content="index, follow" />
  <meta property="og:title" content="RP Biometric Attendance System" />
  <meta property="og:description" content="Revolutionary attendance tracking with advanced face recognition technology" />
  <meta property="og:type" content="website" />
  <link rel="icon" type="image/x-icon" href="RP_Logo.jpeg" />
  <title>Rwanda Polytechnic - Biometric Attendance System</title>

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
      --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --success-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
      --glass-bg: rgba(255, 255, 255, 0.08);
      --glass-border: rgba(255, 255, 255, 0.12);
      --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
      --text-primary: rgba(255, 255, 255, 0.95);
      --text-secondary: rgba(255, 255, 255, 0.7);
      --text-muted: rgba(255, 255, 255, 0.5);
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      background: linear-gradient(135deg, #0f1f3d 0%, #1a365d 50%, #2d3748 100%);
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
      color: var(--text-primary);
    }

    /* Enhanced animated background */
    .bg-shapes {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      z-index: -2;
      overflow: hidden;
    }

    .bg-shape {
      position: absolute;
      border-radius: 50%;
      background: linear-gradient(45deg, rgba(0, 102, 204, 0.15), rgba(102, 126, 234, 0.1));
      animation: float 12s ease-in-out infinite;
      filter: blur(40px);
    }

    .bg-shape:nth-child(1) {
      width: 300px;
      height: 300px;
      top: 10%;
      left: -5%;
      animation-delay: 0s;
    }

    .bg-shape:nth-child(2) {
      width: 250px;
      height: 250px;
      top: 60%;
      right: -3%;
      animation-delay: 4s;
    }

    .bg-shape:nth-child(3) {
      width: 200px;
      height: 200px;
      bottom: 20%;
      left: 50%;
      animation-delay: 8s;
    }

    /* Subtle grid overlay */
    .grid-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: 
        linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
      background-size: 50px 50px;
      z-index: -1;
    }

    @keyframes float {
      0%, 100% { 
        transform: translateY(0px) rotate(0deg) scale(1);
      }
      33% { 
        transform: translateY(-30px) rotate(120deg) scale(1.05);
      }
      66% { 
        transform: translateY(15px) rotate(240deg) scale(0.95);
      }
    }

    /* Enhanced Navbar with glass effect */
    .navbar {
      background: rgba(15, 31, 61, 0.7) !important;
      backdrop-filter: blur(20px) saturate(180%);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      border-bottom: 1px solid var(--glass-border);
      box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .navbar.scrolled {
      background: rgba(15, 31, 61, 0.9) !important;
      backdrop-filter: blur(25px) saturate(200%);
    }

    .navbar-brand img {
      filter: brightness(0) invert(1);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand:hover img {
      transform: scale(1.05) rotate(2deg);
      filter: brightness(0) invert(1) drop-shadow(0 4px 12px rgba(0, 0, 0, 0.15));
    }

    .nav-link {
      color: var(--text-secondary) !important;
      font-weight: 500;
      padding: 0.5rem 1rem !important;
      margin: 0 0.25rem;
      border-radius: 12px;
      transition: all 0.3s ease;
      position: relative;
    }

    .nav-link:hover,
    .nav-link.active {
      color: var(--text-primary) !important;
      background: rgba(255, 255, 255, 0.1);
    }

    .nav-link::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 50%;
      width: 0;
      height: 2px;
      background: var(--accent-gradient);
      transition: all 0.3s ease;
      transform: translateX(-50%);
    }

    .nav-link:hover::after,
    .nav-link.active::after {
      width: 70%;
    }

    /* Enhanced Hero Section */
    .hero {
      background: linear-gradient(135deg, 
        rgba(0, 51, 102, 0.9) 0%, 
        rgba(15, 31, 61, 0.8) 50%, 
        rgba(26, 54, 93, 0.7) 100%);
      padding: 140px 20px 100px;
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
      background: 
        radial-gradient(circle at 20% 80%, rgba(79, 172, 254, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(0, 242, 254, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 40% 40%, rgba(102, 126, 234, 0.05) 0%, transparent 50%);
      animation: pulse 8s ease-in-out infinite;
    }

    .hero .container {
      position: relative;
      z-index: 2;
    }

    .hero h1 {
      font-size: clamp(2.5rem, 5vw, 4rem);
      font-weight: 800;
      margin-bottom: 1.5rem;
      background: linear-gradient(135deg, #ffffff 0%, #e2e8f0 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      line-height: 1.1;
    }

    .hero .lead {
      font-size: clamp(1.1rem, 2vw, 1.4rem);
      margin-bottom: 2.5rem;
      color: var(--text-secondary);
      line-height: 1.6;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    .hero-buttons {
      display: flex;
      gap: 1rem;
      justify-content: center;
      flex-wrap: wrap;
    }

    .btn-glass {
      padding: 1rem 2.5rem;
      font-size: 1.1rem;
      font-weight: 600;
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      color: var(--text-primary);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .btn-glass::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, 
        transparent, 
        rgba(255, 255, 255, 0.2), 
        transparent);
      transition: left 0.6s;
    }

    .btn-glass:hover {
      background: rgba(255, 255, 255, 0.15);
      border-color: rgba(255, 255, 255, 0.3);
      transform: translateY(-3px);
      box-shadow: 
        0 20px 40px rgba(0, 0, 0, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    }

    .btn-glass:hover::before {
      left: 100%;
    }

    .btn-primary-glass {
      background: linear-gradient(135deg, 
        rgba(79, 172, 254, 0.2) 0%, 
        rgba(0, 242, 254, 0.2) 100%);
      border: 1px solid rgba(79, 172, 254, 0.3);
    }

    .btn-primary-glass:hover {
      background: linear-gradient(135deg, 
        rgba(79, 172, 254, 0.3) 0%, 
        rgba(0, 242, 254, 0.3) 100%);
      border-color: rgba(79, 172, 254, 0.5);
    }

    /* Enhanced Features Section */
    .section-title {
      font-size: clamp(2rem, 4vw, 3rem);
      font-weight: 700;
      margin-bottom: 3rem;
      text-align: center;
      position: relative;
      background: linear-gradient(135deg, #ffffff 0%, #a0aec0 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .section-title::after {
      content: '';
      position: absolute;
      bottom: -15px;
      left: 50%;
      transform: translateX(-50%);
      width: 80px;
      height: 4px;
      background: var(--accent-gradient);
      border-radius: 2px;
    }

    .icon-card {
      background: var(--glass-bg);
      backdrop-filter: blur(20px) saturate(180%);
      -webkit-backdrop-filter: blur(20px) saturate(180%);
      padding: 2.5rem 1.5rem;
      border-radius: 24px;
      border: 1px solid var(--glass-border);
      box-shadow: var(--glass-shadow);
      transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
      text-align: center;
      position: relative;
      overflow: hidden;
      height: 100%;
    }

    .icon-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, 
        rgba(79, 172, 254, 0.05) 0%, 
        rgba(0, 242, 254, 0.05) 100%);
      opacity: 0;
      transition: opacity 0.4s ease;
    }

    .icon-card:hover {
      transform: translateY(-12px) scale(1.02);
      border-color: rgba(79, 172, 254, 0.3);
      box-shadow: 
        0 25px 50px rgba(0, 0, 0, 0.25),
        inset 0 1px 0 rgba(255, 255, 255, 0.1);
    }

    .icon-card:hover::before {
      opacity: 1;
    }

    .icon-card i {
      font-size: 3.5rem;
      background: var(--accent-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      margin-bottom: 1.5rem;
      transition: all 0.4s ease;
      display: inline-block;
    }

    .icon-card:hover i {
      transform: scale(1.15) rotate(5deg);
    }

    .icon-card h6 {
      font-weight: 700;
      color: var(--text-primary);
      margin-bottom: 1rem;
      font-size: 1.1rem;
    }

    .icon-card p {
      color: var(--text-secondary);
      font-size: 0.9rem;
      line-height: 1.5;
      margin: 0;
    }

    /* Enhanced About Section */
    #about {
      background: linear-gradient(135deg, 
        rgba(15, 31, 61, 0.8) 0%, 
        rgba(26, 54, 93, 0.6) 100%);
      padding: 100px 0;
      position: relative;
    }

    #about::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.02)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
    }

    #about .container {
      position: relative;
      z-index: 2;
    }

    #about .lead {
      font-size: 1.3rem;
      line-height: 1.8;
      color: var(--text-secondary);
      margin-bottom: 3rem;
    }

    .feature-highlight {
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      padding: 2rem;
      border-radius: 20px;
      border: 1px solid var(--glass-border);
      text-align: center;
      transition: all 0.3s ease;
      height: 100%;
    }

    .feature-highlight:hover {
      transform: translateY(-5px);
      border-color: rgba(79, 172, 254, 0.3);
    }

    .feature-highlight i {
      font-size: 2.5rem;
      margin-bottom: 1rem;
      display: block;
    }

    /* Enhanced Contact Section */
    #contact {
      background: linear-gradient(135deg, 
        rgba(0, 51, 102, 0.9) 0%, 
        rgba(15, 31, 61, 0.8) 100%);
      padding: 100px 0;
      position: relative;
    }

    .contact-card {
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      padding: 2.5rem;
      border-radius: 20px;
      border: 1px solid var(--glass-border);
      text-align: center;
      transition: all 0.3s ease;
      height: 100%;
    }

    .contact-card:hover {
      transform: translateY(-5px);
      border-color: rgba(79, 172, 254, 0.3);
    }

    /* Enhanced Footer */
    .footer {
      background: linear-gradient(135deg, 
        rgba(15, 23, 42, 0.95) 0%, 
        rgba(30, 41, 59, 0.9) 100%);
      backdrop-filter: blur(20px);
      color: var(--text-primary);
      padding: 3rem 0 1.5rem;
      border-top: 1px solid var(--glass-border);
    }

    .footer::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 1px;
      background: var(--accent-gradient);
    }

    /* Enhanced Loading Animation */
    .loading-overlay {
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
      transition: opacity 0.5s ease, visibility 0.5s ease;
    }

    .loading-overlay.hidden {
      opacity: 0;
      visibility: hidden;
    }

    .spinner {
      width: 60px;
      height: 60px;
      border: 3px solid rgba(255, 255, 255, 0.1);
      border-top: 3px solid var(--text-primary);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.8; }
    }

    /* Scroll to Top Button */
    #scrollToTop {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: var(--glass-bg);
      backdrop-filter: blur(20px);
      border: 1px solid var(--glass-border);
      color: var(--text-primary);
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      box-shadow: var(--glass-shadow);
    }

    #scrollToTop:hover {
      background: var(--glass-bg);
      border-color: rgba(79, 172, 254, 0.3);
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    /* Enhanced Social Links */
    .footer a:hover {
      color: var(--accent-gradient) !important;
      transform: translateY(-2px);
    }

    /* Accessibility Improvements */
    .btn-glass:focus,
    .nav-link:focus,
    #scrollToTop:focus {
      outline: 2px solid var(--accent-gradient);
      outline-offset: 2px;
    }

    /* Print Styles */
    @media print {
      .bg-shapes,
      .grid-overlay,
      .loading-overlay,
      #scrollToTop,
      .navbar {
        display: none !important;
      }

      body {
        background: white !important;
        color: black !important;
      }

      .hero,
      .footer {
        background: white !important;
        color: black !important;
      }
    }

    /* Reduced Motion Support */
    @media (prefers-reduced-motion: reduce) {
      *,
      *::before,
      *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
      }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .hero {
        padding: 120px 20px 80px;
      }

      .hero-buttons {
        flex-direction: column;
        align-items: center;
      }

      .btn-glass {
        width: 100%;
        max-width: 280px;
      }

      .icon-card {
        margin-bottom: 1.5rem;
        padding: 2rem 1rem;
      }

      .footer .row > div {
        text-align: center !important;
        margin-bottom: 2rem;
      }

      #scrollToTop {
        bottom: 1rem;
        right: 1rem;
        width: 45px;
        height: 45px;
      }
    }

    @media (max-width: 576px) {
      .hero {
        padding: 100px 20px 60px;
      }

      .hero h1 {
        font-size: 2.5rem;
      }

      .icon-card {
        padding: 1.5rem 1rem;
      }

      .icon-card i {
        font-size: 2.5rem;
      }

      .contact-card {
        padding: 1.5rem;
      }

      .footer {
        padding: 2rem 0 1rem;
      }
    }
  </style>
</head>

<body id="top">

  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="spinner"></div>
  </div>

  <!-- Background Elements -->
  <div class="bg-shapes">
    <div class="bg-shape"></div>
    <div class="bg-shape"></div>
    <div class="bg-shape"></div>
  </div>
  
  <!-- Grid Overlay -->
  <div class="grid-overlay"></div>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark fixed-top" id="mainNavbar">
    <div class="container">
      <a class="navbar-brand fw-bold d-flex align-items-center" href="#top">
        <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" style="height: 40px; width: auto; margin-right: 12px;" />
        <span class="d-none d-sm-inline">RP Attendance System</span>
        <span class="d-sm-none">RP System</span>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse" id="navbarMain">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link active" href="#top" id="homeLink">Home</a></li>
          <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
          <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
          <li class="nav-item"><a class="nav-link" href="#contact">Contact Us</a></li>
        </ul>
        <a href="login.php" class="btn btn-glass ms-3">
          <i class="fas fa-sign-in-alt me-2"></i> Login
        </a>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero">
    <div class="container">
      <?php 
      // Handle logout messages
      if (isset($_GET['logout'])) {
          $logout_reason = $_GET['logout'];
          echo '<div class="row justify-content-center mb-4">';
          echo '<div class="col-md-8 col-lg-6">';
          
          switch ($logout_reason) {
              case 'success':
                  echo '<div class="alert alert-success alert-dismissible fade show d-flex align-items-center" role="alert" style="background: rgba(40, 167, 69, 0.9); border: 1px solid rgba(40, 167, 69, 0.3); backdrop-filter: blur(10px);">';
                  echo '<i class="fas fa-check-circle me-3 fs-5"></i>';
                  echo '<div><strong>Logout Successful!</strong><br>You have been securely logged out. Thank you for using RP Attendance System.</div>';
                  echo '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>';
                  echo '</div>';
                  break;
              case 'csrf_error':
                  echo '<div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert" style="background: rgba(255, 193, 7, 0.9); border: 1px solid rgba(255, 193, 7, 0.3); backdrop-filter: blur(10px);">';
                  echo '<i class="fas fa-shield-alt me-3 fs-5"></i>';
                  echo '<div><strong>Security Verification!</strong><br>Logout completed with security verification. Please log in again to continue.</div>';
                  echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                  echo '</div>';
                  break;
              default:
                  echo '<div class="alert alert-info alert-dismissible fade show d-flex align-items-center" role="alert" style="background: rgba(13, 110, 253, 0.9); border: 1px solid rgba(13, 110, 253, 0.3); backdrop-filter: blur(10px);">';
                  echo '<i class="fas fa-info-circle me-3 fs-5"></i>';
                  echo '<div><strong>Session Ended!</strong><br>You have been logged out. Please log in to access the system.</div>';
                  echo '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>';
                  echo '</div>';
                  break;
          }
          
          echo '</div>';
          echo '</div>';
      }
      
      // Handle session timeout message
      if (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
          echo '<div class="row justify-content-center mb-4">';
          echo '<div class="col-md-8 col-lg-6">';
          echo '<div class="alert alert-warning alert-dismissible fade show d-flex align-items-center" role="alert" style="background: rgba(255, 193, 7, 0.9); border: 1px solid rgba(255, 193, 7, 0.3); backdrop-filter: blur(10px);">';
          echo '<i class="fas fa-clock me-3 fs-5"></i>';
          echo '<div><strong>Session Expired!</strong><br>Your session has expired for security reasons. Please log in again to continue.</div>';
          echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
          echo '</div>';
          echo '</div>';
          echo '</div>';
      }
      ?>
      
      <h1 class="display-4 fw-bold">Welcome to Rwanda Polytechnic<br><span style="opacity: 0.9;">Biometric Attendance System</span></h1>
      <p class="lead mt-4">Revolutionary attendance tracking with advanced face recognition technology and secure fingerprint fallback for maximum reliability.</p>
      <div class="hero-buttons mt-5">
        <a href="login.php" class="btn btn-glass btn-primary-glass">
          <i class="fas fa-right-to-bracket me-2"></i>Get Started
        </a>
        <a href="#features" class="btn btn-glass">
          <i class="fas fa-info-circle me-2"></i>Learn More
        </a>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section class="py-5" id="features">
    <div class="container">
      <h2 class="section-title">Who Can Use This System?</h2>
      <div class="row g-4 justify-content-center">

        <div class="col-6 col-md-4 col-lg-2">
          <div class="icon-card">
            <i class="fas fa-user-shield"></i>
            <h6 class="fw-bold mt-3">Administrator</h6>
            <p class="small">Manage users, system setup, and configurations</p>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
          <div class="icon-card">
            <i class="fas fa-chalkboard-teacher"></i>
            <h6 class="fw-bold mt-3">Lecturer</h6>
            <p class="small">Start attendance sessions and monitor classes</p>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
          <div class="icon-card">
            <i class="fas fa-user-graduate"></i>
            <h6 class="fw-bold mt-3">Student</h6>
            <p class="small">Check attendance records and request leave</p>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
          <div class="icon-card">
            <i class="fas fa-user-tie"></i>
            <h6 class="fw-bold mt-3">Head of Department</h6>
            <p class="small">Approve leave requests and view reports</p>
          </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
          <div class="icon-card">
            <i class="fas fa-tools"></i>
            <h6 class="fw-bold mt-3">Technical Staff</h6>
            <p class="small">Setup biometric devices and maintenance</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- About Section -->
  <section class="py-5" id="about">
    <div class="container text-center">
      <h2 class="section-title">About This System</h2>
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <p class="lead mb-4">
            Rwanda Polytechnic's cutting-edge biometric attendance system revolutionizes traditional attendance tracking with advanced facial recognition technology and secure fingerprint authentication.
          </p>
          <div class="row g-4 mt-4">
            <div class="col-md-4">
              <div class="feature-highlight">
                <i class="fas fa-shield-alt" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                <h6 class="fw-bold">Secure & Reliable</h6>
                <p class="small">Multi-factor biometric authentication ensures data integrity</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="feature-highlight">
                <i class="fas fa-clock" style="background: var(--success-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                <h6 class="fw-bold">Real-time Monitoring</h6>
                <p class="small">Live attendance tracking with instant notifications</p>
              </div>
            </div>
            <div class="col-md-4">
              <div class="feature-highlight">
                <i class="fas fa-chart-line" style="background: var(--secondary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                <h6 class="fw-bold">Advanced Analytics</h6>
                <p class="small">Comprehensive reporting and performance insights</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Contact Section -->
  <section class="py-5" id="contact">
    <div class="container text-center">
      <h2 class="section-title">Get In Touch</h2>
      <div class="row justify-content-center">
        <div class="col-lg-6">
          <p class="mb-4 lead" style="color: var(--text-secondary);">Need assistance with the system?</p>
          <div class="row g-4">
            <div class="col-md-6">
              <div class="contact-card">
                <i class="fas fa-envelope fa-2x mb-3" style="background: var(--accent-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                <h6 class="fw-bold mb-2">Email Support</h6>
                <a href="mailto:it@rp.ac.rw" class="text-decoration-none" style="color: #4facfe;">it@rp.ac.rw</a>
              </div>
            </div>
            <div class="col-md-6">
              <div class="contact-card">
                <i class="fas fa-phone fa-2x mb-3" style="background: var(--success-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                <h6 class="fw-bold mb-2">Phone Support</h6>
                <p class="mb-0" style="color: var(--text-secondary);">Available 8AM - 5PM</p>
              </div>
            </div>
          </div>
          <p class="mt-4 small" style="color: var(--text-muted);">
            Support for login issues, device setup, account registration, and technical assistance
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Scroll to Top Button -->
  <button id="scrollToTop" class="btn btn-glass position-fixed" style="bottom: 2rem; right: 2rem; z-index: 1000; opacity: 0; visibility: hidden; transition: all 0.3s ease;">
    <i class="fas fa-arrow-up"></i>
  </button>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="row g-4">
        <div class="col-lg-4">
          <h5 class="fw-bold mb-3">Rwanda Polytechnic</h5>
          <p class="mb-3" style="color: var(--text-secondary);">Leading technical education with innovative biometric attendance solutions for the future of education.</p>
          <div class="d-flex gap-3">
            <a href="#" class="text-decoration-none" style="color: var(--text-secondary);" aria-label="Facebook">
              <i class="fab fa-facebook-f fa-lg"></i>
            </a>
            <a href="#" class="text-decoration-none" style="color: var(--text-secondary);" aria-label="Twitter">
              <i class="fab fa-twitter fa-lg"></i>
            </a>
            <a href="#" class="text-decoration-none" style="color: var(--text-secondary);" aria-label="LinkedIn">
              <i class="fab fa-linkedin-in fa-lg"></i>
            </a>
            <a href="#" class="text-decoration-none" style="color: var(--text-secondary);" aria-label="Instagram">
              <i class="fab fa-instagram fa-lg"></i>
            </a>
          </div>
        </div>
        <div class="col-lg-4">
          <h6 class="fw-bold mb-3">Quick Links</h6>
          <ul class="list-unstyled">
            <li class="mb-2"><a href="#features" class="text-decoration-none" style="color: var(--text-secondary);">Features</a></li>
            <li class="mb-2"><a href="#about" class="text-decoration-none" style="color: var(--text-secondary);">About Us</a></li>
            <li class="mb-2"><a href="#contact" class="text-decoration-none" style="color: var(--text-secondary);">Contact</a></li>
            <li class="mb-2"><a href="login.php" class="text-decoration-none" style="color: var(--text-secondary);">Login</a></li>
          </ul>
        </div>
        <div class="col-lg-4 text-lg-end">
          <h6 class="fw-bold mb-3">Biometric Attendance System</h6>
          <p class="mb-2" style="color: var(--text-secondary);">Version 1.0</p>
          <p class="mb-2" style="color: var(--text-secondary);">&copy; 2025 Rwanda Polytechnic</p>
          <p class="mb-0 small" style="color: var(--text-muted);">All Rights Reserved</p>
        </div>
      </div>
      <hr style="border-color: var(--glass-border); margin: 2rem 0;">
      <div class="text-center">
        <p class="mb-0 small" style="color: var(--text-muted);">
          Powered by advanced AI technology for secure and efficient attendance management
        </p>
      </div>
    </div>
  </footer>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Enhanced loading overlay
    window.addEventListener('load', function() {
      const loading = document.getElementById('loadingOverlay');
      if (loading) {
        setTimeout(() => {
          loading.classList.add('hidden');
          setTimeout(() => loading.remove(), 500);
        }, 800);
      }
    });

    // Enhanced smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const href = this.getAttribute('href');

        if (href === '#top' || href === '#') {
          window.scrollTo({
            top: 0,
            behavior: 'smooth'
          });
        } else {
          const target = document.querySelector(href);
          if (target) {
            target.scrollIntoView({
              behavior: 'smooth',
              block: 'start'
            });
          }
        }
      });
    });

    // Enhanced navbar scroll effect
    const navbar = document.getElementById('mainNavbar');
    window.addEventListener('scroll', function() {
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    });

    // Add intersection observer for animations
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }
      });
    }, observerOptions);

    // Observe elements for animation
    document.querySelectorAll('.icon-card, .feature-highlight, .contact-card').forEach(el => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(30px)';
      el.style.transition = 'all 0.6s ease';
      observer.observe(el);
    });

    // Scroll to top button
    const scrollToTopBtn = document.getElementById('scrollToTop');
    window.addEventListener('scroll', () => {
      if (window.scrollY > 300) {
        scrollToTopBtn.style.opacity = '1';
        scrollToTopBtn.style.visibility = 'visible';
      } else {
        scrollToTopBtn.style.opacity = '0';
        scrollToTopBtn.style.visibility = 'hidden';
      }
    });

    scrollToTopBtn.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });

    // Add active class to nav links on scroll
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-link[href^="#"]');

    window.addEventListener('scroll', () => {
      let current = '';
      const scrollY = window.scrollY;

      // If at top, set current to 'top' for home link
      if (scrollY < 100) {
        current = 'top';
      } else {
        sections.forEach(section => {
          const sectionTop = section.offsetTop - 100;
          if (scrollY >= sectionTop) {
            current = section.getAttribute('id');
          }
        });
      }

      navLinks.forEach(link => {
        link.classList.remove('active');
        const href = link.getAttribute('href');
        if ((href === '#top' || href === '#') && current === 'top') {
          link.classList.add('active');
        } else if (href === '#' + current) {
          link.classList.add('active');
        }
      });
    });

    // Add loading animation to buttons
    document.querySelectorAll('.btn-glass').forEach(btn => {
      btn.addEventListener('click', function() {
        if (this.href && this.href.includes('#')) {
          this.style.transform = 'scale(0.95)';
          setTimeout(() => {
            this.style.transform = '';
          }, 150);
        }
      });
    });
  </script>

</body>

</html>