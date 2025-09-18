<?php
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once "config.php";
require_once "session_check.php"; // ensure HoD is logged in
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

        $pdo->commit();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['success_message'] = "Lecturer added successfully! Login credentials: Username: $username, Password: 12345";
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
        <div class="col-md-6"><label class="form-label" for="dob">Date of Birth</label><input type="date" id="dob" name="dob" class="form-control" required aria-required="true"></div>
        <div class="col-md-6"><label class="form-label" for="id_number">ID Number</label><input type="text" id="id_number" name="id_number" class="form-control" required aria-required="true"></div>
        <div class="col-md-6"><label class="form-label" for="email">Email</label><input type="email" id="email" name="email" class="form-control" required aria-required="true"></div>
        <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input type="text" id="phone" name="phone" class="form-control"></div>
        <div class="col-md-6"><label class="form-label" for="education_level">Education Level</label>
          <select id="education_level" name="education_level" class="form-select" required aria-required="true">
            <option value="">Select</option><option value="Bachelor's">Bachelor's</option><option value="Master's">Master's</option><option value="PhD">PhD</option><option value="Other">Other</option>
          </select>
        </div>
        <div class="col-md-6"><label class="form-label" for="photo">Photo</label><input type="file" id="photo" name="photo" class="form-control" accept="image/*" aria-describedby="photoHelp"><small id="photoHelp" class="form-text text-muted">Optional. Max 2MB, JPG/PNG/GIF only.</small></div>
      </div>
      <div class="mt-4"><button type="submit" class="btn btn-primary" id="addBtn"><i class="fas fa-plus me-2"></i>Add Lecturer</button></div>
    </form>
  </div>

  <!-- Lecturer List Table -->
  <div class="table-section">
    <h5>Existing Lecturers</h5>
    <form method="GET" class="mb-3" role="search">
      <div class="input-group">
        <label for="search" class="visually-hidden">Search lecturers</label>
        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="Search by name, email or ID" aria-describedby="searchHelp">
        <button type="submit" class="btn btn-primary" aria-label="Search"><i class="fas fa-search"></i></button>
      </div>
      <small id="searchHelp" class="form-text text-muted">Search by first name, last name, email, or ID number.</small>
    </form>

    <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle mb-0">
      <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
        <tr>
          <th class="text-nowrap">#</th>
          <th>Name</th>
          <th class="text-nowrap d-none d-md-table-cell">Gender</th>
          <th class="text-nowrap d-none d-lg-table-cell">ID Number</th>
          <th>Email</th>
          <th class="text-nowrap d-none d-md-table-cell">Phone</th>
          <th class="text-nowrap d-none d-lg-table-cell">Education</th>
          <th class="text-nowrap d-none d-md-table-cell">Photo</th>
          <th class="text-nowrap">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($lecturers as $index => $lec): ?>
        <tr>
          <td class="text-nowrap"><?= ($page-1)*$limit + $index+1 ?></td>
          <td><?= htmlspecialchars($lec['first_name'] . ' ' . $lec['last_name']) ?></td>
          <td class="text-nowrap d-none d-md-table-cell"><?= htmlspecialchars($lec['gender']) ?></td>
          <td class="text-nowrap d-none d-lg-table-cell"><?= htmlspecialchars($lec['id_number']) ?></td>
          <td class="text-truncate" style="max-width:220px;" title="<?= htmlspecialchars($lec['email']) ?>"><?= htmlspecialchars($lec['email']) ?></td>
          <td class="text-nowrap d-none d-md-table-cell"><?= htmlspecialchars($lec['phone']) ?></td>
          <td class="text-nowrap d-none d-lg-table-cell"><?= htmlspecialchars($lec['education_level']) ?></td>
          <td class="text-center d-none d-md-table-cell" style="width:70px;">
            <?php if($lec['photo'] && file_exists("uploads/lecturers/".$lec['photo'])): ?>
              <img src="uploads/lecturers/<?= htmlspecialchars($lec['photo']) ?>" alt="Photo" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
            <?php else: ?>
              <i class="fas fa-user-circle fa-2x text-secondary"></i>
            <?php endif; ?>
          </td>
          <td class="text-nowrap">
            <button class="btn btn-sm btn-info me-1" data-bs-toggle="modal" data-bs-target="#detailsModal"
              data-name="<?= htmlspecialchars($lec['first_name'] . ' ' . $lec['last_name']) ?>"
              data-gender="<?= htmlspecialchars($lec['gender']) ?>"
              data-dob="<?= htmlspecialchars($lec['dob']) ?>"
              data-id-number="<?= htmlspecialchars($lec['id_number']) ?>"
              data-email="<?= htmlspecialchars($lec['email']) ?>"
              data-phone="<?= htmlspecialchars($lec['phone']) ?>"
              data-education="<?= htmlspecialchars($lec['education_level']) ?>"
              data-created="<?= htmlspecialchars($lec['created_at'] ?? '') ?>"
              data-updated="<?= htmlspecialchars($lec['updated_at'] ?? '') ?>"
              data-photo="<?= $lec['photo'] ? 'uploads/lecturers/' . htmlspecialchars($lec['photo']) : '' ?>"
              aria-label="View Details"><i class="fas fa-eye"></i></button>
            <a href="edit-lecturer.php?id=<?= $lec['id'] ?>" class="btn btn-sm btn-warning" aria-label="Edit Lecturer"><i class="fas fa-edit"></i></a>
            <a href="delete-lecturer.php?id=<?= $lec['id'] ?>" class="btn btn-sm btn-danger" aria-label="Delete Lecturer" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="detailsModalLabel">Lecturer Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row">
              <div class="col-md-4 text-center">
                <img id="modalPhoto" src="" alt="Photo" class="img-fluid rounded-circle mb-3" style="width:120px;height:120px;object-fit:cover;">
              </div>
              <div class="col-md-8">
                <h4 id="modalName"></h4>
                <p><strong>Gender:</strong> <span id="modalGender"></span></p>
                <p><strong>Date of Birth:</strong> <span id="modalDob"></span></p>
                <p><strong>ID Number:</strong> <span id="modalIdNumber"></span></p>
                <p><strong>Email:</strong> <span id="modalEmail"></span></p>
                <p><strong>Phone:</strong> <span id="modalPhone"></span></p>
                <p><strong>Education Level:</strong> <span id="modalEducation"></span></p>
                <p><strong>Created:</strong> <span id="modalCreated"></span></p>
                <p><strong>Last Updated:</strong> <span id="modalUpdated"></span></p>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
