<?php
/**
 * Attendance Reports API
 * Provides comprehensive attendance data for lecturers
 * Supports multiple report types: course, class, department, student
 */

session_start();
require_once "../config.php";
require_once "../session_check.php";
require_role(['lecturer', 'hod', 'admin']);

header('Content-Type: application/json');

try {
    // Get request parameters
    $report_type = $_GET['report_type'] ?? 'course';
    $course_id = $_GET['course_id'] ?? null;
    $department_id = $_GET['department_id'] ?? null;
    $option_id = $_GET['option_id'] ?? null;
    $year_level = $_GET['year_level'] ?? null;
    $start_date = $_GET['start_date'] ?? null;
    $end_date = $_GET['end_date'] ?? null;
    $lecturer_id = $_SESSION['lecturer_id'] ?? null;
    
    // Validate lecturer
    if (!$lecturer_id) {
        throw new Exception('Lecturer ID not found in session');
    }
    
    $response = [];
    
    switch ($report_type) {
        case 'course':
            $response = getCourseReport($pdo, $course_id, $start_date, $end_date, $lecturer_id);
            break;
            
        case 'class':
            // If course_id is provided, extract option_id and year_level from it
            if ($course_id && !$option_id && !$year_level) {
                $course_info = $pdo->prepare("SELECT option_id, year FROM courses WHERE id = ?");
                $course_info->execute([$course_id]);
                $course_data = $course_info->fetch(PDO::FETCH_ASSOC);
                if ($course_data) {
                    $option_id = $course_data['option_id'];
                    $year_level = $course_data['year'];
                }
            }
            $response = getClassReport($pdo, $option_id, $year_level, $start_date, $end_date, $lecturer_id);
            break;
            
        case 'department':
            $response = getDepartmentReport($pdo, $department_id, $start_date, $end_date, $lecturer_id);
            break;
            
        case 'student':
            $student_id = $_GET['student_id'] ?? null;
            $response = getStudentReport($pdo, $student_id, $course_id, $start_date, $end_date);
            break;
            
        case 'summary':
            $response = getLecturerSummary($pdo, $lecturer_id, $start_date, $end_date);
            break;
            
        default:
            throw new Exception('Invalid report type');
    }
    
    // Add report type to response
    $response['type'] = $report_type;
    
    echo json_encode([
        'status' => 'success',
        'data' => $response
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Get course attendance report
 */
function getCourseReport($pdo, $course_id, $start_date, $end_date, $lecturer_id) {
    if (!$course_id) {
        throw new Exception('Course ID is required');
    }
    
    // Verify lecturer teaches this course
    $verify_stmt = $pdo->prepare("
        SELECT c.id, c.course_name, c.course_code, o.name as option_name, c.year
        FROM courses c
        LEFT JOIN options o ON c.option_id = o.id
        WHERE c.id = ? AND c.lecturer_id = ?
    ");
    $verify_stmt->execute([$course_id, $lecturer_id]);
    $course = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        throw new Exception('Course not found or you do not have access');
    }
    
    // Build date filter
    $date_filter = "";
    $params = [$course_id];
    
    if ($start_date && $end_date) {
        $date_filter = "AND DATE(ats.session_date) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    // Get all sessions for this course
    $sessions_stmt = $pdo->prepare("
        SELECT 
            ats.id as session_id,
            ats.session_date,
            ats.biometric_method,
            ats.status as session_status,
            COUNT(DISTINCT ar.id) as total_marked,
            COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN ar.id END) as present_count
        FROM attendance_sessions ats
        LEFT JOIN attendance_records ar ON ats.id = ar.session_id
        WHERE ats.course_id = ? $date_filter
        GROUP BY ats.id
        ORDER BY ats.session_date DESC
    ");
    $sessions_stmt->execute($params);
    $sessions = $sessions_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get students enrolled in this course (via option and year)
    $students_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.user_id,
            s.reg_no,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            u.email,
            s.year_level,
            s.status,
            o.name as option_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN courses c ON s.option_id = c.option_id 
                      AND CAST(s.year_level AS UNSIGNED) = c.year
        LEFT JOIN options o ON s.option_id = o.id
        WHERE c.id = ? AND s.status = 'active'
        ORDER BY s.reg_no
    ");
    $students_stmt->execute([$course_id]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate attendance for each student
    $total_sessions = count($sessions);
    
    foreach ($students as &$student) {
        // Get attendance records for this student
        $attendance_stmt = $pdo->prepare("
            SELECT 
                ar.id,
                ar.session_id,
                ar.status,
                ar.recorded_at,
                ats.session_date
            FROM attendance_records ar
            JOIN attendance_sessions ats ON ar.session_id = ats.id
            WHERE ar.student_id = ? 
            AND ats.course_id = ? 
            $date_filter
            ORDER BY ats.session_date DESC
        ");
        
        $attendance_params = [$student['id'], $course_id];
        if ($start_date && $end_date) {
            $attendance_params[] = $start_date;
            $attendance_params[] = $end_date;
        }
        
        $attendance_stmt->execute($attendance_params);
        $attendance_records = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $present_count = count(array_filter($attendance_records, fn($r) => $r['status'] === 'present'));
        $attendance_rate = $total_sessions > 0 ? round(($present_count / $total_sessions) * 100, 2) : 0;
        
        $student['total_sessions'] = $total_sessions;
        $student['present_count'] = $present_count;
        $student['absent_count'] = $total_sessions - $present_count;
        $student['attendance_rate'] = $attendance_rate;
        $student['attendance_records'] = $attendance_records;
        
        // Determine status
        if ($attendance_rate >= 85) {
            $student['attendance_status'] = 'excellent';
        } elseif ($attendance_rate >= 75) {
            $student['attendance_status'] = 'good';
        } elseif ($attendance_rate >= 60) {
            $student['attendance_status'] = 'average';
        } else {
            $student['attendance_status'] = 'poor';
        }
    }
    
    // Calculate summary statistics
    $total_students = count($students);
    $avg_attendance = $total_students > 0 ? 
        round(array_sum(array_column($students, 'attendance_rate')) / $total_students, 2) : 0;
    
    $students_above_85 = count(array_filter($students, fn($s) => $s['attendance_rate'] >= 85));
    $students_75_to_85 = count(array_filter($students, fn($s) => $s['attendance_rate'] >= 75 && $s['attendance_rate'] < 85));
    $students_below_75 = count(array_filter($students, fn($s) => $s['attendance_rate'] < 75));
    
    return [
        'course' => $course,
        'sessions' => $sessions,
        'students' => $students,
        'summary' => [
            'total_students' => $total_students,
            'total_sessions' => $total_sessions,
            'average_attendance' => $avg_attendance,
            'students_above_85' => $students_above_85,
            'students_75_to_85' => $students_75_to_85,
            'students_below_75' => $students_below_75,
            'excellent_count' => count(array_filter($students, fn($s) => $s['attendance_status'] === 'excellent')),
            'good_count' => count(array_filter($students, fn($s) => $s['attendance_status'] === 'good')),
            'average_count' => count(array_filter($students, fn($s) => $s['attendance_status'] === 'average')),
            'poor_count' => count(array_filter($students, fn($s) => $s['attendance_status'] === 'poor'))
        ],
        'date_range' => [
            'start' => $start_date,
            'end' => $end_date
        ]
    ];
}

/**
 * Get class/option attendance report
 */
function getClassReport($pdo, $option_id, $year_level, $start_date, $end_date, $lecturer_id) {
    if (!$option_id || !$year_level) {
        throw new Exception('Option ID and year level are required');
    }
    
    // Get option details
    $option_stmt = $pdo->prepare("
        SELECT o.id, o.name, o.department_id, d.name as department_name
        FROM options o
        JOIN departments d ON o.department_id = d.id
        WHERE o.id = ?
    ");
    $option_stmt->execute([$option_id]);
    $option = $option_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$option) {
        throw new Exception('Option not found');
    }
    
    // Get all courses for this class taught by this lecturer
    $courses_stmt = $pdo->prepare("
        SELECT id, course_name, course_code
        FROM courses
        WHERE option_id = ? AND year = ? AND lecturer_id = ?
    ");
    $courses_stmt->execute([$option_id, $year_level, $lecturer_id]);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all students in this class
    $students_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.reg_no,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            u.email,
            s.status,
            o.name as option_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN options o ON s.option_id = o.id
        WHERE s.option_id = ? AND s.year_level = ? AND s.status = 'active'
        ORDER BY s.reg_no
    ");
    $students_stmt->execute([$option_id, $year_level]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate attendance for each student across all courses
    foreach ($students as &$student) {
        $total_sessions = 0;
        $present_count = 0;
        
        foreach ($courses as $course) {
            // Count sessions for this course
            $session_count_stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM attendance_sessions
                WHERE course_id = ?
                " . ($start_date && $end_date ? "AND DATE(session_date) BETWEEN ? AND ?" : "")
            );
            
            $params = [$course['id']];
            if ($start_date && $end_date) {
                $params[] = $start_date;
                $params[] = $end_date;
            }
            
            $session_count_stmt->execute($params);
            $session_count = $session_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            $total_sessions += $session_count;
            
            // Count present records
            $present_stmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM attendance_records ar
                JOIN attendance_sessions ats ON ar.session_id = ats.id
                WHERE ar.student_id = ? AND ats.course_id = ? AND ar.status = 'present'
                " . ($start_date && $end_date ? "AND DATE(ats.session_date) BETWEEN ? AND ?" : "")
            );
            
            $present_params = [$student['id'], $course['id']];
            if ($start_date && $end_date) {
                $present_params[] = $start_date;
                $present_params[] = $end_date;
            }
            
            $present_stmt->execute($present_params);
            $present_count += $present_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }
        
        $student['total_sessions'] = $total_sessions;
        $student['present_count'] = $present_count;
        $student['absent_count'] = $total_sessions - $present_count;
        $attendance_rate = $total_sessions > 0 ? 
            round(($present_count / $total_sessions) * 100, 2) : 0;
        $student['attendance_rate'] = $attendance_rate;
        $student['year_level'] = $year_level;
        
        // Determine status
        if ($attendance_rate >= 85) {
            $student['attendance_status'] = 'excellent';
        } elseif ($attendance_rate >= 75) {
            $student['attendance_status'] = 'good';
        } elseif ($attendance_rate >= 60) {
            $student['attendance_status'] = 'average';
        } else {
            $student['attendance_status'] = 'poor';
        }
    }
    
    // Calculate summary
    $total_students = count($students);
    $avg_attendance = $total_students > 0 ? 
        round(array_sum(array_column($students, 'attendance_rate')) / $total_students, 2) : 0;
    
    return [
        'option' => $option,
        'year_level' => $year_level,
        'courses' => $courses,
        'students' => $students,
        'summary' => [
            'total_students' => $total_students,
            'total_courses' => count($courses),
            'average_attendance' => $avg_attendance,
            'students_above_85' => count(array_filter($students, fn($s) => $s['attendance_rate'] >= 85)),
            'students_below_75' => count(array_filter($students, fn($s) => $s['attendance_rate'] < 75))
        ]
    ];
}

/**
 * Get department attendance report
 */
function getDepartmentReport($pdo, $department_id, $start_date, $end_date, $lecturer_id) {
    if (!$department_id) {
        throw new Exception('Department ID is required');
    }
    
    // Get department details
    $dept_stmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
    $dept_stmt->execute([$department_id]);
    $department = $dept_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        throw new Exception('Department not found');
    }
    
    // Get all courses in this department taught by this lecturer
    $courses_stmt = $pdo->prepare("
        SELECT c.id, c.course_name, c.course_code, o.name as option_name, c.year
        FROM courses c
        LEFT JOIN options o ON c.option_id = o.id
        WHERE c.department_id = ? AND c.lecturer_id = ?
    ");
    $courses_stmt->execute([$department_id, $lecturer_id]);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics for each course
    foreach ($courses as &$course) {
        $stats = getCourseReport($pdo, $course['id'], $start_date, $end_date, $lecturer_id);
        $course['summary'] = $stats['summary'];
    }
    
    // Calculate overall department statistics
    $total_students = array_sum(array_column($courses, 'summary')['total_students'] ?? [0]);
    $avg_attendance = count($courses) > 0 ?
        round(array_sum(array_column(array_column($courses, 'summary'), 'average_attendance')) / count($courses), 2) : 0;
    
    return [
        'department' => $department,
        'courses' => $courses,
        'summary' => [
            'total_courses' => count($courses),
            'total_students' => $total_students,
            'average_attendance' => $avg_attendance
        ]
    ];
}

/**
 * Get individual student attendance report
 */
function getStudentReport($pdo, $student_id, $course_id, $start_date, $end_date) {
    if (!$student_id) {
        throw new Exception('Student ID is required');
    }
    
    // Get student details
    $student_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.reg_no,
            s.student_id_number,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            u.email,
            s.year_level,
            o.name as option_name,
            d.name as department_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN options o ON s.option_id = o.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE s.id = ?
    ");
    $student_stmt->execute([$student_id]);
    $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        throw new Exception('Student not found');
    }
    
    // Build query for attendance records
    $date_filter = "";
    $params = [$student_id];
    
    if ($course_id) {
        $date_filter .= " AND ats.course_id = ?";
        $params[] = $course_id;
    }
    
    if ($start_date && $end_date) {
        $date_filter .= " AND DATE(ats.session_date) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    }
    
    // Get attendance records
    $records_stmt = $pdo->prepare("
        SELECT 
            ar.id,
            ar.status,
            ar.recorded_at,
            ats.session_date,
            ats.biometric_method,
            c.course_name,
            c.course_code
        FROM attendance_records ar
        JOIN attendance_sessions ats ON ar.session_id = ats.id
        JOIN courses c ON ats.course_id = c.id
        WHERE ar.student_id = ? $date_filter
        ORDER BY ats.session_date DESC
    ");
    $records_stmt->execute($params);
    $records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate statistics
    $total_sessions = count($records);
    $present_count = count(array_filter($records, fn($r) => $r['status'] === 'present'));
    $attendance_rate = $total_sessions > 0 ? round(($present_count / $total_sessions) * 100, 2) : 0;
    
    return [
        'student' => $student,
        'records' => $records,
        'summary' => [
            'total_sessions' => $total_sessions,
            'present_count' => $present_count,
            'absent_count' => $total_sessions - $present_count,
            'attendance_rate' => $attendance_rate
        ]
    ];
}

/**
 * Get lecturer summary dashboard
 */
function getLecturerSummary($pdo, $lecturer_id, $start_date, $end_date) {
    // Debug: Log the lecturer_id being used
    error_log("getLecturerSummary called with lecturer_id: " . $lecturer_id);
    
    // Get all courses taught by this lecturer with full details
    $courses_stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.course_name,
            c.course_code,
            c.year,
            o.name as option_name,
            d.name as department_name
        FROM courses c
        LEFT JOIN options o ON c.option_id = o.id
        LEFT JOIN departments d ON c.department_id = d.id
        WHERE c.lecturer_id = ? AND c.status = 'active'
        ORDER BY c.year, c.course_code
    ");
    $courses_stmt->execute([$lecturer_id]);
    $courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log course count
    error_log("Found " . count($courses) . " courses for lecturer " . $lecturer_id);
    
    // If no courses found, provide helpful error message
    if (count($courses) === 0) {
        throw new Exception("No courses assigned to lecturer ID: $lecturer_id. Please contact admin to assign courses.");
    }
    
    $total_courses = count($courses);
    $total_sessions = 0;
    $total_students = 0;
    $overall_attendance = [];
    
    // Get statistics for each course
    foreach ($courses as &$course) {
        // Build date filter
        $date_filter = "";
        $params = [$course['id']];
        
        if ($start_date && $end_date) {
            $date_filter = "AND DATE(ats.session_date) BETWEEN ? AND ?";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        // Count total sessions
        $session_stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM attendance_sessions ats
            WHERE ats.course_id = ? $date_filter
        ");
        $session_stmt->execute($params);
        $session_count = $session_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get total students enrolled in this course (based on option and year)
        // First, get the course's option_id and year
        $course_info_stmt = $pdo->prepare("SELECT option_id, year FROM courses WHERE id = ?");
        $course_info_stmt->execute([$course['id']]);
        $course_info = $course_info_stmt->fetch(PDO::FETCH_ASSOC);

        if ($course_info && $course_info['option_id'] && $course_info['year']) {
            // Count students enrolled in the same option and year as this course
            $student_stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT s.id) as count
                FROM students s
                WHERE s.option_id = ? AND CAST(s.year_level AS UNSIGNED) = ? AND s.status = 'active'
            ");
            $student_stmt->execute([$course_info['option_id'], $course_info['year']]);
            $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Debug logging
            error_log("Course {$course['id']} ({$course['course_code']}): option_id={$course_info['option_id']}, year={$course_info['year']}, students found=$student_count");
        } else {
            // If course doesn't have option or year, count students who have attendance records
            $student_stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT ar.student_id) as count
                FROM attendance_records ar
                JOIN attendance_sessions ats ON ar.session_id = ats.id
                WHERE ats.course_id = ? $date_filter
            ");
            $student_stmt->execute($params);
            $student_count = $student_stmt->fetch(PDO::FETCH_ASSOC)['count'];

            error_log("Course {$course['id']} ({$course['course_code']}): no option/year info, students from attendance records=$student_count");
        }
        
        // Calculate attendance rate
        $attendance_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count
            FROM attendance_records ar
            JOIN attendance_sessions ats ON ar.session_id = ats.id
            WHERE ats.course_id = ? $date_filter
        ");
        $attendance_stmt->execute($params);
        $attendance_data = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
        
        $total_records = $attendance_data['total_records'];
        $present_count = $attendance_data['present_count'];
        $avg_attendance = $total_records > 0 ? 
            round(($present_count / $total_records) * 100, 2) : 0;
        
        // Add summary to course
        $course['summary'] = [
            'total_students' => $student_count,
            'total_sessions' => $session_count,
            'average_attendance' => $avg_attendance,
            'students_above_85' => 0 // Will be calculated if needed
        ];
        
        $total_sessions += $session_count;
        $total_students += $student_count;
        if ($avg_attendance > 0) {
            $overall_attendance[] = $avg_attendance;
        }
    }
    
    $avg_attendance = count($overall_attendance) > 0 ?
        round(array_sum($overall_attendance) / count($overall_attendance), 2) : 0;
    
    return [
        'courses' => $courses,
        'summary' => [
            'total_courses' => $total_courses,
            'total_sessions' => $total_sessions,
            'total_students' => $total_students,
            'average_attendance' => $avg_attendance
        ],
        'date_range' => [
            'start' => $start_date,
            'end' => $end_date
        ]
    ];
}
?>
