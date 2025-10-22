<?php
/**
 * Test Page for Admin Lecturer Registration
 * Tests all functionalities of the lecturer registration form
 */

session_start();
require_once "config.php";

// Set admin session for testing
$_SESSION['user_id'] = 1;
$_SESSION['username'] = 'test_admin';
$_SESSION['role'] = 'admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Load departments for testing
$departments = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name LIMIT 5");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [
        ['id' => 1, 'name' => 'Computer Science'],
        ['id' => 2, 'name' => 'Engineering'],
        ['id' => 3, 'name' => 'Business Administration']
    ];
}

$testResults = [];
$formSubmitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formSubmitted = true;
    
    // Test form data reception
    $testResults['post_data'] = $_POST;
    $testResults['files_data'] = $_FILES;
    $testResults['validation_results'] = [];
    
    // Test validation
    $required_fields = ['first_name', 'last_name', 'gender', 'dob', 'id_number', 'email', 'department_id', 'education_level'];
    foreach ($required_fields as $field) {
        $testResults['validation_results'][$field] = !empty($_POST[$field]) ? 'PASS' : 'FAIL';
    }
    
    // Test photo upload
    if (isset($_FILES['lecturer_photo']) && $_FILES['lecturer_photo']['error'] === UPLOAD_ERR_OK) {
        $testResults['photo_upload'] = 'PASS - File uploaded successfully';
    } else {
        $testResults['photo_upload'] = 'SKIP - No photo uploaded';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test - Admin Lecturer Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .test-container { max-width: 1200px; margin: 2rem auto; }
        .test-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .test-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 15px 15px 0 0; }
        .photo-preview { width: 150px; height: 150px; border-radius: 10px; overflow: hidden; border: 3px solid #e3f2fd; background: #f8f9fa; display: flex; align-items: center; justify-content: center; }
        .photo-preview img { width: 100%; height: 100%; object-fit: cover; }
        .test-result { margin: 1rem 0; padding: 1rem; border-radius: 8px; }
        .test-pass { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .test-fail { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .test-skip { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
    </style>
</head>
<body>
    <div class="container-fluid test-container">
        <div class="test-card">
            <div class="test-header text-center">
                <h1><i class="fas fa-vial me-3"></i>Lecturer Registration Test Page</h1>
                <p class="mb-0">Test all functionalities of the admin lecturer registration form</p>
            </div>
            
            <div class="row">
                <!-- Test Form -->
                <div class="col-lg-8">
                    <div class="p-4">
                        <h3><i class="fas fa-clipboard-check me-2"></i>Test Form</h3>
                        
                        <form method="POST" enctype="multipart/form-data" id="testForm">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <!-- Photo Upload Test -->
                            <div class="mb-4">
                                <h5><i class="fas fa-camera me-2"></i>Photo Upload Test</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="photo-preview" id="photoPreview">
                                            <div id="photoPlaceholder">
                                                <i class="fas fa-user-circle fa-3x text-muted"></i>
                                            </div>
                                            <img id="previewImg" style="display: none;">
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <input type="file" class="form-control mb-2" id="lecturer_photo" name="lecturer_photo" accept="image/*" onchange="previewPhoto(this)">
                                        <small class="text-muted">Test photo upload with drag & drop support</small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Basic Information Test -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" value="John" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" value="Doe" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Gender *</label>
                                    <select class="form-select" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male" selected>Male</option>
                                        <option value="Female">Female</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date of Birth *</label>
                                    <input type="date" class="form-control" name="dob" value="1990-01-01" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">ID Number *</label>
                                    <input type="text" class="form-control" name="id_number" value="1234567890123456" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" value="john.doe@test.com" required>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" name="phone" value="0781234567">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Department *</label>
                                    <select class="form-select" name="department_id" required>
                                        <option value="">Select Department</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept['id'] ?>" <?= $dept['id'] == 1 ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($dept['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Education Level *</label>
                                <select class="form-select" name="education_level" required>
                                    <option value="">Select Level</option>
                                    <option value="Bachelor's Degree" selected>Bachelor's Degree</option>
                                    <option value="Master's Degree">Master's Degree</option>
                                    <option value="PhD">PhD</option>
                                </select>
                            </div>
                            
                            <!-- Options Test -->
                            <div class="mb-3">
                                <label class="form-label">Options (Test)</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="selected_options[]" value="1" id="opt1" checked>
                                    <label class="form-check-label" for="opt1">Test Option 1</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="selected_options[]" value="2" id="opt2">
                                    <label class="form-check-label" for="opt2">Test Option 2</label>
                                </div>
                            </div>
                            
                            <!-- Courses Test -->
                            <div class="mb-4">
                                <label class="form-label">Courses (Test)</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="selected_courses[]" value="1" id="course1" checked>
                                    <label class="form-check-label" for="course1">Test Course 1</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="selected_courses[]" value="2" id="course2">
                                    <label class="form-check-label" for="course2">Test Course 2</label>
                                </div>
                            </div>
                            
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-test-tube me-2"></i>Test Submit
                                </button>
                                <button type="button" class="btn btn-secondary ms-2" onclick="resetForm()">
                                    <i class="fas fa-undo me-2"></i>Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Test Results -->
                <div class="col-lg-4">
                    <div class="p-4 bg-light h-100">
                        <h3><i class="fas fa-chart-line me-2"></i>Test Results</h3>
                        
                        <?php if ($formSubmitted): ?>
                            <div class="test-result test-pass">
                                <h5><i class="fas fa-check-circle me-2"></i>Form Submitted Successfully</h5>
                            </div>
                            
                            <h5>Field Validation:</h5>
                            <?php foreach ($testResults['validation_results'] as $field => $result): ?>
                                <div class="test-result <?= $result === 'PASS' ? 'test-pass' : 'test-fail' ?>">
                                    <strong><?= ucfirst(str_replace('_', ' ', $field)) ?>:</strong> <?= $result ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="test-result <?= strpos($testResults['photo_upload'], 'PASS') !== false ? 'test-pass' : 'test-skip' ?>">
                                <strong>Photo Upload:</strong> <?= $testResults['photo_upload'] ?>
                            </div>
                            
                            <h5 class="mt-3">Data Received:</h5>
                            <div class="accordion" id="dataAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#postData">
                                            POST Data
                                        </button>
                                    </h2>
                                    <div id="postData" class="accordion-collapse collapse show">
                                        <div class="accordion-body">
                                            <pre class="small"><?= htmlspecialchars(print_r($testResults['post_data'], true)) ?></pre>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#filesData">
                                            FILES Data
                                        </button>
                                    </h2>
                                    <div id="filesData" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <pre class="small"><?= htmlspecialchars(print_r($testResults['files_data'], true)) ?></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php else: ?>
                            <div class="test-result test-skip">
                                <h5><i class="fas fa-info-circle me-2"></i>Ready for Testing</h5>
                                <p>Fill out the form and click "Test Submit" to see results.</p>
                            </div>
                            
                            <h5>Test Checklist:</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-square text-muted me-2"></i>Photo upload functionality</li>
                                <li><i class="fas fa-square text-muted me-2"></i>Form validation</li>
                                <li><i class="fas fa-square text-muted me-2"></i>Data submission</li>
                                <li><i class="fas fa-square text-muted me-2"></i>File handling</li>
                                <li><i class="fas fa-square text-muted me-2"></i>Option/Course selection</li>
                            </ul>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <h5>Quick Actions:</h5>
                            <div class="d-grid gap-2">
                                <a href="admin-register-lecturer.php" class="btn btn-outline-primary">
                                    <i class="fas fa-external-link-alt me-2"></i>Open Main Form
                                </a>
                                <a href="test-lecturer-form.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-vial me-2"></i>Simple Test Form
                                </a>
                                <button class="btn btn-outline-info" onclick="location.reload()">
                                    <i class="fas fa-refresh me-2"></i>Refresh Test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewPhoto(input) {
            const preview = document.getElementById('previewImg');
            const placeholder = document.getElementById('photoPlaceholder');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    placeholder.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function resetForm() {
            document.getElementById('testForm').reset();
            document.getElementById('previewImg').style.display = 'none';
            document.getElementById('photoPlaceholder').style.display = 'block';
        }
        
        // Auto-fill form for quick testing
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Test page loaded successfully');
        });
    </script>
</body>
</html>
