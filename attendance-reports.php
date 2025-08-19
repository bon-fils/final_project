<?php
// Dummy data for demo — replace with DB queries in your actual app
$classes = [
  ['id' => 1, 'name' => 'Year 1 - IT'],
  ['id' => 2, 'name' => 'Year 2 - Electrical'],
  ['id' => 3, 'name' => 'Year 3 - Mechanical'],
];
$courses = [];
if (isset($_GET['class_id'])) {
  switch ($_GET['class_id']) {
    case 1:
      $courses = [
        ['id' => 101, 'name' => 'Web Development'],
        ['id' => 102, 'name' => 'Database Systems']
      ];
      break;
    case 2:
      $courses = [
        ['id' => 201, 'name' => 'Circuit Analysis'],
        ['id' => 202, 'name' => 'Power Systems']
      ];
      break;
    case 3:
      $courses = [
        ['id' => 301, 'name' => 'Thermodynamics'],
        ['id' => 302, 'name' => 'Fluid Mechanics']
      ];
      break;
  }
}
// Dummy attendance data keyed by student and date for the modal
$attendanceDetailsData = [
  'John Doe' => [
    '2025-06-01' => 'Present',
    '2025-06-02' => 'Absent',
    '2025-06-03' => 'Present',
    '2025-06-04' => 'Present',
    '2025-06-05' => 'Absent',
    '2025-06-06' => 'Present'
  ],
  'Jane Smith' => [
    '2025-06-01' => 'Present',
    '2025-06-02' => 'Present',
    '2025-06-03' => 'Absent',
    '2025-06-04' => 'Absent',
    '2025-06-05' => 'Present'
  ],
];

