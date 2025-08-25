<?php
session_start();
require_once "config.php";
require_once "session_check.php"; // ensures student is logged in

$student_id = $_SESSION['student_id'];

// Fetch all attendance records for this student
$stmt = $pdo->prepare("
    SELECT c.name AS course, s.session_date AS date, r.status
    FROM attendance_records r
    JOIN attendance_sessions s ON r.session_id = s.id
    JOIN courses c ON s.course_id = c.id
    WHERE r.student_id = ?
    ORDER BY s.session_date DESC
");
$stmt->execute([$student_id]);
$attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch unique courses for dropdown filter
$stmt2 = $pdo->prepare("
    SELECT DISTINCT c.name
    FROM attendance_records r
    JOIN attendance_sessions s ON r.session_id = s.id
    JOIN courses c ON s.course_id = c.id
    WHERE r.student_id = ?
    ORDER BY c.name
");
$stmt2->execute([$student_id]);
$courses = $stmt2->fetchAll(PDO::FETCH_COLUMN);
?>
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
    body { font-family: 'Segoe UI', sans-serif; background-color: #f5f7fa; }
    .sidebar { position: fixed; top: 0; left: 0; width: 240px; height: 100vh; background: #003366; color: white; padding-top: 20px; }
    .sidebar .logo { width: 80px; margin: 0 auto 10px; display: block; }
    .sidebar a { display: block; padding: 12px 20px; color: #fff; text-decoration: none; }
    .sidebar a:hover, .sidebar a.active { background-color: #0059b3; }
    .topbar { margin-left: 240px; background: #fff; padding: 12px 25px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; }
    .main-content { margin-left: 240px; padding: 25px; }
    .circle-container { display: flex; justify-content: center; margin-bottom: 20px; }
    .circle { width: 160px; height: 160px; border-radius: 50%; border: 12px solid #e6e6e6; position: relative; }
    .circle .inner { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 1.6rem; font-weight: bold; }
    .table-hover tbody tr:hover { background-color: #f1f9ff; }
    .footer { text-align: center; margin-left: 240px; padding: 12px; background: #f0f0f0; border-top: 1px solid #ddd; }
    @media(max-width:768px){ .sidebar{display:none;} .topbar,.main-content,.footer{margin-left:0;} }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center">
      <img src="RP_Logo.jpeg" alt="RP Logo" class="logo rounded-circle" />
      <h5>👨‍🎓 Student</h5>
      <hr />
    </div>
    <a href="students-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="attendance-records.php" class="active"><i class="fas fa-calendar-check me-2"></i> Attendance Records</a>
    <a href="request-leave.php"><i class="fas fa-file-signature me-2"></i> Request Leave</a>
    <a href="leave-status.php"><i class="fas fa-envelope-open-text me-2"></i> Leave Status</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar">
    <h5>Attendance Records</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="circle-container">
      <div class="circle" id="circle">
        <div class="inner" id="attendancePercent">0%</div>
      </div>
    </div>
    <p class="text-center mb-4">Overall Attendance</p>

    <div class="d-flex justify-content-between mb-3">
      <h6>Attendance Table</h6>
      <select id="courseFilter" class="form-select w-auto">
        <option value="All">All Courses</option>
        <?php foreach($courses as $course): ?>
          <option value="<?= htmlspecialchars($course) ?>"><?= htmlspecialchars($course) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered table-striped table-hover">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Course</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody id="attendanceTable"></tbody>
      </table>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | Student Panel
  </div>

  <!-- Scripts -->
  <script>
    const attendanceData = <?= json_encode($attendanceData) ?>;
    const tableBody = document.getElementById("attendanceTable");
    const courseFilter = document.getElementById("courseFilter");
    const percentDisplay = document.getElementById("attendancePercent");
    const circle = document.getElementById("circle");

    function loadTable(filter = "All") {
      tableBody.innerHTML = "";
      let total = 0, present = 0;

      attendanceData.forEach(record => {
        if (filter === "All" || record.course === filter) {
          total++;
          if (record.status === "Present") present++;

          let badgeClass = "secondary";
          if (record.status === "Present") badgeClass = "success";
          else if (record.status === "Absent") badgeClass = "danger";
          else if (record.status === "Excused") badgeClass = "info";

          let row = `<tr>
                      <td>${record.date}</td>
                      <td>${record.course}</td>
                      <td><span class="badge bg-${badgeClass}">${record.status}</span></td>
                     </tr>`;
          tableBody.innerHTML += row;
        }
      });

      let percent = total ? Math.round((present / total) * 100) : 0;
      percentDisplay.textContent = percent + "%";

      // update circle border color dynamically
      if (percent >= 75) {
        circle.style.borderColor = "#28a745"; // green
      } else if (percent >= 50) {
        circle.style.borderColor = "#ffc107"; // yellow
      } else {
        circle.style.borderColor = "#dc3545"; // red
      }
    }

    courseFilter.addEventListener("change", () => loadTable(courseFilter.value));
    loadTable();
  </script>
</body>
</html>
