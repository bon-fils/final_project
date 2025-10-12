<?php
/**
 * Add biometric_method column to attendance_sessions table
 */

require_once "config.php";

echo "<h1>🔧 Adding Biometric Method Column</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .info { color: #17a2b8; }
    .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>📊 Checking Database Connection...</h2>";

    // Test database connection
    $pdo->query("SELECT 1");
    echo "<p class='success'>✅ Database connection successful!</p>";
    echo "</div>";

    // Check if biometric_method column exists
    echo "<div class='card'>";
    echo "<h2>🔍 Checking biometric_method column...</h2>";

    $stmt = $pdo->query("DESCRIBE attendance_sessions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('biometric_method', $columns)) {
        echo "<p class='success'>✅ biometric_method column already exists</p>";
    } else {
        echo "<p class='info'>Adding biometric_method column...</p>";

        $pdo->exec("
            ALTER TABLE attendance_sessions
            ADD COLUMN biometric_method ENUM('face_recognition', 'fingerprint') NOT NULL DEFAULT 'face_recognition'
            AFTER end_time
        ");

        echo "<p class='success'>✅ biometric_method column added successfully</p>";
    }
    echo "</div>";

    // Verify the column was added
    echo "<div class='card'>";
    echo "<h2>✅ Verification</h2>";

    $stmt = $pdo->query("DESCRIBE attendance_sessions");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>attendance_sessions table structure:</strong></p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li><strong>{$column['Field']}</strong> - {$column['Type']} " .
             ($column['Null'] === 'NO' ? '(NOT NULL)' : '(NULL)') .
             ($column['Default'] ? " DEFAULT '{$column['Default']}'" : "") . "</li>";
    }
    echo "</ul>";
    echo "</div>";

    echo "<div class='card'>";
    echo "<h2 class='success'>🎉 Database update completed!</h2>";
    echo "<p>The biometric method selection feature is now ready to use.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='attendance-session.php' class='btn btn-primary' style='margin-right: 10px; padding: 10px 20px; text-decoration: none; background: #007bff; color: white; border-radius: 5px;'>Go to Attendance Session</a>";
    echo "</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<h2 class='error'>❌ Database Update Failed</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?>

<style>
.btn {
    display: inline-block;
    padding: 10px 20px;
    text-decoration: none;
    border-radius: 5px;
    font-weight: bold;
    transition: background-color 0.3s;
}

.btn:hover {
    opacity: 0.9;
}

.btn-primary {
    background: #007bff;
    color: white;
}
</style>