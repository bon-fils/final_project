<?php
session_start();
require_once "config.php";
require_once "session_check.php"; // ensure HoD is logged in
require_role(['hod']);

// Get HoD's department ID by joining through lecturers table
$hod_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT d.id AS department_id
    FROM departments d
    JOIN lecturers l ON d.hod_id = l.id
    JOIN users u ON l.email = u.email AND u.role = 'hod'
    WHERE u.id = ? LIMIT 1
");
$stmt->execute([$hod_id]);
$hod = $stmt->fetch(PDO::FETCH_ASSOC);
$hod_department = $hod['department_id'] ?? null;

$lecturer_id = $_GET['id'] ?? null;
if (!$lecturer_id || !is_numeric($lecturer_id)) {
    header("Location: hod-manage-lecturers.php");
    exit;
}

// Fetch lecturer to confirm
$stmt = $pdo->prepare("SELECT first_name, last_name, photo FROM lecturers WHERE id = ? AND department_id = ?");
$stmt->execute([$lecturer_id, $hod_department]);
$lecturer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$lecturer) {
    header("Location: hod-manage-lecturers.php");
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $pdo->beginTransaction();

        // Delete from lecturers (cascade or manual)
        $stmt = $pdo->prepare("DELETE FROM lecturers WHERE id = ?");
        $stmt->execute([$lecturer_id]);

        // Delete from users (join on email since lecturers table doesn't have user_id column)
        $stmt = $pdo->prepare("DELETE FROM users WHERE email = (SELECT email FROM lecturers WHERE id = ?)");
        $stmt->execute([$lecturer_id]);

        // Delete photo if exists
        if ($lecturer['photo'] && file_exists("uploads/lecturers/" . $lecturer['photo'])) {
            unlink("uploads/lecturers/" . $lecturer['photo']);
        }

        $pdo->commit();
        header("Location: hod-manage-lecturers.php?deleted=1");
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = 'Failed to delete lecturer: ' . htmlspecialchars($e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delete Lecturer | RP Attendance System</title>
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
.btn-danger{background:#dc3545;border:none;}
.btn-danger:hover{background:#c82333;}
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
  <h5 class="m-0 fw-bold">Delete Lecturer</h5>
  <span>HoD Panel</span>
</div>

<div class="main-content">
  <div class="form-section">
    <h5>Confirm Deletion</h5>
    <?php if(!empty($message)): ?><div class="alert alert-danger"><?= $message ?></div><?php endif; ?>
    <p>Are you sure you want to delete the lecturer <strong><?= htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']) ?></strong>?</p>
    <p class="text-danger">This action cannot be undone. It will remove the lecturer from the system and delete associated data.</p>
    <form method="POST">
      <button type="submit" name="confirm_delete" class="btn btn-danger"><i class="fas fa-trash me-2"></i>Yes, Delete</button>
      <a href="hod-manage-lecturers.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Cancel</a>
    </form>
  </div>
</div>

<div class="footer">&copy; <?= date('Y') ?> Rwanda Polytechnic | HoD Panel</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>