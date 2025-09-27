<?php
require_once "config.php"; // PDO connection - must be first
require_once "session_check.php"; // Session management - requires config.php constants
session_start(); // Start session after config and session_check
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_role(['hod']);

// Get HoD's department ID (via departments.hod_id referencing users.id)
$hod_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id AS department_id FROM departments WHERE hod_id = ? LIMIT 1");
$stmt->execute([$hod_id]);
$hod = $stmt->fetch(PDO::FETCH_ASSOC);
$hod_department = $hod['department_id'] ?? null;

// Pagination & Search
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = '';
$params = [$hod_department];

if ($search !== '') {
    $search_sql = " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR id_number LIKE ?)";
    $like_search = "%$search%";
    array_push($params, $like_search, $like_search, $like_search, $like_search);
}

// Count total lecturers
$stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE department_id = ? $search_sql");
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch lecturers with pagination & search
$params[] = $limit;
$params[] = $offset;
$stmt = $pdo->prepare("SELECT * FROM lecturers WHERE department_id = ? $search_sql ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->execute($params);
$lecturers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add lecturer form
$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $formError = 'CSRF token mismatch.';
    } else {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $gender     = $_POST['gender'];
    $dob        = $_POST['dob'];
    $id_number  = trim($_POST['id_number']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $education_level = $_POST['education_level'];
    $role = 'lecturer';
    $password_plain = '12345';
    $password = password_hash($password_plain, PASSWORD_DEFAULT);

    try {
        if (!$hod_department) {
            throw new Exception('Your department could not be determined. Please ensure you are registered as HoD for a department.');
        }

        // Server-side validation
        if (empty($first_name) || empty($last_name) || empty($gender) || empty($dob) ||
            empty($id_number) || empty($email) || empty($education_level)) {
            throw new Exception('All required fields must be filled.');
        }

        // Email validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }

        // Phone number validation - exactly 10 digits only
        if (!empty($phone)) {
            if (!preg_match('/^\d{10}$/', $phone)) {
                throw new Exception('Phone number must be exactly 10 digits only (no spaces, dashes, or country codes).');
            }
        }

        // ID number validation - exactly 16 characters
        if (strlen($id_number) !== 16) {
            throw new Exception('ID Number must be exactly 16 characters long.');
        }

        // Date of birth validation - must be at least 21 years old
        if (!empty($dob)) {
            $birthDate = new DateTime($dob);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;

            if ($age < 21) {
                throw new Exception('Lecturer must be at least 21 years old. Please select a valid date of birth.');
            }

            if ($age > 100) {
                throw new Exception('Please enter a valid date of birth. Age cannot exceed 100 years.');
            }
        }

        // Check for unique email and ID number
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE email = ? OR id_number = ?");
        $stmt->execute([$email, $id_number]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email or ID Number already exists.');
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email already exists in the system.');
        }

        $pdo->beginTransaction();

        // Photo upload with security checks
        $photo_filename = null;
        if (!empty($_FILES['photo']['name'])) {
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_types)) {
                throw new Exception('Invalid file type. Only JPG, PNG, GIF allowed.');
            }
            if ($_FILES['photo']['size'] > $max_size) {
                throw new Exception('File too large. Maximum size is 2MB.');
            }
            $target_dir = "uploads/lecturers/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $photo_filename = uniqid('lec_') . '.' . $ext;
            $target_file = $target_dir . $photo_filename;
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                throw new Exception('Failed to upload photo.');
            }
        }

        // Insert into lecturers table
        $stmt = $pdo->prepare("INSERT INTO lecturers 
            (first_name, last_name, gender, dob, id_number, email, phone, department_id, education_level, role, password, photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $first_name,
            $last_name,
            $gender,
            $dob,
            $id_number,
            $email,
            $phone,
            $hod_department,
            $education_level,
            $role,
            $password,
            $photo_filename
        ]);

        // Get new lecturer ID
        $lecturer_id = (int)$pdo->lastInsertId();

        // Generate unique username: firstname.lastname (lowercase)
        $username_base = strtolower(trim(preg_replace('/\s+/', '.', $first_name . ' ' . $last_name)));
        $username = $username_base;
        $suffix = 0;
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        do {
            $checkStmt->execute([$username]);
            $exists = (int)$checkStmt->fetchColumn() > 0;
            if ($exists) { $suffix++; $username = $username_base . $suffix; }
        } while ($exists);

        // Insert into users table (let users.id auto-increment to avoid PK conflicts)
        $stmtUser = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?)");
        $stmtUser->execute([
            $username,
            $email,
            $password,
            'lecturer',
            date('Y-m-d H:i:s')
        ]);

        // Note: User and lecturer records are created successfully
        // The user_id relationship is not established due to missing user_id column in lecturers table
        // This is logged for potential future enhancement

        // Handle option assignments (required, but allow empty if no options exist)
        $selected_options = $_POST['selected_options'] ?? [];
        if (empty($selected_options) || !is_array($selected_options)) {
            // Check if there are any options in the department
            $option_check_stmt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE department_id = ?");
            $option_check_stmt->execute([$hod_department]);
            $option_count = $option_check_stmt->fetchColumn();

            if ($option_count > 0) {
                throw new Exception('At least one option must be selected for the lecturer.');
            }

            // If no options exist in department, allow empty selection
            $option_ids = [];
        } else {
            $option_ids = array_filter(array_map('intval', $selected_options));
            if (empty($option_ids)) {
                throw new Exception('Invalid option selection.');
            }
        }

        if (!empty($option_ids)) {
            // Validate that all selected options belong to the HoD's department
            $placeholders = str_repeat('?,', count($option_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as valid_count
                FROM options
                WHERE id IN ($placeholders) AND department_id = ?
            ");
            $stmt->execute(array_merge($option_ids, [$hod_department]));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['valid_count'] != count($option_ids)) {
                throw new Exception('Some selected options do not belong to your department.');
            }
        }

        // Handle course assignments if any courses were selected
        $selected_courses = $_POST['selected_courses'] ?? [];
        if (!empty($selected_courses) && is_array($selected_courses)) {
            try {
                // Validate that all selected courses belong to the HoD's department
                $course_ids = array_filter(array_map('intval', $selected_courses));
                if (!empty($course_ids)) {
                    $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as valid_count
                        FROM courses
                        WHERE id IN ($placeholders) AND department_id = ?
                    ");
                    $stmt->execute(array_merge($course_ids, [$hod_department]));
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($result['valid_count'] == count($course_ids)) {
                        // Assign courses to the lecturer
                        $placeholders = str_repeat('?,', count($course_ids) - 1) . '?';
                        $stmt = $pdo->prepare("
                            UPDATE courses
                            SET lecturer_id = ?
                            WHERE id IN ($placeholders)
                        ");
                        $stmt->execute(array_merge([$lecturer_id], $course_ids));

                        // Log the course assignments
                        $log_stmt = $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, details, created_at)
                            VALUES (?, 'course_assignment_registration', ?, NOW())
                        ");
                        $log_stmt->execute([
                            $hod_id,
                            "Assigned " . count($course_ids) . " courses to newly registered lecturer '{$first_name} {$last_name}' (ID: $lecturer_id)"
                        ]);
                    }
                }
            } catch (Exception $e) {
                // Log course assignment error but don't fail the entire registration
                error_log("Course assignment error during lecturer registration: " . $e->getMessage());
            }
        }

        $pdo->commit();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        // Include course and option assignment info in success message
        $course_count = !empty($selected_courses) ? count(array_filter($selected_courses)) : 0;
        $option_count = count($option_ids);
        $course_message = $course_count > 0 ? " and assigned to $course_count course(s)" : "";
        $option_message = $option_count > 0 ? " with access to $option_count option(s)" : " (no option access assigned)";

        $_SESSION['success_message'] = "Lecturer added successfully$course_message$option_message! Login credentials: Username: $username, Password: 12345";
        header("Location: hod-manage-lecturers.php");
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('Error adding lecturer: ' . $e->getMessage());
        $formError = 'Failed to add lecturer: ' . htmlspecialchars($e->getMessage());
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Lecturers | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
  :root {
    --primary-color: #003366;
    --secondary-color: #0059b3;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --danger-color: #dc3545;
    --light-bg: #f8f9fa;
    --card-shadow: 0 4px 12px rgba(0, 51, 102, 0.1);
    --card-shadow-hover: 0 8px 25px rgba(0, 51, 102, 0.15);
  }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #0066cc 0%, #004b99 50%, #003366 100%);
    margin: 0;
    min-height: 100vh;
  }

  .sidebar {
    position: fixed; top: 0; left: 0; width: 250px; height: 100vh;
    background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white; padding-top: 20px; box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    z-index: 1000; overflow-y: auto;
  }

  .sidebar .sidebar-header {
    text-align: center; margin-bottom: 20px; padding: 0 20px;
  }

  .sidebar a {
    display: block; padding: 12px 20px; color: #fff; text-decoration: none;
    transition: all 0.3s ease; margin: 2px 10px; border-radius: 8px; font-weight: 500;
  }

  .sidebar a:hover, .sidebar a.active {
    background-color: rgba(255,255,255,0.1);
    transform: translateX(5px);
  }

  .topbar {
    margin-left: 250px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px);
    padding: 15px 30px; border-bottom: 1px solid rgba(0,51,102,0.1);
    position: sticky; top: 0; z-index: 900; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }

  .main-content {
    margin-left: 250px; padding: 30px;
  }

  .form-section, .table-section {
    background: #fff; border-radius: 15px; padding: 30px;
    box-shadow: var(--card-shadow); margin-bottom: 30px;
    transition: all 0.3s ease; border: 1px solid rgba(0,51,102,0.05);
  }

  .form-section:hover, .table-section:hover {
    transform: translateY(-5px);
    box-shadow: var(--card-shadow-hover);
  }

  .form-section h5, .table-section h5 {
    font-weight: 600; margin-bottom: 25px; color: var(--primary-color);
    border-bottom: 3px solid var(--secondary-color); padding-bottom: 10px;
  }

  .table {
    background: #fff; border-radius: 10px; overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
  }

  .table thead th {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white; font-weight: 600; border: none; padding: 15px;
    font-size: 0.9rem;
  }

  .table tbody td {
    padding: 12px 15px; vertical-align: middle;
    border-bottom: 1px solid rgba(0,51,102,0.1);
  }

  .table tbody tr:hover {
    background-color: rgba(0,102,204,0.02);
  }

  .table img {
    width: 45px; height: 45px; border-radius: 50%; object-fit: cover;
    border: 2px solid var(--secondary-color);
  }

  .btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border: none; border-radius: 8px; font-weight: 500;
    transition: all 0.3s ease;
  }

  .btn-primary:hover {
    background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
    transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,51,102,0.3);
  }

  .btn-info {
    background: linear-gradient(135deg, var(--info-color), #20a8d8);
    border: none; border-radius: 8px;
  }

  .btn-warning {
    background: linear-gradient(135deg, var(--warning-color), #e0a800);
    border: none; border-radius: 8px;
  }

  .btn-danger {
    background: linear-gradient(135deg, var(--danger-color), #c82333);
    border: none; border-radius: 8px;
  }

  .badge {
    font-size: 0.75rem; font-weight: 600;
  }

  .footer {
    text-align: center; margin-left: 250px; padding: 20px;
    font-size: 0.9rem; color: #666; background: rgba(255,255,255,0.9);
    backdrop-filter: blur(10px); border-top: 1px solid rgba(0,51,102,0.1);
  }

  .search-box {
    background: #fff; border-radius: 25px; padding: 5px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }

  .search-box input {
    border: none; background: transparent; padding: 8px 15px;
  }

  .search-box input:focus {
    outline: none; box-shadow: none;
  }

  .search-box .btn {
    border-radius: 20px; padding: 8px 20px;
  }

  .modal-content {
    border-radius: 15px; border: none;
    box-shadow: 0 10px 30px rgba(0,51,102,0.2);
    border: 2px solid rgba(0,102,204,0.1);
  }

  .modal-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white; border-radius: 15px 15px 0 0;
    border-bottom: 2px solid rgba(255,255,255,0.1);
  }

  .modal-title {
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0,0,0,0.1);
  }

  .modal-body {
    padding: 30px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  }

  .modal-footer {
    background: #fff; border-radius: 0 0 15px 15px;
    border-top: 1px solid rgba(0,51,102,0.1);
  }

  .lecturer-details {
    background: rgba(255,255,255,0.8);
    border-radius: 10px; padding: 20px;
    border-left: 4px solid var(--secondary-color);
  }

  .detail-item {
    margin-bottom: 15px; padding: 10px;
    background: rgba(0,102,204,0.05); border-radius: 8px;
  }

  .detail-label {
    font-weight: 600; color: var(--primary-color);
    margin-bottom: 5px;
  }

  .detail-value {
    color: #333; font-size: 1.1rem;
  }

  .form-control, .form-select {
    border-radius: 8px; border: 1px solid rgba(0,51,102,0.2);
    transition: all 0.3s ease;
  }

  .form-control:focus, .form-select:focus {
    border-color: var(--secondary-color);
    box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.25);
  }

  .form-control:invalid {
    border-color: var(--danger-color);
  }

  .form-control:valid {
    border-color: var(--success-color);
  }

  .validation-message {
    font-size: 0.875rem;
    margin-top: 0.25rem;
  }

  .validation-message.error {
    color: var(--danger-color);
  }

  .validation-message.success {
    color: var(--success-color);
  }

  .validation-message.info {
    color: var(--info-color);
  }

  /* Character counter styling */
  #id-counter {
    font-weight: 600;
    min-width: 40px;
    text-align: right;
    color: var(--danger-color) !important;
  }

  /* Enhanced validation message styling */
  .validation-message {
    font-size: 0.875rem;
    margin-top: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    transition: all 0.3s ease;
  }

  .validation-message.success {
    background-color: rgba(40, 167, 69, 0.1);
    border-left: 3px solid var(--success-color);
  }

  .validation-message.error {
    background-color: rgba(220, 53, 69, 0.1);
    border-left: 3px solid var(--danger-color);
  }

  .validation-message.info {
    background-color: rgba(23, 162, 184, 0.1);
    border-left: 3px solid var(--info-color);
  }

  .alert {
    border-radius: 10px; border: none;
  }

  .alert-success {
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    color: #155724;
  }

  .alert-danger {
    background: linear-gradient(135deg, #f8d7da, #f5c6cb);
    color: #721c24;
  }

  .pagination .page-link {
    color: var(--primary-color); border-color: rgba(0,51,102,0.2);
    border-radius: 8px; margin: 0 2px;
  }

  .pagination .page-item.active .page-link {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border-color: var(--primary-color);
  }

  .stats-badge {
    background: linear-gradient(135deg, var(--secondary-color), var(--info-color));
    color: white; padding: 8px 16px; border-radius: 20px;
    font-size: 0.8rem; font-weight: 600;
  }

  @media (max-width: 768px) {
    .sidebar, .topbar, .main-content, .footer {
      margin-left: 0 !important; width: 100%;
    }
    .sidebar { display: none; }
    .table-responsive { font-size: 0.9rem; }

    .modal-dialog {
      margin: 0.5rem;
    }

    .modal-body .row {
      margin: 0;
    }

    .modal-body .col-md-4, .modal-body .col-md-8 {
      margin-bottom: 1rem;
    }
  }

  /* Loading animation */
  .loading {
    display: inline-block; width: 20px; height: 20px;
    border: 3px solid rgba(255,255,255,.3); border-radius: 50%;
    border-top-color: #fff; animation: spin 1s ease-in-out infinite;
  }

  @keyframes spin {
    to { transform: rotate(360deg); }
  }

  /* Fix for excessive animations */
  .fa-spinner {
    animation-duration: 1s !important;
  }

  /* Prevent animation conflicts */
  .card:hover .fa-spinner {
    animation-play-state: running;
  }

  /* Custom scrollbar */
  ::-webkit-scrollbar {
    width: 8px;
  }

  ::-webkit-scrollbar-track {
    background: #f1f1f1;
  }

  ::-webkit-scrollbar-thumb {
    background: var(--primary-color); border-radius: 4px;
  }

  ::-webkit-scrollbar-thumb:hover {
    background: var(--secondary-color);
  }

  /* Tooltip fixes */
  .tooltip {
    z-index: 1050;
  }

  .btn {
    position: relative;
  }

  /* Prevent tooltip conflicts with modal */
  .modal.show ~ .tooltip {
    display: none !important;
  }

  /* Fix for excessive visual feedback */
  * {
    animation-duration: 0.3s !important;
    transition-duration: 0.3s !important;
  }

  /* Prevent rapid firing animations */
  .btn:hover, .card:hover {
    transition: all 0.3s ease;
  }

  /* Stop loading animations after page load */
  body:not(.loading) .fa-spinner {
    animation: none !important;
  }

  /* Enhanced Modal Styles */
  .modal-content {
    border: none;
    border-radius: 15px;
    overflow: hidden;
  }

  .bg-gradient-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)) !important;
  }

  .profile-photo-container {
    position: relative;
  }

  .photo-badge {
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    border: 3px solid white;
  }

  .info-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border-left: 4px solid var(--secondary-color);
    transition: all 0.3s ease;
    opacity: 0;
    transform: translateY(20px);
  }

  .info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }

  .info-header {
    color: var(--primary-color);
    font-size: 0.9rem;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
  }

  .info-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: #333;
  }

  .quick-info {
    border-left: 4px solid var(--info-color);
  }

  .contact-section {
    border-left: 4px solid var(--success-color);
  }

  .system-info {
    border-left: 4px solid var(--warning-color);
  }

  .modal-footer {
    border-top: 1px solid rgba(0,51,102,0.1);
  }

  /* Course and Option selection styling */
  .course-checkbox, .option-checkbox {
    margin-right: 8px;
  }

  .course-selection-container, .option-selection-container {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid rgba(0,51,102,0.1);
    border-radius: 8px;
    background-color: #f8f9fa;
    padding: 15px;
  }

  .course-item {
    padding: 12px;
    margin-bottom: 8px;
    border-radius: 6px;
    border: 1px solid rgba(0,51,102,0.08);
    transition: all 0.2s ease;
    background-color: white;
  }

  .course-item:hover {
    background-color: rgba(0,102,204,0.05);
    border-color: rgba(0,102,204,0.2);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,51,102,0.1);
  }

  .course-item:last-child {
    margin-bottom: 0;
  }

  .course-info {
    font-size: 0.8rem;
    color: #666;
    margin-top: 6px;
  }

  .course-info .row {
    margin: 0 -5px;
  }

  .course-info .col-auto {
    padding: 0 5px;
  }

  .course-info small {
    font-size: 0.75rem;
  }

  /* Disabled course items */
  .course-item.disabled {
    opacity: 0.6;
    background-color: #f8f9fa;
  }

  .course-item.disabled:hover {
    background-color: #f8f9fa;
    border-color: rgba(0,51,102,0.08);
    transform: none;
    box-shadow: none;
  }

  /* Responsive Modal */
  @media (max-width: 992px) {
    .modal-dialog {
      margin: 1rem;
    }

    .profile-photo-container img,
    .profile-photo-container div {
      width: 120px !important;
      height: 120px !important;
    }

    .photo-badge {
      width: 30px !important;
      height: 30px !important;
    }
  }

  /* Animation for modal appearance */
  .modal.fade .modal-dialog {
    transform: scale(0.8) translateY(-50px);
    transition: transform 0.3s ease-out;
  }

  .modal.show .modal-dialog {
    transform: scale(1) translateY(0);
  }
