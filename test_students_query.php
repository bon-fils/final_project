<?php
require_once "config.php";

try {
    echo "<h2>Testing Simple Students Query:</h2>";
    
    // Test 1: Simple select with alias
    echo "<h3>Test 1: Simple SELECT with alias</h3>";
    $stmt = $pdo->query("SELECT s.id, s.reg_no, s.year_level FROM students s LIMIT 3");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($results, true) . "</pre>";
    
    // Test 2: JOIN with users
    echo "<h3>Test 2: JOIN with users table</h3>";
    $stmt = $pdo->query("
        SELECT s.id, s.reg_no, u.first_name, u.last_name 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        LIMIT 3
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($results, true) . "</pre>";
    
    // Test 3: JOIN with options
    echo "<h3>Test 3: JOIN with options table</h3>";
    $stmt = $pdo->query("
        SELECT s.id, s.reg_no, o.name as program_name 
        FROM students s 
        JOIN options o ON s.option_id = o.id 
        LIMIT 3
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($results, true) . "</pre>";
    
    // Test 4: Full JOIN like in hod-students.php
    echo "<h3>Test 4: Full JOIN like in hod-students.php</h3>";
    $stmt = $pdo->query("
        SELECT s.id, s.user_id, s.year_level, s.student_id_number, s.reg_no,
                u.first_name, u.last_name, u.email, u.status, u.phone, u.created_at,
                o.name as program_name, o.id as option_id
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN options o ON s.option_id = o.id
        WHERE o.department_id = 7
        LIMIT 3
    ");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>" . print_r($results, true) . "</pre>";
    
} catch (PDOException $e) {
    echo "<div style='color: red;'>";
    echo "<h3>Error:</h3>";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Code:</strong> " . $e->getCode() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?>
