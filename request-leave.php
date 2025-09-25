<?php
$errorMsg = '';
$successMsg = '';

require_once "config.php";
require_once "session_check.php";

// Ensure student is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
  header("Location: index.php");
  exit;
}

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
  header("Location: index.php");
  exit;
}

// Fetch available courses for the student
$courses = [];
try {
  $stmt = $pdo->prepare("SELECT c.id, c.name FROM courses c INNER JOIN students s ON s.department_id = c.department_id WHERE s.id = ?");
  $stmt->execute([$student_id]);
  $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $courses = [];
}

// Fetch student's leave requests for display
$leave_requests = [];
try {
  $stmt = $pdo->prepare("SELECT id, reason, supporting_file, status, requested_at FROM leave_requests WHERE student_id = ? ORDER BY requested_at DESC LIMIT 5");
  $stmt->execute([$student_id]);
  $leave_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $leave_requests = [];
}

// Handle error messages from GET parameters
if (isset($_GET['error'])) {
  switch ($_GET['error']) {
    case 'missing_fields':
      $errorMsg = 'Please fill in all required fields.';
      break;
    case 'file_too_large':
      $errorMsg = 'File size exceeds maximum allowed size (5MB).';
      break;
    case 'invalid_file_type':
      $errorMsg = 'Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG files are allowed.';
      break;
    case 'upload_failed':
      $errorMsg = 'File upload failed. Please try again.';
      break;
    case 'csrf_invalid':
      $errorMsg = 'Security token expired. Please try again.';
      break;
    default:
      $errorMsg = 'An error occurred. Please try again.';
  }
}

if (isset($_GET['success'])) {
  $successMsg = 'Leave request submitted successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Request Leave | Student | RP Attendance System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
/* ===== CSS CUSTOM PROPERTIES ===== */
:root {
  --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --primary-gradient-hover: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
  --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  --danger-gradient: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
  --info-gradient: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
  --light-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  --glass-gradient: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);

  --shadow-light: 0 4px 15px rgba(0, 0, 0, 0.08);
  --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.15);
  --shadow-heavy: 0 12px 35px rgba(0, 0, 0, 0.2);
  --shadow-glow: 0 0 20px rgba(102, 126, 234, 0.3);
  --shadow-inset: inset 0 2px 4px rgba(0, 0, 0, 0.1);

  --border-radius: 16px;
  --border-radius-sm: 8px;
  --border-radius-lg: 20px;
  --sidebar-width: 250px;
  --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-fast: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);

  --font-primary: 'Inter', 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
  --font-secondary: 'Poppins', 'Segoe UI', sans-serif;
}

/* ===== BODY & BASE STYLES ===== */
body {
  background: var(--light-gradient);
  min-height: 100vh;
  font-family: var(--font-primary);
  margin: 0;
  position: relative;
  overflow-x: hidden;
  color: #2c3e50;
  font-weight: 400;
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

body::before {
  content: '';
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background:
    radial-gradient(circle at 20% 80%, rgba(102, 126, 234, 0.15) 0%, transparent 50%),
    radial-gradient(circle at 80% 20%, rgba(118, 75, 162, 0.15) 0%, transparent 50%),
    radial-gradient(circle at 40% 40%, rgba(79, 172, 254, 0.1) 0%, transparent 50%),
    url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><circle cx="40" cy="40" r="1" fill="rgba(102,126,234,0.08)"/><circle cx="160" cy="160" r="1" fill="rgba(102,126,234,0.08)"/><circle cx="120" cy="80" r="0.5" fill="rgba(102,126,234,0.08)"/><circle cx="80" cy="120" r="0.5" fill="rgba(102,126,234,0.08)"/></svg>');
  pointer-events: none;
  z-index: -1;
  animation: backgroundFloat 20s ease-in-out infinite;
}

@keyframes backgroundFloat {
  0%, 100% { transform: translateY(0px) rotate(0deg); }
  50% { transform: translateY(-10px) rotate(1deg); }
}

/* ===== SIDEBAR ===== */
.sidebar {
  position: fixed;
  top: 0;
  left: 0;
  width: var(--sidebar-width);
  height: 100vh;
  background: rgba(0, 51, 102, 0.95);
  backdrop-filter: blur(20px);
  color: white;
  padding: 30px 0;
  box-shadow: var(--shadow-heavy);
  border-right: 1px solid rgba(255, 255, 255, 0.2);
  z-index: 1000;
  overflow-y: auto;
  transition: var(--transition);
}

.sidebar::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: var(--glass-gradient);
  pointer-events: none;
}

.sidebar-header {
  text-align: center;
  padding: 0 20px 30px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.2);
  margin-bottom: 20px;
  position: relative;
  z-index: 1;
}

