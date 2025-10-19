<?php
/**
 * Lecturer Leave Requests - Backend Implementation
 * Displays and manages leave requests for lecturers
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_once "dashboard_utils.php";
require_role(['lecturer', 'hod']);

// Get user information
$user_id = $_SESSION['user_id'] ?? null;
$lecturer_id = $_SESSION['lecturer_id'] ?? null;

// Debug session data
error_log("lecturer-leave-requests.php - Session Debug:");
error_log("user_id: " . ($user_id ?? 'NULL'));
error_log("lecturer_id: " . ($lecturer_id ?? 'NULL'));
error_log("username: " . ($_SESSION['username'] ?? 'NULL'));
error_log("role: " . ($_SESSION['role'] ?? 'NULL'));

if (!$user_id || !$lecturer_id) {
    error_log("Session expired or invalid - redirecting to login");
    header("Location: login.php?error=session_expired");
    exit;
}

// Get lecturer information
try {
    $stmt = $pdo->prepare("
        SELECT u.username, u.email, u.first_name, u.last_name,
               l.department_id, d.name as department_name
        FROM users u
        INNER JOIN lecturers l ON u.id = l.user_id
        LEFT JOIN departments d ON l.department_id = d.id
        WHERE u.id = :user_id AND u.role = 'lecturer'
    ");
    $stmt->execute(['user_id' => $user_id]);
    $lecturer_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer_info) {
        header("Location: login.php?error=lecturer_not_found");
        exit;
    }

    $lecturer_name = trim(($lecturer_info['first_name'] ?? '') . ' ' . ($lecturer_info['last_name'] ?? ''));
    if (empty($lecturer_name)) {
        $lecturer_name = $lecturer_info['username'];
    }

} catch (PDOException $e) {
    error_log("Lecturer info error: " . $e->getMessage());
    header("Location: login.php?error=database");
    exit;
}

// Get leave requests for students in lecturer's department
try {
    $leave_requests = [];

    error_log("Querying leave requests for lecturer_id: " . $lecturer_id);

    // Get leave requests for students in the lecturer's department
    $stmt = $pdo->prepare("
        SELECT lr.id, lr.student_id, lr.reason, lr.start_date, lr.end_date,
               lr.status, lr.requested_at, lr.responded_at, lr.admin_response,
               s.first_name, s.last_name, s.reg_no, s.student_id_number,
               d.name as department_name, o.name as option_name, s.year_level
        FROM leave_requests lr
        INNER JOIN students s ON lr.student_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN options o ON s.option_id = o.id
        WHERE s.department_id = :department_id
        ORDER BY lr.requested_at DESC
    ");
    $stmt->execute(['department_id' => $lecturer_info['department_id']]);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Found " . count($leave_requests) . " leave requests for department: " . $lecturer_info['department_name']);

    // Calculate statistics
    $total_requests = count($leave_requests);
    $pending_requests = count(array_filter($leave_requests, fn($r) => $r['status'] === 'pending'));
    $approved_requests = count(array_filter($leave_requests, fn($r) => $r['status'] === 'approved'));
    $rejected_requests = count(array_filter($leave_requests, fn($r) => $r['status'] === 'rejected'));

} catch (PDOException $e) {
    error_log("Leave requests query error: " . $e->getMessage());
    $leave_requests = [];
    $total_requests = 0;
    $pending_requests = 0;
    $approved_requests = 0;
    $rejected_requests = 0;
}

// Handle AJAX requests for updating leave request status
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');

    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            $action = $_POST['action'];
            $request_id = $_POST['request_id'] ?? null;
            $response = $_POST['response'] ?? '';

            if (!$request_id) {
                echo json_encode(['status' => 'error', 'message' => 'Request ID is required']);
                exit;
            }

            if ($action === 'approve') {
                $stmt = $pdo->prepare("
                    UPDATE leave_requests
                    SET status = 'approved', responded_at = NOW(), admin_response = ?
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$response, $request_id]);
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("
                    UPDATE leave_requests
                    SET status = 'rejected', responded_at = NOW(), admin_response = ?
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$response, $request_id]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Leave request updated successfully']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update leave request']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Leave Requests | RP Attendance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <!-- Custom Styles -->
  <style>
      :root {
          --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
          --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
          --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
          --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
          --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
          --card-shadow-hover: 0 20px 40px rgba(0,0,0,0.15);
      }

      body {
          font-family: 'Poppins', sans-serif;
          background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
          min-height: 100vh;
      }

      .stats-card {
          background: white;
          border-radius: 15px;
          padding: 25px;
          box-shadow: var(--card-shadow);
          transition: all 0.3s ease;
          border: none;
          position: relative;
          overflow: hidden;
      }

      .stats-card::before {
          content: '';
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          height: 4px;
          background: var(--primary-gradient);
      }

      .stats-card:hover {
          transform: translateY(-5px);
          box-shadow: var(--card-shadow-hover);
      }

      .stats-icon {
          width: 60px;
          height: 60px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 24px;
          margin-bottom: 15px;
      }

      .leave-card {
          background: white;
          border-radius: 15px;
          overflow: hidden;
          box-shadow: var(--card-shadow);
          transition: all 0.3s ease;
          border: none;
          margin-bottom: 20px;
      }

      .leave-card:hover {
          transform: translateY(-5px);
          box-shadow: var(--card-shadow-hover);
      }

      .leave-header {
          background: var(--primary-gradient);
          color: white;
          padding: 20px;
          position: relative;
      }

      .leave-status {
          position: absolute;
          top: 15px;
          right: 15px;
          padding: 5px 12px;
          border-radius: 20px;
          font-size: 0.8rem;
          font-weight: 600;
      }

      .leave-body {
          padding: 25px;
      }

      .leave-title {
          font-size: 1.3rem;
          font-weight: 600;
          margin-bottom: 5px;
          color: #2d3748;
      }

      .leave-code {
          color: #718096;
          font-weight: 500;
          margin-bottom: 15px;
      }

      .leave-meta {
          display: flex;
          flex-wrap: wrap;
          gap: 15px;
          margin-bottom: 20px;
      }

      .meta-item {
          display: flex;
          align-items: center;
          gap: 5px;
          color: #718096;
          font-size: 0.9rem;
      }

      .leave-details {
          background: #f8fafc;
          border-radius: 10px;
          padding: 15px;
          margin-bottom: 20px;
      }

      .detail-item {
          text-align: center;
      }

      .detail-value {
          font-size: 1.5rem;
          font-weight: 700;
          color: #2d3748;
      }

      .detail-label {
          font-size: 0.8rem;
          color: #718096;
          text-transform: uppercase;
          letter-spacing: 0.5px;
      }

      .action-buttons {
          display: flex;
          gap: 10px;
          flex-wrap: wrap;
      }

      .btn-action {
          border-radius: 25px;
          padding: 8px 20px;
          font-weight: 500;
          transition: all 0.3s ease;
      }

      .search-container {
          background: white;
          border-radius: 15px;
          padding: 25px;
          box-shadow: var(--card-shadow);
          margin-bottom: 30px;
      }

      .search-input {
          border: 2px solid #e2e8f0;
          border-radius: 25px;
          padding: 12px 20px;
          font-size: 1rem;
          transition: all 0.3s ease;
      }

      .search-input:focus {
          border-color: #667eea;
          box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
          outline: none;
      }

      .filter-buttons {
          display: flex;
          gap: 10px;
          flex-wrap: wrap;
          margin-top: 15px;
      }

      .filter-btn {
          border-radius: 20px;
          padding: 6px 15px;
          border: 2px solid #e2e8f0;
          background: white;
          color: #718096;
          transition: all 0.3s ease;
      }

      .filter-btn.active {
          background: var(--primary-gradient);
          border-color: transparent;
          color: white;
      }

      .empty-state {
          text-align: center;
          padding: 60px 20px;
          color: #718096;
      }

      .empty-state i {
          font-size: 4rem;
          margin-bottom: 20px;
          opacity: 0.5;
      }

      @media (max-width: 768px) {
          .stats-card {
              margin-bottom: 20px;
          }

          .action-buttons {
              flex-direction: column;
          }

          .action-buttons .btn {
              width: 100%;
          }
      }

      .fade-in {
          animation: fadeIn 0.5s ease-in;
      }

      @keyframes fadeIn {
          from { opacity: 0; transform: translateY(20px); }
          to { opacity: 1; transform: translateY(0); }
      }

      /* Sidebar styles */
      .sidebar {
          position: fixed;
          top: 0;
          left: 0;
          height: 100vh;
          width: 250px;
          background: var(--primary-gradient);
          color: white;
          padding: 20px 0;
          z-index: 100;
          box-shadow: 2px 0 10px rgba(0,0,0,0.1);
      }

      .sidebar .sidebar-header {
          padding: 0 20px 20px;
          border-bottom: 1px solid rgba(255,255,255,0.2);
          margin-bottom: 20px;
      }

      .sidebar .sidebar-header h4 {
          margin: 0;
          font-weight: 600;
          font-size: 18px;
      }

      .sidebar .sidebar-header .subtitle {
          font-size: 12px;
          opacity: 0.8;
          margin-top: 4px;
      }

      .sidebar a {
          color: white;
          text-decoration: none;
          padding: 12px 20px;
          display: block;
          transition: all 0.3s ease;
          border-left: 3px solid transparent;
      }

      .sidebar a:hover, .sidebar a.active {
          background: rgba(255, 255, 255, 0.1);
          border-left-color: rgba(255, 255, 255, 0.5);
      }

      .sidebar a i {
          margin-right: 10px;
          width: 16px;
          text-align: center;
      }

      .main-content {
          margin-left: 250px;
          padding: 20px;
          min-height: 100vh;
      }

      .topbar {
          background: white;
          padding: 20px;
          border-radius: 10px;
          box-shadow: 0 4px 6px rgba(0,0,0,0.1);
          margin-bottom: 20px;
          display: flex;
          justify-content: space-between;
          align-items: center;
      }

      .page-header {
          text-align: center;
          padding: 30px 0;
          margin-bottom: 30px;
      }

      .footer {
          text-align: center;
          padding: 20px;
          color: #718096;
          font-size: 0.9rem;
      }
  </style>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

