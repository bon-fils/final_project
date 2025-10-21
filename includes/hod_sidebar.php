<?php
/**
 * HOD Sidebar Navigation
 * Comprehensive sidebar for Head of Department functionality
 */

// Ensure user is HOD
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hod') {
    return;
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_page_no_ext = pathinfo($current_page, PATHINFO_FILENAME);

// Helper function to check if link is active
function isActiveHodLink($page_names) {
    global $current_page_no_ext;
    if (is_array($page_names)) {
        return in_array($current_page_no_ext, $page_names);
    }
    return $current_page_no_ext === $page_names;
}

// Get HOD information for display
$hod_name = $_SESSION['username'] ?? 'HOD';
$department_name = $user['department_name'] ?? 'Department';
?>

<style>
.hod-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 280px;
    height: 100vh;
    background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 50%, #1d4ed8 100%);
    color: white;
    padding: 0;
    overflow-y: auto;
    box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.hod-sidebar::-webkit-scrollbar {
    width: 6px;
}

.hod-sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.hod-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.hod-sidebar-header {
    padding: 25px 20px;
    background: rgba(0, 0, 0, 0.2);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.hod-sidebar-header h4 {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: #fbbf24;
}

.hod-sidebar-header .department-name {
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.8);
    margin-top: 5px;
}

.hod-sidebar-nav {
    padding: 15px 0;
}

.hod-nav-section {
    margin-bottom: 25px;
}

.hod-nav-section-title {
    padding: 8px 20px;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.hod-nav-link {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: rgba(255, 255, 255, 0.9);
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
    position: relative;
}

.hod-nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: #fbbf24;
    transform: translateX(2px);
}

.hod-nav-link.active {
    background: rgba(255, 255, 255, 0.15);
    color: #fbbf24;
    border-left-color: #fbbf24;
    font-weight: 600;
}

.hod-nav-link i {
    width: 20px;
    margin-right: 12px;
    font-size: 1rem;
    text-align: center;
}

.hod-nav-link .nav-text {
    flex: 1;
    font-size: 0.9rem;
}

.hod-nav-badge {
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: 8px;
    min-width: 18px;
    text-align: center;
}

.hod-nav-badge.warning {
    background: #f59e0b;
}

.hod-nav-badge.success {
    background: #10b981;
}

.hod-nav-badge.info {
    background: #3b82f6;
}

.hod-sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: auto;
}

.hod-user-info {
    display: flex;
    align-items: center;
    padding: 10px;
    background: rgba(0, 0, 0, 0.2);
    border-radius: 8px;
    margin-bottom: 15px;
}

.hod-user-avatar {
    width: 40px;
    height: 40px;
    background: #fbbf24;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    font-weight: bold;
    color: #1e3a8a;
}

.hod-user-details h6 {
    margin: 0;
    font-size: 0.9rem;
    color: white;
}

.hod-user-details small {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.75rem;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .hod-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .hod-sidebar.show {
        transform: translateX(0);
    }
}
</style>

