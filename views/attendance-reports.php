<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Advanced Attendance Reports | <?php echo ucfirst($user_role); ?> | RP Attendance System</title>

    <!-- External CSS Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet" />

    <!-- Custom Styles -->
    <link href="css/attendance-reports.css" rel="stylesheet" />

</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="spinner-border text-light mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>Generating report...</div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <h4><?php echo $user_role === 'admin' ? '👨‍💼 Admin' : '👨‍🏫 Lecturer'; ?></h4>
            <small>RP Attendance System</small>
        </div>

        <ul class="sidebar-nav">
            <li><a href="<?php echo $user_role === 'admin' ? 'admin-dashboard.php' : 'lecturer-dashboard.php'; ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="attendance-reports-refactored.php" class="active"><i class="fas fa-chart-bar"></i> Attendance Reports</a></li>
            <li><a href="logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="fas fa-chart-bar me-3"></i>Advanced Attendance Reports</h2>
                <p>Comprehensive attendance analytics and reporting system</p>
            </div>
            <?php if ($has_required_filters && !empty($report_data) && !isset($report_data['error'])) : ?>
            <div class="btn-group">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-file-csv me-1"></i>CSV
                </a>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" class="btn btn-info btn-sm">
                    <i class="fas fa-file-excel me-1"></i>Excel
                </a>
                <button onclick="window.print()" class="btn btn-primary btn-sm">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Report Type</label>
                        <select name="report_type" class="form-select" onchange="updateFilters(this.value)">
                            <option value="department" <?= ($filters['report_type'] == 'department') ? 'selected' : '' ?>>🏢 Department Report</option>
                            <option value="option" <?= ($filters['report_type'] == 'option') ? 'selected' : '' ?>>📚 Option/Program Report</option>
                            <option value="class" <?= ($filters['report_type'] == 'class') ? 'selected' : '' ?>>🎓 Class/Year Report</option>
                            <option value="course" <?= ($filters['report_type'] == 'course') ? 'selected' : '' ?>>📖 Course Report</option>
                        </select>
                    </div>

                    <div class="col-md-2" id="departmentFilter" style="display: <?= (in_array($filters['report_type'], ['department', 'option'])) ? 'block' : 'none' ?>">
                        <label class="form-label fw-bold">Department</label>
                        <select name="department_id" class="form-select" onchange="this.form.submit()">
                            <option value="">🏢 Select Department</option>
                            <?php foreach ($departments as $dept) : ?>
                                <option value="<?= $dept['id'] ?>" <?= ($filters['department_id'] == $dept['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2" id="optionFilter" style="display: <?= ($filters['report_type'] == 'option') ? 'block' : 'none' ?>">
                        <label class="form-label fw-bold">Program/Option</label>
                        <select name="option_id" class="form-select">
                            <option value="">📚 Select Program</option>
                            <?php foreach ($options as $opt) : ?>
                                <option value="<?= $opt['id'] ?>" <?= ($filters['option_id'] == $opt['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($opt['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2" id="classFilter" style="display: <?= (in_array($filters['report_type'], ['class', 'course'])) ? 'block' : 'none' ?>">
                        <label class="form-label fw-bold">Class/Year Level</label>
                        <select name="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="">🎓 Select Class</option>
                            <?php foreach ($classes as $class) : ?>
                                <option value="<?= $class['id'] ?>" <?= ($filters['class_id'] == $class['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($class['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3" id="courseFilter" style="display: <?= ($filters['report_type'] == 'course') ? 'block' : 'none' ?>">
                        <label class="form-label fw-bold">Course</label>
                        <select name="course_id" class="form-select">
                            <option value="">📖 Select Course</option>
                            <?php foreach ($courses as $course) : ?>
                                <option value="<?= $course['id'] ?>" <?= ($filters['course_id'] == $course['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['name']) ?> (<?= htmlspecialchars($course['course_code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>">
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-2"></i>Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($has_required_filters && !empty($report_data)) : ?>
            <?php if (isset($report_data['error'])) : ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?= htmlspecialchars($report_data['error']) ?>
                </div>
            <?php else : ?>
                <!-- Report Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h5><i class="fas fa-info-circle me-2"></i>Report Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <?php if (isset($report_data['course_info'])) : ?>
                                    <p><strong>Course:</strong> <?= htmlspecialchars($report_data['course_info']['course_name']) ?> (<?= htmlspecialchars($report_data['course_info']['course_code']) ?>)</p>
                                    <p><strong>Department:</strong> <?= htmlspecialchars($report_data['course_info']['department_name']) ?></p>
                                    <p><strong>Lecturer:</strong> <?= htmlspecialchars($report_data['course_info']['lecturer_name'] ?? 'Not Assigned') ?></p>
                                <?php elseif (isset($report_data['department_info'])) : ?>
                                    <p><strong>Department:</strong> <?= htmlspecialchars($report_data['department_info']['name']) ?></p>
                                    <p><strong>Report Type:</strong> Department-wide Attendance</p>
                                <?php elseif (isset($report_data['class_info'])) : ?>
                                    <p><strong>Class:</strong> <?= htmlspecialchars($report_data['class_info']['name']) ?></p>
                                    <p><strong>Report Type:</strong> Class-wide Attendance</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong>From:</strong> <?= $report_data['date_range']['start'] ? date('M d, Y', strtotime($report_data['date_range']['start'])) : 'All time' ?></p>
                                <p><strong>To:</strong> <?= $report_data['date_range']['end'] ? date('M d, Y', strtotime($report_data['date_range']['end'])) : 'All time' ?></p>
                                <p><strong>Total Sessions:</strong> <?= count($report_data['sessions']) ?></p>
                                <p><strong>Total Students:</strong> <?= count($report_data['students']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Dashboard -->
                <?php $summary = $report_data['summary']; ?>
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="mb-2"><i class="fas fa-users fa-2x"></i></div>
                            <div style="font-size: 2rem; font-weight: bold;"><?= $summary['total_students'] ?></div>
                            <div>Total Students</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%);">
                            <div class="mb-2"><i class="fas fa-check-circle fa-2x"></i></div>
                            <div style="font-size: 2rem; font-weight: bold;"><?= $summary['students_above_85_percent'] ?></div>
                            <div>Above 85%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                            <div class="mb-2"><i class="fas fa-exclamation-triangle fa-2x"></i></div>
                            <div style="font-size: 2rem; font-weight: bold;"><?= $summary['students_below_85_percent'] ?></div>
                            <div>Below 85%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);">
                            <div class="mb-2"><i class="fas fa-percentage fa-2x"></i></div>
                            <div style="font-size: 2rem; font-weight: bold;"><?= $summary['average_attendance_rate'] ?>%</div>
                            <div>Average Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Attendance Distribution</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Attendance Trend</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="trendChart" width="400" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Attendance Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-table me-2"></i>Student Attendance Details</h5>
                        <button class="btn btn-outline-primary btn-sm" onclick="toggleDetailedView()">
                            <i class="fas fa-eye me-1"></i>Toggle Details
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="attendanceTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Registration No</th>
                                        <th>Department</th>
                                        <th>Attendance Rate</th>
                                        <th>Status</th>
                                        <th>Present/Absent</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data['attendance'] as $student_id => $data) :
                                        $student = $data['student_info'];
                                        $summary = $data['summary'];
                                        $percentage = $summary['percentage'];

                                        $status = $percentage >= 85 ? 'Allowed to Exam' : 'Not Allowed to Exam';
                                        $statusClass = $percentage >= 85 ? 'success' : 'danger';
                                        $barColor = $percentage >= 85 ? '#10b981' : ($percentage >= 70 ? '#f59e0b' : '#ef4444');
                                    ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($student['full_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($student['reg_no'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($student['department_name'] ?? '') ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold me-2"><?= number_format($percentage, 1) ?>%</span>
                                                <div class="progress" style="width: 60px; height: 8px;">
                                                    <div class="progress-bar" style="width: <?= $percentage ?>%; background-color: <?= $barColor ?>;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-<?= $statusClass ?>"><?= $status ?></span></td>
                                        <td><span class="badge bg-success"><?= $summary['present_count'] ?></span> / <span class="badge bg-danger"><?= $summary['absent_count'] ?></span></td>
                                        <td>
                                            <button class="btn btn-outline-info btn-sm" onclick="showStudentDetails(<?= $student_id ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php elseif ($has_required_filters) : ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-info-circle fa-3x text-muted mb-3"></i>
                    <h5>No Attendance Data Found</h5>
                    <p class="text-muted">No attendance records found for the selected criteria.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user me-2"></i>Student Attendance Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetailsContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // Global variables
        const reportData = <?= json_encode($report_data ?? []) ?>;
        let detailedView = false;

        // Update filter visibility based on report type
        function updateFilters(reportType) {
            const departmentFilter = document.getElementById('departmentFilter');
            const optionFilter = document.getElementById('optionFilter');
            const classFilter = document.getElementById('classFilter');
            const courseFilter = document.getElementById('courseFilter');

            departmentFilter.style.display = 'none';
            optionFilter.style.display = 'none';
            classFilter.style.display = 'none';
            courseFilter.style.display = 'none';

            switch(reportType) {
                case 'department':
                    departmentFilter.style.display = 'block';
                    break;
                case 'option':
                    departmentFilter.style.display = 'block';
                    optionFilter.style.display = 'block';
                    break;
                case 'class':
                    classFilter.style.display = 'block';
                    break;
                case 'course':
                    classFilter.style.display = 'block';
                    courseFilter.style.display = 'block';
                    break;
            }
        }

        // Show student details
        function showStudentDetails(studentId) {
            if (!reportData.attendance || !reportData.attendance[studentId]) return;

            const student = reportData.attendance[studentId];
            const studentInfo = student.student_info;
            const sessions = student.sessions;

            let html = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6>Student Information</h6>
                        <p><strong>Name:</strong> ${studentInfo.full_name}</p>
                        <p><strong>Registration No:</strong> ${studentInfo.reg_no || 'N/A'}</p>
                        <p><strong>Department:</strong> ${studentInfo.department_name || 'N/A'}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Attendance Summary</h6>
                        <p><strong>Overall Rate:</strong> ${student.summary.percentage}%</p>
                        <p><strong>Present:</strong> ${student.summary.present_count}</p>
                        <p><strong>Absent:</strong> ${student.summary.absent_count}</p>
                    </div>
                </div>
                <h6>Session Details</h6>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>`;

            Object.values(sessions).forEach(session => {
                const sessionInfo = session.session_info;
                const status = session.status;
                const statusBadge = status === 'present'
                    ? '<span class="badge bg-success">Present</span>'
                    : '<span class="badge bg-danger">Absent</span>';

                html += `
                    <tr>
                        <td>${new Date(sessionInfo.session_date).toLocaleDateString()}</td>
                        <td>${sessionInfo.start_time} - ${sessionInfo.end_time}</td>
                        <td>${statusBadge}</td>
                    </tr>`;
            });

            html += `
                        </tbody>
                    </table>
                </div>`;

            document.getElementById('studentDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('studentDetailsModal')).show();
        }

        // Toggle detailed view
        function toggleDetailedView() {
            detailedView = !detailedView;
            // Implementation for toggling detailed view can be added here
        }

        // Initialize charts and page
        document.addEventListener('DOMContentLoaded', function() {
            const reportTypeSelect = document.querySelector('select[name="report_type"]');
            if (reportTypeSelect) {
                updateFilters(reportTypeSelect.value);
            }

            // Initialize charts if data is available
            if (reportData && reportData.summary) {
                initializeCharts();
            }
        });

        // Initialize charts
        function initializeCharts() {
            // Attendance Distribution Chart
            const attendanceChartCanvas = document.getElementById('attendanceChart');
            if (attendanceChartCanvas && reportData.summary) {
                const summary = reportData.summary;
                new Chart(attendanceChartCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Above 85%', 'Below 85%', 'Total Students'],
                        datasets: [{
                            data: [
                                summary.students_above_85_percent,
                                summary.students_below_85_percent,
                                summary.total_students
                            ],
                            backgroundColor: [
                                '#10b981',
                                '#f59e0b',
                                '#0066cc'
                            ],
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return `${label}: ${value} (${percentage}%)`;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Attendance Trend Chart (mock data for now - would need session data over time)
            const trendChartCanvas = document.getElementById('trendChart');
            if (trendChartCanvas && reportData.sessions) {
                // Group sessions by date and calculate attendance rates
                const sessionTrends = {};
                if (reportData.attendance) {
                    Object.values(reportData.attendance).forEach(studentData => {
                        if (studentData.sessions) {
                            Object.values(studentData.sessions).forEach(session => {
                                const date = session.session_info.session_date;
                                if (!sessionTrends[date]) {
                                    sessionTrends[date] = { total: 0, present: 0 };
                                }
                                sessionTrends[date].total++;
                                if (session.status === 'present') {
                                    sessionTrends[date].present++;
                                }
                            });
                        }
                    });
                }

                const dates = Object.keys(sessionTrends).sort();
                const rates = dates.map(date => {
                    const data = sessionTrends[date];
                    return data.total > 0 ? Math.round((data.present / data.total) * 100) : 0;
                });

                new Chart(trendChartCanvas, {
                    type: 'line',
                    data: {
                        labels: dates.map(date => new Date(date).toLocaleDateString()),
                        datasets: [{
                            label: 'Attendance Rate (%)',
                            data: rates,
                            borderColor: '#0066cc',
                            backgroundColor: 'rgba(0, 102, 204, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                ticks: {
                                    callback: function(value) {
                                        return value + '%';
                                    }
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `Attendance: ${context.parsed.y}%`;
                                    }
                                }
                            }
                        }
                    }
                });
            }
        }

        // Enhanced export functions
        function exportToCSV() {
            if (!reportData || !reportData.attendance) {
                alert('No data available for export');
                return;
            }

            let csv = 'Student Name,Registration No,Department,Attendance Rate,Status,Present Count,Absent Count\n';

            Object.values(reportData.attendance).forEach(data => {
                const student = data.student_info;
                const summary = data.summary;
                const percentage = summary.percentage;
                const status = percentage >= 85 ? 'Allowed to Exam' : 'Not Allowed to Exam';

                csv += `"${student.full_name}","${student.reg_no || ''}","${student.department_name || ''}",${percentage},"${status}",${summary.present_count},${summary.absent_count}\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `attendance_report_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Print functionality enhancement
        function printReport() {
            const printContent = document.querySelector('.main-content').innerHTML;
            const originalContent = document.body.innerHTML;

            document.body.innerHTML = `
                <div style="padding: 20px;">
                    <h1 style="color: #0066cc; text-align: center;">Attendance Report</h1>
                    ${printContent}
                </div>
            `;

            window.print();
            document.body.innerHTML = originalContent;
            window.location.reload(); // Reload to restore functionality
        }

        // Enhanced search functionality
        function searchStudents() {
            const searchTerm = document.getElementById('studentSearch').value.toLowerCase();
            const rows = document.querySelectorAll('#attendanceTable tbody tr');

            rows.forEach(row => {
                const studentName = row.cells[0].textContent.toLowerCase();
                const regNo = row.cells[1].textContent.toLowerCase();
                const department = row.cells[2].textContent.toLowerCase();

                const matches = studentName.includes(searchTerm) ||
                               regNo.includes(searchTerm) ||
                               department.includes(searchTerm);

                row.style.display = matches ? '' : 'none';
            });
        }

        // Add search input to table header
        document.addEventListener('DOMContentLoaded', function() {
            const tableCard = document.querySelector('.card:has(#attendanceTable)');
            if (tableCard) {
                const header = tableCard.querySelector('.card-header');
                const searchDiv = document.createElement('div');
                searchDiv.className = 'ms-3';
                searchDiv.innerHTML = `
                    <input type="text" id="studentSearch" class="form-control form-control-sm" placeholder="Search students..." onkeyup="searchStudents()">
                `;
                header.appendChild(searchDiv);
            }
        });
    </script>
</body>
</html>