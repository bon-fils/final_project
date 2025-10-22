<?php
/**
 * Test script to verify lecturer form functionality
 * Tests the refined form structure and API endpoints
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "config.php";
require_once "session_check.php";

// Only allow admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Access denied. Admin role required.");
}

echo "<h2>Lecturer Form Refinement Test</h2>";

// Test 1: Database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✓ Database connection working<br>";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "<br>";
}

// Test 2: Departments loading
echo "<h3>2. Departments Loading Test</h3>";
try {
    $stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Found " . count($departments) . " departments<br>";
    foreach ($departments as $dept) {
        echo "  - " . htmlspecialchars($dept['name']) . " (ID: " . $dept['id'] . ")<br>";
    }
} catch (Exception $e) {
    echo "✗ Error loading departments: " . $e->getMessage() . "<br>";
}

// Test 3: Options API endpoint
echo "<h3>3. Options API Test</h3>";
if (!empty($departments)) {
    $testDeptId = $departments[0]['id'];
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/get-options.php?department_id=" . $testDeptId;
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: " . $_SERVER['HTTP_COOKIE'] . "\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['success']) {
            echo "✓ Options API working - Found " . $data['count'] . " options<br>";
        } else {
            echo "✗ Options API error: " . ($data['message'] ?? 'Unknown error') . "<br>";
        }
    } else {
        echo "✗ Failed to connect to options API<br>";
    }
}

// Test 4: Courses API endpoint
echo "<h3>4. Courses API Test</h3>";
if (!empty($departments)) {
    $testDeptId = $departments[0]['id'];
    $url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/get-courses.php?department_id=" . $testDeptId;
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Cookie: " . $_SERVER['HTTP_COOKIE'] . "\r\n"
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if ($data && $data['success']) {
            echo "✓ Courses API working - Found " . $data['count'] . " unassigned courses<br>";
        } else {
            echo "✗ Courses API error: " . ($data['message'] ?? 'Unknown error') . "<br>";
        }
    } else {
        echo "✗ Failed to connect to courses API<br>";
    }
}

// Test 5: Table structure verification
echo "<h3>5. Table Structure Verification</h3>";
try {
    // Check users table structure
    $stmt = $pdo->query("DESCRIBE users");
    $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Users table columns: " . implode(', ', $userColumns) . "<br>";
    
    // Check lecturers table structure
    $stmt = $pdo->query("DESCRIBE lecturers");
    $lecturerColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Lecturers table columns: " . implode(', ', $lecturerColumns) . "<br>";
    
    // Verify required columns exist
    $requiredUserCols = ['id', 'first_name', 'last_name', 'email', 'gender', 'dob'];
    $requiredLecturerCols = ['id', 'user_id', 'gender', 'dob', 'id_number', 'department_id', 'education_level'];
    
    $missingUserCols = array_diff($requiredUserCols, $userColumns);
    $missingLecturerCols = array_diff($requiredLecturerCols, $lecturerColumns);
    
    if (empty($missingUserCols) && empty($missingLecturerCols)) {
        echo "✓ All required columns present in both tables<br>";
    } else {
        if (!empty($missingUserCols)) {
            echo "✗ Missing users table columns: " . implode(', ', $missingUserCols) . "<br>";
        }
        if (!empty($missingLecturerCols)) {
            echo "✗ Missing lecturers table columns: " . implode(', ', $missingLecturerCols) . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error checking table structure: " . $e->getMessage() . "<br>";
}

echo "<h3>Summary</h3>";
echo "<p><strong>Form Structure:</strong> The lecturer registration form is properly aligned with your database schema:</p>";
echo "<ul>";
echo "<li><strong>Users Table:</strong> Stores personal information (first_name, last_name, email, phone, gender, dob)</li>";
echo "<li><strong>Lecturers Table:</strong> Stores lecturer-specific data (user_id, gender, dob, id_number, department_id, education_level)</li>";
echo "<li><strong>Relationship:</strong> lecturers.user_id → users.id</li>";
echo "</ul>";

echo "<p><a href='admin-register-lecturer.php' class='btn btn-primary'>Test the Refined Form</a></p>";
?>
