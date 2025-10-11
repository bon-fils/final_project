<?php
/**
 * Import Locations SQL File
 * Imports the locations.sql file into the database
 */

require_once "config.php";

echo "<h1>🌍 Importing Rwanda Locations Data</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
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
    echo "</div>";

    // Check if locations table exists
    echo "<div class='card'>";
    echo "<h2>📍 Checking Locations Table...</h2>";

    try {
        $pdo->query("DESCRIBE locations");
        echo "<p class='success'>✅ Locations table already exists</p>";

        // Check if table has data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM locations");
        $count = $stmt->fetch()['count'];
        echo "<p class='info'>Current records: $count</p>";

        if ($count > 0) {
            echo "<p class='warning'>⚠️ Table already has data. Skipping import.</p>";
            echo "<p>If you want to re-import, please truncate the table first.</p>";
            echo "</div>";
            exit;
        }

    } catch (Exception $e) {
        echo "<p class='info'>Locations table doesn't exist. It will be created by the SQL import.</p>";
    }
    echo "</div>";

    // Read the SQL file
    echo "<div class='card'>";
    echo "<h2>📖 Reading SQL File...</h2>";

    $sqlFile = "c:/Users/Bonfils/Downloads/locations.sql";

    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
    echo "<p class='success'>✅ SQL file read successfully (" . strlen($sql) . " characters)</p>";
    echo "</div>";

    // Split SQL into individual statements
    echo "<div class='card'>";
    echo "<h2>🔄 Executing SQL Statements...</h2>";

    // Remove comments and split by semicolon
    $sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments

    // Split by semicolon but keep CREATE TABLE and INSERT statements together
    $statements = [];
    $currentStatement = '';
    $inCreateTable = false;
    $inInsert = false;

    $lines = explode("\n", $sql);
    foreach ($lines as $line) {
        $line = trim($line);

        if (empty($line)) continue;

        if (stripos($line, 'CREATE TABLE') === 0) {
            if (!empty($currentStatement)) {
                $statements[] = trim($currentStatement);
            }
            $currentStatement = $line;
            $inCreateTable = true;
        } elseif (stripos($line, 'INSERT INTO') === 0) {
            if (!empty($currentStatement)) {
                $statements[] = trim($currentStatement);
            }
            $currentStatement = $line;
            $inInsert = true;
        } elseif ($inCreateTable || $inInsert) {
            $currentStatement .= "\n" . $line;

            // Check if statement ends
            if (substr($line, -1) === ';') {
                if ($inCreateTable && stripos($line, ');') !== false) {
                    $statements[] = trim($currentStatement);
                    $currentStatement = '';
                    $inCreateTable = false;
                } elseif ($inInsert && substr($line, -2) === ');') {
                    $statements[] = trim($currentStatement);
                    $currentStatement = '';
                    $inInsert = false;
                }
            }
        } elseif (stripos($line, 'SET ') === 0 || stripos($line, 'COMMIT') === 0) {
            // Skip these statements
            continue;
        }
    }

    if (!empty($currentStatement)) {
        $statements[] = trim($currentStatement);
    }

    echo "<p class='info'>Found " . count($statements) . " SQL statements to execute</p>";

    // Execute statements
    $executed = 0;
    $errors = 0;

    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;

        try {
            $pdo->exec($statement);
            $executed++;
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error executing statement: " . substr($statement, 0, 100) . "...</p>";
            echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }

    echo "<p class='success'>✅ Successfully executed $executed statements</p>";
    if ($errors > 0) {
        echo "<p class='error'>❌ $errors statements failed</p>";
    }
    echo "</div>";

    // Verify import
    echo "<div class='card'>";
    echo "<h2>✅ Verifying Import...</h2>";

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM locations");
        $count = $stmt->fetch()['count'];
        echo "<p class='success'>✅ Total locations imported: $count</p>";

        // Show sample data
        $stmt = $pdo->query("SELECT province, district, sector, cell FROM locations LIMIT 5");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<p class='info'>Sample data:</p>";
        echo "<pre>";
        foreach ($samples as $sample) {
            echo "Province: {$sample['province']}, District: {$sample['district']}, Sector: {$sample['sector']}, Cell: {$sample['cell']}\n";
        }
        echo "</pre>";

    } catch (Exception $e) {
        echo "<p class='error'>❌ Error verifying import: " . $e->getMessage() . "</p>";
    }

    echo "</div>";

    echo "<div class='card'>";
    echo "<h2 class='success'>🎉 Import Completed!</h2>";
    echo "<p>The Rwanda locations data has been successfully imported.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='register-student.php' class='btn btn-primary' style='margin-right: 10px; padding: 10px 20px; text-decoration: none; background: #007bff; color: white; border-radius: 5px;'>Go to Student Registration</a>";
    echo "<a href='admin-dashboard.php' class='btn btn-success' style='padding: 10px 20px; text-decoration: none; background: #28a745; color: white; border-radius: 5px;'>Go to Admin Dashboard</a>";
    echo "</div>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='card'>";
    echo "<h2 class='error'>❌ Import Failed</h2>";
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

.btn-success {
    background: #28a745;
    color: white;
}
</style>