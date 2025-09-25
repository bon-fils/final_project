<?php
/**
 * HOD Assignment Frontend
 * Handles the presentation layer for HOD assignment functionality
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

// Generate CSRF token for form submissions
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}
$csrf_token = generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assign HOD | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <style>
        :root {
            --primary-color: #0066cc;
            --primary-dark: #003366;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .sidebar {
            background: var(--primary-dark);
            color: white;
            height: 100vh;
            position: fixed;
            width: 280px;
        }

        .main-content {
            margin-left: 280px;
            padding: 20px;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        .btn {
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .stats-card {
            text-align: center;
            padding: 20px;
        }

        .stats-card i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stats-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .assignment-card {
            border-left: 4px solid var(--primary-color);
        }

        .assignment-card.assigned {
            border-left-color: var(--success-color);
        }

        .assignment-card.unassigned {
            border-left-color: var(--warning-color);
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }

        .loading-overlay {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(5px);
        }
    </style>
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay position-fixed top-0 start-0 w-100 h-100 d-none justify-content-center align-items-center" 
         id="loadingOverlay" style="z-index: 9999;">
        <div class="text-center">
            <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
            <p class="text-muted">Processing your request...</p>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="p-4 text-center">
            <img src="RP_Logo.jpeg" alt="RP Logo" class="rounded-circle mb-3" style="width: 80px; height: 80px;">
            <h5 class="mb-0">Admin Dashboard</h5>
        </div>
        
        <nav class="nav flex-column p-3">
            <a class="nav-link text-white mb-2" href="admin-dashboard.php">
                <i class="fas fa-home me-2"></i>Dashboard
            </a>
            <a class="nav-link text-white mb-2" href="manage-departments.php">
                <i class="fas fa-building me-2"></i>Departments
            </a>
            <a class="nav-link text-white mb-2 active" href="assign-hod.php">
                <i class="fas fa-user-tie me-2"></i>Assign HOD
            </a>
            <a class="nav-link text-white mb-2" href="admin-reports.php">
                <i class="fas fa-chart-bar me-2"></i>Reports
            </a>
            <a class="nav-link text-white" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1 text-primary">
                    <i class="fas fa-user-tie me-2"></i>Assign Head of Department
                </h1>
                <p class="text-muted mb-0">Manage department leadership assignments</p>
            </div>
            <button class="btn btn-primary" onclick="loadData()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
            </button>
        </div>

        <!-- Alert Container -->
        <div id="alertContainer"></div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-xl-3 col-lg-6">
                <div class="card stats-card bg-white">
                    <i class="fas fa-building text-primary"></i>
                    <h3 id="totalDepartments">0</h3>
                    <p class="text-muted">Total Departments</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="card stats-card bg-white">
                    <i class="fas fa-user-check text-success"></i>
                    <h3 id="assignedDepartments">0</h3>
                    <p class="text-muted">Assigned HODs</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="card stats-card bg-white">
                    <i class="fas fa-chalkboard-teacher text-info"></i>
                    <h3 id="totalLecturers">0</h3>
                    <p class="text-muted">Available Lecturers</p>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="card stats-card bg-white">
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                    <h3 id="unassignedDepartments">0</h3>
                    <p class="text-muted">Unassigned Departments</p>
                </div>
            </div>
        </div>

        <!-- Assignment Form -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-plus-circle me-2"></i>
                    HOD Assignment Form
                </h5>
            </div>
            <div class="card-body">
                <form id="assignHodForm">
                    <input type="hidden" id="departmentId" name="department_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="departmentSelect" class="form-label">Department *</label>
                            <select class="form-select" id="departmentSelect" name="department_id" required>
                                <option value="">-- Select Department --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lecturerSelect" class="form-label">Head of Department</label>
                            <select class="form-select" id="lecturerSelect" name="hod_id">
                                <option value="">-- Select Lecturer --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Current Assignment Info -->
                    <div class="alert alert-info" id="currentAssignmentInfo" style="display: none;">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Current Assignment:</strong> <span id="currentHodName"></span>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Assign HOD
                        </button>
                        <button type="button" class="btn btn-secondary" id="resetFormBtn">
                            <i class="fas fa-undo me-2"></i>Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Current Assignments -->
        <div class="card">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Current HOD Assignments
                </h5>
                <button class="btn btn-light btn-sm" id="refreshAssignments">
                    <i class="fas fa-sync-alt me-1"></i>Refresh
                </button>
            </div>
            <div class="card-body">
                <div id="assignmentsContainer" class="row g-3">
                    <!-- Assignments will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global variables
        let allDepartments = [];
        let allLecturers = [];

        // DOM Ready
        $(document).ready(function() {
            loadData();
            setupEventHandlers();
        });

        function setupEventHandlers() {
            // Department form submission
            $('#assignHodForm').on('submit', handleDepartmentSubmit);
            
            // Department selection change
            $('#departmentSelect').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const hodId = selectedOption.data('hod');
                const hodName = selectedOption.data('hod-name');

                if (hodId && hodName) {
                    $('#currentAssignmentInfo').show();
                    $('#currentHodName').text(hodName);
                    $('#lecturerSelect').val(hodId);
                } else {
                    $('#currentAssignmentInfo').hide();
                    $('#lecturerSelect').val('');
                }
            });

            // Reset form
            $('#resetFormBtn').on('click', function() {
                $('#assignHodForm')[0].reset();
                $('#currentAssignmentInfo').hide();
            });

            // Refresh assignments
            $('#refreshAssignments').on('click', loadAssignments);
        }

        function showAlert(type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show">
                    <i class="fas fa-${type === 'success' ? 'check' : 'exclamation-triangle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            $('#alertContainer').html(alertHtml);
            setTimeout(() => $('.alert').alert('close'), 5000);
        }

        function showLoading() {
            $('#loadingOverlay').removeClass('d-none').addClass('d-flex');
        }

        function hideLoading() {
            $('#loadingOverlay').removeClass('d-flex').addClass('d-none');
        }

        function loadData() {
            showLoading();
            
            // Load all data in parallel
            Promise.all([
                loadDepartments(),
                loadLecturers(),
                loadStatistics(),
                loadAssignments()
            ])
            .then(() => {
                hideLoading();
            })
            .catch(error => {
                hideLoading();
                showAlert('danger', 'Failed to load data: ' + error);
            });
        }

        function loadDepartments() {
            return new Promise((resolve, reject) => {
                $.get('api/assign-hod-api.php?action=get_departments')
                    .done(function(response) {
                        if (response.status === 'success') {
                            allDepartments = response.data;
                            const select = $('#departmentSelect');
                            select.empty().append('<option value="">-- Select Department --</option>');

                            response.data.forEach(dept => {
                                const selected = dept.hod_name ? ' (Current HOD: ' + dept.hod_name + ')' : '';
                                select.append(`<option value="${dept.id}" data-hod="${dept.hod_id || ''}" data-hod-name="${dept.hod_name || ''}">${dept.dept_name}${selected}</option>`);
                            });
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        reject('Failed to load departments: ' + error);
                    });
            });
        }

        function loadLecturers() {
            return new Promise((resolve, reject) => {
                $.get('api/assign-hod-api.php?action=get_lecturers')
                    .done(function(response) {
                        if (response.status === 'success') {
                            allLecturers = response.data;
                            const select = $('#lecturerSelect');
                            select.empty().append('<option value="">-- Select Lecturer --</option>');

                            response.data.forEach(lecturer => {
                                select.append(`<option value="${lecturer.id}">${lecturer.full_name}</option>`);
                            });
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        reject('Failed to load lecturers: ' + error);
                    });
            });
        }

        function loadStatistics() {
            return new Promise((resolve, reject) => {
                $.get('api/assign-hod-api.php?action=get_assignment_stats')
                    .done(function(response) {
                        if (response.status === 'success') {
                            const data = response.data;
                            $('#totalDepartments').text(data.total_departments || 0);
                            $('#assignedDepartments').text(data.assigned_departments || 0);
                            $('#totalLecturers').text(data.total_lecturers || 0);
                            $('#unassignedDepartments').text(data.unassigned_departments || 0);
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        reject('Failed to load statistics: ' + error);
                    });
            });
        }

        function loadAssignments() {
            return new Promise((resolve, reject) => {
                $.get('api/assign-hod-api.php?action=get_departments')
                    .done(function(response) {
                        if (response.status === 'success') {
                            renderAssignments(response.data);
                            resolve(response.data);
                        } else {
                            reject(response.message);
                        }
                    })
                    .fail(function(xhr, status, error) {
                        reject('Failed to load assignments: ' + error);
                    });
            });
        }

        function renderAssignments(departments) {
            const container = $('#assignmentsContainer');
            container.empty();

            if (departments.length === 0) {
                container.html(`
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-building fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Departments Found</h5>
                        <p class="text-muted">No departments are available for HOD assignment.</p>
                    </div>
                `);
                return;
            }

            departments.forEach(dept => {
                const cardClass = dept.hod_id ? 'assignment-card assigned' : 'assignment-card unassigned';
                const statusIcon = dept.hod_id ? 'fas fa-user-check text-success' : 'fas fa-exclamation-triangle text-warning';
                const statusText = dept.hod_id ? 'Assigned' : 'Unassigned';

                const cardHtml = `
                    <div class="col-md-6 col-lg-4">
                        <div class="card ${cardClass} h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title text-primary mb-0">${dept.dept_name}</h6>
                                    <span class="badge ${dept.hod_id ? 'bg-success' : 'bg-warning'}">${statusText}</span>
                                </div>
                                
                                <div class="mb-3">
                                    <small class="text-muted">Current HOD:</small>
                                    <div class="${dept.hod_id ? 'text-success' : 'text-warning'}">
                                        ${dept.hod_name || 'Not Assigned'}
                                    </div>
                                </div>

                                <button class="btn btn-sm btn-outline-primary w-100 selectDepartment"
                                        data-id="${dept.id}"
                                        data-name="${dept.dept_name}"
                                        data-hod="${dept.hod_id || ''}"
                                        data-hod-name="${dept.hod_name || ''}">
                                    <i class="fas fa-edit me-1"></i>Select for Assignment
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                container.append(cardHtml);
            });

            // Add event listeners to select buttons
            $('.selectDepartment').on('click', function() {
                const deptId = $(this).data('id');
                const deptName = $(this).data('name');
                const hodId = $(this).data('hod');
                const hodName = $(this).data('hod-name');

                $('#departmentSelect').val(deptId);
                $('#lecturerSelect').val(hodId);

                if (hodId && hodName) {
                    $('#currentAssignmentInfo').show();
                    $('#currentHodName').text(hodName);
                } else {
                    $('#currentAssignmentInfo').hide();
                }

                // Scroll to form
                $('html, body').animate({
                    scrollTop: $('#assignHodForm').offset().top - 100
                }, 500);

                showAlert('info', `Selected department: ${deptName}`);
            });
        }

        function handleDepartmentSubmit(e) {
            e.preventDefault();

            const departmentId = $('#departmentSelect').val();
            const hodId = $('#lecturerSelect').val();

            if (!departmentId) {
                showAlert('warning', 'Please select a department');
                $('#departmentSelect').focus();
                return;
            }

            showLoading();
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.html();
            submitBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Assigning...').prop('disabled', true);

            $.post('api/assign-hod-api.php?action=assign_hod', {
                department_id: departmentId,
                hod_id: hodId,
                csrf_token: "<?php echo $csrf_token; ?>"
            })
            .done(function(response) {
                if (response.status === 'success') {
                    showAlert('success', response.message);
                    loadData(); // Refresh all data
                    $('#assignHodForm')[0].reset();
                    $('#currentAssignmentInfo').hide();
                } else {
                    showAlert('danger', response.message);
                }
            })
            .fail(function(xhr, status, error) {
                showAlert('danger', 'Failed to assign HOD. Please try again.');
            })
            .always(function() {
                hideLoading();
                submitBtn.html(originalText).prop('disabled', false);
            });
        }

        // Make loadData function available globally
        window.loadData = loadData;
    </script>
</body>
</html>