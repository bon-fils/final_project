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
        SELECT s.id, s.reg_no, s.year_level, s.option_id, s.enrollment_date,
               u.first_name, u.last_name, u.email, u.username, u.created_at,
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

// Get attendance statistics for profile
$attendance_stats = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_sessions,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            ROUND(
                CASE
                    WHEN COUNT(*) > 0 THEN (SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(*)
                    ELSE 0
                END, 1
            ) as attendance_percentage
        FROM attendance_records
        WHERE student_id = ?
    ");
    $stmt->execute([$student['id']]);
    $attendance_stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_sessions' => 0,
        'present_count' => 0,
        'attendance_percentage' => 0
    ];
} catch (Exception $e) {
    $attendance_stats = [
        'total_sessions' => 0,
        'present_count' => 0,
        'attendance_percentage' => 0
    ];
}

// Get courses under 85% attendance
$courses_under_85 = [];
try {
    $stmt = $pdo->prepare("
        SELECT
            c.course_name, c.course_code,
            COUNT(ar.id) as total_sessions,
            ROUND(
                CASE
                    WHEN COUNT(ar.id) > 0 THEN (SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100.0) / COUNT(ar.id)
                    ELSE 0
                END, 1
            ) as attendance_percentage
        FROM courses c
        LEFT JOIN attendance_sessions ats ON c.id = ats.course_id
        LEFT JOIN attendance_records ar ON ats.id = ar.session_id AND ar.student_id = ?
        WHERE c.option_id = ?
        GROUP BY c.id, c.course_name, c.course_code
        HAVING attendance_percentage < 85 AND attendance_percentage > 0
        ORDER BY attendance_percentage ASC
    ");
    $stmt->execute([$student['id'], $student['option_id']]);
    $courses_under_85 = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courses_under_85 = [];
}

// Handle profile update
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validation
    $errors = [];

    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($email)) $errors[] = "Email is required";
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format";

    if (empty($errors)) {
        try {
            // Check if email is already taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = "Email is already taken by another user";
            } else {
                // Update user information
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET first_name = ?, last_name = ?, email = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$first_name, $last_name, $email, $user_id]);

                $message = "Profile updated successfully!";
                $message_type = "success";

                // Refresh student data
                $student['first_name'] = $first_name;
                $student['last_name'] = $last_name;
                $student['email'] = $email;
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $message = "Failed to update profile. Please try again.";
            $message_type = "error";
        }
    }

    if (!empty($errors)) {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

$page_title = "My Profile";
$current_page = "student-profile";
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
            --primary-dark-blue: #1e3a8a;
            --secondary-dark-blue: #1e40af;
            --light-blue: #dbeafe;
            --accent-blue: #3b82f6;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --success-color: #16a34a;
            --warning-color: #d97706;
            --danger-color: #dc2626;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light-blue) 0%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--text-dark);
        }

        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: calc(100vh - 70px);
        }

        .topbar {
            margin-left: 280px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            border-bottom: 1px solid rgba(30, 58, 138, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.08);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 1rem;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
        }

        .profile-sidebar {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.08);
            border: 1px solid rgba(30, 58, 138, 0.1);
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-dark-blue) 0%, var(--secondary-dark-blue) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3rem;
            color: white;
            box-shadow: 0 8px 25px rgba(30, 58, 138, 0.2);
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .profile-reg-no {
            color: var(--primary-dark-blue);
            font-weight: 600;
            margin-bottom: 16px;
        }

        .profile-details {
            text-align: left;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--text-dark);
            font-weight: 500;
        }

        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.08);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title i {
            color: var(--primary-dark-blue);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
        }

        .stat-item {
            text-align: center;
            padding: 16px;
            background: var(--light-blue);
            border-radius: 12px;
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark-blue);
            display: block;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-light);
            font-weight: 500;
        }

        .courses-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 20px;
        }

        .warning-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .warning-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--warning-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .form-container {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.08);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-dark-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            outline: none;
        }

        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 12px 24px;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-dark-blue) 0%, var(--secondary-dark-blue) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.3);
            color: white;
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: rgba(22, 163, 74, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: rgba(220, 38, 38, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .footer {
            margin-left: 280px;
            text-align: center;
            padding: 20px;
            border-top: 1px solid rgba(30, 58, 138, 0.1);
            background: white;
        }

        @media (max-width: 768px) {
            .main-content, .topbar, .footer {
                margin-left: 0;
            }

            .profile-container {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Include Student Sidebar -->
    <?php include_once 'includes/students-sidebar.php'; ?>

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <h5 class="mb-0 fw-bold">
                <i class="fas fa-user-cog me-2"></i><?php echo $page_title; ?>
            </h5>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="text-end">
                <small class="text-muted d-block">Welcome back</small>
                <span class="fw-semibold"><?php echo htmlspecialchars($student['first_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-user-cog me-3 text-primary"></i><?php echo $page_title; ?>
                </h1>
                <p class="page-subtitle">View and manage your profile information</p>
            </div>
        </div>

        <!-- Message Display -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Layout -->
        <div class="profile-container">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                </div>
                <div class="profile-name">
                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="profile-reg-no">
                    <?php echo htmlspecialchars($student['reg_no'], ENT_QUOTES, 'UTF-8'); ?>
                </div>

                <div class="profile-details">
                    <div class="detail-item">
                        <span class="detail-label">Department</span>
                        <span class="detail-value"><?php echo htmlspecialchars($student['department_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Program</span>
                        <span class="detail-value"><?php echo htmlspecialchars($student['option_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Year Level</span>
                        <span class="detail-value"><?php echo htmlspecialchars($student['year_level'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Enrollment</span>
                        <span class="detail-value"><?php echo $student['enrollment_date'] ? date('M Y', strtotime($student['enrollment_date'])) : 'N/A'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Profile Main Content -->
            <div class="profile-main">
                <!-- Attendance Overview -->
                <div class="info-card">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line"></i>Attendance Overview
                    </h3>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo htmlspecialchars($attendance_stats['attendance_percentage']); ?>%</span>
                            <span class="stat-label">Overall Rate</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo htmlspecialchars($attendance_stats['present_count']); ?></span>
                            <span class="stat-label">Present</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo htmlspecialchars($attendance_stats['total_sessions']); ?></span>
                            <span class="stat-label">Total Sessions</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo htmlspecialchars($attendance_stats['total_sessions'] - $attendance_stats['present_count']); ?></span>
                            <span class="stat-label">Absent</span>
                        </div>
                    </div>
                </div>

                <!-- Courses Under 85% Warning -->
                <?php if (!empty($courses_under_85)): ?>
                    <div class="courses-warning">
                        <div class="warning-header">
                            <div class="warning-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 text-warning">Attention Required</h5>
                                <p class="mb-0 text-muted">Courses with attendance below 85% minimum rate</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <?php foreach ($courses_under_85 as $course): ?>
                                <div class="col-md-6">
                                    <div class="alert alert-warning border-warning mb-0">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-book me-3 text-warning"></i>
                                            <div>
                                                <strong><?php echo htmlspecialchars($course['course_code']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($course['course_name']); ?></small><br>
                                                <span class="badge bg-warning text-dark"><?php echo $course['attendance_percentage']; ?>% attendance</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Profile Edit Form -->
                <div class="form-container">
                    <h3 class="card-title">
                        <i class="fas fa-edit"></i>Edit Profile Information
                    </h3>

                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" name="first_name" class="form-control"
                                           value="<?php echo htmlspecialchars($student['first_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" name="last_name" class="form-control"
                                           value="<?php echo htmlspecialchars($student['last_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email Address *</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Registration Number</label>
                            <input type="text" class="form-control"
                                   value="<?php echo htmlspecialchars($student['reg_no'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            <small class="text-muted">Registration number cannot be changed</small>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                            <button type="reset" class="btn btn-secondary">
                                <i class="fas fa-undo me-2"></i>Reset Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div>&copy; 2025 Rwanda Polytechnic | Student Management System</div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.querySelector('.student-sidebar');
            if (sidebar) {
                sidebar.classList.toggle('show');
            }
        }

        // Auto-hide mobile sidebar when clicking outside
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.student-sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (window.innerWidth <= 768 && sidebar && toggle) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>