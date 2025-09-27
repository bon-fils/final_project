<?php
/**
 * Database Schema Update Script
 * Adds lecturer_id column to courses table for course assignment functionality
 */

require_once "config.php";

echo "<h1>🔧 Database Schema Update</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .warning { color: #ffc107; }
    .info { color: #17a2b8; }
    .card { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
</style>";

try {
    echo "<div class='card'>";
    echo "<h2>📊 Checking Database Connection...</h2>";

    // Test database connection
    $pdo->query("SELECT 1");
    echo "<p class='success'>✅ Database connection successful!</p>";
    echo "<p class='info'>Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "</p>";
    echo "</div>";

    // Check if courses table exists and has lecturer_id column
    echo "<div class='card'>";
    echo "<h2>🔍 Checking Courses Table Structure...</h2>";

    try {
        // Check if courses table exists
        $pdo->query("DESCRIBE courses");
        echo "<p class='success'>✅ Courses table exists</p>";

        // Check if lecturer_id column exists
        $stmt = $pdo->prepare("SHOW COLUMNS FROM courses LIKE 'lecturer_id'");
        $stmt->execute();
        $column_exists = $stmt->fetch();

        if ($column_exists) {
            echo "<p class='info'>ℹ️ lecturer_id column already exists in courses table</p>";
        } else {
            echo "<p class='warning'>⚠️ lecturer_id column missing from courses table</p>";
            echo "<p class='info'>Adding lecturer_id column to courses table...</p>";

            // Add lecturer_id column to courses table
            $pdo->exec("
                ALTER TABLE courses
                ADD COLUMN lecturer_id INT NULL AFTER status,
                ADD INDEX idx_lecturer_id (lecturer_id),
                ADD FOREIGN KEY (lecturer_id) REFERENCES lecturers(id) ON DELETE SET NULL
            ");

            echo "<p class='success'>✅ lecturer_id column added successfully</p>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>❌ Error checking courses table: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Check if activity_logs table exists for logging course assignments
    echo "<div class='card'>";
    echo "<h2>📋 Checking Activity Logs Table...</h2>";

    try {
        $pdo->query("DESCRIBE activity_logs");
        echo "<p class='success'>✅ Activity logs table exists</p>";
    } catch (Exception $e) {
        echo "<p class='info'>Creating activity_logs table for course assignment logging...</p>";

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_action (action),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        echo "<p class='success'>✅ Activity logs table created successfully</p>";
    }
    echo "</div>";

    // Update existing courses to have proper course_name column
    echo "<div class='card'>";
    echo "<h2>🔄 Updating Course Data Structure...</h2>";

    try {
        // Check if course_name column exists
        $stmt = $pdo->prepare("SHOW COLUMNS FROM courses LIKE 'course_name'");
        $stmt->execute();
        $course_name_exists = $stmt->fetch();

        if (!$course_name_exists) {
            echo "<p class='info'>Adding course_name column to courses table...</p>";

            // Add course_name column (copy from name column)
            $pdo->exec("ALTER TABLE courses ADD COLUMN course_name VARCHAR(100) NULL AFTER name");

            // Copy data from name to course_name
            $pdo->exec("UPDATE courses SET course_name = name WHERE course_name IS NULL");

            echo "<p class='success'>✅ course_name column added and populated</p>";
        } else {
            echo "<p class='info'>ℹ️ course_name column already exists</p>";
        }

    } catch (Exception $e) {
        echo "<p class='error'>❌ Error updating course structure: " . $e->getMessage() . "</p>";
    }
    echo "</div>";

    // Show final statistics
    echo "<div class='card'>";
    echo "<h2>📈 Final Database Statistics</h2>";

    $tables = ['courses', 'lecturers', 'departments', 'activity_logs'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch()['count'];
            echo "<p><strong>$table:</strong> $count records</p>";
        } catch (Exception $e) {
            echo "<p class='error'><strong>$table:</strong> Error - " . $e->getMessage() . "</p>";
        }
    }

    echo "<hr>";
    echo "<h3 class='success'>🎉 Database schema update completed successfully!</h3>";
    echo "<p>The database is now ready for course assignment functionality.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='hod-manage-lecturers.php' class='btn btn-primary' style='margin-right: 10px; padding: 10px 20px; text-decoration: none; background: #007bff; color: white; border-radius: 5px;'>Go to Manage Lecturers</a>";
    echo "<a href='hod-dashboard.php' class='btn btn-success' style='padding: 10px 20px; text-decoration: none; background: #28a745; color: white; border-radius: 5px;'>Go to HoD Dashboard</a>";
    echo "</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<h2 class='error'>❌ Database Update Failed</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<p>Please check your database configuration and try again.</p>";
    echo "</div>";
}

echo "<div class='card' style='margin-top: 30px; padding-bottom: 30px;'>";
echo "<h3>🔧 Troubleshooting</h3>";
echo "<p>If you encounter issues:</p>";
echo "<ol>";
echo "<li>Ensure your database server is running</li>";
echo "<li>Check that the database 'rp_attendance_system' exists</li>";
echo "<li>Verify your database credentials in config.php</li>";
echo "<li>Make sure you have the necessary permissions to modify tables</li>";
echo "</ol>";
echo "</div>";

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

.btn-success {
    background: #28a745;
    color: white;
}
</style>