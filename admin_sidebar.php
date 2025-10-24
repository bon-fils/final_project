<?php
/**
 * Admin Sidebar Component
 * Reusable sidebar with dynamic navigation and role-based menu items
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Define menu items based on user role
function getMenuItems($userRole) {
    $menuItems = [
        'admin' => [
            ['name' => 'Dashboard', 'url' => 'admin-dashboard.php', 'icon' => 'fas fa-home', 'description' => 'Overview and statistics'],
            ['name' => 'Register Student', 'url' => 'register-student.php', 'icon' => 'fas fa-user-plus', 'description' => 'Add new students'],
            ['name' => 'Manage Departments', 'url' => 'manage-departments.php', 'icon' => 'fas fa-building', 'description' => 'Department management'],
            ['name' => 'Assign HOD', 'url' => 'assign-hod.php', 'icon' => 'fas fa-user-tie', 'description' => 'Head of Department'],
            ['name' => 'Attendance Management', 'url' => 'attendance-session.php', 'icon' => 'fas fa-clipboard-list', 'description' => 'Manage attendance sessions'],
            ['name' => 'Attendance Records', 'url' => 'attendance-records.php', 'icon' => 'fas fa-list-check', 'description' => 'View all attendance records'],
            ['name' => 'Leave Management', 'url' => 'leave-requests.php', 'icon' => 'fas fa-calendar-check', 'description' => 'Manage leave requests'],
            ['name' => 'Reports & Analytics', 'url' => 'admin-reports.php', 'icon' => 'fas fa-chart-bar', 'description' => 'Advanced reporting'],
            ['name' => 'System Logs', 'url' => 'system-logs.php', 'icon' => 'fas fa-file-alt', 'description' => 'System activity logs'],
        ],
        'hod' => [
            ['name' => 'Dashboard', 'url' => 'hod-dashboard.php', 'icon' => 'fas fa-home', 'description' => 'Department overview'],
            ['name' => 'Department Reports', 'url' => 'hod-department-reports.php', 'icon' => 'fas fa-chart-line', 'description' => 'Department analytics'],
            ['name' => 'Manage Lecturers', 'url' => 'hod-manage-lecturers.php', 'icon' => 'fas fa-users', 'description' => 'Lecturer management'],
            ['name' => 'Leave Management', 'url' => 'hod-leave-management.php', 'icon' => 'fas fa-calendar-check', 'description' => 'Approve leave requests'],
        ],
        'lecturer' => [
            ['name' => 'Dashboard', 'url' => 'lecturer-dashboard.php', 'icon' => 'fas fa-home', 'description' => 'Course overview'],
            ['name' => 'My Courses', 'url' => 'lecturer-my-courses.php', 'icon' => 'fas fa-book', 'description' => 'Manage courses'],
            ['name' => 'Attendance Records', 'url' => 'attendance-records.php', 'icon' => 'fas fa-clipboard-list', 'description' => 'View attendance'],
        ],
        'student' => [
            ['name' => 'Dashboard', 'url' => 'students-dashboard.php', 'icon' => 'fas fa-home', 'description' => 'My overview'],
            ['name' => 'Request Leave', 'url' => 'request-leave.php', 'icon' => 'fas fa-calendar-plus', 'description' => 'Request leave'],
            ['name' => 'Leave Status', 'url' => 'leave-status.php', 'icon' => 'fas fa-clock', 'description' => 'Check leave status'],
        ]
    ];

    return $menuItems[$userRole] ?? $menuItems['admin'];
}

// Get user role from session
$userRole = $_SESSION['role'] ?? 'admin';
$menuItems = getMenuItems($userRole);

// Get user display name
$userDisplayName = '';
if (isset($_SESSION['first_name']) && isset($_SESSION['last_name'])) {
    $userDisplayName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];
} elseif (isset($_SESSION['username'])) {
    $userDisplayName = $_SESSION['username'];
}
?>

<!-- Admin Sidebar -->
<div class="sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <div class="user-info text-center mb-4">
            <div class="user-avatar mb-2">
                <i class="fas fa-user-circle fa-3x text-primary"></i>
            </div>
            <h6 class="user-name text-white mb-1"><?php echo htmlspecialchars($userDisplayName); ?></h6>
            <small class="user-role text-white-50"><?php echo ucfirst(htmlspecialchars($userRole)); ?></small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($menuItems as $item): ?>
                <li class="nav-item">
                    <a href="<?php echo htmlspecialchars($item['url']); ?>"
                       class="nav-link <?php echo ($currentPage === $item['url']) ? 'active' : ''; ?>"
                       data-title="<?php echo htmlspecialchars($item['description']); ?>">
                        <i class="<?php echo htmlspecialchars($item['icon']); ?>"></i>
                        <span class="nav-text"><?php echo htmlspecialchars($item['name']); ?></span>
                        <?php if ($currentPage === $item['url']): ?>
                            <span class="active-indicator"></span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <?php 
        // Include secure logout helper
        require_once __DIR__ . '/logout_helper.php';
        echo getSecureLogoutLink('nav-link logout-link', 'fas fa-sign-out-alt', '<span class="nav-text">Logout</span>');
        ?>
    </div>
</div>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    background: linear-gradient(135deg, #0066cc 0%, #003366 100%);
    padding: 0;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    z-index: 1000;
    transition: var(--transition);
    overflow-y: auto;
}

.sidebar-header {
    padding: 30px 20px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.1);
}

.user-avatar {
    margin-bottom: 15px;
}

.user-avatar i {
    opacity: 0.9;
    transition: var(--transition);
}

.user-avatar i:hover {
    opacity: 1;
    transform: scale(1.05);
}

.user-name {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 5px;
}

.user-role {
    font-size: 0.85rem;
    opacity: 0.8;
}

.sidebar-nav {
    padding: 20px 0;
    flex: 1;
}

.nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin-bottom: 5px;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 15px 25px;
    color: rgba(255,255,255,0.9);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    border-left: 3px solid transparent;
}

.nav-link:hover {
    background: rgba(255,255,255,0.1);
    color: white;
    border-left-color: rgba(255,255,255,0.3);
    transform: translateX(5px);
}

.nav-link.active {
    background: rgba(255,255,255,0.15);
    color: white;
    border-left-color: #fff;
    font-weight: 600;
}

.nav-link i {
    font-size: 1.2rem;
    margin-right: 15px;
    width: 20px;
    text-align: center;
}

.nav-text {
    flex: 1;
    font-size: 0.95rem;
}

.active-indicator {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    width: 8px;
    height: 8px;
    background: #fff;
    border-radius: 50%;
}

.sidebar-footer {
    padding: 20px 0;
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: auto;
}

.logout-link {
    color: rgba(255,255,255,0.7) !important;
}

.logout-link:hover {
    color: #dc3545 !important;
    background: rgba(220,53,69,0.1) !important;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }

    .sidebar.show {
        transform: translateX(0);
    }
}

/* Mobile menu toggle */
.mobile-menu-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1001;
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 10px;
    border-radius: 5px;
    cursor: pointer;
}

