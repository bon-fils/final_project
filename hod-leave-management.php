<?php
require_once "config.php";
session_start();
require_once "session_check.php";
require_once "includes/hod_auth_helper.php";

// Verify HOD access and get department information
$auth_result = verifyHODAccess($pdo, $_SESSION['user_id']);

if (!$auth_result['success']) {
    // Show error message instead of redirect for better UX
    $error_message = $auth_result['error_message'];
    $department_name = 'No Department Assigned';
    $department_id = null;
    $user = ['name' => $_SESSION['username'] ?? 'User'];
} else {
    $department_name = $auth_result['department_name'];
    $department_id = $auth_result['department_id'];
    $user = $auth_result['user'];
    $lecturer_id = $auth_result['lecturer_id'];
    
    // Set the department variable for backward compatibility
    $department = ['id' => $department_id, 'name' => $department_name];
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle approve/reject actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id'], $_POST['action']) && $department_id) {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request token']);
        exit;
    }
    
    try {
        $leave_id = filter_var($_POST['leave_id'], FILTER_VALIDATE_INT);
        $action = $_POST['action'];
        
        if (!$leave_id || !in_array($action, ['approve', 'reject'])) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid request data']);
            exit;
        }
        
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        // Verify the leave request belongs to this department
        $verify_stmt = $pdo->prepare("
            SELECT lr.id 
            FROM leave_requests lr
            JOIN students s ON lr.student_id = s.id
            WHERE lr.id = ? AND s.department_id = ? AND lr.status = 'pending'
        ");
        $verify_stmt->execute([$leave_id, $department_id]);
        
        if (!$verify_stmt->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Leave request not found or already processed']);
            exit;
        }
        
        // Update the leave request
        $update_stmt = $pdo->prepare("
            UPDATE leave_requests 
            SET status = ?, reviewed_by = ?, reviewed_at = NOW() 
            WHERE id = ?
        ");
        $success = $update_stmt->execute([$status, $lecturer_id, $leave_id]);
        
        if ($success) {
            // Log the action
            error_log("HOD Leave Management: Leave request $leave_id {$status} by lecturer $lecturer_id");
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'status' => $status,
                'message' => "Leave request {$status} successfully"
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update leave request']);
        }
        exit;
    } catch (PDOException $e) {
        error_log("Error updating leave request in hod-leave-management.php: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        exit;
    }
}

// Fetch leave requests for this department
$leaveRequests = [];
$stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];