</style>
</head>
<body>

<div class="sidebar">
  <div class="sidebar-header">
    <h4>👔 Head of Department</h4>
    <hr style="border-color:#ffffff66;">
  </div>
  <a href="hod-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
  <a href="hod-department-reports.php"><i class="fas fa-chart-bar me-2"></i> Department Reports</a>
  <a href="hod-leave-management.php"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave Requests</a>
  <a href="#" onclick="showCourseAssignmentModal()" style="background-color: rgba(255,255,255,0.1);"><i class="fas fa-book me-2"></i> Assign Courses</a>
  <a href="hod-manage-lecturers.php"><i class="fas fa-user-plus me-2"></i> Manage Lecturers</a>
  <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar">
  <h5 class="m-0 fw-bold">Manage Lecturers</h5>
  <span>HoD Panel</span>
</div>

<div class="main-content">
  <!-- Add Lecturer Form -->
  <div class="form-section">
    <h5>Add New Lecturer</h5>
    <?php if(!empty($formError)): ?><div class="alert alert-danger"><?= $formError ?></div><?php endif; ?>
    <?php if(isset($_SESSION['success_message'])): ?><div class="alert alert-success"><?= $_SESSION['success_message'] ?></div><?php unset($_SESSION['success_message']); endif; ?>
    <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm();">
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="first_name">First Name</label><input type="text" id="first_name" name="first_name" class="form-control" required aria-required="true"></div>
        <div class="col-md-6"><label class="form-label" for="last_name">Last Name</label><input type="text" id="last_name" name="last_name" class="form-control" required aria-required="true"></div>
        <div class="col-md-6"><label class="form-label" for="gender">Gender</label>
          <select id="gender" name="gender" class="form-select" required aria-required="true">
            <option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label" for="dob">Date of Birth</label><input type="date" id="dob" name="dob" class="form-control" required aria-required="true" max="<?php echo date('Y-m-d', strtotime('-21 years')); ?>"><small class="form-text text-muted">Must be at least 21 years old.</small></div>
        <div class="col-md-6"><label class="form-label" for="id_number">ID Number</label><input type="text" id="id_number" name="id_number" class="form-control" required aria-required="true" maxlength="16" placeholder="1234567890123456"><div class="d-flex justify-content-between"><small class="form-text text-muted">Must be exactly 16 characters long.</small><small id="id-counter" class="form-text text-info" style="display: none;">0/16</small></div></div>
        <div class="col-md-6"><label class="form-label" for="email">Email</label><input type="email" id="email" name="email" class="form-control" required aria-required="true"></div>
        <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input type="text" id="phone" name="phone" class="form-control" placeholder="1234567890"><small class="form-text text-muted">Optional. Must be exactly 10 digits only.</small></div>
        <div class="col-md-6"><label class="form-label" for="education_level">Education Level</label>
          <select id="education_level" name="education_level" class="form-select" required aria-required="true">
            <option value="">Select</option><option value="Bachelor's">Bachelor's</option><option value="Master's">Master's</option><option value="PhD">PhD</option><option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label" for="photo">Photo</label><input type="file" id="photo" name="photo" class="form-control" accept="image/*" aria-describedby="photoHelp"><small id="photoHelp" class="form-text text-muted">Optional. Max 2MB, JPG/PNG/GIF only.</small></div>
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

      <div class="row g-3 mt-3">
      </div>
      <div class="mt-4"><button type="submit" class="btn btn-primary" id="addBtn"><i class="fas fa-plus me-2"></i>Add Lecturer</button></div>
    </form>
  </div>

  <!-- Statistics Cards -->
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <i class="fas fa-users fa-2x text-primary mb-3"></i>
          <h4 class="text-primary mb-1" id="totalLecturers">
            <i class="fas fa-spinner fa-spin" style="animation-duration: 1s;"></i>
          </h4>
          <p class="text-muted mb-0">Total Lecturers</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <i class="fas fa-mars fa-2x text-info mb-3"></i>
          <h4 class="text-info mb-1" id="maleLecturers">
            <i class="fas fa-spinner fa-spin" style="animation-duration: 1s;"></i>
          </h4>
          <p class="text-muted mb-0">Male Lecturers</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <i class="fas fa-venus fa-2x text-danger mb-3"></i>
          <h4 class="text-danger mb-1" id="femaleLecturers">
            <i class="fas fa-spinner fa-spin" style="animation-duration: 1s;"></i>
          </h4>
          <p class="text-muted mb-0">Female Lecturers</p>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body text-center">
          <i class="fas fa-graduation-cap fa-2x text-success mb-3"></i>
          <h4 class="text-success mb-1" id="phdLecturers">
            <i class="fas fa-spinner fa-spin" style="animation-duration: 1s;"></i>
          </h4>
          <p class="text-muted mb-0">PhD Holders</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Lecturer List Table -->
  <div class="table-section">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">
        <i class="fas fa-users me-2"></i>Existing Lecturers
        <span class="badge bg-primary ms-2" id="currentCount">
          <?= count($lecturers) ?>
        </span>
      </h5>
      <div class="d-flex align-items-center">
        <small class="text-muted me-3">
          Page <?= $page ?> of <?= $total_pages ?>
        </small>
      </div>
    </div>

    <form method="GET" class="mb-4" role="search">
      <div class="search-box d-flex">
        <label for="search" class="visually-hidden">Search lecturers</label>
        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
               class="form-control border-0" placeholder="Search by name, email or ID"
               aria-describedby="searchHelp">
        <button type="submit" class="btn btn-primary" aria-label="Search">
          <i class="fas fa-search"></i>
        </button>
      </div>
      <small id="searchHelp" class="form-text text-muted mt-2">
        <i class="fas fa-info-circle me-1"></i>
        Search by first name, last name, email, or ID number.
      </small>
    </form>

    <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead style="position: sticky; top: 0; z-index: 1;">
        <tr>
          <th class="text-nowrap" style="width: 60px;">#</th>
          <th style="min-width: 180px;">
            <i class="fas fa-user me-2"></i>Name
          </th>
          <th class="text-nowrap d-none d-md-table-cell" style="width: 80px;">
            <i class="fas fa-venus-mars me-1"></i>Gender
          </th>
          <th class="text-nowrap d-none d-lg-table-cell" style="width: 140px;">
            <i class="fas fa-id-card me-1"></i>ID Number
          </th>
          <th style="min-width: 200px;">
            <i class="fas fa-envelope me-1"></i>Email
          </th>
          <th class="text-nowrap d-none d-md-table-cell" style="width: 120px;">
            <i class="fas fa-phone me-1"></i>Phone
          </th>
          <th class="text-nowrap d-none d-lg-table-cell" style="width: 100px;">
            <i class="fas fa-graduation-cap me-1"></i>Education
          </th>
          <th class="text-center d-none d-md-table-cell" style="width: 80px;">
            <i class="fas fa-camera me-1"></i>Photo
          </th>
          <th class="text-center" style="width: 200px;">
            <i class="fas fa-cogs me-1"></i>Actions
          </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($lecturers as $index => $lec): ?>
        <tr style="transition: all 0.2s ease;">
          <td class="text-nowrap fw-bold text-primary">
            <span class="badge bg-light text-primary border">#<?= ($page-1)*$limit + $index+1 ?></span>
          </td>
          <td>
            <div class="d-flex align-items-center">
              <div class="fw-semibold text-dark">
                <?= htmlspecialchars($lec['first_name'] . ' ' . $lec['last_name']) ?>
              </div>
            </div>
          </td>
          <td class="text-nowrap d-none d-md-table-cell">
            <span class="badge <?= $lec['gender'] === 'Male' ? 'bg-info' : 'bg-danger' ?> bg-opacity-75">
              <i class="fas fa-<?= $lec['gender'] === 'Male' ? 'mars' : 'venus' ?> me-1"></i>
              <?= htmlspecialchars($lec['gender']) ?>
            </span>
          </td>
          <td class="text-nowrap d-none d-lg-table-cell">
            <small class="text-muted font-monospace">
              <?= htmlspecialchars(substr($lec['id_number'], 0, 8)) ?>...
            </small>
          </td>
          <td>
            <div class="d-flex align-items-center">
              <i class="fas fa-envelope text-muted me-2"></i>
              <span class="text-truncate" title="<?= htmlspecialchars($lec['email']) ?>">
                <?= htmlspecialchars($lec['email']) ?>
              </span>
            </div>
          </td>
          <td class="text-nowrap d-none d-md-table-cell">
            <div class="d-flex align-items-center">
              <i class="fas fa-phone text-muted me-2"></i>
              <small><?= htmlspecialchars($lec['phone']) ?></small>
            </div>
          </td>
          <td class="text-nowrap d-none d-lg-table-cell">
            <span class="badge bg-success bg-opacity-75">
              <i class="fas fa-graduation-cap me-1"></i>
              <?= htmlspecialchars($lec['education_level']) ?>
            </span>
          </td>
          <td class="text-center d-none d-md-table-cell">
            <?php if($lec['photo'] && file_exists("uploads/lecturers/".$lec['photo'])): ?>
              <img src="uploads/lecturers/<?= htmlspecialchars($lec['photo']) ?>" alt="Photo"
                   class="rounded-circle border border-primary border-2"
                   style="width:40px;height:40px;object-fit:cover;"
                   data-bs-toggle="tooltip" title="View Photo">
            <?php else: ?>
              <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center"
                   style="width:40px;height:40px;">
                <i class="fas fa-user-circle text-secondary fa-lg"></i>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <div class="btn-group" role="group">
              <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal"
                data-name="<?= htmlspecialchars($lec['first_name'] . ' ' . $lec['last_name']) ?>"
                data-gender="<?= htmlspecialchars($lec['gender']) ?>"
                data-dob="<?= htmlspecialchars($lec['dob']) ?>"
                data-id-number="<?= htmlspecialchars($lec['id_number']) ?>"
                data-email="<?= htmlspecialchars($lec['email']) ?>"
                data-phone="<?= htmlspecialchars($lec['phone']) ?>"
                data-education="<?= htmlspecialchars($lec['education_level']) ?>"
                data-created="<?= htmlspecialchars($lec['created_at'] ?? 'Not available') ?>"
                data-updated="<?= htmlspecialchars($lec['updated_at'] ?? 'Not available') ?>"
                data-photo="<?= $lec['photo'] ? 'uploads/lecturers/' . htmlspecialchars($lec['photo']) : '' ?>"
                aria-label="View Details" data-bs-toggle="tooltip" title="View Details">
                <i class="fas fa-eye"></i>
              </button>

              <button class="btn btn-sm btn-primary" onclick="assignCoursesToLecturer(<?= $lec['id'] ?>, '<?= htmlspecialchars($lec['first_name'] . ' ' . $lec['last_name']) ?>')"
                aria-label="Assign Courses" data-bs-toggle="tooltip" title="Assign Courses">
                <i class="fas fa-book"></i>
              </button>

              <a href="edit-lecturer.php?id=<?= $lec['id'] ?>" class="btn btn-sm btn-warning"
                aria-label="Edit Lecturer" data-bs-toggle="tooltip" title="Edit Lecturer">
                <i class="fas fa-edit"></i>
              </a>

              <a href="delete-lecturer.php?id=<?= $lec['id'] ?>" class="btn btn-sm btn-danger"
                aria-label="Delete Lecturer" onclick="return confirm('Are you sure you want to delete this lecturer?')"
                data-bs-toggle="tooltip" title="Delete Lecturer">
                <i class="fas fa-trash"></i>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <!-- Enhanced Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg">
          <div class="modal-header bg-gradient-primary text-white position-relative">
            <h5 class="modal-title fw-bold" id="detailsModalLabel">
              <i class="fas fa-user-graduate me-2"></i>Lecturer Profile
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            <div class="position-absolute top-0 end-0 mt-2 me-3 opacity-75">
              <small class="badge bg-light text-primary">
                <i class="fas fa-id-badge me-1"></i>Profile View
              </small>
            </div>
          </div>

          <div class="modal-body p-4">
            <div class="row g-4">
              <!-- Profile Photo Section -->
              <div class="col-lg-4 text-center">
                <div class="profile-photo-container mb-3">
                  <div class="position-relative d-inline-block">
                    <img id="modalPhoto" src="" alt="Profile Photo"
                         class="img-fluid rounded-circle border border-4 shadow-sm"
                         style="width: 180px; height: 180px; object-fit: cover; background: linear-gradient(135deg, #f8f9fa, #e9ecef);"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="bg-light rounded-circle d-none align-items-center justify-content-center shadow-sm"
                         style="width: 180px; height: 180px; position: absolute; top: 0; left: 0;">
                      <i class="fas fa-user-circle text-muted fa-5x"></i>
                    </div>
                    <div class="photo-badge position-absolute bottom-0 end-0 bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 40px; height: 40px;">
                      <i class="fas fa-camera fa-sm"></i>
                    </div>
                  </div>
                </div>

                <!-- Quick Info -->
                <div class="quick-info bg-light rounded p-3">
                  <h6 class="text-primary mb-2">
                    <i class="fas fa-info-circle me-2"></i>Quick Info
                  </h6>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Status:</span>
                    <span class="badge bg-success">
                      <i class="fas fa-check-circle me-1"></i>Active
                    </span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted">Department:</span>
                    <span class="badge bg-info">
                      <i class="fas fa-building me-1"></i>Current Dept
                    </span>
                  </div>
                </div>
              </div>

              <!-- Details Section -->
              <div class="col-lg-8">
                <div class="lecturer-details">
                  <!-- Header with Name and Role -->
                  <div class="d-flex align-items-center justify-content-between mb-4">
                    <h4 id="modalName" class="text-primary mb-0">
                      <i class="fas fa-user-graduate me-2"></i>
                      <span id="modalNameText">Loading...</span>
                    </h4>
                    <div class="text-end">
                      <small class="text-muted">Lecturer ID</small>
                      <div class="fw-bold text-primary" id="modalIdNumber">-</div>
                    </div>
                  </div>

                  <!-- Main Information Grid -->
                  <div class="row g-3 mb-4">
                    <div class="col-md-6">
                      <div class="info-card h-100">
                        <div class="info-header">
                          <i class="fas fa-venus-mars me-2"></i>
                          <span class="fw-bold">Gender</span>
                        </div>
                        <div class="info-value" id="modalGender">-</div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="info-card h-100">
                        <div class="info-header">
                          <i class="fas fa-graduation-cap me-2"></i>
                          <span class="fw-bold">Education Level</span>
                        </div>
                        <div class="info-value" id="modalEducation">-</div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="info-card h-100">
                        <div class="info-header">
                          <i class="fas fa-birthday-cake me-2"></i>
                          <span class="fw-bold">Date of Birth</span>
                        </div>
                        <div class="info-value" id="modalDob">-</div>
                      </div>
                    </div>

                    <div class="col-md-6">
                      <div class="info-card h-100">
                        <div class="info-header">
                          <i class="fas fa-phone me-2"></i>
                          <span class="fw-bold">Phone Number</span>
                        </div>
                        <div class="info-value" id="modalPhone">-</div>
                      </div>
                    </div>
                  </div>

                  <!-- Contact Information -->
                  <div class="contact-section bg-light rounded p-3 mb-3">
                    <h6 class="text-primary mb-3">
                      <i class="fas fa-address-book me-2"></i>Contact Information
                    </h6>
                    <div class="d-flex align-items-center">
                      <i class="fas fa-envelope text-muted me-3 fa-lg"></i>
                      <div class="flex-grow-1">
                        <small class="text-muted d-block">Email Address</small>
                        <div class="fw-bold" id="modalEmail">-</div>
                      </div>
                    </div>
                  </div>

                  <!-- System Information -->
                  <div class="system-info bg-light rounded p-3">
                    <h6 class="text-primary mb-3">
                      <i class="fas fa-calendar-alt me-2"></i>System Information
                    </h6>
                    <div class="row g-2">
                      <div class="col-md-6">
                        <div class="d-flex justify-content-between">
                          <span class="text-muted">Created:</span>
                          <span class="fw-bold" id="modalCreated">-</span>
                        </div>
                      </div>
                      <div class="col-md-6">
                        <div class="d-flex justify-content-between">
                          <span class="text-muted">Last Updated:</span>
                          <span class="fw-bold" id="modalUpdated">-</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer bg-light">
            <div class="d-flex justify-content-between w-100 align-items-center">
              <small class="text-muted">
                <i class="fas fa-clock me-1"></i>
                Last updated: <span id="modalUpdatedFooter">-</span>
              </small>
              <div>
                <button type="button" class="btn btn-outline-primary me-2">
                  <i class="fas fa-edit me-2"></i>Edit Profile
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  <i class="fas fa-times me-2"></i>Close
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <nav>
      <ul class="pagination justify-content-center">
        <?php for($i=1;$i<=$total_pages;$i++): ?>
        <li class="page-item <?= $i==$page?'active':'' ?>">
          <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>

  <!-- Course Assignment Modal -->
  <div class="modal fade" id="courseAssignmentModal" tabindex="-1" aria-labelledby="courseAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="courseAssignmentModalLabel"><i class="fas fa-book me-2"></i>Assign Courses</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="assignmentContent">
            <div class="text-center">
              <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <p class="mt-2">Loading course assignment...</p>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-primary" onclick="saveCourseAssignments()">
            <i class="fas fa-save me-2"></i>Save Assignments
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="footer">&copy; <?= date('Y') ?> Rwanda Polytechnic | HoD Panel</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Course and Option assignment functionality
let currentLecturerId = null;
let currentLecturerName = '';
let availableCourses = [];
let assignedCourses = [];
let selectedCoursesForRegistration = [];
let availableOptions = [];
let selectedOptionsForRegistration = [];

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
                        ${data.message || 'No options available in your department.'}
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
                <p class="mb-0">No options are available in your department.</p>
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
                <p class="mb-0">All courses in your department are already assigned to lecturers.</p>
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

