<?php
session_start();
require_once "config.php"; // PDO connection
require_once "session_check.php";
require_role(['hod']);

// Get HoD department info
$deptStmt = $pdo->prepare("SELECT id, name FROM departments WHERE hod_id = ?");
$deptStmt->execute([$user_id]);
$department = $deptStmt->fetch();
if (!$department) die("Department not found.");

// Handle approve/reject actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id'], $_POST['action'])) {
    $leave_id = intval($_POST['leave_id']);
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';

    $updStmt = $pdo->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $success = $updStmt->execute([$action, $user_id, $leave_id]);

    header('Content-Type: application/json');
    echo json_encode(['success'=>$success, 'status'=>$action]);
    exit;
}

// Fetch leave requests for this department
$leaveStmt = $pdo->prepare("
    SELECT lr.id, lr.student_id, lr.reason, lr.supporting_file, lr.status,
           lr.from_date, lr.to_date, lr.requested_at,
           s.first_name, s.last_name, s.year_level, d.name as department_name
    FROM leave_requests lr
    INNER JOIN students s ON lr.student_id = s.id
    INNER JOIN departments d ON s.department_id = d.id
    WHERE s.department_id = ?
    ORDER BY lr.requested_at DESC
");
$leaveStmt->execute([$department['id']]);
$leaveRequests = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

// If AJAX request to refresh table only
if(isset($_GET['ajax'])){
    foreach($leaveRequests as $idx => $lr){
        echo '<tr data-id="'.$lr['id'].'">
          <td>'.($idx+1).'</td>
          <td>'.htmlspecialchars($lr['first_name'].' '.$lr['last_name']).'</td>
          <td>'.htmlspecialchars($lr['department_name']).'</td>
          <td>'.htmlspecialchars($lr['year_level']).'</td>
          <td>'.htmlspecialchars($lr['from_date']).'</td>
          <td>'.htmlspecialchars($lr['to_date']).'</td>
          <td>'.htmlspecialchars($lr['reason']).'</td>
          <td>';
          if($lr['supporting_file']){
            echo '<a href="uploads/'.htmlspecialchars($lr['supporting_file']).'" target="_blank" class="btn btn-sm btn-secondary"><i class="fas fa-file-download"></i> View</a>';
          }
          echo '</td>
          <td><span class="badge '.($lr['status']=='approved'?'bg-success':($lr['status']=='rejected'?'bg-danger':'bg-warning text-dark')).'">'.ucfirst($lr['status']).'</span></td>
          <td>';
          if($lr['status']=='pending'){
            echo '<button class="btn btn-sm btn-approve me-1"><i class="fas fa-check"></i> Approve</button>
                  <button class="btn btn-sm btn-reject"><i class="fas fa-times"></i> Reject</button>';
          }
          echo '</td></tr>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leave Management | HoD | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body { font-family:'Segoe UI',sans-serif; background:#f5f7fa; margin:0; }
.sidebar { position:fixed; top:0; left:0; width:250px; height:100vh; background:#003366; color:white; padding-top:20px; }
.sidebar a { display:block; padding:12px 20px; color:#fff; text-decoration:none; }
.sidebar a:hover, .sidebar a.active { background:#0059b3; }
.topbar { margin-left:250px; background:#fff; padding:10px 30px; border-bottom:1px solid #ddd; }
.main-content { margin-left:250px; padding:30px; }
.footer { text-align:center; margin-left:250px; padding:15px; font-size:0.9rem; color:#666; background:#f0f0f0; }
.btn-approve { background-color:#28a745; color:white; }
.btn-reject { background-color:#dc3545; color:white; }
.btn-approve:hover { background-color:#218838; }
.btn-reject:hover { background-color:#c82333; }
@media(max-width:768px){ .sidebar,.topbar,.main-content,.footer{ margin-left:0!important; width:100%; } .sidebar{ display:none; } }
</style>
</head>
<body>

<div class="sidebar">
  <div class="text-center mb-4">
    <img src="RP_Logo.jpeg" alt="RP Logo" width="100" class="mb-2" />
    <h5>HoD Panel</h5>
    <hr style="border-color:#ffffff66;">
  </div>
  <a href="hod-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
  <a href="hod-department-reports.php"><i class="fas fa-chart-bar me-2"></i> Department Reports</a>
  <a href="hod-leave-management.php" class="active"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave</a>
  <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
  <h5 class="m-0 fw-bold">Manage Leave Requests</h5>
  <span>Welcome, Head of Department</span>
</div>

<div class="main-content">
  <div class="card p-4">
    <h6 class="mb-3">Pending Leave Requests</h6>
    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Student Name</th>
            <th>Department</th>
            <th>Class</th>
            <th>From</th>
            <th>To</th>
            <th>Reason</th>
            <th>Support File</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="leaveRequestsTableBody">
          <?php foreach($leaveRequests as $idx => $lr): ?>
          <tr data-id="<?= $lr['id'] ?>">
            <td><?= $idx+1 ?></td>
            <td><?= htmlspecialchars($lr['first_name'].' '.$lr['last_name']) ?></td>
            <td><?= htmlspecialchars($lr['department_name']) ?></td>
            <td><?= htmlspecialchars($lr['year_level']) ?></td>
            <td><?= htmlspecialchars($lr['from_date']) ?></td>
            <td><?= htmlspecialchars($lr['to_date']) ?></td>
            <td><?= htmlspecialchars($lr['reason']) ?></td>
            <td>
              <?php if($lr['supporting_file']): ?>
                <a href="uploads/<?= htmlspecialchars($lr['supporting_file']) ?>" target="_blank" class="btn btn-sm btn-secondary">
                  <i class="fas fa-file-download"></i> View
                </a>
              <?php endif; ?>
            </td>
            <td><span class="badge <?= $lr['status']=='approved'?'bg-success':($lr['status']=='rejected'?'bg-danger':'bg-warning text-dark') ?>"><?= ucfirst($lr['status']) ?></span></td>
            <td>
              <?php if($lr['status']=='pending'): ?>
                <button class="btn btn-sm btn-approve me-1"><i class="fas fa-check"></i> Approve</button>
                <button class="btn btn-sm btn-reject"><i class="fas fa-times"></i> Reject</button>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | HoD Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const tableBody = document.getElementById('leaveRequestsTableBody');

function handleAction(e){
  const btn = e.target.closest('button');
  if(!btn) return;

  const row = btn.closest('tr');
  const leaveId = row.dataset.id;
  const statusCell = row.querySelector('td:nth-child(9) span');
  const action = btn.classList.contains('btn-approve') ? 'approve' : 'reject';

  if(confirm(`Are you sure you want to ${action} this leave request?`)){
    fetch('',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:`leave_id=${leaveId}&action=${action}`
    })
    .then(res=>res.json())
    .then(data=>{
      if(data.success){
        statusCell.className = action==='approve'?'badge bg-success':'badge bg-danger';
        statusCell.textContent = data.status.charAt(0).toUpperCase()+data.status.slice(1);
        row.querySelectorAll('button').forEach(b=>b.disabled=true);
      } else {
        alert('Failed to update status.');
      }
    }).catch(err=>alert('Error: '+err));
  }
}

tableBody.addEventListener('click', handleAction);

// Auto-refresh every 30 seconds
function refreshTable(){
  fetch('hod-leave-management.php?ajax=1')
    .then(res=>res.text())
    .then(html=>{
      if(html) tableBody.innerHTML = html;
    }).catch(err=>console.log('Refresh error:', err));
}
setInterval(refreshTable, 30000);
</script>

</body>
</html>