</div>

<div class="footer">&copy; <?= date('Y') ?> Rwanda Polytechnic | HoD Panel</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

    if (!firstName || !lastName || !gender || !dob || !idNumber || !email || !education) {
        alert('Please fill in all required fields.');
        return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        alert('Please enter a valid email address.');
        return false;
    }

    if (phone && !/^\+?[\d\s-()]+$/.test(phone)) {
        alert('Please enter a valid phone number.');
        return false;
    }

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
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

document.getElementById('detailsModal').addEventListener('show.bs.modal', function (event) {
    var button = event.relatedTarget;
    document.getElementById('modalName').textContent = button.getAttribute('data-name');
    document.getElementById('modalGender').textContent = button.getAttribute('data-gender');
    document.getElementById('modalDob').textContent = button.getAttribute('data-dob');
    document.getElementById('modalIdNumber').textContent = button.getAttribute('data-id-number');
    document.getElementById('modalEmail').textContent = button.getAttribute('data-email');
    document.getElementById('modalPhone').textContent = button.getAttribute('data-phone');
    document.getElementById('modalEducation').textContent = button.getAttribute('data-education');
    document.getElementById('modalCreated').textContent = button.getAttribute('data-created');
    document.getElementById('modalUpdated').textContent = button.getAttribute('data-updated');
    var photo = button.getAttribute('data-photo');
    if (photo) {
        document.getElementById('modalPhoto').src = photo;
        document.getElementById('modalPhoto').style.display = 'block';
    } else {
        document.getElementById('modalPhoto').src = '';
        document.getElementById('modalPhoto').style.display = 'none';
    }
});
</script>
</body>
</html>
