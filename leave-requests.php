<?php
session_start();
require_once "config.php"; // $pdo
require_once "session_check.php";
require_role(['student', 'admin']);

$userRole = $_SESSION['role'] ?? 'admin';

// Resolve logged-in student (only for student role)
$user_id = $_SESSION['user_id'] ?? null;
$student_id = null;
$courses = [];
if ($userRole === 'student' && $user_id) {
    $s = $pdo->prepare("SELECT id, option_id, year_level FROM students WHERE user_id = ? LIMIT 1");
    $s->execute([$user_id]);
    $stu = $s->fetch(PDO::FETCH_ASSOC);
    if ($stu) { $student_id = (int)$stu['id']; }

    // Minimal course list: from courses table by option_id/year_level if such mapping exists
    try {
        $c = $pdo->prepare("SELECT id, name FROM courses WHERE option_id = :opt OR year_level = :yl ORDER BY name");
        $c->execute(['opt' => $stu['option_id'] ?? 0, 'yl' => $stu['year_level'] ?? '']);
        $courses = $c->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $t) {
        // Fallback to all courses if schema differs
        $courses = $pdo->query("SELECT id, name FROM courses ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Leave Management | <?php echo ucfirst($userRole); ?> | RP Attendance System</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f7fa;
      margin: 0;
    }
    .sidebar {
      position: fixed; top: 0; left: 0; width: 250px; height: 100vh;
      background-color: #003366; color: white; padding-top: 20px; overflow-y: auto;
    }
    .sidebar a {
      display: block; padding: 12px 20px; color: #fff; text-decoration: none;
    }
    .sidebar a:hover, .sidebar a.active { background-color: #0059b3; }
    .topbar { margin-left: 250px; background-color: #fff; padding: 10px 30px; border-bottom: 1px solid #ddd; }
    .main-content { margin-left: 250px; padding: 40px 30px; max-width: 700px; }
    .footer { text-align: center; margin-left: 250px; padding: 15px; font-size: 0.9rem; color: #666; background-color: #f0f0f0; }
    label { font-weight: 600; color: #003366; }
    .form-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
    .btn-primary { background-color: #003366; border-color: #003366; }
    .btn-primary:hover { background-color: #0059b3; border-color: #0059b3; }
    @media (max-width: 768px) {
      .sidebar, .topbar, .main-content, .footer { margin-left: 0 !important; width: 100%; }
      .sidebar { display: none; }
      .main-content { padding: 20px; }
    }
  </style>
</head>

<body>
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4><?php echo $userRole === 'admin' ? '👨‍💼 Admin' : '🎓 Student'; ?></h4>
      <hr style="border-color: #ffffff66;">
    </div>
    <?php if ($userRole === 'admin'): ?>
      <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
      <a href="admin-reports.php"><i class="fas fa-chart-bar me-2"></i>Reports & Analytics</a>
      <a href="attendance-session.php"><i class="fas fa-video me-2"></i>Attendance Session</a>
      <a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i>Attendance Records</a>
      <a href="leave-requests.php" class="active"><i class="fas fa-file-signature me-2"></i>Leave Management</a>
      <a href="manage-departments.php"><i class="fas fa-building me-2"></i>Manage Departments</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    <?php else: ?>
      <a href="students-dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
      <a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i>Attendance Records</a>
      <a href="request-leave.php" class="active"><i class="fas fa-file-signature me-2"></i>Request Leave</a>
      <a href="leave-status.php"><i class="fas fa-info-circle me-2"></i>Leave Status</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
    <?php endif; ?>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Request Leave</h5>
    <span>RP Attendance System</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <?php if ($userRole === 'admin'): ?>
      <!-- Admin View - Leave Management Dashboard -->
      <div class="card">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Leave Management Dashboard</h5>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-4">
              <div class="card border-warning">
                <div class="card-body text-center">
                  <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                  <h4 class="text-warning">Pending</h4>
                  <h2 id="pendingCount">0</h2>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border-success">
                <div class="card-body text-center">
                  <i class="fas fa-check fa-3x text-success mb-3"></i>
                  <h4 class="text-success">Approved</h4>
                  <h2 id="approvedCount">0</h2>
                </div>
              </div>
            </div>
            <div class="col-md-4">
              <div class="card border-danger">
                <div class="card-body text-center">
                  <i class="fas fa-times fa-3x text-danger mb-3"></i>
                  <h4 class="text-danger">Rejected</h4>
                  <h2 id="rejectedCount">0</h2>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4">
            <h6>Recent Leave Requests</h6>
            <div class="table-responsive">
              <table class="table table-striped">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="leaveRequestsTable">
                  <tr>
                    <td colspan="5" class="text-center">Loading...</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <!-- Student View - Leave Request Form -->
      <div class="form-container">
        <form id="leaveRequestForm" method="post" action="submit-leave.php" enctype="multipart/form-data">

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
    <?php endif; ?>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | <?php echo ucfirst($userRole); ?> Panel
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    <?php if ($userRole === 'admin'): ?>
    // Admin functionality - Load leave requests data
    $(document).ready(function() {
      loadLeaveStatistics();
      loadLeaveRequests();

      function loadLeaveStatistics() {
        $.get('admin-reports.php?ajax=1&action=get_stats', function(data) {
          $('#pendingCount').text(data.pending_leaves || 0);
          $('#approvedCount').text('0'); // Would need additional query
          $('#rejectedCount').text('0'); // Would need additional query
        });
      }

      function loadLeaveRequests() {
        // This would load recent leave requests for admin management
        $('#leaveRequestsTable').html('<tr><td colspan="5" class="text-center">Feature coming soon...</td></tr>');
      }
    });
    <?php else: ?>
    // Student functionality
    const requestTo = document.getElementById('requestTo');
    const courseSelectWrapper = document.getElementById('courseSelectWrapper');

    requestTo.addEventListener('change', function() {
      if (this.value === 'lecturer') {
        courseSelectWrapper.style.display = 'block';
        document.getElementById('courseId').setAttribute('required', 'required');
      } else {
        courseSelectWrapper.style.display = 'none';
        document.getElementById('courseId').removeAttribute('required');
      }
    });

    document.getElementById('leaveRequestForm').addEventListener('submit', function (e) {
      const fromDate = document.getElementById('fromDate').value;
      const toDate = document.getElementById('toDate').value;

      if (new Date(fromDate) > new Date(toDate)) {
        e.preventDefault();
        alert('From Date cannot be later than To Date.');
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>
        case 'student':
          window.location.href = 'students-dashboard.php';
          break;
        default:
          alert("Invalid role selected.");
      }
    }
  </script>