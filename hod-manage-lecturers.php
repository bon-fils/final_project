<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Lecturers | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f9f9f9;
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

    .form-section, .table-section {
      background-color: #fff;
      border-radius: 10px;
      padding: 25px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      margin-bottom: 30px;
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
      .sidebar, .topbar, .main-content, .footer {
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
    <a href="hod-manage-lecturers.php"><i class="fas fa-user-plus me-2"></i> Manage Lecturers</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Manage Lecturers</h5>
    <span>HoD Panel</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">

    <!-- Add Lecturer Form -->
    <div class="form-section">
      <h5 class="mb-4">Add New Lecturer</h5>
      <form action="add-lecturer-process.php" method="POST">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Phone Number</label>
            <input type="text" name="phone" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label">Department</label>
            <select name="department_id" class="form-select" required>
              <option value="">Select Department</option>
              <option value="1">ICT</option>
              <option value="2">Engineering</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Sex</label>
            <select name="sex" class="form-select" required>
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Education Level</label>
            <select name="education_level" class="form-select" required>
              <option value="">Select</option>
              <option value="Bachelor's">Bachelor's</option>
              <option value="Master's">Master's</option>
              <option value="PhD">PhD</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        <div class="mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add Lecturer
          </button>
        </div>
      </form>
    </div>

    <!-- Lecturer List Table -->
    <div class="table-section">
      <h5 class="mb-4">Existing Lecturers</h5>
      <table class="table table-bordered table-striped">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Sex</th>
            <th>Phone</th>
            <th>Education</th>
            <th>Department</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <!-- Replace with PHP loop -->
          <tr>
            <td>1</td>
            <td>Jane Umutoni</td>
            <td>jane.umutoni@example.com</td>
            <td>Female</td>
            <td>0788888888</td>
            <td>Master's</td>
            <td>ICT</td>
            <td>
              <a href="#" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
              <a href="#" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></a>
            </td>
          </tr>
          <!-- More rows dynamically -->
        </tbody>
      </table>
    </div>

  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | HoD Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
