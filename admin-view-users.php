<?php
/**
 * Admin View Users - Enhanced Version
 * Allows admin to view lists of Lecturers, HODs, and Students with improved UI and functionality
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Initialize CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Additional security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

/**
 * Fetch users by role with proper database schema and enhanced error handling
 */
function fetchUsers($role) {
    global $pdo;

    try {
        // Validate role parameter
        $validRoles = ['student', 'lecturer', 'hod'];
        if (!in_array($role, $validRoles)) {
            error_log("Invalid role parameter: " . $role);
            return [];
        }

        if ($role === 'student') {
            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.status,
                    u.created_at,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    s.reg_no as reference_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as full_name,
                    d.name as department_name,
                    s.year_level
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN options o ON s.option_id = o.id
                LEFT JOIN departments d ON o.department_id = d.id
                WHERE u.role = 'student'
                ORDER BY u.created_at DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.status,
                    u.created_at,
                    u.first_name,
                    u.last_name,
                    u.phone,
                    l.id_number as reference_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as full_name,
                    d.name as department_name,
                    u.role as user_role
                FROM users u
                LEFT JOIN lecturers l ON u.id = l.user_id
                LEFT JOIN departments d ON l.department_id = d.id
                WHERE u.role = ?
                ORDER BY u.created_at DESC
            ");
            $stmt->execute([$role]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Sanitize data
        foreach ($results as &$row) {
            $row['full_name'] = trim($row['full_name']);
            if (empty($row['full_name'])) {
                $row['full_name'] = $row['username'] ?? 'Unknown User';
            }
        }

        return $results;

    } catch (PDOException $e) {
        error_log("Database error fetching users for role '$role': " . $e->getMessage());
        return [];
    } catch (Exception $e) {
        error_log("General error fetching users for role '$role': " . $e->getMessage());
        return [];
    }
}

/**
 * Get user statistics with enhanced error handling
 */
function getUserStats() {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT role, COUNT(*) as count, status
            FROM users
            WHERE role IN ('student', 'lecturer', 'hod')
            GROUP BY role, status
        ");
        $stmt->execute();
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [
            'student' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0],
            'lecturer' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0],
            'hod' => ['total' => 0, 'active' => 0, 'inactive' => 0, 'suspended' => 0]
        ];

        foreach ($stats as $stat) {
            $role = $stat['role'];
            $status = $stat['status'] ?? 'active';
            $count = (int)$stat['count'];

            if (isset($result[$role])) {
                $result[$role]['total'] += $count;
                if (isset($result[$role][$status])) {
                    $result[$role][$status] = $count;
                }
            }
        }

        // Return simplified format for backward compatibility
        return [
            'student' => $result['student']['total'],
            'lecturer' => $result['lecturer']['total'],
            'hod' => $result['hod']['total']
        ];

    } catch (PDOException $e) {
        error_log("Database error getting user stats: " . $e->getMessage());
        return ['student' => 0, 'lecturer' => 0, 'hod' => 0];
    } catch (Exception $e) {
        error_log("General error getting user stats: " . $e->getMessage());
        return ['student' => 0, 'lecturer' => 0, 'hod' => 0];
    }
}