$attendanceData = [];
if (isset($_GET['class_id'], $_GET['course_id'])) {
  $attendanceData = [
    ['student' => 'John Doe', 'attendance_percent' => 92],
    ['student' => 'Jane Smith', 'attendance_percent' => 78],
    ['student' => 'Alice Johnson', 'attendance_percent' => 85],
  ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Attendance Reports | Lecturer | RP Attendance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f7fa;
      margin: 0;
      padding-bottom: 60px;
    }

    /* Sidebar */
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
      z-index: 1000;
    }

    .sidebar a {
      display: block;
      padding: 12px 20px;
      color: #fff;
      text-decoration: none;
      transition: background-color 0.3s ease;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background-color: #0059b3;
    }

    /* Topbar */
    .topbar {
      margin-left: 250px;
      background-color: #fff;
      padding: 12px 30px;
      border-bottom: 1px solid #ddd;
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 900;
      max-width: calc(100% - 250px);
    }

    /* Main content */
    .main-content {
      margin-left: 250px;
      padding: 30px 30px 60px;
      max-width: calc(100% - 250px);
      overflow-x: auto;
    }

    /* Footer */
    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
      position: fixed;
      bottom: 0;
      width: calc(100% - 250px);
      box-shadow: 0 -1px 5px rgba(0, 0, 0, 0.1);
      z-index: 1000;
    }

    /* Buttons container */
    .btn-group-custom {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-bottom: 25px;
      flex-wrap: wrap;
    }

    /* Table responsiveness */
    .table-responsive {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    /* Responsive tweaks */
    @media (max-width: 992px) {
      .sidebar {
        width: 200px;
      }

      .topbar,
      .main-content,
      .footer {
        margin-left: 200px;
        max-width: calc(100% - 200px);
        width: auto;
      }
    }

    @media (max-width: 768px) {
      .sidebar {
        position: relative;
        width: 100%;
        height: auto;
        padding-bottom: 10px;
      }

      .topbar,
      .main-content,
      .footer {
        margin-left: 0;
        max-width: 100%;
        width: 100%;
        padding-left: 15px;
        padding-right: 15px;
      }

      .btn-group-custom {
        justify-content: center;
      }
    }

    /* Modal styles */
    .modal-xl {
      max-width: 95%;
    }

    #attendanceTableAll {
      min-width: 1000px;
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <div class="sidebar" tabindex="0">
    <div class="text-center mb-4">
      <h4>👨‍🏫 Lecturer</h4>
      <hr style="border-color: #ffffff66;" />
    </div>
    <a href="lecturer-dashboard.php">Dashboard</a>
    <a href="lecturer-my-courses.php">My Courses</a>
    <a href="attendance-session.php">Attendance Session</a>
    <a href="attendance-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Attendance Reports</a>
    <a href="leave-requests.php">Leave Requests</a>
    <a href="index.php">Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar">
    <h5 class="m-0 fw-bold">Attendance Reports</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content container-fluid">

    <!-- Buttons -->
    <div class="btn-group-custom">
      <button id="printReport" class="btn btn-outline-primary">
        <i class="fas fa-print me-2"></i> Print Report
      </button>
      <button id="viewAllAttendanceBtn" class="btn btn-info">
        <i class="fas fa-list me-2"></i> View All Attendance Details
      </button>
    </div>

    <!-- Filter Section -->
    <form id="filterForm" method="GET" class="row g-3 mb-4 align-items-end">
      <div class="col-md-4">
        <label for="class_id" class="form-label">Select Class</label>
        <select id="class_id" name="class_id" class="form-select" onchange="this.form.submit()" required>
          <option value="">-- Choose Class --</option>
          <?php foreach ($classes as $class) : ?>
            <option value="<?= $class['id'] ?>" <?= (isset($_GET['class_id']) && $_GET['class_id'] == $class['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($class['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!empty($courses)) : ?>
        <div class="col-md-4">
          <label for="course_id" class="form-label">Select Course</label>
          <select id="course_id" name="course_id" class="form-select" required>
            <option value="">-- Choose Course --</option>
            <?php foreach ($courses as $course) : ?>
              <option value="<?= $course['id'] ?>" <?= (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($course['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4 d-flex justify-content-md-start justify-content-center">
          <button type="submit" class="btn btn-primary w-100 w-md-auto">View Report</button>
        </div>
      <?php endif; ?>
    </form>

    <!-- Attendance Report Table -->
    <?php if (!empty($attendanceData)) : ?>
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
          <thead class="table-dark">
            <tr>
              <th>Student Name</th>
              <th>Attendance %</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attendanceData as $record) :
              $statusClass = $record['attendance_percent'] >= 85 ? 'text-success' : 'text-danger';
              $statusText = $record['attendance_percent'] >= 85 ? 'Allowed' : 'Not Allowed';
            ?>
              <tr>
                <td><?= htmlspecialchars($record['student']) ?></td>
                <td><?= $record['attendance_percent'] ?>%</td>
                <td class="<?= $statusClass ?> fw-bold"><?= $statusText ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php elseif (isset($_GET['course_id'])) : ?>
      <p class="text-center text-muted">No attendance data available for this course.</p>
    <?php endif; ?>

  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; <?= date('Y') ?> Rwanda Polytechnic | Lecturer Panel
  </div>

  <!-- Modal: All Attendance Details -->
  <div class="modal fade" id="attendanceDetailsModal" tabindex="-1" aria-labelledby="attendanceDetailsLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-height: 90vh; overflow-y: auto;">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="attendanceDetailsLabel">All Students Attendance Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="allAttendanceDetailsBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" id="printAllDetailsBtn">
            <i class="fas fa-print me-2"></i> Print Details
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS & FontAwesome -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>

  <script>
    // Attendance details data from PHP passed via JSON
    const attendanceDetailsData = <?= json_encode($attendanceDetailsData); ?>;

    // Helper to get all unique sorted dates
    function getAllDates(data) {
      const datesSet = new Set();
      for (const student in data) {
        Object.keys(data[student]).forEach(date => datesSet.add(date));
      }
      return Array.from(datesSet).sort();
    }

    // Calculate attendance percent for a student
    function calculateAttendancePercent(attendanceObj) {
      const total = Object.keys(attendanceObj).length;
      const presentCount = Object.values(attendanceObj).filter(status => status === 'Present').length;
      return total === 0 ? 0 : (presentCount / total) * 100;
    }

    const modal = new bootstrap.Modal(document.getElementById('attendanceDetailsModal'));
    const modalBody = document.getElementById('allAttendanceDetailsBody');

    document.getElementById('viewAllAttendanceBtn').addEventListener('click', () => {
      modalBody.innerHTML = '';
      const allDates = getAllDates(attendanceDetailsData);

      const table = document.createElement('table');
      table.id = "attendanceTableAll";
      table.className = 'table table-bordered table-hover table-sm';

      // Table Head
      const thead = document.createElement('thead');
      thead.innerHTML = `<tr><th>Student Name</th><th>Decision</th>${allDates.map(date => `<th>${date}</th>`).join('')}</tr>`;
      table.appendChild(thead);

      // Table Body
      const tbody = document.createElement('tbody');
      for (const student in attendanceDetailsData) {
        const attendance = attendanceDetailsData[student];
        const percent = calculateAttendancePercent(attendance);

        let row = `<td>${student}</td>`;
        row += percent < 85 ?
          `<td><span class="badge bg-danger">Not Allowed to Do Exam</span></td>` :
          `<td><span class="badge bg-success">Allowed</span></td>`;

        allDates.forEach(date => {
          const status = attendance[date];
          row += status === 'Present' ?
            `<td><span class="badge bg-success">Present</span></td>` :
            status === 'Absent' ?
            `<td><span class="badge bg-danger">Absent</span></td>` :
            `<td><span class="text-muted">-</span></td>`;
        });

        const tr = document.createElement('tr');
        tr.innerHTML = row;
        tbody.appendChild(tr);
      }

      table.appendChild(tbody);
      modalBody.appendChild(table);
      modal.show();
    });

    // Print main report table
    document.getElementById('printReport').addEventListener('click', () => window.print());

    // Print modal attendance details only
    document.getElementById('printAllDetailsBtn').addEventListener('click', () => {
      const printContents = document.getElementById('allAttendanceDetailsBody').innerHTML;
      const printWindow = window.open('', '', 'width=900,height=600');
      printWindow.document.write(`
        <html>
          <head>
            <title>Print Attendance Details</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
          </head>
          <body>
            <h3 class="text-center mb-4">All Students Attendance Details</h3>
            ${printContents}
          </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.focus();

      setTimeout(() => {
        printWindow.print();
        printWindow.close();
      }, 300);
    });
  </script>
</body>

</html>