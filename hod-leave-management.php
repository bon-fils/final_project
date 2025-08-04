<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Leave Management | HoD | RP Attendance System</title>

  <!-- Bootstrap & Font Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
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

    .btn-approve {
      background-color: #28a745;
      color: white;
    }

    .btn-reject {
      background-color: #dc3545;
      color: white;
    }

    .btn-approve:hover {
      background-color: #218838;
    }

    .btn-reject:hover {
      background-color: #c82333;
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
      <img src="RP_Logo.jpeg" alt="RP Logo" width="100" class="mb-2" />
      <h5>HoD Panel</h5>
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="hod-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="hod-department-reports.php"><i class="fas fa-chart-bar me-2"></i> Department Reports</a>
    <a href="hod-leave-management.php" class="active"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Manage Leave Requests</h5>
    <span>Welcome, Head of Department</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="card p-4">
      <h6 class="mb-3">Pending Leave Requests</h6>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Student Name</th>
              <th>Department</th>
              <th>Class</th>
              <th>From</th>
              <th>To</th>
              <th>Reason</th>
              <th>Support File</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="leaveRequestsTableBody">
            <tr>
              <td>1</td>
              <td>Jean Uwimana</td>
              <td>ICT Department</td>
              <td>Option A</td>
              <td>2025-06-10</td>
              <td>2025-06-12</td>
              <td>Medical appointment</td>
              <td>
                <a href="uploads/leave_1.pdf" target="_blank" class="btn btn-sm btn-secondary">
                  <i class="fas fa-file-download"></i> View
                </a>
              </td>
              <td><span class="badge bg-warning text-dark">Pending</span></td>
              <td>
                <button class="btn btn-sm btn-approve me-1"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-sm btn-reject"><i class="fas fa-times"></i> Reject</button>
              </td>
            </tr>
            <!-- Additional rows loaded dynamically -->
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | HoD Panel
  </div>

  <!-- JS Logic -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('leaveRequestsTableBody').addEventListener('click', function(e) {
      const btn = e.target.closest('button');
      if (!btn) return;

      const row = btn.closest('tr');
      const statusCell = row.querySelector('td:nth-child(9) span');

      if (btn.classList.contains('btn-approve')) {
        if (confirm('Approve this leave request?')) {
          statusCell.className = 'badge bg-success';
          statusCell.textContent = 'Approved';
          row.querySelector('.btn-approve').disabled = true;
          row.querySelector('.btn-reject').disabled = true;
        }
      }

      if (btn.classList.contains('btn-reject')) {
        if (confirm('Reject this leave request?')) {
          statusCell.className = 'badge bg-danger';
          statusCell.textContent = 'Rejected';
          row.querySelector('.btn-approve').disabled = true;
          row.querySelector('.btn-reject').disabled = true;
        }
      }
    });
  </script>
</body>

</html>