.sidebar-header h4 {
  color: white;
  font-weight: 700;
  margin-bottom: 10px;
  font-size: 1.2rem;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
  background: var(--primary-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.sidebar a {
  display: block;
  padding: 15px 25px;
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  font-weight: 500;
  border-radius: 0 var(--border-radius-lg) var(--border-radius-lg) 0;
  margin: 8px 0;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
  z-index: 1;
}

.sidebar a::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  width: 0;
  background: var(--primary-gradient);
  transition: var(--transition);
  z-index: -1;
  border-radius: 0 var(--border-radius-lg) var(--border-radius-lg) 0;
}

.sidebar a:hover::before, .sidebar a.active::before {
  width: 100%;
}

.sidebar a:hover, .sidebar a.active {
  color: white;
  padding-left: 35px;
  transform: translateX(8px);
  box-shadow: var(--shadow-glow);
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.sidebar a i {
  margin-right: 12px;
  width: 20px;
  text-align: center;
  transition: var(--transition-fast);
}

.sidebar a:hover i {
  transform: scale(1.1);
}

/* ===== TOPBAR ===== */
.topbar {
  margin-left: var(--sidebar-width);
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  padding: 20px 30px;
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 900;
  box-shadow: var(--shadow-light);
  transition: var(--transition);
}

.topbar h5 {
  margin: 0;
  font-weight: 600;
  color: #333;
  background: var(--primary-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.topbar span {
  color: #666;
  font-size: 0.9rem;
  font-weight: 500;
}

/* ===== MAIN CONTENT ===== */
.main-content {
  margin-left: var(--sidebar-width);
  padding: 40px 30px;
  max-width: 1400px;
  transition: var(--transition);
  display: flex;
  flex-direction: column;
  gap: 30px;
  width: 100%;
}

/* ===== CONTENT SECTIONS ===== */
.content-section {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  border-radius: var(--border-radius);
  padding: 40px;
  box-shadow: var(--shadow-medium);
  border: 1px solid rgba(255, 255, 255, 0.3);
  position: relative;
  overflow: hidden;
  transition: var(--transition);
  margin-bottom: 0;
}

.content-section:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-heavy);
}

.content-section-header {
  text-align: center;
  margin-bottom: 40px;
  padding-bottom: 25px;
  border-bottom: 2px solid rgba(102, 126, 234, 0.1);
  position: relative;
}

.content-section-header::before {
  content: '';
  position: absolute;
  bottom: -2px;
  left: 50%;
  transform: translateX(-50%);
  width: 100px;
  height: 2px;
  background: var(--primary-gradient);
  border-radius: 1px;
}

.content-section-title {
  color: #2c3e50;
  font-weight: 700;
  margin-bottom: 15px;
  font-size: 1.8rem;
  position: relative;
  display: inline-block;
  background: var(--secondary-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.content-section-title::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 80px;
  height: 4px;
  background: var(--secondary-gradient);
  border-radius: 2px;
}

.content-section-subtitle {
  color: #666;
  font-size: 1rem;
  margin: 0;
  font-weight: 400;
  opacity: 0.8;
  max-width: 600px;
  margin: 0 auto;
  line-height: 1.5;
}

/* ===== FORM SECTIONS ===== */
.form-section {
  background: rgba(102, 126, 234, 0.02);
  border: 1px solid rgba(102, 126, 234, 0.1);
  border-radius: var(--border-radius-sm);
  padding: 25px;
  margin-bottom: 25px;
  transition: var(--transition);
  position: relative;
}

.form-section:hover {
  background: rgba(102, 126, 234, 0.05);
  border-color: rgba(102, 126, 234, 0.2);
  transform: translateY(-1px);
}

.form-section::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--primary-gradient);
  border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0;
}

.form-section-title {
  color: #2c3e50;
  font-weight: 600;
  font-size: 1.1rem;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.form-section-title i {
  color: #667eea;
  margin-right: 10px;
}

.form-label {
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 10px;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  transition: var(--transition-fast);
}

.form-label:hover {
  color: #667eea;
}

.form-control, .form-select {
  border: 2px solid #e9ecef;
  border-radius: var(--border-radius-sm);
  padding: 14px 18px;
  font-size: 0.95rem;
  transition: var(--transition);
  background: rgba(255, 255, 255, 0.95);
  font-family: var(--font-primary);
  position: relative;
}

.form-control:hover, .form-select:hover {
  border-color: #667eea;
  transform: translateY(-1px);
}

.form-control:focus, .form-select:focus {
  border-color: #667eea;
  box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15), var(--shadow-light);
  background: white;
  transform: translateY(-1px);
}

textarea.form-control {
  resize: vertical;
  min-height: 120px;
  line-height: 1.6;
}

.character-count {
  font-size: 0.8rem;
  color: #666;
  margin-top: 8px;
  font-weight: 500;
  transition: var(--transition-fast);
}

.character-count.warning {
  color: #fd7e14;
}

.character-count.danger {
  color: #dc3545;
}

.file-info {
  font-size: 0.85rem;
  color: #666;
  margin-top: 8px;
  font-weight: 500;
}

/* ===== BUTTON ENHANCEMENTS ===== */
.btn-lg {
  padding: 16px 40px;
  font-size: 1rem;
  border-radius: var(--border-radius-sm);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  box-shadow: var(--shadow-medium);
  position: relative;
  overflow: hidden;
}

.btn-lg:hover {
  transform: translateY(-2px) scale(1.02);
  box-shadow: var(--shadow-glow);
}

.btn-outline-primary {
  border: 2px solid #667eea;
  color: #667eea;
  background: transparent;
  transition: var(--transition);
}

.btn-outline-primary:hover {
  background: var(--primary-gradient);
  border-color: transparent;
  color: white;
  transform: translateY(-1px);
  box-shadow: var(--shadow-light);
}

/* ===== TABLE SECTION ===== */
.table-container {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  border-radius: var(--border-radius);
  padding: 30px;
  box-shadow: var(--shadow-medium);
  border: 1px solid rgba(255, 255, 255, 0.3);
  margin-bottom: 30px;
  position: relative;
  overflow: hidden;
  transition: var(--transition);
}

.table-container:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-heavy);
}

.table-container::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 6px;
  background: var(--secondary-gradient);
  box-shadow: 0 0 10px rgba(240, 147, 251, 0.5);
}

.table-header {
  text-align: center;
  margin-bottom: 30px;
  position: relative;
}

.table-title {
  color: #2c3e50;
  font-weight: 700;
  margin-bottom: 10px;
  font-size: 1.6rem;
  background: var(--secondary-gradient);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  position: relative;
}

.table-title::after {
  content: '';
  position: absolute;
  bottom: -8px;
  left: 50%;
  transform: translateX(-50%);
  width: 50px;
  height: 3px;
  background: var(--secondary-gradient);
  border-radius: 2px;
}

.table-subtitle {
  color: #666;
  font-size: 0.95rem;
  margin: 0;
  font-weight: 400;
  opacity: 0.8;
}

.table {
  background: white;
  border-radius: var(--border-radius-sm);
  overflow: hidden;
  box-shadow: var(--shadow-light);
  border: none;
  position: relative;
  margin-bottom: 0;
  table-layout: auto;
  width: 100%;
}

.table::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(45deg, transparent 48%, rgba(102, 126, 234, 0.02) 50%, transparent 52%);
  pointer-events: none;
}

