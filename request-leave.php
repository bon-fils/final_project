<?php
session_start();
require_once "config.php"; // $pdo connection
require_once "session_check.php";
require_role(['student']);

// Ensure student is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: index.php");
    exit;
}

// Get student info
$stmt = $pdo->prepare("SELECT id, option_id, year_level FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? null;

if (!$student_id) {
    die("Student record not found.");
}

$successMsg = $errorMsg = "";

// Fetch available courses
$courses = [];
$sql = "SELECT c.id, c.name
        FROM courses c
        INNER JOIN course_assignments ca ON ca.course_id = c.id
        WHERE ca.option_id = :option_id AND ca.year_level = :year_level";
$stmt = $pdo->prepare($sql);
$stmt->execute(['option_id' => $student['option_id'], 'year_level' => $student['year_level']]);
$courses = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestTo  = $_POST['requestTo'] ?? '';
    $courseId   = $_POST['courseId'] ?? null;
    $fromDate   = $_POST['fromDate'] ?? '';
    $toDate     = $_POST['toDate'] ?? '';
    $reason     = trim($_POST['reason'] ?? '');

    $today = date('Y-m-d');

    // Validation
    if (!$requestTo) {
        $errorMsg = "Please select who to request leave to.";
    } elseif ($requestTo === 'lecturer' && !$courseId) {
        $errorMsg = "Please select a course for lecturer leave request.";
    } elseif (!$reason) {
        $errorMsg = "Reason for leave is required.";
    } elseif ($fromDate < $today || $toDate < $today) {
        $errorMsg = "Leave cannot be requested for past dates.";
    } elseif (new DateTime($fromDate) > new DateTime($toDate)) {
        $errorMsg = "From Date cannot be later than To Date.";
    } else {
        // Duplicate check: prevent overlapping pending requests to same recipient scope
        $dupSql = "SELECT COUNT(*) FROM leave_requests
                   WHERE student_id = :sid AND status = 'pending'
                     AND (
                        (:is_lecturer = 1 AND course_id = :cid)
                        OR (:is_lecturer = 0 AND course_id IS NULL)
                     )
                     AND NOT (to_date < :from_date OR from_date > :to_date)";
        $isLect = $requestTo === 'lecturer' ? 1 : 0;
        $dup = $pdo->prepare($dupSql);
        $dup->bindValue(':sid', $student_id, PDO::PARAM_INT);
        $dup->bindValue(':is_lecturer', $isLect, PDO::PARAM_INT);
        $dup->bindValue(':cid', $isLect ? (int)$courseId : null, $isLect ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $dup->bindValue(':from_date', $fromDate, PDO::PARAM_STR);
        $dup->bindValue(':to_date', $toDate, PDO::PARAM_STR);
        $dup->execute();
        $existsOverlap = (int)$dup->fetchColumn() > 0;
        if ($existsOverlap) {
            $errorMsg = "You already have a pending leave request overlapping these dates for the selected recipient.";
        }

        // File upload
        $supporting_file = null;
        if (!$errorMsg && !empty($_FILES['supportingFile']['name'])) {
            $uploadDir = "uploads/leave_docs/";
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $fileName = time() . "_" . basename($_FILES['supportingFile']['name']);
            $targetPath = $uploadDir . $fileName;
            $allowedExt = ['pdf','doc','docx','jpg','jpeg','png'];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExt) && move_uploaded_file($_FILES['supportingFile']['tmp_name'], $targetPath)) {
                $supporting_file = $fileName;
            } else {
                $errorMsg = "Invalid file type or upload error.";
            }
        }

        // Insert into DB
        if (!$errorMsg) {
            if ($requestTo === 'lecturer') {
                $sqlInsert = "INSERT INTO leave_requests
                              (student_id, reason, supporting_file, status, requested_at, reviewed_by, reviewed_at, course_id, from_date, to_date)
                              VALUES (:student_id, :reason, :supporting_file, 'pending', NOW(), NULL, NULL, :course_id, :from_date, :to_date)";
                $stmt = $pdo->prepare($sqlInsert);
                $stmt->bindValue(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
                $stmt->bindValue(':supporting_file', $supporting_file, PDO::PARAM_STR);
                $stmt->bindValue(':course_id', $courseId, PDO::PARAM_INT);
                $stmt->bindValue(':from_date', $fromDate, PDO::PARAM_STR);
                $stmt->bindValue(':to_date', $toDate, PDO::PARAM_STR);
                $stmt->execute();
            } else { // HOD
                $sqlInsert = "INSERT INTO leave_requests
                              (student_id, reason, supporting_file, status, requested_at, reviewed_by, reviewed_at, from_date, to_date)
                              VALUES (:student_id, :reason, :supporting_file, 'pending', NOW(), NULL, NULL, :from_date, :to_date)";
                $stmt = $pdo->prepare($sqlInsert);
                $stmt->bindValue(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
                $stmt->bindValue(':supporting_file', $supporting_file, PDO::PARAM_STR);
                $stmt->bindValue(':from_date', $fromDate, PDO::PARAM_STR);
                $stmt->bindValue(':to_date', $toDate, PDO::PARAM_STR);
                $stmt->execute();
            }
            $successMsg = "Leave request submitted successfully!";
        }
    }
}

// Fetch last 5 leave requests
$stmt = $pdo->prepare("SELECT id, reason, supporting_file, status, requested_at, from_date, to_date 
                       FROM leave_requests 
                       WHERE student_id = ? 
                       ORDER BY requested_at DESC 
                       LIMIT 5");
$stmt->execute([$student_id]);
$leave_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Request Leave | Student | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{ --primary:#003366; --primary-600:#0059b3; --accent:#0066cc; --muted:#6b7280; --card:#ffffff; --border:#eef1f6; }
body { font-family:'Segoe UI',sans-serif; background: radial-gradient(1200px 600px at 10% -10%, #e6f0ff 0%, transparent 40%), radial-gradient(800px 500px at 100% 0%, #f3f7ff 0%, transparent 55%), #f5f7fa; margin:0; }
.sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:#003366; color:white; padding-top:20px; overflow-y:auto; }
.sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none; }
.sidebar a:hover, .sidebar a.active { background:#0059b3; }
.topbar { margin-left:250px; background:#fff; padding:12px 30px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:10; }
.main-content { margin-left:250px; padding:40px 30px; max-width:900px; }
.footer { text-align:center; margin-left:250px; padding:15px; font-size:0.9rem; color:#666; background:#f0f0f0; }
label { font-weight:600; color:#003366; }
.form-container, .table-container { background:rgba(255,255,255,0.9); padding:28px; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.07); margin-bottom:30px; border:1px solid var(--border); backdrop-filter: saturate(120%) blur(4px); }
.form-header { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.form-header .icon { width:42px; height:42px; display:flex; align-items:center; justify-content:center; border-radius:10px; background:linear-gradient(135deg, var(--primary), var(--accent)); color:#fff; box-shadow:0 6px 14px rgba(0,51,102,0.35); }
.form-header .icon i{ transform: translateZ(0); }
.hint { font-size:0.85rem; color:var(--muted); }
input.form-control, select.form-control, textarea.form-control { border-radius:12px; border:1px solid #d9dee6; transition: all .2s ease; }
.input-group-text{ background:#f3f6fb; border:1px solid #e5ebf3; color:#4b5563; border-right:0; }
.input-group .form-control{ border-left:0; }
.form-control:hover{ border-color:#b8c6d9; }
input.form-control:focus, select.form-control:focus, textarea.form-control:focus { border-color:#0066cc; box-shadow:0 0 0 0.2rem rgba(0,102,204,0.15); }
.btn-primary { background-color:var(--primary); border-color:var(--primary); border-radius:12px; box-shadow:0 8px 18px rgba(0,51,102,0.25); transition: transform .08s ease, box-shadow .2s ease; }
.btn-primary:hover { background-color:var(--primary-600); border-color:var(--primary-600); box-shadow:0 10px 22px rgba(0,89,179,0.28); }
.btn-primary:active{ transform: translateY(1px); }
.badge-rounded { border-radius:30px; padding:6px 10px; font-weight:600; }
.file-info { font-size:0.85rem; color:var(--muted); margin-top:6px; }
.section-title { display:flex; align-items:center; gap:10px; }
.section-title .dot { width:8px; height:8px; border-radius:50%; background:linear-gradient(135deg, var(--accent), #56a3ff); display:inline-block; box-shadow:0 0 0 4px rgba(0,102,204,0.08); }
.table{ border-radius:12px; overflow:hidden; }
.table thead.table-dark{ background:linear-gradient(135deg, var(--primary), var(--primary-600)); border:none; }
.table-striped tbody tr:nth-of-type(odd){ --bs-table-accent-bg:#f7fbff; }
.table tbody tr:hover{ background:#eef6ff; }
.alert{ border-radius:12px; }
@media(max-width:768px) { .sidebar, .topbar, .main-content, .footer { margin-left:0 !important; width:100%; } .sidebar { display:none; } .main-content { padding:20px; } }
</style>
</head>
<body>

<div class="sidebar">
<div class="text-center mb-4"><h4>🎓 Student</h4><hr style="border-color:#ffffff66;"></div>
<a href="students-dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
<a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i>Attendance Records</a>
<a href="request-leave.php" class="active"><i class="fas fa-file-signature me-2"></i>Request Leave</a>
<a href="leave-status.php"><i class="fas fa-info-circle me-2"></i>Leave Status</a>
<a href="index.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
<h5 class="m-0 fw-bold">Request Leave</h5>
<span>RP Attendance System</span>
</div>

<div class="main-content">

<div class="form-container">
<div class="form-header"><div class="icon"><i class="fas fa-file-signature"></i></div><div>
<h5 class="m-0">Submit a Leave Request</h5>
<div class="hint">Fill out the details below. Approved requests will reflect in your records.</div>
</div></div>
<?php if($errorMsg): ?><div class="alert alert-danger"><i class="fas fa-triangle-exclamation me-2"></i><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>
<?php if($successMsg): ?><div class="alert alert-success"><i class="fas fa-circle-check me-2"></i><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>

<form id="leaveRequestForm" method="post" enctype="multipart/form-data">
<div class="row g-3">
  <div class="col-md-6">
    <label for="requestTo" class="form-label">Request To</label>
    <div class="input-group">
      <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
      <select id="requestTo" name="requestTo" class="form-control" required>
        <option value="">-- Select recipient --</option>
        <option value="hod">Head of Department</option>
        <option value="lecturer">Lecturer</option>
      </select>
    </div>
    <div class="hint mt-1">Choose HoD for general absence or Lecturer for course-related leave.</div>
  </div>

  <div class="col-md-6" id="courseSelectWrapper" style="display:none;">
    <label for="courseId" class="form-label">Select Course</label>
    <div class="input-group">
      <span class="input-group-text"><i class="fas fa-book"></i></span>
      <select id="courseId" name="courseId" class="form-control">
        <option value="">-- Select course --</option>
        <?php foreach ($courses as $course): ?>
        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="col-md-6">
    <label for="fromDate" class="form-label">From Date</label>
    <div class="input-group">
      <span class="input-group-text"><i class="fas fa-calendar-day"></i></span>
      <input type="date" id="fromDate" name="fromDate" class="form-control" required />
    </div>
  </div>

  <div class="col-md-6">
    <label for="toDate" class="form-label">To Date</label>
    <div class="input-group">
      <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
      <input type="date" id="toDate" name="toDate" class="form-control" required />
    </div>
  </div>

  <div class="col-12">
    <label for="reason" class="form-label">Reason</label>
    <textarea id="reason" name="reason" class="form-control" rows="4" placeholder="E.g., Medical appointment on 05 Oct; attach letter if available." maxlength="500" required></textarea>
    <div class="d-flex justify-content-between mt-1">
      <span class="hint">Max 500 characters.</span>
      <span class="hint" id="reasonCount">0/500</span>
    </div>
  </div>

  <div class="col-12">
    <label for="supportingFile" class="form-label">Attach Supporting Document (optional)</label>
    <div class="input-group">
      <span class="input-group-text"><i class="fas fa-paperclip"></i></span>
      <input type="file" id="supportingFile" name="supportingFile" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
    </div>
    <div class="file-info">Accepted: PDF, DOC, DOCX, JPG, PNG • Max 5MB</div>
  </div>

  <div class="col-12 text-end mt-3">
    <button type="submit" class="btn btn-primary px-4"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
  </div>
</div>
</form>
</div>

<div class="table-container">
<div class="section-title"><span class="dot"></span><h5 class="m-0 fw-bold">Last 5 Leave Requests</h5></div>
<div class="table-responsive">
<table class="table table-striped table-bordered">
<thead class="table-dark">
<tr>
<th>#</th>
<th>Reason</th>
<th>File</th>
<th>From</th>
<th>To</th>
<th>Status</th>
<th>Requested At</th>
</tr>
</thead>
<tbody>
<?php if(count($leave_requests)>0): foreach($leave_requests as $i=>$req): ?>
<tr>
<td><?= $i+1 ?></td>
<td><?= htmlspecialchars($req['reason']) ?></td>
<td><?php if($req['supporting_file']): ?><a href="uploads/leave_docs/<?= htmlspecialchars($req['supporting_file']) ?>" target="_blank">View</a><?php else: ?>-<?php endif; ?></td>
<td><?= htmlspecialchars($req['from_date']) ?></td>
<td><?= htmlspecialchars($req['to_date']) ?></td>
<td>
<?php if($req['status']=='pending'): ?><span class="badge badge-rounded bg-warning text-dark">Pending</span>
<?php elseif($req['status']=='approved'): ?><span class="badge badge-rounded bg-success">Approved</span>
<?php else: ?><span class="badge badge-rounded bg-danger">Rejected</span><?php endif; ?>
</td>
<td><?= date("d M Y, H:i", strtotime($req['requested_at'])) ?></td>
</tr>
<?php endforeach; else: ?>
<tr><td colspan="7" class="text-center">No leave requests found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Student Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const requestTo = document.getElementById('requestTo');
const courseSelectWrapper = document.getElementById('courseSelectWrapper');
const reason = document.getElementById('reason');
const reasonCount = document.getElementById('reasonCount');
requestTo.addEventListener('change', function() {
    if(this.value==='lecturer'){
        courseSelectWrapper.style.display='block';
        document.getElementById('courseId').setAttribute('required','required');
    } else {
        courseSelectWrapper.style.display='none';
        document.getElementById('courseId').removeAttribute('required');
    }
});

// Prevent past dates
const today = new Date().toISOString().split('T')[0];
document.getElementById('fromDate').setAttribute('min', today);
document.getElementById('toDate').setAttribute('min', today);

document.getElementById('leaveRequestForm').addEventListener('submit', function(e){
    const fromDate = document.getElementById('fromDate').value;
    const toDate = document.getElementById('toDate').value;
    if(new Date(fromDate) < new Date(today) || new Date(toDate) < new Date(today)){
        e.preventDefault();
        alert('Cannot request leave for past dates.');
    } else if(new Date(fromDate) > new Date(toDate)){
        e.preventDefault();
        alert('From Date cannot be later than To Date.');
    }
});

// Live reason counter
reason.addEventListener('input', function(){
    const len = (reason.value || '').length;
    reasonCount.textContent = `${len}/500`;
});
</script>
</body>
</html>
