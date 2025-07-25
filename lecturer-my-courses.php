<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Courses | Lecturer | RP Attendance System</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f5f7fa;
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

    .footer {
      text-align: center;
      margin-left: 250px;
      padding: 15px;
      font-size: 0.9rem;
      color: #666;
      background-color: #f0f0f0;
    }

    .card {
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
      transition: 0.3s ease;
    }

    .card:hover {
      transform: translateY(-2px);
    }

    .btn-action {
      margin-right: 5px;
    }

    .search-bar {
      max-width: 400px;
      margin-bottom: 25px;
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
    <h4>👨‍🏫 Lecturer</h4>
    <hr style="border-color: #ffffff66;">
  </div>
  <a href="lecturer-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
  <a href="lecturer-my-courses.php" class="active"><i class="fas fa-book-open me-2"></i> My Courses</a>
  <a href="attendance-session.php"><i class="fas fa-video me-2"></i> Attendance Session</a>
  <a href="attendance-reports.php"><i class="fas fa-chart-bar me-2"></i> Attendance Reports</a>
  <a href="leave-requests.php"><i class="fas fa-envelope me-2"></i> Leave Requests</a>
  <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
</div>

<!-- Topbar -->
<div class="topbar d-flex justify-content-between align-items-center">
  <h5 class="m-0 fw-bold">My Courses</h5>
  <span>Lecturer Panel</span>
</div>

<!-- Main Content -->
<div class="main-content">
  <input type="text" id="courseSearch" class="form-control search-bar" placeholder="Search courses by name or code...">

  <div class="row g-4" id="courseContainer">
    <!-- Course Card -->
    <div class="col-md-6 course-card">
      <div class="card p-4">
        <h6 class="fw-semibold course-title">Software Engineering</h6>
        <p class="text-muted small mb-2 course-code">SE201</p>
        <div>
          <a href="attendance-session.php" class="btn btn-sm btn-primary btn-action">
            <i class="fas fa-video me-1"></i> Start Session
          </a>
          <a href="attendance-reports.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-chart-bar me-1"></i> View Reports
          </a>
        </div>
      </div>
    </div>

    <div class="col-md-6 course-card">
      <div class="card p-4">
        <h6 class="fw-semibold course-title">Networking</h6>
        <p class="text-muted small mb-2 course-code">NT101</p>
        <div>
          <a href="attendance-session.php" class="btn btn-sm btn-primary btn-action">
            <i class="fas fa-video me-1"></i> Start Session
          </a>
          <a href="attendance-reports.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-chart-bar me-1"></i> View Reports
          </a>
        </div>
      </div>
    </div>

    <!-- Add more courses dynamically -->
  </div>
</div>

<!-- Footer -->
<div class="footer">
  &copy; 2025 Rwanda Polytechnic | Lecturer Panel
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Filter Script -->
<script>
  const searchInput = document.getElementById('courseSearch');
  const courseCards = document.querySelectorAll('.course-card');

  searchInput.addEventListener('keyup', function () {
    const query = this.value.toLowerCase();

    courseCards.forEach(card => {
      const title = card.querySelector('.course-title').textContent.toLowerCase();
      const code = card.querySelector('.course-code').textContent.toLowerCase();

      if (title.includes(query) || code.includes(query)) {
        card.style.display = 'block';
      } else {
        card.style.display = 'none';
      }
    });
  });
</script>

</body>
</html>
