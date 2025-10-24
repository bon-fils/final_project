<?php
require_once "config.php";
session_start();
require_once "session_check.php";
require_once "includes/hod_auth_helper.php";

// Verify HOD access and get department information
$auth_result = verifyHODAccess($pdo, $_SESSION['user_id']);

if (!$auth_result['success']) {
    // Show error message instead of redirect for better UX
    $error_message = $auth_result['error_message'];
    $department_name = 'No Department Assigned';
    $department_id = null;
    $user = ['name' => $_SESSION['username'] ?? 'User'];
} else {
    $department_name = $auth_result['department_name'];
    $department_id = $auth_result['department_id'];
    $user = $auth_result['user'];
    $lecturer_id = $auth_result['lecturer_id'];
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $department_id) {
    if (isset($_POST['action'])) {
        // Validate CSRF token (basic security)
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error_message = "Invalid request. Please try again.";
        } else {
            switch ($_POST['action']) {
                case 'add_program':
                    // Validate input
                    $program_name = trim($_POST['program_name'] ?? '');
                    if (empty($program_name)) {
                        $error_message = "Program name is required.";
                        break;
                    }
                    
                    try {
                        // Check if program name already exists in department
                        $check_stmt = $pdo->prepare("SELECT id FROM options WHERE name = ? AND department_id = ?");
                        $check_stmt->execute([$program_name, $department_id]);
                        if ($check_stmt->fetch()) {
                            $error_message = "A program with this name already exists in your department.";
                            break;
                        }
                        
                        // Insert new program (only using existing columns)
                        $stmt = $pdo->prepare("
                            INSERT INTO options (name, department_id, status, created_at)
                            VALUES (?, ?, 'active', NOW())
                        ");
                        $stmt->execute([$program_name, $department_id]);
                        $success_message = "Program '" . htmlspecialchars($program_name) . "' added successfully!";
                    } catch (PDOException $e) {
                        error_log("Error adding program: " . $e->getMessage());
                        $error_message = "Error adding program. Please try again.";
                    }
                    break;

                case 'update_program':
                    $program_name = trim($_POST['program_name'] ?? '');
                    $program_id = filter_var($_POST['program_id'], FILTER_VALIDATE_INT);
                    
                    if (empty($program_name) || !$program_id) {
                        $error_message = "Invalid program data.";
                        break;
                    }
                    
                    try {
                        // Verify program belongs to this department
                        $verify_stmt = $pdo->prepare("SELECT id FROM options WHERE id = ? AND department_id = ?");
                        $verify_stmt->execute([$program_id, $department_id]);
                        if (!$verify_stmt->fetch()) {
                            $error_message = "Program not found or access denied.";
                            break;
                        }
                        
                        // Check if new name conflicts with existing programs
                        $check_stmt = $pdo->prepare("SELECT id FROM options WHERE name = ? AND department_id = ? AND id != ?");
                        $check_stmt->execute([$program_name, $department_id, $program_id]);
                        if ($check_stmt->fetch()) {
                            $error_message = "A program with this name already exists in your department.";
                            break;
                        }
                        
                        // Update program
                        $stmt = $pdo->prepare("
                            UPDATE options
                            SET name = ?
                            WHERE id = ? AND department_id = ?
                        ");
                        $stmt->execute([$program_name, $program_id, $department_id]);
                        $success_message = "Program updated successfully!";
                    } catch (PDOException $e) {
                        error_log("Error updating program: " . $e->getMessage());
                        $error_message = "Error updating program. Please try again.";
                    }
                    break;
                    
                case 'delete_program':
                    $program_id = filter_var($_POST['program_id'], FILTER_VALIDATE_INT);
                    
                    if (!$program_id) {
                        $error_message = "Invalid program ID.";
                        break;
                    }
                    
                    try {
                        // Check if program has students or courses
                        $check_stmt = $pdo->prepare("
                            SELECT 
                                (SELECT COUNT(*) FROM students WHERE option_id = ?) as student_count,
                                (SELECT COUNT(*) FROM courses WHERE option_id = ?) as course_count
                        ");
                        $check_stmt->execute([$program_id, $program_id]);
                        $usage = $check_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($usage['student_count'] > 0 || $usage['course_count'] > 0) {
                            $error_message = "Cannot delete program. It has {$usage['student_count']} students and {$usage['course_count']} courses assigned.";
                            break;
                        }
                        
                        // Verify program belongs to this department and delete
                        $stmt = $pdo->prepare("DELETE FROM options WHERE id = ? AND department_id = ?");
                        $stmt->execute([$program_id, $department_id]);
                        
                        if ($stmt->rowCount() > 0) {
                            $success_message = "Program deleted successfully!";
                        } else {
                            $error_message = "Program not found or access denied.";
                        }
                    } catch (PDOException $e) {
                        error_log("Error deleting program: " . $e->getMessage());
                        $error_message = "Error deleting program. Please try again.";
                    }
                    break;
            }
        }
    }
}

// Handle GET delete request (for JavaScript redirect)
if (isset($_GET['delete_program']) && $department_id) {
    $program_id = filter_var($_GET['delete_program'], FILTER_VALIDATE_INT);
    if ($program_id) {
        // Redirect to POST to avoid CSRF issues
        echo "<script>if(confirm('Are you sure you want to delete this program?')) { 
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type=\"hidden\" name=\"action\" value=\"delete_program\"><input type=\"hidden\" name=\"program_id\" value=\"$program_id\"><input type=\"hidden\" name=\"csrf_token\" value=\"' + '" . ($_SESSION['csrf_token'] ?? '') . "' + \">';
            document.body.appendChild(form);
            form.submit();
        }</script>";
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get programs for this department
$programs = [];
$stats = [];
if ($department_id) {
    try {
        // Get programs with statistics (using only existing columns)
        $stmt = $pdo->prepare("
            SELECT o.id, o.name, o.status, o.created_at,
                   COUNT(DISTINCT s.id) as total_students,
                   COUNT(DISTINCT c.id) as total_courses,
                   COUNT(DISTINCT CASE WHEN s.year_level = '1' THEN s.id END) as year1_students,
                   COUNT(DISTINCT CASE WHEN s.year_level = '2' THEN s.id END) as year2_students,
                   COUNT(DISTINCT CASE WHEN s.year_level = '3' THEN s.id END) as year3_students,
                   COUNT(DISTINCT CASE WHEN u.status = 'active' THEN s.id END) as active_students
            FROM options o
            LEFT JOIN students s ON o.id = s.option_id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN courses c ON o.id = c.option_id
            WHERE o.department_id = ? AND o.status = 'active'
            GROUP BY o.id, o.name, o.status, o.created_at
            ORDER BY o.name
        ");
        $stmt->execute([$department_id]);
        $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get summary statistics
        $stats_stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT o.id) as total_programs,
                COUNT(DISTINCT s.id) as total_students,
                COUNT(DISTINCT c.id) as total_courses,
                COUNT(DISTINCT CASE WHEN u.status = 'active' THEN s.id END) as active_students
            FROM options o
            LEFT JOIN students s ON o.id = s.option_id
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN courses c ON o.id = c.option_id
            WHERE o.department_id = ? AND o.status = 'active'
        ");
        $stats_stmt->execute([$department_id]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching programs: " . $e->getMessage());
        $error_message = "Unable to load program data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Programs Management - HOD Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
        }
        
        .content-header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .programs-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .stat-item {
            text-align: center;
            padding: 20px;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .program-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .program-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
        }
    </style>
</head>

<body>
    <!-- Include HOD Sidebar -->
    <?php include 'includes/hod_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-2">
                        <i class="fas fa-graduation-cap text-primary me-2"></i>
                        Programs Management
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="hod-dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Programs</li>
                        </ol>
                    </nav>
                    <p class="text-muted mb-0"><?= htmlspecialchars($department_name ?? 'No Department Assigned') ?> Department</p>
                </div>
                <div>
                    <?php if ($department_id): ?>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                        <i class="fas fa-plus me-1"></i> Add Program
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-primary"><?= $stats['total_programs'] ?? 0 ?></div>
                        <div class="stat-label">Total Programs</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-success"><?= $stats['total_students'] ?? 0 ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-info"><?= $stats['total_courses'] ?? 0 ?></div>
                        <div class="stat-label">Total Courses</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-warning"><?= $stats['active_students'] ?? 0 ?></div>
                        <div class="stat-label">Active Students</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Programs List -->
        <div class="programs-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Department Programs
                </h5>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm" id="searchPrograms" placeholder="Search programs...">
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <?php if (empty($programs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-graduation-cap fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No Programs Found</h4>
                    <p class="text-muted">Start by adding your first academic program to the department.</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProgramModal">
                        <i class="fas fa-plus me-1"></i> Add First Program
                    </button>
                </div>
            <?php else: ?>
                <div class="row" id="programsList">
                    <?php foreach ($programs as $program): ?>
                    <div class="col-lg-6 mb-3 program-item" data-name="<?= htmlspecialchars($program['name']) ?>">
                        <div class="program-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6 class="mb-0 text-primary"><?= htmlspecialchars($program['name']) ?></h6>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="editProgram(<?= $program['id'] ?>, '<?= htmlspecialchars($program['name'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a></li>
                                        <li><a class="dropdown-item" href="hod-program-details.php?id=<?= $program['id'] ?>">
                                            <i class="fas fa-eye me-2"></i>View Details
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteProgram(<?= $program['id'] ?>, '<?= htmlspecialchars($program['name'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-trash me-2"></i>Delete
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <span class="badge bg-<?= $program['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <i class="fas fa-<?= $program['status'] === 'active' ? 'check-circle' : 'pause-circle' ?> me-1"></i>
                                    <?= ucfirst($program['status']) ?>
                                </span>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="stat-number text-success" style="font-size: 1.5rem;"><?= $program['total_students'] ?></div>
                                    <small class="text-muted">Total Students</small>
                                </div>
                                <div class="col-4">
                                    <div class="stat-number text-primary" style="font-size: 1.5rem;"><?= $program['total_courses'] ?></div>
                                    <small class="text-muted">Courses</small>
                                </div>
                                <div class="col-4">
                                    <div class="stat-number text-warning" style="font-size: 1.5rem;"><?= $program['active_students'] ?></div>
                                    <small class="text-muted">Active</small>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <div class="row text-center small text-muted">
                                    <div class="col-4">
                                        <span class="badge bg-light text-dark">Y1: <?= $program['year1_students'] ?></span>
                                    </div>
                                    <div class="col-4">
                                        <span class="badge bg-light text-dark">Y2: <?= $program['year2_students'] ?></span>
                                    </div>
                                    <div class="col-4">
                                        <span class="badge bg-light text-dark">Y3: <?= $program['year3_students'] ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    Created: <?= date('M j, Y', strtotime($program['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Program Modal -->
    <div class="modal fade" id="addProgramModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_program">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label for="program_name" class="form-label">Program Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="program_name" name="program_name" required maxlength="255" placeholder="Enter program name">
                            <div class="form-text">Enter a unique name for the academic program.</div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Additional program details like description and duration can be managed through the program details page after creation.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Add Program
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Program Modal -->
    <div class="modal fade" id="editProgramModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Program</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editProgramForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_program">
                        <input type="hidden" name="program_id" id="edit_program_id">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label for="edit_program_name" class="form-label">Program Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_program_name" name="program_name" required maxlength="255">
                            <div class="form-text">Enter a unique name for the academic program.</div>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Warning:</strong> Changing the program name will affect all associated students and courses.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Program
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Search and filter functionality
        document.getElementById('searchPrograms').addEventListener('input', function() {
            filterPrograms();
        });

        document.getElementById('filterStatus').addEventListener('change', function() {
            filterPrograms();
        });

        function filterPrograms() {
            const searchTerm = document.getElementById('searchPrograms').value.toLowerCase();
            const statusFilter = document.getElementById('filterStatus').value;
            const programs = document.querySelectorAll('.program-item');

            programs.forEach(program => {
                const programName = program.getAttribute('data-name').toLowerCase();
                const statusBadge = program.querySelector('.badge');
                const programStatus = statusBadge ? statusBadge.textContent.toLowerCase().trim() : '';

                const matchesSearch = programName.includes(searchTerm);
                const matchesStatus = !statusFilter || programStatus === statusFilter;

                if (matchesSearch && matchesStatus) {
                    program.style.display = 'block';
                } else {
                    program.style.display = 'none';
                }
            });
        }

        function editProgram(programId, programName) {
            // Populate edit form
            document.getElementById('edit_program_id').value = programId;
            document.getElementById('edit_program_name').value = programName;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editProgramModal')).show();
        }

        function deleteProgram(programId, programName) {
            if (confirm(`Are you sure you want to delete the program "${programName}"?\\n\\nThis action cannot be undone and will affect all associated students and courses.`)) {
                // Create and submit form for POST request
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_program">
                    <input type="hidden" name="program_id" value="${programId}">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Add program form validation
            const addForm = document.querySelector('#addProgramModal form');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    const programName = document.getElementById('program_name').value.trim();
                    if (programName.length < 2) {
                        e.preventDefault();
                        alert('Program name must be at least 2 characters long.');
                        return false;
                    }
                });
            }

            // Edit program form validation
            const editForm = document.querySelector('#editProgramForm');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    const programName = document.getElementById('edit_program_name').value.trim();
                    if (programName.length < 2) {
                        e.preventDefault();
                        alert('Program name must be at least 2 characters long.');
                        return false;
                    }
                });
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>
