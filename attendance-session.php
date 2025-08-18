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
      overflow-y: auto;
    }

    .sidebar a {
      display: block;
      padding: 12px 20px;
      color: #fff;
      text-decoration: none;
      font-weight: 500;
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
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
    }

    .main-content {
      margin-left: 250px;
      padding: 30px;
      min-height: calc(100vh - 112px);
    }

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
      border-top: 1px solid #ddd;
    }

    #webcam-preview {
      width: 100%;
      max-width: 640px;
      border: 2px solid #0066cc;
      border-radius: 8px;
      background-color: #000;
      margin-bottom: 20px;
      aspect-ratio: 4/3;
      object-fit: cover;
    }

    @media (max-width: 768px) {

      .sidebar,
      .topbar,
      .main-content,
      .footer {
        margin-left: 0 !important;
        width: 100% !important;
      }

      .sidebar {
        display: none;
      }
    }

    .progress {
      background-color: #e9ecef;
    }

    .progress-bar {
      font-weight: bold;
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <nav class="sidebar" aria-label="Sidebar Navigation">
    <div class="text-center mb-4">
      <h4>👨‍🏫 Lecturer</h4>
      <hr style="border-color: #ffffff66;" />
    </div>
    <a href="lecturer-dashboard.php">Dashboard</a>
    <a href="lecturer-my-courses.php">My Courses</a>
    <a href="attendance-session.php" class="active"><i class="fas fa-video me-2"></i> Attendance Session</a>
    <a href="attendance-reports.php">Attendance Reports</a>
    <a href="leave-requests.php">Leave Requests</a>
    <a href="index.php">Logout</a>
  </nav>

  <!-- Topbar -->
  <header class="topbar" role="banner">
    <h5 class="m-0 fw-bold">Attendance Session</h5>
    <span>RP Attendance System</span>
  </header>

  <!-- Main Content -->
  <main class="main-content" role="main" tabindex="-1">

    <!-- Session Filters -->
    <form id="sessionForm" class="row g-3 mb-4">
      <div class="col-md-3">
        <label for="department" class="form-label fw-semibold">Department</label>
        <select id="department" class="form-select" required>
          <option value="" disabled selected>Select Department</option>
          <option value="ICT">ICT</option>
          <option value="Mechanical">Mechanical</option>
          <option value="Civil">Civil</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="option" class="form-label fw-semibold">Option</label>
        <select id="option" class="form-select" required disabled>
          <option value="" disabled selected>Select Option</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="classLevel" class="form-label fw-semibold">Class (Year)</label>
        <select id="classLevel" class="form-select" required disabled>
          <option value="" disabled selected>Select Class</option>
          <option value="Year 1">Year 1</option>
          <option value="Year 2">Year 2</option>
          <option value="Year 3">Year 3</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="course" class="form-label fw-semibold">Course</label>
        <select id="course" class="form-select" required disabled>
          <option value="" disabled selected>Select Course</option>
        </select>
      </div>

      <div class="col-12 d-flex flex-wrap gap-2">
        <button type="submit" id="start-session" class="btn btn-primary" disabled>
          <i class="fas fa-play me-2"></i> Start Session
        </button>
        <button type="button" id="end-session" class="btn btn-danger" disabled>
          <i class="fas fa-stop me-2"></i> End Session
        </button>
        <a href="#" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#manualMarkModal">
          <i class="fas fa-pen me-2"></i> Manual Mark
        </a>
        <button type="button" class="btn btn-info">
          <i class="fas fa-fingerprint me-2"></i> Use Fingerprint
        </button>
      </div>
    </form>

    <!-- Webcam Preview -->
    <video id="webcam-preview" autoplay muted playsinline></video>

    <!-- Attendance Table -->
    <section class="attendance-table" aria-live="polite">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Name</th>
            <th>Date</th>
            <th>Status</th>
            <th>Method</th>
          </tr>
        </thead>
        <tbody id="attendance-list">
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
    </section>

    <!-- Attendance Analytics -->
    <section class="mt-5">
      <h5 class="fw-bold mb-3">Attendance Statistics</h5>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead class="table-light">
            <tr>
              <th>Student</th>
              <th>Times Present</th>
              <th>Times Absent</th>
              <th>Presence Rate</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>John Doe</td>
              <td>18</td>
              <td>2</td>
              <td>
                <div class="progress" style="height: 20px;">
                  <div class="progress-bar bg-success" style="width: 90%;">90%</div>
                </div>
              </td>
            </tr>
            <tr>
              <td>Jane Smith</td>
              <td>14</td>
              <td>6</td>
              <td>
                <div class="progress" style="height: 20px;">
                  <div class="progress-bar bg-warning" style="width: 70%;">70%</div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

  </main>

  <!-- Manual Mark Modal -->
  <div class="modal fade" id="manualMarkModal" tabindex="-1" aria-labelledby="manualMarkLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="manualMarkLabel">Manual Attendance Marking</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="studentName" class="form-label">Student Name</label>
            <select id="studentName" class="form-select" required>
              <option selected disabled>Select Student</option>
              <option value="John Doe">John Doe</option>
              <option value="Jane Smith">Jane Smith</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="attendanceDate" class="form-label">Date</label>
            <input type="date" id="attendanceDate" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label d-block">Status</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="attendanceStatus" id="statusPresent" value="Present" required>
              <label class="form-check-label" for="statusPresent">Present</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="attendanceStatus" id="statusAbsent" value="Absent" required>
              <label class="form-check-label" for="statusAbsent">Absent</label>
            </div>
          </div>
          <div class="mb-3">
            <label for="attendanceMethod" class="form-label">Method</label>
            <input type="text" id="attendanceMethod" class="form-control" value="Manual" readonly>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Attendance</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Footer -->
  <footer class="footer">
    &copy; 2025 Rwanda Polytechnic | Lecturer Panel
  </footer>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const departmentSelect = document.getElementById('department');
    const optionSelect = document.getElementById('option');
    const classSelect = document.getElementById('classLevel');
    const courseSelect = document.getElementById('course');
    const startBtn = document.getElementById('start-session');
    const endBtn = document.getElementById('end-session');
    const webcamPreview = document.getElementById('webcam-preview');
    const sessionForm = document.getElementById('sessionForm');

    const optionsByDepartment = {
      ICT: ['Software Engineering', 'Networking'],
      Mechanical: ['Thermodynamics', 'Mechatronics'],
      Civil: ['Construction', 'Surveying']
    };

    const coursesByOption = {
      'Software Engineering': ['Web Dev', 'Mobile Dev'],
      'Networking': ['CCNA', 'Wireless Tech'],
      'Thermodynamics': ['Heat Transfer', 'Energy Systems'],
      'Mechatronics': ['Sensors', 'Automation'],
      'Construction': ['Masonry', 'Road Building'],
      'Surveying': ['Land Survey', 'CAD Mapping']
    };

    departmentSelect.addEventListener('change', () => {
      const dept = departmentSelect.value;
      optionSelect.innerHTML = '<option value="" disabled selected>Select Option</option>';
      classSelect.disabled = true;
      courseSelect.disabled = true;
      startBtn.disabled = true;

      if (optionsByDepartment[dept]) {
        optionsByDepartment[dept].forEach(opt => {
          const el = document.createElement('option');
          el.value = opt;
          el.textContent = opt;
          optionSelect.appendChild(el);
        });
        optionSelect.disabled = false;
      }
    });

    optionSelect.addEventListener('change', () => {
      const opt = optionSelect.value;
      courseSelect.innerHTML = '<option value="" disabled selected>Select Course</option>';
      classSelect.disabled = false;

      if (coursesByOption[opt]) {
        coursesByOption[opt].forEach(c => {
          const el = document.createElement('option');
          el.value = c;
          el.textContent = c;
          courseSelect.appendChild(el);
        });
        courseSelect.disabled = false;
      } else {
        courseSelect.disabled = true;
      }

      validateForm();
    });

    classSelect.addEventListener('change', validateForm);
    courseSelect.addEventListener('change', validateForm);

    function validateForm() {
      startBtn.disabled = !(departmentSelect.value && optionSelect.value && classSelect.value && courseSelect.value);
    }

    sessionForm.addEventListener('submit', (e) => {
      e.preventDefault();
      startBtn.disabled = true;
      endBtn.disabled = false;

      if (navigator.mediaDevices?.getUserMedia) {
        navigator.mediaDevices.getUserMedia({
            video: true
          })
          .then(stream => webcamPreview.srcObject = stream)
          .catch(err => alert("Webcam error: " + err));
      }
    });

    endBtn.addEventListener('click', () => {
      endBtn.disabled = true;
      startBtn.disabled = false;
      if (webcamPreview.srcObject) {
        webcamPreview.srcObject.getTracks().forEach(track => track.stop());
        webcamPreview.srcObject = null;
      }
    });

    document.querySelector('#manualMarkModal form').addEventListener('submit', function (e) {
      e.preventDefault();
      const student = document.getElementById('studentName').value;
      const date = document.getElementById('attendanceDate').value;
      const status = document.querySelector('input[name="attendanceStatus"]:checked').value;
      alert(`Manual attendance saved:\nStudent: ${student}\nDate: ${date}\nStatus: ${status}`);
      const modal = bootstrap.Modal.getInstance(document.getElementById('manualMarkModal'));
      modal.hide();
    });
  </script>

</body>

</html>
