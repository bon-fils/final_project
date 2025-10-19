<?php
/**
 * Admin Sidebar Include
 * Reusable sidebar component for all admin pages
 */
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="logo">
        <h3><i class="fas fa-graduation-cap me-2"></i>RP System</h3>
        <small>Admin Panel</small>
    </div>

    <ul class="sidebar-nav">
        <li class="nav-section">
            <i class="fas fa-th-large me-2"></i>Main Dashboard
        </li>
        <li>
            <a href="admin-dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i>Dashboard Overview
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-users me-2"></i>User Management
        </li>
        <li>
            <a href="register-student.php">
                <i class="fas fa-user-plus"></i>Register Student
            </a>
        </li>
        <li>
            <a href="admin-register-lecturer.php">
                <i class="fas fa-chalkboard-teacher"></i>Register Lecturer
            </a>
        </li>
        <li>
            <a href="manage-users.php">
                <i class="fas fa-users-cog"></i>Manage Users
            </a>
        </li>
        <li>
            <a href="admin-leave-management.php">
                <i class="fas fa-clipboard-list"></i>Leave Management
            </a>
        </li>
        <li>
            <a href="admin-view-users.php">
                <i class="fas fa-users"></i>View Users
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-sitemap me-2"></i>Organization
        </li>
        <li>
            <a href="manage-departments.php">
                <i class="fas fa-building"></i>Departments
            </a>
        </li>
        <li>
            <a href="assign-hod.php">
                <i class="fas fa-user-tie"></i>Assign HOD
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-chart-bar me-2"></i>Reports & Analytics
        </li>
        <li>
            <a href="admin-reports.php">
                <i class="fas fa-chart-line"></i>Analytics Reports
            </a>
        </li>
        <li>
            <a href="admin-attendance-reports.php">
                <i class="fas fa-calendar-check"></i>Attendance Reports
            </a>
        </li>

        <li class="nav-section">
            <i class="fas fa-cog me-2"></i>System
        </li>
        <li>
            <a href="system-logs.php">
                <i class="fas fa-file-code"></i>System Logs
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