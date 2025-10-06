<?php
/**
 * User Management Template
 * HTML template for user management interface
 *
 * @version 1.0.0
 * @author Rwanda Polytechnic Development Team
 * @since 2024
 */

// This file should be included after setting $stats and $is_lecturer_registration
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $is_lecturer_registration ? 'Register Lecturer' : 'Manage Users'; ?> | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="css/manage-users.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        // Pass PHP variables to JavaScript
        window.isLecturerRegistration = <?php echo $is_lecturer_registration ? 'true' : 'false'; ?>;
    </script>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

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
                <a href="admin-dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>Dashboard Overview
                </a>
            </li>

            <li class="nav-section">
                <i class="fas fa-users me-2"></i>User Management
            </li>
            <li>
                <a href="manage-users.php" class="<?php echo !$is_lecturer_registration ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>Manage Users
                </a>
            </li>
            <?php if ($is_lecturer_registration): ?>
            <li>
                <a href="manage-users.php?role=lecturer" class="active">
                    <i class="fas fa-chalkboard-teacher"></i>Register Lecturer
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="register-student.php">
                    <i class="fas fa-user-plus"></i>Register Student
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
                <a href="attendance-reports.php">
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
            <li>
                <a href="hod-leave-management.php">
                    <i class="fas fa-clipboard-list"></i>Leave Management
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

    <!-- Main Content -->
    <div class="main-content">
        <?php if (!$is_lecturer_registration): ?>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="mb-1 text-primary">
                    <i class="fas fa-users me-3"></i>User Management
                </h2>
                <p class="text-muted mb-0">Manage user accounts, roles, and permissions</p>
            </div>
            <div class="d-flex gap-2 align-items-center">
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-success" id="exportUsers" title="Export to CSV">
                        <i class="fas fa-download me-1"></i>Export
                    </button>
                    <button class="btn btn-outline-primary" id="refreshUsers" title="Refresh data">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="fas fa-plus me-1"></i>Create User
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="text-center mb-4">
            <h2 class="mb-1 text-primary">
                <i class="fas fa-chalkboard-teacher me-3"></i>Lecturer Registration
            </h2>
            <p class="text-muted mb-0">Register new lecturers for the system</p>
        </div>
        <?php endif; ?>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="text-center text-white">
                <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2">Loading Users</h5>
                <p class="mb-0">Please wait while we fetch user data...</p>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertBox" class="mb-4"></div>

        <?php if (!$is_lecturer_registration): ?>
        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-users text-primary me-3" style="font-size: 2.5rem;"></i>
                            <div>
                                <h3 class="display-4 mb-0" id="totalUsers"><?php echo $stats['total']; ?></h3>
                                <small class="text-muted">Total Users</small>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 6px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 100%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-user-check text-success me-3" style="font-size: 2.5rem;"></i>
                            <div>
                                <h3 class="display-4 mb-0" id="activeUsers"><?php echo $stats['active']; ?></h3>
                                <small class="text-muted">Active Users</small>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 6px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $stats['total'] > 0 ? ($stats['active'] / $stats['total']) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-user-times text-danger me-3" style="font-size: 2.5rem;"></i>
                            <div>
                                <h3 class="display-4 mb-0" id="inactiveUsers"><?php echo $stats['inactive']; ?></h3>
                                <small class="text-muted">Inactive Users</small>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 6px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo $stats['total'] > 0 ? ($stats['inactive'] / $stats['total']) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-chart-line text-info me-3" style="font-size: 2.5rem;"></i>
                            <div>
                                <h3 class="display-4 mb-0" id="activePercentage">
                                    <?php echo $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100, 1) : 0; ?>%
                                </h3>
                                <small class="text-muted">Active Rate</small>
                            </div>
                        </div>
                        <div class="progress mt-3" style="height: 6px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $stats['total'] > 0 ? ($stats['active'] / $stats['total']) * 100 : 0; ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Toolbar -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="mb-0 text-primary">
                            <i class="fas fa-bolt me-2"></i>Quick Actions
                        </h6>
                        <small class="text-muted">Perform bulk operations on selected users</small>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="btn-group" role="group">
                            <button class="btn btn-outline-success btn-sm" id="bulkActivate" disabled>
                                <i class="fas fa-play me-1"></i>Activate Selected
                            </button>
                            <button class="btn btn-outline-warning btn-sm" id="bulkDeactivate" disabled>
                                <i class="fas fa-pause me-1"></i>Deactivate Selected
                            </button>
                            <button class="btn btn-outline-danger btn-sm" id="bulkDelete" disabled>
                                <i class="fas fa-trash me-1"></i>Delete Selected
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search students...">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="roleFilter">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="hod">HOD</option>
                            <option value="lecturer">Lecturer</option>
                            <option value="student">Student</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="departmentFilter">
                            <option value="">All Departments</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="yearLevelFilter">
                            <option value="">All Years</option>
                            <option value="1">Year 1</option>
                            <option value="2">Year 2</option>
                            <option value="3">Year 3</option>
                            <option value="4">BTech</option>

                        </select>
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-outline-secondary w-100" id="clearFilters">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-2">
                        <select class="form-select" id="genderFilter">
                            <option value="">All Genders</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="ageFilter">
                            <option value="">All Ages</option>
                            <option value="16-18">16-18 years</option>
                            <option value="19-21">19-21 years</option>
                            <option value="22-25">22-25 years</option>
                            <option value="26+">26+ years</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control" id="regNoFilter" placeholder="Reg. Number">
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="emailFilter" placeholder="Email Address">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100" id="applyAdvancedFilters">
                            <i class="fas fa-search me-1"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="usersTable">
                        <thead>
                            <tr>
                                <th width="40">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllUsers">
                                        <label class="form-check-label visually-hidden" for="selectAllUsers">Select All</label>
                                    </div>
                                </th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Academic Info</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Users will be loaded here -->
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted" id="usersInfo">
                        Loading users...
                    </div>
                    <nav id="paginationNav">
                        <!-- Pagination will be generated here -->
                    </nav>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Lecturer Registration Form (Similar to hod-manage-lecturers.php) -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Lecturer Information</h5>
            </div>
            <div class="card-body">
                <form id="lecturerRegistrationForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="role" value="lecturer">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" required aria-required="true">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" required aria-required="true">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gender">Gender *</label>
                            <select id="gender" name="gender" class="form-select" required aria-required="true">
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="dob">Date of Birth *</label>
                            <input type="date" id="dob" name="dob" class="form-control" required aria-required="true" max="<?php echo date('Y-m-d', strtotime('-21 years')); ?>">
                            <small class="form-text text-muted">Must be at least 21 years old.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="id_number">ID Number *</label>
                            <input type="text" id="id_number" name="id_number" class="form-control" required aria-required="true" maxlength="16" placeholder="1234567890123456">
                            <div class="d-flex justify-content-between">
                                <small class="form-text text-muted">Must be exactly 16 characters long.</small>
                                <small id="id-counter" class="form-text text-info" style="display: none;">0/16</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required aria-required="true">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" class="form-control" placeholder="1234567890">
                            <small class="form-text text-muted">Optional. Must be exactly 10 digits only.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="education_level">Education Level *</label>
                            <select id="education_level" name="education_level" class="form-select" required aria-required="true">
                                <option value="">Select</option>
                                <option value="Bachelor's">Bachelor's</option>
                                <option value="Master's">Master's</option>
                                <option value="PhD">PhD</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="photo">Photo</label>
                            <input type="file" id="photo" name="photo" class="form-control" accept="image/*" aria-describedby="photoHelp">
                            <small id="photoHelp" class="form-text text-muted">Optional. Max 2MB, JPG/PNG/GIF only.</small>
                        </div>
                    </div>

                    <!-- Course and Option Assignment Section -->
                    <div class="row g-3 mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-cogs me-2"></i>Access Permissions & Assignments
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <!-- Option Assignment Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="fas fa-list me-2"></i>Option Access (Required)
                                                <small class="text-muted fw-normal">- Select options this lecturer can access</small>
                                            </label>
                                            <div class="option-selection-container">
                                                <div id="optionsContainer" class="text-center">
                                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                        <span class="visually-hidden">Loading options...</span>
                                                    </div>
                                                    <p class="text-muted mt-2">Loading available options...</p>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="form-text text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Select at least one option for the lecturer to access.
                                                </small>
                                                <small class="text-primary fw-bold">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    <span id="selectedOptionsCount">0</span> options selected
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Course Assignment Section -->
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label">
                                                <i class="fas fa-book me-2"></i>Course Assignment (Optional)
                                                <small class="text-muted fw-normal">- Only unassigned courses shown</small>
                                            </label>
                                            <div class="course-selection-container">
                                                <div id="coursesContainer" class="text-center">
                                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                        <span class="visually-hidden">Loading courses...</span>
                                                    </div>
                                                    <p class="text-muted mt-2">Loading available courses...</p>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <small class="form-text text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Select courses to assign to this lecturer. Only unassigned courses are available.
                                                </small>
                                                <small class="text-primary fw-bold">
                                                    <i class="fas fa-check-circle me-1"></i>
                                                    <span id="selectedCoursesCount">0</span> courses selected
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Hidden inputs to store selected course and option IDs -->
                    <div id="selectedOptionsInputs" style="display: none;"></div>
                    <div id="selectedCoursesInputs" style="display: none;"></div>

                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" id="addBtn">
                            <i class="fas fa-plus me-2"></i>Register Lecturer
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Create New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="createUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <input type="password" class="form-control" name="password" required minlength="8" autocomplete="current-password">
                                <small class="form-text text-muted">Password must be at least 8 characters long</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="hod">HOD</option>
                                    <option value="lecturer">Lecturer</option>
                                    <option value="student">Student</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference ID</label>
                                <input type="text" class="form-control" name="reference_id" placeholder="Student ID or Employee ID">
                            </div>
                            <!-- Lecturer-specific fields -->
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob">
                            </div>
                            <div class="col-md-6 student-field" style="display: none;">
                                <label class="form-label">Program/Option *</label>
                                <select class="form-select" name="option_id" required>
                                    <option value="">Select Program</option>
                                </select>
                            </div>
                            <div class="col-md-6 student-field" style="display: none;">
                                <label class="form-label">Registration Number *</label>
                                <input type="text" class="form-control" name="reg_no" required placeholder="e.g., 22RP07867">
                            </div>
                            <div class="col-md-6 student-field" style="display: none;">
                                <label class="form-label">Year Level</label>
                                <select class="form-select" name="year_level">
                                    <option value="1">Year 1</option>
                                    <option value="2">Year 2</option>
                                    <option value="3">Year 3</option>
                                    <option value="4">Year 4</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id">
                                    <option value="">Select Department</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Education Level</label>
                                <select class="form-select" name="education_level">
                                    <option value="">Select Level</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Bachelor">Bachelor</option>
                                    <option value="Master">Master</option>
                                    <option value="PhD">PhD</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Photo</label>
                                <input type="file" class="form-control" name="photo" accept="image/*">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm">
                    <input type="hidden" name="user_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role *</label>
                                <select class="form-select" name="role" required>
                                    <option value="">Select Role</option>
                                    <option value="admin">Admin</option>
                                    <option value="hod">HOD</option>
                                    <option value="lecturer">Lecturer</option>
                                    <option value="student">Student</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Reference ID</label>
                                <input type="text" class="form-control" name="reference_id" placeholder="Student ID or Employee ID">
                            </div>
                            <!-- Lecturer-specific fields -->
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="dob">
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="department_id">
                                    <option value="">Select Department</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Education Level</label>
                                <select class="form-select" name="education_level">
                                    <option value="">Select Level</option>
                                    <option value="Certificate">Certificate</option>
                                    <option value="Diploma">Diploma</option>
                                    <option value="Bachelor">Bachelor</option>
                                    <option value="Master">Master</option>
                                    <option value="PhD">PhD</option>
                                </select>
                            </div>
                            <div class="col-md-6 lecturer-field" style="display: none;">
                                <label class="form-label">Photo</label>
                                <input type="file" class="form-control" name="photo" accept="image/*">
                                <small class="form-text text-muted">Leave empty to keep current photo</small>
                            </div>
                            <!-- Student-specific fields -->
                            <div class="col-md-6 student-field" style="display: none;">
                                <label class="form-label">Year Level</label>
                                <select class="form-select" name="year_level">
                                    <option value="1">Year 1</option>
                                    <option value="2">Year 2</option>
                                    <option value="3">Year 3</option>
                                    <option value="4">Year 4</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Reset Password</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="resetPasswordForm">
                    <input type="hidden" name="user_id">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You are about to reset this user's password. This action cannot be undone.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password *</label>
                            <input type="password" class="form-control" name="new_password" required minlength="8" autocomplete="new-password">
                            <small class="form-text text-muted">Password must be at least 8 characters long</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-1"></i>Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/manage-users.js"></script>

    <?php if ($is_lecturer_registration): ?>
    <script>
    // Course and Option assignment functionality for lecturer registration
    let availableCourses = [];
    let assignedCourses = [];
    let availableOptions = [];
    let selectedOptionsForRegistration = [];
    let selectedCoursesForRegistration = [];

    // Load options for registration form
    function loadOptionsForRegistration() {
        const optionsContainer = document.getElementById('optionsContainer');

        fetch('api/department-option-api.php?action=get_options')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.length > 0) {
                    availableOptions = data.data;
                    renderOptionsForRegistration();
                } else {
                    optionsContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${data.message || 'No options available.'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading options:', error);
                optionsContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading options. Please check your connection and try refreshing the page.
                        <br><small class="text-muted">Error details: ${error.message}</small>
                    </div>
                `;
            });
    }

    // Load courses for registration form
    function loadCoursesForRegistration() {
        const coursesContainer = document.getElementById('coursesContainer');

        fetch('api/assign-courses-api.php?action=get_courses')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    availableCourses = data.data;
                    renderCoursesForRegistration();
                } else {
                    coursesContainer.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            No courses available or unable to load courses. You can assign courses later.
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error loading courses:', error);
                coursesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading courses. Please refresh the page or assign courses later.
                    </div>
                `;
            });
    }

    function renderOptionsForRegistration() {
        const optionsContainer = document.getElementById('optionsContainer');

        if (availableOptions.length === 0) {
            optionsContainer.innerHTML = `
                <div class="alert alert-warning text-center">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <h6>No Options Available</h6>
                    <p class="mb-0">No options are available.</p>
                    <small class="text-muted">Please contact your administrator to create options first.</small>
                    <br><br>
                    <button class="btn btn-primary btn-sm" onclick="loadOptionsForRegistration()">
                        <i class="fas fa-refresh me-1"></i>Retry Loading Options
                    </button>
                </div>
            `;
            return;
        }

        let html = `
            <div class="row g-2">
        `;

        availableOptions.forEach(option => {
            html += `
                <div class="col-md-4">
                    <div class="form-check">
                        <input class="form-check-input option-checkbox" type="checkbox"
                               value="${option.id}" id="option_reg_${option.id}"
                               onchange="updateSelectedOptions()">
                        <label class="form-check-label fw-bold" for="option_reg_${option.id}">
                            <i class="fas fa-list me-1"></i>
                            ${option.name}
                        </label>
                    </div>
                </div>
            `;
        });

        html += `
            </div>
        `;

        optionsContainer.innerHTML = html;
        updateSelectedOptions();
    }

    function renderCoursesForRegistration() {
        const coursesContainer = document.getElementById('coursesContainer');

        if (availableCourses.length === 0) {
            coursesContainer.innerHTML = `
                <div class="alert alert-info text-center">
                    <i class="fas fa-info-circle me-2"></i>
                    <h6>No Unassigned Courses Available</h6>
                    <p class="mb-0">All courses are already assigned to lecturers.</p>
                    <small class="text-muted">You can assign courses later using the course assignment feature.</small>
                </div>
            `;
            return;
        }

        // Group courses by status for better organization
        const activeCourses = availableCourses.filter(course => course.status === 'active');
        const inactiveCourses = availableCourses.filter(course => course.status !== 'active');

        let html = '';

        if (activeCourses.length > 0) {
            html += `
                <div class="mb-3">
                    <h6 class="text-success mb-2">
                        <i class="fas fa-check-circle me-2"></i>Available Courses (${activeCourses.length})
                    </h6>
                    ${activeCourses.map(course => renderCourseItem(course)).join('')}
                </div>
            `;
        }

        if (inactiveCourses.length > 0) {
            html += `
                <div class="mb-2">
                    <h6 class="text-muted mb-2">
                        <i class="fas fa-pause-circle me-2"></i>Inactive Courses (${inactiveCourses.length})
                    </h6>
                    ${inactiveCourses.map(course => renderCourseItem(course)).join('')}
                </div>
            `;
        }

        coursesContainer.innerHTML = html;
        updateSelectedCourses();
    }

    function renderCourseItem(course) {
        const isActive = course.status === 'active';
        return `
            <div class="course-item ${!isActive ? 'opacity-75' : ''}">
                <div class="form-check mb-1">
                    <input class="form-check-input course-checkbox" type="checkbox"
                           value="${course.id}" id="course_reg_${course.id}"
                           onchange="updateSelectedCourses()"
                           ${!isActive ? 'disabled' : ''}>
                    <label class="form-check-label fw-bold" for="course_reg_${course.id}">
                        ${course.course_name}
                        <span class="text-muted">(${course.course_code})</span>
                        ${!isActive ? '<small class="text-muted ms-2">(Inactive)</small>' : ''}
                    </label>
                </div>
                <div class="course-info">
                    <div class="row g-1">
                        <div class="col-auto">
                            <i class="fas fa-graduation-cap text-primary"></i>
                            <small class="ms-1">${course.credits || 'N/A'} credits</small>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock text-info"></i>
                            <small class="ms-1">${course.duration_hours || 'N/A'} hours</small>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-${isActive ? 'success' : 'secondary'}">
                                ${course.status || 'unknown'}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function updateSelectedOptions() {
        const checkboxes = document.querySelectorAll('.option-checkbox:checked');
        const selectedCount = document.getElementById('selectedOptionsCount');
        const selectedInputs = document.getElementById('selectedOptionsInputs');

        selectedOptionsForRegistration = Array.from(checkboxes).map(cb => cb.value);

        if (selectedCount) {
            selectedCount.textContent = selectedOptionsForRegistration.length;
        }

        // Update hidden inputs for form submission
        if (selectedInputs) {
            selectedInputs.innerHTML = selectedOptionsForRegistration.map(optionId => `
                <input type="hidden" name="selected_options[]" value="${optionId}">
            `).join('');
        }
    }

    function updateSelectedCourses() {
        const checkboxes = document.querySelectorAll('.course-checkbox:checked');
        const selectedCount = document.getElementById('selectedCoursesCount');
        const selectedInputs = document.getElementById('selectedCoursesInputs');

        selectedCoursesForRegistration = Array.from(checkboxes).map(cb => cb.value);

        if (selectedCount) {
            selectedCount.textContent = selectedCoursesForRegistration.length;
        }

        // Update hidden inputs for form submission
        if (selectedInputs) {
            selectedInputs.innerHTML = selectedCoursesForRegistration.map(courseId => `
                <input type="hidden" name="selected_courses[]" value="${courseId}">
            `).join('');
        }
    }

    // Real-time validation feedback
    document.addEventListener('DOMContentLoaded', function() {
        // Add real-time validation for phone number
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                const value = this.value;
                const feedback = document.getElementById('phone-feedback') || createFeedbackElement('phone');

                if (value === '') {
                    feedback.textContent = '';
                    feedback.className = 'validation-message info';
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (/^\d{10}$/.test(value)) {
                    feedback.textContent = '✓ Valid phone number';
                    feedback.className = 'validation-message success';
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    feedback.textContent = 'Phone must be exactly 10 digits';
                    feedback.className = 'validation-message error';
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        }

        // Add validation for ID number on blur (when user leaves the field)
        const idInput = document.getElementById('id_number');
        const idCounter = document.getElementById('id-counter');
        if (idInput) {
            idInput.addEventListener('blur', function() {
                const value = this.value;
                const feedback = document.getElementById('id-feedback') || createFeedbackElement('id_number');

                if (value === '') {
                    // Hide counter and clear feedback for empty field
                    if (idCounter) idCounter.style.display = 'none';
                    feedback.textContent = '';
                    feedback.className = 'validation-message info';
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (value.length === 16) {
                    // Hide counter and show success for valid length
                    if (idCounter) idCounter.style.display = 'none';
                    feedback.textContent = '✓ ID number is valid';
                    feedback.className = 'validation-message success';
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else if (value.length < 16) {
                    // Show counter and error message for insufficient characters
                    if (idCounter) {
                        idCounter.textContent = `${value.length}/16`;
                        idCounter.className = 'form-text text-danger';
                        idCounter.style.display = 'block';
                    }
                    feedback.textContent = `ID must be exactly 16 characters (${value.length}/16)`;
                    feedback.className = 'validation-message error';
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                } else {
                    // Hide counter and show error for too many characters
                    if (idCounter) idCounter.style.display = 'none';
                    feedback.textContent = 'ID must be exactly 16 characters - Too long!';
                    feedback.className = 'validation-message error';
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });

            // Clear validation message and hide counter when user starts typing again
            idInput.addEventListener('focus', function() {
                const feedback = document.getElementById('id-feedback');
                if (feedback) {
                    feedback.textContent = '';
                    feedback.className = 'validation-message info';
                }
                if (idCounter) {
                    idCounter.style.display = 'none';
                }
                this.classList.remove('is-valid', 'is-invalid');
            });
        }

        // Add real-time validation for date of birth
        const dobInput = document.getElementById('dob');
        if (dobInput) {
            dobInput.addEventListener('change', function() {
                const value = this.value;
                const feedback = document.getElementById('dob-feedback') || createFeedbackElement('dob');

                if (value === '') {
                    feedback.textContent = '';
                    feedback.className = 'validation-message info';
                    this.classList.remove('is-valid', 'is-invalid');
                } else {
                    const birthDate = new Date(value);
                    const today = new Date();
                    const age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();

                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }

                    if (age >= 21 && age <= 100) {
                        feedback.textContent = `✓ Valid age: ${age} years old`;
                        feedback.className = 'validation-message success';
                        this.classList.remove('is-invalid');
                        this.classList.add('is-valid');
                    } else if (age < 21) {
                        feedback.textContent = 'Must be at least 21 years old';
                        feedback.className = 'validation-message error';
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    } else {
                        feedback.textContent = 'Age cannot exceed 100 years';
                        feedback.className = 'validation-message error';
                        this.classList.remove('is-valid');
                        this.classList.add('is-invalid');
                    }
                }
            });
        }

        // Load options and courses on page load
        loadOptionsForRegistration();
        loadCoursesForRegistration();
    });

    // Helper function to create feedback elements
    function createFeedbackElement(fieldName) {
        const element = document.createElement('div');
        element.id = fieldName + '-feedback';
        element.className = 'validation-message info';
        element.style.fontSize = '0.8rem';
        element.style.marginTop = '0.25rem';

        const field = document.getElementById(fieldName);
        if (field && field.parentNode) {
            field.parentNode.appendChild(element);
        }

        return element;
    }

    // Form validation before submission
    document.getElementById('lecturerRegistrationForm')?.addEventListener('submit', function(e) {
        // Ensure selections are updated before validation
        updateSelectedOptions();
        updateSelectedCourses();

        const firstName = document.querySelector('[name="first_name"]').value.trim();
        const lastName = document.querySelector('[name="last_name"]').value.trim();
        const gender = document.querySelector('[name="gender"]').value;
        const dob = document.querySelector('[name="dob"]').value;
        const idNumber = document.querySelector('[name="id_number"]').value.trim();
        const email = document.querySelector('[name="email"]').value.trim();
        const phone = document.querySelector('[name="phone"]').value.trim();
        const education = document.querySelector('[name="education_level"]').value;

        // Validate selected options (required)
        const selectedOptions = document.querySelectorAll('.option-checkbox:checked');
        const availableOptionElements = document.querySelectorAll('.option-checkbox');

        if (availableOptionElements.length > 0 && selectedOptions.length === 0) {
            alert('Please select at least one option for the lecturer to access.');
            e.preventDefault();
            return false;
        }

        if (availableOptionElements.length === 0) {
            // No options available - show warning but allow submission
            if (!confirm('No options are available for assignment. The lecturer will be created without option access. Continue?')) {
                e.preventDefault();
                return false;
            }
        }

        // Basic validation - ensure option IDs are numeric
        for (let checkbox of selectedOptions) {
            if (isNaN(checkbox.value) || parseInt(checkbox.value) <= 0) {
                alert('Invalid option selection detected. Please refresh the page and try again.');
                e.preventDefault();
                return false;
            }
        }

        // Validate selected courses if any
        const selectedCourses = document.querySelectorAll('.course-checkbox:checked');
        if (selectedCourses.length > 0) {
            // Basic validation - ensure course IDs are numeric
            for (let checkbox of selectedCourses) {
                if (isNaN(checkbox.value) || parseInt(checkbox.value) <= 0) {
                    alert('Invalid course selection detected. Please refresh the page and try again.');
                    e.preventDefault();
                    return false;
                }
            }
        }

        // Required field validation
        if (!firstName || !lastName || !gender || !dob || !idNumber || !email || !education) {
            alert('Please fill in all required fields.');
            e.preventDefault();
            return false;
        }

        // Email validation
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            alert('Please enter a valid email address.');
            e.preventDefault();
            return false;
        }

        // Phone number validation - exactly 10 digits only
        if (phone) {
            const phoneRegex = /^\d{10}$/;
            if (!phoneRegex.test(phone)) {
                alert('Phone number must be exactly 10 digits only (no spaces, dashes, or country codes).');
                e.preventDefault();
                return false;
            }
        }

        // ID number validation - exactly 16 characters
        if (idNumber.length !== 16) {
            alert('ID Number must be exactly 16 characters long.');
            e.preventDefault();
            return false;
        }

        // Date of birth validation - must be at least 21 years old
        if (dob) {
            const birthDate = new Date(dob);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();

            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            if (age < 21) {
                alert('Lecturer must be at least 21 years old. Please select a valid date of birth.');
                e.preventDefault();
                return false;
            }

            if (age > 100) {
                alert('Please enter a valid date of birth. Age cannot exceed 100 years.');
                e.preventDefault();
                return false;
            }
        }

        // Set loading state
        const addBtn = document.getElementById('addBtn');
        if (addBtn) {
            addBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Registering...';
            addBtn.disabled = true;
        }

        return true;
    });
    </script>
    <?php endif; ?>
</body>
</html>