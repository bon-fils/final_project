<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Leave Requests | Lecturer | RP Attendance System</title>

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

    .status-badge {
      font-weight: 600;
      padding: 0.4em 0.75em;
      border-radius: 0.25rem;
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
      <hr style="border-color: #ffffff66;" />
    </div>
    <a href="lecturer-dashboard.php">Dashboard</a>
    <a href="lecturer-my-courses.php">My Courses</a>
    <a href="attendance-session.php">Attendance Session</a>
    <a href="attendance-reports.php">Attendance Reports</a>
    <a href="leave-requests.php" class="active"><i class="fas fa-envelope-open-text me-2"></i> Leave Requests</a>
    <a href="index.php">Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Leave Requests</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="card p-4">
      <h5 class="mb-4">Pending Leave Requests</h5>
      <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Student Name</th>
              <th>Course</th>
              <th>From</th>
              <th>To</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="leaveRequestsTable">
            <!-- Sample leave request -->
            <tr>
              <td>John Doe</td>
              <td>Software Engineering</td>
              <td>2025-06-22</td>
              <td>2025-06-25</td>
              <td>Medical Appointment</td>
              <td><span class="status-badge bg-warning text-dark">Pending</span></td>
              <td>
                <button class="btn btn-sm btn-approve me-1"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-sm btn-reject"><i class="fas fa-times"></i> Reject</button>
              </td>
            </tr>
            <tr>
              <td>Jane Smith</td>
              <td>Networking</td>
              <td>2025-06-20</td>
              <td>2025-06-21</td>
              <td>Family Emergency</td>
              <td><span class="status-badge bg-success">Approved</span></td>
              <td>—</td>
            </tr>
            <tr>
              <td>Mark Johnson</td>
              <td>Computer Architecture</td>
              <td>2025-06-18</td>
              <td>2025-06-20</td>
              <td>Conference</td>
              <td><span class="status-badge bg-danger">Rejected</span></td>
              <td>—</td>
            </tr>
            <!-- More rows here -->
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Optional: Add JS to handle Approve/Reject actions -->
  <script>
    const table = document.getElementById('leaveRequestsTable');

    table.addEventListener('click', (e) => {
      const target = e.target.closest('button');
      if (!target) return;

      const row = target.closest('tr');
      const statusCell = row.querySelector('td:nth-child(6) span');
      const actionsCell = row.querySelector('td:nth-child(7)');

      if (target.classList.contains('btn-approve')) {
        // Simulate approve action
        statusCell.textContent = 'Approved';
        statusCell.className = 'status-badge bg-success';
        actionsCell.innerHTML = '—';
      } else if (target.classList.contains('btn-reject')) {
        // Simulate reject action
        statusCell.textContent = 'Rejected';
        statusCell.className = 'status-badge bg-danger';
        actionsCell.innerHTML = '—';
      }
    });
  </script>

</body>

</html>
