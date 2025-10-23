<?php
// Show PHP errors directly in browser
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>Error Log Viewer</h2>";

// Common error log locations
$log_locations = [
    'd:\xampp\apache\logs\error.log',
    'd:\xampp\php\logs\php_error_log',
    'C:\xampp\apache\logs\error.log',
    'C:\xampp\php\logs\php_error_log',
    ini_get('error_log')
];

echo "<h3>Checking log locations:</h3>";
foreach ($log_locations as $log) {
    if ($log && file_exists($log)) {
        echo "<p><strong>Found log at:</strong> $log</p>";
        echo "<h4>Last 50 lines:</h4>";
        echo "<pre style='background: #f5f5f5; padding: 10px; overflow: auto; max-height: 500px;'>";
        $lines = file($log);
        $last_lines = array_slice($lines, -50);
        foreach ($last_lines as $line) {
            // Highlight lines containing "lecturer-my-courses"
            if (strpos($line, 'lecturer-my-courses') !== false || 
                strpos($line, 'PDO') !== false ||
                strpos($line, 'courses') !== false) {
                echo "<span style='background: yellow;'>$line</span>";
            } else {
                echo htmlspecialchars($line);
            }
        }
        echo "</pre>";
    } else {
        echo "<p>❌ Not found: $log</p>";
    }
}

// Also show PHP info about error logging
echo "<h3>PHP Error Configuration:</h3>";
echo "<pre>";
echo "error_log: " . ini_get('error_log') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "</pre>";

// Try to trigger the page and capture output
echo "<h3>Test lecturer-my-courses.php:</h3>";
echo "<p><a href='lecturer-my-courses.php?debug=1' target='_blank'>Open lecturer-my-courses.php?debug=1</a></p>";
?>
