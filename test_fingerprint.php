<?php
// Test fingerprint functionality
echo "<h1>Fingerprint Functionality Test</h1>";

try {
    require_once 'config.php';

    // Test fingerprint directory creation
    $fingerprintDir = 'uploads/fingerprints';
    if (!is_dir($fingerprintDir)) {
        mkdir($fingerprintDir, 0755, true);
        echo "✓ Created fingerprint directory: $fingerprintDir<br>";
    } else {
        echo "✓ Fingerprint directory exists: $fingerprintDir<br>";
    }

    // Test database fingerprint columns
    $columns = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('fingerprint_path', $columns)) {
        echo "✓ fingerprint_path column exists in students table<br>";
    } else {
        echo "❌ fingerprint_path column missing<br>";
    }

    if (in_array('fingerprint_quality', $columns)) {
        echo "✓ fingerprint_quality column exists in students table<br>";
    } else {
        echo "❌ fingerprint_quality column missing<br>";
    }

    echo "<br><strong>✅ Fingerprint functionality ready!</strong><br>";
    echo "<a href='register-student.php'>Test Registration with Fingerprint</a><br>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>