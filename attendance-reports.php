<?php
session_start();
require_once "config.php"; // $pdo connection
require_once "session_check.php";
require_role(['lecturer', 'admin']);

// Ensure lecturer is logged in
$lecturer_id = $_SESSION['lecturer_id'] ?? null;
if (!$lecturer_id) {
    header("Location: index.php");
    exit;
}

// Fetch classes assigned to this lecturer
$stmtClasses = $pdo->prepare("
    SELECT DISTINCT year_level
    FROM courses
    WHERE lecturer_id = :lecturer_id
    ORDER BY year_level ASC
");
$stmtClasses->execute(['lecturer_id' => $lecturer_id]);
$classRows = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);

$classes = [];
foreach ($classRows as $row) {
    $classes[] = ['id' => $row['year_level'], 'name' => $row['year_level']];
}

// Fetch courses for selected class
$selectedClassId = $_GET['class_id'] ?? null;
$selectedCourseId = $_GET['course_id'] ?? null;
$courses = [];
if ($selectedClassId) {
    $stmtCourses = $pdo->prepare("
        SELECT id, name
        FROM courses
        WHERE lecturer_id = :lecturer_id AND year_level = :year_level
        ORDER BY name ASC
    ");
    $stmtCourses->execute([
        'lecturer_id' => $lecturer_id,
        'year_level' => $selectedClassId
    ]);
    $courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch attendance data
$attendanceData = [];
$attendanceDetailsData = [];
if ($selectedClassId && $selectedCourseId) {
    // Main report
    $stmtAttendance = $pdo->prepare("
        SELECT s.id AS student_id, s.name AS student_name,
               SUM(a.status='Present') AS present_count,
               COUNT(a.id) AS total_count
        FROM students s
        LEFT JOIN attendance a ON s.id = a.student_id AND a.course_id = :course_id
        WHERE s.year_level = :year_level
        GROUP BY s.id
        ORDER BY s.name ASC
    ");
    $stmtAttendance->execute([
        'course_id' => $selectedCourseId,
        'year_level' => $selectedClassId
    ]);
    $attendanceRows = $stmtAttendance->fetchAll(PDO::FETCH_ASSOC);

    foreach ($attendanceRows as $row) {
        $percent = $row['total_count'] > 0 ? ($row['present_count'] / $row['total_count']) * 100 : 0;
        $attendanceData[] = [
            'student' => $row['student_name'],
            'attendance_percent' => round($percent)
        ];

        // Modal: detailed attendance
        $stmtDetails = $pdo->prepare("
            SELECT date, status
            FROM attendance
            WHERE student_id = :student_id AND course_id = :course_id
            ORDER BY date ASC
        ");
        $stmtDetails->execute([
            'student_id' => $row['student_id'],
            'course_id' => $selectedCourseId
        ]);
        $detailsRows = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
        foreach ($detailsRows as $dr) {
            $attendanceDetailsData[$row['student_name']][$dr['date']] = $dr['status'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Attendance Reports | Lecturer | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding-bottom: 60px;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #003366;
            color: white;
            padding-top: 20px;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #fff;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background-color: #0059b3;
        }

        .topbar {
            margin-left: 250px;
            background-color: #fff;
            padding: 12px 30px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
            max-width: calc(100% - 250px);
        }

        .main-content {
            margin-left: 250px;
            padding: 30px 30px 60px;
            max-width: calc(100% - 250px);
            overflow-x: auto;
        }

        .footer {
            text-align: center;
            margin-left: 250px;
            padding: 15px;
            font-size: 0.9rem;
            color: #666;
            background-color: #f0f0f0;
            position: fixed;
            bottom: 0;
            width: calc(100% - 250px);
            box-shadow: 0 -1px 5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .btn-group-custom {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding-bottom: 10px;
            }

            .topbar,
            .main-content,
            .footer {
                margin-left: 0;
                max-width: 100%;
                width: 100%;
                padding-left: 15px;
                padding-right: 15px;
            }

            .btn-group-custom {
                justify-content: center;
            }
        }

        .modal-xl {
            max-width: 95%;
        }

        #attendanceTableAll {
            min-width: 1000px;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" tabindex="0">
        <div class="text-center mb-4">
            <h4>👨‍🏫 Lecturer</h4>
            <hr style="border-color: #ffffff66;" />
        </div>
        <a href="lecturer-dashboard.php">Dashboard</a>
        <a href="lecturer-my-courses.php">My Courses</a>
        <a href="attendance-session.php">Attendance Session</a>
        <a href="attendance-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Attendance Reports</a>
        <a href="leave-requests.php">Leave Requests</a>
        <a href="index.php">Logout</a>
    </div>

    <!-- Topbar -->
    <div class="topbar">
        <h5 class="m-0 fw-bold">Attendance Reports</h5>
        <span>RP Attendance System</span>
    </div>

    <!-- Main Content -->
    <div class="main-content container-fluid">
        <!-- Buttons -->
        <div class="btn-group-custom">
            <button id="printReport" class="btn btn-outline-primary">
                <i class="fas fa-print me-2"></i> Print Report
            </button>
            <button id="viewAllAttendanceBtn" class="btn btn-info">
                <i class="fas fa-list me-2"></i> View All Attendance Details
            </button>
        </div>

        <!-- Filter Section -->
        <form id="filterForm" method="GET" class="row g-3 mb-4 align-items-end">
            <div class="col-md-4">
                <label for="class_id" class="form-label">Select Class</label>
                <select id="class_id" name="class_id" class="form-select" onchange="this.form.submit()" required>
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
                    <select id="course_id" name="course_id" class="form-select" required>
                        <option value="">-- Choose Course --</option>
                        <?php foreach ($courses as $course) : ?>
                            <option value="<?= $course['id'] ?>" <?= (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex justify-content-md-start justify-content-center">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto">View Report</button>
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

    <!-- Footer -->
    <div class="footer">
        &copy; <?= date('Y') ?> Rwanda Polytechnic | Lecturer Panel
    </div>

    <!-- Modal: All Attendance Details -->
    <div class="modal fade" id="attendanceDetailsModal" tabindex="-1" aria-labelledby="attendanceDetailsLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl" style="max-height: 90vh; overflow-y: auto;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="attendanceDetailsLabel">All Students Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="allAttendanceDetailsBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="printAllDetailsBtn">
                        <i class="fas fa-print me-2"></i> Print Details
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
    <script>
        const attendanceDetailsData = <?= json_encode($attendanceDetailsData); ?>;

        function getAllDates(data) {
            const datesSet = new Set();
            for (const student in data) {
                Object.keys(data[student]).forEach(date => datesSet.add(date));
            }
            return Array.from(datesSet).sort();
        }

        function calculateAttendancePercent(attendanceObj) {
            const total = Object.keys(attendanceObj).length;
            const presentCount = Object.values(attendanceObj).filter(status => status === 'Present').length;
            return total === 0 ? 0 : (presentCount / total) * 100;
        }

        const modal = new bootstrap.Modal(document.getElementById('attendanceDetailsModal'));
        const modalBody = document.getElementById('allAttendanceDetailsBody');

        document.getElementById('viewAllAttendanceBtn').addEventListener('click', () => {
            modalBody.innerHTML = '';
            const allDates = getAllDates(attendanceDetailsData);

            const table = document.createElement('table');
            table.id = "attendanceTableAll";
            table.className = 'table table-bordered table-hover table-sm';

            const thead = document.createElement('thead');
            thead.innerHTML = `<tr><th>Student Name</th><th>Decision</th>${allDates.map(date => `<th>${date}</th>`).join('')}</tr>`;
            table.appendChild(thead);

            const tbody = document.createElement('tbody');
            for (const student in attendanceDetailsData) {
                const attendance = attendanceDetailsData[student];
                const percent = calculateAttendancePercent(attendance);

                let row = `<td>${student}</td>`;
                row += percent < 85 ?
                    `<td><span class="badge bg-danger">Not Allowed to Do Exam</span></td>` :
                    `<td><span class="badge bg-success">Allowed</span></td>`;

                allDates.forEach(date => {
                    const status = attendance[date];
                    row += status === 'Present' ?
                        `<td><span class="badge bg-success">Present</span></td>` :
                        status === 'Absent' ?
                        `<td><span class="badge bg-danger">Absent</span></td>` :
                        `<td><span class="text-muted">-</span></td>`;
                });

                const tr = document.createElement('tr');
                tr.innerHTML = row;
                tbody.appendChild(tr);
            }

            table.appendChild(tbody);
            modalBody.appendChild(table);
            modal.show();
        });

        document.getElementById('printReport').addEventListener('click', () => window.print());

        document.getElementById('printAllDetailsBtn').addEventListener('click', () => {
            const printContents = document.getElementById('allAttendanceDetailsBody').innerHTML;
            const printWindow = window.open('', '', 'width=900,height=600');
            printWindow.document.write(`
                <html>
                  <head>
                    <title>Print Attendance Details</title>
                    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
                  </head>
                  <body>
                    <h3 class="text-center mb-4">All Students Attendance Details</h3>
                    ${printContents}
                  </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();

            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 300);
        });
    </script>
</body>
</html>
