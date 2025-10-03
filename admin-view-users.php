<?php
/**
 * Admin View Users - Enhanced Version
 * Allows admin to view lists of Lecturers, HODs, and Students with improved UI and functionality
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

/**
 * Fetch users by role with proper database schema
 */
function fetchUsers($role) {
    global $pdo;

    try {
        if ($role === 'student') {
            $stmt = $pdo->prepare("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.status,
                    u.created_at,
                    s.reg_no as reference_id,
                    CONCAT(s.first_name, ' ', s.last_name) as full_name,
                    d.name as department_name,
                    s.year_level,
                    s.telephone as phone
                FROM users u
                LEFT JOIN students s ON u.id = s.user_id
                LEFT JOIN departments d ON s.department_id = d.id
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
                    l.id_number as reference_id,
                    CONCAT(l.first_name, ' ', l.last_name) as full_name,
                    d.name as department_name,
                    l.phone,
                    l.role as user_role
                FROM users u
                LEFT JOIN lecturers l ON u.email = l.email
                LEFT JOIN departments d ON l.department_id = d.id
                WHERE u.role = ?
                ORDER BY u.created_at DESC
            ");
            $stmt->bindParam(1, $role);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching users: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user statistics
 */
function getUserStats() {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT role, COUNT(*) as count
            FROM users
            WHERE role IN ('student', 'lecturer', 'hod')
            GROUP BY role
        ");
        $stmt->execute();
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = ['student' => 0, 'lecturer' => 0, 'hod' => 0];
        foreach ($stats as $stat) {
            $result[$stat['role']] = $stat['count'];
        }
        return $result;
    } catch (PDOException $e) {
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
            <!-- User Type Tabs -->
            <ul class="nav nav-tabs" id="userTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="lecturers-tab" data-bs-toggle="tab" data-bs-target="#lecturers" type="button" role="tab">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Lecturers
                        <span class="badge bg-white text-primary ms-2"><?php echo count($lecturers); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="hods-tab" data-bs-toggle="tab" data-bs-target="#hods" type="button" role="tab">
                        <i class="fas fa-user-tie me-2"></i>HODs
                        <span class="badge bg-white text-success ms-2"><?php echo count($hods); ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">
                        <i class="fas fa-user-graduate me-2"></i>Students
                        <span class="badge bg-white text-info ms-2"><?php echo count($students); ?></span>
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($lecturers as $i => $row): ?>
                                        <tr>
                                            <td><?php echo $i+1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['full_name']); ?></h6>
                                                        <small class="text-muted">@<?php echo htmlspecialchars($row['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <code class="text-primary"><?php echo htmlspecialchars($row['reference_id'] ?? 'N/A'); ?></code>
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($hods as $i => $row): ?>
                                        <tr>
                                            <td><?php echo $i+1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['full_name']); ?></h6>
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
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($students as $i => $row): ?>
                                        <tr>
                                            <td><?php echo $i+1; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($row['full_name']); ?></h6>
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

        // Add smooth transitions and animations
        document.addEventListener('DOMContentLoaded', function() {
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
    </script>
</body>
</html>
