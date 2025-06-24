<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Admin Dashboard | RP Attendance System</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background-color: #003366;
            color: white;
            padding-top: 20px;
            z-index: 1000;
        }

        .sidebar h4 {
            font-weight: bold;
        }

        .sidebar a {
            display: block;
            padding: 12px 20px;
            color: #ffffff;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .sidebar a:hover {
            background-color: #0059b3;
        }

        /* Topbar */
        .topbar {
            margin-left: 250px;
            background-color: #ffffff;
            padding: 10px 30px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 900;
        }

        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .dashboard-card {
            border-left: 5px solid #0066cc;
            background-color: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
        }

        .dashboard-card i {
            font-size: 1.8rem;
            color: #0066cc;
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 15px;
            margin-left: 250px;
            font-size: 0.9rem;
            color: #888;
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
                /* Hidden for mobile view for now */
            }
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="text-center mb-4">
            <h4>👩‍💼 Admin</h4>
            <hr style="border-color: #ffffff66;" />
        </div>
        <a href="admin-dashboard.php"><i class="fas fa-home me-2"></i> Dashboard</a>
        <a href="register-student.php"><i class="fas fa-user-plus me-2"></i> Register Student</a>
        <a href="manage-departments.php"><i class="fas fa-building me-2"></i> Manage Departments</a>
        <a href="admin-reports.html"><i class="fas fa-chart-bar me-2"></i> Reports</a>
        <a href="index.html"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <!-- Topbar -->
    <div class="topbar">
        <h5 class="m-0 fw-bold">Admin Dashboard</h5>
        <span>Welcome, Admin</span>
    </div>

    <!-- Main Dashboard Content -->
    <div class="main-content container-fluid">
        <div class="row g-4">
            <div class="col-md-6 col-xl-3">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6>Total Students</h6>
                            <h4>320</h4>
                        </div>
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6>Total Lecturers</h6>
                            <h4>45</h4>
                        </div>
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6>Active Attendance</h6>
                            <h4>3</h4>
                        </div>
                        <i class="fas fa-video"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="dashboard-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6>Pending Leave Requests</h6>
                            <h4>6</h4>
                        </div>
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Admin Panel
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>