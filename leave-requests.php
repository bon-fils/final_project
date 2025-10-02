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

<?php
// Handle AJAX requests for admin functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Debug: Log the incoming request
    error_log('AJAX Request received - Action: ' . $_POST['action']);
    error_log('POST data: ' . json_encode($_POST));

    try {
        // Simple test action to verify PHP is working
        if ($_POST['action'] === 'test') {
            echo json_encode([
                'success' => true,
                'message' => 'PHP script is working',
                'timestamp' => date('Y-m-d H:i:s'),
                'post_data' => $_POST
            ]);
            exit;
        }

        if ($_POST['action'] === 'get_stats') {
            // Get leave statistics
            $stats = [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0
            ];

            // Count pending requests
            $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'pending'");
            $stats['pending'] = (int)$stmt->fetchColumn();

            // Count approved requests
            $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'approved'");
            $stats['approved'] = (int)$stmt->fetchColumn();

            // Count rejected requests
            $stmt = $pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status = 'rejected'");
            $stats['rejected'] = (int)$stmt->fetchColumn();

            echo json_encode($stats);
            exit;
        }

        if ($_POST['action'] === 'get_leave_requests') {
            // Get recent leave requests - simplified query first
            $stmt = $pdo->query("
                SELECT lr.id, lr.student_id, lr.reason, lr.status, lr.requested_at,
                       COALESCE(lr.from_date, 'Not specified') as from_date,
                       COALESCE(lr.to_date, 'Not specified') as to_date
                FROM leave_requests lr
                ORDER BY lr.requested_at DESC
                LIMIT 20
            ");

            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Try to add student information if students table exists and has matching records
            foreach ($requests as &$request) {
                try {
                    $studentStmt = $pdo->prepare("SELECT first_name, last_name FROM students WHERE id = ? LIMIT 1");
                    $studentStmt->execute([$request['student_id']]);
                    $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

                    if ($student) {
                        $request['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
                    } else {
                        $request['student_name'] = 'Student ID: ' . $request['student_id'] . ' (Not in database)';
                    }
                } catch (Exception $e) {
                    $request['student_name'] = 'Student ID: ' . $request['student_id'] . ' (Error loading)';
                }
            }

            // Debug logging
            error_log('Leave requests query returned: ' . count($requests) . ' records');
            error_log('Sample request data: ' . json_encode($requests[0] ?? 'No data'));

            // Format dates for display
            foreach ($requests as &$request) {
                // Handle missing student data
                if (empty($request['first_name']) && empty($request['last_name'])) {
                    $request['student_name'] = 'Student ID: ' . $request['student_id'] . ' (Not Found)';
                } else {
                    $request['student_name'] = trim($request['first_name'] . ' ' . $request['last_name']);
                }

                $request['from_date'] = $request['from_date'] !== 'N/A' ? date('M d, Y', strtotime($request['from_date'])) : 'Not specified';
                $request['to_date'] = $request['to_date'] !== 'N/A' ? date('M d, Y', strtotime($request['to_date'])) : 'Not specified';
                $request['requested_at'] = date('M d, Y', strtotime($request['requested_at']));
                unset($request['first_name'], $request['last_name']);
            }

            echo json_encode([
                'success' => true,
                'requests' => $requests
            ]);
            exit;
        }

        if ($_POST['action'] === 'update_status' && isset($_POST['request_id'], $_POST['status'])) {
            $requestId = (int)$_POST['request_id'];
            $newStatus = $_POST['status'];

            if (!in_array($newStatus, ['approved', 'rejected'])) {
                throw new Exception('Invalid status');
            }

            // Update leave request status
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE id = ?");
            $result = $stmt->execute([$newStatus, $requestId]);

            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Leave request updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update leave request']);
            }
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
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
      background: linear-gradient(to right, #0066cc, #003366);
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
    .main-content { margin-left: 250px; padding: 25px 40px; min-height: calc(100vh - 80px); }
    .footer { text-align: center; margin-left: 250px; padding: 15px; font-size: 0.9rem; color: #666; background-color: #f0f0f0; }
    label { font-weight: 600; color: #003366; }
    .form-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
    .btn-primary { background-color: #003366; border-color: #003366; }
    .btn-primary:hover { background-color: #0059b3; border-color: #0059b3; }
    @media (max-width: 768px) {
      .sidebar, .topbar, .main-content, .footer { margin-left: 0 !important; width: 100%; }
      .sidebar { display: none; }
      .main-content { padding: 20px 15px; }
      .form-container { padding: 20px 15px; }

      .d-flex.gap-3 { flex-direction: column; gap: 1rem !important; align-items: stretch !important; }
      .d-flex.gap-3 .form-select,
      .d-flex.gap-3 .input-group { width: 100% !important; min-width: unset !important; }

      .btn-group .btn { font-size: 0.875rem; padding: 0.5rem 0.75rem; }
      .card-body.py-5 { padding: 2rem 1rem !important; }

      .table td { padding: 0.75rem 0.5rem; font-size: 0.875rem; }
      .table th { padding: 0.75rem 0.5rem; font-size: 0.875rem; }

      .display-4 { font-size: 2.5rem !important; }
      .fa-4x { font-size: 3rem !important; }
    }

    @media (max-width: 576px) {
      .card-body { padding: 1rem 0.75rem; }
      .main-content { padding: 15px 10px; }
      .form-container { padding: 15px 10px; }

      .btn-group { flex-direction: column; width: fit-content; }
      .btn-group .btn { border-radius: 0.25rem !important; margin-bottom: 0.125rem; }

      .input-group-text { padding: 0.375rem 0.5rem; font-size: 0.875rem; }
      .form-control { font-size: 0.875rem; }

      .card-body.py-5 { padding: 1.5rem 0.75rem !important; }
      .fa-4x { font-size: 2.5rem !important; }
      .display-4 { font-size: 2rem !important; }
      h4 { font-size: 1.1rem; }
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
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
          <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Leave Management Dashboard</h5>
        </div>
        <div class="card-body">
          <div class="row g-4 justify-content-center">
            <div class="col-lg-4 col-md-6 col-sm-6 col-12">
              <div class="card border-warning shadow-sm h-100">
                <div class="card-body text-center py-5">
                  <div class="mb-4">
                    <i class="fas fa-clock fa-4x text-warning"></i>
                  </div>
                  <h4 class="text-warning mb-3">Pending</h4>
                  <h1 id="pendingCount" class="mb-0 text-warning fw-bold display-4">0</h1>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-6 col-sm-6 col-12">
              <div class="card border-success shadow-sm h-100">
                <div class="card-body text-center py-5">
                  <div class="mb-4">
                    <i class="fas fa-check fa-4x text-success"></i>
                  </div>
                  <h4 class="text-success mb-3">Approved</h4>
                  <h1 id="approvedCount" class="mb-0 text-success fw-bold display-4">0</h1>
                </div>
              </div>
            </div>
            <div class="col-lg-4 col-md-12 col-sm-12 col-12">
              <div class="card border-danger shadow-sm h-100">
                <div class="card-body text-center py-5">
                  <div class="mb-4">
                    <i class="fas fa-times fa-4x text-danger"></i>
                  </div>
                  <h4 class="text-danger mb-3">Rejected</h4>
                  <h1 id="rejectedCount" class="mb-0 text-danger fw-bold display-4">0</h1>
                </div>
              </div>
            </div>
          </div>

          <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
              <h5 class="mb-0 fw-bold text-primary"><i class="fas fa-list me-2"></i>Recent Leave Requests</h5>
              <div class="d-flex gap-3 flex-wrap align-items-center">
                <div class="input-group" style="min-width: 320px;">
                  <span class="input-group-text bg-light border-end-0">
                    <i class="fas fa-search text-muted"></i>
                  </span>
                  <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search by student name...">
                </div>
                <select id="statusFilter" class="form-select" style="min-width: 160px;">
                  <option value="">All Status</option>
                  <option value="pending">Pending</option>
                  <option value="approved">Approved</option>
                  <option value="rejected">Rejected</option>
                </select>
              </div>
            </div>
            <div class="table-responsive">
              <table class="table table-hover table-striped">
                <thead class="table-primary">
                  <tr>
                    <th class="fw-semibold py-3">Student</th>
                    <th class="fw-semibold py-3">From</th>
                    <th class="fw-semibold py-3">To</th>
                    <th class="fw-semibold py-3">Status</th>
                    <th class="fw-semibold py-3 text-center">Actions</th>
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

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    <?php if ($userRole === 'admin'): ?>
    // Admin functionality - Load leave requests data
    $(document).ready(function() {
      loadLeaveStatistics();
      loadLeaveRequests();

      // Add search and filter functionality
      $('#searchInput').on('keyup', function() {
        filterLeaveRequests();
      });

      $('#statusFilter').on('change', function() {
        filterLeaveRequests();
      });

      function loadLeaveStatistics() {
        // First test if PHP script is responding
        $.ajax({
          url: 'leave-requests.php',
          method: 'POST',
          data: { action: 'test' },
          success: function(testResponse) {
            console.log('PHP Test Response:', testResponse);

            // Now load actual statistics
            $.ajax({
              url: 'leave-requests.php',
              method: 'POST',
              data: { action: 'get_stats' },
              success: function(data) {
                $('#pendingCount').text(data.pending || 0);
                $('#approvedCount').text(data.approved || 0);
                $('#rejectedCount').text(data.rejected || 0);
              },
              error: function(xhr, status, error) {
                console.error('Failed to load leave statistics:', error);
                console.error('Response:', xhr.responseText);
              }
            });
          },
          error: function(xhr, status, error) {
            console.error('PHP script not responding:', error);
            console.error('Response:', xhr.responseText);
          }
        });
      }

      let allLeaveRequests = []; // Store all requests for filtering

      function loadLeaveRequests() {
        $.ajax({
          url: 'leave-requests.php',
          method: 'POST',
          data: { action: 'get_leave_requests' },
          success: function(response) {
            console.log('AJAX Response:', response);

            if (response.success && response.requests && response.requests.length > 0) {
              allLeaveRequests = response.requests;
              displayLeaveRequests(allLeaveRequests);
            } else {
              console.log('No requests found or empty response');
              $('#leaveRequestsTable').html('<tr><td colspan="5" class="text-center text-muted">No leave requests found</td></tr>');
            }
          },
          error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Status:', status);
            console.error('Response:', xhr.responseText);
            $('#leaveRequestsTable').html('<tr><td colspan="5" class="text-center text-danger">Error loading leave requests: ' + error + '</td></tr>');
          }
        });
      }

      function filterLeaveRequests() {
        const searchTerm = $('#searchInput').val().toLowerCase();
        const statusFilter = $('#statusFilter').val();

        const filteredRequests = allLeaveRequests.filter(function(request) {
          // Filter by student name
          const matchesSearch = request.student_name.toLowerCase().includes(searchTerm);

          // Filter by status
          const matchesStatus = !statusFilter || request.status === statusFilter;

          return matchesSearch && matchesStatus;
        });

        displayLeaveRequests(filteredRequests);
      }

      function displayLeaveRequests(requests) {
        let html = '';
        requests.forEach(function(request) {
          const statusBadge = getStatusBadge(request.status);
          const actionButtons = getActionButtons(request.id, request.status);

          html += `<tr>
            <td><strong>${request.student_name}</strong></td>
            <td>${request.from_date}</td>
            <td>${request.to_date}</td>
            <td>${statusBadge}</td>
            <td class="text-center">${actionButtons}</td>
          </tr>`;
        });

        if (html === '') {
          html = '<tr><td colspan="5" class="text-center text-muted">No leave requests found</td></tr>';
        }

        $('#leaveRequestsTable').html(html);
      }

      function getStatusBadge(status) {
        switch(status) {
          case 'pending': return '<span class="badge bg-warning">Pending</span>';
          case 'approved': return '<span class="badge bg-success">Approved</span>';
          case 'rejected': return '<span class="badge bg-danger">Rejected</span>';
          default: return '<span class="badge bg-secondary">Unknown</span>';
        }
      }

      function getActionButtons(requestId, status) {
        if (status === 'pending') {
          return `
            <div class="btn-group" role="group" aria-label="Leave request actions">
              <button class="btn btn-success btn-sm" onclick="updateLeaveStatus(${requestId}, 'approved')" title="Approve Request">
                <i class="fas fa-check me-1"></i>Approve
              </button>
              <button class="btn btn-danger btn-sm" onclick="updateLeaveStatus(${requestId}, 'rejected')" title="Reject Request">
                <i class="fas fa-times me-1"></i>Reject
              </button>
            </div>
          `;
        }
        return '<span class="badge bg-light text-muted">No actions</span>';
      }

      window.updateLeaveStatus = function(requestId, newStatus) {
        if (confirm(`Are you sure you want to ${newStatus} this leave request?`)) {
          $.ajax({
            url: 'leave-requests.php',
            method: 'POST',
            data: { action: 'update_status', request_id: requestId, status: newStatus },
            success: function(response) {
              if (response.success) {
                loadLeaveStatistics();
                loadLeaveRequests();
                showAlert('Leave request ' + newStatus + ' successfully', 'success');
              } else {
                showAlert('Error updating leave request: ' + response.message, 'danger');
              }
            },
            error: function() {
              showAlert('Error updating leave request', 'danger');
            }
          });
        }
      }

      function showAlert(message, type) {
        // Remove existing alerts of the same type to avoid duplicates
        $(`.alert-${type}`).fadeOut(300, function() {
          $(this).remove();
        });

        const alertHtml = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
          <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
          ${message}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;

        // Insert at the appropriate location based on user role
        if (userRole === 'admin') {
          $('.card-body').first().prepend(alertHtml);
        } else {
          $('.form-container').prepend(alertHtml);
        }

        // Auto-dismiss after 5 seconds for non-error alerts
        if (type !== 'danger') {
          setTimeout(() => $('.alert').not('.alert-danger').fadeOut(300, function() { $(this).remove(); }), 5000);
        }
      }

      // Enhanced error handling for AJAX requests
      $(document).ajaxError(function(event, xhr, settings, thrownError) {
        console.error('AJAX Error:', thrownError);
        showAlert('An error occurred while processing your request. Please try again.', 'danger');
      });

      // Success callback for form submission (if handled via AJAX in the future)
      window.handleFormSuccess = function(response) {
        if (submitBtn) {
          submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>Success!';
          submitBtn.classList.remove('btn-primary');
          submitBtn.classList.add('btn-success');
        }

        showAlert('Leave request submitted successfully!', 'success');

        // Reset form after 2 seconds
        setTimeout(() => {
          document.getElementById('leaveRequestForm').reset();
          if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Request';
            submitBtn.classList.remove('btn-success');
            submitBtn.classList.add('btn-primary');
            submitBtn.disabled = false;
          }
        }, 2000);
      }

      // Error callback for form submission
      window.handleFormError = function(error) {
        if (submitBtn) {
          submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Request';
          submitBtn.disabled = false;
        }

        showAlert(error || 'Failed to submit leave request. Please try again.', 'danger');
      }
    });
    <?php else: ?>
    // Student functionality
    const requestTo = document.getElementById('requestTo');
    const courseSelectWrapper = document.getElementById('courseSelectWrapper');
    const submitBtn = document.querySelector('button[type="submit"]');

    // Enhanced course selection logic
    requestTo.addEventListener('change', function() {
      const courseSelect = document.getElementById('courseId');

      if (this.value === 'lecturer') {
        courseSelectWrapper.style.display = 'block';
        courseSelect.setAttribute('required', 'required');
        // Add fade-in animation
        courseSelectWrapper.style.opacity = '0';
        courseSelectWrapper.style.transition = 'opacity 0.3s ease';
        setTimeout(() => {
          courseSelectWrapper.style.opacity = '1';
        }, 50);
      } else {
        courseSelectWrapper.style.display = 'none';
        courseSelect.removeAttribute('required');
        courseSelect.value = ''; // Clear selection
      }
    });

    // Enhanced form validation
    function validateForm() {
      const fromDate = document.getElementById('fromDate').value;
      const toDate = document.getElementById('toDate').value;
      const reason = document.getElementById('reason').value.trim();
      const errors = [];

      // Date validation
      if (!fromDate) {
        errors.push('From Date is required');
      }

      if (!toDate) {
        errors.push('To Date is required');
      }

      if (fromDate && toDate) {
        const fromDateObj = new Date(fromDate);
        const toDateObj = new Date(toDate);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (fromDateObj < today) {
          errors.push('From Date cannot be in the past');
        }

        if (fromDateObj > toDateObj) {
          errors.push('From Date cannot be later than To Date');
        }

        // Check if leave duration is reasonable (max 30 days)
        const diffTime = Math.abs(toDateObj - fromDateObj);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        if (diffDays > 30) {
          errors.push('Leave duration cannot exceed 30 days');
        }
      }

      // Reason validation
      if (!reason) {
        errors.push('Reason for leave is required');
      } else if (reason.length < 10) {
        errors.push('Please provide a more detailed reason (at least 10 characters)');
      }

      return errors;
    }

    // Real-time validation feedback
    function showValidationErrors(errors) {
      // Remove existing error messages
      document.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
      document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

      // Show new errors
      errors.forEach(error => {
        showAlert(error, 'warning');
      });
    }

    document.getElementById('leaveRequestForm').addEventListener('submit', function (e) {
      const errors = validateForm();

      if (errors.length > 0) {
        e.preventDefault();
        showValidationErrors(errors);
        // Scroll to first error
        const firstError = document.querySelector('.is-invalid');
        if (firstError) {
          firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
      }

      // Show loading state
      if (submitBtn) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
        submitBtn.disabled = true;
      }
    });

    // Enhanced form field validation on blur
    document.getElementById('fromDate').addEventListener('blur', function() {
      if (this.value) {
        const date = new Date(this.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (date < today) {
          this.classList.add('is-invalid');
          showAlert('From Date cannot be in the past', 'warning');
        } else {
          this.classList.remove('is-invalid');
        }
      }
    });

    document.getElementById('toDate').addEventListener('blur', function() {
      const fromDate = document.getElementById('fromDate').value;
      if (this.value && fromDate) {
        if (new Date(this.value) < new Date(fromDate)) {
          this.classList.add('is-invalid');
          showAlert('To Date cannot be earlier than From Date', 'warning');
        } else {
          this.classList.remove('is-invalid');
        }
      }
    });

    document.getElementById('reason').addEventListener('blur', function() {
      if (this.value.trim().length < 10 && this.value.trim().length > 0) {
        this.classList.add('is-invalid');
        showAlert('Please provide a more detailed reason (at least 10 characters)', 'warning');
      } else {
        this.classList.remove('is-invalid');
      }
    });
    <?php endif; ?>
  </script>
</body>
</html>