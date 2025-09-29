<?php
session_start();
require_once "config.php";
require_once "session_check.php";

// Ensure student is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header("Location: login.php");
    exit;
}

// Get student info
$stmt = $pdo->prepare("SELECT s.*, u.email FROM students s JOIN users u ON s.user_id = u.id WHERE s.user_id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$student) {
    header("Location: login.php");
    exit;
}

// Handle messages from redirects
$alertMessage = '';
$alertType = '';

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'csrf_invalid':
            $alertMessage = 'Security validation failed. Please try again.';
            $alertType = 'danger';
            break;
        case 'missing_fields':
            $alertMessage = 'Please fill in all required fields.';
            $alertType = 'danger';
            break;
        case 'file_too_large':
            $alertMessage = 'The uploaded file is too large. Maximum size is 5MB.';
            $alertType = 'danger';
            break;
        case 'invalid_file_type':
            $alertMessage = 'Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG files are allowed.';
            $alertType = 'danger';
            break;
        case 'upload_failed':
            $alertMessage = 'File upload failed. Please try again.';
            $alertType = 'danger';
            break;
        default:
            $alertMessage = 'An error occurred. Please try again.';
            $alertType = 'danger';
    }
}

if (isset($_GET['success'])) {
    $alertMessage = 'Leave request submitted successfully!';
    $alertType = 'success';
}

