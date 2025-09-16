<?php
session_start();

// Prevent caching to stop back-button after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once "config.php"; // PDO connection

// Ensure user is logged in and is HoD
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// Get user info
$stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user || $user['role'] !== 'hod') {
    die("Access denied. You must be a Head of Department to access this page.");
}

// Get HoD department id
$deptStmt = $pdo->prepare("SELECT id, name FROM departments WHERE hod_id = ?");
$deptStmt->execute([$user_id]);
$department = $deptStmt->fetch();
if (!$department) die("Department not found.");

// Fetch options for this department
$optionStmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = :dept_id ORDER BY name");
$optionStmt->execute(['dept_id' => $department['id']]);
$options = $optionStmt->fetchAll(PDO::FETCH_ASSOC);

// Attendance summary
$attStmt = $pdo->prepare("
    SELECT ROUND(AVG(status=1)*100,0) AS avg_attendance
    FROM attendance_records ar
    INNER JOIN students s ON ar.student_id = s.id
    WHERE s.department_id = :dept_id
");
$attStmt->execute(['dept_id' => $department['id']]);
$attendance = $attStmt->fetch();

// Pending leave requests
$leaveStmt = $pdo->prepare("
    SELECT COUNT(*) AS pending_count
    FROM leave_requests lr
    INNER JOIN students s ON lr.student_id = s.id
    WHERE s.department_id = :dept_id AND lr.status='pending'
");
$leaveStmt->execute(['dept_id' => $department['id']]);
$pendingLeaves = $leaveStmt->fetch();

// Handle AJAX filter
if (isset($_GET['ajax']) && $_GET['ajax']==1) {
    $userType = $_GET['userType'] ?? '';
    $optionName = $_GET['option'] ?? '';
    $sex = $_GET['sex'] ?? '';
    $eduLevel = $_GET['eduLevel'] ?? '';

    $filteredUsers = [];

    if ($userType === 'student') {
        $sql = "SELECT s.id, s.first_name, s.last_name, s.sex, s.year_level, o.name AS option_name
                FROM students s
                INNER JOIN options o ON s.option_id = o.id
                WHERE s.department_id = :dept_id";
        $params = ['dept_id' => $department['id']];
        if ($optionName) { $sql .= " AND o.name = :option"; $params['option']=$optionName; }
        if ($sex && $sex !== 'All') { $sql .= " AND s.sex = :sex"; $params['sex']=$sex; }
        if ($eduLevel) { $sql .= " AND s.year_level = :eduLevel"; $params['eduLevel']=$eduLevel; }

    } elseif ($userType === 'lecturer') {
        $sql = "SELECT l.id, l.first_name, l.last_name, l.gender, l.education_level
                FROM lecturers l
                WHERE l.department_id = :dept_id";
        $params = ['dept_id' => $department['id']];
        if ($sex && $sex !== 'All') { $sql .= " AND l.gender = :sex"; $params['sex']=$sex; }
        if ($eduLevel) { $sql .= " AND l.education_level = :eduLevel"; $params['eduLevel']=$eduLevel; }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $filteredUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($filteredUsers);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoD Dashboard | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body { font-family:'Segoe UI',sans-serif; background:#f5f7fa; margin:0; }
.sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:#003366; color:white; padding-top:20px; }
.sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none; }
.sidebar a:hover { background:#0059b3; }
.topbar { margin-left:250px; background:#fff; padding:10px 30px; border-bottom:1px solid #ddd; }
.main-content { margin-left:250px; padding:30px; }
.card { border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05); }
.btn-primary { background-color:#0066cc; border:none; }
.btn-primary:hover { background-color:#004b99; }
.footer { text-align:center; margin-left:250px; padding:15px; font-size:0.9rem; color:#666; background:#f0f0f0; }
.table th, .table td { vertical-align:middle; }
@media(max-width:768px) { .sidebar, .topbar, .main-content, .footer { margin-left:0 !important; width:100%; } .sidebar { display:none; } }
</style>
</head>
<body>
<div class="sidebar">
<div class="text-center mb-4"><h4>👔 Head of Department</h4><hr style="border-color:#ffffff66;"></div>
<a href="hod-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
<a href="hod-department-reports.php"><i class="fas fa-chart-bar me-2"></i> Department Reports</a>
<a href="hod-leave-management.php"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave Requests</a>
<a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
<h5 class="m-0 fw-bold">HoD Dashboard</h5>
<span>HoD Panel</span>
</div>

<div class="main-content">
<div class="row g-4">
<div class="col-md-6 col-lg-4">
  <div class="card p-4">
    <h6 class="mb-3">Department Attendance</h6>
    <div class="display-4 fw-bold text-primary"><?= htmlspecialchars($attendance['avg_attendance'] ?? 0) ?>%</div>
    <small class="text-muted">Average attendance this semester</small>
  </div>
</div>

<div class="col-md-6 col-lg-4">
  <div class="card p-4">
    <h6 class="mb-3">Pending Leave Requests</h6>
    <div class="display-4 fw-bold text-warning"><?= htmlspecialchars($pendingLeaves['pending_count'] ?? 0) ?></div>
    <small class="text-muted">Requests awaiting your approval</small>
  </div>
</div>

<div class="col-md-6 col-lg-4">
  <div class="card p-4 d-flex flex-column justify-content-center align-items-start">
    <h6 class="mb-3">Quick Links</h6>
    <a href="hod-department-reports.php" class="btn btn-primary mb-2 w-100"><i class="fas fa-chart-bar me-2"></i> View Reports</a>
    <a href="hod-leave-management.php" class="btn btn-primary mb-2 w-100"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave</a>
    <a href="hod-manage-lecturers.php" class="btn btn-primary w-100"><i class="fas fa-user-plus me-2"></i> Manage Lecturers</a>
  </div>
</div>

<div class="col-12">
  <div class="card p-4 mt-3">
    <h6 class="mb-3">Filter Records</h6>
    <div class="row g-3">
      <div class="col-md-3">
        <label class="form-label">User Type</label>
        <select class="form-select" id="userType" onchange="updateEducationLevel(); fetchFilteredUsers();">
          <option selected disabled>Select Type</option>
          <option value="student">Student</option>
          <option value="lecturer">Lecturer</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Option</label>
        <select class="form-select" id="optionSelect" onchange="fetchFilteredUsers();">
          <option selected disabled>Choose Option</option>
          <?php foreach($options as $opt): ?>
            <option value="<?= htmlspecialchars($opt['name']) ?>"><?= htmlspecialchars($opt['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Sex</label>
        <select class="form-select" id="sexSelect" onchange="fetchFilteredUsers();">
          <option>All</option>
          <option>Male</option>
          <option>Female</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label" id="eduLabel">Education Level</label>
        <select class="form-select" id="eduLevel" onchange="fetchFilteredUsers();">
          <option selected disabled>Select</option>
        </select>
      </div>
    </div>
  </div>
</div>

<div class="col-12 mt-3">
  <div class="card p-4">
    <h6 class="mb-3">Filtered Results</h6>
    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle" id="resultsTable">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Sex</th>
            <th>Option / Education Level</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

</div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | HoD Panel</div>

<script>
function updateEducationLevel() {
  const userType = document.getElementById("userType").value;
  const eduLabel = document.getElementById("eduLabel");
  const eduLevel = document.getElementById("eduLevel");
  eduLevel.innerHTML = "";
  if(userType === "student") {
    eduLabel.textContent = "Year / Level";
    ["Year 1","Year 2","Year 3"].forEach(y=>{ const o=document.createElement("option"); o.value=y; o.text=y; eduLevel.add(o); });
  } else if(userType === "lecturer") {
    eduLabel.textContent = "Education Level";
    ["A1","A0","Masters"].forEach(l=>{ const o=document.createElement("option"); o.value=l; o.text=l; eduLevel.add(o); });
  }
}

function fetchFilteredUsers() {
  const userType = document.getElementById("userType").value;
  const option = document.getElementById("optionSelect").value;
  const sex = document.getElementById("sexSelect").value;
  const eduLevel = document.getElementById("eduLevel").value;

  const params = new URLSearchParams({
    ajax:1,
    userType, option, sex, eduLevel
  });

  fetch("?"+params.toString())
    .then(res=>res.json())
    .then(data=>{
      const tbody = document.querySelector("#resultsTable tbody");
      tbody.innerHTML = "";
      data.forEach((u,i)=>{
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${i+1}</td>
                        <td>${u.first_name}</td>
                        <td>${u.last_name}</td>
                        <td>${u.sex??u.gender}</td>
                        <td>${u.option_name??u.education_level}</td>`;
        tbody.appendChild(tr);
      });
    });
}

window.onload = updateEducationLevel;
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