.table thead th {
  background: var(--primary-gradient);
  color: white;
  border: none;
  font-weight: 600;
  padding: 18px 15px;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  position: relative;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
  white-space: nowrap;
  vertical-align: middle;
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.table thead th::after {
  content: '';
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, rgba(255, 255, 255, 0.4) 0%, rgba(255, 255, 255, 0.2) 50%, rgba(255, 255, 255, 0.4) 100%);
  border-radius: 0 0 var(--border-radius-sm) var(--border-radius-sm);
}

.table thead th i {
  margin-right: 8px;
  font-size: 0.8rem;
  opacity: 0.9;
}

.table tbody td {
  padding: 18px 15px;
  border: none;
  border-bottom: 1px solid rgba(102, 126, 234, 0.06);
  vertical-align: middle;
  transition: var(--transition-fast);
  position: relative;
  word-wrap: break-word;
  overflow-wrap: break-word;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.8) 0%, rgba(248, 250, 252, 0.8) 100%);
}

.table tbody tr {
  transition: var(--transition-fast);
  position: relative;
}

.table tbody tr::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 0;
  background: var(--primary-gradient);
  transition: var(--transition);
  z-index: -1;
}

.table tbody tr:hover::before {
  width: 4px;
}

.table tbody tr:hover {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.06) 0%, rgba(118, 75, 162, 0.04) 100%);
  transform: translateY(-1px) translateX(2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
  border-left: 3px solid #667eea;
}

.table tbody tr:hover td {
  color: #2c3e50;
  font-weight: 500;
  background: rgba(255, 255, 255, 0.9);
}

.table tbody tr:last-child td {
  border-bottom: none;
}

/* ===== TABLE CELL SPECIFIC STYLING ===== */
.table tbody td:first-child {
  font-weight: 700;
  color: #667eea;
  text-align: center;
  width: 50px;
  min-width: 50px;
}

.table tbody td:nth-child(2) {
  min-width: 200px;
  max-width: 300px;
  word-wrap: break-word;
  word-break: break-word;
  overflow-wrap: break-word;
  line-height: 1.4;
}

.table tbody td:nth-child(3),
.table tbody td:nth-child(4) {
  text-align: center;
  width: 110px;
  min-width: 110px;
  white-space: nowrap;
}

.table tbody td:nth-child(5) {
  text-align: center;
  width: 90px;
  min-width: 90px;
}

.table tbody td:nth-child(6) {
  text-align: center;
  width: 100px;
  min-width: 100px;
}

.table tbody td:nth-child(7) {
  text-align: center;
  width: 130px;
  min-width: 130px;
  font-size: 0.9rem;
}

/* ===== REASON TEXT STYLING ===== */
.reason-text {
  min-width: 200px;
  max-width: 300px;
  line-height: 1.5;
  font-size: 0.85rem;
  color: #2c3e50;
  font-weight: 500;
  word-wrap: break-word;
  word-break: break-word;
  overflow-wrap: break-word;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  padding: 4px 0;
  transition: var(--transition-fast);
}

.reason-text:hover {
  color: #667eea;
  -webkit-line-clamp: 4;
}

/* ===== DATE BADGE STYLING ===== */
.date-badge {
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.06) 100%);
  color: #667eea;
  padding: 6px 12px;
  border-radius: 8px;
  font-size: 0.75rem;
  font-weight: 600;
  display: inline-block;
  min-width: 95px;
  max-width: 115px;
  text-align: center;
  border: 1px solid rgba(102, 126, 234, 0.15);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-shadow: 0 2px 4px rgba(102, 126, 234, 0.08);
  transition: var(--transition-fast);
  position: relative;
}

.date-badge::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.3) 0%, rgba(255, 255, 255, 0.1) 100%);
  border-radius: inherit;
  pointer-events: none;
}

.date-badge:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(102, 126, 234, 0.15);
  border-color: rgba(102, 126, 234, 0.25);
}

/* ===== STATUS BADGE IMPROVEMENTS ===== */
.status-badge {
  padding: 8px 14px;
  border-radius: 25px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 85px;
  max-width: 110px;
  text-align: center;
  border: 2px solid transparent;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
  transition: var(--transition-fast);
  position: relative;
}

.status-badge::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.05) 100%);
  border-radius: inherit;
  pointer-events: none;
}

.status-badge:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.status-pending {
  background: var(--warning-gradient);
  color: #212529;
  border-color: rgba(255, 193, 7, 0.4);
  text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
}

.status-approved {
  background: var(--success-gradient);
  color: white;
  border-color: rgba(25, 135, 84, 0.4);
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.status-rejected {
  background: var(--danger-gradient);
  color: white;
  border-color: rgba(220, 53, 69, 0.4);
  text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

/* ===== REQUEST TIME STYLING ===== */
.request-time {
  font-size: 0.8rem;
  line-height: 1.3;
  text-align: center;
  min-width: 120px;
  padding: 2px 0;
}

.request-date {
  font-weight: 600;
  color: #2c3e50;
  margin-bottom: 2px;
  font-size: 0.8rem;
  text-shadow: 0 1px 2px rgba(44, 62, 80, 0.1);
}

.request-time-detail {
  color: #666;
  font-size: 0.75rem;
  font-weight: 500;
  opacity: 0.8;
  transition: var(--transition-fast);
}

.request-time:hover .request-time-detail {
  opacity: 1;
  color: #667eea;
}

/* ===== DOCUMENT PLACEHOLDER ===== */
.document-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 10px 8px;
  opacity: 0.7;
  transition: var(--transition-fast);
  border-radius: 6px;
  background: rgba(108, 117, 125, 0.05);
}

.document-placeholder:hover {
  opacity: 1;
  background: rgba(108, 117, 125, 0.08);
  transform: translateY(-1px);
}

.document-placeholder i {
  font-size: 1.1rem;
  margin-bottom: 3px;
  color: #6c757d;
}

.document-placeholder small {
  font-size: 0.7rem;
  font-style: italic;
  color: #6c757d;
  font-weight: 500;
}

/* ===== TABLE CELL ALIGNMENT ===== */
.table tbody td.text-center {
  vertical-align: middle;
}

.table tbody td:first-child {
  text-align: center;
  vertical-align: middle;
}

/* ===== TABLE CONTAINER ===== */
.table-responsive {
  border-radius: var(--border-radius-sm);
  box-shadow: var(--shadow-medium);
  background: white;
  overflow-x: auto;
  overflow-y: visible;
  -webkit-overflow-scrolling: touch;
  border: 1px solid rgba(102, 126, 234, 0.1);
  position: relative;
}

.table-responsive::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--secondary-gradient);
  border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0;
}

