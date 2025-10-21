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
                error_log("HOD Timetable: Found department assignment via legacy hod_id match (user_id instead of lecturer_id) for user $user_id");
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
            error_log("HOD Timetable: Department assignment found via {$dept_result['match_type']} for user $user_id, department $department_name");
        }
    }
} catch (PDOException $e) {
    error_log("Database error in hod-timetable.php: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later. Error: " . $e->getMessage();
}

// Get current week's date range
$current_week_start = date('Y-m-d', strtotime('monday this week'));
$current_week_end = date('Y-m-d', strtotime('sunday this week'));

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_schedule':
                try {
                    // Create timetable table if it doesn't exist
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS timetable (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            course_id INT,
                            lecturer_id INT,
                            room VARCHAR(100),
                            day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                            start_time TIME,
                            end_time TIME,
                            semester VARCHAR(50),
                            academic_year VARCHAR(20),
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (course_id) REFERENCES courses(id),
                            FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
                        )
                    ");

                    $stmt = $pdo->prepare("
                        INSERT INTO timetable (course_id, lecturer_id, room, day_of_week, start_time, end_time, semester, academic_year) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $_POST['course_id'],
                        $_POST['lecturer_id'],
                        $_POST['room'],
                        $_POST['day_of_week'],
                        $_POST['start_time'],
                        $_POST['end_time'],
                        $_POST['semester'] ?? 'Semester 1',
                        $_POST['academic_year'] ?? date('Y')
                    ]);
                    $success_message = "Schedule added successfully!";
                } catch (PDOException $e) {
                    $error_message = "Error adding schedule: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get timetable data
$timetable_data = [];
$courses = [];
$lecturers = [];

try {
    // Get courses for this department
    $courses_stmt = $pdo->prepare("
        SELECT c.id, c.name, c.code, o.name as program_name
        FROM courses c
        JOIN options o ON c.option_id = o.id
        WHERE o.department_id = ?
        ORDER BY c.name
    ");
    $courses_stmt->execute([$department_id]);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get lecturers for this department
    $lecturers_stmt = $pdo->prepare("
        SELECT l.id, l.first_name, l.last_name, l.email
        FROM lecturers l
        WHERE l.department_id = ? OR l.id IN (
            SELECT DISTINCT c.lecturer_id 
            FROM courses c 
            JOIN options o ON c.option_id = o.id 
            WHERE o.department_id = ?
        )
        ORDER BY l.first_name, l.last_name
    ");
    $lecturers_stmt->execute([$department_id, $department_id]);
    $lecturers = $lecturers_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get timetable data
    $timetable_stmt = $pdo->prepare("
        SELECT t.*, c.name as course_name, c.code as course_code,
               l.first_name, l.last_name, o.name as program_name
        FROM timetable t
        LEFT JOIN courses c ON t.course_id = c.id
        LEFT JOIN lecturers l ON t.lecturer_id = l.id
        LEFT JOIN options o ON c.option_id = o.id
        WHERE o.department_id = ? OR t.lecturer_id IN (
            SELECT id FROM lecturers WHERE department_id = ?
        )
        ORDER BY 
            CASE t.day_of_week 
                WHEN 'Monday' THEN 1
                WHEN 'Tuesday' THEN 2
                WHEN 'Wednesday' THEN 3
                WHEN 'Thursday' THEN 4
                WHEN 'Friday' THEN 5
                WHEN 'Saturday' THEN 6
                WHEN 'Sunday' THEN 7
            END,
            t.start_time
    ");
    $timetable_stmt->execute([$department_id, $department_id]);
    $timetable_data = $timetable_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching timetable data: " . $e->getMessage());
    $error_message = "Unable to load timetable data.";
}

// Organize timetable by day and time
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$time_slots = [];
for ($hour = 8; $hour <= 18; $hour++) {
    $time_slots[] = sprintf('%02d:00', $hour);
}

$organized_timetable = [];
foreach ($days as $day) {
    $organized_timetable[$day] = [];
    foreach ($time_slots as $time) {
        $organized_timetable[$day][$time] = [];
    }
}

foreach ($timetable_data as $entry) {
    $day = $entry['day_of_week'];
    $start_time = substr($entry['start_time'], 0, 5);
    if (isset($organized_timetable[$day][$start_time])) {
        $organized_timetable[$day][$start_time][] = $entry;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable Management - HOD Dashboard</title>
    
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
        
        .timetable-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .timetable-grid {
            overflow-x: auto;
        }
        
        .timetable-table {
            min-width: 800px;
            border-collapse: separate;
            border-spacing: 2px;
        }
        
        .timetable-table th {
            background: #f8f9fa;
            padding: 15px 10px;
            text-align: center;
            font-weight: 600;
            border-radius: 8px;
            min-width: 120px;
        }
        
        .timetable-table td {
            padding: 8px;
            vertical-align: top;
            border-radius: 6px;
            height: 60px;
            position: relative;
        }
        
        .time-slot {
            background: #e9ecef;
            font-weight: 600;
            text-align: center;
            width: 80px;
        }
        
        .day-cell {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .schedule-entry {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .schedule-entry:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
        }
        
        .schedule-entry .course-code {
            font-weight: bold;
            font-size: 0.9rem;
        }
        
        .schedule-entry .lecturer-name {
            font-size: 0.75rem;
            opacity: 0.9;
        }
        
        .schedule-entry .room-info {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        
        .empty-slot {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .empty-slot:hover {
            background: #e9ecef;
            border-color: #007bff;
            color: #007bff;
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
                        <i class="fas fa-calendar-alt text-primary me-2"></i>
                        Timetable Management
                    </h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="hod-dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Timetable</li>
                        </ol>
                    </nav>
                    <p class="text-muted mb-0"><?= htmlspecialchars($department_name) ?> Department</p>
                </div>
                <div>
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                        <i class="fas fa-plus me-1"></i> Add Schedule
                    </button>
                    <button class="btn btn-primary" onclick="exportTimetable()">
                        <i class="fas fa-download me-1"></i> Export
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

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Timetable Controls -->
        <div class="timetable-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-table me-2"></i>
                    Weekly Timetable
                </h5>
                <div class="d-flex gap-2">
                    <select class="form-select form-select-sm" id="semesterFilter">
                        <option value="Semester 1">Semester 1</option>
                        <option value="Semester 2">Semester 2</option>
                    </select>
                    <select class="form-select form-select-sm" id="yearFilter">
                        <option value="<?= date('Y') ?>"><?= date('Y') ?></option>
                        <option value="<?= date('Y') + 1 ?>"><?= date('Y') + 1 ?></option>
                    </select>
                </div>
            </div>

            <!-- Timetable Grid -->
            <div class="timetable-grid">
                <table class="table timetable-table">
                    <thead>
                        <tr>
                            <th class="time-slot">Time</th>
                            <?php foreach ($days as $day): ?>
                                <th><?= $day ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($time_slots as $time): ?>
                            <tr>
                                <td class="time-slot"><?= $time ?></td>
                                <?php foreach ($days as $day): ?>
                                    <td class="day-cell">
                                        <?php if (!empty($organized_timetable[$day][$time])): ?>
                                            <?php foreach ($organized_timetable[$day][$time] as $entry): ?>
                                                <div class="schedule-entry" onclick="viewScheduleDetails(<?= $entry['id'] ?>)">
                                                    <div class="course-code"><?= htmlspecialchars($entry['course_code']) ?></div>
                                                    <div class="lecturer-name"><?= htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']) ?></div>
                                                    <div class="room-info">
                                                        <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($entry['room']) ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="empty-slot" onclick="addScheduleForSlot('<?= $day ?>', '<?= $time ?>')">
                                                <i class="fas fa-plus"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row">
            <div class="col-md-3">
                <div class="timetable-container text-center">
                    <i class="fas fa-book fa-2x text-primary mb-2"></i>
                    <h4><?= count($courses) ?></h4>
                    <p class="text-muted mb-0">Total Courses</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="timetable-container text-center">
                    <i class="fas fa-chalkboard-teacher fa-2x text-success mb-2"></i>
                    <h4><?= count($lecturers) ?></h4>
                    <p class="text-muted mb-0">Active Lecturers</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="timetable-container text-center">
                    <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                    <h4><?= count($timetable_data) ?></h4>
                    <p class="text-muted mb-0">Scheduled Classes</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="timetable-container text-center">
                    <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                    <h4><?= count($time_slots) * count($days) ?></h4>
                    <p class="text-muted mb-0">Available Slots</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Schedule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_schedule">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="course_id" class="form-label">Course</label>
                                    <select class="form-select" id="course_id" name="course_id" required>
                                        <option value="">Select course...</option>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?= $course['id'] ?>">
                                                [<?= htmlspecialchars($course['code']) ?>] <?= htmlspecialchars($course['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="lecturer_id" class="form-label">Lecturer</label>
                                    <select class="form-select" id="lecturer_id" name="lecturer_id" required>
                                        <option value="">Select lecturer...</option>
                                        <?php foreach ($lecturers as $lecturer): ?>
                                            <option value="<?= $lecturer['id'] ?>">
                                                <?= htmlspecialchars($lecturer['first_name'] . ' ' . $lecturer['last_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="day_of_week" class="form-label">Day</label>
                                    <select class="form-select" id="day_of_week" name="day_of_week" required>
                                        <option value="">Select day...</option>
                                        <?php foreach ($days as $day): ?>
                                            <option value="<?= $day ?>"><?= $day ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="room" class="form-label">Room</label>
                                    <input type="text" class="form-control" id="room" name="room" required placeholder="e.g., Room 101">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_time" class="form-label">Start Time</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_time" class="form-label">End Time</label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="semester" class="form-label">Semester</label>
                                    <select class="form-select" id="semester" name="semester" required>
                                        <option value="Semester 1">Semester 1</option>
                                        <option value="Semester 2">Semester 2</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="academic_year" class="form-label">Academic Year</label>
                                    <input type="number" class="form-control" id="academic_year" name="academic_year" 
                                           value="<?= date('Y') ?>" min="<?= date('Y') ?>" max="<?= date('Y') + 5 ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function addScheduleForSlot(day, time) {
            document.getElementById('day_of_week').value = day;
            document.getElementById('start_time').value = time;
            
            // Calculate end time (1 hour later)
            const startTime = new Date('2000-01-01 ' + time);
            startTime.setHours(startTime.getHours() + 1);
            const endTime = startTime.toTimeString().substr(0, 5);
            document.getElementById('end_time').value = endTime;
            
            new bootstrap.Modal(document.getElementById('addScheduleModal')).show();
        }

        function viewScheduleDetails(scheduleId) {
            // In a real application, this would open a detailed view
            alert('Schedule details for ID: ' + scheduleId);
        }

        function exportTimetable() {
            // In a real application, this would generate and download a PDF/Excel file
            alert('Timetable export functionality would be implemented here');
        }

        // Filter functionality
        document.getElementById('semesterFilter').addEventListener('change', function() {
            // In a real application, this would filter the timetable by semester
            console.log('Filter by semester:', this.value);
        });

        document.getElementById('yearFilter').addEventListener('change', function() {
            // In a real application, this would filter the timetable by year
            console.log('Filter by year:', this.value);
        });
    </script>
</body>
</html>
