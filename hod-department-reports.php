<?php
session_start();
require_once "config.php"; // PDO connection
require_once "session_check.php";
require_role(['hod']);

// Get HoD department id
$deptStmt = $pdo->prepare("SELECT id, name FROM departments WHERE hod_id = ?");
$deptStmt->execute([$user_id]);
$department = $deptStmt->fetch();
if (!$department) die("Department not found.");

// Fetch options for this department
$optionStmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = :dept_id ORDER BY name");
$optionStmt->execute(['dept_id' => $department['id']]);
$options = $optionStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch courses for this department via attendance_sessions
$courseStmt = $pdo->prepare("
    SELECT DISTINCT c.id, c.name
    FROM courses c
    INNER JOIN attendance_sessions s ON c.id = s.course_id
    INNER JOIN students st ON st.option_id = s.option_id
    WHERE st.department_id = :dept_id
    ORDER BY c.name
");
$courseStmt->execute(['dept_id' => $department['id']]);
$coursesAll = $courseStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX filter request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $option_id = $_POST['option'] ?? '';
    $course_id = $_POST['course'] ?? '';
    $year_level = $_POST['year'] ?? '';
    $start_date = $_POST['startDate'] ?? '';
    $end_date = $_POST['endDate'] ?? '';

    if ($option_id && $course_id && $year_level && $start_date && $end_date) {
        $attStmt = $pdo->prepare("
            SELECT DATE(ar.recorded_at) AS date,
                   COUNT(CASE WHEN ar.status=1 THEN 1 END) AS present,
                   COUNT(CASE WHEN ar.status=0 THEN 1 END) AS absent
            FROM attendance_records ar
            INNER JOIN students s ON ar.student_id = s.id
            INNER JOIN attendance_sessions sess ON ar.session_id = sess.id
            WHERE s.department_id = :dept_id
              AND s.option_id = :option_id
              AND s.year_level = :year_level
              AND sess.course_id = :course_id
              AND sess.option_id = :option_id
              AND DATE(ar.recorded_at) BETWEEN :start_date AND :end_date
            GROUP BY DATE(ar.recorded_at)
            ORDER BY DATE(ar.recorded_at) ASC
        ");
        $attStmt->execute([
            'dept_id' => $department['id'],
            'option_id' => $option_id,
            'year_level' => $year_level,
            'course_id' => $course_id,
            'start_date' => $start_date,
            'end_date' => $end_date
        ]);
        $attendanceData = $attStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    header('Content-Type: application/json');
    echo json_encode($attendanceData ?? []);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Department Reports | HoD | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { font-family:'Segoe UI', sans-serif; background:#f5f7fa; margin:0; }
.sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:#003366; color:white; padding-top:20px; }
.sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none; }
.sidebar a:hover, .sidebar a.active { background:#0059b3; }
.topbar { margin-left:250px; background:#fff; padding:10px 30px; border-bottom:1px solid #ddd; }
.main-content { margin-left:250px; padding:30px; }
.card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05); margin-bottom:30px; }
.btn-primary { background:#0066cc; border:none; }
.btn-primary:hover { background:#004b99; }
.footer { text-align:center; margin-left:250px; padding:15px; font-size:0.9rem; color:#666; background:#f0f0f0; }
@media(max-width:768px){ .sidebar,.topbar,.main-content,.footer{ margin-left:0!important; width:100%; } .sidebar{ display:none; } }
</style>
</head>
<body>
<div class="sidebar">
<div class="text-center mb-4"><h4>👔 Head of Department</h4><hr style="border-color:#ffffff66;"></div>
<a href="hod-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
<a href="hod-department-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Department Reports</a>
<a href="hod-leave-management.php"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave Requests</a>
<a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
<h5 class="m-0 fw-bold">Department Attendance Reports</h5>
<span>HoD Panel</span>
</div>

<div class="main-content">
<div class="card p-4">
  <form id="filterForm" class="row g-3 align-items-center">
    <div class="col-md-3">
      <label for="optionSelect" class="form-label">Select Option</label>
      <select id="optionSelect" class="form-select" required>
        <option value="" selected disabled>Choose an option...</option>
        <?php foreach($options as $opt): ?>
        <option value="<?= $opt['id'] ?>"><?= htmlspecialchars($opt['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label for="yearSelect" class="form-label">Year/Level</label>
      <select id="yearSelect" class="form-select" required>
        <option value="" selected disabled>Select year...</option>
        <option value="Year 1">Year 1</option>
        <option value="Year 2">Year 2</option>
        <option value="Year 3">Year 3</option>
      </select>
    </div>
    <div class="col-md-3">
      <label for="courseSelect" class="form-label">Select Course</label>
      <select id="courseSelect" class="form-select" required>
        <option value="" selected disabled>Choose a course...</option>
        <?php foreach($coursesAll as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label for="startDate" class="form-label">Start Date</label>
      <input type="date" id="startDate" class="form-control" max="" required>
    </div>
    <div class="col-md-2">
      <label for="endDate" class="form-label">End Date</label>
      <input type="date" id="endDate" class="form-control" max="" required>
    </div>
    <div class="col-12 d-flex justify-content-end">
      <button type="submit" class="btn btn-primary px-4">Filter</button>
    </div>
  </form>
</div>

<div class="card p-4">
  <h6 class="mb-3">Attendance Overview</h6>
  <canvas id="attendanceChart" height="150"></canvas>
</div>

<div class="card p-4">
  <h6 class="mb-3">Attendance Summary Table</h6>
  <table class="table table-bordered table-hover">
    <thead class="table-light">
      <tr>
        <th>Date</th>
        <th>Option</th>
        <th>Course</th>
        <th>Year/Level</th>
        <th>Present</th>
        <th>Absent</th>
        <th>Attendance %</th>
      </tr>
    </thead>
    <tbody id="attendanceTableBody"></tbody>
  </table>
</div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | HoD Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const today = new Date().toISOString().split("T")[0];
document.getElementById("startDate").setAttribute("max", today);
document.getElementById("endDate").setAttribute("max", today);

const optionSelect = document.getElementById('optionSelect');
const courseSelect = document.getElementById('courseSelect');
let attendanceChart;
const ctx = document.getElementById('attendanceChart').getContext('2d');

document.getElementById('filterForm').addEventListener('submit', e=>{
    e.preventDefault();
    const optionId = optionSelect.value;
    const courseId = courseSelect.value;
    const yearLevel = document.getElementById('yearSelect').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if(!optionId || !courseId || !yearLevel || !startDate || !endDate){
        alert('Please fill all fields.');
        return;
    }

    fetch('',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:`option=${optionId}&course=${courseId}&year=${yearLevel}&startDate=${startDate}&endDate=${endDate}`
    })
    .then(res=>res.json())
    .then(data=>{
        const labels = data.map(d=>d.date);
        const presentData = data.map(d=>d.present);
        const absentData = data.map(d=>d.absent);

        if(attendanceChart) attendanceChart.destroy();
        attendanceChart = new Chart(ctx,{
            type:'bar',
            data:{labels,datasets:[
                {label:'Present',data:presentData,backgroundColor:'rgba(54,162,235,0.7)'},
                {label:'Absent',data:absentData,backgroundColor:'rgba(255,99,132,0.7)'}
            ]},
            options:{responsive:true,scales:{y:{beginAtZero:true}}}
        });

        const tbody = document.getElementById('attendanceTableBody');
        tbody.innerHTML = '';
        data.forEach(d=>{
            const total = d.present + d.absent;
            const percent = total===0?0:Math.round((d.present/total)*100);
            tbody.insertAdjacentHTML('beforeend',`
                <tr>
                    <td>${d.date}</td>
                    <td>${optionSelect.selectedOptions[0].text}</td>
                    <td>${courseSelect.selectedOptions[0].text}</td>
                    <td>${yearLevel}</td>
                    <td>${d.present}</td>
                    <td>${d.absent}</td>
                    <td>${percent}%</td>
                </tr>
            `);
        });
    });
});
</script>
</body>
</html>
