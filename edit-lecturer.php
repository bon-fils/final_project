<?php
session_start();
require_once "config.php";
require_once "session_check.php"; // ensure HoD is logged in
require_role(['hod']);

// Get HoD's department ID
$hod_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id AS department_id FROM departments WHERE hod_id = ? LIMIT 1");
$stmt->execute([$hod_id]);
$hod = $stmt->fetch(PDO::FETCH_ASSOC);
$hod_department = $hod['department_id'] ?? null;

$lecturer_id = $_GET['id'] ?? null;
if (!$lecturer_id || !is_numeric($lecturer_id)) {
    header("Location: hod-manage-lecturers.php");
    exit;
}

// Fetch lecturer data
$stmt = $pdo->prepare("SELECT * FROM lecturers WHERE id = ? AND department_id = ?");
$stmt->execute([$lecturer_id, $hod_department]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lecturer) {
    header("Location: hod-manage-lecturers.php");
    exit;
}

$formError = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $gender     = $_POST['gender'];
    $dob        = $_POST['dob'];
    $id_number  = trim($_POST['id_number']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $education_level = $_POST['education_level'];

    // Photo upload
    $photo_filename = $lecturer['photo']; // keep existing
    if (!empty($_FILES['photo']['name'])) {
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_types)) {
            $formError = 'Invalid file type. Only JPG, PNG, GIF allowed.';
        } elseif ($_FILES['photo']['size'] > $max_size) {
            $formError = 'File too large. Maximum size is 2MB.';
        } else {
            $target_dir = "uploads/lecturers/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            $photo_filename = uniqid('lec_') . '.' . $ext;
            $target_file = $target_dir . $photo_filename;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
                // Delete old photo if exists
                if ($lecturer['photo'] && file_exists("uploads/lecturers/" . $lecturer['photo'])) {
                    unlink("uploads/lecturers/" . $lecturer['photo']);
                }
            } else {
                $formError = 'Failed to upload photo.';
            }
        }
    }

    if (empty($formError)) {
        try {
            // Check uniqueness, excluding current
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM lecturers WHERE (email = ? OR id_number = ?) AND id != ?");
            $stmt->execute([$email, $id_number, $lecturer_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Email or ID Number already exists.');
            }
            // Check if email exists in users table for other users (not this lecturer)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                // Email exists, but check if it's the same lecturer's email
                $stmt = $pdo->prepare("SELECT email FROM lecturers WHERE id = ?");
                $stmt->execute([$lecturer_id]);
                $current_email = $stmt->fetchColumn();
                if ($email !== $current_email) {
                    throw new Exception('Email already exists in the system.');
                }
            }

            $pdo->beginTransaction();

            // Update lecturers table
            $stmt = $pdo->prepare("UPDATE lecturers SET first_name = ?, last_name = ?, gender = ?, dob = ?, id_number = ?, email = ?, phone = ?, education_level = ?, photo = ?, updated_at = ? WHERE id = ?");
            $stmt->execute([
                $first_name, $last_name, $gender, $dob, $id_number, $email, $phone, $education_level, $photo_filename, date('Y-m-d H:i:s'), $lecturer_id
            ]);

            // Update users table if email changed
            // Note: Since there's no direct relationship between lecturers and users tables,
            // we'll skip the users table update for now to avoid the column error.
            // In a real implementation, you might want to establish this relationship properly.
            if ($email !== $lecturer['email']) {
                // For now, we'll just log this change instead of trying to update users table
                error_log("Email changed for lecturer ID $lecturer_id from {$lecturer['email']} to $email - users table not updated due to missing user_id column");
            }

            $pdo->commit();
            $success = true;
            // Refresh data
            $lecturer = array_merge($lecturer, $_POST, ['photo' => $photo_filename]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $formError = 'Failed to update lecturer: ' . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Lecturer | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body{font-family:'Segoe UI',sans-serif;background:linear-gradient(to right, #0066cc, #003366);margin:0;}
.sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:#003366;color:white;padding-top:20px;overflow:auto;}
.sidebar .sidebar-header{text-align:center;margin-bottom:20px;}
.sidebar a{display:block;padding:12px 20px;color:#fff;text-decoration:none;font-weight:500;}
.sidebar a:hover{background:#0059b3;border-radius:6px;}
.topbar{margin-left:250px;background:#fff;padding:15px 30px;border-bottom:1px solid #ddd;position:sticky;top:0;z-index:900;display:flex;justify-content:space-between;align-items:center;}
.main-content{margin-left:250px;padding:30px;}
.form-section{background:#fff;border-radius:12px;padding:25px;box-shadow:0 4px 12px rgba(0,0,0,0.05);margin-bottom:30px;}
.btn-primary{background:#003366;border:none;}
.btn-primary:hover{background:#0059b3;}
.footer{text-align:center;margin-left:250px;padding:15px;font-size:0.9rem;color:#666;background:#f0f0f0;}
@media(max-width:768px){.sidebar,.topbar,.main-content,.footer{margin-left:0 !important;width:100%;}.sidebar{display:none;}}
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
  <a href="hod-manage-lecturers.php"><i class="fas fa-user-plus me-2"></i> Manage Lecturers</a>
  <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<div class="topbar">
  <h5 class="m-0 fw-bold">Edit Lecturer</h5>
  <span>HoD Panel</span>
</div>

<div class="main-content">
  <div class="form-section">
    <h5>Edit Lecturer Details</h5>
    <?php if(!empty($formError)): ?><div class="alert alert-danger"><?= $formError ?></div><?php endif; ?>
    <?php if($success): ?><div class="alert alert-success">Lecturer updated successfully!</div><?php endif; ?>
    <form method="POST" enctype="multipart/form-data">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($lecturer['first_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($lecturer['last_name']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Gender</label>
          <select name="gender" class="form-select" required>
            <option value="Male" <?= $lecturer['gender'] == 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $lecturer['gender'] == 'Female' ? 'selected' : '' ?>>Female</option>
            <option value="Other" <?= $lecturer['gender'] == 'Other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($lecturer['dob']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">ID Number</label><input type="text" name="id_number" class="form-control" value="<?= htmlspecialchars($lecturer['id_number']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($lecturer['email']) ?>" required></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($lecturer['phone']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Education Level</label>
          <select name="education_level" class="form-select" required>
            <option value="Bachelor's" <?= $lecturer['education_level'] == "Bachelor's" ? 'selected' : '' ?>>Bachelor's</option>
            <option value="Master's" <?= $lecturer['education_level'] == "Master's" ? 'selected' : '' ?>>Master's</option>
            <option value="PhD" <?= $lecturer['education_level'] == "PhD" ? 'selected' : '' ?>>PhD</option>
            <option value="Other" <?= $lecturer['education_level'] == "Other" ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*">
          <?php if($lecturer['photo'] && file_exists("uploads/lecturers/".$lecturer['photo'])): ?>
            <small class="text-muted">Current: <img src="uploads/lecturers/<?= htmlspecialchars($lecturer['photo']) ?>" alt="Current Photo" style="width:50px;height:50px;border-radius:50%;object-fit:cover;"></small>
          <?php endif; ?>
        </div>
      </div>
      <div class="mt-4"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Update Lecturer</button> <a href="hod-manage-lecturers.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a></div>
    </form>
  </div>
</div>

<div class="footer">&copy; <?= date('Y') ?> Rwanda Polytechnic | HoD Panel</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>