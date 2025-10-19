<?php
/**
 * Lecturer Sidebar Include
 * Reusable sidebar component for all lecturer pages
 * Contains navigation menu with consistent design and functionality
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <h4><i class="fas fa-graduation-cap me-2"></i>RP System</h4>
        <small>Lecturer Panel</small>
        <hr>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-section">
            <i class="fas fa-th-large me-2"></i>Main Dashboard
        </li>
        <li>
            <a href="lecturer-dashboard.php" class="<?php echo ($current_page === 'lecturer-dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>Dashboard Overview
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-book me-2"></i>Course Management
        </li>
        <li>
            <a href="lecturer-my-courses.php" class="<?php echo ($current_page === 'lecturer-my-courses.php') ? 'active' : ''; ?>">
                <i class="fas fa-book"></i>My Courses
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-calendar-check me-2"></i>Attendance
        </li>
        <li>
            <a href="attendance-session.php" class="<?php echo ($current_page === 'attendance-session.php') ? 'active' : ''; ?>">
                <i class="fas fa-video"></i>Attendance Session
            </a>
        </li>
        <li>
            <a href="lecturer-attendance-reports.php" class="<?php echo ($current_page === 'lecturer-attendance-reports.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>Attendance Reports
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-envelope me-2"></i>Requests
        </li>
        <li>
            <a href="lecturer-leave-requests.php" class="<?php echo ($current_page === 'lecturer-leave-requests.php') ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i>Leave Requests
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-sign-out-alt me-2"></i>Account
        </li>
        <li>
            <a href="logout.php" class="text-danger">
                <i class="fas fa-sign-out-alt"></i>Logout
            </a>
        </li>
    </ul>
</div>

<style>
/* Sidebar Styles - Consistent across all lecturer pages */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
    --card-shadow-hover: 0 20px 40px rgba(0,0,0,0.15);
}

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 280px;
    background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
    border-right: 1px solid rgba(0, 102, 204, 0.1);
    padding: 0;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 0 20px rgba(0, 102, 204, 0.1);
}

.sidebar .logo {
    background: linear-gradient(135deg, #0066cc 0%, #004080 100%);
    color: white;
    padding: 25px 20px;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.sidebar .logo::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="20" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="30" cy="80" r="1.5" fill="rgba(255,255,255,0.1)"/></svg>');
    pointer-events: none;
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

.sidebar .logo hr {
    border-color: rgba(255, 255, 255, 0.3);
    margin: 15px 0;
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
    border-bottom: 1px solid rgba(0, 102, 204, 0.1);
    margin-bottom: 10px;
}

.sidebar-nav a {
    display: block;
    padding: 14px 25px;
    color: #495057;
    text-decoration: none;
    border-radius: 0 25px 25px 0;
    margin: 0 0 2px 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
    position: relative;
    border-left: 3px solid transparent;
}

.sidebar-nav a:hover {
    background: rgba(0, 102, 204, 0.08);
    color: #0066cc;
    border-left-color: #0066cc;
    transform: translateX(8px);
    box-shadow: 2px 0 8px rgba(0, 102, 204, 0.15);
}

.sidebar-nav a.active {
    background: rgba(0, 102, 204, 0.1);
    color: #0066cc;
    border-left-color: #0066cc;
    font-weight: 600;
}

.sidebar-nav a i {
    margin-right: 12px;
    width: 18px;
    text-align: center;
}

.sidebar-nav a.text-danger:hover {
    background: rgba(220, 53, 69, 0.1);
    color: #dc3545;
    border-left-color: #dc3545;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .sidebar {
        position: fixed;
        width: 100%;
        height: auto;
        display: block;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        border-radius: 0 0 15px 15px;
        transform: translateY(-100%);
        transition: transform 0.3s ease;
        z-index: 1000;
    }

    .sidebar.mobile-open {
        transform: translateY(0);
    }

    .sidebar a {
        padding: 12px 18px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        margin: 0;
        border-radius: 0;
    }
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
    background: #0066cc;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #004080;
}
</style>

<script>
// Sidebar toggle functionality for mobile
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('mobile-open');
    }
}

// Auto-highlight active page based on URL
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname.split('/').pop();
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');

    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
});
</script>