<?php
require_once "config.php"; // PDO connection - must be first
require_once "session_check.php"; // Session management - requires config.php constants
session_start(); // Start session after config and session_check

// Check if user is logged in and has HOD role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'hod') {
    header("Location: login.php?error=unauthorized");
    exit;
}

require_role(['hod']);

// Get HoD department info by joining through lecturers table
$hod_id = $_SESSION['user_id'];
try {
    $deptStmt = $pdo->prepare("
        SELECT d.id, d.name
        FROM departments d
        JOIN lecturers l ON d.hod_id = l.id
        JOIN users u ON l.email = u.email AND u.role = 'hod'
        WHERE u.id = ?
    ");
    $deptStmt->execute([$hod_id]);
    $department = $deptStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error in hod-leave-management.php: " . $e->getMessage());
    echo "<div style='padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin: 20px;'>";
    echo "<h3 style='color: #dc3545; margin-bottom: 15px;'>Database Error</h3>";
    echo "<p>There was an error connecting to the database. Please try again later.</p>";
    echo "<p><a href='hod-dashboard.php' style='color: #0066cc; text-decoration: none;'><i class='fas fa-arrow-left me-2'></i>Return to Dashboard</a></p>";
    echo "</div>";
    exit;
}

if (!$department) {
    // Debug information
    error_log("Department not found for HoD ID: $hod_id in leave management");
    error_log("Session data: " . print_r($_SESSION, true));

    // Check if departments table exists and has data
    $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM departments");
    $checkStmt->execute();
    $deptCount = $checkStmt->fetch(PDO::FETCH_ASSOC);

    error_log("Total departments in database: " . $deptCount['count']);

    if ($deptCount['count'] > 0) {
        $deptListStmt = $pdo->prepare("SELECT id, name, hod_id FROM departments");
        $deptListStmt->execute();
        $departments = $deptListStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Available departments: " . print_r($departments, true));
    }

    echo "<div style='padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin: 20px;'>";
    echo "<h3 style='color: #dc3545; margin-bottom: 15px;'>Department Not Found</h3>";
    echo "<p>It appears you are not properly assigned as Head of Department for any department.</p>";
    echo "<p><strong>Debug Information:</strong></p>";
    echo "<ul>";
    echo "<li>Your User ID: <strong>$hod_id</strong></li>";
    echo "<li>Your Role: <strong>" . ($_SESSION['role'] ?? 'Not set') . "</strong></li>";
    echo "<li>Total Departments: <strong>" . $deptCount['count'] . "</strong></li>";
    echo "</ul>";

    if ($deptCount['count'] > 0) {
        echo "<p><strong>Available Departments:</strong></p>";
        echo "<ul>";
        foreach ($departments as $dept) {
            echo "<li>{$dept['name']} (ID: {$dept['id']}, HoD ID: {$dept['hod_id']})</li>";
        }
        echo "</ul>";
        echo "<p><strong>Solution:</strong> Please contact your administrator to assign you as HoD for a department.</p>";
    } else {
        echo "<p><strong>Solution:</strong> No departments exist in the system. Please contact your administrator to create departments first.</p>";
    }

    echo "<hr style='margin: 20px 0;'>";
    echo "<p><a href='hod-dashboard.php' style='color: #0066cc; text-decoration: none;'><i class='fas fa-arrow-left me-2'></i>Return to Dashboard</a></p>";
    echo "</div>";

    exit;
}

// Handle approve/reject actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id'], $_POST['action'])) {
    try {
        $leave_id = intval($_POST['leave_id']);
        $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';

        $updStmt = $pdo->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $success = $updStmt->execute([$action, $hod_id, $leave_id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'status' => $action]);
        exit;
    } catch (PDOException $e) {
        error_log("Error updating leave request in hod-leave-management.php: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

// Fetch leave requests for this department
try {
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
} catch (PDOException $e) {
    error_log("Error fetching leave requests in hod-leave-management.php: " . $e->getMessage());
    $leaveRequests = [];
}

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
:root {
    --primary-color: #000000;
    --primary-dark: #000000;
    --primary-light: #f8f9fa;
    --primary-gradient: linear-gradient(135deg, #000000 0%, #333333 100%);

    --success-color: #10b981;
    --success-light: #d1fae5;
    --success-dark: #047857;

    --danger-color: #ef4444;
    --danger-light: #fee2e2;
    --danger-dark: #dc2626;

    --warning-color: #f59e0b;
    --warning-light: #fef3c7;
    --warning-dark: #d97706;

    --info-color: #06b6d4;
    --info-light: #cffafe;
    --info-dark: #0891b2;

    --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
    --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
    --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
    --border-radius: 12px;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: #000000;
    min-height: 100vh;
    margin: 0;
    position: relative;
    overflow-x: hidden;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
    border-right: 1px solid rgba(0, 0, 0, 0.1);
    padding: 0;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
}

.sidebar .logo {
    background: #000000;
    color: white;
    padding: 25px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.sidebar .logo h4 {
    color: white;
    font-weight: 700;
    margin: 0;
    font-size: 1.4rem;
    position: relative;
    z-index: 2;
    text-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.sidebar-nav {
    list-style: none;
    padding: 20px 0;
    margin: 0;
}

.sidebar-nav .nav-section {
    padding: 15px 20px 10px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    margin-bottom: 10px;
}

.sidebar-nav a {
    display: block;
    padding: 14px 25px;
    color: #000000;
    text-decoration: none;
    border-radius: 0 25px 25px 0;
    margin: 0 0 2px 0;
    transition: var(--transition);
    font-weight: 500;
    position: relative;
    border-left: 3px solid transparent;
}

.sidebar-nav a:hover {
    background: rgba(0, 0, 0, 0.08);
    color: #000000;
    border-left-color: #000000;
    transform: translateX(8px);
    box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
}

.sidebar-nav a.active {
    background: rgba(0, 0, 0, 0.1);
    color: #000000;
    border-left-color: #000000;
    font-weight: 600;
}

.sidebar-nav a i {
    margin-right: 12px;
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}

.topbar {
    margin-left: 280px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    padding: 20px 30px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    z-index: 900;
    box-shadow: var(--shadow-light);
}

.main-content {
    margin-left: 280px;
    padding: 40px 30px;
    max-width: calc(100% - 280px);
    overflow-x: auto;
}

.footer {
    text-align: center;
    margin-left: 280px;
    padding: 20px;
    font-size: 0.9rem;
    color: #666;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-top: 1px solid rgba(0, 0, 0, 0.1);
    position: fixed;
    bottom: 0;
    width: calc(100% - 280px);
    box-shadow: 0 -1px 5px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-light);
    border: 1px solid rgba(255, 255, 255, 0.2);
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: #000000;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-medium);
}

.btn {
    border-radius: 8px;
    font-weight: 600;
    padding: 10px 20px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.btn-primary {
    background: #000000;
    border: none;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
}

.btn-primary:hover {
    background: #333333;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.btn-approve {
    background-color: #10b981 !important;
    color: white !important;
    border: none !important;
}

.btn-reject {
    background-color: #ef4444 !important;
    color: white !important;
    border: none !important;
}

.btn-approve:hover {
    background-color: #047857 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
}

.btn-reject:hover {
    background-color: #dc2626 !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);
}

.table {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: var(--border-radius);
    overflow: hidden;
    box-shadow: var(--shadow-light);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.table thead th {
    background: #000000;
    color: white;
    border: none;
    font-weight: 600;
    padding: 15px;
    position: relative;
}

.table tbody td {
    padding: 15px;
    border-color: rgba(0, 0, 0, 0.1);
    transition: var(--transition);
}

.table tbody tr:hover td {
    background: rgba(0, 0, 0, 0.05);
    transform: translateX(5px);
}

.mobile-menu-toggle {
    display: none;
    position: fixed;
    top: 15px;
    left: 15px;
    z-index: 1003;
    background: #000000;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    transition: var(--transition);
}

.mobile-menu-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4);
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        width: 260px;
        z-index: 1002;
    }

    .sidebar.show {
        transform: translateX(0);
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
    }

    .sidebar.show::after {
        content: '';
        position: fixed;
        top: 0;
        left: 260px;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(2px);
        z-index: -1;
    }

    .topbar,
    .main-content,
    .footer {
        margin-left: 0 !important;
        max-width: 100% !important;
        width: 100% !important;
    }

    .topbar {
        padding: 15px 20px;
    }

    .main-content {
        padding: 20px 15px;
    }

    .mobile-menu-toggle {
        display: block !important;
    }

    .sidebar-nav a {
        padding: 16px 20px;
        font-size: 0.95rem;
    }

    .sidebar-nav .nav-section {
        padding: 12px 20px 8px;
        font-size: 0.7rem;
    }
}

/* Form styling */
.form-label {
    color: #000000;
    font-weight: 600;
}

.form-control, .form-select {
    color: #000000;
    border: 1px solid #000000;
}

.form-control:focus, .form-select:focus {
    border-color: #000000;
    box-shadow: 0 0 0 0.2rem rgba(0, 0, 0, 0.25);
}
</style>
</head>
<body>

<!-- Include Admin Sidebar -->
<?php include 'includes/admin-sidebar.php'; ?>

<div class="topbar">
    <div class="d-flex align-items-center justify-content-end">
        <div class="d-flex gap-2 align-items-center">
            <div class="badge bg-primary fs-6 px-3 py-2">
                <i class="fas fa-clock me-1"></i>Live Updates
            </div>
            <div class="badge bg-success fs-6 px-3 py-2">
                <i class="fas fa-user-tie me-1"></i>Head of Department
            </div>
            <button class="btn btn-outline-primary btn-sm" onclick="refreshTable()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
    </div>
</div>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header text-center py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #000000 100%); border-bottom: 2px solid #000000; margin-bottom: 20px;">
        <div class="d-flex align-items-center justify-content-center">
            <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" style="height: 60px; width: auto; margin-right: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
            <h1 style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0; font-weight: 700;">
                Leave Management
            </h1>
        </div>
    </div>

    <div class="card p-4">
        <h6 class="mb-3"><i class="fas fa-envelope-open-text me-2"></i>Department Leave Requests</h6>
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

<div class="footer">
    &copy; 2025 Rwanda Polytechnic | Head of Department Panel
</div>

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
