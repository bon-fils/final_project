<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Lecturer Attendance Reports | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="css/attendance-reports.css" rel="stylesheet" />
    <style>
        :root {
            /* Primary Brand Colors - RP Blue with Modern Palette */
            --primary-color: #0066cc;
            --primary-dark: #003366;
            --primary-light: #e6f0ff;
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);

            /* Status Colors - Enhanced Contrast and Modern */
            --success-color: #10b981;
            --success-light: #d1fae5;
            --success-dark: #047857;
            --success-gradient: linear-gradient(135deg, #10b981 0%, #047857 100%);

            --danger-color: #ef4444;
            --danger-light: #fee2e2;
            --danger-dark: #dc2626;
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);

            --warning-color: #f59e0b;
            --warning-light: #fef3c7;
            --warning-dark: #d97706;
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);

            --info-color: #06b6d4;
            --info-light: #cffafe;
            --info-dark: #0891b2;
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);

            /* Layout Variables */
            --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
            --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background: #0066cc;
            min-height: 100vh;
            margin: 0;
            position: relative;
            overflow-x: hidden;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%);
            border-right: 1px solid rgba(0, 102, 204, 0.1);
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(0, 102, 204, 0.1);
        }

        .sidebar .logo {
            background: #000000;
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
            color: #000000;
            text-decoration: none;
            border-radius: 0 25px 25px 0;
            margin: 0 0 2px 0;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover {
            background: rgba(0, 102, 204, 0.08);
            color: #000000;
            border-left-color: #0066cc;
            transform: translateX(8px);
            box-shadow: 2px 0 8px rgba(0, 102, 204, 0.15);
        }

        .sidebar-nav a.active {
            background: rgba(0, 102, 204, 0.1);
            color: #000000;
            border-left-color: #0066cc;
            font-weight: 600;
        }

        .sidebar-nav a i {
            margin-right: 12px;
            width: 18px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            transition: var(--transition);
        }

        .topbar {
            margin-left: 280px;
            background: rgba(255,255,255,0.95);
            padding: 20px 30px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: var(--transition);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header {
            background: linear-gradient(135deg, #f8fafc 0%, #e0f2fe 100%);
            border-bottom: 2px solid #0ea5e9;
            margin-bottom: 30px;
            padding: 30px 0;
            text-align: center;
        }

        .page-header h1 {
            color: #000000;
            margin: 0;
            font-weight: 700;
            font-size: 2.5rem;
        }

        .card {
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            margin-bottom: 25px;
            border: 2px solid #000000;
            overflow: hidden;
            background: #ffffff;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-heavy);
        }

        .card-header {
            background: #000000;
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 20px 25px;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: #000000;
            border: none;
        }

        .btn-primary:hover {
            background: #333333;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            transition: var(--transition);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 51, 102, 0.25);
        }

        .table {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .table th {
            background-color: #000000;
            font-weight: 600;
            border: none;
            color: #ffffff;
        }

        .table td {
            border-color: #e9ecef;
            vertical-align: middle;
        }

        .badge {
            font-weight: 600;
            border-radius: 6px;
            padding: 6px 12px;
        }

        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 15px 20px;
        }

        .alert i {
            margin-right: 10px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 102, 204, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .loading-overlay.d-none {
            display: none !important;
        }

        .simple-bar-chart {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            height: 220px;
            padding: 20px 10px;
            gap: 6px;
            overflow-x: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) transparent;
        }

        .simple-bar-chart::-webkit-scrollbar {
            height: 4px;
        }

        .simple-bar-chart::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 2px;
        }

        .simple-bar-chart::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 2px;
        }

        .bar-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 45px;
            max-width: 60px;
        }

        .bar-container {
            width: 100%;
            height: 160px;
            background: rgba(0,0,0,0.08);
            border-radius: 6px 6px 0 0;
            position: relative;
            overflow: hidden;
            margin-bottom: 10px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .bar-fill {
            width: 100%;
            position: absolute;
            bottom: 0;
            border-radius: 6px 6px 0 0;
            transition: height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }

        .bar-fill:hover {
            opacity: 0.9;
        }

        .bar-label {
            font-size: 0.75rem;
            color: #000000;
            font-weight: 600;
            text-align: center;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .bar-value {
            font-size: 0.85rem;
            font-weight: 700;
            color: #000000;
            background: rgba(0, 0, 0, 0.1);
            padding: 2px 6px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.2);
        }

        .attendance-bar {
            width: 100px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 0 10px;
        }

        .attendance-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.6s ease;
        }

        .attendance-fill.excellent {
            background: #10b981;
        }

        .attendance-fill.good {
            background: #f59e0b;
        }

        .attendance-fill.poor {
            background: #ef4444;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .status-badge.bg-success {
            background: #10b981 !important;
        }

        .status-badge.bg-danger {
            background: #ef4444 !important;
        }

        .status-badge.bg-warning {
            background: #f59e0b !important;
        }

        .filter-controls {
            background: rgba(255,255,255,0.8);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            backdrop-filter: blur(10px);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #000000;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar, .topbar, .main-content {
                margin-left: 0 !important;
                width: 100%;
            }
            .sidebar {
                position: fixed;
                width: 100%;
                height: auto;
                display: block;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                border-radius: 0 0 var(--border-radius) var(--border-radius);
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

            .main-content {
                padding: 20px;
            }

            .mobile-menu-toggle {
                display: block !important;
                position: fixed;
                top: 20px;
                left: 20px;
                background: var(--primary-color);
                color: white;
                border: none;
                border-radius: 8px;
                width: 50px;
                height: 50px;
                z-index: 1001;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
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
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
    </style>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle d-lg-none" onclick="toggleSidebar()" aria-label="Toggle navigation menu">
        <i class="fas fa-bars" aria-hidden="true"></i>
    </button>

    <!-- Include Lecturer Sidebar -->
    <?php include 'includes/lecturer-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex align-items-center justify-content-center">
                <img src="RP_Logo.jpeg" alt="Rwanda Polytechnic Logo" style="height: 60px; width: auto; margin-right: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'">
                <h1>Lecturer Attendance Reports</h1>
            </div>
        </div>

        <div class="topbar">
            <div class="d-flex align-items-center justify-content-end">
                <div class="d-flex gap-2 align-items-center">
                    <div class="badge bg-primary fs-6 px-3 py-2">
                        <i class="fas fa-clock me-1"></i>Live Updates
                    </div>
                    <div class="badge bg-success fs-6 px-3 py-2">
                        <i class="fas fa-chalkboard-teacher me-1"></i>Lecturer
                    </div>
                    <button class="btn btn-outline-primary btn-sm" id="refreshReports" title="Refresh Report Data">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>

        <div class="loading-overlay d-none" id="loadingOverlay">
            <div class="text-center text-white">
                <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <h5 class="mb-2" id="loadingTitle">Loading Reports</h5>
                <p class="mb-0" id="loadingText">Please wait while we fetch the latest data...</p>
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertBox" class="mb-4"></div>

        <!-- Filter Controls -->
        <div class="filter-controls">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="reportType" class="form-label fw-bold" style="color: #000000;">Report Type</label>
                    <select class="form-select" id="reportType" onchange="updateFilters()" style="color: #000000;">
                        <option value="course" style="color: #000000;">Course Report</option>
                        <option value="class" style="color: #000000;">Class Report</option>
                        <option value="department" style="color: #000000;">Department Report</option>
                    </select>
                </div>
                <div class="col-md-3" id="departmentFilter" style="display: none;">
                    <label for="departmentSelect" class="form-label fw-bold" style="color: #000000;">Department</label>
                    <select class="form-select" id="departmentSelect" style="color: #000000;">
                        <option value="" style="color: #000000;">Select Department</option>
                        <option value="1" style="color: #000000;">Computer Science</option>
                        <option value="2" style="color: #000000;">Information Technology</option>
                        <option value="3" style="color: #000000;">Electrical Engineering</option>
                        <option value="4" style="color: #000000;">Mechanical Engineering</option>
                    </select>
                </div>
                <div class="col-md-3" id="classFilter" style="display: none;">
                    <label for="classSelect" class="form-label fw-bold" style="color: #000000;">Class/Year</label>
                    <select class="form-select" id="classSelect" style="color: #000000;">
                        <option value="" style="color: #000000;">Select Class</option>
                        <option value="1" style="color: #000000;">Year 1</option>
                        <option value="2" style="color: #000000;">Year 2</option>
                        <option value="3" style="color: #000000;">Year 3</option>
                        <option value="4" style="color: #000000;">Year 4</option>
                    </select>
                </div>
                <div class="col-md-3" id="courseFilter">
                    <label for="courseSelect" class="form-label fw-bold" style="color: #000000;">Course</label>
                    <select class="form-select" id="courseSelect" style="color: #000000;">
                        <option value="" style="color: #000000;">Select Course</option>
                        <option value="1" style="color: #000000;">Introduction to Programming</option>
                        <option value="2" style="color: #000000;">Data Structures</option>
                        <option value="3" style="color: #000000;">Database Systems</option>
                        <option value="4" style="color: #000000;">Web Development</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mt-2">
                <div class="col-md-3">
                    <label for="startDate" class="form-label fw-bold" style="color: #000000;">Start Date</label>
                    <input type="date" class="form-control" id="startDate" style="color: #000000;">
                </div>
                <div class="col-md-3">
                    <label for="endDate" class="form-label fw-bold" style="color: #000000;">End Date</label>
                    <input type="date" class="form-control" id="endDate" style="color: #000000;">
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button class="btn btn-primary me-2" onclick="generateReport()">
                        <i class="fas fa-search"></i>Generate Report
                    </button>
                    <button class="btn btn-success me-2" onclick="exportToCSV()">
                        <i class="fas fa-download"></i>Export CSV
                    </button>
                    <button class="btn btn-info" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i>Export PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Report Content -->
        <div id="reportContent">
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h5 style="color: #000000;">Select Filters to Generate Report</h5>
                <p class="mb-0" style="color: #000000;">Choose your report type and parameters above to view attendance data.</p>
            </div>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i>Student Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Demo data
        const demoData = {
            departments: [
                {id: 1, name: 'Computer Science'},
                {id: 2, name: 'Information Technology'},
                {id: 3, name: 'Electrical Engineering'},
                {id: 4, name: 'Mechanical Engineering'}
            ],
            courses: [
                {id: 1, name: 'Introduction to Programming', code: 'CS101'},
                {id: 2, name: 'Data Structures', code: 'CS201'},
                {id: 3, name: 'Database Systems', code: 'CS301'},
                {id: 4, name: 'Web Development', code: 'IT201'}
            ],
            students: [
                {id: 1, name: 'John Doe', reg_no: 'STU001', department: 'Computer Science', year: 1, attendance: 85},
                {id: 2, name: 'Jane Smith', reg_no: 'STU002', department: 'Computer Science', year: 1, attendance: 92},
                {id: 3, name: 'Bob Johnson', reg_no: 'STU003', department: 'Information Technology', year: 2, attendance: 78},
                {id: 4, name: 'Alice Brown', reg_no: 'STU004', department: 'Computer Science', year: 2, attendance: 88},
                {id: 5, name: 'Charlie Wilson', reg_no: 'STU005', department: 'Electrical Engineering', year: 3, attendance: 65},
                {id: 6, name: 'Diana Davis', reg_no: 'STU006', department: 'Computer Science', year: 3, attendance: 95},
                {id: 7, name: 'Eve Miller', reg_no: 'STU007', department: 'Information Technology', year: 1, attendance: 82},
                {id: 8, name: 'Frank Garcia', reg_no: 'STU008', department: 'Computer Science', year: 2, attendance: 76}
            ]
        };

        // Global variables
        let currentReportData = null;

        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        }

        // Update filter visibility based on report type
        function updateFilters() {
            const reportType = document.getElementById('reportType').value;
            const departmentFilter = document.getElementById('departmentFilter');
            const classFilter = document.getElementById('classFilter');
            const courseFilter = document.getElementById('courseFilter');

            // Hide all filters first
            departmentFilter.style.display = 'none';
            classFilter.style.display = 'none';
            courseFilter.style.display = 'block'; // Always show course filter for lecturers

            // Show relevant filters based on report type
            switch(reportType) {
                case 'department':
                    departmentFilter.style.display = 'block';
                    break;
                case 'class':
                    classFilter.style.display = 'block';
                    break;
            }
        }

        // Generate demo report
        function generateReport() {
            const reportType = document.getElementById('reportType').value;
            const courseId = document.getElementById('courseSelect').value;
            const departmentId = document.getElementById('departmentSelect').value;
            const classId = document.getElementById('classSelect').value;

            if (!courseId && reportType === 'course') {
                showAlert('Please select a course', 'warning');
                return;
            }

            // Prevent multiple simultaneous requests
            if (document.getElementById('loadingOverlay').classList.contains('d-none') === false) {
                return; // Already loading
            }

            // Clear previous alerts
            document.getElementById('alertBox').innerHTML = '';

            // Show loading
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.classList.remove('d-none');

            // Update loading text
            const loadingText = loadingOverlay.querySelector('h5');
            const loadingSubtext = loadingOverlay.querySelector('p');
            loadingText.textContent = 'Generating Report';
            loadingSubtext.textContent = 'Please wait while we process the data...';

            // Simulate API call delay
            setTimeout(() => {
                try {
                    const reportData = generateDemoReportData(reportType, courseId, departmentId, classId);
                    displayReport(reportData);

                    // Hide loading
                    loadingOverlay.classList.add('d-none');

                    showAlert('Report generated successfully!', 'success');
                } catch (error) {
                    console.error('Report generation error:', error);
                    loadingOverlay.classList.add('d-none');
                    showAlert('Failed to generate report. Please try again.', 'error');
                }
            }, 1200); // Reduced from 1500ms for better UX
        }

        // Generate demo report data
        function generateDemoReportData(reportType, courseId, departmentId, classId) {
            let filteredStudents = [...demoData.students];

            // Filter based on report type
            switch(reportType) {
                case 'course':
                    // For course reports, show all students (simplified demo)
                    break;
                case 'department':
                    if (departmentId) {
                        const deptName = demoData.departments.find(d => d.id == departmentId)?.name;
                        filteredStudents = filteredStudents.filter(s => s.department === deptName);
                    }
                    break;
                case 'class':
                    if (classId) {
                        filteredStudents = filteredStudents.filter(s => s.year == classId);
                    }
                    break;
            }

            // Calculate summary
            const totalStudents = filteredStudents.length;
            const avgAttendance = totalStudents > 0 ?
                Math.round(filteredStudents.reduce((sum, s) => sum + s.attendance, 0) / totalStudents) : 0;
            const above85 = filteredStudents.filter(s => s.attendance >= 85).length;
            const below85 = filteredStudents.filter(s => s.attendance < 85).length;

            return {
                type: reportType,
                students: filteredStudents,
                summary: {
                    total_students: totalStudents,
                    average_attendance: avgAttendance,
                    students_above_85: above85,
                    students_below_85: below85
                }
            };
        }

        // Display report
        function displayReport(data) {
            const content = document.getElementById('reportContent');

            if (data.students.length === 0) {
                content.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-info-circle"></i>
                        <h5 style="color: #000000;">No Data Found</h5>
                        <p class="mb-0" style="color: #000000;">No students found matching the selected criteria.</p>
                    </div>
                `;
                return;
            }

            // Store current data for export
            currentReportData = data;

            let html = `
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 style="color: #000000;">${data.summary.total_students}</h3>
                                <p class="mb-0" style="color: #000000;">Total Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 style="color: #000000;">${data.summary.average_attendance}%</h3>
                                <p class="mb-0" style="color: #000000;">Average Attendance</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 style="color: #000000;">${data.summary.students_above_85}</h3>
                                <p class="mb-0" style="color: #000000;">Above 85%</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 style="color: #000000;">${data.summary.students_below_85}</h3>
                                <p class="mb-0" style="color: #000000;">Below 85%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-table me-2"></i>Student Attendance Report</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Registration No</th>
                                        <th>Department</th>
                                        <th>Year</th>
                                        <th>Attendance %</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>`;

            data.students.forEach(student => {
                const percentage = student.attendance;
                let statusClass = 'bg-danger';
                let statusText = 'Poor';
                let barClass = 'poor';

                if (percentage >= 85) {
                    statusClass = 'bg-success';
                    statusText = 'Excellent';
                    barClass = 'excellent';
                } else if (percentage >= 70) {
                    statusClass = 'bg-warning';
                    statusText = 'Good';
                    barClass = 'good';
                }

                html += `
                                    <tr>
                                        <td>${student.name}</td>
                                        <td>${student.reg_no}</td>
                                        <td>${student.department}</td>
                                        <td>Year ${student.year}</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2 fw-bold">${percentage}%</div>
                                                <div class="attendance-bar">
                                                    <div class="attendance-fill ${barClass}" style="width: ${percentage}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                                        <td>
                                            <button class="btn btn-outline-info btn-sm" onclick="showStudentDetails(${student.id})">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>`;
            });

            html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>`;

            content.innerHTML = html;
        }

        // Show student details
        function showStudentDetails(studentId) {
            const student = demoData.students.find(s => s.id == studentId);
            if (!student) return;

            const modal = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
            const content = document.getElementById('studentDetailsContent');

            content.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Student Information</h6>
                        <p><strong>Name:</strong> ${student.name}</p>
                        <p><strong>Registration No:</strong> ${student.reg_no}</p>
                        <p><strong>Department:</strong> ${student.department}</p>
                        <p><strong>Year:</strong> Year ${student.year}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Attendance Summary</h6>
                        <p><strong>Overall Rate:</strong> ${student.attendance}%</p>
                        <p><strong>Status:</strong> ${student.attendance >= 85 ? 'Excellent' : student.attendance >= 70 ? 'Good' : 'Needs Improvement'}</p>
                    </div>
                </div>
                <h6>Recent Sessions</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Course</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>2025-01-15</td>
                                <td>Introduction to Programming</td>
                                <td><span class="badge bg-success">Present</span></td>
                            </tr>
                            <tr>
                                <td>2025-01-14</td>
                                <td>Data Structures</td>
                                <td><span class="badge bg-success">Present</span></td>
                            </tr>
                            <tr>
                                <td>2025-01-13</td>
                                <td>Database Systems</td>
                                <td><span class="badge bg-danger">Absent</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>`;

            modal.show();
        }

        // Export functions
        function exportToCSV() {
            if (!currentReportData) {
                showAlert('Please generate a report first', 'warning');
                return;
            }

            let csv = 'Student Name,Registration No,Department,Year,Attendance %,Status\n';

            currentReportData.students.forEach(student => {
                const status = student.attendance >= 85 ? 'Excellent' :
                              student.attendance >= 70 ? 'Good' : 'Poor';
                csv += `"${student.name}","${student.reg_no}","${student.department}","Year ${student.year}","${student.attendance}%","${status}"\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'lecturer_attendance_report.csv';
            a.click();
            window.URL.revokeObjectURL(url);

            showAlert('CSV exported successfully!', 'success');
        }

        function exportToPDF() {
            showAlert('PDF export feature coming soon. Use CSV export for now.', 'info');
        }

        // Alert display function
        function showAlert(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' :
                              type === 'error' ? 'alert-danger' :
                              type === 'warning' ? 'alert-warning' : 'alert-info';

            const icon = type === 'success' ? 'fas fa-check-circle' :
                        type === 'error' ? 'fas fa-exclamation-triangle' :
                        type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

            const alert = document.createElement('div');
            alert.className = `alert ${alertClass} alert-dismissible fade show`;
            alert.innerHTML = `
                <i class="${icon} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.getElementById('alertBox').appendChild(alert);

            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateFilters();
            showAlert('Lecturer Attendance Reports loaded successfully!', 'success');
        });
    </script>
</body>
</html>