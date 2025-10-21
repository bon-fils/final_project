<?php
/**
 * Quick HOD Assignment Tool
 * Simple interface to assign HODs to departments
 */

require_once "config.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign'])) {
    $lecturer_id = $_POST['lecturer_id'];
    $department_id = $_POST['department_id'];
    
    try {
        // Update department to assign HOD
        $stmt = $pdo->prepare("UPDATE departments SET hod_id = ? WHERE id = ?");
        $stmt->execute([$lecturer_id, $department_id]);
        
        $success = "HOD assigned successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get available lecturers (HOD users with lecturer records)
$lecturers_stmt = $pdo->query("
    SELECT l.id, l.first_name, l.last_name, l.email, u.username
    FROM lecturers l
    JOIN users u ON l.user_id = u.id
    WHERE u.role = 'hod'
    ORDER BY l.first_name, l.last_name
");
$lecturers = $lecturers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments
$departments_stmt = $pdo->query("
    SELECT d.id, d.name, d.hod_id,
           l.first_name as current_hod_fname, l.last_name as current_hod_lname
    FROM departments d
    LEFT JOIN lecturers l ON d.hod_id = l.id
    ORDER BY d.name
");
$departments = $departments_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Quick HOD Assignment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-user-tie me-2"></i>Quick HOD Assignment</h3>
                </div>
                <div class="card-body">
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Assignment Form -->
                        <div class="col-md-6">
                            <h5><i class="fas fa-plus-circle me-2"></i>Assign HOD to Department</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="lecturer_id" class="form-label">Select HOD Lecturer</label>
                                    <select name="lecturer_id" id="lecturer_id" class="form-select" required>
                                        <option value="">Choose HOD...</option>
                                        <?php foreach ($lecturers as $lecturer): ?>
                                            <option value="<?= $lecturer['id'] ?>">
                                                <?= htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']) ?>
                                                (<?= htmlspecialchars($lecturer['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="department_id" class="form-label">Select Department</label>
                                    <select name="department_id" id="department_id" class="form-select" required>
                                        <option value="">Choose Department...</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>">
                                                <?= htmlspecialchars($dept['name']) ?>
                                                <?php if ($dept['current_hod_fname']): ?>
                                                    (Currently: <?= htmlspecialchars($dept['current_hod_fname'] . ' ' . $dept['current_hod_lname']) ?>)
                                                <?php else: ?>
                                                    (No HOD assigned)
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" name="assign" class="btn btn-primary">
                                    <i class="fas fa-check me-2"></i>Assign HOD
                                </button>
                            </form>
                        </div>
                        
                        <!-- Current Status -->
                        <div class="col-md-6">
                            <h5><i class="fas fa-list me-2"></i>Current Assignments</h5>
                            <div class="table-responsive">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Current HOD</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($departments as $dept): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($dept['name']) ?></td>
                                                <td>
                                                    <?php if ($dept['current_hod_fname']): ?>
                                                        <?= htmlspecialchars($dept['current_hod_fname'] . ' ' . $dept['current_hod_lname']) ?>
                                                    <?php else: ?>
                                                        <em class="text-muted">Not assigned</em>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($dept['hod_id']): ?>
                                                        <span class="badge bg-success">Assigned</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Vacant</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex gap-2">
                        <a href="fix_hod_system.php" class="btn btn-info">
                            <i class="fas fa-tools me-2"></i>Run System Fix
                        </a>
                        <a href="hod-dashboard.php" class="btn btn-success">
                            <i class="fas fa-tachometer-alt me-2"></i>Test HOD Dashboard
                        </a>
                        <a href="login_new.php" class="btn btn-secondary">
                            <i class="fas fa-sign-in-alt me-2"></i>Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
