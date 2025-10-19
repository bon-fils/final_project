<?php
/**
 * Admin Leave Management
 * Complete leave management system for administrators
 * Full system-wide overview and management capabilities
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

$user_id = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'admin';

// Handle approve/reject actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_id'], $_POST['action'])) {
    try {
        $leave_id = intval($_POST['leave_id']);
        $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';

        $updStmt = $pdo->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
        $success = $updStmt->execute([$action, $user_id, $leave_id]);

        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'status' => $action]);
        exit;
    } catch (PDOException $e) {
        error_log("Error updating leave request in admin-leave-management.php: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'], $_POST['leave_ids'])) {
    try {
        $leave_ids = json_decode($_POST['leave_ids'], true);
        $action = $_POST['bulk_action'] === 'approve' ? 'approved' : 'rejected';

        if (!empty($leave_ids) && is_array($leave_ids)) {
            $placeholders = str_repeat('?,', count($leave_ids) - 1) . '?';
            $updStmt = $pdo->prepare("UPDATE leave_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id IN ($placeholders)");
            $params = array_merge([$action, $user_id], $leave_ids);
            $success = $updStmt->execute($params);

            header('Content-Type: application/json');
            echo json_encode(['success' => $success, 'affected_rows' => $updStmt->rowCount()]);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error in bulk update: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

// Fetch leave requests with comprehensive data
try {
    $leaveStmt = $pdo->prepare("
        SELECT lr.id, lr.student_id, lr.reason, lr.supporting_file, lr.status,
               lr.from_date, lr.to_date, lr.requested_at, lr.reviewed_at,
               s.first_name, s.last_name, s.year_level, s.reg_no,
               d.name as department_name, d.id as department_id,
               l.id_number as lecturer_name,
               u.username as reviewed_by_name
        FROM leave_requests lr
        INNER JOIN students s ON lr.student_id = s.id
        INNER JOIN departments d ON s.department_id = d.id
        LEFT JOIN lecturers l ON s.option_id = l.option_id AND l.department_id = d.id
        LEFT JOIN users u ON lr.reviewed_by = u.id
        ORDER BY lr.requested_at DESC
    ");
    $leaveStmt->execute();
    $leaveRequests = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching leave requests in admin-leave-management.php: " . $e->getMessage());
    $leaveRequests = [];
}

// Calculate statistics
$stats = [
    'total' => count($leaveRequests),
    'pending' => count(array_filter($leaveRequests, fn($lr) => $lr['status'] === 'pending')),
    'approved' => count(array_filter($leaveRequests, fn($lr) => $lr['status'] === 'approved')),
    'rejected' => count(array_filter($leaveRequests, fn($lr) => $lr['status'] === 'rejected'))
];

// Group by department
$departmentStats = [];
foreach ($leaveRequests as $leave) {
    $dept = $leave['department_name'];
    if (!isset($departmentStats[$dept])) {
        $departmentStats[$dept] = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
    }
    $departmentStats[$dept]['total']++;
    $departmentStats[$dept][$leave['status']]++;
}

// If AJAX request to refresh table only
if(isset($_GET['ajax'])){
    foreach($leaveRequests as $idx => $lr){
        $statusClass = $lr['status']=='approved'?'bg-success':($lr['status']=='rejected'?'bg-danger':'bg-warning text-dark');
        $days = ceil((strtotime($lr['to_date']) - strtotime($lr['from_date'])) / (60*60*24)) + 1;

        echo '<tr data-id="'.$lr['id'].'" data-status="'.$lr['status'].'" data-department="'.$lr['department_id'].'">
          <td><input type="checkbox" class="leave-checkbox" value="'.$lr['id'].'"></td>
          <td>'.($idx+1).'</td>
          <td>'.htmlspecialchars($lr['first_name'].' '.$lr['last_name']).'<br><small class="text-muted">ID: '.htmlspecialchars($lr['reg_no']).'</small></td>
          <td>'.htmlspecialchars($lr['department_name']).'</td>
          <td>'.htmlspecialchars($lr['year_level']).'</td>
          <td>'.htmlspecialchars($lr['from_date']).'</td>
          <td>'.htmlspecialchars($lr['to_date']).'</td>
          <td><span class="badge bg-info">'.$days.' day'.($days!=1?'s':'').'</span></td>
          <td><span title="'.htmlspecialchars($lr['reason']).'">'.htmlspecialchars(substr($lr['reason'],0,50)).(strlen($lr['reason'])>50?'...':'').'</span></td>
          <td><span class="badge '.$statusClass.'">'.ucfirst($lr['status']).'</span></td>
          <td><small class="text-muted">'.date('M d, Y', strtotime($lr['requested_at'])).'</small></td>
          <td>';
          if($lr['supporting_file']){
            echo '<a href="uploads/'.htmlspecialchars($lr['supporting_file']).'" target="_blank" class="btn btn-sm btn-secondary me-1" title="View File"><i class="fas fa-file"></i></a>';
          }
          if($lr['status']=='pending'){
            echo '<button class="btn btn-sm btn-success me-1 approve-btn" title="Approve"><i class="fas fa-check"></i></button>
                  <button class="btn btn-sm btn-danger reject-btn" title="Reject"><i class="fas fa-times"></i></button>';
          } else {
            echo '<button class="btn btn-sm btn-info view-btn" title="View Details"><i class="fas fa-eye"></i></button>';
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
    <title>Admin Leave Management | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #f0f9ff;
            --primary-gradient: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);

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

            --shadow-light: 0 4px 15px rgba(14, 165, 233, 0.08);
            --shadow-medium: 0 8px 25px rgba(14, 165, 233, 0.15);
            --shadow-heavy: 0 12px 35px rgba(14, 165, 233, 0.2);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 50%, #7dd3fc 100%);
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
            background: var(--primary-gradient);
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
            background: var(--primary-gradient);
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
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
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
            background: var(--primary-gradient);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow-light);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .stat-card i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            color: var(--primary-color);
        }

        .stat-card p {
            margin: 10px 0 0 0;
            color: #666;
            font-weight: 500;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1003;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            box-shadow: 0 4px 20px rgba(14, 165, 233, 0.3);
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
            box-shadow: 0 6px 25px rgba(14, 165, 233, 0.4);
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

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .bulk-actions {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .filters-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .department-breakdown {
            margin-top: 30px;
        }

        .department-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: var(--transition);
        }

        .department-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .leave-checkbox {
            transform: scale(1.2);
        }

        .selected-count {
            background: var(--primary-gradient);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Admin Sidebar -->
    <?php include 'includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header text-center py-4" style="background: linear-gradient(135deg, #f8fafc 0%, #0ea5e9 100%); border-bottom: 2px solid #0ea5e9; margin-bottom: 20px;">
            <div class="d-flex align-items-center justify-content-center">
                <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" style="height: 60px; width: auto; margin-right: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(14,165,233,0.1);" onerror="this.style.display='none'">
                <h1 style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin: 0; font-weight: 700;">
                    Admin Leave Management
                </h1>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-clipboard-list"></i>
                <h3 id="totalLeaves"><?= $stats['total'] ?></h3>
                <p>Total Leave Requests</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock" style="color: #f59e0b;"></i>
                <h3 id="pendingLeaves"><?= $stats['pending'] ?></h3>
                <p>Pending Requests</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-check-circle" style="color: #10b981;"></i>
                <h3 id="approvedLeaves"><?= $stats['approved'] ?></h3>
                <p>Approved Requests</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-times-circle" style="color: #ef4444;"></i>
                <h3 id="rejectedLeaves"><?= $stats['rejected'] ?></h3>
                <p>Rejected Requests</p>
            </div>
        </div>

        <!-- Bulk Actions -->
        <div class="bulk-actions">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0 fw-bold">
                        <i class="fas fa-tasks me-2"></i>Bulk Actions
                        <span class="selected-count" id="selectedCount" style="display: none;">0 selected</span>
                    </h6>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" id="bulkApproveBtn" disabled>
                        <i class="fas fa-check me-1"></i>Approve Selected
                    </button>
                    <button class="btn btn-danger btn-sm" id="bulkRejectBtn" disabled>
                        <i class="fas fa-times me-1"></i>Reject Selected
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="selectAllBtn">
                        <i class="fas fa-check-square me-1"></i>Select All
                    </button>
                    <button class="btn btn-outline-primary btn-sm" onclick="refreshTable()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="color: var(--primary-color);">Status Filter</label>
                    <select id="statusFilter" class="form-select" onchange="filterTable()" style="color: var(--primary-color);">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="color: var(--primary-color);">Department Filter</label>
                    <select id="departmentFilter" class="form-select" onchange="filterTable()" style="color: var(--primary-color);">
                        <option value="all">All Departments</option>
                        <?php
                        $uniqueDepts = array_unique(array_column($leaveRequests, 'department_name'));
                        foreach ($uniqueDepts as $dept) {
                            echo "<option value=\"$dept\">$dept</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="color: var(--primary-color);">Search</label>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by name or reason..." onkeyup="filterTable()" style="color: var(--primary-color);">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold" style="color: var(--primary-color);">Date Range</label>
                    <select id="dateFilter" class="form-select" onchange="filterTable()" style="color: var(--primary-color);">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Leave Requests Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i>Leave Requests Overview
                </h5>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-info btn-sm" onclick="exportToCSV()">
                        <i class="fas fa-file-csv me-1"></i>Export CSV
                    </button>
                    <button class="btn btn-outline-success btn-sm" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-1"></i>Export Excel
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="leaveTable">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="selectAllCheckbox"></th>
                                <th>#</th>
                                <th>Student Details</th>
                                <th>Department</th>
                                <th>Class</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Days</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Requested</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="leaveTableBody">
                            <?php foreach($leaveRequests as $idx => $lr):
                                $statusClass = $lr['status']=='approved'?'bg-success':($lr['status']=='rejected'?'bg-danger':'bg-warning text-dark');
                                $days = ceil((strtotime($lr['to_date']) - strtotime($lr['from_date'])) / (60*60*24)) + 1;
                            ?>
                            <tr data-id="<?= $lr['id'] ?>" data-status="<?= $lr['status'] ?>" data-department="<?= $lr['department_id'] ?>">
                                <td><input type="checkbox" class="leave-checkbox" value="<?= $lr['id'] ?>"></td>
                                <td><?= $idx+1 ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($lr['first_name'].' '.$lr['last_name']) ?></strong>
                                    <br><small class="text-muted">ID: <?= htmlspecialchars($lr['reg_no']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($lr['department_name']) ?></td>
                                <td><?= htmlspecialchars($lr['year_level']) ?></td>
                                <td><?= htmlspecialchars($lr['from_date']) ?></td>
                                <td><?= htmlspecialchars($lr['to_date']) ?></td>
                                <td><span class="badge bg-info"><?= $days ?> day<?= $days != 1 ? 's' : '' ?></span></td>
                                <td>
                                    <span title="<?= htmlspecialchars($lr['reason']) ?>">
                                        <?= htmlspecialchars(substr($lr['reason'], 0, 50)) ?><?= strlen($lr['reason']) > 50 ? '...' : '' ?>
                                    </span>
                                </td>
                                <td><span class="badge <?= $statusClass ?>"><?= ucfirst($lr['status']) ?></span></td>
                                <td><small class="text-muted"><?= date('M d, Y', strtotime($lr['requested_at'])) ?></small></td>
                                <td>
                                    <?php if($lr['supporting_file']): ?>
                                        <a href="uploads/<?= htmlspecialchars($lr['supporting_file']) ?>" target="_blank" class="btn btn-sm btn-secondary me-1" title="View File">
                                            <i class="fas fa-file"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if($lr['status']=='pending'): ?>
                                        <button class="btn btn-sm btn-success me-1 approve-btn" title="Approve">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger reject-btn" title="Reject">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-info view-btn" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Department Breakdown -->
        <div class="department-breakdown">
            <h5 class="mb-3">
                <i class="fas fa-building me-2"></i>Department-wise Breakdown
            </h5>
            <div class="row">
                <?php foreach ($departmentStats as $deptName => $deptStats): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="department-card">
                        <h6 class="fw-bold mb-3">
                            <i class="fas fa-building me-2"></i><?= htmlspecialchars($deptName) ?>
                        </h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="text-warning fw-bold fs-5"><?= $deptStats['pending'] ?></div>
                                <small class="text-muted">Pending</small>
                            </div>
                            <div class="col-6">
                                <div class="text-success fw-bold fs-5"><?= $deptStats['approved'] ?></div>
                                <small class="text-muted">Approved</small>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between text-sm">
                            <span>Total: <strong><?= $deptStats['total'] ?></strong></span>
                            <span>Rejected: <strong class="text-danger"><?= $deptStats['rejected'] ?></strong></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Admin Panel - Complete Leave Management System
    </div>

    <!-- Leave Details Modal -->
    <div class="modal fade" id="leaveDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-signature me-2"></i>Leave Request Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="leaveDetailContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let allLeaveRequests = <?= json_encode($leaveRequests) ?>;
        let filteredRequests = [...allLeaveRequests];

        // Sidebar toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
        }

        // Handle individual approve/reject actions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('approve-btn') || e.target.closest('.approve-btn')) {
                const btn = e.target.closest('.approve-btn');
                const row = btn.closest('tr');
                const leaveId = row.dataset.id;
                handleAction(leaveId, 'approve');
            }

            if (e.target.classList.contains('reject-btn') || e.target.closest('.reject-btn')) {
                const btn = e.target.closest('.reject-btn');
                const leaveId = btn.dataset.id || btn.closest('tr').dataset.id;
                handleAction(leaveId, 'reject');
            }

            if (e.target.classList.contains('view-btn') || e.target.closest('.view-btn')) {
                const btn = e.target.closest('.view-btn');
                const row = btn.closest('tr');
                const leaveId = row.dataset.id;
                viewLeaveDetails(leaveId);
            }
        });

        // Handle individual actions
        async function handleAction(leaveId, action) {
            if (!confirm(`Are you sure you want to ${action} this leave request?`)) return;

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `leave_id=${leaveId}&action=${action}`
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(`${action.charAt(0).toUpperCase() + action.slice(1)} successfully!`, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('Failed to update leave request', 'danger');
                }
            } catch (error) {
                showAlert('Error updating leave request', 'danger');
            }
        }

        // Bulk actions
        document.getElementById('selectAllCheckbox').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.leave-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });

        document.getElementById('selectAllBtn').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('.leave-checkbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);

            checkboxes.forEach(cb => cb.checked = !allChecked);
            document.getElementById('selectAllCheckbox').checked = !allChecked;
            updateBulkActions();
        });

        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('leave-checkbox')) {
                updateBulkActions();
            }
        });

        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.leave-checkbox:checked');
            const count = checkedBoxes.length;

            document.getElementById('selectedCount').textContent = count + ' selected';
            document.getElementById('selectedCount').style.display = count > 0 ? 'inline' : 'none';

            document.getElementById('bulkApproveBtn').disabled = count === 0;
            document.getElementById('bulkRejectBtn').disabled = count === 0;
        }

        document.getElementById('bulkApproveBtn').addEventListener('click', () => performBulkAction('approve'));
        document.getElementById('bulkRejectBtn').addEventListener('click', () => performBulkAction('reject'));

        async function performBulkAction(action) {
            const checkedBoxes = document.querySelectorAll('.leave-checkbox:checked');
            if (checkedBoxes.length === 0) return;

            const leaveIds = Array.from(checkedBoxes).map(cb => cb.value);

            if (!confirm(`Are you sure you want to ${action} ${leaveIds.length} leave request(s)?`)) return;

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `bulk_action=${action}&leave_ids=${JSON.stringify(leaveIds)}`
                });

                const result = await response.json();

                if (result.success) {
                    showAlert(`${action.charAt(0).toUpperCase() + action.slice(1)} ${result.affected_rows} leave request(s) successfully!`, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('Failed to update leave requests', 'danger');
                }
            } catch (error) {
                showAlert('Error updating leave requests', 'danger');
            }
        }

        // Filtering
        function filterTable() {
            const statusFilter = document.getElementById('statusFilter').value;
            const departmentFilter = document.getElementById('departmentFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const dateFilter = document.getElementById('dateFilter').value;

            filteredRequests = allLeaveRequests.filter(leave => {
                // Status filter
                if (statusFilter !== 'all' && leave.status !== statusFilter) return false;

                // Department filter
                if (departmentFilter !== 'all' && leave.department_name !== departmentFilter) return false;

                // Search filter
                if (searchTerm) {
                    const fullName = `${leave.first_name} ${leave.last_name}`.toLowerCase();
                    if (!fullName.includes(searchTerm) && !leave.reason.toLowerCase().includes(searchTerm)) return false;
                }

                // Date filter
                if (dateFilter !== 'all') {
                    const requestedDate = new Date(leave.requested_at);
                    const now = new Date();

                    switch (dateFilter) {
                        case 'today':
                            if (requestedDate.toDateString() !== now.toDateString()) return false;
                            break;
                        case 'week':
                            const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
                            if (requestedDate < weekAgo) return false;
                            break;
                        case 'month':
                            const monthAgo = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000);
                            if (requestedDate < monthAgo) return false;
                            break;
                        case 'year':
                            const yearAgo = new Date(now.getTime() - 365 * 24 * 60 * 60 * 1000);
                            if (requestedDate < yearAgo) return false;
                            break;
                    }
                }

                return true;
            });

            renderFilteredTable();
        }

        function renderFilteredTable() {
            const tbody = document.getElementById('leaveTableBody');

            if (filteredRequests.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="12" class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Leave Requests Found</h5>
                            <p class="text-muted">No requests match your current filters.</p>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            filteredRequests.forEach((leave, idx) => {
                const statusClass = leave.status === 'approved' ? 'bg-success' :
                                  leave.status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark';
                const days = Math.ceil((new Date(leave.to_date) - new Date(leave.from_date)) / (1000 * 60 * 60 * 24)) + 1;

                html += `
                    <tr data-id="${leave.id}" data-status="${leave.status}" data-department="${leave.department_id}">
                        <td><input type="checkbox" class="leave-checkbox" value="${leave.id}"></td>
                        <td>${idx + 1}</td>
                        <td>
                            <strong>${leave.first_name} ${leave.last_name}</strong>
                            <br><small class="text-muted">ID: ${leave.reg_no}</small>
                        </td>
                        <td>${leave.department_name}</td>
                        <td>${leave.year_level}</td>
                        <td>${leave.from_date}</td>
                        <td>${leave.to_date}</td>
                        <td><span class="badge bg-info">${days} day${days !== 1 ? 's' : ''}</span></td>
                        <td>
                            <span title="${leave.reason}">${leave.reason.length > 50 ? leave.reason.substring(0, 50) + '...' : leave.reason}</span>
                        </td>
                        <td><span class="badge ${statusClass}">${leave.status.charAt(0).toUpperCase() + leave.status.slice(1)}</span></td>
                        <td><small class="text-muted">${new Date(leave.requested_at).toLocaleDateString()}</small></td>
                        <td>
                            ${leave.supporting_file ? `<a href="uploads/${leave.supporting_file}" target="_blank" class="btn btn-sm btn-secondary me-1" title="View File"><i class="fas fa-file"></i></a>` : ''}
                            ${leave.status === 'pending' ?
                                `<button class="btn btn-sm btn-success me-1 approve-btn" title="Approve"><i class="fas fa-check"></i></button>
                                 <button class="btn btn-sm btn-danger reject-btn" title="Reject"><i class="fas fa-times"></i></button>` :
                                `<button class="btn btn-sm btn-info view-btn" title="View Details"><i class="fas fa-eye"></i></button>`
                            }
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            updateBulkActions();
        }

        // View leave details
        function viewLeaveDetails(leaveId) {
            const leave = allLeaveRequests.find(l => l.id == leaveId);
            if (!leave) return;

            const days = Math.ceil((new Date(leave.to_date) - new Date(leave.from_date)) / (1000 * 60 * 60 * 24)) + 1;

            const html = `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-user me-2"></i>Student Information</h6>
                        <p><strong>Name:</strong> ${leave.first_name} ${leave.last_name}</p>
                        <p><strong>Registration No:</strong> ${leave.reg_no}</p>
                        <p><strong>Department:</strong> ${leave.department_name}</p>
                        <p><strong>Class:</strong> ${leave.year_level}</p>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-calendar me-2"></i>Leave Information</h6>
                        <p><strong>From:</strong> ${new Date(leave.from_date).toLocaleDateString()}</p>
                        <p><strong>To:</strong> ${new Date(leave.to_date).toLocaleDateString()}</p>
                        <p><strong>Days:</strong> ${days}</p>
                        <p><strong>Status:</strong> <span class="badge ${leave.status === 'approved' ? 'bg-success' : leave.status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'}">${leave.status.charAt(0).toUpperCase() + leave.status.slice(1)}</span></p>
                   