function showCourseAssignmentModal() {
    const modal = new bootstrap.Modal(document.getElementById('courseAssignmentModal'));
    modal.show();
    loadCourseAssignmentData();
}

function assignCoursesToLecturer(lecturerId, lecturerName) {
    currentLecturerId = lecturerId;
    currentLecturerName = lecturerName;
    showCourseAssignmentModal();
}

function loadCourseAssignmentData() {
    const content = document.getElementById('assignmentContent');
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading course assignment data...</p>
        </div>
    `;

    // Load available courses and current assignments
    Promise.all([
        fetch('api/assign-courses-api.php?action=get_courses'),
        fetch('api/assign-courses-api.php?action=get_assigned_courses', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ lecturer_id: currentLecturerId })
        })
    ])
    .then(responses => Promise.all(responses.map(r => r.json())))
    .then(data => {
        availableCourses = data[0];
        assignedCourses = data[1];
        renderCourseAssignmentInterface();
    })
    .catch(error => {
        console.error('Error loading course data:', error);
        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Error loading course assignment data. Please try again.
            </div>
        `;
    });
}

function renderCourseAssignmentInterface() {
    const content = document.getElementById('assignmentContent');
    const modalTitle = document.getElementById('courseAssignmentModalLabel');
    modalTitle.innerHTML = `<i class="fas fa-book me-2"></i>Assign Courses to ${currentLecturerName}`;

    content.innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6><i class="fas fa-list me-2"></i>Available Courses</h6>
                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                    ${availableCourses.map(course => `
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" value="${course.id}"
                                  id="course_${course.id}" ${assignedCourses.some(ac => ac.id === course.id) ? 'checked' : ''}>
                            <label class="form-check-label" for="course_${course.id}">
                                ${course.course_name} (${course.course_code})
                            </label>
                        </div>
                    `).join('')}
                </div>
            </div>
            <div class="col-md-6">
                <h6><i class="fas fa-check-circle me-2"></i>Currently Assigned</h6>
                <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                    <div id="assignedCoursesList">
                        ${assignedCourses.map(course => `
                            <div class="badge bg-primary me-1 mb-1 p-2">
                                ${course.course_name} (${course.course_code})
                                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeCourseAssignment(${course.id})"></button>
                            </div>
                        `).join('')}
                    </div>
                    <div id="noAssignments" class="${assignedCourses.length === 0 ? '' : 'd-none'} text-muted">
                        No courses currently assigned
                    </div>
                </div>
            </div>
        </div>
    `;
}

function saveCourseAssignments() {
    const selectedCourses = Array.from(document.querySelectorAll('#assignmentContent input[type="checkbox"]:checked'))
        .map(cb => parseInt(cb.value));

    if (!currentLecturerId) {
        alert('No lecturer selected');
        return;
    }

    const saveBtn = document.querySelector('#courseAssignmentModal .btn-primary');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
    saveBtn.disabled = true;

    fetch('api/assign-courses-api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'save_course_assignments',
            lecturer_id: currentLecturerId,
            course_ids: selectedCourses
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close modal and refresh page
            bootstrap.Modal.getInstance(document.getElementById('courseAssignmentModal')).hide();
            location.reload();
        } else {
            alert('Error saving course assignments: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error saving assignments:', error);
        alert('Error saving course assignments. Please try again.');
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function removeCourseAssignment(courseId) {
    const checkbox = document.getElementById('course_' + courseId);
    if (checkbox) {
        checkbox.checked = false;
        updateAssignedCoursesDisplay();
    }
}

function updateAssignedCoursesDisplay() {
    const assignedList = document.getElementById('assignedCoursesList');
    const noAssignments = document.getElementById('noAssignments');
    const checkedBoxes = document.querySelectorAll('#assignmentContent input[type="checkbox"]:checked');

    assignedList.innerHTML = Array.from(checkedBoxes).map(cb => {
        const course = availableCourses.find(c => c.id == cb.value);
        return `
            <div class="badge bg-primary me-1 mb-1 p-2">
                ${course.course_name} (${course.course_code})
                <button type="button" class="btn-close btn-close-white ms-1" onclick="removeCourseAssignment(${course.id})"></button>
            </div>
        `;
    }).join('');

    if (checkedBoxes.length === 0) {
        noAssignments.classList.remove('d-none');
    } else {
        noAssignments.classList.add('d-none');
    }
}

// Add event listeners for checkboxes
document.addEventListener('change', function(e) {
    if (e.target.type === 'checkbox' && e.target.id.startsWith('course_')) {
        updateAssignedCoursesDisplay();
    }
});

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

// Load statistics on page load
document.addEventListener('DOMContentLoaded', function() {
    loadLecturerStatistics();
    initializeTooltips();
    loadOptionsForRegistration(); // Load options for registration form
    loadCoursesForRegistration(); // Load courses for registration form

    // Failsafe: stop all loading animations after 10 seconds
    setTimeout(function() {
        const spinners = document.querySelectorAll('.fa-spinner');
        spinners.forEach(spinner => {
            spinner.style.animation = 'none';
            spinner.style.opacity = '0.7';
        });
        console.log('Loading animations stopped by failsafe timeout');
    }, 10000);
});

// Load lecturer statistics with enhanced error handling and caching
function loadLecturerStatistics() {
    // Show loading state with enhanced visual feedback
    const elements = ['totalLecturers', 'maleLecturers', 'femaleLecturers', 'phdLecturers'];
    elements.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.innerHTML = '<i class="fas fa-spinner fa-spin" style="animation-duration: 1s;"></i>';
            element.title = 'Loading statistics...';
        }
    });

    fetch('api/assign-courses-api.php?action=get_lecturer_statistics', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success && data.statistics) {
            // Update statistics with smooth animation
            updateStatisticWithAnimation('totalLecturers', data.statistics.total_lecturers);
            updateStatisticWithAnimation('maleLecturers', data.statistics.male_lecturers);
            updateStatisticWithAnimation('femaleLecturers', data.statistics.female_lecturers);
            updateStatisticWithAnimation('phdLecturers', data.statistics.phd_holders);

            // Add tooltip with additional info if available
            if (data.statistics.contact_info_completeness) {
                const totalElement = document.getElementById('totalLecturers');
                if (totalElement) {
                    totalElement.title = `Contact info completeness: ${data.statistics.contact_info_completeness}`;
                    totalElement.setAttribute('data-bs-toggle', 'tooltip');
                }
            }

            // Add cache indicator if data came from cache
            if (data.cached) {
                console.log('Statistics loaded from cache');
                addCacheIndicator();
            }

            // Log performance metrics
            if (data.last_updated) {
                console.log(`Statistics last updated: ${data.last_updated}`);
            }
        } else {
            throw new Error(data.message || 'Failed to load statistics');
        }
    })
    .catch(error => {
        console.error('Error loading statistics:', error);

        // Set error indicators
        elements.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = '<i class="fas fa-exclamation-triangle text-warning" title="Error loading data"></i>';
                element.title = `Error: ${error.message}`;
            }
        });

        // Fallback to legacy method after a short delay
        console.log('Falling back to legacy statistics method...');
        setTimeout(() => {
            loadLecturerStatisticsLegacy();
        }, 1000);
    });
}

// Add cache indicator to show when data is loaded from cache
function addCacheIndicator() {
    const statsCards = document.querySelectorAll('.card-body');
    statsCards.forEach(card => {
        const existingIndicator = card.querySelector('.cache-indicator');
        if (!existingIndicator) {
            const indicator = document.createElement('small');
            indicator.className = 'cache-indicator text-success position-absolute';
            indicator.innerHTML = '<i class="fas fa-bolt me-1"></i>Cached';
            indicator.style.fontSize = '0.7rem';
            indicator.style.opacity = '0.7';
            indicator.style.bottom = '5px';
            indicator.style.right = '10px';
            indicator.style.zIndex = '10';
            card.style.position = 'relative';
            card.appendChild(indicator);
        }
    });
}

// Animate number changes for better UX
function updateStatisticWithAnimation(elementId, newValue) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const currentValue = parseInt(element.textContent) || 0;
    const targetValue = parseInt(newValue) || 0;

    if (currentValue === targetValue) {
        element.textContent = targetValue;
        return;
    }

    // Simple animation for number changes
    const duration = 500; // 500ms
    const startTime = Date.now();
    const startValue = currentValue;

    function animate() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Easing function for smooth animation
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const current = Math.round(startValue + (targetValue - startValue) * easeOut);

        element.textContent = current;

        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            element.textContent = targetValue;
        }
    }

    animate();
}

// Legacy fallback method for statistics loading
function loadLecturerStatisticsLegacy() {
    fetch('api/assign-courses-api.php?action=get_lecturers')
        .then(response => response.json())
        .then(data => {
            if (data && Array.isArray(data)) {
                const total = data.length;
                const male = data.filter(l => l.gender === 'Male').length;
                const female = data.filter(l => l.gender === 'Female').length;
                const phd = data.filter(l => l.education_level === 'PhD').length;

                updateStatisticWithAnimation('totalLecturers', total);
                updateStatisticWithAnimation('maleLecturers', male);
                updateStatisticWithAnimation('femaleLecturers', female);
                updateStatisticWithAnimation('phdLecturers', phd);

                console.log('Legacy statistics loaded successfully');
            } else {
                throw new Error('Invalid data format from legacy API');
            }
        })
        .catch(error => {
            console.error('Error in legacy statistics loading:', error);
            // Set error indicators
            const elements = ['totalLecturers', 'maleLecturers', 'femaleLecturers', 'phdLecturers'];
            elements.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.innerHTML = '<i class="fas fa-exclamation-triangle text-danger" title="Failed to load data"></i>';
                }
            });
        });
}


// Initialize tooltips
function initializeTooltips() {
    // Dispose existing tooltips first to prevent conflicts
    var existingTooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    existingTooltips.forEach(function(el) {
        var tooltip = bootstrap.Tooltip.getInstance(el);
        if (tooltip) {
            tooltip.dispose();
        }
    });

    // Initialize new tooltips with proper configuration
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            trigger: 'hover focus',
            delay: { show: 500, hide: 100 },
            html: false,
            placement: 'top'
        });
    });

    return tooltipList;
}

function validateForm() {
    const firstName = document.querySelector('[name="first_name"]').value.trim();
    const lastName = document.querySelector('[name="last_name"]').value.trim();
    const gender = document.querySelector('[name="gender"]').value;
    const dob = document.querySelector('[name="dob"]').value;
    const idNumber = document.querySelector('[name="id_number"]').value.trim();
    const email = document.querySelector('[name="email"]').value.trim();
    const phone = document.querySelector('[name="phone"]').value.trim();
    const education = document.querySelector('[name="education_level"]').value;
    const photo = document.querySelector('[name="photo"]').files[0];

    // Validate selected options (required) - but allow fallback if no options are available
    const selectedOptions = document.querySelectorAll('.option-checkbox:checked');
    const availableOptionElements = document.querySelectorAll('.option-checkbox');

    if (availableOptionElements.length > 0 && selectedOptions.length === 0) {
        alert('Please select at least one option for the lecturer to access.');
        return false;
    }

    if (availableOptionElements.length === 0) {
        // No options available - show warning but allow submission
        if (!confirm('No options are available for assignment. The lecturer will be created without option access. Continue?')) {
            return false;
        }
    }

    // Basic validation - ensure option IDs are numeric
    for (let checkbox of selectedOptions) {
        if (isNaN(checkbox.value) || parseInt(checkbox.value) <= 0) {
            alert('Invalid option selection detected. Please refresh the page and try again.');
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
                return false;
            }
        }
    }

    // Required field validation
    if (!firstName || !lastName || !gender || !dob || !idNumber || !email || !education) {
        alert('Please fill in all required fields.');
        return false;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address.');
        return false;
    }

    // Phone number validation - exactly 10 digits only
    if (phone) {
        const phoneRegex = /^\d{10}$/;
        if (!phoneRegex.test(phone)) {
            alert('Phone number must be exactly 10 digits only (no spaces, dashes, or country codes).');
            return false;
        }
    }

    // ID number validation - exactly 16 characters
    if (idNumber.length !== 16) {
        alert('ID Number must be exactly 16 characters long.');
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
            return false;
        }

        if (age > 100) {
            alert('Please enter a valid date of birth. Age cannot exceed 100 years.');
            return false;
        }
    }

    // Photo validation
    if (photo) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(photo.type)) {
            alert('Invalid file type. Only JPG, PNG, GIF allowed.');
            return false;
        }
        if (photo.size > 2 * 1024 * 1024) {
            alert('File too large. Maximum size is 2MB.');
            return false;
        }
    }

    // Set loading state
    document.getElementById('addBtn').innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
    document.getElementById('addBtn').disabled = true;
    return true;
}

// Initialize tooltips
function initializeTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Course assignment functionality
function assignCoursesToLecturer(lecturerId, lecturerName) {
    // Open course assignment modal from hod-dashboard
    if (window.parent && window.parent.assignCourses) {
        window.parent.assignCourses(lecturerId, lecturerName);
    } else {
        // If not in iframe, redirect to dashboard with modal trigger
        window.location.href = 'hod-dashboard.php?assign_course=' + lecturerId + '&lecturer_name=' + encodeURIComponent(lecturerName);
    }
}

// Modal event handler for lecturer details
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips first
    initializeTooltips();

    const detailsModal = document.getElementById('detailsModal');
    if (detailsModal) {
        detailsModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (button) {
                // Populate modal with lecturer data
                const name = button.getAttribute('data-name') || 'Unknown Lecturer';
                const gender = button.getAttribute('data-gender') || '-';
                const dob = button.getAttribute('data-dob') || '-';
                const idNumber = button.getAttribute('data-id-number') || '-';
                const email = button.getAttribute('data-email') || '-';
                const phone = button.getAttribute('data-phone') || '-';
                const education = button.getAttribute('data-education') || '-';
                const created = button.getAttribute('data-created') || '-';
                const updated = button.getAttribute('data-updated') || '-';
                const photo = button.getAttribute('data-photo') || '';

                // Update all modal fields
                document.getElementById('modalNameText').textContent = name;
                document.getElementById('modalGender').textContent = gender;
                document.getElementById('modalDob').textContent = dob;
                document.getElementById('modalIdNumber').textContent = idNumber;
                document.getElementById('modalEmail').textContent = email;
                document.getElementById('modalPhone').textContent = phone;
                document.getElementById('modalEducation').textContent = education;
                document.getElementById('modalCreated').textContent = created;
                document.getElementById('modalUpdated').textContent = updated;
                document.getElementById('modalUpdatedFooter').textContent = updated;

                // Handle photo with enhanced error handling
                const modalPhoto = document.getElementById('modalPhoto');
                const photoPlaceholder = modalPhoto.nextElementSibling;

                if (photo && photo.trim() !== '') {
                    modalPhoto.src = photo;
                    modalPhoto.style.display = 'block';
                    modalPhoto.onerror = function() {
                        this.style.display = 'none';
                        if (photoPlaceholder) {
                            photoPlaceholder.style.display = 'flex';
                            photoPlaceholder.classList.remove('d-none');
                        }
                    };
                    if (photoPlaceholder) {
                        photoPlaceholder.style.display = 'none';
                        photoPlaceholder.classList.add('d-none');
                    }
                } else {
                    modalPhoto.style.display = 'none';
                    if (photoPlaceholder) {
                        photoPlaceholder.style.display = 'flex';
                        photoPlaceholder.classList.remove('d-none');
                    }
                }

                // Add subtle animation to info cards
                setTimeout(() => {
                    const infoCards = document.querySelectorAll('.info-card');
                    infoCards.forEach((card, index) => {
                        setTimeout(() => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        }, index * 100);
                    });
                }, 100);
            }
        });

        // Hide tooltips when modal is shown to prevent conflicts
        detailsModal.addEventListener('show.bs.modal', function () {
            var tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltips.forEach(function(el) {
                var tooltip = bootstrap.Tooltip.getInstance(el);
                if (tooltip) {
                    tooltip.hide();
                }
            });
        });
    }
});
</script>
</body>
</html>
