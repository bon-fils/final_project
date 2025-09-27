<?php
// Version without authentication for testing
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "config.php";

// Simulate a lecturer login for testing
$_SESSION['user_id'] = 1; // Assume user ID 1 exists
$_SESSION['role'] = 'lecturer';

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

// Validate user_id
if (!$user_id) {
    die("No user ID set in session");
}

echo "<!-- Debug: User ID = $user_id, Role = {$_SESSION['role']} -->";

// Get lecturer's department_id first - join on email instead of ID
$dept_stmt = $pdo->prepare("
    SELECT l.department_id, l.id as lecturer_id
    FROM lecturers l
    INNER JOIN users u ON l.email = u.email
    WHERE u.id = :user_id AND u.role = 'lecturer'
");
$dept_stmt->execute(['user_id' => $user_id]);
$lecturer_dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer_dept || !isset($lecturer_dept['department_id'])) {
    echo "<p style='color: red;'>No lecturer record found. Creating one...</p>";

    // Try to create a lecturer record if it doesn't exist
    $create_lecturer_stmt = $pdo->prepare("
        INSERT INTO lecturers (first_name, last_name, email, department_id, role, password)
        SELECT
            CASE WHEN username LIKE '% %' THEN SUBSTRING_INDEX(username, ' ', 1) ELSE username END as first_name,
            CASE WHEN username LIKE '% %' THEN SUBSTRING_INDEX(username, ' ', -1) ELSE '' END as last_name,
            email, 7, 'lecturer', '12345'
        FROM users
        WHERE id = :user_id AND role = 'lecturer'
        ON DUPLICATE KEY UPDATE email = email
    ");
    $create_lecturer_stmt->execute(['user_id' => $user_id]);

    // Try again to get the department
    $dept_stmt->execute(['user_id' => $user_id]);
    $lecturer_dept = $dept_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$lecturer_dept || !isset($lecturer_dept['department_id'])) {
        die("<p style='color: red;'>Could not create or find lecturer record. Please check database setup.</p>");
    }
}

echo "<!-- Debug: Lecturer ID = {$lecturer_dept['lecturer_id']}, Department = {$lecturer_dept['department_id']} -->";

// Store lecturer_id in session for other pages to use
$_SESSION['lecturer_id'] = $lecturer_dept['lecturer_id'];

// First, add lecturer_id column to courses table if it doesn't exist
try {
    $pdo->query("ALTER TABLE courses ADD COLUMN lecturer_id INT NULL AFTER department_id");
    $pdo->query("CREATE INDEX idx_lecturer_id ON courses(lecturer_id)");
} catch (PDOException $e) {
    // Column might already exist, continue
}

