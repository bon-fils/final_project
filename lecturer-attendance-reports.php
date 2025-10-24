<?php
/**
 * Lecturer Attendance Reports - Direct Database Access
 * Based on successful test-lecturer-reports.php pattern
 */
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['lecturer', 'hod', 'admin']);

$lecturer_id = $_SESSION['lecturer_id'] ?? null;
if (!$lecturer_id) {
    header("Location: login.php");
    exit();
}

// Use global $pdo from config.php
global $pdo;

// Get lecturer info
$lecturer_stmt = $pdo->prepare("
    SELECT 
        l.id as lecturer_id,
        l.user_id,
        l.department_id,
        u.first_name,
        u.last_name,
        u.email,
        d.name as department_name
    FROM lecturers l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN departments d ON l.department_id = d.id
    WHERE l.id = ?
");
$lecturer_stmt->execute([$lecturer_id]);
$lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer) {
    die("Lecturer record not found. Please contact administrator.");
}

// Get all courses for this lecturer with stats
$courses_stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.course_code,
        c.course_name,
        c.option_id,
        o.name as option_name,
        c.year,
        c.department_id
    FROM courses c
    LEFT JOIN options o ON c.option_id = o.id
    WHERE c.lecturer_id = ? AND c.status = 'active'
    ORDER BY c.year, c.course_code
");
$courses_stmt->execute([$lecturer_id]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get detailed stats for each course (same as test page)
$course_stats = [];
foreach ($courses as $course) {
    // Count students enrolled in this course
    $student_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id) as count
        FROM students s
        WHERE s.option_id = ? 
          AND CAST(s.year_level AS UNSIGNED) = ? 
          AND s.status = 'active'
    ");
    $student_stmt->execute([$course['option_id'], $course['year']]);
    $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count sessions for this course
    $session_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM attendance_sessions
        WHERE course_id = ?
    ");
    $session_stmt->execute([$course['id']]);
    $session_count = $session_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get attendance statistics
    $attendance_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count
        FROM attendance_records ar
        JOIN attendance_sessions ats ON ar.session_id = ats.id
        WHERE ats.course_id = ?
    ");
    $attendance_stmt->execute([$course['id']]);
    $attendance_data = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_records = $attendance_data['total_records'] ?? 0;
    $present_count = $attendance_data['present_count'] ?? 0;
    
    // Calculate based on total possible records (students × sessions)
    $total_possible_records = $student_count * $session_count;
    $avg_attendance = $total_possible_records > 0 ? round(($present_count / $total_possible_records) * 100, 2) : 0;
    
    $course_stats[] = [
        'id' => $course['id'],
        'course_code' => $course['course_code'],
        'course_name' => $course['course_name'],
        'option_name' => $course['option_name'] ?? 'N/A',
        'year' => $course['year'],
        'student_count' => $student_count,
        'session_count' => $session_count,
        'total_records' => $total_records,
        'present_count' => $present_count,
        'avg_attendance' => $avg_attendance
    ];
}