.table-responsive::-webkit-scrollbar {
  height: 8px;
}

.table-responsive::-webkit-scrollbar-track {
  background: rgba(102, 126, 234, 0.1);
  border-radius: 4px;
  margin: 0 2px;
}

.table-responsive::-webkit-scrollbar-thumb {
  background: var(--primary-gradient);
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.table-responsive::-webkit-scrollbar-thumb:hover {
  background: var(--primary-gradient-hover);
  transform: scaleY(1.2);
}

/* ===== IMPROVED TABLE SPACING ===== */
.table tbody td {
  vertical-align: middle;
  line-height: 1.4;
}

.table tbody td:nth-child(2) {
  vertical-align: top;
  padding-top: 20px;
}

/* ===== STATUS ICONS ===== */
.status-badge i {
  font-size: 0.7rem;
  margin-right: 6px;
}

/* ===== DATE BADGE ENHANCEMENTS ===== */
.date-badge {
  box-shadow: 0 2px 4px rgba(102, 126, 234, 0.1);
  transition: var(--transition-fast);
}

.date-badge:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
}

/* ===== TABLE ENHANCEMENTS ===== */
.table-header-custom {
  background: var(--primary-gradient) !important;
  color: white;
  border: none;
  font-weight: 600;
  padding: 15px 12px;
  font-size: 0.9rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.reason-text {
  max-width: 200px;
  line-height: 1.4;
  font-size: 0.9rem;
}

.date-badge {
  background: rgba(102, 126, 234, 0.1);
  color: #667eea;
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 500;
}

.status-badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: inline-flex;
  align-items: center;
}

.status-pending {
  background: var(--warning-gradient);
  color: #212529;
}

.status-approved {
  background: var(--success-gradient);
  color: white;
}

.status-rejected {
  background: var(--danger-gradient);
  color: white;
}

.request-time {
  font-size: 0.85rem;
  line-height: 1.3;
}

.request-date {
  font-weight: 600;
  color: #333;
}

.request-time-detail {
  color: #666;
  font-size: 0.8rem;
}

.empty-state {
  padding: 60px 20px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-state-icon {
  margin-bottom: 20px;
  opacity: 0.6;
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}

.empty-state-title {
  margin-bottom: 15px;
  font-weight: 600;
  color: #6c757d;
  font-size: 1.2rem;
}

.empty-state-text {
  margin: 0 0 25px 0;
  font-size: 0.95rem;
  color: #868e96;
  max-width: 400px;
  text-align: center;
  line-height: 1.5;
}

.empty-state-action .btn {
  padding: 12px 24px;
  border-radius: var(--border-radius-sm);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: var(--shadow-light);
  transition: var(--transition);
}

.empty-state-action .btn:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-medium);
}

/* ===== FILE PREVIEW ENHANCEMENTS ===== */
.file-preview {
  animation: filePreviewSlideIn 0.3s ease-out;
}

