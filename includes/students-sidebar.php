<?php
/**
 * Student Sidebar Component
 * Reusable sidebar for all student pages
 */

// Get current page for active state
$current_page = $_GET['page'] ?? 'dashboard';
$current_file = basename($_SERVER['PHP_SELF']);
?>

<!-- Student Sidebar -->
<div class="student-sidebar" id="studentSidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-graduation-cap"></i>
            <div>
                <h5 class="sidebar-title">RP Student</h5>
                <small class="sidebar-subtitle">Portal</small>
            </div>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['first_name'] ?? 'S', 0, 1)); ?>
            </div>
            <div class="user-details">
                <h6><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Student', ENT_QUOTES, 'UTF-8'); ?></h6>
                <small><?php echo htmlspecialchars($_SESSION['last_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="nav-section">
            <div class="nav-section-title">Dashboard</div>
            <div class="nav-item">
                <a href="students-dashboard.php" class="nav-link <?php echo $current_file == 'students-dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Academic Section -->
        <div class="nav-section">
            <div class="nav-section-title">Academic</div>
            <div class="nav-item">
                <a href="attendance-records.php" class="nav-link <?php echo $current_file == 'attendance-records.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance Records</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="my-courses.php" class="nav-link <?php echo $current_file == 'my-courses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i>
                    <span>My Courses</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="academic-calendar.php" class="nav-link <?php echo $current_file == 'academic-calendar.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Academic Calendar</span>
                </a>
            </div>
        </div>

        <!-- Services Section -->
        <div class="nav-section">
            <div class="nav-section-title">Services</div>
            <div class="nav-item">
                <a href="request-leave.php" class="nav-link <?php echo $current_file == 'request-leave.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature"></i>
                    <span>Request Leave</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="leave-status.php" class="nav-link <?php echo $current_file == 'leave-status.php' ? 'active' : ''; ?>">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>Leave Status</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="library-portal.php" class="nav-link <?php echo $current_file == 'library-portal.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book-open"></i>
                    <span>Library Portal</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="fee-payments.php" class="nav-link <?php echo $current_file == 'fee-payments.php' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Fee Payments</span>
                </a>
            </div>
        </div>

        <!-- Career Section -->
        <div class="nav-section">
            <div class="nav-section-title">Career</div>
            <div class="nav-item">
                <a href="career-portal.php" class="nav-link <?php echo $current_file == 'career-portal.php' ? 'active' : ''; ?>">
                    <i class="fas fa-briefcase"></i>
                    <span>Career Portal</span>
                </a>
            </div>
        </div>

        <!-- Account Section -->
        <div class="nav-section">
            <div class="nav-section-title">Account</div>
            <div class="nav-item">
                <a href="student-profile.php" class="nav-link <?php echo $current_file == 'student-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i>
                    <span>My Profile</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="logout.php" class="nav-link logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </nav>
</div>

<style>
/* Student Sidebar Styles */
:root {
    --student-primary: #1e3a8a;
    --student-secondary: #1e40af;
    --student-accent: #3b82f6;
    --student-light: #dbeafe;
    --sidebar-width: 280px;
}

.student-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: linear-gradient(180deg, var(--student-primary) 0%, var(--student-secondary) 100%);
    color: white;
    padding: 0;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
}

.sidebar-header {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
}

.sidebar-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: white;
}

.sidebar-logo i {
    font-size: 2rem;
    color: white;
}

.sidebar-title {
    color: white;
    font-weight: 700;
    margin: 0;
    font-size: 1.4rem;
}

.sidebar-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.75rem;
    font-weight: 500;
}

.sidebar-user {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 45px;
    height: 45px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    border: 2px solid rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1.1rem;
    color: white;
}

.user-details h6 {
    color: white;
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.user-details small {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.8rem;
}

.sidebar-nav {
    padding: 20px 0;
}

.nav-section {
    margin-bottom: 24px;
}

.nav-section-title {
    padding: 8px 20px;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.nav-item {
    margin: 2px 0;
}

.nav-link {
    display: block;
    padding: 12px 20px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: all 0.2s ease;
    font-weight: 500;
    position: relative;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border-left-color: var(--student-accent);
    padding-left: 24px;
}

.nav-link.active {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    border-left-color: var(--student-accent);
    font-weight: 600;
}

.nav-link.active::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 60%;
    background: var(--student-accent);
    border-radius: 0 2px 2px 0;
}

.nav-link i {
    margin-right: 12px;
    width: 18px;
    text-align: center;
    font-size: 1rem;
}

.logout-link {
    color: #fca5a5 !important;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    margin-top: 20px;
    padding-top: 16px;
}

.logout-link:hover {
    background: rgba(252, 165, 165, 0.1);
    color: #fca5a5 !important;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .student-sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1002;
    }

    .student-sidebar.show {
        transform: translateX(0);
        box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
    }

    .student-sidebar.show::after {
        content: '';
        position: fixed;
        top: 0;
        left: 280px;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(2px);
        z-index: -1;
    }
}
</style>