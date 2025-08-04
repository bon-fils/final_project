<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Request Leave | Student | RP Attendance System</title>

  <!-- Bootstrap CSS & Font Awesome -->
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
      overflow-y: auto;
    }

    .sidebar h4 {
      font-size: 1.3rem;
    }

    .sidebar a {
      display: block;
      padding: 12px 20px;
      color: #fff;
      text-decoration: none;
      transition: background 0.2s ease;
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
      padding: 40px 30px;
      max-width: 700px;
    }

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
    }

    label {
      font-weight: 600;
      color: #003366;
    }

    .form-container {
      background: white;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .btn-primary {
      background-color: #003366;
      border-color: #003366;
    }

    .btn-primary:hover {
      background-color: #0059b3;
      border-color: #0059b3;
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

      .main-content {
        padding: 20px;
      }
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4>🎓 Student</h4>
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="students-dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
    <a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i>Attendance Records</a>
    <a href="request-leave.php" class="active"><i class="fas fa-file-signature me-2"></i>Request Leave</a>
    <a href="leave-status.php"><i class="fas fa-info-circle me-2"></i>Leave Status</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Request Leave</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="form-container">
      <form id="leaveRequestForm" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="fromDate" class="form-label">From Date</label>
          <input type="date" id="fromDate" name="fromDate" class="form-control" required />
        </div>
        <div class="mb-3">
          <label for="toDate" class="form-label">To Date</label>
          <input type="date" id="toDate" name="toDate" class="form-control" required />
        </div>
        <div class="mb-3">
          <label for="reason" class="form-label">Reason</label>
          <textarea id="reason" name="reason" class="form-control" rows="4" placeholder="Enter your reason for leave" required></textarea>
        </div>
        <div class="mb-3">
          <label for="supportingFile" class="form-label">Attach Supporting Document (optional)</label>
          <input type="file" id="supportingFile" name="supportingFile" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
          <small class="text-muted">Allowed file types: PDF, DOC, DOCX, JPG, PNG</small>
        </div>
        <div class="text-end">
          <button type="submit" class="btn btn-primary px-4"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Student Panel
  </div>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('leaveRequestForm').addEventListener('submit', function (e) {
      e.preventDefault();

      const fromDate = document.getElementById('fromDate').value;
      const toDate = document.getElementById('toDate').value;
      const reason = document.getElementById('reason').value.trim();

      if (new Date(fromDate) > new Date(toDate)) {
        alert('From Date cannot be later than To Date.');
        return;
      }

      if (!reason) {
        alert('Please enter a reason for your leave.');
        return;
      }

      // Simulated submission
      alert('Leave request submitted successfully!');
      this.reset();
    });
  </script>
</body>

</html>
