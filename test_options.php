<?php
require_once "config.php";

try {
    echo "Testing options table:\n\n";

    // Check if options table exists and has data
    $stmt = $pdo->query("SHOW TABLES LIKE 'options'");
    if ($stmt->rowCount() == 0) {
        echo "Options table does not exist!\n";
        exit;
    }

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM options");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total options in database: " . $count['count'] . "\n";

    if ($count['count'] > 0) {
        echo "\nSample options:\n";
        $stmt = $pdo->query("SELECT * FROM options LIMIT 5");
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($options as $option) {
            echo "  ID: {$option['id']}, Name: {$option['name']}, Dept ID: {$option['department_id']}\n";
        }

        echo "\nOptions by department:\n";
        $stmt = $pdo->query("
            SELECT o.department_id, d.name as dept_name, COUNT(*) as option_count
            FROM options o
            LEFT JOIN departments d ON o.department_id = d.id
            GROUP BY o.department_id, d.name
        ");
        $dept_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($dept_options as $dept) {
            echo "  Department {$dept['department_id']} ({$dept['dept_name']}): {$dept['option_count']} options\n";
        }
    } else {
        echo "\nNo options found. This explains why the API is failing.\n";
        echo "You need to create some options for the departments first.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>