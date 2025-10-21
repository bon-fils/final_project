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
        header("Location: login_new.php?error=not_assigned");
        exit;
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
                error_log("HOD Courses: Found department assignment via legacy hod_id match (user_id instead of lecturer_id) for user $user_id");
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
            header("Location: login_new.php?error=not_assigned");
            exit;
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
            error_log("HOD Courses: Department assignment found via {$dept_result['match_type']} for user $user_id, department $department_name");
        }
    }
} catch (PDOException $e) {
    error_log("Database error in hod-courses.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later. Error: " . $e->getMessage();
}

// Get courses in this department
$courses = [];
$stats = [];
try {
    // Get courses with lecturer and program information
    $stmt = $pdo->prepare("
        SELECT c.id, c.name as course_name, c.code, c.credits, c.description,
               o.name as program_name,
               l.first_name as lecturer_fname, l.last_name as lecturer_lname,
               l.email as lecturer_email,
               COUNT(DISTINCT s.id) as enrolled_students,
               COUNT(DISTINCT ats.id) as total_sessions,
               AVG(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100 as avg_attendance
        FROM courses c
        JOIN options o ON c.option_id = o.id
        LEFT JOIN lecturers l ON c.lecturer_id = l.id
        LEFT JOIN students s ON s.option_id = o.id
        LEFT JOIN attendance_sessions ats ON c.id = ats.course_id
        LEFT JOIN attendance_records ar ON ats.id = ar.session_id
        WHERE o.department_id = ?
        GROUP BY c.id, c.name, c.code, c.credits, c.description, o.name, l.first_name, l.last_name, l.email
        ORDER BY o.name, c.name
    ");
    $stmt->execute([$department_id]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(c.id) as total_courses,
            COUNT(CASE WHEN c.lecturer_id IS NOT NULL THEN 1 END) as assigned_courses,
            COUNT(CASE WHEN c.lecturer_id IS NULL THEN 1 END) as unassigned_courses,
            COUNT(DISTINCT o.id) as programs_count,
            SUM(c.credits) as total_credits
        FROM courses c
        JOIN options o ON c.option_id = o.id
        WHERE o.department_id = ?
    ");
    $stats_stmt->execute([$department_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Get available lecturers for assignment
    $lecturers_stmt = $pdo->prepare("
        SELECT l.id, l.first_name, l.last_name, l.email,
               COUNT(c.id) as assigned_courses_count
        FROM lecturers l
        LEFT JOIN courses c ON l.id = c.lecturer_id
        WHERE l.department_id = ?
        GROUP BY l.id, l.first_name, l.last_name, l.email
        ORDER BY l.first_name, l.last_name
    ");
    $lecturers_stmt->execute([$department_id]);
    $available_lecturers = $lecturers_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $error_message = "Unable to load course data.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management - HOD Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
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
        
        .courses-table-container {
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
        
        .course-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .course-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .course-card.unassigned {
            border-left: 4px solid #dc3545;
            background: #fff5f5;
        }
        
        .course-card.assigned {
            border-left: 4px solid #28a745;
        }
        
        .lecturer-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
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
                        <i class="fas fa-book text-primary me-2"></i>
                        Course Management
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="hod-dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Courses</li>
                        </ol>
                    </nav>
                    <p class="text-muted mb-0"><?= htmlspecialchars($department_name) ?> Department</p>
                </div>
                <div>
                    <button class="btn btn-warning me-2" onclick="bulkAssignCourses()">
                        <i class="fas fa-tasks me-1"></i> Bulk Assign
                    </button>
                    <button class="btn btn-success" onclick="addNewCourse()">
                        <i class="fas fa-plus me-1"></i> Add Course
                    </button>
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-primary"><?= $stats['total_courses'] ?? 0 ?></div>
                        <div class="stat-label">Total Courses</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-success"><?= $stats['assigned_courses'] ?? 0 ?></div>
                        <div class="stat-label">Assigned Courses</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-danger"><?= $stats['unassigned_courses'] ?? 0 ?></div>
                        <div class="stat-label">Unassigned Courses</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stat-item">
                        <div class="stat-number text-info"><?= $stats['total_credits'] ?? 0 ?></div>
                        <div class="stat-label">Total Credits</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course Assignment Progress -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="stats-card">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-line me-2"></i>
                        Course Assignment Progress
                    </h5>
                    <div class="row">
                        <div class="col-md-8">
                            <div class="progress mb-2" style="height: 25px;">
                                <?php 
                                $assignment_percentage = $stats['total_courses'] > 0 ? 
                                    round(($stats['assigned_courses'] / $stats['total_courses']) * 100, 1) : 0;
                                ?>
                                <div class="progress-bar bg-success" style="width: <?= $assignment_percentage ?>%">
                                    <?= $assignment_percentage ?>% Assigned
                                </div>
                            </div>
                            <small class="text-muted">
                                <?= $stats['assigned_courses'] ?> of <?= $stats['total_courses'] ?> courses have been assigned to lecturers
                            </small>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($stats['unassigned_courses'] > 0): ?>
                                <button class="btn btn-warning" onclick="showUnassignedCourses()">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?= $stats['unassigned_courses'] ?> Need Assignment
                                </button>
                            <?php else: ?>
                                <span class="badge bg-success fs-6">
                                    <i class="fas fa-check-circle me-1"></i>
                                    All Courses Assigned
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Courses Grid/List -->
        <div class="courses-table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Courses List
                </h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="programFilter" onchange="filterByProgram()">
                        <option value="">All Programs</option>
                        <?php 
                        $programs = array_unique(array_column($courses, 'program_name'));
                        foreach ($programs as $program): 
                        ?>
                            <option value="<?= htmlspecialchars($program) ?>"><?= htmlspecialchars($program) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-select form-select-sm" id="assignmentFilter" onchange="filterByAssignment()">
                        <option value="">All Courses</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Unassigned</option>
                    </select>
                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleView()" id="viewToggle">
                        <i class="fas fa-th-large"></i> Grid View
                    </button>
                </div>
            </div>
            
            <!-- Table View -->
            <div id="tableView">
                <div class="table-responsive">
                    <table class="table table-hover" id="coursesTable">
                        <thead class="table-light">
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Program</th>
                                <th>Credits</th>
                                <th>Lecturer</th>
                                <th>Students</th>
                                <th>Attendance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                            <tr class="<?= $course['lecturer_fname'] ? 'table-success' : 'table-warning' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($course['code']) ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($course['course_name']) ?></div>
                                        <?php if ($course['description']): ?>
                                            <small class="text-muted"><?= htmlspecialchars(substr($course['description'], 0, 50)) ?>...</small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($course['program_name']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= $course['credits'] ?> Credits</span>
                                </td>
                                <td>
                                    <?php if ($course['lecturer_fname']): ?>
                                        <div class="lecturer-badge">
                                            <i class="fas fa-user me-1"></i>
                                            <?= htmlspecialchars($course['lecturer_fname'] . ' ' . $course['lecturer_lname']) ?>
                                        </div>
                                        <small class="text-muted d-block"><?= htmlspecialchars($course['lecturer_email']) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Unassigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?= $course['enrolled_students'] ?> Students</span>
                                </td>
                                <td>
                                    <?php if ($course['avg_attendance'] !== null): ?>
                                        <?php 
                                        $attendance = round($course['avg_attendance'], 1);
                                        $badge_class = $attendance >= 80 ? 'success' : ($attendance >= 60 ? 'warning' : 'danger');
                                        ?>
                                        <span class="badge bg-<?= $badge_class ?>"><?= $attendance ?>%</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No Data</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" onclick="viewCourse(<?= $course['id'] ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" onclick="assignLecturer(<?= $course['id'] ?>)" title="Assign Lecturer">
                                            <i class="fas fa-user-plus"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="editCourse(<?= $course['id'] ?>)" title="Edit Course">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grid View -->
            <div id="gridView" style="display: none;">
                <div class="row">
                    <?php foreach ($courses as $course): ?>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="course-card <?= $course['lecturer_fname'] ? 'assigned' : 'unassigned' ?>">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0"><?= htmlspecialchars($course['course_name']) ?></h6>
                                <span class="badge bg-info"><?= $course['credits'] ?> Credits</span>
                            </div>
                            <p class="text-muted mb-2">
                                <strong><?= htmlspecialchars($course['code']) ?></strong> • 
                                <?= htmlspecialchars($course['program_name']) ?>
                            </p>
                            
                            <?php if ($course['lecturer_fname']): ?>
                                <div class="lecturer-info mb-2">
                                    <small class="text-success">
                                        <i class="fas fa-user me-1"></i>
                                        <?= htmlspecialchars($course['lecturer_fname'] . ' ' . $course['lecturer_lname']) ?>
                                    </small>
                                </div>
                            <?php else: ?>
                                <div class="mb-2">
                                    <span class="badge bg-danger">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Needs Lecturer Assignment
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?= $course['enrolled_students'] ?> Students
                                </small>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary" onclick="viewCourse(<?= $course['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-outline-warning" onclick="assignLecturer(<?= $course['id'] ?>)">
                                        <i class="fas fa-user-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Lecturer Modal -->
    <div class="modal fade" id="assignLecturerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Lecturer to Course</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="assignLecturerForm">
                        <input type="hidden" id="courseId" name="course_id">
                        <div class="mb-3">
                            <label for="lecturerId" class="form-label">Select Lecturer</label>
                            <select class="form-select" id="lecturerId" name="lecturer_id" required>
                                <option value="">Choose a lecturer...</option>
                                <?php foreach ($available_lecturers as $lecturer): ?>
                                    <option value="<?= $lecturer['id'] ?>">
                                        <?= htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']) ?>
                                        (<?= $lecturer['assigned_courses_count'] ?> courses)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="assignmentNotes" class="form-label">Assignment Notes (Optional)</label>
                            <textarea class="form-control" id="assignmentNotes" name="notes" rows="3" 
                                      placeholder="Any special instructions or notes for this assignment..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="submitAssignment()">Assign Lecturer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#coursesTable').DataTable({
                responsive: true,
                pageLength: 25,
                order: [[1, 'asc']],
                language: {
                    search: "Search courses:",
                    lengthMenu: "Show _MENU_ courses per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ courses"
                }
            });
        });

        function toggleView() {
            const tableView = document.getElementById('tableView');
            const gridView = document.getElementById('gridView');
            const toggleBtn = document.getElementById('viewToggle');
            
            if (tableView.style.display === 'none') {
                tableView.style.display = 'block';
                gridView.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fas fa-th-large"></i> Grid View';
            } else {
                tableView.style.display = 'none';
                gridView.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fas fa-table"></i> Table View';
            }
        }

        function filterByProgram() {
            const program = document.getElementById('programFilter').value;
            const table = $('#coursesTable').DataTable();
            table.column(2).search(program).draw();
        }

        function filterByAssignment() {
            const assignment = document.getElementById('assignmentFilter').value;
            const table = $('#coursesTable').DataTable();
            
            if (assignment === 'assigned') {
                table.column(4).search('^(?!.*Unassigned).*$', true, false).draw();
            } else if (assignment === 'unassigned') {
                table.column(4).search('Unassigned').draw();
            } else {
                table.column(4).search('').draw();
            }
        }

        function viewCourse(courseId) {
            window.location.href = `hod-course-details.php?id=${courseId}`;
        }

        function assignLecturer(courseId) {
            document.getElementById('courseId').value = courseId;
            new bootstrap.Modal(document.getElementById('assignLecturerModal')).show();
        }

        function editCourse(courseId) {
            window.location.href = `hod-edit-course.php?id=${courseId}`;
        }

        function addNewCourse() {
            window.location.href = 'hod-add-course.php';
        }

        function bulkAssignCourses() {
            window.location.href = 'hod-bulk-assign-courses.php';
        }

        function showUnassignedCourses() {
            document.getElementById('assignmentFilter').value = 'unassigned';
            filterByAssignment();
        }

        function submitAssignment() {
            const form = document.getElementById('assignLecturerForm');
            const formData = new FormData(form);
            
            fetch('api/assign-course-lecturer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while assigning the lecturer.');
            });
        }
    </script>
</body>
</html>