@keyframes filePreviewSlideIn {
  from {
    opacity: 0;
    transform: translateY(20px) scale(0.9);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.file-name {
  color: #2c3e50;
  font-weight: 600;
  margin-bottom: 5px;
  font-size: 0.9rem;
  word-break: break-all;
}

.file-size {
  color: #666;
  font-size: 0.8rem;
  margin-bottom: 0;
}

/* ===== PROGRESS BAR ENHANCEMENTS ===== */
.progress {
  border-radius: 2px;
  overflow: hidden;
  background-color: rgba(102, 126, 234, 0.1);
}

.progress-bar {
  background: var(--success-gradient) !important;
  transition: width 0.3s ease;
}

/* ===== SCROLL ANIMATIONS ===== */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes bounceIn {
  0% {
    opacity: 0;
    transform: scale(0.3);
  }
  50% {
    opacity: 1;
    transform: scale(1.05);
  }
  70% {
    transform: scale(0.9);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}

.form-container, .table-container {
  animation: fadeInUp 0.6s ease-out;
}

.sidebar {
  animation: slideInLeft 0.6s ease-out;
}

.btn-primary {
  animation: bounceIn 0.6s ease-out;
}

/* ===== FOCUS STATES ===== */
.form-control:focus, .form-select:focus {
  animation: focusPulse 0.3s ease-out;
}

@keyframes focusPulse {
  0% {
    box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
  }
  70% {
    box-shadow: 0 0 0 10px rgba(102, 126, 234, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
  }
}

/* ===== STATUS BADGES ===== */
.badge {
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge.bg-warning {
  background: var(--warning-gradient) !important;
  color: #212529;
}

.badge.bg-success {
  background: var(--success-gradient) !important;
}

.badge.bg-danger {
  background: var(--danger-gradient) !important;
}

/* ===== BUTTONS ===== */
.btn-primary {
  background: var(--primary-gradient);
  border: none;
  padding: 14px 32px;
  border-radius: var(--border-radius-sm);
  font-weight: 600;
  font-size: 0.95rem;
  transition: var(--transition);
  position: relative;
  overflow: hidden;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: var(--shadow-light);
}

.btn-primary::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: var(--transition-slow);
}

.btn-primary:hover::before {
  left: 100%;
}

.btn-primary:hover {
  transform: translateY(-3px) scale(1.02);
  box-shadow: var(--shadow-glow);
  background: var(--primary-gradient-hover);
}

.btn-primary:active {
  transform: translateY(-1px) scale(1.01);
}

.btn-primary:disabled {
  opacity: 0.7;
  transform: none;
  box-shadow: var(--shadow-light);
}

.btn-primary .spinner-border {
  width: 1rem;
  height: 1rem;
  margin-right: 8px;
}

/* Button group enhancements */
.btn-group .btn {
  border-radius: 0;
}

.btn-group .btn:first-child {
  border-radius: var(--border-radius-sm) 0 0 var(--border-radius-sm);
}

.btn-group .btn:last-child {
  border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;
}

/* ===== ALERTS ===== */
.alert {
  border-radius: 8px;
  border: none;
  padding: 15px 20px;
  margin-bottom: 25px;
  font-weight: 500;
}

.alert-danger {
  background: linear-gradient(135deg, rgba(220, 53, 69, 0.1) 0%, rgba(220, 53, 69, 0.05) 100%);
  color: #721c24;
  border-left: 4px solid #dc3545;
}

.alert-success {
  background: linear-gradient(135deg, rgba(25, 135, 84, 0.1) 0%, rgba(25, 135, 84, 0.05) 100%);
  color: #155724;
  border-left: 4px solid #198754;
}

/* ===== FOOTER ===== */
.footer {
  text-align: center;
  margin-left: var(--sidebar-width);
  padding: 20px;
  font-size: 0.9rem;
  color: #666;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(10px);
  border-top: 1px solid rgba(0, 0, 0, 0.1);
  transition: var(--transition);
}

/* ===== ANIMATIONS ===== */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

.form-container, .table-container {
  animation: fadeInUp 0.6s ease-out;
}

.sidebar {
  animation: slideInLeft 0.6s ease-out;
}

/* ===== LAYOUT ORGANIZATION ===== */
.row {
  margin: 0 -15px;
}

.row > * {
  padding: 0 15px;
}

.gap-20 {
  gap: 20px;
}

.gap-30 {
  gap: 30px;
}

.gap-40 {
  gap: 40px;
}

/* ===== SECTION SPACING ===== */
.content-section + .content-section {
  margin-top: 40px;
}

.form-section + .form-section {
  margin-top: 30px;
}

/* ===== COLUMN ORGANIZATION ===== */
.col-lg-6 {
  flex: 0 0 auto;
  width: 50%;
}

.col-md-6 {
  flex: 0 0 auto;
  width: 50%;
}

@media (max-width: 992px) {
  .col-lg-6 {
    width: 100%;
    flex: 0 0 auto;
  }
}

@media (max-width: 768px) {
  .col-md-6 {
    width: 100%;
    flex: 0 0 auto;
  }
}

/* ===== RESPONSIVE DESIGN ===== */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
    width: var(--sidebar-width);
  }

  .sidebar.show {
    transform: translateX(0);
  }

  .topbar, .main-content, .footer {
    margin-left: 0 !important;
  }

  .topbar {
    padding: 15px 20px;
  }

  .main-content {
    padding: 20px 15px;
    gap: 20px;
  }

  .content-section {
    padding: 25px 20px;
    margin-bottom: 20px;
  }

  .content-section-header {
    margin-bottom: 25px;
  }

  .content-section-title {
    font-size: 1.4rem;
  }

  .content-section-subtitle {
    font-size: 0.9rem;
  }

  .form-section {
    padding: 20px 15px;
    margin-bottom: 20px;
  }

  .form-section-title {
    font-size: 1rem;
  }

  .table-responsive {
    font-size: 0.85rem;
  }

  .table thead th,
  .table tbody td {
    padding: 10px 6px;
    font-size: 0.75rem;
  }

  .table thead th {
    font-size: 0.7rem;
    padding: 8px 4px;
  }

  .table tbody td:first-child {
    width: 40px;
    min-width: 40px;
    font-size: 0.8rem;
  }

  .table tbody td:nth-child(2) {
    min-width: 150px;
    max-width: 200px;
    font-size: 0.75rem;
  }

  .table tbody td:nth-child(3),
  .table tbody td:nth-child(4) {
    width: 90px;
    min-width: 90px;
    font-size: 0.75rem;
  }

  .table tbody td:nth-child(5) {
    width: 70px;
    min-width: 70px;
    font-size: 0.75rem;
  }

  .table tbody td:nth-child(6) {
    width: 80px;
    min-width: 80px;
    font-size: 0.75rem;
  }

  .table tbody td:nth-child(7) {
    width: 100px;
    min-width: 100px;
    font-size: 0.75rem;
  }

  .reason-text {
    min-width: 150px;
    max-width: 200px;
    font-size: 0.75rem;
    -webkit-line-clamp: 2;
  }

  .date-badge {
    min-width: 70px;
    max-width: 90px;
    font-size: 0.7rem;
    padding: 3px 6px;
  }

  .status-badge {
    min-width: 60px;
    max-width: 80px;
    font-size: 0.65rem;
    padding: 4px 8px;
  }

  .request-time {
    font-size: 0.7rem;
  }

  .request-date {
    font-size: 0.7rem;
  }

  .request-time-detail {
    font-size: 0.65rem;
  }
}

@media (max-width: 576px) {
  .content-section {
    padding: 20px 15px;
  }

  .content-section-title {
    font-size: 1.2rem;
  }

  .form-section {
    padding: 15px 12px;
  }

  .btn-lg {
    width: 100%;
    margin-top: 10px;
    padding: 14px 20px;
  }

  .table th,
  .table td {
    padding: 6px 3px;
    font-size: 0.7rem;
  }

  .table thead th {
    font-size: 0.65rem;
    padding: 6px 2px;
  }

  .status-badge {
    font-size: 0.6rem;
    padding: 3px 6px;
    min-width: 50px;
  }

  .date-badge {
    font-size: 0.65rem;
    padding: 2px 4px;
    min-width: 60px;
  }

  .reason-text {
    min-width: 120px;
    max-width: 150px;
    font-size: 0.65rem;
  }

  .request-time {
    font-size: 0.65rem;
  }

  .request-date {
    font-size: 0.65rem;
  }

  .request-time-detail {
    font-size: 0.6rem;
  }
}

/* ===== CUSTOM SCROLLBAR ===== */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb {
  background: var(--primary-gradient);
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
}

/* ===== FORM ENHANCEMENTS ===== */
.course-select-wrapper {
  background: rgba(102, 126, 234, 0.05);
  border-radius: 8px;
  padding: 20px;
  margin: 15px 0;
  border: 2px solid transparent;
  transition: var(--transition);
}

.course-select-wrapper.show {
  border-color: #667eea;
  background: rgba(102, 126, 234, 0.08);
}

.file-upload-area {
  border: 2px dashed #e9ecef;
  border-radius: var(--border-radius-sm);
  padding: 30px 20px;
  text-align: center;
  transition: var(--transition);
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.02) 0%, rgba(118, 75, 162, 0.02) 100%);
  position: relative;
  overflow: hidden;
}

