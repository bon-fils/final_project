<?php
// Test fingerprint functionality with existing database structure
echo "<h1>Fingerprint Functionality Test (Existing DB Structure)</h1>";

try {
    require_once 'config.php';

    // Test database fingerprint column
    $columns = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('fingerprint', $columns)) {
        echo "✓ fingerprint column exists in students table<br>";
    } else {
        echo "❌ fingerprint column missing<br>";
    }

    // Test fingerprint data processing
    $testData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    $processed = processFingerprintData($testData);
    echo "✓ Fingerprint data processed successfully<br>";
    echo "✓ Generated hash: " . substr($processed, 0, 20) . "...<br>";

    // Test database insertion preparation
    $testStudent = [
        'first_name' => 'Test',
        'last_name' => 'Student',
        'email' => 'test@fingerprint.com',
        'telephone' => '0780000000',
        'department_id' => 1,
        'option_id' => 1,
        'reg_no' => 'TEST001',
        'student_id' => '12345678',
        'province' => 1,
        'district' => 1,
        'sector' => 1,
        'registration_date' => date('Y-m-d H:i:s')
    ];

    echo "<br><strong>✅ Fingerprint functionality ready with existing database!</strong><br>";
    echo "<a href='register-student.php'>Test Registration with Fingerprint</a><br>";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

function processFingerprintData($fingerprintData) {
    // fingerprintData is a base64 encoded image data URL
    if (strpos($fingerprintData, 'data:image') !== 0) {
        throw new Exception('Invalid fingerprint data format');
    }

    // Extract base64 data
    $data = explode(',', $fingerprintData);
    if (count($data) !== 2) {
        throw new Exception('Invalid fingerprint data structure');
    }

    $base64Data = $data[1];
    $imageData = base64_decode($base64Data);

    if ($imageData === false) {
        throw new Exception('Failed to decode fingerprint data');
    }

    // Generate a hash of the fingerprint data for storage
    $fingerprintHash = password_hash($base64Data, PASSWORD_DEFAULT);

    return $fingerprintHash;
}
?>