// Update courses to assign them to the current lecturer if not already assigned
$update_stmt = $pdo->prepare("
    UPDATE courses
    SET lecturer_id = :lecturer_id
    WHERE lecturer_id IS NULL AND department_id = :department_id
");
$update_stmt->execute([
    'lecturer_id' => $lecturer_dept['lecturer_id'],
    'department_id' => $lecturer_dept['department_id']
]);

// Fetch year levels from students in lecturer's courses
$stmtClasses = $pdo->prepare("
    SELECT DISTINCT s.year_level
    FROM students s
    INNER JOIN courses c ON s.option_id = c.id
    WHERE c.lecturer_id = :lecturer_id
    ORDER BY s.year_level ASC
");
$stmtClasses->execute(['lecturer_id' => $lecturer_dept['lecturer_id']]);
$classRows = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

$classes = [];
foreach ($classRows as $row) {
    $classes[] = ['id' => $row['year_level'], 'name' => $row['year_level']];
}

echo "<!-- Debug: Found " . count($classes) . " classes -->";

// Fetch courses for selected class
$selectedClassId = $_GET['class_id'] ?? null;
$selectedCourseId = $_GET['course_id'] ?? null;
$courses = [];
if ($selectedClassId) {
    $stmtCourses = $pdo->prepare("
        SELECT c.id, c.name
        FROM courses c
        INNER JOIN students s ON s.option_id = c.id
        WHERE c.lecturer_id = :lecturer_id AND s.year_level = :year_level
        GROUP BY c.id, c.name
        ORDER BY c.name ASC
    ");
    $stmtCourses->execute([
        'lecturer_id' => $lecturer_dept['lecturer_id'],
        'year_level' => $selectedClassId
    ]);
    $courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);
}

echo "<!-- Debug: Found " . count($courses) . " courses for class $selectedClassId -->";

// Fetch attendance data
$attendanceData = [];
$attendanceDetailsData = [];
if ($selectedClassId && $selectedCourseId) {
    try {
        // Main report - get students and their attendance for this course
        $stmtAttendance = $pdo->prepare("
            SELECT
                s.id AS student_id,
                CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                COUNT(CASE WHEN ar.status = 'present' THEN 1 END) AS present_count,
                COUNT(ar.id) AS total_count
            FROM students s
            LEFT JOIN attendance_records ar ON s.id = ar.student_id
            LEFT JOIN attendance_sessions sess ON ar.session_id = sess.id
            INNER JOIN courses c ON sess.course_id = c.id
            WHERE s.year_level = :year_level AND sess.course_id = :course_id AND c.lecturer_id = :lecturer_id
            GROUP BY s.id, s.first_name, s.last_name
            ORDER BY s.first_name, s.last_name ASC
        ");
        $stmtAttendance->execute([
            'year_level' => $selectedClassId,
            'course_id' => $selectedCourseId,
            'lecturer_id' => $lecturer_dept['lecturer_id']
        ]);
        $attendanceRows = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);

        foreach ($attendanceRows as $row) {
            $percent = $row['total_count'] > 0 ? ($row['present_count'] / $row['total_count']) * 100 : 0;
            $attendanceData[] = [
                'student' => $row['student_name'],
                'attendance_percent' => round($percent)
            ];

            // Modal: detailed attendance - get session dates and attendance status
            $stmtDetails = $pdo->prepare("
                SELECT
                    DATE(sess.session_date) as date,
                    ar.status
                FROM attendance_sessions sess
                LEFT JOIN attendance_records ar ON sess.id = ar.session_id AND ar.student_id = :student_id
                WHERE sess.course_id = :course_id
                ORDER BY sess.session_date ASC
            ");
            $stmtDetails->execute([
                'student_id' => $row['student_id'],
                'course_id' => $selectedCourseId
            ]);
            $detailsRows = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

            foreach ($detailsRows as $dr) {
                $attendanceDetailsData[$row['student_name']][$dr['date']] = $dr['status'] ?? 'Absent';
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching attendance data: " . $e->getMessage());
        $attendanceData = [];
        $attendanceDetailsData = [];
    }
}

echo "<!-- Debug: Found " . count($attendanceData) . " attendance records -->";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Attendance Reports | Lecturer | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
</head>

<body>
    <div class="container mt-4">
        <h1 class="mb-4">Attendance Reports - Test Version</h1>

        <!-- Debug Info -->
        <div class="alert alert-info">
            <h5>Debug Information:</h5>
            <p>User ID: <?php echo $user_id; ?></p>
            <p>Lecturer ID: <?php echo $lecturer_dept['lecturer_id']; ?></p>
            <p>Department ID: <?php echo $lecturer_dept['department_id']; ?></p>
            <p>Classes Found: <?php echo count($classes); ?></p>
            <p>Selected Class: <?php echo $selectedClassId ?: 'None'; ?></p>
            <p>Selected Course: <?php echo $selectedCourseId ?: 'None'; ?></p>
            <p>Attendance Records: <?php echo count($attendanceData); ?></p>
        </div>

        <!-- Filter Section -->
        <form method="GET" class="row g-3 mb-4 align-items-end">
            <div class="col-md-4">
                <label for="class_id" class="form-label">Select Class</label>
                <select id="class_id" name="class_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($classes as $class) : ?>
                        <option value="<?= $class['id'] ?>" <?= (isset($_GET['class_id']) && $_GET['class_id'] == $class['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (!empty($courses)) : ?>
                <div class="col-md-4">
                    <label for="course_id" class="form-label">Select Course</label>
                    <select id="course_id" name="course_id" class="form-select">
                        <option value="">-- Choose Course --</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?= $course['id'] ?>" <?= (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">View Report</button>
                </div>
            <?php endif; ?>
        </form>

        <!-- Attendance Report Table -->
        <?php if (!empty($attendanceData)) : ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Student Name</th>
                            <th>Attendance %</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendanceData as $record) :
                            $statusClass = $record['attendance_percent'] >= 85 ? 'text-success' : 'text-danger';
                            $statusText = $record['attendance_percent'] >= 85 ? 'Allowed' : 'Not Allowed';
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($record['student']) ?></td>
                                <td><?= $record['attendance_percent'] ?>%</td>
                                <td class="<?= $statusClass ?> fw-bold"><?= $statusText ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (isset($_GET['course_id'])) : ?>
            <p class="text-center text-muted">No attendance data available for this course.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>