<?php
/**
 * Test if PHP can call Python script
 */

echo "<h1>Testing Python Call from PHP</h1>";

// Test 1: Check if python is available
echo "<h2>Test 1: Python Version</h2>";
$pythonVersion = shell_exec('python --version 2>&1');
echo "<pre>$pythonVersion</pre>";

// Test 2: Simple Python script
echo "<h2>Test 2: Simple Python Script</h2>";
$simpleOutput = shell_exec('python -c "print(\'Hello from Python!\')" 2>&1');
echo "<pre>$simpleOutput</pre>";

// Test 3: Test JSON output
echo "<h2>Test 3: JSON Output</h2>";
$jsonOutput = shell_exec('python -c "import json; print(json.dumps({\'status\': \'success\', \'message\': \'Test\'}))" 2>&1');
echo "<pre>$jsonOutput</pre>";
$decoded = json_decode($jsonOutput, true);
if ($decoded) {
    echo "<p style='color: green;'>✅ JSON parsing successful!</p>";
    echo "<pre>" . print_r($decoded, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ JSON parsing failed: " . json_last_error_msg() . "</p>";
}

// Test 4: Test face recognition script exists
echo "<h2>Test 4: Face Recognition Script</h2>";
$scriptPath = __DIR__ . '/face_recognition_compare.py';
if (file_exists($scriptPath)) {
    echo "<p style='color: green;'>✅ Script exists: $scriptPath</p>";
    
    // Try to run it without arguments to see error message
    $testOutput = shell_exec("python \"$scriptPath\" 2>&1");
    echo "<h3>Script Output (no args):</h3>";
    echo "<pre>$testOutput</pre>";
} else {
    echo "<p style='color: red;'>❌ Script not found: $scriptPath</p>";
}

// Test 5: Test with dummy image
echo "<h2>Test 5: Test with Dummy Image</h2>";
$tempPath = __DIR__ . '/temp';
if (!is_dir($tempPath)) {
    mkdir($tempPath, 0777, true);
}

// Create a simple test image
$testImage = $tempPath . '/test.jpg';
$img = imagecreatetruecolor(100, 100);
imagejpeg($img, $testImage);
imagedestroy($img);

if (file_exists($testImage)) {
    echo "<p style='color: green;'>✅ Test image created: $testImage</p>";
    
    // Try to run face recognition on it
    $command = sprintf('python "%s" "%s" 1 2>&1', $scriptPath, $testImage);
    echo "<p><strong>Command:</strong> $command</p>";
    
    $output = shell_exec($command);
    echo "<h3>Output:</h3>";
    echo "<pre>$output</pre>";
    
    // Try to parse as JSON
    $result = json_decode($output, true);
    if ($result) {
        echo "<p style='color: green;'>✅ JSON parsing successful!</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>❌ JSON parsing failed: " . json_last_error_msg() . "</p>";
    }
    
    // Clean up
    unlink($testImage);
} else {
    echo "<p style='color: red;'>❌ Failed to create test image</p>";
}

echo "<hr>";
echo "<p><a href='attendance-session.php'>← Back to Attendance Session</a></p>";
?>
