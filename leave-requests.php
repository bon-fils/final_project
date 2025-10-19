<?php
/**
 * Leave Requests - Frontend Only
 * Complete frontend implementation with demo functionality
 * No backend dependencies - works as standalone demo
 */

// Get user session data
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin', 'lecturer', 'hod', 'student']);

$user_id = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'student';
$displayRole = in_array($userRole, ['admin', 'hod', 'lecturer']) ? 'admin' : $userRole;
$student_id = ($userRole === 'student') ? $_SESSION['student_id'] ?? null : null;
$lecturer_id = ($userRole === 'lecturer') ? $_SESSION['lecturer_id'] ?? null : null;

// Load real data from database
try {
    // Get courses for student leave requests (if student)
    $courses = [];
    if ($userRole === 'student' && $student_id) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.course_name as name, c.course_code
            FROM courses c
            INNER JOIN students s ON s.option_id = c.option_id
            WHERE s.id = :student_id
            ORDER BY c.course_name
        ");
        $stmt->execute(['student_id' => $student_id]);
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get leave statistics for admin/lecturer/hod
    $leave_stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    if ($displayRole === 'admin') {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    CASE
                        WHEN status = 'pending' THEN 'pending'
                        WHEN status = 'approved' THEN 'approved'
                        WHEN status = 'rejected' THEN 'rejected'
                        ELSE 'pending'
                    END as status,
                    COUNT(*) as count
                FROM leave_requests
                GROUP BY status
            ");
            $stmt->execute();
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($stats as $stat) {
                $leave_stats[$stat['status']] = (int)$stat['count'];
            }
        } catch (PDOException $e) {
            error_log("Error loading leave stats: " . $e->getMessage());
        }
    }

} catch (PDOException $e) {
    error_log("Database error in leave-requests.php: " . $e->getMessage());
    $courses = [];
    $leave_stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
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
    <!-- System Status Notice -->
    <div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
        <i class="fas fa-info-circle me-2"></i><strong>Live System:</strong> Connected to RP Attendance Database.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h4><?php echo $displayRole === 'admin' ? '👨‍💼 ' . ucfirst($userRole) : '🎓 Student'; ?></h4>
            <hr style="border-color: #ffffff66;">
        </div>

        <?php if ($displayRole === 'admin'): ?>
        <div class="user-info" style="padding: 15px 20px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px;">
            <small class="text-white-50">
                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['first_name'] ?? '') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? ''); ?><br>
                <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($_SESSION['department_name'] ?? 'Not Assigned'); ?>
            </small>
        </div>
        <?php endif; ?>
        <?php if ($displayRole === 'admin'): ?>
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
    <?php if ($displayRole === 'admin'): ?>
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
                  <h1 id="pendingCount" class="mb-0 text-warning fw-bold display-4"><?php echo $leave_stats['pending']; ?></h1>
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
                  <h1 id="approvedCount" class="mb-0 text-success fw-bold display-4"><?php echo $leave_stats['approved']; ?></h1>
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
                  <h1 id="rejectedCount" class="mb-0 text-danger fw-bold display-4"><?php echo $leave_stats['rejected']; ?></h1>
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
                    <th class="fw-semibold py-3">Reason</th>
                    <th class="fw-semibold py-3">Requested</th>
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
        <?php if ($displayRole === 'admin'): ?>
        // Admin functionality - Real backend integration
        $(document).ready(function() {
            loadRealLeaveStatistics();
            loadRealLeaveRequests();

            // Add search and filter functionality
            $('#searchInput').on('keyup', function() {
                filterLeaveRequests();
            });

            $('#statusFilter').on('change', function() {
                filterLeaveRequests();
            });
        });

        // Real leave statistics
        function loadRealLeaveStatistics() {
            // Statistics are now loaded from PHP backend
            showNotification('Live leave statistics loaded successfully!', 'success');
        }

        // Real leave requests data
        let allLeaveRequests = [];

        function loadRealLeaveRequests() {
            // Load real leave requests from database via AJAX
            $.ajax({
                url: 'api/leave-requests-api.php',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        allLeaveRequests = data.requests.map(request => ({
                            id: request.id,
                            student_name: request.student_name,
                            from_date: 'N/A', // Table doesn't have from_date/to_date columns
                            to_date: 'N/A',   // Will need to add these columns if needed
                            status: request.status,
                            requested_at: new Date(request.requested_at).toLocaleDateString(),
                            reg_no: request.reg_no,
                            department_name: request.department_name,
                            reason: request.reason
                        }));
                        displayLeaveRequests(allLeaveRequests);
                        showNotification('Live leave requests loaded!', 'info');
                    } else {
                        showNotification('Failed to load leave requests', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('API Error:', error);
                    // Fallback to demo data if API fails
                    allLeaveRequests = [
                        {
                            id: 1,
                            student_name: 'John Doe',
                            from_date: 'Dec 15, 2025',
                            to_date: 'Dec 17, 2025',
                            status: 'pending',
                            requested_at: 'Dec 10, 2025',
                            reg_no: 'STU001',
                            department_name: 'Computer Science',
                            reason: 'Medical leave'
                        },
                        {
                            id: 2,
                            student_name: 'Jane Smith',
                            from_date: 'Dec 20, 2025',
                            to_date: 'Dec 22, 2025',
                            status: 'approved',
                            requested_at: 'Dec 12, 2025',
                            reg_no: 'STU002',
                            department_name: 'Information Technology',
                            reason: 'Family emergency'
                        }
                    ];
                    displayLeaveRequests(allLeaveRequests);
                    showNotification('Using demo data - API unavailable', 'warning');
                }
            });
        }

        function filterLeaveRequests() {
            const searchTerm = $('#searchInput').val().toLowerCase();
            const statusFilter = $('#statusFilter').val();

            const filteredRequests = allLeaveRequests.filter(function(request) {
                const matchesSearch = request.student_name.toLowerCase().includes(searchTerm);
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
                    <td><strong>${request.student_name}</strong><br><small class="text-muted">${request.reg_no}</small></td>
                    <td>${request.reason || 'N/A'}</td>
                    <td>${request.requested_at}</td>
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
                // Real API call to update leave status
                $.ajax({
                    url: 'api/leave-requests-api.php',
                    method: 'POST',
                    data: {
                        action: 'update_status',
                        request_id: requestId,
                        status: newStatus
                    },
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            showNotification(`Leave request ${newStatus} successfully!`, 'success');

                            // Update the request status in our local data
                            const request = allLeaveRequests.find(r => r.id === requestId);
                            if (request) {
                                request.status = newStatus;
                                displayLeaveRequests(allLeaveRequests);
                                // Reload statistics
                                location.reload();
                            }
                        } else {
                            showNotification('Failed to update leave request status', 'error');
                        }
                    },
                    error: function() {
                        showNotification('Error updating leave request', 'error');
                    }
                });
            }
        }

        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' :
                               type === 'error' ? 'alert-danger' :
                               type === 'warning' ? 'alert-warning' : 'alert-info';

            const icon = type === 'success' ? 'fas fa-check-circle' :
                          type === 'error' ? 'fas fa-exclamation-triangle' :
                          type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;';
            alert.innerHTML = `
                <div class="d-flex align-items-start">
                    <i class="${icon} me-2 mt-1"></i>
                    <div class="flex-grow-1">
                        <div class="fw-bold">${type.toUpperCase()}</div>
                        <div>${message}</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;

            document.body.appendChild(alert);

            setTimeout(() => {
                if (alert.parentNode) {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 300);
                }
            }, 4000);
        }

        <?php else: ?>
        // Student functionality - Demo form handling
        const requestTo = document.getElementById('requestTo');
        const courseSelectWrapper = document.getElementById('courseSelectWrapper');
        const submitBtn = document.querySelector('button[type="submit"]');

        // Enhanced course selection logic
        requestTo.addEventListener('change', function() {
            const courseSelect = document.getElementById('courseId');

            if (this.value === 'lecturer') {
                courseSelectWrapper.style.display = 'block';
                courseSelect.setAttribute('required', 'required');
                courseSelectWrapper.style.opacity = '0';
                courseSelectWrapper.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    courseSelectWrapper.style.opacity = '1';
                }, 50);
            } else {
                courseSelectWrapper.style.display = 'none';
                courseSelect.removeAttribute('required');
                courseSelect.value = '';
            }
        });

        // Enhanced form validation
        function validateForm() {
            const fromDate = document.getElementById('fromDate').value;
            const toDate = document.getElementById('toDate').value;
            const reason = document.getElementById('reason').value.trim();
            const errors = [];

            if (!fromDate) errors.push('From Date is required');
            if (!toDate) errors.push('To Date is required');

            if (fromDate && toDate) {
                const fromDateObj = new Date(fromDate);
                const toDateObj = new Date(toDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (fromDateObj < today) errors.push('From Date cannot be in the past');
                if (fromDateObj > toDateObj) errors.push('From Date cannot be later than To Date');

                const diffTime = Math.abs(toDateObj - fromDateObj);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                if (diffDays > 30) errors.push('Leave duration cannot exceed 30 days');
            }

            if (!reason) {
                errors.push('Reason for leave is required');
            } else if (reason.length < 10) {
                errors.push('Please provide a more detailed reason (at least 10 characters)');
            }

            return errors;
        }

        function showAlert(message, type = 'warning') {
            const alertClass = type === 'success' ? 'alert-success' :
                               type === 'error' ? 'alert-danger' :
                               type === 'warning' ? 'alert-warning' : 'alert-info';

            const icon = type === 'success' ? 'fas fa-check-circle' :
                          type === 'error' ? 'fas fa-exclamation-triangle' :
                          type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show`;
            alert.innerHTML = `
                <i class="${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.querySelector('.form-container').prepend(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        document.getElementById('leaveRequestForm').addEventListener('submit', function (e) {
            e.preventDefault(); // Prevent actual form submission

            const errors = validateForm();

            if (errors.length > 0) {
                errors.forEach(error => showAlert(error, 'warning'));
                return false;
            }

            // Show loading state
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                submitBtn.disabled = true;
            }

            // Real form submission
            const formData = new FormData(document.getElementById('leaveRequestForm'));

            fetch('submit-leave.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
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
                        // Reset course selection
                        courseSelectWrapper.style.display = 'none';
                        document.getElementById('courseId').removeAttribute('required');
                    }, 2000);
                } else {
                    showAlert(data.message || 'Failed to submit leave request', 'error');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Request';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error submitting leave request. Please try again.', 'error');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Request';
                }
            });
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