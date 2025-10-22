<?php
session_start();
require_once "config.php";

// Simple test form for lecturer registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h1>Form Submission Test Results</h1>";
    
    echo "<h2>POST Data Received:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h2>FILES Data Received:</h2>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    echo "<h2>Session Data:</h2>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    
    // Test database connection
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM departments");
        $count = $stmt->fetchColumn();
        echo "<h2>Database Connection: ✅ Working</h2>";
        echo "<p>Found {$count} departments in database</p>";
    } catch (Exception $e) {
        echo "<h2>Database Connection: ❌ Failed</h2>";
        echo "<p>Error: " . $e->getMessage() . "</p>";
    }
    
    echo "<hr>";
    echo "<a href='test-lecturer-form.php'>Test Again</a> | ";
    echo "<a href='admin-register-lecturer.php'>Back to Registration Form</a>";
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lecturer Form Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>🧪 Lecturer Registration Form Test</h1>
        <p>This is a simplified test to verify form submission is working.</p>
        
        <div class="alert alert-info">
            <h5>Test Instructions:</h5>
            <ol>
                <li>Fill out the form below with test data</li>
                <li>Click "Test Submit"</li>
                <li>Check if the data is received properly</li>
                <li>If this works, the issue is in the main registration form processing</li>
            </ol>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="border p-4 rounded">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">First Name *</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" 
                           value="John" required>
                </div>
                
                <div class="col-md-6">
                    <label for="last_name" class="form-label">Last Name *</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="Doe" required>
                </div>
                
                <div class="col-md-6">
                    <label for="gender" class="form-label">Gender *</label>
                    <select class="form-select" id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" selected>Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="dob" class="form-label">Date of Birth *</label>
                    <input type="date" class="form-control" id="dob" name="dob" 
                           value="1990-01-01" required>
                </div>
                
                <div class="col-md-6">
                    <label for="id_number" class="form-label">ID Number *</label>
                    <input type="text" class="form-control" id="id_number" name="id_number" 
                           value="1234567890123456" required>
                </div>
                
                <div class="col-md-6">
                    <label for="email" class="form-label">Email *</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="john.doe@test.com" required>
                </div>
                
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone" 
                           value="0781234567">
                </div>
                
                <div class="col-md-6">
                    <label for="department_id" class="form-label">Department *</label>
                    <select class="form-select" id="department_id" name="department_id" required>
                        <option value="">Select Department</option>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
                            while ($dept = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $selected = ($dept['id'] == 1) ? 'selected' : '';
                                echo "<option value='{$dept['id']}' {$selected}>{$dept['name']}</option>";
                            }
                        } catch (Exception $e) {
                            echo "<option value=''>Error loading departments</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="education_level" class="form-label">Education Level *</label>
                    <select class="form-select" id="education_level" name="education_level" required>
                        <option value="">Select Education Level</option>
                        <option value="Bachelor's Degree" selected>Bachelor's Degree</option>
                        <option value="Master's Degree">Master's Degree</option>
                        <option value="PhD">PhD</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <label for="lecturer_photo" class="form-label">Photo (Optional)</label>
                    <input type="file" class="form-control" id="lecturer_photo" name="lecturer_photo" 
                           accept="image/*">
                </div>
                
                <div class="col-12">
                    <h5>Test Options</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="selected_options[]" 
                               value="1" id="option1">
                        <label class="form-check-label" for="option1">
                            Test Option 1
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="selected_options[]" 
                               value="2" id="option2" checked>
                        <label class="form-check-label" for="option2">
                            Test Option 2
                        </label>
                    </div>
                </div>
                
                <div class="col-12">
                    <h5>Test Courses</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="selected_courses[]" 
                               value="1" id="course1" checked>
                        <label class="form-check-label" for="course1">
                            Test Course 1
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-test me-2"></i>Test Submit
                </button>
                <a href="admin-register-lecturer.php" class="btn btn-secondary ms-2">
                    Back to Main Form
                </a>
            </div>
        </form>
        
        <div class="mt-4">
            <h3>Troubleshooting Tips:</h3>
            <ul>
                <li><strong>If this test works:</strong> The issue is in the main form's validation or processing logic</li>
                <li><strong>If this test fails:</strong> There's a server configuration or PHP issue</li>
                <li><strong>Check browser console:</strong> Look for JavaScript errors</li>
                <li><strong>Check server logs:</strong> Look for PHP errors in error_log</li>
            </ul>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
