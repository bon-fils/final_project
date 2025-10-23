<?php
declare(strict_types=1);
session_start();

require_once "config.php";
require_once "session_check.php";
require_role(['student']);

$user_id = (int)($_SESSION['user_id'] ?? 0);

// Get student information
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.reg_no, s.year_level, s.option_id,
               u.first_name, u.last_name, u.email,
               d.name as department_name, o.name as option_name
        FROM students s
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN options o ON s.option_id = o.id
        LEFT JOIN departments d ON o.department_id = d.id
        WHERE s.user_id = ? AND u.status = 'active'
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header("Location: login.php?error=student_not_found");
        exit();
    }

} catch (Exception $e) {
    error_log("Student info error: " . $e->getMessage());
    header("Location: login.php?error=database");
    exit();
}

// Get dashboard statistics
$dashboard_stats = [
    'attendance_rate' => null,
    'total_sessions' => 0,
    'present_sessions' => 0,
    'total_leave_requests' => 0,
    'pending_leave' => 0,
    'approved_leave' => 0,
    'rejected_leave' => 0,
    'courses_under_85' => 0,
    'enrolled_courses' => 0
];

// Get attendance rate
try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_sessions,
            ROUND(
                CASE
                    WHEN COUNT(*) > 0 THEN (SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(*)
                    ELSE NULL
                END, 1
            ) as attendance_percentage
        FROM attendance_records
        WHERE student_id = ?
    ");
    $stmt->execute([$student['id']]);
    $attendance_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($attendance_data['total_sessions'] > 0) {
        $dashboard_stats['attendance_rate'] = $attendance_data['attendance_percentage'];
        $dashboard_stats['total_sessions'] = $attendance_data['total_sessions'];
        $dashboard_stats['present_sessions'] = $attendance_data['present_sessions'];
    } else {
        $dashboard_stats['attendance_rate'] = null; // No attendance data yet
        $dashboard_stats['total_sessions'] = 0;
        $dashboard_stats['present_sessions'] = 0;
    }
} catch (Exception $e) {
    $dashboard_stats['attendance_rate'] = null;
    $dashboard_stats['total_sessions'] = 0;
    $dashboard_stats['present_sessions'] = 0;
}

// Get leave requests statistics
try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
        FROM leave_requests
        WHERE student_id = ?
    ");
    $stmt->execute([$student['id']]);
    $leave_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $dashboard_stats['total_leave_requests'] = $leave_data['total_requests'] ?? 0;
    $dashboard_stats['pending_leave'] = $leave_data['pending_count'] ?? 0;
    $dashboard_stats['approved_leave'] = $leave_data['approved_count'] ?? 0;
    $dashboard_stats['rejected_leave'] = $leave_data['rejected_count'] ?? 0;
} catch (Exception $e) {
    $dashboard_stats['total_leave_requests'] = 0;
    $dashboard_stats['pending_leave'] = 0;
    $dashboard_stats['approved_leave'] = 0;
    $dashboard_stats['rejected_leave'] = 0;
}

// Get courses under 85% - only courses in student's year level
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as courses_under_85
        FROM (
            SELECT c.id
            FROM courses c
            LEFT JOIN attendance_sessions ats ON c.id = ats.course_id
            LEFT JOIN attendance_records ar ON ats.id = ar.session_id AND ar.student_id = ?
            WHERE c.option_id = ? AND c.year = ?
            GROUP BY c.id
            HAVING ROUND(
                CASE
                    WHEN COUNT(ar.id) > 0 THEN (SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(ar.id)
                    ELSE 0
                END, 1
            ) < 85 AND COUNT(ar.id) > 0
        ) as under_85_courses
    ");
    $stmt->execute([$student['id'], $student['option_id'], $student['year_level']]);
    $courses_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $dashboard_stats['courses_under_85'] = $courses_data['courses_under_85'] ?? 0;
} catch (Exception $e) {
    $dashboard_stats['courses_under_85'] = 0;
}

