<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>HoD Dashboard | RP Attendance System</title>

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

    .sidebar a:hover {
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
    }

    .btn-primary {
      background-color: #0066cc;
      border: none;
    }

    .btn-primary:hover {
      background-color: #004b99;
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
      <h4>👔 Head of Department</h4>
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="hod-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="hod-department-reports.php"><i class="fas fa-chart-bar me-2"></i> Department Reports</a>
    <a href="hod-leave-management.php"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave Requests</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Head of Department Dashboard</h5>
    <span>HoD Panel</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="row g-4">

      <!-- Attendance Summary -->
      <div class="col-md-6 col-lg-4">
        <div class="card p-4">
          <h6 class="mb-3">Department Attendance</h6>
          <div class="display-4 fw-bold text-primary">87%</div>
          <small class="text-muted">Average attendance this semester</small>
        </div>
      </div>

      <!-- Pending Leave Requests -->
      <div class="col-md-6 col-lg-4">
        <div class="card p-4">
          <h6 class="mb-3">Pending Leave Requests</h6>
          <div class="display-4 fw-bold text-warning">5</div>
          <small class="text-muted">Requests awaiting your approval</small>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="col-md-6 col-lg-4">
        <div class="card p-4 d-flex flex-column justify-content-center align-items-start">
          <h6 class="mb-3">Quick Links</h6>
          <a href="hod-department-reports.php" class="btn btn-primary mb-2 w-100">
            <i class="fas fa-chart-bar me-2"></i> View Reports
          </a>
          <a href="hod-leave-management.php" class="btn btn-primary mb-2 w-100">
            <i class="fas fa-envelope-open-text me-2"></i> Manage Leave
          </a>
          <a href="hod-manage-lecturers.php" class="btn btn-primary w-100">
            <i class="fas fa-user-plus me-2"></i> Manage Lecturers
          </a>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="col-12">
        <div class="card p-4 mt-3">
          <h6 class="mb-3">Filter Records</h6>
          <form class="row g-3">
            <div class="col-md-3">
              <label class="form-label">User Type</label>
              <select class="form-select" id="userType" onchange="updateEducationLevel()">
                <option selected disabled>Select Type</option>
                <option value="student">Student</option>
                <option value="lecturer">Lecturer</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Option</label>
              <select class="form-select">
                <option selected disabled>Choose Option</option>
                <option>Networking</option>
                <option>Software Engineering</option>
                <option>Electrical</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Sex</label>
              <select class="form-select">
                <option selected>All</option>
                <option>Male</option>
                <option>Female</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" id="eduLabel">Education Level</label>
              <select class="form-select" id="eduLevel">
                <option selected disabled>Select</option>
                <!-- Options will be filled by JS -->
              </select>
            </div>
            <div class="col-12 mt-3">
              <button type="submit" class="btn btn-primary">
                <i class="fas fa-search me-2"></i> Apply Filter
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | HoD Panel
  </div>

  <!-- JavaScript -->
  <script>
    function updateEducationLevel() {
      const userType = document.getElementById("userType").value;
      const eduLabel = document.getElementById("eduLabel");
      const eduLevel = document.getElementById("eduLevel");

      eduLevel.innerHTML = ""; // Clear old options

      if (userType === "student") {
        eduLabel.textContent = "Year / Level";
        ["Year 1", "Year 2", "Year 3"].forEach(year => {
          const option = document.createElement("option");
          option.text = year;
          eduLevel.add(option);
        });
      } else if (userType === "lecturer") {
        eduLabel.textContent = "Education Level";
        ["A1", "A0", "Masters"].forEach(level => {
          const option = document.createElement("option");
          option.text = level;
          eduLevel.add(option);
        });
      }
    }
  </script>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
