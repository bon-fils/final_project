<?php
/**
 * Leave Status - Student Portal
 * View all leave requests made by the student and their current status
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['student']);

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// Get student info with enhanced details
$stmt = $pdo->prepare("
    SELECT s.*, u.email, u.first_name, u.last_name, u.username
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.user_id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    header("Location: login.php");
    exit;
}

// Get all leave requests for this student (not just last 5)
$sql = "
    SELECT lr.*,
           COALESCE(u.first_name, lr.reviewed_by) as reviewer_first_name,
           COALESCE(u.last_name, '') as reviewer_last_name,
           u.role as reviewer_role
    FROM leave_requests lr
    LEFT JOIN users u ON lr.reviewed_by_id = u.id
    WHERE lr.student_id = ?
    ORDER BY lr.requested_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$student['id']]);
$leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_requests = count($leave_requests);
$pending_count = count(array_filter($leave_requests, fn($lr) => $lr['status'] === 'pending'));
$approved_count = count(array_filter($leave_requests, fn($lr) => $lr['status'] === 'approved'));
$rejected_count = count(array_filter($leave_requests, fn($lr) => $lr['status'] === 'rejected'));

// Get recent activity (last 30 days)
$recent_requests = array_filter($leave_requests, function($lr) {
    $request_date = strtotime($lr['requested_at']);
    $thirty_days_ago = strtotime('-30 days');
    return $request_date >= $thirty_days_ago;
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Status | Student | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #0066cc;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background-color: #343a40;
            color: white;
            padding: 20px 0;
        }

        .sidebar a {
            display: block;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
        }

        .sidebar a.active {
            background-color: #007bff;
        }

        .topbar {
            margin-left: var(--sidebar-width);
            background-color: white;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }

        .stats-card .icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .stats-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stats-card .label {
            color: #666;
            font-size: 0.9rem;
        }

        .leave-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
            border-left: 4px solid var(--primary-color);
        }

        .leave-card.pending {
            border-left-color: var(--warning-color);
        }

        .leave-card.approved {
            border-left-color: var(--success-color);
        }

        .leave-card.rejected {
            border-left-color: var(--danger-color);
        }

        .leave-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .leave-header.pending {
            background: linear-gradient(135deg, var(--warning-color), #e0a800);
        }

        .leave-header.approved {
            background: linear-gradient(135deg, var(--success-color), #218838);
        }

        .leave-header.rejected {
            background: linear-gradient(135deg, var(--danger-color), #c82333);
        }

        .leave-title {
            font-weight: 600;
            margin: 0;
        }

        .leave-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .leave-body {
            padding: 20px;
        }

        .leave-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }

        .detail-value {
            color: #666;
            font-size: 0.9rem;
        }

        .leave-reason {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
        }

        .leave-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .footer {
            text-align: center;
            margin-left: var(--sidebar-width);
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid #dee2e6;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }

            .topbar, .main-content, .footer {
                margin-left: 0;
            }

            .leave-details {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .leave-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<?php
// Include student sidebar for consistent navigation
$student_name = htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$student = $student ?? ['department_name' => 'Department'];
include 'includes/student_sidebar.php';
?>

<div class="topbar">
    <h5 class="m-0 fw-bold">Leave Request Status</h5>
    <span>RP Attendance System</span>
</div>

<div class="main-content">
    <!-- Statistics Overview -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="icon text-primary">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="value"><?php echo htmlspecialchars($total_requests); ?></div>
                <div class="label">Total Requests</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="icon text-warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="value"><?php echo htmlspecialchars($pending_count); ?></div>
                <div class="label">Pending</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="icon text-success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="value"><?php echo htmlspecialchars($approved_count); ?></div>
                <div class="label">Approved</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="icon text-danger">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="value"><?php echo htmlspecialchars($rejected_count); ?></div>
                <div class="label">Rejected</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-buttons">
        <button class="filter-btn active" data-filter="all">All Requests (<?php echo $total_requests; ?>)</button>
        <button class="filter-btn" data-filter="pending">Pending (<?php echo $pending_count; ?>)</button>
        <button class="filter-btn" data-filter="approved">Approved (<?php echo $approved_count; ?>)</button>
        <button class="filter-btn" data-filter="rejected">Rejected (<?php echo $rejected_count; ?>)</button>
        <button class="filter-btn" data-filter="recent">Recent (30 days)</button>
    </div>

    <!-- Leave Requests -->
    <div id="leaveRequestsContainer">
        <?php if (count($leave_requests) > 0): ?>
            <?php foreach ($leave_requests as $lr): ?>
                <div class="leave-card <?php echo htmlspecialchars($lr['status']); ?>" data-status="<?php echo htmlspecialchars($lr['status']); ?>" data-date="<?php echo strtotime($lr['requested_at']); ?>">
                    <div class="leave-header <?php echo htmlspecialchars($lr['status']); ?>">
                        <div class="leave-title">
                            Leave Request #<?php echo htmlspecialchars($lr['id']); ?>
                        </div>
                        <div class="leave-status status-<?php echo htmlspecialchars($lr['status']); ?>">
                            <?php
                            switch($lr['status']){
                                case 'pending': echo '⏳ Pending Review'; break;
                                case 'approved': echo '✅ Approved'; break;
                                case 'rejected': echo '❌ Rejected'; break;
                                default: echo 'Unknown';
                            }
                            ?>
                        </div>
                    </div>

                    <div class="leave-body">
                        <!-- Extract dates and reason from structured format -->
                        <?php
                        $from_date = '-';
                        $to_date = '-';
                        $main_reason = 'Leave Request';
                        $requested_to = 'HoD';
                        $course_id = 'All Courses';

                        if (preg_match('/From:\s*([^\n\r]+)/', $lr['reason'], $matches)) {
                            $from_date = date("M d, Y", strtotime(trim($matches[1])));
                        }
                        if (preg_match('/To:\s*([^\n\r]+)/', $lr['reason'], $matches)) {
                            $to_date = date("M d, Y", strtotime(trim($matches[1])));
                        }
                        if (preg_match('/Requested To:\s*(HoD|Lecturer)/', $lr['reason'], $matches)) {
                            $requested_to = $matches[1];
                        }
                        if (preg_match('/Course ID:\s*([^\n\r]+)/', $lr['reason'], $matches)) {
                            $course_id = 'Course ID: ' . trim($matches[1]);
                        }

                        $reason_parts = explode('-- Details --', $lr['reason']);
                        if (!empty($reason_parts[0])) {
                            $main_reason = trim($reason_parts[0]);
                        }
                        ?>

                        <div class="leave-details">
                            <div class="detail-item">
                                <div class="detail-label">From Date</div>
                                <div class="detail-value"><?php echo htmlspecialchars($from_date); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">To Date</div>
                                <div class="detail-value"><?php echo htmlspecialchars($to_date); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Requested To</div>
                                <div class="detail-value"><?php echo htmlspecialchars($requested_to); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Course</div>
                                <div class="detail-value"><?php echo htmlspecialchars($course_id); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Submitted</div>
                                <div class="detail-value"><?php echo date("M d, Y H:i", strtotime($lr['requested_at'])); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Reviewed By</div>
                                <div class="detail-value">
                                    <?php
                                    if (!empty($lr['reviewer_first_name'])) {
                                        echo htmlspecialchars($lr['reviewer_first_name'] . ' ' . $lr['reviewer_last_name']);
                                        if (!empty($lr['reviewer_role'])) {
                                            echo ' (' . htmlspecialchars(ucfirst($lr['reviewer_role'])) . ')';
                                        }
                                    } elseif (!empty($lr['reviewed_by'])) {
                                        echo htmlspecialchars($lr['reviewed_by']);
                                    } else {
                                        echo 'Not reviewed yet';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="leave-reason">
                            <strong>Reason:</strong><br>
                            <?php echo htmlspecialchars($main_reason); ?>
                        </div>

                        <div class="leave-actions">
                            <?php if(!empty($lr['supporting_file'])): ?>
                                <a href="uploads/leave_docs/<?php echo htmlspecialchars($lr['supporting_file']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-file-pdf me-1"></i>View Document
                                </a>
                            <?php endif; ?>

                            <?php if($lr['status'] === 'pending'): ?>
                                <button class="btn btn-outline-warning btn-sm" onclick="editRequest(<?php echo $lr['id']; ?>)">
                                    <i class="fas fa-edit me-1"></i>Edit Request
                                </button>
                            <?php endif; ?>

                            <button class="btn btn-outline-info btn-sm" onclick="viewDetails(<?php echo $lr['id']; ?>)">
                                <i class="fas fa-eye me-1"></i>View Details
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-signature"></i>
                <h4>No Leave Requests</h4>
                <p>You haven't submitted any leave requests yet.</p>
                <a href="request-leave.php" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Submit New Request
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Student Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const leaveCards = document.querySelectorAll('.leave-card');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            filterButtons.forEach(btn => btn.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            const filterType = this.dataset.filter;

            leaveCards.forEach(card => {
                const status = card.dataset.status;
                const requestDate = parseInt(card.dataset.date);
                const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);

                let show = false;

                switch (filterType) {
                    case 'all':
                        show = true;
                        break;
                    case 'pending':
                        show = status === 'pending';
                        break;
                    case 'approved':
                        show = status === 'approved';
                        break;
                    case 'rejected':
                        show = status === 'rejected';
                        break;
                    case 'recent':
                        show = requestDate >= thirtyDaysAgo;
                        break;
                }

                card.style.display = show ? 'block' : 'none';
            });
        });
    });
});

// Action functions
function editRequest(requestId) {
    // Redirect to edit page or show modal
    alert('Edit functionality will be implemented. Request ID: ' + requestId);
}

function viewDetails(requestId) {
    // Show detailed modal or redirect
    alert('Detailed view will be implemented. Request ID: ' + requestId);
}
</script>

</body>
</html>
