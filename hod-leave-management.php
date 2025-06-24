<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Leave Management | HoD | RP Attendance System</title>

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

    .btn-primary {
      background-color: #0066cc;
      border: none;
    }

    .btn-primary:hover {
      background-color: #004b99;
    }

    .btn-approve {
      background-color: #28a745;
      border: none;
      color: white;
    }

    .btn-approve:hover {
      background-color: #218838;
    }

    .btn-reject {
      background-color: #dc3545;
      border: none;
      color: white;
    }

    .btn-reject:hover {
      background-color: #c82333;
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
    <a href="hod-leave-management.php" class="active"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave Requests</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Manage Leave Requests</h5>
    <span>HoD Panel</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="card p-4">
      <h6 class="mb-3">Pending Leave Requests</h6>
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Course</th>
            <th>From</th>
            <th>To</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="leaveRequestsTableBody">
          <!-- Sample data row -->
          <tr>
            <td>1</td>
            <td>Jean Uwimana</td>
            <td>Software Engineering</td>
            <td>2025-06-10</td>
            <td>2025-06-12</td>
            <td>Medical appointment</td>
            <td><span class="badge bg-warning text-dark">Pending</span></td>
            <td>
              <button class="btn btn-sm btn-approve me-1"><i class="fas fa-check"></i> Approve</button>
              <button class="btn btn-sm btn-reject"><i class="fas fa-times"></i> Reject</button>
            </td>
          </tr>
          <!-- More rows will be dynamically added -->
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

  <script>
    // TODO: Replace with API call to fetch leave requests for the department
    // For demonstration, table is static

    // Add event listeners for approve/reject buttons
    document.getElementById('leaveRequestsTableBody').addEventListener('click', (e) => {
      if (e.target.closest('.btn-approve')) {
        const row = e.target.closest('tr');
        if (confirm('Are you sure you want to approve this leave request?')) {
          // TODO: call API to approve leave
          row.querySelector('td:nth-child(7) span').className = 'badge bg-success';
          row.querySelector('td:nth-child(7) span').textContent = 'Approved';
          e.target.closest('.btn-approve').disabled = true;
          e.target.closest('.btn-reject').disabled = true;
        }
      } else if (e.target.closest('.btn-reject')) {
        const row = e.target.closest('tr');
        if (confirm('Are you sure you want to reject this leave request?')) {
          // TODO: call API to reject leave
          row.querySelector('td:nth-child(7) span').className = 'badge bg-danger';
          row.querySelector('td:nth-child(7) span').textContent = 'Rejected';
          e.target.closest('.btn-approve').disabled = true;
          e.target.closest('.btn-reject').disabled = true;
        }
      }
    });
  </script>
</body>

</html>
