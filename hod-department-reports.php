<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Department Reports | HoD | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <!-- Chart.js CDN -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f7fa;
      margin: 0;
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
    }

    .sidebar a {
      display: block;
      padding: 12px 20px;
      color: #fff;
      text-decoration: none;
    }

    .sidebar a:hover,
    .sidebar a.active {
      background-color: #0059b3;
    }

    .topbar {
      margin-left: 250px;
      background-color: #fff;
      padding: 10px 30px;
      border-bottom: 1px solid #ddd;
    }

    .main-content {
      margin-left: 250px;
      padding: 30px;
    }

    .card {
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      margin-bottom: 30px;
    }

    .btn-primary {
      background-color: #0066cc;
      border: none;
    }

    .btn-primary:hover {
      background-color: #004b99;
    }

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
    }

    @media (max-width: 768px) {

      .sidebar,
      .topbar,
      .main-content,
      .footer {
        margin-left: 0 !important;
        width: 100%;
      }

      .sidebar {
        display: none;
      }
    }
  </style>
</head>

<body>

  <!-- Sidebar -->
  <div class="sidebar">
    <div class="text-center mb-4">
      <h4>👔 Head of Department</h4>
      <hr style="border-color: #ffffff66;">
    </div>
    <a href="hod-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
    <a href="hod-department-reports.php" class="active"><i class="fas fa-chart-bar me-2"></i> Department Reports</a>
    <a href="hod-leave-management.php"><i class="fas fa-envelope-open-text me-2"></i> Manage Leave Requests</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
  </div>

  <!-- Topbar -->
  <div class="topbar d-flex justify-content-between align-items-center">
    <h5 class="m-0 fw-bold">Department Attendance Reports</h5>
    <span>HoD Panel</span>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="card p-4">
      <form id="filterForm" class="row g-3 align-items-center">
        <div class="col-md-3">
          <label for="optionSelect" class="form-label">Select Option</label>
          <select id="optionSelect" class="form-select" required>
            <option value="" selected disabled>Choose an option...</option>
            <option value="IT">IT</option>
            <option value="HWE">HWE</option>
            <option value="CS">CS</option>
          </select>
        </div>
        <div class="col-md-2">
          <label for="yearSelect" class="form-label">Year/Level</label>
          <select id="yearSelect" class="form-select" required>
            <option value="" selected disabled>Select year...</option>
            <option value="Year 1">Year 1</option>
            <option value="Year 2">Year 2</option>
            <option value="Year 3">Year 3</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="courseSelect" class="form-label">Select Course</label>
          <select id="courseSelect" class="form-select" required disabled>
            <option value="" selected disabled>Choose a course...</option>
          </select>
        </div>
        <div class="col-md-2">
          <label for="startDate" class="form-label">Start Date</label>
          <input type="date" id="startDate" class="form-control" max="" required />
        </div>
        <div class="col-md-2">
          <label for="endDate" class="form-label">End Date</label>
          <input type="date" id="endDate" class="form-control" max="" required />
        </div>
        <div class="col-md-12 d-flex justify-content-end">
          <button type="submit" class="btn btn-primary px-4">Filter</button>
        </div>
      </form>
    </div>

    <div class="card p-4">
      <h6 class="mb-3">Attendance Overview</h6>
      <canvas id="attendanceChart" height="150"></canvas>
    </div>

    <div class="card p-4">
      <h6 class="mb-3">Attendance Summary Table</h6>
      <table class="table table-bordered table-hover">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Option</th>
            <th>Course</th>
            <th>Year/Level</th>
            <th>Present</th>
            <th>Absent</th>
            <th>Attendance %</th>
          </tr>
        </thead>
        <tbody id="attendanceTableBody">
          <!-- Data rows will be dynamically inserted here -->
        </tbody>
      </table>
    </div>
  </div>

  <!-- Footer -->
  <div class="footer">
    &copy; 2025 Rwanda Polytechnic | HoD Panel
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    // Prevent future dates
    const today = new Date().toISOString().split("T")[0];
    document.getElementById("startDate").setAttribute("max", today);
    document.getElementById("endDate").setAttribute("max", today);

    // Mapping of options to courses
    const optionCourses = {
      IT: [
        { id: 'SE', name: 'Software Engineering' },
        { id: 'NW', name: 'Networking' }
      ],
      HWE: [
        { id: 'DS', name: 'Data Science' },
        { id: 'SE', name: 'Software Engineering' }
      ],
      CS: [
        { id: 'NW', name: 'Networking' },
        { id: 'DS', name: 'Data Science' }
      ]
    };

    // Dummy attendance data
    const attendanceData = {
      IT: {
        SE: [
          { date: '2025-06-01', present: 25, absent: 5 },
          { date: '2025-06-02', present: 22, absent: 8 },
          { date: '2025-06-03', present: 27, absent: 3 }
        ],
        NW: [
          { date: '2025-06-01', present: 20, absent: 10 },
          { date: '2025-06-02', present: 21, absent: 9 },
          { date: '2025-06-03', present: 19, absent: 11 }
        ]
      }
    };

    const optionSelect = document.getElementById('optionSelect');
    const yearSelect = document.getElementById('yearSelect');
    const courseSelect = document.getElementById('courseSelect');
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    let attendanceChart;

    // Populate course dropdown
    optionSelect.addEventListener('change', () => {
      const selectedOption = optionSelect.value;
      courseSelect.innerHTML = '<option value="" selected disabled>Choose a course...</option>';
      if (selectedOption && optionCourses[selectedOption]) {
        optionCourses[selectedOption].forEach(course => {
          const opt = document.createElement('option');
          opt.value = course.id;
          opt.textContent = course.name;
          courseSelect.appendChild(opt);
        });
        courseSelect.disabled = false;
      } else {
        courseSelect.disabled = true;
      }
    });

    function renderChart(optionId, courseId, yearLevel, startDate, endDate) {
      const data =
        attendanceData[optionId] && attendanceData[optionId][courseId]
          ? attendanceData[optionId][courseId]
          : [];

      const filteredData = data.filter(d => {
        if (startDate && d.date < startDate) return false;
        if (endDate && d.date > endDate) return false;
        return true;
      });

      const labels = filteredData.map(d => d.date);
      const presentData = filteredData.map(d => d.present);
      const absentData = filteredData.map(d => d.absent);

      if (attendanceChart) attendanceChart.destroy();

      attendanceChart = new Chart(ctx, {
        type: 'bar',
        data: {
          labels,
          datasets: [
            { label: 'Present', data: presentData, backgroundColor: 'rgba(54, 162, 235, 0.7)' },
            { label: 'Absent', data: absentData, backgroundColor: 'rgba(255, 99, 132, 0.7)' }
          ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
      });

      const tbody = document.getElementById('attendanceTableBody');
      tbody.innerHTML = '';
      filteredData.forEach(d => {
        const total = d.present + d.absent;
        const percent = total === 0 ? 0 : Math.round((d.present / total) * 100);
        const row = `
          <tr>
            <td>${d.date}</td>
            <td>${optionId}</td>
            <td>${courseSelect.selectedOptions[0].text}</td>
            <td>${yearLevel}</td>
            <td>${d.present}</td>
            <td>${d.absent}</td>
            <td>${percent}%</td>
          </tr>`;
        tbody.insertAdjacentHTML('beforeend', row);
      });
    }

    document.getElementById('filterForm').addEventListener('submit', e => {
      e.preventDefault();
      const optionId = optionSelect.value;
      const courseId = courseSelect.value;
      const yearLevel = yearSelect.value;
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;

      if (!optionId || !courseId || !yearLevel) {
        alert('Please select option, course, and year.');
        return;
      }
      renderChart(optionId, courseId, yearLevel, startDate, endDate);
    });
  </script>
</body>
</html>