$lecturers = fetchUsers('lecturer');
$hods = fetchUsers('hod');
$students = fetchUsers('student');
$userStats = getUserStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Users | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            background: linear-gradient(to right, #0066cc, #003366);
            min-height: 100vh;
            font-family: 'Segoe UI', 'Roboto', sans-serif;
            margin: 0;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
            border-right: 1px solid rgba(0, 102, 204, 0.1);
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(0, 102, 204, 0.1);
        }

        .main-content {
            margin-left: 280px;
            padding: 20px 30px;
            min-height: 100vh;
        }

        .card {
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: none;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(5px);
            margin-bottom: 20px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .card-header {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            border: none;
            font-weight: 600;
            padding: 20px;
        }

        .stats-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.7) 100%);
            border: 1px solid rgba(0,102,204,0.1);
        }

        .stats-card .card-body {
            padding: 25px;
            text-align: center;
        }

        .stats-card .display-4 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #0066cc;
            margin-bottom: 10px;
        }

        .stats-card .text-muted {
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-tabs {
            border: none;
            background: rgba(255,255,255,0.8);
            border-radius: var(--border-radius);
            padding: 10px;
            margin-bottom: 20px;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #0066cc;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
            margin-right: 5px;
            transition: var(--transition);
        }

        .nav-tabs .nav-link.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(0,102,204,0.3);
        }

        .nav-tabs .nav-link:hover:not(.active) {
            background: rgba(0,102,204,0.1);
            color: #004080;
        }

        .table {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--primary-gradient);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .table tbody tr:hover {
            background: rgba(0,102,204,0.05);
        }

        .status-badge {
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .status-active { background-color: #28a745; color: white; }
        .status-inactive { background-color: #dc3545; color: white; }
        .status-suspended { background-color: #ffc107; color: black; }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 8px 16px;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            box-shadow: 0 4px 15px rgba(0, 102, 204, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-gradient);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 102, 204, 0.4);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
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

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1003;
            background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px;
            box-shadow: 0 4px 20px rgba(0, 102, 204, 0.3);
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
            box-shadow: 0 6px 25px rgba(0, 102, 204, 0.4);
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

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .mobile-menu-toggle {
                display: block !important;
            }

            .stats-card .display-4 {
                font-size: 2rem;
            }

            .table-responsive {
                font-size: 0.9rem;
            }
        }

        /* Enhanced search and filter styling */
        .input-group-text {
            background: var(--primary-gradient);
            color: white;
            border: none;
        }

        .form-select:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.15);
        }

        /* Action buttons styling */
        .btn-group .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .btn-outline-primary:hover { background-color: #0066cc; border-color: #0066cc; }
        .btn-outline-success:hover { background-color: #28a745; border-color: #28a745; }
        .btn-outline-info:hover { background-color: #17a2b8; border-color: #17a2b8; }
        .btn-outline-warning:hover { background-color: #ffc107; border-color: #ffc107; color: black; }
        .btn-outline-danger:hover { background-color: #dc3545; border-color: #dc3545; }

        /* Enhanced mobile responsiveness for action buttons */
        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .btn-group .btn {
                width: 100%;
                margin-bottom: 2px;
            }

            .table-responsive {
                font-size: 0.85rem;
            }

            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 576px) {
            .stats-card .display-4 {
                font-size: 1.8rem;
            }

            .card-header {
                padding: 15px;
                font-size: 1rem;
            }

            .table th, .table td {
                padding: 8px 4px;
            }
        }

        /* Loading overlay styles */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        /* Enhanced button hover effects */
        .btn-group .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        /* Better focus states for accessibility */
        .btn:focus {
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
            outline: none;
        }

        /* Improved table responsiveness */
        @media (max-width: 768px) {
            .table-responsive {
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .btn-group .btn {
                width: 100%;
                margin-bottom: 2px;
                border-radius: 4px !important;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <?php include 'admin_sidebar.php'; ?>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay d-none" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-2">Loading user data...</div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h2 class="mb-1 text-white">
                        <i class="fas fa-users me-3"></i>User Directory
                    </h2>
                    <p class="text-white-50 mb-0">View and manage all system users</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="manage-users.php" class="btn btn-light">
                        <i class="fas fa-cog me-1"></i>Manage Users
                    </a>
                    <a href="admin-dashboard.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-1"></i>Dashboard
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-xl-4 col-lg-4 col-md-4">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-user-graduate text-primary mb-3" style="font-size: 2.5rem;"></i>
                            <div class="display-4"><?php echo $userStats['student']; ?></div>
                            <p class="text-muted mb-0">Students</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-4">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-chalkboard-teacher text-success mb-3" style="font-size: 2.5rem;"></i>
                            <div class="display-4"><?php echo $userStats['lecturer']; ?></div>
                            <p class="text-muted mb-0">Lecturers</p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-4">
                    <div class="card stats-card text-center">
                        <div class="card-body">
                            <i class="fas fa-user-tie text-warning mb-3" style="font-size: 2.5rem;"></i>
                            <div class="display-4"><?php echo $userStats['hod']; ?></div>
                            <p class="text-muted mb-0">HODs</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Search and Filter Bar -->
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="searchInput" class="form-label fw-semibold">
                                <i class="fas fa-search me-1 text-primary"></i>Search Users
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light" aria-hidden="true">
                                    <i class="fas fa-search text-muted"></i>
                                </span>
                                <input type="text" class="form-control" id="searchInput"
                                       placeholder="Search by name, email, or ID..."
                                       aria-describedby="searchHelp"
                                       autocomplete="off">
                            </div>
                            <div id="searchHelp" class="form-text text-muted small">
                                <i class="fas fa-info-circle me-1"></i>Search across names, emails, and IDs
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="statusFilter" class="form-label fw-semibold">
                                <i class="fas fa-filter me-1 text-success"></i>Filter by Status
                            </label>
                            <select class="form-select" id="statusFilter" aria-describedby="statusHelp">
                                <option value="">All Statuses</option>
                                <option value="active">🟢 Active Users</option>
                                <option value="inactive">🔴 Inactive Users</option>
                                <option value="suspended">🟡 Suspended Users</option>
                            </select>
                            <div id="statusHelp" class="form-text text-muted small">
                                <i class="fas fa-user-check me-1"></i>Filter users by account status
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="departmentFilter" class="form-label fw-semibold">
                                <i class="fas fa-building me-1 text-info"></i>Filter by Department
                            </label>
                            <select class="form-select" id="departmentFilter" aria-describedby="departmentHelp">
                                <option value="">All Departments</option>
                                <?php
                                try {
                                    $deptStmt = $pdo->query("SELECT id, name FROM departments WHERE status = 'active' ORDER BY name");
                                    $deptCount = 0;
                                    while ($dept = $deptStmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<option value='{$dept['id']}'>🏫 {$dept['name']}</option>";
                                        $deptCount++;
                                    }
                                    if ($deptCount === 0) {
                                        echo "<option value='' disabled>No departments available</option>";
                                    }
                                } catch (Exception $e) {
                                    echo "<option value='' disabled>Error loading departments</option>";
                                }
                                ?>
                            </select>
                            <div id="departmentHelp" class="form-text text-muted small">
                                <i class="fas fa-university me-1"></i>Filter users by their department
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold invisible">Actions</label>
                            <button class="btn btn-outline-primary w-100" onclick="exportUsers()"
                                    aria-describedby="exportHelp">
                                <i class="fas fa-download me-1"></i>Export Data
                            </button>
                            <div id="exportHelp" class="form-text text-muted small">
                                <i class="fas fa-file-export me-1"></i>Download user data
                            </div>
                        </div>
                    </div>

                    <!-- Active Filters Display -->
                    <div id="activeFilters" class="mt-3 d-none">
                        <div class="d-flex align-items-center">
                            <small class="text-muted me-2">
                                <i class="fas fa-filter me-1"></i>Active filters:
                            </small>
                            <div id="filterTags" class="d-flex gap-2 flex-wrap"></div>
                            <button class="btn btn-sm btn-outline-secondary ms-2" onclick="clearAllFilters()">
                                <i class="fas fa-times me-1"></i>Clear All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Type Tabs -->
            <ul class="nav nav-tabs" id="userTabs" role="tablist" aria-label="User type selection">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="lecturers-tab" data-bs-toggle="tab" data-bs-target="#lecturers" type="button" role="tab" aria-controls="lecturers" aria-selected="true">
                        <i class="fas fa-chalkboard-teacher me-2" aria-hidden="true"></i>Lecturers
                        <span class="badge bg-white text-primary ms-2" aria-label="<?php echo count($lecturers); ?> lecturers"><?php echo count($lecturers); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="hods-tab" data-bs-toggle="tab" data-bs-target="#hods" type="button" role="tab" aria-controls="hods" aria-selected="false">
                        <i class="fas fa-user-tie me-2" aria-hidden="true"></i>HODs
                        <span class="badge bg-white text-success ms-2" aria-label="<?php echo count($hods); ?> heads of department"><?php echo count($hods); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" aria-controls="students" aria-selected="false">
                        <i class="fas fa-user-graduate me-2" aria-hidden="true"></i>Students
                        <span class="badge bg-white text-info ms-2" aria-label="<?php echo count($students); ?> students"><?php echo count($students); ?></span>
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="userTabsContent">
                <!-- Lecturers Tab -->
                <div class="tab-pane fade show active" id="lecturers" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-chalkboard-teacher me-2"></i>Lecturers Directory
                            <span class="badge bg-light text-primary ms-2"><?php echo count($lecturers); ?> Total</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($lecturers)): ?>
                            <div class="table-responsive">
                                <table class="table mb-0" role="table" aria-label="Lecturers directory">
                                    <thead>
                                        <tr>
                                            <th scope="col" aria-label="Row number">#</th>
                                            <th scope="col">User</th>
                                            <th scope="col">Staff ID</th>
                                            <th scope="col">Contact</th>
                                            <th scope="col">Department</th>
                                            <th scope="col">Status</th>
                                            <th scope="col">Joined</th>
                                            <th scope="col">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($lecturers as $i => $row): ?>
                                        <tr role="row">
                                            <td role="gridcell" aria-label="Row <?php echo $i+1; ?>"><?php echo $i+1; ?></td>
                                            <td role="gridcell">
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3" aria-hidden="true">
                                                        <?php echo strtoupper(substr($row['full_name'] ?? $row['username'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></h6>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td role="gridcell">
                                                <code class="text-primary" aria-label="Staff ID: <?php echo htmlspecialchars($row['reference_id'] ?? 'N/A'); ?>">
                                                    <?php echo htmlspecialchars($row['reference_id'] ?? 'N/A'); ?>
                                                </code>
                                            </td>
                                            <td role="gridcell">
                                                <div>
                                                    <i class="fas fa-envelope text-muted me-1" aria-hidden="true"></i>
                                                    <span aria-label="Email: <?php echo htmlspecialchars($row['email']); ?>">
                                                        <?php echo htmlspecialchars($row['email']); ?>
                                                    </span>
                                                    <?php if (!empty($row['phone'])): ?>
                                                    <br><i class="fas fa-phone text-muted me-1" aria-hidden="true"></i>
                                                    <span aria-label="Phone: <?php echo htmlspecialchars($row['phone']); ?>">
                                                        <?php echo htmlspecialchars($row['phone']); ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td role="gridcell" aria-label="Department: <?php echo htmlspecialchars($row['department_name'] ?? 'Not Assigned'); ?>">
                                                <?php echo htmlspecialchars($row['department_name'] ?? 'Not Assigned'); ?>
                                            </td>
                                            <td role="gridcell">
                                                <span class="status-badge status-<?php echo $row['status'] ?? 'active'; ?>"
                                                      aria-label="Status: <?php echo ucfirst($row['status'] ?? 'active'); ?>">
                                                    <?php echo ucfirst($row['status'] ?? 'active'); ?>
                                                </span>
                                            </td>
                                            <td role="gridcell" aria-label="Joined: <?php echo date('M d, Y', strtotime($row['created_at'])); ?>">
                                                <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                            </td>
                                            <td role="gridcell">
                                                <div class="btn-group" role="group" aria-label="Actions for <?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?>">
                                                    <button class="btn btn-sm btn-outline-primary"
                                                            onclick="viewUserDetails(<?php echo $row['id']; ?>, 'lecturer')"
                                                            title="View Details"
                                                            aria-label="View details for <?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?>">
                                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning"
                                                            onclick="editUser(<?php echo $row['id']; ?>, 'lecturer')"
                                                            title="Edit User"
                                                            aria-label="Edit <?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?>">
                                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                            onclick="toggleUserStatus(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')"
                                                            title="Toggle Status"
                                                            aria-label="<?php echo $row['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> <?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?>">
                                                        <i class="fas fa-<?php echo $row['status'] === 'active' ? 'ban' : 'check'; ?>" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h5>No Lecturers Found</h5>
                                <p>No lecturers have been registered in the system yet.</p>
                                <a href="manage-users.php?role=lecturer" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Register First Lecturer
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- HODs Tab -->
                <div class="tab-pane fade" id="hods" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-tie me-2"></i>Heads of Department
                            <span class="badge bg-light text-success ms-2"><?php echo count($hods); ?> Total</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($hods)): ?>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>User</th>
                                            <th>Staff ID</th>
                                            <th>Contact</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($hods as $i => $row): ?>
                                        <tr>
                                            <td><?php echo $i+1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($row['full_name'] ?? $row['username'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></h6>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="text-success"><?php echo htmlspecialchars($row['reference_id'] ?? 'N/A'); ?></code>
                                            </td>
                                            <td>
                                                <div>
                                                    <i class="fas fa-envelope text-muted me-1"></i><?php echo htmlspecialchars($row['email']); ?>
                                                    <?php if (!empty($row['phone'])): ?>
                                                    <br><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($row['phone']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['department_name'] ?? 'Not Assigned'); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $row['status'] ?? 'active'; ?>">
                                                    <?php echo ucfirst($row['status'] ?? 'active'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-success" onclick="viewUserDetails(<?php echo $row['id']; ?>, 'hod')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editUser(<?php echo $row['id']; ?>, 'hod')" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="toggleUserStatus(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')" title="Toggle Status">
                                                        <i class="fas fa-<?php echo $row['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-tie"></i>
                                <h5>No HODs Found</h5>
                                <p>No Heads of Department have been assigned yet.</p>
                                <a href="assign-hod.php" class="btn btn-success">
                                    <i class="fas fa-user-plus me-1"></i>Assign HOD
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Students Tab -->
                <div class="tab-pane fade" id="students" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-user-graduate me-2"></i>Students Directory
                            <span class="badge bg-light text-info ms-2"><?php echo count($students); ?> Total</span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($students)): ?>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>User</th>
                                            <th>Reg Number</th>
                                            <th>Contact</th>
                                            <th>Department</th>
                                            <th>Year</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($students as $i => $row): ?>
                                        <tr>
                                            <td><?php echo $i+1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($row['full_name'] ?? $row['username'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['full_name'] ?? $row['username']); ?></h6>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="text-info"><?php echo htmlspecialchars($row['reference_id'] ?? 'N/A'); ?></code>
                                            </td>
                                            <td>
                                                <div>
                                                    <i class="fas fa-envelope text-muted me-1"></i><?php echo htmlspecialchars($row['email']); ?>
                                                    <?php if (!empty($row['phone'])): ?>
                                                    <br><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($row['phone']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['department_name'] ?? 'Not Assigned'); ?></td>
                                            <td>
                                                <?php if (!empty($row['year_level'])): ?>
                                                <span class="badge bg-primary">Year <?php echo $row['year_level']; ?></span>
                                                <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $row['status'] ?? 'active'; ?>">
                                                    <?php echo ucfirst($row['status'] ?? 'active'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-info" onclick="viewUserDetails(<?php echo $row['id']; ?>, 'student')" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editUser(<?php echo $row['id']; ?>, 'student')" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="toggleUserStatus(<?php echo $row['id']; ?>, '<?php echo $row['status']; ?>')" title="Toggle Status">
                                                        <i class="fas fa-<?php echo $row['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-graduate"></i>
                                <h5>No Students Found</h5>
                                <p>No students have been registered in the system yet.</p>
                                <a href="register-student.php" class="btn btn-info">
                                    <i class="fas fa-plus me-1"></i>Register First Student
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggleBtn.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Handle window resize
        window.addEventListener('resize', function() {
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth > 768) {
                sidebar.classList.remove('show');
            }
        });

        // Search and filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const departmentFilter = document.getElementById('departmentFilter');

            // Add event listeners for search and filters
            searchInput.addEventListener('input', filterUsers);
            statusFilter.addEventListener('change', filterUsers);
            departmentFilter.addEventListener('change', filterUsers);

            // Add fade-in animation to cards
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add click animation to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });

        // Enhanced filter users based on search and filters
        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            const statusFilter = document.getElementById('statusFilter').value;
            const departmentFilter = document.getElementById('departmentFilter').value;

            const tables = document.querySelectorAll('tbody');
            let totalVisible = 0;

            tables.forEach(table => {
                const rows = table.querySelectorAll('tr');
                let tableVisible = 0;

                rows.forEach(row => {
                    const cells = row.querySelectorAll('td');
                    if (cells.length < 5) return; // Skip if not enough cells

                    let showRow = true;

                    // Search filter - search in name, email, and reference ID
                    if (searchTerm) {
                        const searchableText = [
                            cells[1]?.textContent || '', // User info cell
                            cells[2]?.textContent || '', // ID/Reference cell
                            cells[3]?.textContent || '', // Contact cell
                        ].join(' ').toLowerCase();

                        if (!searchableText.includes(searchTerm)) {
                            showRow = false;
                        }
                    }

                    // Status filter
                    if (statusFilter && showRow) {
                        const statusBadge = row.querySelector('.status-badge');
                        if (statusBadge) {
                            const status = statusBadge.textContent.toLowerCase().trim();
                            if (status !== statusFilter) {
                                showRow = false;
                            }
                        }
                    }

                    // Department filter
                    if (departmentFilter && showRow) {
                        const departmentCell = cells[4]; // Department column
                        if (departmentCell) {
                            const departmentText = departmentCell.textContent.trim();
                            // Check if department matches the selected department ID
                            const departmentOptions = document.querySelectorAll('#departmentFilter option');
                            let selectedDeptName = '';
                            departmentOptions.forEach(option => {
                                if (option.value === departmentFilter) {
                                    selectedDeptName = option.textContent.toLowerCase();
                                }
                            });

                            if (selectedDeptName && !departmentText.toLowerCase().includes(selectedDeptName)) {
                                showRow = false;
                            }
                        }
                    }

                    row.style.display = showRow ? '' : 'none';
                    if (showRow) {
                        tableVisible++;
                        totalVisible++;
                    }
                });

                // Update tab badge if this is a tab content
                const tabContent = table.closest('.tab-pane');
                if (tabContent) {
                    const tabId = tabContent.id;
                    const tabButton = document.querySelector(`[data-bs-target="#${tabId}"] .badge`);
                    if (tabButton) {
                        tabButton.textContent = tableVisible;
                    }
                }
            });

            // Update total stats if needed
            updateFilteredStats(totalVisible);
        }

        // Update filtered statistics display
        function updateFilteredStats(visibleCount) {
            // Could add additional UI feedback here
            console.log(`Showing ${visibleCount} filtered results`);
        }

        // User action functions
        function viewUserDetails(userId, role) {
            // Open user details modal or redirect to user profile
            window.location.href = `user-details.php?id=${userId}&role=${role}`;
        }

        function editUser(userId, role) {
            // Redirect to edit user page
            window.location.href = `edit-user.php?id=${userId}&role=${role}`;
        }

        async function toggleUserStatus(userId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activate' : 'deactivate';

            if (!confirm(`Are you sure you want to ${action} this user?\n\nThis will ${newStatus === 'inactive' ? 'prevent them from logging in' : 'allow them to log in again'}.`)) {
                return;
            }

            // Show loading state
            const button = event.target.closest('button');
            const originalHtml = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const response = await fetch('api/user-management-api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'toggle_status',
                        user_id: parseInt(userId),
                        new_status: newStatus,
                        csrf_token: '<?php echo $_SESSION['csrf_token']; ?>'
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();

                if (result.success) {
                    // Show success message
                    showAlert(`User ${action}d successfully!`, 'success');

                    // Update the status badge without full reload
                    const statusBadge = button.closest('tr').querySelector('.status-badge');
                    if (statusBadge) {
                        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                        statusBadge.className = `status-badge status-${newStatus}`;
                    }

                    // Update button icon
                    const icon = button.querySelector('i');
                    if (icon) {
                        icon.className = newStatus === 'active' ? 'fas fa-ban' : 'fas fa-check';
                    }

                    // Update button title
                    button.title = newStatus === 'active' ? 'Deactivate User' : 'Activate User';

                } else {
                    throw new Error(result.message || 'Unknown error occurred');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error updating user status: ' + error.message, 'error');
            } finally {
                // Restore button state
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        }

        // Enhanced export users functionality
        function exportUsers() {
            const activeTab = document.querySelector('.nav-tabs .nav-link.active');
            let userType = 'all';

            if (activeTab.id === 'lecturers-tab') {
                userType = 'lecturer';
            } else if (activeTab.id === 'hods-tab') {
                userType = 'hod';
            } else if (activeTab.id === 'students-tab') {
                userType = 'student';
            }

            // Show loading state
            const exportBtn = document.querySelector('button[onclick="exportUsers()"]');
            const originalHtml = exportBtn.innerHTML;
            exportBtn.disabled = true;
            exportBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Exporting...';

            try {
                // Create a form to submit export request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'export-users.php';
                form.style.display = 'none';

                // Add CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?php echo $_SESSION['csrf_token']; ?>';
                form.appendChild(csrfInput);

                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'user_type';
                typeInput.value = userType;
                form.appendChild(typeInput);

                // Add current filters
                const searchInput = document.createElement('input');
                searchInput.type = 'hidden';
                searchInput.name = 'search_term';
                searchInput.value = document.getElementById('searchInput').value;
                form.appendChild(searchInput);

                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status_filter';
                statusInput.value = document.getElementById('statusFilter').value;
                form.appendChild(statusInput);

                const deptInput = document.createElement('input');
                deptInput.type = 'hidden';
                deptInput.name = 'department_filter';
                deptInput.value = document.getElementById('departmentFilter').value;
                form.appendChild(deptInput);

                document.body.appendChild(form);
                form.submit();

                showAlert('Export started! Your download should begin shortly.', 'success');

            } catch (error) {
                console.error('Export error:', error);
                showAlert('Failed to start export. Please try again.', 'error');
            } finally {
                // Restore button state after a short delay
                setTimeout(() => {
                    exportBtn.disabled = false;
                    exportBtn.innerHTML = originalHtml;
                }, 2000);
            }
        }

        // Enhanced alert function
        function showAlert(message, type = 'info', duration = 5000) {
            // Remove existing alerts
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 400px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);';
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;

            document.body.appendChild(alertDiv);

            // Auto-dismiss
            if (duration > 0) {
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, duration);
            }
        }
    </script>
</body>
</html>