// Load real leave requests
$leaveRequests = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE student_id = ? ORDER BY requested_at DESC");
    $stmt->execute([$student['id']]);
    $leaveRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle database error silently for now
    $leaveRequests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Leave | Student | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #0066cc 0%, #003366 100%);
            --primary-gradient-hover: linear-gradient(135deg, #0052a3 0%, #002b50 100%);
            --secondary-gradient: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            --info-gradient: linear-gradient(135deg, #17a2b8 0%, #20c997 100%);
            --light-gradient: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --glass-gradient: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);

            --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.07);
            --shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.10);
            --shadow-heavy: 0 8px 32px rgba(0, 0, 0, 0.13);
            --shadow-glow: 0 0 20px rgba(0, 102, 204, 0.18);
            --shadow-inset: inset 0 2px 4px rgba(0, 0, 0, 0.07);

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
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
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
                radial-gradient(circle at 20% 80%, rgba(0, 102, 204, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(0, 51, 102, 0.15) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(23, 162, 184, 0.1) 0%, transparent 50%);
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
            background: linear-gradient(135deg, #003366 80%, #0066cc 100%);
            color: #fff;
            padding: 30px 0;
            box-shadow: var(--shadow-heavy);
            border-right: 1px solid rgba(255, 255, 255, 0.13);
            z-index: 1000;
            overflow-y: auto;
            transition: var(--transition);
        }

        .sidebar-header {
            text-align: center;
            padding: 0 20px 30px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .sidebar-header h4 {
            color: #fff;
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 1.2rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar a {
            display: block;
            padding: 15px 25px;
            color: rgba(255, 255, 255, 0.92);
            text-decoration: none;
            font-weight: 500;
            border-radius: 0 var(--border-radius-lg) var(--border-radius-lg) 0;
            margin: 8px 0;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
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
            color: #fff;
            padding-left: 35px;
            transform: translateX(8px);
            box-shadow: var(--shadow-glow);
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
            background: linear-gradient(90deg, #f8f9fa 80%, #e9ecef 100%);
            padding: 20px 30px;
            border-bottom: 1px solid #e9ecef;
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
            max-width: 100vw;
            margin-right: 0;
            /* margin-left: 0; */
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            gap: 30px;
            width: calc(100vw - var(--sidebar-width));
        }

        /* ===== CONTENT SECTIONS ===== */
        .content-section {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 32px 24px;
            box-shadow: var(--shadow-medium);
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            margin-bottom: 0;
            width: 100%;
        }

        .content-section:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-heavy);
        }

        .content-section-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 2px solid #e9ecef;
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
            color: #003366;
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
          background: #f8f9fa;
          border: 1px solid #e9ecef;
          border-radius: var(--border-radius);
          padding: 24px 16px;
          margin-bottom: 24px;
          transition: var(--transition);
          position: relative;
          box-shadow: var(--shadow-light);
          display: flex;
          flex-direction: column;
          gap: 18px;
          width: 100%;
        }
        
        .form-section:hover {
          border-color: #0066cc;
          box-shadow: var(--shadow-medium);
          transform: translateY(-2px);
        }
        
        /* ===== STEP INDICATORS ===== */
        .step-indicator {
          display: flex;
          align-items: center;
          margin-bottom: 24px;
          padding-bottom: 16px;
          border-bottom: 2px solid #f1f5f9;
        }
        
        .step-number {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 32px;
          height: 32px;
          background: var(--primary-gradient);
          color: white;
          border-radius: 50%;
          font-weight: 700;
          font-size: 0.9rem;
          margin-right: 16px;
          box-shadow: var(--shadow-sm);
        }
        
        .form-section-title {
          color: var(--dark-color);
          font-weight: 600;
          font-size: 1.25rem;
          margin: 0;
          display: flex;
          align-items: center;
        }
        
        .form-section-title i {
          color: var(--primary-color);
          margin-right: 8px;
        }
        
        /* ===== RECIPIENT SELECTION ===== */
        .recipient-selection {
          margin-top: 8px;
        }
        
        .recipient-card {
          position: relative;
          cursor: pointer;
          transition: var(--transition);
        }
        
        .recipient-card:hover {
          transform: translateY(-2px);
        }
        
        .recipient-label {
          display: flex;
          align-items: center;
          padding: 20px;
          border: 2px solid #e2e8f0;
          border-radius: var(--border-radius);
          background: #ffffff;
          transition: var(--transition);
          cursor: pointer;
          margin: 0;
          width: 100%;
          position: relative;
        }
        
        .recipient-card:hover .recipient-label {
          border-color: var(--primary-color);
          box-shadow: var(--shadow-md);
        }
        
        .recipient-card input[type="radio"]:checked + .recipient-label {
          border-color: var(--primary-color);
          background: rgba(0, 102, 204, 0.02);
          box-shadow: var(--shadow-md);
        }
        
        .recipient-icon {
          margin-right: 16px;
          display: flex;
          align-items: center;
          justify-content: center;
          width: 48px;
          height: 48px;
          background: rgba(0, 102, 204, 0.08);
          border-radius: var(--border-radius);
        }
        
        .recipient-content h6 {
          color: var(--dark-color);
          font-weight: 600;
          margin-bottom: 4px;
        }
        
        .recipient-content small {
          color: #64748b;
          font-size: 0.85rem;
        }
        
        .recipient-radio {
          margin-left: auto;
          color: #cbd5e1;
          transition: var(--transition);
        }
        
        .recipient-card.active .recipient-label {
          border-color: var(--primary-color);
          background: rgba(0, 102, 204, 0.02);
          box-shadow: var(--shadow-md);
        }
        
        .recipient-card.active .recipient-radio {
          color: var(--primary-color);
        }
        
        .recipient-card.active .recipient-radio i:before {
          content: '\f111'; /* fas fa-dot-circle */
        }
        
        /* ===== COURSE SELECTION ===== */
        .course-selection-wrapper {
          background: #f8fafc;
          border: 1px solid #e2e8f0;
          border-radius: var(--border-radius);
          padding: 20px;
          margin-top: 16px;
          animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
          from {
            opacity: 0;
            transform: translateY(-10px);
          }
          to {
            opacity: 1;
            transform: translateY(0);
          }
        }
        
        /* ===== DATE INPUTS ===== */
        .date-input-wrapper {
          position: relative;
        }
        
        .date-input-wrapper .form-text {
          margin-top: 4px;
          font-size: 0.8rem;
        }
        
        /* ===== LEAVE DURATION ===== */
        .leave-duration {
          margin-top: 16px;
        }
        
        .leave-duration .alert {
          border: none;
          border-radius: var(--border-radius);
          padding: 12px 16px;
        }
        
        /* ===== REASON INPUT ===== */
        .reason-input-wrapper {
          position: relative;
        }
        
        .reason-input-wrapper .form-text {
          margin-top: 8px;
          font-size: 0.85rem;
          color: #64748b;
        }
        
        /* ===== DOCUMENT UPLOAD ===== */
        .document-upload-wrapper {
          position: relative;
        }
        
        .file-requirements {
          margin-top: 12px;
        }
        
        /* ===== SUBMIT SECTION ===== */
        .submit-section {
          text-align: center;
          padding: 16px 0;
        }
        
        .submit-card {
          background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
          border: 1px solid #e2e8f0;
          border-radius: var(--border-radius);
          padding: 32px 24px;
          box-shadow: var(--shadow-sm);
        }
        
        .submit-icon {
          margin-bottom: 16px;
        }
        
        .submit-title {
          color: var(--dark-color);
          font-weight: 600;
          margin-bottom: 8px;
        }
        
        .submit-description {
          color: #64748b;
          font-size: 0.9rem;
          margin-bottom: 24px;
          line-height: 1.5;
        }
        
        .submit-card .form-text {
          color: #94a3b8;
          font-size: 0.8rem;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 6px;
            font-size: 0.97rem;
            display: flex;
            align-items: center;
            transition: var(--transition-fast);
        }

        .form-label:hover {
            color: #0066cc;
        }

        .form-control, .form-select {
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius-sm);
            padding: 12px 16px;
            font-size: 0.97rem;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.97);
            font-family: var(--font-primary);
            position: relative;
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
        }
            flex: 1 1 300px;
            min-width: 220px;
            max-width: 100%;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-control:hover, .form-select:hover {
            border-color: #0066cc;
            transform: translateY(-1px);
        }

        .form-control:focus, .form-select:focus {
            border-color: #0066cc;
            box-shadow: 0 0 0 0.2rem rgba(0, 102, 204, 0.15), var(--shadow-light);
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
            border: 2px solid #0066cc;
            color: #0066cc;
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

        .table tbody td {
            padding: 18px 15px;
            border: none;
            border-bottom: 1px solid rgba(0, 102, 204, 0.06);
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

        .table tbody tr:hover {
            background: linear-gradient(135deg, rgba(0, 102, 204, 0.06) 0%, rgba(0, 51, 102, 0.04) 100%);
            transform: translateY(-1px) translateX(2px);
            box-shadow: 0 4px 12px rgba(0, 102, 204, 0.15);
            border-left: 3px solid #0066cc;
        }

        .table tbody tr:hover td {
            color: #2c3e50;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.9);
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
            color: #0066cc;
            -webkit-line-clamp: 4;
        }

        /* ===== DATE BADGE STYLING ===== */
        .date-badge {
            background: linear-gradient(135deg, rgba(0, 102, 204, 0.08) 0%, rgba(0, 51, 102, 0.06) 100%);
            color: #0066cc;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
            min-width: 95px;
            max-width: 115px;
            text-align: center;
            border: 1px solid rgba(0, 102, 204, 0.15);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            box-shadow: 0 2px 4px rgba(0, 102, 204, 0.08);
            transition: var(--transition-fast);
            position: relative;
        }

        .date-badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 102, 204, 0.15);
            border-color: rgba(0, 102, 204, 0.25);
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
            color: #0066cc;
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

        /* ===== TABLE CONTAINER ===== */
        .table-responsive {
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-medium);
            background: white;
            overflow-x: auto;
            overflow-y: visible;
            -webkit-overflow-scrolling: touch;
            border: 1px solid rgba(0, 102, 204, 0.1);
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

        /* ===== EMPTY STATE ===== */
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

        .btn-primary:hover {
            transform: translateY(-3px) scale(1.02);
            box-shadow: var(--shadow-glow);
            background: var(--primary-gradient-hover);
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

        /* ===== FILE UPLOAD ENHANCEMENTS ===== */
        .file-upload-area {
            border: 2px dashed #e9ecef;
            border-radius: var(--border-radius-sm);
            padding: 30px 20px;
            text-align: center;
            transition: var(--transition);
            background: linear-gradient(135deg, rgba(0, 102, 204, 0.02) 0%, rgba(0, 51, 102, 0.02) 100%);
            position: relative;
            overflow: hidden;
        }

        .file-upload-area:hover {
            border-color: #0066cc;
            background: linear-gradient(135deg, rgba(0, 102, 204, 0.08) 0%, rgba(0, 51, 102, 0.05) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-light);
        }

        .file-upload-area.dragover {
            border-color: #0066cc;
            background: linear-gradient(135deg, rgba(0, 102, 204, 0.15) 0%, rgba(0, 51, 102, 0.1) 100%);
            transform: scale(1.02);
            box-shadow: var(--shadow-medium);
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
        }

        @media (max-width: 576px) {
            .content-section {
                padding: 20px 15px;
            }

            .content-section-title {
                font-size: 1.2rem;
            }

            .form-section {
                padding: 20px 16px;
            }

            .step-indicator {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .step-number {
                align-self: flex-start;
            }

            .recipient-label {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }

            .recipient-icon {
                margin-right: 0;
                margin-bottom: 8px;
            }

            .recipient-content {
                text-align: center;
            }

            .recipient-radio {
                position: absolute;
                top: 12px;
                right: 12px;
            }

            .course-selection-wrapper {
                padding: 16px;
            }

            .submit-card {
                padding: 24px 16px;
            }

            .submit-title {
                font-size: 1.1rem;
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

        <div id="alertContainer">
            <?php if ($alertMessage): ?>
                <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $alertType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($alertMessage); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>

        <form id="leaveRequestForm" method="post" action="submit-leave.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <!-- Step 1: Request Type & Recipient -->
            <div class="form-section">
                <div class="step-indicator">
                    <span class="step-number">1</span>
                    <h4 class="form-section-title mb-0">
                        <i class="fas fa-user-tie me-2"></i>Who are you requesting leave from?
                    </h4>
                </div>

                <div class="row g-4">
                    <div class="col-12">
                        <div class="recipient-selection">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="recipient-card" data-value="hod">
                                        <input type="radio" id="requestToHod" name="requestTo" value="hod" class="d-none" required>
                                        <label for="requestToHod" class="recipient-label">
                                            <div class="recipient-icon">
                                                <i class="fas fa-user-tie fa-2x text-primary"></i>
                                            </div>
                                            <div class="recipient-content">
                                                <h6 class="mb-1">Head of Department</h6>
                                                <small class="text-muted">Request leave from your department head</small>
                                            </div>
                                            <div class="recipient-radio">
                                                <i class="fas fa-circle"></i>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="recipient-card" data-value="lecturer">
                                        <input type="radio" id="requestToLecturer" name="requestTo" value="lecturer" class="d-none">
                                        <label for="requestToLecturer" class="recipient-label">
                                            <div class="recipient-icon">
                                                <i class="fas fa-chalkboard-teacher fa-2x text-info"></i>
                                            </div>
                                            <div class="recipient-content">
                                                <h6 class="mb-1">Course Lecturer</h6>
                                                <small class="text-muted">Request leave from a specific course lecturer</small>
                                            </div>
                                            <div class="recipient-radio">
                                                <i class="fas fa-circle"></i>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Course Selection (Hidden by default) -->
                    <div class="col-12" id="courseSelectionContainer" style="display: none;">
                        <div class="course-selection-wrapper">
                            <label for="courseId" class="form-label">
                                <i class="fas fa-book me-2 text-primary"></i>Which course?
                            </label>
                            <select id="courseId" name="courseId" class="form-select">
                                <option value="">-- Select Course --</option>
                                <option value="1">Computer Science 101</option>
                                <option value="2">Mathematics for Computing</option>
                                <option value="3">Database Systems</option>
                                <option value="4">Web Development</option>
                                <option value="5">Software Engineering</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Date Range -->
            <div class="form-section">
                <div class="step-indicator">
                    <span class="step-number">2</span>
                    <h4 class="form-section-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>When do you need leave?
                    </h4>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="date-input-wrapper">
                            <label for="fromDate" class="form-label">
                                <i class="fas fa-calendar-plus me-2 text-primary"></i>From Date
                            </label>
                            <input type="date" id="fromDate" name="fromDate" class="form-control" required />
                            <small class="form-text text-muted">First day of leave</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="date-input-wrapper">
                            <label for="toDate" class="form-label">
                                <i class="fas fa-calendar-minus me-2 text-primary"></i>To Date
                            </label>
                            <input type="date" id="toDate" name="toDate" class="form-control" required />
                            <small class="form-text text-muted">Last day of leave</small>
                        </div>
                    </div>
                </div>

                <div class="leave-duration mt-3" id="leaveDuration" style="display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Duration:</strong> <span id="durationText"></span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Reason for Leave -->
            <div class="form-section">
                <div class="step-indicator">
                    <span class="step-number">3</span>
                    <h4 class="form-section-title mb-0">
                        <i class="fas fa-comment-alt me-2"></i>Why do you need leave?
                    </h4>
                </div>

                <div class="reason-input-wrapper">
                    <label for="reason" class="form-label">
                        <i class="fas fa-pen me-2 text-primary"></i>Detailed Reason
                    </label>
                    <textarea id="reason" name="reason" class="form-control"
                              rows="6" maxlength="500"
                              placeholder="Please provide a detailed explanation for your leave request. Include any relevant context that will help your request be approved..."
                              required></textarea>
                    <div class="character-count">
                        <small id="reasonCount" class="text-muted">0/500 characters</small>
                    </div>
                    <small class="form-text text-muted mt-2">
                        <i class="fas fa-lightbulb me-1"></i>
                        Tip: Be specific about your reason and how long you need. This helps in faster approval.
                    </small>
                </div>
            </div>

            <!-- Step 4: Supporting Documents -->
            <div class="form-section">
                <div class="step-indicator">
                    <span class="step-number">4</span>
                    <h4 class="form-section-title mb-0">
                        <i class="fas fa-paperclip me-2"></i>Supporting Documents (Optional)
                    </h4>
                </div>

                <div class="document-upload-wrapper">
                    <p class="text-muted mb-3">
                        Upload any supporting documents that strengthen your leave request (medical certificates, event invitations, etc.)
                    </p>

                    <div class="file-upload-area" id="fileUploadArea">
                        <input type="file" id="supportingFile" name="supportingFile"
                               class="d-none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" />
                        <div class="file-upload-content">
                            <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                            <h6 class="mb-2">Drag & drop your file here</h6>
                            <p class="mb-2 text-muted">or <span class="text-primary fw-bold">browse files</span></p>
                            <div class="file-requirements">
                                <small class="text-muted d-block">• Maximum file size: 5MB</small>
                                <small class="text-muted d-block">• Allowed formats: PDF, DOC, DOCX, JPG, PNG</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Section -->
            <div class="form-section">
                <div class="submit-section">
                    <div class="row">
                        <div class="col-lg-8 mx-auto">
                            <div class="submit-card">
                                <div class="submit-icon">
                                    <i class="fas fa-paper-plane fa-2x text-primary"></i>
                                </div>
                                <h5 class="submit-title">Ready to Submit?</h5>
                                <p class="submit-description">
                                    Please review your request details before submitting. You can track the status of your request in the history section below.
                                </p>
                                <button type="submit" id="submitBtn" class="btn btn-primary btn-lg w-100">
                                    <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                                    <i class="fas fa-paper-plane me-2"></i>Submit Leave Request
                                </button>
                                <small class="form-text text-muted text-center mt-2">
                                    <i class="fas fa-clock me-1"></i>
                                    Requests are typically reviewed within 24-48 hours
                                </small>
                            </div>
                        </div>
                    </div>
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
                <tbody id="leaveRequestsTableBody">
                    <!-- Leave requests will be dynamically inserted here -->
                </tbody>
            </table>
        </div>
    </div>

</div>

<div class="footer">&copy; 2025 Rwanda Polytechnic | Student Panel</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize the page
        initializePage();
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('fromDate').min = today;
        document.getElementById('toDate').min = today;
        
        // Load leave requests data
        loadLeaveRequests();
    });

    function initializePage() {
        const recipientCards = document.querySelectorAll('.recipient-card');
        const courseId = document.getElementById('courseId');
        const courseSelectionContainer = document.getElementById('courseSelectionContainer');
        const fromDate = document.getElementById('fromDate');
        const toDate = document.getElementById('toDate');
        const reason = document.getElementById('reason');
        const form = document.getElementById('leaveRequestForm');
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('supportingFile');
        const leaveDuration = document.getElementById('leaveDuration');
        const durationText = document.getElementById('durationText');

        // Handle recipient selection
        recipientCards.forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                const value = this.dataset.value;

                // Remove active class from all cards
                recipientCards.forEach(c => c.classList.remove('active'));
                // Add active class to clicked card
                this.classList.add('active');

                // Check the radio button
                radio.checked = true;

                // Show/hide course selection
                if (value === 'lecturer') {
                    courseSelectionContainer.style.display = 'block';
                    courseId.setAttribute('required', 'required');
                } else {
                    courseSelectionContainer.style.display = 'none';
                    courseId.removeAttribute('required');
                    courseId.value = '';
                }
            });
        });

        // Date validation and duration calculation
        function validateDates() {
            const from = new Date(fromDate.value);
            const to = new Date(toDate.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (from < today) {
                showAlert('From date cannot be in the past', 'danger');
                fromDate.value = '';
                hideLeaveDuration();
                return false;
            }

            if (to < from) {
                showAlert('To date cannot be earlier than From date', 'danger');
                toDate.value = '';
                hideLeaveDuration();
                return false;
            }

            calculateLeaveDuration();
            return true;
        }

        function calculateLeaveDuration() {
            if (!fromDate.value || !toDate.value) {
                hideLeaveDuration();
                return;
            }

            const from = new Date(fromDate.value);
            const to = new Date(toDate.value);
            const diffTime = Math.abs(to - from);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both start and end dates

            if (diffDays === 1) {
                durationText.textContent = '1 day';
            } else {
                durationText.textContent = `${diffDays} days`;
            }

            leaveDuration.style.display = 'block';
        }

        function hideLeaveDuration() {
            leaveDuration.style.display = 'none';
        }

        fromDate.addEventListener('change', function() {
            if (fromDate.value) {
                // Update toDate min value
                toDate.min = fromDate.value;
                // Clear toDate if it's before fromDate
                if (toDate.value && new Date(toDate.value) < new Date(fromDate.value)) {
                    toDate.value = '';
                    hideLeaveDuration();
                }
            }

            if (fromDate.value && toDate.value) {
                validateDates();
            } else if (fromDate.value) {
                hideLeaveDuration();
            }
        });

        toDate.addEventListener('change', function() {
            if (fromDate.value && toDate.value) {
                validateDates();
            } else {
                hideLeaveDuration();
            }
        });

        // File upload functionality
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
                const file = this.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['application/pdf', 'application/msword', 
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 
                    'image/jpeg', 'image/png'];

                if (file.size > maxSize) {
                    showAlert('File size exceeds maximum allowed size (5MB).', 'danger');
                    this.value = '';
                    return;
                }

                if (!allowedTypes.includes(file.type)) {
                    showAlert('Invalid file type. Only PDF, DOC, DOCX, JPG, and PNG files are allowed.', 'danger');
                    this.value = '';
                    return;
                }

                updateFileDisplay(file);
            }
        });

        // Character counter for reason field
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

        // Form submission
        form.addEventListener('submit', function(e) {
            if (!validateForm()) {
                e.preventDefault();
                return;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const spinner = submitBtn.querySelector('.spinner-border');
            const btnText = submitBtn.querySelector('i + *') || submitBtn.lastChild;

            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            btnText.textContent = 'Submitting...';

            // Let the form submit normally to submit-leave.php
            // The page will redirect back with success/error messages
        });
    }

    function validateForm() {
        const requestTo = document.querySelector('input[name="requestTo"]:checked');
        const courseId = document.getElementById('courseId');
        const fromDate = document.getElementById('fromDate');
        const toDate = document.getElementById('toDate');
        const reason = document.getElementById('reason');

        let isValid = true;
        let errorMessage = '';

        // Check required fields
        if (!requestTo) {
            errorMessage += 'Please select who to request leave from.<br>';
            isValid = false;
        }

        if (requestTo && requestTo.value === 'lecturer' && !courseId.value) {
            errorMessage += 'Please select a course.<br>';
            isValid = false;
        }

        if (!fromDate.value) {
            errorMessage += 'Please select a From date.<br>';
            isValid = false;
        }

        if (!toDate.value) {
            errorMessage += 'Please select a To date.<br>';
            isValid = false;
        }

        if (!reason.value.trim()) {
            errorMessage += 'Please enter a reason for leave.<br>';
            isValid = false;
        }

        // Validate dates
        if (fromDate.value && toDate.value && !validateDates()) {
            isValid = false;
        }

        if (!isValid) {
            showAlert(errorMessage, 'danger');
        }

        return isValid;
    }


    function updateFileDisplay(file) {
        const fileUploadContent = document.querySelector('.file-upload-content');
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

        // Add remove button
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-sm btn-outline-danger mt-3';
        removeBtn.innerHTML = '<i class="fas fa-times me-1"></i>Remove File';
        removeBtn.onclick = function() {
            document.getElementById('supportingFile').value = '';
            resetFileUploadArea();
        };

        fileUploadContent.appendChild(removeBtn);
    }

    function resetFileUploadArea() {
        const fileUploadContent = document.querySelector('.file-upload-content');
        fileUploadContent.innerHTML = `
            <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-3"></i>
            <p class="mb-2">Drag & drop your file here or <span class="text-primary">browse</span></p>
            <small class="text-muted">Maximum file size: 5MB • Allowed formats: PDF, DOC, DOCX, JPG, PNG</small>
        `;
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

    function showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alertClass = type === 'danger' ? 'alert-danger' : 'alert-success';
        const iconClass = type === 'danger' ? 'fa-exclamation-triangle' : 'fa-check-circle';
        
        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show`;
        alert.innerHTML = `
            <i class="fas ${iconClass} me-2"></i>${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        alertContainer.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    function loadLeaveRequests() {
        const leaveData = <?php echo json_encode($leaveRequests); ?>;
        const tableBody = document.getElementById('leaveRequestsTableBody');

        if (leaveData.length === 0) {
            tableBody.innerHTML = `
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
            `;
            return;
        }

        tableBody.innerHTML = leaveData.map((req, index) => {
            const reasonText = extractMainReason(req.reason);
            const fromDate = extractDate(req.reason, 'From');
            const toDate = extractDate(req.reason, 'To');

            return `
                <tr>
                    <td class="text-center fw-bold text-primary">${index + 1}</td>
                    <td>
                        <div class="reason-text">${reasonText}</div>
                    </td>
                    <td class="text-center">
                        <span class="date-badge">${fromDate}</span>
                    </td>
                    <td class="text-center">
                        <span class="date-badge">${toDate}</span>
                    </td>
                    <td class="text-center">
                        ${req.supporting_file ?
                            `<a href="uploads/leave_docs/${req.supporting_file}" target="_blank" class="btn btn-sm btn-outline-primary" title="View Document">
                                <i class="fas fa-eye me-1"></i>View
                            </a>` :
                            `<span class="document-placeholder">
                                <i class="fas fa-file-upload text-muted me-1"></i>
                                <small class="text-muted">No file</small>
                            </span>`
                        }
                    </td>
                    <td class="text-center">
                        <span class="status-badge status-${req.status}">
                            <i class="fas ${getStatusIcon(req.status)} me-1"></i>${req.status.charAt(0).toUpperCase() + req.status.slice(1)}
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="request-time">
                            <div class="request-date">${formatDate(req.requested_at)}</div>
                            <div class="request-time-detail">${formatTime(req.requested_at)}</div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }


    function extractMainReason(reason) {
        if (reason.includes('-- Details --')) {
            return reason.split('-- Details --')[0].trim();
        }
        return reason.length > 60 ? reason.substring(0, 60) + '...' : reason;
    }

    function extractDate(reason, type) {
        const regex = new RegExp(`${type}:\\s*([^\\n\\r-]+)`);
        const match = reason.match(regex);
        if (match && match[1]) {
            const date = new Date(match[1].trim());
            return date.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
        }
        return 'Not set';
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function formatTime(dateString) {
        const date = new Date(dateString);
        return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function getStatusIcon(status) {
        const icons = {
            'pending': 'fa-clock',
            'approved': 'fa-check',
            'rejected': 'fa-times'
        };
        return icons[status] || 'fa-question';
    }
</script>
</body>
</html>