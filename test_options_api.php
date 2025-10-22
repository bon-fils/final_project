<?php
/**
 * Test Options API - Direct Database Test
 */

require_once "config.php";
session_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Test Options API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Test Options API for Department 7</h1>";

try {
    // Test 1: Check session
    echo "<h2>1. Session Check</h2>";
    if (isset($_SESSION['user_id'])) {
        echo "<div class='success'>✅ User ID: {$_SESSION['user_id']}</div>";
        echo "<div class='success'>✅ Role: {$_SESSION['role']}</div>";
    } else {
        echo "<div class='error'>❌ No session found</div>";
    }
    
    // Test 2: Check options table structure
    echo "<h2>2. Options Table Structure</h2>";
    $columns = $pdo->query("DESCRIBE options")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$col['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test 3: Direct query
    echo "<h2>3. Direct Database Query</h2>";
    $stmt = $pdo->prepare("SELECT * FROM options WHERE department_id = 7");
    $stmt->execute();
    $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>Found " . count($options) . " options for department 7</div>";
    
    if (!empty($options)) {
        echo "<table>";
        $headers = array_keys($options[0]);
        echo "<tr>";
        foreach ($headers as $header) {
            echo "<th>$header</th>";
        }
        echo "</tr>";
        
        foreach ($options as $option) {
            echo "<tr>";
            foreach ($option as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 4: Test the API query specifically
    echo "<h2>4. API Query Test</h2>";
    try {
        $api_stmt = $pdo->prepare("
            SELECT id, name, 
                   COALESCE(description, '') as description,
                   COALESCE(created_at, NOW()) as created_at
            FROM options 
            WHERE department_id = ? 
            ORDER BY name
        ");
        $api_stmt->execute([7]);
        $api_options = $api_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<div class='success'>✅ API query executed successfully</div>";
        echo "<pre>" . json_encode($api_options, JSON_PRETTY_PRINT) . "</pre>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ API query failed: " . $e->getMessage() . "</div>";
        
        // Try simpler query
        echo "<h3>Trying simpler query...</h3>";
        try {
            $simple_stmt = $pdo->prepare("SELECT id, name FROM options WHERE department_id = ?");
            $simple_stmt->execute([7]);
            $simple_options = $simple_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<div class='success'>✅ Simple query worked</div>";
            echo "<pre>" . json_encode($simple_options, JSON_PRETTY_PRINT) . "</pre>";
            
        } catch (Exception $e2) {
            echo "<div class='error'>❌ Even simple query failed: " . $e2->getMessage() . "</div>";
        }
    }
    
    // Test 5: Test API endpoint directly
    echo "<h2>5. API Endpoint Test</h2>";
    echo "<div id='api-result'></div>";
    echo "<button onclick='testAPI()'>Test Original API</button> ";
    echo "<button onclick='testFixedAPI()'>Test Fixed API</button>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "
<script>
async function testAPI() {
    const resultDiv = document.getElementById('api-result');
    resultDiv.innerHTML = '<div class=\"info\">Testing original API...</div>';
    
    try {
        const response = await fetch('api/get-options.php?department_id=7');
        const data = await response.json();
        
        resultDiv.innerHTML = '<h3>Original API Result:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    } catch (error) {
        resultDiv.innerHTML = '<div class=\"error\">❌ API Error: ' + error.message + '</div>';
    }
}

async function testFixedAPI() {
    const resultDiv = document.getElementById('api-result');
    resultDiv.innerHTML = '<div class=\"info\">Testing fixed API...</div>';
    
    try {
        const response = await fetch('api/get-options-fixed.php?department_id=7');
        const data = await response.json();
        
        resultDiv.innerHTML = '<h3>Fixed API Result:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
    } catch (error) {
        resultDiv.innerHTML = '<div class=\"error\">❌ Fixed API Error: ' + error.message + '</div>';
    }
}
</script>";

echo "</div></body></html>";
?>
