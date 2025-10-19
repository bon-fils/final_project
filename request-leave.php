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
            --sidebar-width: 250px;
            --primary-color: #0066cc;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --dark-color: #2c3e50;
        }

        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background-color: #343a40;
            color: white;
            padding: 20px 0;
        }

        .sidebar a {
            display: block;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
        }

        .sidebar a.active {
            background-color: #007bff;
        }

        .topbar {
            margin-left: var(--sidebar-width);
            background-color: white;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .content-section {
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .content-section-title {
            color: #007bff;
            margin-bottom: 15px;
            font-size: 1.5rem;
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
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
        }

        .form-control, .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-control:focus, .form-select:focus {
            border-color: #007bff;
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .character-count {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .character-count.text-warning {
            color: #ffc107;
        }

        .character-count.text-danger {
            color: #dc3545;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-primary:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .bg-warning {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        .bg-success {
            background-color: #28a745 !important;
            color: white !important;
        }

        .bg-danger {
            background-color: #dc3545 !important;
            color: white !important;
        }

        .bg-secondary {
            background-color: #6c757d !important;
            color: white !important;
        }

        .btn-group {
            display: flex;
            gap: 2px;
        }

        .btn-group .btn {
            border-radius: 4px !important;
            margin: 0;
        }

        .btn-group .btn-outline-primary {
            border-color: #007bff;
            color: #007bff;
        }

        .btn-group .btn-outline-primary:hover {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-group .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }

        .btn-group .btn-outline-danger:hover {
            background-color: #dc3545;
            border-color: #dc3545;
        }

        .btn-outline-warning {
            border-color: #ffc107;
            color: #ffc107;
        }

        .btn-outline-warning:hover {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .footer {
            text-align: center;
            margin-left: var(--sidebar-width);
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }

            .topbar, .main-content, .footer {
                margin-left: 0;
            }

            .main-content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<?php
// Include student sidebar for consistent navigation
$student_name = htmlspecialchars(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
$student = $student ?? ['department_name' => 'Department'];
include 'includes/student_sidebar.php';
?>

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
            <input type="hidden" id="updateRequestId" name="update_request_id" value="">

            <div class="row g-3">
                <!-- Request To -->
                <div class="col-md-6">
                    <label for="requestTo" class="form-label">Request To</label>
                    <select id="requestTo" name="requestTo" class="form-select" required>
                        <option value="">Select recipient</option>
                        <option value="hod">Head of Department</option>
                        <option value="lecturer">Course Lecturer</option>
                    </select>
                </div>

                <!-- Course (shown only when lecturer is selected) -->
                <div class="col-md-6" id="courseSelectionContainer" style="display: none;">
                    <label for="courseId" class="form-label">Course</label>
                    <select id="courseId" name="courseId" class="form-select">
                        <option value="">Select course</option>
                        <option value="1">Computer Science 101</option>
                        <option value="2">Mathematics for Computing</option>
                        <option value="3">Database Systems</option>
                        <option value="4">Web Development</option>
                        <option value="5">Software Engineering</option>
                    </select>
                </div>

                <!-- From Date -->
                <div class="col-md-6">
                    <label for="fromDate" class="form-label">From Date</label>
                    <input type="date" id="fromDate" name="fromDate" class="form-control" required>
                </div>

                <!-- To Date -->
                <div class="col-md-6">
                    <label for="toDate" class="form-label">To Date</label>
                    <input type="date" id="toDate" name="toDate" class="form-control" required>
                </div>

                <!-- Reason -->
                <div class="col-12">
                    <label for="reason" class="form-label">Reason for Leave</label>
                    <textarea id="reason" name="reason" class="form-control" rows="4" maxlength="500"
                              placeholder="Please explain why you need leave..." required></textarea>
                    <div class="form-text">
                        <small id="reasonCount" class="text-muted">0/500 characters</small>
                    </div>
                </div>

                <!-- Supporting Document -->
                <div class="col-12">
                    <label for="supportingFile" class="form-label">Supporting Document (Optional)</label>
                    <input type="file" id="supportingFile" name="supportingFile" class="form-control"
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <div class="form-text">
                        <small class="text-muted">Allowed formats: PDF, DOC, DOCX, JPG, PNG. Max size: 5MB</small>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="col-12">
                    <div class="d-flex gap-2">
                        <button type="submit" id="submitBtn" class="btn btn-primary flex-fill">
                            <span class="spinner-border spinner-border-sm d-none me-2" role="status" aria-hidden="true"></span>
                            <i class="fas fa-paper-plane me-2"></i>Submit Leave Request
                        </button>
                        <button type="button" id="cancelBtn" class="btn btn-secondary d-none" onclick="cancelEdit()">
                            <i class="fas fa-times me-2"></i>Cancel Edit
                        </button>
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
                <thead>
                    <tr>
                        <th class="text-center">#</th>
                        <th>Reason</th>
                        <th class="text-center">From Date</th>
                        <th class="text-center">To Date</th>
                        <th class="text-center">Document</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Requested At</th>
                        <th class="text-center">Actions</th>
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
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('fromDate').min = today;
        document.getElementById('toDate').min = today;

        // Handle recipient selection
        document.getElementById('requestTo').addEventListener('change', function() {
            const courseContainer = document.getElementById('courseSelectionContainer');
            const courseSelect = document.getElementById('courseId');

            if (this.value === 'lecturer') {
                courseContainer.style.display = 'block';
                courseSelect.required = true;
            } else {
                courseContainer.style.display = 'none';
                courseSelect.required = false;
                courseSelect.value = '';
            }
        });

        // Date validation
        document.getElementById('fromDate').addEventListener('change', function() {
            document.getElementById('toDate').min = this.value;
            if (document.getElementById('toDate').value && document.getElementById('toDate').value < this.value) {
                document.getElementById('toDate').value = '';
            }
        });

        // Character counter for reason field
        document.getElementById('reason').addEventListener('input', function() {
            const maxLength = 500;
            const currentLength = this.value.length;
            const countElement = document.getElementById('reasonCount');

            countElement.textContent = `${currentLength}/500 characters`;

            // Color coding
            countElement.classList.remove('text-warning', 'text-danger');
            if (currentLength > 450) {
                countElement.classList.add('text-danger');
            } else if (currentLength > 400) {
                countElement.classList.add('text-warning');
            }

            // Prevent exceeding limit
            if (currentLength > maxLength) {
                this.value = this.value.substring(0, maxLength);
                countElement.textContent = `${maxLength}/500 characters`;
            }
        });

        // File validation
        document.getElementById('supportingFile').addEventListener('change', function() {
            if (this.files.length > 0) {
                const file = this.files[0];
                const maxSize = 5 * 1024 * 1024; // 5MB
                const allowedTypes = ['application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'image/jpeg', 'image/png'];

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

        // Form submission
        document.getElementById('leaveRequestForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const spinner = submitBtn.querySelector('.spinner-border');
            const isUpdate = document.getElementById('updateRequestId').value !== '';

            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${isUpdate ? 'Updating...' : 'Submitting...'}`;
        });

        // Reset form after successful submission (this would be handled by page redirect in real implementation)
        function resetForm() {
            document.getElementById('leaveRequestForm').reset();
            document.getElementById('courseSelectionContainer').style.display = 'none';
            document.getElementById('courseId').required = false;
            document.getElementById('updateRequestId').value = '';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Leave Request';
            document.getElementById('cancelBtn').classList.add('d-none');
            document.getElementById('reasonCount').textContent = '0/500 characters';
        }

        function cancelEdit() {
            resetForm();
            showAlert('Edit cancelled. Form reset to create new request.', 'success');
        }

        // Load leave requests
        loadLeaveRequests();
    });

    function showAlert(message, type) {
        const alertContainer = document.getElementById('alertContainer');
        const alertClass = type === 'danger' ? 'alert-danger' : 'alert-success';

        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
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

            // Show appropriate action buttons based on status
            let actionButtons = '';

            if (req.status === 'pending') {
                // For pending requests: Edit and Cancel (delete)
                actionButtons = `
                    <div class="btn-group" role="group">
                        <button class="btn btn-sm btn-outline-primary" onclick="editRequest(${req.id})" title="Edit Request">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger" onclick="cancelRequest(${req.id}, 'delete')" title="Cancel Request">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                `;
            } else if (req.status === 'approved' || req.status === 'rejected') {
                // For approved/rejected requests: Cancel (withdraw)
                actionButtons = `
                    <button class="btn btn-sm btn-outline-warning" onclick="cancelRequest(${req.id}, 'withdraw')" title="Withdraw Request">
                        <i class="fas fa-undo me-1"></i>Withdraw
                    </button>
                `;
            } else {
                actionButtons = `<span class="text-muted small">No actions available</span>`;
            }

            return `
                <tr>
                    <td class="text-center">${index + 1}</td>
                    <td>${reasonText}</td>
                    <td class="text-center">${fromDate}</td>
                    <td class="text-center">${toDate}</td>
                    <td class="text-center">
                        ${req.supporting_file ?
                            `<a href="uploads/leave_docs/${req.supporting_file}" target="_blank" class="btn btn-sm btn-outline-primary" title="View Document">
                                <i class="fas fa-eye"></i>
                            </a>` :
                            `<span class="text-muted">No file</span>`
                        }
                    </td>
                    <td class="text-center">
                        <span class="badge ${getStatusClass(req.status)}">
                            ${req.status.charAt(0).toUpperCase() + req.status.slice(1)}
                        </span>
                    </td>
                    <td class="text-center">
                        <div>
                            <div class="small">${formatDate(req.requested_at)}</div>
                            <div class="small text-muted">${formatTime(req.requested_at)}</div>
                        </div>
                    </td>
                    <td class="text-center">
                        ${actionButtons}
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

    function getStatusClass(status) {
        const classes = {
            'pending': 'bg-warning text-dark',
            'approved': 'bg-success',
            'rejected': 'bg-danger'
        };
        return classes[status] || 'bg-secondary';
    }

    function getStatusIcon(status) {
        const icons = {
            'pending': 'fa-clock',
            'approved': 'fa-check',
            'rejected': 'fa-times'
        };
        return icons[status] || 'fa-question';
    }

    function editRequest(requestId) {
        // Find the request data
        const leaveData = <?php echo json_encode($leaveRequests); ?>;
        const request = leaveData.find(req => req.id == requestId);

        if (!request) {
            alert('Request not found');
            return;
        }

        // Parse the reason to extract dates and reason text
        const reasonParts = request.reason.split('-- Details --');
        const mainReason = reasonParts[0].trim();

        // Extract dates from the reason text
        const fromDateMatch = request.reason.match(/From:\s*([^-\n\r]+)/);
        const toDateMatch = request.reason.match(/To:\s*([^-\n\r]+)/);

        const fromDate = fromDateMatch ? fromDateMatch[1].trim() : '';
        const toDate = toDateMatch ? toDateMatch[1].trim() : '';

        // Populate the form with existing data
        document.getElementById('requestTo').value = request.request_to || 'hod';
        document.getElementById('fromDate').value = fromDate;
        document.getElementById('toDate').value = toDate;
        document.getElementById('reason').value = mainReason;

        // Handle course selection if applicable
        if (request.request_to === 'lecturer' && request.course_id) {
            document.getElementById('courseId').value = request.course_id;
            document.getElementById('courseSelectionContainer').style.display = 'block';
            document.getElementById('courseId').required = true;
        }

        // Update character count
        const reasonTextarea = document.getElementById('reason');
        const countElement = document.getElementById('reasonCount');
        countElement.textContent = `${reasonTextarea.value.length}/500 characters`;

        // Scroll to form and highlight it
        document.getElementById('leaveRequestForm').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('leaveRequestForm').style.boxShadow = '0 0 0 3px rgba(0, 123, 255, 0.25)';

        // Reset shadow after animation
        setTimeout(() => {
            document.getElementById('leaveRequestForm').style.boxShadow = '';
        }, 2000);

        // Change submit button text and show cancel button
        const submitBtn = document.getElementById('submitBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Request';
        cancelBtn.classList.remove('d-none');

        // Set update mode
        document.getElementById('updateRequestId').value = requestId;

        // Show success message
        showAlert('Request loaded for editing. Make your changes and click Update.', 'success');
    }

    function cancelRequest(requestId, action) {
        const actionText = action === 'delete' ? 'delete' : 'withdraw';
        const confirmMessage = action === 'delete'
            ? 'Are you sure you want to delete this leave request? This action cannot be undone.'
            : 'Are you sure you want to withdraw this leave request? The recipient will be notified.';

        if (confirm(confirmMessage)) {
            // Create a form to submit the cancellation
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'submit-leave.php';
            form.style.display = 'none';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = 'csrf_token';
            csrfToken.value = '<?php echo generate_csrf_token(); ?>';
            form.appendChild(csrfToken);

            // Add request ID
            const requestIdField = document.createElement('input');
            requestIdField.type = 'hidden';
            requestIdField.name = 'cancel_request_id';
            requestIdField.value = requestId;
            form.appendChild(requestIdField);

            // Add action type
            const actionField = document.createElement('input');
            actionField.type = 'hidden';
            actionField.name = 'cancel_action';
            actionField.value = action;
            form.appendChild(actionField);

            // Add to body and submit
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
</body>
</html>