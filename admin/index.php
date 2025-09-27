<?php
/**
 * Admin Panel - Main Interface
 * Enhanced admin dashboard with modern UI
 */

session_start();
require_once "../config.php";
require_once "../session_check.php";
require_role(['admin']);

// Page configuration
$page_title = "Admin Dashboard";
$current_page = "dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | RP Attendance System</title>

    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            --sidebar-width: 280px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Animated Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background:
                radial-gradient(circle at 20% 80%, rgba(0, 102, 204, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0, 102, 204, 0.1) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        /* Sidebar Styles */
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #003366 0%, #0066cc 100%);
            color: white;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .admin-sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .sidebar-logo i {
            font-size: 2.5rem;
            color: #ffffff;
            margin-right: 10px;
        }

        .sidebar-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .sidebar-subtitle {
            font-size: 0.85rem;
            opacity: 0.8;
            margin: 5px 0 0 0;
        }

        .sidebar-user {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.05);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: 600;
            color: #003366;
        }

        .user-details h6 {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .user-details small {
            opacity: 0.7;
            font-size: 0.75rem;
        }

        /* Navigation Styles */
        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 25px;
        }

        .nav-section-title {
            padding: 0 20px;
            margin-bottom: 10px;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
            font-weight: 600;
        }

        .nav-item {
            margin: 2px 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            font-weight: 500;
        }

        .nav-link:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .nav-link.active {
            color: white;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 100%);
            box-shadow: inset 3px 0 0 #ffffff;
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .nav-badge {
            margin-left: auto;
            background: rgba(255, 255, 255, 0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Header Styles */
        .admin-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            z-index: 999;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .admin-header.collapsed {
            left: 0;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 30px;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #003366;
            margin-right: 20px;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: rgba(0, 102, 204, 0.1);
            color: #0066cc;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #003366;
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 102, 204, 0.1);
            color: #0066cc;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .btn-icon:hover {
            background: #0066cc;
            color: white;
            transform: translateY(-2px);
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            border-radius: 25px;
            background: rgba(0, 102, 204, 0.05);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .user-menu:hover {
            background: rgba(0, 102, 204, 0.1);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 30px;
            min-height: calc(100vh - var(--header-height));
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
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
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #003366;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.8rem;
            margin-top: 8px;
        }

        .stat-change.positive {
            color: #28a745;
        }

        .stat-change.negative {
            color: #dc3545;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #003366;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 10px;
            color: #0066cc;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: #003366;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .action-card:hover {
            background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            color: white;
            border-color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 102, 204, 0.3);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }

        .action-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .action-desc {
            font-size: 0.8rem;
            opacity: 0.7;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 0px;
            }

            .admin-sidebar {
                transform: translateX(-100%);
            }

            .admin-sidebar.show {
                transform: translateX(0);
            }

            .admin-header {
                left: 0;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .action-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Loading States */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0066cc;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Notification Styles */
        .notification {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
        }

        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .mobile-menu-toggle {
                display: block;
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include shared components -->
    <?php include_once 'includes/admin-sidebar.php'; ?>
    <?php include_once 'includes/admin-header.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #0066cc, #003366);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value" id="totalStudents">-</div>
                <div class="stat-label">Total Students</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    +5.2% from last month
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #1e7e34);">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-value" id="totalLecturers">-</div>
                <div class="stat-label">Total Lecturers</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    +2 new this week
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107, #e0a800);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value" id="todayAttendance">-</div>
                <div class="stat-label">Today's Attendance</div>
                <div class="stat-change negative">
                    <i class="fas fa-arrow-down"></i>
                    -3.1% from yesterday
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value" id="pendingLeaves">-</div>
                <div class="stat-label">Pending Leaves</div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    Requires attention
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2 class="section-title">
                <i class="fas fa-bolt"></i>
                Quick Actions
            </h2>
            <div class="action-grid">
                <a href="student-registration.php" class="action-card">
                    <i class="fas fa-user-plus action-icon" style="color: #28a745;"></i>
                    <div class="action-title">Register Student</div>
                    <div class="action-desc">Add new student to system</div>
                </a>
                <a href="reports.php" class="action-card">
                    <i class="fas fa-chart-bar action-icon" style="color: #0066cc;"></i>
                    <div class="action-title">View Reports</div>
                    <div class="action-desc">Generate attendance reports</div>
                </a>
                <a href="hod-management.php" class="action-card">
                    <i class="fas fa-user-tie action-icon" style="color: #ffc107;"></i>
                    <div class="action-title">Manage HODs</div>
                    <div class="action-desc">Assign Head of Departments</div>
                </a>
                <a href="leave-management.php" class="action-card">
                    <i class="fas fa-calendar-alt action-icon" style="color: #dc3545;"></i>
                    <div class="action-title">Leave Requests</div>
                    <div class="action-desc">Review pending leaves</div>
                </a>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card" style="border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);">
                    <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: none;">
                        <h5 class="mb-0" style="color: #003366; font-weight: 600;">
                            <i class="fas fa-clock me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            <div class="activity-item">
                                <div class="activity-icon" style="background: #28a745;"></div>
                                <div class="activity-content">
                                    <h6>New student registered</h6>
                                    <small class="text-muted">2 minutes ago</small>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: #0066cc;"></div>
                                <div class="activity-content">
                                    <h6>HOD assigned to ICT Department</h6>
                                    <small class="text-muted">15 minutes ago</small>
                                </div>
                            </div>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: #ffc107;"></div>
                                <div class="activity-content">
                                    <h6>Leave request approved</h6>
                                    <small class="text-muted">1 hour ago</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card" style="border-radius: 16px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);">
                    <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border: none;">
                        <h5 class="mb-0" style="color: #003366; font-weight: 600;">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="notification-item">
                            <i class="fas fa-info-circle text-info"></i>
                            <span>System backup completed successfully</span>
                        </div>
                        <div class="notification-item">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            <span>3 leave requests pending approval</span>
                        </div>
                        <div class="notification-item">
                            <i class="fas fa-check-circle text-success"></i>
                            <span>All systems operational</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay" style="display: none;">
        <div class="loading-spinner"></div>
    </div>

    <!-- External Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>

    <!-- Shared Admin Scripts -->
    <?php include_once 'includes/admin-scripts.php'; ?>

    <!-- Page-specific Scripts -->
    <script>
        // Load statistics
        function loadStatistics() {
            $.getJSON('../admin-reports.php?ajax=1&action=get_stats', function(data) {
                document.getElementById('totalStudents').textContent = data.total_students || 0;
                document.getElementById('totalLecturers').textContent = '24'; // You can add this to the API
                document.getElementById('todayAttendance').textContent = Math.round(data.avg_attendance || 0) + '%';
                document.getElementById('pendingLeaves').textContent = data.pending_leaves || 0;
            }).fail(function() {
                console.error('Failed to load statistics');
            });
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadStatistics();
        });
    </script>
</body>
</html>