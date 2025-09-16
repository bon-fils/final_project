<?php
session_start();
require_once "config.php";
require_once "session_check.php"; // ensure HoD is logged in

// Get HoD's department ID
$hod_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT department_id FROM lecturers WHERE id = ?");
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Photo upload
    $photo_filename = null;
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "uploads/lecturers/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photo_filename = uniqid('lec_') . '.' . $ext;
        $target_file = $target_dir . $photo_filename;
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) $photo_filename = null;
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
    $lecturer_id = $pdo->lastInsertId();

    // Generate unique username: firstname.lastname (lowercase)
    $username_base = strtolower($first_name . '.' . $last_name);
    $username = $username_base;
    $suffix = 1;
    while ($pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?")->execute([$username]) && $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?")->fetchColumn() > 0) {
        $username = $username_base . $suffix;
        $suffix++;
    }

    // Insert into users table
    $stmtUser = $pdo->prepare("INSERT INTO users (id, username, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtUser->execute([
        $lecturer_id,
        $username,
        $email,
        $password,
        'lecturer',
        date('Y-m-d H:i:s')
    ]);

    header("Location: hod-manage-lecturers.php");
    exit;
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
body{font-family:'Segoe UI',sans-serif;background:#f4f6f9;margin:0;}
.sidebar{position:fixed;top:0;left:0;width:250px;height:100vh;background:#003366;color:white;padding-top:20px;overflow:auto;}
.sidebar .sidebar-header{text-align:center;margin-bottom:20px;}
.sidebar a{display:block;padding:12px 20px;color:#fff;text-decoration:none;font-weight:500;}
.sidebar a:hover{background:#0059b3;border-radius:6px;}
.topbar{margin-left:250px;background:#fff;padding:15px 30px;border-bottom:1px solid #ddd;position:sticky;top:0;z-index:900;display:flex;justify-content:space-between;align-items:center;}
.main-content{margin-left:250px;padding:30px;}
.form-section, .table-section{background:#fff;border-radius:12px;padding:25px;box-shadow:0 4px 12px rgba(0,0,0,0.05);margin-bottom:30px;transition:0.3s;}
.form-section:hover, .table-section:hover{transform:translateY(-2px);}
.form-section h5,.table-section h5{font-weight:bold;margin-bottom:20px;color:#003366;}
.table img{width:50px;height:50px;border-radius:50%;object-fit:cover;}
.table td, .table th{vertical-align:middle;}
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
  <h5 class="m-0 fw-bold">Manage Lecturers</h5>
  <span>HoD Panel</span>
</div>

<div class="main-content">
  <!-- Add Lecturer Form -->
  <div class="form-section">
    <h5>Add New Lecturer</h5>
    <form method="POST" enctype="multipart/form-data">
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label">First Name</label><input type="text" name="first_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Last Name</label><input type="text" name="last_name" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Gender</label>
          <select name="gender" class="form-select" required>
            <option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" name="dob" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">ID Number</label><input type="text" name="id_number" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Education Level</label>
          <select name="education_level" class="form-select" required>
            <option value="">Select</option><option value="Bachelor's">Bachelor's</option><option value="Master's">Master's</option><option value="PhD">PhD</option><option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label">Photo</label><input type="file" name="photo" class="form-control" accept="image/*"></div>
      </div>
      <div class="mt-4"><button type="submit" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Lecturer</button></div>
    </form>
  </div>

  <!-- Lecturer List Table -->
  <div class="table-section">
    <h5>Existing Lecturers</h5>
    <form method="GET" class="mb-3">
      <div class="input-group">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by name, email or ID">
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
      </div>
    </form>

    <table class="table table-bordered table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th><th>Photo</th><th>Name</th><th>Gender</th><th>DOB</th><th>ID</th><th>Email</th><th>Phone</th><th>Education</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($lecturers as $index => $lec): ?>
        <tr>
          <td><?= ($page-1)*$limit + $index+1 ?></td>
          <td>
            <?php if($lec['photo'] && file_exists("uploads/lecturers/".$lec['photo'])): ?>
              <img src="uploads/lecturers/<?= htmlspecialchars($lec['photo']) ?>" alt="Photo">
            <?php else: ?>
              <i class="fas fa-user-circle fa-2x text-secondary"></i>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($lec['first_name'].' '.$lec['last_name']) ?></td>
          <td><?= htmlspecialchars($lec['gender']) ?></td>
          <td><?= htmlspecialchars($lec['dob']) ?></td>
          <td><?= htmlspecialchars($lec['id_number']) ?></td>
          <td><?= htmlspecialchars($lec['email']) ?></td>
          <td><?= htmlspecialchars($lec['phone']) ?></td>
          <td><?= htmlspecialchars($lec['education_level']) ?></td>
          <td>
            <a href="edit-lecturer.php?id=<?= $lec['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
            <a href="delete-lecturer.php?id=<?= $lec['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

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
</div>

<div class="footer">&copy; <?= date('Y') ?> Rwanda Polytechnic | HoD Panel</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