.file-upload-area::before {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: conic-gradient(from 0deg, transparent, rgba(102, 126, 234, 0.05), transparent);
  transition: var(--transition-slow);
  opacity: 0;
}

.file-upload-area:hover::before {
  opacity: 1;
  animation: rotate 4s linear infinite;
}

@keyframes rotate {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.file-upload-area:hover {
  border-color: #667eea;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.05) 100%);
  transform: translateY(-2px);
  box-shadow: var(--shadow-light);
}

.file-upload-area.dragover {
  border-color: #667eea;
  background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.1) 100%);
  transform: scale(1.02);
  box-shadow: var(--shadow-medium);
}

.file-upload-content {
  position: relative;
  z-index: 1;
}

.file-upload-content i {
  transition: var(--transition);
}

.file-upload-area:hover .file-upload-content i {
  transform: scale(1.1);
  color: #667eea !important;
}
</style>
</head>
<body>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h4>🎓 Student Portal</h4>
    </div>
    <a href="students-dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a>
    <a href="attendance-records.php"><i class="fas fa-calendar-check me-2"></i>Attendance Records</a>
    <a href="request-leave.php" class="active"><i class="fas fa-file-signature me-2"></i>Request Leave</a>
    <a href="leave-status.php"><i class="fas fa-info-circle me-2"></i>Leave Status</a>
    <a href="index.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
</div>

<div class="topbar d-flex justify-content-between align-items-center">
<h5 class="m-0 fw-bold">Request Leave</h5>
<span>RP Attendance System</span>
</div>

<div class="main-content">

<div class="content-section">
    <div class="content-section-header">
        <h2 class="content-section-title">
            <i class="fas fa-file-signature me-3 text-primary"></i>Request New Leave
        </h2>
        <p class="content-section-subtitle">
            Submit a new leave request with all necessary details and supporting documentation
        </p>
    </div>

    <?php if($errorMsg): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?= $errorMsg ?>
        </div>
    <?php endif; ?>

    <?php if($successMsg): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $successMsg ?>
        </div>
    <?php endif; ?>

    <form id="leaveRequestForm" method="post" action="submit-leave.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">

        <!-- Request Details Section -->
        <div class="form-section">
            <h4 class="form-section-title">
                <i class="fas fa-info-circle me-2"></i>Request Details
            </h4>

            <div class="row">
                <div class="col-lg-6 mb-4">
                    <label for="requestTo" class="form-label">
                        <i class="fas fa-user-tie me-2 text-primary"></i>Request To
                    </label>
                    <select id="requestTo" name="requestTo" class="form-select" required>
                        <option value="">-- Select Recipient --</option>
                        <option value="hod">Head of Department</option>
                        <option value="lecturer">Lecturer</option>
                    </select>
                </div>

                <div class="col-lg-6 mb-4">
                    <label for="courseId" class="form-label">
                        <i class="fas fa-book me-2 text-primary"></i>Select Course
                    </label>
                    <select id="courseId" name="courseId" class="form-select">
                        <option value="">-- Select Course --</option>
                        <?php if (count($courses) > 0): ?>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['name']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No courses available</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Date Range Section -->
        <div class="form-section">
            <h4 class="form-section-title">
                <i class="fas fa-calendar-alt me-2"></i>Date Range
            </h4>

            <div class="row">
                <div class="col-md-6 mb-4">
                    <label for="fromDate" class="form-label">
                        <i class="fas fa-calendar-plus me-2 text-primary"></i>From Date
                    </label>
                    <input type="date" id="fromDate" name="fromDate" class="form-control" required />
                </div>

                <div class="col-md-6 mb-4">
                    <label for="toDate" class="form-label">
                        <i class="fas fa-calendar-minus me-2 text-primary"></i>To Date
                    </label>
                    <input type="date" id="toDate" name="toDate" class="form-control" required />
                </div>
            </div>
        </div>

        <!-- Reason Section -->
        <div class="form-section">
            <h4 class="form-section-title">
                <i class="fas fa-comment-alt me-2"></i>Reason & Documentation
            </h4>

            <div class="mb-4">
                <label for="reason" class="form-label">
                    <i class="fas fa-pen me-2 text-primary"></i>Reason for Leave
                </label>
                <textarea id="reason" name="reason" class="form-control"
                          rows="5" maxlength="500"
                          placeholder="Please provide a detailed reason for your leave request..."
                          required></textarea>
                <div class="character-count">
                    <small id="reasonCount" class="text-muted">0/500 characters</small>
                </div>
            </div>

            <div class="mb-4">
                <label for="supportingFile" class="form-label">
                    <i class="fas fa-paperclip me-2 text-primary"></i>Supporting Document (Optional)
                </label>
                <div class="file-upload-area" id="fileUploadArea">
                    <input type="file" id="supportingFile" name="supportingFile"
                           class="d-none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                    <div class="file-upload-content">
                        <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-3"></i>
                        <p class="mb-2">Drag & drop your file here or <span class="text-primary">browse</span></p>
                        <small class="text-muted">Maximum file size: 5MB • Allowed formats: PDF, DOC, DOCX, JPG, PNG</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Section -->
        <div class="form-section">
            <div class="text-center">
                <button type="submit" id="submitBtn" class="btn btn-primary btn-lg">
                    <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                    <i class="fas fa-paper-plane me-2"></i>Submit Leave Request
                </button>
            </div>
        </div>
    </form>