@media (max-width: 768px) {
    .mobile-menu-toggle {
        display: block;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth scrolling for sidebar
    const sidebar = document.getElementById('adminSidebar');
    if (sidebar) {
        sidebar.addEventListener('wheel', function(e) {
            e.preventDefault();
            sidebar.scrollTop += e.deltaY;
        });
    }

    // Add tooltips for menu items
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            const title = this.getAttribute('data-title');
            if (title) {
                // Create tooltip
                const tooltip = document.createElement('div');
                tooltip.className = 'nav-tooltip';
                tooltip.textContent = title;
                document.body.appendChild(tooltip);

                // Position tooltip
                const rect = this.getBoundingClientRect();
                tooltip.style.position = 'fixed';
                tooltip.style.top = rect.top + 'px';
                tooltip.style.left = (rect.right + 10) + 'px';
                tooltip.style.background = 'rgba(0,0,0,0.8)';
                tooltip.style.color = 'white';
                tooltip.style.padding = '5px 10px';
                tooltip.style.borderRadius = '4px';
                tooltip.style.fontSize = '0.8rem';
                tooltip.style.zIndex = '9999';
                tooltip.style.whiteSpace = 'nowrap';

                this.addEventListener('mouseleave', function() {
                    if (tooltip.parentNode) {
                        tooltip.parentNode.removeChild(tooltip);
                    }
                });
            }
        });
    });
});
</script>