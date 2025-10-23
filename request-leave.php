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
               d.name as department_name, d.id as department_id, o.name as option_name
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

// Get courses for the student's year and option (with lecturers)
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.course_name, c.course_code, c.lecturer_id,
               CONCAT(l.first_name, ' ', l.last_name) as lecturer_name
        FROM courses c
        LEFT JOIN lecturers l ON c.lecturer_id = l.id
        WHERE c.option_id = ? AND c.year = ? AND c.status = 'active'
        ORDER BY c.course_name
    ");
    $stmt->execute([$student['option_id'], $student['year_level']]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $courses = [];
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $leave_type = trim($_POST['leave_type'] ?? '');
    $request_type = trim($_POST['request_type'] ?? ''); // Lecturer or HOD
    $course_id = trim($_POST['course_id'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $supporting_file = null;
    $requested_to = '';

    // Validation
    $errors = [];

    if (empty($leave_type)) {
        $errors[] = "Please select a leave type";
    }

    if (empty($request_type)) {
        $errors[] = "Please select who to send the request to";
    }
    
    // If requesting to lecturer, course must be selected
    if ($request_type === 'Lecturer') {
        if (empty($course_id)) {
            $errors[] = "Please select a course when requesting to lecturer";
        } else {
            // Get lecturer name from course
            foreach ($courses as $course) {
                if ($course['id'] == $course_id) {
                    $requested_to = $course['lecturer_name'] ?? 'Lecturer';
                    break;
                }
            }
        }
    } else {
        $requested_to = 'HOD';
    }

    if (empty($start_date)) {
        $errors[] = "Please select a start date";
    }

    if (empty($end_date)) {
        $errors[] = "Please select an end date";
    }

    if (empty($reason)) {
        $errors[] = "Please provide a reason for the leave";
    }

    if (!empty($start_date) && !empty($end_date)) {
        $start = strtotime($start_date);
        $end = strtotime($end_date);
        $today = strtotime(date('Y-m-d'));

        if ($start > $end) {
            $errors[] = "End date cannot be before start date";
        }

        if ($start < $today) {
            $errors[] = "Start date cannot be in the past";
        }
    }
    
    // Handle file upload
    if (isset($_FILES['supporting_file']) && $_FILES['supporting_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['supporting_file']['type'], $allowed_types)) {
            $errors[] = "Only PDF, JPG, and PNG files are allowed";
        }
        
        if ($_FILES['supporting_file']['size'] > $max_size) {
            $errors[] = "File size must not exceed 5MB";
        }
        
        if (empty($errors)) {
            $upload_dir = 'uploads/leave_documents/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['supporting_file']['name'], PATHINFO_EXTENSION);
            $file_name = 'leave_' . $student['id'] . '_' . time() . '.' . $file_extension;
            $supporting_file = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['supporting_file']['tmp_name'], $supporting_file)) {
                $errors[] = "Failed to upload file";
                $supporting_file = null;
            }
        }
    }

    if (empty($errors)) {
        try {
            // Prepare reason field with leave details
            $full_reason = "Leave Type: " . $leave_type . "\n";
            $full_reason .= "Period: " . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date)) . "\n";
            $full_reason .= "Reason: " . $reason;
            
            // Insert leave request
            $stmt = $pdo->prepare("
                INSERT INTO leave_requests
                (student_id, requested_to, reason, supporting_file, status, requested_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$student['id'], $requested_to, $full_reason, $supporting_file]);

            $message = "Leave request submitted successfully! You will be notified once it's reviewed.";
            $message_type = "success";
            
            // Clear form by redirecting
            header("Location: leave-status.php?success=1");
            exit();

        } catch (Exception $e) {
            error_log("Leave request error: " . $e->getMessage());
            $message = "Failed to submit leave request. Please try again.";
            $message_type = "error";
        }
    } else {
        $message = implode("<br>", $errors);
        $message_type = "error";
    }
}

