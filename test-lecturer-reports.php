<?php
session_start();
require_once 'config.php';

// Check if user is logged in as lecturer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lecturer') {
    die("Please login as a lecturer first. <a href='login.php'>Login</a>");
}

// Use the global $pdo from config.php
global $pdo;

// Get lecturer info
$lecturer_stmt = $pdo->prepare("
    SELECT 
        l.id as lecturer_id,
        l.user_id,
        l.department_id,
        u.first_name,
        u.last_name,
        u.email,
        d.name as department_name
    FROM lecturers l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN departments d ON l.department_id = d.id
    WHERE l.user_id = ?
");
$lecturer_stmt->execute([$_SESSION['user_id']]);
$lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);

if (!$lecturer) {
    die("Lecturer record not found for user_id: " . $_SESSION['user_id']);
}

$lecturer_id = $lecturer['lecturer_id'];

// Test 1: Get courses assigned to lecturer
$courses_stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.course_code,
        c.course_name,
        c.option_id,
        o.name as option_name,
        c.year,
        c.lecturer_id,
        c.department_id,
        c.status
    FROM courses c
    LEFT JOIN options o ON c.option_id = o.id
    WHERE c.lecturer_id = ? AND c.status = 'active'
    ORDER BY c.year, c.course_code
");
$courses_stmt->execute([$lecturer_id]);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Test 2: For each course, count students
$course_details = [];
foreach ($courses as $course) {
    // Get students for this course
    $student_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id) as count
        FROM students s
        WHERE s.option_id = ? 
          AND CAST(s.year_level AS UNSIGNED) = ? 
          AND s.status = 'active'
    ");
    $student_stmt->execute([$course['option_id'], $course['year']]);
    $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get sessions for this course
    $session_stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM attendance_sessions
        WHERE course_id = ?
    ");
    $session_stmt->execute([$course['id']]);
    $session_count = $session_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get attendance records
    $attendance_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count
        FROM attendance_records ar
        JOIN attendance_sessions ats ON ar.session_id = ats.id
        WHERE ats.course_id = ?
    ");
    $attendance_stmt->execute([$course['id']]);
    $attendance_data = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
    
    $course_details[] = [
        'course' => $course,
        'student_count' => $student_count,
        'session_count' => $session_count,
        'total_records' => $attendance_data['total_records'],
        'present_count' => $attendance_data['present_count']
    ];
}

// Test 3: Get all students in lecturer's department
$all_students_stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.reg_no,
        s.option_id,
        o.name as option_name,
        s.year_level,
        CAST(s.year_level AS UNSIGNED) as year_int,
        s.status,
        CONCAT(u.first_name, ' ', u.last_name) as student_name
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN options o ON s.option_id = o.id
    WHERE s.department_id = ? AND s.status = 'active'
    ORDER BY s.option_id, s.year_level, s.reg_no
