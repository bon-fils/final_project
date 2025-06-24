<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Lecturer Dashboard | RP Attendance System</title>

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
    }

    .main-content {
      margin-left: 250px;
      padding: 30px;
    }

    .card {
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      margin-bottom: 30px;
    }

    .widget {
      background-color: #fff;
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .widget h3 {
      margin-bottom: 10px;
      color: #0066cc;
    }

    .widget p {
      font-size: 1.5rem;
      font-weight: 700;
      margin: 0;
      color: #003366;
    }

    .nav-links a {
      display: inline-block;
      margin-right: 15px;
      color: #0066cc;
      font-weight: 600;
      text-decoration: none;
      border-bottom: 2px solid transparent;
      padding-bottom: 3px;
      transition: border-color 0.3s ease;
    }

    .nav-links a:hover,
    .nav-links a.active {
      border-color: #0066cc;
      color: #004b99;
    }

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
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
    <div class="text-center mb-4">
      <h4>👨‍🏫 Lecturer</h4>
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="lecturer-dashboard.php" class="active"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="my-courses.php"><i class="fas fa-book me-2"></i> My Courses</a>
    <a href="attendance-session.php"><i class="fas fa-video me-2"></i> Attendance Session</a>
    <a href="attendance-reports.php"><i class="fas fa-chart-line me-2"></i> Attendance Reports</a>
    <a href="leave-requests.php"><i class="fas fa-envelope me-2"></i> Leave Requests</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Lecturer Dashboard</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="row g-4 mb-4">
      <div class="col-md-4">
        <div class="widget">
          <h3>Assigned Courses</h3>
          <p>4</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="widget">
          <h3>Today's Attendance</h3>
          <p>120</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="widget">
          <h3>Pending Leave Requests</h3>
          <p>5</p>
        </div>
      </div>
    </div>

    <div class="nav-links mb-3">
      <a href="my-courses.php" class="active">My Courses</a>
      <a href="attendance-session.php">Start/Stop Session</a>
      <a href="attendance-reports.php">Attendance Reports</a>
      <a href="leave-requests.php">Leave Requests</a>
    </div>

    <div class="card p-4">
      <h6>Welcome back, Lecturer!</h6>
      <p>Use the navigation above to manage your courses, take attendance, and handle leave requests.</p>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
