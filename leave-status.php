<?php
declare(strict_types=1);
session_start();

require_once "config.php";
require_once "session_check.php";
require_role(['student']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Get student information
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.reg_no, s.year_level,
               u.first_name, u.last_name, u.email,
               d.name as department_name, o.name as option_name
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN options o ON s.option_id = o.id
        LEFT JOIN departments d ON o.department_id = d.id
        WHERE s.user_id = ? AND u.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: login.php?error=student_not_found");
        exit();
    }

} catch (Exception $e) {
    error_log("Student info error: " . $e->getMessage());
    header("Location: login.php?error=database");
    exit();
}

// Get leave requests for this student
$leave_requests = [];
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

try {
    $stmt = $pdo->prepare("
        SELECT
            id, leave_type, start_date, end_date, reason, status,
            created_at, reviewed_at, reviewer_comments
        FROM leave_requests
        WHERE student_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$student['id']]);
    $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $stats['total'] = count($leave_requests);
    foreach ($leave_requests as $request) {
        $stats[$request['status']]++;
    }

} catch (Exception $e) {
    error_log("Leave requests error: " . $e->getMessage());
    $leave_requests = [];
}

$page_title = "Leave Status";
$current_page = "leave-status";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark-blue: #1e3a8a;
            --secondary-dark-blue: #1e40af;
            --light-blue: #dbeafe;
            --accent-blue: #3b82f6;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --success-color: #16a34a;
            --warning-color: #d97706;
            --danger-color: #dc2626;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light-blue) 0%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--text-dark);
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }

        .topbar {
            margin-left: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            border-bottom: 1px solid rgba(30, 58, 138, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.08);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.05);
            border: 1px solid rgba(30, 58, 138, 0.1);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stat-primary .stat-icon {
            background: linear-gradient(135deg, var(--primary-dark-blue) 0%, var(--secondary-dark-blue) 100%);
            color: white;
        }

        .stat-warning .stat-icon {
            background: linear-gradient(135deg, var(--warning-color) 0%, #92400e 100%);
            color: white;
        }

        .stat-success .stat-icon {
            background: linear-gradient(135deg, var(--success-color) 0%, #15803d 100%);
            color: white;
        }

        .stat-danger .stat-icon {
            background: linear-gradient(135deg, var(--danger-color) 0%, #b91c1c 100%);
            color: white;
        }

        .stat-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-content p {
            font-size: 0.9rem;
            color: var(--text-light);
        }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.05);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border: none;
            font-weight: 600;
            color: var(--text-dark);
            padding: 16px;
            font-size: 0.9rem;
        }

        .table tbody td {
            padding: 16px;
            border-top: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: rgba(217, 119, 6, 0.1);
            color: var(--warning-color);
            border: 1px solid rgba(217, 119, 6, 0.2);
        }

        .status-approved {
            background: rgba(22, 163, 74, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(22, 163, 74, 0.2);
        }

        .status-rejected {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .leave-details {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
            box-shadow: 0 2px 10px rgba(30, 58, 138, 0.05);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .details-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .details-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--light-blue);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark-blue);
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .detail-item {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #e5e7eb;
        }

        .detail-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .comments-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .comments-section h6 {
            color: var(--text-dark);
            margin-bottom: 12px;
        }

        .comments-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #e5e7eb;
        }

        .footer {
            margin-left: 280px;
            text-align: center;
            padding: 20px;
            border-top: 1px solid rgba(30, 58, 138, 0.1);
            background: white;
        }

        @media (max-width: 768px) {
            .main-content, .topbar, .footer {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Student Sidebar -->
    <?php include_once 'includes/students-sidebar.php'; ?>

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-envelope-open-text me-2"></i><?php echo $page_title; ?>
            </h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <small class="text-muted d-block">Welcome back</small>
                <span class="fw-semibold"><?php echo htmlspecialchars($student['first_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-envelope-open-text me-3 text-primary"></i><?php echo $page_title; ?>
                </h1>
                <p class="page-subtitle">Track the status of your leave requests</p>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo htmlspecialchars($stats['total']); ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>

            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo htmlspecialchars($stats['pending']); ?></h3>
                    <p>Pending</p>
                </div>
            </div>

            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo htmlspecialchars($stats['approved']); ?></h3>
                    <p>Approved</p>
                </div>
            </div>

            <div class="stat-card stat-danger">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo htmlspecialchars($stats['rejected']); ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>

        <!-- Leave Requests Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leave_requests)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Leave Requests Found</h5>
                                <p class="text-muted mb-0">You haven't submitted any leave requests yet</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leave_requests as $request): ?>
                            <?php
                            $status_class = 'status-' . $request['status'];
                            $status_text = ucfirst($request['status']);
                            $leave_type_text = ucfirst(str_replace('_', ' ', $request['leave_type']));

                            $start_date = date('M d, Y', strtotime($request['start_date']));
                            $end_date = date('M d, Y', strtotime($request['end_date']));
                            $duration = ($start_date === $end_date) ? $start_date : $start_date . ' - ' . $end_date;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($leave_type_text); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($duration); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewDetails(<?php echo $request['id']; ?>)">
                                        <i class="fas fa-eye"></i> Details
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Leave Details Modal -->
        <div class="modal fade" id="leaveDetailsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-file-alt me-2"></i>Leave Request Details
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="leaveDetailsContent">
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div>&copy; 2025 Rwanda Polytechnic | Student Management System</div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.student-sidebar');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
        }

        // Auto-hide mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.student-sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768 && sidebar && toggle) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // View leave details
        function viewDetails(requestId) {
            fetch(`api/leave-details-api.php?id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayLeaveDetails(data.request);
                    } else {
                        alert('Failed to load leave details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load leave details');
                });
        }

        function displayLeaveDetails(request) {
            const statusClass = `status-${request.status}`;
            const statusText = request.status.charAt(0).toUpperCase() + request.status.slice(1);
            const leaveTypeText = request.leave_type.charAt(0).toUpperCase() + request.leave_type.slice(1).replace('_', ' ');

            const startDate = new Date(request.start_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const endDate = new Date(request.end_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });

            const submittedDate = new Date(request.created_at).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            let detailsHtml = `
                <div class="leave-details">
                    <div class="details-header">
                        <div class="details-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <h5 class="mb-1">${leaveTypeText} Leave</h5>
                            <span class="status-badge ${statusClass}">${statusText}</span>
                        </div>
                    </div>

                    <div class="details-grid">
                        <div class="detail-item">
                            <div class="detail-label">Start Date</div>
                            <div class="detail-value">${startDate}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">End Date</div>
                            <div class="detail-value">${endDate}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Submitted On</div>
                            <div class="detail-value">${submittedDate}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge ${statusClass}">${statusText}</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="detail-label">Reason</div>
                        <div class="detail-value" style="margin-top: 8px;">${request.reason}</div>
                    </div>
            `;

            if (request.reviewer_comments) {
                detailsHtml += `
                    <div class="comments-section">
                        <h6><i class="fas fa-comments me-2"></i>Reviewer Comments</h6>
                        <div class="comments-box">
                            ${request.reviewer_comments}
                        </div>
                    </div>
                `;
            }

            if (request.reviewed_at) {
                const reviewedDate = new Date(request.reviewed_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
                detailsHtml += `
                    <div class="mt-3">
                        <div class="detail-label">Reviewed On</div>
                        <div class="detail-value" style="margin-top: 8px;">${reviewedDate}</div>
                    </div>
                `;
            }

            detailsHtml += `</div>`;

            document.getElementById('leaveDetailsContent').innerHTML = detailsHtml;

            const modal = new bootstrap.Modal(document.getElementById('leaveDetailsModal'));
            modal.show();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
