<?php
/**
 * Admin View Users
 * Allows admin to view lists of Lecturers, HODs, and Students
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['admin']);

function fetchUsers($role) {
    global $pdo;
    if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT id, reg_number, full_name, email, department, created_at FROM students ORDER BY created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT id, staff_id, full_name, email, department, role, created_at FROM lecturers WHERE role = ? ORDER BY created_at DESC");
        $stmt->bindParam(1, $role);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$lecturers = fetchUsers('lecturer');
$hods = fetchUsers('hod');
$students = fetchUsers('student');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>View Users | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f8fb; }
        .card { border-radius: 14px; box-shadow: 0 4px 18px rgba(0,0,0,0.07); }
        .nav-tabs .nav-link.active { background: #0066cc; color: #fff; }
        .nav-tabs .nav-link { color: #0066cc; font-weight: 600; }
        table th, table td { vertical-align: middle !important; }
    </style>
</head>
<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content" style="margin-left:280px; padding:30px 20px;">
        <div class="container-fluid">
            <h2 class="mb-4"><i class="fas fa-users me-2"></i>View Users</h2>
            <ul class="nav nav-tabs mb-3" id="userTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="lecturers-tab" data-bs-toggle="tab" data-bs-target="#lecturers" type="button" role="tab">Lecturers</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="hods-tab" data-bs-toggle="tab" data-bs-target="#hods" type="button" role="tab">HODs</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab">Students</button>
                </li>
            </ul>
            <div class="tab-content" id="userTabsContent">
                <div class="tab-pane fade show active" id="lecturers" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white"><i class="fas fa-chalkboard-teacher me-2"></i>Lecturers</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Staff ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($lecturers as $i => $row): ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td><?= htmlspecialchars($row['staff_id']) ?></td>
                                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td><?= htmlspecialchars($row['email']) ?></td>
                                            <td><?= htmlspecialchars($row['department']) ?></td>
                                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($lecturers)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No lecturers found.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="hods" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white"><i class="fas fa-user-tie me-2"></i>HODs</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Staff ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($hods as $i => $row): ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td><?= htmlspecialchars($row['staff_id']) ?></td>
                                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td><?= htmlspecialchars($row['email']) ?></td>
                                            <td><?= htmlspecialchars($row['department']) ?></td>
                                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($hods)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No HODs found.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="students" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white"><i class="fas fa-user-graduate me-2"></i>Students</div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-striped mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Reg Number</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Department</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($students as $i => $row): ?>
                                        <tr>
                                            <td><?= $i+1 ?></td>
                                            <td><?= htmlspecialchars($row['reg_number']) ?></td>
                                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td><?= htmlspecialchars($row['email']) ?></td>
                                            <td><?= htmlspecialchars($row['department']) ?></td>
                                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($students)): ?>
                                        <tr><td colspan="6" class="text-center text-muted">No students found.</td></tr>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <a href="admin-dashboard.php" class="btn btn-secondary mt-3"><i class="fas fa-arrow-left me-2"></i>Back to Dashboard</a>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