</head>
<body>
    <!-- System Status Notice -->
    <div class="alert alert-info alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
        <i class="fas fa-info-circle me-2"></i><strong>Live System:</strong> Connected to RP Attendance Database.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Include Lecturer Sidebar -->
    <?php include 'includes/lecturer-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h1 class="page-title">Leave Requests Management</h1>
                <div class="system-title">Rwanda Polytechnic Attendance System</div>
                <div class="user-info mt-1">
                    <small class="text-muted">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($lecturer_name); ?> |
                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($lecturer_info['department_name'] ?? 'Not Assigned'); ?>
                    </small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-success">Live System</span>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshRequests()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>

    <!-- Statistics Overview -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="stat-value text-primary"><?= $total_requests ?></div>
                <div class="stat-label">Total Requests</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-warning" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value text-warning"><?= $pending_requests ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-success" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value text-success"><?= $approved_requests ?></div>
                <div class="stat-label">Approved</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="stats-icon text-danger" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white;">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value text-danger"><?= $rejected_requests ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-container">
        <div class="row g-3 align-items-center">
            <div class="col-md-8">
                <input type="text" id="requestSearch" class="form-control search-input" placeholder="🔍 Search requests by student name, registration number, or reason...">
            </div>
            <div class="col-md-4">
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <button class="btn btn-outline-primary btn-sm" onclick="location.reload()" title="Refresh Requests">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">
                <i class="fas fa-th-large me-1"></i>All Requests (<?= $total_requests ?>)
            </button>
            <button class="filter-btn" data-filter="pending">
                <i class="fas fa-clock me-1"></i>Pending (<?= $pending_requests ?>)
            </button>
            <button class="filter-btn" data-filter="approved">
                <i class="fas fa-check-circle me-1"></i>Approved (<?= $approved_requests ?>)
            </button>
            <button class="filter-btn" data-filter="rejected">
                <i class="fas fa-times-circle me-1"></i>Rejected (<?= $rejected_requests ?>)
            </button>
            <button class="filter-btn" data-filter="recent">
                <i class="fas fa-calendar-day me-1"></i>Recent (7 days)
            </button>
        </div>
    </div>

    <!-- Leave Requests Grid -->
    <div class="row g-4" id="requestContainer">
        <?php if (!empty($leave_requests) && is_array($leave_requests)): ?>
            <?php foreach ($leave_requests as $index => $request): ?>
                <div class="col-lg-6 col-xl-4 leave-card fade-in" data-request-id="<?= htmlspecialchars($request['id']) ?>"
                     data-status="<?= htmlspecialchars($request['status']) ?>"
                     data-requested-at="<?= htmlspecialchars(strtotime($request['requested_at'])) ?>"
                     style="animation-delay: <?= $index * 0.1 ?>s">
                    <div class="leave-card">
                        <div class="leave-header">
                            <div class="leave-status bg-<?= $request['status'] === 'pending' ? 'warning' : ($request['status'] === 'approved' ? 'success' : 'danger') ?>">
                                <?= ucfirst(htmlspecialchars($request['status'])) ?>
                            </div>
                            <h5 class="leave-title mb-1">
                                <?= htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) ?>
                            </h5>
                            <div class="leave-code">
                                <?= htmlspecialchars($request['reg_no']) ?>
                            </div>
                        </div>

                        <div class="leave-body">
                            <div class="leave-meta">
                                <div class="meta-item">
                                    <i class="fas fa-building text-primary"></i>
                                    <span><?= htmlspecialchars($request['department_name']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-graduation-cap text-success"></i>
                                    <span>Year <?= htmlspecialchars($request['year_level']) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-calendar text-warning"></i>
                                    <span><?= htmlspecialchars(date('M d, Y', strtotime($request['requested_at']))) ?></span>
                                </div>
                            </div>

                            <!-- Leave Details -->
                            <div class="leave-details">
                                <div class="row g-3">
                                    <div class="col-6 detail-item">
                                        <div class="detail-value text-primary">
                                            <?= htmlspecialchars(date('M d', strtotime($request['start_date']))) ?>
                                        </div>
                                        <div class="detail-label">Start Date</div>
                                    </div>
                                    <div class="col-6 detail-item">
                                        <div class="detail-value text-primary">
                                            <?= htmlspecialchars(date('M d', strtotime($request['end_date']))) ?>
                                        </div>
                                        <div class="detail-label">End Date</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reason -->
                            <div class="mb-3">
                                <strong>Reason:</strong>
                                <p class="text-muted small mb-0">
                                    <?= htmlspecialchars(substr($request['reason'], 0, 100)) ?>
                                    <?= strlen($request['reason']) > 100 ? '...' : '' ?>
                                </p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="action-buttons">
                                <?php if ($request['status'] === 'pending'): ?>
                                    <button class="btn btn-success btn-action" onclick="respondToRequest(<?= htmlspecialchars($request['id']) ?>, 'approve')">
                                        <i class="fas fa-check me-1"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-action" onclick="respondToRequest(<?= htmlspecialchars($request['id']) ?>, 'reject')">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-outline-info btn-action" onclick="viewRequestDetails(<?= htmlspecialchars($request['id']) ?>)">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-file-signature text-muted"></i>
                    <h4>No Leave Requests</h4>
                    <p>No leave requests are currently available for your department. Students will submit requests here when needed.</p>
                    <button class="btn btn-primary" onclick="refreshRequests()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh Data
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

    <!-- Footer -->
    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Lecturer Panel - Live System
    </div>

    <!-- Response Modal -->
    <div class="modal fade" id="responseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Respond to Leave Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="responseForm">
                        <input type="hidden" id="modalRequestId" name="request_id">
                        <input type="hidden" id="modalAction" name="action">
                        <div class="mb-3">
                            <label for="responseText" class="form-label">Response Message (Optional)</label>
                            <textarea class="form-control" id="responseText" name="response" rows="3" placeholder="Add a note for the student..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn" id="confirmResponseBtn" onclick="submitResponse()">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Enhanced JavaScript for Live System -->
    <script>
    // Global variables
    let allRequests = [];
    let filteredRequests = [];

    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        initializeRequests();
        setupEventListeners();
        showWelcomeMessage();
        setupAutoRefresh();
    });

    // Show welcome message
    function showWelcomeMessage() {
        setTimeout(() => {
            showNotification('Welcome to Leave Requests Management! Real-time data from RP Attendance System.', 'success');
        }, 1000);
    }

    // Initialize requests data
    function initializeRequests() {
        const requestCards = document.querySelectorAll('.leave-card');
        allRequests = Array.from(requestCards);
        filteredRequests = [...allRequests];
        updateFilterCounts();
    }

    // Setup event listeners
    function setupEventListeners() {
        // Search functionality
        const searchInput = document.getElementById('requestSearch');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(handleSearch, 300));
        }

        // Filter buttons
        const filterButtons = document.querySelectorAll('.filter-btn');
        filterButtons.forEach(button => {
            button.addEventListener('click', handleFilter);
        });
    }

    // Setup auto-refresh functionality
    function setupAutoRefresh() {
        // Auto-refresh every 2 minutes for leave requests
        setInterval(refreshRequests, 120000);

        // Handle visibility change
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                refreshRequests();
            }
        });
    }

    // Refresh requests data from server
    function refreshRequests() {
        const refreshBtn = document.querySelector('button[onclick="refreshRequests()"]');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
        }

        // For demo purposes, just reload the page
        // In a real implementation, you'd fetch updated data via AJAX
        setTimeout(() => {
            location.reload();
        }, 1000);
    }

    // Enhanced search functionality with debouncing
    function handleSearch() {
        const query = this.value.toLowerCase().trim();
        const filterType = document.querySelector('.filter-btn.active').dataset.filter;

        filteredRequests = allRequests.filter(card => {
            const title = card.querySelector('.leave-title').textContent.toLowerCase();
            const code = card.querySelector('.leave-code').textContent.toLowerCase();
            const reason = card.querySelector('p') ? card.querySelector('p').textContent.toLowerCase() : '';

            const matchesSearch = !query ||
                title.includes(query) ||
                code.includes(query) ||
                reason.includes(query);

            const matchesFilter = checkFilterMatch(card, filterType);

            return matchesSearch && matchesFilter;
        });

        updateRequestDisplay();
    }

    // Filter functionality
    function handleFilter() {
        // Update active filter button
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');

        const filterType = this.dataset.filter;
        const searchQuery = document.getElementById('requestSearch').value.toLowerCase().trim();

        filteredRequests = allRequests.filter(card => {
            const matchesSearch = !searchQuery ||
                card.querySelector('.leave-title').textContent.toLowerCase().includes(searchQuery) ||
                card.querySelector('.leave-code').textContent.toLowerCase().includes(searchQuery);

            const matchesFilter = checkFilterMatch(card, filterType);

            return matchesSearch && matchesFilter;
        });

        updateRequestDisplay();
    }

    // Check if request matches filter criteria
    function checkFilterMatch(card, filterType) {
        const status = card.dataset.status;
        const requestedAt = parseInt(card.dataset.requestedAt) || 0;

        switch (filterType) {
            case 'all':
                return true;
            case 'pending':
                return status === 'pending';
            case 'approved':
                return status === 'approved';
            case 'rejected':
                return status === 'rejected';
            case 'recent':
                const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
                return requestedAt > sevenDaysAgo;
            default:
                return true;
        }
    }

    // Update request display with animations
    function updateRequestDisplay() {
        const container = document.getElementById('requestContainer');

        // Hide all cards first
        allRequests.forEach(card => {
            card.style.display = 'none';
            card.classList.remove('fade-in');
        });

        // Show filtered cards with staggered animation
        filteredRequests.forEach((card, index) => {
            setTimeout(() => {
                card.style.display = 'block';
                card.classList.add('fade-in');
            }, index * 50);
        });

        // Update filter counts after filtering
        updateFilterCounts();
    }

    // Update filter button counts
    function updateFilterCounts() {
        const filters = {
            'all': allRequests.length,
            'pending': allRequests.filter(card => card.dataset.status === 'pending').length,
            'approved': allRequests.filter(card => card.dataset.status === 'approved').length,
            'rejected': allRequests.filter(card => card.dataset.status === 'rejected').length,
            'recent': allRequests.filter(card => {
                const requestedAt = parseInt(card.dataset.requestedAt) || 0;
                const sevenDaysAgo = Date.now() - (7 * 24 * 60 * 60 * 1000);
                return requestedAt > sevenDaysAgo;
            }).length
        };

        document.querySelectorAll('.filter-btn').forEach(button => {
            const filterType = button.dataset.filter;
            const count = filters[filterType] || 0;
            const icon = button.querySelector('i').outerHTML;
            const text = button.textContent.replace(/\(\d+\)$/, '').trim();
            button.innerHTML = `${icon} ${text} (${count})`;
        });
    }

    // Respond to leave request
    function respondToRequest(requestId, action) {
        const modal = new bootstrap.Modal(document.getElementById('responseModal'));
        const form = document.getElementById('responseForm');
        const confirmBtn = document.getElementById('confirmResponseBtn');

        document.getElementById('modalRequestId').value = requestId;
        document.getElementById('modalAction').value = action;
        document.getElementById('responseText').value = '';

        // Update modal appearance based on action
        if (action === 'approve') {
            confirmBtn.className = 'btn btn-success';
            confirmBtn.textContent = 'Approve Request';
            document.querySelector('.modal-title').textContent = 'Approve Leave Request';
        } else {
            confirmBtn.className = 'btn btn-danger';
            confirmBtn.textContent = 'Reject Request';
            document.querySelector('.modal-title').textContent = 'Reject Leave Request';
        }

        modal.show();
    }

    // Submit response
    function submitResponse() {
        const formData = new FormData(document.getElementById('responseForm'));

        fetch('lecturer-leave-requests.php?ajax=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                showNotification('Leave request updated successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('responseModal')).hide();
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification('Failed to update request: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Response error:', error);
            showNotification('Error updating request. Please try again.', 'error');
        });
    }

    // View request details
    function viewRequestDetails(requestId) {
        showNotification(`Loading detailed information for request ${requestId}...`, 'info');
        // Could implement modal or redirect to request details page
    }

    // Enhanced notification system
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

        // Auto remove after 4 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 4000);
    }

    // Debounce utility function
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    console.log('🎓 Lecturer Leave Requests Live System loaded successfully!');
    console.log('💡 Real-time data from RP Attendance Database');
    console.log('🔍 Use search to find specific requests');
    console.log('🔄 Auto-refresh enabled - data updates every 2 minutes');
    </script>

</body>
</html>