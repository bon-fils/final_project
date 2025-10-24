<?php
/**
 * Student Sidebar Include
 * Provides consistent sidebar navigation for all student pages
 */

require_once "access_control.php";

// Get student data from session or passed variables
$student_name = $student_name ?? 'Student';
$student = $student ?? ['department_name' => 'Department'];
?>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="student-avatar">
            <i class="fas fa-user-graduate"></i>
        </div>
        <h5><?php echo htmlspecialchars($student_name, ENT_QUOTES, 'UTF-8'); ?></h5>
        <div class="department-badge">
            <?php echo htmlspecialchars($student['department_name'] ?? 'Department', ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>
    <a href="students-dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'students-dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>

    <?php if (hasStudentAccess('attendance_records', $student ?? [])): ?>
    <a href="attendance-records.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'attendance-records.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-check"></i> Attendance Records
    </a>
    <?php endif; ?>

    <?php if (hasStudentAccess('leave_request', $student ?? [])): ?>
    <a href="request-leave.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'request-leave.php' ? 'active' : ''; ?>">
        <i class="fas fa-file-signature"></i> Request Leave
    </a>
    <?php endif; ?>

    <?php if (hasStudentAccess('leave_status', $student ?? [])): ?>
    <a href="leave-status.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'leave-status.php' ? 'active' : ''; ?>">
        <i class="fas fa-envelope-open-text"></i> Leave Status
    </a>
    <?php endif; ?>

    <?php if (hasStudentAccess('my_courses', $student ?? [])): ?>
    <a href="my-courses.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'my-courses.php' ? 'active' : ''; ?>">
        <i class="fas fa-book"></i> My Courses
    </a>
    <?php endif; ?>

    <?php if (hasStudentAccess('academic_calendar', $student ?? [])): ?>
    <a href="academic-calendar.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'academic-calendar.php' ? 'active' : ''; ?>">
        <i class="fas fa-calendar-alt"></i> Academic Calendar
    </a>
    <?php endif; ?>

    <?php if (hasStudentAccess('library_portal', $student ?? [])): ?>
    <a href="library-portal.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'library-portal.php' ? 'active' : ''; ?>">
        <i class="fas fa-book-open"></i> Library
    </a>
    <?php endif; ?>

    <?php if (hasStudentAccess('fee_payments', $student ?? [])): ?>
    <a href="fee-payments.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'fee-payments.php' ? 'active' : ''; ?>">
        <i class="fas fa-credit-card"></i> Fee Payments
    </a>
    <?php endif; ?>

    <?php if (hasStudentAccess('career_portal', $student ?? [])): ?>
    <a href="career-portal.php" class="<?php echo basename($_SERVER['PHP_SELF']) === 'career-portal.php' ? 'active' : ''; ?>">
        <i class="fas fa-briefcase"></i> Career Portal
    </a>
    <?php endif; ?>

    <a href="<?php echo logout_url(); ?>" onclick="return confirm('Are you sure you want to logout?')">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>