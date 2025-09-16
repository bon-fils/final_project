<?php
session_start();
require_once "config.php"; // $pdo connection

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
        // File upload
        $supporting_file = null;
        if (!empty($_FILES['supportingFile']['name'])) {
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
body { font-family:'Segoe UI',sans-serif; background:#f5f7fa; margin:0; }
.sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:#003366; color:white; padding-top:20px; overflow-y:auto; }
.sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none; }
.sidebar a:hover, .sidebar a.active { background:#0059b3; }
.topbar { margin-left:250px; background:#fff; padding:10px 30px; border-bottom:1px solid #ddd; }
.main-content { margin-left:250px; padding:40px 30px; max-width:700px; }
.footer { text-align:center; margin-left:250px; padding:15px; font-size:0.9rem; color:#666; background:#f0f0f0; }
label { font-weight:600; color:#003366; }
.form-container, .table-container { background:white; padding:30px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.05); margin-bottom:30px; }
.btn-primary { background-color:#003366; border-color:#003366; }
.btn-primary:hover { background-color:#0059b3; border-color:#0059b3; }
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
<?php if($errorMsg): ?><div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div><?php endif; ?>
<?php if($successMsg): ?><div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div><?php endif; ?>

<form id="leaveRequestForm" method="post" enctype="multipart/form-data">
<div class="mb-3">
<label for="requestTo" class="form-label">Request To</label>
<select id="requestTo" name="requestTo" class="form-control" required>
<option value="">-- Select --</option>
<option value="hod">Head of Department</option>
<option value="lecturer">Lecturer</option>
</select>
</div>

<div class="mb-3" id="courseSelectWrapper" style="display:none;">
<label for="courseId" class="form-label">Select Course</label>
<select id="courseId" name="courseId" class="form-control">
<option value="">-- Select Course --</option>
<?php foreach ($courses as $course): ?>
<option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
<?php endforeach; ?>
</select>
</div>

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
</div>

<div class="text-end">
<button type="submit" class="btn btn-primary px-4"><i class="fas fa-paper-plane me-2"></i>Submit Request</button>
</div>
</form>
</div>

<div class="table-container">
<h5 class="mb-3 fw-bold">📑 Last 5 Leave Requests</h5>
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
<?php if($req['status']=='pending'): ?><span class="badge bg-warning text-dark">Pending</span>
<?php elseif($req['status']=='approved'): ?><span class="badge bg-success">Approved</span>
<?php else: ?><span class="badge bg-danger">Rejected</span><?php endif; ?>
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
</script>
</body>
</html>
