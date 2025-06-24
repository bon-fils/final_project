<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance Session | Lecturer | RP Attendance System</title>

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

    .session-controls {
      margin-bottom: 20px;
    }

    #webcam-preview {
      width: 100%;
      max-width: 640px;
      border: 2px solid #0066cc;
      border-radius: 8px;
      background-color: #000;
      display: block;
      margin-bottom: 20px;
    }

    .attendance-table {
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      overflow-x: auto;
    }

    .manual-mark-btn {
      margin-top: 10px;
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
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="lecturer-dashboard.php">Dashboard</a>
    <a href="lecturer-my-courses.php">My Courses</a>
    <a href="attendance-session.php" class="active"><i class="fas fa-video me-2"></i> Attendance Session</a>
    <a href="attendance-reports.php">Attendance Reports</a>
    <a href="leave-requests.php">Leave Requests</a>
    <a href="index.php">Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Attendance Session</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="session-controls d-flex flex-wrap align-items-center gap-3">
      <button id="start-session" class="btn btn-primary"><i class="fas fa-play me-2"></i>Start Session</button>
      <button id="end-session" class="btn btn-danger" disabled><i class="fas fa-stop me-2"></i>End Session</button>
      <button id="manual-mark" class="btn btn-secondary manual-mark-btn"><i class="fas fa-pen me-2"></i>Manual Mark</button>
      <button id="use-fingerprint" class="btn btn-info manual-mark-btn"><i class="fas fa-fingerprint me-2"></i>Use Fingerprint</button>
    </div>

    <!-- Webcam preview -->
    <video id="webcam-preview" autoplay muted playsinline></video>

    <!-- Attendance Table -->
    <div class="attendance-table">
      <table class="table table-bordered table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th scope="col">Name</th>
            <th scope="col">Date</th>
            <th scope="col">Status</th>
            <th scope="col">Method</th>
          </tr>
        </thead>
        <tbody id="attendance-list" aria-live="polite" aria-relevant="all">
          <!-- Example rows -->
          <tr>
            <td>John Doe</td>
            <td>2025-06-24</td>
            <td><span class="badge bg-success">Present</span></td>
            <td>Face Recognition</td>
          </tr>
          <tr>
            <td>Jane Smith</td>
            <td>2025-06-24</td>
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

  <!-- Placeholder JS for webcam and buttons -->
  <script>
    const startBtn = document.getElementById('start-session');
    const endBtn = document.getElementById('end-session');
    const webcamPreview = document.getElementById('webcam-preview');

    startBtn.addEventListener('click', () => {
      startBtn.disabled = true;
      endBtn.disabled = false;
      // Start webcam and face recognition logic here
      if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        navigator.mediaDevices.getUserMedia({ video: true })
          .then(stream => {
            webcamPreview.srcObject = stream;
          })
          .catch(err => {
            alert('Could not start webcam: ' + err);
          });
      }
    });

    endBtn.addEventListener('click', () => {
      startBtn.disabled = false;
      endBtn.disabled = true;
      // Stop webcam
      if (webcamPreview.srcObject) {
        webcamPreview.srcObject.getTracks().forEach(track => track.stop());
        webcamPreview.srcObject = null;
      }
    });
  </script>

</body>

</html>
