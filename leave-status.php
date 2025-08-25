<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Leave Status | Student | RP Attendance System</title>

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
      max-width: 1000px;
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

      .table th,
      .table td {
        font-size: 0.85rem;
      }
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4>👨🎓 Student</h4>
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="students-dashboard.php">Dashboard</a>
    <a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i> Attendance Records</a>
    <a href="request-leave.php"><i class="fas fa-file-signature me-2"></i> Request Leave</a>
    <a href="leave-status.php" class="active"><i class="fas fa-info-circle me-2"></i> Leave Status</a>
    <a href="index.php">Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Leave Request Status</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>From Date</th>
          <th>To Date</th>
          <th>Reason</th>
          <th>Recipient</th>
          <th>Role</th>
          <th>Course</th>
          <th>Status</th>
          <th>Supporting Document</th>
        </tr>
      </thead>
      <tbody>
        <!-- Example Data -->
        <tr>
          <td>2025-06-01</td>
          <td>2025-06-03</td>
          <td>Medical leave</td>
          <td>Mr. John Doe</td>
          <td>Lecturer</td>
          <td>Web Development</td>
          <td><span class="badge bg-warning text-dark">Pending</span></td>
          <td><em>Not uploaded</em></td>
        </tr>
        <tr>
          <td>2025-05-10</td>
          <td>2025-05-12</td>
          <td>Family emergency</td>
          <td>Mrs. Jane Smith</td>
          <td>HoD</td>
          <td></td>
          <td><span class="badge bg-success">Approved</span></td>
          <td><a href="uploads/support_doc_123.pdf" target="_blank" class="btn btn-sm btn-outline-primary">
              <i class="fas fa-file-pdf me-1"></i>View</a>
          </td>
        </tr>
        <tr>
          <td>2025-04-05</td>
          <td>2025-04-06</td>
          <td>Personal reasons</td>
          <td>Mr. Paul Mwangi</td>
          <td>Lecturer</td>
          <td>Database Systems</td>
          <td><span class="badge bg-danger">Rejected</span></td>
          <td><em>Not uploaded</em></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Student Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