// Calculate overall statistics
$total_courses = count($course_stats);
$total_students = array_sum(array_column($course_stats, 'student_count'));
$total_sessions = array_sum(array_column($course_stats, 'session_count'));
$avg_attendance_all = $total_courses > 0 ? round(array_sum(array_column($course_stats, 'avg_attendance')) / $total_courses, 2) : 0;
?>
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
            background: #ffffff;
            min-height: 100vh;
            margin: 0;
            position: relative;
            overflow-x: hidden;
            color: #000000;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: #ffffff;
            border-right: 1px solid #000000;
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
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
            background: #f0f0f0;
            color: #000000;
            border-left-color: #000000;
            transform: translateX(8px);
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
        }

        .sidebar-nav a.active {
            background: #f0f0f0;
            color: #000000;
            border-left-color: #000000;
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
            background: #f8f9fa;
            border-bottom: 2px solid #000000;
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
            border: 1px solid #000000;
            color: white;
        }

        .btn-primary:hover {
            background: #333333;
            border-color: #333333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            color: white;
        }

        .btn-danger {
            background: #000000;
            border: 1px solid #000000;
            color: white;
        }

        .btn-danger:hover {
            background: #333333;
            border-color: #333333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-info {
            background: #000000;
            border: 1px solid #000000;
            color: white;
        }

        .btn-info:hover {
            background: #333333;
            border-color: #333333;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .btn-outline-secondary {
            background: #ffffff;
            color: #000000;
            border: 1px solid #000000;
        }

        .btn-outline-secondary:hover {
            background: #f0f0f0;
            color: #000000;
            border-color: #000000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
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
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            border: 1px solid #000000;
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
            background: #ffffff;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid #000000;
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

            /* Table responsiveness */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                scrollbar-color: var(--primary-color) transparent;
            }

            .table-responsive::-webkit-scrollbar {
                height: 6px;
            }

            .table-responsive::-webkit-scrollbar-track {
                background: rgba(0,0,0,0.05);
                border-radius: 3px;
            }

            .table-responsive::-webkit-scrollbar-thumb {
                background: var(--primary-color);
                border-radius: 3px;
            }

            .table-responsive::-webkit-scrollbar-thumb:hover {
                background: var(--primary-dark);
            }

            .table {
                min-width: 1400px; /* Ensure table doesn't compress too much */
                font-size: 0.875rem;
            }

            .table th, .table td {
                white-space: nowrap;
                padding: 8px 4px;
            }

            .table th {
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .btn {
                padding: 6px 8px;
                font-size: 0.75rem;
                margin-bottom: 2px;
            }

            .btn i {
                margin-right: 4px;
            }

            .badge {
                font-size: 0.7rem;
                padding: 3px 6px;
            }

            .attendance-bar {
                width: 60px;
                height: 6px;
            }

            /* Stack buttons vertically on very small screens */
            @media (max-width: 480px) {
                .table th, .table td {
                    padding: 6px 2px;
                    font-size: 0.8rem;
                }

                .btn {
                    display: block;
                    width: 100%;
                    margin-bottom: 2px;
                    text-align: center;
                }

                .btn-group-mobile {
                    display: flex;
                    flex-direction: column;
                    gap: 2px;
                }
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

        <!-- Info Banner -->
        <!-- <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Attendance Reports:</strong> Showing all your courses with real-time statistics. 
            Data is automatically loaded from the database.
        </div> -->

        <!-- Compact Filter & Summary Section -->
        <?php if (count($course_stats) > 0): ?>
        <div class="row g-3 mb-3">
            <!-- Filter Controls -->
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-body py-2">
                        <h6 class="mb-2"><i class="fas fa-filter me-2"></i>Filter Courses</h6>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <input type="text" class="form-control form-control-sm" id="filterCourse" placeholder="Search course..." onkeyup="filterCourses()" style="color: #000000;">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="filterYear" onchange="filterCourses()" style="color: #000000;">
                                    <option value="" style="color: #000000;">All Years</option>
                                    <option value="1" style="color: #000000;">Year 1</option>
                                    <option value="2" style="color: #000000;">Year 2</option>
                                    <option value="3" style="color: #000000;">Year 3</option>
                                    <option value="4" style="color: #000000;">Year 4</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" id="filterStatus" onchange="filterCourses()" style="color: #000000;">
                                    <option value="" style="color: #000000;">All Status</option>
                                    <option value="excellent" style="color: #000000;">Excellent</option>
                                    <option value="good" style="color: #000000;">Good</option>
                                    <option value="average" style="color: #000000;">Average</option>
                                    <option value="poor" style="color: #000000;">Poor</option>
                                </select>
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-outline-secondary btn-sm w-100" onclick="resetFilters()" title="Reset Filters">
                                    <i class="fas fa-redo"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Compact Summary Stats -->
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-body py-2">
                        <h6 class="mb-2"><i class="fas fa-chart-bar me-2"></i>Summary</h6>
                        <div class="row g-2 text-center">
                            <div class="col-3">
                                <div class="p-1">
                                    <i class="fas fa-book" style="color: #0066cc;"></i>
                                    <h5 class="mb-0 mt-1" style="color: #000000;"><?php echo $total_courses; ?></h5>
                                    <small style="color: #666;">Courses</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-1">
                                    <i class="fas fa-users" style="color: #10b981;"></i>
                                    <h5 class="mb-0 mt-1" style="color: #000000;"><?php echo $total_students; ?></h5>
                                    <small style="color: #666;">Students</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-1">
                                    <i class="fas fa-calendar-check" style="color: #f59e0b;"></i>
                                    <h5 class="mb-0 mt-1" style="color: #000000;"><?php echo $total_sessions; ?></h5>
                                    <small style="color: #666;">Sessions</small>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-1">
                                    <i class="fas fa-percentage" style="color: #06b6d4;"></i>
                                    <h5 class="mb-0 mt-1" style="color: #000000;"><?php echo $avg_attendance_all; ?>%</h5>
                                    <small style="color: #666;">Avg</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Report Content -->
        <div id="reportContent">
            <?php if (count($course_stats) === 0): ?>
                <!-- No Courses Found -->
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle fa-3x mb-3" style="color: #ef4444;"></i>
                    <h5 style="color: #000000;">No Courses Assigned</h5>
                    <p class="mb-3" style="color: #000000;">You don't have any courses assigned yet. Please contact your administrator.</p>
                    <div class="alert alert-info d-inline-block">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Need Help?</strong> Run diagnostics to check your setup.
                    </div>
                    <div class="mt-3">
                        <a href="test-lecturer-reports.php" class="btn btn-outline-info">
                            <i class="fas fa-vial me-2"></i>Run Diagnostics
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Courses Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><i class="fas fa-table me-2"></i>All Courses Overview</h6>
                        <button class="btn btn-sm btn-danger" onclick="exportAllCourses('pdf')">
                            <i class="fas fa-file-pdf me-1"></i>Export PDF
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
                            <table class="table table-striped table-hover" style="min-width: 1400px;">
                                <thead class="table-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Option</th>
                                        <th>Year</th>
                                        <th>Students</th>
                                        <th>Sessions</th>
                                        <th>Avg Attendance</th>
                                        <th>Status</th>
                                        <th>View Attendance Details</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_stats as $index => $course): ?>
                                        <?php
                                        $avg = $course['avg_attendance'];
                                        if ($avg >= 85) {
                                            $statusClass = 'bg-success';
                                            $statusText = 'Excellent';
                                            $barClass = 'excellent';
                                        } elseif ($avg >= 75) {
                                            $statusClass = 'bg-warning';
                                            $statusText = 'Good';
                                            $barClass = 'good';
                                        } elseif ($avg >= 60) {
                                            $statusClass = 'bg-info';
                                            $statusText = 'Average';
                                            $barClass = 'average';
                                        } else {
                                            $statusClass = 'bg-danger';
                                            $statusText = 'Poor';
                                            $barClass = 'poor';
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><strong><?php echo htmlspecialchars($course['course_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($course['course_name']); ?></td>
                                            <td><small><?php echo htmlspecialchars($course['option_name']); ?></small></td>
                                            <td><span class="badge bg-secondary">Year <?php echo $course['year']; ?></span></td>
                                            <td><span class="badge bg-primary"><?php echo $course['student_count']; ?></span></td>
                                            <td><span class="badge bg-info"><?php echo $course['session_count']; ?></span></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-2 fw-bold"><?php echo $avg; ?>%</span>
                                                    <div class="attendance-bar">
                                                        <div class="attendance-fill <?php echo $barClass; ?>" style="width: <?php echo $avg; ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                            <td>
                                                <div class="btn-group-mobile">
                                                    <button class="btn btn-sm btn-primary" onclick="viewCourseAttendance(<?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>', '<?php echo htmlspecialchars($course['course_name']); ?>')">
                                                        <i class="fas fa-eye me-1"></i>View Attendance
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group-mobile">
                                                    <button class="btn btn-sm btn-success" onclick="exportCourseAttendance('pdf', <?php echo $course['id']; ?>, '<?php echo htmlspecialchars($course['course_code']); ?>', '<?php echo htmlspecialchars($course['course_name']); ?>')">
                                                        <i class="fas fa-file-pdf me-1"></i>Export PDF
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Course Attendance Modal -->
    <div class="modal fade" id="courseAttendanceModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clipboard-list me-2"></i><span id="modalCourseTitle">Course Attendance Records</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="courseAttendanceContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading attendance records...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-check me-2"></i><span id="modalStudentTitle">Student Attendance Details</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading session details...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sidebar toggle functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('mobile-open');
        }

        // Simple alert function
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

            const alertBox = document.getElementById('alertBox');
            if (alertBox) {
                alertBox.appendChild(alert);
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 5000);
            }
        }

        // Client-side filter function (no API calls)
        function filterCourses() {
            const searchText = document.getElementById('filterCourse').value.toLowerCase();
            const filterYear = document.getElementById('filterYear').value;
            const filterStatus = document.getElementById('filterStatus').value;
            
            const rows = document.querySelectorAll('#reportContent tbody tr');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const courseCode = row.cells[1].textContent.toLowerCase();
                const courseName = row.cells[2].textContent.toLowerCase();
                const year = row.cells[4].textContent.replace('Year ', '');
                const avgPercent = parseFloat(row.cells[8].querySelector('.fw-bold').textContent);
                
                // Check search text
                const matchesSearch = !searchText || 
                    courseCode.includes(searchText) || 
                    courseName.includes(searchText);
                
                // Check year filter
                const matchesYear = !filterYear || year === filterYear;
                
                // Check status filter
                let matchesStatus = true;
                if (filterStatus) {
                    if (filterStatus === 'excellent' && avgPercent < 85) matchesStatus = false;
                    if (filterStatus === 'good' && (avgPercent < 75 || avgPercent >= 85)) matchesStatus = false;
                    if (filterStatus === 'average' && (avgPercent < 60 || avgPercent >= 75)) matchesStatus = false;
                    if (filterStatus === 'poor' && avgPercent >= 60) matchesStatus = false;
                }
                
                // Show/hide row
                if (matchesSearch && matchesYear && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update row numbers
            let displayIndex = 1;
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    row.cells[0].textContent = displayIndex++;
                }
            });
            
            // Show message if no results
            if (visibleCount === 0) {
                showAlert('No courses match the current filters', 'info');
            }
        }
        
        // Reset filters
        function resetFilters() {
            document.getElementById('filterCourse').value = '';
            document.getElementById('filterYear').value = '';
            document.getElementById('filterStatus').value = '';
            filterCourses();
            showAlert('Filters reset', 'success');
        }

        // View course attendance records
        function viewCourseAttendance(courseId, courseCode, courseName) {
            // Store course ID for student details
            currentCourseId = courseId;
            
            // Update modal title
            document.getElementById('modalCourseTitle').textContent = `${courseCode} - ${courseName}`;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('courseAttendanceModal'));
            modal.show();
            
            // Reset content to loading state
            document.getElementById('courseAttendanceContent').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading attendance records...</p>
                </div>
            `;
            
            // Fetch attendance data using PHP
            fetch(`get-course-attendance.php?course_id=${courseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        displayAttendanceRecords(data.data);
                    } else {
                        document.getElementById('courseAttendanceContent').innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${data.message || 'No attendance records found for this course.'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('courseAttendanceContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading attendance records. Please try again.
                        </div>
                    `;
                });
        }
        
        // Display attendance records in modal
        function displayAttendanceRecords(data) {
            console.log('Attendance Data:', data); // Debug log
            
            const students = data.students || [];
            const sessions = data.sessions || [];
            
            if (students.length === 0) {
                document.getElementById('courseAttendanceContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No students enrolled in this course yet.
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Total Sessions</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Attendance %</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            students.forEach((student, index) => {
                // Use the attendance_percentage from backend (already calculated correctly)
                const attendancePercent = parseFloat(student.attendance_percentage || 0);
                
                let statusClass = 'danger';
                let statusText = 'Poor';
                if (attendancePercent >= 85) {
                    statusClass = 'success';
                    statusText = 'Excellent';
                } else if (attendancePercent >= 75) {
                    statusClass = 'warning';
                    statusText = 'Good';
                } else if (attendancePercent >= 60) {
                    statusClass = 'info';
                    statusText = 'Average';
                }
                
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td><strong>${student.reg_no || student.student_id || student.student_id_number}</strong></td>
                        <td>${student.student_name}</td>
                        <td><span class="badge bg-secondary">${student.total_sessions || 0}</span></td>
                        <td><span class="badge bg-success">${student.present_count || 0}</span></td>
                        <td><span class="badge bg-danger">${student.absent_count || 0}</span></td>
                        <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-${statusClass}" role="progressbar" 
                                     style="width: ${attendancePercent}%" 
                                     aria-valuenow="${attendancePercent}" aria-valuemin="0" aria-valuemax="100">
                                    ${attendancePercent.toFixed(1)}%
                                </div>
                            </div>
                        </td>
                        <td><span class="badge bg-${statusClass}">${statusText}</span></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="viewStudentDetails(${student.student_id}, '${student.student_name}', '${student.reg_no}')">
                                <i class="fas fa-list me-1"></i>Details
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <h6><i class="fas fa-info-circle me-2"></i>Summary</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body py-2">
                                    <h4>${students.length}</h4>
                                    <small>Total Students</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body py-2">
                                    <h4>${sessions.length || data.total_sessions || 0}</h4>
                                    <small>Total Sessions</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body py-2">
                                    <h4>${data.average_attendance || 0}%</h4>
                                    <small>Avg Attendance</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-center">
                                <div class="card-body py-2">
                                    <button class="btn btn-sm btn-danger" onclick="exportCourseAttendance('pdf')">
                                        <i class="fas fa-file-pdf me-1"></i>Export PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('courseAttendanceContent').innerHTML = html;
        }
        
        // Store current course ID for student details
        let currentCourseId = null;
        let currentCourseSessions = [];
        
        // View student session-by-session details
        function viewStudentDetails(studentId, studentName, regNo) {
            // Update modal title
            document.getElementById('modalStudentTitle').textContent = `${studentName} (${regNo})`;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
            modal.show();
            
            // Reset content to loading state
            document.getElementById('studentDetailsContent').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading session details...</p>
                </div>
            `;
            
            // Fetch student session details
            fetch(`get-student-session-details.php?student_id=${studentId}&course_id=${currentCourseId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        displayStudentSessionDetails(data.data);
                    } else {
                        document.getElementById('studentDetailsContent').innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ${data.message || 'No session details found.'}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('studentDetailsContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading session details. Please try again.
                        </div>
                    `;
                });
        }
        
        // Display student session details
        function displayStudentSessionDetails(data) {
            const sessions = data.sessions || [];
            const student = data.student || {};
            
            if (sessions.length === 0) {
                document.getElementById('studentDetailsContent').innerHTML = `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No sessions found for this course.
                    </div>
                `;
                return;
            }
            
            let html = `
                <div class="mb-3">
                    <h6><i class="fas fa-info-circle me-2"></i>Summary</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <strong>Total Sessions:</strong> ${sessions.length}
                        </div>
                        <div class="col-md-3">
                            <strong>Present:</strong> <span class="badge bg-success">${data.present_count || 0}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Absent:</strong> <span class="badge bg-danger">${data.absent_count || 0}</span>
                        </div>
                        <div class="col-md-3">
                            <strong>Attendance:</strong> <strong>${data.attendance_percentage || 0}%</strong>
                        </div>
                    </div>
                </div>
                
                <h6><i class="fas fa-calendar-alt me-2"></i>Session Details</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Session Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Marked At</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            sessions.forEach((session, index) => {
                const statusBadge = session.status === 'present' 
                    ? '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Present</span>'
                    : '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Absent</span>';
                
                const markedAt = session.marked_at 
                    ? new Date(session.marked_at).toLocaleString()
                    : '<span class="text-muted">Not marked</span>';
                
                const sessionTime = session.start_time && session.end_time
                    ? `${session.start_time} - ${session.end_time}`
                    : '<span class="text-muted">N/A</span>';
                
                html += `
                    <tr>
                        <td>${index + 1}</td>
                        <td><strong>${new Date(session.session_date).toLocaleDateString()}</strong></td>
                        <td>${sessionTime}</td>
                        <td>${statusBadge}</td>
                        <td><small>${markedAt}</small></td>
                    </tr>
                `;
            });
            
            html += `
                        </tbody>
                    </table>
                </div>
            `;
            
            document.getElementById('studentDetailsContent').innerHTML = html;
        }
        
        // Export course attendance (CSV or PDF)
        function exportCourseAttendance(format, courseId = null, courseCode = null, courseName = null) {
            const exportCourseId = courseId || currentCourseId;
            if (!exportCourseId) {
                showAlert('No course selected', 'warning');
                return;
            }

            const url = `export-attendance.php?type=course&course_id=${exportCourseId}&format=${format}`;
            window.open(url, '_blank');
            showAlert(`Exporting ${format.toUpperCase()} for ${courseCode || 'selected course'}...`, 'success');
        }
        
        // Export all courses (CSV or PDF)
        function exportAllCourses(format) {
            const url = `export-attendance.php?type=all&format=${format}`;
            window.open(url, '_blank');
            showAlert(`Exporting all courses as ${format.toUpperCase()}...`, 'success');
        }

        // Show success message on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (count($course_stats) > 0): ?>
                showAlert('Reports loaded successfully! Showing <?php echo $total_courses; ?> courses with <?php echo $total_students; ?> students.', 'success');
            <?php endif; ?>
        });
    </script>
</body>
</html>