// Get enrolled courses count - all courses in student's year level within their option
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as enrolled_courses 
        FROM courses 
        WHERE option_id = ? AND year = ? AND status = 'active'
    ");
    $stmt->execute([$student['option_id'], $student['year_level']]);
    $courses_count = $stmt->fetch(PDO::FETCH_ASSOC);
    $dashboard_stats['enrolled_courses'] = $courses_count['enrolled_courses'] ?? 0;
} catch (Exception $e) {
    error_log("Error getting enrolled courses: " . $e->getMessage());
    $dashboard_stats['enrolled_courses'] = 0;
}

$page_title = "Student Dashboard";
$current_page = "dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #eff6ff;
            --success-color: #16a34a;
            --success-light: #dcfce7;
            --success-dark: #15803d;
            --danger-color: #dc2626;
            --danger-light: #fef2f2;
            --danger-dark: #b91c1c;
            --warning-color: #d97706;
            --warning-light: #fffbeb;
            --warning-dark: #92400e;
            --info-color: #0891b2;
            --info-light: #ecfeff;
            --info-dark: #0e7490;
            --light-bg: #ffffff;
            --dark-bg: #1f2937;
            --shadow-light: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-heavy: 0 10px 15px rgba(0,0,0,0.1);
            --border-radius: 8px;
            --border-radius-sm: 6px;
            --border-radius-lg: 12px;
            --transition: all 0.2s ease;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            min-height: 100vh;
            margin: 0;
            position: relative;
            color: #374151;
            line-height: 1.6;
        }

        /* Enhanced color scheme for better visual hierarchy */
        .text-primary { color: var(--primary-color) !important; }
        .text-success { color: var(--success-color) !important; }
        .text-danger { color: var(--danger-color) !important; }
        .text-warning { color: var(--warning-color) !important; }
        .text-info { color: var(--info-color) !important; }
        .bg-primary { background-color: var(--primary-color) !important; }
        .bg-success { background-color: var(--success-color) !important; }
        .bg-danger { background-color: var(--danger-color) !important; }
        .bg-warning { background-color: var(--warning-color) !important; }
        .bg-info { background-color: var(--info-color) !important; }

        /* ASP-inspired modern design elements */
        .glass-effect {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .gradient-border {
            position: relative;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 2px;
            border-radius: var(--border-radius);
        }

        .gradient-border::before {
            content: '';
            position: absolute;
            inset: 2px;
            background: white;
            border-radius: calc(var(--border-radius) - 2px);
            z-index: 1;
        }

        .metric-card, .action-card {
            position: relative;
            z-index: 2;
        }

        /* Enhanced shadows and depth */
        .shadow-premium {
            box-shadow:
                0 4px 20px rgba(0, 0, 0, 0.08),
                0 2px 8px rgba(0, 0, 0, 0.04),
                0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .shadow-premium:hover {
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.12),
                0 4px 16px rgba(0, 0, 0, 0.08),
                0 2px 8px rgba(0, 0, 0, 0.04);
        }

        /* Hover lift effect */
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15) !important;
        }

        /* Modern button effects */
        .btn-modern {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn-modern:hover::before {
            left: 100%;
        }

        /* ASP-style card animations */
        .card-asp {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            transform: translateY(0);
        }

        .card-asp:hover {
            transform: translateY(-8px) scale(1.02);
        }

        /* Modern typography */
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Enhanced loading states */
        .loading-shimmer {
            background: linear-gradient(90deg,
                rgba(255, 255, 255, 0) 0%,
                rgba(255, 255, 255, 0.4) 50%,
                rgba(255, 255, 255, 0) 100%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background-color: #ffffff;
            border-right: 1px solid #e5e7eb;
            padding: 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--shadow-light);
        }

        .sidebar .logo {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 24px 20px;
            text-align: center;
            position: relative;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .logo h4 {
            color: white;
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .sidebar-nav {
            list-style: none;
            padding: 20px 0;
            margin: 0;
        }

        .sidebar-nav .nav-section {
            padding: 12px 20px 8px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 8px;
        }

        .sidebar-nav a {
            display: block;
            padding: 14px 25px;
            color: #374151;
            text-decoration: none;
            border-radius: 0 6px 6px 0;
            margin: 0 0 2px 0;
            transition: var(--transition);
            font-weight: 500;
            border-left: 3px solid transparent;
        }

        .sidebar-nav a:hover {
            background-color: var(--primary-light);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            transform: translateX(4px);
        }

        .sidebar-nav a.active {
            background-color: var(--primary-light);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 600;
        }

        .sidebar-nav a i {
            margin-right: 12px;
            width: 18px;
            text-align: center;
            font-size: 1rem;
        }

        .topbar {
            margin-left: 260px;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 30px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .main-content {
            margin-left: 260px;
            padding: 32px;
            max-width: calc(100% - 260px);
            overflow-x: auto;
            transition: var(--transition);
        }

        .footer {
            text-align: center;
            margin-left: 260px;
            padding: 20px;
            font-size: 0.9rem;
            color: #6b7280;
            background-color: #ffffff;
            border-top: 1px solid #e5e7eb;
            position: fixed;
            bottom: 0;
            width: calc(100% - 260px);
            box-shadow: 0 -1px 3px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .card {
            background-color: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            border: 1px solid #e5e7eb;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }

        .card-header {
            background-color: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
            padding: 20px 24px;
            font-weight: 600;
            color: #374151;
        }

        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 600;
            padding: 10px 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: none;
            font-size: 0.9rem;
            letter-spacing: 0.025em;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
        }

        .table {
            background-color: #ffffff;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            border: 1px solid #e5e7eb;
        }

        .table thead th {
            background-color: #f9fafb;
            color: #374151;
            border: none;
            font-weight: 600;
            padding: 16px;
            position: relative;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
            text-transform: uppercase;
        }

        .table thead th::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background-color: #e5e7eb;
        }

        .table tbody td {
            padding: 16px;
            border-color: #f3f4f6;
            transition: var(--transition);
            vertical-align: middle;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        .table tbody tr:hover td {
            border-color: #e5e7eb;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1003;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius-sm);
            padding: 12px;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            transition: var(--transition);
        }

        .mobile-menu-toggle:hover {
            background-color: var(--primary-dark);
            transform: scale(1.05);
        }

        .mobile-menu-toggle:active {
            transform: scale(0.95);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                width: 260px;
                z-index: 1002;
            }

            .sidebar.show {
                transform: translateX(0);
                box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            }

            .sidebar.show::after {
                content: '';
                position: fixed;
                top: 0;
                left: 260px;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                backdrop-filter: blur(2px);
                z-index: -1;
            }

            .topbar,
            .main-content,
            .footer {
                margin-left: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }

            .topbar {
                padding: 16px 20px;
                margin-left: 0;
            }

            .main-content {
                padding: 20px 16px;
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block !important;
            }

            .btn-group-custom {
                justify-content: center;
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .btn-group-custom .btn {
                margin-bottom: 0;
                width: 100%;
            }

            .sidebar-nav a {
                padding: 16px 20px;
                font-size: 1rem;
                border-radius: 0 6px 6px 0;
            }

            .sidebar-nav .nav-section {
                padding: 12px 20px 8px;
                font-size: 0.75rem;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 16px 12px;
            }

            .topbar {
                padding: 14px 16px;
            }

            .table thead th,
            .table tbody td {
                padding: 12px 8px;
                font-size: 0.8rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .btn {
                padding: 10px 16px;
                font-size: 0.85rem;
            }
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(248, 250, 252, 0.9));
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-premium);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark), var(--success-color));
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .welcome-main h1 {
            font-size: 1.875rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 4px;
        }

        .welcome-main p {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .welcome-avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background-color: #ffffff;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid #e5e7eb;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            margin-bottom: 12px;
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
        }

        /* Dashboard Overview */
        .dashboard-overview {
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 8px;
            color: var(--primary-color);
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .metric-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid #e5e7eb;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .metric-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .metric-card:hover::before {
            opacity: 1;
        }

        .metric-card:hover {
            box-shadow: var(--shadow-premium);
            transform: translateY(-4px);
            border-color: rgba(37, 99, 235, 0.2);
        }

        .metric-card.primary {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.02), rgba(29, 78, 216, 0.02));
        }

        .metric-card.secondary {
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.02), rgba(14, 116, 144, 0.02));
        }

        .metric-card.warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.02), rgba(217, 119, 6, 0.02));
            border-left: 4px solid var(--warning-color);
        }

        .metric-card.tertiary {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.02), rgba(124, 58, 237, 0.02));
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            background-color: var(--primary-light);
            color: var(--primary-color);
        }

        .metric-content h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 2px;
            transition: color 0.3s ease;
        }

        .metric-card:hover .metric-content h3 {
            color: var(--primary-color);
        }

        .metric-content p {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
        }

        /* Quick Actions */
        .quick-actions-section {
            margin-top: 24px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
        }

        .action-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: var(--border-radius);
            padding: 20px;
            text-decoration: none;
            color: #374151;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg,
                rgba(37, 99, 235, 0.02),
                rgba(29, 78, 216, 0.02));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .action-card:hover::before {
            opacity: 1;
        }

        .action-card:hover {
            box-shadow: var(--shadow-premium);
            transform: translateY(-6px) scale(1.01);
            color: #374151;
            text-decoration: none;
            border-color: rgba(37, 99, 235, 0.2);
        }

        .action-card * {
            position: relative;
            z-index: 2;
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--border-radius-sm);
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.25rem;
        }

        .action-content h6 {
            font-size: 1rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0 0 4px 0;
            transition: color 0.3s ease;
        }

        .action-card:hover .action-content h6 {
            color: var(--primary-color);
        }

        .action-content p {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Student Sidebar -->
    <?php include 'includes/students-sidebar.php'; ?>

    <style>
    /* Additional dashboard-specific styles */
    .main-content {
        margin-left: 260px;
        padding: 32px;
        max-width: calc(100% - 260px);
        overflow-x: auto;
        transition: var(--transition);
    }

    .topbar {
        margin-left: 260px;
        background-color: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        padding: 20px 30px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        position: sticky;
        top: 0;
        z-index: 900;
        box-shadow: var(--shadow-light);
        transition: var(--transition);
    }

    .footer {
        text-align: center;
        margin-left: 260px;
        padding: 20px;
        font-size: 0.9rem;
        color: #6b7280;
        background-color: #ffffff;
        border-top: 1px solid #e5e7eb;
        position: fixed;
        bottom: 0;
        width: calc(100% - 260px);
        box-shadow: 0 -1px 3px rgba(0,0,0,0.1);
        z-index: 1000;
    }

    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        .main-content,
        .topbar,
        .footer {
            margin-left: 0 !important;
            max-width: 100% !important;
            width: 100% !important;
        }

        .topbar {
            padding: 16px 20px;
            margin-left: 0;
        }

        .main-content {
            padding: 20px 16px;
            margin-left: 0;
        }

        .mobile-menu-toggle {
            display: block !important;
        }
    }
    </style>

    <!-- Topbar -->
    <div class="topbar">
        <div class="d-flex align-items-center">
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-tachometer-alt me-2"></i>Student Dashboard
            </h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="user-info">
                <div class="user-details">
                    <div class="fw-semibold"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($student['reg_no'], ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="mb-4">
            <h2 class="mb-1">Welcome back, <?php echo htmlspecialchars($student['first_name'], ENT_QUOTES, 'UTF-8'); ?>! 👋</h2>
            <p class="text-muted mb-0">
                <i class="fas fa-user-graduate me-2"></i><?php echo htmlspecialchars($student['reg_no'], ENT_QUOTES, 'UTF-8'); ?> | 
                <i class="fas fa-graduation-cap me-2 ms-2"></i><?php echo htmlspecialchars($student['option_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?> - Year <?php echo htmlspecialchars($student['year_level'], ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <!-- Attendance Rate Card -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fas fa-chart-line text-white fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 small">Attendance Rate</h6>
                                <h3 class="mb-0 fw-bold">
                                    <?php if ($dashboard_stats['attendance_rate'] !== null): ?>
                                        <?php echo htmlspecialchars((string)$dashboard_stats['attendance_rate']); ?>%
                                    <?php else: ?>
                                        <small class="text-muted">No data</small>
                                    <?php endif; ?>
                                </h3>
                            </div>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-check-circle text-success me-1"></i>
                            <?php echo htmlspecialchars((string)$dashboard_stats['present_sessions']); ?> / <?php echo htmlspecialchars((string)$dashboard_stats['total_sessions']); ?> sessions attended
                        </small>
                    </div>
                </div>
            </div>

            <!-- Enrolled Courses Card -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                <i class="fas fa-book text-white fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 small">Enrolled Courses</h6>
                                <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars((string)$dashboard_stats['enrolled_courses']); ?></h3>
                            </div>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-graduation-cap text-info me-1"></i>
                            Year <?php echo htmlspecialchars($student['year_level']); ?>
                        </small>
                    </div>
                </div>
            </div>

            <!-- Total Leave Requests Card -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                <i class="fas fa-file-signature text-white fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 small">Leave Requests</h6>
                                <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars((string)$dashboard_stats['total_leave_requests']); ?></h3>
                            </div>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-clock text-warning me-1"></i>
                            <?php echo htmlspecialchars((string)$dashboard_stats['pending_leave']); ?> pending
                        </small>
                    </div>
                </div>
            </div>

            <!-- Courses Under 85% Card -->
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="rounded-circle p-3 me-3" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                                <i class="fas fa-exclamation-triangle text-white fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 small">Courses Under 85%</h6>
                                <h3 class="mb-0 fw-bold"><?php echo htmlspecialchars((string)$dashboard_stats['courses_under_85']); ?></h3>
                            </div>
                        </div>
                        <small class="text-muted">
                            <?php if ($dashboard_stats['courses_under_85'] > 0): ?>
                                <i class="fas fa-exclamation-circle text-danger me-1"></i>Needs attention
                            <?php else: ?>
                                <i class="fas fa-check-circle text-success me-1"></i>All good!
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- No Courses Alert -->
        <?php if ($dashboard_stats['enrolled_courses'] == 0): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>No Courses Found!</strong> 
            There are currently no courses available for <strong><?php echo htmlspecialchars($student['option_name']); ?> - Year <?php echo htmlspecialchars((string)$student['year_level']); ?></strong>.
            Please contact your academic advisor or administrator.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <h5 class="mb-3 mt-4"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
        <div class="row g-3">
            <div class="col-md-3">
                <a href="attendance-records.php" class="card border-0 shadow-sm text-decoration-none h-100 hover-lift">
                    <div class="card-body text-center">
                        <div class="rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-calendar-check text-white fa-lg"></i>
                        </div>
                        <h6 class="mb-1">View Attendance</h6>
                        <small class="text-muted">Check your records</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="request-leave.php" class="card border-0 shadow-sm text-decoration-none h-100 hover-lift">
                    <div class="card-body text-center">
                        <div class="rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-file-signature text-white fa-lg"></i>
                        </div>
                        <h6 class="mb-1">Request Leave</h6>
                        <small class="text-muted">Submit application</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="leave-status.php" class="card border-0 shadow-sm text-decoration-none h-100 hover-lift">
                    <div class="card-body text-center">
                        <div class="rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-envelope-open-text text-white fa-lg"></i>
                        </div>
                        <h6 class="mb-1">Leave Status</h6>
                        <small class="text-muted">Check requests</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="student-profile.php" class="card border-0 shadow-sm text-decoration-none h-100 hover-lift">
                    <div class="card-body text-center">
                        <div class="rounded-circle p-3 mx-auto mb-3" style="width: 60px; height: 60px; background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                            <i class="fas fa-user-cog text-white fa-lg"></i>
                        </div>
                        <h6 class="mb-1">My Profile</h6>
                        <small class="text-muted">Edit profile</small>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div>&copy; 2025 Rwanda Polytechnic | Student Management System</div>
    </div>

    <script>
        // Sidebar toggle functionality for mobile
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.toggle('mobile-open');
            }
        }

        // Auto-hide mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768) {
                if (sidebar && !sidebar.contains(event.target) && toggle && !toggle.contains(event.target)) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

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
</body>
</html>