if ($department_id) {
    try {
        // Get leave requests with proper student information
        $leaveStmt = $pdo->prepare("
            SELECT lr.id, lr.student_id, lr.reason, lr.supporting_file, lr.status,
                   lr.from_date, lr.to_date, lr.requested_at, lr.reviewed_at,
                   u.first_name, u.last_name, s.year_level, d.name as department_name,
                   s.reg_no,
                   reviewer.first_name as reviewer_first_name, 
                   reviewer.last_name as reviewer_last_name
            FROM leave_requests lr
            INNER JOIN students s ON lr.student_id = s.id
            INNER JOIN users u ON s.user_id = u.id
            INNER JOIN departments d ON s.department_id = d.id
            LEFT JOIN lecturers l ON lr.reviewed_by = l.id
            LEFT JOIN users reviewer ON l.user_id = reviewer.id
            WHERE s.department_id = ?
            ORDER BY 
                CASE lr.status 
                    WHEN 'pending' THEN 1 
                    WHEN 'approved' THEN 2 
                    WHEN 'rejected' THEN 3 
                END,
                lr.requested_at DESC
        ");
        $leaveStmt->execute([$department_id]);
        $leaveRequests = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate statistics
        foreach ($leaveRequests as $request) {
            $stats['total']++;
            $stats[$request['status']]++;
        }
        
    } catch (PDOException $e) {
        error_log("Error fetching leave requests in hod-leave-management.php: " . $e->getMessage());
        $error_message = "Unable to load leave requests: " . $e->getMessage();
    }
}

// If AJAX request to refresh table only
if(isset($_GET['ajax']) && $department_id){
    foreach($leaveRequests as $idx => $lr){
        $duration = '';
        if ($lr['from_date'] && $lr['to_date']) {
            $from = new DateTime($lr['from_date']);
            $to = new DateTime($lr['to_date']);
            $diff = $from->diff($to)->days + 1;
            $duration = $diff . ' day' . ($diff > 1 ? 's' : '');
        }
        
        echo '<tr data-id="'.$lr['id'].'" class="leave-request-row status-'.$lr['status'].'">
          <td>'.($idx+1).'</td>
          <td>
            <div class="fw-bold">'.htmlspecialchars($lr['first_name'].' '.$lr['last_name']).'</div>
            <small class="text-muted">'.htmlspecialchars($lr['reg_no'] ?? 'N/A').'</small>
          </td>
          <td>'.htmlspecialchars($lr['year_level']).'</td>
          <td>
            <div>'.htmlspecialchars(date('M j, Y', strtotime($lr['from_date']))).'</div>
            <small class="text-muted">to '.htmlspecialchars(date('M j, Y', strtotime($lr['to_date']))).'</small>
            '.($duration ? '<br><span class="badge bg-info">'.$duration.'</span>' : '').
          '</td>
          <td>
            <div class="reason-text" title="'.htmlspecialchars($lr['reason']).'">
              '.htmlspecialchars(strlen($lr['reason']) > 50 ? substr($lr['reason'], 0, 50).'...' : $lr['reason']).
            '</div>
          </td>
          <td class="text-center">';
          if($lr['supporting_file']){
            echo '<a href="uploads/'.htmlspecialchars($lr['supporting_file']).'" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-file-alt me-1"></i>View
                  </a>';
          } else {
            echo '<span class="text-muted">No file</span>';
          }
          echo '</td>
          <td>
            <span class="badge '.($lr['status']=='approved'?'bg-success':($lr['status']=='rejected'?'bg-danger':'bg-warning text-dark')).'">
              <i class="fas fa-'.($lr['status']=='approved'?'check':($lr['status']=='rejected'?'times':'clock')).' me-1"></i>
              '.ucfirst($lr['status']).
            '</span>';
          if ($lr['status'] !== 'pending' && $lr['reviewed_at']) {
            echo '<br><small class="text-muted">'.date('M j, Y', strtotime($lr['reviewed_at'])).'</small>';
          }
          echo '</td>
          <td class="text-center">';
          if($lr['status']=='pending'){
            echo '<div class="btn-group" role="group">
                    <button class="btn btn-sm btn-approve" data-action="approve" title="Approve Request">
                      <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-reject" data-action="reject" title="Reject Request">
                      <i class="fas fa-times"></i>
                    </button>
                  </div>';
          } else {
            if ($lr['reviewer_first_name']) {
              echo '<small class="text-muted">By: '.htmlspecialchars($lr['reviewer_first_name'].' '.$lr['reviewer_last_name']).'</small>';
            }
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

<!-- Include HOD Sidebar -->
<?php include 'includes/hod_sidebar.php'; ?>

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

    <!-- Authentication Error Display -->
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Access Error:</strong> <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <?php if ($department_id): ?>
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-number text-primary" style="font-size: 2.5rem; font-weight: bold;"><?= $stats['total'] ?></div>
                    <div class="stat-label text-muted">Total Requests</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-number text-warning" style="font-size: 2.5rem; font-weight: bold;"><?= $stats['pending'] ?></div>
                    <div class="stat-label text-muted">Pending Review</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-number text-success" style="font-size: 2.5rem; font-weight: bold;"><?= $stats['approved'] ?></div>
                    <div class="stat-label text-muted">Approved</div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="stat-number text-danger" style="font-size: 2.5rem; font-weight: bold;"><?= $stats['rejected'] ?></div>
                    <div class="stat-label text-muted">Rejected</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="fas fa-envelope-open-text me-2"></i>Leave Requests - <?= htmlspecialchars($department_name) ?></h6>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm" id="statusFilter" style="width: auto;">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshTable()">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
        </div>
        
        <?php if (empty($leaveRequests) && $department_id): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                <h4 class="text-muted">No Leave Requests</h4>
                <p class="text-muted">There are no leave requests for your department yet.</p>
            </div>
        <?php elseif ($department_id): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Student</th>
                        <th style="width: 80px;">Year</th>
                        <th style="width: 180px;">Duration</th>
                        <th>Reason</th>
                        <th style="width: 100px;">File</th>
                        <th style="width: 120px;">Status</th>
                        <th style="width: 120px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="leaveRequestsTableBody">
                    <?php foreach($leaveRequests as $idx => $lr): 
                        $duration = '';
                        if ($lr['from_date'] && $lr['to_date']) {
                            $from = new DateTime($lr['from_date']);
                            $to = new DateTime($lr['to_date']);
                            $diff = $from->diff($to)->days + 1;
                            $duration = $diff . ' day' . ($diff > 1 ? 's' : '');
                        }
                    ?>
                    <tr data-id="<?= $lr['id'] ?>" class="leave-request-row status-<?= $lr['status'] ?>">
                        <td><?= $idx+1 ?></td>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($lr['first_name'].' '.$lr['last_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($lr['reg_no'] ?? 'N/A') ?></small>
                        </td>
                        <td><?= htmlspecialchars($lr['year_level']) ?></td>
                        <td>
                            <div><?= htmlspecialchars(date('M j, Y', strtotime($lr['from_date']))) ?></div>
                            <small class="text-muted">to <?= htmlspecialchars(date('M j, Y', strtotime($lr['to_date']))) ?></small>
                            <?php if ($duration): ?><br><span class="badge bg-info"><?= $duration ?></span><?php endif; ?>
                        </td>
                        <td>
                            <div class="reason-text" title="<?= htmlspecialchars($lr['reason']) ?>">
                                <?= htmlspecialchars(strlen($lr['reason']) > 50 ? substr($lr['reason'], 0, 50).'...' : $lr['reason']) ?>
                            </div>
                        </td>
                        <td class="text-center">
                            <?php if($lr['supporting_file']): ?>
                                <a href="uploads/<?= htmlspecialchars($lr['supporting_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-file-alt me-1"></i>View
                                </a>
                            <?php else: ?>
                                <span class="text-muted">No file</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $lr['status']=='approved'?'bg-success':($lr['status']=='rejected'?'bg-danger':'bg-warning text-dark') ?>">
                                <i class="fas fa-<?= $lr['status']=='approved'?'check':($lr['status']=='rejected'?'times':'clock') ?> me-1"></i>
                                <?= ucfirst($lr['status']) ?>
                            </span>
                            <?php if ($lr['status'] !== 'pending' && $lr['reviewed_at']): ?>
                                <br><small class="text-muted"><?= date('M j, Y', strtotime($lr['reviewed_at'])) ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if($lr['status']=='pending'): ?>
                                <div class="btn-group" role="group">
                                    <button class="btn btn-sm btn-approve" data-action="approve" title="Approve Request">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-reject" data-action="reject" title="Reject Request">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php if ($lr['reviewer_first_name']): ?>
                                    <small class="text-muted">By: <?= htmlspecialchars($lr['reviewer_first_name'].' '.$lr['reviewer_last_name']) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">
    &copy; 2025 Rwanda Polytechnic | Head of Department Panel
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const tableBody = document.getElementById('leaveRequestsTableBody');
const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

// Handle approve/reject actions
function handleAction(e) {
    const btn = e.target.closest('button');
    if (!btn || !btn.hasAttribute('data-action')) return;

    const row = btn.closest('tr');
    const leaveId = row.dataset.id;
    const action = btn.getAttribute('data-action');
    const statusCell = row.querySelector('td:nth-child(7) span');
    const actionsCell = row.querySelector('td:nth-child(8)');
    
    // Get student name for confirmation
    const studentName = row.querySelector('td:nth-child(2) .fw-bold').textContent;
    
    const actionText = action === 'approve' ? 'approve' : 'reject';
    const confirmMessage = `Are you sure you want to ${actionText} the leave request from ${studentName}?`;
    
    if (confirm(confirmMessage)) {
        // Disable buttons to prevent double-click
        const buttons = row.querySelectorAll('button');
        buttons.forEach(b => b.disabled = true);
        
        // Show loading state
        actionsCell.innerHTML = '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>';
        
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `leave_id=${leaveId}&action=${action}&csrf_token=${csrfToken}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Update status badge
                const badgeClass = action === 'approve' ? 'badge bg-success' : 'badge bg-danger';
                const iconClass = action === 'approve' ? 'fas fa-check' : 'fas fa-times';
                statusCell.className = badgeClass;
                statusCell.innerHTML = `<i class="${iconClass} me-1"></i>${data.status.charAt(0).toUpperCase() + data.status.slice(1)}`;
                
                // Update actions cell
                actionsCell.innerHTML = `<small class="text-muted">Just ${action}d</small>`;
                
                // Show success message
                showAlert(`Leave request ${action}d successfully!`, 'success');
                
                // Update statistics
                updateStatistics();
                
            } else {
                // Re-enable buttons on error
                buttons.forEach(b => b.disabled = false);
                actionsCell.innerHTML = `
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-approve" data-action="approve" title="Approve Request">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn btn-sm btn-reject" data-action="reject" title="Reject Request">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                showAlert(data.message || 'Failed to update leave request.', 'error');
            }
        })
        .catch(err => {
            console.error('Error:', err);
            // Re-enable buttons on error
            buttons.forEach(b => b.disabled = false);
            actionsCell.innerHTML = `
                <div class="btn-group" role="group">
                    <button class="btn btn-sm btn-approve" data-action="approve" title="Approve Request">
                        <i class="fas fa-check"></i>
                    </button>
                    <button class="btn btn-sm btn-reject" data-action="reject" title="Reject Request">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            showAlert('Network error occurred. Please try again.', 'error');
        });
    }
}

// Status filtering
document.getElementById('statusFilter')?.addEventListener('change', function() {
    const filterValue = this.value.toLowerCase();
    const rows = tableBody.querySelectorAll('.leave-request-row');
    
    rows.forEach(row => {
        if (!filterValue || row.classList.contains(`status-${filterValue}`)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Update statistics after action
function updateStatistics() {
    const rows = tableBody.querySelectorAll('.leave-request-row');
    const stats = { total: 0, pending: 0, approved: 0, rejected: 0 };
    
    rows.forEach(row => {
        stats.total++;
        if (row.classList.contains('status-pending')) stats.pending++;
        else if (row.classList.contains('status-approved')) stats.approved++;
        else if (row.classList.contains('status-rejected')) stats.rejected++;
    });
    
    // Update stat cards if they exist
    const statCards = document.querySelectorAll('.stat-number');
    if (statCards.length >= 4) {
        statCards[0].textContent = stats.total;
        statCards[1].textContent = stats.pending;
        statCards[2].textContent = stats.approved;
        statCards[3].textContent = stats.rejected;
    }
}

// Show alert messages
function showAlert(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                     type === 'error' ? 'alert-danger' : 'alert-info';
    
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alertDiv);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Auto-refresh functionality
function refreshTable() {
    if (!tableBody) return;
    
    fetch('hod-leave-management.php?ajax=1')
        .then(res => res.text())
        .then(html => {
            if (html.trim()) {
                tableBody.innerHTML = html;
                updateStatistics();
                console.log('Table refreshed at', new Date().toLocaleTimeString());
            }
        })
        .catch(err => {
            console.log('Refresh error:', err);
        });
}

// Event listeners
if (tableBody) {
    tableBody.addEventListener('click', handleAction);
}

// Auto-refresh every 30 seconds
setInterval(refreshTable, 30000);

// Initial setup
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, 5000);
    });
});
</script>

</body>
</html>
