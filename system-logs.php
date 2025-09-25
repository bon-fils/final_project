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
  <title>System Logs | RP Attendance System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container">
    <h3>📄 System Logs</h3>
    <p class="text-muted">Logs of biometric operations, system alerts, and configuration events.</p>

    <table class="table table-striped">
      <thead>
        <tr>
          <th>Date</th>
          <th>Event</th>
          <th>Status</th>
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
</body>
</html>
