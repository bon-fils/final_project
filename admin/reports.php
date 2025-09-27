<?php
/**
 * Admin Reports - Enhanced Version
 * Comprehensive reporting system with advanced analytics
 */

session_start();
require_once "../config.php";
require_once "../session_check.php";
require_role(['admin']);

$page_title = "Reports & Analytics";
$current_page = "reports";
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
    <link href="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
        }

        /* Reuse sidebar and header styles from index.php */
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
        }

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
        }

        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 30px;
            min-height: calc(100vh - var(--header-height));
        }

        /* Enhanced Report Styles */
        .report-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .report-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .report-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #003366;
            margin: 0;
            display: flex;
            align-items: center;
        }

        .report-title i {
            margin-right: 12px;
            color: #0066cc;
        }

        .chart-container {
            position: relative;
            height: 350px;
            padding: 20px;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .analytics-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .metric-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .metric-item:last-child {
            border-bottom: none;
        }

        .metric-label {
            font-weight: 500;
            color: #6c757d;
        }

        .metric-value {
            font-size: 1.2rem;
            font-weight: 700;
            color: #003366;
        }

        .metric-change {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .metric-change.positive {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .metric-change.negative {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        /* Enhanced Filter Styles */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: #003366;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .filter-control {
            padding: 10px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .filter-control:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.1);
        }

        /* Tab Styles */
        .nav-tabs-enhanced {
            border: none;
            margin-bottom: 25px;
        }

        .nav-tabs-enhanced .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            padding: 12px 20px;
            font-weight: 600;
            color: #6c757d;
            background: rgba(0, 102, 204, 0.05);
            margin-right: 5px;
        }

        .nav-tabs-enhanced .nav-link.active {
            background: var(--primary-gradient);
            color: white;
        }

        .nav-tabs-enhanced .nav-link:hover {
            color: #0066cc;
            background: rgba(0, 102, 204, 0.1);
        }

        /* Table Styles */
        .table-enhanced {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }

        .table-enhanced thead th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            font-weight: 600;
            color: #003366;
            padding: 15px;
        }

        .table-enhanced tbody td {
            padding: 15px;
            vertical-align: middle;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-excellent {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }

        .status-good {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }

        .status-poor {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }

            .analytics-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }

            .chart-container {
                height: 250px;
            }
        }

        /* Loading Animation */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
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
    <main class="main-content">
        <!-- Statistics Overview -->
        <div class="analytics-grid">
            <div class="analytics-card">
                <h5 class="mb-4" style="color: #003366; font-weight: 600;">
                    <i class="fas fa-chart-line me-2"></i>Attendance Overview
                </h5>
                <div class="chart-container">
                    <canvas id="overviewChart"></canvas>
                </div>
            </div>

            <div class="analytics-card">
                <h5 class="mb-4" style="color: #003366; font-weight: 600;">
                    <i class="fas fa-building me-2"></i>Department Performance
                </h5>
                <div id="departmentMetrics">
                    <div class="loading-shimmer" style="height: 200px; border-radius: 8px;"></div>
                </div>
            </div>
        </div>

        <!-- Report Tabs -->
        <div class="report-container">
            <ul class="nav nav-tabs nav-tabs-enhanced" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance-reports" type="button" role="tab">
                        <i class="fas fa-calendar-check me-2"></i>Attendance Reports
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="hod-tab" data-bs-toggle="tab" data-bs-target="#hod-reports" type="button" role="tab">
                        <i class="fas fa-user-tie me-2"></i>HOD Reports
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="lecturer-tab" data-bs-toggle="tab" data-bs-target="#lecturer-reports" type="button" role="tab">
                        <i class="fas fa-chalkboard-teacher me-2"></i>Lecturer Reports
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#advanced-analytics" type="button" role="tab">
                        <i class="fas fa-chart-bar me-2"></i>Advanced Analytics
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="reportTabContent">
                <!-- Attendance Reports Tab -->
                <div class="tab-pane fade show active" id="attendance-reports" role="tabpanel">
                    <!-- Filters -->
                    <div class="filter-section">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label class="filter-label">Department</label>
                                <select id="deptFilter" class="filter-control">
                                    <option value="">All Departments</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Course</label>
                                <select id="courseFilter" class="filter-control" disabled>
                                    <option value="">All Courses</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Date Range</label>
                                <input type="text" id="dateRange" class="filter-control" placeholder="Select date range">
                            </div>
                            <div class="filter-group">
                                <label class="filter-label">Records per Page</label>
                                <select id="recordsPerPage" class="filter-control">
                                    <option value="25">25</option>
                                    <option value="50" selected>50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" onclick="applyFilters()">
                                <i class="fas fa-filter me-2"></i>Apply Filters
                            </button>
                            <button class="btn btn-outline-secondary" onclick="resetFilters()">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </div>

                    <!-- Reports Table -->
                    <div class="table-responsive">
                        <table class="table table-enhanced">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Department</th>
                                    <th>Course</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reportsTableBody">
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="paginationContainer" class="d-flex justify-content-center mt-4">
                        <!-- Pagination will be inserted here -->
                    </div>
                </div>

                <!-- HOD Reports Tab -->
                <div class="tab-pane fade" id="hod-reports" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-enhanced">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>HOD Name</th>
                                    <th>Students</th>
                                    <th>Courses</th>
                                    <th>Attendance Rate</th>
                                    <th>Performance</th>
                                </tr>
                            </thead>
                            <tbody id="hodReportsBody">
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <div class="spinner-border text-primary" role="status"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Advanced Analytics Tab -->
                <div class="tab-pane fade" id="advanced-analytics" role="tabpanel">
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="analytics-card">
                                <h6 class="mb-3">Attendance Trends</h6>
                                <div class="chart-container">
                                    <canvas id="trendsChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="analytics-card">
                                <h6 class="mb-3">Course Performance</h6>
                                <div class="chart-container">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- External Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker@3.1.0/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

    <!-- Shared Admin Scripts -->
    <?php include_once 'includes/admin-scripts.php'; ?>

    <script>
        let currentFilters = {};
        let currentPage = 1;
        let charts = {};

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadDepartments();
            initializeDateRangePicker();
            loadAnalytics();
            setupEventListeners();

            // Initialize tab change handlers
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const target = e.target.getAttribute('data-bs-target');
                    if (target === '#hod-reports') {
                        loadHODReports();
                    } else if (target === '#advanced-analytics') {
                        loadAdvancedAnalytics();
                    }
                });
            });
        });

        // Load departments for filter
        function loadDepartments() {
            $.getJSON('../api/assign-hod-api.php?action=get_departments', function(response) {
                const select = $('#deptFilter');
                select.empty().append('<option value="">All Departments</option>');

                if (response.status === 'success' && response.data) {
                    response.data.forEach(dept => {
                        select.append(`<option value="${dept.id}">${dept.name}</option>`);
                    });
                }
            });
        }

        // Initialize date range picker
        function initializeDateRangePicker() {
            $('#dateRange').daterangepicker({
                autoUpdateInput: false,
                locale: {
                    cancelLabel: 'Clear',
                    format: 'YYYY-MM-DD'
                },
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')]
                }
            });

            $('#dateRange').on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD') + ' to ' + picker.endDate.format('YYYY-MM-DD'));
            });

            $('#dateRange').on('cancel.daterangepicker', function(ev, picker) {
                $(this).val('');
            });
        }

        // Setup event listeners
        function setupEventListeners() {
            $('#deptFilter').change(function() {
                // Load courses for selected department
                const deptId = $(this).val();
                loadCourses(deptId);
            });
        }

        // Load courses for department
        function loadCourses(deptId) {
            const courseSelect = $('#courseFilter');
            courseSelect.prop('disabled', true);

            if (!deptId) {
                courseSelect.empty().append('<option value="">All Courses</option>');
                return;
            }

            $.getJSON('../admin-reports.php?ajax=1&action=get_courses', {
                department_id: deptId
            }, function(data) {
                courseSelect.empty().append('<option value="">All Courses</option>');
                data.forEach(function(course) {
                    courseSelect.append(`<option value="${course.id}">${course.name}</option>`);
                });
                courseSelect.prop('disabled', false);
            });
        }

        // Load analytics data
        function loadAnalytics() {
            $.getJSON('../admin-reports.php?ajax=1&action=get_analytics', function(data) {
                if (data && !data.error) {
                    renderAnalyticsCharts(data);
                    renderDepartmentMetrics(data.department_attendance || []);
                } else {
                    console.error('Analytics error:', data?.error);
                    showAlert('Failed to load analytics data', 'danger');
                }
            }).fail(function(xhr, status, error) {
                console.error('Failed to load analytics:', xhr, status, error);
                showAlert('Failed to load analytics', 'danger');
            });
        }

        // Render analytics charts
        function renderAnalyticsCharts(data) {
            // Overview chart
            const overviewCtx = document.getElementById('overviewChart').getContext('2d');
            if (charts.overview) charts.overview.destroy();

            charts.overview = new Chart(overviewCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent'],
                    datasets: [{
                        data: [
                            data.daily_trends?.reduce((sum, item) => sum + (item.present_count || 0), 0) || 0,
                            data.daily_trends?.reduce((sum, item) => sum + (item.absent_count || 0), 0) || 0
                        ],
                        backgroundColor: ['#28a745', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Overall Attendance Distribution'
                        }
                    }
                }
            });
        }

        // Render department metrics
        function renderDepartmentMetrics(departments) {
            const container = $('#departmentMetrics');
            container.empty();

            if (departments.length === 0) {
                container.html('<p class="text-muted text-center py-4">No department data available</p>');
                return;
            }

            departments.forEach(dept => {
                const performance = dept.attendance_rate >= 90 ? 'excellent' :
                                  dept.attendance_rate >= 75 ? 'good' : 'poor';

                container.append(`
                    <div class="metric-item">
                        <div>
                            <div class="metric-label">${dept.department}</div>
                            <div class="metric-value">${dept.attendance_rate}%</div>
                        </div>
                        <span class="status-badge status-${performance}">
                            ${performance.charAt(0).toUpperCase() + performance.slice(1)}
                        </span>
                    </div>
                `);
            });
        }

        // Apply filters
        function applyFilters() {
            const filters = {
                department_id: $('#deptFilter').val(),
                course_id: $('#courseFilter').val(),
                records_per_page: $('#recordsPerPage').val()
            };

            const dateRange = $('#dateRange').val();
            if (dateRange && dateRange.includes(' to ')) {
                const [startDate, endDate] = dateRange.split(' to ');
                filters.date_from = startDate;
                filters.date_to = endDate;
            }

            currentFilters = filters;
            currentPage = 1;
            loadReports(filters, 1);
        }

        // Load reports with pagination
        function loadReports(filters = {}, page = 1) {
            $('#reportsTableBody').html(`
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                    </td>
                </tr>
            `);

            const queryParams = $.param({...filters, page, limit: filters.records_per_page || 50});

            $.getJSON(`../admin-reports.php?ajax=1&action=get_paginated_reports&${queryParams}`, function(response) {
                if (response.error) {
                    $('#reportsTableBody').html(`
                        <tr>
                            <td colspan="6" class="text-center py-4 text-danger">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                <div>${response.error}</div>
                            </td>
                        </tr>
                    `);
                } else {
                    displayReports(response.data);
                    renderPagination(response.pagination);
                }
            });
        }

        // Display reports in table
        function displayReports(reports) {
            const tbody = $('#reportsTableBody');
            tbody.empty();

            if (reports.length === 0) {
                tbody.html(`
                    <tr>
                        <td colspan="6" class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No Records Found</h5>
                            <p class="text-muted mb-0">Try adjusting your filters</p>
                        </td>
                    </tr>
                `);
                return;
            }

            reports.forEach(report => {
                const statusBadge = report.status === 'present'
                    ? '<span class="status-badge status-excellent">Present</span>'
                    : '<span class="status-badge status-poor">Absent</span>';

                tbody.append(`
                    <tr>
                        <td>${report.first_name} ${report.last_name}</td>
                        <td>${report.department_name}</td>
                        <td>${report.course_name || 'N/A'}</td>
                        <td>${report.attendance_date}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewDetails('${report.id}')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `);
            });
        }

        // Render pagination
        function renderPagination(pagination) {
            const { page, pages, total } = pagination;
            currentPage = page;

            let paginationHtml = `
                <nav aria-label="Reports pagination">
                    <ul class="pagination">
                        <li class="page-item ${page <= 1 ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="changePage(${page - 1})">Previous</a>
                        </li>
            `;

            for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) {
                paginationHtml += `
                    <li class="page-item ${i === page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
                    </li>
                `;
            }

            paginationHtml += `
                        <li class="page-item ${page >= pages ? 'disabled' : ''}">
                            <a class="page-link" href="#" onclick="changePage(${page + 1})">Next</a>
                        </li>
                    </ul>
                    <div class="text-muted mt-2">
                        Showing page ${page} of ${pages} (${total} total records)
                    </div>
                </nav>
            `;

            $('#paginationContainer').html(paginationHtml);
        }

        // Change page
        function changePage(newPage) {
            if (newPage >= 1) {
                loadReports(currentFilters, newPage);
            }
        }

        // Reset filters
        function resetFilters() {
            $('#deptFilter').val('');
            $('#courseFilter').val('').prop('disabled', true);
            $('#dateRange').val('');
            $('#recordsPerPage').val('50');
            currentFilters = {};
            currentPage = 1;
            loadReports({}, 1);
        }

        // Load HOD reports
        function loadHODReports() {
            $.getJSON('../admin-reports.php?ajax=1&action=get_analytics', function(data) {
                const tbody = $('#hodReportsBody');
                tbody.empty();

                if (data.hod_reports && data.hod_reports.length > 0) {
                    data.hod_reports.forEach(hod => {
                        const performance = hod.attendance_records > 0 ?
                            (hod.attendance_records / hod.students_count * 100) : 0;
                        const performanceClass = performance >= 80 ? 'excellent' :
                                              performance >= 60 ? 'good' : 'poor';

                        tbody.append(`
                            <tr>
                                <td>${hod.department_name}</td>
                                <td>${hod.hod_name || 'Not Assigned'}</td>
                                <td>${hod.students_count}</td>
                                <td>${hod.courses_count}</td>
                                <td>${Math.round(performance)}%</td>
                                <td>
                                    <span class="status-badge status-${performanceClass}">
                                        ${performanceClass.charAt(0).toUpperCase() + performanceClass.slice(1)}
                                    </span>
                                </td>
                            </tr>
                        `);
                    });
                } else {
                    tbody.html('<tr><td colspan="6" class="text-center py-4">No HOD reports available</td></tr>');
                }
            });
        }

        // Load advanced analytics
        function loadAdvancedAnalytics() {
            $.getJSON('../admin-reports.php?ajax=1&action=get_analytics', function(data) {
                renderAdvancedCharts(data);
            });
        }

        // Render advanced charts
        function renderAdvancedCharts(data) {
            // Trends chart
            const trendsCtx = document.getElementById('trendsChart').getContext('2d');
            if (charts.trends) charts.trends.destroy();

            const trendLabels = data.daily_trends?.map(item => item.date) || [];
            const presentData = data.daily_trends?.map(item => item.present_count) || [];
            const absentData = data.daily_trends?.map(item => item.absent_count) || [];

            charts.trends = new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: trendLabels,
                    datasets: [{
                        label: 'Present',
                        data: presentData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Absent',
                        data: absentData,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Performance chart
            const performanceCtx = document.getElementById('performanceChart').getContext('2d');
            if (charts.performance) charts.performance.destroy();

            const courseLabels = data.course_performance?.slice(0, 5).map(item => item.course_name) || [];
            const performanceData = data.course_performance?.slice(0, 5).map(item => item.attendance_rate) || [];

            charts.performance = new Chart(performanceCtx, {
                type: 'bar',
                data: {
                    labels: courseLabels,
                    datasets: [{
                        label: 'Attendance Rate (%)',
                        data: performanceData,
                        backgroundColor: performanceData.map(rate =>
                            rate >= 90 ? '#28a745' :
                            rate >= 75 ? '#ffc107' : '#dc3545'
                        )
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Export functions
        function exportAllReports() {
            if (Object.keys(currentFilters).length === 0) {
                showAlert('Please apply filters first to export data', 'warning');
                return;
            }

            const queryParams = $.param({
                ...currentFilters,
                export: 'excel',
                ajax: 1,
                action: 'export_reports'
            });

            window.open(`../admin-reports.php?${queryParams}`, '_blank');
        }

        // View details
        function viewDetails(recordId) {
            showAlert(`View details for record ${recordId}`, 'info');
        }

        // Refresh data
        function refreshData() {
            loadAnalytics();
            loadReports(currentFilters, currentPage);
            showAlert('Data refreshed successfully', 'success');
        }

        // Show alert helper
        function showAlert(message, type = 'info') {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 90px; right: 20px; z-index: 9999; min-width: 300px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;

            document.querySelectorAll('.alert').forEach(alert => alert.remove());
            document.body.insertAdjacentHTML('beforeend', alertHtml);

            setTimeout(() => {
                document.querySelector('.alert')?.remove();
            }, 5000);
        }

        // Load initial reports
        loadReports();
    </script>
</body>
</html>