$page_title = "Request Leave";
$current_page = "request-leave";
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
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 4px;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 0.95rem;
            margin-bottom: 0;
        }

        .form-container {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px rgba(30, 58, 138, 0.08);
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .form-title i {
            color: var(--primary-dark-blue);
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            display: block;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            padding: 12px 16px;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-dark-blue);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
            outline: none;
        }

        .form-control:invalid {
            border-color: var(--danger-color);
        }

        .textarea-group textarea {
            resize: vertical;
            min-height: 120px;
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

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
            color: white;
        }

        .alert {
            border-radius: 8px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
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

        .leave-info {
            background: var(--light-blue);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid rgba(30, 58, 138, 0.1);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-dark-blue);
            box-shadow: 0 2px 8px rgba(30, 58, 138, 0.1);
        }

        .info-content h6 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .info-content p {
            font-size: 0.8rem;
            color: var(--text-light);
            margin: 0;
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

            .form-container {
                padding: 24px;
            }

            .info-grid {
                grid-template-columns: 1fr;
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
                <i class="fas fa-file-signature me-2"></i><?php echo $page_title; ?>
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
                    <i class="fas fa-file-signature me-3 text-primary"></i><?php echo $page_title; ?>
                </h1>
                <p class="page-subtitle">Submit a leave request for approval</p>
            </div>
        </div>

        <!-- Leave Information -->
        <div class="leave-info">
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="info-content">
                        <h6>Request Processing</h6>
                        <p>Leave requests are typically processed within 2-3 business days</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="info-content">
                        <h6>Advance Notice</h6>
                        <p>Please submit requests at least 3 days in advance when possible</p>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="info-content">
                        <h6>Documentation</h6>
                        <p>Medical leave may require supporting documentation</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Message Display -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?>">
                <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Leave Request Form -->
        <div class="form-container">
            <h3 class="form-title">
                <i class="fas fa-plus-circle"></i>Submit Leave Request
            </h3>

            <form method="POST" id="leaveForm" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Leave Type *</label>
                            <select name="leave_type" class="form-select" required>
                                <option value="">Select leave type</option>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Personal Leave">Personal Leave</option>
                                <option value="Family Emergency">Family Emergency</option>
                                <option value="Medical Appointment">Medical Appointment</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Request To *</label>
                            <select name="request_type" id="requestType" class="form-select" required>
                                <option value="">Select recipient</option>
                                <option value="Lecturer">Lecturer</option>
                                <option value="HOD">Head of Department (HOD)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Course Selection (shown only when Lecturer is selected) -->
                <div class="row" id="courseRow" style="display: none;">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="form-label">Select Course *</label>
                            <select name="course_id" id="courseSelect" class="form-select">
                                <option value="">Select a course</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            data-lecturer="<?php echo htmlspecialchars($course['lecturer_name'] ?? 'Not Assigned'); ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                        <?php if ($course['lecturer_name']): ?>
                                            (Lecturer: <?php echo htmlspecialchars($course['lecturer_name']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Your request will be sent to the lecturer of the selected course</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Student ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['reg_no'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['department_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Start Date *</label>
                            <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">End Date *</label>
                            <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Supporting Document (Optional)</label>
                    <input type="file" name="supporting_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <small class="text-muted">Upload medical certificate or other supporting documents (PDF, JPG, PNG - Max 5MB)</small>
                </div>

                <div class="form-group textarea-group">
                    <label class="form-label">Reason for Leave *</label>
                    <textarea name="reason" class="form-control" placeholder="Please provide detailed reason for your leave request..." required maxlength="500"></textarea>
                    <small class="text-muted">Maximum 500 characters</small>
                </div>

                <div class="d-flex gap-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo me-2"></i>Reset Form
                    </button>
                </div>
            </form>
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

        // Form validation
        document.getElementById('leaveForm').addEventListener('submit', function(e) {
            const startDate = new Date(this.start_date.value);
            const endDate = new Date(this.end_date.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (startDate < today) {
                e.preventDefault();
                alert('Start date cannot be in the past');
                return false;
            }

            if (endDate < startDate) {
                e.preventDefault();
                alert('End date cannot be before start date');
                return false;
            }
        });

        // Auto-set minimum end date when start date changes
        document.querySelector('input[name="start_date"]').addEventListener('change', function() {
            const endDateInput = document.querySelector('input[name="end_date"]');
            endDateInput.min = this.value;
        });

        // Show/hide course selection based on request type
        document.getElementById('requestType').addEventListener('change', function() {
            const courseRow = document.getElementById('courseRow');
            const courseSelect = document.getElementById('courseSelect');
            
            if (this.value === 'Lecturer') {
                courseRow.style.display = 'block';
                courseSelect.required = true;
            } else {
                courseRow.style.display = 'none';
                courseSelect.required = false;
                courseSelect.value = '';
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>