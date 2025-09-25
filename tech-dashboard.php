<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['tech']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Tech Staff Dashboard | RP Attendance System</title>

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

        .card i {
            font-size: 2rem;
            color: #0066cc;
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
            <h4>🛠️ Tech Staff</h4>
            <hr style="border-color: #ffffff66;">
        </div>
        <a href="tech-dashboard.php" class="active"><i class="fas fa-tools me-2"></i> Dashboard</a>
        <a href="webcam-setup.php"><i class="fas fa-video me-2"></i> Webcam Setup</a>
        <a href="fingerprint-setup.php"><i class="fas fa-fingerprint me-2"></i> Fingerprint Setup</a>
        <a href="system-logs.php"><i class="fas fa-file-alt me-2"></i> System Logs</a>
        <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
    </div>

    <!-- Topbar -->
    <div class="topbar d-flex justify-content-between align-items-center">
        <h5 class="m-0 fw-bold">Tech Staff Dashboard</h5>
        <span>RP Attendance System</span>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card p-4 text-center">
                    <i class="fas fa-video mb-3"></i>
                    <h6>Webcam Setup</h6>
                    <p class="text-muted small">Test and configure live camera used in attendance sessions.</p>
                    <a href="webcam-setup.php" class="btn btn-primary btn-sm">Configure</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 text-center">
                    <i class="fas fa-fingerprint mb-3"></i>
                    <h6>Fingerprint Setup</h6>
                    <p class="text-muted small">Connect and test USB fingerprint scanner devices.</p>
                    <a href="fingerprint-setup.php" class="btn btn-primary btn-sm">Configure</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-4 text-center">
                    <i class="fas fa-file-alt mb-3"></i>
                    <h6>System Logs</h6>
                    <p class="text-muted small">Review and diagnose biometric and system operation logs.</p>
                    <a href="system-logs.php" class="btn btn-primary btn-sm">View Logs</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        &copy; 2025 Rwanda Polytechnic | Tech Panel
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>