");
$all_students_stmt->execute([$lecturer['department_id']]);
$all_students = $all_students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Test 4: Get all options in department
$options_stmt = $pdo->prepare("
    SELECT id, name, status
    FROM options
    WHERE department_id = ? AND status = 'active'
");
$options_stmt->execute([$lecturer['department_id']]);
$options = $options_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Reports Test Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .test-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-section h3 {
            color: #0066cc;
            border-bottom: 2px solid #0066cc;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
        .status-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #0066cc;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
        }
        .api-test-btn {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h1><i class="fas fa-vial me-2"></i>Lecturer Attendance Reports - Test Page</h1>
                <p class="text-muted">Diagnostic tool to verify data and configuration</p>
                <a href="lecturer-attendance-reports.php" class="btn btn-primary">
                    <i class="fas fa-chart-line me-2"></i>Go to Reports Page
                </a>
            </div>
        </div>

        <!-- Test 1: Session & Lecturer Info -->
        <div class="test-section">
            <h3><i class="fas fa-user-check me-2"></i>Test 1: Session & Lecturer Information</h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="info-card">
                        <h5>Session Data</h5>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>User ID:</strong></td>
                                <td><?php echo $_SESSION['user_id']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Role:</strong></td>
                                <td><?php echo $_SESSION['role']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Lecturer ID:</strong></td>
                                <td><?php echo $_SESSION['lecturer_id'] ?? '<span class="text-danger">NOT SET</span>'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-card">
                        <h5>Lecturer Database Record</h5>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Lecturer ID:</strong></td>
                                <td><?php echo $lecturer['lecturer_id']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Name:</strong></td>
                                <td><?php echo $lecturer['first_name'] . ' ' . $lecturer['last_name']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Email:</strong></td>
                                <td><?php echo $lecturer['email']; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Department:</strong></td>
                                <td><?php echo $lecturer['department_name'] ?? 'N/A'; ?> (ID: <?php echo $lecturer['department_id']; ?>)</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <?php if (!isset($_SESSION['lecturer_id']) || $_SESSION['lecturer_id'] != $lecturer['lecturer_id']): ?>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Session lecturer_id doesn't match database! 
                    Session: <?php echo $_SESSION['lecturer_id'] ?? 'NOT SET'; ?>, 
                    Database: <?php echo $lecturer['lecturer_id']; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-success mt-3">
                    <i class="fas fa-check-circle me-2"></i>
                    Session data is correct!
                </div>
            <?php endif; ?>
        </div>

        <!-- Test 2: Courses Assigned -->
        <div class="test-section">
            <h3><i class="fas fa-book me-2"></i>Test 2: Courses Assigned to Lecturer</h3>
            
            <?php if (count($courses) === 0): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-times-circle me-2"></i>
                    <strong>ERROR:</strong> No courses assigned to lecturer ID <?php echo $lecturer_id; ?>
                    <hr>
                    <strong>Fix:</strong> Run this SQL command:
                    <pre>UPDATE courses SET lecturer_id = <?php echo $lecturer_id; ?> WHERE department_id = <?php echo $lecturer['department_id']; ?> AND lecturer_id IS NULL;</pre>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Found <strong><?php echo count($courses); ?></strong> courses assigned to you
                </div>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Code</th>
                                <th>Course Name</th>
                                <th>Option</th>
                                <th>Year</th>
                                <th>Students</th>
                                <th>Sessions</th>
                                <th>Records</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($course_details as $detail): ?>
                                <?php 
                                $course = $detail['course'];
                                $status_class = 'status-danger';
                                if ($detail['student_count'] > 0 && $detail['session_count'] > 0) {
                                    $status_class = 'status-success';
                                } elseif ($detail['student_count'] > 0 || $detail['session_count'] > 0) {
                                    $status_class = 'status-warning';
                                }
                                ?>
                                <tr>
                                    <td><?php echo $course['id']; ?></td>
                                    <td><strong><?php echo $course['course_code']; ?></strong></td>
                                    <td><?php echo $course['course_name']; ?></td>
                                    <td>
                                        <?php if ($course['option_id']): ?>
                                            <?php echo $course['option_name'] ?? 'Unknown'; ?> (ID: <?php echo $course['option_id']; ?>)
                                        <?php else: ?>
                                            <span class="text-danger">NULL</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>Year <?php echo $course['year']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $detail['student_count'] > 0 ? 'success' : 'danger'; ?>">
                                            <?php echo $detail['student_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $detail['session_count'] > 0 ? 'success' : 'warning'; ?>">
                                            <?php echo $detail['session_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $detail['total_records']; ?> 
                                        (<?php echo $detail['present_count']; ?> present)
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php 
                                            if ($detail['student_count'] > 0 && $detail['session_count'] > 0) {
                                                echo 'READY';
                                            } elseif ($detail['student_count'] > 0) {
                                                echo 'NO SESSIONS';
                                            } elseif ($detail['session_count'] > 0) {
                                                echo 'NO STUDENTS';
                                            } else {
                                                echo 'EMPTY';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Test 3: Students in Department -->
        <div class="test-section">
            <h3><i class="fas fa-users me-2"></i>Test 3: Students in Your Department</h3>
            
            <?php if (count($all_students) === 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No active students found in department ID <?php echo $lecturer['department_id']; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Found <strong><?php echo count($all_students); ?></strong> active students in your department
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Reg No</th>
                                <th>Name</th>
                                <th>Option</th>
                                <th>Year Level</th>
                                <th>Year (INT)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_students as $student): ?>
                                <tr>
                                    <td><?php echo $student['id']; ?></td>
                                    <td><?php echo $student['reg_no']; ?></td>
                                    <td><?php echo $student['student_name']; ?></td>
                                    <td>
                                        <?php echo $student['option_name'] ?? 'N/A'; ?> 
                                        (ID: <?php echo $student['option_id']; ?>)
                                    </td>
                                    <td><?php echo $student['year_level']; ?></td>
                                    <td><?php echo $student['year_int']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Test 4: Options in Department -->
        <div class="test-section">
            <h3><i class="fas fa-list me-2"></i>Test 4: Options/Programs in Department</h3>
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>Option ID</th>
                            <th>Option Name</th>
                            <th>Status</th>
                            <th>Students</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($options as $option): ?>
                            <?php
                            $opt_students = array_filter($all_students, function($s) use ($option) {
                                return $s['option_id'] == $option['id'];
                            });
                            ?>
                            <tr>
                                <td><?php echo $option['id']; ?></td>
                                <td><?php echo $option['name']; ?></td>
                                <td><?php echo $option['status']; ?></td>
                                <td><?php echo count($opt_students); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Test 5: API Test -->
        <div class="test-section">
            <h3><i class="fas fa-plug me-2"></i>Test 5: API Endpoint Test</h3>
            
            <button class="btn btn-primary api-test-btn" onclick="testAPI()">
                <i class="fas fa-play me-2"></i>Test API: Get Lecturer Summary
            </button>
            
            <div id="apiResult" class="mt-3" style="display:none;">
                <h5>API Response:</h5>
                <pre id="apiOutput"></pre>
            </div>
        </div>

        <!-- Test 6: Recommendations -->
        <div class="test-section">
            <h3><i class="fas fa-tools me-2"></i>Test 6: Recommendations</h3>
            
            <?php
            $issues = [];
            $fixes = [];
            
            // Check for courses without option_id
            $courses_no_option = array_filter($courses, function($c) { return !$c['option_id']; });
            if (count($courses_no_option) > 0) {
                $issues[] = count($courses_no_option) . " courses missing option_id";
                $fixes[] = "UPDATE courses SET option_id = 17 WHERE department_id = {$lecturer['department_id']} AND option_id IS NULL;";
            }
            
            // Check for courses with 0 students
            $courses_no_students = array_filter($course_details, function($c) { return $c['student_count'] == 0; });
            if (count($courses_no_students) > 0) {
                $issues[] = count($courses_no_students) . " courses have 0 students enrolled";
                $fixes[] = "Check if students exist for the option_id and year of these courses";
            }
            
            if (count($issues) === 0): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>All checks passed!</strong> Your setup looks good.
                </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Issues Found:</strong>
                    <ul class="mb-0">
                        <?php foreach ($issues as $issue): ?>
                            <li><?php echo $issue; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="alert alert-info">
                    <strong>Recommended Fixes:</strong>
                    <?php foreach ($fixes as $fix): ?>
                        <pre><?php echo $fix; ?></pre>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testAPI() {
            const btn = $('.api-test-btn');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Testing...');
            
            $.ajax({
                url: 'api/get-attendance-reports.php',
                method: 'GET',
                data: {
                    report_type: 'lecturer_summary',
                    start_date: '2025-01-01',
                    end_date: '2025-12-31'
                },
                success: function(response) {
                    $('#apiResult').show();
                    $('#apiOutput').text(JSON.stringify(response, null, 2));
                    btn.prop('disabled', false).html('<i class="fas fa-check me-2"></i>Test Successful');
                    btn.removeClass('btn-primary').addClass('btn-success');
                },
                error: function(xhr, status, error) {
                    $('#apiResult').show();
                    $('#apiOutput').text('ERROR: ' + xhr.responseText);
                    btn.prop('disabled', false).html('<i class="fas fa-times me-2"></i>Test Failed');
                    btn.removeClass('btn-primary').addClass('btn-danger');
                }
            });
        }
    </script>
</body>
</html>