</div>
<div class="content-section">
    <div class="content-section-header">
        <h2 class="content-section-title">
            <i class="fas fa-history me-3 text-secondary"></i>Leave Request History
        </h2>
        <p class="content-section-subtitle">
            View and track your recent leave request submissions and their current status
        </p>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-header-custom">
                <tr>
                    <th scope="col" class="text-center" style="width: 50px; min-width: 50px;">
                        <i class="fas fa-hashtag me-1"></i>#
                    </th>
                    <th scope="col" style="min-width: 200px;">
                        <i class="fas fa-comment-alt me-2"></i>Reason
                    </th>
                    <th scope="col" class="text-center" style="width: 110px; min-width: 110px;">
                        <i class="fas fa-calendar-plus me-1"></i>From Date
                    </th>
                    <th scope="col" class="text-center" style="width: 110px; min-width: 110px;">
                        <i class="fas fa-calendar-minus me-1"></i>To Date
                    </th>
                    <th scope="col" class="text-center" style="width: 90px; min-width: 90px;">
                        <i class="fas fa-paperclip me-1"></i>Document
                    </th>
                    <th scope="col" class="text-center" style="width: 100px; min-width: 100px;">
                        <i class="fas fa-info-circle me-1"></i>Status
                    </th>
                    <th scope="col" class="text-center" style="width: 130px; min-width: 130px;">
                        <i class="fas fa-clock me-1"></i>Requested At
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($leave_requests) > 0): ?>
                    <?php foreach($leave_requests as $i => $req): ?>
                        <tr>
                            <td class="text-center fw-bold text-primary">
                                <?= $i + 1 ?>
                            </td>
                            <td>
                                <div class="reason-text">
                                    <?php
                                    // Extract main reason (before the structured details)
                                    $reason_text = $req['reason'];

                                    // Handle both single line and multi-line formats
                                    if (strpos($reason_text, '-- Details --') !== false) {
                                        $reason_parts = explode('-- Details --', $reason_text);
                                        $main_reason = trim($reason_parts[0]);
                                    } else {
                                        // Fallback: extract first line or first part before date
                                        $lines = explode("\n", $reason_text);
                                        $main_reason = trim($lines[0]);
                                        if (empty($main_reason)) {
                                            $main_reason = 'Leave Request';
                                        }
                                    }

                                    // Fix common typos and clean up text
                                    $main_reason = htmlspecialchars($main_reason);
                                    $main_reason = str_replace('familly', 'Family', $main_reason);

                                    // Truncate if too long
                                    if(strlen($main_reason) > 60) {
                                        echo substr($main_reason, 0, 60) . '...';
                                    } else {
                                        echo $main_reason;
                                    }
                                    ?>
                                </div>
                            </td>
                            <td class="text-center">
                                <span class="date-badge">
                                    <?php
                                    // Extract from date - handle multiple formats
                                    $from_date = 'Not set';
                                    $from_date_clean = '';

                                    if (preg_match('/From:\s*([^\n\r-]+)/', $reason_text, $matches)) {
                                        $from_date_clean = trim($matches[1]);
                                    } elseif (preg_match('/From:\s*([^\n\r]+)/', $reason_text, $matches)) {
                                        $from_date_clean = trim($matches[1]);
                                    }

                                    if (!empty($from_date_clean)) {
                                        $timestamp = strtotime($from_date_clean);
                                        if ($timestamp !== false) {
                                            $from_date = date("d M Y", $timestamp);
                                        } else {
                                            $from_date = 'Invalid';
                                        }
                                    }
                                    echo $from_date;
                                    ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="date-badge">
                                    <?php
                                    // Extract to date - handle multiple formats
                                    $to_date = 'Not set';
                                    $to_date_clean = '';

                                    if (preg_match('/To:\s*([^\n\r-]+)/', $reason_text, $matches)) {
                                        $to_date_clean = trim($matches[1]);
                                    } elseif (preg_match('/To:\s*([^\n\r]+)/', $reason_text, $matches)) {
                                        $to_date_clean = trim($matches[1]);
                                    }

                                    if (!empty($to_date_clean)) {
                                        $timestamp = strtotime($to_date_clean);
                                        if ($timestamp !== false) {
                                            $to_date = date("d M Y", $timestamp);
                                        } else {
                                            $to_date = 'Invalid';
                                        }
                                    }
                                    echo $to_date;
                                    ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if($req['supporting_file']): ?>
                                    <a href="uploads/leave_docs/<?= $req['supporting_file'] ?>"
                                       target="_blank"
                                       class="btn btn-sm btn-outline-primary"
                                       title="View Document">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                <?php else: ?>
                                    <span class="document-placeholder">
                                        <i class="fas fa-file-upload text-muted me-1"></i>
                                        <small class="text-muted">No file</small>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if($req['status'] == 'pending'): ?>
                                    <span class="status-badge status-pending">
                                        <i class="fas fa-clock me-1"></i>Pending
                                    </span>
                                <?php elseif($req['status'] == 'approved'): ?>
                                    <span class="status-badge status-approved">
                                        <i class="fas fa-check me-1"></i>Approved
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-rejected">
                                        <i class="fas fa-times me-1"></i>Rejected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="request-time">
                                    <div class="request-date">
                                        <?= date("d M Y", strtotime($req['requested_at'])) ?>
                                    </div>
                                    <div class="request-time-detail">
                                        <?= date("H:i", strtotime($req['requested_at'])) ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-inbox fa-4x text-muted mb-4"></i>
                                </div>
                                <h5 class="empty-state-title">No Leave Requests Found</h5>
                                <p class="empty-state-text">You haven't submitted any leave requests yet.</p>
                                <div class="empty-state-action">
                                    <a href="#leaveRequestForm" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Submit Your First Request
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Student Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const requestTo = document.getElementById('requestTo');
    const courseSelectWrapper = document.getElementById('courseSelectWrapper');
    const courseId = document.getElementById('courseId');
    const fromDate = document.getElementById('fromDate');
    const toDate = document.getElementById('toDate');
    const reason = document.getElementById('reason');
    const form = document.getElementById('leaveRequestForm');

    // Handle request type change
    requestTo.addEventListener('change', function() {
        const courseSection = document.getElementById('courseId').closest('.col-lg-6');
        if(this.value==='lecturer'){
            courseSection.style.display='block';
            courseId.setAttribute('required','required');
        } else {
            courseSection.style.display='none';
            courseId.removeAttribute('required');
            courseId.value = '';
        }
    });

    // Date validation
    function validateDates() {
        const from = new Date(fromDate.value);
        const to = new Date(toDate.value);
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        if (from < today) {
            alert('From date cannot be in the past');
            fromDate.value = '';
            return false;
        }

        if (to < from) {
            alert('To date cannot be earlier than From date');
            toDate.value = '';
            return false;
        }

        return true;
    }

    fromDate.addEventListener('change', validateDates);
    toDate.addEventListener('change', validateDates);

    // File validation
    const supportingFile = document.getElementById('supportingFile');
    supportingFile.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const maxSize = 5 * 1024 * 1024; // 5MB
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];

            if (file.size > maxSize) {
                alert('File size exceeds maximum allowed size (5MB).');
                this.value = '';
                return;
            }

            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG files are allowed.');
                this.value = '';
                return;
            }
        }
    });

    // Form validation and submission
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevent default submission for validation

        let isValid = true;
        let errorMessage = '';

        // Check required fields
        if (!requestTo.value) {
            errorMessage += 'Please select who to request leave from.\n';
            isValid = false;
        }

        if (requestTo.value === 'lecturer' && !courseId.value) {
            errorMessage += 'Please select a course.\n';
            isValid = false;
        }

        if (!fromDate.value) {
            errorMessage += 'Please select a From date.\n';
            isValid = false;
        }

        if (!toDate.value) {
            errorMessage += 'Please select a To date.\n';
            isValid = false;
        }

        if (!reason.value.trim()) {
            errorMessage += 'Please enter a reason for leave.\n';
            isValid = false;
        }

        // Validate dates
        if (fromDate.value && toDate.value && !validateDates()) {
            isValid = false;
        }

        if (!isValid) {
            alert(errorMessage);
            return;
        }

        // Show loading state
        const submitBtn = document.getElementById('submitBtn');
        const spinner = submitBtn.querySelector('.spinner-border');
        const btnText = submitBtn.querySelector('i + *') || submitBtn.lastChild;

        submitBtn.disabled = true;
        spinner.classList.remove('d-none');
        btnText.textContent = 'Submitting...';

        // Submit the form
        form.submit();
    });

    // Character counter for reason field with enhanced feedback
    const reasonCount = document.getElementById('reasonCount');
    reason.addEventListener('input', function() {
        const maxLength = 500;
        const currentLength = this.value.length;
        const percentage = (currentLength / maxLength) * 100;

        reasonCount.textContent = `${currentLength}/500 characters`;

        // Remove existing classes
        reasonCount.classList.remove('warning', 'danger');

        // Add color coding based on character count
        if (percentage > 90) {
            reasonCount.classList.add('danger');
        } else if (percentage > 75) {
            reasonCount.classList.add('warning');
        }

        if (currentLength > maxLength) {
            this.value = this.value.substring(0, maxLength);
            reasonCount.textContent = `${maxLength}/500 characters`;
            reasonCount.classList.add('danger');
        }
    });

    // File upload enhancements
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('supportingFile');
    const fileUploadContent = fileUploadArea.querySelector('.file-upload-content');

    // Click to browse files
    fileUploadArea.addEventListener('click', function(e) {
        if (e.target !== fileInput) {
            fileInput.click();
        }
    });

    // Drag and drop functionality
    fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        fileUploadArea.classList.add('dragover');
    });

    fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');
    });

    fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        fileUploadArea.classList.remove('dragover');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileDisplay(files[0]);
        }
    });

    // File input change
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            updateFileDisplay(this.files[0]);
        }
    });

    function updateFileDisplay(file) {
        const fileName = file.name;
        const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
        const fileExtension = fileName.split('.').pop().toLowerCase();
        const fileIcon = getFileIcon(fileExtension);

        fileUploadContent.innerHTML = `
            <div class="file-preview">
                <i class="${fileIcon} fa-3x text-success mb-3"></i>
                <h6 class="file-name">${fileName}</h6>
                <p class="file-size">Size: ${fileSize}</p>
                <div class="progress mt-3" style="height: 4px;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                         role="progressbar" style="width: 100%"></div>
                </div>
            </div>
        `;

        // Add remove button with enhanced styling
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger mt-3';
        removeBtn.innerHTML = '<i class="fas fa-times me-1"></i>Remove File';
        removeBtn.onclick = function() {
            fileInput.value = '';
            resetFileUploadArea();
        };

        fileUploadContent.appendChild(removeBtn);
    }

    function getFileIcon(extension) {
        const icons = {
            'pdf': 'fas fa-file-pdf',
            'doc': 'fas fa-file-word',
            'docx': 'fas fa-file-word',
            'jpg': 'fas fa-file-image',
            'jpeg': 'fas fa-file-image',
            'png': 'fas fa-file-image'
        };
        return icons[extension] || 'fas fa-file-alt';
    }

    function resetFileUploadArea() {
        fileUploadContent.innerHTML = `
            <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-3"></i>
            <p class="mb-2">Drag & drop your file here or <span class="text-primary">browse</span></p>
            <small class="text-muted">Maximum file size: 5MB • Allowed formats: PDF, DOC, DOCX, JPG, PNG</small>
        `;
    }

    // Add loading animation to form submission
    const submitBtn = document.getElementById('submitBtn');
    const originalBtnText = submitBtn.innerHTML;

    form.addEventListener('submit', function(e) {
        // Update button to show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';

        // Re-enable button after 10 seconds as fallback
        setTimeout(function() {
            if (submitBtn.disabled) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            }
        }, 10000);
    });
});
</script>
</body>
</html>
