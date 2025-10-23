<?php
session_start();
require_once "config.php";

$lecturer_id = $_SESSION['lecturer_id'] ?? null;
$user_id = $_SESSION['user_id'] ?? null;

echo "<h2>Debugging Lecturer Courses</h2>";
echo "<p><strong>Session lecturer_id:</strong> " . ($lecturer_id ?? 'NULL') . "</p>";
echo "<p><strong>Session user_id:</strong> " . ($user_id ?? 'NULL') . "</p>";

if (!$lecturer_id) {
    echo "<p style='color: red;'>ERROR: No lecturer_id in session!</p>";
    exit;
}

// Check lecturers table
echo "<h3>1. Lecturer Record:</h3>";
$lecturer_stmt = $pdo->prepare("SELECT * FROM lecturers WHERE id = ?");
$lecturer_stmt->execute([$lecturer_id]);
$lecturer = $lecturer_stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($lecturer);
echo "</pre>";

// Check courses table
echo "<h3>2. All Courses in Database:</h3>";
$all_courses = $pdo->query("SELECT id, course_code, course_name, lecturer_id, status FROM courses LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($all_courses);
echo "</pre>";

// Check courses for this lecturer
echo "<h3>3. Courses for lecturer_id = $lecturer_id:</h3>";
$my_courses_stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.course_code,
        c.name as course_name,
        c.lecturer_id,
        c.status,
        d.name as department_name
    FROM courses c
    LEFT JOIN departments d ON c.department_id = d.id
    WHERE c.lecturer_id = ?
");
$my_courses_stmt->execute([$lecturer_id]);
$my_courses = $my_courses_stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p><strong>Found: " . count($my_courses) . " courses</strong></p>";
echo "<pre>";
print_r($my_courses);
echo "</pre>";

// Check if lecturer_id is correct type
echo "<h3>4. Data Type Check:</h3>";
echo "<p>Session lecturer_id type: " . gettype($lecturer_id) . "</p>";
if (!empty($all_courses)) {
    echo "<p>Database lecturer_id type in courses: " . gettype($all_courses[0]['lecturer_id']) . "</p>";
    echo "<p>Sample lecturer_id from database: " . $all_courses[0]['lecturer_id'] . "</p>";
}

// Try with explicit casting
echo "<h3>5. Try with CAST:</h3>";
$cast_stmt = $pdo->prepare("
    SELECT id, course_code, name, lecturer_id
    FROM courses
    WHERE CAST(lecturer_id AS UNSIGNED) = CAST(? AS UNSIGNED)
");
$cast_stmt->execute([$lecturer_id]);
$cast_results = $cast_stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p><strong>Found with CAST: " . count($cast_results) . " courses</strong></p>";
echo "<pre>";
print_r($cast_results);
echo "</pre>";

// Check user info
echo "<h3>6. User Info:</h3>";
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($user);
echo "</pre>";
?>
