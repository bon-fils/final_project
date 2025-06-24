<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance Reports | Lecturer | RP Attendance System</title>

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

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
    }

    .filter-section {
      margin-bottom: 20px;
    }

    .report-summary {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
    }

    .summary-card {
      background: white;
      padding: 15px 20px;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
      flex: 1;
      text-align: center;
    }

    /* Print styles */
    @media print {
      body * {
        visibility: hidden;
      }

      .main-content,
      .main-content * {
        visibility: visible;
      }

      .main-content {
        margin: 0;
        padding: 0;
        width: 100%;
      }

      .btn,
      .filter-section form,
      .topbar,
      .sidebar,
      .footer {
        display: none !important;
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4>👨‍🏫 Lecturer</h4>
      <hr style="border-color: #ffffff66;" />
    </div>
    <a href="lecturer-dashboard.php">Dashboard</a>
    <a href="lecturer-my-courses.php">My Courses</a>
    <a href="attendance-session.php">Attendance Session</a>
    <a href="attendance-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Attendance Reports</a>
    <a href="leave-requests.php">Leave Requests</a>
    <a href="index.php">Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Attendance Reports</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Print Button -->
    <div class="d-flex justify-content-end mb-3">
      <button id="printReport" class="btn btn-outline-primary">
        <i class="fas fa-print me-2"></i> Print Report
      </button>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
      <form id="filterForm" class="row g-3 align-items-center">
        <div class="col-md-4">
          <label for="course" class="form-label">Course</label>
          <select id="course" class="form-select">
            <option selected>All Courses</option>
            <option>Software Engineering</option>
            <option>Networking</option>
            <option>Computer Architecture</option>
          </select>
        </div>
        <div class="col-md-4">
          <label for="dateFrom" class="form-label">From Date</label>
          <input type="date" id="dateFrom" class="form-control" />
        </div>
        <div class="col-md-4">
          <label for="dateTo" class="form-label">To Date</label>
          <input type="date" id="dateTo" class="form-control" />
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter me-2"></i> Filter
          </button>
        </div>
      </form>
    </div>

    <!-- Summary Statistics -->
    <div class="report-summary" id="summaryStats">
      <div class="summary-card">
        <h6>Total Attendance Records</h6>
        <p class="fs-4 fw-bold">320</p>
      </div>
      <div class="summary-card">
        <h6>Present Students</h6>
        <p class="fs-4 fw-bold text-success">280</p>
      </div>
      <div class="summary-card">
        <h6>Absent Students</h6>
        <p class="fs-4 fw-bold text-danger">40</p>
      </div>
      <div class="summary-card">
        <h6>Average Attendance Rate</h6>
        <p class="fs-4 fw-bold">87.5%</p>
      </div>
    </div>

    <!-- Attendance Report Table -->
    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Student Name</th>
            <th scope="col">Course</th>
            <th scope="col">Date</th>
            <th scope="col">Status</th>
            <th scope="col">Method</th>
          </tr>
        </thead>
        <tbody id="attendanceTableBody">
          <!-- Sample rows -->
          <tr>
            <td>John Doe</td>
            <td>Software Engineering</td>
            <td>2025-06-20</td>
            <td><span class="badge bg-success">Present</span></td>
            <td>Face Recognition</td>
          </tr>
          <tr>
            <td>Jane Smith</td>
            <td>Networking</td>
            <td>2025-06-20</td>
            <td><span class="badge bg-danger">Absent</span></td>
            <td>-</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Print Script -->
  <script>
    document.getElementById('printReport').addEventListener('click', () => {
      window.print();
    });
  </script>
</body>

</html>
