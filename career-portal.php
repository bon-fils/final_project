<?php
/**
 * Career Portal - Student Portal
 * Access career resources, job listings, and internship opportunities
 */

session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['student']);

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

// Get career data
try {
    // Job listings
    $stmt = $pdo->prepare("
        SELECT * FROM job_listings
        WHERE status = 'active' AND application_deadline >= CURDATE()
        ORDER BY posted_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $job_listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Internship opportunities
    $stmt = $pdo->prepare("
        SELECT * FROM internship_opportunities
        WHERE status = 'active' AND application_deadline >= CURDATE()
        ORDER BY posted_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $internships = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Career resources
    $stmt = $pdo->prepare("
        SELECT * FROM career_resources
        WHERE status = 'active'
        ORDER BY created_at DESC
        LIMIT 6
    ");
    $stmt->execute();
    $career_resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Student's applications
    $stmt = $pdo->prepare("
        SELECT ja.*, jl.title as job_title, jl.company_name,
               io.title as internship_title, io.company_name as internship_company
        FROM job_applications ja
        LEFT JOIN job_listings jl ON ja.job_id = jl.id
        LEFT JOIN internship_applications ia ON ja.id = ia.application_id
        LEFT JOIN internship_opportunities io ON ia.internship_id = io.id
        WHERE ja.student_id = ?
        ORDER BY ja.applied_date DESC
        LIMIT 5
    ");
    $stmt->execute([$student['id']]);
    $my_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $job_listings = [];
    $internships = [];
    $career_resources = [];
    $my_applications = [];
}

// Calculate statistics
$total_jobs = count($job_listings);
$total_internships = count($internships);
$total_resources = count($career_resources);
$total_applications = count($my_applications);

// Set user role for frontend compatibility
$userRole = 'student';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Portal | Student | RP Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 250px;
            --primary-color: #0066cc;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
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

        .stats-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 20px;
        }

        .job-card, .internship-card, .resource-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            overflow: hidden;
            transition: transform 0.3s;
        }

        .job-card:hover, .internship-card:hover, .resource-card:hover {
            transform: translateY(-5px);
        }

        .job-header, .internship-header {
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            color: white;
            padding: 15px;
        }

        .resource-header {
            background: linear-gradient(135deg, var(--success-color), #218838);
            color: white;
            padding: 15px;
        }

        .job-title, .internship-title, .resource-title {
            font-weight: 600;
            margin: 0;
            font-size: 1.1rem;
        }

        .job-company, .internship-company {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        .job-body, .internship-body, .resource-body {
            padding: 15px;
        }

        .job-details, .internship-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-weight: 600;
            color: #333;
            font-size: 0.8rem;
            margin-bottom: 2px;
        }

        .detail-value {
            color: #666;
            font-size: 0.9rem;
        }

        .search-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .applications-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }

        .footer {
            text-align: center;
            margin-left: var(--sidebar-width);
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
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

            .job-details, .internship-details {
                grid-template-columns: 1fr;
                gap: 8px;
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

<div class="topbar">
    <h5 class="m-0 fw-bold">Career Portal</h5>
    <span>RP Attendance System</span>
</div>

<div class="main-content">
    <!-- Career Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-primary mb-2">
                    <i class="fas fa-briefcase fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo $total_jobs; ?></div>
                <div class="text-muted">Job Openings</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-info mb-2">
                    <i class="fas fa-user-graduate fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo $total_internships; ?></div>
                <div class="text-muted">Internships</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-success mb-2">
                    <i class="fas fa-book fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo $total_resources; ?></div>
                <div class="text-muted">Resources</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stats-card">
                <div class="text-warning mb-2">
                    <i class="fas fa-paper-plane fa-2x"></i>
                </div>
                <div class="h4 mb-1"><?php echo $total_applications; ?></div>
                <div class="text-muted">My Applications</div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="search-container">
        <h6 class="mb-3"><i class="fas fa-search me-2"></i>Find Opportunities</h6>
        <div class="row g-3">
            <div class="col-md-6">
                <input type="text" id="careerSearch" class="form-control" placeholder="Search jobs, internships, or companies...">
            </div>
            <div class="col-md-3">
                <select id="typeFilter" class="form-select">
                    <option value="">All Types</option>
                    <option value="job">Jobs Only</option>
                    <option value="internship">Internships Only</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="categoryFilter" class="form-select">
                    <option value="">All Categories</option>
                    <option value="technology">Technology</option>
                    <option value="finance">Finance</option>
                    <option value="healthcare">Healthcare</option>
                    <option value="education">Education</option>
                    <option value="engineering">Engineering</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        <div class="mt-3">
            <button class="btn btn-primary me-2" onclick="searchOpportunities()">
                <i class="fas fa-search me-1"></i>Search
            </button>
            <button class="btn btn-outline-secondary" onclick="clearFilters()">
                <i class="fas fa-times me-1"></i>Clear Filters
            </button>
        </div>
    </div>

    <!-- Job Listings -->
    <div class="mb-4">
        <h6 class="mb-3"><i class="fas fa-briefcase me-2"></i>Latest Job Openings</h6>
        <div class="row" id="jobsContainer">
            <?php if (count($job_listings) > 0): ?>
                <?php foreach ($job_listings as $job): ?>
                    <div class="col-md-6 col-lg-4 job-item">
                        <div class="job-card">
                            <div class="job-header">
                                <h6 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h6>
                                <div class="job-company"><?php echo htmlspecialchars($job['company_name']); ?></div>
                            </div>
                            <div class="job-body">
                                <div class="job-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Location</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($job['location'] ?? 'Remote'); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Salary</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($job['salary_range'] ?? 'Competitive'); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Deadline</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($job['application_deadline'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Type</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($job['job_type'] ?? 'Full-time'); ?></div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-primary btn-sm flex-fill" onclick="applyForJob(<?php echo $job['id']; ?>)">
                                        <i class="fas fa-paper-plane me-1"></i>Apply
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="viewJobDetails(<?php echo $job['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-briefcase"></i>
                        <h6>No Job Openings</h6>
                        <p>New opportunities will be posted soon.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Internship Opportunities -->
    <div class="mb-4">
        <h6 class="mb-3"><i class="fas fa-user-graduate me-2"></i>Internship Opportunities</h6>
        <div class="row" id="internshipsContainer">
            <?php if (count($internships) > 0): ?>
                <?php foreach ($internships as $internship): ?>
                    <div class="col-md-6 col-lg-4 internship-item">
                        <div class="internship-card">
                            <div class="internship-header">
                                <h6 class="internship-title"><?php echo htmlspecialchars($internship['title']); ?></h6>
                                <div class="internship-company"><?php echo htmlspecialchars($internship['company_name']); ?></div>
                            </div>
                            <div class="internship-body">
                                <div class="internship-details">
                                    <div class="detail-item">
                                        <div class="detail-label">Duration</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($internship['duration_months'] ?? '3-6'); ?> months</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Location</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($internship['location'] ?? 'On-site'); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Stipend</div>
                                        <div class="detail-value"><?php echo htmlspecialchars($internship['stipend'] ?? 'Paid'); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Deadline</div>
                                        <div class="detail-value"><?php echo date('M d, Y', strtotime($internship['application_deadline'])); ?></div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-info btn-sm flex-fill" onclick="applyForInternship(<?php echo $internship['id']; ?>)">
                                        <i class="fas fa-paper-plane me-1"></i>Apply
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="viewInternshipDetails(<?php echo $internship['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-user-graduate"></i>
                        <h6>No Internship Opportunities</h6>
                        <p>New internship positions will be available soon.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Career Resources -->
    <div class="mb-4">
        <h6 class="mb-3"><i class="fas fa-book me-2"></i>Career Resources</h6>
        <div class="row" id="resourcesContainer">
            <?php if (count($career_resources) > 0): ?>
                <?php foreach ($career_resources as $resource): ?>
                    <div class="col-md-6 col-lg-4 resource-item">
                        <div class="resource-card">
                            <div class="resource-header">
                                <h6 class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></h6>
                            </div>
                            <div class="resource-body">
                                <p class="text-muted small mb-3"><?php echo htmlspecialchars(substr($resource['description'], 0, 100)); ?>...</p>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm flex-fill" onclick="accessResource(<?php echo $resource['id']; ?>)">
                                        <i class="fas fa-external-link-alt me-1"></i>Access
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" onclick="downloadResource(<?php echo $resource['id']; ?>)">
                                        <i class="fas fa-download me-1"></i>Download
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-book"></i>
                        <h6>No Resources Available</h6>
                        <p>Career resources are being prepared.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- My Applications -->
    <div class="applications-section">
        <h6 class="mb-3"><i class="fas fa-paper-plane me-2"></i>My Applications</h6>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Company</th>
                        <th>Type</th>
                        <th>Applied Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="applicationsTable">
                    <?php if (count($my_applications) > 0): ?>
                        <?php foreach ($my_applications as $app): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($app['job_title'] ?: $app['internship_title']); ?></td>
                                <td><?php echo htmlspecialchars($app['company_name'] ?: $app['internship_company']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $app['job_title'] ? 'primary' : 'info'; ?>">
                                        <?php echo $app['job_title'] ? 'Job' : 'Internship'; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['applied_date'])); ?></td>
                                <td>
                                    <span class="badge bg-warning">Under Review</span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewApplication(<?php echo $app['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-paper-plane"></i>
                                    <h6>No Applications Yet</h6>
                                    <p>You haven't applied for any positions yet.</p>
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
// Career functions
function searchOpportunities() {
    const query = document.getElementById('careerSearch').value.toLowerCase();
    const type = document.getElementById('typeFilter').value;
    const category = document.getElementById('categoryFilter').value;

    showNotification('Search functionality will be implemented with backend integration.', 'info');
}

function clearFilters() {
    document.getElementById('careerSearch').value = '';
    document.getElementById('typeFilter').value = '';
    document.getElementById('categoryFilter').value = '';
    showNotification('Filters cleared', 'info');
}

function applyForJob(jobId) {
    if (confirm('Are you sure you want to apply for this job?')) {
        showNotification('Application submitted successfully! This feature will be fully implemented with backend.', 'success');
    }
}

function applyForInternship(internshipId) {
    if (confirm('Are you sure you want to apply for this internship?')) {
        showNotification('Application submitted successfully! This feature will be fully implemented with backend.', 'success');
    }
}

function viewJobDetails(jobId) {
    showNotification('Job details view will be implemented. Job ID: ' + jobId, 'info');
}

function viewInternshipDetails(internshipId) {
    showNotification('Internship details view will be implemented. Internship ID: ' + internshipId, 'info');
}

function accessResource(resourceId) {
    showNotification('Resource access will be implemented. Resource ID: ' + resourceId, 'info');
}

function downloadResource(resourceId) {
    showNotification('Resource download will be implemented. Resource ID: ' + resourceId, 'info');
}

function viewApplication(applicationId) {
    showNotification('Application details view will be implemented. Application ID: ' + applicationId, 'info');
}

// Notification system
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' :
                        type === 'error' ? 'alert-danger' :
                        type === 'warning' ? 'alert-warning' : 'alert-info';

    const icon = type === 'success' ? 'fas fa-check-circle' :
                  type === 'error' ? 'fas fa-exclamation-triangle' :
                  type === 'warning' ? 'fas fa-exclamation-circle' : 'fas fa-info-circle';

    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 350px; max-width: 500px;';
    alert.innerHTML = `
        <div class="d-flex align-items-start">
            <i class="${icon} me-2 mt-1"></i>
            <div class="flex-grow-1">
                <div class="fw-bold">${type.toUpperCase()}</div>
                <div>${message}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

    document.body.appendChild(alert);

    setTimeout(() => {
        if (alert.parentNode) {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 300);
        }
    }, 4000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl+F to focus search
    if (e.ctrlKey && e.key === 'f') {
        e.preventDefault();
        document.getElementById('careerSearch').focus();
    }
});
</script>

</body>
</html>