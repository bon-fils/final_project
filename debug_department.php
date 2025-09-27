<?php
session_start();
require_once "config.php";
require_once "session_check.php";
require_role(['hod']);

echo "<h1>Department Debug Information</h1>";

echo "<h3>Session Information:</h3>";
echo "<pre>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "\n";
echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "\n";
echo "Full Session: " . print_r($_SESSION, true) . "\n";
echo "</pre>";

echo "<h3>Database Information:</h3>";

// Check departments table
$deptStmt = $pdo->prepare("SELECT COUNT(*) as count FROM departments");
$deptStmt->execute();
$deptCount = $deptStmt->fetch(PDO::FETCH_ASSOC);
echo "<p>Total departments: <strong>" . $deptCount['count'] . "</strong></p>";

if ($deptCount['count'] > 0) {
    $deptListStmt = $pdo->prepare("SELECT id, name, hod_id FROM departments");
    $deptListStmt->execute();
    $departments = $deptListStmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Name</th><th>HoD ID</th></tr>";
    foreach ($departments as $dept) {
        echo "<tr>";
        echo "<td>{$dept['id']}</td>";
        echo "<td>{$dept['name']}</td>";
        echo "<td>{$dept['hod_id']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check current user's department
$hod_id = $_SESSION['user_id'];
$deptStmt = $pdo->prepare("SELECT id, name FROM departments WHERE hod_id = ?");
$deptStmt->execute([$hod_id]);
$department = $deptStmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Current User Department:</h3>";
if ($department) {
    echo "<p><strong>Found:</strong> {$department['name']} (ID: {$department['id']})</p>";
} else {
    echo "<p><strong style='color: red;'>Not found!</strong> No department assigned to HoD ID: $hod_id</p>";

    echo "<h4>Possible Solutions:</h4>";
    echo "<ol>";
    echo "<li>Check if your user ID ($hod_id) is properly set in the database</li>";
    echo "<li>Ensure you are assigned as HoD in the departments table</li>";
    echo "<li>Contact your administrator to assign you to a department</li>";
    echo "<li>Check if the departments table has the correct structure</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<p><a href='hod-dashboard.php'>← Back to Dashboard</a></p>";
?>