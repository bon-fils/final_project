<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Reports | RP Attendance System</title>

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
      <h4>👩‍💼 Admin</h4>
      <hr style="border-color: #ffffff66;" />
    </div>
    <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
    <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
    <a href="admin-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Reports</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Attendance Reports</h5>
    <span>Admin Panel</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">

    <!-- Statistics Summary -->
    <div class="row mb-4 g-3">
      <div class="col-md-3">
        <div class="card text-white bg-primary h-100">
          <div class="card-body d-flex align-items-center">
            <i class="fas fa-users fa-3x me-3"></i>
            <div>
              <h6>Total Students</h6>
              <h4 id="totalStudents">--</h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-white bg-success h-100">
          <div class="card-body d-flex align-items-center">
            <i class="fas fa-calendar-check fa-3x me-3"></i>
            <div>
              <h6>Total Sessions</h6>
              <h4 id="totalSessions">--</h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-white bg-info h-100">
          <div class="card-body d-flex align-items-center">
            <i class="fas fa-percent fa-3x me-3"></i>
            <div>
              <h6>Average Attendance</h6>
              <h4 id="avgAttendance">--%</h4>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-white bg-warning h-100">
          <div class="card-body d-flex align-items-center">
            <i class="fas fa-clock fa-3x me-3"></i>
            <div>
              <h6>Pending Leave</h6>
              <h4 id="pendingLeave">--</h4>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <form id="filterForm" class="row g-3 align-items-center mb-4">
      <div class="col-md-4">
        <label for="departmentFilter" class="form-label">Department</label>
        <select id="departmentFilter" class="form-select">
          <option value="">All Departments</option>
          <option>Computer Engineering</option>
          <option>Electrical Engineering</option>
          <option>Mechanical Engineering</option>
          <!-- Dynamically filled in production -->
        </select>
      </div>
      <div class="col-md-4">
        <label for="courseFilter" class="form-label">Course</label>
        <select id="courseFilter" class="form-select">
          <option value="">All Courses</option>
          <option>Software Engineering</option>
          <option>Networking</option>
          <option>Power Systems</option>
          <!-- Dynamically filled -->
        </select>
      </div>
      <div class="col-md-4">
        <label for="dateRange" class="form-label">Date Range</label>
        <input type="text" id="dateRange" class="form-control" placeholder="YYYY-MM-DD to YYYY-MM-DD" />
      </div>
      <div class="col-12 d-flex justify-content-end mt-2">
        <button type="submit" class="btn btn-primary me-2">
          <i class="fas fa-filter me-1"></i> Filter
        </button>
        <button type="button" class="btn btn-outline-secondary" id="resetFilters">
          <i class="fas fa-undo me-1"></i> Reset
        </button>
      </div>
    </form>

    <!-- Report Table -->
    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Department</th>
            <th>Course</th>
            <th>Date</th>
            <th>Status</th>
            <th>Method</th>
          </tr>
        </thead>
        <tbody id="reportTableBody">
          <!-- Dynamic report rows here -->
          <tr>
            <td>1</td>
            <td>Jean Mukamana</td>
            <td>Computer Engineering</td>
            <td>Software Engineering</td>
            <td>2025-06-20</td>
            <td><span class="badge bg-success">Present</span></td>
            <td>Face Recognition</td>
          </tr>
          <tr>
            <td>2</td>
            <td>Eric Uwizeye</td>
            <td>Electrical Engineering</td>
            <td>Power Systems</td>
            <td>2025-06-20</td>
            <td><span class="badge bg-danger">Absent</span></td>
            <td>Fingerprint</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Export Buttons -->
    <div class="d-flex justify-content-end mt-3 gap-2">
      <button class="btn btn-success">
        <i class="fas fa-file-excel me-1"></i> Export Excel
      </button>
      <button class="btn btn-danger">
        <i class="fas fa-file-pdf me-1"></i> Export PDF
      </button>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Admin Panel
  </div>

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Optional: Date range picker & stats script -->
  <script>
    // Simulate dynamic data for stats
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('totalStudents').textContent = '560';
      document.getElementById('totalSessions').textContent = '78';
      document.getElementById('avgAttendance').textContent = '85%';
      document.getElementById('pendingLeave').textContent = '12';
    });

    // Simple placeholder for filter form
    document.getElementById('filterForm').addEventListener('submit', function (e) {
      e.preventDefault();
      alert('Filter applied (functionality to be implemented)');
    });
    document.getElementById('resetFilters').addEventListener('click', function () {
      document.getElementById('departmentFilter').value = '';
      document.getElementById('courseFilter').value = '';
      document.getElementById('dateRange').value = '';
    });
  </script>
</body>

</html>
