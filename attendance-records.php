<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance Records | Student | RP Attendance System</title>

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

    .attendance-table {
      background-color: #fff;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      overflow-x: auto;
      margin-bottom: 40px;
    }

    table {
      margin-bottom: 0;
    }

    /* Circular Progress Styles */
    .progress-circle {
      width: 120px;
      height: 120px;
      margin: 20px auto;
      position: relative;
    }

    .progress-circle svg {
      transform: rotate(-90deg);
      width: 120px;
      height: 120px;
    }

    .progress-circle circle {
      fill: none;
      stroke-width: 12;
      cx: 60;
      cy: 60;
      r: 54;
    }

    .progress-circle .bg {
      stroke: #ddd;
    }

    .progress-circle .progress {
      stroke: #0066cc;
      stroke-linecap: round;
      stroke-dasharray: 339.292; /* 2 * PI * 54 */
      stroke-dashoffset: 339.292;
      transition: stroke-dashoffset 1.5s ease-out;
    }

    .progress-circle .percentage {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      font-weight: 600;
      font-size: 1.4rem;
      color: #0066cc;
    }

    .course-title {
      text-align: center;
      font-weight: 600;
      margin-top: 8px;
      color: #003366;
    }

    /* Responsive adjustments */
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
      <h4>👨🎓 Student</h4>
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="students-dashboard.php">Dashboard</a>
    <a href="attendance-records.php" class="active"><i class="fas fa-calendar-check me-2"></i> Attendance Records</a>
    <a href="request-leave.php"><i class="fas fa-file-signature me-2"></i> Request Leave</a>
    <a href="leave-status.php"><i class="fas fa-info-circle me-2"></i> Leave Status</a>
    <a href="index.php">Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Attendance Records</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">

    <div class="row justify-content-center">
      <!-- Overall Attendance -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="progress-circle" id="overallAttendanceCircle">
          <svg>
            <circle class="bg" cx="60" cy="60" r="54"></circle>
            <circle class="progress"></circle>
          </svg>
          <div class="percentage" id="overallAttendancePct">0%</div>
          <div class="course-title">Overall Attendance</div>
        </div>
      </div>

      <!-- Software Engineering -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="progress-circle" id="seAttendanceCircle">
          <svg>
            <circle class="bg" cx="60" cy="60" r="54"></circle>
            <circle class="progress"></circle>
          </svg>
          <div class="percentage" id="seAttendancePct">0%</div>
          <div class="course-title">Software Engineering</div>
        </div>
      </div>

      <!-- Networking -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="progress-circle" id="netAttendanceCircle">
          <svg>
            <circle class="bg" cx="60" cy="60" r="54"></circle>
            <circle class="progress"></circle>
          </svg>
          <div class="percentage" id="netAttendancePct">0%</div>
          <div class="course-title">Networking</div>
        </div>
      </div>

      <!-- Electrical Engineering -->
      <div class="col-12 col-md-6 col-lg-3">
        <div class="progress-circle" id="eeAttendanceCircle">
          <svg>
            <circle class="bg" cx="60" cy="60" r="54"></circle>
            <circle class="progress"></circle>
          </svg>
          <div class="percentage" id="eeAttendancePct">0%</div>
          <div class="course-title">Electrical Engineering</div>
        </div>
      </div>
    </div>

    <!-- Attendance Table -->
    <div class="attendance-table mt-4 p-3">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th scope="col">Course</th>
            <th scope="col">Date</th>
            <th scope="col">Status</th>
          </tr>
        </thead>
        <tbody id="attendance-table-body">
          <!-- Sample data will be populated here -->
        </tbody>
      </table>
    </div>

  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Student Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Sample attendance data (replace with real data fetching)
    const attendanceData = [
      { course: 'Software Engineering', date: '2025-06-15', status: 'Present' },
      { course: 'Networking', date: '2025-06-15', status: 'Absent' },
      { course: 'Electrical Engineering', date: '2025-06-14', status: 'Present' },
      { course: 'Software Engineering', date: '2025-06-14', status: 'Present' },
      { course: 'Networking', date: '2025-06-13', status: 'Present' },
      { course: 'Electrical Engineering', date: '2025-06-13', status: 'Absent' },
      // More records...
    ];

    // Animate progress circle stroke from 0 to target
    function animateProgress(circleElement, targetPercent) {
      const circumference = 2 * Math.PI * 54;
      let start = null;
      const duration = 1500;
      const initialOffset = circumference;
      const targetOffset = circumference - (targetPercent / 100) * circumference;

      function step(timestamp) {
        if (!start) start = timestamp;
        const elapsed = timestamp - start;
        const progress = Math.min(elapsed / duration, 1);
        const currentOffset = initialOffset - progress * (initialOffset - targetOffset);
        circleElement.style.strokeDashoffset = currentOffset;

        if (progress < 1) {
          requestAnimationFrame(step);
        }
      }

      requestAnimationFrame(step);
    }

    // Update attendance circles with animated progress
    function updateAttendanceCircles(data) {
      const rates = {
        overall: { present: 0, total: 0 },
        'Software Engineering': { present: 0, total: 0 },
        'Networking': { present: 0, total: 0 },
        'Electrical Engineering': { present: 0, total: 0 },
      };

      data.forEach(record => {
        rates.overall.total++;
        if (record.status === 'Present') rates.overall.present++;

        if (rates[record.course]) {
          rates[record.course].total++;
          if (record.status === 'Present') rates[record.course].present++;
        }
      });

      // Animate and update text
      const overallCircle = document.querySelector('#overallAttendanceCircle .progress');
      const overallPercent = rates.overall.total ? (rates.overall.present / rates.overall.total) * 100 : 0;
      animateProgress(overallCircle, overallPercent);
      document.getElementById('overallAttendancePct').textContent = overallPercent.toFixed(1) + '%';

      const seCircle = document.querySelector('#seAttendanceCircle .progress');
      const sePercent = rates['Software Engineering'].total ? (rates['Software Engineering'].present / rates['Software Engineering'].total) * 100 : 0;
      animateProgress(seCircle, sePercent);
      document.getElementById('seAttendancePct').textContent = sePercent.toFixed(1) + '%';

      const netCircle = document.querySelector('#netAttendanceCircle .progress');
      const netPercent = rates['Networking'].total ? (rates['Networking'].present / rates['Networking'].total) * 100 : 0;
      animateProgress(netCircle, netPercent);
      document.getElementById('netAttendancePct').textContent = netPercent.toFixed(1) + '%';

      const eeCircle = document.querySelector('#eeAttendanceCircle .progress');
      const eePercent = rates['Electrical Engineering'].total ? (rates['Electrical Engineering'].present / rates['Electrical Engineering'].total) * 100 : 0;
      animateProgress(eeCircle, eePercent);
      document.getElementById('eeAttendancePct').textContent = eePercent.toFixed(1) + '%';
    }

    // Populate attendance table rows
    function populateAttendanceTable(data) {
      const tbody = document.getElementById('attendance-table-body');
      tbody.innerHTML = '';
      data.forEach(record => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${record.course}</td>
          <td>${record.date}</td>
          <td>${record.status === 'Present' ? '<span class="badge bg-success">Present</span>' : '<span class="badge bg-danger">Absent</span>'}</td>
        `;
        tbody.appendChild(tr);
      });
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
      populateAttendanceTable(attendanceData);
      updateAttendanceCircles(attendanceData);
    });
  </script>
</body>

</html>
