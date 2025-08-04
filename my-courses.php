<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Courses | Lecturer | RP Attendance System</title>

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

    table {
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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
    <a href="lecturer-dashboard.php">Dashboard</a>
    <a href="lecturer-my-courses.php" class="active"><i class="fas fa-book me-2"></i> My Courses</a>
    <a href="attendance-session.php"><i class="fas fa-video me-2"></i> Attendance Session</a>
    <a href="attendance-reports.php"><i class="fas fa-chart-line me-2"></i> Attendance Reports</a>
    <a href="leave-requests.php"><i class="fas fa-envelope me-2"></i> Leave Requests</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">My Courses</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <table class="table table-hover table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Course Name</th>
          <th>Course Code</th>
          <th>Department</th>
          <th>Option</th>
          <th>Class</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <!-- Example courses -->
        <tr>
          <td>1</td>
          <td>Software Engineering</td>
          <td>SE101</td>
          <td>ICT</td>
          <td>Software Dev</td>
          <td>Year 2</td>
          <td>
            <a href="attendance-session.php?course=SE101" class="btn btn-sm btn-primary me-1" title="Start Session">
              <i class="fas fa-play"></i>
            </a>
            <a href="attendance-reports.php?course=SE101" class="btn btn-sm btn-secondary" title="View Reports">
              <i class="fas fa-chart-bar"></i>
            </a>
          </td>
        </tr>
        <tr>
          <td>2</td>
          <td>Computer Networks</td>
          <td>CN201</td>
          <td>ICT</td>
          <td>Networking</td>
          <td>Year 3</td>
          <td>
            <a href="attendance-session.php?course=CN201" class="btn btn-sm btn-primary me-1" title="Start Session">
              <i class="fas fa-play"></i>
            </a>
            <a href="attendance-reports.php?course=CN201" class="btn btn-sm btn-secondary" title="View Reports">
              <i class="fas fa-chart-bar"></i>
            </a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
