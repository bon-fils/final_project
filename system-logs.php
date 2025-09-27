<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['tech']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>System Logs | RP Attendance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
      --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
      --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      --info-gradient: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
      --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
      --shadow-medium: 0 8px 25px rgba(0,0,0,0.15);
      --shadow-heavy: 0 12px 35px rgba(0,0,0,0.2);
      --border-radius: 12px;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(to right, #0066cc, #003366);
      min-height: 100vh;
      margin: 0;
      position: relative;
      overflow-x: hidden;
    }

    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="80" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="60" cy="40" r="0.5" fill="rgba(255,255,255,0.1)"/></svg>');
      pointer-events: none;
      z-index: -1;
    }

    .main-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: var(--border-radius);
      padding: 40px;
      box-shadow: var(--shadow-medium);
      border: 1px solid rgba(255, 255, 255, 0.2);
      margin: 20px;
      position: relative;
      overflow: hidden;
    }

    .main-container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--primary-gradient);
    }

    .page-header {
      text-align: center;
      margin-bottom: 40px;
      padding-bottom: 25px;
      border-bottom: 2px solid rgba(0, 102, 204, 0.1);
      position: relative;
    }

    .page-title {
      color: #2c3e50;
      font-weight: 700;
      margin-bottom: 15px;
      font-size: 2rem;
      background: var(--primary-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .page-subtitle {
      color: #666;
      font-size: 1rem;
      margin: 0;
      font-weight: 400;
      opacity: 0.8;
    }

    .table {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: var(--border-radius);
      overflow: hidden;
      box-shadow: var(--shadow-light);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .table thead th {
      background: var(--primary-gradient);
      color: white;
      border: none;
      font-weight: 600;
      padding: 15px;
      position: relative;
    }

    .table tbody td {
      padding: 15px;
      border-color: rgba(0, 102, 204, 0.1);
      transition: var(--transition);
    }

    .table tbody tr:hover td {
      background: rgba(0, 102, 204, 0.05);
      transform: translateX(5px);
    }

    .badge {
      font-size: 0.8rem;
      font-weight: 600;
      padding: 6px 12px;
      border-radius: 20px;
    }

    .badge.bg-success {
      background: var(--success-gradient) !important;
    }

    .badge.bg-danger {
      background: var(--danger-gradient) !important;
    }

    .badge.bg-warning {
      background: var(--warning-gradient) !important;
    }

    @media (max-width: 768px) {
      .main-container {
        margin: 10px;
        padding: 20px;
      }

      .page-title {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="main-container">
    <div class="page-header">
      <h1 class="page-title">
        <i class="fas fa-file-alt me-3"></i>System Logs
      </h1>
      <p class="page-subtitle">
        Logs of biometric operations, system alerts, and configuration events
      </p>
    </div>

    <div class="table-responsive">
      <table class="table table-hover">
        <thead>
          <tr>
            <th><i class="fas fa-calendar me-2"></i>Date</th>
            <th><i class="fas fa-info-circle me-2"></i>Event</th>
            <th><i class="fas fa-check-circle me-2"></i>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>2025-08-05 10:45</td>
            <td>Webcam Configured</td>
            <td><span class="badge bg-success">Success</span></td>
          </tr>
          <tr>
            <td>2025-08-05 10:46</td>
            <td>Fingerprint Device Not Found</td>
            <td><span class="badge bg-danger">Error</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
