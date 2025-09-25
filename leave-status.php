<?php
session_start();
require_once "config.php"; // PDO connection
require_once "session_check.php";
require_role(['student', 'admin']);

// Ensure student is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: index.php");
    exit;
}

// Get student info
$stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();
$student_id = $student['id'] ?? null;
if (!$student_id) die("Student not found.");

// Fetch last 5 leave requests
$sql = "SELECT *
        FROM leave_requests
        WHERE student_id = ?
        ORDER BY requested_at DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([$student_id]);
$leave_requests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leave Status | Student | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body { font-family:'Segoe UI',sans-serif; background:#f5f7fa; margin:0; }
.sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:#003366; color:white; padding-top:20px; overflow-y:auto; }
.sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none; }
.sidebar a:hover, .sidebar a.active { background:#0059b3; }
.topbar { margin-left:250px; background:#fff; padding:10px 30px; border-bottom:1px solid #ddd; }
.main-content { margin-left:250px; padding:30px; max-width:1000px; }
.footer { text-align:center; margin-left:250px; padding:15px; font-size:0.9rem; color:#666; background:#f0f0f0; }
.table th, .table td { vertical-align: middle; }
@media(max-width:768px) { .sidebar, .topbar, .main-content, .footer { margin-left:0 !important; width:100%; } .sidebar { display:none; } .table th, .table td { font-size:0.85rem; } }
</style>
</head>
<body>

<div class="sidebar">
<div class="text-center mb-4">
<h4>🎓 Student</h4>
<hr style="border-color:#ffffff66;">
</div>
<a href="students-dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
<a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i>Attendance Records</a>
<a href="request-leave.php"><i class="fas fa-file-signature me-2"></i>Request Leave</a>
<a href="leave-status.php" class="active"><i class="fas fa-info-circle me-2"></i>Leave Status</a>
<a href="index.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
<h5 class="m-0 fw-bold">Leave Request Status</h5>
<span>RP Attendance System</span>
</div>

<div class="main-content">
<h5 class="mb-3 fw-bold">📑 Last 5 Leave Requests</h5>
<div class="table-responsive">
<table class="table table-bordered table-hover align-middle">
<thead class="table-light">
<tr>
<th>From Date</th>
<th>To Date</th>
<th>Reason</th>
<th>Recipient</th>
<th>Role</th>
<th>Course</th>
<th>Status</th>
<th>Supporting Document</th>
</tr>
</thead>
<tbody>
<?php if(count($leave_requests) > 0): ?>
    <?php foreach($leave_requests as $lr): ?>
    <tr>
        <td>
        <?php
        // Extract from date from structured reason
        if (preg_match('/From:\s*([^\n\r]+)/', $lr['reason'], $matches)) {
            echo date("d M Y", strtotime(trim($matches[1])));
        } else {
            echo '-';
        }
        ?>
        </td>
        <td>
        <?php
        // Extract to date from structured reason
        if (preg_match('/To:\s*([^\n\r]+)/', $lr['reason'], $matches)) {
            echo date("d M Y", strtotime(trim($matches[1])));
        } else {
            echo '-';
        }
        ?>
        </td>
        <td>
        <?php
        // Extract main reason (before structured details)
        $reason_parts = explode('-- Details --', $lr['reason']);
        $main_reason = trim($reason_parts[0]);
        if (empty($main_reason)) {
            $main_reason = 'Leave Request';
        }
        echo htmlspecialchars($main_reason);
        ?>
        </td>
        <td><?= htmlspecialchars($lr['reviewed_by'] ?? '-') ?></td>
        <td>
        <?php
        // Determine role from structured reason
        if (preg_match('/Requested To:\s*(HoD|Lecturer)/', $lr['reason'], $matches)) {
            echo $matches[1];
        } else {
            echo 'HoD'; // Default
        }
        ?>
        </td>
        <td>
        <?php
        // Extract course information
        if (preg_match('/Course ID:\s*([^\n\r]+)/', $lr['reason'], $matches)) {
            echo 'Course ID: ' . htmlspecialchars(trim($matches[1]));
        } else {
            echo 'All Courses';
        }
        ?>
        </td>
        <td>
            <?php
            switch($lr['status']){
                case 'pending': echo '<span class="badge bg-warning text-dark">Pending</span>'; break;
                case 'approved': echo '<span class="badge bg-success">Approved</span>'; break;
                case 'rejected': echo '<span class="badge bg-danger">Rejected</span>'; break;
                default: echo '<span class="badge bg-secondary">Unknown</span>';
            }
            ?>
        </td>
        <td>
            <?php if(!empty($lr['supporting_file'])): ?>
                <a href="uploads/leave_docs/<?= htmlspecialchars($lr['supporting_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-file-pdf me-1"></i>View
                </a>
            <?php else: ?>
                <em>Not uploaded</em>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
<?php else: ?>
<tr><td colspan="8" class="text-center">No leave requests found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Student Panel</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
