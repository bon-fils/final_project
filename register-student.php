<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register Student | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>

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

    .form-label {
      font-weight: 500;
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
    <h4>👩‍💼 Admin</h4>
    <hr style="border-color: #ffffff66;">
  </div>
  <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
  <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
  <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
  <a href="admin-reports.php"><i class="fas fa-chart-bar me-2"></i> Reports</a>
  <a href="index.html"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<!-- Topbar -->
<div class="topbar d-flex justify-content-between align-items-center">
  <h5 class="m-0 fw-bold">Register Student</h5>
  <span>Admin Panel</span>
</div>

<!-- Main Content -->
<div class="main-content">
  <div class="card p-4">
    <h5 class="mb-4">Student Registration Form</h5>
    <form>
      <div class="row g-4">
        <!-- Student Info -->
        <div class="col-md-6">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-control" placeholder="Enter full name" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" placeholder="Enter email" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Registration Number</label>
          <input type="text" class="form-control" placeholder="e.g. RP/CT/2023/1234" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Department</label>
          <select class="form-select" required>
            <option selected disabled>-- Select Department --</option>
            <option>Computer Engineering</option>
            <option>Electrical Engineering</option>
            <option>Mechanical Engineering</option>
            <!-- More departments... -->
          </select>
        </div>

        <!-- Upload Photo -->
        <div class="col-md-6">
          <label class="form-label">Upload Photo</label>
          <input type="file" class="form-control" accept="image/*" required>
        </div>

        <!-- Fingerprint Capture -->
        <div class="col-md-6">
          <label class="form-label d-block">Fingerprint Capture</label>
          <button type="button" class="btn btn-outline-secondary w-100">
            <i class="fas fa-fingerprint me-2"></i> Capture Fingerprint
          </button>
        </div>

        <!-- Password -->
        <div class="col-md-6">
          <label class="form-label">Set Password</label>
          <input type="password" class="form-control" placeholder="Enter password" required>
        </div>
        <div class="col-md-6">
          <label class="form-label d-block invisible">Register</label>
          <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-save me-2"></i> Register Student
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Footer -->
<div class="footer">
  &copy; 2025 Rwanda Polytechnic | Admin Panel
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
