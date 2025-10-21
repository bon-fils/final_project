<?php
require_once "config.php";
session_start();
require_once "session_check.php";

// Ensure user is logged in and is HoD
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hod') {
    header("Location: login_new.php");
    exit;
}

// Get HoD information and verify department assignment
$user_id = $_SESSION['user_id'];
$department_name = null;
$department_id = null;
try {
    // First, check if the user has a lecturer record
    $lecturer_stmt = $pdo->prepare("SELECT id, gender, dob, id_number, department_id, education_level FROM lecturers WHERE user_id = ?");
    $lecturer_stmt->execute([$user_id]);
    $lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer) {
        // HOD user doesn't have a lecturer record
        $error_message = "You are not registered as a lecturer. Please contact administrator.";
    } else {
        $lecturer_id = $lecturer['id'];

        // Try multiple approaches to find department assignment (handles both correct and legacy hod_id references)
        $dept_result = null;

        // Approach 1: Correct way - hod_id points to lecturers.id
        $stmt = $pdo->prepare("
            SELECT d.name as department_name, d.id as department_id, 'direct' as match_type
            FROM departments d
            WHERE d.hod_id = ? AND d.hod_id IS NOT NULL
        ");
        $stmt->execute([$lecturer_id]);
        $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Approach 2: Legacy way - hod_id might point to users.id (incorrect but may exist in data)
        if (!$dept_result) {
            $stmt = $pdo->prepare("
                SELECT d.name as department_name, d.id as department_id, 'legacy' as match_type
                FROM departments d
                WHERE d.hod_id = ? AND d.hod_id IS NOT NULL
            ");
            $stmt->execute([$user_id]);
            $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);

            // If found via legacy method, log it for potential data fix
            if ($dept_result) {
                error_log("HOD Programs: Found department assignment via legacy hod_id match (user_id instead of lecturer_id) for user $user_id");
            }
        }

        // Approach 3: Check if lecturer's department_id matches any department's hod_id
        if (!$dept_result && $lecturer['department_id']) {
            $stmt = $pdo->prepare("
                SELECT d.name as department_name, d.id as department_id, 'department_match' as match_type
                FROM departments d
                WHERE d.id = ? AND d.hod_id IS NOT NULL
            ");
            $stmt->execute([$lecturer['department_id']]);
            $dept_result = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$dept_result) {
            // User is HOD but not assigned to any department
            $error_message = "You are not assigned as Head of Department for any department. Please contact administrator.";
        } else {
            $department_name = $dept_result['department_name'];
            $department_id = $dept_result['department_id'];

            // Get user information
            $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $user['department_name'] = $department_name;
            $user['department_id'] = $department_id;

            // Log match type for debugging
            error_log("HOD Programs: Department assignment found via {$dept_result['match_type']} for user $user_id, department $department_name");
        }
    }
} catch (PDOException $e) {
    error_log("Database error in hod-programs.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later. Error: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $department_id) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_program':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO options (name, description, department_id, duration_years, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $_POST['program_name'],
                        $_POST['description'],
                        $department_id,
                        $_POST['duration_years']
                    ]);
                    $success_message = "Program added successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error adding program: " . $e->getMessage();
                }
                break;

            case 'update_program':
                try {
                    $stmt = $pdo->prepare("
                        UPDATE options
                        SET name = ?, description = ?, duration_years = ?
                        WHERE id = ? AND department_id = ?
                    ");
                    $stmt->execute([
                        $_POST['program_name'],
                        $_POST['description'],
                        $_POST['duration_years'],
                        $_POST['program_id'],
                        $department_id
                    ]);
                    $success_message = "Program updated successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error updating program: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get programs for this department
$programs = [];
$stats = [];
if ($department_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.id, o.name, o.description, o.duration_years, o.created_at,
                   COUNT(DISTINCT s.id) as total_students,
                   COUNT(DISTINCT c.id) as total_courses,
                   COUNT(DISTINCT CASE WHEN s.year_level = 1 THEN s.id END) as year1_students,
                   COUNT(DISTINCT CASE WHEN s.year_level = 2 THEN s.id END) as year2_students,
                   COUNT(DISTINCT CASE WHEN s.year_level = 3 THEN s.id END) as year3_students
            FROM options o
            LEFT JOIN students s ON o.id = s.option_id
            LEFT JOIN courses c ON o.id = c.option_id
            WHERE o.department_id = ?
            GROUP BY o.id, o.name, o.description, o.duration_years, o.created_at
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
                AVG(o.duration_years) as avg_duration
            FROM options o
            LEFT JOIN students s ON o.id = s.option_id
            LEFT JOIN courses c ON o.id = c.option_id
            WHERE o.department_id = ?
        ");
        $stats_stmt->execute([$department_id]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error fetching programs: " . $e->getMessage());
        $error_message = "Unable to load program data.";
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
                        <div class="stat-number text-warning"><?= number_format($stats['avg_duration'] ?? 0, 1) ?></div>
                        <div class="stat-label">Avg Duration (Years)</div>
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
                    <select class="form-select form-select-sm" id="filterDuration">
                        <option value="">All Durations</option>
                        <option value="2">2 Years</option>
                        <option value="3">3 Years</option>
                        <option value="4">4 Years</option>
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
                    <div class="col-lg-6 mb-3 program-item" data-duration="<?= $program['duration_years'] ?>">
                        <div class="program-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h6 class="mb-0 text-primary"><?= htmlspecialchars($program['name']) ?></h6>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="editProgram(<?= $program['id'] ?>)">
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a></li>
                                        <li><a class="dropdown-item" href="hod-program-details.php?id=<?= $program['id'] ?>">
                                            <i class="fas fa-eye me-2"></i>View Details
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteProgram(<?= $program['id'] ?>)">
                                            <i class="fas fa-trash me-2"></i>Delete
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <p class="text-muted mb-3"><?= htmlspecialchars($program['description']) ?></p>
                            
                            <div class="row text-center">
                                <div class="col-3">
                                    <div class="stat-number text-info" style="font-size: 1.5rem;"><?= $program['duration_years'] ?></div>
                                    <small class="text-muted">Years</small>
                                </div>
                                <div class="col-3">
                                    <div class="stat-number text-success" style="font-size: 1.5rem;"><?= $program['total_students'] ?></div>
                                    <small class="text-muted">Students</small>
                                </div>
                                <div class="col-3">
                                    <div class="stat-number text-primary" style="font-size: 1.5rem;"><?= $program['total_courses'] ?></div>
                                    <small class="text-muted">Courses</small>
                                </div>
                                <div class="col-3">
                                    <div class="stat-number text-warning" style="font-size: 1.5rem;">
                                        <?= $program['year1_students'] + $program['year2_students'] + $program['year3_students'] ?>
                                    </div>
                                    <small class="text-muted">Active</small>
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
                        <div class="mb-3">
                            <label for="program_name" class="form-label">Program Name</label>
                            <input type="text" class="form-control" id="program_name" name="program_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="duration_years" class="form-label">Duration (Years)</label>
                            <select class="form-select" id="duration_years" name="duration_years" required>
                                <option value="">Select duration...</option>
                                <option value="2">2 Years</option>
                                <option value="3">3 Years</option>
                                <option value="4">4 Years</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Program</button>
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
                        <div class="mb-3">
                            <label for="edit_program_name" class="form-label">Program Name</label>
                            <input type="text" class="form-control" id="edit_program_name" name="program_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_duration_years" class="form-label">Duration (Years)</label>
                            <select class="form-select" id="edit_duration_years" name="duration_years" required>
                                <option value="">Select duration...</option>
                                <option value="2">2 Years</option>
                                <option value="3">3 Years</option>
                                <option value="4">4 Years</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Program</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Search functionality
        document.getElementById('searchPrograms').addEventListener('input', function() {
            filterPrograms();
        });

        document.getElementById('filterDuration').addEventListener('change', function() {
            filterPrograms();
        });

        function filterPrograms() {
            const searchTerm = document.getElementById('searchPrograms').value.toLowerCase();
            const durationFilter = document.getElementById('filterDuration').value;
            const programs = document.querySelectorAll('.program-item');

            programs.forEach(program => {
                const programName = program.querySelector('h6').textContent.toLowerCase();
                const programDescription = program.querySelector('p').textContent.toLowerCase();
                const programDuration = program.getAttribute('data-duration');

                const matchesSearch = programName.includes(searchTerm) || programDescription.includes(searchTerm);
                const matchesDuration = !durationFilter || programDuration === durationFilter;

                if (matchesSearch && matchesDuration) {
                    program.style.display = 'block';
                } else {
                    program.style.display = 'none';
                }
            });
        }

        function editProgram(programId) {
            // Get program data (in a real app, this would be an AJAX call)
            const programCard = document.querySelector(`[onclick="editProgram(${programId})"]`).closest('.program-card');
            const programName = programCard.querySelector('h6').textContent;
            const programDescription = programCard.querySelector('p').textContent;
            
            // Populate edit form
            document.getElementById('edit_program_id').value = programId;
            document.getElementById('edit_program_name').value = programName;
            document.getElementById('edit_description').value = programDescription;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editProgramModal')).show();
        }

        function deleteProgram(programId) {
            if (confirm('Are you sure you want to delete this program? This action cannot be undone.')) {
                // In a real app, this would be an AJAX call
                window.location.href = `?delete_program=${programId}`;
            }
        }
    </script>
</body>
</html>
