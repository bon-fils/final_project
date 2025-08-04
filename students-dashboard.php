<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Student Dashboard | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f7fa;
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
    }

    .sidebar .logo {
      width: 80px;
      margin: 0 auto 15px;
      display: block;
    }

    .sidebar a {
      display: block;
      padding: 12px 20px;
      color: #fff;
      text-decoration: none;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background-color: #0059b3;
    }

    .topbar {
      margin-left: 250px;
      background-color: #fff;
      padding: 10px 30px;
      border-bottom: 1px solid #ddd;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .main-content {
      margin-left: 250px;
      padding: 30px;
    }

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
    }

    .widget {
      background: white;
      border-radius: 12px;
      padding: 25px 20px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
      text-align: center;
    }

    .widget h3 {
      font-weight: 700;
      font-size: 2.5rem;
      margin-bottom: 10px;
      color: #0066cc;
    }

    .widget p {
      font-size: 1rem;
      color: #444;
      margin-bottom: 0;
    }

    .nav-section {
      margin-top: 40px;
      display: flex;
      gap: 25px;
      flex-wrap: wrap;
      justify-content: center;
    }

    .nav-card {
      flex: 1 1 200px;
      max-width: 250px;
      background: white;
      border-radius: 12px;
      padding: 20px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
      cursor: pointer;
      transition: background-color 0.3s ease;
      text-align: center;
      color: #003366;
      text-decoration: none;
    }

    .nav-card:hover {
      background-color: #e6f0ff;
    }

    .nav-card i {
      font-size: 3rem;
      margin-bottom: 10px;
      color: #0066cc;
    }

    @media (max-width: 768px) {

      .sidebar,
      .topbar,
      .main-content,
      .footer {
        margin-left: 0 !important;
        width: 100%;
      }

      .sidebar {
        display: none;
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-3">
      <img src="assets/images/RP_Logo.jpg" alt="RP Logo" class="logo rounded-circle" />
      <h5>👨‍🎓 Student</h5>
      <hr style="border-color: #ffffff66;" />
    </div>
    <a href="students-dashboard.php" class="active"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i> Attendance Records</a>
    <a href="request-leave.php"><i class="fas fa-file-signature me-2"></i> Request Leave</a>
    <a href="leave-status.php"><i class="fas fa-envelope-open-text me-2"></i> Leave Status</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar">
    <h5 class="m-0 fw-bold">Student Dashboard</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">

    <div class="row g-4 justify-content-center">
      <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="widget">
          <h3>87%</h3>
          <p>Attendance Percentage</p>
        </div>
      </div>

      <div class="col-sm-6 col-md-4 col-lg-3">
        <div class="widget">
          <h3>2 Pending</h3>
          <p>Leave Requests</p>
        </div>
      </div>
    </div>

    <div class="nav-section">
      <a href="attendance-records.php" class="nav-card">
        <i class="fas fa-calendar-check"></i>
        <h6>Attendance Records</h6>
      </a>

      <a href="request-leave.php" class="nav-card">
        <i class="fas fa-file-signature"></i>
        <h6>Request Leave</h6>
      </a>

      <a href="leave-status.php" class="nav-card">
        <i class="fas fa-envelope-open-text"></i>
        <h6>Leave Status</h6>
      </a>
    </div>

  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Student Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>