<div class="hod-sidebar">
    <!-- Header -->
    <div class="hod-sidebar-header">
        <div class="d-flex align-items-center justify-content-center mb-2">
            <i class="fas fa-user-tie fa-2x text-warning me-2"></i>
            <div>
                <h4 class="mb-0">HOD Panel</h4>
                <div class="department-name"><?= htmlspecialchars($department_name) ?></div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <div class="hod-sidebar-nav">
        
        <!-- Dashboard Section -->
        <div class="hod-nav-section">
            <div class="hod-nav-section-title">Dashboard</div>
            <a href="hod-dashboard.php" class="hod-nav-link <?= isActiveHodLink('hod-dashboard') ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-text">Overview</span>
            </a>
        </div>

        <!-- Student Management -->
        <div class="hod-nav-section">
            <div class="hod-nav-section-title">Student Management</div>
            <a href="hod-students.php" class="hod-nav-link <?= isActiveHodLink(['hod-students', 'hod-student-details']) ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span class="nav-text">View Students</span>
                <?php if (isset($stats['total_students']) && $stats['total_students'] > 0): ?>
                    <span class="hod-nav-badge info"><?= $stats['total_students'] ?></span>
                <?php endif; ?>
            </a>
            <a href="hod-attendance-overview.php" class="hod-nav-link <?= isActiveHodLink(['hod-attendance-overview', 'hod-attendance-details']) ? 'active' : '' ?>">
                <i class="fas fa-calendar-check"></i>
                <span class="nav-text">Attendance Overview</span>
                <?php if (isset($stats['avg_attendance'])): ?>
                    <span class="hod-nav-badge <?= $stats['avg_attendance'] >= 80 ? 'success' : ($stats['avg_attendance'] >= 60 ? 'warning' : '') ?>"><?= $stats['avg_attendance'] ?>%</span>
                <?php endif; ?>
            </a>
            <a href="hod-leave-management.php" class="hod-nav-link <?= isActiveHodLink('hod-leave-management') ? 'active' : '' ?>">
                <i class="fas fa-envelope-open-text"></i>
                <span class="nav-text">Leave Requests</span>
                <?php if (isset($stats['pending_leaves']) && $stats['pending_leaves'] > 0): ?>
                    <span class="hod-nav-badge"><?= $stats['pending_leaves'] ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Academic Management -->
        <div class="hod-nav-section">
            <div class="hod-nav-section-title">Academic Management</div>
            <a href="hod-programs.php" class="hod-nav-link <?= isActiveHodLink('hod-programs') ? 'active' : '' ?>">
                <i class="fas fa-graduation-cap"></i>
                <span class="nav-text">Programs</span>
                <?php if (isset($stats['total_programs'])): ?>
                    <span class="hod-nav-badge info"><?= $stats['total_programs'] ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Staff Management -->
        <div class="hod-nav-section">
            <div class="hod-nav-section-title">Staff Management</div>
            <a href="hod-manage-lecturers.php" class="hod-nav-link <?= isActiveHodLink(['hod-manage-lecturers', 'hod-lecturer-details']) ? 'active' : '' ?>">
                <i class="fas fa-chalkboard-teacher"></i>
                <span class="nav-text">Lecturers</span>
                <?php if (isset($stats['total_lecturers'])): ?>
                    <span class="hod-nav-badge info"><?= $stats['total_lecturers'] ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- Reports & Analytics -->
        <div class="hod-nav-section">
            <div class="hod-nav-section-title">Reports & Analytics</div>
            <a href="hod-department-reports.php" class="hod-nav-link <?= isActiveHodLink('hod-department-reports') ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span class="nav-text">Department Reports</span>
            </a>
        </div>
    </div>

    <!-- Footer -->
    <div class="hod-sidebar-footer">
        <div class="hod-user-info">
            <div class="hod-user-avatar">
                <?= strtoupper(substr($hod_name, 0, 1)) ?>
            </div>
            <div class="hod-user-details">
                <h6><?= htmlspecialchars($hod_name) ?></h6>
                <small>Head of Department</small>
            </div>
        </div>
        <a href="logout.php" class="hod-nav-link" style="border-radius: 8px; background: rgba(239, 68, 68, 0.2); border-left: 3px solid #ef4444;">
            <i class="fas fa-sign-out-alt"></i>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</div>

<!-- Mobile toggle button -->
<button class="btn btn-primary d-md-none position-fixed" 
        style="top: 20px; left: 20px; z-index: 1001;" 
        onclick="toggleHodSidebar()">
    <i class="fas fa-bars"></i>
</button>

<script>
function toggleHodSidebar() {
    const sidebar = document.querySelector('.hod-sidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.hod-sidebar');
    const toggleBtn = document.querySelector('[onclick="toggleHodSidebar()"]');
    
    if (window.innerWidth <= 768 && 
        !sidebar.contains(e.target) && 
        !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('show');
    }
});
</script>
