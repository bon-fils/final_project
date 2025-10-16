<?php
/**
 * Modern Sidebar Component for RP Attendance System
 * Features: Responsive, Accessible, Theme-aware
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$user_role = $_SESSION['role'] ?? 'guest';
$user_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
if (empty($user_name)) {
    $user_name = $_SESSION['username'] ?? 'User';
}
?>

<!-- Sidebar Styles -->
<style>
:root {
    --sidebar-width: 280px;
    --sidebar-collapsed-width: 80px;
    --sidebar-bg: linear-gradient(180deg, #0ea5e9 0%, #0284c7 100%);
    --sidebar-text: #ffffff;
    --sidebar-text-muted: rgba(255, 255, 255, 0.8);
    --sidebar-hover: rgba(255, 255, 255, 0.15);
    --sidebar-active: rgba(255, 255, 255, 0.2);
    --sidebar-border: rgba(255, 255, 255, 0.1);
    --sidebar-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --border-radius: 12px;
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    z-index: 1000;
    overflow-y: auto;
    overflow-x: hidden;
    transition: var(--transition);
    box-shadow: var(--sidebar-shadow);
    backdrop-filter: blur(10px);
}

.sidebar.collapsed {
    width: var(--sidebar-collapsed-width);
}

.sidebar.collapsed .sidebar-text {
    opacity: 0;
    visibility: hidden;
}

.sidebar.collapsed .sidebar-item-text {
    display: none;
}

.sidebar.collapsed .sidebar-header {
    padding: 20px 15px;
}

.sidebar.collapsed .sidebar-menu-item {
    padding: 15px;
    justify-content: center;
}

.sidebar.collapsed .sidebar-menu-link {
    justify-content: center;
}

.sidebar::-webkit-scrollbar {
    width: 4px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

.sidebar-header {
    padding: 30px 25px 20px;
    border-bottom: 1px solid var(--sidebar-border);
    transition: var(--transition);
}

.sidebar-logo {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.sidebar-logo img {
    width: 50px;
    height: 50px;
    border-radius: var(--border-radius);
    object-fit: cover;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transition: var(--transition);
}

.sidebar-logo:hover img {
    transform: scale(1.05);
}

.sidebar-logo-text {
    flex: 1;
}

.sidebar-logo-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    color: var(--sidebar-text);
    letter-spacing: -0.025em;
}

.sidebar-logo-subtitle {
    font-size: 0.875rem;
    color: var(--sidebar-text-muted);
    margin: 0.25rem 0 0;
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--border-radius);
    backdrop-filter: blur(10px);
    border: 1px solid var(--sidebar-border);
}

.sidebar-user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.1));
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--sidebar-text);
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.sidebar-user-info {
    flex: 1;
    min-width: 0;
}

.sidebar-user-name {
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--sidebar-text);
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.sidebar-user-role {
    font-size: 0.75rem;
    color: var(--sidebar-text-muted);
    margin: 0.125rem 0 0;
    text-transform: capitalize;
}

.sidebar-menu {
    padding: 20px 0;
}

.sidebar-menu-item {
    margin: 0 15px 5px;
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--transition);
}

.sidebar-menu-item:hover {
    background: var(--sidebar-hover);
    transform: translateX(5px);
}

.sidebar-menu-link {
    display: flex;
    align-items: center;
    padding: 15px 20px;
    color: var(--sidebar-text);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.sidebar-menu-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.sidebar-menu-link:hover::before,
.sidebar-menu-link.active::before {
    left: 100%;
}

.sidebar-menu-link.active {
    background: var(--sidebar-active);
    font-weight: 600;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
}

.sidebar-menu-icon {
    width: 20px;
    height: 20px;
    margin-right: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sidebar-menu-link.active .sidebar-menu-icon {
    color: #ffffff;
}

.sidebar-item-text {
    flex: 1;
    font-size: 0.875rem;
    font-weight: 500;
    transition: var(--transition);
}

.sidebar-menu-badge {
    background: rgba(255, 255, 255, 0.2);
    color: var(--sidebar-text);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: auto;
}

.sidebar-toggle {
    position: absolute;
    top: 20px;
    right: -15px;
    width: 30px;
    height: 30px;
    background: #ffffff;
    border: none;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    cursor: pointer;
    z-index: 1001;
    transition: var(--transition);
}

.sidebar-toggle:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.sidebar-toggle-icon {
    width: 16px;
    height: 16px;
    transition: var(--transition);
}

.sidebar.collapsed .sidebar-toggle-icon {
    transform: rotate(180deg);
}

/* Mobile Styles */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        width: var(--sidebar-width);
    }

    .sidebar.mobile-open {
        transform: translateX(0);
    }

    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: var(--transition);
    }

    .sidebar-overlay.active {
        opacity: 1;
        visibility: visible;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --sidebar-bg: linear-gradient(180deg, #1e293b 0%, #334155 100%);
        --sidebar-text: #f1f5f9;
        --sidebar-text-muted: rgba(241, 245, 249, 0.8);
        --sidebar-hover: rgba(241, 245, 249, 0.1);
        --sidebar-active: rgba(241, 245, 249, 0.15);
        --sidebar-border: rgba(241, 245, 249, 0.1);
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .sidebar,
    .sidebar-menu-item,
    .sidebar-menu-link,
    .sidebar-toggle {
        transition: none;
    }

    .sidebar-menu-link::before {
        display: none;
    }
}

/* High contrast support */
@media (prefers-contrast: high) {
    .sidebar {
        border-right: 2px solid var(--sidebar-text);
    }

    .sidebar-menu-link.active {
        border: 2px solid var(--sidebar-text);
    }
}

/* Focus management */
.sidebar-menu-link:focus {
    outline: 2px solid var(--sidebar-text);
    outline-offset: 2px;
    border-radius: var(--border-radius);
}

.sidebar-toggle:focus {
    outline: 2px solid var(--sidebar-text);
    outline-offset: 2px;
}
</style>

<!-- Sidebar HTML -->
<div class="sidebar" id="sidebar">
    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
        <svg class="sidebar-toggle-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </button>

    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <img src="../RP_Logo.jpeg" alt="RP Attendance System Logo" onerror="this.src='../assets/images/logo-placeholder.png'">
            <div class="sidebar-logo-text">
                <h1 class="sidebar-logo-title">RP System</h1>
                <p class="sidebar-logo-subtitle">Attendance Management</p>
            </div>
        </div>

        <!-- User Info -->
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="sidebar-user-info">
                <p class="sidebar-user-name"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="sidebar-user-role"><?php echo htmlspecialchars($user_role); ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-menu" role="navigation" aria-label="Main navigation">
        <?php
        $menu_items = [
            'lecturer' => [
                ['href' => 'lecturer-dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'page' => 'lecturer-dashboard'],
                ['href' => 'my-courses.php', 'icon' => 'fas fa-book', 'text' => 'My Courses', 'page' => 'my-courses'],
                ['href' => 'attendance-session.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Attendance Sessions', 'page' => 'attendance-session'],
                ['href' => 'take-attendance.php', 'icon' => 'fas fa-clipboard-check', 'text' => 'Take Attendance', 'page' => 'take-attendance'],
                ['href' => 'attendance-reports.php', 'icon' => 'fas fa-chart-bar', 'text' => 'Reports', 'page' => 'attendance-reports'],
                ['href' => 'leave-requests.php', 'icon' => 'fas fa-calendar-times', 'text' => 'Leave Requests', 'page' => 'leave-requests'],
            ],
            'admin' => [
                ['href' => 'admin/index.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'page' => 'index'],
                ['href' => 'manage-users.php', 'icon' => 'fas fa-users', 'text' => 'Manage Users', 'page' => 'manage-users'],
                ['href' => 'manage-departments.php', 'icon' => 'fas fa-building', 'text' => 'Departments', 'page' => 'manage-departments'],
                ['href' => 'admin/reports.php', 'icon' => 'fas fa-chart-line', 'text' => 'Reports', 'page' => 'reports'],
                ['href' => 'system-logs.php', 'icon' => 'fas fa-history', 'text' => 'System Logs', 'page' => 'system-logs'],
                ['href' => 'admin-settings.php', 'icon' => 'fas fa-cog', 'text' => 'Settings', 'page' => 'admin-settings'],
            ],
            'hod' => [
                ['href' => 'hod-dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'page' => 'hod-dashboard'],
                ['href' => 'hod-department-reports.php', 'icon' => 'fas fa-chart-pie', 'text' => 'Department Reports', 'page' => 'hod-department-reports'],
                ['href' => 'hod-manage-lecturers.php', 'icon' => 'fas fa-user-tie', 'text' => 'Manage Lecturers', 'page' => 'hod-manage-lecturers'],
                ['href' => 'hod-leave-management.php', 'icon' => 'fas fa-calendar-check', 'text' => 'Leave Management', 'page' => 'hod-leave-management'],
            ],
            'student' => [
                ['href' => 'students-dashboard.php', 'icon' => 'fas fa-tachometer-alt', 'text' => 'Dashboard', 'page' => 'students-dashboard'],
                ['href' => 'my-courses.php', 'icon' => 'fas fa-book-open', 'text' => 'My Courses', 'page' => 'my-courses'],
                ['href' => 'leave-status.php', 'icon' => 'fas fa-calendar-alt', 'text' => 'Leave Status', 'page' => 'leave-status'],
            ]
        ];

        $current_menu = $menu_items[$user_role] ?? [];

        foreach ($current_menu as $item):
            $is_active = ($current_page === $item['page']);
        ?>
            <div class="sidebar-menu-item">
                <a href="<?php echo htmlspecialchars($item['href']); ?>"
                   class="sidebar-menu-link <?php echo $is_active ? 'active' : ''; ?>"
                   aria-current="<?php echo $is_active ? 'page' : 'false'; ?>">
                    <div class="sidebar-menu-icon">
                        <i class="<?php echo htmlspecialchars($item['icon']); ?>" aria-hidden="true"></i>
                    </div>
                    <span class="sidebar-item-text"><?php echo htmlspecialchars($item['text']); ?></span>
                    <?php if (isset($item['badge'])): ?>
                        <span class="sidebar-menu-badge"><?php echo htmlspecialchars($item['badge']); ?></span>
                    <?php endif; ?>
                </a>
            </div>
        <?php endforeach; ?>

        <!-- Logout -->
        <div class="sidebar-menu-item">
            <a href="logout.php" class="sidebar-menu-link" onclick="return confirm('Are you sure you want to logout?')">
                <div class="sidebar-menu-icon">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                </div>
                <span class="sidebar-item-text">Logout</span>
            </a>
        </div>
    </nav>
</div>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    // Toggle sidebar collapse/expand
    sidebarToggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');

        // Update toggle icon
        const icon = this.querySelector('.sidebar-toggle-icon');
        if (sidebar.classList.contains('collapsed')) {
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>';
        } else {
            icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>';
        }

        // Store preference
        localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
    });

    // Mobile sidebar toggle
    function toggleMobileSidebar() {
        sidebar.classList.toggle('mobile-open');
        sidebarOverlay.classList.toggle('active');
    }

    // Mobile menu button (if exists)
    const mobileMenuBtn = document.querySelector('.mobile-menu-toggle');
    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', toggleMobileSidebar);
    }

    // Close sidebar when clicking overlay
    sidebarOverlay.addEventListener('click', toggleMobileSidebar);

    // Close sidebar on mobile when clicking menu items
    if (window.innerWidth <= 768) {
        document.querySelectorAll('.sidebar-menu-link').forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
            });
        });
    }

    // Restore sidebar state from localStorage
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (sidebarCollapsed) {
        sidebar.classList.add('collapsed');
        const icon = sidebarToggle.querySelector('.sidebar-toggle-icon');
        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>';
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        }
    });

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        // Escape key closes mobile sidebar
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            toggleMobileSidebar();
        }
    });
});
</script>